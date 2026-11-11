<?php

namespace Corinthian\Columns\Types;

use Corinthian\Columns\Config;
use Corinthian\Screens\Screen;

defined( 'ABSPATH' ) || exit;

/**
 * Implements Editable for a post column by handing the work to PostEditor.
 */
trait EditsPost {

	abstract protected function edit_kind(): string;

	/**
	 * @return array{taxonomy?:string,allow_create?:bool}
	 */
	protected function edit_extra( Config $config, Screen $screen ): array {
		return array();
	}

	public function edit_field( Config $config, Screen $screen ): array {
		return PostEditor::field( $this->edit_kind(), $screen, $this->edit_extra( $config, $screen ) );
	}

	public function edit_value( int $object_id, Config $config, Screen $screen ): mixed {
		return PostEditor::value( $this->edit_kind(), $object_id, $this->edit_extra( $config, $screen ) );
	}

	public function save( int $object_id, Config $config, Screen $screen, mixed $value, string $mode = 'set' ): bool|\WP_Error {
		return PostEditor::save( $this->edit_kind(), $object_id, $value, $mode, $this->edit_extra( $config, $screen ) );
	}
}
