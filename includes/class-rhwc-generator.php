<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHWC_Generator {

	const GENERATED_REVIEW_META_KEY = '_rhwc_generated_review';
	const GENERATED_REVIEW_AGENT    = 'Review Helper for WooCommerce';
	const LAST_BULK_BATCH_OPTION    = 'rhwc_last_bulk_batch';

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
			$names_array = array( 'Anonymous' );
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
