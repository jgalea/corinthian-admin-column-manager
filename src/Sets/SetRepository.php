<?php

namespace Corinthian\Sets;

use Corinthian\Admin\TableUi;
use Corinthian\Columns\Config;
use Corinthian\Schema;
use Corinthian\Screens\Screen;

defined( 'ABSPATH' ) || exit;

final class SetRepository {

	public const ACTIVE_META_PREFIX = 'corinthian_active_';

	/** @var array<string,list<ColumnSet>> */
	private array $cache = array();

	/**
	 * @return list<ColumnSet>
	 */
	public function for_screen( string $screen ): array {
		if ( isset( $this->cache[ $screen ] ) ) {
			return $this->cache[ $screen ];
		}
		global $wpdb;
		$table = Schema::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table name from $wpdb->prefix, request-cached.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE screen = %s ORDER BY position ASC, id ASC", $screen ), ARRAY_A );

		$sets = array_merge(
			array_values( array_filter( CodeSets::all(), static fn( ColumnSet $s ) => $s->screen === $screen ) ),
			array_map( array( $this, 'hydrate' ), $rows ?? array() )
		);
		usort( $sets, static fn( ColumnSet $a, ColumnSet $b ) => array( $a->position, $a->id ) <=> array( $b->position, $b->id ) );

		$this->cache[ $screen ] = $sets;
		return $this->cache[ $screen ];
	}

	/**
	 * @return list<ColumnSet>
	 */
	public function all(): array {
		global $wpdb;
		$table = Schema::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table name from $wpdb->prefix.
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY screen ASC, position ASC, id ASC", ARRAY_A );
		return array_merge( CodeSets::all(), array_map( array( $this, 'hydrate' ), $rows ?? array() ) );
	}

	/**
	 * Whether any saved set has a column of one of these types.
	 *
	 * @param list<string> $types
	 */
	public function stored_uses_type( array $types ): bool {
		global $wpdb;
		$table = Schema::table();
		$likes = array_map( static fn( string $type ) => '%' . $wpdb->esc_like( '"type":"' . $type . '"' ) . '%', $types );
		$where = implode( ' OR ', array_fill( 0, count( $likes ), 'columns LIKE %s' ) );
		// A site that switched in from the network may not have the table yet.
		$quiet = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table name; the placeholders are generated from the count of $types.
		$found = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE {$where} LIMIT 1", $likes ) );
		$wpdb->suppress_errors( $quiet );
		return null !== $found;
	}

	public function find( int $id ): ?ColumnSet {
		if ( CodeSets::is_code_id( $id ) ) {
			foreach ( CodeSets::all() as $set ) {
				if ( $set->id === $id ) {
					return $set;
				}
			}
			return null;
		}
		global $wpdb;
		$table = Schema::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table name from $wpdb->prefix.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return $row ? $this->hydrate( $row ) : null;
	}

	/**
	 * The set this user sees on the screen: their own pick if still allowed,
	 * otherwise the first set their role may use. Null means WordPress defaults.
	 */
	public function active_for( Screen $screen, int $user_id ): ?ColumnSet {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return null;
		}
		$sets = array_values( array_filter( $this->for_screen( $screen->key() ), static fn( ColumnSet $s ) => $s->visible_to( $user ) ) );
		if ( ! $sets ) {
			return null;
		}
		$picked = (int) get_user_meta( $user_id, self::active_meta_key( $screen->key() ), true );
		foreach ( $sets as $set ) {
			if ( $set->id === $picked ) {
				return $set;
			}
		}
		return $sets[0];
	}

	public static function active_meta_key( string $screen ): string {
		return self::ACTIVE_META_PREFIX . md5( $screen );
	}

	/**
	 * @param array<string,mixed> $data screen, title, columns, settings, position.
	 */
	public function save( array $data, int $id = 0 ): ColumnSet {
		if ( CodeSets::is_code_id( $id ) ) {
			throw new \DomainException( 'Column sets defined in code are read-only.' );
		}
		global $wpdb;
		$now     = current_time( 'mysql', true );
		$columns = array();
		$seen    = array();
		foreach ( (array) ( $data['columns'] ?? array() ) as $column ) {
			$config = Config::from_array( (array) $column );
			if ( '' === $config->id || '' === $config->type || isset( $seen[ $config->id ] ) ) {
				continue;
			}
			$seen[ $config->id ] = true;
			$columns[]           = $config->to_array();
		}

		$row = array(
			'screen'     => sanitize_text_field( (string) ( $data['screen'] ?? '' ) ),
			'title'      => sanitize_text_field( (string) ( $data['title'] ?? '' ) ),
			'columns'    => (string) wp_json_encode( $columns ),
			'settings'   => (string) wp_json_encode( self::clean_settings( (array) ( $data['settings'] ?? array() ) ) ),
			'position'   => (int) ( $data['position'] ?? 0 ),
			'updated_at' => $now,
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		if ( $id > 0 ) {
			$wpdb->update( Schema::table(), $row, array( 'id' => $id ) );
		} else {
			$row['created_at'] = $now;
			$wpdb->insert( Schema::table(), $row );
			$id = (int) $wpdb->insert_id;
		}
		// phpcs:enable

		$this->cache = array();
		$set         = $this->find( $id );
		if ( ! $set ) {
			throw new \RuntimeException( 'Column set could not be saved.' );
		}
		/**
		 * Fires after a column set is saved.
		 */
		do_action( 'corinthian_set_saved', $set );
		return $set;
	}

	public function delete( int $id ): bool {
		if ( CodeSets::is_code_id( $id ) ) {
			return false;
		}
		global $wpdb;
		$set         = $this->find( $id );
		$this->cache = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = (bool) $wpdb->delete( Schema::table(), array( 'id' => $id ) );
		if ( $deleted && $set ) {
			delete_metadata( 'user', 0, TableUi::widths_meta_key( $set->screen, $id ), '', true );
			delete_metadata( 'user', 0, self::active_meta_key( $set->screen ), $id, true );
			/**
			 * Fires after a column set is deleted, so add-ons can drop data stored per set.
			 */
			do_action( 'corinthian_set_deleted', $id, $set->screen );
		}
		return $deleted;
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	private static function clean_settings( array $settings ): array {
		$roles = array_values( array_filter( array_map( 'sanitize_key', (array) ( $settings['roles'] ?? array() ) ) ) );
		$users = array_values( array_filter( array_map( 'absint', (array) ( $settings['users'] ?? array() ) ) ) );
		$own   = array(
			'roles'             => $roles,
			'users'             => $users,
			'horizontal_scroll' => ! empty( $settings['horizontal_scroll'] ),
			'sticky_header'     => ! empty( $settings['sticky_header'] ),
		);
		if ( absint( $settings['per_page'] ?? 0 ) > 0 ) {
			$own['per_page'] = min( 999, absint( $settings['per_page'] ) );
		}
		$by = sanitize_key( (string) ( $settings['default_orderby'] ?? '' ) );
		if ( '' !== $by ) {
			$own['default_orderby'] = $by;
			$own['default_order']   = 'desc' === strtolower( (string) ( $settings['default_order'] ?? '' ) ) ? 'desc' : 'asc';
		}
		$handled = array( 'roles', 'users', 'horizontal_scroll', 'sticky_header', 'per_page', 'default_orderby', 'default_order' );
		return $own + array_diff_key( $settings, array_flip( $handled ) );
	}

	/**
	 * @param array<string,mixed> $row
	 */
	private function hydrate( array $row ): ColumnSet {
		$columns  = json_decode( (string) $row['columns'], true );
		$settings = json_decode( (string) $row['settings'], true );
		return new ColumnSet(
			id: (int) $row['id'],
			screen: (string) $row['screen'],
			title: (string) $row['title'],
			columns: array_map( array( Config::class, 'from_array' ), is_array( $columns ) ? array_values( $columns ) : array() ),
			settings: is_array( $settings ) ? $settings : array(),
			position: (int) $row['position'],
		);
	}
}
