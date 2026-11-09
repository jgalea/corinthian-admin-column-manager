<?php

namespace Corinthian\Screens;

use Corinthian\Columns\Registry;
use Corinthian\Sets\SetRepository;
use WP_Taxonomy;
use WP_Term_Query;

final class TermScreen extends Screen {

	public function __construct(
		Registry $registry,
		SetRepository $sets,
		private WP_Taxonomy $taxonomy,
	) {
		parent::__construct( $registry, $sets );
	}

	public function key(): string {
		return 'term:' . $this->taxonomy->name;
	}

	public function label(): string {
		return (string) $this->taxonomy->labels->name;
	}

	public function screen_id(): string {
		return 'edit-' . $this->taxonomy->name;
	}

	public function object_type(): string {
		return 'term';
	}

	public function subtype(): string {
		return $this->taxonomy->name;
	}

	public function url(): string {
		$args = array( 'taxonomy' => $this->taxonomy->name );
		$type = $this->taxonomy->object_type[0] ?? '';
		if ( '' !== $type && 'post' !== $type ) {
			$args['post_type'] = $type;
		}
		return add_query_arg( $args, admin_url( 'edit-tags.php' ) );
	}

	public function register(): void {
		$tax = $this->taxonomy->name;
		add_filter( "manage_{$this->screen_id()}_columns", array( $this, 'filter_columns' ), PHP_INT_MAX - 10 );
		add_filter( "manage_{$this->screen_id()}_sortable_columns", array( $this, 'filter_sortable' ), PHP_INT_MAX - 10 );
		add_filter(
			"manage_{$tax}_custom_column",
			function ( $output, $column, $term_id ) {
				if ( str_starts_with( (string) $column, 'corinthian-' ) ) {
					return $this->cell( (string) $column, (int) $term_id );
				}
				return $output;
			},
			10,
			3
		);
		add_action( 'parse_term_query', array( $this, 'maybe_sort' ) );
		add_filter( 'terms_clauses', array( $this, 'maybe_filter' ), 5, 2 );
	}

	/**
	 * @param array<string,string> $clauses
	 * @param list<string>|string  $taxonomies
	 * @return array<string,string>
	 */
	public function maybe_filter( array $clauses, $taxonomies ): array {
		if ( ! is_admin() || ! $this->is_current() || array( $this->taxonomy->name ) !== array_values( (array) $taxonomies ) ) {
			return $clauses;
		}
		$sql = $this->filter_sql();
		if ( '' !== $sql['where'] ) {
			$clauses['join']  .= ' ' . $sql['join'];
			$clauses['where'] .= ' AND ' . $sql['where'];
		}
		return $clauses;
	}

	public function maybe_sort( WP_Term_Query $query ): void {
		if ( ! is_admin() || ! $this->is_current() || 'count' === ( $query->query_vars['fields'] ?? '' ) ) {
			return;
		}
		$taxonomies = (array) ( $query->query_vars['taxonomy'] ?? array() );
		if ( array( $this->taxonomy->name ) !== array_values( $taxonomies ) ) {
			return;
		}
		$target = $this->sort_target( $query->query_vars['orderby'] ?? '' );
		if ( ! $target ) {
			return;
		}
		[ $config, $type ]            = $target;
		$order                        = self::normalize_order( $query->query_vars['order'] ?? '' );
		$query->query_vars['orderby'] = 'none';

		add_filter(
			'terms_clauses',
			function ( $clauses, $taxonomies, $args ) use ( $config, $type, $order ) {
				if ( array( $this->taxonomy->name ) !== array_values( (array) $taxonomies ) || 'none' !== ( $args['orderby'] ?? '' ) ) {
					return $clauses;
				}
				$normalized         = $type->sort_clauses(
					array(
						'join'    => (string) ( $clauses['join'] ?? '' ),
						'where'   => (string) ( $clauses['where'] ?? '' ),
						'orderby' => '',
						'groupby' => '',
					),
					$config,
					$order,
					$this
				);
				$clauses['join']    = $normalized['join'];
				$clauses['where']   = $normalized['where'];
				$groupby            = '' !== $normalized['groupby'] ? 'GROUP BY ' . $normalized['groupby'] . ' ' : '';
				$clauses['orderby'] = $groupby . 'ORDER BY ' . trim( $normalized['orderby'] . ", t.term_id {$order}", ', ' );
				$clauses['order']   = '';
				return $clauses;
			},
			10,
			3
		);
	}

	public function primary_column(): string {
		return 'name';
	}
}
