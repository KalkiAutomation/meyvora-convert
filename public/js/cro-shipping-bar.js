/**
 * CRO Shipping Bar – progress bar and message, updates on cart events
 *
 * @package Meyvora_Convert
 */
(function($) {
	'use strict';

	if ( typeof croShippingBar === 'undefined' ) {
		return;
	}

	var settings = croShippingBar.settings;
	var threshold = parseFloat( croShippingBar.threshold, 10 );
	var hasTrackedProgress = false;

	/**
	 * Update bar message and progress from cart total.
	 *
	 * @param {number} cartTotal Current cart subtotal.
	 */
	function updateBar( cartTotal ) {
		var $bar = $( '.cro-shipping-bar' );
		if ( ! $bar.length ) {
			return;
		}

		var remaining = Math.max( 0, threshold - cartTotal );
		var progress = threshold > 0 ? Math.min( 100, ( cartTotal / threshold ) * 100 ) : 0;
		var achieved = remaining <= 0;

		var message;
		if ( achieved ) {
			message = settings && settings.message_achieved ? settings.message_achieved : '';
			$bar.addClass( 'cro-shipping-achieved' );
		} else {
			message = settings && settings.message_progress
				? settings.message_progress.replace( '{amount}', ( croShippingBar.currency || '' ) + remaining.toFixed( 2 ) )
				: '';
			$bar.removeClass( 'cro-shipping-achieved' );
		}

		$bar.find( '.cro-shipping-bar-message' ).html( message );

		var $fill = $bar.find( '.cro-shipping-bar-fill' );
		if ( achieved ) {
			$fill.parent().hide();
		} else {
			$fill.parent().show();
			$fill.css( 'width', progress + '%' );
		}

		// Track shipping bar progress interaction (once per page).
		if ( progress > 0 && ! hasTrackedProgress && typeof croTracker !== 'undefined' && croTracker.track ) {
			hasTrackedProgress = true;
			croTracker.track( 'shipping_bar_progress', { progress: Math.round( progress ), page_url: window.location.href } );
		}
	}

	// Listen for cart updates.
	$( document.body ).on( 'added_to_cart removed_from_cart updated_cart_totals', function() {
		$.ajax({
			url: croShippingBar.ajaxUrl,
			type: 'POST',
			data: {
				action: 'cro_get_cart_total',
				nonce: croShippingBar.nonce
			},
			success: function( response ) {
				if ( response.success && response.data && typeof response.data.total !== 'undefined' ) {
					updateBar( parseFloat( response.data.total, 10 ) );
				}
			}
		});
	});

})( jQuery );
