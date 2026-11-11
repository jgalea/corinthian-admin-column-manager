<?php

namespace Corinthian\Columns\Types;

use Corinthian\Columns\ColumnType;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Post columns that need no settings of their own.
 */
final class CoreFieldTypes {

	/**
	 * @return list<ColumnType>
	 */
	public static function all(): array {
		$supports = static fn( string $feature ) => static fn( string $pt ): bool => post_type_supports( $pt, $feature );

		return array(
			new SortablePostFieldType( 'post.id', static fn() => __( 'ID', 'corinthian-admin-column-manager' ), 'post', static fn( WP_Post $p ) => $p->ID, 'ID', PostFilter::number( 'ID', false ) ),
			new EditableSortablePostFieldType( 'slug', 'post.slug', static fn() => __( 'Slug', 'corinthian-admin-column-manager' ), 'post', static fn( WP_Post $p ) => $p->post_name, 'post_name', PostFilter::text( 'post_name' ), true ),
			new EditableSortablePostFieldType(
				'status',
				'post.status',
				static fn() => __( 'Status', 'corinthian-admin-column-manager' ),
				'post',
				static function ( WP_Post $p ) {
					$obj = get_post_status_object( $p->post_status );
					return $obj ? $obj->label : $p->post_status;
				},
				'post_status',
				PostFilter::in(
					'post_status',
					static function () {
						$options = array();
						foreach ( get_post_stati( array(), 'objects' ) as $name => $status ) {
							if ( ! in_array( $name, array( 'auto-draft', 'inherit' ), true ) ) {
								$options[ $name ] = $status->label;
							}
						}
						return $options;
					}
				)
			),
			new EditableSortablePostFieldType( 'menu_order', 'post.menu_order', static fn() => __( 'Menu order', 'corinthian-admin-column-manager' ), 'hierarchy', static fn( WP_Post $p ) => $p->menu_order, 'menu_order', PostFilter::number( 'menu_order' ), false, $supports( 'page-attributes' ) ),
			new SortablePostFieldType(
				'post.comment_count',
				static fn() => __( 'Comment count', 'corinthian-admin-column-manager' ),
				'comments',
				static fn( WP_Post $p ) => (int) $p->comment_count,
				'comment_count',
				PostFilter::number( 'comment_count' ),
				false,
				$supports( 'comments' ),
				static function ( $value, int $id ) {
					$count = (int) $value;
					if ( $count < 1 ) {
						return esc_html( (string) $count );
					}
					return '<a href="' . esc_url( add_query_arg( 'p', $id, admin_url( 'edit-comments.php' ) ) ) . '">' . esc_html( number_format_i18n( $count ) ) . '</a>';
				}
			),
			new PostFieldType(
				'post.visibility',
				static fn() => __( 'Visibility', 'corinthian-admin-column-manager' ),
				'post',
				static function ( WP_Post $p ) {
					if ( '' !== $p->post_password ) {
						return __( 'Password protected', 'corinthian-admin-column-manager' );
					}
					return 'private' === $p->post_status ? __( 'Private', 'corinthian-admin-column-manager' ) : __( 'Public', 'corinthian-admin-column-manager' );
				}
			),
			new PostFieldType(
				'post.type',
				static fn() => __( 'Post type', 'corinthian-admin-column-manager' ),
				'post',
				static function ( WP_Post $p ) {
					$obj = get_post_type_object( $p->post_type );
					return $obj ? $obj->labels->singular_name : $p->post_type;
				}
			),
			new PostFieldType( 'post.guid', static fn() => __( 'GUID', 'corinthian-admin-column-manager' ), 'post', static fn( WP_Post $p ) => $p->guid ),
			new PostFieldType(
				'post.shortlink',
				static fn() => __( 'Shortlink', 'corinthian-admin-column-manager' ),
				'post',
				static fn( WP_Post $p ) => is_post_type_viewable( $p->post_type ) ? wp_get_shortlink( $p->ID ) : null
			),
			new PostFieldType(
				'post.relative_path',
				static fn() => __( 'Relative path', 'corinthian-admin-column-manager' ),
				'post',
				static fn( WP_Post $p ) => is_post_type_viewable( $p->post_type ) ? wp_make_link_relative( (string) get_permalink( $p ) ) : null
			),
			new PostFieldType(
				'post.path_depth',
				static fn() => __( 'Path depth', 'corinthian-admin-column-manager' ),
				'hierarchy',
				static function ( WP_Post $p ) {
					if ( ! is_post_type_viewable( $p->post_type ) ) {
						return null;
					}
					$path = trim( (string) wp_parse_url( (string) get_permalink( $p ), PHP_URL_PATH ), '/' );
					$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
					if ( '' !== $home && str_starts_with( $path, $home ) ) {
						$path = trim( substr( $path, strlen( $home ) ), '/' );
					}
					return '' === $path ? null : count( explode( '/', $path ) );
				}
			),
			new EditableFilterablePostFieldType(
				'template',
				'post.template',
				static fn() => __( 'Page template', 'corinthian-admin-column-manager' ),
				'post',
				static function ( WP_Post $p ) {
					$slug = get_page_template_slug( $p );
					if ( ! $slug ) {
						return null;
					}
					$names = array_flip( wp_get_theme()->get_page_templates( $p, $p->post_type ) );
					return $names[ $slug ] ?? $slug;
				},
				PostFilter::template(),
				static fn( string $pt ) => post_type_supports( $pt, 'page-attributes' ) || array() !== wp_get_theme()->get_page_templates( null, $pt )
			),
			new EditableFilterablePostFieldType( 'comment_status', 'post.comment_status', static fn() => __( 'Comment status', 'corinthian-admin-column-manager' ), 'comments', static fn( WP_Post $p ) => 'open' === $p->comment_status ? __( 'Open', 'corinthian-admin-column-manager' ) : __( 'Closed', 'corinthian-admin-column-manager' ), PostFilter::in( 'comment_status', self::open_closed( ... ) ), $supports( 'comments' ) ),
			new EditableFilterablePostFieldType( 'ping_status', 'post.ping_status', static fn() => __( 'Ping status', 'corinthian-admin-column-manager' ), 'comments', static fn( WP_Post $p ) => 'open' === $p->ping_status ? __( 'Open', 'corinthian-admin-column-manager' ) : __( 'Closed', 'corinthian-admin-column-manager' ), PostFilter::in( 'ping_status', self::open_closed( ... ) ), $supports( 'trackbacks' ) ),
			new FilterablePostFieldType(
				'post.last_modified_by',
				static fn() => __( 'Last modified by', 'corinthian-admin-column-manager' ),
				'author',
				static fn( WP_Post $p ) => (int) get_post_meta( $p->ID, '_edit_last', true ) ? self::user_name( (int) get_post_meta( $p->ID, '_edit_last', true ) ) : null,
				PostFilter::meta_ids( '_edit_last', static fn() => PostFilter::author_options() )
			),
			new PostFieldType(
				'post.edit_lock',
				static fn() => __( 'Being edited by', 'corinthian-admin-column-manager' ),
				'author',
				static function ( WP_Post $p ) {
					$parts = explode( ':', (string) get_post_meta( $p->ID, '_edit_lock', true ) );
					if ( 2 !== count( $parts ) ) {
						return null;
					}
					$window = (int) apply_filters( 'wp_check_post_lock_window', 150 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's own filter.
					return time() - (int) $parts[0] < $window ? self::user_name( (int) $parts[1] ) : null;
				}
			),
			new PostFieldType(
				'post.char_count',
				static fn() => __( 'Character count', 'corinthian-admin-column-manager' ),
				'content',
				static fn( WP_Post $p ) => mb_strlen( WordCountMeta::plain_text( $p ), 'UTF-8' ),
				$supports( 'editor' ),
				static fn( $value ) => esc_html( number_format_i18n( (int) $value ) )
			),
			new PostFieldType(
				'post.images_count',
				static fn() => __( 'Images in content', 'corinthian-admin-column-manager' ),
				'media',
				static fn( WP_Post $p ) => (int) preg_match_all( '/<img\b/i', $p->post_content ),
				$supports( 'editor' )
			),
			new FilterablePostFieldType(
				'post.attachments_count',
				static fn() => __( 'Attachments', 'corinthian-admin-column-manager' ),
				'media',
				static fn( WP_Post $p ) => ChildCounter::count( $p->ID, 'attachment' ),
				PostFilter::child_count( 'attachment' )
			),
			new FilterablePostFieldType(
				'post.children_count',
				static fn() => __( 'Children', 'corinthian-admin-column-manager' ),
				'hierarchy',
				static fn( WP_Post $p ) => ChildCounter::count( $p->ID, 'children' ),
				PostFilter::child_count( 'children' ),
				static fn( string $pt ) => is_post_type_hierarchical( $pt )
			),
			new FilterablePostFieldType(
				'post.revisions_count',
				static fn() => __( 'Revisions', 'corinthian-admin-column-manager' ),
				'content',
				static fn( WP_Post $p ) => ChildCounter::count( $p->ID, 'revision' ),
				PostFilter::child_count( 'revision' ),
				$supports( 'revisions' )
			),
			new PostFieldType(
				'post.shortcodes',
				static fn() => __( 'Shortcodes used', 'corinthian-admin-column-manager' ),
				'content',
				static function ( WP_Post $p ) {
					if ( ! preg_match_all( '/' . get_shortcode_regex() . '/', $p->post_content, $found, PREG_SET_ORDER ) ) {
						return null;
					}
					$names = array();
					foreach ( $found as $match ) {
						if ( '[' !== $match[1] ) {
							$names[ $match[2] ] = $match[2];
						}
					}
					return array_values( $names );
				},
				$supports( 'editor' ),
				static fn( $value ) => implode( ' ', array_map( static fn( $name ) => '<code>[' . esc_html( (string) $name ) . ']</code>', (array) $value ) )
			),
		);
	}

	/**
	 * @return array<string,string>
	 */
	private static function open_closed(): array {
		return array(
			'open'   => __( 'Open', 'corinthian-admin-column-manager' ),
			'closed' => __( 'Closed', 'corinthian-admin-column-manager' ),
		);
	}

	private static function user_name( int $user_id ): ?string {
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : null;
	}
}
