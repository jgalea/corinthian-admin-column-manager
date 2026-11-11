<?php

namespace Corinthian\Tests;

use Corinthian\Admin\TableUi;
use Corinthian\Plugin;
use Corinthian\Sets\SetRepository;
use WP_UnitTestCase;

final class UninstallTest extends WP_UnitTestCase {

	public function test_uninstall_removes_options_meta_and_user_data(): void {
		global $wpdb;
		$user = self::factory()->user->create();
		$post = self::factory()->post->create();
		update_option( 'corinthian_db_version', '1', false );
		update_option( 'corinthian_word_count_backfill_done', time(), false );
		update_option( 'corinthian_native_' . md5( 'post:post' ), array( 'title' => 'Title' ), false );
		update_option( 'unrelated_option', 'keep' );
		update_post_meta( $post, '_corinthian_word_count', 12 );
		update_post_meta( $post, 'unrelated_meta', 'keep' );
		update_user_meta( $user, SetRepository::active_meta_key( 'post:post' ), 5 );
		update_user_meta( $user, TableUi::widths_meta_key( 'post:post', 5 ), array( 'a' => 100 ) );
		update_user_meta( $user, 'unrelated_user_meta', 'keep' );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'corinthian-admin-column-manager/corinthian-admin-column-manager.php' );
		}
		$wpdb->suppress_errors( true );
		include dirname( __DIR__, 2 ) . '/uninstall.php';
		$wpdb->suppress_errors( false );

		$this->assertFalse( get_option( 'corinthian_db_version' ) );
		$this->assertFalse( get_option( 'corinthian_word_count_backfill_done' ) );
		$this->assertFalse( get_option( 'corinthian_native_' . md5( 'post:post' ) ) );
		$this->assertSame( 'keep', get_option( 'unrelated_option' ) );
		$this->assertSame( '', get_post_meta( $post, '_corinthian_word_count', true ) );
		$this->assertSame( 'keep', get_post_meta( $post, 'unrelated_meta', true ) );
		$this->assertSame( '', get_user_meta( $user, SetRepository::active_meta_key( 'post:post' ), true ) );
		$this->assertSame( '', get_user_meta( $user, TableUi::widths_meta_key( 'post:post', 5 ), true ) );
		$this->assertSame( 'keep', get_user_meta( $user, 'unrelated_user_meta', true ) );
	}

	public function test_deleting_a_set_removes_its_per_user_data(): void {
		$sets = Plugin::instance()->sets;
		$set  = $sets->save(
			array(
				'screen'  => 'post:post',
				'title'   => 'Temp',
				'columns' => array(),
			)
		);
		$user = self::factory()->user->create();
		update_user_meta( $user, SetRepository::active_meta_key( 'post:post' ), $set->id );
		update_user_meta( $user, TableUi::widths_meta_key( 'post:post', $set->id ), array( 'a' => 100 ) );
		$fired = 0;
		add_action(
			'corinthian_set_deleted',
			static function () use ( &$fired ) {
				++$fired;
			}
		);

		$this->assertTrue( $sets->delete( $set->id ) );

		$this->assertSame( '', get_user_meta( $user, SetRepository::active_meta_key( 'post:post' ), true ) );
		$this->assertSame( '', get_user_meta( $user, TableUi::widths_meta_key( 'post:post', $set->id ), true ) );
		$this->assertSame( 1, $fired );
	}
}
