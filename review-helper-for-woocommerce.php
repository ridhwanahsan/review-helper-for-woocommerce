<?php
/**
 * Plugin Name: Review Helper for WooCommerce
 * Plugin URI: https://example.com
 * Description: Helps store admins easily generate and manage WooCommerce product reviews manually (on click) and in bulk, without using AI or external APIs.
 * Version: 1.0.0
 * Author: Antigravity
 * Author URI: https://example.com
 * License: GPL2
 * Text Domain: rhwc
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'RHWC_VERSION', '1.0.0' );
define( 'RHWC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RHWC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include core class
require_once RHWC_PLUGIN_DIR . 'includes/class-rhwc-core.php';

// Initialize the plugin
function rhwc_init() {
	if ( class_exists( 'RHWC_Core' ) ) {
		$rhwc = new RHWC_Core();
		$rhwc->init();
	}
}
add_action( 'plugins_loaded', 'rhwc_init' );

// Activation hook
register_activation_hook( __FILE__, 'rhwc_activate' );
function rhwc_activate() {
	// Add initial options if they don't exist
	add_option( 'rhwc_default_count', 5 );
	add_option( 'rhwc_default_rating', '4-5' ); // Range like "4-5" or fixed "5"
	
	// Default English names
	$default_names = "John Doe\nJane Smith\nMichael Johnson\nEmily Davis\nChris Brown\nSarah Miller\nDavid Wilson\nLaura Taylor\nJames Moore\nLinda Thomas\nRobert Anderson\nMary Thomas\nWilliam Jackson\nPatricia White\nRichard Harris\nJennifer Martin\nCharles Thompson\nElizabeth Garcia\nJoseph Martinez\nSusan Robinson";
	add_option( 'rhwc_default_names', $default_names );
	
	// Default custom comments
	$default_comments = "Great product.\nExactly what I needed.\nHighly recommended.\nVery satisfied with {product_name}.\nThe quality of {product_name} is outstanding!\nI love this product.";
	add_option( 'rhwc_custom_comments', $default_comments );
	
	add_option( 'rhwc_content_style', 'short' );
	add_option( 'rhwc_enable_bulk', 'yes' );
}
