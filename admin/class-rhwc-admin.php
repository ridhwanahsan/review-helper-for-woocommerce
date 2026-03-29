<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHWC_Admin {

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function add_admin_menu() {
		add_menu_page(
			__( 'Review Helper', 'rhwc' ),
			__( 'Review Helper', 'rhwc' ),
			'manage_woocommerce',
			'review-helper',
			array( $this, 'render_admin_page' ),
			'dashicons-star-filled',
			56
		);
	}

	public function register_settings() {
		register_setting( 'rhwc_settings_group', 'rhwc_default_count' );
		register_setting( 'rhwc_settings_group', 'rhwc_default_rating' );
		register_setting( 'rhwc_settings_group', 'rhwc_default_names' );
		register_setting( 'rhwc_settings_group', 'rhwc_custom_comments' );
		register_setting( 'rhwc_settings_group', 'rhwc_content_style' );
		register_setting( 'rhwc_settings_group', 'rhwc_enable_bulk' );
	}

	public function render_admin_page() {
		require_once RHWC_PLUGIN_DIR . 'admin/views/admin-page.php';
	}
}
