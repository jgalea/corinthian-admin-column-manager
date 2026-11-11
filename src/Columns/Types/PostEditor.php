<?php

namespace Corinthian\Columns\Types;

use Corinthian\Screens\Screen;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Validation and storage for editing post columns. Every write goes through
 * core APIs so hooks and revisions fire, and checks the capability the change needs.
 */
final class PostEditor {

	public const AUTHOR_LIMIT = 200;
	public const PARENT_LIMIT = 500;
	public const TERM_LIMIT   = 500;

	private const MODES = array(
		'slug'           => array( 'set', 'clear' ),
		'excerpt'        => array( 'set', 'clear' ),
		'status'         => array( 'set' ),
		'author'         => array( 'set' ),
		'date'           => array( 'set' ),
		'comment_status' => array( 'set', 'clear' ),
		'ping_status'    => array( 'set', 'clear' ),
		'sticky'         => array( 'set', 'clear' ),
		'parent'         => array( 'set', 'clear' ),
		'menu_order'     => array( 'set', 'clear' ),
		'template'       => array( 'set', 'clear' ),
		'format'         => array( 'set', 'clear' ),
		'taxonomy'       => array( 'set', 'append', 'remove', 'clear' ),
		'featured_image' => array( 'set', 'clear' ),
	);

	/**
	 * @param array{taxonomy?:string} $extra
	 * @return array<string,mixed>
	 */
	public static function field( string $kind, Screen $screen, array $extra = array() ): array {
		$type  = $screen->subtype();
		$field = array(
			'input' => match ( $kind ) {
				'excerpt'                          => 'textarea',
				'status', 'author', 'parent', 'template', 'format' => 'select',
				'date'                             => 'datetime',
				'comment_status', 'ping_status', 'sticky' => 'toggle',
				'menu_order'                       => 'number',
				'taxonomy'                         => 'multiselect',
				'featured_image'                   => 'media',
				default                            => 'text',
			},
			'bulk'  => self::MODES[ $kind ] ?? array( 'set' ),
		);
		$options = match ( $kind ) {
			'status'   => self::status_options( $type ),
			'author'   => self::author_options( $type ),
			'parent'   => self::parent_options( $type ),
			'template' => self::template_options( $type ),
			'format'   => self::format_options(),
			'taxonomy' => self::term_options( (string) ( $extra['taxonomy'] ?? '' ) ),
			default    => null,
		};
		if ( null !== $options ) {
			$field['options'] = $options;
		}
		return $field;
	}

	/**
	 * @param array{taxonomy?:string} $extra
	 */
	public static function value( string $kind, int $id, array $extra = array() ): mixed {
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}
		return match ( $kind ) {
			'slug'           => $post->post_name,
			'excerpt'        => $post->post_excerpt,
			'status'         => $post->post_status,
			'author'         => (int) $post->post_author,
			'date'           => str_starts_with( $post->post_date, '0000' ) ? '' : substr( $post->post_date, 0, 16 ),
			'comment_status' => 'open' === $post->comment_status,
			'ping_status'    => 'open' === $post->ping_status,
			'sticky'         => is_sticky( $id ),
			'parent'         => (int) $post->post_parent,
			'menu_order'     => (int) $post->menu_order,
			'template'       => get_page_template_slug( $post ) ? get_page_template_slug( $post ) : 'default',
			'format'         => get_post_format( $post ) ? get_post_format( $post ) : 'standard',
			'featured_image' => (int) get_post_thumbnail_id( $post ),
			'taxonomy'       => self::term_ids( $id, (string) ( $extra['taxonomy'] ?? '' ) ),
			default          => null,
		};
	}

	/**
	 * @param array{taxonomy?:string,allow_create?:bool} $extra
	 */
	public static function save( string $kind, int $id, mixed $value, string $mode, array $extra = array() ): bool|WP_Error {
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'corinthian_missing', __( 'This item no longer exists.', 'corinthian-admin-column-manager' ) );
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			return new WP_Error( 'corinthian_forbidden', __( 'You cannot edit this item.', 'corinthian-admin-column-manager' ) );
		}
		if ( ! in_array( $mode, self::MODES[ $kind ] ?? array(), true ) ) {
			return new WP_Error( 'corinthian_bad_mode', __( 'This column does not support that edit.', 'corinthian-admin-column-manager' ) );
		}
		if ( 'clear' !== $mode && ! is_scalar( $value ) && null !== $value && ! ( 'taxonomy' === $kind && is_array( $value ) ) ) {
			return new WP_Error( 'corinthian_invalid', __( 'Invalid value.', 'corinthian-admin-column-manager' ) );
		}
		$clear = 'clear' === $mode;
		$text  = is_scalar( $value ) ? trim( (string) $value ) : '';
		$type  = get_post_type_object( $post->post_type );
		if ( ! $type ) {
			return new WP_Error( 'corinthian_missing', __( 'This item no longer exists.', 'corinthian-admin-column-manager' ) );
		}

		switch ( $kind ) {
			case 'slug':
				$slug = $clear ? '' : sanitize_title( $text );
				if ( ! $clear && '' === $slug ) {
					return new WP_Error( 'corinthian_invalid', __( 'Enter a slug.', 'corinthian-admin-column-manager' ) );
				}
				if ( '' !== $slug ) {
					$slug = wp_unique_post_slug( $slug, $id, $post->post_status, $post->post_type, (int) $post->post_parent );
				}
				return self::update(
					array(
						'ID'        => $id,
						'post_name' => $slug,
					)
				);

			case 'excerpt':
				if ( ! post_type_supports( $post->post_type, 'excerpt' ) ) {
					return new WP_Error( 'corinthian_unsupported', __( 'This content type has no excerpt.', 'corinthian-admin-column-manager' ) );
				}
				$excerpt = $clear ? '' : (string) $value;
				if ( ! current_user_can( 'unfiltered_html' ) ) {
					$excerpt = wp_kses_post( $excerpt );
				}
				return self::update(
					array(
						'ID'           => $id,
						'post_excerpt' => $excerpt,
					)
				);

			case 'status':
				return self::save_status( $post, $type, sanitize_key( $text ) );

			case 'author':
				if ( ! current_user_can( $type->cap->edit_others_posts ) ) {
					return new WP_Error( 'corinthian_forbidden', __( 'You cannot change the author.', 'corinthian-admin-column-manager' ) );
				}
				if ( ! post_type_supports( $post->post_type, 'author' ) ) {
					return new WP_Error( 'corinthian_unsupported', __( 'This content type has no author.', 'corinthian-admin-column-manager' ) );
				}
				$author = ctype_digit( $text ) ? (int) $text : 0;
				if ( ! $author || ! get_userdata( $author ) || ! user_can( $author, $type->cap->edit_posts ) ) {
					return new WP_Error( 'corinthian_invalid', __( 'Choose a user who can edit this content type.', 'corinthian-admin-column-manager' ) );
				}
				return self::update(
					array(
						'ID'          => $id,
						'post_author' => $author,
					)
				);

			case 'date':
				return self::save_date( $post, $text );

			case 'comment_status':
			case 'ping_status':
				$feature = 'comment_status' === $kind ? 'comments' : 'trackbacks';
				if ( ! post_type_supports( $post->post_type, $feature ) ) {
					return new WP_Error( 'corinthian_unsupported', __( 'This content type does not support that setting.', 'corinthian-admin-column-manager' ) );
				}
				$on = ! $clear && self::truthy( $value );
				return self::update(
					array(
						'ID'  => $id,
						$kind => $on ? 'open' : 'closed',
					)
				);

			case 'sticky':
				if ( 'post' !== $post->post_type ) {
					return new WP_Error( 'corinthian_unsupported', __( 'Only posts can be sticky.', 'corinthian-admin-column-manager' ) );
				}
				if ( ! current_user_can( $type->cap->edit_others_posts ) ) {
					return new WP_Error( 'corinthian_forbidden', __( 'You cannot change sticky posts.', 'corinthian-admin-column-manager' ) );
				}
				if ( ! $clear && self::truthy( $value ) ) {
					stick_post( $id );
				} else {
					unstick_post( $id );
				}
				return true;

			case 'parent':
				return self::save_parent( $post, $clear ? 0 : $text );

			case 'menu_order':
				if ( ! post_type_supports( $post->post_type, 'page-attributes' ) ) {
					return new WP_Error( 'corinthian_unsupported', __( 'This content type has no order.', 'corinthian-admin-column-manager' ) );
				}
				if ( ! $clear && ! preg_match( '/^-?\d{1,9}$/', $text ) ) {
					return new WP_Error( 'corinthian_invalid', __( 'Enter a whole number.', 'corinthian-admin-column-manager' ) );
				}
				return self::update(
					array(
						'ID'         => $id,
						'menu_order' => $clear ? 0 : (int) $text,
					)
				);

			case 'template':
				$templates = wp_get_theme()->get_page_templates( $post, $post->post_type );
				$chosen    = $clear || '' === $text ? 'default' : $text;
				if ( 'default' !== $chosen && ! isset( $templates[ $chosen ] ) ) {
					return new WP_Error( 'corinthian_invalid', __( 'That template is not available.', 'corinthian-admin-column-manager' ) );
				}
				return self::update(
					array(
						'ID'            => $id,
						'page_template' => $chosen,
					)
				);

			case 'format':
				if ( ! post_type_supports( $post->post_type, 'post-formats' ) ) {
					return new WP_Error( 'corinthian_unsupported', __( 'This content type has no formats.', 'corinthian-admin-column-manager' ) );
				}
				$format = $clear || '' === $text ? 'standard' : sanitize_key( $text );
				if ( ! isset( self::format_options()[ $format ] ) ) {
					return new WP_Error( 'corinthian_invalid', __( 'That format is not available.', 'corinthian-admin-column-manager' ) );
				}
				$result = set_post_format( $post, $format );
				return is_wp_error( $result ) ? $result : true;

			case 'taxonomy':
				return self::save_terms( $post, $value, $mode, $extra );

			case 'featured_image':
				return self::save_thumbnail( $post, $clear ? '' : $text );
		}
		return new WP_Error( 'corinthian_unsupported', __( 'This column cannot be edited.', 'corinthian-admin-column-manager' ) );
	}

	/**
	 * @param array<string,mixed> $args
	 */
	private static function update( array $args ): bool|WP_Error {
		$result = wp_update_post( wp_slash( $args ), true );
		return is_wp_error( $result ) ? $result : true;
	}

	private static function truthy( mixed $value ): bool {
		return in_array( is_string( $value ) ? strtolower( trim( $value ) ) : $value, array( true, 1, '1', 'true', 'yes', 'on', 'open' ), true );
	}

	private static function save_status( WP_Post $post, \WP_Post_Type $type, string $status ): bool|WP_Error {
		$allowed = self::settable_statuses( $type, $post );
		if ( '' === $status || ! isset( $allowed[ $status ] ) ) {
			return new WP_Error( 'corinthian_invalid', __( 'You cannot set that status.', 'corinthian-admin-column-manager' ) );
		}
		if ( 'future' === $status ) {
			$time = get_post_timestamp( $post, 'date' );
			if ( ! $time || $time <= time() ) {
				return new WP_Error( 'corinthian_invalid', __( 'Set a future publish date before scheduling.', 'corinthian-admin-column-manager' ) );
			}
		}
		return self::update(
			array(
				'ID'          => $post->ID,
				'post_status' => $status,
			)
		);
	}

	/**
	 * @return array<string,string> Status => label the current user may set on this type.
	 */
	private static function settable_statuses( \WP_Post_Type $type, ?WP_Post $post = null ): array {
		$out = array();
		foreach ( get_post_stati( array(), 'objects' ) as $name => $status ) {
			if ( in_array( $name, array( 'auto-draft', 'inherit', 'trash', 'future' ), true ) || $status->internal ) {
				continue;
			}
			// Anything visible to others needs the publish capability; drafts and pending do not.
			if ( ( $status->public || $status->private ) && ! current_user_can( $type->cap->publish_posts ) ) {
				continue;
			}
			$out[ $name ] = (string) $status->label;
		}
		if ( current_user_can( $type->cap->publish_posts ) ) {
			$future = get_post_status_object( 'future' );
			if ( $future && ( ! $post || 'future' === $post->post_status || get_post_timestamp( $post, 'date' ) > time() ) ) {
				$out['future'] = (string) $future->label;
			}
		}
		return $out;
	}

	/**
	 * @return array<string,string>
	 */
	private static function status_options( string $post_type ): array {
		$type = get_post_type_object( $post_type );
		return $type ? self::settable_statuses( $type ) : array();
	}

	private static function save_date( WP_Post $post, string $text ): bool|WP_Error {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i', str_replace( 'T', ' ', $text ), wp_timezone() );
		$err  = \DateTimeImmutable::getLastErrors();
		if ( ! $date || ( $err && ( $err['warning_count'] || $err['error_count'] ) ) || $date->format( 'Y-m-d H:i' ) !== str_replace( 'T', ' ', $text ) ) {
			return new WP_Error( 'corinthian_invalid', __( 'Enter a date and time as YYYY-MM-DD HH:MM.', 'corinthian-admin-column-manager' ) );
		}
		$local = $date->format( 'Y-m-d H:i:s' );
		return self::update(
			array(
				'ID'            => $post->ID,
				'post_date'     => $local,
				'post_date_gmt' => get_gmt_from_date( $local ),
				'edit_date'     => true,
			)
		);
	}

	private static function save_parent( WP_Post $post, int|string $parent ): bool|WP_Error {
		if ( ! is_post_type_hierarchical( $post->post_type ) ) {
			return new WP_Error( 'corinthian_unsupported', __( 'This content type has no hierarchy.', 'corinthian-admin-column-manager' ) );
		}
		if ( 0 !== $parent && ! ( is_string( $parent ) && ctype_digit( $parent ) ) ) {
			return new WP_Error( 'corinthian_invalid', __( 'Choose a parent.', 'corinthian-admin-column-manager' ) );
		}
		$parent = (int) $parent;
		if ( $parent > 0 ) {
			$target = get_post( $parent );
			if ( ! $target || $target->post_type !== $post->post_type || ! current_user_can( 'read_post', $parent ) ) {
				return new WP_Error( 'corinthian_invalid', __( 'Choose a parent of the same type.', 'corinthian-admin-column-manager' ) );
			}
			if ( $parent === $post->ID || in_array( $post->ID, array_map( 'intval', get_post_ancestors( $parent ) ), true ) ) {
				return new WP_Error( 'corinthian_invalid', __( 'An item cannot be its own parent or a child of its own descendant.', 'corinthian-admin-column-manager' ) );
			}
		}
		return self::update(
			array(
				'ID'          => $post->ID,
				'post_parent' => $parent,
			)
		);
	}

	/**
	 * @param array{taxonomy?:string,allow_create?:bool} $extra
	 */
	private static function save_terms( WP_Post $post, mixed $value, string $mode, array $extra ): bool|WP_Error {
		$name     = (string) ( $extra['taxonomy'] ?? '' );
		$taxonomy = '' !== $name ? get_taxonomy( $name ) : false;
		if ( ! $taxonomy || ! $taxonomy->show_ui || ! is_object_in_taxonomy( $post->post_type, $name ) ) {
			return new WP_Error( 'corinthian_invalid', __( 'That taxonomy is not available for this content.', 'corinthian-admin-column-manager' ) );
		}
		if ( ! current_user_can( $taxonomy->cap->assign_terms ) ) {
			return new WP_Error( 'corinthian_forbidden', __( 'You cannot assign these terms.', 'corinthian-admin-column-manager' ) );
		}
		if ( 'clear' === $mode ) {
			$result = wp_set_object_terms( $post->ID, array(), $name );
			return is_wp_error( $result ) ? $result : true;
		}

		$items = is_array( $value ) ? $value : ( '' === trim( (string) $value ) ? array() : explode( ',', (string) $value ) );
		$ids   = array();
		foreach ( $items as $item ) {
			if ( ! is_scalar( $item ) || '' === trim( (string) $item ) ) {
				return new WP_Error( 'corinthian_invalid', __( 'Invalid term.', 'corinthian-admin-column-manager' ) );
			}
			$item = trim( (string) $item );
			if ( ctype_digit( $item ) ) {
				$term = get_term( (int) $item, $name );
				if ( ! $term instanceof \WP_Term || ! current_user_can( 'assign_term', $term->term_id ) ) {
					return new WP_Error( 'corinthian_invalid', __( 'One of the terms does not exist or cannot be assigned.', 'corinthian-admin-column-manager' ) );
				}
				$ids[] = $term->term_id;
				continue;
			}
			if ( 'set' !== $mode && 'append' !== $mode ) {
				return new WP_Error( 'corinthian_invalid', __( 'Invalid term.', 'corinthian-admin-column-manager' ) );
			}
			if ( empty( $extra['allow_create'] ) || ! current_user_can( $taxonomy->cap->edit_terms ) ) {
				return new WP_Error( 'corinthian_forbidden', __( 'You cannot create new terms here.', 'corinthian-admin-column-manager' ) );
			}
			$existing = term_exists( $item, $name );
			if ( ! $existing ) {
				$existing = wp_insert_term( $item, $name );
				if ( is_wp_error( $existing ) ) {
					return $existing;
				}
			}
			$ids[] = (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
		}
		$ids = array_values( array_unique( $ids ) );

		if ( 'remove' === $mode ) {
			$result = $ids ? wp_remove_object_terms( $post->ID, $ids, $name ) : true;
		} else {
			$result = wp_set_object_terms( $post->ID, $ids, $name, 'append' === $mode );
		}
		return is_wp_error( $result ) ? $result : true;
	}

	private static function save_thumbnail( WP_Post $post, string $text ): bool|WP_Error {
		if ( ! post_type_supports( $post->post_type, 'thumbnail' ) ) {
			return new WP_Error( 'corinthian_unsupported', __( 'This content type has no featured image.', 'corinthian-admin-column-manager' ) );
		}
		if ( '' === $text || '0' === $text ) {
			delete_post_thumbnail( $post );
			return true;
		}
		$attachment = ctype_digit( $text ) ? (int) $text : 0;
		if ( ! $attachment || 'attachment' !== get_post_type( $attachment ) || ! wp_attachment_is_image( $attachment ) || ! current_user_can( 'read_post', $attachment ) ) {
			return new WP_Error( 'corinthian_invalid', __( 'Choose an image from the media library.', 'corinthian-admin-column-manager' ) );
		}
		if ( (int) get_post_thumbnail_id( $post ) !== $attachment && ! set_post_thumbnail( $post, $attachment ) ) {
			return new WP_Error( 'corinthian_failed', __( 'The featured image could not be saved.', 'corinthian-admin-column-manager' ) );
		}
		return true;
	}

	/**
	 * @return array<string,string>
	 */
	private static function author_options( string $post_type ): array {
		$type = get_post_type_object( $post_type );
		if ( ! $type || ! current_user_can( $type->cap->edit_others_posts ) ) {
			return array();
		}
		$out = array();
		foreach ( get_users(
			array(
				'capability' => $type->cap->edit_posts,
				'number'     => self::AUTHOR_LIMIT,
				'orderby'    => 'display_name',
				'fields'     => array( 'ID', 'display_name' ),
			)
		) as $user ) {
			$out[ (string) $user->ID ] = (string) $user->display_name;
		}
		return $out;
	}

	/**
	 * @return array<int|string,string>
	 */
	private static function parent_options( string $post_type ): array {
		$out = array( '0' => __( '(none)', 'corinthian-admin-column-manager' ) );
		if ( ! is_post_type_hierarchical( $post_type ) ) {
			return $out;
		}
		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page' => self::PARENT_LIMIT,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		foreach ( $posts as $post ) {
			if ( current_user_can( 'read_post', $post->ID ) ) {
				$out[ (string) $post->ID ] = '' === $post->post_title ? __( '(no title)', 'corinthian-admin-column-manager' ) : $post->post_title;
			}
		}
		return $out;
	}

	/**
	 * @return array<string,string>
	 */
	private static function template_options( string $post_type ): array {
		$out = array( 'default' => __( 'Default template', 'corinthian-admin-column-manager' ) );
		foreach ( wp_get_theme()->get_page_templates( null, $post_type ) as $file => $name ) {
			$out[ (string) $file ] = (string) $name;
		}
		return $out;
	}

	/**
	 * @return array<string,string>
	 */
	private static function format_options(): array {
		$all       = get_post_format_strings();
		$supported = current_theme_supports( 'post-formats' ) ? (array) ( get_theme_support( 'post-formats' )[0] ?? array() ) : array();
		$out       = array( 'standard' => $all['standard'] );
		foreach ( $supported as $slug ) {
			if ( isset( $all[ $slug ] ) ) {
				$out[ $slug ] = $all[ $slug ];
			}
		}
		return $out;
	}

	/**
	 * @return array<string,string>
	 */
	private static function term_options( string $taxonomy ): array {
		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => self::TERM_LIMIT,
				'orderby'    => 'name',
				'fields'     => 'id=>name',
			)
		);
		$out   = array();
		foreach ( is_array( $terms ) ? $terms : array() as $id => $name ) {
			$out[ (string) $id ] = (string) $name;
		}
		return $out;
	}

	/**
	 * @return list<int>
	 */
	private static function term_ids( int $post_id, string $taxonomy ): array {
		$terms = '' !== $taxonomy ? get_the_terms( $post_id, $taxonomy ) : false;
		return is_array( $terms ) ? array_values( array_map( 'intval', wp_list_pluck( $terms, 'term_id' ) ) ) : array();
	}
}
