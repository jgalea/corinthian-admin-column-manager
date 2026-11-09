<?php

namespace Corinthian\Columns\Types\Sites;

use Corinthian\Columns\Config;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Sortable;
use Corinthian\Columns\Types\Users\FilterSql;
use Corinthian\Screens\Screen;

/**
 * Any key in the site meta table (wp_blogmeta).
 */
final class SiteMetaType extends AbstractSiteType implements Sortable, Filterable {

	public function __construct() {
		parent::__construct( 'meta' );
	}

	public function label(): string {
		return __( 'Site meta', 'corinthian-admin-column-manager' );
	}

	public function group(): string {
		return __( 'Custom fields', 'corinthian-admin-column-manager' );
	}

	public function settings_schema( Screen $screen ): array {
		return array(
			array(
				'name'    => 'meta_key',
				'type'    => 'text',
				'label'   => __( 'Field', 'corinthian-admin-column-manager' ),
				'default' => '',
			),
			array(
				'name'    => 'display',
				'type'    => 'select',
				'label'   => __( 'Show as', 'corinthian-admin-column-manager' ),
				'default' => 'text',
				'options' => array(
					'text'    => __( 'Text', 'corinthian-admin-column-manager' ),
					'number'  => __( 'Number', 'corinthian-admin-column-manager' ),
					'date'    => __( 'Date', 'corinthian-admin-column-manager' ),
					'boolean' => __( 'Yes / No', 'corinthian-admin-column-manager' ),
				),
			),
		);
	}

	private function key_of( Config $config ): string {
		return (string) $config->setting( 'meta_key', '' );
	}

	private function display( Config $config ): string {
		return (string) $config->setting( 'display', 'text' );
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		$key = $this->key_of( $config );
		if ( '' === $key || ! $this->site( $object_id ) ) {
			return null;
		}
		$values = get_site_meta( $object_id, $key, false );
		if ( ! is_array( $values ) || ! $values ) {
			return null;
		}
		return 1 === count( $values ) ? $values[0] : $values;
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			$flat = array_filter( (array) $value, 'is_scalar' );
			return $flat ? esc_html( implode( ', ', array_map( 'strval', $flat ) ) ) : '<code>' . esc_html__( '(complex value)', 'corinthian-admin-column-manager' ) . '</code>';
		}
		$text = is_scalar( $value ) ? (string) $value : '';
		switch ( $this->display( $config ) ) {
			case 'boolean':
				$on = ! in_array( strtolower( $text ), array( '', '0', 'false', 'no', 'off' ), true );
				return $on ? '<span class="dashicons dashicons-yes" aria-label="' . esc_attr__( 'Yes', 'corinthian-admin-column-manager' ) . '"></span>' : '<span class="dashicons dashicons-no-alt" aria-label="' . esc_attr__( 'No', 'corinthian-admin-column-manager' ) . '"></span>';
			case 'date':
				$timestamp = is_numeric( $text ) ? (int) $text : strtotime( $text );
				return $timestamp ? esc_html( wp_date( $this->date_format(), $timestamp ) ) : esc_html( $text );
			default:
				return esc_html( wp_trim_words( $text, 30 ) );
		}
	}

	private function date_format(): string {
		return (string) get_option( 'date_format' );
	}

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		global $wpdb;
		$key = $this->key_of( $config );
		if ( '' === $key ) {
			return $clauses;
		}
		$sub  = $this->meta_subselect( $wpdb->blogmeta, 'blog_id', "{$wpdb->blogs}.blog_id", $key );
		$sort = 'number' === $this->display( $config ) ? "CAST({$sub} AS DECIMAL(30,6))" : $sub;
		return $this->order_by( $clauses, $sort, $order, self::blank( $sub ) );
	}

	public function filter_operators( Config $config, Screen $screen ): array {
		return FilterSql::meta_operators( $this->display( $config ) );
	}

	public function filter_condition( Config $config, string $op, mixed $value, Screen $screen, string $alias ): ?array {
		global $wpdb;
		$key = $this->key_of( $config );
		if ( '' === $key ) {
			return null;
		}
		$where = FilterSql::meta(
			FilterSql::meta_kind( $this->display( $config ) ),
			$op,
			$value,
			array(
				'table'     => $wpdb->blogmeta,
				'id_column' => 'blog_id',
				'outer_id'  => "{$wpdb->blogs}.blog_id",
				'key'       => $key,
			),
			$alias
		);
		return null === $where ? null : array(
			'join'  => '',
			'where' => $where,
		);
	}
}
