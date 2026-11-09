<?php

namespace Corinthian\Columns;

use Corinthian\Screens\Screen;

/**
 * A kind of column. One instance per type; per-column choices live in Config.
 *
 * Optional capabilities are separate interfaces (Sortable, Filterable,
 * Editable, Exportable) so a type only implements what it supports.
 */
interface ColumnType {

	/** Unique type key, e.g. "post.word_count" or "meta". */
	public function key(): string;

	public function label(): string;

	/** Group heading in the column picker. */
	public function group(): string;

	/** Whether this type can be used on the given screen. */
	public function supports( Screen $screen ): bool;

	/**
	 * Settings the editor shows for this type, as a list of field definitions:
	 * [ 'name' => 'meta_key', 'type' => 'text'|'select'|'number'|'toggle', 'label' => ..., 'options' => [value => label], 'default' => ... ]
	 *
	 * @return list<array<string,mixed>>
	 */
	public function settings_schema( Screen $screen ): array;

	/**
	 * Raw value for an object, used by render, export and conditional formatting.
	 */
	public function value( int $object_id, Config $config, Screen $screen ): mixed;

	/**
	 * Escaped HTML for the table cell.
	 */
	public function render( int $object_id, Config $config, Screen $screen ): string;
}
