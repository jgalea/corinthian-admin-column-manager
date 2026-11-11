<?php

namespace Corinthian\Screens;

use Closure;
use WP_Site_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Runs WP_Site_Query without its result cache.
 *
 * The cache key only covers the query variables, not what sites_clauses adds,
 * so a filtered or sorted query would be answered from (and written over) the
 * entry of the plain one. Queries carrying UNCACHED skip the cache entirely.
 */
final class SiteQuery {

	public const UNCACHED = 'corinthian_uncached';

	public static function hook(): void {
		add_filter( 'sites_pre_query', array( self::class, 'pre_query' ), 10, 2 );
	}

	/**
	 * @param mixed $data
	 * @return mixed
	 */
	public static function pre_query( $data, WP_Site_Query $query ) {
		if ( null !== $data || empty( $query->query_vars[ self::UNCACHED ] ) ) {
			return $data;
		}
		$run = Closure::bind(
			static function ( WP_Site_Query $q ) {
				$ids = $q->get_site_ids();
				if ( is_array( $ids ) && $ids ) {
					// Private in core; the closure runs in WP_Site_Query's scope.
					$found = 'set_found_sites';
					$q->$found();
					if ( $q->found_sites && $q->query_vars['number'] ) {
						$q->max_num_pages = (int) ceil( $q->found_sites / $q->query_vars['number'] );
					}
				}
				return $ids;
			},
			null,
			WP_Site_Query::class
		);
		$ids = $run( $query );
		if ( $query->query_vars['count'] ) {
			return (int) $ids;
		}
		$ids = array_map( 'intval', (array) $ids );
		if ( 'ids' === $query->query_vars['fields'] ) {
			return $ids;
		}
		return array_values( array_filter( array_map( 'get_site', $ids ) ) );
	}

	/**
	 * Add a filter result's join and where to a sites_clauses array.
	 *
	 * @param array<string,string> $clauses
	 * @param array{join:string,where:string} $sql
	 * @return array<string,string>
	 */
	public static function narrow( array $clauses, array $sql ): array {
		if ( '' === $sql['where'] ) {
			return $clauses;
		}
		$clauses['join']  = trim( ( $clauses['join'] ?? '' ) . ' ' . $sql['join'] );
		$clauses['where'] = '' === trim( $clauses['where'] ?? '' ) ? $sql['where'] : $clauses['where'] . ' AND ' . $sql['where'];
		return $clauses;
	}

	/**
	 * Order by a sortable column, with the site id as the tie-break.
	 *
	 * @param array<string,string> $clauses
	 * @return array<string,string>
	 */
	public static function order( array $clauses, \Corinthian\Columns\Config $config, \Corinthian\Columns\Sortable $type, string $order, Screen $screen ): array {
		global $wpdb;
		$sorted            = $type->sort_clauses( $clauses + array( 'groupby' => '' ), $config, $order, $screen );
		$sorted['orderby'] = trim( $sorted['orderby'] . ", {$wpdb->blogs}.blog_id {$order}", ', ' );
		return $sorted;
	}
}
