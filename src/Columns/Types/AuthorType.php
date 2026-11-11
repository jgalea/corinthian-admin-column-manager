<?php

namespace Corinthian\Columns\Types;

use Corinthian\Columns\Config;
use Corinthian\Columns\Editable;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Sortable;
use Corinthian\Screens\Screen;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class AuthorType extends AbstractPostType implements Sortable, Filterable, Editable {

	use EditsPost;

	private const COLUMNS = array(
		'display_name' => 'display_name',
		'user_login'   => 'user_login',
		'user_email'   => 'user_email',
		'avatar_name'  => 'display_name',
	);

	public function key(): string {
		return 'post.author';
	}

	public function label(): string {
		return __( 'Author', 'corinthian-admin-column-manager' );
	}

	protected function group_slug(): string {
		return 'author';
	}

	protected function supports_post_type( string $post_type ): bool {
		return post_type_supports( $post_type, 'author' );
	}

	public function settings_schema( Screen $screen ): array {
		return array(
			array(
				'name'    => 'display',
				'type'    => 'select',
				'label'   => __( 'Show', 'corinthian-admin-column-manager' ),
				'default' => 'display_name',
				'options' => array(
					'display_name' => __( 'Display name', 'corinthian-admin-column-manager' ),
					'user_login'   => __( 'Username', 'corinthian-admin-column-manager' ),
					'user_email'   => __( 'Email', 'corinthian-admin-column-manager' ),
					'avatar_name'  => __( 'Avatar and name', 'corinthian-admin-column-manager' ),
				),
			),
		);
	}

	private function display( Config $config ): string {
		$display = (string) $config->setting( 'display', 'display_name' );
		return isset( self::COLUMNS[ $display ] ) ? $display : 'display_name';
	}

	protected function post_value( WP_Post $post, Config $config, Screen $screen ): mixed {
		$user = get_userdata( (int) $post->post_author );
		if ( ! $user ) {
			return null;
		}
		return (string) $user->{self::COLUMNS[ $this->display( $config ) ]};
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		$post = get_post( $object_id );
		if ( ! $post ) {
			return '';
		}
		$url  = add_query_arg(
			array(
				'post_type' => $post->post_type,
				'author'    => (int) $post->post_author,
			),
			admin_url( 'edit.php' )
		);
		$text = esc_html( (string) $value );
		if ( 'avatar_name' === $this->display( $config ) ) {
			$text = get_avatar( (int) $post->post_author, 24 ) . ' ' . $text;
		}
		return '<a href="' . esc_url( $url ) . '">' . $text . '</a>';
	}

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		global $wpdb;
		$alias            = $this->alias( $config );
		$col              = $alias . '.' . self::COLUMNS[ $this->display( $config ) ];
		$clauses['join'] .= " LEFT JOIN {$wpdb->users} AS {$alias} ON {$alias}.ID = {$wpdb->posts}.post_author";
		return $this->order_by( $clauses, $col, $order, "{$col} IS NULL OR {$col} = ''" );
	}

	public function filter_operators( Config $config, Screen $screen ): array {
		return PostFilter::in_ops( PostFilter::author_options() );
	}

	public function filter_condition( Config $config, string $op, mixed $value, Screen $screen, string $alias ): ?array {
		global $wpdb;
		return PostFilter::wrap( PostFilter::in_where( "{$wpdb->posts}.post_author", $op, $value, true ) );
	}

	protected function edit_kind(): string {
		return 'author';
	}
}
