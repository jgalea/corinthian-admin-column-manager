<?php

namespace Corinthian\Columns;

use Corinthian\Screens\Screen;

abstract class AbstractType implements ColumnType {

	public function settings_schema( Screen $screen ): array {
		return array();
	}

	public function render( int $object_id, Config $config, Screen $screen ): string {
		$value = $this->value( $object_id, $config, $screen );
		if ( null === $value || '' === $value || array() === $value ) {
			return '<span aria-hidden="true">&#8212;</span>';
		}
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_map( 'strval', $value ) );
		}
		return esc_html( (string) $value );
	}

	/**
	 * Unique alias for a join added by this column, so two columns of the same
	 * type can sort or filter in one query without colliding.
	 */
	protected function alias( Config $config ): string {
		return 'cln_' . preg_replace( '/[^a-z0-9_]/', '', $config->id );
	}
}
