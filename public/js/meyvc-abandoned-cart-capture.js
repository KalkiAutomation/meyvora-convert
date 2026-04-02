/**
 * Abandoned cart email capture: checkout checkbox (Option A) and cart form (Option B).
 * Sends email + consent via AJAX; no silent capture.
 */
(function ($) {
	'use strict';

	var config = window.meyvcAbandonedCartCapture || {};
	var ajaxUrl = config.ajax_url || '';
	var nonce = config.nonce || '';

	function sendEmailConsent(email, consent, done) {
		if (!ajaxUrl || !nonce) {
			if (done) done({ success: false, message: 'Missing config.' });
			return;
		}
		$.post(ajaxUrl, {
			action: 'meyvc_save_abandoned_cart_email',
			nonce: nonce,
			email: email || '',
			consent: consent ? '1' : '0'
		})
			.done(function (response) {
				if (response && response.success && done) {
					done({ success: true, message: response.data && response.data.message ? response.data.message : 'Saved.' });
				} else {
					done({ success: false, message: (response && response.data && response.data.message) ? response.data.message : 'Error.' });
				}
			})
			.fail(function (xhr) {
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : 'Request failed.';
				if (done) done({ success: false, message: msg });
			});
	}

	// Option A: Checkout – when reminder checkbox or billing email changes, save if checkbox checked and email present
	function initCheckout() {
		var $wrap = $(document.body);
		var $checkbox = $wrap.find('[name="meyvc_abandoned_cart_reminder"]');
		var $billingEmail = $wrap.find('#billing_email');
		var $notice = $wrap.find('.meyvc-abandoned-cart-checkout-notice');
		if (!$checkbox.length || !$billingEmail.length) return;

		var debounceTimer;
		function maybeSave() {
			clearTimeout(debounceTimer);
			debounceTimer = setTimeout(function () {
				var consent = $checkbox.is(':checked');
				var email = ($billingEmail.val() || '').trim();
				if (consent && !email) return;
				sendEmailConsent(email, consent, function (result) {
					if ($notice.length) {
						$notice.removeClass('success error').show().text(result.message);
						$notice.addClass(result.success ? 'success' : 'error');
					}
				});
			}, 400);
		}

		$checkbox.on('change', maybeSave);
		$billingEmail.on('input blur', function () {
			if ($checkbox.is(':checked')) maybeSave();
		});
	}

	// Option B: Cart – Save button sends email + consent
	function initCart() {
		var $container = $('[data-meyvc-cart-email-capture]');
		if (!$container.length) return;

		var $email = $container.find('#meyvc_cart_reminder_email');
		var $consent = $container.find('#meyvc_cart_reminder_consent');
		var $btn = $container.find('.meyvc-cart-reminder-save');
		var $feedback = $container.find('[data-meyvc-feedback]');

		$btn.on('click', function () {
			var email = ($email.val() || '').trim();
			var consent = $consent.is(':checked');
			if (consent && !email) {
				$feedback.removeClass('success').addClass('error').show().text('Please enter your email.');
				return;
			}
			$btn.prop('disabled', true);
			sendEmailConsent(email, consent, function (result) {
				$btn.prop('disabled', false);
				$feedback.removeClass('success error').addClass(result.success ? 'success' : 'error').show().text(result.message);
			});
		});
	}

	$(function () {
		initCheckout();
		initCart();
	});
})(jQuery);
