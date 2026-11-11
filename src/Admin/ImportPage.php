<?php

namespace Corinthian\Admin;

use Corinthian\Import\AdminColumnsImporter;
use Corinthian\Plugin;

defined( 'ABSPATH' ) || exit;

final class ImportPage {

	public const SLUG = 'corinthian-import';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	public static function url(): string {
		return add_query_arg( 'page', self::SLUG, admin_url( 'options.php' ) );
	}

	public static function available(): bool {
		return array() !== ( new AdminColumnsImporter( Plugin::instance() ) )->find_views();
	}

	public function menu(): void {
		add_submenu_page(
			'options.php',
			__( 'Import from Admin Columns', 'corinthian-admin-column-manager' ),
			'',
			Plugin::capability(),
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		$importer = new AdminColumnsImporter( Plugin::instance() );
		$report   = null;
		$imported = false;
		$roles    = array();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified below with check_admin_referer().
		if ( isset( $_POST['corinthian_import_mode'] ) ) {
			check_admin_referer( 'corinthian_import' );
			$imported = 'import' === sanitize_key( wp_unslash( $_POST['corinthian_import_mode'] ) );
			$roles    = array_values( array_filter( array_map( 'sanitize_key', (array) wp_unslash( $_POST['corinthian_roles'] ?? array() ) ), static fn( string $role ) => (bool) get_role( $role ) ) );
			$report   = $importer->run( ! $imported, $roles, current_user_can( 'manage_network_options' ) );
		}
		// phpcs:enable

		echo '<div class="wrap"><h1>' . esc_html__( 'Import from Admin Columns', 'corinthian-admin-column-manager' ) . '</h1>';
		echo '<p><a href="' . esc_url( EditorPage::url() ) . '">' . esc_html__( 'Back to Columns', 'corinthian-admin-column-manager' ) . '</a></p>';

		if ( null !== $report ) {
			$this->render_report( $report, $imported );
		} elseif ( array() === $importer->find_views() ) {
			echo '<p>' . esc_html__( 'No Admin Columns views were found on this site.', 'corinthian-admin-column-manager' ) . '</p></div>';
			return;
		}
		?>
		<form method="post" class="corinthian-import">
			<?php wp_nonce_field( 'corinthian_import' ); ?>
			<p><?php esc_html_e( 'Your Admin Columns views are copied into Corinthian as column sets. Importing again updates the sets created before instead of adding duplicates, and Admin Columns data stays as it is.', 'corinthian-admin-column-manager' ); ?></p>
			<fieldset style="display:flex;flex-wrap:wrap;gap:.5em 1.5em;border:0;padding:0;margin:0">
				<legend style="margin-block-end:.5em"><strong><?php esc_html_e( 'Views limited to specific users or roles also show to', 'corinthian-admin-column-manager' ); ?></strong></legend>
				<?php foreach ( wp_roles()->get_names() as $slug => $name ) : ?>
					<label style="display:inline-flex;align-items:center;gap:.4em">
						<input type="checkbox" name="corinthian_roles[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $roles, true ) ); ?>>
						<?php echo esc_html( translate_user_role( $name ) ); ?>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<p class="submit">
				<button type="submit" name="corinthian_import_mode" value="preview" class="button"><?php esc_html_e( 'Preview', 'corinthian-admin-column-manager' ); ?></button>
				<button type="submit" name="corinthian_import_mode" value="import" class="button button-primary"><?php esc_html_e( 'Import views', 'corinthian-admin-column-manager' ); ?></button>
			</p>
		</form>
		</div>
		<?php
	}

	/**
	 * @param list<array{source:string,screen:string,status:string,set_id:int,imported:list<string>,skipped:list<string>}> $report
	 */
	private function render_report( array $report, bool $imported ): void {
		if ( ! $report ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'No Admin Columns views were found on this site.', 'corinthian-admin-column-manager' ) . '</p></div>';
			return;
		}
		$notice = $imported ? __( 'Import finished.', 'corinthian-admin-column-manager' ) : __( 'Preview only: nothing has been saved yet.', 'corinthian-admin-column-manager' );
		echo '<div class="notice notice-' . ( $imported ? 'success' : 'info' ) . '"><p>' . esc_html( $notice ) . '</p></div>';
		echo '<table class="widefat striped" style="max-inline-size:60rem"><thead><tr>';
		foreach ( array( __( 'List screen', 'corinthian-admin-column-manager' ), __( 'Result', 'corinthian-admin-column-manager' ), __( 'Columns', 'corinthian-admin-column-manager' ), __( 'Left out', 'corinthian-admin-column-manager' ) ) as $heading ) {
			echo '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $report as $entry ) {
			echo '<tr><td>' . esc_html( $entry['screen'] ) . '</td><td>' . esc_html( $entry['status'] );
			if ( $entry['set_id'] ) {
				echo ' <a href="' . esc_url( EditorPage::url( $entry['screen'], $entry['set_id'] ) ) . '">' . esc_html__( 'Edit', 'corinthian-admin-column-manager' ) . '</a>';
			}
			echo '</td><td>' . esc_html( (string) count( $entry['imported'] ) ) . '</td><td>' . esc_html( implode( ', ', $entry['skipped'] ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
}
