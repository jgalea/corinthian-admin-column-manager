<?php

namespace Corinthian\Columns\Types;

use Closure;
use Corinthian\Columns\Config;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Sortable;
use Corinthian\Screens\Screen;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * A PostFieldType that sorts by one column of the posts table.
 */
class SortablePostFieldType extends PostFieldType implements Sortable, Filterable {

	/**
	 * @param Closure():string                                $label
	 * @param Closure(WP_Post,Config,Screen):mixed            $reader
	 * @param (Closure(string):bool)|null                     $post_types
	 * @param (Closure(mixed,int,Config,Screen):string)|null  $formatter
	 */
	public function __construct(
		string $key,
		Closure $label,
		string $group,
		Closure $reader,
		private string $column,
		private PostFilter $filter,
		private bool $empty_last = false,
		?Closure $post_types = null,
		?Closure $formatter = null,
	) {
		parent::__construct( $key, $label, $group, $reader, $post_types, $formatter );
	}

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		global $wpdb;
		$col   = "{$wpdb->posts}.{$this->column}";
		$empty = $this->empty_last ? "{$col} = ''" : '';
		return $this->order_by( $clauses, $col, $order, $empty );
	}

	public function filter_operators( Config $config, Screen $screen ): array {
		return $this->filter->operators( $config, $screen );
	}

	public function filter_condition( Config $config, string $op, mixed $value, Screen $screen, string $alias ): ?array {
		return $this->filter->condition( $config, $op, $value, $screen, $alias );
	}
}
