<?php

namespace Corinthian\Columns\Types\Users;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Validation and storage for editing user columns. Writes go through
 * wp_update_user, and each change checks the capability it needs.
 */
final class UserEditor {

	private const MODES = array(
		'display_name' => array( 'set' ),
		'first_name'   => array( 'set', 'clear' ),
		'last_name'    => array( 'set', 'clear' ),
		'nickname'     => array( 'set' ),
		'website'      => array( 'set', 'clear' ),
		'description'  => array( 'set', 'clear' ),
		'email'        => array( 'set' ),
		'roles'        => array( 'set' ),
		'locale'       => array( 'set', 'clear' ),
		'rich_editing' => array( 'set' ),
		'admin_color'  => array( 'set' ),
	);

	/**
	 * Email must be unique per user, so it has no bulk mode.
	 */
	private const NO_BULK = array( 'email' );

	/**
	 * @return array<string,mixed>
	 */
	public static function field( string $kind ): array {
		$field = array(
			'input' => match ( $kind ) {
				'description'                       => 'textarea',
				'roles', 'locale', 'admin_color'    => 'select',
				'rich_editing'                      => 'toggle',
				default                             => 'text',
			},
		);
		$options = match ( $kind ) {
			'roles'       => self::role_options(),
			'locale'      => self::locale_options(),
			'admin_color' => self::color_options(),
			default       => null,
		};
		if ( null !== $options ) {
			$field['options'] = $options;
		}
		if ( ! in_array( $kind, self::NO_BULK, true ) ) {
			$field['bulk'] = self::MODES[ $kind ] ?? array( 'set' );
		}
		return $field;
	}

	public static function value( string $kind, int $id ): mixed {
		$user = get_userdata( $id );
		if ( ! $user ) {
			return null;
		}
		$color = (string) get_user_meta( $id, 'admin_color', true );
		return match ( $kind ) {
			'display_name' => (string) $user->display_name,
			'email'       => (string) $user->user_email,
			'website'     => (string) $user->user_url,
			'roles'       => (string) ( array_values( $user->roles )[0] ?? '' ),
			'rich_editing' => 'false' !== get_user_meta( $id, 'rich_editing', true ),
			'admin_color' => '' !== $color ? $color : 'fresh',
			default       => (string) get_user_meta( $id, $kind, true ),
		};
	}

	public static function save( string $kind, int $id, mixed $value, string $mode ): bool|WP_Error {
		$user = get_userdata( $id );
		if ( ! $user ) {
			return new WP_Error( 'corinthian_missing', __( 'That user does not exist.', 'corinthian-admin-column-manager' ) );
		}
		if ( ! current_user_can( 'edit_user', $id ) ) {
			return new WP_Error( 'corinthian_forbidden', __( 'You cannot edit this user.', 'corinthian-admin-column-manager' ) );
		}
		if ( ! in_array( $mode, self::MODES[ $kind ] ?? array(), true ) ) {
			return new WP_Error( 'corinthian_bad_mode', __( 'This column does not support that edit.', 'corinthian-admin-column-manager' ) );
		}
		if ( 'clear' === $mode ) {
			$value = '';
		} elseif ( ! is_scalar( $value ) ) {
			return new WP_Error( 'corinthian_invalid', __( 'Invalid value.', 'corinthian-admin-column-manager' ) );
		}
		$text = is_bool( $value ) ? ( $value ? 'true' : 'false' ) : trim( (string) $value );

		$args = match ( $kind ) {
			'display_name', 'nickname' => self::required_text( $kind, $text ),
			'first_name', 'last_name'  => array( $kind => sanitize_text_field( $text ) ),
			'description'              => array( 'description' => sanitize_textarea_field( $text ) ),
			'website'                  => self::website( $text ),
			'email'                    => self::email( $id, $text ),
			'roles'                    => self::role( $user, $text ),
			'locale'                   => self::locale( $text ),
			'rich_editing'             => self::rich_editing( $text ),
			default                    => self::admin_color( $text ),
		};
		if ( $args instanceof WP_Error ) {
			return $args;
		}
		$result = wp_update_user( wp_slash( array_merge( array( 'ID' => $id ), $args ) ) );
		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * @return array<string,string>|WP_Error
	 */
	private static function required_text( string $kind, string $text ): array|WP_Error {
		$text = sanitize_text_field( $text );
		if ( '' === $text ) {
			return new WP_Error( 'corinthian_invalid', __( 'This cannot be empty.', 'corinthian-admin-column-manager' ) );
		}
		if ( strlen( $text ) > 250 ) {
			return new WP_Error( 'corinthian_invalid', __( 'That is too long.', 'corinthian-admin-column-manager' ) );
		}
		return array( $kind => $text );
	}

	/**
	 * @return array<string,string>|WP_Error
	 */
	private static function website( string $text ): array|WP_Error {
		if ( '' === $text ) {
			return array( 'user_url' => '' );
		}
		$url = esc_url_raw( $text, array( 'http', 'https' ) );
		if ( '' === $url || false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'corinthian_invalid', __( 'Enter a valid web address.', 'corinthian-admin-column-manager' ) );
		}
		return array( 'user_url' => $url );
	}

	/**
	 * @return array<string,string>|WP_Error
	 */
	private static function email( int $id, string $text ): array|WP_Error {
		if ( ! current_user_can( 'edit_users' ) && get_current_user_id() !== $id ) {
			return new WP_Error( 'corinthian_forbidden', __( 'You cannot change this email address.', 'corinthian-admin-column-manager' ) );
		}
		$email = sanitize_email( $text );
		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error( 'corinthian_invalid', __( 'Enter a valid email address.', 'corinthian-admin-column-manager' ) );
		}
		$owner = email_exists( $email );
		if ( $owner && (int) $owner !== $id ) {
			return new WP_Error( 'corinthian_duplicate', __( 'That email address is already in use.', 'corinthian-admin-column-manager' ) );
		}
		return array( 'user_email' => $email );
	}

	/**
	 * @return array<string,string>|WP_Error
	 */
	private static function role( \WP_User $user, string $text ): array|WP_Error {
		if ( ! current_user_can( 'promote_user', $user->ID ) ) {
			return new WP_Error( 'corinthian_forbidden', __( 'You cannot change this user\'s role.', 'corinthian-admin-column-manager' ) );
		}
		if ( get_current_user_id() === $user->ID ) {
			return new WP_Error( 'corinthian_own_role', __( 'You cannot change your own role.', 'corinthian-admin-column-manager' ) );
		}
		$role = sanitize_key( $text );
		if ( ! isset( self::editable_roles()[ $role ] ) ) {
			return new WP_Error( 'corinthian_invalid', __( 'Choose a role you are allowed to assign.', 'corinthian-admin-column-manager' ) );
		}
		if ( 'administrator' !== $role && in_array( 'administrator', $user->roles, true ) ) {
			$admins = get_users(
				array(
					'role'   => 'administrator',
					'fields' => 'ID',
					'number' => 2,
				)
			);
			if ( count( $admins ) < 2 ) {
				return new WP_Error( 'corinthian_last_admin', __( 'You cannot remove the last administrator.', 'corinthian-admin-column-manager' ) );
			}
		}
		return array( 'role' => $role );
	}

	/**
	 * @return array<string,string>|WP_Error
	 */
	private static function locale( string $text ): array|WP_Error {
		if ( ! isset( self::locale_options()[ $text ] ) ) {
			return new WP_Error( 'corinthian_invalid', __( 'Choose an installed language.', 'corinthian-admin-column-manager' ) );
		}
		return array( 'locale' => $text );
	}

	/**
	 * @return array<string,string>|WP_Error
	 */
	private static function rich_editing( string $text ): array|WP_Error {
		if ( ! in_array( $text, array( 'true', 'false', '1', '0' ), true ) ) {
			return new WP_Error( 'corinthian_invalid', __( 'Choose yes or no.', 'corinthian-admin-column-manager' ) );
		}
		return array( 'rich_editing' => in_array( $text, array( 'true', '1' ), true ) ? 'true' : 'false' );
	}

	/**
	 * @return array<string,string>|WP_Error
	 */
	private static function admin_color( string $text ): array|WP_Error {
		if ( ! isset( self::color_options()[ $text ] ) ) {
			return new WP_Error( 'corinthian_invalid', __( 'Choose an available color scheme.', 'corinthian-admin-column-manager' ) );
		}
		return array( 'admin_color' => $text );
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private static function editable_roles(): array {
		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		return get_editable_roles();
	}

	/**
	 * @return array<string,string>
	 */
	private static function role_options(): array {
		$options = array();
		foreach ( self::editable_roles() as $slug => $role ) {
			$options[ (string) $slug ] = translate_user_role( (string) $role['name'] );
		}
		return $options;
	}

	/**
	 * @return array<string,string>
	 */
	private static function locale_options(): array {
		$options = array(
			''      => __( 'Site default', 'corinthian-admin-column-manager' ),
			'en_US' => 'English (United States)',
		);
		foreach ( get_available_languages() as $code ) {
			$options[ $code ] = $code;
		}
		return $options;
	}

	/**
	 * @return array<string,string>
	 */
	private static function color_options(): array {
		global $_wp_admin_css_colors;
		if ( empty( $_wp_admin_css_colors ) ) {
			register_admin_color_schemes();
		}
		$options = array();
		foreach ( (array) $_wp_admin_css_colors as $slug => $scheme ) {
			$options[ (string) $slug ] = is_object( $scheme ) && isset( $scheme->name ) ? (string) $scheme->name : (string) $slug;
		}
		return $options;
	}
}
