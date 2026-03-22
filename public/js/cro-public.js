/**
 * Meyvora Convert – public behavioral tracking
 *
 * @package Meyvora_Convert
 */
(function() {
	'use strict';

	function reportError(error) {
		try {
			if (window.croConfig && window.croConfig.errorReporting && window.croConfig.ajaxUrl) {
				fetch(window.croConfig.ajaxUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: 'action=cro_log_error&nonce=' + encodeURIComponent((window.croConfig.logErrorNonce || window.croConfig.nonce || '')) +
						'&data=' + encodeURIComponent(JSON.stringify({
							message: (error && error.message) ? String(error.message) : String(error),
							stack: (error && error.stack) ? String(error.stack) : '',
							url: window.location ? window.location.href : '',
							userAgent: navigator && navigator.userAgent ? navigator.userAgent : ''
						}))
				}).catch(function() {});
			}
		} catch (e) {}
	}

	function initCROToolkit() {
		/**
	 * Base class for CRO components – tracks listeners and observers for cleanup (memory leak prevention).
	 */
	class CROBase {
		constructor() {
			this.eventListeners = [];
			this.observers = [];
		}

		addListener(element, event, handler, options) {
			if (!element || !event || !handler) return;
			element.addEventListener(event, handler, options || false);
			this.eventListeners.push({ element: element, event: event, handler: handler, options: options });
		}

		addObserver(observer) {
			if (observer) {
				this.observers.push(observer);
			}
		}

		destroy() {
			this.eventListeners.forEach(function(entry) {
				try {
					entry.element.removeEventListener(entry.event, entry.handler, entry.options || false);
				} catch (e) {}
			});
			this.eventListeners = [];
			this.observers.forEach(function(observer) {
				try {
					if (observer && typeof observer.disconnect === 'function') {
						observer.disconnect();
					}
				} catch (e) {}
			});
			this.observers = [];
		}
	}

	window.CROBase = CROBase;

	/**
	 * Tracks time on page, scroll depth, and user interaction for campaign targeting.
	 */
	class CROBehaviorTracker {
		constructor() {
			this.startTime = Date.now();
			this.maxScrollDepth = 0;
			this.hasInteracted = false;
			this.scrollDepth = 0;

			this.init();
		}

		init() {
			// Track scroll depth (throttled).
			window.addEventListener('scroll', this.throttle(() => {
				this.updateScrollDepth();
			}, 100), { passive: true });

			// Track interactions.
			document.addEventListener('click', () => {
				this.hasInteracted = true;
			});

			document.addEventListener('keydown', () => {
				this.hasInteracted = true;
			});
		}

		updateScrollDepth() {
			const windowHeight = window.innerHeight;
			const documentHeight = document.documentElement.scrollHeight;
			const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
			const scrollable = documentHeight - windowHeight;

			if (scrollable <= 0) {
				this.scrollDepth = 100;
			} else {
				this.scrollDepth = Math.round((scrollTop / scrollable) * 100);
			}

			this.scrollDepth = Math.max(0, Math.min(100, this.scrollDepth));
			this.maxScrollDepth = Math.max(this.maxScrollDepth, this.scrollDepth);
		}

		getTimeOnPage() {
			return Math.floor((Date.now() - this.startTime) / 1000);
		}

		getScrollDepth() {
			return this.maxScrollDepth;
		}

		getHasInteracted() {
			return this.hasInteracted;
		}

		getContext() {
			return {
				time_on_page: this.getTimeOnPage(),
				scroll_depth: this.getScrollDepth(),
				has_interacted: this.getHasInteracted()
			};
		}

		throttle(func, limit) {
			let inThrottle;
			return (...args) => {
				if (!inThrottle) {
					func.apply(this, args);
					inThrottle = true;
					setTimeout(() => (inThrottle = false), limit);
				}
			};
		}
	}

	// Initialize and expose for exit intent / popup scripts.
	window.croBehavior = new CROBehaviorTracker();

	/**
	 * Cross-tab deduplication: avoid showing same campaign in multiple tabs.
	 */
	class CROCrossTabSync {
		constructor() {
			this.channel = null;
			this.shownCampaigns = new Set();
			this.init();
		}

		init() {
			// Use BroadcastChannel if available.
			if (typeof BroadcastChannel !== 'undefined') {
				this.channel = new BroadcastChannel('meyvora_convert');
				this.channel.onmessage = (e) => this.handleMessage(e);
			} else {
				// Fallback to storage events (other tabs only).
				window.addEventListener('storage', (e) => {
					if (e.key === 'cro_shown_campaign' && e.newValue) {
						this.shownCampaigns.add(String(e.newValue));
					}
				});
			}

			// Load from sessionStorage.
			try {
				const stored = sessionStorage.getItem('cro_shown_campaigns');
				if (stored) {
					JSON.parse(stored).forEach((id) => this.shownCampaigns.add(String(id)));
				}
			} catch (err) {
				// Ignore if sessionStorage unavailable.
			}
		}

		handleMessage(event) {
			if (event.data && event.data.type === 'campaign_shown') {
				this.shownCampaigns.add(String(event.data.campaignId));
			}
		}

		notifyCampaignShown(campaignId) {
			const id = String(campaignId);
			this.shownCampaigns.add(id);

			// Persist to sessionStorage.
			try {
				sessionStorage.setItem('cro_shown_campaigns', JSON.stringify([...this.shownCampaigns]));
			} catch (err) {
				// Ignore.
			}

			// Broadcast to other tabs.
			if (this.channel) {
				this.channel.postMessage({
					type: 'campaign_shown',
					campaignId: id
				});
			} else {
				// Fallback: use localStorage to trigger storage event in other tabs.
				try {
					localStorage.setItem('cro_shown_campaign', id);
					localStorage.removeItem('cro_shown_campaign');
				} catch (err) {
					// Ignore.
				}
			}
		}

		wasShownInAnyTab(campaignId) {
			return this.shownCampaigns.has(String(campaignId));
		}
	}

	window.croCrossTab = new CROCrossTabSync();
	}

	try {
		initCROToolkit();
	} catch (err) {
		console.error('Meyvora Convert Error:', err);
		reportError(err);
	}
})();
