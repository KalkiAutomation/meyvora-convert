/**
 * CRO UX Detector – client-side UX state (typing, form focus, reduced motion).
 * Used before showing popups to avoid interrupting checkout or form input.
 *
 * @package Meyvora_Convert
 */
(function() {
	'use strict';

	/**
	 * CROUXDetector – detects form focus, typing, checkout/payment fields, prefers-reduced-motion.
	 */
	function CROUXDetector() {
		this.state = {
			isTyping: false,
			formFocused: false,
			isCheckoutForm: false,
			isPaymentForm: false,
			prefersReducedMotion: false,
			lastInteraction: null
		};
		this.init();
	}

	CROUXDetector.prototype.init = function() {
		var self = this;

		// Detect reduced motion preference.
		var motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
		self.state.prefersReducedMotion = motionQuery && motionQuery.matches;

		if (motionQuery && motionQuery.addEventListener) {
			motionQuery.addEventListener('change', function(e) {
				self.state.prefersReducedMotion = e.matches;
			});
		}

		// Track form focus.
		document.addEventListener('focusin', function(e) {
			if (self.isFormElement(e.target)) {
				self.state.formFocused = true;
				self.state.isTyping = true;
				if (self.isCheckoutField(e.target)) {
					self.state.isCheckoutForm = true;
				}
				if (self.isPaymentField(e.target)) {
					self.state.isPaymentForm = true;
				}
			}
		});

		document.addEventListener('focusout', function(e) {
			if (self.isFormElement(e.target)) {
				setTimeout(function() {
					var active = document.activeElement;
					if (!self.isFormElement(active)) {
						self.state.formFocused = false;
						self.state.isTyping = false;
						self.state.isCheckoutForm = false;
						self.state.isPaymentForm = false;
					}
				}, 100);
			}
		});

		// Track typing.
		document.addEventListener('keydown', function() {
			if (self.state.formFocused) {
				self.state.isTyping = true;
				self.state.lastInteraction = Date.now();
			}
		});
	};

	CROUXDetector.prototype.isFormElement = function(el) {
		if (!el) return false;
		var tag = el.tagName ? el.tagName.toLowerCase() : '';
		return tag === 'input' || tag === 'textarea' || tag === 'select' || !!el.isContentEditable;
	};

	CROUXDetector.prototype.isCheckoutField = function(el) {
		return (el.closest && (el.closest('.woocommerce-checkout') || el.closest('#customer_details'))) || false;
	};

	CROUXDetector.prototype.isPaymentField = function(el) {
		if (!el.closest) return false;
		return !!(
			el.closest('.payment_box') ||
			el.closest('#payment') ||
			el.closest('.wc-stripe-elements-field') ||
			el.closest('.wc-braintree-hosted-field')
		);
	};

	CROUXDetector.prototype.getState = function() {
		return this.state;
	};

	CROUXDetector.prototype.canShowPopup = function() {
		if (this.state.isTyping) return false;
		if (this.state.isCheckoutForm) return false;
		if (this.state.isPaymentForm) return false;
		return true;
	};

	CROUXDetector.prototype.shouldReduceMotion = function() {
		return this.state.prefersReducedMotion;
	};

	// Initialize and expose.
	window.CROUXDetector = CROUXDetector;
	window.croUX = new CROUXDetector();

	/**
	 * Whether a popup may be shown based on current UX state.
	 *
	 * @return {boolean}
	 */
	window.canShowPopupNow = function() {
		return window.croUX && window.croUX.canShowPopup();
	};
})();
