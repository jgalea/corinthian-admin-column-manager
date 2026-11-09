<?php

namespace Corinthian\Columns;

use Corinthian\Screens\Screen;

interface Sortable {

	/**
	 * Change the screen's SQL clauses so results order by this column.
	 *
	 * $clauses always has the keys join, where, orderby and groupby. orderby
	 * holds the expression only, without the ORDER BY keyword. Each screen maps
	 * these onto its own query (posts_clauses, comments_clauses, terms_clauses,
	 * pre_user_query). Main table aliases: posts use the full table name,
	 * users the full table name, comments the full table name, terms "t" with
	 * term_taxonomy "tt".
	 * Sorting always happens in SQL, never by loading every row into PHP.
	 *
	 * @param array<string,string> $clauses
	 * @param 'ASC'|'DESC'         $order
	 * @return array<string,string>
	 */
	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array;
}
