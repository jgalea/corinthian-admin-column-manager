<?php

namespace Corinthian\Columns\Types\Sites;

use Corinthian\Columns\Config;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Sortable;
use Corinthian\Columns\Types\Users\FilterSql;
use Corinthian\Columns\Types\Users\FiltersField;
use Corinthian\Screens\Screen;

/**
 * Registered and last updated, both stored in UTC on the blogs table.
 */
final class SiteDateType extends AbstractSiteType implements Sortable, Filterable {

	use FiltersField;

	public function label(): string {
		return 'registered' === $this->field ? __( 'Registered', 'corinthian-admin-column-manager' ) : __( 'Last updated', 'corinthian-admin-column-manager' );
	}

	public function settings_schema( Screen $screen ): array {
		return $this->date_settings();
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		$site = $this->site( $object_id );
		if ( ! $site ) {
			return null;
		}
		$date = (string) $site->{$this->field};
		return '' === $date || str_starts_with( $date, '0000' ) ? null : $date;
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		$timestamp = strtotime( ( is_scalar( $value ) ? (string) $value : '' ) . ' UTC' );
		return $timestamp ? $this->format_timestamp( $timestamp, $config ) : '';
	}

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		global $wpdb;
		$column = "{$wpdb->blogs}.{$this->field}";
		return $this->order_by( $clauses, $column, $order, "({$column} IS NULL OR {$column} < '1000-01-01')" );
	}

	protected function filter_spec( Config $config, Screen $screen ): ?array {
		global $wpdb;
		return array(
			'kind' => FilterSql::DATE,
			'expr' => "{$wpdb->blogs}.{$this->field}",
		);
	}
}
