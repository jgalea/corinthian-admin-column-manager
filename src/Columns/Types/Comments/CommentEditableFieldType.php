<?php

namespace Corinthian\Columns\Types\Comments;

use Corinthian\Columns\Editable;

/**
 * The comment table columns that can be edited: author name, author email and status.
 */
final class CommentEditableFieldType extends CommentFieldType implements Editable {

	use EditsComment;
}
