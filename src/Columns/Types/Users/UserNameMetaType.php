<?php

namespace Corinthian\Columns\Types\Users;

use Corinthian\Columns\Config;
use Corinthian\Columns\Sortable;
use Corinthian\Screens\Screen;

defined( 'ABSPATH' ) || exit;

final class UserNameMetaType extends UserProfileType implements Sortable {

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		global $wpdb;
		$sub = $this->meta_subselect( $wpdb->usermeta, 'user_id', "{$wpdb->users}.ID", $this->field );
		return $this->order_by( $clauses, $sub, $order, self::blank( $sub ) );
	}
}
