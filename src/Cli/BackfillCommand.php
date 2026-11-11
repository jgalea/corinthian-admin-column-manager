<?php

namespace Corinthian\Cli;

use Corinthian\Maintenance\WordCountBackfill;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

final class BackfillCommand {

	/**
	 * Store word counts for every post that doesn't have one yet.
	 *
	 * ## EXAMPLES
	 *
	 *     wp corinthian backfill
	 *
	 * @param list<string>         $args
	 * @param array<string,string> $assoc_args
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$total = 0;
		do {
			$done   = WordCountBackfill::run_batch();
			$total += $done;
		} while ( WordCountBackfill::BATCH === $done );
		WP_CLI::success( sprintf( 'Stored word counts for %d posts.', $total ) );
	}
}
