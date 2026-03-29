<?php
/**
 * Uninstall script for Review Helper for WooCommerce.
 *
 * Runs when the plugin is deleted from WordPress admin.
 * Asks user whether to remove data before proceeding.
 *
 * @package ReviewHelperForWooCommerce
 */

// Only run via WordPress uninstall — never directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Only delete data if the user explicitly opted in via Settings.
$delete_data = get_option( 'rhwc_delete_data_on_uninstall', 'no' );

if ( 'yes' !== $delete_data ) {
	// User chose to keep data — do nothing.
	return;
}

// ---------------------------------------------------------------------------
// Remove all plugin options.
// ---------------------------------------------------------------------------
$options = array(
	'rhwc_default_count',
	'rhwc_default_rating',
	'rhwc_default_names',
	'rhwc_custom_comments',
	'rhwc_content_style',
	'rhwc_enable_bulk',
	'rhwc_total_generated',
	'rhwc_last_generated_date',
	'rhwc_delete_data_on_uninstall',
	'rhwc_review_notice_dismissed',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// ---------------------------------------------------------------------------
// Remove generated-review post-meta from all products.
// ---------------------------------------------------------------------------
global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->delete(
	$wpdb->postmeta,
	array( 'meta_key' => '_rhwc_generated_reviews' ),
	array( '%s' )
);
