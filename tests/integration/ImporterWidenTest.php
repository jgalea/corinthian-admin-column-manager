<?php

namespace Corinthian\Tests;

use Corinthian\Import\AdminColumnsImporter;
use WP_UnitTestCase;

final class ImporterWidenTest extends WP_UnitTestCase {

	public function test_a_user_only_view_also_shows_to_the_given_roles(): void {
		$out = AdminColumnsImporter::widen( array( 'roles' => array(), 'users' => array( 5 ) ), array( 'administrator' ) );
		$this->assertSame( array( 'administrator' ), $out['roles'] );
		$this->assertSame( array( 5 ), $out['users'], 'The original user keeps access.' );
	}

	public function test_an_unrestricted_view_stays_unrestricted(): void {
		$out = AdminColumnsImporter::widen( array( 'roles' => array(), 'users' => array() ), array( 'administrator' ) );
		$this->assertSame( array(), $out['roles'], 'Adding a role would narrow a view everyone could see.' );
	}

	public function test_roles_merge_without_duplicates_and_no_roles_means_no_change(): void {
		$out = AdminColumnsImporter::widen( array( 'roles' => array( 'editor', 'administrator' ) ), array( 'administrator' ) );
		$this->assertSame( array( 'editor', 'administrator' ), $out['roles'] );
		$same = array( 'roles' => array(), 'users' => array( 5 ) );
		$this->assertSame( $same, AdminColumnsImporter::widen( $same, array() ) );
	}
}
