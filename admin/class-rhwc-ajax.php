<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHWC_Ajax {

	public function init() {
		add_action( 'wp_ajax_rhwc_generate_single', array( $this, 'generate_single' ) );
		add_action( 'wp_ajax_rhwc_generate_bulk', array( $this, 'generate_bulk' ) );
		add_action( 'wp_ajax_rhwc_undo_last_bulk_generation', array( $this, 'undo_last_bulk_generation' ) );
		add_action( 'wp_ajax_rhwc_get_reviews', array( $this, 'get_reviews' ) );
		add_action( 'wp_ajax_rhwc_delete_selected_reviews', array( $this, 'delete_selected_reviews' ) );
		add_action( 'wp_ajax_rhwc_delete_generated_reviews', array( $this, 'delete_generated_reviews' ) );
		add_action( 'wp_ajax_rhwc_delete_review', array( $this, 'delete_review' ) );
		add_action( 'wp_ajax_rhwc_toggle_review_status', array( $this, 'toggle_review_status' ) );
		add_action( 'wp_ajax_rhwc_edit_review', array( $this, 'edit_review' ) );
		add_action( 'wp_ajax_rhwc_save_settings', array( $this, 'save_settings' ) );
		add_action( 'wp_ajax_rhwc_import_settings', array( $this, 'import_settings' ) );
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

	private function get_filtered_generated_review_ids( $filter_product = 0, $filter_rating = 0 ) {
		$query_args = array(
			'type'   => 'review',
			'status' => 'all',
			'fields' => 'ids',
		);

		if ( $filter_product ) {
			$query_args['post_id'] = $filter_product;
		}

		$comment_ids = get_comments( $query_args );
		$matches     = array();

		foreach ( $comment_ids as $comment_id ) {
			if ( $filter_rating && $filter_rating !== (int) get_comment_meta( $comment_id, 'rating', true ) ) {
				continue;
			}

			if ( ! RHWC_Generator::is_generated_review( $comment_id ) ) {
				continue;
			}

			$matches[] = (int) $comment_id;
		}

		return $matches;
	}

	private function refresh_product_review_data( $product_id ) {
		$product_id = absint( $product_id );

		if ( ! $product_id ) {
			return;
		}

		RHWC_Generator::sync_generated_reviews_flag( $product_id );

		if ( class_exists( 'WC_Comments' ) ) {
			WC_Comments::clear_transients( $product_id );
		}
	}

	private function sync_last_bulk_batch_after_comment_deletion( $deleted_comment_ids ) {
		$deleted_comment_ids = is_array( $deleted_comment_ids ) ? $deleted_comment_ids : array( $deleted_comment_ids );
		$deleted_comment_ids = array_values( array_filter( array_map( 'absint', $deleted_comment_ids ) ) );

		if ( empty( $deleted_comment_ids ) ) {
			return;
		}

		$last_batch = RHWC_Generator::get_last_bulk_batch();

		if ( empty( $last_batch ) ) {
			return;
		}

		$remaining_comment_ids = array_values( array_diff( $last_batch['commentIds'], $deleted_comment_ids ) );

		if ( empty( $remaining_comment_ids ) ) {
			RHWC_Generator::clear_last_bulk_batch();
			return;
		}

		$last_batch['commentIds'] = $remaining_comment_ids;
		$last_batch['generated']  = count( $remaining_comment_ids );

		RHWC_Generator::save_last_bulk_batch( $last_batch );
	}

	private function delete_review_ids( $comment_ids ) {
		$comment_ids = is_array( $comment_ids ) ? $comment_ids : array( $comment_ids );
		$comment_ids = array_values( array_filter( array_map( 'absint', $comment_ids ) ) );

		$affected_product_ids = array();
		$deleted_comment_ids  = array();
		$deleted_count        = 0;

		foreach ( $comment_ids as $comment_id ) {
			$comment = get_comment( $comment_id );

			if ( ! $comment instanceof WP_Comment ) {
				continue;
			}

			$product_id = absint( $comment->comment_post_ID );

			if ( wp_delete_comment( $comment_id, true ) ) {
				++$deleted_count;
				$deleted_comment_ids[] = $comment_id;

				if ( $product_id ) {
					$affected_product_ids[ $product_id ] = $product_id;
				}
			}
		}

		foreach ( $affected_product_ids as $product_id ) {
			$this->refresh_product_review_data( $product_id );
		}

		$this->sync_last_bulk_batch_after_comment_deletion( $deleted_comment_ids );

		return array(
			'deleted'   => $deleted_count,
			'products'  => count( $affected_product_ids ),
			'productIds' => array_values( $affected_product_ids ),
			'commentIds' => $deleted_comment_ids,
		);
	}

	private function is_bulk_excluded_product( $product_id, $excluded_product_ids, $excluded_category_ids ) {
		$product_id = absint( $product_id );

		if ( in_array( $product_id, $excluded_product_ids, true ) ) {
			return true;
		}

		if ( empty( $excluded_category_ids ) ) {
			return false;
		}

		$product_category_ids = wp_get_post_terms(
			$product_id,
			'product_cat',
			array(
				'fields' => 'ids',
			)
		);

		if ( is_wp_error( $product_category_ids ) ) {
			return false;
		}

		return ! empty( array_intersect( $excluded_category_ids, array_map( 'absint', $product_category_ids ) ) );
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

		$product_ids_input       = isset( $_POST['product_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['product_ids'] ) ) : '';
		$count                   = isset( $_POST['count'] ) ? RHWC_Admin::sanitize_default_count( sanitize_text_field( wp_unslash( $_POST['count'] ) ) ) : 5;
		$rating                  = isset( $_POST['rating'] ) ? RHWC_Admin::sanitize_rating_range( sanitize_text_field( wp_unslash( $_POST['rating'] ) ) ) : '4-5';
		$names                   = isset( $_POST['names'] ) ? RHWC_Admin::sanitize_multiline_textarea( sanitize_textarea_field( wp_unslash( $_POST['names'] ) ) ) : '';
		$style                   = isset( $_POST['style'] ) ? RHWC_Admin::sanitize_content_style( sanitize_key( wp_unslash( $_POST['style'] ) ) ) : 'short';
		$exclude_generated       = true;
		$all_products_selected   = is_string( $product_ids_input ) && 'all' === sanitize_key( $product_ids_input );
		$excluded_product_ids    = RHWC_Admin::get_csv_ids( get_option( 'rhwc_excluded_products', '' ) );
		$excluded_category_ids   = RHWC_Admin::get_csv_ids( get_option( 'rhwc_excluded_categories', '' ) );

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

		$total_generated       = 0;
		$products_processed    = 0;
		$products_skipped      = 0;
		$products_excluded     = 0;
		$generated_comment_ids = array();
		$generated_product_ids = array();

		foreach ( $product_ids as $product_id ) {
			if ( $this->is_bulk_excluded_product( $product_id, $excluded_product_ids, $excluded_category_ids ) ) {
				++$products_excluded;
				continue;
			}

			if ( $exclude_generated ) {
				$has_generated = get_post_meta( $product_id, '_rhwc_generated_reviews', true );
				if ( 'yes' === $has_generated ) {
					++$products_skipped;
					continue;
				}
			}

			$result = RHWC_Generator::generate_reviews( $product_id, $count, $rating, $names, $style, true );
			if ( ! is_wp_error( $result ) ) {
				$total_generated += $result['count'];
				++$products_processed;

				if ( ! empty( $result['commentIds'] ) ) {
					$generated_comment_ids = array_merge( $generated_comment_ids, $result['commentIds'] );
					$generated_product_ids[] = (int) $product_id;
				}
			}
		}

		if ( ! empty( $generated_comment_ids ) ) {
			$target_label = $all_products_selected
				? esc_html__( 'All published products', 'review-helper-for-woocommerce' )
				: sprintf(
					/* translators: %d: number of requested products. */
					esc_html__( '%d specific products', 'review-helper-for-woocommerce' ),
					count( $product_ids )
				);

			RHWC_Generator::save_last_bulk_batch(
				array(
					'id'                => wp_generate_uuid4(),
					'createdAt'         => current_time( 'mysql' ),
					'generated'         => $total_generated,
					'processed'         => $products_processed,
					'skipped'           => $products_skipped,
					'target'            => $all_products_selected ? 'all' : 'specific',
					'targetLabel'       => $target_label,
					'requestedProducts' => count( $product_ids ),
					'countPerProduct'   => (int) $count,
					'rating'            => $rating,
					'style'             => $style,
					'skipGenerated'     => $exclude_generated ? 'yes' : 'no',
					'productIds'        => array_values( array_unique( $generated_product_ids ) ),
					'commentIds'        => array_values( array_unique( array_map( 'absint', $generated_comment_ids ) ) ),
				)
			);
		}

		if ( 0 === $products_processed && $products_skipped > 0 && 0 === $products_excluded ) {
			$message = sprintf(
				/* translators: %d: number of skipped products. */
				esc_html__( 'No new reviews were generated because all %d selected products were already marked as having generated reviews. Disable the skip option if you want to generate more reviews for them.', 'review-helper-for-woocommerce' ),
				$products_skipped
			);
		} elseif ( 0 === $products_processed && $products_excluded > 0 && 0 === $products_skipped ) {
			$message = sprintf(
				/* translators: %d: number of excluded products. */
				esc_html__( 'No new reviews were generated because all %d selected products were excluded by your product or category rules.', 'review-helper-for-woocommerce' ),
				$products_excluded
			);
		} elseif ( 0 === $products_processed && $products_skipped > 0 && $products_excluded > 0 ) {
			$message = sprintf(
				/* translators: 1: skipped products count, 2: excluded products count. */
				esc_html__( 'No new reviews were generated. %1$d selected products were already marked as generated and %2$d were excluded by your rules.', 'review-helper-for-woocommerce' ),
				$products_skipped,
				$products_excluded
			);
		} elseif ( $products_skipped > 0 && $products_excluded > 0 ) {
			$message = sprintf(
				/* translators: 1: reviews generated, 2: processed products, 3: skipped products, 4: excluded products. */
				esc_html__( 'Successfully generated %1$d reviews across %2$d products. Skipped %3$d products that already had generated reviews and excluded %4$d products by your rules.', 'review-helper-for-woocommerce' ),
				$total_generated,
				$products_processed,
				$products_skipped,
				$products_excluded
			);
		} elseif ( $products_skipped > 0 ) {
			$message = sprintf(
				/* translators: 1: number of generated reviews, 2: number of processed products, 3: number of skipped products. */
				esc_html__( 'Successfully generated %1$d reviews across %2$d products. Skipped %3$d products that already had generated reviews.', 'review-helper-for-woocommerce' ),
				$total_generated,
				$products_processed,
				$products_skipped
			);
		} elseif ( $products_excluded > 0 ) {
			$message = sprintf(
				/* translators: 1: number of generated reviews, 2: number of processed products, 3: number of excluded products. */
				esc_html__( 'Successfully generated %1$d reviews across %2$d products. Excluded %3$d products by your product or category rules.', 'review-helper-for-woocommerce' ),
				$total_generated,
				$products_processed,
				$products_excluded
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
				'excluded'  => $products_excluded,
				'lastBatch' => RHWC_Generator::get_last_bulk_batch_summary(),
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

		$payload = RHWC_Admin::update_settings(
			array(
				'defaultCount'          => isset( $_POST['default_count'] ) ? sanitize_text_field( wp_unslash( $_POST['default_count'] ) ) : null,
				'defaultRating'         => isset( $_POST['default_rating'] ) ? sanitize_text_field( wp_unslash( $_POST['default_rating'] ) ) : null,
				'defaultNames'          => isset( $_POST['default_names'] ) ? sanitize_textarea_field( wp_unslash( $_POST['default_names'] ) ) : null,
				'customComments'        => isset( $_POST['custom_comments'] ) ? sanitize_textarea_field( wp_unslash( $_POST['custom_comments'] ) ) : null,
				'contentStyle'          => isset( $_POST['content_style'] ) ? sanitize_key( wp_unslash( $_POST['content_style'] ) ) : null,
				'reviewStatus'          => isset( $_POST['review_status'] ) ? sanitize_key( wp_unslash( $_POST['review_status'] ) ) : null,
				'dateFromDays'          => isset( $_POST['date_from_days'] ) ? wp_unslash( $_POST['date_from_days'] ) : null,
				'dateToDays'            => isset( $_POST['date_to_days'] ) ? wp_unslash( $_POST['date_to_days'] ) : null,
				'excludedProducts'      => isset( $_POST['excluded_products'] ) ? sanitize_text_field( wp_unslash( $_POST['excluded_products'] ) ) : null,
				'excludedCategories'    => isset( $_POST['excluded_categories'] ) ? sanitize_text_field( wp_unslash( $_POST['excluded_categories'] ) ) : null,
				'enableBulk'            => isset( $_POST['enable_bulk'] ) ? sanitize_key( wp_unslash( $_POST['enable_bulk'] ) ) : null,
				'deleteDataOnUninstall' => isset( $_POST['delete_data_on_uninstall'] ) ? sanitize_key( wp_unslash( $_POST['delete_data_on_uninstall'] ) ) : null,
			)
		);

		wp_send_json_success(
			array(
				'message'  => esc_html__( 'Settings saved.', 'review-helper-for-woocommerce' ),
				'settings' => $payload,
			)
		);
	}

	public function import_settings() {
		if ( ! check_ajax_referer( 'rhwc_ajax_nonce', 'security', false ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed.', 'review-helper-for-woocommerce' ),
				),
				403
			);
		}
		$this->check_permissions();

		$settings_json = isset( $_POST['settings_json'] ) ? wp_unslash( $_POST['settings_json'] ) : '';
		$result        = RHWC_Admin::import_settings_json( $settings_json );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				),
				400
			);
		}

		wp_send_json_success(
			array(
				'message'  => esc_html__( 'Settings imported successfully.', 'review-helper-for-woocommerce' ),
				'settings' => $result,
			)
		);
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

		$filter_product   = isset( $_POST['filter_product'] ) ? absint( wp_unslash( $_POST['filter_product'] ) ) : 0;
		$filter_rating    = isset( $_POST['filter_rating'] ) ? absint( wp_unslash( $_POST['filter_rating'] ) ) : 0;
		$filter_generated = isset( $_POST['filter_generated'] ) ? RHWC_Admin::sanitize_yes_no( sanitize_key( wp_unslash( $_POST['filter_generated'] ) ) ) : 'no';

		if ( $filter_product ) {
			$args['post_id'] = $filter_product;
		}

		if ( $filter_rating || 'yes' === $filter_generated ) {
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
				if ( $filter_rating && $filter_rating !== (int) get_comment_meta( $comment_id, 'rating', true ) ) {
					continue;
				}

				if ( 'yes' === $filter_generated && ! RHWC_Generator::is_generated_review( $comment_id ) ) {
					continue;
				}

				$matching_comment_ids[] = (int) $comment_id;
			}

			$total_comments    = count( $matching_comment_ids );
			$paged_comment_ids = array_slice( $matching_comment_ids, ( $page - 1 ) * $per_page, $per_page );
			$comments          = array();

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

		$total_pages = $per_page > 0 ? max( 1, (int) ceil( $total_comments / $per_page ) ) : 1;

		// Build JSON for React and avoid server-rendered HTML snippets.
		$reviews = array();

		foreach ( $comments as $comment ) {
			$rating              = get_comment_meta( $comment->comment_ID, 'rating', true );
			$product             = wc_get_product( $comment->comment_post_ID );
			$product_name        = $product ? wp_strip_all_tags( $product->get_name() ) : esc_html__( 'Unknown Product', 'review-helper-for-woocommerce' );
			$generated_by_plugin = RHWC_Generator::is_generated_review( $comment );

			$reviews[] = array(
				'id'             => $comment->comment_ID,
				'author'         => $comment->comment_author,
				'date'           => $comment->comment_date,
				'content'        => $comment->comment_content,
				'rating'         => $rating,
				'approved'       => $comment->comment_approved,
				'generatedByPlugin' => $generated_by_plugin,
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

	public function undo_last_bulk_generation() {
		if ( ! check_ajax_referer( 'rhwc_ajax_nonce', 'security', false ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed.', 'review-helper-for-woocommerce' ),
				),
				403
			);
		}
		$this->check_permissions();

		$last_batch = RHWC_Generator::get_last_bulk_batch();

		if ( empty( $last_batch ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'There is no undoable bulk batch right now.', 'review-helper-for-woocommerce' ),
				),
				400
			);
		}

		$deleted_count        = 0;
		$affected_product_ids = array_values( array_unique( array_filter( array_map( 'absint', $last_batch['productIds'] ) ) ) );

		foreach ( $last_batch['commentIds'] as $comment_id ) {
			$comment = get_comment( $comment_id );

			if ( ! $comment instanceof WP_Comment ) {
				continue;
			}

			if ( ! RHWC_Generator::is_generated_review( $comment ) ) {
				continue;
			}

			$product_id = absint( $comment->comment_post_ID );

			if ( $product_id ) {
				$affected_product_ids[ $product_id ] = $product_id;
			}

			if ( wp_delete_comment( $comment_id, true ) ) {
				++$deleted_count;
			}
		}

		foreach ( $affected_product_ids as $product_id ) {
			$this->refresh_product_review_data( $product_id );
		}

		RHWC_Generator::clear_last_bulk_batch();

		if ( $deleted_count > 0 ) {
			$message = sprintf(
				/* translators: 1: number of deleted reviews, 2: number of affected products. */
				esc_html__( 'Successfully undid the last bulk batch by deleting %1$d generated reviews across %2$d products.', 'review-helper-for-woocommerce' ),
				$deleted_count,
				count( $affected_product_ids )
			);
		} else {
			$message = esc_html__( 'The saved bulk batch could no longer be undone, so its undo state was cleared.', 'review-helper-for-woocommerce' );
		}

		wp_send_json_success(
			array(
				'message'  => $message,
				'deleted'  => $deleted_count,
				'products' => count( $affected_product_ids ),
				'lastBatch' => null,
			)
		);
	}

	public function delete_selected_reviews() {
		if ( ! check_ajax_referer( 'rhwc_ajax_nonce', 'security', false ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed.', 'review-helper-for-woocommerce' ),
				),
				403
			);
		}
		$this->check_permissions();

		$ids_input = isset( $_POST['ids'] ) ? sanitize_text_field( wp_unslash( $_POST['ids'] ) ) : '';
		$comment_ids = array_values(
			array_filter(
				array_map(
					'absint',
					array_map(
						'trim',
						explode( ',', $ids_input )
					)
				)
			)
		);

		if ( empty( $comment_ids ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'No reviews were selected for removal.', 'review-helper-for-woocommerce' ),
				),
				400
			);
		}

		$result = $this->delete_review_ids( $comment_ids );

		if ( $result['deleted'] > 0 ) {
			$message = sprintf(
				/* translators: 1: number of deleted reviews, 2: number of affected products. */
				esc_html__( 'Successfully deleted %1$d selected reviews across %2$d products.', 'review-helper-for-woocommerce' ),
				$result['deleted'],
				$result['products']
			);
		} else {
			$message = esc_html__( 'No selected reviews were deleted.', 'review-helper-for-woocommerce' );
		}

		wp_send_json_success(
			array(
				'message'   => $message,
				'deleted'   => $result['deleted'],
				'products'  => $result['products'],
				'lastBatch' => RHWC_Generator::get_last_bulk_batch_summary(),
			)
		);
	}

	public function delete_generated_reviews() {
		if ( ! check_ajax_referer( 'rhwc_ajax_nonce', 'security', false ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed.', 'review-helper-for-woocommerce' ),
				),
				403
			);
		}
		$this->check_permissions();

		$filter_product = isset( $_POST['filter_product'] ) ? absint( wp_unslash( $_POST['filter_product'] ) ) : 0;
		$filter_rating  = isset( $_POST['filter_rating'] ) ? absint( wp_unslash( $_POST['filter_rating'] ) ) : 0;
		$comment_ids    = $this->get_filtered_generated_review_ids( $filter_product, $filter_rating );

		if ( empty( $comment_ids ) ) {
			wp_send_json_success(
				array(
					'message'  => esc_html__( 'No generated reviews matched the current filters.', 'review-helper-for-woocommerce' ),
					'deleted'  => 0,
					'products' => 0,
				)
			);
		}

		$result        = $this->delete_review_ids( $comment_ids );
		$deleted_count = $result['deleted'];

		if ( $deleted_count > 0 ) {
			$message = sprintf(
				/* translators: 1: number of deleted reviews, 2: number of affected products. */
				esc_html__( 'Successfully deleted %1$d generated reviews across %2$d products.', 'review-helper-for-woocommerce' ),
				$deleted_count,
				$result['products']
			);
		} else {
			$message = esc_html__( 'No generated reviews were deleted.', 'review-helper-for-woocommerce' );
		}

		wp_send_json_success(
			array(
				'message'  => $message,
				'deleted'  => $deleted_count,
				'products' => $result['products'],
				'lastBatch' => RHWC_Generator::get_last_bulk_batch_summary(),
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
			$result = $this->delete_review_ids( array( $comment_id ) );

			if ( $result['deleted'] > 0 ) {
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
