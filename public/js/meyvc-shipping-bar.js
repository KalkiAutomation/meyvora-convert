/**
 * CRO Shipping Bar – progress bar and message, updates on cart events
 *
 * @package Meyvora_Convert
 */
(function($) {
	'use strict';

	if ( typeof meyvcShippingBar === 'undefined' ) {
		return;
	}

	var settings = meyvcShippingBar.settings;
	var threshold = parseFloat( meyvcShippingBar.threshold, 10 );
	var hasTrackedProgress = false;

	/**
	 * Update bar message and progress from cart total.
	 *
	 * @param {number} cartTotal Current cart subtotal.
	 */
	function updateBar( cartTotal ) {
		var $bar = $( '.meyvc-shipping-bar' );
		if ( ! $bar.length ) {
			return;
		}

		var remaining = Math.max( 0, threshold - cartTotal );
		var progress = threshold > 0 ? Math.min( 100, ( cartTotal / threshold ) * 100 ) : 0;
		var achieved = remaining <= 0;

		var message;
		if ( achieved ) {
			message = settings && settings.message_achieved ? settings.message_achieved : '';
			$bar.addClass( 'meyvc-shipping-achieved' );
		} else {
			message = settings && settings.message_progress
				? settings.message_progress.replace( '{amount}', ( meyvcShippingBar.currency || '' ) + remaining.toFixed( 2 ) )
				: '';
			$bar.removeClass( 'meyvc-shipping-achieved' );
		}

		$bar.find( '.meyvc-shipping-bar-message' ).html( message );

		var $fill = $bar.find( '.meyvc-shipping-bar-fill' );
		if ( achieved ) {
			$fill.parent().hide();
		} else {
			$fill.parent().show();
			$fill.css( 'width', progress + '%' );
		}

		// Track shipping bar progress interaction (once per page).
		if ( progress > 0 && ! hasTrackedProgress && typeof meyvcTracker !== 'undefined' && meyvcTracker.track ) {
			hasTrackedProgress = true;
			meyvcTracker.track( 'shipping_bar_progress', { progress: Math.round( progress ), page_url: window.location.href } );
		}
	}

	// Listen for cart updates.
	$( document.body ).on( 'added_to_cart removed_from_cart updated_cart_totals', function() {
		$.ajax({
			url: meyvcShippingBar.ajaxUrl,
			type: 'POST',
			data: {
				action: 'meyvc_get_cart_total',
				nonce: meyvcShippingBar.nonce
			},
			success: function( response ) {
				if ( response.success && response.data && typeof response.data.total !== 'undefined' ) {
					updateBar( parseFloat( response.data.total, 10 ) );
				}
			}
		});
	});

})( jQuery );
