<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$corinthian_clean_site = static function () use ( $wpdb ): void {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}corinthian_sets" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table, name built from the prefix.
	delete_option( 'corinthian_db_version' );
	delete_option( 'corinthian_word_count_backfill_done' );
	delete_option( 'corinthian_word_count_needed' );
	$native = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'corinthian_native_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
	foreach ( $native as $corinthian_option ) {
		delete_option( $corinthian_option );
	}
	delete_post_meta_by_key( '_corinthian_word_count' );
	wp_clear_scheduled_hook( 'corinthian_word_count_backfill' );
};

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $corinthian_site_id ) {
		switch_to_blog( (int) $corinthian_site_id );
		$corinthian_clean_site();
		restore_current_blog();
	}
} else {
	$corinthian_clean_site();
}

foreach ( array( 'corinthian_active_', 'corinthian_widths_' ) as $corinthian_prefix ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $wpdb->esc_like( $corinthian_prefix ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
}
