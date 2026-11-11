<?php

namespace Corinthian\Columns\Types\Terms;

use Corinthian\Columns\Types\Users\ScreenFieldType;
use Corinthian\Screens\Screen;
use WP_Term;

defined( 'ABSPATH' ) || exit;

abstract class AbstractTermType extends ScreenFieldType {

	protected function prefix(): string {
		return 'term';
	}

	public function group(): string {
		return __( 'Term', 'corinthian-admin-column-manager' );
	}

	public function supports( Screen $screen ): bool {
		return 'term' === $screen->object_type();
	}

	protected function term( int $term_id, Screen $screen ): ?WP_Term {
		$taxonomy = $screen->subtype();
		$term     = get_term( $term_id, '' !== $taxonomy ? $taxonomy : '' );
		return $term instanceof WP_Term ? $term : null;
	}
}
