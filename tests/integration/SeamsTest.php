<?php

namespace Corinthian\Tests;

use Corinthian\Admin\EditorPage;
use Corinthian\Admin\TableUi;
use Corinthian\Plugin;
use Corinthian\Rest\SetsController;
use Corinthian\Screens\PostTypeScreen;
use WP_UnitTestCase;

class SeamsTest extends WP_UnitTestCase {

	private Plugin $plugin;

	public function set_up(): void {
		parent::set_up();
		$GLOBALS['wp_scripts'] = null;
		$this->plugin = Plugin::instance();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	private function screen(): PostTypeScreen {
		return new PostTypeScreen( $this->plugin->registry, $this->plugin->sets, get_post_type_object( 'post' ) );
	}

	private function make_set(): void {
		$this->plugin->sets->save(
			array(
				'screen'  => 'post:post',
				'title'   => 'Seams',
				'columns' => array(
					array(
						'id'       => 'title',
						'type'     => 'native',
						'label'    => '',
						'settings' => array( 'key' => 'title' ),
					),
				),
			)
		);
	}

	public function test_query_sql_filter_is_applied_and_validated(): void {
		$seen = null;
		$cb   = static function ( $sql, $screen ) use ( &$seen ) {
			$seen = $screen;
			return array(
				'join'  => ' JOIN x',
				'where' => ' AND 1=1',
			);
		};
		add_filter( 'corinthian_query_sql', $cb, 20, 2 );
		$screen = $this->screen();
		$this->assertSame(
			array(
				'join'  => ' JOIN x',
				'where' => ' AND 1=1',
			),
			$screen->filter_sql()
		);
		$this->assertSame( $screen, $seen );
		remove_filter( 'corinthian_query_sql', $cb, 20 );

		$bad = static fn() => array( 'join' => 1, 'where' => '' );
		add_filter( 'corinthian_query_sql', $bad, 20 );
		$this->assertSame(
			array(
				'join'  => '',
				'where' => '',
			),
			$this->screen()->filter_sql()
		);
		remove_filter( 'corinthian_query_sql', $bad, 20 );
	}

	public function test_booted_action_fired_during_boot(): void {
		$this->assertGreaterThan( 0, did_action( 'corinthian_booted' ) );
	}

	public function test_type_description_filter(): void {
		$cb = static function ( $desc, $type, $screen ) {
			$desc['seam'] = $type->key() . '@' . $screen->key();
			return $desc;
		};
		add_filter( 'corinthian_type_description', $cb, 20, 3 );
		$out   = ( new SetsController( $this->plugin ) )->describe_screen( $this->screen() );
		$types = $out['types'];
		$this->assertNotEmpty( $types );
		foreach ( $types as $desc ) {
			$this->assertSame( $desc['key'] . '@post:post', $desc['seam'] );
		}
		remove_filter( 'corinthian_type_description', $cb, 20 );
	}

	public function test_table_payload_filter_and_enqueue_action(): void {
		$this->make_set();
		$screen = $this->screen();
		$fired  = array();
		$filter = static function ( $data, $s, $set ) {
			$data['seam'] = $set->id;
			return $data;
		};
		$action = static function ( $s, $set ) use ( &$fired ) {
			$fired[] = $set->id;
		};
		add_filter( 'corinthian_table_payload', $filter, 20, 3 );
		add_action( 'corinthian_table_enqueue', $action, 10, 2 );

		( new TableUi( $this->plugin ) )->enqueue( $screen );

		$set = $screen->active_set();
		$this->assertNotNull( $set );
		$this->assertSame( array( $set->id ), $fired );
		$script = implode( '', (array) wp_scripts()->get_data( 'corinthian-table', 'before' ) );
		$this->assertStringContainsString( '"seam":' . $set->id, $script );
		$this->assertStringContainsString( '"resize":true', $script );
		remove_filter( 'corinthian_table_payload', $filter, 20 );
		remove_action( 'corinthian_table_enqueue', $action, 10 );
	}

	public function test_resize_off_still_loads_script(): void {
		$set = $this->plugin->sets->save(
			array(
				'screen'   => 'post:post',
				'title'    => 'No resize',
				'settings' => array( 'resize_columns' => false ),
				'columns'  => array(
					array(
						'id'       => 'title',
						'type'     => 'native',
						'label'    => '',
						'settings' => array( 'key' => 'title' ),
					),
				),
			)
		);
		( new TableUi( $this->plugin ) )->enqueue( $this->screen() );
		$this->assertTrue( wp_script_is( 'corinthian-table', 'enqueued' ) );
		$this->assertStringContainsString( '"resize":false', implode( '', (array) wp_scripts()->get_data( 'corinthian-table', 'before' ) ) );
		$this->assertGreaterThan( 0, $set->id );
	}

	public function test_switcher_action_fires(): void {
		$this->make_set();
		$screen = $this->screen();
		$args   = array();
		$cb     = static function ( $s, $active ) use ( &$args ) {
			$args = array( $s, $active );
			echo '<i class="seam-button"></i>';
		};
		add_action( 'corinthian_switcher_actions', $cb, 10, 2 );
		ob_start();
		( new TableUi( $this->plugin ) )->print_switcher( $screen );
		$html = (string) ob_get_clean();
		remove_action( 'corinthian_switcher_actions', $cb, 10 );

		$this->assertSame( $screen, $args[0] );
		$this->assertSame( $screen->active_set()?->id, $args[1]?->id );
		$this->assertStringContainsString( 'seam-button', $html );
	}

	public function test_editor_payload_filter_and_enqueue_action(): void {
		$page = new EditorPage();
		$prop = new \ReflectionProperty( $page, 'hook' );
		$prop->setValue( $page, 'tools_page_corinthian' );
		$fired  = 0;
		$filter = static function ( $data ) {
			$data['seam'] = true;
			return $data;
		};
		$action = static function () use ( &$fired ) {
			++$fired;
		};
		add_filter( 'corinthian_editor_payload', $filter );
		add_action( 'corinthian_editor_enqueue', $action );
		$page->enqueue( 'tools_page_corinthian' );

		$this->assertSame( 1, $fired );
		$this->assertStringContainsString( '"seam":true', implode( '', (array) wp_scripts()->get_data( 'corinthian-editor', 'before' ) ) );
		remove_filter( 'corinthian_editor_payload', $filter );
		remove_action( 'corinthian_editor_enqueue', $action );
	}
}
