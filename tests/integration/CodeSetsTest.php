<?php

namespace Corinthian\Tests;

use Corinthian\Plugin;
use Corinthian\Sets\CodeSets;
use WP_UnitTestCase;

final class CodeSetsTest extends WP_UnitTestCase {

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
}
