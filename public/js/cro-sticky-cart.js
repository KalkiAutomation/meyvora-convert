/**
 * CRO Sticky Cart – show/hide bar and add-to-cart
 *
 * @package Meyvora_Convert
 */
(function($) {
	'use strict';

	if ( typeof croStickyCart === 'undefined' ) {
		return;
	}

	var settings = croStickyCart.settings;
	var $stickyBar = $('#cro-sticky-cart');
	var $button = $stickyBar.find('.cro-sticky-cart-button');

	var isVisible = false;
	var originalAddToCart = null;

	/**
	 * Find original add to cart button position (pixels from top).
	 */
	function getAddToCartPosition() {
		var $originalBtn = $('form.cart .single_add_to_cart_button, form.cart button[type="submit"]').first();
		if ( $originalBtn.length ) {
			return $originalBtn.offset().top + $originalBtn.outerHeight();
		}
		return 300;
	}

	/**
	 * Show/hide bar based on scroll.
	 */
	function handleScroll() {
		var scrollTop = $(window).scrollTop();
		var triggerPoint = originalAddToCart !== null ? originalAddToCart : getAddToCartPosition();
		var showAfter = ( settings && settings.show_after_scroll ) ? parseInt( settings.show_after_scroll, 10 ) : 100;

		var shouldShow = scrollTop > Math.max( triggerPoint, showAfter );

		if ( shouldShow && ! isVisible ) {
			$stickyBar.addClass( 'cro-sticky-cart-visible' );
			isVisible = true;
		} else if ( ! shouldShow && isVisible ) {
			$stickyBar.removeClass( 'cro-sticky-cart-visible' );
			isVisible = false;
		}
	}

	/**
	 * Add to cart via AJAX.
	 */
	function addToCart( productId ) {
		var originalText = $button.text();

		$button.prop( 'disabled', true ).text( croStickyCart.i18n.adding );

		$.ajax({
			url: croStickyCart.ajaxUrl,
			type: 'POST',
			data: {
				action: 'cro_add_to_cart',
				product_id: productId,
				quantity: 1,
				nonce: croStickyCart.nonce
			},
			success: function( response ) {
				if ( response.success ) {
					$button.text( croStickyCart.i18n.added );

					// Trigger WooCommerce cart update.
					$( document.body ).trigger( 'added_to_cart', [ response.data.fragments, response.data.cart_hash ] );

					// Show view cart link.
					var cartUrl = ( typeof wc_add_to_cart_params !== 'undefined' && wc_add_to_cart_params.cart_url )
						? wc_add_to_cart_params.cart_url
						: ( croStickyCart.cartUrl || '/' );
					setTimeout( function() {
						$button.prop( 'disabled', false ).html(
							'<a href="' + cartUrl + '" style="color: inherit; text-decoration: none;">' +
							croStickyCart.i18n.view_cart + '</a>'
						);
					}, 1500 );

					// Track event if tracker exists.
					if ( typeof croTracker !== 'undefined' && croTracker.track ) {
						croTracker.track( 'sticky_cart_add', { product_id: productId } );
					}
				} else {
					$button.text( originalText ).prop( 'disabled', false );
				}
			},
			error: function() {
				$button.text( originalText ).prop( 'disabled', false );
			}
		});
	}

	/**
	 * Scroll to product form (for variable products).
	 */
	function scrollToOptions() {
		var $form = $( 'form.cart' );
		if ( $form.length ) {
			$( 'html, body' ).animate({
				scrollTop: $form.offset().top - 100
			}, 500 );
		}
	}

	$( document ).ready( function() {
		if ( ! $stickyBar.length ) {
			return;
		}

		// Ensure initial state: hidden via transform (not display:none which breaks transform).
		$stickyBar.css( 'display', 'block' ).removeClass( 'cro-sticky-cart-visible' );

		originalAddToCart = getAddToCartPosition();

		// Throttled scroll handler.
		var scrollTimeout;
		$( window ).on( 'scroll', function() {
			if ( scrollTimeout ) {
				return;
			}
			scrollTimeout = setTimeout( function() {
				handleScroll();
				scrollTimeout = null;
			}, 100 );
		});

		// Initial check.
		handleScroll();

		// Add to cart click (simple products).
		$button.on( 'click', function( e ) {
			var productId = $( this ).data( 'product-id' );
			if ( productId ) {
				e.preventDefault();
				addToCart( productId );
			}
		});

		// Scroll to options (variable products).
		$stickyBar.on( 'click', '.cro-scroll-to-options', function( e ) {
			e.preventDefault();
			scrollToOptions();
		});
	});

})( jQuery );
