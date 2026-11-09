<?php

namespace Corinthian\Columns\Types;

/**
 * Counts related posts for every row on the current list page with one query.
 */
final class ChildCounter {

	private const GROUP = 'corinthian_counts';

	/**
	 * @param 'attachment'|'revision'|'children' $kind
	 */
	public static function count( int $post_id, string $kind ): int {
		wp_cache_add_non_persistent_groups( self::GROUP );
		$key = $kind . ':' . $post_id;
		$hit = wp_cache_get( $key, self::GROUP, false, $found );
		if ( $found ) {
			return (int) $hit;
		}
		self::load( array_values( array_unique( array_merge( array( $post_id ), self::listed_ids() ) ) ), $kind );
		return (int) wp_cache_get( $key, self::GROUP );
	}

	/**
	 * @return list<int>
	 */
	private static function listed_ids(): array {
		global $wp_query;
		$ids = array();
		if ( $wp_query instanceof \WP_Query && is_array( $wp_query->posts ) ) {
			foreach ( array_slice( $wp_query->posts, 0, 200 ) as $post ) {
				$ids[] = is_object( $post ) ? (int) $post->ID : (int) $post;
			}
		}
		return $ids;
	}

	/**
	 * @param list<int> $ids
	 */
	private static function load( array $ids, string $kind ): void {
		global $wpdb;
		$where = match ( $kind ) {
			'attachment' => "post_type = 'attachment'",
			'revision'   => "post_type = 'revision'",
			default      => "post_type NOT IN ('attachment','revision','nav_menu_item') AND post_status NOT IN ('trash','auto-draft')",
		};
		$marks = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT post_parent, COUNT(*) AS n FROM {$wpdb->posts} WHERE {$where} AND post_parent IN ({$marks}) GROUP BY post_parent", $ids ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.DirectDatabaseQuery,PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where is one of three literal clauses from the match above and the %d list is generated from the id count.

		foreach ( $ids as $id ) {
			wp_cache_set( $kind . ':' . $id, 0, self::GROUP );
		}
		foreach ( $rows as $row ) {
			wp_cache_set( $kind . ':' . (int) $row['post_parent'], (int) $row['n'], self::GROUP );
		}
	}
}
