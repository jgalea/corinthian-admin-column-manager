<?php

namespace Corinthian\Columns\Types;

use Corinthian\Columns\Config;
use Corinthian\Columns\Editable;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Sortable;
use Corinthian\Screens\Screen;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class PostFormatType extends AbstractPostType implements Sortable, Filterable, Editable {

	use EditsPost;

	public function key(): string {
		return 'post.format';
	}

	public function label(): string {
		return __( 'Post format', 'corinthian-admin-column-manager' );
	}

	protected function supports_post_type( string $post_type ): bool {
		return post_type_supports( $post_type, 'post-formats' );
	}

	protected function post_value( WP_Post $post, Config $config, Screen $screen ): mixed {
		$format = get_post_format( $post );
		return get_post_format_string( $format ? $format : 'standard' );
	}

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		return $this->sort_by_first_term( $clauses, $config, 'post_format', $order );
	}

	public function filter_operators( Config $config, Screen $screen ): array {
		return PostFilter::in_ops( get_post_format_strings() );
	}

	public function filter_condition( Config $config, string $op, mixed $value, Screen $screen, string $alias ): ?array {
		global $wpdb;
		$slugs = array_values( array_intersect( PostFilter::list_values( $value, false ), array_keys( get_post_format_strings() ) ) );
		if ( ! $slugs || ! in_array( $op, array( 'in', 'not_in' ), true ) ) {
			return null;
		}
		$parts = array();
		$named = array_map( static fn( $slug ) => 'post-format-' . $slug, array_values( array_diff( $slugs, array( 'standard' ) ) ) );
		if ( $named ) {
			$marks   = implode( ',', array_fill( 0, count( $named ), '%s' ) );
			$parts[] = PostFilter::term_exists( $alias, 'post_format', $wpdb->prepare( "{$alias}_t.slug IN ({$marks})", $named ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		}
		if ( in_array( 'standard', $slugs, true ) ) {
			$parts[] = 'NOT ' . PostFilter::term_exists( $alias . 'a', 'post_format' );
		}
		$any = '(' . implode( ' OR ', $parts ) . ')';
		return PostFilter::wrap( 'in' === $op ? $any : "NOT {$any}" );
	}

	protected function edit_kind(): string {
		return 'format';
	}
}
