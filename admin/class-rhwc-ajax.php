<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHWC_Ajax {

	public function init() {
		add_action( 'wp_ajax_rhwc_generate_single', array( $this, 'generate_single' ) );
		add_action( 'wp_ajax_rhwc_generate_bulk', array( $this, 'generate_bulk' ) );
		add_action( 'wp_ajax_rhwc_schedule_bulk_generation', array( $this, 'schedule_bulk_generation' ) );
		add_action( 'wp_ajax_rhwc_cancel_scheduled_bulk_generation', array( $this, 'cancel_scheduled_bulk_generation' ) );
		add_action( 'wp_ajax_rhwc_pause_scheduled_bulk_generation', array( $this, 'pause_scheduled_bulk_generation' ) );
		add_action( 'wp_ajax_rhwc_resume_scheduled_bulk_generation', array( $this, 'resume_scheduled_bulk_generation' ) );
		add_action( 'wp_ajax_rhwc_run_schedule_now', array( $this, 'run_schedule_now' ) );
		add_action( 'wp_ajax_rhwc_save_schedule_template', array( $this, 'save_schedule_template' ) );
		add_action( 'wp_ajax_rhwc_delete_schedule_template', array( $this, 'delete_schedule_template' ) );
		add_action( 'wp_ajax_rhwc_import_schedules', array( $this, 'import_schedules' ) );
		add_action( 'wp_ajax_rhwc_undo_last_bulk_generation', array( $this, 'undo_last_bulk_generation' ) );
		add_action( 'wp_ajax_rhwc_get_reviews', array( $this, 'get_reviews' ) );
		add_action( 'wp_ajax_rhwc_delete_selected_reviews', array( $this, 'delete_selected_reviews' ) );
		add_action( 'wp_ajax_rhwc_delete_generated_reviews', array( $this, 'delete_generated_reviews' ) );
		add_action( 'wp_ajax_rhwc_delete_review', array( $this, 'delete_review' ) );
		add_action( 'wp_ajax_rhwc_toggle_review_status', array( $this, 'toggle_review_status' ) );
		add_action( 'wp_ajax_rhwc_edit_review', array( $this, 'edit_review' ) );
		add_action( 'wp_ajax_rhwc_save_settings', array( $this, 'save_settings' ) );
		add_action( 'wp_ajax_rhwc_import_settings', array( $this, 'import_settings' ) );
		add_action( 'rhwc_run_scheduled_bulk_generation', array( $this, 'run_scheduled_bulk_generation' ) );
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

		$schedule_time = isset( $payload['scheduleTime'] ) && ! empty( $payload['scheduleTime'] ) ? $payload['scheduleTime'] : '09:00';

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

	private function sanitize_schedule_delay_minutes( $value ) {
		$minutes = absint( $value );

		if ( $minutes < 1 ) {
			return 1;
		}

		return min( 10080, $minutes );
	}

	private function sanitize_schedule_interval_minutes( $value ) {
		return $this->sanitize_schedule_delay_minutes( $value );
	}

	private function sanitize_schedule_recurrence( $value ) {
		$value = sanitize_key( (string) $value );

		if ( ! in_array( $value, array( 'once', 'daily', 'weekly', 'custom' ), true ) ) {
			return 'once';
		}

		return $value;
	}

	private function sanitize_schedule_days( $days ) {
		if ( ! is_array( $days ) ) {
			$days = explode( ',', (string) $days );
		}

		return array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $day ) {
							$day = absint( $day );
							return $day <= 6 ? $day : null;
						},
						$days
					),
					static function ( $day ) {
						return null !== $day;
					}
				)
			)
		);
	}

	private function add_schedule_history_entry( $job, $event, $message, $status = 'info' ) {
		$job = is_array( $job ) ? $job : array();

		RHWC_Generator::add_schedule_history_entry(
			array(
				'jobId'       => isset( $job['id'] ) ? $job['id'] : '',
				'jobLabel'    => isset( $job['targetLabel'] ) ? $job['targetLabel'] : '',
				'event'       => $event,
				'status'      => $status,
				'createdAt'   => current_time( 'mysql' ),
				'createdTimestamp' => time(),
				'cycleNumber' => isset( $job['cycleNumber'] ) ? absint( $job['cycleNumber'] ) : 0,
				'message'     => $message,
			)
		);
	}

	private function get_site_timezone_label() {
		$timezone = wp_timezone_string();

		if ( ! empty( $timezone ) ) {
			return $timezone;
		}

		return sprintf(
			'UTC%s',
			(string) get_option( 'gmt_offset', 0 )
		);
	}

	private function get_schedule_response_data( $message = '' ) {
		return array(
			'message'         => $message,
			'scheduledJob'    => RHWC_Generator::get_scheduled_bulk_job_summary(),
			'scheduledJobs'   => RHWC_Generator::get_scheduled_bulk_jobs_summary(),
			'scheduleHistory' => RHWC_Generator::get_schedule_history_summary(),
			'scheduleTemplates' => RHWC_Generator::get_schedule_templates_summary(),
			'siteTimezone'    => $this->get_site_timezone_label(),
		);
	}

	private function get_schedule_recurrence_label( $job ) {
		$recurrence_type = isset( $job['recurrenceType'] ) ? $this->sanitize_schedule_recurrence( $job['recurrenceType'] ) : 'once';

		switch ( $recurrence_type ) {
			case 'daily':
				return esc_html__( 'daily', 'review-helper-for-woocommerce' );
			case 'weekly':
				return esc_html__( 'weekly', 'review-helper-for-woocommerce' );
			case 'custom':
				return esc_html__( 'custom weekdays', 'review-helper-for-woocommerce' );
			default:
				return esc_html__( 'one time', 'review-helper-for-woocommerce' );
		}
	}

	private function create_schedule_template_from_payload( $payload, $template_name, $existing_template = null ) {
		$payload        = $this->normalize_bulk_payload( $payload );
		$template_name  = sanitize_text_field( (string) $template_name );
		$existing_template = is_array( $existing_template ) ? $existing_template : array();
		$schedule_time  = isset( $payload['scheduleTime'] ) && ! empty( $payload['scheduleTime'] )
			? sanitize_text_field( (string) $payload['scheduleTime'] )
			: '09:00';

		if ( '' === $template_name ) {
			return new WP_Error( 'missing_template_name', esc_html__( 'Add a template name before saving.', 'review-helper-for-woocommerce' ) );
		}

		return array(
			'id'                 => ! empty( $existing_template['id'] ) ? $existing_template['id'] : wp_generate_uuid4(),
			'name'               => $template_name,
			'createdAt'          => ! empty( $existing_template['createdAt'] ) ? $existing_template['createdAt'] : current_time( 'mysql' ),
			'target'             => $payload['target'],
			'productIdsInput'    => $payload['productIdsInput'],
			'countPerProduct'    => $payload['countPerProduct'],
			'rating'             => $payload['rating'],
			'style'              => $payload['style'],
			'names'              => $payload['names'],
			'skipGenerated'      => $payload['skipGenerated'],
			'reviewStatus'       => $payload['reviewStatus'],
			'dateFromDays'       => $payload['dateFromDays'],
			'dateToDays'         => $payload['dateToDays'],
			'excludedProducts'   => $payload['excludedProducts'],
			'excludedCategories' => $payload['excludedCategories'],
			'recurrenceType'     => isset( $payload['recurrenceType'] ) ? $payload['recurrenceType'] : 'once',
			'customDays'         => isset( $payload['customDays'] ) ? $payload['customDays'] : array(),
			'scheduleDate'       => isset( $payload['scheduleDate'] ) ? sanitize_text_field( (string) $payload['scheduleDate'] ) : '',
			'scheduleTime'       => $schedule_time,
			'scheduleWeekday'    => isset( $payload['scheduleWeekday'] ) ? min( 6, absint( $payload['scheduleWeekday'] ) ) : 0,
			'delayMinMinutes'    => $payload['delayMinMinutes'],
			'delayMaxMinutes'    => $payload['delayMaxMinutes'],
		);
	}

	private function schedule_job_event( $job, $timestamp = 0 ) {
		$timestamp = $timestamp ? (int) $timestamp : (int) $job['timestamp'];

		if ( $timestamp < 1 || empty( $job['id'] ) ) {
			return new WP_Error( 'invalid_schedule', esc_html__( 'The scheduled job is missing required timing data.', 'review-helper-for-woocommerce' ) );
		}

		return wp_schedule_single_event( $timestamp, 'rhwc_run_scheduled_bulk_generation', array( $job['id'] ), true );
	}

	private function get_schedule_delay_seconds( $job ) {
		$min_minutes = isset( $job['delayMinMinutes'] ) ? $this->sanitize_schedule_delay_minutes( $job['delayMinMinutes'] ) : 30;
		$max_minutes = isset( $job['delayMaxMinutes'] ) ? $this->sanitize_schedule_delay_minutes( $job['delayMaxMinutes'] ) : $min_minutes;

		if ( $min_minutes > $max_minutes ) {
			$temp        = $min_minutes;
			$min_minutes = $max_minutes;
			$max_minutes = $temp;
		}

		return wp_rand( $min_minutes, $max_minutes ) * MINUTE_IN_SECONDS;
	}

	private function get_next_recurrence_timestamp( $job, $reference_timestamp = 0 ) {
		$recurrence_type = isset( $job['recurrenceType'] ) ? $this->sanitize_schedule_recurrence( $job['recurrenceType'] ) : 'once';

		if ( 'once' === $recurrence_type ) {
			return 0;
		}

		$reference_timestamp = $reference_timestamp ? (int) $reference_timestamp : time();
		$timezone            = wp_timezone();
		$reference           = ( new DateTimeImmutable( '@' . $reference_timestamp ) )->setTimezone( $timezone );
		$schedule_time       = isset( $job['scheduleTime'] ) ? sanitize_text_field( (string) $job['scheduleTime'] ) : '09:00';
		$time_parts          = array_map( 'absint', explode( ':', $schedule_time ) );
		$hour                = isset( $time_parts[0] ) ? min( 23, $time_parts[0] ) : 9;
		$minute              = isset( $time_parts[1] ) ? min( 59, $time_parts[1] ) : 0;
		$base_candidate      = $reference->setTime( $hour, $minute, 0 );

		if ( 'daily' === $recurrence_type ) {
			$candidate = $base_candidate;

			if ( $candidate->getTimestamp() <= $reference->getTimestamp() ) {
				$candidate = $candidate->modify( '+1 day' );
			}

			return $candidate->getTimestamp();
		}

		if ( 'weekly' === $recurrence_type ) {
			$target_weekday = isset( $job['scheduleWeekday'] ) ? min( 6, absint( $job['scheduleWeekday'] ) ) : (int) $reference->format( 'w' );
			$current_weekday = (int) $reference->format( 'w' );
			$days_ahead = ( $target_weekday - $current_weekday + 7 ) % 7;
			$candidate  = $base_candidate->modify( sprintf( '+%d day', $days_ahead ) );

			if ( $candidate->getTimestamp() <= $reference->getTimestamp() ) {
				$candidate = $candidate->modify( '+7 day' );
			}

			return $candidate->getTimestamp();
		}

		$custom_days = isset( $job['customDays'] ) ? $this->sanitize_schedule_days( $job['customDays'] ) : array();

		if ( empty( $custom_days ) ) {
			return 0;
		}

		for ( $offset = 0; $offset <= 7; $offset++ ) {
			$candidate = $base_candidate->modify( sprintf( '+%d day', $offset ) );
			$weekday   = (int) $candidate->format( 'w' );

			if ( ! in_array( $weekday, $custom_days, true ) ) {
				continue;
			}

			if ( $candidate->getTimestamp() <= $reference->getTimestamp() ) {
				continue;
			}

			return $candidate->getTimestamp();
		}

		return 0;
	}

	private function normalize_bulk_payload( $payload ) {
		$product_ids_input = isset( $payload['productIdsInput'] ) ? sanitize_text_field( (string) $payload['productIdsInput'] ) : '';
		$product_ids_input = 'all' === sanitize_key( $product_ids_input )
			? 'all'
			: implode( ',', RHWC_Admin::get_csv_ids( $product_ids_input ) );

		$count  = isset( $payload['countPerProduct'] ) ? $payload['countPerProduct'] : ( isset( $payload['count'] ) ? $payload['count'] : 5 );
		$rating = isset( $payload['rating'] ) ? $payload['rating'] : '4-5';
		$names  = isset( $payload['names'] ) ? $payload['names'] : '';
		$style  = isset( $payload['style'] ) ? $payload['style'] : 'short';

		$excluded_products = isset( $payload['excludedProducts'] ) ? $payload['excludedProducts'] : array();
		if ( ! is_array( $excluded_products ) ) {
			$excluded_products = RHWC_Admin::get_csv_ids( $excluded_products );
		}

		$excluded_categories = isset( $payload['excludedCategories'] ) ? $payload['excludedCategories'] : array();
		if ( ! is_array( $excluded_categories ) ) {
			$excluded_categories = RHWC_Admin::get_csv_ids( $excluded_categories );
		}

		$date_from_days = isset( $payload['dateFromDays'] ) ? RHWC_Admin::sanitize_day_offset( $payload['dateFromDays'] ) : 0;
		$date_to_days   = isset( $payload['dateToDays'] ) ? RHWC_Admin::sanitize_day_offset( $payload['dateToDays'] ) : 30;

		if ( $date_from_days > $date_to_days ) {
			$temp           = $date_from_days;
			$date_from_days = $date_to_days;
			$date_to_days   = $temp;
		}

		return array(
			'productIdsInput'    => $product_ids_input,
			'target'             => 'all' === $product_ids_input ? 'all' : 'specific',
			'countPerProduct'    => RHWC_Admin::sanitize_default_count( $count ),
			'rating'             => RHWC_Admin::sanitize_rating_range( $rating ),
			'names'              => RHWC_Admin::sanitize_multiline_textarea( $names ),
			'style'              => RHWC_Admin::sanitize_content_style( $style ),
			'skipGenerated'      => RHWC_Admin::sanitize_yes_no( isset( $payload['skipGenerated'] ) ? $payload['skipGenerated'] : 'yes' ),
			'reviewStatus'       => RHWC_Admin::sanitize_review_status( isset( $payload['reviewStatus'] ) ? $payload['reviewStatus'] : 'approved' ),
			'dateFromDays'       => $date_from_days,
			'dateToDays'         => $date_to_days,
			'excludedProducts'   => array_values( array_unique( array_filter( array_map( 'absint', $excluded_products ) ) ) ),
			'excludedCategories' => array_values( array_unique( array_filter( array_map( 'absint', $excluded_categories ) ) ) ),
			'intervalMinutes'    => $this->sanitize_schedule_interval_minutes( isset( $payload['intervalMinutes'] ) ? $payload['intervalMinutes'] : 30 ),
			'delayMinMinutes'    => $this->sanitize_schedule_delay_minutes( isset( $payload['delayMinMinutes'] ) ? $payload['delayMinMinutes'] : ( isset( $payload['intervalMinutes'] ) ? $payload['intervalMinutes'] : 30 ) ),
			'delayMaxMinutes'    => $this->sanitize_schedule_delay_minutes( isset( $payload['delayMaxMinutes'] ) ? $payload['delayMaxMinutes'] : ( isset( $payload['intervalMinutes'] ) ? $payload['intervalMinutes'] : 30 ) ),
			'recurrenceType'     => $this->sanitize_schedule_recurrence( isset( $payload['recurrenceType'] ) ? $payload['recurrenceType'] : 'once' ),
			'customDays'         => $this->sanitize_schedule_days( isset( $payload['customDays'] ) ? $payload['customDays'] : array() ),
			'scheduleDate'       => isset( $payload['scheduleDate'] ) ? sanitize_text_field( (string) $payload['scheduleDate'] ) : '',
			'scheduleTime'       => isset( $payload['scheduleTime'] ) ? sanitize_text_field( (string) $payload['scheduleTime'] ) : '',
			'scheduleWeekday'    => isset( $payload['scheduleWeekday'] ) ? min( 6, absint( $payload['scheduleWeekday'] ) ) : 0,
		);
	}

	private function get_bulk_request_payload() {
		$settings = RHWC_Admin::get_settings_payload();

		return $this->normalize_bulk_payload(
			array(
				'productIdsInput'    => isset( $_POST['product_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['product_ids'] ) ) : '',
				'countPerProduct'    => isset( $_POST['count'] ) ? sanitize_text_field( wp_unslash( $_POST['count'] ) ) : $settings['defaultCount'],
				'rating'             => isset( $_POST['rating'] ) ? sanitize_text_field( wp_unslash( $_POST['rating'] ) ) : $settings['defaultRating'],
				'names'              => isset( $_POST['names'] ) ? sanitize_textarea_field( wp_unslash( $_POST['names'] ) ) : $settings['defaultNames'],
				'style'              => isset( $_POST['style'] ) ? sanitize_key( wp_unslash( $_POST['style'] ) ) : $settings['contentStyle'],
				'skipGenerated'      => isset( $_POST['exclude_generated'] ) ? sanitize_text_field( wp_unslash( $_POST['exclude_generated'] ) ) : 'true',
				'reviewStatus'       => $settings['reviewStatus'],
				'dateFromDays'       => $settings['dateFromDays'],
				'dateToDays'         => $settings['dateToDays'],
				'excludedProducts'   => RHWC_Admin::get_csv_ids( $settings['excludedProducts'] ),
				'excludedCategories' => RHWC_Admin::get_csv_ids( $settings['excludedCategories'] ),
				'intervalMinutes'    => isset( $_POST['interval_minutes'] ) ? sanitize_text_field( wp_unslash( $_POST['interval_minutes'] ) ) : 30,
				'delayMinMinutes'    => isset( $_POST['delay_min_minutes'] ) ? sanitize_text_field( wp_unslash( $_POST['delay_min_minutes'] ) ) : null,
				'delayMaxMinutes'    => isset( $_POST['delay_max_minutes'] ) ? sanitize_text_field( wp_unslash( $_POST['delay_max_minutes'] ) ) : null,
			)
		);
	}

	private function get_schedule_request_payload() {
		$payload = $this->get_bulk_request_payload();

		$payload['recurrenceType'] = $this->sanitize_schedule_recurrence(
			isset( $_POST['recurrence_type'] ) ? sanitize_key( wp_unslash( $_POST['recurrence_type'] ) ) : 'once'
		);
		$payload['customDays']     = $this->sanitize_schedule_days(
			isset( $_POST['custom_days'] ) ? wp_unslash( $_POST['custom_days'] ) : array()
		);
		$payload['scheduleWeekday'] = isset( $_POST['schedule_weekday'] )
			? min( 6, absint( wp_unslash( $_POST['schedule_weekday'] ) ) )
			: 0;
		$payload['scheduleDate']   = isset( $_POST['schedule_date'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_date'] ) ) : '';
		$payload['scheduleTime']   = isset( $_POST['schedule_time'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_time'] ) ) : '';

		if ( $payload['delayMinMinutes'] > $payload['delayMaxMinutes'] ) {
			$temp                      = $payload['delayMinMinutes'];
			$payload['delayMinMinutes'] = $payload['delayMaxMinutes'];
			$payload['delayMaxMinutes'] = $temp;
		}

		$payload['intervalMinutes'] = $payload['delayMinMinutes'];

		return $payload;
	}

	private function get_bulk_target_label( $target, $requested_products ) {
		if ( 'all' === $target ) {
			return esc_html__( 'All published products', 'review-helper-for-woocommerce' );
		}

		return sprintf(
			/* translators: %d: number of requested products. */
			esc_html__( '%d specific products', 'review-helper-for-woocommerce' ),
			$requested_products
		);
	}

	private function resolve_bulk_product_ids( $payload ) {
		if ( 'all' === $payload['target'] ) {
			return array_values(
				array_filter(
					array_map(
						'absint',
						wc_get_products(
							array(
								'limit'  => -1,
								'status' => 'publish',
								'return' => 'ids',
							)
						)
					)
				)
			);
		}

		return RHWC_Admin::get_csv_ids( $payload['productIdsInput'] );
	}

	private function get_bulk_generation_message( $total_generated, $products_processed, $products_skipped, $products_excluded ) {
		if ( 0 === $products_processed && $products_skipped > 0 && 0 === $products_excluded ) {
			return sprintf(
				/* translators: %d: number of skipped products. */
				esc_html__( 'No new reviews were generated because all %d selected products were already marked as having generated reviews. Disable the skip option if you want to generate more reviews for them.', 'review-helper-for-woocommerce' ),
				$products_skipped
			);
		}

		if ( 0 === $products_processed && $products_excluded > 0 && 0 === $products_skipped ) {
			return sprintf(
				/* translators: %d: number of excluded products. */
				esc_html__( 'No new reviews were generated because all %d selected products were excluded by your product or category rules.', 'review-helper-for-woocommerce' ),
				$products_excluded
			);
		}

		if ( 0 === $products_processed && $products_skipped > 0 && $products_excluded > 0 ) {
			return sprintf(
				/* translators: 1: skipped products count, 2: excluded products count. */
				esc_html__( 'No new reviews were generated. %1$d selected products were already marked as generated and %2$d were excluded by your rules.', 'review-helper-for-woocommerce' ),
				$products_skipped,
				$products_excluded
			);
		}

		if ( $products_skipped > 0 && $products_excluded > 0 ) {
			return sprintf(
				/* translators: 1: reviews generated, 2: processed products, 3: skipped products, 4: excluded products. */
				esc_html__( 'Successfully generated %1$d reviews across %2$d products. Skipped %3$d products that already had generated reviews and excluded %4$d products by your rules.', 'review-helper-for-woocommerce' ),
				$total_generated,
				$products_processed,
				$products_skipped,
				$products_excluded
			);
		}

		if ( $products_skipped > 0 ) {
			return sprintf(
				/* translators: 1: number of generated reviews, 2: number of processed products, 3: number of skipped products. */
				esc_html__( 'Successfully generated %1$d reviews across %2$d products. Skipped %3$d products that already had generated reviews.', 'review-helper-for-woocommerce' ),
				$total_generated,
				$products_processed,
				$products_skipped
			);
		}

		if ( $products_excluded > 0 ) {
			return sprintf(
				/* translators: 1: number of generated reviews, 2: number of processed products, 3: number of excluded products. */
				esc_html__( 'Successfully generated %1$d reviews across %2$d products. Excluded %3$d products by your product or category rules.', 'review-helper-for-woocommerce' ),
				$total_generated,
				$products_processed,
				$products_excluded
			);
		}

		return sprintf(
			/* translators: 1: number of generated reviews, 2: number of products processed. */
			esc_html__( 'Successfully generated %1$d reviews across %2$d products.', 'review-helper-for-woocommerce' ),
			$total_generated,
			$products_processed
		);
	}

	private function run_bulk_generation( $payload ) {
		$payload     = $this->normalize_bulk_payload( $payload );
		$product_ids = $this->resolve_bulk_product_ids( $payload );

		if ( empty( $product_ids ) ) {
			return new WP_Error( 'no_products', esc_html__( 'No products selected.', 'review-helper-for-woocommerce' ) );
		}

		$total_generated       = 0;
		$products_processed    = 0;
		$products_skipped      = 0;
		$products_excluded     = 0;
		$generated_comment_ids = array();
		$generated_product_ids = array();
		$generation_args       = array(
			'reviewStatus' => $payload['reviewStatus'],
			'dateFromDays' => $payload['dateFromDays'],
			'dateToDays'   => $payload['dateToDays'],
		);

		foreach ( $product_ids as $product_id ) {
			if ( $this->is_bulk_excluded_product( $product_id, $payload['excludedProducts'], $payload['excludedCategories'] ) ) {
				++$products_excluded;
				continue;
			}

			if ( 'yes' === $payload['skipGenerated'] ) {
				$has_generated = get_post_meta( $product_id, '_rhwc_generated_reviews', true );
				if ( 'yes' === $has_generated ) {
					++$products_skipped;
					continue;
				}
			}

			$result = RHWC_Generator::generate_reviews(
				$product_id,
				$payload['countPerProduct'],
				$payload['rating'],
				$payload['names'],
				$payload['style'],
				true,
				$generation_args
			);

			if ( is_wp_error( $result ) ) {
				continue;
			}

			$total_generated += $result['count'];
			++$products_processed;

			if ( ! empty( $result['commentIds'] ) ) {
				$generated_comment_ids = array_merge( $generated_comment_ids, $result['commentIds'] );
				$generated_product_ids[] = (int) $product_id;
			}
		}

		$requested_products = count( $product_ids );

		if ( ! empty( $generated_comment_ids ) ) {
			RHWC_Generator::save_last_bulk_batch(
				array(
					'id'                => wp_generate_uuid4(),
					'createdAt'         => current_time( 'mysql' ),
					'generated'         => $total_generated,
					'processed'         => $products_processed,
					'skipped'           => $products_skipped,
					'target'            => $payload['target'],
					'targetLabel'       => $this->get_bulk_target_label( $payload['target'], $requested_products ),
					'requestedProducts' => $requested_products,
					'countPerProduct'   => $payload['countPerProduct'],
					'rating'            => $payload['rating'],
					'style'             => $payload['style'],
					'skipGenerated'     => $payload['skipGenerated'],
					'productIds'        => array_values( array_unique( $generated_product_ids ) ),
					'commentIds'        => array_values( array_unique( array_map( 'absint', $generated_comment_ids ) ) ),
				)
			);
		}

		return array(
			'message'   => $this->get_bulk_generation_message( $total_generated, $products_processed, $products_skipped, $products_excluded ),
			'generated' => $total_generated,
			'processed' => $products_processed,
			'skipped'   => $products_skipped,
			'excluded'  => $products_excluded,
			'lastBatch' => RHWC_Generator::get_last_bulk_batch_summary(),
		);
	}

	private function parse_schedule_timestamp( $schedule_date, $schedule_time ) {
		$schedule_date = sanitize_text_field( (string) $schedule_date );
		$schedule_time = sanitize_text_field( (string) $schedule_time );

		if ( empty( $schedule_date ) || empty( $schedule_time ) ) {
			return new WP_Error( 'missing_schedule', esc_html__( 'Choose both a schedule date and time.', 'review-helper-for-woocommerce' ) );
		}

		$datetime = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $schedule_date . ' ' . $schedule_time, wp_timezone() );
		$errors   = DateTimeImmutable::getLastErrors();

		if ( false === $datetime || ( is_array( $errors ) && ( ! empty( $errors['warning_count'] ) || ! empty( $errors['error_count'] ) ) ) ) {
			return new WP_Error( 'invalid_schedule', esc_html__( 'The scheduled date or time is invalid.', 'review-helper-for-woocommerce' ) );
		}

		$timestamp = $datetime->getTimestamp();

		if ( $timestamp <= time() ) {
			return new WP_Error( 'past_schedule', esc_html__( 'Choose a future date and time for the scheduled run.', 'review-helper-for-woocommerce' ) );
		}

		return $timestamp;
	}

	private function unschedule_saved_bulk_job( $job = null ) {
		$job = empty( $job ) ? RHWC_Generator::get_scheduled_bulk_job() : $job;

		if ( empty( $job ) || empty( $job['id'] ) ) {
			return true;
		}

		$result = wp_clear_scheduled_hook( 'rhwc_run_scheduled_bulk_generation', array( $job['id'] ), true );

		return is_wp_error( $result ) ? $result : true;
	}

	private function create_scheduled_bulk_job( $payload, $timestamp, $existing_job = null ) {
		$payload = $this->normalize_bulk_payload( $payload );
		$queue   = $this->build_scheduled_review_queue( $payload );
		$existing_job = is_array( $existing_job ) ? $existing_job : array();

		if ( is_wp_error( $queue ) ) {
			return $queue;
		}

		$schedule_date = isset( $payload['scheduleDate'] ) ? sanitize_text_field( (string) $payload['scheduleDate'] ) : gmdate( 'Y-m-d', $timestamp );
		$schedule_time = isset( $payload['scheduleTime'] ) ? sanitize_text_field( (string) $payload['scheduleTime'] ) : gmdate( 'H:i', $timestamp );
		$time_parts    = explode( ':', $schedule_time );
		$schedule_time = sprintf(
			'%02d:%02d',
			isset( $time_parts[0] ) ? min( 23, absint( $time_parts[0] ) ) : 0,
			isset( $time_parts[1] ) ? min( 59, absint( $time_parts[1] ) ) : 0
		);

		$schedule_weekday = isset( $payload['scheduleWeekday'] ) ? absint( $payload['scheduleWeekday'] ) : (int) wp_date( 'w', $timestamp, wp_timezone() );
		$schedule_weekday = min( 6, $schedule_weekday );

		return array(
			'id'                 => ! empty( $existing_job['id'] ) ? $existing_job['id'] : wp_generate_uuid4(),
			'batchId'            => wp_generate_uuid4(),
			'createdAt'          => ! empty( $existing_job['createdAt'] ) ? $existing_job['createdAt'] : current_time( 'mysql' ),
			'runAt'              => wp_date( 'Y-m-d H:i:s', $timestamp, wp_timezone() ),
			'timestamp'          => $timestamp,
			'status'             => 'active',
			'recurrenceType'     => isset( $payload['recurrenceType'] ) ? $payload['recurrenceType'] : 'once',
			'customDays'         => isset( $payload['customDays'] ) ? $payload['customDays'] : array(),
			'scheduleDate'       => $schedule_date,
			'scheduleTime'       => $schedule_time,
			'scheduleWeekday'    => $schedule_weekday,
			'target'             => $payload['target'],
			'targetLabel'        => $this->get_bulk_target_label( $payload['target'], $queue['requestedProducts'] ),
			'requestedProducts'  => $queue['requestedProducts'],
			'countPerProduct'    => $payload['countPerProduct'],
			'rating'             => $payload['rating'],
			'style'              => $payload['style'],
			'names'              => $payload['names'],
			'skipGenerated'      => $payload['skipGenerated'],
			'reviewStatus'       => $payload['reviewStatus'],
			'dateFromDays'       => $payload['dateFromDays'],
			'dateToDays'         => $payload['dateToDays'],
			'excludedProducts'   => $payload['excludedProducts'],
			'excludedCategories' => $payload['excludedCategories'],
			'excludedCount'      => $queue['excludedProducts'],
			'skippedCount'       => $queue['skippedProducts'],
			'delayMinMinutes'    => isset( $payload['delayMinMinutes'] ) ? $payload['delayMinMinutes'] : $payload['intervalMinutes'],
			'delayMaxMinutes'    => isset( $payload['delayMaxMinutes'] ) ? $payload['delayMaxMinutes'] : $payload['intervalMinutes'],
			'intervalMinutes'    => $payload['intervalMinutes'],
			'plannedReviews'     => $queue['plannedReviews'],
			'remainingReviews'   => $queue['plannedReviews'],
			'cycleNumber'        => 1,
			'lastRunAt'          => '',
			'generatedCommentIds' => array(),
			'generatedProductIds' => array(),
			'reviewQueue'        => $queue['reviewQueue'],
			'productIdsInput'    => $payload['productIdsInput'],
		);
	}

	private function create_imported_scheduled_job( $raw_job ) {
		if ( ! is_array( $raw_job ) ) {
			return new WP_Error( 'invalid_job', esc_html__( 'One imported schedule job was invalid.', 'review-helper-for-woocommerce' ) );
		}

		$payload = $this->normalize_bulk_payload(
			array(
				'productIdsInput'    => isset( $raw_job['productIdsInput'] ) ? $raw_job['productIdsInput'] : ( isset( $raw_job['target'] ) && 'all' === $raw_job['target'] ? 'all' : '' ),
				'countPerProduct'    => isset( $raw_job['countPerProduct'] ) ? $raw_job['countPerProduct'] : 5,
				'rating'             => isset( $raw_job['rating'] ) ? $raw_job['rating'] : '4-5',
				'names'              => isset( $raw_job['names'] ) ? $raw_job['names'] : '',
				'style'              => isset( $raw_job['style'] ) ? $raw_job['style'] : 'short',
				'skipGenerated'      => isset( $raw_job['skipGenerated'] ) ? $raw_job['skipGenerated'] : 'yes',
				'reviewStatus'       => isset( $raw_job['reviewStatus'] ) ? $raw_job['reviewStatus'] : 'approved',
				'dateFromDays'       => isset( $raw_job['dateFromDays'] ) ? $raw_job['dateFromDays'] : 0,
				'dateToDays'         => isset( $raw_job['dateToDays'] ) ? $raw_job['dateToDays'] : 30,
				'excludedProducts'   => isset( $raw_job['excludedProducts'] ) ? $raw_job['excludedProducts'] : array(),
				'excludedCategories' => isset( $raw_job['excludedCategories'] ) ? $raw_job['excludedCategories'] : array(),
				'intervalMinutes'    => isset( $raw_job['intervalMinutes'] ) ? $raw_job['intervalMinutes'] : 30,
				'delayMinMinutes'    => isset( $raw_job['delayMinMinutes'] ) ? $raw_job['delayMinMinutes'] : 30,
				'delayMaxMinutes'    => isset( $raw_job['delayMaxMinutes'] ) ? $raw_job['delayMaxMinutes'] : 30,
				'recurrenceType'     => isset( $raw_job['recurrenceType'] ) ? $raw_job['recurrenceType'] : 'once',
				'customDays'         => isset( $raw_job['customDays'] ) ? $raw_job['customDays'] : array(),
				'scheduleDate'       => isset( $raw_job['scheduleDate'] ) ? $raw_job['scheduleDate'] : '',
				'scheduleTime'       => isset( $raw_job['scheduleTime'] ) ? $raw_job['scheduleTime'] : '',
				'scheduleWeekday'    => isset( $raw_job['scheduleWeekday'] ) ? $raw_job['scheduleWeekday'] : 0,
			)
		);

		$timestamp = isset( $raw_job['timestamp'] ) ? (int) $raw_job['timestamp'] : 0;

		if ( $timestamp <= time() ) {
			$timestamp = time() + ( 5 * MINUTE_IN_SECONDS );
		}

		$job = $this->create_scheduled_bulk_job( $payload, $timestamp );

		if ( is_wp_error( $job ) ) {
			return $job;
		}

		if ( isset( $raw_job['reviewQueue'] ) && is_array( $raw_job['reviewQueue'] ) ) {
			$queue = array();

			foreach ( $raw_job['reviewQueue'] as $queue_item ) {
				if ( ! is_array( $queue_item ) ) {
					continue;
				}

				$product_id = isset( $queue_item['productId'] ) ? absint( $queue_item['productId'] ) : 0;
				$remaining  = isset( $queue_item['remaining'] ) ? absint( $queue_item['remaining'] ) : 0;

				if ( ! $product_id || ! $remaining ) {
					continue;
				}

				$queue[] = array(
					'productId' => $product_id,
					'remaining' => $remaining,
				);
			}

			if ( ! empty( $queue ) ) {
				$job['reviewQueue'] = $queue;
				$job['remainingReviews'] = array_sum(
					array_map(
						static function ( $queue_item ) {
							return (int) $queue_item['remaining'];
						},
						$queue
					)
				);
				$job['plannedReviews'] = isset( $raw_job['plannedReviews'] )
					? max( $job['remainingReviews'], absint( $raw_job['plannedReviews'] ) )
					: $job['remainingReviews'];
			}
		}

		if ( isset( $raw_job['generatedCommentIds'] ) && is_array( $raw_job['generatedCommentIds'] ) ) {
			$job['generatedCommentIds'] = array_values(
				array_unique(
					array_filter( array_map( 'absint', $raw_job['generatedCommentIds'] ) )
				)
			);
		}

		if ( isset( $raw_job['generatedProductIds'] ) && is_array( $raw_job['generatedProductIds'] ) ) {
			$job['generatedProductIds'] = array_values(
				array_unique(
					array_filter( array_map( 'absint', $raw_job['generatedProductIds'] ) )
				)
			);
		}

		$job['cycleNumber'] = isset( $raw_job['cycleNumber'] )
			? max( 1, absint( $raw_job['cycleNumber'] ) )
			: 1;
		$job['lastRunAt']   = isset( $raw_job['lastRunAt'] )
			? sanitize_text_field( (string) $raw_job['lastRunAt'] )
			: '';

		if ( isset( $raw_job['status'] ) && 'paused' === sanitize_key( (string) $raw_job['status'] ) ) {
			$job['status'] = 'paused';
		}

		return $job;
	}

	private function build_scheduled_review_queue( $payload ) {
		$payload     = $this->normalize_bulk_payload( $payload );
		$product_ids = $this->resolve_bulk_product_ids( $payload );

		if ( empty( $product_ids ) ) {
			return new WP_Error( 'no_products', esc_html__( 'No products selected.', 'review-helper-for-woocommerce' ) );
		}

		$review_queue      = array();
		$skipped_products  = 0;
		$excluded_products = 0;

		foreach ( $product_ids as $product_id ) {
			if ( $this->is_bulk_excluded_product( $product_id, $payload['excludedProducts'], $payload['excludedCategories'] ) ) {
				++$excluded_products;
				continue;
			}

			if ( 'yes' === $payload['skipGenerated'] && 'yes' === get_post_meta( $product_id, '_rhwc_generated_reviews', true ) ) {
				++$skipped_products;
				continue;
			}

			$review_queue[] = array(
				'productId' => (int) $product_id,
				'remaining' => (int) $payload['countPerProduct'],
			);
		}

		if ( empty( $review_queue ) ) {
			return new WP_Error(
				'no_reviews_to_schedule',
				$this->get_bulk_generation_message( 0, 0, $skipped_products, $excluded_products )
			);
		}

		return array(
			'reviewQueue'      => $review_queue,
			'requestedProducts' => count( $product_ids ),
			'plannedReviews'   => count( $review_queue ) * (int) $payload['countPerProduct'],
			'skippedProducts'  => $skipped_products,
			'excludedProducts' => $excluded_products,
		);
	}

	private function prime_scheduled_bulk_job_cycle( $job, $timestamp = 0 ) {
		$queue = $this->build_scheduled_review_queue( $job );

		if ( is_wp_error( $queue ) ) {
			return $queue;
		}

		$job['batchId']            = wp_generate_uuid4();
		$job['requestedProducts']  = $queue['requestedProducts'];
		$job['targetLabel']        = $this->get_bulk_target_label( $job['target'], $queue['requestedProducts'] );
		$job['excludedCount']      = $queue['excludedProducts'];
		$job['skippedCount']       = $queue['skippedProducts'];
		$job['plannedReviews']     = $queue['plannedReviews'];
		$job['remainingReviews']   = $queue['plannedReviews'];
		$job['generatedCommentIds'] = array();
		$job['generatedProductIds'] = array();
		$job['reviewQueue']        = $queue['reviewQueue'];

		if ( $timestamp > 0 ) {
			$job['timestamp'] = (int) $timestamp;
			$job['runAt']     = wp_date( 'Y-m-d H:i:s', $job['timestamp'], wp_timezone() );
		}

		return $job;
	}

	private function save_last_bulk_batch_from_job( $job ) {
		if ( empty( $job['generatedCommentIds'] ) || ! is_array( $job['generatedCommentIds'] ) ) {
			return;
		}

		RHWC_Generator::save_last_bulk_batch(
			array(
				'id'                => $job['batchId'],
				'createdAt'         => current_time( 'mysql' ),
				'generated'         => count( $job['generatedCommentIds'] ),
				'processed'         => count( $job['generatedProductIds'] ),
				'skipped'           => absint( $job['skippedCount'] ),
				'target'            => $job['target'],
				'targetLabel'       => $job['targetLabel'],
				'requestedProducts' => absint( $job['requestedProducts'] ),
				'countPerProduct'   => absint( $job['countPerProduct'] ),
				'rating'            => $job['rating'],
				'style'             => $job['style'],
				'skipGenerated'     => $job['skipGenerated'],
				'productIds'        => $job['generatedProductIds'],
				'commentIds'        => $job['generatedCommentIds'],
			)
		);
	}

	private function schedule_next_recurrence( $job, $message_prefix = '' ) {
		$next_timestamp = $this->get_next_recurrence_timestamp( $job, max( time(), (int) $job['timestamp'] ) );

		if ( $next_timestamp < 1 ) {
			$this->add_schedule_history_entry(
				$job,
				'recurrence_failed',
				esc_html__( 'The next recurring run could not be calculated, so this schedule was removed.', 'review-helper-for-woocommerce' ),
				'error'
			);
			RHWC_Generator::clear_scheduled_bulk_job( $job['id'] );
			return;
		}

		$job['cycleNumber']         = max( 1, absint( $job['cycleNumber'] ) ) + 1;
		$job['timestamp']           = $next_timestamp;
		$job['runAt']               = wp_date( 'Y-m-d H:i:s', $next_timestamp, wp_timezone() );
		$job['plannedReviews']      = 0;
		$job['remainingReviews']    = 0;
		$job['generatedCommentIds'] = array();
		$job['generatedProductIds'] = array();
		$job['reviewQueue']         = array();
		$job['lastRunAt']           = current_time( 'mysql' );

		RHWC_Generator::save_scheduled_bulk_job( $job );

		$scheduled = $this->schedule_job_event( $job, $next_timestamp );

		if ( is_wp_error( $scheduled ) || false === $scheduled ) {
			$job['status'] = 'paused';
			RHWC_Generator::save_scheduled_bulk_job( $job );
			$this->add_schedule_history_entry(
				$job,
				'recurrence_failed',
				esc_html__( 'The next recurring cycle could not be queued, so the schedule was paused.', 'review-helper-for-woocommerce' ),
				'error'
			);
			return;
		}

		$this->add_schedule_history_entry(
			$job,
			'recurrence_queued',
			sprintf(
				/* translators: 1: optional message prefix, 2: next run time. */
				esc_html__( '%1$sNext cycle queued for %2$s.', 'review-helper-for-woocommerce' ),
				$message_prefix,
				$job['runAt']
			),
			'success'
		);
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

		$result = $this->run_bulk_generation( $this->get_bulk_request_payload() );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				),
				400
			);
		}

		wp_send_json_success( $result );
	}

	public function schedule_bulk_generation() {
		if ( ! check_ajax_referer( 'rhwc_ajax_nonce', 'security', false ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed.', 'review-helper-for-woocommerce' ),
				),
				403
			);
		}
		$this->check_permissions();

		$payload        = $this->get_schedule_request_payload();
		$timestamp      = $this->parse_schedule_timestamp( $payload['scheduleDate'], $payload['scheduleTime'] );
		$editing_job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
		$existing_job   = ! empty( $editing_job_id ) ? RHWC_Generator::get_scheduled_bulk_job( $editing_job_id ) : null;

		if ( is_wp_error( $timestamp ) ) {
			wp_send_json_error(
				array(
					'message' => $timestamp->get_error_message(),
				),
				400
			);
		}

		if ( ! empty( $editing_job_id ) && empty( $existing_job ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'The scheduled job you tried to edit could not be found.', 'review-helper-for-woocommerce' ),
				),
				404
			);
		}

		if ( 'weekly' !== $payload['recurrenceType'] || empty( $payload['scheduleWeekday'] ) ) {
			$payload['scheduleWeekday'] = (int) wp_date( 'w', $timestamp, wp_timezone() );
		}
		$job                        = $this->create_scheduled_bulk_job( $payload, $timestamp, $existing_job );

		if ( is_wp_error( $job ) ) {
			wp_send_json_error(
				array(
					'message' => $job->get_error_message(),
				),
				400
			);
		}

		if ( ! empty( $existing_job ) ) {
			$unschedule = $this->unschedule_saved_bulk_job( $existing_job );

			if ( is_wp_error( $unschedule ) ) {
				wp_send_json_error(
					array(
						'message' => esc_html__( 'The existing schedule could not be updated right now.', 'review-helper-for-woocommerce' ),
					),
					500
				);
			}
		}

		RHWC_Generator::save_scheduled_bulk_job( $job );
		$scheduled = $this->schedule_job_event( $job, $job['timestamp'] );

		if ( is_wp_error( $scheduled ) || false === $scheduled ) {
			RHWC_Generator::clear_scheduled_bulk_job( $job['id'] );

			wp_send_json_error(
				array(
					'message' => esc_html__( 'WordPress could not schedule the review generation job.', 'review-helper-for-woocommerce' ),
				),
				500
			);
		}

		$this->add_schedule_history_entry(
			$job,
			! empty( $existing_job ) ? 'updated' : 'scheduled',
			sprintf(
				/* translators: 1: recurrence label, 2: planned reviews, 3: scheduled start time. */
				! empty( $existing_job )
					? esc_html__( 'Updated the %1$s schedule with %2$d planned reviews starting at %3$s.', 'review-helper-for-woocommerce' )
					: esc_html__( 'Queued a %1$s schedule for %2$d planned reviews starting at %3$s.', 'review-helper-for-woocommerce' ),
				$this->get_schedule_recurrence_label( $job ),
				$job['plannedReviews'],
				$job['runAt']
			),
			'success'
		);

		$delay_message = $job['delayMinMinutes'] === $job['delayMaxMinutes']
			? sprintf(
				/* translators: %d: delay in minutes. */
				esc_html__( 'Each review will wait %d minutes before the next one.', 'review-helper-for-woocommerce' ),
				$job['delayMinMinutes']
			)
			: sprintf(
				/* translators: 1: minimum delay in minutes, 2: maximum delay in minutes. */
				esc_html__( 'Each review will wait a random %1$d to %2$d minutes before the next one.', 'review-helper-for-woocommerce' ),
				$job['delayMinMinutes'],
				$job['delayMaxMinutes']
			);

		wp_send_json_success(
			$this->get_schedule_response_data(
				sprintf(
					/* translators: 1: total reviews planned, 2: scheduled local date and time, 3: delay message. */
					! empty( $existing_job )
						? esc_html__( 'Updated the scheduled job with %1$d reviews starting at %2$s. %3$s', 'review-helper-for-woocommerce' )
						: esc_html__( 'Scheduled %1$d reviews starting at %2$s. %3$s', 'review-helper-for-woocommerce' ),
					$job['plannedReviews'],
					$job['runAt'],
					$delay_message
				)
			)
		);
	}

	public function cancel_scheduled_bulk_generation() {
		if ( ! check_ajax_referer( 'rhwc_ajax_nonce', 'security', false ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed.', 'review-helper-for-woocommerce' ),
				),
				403
			);
		}
		$this->check_permissions();

		$job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
		$job    = RHWC_Generator::get_scheduled_bulk_job( $job_id );

		if ( empty( $job ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'There is no scheduled bulk generation job to cancel.', 'review-helper-for-woocommerce' ),
				),
				400
			);
		}

		$unschedule = $this->unschedule_saved_bulk_job( $job );

		if ( is_wp_error( $unschedule ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'The scheduled review generation job could not be canceled.', 'review-helper-for-woocommerce' ),
				),
				500
			);
		}

		RHWC_Generator::clear_scheduled_bulk_job( $job['id'] );
		$this->add_schedule_history_entry(
			$job,
			'canceled',
			esc_html__( 'The scheduled job was canceled manually from the admin screen.', 'review-helper-for-woocommerce' ),
			'warning'
		);

		wp_send_json_success( $this->get_schedule_response_data( esc_html__( 'The scheduled bulk review generation job was canceled.', 'review-helper-for-woocommerce' ) ) );
	}

	public function pause_scheduled_bulk_generation() {
		if ( ! check_ajax_referer( 'rhwc_ajax_nonce', 'security', false ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed.', 'review-helper-for-woocommerce' ),
				),
				403
			);
		}
		$this->check_permissions();

		$job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
		$job    = RHWC_Generator::get_scheduled_bulk_job( $job_id );

		if ( empty( $job ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'That scheduled job could not be found.', 'review-helper-for-woocommerce' ),
				),
				404
			);
		}

		if ( 'paused' === $job['status'] ) {
			wp_send_json_success( $this->get_schedule_response_data( esc_html__( 'That scheduled job is already paused.', 'review-helper-for-woocommerce' ) ) );
		}

		$unschedule = $this->unschedule_saved_bulk_job( $job );

		if ( is_wp_error( $unschedule ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'The schedule could not be paused right now.', 'review-helper-for-woocommerce' ),
				),
				500
			);
		}

		$job['status'] = 'paused';
		RHWC_Generator::save_scheduled_bulk_job( $job );
		$this->add_schedule_history_entry(
			$job,
			'paused',
			esc_html__( 'The schedule was paused manually.', 'review-helper-for-woocommerce' ),
			'warning'
		);

		wp_send_json_success( $this->get_schedule_response_data( esc_html__( 'The scheduled job is now paused.', 'review-helper-for-woocommerce' ) ) );
	}

	public function resume_scheduled_bulk_generation() {
		if ( ! check_ajax_referer( 'rhwc_ajax_nonce', 'security', false ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed.', 'review-helper-for-woocommerce' ),
				),
				403
			);
		}
		$this->check_permissions();

		$job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
		$job    = RHWC_Generator::get_scheduled_bulk_job( $job_id );

		if ( empty( $job ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'That scheduled job could not be found.', 'review-helper-for-woocommerce' ),
				),
				404
			);
		}

		if ( 'paused' !== $job['status'] ) {
			wp_send_json_success( $this->get_schedule_response_data( esc_html__( 'That scheduled job is already active.', 'review-helper-for-woocommerce' ) ) );
		}

		if ( ! empty( $job['reviewQueue'] ) && absint( $job['remainingReviews'] ) > 0 ) {
			$resume_timestamp = ! empty( $job['timestamp'] ) && (int) $job['timestamp'] > time()
				? (int) $job['timestamp']
				: time() + MINUTE_IN_SECONDS;
		} else {
			$resume_timestamp = ! empty( $job['timestamp'] ) && (int) $job['timestamp'] > time()
				? (int) $job['timestamp']
				: $this->get_next_recurrence_timestamp( $job, time() );
		}

		if ( $resume_timestamp < 1 ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'The next schedule time could not be calculated for this job.', 'review-helper-for-woocommerce' ),
				),
				400
			);
		}

		$job['status']    = 'active';
		$job['timestamp'] = $resume_timestamp;
		$job['runAt']     = wp_date( 'Y-m-d H:i:s', $resume_timestamp, wp_timezone() );

		RHWC_Generator::save_scheduled_bulk_job( $job );

		$scheduled = $this->schedule_job_event( $job, $resume_timestamp );

		if ( is_wp_error( $scheduled ) || false === $scheduled ) {
			$job['status'] = 'paused';
			RHWC_Generator::save_scheduled_bulk_job( $job );

			wp_send_json_error(
				array(
					'message' => esc_html__( 'WordPress could not resume the scheduled job.', 'review-helper-for-woocommerce' ),
				),
				500
			);
		}

		$this->add_schedule_history_entry(
			$job,
			'resumed',
			sprintf(
				/* translators: %s: resumed run time. */
				esc_html__( 'The schedule was resumed and re-queued for %s.', 'review-helper-for-woocommerce' ),
				$job['runAt']
			),
			'success'
		);

		wp_send_json_success( $this->get_schedule_response_data( esc_html__( 'The scheduled job is active again.', 'review-helper-for-woocommerce' ) ) );
	}

	public function run_scheduled_bulk_generation( $job_id = '' ) {
		$job_id = sanitize_text_field( (string) $job_id );
		$job    = RHWC_Generator::get_scheduled_bulk_job( $job_id );

		if ( empty( $job ) ) {
			return;
		}

		if ( ! empty( $job_id ) && $job_id !== $job['id'] ) {
			return;
		}

		if ( 'paused' === $job['status'] ) {
			return;
		}

		if ( empty( $job['reviewQueue'] ) || absint( $job['remainingReviews'] ) < 1 ) {
			$primed_job = $this->prime_scheduled_bulk_job_cycle( $job, $job['timestamp'] );

			if ( is_wp_error( $primed_job ) ) {
				$this->add_schedule_history_entry(
					$job,
					'cycle_skipped',
					$primed_job->get_error_message(),
					'warning'
				);

				if ( 'once' === $job['recurrenceType'] ) {
					RHWC_Generator::clear_scheduled_bulk_job( $job['id'] );
					return;
				}

				$this->schedule_next_recurrence( $job );
				return;
			}

			$job = $primed_job;
			RHWC_Generator::save_scheduled_bulk_job( $job );
			$this->add_schedule_history_entry(
				$job,
				'cycle_started',
				sprintf(
					/* translators: 1: cycle number, 2: planned reviews. */
					esc_html__( 'Cycle %1$d started with %2$d planned reviews.', 'review-helper-for-woocommerce' ),
					$job['cycleNumber'],
					$job['plannedReviews']
				),
				'info'
			);
		}

		$queue_item = array_shift( $job['reviewQueue'] );

		if ( ! is_array( $queue_item ) || empty( $queue_item['productId'] ) ) {
			RHWC_Generator::clear_scheduled_bulk_job( $job['id'] );
			return;
		}

		$result = RHWC_Generator::generate_reviews(
			(int) $queue_item['productId'],
			1,
			$job['rating'],
			$job['names'],
			$job['style'],
			true,
			array(
				'reviewStatus' => $job['reviewStatus'],
				'dateFromDays' => $job['dateFromDays'],
				'dateToDays'   => $job['dateToDays'],
			)
		);

		$job['lastRunAt'] = current_time( 'mysql' );

		if ( ! is_wp_error( $result ) && ! empty( $result['commentIds'] ) ) {
			$job['generatedCommentIds'] = array_values(
				array_unique(
					array_merge( $job['generatedCommentIds'], array_map( 'absint', $result['commentIds'] ) )
				)
			);
			$job['generatedProductIds'][] = (int) $queue_item['productId'];
			$job['generatedProductIds']    = array_values( array_unique( array_map( 'absint', $job['generatedProductIds'] ) ) );

			$this->add_schedule_history_entry(
				$job,
				'review_generated',
				sprintf(
					/* translators: 1: product ID, 2: remaining reviews. */
					esc_html__( 'Generated one scheduled review for product #%1$d. %2$d reviews remain in this cycle.', 'review-helper-for-woocommerce' ),
					(int) $queue_item['productId'],
					max( 0, absint( $job['remainingReviews'] ) - 1 )
				),
				'success'
			);
		} elseif ( is_wp_error( $result ) ) {
			$this->add_schedule_history_entry(
				$job,
				'review_failed',
				sprintf(
					/* translators: 1: product ID, 2: error message. */
					esc_html__( 'Scheduled review generation failed for product #%1$d: %2$s', 'review-helper-for-woocommerce' ),
					(int) $queue_item['productId'],
					$result->get_error_message()
				),
				'error'
			);
		}

		$queue_item['remaining'] = max( 0, absint( $queue_item['remaining'] ) - 1 );

		if ( $queue_item['remaining'] > 0 ) {
			$job['reviewQueue'][] = array(
				'productId' => (int) $queue_item['productId'],
				'remaining' => (int) $queue_item['remaining'],
			);
		}

		$job['remainingReviews'] = max( 0, absint( $job['remainingReviews'] ) - 1 );

		if ( ! empty( $job['reviewQueue'] ) && $job['remainingReviews'] > 0 ) {
			$next_timestamp   = time() + $this->get_schedule_delay_seconds( $job );
			$job['timestamp'] = $next_timestamp;
			$job['runAt']     = wp_date( 'Y-m-d H:i:s', $next_timestamp, wp_timezone() );

			RHWC_Generator::save_scheduled_bulk_job( $job );
			$scheduled = $this->schedule_job_event( $job, $next_timestamp );

			if ( is_wp_error( $scheduled ) || false === $scheduled ) {
				$job['status'] = 'paused';
				RHWC_Generator::save_scheduled_bulk_job( $job );
				$this->add_schedule_history_entry(
					$job,
					'requeue_failed',
					esc_html__( 'The next scheduled review could not be queued, so the job was paused.', 'review-helper-for-woocommerce' ),
					'error'
				);
			}

			return;
		}

		if ( ! empty( $job['generatedCommentIds'] ) ) {
			$this->save_last_bulk_batch_from_job( $job );
		}

		$this->add_schedule_history_entry(
			$job,
			'cycle_completed',
			sprintf(
				/* translators: 1: cycle number, 2: generated reviews, 3: processed products. */
				esc_html__( 'Cycle %1$d completed with %2$d generated reviews across %3$d products.', 'review-helper-for-woocommerce' ),
				$job['cycleNumber'],
				count( $job['generatedCommentIds'] ),
				count( $job['generatedProductIds'] )
			),
			! empty( $job['generatedCommentIds'] ) ? 'success' : 'warning'
		);

		if ( 'once' === $job['recurrenceType'] ) {
			RHWC_Generator::clear_scheduled_bulk_job( $job['id'] );
			return;
		}

		$this->schedule_next_recurrence( $job );
	}

	public function run_schedule_now() {
		if ( ! check_ajax_referer( 'rhwc_ajax_nonce', 'security', false ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed.', 'review-helper-for-woocommerce' ),
				),
				403
			);
		}
		$this->check_permissions();

		$job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
		$job    = RHWC_Generator::get_scheduled_bulk_job( $job_id );

		if ( empty( $job ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'That scheduled job could not be found.', 'review-helper-for-woocommerce' ),
				),
				404
			);
		}

		$unschedule = $this->unschedule_saved_bulk_job( $job );

		if ( is_wp_error( $unschedule ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'The saved cron event could not be updated right now.', 'review-helper-for-woocommerce' ),
				),
				500
			);
		}

		$job['status']    = 'active';
		$job['timestamp'] = time();
		$job['runAt']     = wp_date( 'Y-m-d H:i:s', $job['timestamp'], wp_timezone() );

		RHWC_Generator::save_scheduled_bulk_job( $job );
		$this->add_schedule_history_entry(
			$job,
			'run_now',
			esc_html__( 'Manual run triggered from the schedule tab.', 'review-helper-for-woocommerce' ),
			'info'
		);

		$this->run_scheduled_bulk_generation( $job['id'] );

		wp_send_json_success(
			$this->get_schedule_response_data(
				esc_html__( 'The scheduled job ran immediately and the queue has been refreshed.', 'review-helper-for-woocommerce' )
			)
		);
	}

	public function save_schedule_template() {
		if ( ! check_ajax_referer( 'rhwc_ajax_nonce', 'security', false ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed.', 'review-helper-for-woocommerce' ),
				),
				403
			);
		}
		$this->check_permissions();

		$template_name = isset( $_POST['template_name'] ) ? sanitize_text_field( wp_unslash( $_POST['template_name'] ) ) : '';
		$template_id   = isset( $_POST['template_id'] ) ? sanitize_text_field( wp_unslash( $_POST['template_id'] ) ) : '';
		$existing      = array();

		if ( ! empty( $template_id ) ) {
			foreach ( RHWC_Generator::get_schedule_templates() as $template ) {
				if ( $template['id'] === $template_id ) {
					$existing = $template;
					break;
				}
			}
		}

		$template = $this->create_schedule_template_from_payload(
			$this->get_schedule_request_payload(),
			$template_name,
			$existing
		);

		if ( is_wp_error( $template ) ) {
			wp_send_json_error(
				array(
					'message' => $template->get_error_message(),
				),
				400
			);
		}

		RHWC_Generator::save_schedule_template( $template );

		wp_send_json_success(
			$this->get_schedule_response_data(
				sprintf(
					/* translators: %s: template name. */
					esc_html__( 'Saved the "%s" schedule template.', 'review-helper-for-woocommerce' ),
					$template['name']
				)
			)
		);
	}

	public function delete_schedule_template() {
		if ( ! check_ajax_referer( 'rhwc_ajax_nonce', 'security', false ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed.', 'review-helper-for-woocommerce' ),
				),
				403
			);
		}
		$this->check_permissions();

		$template_id = isset( $_POST['template_id'] ) ? sanitize_text_field( wp_unslash( $_POST['template_id'] ) ) : '';

		if ( empty( $template_id ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'No schedule template was selected.', 'review-helper-for-woocommerce' ),
				),
				400
			);
		}

		if ( ! RHWC_Generator::delete_schedule_template( $template_id ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'That schedule template could not be found.', 'review-helper-for-woocommerce' ),
				),
				404
			);
		}

		wp_send_json_success(
			$this->get_schedule_response_data(
				esc_html__( 'The schedule template was deleted.', 'review-helper-for-woocommerce' )
			)
		);
	}

	public function import_schedules() {
		if ( ! check_ajax_referer( 'rhwc_ajax_nonce', 'security', false ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed.', 'review-helper-for-woocommerce' ),
				),
				403
			);
		}
		$this->check_permissions();

		$schedules_json = isset( $_POST['schedules_json'] ) ? wp_unslash( $_POST['schedules_json'] ) : '';
		$data           = json_decode( (string) $schedules_json, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'The imported schedules JSON is invalid.', 'review-helper-for-woocommerce' ),
				),
				400
			);
		}

		$templates      = isset( $data['scheduleTemplates'] ) && is_array( $data['scheduleTemplates'] ) ? $data['scheduleTemplates'] : ( isset( $data['templates'] ) && is_array( $data['templates'] ) ? $data['templates'] : array() );
		$jobs           = isset( $data['scheduledJobs'] ) && is_array( $data['scheduledJobs'] ) ? $data['scheduledJobs'] : ( isset( $data['jobs'] ) && is_array( $data['jobs'] ) ? $data['jobs'] : array() );
		$imported_jobs  = 0;
		$imported_templates = 0;

		foreach ( $templates as $template ) {
			if ( ! is_array( $template ) ) {
				continue;
			}

			unset( $template['id'] );

			if ( RHWC_Generator::save_schedule_template( $template ) ) {
				++$imported_templates;
			}
		}

		foreach ( $jobs as $raw_job ) {
			$job = $this->create_imported_scheduled_job( $raw_job );

			if ( is_wp_error( $job ) ) {
				continue;
			}

			RHWC_Generator::save_scheduled_bulk_job( $job );

			if ( 'paused' !== $job['status'] ) {
				$scheduled = $this->schedule_job_event( $job, $job['timestamp'] );

				if ( is_wp_error( $scheduled ) || false === $scheduled ) {
					RHWC_Generator::clear_scheduled_bulk_job( $job['id'] );
					continue;
				}
			}

			$this->add_schedule_history_entry(
				$job,
				'imported',
				sprintf(
					/* translators: 1: recurrence label, 2: run time. */
					esc_html__( 'Imported a %1$s schedule that is set to run at %2$s.', 'review-helper-for-woocommerce' ),
					$this->get_schedule_recurrence_label( $job ),
					$job['runAt']
				),
				'success'
			);

			++$imported_jobs;
		}

		if ( 0 === $imported_jobs && 0 === $imported_templates ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'No schedules or templates could be imported from that JSON payload.', 'review-helper-for-woocommerce' ),
				),
				400
			);
		}

		wp_send_json_success(
			$this->get_schedule_response_data(
				sprintf(
					/* translators: 1: imported jobs count, 2: imported templates count. */
					esc_html__( 'Imported %1$d schedules and %2$d templates.', 'review-helper-for-woocommerce' ),
					$imported_jobs,
					$imported_templates
				)
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
