<?php

namespace Corinthian\Columns\Types;

use Corinthian\Columns\Config;
use Corinthian\Columns\FilterOptions;
use Corinthian\Screens\Screen;

defined( 'ABSPATH' ) || exit;

/**
 * Searchable, paged value lists for FilterOptions columns: users, terms and
 * distinct meta values. Every query is prepared and limited to one page.
 */
final class FilterValues {

	private const CACHE_GROUP = 'corinthian';

	private const MAX_LABEL = 120;

	/**
	 * "is any of" and "is none of" with the first values inline, flagged for
	 * search when the column holds more.
	 *
	 * @return list<array<string,mixed>>
	 */
	public static function in_ops( FilterOptions $type, Config $config, Screen $screen, bool $with_empty = false ): array {
		$first = $type->filter_options( $config, $screen, '', 1, FilterOptions::INLINE );
		return self::flag_search( PostFilter::in_ops( $first['options'], $with_empty ), $first['more'] );
	}

	/**
	 * Operators for a text custom field. Up to FilterOptions::INLINE distinct
	 * values are offered as a pick list ahead of the text operators. Past that
	 * the text operators come first and the pick list is searched.
	 *
	 * @param list<array<string,mixed>> $text_ops
	 * @return list<array<string,mixed>>
	 */
	public static function meta_ops( FilterOptions $type, Config $config, Screen $screen, array $text_ops ): array {
		foreach ( $text_ops as $i => $op ) {
			if ( 'text' === ( $op['input'] ?? '' ) ) {
				$text_ops[ $i ]['search'] = true;
			}
		}
		$first = $type->filter_options( $config, $screen, '', 1, FilterOptions::INLINE );
		if ( ! $first['options'] ) {
			return $text_ops;
		}
		$pick = self::flag_search( PostFilter::in_ops( $first['options'] ), $first['more'] );
		return $first['more'] ? array_merge( $text_ops, $pick ) : array_merge( $pick, $text_ops );
	}

	/**
	 * @param list<array<string,mixed>> $ops
	 * @return list<array<string,mixed>>
	 */
	private static function flag_search( array $ops, bool $more ): array {
		if ( ! $more ) {
			return $ops;
		}
		foreach ( $ops as $i => $op ) {
			if ( in_array( $op['op'] ?? '', array( 'in', 'not_in' ), true ) ) {
				$ops[ $i ]['search'] = true;
			}
		}
		return $ops;
	}

	public static function user_source(): FilterOptions {
		return new class() implements FilterOptions {
			public function filter_options( Config $config, Screen $screen, string $search, int $page, int $per_page ): array {
				return FilterValues::users( $search, $page, $per_page );
			}

			public function filter_labels( Config $config, Screen $screen, array $values ): array {
				return FilterValues::user_labels( $values );
			}
		};
	}

	/**
	 * Users by display name. People who can list users also match on username
	 * and email; everyone else sees only users who can write posts.
	 *
	 * @return array{options:array<string,string>,more:bool}
	 */
	public static function users( string $search, int $page, int $per_page ): array {
		$args = array(
			'number'      => $per_page + 1,
			'offset'      => ( max( 1, $page ) - 1 ) * $per_page,
			'orderby'     => array(
				'display_name' => 'ASC',
				'ID'           => 'ASC',
			),
			'fields'      => array( 'ID', 'display_name' ),
			'count_total' => false,
		);
		if ( ! current_user_can( 'list_users' ) ) {
			$args['capability'] = 'edit_posts';
		}
		if ( '' !== $search ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = current_user_can( 'list_users' ) ? array( 'display_name', 'user_login', 'user_email' ) : array( 'display_name' );
		}
		$out = array();
		foreach ( get_users( $args ) as $user ) {
			$out[ (string) $user->ID ] = self::label( (string) $user->display_name, (int) $user->ID );
		}
		return self::page( $out, $per_page );
	}

	/**
	 * @param list<string> $values
	 * @return array<string,string>
	 */
	public static function user_labels( array $values ): array {
		$ids = PostFilter::list_values( $values, true );
		if ( ! $ids ) {
			return array();
		}
		$args = array(
			'include'     => $ids,
			'number'      => count( $ids ),
			'fields'      => array( 'ID', 'display_name' ),
			'count_total' => false,
		);
		if ( ! current_user_can( 'list_users' ) ) {
			$args['capability'] = 'edit_posts';
		}
		$out = array();
		foreach ( get_users( $args ) as $user ) {
			$out[ (string) $user->ID ] = self::label( (string) $user->display_name, (int) $user->ID );
		}
		return $out;
	}

	/**
	 * @return array{options:array<string,string>,more:bool}
	 */
	public static function terms( string $taxonomy, string $search, int $page, int $per_page ): array {
		if ( '' === $taxonomy ) {
			return self::page( array(), $per_page );
		}
		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => $per_page + 1,
			'offset'     => ( max( 1, $page ) - 1 ) * $per_page,
			'orderby'    => 'name',
			'fields'     => 'id=>name',
		);
		if ( '' !== $search ) {
			$args['name__like'] = $search;
		}
		return self::page( self::term_rows( get_terms( $args ) ), $per_page );
	}

	/**
	 * @param list<string> $values
	 * @return array<string,string>
	 */
	public static function term_labels( string $taxonomy, array $values ): array {
		$ids = PostFilter::list_values( $values, true );
		if ( '' === $taxonomy || ! $ids ) {
			return array();
		}
		return self::term_rows(
			get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'include'    => $ids,
					'fields'     => 'id=>name',
				)
			)
		);
	}

	/**
	 * Distinct non-empty values of one meta key, starting with $search. The
	 * prefix match lets the meta_key index narrow the rows before the LIKE.
	 *
	 * @param string $table       Meta table, read as alias "m".
	 * @param string $cache_group Core cache group whose last_changed covers this table.
	 * @param string $scope       Prepared JOIN that limits which objects count.
	 * @return array{options:array<string,string>,more:bool}
	 */
	public static function meta( string $table, string $cache_group, string $key, string $search, int $page, int $per_page, string $scope = '' ): array {
		global $wpdb;
		$offset    = ( max( 1, $page ) - 1 ) * $per_page;
		$cache_key = 'meta_values:' . md5( wp_json_encode( array( $table, $key, $search, $offset, $per_page, $scope ) ) . ':' . wp_cache_get_last_changed( $cache_group ) );
		$values    = wp_cache_get( $cache_key, self::CACHE_GROUP, false, $found );
		if ( ! $found ) {
			$where = $wpdb->prepare( 'm.meta_key = %s', $key );
			if ( '' !== $search ) {
				$where .= $wpdb->prepare( ' AND m.meta_value LIKE %s', $wpdb->esc_like( $search ) . '%' );
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Cached above; the table name is core's, $scope and $where are prepared.
			$values = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT m.meta_value FROM {$table} AS m {$scope} WHERE {$where} AND m.meta_value <> '' ORDER BY m.meta_value ASC LIMIT %d OFFSET %d", $per_page + 1, $offset ) );
			wp_cache_set( $cache_key, $values, self::CACHE_GROUP, HOUR_IN_SECONDS );
		}
		$out = array();
		foreach ( (array) $values as $value ) {
			$value         = (string) $value;
			$out[ $value ] = self::label( $value, 0 );
		}
		return self::page( $out, $per_page );
	}

	/**
	 * A meta value is its own label.
	 *
	 * @param list<string> $values
	 * @return array<string,string>
	 */
	public static function meta_labels( array $values ): array {
		$out = array();
		foreach ( PostFilter::list_values( $values, false ) as $value ) {
			$out[ (string) $value ] = self::label( (string) $value, 0 );
		}
		return $out;
	}

	/**
	 * @param array<int|string,string>|\WP_Error|string $terms
	 * @return array<string,string>
	 */
	private static function term_rows( $terms ): array {
		$out = array();
		foreach ( is_array( $terms ) ? $terms : array() as $id => $name ) {
			$out[ (string) $id ] = self::label( wp_specialchars_decode( (string) $name, ENT_QUOTES ), (int) $id );
		}
		return $out;
	}

	private static function label( string $text, int $id ): string {
		$text = trim( $text );
		if ( '' === $text ) {
			return '#' . $id;
		}
		return mb_strlen( $text ) > self::MAX_LABEL ? mb_substr( $text, 0, self::MAX_LABEL - 1 ) . '…' : $text;
	}

	/**
	 * Trim the look-ahead row that was fetched to learn whether a next page exists.
	 *
	 * @param array<string,string> $rows
	 * @return array{options:array<string,string>,more:bool}
	 */
	private static function page( array $rows, int $per_page ): array {
		return array(
			'options' => array_slice( $rows, 0, $per_page, true ),
			'more'    => count( $rows ) > $per_page,
		);
	}
}
