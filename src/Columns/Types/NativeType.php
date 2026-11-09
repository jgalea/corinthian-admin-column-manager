<?php

namespace Corinthian\Columns\Types;

use Corinthian\Columns\AbstractType;
use Corinthian\Columns\Config;
use Corinthian\Screens\Screen;

/**
 * A column WordPress or another plugin registers. Corinthian only places and
 * labels it; the original code renders it and handles its sorting.
 */
final class NativeType extends AbstractType {

	public function key(): string {
		return 'native';
	}

	public function label(): string {
		return __( 'Default column', 'corinthian-admin-column-manager' );
	}

	public function group(): string {
		return __( 'Default', 'corinthian-admin-column-manager' );
	}

	public function supports( Screen $screen ): bool {
		return true;
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		return null;
	}

	public function render( int $object_id, Config $config, Screen $screen ): string {
		return '';
	}
}
