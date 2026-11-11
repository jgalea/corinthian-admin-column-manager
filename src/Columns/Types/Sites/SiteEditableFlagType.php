<?php

namespace Corinthian\Columns\Types\Sites;

use Corinthian\Columns\Config;
use Corinthian\Columns\Editable;
use Corinthian\Screens\Screen;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The flags a network admin may flip from the list: public and archived.
 */
final class SiteEditableFlagType extends SiteFlagType implements Editable {

	public function edit_field( Config $config, Screen $screen ): array {
		return array(
			'input' => 'toggle',
			'bulk'  => array( 'set' ),
		);
	}

	public function edit_value( int $object_id, Config $config, Screen $screen ): mixed {
		return $this->value( $object_id, $config, $screen );
	}

	public function save( int $object_id, Config $config, Screen $screen, mixed $value, string $mode = 'set' ): bool|WP_Error {
		if ( 'set' !== $mode ) {
			return new WP_Error( 'corinthian_bad_mode', __( 'This column does not support that edit.', 'corinthian-admin-column-manager' ) );
		}
		if ( ! current_user_can( 'manage_sites' ) || ! $this->site( $object_id ) ) {
			return new WP_Error( 'corinthian_forbidden', __( 'You cannot edit this item.', 'corinthian-admin-column-manager' ) );
		}
		$on = is_bool( $value ) ? $value : in_array( is_scalar( $value ) ? strtolower( (string) $value ) : '', array( '1', 'true', 'yes', 'on' ), true );
		if ( false === update_blog_status( $object_id, $this->field, $on ? '1' : '0' ) ) {
			return new WP_Error( 'corinthian_failed', __( 'The site could not be updated.', 'corinthian-admin-column-manager' ) );
		}
		return true;
	}
}
