<?php

namespace Corinthian\Columns\Types;

use Corinthian\Columns\Config;
use Corinthian\Columns\Editable;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Sortable;
use Corinthian\Screens\Screen;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class ParentType extends AbstractPostType implements Sortable, Filterable, Editable {

	use EditsPost;

	public function key(): string {
		return 'post.parent';
	}

	public function label(): string {
		return __( 'Parent', 'corinthian-admin-column-manager' );
	}

	protected function group_slug(): string {
		return 'hierarchy';
	}

	protected function supports_post_type( string $post_type ): bool {
		return is_post_type_hierarchical( $post_type );
	}

	protected function post_value( WP_Post $post, Config $config, Screen $screen ): mixed {
		return $post->post_parent ? (int) $post->post_parent : null;
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		$parent = (int) $value;
		$title  = esc_html( get_the_title( $parent ) );
		$url    = $this->edit_link( $parent );
		return $url ? '<a href="' . $url . '">' . $title . '</a>' : $title;
	}

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		global $wpdb;
		$alias            = $this->alias( $config );
		$col              = "{$alias}.post_title";
		$clauses['join'] .= " LEFT JOIN {$wpdb->posts} AS {$alias} ON {$alias}.ID = {$wpdb->posts}.post_parent";
		return $this->order_by( $clauses, $col, $order, "{$col} IS NULL OR {$col} = ''" );
	}

	public function filter_operators( Config $config, Screen $screen ): array {
		return array(
			array(
				'op'    => 'eq',
				'label' => __( 'is', 'corinthian-admin-column-manager' ),
				'input' => 'number',
			),
			array(
				'op'    => 'empty',
				'label' => __( 'is top level', 'corinthian-admin-column-manager' ),
				'input' => 'none',
			),
		);
	}

	public function filter_condition( Config $config, string $op, mixed $value, Screen $screen, string $alias ): ?array {
		global $wpdb;
		if ( 'empty' === $op ) {
			return PostFilter::wrap( "{$wpdb->posts}.post_parent = 0" );
		}
		$id = is_scalar( $value ) ? absint( $value ) : 0;
		return 'eq' === $op && $id > 0 ? PostFilter::wrap( $wpdb->prepare( "{$wpdb->posts}.post_parent = %d", $id ) ) : null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	protected function edit_kind(): string {
		return 'parent';
	}
}
