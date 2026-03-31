<?php
/**
 * Uninstall script for Review Helper for WooCommerce.
 *
 * Runs when the plugin is deleted from WordPress admin.
 *
 * @package ReviewHelperForWooCommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$rhwc_delete_data = get_option( 'rhwc_delete_data_on_uninstall', 'no' );

if ( 'yes' !== $rhwc_delete_data ) {
	return;
}

$rhwc_options = array(
	'rhwc_default_count',
	'rhwc_default_rating',
	'rhwc_default_names',
	'rhwc_custom_comments',
	'rhwc_content_style',
	'rhwc_review_status',
	'rhwc_date_from_days',
	'rhwc_date_to_days',
	'rhwc_excluded_products',
	'rhwc_excluded_categories',
	'rhwc_enable_bulk',
	'rhwc_total_generated',
	'rhwc_last_generated_date',
	'rhwc_last_bulk_batch',
	'rhwc_delete_data_on_uninstall',
);

foreach ( $rhwc_options as $rhwc_option ) {
	delete_option( $rhwc_option );
}

delete_post_meta_by_key( '_rhwc_generated_reviews' );

// Remove comment-level generated review markers added by the plugin.
delete_metadata( 'comment', 0, '_rhwc_generated_review', '', true );
