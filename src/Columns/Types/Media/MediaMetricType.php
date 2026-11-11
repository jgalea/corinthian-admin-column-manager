<?php

namespace Corinthian\Columns\Types\Media;

use Corinthian\Columns\Config;
use Corinthian\Columns\Sortable;
use Corinthian\Screens\Screen;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Types\Users\FilterSql;
use Corinthian\Columns\Types\Users\FiltersField;

defined( 'ABSPATH' ) || exit;

/**
 * File size, width and height, sorted through the cached post meta in MediaMeta.
 */
final class MediaMetricType extends AbstractMediaType implements Sortable, Filterable {

	use FiltersField;

	private const KEYS = array(
		'file_size' => MediaMeta::FILESIZE,
		'width'     => MediaMeta::WIDTH,
		'height'    => MediaMeta::HEIGHT,
	);

	public function label(): string {
		return match ( $this->field ) {
			'file_size' => __( 'File size', 'corinthian-admin-column-manager' ),
			'width'     => __( 'Width', 'corinthian-admin-column-manager' ),
			default     => __( 'Height', 'corinthian-admin-column-manager' ),
		};
	}

	public function group(): string {
		return __( 'File', 'corinthian-admin-column-manager' );
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		if ( ! get_post( $object_id ) ) {
			return null;
		}
		return match ( $this->field ) {
			'file_size' => MediaMeta::filesize( $object_id ),
			'width'     => MediaMeta::width( $object_id ),
			default     => MediaMeta::height( $object_id ),
		};
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		$number = is_numeric( $value ) ? (int) $value : 0;
		if ( 'file_size' === $this->field ) {
			$size = size_format( $number );
			return esc_html( false !== $size ? $size : '0 B' );
		}
		/* translators: %s: number of pixels. */
		return esc_html( sprintf( __( '%s px', 'corinthian-admin-column-manager' ), number_format_i18n( $number ) ) );
	}

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		global $wpdb;
		MediaMeta::backfill();
		$sub = $this->meta_subselect( $wpdb->postmeta, 'post_id', "{$wpdb->posts}.ID", self::KEYS[ $this->field ] );
		return $this->order_by( $clauses, "CAST({$sub} AS UNSIGNED)", $order, self::blank( $sub ) );
	}

	protected function filter_spec( Config $config, Screen $screen ): ?array {
		global $wpdb;
		return array(
			'kind' => FilterSql::NUMBER,
			'meta' => array(
				'table'     => $wpdb->postmeta,
				'id_column' => 'post_id',
				'outer_id'  => "{$wpdb->posts}.ID",
				'key'       => self::KEYS[ $this->field ],
			),
		);
	}

	protected function before_filter(): void {
		MediaMeta::backfill();
	}
}
