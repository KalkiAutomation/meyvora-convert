/**
 * CRO Signal Collector – JavaScript signal collection for intent scoring
 *
 * Tracks: exit intent (mouse up), fast scroll up, scroll depth, idle time,
 * time on page, interactions, typing state, tab blur, back button.
 * Dispatches 'cro:signal' when signals change. Uses throttling for performance.
 *
 * @package Meyvora_Convert
 */
(function() {
	'use strict';

	/**
	 * CROSignalCollector – collects UX signals for exit-intent and intent scoring.
	 */
	class CROSignalCollector {
		constructor() {
			this.pageLoadTime = Date.now();
			this.lastInteractionTime = Date.now();
			this.interactionCount = 0;
			this.isTyping = false;
			this.tabBlurTime = null;
			this.backButtonCount = 0;

			this.mousePositions = [];
			this.scrollPositions = [];
			this.fastScrollUpStart = null;

			this.scrollDepth = 0;
			this.maxScrollDepth = 0;

			this.dispatchScheduled = false;
			this.lastDispatchedSignals = null;

			this.init();
		}

		init() {
			var self = this;

			// 1. Mouse movement – exit intent (fast move toward top)
			if (!this.isMobile()) {
				document.addEventListener('mousemove', this.throttle(function(e) {
					var t = Date.now();
					self.mousePositions.push({ x: e.clientX, y: e.clientY, t: t });
					if (self.mousePositions.length > 15) {
						self.mousePositions.shift();
					}
					self.scheduleDispatch();
				}, 50));
			}

			// 2. Scroll – velocity, fast scroll up, scroll depth
			window.addEventListener('scroll', this.throttle(function() {
				var t = Date.now();
				var y = window.pageYOffset || document.documentElement.scrollTop;
				self.scrollPositions.push({ y: y, t: t });
				if (self.scrollPositions.length > 20) {
					self.scrollPositions.shift();
				}
				self.updateScrollDepth();
				self.scheduleDispatch();
			}, 100), { passive: true });

			// 3. Idle time + 5. Interactions – clicks, keys, touches
			function recordInteraction() {
				self.lastInteractionTime = Date.now();
				self.interactionCount += 1;
				self.scheduleDispatch();
			}
			document.addEventListener('click', recordInteraction);
			document.addEventListener('keydown', recordInteraction);
			document.addEventListener('touchstart', recordInteraction, { passive: true });

			// 4. Time on page: derived in getSignals from pageLoadTime
			// 5. (above)
			// 6. Typing state
			document.addEventListener('focusin', function(e) {
				if (self.isInputElement(e.target)) {
					self.isTyping = true;
					self.scheduleDispatch();
				}
			});
			document.addEventListener('focusout', function(e) {
				if (self.isInputElement(e.target)) {
					self.isTyping = false;
					self.scheduleDispatch();
				}
			});

			// 7. Tab blur (visibility change)
			document.addEventListener('visibilitychange', function() {
				if (document.hidden) {
					self.tabBlurTime = Date.now();
				} else {
					self.tabBlurTime = null;
				}
				self.scheduleDispatch();
			});

			// 8. Back button (popstate)
			window.addEventListener('popstate', function() {
				self.backButtonCount += 1;
				self.scheduleDispatch();
			});
		}

		updateScrollDepth() {
			var winH = window.innerHeight;
			var docH = document.documentElement.scrollHeight;
			var top = window.pageYOffset || document.documentElement.scrollTop;
			var scrollable = Math.max(0, docH - winH);
			if (scrollable <= 0) {
				this.scrollDepth = 100;
			} else {
				this.scrollDepth = Math.round((top / scrollable) * 100);
			}
			this.scrollDepth = Math.max(0, Math.min(100, this.scrollDepth));
			this.maxScrollDepth = Math.max(this.maxScrollDepth, this.scrollDepth);
		}

		getMouseVelocity() {
			var p = this.mousePositions;
			if (p.length < 2) return 0;
			var last = p[p.length - 1];
			var prev = p[p.length - 2];
			var dy = last.y - prev.y;
			var dt = (last.t - prev.t) / 1000;
			return dt > 0 ? dy / dt : 0;
		}

		getMouseY() {
			var p = this.mousePositions;
			return p.length > 0 ? p[p.length - 1].y : null;
		}

		getScrollVelocity() {
			var p = this.scrollPositions;
			if (p.length < 2) return 0;
			var last = p[p.length - 1];
			var prev = p[p.length - 2];
			var dy = last.y - prev.y;
			var dt = (last.t - prev.t) / 1000;
			return dt > 0 ? dy / dt : 0;
		}

		getScrollUpFast() {
			var v = this.getScrollVelocity();
			var threshold = -800;
			if (v < threshold) {
				if (!this.fastScrollUpStart) {
					this.fastScrollUpStart = Date.now();
				}
				var duration = (Date.now() - this.fastScrollUpStart) / 1000;
				return { velocity: v, duration: duration };
			}
			this.fastScrollUpStart = null;
			return null;
		}

		getIdleSeconds() {
			return (Date.now() - this.lastInteractionTime) / 1000;
		}

		getTimeOnPageSeconds() {
			return (Date.now() - this.pageLoadTime) / 1000;
		}

		isInputElement(el) {
			if (!el || !el.tagName) return false;
			var tag = el.tagName.toLowerCase();
			return tag === 'input' || tag === 'textarea' || tag === 'select' || !!el.isContentEditable;
		}

		isMobile() {
			return /Android|iPhone|iPad|iPod|webOS|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
		}

		throttle(fn, limit) {
			var self = this;
			var lastRun = 0;
			var timer = null;
			return function() {
				var args = arguments;
				var now = Date.now();
				var elapsed = now - lastRun;
				if (elapsed >= limit) {
					lastRun = now;
					return fn.apply(self, args);
				}
				if (!timer) {
					timer = setTimeout(function() {
						timer = null;
						lastRun = Date.now();
						fn.apply(self, args);
					}, limit - elapsed);
				}
			};
		}

		scheduleDispatch() {
			var self = this;
			if (this.dispatchScheduled) return;
			this.dispatchScheduled = true;
			requestAnimationFrame(function() {
				self.dispatchScheduled = false;
				self.dispatchSignal();
			});
		}

		dispatchSignal() {
			var signals = this.getSignals();
			var prev = this.lastDispatchedSignals;
			if (prev && this.signalsEqual(prev, signals)) {
				return;
			}
			this.lastDispatchedSignals = JSON.parse(JSON.stringify(signals));
			try {
				document.dispatchEvent(new CustomEvent('cro:signal', {
					detail: { signals: signals },
					bubbles: true
				}));
			} catch (e) {}
		}

		signalsEqual(a, b) {
			var keys = ['exit_mouse', 'scroll_up_fast', 'scroll_depth', 'idle_time', 'time_on_page', 'interaction', 'tab_blur', 'back_button', 'is_typing'];
			for (var i = 0; i < keys.length; i++) {
				var k = keys[i];
				var av = a[k];
				var bv = b[k];
				if (typeof av === 'object' && av !== null && typeof bv === 'object' && bv !== null) {
					if (JSON.stringify(av) !== JSON.stringify(bv)) return false;
				} else if (av !== bv) {
					return false;
				}
			}
			return true;
		}

		/**
		 * Return current signals for intent scoring / decision engine.
		 *
		 * @return {Object} Signals: exit_mouse, scroll_up_fast, scroll_depth, idle_time, time_on_page, interaction, tab_blur, back_button, is_typing
		 */
		getSignals() {
			var mouseY = this.getMouseY();
			var mouseVelocity = this.getMouseVelocity();
			var exitMouse = null;
			if (mouseY !== null && mouseVelocity < -200) {
				exitMouse = { y: mouseY, velocity: mouseVelocity };
			}

			var scrollUpFast = this.getScrollUpFast();

			return {
				exit_mouse: exitMouse,
				scroll_up_fast: scrollUpFast,
				scroll_depth: this.maxScrollDepth,
				idle_time: Math.floor(this.getIdleSeconds()),
				time_on_page: Math.floor(this.getTimeOnPageSeconds()),
				interaction: this.interactionCount,
				tab_blur: !!document.hidden || (this.tabBlurTime !== null),
				back_button: this.backButtonCount,
				is_typing: this.isTyping,
				// Aliases for backend intent scorer
				mouse_velocity: mouseVelocity,
				scroll_velocity: this.getScrollVelocity(),
				exit_from_top: !!exitMouse,
				visibility_hidden: document.hidden,
				has_interacted: this.interactionCount > 0
			};
		}
	}

	// Initialize and expose
	var collector = new CROSignalCollector();
	window.CROSignalCollector = CROSignalCollector;
	window.croSignals = collector;
	window.croSignalCollector = collector;

})();
