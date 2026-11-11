<?php

namespace Corinthian\Columns\Types\Users;

use Corinthian\Columns\Config;
use Corinthian\Columns\Editable;
use Corinthian\Screens\Screen;
use Corinthian\Columns\Filterable;

defined( 'ABSPATH' ) || exit;

/**
 * Profile fields stored as user meta: names, biography and editing preferences.
 */
class UserProfileType extends AbstractUserType implements Filterable, Editable {

	use EditsUser;
	use FiltersField;

	public function label(): string {
		return match ( $this->field ) {
			'first_name'  => __( 'First name', 'corinthian-admin-column-manager' ),
			'last_name'   => __( 'Last name', 'corinthian-admin-column-manager' ),
			'nickname'    => __( 'Nickname', 'corinthian-admin-column-manager' ),
			'description' => __( 'Biography', 'corinthian-admin-column-manager' ),
			'rich_editing' => __( 'Visual editor', 'corinthian-admin-column-manager' ),
			'admin_color' => __( 'Admin color scheme', 'corinthian-admin-column-manager' ),
			default       => __( 'Language', 'corinthian-admin-column-manager' ),
		};
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		$value = get_user_meta( $object_id, $this->field, true );
		if ( 'rich_editing' === $this->field ) {
			return 'false' === $value ? 'false' : 'true';
		}
		if ( 'admin_color' === $this->field && '' === $value ) {
			return 'fresh';
		}
		return is_scalar( $value ) ? (string) $value : null;
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		$text = is_scalar( $value ) ? (string) $value : '';
		return match ( $this->field ) {
			'description'  => esc_html( wp_trim_words( $text, 30 ) ),
			'rich_editing' => esc_html( 'true' === $text ? __( 'Yes', 'corinthian-admin-column-manager' ) : __( 'No', 'corinthian-admin-column-manager' ) ),
			default        => esc_html( $text ),
		};
	}

	protected function filter_spec( Config $config, Screen $screen ): ?array {
		global $wpdb;
		$sub = $this->meta_subselect( $wpdb->usermeta, 'user_id', "{$wpdb->users}.ID", $this->field );
		if ( 'rich_editing' === $this->field ) {
			return array(
				'kind'    => FilterSql::ENUM,
				'expr'    => "IF({$sub} = 'false', 'false', 'true')",
				'options' => array(
					'true'  => __( 'Yes', 'corinthian-admin-column-manager' ),
					'false' => __( 'No', 'corinthian-admin-column-manager' ),
				),
			);
		}
		return array(
			'kind' => FilterSql::TEXT,
			'expr' => 'admin_color' === $this->field ? "COALESCE(NULLIF({$sub}, ''), 'fresh')" : $sub,
		);
	}
}
