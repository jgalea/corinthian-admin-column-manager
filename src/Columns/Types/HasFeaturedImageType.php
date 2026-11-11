<?php

namespace Corinthian\Columns\Types;

use Corinthian\Columns\Config;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Sortable;
use Corinthian\Screens\Screen;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class HasFeaturedImageType extends AbstractPostType implements Sortable, Filterable {

	public function key(): string {
		return 'post.has_featured_image';
	}

	public function label(): string {
		return __( 'Has featured image', 'corinthian-admin-column-manager' );
	}

	protected function group_slug(): string {
		return 'media';
	}

	protected function supports_post_type( string $post_type ): bool {
		return post_type_supports( $post_type, 'thumbnail' );
	}

	protected function post_value( WP_Post $post, Config $config, Screen $screen ): mixed {
		return has_post_thumbnail( $post );
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		return $this->yes_no( (bool) $value );
	}

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		$clauses = $this->join_meta( $clauses, $config, '_thumbnail_id' );
		$expr    = 'MAX(CAST(' . $this->alias( $config ) . '.meta_value AS UNSIGNED))';
		// Posts with an image first in both directions; the screen's ID tie-break follows the order.
		return $this->order_by( $clauses, '', $order, "{$expr} IS NULL OR {$expr} = 0" );
	}

	public function filter_operators( Config $config, Screen $screen ): array {
		return PostFilter::yes_no_ops();
	}

	public function filter_condition( Config $config, string $op, mixed $value, Screen $screen, string $alias ): ?array {
		$flag = PostFilter::flag( $value );
		if ( 'eq' !== $op || null === $flag ) {
			return null;
		}
		$has = PostFilter::meta_exists( $alias, '_thumbnail_id', "CAST({$alias}.meta_value AS UNSIGNED) > 0" );
		return PostFilter::wrap( $flag ? $has : "NOT {$has}" );
	}
}
