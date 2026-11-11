<?php

namespace Corinthian\Columns\Types;

use Closure;
use Corinthian\Columns\Editable;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class EditableSortablePostFieldType extends SortablePostFieldType implements Editable {

	use EditsPost;

	/**
	 * @param Closure():string                                                       $label
	 * @param Closure(WP_Post,\Corinthian\Columns\Config,\Corinthian\Screens\Screen):mixed $reader
	 * @param (Closure(string):bool)|null                                            $post_types
	 * @param (Closure(mixed,int,\Corinthian\Columns\Config,\Corinthian\Screens\Screen):string)|null $formatter
	 */
	public function __construct(
		private string $kind,
		string $key,
		Closure $label,
		string $group,
		Closure $reader,
		string $column,
		PostFilter $filter,
		bool $empty_last = false,
		?Closure $post_types = null,
		?Closure $formatter = null,
	) {
		parent::__construct( $key, $label, $group, $reader, $column, $filter, $empty_last, $post_types, $formatter );
	}

	protected function edit_kind(): string {
		return $this->kind;
	}
}
