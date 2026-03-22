/**
 * Meyvora Convert – deferred bootstrap
 * Runs only when page is interactive to avoid blocking.
 *
 * @package Meyvora_Convert
 */
(function() {
	'use strict';

	function initCRO() {
		var config = window.croConfig || {};
		var features = config.features || {};

		// CROController handles all campaign triggers (exit intent, time, scroll, inactivity).
		// It self-initializes on DOMContentLoaded via its own listener in cro-controller.js.
		// No action needed here — this block is intentionally left clean.

		if (features.stickyCart && typeof window.CROStickyCart !== 'undefined') {
			window.croStickyCart = new window.CROStickyCart();
		}

		if (features.shippingBar && typeof window.CROShippingBar !== 'undefined') {
			window.croShippingBar = new window.CROShippingBar();
		}

		try {
			window.dispatchEvent(new CustomEvent('cro:init', { detail: { features: features } }));
		} catch (e) {
			// IE11 fallback
			var ev = document.createEvent('CustomEvent');
			if (ev && ev.initCustomEvent) {
				ev.initCustomEvent('cro:init', true, true, { features: features });
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
			requestIdleCallback(initCRO, { timeout: 2000 });
		} else {
			setTimeout(initCRO, 100);
		}
	}

	runWhenReady();
})();
