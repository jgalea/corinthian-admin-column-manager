<?php

namespace Corinthian\Columns\Types\Users;

use Corinthian\Columns\Config;
use Corinthian\Columns\Sortable;
use Corinthian\Screens\Screen;
use Corinthian\Columns\Filterable;

defined( 'ABSPATH' ) || exit;

final class UserCommentCountType extends AbstractUserType implements Sortable, Filterable {

	use FiltersField;

	public function __construct() {
		parent::__construct( 'comment_count' );
	}

	public function label(): string {
		return __( 'Comment count', 'corinthian-admin-column-manager' );
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		return (int) get_comments(
			array(
				'user_id' => $object_id,
				'status'  => 'approve',
				'count'   => true,
			)
		);
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		$count = is_numeric( $value ) ? (int) $value : 0;
		if ( $count < 1 ) {
			return esc_html( (string) $count );
		}
		return $this->link( add_query_arg( 'user_id', $object_id, admin_url( 'edit-comments.php' ) ), number_format_i18n( $count ) );
	}

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		global $wpdb;
		$sub = "(SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = {$wpdb->users}.ID AND comment_approved = '1')";
		return $this->order_by( $clauses, $sub, $order );
	}

	protected function filter_spec( Config $config, Screen $screen ): ?array {
		global $wpdb;
		return array(
			'kind' => FilterSql::NUMBER,
			'expr' => "(SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = {$wpdb->users}.ID AND comment_approved = '1')",
		);
	}
}
