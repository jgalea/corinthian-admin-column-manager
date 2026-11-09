<?php

namespace Corinthian\Columns\Types;

use Corinthian\Columns\Config;
use Corinthian\Columns\Editable;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Sortable;
use Corinthian\Screens\Screen;
use WP_Post;
use WP_Term;

final class TaxonomyType extends AbstractPostType implements Sortable, Filterable, Editable {

	use EditsPost;

	public function key(): string {
		return 'post.taxonomy';
	}

	public function label(): string {
		return __( 'Taxonomy terms', 'corinthian-admin-column-manager' );
	}

	protected function group_slug(): string {
		return 'taxonomies';
	}

	protected function supports_post_type( string $post_type ): bool {
		return array() !== $this->taxonomies( $post_type );
	}

	public function settings_schema( Screen $screen ): array {
		$options = $this->taxonomies( $screen->subtype() );
		return array(
			array(
				'name'    => 'taxonomy',
				'type'    => 'select',
				'label'   => __( 'Taxonomy', 'corinthian-admin-column-manager' ),
				'default' => (string) array_key_first( $options ),
				'options' => $options,
			),
		);
	}

	/**
	 * @return array<string,string> Taxonomy name => label.
	 */
	private function taxonomies( string $post_type ): array {
		$out = array();
		foreach ( get_object_taxonomies( $post_type, 'objects' ) as $taxonomy ) {
			if ( $taxonomy->show_ui ) {
				$out[ $taxonomy->name ] = $taxonomy->labels->name;
			}
		}
		return $out;
	}

	private function chosen( Config $config, Screen $screen ): string {
		$options = $this->taxonomies( $screen->subtype() );
		$chosen  = (string) $config->setting( 'taxonomy', '' );
		return isset( $options[ $chosen ] ) ? $chosen : (string) array_key_first( $options );
	}

	/**
	 * @return list<WP_Term>
	 */
	private function terms( int $post_id, string $taxonomy ): array {
		if ( '' === $taxonomy ) {
			return array();
		}
		$terms = get_the_terms( $post_id, $taxonomy );
		return is_array( $terms ) ? array_values( $terms ) : array();
	}

	protected function post_value( WP_Post $post, Config $config, Screen $screen ): mixed {
		$names = wp_list_pluck( $this->terms( $post->ID, $this->chosen( $config, $screen ) ), 'name' );
		return $names ? array_values( $names ) : null;
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		$taxonomy = get_taxonomy( $this->chosen( $config, $screen ) );
		if ( ! $taxonomy ) {
			return '';
		}
		$links = array();
		foreach ( $this->terms( $object_id, $taxonomy->name ) as $term ) {
			$args = array( 'post_type' => $screen->subtype() );
			if ( $taxonomy->query_var ) {
				$args[ $taxonomy->query_var ] = $term->slug;
			} else {
				$args['taxonomy'] = $taxonomy->name;
				$args['term']     = $term->slug;
			}
			$links[] = '<a href="' . esc_url( add_query_arg( $args, admin_url( 'edit.php' ) ) ) . '">' . esc_html( $term->name ) . '</a>';
		}
		return implode( ', ', $links );
	}

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		$taxonomy = $this->chosen( $config, $screen );
		return '' === $taxonomy ? $clauses : $this->sort_by_first_term( $clauses, $config, $taxonomy, $order );
	}

	public function filter_operators( Config $config, Screen $screen ): array {
		$taxonomy = $this->chosen( $config, $screen );
		$options  = array();
		if ( '' !== $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'number'     => 500,
					'orderby'    => 'name',
					'fields'     => 'id=>name',
				)
			);
			foreach ( is_array( $terms ) ? $terms : array() as $id => $name ) {
				$options[ (string) $id ] = (string) $name;
			}
		}
		return PostFilter::in_ops( $options, true );
	}

	public function filter_condition( Config $config, string $op, mixed $value, Screen $screen, string $alias ): ?array {
		global $wpdb;
		$taxonomy = $this->chosen( $config, $screen );
		if ( '' === $taxonomy ) {
			return null;
		}
		if ( 'empty' === $op || 'not_empty' === $op ) {
			$any = PostFilter::term_exists( $alias, $taxonomy );
			return PostFilter::wrap( 'empty' === $op ? "NOT {$any}" : $any );
		}
		$ids = PostFilter::list_values( $value, true );
		if ( ! $ids || ! in_array( $op, array( 'in', 'not_in' ), true ) ) {
			return null;
		}
		$marks  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$exists = PostFilter::term_exists( $alias, $taxonomy, $wpdb->prepare( "{$alias}_x.term_id IN ({$marks})", $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		return PostFilter::wrap( 'in' === $op ? $exists : "NOT {$exists}" );
	}

	protected function edit_kind(): string {
		return 'taxonomy';
	}

	/**
	 * @return array{taxonomy:string,allow_create:bool}
	 */
	protected function edit_extra( Config $config, Screen $screen ): array {
		return array(
			'taxonomy'     => $this->chosen( $config, $screen ),
			'allow_create' => (bool) $config->setting( 'allow_create', false ),
		);
	}
}
