<?php

namespace Corinthian\Columns;

defined( 'ABSPATH' ) || exit;

/**
 * One column as placed in a set: which type it is, plus the user's choices for it.
 *
 * Native columns (ones WordPress or another plugin registers) use the type
 * "native" and keep their original key in settings.key, so their own render
 * callbacks keep working.
 */
final class Config {

	/**
	 * @param array<string,mixed> $settings
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $type,
		public readonly string $label,
		public readonly ?int $width = null,
		public readonly string $width_unit = 'px',
		public readonly array $settings = array(),
	) {}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function from_array( array $data ): self {
		$width = isset( $data['width'] ) && '' !== $data['width'] && null !== $data['width'] ? max( 0, (int) $data['width'] ) : null;
		$unit  = ( $data['width_unit'] ?? 'px' ) === '%' ? '%' : 'px';

		return new self(
			id: sanitize_key( (string) ( $data['id'] ?? '' ) ),
			type: sanitize_text_field( (string) ( $data['type'] ?? '' ) ),
			label: sanitize_text_field( (string) ( $data['label'] ?? '' ) ),
			width: $width > 0 ? $width : null,
			width_unit: $unit,
			settings: is_array( $data['settings'] ?? null ) ? $data['settings'] : array(),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return array(
			'id'         => $this->id,
			'type'       => $this->type,
			'label'      => $this->label,
			'width'      => $this->width,
			'width_unit' => $this->width_unit,
			'settings'   => $this->settings,
		);
	}

	public function setting( string $key, mixed $fallback = null ): mixed {
		return $this->settings[ $key ] ?? $fallback;
	}

	/**
	 * The key this column gets in the list table's columns array.
	 */
	public function table_key(): string {
		if ( 'native' === $this->type ) {
			return (string) $this->setting( 'key', $this->id );
		}
		return 'corinthian-' . $this->id;
	}
}
