<?php

namespace Corinthian\Columns\Types;

use Corinthian\Columns\Config;
use Corinthian\Screens\Screen;
use WP_Post;

final class PermalinkType extends AbstractPostType {

	public function key(): string {
		return 'post.permalink';
	}

	public function label(): string {
		return __( 'Permalink', 'corinthian-admin-column-manager' );
	}

	protected function post_value( WP_Post $post, Config $config, Screen $screen ): mixed {
		return is_post_type_viewable( $post->post_type ) ? get_permalink( $post ) : null;
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		$url = (string) $value;
		return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $url ) . '</a>';
	}
}
