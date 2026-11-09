<?php

namespace Corinthian\Columns\Types\Terms;

use Corinthian\Screens\Screen;
use WP_Error;
use WP_Term;

/**
 * Validation and storage for editing term columns, through wp_update_term.
 */
final class TermEditor {

	public const PARENT_LIMIT = 500;

	private const MODES = array(
		'name'        => array( 'set' ),
		'description' => array( 'set', 'clear' ),
		'parent'      => array( 'set', 'clear' ),
	);

	/**
	 * Slugs must be unique, so they have no bulk mode.
	 *
	 * @return array<string,mixed>
	 */
	public static function field( string $kind, Screen $screen ): array {
		$field = array(
			'input' => match ( $kind ) {
				'description' => 'textarea',
				'parent'      => 'select',
				default       => 'text',
			},
		);
		if ( 'parent' === $kind ) {
			$field['options'] = self::parent_options( $screen->subtype() );
		}
		if ( 'slug' !== $kind ) {
			$field['bulk'] = self::MODES[ $kind ] ?? array( 'set' );
		}
		return $field;
	}

	public static function value( string $kind, int $id, Screen $screen ): mixed {
		$term = self::term( $id, $screen );
		if ( ! $term instanceof WP_Term ) {
			return null;
		}
		return match ( $kind ) {
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			default       => (int) $term->parent,
		};
	}

	public static function save( string $kind, int $id, Screen $screen, mixed $value, string $mode ): bool|WP_Error {
		$term = self::term( $id, $screen );
		if ( ! $term instanceof WP_Term ) {
			return new WP_Error( 'corinthian_missing', __( 'That term does not exist.', 'corinthian-admin-column-manager' ) );
		}
		if ( ! current_user_can( 'edit_term', $id ) ) {
			return new WP_Error( 'corinthian_forbidden', __( 'You cannot edit this term.', 'corinthian-admin-column-manager' ) );
		}
		$modes = 'slug' === $kind ? array( 'set' ) : ( self::MODES[ $kind ] ?? array() );
		if ( ! in_array( $mode, $modes, true ) ) {
			return new WP_Error( 'corinthian_bad_mode', __( 'This column does not support that edit.', 'corinthian-admin-column-manager' ) );
		}
		if ( 'clear' === $mode ) {
			$value = '';
		} elseif ( ! is_scalar( $value ) ) {
			return new WP_Error( 'corinthian_invalid', __( 'Invalid value.', 'corinthian-admin-column-manager' ) );
		}
		$text = trim( (string) $value );

		$args = match ( $kind ) {
			'name'        => self::name( $text ),
			'slug'        => self::slug( $term, $text ),
			'description' => array( 'description' => current_user_can( 'unfiltered_html' ) ? $text : wp_kses_post( $text ) ),
			default       => self::parent( $term, $text ),
		};
		if ( $args instanceof WP_Error ) {
			return $args;
		}
		$result = wp_update_term( $id, $term->taxonomy, wp_slash( $args ) );
		return is_wp_error( $result ) ? $result : true;
	}

	private static function term( int $id, Screen $screen ): ?WP_Term {
		$term     = get_term( $id );
		$taxonomy = $screen->subtype();
		return $term instanceof WP_Term && ( '' === $taxonomy || $term->taxonomy === $taxonomy ) ? $term : null;
	}

	/**
	 * @return array<string,string>|WP_Error
	 */
	private static function name( string $text ): array|WP_Error {
		$name = sanitize_text_field( $text );
		if ( '' === $name ) {
			return new WP_Error( 'corinthian_invalid', __( 'A term needs a name.', 'corinthian-admin-column-manager' ) );
		}
		if ( strlen( $name ) > 200 ) {
			return new WP_Error( 'corinthian_invalid', __( 'That is too long.', 'corinthian-admin-column-manager' ) );
		}
		return array( 'name' => $name );
	}

	/**
	 * @return array<string,string>|WP_Error
	 */
	private static function slug( WP_Term $term, string $text ): array|WP_Error {
		$slug = sanitize_title( $text );
		if ( '' === $slug || strlen( $slug ) > 200 ) {
			return new WP_Error( 'corinthian_invalid', __( 'Enter a slug with letters, numbers or hyphens.', 'corinthian-admin-column-manager' ) );
		}
		$other = get_term_by( 'slug', $slug, $term->taxonomy );
		if ( $other instanceof WP_Term && $other->term_id !== $term->term_id ) {
			return new WP_Error( 'corinthian_duplicate', __( 'Another term in this taxonomy already uses that slug.', 'corinthian-admin-column-manager' ) );
		}
		return array( 'slug' => $slug );
	}

	/**
	 * @return array<string,int>|WP_Error
	 */
	private static function parent( WP_Term $term, string $text ): array|WP_Error {
		if ( ! is_taxonomy_hierarchical( $term->taxonomy ) ) {
			return new WP_Error( 'corinthian_invalid', __( 'This taxonomy has no parent terms.', 'corinthian-admin-column-manager' ) );
		}
		if ( '' === $text || '0' === $text ) {
			return array( 'parent' => 0 );
		}
		if ( ! ctype_digit( $text ) ) {
			return new WP_Error( 'corinthian_invalid', __( 'Choose a parent term.', 'corinthian-admin-column-manager' ) );
		}
		$parent = get_term( (int) $text, $term->taxonomy );
		if ( ! $parent instanceof WP_Term ) {
			return new WP_Error( 'corinthian_invalid', __( 'The parent must be a term of the same taxonomy.', 'corinthian-admin-column-manager' ) );
		}
		if ( $parent->term_id === $term->term_id || in_array( $term->term_id, get_ancestors( $parent->term_id, $term->taxonomy, 'taxonomy' ), true ) ) {
			return new WP_Error( 'corinthian_cycle', __( 'A term cannot be its own parent or a child of its own descendant.', 'corinthian-admin-column-manager' ) );
		}
		return array( 'parent' => $parent->term_id );
	}

	/**
	 * @return array<int|string,string>
	 */
	private static function parent_options( string $taxonomy ): array {
		$options = array( '0' => __( 'None (top level)', 'corinthian-admin-column-manager' ) );
		if ( '' === $taxonomy || ! is_taxonomy_hierarchical( $taxonomy ) ) {
			return $options;
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => self::PARENT_LIMIT,
				'orderby'    => 'name',
			)
		);
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$options[ (string) $term->term_id ] = $term->name;
		}
		return $options;
	}
}
