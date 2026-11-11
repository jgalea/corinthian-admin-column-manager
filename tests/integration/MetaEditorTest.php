<?php

namespace Corinthian\Tests;

use Corinthian\Columns\Types\Users\MetaEditor;
use WP_UnitTestCase;

final class MetaEditorTest extends WP_UnitTestCase {

	/**
	 * @dataProvider core_user_state
	 */
	public function test_core_user_state_is_not_editable( string $key ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$user = self::factory()->user->create();

		$result = MetaEditor::save( 'user', $user, $key, 'x', 'set', 'text' );

		$this->assertWPError( $result );
		$this->assertSame( 'corinthian_protected', $result->get_error_code() );
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function core_user_state(): array {
		return array(
			'session tokens'   => array( 'session_tokens' ),
			'capabilities in capitals' => array( 'WP_Capabilities' ),
			'session tokens in mixed case' => array( 'Session_Tokens' ),
			'user level in capitals' => array( 'WP_User_Level' ),
			'padded key' => array( ' wp_capabilities ' ),
			'trailing space capitals' => array( 'WP_CAPABILITIES ' ),
			'slashed' => array( 'wp_capa\\bilities' ),
			'slashed underscores' => array( 'wp\\_user\\_level' ),
			'fullwidth lookalike' => array( 'ｗp_capabilities' ),
			'accented lookalike' => array( 'wp_capabilitiés' ),
			'password nag'     => array( 'default_password_nag' ),
			'use ssl'          => array( 'use_ssl' ),
			'admin bar'        => array( 'show_admin_bar_front' ),
		);
	}

	public function test_a_plain_meta_key_is_still_editable(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$user = self::factory()->user->create();

		$this->assertTrue( MetaEditor::save( 'user', $user, 'favourite_colour', 'green', 'set', 'text' ) );
		$this->assertSame( 'green', get_user_meta( $user, 'favourite_colour', true ) );
	}
}
