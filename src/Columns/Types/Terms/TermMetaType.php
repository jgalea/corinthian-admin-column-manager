<?php

namespace Corinthian\Columns\Types\Terms;

use Corinthian\Columns\Types\Users\AbstractMetaType;
use Corinthian\Screens\Screen;

defined( 'ABSPATH' ) || exit;

final class TermMetaType extends AbstractMetaType {

	protected function prefix(): string {
		return 'term';
	}

	public function supports( Screen $screen ): bool {
		return 'term' === $screen->object_type();
	}

	protected function meta_table(): string {
		global $wpdb;
		return $wpdb->termmeta;
	}

	protected function meta_id_column(): string {
		return 'term_id';
	}

	protected function outer_id(): string {
		return 't.term_id';
	}

	protected function read_meta( int $object_id, string $key ): array {
		return get_term_meta( $object_id, $key, false );
	}
}
