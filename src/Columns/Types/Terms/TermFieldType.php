<?php

namespace Corinthian\Columns\Types\Terms;

use Corinthian\Columns\Config;
use Corinthian\Columns\Sortable;
use Corinthian\Screens\Screen;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Types\Users\FilterSql;
use Corinthian\Columns\Types\Users\FiltersField;

/**
 * Term columns that sort in SQL. The terms screen aliases the tables as t and tt.
 */
class TermFieldType extends AbstractTermType implements Sortable, Filterable {

	use FiltersField;

	public function label(): string {
		return match ( $this->field ) {
			'id'         => __( 'Term ID', 'corinthian-admin-column-manager' ),
			'name'       => __( 'Name', 'corinthian-admin-column-manager' ),
			'slug'       => __( 'Slug', 'corinthian-admin-column-manager' ),
			'post_count' => __( 'Post count', 'corinthian-admin-column-manager' ),
			default      => __( 'Parent', 'corinthian-admin-column-manager' ),
		};
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		$term = $this->term( $object_id, $screen );
		if ( ! $term ) {
			return null;
		}
		switch ( $this->field ) {
			case 'id':
				return $term->term_id;
			case 'name':
				return $term->name;
			case 'slug':
				return $term->slug;
			case 'post_count':
				return (int) $term->count;
			default:
				$parent = $term->parent ? $this->term( $term->parent, $screen ) : null;
				return $parent ? $parent->name : null;
		}
	}

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		global $wpdb;
		switch ( $this->field ) {
			case 'id':
				return $this->order_by( $clauses, 't.term_id', $order );
			case 'post_count':
				return $this->order_by( $clauses, 'tt.count', $order );
			case 'parent':
				$sub = "(SELECT pt.name FROM {$wpdb->terms} pt WHERE pt.term_id = tt.parent)";
				return $this->order_by( $clauses, $sub, $order, self::blank( $sub ) );
			default:
				$column = 't.' . $this->field;
				return $this->order_by( $clauses, $column, $order, self::blank( $column ) );
		}
	}

	protected function filter_spec( Config $config, Screen $screen ): ?array {
		global $wpdb;
		switch ( $this->field ) {
			case 'id':
				return array(
					'kind' => FilterSql::NUMBER,
					'expr' => 't.term_id',
				);
			case 'post_count':
				return array(
					'kind' => FilterSql::NUMBER,
					'expr' => 'tt.count',
				);
			case 'parent':
				$options = array( '0' => __( 'None (top level)', 'corinthian-admin-column-manager' ) );
				// A direct query: get_terms() would re-enter the screen's own terms_clauses filter.
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT t.term_id, t.name FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} x ON x.term_id = t.term_id WHERE x.taxonomy = %s ORDER BY t.name LIMIT 500", $screen->subtype() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- See the comment above; the table names come from $wpdb.
				foreach ( (array) $rows as $row ) {
					$options[ (string) $row->term_id ] = (string) $row->name;
				}
				return array(
					'kind'    => FilterSql::ENUM,
					'expr'    => 'tt.parent',
					'options' => $options,
				);
			default:
				return array(
					'kind' => FilterSql::TEXT,
					'expr' => 't.' . $this->field,
				);
		}
	}
}
