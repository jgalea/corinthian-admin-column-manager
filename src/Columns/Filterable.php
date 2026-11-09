<?php

namespace Corinthian\Columns;

use Corinthian\Screens\Screen;

/**
 * A column that can narrow the list with smart-filter rules.
 *
 * Filters always run in SQL. A rule becomes one boolean SQL expression plus
 * any joins it needs; the screen combines several rules with AND or OR.
 */
interface Filterable {

	/**
	 * Operators this column offers, in display order. Each entry:
	 * [ 'op' => 'contains', 'label' => 'contains', 'input' => 'text'|'number'|'date'|'select'|'none', 'options' => [ value => label ] ]
	 *
	 * Standard op names (reuse them so the UI and AI filters can rely on them):
	 * eq, neq, contains, not_contains, starts, ends, gt, gte, lt, lte, between,
	 * before, after, within_days, older_than_days, empty, not_empty, in, not_in.
	 * "between" takes [ min, max ]; "in"/"not_in" take a list.
	 *
	 * @return list<array<string,mixed>>
	 */
	public function filter_operators( Config $config, Screen $screen ): array;

	/**
	 * SQL for one rule, or null when the op or value is invalid (the rule is
	 * then ignored). Values must go through $wpdb->prepare(). Use $alias for
	 * any table you join so the same column can appear in several rules.
	 *
	 * Main table names follow the Sortable docblock: posts, users and comments
	 * use the full table name; terms use "t" with term_taxonomy "tt".
	 *
	 * @return array{join:string,where:string}|null
	 */
	public function filter_condition( Config $config, string $op, mixed $value, Screen $screen, string $alias ): ?array;
}
