<?php

namespace Corinthian\Columns\Types\Users;

use Corinthian\Screens\Screen;

abstract class AbstractUserType extends ScreenFieldType {

	protected function prefix(): string {
		return 'user';
	}

	public function group(): string {
		return __( 'User', 'corinthian-admin-column-manager' );
	}

	public function supports( Screen $screen ): bool {
		return 'user' === $screen->object_type();
	}
}
