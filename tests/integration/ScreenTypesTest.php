<?php

namespace Corinthian\Tests;

use Corinthian\Columns\Config;
use Corinthian\Columns\Registry;
use Corinthian\Columns\Sortable;
use Corinthian\Columns\Types\Media\MediaMeta;
use Corinthian\Columns\Types\ScreenTypeLoader;
use Corinthian\Screens\CommentScreen;
use Corinthian\Screens\MediaScreen;
use Corinthian\Screens\PostTypeScreen;
use Corinthian\Screens\Screen;
use Corinthian\Screens\TermScreen;
use Corinthian\Screens\UserScreen;
use Corinthian\Sets\SetRepository;
use WP_Comment_Query;
use WP_Query;
use WP_Term_Query;
use WP_UnitTestCase;
use WP_User_Query;

class ScreenTypesTest extends WP_UnitTestCase {

	private Registry $registry;

	/** @var list<string> */
	private array $files = array();

	private int $seq = 0;

	public function set_up(): void {
		parent::set_up();
		$this->registry = new Registry();
		ScreenTypeLoader::register( $this->registry );
		wp_cache_delete( 'last_login_key', 'corinthian' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down(): void {
		foreach ( $this->files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}
		$this->files = array();
		parent::tear_down();
	}

	private function screen( string $kind ): Screen {
		$sets = new SetRepository();
		switch ( $kind ) {
			case 'user':
				return new UserScreen( $this->registry, $sets );
			case 'comment':
				return new CommentScreen( $this->registry, $sets );
			case 'term':
				$taxonomy = get_taxonomy( 'category' );
				$this->assertNotFalse( $taxonomy );
				return new TermScreen( $this->registry, $sets, $taxonomy );
			case 'media':
				return new MediaScreen( $this->registry, $sets, get_post_type_object( 'attachment' ) );
			default:
				return new PostTypeScreen( $this->registry, $sets, get_post_type_object( 'post' ) );
		}
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	private function config( string $key, array $settings = array() ): Config {
		return new Config( 'c' . ( ++$this->seq ), $key, 'Label', null, 'px', $settings );
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	private function render( string $kind, string $key, int $id, array $settings = array() ): string {
		$type = $this->registry->get( $key );
		$this->assertNotNull( $type, "Type $key is registered" );
		return $type->render( $id, $this->config( $key, $settings ), $this->screen( $kind ) );
	}

	private function dash(): string {
		return '<span aria-hidden="true">&#8212;</span>';
	}

	// Registration.

	/** @return list<string> */
	private function expected_keys(): array {
		return array(
			'user.id', 'user.username', 'user.display_name', 'user.first_name', 'user.last_name', 'user.nickname', 'user.email', 'user.website',
			'user.registered', 'user.roles', 'user.post_count', 'user.comment_count', 'user.avatar', 'user.description', 'user.meta',
			'user.last_login', 'user.capabilities', 'user.rich_editing', 'user.admin_color', 'user.locale',
			'media.file_name', 'media.file_size', 'media.mime_type', 'media.dimensions', 'media.width', 'media.height', 'media.alt',
			'media.caption', 'media.description', 'media.url', 'media.attached_to', 'media.uploaded_by', 'media.exif_camera',
			'media.exif_aperture', 'media.exif_iso', 'media.exif_shutter', 'media.exif_focal_length', 'media.exif_created',
			'media.sizes', 'media.featured_count',
			'comment.id', 'comment.author', 'comment.author_email', 'comment.author_url', 'comment.author_ip', 'comment.date',
			'comment.status', 'comment.type', 'comment.parent', 'comment.post_title', 'comment.excerpt', 'comment.word_count',
			'comment.user_agent', 'comment.meta',
			'term.id', 'term.name', 'term.slug', 'term.description', 'term.post_count', 'term.parent', 'term.meta', 'term.link', 'term.depth',
		);
	}

	public function test_registers_every_key_once(): void {
		$expected = $this->expected_keys();
		$found    = array();
		foreach ( $expected as $key ) {
			$type = $this->registry->get( $key );
			$this->assertNotNull( $type, "$key is registered" );
			$this->assertSame( $key, $type->key() );
			$found[] = $type->key();
		}
		$this->assertCount( count( array_unique( $expected ) ), $found );
		$this->assertGreaterThanOrEqual( 60, count( $expected ) );

		$all = array();
		foreach ( array( 'user', 'media', 'comment', 'term' ) as $kind ) {
			$all = array_merge( $all, array_keys( $this->registry->for_screen( $this->screen( $kind ) ) ) );
		}
		// user.last_login only appears once a login meta key exists.
		$this->assertEqualsCanonicalizing( array_values( array_diff( $expected, array( 'user.last_login' ) ) ), $all );
	}

	public function test_supports_only_their_own_screen(): void {
		$screens = array();
		foreach ( array( 'user', 'media', 'comment', 'term', 'post' ) as $kind ) {
			$screens[ $kind ] = $this->screen( $kind );
		}
		foreach ( $this->expected_keys() as $key ) {
			$type = $this->registry->get( $key );
			$own  = explode( '.', $key )[0];
			foreach ( $screens as $kind => $screen ) {
				if ( 'user.last_login' === $key ) {
					continue;
				}
				$this->assertSame( $own === $kind, $type->supports( $screen ), "$key on the $kind screen" );
			}
		}
		$page = new PostTypeScreen( $this->registry, new SetRepository(), get_post_type_object( 'page' ) );
		$this->assertFalse( $this->registry->get( 'media.file_name' )->supports( $page ) );
	}

	public function test_last_login_needs_an_existing_meta_key(): void {
		$screen = $this->screen( 'user' );
		$type   = $this->registry->get( 'user.last_login' );
		$this->assertFalse( $type->supports( $screen ) );

		$user = self::factory()->user->create();
		update_user_meta( $user, 'last_login', 1700000000 );
		wp_cache_delete( 'last_login_key', 'corinthian' );
		$this->assertTrue( $type->supports( $screen ) );
		$schema = $type->settings_schema( $screen );
		$this->assertSame( 'last_login', $schema[0]['default'] );
		$this->assertSame( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), 1700000000 ), $this->render( 'user', 'user.last_login', $user ) );
	}

	public function test_groups_use_screen_vocabulary(): void {
		$groups = array();
		foreach ( $this->expected_keys() as $key ) {
			$groups[ $this->registry->get( $key )->group() ] = true;
		}
		$this->assertEqualsCanonicalizing( array( 'User', 'Media', 'File', 'EXIF', 'Comment', 'Term', 'Custom fields' ), array_keys( $groups ) );
	}

	// Fixtures.

	/** @return array<string,int> */
	private function user_fixture(): array {
		$user = self::factory()->user->create(
			array(
				'user_login'      => 'rendu',
				'display_name'    => 'Render User',
				'first_name'      => 'Ada',
				'last_name'       => 'Lovelace',
				'nickname'        => 'Countess',
				'user_email'      => 'ada@example.org',
				'user_url'        => 'https://example.org/ada',
				'user_registered' => '2021-03-04 05:06:07',
				'role'            => 'editor',
				'description'     => 'Bio <b>bold</b> text',
			)
		);
		self::factory()->post->create_many( 2, array( 'post_author' => $user ) );
		$post = self::factory()->post->create();
		self::factory()->comment->create(
			array(
				'user_id'          => $user,
				'comment_post_ID'  => $post,
				'comment_approved' => '1',
			)
		);
		update_user_meta( $user, 'cln_note', 'hello <i>x</i>' );
		update_user_meta( $user, 'cln_flag', '0' );
		update_user_meta( $user, 'cln_link', 'https://example.org/x' );
		update_user_meta( $user, 'cln_num', '1234.5' );
		update_user_meta( $user, 'last_login', 1600000000 );
		update_user_meta( $user, 'locale', 'de_DE' );
		update_user_meta( $user, 'admin_color', 'sunrise' );
		update_user_meta( $user, 'rich_editing', 'false' );
		return array( 'user' => $user );
	}

	/** @return array<string,int> */
	private function comment_fixture(): array {
		$post = self::factory()->post->create( array( 'post_title' => 'Hello Post' ) );
		$root = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post,
				'comment_author'  => 'Parent Person',
			)
		);
		$id   = self::factory()->comment->create(
			array(
				'comment_post_ID'      => $post,
				'comment_parent'       => $root,
				'comment_author'       => 'Grace <script>alert(1)</script>',
				'comment_author_email' => 'grace@example.org',
				'comment_author_url'   => 'https://example.org/g',
				'comment_author_IP'    => '10.1.2.3',
				'comment_agent'        => 'UA/1.0 <x>',
				'comment_content'      => 'one two three <b>four</b> five six',
				'comment_date'         => '2022-05-06 07:08:09',
				'comment_date_gmt'     => '2022-05-06 07:08:09',
				'comment_approved'     => '1',
				'comment_type'         => 'comment',
			)
		);
		update_comment_meta( $id, 'cln_note', 'note' );
		return array(
			'comment' => $id,
			'parent'  => $root,
		);
	}

	/** @return array<string,int> */
	private function term_fixture(): array {
		$this->set_permalink_structure( '/%postname%/' );
		create_initial_taxonomies();
		$parent = self::factory()->category->create( array( 'name' => 'Parent Cat' ) );
		$child  = self::factory()->category->create(
			array(
				'name'        => 'Child Cat',
				'slug'        => 'child-cat',
				'parent'      => $parent,
				'description' => 'A <b>desc</b> here',
			)
		);
		global $wpdb;
		$wpdb->update( $wpdb->term_taxonomy, array( 'count' => 4 ), array( 'term_id' => $child ) );
		clean_term_cache( $child, 'category' );
		update_term_meta( $child, 'cln_note', 'tm' );
		return array(
			'term'   => $child,
			'parent' => $parent,
		);
	}

	private function attachment( array $args = array(), string $name = '', int $bytes = 0 ): int {
		$file = '';
		if ( '' !== $name ) {
			$dir  = wp_upload_dir();
			$file = $dir['path'] . '/' . $name;
			file_put_contents( $file, str_repeat( 'x', $bytes ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$this->files[] = $file;
		}
		$args += array(
			'post_title'     => 'att' . ( ++$this->seq ),
			'post_status'    => 'inherit',
			'post_mime_type' => 'image/jpeg',
		);
		return (int) wp_insert_attachment( $args, $file );
	}

	/** @return array<string,int> */
	private function media_fixture(): array {
		$author = self::factory()->user->create( array( 'display_name' => 'Shooter' ) );
		$post   = self::factory()->post->create( array( 'post_title' => 'Gallery Post' ) );
		$id     = $this->attachment(
			array(
				'post_parent'  => $post,
				'post_author'  => $author,
				'post_excerpt' => 'A caption',
				'post_content' => 'A <i>description</i> text',
			),
			'photo.jpg',
			2048
		);
		update_post_meta( $id, '_wp_attachment_image_alt', 'Alt <img src=x onerror=alert(1)>' );
		wp_update_attachment_metadata(
			$id,
			array(
				'width'      => 1024,
				'height'     => 768,
				'filesize'   => 2048,
				'sizes'      => array(
					'thumbnail' => array(
						'width'  => 150,
						'height' => 150,
					),
				),
				'image_meta' => array(
					'camera'            => 'Canon EOS',
					'aperture'          => '2.8',
					'iso'               => '400',
					'shutter_speed'     => '0.004',
					'focal_length'      => '50',
					'created_timestamp' => 1600000000,
				),
			)
		);
		foreach ( array( 'One', 'Two' ) as $title ) {
			$p = self::factory()->post->create( array( 'post_title' => $title ) );
			update_post_meta( $p, '_thumbnail_id', $id );
		}
		return array( 'media' => $id );
	}

	// Rendering.

	/**
	 * @return array<string,array{0:string}>
	 */
	public static function render_keys(): array {
		$keys = array();
		foreach ( array_keys( self::render_cases() ) as $name ) {
			$keys[ $name ] = array( $name );
		}
		return $keys;
	}

	/**
	 * Each case: kind, type key, settings, fixture name, then the strings the output must and must not contain.
	 *
	 * @return array<string,array{0:string,1:string,2:array<string,mixed>,3:string,4:list<string>,5:list<string>}>
	 */
	private static function render_cases(): array {
		$none = array();
		return array(
			'user.id'                 => array( 'user', 'user.id', $none, 'user', array( '@id' ), $none ),
			'user.username'           => array( 'user', 'user.username', $none, 'user', array( 'rendu' ), $none ),
			'user.display_name'       => array( 'user', 'user.display_name', $none, 'user', array( 'Render User' ), $none ),
			'user.first_name'         => array( 'user', 'user.first_name', $none, 'user', array( 'Ada' ), $none ),
			'user.last_name'          => array( 'user', 'user.last_name', $none, 'user', array( 'Lovelace' ), $none ),
			'user.nickname'           => array( 'user', 'user.nickname', $none, 'user', array( 'Countess' ), $none ),
			'user.email'              => array( 'user', 'user.email', $none, 'user', array( 'href="mailto:ada@example.org"', '>ada@example.org</a>' ), $none ),
			'user.website'            => array( 'user', 'user.website', $none, 'user', array( 'href="https://example.org/ada"', 'rel="noopener noreferrer"' ), $none ),
			'user.registered custom'  => array( 'user', 'user.registered', array( 'format' => 'custom', 'custom_format' => 'Y/m/d H:i' ), 'user', array( '2021/03/04 05:06' ), $none ),
			'user.registered wp'      => array( 'user', 'user.registered', $none, 'user', array( '@date:2021-03-04 05:06:07 UTC' ), $none ),
			'user.roles'              => array( 'user', 'user.roles', $none, 'user', array( 'Editor' ), $none ),
			'user.post_count'         => array( 'user', 'user.post_count', array( 'post_type' => 'post' ), 'user', array( '>2</a>', 'author=' ), $none ),
			'user.comment_count'      => array( 'user', 'user.comment_count', $none, 'user', array( '>1</a>', 'user_id=' ), $none ),
			'user.avatar'             => array( 'user', 'user.avatar', array( 'size' => 48 ), 'user', array( '<img', 'avatar-48' ), $none ),
			'user.description'        => array( 'user', 'user.description', $none, 'user', array( 'Bio bold text' ), array( '<b>' ) ),
			'user.meta text'          => array( 'user', 'user.meta', array( 'meta_key' => 'cln_note' ), 'user', array( 'hello x' ), array( '<i>' ) ),
			'user.meta number'        => array( 'user', 'user.meta', array( 'meta_key' => 'cln_num', 'display' => 'number' ), 'user', array( '1,234.50' ), $none ),
			'user.meta boolean'       => array( 'user', 'user.meta', array( 'meta_key' => 'cln_flag', 'display' => 'boolean' ), 'user', array( 'dashicons-no-alt' ), $none ),
			'user.meta url'           => array( 'user', 'user.meta', array( 'meta_key' => 'cln_link', 'display' => 'url' ), 'user', array( 'href="https://example.org/x"' ), $none ),
			'user.last_login'         => array( 'user', 'user.last_login', $none, 'user', array( '@date:@1600000000 datetime' ), $none ),
			'user.capabilities'       => array( 'user', 'user.capabilities', $none, 'user', array( '<span title="', ' capabilities</span>' ), $none ),
			'user.rich_editing'       => array( 'user', 'user.rich_editing', $none, 'user', array( 'No' ), $none ),
			'user.admin_color'        => array( 'user', 'user.admin_color', $none, 'user', array( 'sunrise' ), $none ),
			'user.locale'             => array( 'user', 'user.locale', $none, 'user', array( 'de_DE' ), $none ),

			'comment.id'              => array( 'comment', 'comment.id', $none, 'comment', array( '@id' ), $none ),
			'comment.author'          => array( 'comment', 'comment.author', $none, 'comment', array( 'Grace &lt;script&gt;alert(1)&lt;/script&gt;' ), array( '<script' ) ),
			'comment.author_email'    => array( 'comment', 'comment.author_email', $none, 'comment', array( 'href="mailto:grace@example.org"' ), $none ),
			'comment.author_url'      => array( 'comment', 'comment.author_url', $none, 'comment', array( 'href="https://example.org/g"' ), $none ),
			'comment.author_ip'       => array( 'comment', 'comment.author_ip', $none, 'comment', array( '10.1.2.3' ), $none ),
			'comment.date'            => array( 'comment', 'comment.date', array( 'format' => 'custom', 'custom_format' => 'Y-m-d H:i' ), 'comment', array( '2022-05-06 07:08' ), $none ),
			'comment.status'          => array( 'comment', 'comment.status', $none, 'comment', array( 'Approved' ), $none ),
			'comment.type'            => array( 'comment', 'comment.type', $none, 'comment', array( 'Comment' ), $none ),
			'comment.parent'          => array( 'comment', 'comment.parent', $none, 'comment', array( 'Parent Person (#', 'href=' ), $none ),
			'comment.post_title'      => array( 'comment', 'comment.post_title', $none, 'comment', array( 'Hello Post' ), $none ),
			'comment.excerpt'         => array( 'comment', 'comment.excerpt', array( 'words' => 3 ), 'comment', array( 'one two three&hellip;' ), array( 'four' ) ),
			'comment.word_count'      => array( 'comment', 'comment.word_count', $none, 'comment', array( '@exact:6' ), $none ),
			'comment.user_agent'      => array( 'comment', 'comment.user_agent', $none, 'comment', array( 'UA/1.0 &lt;x&gt;' ), array( '<x>' ) ),
			'comment.meta'            => array( 'comment', 'comment.meta', array( 'meta_key' => 'cln_note' ), 'comment', array( 'note' ), $none ),

			'term.id'                 => array( 'term', 'term.id', $none, 'term', array( '@id' ), $none ),
			'term.name'               => array( 'term', 'term.name', $none, 'term', array( 'Child Cat' ), $none ),
			'term.slug'               => array( 'term', 'term.slug', $none, 'term', array( 'child-cat' ), $none ),
			'term.description'        => array( 'term', 'term.description', $none, 'term', array( 'A desc here' ), array( '<b>' ) ),
			'term.post_count'         => array( 'term', 'term.post_count', $none, 'term', array( '@exact:4' ), $none ),
			'term.parent'             => array( 'term', 'term.parent', $none, 'term', array( 'Parent Cat' ), $none ),
			'term.meta'               => array( 'term', 'term.meta', array( 'meta_key' => 'cln_note' ), 'term', array( 'tm' ), $none ),
			'term.link'               => array( 'term', 'term.link', $none, 'term', array( '/category/parent-cat/child-cat/' ), $none ),
			'term.depth'              => array( 'term', 'term.depth', $none, 'term', array( '@exact:1' ), $none ),

			'media.file_name'         => array( 'media', 'media.file_name', $none, 'media', array( 'photo.jpg' ), $none ),
			'media.file_size'         => array( 'media', 'media.file_size', $none, 'media', array( '2 KB' ), $none ),
			'media.mime_type'         => array( 'media', 'media.mime_type', $none, 'media', array( 'image/jpeg' ), $none ),
			'media.dimensions'        => array( 'media', 'media.dimensions', $none, 'media', array( '1024 × 768' ), $none ),
			'media.width'             => array( 'media', 'media.width', $none, 'media', array( '1,024 px' ), $none ),
			'media.height'            => array( 'media', 'media.height', $none, 'media', array( '768 px' ), $none ),
			'media.alt'               => array( 'media', 'media.alt', $none, 'media', array( 'Alt &lt;img src=x onerror=alert(1)&gt;' ), array( '<img' ) ),
			'media.caption'           => array( 'media', 'media.caption', $none, 'media', array( 'A caption' ), $none ),
			'media.description'       => array( 'media', 'media.description', $none, 'media', array( 'A description text' ), array( '<i>' ) ),
			'media.url'               => array( 'media', 'media.url', $none, 'media', array( 'photo.jpg</a>', 'href="http' ), $none ),
			'media.attached_to'       => array( 'media', 'media.attached_to', $none, 'media', array( '>Gallery Post</a>' ), $none ),
			'media.uploaded_by'       => array( 'media', 'media.uploaded_by', $none, 'media', array( '>Shooter</a>' ), $none ),
			'media.exif_camera'       => array( 'media', 'media.exif_camera', $none, 'media', array( '@exact:Canon EOS' ), $none ),
			'media.exif_aperture'     => array( 'media', 'media.exif_aperture', $none, 'media', array( '@exact:f/2.8' ), $none ),
			'media.exif_iso'          => array( 'media', 'media.exif_iso', $none, 'media', array( '@exact:400' ), $none ),
			'media.exif_shutter'      => array( 'media', 'media.exif_shutter', $none, 'media', array( '@exact:1/250 s' ), $none ),
			'media.exif_focal_length' => array( 'media', 'media.exif_focal_length', $none, 'media', array( '@exact:50 mm' ), $none ),
			'media.exif_created'      => array( 'media', 'media.exif_created', array( 'format' => 'custom', 'custom_format' => 'Y-m-d H:i' ), 'media', array( '@exact:2020-09-13 12:26' ), $none ),
			'media.sizes'             => array( 'media', 'media.sizes', $none, 'media', array( '@exact:full (1024×768), thumbnail (150×150)' ), $none ),
			'media.featured_count'    => array( 'media', 'media.featured_count', $none, 'media', array( '@exact:2' ), $none ),
		);
	}

	/**
	 * @dataProvider render_keys
	 */
	public function test_render( string $case ): void {
		$def = self::render_cases()[ $case ];
		list( $kind, $key, $settings, $fixture, $contains, $not_contains ) = $def;

		$ids = match ( $fixture ) {
			'user'    => $this->user_fixture(),
			'comment' => $this->comment_fixture(),
			'term'    => $this->term_fixture(),
			default   => $this->media_fixture(),
		};
		$id   = $ids[ $fixture ];
		$html = $this->render( $kind, $key, $id, $settings );

		$this->assertNotSame( $this->dash(), $html, "$case rendered empty" );
		foreach ( $contains as $needle ) {
			if ( '@id' === $needle ) {
				$this->assertSame( (string) $id, $html, $case );
			} elseif ( str_starts_with( $needle, '@exact:' ) ) {
				$this->assertSame( substr( $needle, 7 ), $html, $case );
			} elseif ( str_starts_with( $needle, '@date:' ) ) {
				$spec     = substr( $needle, 6 );
				$datetime = str_ends_with( $spec, ' datetime' );
				$spec     = $datetime ? substr( $spec, 0, -9 ) : $spec;
				$ts       = (int) strtotime( $spec );
				$format   = (string) get_option( 'date_format' ) . ( $datetime ? ' ' . get_option( 'time_format' ) : '' );
				$this->assertSame( esc_html( wp_date( $format, $ts ) ), $html, $case );
			} else {
				$this->assertStringContainsString( $needle, $html, $case );
			}
		}
		foreach ( $not_contains as $needle ) {
			$this->assertStringNotContainsString( $needle, $html, "$case is escaped" );
		}
	}

	/**
	 * @return array<string,array{0:string,1:string,2:array<string,mixed>}>
	 */
	public static function empty_cases(): array {
		$none  = array();
		$cases = array(
			'user'    => array( 'user.first_name', 'user.last_name', 'user.description', 'user.locale', 'user.website', 'user.last_login', 'user.meta', 'user.roles' ),
			'comment' => array( 'comment.author_url', 'comment.author_ip', 'comment.parent', 'comment.user_agent', 'comment.excerpt', 'comment.meta', 'comment.author_email' ),
			'term'    => array( 'term.description', 'term.parent', 'term.meta' ),
			'media'   => array(
				'media.file_name', 'media.file_size', 'media.width', 'media.height', 'media.dimensions', 'media.alt', 'media.caption',
				'media.description', 'media.attached_to', 'media.uploaded_by', 'media.exif_camera', 'media.exif_aperture', 'media.exif_iso',
				'media.exif_shutter', 'media.exif_focal_length', 'media.exif_created', 'media.sizes',
			),
		);
		$out = array();
		foreach ( $cases as $kind => $keys ) {
			foreach ( $keys as $key ) {
				$out[ $key ] = array( $kind, $key, $none );
			}
		}
		return $out;
	}

	/**
	 * @dataProvider empty_cases
	 * @param array<string,mixed> $settings
	 */
	public function test_empty_value_renders_em_dash( string $kind, string $key, array $settings ): void {
		switch ( $kind ) {
			case 'user':
				$id = self::factory()->user->create( array( 'role' => '' ) );
				break;
			case 'comment':
				$id = self::factory()->comment->create(
					array(
						'comment_post_ID'      => self::factory()->post->create(),
						'comment_content'      => '',
						'comment_author_email' => '',
						'comment_author_url'   => '',
					)
				);
				break;
			case 'term':
				$id = self::factory()->category->create( array( 'description' => '' ) );
				break;
			default:
				$id = $this->attachment( array( 'post_author' => 0 ) );
				break;
		}
		$html = $this->render( $kind, $key, $id, $settings );
		$this->assertSame( $this->dash(), $html, $key );
	}

	public function test_missing_object_renders_em_dash(): void {
		foreach ( array( 'user.username', 'comment.author', 'term.name', 'media.file_name' ) as $key ) {
			$kind = explode( '.', $key )[0];
			$this->assertSame( $this->dash(), $this->render( $kind, $key, 99999999 ), $key );
		}
	}

	public function test_media_url_is_only_a_link_when_a_file_exists(): void {
		$id = $this->attachment( array(), 'linked.jpg', 10 );
		$this->assertStringContainsString( 'linked.jpg</a>', $this->render( 'media', 'media.url', $id ) );
	}

	// Media size cache.

	public function test_file_size_is_cached_in_meta_and_refreshed_on_metadata_update(): void {
		$id = $this->attachment( array(), 'cache.jpg', 321 );
		$this->assertFalse( metadata_exists( 'post', $id, MediaMeta::FILESIZE ) );
		$this->assertSame( 321, MediaMeta::filesize( $id ) );
		$this->assertSame( '321', get_post_meta( $id, MediaMeta::FILESIZE, true ) );

		wp_update_attachment_metadata( $id, array( 'filesize' => 999 ) );
		$this->assertFalse( metadata_exists( 'post', $id, MediaMeta::FILESIZE ) );
		$this->assertSame( 999, MediaMeta::filesize( $id ) );
	}

	public function test_missing_file_size_is_cached_as_empty_and_not_recomputed(): void {
		$id = $this->attachment();
		$this->assertNull( MediaMeta::filesize( $id ) );
		$this->assertTrue( metadata_exists( 'post', $id, MediaMeta::FILESIZE ) );
		$this->assertSame( '', get_post_meta( $id, MediaMeta::FILESIZE, true ) );
	}

	// Sorting.

	/**
	 * @param array<string,mixed> $settings
	 * @param list<int>           $ids
	 * @return list<int>
	 */
	private function sorted( string $kind, string $key, array $settings, string $order, array $ids ): array {
		$type   = $this->registry->get( $key );
		$this->assertInstanceOf( Sortable::class, $type, "$key is sortable" );
		$config = $this->config( $key, $settings );
		$screen = $this->screen( $kind );
		$empty  = array(
			'join'    => '',
			'where'   => '',
			'orderby' => '',
			'groupby' => '',
		);
		global $wpdb;

		switch ( $kind ) {
			case 'user':
				$hook = static function ( $query ) use ( $type, $config, $screen, $order, $empty, $wpdb ) {
					$clauses              = $type->sort_clauses( $empty, $config, $order, $screen );
					$query->query_from   .= ' ' . $clauses['join'];
					$query->query_where  .= $clauses['where'];
					$orderby              = trim( $clauses['orderby'] . ", {$wpdb->users}.ID {$order}", ', ' );
					$groupby              = '' !== $clauses['groupby'] ? 'GROUP BY ' . $clauses['groupby'] . ' ' : '';
					$query->query_orderby = $groupby . 'ORDER BY ' . $orderby;
				};
				add_action( 'pre_user_query', $hook );
				$query  = new WP_User_Query(
					array(
						'include' => $ids,
						'fields'  => 'ID',
					)
				);
				$result = $query->get_results();
				remove_action( 'pre_user_query', $hook );
				break;

			case 'comment':
				$query = new WP_Comment_Query();
				$hook  = static function ( $clauses, $q ) use ( $query, $type, $config, $screen, $order, $empty, $wpdb ) {
					if ( $q !== $query ) {
						return $clauses;
					}
					$clauses            += $empty;
					$clauses             = $type->sort_clauses( $clauses, $config, $order, $screen );
					$clauses['orderby']  = trim( $clauses['orderby'] . ", {$wpdb->comments}.comment_ID {$order}", ', ' );
					return $clauses;
				};
				add_filter( 'comments_clauses', $hook, 10, 2 );
				$result = $query->query(
					array(
						'comment__in' => $ids,
						'status'      => array( 'approve', 'hold', 'spam', 'trash' ),
						'orderby'     => 'none',
						'fields'      => 'ids',
						'cache_domain' => uniqid( 'cln', true ),
					)
				);
				remove_filter( 'comments_clauses', $hook, 10 );
				break;

			case 'term':
				$hook = static function ( $clauses ) use ( $type, $config, $screen, $order ) {
					$normalized         = $type->sort_clauses(
						array(
							'join'    => (string) $clauses['join'],
							'where'   => (string) $clauses['where'],
							'orderby' => '',
							'groupby' => '',
						),
						$config,
						$order,
						$screen
					);
					$clauses['join']    = $normalized['join'];
					$clauses['where']   = $normalized['where'];
					$groupby            = '' !== $normalized['groupby'] ? 'GROUP BY ' . $normalized['groupby'] . ' ' : '';
					$clauses['orderby'] = $groupby . 'ORDER BY ' . trim( $normalized['orderby'] . ", t.term_id {$order}", ', ' );
					$clauses['order']   = '';
					return $clauses;
				};
				add_filter( 'terms_clauses', $hook );
				$query  = new WP_Term_Query(
					array(
						'taxonomy'     => 'category',
						'include'      => $ids,
						'hide_empty'   => false,
						'hierarchical' => false,
						'orderby'      => 'none',
						'fields'       => 'ids',
					)
				);
				$result = $query->get_terms();
				remove_filter( 'terms_clauses', $hook );
				break;

			default:
				$hook = static function ( $clauses ) use ( $type, $config, $screen, $order, $wpdb ) {
					$clauses            = $type->sort_clauses( $clauses, $config, $order, $screen );
					$clauses['orderby'] = trim( ( $clauses['orderby'] ?? '' ) . ", {$wpdb->posts}.ID {$order}", ', ' );
					return $clauses;
				};
				add_filter( 'posts_clauses', $hook );
				$query  = new WP_Query(
					array(
						'post_type'      => 'attachment',
						'post_status'    => 'inherit',
						'post__in'       => $ids,
						'orderby'        => 'none',
						'fields'         => 'ids',
						'posts_per_page' => -1,
					)
				);
				$result = $query->posts;
				remove_filter( 'posts_clauses', $hook );
				break;
		}
		return array_map( 'intval', $result );
	}

	/**
	 * Seeds objects and returns the ids with a value in ascending order, then the ids without one.
	 *
	 * @return array{0:list<int>,1:list<int>}
	 */
	private function seed( string $name ): array {
		$u = static fn( array $args = array() ) => (int) self::factory()->user->create( $args );

		switch ( $name ) {
			case 'user.id':
				return array( array( $u(), $u(), $u() ), array() );
			case 'user.username':
				$b = $u( array( 'user_login' => 'cln_b' ) );
				$a = $u( array( 'user_login' => 'cln_a' ) );
				$c = $u( array( 'user_login' => 'cln_c' ) );
				return array( array( $a, $b, $c ), array() );
			case 'user.display_name':
				$b = $u( array( 'display_name' => 'Bob' ) );
				$a = $u( array( 'display_name' => 'Al' ) );
				$c = $u( array( 'display_name' => 'Cy' ) );
				return array( array( $a, $b, $c ), array() );
			case 'user.first_name':
				$z = $u( array( 'first_name' => 'Zed' ) );
				$a = $u( array( 'first_name' => 'Amy' ) );
				$m = $u( array( 'first_name' => 'Mia' ) );
				return array( array( $a, $m, $z ), array( $u() ) );
			case 'user.last_name':
				$z = $u( array( 'last_name' => 'Zed' ) );
				$a = $u( array( 'last_name' => 'Amy' ) );
				$m = $u( array( 'last_name' => 'Mia' ) );
				return array( array( $a, $m, $z ), array( $u() ) );
			case 'user.email':
				$c = $u( array( 'user_email' => 'c@example.org' ) );
				$a = $u( array( 'user_email' => 'a@example.org' ) );
				$b = $u( array( 'user_email' => 'b@example.org' ) );
				return array( array( $a, $b, $c ), array() );
			case 'user.website':
				$b = $u( array( 'user_url' => 'https://b.example.org' ) );
				$a = $u( array( 'user_url' => 'https://a.example.org' ) );
				$c = $u( array( 'user_url' => 'https://c.example.org' ) );
				return array( array( $a, $b, $c ), array( $u(), $u() ) );
			case 'user.registered':
				$b = $u( array( 'user_registered' => '2021-01-01 00:00:00' ) );
				$a = $u( array( 'user_registered' => '2019-06-01 00:00:00' ) );
				$c = $u( array( 'user_registered' => '2023-12-31 23:59:59' ) );
				return array( array( $a, $b, $c ), array() );
			case 'user.post_count':
				$three = $u();
				$one   = $u();
				$two   = $u();
				$zero  = $u();
				self::factory()->post->create_many( 3, array( 'post_author' => $three ) );
				self::factory()->post->create_many( 1, array( 'post_author' => $one ) );
				self::factory()->post->create_many( 2, array( 'post_author' => $two ) );
				// A draft and a page do not count towards published posts.
				self::factory()->post->create( array( 'post_author' => $zero, 'post_status' => 'draft' ) );
				self::factory()->post->create( array( 'post_author' => $zero, 'post_type' => 'page' ) );
				return array( array( $zero, $one, $two, $three ), array() );
			case 'user.comment_count':
				$post  = self::factory()->post->create();
				$two   = $u();
				$one   = $u();
				$zero  = $u();
				$three = $u();
				foreach ( array( $three => 3, $one => 1, $two => 2 ) as $user => $n ) {
					for ( $i = 0; $i < $n; $i++ ) {
						self::factory()->comment->create(
							array(
								'user_id'         => $user,
								'comment_post_ID' => $post,
							)
						);
					}
				}
				// A pending comment does not count.
				self::factory()->comment->create(
					array(
						'user_id'          => $zero,
						'comment_post_ID'  => $post,
						'comment_approved' => '0',
					)
				);
				return array( array( $zero, $one, $two, $three ), array() );
			case 'user.meta text':
				$b = $u();
				$a = $u();
				$c = $u();
				update_user_meta( $b, 'cln_val', 'banana' );
				update_user_meta( $a, 'cln_val', 'apple' );
				update_user_meta( $c, 'cln_val', 'cherry' );
				$blank = $u();
				$gone  = $u();
				update_user_meta( $blank, 'cln_val', '' );
				return array( array( $a, $b, $c ), array( $blank, $gone ) );
			case 'user.meta number':
				$a = $u();
				$b = $u();
				$c = $u();
				update_user_meta( $a, 'cln_val', '100' );
				update_user_meta( $b, 'cln_val', '9' );
				update_user_meta( $c, 'cln_val', '10.5' );
				return array( array( $b, $c, $a ), array( $u() ) );
			case 'user.last_login':
				$a = $u();
				$b = $u();
				$c = $u();
				update_user_meta( $a, 'last_login', '1700000300' );
				update_user_meta( $b, 'last_login', '1700000100' );
				update_user_meta( $c, 'last_login', '1700000200' );
				return array( array( $b, $c, $a ), array( $u() ) );

			case 'comment.id':
				$post = self::factory()->post->create();
				$ids  = array();
				for ( $i = 0; $i < 3; $i++ ) {
					$ids[] = self::factory()->comment->create( array( 'comment_post_ID' => $post ) );
				}
				return array( $ids, array() );
			case 'comment.author':
				$b = $this->comment( array( 'comment_author' => 'Bob' ) );
				$a = $this->comment( array( 'comment_author' => 'Al' ) );
				$c = $this->comment( array( 'comment_author' => 'Cy' ) );
				return array( array( $a, $b, $c ), array( $this->comment( array( 'comment_author' => '' ) ) ) );
			case 'comment.author_email':
				$b = $this->comment( array( 'comment_author_email' => 'b@example.org' ) );
				$a = $this->comment( array( 'comment_author_email' => 'a@example.org' ) );
				$c = $this->comment( array( 'comment_author_email' => 'c@example.org' ) );
				return array( array( $a, $b, $c ), array( $this->comment( array( 'comment_author_email' => '' ) ) ) );
			case 'comment.author_ip':
				$b = $this->comment( array( 'comment_author_IP' => '2.2.2.2' ) );
				$a = $this->comment( array( 'comment_author_IP' => '1.1.1.1' ) );
				$c = $this->comment( array( 'comment_author_IP' => '3.3.3.3' ) );
				return array( array( $a, $b, $c ), array( $this->comment( array( 'comment_author_IP' => '' ) ) ) );
			case 'comment.date':
				$b = $this->comment(
					array(
						'comment_date'     => '2021-01-01 00:00:00',
						'comment_date_gmt' => '2021-01-01 00:00:00',
					)
				);
				$a = $this->comment(
					array(
						'comment_date'     => '2019-01-01 00:00:00',
						'comment_date_gmt' => '2019-01-01 00:00:00',
					)
				);
				$c = $this->comment(
					array(
						'comment_date'     => '2023-01-01 00:00:00',
						'comment_date_gmt' => '2023-01-01 00:00:00',
					)
				);
				return array( array( $a, $b, $c ), array() );
			case 'comment.status':
				$spam = $this->comment( array( 'comment_approved' => 'spam' ) );
				$ok   = $this->comment( array( 'comment_approved' => '1' ) );
				$hold = $this->comment( array( 'comment_approved' => '0' ) );
				return array( array( $hold, $ok, $spam ), array() );
			case 'comment.type':
				$t = $this->comment( array( 'comment_type' => 'trackback' ) );
				$c = $this->comment( array( 'comment_type' => 'comment' ) );
				$p = $this->comment( array( 'comment_type' => 'pingback' ) );
				return array( array( $c, $p, $t ), array() );
			case 'comment.post_title':
				$posts = array(
					'Beta'  => self::factory()->post->create( array( 'post_title' => 'Beta' ) ),
					'Alpha' => self::factory()->post->create( array( 'post_title' => 'Alpha' ) ),
					'Gamma' => self::factory()->post->create( array( 'post_title' => 'Gamma' ) ),
				);
				$b     = $this->comment( array( 'comment_post_ID' => $posts['Beta'] ) );
				$a     = $this->comment( array( 'comment_post_ID' => $posts['Alpha'] ) );
				$g     = $this->comment( array( 'comment_post_ID' => $posts['Gamma'] ) );
				$blank = $this->comment( array( 'comment_post_ID' => self::factory()->post->create( array( 'post_title' => '' ) ) ) );
				return array( array( $a, $b, $g ), array( $blank ) );
			case 'comment.meta':
				$b = $this->comment();
				$a = $this->comment();
				$c = $this->comment();
				update_comment_meta( $b, 'cln_val', 'banana' );
				update_comment_meta( $a, 'cln_val', 'apple' );
				update_comment_meta( $c, 'cln_val', 'cherry' );
				return array( array( $a, $b, $c ), array( $this->comment(), $this->comment() ) );

			case 'term.id':
				$f = self::factory()->category;
				return array( array( $f->create(), $f->create(), $f->create() ), array() );
			case 'term.name':
				$f = self::factory()->category;
				$z = $f->create( array( 'name' => 'Zeta' ) );
				$a = $f->create( array( 'name' => 'Alpha' ) );
				$m = $f->create( array( 'name' => 'Mid' ) );
				return array( array( $a, $m, $z ), array() );
			case 'term.slug':
				$f = self::factory()->category;
				$z = $f->create( array( 'slug' => 'zz-slug' ) );
				$a = $f->create( array( 'slug' => 'aa-slug' ) );
				$m = $f->create( array( 'slug' => 'mm-slug' ) );
				return array( array( $a, $m, $z ), array() );
			case 'term.post_count':
				global $wpdb;
				$f     = self::factory()->category;
				$ids   = array(
					5 => $f->create(),
					1 => $f->create(),
					3 => $f->create(),
					0 => $f->create(),
				);
				foreach ( $ids as $count => $term ) {
					$wpdb->update( $wpdb->term_taxonomy, array( 'count' => $count ), array( 'term_id' => $term ) );
				}
				clean_term_cache( array_values( $ids ), 'category' );
				return array( array( $ids[0], $ids[1], $ids[3], $ids[5] ), array() );
			case 'term.parent':
				$f      = self::factory()->category;
				$apple  = $f->create( array( 'name' => 'Apple' ) );
				$banana = $f->create( array( 'name' => 'Banana' ) );
				$c1     = $f->create( array( 'parent' => $banana ) );
				$c2     = $f->create( array( 'parent' => $apple ) );
				return array( array( $c2, $c1 ), array( $f->create() ) );
			case 'term.meta':
				$f = self::factory()->category;
				$b = $f->create();
				$a = $f->create();
				$c = $f->create();
				update_term_meta( $b, 'cln_val', 'banana' );
				update_term_meta( $a, 'cln_val', 'apple' );
				update_term_meta( $c, 'cln_val', 'cherry' );
				return array( array( $a, $b, $c ), array( $f->create(), $f->create() ) );

			case 'media.file_name':
				$b = $this->attachment( array(), 'b-file.jpg', 1 );
				$a = $this->attachment( array(), 'a-file.png', 1 );
				$c = $this->attachment( array(), 'c-file.gif', 1 );
				return array( array( $a, $b, $c ), array( $this->attachment() ) );
			case 'media.file_size':
				$s300 = $this->attachment( array(), 'm300.jpg', 300 );
				$s100 = $this->attachment( array(), 'm100.jpg', 100 );
				$s200 = $this->attachment( array(), 'm200.jpg', 200 );
				$s150 = $this->attachment();
				update_post_meta( $s150, '_wp_attachment_metadata', array( 'filesize' => 150 ) );
				return array( array( $s100, $s150, $s200, $s300 ), array( $this->attachment() ) );
			case 'media.mime_type':
				$png  = $this->attachment( array( 'post_mime_type' => 'image/png' ) );
				$gif  = $this->attachment( array( 'post_mime_type' => 'image/gif' ) );
				$jpg  = $this->attachment( array( 'post_mime_type' => 'image/jpeg' ) );
				$none = $this->attachment( array( 'post_mime_type' => '' ) );
				return array( array( $gif, $jpg, $png ), array( $none ) );
			case 'media.width':
			case 'media.height':
				$dim = 'media.width' === $name ? 'width' : 'height';
				$ids = array();
				foreach ( array( 800, 1600, 100 ) as $px ) {
					$id = $this->attachment();
					wp_update_attachment_metadata( $id, array( $dim => $px ) );
					$ids[ $px ] = $id;
				}
				$gone = $this->attachment();
				return array( array( $ids[100], $ids[800], $ids[1600] ), array( $gone ) );
			case 'media.alt':
				$b = $this->attachment();
				$a = $this->attachment();
				$c = $this->attachment();
				update_post_meta( $b, '_wp_attachment_image_alt', 'banana' );
				update_post_meta( $a, '_wp_attachment_image_alt', 'apple' );
				update_post_meta( $c, '_wp_attachment_image_alt', 'cherry' );
				return array( array( $a, $b, $c ), array( $this->attachment() ) );
			case 'media.attached_to':
				$p = array();
				foreach ( array( 'Beta', 'Alpha', 'Gamma' ) as $title ) {
					$p[ $title ] = self::factory()->post->create( array( 'post_title' => $title ) );
				}
				$b = $this->attachment( array( 'post_parent' => $p['Beta'] ) );
				$a = $this->attachment( array( 'post_parent' => $p['Alpha'] ) );
				$g = $this->attachment( array( 'post_parent' => $p['Gamma'] ) );
				return array( array( $a, $b, $g ), array( $this->attachment() ) );
			case 'media.uploaded_by':
				$b = $this->attachment( array( 'post_author' => $u( array( 'display_name' => 'Bob' ) ) ) );
				$a = $this->attachment( array( 'post_author' => $u( array( 'display_name' => 'Al' ) ) ) );
				$c = $this->attachment( array( 'post_author' => $u( array( 'display_name' => 'Cy' ) ) ) );
				return array( array( $a, $b, $c ), array( $this->attachment( array( 'post_author' => 0 ) ) ) );
		}
		$this->fail( "No seeder for $name" );
	}

	/**
	 * @param array<string,mixed> $args
	 */
	private function comment( array $args = array() ): int {
		static $post = 0;
		if ( ! $post || ! get_post( $post ) ) {
			$post = self::factory()->post->create();
		}
		$args += array(
			'comment_post_ID'  => $post,
			'comment_approved' => '1',
			'comment_type'     => 'comment',
		);
		return (int) self::factory()->comment->create( $args );
	}

	/**
	 * @return array<string,array{0:string,1:string,2:array<string,mixed>}>
	 */
	public static function sort_cases(): array {
		$none = array();
		$out  = array();
		foreach ( array(
			'user.id', 'user.username', 'user.display_name', 'user.first_name', 'user.last_name', 'user.email', 'user.website',
			'user.registered', 'user.post_count', 'user.comment_count', 'user.last_login',
			'comment.id', 'comment.author', 'comment.author_email', 'comment.author_ip', 'comment.date', 'comment.status', 'comment.type',
			'comment.post_title', 'term.id', 'term.name', 'term.slug', 'term.post_count', 'term.parent',
			'media.file_name', 'media.file_size', 'media.mime_type', 'media.width', 'media.height', 'media.alt', 'media.attached_to',
			'media.uploaded_by',
		) as $name ) {
			$out[ $name ] = array( $name, $name, 'user.post_count' === $name ? array( 'post_type' => 'post' ) : $none );
		}
		foreach ( array( 'user', 'comment', 'term' ) as $kind ) {
			$out[ "$kind.meta" ] = array( "$kind.meta", "$kind.meta", array( 'meta_key' => 'cln_val' ) );
		}
		$out['user.meta text']   = array( 'user.meta text', 'user.meta', array( 'meta_key' => 'cln_val' ) );
		$out['user.meta number'] = array( 'user.meta number', 'user.meta', array( 'meta_key' => 'cln_val', 'display' => 'number' ) );
		unset( $out['user.meta'] );
		return $out;
	}

	/**
	 * @dataProvider sort_cases
	 * @param array<string,mixed> $settings
	 */
	public function test_sorts_in_sql_with_empty_values_last( string $seed, string $key, array $settings ): void {
		$kind = explode( '.', $key )[0];
		list( $valued, $empty ) = $this->seed( $seed );
		$this->assertGreaterThanOrEqual( 2, count( $valued ), 'seed has values to order' );

		$all  = array_merge( $valued, $empty );
		$asc  = $this->sorted( $kind, $key, $settings, 'ASC', $all );
		$desc = $this->sorted( $kind, $key, $settings, 'DESC', $all );

		$this->assertCount( count( $all ), $asc, 'ASC returns every seeded row' );
		$this->assertCount( count( $all ), $desc, 'DESC returns every seeded row' );
		$this->assertSame( $valued, array_slice( $asc, 0, count( $valued ) ), "$seed ASC" );
		$this->assertSame( array_reverse( $valued ), array_slice( $desc, 0, count( $valued ) ), "$seed DESC" );

		$empty_asc  = array_slice( $asc, count( $valued ) );
		$empty_desc = array_slice( $desc, count( $valued ) );
		sort( $empty_asc );
		sort( $empty_desc );
		$sorted_empty = $empty;
		sort( $sorted_empty );
		$this->assertSame( $sorted_empty, $empty_asc, "$seed ASC empties last" );
		$this->assertSame( $sorted_empty, $empty_desc, "$seed DESC empties last" );
	}

	public function test_media_sort_backfills_the_size_cache_for_attachments_never_rendered(): void {
		list( $valued ) = $this->seed( 'media.file_size' );
		foreach ( $valued as $id ) {
			$this->assertFalse( metadata_exists( 'post', $id, MediaMeta::FILESIZE ) );
		}
		$this->sorted( 'media', 'media.file_size', array(), 'ASC', $valued );
		foreach ( $valued as $id ) {
			$this->assertTrue( metadata_exists( 'post', $id, MediaMeta::FILESIZE ) );
		}
	}

	public function test_sorting_the_same_type_twice_in_one_set_does_not_collide(): void {
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		update_user_meta( $u1, 'cln_a', 'x' );
		update_user_meta( $u2, 'cln_a', 'y' );
		update_user_meta( $u1, 'cln_b', 'y' );
		update_user_meta( $u2, 'cln_b', 'x' );
		$this->assertSame( array( $u1, $u2 ), $this->sorted( 'user', 'user.meta', array( 'meta_key' => 'cln_a' ), 'ASC', array( $u1, $u2 ) ) );
		$this->assertSame( array( $u2, $u1 ), $this->sorted( 'user', 'user.meta', array( 'meta_key' => 'cln_b' ), 'ASC', array( $u1, $u2 ) ) );
	}

	public function test_non_sortable_types_do_not_implement_sortable(): void {
		foreach ( array( 'user.nickname', 'user.roles', 'user.avatar', 'user.description', 'user.capabilities', 'user.rich_editing', 'media.dimensions', 'media.caption', 'media.url', 'media.sizes', 'media.featured_count', 'media.exif_iso', 'comment.author_url', 'comment.parent', 'comment.excerpt', 'comment.word_count', 'comment.user_agent', 'term.description', 'term.link', 'term.depth' ) as $key ) {
			$this->assertNotInstanceOf( Sortable::class, $this->registry->get( $key ), $key );
		}
	}
}
