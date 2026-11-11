<?php

namespace Corinthian;

use Corinthian\Admin\EditorPage;
use Corinthian\Admin\TableUi;
use Corinthian\Columns\Registry;
use Corinthian\Columns\Types\TypeLoader;
use Corinthian\Rest\SetsController;
use Corinthian\Screens\ScreenRegistry;
use Corinthian\Sets\SetRepository;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?self $instance = null;

	public readonly Registry $registry;
	public readonly SetRepository $sets;
	public readonly ScreenRegistry $screens;

	private function __construct() {
		$this->registry = new Registry();
		$this->sets     = new SetRepository();
		$this->screens  = new ScreenRegistry( $this->registry, $this->sets );
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function boot(): void {
		$plugin = self::instance();

		Schema::maybe_upgrade();
		TypeLoader::register( $plugin->registry );
		\Corinthian\Columns\Types\ScreenTypeLoader::register( $plugin->registry );
		\Corinthian\Columns\Types\Sites\SiteTypeLoader::register( $plugin->registry );

		add_action(
			'init',
			static function () use ( $plugin ) {
				/**
				 * Register extra column types.
				 */
				do_action( 'corinthian_register_types', $plugin->registry );
			},
			20
		);

		\Corinthian\Maintenance\WordCountBackfill::register();

		add_action( 'rest_api_init', array( new SetsController( $plugin ), 'register' ) );
		add_action( 'rest_api_init', array( new \Corinthian\Rest\WidthsController( $plugin ), 'register' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'corinthian import-admin-columns', \Corinthian\Cli\ImportCommand::class );
			\WP_CLI::add_command( 'corinthian backfill', \Corinthian\Cli\BackfillCommand::class );
			// List screens register on admin_init; the CLI needs them too.
			add_action(
				'init',
				static function () use ( $plugin ) {
					$plugin->screens->all();
				},
				PHP_INT_MAX
			);
		}

		if ( is_admin() ) {
			( new EditorPage() )->register();
			( new \Corinthian\Admin\ImportPage() )->register();
			( new TableUi( $plugin ) )->register();
			add_action(
				'admin_init',
				static function () use ( $plugin ) {
					foreach ( $plugin->screens->all() as $screen ) {
						$screen->register();
					}
				}
			);
		}

		/**
		 * Fires once Corinthian has finished booting.
		 */
		do_action( 'corinthian_booted', $plugin );
	}

	public static function capability(): string {
		/**
		 * Capability needed to edit column sets.
		 */
		return (string) apply_filters( 'corinthian_manage_capability', 'manage_options' );
	}
}
