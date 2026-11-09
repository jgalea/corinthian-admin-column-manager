<?php

namespace Corinthian\Maintenance;

use Corinthian\Columns\Types\WordCountMeta;

/**
 * Stores word counts for posts saved before Corinthian was active, so word
 * count and reading time sort and filter correctly across the whole site.
 * Runs in small cron batches after activation; `wp corinthian backfill` runs it all at once.
 */
final class WordCountBackfill {

	public const HOOK   = 'corinthian_word_count_backfill';
	public const OPTION = 'corinthian_word_count_backfill_done';
	public const BATCH  = 200;

	public static function register(): void {
		add_action(
			self::HOOK,
			static function (): void {
				self::run_batch();
			}
		);
		add_action(
			'admin_init',
			static function () {
				if ( ! get_option( self::OPTION ) && ! wp_next_scheduled( self::HOOK ) ) {
					wp_schedule_single_event( time() + 30, self::HOOK );
				}
			}
		);
	}

	/**
	 * Count words for one batch of posts missing a stored count. Reschedules
	 * itself until none are left.
	 *
	 * @return int Posts processed.
	 */
	public static function run_batch(): int {
		$ids = self::missing( self::BATCH );
		foreach ( $ids as $id ) {
			WordCountMeta::store( $id );
		}
		if ( count( $ids ) < self::BATCH ) {
			update_option( self::OPTION, time(), false );
		} elseif ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + 10, self::HOOK );
		}
		return count( $ids );
	}

	/**
	 * @return list<int>
	 */
	public static function missing( int $limit ): array {
		global $wpdb;
		$types = array_values( array_diff( get_post_types( array( 'show_ui' => true ) ), array( 'attachment', 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_font_family', 'wp_font_face' ) ) );
		if ( ! $types ) {
			return array();
		}
		$in = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The %s list is generated from the count of $types, and the replacements are merged into one array.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} m ON (m.post_id = p.ID AND m.meta_key = %s)
				WHERE m.meta_id IS NULL AND p.post_type IN ($in) AND p.post_status NOT IN ('auto-draft', 'trash', 'inherit')
				ORDER BY p.ID ASC LIMIT %d",
				array_merge( array( WordCountMeta::KEY ), $types, array( $limit ) )
			)
		);
		// phpcs:enable
		return array_map( 'intval', $ids );
	}
}
