/**
 * Admin JavaScript
 */
(function($) {
	'use strict';

	/**
	 * Boosters: live preview for Free shipping bar (50% cart demo).
	 */
	function croFormatPreviewPrice(amount) {
		var sym = typeof croAdmin !== 'undefined' && croAdmin.currency ? croAdmin.currency : '$';
		var dec = 2;
		if (typeof croAdmin !== 'undefined' && croAdmin.priceDecimals != null) {
			dec = parseInt(croAdmin.priceDecimals, 10);
			if (isNaN(dec)) {
				dec = 2;
			}
		}
		var n = parseFloat(amount, 10);
		if (isNaN(n)) {
			n = 0;
		}
		return sym + n.toFixed(dec);
	}

	function updateCroShippingBarPreview() {
		var $wrap = $('#cro-shipping-bar-preview-wrap');
		var $bar = $('#cro-shipping-bar-preview');
		if (!$wrap.length || !$bar.length) {
			return;
		}
		var useWoo = $('#shipping_bar_use_woo').is(':checked');
		var wooTh = parseFloat($wrap.data('woo-threshold'), 10);
		if (isNaN(wooTh)) {
			wooTh = 0;
		}
		var customTh = parseFloat($('#shipping_bar_threshold').val(), 10);
		if (isNaN(customTh)) {
			customTh = 0;
		}
		var th = useWoo && wooTh > 0 ? wooTh : customTh;
		if (th <= 0) {
			th = 50;
		}
		var cart = th * 0.5;
		var remaining = Math.max(0, th - cart);
		var pct = Math.min(100, (cart / th) * 100);

		var msgRaw = $('#shipping_bar_message_progress').val();
		if (!msgRaw) {
			msgRaw = $('#shipping_bar_message_progress').attr('placeholder') || '';
		}
		var formatted = croFormatPreviewPrice(remaining);
		var line = (msgRaw || '').split('{amount}').join(formatted);
		$('#cro-shipping-bar-preview-message').text(line);

		var bg = $('input[name="shipping_bar_bg_color"]').val() || '#f7f7f7';
		var barColor = $('input[name="shipping_bar_bar_color"]').val() || '#333333';
		$bar.css('background-color', bg);
		$('#cro-shipping-bar-preview-fill').css({
			width: pct + '%',
			'background-color': barColor
		});
		$('#cro-shipping-bar-preview-progress-wrap').show();
	}

	/**
	 * Boosters: live preview for Sticky add-to-cart bar (matches storefront markup).
	 */
	function updateCroStickyCartPreview() {
		var $wrap = $('#cro-sticky-cart-preview-wrap');
		if (!$wrap.length) {
			return;
		}
		var defaults = {};
		try {
			defaults = JSON.parse($wrap.attr('data-default-buttons') || '{}');
		} catch (e) {
			defaults = {};
		}
		var tone = $('#sticky_cart_tone').val() || 'neutral';
		var btnRaw = ($('#sticky_cart_button_text').val() || '').trim();
		var btnText = btnRaw;
		if (!btnText) {
			btnText = defaults[tone] || defaults.neutral || 'Add to cart';
		}
		$('#cro-sticky-cart-preview-btn').text(btnText);

		var bg = $('input[name="sticky_cart_bg_color"]').val() || '#ffffff';
		var btnBg = $('input[name="sticky_cart_button_color"]').val() || '#333333';
		$('#cro-sticky-cart-preview-inner').css('background-color', bg);
		$('#cro-sticky-cart-preview-btn').css({
			'background-color': btnBg,
			color: '#ffffff'
		});

		$('#cro-sticky-cart-preview-image-wrap').toggle($('input[name="sticky_cart_show_image"]').is(':checked'));
		$('#cro-sticky-cart-preview-title').toggle($('input[name="sticky_cart_show_title"]').is(':checked'));
		$('#cro-sticky-cart-preview-price').toggle($('input[name="sticky_cart_show_price"]').is(':checked'));
	}

	$(document).ready(function() {
		// Initialize color pickers on boosters/settings pages.
		if ($('.cro-color-picker').length && $.fn.wpColorPicker) {
			$('.cro-color-picker').wpColorPicker({
				change: function() {
					$(document).trigger('croShippingBarPreviewUpdate');
					$(document).trigger('croStickyCartPreviewUpdate');
				},
				clear: function() {
					$(document).trigger('croShippingBarPreviewUpdate');
					$(document).trigger('croStickyCartPreviewUpdate');
				}
			});
		}

		$(document).on(
			'input change',
			'#shipping_bar_message_progress, #shipping_bar_threshold, #shipping_bar_use_woo',
			updateCroShippingBarPreview
		);
		$(document).on('change', '#shipping_bar_tone', updateCroShippingBarPreview);
		$(document).on('croShippingBarPreviewUpdate', updateCroShippingBarPreview);

		if ($('#cro-shipping-bar-preview-wrap').length) {
			updateCroShippingBarPreview();
		}

		$(document).on(
			'input change',
			'#sticky_cart_button_text, #sticky_cart_tone, input[name="sticky_cart_show_image"], input[name="sticky_cart_show_title"], input[name="sticky_cart_show_price"]',
			updateCroStickyCartPreview
		);
		$(document).on('select2:select', '#sticky_cart_tone', updateCroStickyCartPreview);
		$(document).on('croStickyCartPreviewUpdate', updateCroStickyCartPreview);

		if ($('#cro-sticky-cart-preview-wrap').length) {
			updateCroStickyCartPreview();
		}

		// Sticky nav: add .is-stuck when nav has scrolled past sentinel (for shadow).
		var sentinel = document.getElementById('cro-admin-layout-nav-sentinel');
		var nav = sentinel ? sentinel.nextElementSibling : null;
		if (sentinel && nav && nav.classList.contains('cro-admin-layout__nav')) {
			var observer = new IntersectionObserver(
				function(entries) {
					entries.forEach(function(entry) {
						if (entry.target === sentinel) {
							if (entry.intersectionRatio === 0) {
								nav.classList.add('is-stuck');
							} else {
								nav.classList.remove('is-stuck');
							}
						}
					});
				},
				{ root: null, rootMargin: '0px', threshold: 0 }
			);
			observer.observe(sentinel);
		}
	});

})(jQuery);
