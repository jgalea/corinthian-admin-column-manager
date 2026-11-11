<?php

namespace Corinthian\Columns\Types\Sites;

use Corinthian\Columns\Config;
use Corinthian\Screens\Screen;

defined( 'ABSPATH' ) || exit;

/**
 * Published posts of a site. Each count switches to the site, so results are
 * cached for five minutes and a single request looks up at most LOOKUP_CAP
 * sites; rows beyond that show a dash instead of timing out an export.
 */
final class SitePostCountType extends AbstractSiteType {

	public const LOOKUP_CAP  = 200;
	public const CACHE_GROUP = 'corinthian_site_counts';

	private int $lookups = 0;

	public function __construct() {
		parent::__construct( 'post_count' );
	}

	public function label(): string {
		return __( 'Post count', 'corinthian-admin-column-manager' );
	}

	public function settings_schema( Screen $screen ): array {
		return array(
			array(
				'name'    => 'post_type',
				'type'    => 'text',
				'label'   => __( 'Post type', 'corinthian-admin-column-manager' ),
				'default' => 'post',
			),
		);
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		if ( ! $this->site( $object_id ) ) {
			return null;
		}
		$type  = sanitize_key( (string) $config->setting( 'post_type', 'post' ) );
		$type  = '' === $type ? 'post' : $type;
		$key   = $object_id . ':' . $type;
		$count = wp_cache_get( $key, self::CACHE_GROUP );
		if ( false !== $count ) {
			return (int) $count;
		}
		if ( $this->lookups >= self::LOOKUP_CAP ) {
			return null;
		}
		++$this->lookups;
		switch_to_blog( $object_id );
		try {
			$count = (int) ( wp_count_posts( $type )->publish ?? 0 );
		} finally {
			restore_current_blog();
		}
		wp_cache_set( $key, $count, self::CACHE_GROUP, 5 * MINUTE_IN_SECONDS );
		return $count;
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		return esc_html( number_format_i18n( is_numeric( $value ) ? (int) $value : 0 ) );
	}
}
