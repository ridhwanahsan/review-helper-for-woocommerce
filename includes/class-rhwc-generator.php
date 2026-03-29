<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHWC_Generator {

	public static function generate_reviews( $product_id, $count, $rating_range, $reviewer_names, $content_style = 'short' ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'invalid_product', __( 'Invalid product ID.', 'rhwc' ) );
		}

		$names_array = array_filter( array_map( 'trim', explode( "\n", $reviewer_names ) ) );
		if ( empty( $names_array ) ) {
            $names_array = array( 'Anonymous' );
        }

		list( $min_rating, $max_rating ) = self::parse_rating_range( $rating_range );

		$generated_count = 0;
		$product_title   = $product->get_name();

		for ( $i = 0; $i < $count; $i++ ) {
			$name   = $names_array[ array_rand( $names_array ) ];
			$rating = mt_rand( $min_rating, $max_rating );
			$text   = self::generate_text( $product_title, $content_style );

			$comment_id = self::insert_review( $product_id, $name, $rating, $text );

			if ( $comment_id && ! is_wp_error( $comment_id ) ) {
				$generated_count++;
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
            	WC_Comments::get_average_rating_for_product( $product );
			}
		}

		return $generated_count;
	}

	private static function insert_review( $product_id, $author, $rating, $content ) {

		// Randomize date within last 30 days (uses UTC-safe time())
		$random_days    = mt_rand( 0, 30 );
		$random_seconds = mt_rand( 0, 86400 );
		$timestamp      = time() - ( $random_days * 86400 ) - $random_seconds;
		$gmt_time       = gmdate( 'Y-m-d H:i:s', $timestamp );
		$local_time     = get_date_from_gmt( $gmt_time );

		$data = array(
			'comment_post_ID'      => $product_id,
			'comment_author'       => $author,
			'comment_author_email' => sanitize_title( $author ) . mt_rand( 10, 999 ) . '@example.com',
			'comment_author_url'   => '',
			'comment_content'      => $content,
			'comment_type'         => 'review',
			'comment_parent'       => 0,
			'user_id'              => 0,
			'comment_author_IP'    => '127.0.0.1',
			'comment_agent'        => 'Review Helper for WooCommerce',
			'comment_date'         => $local_time,
			'comment_date_gmt'     => $gmt_time,
			'comment_approved'     => 1,
		);

		$comment_id = wp_insert_comment( $data );

		if ( $comment_id && ! is_wp_error( $comment_id ) ) {
			update_comment_meta( $comment_id, 'rating', $rating );
		}

		return $comment_id;
	}

	private static function parse_rating_range( $range ) {
		if ( strpos( $range, '-' ) !== false ) {
			$parts = explode( '-', $range );
			$min = max( 1, min( 5, intval( trim( $parts[0] ) ) ) );
			$max = max( 1, min( 5, intval( trim( $parts[1] ) ) ) );
			if ( $min > $max ) {
				$temp = $min;
				$min = $max;
				$max = $temp;
			}
			return array( $min, $max );
		}
		
		$val = max( 1, min( 5, intval( trim( $range ) ) ) );
		return array( $val, $val );
	}

	private static function generate_text( $product_title, $style ) {
		$custom_comments = get_option( 'rhwc_custom_comments', '' );
		$custom_array = array_filter( array_map( 'trim', explode( "\n", $custom_comments ) ) );

		if ( ! empty( $custom_array ) ) {
			// Use customized comments
			$text = $custom_array[ array_rand( $custom_array ) ];
			$text = str_replace( '{product_name}', $product_title, $text );
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
			$text = sprintf( $template, $product_title );
		} else {
			$template = $long_phrases[ array_rand( $long_phrases ) ];
            $prefix = ( mt_rand(1, 10) > 5 ) ? $intro[ array_rand( $intro ) ] . ' ' : '';
			$text = $prefix . sprintf( $template, $product_title );
		}

		return sanitize_textarea_field( $text );
	}
}
