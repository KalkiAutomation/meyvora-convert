/**
 * CRO Offer Banner: Apply coupon button → AJAX → refresh fragments.
 */
(function ($) {
	'use strict';

	function applyOfferCoupon($banner) {
		var $btn = $banner.find('.meyvc-offer-apply-coupon').first();
		var $success = $banner.find('.meyvc-offer-banner__msg--success');
		var $error = $banner.find('.meyvc-offer-banner__msg--error');
		var nonce = $banner.data('nonce');
		var url = $banner.data('ajax-url');
		var couponCode = $banner.data('coupon-code');
		if (!nonce || !url) return;
		$success.hide().text('');
		$error.hide().text('');
		$btn.prop('disabled', true);
		var payload = {
			action: 'meyvc_apply_offer_coupon',
			nonce: nonce
		};
		if (couponCode && typeof couponCode === 'string' && couponCode.length > 0) {
			payload.coupon_code = couponCode;
		}
		$.post(url, payload).done(function (res) {
			if (res.success && res.data) {
				$success.text(res.data.message || '').show();
				$btn.closest('.meyvc-offer-banner__actions').hide();
				if (res.data.fragments && typeof res.data.fragments === 'object') {
					$.each(res.data.fragments, function (selector, html) {
						$(selector).replaceWith(html);
					});
				}
				if (res.data.cart_hash && typeof wc_cart_fragments_params !== 'undefined') {
					try {
						sessionStorage.setItem(wc_cart_fragments_params.cart_hash_key, res.data.cart_hash);
						localStorage.setItem(wc_cart_fragments_params.cart_hash_key, res.data.cart_hash);
					} catch (e) {}
				}
				$(document.body).trigger('wc_fragments_refreshed');
			}
		}).fail(function (xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ?
				xhr.responseJSON.data.message : 'Request failed.';
			$error.text(msg).show();
		}).always(function () {
			$btn.prop('disabled', false);
		});
	}

	$(document.body).on('click', '.meyvc-offer-apply-coupon', function () {
		var $banner = $(this).closest('.meyvc-offer-banner');
		if ($banner.length) {
			applyOfferCoupon($banner);
		}
	});
})(jQuery);
