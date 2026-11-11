<?php

namespace Corinthian\Columns\Types\Terms;

use Corinthian\Columns\Editable;

defined( 'ABSPATH' ) || exit;

/**
 * The term columns that can be edited: name, slug and parent.
 */
final class TermEditableFieldType extends TermFieldType implements Editable {

	use EditsTerm;
}
