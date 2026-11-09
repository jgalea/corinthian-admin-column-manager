<?php

namespace Corinthian\Columns\Types;

use Corinthian\Columns\AbstractType;
use Corinthian\Columns\Config;
use Corinthian\Screens\Screen;
use WP_Post;

abstract class AbstractPostType extends AbstractType {

	public function supports( Screen $screen ): bool {
		return 'post' === $screen->object_type() && $this->supports_post_type( $screen->subtype() );
	}

	public function group(): string {
		return match ( $this->group_slug() ) {
			'dates'       => __( 'Dates', 'corinthian-admin-column-manager' ),
			'author'      => __( 'Author', 'corinthian-admin-column-manager' ),
			'taxonomies'  => __( 'Taxonomies', 'corinthian-admin-column-manager' ),
			'content'     => __( 'Content', 'corinthian-admin-column-manager' ),
			'media'       => __( 'Media', 'corinthian-admin-column-manager' ),
			'comments'    => __( 'Comments', 'corinthian-admin-column-manager' ),
			'hierarchy'   => __( 'Hierarchy', 'corinthian-admin-column-manager' ),
			default       => __( 'Post', 'corinthian-admin-column-manager' ),
		};
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		$post = get_post( $object_id );
		return $post instanceof WP_Post ? $this->post_value( $post, $config, $screen ) : null;
	}

	public function render( int $object_id, Config $config, Screen $screen ): string {
		$value = $this->value( $object_id, $config, $screen );
		if ( null === $value || '' === $value || array() === $value ) {
			return $this->dash();
		}
		$html = $this->format( $value, $object_id, $config, $screen );
		return '' === $html ? $this->dash() : $html;
	}

	protected function group_slug(): string {
		return 'post';
	}

	protected function supports_post_type( string $post_type ): bool {
		return true;
	}

	abstract protected function post_value( WP_Post $post, Config $config, Screen $screen ): mixed;

	/**
	 * Escaped HTML for a non-empty value.
	 */
	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_map( 'strval', $value ) );
		}
		return esc_html( is_bool( $value ) ? ( $value ? '1' : '0' ) : (string) $value ); // phpcs:ignore WordPress.PHP.StrictComparisons
	}

	protected function dash(): string {
		return '<span aria-hidden="true">&#8212;</span>';
	}

	protected function yes_no( bool $on ): string {
		return $on
			? '<span class="dashicons dashicons-yes" aria-label="' . esc_attr__( 'Yes', 'corinthian-admin-column-manager' ) . '"></span>'
			: '<span class="dashicons dashicons-no-alt" aria-label="' . esc_attr__( 'No', 'corinthian-admin-column-manager' ) . '"></span>';
	}

	protected function direction( string $order ): string {
		return 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';
	}

	/**
	 * Sets ORDER BY, pushing rows matching $last_when (a SQL condition) to the end.
	 *
	 * @param array<string,string> $clauses
	 * @return array<string,string>
	 */
	protected function order_by( array $clauses, string $expr, string $order, string $last_when = '' ): array {
		$parts = array();
		if ( '' !== $last_when ) {
			$parts[] = "({$last_when}) ASC";
		}
		if ( '' !== $expr ) {
			$parts[] = $expr . ' ' . $this->direction( $order );
		}
		$clauses['orderby'] = implode( ', ', $parts );
		return $clauses;
	}

	/**
	 * LEFT JOIN one meta key under this column's alias and group by post.
	 *
	 * @param array<string,string> $clauses
	 * @return array<string,string>
	 */
	protected function join_meta( array $clauses, Config $config, string $meta_key ): array {
		global $wpdb;
		$alias              = $this->alias( $config );
		$clauses['join']   .= $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} AS {$alias} ON ({$alias}.post_id = {$wpdb->posts}.ID AND {$alias}.meta_key = %s)", $meta_key ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$clauses['groupby'] = "{$wpdb->posts}.ID";
		return $clauses;
	}

	/**
	 * Sort by the first term name of one taxonomy.
	 *
	 * @param array<string,string> $clauses
	 * @return array<string,string>
	 */
	protected function sort_by_first_term( array $clauses, Config $config, string $taxonomy, string $order ): array {
		global $wpdb;
		$a                  = $this->alias( $config );
		$clauses['join']   .= $wpdb->prepare( " LEFT JOIN {$wpdb->term_relationships} AS {$a}_r ON {$a}_r.object_id = {$wpdb->posts}.ID LEFT JOIN {$wpdb->term_taxonomy} AS {$a}_x ON ({$a}_x.term_taxonomy_id = {$a}_r.term_taxonomy_id AND {$a}_x.taxonomy = %s) LEFT JOIN {$wpdb->terms} AS {$a}_t ON {$a}_t.term_id = {$a}_x.term_id", $taxonomy ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$clauses['groupby'] = "{$wpdb->posts}.ID";
		return $this->order_by( $clauses, "MIN({$a}_t.name)", $order, "MIN({$a}_t.name) IS NULL" );
	}

	protected function edit_link( int $post_id ): string {
		$url = get_edit_post_link( $post_id, 'raw' );
		return $url ? esc_url( $url ) : '';
	}

	protected function user_name( int $user_id ): string {
		$user = $user_id ? get_userdata( $user_id ) : false;
		return $user ? $user->display_name : '';
	}
}
