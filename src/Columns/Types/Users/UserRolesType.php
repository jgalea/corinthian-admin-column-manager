<?php

namespace Corinthian\Columns\Types\Users;

use Corinthian\Columns\Config;
use Corinthian\Columns\Editable;
use Corinthian\Columns\Filterable;
use Corinthian\Screens\Screen;

final class UserRolesType extends AbstractUserType implements Filterable, Editable {

	use EditsUser;

	public function __construct() {
		parent::__construct( 'roles' );
	}

	public function label(): string {
		return __( 'Role(s)', 'corinthian-admin-column-manager' );
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		$user = get_userdata( $object_id );
		if ( ! $user ) {
			return null;
		}
		$names = wp_roles()->role_names;
		$out   = array();
		foreach ( $user->roles as $role ) {
			$out[] = isset( $names[ $role ] ) ? translate_user_role( $names[ $role ] ) : $role;
		}
		return $out;
	}

	public function filter_operators( Config $config, Screen $screen ): array {
		$options = array();
		foreach ( wp_roles()->get_names() as $slug => $name ) {
			$options[ $slug ] = translate_user_role( $name );
		}
		return FilterSql::operators( FilterSql::ENUM, $options );
	}

	public function filter_condition( Config $config, string $op, mixed $value, Screen $screen, string $alias ): ?array {
		global $wpdb;
		if ( ! in_array( $op, array( 'in', 'not_in' ), true ) ) {
			return null;
		}
		$alias = preg_replace( '/[^A-Za-z0-9_]/', '', $alias );
		$known = wp_roles()->get_names();
		$likes = array();
		foreach ( is_array( $value ) ? $value : array( $value ) as $role ) {
			if ( is_string( $role ) && isset( $known[ $role ] ) ) {
				// Roles are stored serialized as role => true in the capabilities meta.
				$likes[] = "{$alias}.meta_value LIKE " . $wpdb->prepare( '%s', '%' . $wpdb->esc_like( '"' . $role . '";b:1' ) . '%' );
			}
		}
		if ( ! $likes ) {
			return null;
		}
		$sub =  $wpdb->prepare( "SELECT 1 FROM {$wpdb->usermeta} {$alias} WHERE {$alias}.user_id = {$wpdb->users}.ID AND {$alias}.meta_key = %s", $wpdb->get_blog_prefix() . 'capabilities' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array(
			'join'  => '',
			'where' => ( 'in' === $op ? 'EXISTS (' : 'NOT EXISTS (' ) . $sub . ' AND (' . implode( ' OR ', $likes ) . '))',
		);
	}
}
