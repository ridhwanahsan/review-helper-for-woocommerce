<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHWC_Ajax {

	public function init() {
		add_action( 'wp_ajax_rhwc_generate_single', array( $this, 'generate_single' ) );
		add_action( 'wp_ajax_rhwc_generate_bulk', array( $this, 'generate_bulk' ) );
		add_action( 'wp_ajax_rhwc_get_reviews', array( $this, 'get_reviews' ) );
		add_action( 'wp_ajax_rhwc_delete_review', array( $this, 'delete_review' ) );
		add_action( 'wp_ajax_rhwc_toggle_review_status', array( $this, 'toggle_review_status' ) );
		add_action( 'wp_ajax_rhwc_edit_review', array( $this, 'edit_review' ) );
		// React-specific endpoints
		add_action( 'wp_ajax_rhwc_save_settings', array( $this, 'save_settings' ) );
		add_action( 'wp_ajax_rhwc_get_settings', array( $this, 'get_settings' ) );
		add_action( 'wp_ajax_rhwc_get_products_list', array( $this, 'get_products_list' ) );
	}

	private function check_nonce() {
		if ( ! check_ajax_referer( 'rhwc_ajax_nonce', 'security', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'rhwc' ) ) );
			wp_die();
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'rhwc' ) ) );
			wp_die();
		}
	}

	public function generate_single() {
		$this->check_nonce();

		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		$count      = isset( $_POST['count'] ) ? intval( $_POST['count'] ) : 5;
		$rating     = isset( $_POST['rating'] ) ? sanitize_text_field( $_POST['rating'] ) : '4-5';
		$names      = isset( $_POST['names'] ) ? sanitize_textarea_field( wp_unslash( $_POST['names'] ) ) : '';
		$style      = isset( $_POST['style'] ) ? sanitize_text_field( $_POST['style'] ) : 'short';

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'No product ID provided.', 'rhwc' ) ) );
		}

		$generated = RHWC_Generator::generate_reviews( $product_id, $count, $rating, $names, $style );

		if ( is_wp_error( $generated ) ) {
			wp_send_json_error( array( 'message' => $generated->get_error_message() ) );
		}

		wp_send_json_success( array( 
			'message'   => sprintf( __( 'Successfully generated %d reviews.', 'rhwc' ), $generated ),
			'generated' => $generated
		) );
	}

	public function generate_bulk() {
		$this->check_nonce();

		$product_ids_raw = isset( $_POST['product_ids'] ) ? $_POST['product_ids'] : '';
		$count       = isset( $_POST['count'] ) ? intval( $_POST['count'] ) : 5;
		$rating      = isset( $_POST['rating'] ) ? sanitize_text_field( $_POST['rating'] ) : '4-5';
		$names       = isset( $_POST['names'] ) ? sanitize_textarea_field( wp_unslash( $_POST['names'] ) ) : '';
		$style       = isset( $_POST['style'] ) ? sanitize_text_field( $_POST['style'] ) : 'short';
        $exclude_gen = isset( $_POST['exclude_generated'] ) ? filter_var( $_POST['exclude_generated'], FILTER_VALIDATE_BOOLEAN ) : true;

		if ( empty( $product_ids_raw ) ) {
			wp_send_json_error( array( 'message' => __( 'No products selected.', 'rhwc' ) ) );
		}

		$product_ids = is_string( $product_ids_raw ) ? array_map( 'intval', explode( ',', $product_ids_raw ) ) : array_map( 'intval', $product_ids_raw );
		
        if ( empty( $product_ids ) || ( is_array($product_ids) && in_array(0, $product_ids) && trim($product_ids_raw) === 'all' ) ) {
            $args = array(
                'limit'   => -1,
                'status'  => 'publish',
                'return'  => 'ids',
            );
            $product_ids = wc_get_products( $args );
        }

		$total_generated = 0;
        $products_processed = 0;

		foreach ( $product_ids as $pid ) {
            if ( $exclude_gen ) {
                $has_generated = get_post_meta( $pid, '_rhwc_generated_reviews', true );
                if ( 'yes' === $has_generated ) {
                    continue; // Skip already generated
                }
            }
            
			$result = RHWC_Generator::generate_reviews( $pid, $count, $rating, $names, $style );
			if ( ! is_wp_error( $result ) ) {
				$total_generated += $result;
                $products_processed++;
			}
		}

		wp_send_json_success( array( 
			'message' => sprintf( __( 'Successfully generated %d reviews across %d products.', 'rhwc' ), $total_generated, $products_processed ) 
		) );
	}

	public function save_settings() {
		$this->check_nonce();

		update_option( 'rhwc_default_count',              intval( $_POST['default_count'] ?? 5 ) );
		update_option( 'rhwc_default_rating',             sanitize_text_field( wp_unslash( $_POST['default_rating'] ?? '4-5' ) ) );
		update_option( 'rhwc_default_names',              sanitize_textarea_field( wp_unslash( $_POST['default_names'] ?? '' ) ) );
		update_option( 'rhwc_custom_comments',            sanitize_textarea_field( wp_unslash( $_POST['custom_comments'] ?? '' ) ) );
		update_option( 'rhwc_content_style',              sanitize_text_field( wp_unslash( $_POST['content_style'] ?? 'medium' ) ) );
		update_option( 'rhwc_enable_bulk',                sanitize_text_field( wp_unslash( $_POST['enable_bulk'] ?? 'yes' ) ) );
		update_option( 'rhwc_delete_data_on_uninstall',   sanitize_key( $_POST['delete_data_on_uninstall'] ?? 'no' ) );

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'rhwc' ) ) );
	}

	public function get_settings() {
		$this->check_nonce();
		wp_send_json_success( array(
			'defaultCount'           => get_option( 'rhwc_default_count', 5 ),
			'defaultRating'          => get_option( 'rhwc_default_rating', '4-5' ),
			'defaultNames'           => get_option( 'rhwc_default_names', '' ),
			'customComments'         => get_option( 'rhwc_custom_comments', '' ),
			'contentStyle'           => get_option( 'rhwc_content_style', 'medium' ),
			'enableBulk'             => get_option( 'rhwc_enable_bulk', 'yes' ),
			'deleteDataOnUninstall'  => get_option( 'rhwc_delete_data_on_uninstall', 'no' ),
		) );
	}

	public function get_products_list() {
		$this->check_nonce();
		$products = wc_get_products( array( 'limit' => -1, 'status' => 'publish', 'return' => 'objects' ) );
		$list = array();
		foreach ( $products as $product ) {
			$list[] = array( 'id' => $product->get_id(), 'name' => $product->get_name() );
		}
		wp_send_json_success( $list );
	}

	public function get_reviews() {
		$this->check_nonce();

		$page     = isset( $_POST['paged'] ) ? intval( $_POST['paged'] ) : 1;
		$per_page = 20;

		$args = array(
			'type'     => 'review',
			'status'   => 'all',
			'number'   => $per_page,
			'offset'   => ( $page - 1 ) * $per_page,
			'meta_key' => 'rating',
		);

		if ( ! empty( $_POST['filter_product'] ) ) {
			$args['post_id'] = intval( $_POST['filter_product'] );
		}

		if ( ! empty( $_POST['filter_rating'] ) ) {
			$args['meta_query'] = array(
				array(
					'key'   => 'rating',
					'value' => intval( $_POST['filter_rating'] ),
				),
			);
		}

		$comments = get_comments( $args );

		// Count total for pagination
		$count_args            = $args;
		$count_args['count']   = true;
		unset( $count_args['number'], $count_args['offset'] );
		$total_comments = get_comments( $count_args );
		$total_pages    = $per_page > 0 ? ceil( $total_comments / $per_page ) : 1;

		// Build JSON array for React — no HTML blobs
		$reviews = array();
		foreach ( $comments as $comment ) {
			$rating       = get_comment_meta( $comment->comment_ID, 'rating', true );
			$product      = wc_get_product( $comment->comment_post_ID );
			$product_name = $product ? $product->get_name() : __( 'Unknown Product', 'rhwc' );

			$reviews[] = array(
				'id'             => $comment->comment_ID,
				'author'         => $comment->comment_author,
				'date'           => $comment->comment_date,
				'content'        => $comment->comment_content,
				'rating'         => $rating,
				'approved'       => $comment->comment_approved,
				'productName'    => $product_name,
				'productEditUrl' => get_edit_post_link( $comment->comment_post_ID ),
			);
		}

		wp_send_json_success( array(
			'reviews'    => $reviews,
			'totalPages' => (int) $total_pages,
			'page'       => $page,
		) );
	}

	public function delete_review() {
		$this->check_nonce();
		$comment_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;

		if ( $comment_id ) {
            $comment = get_comment( $comment_id );
            if ( $comment ) {
                $product_id = $comment->comment_post_ID;
			    wp_delete_comment( $comment_id, true );
                
                $product = wc_get_product( $product_id );
                if ( $product && class_exists( 'WC_Comments' ) ) {
                    WC_Comments::get_average_rating_for_product( $product );
                }
                
			    wp_send_json_success();
            }
		}
		wp_send_json_error();
	}

    public function toggle_review_status() {
        $this->check_nonce();
        $comment_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;

        if ( $comment_id ) {
            $comment = get_comment( $comment_id );
            $new_status = '1' === $comment->comment_approved ? '0' : '1';
            wp_update_comment( array(
                'comment_ID' => $comment_id,
                'comment_approved' => $new_status
            ) );

            $product = wc_get_product( $comment->comment_post_ID );
            if ( $product && class_exists( 'WC_Comments' ) ) {
                WC_Comments::get_average_rating_for_product( $product );
            }

            wp_send_json_success( array( 'new_status' => $new_status ) );
        }
        wp_send_json_error();
    }

    public function edit_review() {
        $this->check_nonce();
        $comment_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        $content = isset( $_POST['content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '';

        if ( $comment_id && $content ) {
            wp_update_comment( array(
                'comment_ID' => $comment_id,
                'comment_content' => $content
            ) );
            wp_send_json_success( array( 'content' => nl2br( esc_html( $content ) ) ) );
        }
        wp_send_json_error();
    }
}
