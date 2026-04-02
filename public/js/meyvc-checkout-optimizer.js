/**
 * Classic checkout: coupon toggle + optional first-field focus.
 */
(function ($) {
	'use strict';
	$(function () {
		$(document).on('click', '.meyvc-coupon-toggle-link', function (e) {
			e.preventDefault();
			$('.meyvc-coupon-form').slideToggle();
		});
	});
})(jQuery);

(function ($) {
	'use strict';
	if (!$('body').hasClass('woocommerce-checkout')) {
		return;
	}
	$(function () {
		var $fields = $(
			'.woocommerce-checkout input[type="text"], .woocommerce-checkout input[type="email"]'
		).filter(':visible');
		$fields.each(function () {
			if ($(this).val() === '') {
				$(this).trigger('focus');
				return false;
			}
		});
	});
})(jQuery);
