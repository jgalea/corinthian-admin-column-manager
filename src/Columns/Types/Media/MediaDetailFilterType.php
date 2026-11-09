<?php

namespace Corinthian\Columns\Types\Media;

use Corinthian\Columns\Config;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Types\Users\FilterSql;
use Corinthian\Columns\Types\Users\FiltersField;
use Corinthian\Screens\Screen;

/**
 * The attachment detail columns that can be filtered: caption, description and featured image use.
 */
class MediaDetailFilterType extends MediaDetailType implements Filterable {

	use FiltersField;

	protected function filter_spec( Config $config, Screen $screen ): ?array {
		global $wpdb;
		if ( 'featured_count' === $this->field ) {
			return array(
				'kind' => FilterSql::NUMBER,
				'expr' => "(SELECT COUNT(DISTINCT pm.post_id) FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_thumbnail_id' AND pm.meta_value = CAST({$wpdb->posts}.ID AS CHAR) AND p.post_status NOT IN ('trash', 'auto-draft') AND p.post_type <> 'revision')",
			);
		}
		return array(
			'kind' => FilterSql::TEXT,
			'expr' => "{$wpdb->posts}." . ( 'caption' === $this->field ? 'post_excerpt' : 'post_content' ),
		);
	}
}
