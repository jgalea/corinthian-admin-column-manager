<?php

namespace Corinthian\Columns\Types\Media;

use Corinthian\Columns\Editable;

/**
 * The attachment columns that can be edited: alt text and the parent post.
 */
final class MediaEditableFieldType extends MediaFieldType implements Editable {

	use EditsMedia;
}
