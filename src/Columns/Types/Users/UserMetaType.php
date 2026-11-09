<?php

namespace Corinthian\Columns\Types\Users;

use Corinthian\Screens\Screen;

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

	protected function outer_id(): string {
		global $wpdb;
		return "{$wpdb->users}.ID";
	}

	protected function read_meta( int $object_id, string $key ): array {
		return get_user_meta( $object_id, $key, false );
	}
}
