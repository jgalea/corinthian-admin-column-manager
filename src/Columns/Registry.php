<?php

namespace Corinthian\Columns;

use Corinthian\Screens\Screen;

defined( 'ABSPATH' ) || exit;

final class Registry {

	/** @var array<string,ColumnType> */
	private array $types = array();

	public function add( ColumnType $type ): void {
		$this->types[ $type->key() ] = $type;
	}

	public function get( string $key ): ?ColumnType {
		return $this->types[ $key ] ?? null;
	}

	/**
	 * @return array<string,ColumnType>
	 */
	public function for_screen( Screen $screen ): array {
		return array_filter( $this->types, static fn( ColumnType $t ) => $t->supports( $screen ) );
	}
}
