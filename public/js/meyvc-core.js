/**
 * Meyvora Convert – deferred bootstrap
 * Runs only when page is interactive to avoid blocking.
 *
 * @package Meyvora_Convert
 */
(function() {
	'use strict';

	function initMEYVC() {
		var config = window.meyvcConfig || {};
		var features = config.features || {};

		// MEYVCController handles all campaign triggers (exit intent, time, scroll, inactivity).
		// It self-initializes on DOMContentLoaded via its own listener in meyvc-controller.js.
		// No action needed here — this block is intentionally left clean.

		if (features.stickyCart && typeof window.MEYVCStickyCart !== 'undefined') {
			window.meyvcStickyCart = new window.MEYVCStickyCart();
		}

		if (features.shippingBar && typeof window.MEYVCShippingBar !== 'undefined') {
			window.meyvcShippingBar = new window.MEYVCShippingBar();
		}

		try {
			window.dispatchEvent(new CustomEvent('meyvc:init', { detail: { features: features } }));
		} catch (e) {
			// IE11 fallback
			var ev = document.createEvent('CustomEvent');
			if (ev && ev.initCustomEvent) {
				ev.initCustomEvent('meyvc:init', true, true, { features: features });
				window.dispatchEvent(ev);
			}
		}
	}

	function runWhenReady() {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', runWhenIdle);
		} else {
			runWhenIdle();
		}
	}

	function runWhenIdle() {
		if (typeof requestIdleCallback !== 'undefined') {
			requestIdleCallback(initMEYVC, { timeout: 2000 });
		} else {
			setTimeout(initMEYVC, 100);
		}
	}

	runWhenReady();
})();
