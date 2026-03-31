<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHWC_Generator {

	const GENERATED_REVIEW_META_KEY = '_rhwc_generated_review';
	const GENERATED_REVIEW_AGENT    = 'Review Helper for WooCommerce';
	const LAST_BULK_BATCH_OPTION    = 'rhwc_last_bulk_batch';
	const SCHEDULED_BULK_JOB_OPTION = 'rhwc_scheduled_bulk_job';
	const SCHEDULED_BULK_JOBS_OPTION = 'rhwc_scheduled_bulk_jobs';
	const SCHEDULE_HISTORY_OPTION    = 'rhwc_schedule_history';
	const SCHEDULE_TEMPLATES_OPTION  = 'rhwc_schedule_templates';
	const MAX_SCHEDULE_HISTORY       = 100;

	public static function is_generated_review( $comment ) {
		$comment = $comment instanceof WP_Comment ? $comment : get_comment( $comment );

		if ( ! $comment instanceof WP_Comment ) {
			return false;
		}

		$generated_marker = get_comment_meta( $comment->comment_ID, self::GENERATED_REVIEW_META_KEY, true );

		if ( 'yes' === $generated_marker ) {
			return true;
		}

		return self::GENERATED_REVIEW_AGENT === $comment->comment_agent;
	}

	public static function get_generation_behavior_settings() {
		return self::normalize_generation_args(
			array(
				'reviewStatus' => get_option( 'rhwc_review_status', 'approved' ),
				'dateFromDays' => get_option( 'rhwc_date_from_days', 0 ),
				'dateToDays'   => get_option( 'rhwc_date_to_days', 30 ),
			)
		);
	}

	public static function generate_reviews( $product_id, $count, $rating_range, $reviewer_names, $content_style = 'short', $return_details = false, $generation_args = array() ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'invalid_product', __( 'Invalid product ID.', 'review-helper-for-woocommerce' ) );
		}

		$generation_args = self::normalize_generation_args(
			wp_parse_args( $generation_args, self::get_generation_behavior_settings() )
		);
		$names_array = array_filter( array_map( 'trim', explode( "\n", $reviewer_names ) ) );
		if ( empty( $names_array ) ) {
			$names_array = self::get_default_name_pool();
		}

		list( $min_rating, $max_rating ) = self::parse_rating_range( $rating_range );

		$generated_count = 0;
		$product_title   = $product->get_name();
		$comment_ids     = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$name   = $names_array[ array_rand( $names_array ) ];
			$rating = wp_rand( $min_rating, $max_rating );
			$text   = self::generate_text( $product_title, $content_style );
			$comment_id = self::insert_review(
				$product_id,
				$name,
				$rating,
				$text,
				$generation_args['reviewStatus'],
				$generation_args['dateFromDays'],
				$generation_args['dateToDays']
			);

			if ( $comment_id && ! is_wp_error( $comment_id ) ) {
				$generated_count++;
				$comment_ids[] = (int) $comment_id;
			}
		}

		if ( $generated_count > 0 ) {
			// Update meta to track the total
			$total = get_option( 'rhwc_total_generated', 0 ) + $generated_count;
			update_option( 'rhwc_total_generated', $total );
			update_option( 'rhwc_last_generated_date', current_time( 'mysql' ) );

			// Mark product as having generated reviews to prevent bulk duplication
			update_post_meta( $product_id, '_rhwc_generated_reviews', 'yes' );

			// Update product average rating cache
			if ( class_exists( 'WC_Comments' ) ) {
				WC_Comments::clear_transients( $product_id );
			}
		}

		if ( $return_details ) {
			return array(
				'count'      => $generated_count,
				'commentIds' => $comment_ids,
				'productId'  => (int) $product_id,
			);
		}

		return $generated_count;
	}

	public static function get_last_bulk_batch() {
		return self::normalize_last_bulk_batch( get_option( self::LAST_BULK_BATCH_OPTION, array() ) );
	}

	public static function get_last_bulk_batch_summary() {
		$batch = self::get_last_bulk_batch();

		if ( empty( $batch ) ) {
			return null;
		}

		$schedule_time = isset( $template['scheduleTime'] ) ? sanitize_text_field( (string) $template['scheduleTime'] ) : '09:00';
		$schedule_time = '' !== $schedule_time ? $schedule_time : '09:00';

		return array(
			'id'                => $batch['id'],
			'createdAt'         => $batch['createdAt'],
			'generated'         => $batch['generated'],
			'processed'         => $batch['processed'],
			'skipped'           => $batch['skipped'],
			'target'            => $batch['target'],
			'targetLabel'       => $batch['targetLabel'],
			'requestedProducts' => $batch['requestedProducts'],
			'countPerProduct'   => $batch['countPerProduct'],
			'rating'            => $batch['rating'],
			'style'             => $batch['style'],
			'skipGenerated'     => $batch['skipGenerated'],
		);
	}

	public static function get_scheduled_bulk_jobs() {
		$jobs = get_option( self::SCHEDULED_BULK_JOBS_OPTION, null );

		if ( ! is_array( $jobs ) ) {
			$legacy_job = self::normalize_scheduled_bulk_job( get_option( self::SCHEDULED_BULK_JOB_OPTION, array() ) );

			if ( empty( $legacy_job ) ) {
				return array();
			}

			$jobs = array( $legacy_job );
		}

		$normalized_jobs = array();

		foreach ( $jobs as $job ) {
			$job = self::normalize_scheduled_bulk_job( $job );

			if ( empty( $job ) ) {
				continue;
			}

			$normalized_jobs[ $job['id'] ] = $job;
		}

		$normalized_jobs = array_values( $normalized_jobs );

		usort(
			$normalized_jobs,
			static function ( $left, $right ) {
				$left_status  = isset( $left['status'] ) ? $left['status'] : 'active';
				$right_status = isset( $right['status'] ) ? $right['status'] : 'active';

				if ( $left_status !== $right_status ) {
					return 'paused' === $left_status ? 1 : -1;
				}

				return (int) $left['timestamp'] <=> (int) $right['timestamp'];
			}
		);

		return $normalized_jobs;
	}

	public static function get_scheduled_bulk_jobs_summary() {
		$summaries = array();

		foreach ( self::get_scheduled_bulk_jobs() as $job ) {
			$summaries[] = self::build_scheduled_job_summary( $job );
		}

		return $summaries;
	}

	public static function get_scheduled_bulk_job( $job_id = '' ) {
		$jobs = self::get_scheduled_bulk_jobs();

		if ( empty( $job_id ) ) {
			return empty( $jobs ) ? null : $jobs[0];
		}

		foreach ( $jobs as $job ) {
			if ( $job['id'] === $job_id ) {
				return $job;
			}
		}

		return null;
	}

	public static function get_scheduled_bulk_job_summary( $job_id = '' ) {
		$job = self::get_scheduled_bulk_job( $job_id );

		if ( empty( $job ) ) {
			return null;
		}

		return self::build_scheduled_job_summary( $job );
	}

	public static function save_last_bulk_batch( $batch ) {
		$batch = self::normalize_last_bulk_batch( $batch );

		if ( empty( $batch ) ) {
			self::clear_last_bulk_batch();
			return false;
		}

		update_option( self::LAST_BULK_BATCH_OPTION, $batch );

		return true;
	}

	public static function clear_last_bulk_batch() {
		delete_option( self::LAST_BULK_BATCH_OPTION );
	}

	public static function save_scheduled_bulk_job( $job ) {
		$job = self::normalize_scheduled_bulk_job( $job );

		if ( empty( $job ) ) {
			self::clear_scheduled_bulk_job();
			return false;
		}

		$jobs              = self::get_scheduled_jobs_by_id();
		$jobs[ $job['id'] ] = $job;

		update_option( self::SCHEDULED_BULK_JOBS_OPTION, array_values( $jobs ) );
		delete_option( self::SCHEDULED_BULK_JOB_OPTION );

		return true;
	}

	public static function delete_scheduled_bulk_job( $job_id ) {
		$job_id = sanitize_text_field( (string) $job_id );

		if ( '' === $job_id ) {
			return self::clear_scheduled_bulk_jobs();
		}

		$jobs = self::get_scheduled_jobs_by_id();

		if ( ! isset( $jobs[ $job_id ] ) ) {
			return false;
		}

		unset( $jobs[ $job_id ] );

		if ( empty( $jobs ) ) {
			return self::clear_scheduled_bulk_jobs();
		}

		update_option( self::SCHEDULED_BULK_JOBS_OPTION, array_values( $jobs ) );
		delete_option( self::SCHEDULED_BULK_JOB_OPTION );

		return true;
	}

	public static function clear_scheduled_bulk_job( $job_id = '' ) {
		if ( ! empty( $job_id ) ) {
			return self::delete_scheduled_bulk_job( $job_id );
		}

		return self::clear_scheduled_bulk_jobs();
	}

	public static function clear_scheduled_bulk_jobs() {
		delete_option( self::SCHEDULED_BULK_JOBS_OPTION );
		delete_option( self::SCHEDULED_BULK_JOB_OPTION );

		return true;
	}

	public static function get_schedule_history() {
		$history = get_option( self::SCHEDULE_HISTORY_OPTION, array() );

		if ( ! is_array( $history ) ) {
			return array();
		}

		$entries = array();

		foreach ( $history as $entry ) {
			$entry = self::normalize_schedule_history_entry( $entry );

			if ( empty( $entry ) ) {
				continue;
			}

			$entries[] = $entry;
		}

		usort(
			$entries,
			static function ( $left, $right ) {
				return strcmp( (string) $right['createdAt'], (string) $left['createdAt'] );
			}
		);

		return $entries;
	}

	public static function get_schedule_history_summary( $limit = 15 ) {
		return array_slice( self::get_schedule_history(), 0, max( 1, absint( $limit ) ) );
	}

	public static function get_schedule_templates() {
		$templates = get_option( self::SCHEDULE_TEMPLATES_OPTION, array() );

		if ( ! is_array( $templates ) ) {
			return array();
		}

		$entries = array();

		foreach ( $templates as $template ) {
			$template = self::normalize_schedule_template( $template );

			if ( empty( $template ) ) {
				continue;
			}

			$entries[ $template['id'] ] = $template;
		}

		$entries = array_values( $entries );

		usort(
			$entries,
			static function ( $left, $right ) {
				return strcmp( (string) $left['name'], (string) $right['name'] );
			}
		);

		return $entries;
	}

	public static function get_schedule_templates_summary() {
		$summaries = array();

		foreach ( self::get_schedule_templates() as $template ) {
			$summaries[] = self::build_schedule_template_summary( $template );
		}

		return $summaries;
	}

	public static function save_schedule_template( $template ) {
		$template = self::normalize_schedule_template( $template );

		if ( empty( $template ) ) {
			return false;
		}

		$templates                   = self::get_schedule_templates_by_id();
		$templates[ $template['id'] ] = $template;

		update_option( self::SCHEDULE_TEMPLATES_OPTION, array_values( $templates ) );

		return true;
	}

	public static function delete_schedule_template( $template_id ) {
		$template_id = sanitize_text_field( (string) $template_id );

		if ( '' === $template_id ) {
			return self::clear_schedule_templates();
		}

		$templates = self::get_schedule_templates_by_id();

		if ( ! isset( $templates[ $template_id ] ) ) {
			return false;
		}

		unset( $templates[ $template_id ] );

		if ( empty( $templates ) ) {
			return self::clear_schedule_templates();
		}

		update_option( self::SCHEDULE_TEMPLATES_OPTION, array_values( $templates ) );

		return true;
	}

	public static function clear_schedule_templates() {
		delete_option( self::SCHEDULE_TEMPLATES_OPTION );

		return true;
	}

	public static function add_schedule_history_entry( $entry ) {
		$entry = self::normalize_schedule_history_entry( $entry );

		if ( empty( $entry ) ) {
			return false;
		}

		$history   = self::get_schedule_history();
		$history[] = $entry;

		usort(
			$history,
			static function ( $left, $right ) {
				return strcmp( (string) $right['createdAt'], (string) $left['createdAt'] );
			}
		);

		$history = array_slice( $history, 0, self::MAX_SCHEDULE_HISTORY );

		update_option( self::SCHEDULE_HISTORY_OPTION, $history );

		return true;
	}

	public static function sync_generated_reviews_flag( $product_id ) {
		$product_id = absint( $product_id );

		if ( ! $product_id ) {
			return false;
		}

		$comment_ids = get_comments(
			array(
				'post_id' => $product_id,
				'type'    => 'review',
				'status'  => 'all',
				'fields'  => 'ids',
			)
		);

		foreach ( $comment_ids as $comment_id ) {
			if ( self::is_generated_review( $comment_id ) ) {
				update_post_meta( $product_id, '_rhwc_generated_reviews', 'yes' );
				return true;
			}
		}

		delete_post_meta( $product_id, '_rhwc_generated_reviews' );

		return false;
	}

	private static function normalize_last_bulk_batch( $batch ) {
		if ( ! is_array( $batch ) ) {
			return null;
		}

		$comment_ids = isset( $batch['commentIds'] ) && is_array( $batch['commentIds'] )
			? array_values( array_filter( array_map( 'absint', $batch['commentIds'] ) ) )
			: array();

		$product_ids = isset( $batch['productIds'] ) && is_array( $batch['productIds'] )
			? array_values( array_filter( array_map( 'absint', $batch['productIds'] ) ) )
			: array();

		if ( empty( $comment_ids ) ) {
			return null;
		}

		$rating = isset( $batch['rating'] ) ? sanitize_text_field( (string) $batch['rating'] ) : '4-5';
		if ( ! preg_match( '/^\d(?:-\d)?$/', $rating ) ) {
			$rating = '4-5';
		}

		$style = isset( $batch['style'] ) ? sanitize_key( (string) $batch['style'] ) : 'short';
		if ( ! in_array( $style, array( 'short', 'medium', 'long' ), true ) ) {
			$style = 'short';
		}

		$target = isset( $batch['target'] ) && 'specific' === $batch['target'] ? 'specific' : 'all';

		return array(
			'id'                => isset( $batch['id'] ) ? sanitize_text_field( (string) $batch['id'] ) : wp_generate_uuid4(),
			'createdAt'         => isset( $batch['createdAt'] ) ? sanitize_text_field( (string) $batch['createdAt'] ) : current_time( 'mysql' ),
			'generated'         => isset( $batch['generated'] ) ? absint( $batch['generated'] ) : count( $comment_ids ),
			'processed'         => isset( $batch['processed'] ) ? absint( $batch['processed'] ) : count( $product_ids ),
			'skipped'           => isset( $batch['skipped'] ) ? absint( $batch['skipped'] ) : 0,
			'target'            => $target,
			'targetLabel'       => isset( $batch['targetLabel'] ) ? sanitize_text_field( (string) $batch['targetLabel'] ) : '',
			'requestedProducts' => isset( $batch['requestedProducts'] ) ? absint( $batch['requestedProducts'] ) : count( $product_ids ),
			'countPerProduct'   => isset( $batch['countPerProduct'] ) ? absint( $batch['countPerProduct'] ) : 0,
			'rating'            => $rating,
			'style'             => $style,
			'skipGenerated'     => ! empty( $batch['skipGenerated'] ) && 'no' === $batch['skipGenerated'] ? 'no' : 'yes',
			'productIds'        => $product_ids,
			'commentIds'        => $comment_ids,
		);
	}

	private static function normalize_scheduled_bulk_job( $job ) {
		if ( ! is_array( $job ) ) {
			return null;
		}

		$product_ids_input = isset( $job['productIdsInput'] ) ? sanitize_text_field( (string) $job['productIdsInput'] ) : '';
		$product_ids_input = 'all' === sanitize_key( $product_ids_input ) ? 'all' : $product_ids_input;
		$count             = isset( $job['countPerProduct'] ) ? absint( $job['countPerProduct'] ) : 0;
		$timestamp         = isset( $job['timestamp'] ) ? (int) $job['timestamp'] : 0;
		$delay_min_minutes = isset( $job['delayMinMinutes'] ) ? absint( $job['delayMinMinutes'] ) : ( isset( $job['intervalMinutes'] ) ? absint( $job['intervalMinutes'] ) : 30 );
		$delay_max_minutes = isset( $job['delayMaxMinutes'] ) ? absint( $job['delayMaxMinutes'] ) : ( isset( $job['intervalMinutes'] ) ? absint( $job['intervalMinutes'] ) : 30 );
		$review_queue      = array();
		$planned_reviews   = 0;

		if ( empty( $product_ids_input ) || $count < 1 || $timestamp < 1 || $delay_min_minutes < 1 || $delay_max_minutes < 1 ) {
			return null;
		}

		if ( $delay_min_minutes > $delay_max_minutes ) {
			$temp              = $delay_min_minutes;
			$delay_min_minutes = $delay_max_minutes;
			$delay_max_minutes = $temp;
		}

		$rating = isset( $job['rating'] ) ? sanitize_text_field( (string) $job['rating'] ) : '4-5';
		if ( ! preg_match( '/^\d(?:-\d)?$/', $rating ) ) {
			$rating = '4-5';
		}

		$style = isset( $job['style'] ) ? sanitize_key( (string) $job['style'] ) : 'short';
		if ( ! in_array( $style, array( 'short', 'medium', 'long' ), true ) ) {
			$style = 'short';
		}

		$review_status = isset( $job['reviewStatus'] ) ? sanitize_key( (string) $job['reviewStatus'] ) : 'approved';
		if ( ! in_array( $review_status, array( 'approved', 'pending' ), true ) ) {
			$review_status = 'approved';
		}

		$date_from_days = isset( $job['dateFromDays'] ) ? min( 3650, absint( $job['dateFromDays'] ) ) : 0;
		$date_to_days   = isset( $job['dateToDays'] ) ? min( 3650, absint( $job['dateToDays'] ) ) : 30;

		if ( $date_from_days > $date_to_days ) {
			$temp           = $date_from_days;
			$date_from_days = $date_to_days;
			$date_to_days   = $temp;
		}

		$target = isset( $job['target'] ) && 'specific' === $job['target'] ? 'specific' : 'all';
		$status = isset( $job['status'] ) ? sanitize_key( (string) $job['status'] ) : 'active';
		if ( ! in_array( $status, array( 'active', 'paused' ), true ) ) {
			$status = 'active';
		}

		$recurrence_type = isset( $job['recurrenceType'] ) ? sanitize_key( (string) $job['recurrenceType'] ) : 'once';
		if ( ! in_array( $recurrence_type, array( 'once', 'daily', 'weekly', 'custom' ), true ) ) {
			$recurrence_type = 'once';
		}

		$custom_days = self::normalize_schedule_days( isset( $job['customDays'] ) ? $job['customDays'] : array() );
		$generated_comment_ids = isset( $job['generatedCommentIds'] ) && is_array( $job['generatedCommentIds'] )
			? array_values( array_unique( array_filter( array_map( 'absint', $job['generatedCommentIds'] ) ) ) )
			: array();
		$generated_product_ids = isset( $job['generatedProductIds'] ) && is_array( $job['generatedProductIds'] )
			? array_values( array_unique( array_filter( array_map( 'absint', $job['generatedProductIds'] ) ) ) )
			: array();

		if ( isset( $job['reviewQueue'] ) && is_array( $job['reviewQueue'] ) ) {
			foreach ( $job['reviewQueue'] as $queue_item ) {
				if ( ! is_array( $queue_item ) ) {
					continue;
				}

				$product_id = isset( $queue_item['productId'] ) ? absint( $queue_item['productId'] ) : 0;
				$remaining  = isset( $queue_item['remaining'] ) ? absint( $queue_item['remaining'] ) : 0;

				if ( ! $product_id || ! $remaining ) {
					continue;
				}

				$review_queue[]   = array(
					'productId' => $product_id,
					'remaining' => $remaining,
				);
				$planned_reviews += $remaining;
			}
		}

		if ( 'custom' === $recurrence_type && empty( $custom_days ) ) {
			return null;
		}

		if ( empty( $review_queue ) && empty( $generated_comment_ids ) && 'once' === $recurrence_type && 'active' === $status ) {
			return null;
		}

		if ( ! $planned_reviews ) {
			$planned_reviews = isset( $job['plannedReviews'] ) ? absint( $job['plannedReviews'] ) : count( $generated_comment_ids );
		}

		return array(
			'id'                 => isset( $job['id'] ) ? sanitize_text_field( (string) $job['id'] ) : wp_generate_uuid4(),
			'batchId'            => isset( $job['batchId'] ) ? sanitize_text_field( (string) $job['batchId'] ) : wp_generate_uuid4(),
			'createdAt'          => isset( $job['createdAt'] ) ? sanitize_text_field( (string) $job['createdAt'] ) : current_time( 'mysql' ),
			'runAt'              => isset( $job['runAt'] ) ? sanitize_text_field( (string) $job['runAt'] ) : get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $timestamp ) ),
			'timestamp'          => $timestamp,
			'status'             => $status,
			'recurrenceType'     => $recurrence_type,
			'customDays'         => $custom_days,
			'scheduleDate'       => isset( $job['scheduleDate'] ) ? sanitize_text_field( (string) $job['scheduleDate'] ) : gmdate( 'Y-m-d', $timestamp ),
			'scheduleTime'       => isset( $job['scheduleTime'] ) ? sanitize_text_field( (string) $job['scheduleTime'] ) : gmdate( 'H:i', $timestamp ),
			'scheduleWeekday'    => isset( $job['scheduleWeekday'] ) ? min( 6, absint( $job['scheduleWeekday'] ) ) : (int) gmdate( 'w', $timestamp ),
			'target'             => $target,
			'targetLabel'        => isset( $job['targetLabel'] ) ? sanitize_text_field( (string) $job['targetLabel'] ) : ( 'specific' === $target ? __( 'Specific products', 'review-helper-for-woocommerce' ) : __( 'All published products', 'review-helper-for-woocommerce' ) ),
			'requestedProducts'  => isset( $job['requestedProducts'] ) ? absint( $job['requestedProducts'] ) : 0,
			'countPerProduct'    => $count,
			'rating'             => $rating,
			'style'              => $style,
			'names'              => isset( $job['names'] ) ? sanitize_textarea_field( (string) $job['names'] ) : '',
			'skipGenerated'      => ! empty( $job['skipGenerated'] ) && 'no' === $job['skipGenerated'] ? 'no' : 'yes',
			'reviewStatus'       => $review_status,
			'dateFromDays'       => $date_from_days,
			'dateToDays'         => $date_to_days,
			'excludedProducts'   => array_values( array_unique( array_filter( array_map( 'absint', isset( $job['excludedProducts'] ) && is_array( $job['excludedProducts'] ) ? $job['excludedProducts'] : array() ) ) ) ),
			'excludedCategories' => array_values( array_unique( array_filter( array_map( 'absint', isset( $job['excludedCategories'] ) && is_array( $job['excludedCategories'] ) ? $job['excludedCategories'] : array() ) ) ) ),
			'excludedCount'      => isset( $job['excludedCount'] ) ? absint( $job['excludedCount'] ) : 0,
			'skippedCount'       => isset( $job['skippedCount'] ) ? absint( $job['skippedCount'] ) : 0,
			'delayMinMinutes'    => min( 10080, $delay_min_minutes ),
			'delayMaxMinutes'    => min( 10080, $delay_max_minutes ),
			'intervalMinutes'    => min( 10080, $delay_min_minutes ),
			'plannedReviews'     => $planned_reviews,
			'remainingReviews'   => isset( $job['remainingReviews'] ) ? absint( $job['remainingReviews'] ) : $planned_reviews,
			'cycleNumber'        => isset( $job['cycleNumber'] ) ? max( 1, absint( $job['cycleNumber'] ) ) : 1,
			'lastRunAt'          => isset( $job['lastRunAt'] ) ? sanitize_text_field( (string) $job['lastRunAt'] ) : '',
			'generatedCommentIds' => $generated_comment_ids,
			'generatedProductIds' => $generated_product_ids,
			'reviewQueue'        => $review_queue,
			'productIdsInput'    => $product_ids_input,
		);
	}

	private static function get_scheduled_jobs_by_id() {
		$jobs = array();

		foreach ( self::get_scheduled_bulk_jobs() as $job ) {
			$jobs[ $job['id'] ] = $job;
		}

		return $jobs;
	}

	private static function get_schedule_templates_by_id() {
		$templates = array();

		foreach ( self::get_schedule_templates() as $template ) {
			$templates[ $template['id'] ] = $template;
		}

		return $templates;
	}

	private static function build_scheduled_job_summary( $job ) {
		$scheduled_products = isset( $job['reviewQueue'] ) && is_array( $job['reviewQueue'] )
			? count( $job['reviewQueue'] )
			: 0;
		$generated_reviews  = isset( $job['generatedCommentIds'] ) && is_array( $job['generatedCommentIds'] )
			? count( $job['generatedCommentIds'] )
			: 0;

		return array(
			'id'                  => $job['id'],
			'batchId'             => $job['batchId'],
			'createdAt'           => $job['createdAt'],
			'runAt'               => $job['runAt'],
			'timestamp'           => $job['timestamp'],
			'status'              => $job['status'],
			'recurrenceType'      => $job['recurrenceType'],
			'customDays'          => $job['customDays'],
			'scheduleDate'        => $job['scheduleDate'],
			'scheduleTime'        => $job['scheduleTime'],
			'scheduleWeekday'     => $job['scheduleWeekday'],
			'target'              => $job['target'],
			'targetLabel'         => $job['targetLabel'],
			'requestedProducts'   => $job['requestedProducts'],
			'scheduledProducts'   => $scheduled_products,
			'countPerProduct'     => $job['countPerProduct'],
			'rating'              => $job['rating'],
			'style'               => $job['style'],
			'names'               => $job['names'],
			'skipGenerated'       => $job['skipGenerated'],
			'reviewStatus'        => $job['reviewStatus'],
			'dateFromDays'        => $job['dateFromDays'],
			'dateToDays'          => $job['dateToDays'],
			'excludedProducts'    => $job['excludedProducts'],
			'excludedCategories'  => $job['excludedCategories'],
			'excludedCount'       => $job['excludedCount'],
			'skippedCount'        => $job['skippedCount'],
			'delayMinMinutes'     => $job['delayMinMinutes'],
			'delayMaxMinutes'     => $job['delayMaxMinutes'],
			'intervalMinutes'     => $job['intervalMinutes'],
			'plannedReviews'      => $job['plannedReviews'],
			'remainingReviews'    => $job['remainingReviews'],
			'generatedReviews'    => $generated_reviews,
			'reviewerCount'       => count( array_filter( array_map( 'trim', explode( "\n", $job['names'] ) ) ) ),
			'cycleNumber'         => $job['cycleNumber'],
			'lastRunAt'           => $job['lastRunAt'],
			'productIdsInput'     => $job['productIdsInput'],
			'generatedCommentIds' => $job['generatedCommentIds'],
			'generatedProductIds' => $job['generatedProductIds'],
			'reviewQueue'         => $job['reviewQueue'],
		);
	}

	private static function normalize_schedule_template( $template ) {
		if ( ! is_array( $template ) ) {
			return null;
		}

		$name              = isset( $template['name'] ) ? sanitize_text_field( (string) $template['name'] ) : '';
		$product_ids_input = isset( $template['productIdsInput'] ) ? sanitize_text_field( (string) $template['productIdsInput'] ) : '';
		$product_ids_input = 'all' === sanitize_key( $product_ids_input ) ? 'all' : $product_ids_input;
		$count             = isset( $template['countPerProduct'] ) ? absint( $template['countPerProduct'] ) : 0;
		$delay_min_minutes = isset( $template['delayMinMinutes'] ) ? absint( $template['delayMinMinutes'] ) : 30;
		$delay_max_minutes = isset( $template['delayMaxMinutes'] ) ? absint( $template['delayMaxMinutes'] ) : $delay_min_minutes;

		if ( '' === $name || '' === $product_ids_input || $count < 1 || $delay_min_minutes < 1 || $delay_max_minutes < 1 ) {
			return null;
		}

		if ( $delay_min_minutes > $delay_max_minutes ) {
			$temp              = $delay_min_minutes;
			$delay_min_minutes = $delay_max_minutes;
			$delay_max_minutes = $temp;
		}

		$rating = isset( $template['rating'] ) ? sanitize_text_field( (string) $template['rating'] ) : '4-5';
		if ( ! preg_match( '/^\d(?:-\d)?$/', $rating ) ) {
			$rating = '4-5';
		}

		$style = isset( $template['style'] ) ? sanitize_key( (string) $template['style'] ) : 'short';
		if ( ! in_array( $style, array( 'short', 'medium', 'long' ), true ) ) {
			$style = 'short';
		}

		$review_status = isset( $template['reviewStatus'] ) ? sanitize_key( (string) $template['reviewStatus'] ) : 'approved';
		if ( ! in_array( $review_status, array( 'approved', 'pending' ), true ) ) {
			$review_status = 'approved';
		}

		$recurrence_type = isset( $template['recurrenceType'] ) ? sanitize_key( (string) $template['recurrenceType'] ) : 'once';
		if ( ! in_array( $recurrence_type, array( 'once', 'daily', 'weekly', 'custom' ), true ) ) {
			$recurrence_type = 'once';
		}

		$custom_days = self::normalize_schedule_days( isset( $template['customDays'] ) ? $template['customDays'] : array() );

		if ( 'custom' === $recurrence_type && empty( $custom_days ) ) {
			return null;
		}

		$date_from_days = isset( $template['dateFromDays'] ) ? min( 3650, absint( $template['dateFromDays'] ) ) : 0;
		$date_to_days   = isset( $template['dateToDays'] ) ? min( 3650, absint( $template['dateToDays'] ) ) : 30;
		$schedule_date  = isset( $template['scheduleDate'] ) ? sanitize_text_field( (string) $template['scheduleDate'] ) : '';
		$schedule_time  = isset( $template['scheduleTime'] ) && ! empty( $template['scheduleTime'] )
			? sanitize_text_field( (string) $template['scheduleTime'] )
			: '09:00';

		if ( $date_from_days > $date_to_days ) {
			$temp           = $date_from_days;
			$date_from_days = $date_to_days;
			$date_to_days   = $temp;
		}

		return array(
			'id'                 => isset( $template['id'] ) ? sanitize_text_field( (string) $template['id'] ) : wp_generate_uuid4(),
			'name'               => $name,
			'createdAt'          => isset( $template['createdAt'] ) ? sanitize_text_field( (string) $template['createdAt'] ) : current_time( 'mysql' ),
			'target'             => isset( $template['target'] ) && 'specific' === $template['target'] ? 'specific' : 'all',
			'productIdsInput'    => $product_ids_input,
			'countPerProduct'    => $count,
			'rating'             => $rating,
			'style'              => $style,
			'names'              => isset( $template['names'] ) ? sanitize_textarea_field( (string) $template['names'] ) : '',
			'skipGenerated'      => ! empty( $template['skipGenerated'] ) && 'no' === $template['skipGenerated'] ? 'no' : 'yes',
			'reviewStatus'       => $review_status,
			'dateFromDays'       => $date_from_days,
			'dateToDays'         => $date_to_days,
			'excludedProducts'   => array_values( array_unique( array_filter( array_map( 'absint', isset( $template['excludedProducts'] ) && is_array( $template['excludedProducts'] ) ? $template['excludedProducts'] : array() ) ) ) ),
			'excludedCategories' => array_values( array_unique( array_filter( array_map( 'absint', isset( $template['excludedCategories'] ) && is_array( $template['excludedCategories'] ) ? $template['excludedCategories'] : array() ) ) ) ),
			'recurrenceType'     => $recurrence_type,
			'customDays'         => $custom_days,
			'scheduleDate'       => $schedule_date,
			'scheduleTime'       => $schedule_time,
			'scheduleWeekday'    => isset( $template['scheduleWeekday'] ) ? min( 6, absint( $template['scheduleWeekday'] ) ) : 0,
			'delayMinMinutes'    => min( 10080, $delay_min_minutes ),
			'delayMaxMinutes'    => min( 10080, $delay_max_minutes ),
		);
	}

	private static function build_schedule_template_summary( $template ) {
		return array(
			'id'                 => $template['id'],
			'name'               => $template['name'],
			'createdAt'          => $template['createdAt'],
			'target'             => $template['target'],
			'productIdsInput'    => $template['productIdsInput'],
			'countPerProduct'    => $template['countPerProduct'],
			'rating'             => $template['rating'],
			'style'              => $template['style'],
			'names'              => $template['names'],
			'reviewerCount'      => count( array_filter( array_map( 'trim', explode( "\n", $template['names'] ) ) ) ),
			'skipGenerated'      => $template['skipGenerated'],
			'reviewStatus'       => $template['reviewStatus'],
			'dateFromDays'       => $template['dateFromDays'],
			'dateToDays'         => $template['dateToDays'],
			'excludedProducts'   => $template['excludedProducts'],
			'excludedCategories' => $template['excludedCategories'],
			'recurrenceType'     => $template['recurrenceType'],
			'customDays'         => $template['customDays'],
			'scheduleDate'       => $template['scheduleDate'],
			'scheduleTime'       => $template['scheduleTime'],
			'scheduleWeekday'    => $template['scheduleWeekday'],
			'delayMinMinutes'    => $template['delayMinMinutes'],
			'delayMaxMinutes'    => $template['delayMaxMinutes'],
		);
	}

	private static function normalize_schedule_days( $days ) {
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

	private static function normalize_schedule_history_entry( $entry ) {
		if ( ! is_array( $entry ) ) {
			return null;
		}

		$message = isset( $entry['message'] ) ? sanitize_text_field( (string) $entry['message'] ) : '';

		if ( '' === $message ) {
			return null;
		}

		return array(
			'id'          => isset( $entry['id'] ) ? sanitize_text_field( (string) $entry['id'] ) : wp_generate_uuid4(),
			'jobId'       => isset( $entry['jobId'] ) ? sanitize_text_field( (string) $entry['jobId'] ) : '',
			'jobLabel'    => isset( $entry['jobLabel'] ) ? sanitize_text_field( (string) $entry['jobLabel'] ) : '',
			'event'       => isset( $entry['event'] ) ? sanitize_key( (string) $entry['event'] ) : 'info',
			'status'      => isset( $entry['status'] ) ? sanitize_key( (string) $entry['status'] ) : 'info',
			'createdAt'   => isset( $entry['createdAt'] ) ? sanitize_text_field( (string) $entry['createdAt'] ) : current_time( 'mysql' ),
			'createdTimestamp' => isset( $entry['createdTimestamp'] ) ? (int) $entry['createdTimestamp'] : time(),
			'message'     => $message,
			'cycleNumber' => isset( $entry['cycleNumber'] ) ? absint( $entry['cycleNumber'] ) : 0,
		);
	}

	private static function normalize_generation_args( $generation_args ) {
		$review_status = isset( $generation_args['reviewStatus'] ) ? sanitize_key( (string) $generation_args['reviewStatus'] ) : 'approved';
		$date_from_days = isset( $generation_args['dateFromDays'] ) ? absint( $generation_args['dateFromDays'] ) : 0;
		$date_to_days   = isset( $generation_args['dateToDays'] ) ? absint( $generation_args['dateToDays'] ) : 30;

		if ( ! in_array( $review_status, array( 'approved', 'pending' ), true ) ) {
			$review_status = 'approved';
		}

		if ( $date_from_days > $date_to_days ) {
			$temp           = $date_from_days;
			$date_from_days = $date_to_days;
			$date_to_days   = $temp;
		}

		return array(
			'reviewStatus' => $review_status,
			'dateFromDays' => min( 3650, $date_from_days ),
			'dateToDays'   => min( 3650, $date_to_days ),
		);
	}

	private static function get_default_name_pool() {
		$default_names = class_exists( 'RHWC_Admin' )
			? RHWC_Admin::get_default_names()
			: "John Doe\nJane Smith\nMichael Johnson\nEmily Davis\nChris Brown\nSarah Miller\nDavid Wilson\nLaura Taylor\nJames Moore\nLinda Thomas\nRobert Anderson\nMary Thomas\nWilliam Jackson\nPatricia White\nRichard Harris\nJennifer Martin\nCharles Thompson\nElizabeth Garcia\nJoseph Martinez\nSusan Robinson";
		$names_array   = array_filter( array_map( 'trim', explode( "\n", $default_names ) ) );

		return ! empty( $names_array ) ? $names_array : array( 'Anonymous' );
	}

	private static function insert_review( $product_id, $author, $rating, $content, $review_status = 'approved', $date_from_days = 0, $date_to_days = 30 ) {

		// Randomize the review date inside the configured date window (uses UTC-safe time()).
		$random_days    = wp_rand( absint( $date_from_days ), absint( $date_to_days ) );
		$random_seconds = wp_rand( 0, 86400 );
		$timestamp      = time() - ( $random_days * 86400 ) - $random_seconds;
		$gmt_time       = gmdate( 'Y-m-d H:i:s', $timestamp );
		$local_time     = get_date_from_gmt( $gmt_time );

		$data = array(
			'comment_post_ID'      => $product_id,
			'comment_author'       => $author,
			'comment_author_email' => sanitize_title( $author ) . wp_rand( 10, 999 ) . '@example.com',
			'comment_author_url'   => '',
			'comment_content'      => $content,
			'comment_type'         => 'review',
			'comment_parent'       => 0,
			'user_id'              => 0,
			'comment_author_IP'    => '127.0.0.1',
			'comment_agent'        => self::GENERATED_REVIEW_AGENT,
			'comment_date'         => $local_time,
			'comment_date_gmt'     => $gmt_time,
			'comment_approved'     => 'pending' === $review_status ? 0 : 1,
		);

		$comment_id = wp_insert_comment( $data );

		if ( $comment_id && ! is_wp_error( $comment_id ) ) {
			update_comment_meta( $comment_id, 'rating', $rating );
			update_comment_meta( $comment_id, self::GENERATED_REVIEW_META_KEY, 'yes' );
		}

		return $comment_id;
	}

	private static function parse_rating_range( $range ) {
		if ( strpos( $range, '-' ) !== false ) {
			$parts = explode( '-', $range );
			$min   = max( 1, min( 5, intval( trim( $parts[0] ) ) ) );
			$max   = max( 1, min( 5, intval( trim( $parts[1] ) ) ) );
			if ( $min > $max ) {
				$temp = $min;
				$min  = $max;
				$max  = $temp;
			}
			return array( $min, $max );
		}

		$val = max( 1, min( 5, intval( trim( $range ) ) ) );
		return array( $val, $val );
	}

	private static function generate_text( $product_title, $style ) {
		$custom_comments = get_option( 'rhwc_custom_comments', '' );
		$custom_array    = array_filter( array_map( 'trim', explode( "\n", $custom_comments ) ) );

		if ( ! empty( $custom_array ) ) {
			// Use customized comments
			$text = $custom_array[ array_rand( $custom_array ) ];
			$text = str_replace( array( '{product_name}', '<product>' ), $product_title, $text );
			return sanitize_textarea_field( $text );
		}

		// Fallback to builtin templates if custom comments are empty
		$short_phrases = array(
			"Good quality.",
			"Value for money.",
			"Highly recommended.",
			"Works as expected.",
			"Very satisfied.",
			"Great product.",
			"Exactly what I needed.",
			"Fast delivery and good item.",
			"Nice packaging and product.",
			"I love this."
		);

		$medium_phrases = array(
			"The %s is a really good product. I am very happy with my purchase.",
			"I bought the %s recently and it lived up to my expectations. Highly recommended.",
			"Good value for money. The %s works exactly as described in the description.",
			"Very satisfied with the %s. The quality is great and the price is fair.",
			"I've been looking for something like this. The %s is fantastic and I use it all the time.",
			"Overall a great experience. The %s arrived quickly and it's very nicely made.",
			"Perfect! The %s is exactly what I was hoping for. The build quality is solid.",
			"I highly recommend the %s. It's affordable, reliable, and looks good too.",
			"Five stars for the %s. I will definitely consider buying more in the future.",
			"I'm quite impressed with the %s. It exceeded my expectations."
		);

		$long_phrases = array(
			"I was initially hesitant to buy the %s, but I am so glad I did! The quality is outstanding, and it feels much more premium than the price suggests. I'll be recommending this to my friends.",
			"After using the %s for a few days, I can honestly say it's one of the best purchases I've made recently. The attention to detail is remarkable. You really get great value for money here.",
			"If you are on the fence about the %s, just get it. It arrived perfectly packaged, and the performance is just flawless. I couldn't be happier with this product.",
			"I've tried similar items before, but the %s stands out. It's incredibly durable and works flawlessly. Customer service was also very helpful regarding my shipping queries.",
			"The %s is simply brilliant. From the smooth finish to the reliable functionality, it checks all the boxes for me. Everyone in my family wants one now!"
		);

		$intro = array(
			"Wow!", "Honestly,", "To be honest,", "I must say,", "Unbelievable.", "Wow, just wow."
		);

		$text = '';

		if ( 'short' === $style ) {
			$text = $short_phrases[ array_rand( $short_phrases ) ];
		} elseif ( 'medium' === $style ) {
			$template = $medium_phrases[ array_rand( $medium_phrases ) ];
			$text     = sprintf( $template, $product_title );
		} else {
			$template = $long_phrases[ array_rand( $long_phrases ) ];
			$prefix   = ( wp_rand( 1, 10 ) > 5 ) ? $intro[ array_rand( $intro ) ] . ' ' : '';
			$text     = $prefix . sprintf( $template, $product_title );
		}

		return sanitize_textarea_field( $text );
	}
}
