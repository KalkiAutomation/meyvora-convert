/**
 * Exit intent detection
 *
 * @package Meyvora_Convert
 */
(function($) {
	'use strict';

	var exitIntentDetected = false;

	function triggerExitIntent(source) {
		if (exitIntentDetected) return;
		exitIntentDetected = true;
		$(document).trigger('cro:exit-intent', source ? [source] : []);
	}

	function triggerMobileExitIntent(source) {
		triggerExitIntent(source);
	}

	/**
	 * Signal collector for exit intent validation
	 */
	class CROSignalCollector {
		constructor() {
			this.signals = {
				mouse_positions: [],
				scroll_positions: [],
				last_interaction: null,
				typing_start: null,
				is_typing: false,
				has_interacted: false,
				time_on_page: 0,
				page_load_time: Date.now()
			};
			
			this.init();
		}
		
		init() {
			// Track mouse movement (desktop)
			if (!this.isMobile()) {
				document.addEventListener('mousemove', this.throttle((e) => {
					this.signals.mouse_positions.push({
						x: e.clientX,
						y: e.clientY,
						t: Date.now()
					});
					// Keep only last 10 positions
					if (this.signals.mouse_positions.length > 10) {
						this.signals.mouse_positions.shift();
					}
				}, 50));
			}
			
			// Track scroll
			window.addEventListener('scroll', this.throttle(() => {
				this.signals.scroll_positions.push({
					y: window.scrollY,
					t: Date.now()
				});
				if (this.signals.scroll_positions.length > 10) {
					this.signals.scroll_positions.shift();
				}
			}, 100));
			
			// Track interactions
			document.addEventListener('click', () => {
				this.signals.has_interacted = true;
				this.signals.last_interaction = Date.now();
			});
			
			// Track typing
			document.addEventListener('focusin', (e) => {
				if (this.isInputElement(e.target)) {
					this.signals.is_typing = true;
					this.signals.typing_start = Date.now();
				}
			});
			
			document.addEventListener('focusout', (e) => {
				if (this.isInputElement(e.target)) {
					this.signals.is_typing = false;
				}
			});

			// Track visibility changes (mobile tab switch)
			document.addEventListener('visibilitychange', () => {
				if (document.hidden) {
					this.signals.visibility_hidden = true;
					this.signals.visibility_hidden_time = Date.now();
				} else {
					this.signals.visibility_hidden = false;
				}
			});
		}
		
		getMouseVelocity() {
			const positions = this.signals.mouse_positions;
			if (positions.length < 2) return 0;
			
			const last = positions[positions.length - 1];
			const prev = positions[positions.length - 2];
			
			const dy = last.y - prev.y;
			const dt = (last.t - prev.t) / 1000; // seconds
			
			return dy / dt; // pixels per second (negative = moving up)
		}
		
		getScrollVelocity() {
			const positions = this.signals.scroll_positions;
			if (positions.length < 2) return 0;
			
			const last = positions[positions.length - 1];
			const prev = positions[positions.length - 2];
			
			const dy = last.y - prev.y;
			const dt = (last.t - prev.t) / 1000;
			
			return dy / dt;
		}
		
		isFastScrolling() {
			return Math.abs(this.getScrollVelocity()) > 2000;
		}

		isRapidScrollUp() {
			const velocity = this.getScrollVelocity();
			return velocity < -1000; // Negative = scrolling up
		}
		
		getTimeOnPageSeconds() {
			return Math.floor((Date.now() - this.signals.page_load_time) / 1000);
		}

		getSignalData() {
			const timeOnPage = this.getTimeOnPageSeconds();
			const mouseVelocity = this.getMouseVelocity();
			const exitFromTop = mouseVelocity < -200 && this.signals.mouse_positions.length > 0;

			return {
				mouse_velocity: mouseVelocity,
				scroll_velocity: this.getScrollVelocity(),
				is_fast_scrolling: this.isFastScrolling(),
				is_scrolling_fast: this.isFastScrolling(),
				rapid_scroll_up: this.isRapidScrollUp(),
				exit_from_top: exitFromTop,
				is_typing: this.signals.is_typing,
				has_interacted: this.signals.has_interacted,
				time_on_page: timeOnPage,
				visibility_hidden: this.signals.visibility_hidden || false,
				back_button: false // Would need popstate listener to detect
			};
		}
		
		isInputElement(el) {
			const tag = el.tagName.toLowerCase();
			return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable;
		}
		
		isMobile() {
			return /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
		}
		
		throttle(func, limit) {
			let inThrottle;
			return function(...args) {
				if (!inThrottle) {
					func.apply(this, args);
					inThrottle = true;
					setTimeout(() => inThrottle = false, limit);
				}
			};
		}
	}

	// Initialize signal collector
	window.croSignals = new CROSignalCollector();

	/**
	 * CRO Debugger class for campaign debugging.
	 */
	class CRODebugger {
		constructor() {
			this.logs = [];
		}

		_isOn() {
			return typeof window.CRODebug !== 'undefined' && window.CRODebug.isEnabled();
		}

		log(category, message, data = null) {
			if (!this._isOn()) return;

			const entry = {
				time: new Date().toISOString(),
				category: category,
				message: message,
				data: data
			};

			this.logs.push(entry);
			window.CRODebug.log(`[CRO Debug] ${category}: ${message}`, data || '');
		}

		render() {
			if (!this._isOn()) return;

			const $panel = $('<div>', {
				id: 'cro-debug-panel',
				css: {
					position: 'fixed',
					bottom: '10px',
					right: '10px',
					width: '350px',
					maxHeight: '300px',
					overflow: 'auto',
					background: 'rgba(0,0,0,0.9)',
					color: '#fff',
					padding: '10px',
					fontSize: '11px',
					fontFamily: 'monospace',
					zIndex: 999999,
					borderRadius: '5px'
				}
			});

			const $header = $('<div>', {
				css: { fontWeight: 'bold', marginBottom: '10px', borderBottom: '1px solid #444', paddingBottom: '5px' },
				html: '🔧 Meyvora Convert Debug <button id="cro-debug-close" style="float:right;background:none;border:none;color:#fff;cursor:pointer;">×</button>'
			});

			const $content = $('<div>', { id: 'cro-debug-content' });

			$panel.append($header).append($content);
			$('body').append($panel);

			$('#cro-debug-close').on('click', () => $panel.hide());

			this.updatePanel();
		}

		updatePanel() {
			const $content = $('#cro-debug-content');
			if (!$content.length) return;

			let html = '';
			this.logs.forEach(log => {
				const color = log.category === 'ERROR' ? '#ff6b6b' :
							 log.category === 'SUCCESS' ? '#69db7c' : '#fff';
				html += `<div style="color:${color};margin-bottom:5px;">
					<strong>[${log.category}]</strong> ${log.message}
				</div>`;
			});

			$content.html(html || 'No logs yet...');
		}

		checkCampaign(campaign, context) {
			if (!this._isOn()) return true;

			this.log('CHECK', `Evaluating campaign: ${campaign.name || campaign.id || 'Unknown'}`);

			const rules = campaign.targeting_rules || campaign.targeting || {};

			// Page check
			const pageMatch = this.checkPages(rules.pages || {}, context);
			this.log(pageMatch ? 'PASS' : 'FAIL', `Page targeting: ${pageMatch ? 'matched' : 'not matched'}`);

			// Behavior check
			const behaviorMatch = this.checkBehavior(rules.behavior || {}, context);
			this.log(behaviorMatch ? 'PASS' : 'FAIL', `Behavioral targeting: ${behaviorMatch ? 'matched' : 'not matched'}`);

			// Device check
			const deviceMatch = this.checkDevice(rules.device || {}, context);
			this.log(deviceMatch ? 'PASS' : 'FAIL', `Device targeting: ${deviceMatch ? 'matched' : 'not matched'}`);

			// Frequency check
			const freqMatch = this.checkFrequency(campaign);
			this.log(freqMatch ? 'PASS' : 'FAIL', `Frequency: ${freqMatch ? 'can show' : 'already shown recently'}`);

			const finalResult = pageMatch && behaviorMatch && deviceMatch && freqMatch;
			this.log(finalResult ? 'SUCCESS' : 'SKIP', `Final result: ${finalResult ? 'WILL SHOW' : 'WILL NOT SHOW'}`);

			this.updatePanel();

			return finalResult;
		}

		checkPages(rules, context) {
			const excludedSlugs = Array.isArray(rules.excluded_pages)
				? rules.excluded_pages
				: (Array.isArray(rules.exclude) ? rules.exclude : []);
			if (!rules || (!rules.include && !rules.exclude && !rules.excluded_pages)) {
				return true;
			}

			const pageType = context.page_type || this.getPageType();

			// Check exclusions first (excluded_pages; legacy exclude for cached payloads)
			if (excludedSlugs.length && excludedSlugs.indexOf(pageType) !== -1) {
				return false;
			}

			// Check inclusions
			if (rules.include && Array.isArray(rules.include) && rules.include.length > 0) {
				return rules.include.indexOf(pageType) !== -1;
			}

			return true;
		}

		checkBehavior(rules, context) {
			if (!rules || Object.keys(rules).length === 0) {
				return true;
			}

			const behavior = window.croBehavior || {};
			const timeOnPage = behavior.getTimeOnPage ? behavior.getTimeOnPage() : (context.time_on_page || 0);
			const scrollDepth = behavior.getScrollDepth ? behavior.getScrollDepth() : (context.scroll_depth || 0);
			const hasInteracted = behavior.getHasInteracted ? behavior.getHasInteracted() : (context.has_interacted || false);

			// Min time on page
			if (rules.min_time_on_page && timeOnPage < rules.min_time_on_page) {
				return false;
			}

			// Min scroll depth
			if (rules.min_scroll_depth && scrollDepth < rules.min_scroll_depth) {
				return false;
			}

			// Require interaction
			if (rules.require_interaction && !hasInteracted) {
				return false;
			}

			// Cart status
			if (rules.cart_status && rules.cart_status !== 'any') {
				const cartHasItems = context.cart_has_items || false;
				if (rules.cart_status === 'has_items' && !cartHasItems) {
					return false;
				}
				if (rules.cart_status === 'empty' && cartHasItems) {
					return false;
				}
			}

			// Cart value
			const cartValue = context.cart_value || 0;
			if (rules.cart_min_value && cartValue < rules.cart_min_value) {
				return false;
			}
			if (rules.cart_max_value && cartValue > rules.cart_max_value) {
				return false;
			}

			return true;
		}

		checkDevice(rules, context) {
			if (!rules || Object.keys(rules).length === 0) {
				return true;
			}

			const deviceType = context.device_type || this.getDeviceType();

			// If all devices disabled, default to show
			if (!rules.desktop && !rules.mobile && !rules.tablet) {
				return true;
			}

			if (deviceType === 'desktop' && !rules.desktop) {
				return false;
			}
			if (deviceType === 'mobile' && !rules.mobile) {
				return false;
			}
			if (deviceType === 'tablet' && !rules.tablet) {
				return false;
			}

			return true;
		}

		checkFrequency(campaign) {
			const displayRules = campaign.display_rules || {};
			const frequency = displayRules.frequency;

			if (!frequency || frequency === 'always') {
				return true;
			}

			const campaignId = campaign.id;
			const cookieName = 'cro_campaign_' + campaignId;

			if (frequency === 'once_ever') {
				return !this.getCookie(cookieName);
			}

			if (frequency === 'once_per_session') {
				try {
					return !sessionStorage.getItem(cookieName);
				} catch (e) {
					return !this.getCookie(cookieName + '_session');
				}
			}

			if (frequency === 'once_per_day') {
				const lastShown = this.getCookie(cookieName);
				if (!lastShown) return true;
				const lastDate = new Date(parseInt(lastShown, 10));
				const today = new Date();
				return lastDate.getDate() !== today.getDate() ||
					   lastDate.getMonth() !== today.getMonth() ||
					   lastDate.getFullYear() !== today.getFullYear();
			}

			if (frequency === 'once_per_x_days') {
				const days = displayRules.frequency_days || 7;
				const lastShown = this.getCookie(cookieName);
				if (!lastShown) return true;
				const lastDate = new Date(parseInt(lastShown, 10));
				const now = new Date();
				const diffTime = now - lastDate;
				const diffDays = diffTime / (1000 * 60 * 60 * 24);
				return diffDays >= days;
			}

			return true;
		}

		getPageType() {
			if (typeof wc_add_to_cart_params !== 'undefined') {
				if (window.location.pathname.indexOf('/shop/') !== -1 || window.location.pathname.indexOf('/product-category/') !== -1) {
					return 'shop';
				}
				if (window.location.pathname.indexOf('/product/') !== -1) {
					return 'product';
				}
				if (window.location.pathname.indexOf('/cart/') !== -1) {
					return 'cart';
				}
				if (window.location.pathname.indexOf('/checkout/') !== -1) {
					return 'checkout';
				}
			}
			if (window.location.pathname === '/' || window.location.pathname === '') {
				return 'home';
			}
			return 'other';
		}

		getDeviceType() {
			if (window.innerWidth < 768) {
				return 'mobile';
			}
			if (window.innerWidth < 1024) {
				return 'tablet';
			}
			return 'desktop';
		}

		getCookie(name) {
			const value = '; ' + document.cookie;
			const parts = value.split('; ' + name + '=');
			if (parts.length === 2) {
				return parts.pop().split(';').shift();
			}
			return null;
		}
	}

	// Initialize debugger (panel only when debug mode is on — same gate as CRODebug).
	const croDebugger = new CRODebugger();
	if (window.CRODebug && window.CRODebug.isEnabled()) {
		$(document).ready(function() {
			croDebugger.render();
		});
	}

	// Expose debugger globally for use by popup scripts
	window.croDebugger = croDebugger;

	$(document).ready(function() {
		var sig = window.croSignals;
		var isMobile = sig && typeof sig.isMobile === 'function' && sig.isMobile();

		// Desktop: mouse leaving viewport toward browser chrome.
		if (!isMobile) {
			$(document).on('mouseleave', function(e) {
				if (!exitIntentDetected && e.clientY <= 0) {
					triggerExitIntent('mouse_leave_top');
				}
			});
		}

		// Mobile / tablet: back button, rapid scroll-up near top, tab switch.
		if (isMobile && sig) {
			var lastScrollY = window.scrollY;
			var lastScrollTime = Date.now();
			var throttledScroll = sig.throttle(function() {
				if (exitIntentDetected) {
					return;
				}
				var now = Date.now();
				var delta = lastScrollY - window.scrollY;
				var elapsed = now - lastScrollTime;
				if (delta > window.innerHeight * 0.4 && elapsed < 400 && window.scrollY < 200) {
					triggerMobileExitIntent('rapid_scroll_up');
				}
				lastScrollY = window.scrollY;
				lastScrollTime = now;
			}, 50);
			window.addEventListener('scroll', throttledScroll, { passive: true });

			history.pushState({ cro_exit_guard: true }, '');
			window.addEventListener('popstate', function() {
				if (!exitIntentDetected) {
					history.pushState({ cro_exit_guard: true }, '');
					triggerMobileExitIntent('back_button');
				}
			});

			document.addEventListener('visibilitychange', function() {
				if (!document.hidden || exitIntentDetected) {
					return;
				}
				var t = typeof sig.getTimeOnPageSeconds === 'function' ? sig.getTimeOnPageSeconds() : 0;
				if (t > 10) {
					triggerMobileExitIntent('tab_switch');
				}
			});
		}
	});

})(jQuery);
