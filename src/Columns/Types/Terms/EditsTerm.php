<?php

namespace Corinthian\Columns\Types\Terms;

use Corinthian\Columns\Config;
use Corinthian\Screens\Screen;

defined( 'ABSPATH' ) || exit;

/**
 * Implements Editable for a term column by handing the work to TermEditor.
 */
trait EditsTerm {

	public function edit_field( Config $config, Screen $screen ): array {
		return TermEditor::field( $this->field, $screen );
	}

	public function edit_value( int $object_id, Config $config, Screen $screen ): mixed {
		return TermEditor::value( $this->field, $object_id, $screen );
	}

	public function save( int $object_id, Config $config, Screen $screen, mixed $value, string $mode = 'set' ): bool|\WP_Error {
		return TermEditor::save( $this->field, $object_id, $screen, $value, $mode );
	}
}
