<?php

namespace Corinthian\Screens;

use WP_Site_Query;

defined( 'ABSPATH' ) || exit;

final class NetworkSiteScreen extends Screen {

	public function key(): string {
		return 'network-sites';
	}

	public function label(): string {
		return __( 'Network sites', 'corinthian-admin-column-manager' );
	}

	public function screen_id(): string {
		return 'sites-network';
	}

	public function object_type(): string {
		return 'site';
	}

	public function url(): string {
		return network_admin_url( 'sites.php' );
	}

	public function network_capability(): string {
		return 'manage_sites';
	}

	public function register(): void {
		SiteQuery::hook();
		add_filter( 'wpmu_blogs_columns', array( $this, 'filter_columns' ), PHP_INT_MAX - 10 );
		add_filter( 'manage_sites-network_sortable_columns', array( $this, 'filter_sortable' ), PHP_INT_MAX - 10 );
		// This core hook is an action that echoes, unlike the filter the users table has.
		add_action(
			'manage_sites_custom_column',
			function ( $column, $blog_id ) {
				if ( $this->is_current() && str_starts_with( (string) $column, 'corinthian-' ) ) {
					echo $this->cell( (string) $column, (int) $blog_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Column types return escaped HTML.
				}
			},
			10,
			2
		);
		add_filter( 'ms_sites_list_table_query_args', array( $this, 'query_args' ) );
		add_filter( 'sites_clauses', array( $this, 'clauses' ), 10, 2 );
	}

	/**
	 * Marks the list table's own queries, so sites_clauses leaves every other site query alone.
	 *
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	public function query_args( $args ) {
		$args = (array) $args;
		if ( ! is_admin() || ! $this->is_current() ) {
			return $args;
		}
		if ( $this->sort_target( $args['orderby'] ?? '' ) || '' !== $this->filter_sql()['where'] ) {
			$args[ SiteQuery::UNCACHED ] = true;
		}
		return $args;
	}

	/**
	 * @param array<string,string> $clauses
	 * @return array<string,string>
	 */
	public function clauses( $clauses, WP_Site_Query $query ) {
		if ( empty( $query->query_vars[ SiteQuery::UNCACHED ] ) || ! $this->is_current() ) {
			return $clauses;
		}
		$clauses = SiteQuery::narrow( (array) $clauses, $this->filter_sql() );
		$target  = $query->query_vars['count'] ? null : $this->sort_target( $query->query_vars['orderby'] ?? '' );
		if ( $target ) {
			$clauses = SiteQuery::order( $clauses, $target[0], $target[1], self::normalize_order( $query->query_vars['order'] ?? 'ASC' ), $this );
		}
		return $clauses;
	}

	public function primary_column(): string {
		return 'blogname';
	}
}
