<?php

namespace Corinthian\Columns\Types\Users;

use Corinthian\Screens\NetworkUserScreen;
use Corinthian\Screens\Screen;

defined( 'ABSPATH' ) || exit;

class UserMetaType extends AbstractMetaType {

	protected function prefix(): string {
		return 'user';
	}

	public function supports( Screen $screen ): bool {
		return 'user' === $screen->object_type();
	}

	protected function meta_table(): string {
		global $wpdb;
		return $wpdb->usermeta;
	}

	protected function meta_id_column(): string {
		return 'user_id';
	}

	/**
	 * User meta is shared across a network, so a site's list only offers
	 * values from its own members.
	 */
	protected function meta_scope( Screen $screen ): string {
		if ( ! is_multisite() || $screen instanceof NetworkUserScreen ) {
			return '';
		}
		global $wpdb;
		return $wpdb->prepare( "INNER JOIN {$wpdb->usermeta} AS cap ON cap.user_id = m.user_id AND cap.meta_key = %s", $wpdb->get_blog_prefix() . 'capabilities' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	protected function outer_id(): string {
		global $wpdb;
		return "{$wpdb->users}.ID";
	}

	protected function read_meta( int $object_id, string $key ): array {
		return get_user_meta( $object_id, $key, false );
	}
}
