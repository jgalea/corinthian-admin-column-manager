<?php

namespace Corinthian\Columns\Types;

use Corinthian\Columns\Config;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Sortable;
use Corinthian\Screens\Screen;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class ReadingTimeType extends AbstractPostType implements Sortable, Filterable {

	public function key(): string {
		return 'post.reading_time';
	}

	public function label(): string {
		return __( 'Reading time', 'corinthian-admin-column-manager' );
	}

	protected function group_slug(): string {
		return 'content';
	}

	protected function supports_post_type( string $post_type ): bool {
		return post_type_supports( $post_type, 'editor' );
	}

	public function settings_schema( Screen $screen ): array {
		return array(
			array(
				'name'    => 'words_per_minute',
				'type'    => 'number',
				'label'   => __( 'Words per minute', 'corinthian-admin-column-manager' ),
				'default' => 200,
			),
		);
	}

	protected function post_value( WP_Post $post, Config $config, Screen $screen ): mixed {
		$words = WordCountMeta::get( $post->ID );
		if ( $words < 1 ) {
			return null;
		}
		$wpm = max( 1, (int) $config->setting( 'words_per_minute', 200 ) );
		return max( 1, (int) ceil( $words / $wpm ) );
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		/* translators: %d: minutes. */
		return esc_html( sprintf( __( '%d min read', 'corinthian-admin-column-manager' ), (int) $value ) );
	}

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		$clauses = $this->join_meta( $clauses, $config, WordCountMeta::KEY );
		$expr    = 'MAX(CAST(' . $this->alias( $config ) . '.meta_value AS UNSIGNED))';
		return $this->order_by( $clauses, $expr, $order, "{$expr} IS NULL OR {$expr} = 0" );
	}

	public function filter_operators( Config $config, Screen $screen ): array {
		return PostFilter::number_ops( true );
	}

	public function filter_condition( Config $config, string $op, mixed $value, Screen $screen, string $alias ): ?array {
		global $wpdb;
		$wpm  = max( 1, (int) $config->setting( 'words_per_minute', 200 ) );
		$join = $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} AS {$alias} ON ({$alias}.post_id = {$wpdb->posts}.ID AND {$alias}.meta_key = %s)", WordCountMeta::KEY ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$w    = "CAST({$alias}.meta_value AS UNSIGNED)";
		// Minutes are ceil(words / wpm), so each minute value is a word range.
		$span = static fn( int $from, int $to ): string => $wpdb->prepare( "({$w} > %d AND {$w} <= %d)", ( $from - 1 ) * $wpm, $to * $wpm ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$min  = is_scalar( $value ) && is_numeric( $value ) ? (int) $value : 0;
		$both = is_array( $value ) && 2 === count( $value ) && is_numeric( $value[0] ) && is_numeric( $value[1] ) && min( $value ) >= 1;

		$where = match ( $op ) {
			'empty'   => "({$alias}.meta_value IS NULL OR {$w} = 0)",
			'eq'      => $min >= 1 ? $span( $min, $min ) : null,
			'neq'     => $min >= 1 ? "({$alias}.meta_value IS NULL OR NOT " . $span( $min, $min ) . ')' : null,
			'gt'      => $min >= 1 ? $wpdb->prepare( "{$w} > %d", $min * $wpm ) : null, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'lt'      => $min >= 1 ? $wpdb->prepare( "({$w} > 0 AND {$w} <= %d)", ( $min - 1 ) * $wpm ) : null, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'between' => $both ? $span( (int) min( $value ), (int) max( $value ) ) : null,
			default   => null,
		};
		return null === $where ? null : array(
			'join'  => $join,
			'where' => $where,
		);
	}
}
