<?php

namespace Corinthian\Columns\Types\Users;

use Corinthian\Columns\Config;
use Corinthian\Columns\Sortable;
use Corinthian\Screens\Screen;
use Corinthian\Columns\Filterable;

final class UserDateType extends AbstractUserType implements Sortable, Filterable {

	use FiltersField;

	public function __construct() {
		parent::__construct( 'registered' );
	}

	public function label(): string {
		return __( 'Registered date', 'corinthian-admin-column-manager' );
	}

	public function settings_schema( Screen $screen ): array {
		return $this->date_settings();
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		$user = get_userdata( $object_id );
		if ( ! $user || str_starts_with( (string) $user->user_registered, '0000' ) ) {
			return null;
		}
		return (string) $user->user_registered;
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		$timestamp = strtotime( ( is_scalar( $value ) ? (string) $value : '' ) . ' UTC' );
		return $timestamp ? $this->format_timestamp( $timestamp, $config ) : '';
	}

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		global $wpdb;
		$column = "{$wpdb->users}.user_registered";
		return $this->order_by( $clauses, $column, $order, "({$column} IS NULL OR {$column} < '1000-01-01')" );
	}

	protected function filter_spec( Config $config, Screen $screen ): ?array {
		global $wpdb;
		return array(
			'kind' => FilterSql::DATE,
			'expr' => "{$wpdb->users}.user_registered",
		);
	}
}
