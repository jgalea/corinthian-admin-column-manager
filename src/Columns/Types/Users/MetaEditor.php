<?php

namespace Corinthian\Columns\Types\Users;

use WP_Error;

/**
 * Validation and storage for editing one meta key on a user, comment or term.
 * Every write checks the meta capability for the exact change.
 */
final class MetaEditor {

	/**
	 * @return array<string,mixed>
	 */
	public static function field( string $display ): array {
		return array(
			'input' => match ( $display ) {
				'number'   => 'number',
				'date'     => 'date',
				'datetime' => 'datetime',
				'boolean'  => 'toggle',
				'image'    => 'media',
				default    => 'text',
			},
			'bulk'  => array( 'set', 'clear' ),
		);
	}

	public static function value( string $type, int $id, string $key ): ?string {
		if ( '' === $key ) {
			return null;
		}
		$values = get_metadata( $type, $id, $key, false );
		return is_array( $values ) && 1 === count( $values ) && is_scalar( $values[0] ) ? (string) $values[0] : null;
	}

	public static function save( string $type, int $id, string $key, mixed $value, string $mode, string $display ): bool|WP_Error {
		if ( '' === $key ) {
			return new WP_Error( 'corinthian_no_key', __( 'This column has no field set.', 'corinthian-admin-column-manager' ) );
		}
		if ( ! in_array( $mode, array( 'set', 'clear' ), true ) ) {
			return new WP_Error( 'corinthian_bad_mode', __( 'This column does not support that edit.', 'corinthian-admin-column-manager' ) );
		}
		if ( self::is_reserved( $type, $key ) ) {
			return new WP_Error( 'corinthian_protected', __( 'You cannot change this field.', 'corinthian-admin-column-manager' ) );
		}
		$cap = 'clear' === $mode ? 'delete' : ( metadata_exists( $type, $id, $key ) ? 'edit' : 'add' );
		if ( ! current_user_can( "{$cap}_{$type}_meta", $id, $key ) ) {
			return new WP_Error( 'corinthian_protected', __( 'You cannot change this field.', 'corinthian-admin-column-manager' ) );
		}
		$existing = get_metadata( $type, $id, $key, false );
		if ( is_array( $existing ) && ( count( $existing ) > 1 || ( $existing && ! is_scalar( $existing[0] ) ) ) ) {
			return new WP_Error( 'corinthian_complex', __( 'This field holds structured data and cannot be edited here.', 'corinthian-admin-column-manager' ) );
		}
		if ( 'clear' === $mode ) {
			delete_metadata( $type, $id, $key );
			return true;
		}
		if ( ! is_scalar( $value ) && null !== $value ) {
			return new WP_Error( 'corinthian_invalid', __( 'Invalid value.', 'corinthian-admin-column-manager' ) );
		}
		$value = is_bool( $value ) ? ( $value ? '1' : '0' ) : (string) $value;
		switch ( $display ) {
			case 'number':
				if ( '' !== $value && ! is_numeric( $value ) ) {
					return new WP_Error( 'corinthian_invalid', __( 'Enter a number.', 'corinthian-admin-column-manager' ) );
				}
				break;
			case 'date':
				if ( '' !== $value && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
					return new WP_Error( 'corinthian_invalid', __( 'Enter a date as YYYY-MM-DD.', 'corinthian-admin-column-manager' ) );
				}
				break;
			case 'datetime':
				if ( '' !== $value && ! preg_match( '/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $value ) ) {
					return new WP_Error( 'corinthian_invalid', __( 'Enter a date and time as YYYY-MM-DD HH:MM.', 'corinthian-admin-column-manager' ) );
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
			delete_metadata( $type, $id, $key );
			return true;
		}
		update_metadata( $type, $id, $key, wp_slash( $value ) );
		return true;
	}

	/**
	 * User meta that holds capabilities, levels and other per-site core state is never editable.
	 */
	private static function is_reserved( string $type, string $key ): bool {
		if ( is_protected_meta( $key, $type ) ) {
			return true;
		}
		if ( 'user' !== $type ) {
			return false;
		}
		global $wpdb;
		foreach ( array( $wpdb->base_prefix, $wpdb->prefix, $wpdb->get_blog_prefix() ) as $prefix ) {
			if ( '' !== $prefix && str_starts_with( $key, $prefix ) ) {
				return true;
			}
		}
		return false;
	}
}
