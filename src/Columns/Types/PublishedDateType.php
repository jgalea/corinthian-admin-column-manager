<?php

namespace Corinthian\Columns\Types;

use Corinthian\Columns\Editable;

defined( 'ABSPATH' ) || exit;

final class PublishedDateType extends DateType implements Editable {

	use EditsPost;

	public function __construct() {
		parent::__construct( 'date' );
	}

	protected function edit_kind(): string {
		return 'date';
	}
}
