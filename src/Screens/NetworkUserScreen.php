<?php

namespace Corinthian\Screens;

final class NetworkUserScreen extends UserScreen {

	public function key(): string {
		return 'network-users';
	}

	public function label(): string {
		return __( 'Network users', 'corinthian-admin-column-manager' );
	}

	public function screen_id(): string {
		return 'users-network';
	}

	public function url(): string {
		return network_admin_url( 'users.php' );
	}

	public function network_capability(): string {
		return 'manage_network_users';
	}

	public function user_can_list(): bool {
		return current_user_can( 'manage_network_users' );
	}

	public function register(): void {
		add_filter( 'wpmu_users_columns', array( $this, 'filter_columns' ), PHP_INT_MAX - 10 );
		add_filter( 'manage_users-network_sortable_columns', array( $this, 'filter_sortable' ), PHP_INT_MAX - 10 );
		add_filter(
			'manage_users_custom_column',
			function ( $output, $column, $user_id ) {
				if ( $this->is_current() && str_starts_with( (string) $column, 'corinthian-' ) ) {
					return $this->cell( (string) $column, (int) $user_id );
				}
				return $output;
			},
			20,
			3
		);
		add_action( 'pre_user_query', array( $this, 'maybe_sort' ) );
		add_action( 'pre_user_query', array( $this, 'maybe_filter' ) );
	}
}
