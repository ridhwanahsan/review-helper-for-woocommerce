/* global jQuery */

jQuery( document ).ready( ( $ ) => {
	const rhwcData = window[ 'rhwc_data' ]; // eslint-disable-line dot-notation
	const $button = $( '#rhwc-generate-btn' );

	if ( ! $button.length ) {
		return;
	}

	$button.on( 'click', ( event ) => {
		event.preventDefault();

		const $spinner = $( '#rhwc-single-spinner' );
		const $message = $( '#rhwc-single-message' );
		const productId = $button.data( 'product-id' );
		const count = $( '#rhwc_count' ).val();
		const rating = $( '#rhwc_rating' ).val();
		const names = $( '#rhwc_names' ).val();
		const style = $( '#rhwc_style' ).val();

		$button.prop( 'disabled', true );
		$spinner.addClass( 'is-active' );
		$message.empty();

		$.ajax( {
			url: rhwcData.ajax_url,
			type: 'POST',
			data: {
				action: 'rhwc_generate_single',
				security: rhwcData.nonce,
				product_id: productId,
				count,
				rating,
				names,
				style,
			},
			success( response ) {
				$spinner.removeClass( 'is-active' );
				$button.prop( 'disabled', false );

				if ( response.success ) {
					$message.html(
						`<div class="notice notice-success inline"><p>${ response.data.message }</p></div>`
					);
					return;
				}

				$message.html(
					`<div class="notice notice-error inline"><p>${ response.data.message }</p></div>`
				);
			},
			error() {
				$spinner.removeClass( 'is-active' );
				$button.prop( 'disabled', false );
				$message.html(
					'<div class="notice notice-error inline"><p>An error occurred.</p></div>'
				);
			},
		} );
	} );
} );
