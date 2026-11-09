<?php

namespace Corinthian\Columns\Types\Comments;

use Corinthian\Columns\Types\Users\AbstractMetaType;
use Corinthian\Screens\Screen;

final class CommentMetaType extends AbstractMetaType {

	protected function prefix(): string {
		return 'comment';
	}

	public function supports( Screen $screen ): bool {
		return 'comment' === $screen->object_type();
	}

	protected function meta_table(): string {
		global $wpdb;
		return $wpdb->commentmeta;
	}

	protected function meta_id_column(): string {
		return 'comment_id';
	}

	protected function outer_id(): string {
		global $wpdb;
		return "{$wpdb->comments}.comment_ID";
	}

	protected function read_meta( int $object_id, string $key ): array {
		return get_comment_meta( $object_id, $key, false );
	}
}
