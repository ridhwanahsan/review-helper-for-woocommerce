<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHWC_Core {

	public function init() {
		// Check if WooCommerce is active
		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'wc_missing_notice' ) );
			return;
		}

		$this->includes();
		$this->init_hooks();
	}

	private function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	public function wc_missing_notice() {
		echo '<div class="notice notice-error is-dismissible"><p><strong>Review Helper for WooCommerce</strong> requires WooCommerce to be installed and active.</p></div>';
	}

	private function includes() {
		require_once RHWC_PLUGIN_DIR . 'includes/class-rhwc-generator.php';
		
		if ( is_admin() ) {
			require_once RHWC_PLUGIN_DIR . 'admin/class-rhwc-admin.php';
			require_once RHWC_PLUGIN_DIR . 'admin/class-rhwc-meta-box.php';
			require_once RHWC_PLUGIN_DIR . 'admin/class-rhwc-ajax.php';
		}
	}

	private function init_hooks() {
		// Load textdomain for i18n
		add_action( 'init', array( $this, 'load_textdomain' ) );

		if ( is_admin() ) {
			$admin = new RHWC_Admin();
			$admin->init();

			$meta_box = new RHWC_Meta_Box();
			$meta_box->init();

			$ajax = new RHWC_Ajax();
			$ajax->init();

			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
			add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
		}
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'rhwc', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	public function enqueue_admin_scripts( $hook ) {
		$is_product_edit = ( 'post.php' === $hook || 'post-new.php' === $hook ) && get_post_type() === 'product';
		$is_our_page     = strpos( $hook, 'review-helper' ) !== false;

		if ( ! $is_product_edit && ! $is_our_page ) {
			return;
		}

		// --- React App (admin dashboard pages only) ---
		if ( $is_our_page ) {
			$build_asset_file = RHWC_PLUGIN_DIR . 'build/index.asset.php';
			if ( file_exists( $build_asset_file ) ) {
				$asset = include $build_asset_file;
			} else {
				$asset = array( 'dependencies' => array( 'wp-element', 'wp-components' ), 'version' => RHWC_VERSION );
			}

			wp_enqueue_script(
				'rhwc-react-app',
				RHWC_PLUGIN_URL . 'build/index.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);

			wp_enqueue_style(
				'rhwc-react-app-css',
				RHWC_PLUGIN_URL . 'build/style-index.css',
				array(),
				$asset['version']
			);

			// Localize all data the React app needs into window.rhwcReactGlobal
			wp_localize_script( 'rhwc-react-app', 'rhwcReactGlobal', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'rhwc_ajax_nonce' ),
				'stats'   => array(
					'totalGenerated'  => get_option( 'rhwc_total_generated', 0 ),
					'lastGeneratedDate' => get_option( 'rhwc_last_generated_date', 'Never' ),
				),
				'settings' => array(
					'defaultCount'   => get_option( 'rhwc_default_count', 5 ),
					'defaultRating'  => get_option( 'rhwc_default_rating', '4-5' ),
					'defaultNames'   => get_option( 'rhwc_default_names', '' ),
					'customComments' => get_option( 'rhwc_custom_comments', '' ),
					'contentStyle'   => get_option( 'rhwc_content_style', 'medium' ),
					'enableBulk'     => get_option( 'rhwc_enable_bulk', 'yes' ),
				),
			) );

			// Also load dashicons just in case theme doesn't
			wp_enqueue_style( 'dashicons' );
		}

		// --- Legacy meta-box script on product edit pages ---
		if ( $is_product_edit ) {
			wp_enqueue_style( 'rhwc-admin-css', RHWC_PLUGIN_URL . 'assets/css/rhwc-admin.css', array(), RHWC_VERSION );
			wp_enqueue_script( 'rhwc-admin-js', RHWC_PLUGIN_URL . 'assets/js/rhwc-admin.js', array( 'jquery' ), RHWC_VERSION, true );
			wp_localize_script( 'rhwc-admin-js', 'rhwc_data', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'rhwc_ajax_nonce' ),
			) );
		}
	}

	public function register_dashboard_widget() {
		wp_add_dashboard_widget(
			'rhwc_dashboard_widget',
			__( 'Review Helper for WooCommerce', 'rhwc' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	public function render_dashboard_widget() {
		$total_generated = get_option( 'rhwc_total_generated', 0 );
		$last_date       = get_option( 'rhwc_last_generated_date', __( 'Never', 'rhwc' ) );

		echo '<div class="rhwc-dashboard-widget">';
		echo '<p><strong>' . __( 'Total Generated Reviews:', 'rhwc' ) . '</strong> ' . esc_html( $total_generated ) . '</p>';
		echo '<p><strong>' . __( 'Last Generated Date:', 'rhwc' ) . '</strong> ' . esc_html( $last_date ) . '</p>';
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=review-helper' ) ) . '" class="button button-primary">' . __( 'Manage Reviews', 'rhwc' ) . '</a></p>';
		echo '</div>';
	}
}
