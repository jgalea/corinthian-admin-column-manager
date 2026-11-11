<?php

namespace Corinthian\Columns\Types;

use Corinthian\Columns\Config;
use Corinthian\Screens\Screen;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class LinksCountType extends AbstractPostType {

	public function key(): string {
		return 'post.links_count';
	}

	public function label(): string {
		return __( 'Links in content', 'corinthian-admin-column-manager' );
	}

	protected function group_slug(): string {
		return 'content';
	}

	protected function supports_post_type( string $post_type ): bool {
		return post_type_supports( $post_type, 'editor' );
	}

	public function settings_schema( Screen $screen ): array {
		return array(
			array(
				'name'    => 'show',
				'type'    => 'select',
				'label'   => __( 'Count', 'corinthian-admin-column-manager' ),
				'default' => 'both',
				'options' => array(
					'both'     => __( 'Internal and external', 'corinthian-admin-column-manager' ),
					'internal' => __( 'Internal only', 'corinthian-admin-column-manager' ),
					'external' => __( 'External only', 'corinthian-admin-column-manager' ),
				),
			),
		);
	}

	protected function post_value( WP_Post $post, Config $config, Screen $screen ): mixed {
		$counts = array(
			'internal' => 0,
			'external' => 0,
		);
		if ( ! preg_match_all( '/<a\b[^>]*?\shref\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $post->post_content, $found, PREG_SET_ORDER ) ) {
			return $counts;
		}
		$home = $this->host( home_url() );
		foreach ( $found as $match ) {
			$href = trim( html_entity_decode( '' !== $match[1] ? $match[1] : ( $match[2] ?? '' ) ) );
			if ( '' === $href || preg_match( '/^(#|mailto:|tel:|javascript:)/i', $href ) ) {
				continue;
			}
			$host = $this->host( str_starts_with( $href, '//' ) ? 'https:' . $href : $href );
			++$counts[ '' === $host || $host === $home ? 'internal' : 'external' ];
		}
		return $counts;
	}

	private function host( string $url ): string {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return is_string( $host ) ? preg_replace( '/^www\./', '', strtolower( $host ) ) ?? '' : '';
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		$counts = (array) $value;
		$show   = (string) $config->setting( 'show', 'both' );
		if ( 'internal' === $show || 'external' === $show ) {
			return esc_html( (string) (int) $counts[ $show ] );
		}
		/* translators: 1: internal link count, 2: external link count. */
		return esc_html( sprintf( __( '%1$d internal / %2$d external', 'corinthian-admin-column-manager' ), (int) $counts['internal'], (int) $counts['external'] ) );
	}
}
