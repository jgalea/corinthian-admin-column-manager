<?php

namespace Corinthian\Columns\Types\Comments;

use Corinthian\Columns\Config;
use Corinthian\Screens\Screen;

/**
 * Implements Editable for a comment column by handing the work to CommentEditor.
 */
trait EditsComment {

	public function edit_field( Config $config, Screen $screen ): array {
		return CommentEditor::field( $this->field );
	}

	public function edit_value( int $object_id, Config $config, Screen $screen ): mixed {
		return CommentEditor::value( $this->field, $object_id );
	}

	public function save( int $object_id, Config $config, Screen $screen, mixed $value, string $mode = 'set' ): bool|\WP_Error {
		return CommentEditor::save( $this->field, $object_id, $value, $mode );
	}
}
