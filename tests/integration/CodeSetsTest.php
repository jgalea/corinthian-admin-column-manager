<?php

namespace Corinthian\Tests;

use Corinthian\Plugin;
use Corinthian\Sets\CodeSets;
use WP_UnitTestCase;

final class CodeSetsTest extends WP_UnitTestCase {

	public function tear_down(): void {
		( new \ReflectionProperty( CodeSets::class, 'sets' ) )->setValue( null, array() );
		parent::tear_down();
	}

	private function set_data( string $title ): array {
		return array(
			'screen'   => 'post:post',
			'title'    => $title,
			'columns'  => array(
				array( 'id' => 'title', 'type' => 'native', 'label' => '', 'settings' => array( 'key' => 'title' ) ),
				array( 'id' => 'prio', 'type' => 'post.meta', 'label' => 'Priority', 'width' => 90, 'settings' => array( 'meta_key' => 'priority', 'display' => 'number' ) ),
			),
			'settings' => array( 'roles' => array( 'editor' ), 'users' => array( 7 ), 'sticky_header' => true ),
		);
	}

	public function test_code_sets_are_listed_and_read_only(): void {
		corinthian_register_set( array( 'key' => 'code_test' ) + $this->set_data( 'From code' ) );
		$repo = Plugin::instance()->sets;
		$id   = CodeSets::id_for( 'code_test' );

		$titles = array_map( static fn( $s ) => $s->title, $repo->for_screen( 'post:post' ) );
		$this->assertContains( 'From code', $titles );
		$found = $repo->find( $id );
		$this->assertNotNull( $found );
		$this->assertTrue( $found->settings['readonly'] );
		$this->assertFalse( $repo->delete( $id ) );

		$this->expectException( \DomainException::class );
		$repo->save( $this->set_data( 'Overwrite' ), $id );
	}

	public function test_rest_refuses_to_change_or_delete_a_code_set(): void {
		corinthian_register_set( array( 'key' => 'code_rest' ) + $this->set_data( 'Rest code' ) );
		$id = CodeSets::id_for( 'code_rest' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$controller = new \Corinthian\Rest\SetsController( Plugin::instance() );

		$update = new \WP_REST_Request( 'PUT', '/corinthian/v1/sets/' . $id );
		$update->set_url_params( array( 'id' => $id ) );
		$update->set_header( 'content-type', 'application/json' );
		$update->set_body( (string) wp_json_encode( array( 'title' => 'Nope' ) ) );
		$response = $controller->update_set( $update );
		$this->assertWPError( $response );
		$this->assertSame( 'corinthian_readonly', $response->get_error_code() );
		$this->assertSame( 403, $response->get_error_data()['status'] );

		$delete = new \WP_REST_Request( 'DELETE', '/corinthian/v1/sets/' . $id );
		$delete->set_url_params( array( 'id' => $id ) );
		$response = $controller->delete_set( $delete );
		$this->assertWPError( $response );
		$this->assertSame( 'corinthian_readonly', $response->get_error_code() );
	}

	private function duplicate( int $id, array $body = array() ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', '/corinthian/v1/sets/' . $id . '/duplicate' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );
		do_action( 'rest_api_init' );
		return rest_get_server()->dispatch( $request );
	}

	public function test_a_set_can_be_duplicated_including_one_defined_in_code(): void {
		corinthian_register_set( array( 'key' => 'code_copy' ) + $this->set_data( 'Original' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$id = CodeSets::id_for( 'code_copy' );

		$response = $this->duplicate( $id );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$copy = $response->get_data();
		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'Original (copy)', $copy['title'] );
		$this->assertFalse( CodeSets::is_code_id( $copy['id'] ) );
		$this->assertCount( 2, $copy['columns'] );
		$this->assertArrayNotHasKey( 'readonly', (array) $copy['settings'] );
		$this->assertArrayNotHasKey( 'code_key', (array) $copy['settings'] );
		$this->assertNotNull( Plugin::instance()->sets->find( $copy['id'] ) );
	}

	public function test_duplicating_to_another_screen_keeps_only_columns_that_fit(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$source = Plugin::instance()->sets->save( $this->set_data( 'Posts set' ) );

		$response = $this->duplicate(
			$source->id,
			array(
				'screen' => 'users',
				'title'  => 'People',
			)
		);

		$copy = $response->get_data();
		$this->assertSame( 'users', $copy['screen'] );
		$this->assertSame( 'People', $copy['title'] );
		$this->assertSame( array(), $copy['columns'], 'native and post-only columns are not carried over' );
	}

	public function test_duplicating_to_another_screen_drops_formatting_and_segments_for_missing_columns(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$data                         = $this->set_data( 'Posts set' );
		$data['settings']['formatting'] = array(
			array( 'id' => 'a', 'column' => 'prio', 'op' => 'gt', 'value' => 1 ),
			array( 'id' => 'b', 'column' => 'id', 'op' => 'gt', 'value' => 1 ),
		);
		$data['settings']['segments']   = array(
			array( 'id' => 's1', 'name' => 'Needs prio', 'filter' => array( 'match' => 'all', 'rules' => array( array( 'column' => 'prio', 'op' => 'gt', 'value' => 1 ) ) ) ),
			array( 'id' => 's2', 'name' => 'Mixed', 'filter' => array( 'match' => 'all', 'rules' => array( array( 'column' => 'id', 'op' => 'gt', 'value' => 1 ), array( 'column' => 'prio', 'op' => 'gt', 'value' => 1 ) ) ) ),
		);
		$data['columns'][]            = array( 'id' => 'id', 'type' => 'user.id', 'label' => '', 'settings' => array() );
		$source                       = Plugin::instance()->sets->save( $data );
		$this->forget_active_sets();

		$copy     = $this->duplicate( $source->id, array( 'screen' => 'users' ) )->get_data();
		$settings = (array) $copy['settings'];

		$this->assertSame( array( 'id' ), array_column( $copy['columns'], 'id' ) );
		$this->assertSame( array( 'b' ), array_column( $settings['formatting'], 'id' ) );
		$this->assertSame( array(), $settings['segments'] );
		$this->forget_active_sets();
	}

	private function forget_active_sets(): void {
		foreach ( array( 'post:post', 'users' ) as $key ) {
			( new \ReflectionProperty( \Corinthian\Screens\Screen::class, 'active' ) )->setValue( Plugin::instance()->screens->get( $key ), null );
		}
	}
}
