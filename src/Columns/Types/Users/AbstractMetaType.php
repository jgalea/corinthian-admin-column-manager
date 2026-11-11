<?php

namespace Corinthian\Columns\Types\Users;

use Corinthian\Columns\Config;
use Corinthian\Columns\Editable;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Sortable;
use Corinthian\Screens\Screen;

defined( 'ABSPATH' ) || exit;

/**
 * Any meta key on a user, comment or term, shown like the post custom field column.
 */
abstract class AbstractMetaType extends ScreenFieldType implements Sortable, Filterable, Editable {

	public function __construct( string $field = 'meta' ) {
		parent::__construct( $field );
	}

	abstract protected function meta_table(): string;

	abstract protected function meta_id_column(): string;

	abstract protected function outer_id(): string;

	/**
	 * @return array<int,mixed>
	 */
	abstract protected function read_meta( int $object_id, string $key ): array;

	public function label(): string {
		return __( 'Custom field', 'corinthian-admin-column-manager' );
	}

	public function group(): string {
		return __( 'Custom fields', 'corinthian-admin-column-manager' );
	}

	protected function default_key(): string {
		return '';
	}

	protected function default_display(): string {
		return 'text';
	}

	public function settings_schema( Screen $screen ): array {
		return array(
			array(
				'name'    => 'meta_key',
				'type'    => 'meta_key',
				'label'   => __( 'Field', 'corinthian-admin-column-manager' ),
				'default' => $this->default_key(),
			),
			array(
				'name'    => 'display',
				'type'    => 'select',
				'label'   => __( 'Show as', 'corinthian-admin-column-manager' ),
				'default' => $this->default_display(),
				'options' => array(
					'text'     => __( 'Text', 'corinthian-admin-column-manager' ),
					'number'   => __( 'Number', 'corinthian-admin-column-manager' ),
					'date'     => __( 'Date', 'corinthian-admin-column-manager' ),
					'datetime' => __( 'Date and time', 'corinthian-admin-column-manager' ),
					'image'    => __( 'Image', 'corinthian-admin-column-manager' ),
					'url'      => __( 'Link', 'corinthian-admin-column-manager' ),
					'boolean'  => __( 'Yes / No', 'corinthian-admin-column-manager' ),
				),
			),
		);
	}

	protected function meta_key( Config $config ): string {
		$key = (string) $config->setting( 'meta_key', '' );
		return '' !== $key ? $key : $this->default_key();
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		$key = $this->meta_key( $config );
		if ( '' === $key ) {
			return null;
		}
		$values = $this->read_meta( $object_id, $key );
		if ( ! $values ) {
			return null;
		}
		return 1 === count( $values ) ? $values[0] : $values;
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			$flat = array_filter( (array) $value, 'is_scalar' );
			return $flat ? esc_html( implode( ', ', array_map( 'strval', $flat ) ) ) : '<code>' . esc_html__( '(complex value)', 'corinthian-admin-column-manager' ) . '</code>';
		}
		$value   = is_scalar( $value ) ? (string) $value : '';
		$display = (string) $config->setting( 'display', $this->default_display() );

		switch ( $display ) {
			case 'number':
				return is_numeric( $value ) ? esc_html( number_format_i18n( (float) $value, (int) ( fmod( (float) $value, 1 ) ? 2 : 0 ) ) ) : esc_html( $value );
			case 'date':
			case 'datetime':
				$ts = is_numeric( $value ) ? (int) $value : strtotime( $value );
				if ( ! $ts ) {
					return esc_html( $value );
				}
				$date_format = (string) get_option( 'date_format' );
				if ( 'datetime' === $display ) {
					$date_format .= ' ' . get_option( 'time_format' );
				}
				return esc_html( wp_date( $date_format, $ts ) );
			case 'image':
				$img = is_numeric( $value ) ? wp_get_attachment_image( (int) $value, array( 60, 60 ) ) : '';
				if ( $img ) {
					return $img;
				}
				return wp_http_validate_url( $value ) ? '<img src="' . esc_url( $value ) . '" alt="" style="max-width:60px;max-height:60px">' : esc_html( $value );
			case 'url':
				return wp_http_validate_url( $value ) ? $this->link( $value, $value, true ) : esc_html( $value );
			case 'boolean':
				$on = ! in_array( strtolower( $value ), array( '', '0', 'false', 'no', 'off' ), true );
				return $on ? '<span class="dashicons dashicons-yes" aria-label="' . esc_attr__( 'Yes', 'corinthian-admin-column-manager' ) . '"></span>' : '<span class="dashicons dashicons-no-alt" aria-label="' . esc_attr__( 'No', 'corinthian-admin-column-manager' ) . '"></span>';
			default:
				return esc_html( wp_trim_words( $value, 30 ) );
		}
	}

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		$key = $this->meta_key( $config );
		if ( '' === $key ) {
			return $clauses;
		}
		$sub  = $this->meta_subselect( $this->meta_table(), $this->meta_id_column(), $this->outer_id(), $key );
		$sort = 'number' === $config->setting( 'display', $this->default_display() ) ? "CAST({$sub} AS DECIMAL(30,6))" : $sub;
		return $this->order_by( $clauses, $sort, $order, self::blank( $sub ) );
	}

	public function filter_operators( Config $config, Screen $screen ): array {
		return FilterSql::meta_operators( (string) $config->setting( 'display', $this->default_display() ) );
	}

	public function filter_condition( Config $config, string $op, mixed $value, Screen $screen, string $alias ): ?array {
		$key = $this->meta_key( $config );
		if ( '' === $key ) {
			return null;
		}
		$where = FilterSql::meta(
			FilterSql::meta_kind( (string) $config->setting( 'display', $this->default_display() ) ),
			$op,
			$value,
			array(
				'table'     => $this->meta_table(),
				'id_column' => $this->meta_id_column(),
				'outer_id'  => $this->outer_id(),
				'key'       => $key,
			),
			$alias
		);
		return null === $where ? null : array(
			'join'  => '',
			'where' => $where,
		);
	}

	public function edit_field( Config $config, Screen $screen ): array {
		return MetaEditor::field( (string) $config->setting( 'display', $this->default_display() ) );
	}

	public function edit_value( int $object_id, Config $config, Screen $screen ): mixed {
		return MetaEditor::value( $this->prefix(), $object_id, $this->meta_key( $config ) );
	}

	public function save( int $object_id, Config $config, Screen $screen, mixed $value, string $mode = 'set' ): bool|\WP_Error {
		return MetaEditor::save( $this->prefix(), $object_id, $this->meta_key( $config ), $value, $mode, (string) $config->setting( 'display', $this->default_display() ) );
	}
}
