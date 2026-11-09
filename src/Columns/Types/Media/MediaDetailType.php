<?php

namespace Corinthian\Columns\Types\Media;

use Corinthian\Columns\Config;
use Corinthian\Screens\Screen;

/**
 * Attachment columns that are not sortable: dimensions, caption, description, URL, sizes, featured image use.
 */
class MediaDetailType extends AbstractMediaType {

	public function label(): string {
		return match ( $this->field ) {
			'dimensions'     => __( 'Dimensions', 'corinthian-admin-column-manager' ),
			'caption'        => __( 'Caption', 'corinthian-admin-column-manager' ),
			'description'    => __( 'Description', 'corinthian-admin-column-manager' ),
			'url'            => __( 'Full URL', 'corinthian-admin-column-manager' ),
			'sizes'          => __( 'Available sizes', 'corinthian-admin-column-manager' ),
			default          => __( 'Used as featured image', 'corinthian-admin-column-manager' ),
		};
	}

	public function group(): string {
		return in_array( $this->field, array( 'dimensions', 'url', 'sizes' ), true ) ? __( 'File', 'corinthian-admin-column-manager' ) : parent::group();
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		$post = get_post( $object_id );
		if ( ! $post ) {
			return null;
		}
		switch ( $this->field ) {
			case 'dimensions':
				$width  = MediaMeta::width( $object_id );
				$height = MediaMeta::height( $object_id );
				return $width && $height ? $width . ' × ' . $height : null;
			case 'caption':
				return wp_trim_words( wp_strip_all_tags( $post->post_excerpt ), 30 );
			case 'description':
				return wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
			case 'url':
				$url = wp_get_attachment_url( $object_id );
				return $url ? $url : null;
			case 'sizes':
				$meta  = MediaMeta::metadata( $object_id );
				$sizes = array();
				if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
					$sizes[] = 'full (' . (int) $meta['width'] . '×' . (int) $meta['height'] . ')';
				}
				foreach ( (array) ( $meta['sizes'] ?? array() ) as $name => $size ) {
					if ( is_array( $size ) && isset( $size['width'], $size['height'] ) ) {
						$sizes[] = $name . ' (' . (int) $size['width'] . '×' . (int) $size['height'] . ')';
					}
				}
				return $sizes;
			default:
				return $this->featured_count( $object_id );
		}
	}

	private function featured_count( int $attachment_id ): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT pm.post_id) FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_thumbnail_id' AND pm.meta_value = %s AND p.post_status NOT IN ('trash', 'auto-draft') AND p.post_type <> 'revision'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(string) $attachment_id
			)
		);
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		if ( 'url' === $this->field ) {
			$text = is_scalar( $value ) ? (string) $value : '';
			return $this->link( $text, $text, true );
		}
		return parent::format( $value, $object_id, $config, $screen );
	}
}
