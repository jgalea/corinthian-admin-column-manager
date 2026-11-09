<?php
/**
 * Plugin Name:       Corinthian Admin Column Manager
 * Description:       Choose, order, resize, sort and save the columns on every WordPress admin list screen.
 * Version:           0.1.1
 * Requires at least: 6.9
 * Requires PHP:      8.1
 * Author:            Jean Galea
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       corinthian-admin-column-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CORINTHIAN_VERSION', '0.1.1' );
define( 'CORINTHIAN_FILE', __FILE__ );
define( 'CORINTHIAN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CORINTHIAN_URL', plugin_dir_url( __FILE__ ) );

$corinthian_autoload = CORINTHIAN_DIR . 'vendor/autoload.php';
if ( ! file_exists( $corinthian_autoload ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'Corinthian: run `composer install` in the plugin directory before activating.', 'corinthian-admin-column-manager' );
			echo '</p></div>';
		}
	);
	return;
}
require $corinthian_autoload;

/**
 * Define a column set in code. Call it from the corinthian_register_types action.
 *
 * @param array<string,mixed> $set key (stable, unique), screen, title, columns, settings, position.
 */
function corinthian_register_set( array $set ): void {
	\Corinthian\Sets\CodeSets::add( $set );
}

register_activation_hook( __FILE__, array( \Corinthian\Schema::class, 'install' ) );

add_action( 'plugins_loaded', array( \Corinthian\Plugin::class, 'boot' ) );
