<?php

namespace Corinthian\Columns;

use Corinthian\Screens\Screen;

/**
 * A column whose value can be changed from the list table, one row at a time
 * (inline) or across many rows (bulk).
 *
 * The controller has already checked the per-object capability (edit_post,
 * edit_user, edit_comment, edit_term) before calling save().
 */
interface Editable {

	/**
	 * How the editor should look:
	 * [ 'input' => 'text'|'textarea'|'number'|'date'|'datetime'|'select'|'multiselect'|'toggle'|'media'|'user', 'options' => [ value => label ], 'bulk' => [ 'set', 'clear', 'append', 'remove' ] ]
	 * "bulk" lists the bulk modes this column supports; omit it for inline only.
	 *
	 * @return array<string,mixed>
	 */
	public function edit_field( Config $config, Screen $screen ): array;

	/**
	 * Current value in the shape the editor expects (ids for relations, Y-m-d for dates).
	 */
	public function edit_value( int $object_id, Config $config, Screen $screen ): mixed;

	/**
	 * Validate, sanitize and store a new value.
	 *
	 * $mode is "set" for inline edits; bulk edits may also pass "clear",
	 * "append" or "remove" when edit_field() lists them.
	 *
	 * @return true|\WP_Error
	 */
	public function save( int $object_id, Config $config, Screen $screen, mixed $value, string $mode = 'set' ): bool|\WP_Error;
}
