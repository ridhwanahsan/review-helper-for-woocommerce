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
			__( 'Review Helper', 'review-helper-for-woocommerce' ),
			__( 'Review Helper', 'review-helper-for-woocommerce' ),
			'manage_woocommerce',
			'review-helper',
			array( $this, 'render_admin_page' ),
			'dashicons-star-filled',
			56
		);
	}

	public function register_settings() {
		register_setting(
			'rhwc_settings_group',
			'rhwc_default_count',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_default_count' ),
				'default'           => 5,
				'type'              => 'integer',
			)
		);
		register_setting(
			'rhwc_settings_group',
			'rhwc_default_rating',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_rating_range' ),
				'default'           => '4-5',
				'type'              => 'string',
			)
		);
		register_setting(
			'rhwc_settings_group',
			'rhwc_default_names',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_multiline_textarea' ),
				'default'           => '',
				'type'              => 'string',
			)
		);
		register_setting(
			'rhwc_settings_group',
			'rhwc_custom_comments',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_multiline_textarea' ),
				'default'           => '',
				'type'              => 'string',
			)
		);
		register_setting(
			'rhwc_settings_group',
			'rhwc_content_style',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_content_style' ),
				'default'           => 'short',
				'type'              => 'string',
			)
		);
		register_setting(
			'rhwc_settings_group',
			'rhwc_enable_bulk',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_yes_no' ),
				'default'           => 'yes',
				'type'              => 'string',
			)
		);
		register_setting(
			'rhwc_settings_group',
			'rhwc_delete_data_on_uninstall',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_yes_no' ),
				'default'           => 'no',
				'type'              => 'string',
			)
		);
	}

	public function render_admin_page() {
		require_once RHWC_PLUGIN_DIR . 'admin/views/admin-page.php';
	}

	public static function sanitize_default_count( $value ) {
		$value = absint( $value );

		if ( $value < 1 ) {
			return 1;
		}

		return min( 100, $value );
	}

	public static function sanitize_rating_range( $value ) {
		$value = sanitize_text_field( (string) $value );

		if ( ! preg_match( '/^\d(?:-\d)?$/', $value ) ) {
			return '4-5';
		}

		$parts = array_map( 'absint', array_map( 'trim', explode( '-', $value ) ) );
		$min   = max( 1, min( 5, $parts[0] ) );
		$max   = isset( $parts[1] ) ? max( 1, min( 5, $parts[1] ) ) : $min;

		if ( $min > $max ) {
			$temp = $min;
			$min  = $max;
			$max  = $temp;
		}

		if ( $min === $max ) {
			return (string) $min;
		}

		return $min . '-' . $max;
	}

	public static function sanitize_multiline_textarea( $value ) {
		return sanitize_textarea_field( (string) $value );
	}

	public static function sanitize_content_style( $value ) {
		$value = sanitize_key( (string) $value );

		if ( ! in_array( $value, array( 'short', 'medium', 'long' ), true ) ) {
			return 'short';
		}

		return $value;
	}

	public static function sanitize_yes_no( $value ) {
		$value = sanitize_key( (string) $value );

		if ( in_array( $value, array( '0', 'false', 'no', 'off' ), true ) ) {
			return 'no';
		}

		return 'yes';
	}
}
