<?php

namespace Corinthian\Columns\Types\Users;

use Corinthian\Columns\Config;
use Corinthian\Screens\Screen;

final class UserAvatarType extends AbstractUserType {

	public function __construct() {
		parent::__construct( 'avatar' );
	}

	public function label(): string {
		return __( 'Avatar', 'corinthian-admin-column-manager' );
	}

	public function settings_schema( Screen $screen ): array {
		return array(
			array(
				'name'    => 'size',
				'type'    => 'number',
				'label'   => __( 'Size in pixels', 'corinthian-admin-column-manager' ),
				'default' => 32,
			),
		);
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		$url = get_avatar_url( $object_id );
		return $url ? $url : null;
	}

	public function render( int $object_id, Config $config, Screen $screen ): string {
		if ( ! $this->value( $object_id, $config, $screen ) ) {
			return $this->dash();
		}
		$size   = max( 16, min( 256, (int) $config->setting( 'size', 32 ) ) );
		$avatar = get_avatar( $object_id, $size );
		return $avatar ? $avatar : $this->dash();
	}
}
