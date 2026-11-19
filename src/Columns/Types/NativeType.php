<?php

namespace Corinthian\Columns\Types;

use Corinthian\Columns\AbstractType;
use Corinthian\Columns\Config;
use Corinthian\Columns\Filterable;
use Corinthian\Screens\Screen;

defined( 'ABSPATH' ) || exit;

/**
 * A column WordPress or another plugin registers. Corinthian only places and
 * labels it; the original code renders it and handles its sorting.
 */
final class NativeType extends AbstractType implements Filterable {

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

	public function filter_operators( Config $config, Screen $screen ): array {
		return $this->filters_title( $config, $screen ) ? PostFilter::text_ops() : array();
	}

	public function filter_condition( Config $config, string $op, mixed $value, Screen $screen, string $alias ): ?array {
		global $wpdb;
		return $this->filters_title( $config, $screen ) ? PostFilter::wrap( PostFilter::text_where( "{$wpdb->posts}.post_title", $op, $value ) ) : null;
	}

	private function filters_title( Config $config, Screen $screen ): bool {
		return 'post' === $screen->object_type() && 'title' === $config->setting( 'key' );
	}
}
