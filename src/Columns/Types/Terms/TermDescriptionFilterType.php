<?php

namespace Corinthian\Columns\Types\Terms;

use Corinthian\Columns\Config;
use Corinthian\Columns\Editable;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Types\Users\FilterSql;
use Corinthian\Columns\Types\Users\FiltersField;
use Corinthian\Screens\Screen;

defined( 'ABSPATH' ) || exit;

final class TermDescriptionFilterType extends TermTextType implements Filterable, Editable {

	use EditsTerm;
	use FiltersField;

	protected function filter_spec( Config $config, Screen $screen ): ?array {
		return array(
			'kind' => FilterSql::TEXT,
			'expr' => 'tt.description',
		);
	}
}
