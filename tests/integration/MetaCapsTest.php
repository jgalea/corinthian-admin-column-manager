<?php

namespace Corinthian\Tests;

use Corinthian\Columns\MetaCaps;
use WP_UnitTestCase;

/**
 * A site running ACF next to Meta Box or Pods: ACF claims any meta key named
 * like one of its fields and denies the generic meta caps on it.
 */
final class MetaCapsTest extends WP_UnitTestCase {

	private bool $own_guard = false;

	public function set_up(): void {
		parent::set_up();
		if ( ! function_exists( 'acf_map_post_meta_caps' ) ) {
			// Stand-in with ACF's shape when ACF isn't loaded in this run: deny the key "shared".
			eval( 'function acf_map_post_meta_caps( $caps, $cap, ...$ctx ) { $args = isset( $ctx[1] ) ? (array) $ctx[1] : array(); if ( in_array( $cap, array( "add_post_meta", "edit_post_meta", "delete_post_meta" ), true ) && "shared" === ( $args[1] ?? "" ) ) { $caps[] = "do_not_allow"; } return $caps; }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
		}
		if ( false === has_filter( 'map_meta_cap', 'acf_map_post_meta_caps' ) ) {
			add_filter( 'map_meta_cap', 'acf_map_post_meta_caps', 10, 4 );
			$this->own_guard = true;
		}
	}

	public function tear_down(): void {
		if ( $this->own_guard ) {
			remove_filter( 'map_meta_cap', 'acf_map_post_meta_caps', 10 );
		}
		parent::tear_down();
	}

	public function test_field_plugin_writes_pass_acfs_name_guard_and_the_guard_is_restored(): void {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		$post   = self::factory()->post->create();
		update_post_meta( $post, '_shared', 'field_1234567890abc' ); // ACF's reference, in case real ACF is loaded.
		wp_set_current_user( $editor );

		$this->assertFalse( current_user_can( 'edit_post_meta', $post, 'shared' ), 'Control: the guard denies the plain check.' );
		$this->assertTrue( MetaCaps::can( 'edit_post_meta', $post, 'shared' ) );
		$this->assertNotFalse( has_filter( 'map_meta_cap', 'acf_map_post_meta_caps' ), 'The guard is back after the check.' );
	}

	public function test_lifting_the_guard_never_grants_what_the_post_cap_denies(): void {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		$post   = self::factory()->post->create( array( 'post_author' => self::factory()->user->create( array( 'role' => 'editor' ) ) ) );
		wp_set_current_user( $author );
		$this->assertFalse( MetaCaps::can( 'edit_post_meta', $post, 'shared' ), "An author still can't edit someone else's post meta." );
	}
}
