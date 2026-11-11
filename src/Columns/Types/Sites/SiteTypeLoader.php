<?php

namespace Corinthian\Columns\Types\Sites;

use Corinthian\Columns\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Column types for the network admin sites screen.
 */
final class SiteTypeLoader {

	public static function register( Registry $registry ): void {
		$types = array( new SiteNameType(), new SiteMetaType(), new SitePostCountType() );
		foreach ( array( 'id', 'domain', 'path' ) as $field ) {
			$types[] = new SiteFieldType( $field );
		}
		foreach ( array( 'registered', 'last_updated' ) as $field ) {
			$types[] = new SiteDateType( $field );
		}
		foreach ( SiteFlagType::FLAGS as $field ) {
			$types[] = in_array( $field, array( 'public', 'archived' ), true ) ? new SiteEditableFlagType( $field ) : new SiteFlagType( $field );
		}
		foreach ( array( 'language', 'admin_email' ) as $field ) {
			$types[] = new SiteOptionType( $field );
		}
		foreach ( $types as $type ) {
			$registry->add( $type );
		}
	}
}
