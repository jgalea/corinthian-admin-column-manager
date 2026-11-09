<?php

namespace Corinthian\Import;

use Corinthian\Plugin;
use Corinthian\Sets\ColumnSet;

/**
 * Imports column views saved by the Admin Columns plugin (free or Pro), so a
 * site can switch to Corinthian without rebuilding its views by hand.
 *
 * Reads the {prefix}admin_columns table (current versions) and the older
 * cpac_options_* options. Never writes to either.
 */
final class AdminColumnsImporter {

	/**
	 * Their column type => [our type, settings mapper].
	 *
	 * @var array<string,string>
	 */
	private const TYPE_MAP = array(
		'column-postid'                 => 'post.id',
		'column-slug'                   => 'post.slug',
		'column-excerpt'                => 'post.excerpt',
		'column-word_count'             => 'post.word_count',
		'column-estimated_reading_time' => 'post.reading_time',
		'column-character_count'        => 'post.char_count',
		'column-featured_image'         => 'post.featured_image',
		'column-permalink'              => 'post.permalink',
		'column-path'                   => 'post.relative_path',
		'column-shortlink'              => 'post.shortlink',
		'column-status'                 => 'post.status',
		'column-sticky'                 => 'post.sticky',
		'column-comment_count'          => 'post.comment_count',
		'column-comment_status'         => 'post.comment_status',
		'column-ping_status'            => 'post.ping_status',
		'column-author_name'            => 'post.author',
		'column-last_modified_author'   => 'post.last_modified_by',
		'column-date_published'         => 'post.published_date',
		'column-modified'               => 'post.modified_date',
		'column-parent'                 => 'post.parent',
		'column-order'                  => 'post.menu_order',
		'column-page_template'          => 'post.template',
		'column-post_formats'           => 'post.format',
		'column-taxonomy'               => 'post.taxonomy',
		'column-attachment'             => 'post.attachments_count',
		'column-meta'                   => 'post.meta',
		'column-guid'                   => 'post.guid',
		'column-shortcodes'             => 'post.shortcodes',
		'column-images'                 => 'post.images_count',
		'column-depth'                  => 'post.path_depth',
		'column-revisions'              => 'post.revisions_count',
	);

	public function __construct( private Plugin $plugin ) {}

	/**
	 * @return list<array{source:string,list_key:string,title:string,columns:list<array<string,mixed>>,settings:array<string,mixed>}>
	 */
	public function find_views(): array {
		global $wpdb;
		$views = array();
		$table = $wpdb->prefix . 'admin_columns';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is our source plugin's table name built from $wpdb->prefix; identifiers can't be bound.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			$rows = $wpdb->get_results( "SELECT id, list_key, title, columns, settings FROM {$table} ORDER BY id ASC", ARRAY_A );
			foreach ( $rows ?: array() as $row ) {
				$views[] = array(
					'source'   => 'table:' . $row['id'],
					'list_key' => (string) $row['list_key'],
					'title'    => (string) $row['title'],
					'columns'  => self::decode_list( (string) $row['columns'] ),
					'settings' => self::decode( (string) $row['settings'] ),
				);
			}
		}

		$options = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'cpac\\_options\\_%' AND option_name NOT LIKE '%\\_\\_default'", ARRAY_A );
		// phpcs:enable
		foreach ( $options ?: array() as $option ) {
			$columns = self::decode( (string) $option['option_value'] );
			if ( ! $columns ) {
				continue;
			}
			$views[] = array(
				'source'   => 'option:' . $option['option_name'],
				'list_key' => substr( (string) $option['option_name'], strlen( 'cpac_options_' ) ),
				'title'    => '',
				'columns'  => array_values( array_filter( $columns, 'is_array' ) ),
				'settings' => array(),
			);
		}

		return $views;
	}

	/**
	 * @param bool         $dry_run    Report what would happen without saving.
	 * @param list<string> $also_roles Roles that should also see views limited to specific users or roles.
	 * @return list<array{source:string,screen:string,status:string,set_id:int,imported:list<string>,skipped:list<string>}>
	 */
	public function run( bool $dry_run = false, array $also_roles = array() ): array {
		$report   = array();
		$existing = array();
		foreach ( $this->plugin->sets->all() as $set ) {
			$from = (string) ( $set->settings['imported_from'] ?? '' );
			if ( '' !== $from ) {
				$existing[ $from ] = $set;
			}
		}

		foreach ( $this->find_views() as $view ) {
			$screen = $this->screen_for( $view['list_key'] );
			$entry  = array(
				'source'   => $view['source'],
				'screen'   => $screen ?? $view['list_key'],
				'status'   => '',
				'set_id'   => 0,
				'imported' => array(),
				'skipped'  => array(),
			);
			if ( null === $screen ) {
				$entry['status'] = 'unsupported screen';
				$report[]        = $entry;
				continue;
			}

			// Columns this table showed the last time it was opened; empty until someone opens it.
			$native  = array_keys( $this->plugin->screens->get( $screen )->native_columns() );
			$columns = array();
			foreach ( $view['columns'] as $column ) {
				$mapped = $this->map_column( $column );
				if ( null !== $mapped && 'native' === $mapped['type'] && $native && ! in_array( $mapped['settings']['key'], $native, true ) ) {
					$mapped = null;
				}
				if ( null === $mapped ) {
					$entry['skipped'][] = (string) ( $column['type'] ?? '?' );
					continue;
				}
				$columns[]           = $mapped;
				$entry['imported'][] = (string) ( $column['type'] ?? '?' );
			}

			if ( ! $columns ) {
				$entry['status'] = 'no columns left';
				$report[]        = $entry;
				continue;
			}

			$data = array(
				'screen'   => $screen,
				'title'    => '' !== trim( $view['title'] ) ? $view['title'] : __( 'Imported view', 'corinthian-admin-column-manager' ),
				'columns'  => $columns,
				'settings' => self::widen( $this->map_settings( $view['settings'] ), $also_roles ) + array( 'imported_from' => $view['source'] ),
			);

			$previous = $existing[ $view['source'] ] ?? null;
			if ( $dry_run ) {
				$entry['status'] = $previous ? 'would update' : 'would create';
				$entry['set_id'] = $previous ? $previous->id : 0;
			} else {
				$set             = $this->plugin->sets->save( $data, $previous ? $previous->id : 0 );
				$entry['status'] = $previous ? 'updated' : 'created';
				$entry['set_id'] = $set->id;
			}
			$report[] = $entry;
		}
		return $report;
	}

	public function screen_for( string $list_key ): ?string {
		$fixed = array(
			'wp-users'    => 'users',
			'wp-media'    => 'media',
			'wp-comments' => 'comments',
			'wp-ms_users' => 'network-users',
			'wp-ms_sites' => 'network-sites',
		);
		if ( isset( $fixed[ $list_key ] ) ) {
			$key = $fixed[ $list_key ];
		} elseif ( str_starts_with( $list_key, 'wp-taxonomy_' ) ) {
			$key = 'term:' . substr( $list_key, strlen( 'wp-taxonomy_' ) );
		} elseif ( post_type_exists( $list_key ) ) {
			$key = 'post:' . $list_key;
		} else {
			return null;
		}
		return $this->plugin->screens->get( $key ) ? $key : null;
	}

	/**
	 * @param array<string,mixed> $column
	 * @return array<string,mixed>|null
	 */
	public function map_column( array $column ): ?array {
		$type  = (string) ( $column['type'] ?? '' );
		$label = trim( wp_strip_all_tags( (string) ( $column['label'] ?? '' ) ) );
		$width = (int) ( $column['width'] ?? 0 );
		$base  = array(
			'id'         => self::column_id( $column, $type ),
			'label'      => $label,
			'width'      => $width > 0 ? $width : null,
			'width_unit' => ( $column['width_unit'] ?? 'px' ) === '%' ? '%' : 'px',
			'settings'   => array(),
		);
		if ( 'off' === ( $column['sort'] ?? 'on' ) ) {
			$base['settings']['sortable'] = false;
		}

		if ( '' === $type ) {
			return null;
		}

		// ACF field keys: show the stored meta value until the ACF integration is active.
		if ( str_starts_with( $type, 'field_' ) ) {
			$field = $this->acf_field( $type );
			if ( ! $field ) {
				return null;
			}
			$registry_has_acf = (bool) $this->plugin->registry->get( 'acf.field' );
			return array(
				'type'     => $registry_has_acf ? 'acf.field' : 'post.meta',
				'label'    => '' !== $label ? $label : $field['label'],
				'settings' => $base['settings'] + ( $registry_has_acf
					? array( 'field_key' => $type )
					: array(
						'meta_key' => $field['name'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- A column setting name, not a query.
						'display'  => 'text',
					) ),
			) + $base;
		}

		if ( isset( self::TYPE_MAP[ $type ] ) ) {
			$ours = self::TYPE_MAP[ $type ];
			if ( ! $this->plugin->registry->get( $ours ) ) {
				return null;
			}
			$base['type']      = $ours;
			$base['settings'] += $this->map_type_settings( $type, $column );
			return $base;
		}

		// Anything else not prefixed "column-" is a WordPress or third-party column.
		if ( ! str_starts_with( $type, 'column-' ) ) {
			$base['type']            = 'native';
			$base['id']              = sanitize_key( $type );
			$base['settings']['key'] = $type;
			return $base;
		}

		return null;
	}

	/**
	 * @param array<string,mixed> $column
	 * @return array<string,mixed>
	 */
	private function map_type_settings( string $type, array $column ): array {
		$out = array();
		switch ( $type ) {
			case 'column-estimated_reading_time':
				$out['words_per_minute'] = max( 1, (int) ( $column['words_per_minute'] ?? 200 ) );
				break;
			case 'column-date_published':
			case 'column-modified':
				$format = (string) ( $column['date_format'] ?? '' );
				if ( '' === $format || 'wp_default' === $format ) {
					$out['format'] = 'wp';
				} elseif ( 'diff' === $format ) {
					$out['format'] = 'relative';
				} else {
					$out['format']        = 'custom';
					$out['custom_format'] = $format;
				}
				break;
			case 'column-excerpt':
				$out['words'] = max( 1, (int) ( $column['excerpt_length'] ?? 30 ) );
				break;
			case 'column-taxonomy':
				$out['taxonomy'] = sanitize_key( (string) ( $column['taxonomy'] ?? '' ) );
				break;
			case 'column-meta':
				$out['meta_key'] = (string) ( $column['field'] ?? '' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- A column setting name, not a query.
				$out['display']  = match ( (string) ( $column['field_type'] ?? '' ) ) {
					'numeric', 'number' => 'number',
					'date'              => 'date',
					'image', 'library_id' => 'image',
					'link'              => 'url',
					'checkmark'         => 'boolean',
					default             => 'text',
				};
				break;
			case 'column-featured_image':
				$out['size'] = (string) ( $column['image_size'] ?? 'thumbnail' );
				break;
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	private function map_settings( array $settings ): array {
		$out = array(
			'roles'             => array_values( array_map( 'strval', (array) ( $settings['roles'] ?? array() ) ) ),
			'users'             => array_values( array_filter( array_map( 'intval', (array) ( $settings['users'] ?? array() ) ) ) ),
			'horizontal_scroll' => 'on' === ( $settings['horizontal_scrolling'] ?? '' ),
		);
		if ( ! empty( $settings['sorting'] ) && is_string( $settings['sorting'] ) ) {
			$out['default_sort'] = array(
				'column' => $settings['sorting'],
				'order'  => 'asc' === strtolower( (string) ( $settings['sorting_order'] ?? 'desc' ) ) ? 'ASC' : 'DESC',
			);
		}
		return $out;
	}

	/**
	 * @return array{name:string,label:string}|null
	 */
	private function acf_field( string $key ): ?array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT post_title, post_excerpt FROM {$wpdb->posts} WHERE post_type = 'acf-field' AND post_name = %s LIMIT 1", $key ), ARRAY_A );
		if ( ! $row || '' === (string) $row['post_excerpt'] ) {
			return null;
		}
		return array(
			'name'  => (string) $row['post_excerpt'],
			'label' => (string) $row['post_title'],
		);
	}

	/**
	 * @param array<string,mixed> $column
	 */
	private static function column_id( array $column, string $type ): string {
		$name = sanitize_key( (string) ( $column['name'] ?? '' ) );
		return '' !== $name ? $name : sanitize_key( $type ) . '_' . substr( md5( (string) wp_json_encode( $column ) ), 0, 6 );
	}

	/**
	 * Stored values are PHP-serialized in some versions and JSON in others.
	 *
	 * @return array<mixed>
	 */
	private static function decode( string $raw ): array {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return array();
		}
		$json = json_decode( $raw, true );
		if ( is_array( $json ) ) {
			return $json;
		}
		if ( is_serialized( $raw ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- No objects allowed.
			$data = @unserialize( $raw, array( 'allowed_classes' => false ) );
			return is_array( $data ) ? $data : array();
		}
		return array();
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private static function decode_list( string $raw ): array {
		return array_values( array_filter( self::decode( $raw ), 'is_array' ) );
	}

	/**
	 * Let extra roles see a view that was limited to specific users or roles.
	 * An unrestricted view stays unrestricted: adding roles would narrow it.
	 *
	 * @param array<string,mixed> $settings
	 * @param list<string>        $also_roles
	 * @return array<string,mixed>
	 */
	public static function widen( array $settings, array $also_roles ): array {
		$roles = array_values( array_filter( (array) ( $settings['roles'] ?? array() ) ) );
		$users = array_values( array_filter( (array) ( $settings['users'] ?? array() ) ) );
		if ( ! $also_roles || ( ! $roles && ! $users ) ) {
			return $settings;
		}
		$settings['roles'] = array_values( array_unique( array_merge( $roles, $also_roles ) ) );
		return $settings;
	}

	public static function summary_line( ColumnSet $set ): string {
		return sprintf( '#%d %s (%s, %d columns)', $set->id, $set->title, $set->screen, count( $set->columns ) );
	}
}
