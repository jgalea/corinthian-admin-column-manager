<?php

namespace Corinthian\Screens;

use Corinthian\Columns\Registry;
use Corinthian\Sets\SetRepository;

defined( 'ABSPATH' ) || exit;

final class ScreenRegistry {

	/** @var array<string,Screen>|null */
	private ?array $screens = null;

	public function __construct(
		private Registry $registry,
		private SetRepository $sets,
	) {}

	/**
	 * @return array<string,Screen>
	 */
	public function all(): array {
		if ( null !== $this->screens ) {
			return $this->screens;
		}
		$screens = array();
		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $post_type ) {
			if ( in_array( $post_type->name, array( 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_font_family', 'wp_font_face', 'attachment' ), true ) ) {
				continue;
			}
			$screen                    = new PostTypeScreen( $this->registry, $this->sets, $post_type );
			$screens[ $screen->key() ] = $screen;
		}

		$media                    = new MediaScreen( $this->registry, $this->sets, get_post_type_object( 'attachment' ) );
		$screens[ $media->key() ] = $media;
		$users                    = new UserScreen( $this->registry, $this->sets );
		$screens[ $users->key() ] = $users;
		if ( is_multisite() ) {
			$network_users                    = new NetworkUserScreen( $this->registry, $this->sets );
			$screens[ $network_users->key() ] = $network_users;
			$network_sites                    = new NetworkSiteScreen( $this->registry, $this->sets );
			$screens[ $network_sites->key() ] = $network_sites;
		}
		$comments                    = new CommentScreen( $this->registry, $this->sets );
		$screens[ $comments->key() ] = $comments;
		foreach ( get_taxonomies( array( 'show_ui' => true ), 'objects' ) as $taxonomy ) {
			if ( in_array( $taxonomy->name, array( 'nav_menu', 'link_category', 'post_format', 'wp_theme', 'wp_template_part_area', 'wp_pattern_category' ), true ) ) {
				continue;
			}
			$term                    = new TermScreen( $this->registry, $this->sets, $taxonomy );
			$screens[ $term->key() ] = $term;
		}

		/**
		 * Add or remove list screens Corinthian manages.
		 *
		 * @param array<string,Screen> $screens
		 * @param Registry             $registry
		 * @param SetRepository        $sets
		 */
		$this->screens = (array) apply_filters( 'corinthian_screens', $screens, $this->registry, $this->sets );
		return $this->screens;
	}

	public function get( string $key ): ?Screen {
		return $this->all()[ $key ] ?? null;
	}

	public function for_screen_id( string $screen_id ): ?Screen {
		foreach ( $this->all() as $screen ) {
			if ( $screen->screen_id() === $screen_id ) {
				return $screen;
			}
		}
		return null;
	}
}
