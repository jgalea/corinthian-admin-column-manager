<?php

namespace Corinthian\Columns\Types;

use Corinthian\Columns\Config;
use Corinthian\Columns\Editable;
use Corinthian\Columns\Filterable;
use Corinthian\Screens\Screen;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class ExcerptType extends AbstractPostType implements Filterable, Editable {

	use EditsPost;

	public function key(): string {
		return 'post.excerpt';
	}

	public function label(): string {
		return __( 'Excerpt', 'corinthian-admin-column-manager' );
	}

	protected function group_slug(): string {
		return 'content';
	}

	protected function supports_post_type( string $post_type ): bool {
		return post_type_supports( $post_type, 'excerpt' );
	}

	public function settings_schema( Screen $screen ): array {
		return array(
			array(
				'name'    => 'words',
				'type'    => 'number',
				'label'   => __( 'Word limit', 'corinthian-admin-column-manager' ),
				'default' => 20,
			),
		);
	}

	protected function post_value( WP_Post $post, Config $config, Screen $screen ): mixed {
		return trim( wp_strip_all_tags( $post->post_excerpt ) );
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		return esc_html( wp_trim_words( (string) $value, max( 1, (int) $config->setting( 'words', 20 ) ), '…' ) );
	}

	public function filter_operators( Config $config, Screen $screen ): array {
		return PostFilter::text_ops();
	}

	public function filter_condition( Config $config, string $op, mixed $value, Screen $screen, string $alias ): ?array {
		global $wpdb;
		return PostFilter::wrap( PostFilter::text_where( "{$wpdb->posts}.post_excerpt", $op, $value ) );
	}

	protected function edit_kind(): string {
		return 'excerpt';
	}
}
