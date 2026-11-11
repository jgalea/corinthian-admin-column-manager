<?php

namespace Corinthian\Columns\Types;

use Corinthian\Columns\AbstractType;
use Corinthian\Columns\Config;
use Corinthian\Columns\Editable;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Sortable;
use Corinthian\Screens\Screen;

defined( 'ABSPATH' ) || exit;

/**
 * Any post meta key, shown as text, number, date, image, URL or yes/no.
 */
final class PostMetaType extends AbstractType implements Sortable, Filterable, Editable {

	public function key(): string {
		return 'post.meta';
	}

	public function label(): string {
		return __( 'Custom field', 'corinthian-admin-column-manager' );
	}

	public function group(): string {
		return __( 'Custom fields', 'corinthian-admin-column-manager' );
	}

	public function supports( Screen $screen ): bool {
		return 'post' === $screen->object_type();
	}

	public function settings_schema( Screen $screen ): array {
		return array(
			array(
				'name'    => 'meta_key',
				'type'    => 'meta_key',
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
					'image'   => __( 'Image', 'corinthian-admin-column-manager' ),
					'url'     => __( 'Link', 'corinthian-admin-column-manager' ),
					'boolean' => __( 'Yes / No', 'corinthian-admin-column-manager' ),
				),
			),
		);
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		$key = (string) $config->setting( 'meta_key', '' );
		if ( '' === $key ) {
			return null;
		}
		$values = get_post_meta( $object_id, $key, false );
		if ( ! $values ) {
			return null;
		}
		return 1 === count( $values ) ? $values[0] : $values;
	}

	public function render( int $object_id, Config $config, Screen $screen ): string {
		$value = $this->value( $object_id, $config, $screen );
		if ( null === $value || '' === $value ) {
			return parent::render( $object_id, $config, $screen );
		}
		if ( is_array( $value ) || is_object( $value ) ) {
			$flat = array_filter( (array) $value, 'is_scalar' );
			return $flat ? esc_html( implode( ', ', array_map( 'strval', $flat ) ) ) : '<code>' . esc_html__( '(complex value)', 'corinthian-admin-column-manager' ) . '</code>';
		}
		$value = (string) $value;

		switch ( $config->setting( 'display', 'text' ) ) {
			case 'number':
				return is_numeric( $value ) ? esc_html( number_format_i18n( (float) $value, (int) ( fmod( (float) $value, 1 ) ? 2 : 0 ) ) ) : esc_html( $value );
			case 'date':
				$ts = is_numeric( $value ) ? (int) $value : strtotime( $value );
				return $ts ? esc_html( wp_date( (string) get_option( 'date_format' ), $ts ) ) : esc_html( $value );
			case 'image':
				$img = is_numeric( $value ) ? wp_get_attachment_image( (int) $value, array( 60, 60 ) ) : '';
				if ( $img ) {
					return $img;
				}
				return wp_http_validate_url( $value ) ? '<img src="' . esc_url( $value ) . '" alt="" style="max-width:60px;max-height:60px">' : esc_html( $value );
			case 'url':
				return wp_http_validate_url( $value ) ? '<a href="' . esc_url( $value ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $value ) . '</a>' : esc_html( $value );
			case 'boolean':
				$on = ! in_array( strtolower( $value ), array( '', '0', 'false', 'no', 'off' ), true );
				return $on ? '<span class="dashicons dashicons-yes" aria-label="' . esc_attr__( 'Yes', 'corinthian-admin-column-manager' ) . '"></span>' : '<span class="dashicons dashicons-no-alt" aria-label="' . esc_attr__( 'No', 'corinthian-admin-column-manager' ) . '"></span>';
			default:
				return esc_html( wp_trim_words( $value, 30 ) );
		}
	}

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		global $wpdb;
		$key   = (string) $config->setting( 'meta_key', '' );
		$alias = $this->alias( $config );
		if ( '' === $key ) {
			return $clauses;
		}

		$clauses['join']   .= $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} AS {$alias} ON ({$alias}.post_id = {$wpdb->posts}.ID AND {$alias}.meta_key = %s)", $key ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$clauses['groupby'] = "{$wpdb->posts}.ID";

		$expr = match ( $config->setting( 'display', 'text' ) ) {
			'number'  => "MAX(CAST({$alias}.meta_value AS DECIMAL(30,6)))",
			'date'    => "MAX({$alias}.meta_value)",
			default   => "MAX({$alias}.meta_value)",
		};
		// Empty values always sort last.
		$clauses['orderby'] = "(MAX({$alias}.meta_value) IS NULL OR MAX({$alias}.meta_value) = '') ASC, {$expr} {$order}";
		return $clauses;
	}

	public function filter_operators( Config $config, Screen $screen ): array {
		$display = $config->setting( 'display', 'text' );
		$empty   = array(
			array(
				'op'    => 'empty',
				'label' => __( 'is empty', 'corinthian-admin-column-manager' ),
				'input' => 'none',
			),
			array(
				'op'    => 'not_empty',
				'label' => __( 'is not empty', 'corinthian-admin-column-manager' ),
				'input' => 'none',
			),
		);
		if ( 'number' === $display ) {
			return array_merge(
				array(
					array(
						'op'    => 'eq',
						'label' => '=',
						'input' => 'number',
					),
					array(
						'op'    => 'neq',
						'label' => '≠',
						'input' => 'number',
					),
					array(
						'op'    => 'gt',
						'label' => '>',
						'input' => 'number',
					),
					array(
						'op'    => 'lt',
						'label' => '<',
						'input' => 'number',
					),
					array(
						'op'    => 'between',
						'label' => __( 'between', 'corinthian-admin-column-manager' ),
						'input' => 'number',
					),
				),
				$empty
			);
		}
		if ( 'date' === $display ) {
			return array_merge(
				array(
					array(
						'op'    => 'before',
						'label' => __( 'before', 'corinthian-admin-column-manager' ),
						'input' => 'date',
					),
					array(
						'op'    => 'after',
						'label' => __( 'after', 'corinthian-admin-column-manager' ),
						'input' => 'date',
					),
					array(
						'op'    => 'within_days',
						'label' => __( 'in the last (days)', 'corinthian-admin-column-manager' ),
						'input' => 'number',
					),
					array(
						'op'    => 'older_than_days',
						'label' => __( 'older than (days)', 'corinthian-admin-column-manager' ),
						'input' => 'number',
					),
				),
				$empty
			);
		}
		if ( 'boolean' === $display ) {
			return array(
				array(
					'op'      => 'eq',
					'label'   => __( 'is', 'corinthian-admin-column-manager' ),
					'input'   => 'select',
					'options' => array(
						'1' => __( 'Yes', 'corinthian-admin-column-manager' ),
						'0' => __( 'No', 'corinthian-admin-column-manager' ),
					),
				),
			);
		}
		return array_merge(
			array(
				array(
					'op'    => 'contains',
					'label' => __( 'contains', 'corinthian-admin-column-manager' ),
					'input' => 'text',
				),
				array(
					'op'    => 'not_contains',
					'label' => __( 'does not contain', 'corinthian-admin-column-manager' ),
					'input' => 'text',
				),
				array(
					'op'    => 'eq',
					'label' => __( 'is', 'corinthian-admin-column-manager' ),
					'input' => 'text',
				),
				array(
					'op'    => 'neq',
					'label' => __( 'is not', 'corinthian-admin-column-manager' ),
					'input' => 'text',
				),
				array(
					'op'    => 'starts',
					'label' => __( 'starts with', 'corinthian-admin-column-manager' ),
					'input' => 'text',
				),
			),
			$empty
		);
	}

	public function filter_condition( Config $config, string $op, mixed $value, Screen $screen, string $alias ): ?array {
		global $wpdb;
		$key = (string) $config->setting( 'meta_key', '' );
		if ( '' === $key ) {
			return null;
		}
		// One meta row per post for the comparison; posts without the key get NULL.
		$join = $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} AS {$alias} ON ({$alias}.post_id = {$wpdb->posts}.ID AND {$alias}.meta_key = %s)", $key ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$col  = "{$alias}.meta_value";
		$num  = "CAST({$col} AS DECIMAL(30,6))";
		$v    = is_array( $value ) ? $value : (string) $value;

		// Negative ops on a LEFT JOIN must also match posts without the key.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Every branch of the match builds its fragment with $wpdb->prepare() or a fixed literal; only the generated join alias and column expression are interpolated.
		$where = match ( $op ) {
			'empty'           => "({$col} IS NULL OR {$col} = '')",
			'not_empty'       => "({$col} IS NOT NULL AND {$col} <> '')",
			'contains'        => is_string( $v ) && '' !== $v ? $wpdb->prepare( "{$col} LIKE %s", '%' . $wpdb->esc_like( $v ) . '%' ) : null, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'not_contains'    => is_string( $v ) && '' !== $v ? $wpdb->prepare( "({$col} IS NULL OR {$col} NOT LIKE %s)", '%' . $wpdb->esc_like( $v ) . '%' ) : null, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'starts'          => is_string( $v ) && '' !== $v ? $wpdb->prepare( "{$col} LIKE %s", $wpdb->esc_like( $v ) . '%' ) : null, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'eq'              => is_string( $v ) ? ( 'number' === $config->setting( 'display' ) && is_numeric( $v ) ? $wpdb->prepare( "{$num} = %f", (float) $v ) : ( 'boolean' === $config->setting( 'display' ) ? ( '1' === $v ? "({$col} IS NOT NULL AND {$col} NOT IN ('', '0', 'false', 'no', 'off'))" : "({$col} IS NULL OR {$col} IN ('', '0', 'false', 'no', 'off'))" ) : $wpdb->prepare( "{$col} = %s", $v ) ) ) : null, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'neq'             => is_string( $v ) ? ( 'number' === $config->setting( 'display' ) && is_numeric( $v ) ? $wpdb->prepare( "({$col} IS NULL OR {$num} <> %f)", (float) $v ) : $wpdb->prepare( "({$col} IS NULL OR {$col} <> %s)", $v ) ) : null, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'gt'              => is_string( $v ) && is_numeric( $v ) ? $wpdb->prepare( "{$num} > %f", (float) $v ) : null, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'lt'              => is_string( $v ) && is_numeric( $v ) ? $wpdb->prepare( "{$num} < %f", (float) $v ) : null, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'between'         => is_array( $v ) && 2 === count( $v ) && is_numeric( $v[0] ) && is_numeric( $v[1] ) ? $wpdb->prepare( "{$num} BETWEEN %f AND %f", (float) min( $v ), (float) max( $v ) ) : null, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'before'          => is_string( $v ) && strtotime( $v ) ? $wpdb->prepare( "({$col} <> '' AND {$col} < %s)", gmdate( 'Y-m-d', (int) strtotime( $v ) ) ) : null, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'after'           => is_string( $v ) && strtotime( $v ) ? $wpdb->prepare( "{$col} > %s", gmdate( 'Y-m-d', (int) strtotime( $v ) ) ) : null, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'within_days'     => is_string( $v ) && ctype_digit( $v ) ? $wpdb->prepare( "{$col} >= %s", gmdate( 'Y-m-d', time() - (int) $v * DAY_IN_SECONDS ) ) : null, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'older_than_days' => is_string( $v ) && ctype_digit( $v ) ? $wpdb->prepare( "({$col} <> '' AND {$col} < %s)", gmdate( 'Y-m-d', time() - (int) $v * DAY_IN_SECONDS ) ) : null, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			default           => null,
		};
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return null === $where ? null : array(
			'join'  => $join,
			'where' => $where,
		);
	}

	public function edit_field( Config $config, Screen $screen ): array {
		$input = match ( $config->setting( 'display', 'text' ) ) {
			'number'  => 'number',
			'date'    => 'date',
			'boolean' => 'toggle',
			'image'   => 'media',
			default   => 'text',
		};
		return array(
			'input' => $input,
			'bulk'  => array( 'set', 'clear' ),
		);
	}

	public function edit_value( int $object_id, Config $config, Screen $screen ): mixed {
		$value = $this->value( $object_id, $config, $screen );
		return is_scalar( $value ) ? (string) $value : null;
	}

	public function save( int $object_id, Config $config, Screen $screen, mixed $value, string $mode = 'set' ): bool|\WP_Error {
		$key = (string) $config->setting( 'meta_key', '' );
		if ( '' === $key ) {
			return new \WP_Error( 'corinthian_no_key', __( 'This column has no field set.', 'corinthian-admin-column-manager' ) );
		}
		$cap = 'clear' === $mode ? 'delete_post_meta' : ( metadata_exists( 'post', $object_id, $key ) ? 'edit_post_meta' : 'add_post_meta' );
		if ( ! current_user_can( $cap, $object_id, $key ) ) {
			return new \WP_Error( 'corinthian_protected', __( 'You cannot change this field.', 'corinthian-admin-column-manager' ) );
		}
		$existing = get_post_meta( $object_id, $key, false );
		if ( count( $existing ) > 1 || ( $existing && ! is_scalar( $existing[0] ) ) ) {
			return new \WP_Error( 'corinthian_complex', __( 'This field holds structured data and cannot be edited here.', 'corinthian-admin-column-manager' ) );
		}
		if ( 'clear' === $mode ) {
			delete_post_meta( $object_id, $key );
			return true;
		}
		if ( ! is_scalar( $value ) && null !== $value ) {
			return new \WP_Error( 'corinthian_invalid', __( 'Invalid value.', 'corinthian-admin-column-manager' ) );
		}
		$value = (string) $value;
		switch ( $config->setting( 'display', 'text' ) ) {
			case 'number':
				if ( '' !== $value && ! is_numeric( $value ) ) {
					return new \WP_Error( 'corinthian_invalid', __( 'Enter a number.', 'corinthian-admin-column-manager' ) );
				}
				break;
			case 'date':
				if ( '' !== $value && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
					return new \WP_Error( 'corinthian_invalid', __( 'Enter a date as YYYY-MM-DD.', 'corinthian-admin-column-manager' ) );
				}
				break;
			case 'boolean':
				$value = in_array( strtolower( $value ), array( '1', 'true', 'yes', 'on' ), true ) ? '1' : '0';
				break;
			case 'image':
				$value = (string) absint( $value );
				break;
			default:
				$value = sanitize_textarea_field( $value );
		}
		if ( '' === $value ) {
			delete_post_meta( $object_id, $key );
			return true;
		}
		update_post_meta( $object_id, $key, wp_slash( $value ) );
		return true;
	}
}
