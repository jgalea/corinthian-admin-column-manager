<?php

namespace Corinthian\Columns\Types\Sites;

use Corinthian\Columns\Types\Users\ScreenFieldType;
use Corinthian\Screens\Screen;
use WP_Site;

abstract class AbstractSiteType extends ScreenFieldType {

	protected function prefix(): string {
		return 'site';
	}

	public function group(): string {
		return __( 'Site', 'corinthian-admin-column-manager' );
	}

	public function supports( Screen $screen ): bool {
		return 'site' === $screen->object_type();
	}

	protected function site( int $site_id ): ?WP_Site {
		return get_site( $site_id );
	}

	protected function admin_link( int $site_id, string $text ): string {
		return $this->link( network_admin_url( 'site-info.php?id=' . $site_id ), $text );
	}
}
