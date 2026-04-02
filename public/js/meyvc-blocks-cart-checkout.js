/**
 * Meyvora Convert – WooCommerce Blocks (Cart / Checkout) extension.
 *
 * Loaded when Cart or Checkout block is on the page. Settings are available via
 * getSetting('meyvora-convert_data') (from wc-settings). Use this script to render
 * CRO UI via block slots or to enhance server-injected markup.
 */
(function () {
	'use strict';

	function getMeyvcData() {
		try {
			if (typeof window.wc !== 'undefined' && window.wc.wcSettings && typeof window.wc.wcSettings.getSetting === 'function') {
				return window.wc.wcSettings.getSetting('meyvora-convert_data', {});
			}
if (typeof window.wcSettings !== 'undefined' && window.wcSettings['meyvora-convert_data']) {
			return window.wcSettings['meyvora-convert_data'];
			}
		} catch (e) {
			// ignore
		}
		return {};
	}

	var data = getMeyvcData();

	// Coupon toggle: enhance server-injected .meyvc-blocks-coupon form (fallback from render_block).
	if (data.checkoutOptimizerEnabled && data.checkoutSettings && data.checkoutSettings.move_coupon_to_top && data.couponsEnabled) {
		function initCouponToggle() {
			var wrapper = document.querySelector('.meyvc-blocks-coupon');
			if (!wrapper) return;
			var link = wrapper.querySelector('.meyvc-coupon-toggle-link');
			var form = wrapper.querySelector('.meyvc-coupon-form');
			if (link && form) {
				link.addEventListener('click', function (e) {
					e.preventDefault();
					form.style.display = form.style.display === 'none' ? '' : 'none';
				});
			}
		}
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', initCouponToggle);
		} else {
			initCouponToggle();
		}
	}

	// Expose for slot-based rendering or other extensions.
	window.meyvcBlocksData = data;
})();
