<?php

namespace Corinthian\Columns\Types\Comments;

use Corinthian\Columns\Types\Users\ScreenFieldType;
use Corinthian\Screens\Screen;
use WP_Comment;

defined( 'ABSPATH' ) || exit;

abstract class AbstractCommentType extends ScreenFieldType {

	protected function prefix(): string {
		return 'comment';
	}

	public function group(): string {
		return __( 'Comment', 'corinthian-admin-column-manager' );
	}

	public function supports( Screen $screen ): bool {
		return 'comment' === $screen->object_type();
	}

	protected function comment( int $comment_id ): ?WP_Comment {
		return get_comment( $comment_id );
	}
}
