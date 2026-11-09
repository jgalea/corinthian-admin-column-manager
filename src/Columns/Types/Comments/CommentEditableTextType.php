<?php

namespace Corinthian\Columns\Types\Comments;

use Corinthian\Columns\Editable;

/**
 * The comment text columns that can be edited: author URL and content.
 */
final class CommentEditableTextType extends CommentTextFilterType implements Editable {

	use EditsComment;
}
