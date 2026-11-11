<?php

namespace Corinthian\Columns\Types\Media;

use Corinthian\Columns\Editable;

defined( 'ABSPATH' ) || exit;

/**
 * The attachment detail columns that can be edited: caption and description.
 */
final class MediaEditableDetailType extends MediaDetailFilterType implements Editable {

	use EditsMedia;
}
