<?php

namespace Corinthian\Columns\Types\Users;

use Corinthian\Columns\AbstractType;
use Corinthian\Columns\Config;
use Corinthian\Screens\Screen;
use DateTimeZone;

/**
 * Shared base for the user, media, comment and term column types.
 */
abstract class ScreenFieldType extends AbstractType {

	public function __construct( protected string $field ) {}

	abstract protected function prefix(): string;

	public function key(): string {
		return $this->prefix() . '.' . $this->field;
	}

	public function render( int $object_id, Config $config, Screen $screen ): string {
		$value = $this->value( $object_id, $config, $screen );
		if ( null === $value || '' === $value || array() === $value ) {
			return $this->dash();
		}
		return $this->format( $value, $object_id, $config, $screen );
	}

	/**
	 * Escaped HTML for a non-empty value.
	 */
	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_map( 'strval', array_filter( $value, 'is_scalar' ) ) );
		}
		return esc_html( is_scalar( $value ) ? (string) $value : '' );
	}

	protected function dash(): string {
		return '<span aria-hidden="true">&#8212;</span>';
	}

	protected function link( string $url, string $text, bool $external = false ): string {
		$attrs = $external ? ' target="_blank" rel="noopener noreferrer"' : '';
		return '<a href="' . esc_url( $url ) . '"' . $attrs . '>' . esc_html( $text ) . '</a>';
	}

	/**
	 * Sets the orderby clause. $empty is a SQL condition that is true for rows
	 * without a value; those rows always sort last.
	 *
	 * @param array<string,string> $clauses
	 * @return array<string,string>
	 */
	protected function order_by( array $clauses, string $expr, string $order, ?string $empty = null ): array {
		$order              = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';
		$clauses['orderby'] = ( null !== $empty ? "{$empty} ASC, " : '' ) . "{$expr} {$order}";
		return $clauses;
	}

	protected static function blank( string $expr ): string {
		return "({$expr} IS NULL OR {$expr} = '')";
	}

	protected static function zero( string $expr ): string {
		return "({$expr} IS NULL OR {$expr} = 0)";
	}

	/**
	 * Correlated sub-select for one meta value, so sorting needs no join or GROUP BY.
	 */
	protected function meta_subselect( string $table, string $id_column, string $outer_id, string $key ): string {
		global $wpdb;
		return $wpdb->prepare( "(SELECT MAX(cm.meta_value) FROM {$table} cm WHERE cm.{$id_column} = {$outer_id} AND cm.meta_key = %s)", $key ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	protected function date_settings(): array {
		return array(
			array(
				'name'    => 'format',
				'type'    => 'select',
				'label'   => __( 'Format', 'corinthian-admin-column-manager' ),
				'default' => 'wp',
				'options' => array(
					'wp'       => __( 'WordPress date format', 'corinthian-admin-column-manager' ),
					'datetime' => __( 'Date and time', 'corinthian-admin-column-manager' ),
					'relative' => __( 'Relative (x ago)', 'corinthian-admin-column-manager' ),
					'custom'   => __( 'Custom PHP format', 'corinthian-admin-column-manager' ),
				),
			),
			array(
				'name'    => 'custom_format',
				'type'    => 'text',
				'label'   => __( 'Custom format', 'corinthian-admin-column-manager' ),
				'default' => 'Y-m-d',
			),
		);
	}

	protected function format_timestamp( int $timestamp, Config $config, ?DateTimeZone $zone = null ): string {
		$date_format = (string) get_option( 'date_format' );
		switch ( $config->setting( 'format', 'wp' ) ) {
			case 'datetime':
				return esc_html( wp_date( $date_format . ' ' . get_option( 'time_format' ), $timestamp, $zone ) );
			case 'relative':
				$diff = human_time_diff( $timestamp, time() );
				/* translators: %s: time span such as "3 hours". */
				$text = $timestamp > time() ? __( 'in %s', 'corinthian-admin-column-manager' ) : __( '%s ago', 'corinthian-admin-column-manager' );
				return esc_html( sprintf( $text, $diff ) );
			case 'custom':
				$custom = (string) $config->setting( 'custom_format', 'Y-m-d' );
				return esc_html( wp_date( '' === $custom ? 'Y-m-d' : $custom, $timestamp, $zone ) );
			default:
				return esc_html( wp_date( $date_format, $timestamp, $zone ) );
		}
	}
}
