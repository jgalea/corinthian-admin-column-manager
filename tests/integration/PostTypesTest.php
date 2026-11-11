<?php

namespace Corinthian\Tests;

use Corinthian\Columns\Config;
use Corinthian\Columns\Registry;
use Corinthian\Columns\Sortable;
use Corinthian\Columns\Types\TypeLoader;
use Corinthian\Screens\PostTypeScreen;
use Corinthian\Sets\SetRepository;
use WP_Query;
use WP_UnitTestCase;

class PostTypesTest extends WP_UnitTestCase {

	private const KEYS = array(
		'id',
		'slug',
		'excerpt',
		'word_count',
		'reading_time',
		'char_count',
		'featured_image',
		'has_featured_image',
		'permalink',
		'relative_path',
		'shortlink',
		'status',
		'visibility',
		'sticky',
		'comment_count',
		'comment_status',
		'ping_status',
		'author',
		'last_modified_by',
		'published_date',
		'modified_date',
		'parent',
		'menu_order',
		'template',
		'format',
		'taxonomy',
		'attachments_count',
		'children_count',
		'edit_lock',
		'type',
		'revisions_count',
		'guid',
		'shortcodes',
		'images_count',
		'links_count',
		'path_depth',
	);

	private const SORTABLE = array(
		'id',
		'slug',
		'word_count',
		'reading_time',
		'has_featured_image',
		'status',
		'sticky',
		'comment_count',
		'author',
		'published_date',
		'modified_date',
		'parent',
		'menu_order',
		'format',
		'taxonomy',
	);

	private Registry $registry;
	private PostTypeScreen $screen;
	private PostTypeScreen $pages;

	private int $alice;
	private int $bob;
	private int $attachment;
	private int $p1;
	private int $p2;
	private int $p3;
	private int $p0;

	public function set_up(): void {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		// A set that shows word counts, so posts store theirs on save.
		( new SetRepository() )->save(
			array(
				'screen'  => 'post:post',
				'title'   => 'Counts',
				'columns' => array(
					array(
						'id'   => 'wc',
						'type' => 'post.word_count',
					),
				),
			)
		);

		register_taxonomy(
			'corinthian_topic',
			'post',
			array(
				'label'     => 'Topics',
				'public'    => true,
				'show_ui'   => true,
				'query_var' => false,
			)
		);
		register_post_type(
			'cln_plain',
			array(
				'public'   => true,
				'supports' => array( 'title' ),
			)
		);

		$this->registry = new Registry();
		TypeLoader::register( $this->registry );
		$this->screen = new PostTypeScreen( $this->registry, new SetRepository(), get_post_type_object( 'post' ) );
		$this->pages  = new PostTypeScreen( $this->registry, new SetRepository(), get_post_type_object( 'page' ) );

		$this->alice = self::factory()->user->create(
			array(
				'display_name' => 'Alice',
				'user_login'   => 'alice_l',
				'user_email'   => 'alice@example.com',
			)
		);
		$this->bob   = self::factory()->user->create( array( 'display_name' => 'Bob' ) );

		$this->attachment = self::factory()->attachment->create_object( 'cln-test.png', 0, array( 'post_mime_type' => 'image/png' ) );

		$this->p1 = self::factory()->post->create(
			array(
				'post_title'   => 'One',
				'post_name'    => 'slug-b',
				'post_content' => '<p>one two three</p>',
				'post_excerpt' => 'one two three four five',
				'post_date'    => '2024-01-10 10:00:00',
				'post_author'  => $this->alice,
				'menu_order'   => 5,
			)
		);
		$this->p2 = self::factory()->post->create(
			array(
				'post_title'   => 'Two',
				'post_name'    => 'slug-a',
				'post_content' => 'one two three four five six seven eight nine ten',
				'post_date'    => '2024-02-10 10:00:00',
				'post_author'  => $this->bob,
			)
		);
		$this->p3 = self::factory()->post->create(
			array(
				'post_title'   => 'Three',
				'post_name'    => 'slug-c',
				'post_content' => str_repeat( 'word ', 450 ),
				'post_date'    => '2024-03-10 10:00:00',
				'post_author'  => $this->alice,
			)
		);
		$this->p0 = self::factory()->post->create(
			array(
				'post_title'   => 'Empty',
				'post_status'  => 'draft',
				'post_name'    => '',
				'post_content' => '',
				'post_date'    => '2024-04-10 10:00:00',
			)
		);

		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_author' => 0 ), array( 'ID' => $this->p0 ) );
		$wpdb->update( $wpdb->posts, array( 'comment_count' => 2 ), array( 'ID' => $this->p1 ) );
		$wpdb->update( $wpdb->posts, array( 'comment_count' => 7 ), array( 'ID' => $this->p2 ) );
		foreach ( array(
			$this->p3 => '2023-01-01',
			$this->p1 => '2023-02-01',
			$this->p2 => '2023-03-01',
			$this->p0 => '2023-04-01',
		) as $id => $day ) {
			$wpdb->update(
				$wpdb->posts,
				array(
					'post_modified'     => "$day 08:00:00",
					'post_modified_gmt' => "$day 08:00:00",
				),
				array( 'ID' => $id )
			);
		}
		foreach ( array( $this->p0, $this->p1, $this->p2, $this->p3 ) as $id ) {
			clean_post_cache( $id );
		}

		set_post_thumbnail( $this->p1, $this->attachment );
		set_post_format( $this->p1, 'aside' );
		set_post_format( $this->p2, 'quote' );
		stick_post( $this->p2 );
		wp_set_object_terms( $this->p1, array( 'Zeta' ), 'corinthian_topic' );
		wp_set_object_terms( $this->p2, array( 'Alpha' ), 'corinthian_topic' );
		wp_set_object_terms( $this->p3, array( 'Zzz', 'Mid' ), 'corinthian_topic' );
	}

	public function tear_down(): void {
		unregister_taxonomy( 'corinthian_topic' );
		unregister_post_type( 'cln_plain' );
		remove_shortcode( 'corinthian_test' );
		parent::tear_down();
	}

	private function cfg( string $key, array $settings = array() ): Config {
		return new Config( 'c1', $key, 'Label', null, 'px', $settings );
	}

	private function render( string $name, int $id, array $settings = array(), ?PostTypeScreen $screen = null ): string {
		$key  = 'post.' . $name;
		$type = $this->registry->get( $key );
		$this->assertNotNull( $type, "$key is registered" );
		return $type->render( $id, $this->cfg( $key, $settings ), $screen ?? $this->screen );
	}

	private function value( string $name, int $id, array $settings = array(), ?PostTypeScreen $screen = null ) {
		$key = 'post.' . $name;
		return $this->registry->get( $key )->value( $id, $this->cfg( $key, $settings ), $screen ?? $this->screen );
	}

	/**
	 * Runs a real WP_Query with the column's sort_clauses applied the way PostTypeScreen does.
	 *
	 * @return list<int>
	 */
	private function sorted( string $name, array $settings, string $order, array $ids, string $post_type = 'post' ): array {
		$key    = 'post.' . $name;
		$type   = $this->registry->get( $key );
		$config = $this->cfg( $key, $settings );
		$screen = 'page' === $post_type ? $this->pages : $this->screen;
		$this->assertInstanceOf( Sortable::class, $type );

		$callback = function ( $clauses ) use ( $type, $config, $order, $screen ) {
			global $wpdb;
			$clauses            = $type->sort_clauses( $clauses, $config, $order, $screen );
			$clauses['orderby'] = trim( ( $clauses['orderby'] ?? '' ) . ", {$wpdb->posts}.ID {$order}", ', ' );
			return $clauses;
		};
		add_filter( 'posts_clauses', $callback );
		$query = new WP_Query(
			array(
				'post__in'            => $ids,
				'post_type'           => $post_type,
				'post_status'         => 'any',
				'orderby'             => 'none',
				'fields'              => 'ids',
				'posts_per_page'      => -1,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'suppress_filters'    => false,
			)
		);
		remove_filter( 'posts_clauses', $callback );

		$this->assertSame( '', (string) $GLOBALS['wpdb']->last_error );
		return array_map( 'intval', $query->posts );
	}

	private function assertSortOrder( string $name, array $settings, array $asc, array $desc, string $post_type = 'post' ): void {
		$all = $asc;
		$this->assertSame( $asc, $this->sorted( $name, $settings, 'ASC', $all, $post_type ), "$name ASC" );
		$this->assertSame( $desc, $this->sorted( $name, $settings, 'DESC', $all, $post_type ), "$name DESC" );
	}

	private function dash(): string {
		return '<span aria-hidden="true">&#8212;</span>';
	}

	private function pages(): array {
		$parent_b = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Parent B',
				'post_name'  => 'parent-b',
			)
		);
		$parent_a = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Parent A',
				'post_name'  => 'parent-a',
			)
		);
		$c1       = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $parent_b,
				'menu_order'  => 3,
				'post_name'   => 'child-one',
			)
		);
		$c2       = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $parent_a,
				'menu_order'  => 1,
			)
		);
		$orphan   = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'menu_order' => 2,
			)
		);
		return compact( 'parent_a', 'parent_b', 'c1', 'c2', 'orphan' );
	}

	public function test_every_type_is_registered_and_supported_on_posts_or_pages(): void {
		$this->assertCount( 36, self::KEYS );
		foreach ( self::KEYS as $name ) {
			$type = $this->registry->get( 'post.' . $name );
			$this->assertNotNull( $type, $name );
			$this->assertTrue( $type->supports( $this->screen ) || $type->supports( $this->pages ), $name );
			$this->assertContains( $type->group(), array( 'Post', 'Dates', 'Author', 'Taxonomies', 'Content', 'Media', 'Comments', 'Hierarchy' ), $name );
			$this->assertNotSame( '', $type->label() );
			$this->assertSame( in_array( $name, self::SORTABLE, true ), $type instanceof Sortable, "$name sortable" );
		}
	}

	public function test_every_type_renders_a_string_for_a_seeded_post(): void {
		foreach ( self::KEYS as $name ) {
			$type = $this->registry->get( 'post.' . $name );
			if ( ! $type->supports( $this->screen ) ) {
				continue;
			}
			$this->assertNotSame( '', $this->render( $name, $this->p1 ), $name );
		}
	}

	public function test_support_checks_follow_post_type_features(): void {
		$plain = new PostTypeScreen( $this->registry, new SetRepository(), get_post_type_object( 'cln_plain' ) );
		foreach ( array( 'excerpt', 'featured_image', 'has_featured_image', 'word_count', 'reading_time', 'char_count', 'comment_count', 'comment_status', 'ping_status', 'taxonomy', 'parent', 'sticky', 'format', 'revisions_count', 'author' ) as $name ) {
			$this->assertFalse( $this->registry->get( 'post.' . $name )->supports( $plain ), "$name on a title-only type" );
		}
		$this->assertTrue( $this->registry->get( 'post.id' )->supports( $plain ) );
		$this->assertFalse( $this->registry->get( 'post.sticky' )->supports( $this->pages ) );
		$this->assertFalse( $this->registry->get( 'post.excerpt' )->supports( $this->pages ) );
		$this->assertFalse( $this->registry->get( 'post.parent' )->supports( $this->screen ) );
		$this->assertTrue( $this->registry->get( 'post.parent' )->supports( $this->pages ) );
		$this->assertTrue( $this->registry->get( 'post.menu_order' )->supports( $this->pages ) );
		$this->assertFalse( $this->registry->get( 'post.menu_order' )->supports( $this->screen ) );
		$this->assertTrue( $this->registry->get( 'post.children_count' )->supports( $this->pages ) );
		$this->assertFalse( $this->registry->get( 'post.children_count' )->supports( $this->screen ) );
	}

	public function test_id_and_slug(): void {
		$this->assertSame( (string) $this->p1, $this->render( 'id', $this->p1 ) );
		$this->assertSame( 'slug-b', $this->render( 'slug', $this->p1 ) );
		$this->assertSame( $this->dash(), $this->render( 'slug', $this->p0 ) );
		$this->assertSortOrder( 'id', array(), array( $this->p1, $this->p2, $this->p3, $this->p0 ), array( $this->p0, $this->p3, $this->p2, $this->p1 ) );
		$this->assertSortOrder( 'slug', array(), array( $this->p2, $this->p1, $this->p3, $this->p0 ), array( $this->p3, $this->p1, $this->p2, $this->p0 ) );
	}

	public function test_status_and_visibility(): void {
		$this->assertSame( 'Published', $this->render( 'status', $this->p1 ) );
		$this->assertSame( 'Draft', $this->render( 'status', $this->p0 ) );
		$this->assertSortOrder( 'status', array(), array( $this->p0, $this->p1, $this->p2, $this->p3 ), array( $this->p3, $this->p2, $this->p1, $this->p0 ) );

		$locked  = self::factory()->post->create( array( 'post_password' => 'secret' ) );
		$private = self::factory()->post->create( array( 'post_status' => 'private' ) );
		$this->assertSame( 'Public', $this->render( 'visibility', $this->p1 ) );
		$this->assertSame( 'Password protected', $this->render( 'visibility', $locked ) );
		$this->assertSame( 'Private', $this->render( 'visibility', $private ) );
	}

	public function test_excerpt_respects_word_limit_and_escapes(): void {
		$this->assertSame( 'one two three…', $this->render( 'excerpt', $this->p1, array( 'words' => 3 ) ) );
		$this->assertSame( 'one two three four five', $this->render( 'excerpt', $this->p1 ) );
		$blank = self::factory()->post->create( array( 'post_excerpt' => '' ) );
		$this->assertSame( $this->dash(), $this->render( 'excerpt', $blank ) );

		$xss = self::factory()->post->create( array( 'post_excerpt' => '<script>alert(1)</script> a & b' ) );
		$out = $this->render( 'excerpt', $xss );
		$this->assertStringNotContainsString( '<script', $out );
		$this->assertStringContainsString( '&amp;', $out );
	}

	public function test_word_count_is_stored_on_save_and_backfilled_on_render(): void {
		$this->assertSame( '3', get_post_meta( $this->p1, '_corinthian_word_count', true ) );
		$this->assertSame( '3', $this->render( 'word_count', $this->p1 ) );
		$this->assertSame( '450', $this->render( 'word_count', $this->p3 ) );

		delete_post_meta( $this->p2, '_corinthian_word_count' );
		$this->assertSame( '10', $this->render( 'word_count', $this->p2 ) );
		$this->assertSame( '10', get_post_meta( $this->p2, '_corinthian_word_count', true ) );

		wp_update_post(
			array(
				'ID'           => $this->p1,
				'post_content' => 'a b c d',
			)
		);
		$this->assertSame( '4', get_post_meta( $this->p1, '_corinthian_word_count', true ) );
		wp_update_post(
			array(
				'ID'           => $this->p1,
				'post_content' => '<p>one two three</p>',
			)
		);
	}

	public function test_word_count_sort_puts_missing_values_last(): void {
		delete_post_meta( $this->p0, '_corinthian_word_count' );
		$this->assertSortOrder( 'word_count', array(), array( $this->p1, $this->p2, $this->p3, $this->p0 ), array( $this->p3, $this->p2, $this->p1, $this->p0 ) );
	}

	public function test_reading_time(): void {
		$this->assertSame( '1 min read', $this->render( 'reading_time', $this->p1 ) );
		$this->assertSame( '3 min read', $this->render( 'reading_time', $this->p3 ) );
		$this->assertSame( '5 min read', $this->render( 'reading_time', $this->p3, array( 'words_per_minute' => 100 ) ) );
		$this->assertSame( $this->dash(), $this->render( 'reading_time', $this->p0 ) );
		$this->assertSortOrder( 'reading_time', array(), array( $this->p1, $this->p2, $this->p3, $this->p0 ), array( $this->p3, $this->p2, $this->p1, $this->p0 ) );
	}

	public function test_character_count(): void {
		$this->assertSame( '13', $this->render( 'char_count', $this->p1 ) );
		$this->assertSame( '2,249', $this->render( 'char_count', $this->p3 ) );
		$this->assertSame( '0', (string) $this->value( 'char_count', $this->p0 ) );
	}

	public function test_featured_image_and_has_featured_image(): void {
		$out = $this->render( 'featured_image', $this->p1 );
		$this->assertStringContainsString( '<img', $out );
		$this->assertStringContainsString( 'post=' . $this->p1, $out );
		$this->assertStringContainsString( 'action=edit', $out );
		$this->assertSame( $this->dash(), $this->render( 'featured_image', $this->p2 ) );
		$this->assertStringContainsString( 'size-medium', $this->render( 'featured_image', $this->p1, array( 'size' => 'medium' ) ) );
		$this->assertStringContainsString( 'size-thumbnail', $this->render( 'featured_image', $this->p1, array( 'size' => 'not-a-size' ) ) );

		$this->assertStringContainsString( 'dashicons-yes', $this->render( 'has_featured_image', $this->p1 ) );
		$this->assertStringContainsString( 'dashicons-no-alt', $this->render( 'has_featured_image', $this->p2 ) );
		$this->assertSortOrder( 'has_featured_image', array(), array( $this->p1, $this->p2, $this->p3, $this->p0 ), array( $this->p1, $this->p0, $this->p3, $this->p2 ) );
	}

	public function test_permalink_relative_path_shortlink_and_depth(): void {
		$link = get_permalink( $this->p1 );
		$out  = $this->render( 'permalink', $this->p1 );
		$this->assertStringContainsString( 'href="' . esc_url( $link ) . '"', $out );
		$this->assertStringContainsString( 'rel="noopener noreferrer"', $out );
		$this->assertSame( '/slug-b/', $this->render( 'relative_path', $this->p1 ) );
		$this->assertSame( esc_html( wp_get_shortlink( $this->p1 ) ), $this->render( 'shortlink', $this->p1 ) );
		$this->assertSame( '1', $this->render( 'path_depth', $this->p1 ) );

		$pages = $this->pages();
		$this->assertSame( '2', $this->render( 'path_depth', $pages['c1'], array(), $this->pages ) );
		$this->assertSame( '/parent-b/child-one/', $this->render( 'relative_path', $pages['c1'], array(), $this->pages ) );
	}

	public function test_sticky(): void {
		$this->assertStringContainsString( 'dashicons-yes', $this->render( 'sticky', $this->p2 ) );
		$this->assertStringContainsString( 'dashicons-no-alt', $this->render( 'sticky', $this->p1 ) );
		$this->assertSortOrder( 'sticky', array(), array( $this->p2, $this->p1, $this->p3, $this->p0 ), array( $this->p2, $this->p0, $this->p3, $this->p1 ) );
	}

	public function test_comment_count_comment_status_and_ping_status(): void {
		$out = $this->render( 'comment_count', $this->p2 );
		$this->assertStringContainsString( 'edit-comments.php?p=' . $this->p2, $out );
		$this->assertStringContainsString( '>7</a>', $out );
		$this->assertSame( '0', $this->render( 'comment_count', $this->p3 ) );
		$this->assertSortOrder( 'comment_count', array(), array( $this->p3, $this->p0, $this->p1, $this->p2 ), array( $this->p2, $this->p1, $this->p0, $this->p3 ) );

		wp_update_post(
			array(
				'ID'             => $this->p3,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			)
		);
		$this->assertSame( 'Closed', $this->render( 'comment_status', $this->p3 ) );
		$this->assertSame( 'Closed', $this->render( 'ping_status', $this->p3 ) );
		$this->assertSame( 'Open', $this->render( 'comment_status', $this->p1 ) );
		$this->assertSame( 'Open', $this->render( 'ping_status', $this->p1 ) );
	}

	public function test_author_display_modes_and_sort(): void {
		$this->assertStringContainsString( '>Alice</a>', $this->render( 'author', $this->p1 ) );
		$this->assertStringContainsString( 'author=' . $this->alice, $this->render( 'author', $this->p1 ) );
		$this->assertStringContainsString( '>alice_l</a>', $this->render( 'author', $this->p1, array( 'display' => 'user_login' ) ) );
		$this->assertStringContainsString( '>alice@example.com</a>', $this->render( 'author', $this->p1, array( 'display' => 'user_email' ) ) );
		$avatar = $this->render( 'author', $this->p1, array( 'display' => 'avatar_name' ) );
		$this->assertStringContainsString( '<img', $avatar );
		$this->assertStringContainsString( 'Alice</a>', $avatar );
		$this->assertSame( $this->dash(), $this->render( 'author', $this->p0 ) );

		$eve = self::factory()->user->create( array( 'display_name' => 'Eve <i>x</i>' ) );
		$id  = self::factory()->post->create( array( 'post_author' => $eve ) );
		$this->assertStringNotContainsString( '<i>', $this->render( 'author', $id ) );

		$this->assertSortOrder( 'author', array(), array( $this->p1, $this->p3, $this->p2, $this->p0 ), array( $this->p2, $this->p3, $this->p1, $this->p0 ) );
		$this->assertSortOrder( 'author', array( 'display' => 'user_login' ), array( $this->p1, $this->p3, $this->p2, $this->p0 ), array( $this->p2, $this->p3, $this->p1, $this->p0 ) );
	}

	public function test_last_modified_by_and_edit_lock(): void {
		$this->assertSame( $this->dash(), $this->render( 'last_modified_by', $this->p1 ) );
		update_post_meta( $this->p1, '_edit_last', $this->bob );
		$this->assertSame( 'Bob', $this->render( 'last_modified_by', $this->p1 ) );

		$this->assertSame( $this->dash(), $this->render( 'edit_lock', $this->p1 ) );
		update_post_meta( $this->p1, '_edit_lock', time() . ':' . $this->alice );
		$this->assertSame( 'Alice', $this->render( 'edit_lock', $this->p1 ) );
		update_post_meta( $this->p1, '_edit_lock', ( time() - 1000 ) . ':' . $this->alice );
		$this->assertSame( $this->dash(), $this->render( 'edit_lock', $this->p1 ) );
	}

	public function test_dates_formats_and_sort(): void {
		$this->assertSame( 'January 10, 2024', $this->render( 'published_date', $this->p1 ) );
		$this->assertSame( 'January 10, 2024 10:00 am', $this->render( 'published_date', $this->p1, array( 'format' => 'datetime' ) ) );
		$this->assertSame(
			'2024/01/10',
			$this->render(
				'published_date',
				$this->p1,
				array(
					'format'        => 'custom',
					'custom_format' => 'Y/m/d',
				)
			)
		);
		$this->assertSame(
			'2023-02-01',
			$this->render(
				'modified_date',
				$this->p1,
				array(
					'format'        => 'custom',
					'custom_format' => 'Y-m-d',
				)
			)
		);
		$this->assertStringContainsString( 'ago', $this->render( 'published_date', $this->p1, array( 'format' => 'relative' ) ) );

		$recent = self::factory()->post->create(
			array(
				'post_date'     => gmdate( 'Y-m-d H:i:s', time() - 3 * HOUR_IN_SECONDS ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - 3 * HOUR_IN_SECONDS ),
			)
		);
		$this->assertSame( '3 hours ago', $this->render( 'published_date', $recent, array( 'format' => 'relative' ) ) );

		$this->assertSortOrder( 'published_date', array(), array( $this->p1, $this->p2, $this->p3, $this->p0 ), array( $this->p0, $this->p3, $this->p2, $this->p1 ) );
		$this->assertSortOrder( 'modified_date', array(), array( $this->p3, $this->p1, $this->p2, $this->p0 ), array( $this->p0, $this->p2, $this->p1, $this->p3 ) );
	}

	public function test_parent_menu_order_children_and_template_on_pages(): void {
		$p = $this->pages();
		$this->assertSame( '<a href="' . esc_url( get_edit_post_link( $p['parent_b'], 'raw' ) ) . '">Parent B</a>', $this->render( 'parent', $p['c1'], array(), $this->pages ) );
		$this->assertSame( $this->dash(), $this->render( 'parent', $p['orphan'], array(), $this->pages ) );
		$this->assertSame( '3', $this->render( 'menu_order', $p['c1'], array(), $this->pages ) );
		$this->assertSame( '1', $this->render( 'children_count', $p['parent_b'], array(), $this->pages ) );
		$this->assertSame( '0', $this->render( 'children_count', $p['c1'], array(), $this->pages ) );

		update_post_meta( $p['c1'], '_wp_page_template', 'wide.php' );
		$this->assertSame( 'wide.php', $this->render( 'template', $p['c1'], array(), $this->pages ) );
		$this->assertSame( $this->dash(), $this->render( 'template', $p['c2'], array(), $this->pages ) );

		$ids = array( $p['c1'], $p['c2'], $p['orphan'] );
		$this->assertSortOrder( 'parent', array(), array( $p['c2'], $p['c1'], $p['orphan'] ), array( $p['c1'], $p['c2'], $p['orphan'] ), 'page' );
		$this->assertSortOrder( 'menu_order', array(), array( $p['c2'], $p['orphan'], $p['c1'] ), array( $p['c1'], $p['orphan'], $p['c2'] ), 'page' );
		$this->assertCount( 3, $ids );
	}

	public function test_parent_title_is_escaped(): void {
		$parent = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'A <b>bold</b> & "quoted"',
			)
		);
		$child  = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $parent,
			)
		);
		$out    = $this->render( 'parent', $child, array(), $this->pages );
		$this->assertStringNotContainsString( '<b>', $out );
		$this->assertStringContainsString( '&lt;b&gt;bold', $out );
	}

	public function test_post_format(): void {
		$this->assertSame( 'Aside', $this->render( 'format', $this->p1 ) );
		$this->assertSame( 'Quote', $this->render( 'format', $this->p2 ) );
		$this->assertSame( 'Standard', $this->render( 'format', $this->p3 ) );
		$this->assertSortOrder( 'format', array(), array( $this->p1, $this->p2, $this->p3, $this->p0 ), array( $this->p2, $this->p1, $this->p0, $this->p3 ) );
	}

	public function test_taxonomy_terms_link_to_filtered_list_and_sort_by_first_term(): void {
		$out = $this->render( 'taxonomy', $this->p3, array( 'taxonomy' => 'corinthian_topic' ) );
		$this->assertStringContainsString( 'taxonomy=corinthian_topic', $out );
		$this->assertStringContainsString( 'term=mid', $out );
		$this->assertStringContainsString( 'post_type=post', $out );
		$this->assertStringContainsString( '>Mid</a>', $out );
		$this->assertStringContainsString( '>Zzz</a>', $out );
		$this->assertSame( $this->dash(), $this->render( 'taxonomy', $this->p0, array( 'taxonomy' => 'corinthian_topic' ) ) );

		$category = wp_insert_term( 'News <b>', 'category', array( 'slug' => 'news' ) );
		wp_set_object_terms( $this->p1, array( (int) $category['term_id'] ), 'category' );
		$cat = $this->render( 'taxonomy', $this->p1, array( 'taxonomy' => 'category' ) );
		$this->assertStringContainsString( 'category_name=news', $cat );
		$this->assertStringNotContainsString( '<b>', $cat );

		$this->assertContains( 'corinthian_topic', array_keys( $this->registry->get( 'post.taxonomy' )->settings_schema( $this->screen )[0]['options'] ) );

		$settings = array( 'taxonomy' => 'corinthian_topic' );
		$this->assertSortOrder( 'taxonomy', $settings, array( $this->p2, $this->p3, $this->p1, $this->p0 ), array( $this->p1, $this->p3, $this->p2, $this->p0 ) );
	}

	public function test_counts_attachments_revisions_images_links_and_shortcodes(): void {
		self::factory()->attachment->create_object( 'cln-a.png', $this->p1, array( 'post_mime_type' => 'image/png' ) );
		self::factory()->attachment->create_object( 'cln-b.png', $this->p1, array( 'post_mime_type' => 'image/png' ) );
		wp_insert_post(
			array(
				'post_type'    => 'revision',
				'post_status'  => 'inherit',
				'post_parent'  => $this->p2,
				'post_name'    => $this->p2 . '-revision-v1',
				'post_title'   => 'r',
				'post_content' => 'x',
			)
		);
		add_shortcode( 'corinthian_test', '__return_empty_string' );
		$rich = self::factory()->post->create(
			array(
				'post_content' => '<p>See <a href="/about/">a</a> <a href="' . home_url( '/y' ) . '">b</a> <a href="https://example.com/x">c</a> <a href=\'https://www.other.test/\'>d</a> <a href="#top">e</a> <a href="mailto:x@example.com">f</a></p><img src="a.png"><img src="b.png"><img src="c.png">[corinthian_test] [gallery ids="1"] [[corinthian_test]]',
			)
		);

		$this->assertSame( '2', $this->render( 'attachments_count', $this->p1 ) );
		$this->assertSame( '0', $this->render( 'attachments_count', $this->p3 ) );
		$this->assertSame( '1', $this->render( 'revisions_count', $this->p2 ) );
		$this->assertSame( '0', $this->render( 'revisions_count', $this->p3 ) );
		$this->assertSame( '3', $this->render( 'images_count', $rich ) );
		$this->assertSame( '0', $this->render( 'images_count', $this->p1 ) );

		$this->assertSame( '2 internal / 2 external', $this->render( 'links_count', $rich ) );
		$this->assertSame( '2', $this->render( 'links_count', $rich, array( 'show' => 'internal' ) ) );
		$this->assertSame( '2', $this->render( 'links_count', $rich, array( 'show' => 'external' ) ) );

		$this->assertSame( '<code>[corinthian_test]</code> <code>[gallery]</code>', $this->render( 'shortcodes', $rich ) );
		$this->assertSame( $this->dash(), $this->render( 'shortcodes', $this->p1 ) );
	}

	public function test_guid_type_and_shortlink(): void {
		$this->assertSame( esc_html( get_post( $this->p1 )->guid ), $this->render( 'guid', $this->p1 ) );
		$this->assertSame( 'Post', $this->render( 'type', $this->p1 ) );
		$page = $this->pages()['c1'];
		$this->assertSame( 'Page', $this->render( 'type', $page, array(), $this->pages ) );
		$this->assertStringContainsString( (string) $page, $this->render( 'shortlink', $page, array(), $this->pages ) );
	}
}
