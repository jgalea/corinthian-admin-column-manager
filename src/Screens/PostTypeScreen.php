<?php

namespace Corinthian\Screens;

use Corinthian\Columns\Registry;
use Corinthian\Sets\SetRepository;
use WP_Post_Type;
use WP_Query;

defined( 'ABSPATH' ) || exit;

class PostTypeScreen extends Screen {

	public function __construct(
		Registry $registry,
		SetRepository $sets,
		protected WP_Post_Type $post_type,
	) {
		parent::__construct( $registry, $sets );
	}

	public function key(): string {
		return 'post:' . $this->post_type->name;
	}

	public function label(): string {
		return (string) $this->post_type->labels->name;
	}

	public function screen_id(): string {
		return 'edit-' . $this->post_type->name;
	}

	public function object_type(): string {
		return 'post';
	}

	public function subtype(): string {
		return $this->post_type->name;
	}

	public function url(): string {
		return admin_url( 'edit.php?post_type=' . $this->post_type->name );
	}

	protected function render_hook(): string {
		return "manage_{$this->post_type->name}_posts_custom_column";
	}

	public function register(): void {
		// The screen-level filter runs after every per-type and plugin filter.
		add_filter( "manage_{$this->screen_id()}_columns", array( $this, 'filter_columns' ), PHP_INT_MAX - 10 );
		add_filter( "manage_{$this->screen_id()}_sortable_columns", array( $this, 'filter_sortable' ), PHP_INT_MAX - 10 );
		add_action(
			$this->render_hook(),
			function ( $column, $post_id ) {
				if ( str_starts_with( (string) $column, 'corinthian-' ) ) {
					echo $this->cell( (string) $column, (int) $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Column types return escaped HTML.
				}
			},
			10,
			2
		);
		add_action( 'pre_get_posts', array( $this, 'maybe_sort' ) );
		add_action( 'pre_get_posts', array( $this, 'maybe_filter' ) );
	}

	public function maybe_filter( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || ! $this->is_current() ) {
			return;
		}
		$sql = $this->filter_sql();
		if ( '' === $sql['where'] ) {
			return;
		}
		add_filter(
			'posts_clauses',
			static function ( $clauses, $q ) use ( $query, $sql ) {
				if ( $q === $query ) {
					$clauses['join']  .= ' ' . $sql['join'];
					$clauses['where'] .= ' AND ' . $sql['where'];
				}
				return $clauses;
			},
			5,
			2
		);
	}

	public function maybe_sort( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || ! $this->is_current() ) {
			return;
		}
		$target = $this->sort_target( $query->get( 'orderby' ) );
		if ( ! $target ) {
			return;
		}
		[ $config, $type ] = $target;
		$order             = self::normalize_order( $query->get( 'order' ) );
		$query->set( 'orderby', 'none' );

		add_filter(
			'posts_clauses',
			function ( $clauses, $q ) use ( $query, $config, $type, $order ) {
				if ( $q !== $query ) {
					return $clauses;
				}
				global $wpdb;
				$clauses = $type->sort_clauses( $clauses, $config, $order, $this );
				// Stable tie-break so paging never repeats or skips rows.
				$clauses['orderby'] = trim( ( $clauses['orderby'] ?? '' ) . ", {$wpdb->posts}.ID {$order}", ', ' );
				return $clauses;
			},
			10,
			2
		);
	}
}
