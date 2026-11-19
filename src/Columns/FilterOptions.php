<?php

namespace Corinthian\Columns;

use Corinthian\Screens\Screen;

defined( 'ABSPATH' ) || exit;

/**
 * A Filterable column whose values can be searched and paged, for columns
 * with too many values to list in full (authors, terms, distinct meta values).
 *
 * Operators backed by it carry 'search' => true. On "in" and "not_in" that
 * means 'options' holds only the first INLINE values and the rest come from
 * filter_options(). On a text operator it means typed values can be
 * completed from filter_options().
 */
interface FilterOptions {

	/**
	 * Up to this many values ship inline with the operators. A text custom
	 * field with this many distinct values or fewer is offered as a pick list.
	 */
	public const INLINE = 50;

	/**
	 * One page of values matching $search, in display order.
	 *
	 * @return array{options:array<string,string>,more:bool} value => label, and whether a later page exists.
	 */
	public function filter_options( Config $config, Screen $screen, string $search, int $page, int $per_page ): array;

	/**
	 * Labels for values picked earlier, wherever they fall in the list.
	 * Values that no longer exist are left out.
	 *
	 * @param list<string> $values
	 * @return array<string,string>
	 */
	public function filter_labels( Config $config, Screen $screen, array $values ): array;
}
