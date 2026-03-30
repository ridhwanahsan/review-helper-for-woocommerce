<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHWC_Admin {

	public static function get_default_names() {
		return "John Doe\nJane Smith\nMichael Johnson\nEmily Davis\nChris Brown\nSarah Miller\nDavid Wilson\nLaura Taylor\nJames Moore\nLinda Thomas\nRobert Anderson\nMary Thomas\nWilliam Jackson\nPatricia White\nRichard Harris\nJennifer Martin\nCharles Thompson\nElizabeth Garcia\nJoseph Martinez\nSusan Robinson";
	}

	public static function get_default_comments() {
		return "Great product.\nExactly what I needed.\nHighly recommended.\nVery satisfied with {product_name}.\nThe quality of {product_name} is outstanding!\nI love this product.";
	}

	public static function get_setting_definitions() {
		return array(
			'defaultCount'          => array(
				'option'   => 'rhwc_default_count',
				'default'  => 5,
				'sanitize' => array( __CLASS__, 'sanitize_default_count' ),
			),
			'defaultRating'         => array(
				'option'   => 'rhwc_default_rating',
				'default'  => '4-5',
				'sanitize' => array( __CLASS__, 'sanitize_rating_range' ),
			),
			'defaultNames'          => array(
				'option'   => 'rhwc_default_names',
				'default'  => self::get_default_names(),
				'sanitize' => array( __CLASS__, 'sanitize_multiline_textarea' ),
			),
			'customComments'        => array(
				'option'   => 'rhwc_custom_comments',
				'default'  => self::get_default_comments(),
				'sanitize' => array( __CLASS__, 'sanitize_multiline_textarea' ),
			),
			'contentStyle'          => array(
				'option'   => 'rhwc_content_style',
				'default'  => 'short',
				'sanitize' => array( __CLASS__, 'sanitize_content_style' ),
			),
			'reviewStatus'          => array(
				'option'   => 'rhwc_review_status',
				'default'  => 'approved',
				'sanitize' => array( __CLASS__, 'sanitize_review_status' ),
			),
			'dateFromDays'          => array(
				'option'   => 'rhwc_date_from_days',
				'default'  => 0,
				'sanitize' => array( __CLASS__, 'sanitize_day_offset' ),
			),
			'dateToDays'            => array(
				'option'   => 'rhwc_date_to_days',
				'default'  => 30,
				'sanitize' => array( __CLASS__, 'sanitize_day_offset' ),
			),
			'excludedProducts'      => array(
				'option'   => 'rhwc_excluded_products',
				'default'  => '',
				'sanitize' => array( __CLASS__, 'sanitize_csv_ids' ),
			),
			'excludedCategories'    => array(
				'option'   => 'rhwc_excluded_categories',
				'default'  => '',
				'sanitize' => array( __CLASS__, 'sanitize_csv_ids' ),
			),
			'enableBulk'            => array(
				'option'   => 'rhwc_enable_bulk',
				'default'  => 'yes',
				'sanitize' => array( __CLASS__, 'sanitize_yes_no' ),
			),
			'deleteDataOnUninstall' => array(
				'option'   => 'rhwc_delete_data_on_uninstall',
				'default'  => 'no',
				'sanitize' => array( __CLASS__, 'sanitize_yes_no' ),
			),
		);
	}

	public static function get_settings_payload() {
		$payload = array();

		foreach ( self::get_setting_definitions() as $key => $definition ) {
			$payload[ $key ] = get_option( $definition['option'], $definition['default'] );
		}

		return self::normalize_settings_payload( $payload );
	}

	public static function update_settings( $settings ) {
		$payload = self::get_settings_payload();

		foreach ( self::get_setting_definitions() as $key => $definition ) {
			if ( ! array_key_exists( $key, $settings ) ) {
				continue;
			}

			$value = $settings[ $key ];

			if ( is_callable( $definition['sanitize'] ) ) {
				$value = call_user_func( $definition['sanitize'], $value );
			}

			$payload[ $key ] = $value;
		}

		$payload = self::normalize_settings_payload( $payload );

		foreach ( self::get_setting_definitions() as $key => $definition ) {
			update_option( $definition['option'], $payload[ $key ] );
		}

		return $payload;
	}

	public static function import_settings_json( $json ) {
		$data = json_decode( (string) $json, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return new WP_Error( 'invalid_json', __( 'The imported settings JSON is invalid.', 'review-helper-for-woocommerce' ) );
		}

		$settings = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : $data;

		return self::update_settings( $settings );
	}

	public static function get_csv_ids( $value ) {
		$value = is_array( $value ) ? implode( ',', $value ) : (string) $value;
		$parts = preg_split( '/[\s,]+/', $value );

		return array_values( array_unique( array_filter( array_map( 'absint', $parts ) ) ) );
	}

	private static function normalize_settings_payload( $payload ) {
		$payload['dateFromDays'] = self::sanitize_day_offset( $payload['dateFromDays'] );
		$payload['dateToDays']   = self::sanitize_day_offset( $payload['dateToDays'] );

		if ( $payload['dateFromDays'] > $payload['dateToDays'] ) {
			$temp                    = $payload['dateFromDays'];
			$payload['dateFromDays'] = $payload['dateToDays'];
			$payload['dateToDays']   = $temp;
		}

		$payload['excludedProducts']   = self::sanitize_csv_ids( $payload['excludedProducts'] );
		$payload['excludedCategories'] = self::sanitize_csv_ids( $payload['excludedCategories'] );

		return $payload;
	}

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
				'default'           => self::get_default_names(),
				'type'              => 'string',
			)
		);
		register_setting(
			'rhwc_settings_group',
			'rhwc_custom_comments',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_multiline_textarea' ),
				'default'           => self::get_default_comments(),
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
			'rhwc_review_status',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_review_status' ),
				'default'           => 'approved',
				'type'              => 'string',
			)
		);
		register_setting(
			'rhwc_settings_group',
			'rhwc_date_from_days',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_day_offset' ),
				'default'           => 0,
				'type'              => 'integer',
			)
		);
		register_setting(
			'rhwc_settings_group',
			'rhwc_date_to_days',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_day_offset' ),
				'default'           => 30,
				'type'              => 'integer',
			)
		);
		register_setting(
			'rhwc_settings_group',
			'rhwc_excluded_products',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_csv_ids' ),
				'default'           => '',
				'type'              => 'string',
			)
		);
		register_setting(
			'rhwc_settings_group',
			'rhwc_excluded_categories',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_csv_ids' ),
				'default'           => '',
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

	public static function sanitize_review_status( $value ) {
		$value = sanitize_key( (string) $value );

		if ( ! in_array( $value, array( 'approved', 'pending' ), true ) ) {
			return 'approved';
		}

		return $value;
	}

	public static function sanitize_day_offset( $value ) {
		return min( 3650, absint( $value ) );
	}

	public static function sanitize_csv_ids( $value ) {
		$ids = self::get_csv_ids( $value );

		return implode( ',', $ids );
	}

	public static function sanitize_yes_no( $value ) {
		$value = sanitize_key( (string) $value );

		if ( in_array( $value, array( '0', 'false', 'no', 'off' ), true ) ) {
			return 'no';
		}

		return 'yes';
	}
}
