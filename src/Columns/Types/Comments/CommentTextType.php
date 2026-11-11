<?php

namespace Corinthian\Columns\Types\Comments;

use Corinthian\Columns\Config;
use Corinthian\Screens\Screen;

defined( 'ABSPATH' ) || exit;

/**
 * Comment columns that are not sortable: author URL, reply-to, excerpt, word count, user agent.
 */
class CommentTextType extends AbstractCommentType {

	public function label(): string {
		return match ( $this->field ) {
			'author_url' => __( 'Author URL', 'corinthian-admin-column-manager' ),
			'parent'     => __( 'In reply to', 'corinthian-admin-column-manager' ),
			'excerpt'    => __( 'Excerpt', 'corinthian-admin-column-manager' ),
			'word_count' => __( 'Word count', 'corinthian-admin-column-manager' ),
			default      => __( 'User agent', 'corinthian-admin-column-manager' ),
		};
	}

	public function settings_schema( Screen $screen ): array {
		if ( 'excerpt' !== $this->field ) {
			return array();
		}
		return array(
			array(
				'name'    => 'words',
				'type'    => 'number',
				'label'   => __( 'Word limit', 'corinthian-admin-column-manager' ),
				'default' => 20,
			),
		);
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		$comment = $this->comment( $object_id );
		if ( ! $comment ) {
			return null;
		}
		switch ( $this->field ) {
			case 'author_url':
				return (string) $comment->comment_author_url;
			case 'parent':
				$parent = (int) $comment->comment_parent ? $this->comment( (int) $comment->comment_parent ) : null;
				return $parent ? $parent->comment_author . ' (#' . $parent->comment_ID . ')' : null;
			case 'excerpt':
				$words = max( 1, min( 500, (int) $config->setting( 'words', 20 ) ) );
				return wp_trim_words( wp_strip_all_tags( $comment->comment_content ), $words );
			case 'word_count':
				$plain = trim( wp_strip_all_tags( $comment->comment_content ) );
				$words = '' === $plain ? array() : preg_split( '/\s+/u', $plain );
				return is_array( $words ) ? count( $words ) : 0;
			default:
				return (string) $comment->comment_agent;
		}
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		$text = is_scalar( $value ) ? (string) $value : '';
		if ( 'author_url' === $this->field ) {
			return $this->link( $text, $text, true );
		}
		if ( 'parent' === $this->field ) {
			$comment = $this->comment( $object_id );
			return $comment ? $this->link( get_comment_link( (int) $comment->comment_parent ), $text ) : esc_html( $text );
		}
		return esc_html( $text );
	}
}
