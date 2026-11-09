<?php

namespace Corinthian\Columns\Types\Media;

/**
 * File size and pixel dimensions cached as plain post meta, because the
 * values inside _wp_attachment_metadata are serialized and cannot be sorted in SQL.
 */
final class MediaMeta {

	public const FILESIZE = '_corinthian_filesize';
	public const WIDTH    = '_corinthian_width';
	public const HEIGHT   = '_corinthian_height';

	private const BACKFILL_LIMIT = 2000;

	/**
	 * @return array<string,mixed>
	 */
	public static function metadata( int $attachment_id ): array {
		$meta = wp_get_attachment_metadata( $attachment_id );
		return is_array( $meta ) ? $meta : array();
	}

	public static function filesize( int $attachment_id ): ?int {
		return self::cached( $attachment_id, self::FILESIZE );
	}

	public static function width( int $attachment_id ): ?int {
		return self::cached( $attachment_id, self::WIDTH );
	}

	public static function height( int $attachment_id ): ?int {
		return self::cached( $attachment_id, self::HEIGHT );
	}

	private static function cached( int $attachment_id, string $key ): ?int {
		if ( ! metadata_exists( 'post', $attachment_id, $key ) ) {
			self::store( $attachment_id );
		}
		$value = get_post_meta( $attachment_id, $key, true );
		return '' === $value || ! is_numeric( $value ) ? null : (int) $value;
	}

	/**
	 * Stores all three cached values for one attachment. A missing value is
	 * stored as an empty string so it is not recomputed on every request.
	 */
	public static function store( int $attachment_id ): void {
		$meta = self::metadata( $attachment_id );

		$size = isset( $meta['filesize'] ) && is_numeric( $meta['filesize'] ) ? (int) $meta['filesize'] : null;
		if ( null === $size ) {
			$path = get_attached_file( $attachment_id );
			$size = $path && is_file( $path ) ? (int) filesize( $path ) : null;
		}

		$values = array(
			self::FILESIZE => $size,
			self::WIDTH    => isset( $meta['width'] ) && (int) $meta['width'] > 0 ? (int) $meta['width'] : null,
			self::HEIGHT   => isset( $meta['height'] ) && (int) $meta['height'] > 0 ? (int) $meta['height'] : null,
		);
		foreach ( $values as $key => $value ) {
			update_post_meta( $attachment_id, $key, null === $value ? '' : $value );
		}
	}

	/**
	 * Fills the cache for attachments that have none yet, so a sort sees every row.
	 */
	public static function backfill(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON (m.post_id = p.ID AND m.meta_key = %s) WHERE p.post_type = 'attachment' AND m.meta_id IS NULL LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::FILESIZE,
				self::BACKFILL_LIMIT
			)
		);
		foreach ( $ids as $id ) {
			self::store( (int) $id );
		}
	}

	/**
	 * Filter callback for wp_update_attachment_metadata: drop stale cached values.
	 *
	 * @param mixed $data
	 * @return mixed
	 */
	public static function forget( $data, int $attachment_id ) {
		delete_post_meta( $attachment_id, self::FILESIZE );
		delete_post_meta( $attachment_id, self::WIDTH );
		delete_post_meta( $attachment_id, self::HEIGHT );
		return $data;
	}
}
