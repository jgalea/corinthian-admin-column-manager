<?php

namespace Corinthian;

final class Schema {

	public const DB_VERSION = '1';
	public const OPTION     = 'corinthian_db_version';

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'corinthian_sets';
	}

	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				screen varchar(190) NOT NULL,
				title varchar(190) NOT NULL DEFAULT '',
				columns longtext NOT NULL,
				settings longtext NOT NULL,
				position int(11) NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY screen (screen)
			) {$charset};"
		);

		update_option( self::OPTION, self::DB_VERSION, false );
	}

	public static function maybe_upgrade(): void {
		if ( get_option( self::OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}
}
