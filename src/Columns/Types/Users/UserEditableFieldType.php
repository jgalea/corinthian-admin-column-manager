<?php

namespace Corinthian\Columns\Types\Users;

use Corinthian\Columns\Editable;

/**
 * The user table columns that can be edited: display name, email and website.
 */
final class UserEditableFieldType extends UserFieldType implements Editable {

	use EditsUser;
}
