<?php

namespace Corinthian\Columns\Types;

use Closure;
use Corinthian\Columns\Config;
use Corinthian\Screens\Screen;

defined( 'ABSPATH' ) || exit;

/**
 * Smart-filter operators and SQL shared by the post column types.
 */
final class PostFilter {

	/**
	 * @param Closure(Config,Screen):list<array<string,mixed>>                        $operators
	 * @param Closure(Config,string,mixed,Screen,string):(array{join:string,where:string}|null) $condition
	 */
	public function __construct(
		private Closure $operators,
		private Closure $condition,
	) {}

	/**
	 * @return list<array<string,mixed>>
	 */
	public function operators( Config $config, Screen $screen ): array {
		return ( $this->operators )( $config, $screen );
	}

	/**
	 * @return array{join:string,where:string}|null
	 */
	public function condition( Config $config, string $op, mixed $value, Screen $screen, string $alias ): ?array {
		return ( $this->condition )( $config, $op, $value, $screen, $alias );
	}

	public static function text( string $column ): self {
		return new self(
			static fn() => self::text_ops(),
			static function ( Config $c, string $op, mixed $value ) use ( $column ) {
				global $wpdb;
				return self::wrap( self::text_where( "{$wpdb->posts}.{$column}", $op, $value ) );
			}
		);
	}

	public static function number( string $column, bool $allow_empty = true ): self {
		return new self(
			static fn() => self::number_ops( $allow_empty ),
			static function ( Config $c, string $op, mixed $value ) use ( $column, $allow_empty ) {
				global $wpdb;
				return $allow_empty || ! in_array( $op, array( 'empty', 'not_empty' ), true )
					? self::wrap( self::number_where( "{$wpdb->posts}.{$column}", $op, $value ) )
					: null;
			}
		);
	}

	/**
	 * Number of child posts of one kind, counted with a correlated subquery.
	 *
	 * @param 'attachment'|'revision'|'children' $kind
	 */
	public static function child_count( string $kind ): self {
		return new self(
			static fn() => self::number_ops( true ),
			static function ( Config $c, string $op, mixed $value, Screen $s, string $alias ) use ( $kind ) {
				global $wpdb;
				$where = match ( $kind ) {
					'attachment' => "{$alias}.post_type = 'attachment'",
					'revision'   => "{$alias}.post_type = 'revision'",
					default      => "{$alias}.post_type NOT IN ('attachment','revision','nav_menu_item') AND {$alias}.post_status NOT IN ('trash','auto-draft')",
				};
				$expr = "(SELECT COUNT(*) FROM {$wpdb->posts} AS {$alias} WHERE {$alias}.post_parent = {$wpdb->posts}.ID AND {$where})";
				return self::wrap( self::number_where( $expr, $op, $value ) );
			}
		);
	}

	/**
	 * @param Closure(Screen):array<string,string> $options
	 */
	public static function in( string $column, Closure $options, bool $ints = false ): self {
		return new self(
			static fn( Config $c, Screen $s ) => self::in_ops( $options( $s ) ),
			static function ( Config $c, string $op, mixed $value ) use ( $column, $ints ) {
				global $wpdb;
				return self::wrap( self::in_where( "{$wpdb->posts}.{$column}", $op, $value, $ints ) );
			}
		);
	}

	/**
	 * Posts whose single meta value is one of a list of ids.
	 *
	 * @param Closure(Screen):array<string,string> $options
	 */
	public static function meta_ids( string $meta_key, Closure $options ): self {
		return new self(
			static fn( Config $c, Screen $s ) => self::in_ops( $options( $s ) ),
			static function ( Config $c, string $op, mixed $value, Screen $s, string $alias ) use ( $meta_key ) {
				$ids = self::list_values( $value, true );
				if ( ! $ids || ! in_array( $op, array( 'in', 'not_in' ), true ) ) {
					return null;
				}
				global $wpdb;
				$marks  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
				$exists = $wpdb->prepare( "EXISTS (SELECT 1 FROM {$wpdb->postmeta} AS {$alias} WHERE {$alias}.post_id = {$wpdb->posts}.ID AND {$alias}.meta_key = %s AND CAST({$alias}.meta_value AS UNSIGNED) IN ({$marks}))", array_merge( array( $meta_key ), $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				return self::wrap( 'in' === $op ? $exists : "NOT {$exists}" );
			}
		);
	}

	public static function template(): self {
		return new self(
			static function ( Config $c, Screen $s ) {
				$options = array( 'default' => __( 'Default template', 'corinthian-admin-column-manager' ) );
				foreach ( wp_get_theme()->get_page_templates( null, $s->subtype() ) as $file => $name ) {
					$options[ (string) $file ] = (string) $name;
				}
				return self::in_ops( $options );
			},
			static function ( Config $c, string $op, mixed $value, Screen $s, string $alias ) {
				$slugs = self::list_values( $value, false );
				if ( ! $slugs || ! in_array( $op, array( 'in', 'not_in' ), true ) ) {
					return null;
				}
				global $wpdb;
				$parts = array();
				$named = array_values( array_diff( $slugs, array( 'default' ) ) );
				$base  = "SELECT 1 FROM {$wpdb->postmeta} AS {$alias} WHERE {$alias}.post_id = {$wpdb->posts}.ID AND {$alias}.meta_key = '_wp_page_template'";
				if ( $named ) {
					$marks   = implode( ',', array_fill( 0, count( $named ), '%s' ) );
					$parts[] = $wpdb->prepare( "EXISTS ({$base} AND {$alias}.meta_value IN ({$marks}))", $named ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				}
				if ( in_array( 'default', $slugs, true ) ) {
					$parts[] = "NOT EXISTS ({$base} AND {$alias}.meta_value NOT IN ('', 'default'))";
				}
				$any = '(' . implode( ' OR ', $parts ) . ')';
				return self::wrap( 'in' === $op ? $any : "NOT {$any}" );
			}
		);
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public static function text_ops(): array {
		return array_merge(
			array(
				array(
					'op'    => 'contains',
					'label' => __( 'contains', 'corinthian-admin-column-manager' ),
					'input' => 'text',
				),
				array(
					'op'    => 'not_contains',
					'label' => __( 'does not contain', 'corinthian-admin-column-manager' ),
					'input' => 'text',
				),
				array(
					'op'    => 'eq',
					'label' => __( 'is', 'corinthian-admin-column-manager' ),
					'input' => 'text',
				),
				array(
					'op'    => 'neq',
					'label' => __( 'is not', 'corinthian-admin-column-manager' ),
					'input' => 'text',
				),
				array(
					'op'    => 'starts',
					'label' => __( 'starts with', 'corinthian-admin-column-manager' ),
					'input' => 'text',
				),
			),
			self::empty_ops()
		);
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public static function number_ops( bool $allow_empty ): array {
		$ops = array(
			array(
				'op'    => 'eq',
				'label' => '=',
				'input' => 'number',
			),
			array(
				'op'    => 'neq',
				'label' => '≠',
				'input' => 'number',
			),
			array(
				'op'    => 'gt',
				'label' => '>',
				'input' => 'number',
			),
			array(
				'op'    => 'lt',
				'label' => '<',
				'input' => 'number',
			),
			array(
				'op'    => 'between',
				'label' => __( 'between', 'corinthian-admin-column-manager' ),
				'input' => 'number',
			),
		);
		if ( $allow_empty ) {
			$ops[] = array(
				'op'    => 'empty',
				'label' => __( 'is empty', 'corinthian-admin-column-manager' ),
				'input' => 'none',
			);
		}
		return $ops;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public static function date_ops(): array {
		return array(
			array(
				'op'    => 'before',
				'label' => __( 'before', 'corinthian-admin-column-manager' ),
				'input' => 'date',
			),
			array(
				'op'    => 'after',
				'label' => __( 'after', 'corinthian-admin-column-manager' ),
				'input' => 'date',
			),
			array(
				'op'    => 'within_days',
				'label' => __( 'in the last (days)', 'corinthian-admin-column-manager' ),
				'input' => 'number',
			),
			array(
				'op'    => 'older_than_days',
				'label' => __( 'older than (days)', 'corinthian-admin-column-manager' ),
				'input' => 'number',
			),
		);
	}

	/**
	 * @param array<string,string> $options
	 * @return list<array<string,mixed>>
	 */
	public static function in_ops( array $options, bool $with_empty = false ): array {
		$ops = array(
			array(
				'op'      => 'in',
				'label'   => __( 'is any of', 'corinthian-admin-column-manager' ),
				'input'   => 'select',
				'options' => $options,
			),
			array(
				'op'      => 'not_in',
				'label'   => __( 'is none of', 'corinthian-admin-column-manager' ),
				'input'   => 'select',
				'options' => $options,
			),
		);
		return $with_empty ? array_merge( $ops, self::empty_ops() ) : $ops;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public static function yes_no_ops(): array {
		return array(
			array(
				'op'      => 'eq',
				'label'   => __( 'is', 'corinthian-admin-column-manager' ),
				'input'   => 'select',
				'options' => array(
					'1' => __( 'Yes', 'corinthian-admin-column-manager' ),
					'0' => __( 'No', 'corinthian-admin-column-manager' ),
				),
			),
		);
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public static function empty_ops(): array {
		return array(
			array(
				'op'    => 'empty',
				'label' => __( 'is empty', 'corinthian-admin-column-manager' ),
				'input' => 'none',
			),
			array(
				'op'    => 'not_empty',
				'label' => __( 'is not empty', 'corinthian-admin-column-manager' ),
				'input' => 'none',
			),
		);
	}

	/**
	 * @return array{join:string,where:string}|null
	 */
	public static function wrap( ?string $where ): ?array {
		return null === $where || '' === $where ? null : array(
			'join'  => '',
			'where' => $where,
		);
	}

	public static function text_where( string $col, string $op, mixed $value ): ?string {
		global $wpdb;
		$v = is_scalar( $value ) ? (string) $value : null;
		return match ( $op ) {
			'empty'        => "({$col} IS NULL OR {$col} = '')",
			'not_empty'    => "({$col} IS NOT NULL AND {$col} <> '')",
			'contains'     => null !== $v && '' !== $v ? $wpdb->prepare( "{$col} LIKE %s", '%' . $wpdb->esc_like( $v ) . '%' ) : null, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'not_contains' => null !== $v && '' !== $v ? $wpdb->prepare( "({$col} IS NULL OR {$col} NOT LIKE %s)", '%' . $wpdb->esc_like( $v ) . '%' ) : null, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'starts'       => null !== $v && '' !== $v ? $wpdb->prepare( "{$col} LIKE %s", $wpdb->esc_like( $v ) . '%' ) : null, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'eq'           => null !== $v ? $wpdb->prepare( "{$col} = %s", $v ) : null, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'neq'          => null !== $v ? $wpdb->prepare( "({$col} IS NULL OR {$col} <> %s)", $v ) : null, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			default        => null,
		};
	}

	public static function number_where( string $expr, string $op, mixed $value ): ?string {
		global $wpdb;
		$v = is_scalar( $value ) && is_numeric( $value ) ? (float) $value : null;
		return match ( $op ) {
			'empty'     => "({$expr} IS NULL OR {$expr} = 0)",
			'not_empty' => "({$expr} IS NOT NULL AND {$expr} <> 0)",
			'eq'        => null !== $v ? $wpdb->prepare( "{$expr} = %f", $v ) : null, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'neq'       => null !== $v ? $wpdb->prepare( "({$expr} IS NULL OR {$expr} <> %f)", $v ) : null, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'gt'        => null !== $v ? $wpdb->prepare( "{$expr} > %f", $v ) : null, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'lt'        => null !== $v ? $wpdb->prepare( "{$expr} < %f", $v ) : null, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'between'   => is_array( $value ) && 2 === count( $value ) && is_numeric( $value[0] ) && is_numeric( $value[1] ) ? $wpdb->prepare( "{$expr} BETWEEN %f AND %f", (float) min( $value ), (float) max( $value ) ) : null, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			default     => null,
		};
	}

	/**
	 * $col is a local-time datetime column (post_date, post_modified). Day
	 * boundaries and "now" come from wp_date(), which uses the site timezone,
	 * so nothing is converted with get_gmt_from_date().
	 */
	public static function date_where( string $col, string $op, mixed $value ): ?string {
		global $wpdb;
		$real = "{$col} <> '0000-00-00 00:00:00'";
		if ( 'within_days' === $op || 'older_than_days' === $op ) {
			if ( ! is_scalar( $value ) || ! ctype_digit( (string) $value ) ) {
				return null;
			}
			$edge = wp_date( 'Y-m-d H:i:s', time() - (int) $value * DAY_IN_SECONDS );
			if ( 'older_than_days' === $op ) {
				return $wpdb->prepare( "({$real} AND {$col} < %s)", $edge ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			return $wpdb->prepare( "({$col} >= %s AND {$col} <= %s)", $edge, wp_date( 'Y-m-d H:i:s' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		if ( ( 'before' !== $op && 'after' !== $op ) || ! is_string( $value ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) || ! wp_checkdate( (int) $m[2], (int) $m[3], (int) $m[1], $value ) ) {
			return null;
		}
		if ( 'before' === $op ) {
			return $wpdb->prepare( "({$real} AND {$col} < %s)", $value . ' 00:00:00' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$next = gmdate( 'Y-m-d', (int) strtotime( $value . ' 00:00:00 UTC' ) + DAY_IN_SECONDS );
		return $wpdb->prepare( "{$col} >= %s", $next . ' 00:00:00' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function in_where( string $col, string $op, mixed $value, bool $ints = false ): ?string {
		global $wpdb;
		$list = self::list_values( $value, $ints );
		if ( ! $list || ! in_array( $op, array( 'in', 'not_in' ), true ) ) {
			return null;
		}
		$marks = implode( ',', array_fill( 0, count( $list ), $ints ? '%d' : '%s' ) );
		if ( 'in' === $op ) {
			return $wpdb->prepare( "{$col} IN ({$marks})", $list ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		}
		return $wpdb->prepare( "({$col} IS NULL OR {$col} NOT IN ({$marks}))", $list ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	}

	/**
	 * EXISTS subquery on the posts a term relationship points at. $extra is an
	 * already-prepared condition on {alias}_x (term_taxonomy) or {alias}_t (terms).
	 */
	public static function term_exists( string $alias, string $taxonomy, string $extra = '' ): string {
		global $wpdb;
		$sql = $wpdb->prepare( "EXISTS (SELECT 1 FROM {$wpdb->term_relationships} AS {$alias}_r INNER JOIN {$wpdb->term_taxonomy} AS {$alias}_x ON {$alias}_x.term_taxonomy_id = {$alias}_r.term_taxonomy_id INNER JOIN {$wpdb->terms} AS {$alias}_t ON {$alias}_t.term_id = {$alias}_x.term_id WHERE {$alias}_r.object_id = {$wpdb->posts}.ID AND {$alias}_x.taxonomy = %s", $taxonomy ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $sql . ( '' === $extra ? '' : " AND {$extra}" ) . ')';
	}

	public static function meta_exists( string $alias, string $meta_key, string $extra = '' ): string {
		global $wpdb;
		$sql = $wpdb->prepare( "EXISTS (SELECT 1 FROM {$wpdb->postmeta} AS {$alias} WHERE {$alias}.post_id = {$wpdb->posts}.ID AND {$alias}.meta_key = %s", $meta_key ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $sql . ( '' === $extra ? '' : " AND {$extra}" ) . ')';
	}

	public static function flag( mixed $value ): ?bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		$v = is_scalar( $value ) ? (string) $value : '';
		return '1' === $v ? true : ( '0' === $v ? false : null );
	}

	/**
	 * @return list<string>|list<int>
	 */
	public static function list_values( mixed $value, bool $ints ): array {
		$out = array();
		foreach ( is_array( $value ) ? $value : array( $value ) as $item ) {
			if ( ! is_scalar( $item ) || '' === (string) $item ) {
				continue;
			}
			if ( $ints ) {
				if ( absint( $item ) > 0 ) {
					$out[] = absint( $item );
				}
			} else {
				$out[] = (string) $item;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @return array<string,string>
	 */
	public static function author_options(): array {
		$out = array();
		foreach ( get_users(
			array(
				'number'  => 200,
				'orderby' => 'display_name',
				'fields'  => array( 'ID', 'display_name' ),
			)
		) as $user ) {
			$out[ (string) $user->ID ] = $user->display_name;
		}
		return $out;
	}
}
