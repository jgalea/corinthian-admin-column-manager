<?php

namespace Corinthian\Columns;

use Corinthian\Screens\Screen;

/**
 * A Sortable type whose columns can sort only for some settings (an ACF
 * repeater can't, an ACF number can). Screens hide the sort arrow when sorts() is false.
 */
interface ConditionallySortable {

	public function sorts( Config $config, Screen $screen ): bool;
}
