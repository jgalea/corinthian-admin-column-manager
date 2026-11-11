<?php

namespace Corinthian\Tests;

use Corinthian\Columns\Config;
use Corinthian\Columns\Registry;
use Corinthian\Columns\Sortable;
use Corinthian\Columns\Types\ScreenTypeLoader;
use Corinthian\Columns\Types\Sites\SiteTypeLoader;
use Corinthian\Plugin;
use Corinthian\Screens\NetworkSiteScreen;
use Corinthian\Screens\NetworkUserScreen;
use Corinthian\Screens\UserScreen;
use Corinthian\Sets\ColumnSet;
use Corinthian\Sets\SetRepository;
use WP_UnitTestCase;
use WP_User_Query;

/**
 * Network admin screens. Run with phpunit-multisite.xml.dist.
 *
 * @group MultisiteTest
 * @group ms-required
 */
class MultisiteTest extends WP_UnitTestCase {

	private Registry $registry;
	private SetRepository $sets;
	private NetworkSiteScreen $sites_screen;
	private NetworkUserScreen $users_screen;
	private int $super;
	private int $plain_admin;

	/** @var array<string,int> */
	private array $ids = array();

	/** @var list<int> */
	private array $all = array();

	public function set_up(): void {
		parent::set_up();
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Needs multisite: run phpunit-multisite.xml.dist.' );
		}
		$this->registry     = new Registry();
		ScreenTypeLoader::register( $this->registry );
		SiteTypeLoader::register( $this->registry );
		$this->sets         = new SetRepository();
		$this->sites_screen = new NetworkSiteScreen( $this->registry, $this->sets );
		$this->users_screen = new NetworkUserScreen( $this->registry, $this->sets );

		$this->super = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $this->super );
		$this->plain_admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->super );

		$rows = array(
			'alpha' => array( 'a.example.org', '2020-01-10 10:00:00', '2023-03-01 10:00:00', 'Alpha Site' ),
			'beta'  => array( 'b.example.org', '2021-02-10 10:00:00', '2022-02-01 10:00:00', 'Beta Site' ),
			'gamma' => array( 'c.example.org', '2022-03-10 10:00:00', '2021-01-05 10:00:00', 'Gamma Site' ),
			'delta' => array( 'd.example.org', '2023-04-10 10:00:00', '2020-06-01 10:00:00', 'Delta Site' ),
		);
		global $wpdb;
		foreach ( $rows as $slug => $row ) {
			$id = self::factory()->blog->create(
				array(
					'domain'  => get_network()->domain,
					'path'    => '/s-' . $slug . '/',
					'title'   => $row[3],
					'user_id' => $this->super,
				)
			);
			$this->ids[ $slug ] = $id;
		}
		$this->all = array_values( $this->ids );

		foreach ( $this->ids as $id ) {
			update_blog_status( $id, 'public', 1 );
		}
		update_blog_status( $this->ids['beta'], 'public', 0 );
		update_blog_status( $this->ids['beta'], 'archived', 1 );
		update_blog_status( $this->ids['gamma'], 'spam', 1 );
		update_blog_status( $this->ids['gamma'], 'mature', 1 );
		update_blog_status( $this->ids['delta'], 'deleted', 1 );

		update_site_meta( $this->ids['alpha'], 'rank', '30' );
		update_site_meta( $this->ids['beta'], 'rank', '10' );
		update_site_meta( $this->ids['gamma'], 'rank', '20' );
		add_filter(
			'get_available_languages',
			static function ( $languages ) {
				return array_merge( (array) $languages, array( 'fr_FR' ) );
			}
		);
		update_blog_option( $this->ids['beta'], 'WPLANG', 'fr_FR' );
		update_blog_option( $this->ids['alpha'], 'admin_email', 'alpha-owner@example.org' );

		switch_to_blog( $this->ids['gamma'] );
		self::factory()->post->create_many( 2, array( 'post_status' => 'publish' ) );
		restore_current_blog();

		// Last, because every status or option change above bumps last_updated.
		foreach ( $rows as $slug => $row ) {
			$wpdb->update(
				$wpdb->blogs,
				array(
					'domain'       => $row[0],
					'registered'   => $row[1],
					'last_updated' => $row[2],
				),
				array( 'blog_id' => $this->ids[ $slug ] )
			);
			clean_blog_cache( $this->ids[ $slug ] );
		}
	}

	private function config( string $key, array $settings = array() ): Config {
		return new Config( 'c', $key, 'Label', null, 'px', $settings );
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	private function column_set( string $screen, string $key, array $settings = array() ): ColumnSet {
		return $this->sets->save(
			array(
				'screen'  => $screen,
				'title'   => 'Test ' . $key,
				'columns' => array(
					array(
						'id'       => 'c',
						'type'     => $key,
						'label'    => 'Label',
						'settings' => $settings,
					),
				),
			)
		);
	}

	/**
	 * @param list<string> $slugs
	 * @return list<int>
	 */
	private function ids_of( array $slugs ): array {
		return array_map( fn( $s ) => $this->ids[ $s ], $slugs );
	}

	private function sorted( ColumnSet $set, string $order ): array {
		$screen = new NetworkSiteScreen( $this->registry, $this->sets );
		$screen->register();
		set_current_screen( 'sites-network' );
		$this->assertSame( $set->id, $screen->active_set()?->id );

		$args = apply_filters(
			'ms_sites_list_table_query_args',
			array(
				'number'     => 50,
				'network_id' => get_current_network_id(),
				'orderby'    => 'corinthian-c',
				'order'      => $order,
				'fields'     => 'ids',
				'site__in'   => $this->all,
			)
		);
		$this->assertTrue( $args[ \Corinthian\Screens\SiteQuery::UNCACHED ] ?? false, 'the list table query is marked' );
		$ids = get_sites( $args );
		remove_filter( 'ms_sites_list_table_query_args', array( $screen, 'query_args' ) );
		remove_filter( 'sites_clauses', array( $screen, 'clauses' ), 10 );
		return $ids;
	}

	public function test_registry_lists_network_screens_with_their_ids(): void {
		$plugin = Plugin::instance();
		$sites  = $plugin->screens->get( 'network-sites' );
		$users  = $plugin->screens->get( 'network-users' );
		$this->assertInstanceOf( NetworkSiteScreen::class, $sites );
		$this->assertInstanceOf( NetworkUserScreen::class, $users );
		$this->assertSame( 'sites-network', $sites->screen_id() );
		$this->assertSame( 'users-network', $users->screen_id() );
		$this->assertSame( 'site', $sites->object_type() );
		$this->assertSame( 'user', $users->object_type() );
		$this->assertSame( $users, $plugin->screens->for_screen_id( 'users-network' ) );
		$this->assertSame( 'users', $plugin->screens->get( 'users' )?->screen_id() );
	}

	public function test_site_types_apply_to_the_sites_screen_only(): void {
		$site_types = array_keys( $this->registry->for_screen( $this->sites_screen ) );
		foreach ( array( 'site.id', 'site.domain', 'site.path', 'site.name', 'site.registered', 'site.last_updated', 'site.public', 'site.archived', 'site.spam', 'site.deleted', 'site.mature', 'site.language', 'site.post_count', 'site.admin_email', 'site.meta' ) as $key ) {
			$this->assertContains( $key, $site_types );
		}
		$this->assertNotContains( 'user.email', $site_types );
		$user_types = array_keys( $this->registry->for_screen( $this->users_screen ) );
		$this->assertContains( 'user.email', $user_types );
		$this->assertNotContains( 'site.id', $user_types );
	}

	/**
	 * @return array<string,array{0:string,1:array<string,mixed>,2:string}>
	 */
	public static function render_cases(): array {
		return array(
			'id'            => array( 'site.id', array(), '{alpha}' ),
			'domain'        => array( 'site.domain', array(), 'a.example.org' ),
			'path'          => array( 'site.path', array(), '/s-alpha/' ),
			'name'          => array( 'site.name', array(), 'Alpha Site' ),
			'registered'    => array( 'site.registered', array( 'format' => 'custom', 'custom_format' => 'Y-m-d' ), '2020-01-10' ),
			'last_updated'  => array( 'site.last_updated', array( 'format' => 'custom', 'custom_format' => 'Y-m-d' ), '2023-03-01' ),
			'public'        => array( 'site.public', array(), 'dashicons-yes' ),
			'archived'      => array( 'site.archived', array(), 'dashicons-no-alt' ),
			'spam'          => array( 'site.spam', array(), 'dashicons-no-alt' ),
			'deleted'       => array( 'site.deleted', array(), 'dashicons-no-alt' ),
			'mature'        => array( 'site.mature', array(), 'dashicons-no-alt' ),
			'language'      => array( 'site.language', array(), 'en_US' ),
			'admin_email'   => array( 'site.admin_email', array(), 'alpha-owner@example.org' ),
			'post_count'    => array( 'site.post_count', array(), '1' ),
			'meta'          => array( 'site.meta', array( 'meta_key' => 'rank', 'display' => 'number' ), '30' ),
		);
	}

	/**
	 * @dataProvider render_cases
	 * @param array<string,mixed> $settings
	 */
	public function test_each_site_type_renders( string $key, array $settings, string $expected ): void {
		$type = $this->registry->get( $key );
		$this->assertNotNull( $type, $key );
		$expected = str_replace( '{alpha}', (string) $this->ids['alpha'], $expected );
		$html     = $type->render( $this->ids['alpha'], $this->config( $key, $settings ), $this->sites_screen );
		$this->assertStringContainsString( $expected, $html );
	}

	public function test_render_other_sites_and_empty_values(): void {
		$beta = $this->ids['beta'];
		$this->assertStringContainsString( 'fr_FR', $this->registry->get( 'site.language' )->render( $beta, $this->config( 'site.language' ), $this->sites_screen ) );
		$this->assertStringContainsString( 'dashicons-yes', $this->registry->get( 'site.archived' )->render( $beta, $this->config( 'site.archived' ), $this->sites_screen ) );
		$this->assertStringContainsString( 'dashicons-no-alt', $this->registry->get( 'site.public' )->render( $beta, $this->config( 'site.public' ), $this->sites_screen ) );
		$this->assertStringContainsString( '3', $this->registry->get( 'site.post_count' )->render( $this->ids['gamma'], $this->config( 'site.post_count', array( 'post_type' => 'post' ) ), $this->sites_screen ) );
		$this->assertSame( 3, $this->registry->get( 'site.post_count' )->value( $this->ids['gamma'], $this->config( 'site.post_count' ), $this->sites_screen ) );
		$this->assertStringContainsString( '&#8212;', $this->registry->get( 'site.meta' )->render( $this->ids['delta'], $this->config( 'site.meta', array( 'meta_key' => 'rank' ) ), $this->sites_screen ) );
		$this->assertStringContainsString( '&#8212;', $this->registry->get( 'site.id' )->render( 99999999, $this->config( 'site.id' ), $this->sites_screen ) );
	}

	public function test_post_count_is_cached_and_capped(): void {
		$type   = $this->registry->get( 'site.post_count' );
		$config = $this->config( 'site.post_count' );
		$this->assertSame( 3, $type->value( $this->ids['gamma'], $config, $this->sites_screen ) );
		switch_to_blog( $this->ids['gamma'] );
		self::factory()->post->create( array( 'post_status' => 'publish' ) );
		restore_current_blog();
		wp_cache_delete( $this->ids['gamma'] . ':post', 'posts' );
		$this->assertSame( 3, $type->value( $this->ids['gamma'], $config, $this->sites_screen ), 'served from the five minute cache' );

		$this->assertSame( 200, \Corinthian\Columns\Types\Sites\SitePostCountType::LOOKUP_CAP );
		$fresh = new \Corinthian\Columns\Types\Sites\SitePostCountType();
		wp_cache_flush();
		$looked = 0;
		foreach ( range( 1, 205 ) as $n ) {
			if ( null !== $fresh->value( $this->ids['alpha'], $this->config( 'site.post_count', array( 'post_type' => 'ptype' . $n ) ), $this->sites_screen ) ) {
				++$looked;
			}
		}
		$this->assertSame( 200, $looked );
	}

	/**
	 * @return array<string,array{0:string,1:array<string,mixed>,2:list<string>,3:list<string>}>
	 */
	public static function sort_cases(): array {
		return array(
			'id'           => array( 'site.id', array(), array( 'alpha', 'beta', 'gamma', 'delta' ), array( 'delta', 'gamma', 'beta', 'alpha' ) ),
			'domain'       => array( 'site.domain', array(), array( 'alpha', 'beta', 'gamma', 'delta' ), array( 'delta', 'gamma', 'beta', 'alpha' ) ),
			'path'         => array( 'site.path', array(), array( 'alpha', 'beta', 'delta', 'gamma' ), array( 'gamma', 'delta', 'beta', 'alpha' ) ),
			'registered'   => array( 'site.registered', array(), array( 'alpha', 'beta', 'gamma', 'delta' ), array( 'delta', 'gamma', 'beta', 'alpha' ) ),
			'last_updated' => array( 'site.last_updated', array(), array( 'delta', 'gamma', 'beta', 'alpha' ), array( 'alpha', 'beta', 'gamma', 'delta' ) ),
			'meta number'  => array( 'site.meta', array( 'meta_key' => 'rank', 'display' => 'number' ), array( 'beta', 'gamma', 'alpha', 'delta' ), array( 'alpha', 'gamma', 'beta', 'delta' ) ),
		);
	}

	/**
	 * @dataProvider sort_cases
	 * @param array<string,mixed> $settings
	 * @param list<string>        $asc
	 * @param list<string>        $desc
	 */
	public function test_each_sortable_type_orders_the_list_in_sql( string $key, array $settings, array $asc, array $desc ): void {
		$this->assertInstanceOf( Sortable::class, $this->registry->get( $key ) );
		$set = $this->column_set( 'network-sites', $key, $settings );
		$this->assertSame( $this->ids_of( $asc ), $this->sorted( $set, 'ASC' ), "$key ASC" );
		$this->assertSame( $this->ids_of( $desc ), $this->sorted( $set, 'DESC' ), "$key DESC" );
	}

	public function test_flags_and_name_are_not_offered_as_sortable(): void {
		foreach ( array( 'site.public', 'site.archived', 'site.spam', 'site.deleted', 'site.mature', 'site.name', 'site.language', 'site.admin_email', 'site.post_count' ) as $key ) {
			$this->assertNotInstanceOf( Sortable::class, $this->registry->get( $key ), $key );
		}
		$set = $this->column_set( 'network-sites', 'site.domain' );
		$this->assertSame( $set->id, $this->sites_screen->active_set()?->id );
		$sortable = $this->sites_screen->filter_sortable( array( 'blogname' => array( 'blogname', false ) ) );
		$this->assertArrayHasKey( 'corinthian-c', $sortable );
	}

	public function test_custom_site_column_prints_through_the_core_action(): void {
		$set = $this->column_set( 'network-sites', 'site.domain' );
		$this->sites_screen->register();
		set_current_screen( 'sites-network' );
		$this->assertSame( $set->id, $this->sites_screen->active_set()?->id );

		$columns = $this->sites_screen->filter_columns( array( 'cb' => '<input>', 'blogname' => 'URL', 'users' => 'Users' ) );
		$this->assertSame( array( 'cb', 'blogname', 'corinthian-c' ), array_keys( $columns ) );

		ob_start();
		do_action( 'manage_sites_custom_column', 'corinthian-c', $this->ids['alpha'] );
		$out = (string) ob_get_clean();
		$this->assertStringContainsString( 'a.example.org', $out );

		ob_start();
		do_action( 'manage_sites_custom_column', 'other', $this->ids['alpha'] );
		$this->assertSame( '', (string) ob_get_clean() );
	}

	public function test_core_hook_names_exist_where_the_screens_expect_them(): void {
		$sites = (string) file_get_contents( ABSPATH . 'wp-admin/includes/class-wp-ms-sites-list-table.php' );
		$users = (string) file_get_contents( ABSPATH . 'wp-admin/includes/class-wp-ms-users-list-table.php' );
		$query = (string) file_get_contents( ABSPATH . WPINC . '/class-wp-site-query.php' );
		$this->assertStringContainsString( "apply_filters( 'wpmu_blogs_columns'", $sites );
		$this->assertStringContainsString( "do_action( 'manage_sites_custom_column'", $sites );
		$this->assertStringContainsString( "apply_filters( 'ms_sites_list_table_query_args'", $sites );
		$this->assertStringContainsString( "'sites_clauses'", $query );
		$this->assertStringContainsString( "apply_filters( 'wpmu_users_columns'", $users );
		$this->assertStringContainsString( "apply_filters( 'manage_users_custom_column'", $users );
		$this->assertStringContainsString( '"manage_{$this->screen->id}_sortable_columns"', (string) file_get_contents( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' ) );
	}

	public function test_network_users_screen_applies_a_set_and_a_user_type_sorts(): void {
		$a = self::factory()->user->create( array( 'user_email' => 'carol@example.org', 'user_login' => 'ms-carol' ) );
		$b = self::factory()->user->create( array( 'user_email' => 'alice@example.org', 'user_login' => 'ms-alice' ) );
		$c = self::factory()->user->create( array( 'user_email' => 'bob@example.org', 'user_login' => 'ms-bob' ) );
		$set = $this->column_set( 'network-users', 'user.email' );

		$screen = new NetworkUserScreen( $this->registry, $this->sets );
		$screen->register();
		set_current_screen( 'users-network' );
		$this->assertSame( $set->id, $screen->active_set()?->id, 'the set applies' );

		$columns = apply_filters( 'wpmu_users_columns', array( 'cb' => '<input>', 'username' => 'Username', 'email' => 'Email' ) );
		$this->assertSame( array( 'cb', 'username', 'corinthian-c' ), array_keys( $columns ) );
		$sortable = apply_filters( 'manage_users-network_sortable_columns', array() );
		$this->assertArrayHasKey( 'corinthian-c', $sortable );

		$sorted = static function ( string $order ) use ( $a, $b, $c ): array {
			return ( new WP_User_Query(
				array(
					'blog_id' => 0,
					'include' => array( $a, $b, $c ),
					'fields'  => 'ID',
					'orderby' => 'corinthian-c',
					'order'   => $order,
				)
			) )->get_results();
		};
		$this->assertSame( array( $b, $c, $a ), array_map( 'intval', $sorted( 'ASC' ) ) );
		$this->assertSame( array( $a, $c, $b ), array_map( 'intval', $sorted( 'DESC' ) ) );

		$this->assertSame( 'alice@example.org', wp_strip_all_tags( apply_filters( 'manage_users_custom_column', '', 'corinthian-c', $b ) ) );
		$this->assertSame( 'untouched', apply_filters( 'manage_users_custom_column', 'untouched', 'plain', $b ) );
	}

	public function test_network_users_screen_does_not_leak_into_the_single_site_users_screen(): void {
		$this->column_set( 'network-users', 'user.email' );
		$network = new NetworkUserScreen( $this->registry, $this->sets );
		$single  = new UserScreen( $this->registry, $this->sets );
		$network->register();
		$single->register();
		set_current_screen( 'users' );
		$this->assertFalse( $network->is_current() );
		$this->assertTrue( $single->is_current() );
		$this->assertSame( array( 'username' => 'Username' ), $single->filter_columns( array( 'username' => 'Username' ) ), 'no set on the single site screen' );
	}

	public function test_non_super_admin_is_refused_for_list_and_read(): void {
		wp_set_current_user( $this->plain_admin );
		$this->assertFalse( is_super_admin() );
		$this->assertTrue( current_user_can( 'list_users' ), 'a site admin can list users, so this check is not vacuous' );
		$this->assertTrue( ( new UserScreen( $this->registry, $this->sets ) )->user_can_list() );

		$this->assertFalse( $this->sites_screen->user_can_list() );
		$this->assertFalse( $this->sites_screen->user_can_read( $this->ids['alpha'] ) );

		$this->assertFalse( $this->users_screen->user_can_list() );
		$this->assertFalse( $this->users_screen->user_can_read( $this->plain_admin ) );
		$this->assertTrue( ( new UserScreen( $this->registry, $this->sets ) )->user_can_read( $this->plain_admin ), 'the single site screen still reads' );

		wp_set_current_user( $this->super );
		$this->assertTrue( $this->sites_screen->user_can_list() );
		$this->assertTrue( $this->sites_screen->user_can_read( $this->ids['alpha'] ) );
		$this->assertFalse( $this->sites_screen->user_can_read( 99999999 ) );
		$this->assertTrue( $this->users_screen->user_can_list() );
		$this->assertTrue( $this->users_screen->user_can_read( $this->plain_admin ) );
	}

	public function test_editor_page_urls_and_menu_for_network_screens(): void {
		$this->assertStringStartsWith( network_admin_url( 'settings.php' ), \Corinthian\Admin\EditorPage::url( 'network-sites' ) );
		$this->assertStringContainsString( 'screen=network-users', \Corinthian\Admin\EditorPage::url( 'network-users', 3 ) );
		$this->assertStringStartsWith( admin_url( 'options-general.php' ), \Corinthian\Admin\EditorPage::url( 'users' ) );

		global $submenu;
		set_current_screen( 'dashboard-network' );
		( new \Corinthian\Admin\EditorPage() )->network_menu();
		$slugs = wp_list_pluck( (array) ( $submenu['settings.php'] ?? array() ), 2 );
		$this->assertContains( 'corinthian', $slugs );
		$entry = array_values( array_filter( $submenu['settings.php'], static fn( $e ) => 'corinthian' === $e[2] ) )[0];
		$this->assertSame( 'manage_network_options', $entry[1] );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function rest_write( string $method, string $route, array $body ): ?array {
		$request = new \WP_REST_Request( $method, $route );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );
		do_action( 'rest_api_init' );
		$response = rest_get_server()->dispatch( $request );
		return array(
			'status' => $response->get_status(),
			'data'   => $response->get_data(),
		);
	}

	public function test_network_sets_need_manage_network_options_to_write(): void {
		$payload = array(
			'screen'  => 'network-users',
			'title'   => 'Network',
			'columns' => array(),
		);

		wp_set_current_user( $this->plain_admin );
		$created = $this->rest_write( 'POST', '/corinthian/v1/sets', $payload );
		$this->assertContains( $created['status'], array( 401, 403 ), 'a main-site admin cannot create a network set' );

		wp_set_current_user( $this->super );
		$created = $this->rest_write( 'POST', '/corinthian/v1/sets', $payload );
		$this->assertSame( 201, $created['status'] );
		$id = (int) $created['data']['id'];

		wp_set_current_user( $this->plain_admin );
		$updated = $this->rest_write( 'PUT', '/corinthian/v1/sets/' . $id, array( 'title' => 'Hijacked' ) );
		$this->assertContains( $updated['status'], array( 401, 403 ) );
		$deleted = $this->rest_write( 'DELETE', '/corinthian/v1/sets/' . $id, array() );
		$this->assertContains( $deleted['status'], array( 401, 403 ) );
		$this->assertSame( 'Network', $this->sets->find( $id )->title );

		$site_set = $this->rest_write(
			'POST',
			'/corinthian/v1/sets',
			array(
				'screen'  => 'users',
				'title'   => 'Site',
				'columns' => array(),
			)
		);
		$this->assertSame( 201, $site_set['status'], 'single site screens stay writable for site admins' );
	}

	public function test_super_admins_without_a_role_match_administrator_sets(): void {
		$set   = new ColumnSet( id: 1, screen: 'users', title: 'Admins', columns: array(), settings: array( 'roles' => array( 'administrator' ) ) );
		$plain = new \WP_User( self::factory()->user->create( array( 'role' => '' ) ) );
		$this->assertFalse( $set->visible_to( $plain ), 'control: a roleless regular user does not match' );

		grant_super_admin( $plain->ID );
		$this->assertTrue( $set->visible_to( new \WP_User( $plain->ID ) ) );
	}

	public function test_the_import_screen_leaves_network_views_to_network_admins(): void {
		update_option( 'cpac_options_wp-ms_users', array( 'user_login' => array( 'type' => 'column-user_login', 'label' => '' ) ) );
		$importer = new \Corinthian\Import\AdminColumnsImporter( Plugin::instance() );

		$report = $importer->run( true, array(), false );
		$entry  = array_values( array_filter( $report, static fn( $e ) => 'option:cpac_options_wp-ms_users' === $e['source'] ) );
		$this->assertCount( 1, $entry );
		$this->assertSame( 'network admin only', $entry[0]['status'] );

		$report = $importer->run( true, array(), true );
		$entry  = array_values( array_filter( $report, static fn( $e ) => 'option:cpac_options_wp-ms_users' === $e['source'] ) );
		$this->assertNotSame( 'network admin only', $entry[0]['status'] );
	}
}
