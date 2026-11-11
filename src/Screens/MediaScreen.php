<?php

namespace Corinthian\Screens;

defined( 'ABSPATH' ) || exit;

/**
 * The media library in list mode. Attachments are posts, so sorting and post
 * column types work the same way as on other post screens.
 */
final class MediaScreen extends PostTypeScreen {

	public function key(): string {
		return 'media';
	}

	public function screen_id(): string {
		return 'upload';
	}

	public function url(): string {
		return admin_url( 'upload.php?mode=list' );
	}

	protected function render_hook(): string {
		return 'manage_media_custom_column';
	}
}
