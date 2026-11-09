<?php

namespace Corinthian\Columns\Types\Media;

use Corinthian\Columns\Config;
use Corinthian\Screens\Screen;

/**
 * Implements Editable for a media column by handing the work to MediaEditor.
 */
trait EditsMedia {

	public function edit_field( Config $config, Screen $screen ): array {
		return MediaEditor::field( $this->field );
	}

	public function edit_value( int $object_id, Config $config, Screen $screen ): mixed {
		return MediaEditor::value( $this->field, $object_id );
	}

	public function save( int $object_id, Config $config, Screen $screen, mixed $value, string $mode = 'set' ): bool|\WP_Error {
		return MediaEditor::save( $this->field, $object_id, $value, $mode );
	}
}
