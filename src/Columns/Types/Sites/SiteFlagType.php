<?php

namespace Corinthian\Columns\Types\Sites;

use Corinthian\Columns\Config;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Types\Users\FilterSql;
use Corinthian\Screens\Screen;

defined( 'ABSPATH' ) || exit;

/**
 * The status flags on the blogs table: public, archived, spam, deleted, mature.
 */
class SiteFlagType extends AbstractSiteType implements Filterable {

	public const FLAGS = array( 'public', 'archived', 'spam', 'deleted', 'mature' );

	public function label(): string {
		return match ( $this->field ) {
			'public'   => __( 'Public', 'corinthian-admin-column-manager' ),
			'archived' => __( 'Archived', 'corinthian-admin-column-manager' ),
			'spam'     => __( 'Spam', 'corinthian-admin-column-manager' ),
			'deleted'  => __( 'Deleted', 'corinthian-admin-column-manager' ),
			default    => __( 'Mature', 'corinthian-admin-column-manager' ),
		};
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		$site = $this->site( $object_id );
		return $site ? '1' === (string) $site->{$this->field} : null;
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		return $value
			? '<span class="dashicons dashicons-yes" aria-label="' . esc_attr__( 'Yes', 'corinthian-admin-column-manager' ) . '"></span>'
			: '<span class="dashicons dashicons-no-alt" aria-label="' . esc_attr__( 'No', 'corinthian-admin-column-manager' ) . '"></span>';
	}

	public function filter_operators( Config $config, Screen $screen ): array {
		return FilterSql::operators( FilterSql::BOOL );
	}

	public function filter_condition( Config $config, string $op, mixed $value, Screen $screen, string $alias ): ?array {
		global $wpdb;
		$value = is_bool( $value ) ? (string) (int) $value : $value;
		if ( 'eq' !== $op || ! in_array( $value, array( '0', '1', 0, 1 ), true ) ) {
			return null;
		}
		return array(
			'join'  => '',
			'where' => $wpdb->prepare( "{$wpdb->blogs}.{$this->field} = %d", (int) $value ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}
}
