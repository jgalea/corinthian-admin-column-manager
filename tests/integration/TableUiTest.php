<?php

namespace Corinthian\Tests;

use Corinthian\Admin\TableUi;
use Corinthian\Plugin;
use WP_UnitTestCase;

final class TableUiTest extends WP_UnitTestCase {

	private Plugin $plugin;

	public function set_up(): void {
		parent::set_up();
		$this->plugin = Plugin::instance();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_GET = array();
		$_REQUEST = array();
	}

	public function tear_down(): void {
		$_GET = array();
		$_REQUEST = array();
		parent::tear_down();
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	private function make_set( array $settings ): void {
		$this->plugin->sets->save(
			array(
				'screen'   => 'post:post',
				'title'    => 'Table',
				'columns'  => array(
					array(
						'id'   => 'wc',
						'type' => 'post.word_count',
					),
					array(
						'id'   => 'id',
						'type' => 'post.id',
					),
				),
				'settings' => $settings,
			)
		);
		// The shared screen object remembers the set it resolved first.
		( new \ReflectionProperty( \Corinthian\Screens\Screen::class, 'active' ) )->setValue( $this->plugin->screens->get( 'post:post' ), null );
	}

	private function styles( bool $fit ): string {
		$this->make_set( array( 'fit_width' => $fit ) );
		$screen = $this->plugin->screens->get( 'post:post' );
		ob_start();
		( new TableUi( $this->plugin ) )->print_styles( $screen );
		return (string) ob_get_clean();
	}

	public function test_sets_scroll_sideways_by_default(): void {
		$scrolling = $this->styles( false );
		$this->assertStringContainsString( 'min-width:', $scrolling );
		$this->assertStringContainsString( 'corinthian-scroll', $scrolling );
	}

	public function test_fit_width_set_has_no_scroll_wrapper(): void {
		$fitted = $this->styles( true );
		$this->assertStringNotContainsString( 'min-width:', $fitted );
		$this->assertStringNotContainsString( 'corinthian-scroll', $fitted );
	}

	public function test_sticky_header_uses_the_admin_bar_height(): void {
		$this->make_set(
			array(
				'fit_width'     => true,
				'sticky_header' => true,
			)
		);
		ob_start();
		( new TableUi( $this->plugin ) )->print_styles( $this->plugin->screens->get( 'post:post' ) );
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'var(--wp-admin--admin-bar--height,32px)', $html );
		$this->assertStringNotContainsString( 'top:32px', $html );
	}

	public function test_sticky_header_on_a_scrolling_set_sticks_to_the_page(): void {
		$this->make_set( array( 'sticky_header' => true ) );
		ob_start();
		( new TableUi( $this->plugin ) )->print_styles( $this->plugin->screens->get( 'post:post' ) );
		$html = (string) ob_get_clean();
		$this->assertStringNotContainsString( 'max-block-size', $html );
		$this->assertStringContainsString( '.corinthian-scroll{overflow:visible}', $html );
		$this->assertStringContainsString( 'inset-block-start:var(--wp-admin--admin-bar--height,32px)', $html );
	}

	public function test_set_defaults_rows_per_page_and_sort(): void {
		$this->make_set(
			array(
				'per_page'        => 75,
				'default_orderby' => 'corinthian-wc',
				'default_order'   => 'desc',
			)
		);
		set_current_screen( 'edit-post' );
		( new TableUi( $this->plugin ) )->on_screen( get_current_screen() );

		$this->assertSame( 75, apply_filters( 'edit_post_per_page', 20 ) );
		$this->assertSame( 'corinthian-wc', $_GET['orderby'] );
		$this->assertSame( 'desc', $_GET['order'] );
	}

	public function test_a_requested_sort_and_the_users_own_per_page_win(): void {
		$this->make_set(
			array(
				'per_page'        => 75,
				'default_orderby' => 'corinthian-wc',
			)
		);
		update_user_meta( get_current_user_id(), 'edit_post_per_page', 30 );
		$_GET['orderby'] = 'title';
		set_current_screen( 'edit-post' );
		( new TableUi( $this->plugin ) )->on_screen( get_current_screen() );

		$this->assertSame( 30, apply_filters( 'edit_post_per_page', 30 ) );
		$this->assertSame( 'title', $_GET['orderby'] );
		$this->assertArrayNotHasKey( 'order', $_GET );
	}
}
