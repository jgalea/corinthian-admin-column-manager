<?php

namespace Corinthian\Columns\Types\Users;

use Corinthian\Columns\Config;
use Corinthian\Screens\Screen;

/**
 * Filterable behaviour for types that describe their filter in one array.
 *
 * kind:    one of the FilterSql kinds
 * expr:    single-valued SQL expression (column or scalar sub-select)
 * meta:    instead of expr, a meta row lookup for FilterSql::meta()
 * options: enumeration values => labels
 * map:     enumeration value => raw database values
 *
 * @phpstan-type Spec array{kind:string,expr?:string,meta?:array{table:string,id_column:string,outer_id:string,key:string,template?:string},options?:array<string,string>,map?:array<string,list<string>>,datetime?:bool}
 */
trait FiltersField {

	/**
	 * @return Spec|null
	 */
	abstract protected function filter_spec( Config $config, Screen $screen ): ?array;

	protected function before_filter(): void {}

	public function filter_operators( Config $config, Screen $screen ): array {
		$spec = $this->filter_spec( $config, $screen );
		if ( ! $spec ) {
			return array();
		}
		return FilterSql::operators( $spec['kind'], $spec['options'] ?? array() );
	}

	public function filter_condition( Config $config, string $op, mixed $value, Screen $screen, string $alias ): ?array {
		$spec = $this->filter_spec( $config, $screen );
		if ( ! $spec ) {
			return null;
		}
		$this->before_filter();
		if ( isset( $spec['meta'] ) ) {
			$where = FilterSql::meta( $spec['kind'], $op, $value, $spec['meta'], $alias );
		} else {
			$where = FilterSql::column( $spec['kind'], $op, $value, $spec['expr'] ?? '', $spec['options'] ?? array(), $spec['map'] ?? array(), $spec['datetime'] ?? true );
		}
		return null === $where ? null : array(
			'join'  => '',
			'where' => $where,
		);
	}
}
