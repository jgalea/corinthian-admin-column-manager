<?php

namespace Corinthian\Columns\Types\Comments;

use Corinthian\Columns\Config;
use Corinthian\Columns\Sortable;
use Corinthian\Screens\Screen;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Types\Users\FilterSql;
use Corinthian\Columns\Types\Users\FiltersField;

defined( 'ABSPATH' ) || exit;

final class CommentDateType extends AbstractCommentType implements Sortable, Filterable {

	use FiltersField;

	public function __construct() {
		parent::__construct( 'date' );
	}

	public function label(): string {
		return __( 'Comment date', 'corinthian-admin-column-manager' );
	}

	public function settings_schema( Screen $screen ): array {
		return $this->date_settings();
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		$comment = $this->comment( $object_id );
		if ( ! $comment || str_starts_with( $comment->comment_date_gmt, '0000' ) ) {
			return null;
		}
		return $comment->comment_date_gmt;
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		$timestamp = strtotime( ( is_scalar( $value ) ? (string) $value : '' ) . ' UTC' );
		return $timestamp ? $this->format_timestamp( $timestamp, $config ) : '';
	}

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		global $wpdb;
		$column = "{$wpdb->comments}.comment_date_gmt";
		return $this->order_by( $clauses, $column, $order, "({$column} IS NULL OR {$column} < '1000-01-01')" );
	}

	protected function filter_spec( Config $config, Screen $screen ): ?array {
		global $wpdb;
		return array(
			'kind' => FilterSql::DATE,
			'expr' => "{$wpdb->comments}.comment_date_gmt",
		);
	}
}
