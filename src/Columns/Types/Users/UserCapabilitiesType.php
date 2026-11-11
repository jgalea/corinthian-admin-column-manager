<?php

namespace Corinthian\Columns\Types\Users;

use Corinthian\Columns\Config;
use Corinthian\Screens\Screen;

defined( 'ABSPATH' ) || exit;

final class UserCapabilitiesType extends AbstractUserType {

	public function __construct() {
		parent::__construct( 'capabilities' );
	}

	public function label(): string {
		return __( 'Capabilities summary', 'corinthian-admin-column-manager' );
	}

	/**
	 * @return list<string> Granted capabilities, excluding the role names WordPress stores alongside them.
	 */
	private function granted( int $user_id ): array {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array();
		}
		$caps = array_diff_key( array_filter( $user->allcaps ), array_flip( $user->roles ) );
		return array_map( 'strval', array_keys( $caps ) );
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		$count = count( $this->granted( $object_id ) );
		return $count ? $count : null;
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		$caps = $this->granted( $object_id );
		sort( $caps );
		/* translators: %s: number of capabilities. */
		$text = sprintf( _n( '%s capability', '%s capabilities', count( $caps ), 'corinthian-admin-column-manager' ), number_format_i18n( count( $caps ) ) );
		return '<span title="' . esc_attr( implode( ', ', array_slice( $caps, 0, 40 ) ) ) . '">' . esc_html( $text ) . '</span>';
	}
}
