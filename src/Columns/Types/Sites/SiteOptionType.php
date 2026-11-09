<?php

namespace Corinthian\Columns\Types\Sites;

use Corinthian\Columns\Config;
use Corinthian\Screens\Screen;

/**
 * Read-only options of a site: language and admin email.
 */
final class SiteOptionType extends AbstractSiteType {

	public function label(): string {
		return 'language' === $this->field ? __( 'Language', 'corinthian-admin-column-manager' ) : __( 'Admin email', 'corinthian-admin-column-manager' );
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		if ( ! $this->site( $object_id ) ) {
			return null;
		}
		if ( 'language' === $this->field ) {
			$locale = (string) get_blog_option( $object_id, 'WPLANG', '' );
			return '' === $locale ? 'en_US' : $locale;
		}
		return (string) get_blog_option( $object_id, 'admin_email', '' );
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		$text = is_scalar( $value ) ? (string) $value : '';
		return 'admin_email' === $this->field ? $this->link( 'mailto:' . $text, $text ) : esc_html( $text );
	}
}
