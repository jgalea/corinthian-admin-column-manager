<?php

namespace Corinthian\Columns\Types;

use WP_Post;

/**
 * SQL can't count words cheaply, so the count is stored as post meta on
 * save_post. Posts saved before the plugin was active get it on first render;
 * until then they sort last.
 */
final class WordCountMeta {

	public const KEY = '_corinthian_word_count';

	public static function register(): void {
		add_action( 'save_post', array( self::class, 'on_save' ) );
	}

	public static function on_save( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		self::store( $post_id );
	}

	public static function get( int $post_id ): int {
		$stored = get_post_meta( $post_id, self::KEY, true );
		return '' === $stored ? self::store( $post_id ) : (int) $stored;
	}

	public static function store( int $post_id ): int {
		$post  = get_post( $post_id );
		$count = $post instanceof WP_Post ? self::count_words( self::plain_text( $post ) ) : 0;
		update_post_meta( $post_id, self::KEY, $count );
		return $count;
	}

	public static function count_words( string $text ): int {
		$words = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $words ) ? count( $words ) : 0;
	}

	public static function plain_text( WP_Post $post ): string {
		return trim( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) );
	}
}
