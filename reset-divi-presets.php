<?php
/**
 * Plugin Name:       Reset Divi Presets
 * Description:       Adds an admin bar menu with options to reset Divi 4 and Divi 5 global presets.
 * Version:           2.0.0
 * Author:            Pavel Kolpakov
 * Contributors:      Eduard Ungureanu, Karen Balozyan
 * Author URI:        https://example.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       reset-divi-presets
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Main plugin class.
 */
final class Reset_Divi_Presets {

	/**
	 * The single instance of the class.
	 *
	 * @var Reset_Divi_Presets
	 */
	private static $instance = null;
	private $popup_rendered  = false;

	/**
	 * Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_menu' ], 100 );
		add_action( 'init', [ $this, 'handle_reset_actions' ] );
		add_action( 'wp_footer', [ $this, 'render_notice_popup' ], 1001 );
		add_action( 'admin_footer', [ $this, 'render_notice_popup' ] );
	}

	/**
	 * Add admin bar menu.
	 */
	public function add_admin_bar_menu( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$wp_admin_bar->add_node( [
			'id'    => 'reset-divi-presets',
			'title' => __( 'Reset Divi Presets', 'reset-divi-presets' ),
			'href'  => '#',
		] );

		$current_url = $this->get_current_url();

		$reset_divi_4_url = add_query_arg( [
			'action'   => 'reset_divi_4_presets',
			'_wpnonce' => wp_create_nonce( 'reset_divi_4_presets' ),
		], $current_url );

		$reset_divi_5_url = add_query_arg( [
			'action'   => 'reset_divi_5_presets',
			'_wpnonce' => wp_create_nonce( 'reset_divi_5_presets' ),
		], $current_url );

		$reset_global_variables_url = add_query_arg( [
			'action'   => 'reset_global_variables',
			'_wpnonce' => wp_create_nonce( 'reset_global_variables' ),
		], $current_url );

		$reset_theme_customizer_url = add_query_arg( [
			'action'   => 'reset_theme_customizer',
			'_wpnonce' => wp_create_nonce( 'reset_theme_customizer' ),
		], $current_url );
		$reset_all_d5_url = add_query_arg( [
			'action'   => 'reset_all_d5',
			'_wpnonce' => wp_create_nonce( 'reset_all_d5' ),
		], $current_url );

		$wp_admin_bar->add_node( [
			'id'     => 'reset-divi-4-presets',
			'title'  => __( 'Reset Divi 4 Presets', 'reset-divi-presets' ),
			'parent' => 'reset-divi-presets',
			'href'   => $reset_divi_4_url,
		] );

		$wp_admin_bar->add_node( [
			'id'     => 'reset-divi-5-presets',
			'title'  => __( 'Reset Divi 5 Presets', 'reset-divi-presets' ),
			'parent' => 'reset-divi-presets',
			'href'   => $reset_divi_5_url,
		] );

		$wp_admin_bar->add_node( [
			'id'     => 'reset-global-variables',
			'title'  => __( 'Reset Global Variables', 'reset-divi-presets' ),
			'parent' => 'reset-divi-presets',
			'href'   => $reset_global_variables_url,
		] );

		$wp_admin_bar->add_node( [
			'id'     => 'reset-theme-customizer',
			'title'  => __( 'Reset Theme Customizer', 'reset-divi-presets' ),
			'parent' => 'reset-divi-presets',
			'href'   => $reset_theme_customizer_url,
		] );

		$wp_admin_bar->add_node( [
			'id'     => 'reset-all-d5',
			'title'  => '<span class="rdp-reset-all-d5-label"><strong style="font-weight: bold; color: red !important;">' . esc_html__( 'Reset ALL D5', 'reset-divi-presets' ) . '</strong></span>',
			'parent' => 'reset-divi-presets',
			'href'   => $reset_all_d5_url,
		] );
	}

	/**
	 * Get current request URL.
	 *
	 * @return string
	 */
	private function get_current_url() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$request_uri = strtok( $request_uri, '#' );
		if ( false === $request_uri ) {
			$request_uri = '/';
		}

		$scheme = is_ssl() ? 'https' : 'http';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : '';

		if ( '' !== $host ) {
			return esc_url_raw( $scheme . '://' . $host . $request_uri );
		}

		return home_url( $request_uri );
	}

	/**
	 * Handle reset actions.
	 */
	public function handle_reset_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$redirect_url = remove_query_arg( [ 'action', '_wpnonce', 'rdp_notice' ] );
		$action       = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

		if ( 'reset_all_d5' === $action ) {
			check_admin_referer( 'reset_all_d5' );

			$success = $this->reset_divi_5_presets_options()
				&& $this->reset_global_variables_data()
				&& $this->reset_theme_customizer_from_json();

			wp_safe_redirect( add_query_arg( 'rdp_notice', $success ? 'all_d5_reset_success' : 'all_d5_reset_failed', $redirect_url ) );
			exit;
		}

		if ( 'reset_divi_4_presets' === $action ) {
			check_admin_referer( 'reset_divi_4_presets' );
			delete_option( 'et_divi_builder_global_presets_history_ng' );
			delete_option( 'et_divi_builder_global_presets_ng' );

			$success = ( false === get_option( 'et_divi_builder_global_presets_history_ng', false ) )
				&& ( false === get_option( 'et_divi_builder_global_presets_ng', false ) );

			wp_safe_redirect( add_query_arg( 'rdp_notice', $success ? 'divi_4_reset_success' : 'divi_4_reset_failed', $redirect_url ) );
			exit;
		}

		if ( 'reset_divi_5_presets' === $action ) {
			check_admin_referer( 'reset_divi_5_presets' );
			$success = $this->reset_divi_5_presets_options();

			wp_safe_redirect( add_query_arg( 'rdp_notice', $success ? 'divi_5_reset_success' : 'divi_5_reset_failed', $redirect_url ) );
			exit;
		}

		if ( 'reset_global_variables' === $action ) {
			check_admin_referer( 'reset_global_variables' );
			$success = $this->reset_global_variables_data();

			wp_safe_redirect( add_query_arg( 'rdp_notice', $success ? 'global_variables_reset_success' : 'global_variables_reset_failed', $redirect_url ) );
			exit;
		}

		if ( 'reset_theme_customizer' === $action ) {
			check_admin_referer( 'reset_theme_customizer' );

			$success = $this->reset_theme_customizer_from_json();

			wp_safe_redirect( add_query_arg( 'rdp_notice', $success ? 'theme_customizer_reset_success' : 'theme_customizer_reset_failed', $redirect_url ) );
			exit;
		}
	}

	/**
	 * Reset Divi 5 global presets options.
	 *
	 * @return bool
	 */
	private function reset_divi_5_presets_options() {
		delete_option( 'et_divi_builder_global_presets_history_d5' );
		delete_option( 'et_divi_builder_global_presets_d5' );

		return ( false === get_option( 'et_divi_builder_global_presets_history_d5', false ) )
			&& ( false === get_option( 'et_divi_builder_global_presets_d5', false ) );
	}

	/**
	 * Reset global variables options.
	 *
	 * @return bool
	 */
	private function reset_global_variables_data() {
		$global_data_reset = true;
		$et_divi_options   = get_option( 'et_divi' );
		if ( is_array( $et_divi_options ) ) {
			$et_divi_options['et_global_data'] = '';
			update_option( 'et_divi', $et_divi_options );
			$updated_et_divi   = get_option( 'et_divi' );
			$global_data_reset = is_array( $updated_et_divi ) && '' === ( isset( $updated_et_divi['et_global_data'] ) ? $updated_et_divi['et_global_data'] : '' );
		}

		delete_option( 'et_divi_global_variables' );
		$global_variables_deleted = ( false === get_option( 'et_divi_global_variables', false ) );

		return $global_data_reset && $global_variables_deleted;
	}

	/**
	 * Reset Theme Customizer options from local JSON.
	 *
	 * @return bool
	 */
	private function reset_theme_customizer_from_json() {
		$json_file = plugin_dir_path( __FILE__ ) . 'assets/default-d5-customizer.json';
		if ( ! file_exists( $json_file ) || ! is_readable( $json_file ) ) {
			return false;
		}

		$json_raw = file_get_contents( $json_file );
		if ( false === $json_raw ) {
			return false;
		}

		$payload = json_decode( $json_raw, true );
		if ( ! is_array( $payload ) || empty( $payload['context'] ) || ! isset( $payload['data'] ) || ! is_array( $payload['data'] ) ) {
			return false;
		}

		$context = sanitize_key( $payload['context'] );
		$data    = $payload['data'];

		if ( '' === $context ) {
			return false;
		}

		$context_saved = update_option( $context, $data );
		if ( ! $context_saved ) {
			$context_saved = ( get_option( $context ) === $data );
		}

		/*
		 * Preserve selected menu locations from current theme mods.
		 */
		$current_menu_locations = get_theme_mod( 'nav_menu_locations' );
		if ( is_array( $current_menu_locations ) && ! empty( $current_menu_locations ) ) {
			$data['nav_menu_locations'] = $current_menu_locations;
			update_option( $context, $data );
			$context_saved = ( get_option( $context ) === $data );
		}

		/*
		 * Theme Customizer UI mainly reads from theme_mods_*.
		 * Clear old values first, then apply defaults from JSON.
		 */
		$theme_mods_targets = array_unique(
			array_filter(
				[
					'theme_mods_' . get_stylesheet(),
					'theme_mods_' . get_template(),
					'theme_mods_Divi',
				]
			)
		);

		$theme_mods_saved = true;
		foreach ( $theme_mods_targets as $theme_mods_key ) {
			$existing_theme_mods  = get_option( $theme_mods_key, [] );
			$target_theme_mods    = $data;
			$existing_menu_locs   = is_array( $existing_theme_mods ) && isset( $existing_theme_mods['nav_menu_locations'] ) && is_array( $existing_theme_mods['nav_menu_locations'] )
				? $existing_theme_mods['nav_menu_locations']
				: [];
			if ( ! empty( $existing_menu_locs ) ) {
				$target_theme_mods['nav_menu_locations'] = $existing_menu_locs;
			}

			delete_option( $theme_mods_key );

			$saved = update_option( $theme_mods_key, $target_theme_mods );
			if ( ! $saved ) {
				$saved = ( get_option( $theme_mods_key ) === $target_theme_mods );
			}

			$theme_mods_saved = $theme_mods_saved && $saved;
		}

		$et_divi_saved = update_option( 'et_divi', '' );
		if ( ! $et_divi_saved ) {
			$et_divi_saved = ( '' === get_option( 'et_divi', '' ) );
		}

		return $context_saved && $theme_mods_saved && $et_divi_saved;
	}

	/**
	 * Get notice data.
	 */
	private function get_notice_data() {
		if ( ! isset( $_GET['rdp_notice'] ) ) {
			return null;
		}

		$notice_key = sanitize_text_field( wp_unslash( $_GET['rdp_notice'] ) );

		if ( 'divi_4_reset_success' === $notice_key ) {
			return [
				'type'    => 'success',
				'message' => __( 'Divi 4 presets have been reset successfully.', 'reset-divi-presets' ),
			];
		}

		if ( 'divi_5_reset_success' === $notice_key ) {
			return [
				'type'    => 'success',
				'message' => __( 'Divi 5 presets have been reset successfully.', 'reset-divi-presets' ),
			];
		}

		if ( 'global_variables_reset_success' === $notice_key ) {
			return [
				'type'    => 'success',
				'message' => __( 'Global variables have been reset successfully.', 'reset-divi-presets' ),
			];
		}

		if ( 'theme_customizer_reset_success' === $notice_key ) {
			return [
				'type'    => 'success',
				'message' => __( 'Theme Customizer settings have been reset successfully.', 'reset-divi-presets' ),
			];
		}

		if ( 'all_d5_reset_success' === $notice_key ) {
			return [
				'type'    => 'success',
				'message' => __( 'All Divi 5 settings have been reset successfully.', 'reset-divi-presets' ),
			];
		}

		if ( 'divi_4_reset_failed' === $notice_key || 'divi_5_reset_failed' === $notice_key || 'global_variables_reset_failed' === $notice_key || 'theme_customizer_reset_failed' === $notice_key || 'all_d5_reset_failed' === $notice_key ) {
			return [
				'type'    => 'error',
				'message' => __( 'Failed to reset settings.', 'reset-divi-presets' ),
			];
		}

		return null;
	}

	/**
	 * Render floating popup notice.
	 */
	public function render_notice_popup() {
		if ( $this->popup_rendered ) {
			return;
		}
		$this->popup_rendered = true;

		$notice_data = $this->get_notice_data();
		$has_notice  = ! empty( $notice_data );
		$popup_class = $has_notice && 'error' === $notice_data['type'] ? 'rdp-popup-error' : 'rdp-popup-success';
		$popup_text  = $has_notice ? $notice_data['message'] : '';
		$redirect_url = remove_query_arg( 'rdp_notice' );
		?>
		<style>
			.rdp-notice-popup {
				position: relative !important;
				top: 0 !important;
				right: 0 !important;
				padding: 8px 12px !important;
				border-radius: 0 !important;
				color: #fff !important;
				font-size: 13px !important;
				line-height: 1.3 !important;
				white-space: nowrap !important;
				box-shadow: 0 8px 22px rgba(0, 0, 0, 0.18) !important;
				z-index: 99999 !important;
				opacity: 1 !important;
				transition: opacity 1.5s ease !important;
			}
			.rdp-popup-success {
				background-color: #16a34a !important;
			}
			.rdp-popup-error {
				background-color: #dc2626 !important;
				font-weight: 700 !important;
			}
			.rdp-notice-popup.rdp-fade-out {
				opacity: 0 !important;
			}
			.rdp-notice-popup.rdp-is-hidden {
				display: none !important;
			}
		</style>
		<script>
			(function() {
				function ensurePopupContainer(attempt) {
					var adminBar = document.getElementById('wpadminbar');
					if (!adminBar) {
						if (attempt < 30) {
							setTimeout(function() {
								ensurePopupContainer(attempt + 1);
							}, 50);
						}
						return;
					}

					var popup = document.getElementById('rdp-notice-popup');
					if (!popup) {
						popup = document.createElement('span');
						popup.id = 'rdp-notice-popup';
						popup.className = 'rdp-notice-popup rdp-popup-success rdp-is-hidden';
						adminBar.appendChild(popup);
					}

					popup.setAttribute('data-has-notice', '<?php echo $has_notice ? '1' : '0'; ?>');
					popup.setAttribute('data-redirect-url', '<?php echo esc_js( esc_url_raw( $redirect_url ) ); ?>');
					popup.textContent = '<?php echo esc_js( $popup_text ); ?>';
					popup.classList.remove('rdp-popup-success', 'rdp-popup-error');
					popup.classList.add('<?php echo esc_js( $popup_class ); ?>');

					if (popup.getAttribute('data-has-notice') !== '1') {
						return;
					}

					popup.classList.remove('rdp-is-hidden');
					setTimeout(function() {
						popup.classList.add('rdp-fade-out');
					}, 3500);
					setTimeout(function() {
						if (popup.parentNode) {
							popup.parentNode.removeChild(popup);
						}
					}, 5000);
					if (window.history && window.history.replaceState) {
						window.history.replaceState(null, null, popup.getAttribute('data-redirect-url'));
					}
				}

				ensurePopupContainer(0);
			})();
		</script>
		<?php
	}
}

/**
 * Begins execution of the plugin.
 */
function reset_divi_presets_run() {
	return Reset_Divi_Presets::instance();
}
reset_divi_presets_run();
