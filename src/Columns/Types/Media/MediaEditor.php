<?php

namespace Corinthian\Columns\Types\Media;

use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Validation and storage for editing media columns, through wp_update_post and post meta.
 */
final class MediaEditor {

	private const INPUTS = array(
		'alt'         => 'text',
		'caption'     => 'textarea',
		'description' => 'textarea',
		'attached_to' => 'number',
	);

	/**
	 * @return array<string,mixed>
	 */
	public static function field( string $kind ): array {
		return array(
			'input' => self::INPUTS[ $kind ] ?? 'text',
			'bulk'  => array( 'set', 'clear' ),
		);
	}

	public static function value( string $kind, int $id ): mixed {
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}
		return match ( $kind ) {
			'alt'         => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'caption'     => $post->post_excerpt,
			'description' => $post->post_content,
			default       => (int) $post->post_parent,
		};
	}

	public static function save( string $kind, int $id, mixed $value, string $mode ): bool|WP_Error {
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type ) {
			return new WP_Error( 'corinthian_missing', __( 'That media item does not exist.', 'corinthian-admin-column-manager' ) );
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			return new WP_Error( 'corinthian_forbidden', __( 'You cannot edit this item.', 'corinthian-admin-column-manager' ) );
		}
		if ( ! in_array( $mode, array( 'set', 'clear' ), true ) ) {
			return new WP_Error( 'corinthian_bad_mode', __( 'This column does not support that edit.', 'corinthian-admin-column-manager' ) );
		}
		if ( 'clear' === $mode ) {
			$value = '';
		} elseif ( ! is_scalar( $value ) ) {
			return new WP_Error( 'corinthian_invalid', __( 'Invalid value.', 'corinthian-admin-column-manager' ) );
		}
		$text = trim( (string) $value );

		switch ( $kind ) {
			case 'alt':
				if ( ! wp_attachment_is_image( $post ) ) {
					return new WP_Error( 'corinthian_invalid', __( 'Only images have alt text.', 'corinthian-admin-column-manager' ) );
				}
				$text = sanitize_text_field( $text );
				if ( '' === $text ) {
					delete_post_meta( $id, '_wp_attachment_image_alt' );
				} else {
					update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( $text ) );
				}
				return true;
			case 'caption':
				return self::update(
					array(
						'ID'           => $id,
						'post_excerpt' => $text,
					)
				);
			case 'description':
				return self::update(
					array(
						'ID'           => $id,
						'post_content' => $text,
					)
				);
			default:
				$parent = self::parent_id( $post, $text );
				return $parent instanceof WP_Error ? $parent : self::update(
					array(
						'ID'          => $id,
						'post_parent' => $parent,
					)
				);
		}
	}

	private static function parent_id( WP_Post $post, string $text ): int|WP_Error {
		if ( '' === $text || '0' === $text ) {
			return 0;
		}
		if ( ! ctype_digit( $text ) ) {
			return new WP_Error( 'corinthian_invalid', __( 'Enter the ID of a post.', 'corinthian-admin-column-manager' ) );
		}
		$parent = get_post( (int) $text );
		if ( ! $parent instanceof WP_Post || $parent->ID === $post->ID || in_array( $parent->post_type, array( 'attachment', 'revision', 'nav_menu_item' ), true ) ) {
			return new WP_Error( 'corinthian_invalid', __( 'That post cannot hold attachments.', 'corinthian-admin-column-manager' ) );
		}
		if ( ! current_user_can( 'edit_post', $parent->ID ) ) {
			return new WP_Error( 'corinthian_forbidden', __( 'You cannot attach media to that post.', 'corinthian-admin-column-manager' ) );
		}
		return $parent->ID;
	}

	/**
	 * @param array<string,mixed> $args
	 */
	private static function update( array $args ): bool|WP_Error {
		$result = wp_update_post( wp_slash( $args ), true );
		return is_wp_error( $result ) ? $result : true;
	}
}
