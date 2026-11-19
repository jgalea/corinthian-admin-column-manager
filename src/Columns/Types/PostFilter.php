<?php

namespace Corinthian\Columns\Types;

use Closure;
use Corinthian\Columns\Config;
use Corinthian\Columns\FilterOptions;
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
		private ?FilterOptions $values = null,
	) {}

	public function values(): ?FilterOptions {
		return $this->values;
	}

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

	/**
	 * Posts whose single meta value is a user id, picked from a searchable user list.
	 */
	public static function meta_users( string $meta_key ): self {
		$users = FilterValues::user_source();
		return new self(
			static fn( Config $c, Screen $s ) => FilterValues::in_ops( $users, $c, $s ),
			self::meta_ids( $meta_key, static fn() => array() )->condition,
			$users
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
				array(
					'op'    => 'ends',
					'label' => __( 'ends with', 'corinthian-admin-column-manager' ),
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
				'label' => __( 'equals', 'corinthian-admin-column-manager' ),
				'input' => 'number',
			),
			array(
				'op'    => 'neq',
				'label' => __( 'does not equal', 'corinthian-admin-column-manager' ),
				'input' => 'number',
			),
			array(
				'op'    => 'gt',
				'label' => __( 'is greater than', 'corinthian-admin-column-manager' ),
				'input' => 'number',
			),
			array(
				'op'    => 'lt',
				'label' => __( 'is less than', 'corinthian-admin-column-manager' ),
				'input' => 'number',
			),
			array(
				'op'    => 'between',
				'label' => __( 'is between', 'corinthian-admin-column-manager' ),
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
		$ops = array(
			array( 'before', __( 'before', 'corinthian-admin-column-manager' ), 'date' ),
			array( 'after', __( 'after', 'corinthian-admin-column-manager' ), 'date' ),
			array( 'within_days', __( 'in the last (days)', 'corinthian-admin-column-manager' ), 'number' ),
			array( 'older_than_days', __( 'older than (days)', 'corinthian-admin-column-manager' ), 'number' ),
			array( 'between', __( 'between', 'corinthian-admin-column-manager' ), 'date' ),
			array( 'on', __( 'on', 'corinthian-admin-column-manager' ), 'date' ),
			array( 'today', __( 'today', 'corinthian-admin-column-manager' ), 'none' ),
			array( 'future', __( 'in the future', 'corinthian-admin-column-manager' ), 'none' ),
			array( 'next_days', __( 'in the next (days)', 'corinthian-admin-column-manager' ), 'number' ),
			array( 'this_week', __( 'this week', 'corinthian-admin-column-manager' ), 'none' ),
			array( 'this_month', __( 'this month', 'corinthian-admin-column-manager' ), 'none' ),
		);
		return array_map(
			static fn( array $o ) => array(
				'op'    => $o[0],
				'label' => $o[1],
				'input' => $o[2],
			),
			$ops
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
	public static function yes_no_ops( bool $with_empty = false ): array {
		$ops = array(
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
		return $with_empty ? array_merge( $ops, self::empty_ops() ) : $ops;
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
			'ends'         => null !== $v && '' !== $v ? $wpdb->prepare( "{$col} LIKE %s", '%' . $wpdb->esc_like( $v ) ) : null, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
		if ( in_array( $op, self::RANGE_OPS, true ) ) {
			$range = self::date_range( $op, $value );
			if ( ! $range ) {
				return null;
			}
			$parts = array();
			if ( $range[0] ) {
				$parts[] = $wpdb->prepare( "{$col} >= %s", $range[0]->format( 'Y-m-d H:i:s' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			if ( $range[1] ) {
				$parts[] = $wpdb->prepare( "{$col} < %s", $range[1]->format( 'Y-m-d H:i:s' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			return '(' . implode( ' AND ', $parts ) . ')';
		}
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

	/**
	 * Ops resolved to a half-open range of site-local time.
	 */
	public const RANGE_OPS = array( 'between', 'on', 'today', 'future', 'next_days', 'this_week', 'this_month' );

	/**
	 * Site-local [from, to) for a range op. Either end is null when open.
	 * Days start at midnight in the site timezone and weeks at start_of_week.
	 *
	 * @return array{0:?\DateTimeImmutable,1:?\DateTimeImmutable}|null
	 */
	public static function date_range( string $op, mixed $value ): ?array {
		$tz    = wp_timezone();
		$stamp = apply_filters( 'corinthian_filter_now', null );
		$now   = null === $stamp ? new \DateTimeImmutable( 'now', $tz ) : ( new \DateTimeImmutable( '@' . (int) $stamp ) )->setTimezone( $tz );
		$today = $now->setTime( 0, 0 );
		$day   = static function ( mixed $v ) use ( $tz ): ?\DateTimeImmutable {
			if ( ! is_string( $v ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m ) || ! wp_checkdate( (int) $m[2], (int) $m[3], (int) $m[1], $v ) ) {
				return null;
			}
			return new \DateTimeImmutable( $v . ' 00:00:00', $tz );
		};
		switch ( $op ) {
			case 'on':
				$d = $day( $value );
				return $d ? array( $d, $d->modify( '+1 day' ) ) : null;
			case 'between':
				if ( ! is_array( $value ) || 2 !== count( $value ) ) {
					return null;
				}
				$a = $day( array_values( $value )[0] );
				$b = $day( array_values( $value )[1] );
				if ( ! $a || ! $b ) {
					return null;
				}
				return $a <= $b ? array( $a, $b->modify( '+1 day' ) ) : array( $b, $a->modify( '+1 day' ) );
			case 'today':
				return array( $today, $today->modify( '+1 day' ) );
			case 'future':
				return array( $now, null );
			case 'next_days':
				if ( ! is_scalar( $value ) || ! ctype_digit( (string) $value ) ) {
					return null;
				}
				return array( $today, $today->modify( '+' . ( (int) $value + 1 ) . ' days' ) );
			case 'this_week':
				$back  = ( (int) $today->format( 'w' ) - (int) get_option( 'start_of_week', 1 ) + 7 ) % 7;
				$start = $today->modify( "-{$back} days" );
				return array( $start, $start->modify( '+7 days' ) );
			case 'this_month':
				$start = $today->modify( 'first day of this month' );
				return array( $start, $start->modify( '+1 month' ) );
		}
		return null;
	}

	/**
	 * Same ranges against a meta value stored as Y-m-d or as a Unix timestamp.
	 */
	public static function meta_date_where( string $col, string $op, mixed $value ): ?string {
		global $wpdb;
		$range = self::date_range( $op, $value );
		if ( ! $range ) {
			return null;
		}
		// A Y-m-d value counts as midnight, so the first date at or after an instant is its date, or the next day when it falls mid-day.
		$ceil     = static fn( \DateTimeImmutable $t ): string => ( '00:00:00' === $t->format( 'H:i:s' ) ? $t : $t->setTime( 0, 0 )->modify( '+1 day' ) )->format( 'Y-m-d' );
		$day      = "SUBSTRING({$col}, 1, 10)";
		$by_day   = array( "{$col} REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}'" );
		$by_stamp = array( "{$col} REGEXP '^[0-9]{9,11}$'" );
		if ( $range[0] ) {
			$by_day[]   = $wpdb->prepare( "{$day} >= %s", $ceil( $range[0] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$by_stamp[] = $wpdb->prepare( "CAST({$col} AS UNSIGNED) >= %d", $range[0]->getTimestamp() ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		if ( $range[1] ) {
			$by_day[]   = $wpdb->prepare( "{$day} < %s", $ceil( $range[1] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$by_stamp[] = $wpdb->prepare( "CAST({$col} AS UNSIGNED) < %d", $range[1]->getTimestamp() ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return '((' . implode( ' AND ', $by_day ) . ') OR (' . implode( ' AND ', $by_stamp ) . '))';
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
}
