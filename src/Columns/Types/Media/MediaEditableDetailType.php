<?php

namespace Corinthian\Columns\Types\Media;

use Corinthian\Columns\Editable;

/**
 * The attachment detail columns that can be edited: caption and description.
 */
final class MediaEditableDetailType extends MediaDetailFilterType implements Editable {

	use EditsMedia;
}
