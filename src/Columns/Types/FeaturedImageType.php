<?php

namespace Corinthian\Columns\Types;

use Corinthian\Columns\Config;
use Corinthian\Columns\Editable;
use Corinthian\Columns\Filterable;
use Corinthian\Screens\Screen;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class FeaturedImageType extends AbstractPostType implements Filterable, Editable {

	use EditsPost;

	public function key(): string {
		return 'post.featured_image';
	}

	public function label(): string {
		return __( 'Featured image', 'corinthian-admin-column-manager' );
	}

	protected function group_slug(): string {
		return 'media';
	}

	protected function supports_post_type( string $post_type ): bool {
		return post_type_supports( $post_type, 'thumbnail' );
	}

	public function settings_schema( Screen $screen ): array {
		$sizes = array();
		foreach ( get_intermediate_image_sizes() as $size ) {
			$sizes[ $size ] = $size;
		}
		return array(
			array(
				'name'    => 'size',
				'type'    => 'select',
				'label'   => __( 'Image size', 'corinthian-admin-column-manager' ),
				'default' => 'thumbnail',
				'options' => $sizes,
			),
		);
	}

	protected function post_value( WP_Post $post, Config $config, Screen $screen ): mixed {
		$id = (int) get_post_thumbnail_id( $post );
		return $id ? $id : null;
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		$size = (string) $config->setting( 'size', 'thumbnail' );
		if ( ! in_array( $size, get_intermediate_image_sizes(), true ) ) {
			$size = 'thumbnail';
		}
		$image = wp_get_attachment_image( (int) $value, $size );
		if ( '' === $image ) {
			return '';
		}
		$url = $this->edit_link( $object_id );
		return $url ? '<a href="' . $url . '">' . $image . '</a>' : $image;
	}

	public function filter_operators( Config $config, Screen $screen ): array {
		return PostFilter::empty_ops();
	}

	public function filter_condition( Config $config, string $op, mixed $value, Screen $screen, string $alias ): ?array {
		$has = PostFilter::meta_exists( $alias, '_thumbnail_id', "CAST({$alias}.meta_value AS UNSIGNED) > 0" );
		return PostFilter::wrap(
			match ( $op ) {
				'not_empty' => $has,
				'empty'     => "NOT {$has}",
				default     => null,
			}
		);
	}

	protected function edit_kind(): string {
		return 'featured_image';
	}
}
