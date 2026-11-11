<?php

namespace Corinthian\Columns\Types\Users;

use Corinthian\Columns\Config;
use Corinthian\Screens\Screen;

defined( 'ABSPATH' ) || exit;

/**
 * Implements Editable for a user column by handing the work to UserEditor.
 */
trait EditsUser {

	public function edit_field( Config $config, Screen $screen ): array {
		return UserEditor::field( $this->field );
	}

	public function edit_value( int $object_id, Config $config, Screen $screen ): mixed {
		return UserEditor::value( $this->field, $object_id );
	}

	public function save( int $object_id, Config $config, Screen $screen, mixed $value, string $mode = 'set' ): bool|\WP_Error {
		return UserEditor::save( $this->field, $object_id, $value, $mode );
	}
}
