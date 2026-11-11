<?php

namespace Corinthian\Columns\Types;

use Corinthian\Columns\Config;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Sortable;
use Corinthian\Screens\Screen;
use WP_Post;

defined( 'ABSPATH' ) || exit;

class DateType extends AbstractPostType implements Sortable, Filterable {

	/**
	 * @param 'date'|'modified' $field
	 */
	public function __construct( private string $field ) {}

	public function key(): string {
		return 'date' === $this->field ? 'post.published_date' : 'post.modified_date';
	}

	public function label(): string {
		return 'date' === $this->field ? __( 'Published date', 'corinthian-admin-column-manager' ) : __( 'Modified date', 'corinthian-admin-column-manager' );
	}

	protected function group_slug(): string {
		return 'dates';
	}

	public function settings_schema( Screen $screen ): array {
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

	protected function post_value( WP_Post $post, Config $config, Screen $screen ): mixed {
		$raw = 'date' === $this->field ? $post->post_date : $post->post_modified;
		return str_starts_with( $raw, '0000' ) ? null : $raw;
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		$timestamp = get_post_timestamp( $object_id, $this->field );
		if ( ! $timestamp ) {
			return '';
		}
		$date_format = (string) get_option( 'date_format' );
		switch ( $config->setting( 'format', 'wp' ) ) {
			case 'datetime':
				return esc_html( wp_date( $date_format . ' ' . get_option( 'time_format' ), $timestamp ) );
			case 'relative':
				$diff = human_time_diff( $timestamp, time() );
				/* translators: %s: time span such as "3 hours". */
				$text = $timestamp > time() ? __( 'in %s', 'corinthian-admin-column-manager' ) : __( '%s ago', 'corinthian-admin-column-manager' );
				return esc_html( sprintf( $text, $diff ) );
			case 'custom':
				$custom = (string) $config->setting( 'custom_format', 'Y-m-d' );
				return esc_html( wp_date( '' === $custom ? 'Y-m-d' : $custom, $timestamp ) );
			default:
				return esc_html( wp_date( $date_format, $timestamp ) );
		}
	}

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		global $wpdb;
		$col = $wpdb->posts . ( 'date' === $this->field ? '.post_date' : '.post_modified' );
		return $this->order_by( $clauses, $col, $order );
	}

	public function filter_operators( Config $config, Screen $screen ): array {
		return PostFilter::date_ops();
	}

	public function filter_condition( Config $config, string $op, mixed $value, Screen $screen, string $alias ): ?array {
		global $wpdb;
		return PostFilter::wrap( PostFilter::date_where( $wpdb->posts . ( 'date' === $this->field ? '.post_date' : '.post_modified' ), $op, $value ) );
	}
}
