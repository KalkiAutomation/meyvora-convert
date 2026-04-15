/**
 * CRO Sticky Cart – show/hide bar and add-to-cart
 *
 * @package Meyvora_Convert
 */
(function($) {
	'use strict';

	/**
	 * If PHP did not localize meyvcStickyCart (e.g. race on enqueue), build minimal config from the bar DOM.
	 */
	function ensureMeyvcStickyCart() {
		if ( typeof meyvcStickyCart !== 'undefined' ) {
			return true;
		}
		var $bar = $( '#meyvc-sticky-cart' );
		if ( ! $bar.length ) {
			return false;
		}
		var pid = parseInt( $bar.attr( 'data-product-id' ) || '0', 10 ) || 0;
		if ( ! pid ) {
			return false;
		}
		var ajaxUrl = $bar.attr( 'data-ajax-url' ) || '';
		var nonce = $bar.attr( 'data-nonce' ) || '';
		var cartUrl = '/';
		if ( typeof wc_add_to_cart_params !== 'undefined' && wc_add_to_cart_params.cart_url ) {
			cartUrl = wc_add_to_cart_params.cart_url;
		}
		window.meyvcStickyCart = {
			ajaxUrl: ajaxUrl,
			nonce: nonce,
			settings: {},
			product: { id: pid, type: 'simple', in_stock: true },
			variations: [],
			cartUrl: cartUrl,
			i18n: { adding: 'Adding...', added: 'Added!', view_cart: 'View Cart', choose_variation: 'Please select all options.' }
		};
		return true;
	}

	function getStickyAttributes( $bar ) {
		var attrs = {};
		var complete = true;
		$bar.find( 'select.meyvc-sticky-cart-attr' ).each( function() {
			var $s = $( this );
			var name = $s.attr( 'data-attribute_name' );
			if ( ! name ) {
				return;
			}
			var val = $s.val();
			if ( ! val ) {
				complete = false;
			}
			attrs[ name ] = val;
		} );
		return { attrs: attrs, complete: complete };
	}

	function findMatchingVariation( variations, attrs ) {
		if ( ! variations || ! variations.length ) {
			return null;
		}
		var i, v, va, k, need, ok;
		for ( i = 0; i < variations.length; i++ ) {
			v = variations[ i ];
			if ( ! v || ! v.variation_is_active ) {
				continue;
			}
			va = v.attributes || {};
			ok = true;
			for ( k in va ) {
				if ( ! Object.prototype.hasOwnProperty.call( va, k ) ) {
					continue;
				}
				need = va[ k ];
				if ( need === '' || need === null ) {
					continue;
				}
				if ( String( attrs[ k ] || '' ).toLowerCase() !== String( need ).toLowerCase() ) {
					ok = false;
					break;
				}
			}
			if ( ok && v.is_in_stock ) {
				return v;
			}
		}
		return null;
	}

	function syncVariableSticky( $stickyBar, $btn ) {
		var vars = ( typeof meyvcStickyCart !== 'undefined' && meyvcStickyCart.variations ) ? meyvcStickyCart.variations : [];
		var pack = getStickyAttributes( $stickyBar );
		var $price = $stickyBar.find( '.meyvc-sticky-cart-price' );
		if ( ! pack.complete ) {
			$btn.prop( 'disabled', true ).attr( 'data-variation-id', '' );
			if ( $price.length && $price.data( 'meyvcPriceHtml' ) ) {
				$price.html( $price.data( 'meyvcPriceHtml' ) );
			}
			return;
		}
		var matched = findMatchingVariation( vars, pack.attrs );
		if ( ! matched || ! matched.variation_id ) {
			$btn.prop( 'disabled', true ).attr( 'data-variation-id', '' );
			return;
		}
		$btn.prop( 'disabled', false ).attr( 'data-variation-id', String( matched.variation_id ) );
		if ( matched.price_html && $price.length ) {
			if ( ! $price.data( 'meyvcPriceHtml' ) ) {
				$price.data( 'meyvcPriceHtml', $price.html() );
			}
			$price.html( matched.price_html );
		}
	}

	$( document ).ready( function() {
		if ( ! ensureMeyvcStickyCart() ) {
			return;
		}

		var settings   = meyvcStickyCart.settings;
		var $stickyBar = $( '#meyvc-sticky-cart' );
		var $button    = $stickyBar.find( '.meyvc-sticky-cart-button' );
		var isVisible  = false;
		var originalAddToCart = null;

		if ( ! $stickyBar.length ) {
			return;
		}

		var isVariable = $stickyBar.find( 'select.meyvc-sticky-cart-attr' ).length > 0;
		if ( isVariable && $stickyBar.find( '.meyvc-sticky-cart-price' ).length ) {
			var $p = $stickyBar.find( '.meyvc-sticky-cart-price' );
			$p.data( 'meyvcPriceHtml', $p.html() );
		}

		function getAddToCartPosition() {
			var $originalBtn = $(
				'form.cart .single_add_to_cart_button,' +
				'form.cart button[type="submit"],' +
				'.wp-block-woocommerce-add-to-cart-form button[type="submit"]'
			).first();
			if ( $originalBtn.length ) {
				return $originalBtn.offset().top + $originalBtn.outerHeight();
			}
			return 300;
		}

		function handleScroll() {
			var scrollTop    = $( window ).scrollTop();
			var triggerPoint = originalAddToCart !== null ? originalAddToCart : getAddToCartPosition();
			var showAfter    = ( settings && settings.show_after_scroll ) ? parseInt( settings.show_after_scroll, 10 ) : 100;
			var shouldShow   = scrollTop > Math.max( triggerPoint, showAfter );
			if ( shouldShow && ! isVisible ) {
				$stickyBar.addClass( 'meyvc-sticky-cart-visible' );
				isVisible = true;
			} else if ( ! shouldShow && isVisible ) {
				$stickyBar.removeClass( 'meyvc-sticky-cart-visible' );
				isVisible = false;
			}
		}

		function addToCart( productId, variationId ) {
			var originalText = $button.text();
			$button.prop( 'disabled', true ).text( meyvcStickyCart.i18n.adding );
			var data = {
				action: 'meyvc_add_to_cart',
				product_id: productId,
				quantity: 1,
				nonce: meyvcStickyCart.nonce
			};
			if ( variationId ) {
				data.variation_id = variationId;
				var pack = getStickyAttributes( $stickyBar );
				var k;
				for ( k in pack.attrs ) {
					if ( Object.prototype.hasOwnProperty.call( pack.attrs, k ) ) {
						data[ k ] = pack.attrs[ k ];
					}
				}
			}
			$.ajax({
				url: meyvcStickyCart.ajaxUrl,
				type: 'POST',
				data: data,
				success: function( response ) {
					if ( response.success ) {
						$button.text( meyvcStickyCart.i18n.added );
						$( document.body ).trigger( 'added_to_cart', [ response.data.fragments, response.data.cart_hash ] );
						var cartUrl = ( typeof wc_add_to_cart_params !== 'undefined' && wc_add_to_cart_params.cart_url )
							? wc_add_to_cart_params.cart_url
							: ( meyvcStickyCart.cartUrl || '/' );
						setTimeout( function() {
							$button.prop( 'disabled', false ).html(
								'<a href="' + cartUrl + '" style="color:inherit;text-decoration:none;">' +
								meyvcStickyCart.i18n.view_cart + '</a>'
							);
						}, 1500 );
						if ( typeof meyvcTracker !== 'undefined' && meyvcTracker.track ) {
							meyvcTracker.track( 'sticky_cart_add', { product_id: productId, variation_id: variationId || 0 } );
						}
					} else {
						$button.text( originalText ).prop( 'disabled', false );
						if ( isVariable ) {
							syncVariableSticky( $stickyBar, $button );
						}
					}
				},
				error: function() {
					$button.text( originalText ).prop( 'disabled', false );
					if ( isVariable ) {
						syncVariableSticky( $stickyBar, $button );
					}
				}
			});
		}

		function scrollToOptions() {
			var $form = $( 'form.cart, .wp-block-woocommerce-add-to-cart-form' ).first();
			if ( $form.length ) {
				$( 'html, body' ).animate({ scrollTop: $form.offset().top - 100 }, 500 );
			}
		}

		if ( isVariable ) {
			syncVariableSticky( $stickyBar, $button );
			$stickyBar.on( 'change', 'select.meyvc-sticky-cart-attr', function() {
				syncVariableSticky( $stickyBar, $button );
			} );
		}

		$stickyBar.css( 'display', 'block' ).removeClass( 'meyvc-sticky-cart-visible' );
		originalAddToCart = getAddToCartPosition();
		var scrollTimeout;
		$( window ).on( 'scroll', function() {
			if ( scrollTimeout ) { return; }
			scrollTimeout = setTimeout( function() {
				handleScroll();
				scrollTimeout = null;
			}, 100 );
		});
		handleScroll();

		$button.on( 'click', function( e ) {
			var productId = parseInt( $( this ).attr( 'data-product-id' ) || '0', 10 );
			if ( ! productId ) {
				return;
			}
			e.preventDefault();
			if ( $( this ).hasClass( 'meyvc-sticky-cart-button--variable' ) ) {
				var vid = parseInt( $( this ).attr( 'data-variation-id' ) || '0', 10 );
				if ( ! vid ) {
					var msg = ( meyvcStickyCart.i18n && meyvcStickyCart.i18n.choose_variation ) ? meyvcStickyCart.i18n.choose_variation : 'Please select all options.';
					if ( typeof window.alert === 'function' ) {
						window.alert( msg );
					}
					return;
				}
				addToCart( productId, vid );
				return;
			}
			addToCart( productId, 0 );
		});
		$stickyBar.on( 'click', '.meyvc-scroll-to-options', function( e ) {
			e.preventDefault();
			scrollToOptions();
		});
	});

})( jQuery );
