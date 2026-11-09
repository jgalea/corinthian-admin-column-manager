<?php

namespace Corinthian\Columns\Types\Users;

use Corinthian\Columns\Config;
use Corinthian\Columns\Sortable;
use Corinthian\Screens\Screen;
use Corinthian\Columns\Filterable;

/**
 * Columns that live on the users table.
 */
class UserFieldType extends AbstractUserType implements Sortable, Filterable {

	use FiltersField;

	private const COLUMNS = array(
		'id'           => 'ID',
		'username'     => 'user_login',
		'display_name' => 'display_name',
		'email'        => 'user_email',
		'website'      => 'user_url',
	);

	public function label(): string {
		return match ( $this->field ) {
			'id'           => __( 'User ID', 'corinthian-admin-column-manager' ),
			'username'     => __( 'Username', 'corinthian-admin-column-manager' ),
			'display_name' => __( 'Display name', 'corinthian-admin-column-manager' ),
			'email'        => __( 'Email', 'corinthian-admin-column-manager' ),
			default        => __( 'Website', 'corinthian-admin-column-manager' ),
		};
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		$user = get_userdata( $object_id );
		if ( ! $user ) {
			return null;
		}
		$column = self::COLUMNS[ $this->field ];
		return 'id' === $this->field ? (int) $user->ID : (string) $user->$column;
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		$text = is_scalar( $value ) ? (string) $value : '';
		return match ( $this->field ) {
			'email'   => $this->link( 'mailto:' . $text, $text ),
			'website' => $this->link( $text, $text, true ),
			default   => esc_html( $text ),
		};
	}

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		global $wpdb;
		$column = "{$wpdb->users}." . self::COLUMNS[ $this->field ];
		return $this->order_by( $clauses, $column, $order, 'id' === $this->field ? null : self::blank( $column ) );
	}

	protected function filter_spec( Config $config, Screen $screen ): ?array {
		global $wpdb;
		return array(
			'kind' => 'id' === $this->field ? FilterSql::NUMBER : FilterSql::TEXT,
			'expr' => "{$wpdb->users}." . self::COLUMNS[ $this->field ],
		);
	}
}
