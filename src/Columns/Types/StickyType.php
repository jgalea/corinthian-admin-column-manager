<?php

namespace Corinthian\Columns\Types;

use Corinthian\Columns\Config;
use Corinthian\Columns\Editable;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Sortable;
use Corinthian\Screens\Screen;
use WP_Post;

final class StickyType extends AbstractPostType implements Sortable, Filterable, Editable {

	use EditsPost;

	public function key(): string {
		return 'post.sticky';
	}

	public function label(): string {
		return __( 'Sticky', 'corinthian-admin-column-manager' );
	}

	protected function supports_post_type( string $post_type ): bool {
		return 'post' === $post_type;
	}

	protected function post_value( WP_Post $post, Config $config, Screen $screen ): mixed {
		return is_sticky( $post->ID );
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		return $this->yes_no( (bool) $value );
	}

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		global $wpdb;
		$ids = array_filter( array_map( 'absint', (array) get_option( 'sticky_posts', array() ) ) );
		if ( ! $ids ) {
			return $clauses;
		}
		// Sticky posts first in both directions; the screen's ID tie-break follows the order.
		return $this->order_by( $clauses, '', $order, "{$wpdb->posts}.ID NOT IN (" . implode( ',', $ids ) . ')' );
	}

	public function filter_operators( Config $config, Screen $screen ): array {
		return PostFilter::yes_no_ops();
	}

	public function filter_condition( Config $config, string $op, mixed $value, Screen $screen, string $alias ): ?array {
		global $wpdb;
		$flag = PostFilter::flag( $value );
		if ( 'eq' !== $op || null === $flag ) {
			return null;
		}
		$ids = array_values( array_filter( array_map( 'absint', (array) get_option( 'sticky_posts', array() ) ) ) );
		if ( ! $ids ) {
			return PostFilter::wrap( $flag ? '1 = 0' : '1 = 1' );
		}
		return PostFilter::wrap( "{$wpdb->posts}.ID " . ( $flag ? 'IN' : 'NOT IN' ) . ' (' . implode( ',', $ids ) . ')' );
	}

	protected function edit_kind(): string {
		return 'sticky';
	}
}
