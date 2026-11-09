<?php

namespace Corinthian\Admin;

use Corinthian\Plugin;
use Corinthian\Rest\SetsController;

final class EditorPage {

	public const SLUG = 'corinthian';

	private string $hook = '';

	public static function url( string $screen = '', int $set = 0 ): string {
		$args = array( 'page' => self::SLUG );
		if ( '' !== $screen ) {
			$args['screen'] = $screen;
		}
		if ( $set ) {
			$args['set'] = $set;
		}
		$base = str_starts_with( $screen, 'network-' ) ? network_admin_url( 'settings.php' ) : admin_url( 'options-general.php' );
		return add_query_arg( $args, $base );
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'network_admin_menu', array( $this, 'network_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function menu(): void {
		$this->hook = (string) add_options_page(
			__( 'Columns', 'corinthian-admin-column-manager' ),
			__( 'Columns', 'corinthian-admin-column-manager' ),
			Plugin::capability(),
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function network_menu(): void {
		$this->hook = (string) add_submenu_page(
			'settings.php',
			__( 'Columns', 'corinthian-admin-column-manager' ),
			__( 'Columns', 'corinthian-admin-column-manager' ),
			'manage_network_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function enqueue( string $hook ): void {
		if ( $hook !== $this->hook ) {
			return;
		}
		$asset_file = CORINTHIAN_DIR . 'assets/build/editor.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;
		wp_enqueue_script( 'corinthian-editor', CORINTHIAN_URL . 'assets/build/editor.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'corinthian-editor', 'corinthian-admin-column-manager' );
		if ( file_exists( CORINTHIAN_DIR . 'assets/build/editor.css' ) ) {
			wp_enqueue_style( 'corinthian-editor', CORINTHIAN_URL . 'assets/build/editor.css', array( 'wp-components' ), $asset['version'] );
		}
		wp_enqueue_style( 'wp-components' );

		$roles = array();
		foreach ( wp_roles()->get_names() as $slug => $name ) {
			$roles[] = array(
				'value' => $slug,
				'label' => translate_user_role( $name ),
			);
		}

		$data = array(
			'namespace' => SetsController::NAMESPACE,
			'roles'     => $roles,
			'initial'   => array(
				// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only UI state.
				'screen' => sanitize_text_field( wp_unslash( $_GET['screen'] ?? '' ) ),
				'set'    => absint( $_GET['set'] ?? 0 ),
				// phpcs:enable
			),
		);
		/**
		 * Filters the data printed as window.corinthianEditor.
		 */
		$data = apply_filters( 'corinthian_editor_payload', $data );
		wp_add_inline_script( 'corinthian-editor', 'window.corinthianEditor = ' . wp_json_encode( $data ) . ';', 'before' );
		do_action( 'corinthian_editor_enqueue' );
	}

	public function render(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'Columns', 'corinthian-admin-column-manager' ) . '</h1>';
		if ( ! file_exists( CORINTHIAN_DIR . 'assets/build/editor.asset.php' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Corinthian: run `npm run build` to build the column editor.', 'corinthian-admin-column-manager' ) . '</p></div>';
		}
		echo '<div id="corinthian-editor"></div></div>';
	}
}
