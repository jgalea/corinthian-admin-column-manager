<?php

namespace Corinthian\Columns\Types;

use Corinthian\Columns\Config;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Sortable;
use Corinthian\Screens\Screen;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class WordCountType extends AbstractPostType implements Sortable, Filterable {

	public function key(): string {
		return 'post.word_count';
	}

	public function label(): string {
		return __( 'Word count', 'corinthian-admin-column-manager' );
	}

	protected function group_slug(): string {
		return 'content';
	}

	protected function supports_post_type( string $post_type ): bool {
		return post_type_supports( $post_type, 'editor' );
	}

	protected function post_value( WP_Post $post, Config $config, Screen $screen ): mixed {
		return WordCountMeta::get( $post->ID );
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		return esc_html( number_format_i18n( (int) $value ) );
	}

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		$clauses = $this->join_meta( $clauses, $config, WordCountMeta::KEY );
		$expr    = 'MAX(CAST(' . $this->alias( $config ) . '.meta_value AS UNSIGNED))';
		return $this->order_by( $clauses, $expr, $order, "{$expr} IS NULL" );
	}

	public function filter_operators( Config $config, Screen $screen ): array {
		return PostFilter::number_ops( true );
	}

	public function filter_condition( Config $config, string $op, mixed $value, Screen $screen, string $alias ): ?array {
		global $wpdb;
		$where = PostFilter::number_where( "CAST({$alias}.meta_value AS UNSIGNED)", $op, $value );
		return null === $where ? null : array(
			'join'  => $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} AS {$alias} ON ({$alias}.post_id = {$wpdb->posts}.ID AND {$alias}.meta_key = %s)", WordCountMeta::KEY ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'where' => $where,
		);
	}
}
