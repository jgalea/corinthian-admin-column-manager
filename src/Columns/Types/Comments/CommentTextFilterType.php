<?php

namespace Corinthian\Columns\Types\Comments;

use Corinthian\Columns\Config;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Types\Users\FilterSql;
use Corinthian\Columns\Types\Users\FiltersField;
use Corinthian\Screens\Screen;

/**
 * The comment text columns that can be filtered: author URL, excerpt and user agent.
 */
class CommentTextFilterType extends CommentTextType implements Filterable {

	use FiltersField;

	protected function filter_spec( Config $config, Screen $screen ): ?array {
		global $wpdb;
		$column = match ( $this->field ) {
			'author_url' => 'comment_author_url',
			'excerpt'    => 'comment_content',
			default      => 'comment_agent',
		};
		return array(
			'kind' => FilterSql::TEXT,
			'expr' => "{$wpdb->comments}.{$column}",
		);
	}
}
