<?php

namespace Corinthian\Columns\Types\Comments;

use WP_Comment;
use WP_Error;

/**
 * Validation and storage for editing comment columns, through wp_update_comment
 * and wp_set_comment_status.
 */
final class CommentEditor {

	private const MODES = array(
		'author'     => array( 'set' ),
		'author_url' => array( 'set', 'clear' ),
		'excerpt'    => array( 'set' ),
		'status'     => array( 'set' ),
	);

	/**
	 * @return array<string,mixed>
	 */
	public static function field( string $kind ): array {
		$field = array(
			'input' => match ( $kind ) {
				'excerpt' => 'textarea',
				'status'  => 'select',
				default   => 'text',
			},
		);
		if ( 'status' === $kind ) {
			$field['options'] = self::status_options();
		}
		// Email is inline only.
		if ( 'author_email' !== $kind ) {
			$field['bulk'] = self::MODES[ $kind ] ?? array( 'set' );
		}
		return $field;
	}

	public static function value( string $kind, int $id ): mixed {
		$comment = get_comment( $id );
		if ( ! $comment instanceof WP_Comment ) {
			return null;
		}
		return match ( $kind ) {
			'author'       => $comment->comment_author,
			'author_email' => $comment->comment_author_email,
			'author_url'   => $comment->comment_author_url,
			'excerpt'      => $comment->comment_content,
			default        => match ( wp_get_comment_status( $comment ) ) {
				'approved'   => 'approve',
				'unapproved' => 'hold',
				default      => (string) wp_get_comment_status( $comment ),
			},
		};
	}

	public static function save( string $kind, int $id, mixed $value, string $mode ): bool|WP_Error {
		$comment = get_comment( $id );
		if ( ! $comment instanceof WP_Comment ) {
			return new WP_Error( 'corinthian_missing', __( 'That comment does not exist.', 'corinthian-admin-column-manager' ) );
		}
		if ( ! current_user_can( 'edit_comment', $id ) ) {
			return new WP_Error( 'corinthian_forbidden', __( 'You cannot edit this comment.', 'corinthian-admin-column-manager' ) );
		}
		$modes = 'author_email' === $kind ? array( 'set' ) : ( self::MODES[ $kind ] ?? array() );
		if ( ! in_array( $mode, $modes, true ) ) {
			return new WP_Error( 'corinthian_bad_mode', __( 'This column does not support that edit.', 'corinthian-admin-column-manager' ) );
		}
		if ( 'clear' === $mode ) {
			$value = '';
		} elseif ( ! is_scalar( $value ) ) {
			return new WP_Error( 'corinthian_invalid', __( 'Invalid value.', 'corinthian-admin-column-manager' ) );
		}
		$text = trim( (string) $value );

		if ( 'status' === $kind ) {
			return self::set_status( $id, $text );
		}
		$args = match ( $kind ) {
			'author'       => self::required_text( 'comment_author', $text, 245 ),
			'author_email' => self::email( $text ),
			'author_url'   => self::url( $text ),
			default        => self::content( $text ),
		};
		if ( $args instanceof WP_Error ) {
			return $args;
		}
		$result = wp_update_comment( wp_slash( array_merge( array( 'comment_ID' => $id ), $args ) ), true );
		return is_wp_error( $result ) ? $result : true;
	}

	private static function set_status( int $id, string $status ): bool|WP_Error {
		if ( ! isset( self::status_options()[ $status ] ) ) {
			return new WP_Error( 'corinthian_invalid', __( 'Choose approve, hold, spam or trash.', 'corinthian-admin-column-manager' ) );
		}
		if ( ! current_user_can( 'moderate_comments' ) ) {
			return new WP_Error( 'corinthian_forbidden', __( 'You cannot moderate comments.', 'corinthian-admin-column-manager' ) );
		}
		$result = wp_set_comment_status( $id, $status, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $result ? true : new WP_Error( 'corinthian_failed', __( 'The comment status could not be changed.', 'corinthian-admin-column-manager' ) );
	}

	/**
	 * @return array<string,string>|WP_Error
	 */
	private static function required_text( string $column, string $text, int $max ): array|WP_Error {
		$text = sanitize_text_field( $text );
		if ( '' === $text ) {
			return new WP_Error( 'corinthian_invalid', __( 'This cannot be empty.', 'corinthian-admin-column-manager' ) );
		}
		if ( strlen( $text ) > $max ) {
			return new WP_Error( 'corinthian_invalid', __( 'That is too long.', 'corinthian-admin-column-manager' ) );
		}
		return array( $column => $text );
	}

	/**
	 * @return array<string,string>|WP_Error
	 */
	private static function email( string $text ): array|WP_Error {
		$email = sanitize_email( $text );
		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error( 'corinthian_invalid', __( 'Enter a valid email address.', 'corinthian-admin-column-manager' ) );
		}
		return array( 'comment_author_email' => $email );
	}

	/**
	 * @return array<string,string>|WP_Error
	 */
	private static function url( string $text ): array|WP_Error {
		if ( '' === $text ) {
			return array( 'comment_author_url' => '' );
		}
		$url = esc_url_raw( $text, array( 'http', 'https' ) );
		if ( '' === $url || false === filter_var( $url, FILTER_VALIDATE_URL ) || strlen( $url ) > 200 ) {
			return new WP_Error( 'corinthian_invalid', __( 'Enter a valid web address.', 'corinthian-admin-column-manager' ) );
		}
		return array( 'comment_author_url' => $url );
	}

	/**
	 * @return array<string,string>|WP_Error
	 */
	private static function content( string $text ): array|WP_Error {
		if ( '' === $text ) {
			return new WP_Error( 'corinthian_invalid', __( 'A comment cannot be empty.', 'corinthian-admin-column-manager' ) );
		}
		return array( 'comment_content' => $text );
	}

	/**
	 * @return array<string,string>
	 */
	private static function status_options(): array {
		return array(
			'approve' => __( 'Approved', 'corinthian-admin-column-manager' ),
			'hold'    => __( 'Pending', 'corinthian-admin-column-manager' ),
			'spam'    => __( 'Spam', 'corinthian-admin-column-manager' ),
			'trash'   => __( 'Trash', 'corinthian-admin-column-manager' ),
		);
	}
}
