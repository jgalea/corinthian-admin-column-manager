<?php

namespace Corinthian\Screens;

use WP_Comment_Query;

final class CommentScreen extends Screen {

	public function key(): string {
		return 'comments';
	}

	public function label(): string {
		return __( 'Comments', 'corinthian-admin-column-manager' );
	}

	public function screen_id(): string {
		return 'edit-comments';
	}

	public function object_type(): string {
		return 'comment';
	}

	public function url(): string {
		return admin_url( 'edit-comments.php' );
	}

	public function register(): void {
		add_filter( 'manage_edit-comments_columns', array( $this, 'filter_columns' ), PHP_INT_MAX - 10 );
		add_filter( 'manage_edit-comments_sortable_columns', array( $this, 'filter_sortable' ), PHP_INT_MAX - 10 );
		add_action(
			'manage_comments_custom_column',
			function ( $column, $comment_id ) {
				if ( str_starts_with( (string) $column, 'corinthian-' ) ) {
					echo $this->cell( (string) $column, (int) $comment_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Column types return escaped HTML.
				}
			},
			10,
			2
		);
		add_action( 'pre_get_comments', array( $this, 'maybe_sort' ) );
		add_action( 'pre_get_comments', array( $this, 'maybe_filter' ) );
	}

	public function maybe_filter( WP_Comment_Query $query ): void {
		if ( ! is_admin() || ! $this->is_current() ) {
			return;
		}
		$sql = $this->filter_sql();
		if ( '' === $sql['where'] ) {
			return;
		}
		$query->query_vars['cache_domain'] = ( $query->query_vars['cache_domain'] ?? 'core' ) . ':f' . md5( $sql['where'] );
		add_filter(
			'comments_clauses',
			static function ( $clauses, $q ) use ( $query, $sql ) {
				if ( $q === $query ) {
					$clauses['join']  .= ' ' . $sql['join'];
					$clauses['where'] .= ' AND ' . $sql['where'];
				}
				return $clauses;
			},
			5,
			2
		);
	}

	public function maybe_sort( WP_Comment_Query $query ): void {
		if ( ! is_admin() || ! $this->is_current() || ! empty( $query->query_vars['count'] ) ) {
			return;
		}
		$target = $this->sort_target( $query->query_vars['orderby'] ?? '' );
		if ( ! $target ) {
			return;
		}
		[ $config, $type ] = $target;
		$order             = self::normalize_order( $query->query_vars['order'] ?? '' );
		// The comment query cache keys on query vars, not SQL: make each column/order distinct.
		$query->query_vars['cache_domain'] = 'corinthian:' . $config->id . ':' . $order;
		$query->query_vars['orderby']      = 'none';

		add_filter(
			'comments_clauses',
			function ( $clauses, $q ) use ( $query, $config, $type, $order ) {
				if ( $q !== $query ) {
					return $clauses;
				}
				global $wpdb;
				$clauses           += array(
					'join'    => '',
					'where'   => '',
					'orderby' => '',
					'groupby' => '',
				);
				$clauses            = $type->sort_clauses( $clauses, $config, $order, $this );
				$clauses['orderby'] = trim( $clauses['orderby'] . ", {$wpdb->comments}.comment_ID {$order}", ', ' );
				return $clauses;
			},
			10,
			2
		);
	}

	public function primary_column(): string {
		return 'author';
	}
}
