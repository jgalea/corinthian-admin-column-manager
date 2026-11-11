<?php

namespace Corinthian\Cli;

use Corinthian\Import\AdminColumnsImporter;
use Corinthian\Plugin;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

final class ImportCommand {

	/**
	 * Import column views saved by the Admin Columns plugin.
	 *
	 * Running it again updates the sets it created before instead of adding duplicates.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show what would be imported without saving anything.
	 *
	 * [--roles=<roles>]
	 * : Comma-separated roles that should also see views limited to specific users or roles.
	 *
	 * ## EXAMPLES
	 *
	 *     wp corinthian import-admin-columns --dry-run
	 *     wp corinthian import-admin-columns --roles=administrator
	 *
	 * @param list<string>          $args
	 * @param array<string,string>  $assoc_args
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$dry_run = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$roles   = array_values( array_filter( array_map( 'sanitize_key', explode( ',', (string) ( $assoc_args['roles'] ?? '' ) ) ) ) );
		foreach ( $roles as $role ) {
			if ( ! get_role( $role ) ) {
				WP_CLI::error( sprintf( 'Unknown role: %s', $role ) );
			}
		}
		$report = ( new AdminColumnsImporter( Plugin::instance() ) )->run( $dry_run, $roles );

		if ( ! $report ) {
			WP_CLI::warning( 'No Admin Columns views found.' );
			return;
		}

		$rows = array();
		foreach ( $report as $entry ) {
			$rows[] = array(
				'source'   => $entry['source'],
				'screen'   => $entry['screen'],
				'status'   => $entry['status'],
				'set'      => $entry['set_id'] ? '#' . $entry['set_id'] : '',
				'imported' => count( $entry['imported'] ),
				'skipped'  => implode( ', ', $entry['skipped'] ),
			);
		}
		WP_CLI\Utils\format_items( 'table', $rows, array( 'source', 'screen', 'status', 'set', 'imported', 'skipped' ) );
		WP_CLI::success( $dry_run ? 'Dry run finished; nothing was saved.' : 'Import finished.' );
	}
}
