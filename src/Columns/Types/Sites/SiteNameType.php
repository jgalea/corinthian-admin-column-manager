<?php

namespace Corinthian\Columns\Types\Sites;

use Corinthian\Columns\Config;
use Corinthian\Columns\Editable;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Types\Users\FilterSql;
use Corinthian\Screens\Screen;
use WP_Error;

/**
 * The blogname option. It lives in each site's own options table, so the
 * filter joins a UNION of those tables, limited to the first SITE_CAP sites.
 */
final class SiteNameType extends AbstractSiteType implements Filterable, Editable {

	public const SITE_CAP = 500;

	public function __construct() {
		parent::__construct( 'name' );
	}

	public function label(): string {
		return __( 'Site name', 'corinthian-admin-column-manager' );
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		if ( ! $this->site( $object_id ) ) {
			return null;
		}
		return (string) get_blog_option( $object_id, 'blogname', '' );
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		return $this->admin_link( $object_id, is_scalar( $value ) ? (string) $value : '' );
	}

	public function filter_operators( Config $config, Screen $screen ): array {
		return FilterSql::operators( FilterSql::TEXT );
	}

	public function filter_condition( Config $config, string $op, mixed $value, Screen $screen, string $alias ): ?array {
		global $wpdb;
		$alias = (string) preg_replace( '/[^A-Za-z0-9_]/', '', $alias );
		$where = FilterSql::column( FilterSql::TEXT, $op, $value, "{$alias}.option_value" );
		if ( null === $where ) {
			return null;
		}
		return array(
			'join'  => 'LEFT JOIN (' . $this->names_union() . ") {$alias} ON {$alias}.blog_id = {$wpdb->blogs}.blog_id",
			'where' => $where,
		);
	}

	private function names_union(): string {
		global $wpdb;
		$ids    = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT blog_id FROM {$wpdb->blogs} WHERE site_id = %d ORDER BY blog_id ASC LIMIT %d", get_current_network_id(), self::SITE_CAP ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Network-wide site list, no core API returns names in one query.
		$tables = array();
		foreach ( $ids as $id ) {
			$tables[ $wpdb->get_blog_prefix( $id ) . 'options' ] = $id;
		}
		$existing = array();
		if ( $tables ) {
			$marks    = implode( ',', array_fill( 0, count( $tables ), '%s' ) );
			$existing = (array) $wpdb->get_col( $wpdb->prepare( "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ({$marks})", array_keys( $tables ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- information_schema lookup; the %s list is generated from the table count.
		}
		$parts = array();
		foreach ( $existing as $table ) {
			$table = (string) $table;
			if ( isset( $tables[ $table ] ) ) {
				$parts[] = $wpdb->prepare( "SELECT %d AS blog_id, option_value FROM `{$table}` WHERE option_name = 'blogname'", $tables[ $table ] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}
		return $parts ? implode( ' UNION ALL ', $parts ) : "SELECT 0 AS blog_id, '' AS option_value FROM DUAL WHERE 1 = 0";
	}

	public function edit_field( Config $config, Screen $screen ): array {
		return array(
			'input' => 'text',
			'bulk'  => array( 'set' ),
		);
	}

	public function edit_value( int $object_id, Config $config, Screen $screen ): mixed {
		return $this->value( $object_id, $config, $screen );
	}

	public function save( int $object_id, Config $config, Screen $screen, mixed $value, string $mode = 'set' ): bool|WP_Error {
		if ( 'set' !== $mode ) {
			return new WP_Error( 'corinthian_bad_mode', __( 'This column does not support that edit.', 'corinthian-admin-column-manager' ) );
		}
		if ( ! current_user_can( 'manage_sites' ) || ! $this->site( $object_id ) ) {
			return new WP_Error( 'corinthian_forbidden', __( 'You cannot edit this item.', 'corinthian-admin-column-manager' ) );
		}
		$name = is_scalar( $value ) ? trim( sanitize_text_field( (string) $value ) ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'corinthian_invalid', __( 'Enter a site name.', 'corinthian-admin-column-manager' ) );
		}
		if ( (string) get_blog_option( $object_id, 'blogname', '' ) === $name ) {
			return true;
		}
		if ( ! update_blog_option( $object_id, 'blogname', $name ) ) {
			return new WP_Error( 'corinthian_failed', __( 'The site could not be updated.', 'corinthian-admin-column-manager' ) );
		}
		return true;
	}
}
