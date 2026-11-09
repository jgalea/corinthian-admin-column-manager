<?php

namespace Corinthian\Admin;

use Corinthian\Plugin;
use Corinthian\Screens\Screen;
use Corinthian\Sets\SetRepository;

/**
 * Everything Corinthian adds to the list table itself: column widths, the
 * horizontal-scroll guard that stops columns collapsing, and the set switcher.
 */
final class TableUi {

	/** Minimum width in px an unsized column may shrink to. */
	public const MIN_COLUMN_WIDTH = 140;

	public function __construct( private Plugin $plugin ) {}

	public function register(): void {
		add_action( 'current_screen', array( $this, 'on_screen' ) );
		add_action( 'admin_post_corinthian_switch_set', array( $this, 'switch_set' ) );
	}

	public function on_screen( \WP_Screen $wp_screen ): void {
		$screen = $this->plugin->screens->for_screen_id( $wp_screen->id );
		if ( ! $screen ) {
			return;
		}
		add_action( 'admin_head', fn() => $this->print_styles( $screen ) );
		add_action( 'admin_enqueue_scripts', fn() => $this->enqueue( $screen ) );
		// Not every list table has a tablenav hook, so print in the footer and move it.
		add_action( 'admin_footer', fn() => $this->print_switcher( $screen ) );
	}

	public function print_styles( Screen $screen ): void {
		$set = $screen->active_set();
		if ( ! $set ) {
			return;
		}

		$rules     = array();
		$min_total = 40; // Checkbox column.
		$mine      = self::user_widths( $screen->key(), $set->id, get_current_user_id() );
		foreach ( $set->columns as $config ) {
			$key      = $config->table_key();
			$selector = '.wp-list-table .column-' . sanitize_html_class( $key );
			$width    = $config->width;
			$unit     = '%' === $config->width_unit ? '%' : 'px';
			if ( isset( $mine[ $key ] ) ) {
				$width = $mine[ $key ];
				$unit  = 'px';
			}
			if ( $width ) {
				$rules[] = $selector . '{width:' . (int) $width . $unit . '}';
			}
			$min_total += ( $width && 'px' === $unit ) ? $width : self::MIN_COLUMN_WIDTH;
		}

		// WordPress uses a fixed table layout: once sized columns take the full
		// width, unsized ones collapse to nothing. A minimum table width plus a
		// scrolling wrapper keeps every column readable.
		$rules[]  = '.corinthian-scroll{overflow-x:auto}';
		$all_mine = array() !== $mine && array() === array_diff( array_map( static fn( $c ) => $c->table_key(), $set->columns ), array_keys( $mine ) );
		if ( $all_mine ) {
			// The user sized every column by dragging: the table is exactly their sum.
			$rules[] = '.corinthian-scroll>.wp-list-table{width:' . (int) $min_total . 'px;min-width:0}';
		} else {
			$rules[] = '.corinthian-scroll>.wp-list-table{min-width:' . (int) $min_total . 'px}';
		}
		if ( ! empty( $set->settings['sticky_header'] ) ) {
			$rules[] = '.wp-list-table thead th,.wp-list-table thead td{position:sticky;top:32px;z-index:2;background:#fff}';
			$rules[] = '.corinthian-scroll{overflow-x:visible}';
		}
		$rules[] = '.corinthian-switcher{display:inline-flex;gap:6px;align-items:center;margin:0 8px 0 0}';

		echo '<style id="corinthian-table">' . implode( '', $rules ) . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from integers and sanitized class names.
		?>
		<script>
		document.addEventListener('DOMContentLoaded',function(){var t=document.querySelector('table.wp-list-table');if(t&&!t.parentNode.classList.contains('corinthian-scroll')){var w=document.createElement('div');w.className='corinthian-scroll';t.parentNode.insertBefore(w,t);w.appendChild(t);}});
		</script>
		<?php
	}

	public const WIDTHS_META_PREFIX = 'corinthian_widths_';

	public static function widths_meta_key( string $screen_key, int $set_id ): string {
		return self::WIDTHS_META_PREFIX . md5( $screen_key . '|' . $set_id );
	}

	/**
	 * Column widths this user dragged for a set, as table key => px.
	 *
	 * @return array<string,int>
	 */
	public static function user_widths( string $screen_key, int $set_id, int $user_id ): array {
		$stored = get_user_meta( $user_id, self::widths_meta_key( $screen_key, $set_id ), true );
		if ( ! is_array( $stored ) ) {
			return array();
		}
		$out = array();
		foreach ( $stored as $key => $px ) {
			$out[ (string) $key ] = max( self::MIN_RESIZE, min( self::MAX_RESIZE, (int) $px ) );
		}
		return $out;
	}

	public const MIN_RESIZE = 40;
	public const MAX_RESIZE = 2000;

	public function enqueue( Screen $screen ): void {
		$set   = $screen->active_set();
		$asset = CORINTHIAN_DIR . 'assets/build/table.asset.php';
		if ( ! $set || ! file_exists( $asset ) ) {
			return;
		}
		$meta = require $asset;
		wp_enqueue_script( 'corinthian-table', CORINTHIAN_URL . 'assets/build/table.js', $meta['dependencies'], $meta['version'], true );
		wp_enqueue_style( 'corinthian-table', CORINTHIAN_URL . 'assets/build/table.css', array(), $meta['version'] );
		wp_style_add_data( 'corinthian-table', 'rtl', 'replace' );
		$data = array(
			'screen'     => $screen->key(),
			'set'        => $set->id,
			'path'       => '/corinthian/v1/widths',
			'minWidth'   => self::MIN_RESIZE,
			'objectType' => $screen->object_type(),
			'resize'     => false !== ( $set->settings['resize_columns'] ?? true ),
		);
		/**
		 * Filters the data printed as window.corinthianTable.
		 */
		$data = apply_filters( 'corinthian_table_payload', $data, $screen, $set );
		wp_add_inline_script( 'corinthian-table', 'window.corinthianTable = ' . wp_json_encode( $data ) . ';', 'before' );
		do_action( 'corinthian_table_enqueue', $screen, $set );
	}

	public function print_switcher( Screen $screen ): void {
		$user = wp_get_current_user();
		$sets = array_filter( $this->plugin->sets->for_screen( $screen->key() ), static fn( $s ) => $s->visible_to( $user ) );
		$can  = current_user_can( Plugin::capability() );
		if ( count( $sets ) < 2 && ! $can && ! $screen->active_set() ) {
			return;
		}
		$active = $screen->active_set();
		// A div, not a form: this prints inside the list table's own form.
		?>
		<div class="corinthian-switcher alignleft actions" hidden>
			<?php if ( count( $sets ) > 1 ) : ?>
				<label class="screen-reader-text" for="corinthian-set"><?php esc_html_e( 'Column set', 'corinthian-admin-column-manager' ); ?></label>
				<select id="corinthian-set" form="corinthian-none" onchange="window.location.href=this.value">
					<?php foreach ( $sets as $set ) : ?>
						<option value="<?php echo esc_url( self::switch_url( $screen->key(), $set->id ) ); ?>" <?php selected( $active && $active->id === $set->id ); ?>><?php echo esc_html( $set->title ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
			<?php do_action( 'corinthian_switcher_actions', $screen, $active ); ?>
			<?php if ( $can ) : ?>
				<a class="button" href="<?php echo esc_url( EditorPage::url( $screen->key(), $active ? $active->id : 0 ) ); ?>"><?php esc_html_e( 'Edit columns', 'corinthian-admin-column-manager' ); ?></a>
			<?php endif; ?>
		</div>
		<script>
		(function(){var s=document.querySelector('.corinthian-switcher'),n=document.querySelector('.tablenav.top');if(!s)return;if(n){var b=n.querySelector('.tablenav-pages, br.clear');n.insertBefore(s,b);}else{var t=document.querySelector('.wp-list-table');if(t)t.closest('form,div').insertAdjacentElement('beforebegin',s);}s.hidden=false;})();
		</script>
		<?php
	}

	public static function switch_url( string $screen_key, int $set_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'corinthian_switch_set',
					'screen' => rawurlencode( $screen_key ),
					'set'    => $set_id,
				),
				admin_url( 'admin-post.php' )
			),
			'corinthian_switch_set'
		);
	}

	public function switch_set(): void {
		check_admin_referer( 'corinthian_switch_set' );
		$screen_key = rawurldecode( sanitize_text_field( wp_unslash( $_GET['screen'] ?? '' ) ) );
		$set_id     = absint( $_GET['set'] ?? 0 );
		$screen     = $this->plugin->screens->get( $screen_key );
		$set        = $set_id ? $this->plugin->sets->find( $set_id ) : null;

		if ( $screen && $set && $set->screen === $screen_key && $set->visible_to( wp_get_current_user() ) ) {
			update_user_meta( get_current_user_id(), SetRepository::active_meta_key( $screen_key ), $set->id );
		}
		wp_safe_redirect( $screen ? $screen->url() : admin_url() );
		exit;
	}
}
