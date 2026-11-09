<?php

namespace Corinthian\Columns\Types\Users;

/**
 * SQL builders shared by the user, media, comment and term filters.
 *
 * Every value goes through $wpdb->prepare(). Expressions are never passed as
 * the prepare() format, so a "%" inside one cannot be mistaken for a placeholder.
 */
final class FilterSql {

	public const TEXT   = 'text';
	public const NUMBER = 'number';
	public const DATE   = 'date';
	public const ENUM   = 'enum';
	public const BOOL   = 'bool';

	private const NEGATIVE = array(
		'neq'          => 'eq',
		'not_contains' => 'contains',
		'not_in'       => 'in',
	);

	private const FALSY = "('', '0', 'false', 'no', 'off')";

	/**
	 * @param array<string,string> $options Value => label, for enumerations.
	 * @return list<array<string,mixed>>
	 */
	public static function operators( string $kind, array $options = array() ): array {
		$empty = array(
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
		switch ( $kind ) {
			case self::NUMBER:
				return array(
					array( 'op' => 'eq', 'label' => '=', 'input' => 'number' ),
					array( 'op' => 'neq', 'label' => '≠', 'input' => 'number' ),
					array( 'op' => 'gt', 'label' => '>', 'input' => 'number' ),
					array( 'op' => 'lt', 'label' => '<', 'input' => 'number' ),
					array( 'op' => 'between', 'label' => __( 'between', 'corinthian-admin-column-manager' ), 'input' => 'number' ),
				);
			case self::DATE:
				return array(
					array( 'op' => 'before', 'label' => __( 'before', 'corinthian-admin-column-manager' ), 'input' => 'date' ),
					array( 'op' => 'after', 'label' => __( 'after', 'corinthian-admin-column-manager' ), 'input' => 'date' ),
					array( 'op' => 'within_days', 'label' => __( 'in the last (days)', 'corinthian-admin-column-manager' ), 'input' => 'number' ),
					array( 'op' => 'older_than_days', 'label' => __( 'older than (days)', 'corinthian-admin-column-manager' ), 'input' => 'number' ),
				);
			case self::ENUM:
				return array(
					array( 'op' => 'in', 'label' => __( 'is any of', 'corinthian-admin-column-manager' ), 'input' => 'select', 'options' => $options ),
					array( 'op' => 'not_in', 'label' => __( 'is none of', 'corinthian-admin-column-manager' ), 'input' => 'select', 'options' => $options ),
				);
			case self::BOOL:
				return array(
					array( 'op' => 'eq', 'label' => __( 'is', 'corinthian-admin-column-manager' ), 'input' => 'select', 'options' => array( '1' => __( 'Yes', 'corinthian-admin-column-manager' ), '0' => __( 'No', 'corinthian-admin-column-manager' ) ) ),
				);
			default:
				return array_merge(
					array(
						array( 'op' => 'contains', 'label' => __( 'contains', 'corinthian-admin-column-manager' ), 'input' => 'text' ),
						array( 'op' => 'not_contains', 'label' => __( 'does not contain', 'corinthian-admin-column-manager' ), 'input' => 'text' ),
						array( 'op' => 'eq', 'label' => __( 'is', 'corinthian-admin-column-manager' ), 'input' => 'text' ),
						array( 'op' => 'neq', 'label' => __( 'is not', 'corinthian-admin-column-manager' ), 'input' => 'text' ),
						array( 'op' => 'starts', 'label' => __( 'starts with', 'corinthian-admin-column-manager' ), 'input' => 'text' ),
					),
					$empty
				);
		}
	}

	/**
	 * Condition on a single-valued expression (a column or a scalar sub-select).
	 * Negative operators also match NULL.
	 *
	 * @param array<string,list<string>> $map      Option value => raw database values, for enumerations.
	 * @param array<string,string>       $options  Allowed enumeration values.
	 */
	public static function column( string $kind, string $op, mixed $value, string $expr, array $options = array(), array $map = array(), bool $datetime_column = true ): ?string {
		if ( 'empty' === $op ) {
			return self::TEXT === $kind ? "({$expr} IS NULL OR {$expr} = '')" : null;
		}
		if ( 'not_empty' === $op ) {
			return self::TEXT === $kind ? "({$expr} IS NOT NULL AND {$expr} <> '')" : null;
		}
		if ( isset( self::NEGATIVE[ $op ] ) ) {
			$positive = self::positive( $kind, self::NEGATIVE[ $op ], $value, $expr, false, $datetime_column, $options, $map );
			return null === $positive ? null : "({$expr} IS NULL OR NOT ({$positive}))";
		}
		return self::positive( $kind, $op, $value, $expr, false, $datetime_column, $options, $map );
	}

	/**
	 * Condition on a meta table with one or more rows per object, as EXISTS
	 * sub-queries so the outer query's rows are never multiplied. Negative
	 * operators are NOT EXISTS, which also matches objects without the key.
	 *
	 * @param array{table:string,id_column:string,outer_id:string,key:string,template?:string} $meta
	 */
	public static function meta( string $kind, string $op, mixed $value, array $meta, string $alias ): ?string {
		$alias = preg_replace( '/[^A-Za-z0-9_]/', '', $alias );
		$value_expr = str_replace( '__V__', "{$alias}.meta_value", $meta['template'] ?? '__V__' );

		if ( 'empty' === $op || 'not_empty' === $op ) {
			$positive = "{$value_expr} IS NOT NULL AND {$value_expr} <> ''";
			$negate   = 'empty' === $op;
		} elseif ( self::BOOL === $kind ) {
			if ( 'eq' !== $op || ! in_array( (string) ( is_scalar( $value ) ? $value : '' ), array( '0', '1' ), true ) ) {
				return null;
			}
			$positive = "{$value_expr} IS NOT NULL AND {$value_expr} NOT IN " . self::FALSY;
			$negate   = '0' === (string) $value;
		} elseif ( isset( self::NEGATIVE[ $op ] ) ) {
			$positive = self::positive( $kind, self::NEGATIVE[ $op ], $value, $value_expr, true, false );
			$negate   = true;
		} else {
			$positive = self::positive( $kind, $op, $value, $value_expr, true, false );
			$negate   = false;
		}
		if ( null === $positive ) {
			return null;
		}

		global $wpdb;
		$sub = $wpdb->prepare( "SELECT 1 FROM {$meta['table']} {$alias} WHERE {$alias}.{$meta['id_column']} = {$meta['outer_id']} AND {$alias}.meta_key = %s", $meta['key'] ) . " AND ({$positive})"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return ( $negate ? 'NOT EXISTS (' : 'EXISTS (' ) . $sub . ')';
	}

	/**
	 * @param array<string,string>       $options
	 * @param array<string,list<string>> $map
	 */
	private static function positive( string $kind, string $op, mixed $value, string $expr, bool $varchar, bool $datetime_column, array $options = array(), array $map = array() ): ?string {
		global $wpdb;

		if ( self::ENUM === $kind ) {
			if ( 'in' !== $op ) {
				return null;
			}
			$raw = array();
			foreach ( is_array( $value ) ? $value : array( $value ) as $item ) {
				$item = is_scalar( $item ) ? (string) $item : '';
				if ( '' === $item || ( $options && ! isset( $options[ $item ] ) ) ) {
					continue;
				}
				foreach ( $map[ $item ] ?? array( $item ) as $db_value ) {
					$raw[] = $db_value;
				}
			}
			$raw = array_values( array_unique( $raw ) );
			if ( ! $raw ) {
				return null;
			}
			return $expr . ' IN (' . $wpdb->prepare( implode( ',', array_fill( 0, count( $raw ), '%s' ) ), $raw ) . ')'; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		if ( is_array( $value ) ) {
			$between = 'between' === $op && 2 === count( $value ) ? array_map( static fn( $v ) => is_scalar( $v ) ? (string) $v : '', array_values( $value ) ) : null;
			if ( null === $between ) {
				return null;
			}
			$value = $between;
		} elseif ( is_scalar( $value ) ) {
			$value = (string) $value;
		} else {
			return null;
		}

		if ( self::NUMBER === $kind ) {
			$num   = $varchar ? "CAST({$expr} AS DECIMAL(30,6))" : $expr;
			$guard = $varchar ? "({$expr} IS NOT NULL AND {$expr} <> '') AND " : '';
			if ( 'between' === $op ) {
				return is_array( $value ) && is_numeric( $value[0] ) && is_numeric( $value[1] )
					? $guard . $num . ' BETWEEN ' . $wpdb->prepare( '%f AND %f', (float) min( $value ), (float) max( $value ) )
					: null;
			}
			$sql = array(
				'eq' => '=',
				'gt' => '>',
				'lt' => '<',
			)[ $op ] ?? null;
			return null !== $sql && is_string( $value ) && is_numeric( $value ) ? $guard . $num . " {$sql} " . $wpdb->prepare( '%f', (float) $value ) : null;
		}

		if ( self::DATE === $kind ) {
			if ( ! is_string( $value ) ) {
				return null;
			}
			$date = $expr;
			if ( $varchar ) {
				// Meta dates are either date strings or Unix timestamps.
				$date = "(CASE WHEN {$expr} REGEXP '^[0-9]{9,10}$' THEN DATE_ADD('1970-01-01', INTERVAL CAST({$expr} AS UNSIGNED) SECOND) ELSE {$expr} END)";
			}
			$guard = $datetime_column && ! $varchar ? "{$date} >= '1000-01-01' AND " : "({$date} IS NOT NULL AND {$date} <> '') AND ";
			switch ( $op ) {
				case 'before':
					return strtotime( $value ) ? $guard . $date . ' < ' . $wpdb->prepare( '%s', gmdate( 'Y-m-d', (int) strtotime( $value ) ) ) : null;
				case 'after':
					return strtotime( $value ) ? $guard . $date . ' >= ' . $wpdb->prepare( '%s', gmdate( 'Y-m-d', (int) strtotime( $value ) + DAY_IN_SECONDS ) ) : null;
				case 'within_days':
					return ctype_digit( $value ) ? $guard . $date . ' >= ' . $wpdb->prepare( '%s', gmdate( 'Y-m-d H:i:s', time() - (int) $value * DAY_IN_SECONDS ) ) : null;
				case 'older_than_days':
					return ctype_digit( $value ) ? $guard . $date . ' < ' . $wpdb->prepare( '%s', gmdate( 'Y-m-d H:i:s', time() - (int) $value * DAY_IN_SECONDS ) ) : null;
				default:
					return null;
			}
		}

		if ( ! is_string( $value ) ) {
			return null;
		}
		switch ( $op ) {
			case 'eq':
				return $expr . ' = ' . $wpdb->prepare( '%s', $value );
			case 'contains':
				return '' !== $value ? $expr . ' LIKE ' . $wpdb->prepare( '%s', '%' . $wpdb->esc_like( $value ) . '%' ) : null;
			case 'starts':
				return '' !== $value ? $expr . ' LIKE ' . $wpdb->prepare( '%s', $wpdb->esc_like( $value ) . '%' ) : null;
			default:
				return null;
		}
	}

	/**
	 * Operators for a custom field by its "Show as" setting.
	 *
	 * @return list<array<string,mixed>>
	 */
	public static function meta_operators( string $display ): array {
		return match ( self::meta_kind( $display ) ) {
			self::NUMBER => array_merge( self::operators( self::NUMBER ), array_slice( self::operators( self::TEXT ), -2 ) ),
			self::DATE   => array_merge( self::operators( self::DATE ), array_slice( self::operators( self::TEXT ), -2 ) ),
			self::BOOL   => self::operators( self::BOOL ),
			default      => self::operators( self::TEXT ),
		};
	}

	public static function meta_kind( string $display ): string {
		return match ( $display ) {
			'number'           => self::NUMBER,
			'date', 'datetime' => self::DATE,
			'boolean'          => self::BOOL,
			default            => self::TEXT,
		};
	}
}
