<?php

namespace Corinthian\Columns\Types\Comments;

use Corinthian\Columns\Config;
use Corinthian\Columns\Sortable;
use Corinthian\Screens\Screen;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Types\Users\FilterSql;
use Corinthian\Columns\Types\Users\FiltersField;

final class CommentPostTitleType extends AbstractCommentType implements Sortable, Filterable {

	use FiltersField;

	public function __construct() {
		parent::__construct( 'post_title' );
	}

	public function label(): string {
		return __( 'Post title', 'corinthian-admin-column-manager' );
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		$comment = $this->comment( $object_id );
		if ( ! $comment || ! get_post( (int) $comment->comment_post_ID ) ) {
			return null;
		}
		$title = get_the_title( (int) $comment->comment_post_ID );
		return '' !== $title ? $title : '#' . $comment->comment_post_ID;
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		$text    = is_scalar( $value ) ? (string) $value : '';
		$comment = $this->comment( $object_id );
		$url     = $comment ? get_edit_post_link( (int) $comment->comment_post_ID, 'raw' ) : null;
		return $url ? $this->link( $url, $text ) : esc_html( $text );
	}

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		global $wpdb;
		$sub = "(SELECT pp.post_title FROM {$wpdb->posts} pp WHERE pp.ID = {$wpdb->comments}.comment_post_ID)";
		return $this->order_by( $clauses, $sub, $order, self::blank( $sub ) );
	}

	protected function filter_spec( Config $config, Screen $screen ): ?array {
		global $wpdb;
		return array(
			'kind' => FilterSql::TEXT,
			'expr' => "(SELECT pp.post_title FROM {$wpdb->posts} pp WHERE pp.ID = {$wpdb->comments}.comment_post_ID)",
		);
	}
}
