<?php

namespace Corinthian\Columns\Types\Media;

use Corinthian\Columns\Types\Users\ScreenFieldType;
use Corinthian\Screens\Screen;

defined( 'ABSPATH' ) || exit;

abstract class AbstractMediaType extends ScreenFieldType {

	protected function prefix(): string {
		return 'media';
	}

	public function group(): string {
		return __( 'Media', 'corinthian-admin-column-manager' );
	}

	public function supports( Screen $screen ): bool {
		return 'post' === $screen->object_type() && 'attachment' === $screen->subtype();
	}
}
