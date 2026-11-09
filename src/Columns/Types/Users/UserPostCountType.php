<?php

namespace Corinthian\Columns\Types\Users;

use Corinthian\Columns\Config;
use Corinthian\Columns\Sortable;
use Corinthian\Screens\Screen;
use Corinthian\Columns\Filterable;

final class UserPostCountType extends AbstractUserType implements Sortable, Filterable {

	use FiltersField;

	public function __construct() {
		parent::__construct( 'post_count' );
	}

	public function label(): string {
		return __( 'Post count', 'corinthian-admin-column-manager' );
	}

	public function settings_schema( Screen $screen ): array {
		$options = array();
		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $type ) {
			$options[ $type->name ] = (string) $type->labels->name;
		}
		return array(
			array(
				'name'    => 'post_type',
				'type'    => 'select',
				'label'   => __( 'Post type', 'corinthian-admin-column-manager' ),
				'default' => 'post',
				'options' => $options,
			),
		);
	}

	private function post_type( Config $config ): string {
		$type = (string) $config->setting( 'post_type', 'post' );
		return post_type_exists( $type ) ? $type : 'post';
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		return (int) count_user_posts( $object_id, $this->post_type( $config ), true );
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		$count = is_numeric( $value ) ? (int) $value : 0;
		if ( $count < 1 ) {
			return esc_html( (string) $count );
		}
		$url = add_query_arg(
			array(
				'post_type' => $this->post_type( $config ),
				'author'    => $object_id,
			),
			admin_url( 'edit.php' )
		);
		return $this->link( $url, number_format_i18n( $count ) );
	}

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		global $wpdb;
		$sub = $wpdb->prepare( "(SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = {$wpdb->users}.ID AND post_type = %s AND post_status = 'publish')", $this->post_type( $config ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $this->order_by( $clauses, $sub, $order );
	}

	protected function filter_spec( Config $config, Screen $screen ): ?array {
		global $wpdb;
		return array(
			'kind' => FilterSql::NUMBER,
			'expr' => $wpdb->prepare( "(SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = {$wpdb->users}.ID AND post_type = %s AND post_status = 'publish')", $this->post_type( $config ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}
}
