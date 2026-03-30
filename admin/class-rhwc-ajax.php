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
		add_action( 'wp_ajax_rhwc_save_settings', array( $this, 'save_settings' ) );
		add_action( 'wp_ajax_rhwc_get_settings', array( $this, 'get_settings' ) );
		add_action( 'wp_ajax_rhwc_get_products_list', array( $this, 'get_products_list' ) );
	}

	private function check_permissions() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Permission denied.', 'review-helper-for-woocommerce' ),
				),
				403
			);
		}
	}

	public function generate_single() {
		if ( ! check_ajax_referer( 'rhwc_ajax_nonce', 'security', false ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed.', 'review-helper-for-woocommerce' ),
				),
				403
			);
		}
		$this->check_permissions();

		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$count      = isset( $_POST['count'] ) ? RHWC_Admin::sanitize_default_count( sanitize_text_field( wp_unslash( $_POST['count'] ) ) ) : 5;
		$rating     = isset( $_POST['rating'] ) ? RHWC_Admin::sanitize_rating_range( sanitize_text_field( wp_unslash( $_POST['rating'] ) ) ) : '4-5';
		$names      = isset( $_POST['names'] ) ? RHWC_Admin::sanitize_multiline_textarea( sanitize_textarea_field( wp_unslash( $_POST['names'] ) ) ) : '';
		$style      = isset( $_POST['style'] ) ? RHWC_Admin::sanitize_content_style( sanitize_key( wp_unslash( $_POST['style'] ) ) ) : 'short';

		if ( ! $product_id ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'No product ID provided.', 'review-helper-for-woocommerce' ),
				),
				400
			);
		}

		$generated = RHWC_Generator::generate_reviews( $product_id, $count, $rating, $names, $style );

		if ( is_wp_error( $generated ) ) {
			wp_send_json_error( array( 'message' => $generated->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				/* translators: %d: number of generated reviews. */
				'message'   => sprintf( esc_html__( 'Successfully generated %d reviews.', 'review-helper-for-woocommerce' ), $generated ),
				'generated' => $generated,
			)
		);
	}

	public function generate_bulk() {
		if ( ! check_ajax_referer( 'rhwc_ajax_nonce', 'security', false ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed.', 'review-helper-for-woocommerce' ),
				),
				403
			);
		}
		$this->check_permissions();

		$product_ids_input     = isset( $_POST['product_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['product_ids'] ) ) : '';
		$count                 = isset( $_POST['count'] ) ? RHWC_Admin::sanitize_default_count( sanitize_text_field( wp_unslash( $_POST['count'] ) ) ) : 5;
		$rating                = isset( $_POST['rating'] ) ? RHWC_Admin::sanitize_rating_range( sanitize_text_field( wp_unslash( $_POST['rating'] ) ) ) : '4-5';
		$names                 = isset( $_POST['names'] ) ? RHWC_Admin::sanitize_multiline_textarea( sanitize_textarea_field( wp_unslash( $_POST['names'] ) ) ) : '';
		$style                 = isset( $_POST['style'] ) ? RHWC_Admin::sanitize_content_style( sanitize_key( wp_unslash( $_POST['style'] ) ) ) : 'short';
		$exclude_generated     = true;
		$all_products_selected = is_string( $product_ids_input ) && 'all' === sanitize_key( $product_ids_input );

		if ( isset( $_POST['exclude_generated'] ) ) {
			$exclude_generated = in_array(
				sanitize_text_field( wp_unslash( $_POST['exclude_generated'] ) ),
				array( '1', 'true', 'yes', 'on' ),
				true
			);
		}

		if ( $all_products_selected ) {
			$product_ids = wc_get_products(
				array(
					'limit'  => -1,
					'status' => 'publish',
					'return' => 'ids',
				)
			);
		} else {
			$product_ids = array_filter(
				array_map(
					'absint',
					array_map(
						'trim',
						explode( ',', $product_ids_input )
					)
				)
			);
		}

		if ( empty( $product_ids ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'No products selected.', 'review-helper-for-woocommerce' ),
				),
				400
			);
		}

		$total_generated    = 0;
		$products_processed = 0;
		$products_skipped   = 0;

		foreach ( $product_ids as $product_id ) {
			if ( $exclude_generated ) {
				$has_generated = get_post_meta( $product_id, '_rhwc_generated_reviews', true );
				if ( 'yes' === $has_generated ) {
					++$products_skipped;
					continue;
				}
			}

			$result = RHWC_Generator::generate_reviews( $product_id, $count, $rating, $names, $style );
			if ( ! is_wp_error( $result ) ) {
				$total_generated += $result;
				++$products_processed;
			}
		}

		if ( 0 === $products_processed && $products_skipped > 0 ) {
			$message = sprintf(
				/* translators: %d: number of skipped products. */
				esc_html__( 'No new reviews were generated because all %d selected products were already marked as having generated reviews. Disable the skip option if you want to generate more reviews for them.', 'review-helper-for-woocommerce' ),
				$products_skipped
			);
		} elseif ( $products_skipped > 0 ) {
			$message = sprintf(
				/* translators: 1: number of generated reviews, 2: number of processed products, 3: number of skipped products. */
				esc_html__( 'Successfully generated %1$d reviews across %2$d products. Skipped %3$d products that already had generated reviews.', 'review-helper-for-woocommerce' ),
				$total_generated,
				$products_processed,
				$products_skipped
			);
		} else {
			$message = sprintf(
				/* translators: 1: number of generated reviews, 2: number of products processed. */
				esc_html__( 'Successfully generated %1$d reviews across %2$d products.', 'review-helper-for-woocommerce' ),
				$total_generated,
				$products_processed
			);
		}

		wp_send_json_success(
			array(
				'message'   => $message,
				'generated' => $total_generated,
				'processed' => $products_processed,
				'skipped'   => $products_skipped,
			)
		);
	}

	public function save_settings() {
		if ( ! check_ajax_referer( 'rhwc_ajax_nonce', 'security', false ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed.', 'review-helper-for-woocommerce' ),
				),
				403
			);
		}
		$this->check_permissions();

		$default_count            = isset( $_POST['default_count'] ) ? RHWC_Admin::sanitize_default_count( sanitize_text_field( wp_unslash( $_POST['default_count'] ) ) ) : 5;
		$default_rating           = isset( $_POST['default_rating'] ) ? RHWC_Admin::sanitize_rating_range( sanitize_text_field( wp_unslash( $_POST['default_rating'] ) ) ) : '4-5';
		$default_names            = isset( $_POST['default_names'] ) ? RHWC_Admin::sanitize_multiline_textarea( sanitize_textarea_field( wp_unslash( $_POST['default_names'] ) ) ) : '';
		$custom_comments          = isset( $_POST['custom_comments'] ) ? RHWC_Admin::sanitize_multiline_textarea( sanitize_textarea_field( wp_unslash( $_POST['custom_comments'] ) ) ) : '';
		$content_style            = isset( $_POST['content_style'] ) ? RHWC_Admin::sanitize_content_style( sanitize_key( wp_unslash( $_POST['content_style'] ) ) ) : 'medium';
		$enable_bulk              = isset( $_POST['enable_bulk'] ) ? RHWC_Admin::sanitize_yes_no( sanitize_key( wp_unslash( $_POST['enable_bulk'] ) ) ) : 'yes';
		$delete_data_on_uninstall = isset( $_POST['delete_data_on_uninstall'] ) ? RHWC_Admin::sanitize_yes_no( sanitize_key( wp_unslash( $_POST['delete_data_on_uninstall'] ) ) ) : 'no';

		update_option( 'rhwc_default_count', $default_count );
		update_option( 'rhwc_default_rating', $default_rating );
		update_option( 'rhwc_default_names', $default_names );
		update_option( 'rhwc_custom_comments', $custom_comments );
		update_option( 'rhwc_content_style', $content_style );
		update_option( 'rhwc_enable_bulk', $enable_bulk );
		update_option( 'rhwc_delete_data_on_uninstall', $delete_data_on_uninstall );

		wp_send_json_success(
			array(
				'message' => esc_html__( 'Settings saved.', 'review-helper-for-woocommerce' ),
			)
		);
	}

	public function get_settings() {
		if ( ! check_ajax_referer( 'rhwc_ajax_nonce', 'security', false ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed.', 'review-helper-for-woocommerce' ),
				),
				403
			);
		}
		$this->check_permissions();

		wp_send_json_success(
			array(
				'defaultCount'          => get_option( 'rhwc_default_count', 5 ),
				'defaultRating'         => get_option( 'rhwc_default_rating', '4-5' ),
				'defaultNames'          => get_option( 'rhwc_default_names', '' ),
				'customComments'        => get_option( 'rhwc_custom_comments', '' ),
				'contentStyle'          => get_option( 'rhwc_content_style', 'medium' ),
				'enableBulk'            => get_option( 'rhwc_enable_bulk', 'yes' ),
				'deleteDataOnUninstall' => get_option( 'rhwc_delete_data_on_uninstall', 'no' ),
			)
		);
	}

	public function get_products_list() {
		if ( ! check_ajax_referer( 'rhwc_ajax_nonce', 'security', false ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed.', 'review-helper-for-woocommerce' ),
				),
				403
			);
		}
		$this->check_permissions();

		$products = wc_get_products(
			array(
				'limit'  => -1,
				'status' => 'publish',
				'return' => 'objects',
			)
		);
		$list = array();

		foreach ( $products as $product ) {
			$list[] = array(
				'id'   => $product->get_id(),
				'name' => wp_strip_all_tags( $product->get_name() ),
			);
		}

		wp_send_json_success( $list );
	}

	public function get_reviews() {
		if ( ! check_ajax_referer( 'rhwc_ajax_nonce', 'security', false ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed.', 'review-helper-for-woocommerce' ),
				),
				403
			);
		}
		$this->check_permissions();

		$page     = isset( $_POST['paged'] ) ? absint( wp_unslash( $_POST['paged'] ) ) : 1;
		$per_page = 20;

		if ( $page < 1 ) {
			$page = 1;
		}

		$args = array(
			'type'   => 'review',
			'status' => 'all',
			'number' => $per_page,
			'offset' => ( $page - 1 ) * $per_page,
		);

		$filter_product = isset( $_POST['filter_product'] ) ? absint( wp_unslash( $_POST['filter_product'] ) ) : 0;
		$filter_rating  = isset( $_POST['filter_rating'] ) ? absint( wp_unslash( $_POST['filter_rating'] ) ) : 0;

		if ( $filter_product ) {
			$args['post_id'] = $filter_product;
		}

		if ( $filter_rating ) {
			$comment_query_args = array(
				'type'   => 'review',
				'status' => 'all',
				'fields' => 'ids',
			);

			if ( $filter_product ) {
				$comment_query_args['post_id'] = $filter_product;
			}

			$comment_ids = get_comments( $comment_query_args );

			$matching_comment_ids = array();

			foreach ( $comment_ids as $comment_id ) {
				if ( $filter_rating === (int) get_comment_meta( $comment_id, 'rating', true ) ) {
					$matching_comment_ids[] = (int) $comment_id;
				}
			}

			$total_comments   = count( $matching_comment_ids );
			$paged_comment_ids = array_slice( $matching_comment_ids, ( $page - 1 ) * $per_page, $per_page );
			$comments         = array();

			foreach ( $paged_comment_ids as $comment_id ) {
				$comment = get_comment( $comment_id );
				if ( $comment instanceof WP_Comment ) {
					$comments[] = $comment;
				}
			}
		} else {
			$comments = get_comments( $args );

			$count_args          = $args;
			$count_args['count'] = true;
			unset( $count_args['number'], $count_args['offset'] );

			$total_comments = get_comments( $count_args );
		}

		$total_pages    = $per_page > 0 ? ceil( $total_comments / $per_page ) : 1;

		// Build JSON for React and avoid server-rendered HTML snippets.
		$reviews = array();

		foreach ( $comments as $comment ) {
			$rating       = get_comment_meta( $comment->comment_ID, 'rating', true );
			$product      = wc_get_product( $comment->comment_post_ID );
			$product_name = $product ? wp_strip_all_tags( $product->get_name() ) : esc_html__( 'Unknown Product', 'review-helper-for-woocommerce' );

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

		wp_send_json_success(
			array(
				'reviews'    => $reviews,
				'totalPages' => (int) $total_pages,
				'page'       => $page,
			)
		);
	}

	public function delete_review() {
		if ( ! check_ajax_referer( 'rhwc_ajax_nonce', 'security', false ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed.', 'review-helper-for-woocommerce' ),
				),
				403
			);
		}
		$this->check_permissions();

		$comment_id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;

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
		if ( ! check_ajax_referer( 'rhwc_ajax_nonce', 'security', false ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed.', 'review-helper-for-woocommerce' ),
				),
				403
			);
		}
		$this->check_permissions();

		$comment_id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;

		if ( $comment_id ) {
			$comment = get_comment( $comment_id );
			if ( $comment ) {
				$new_status = '1' === $comment->comment_approved ? '0' : '1';

				wp_update_comment(
					array(
						'comment_ID'       => $comment_id,
						'comment_approved' => $new_status,
					)
				);

				$product = wc_get_product( $comment->comment_post_ID );
				if ( $product && class_exists( 'WC_Comments' ) ) {
					WC_Comments::get_average_rating_for_product( $product );
				}

				wp_send_json_success( array( 'new_status' => $new_status ) );
			}
		}

		wp_send_json_error();
	}

	public function edit_review() {
		if ( ! check_ajax_referer( 'rhwc_ajax_nonce', 'security', false ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed.', 'review-helper-for-woocommerce' ),
				),
				403
			);
		}
		$this->check_permissions();

		$comment_id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		$content    = isset( $_POST['content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '';

		if ( $comment_id && $content ) {
			wp_update_comment(
				array(
					'comment_ID'      => $comment_id,
					'comment_content' => $content,
				)
			);

			wp_send_json_success( array( 'content' => nl2br( esc_html( $content ) ) ) );
		}

		wp_send_json_error();
	}
}
