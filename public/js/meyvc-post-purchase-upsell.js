/**
 * Post-purchase upsell: add to cart + dismiss (localized via wp_localize_script).
 */
(function ($) {
	'use strict';
	var cfg = typeof meyvcPostPurchaseUpsell !== 'undefined' ? meyvcPostPurchaseUpsell : {};
	var ajaxUrl = cfg.ajaxUrl || '';
	var action = cfg.action || 'meyvc_post_purchase_add';
	var i18n = cfg.i18n || {};

	$(function () {
		$('.meyvc-post-purchase-add').on('click', function () {
			var $btn = $(this);
			$btn.prop('disabled', true).text(i18n.adding || '…');
			$.post(ajaxUrl, {
				action: action,
				nonce: $btn.data('nonce'),
				product_id: $btn.data('product-id'),
			})
				.done(function (r) {
					if (r && r.success) {
						$btn.text(i18n.added || '');
					} else {
						$btn.prop('disabled', false).text(i18n.add || '');
						window.alert(r && r.data && r.data.message ? r.data.message : i18n.error || 'Error');
					}
				})
				.fail(function () {
					$btn.prop('disabled', false).text(i18n.add || '');
				});
		});
		$('.meyvc-post-purchase-dismiss').on('click', function (e) {
			e.preventDefault();
			$(this).closest('.meyvc-post-purchase-upsell').slideUp(200);
		});
	});
})(jQuery);
