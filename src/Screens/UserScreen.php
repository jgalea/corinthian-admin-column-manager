<?php

namespace Corinthian\Screens;

use WP_User_Query;

class UserScreen extends Screen {

	public function key(): string {
		return 'users';
	}

	public function label(): string {
		return __( 'Users', 'corinthian-admin-column-manager' );
	}

	public function screen_id(): string {
		return 'users';
	}

	public function object_type(): string {
		return 'user';
	}

	public function url(): string {
		return admin_url( 'users.php' );
	}

	public function register(): void {
		add_filter( 'manage_users_columns', array( $this, 'filter_columns' ), PHP_INT_MAX - 10 );
		add_filter( 'manage_users_sortable_columns', array( $this, 'filter_sortable' ), PHP_INT_MAX - 10 );
		add_filter(
			'manage_users_custom_column',
			function ( $output, $column, $user_id ) {
				if ( str_starts_with( (string) $column, 'corinthian-' ) ) {
					return $this->cell( (string) $column, (int) $user_id );
				}
				return $output;
			},
			10,
			3
		);
		add_action( 'pre_user_query', array( $this, 'maybe_sort' ) );
		add_action( 'pre_user_query', array( $this, 'maybe_filter' ) );
	}

	public function maybe_filter( WP_User_Query $query ): void {
		if ( ! is_admin() || ! $this->is_current() ) {
			return;
		}
		$sql = $this->filter_sql();
		if ( '' !== $sql['where'] ) {
			$query->query_from  .= ' ' . $sql['join'];
			$query->query_where .= ' AND ' . $sql['where'];
		}
	}

	public function maybe_sort( WP_User_Query $query ): void {
		if ( ! is_admin() || ! $this->is_current() ) {
			return;
		}
		$target = $this->sort_target( $query->get( 'orderby' ) );
		if ( ! $target ) {
			return;
		}
		[ $config, $type ] = $target;
		global $wpdb;
		$order = self::normalize_order( $query->get( 'order' ) );

		$clauses = $type->sort_clauses(
			array(
				'join'    => '',
				'where'   => '',
				'orderby' => '',
				'groupby' => '',
			),
			$config,
			$order,
			$this
		);

		$query->query_from   .= ' ' . $clauses['join'];
		$query->query_where  .= $clauses['where'];
		$orderby              = trim( $clauses['orderby'] . ", {$wpdb->users}.ID {$order}", ', ' );
		$groupby              = '' !== $clauses['groupby'] ? 'GROUP BY ' . $clauses['groupby'] . ' ' : '';
		$query->query_orderby = $groupby . 'ORDER BY ' . $orderby;
	}

	public function primary_column(): string {
		return 'username';
	}
}
