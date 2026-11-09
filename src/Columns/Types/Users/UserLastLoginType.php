<?php

namespace Corinthian\Columns\Types\Users;

use Corinthian\Screens\Screen;

/**
 * Preset over user meta that other plugins already write. Corinthian tracks no logins itself.
 */
final class UserLastLoginType extends UserMetaType {

	private const CANDIDATES = array( 'last_login', '_last_login', 'last_login_time', 'wfls-last-login', 'last_activity', 'wc_last_active' );

	public function __construct() {
		parent::__construct( 'last_login' );
	}

	public function label(): string {
		return __( 'Last login', 'corinthian-admin-column-manager' );
	}

	public function group(): string {
		return __( 'User', 'corinthian-admin-column-manager' );
	}

	public function supports( Screen $screen ): bool {
		return parent::supports( $screen ) && '' !== self::detected_key();
	}

	protected function default_key(): string {
		return self::detected_key();
	}

	protected function default_display(): string {
		return 'datetime';
	}

	public static function detected_key(): string {
		$cached = wp_cache_get( 'last_login_key', 'corinthian', false, $found );
		if ( $found ) {
			return (string) $cached;
		}
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( self::CANDIDATES ), '%s' ) );
		$existing     = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_key FROM {$wpdb->usermeta} WHERE meta_key IN ({$placeholders})", self::CANDIDATES ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The %s list is generated from the candidate count.
		$key          = '';
		foreach ( self::CANDIDATES as $candidate ) {
			if ( in_array( $candidate, $existing, true ) ) {
				$key = $candidate;
				break;
			}
		}
		wp_cache_set( 'last_login_key', $key, 'corinthian', 300 );
		return $key;
	}
}
