<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHWC_Meta_Box {

	public function init() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
	}

	public function add_meta_box() {
		add_meta_box(
			'rhwc_generate_reviews_meta_box',
			__( 'Generate Reviews (Review Helper)', 'review-helper-for-woocommerce' ),
			array( $this, 'render_meta_box' ),
			'product',
			'side',
			'high'
		);
	}

	public function render_meta_box( $post ) {
		$default_count = get_option( 'rhwc_default_count', 5 );
		$default_rating = get_option( 'rhwc_default_rating', '4-5' );
		$default_names = get_option( 'rhwc_default_names', '' );
		$style = get_option( 'rhwc_content_style', 'short' );
		
		?>
		<div class="rhwc-meta-box-wrapper">
			<p>
				<label for="rhwc_count"><strong><?php esc_html_e( 'Number of Reviews:', 'review-helper-for-woocommerce' ); ?></strong></label>
				<input type="number" id="rhwc_count" class="widefat" value="<?php echo esc_attr( $default_count ); ?>" min="1" max="100">
			</p>
			<p>
				<label for="rhwc_rating"><strong><?php esc_html_e( 'Rating Range:', 'review-helper-for-woocommerce' ); ?></strong></label>
				<select id="rhwc_rating" class="widefat">
					<option value="5" <?php selected( $default_rating, '5' ); ?>><?php esc_html_e( '5 Stars', 'review-helper-for-woocommerce' ); ?></option>
					<option value="4-5" <?php selected( $default_rating, '4-5' ); ?>><?php esc_html_e( '4-5 Stars', 'review-helper-for-woocommerce' ); ?></option>
					<option value="3-5" <?php selected( $default_rating, '3-5' ); ?>><?php esc_html_e( '3-5 Stars', 'review-helper-for-woocommerce' ); ?></option>
					<option value="1-5" <?php selected( $default_rating, '1-5' ); ?>><?php esc_html_e( '1-5 Stars', 'review-helper-for-woocommerce' ); ?></option>
				</select>
			</p>
			<p>
				<label for="rhwc_names"><strong><?php esc_html_e( 'Reviewer Names (One per line):', 'review-helper-for-woocommerce' ); ?></strong></label>
				<textarea id="rhwc_names" class="widefat" rows="4"><?php echo esc_textarea( $default_names ); ?></textarea>
			</p>
			<input type="hidden" id="rhwc_style" value="<?php echo esc_attr( $style ); ?>">
			
			<p>
				<button type="button" class="button button-primary" id="rhwc-generate-btn" data-product-id="<?php echo esc_attr( $post->ID ); ?>">
					<?php esc_html_e( 'Generate Reviews', 'review-helper-for-woocommerce' ); ?>
				</button>
				<span class="spinner" id="rhwc-single-spinner"></span>
			</p>
			<div id="rhwc-single-message"></div>
		</div>
		<?php
	}
}
