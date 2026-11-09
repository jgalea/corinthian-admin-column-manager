<?php

namespace Corinthian\Tests;

use Corinthian\Import\AdminColumnsImporter;
use Corinthian\Plugin;
use Corinthian\Screens\Screen;
use WP_UnitTestCase;

final class ImporterStaleColumnsTest extends WP_UnitTestCase {

	private Plugin $plugin;

	public function set_up(): void {
		parent::set_up();
		$this->plugin = Plugin::instance();
		foreach ( $this->plugin->sets->for_screen( 'post:page' ) as $set ) {
			$this->plugin->sets->delete( $set->id );
		}
	}

	private function remember( string $screen, array $columns ): void {
		update_option( Screen::NATIVE_OPTION_PREFIX . md5( $screen ), $columns, false );
	}

	private function view( array $types ): void {
		$columns = array();
		foreach ( $types as $type ) {
			$columns[ $type ] = array(
				'type'  => $type,
				'label' => '',
			);
		}
		update_option( 'cpac_options_page', $columns );
	}

	private function page_entry( array $report ): array {
		foreach ( $report as $entry ) {
			if ( 'option:cpac_options_page' === $entry['source'] ) {
				return $entry;
			}
		}
		$this->fail( 'The page view was not reported.' );
	}

	public function tear_down(): void {
		delete_option( 'cpac_options_page' );
		delete_option( Screen::NATIVE_OPTION_PREFIX . md5( 'post:page' ) );
		parent::tear_down();
	}

	public function test_columns_the_table_no_longer_has_are_skipped(): void {
		$this->remember(
			'post:page',
			array(
				'title' => 'Title',
				'date'  => 'Date',
			)
		);
		$this->view( array( 'title', 'ratings', 'date' ) );

		$entry = $this->page_entry( ( new AdminColumnsImporter( $this->plugin ) )->run() );

		$this->assertSame( 'created', $entry['status'] );
		$this->assertSame( array( 'title', 'date' ), $entry['imported'] );
		$this->assertSame( array( 'ratings' ), $entry['skipped'] );
	}

	public function test_a_view_with_nothing_left_creates_no_set(): void {
		$this->remember(
			'post:page',
			array(
				'title' => 'Title',
				'date'  => 'Date',
			)
		);
		$this->view( array( 'ratings', 'cp_notes' ) );

		$entry = $this->page_entry( ( new AdminColumnsImporter( $this->plugin ) )->run() );

		$this->assertSame( 'no columns left', $entry['status'] );
		$this->assertSame( array(), $this->plugin->sets->for_screen( 'post:page' ) );
	}

	public function test_native_columns_are_kept_when_the_table_was_never_opened(): void {
		$this->view( array( 'title', 'ratings' ) );

		$entry = $this->page_entry( ( new AdminColumnsImporter( $this->plugin ) )->run( true ) );

		$this->assertSame( array( 'title', 'ratings' ), $entry['imported'], 'With no record of the table, nothing can be judged missing.' );
	}

	public function test_the_main_column_comes_back_when_a_set_lost_it(): void {
		$this->plugin->sets->save(
			array(
				'screen'  => 'post:page',
				'title'   => 'Lost title',
				'columns' => array(
					array(
						'id'       => 'date',
						'type'     => 'native',
						'label'    => '',
						'settings' => array( 'key' => 'date' ),
					),
				),
			)
		);
		$screen = $this->plugin->screens->get( 'post:page' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$out = $screen->filter_columns(
			array(
				'cb'    => '<input>',
				'title' => 'Title',
				'date'  => 'Date',
			)
		);

		$this->assertSame( array( 'cb', 'title', 'date' ), array_keys( $out ) );
	}
}
