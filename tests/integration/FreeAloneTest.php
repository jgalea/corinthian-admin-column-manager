<?php

namespace Corinthian\Tests;

use Corinthian\Plugin;
use Corinthian\Rest\SetsController;
use Corinthian\Screens\CommentScreen;
use Corinthian\Screens\MediaScreen;
use Corinthian\Screens\NetworkUserScreen;
use Corinthian\Screens\PostTypeScreen;
use Corinthian\Screens\Screen;
use Corinthian\Screens\ScreenRegistry;
use Corinthian\Screens\TermScreen;
use Corinthian\Screens\UserScreen;
use WP_REST_Request;
use WP_UnitTestCase;

class FreeAloneTest extends WP_UnitTestCase {

	private Plugin $plugin;

	public function set_up(): void {
		parent::set_up();
		$this->plugin = Plugin::instance();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * @return array<string,Screen>
	 */
	private function fresh_screens(): array {
		return ( new ScreenRegistry( $this->plugin->registry, $this->plugin->sets ) )->all();
	}

	private function type_key_for( Screen $screen ): string {
		return match ( true ) {
			$screen instanceof MediaScreen => 'media.file_name',
			$screen instanceof UserScreen, $screen instanceof NetworkUserScreen => 'user.id',
			$screen instanceof CommentScreen => 'comment.id',
			$screen instanceof TermScreen => 'term.id',
			default => 'post.id',
		};
	}

	private function seed_object( Screen $screen ): int {
		if ( $screen instanceof PostTypeScreen ) {
			return (int) self::factory()->post->create( array( 'post_type' => $screen->subtype() ) );
		}
		if ( $screen instanceof MediaScreen ) {
			return (int) self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );
		}
		if ( $screen instanceof UserScreen ) {
			return (int) self::factory()->user->create();
		}
		if ( $screen instanceof CommentScreen ) {
			return (int) self::factory()->comment->create( array( 'comment_post_ID' => self::factory()->post->create() ) );
		}
		if ( $screen instanceof TermScreen ) {
			return (int) self::factory()->term->create( array( 'taxonomy' => $screen->subtype() ) );
		}
		$this->fail( 'Unhandled screen ' . $screen->key() );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function proish_set( string $screen_key ): array {
		return array(
			'screen'   => $screen_key,
			'title'    => 'Free alone',
			'settings' => array(
				'segments'   => array(
					array(
						'id'      => 's1',
						'label'   => 'Drafts',
						'filters' => array( 'status' => 'draft' ),
					),
				),
				'formatting' => array(
					array(
						'column' => 'c2',
						'when'   => 'gt',
						'value'  => 5,
						'color'  => '#f00',
					),
				),
			),
			'columns'  => array(
				array(
					'id'       => 'c1',
					'type'     => 'post.id',
					'label'    => 'ID',
					'settings' => array(),
				),
				array(
					'id'       => 'c2',
					'type'     => 'vendor.unknown_type',
					'label'    => 'Mystery',
					'width'    => 120,
					'settings' => array(
						'filterable' => false,
						'editable'   => true,
						'custom'     => array( 'a' => 1 ),
					),
				),
			),
		);
	}

	public function test_no_pro_classes_are_loaded(): void {
		$found = array_filter(
			array_merge( get_declared_classes(), get_declared_interfaces(), get_declared_traits() ),
			static fn( string $name ) => str_starts_with( $name, 'CorinthianPro\\' )
		);
		$this->assertSame( array(), array_values( $found ) );
	}

	public function test_every_screen_has_columns_and_renders_a_cell(): void {
		$screens = $this->fresh_screens();
		$this->assertNotEmpty( $screens );
		$checked = 0;
		foreach ( $screens as $key => $screen ) {
			$type_key = $this->type_key_for( $screen );
			$this->assertNotNull( $this->plugin->registry->get( $type_key ), "$key: $type_key registered" );
			$this->assertArrayHasKey( $type_key, $this->plugin->registry->for_screen( $screen ), "$key has columns" );

			$this->plugin->sets->save(
				array(
					'screen'  => $key,
					'title'   => 'Render',
					'columns' => array(
						array(
							'id'       => 'c1',
							'type'     => $type_key,
							'label'    => 'Col',
							'settings' => array(),
						),
					),
				)
			);
			$id = $this->seed_object( $screen );
			$this->assertNotSame( '', $screen->cell( 'corinthian-c1', $id ), "$key renders a cell" );
			++$checked;
		}
		$this->assertSame( count( $screens ), $checked );
		$this->assertGreaterThanOrEqual( 6, $checked );
	}

	public function test_resave_through_rest_keeps_unknown_data(): void {
		$set = $this->plugin->sets->save( $this->proish_set( 'post:post' ) );
		$this->assertCount( 2, $set->columns );

		$request = new WP_REST_Request( 'PUT', '/corinthian/v1/sets/' . $set->id );
		$request->set_url_params( array( 'id' => $set->id ) );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( array( 'title' => 'Renamed' ) ) );
		$response = ( new SetsController( $this->plugin ) )->update_set( $request );
		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$after = $this->plugin->sets->find( $set->id );
		$this->assertNotNull( $after );
		$this->assertSame( 'Renamed', $after->title );
		$this->assertSame( $set->settings['segments'], $after->settings['segments'] );
		$this->assertSame( $set->settings['formatting'], $after->settings['formatting'] );
		$this->assertCount( 2, $after->columns );
		$this->assertSame( 'vendor.unknown_type', $after->columns[1]->type );
		$this->assertFalse( $after->columns[1]->setting( 'filterable' ) );
		$this->assertTrue( $after->columns[1]->setting( 'editable' ) );
		$this->assertSame( array( 'a' => 1 ), $after->columns[1]->setting( 'custom' ) );
		$this->assertSame( $set->columns[1]->to_array(), $after->columns[1]->to_array() );
	}

	public function test_resave_with_full_payload_keeps_unknown_data(): void {
		$set     = $this->plugin->sets->save( $this->proish_set( 'post:post' ) );
		$request = new WP_REST_Request( 'PUT', '/corinthian/v1/sets/' . $set->id );
		$request->set_url_params( array( 'id' => $set->id ) );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $set->to_array() ) );
		( new SetsController( $this->plugin ) )->update_set( $request );

		$after = $this->plugin->sets->find( $set->id );
		$this->assertNotNull( $after );
		$this->assertSame( $set->settings, $after->settings );
		$this->assertSame( $set->columns[1]->to_array(), $after->columns[1]->to_array() );
	}

	public function test_sorting_by_unknown_column_does_nothing(): void {
		$this->plugin->sets->save( $this->proish_set( 'post:post' ) );
		$screen = $this->fresh_screens()['post:post'];
		$this->assertNull( $screen->sort_target( 'corinthian-c2' ) );
		$this->assertArrayNotHasKey( 'corinthian-c2', $screen->filter_sortable( array() ) );
	}
}
