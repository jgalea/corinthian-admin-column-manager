<?php

namespace Corinthian\Columns\Types;

use Closure;
use Corinthian\Columns\Config;
use Corinthian\Columns\Filterable;
use Corinthian\Screens\Screen;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * A PostFieldType whose rules are compiled by a PostFilter.
 */
class FilterablePostFieldType extends PostFieldType implements Filterable {

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
		private PostFilter $filter,
		?Closure $post_types = null,
		?Closure $formatter = null,
	) {
		parent::__construct( $key, $label, $group, $reader, $post_types, $formatter );
	}

	public function filter_operators( Config $config, Screen $screen ): array {
		return $this->filter->operators( $config, $screen );
	}

	public function filter_condition( Config $config, string $op, mixed $value, Screen $screen, string $alias ): ?array {
		return $this->filter->condition( $config, $op, $value, $screen, $alias );
	}
}
