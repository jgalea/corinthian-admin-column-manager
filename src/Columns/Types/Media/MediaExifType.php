<?php

namespace Corinthian\Columns\Types\Media;

use Corinthian\Columns\Config;
use Corinthian\Screens\Screen;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * One field of the EXIF data WordPress keeps in image_meta.
 */
final class MediaExifType extends AbstractMediaType {

	private const KEYS = array(
		'camera'       => 'camera',
		'aperture'     => 'aperture',
		'iso'          => 'iso',
		'shutter'      => 'shutter_speed',
		'focal_length' => 'focal_length',
		'created'      => 'created_timestamp',
	);

	public function __construct( string $field ) {
		parent::__construct( 'exif_' . $field );
	}

	private function exif_key(): string {
		return self::KEYS[ substr( $this->field, 5 ) ];
	}

	public function label(): string {
		return match ( $this->exif_key() ) {
			'camera'        => __( 'Camera', 'corinthian-admin-column-manager' ),
			'aperture'      => __( 'Aperture', 'corinthian-admin-column-manager' ),
			'iso'           => __( 'ISO', 'corinthian-admin-column-manager' ),
			'shutter_speed' => __( 'Shutter speed', 'corinthian-admin-column-manager' ),
			'focal_length'  => __( 'Focal length', 'corinthian-admin-column-manager' ),
			default         => __( 'Date taken', 'corinthian-admin-column-manager' ),
		};
	}

	public function group(): string {
		return __( 'EXIF', 'corinthian-admin-column-manager' );
	}

	public function settings_schema( Screen $screen ): array {
		return 'created_timestamp' === $this->exif_key() ? $this->date_settings() : array();
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		$image_meta = MediaMeta::metadata( $object_id )['image_meta'] ?? null;
		if ( ! is_array( $image_meta ) ) {
			return null;
		}
		$value = $image_meta[ $this->exif_key() ] ?? null;
		// WordPress stores "0" for fields the file does not have.
		if ( null === $value || '' === $value || 0 === $value || '0' === $value || ! is_scalar( $value ) ) {
			return null;
		}
		return $value;
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		$text = is_scalar( $value ) ? (string) $value : '';
		switch ( $this->exif_key() ) {
			case 'aperture':
				return esc_html( 'f/' . $text );
			case 'shutter_speed':
				$seconds = (float) $text;
				if ( $seconds > 0 && $seconds < 1 ) {
					return esc_html( '1/' . round( 1 / $seconds ) . ' s' );
				}
				return esc_html( $text . ' s' );
			case 'focal_length':
				return esc_html( $text . ' mm' );
			case 'created_timestamp':
				// WordPress stores the camera's local time as if it were UTC.
				return $this->format_timestamp( (int) $text, $config, new DateTimeZone( 'UTC' ) );
			default:
				return esc_html( $text );
		}
	}
}
