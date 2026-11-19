<?php

namespace Corinthian\Tests;

use Corinthian\Columns\Config;
use Corinthian\Columns\Filterable;
use Corinthian\Plugin;
use Corinthian\Screens\PostTypeScreen;
use Corinthian\Screens\Screen;
use WP_UnitTestCase;

class FilterOperatorsTest extends WP_UnitTestCase {

	private Screen $screen;

	public function set_up(): void {
		parent::set_up();
		foreach ( Plugin::instance()->screens->all() as $screen ) {
			if ( $screen instanceof PostTypeScreen && 'post' === $screen->subtype() ) {
				$this->screen = $screen;
			}
		}
	}

	public function tear_down(): void {
		remove_all_filters( 'corinthian_filter_now' );
		parent::tear_down();
	}

	private function freeze( string $local, string $tz = 'UTC', int $week_start = 1 ): void {
		update_option( 'timezone_string', $tz );
		update_option( 'start_of_week', $week_start );
		$ts = ( new \DateTimeImmutable( $local, new \DateTimeZone( $tz ) ) )->getTimestamp();
		add_filter( 'corinthian_filter_now', static fn() => $ts );
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	private function rows( string $type_key, array $settings, string $op, mixed $value ): int {
		global $wpdb;
		$type = Plugin::instance()->registry->get( $type_key );
		$this->assertInstanceOf( Filterable::class, $type );
		$config = Config::from_array(
			array(
				'id'       => 'c1',
				'type'     => $type_key,
				'settings' => $settings,
			)
		);
		$this->assertContains( $op, array_column( $type->filter_operators( $config, $this->screen ), 'op' ), "{$type_key} offers {$op}" );
		$sql = $type->filter_condition( $config, $op, $value, $this->screen, 'f1' );
		$this->assertNotNull( $sql, "{$type_key} {$op} compiled" );
		return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT {$wpdb->posts}.ID) FROM {$wpdb->posts} {$sql['join']} WHERE {$wpdb->posts}.post_type = 'post' AND {$wpdb->posts}.post_status IN ('publish','future') AND {$sql['where']}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private function post( string $date, array $extra = array() ): int {
		return (int) self::factory()->post->create(
			array_merge(
				array(
					'post_date'   => $date,
					'post_status' => 'publish',
				),
				$extra
			)
		);
	}

	private function dates( string $op, mixed $value ): int {
		return $this->rows( 'post.published_date', array(), $op, $value );
	}

	public function test_between_is_inclusive_at_both_ends(): void {
		$this->freeze( '2026-11-18 12:00:00' );
		$this->post( '2026-11-09 23:59:59' );
		$this->post( '2026-11-10 00:00:00' );
		$this->post( '2026-11-12 13:00:00' );
		$this->post( '2026-11-14 23:59:59' );
		$this->post( '2026-11-15 00:00:00' );
		$this->assertSame( 3, $this->dates( 'between', array( '2026-11-10', '2026-11-14' ) ) );
		$this->assertSame( 3, $this->dates( 'between', array( '2026-11-14', '2026-11-10' ) ), 'reversed bounds are swapped' );
		$this->assertNull(
			Plugin::instance()->registry->get( 'post.published_date' )->filter_condition(
				Config::from_array(
					array(
						'id'   => 'c',
						'type' => 'post.published_date',
					)
				),
				'between',
				array( '2026-11-10', 'nope' ),
				$this->screen,
				'f'
			)
		);
	}

	public function test_on_a_date_covers_the_whole_day(): void {
		$this->freeze( '2026-11-18 12:00:00' );
		$this->post( '2026-11-11 23:59:59' );
		$this->post( '2026-11-12 00:00:00' );
		$this->post( '2026-11-12 23:59:59' );
		$this->post( '2026-11-13 00:00:00' );
		$this->assertSame( 2, $this->dates( 'on', '2026-11-12' ) );
	}

	public function test_today_follows_the_site_timezone(): void {
		$this->post( '2026-11-17 23:59:59' );
		$this->post( '2026-11-18 00:00:00' );
		$this->post( '2026-11-18 23:59:59' );
		$this->post( '2026-11-19 00:00:00' );

		// 23:30 UTC on the 17th is 12:30 on the 18th in Auckland.
		$this->freeze( '2026-11-18 12:30:00', 'Pacific/Auckland' );
		$this->assertSame( 2, $this->dates( 'today', '' ) );

		remove_all_filters( 'corinthian_filter_now' );
		$this->freeze( '2026-11-17 23:30:00', 'UTC' );
		$this->assertSame( 1, $this->dates( 'today', '' ), 'same instant, UTC site, still the 17th' );
	}

	public function test_future_starts_at_the_current_second(): void {
		$this->freeze( '2026-11-18 12:00:00' );
		$this->post( '2026-11-18 11:59:59' );
		$this->post( '2026-11-18 12:00:00' );
		$this->post( '2026-11-18 12:00:01', array( 'post_status' => 'future' ) );
		$this->post( '2027-01-01 00:00:00', array( 'post_status' => 'future' ) );
		$this->assertSame( 3, $this->dates( 'future', '' ) );
	}

	public function test_next_days_runs_from_today_to_the_end_of_day_n(): void {
		$this->freeze( '2026-11-18 12:00:00' );
		$this->post( '2026-11-17 23:59:59' );
		$this->post( '2026-11-18 00:00:00' );
		$this->post( '2026-11-25 23:59:59' );
		$this->post( '2026-11-26 00:00:00' );
		$this->assertSame( 2, $this->dates( 'next_days', '7' ) );
		$this->assertSame( 1, $this->dates( 'next_days', '0' ) );
	}

	/**
	 * @return array<string,array{0:int,1:list<string>}>
	 */
	public static function week_starts(): array {
		// Wednesday 18 November 2026; one post per day from Fri 13 to Tue 24.
		return array(
			'monday'   => array( 1, array( '16', '17', '18', '19', '20', '21', '22' ) ),
			'sunday'   => array( 0, array( '15', '16', '17', '18', '19', '20', '21' ) ),
			'saturday' => array( 6, array( '14', '15', '16', '17', '18', '19', '20' ) ),
		);
	}

	/**
	 * @dataProvider week_starts
	 * @param list<string> $days
	 */
	public function test_this_week_follows_start_of_week( int $start, array $days ): void {
		global $wpdb;
		$this->freeze( '2026-11-18 12:00:00', 'UTC', $start );
		for ( $d = 13; $d <= 24; $d++ ) {
			$this->post( "2026-11-{$d} 10:00:00" );
		}
		$type   = Plugin::instance()->registry->get( 'post.published_date' );
		$config = Config::from_array(
			array(
				'id'   => 'c',
				'type' => 'post.published_date',
			)
		);
		$sql    = $type->filter_condition( $config, 'this_week', '', $this->screen, 'f' );
		$got    = $wpdb->get_col( "SELECT DAY(post_date) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status IN ('publish','future') AND {$sql['where']} ORDER BY post_date" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->assertSame( $days, $got );
	}

	public function test_this_month_uses_calendar_month_edges(): void {
		$this->freeze( '2026-11-18 12:00:00' );
		$this->post( '2026-10-31 23:59:59' );
		$this->post( '2026-11-01 00:00:00' );
		$this->post( '2026-11-30 23:59:59' );
		$this->post( '2026-12-01 00:00:00' );
		$this->assertSame( 2, $this->dates( 'this_month', '' ) );
	}

	public function test_meta_dates_stored_as_ymd_and_as_timestamps(): void {
		$this->freeze( '2026-11-18 12:00:00', 'Europe/Lisbon' );
		$ymd   = static fn( string $d ) => $d;
		$stamp = static fn( string $d ) => (string) ( new \DateTimeImmutable( $d . ' 00:00:00', new \DateTimeZone( 'Europe/Lisbon' ) ) )->getTimestamp();
		foreach ( array( '2026-11-09', '2026-11-10', '2026-11-14', '2026-11-15' ) as $d ) {
			$a = $this->post( '2026-01-01 00:00:00' );
			update_post_meta( $a, 'when', $ymd( $d ) );
			$b = $this->post( '2026-01-01 00:00:00' );
			update_post_meta( $b, 'when', $stamp( $d ) );
		}
		$none = $this->post( '2026-01-01 00:00:00' );
		$this->assertNotSame( 0, $none );
		$s = array(
			'meta_key' => 'when',
			'display'  => 'date',
		);
		$this->assertSame( 4, $this->rows( 'post.meta', $s, 'between', array( '2026-11-10', '2026-11-14' ) ) );
		$this->assertSame( 2, $this->rows( 'post.meta', $s, 'on', '2026-11-14' ) );
		$this->assertSame( 0, $this->rows( 'post.meta', $s, 'today', '' ) );
		$this->assertSame( 0, $this->rows( 'post.meta', $s, 'future', '' ) );
		$late = $this->post( '2026-01-01 00:00:00' );
		update_post_meta( $late, 'when', '2026-11-18' );
		update_post_meta( $this->post( '2026-01-01 00:00:00' ), 'when', '2026-11-19' );
		$this->assertSame( 1, $this->rows( 'post.meta', $s, 'today', '' ) );
		$this->assertSame( 1, $this->rows( 'post.meta', $s, 'future', '' ), 'a date-only value for today is not in the future' );
		$this->assertSame( 2, $this->rows( 'post.meta', $s, 'next_days', '1' ) );
		$this->assertSame( 10, $this->rows( 'post.meta', $s, 'this_month', '' ) );
	}

	public function test_old_date_operators_still_compile(): void {
		$this->freeze( '2026-11-18 12:00:00' );
		$this->post( '2026-11-10 10:00:00' );
		$this->assertSame( 1, $this->dates( 'before', '2026-11-11' ) );
		$this->assertSame( 1, $this->dates( 'after', '2026-11-09' ) );
		$ops = array_column( \Corinthian\Columns\Types\PostFilter::date_ops(), 'op' );
		$this->assertSame( array( 'before', 'after', 'within_days', 'older_than_days' ), array_slice( $ops, 0, 4 ) );
	}

	public function test_title_column_filters_with_every_text_operator(): void {
		$this->post( '2026-11-10 10:00:00', array( 'post_title' => 'Alpha Report' ) );
		$this->post( '2026-11-10 10:00:00', array( 'post_title' => 'Beta Report' ) );
		$this->post( '2026-11-10 10:00:00', array( 'post_title' => 'Alpha' ) );
		$this->post( '2026-11-10 10:00:00', array( 'post_title' => 'Gamma' ) );
		$settings = array( 'key' => 'title' );
		$this->assertSame( 2, $this->rows( 'native', $settings, 'contains', 'Report' ) );
		$this->assertSame( 2, $this->rows( 'native', $settings, 'not_contains', 'Report' ) );
		$this->assertSame( 1, $this->rows( 'native', $settings, 'eq', 'Alpha' ) );
		$this->assertSame( 3, $this->rows( 'native', $settings, 'neq', 'Alpha' ) );
		$this->assertSame( 2, $this->rows( 'native', $settings, 'starts', 'Alpha' ) );
		$this->assertSame( 2, $this->rows( 'native', $settings, 'ends', 'Report' ) );
		$this->assertSame( 1, $this->rows( 'native', $settings, 'ends', 'Gamma' ) );

		$native = Plugin::instance()->registry->get( 'native' );
		$other  = Config::from_array(
			array(
				'id'       => 'c',
				'type'     => 'native',
				'settings' => array( 'key' => 'date' ),
			)
		);
		$this->assertSame( array(), $native->filter_operators( $other, $this->screen ), 'other default columns stay unfilterable' );
	}

	public function test_ends_with_on_slug_and_text_meta(): void {
		$a = $this->post( '2026-11-10 10:00:00', array( 'post_name' => 'one-final' ) );
		$this->post( '2026-11-10 10:00:00', array( 'post_name' => 'final-two' ) );
		update_post_meta( $a, 'note', 'see you in Lisbon' );
		$this->assertSame( 1, $this->rows( 'post.slug', array(), 'ends', 'final' ) );
		$this->assertSame( 1, $this->rows( 'post.meta', array( 'meta_key' => 'note' ), 'ends', 'Lisbon' ) );
		$this->assertSame( 0, $this->rows( 'post.meta', array( 'meta_key' => 'note' ), 'ends', 'see' ) );
		$this->assertSame( 0, $this->rows( 'post.meta', array( 'meta_key' => 'note' ), 'ends', '%' ), 'LIKE wildcards are escaped' );
	}

	public function test_author_empty_and_not_empty(): void {
		global $wpdb;
		$user = self::factory()->user->create();
		$this->post( '2026-11-10 10:00:00', array( 'post_author' => $user ) );
		$orphan = $this->post( '2026-11-10 10:00:00' );
		$wpdb->update( $wpdb->posts, array( 'post_author' => 0 ), array( 'ID' => $orphan ) );
		$gone = $this->post( '2026-11-10 10:00:00' );
		$wpdb->update( $wpdb->posts, array( 'post_author' => 999999 ), array( 'ID' => $gone ) );
		$this->assertSame( 2, $this->rows( 'post.author', array(), 'empty', null ) );
		$this->assertSame( 1, $this->rows( 'post.author', array(), 'not_empty', null ) );
		$this->assertSame( 1, $this->rows( 'post.author', array(), 'in', array( (string) $user ) ) );
	}

	public function test_boolean_meta_empty_and_not_empty(): void {
		$s = array(
			'meta_key' => 'flag',
			'display'  => 'boolean',
		);
		update_post_meta( $this->post( '2026-11-10 10:00:00' ), 'flag', '1' );
		update_post_meta( $this->post( '2026-11-10 10:00:00' ), 'flag', '0' );
		update_post_meta( $this->post( '2026-11-10 10:00:00' ), 'flag', '' );
		$this->post( '2026-11-10 10:00:00' );
		$this->assertSame( 2, $this->rows( 'post.meta', $s, 'not_empty', null ) );
		$this->assertSame( 2, $this->rows( 'post.meta', $s, 'empty', null ) );
		$this->assertSame( 1, $this->rows( 'post.meta', $s, 'eq', '1' ) );
	}

	public function test_number_operators_read_as_words_and_keep_their_keys(): void {
		$ops = \Corinthian\Columns\Types\PostFilter::number_ops( true );
		$this->assertSame( array( 'eq', 'neq', 'gt', 'lt', 'between', 'empty' ), array_column( $ops, 'op' ) );
		$this->assertSame( array( 'equals', 'does not equal', 'is greater than', 'is less than', 'is between', 'is empty' ), array_column( $ops, 'label' ) );
		$s = array(
			'meta_key' => 'price',
			'display'  => 'number',
		);
		update_post_meta( $this->post( '2026-11-10 10:00:00' ), 'price', '5' );
		update_post_meta( $this->post( '2026-11-10 10:00:00' ), 'price', '15' );
		$this->assertSame( 1, $this->rows( 'post.meta', $s, 'gt', '10' ) );
		$this->assertSame( 2, $this->rows( 'post.meta', $s, 'between', array( '5', '15' ) ) );
	}
}
