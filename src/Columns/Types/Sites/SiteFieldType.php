<?php

namespace Corinthian\Columns\Types\Sites;

use Corinthian\Columns\Config;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Sortable;
use Corinthian\Columns\Types\Users\FilterSql;
use Corinthian\Columns\Types\Users\FiltersField;
use Corinthian\Screens\Screen;

/**
 * Text and number columns that live on the blogs table: id, domain and path.
 */
final class SiteFieldType extends AbstractSiteType implements Sortable, Filterable {

	use FiltersField;

	private const COLUMNS = array(
		'id'     => 'blog_id',
		'domain' => 'domain',
		'path'   => 'path',
	);

	public function label(): string {
		return match ( $this->field ) {
			'id'     => __( 'Site ID', 'corinthian-admin-column-manager' ),
			'domain' => __( 'Domain', 'corinthian-admin-column-manager' ),
			default  => __( 'Path', 'corinthian-admin-column-manager' ),
		};
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		$site = $this->site( $object_id );
		if ( ! $site ) {
			return null;
		}
		return 'id' === $this->field ? (int) $site->blog_id : (string) $site->{$this->field};
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		$text = is_scalar( $value ) ? (string) $value : '';
		return 'id' === $this->field ? esc_html( $text ) : $this->admin_link( $object_id, $text );
	}

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		global $wpdb;
		$column = "{$wpdb->blogs}." . self::COLUMNS[ $this->field ];
		return $this->order_by( $clauses, $column, $order, 'id' === $this->field ? null : self::blank( $column ) );
	}

	protected function filter_spec( Config $config, Screen $screen ): ?array {
		global $wpdb;
		return array(
			'kind' => 'id' === $this->field ? FilterSql::NUMBER : FilterSql::TEXT,
			'expr' => "{$wpdb->blogs}." . self::COLUMNS[ $this->field ],
		);
	}
}
