/**
 * CRO Popup Controller
 *
 * Main orchestrator that coordinates:
 * - Signal collection
 * - Decision making (via REST API)
 * - Popup rendering
 * - Event tracking
 */
(function () {
  "use strict";

  const CROController = {
    // Configuration
    config: {
      restUrl: "",
      nonce: "",
      ajaxUrl: "",
      publicNonce: "",
      debug: false,
      visitorState: {},
      context: {},
    },

    // State
    state: {
      initialized: false,
      popupShown: false,
      currentCampaign: null,
      signalCollector: null,
      decisionPending: false,
      pageviewId: null,
      lastDecidePayload: null,
      lastSignals: null,
    },

    // Trigger handlers
    triggers: {},

    /**
     * Initialize the controller
     */
    init: function (config) {
      if (this.state.initialized) return;

      this.config = { ...this.config, ...config };

      // Don't run on admin or if disabled
      if (this.shouldSkip()) {
        this.log("Skipping initialization");
        return;
      }

      this.log("Initializing CRO Controller");

      // One pageview ID per page load (for A/B impression dedupe; same ID sent with every decide request)
      this.state.pageviewId = this.getPageviewId();

      // Initialize signal collector
      this.initSignalCollector();

      // Set up trigger listeners
      this.initTriggers();

      // Listen for manual triggers
      this.initManualTriggers();

      this.state.initialized = true;

      // Dispatch ready event
      document.dispatchEvent(new CustomEvent("cro:ready"));
    },

    /**
     * Check if we should skip initialization
     */
    shouldSkip: function () {
      // Skip on admin pages
      if (document.body.classList.contains("wp-admin")) return true;

      // Skip if disabled via config
      if (this.config.disabled) return true;

      // Skip on checkout (always). Context uses page_type from cro_get_request_context (not page.type).
      var ctx = this.config.context || {};
      if (ctx.page_type === "checkout") return true;

      return false;
    },

    /**
     * Generate a stable pageview ID for this page load (used for A/B impression dedupe).
     * One ID per load; same ID sent with every decide request on this page.
     */
    getPageviewId: function () {
      if (typeof crypto !== "undefined" && crypto.randomUUID) {
        return crypto.randomUUID();
      }
      return "pv-" + Date.now() + "-" + Math.random().toString(36).slice(2, 11);
    },

    /**
     * Initialize signal collector
     */
    initSignalCollector: function () {
      // Reuse the singleton from cro-signals.js instead of creating a second instance
      if (typeof window.croSignals !== "undefined") {
        this.state.signalCollector = window.croSignals;
      } else if (typeof CROSignalCollector !== "undefined") {
        this.state.signalCollector = new CROSignalCollector();
      }

      // Listen for signal dispatches from the collector
      document.addEventListener("cro:signal", (e) => {
        if (e.detail && e.detail.signals) {
          this.state.lastSignals = e.detail.signals;
          this.onSignal(e.detail);
        }
      });
    },

    /**
     * Initialize trigger listeners
     */
    initTriggers: function () {
      const self = this;

      // Exit Intent Trigger
      this.triggers.exit_intent = {
        active: false,
        init: function () {
          if (this.active) return;
          this.active = true;

          // Desktop: mouse leave from top
          document.addEventListener("mouseout", function (e) {
            if (e.clientY <= 0 && !e.relatedTarget) {
              self.onTrigger("exit_intent", { type: "mouse_exit" });
            }
          });

          // Mobile: handled via signals (scroll up, back button)
        },
      };

      // Scroll Trigger
      this.triggers.scroll = {
        active: false,
        threshold: 50,
        triggered: false,
        init: function (threshold) {
          if (this.active) return;
          this.active = true;
          this.threshold = threshold || 50;

          window.addEventListener(
            "scroll",
            self.throttle(() => {
              if (this.triggered) return;

              const scrollPercent = self.getScrollPercent();
              if (scrollPercent >= this.threshold) {
                this.triggered = true;
                self.onTrigger("scroll", { depth: scrollPercent });
              }
            }, 100)
          );
        },
      };

      // Time Trigger
      this.triggers.time = {
        active: false,
        timeout: null,
        init: function (seconds) {
          if (this.active) return;
          this.active = true;

          this.timeout = setTimeout(() => {
            self.onTrigger("time", { seconds: seconds });
          }, seconds * 1000);
        },
      };

      // Inactivity Trigger
      this.triggers.inactivity = {
        active: false,
        timeout: null,
        lastActivity: Date.now(),
        init: function (seconds) {
          if (this.active) return;
          this.active = true;

          const checkInactivity = () => {
            const idle = (Date.now() - this.lastActivity) / 1000;
            if (idle >= seconds) {
              self.onTrigger("inactivity", {
                idle_seconds: Math.floor(idle),
              });
            }
            // Always reschedule — a campaign requiring longer idle may still match later.
            if (!self.state.popupShown) {
              this.timeout = setTimeout(checkInactivity, 5000);
            }
          };

          // Reset on activity
          ["mousemove", "keydown", "scroll", "click", "touchstart"].forEach(
            (event) => {
              document.addEventListener(
                event,
                () => {
                  this.lastActivity = Date.now();
                },
                { passive: true }
              );
            }
          );

          this.timeout = setTimeout(checkInactivity, 1000);
        },
      };

      // Click Trigger
      this.triggers.click = {
        active: false,
        init: function (selector) {
          if (this.active || !selector) return;
          this.active = true;

          document.addEventListener("click", function (e) {
            if (e.target.matches(selector) || e.target.closest(selector)) {
              e.preventDefault();
              self.onTrigger("click", { selector: selector });
            }
          });
        },
      };

      // Initialize default trigger (exit intent)
      this.triggers.exit_intent.init();

      // Fire page_load once so campaigns set to "show on load" or time with short delay get evaluated
      setTimeout(function () {
        if (!self.state.popupShown && !self.state.decisionPending) {
          self.onTrigger("page_load", {});
        }
      }, 500);

      // Time trigger: repeat every 1s so checkpoints are not lost while decisionPending (e.g. slow page_load /decide).
      self._initTime = Date.now();
      var timeThresholds = [1, 3, 10, 30, 60];
      var timeThresholdsFired = {};
      self._timeInterval = setInterval(function () {
        if (self.state.popupShown) {
          clearInterval(self._timeInterval);
          self._timeInterval = null;
          return;
        }
        var elapsed =
          typeof window.croBehavior !== "undefined"
            ? window.croBehavior.getTimeOnPage()
            : Math.floor((Date.now() - (self._initTime || Date.now())) / 1000);
        timeThresholds.forEach(function (threshold) {
          if (
            elapsed >= threshold &&
            !timeThresholdsFired[threshold] &&
            !self.state.decisionPending
          ) {
            timeThresholdsFired[threshold] = true;
            self.onTrigger("time", { seconds: elapsed });
          }
        });
      }, 1000);

      // Activate scroll trigger: fire when user reaches 25%, 50%, 75%, 100% so scroll-depth campaigns get evaluated
      var scrollFired = {};
      window.addEventListener(
        "scroll",
        self.throttle(function () {
          if (self.state.popupShown || self.state.decisionPending) return;
          var pct = self.getScrollPercent();
          [25, 50, 75, 100].forEach(function (threshold) {
            if (pct >= threshold && !scrollFired[threshold]) {
              scrollFired[threshold] = true;
              self.onTrigger("scroll", { depth: pct });
            }
          });
        }, 200),
        { passive: true }
      );

      // Inactivity trigger: 30s is the floor; server filter_eligible_by_trigger enforces per-campaign idle_seconds.
      this.triggers.inactivity.init(30);
    },

    /**
     * Initialize manual trigger points
     */
    initManualTriggers: function () {
      const self = this;

      // Allow manual trigger via data attributes
      document.querySelectorAll("[data-cro-trigger]").forEach((el) => {
        el.addEventListener("click", function (e) {
          const campaignId = this.dataset.croCampaign;
          if (campaignId) {
            e.preventDefault();
            self.showCampaign(parseInt(campaignId));
          }
        });
      });

      // Global trigger function
      window.croTrigger = function (campaignId) {
        self.showCampaign(campaignId);
      };
    },

    /**
     * Handle signal from collector
     */
    onSignal: function (signalData) {
      // e.detail = { signals: { exit_mouse: bool, scroll_up_fast: bool, ... } }
      var signals =
        signalData && signalData.signals ? signalData.signals : signalData;
      if (!signals || typeof signals !== "object") {
        return;
      }

      var exitSignals = [
        "exit_mouse",
        "scroll_up_fast",
        "tab_blur",
        "back_button",
      ];
      for (var i = 0; i < exitSignals.length; i++) {
        if (signals[exitSignals[i]]) {
          this.log("Signal received:", exitSignals[i]);
          this.onTrigger("exit_intent", {
            type: exitSignals[i],
            signals: signals,
          });
          return;
        }
      }
    },

    /**
     * Handle trigger event
     */
    onTrigger: function (triggerType, data) {
      if (this.state.popupShown || this.state.decisionPending) {
        this.log("Trigger ignored - popup shown or decision pending");
        return;
      }

      this.log("Trigger fired:", triggerType, data);

      // Request decision from server
      this.requestDecision(triggerType, data);
    },

    /**
     * Get REST API base URL (absolute). Uses config.restUrl or falls back to /wp-json/.
     */
    getRestUrl: function () {
      var base = this.config.restUrl || "";
      if (!base || base.indexOf("wp-json") === -1) {
        var origin =
          typeof window !== "undefined" && window.location
            ? window.location.origin
            : "";
        base = origin ? origin + "/wp-json/" : "/wp-json/";
      }
      return base.replace(/\/?$/, "/");
    },

    /**
     * Request decision from server
     */
    requestDecision: function (triggerType, triggerData) {
      this.state.decisionPending = true;

      // Enrich trigger_data with live behavioral metrics from croBehavior (cro-public.js)
      var enrichedTriggerData = Object.assign({}, triggerData || {});
      if (typeof window.croBehavior !== "undefined") {
        enrichedTriggerData.seconds = window.croBehavior.getTimeOnPage();
        enrichedTriggerData.depth = window.croBehavior.getScrollDepth();
        if (typeof enrichedTriggerData.idle_seconds === "undefined") {
          enrichedTriggerData.idle_seconds = 0;
        }
      }

      const rawSignals = this.state.signalCollector
        ? this.state.signalCollector.getSignals()
        : {};
      const mergedSignals = Object.assign(
        {},
        rawSignals,
        this.state.lastSignals || {}
      );
      const signals = mergedSignals;

      const requestData = {
        trigger_type: triggerType,
        trigger_data: enrichedTriggerData,
        signals: signals,
        context: this.config.context,
        pageview_id: this.state.pageviewId || undefined,
      };

      this.state.lastDecidePayload = requestData;

      this.log("Requesting decision:", requestData);

      fetch(this.getRestUrl() + "cro/v1/decide", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(requestData),
        credentials: "same-origin",
      })
        .then(function (response) {
          return response.json().then(function (data) {
            return { ok: response.ok, status: response.status, data: data };
          });
        })
        .then(
          function (result) {
            this.state.decisionPending = false;
            var decision = result.data;
            if (!result.ok) {
              this.log(
                "Decide HTTP " + result.status,
                decision && decision.code ? decision.code : "",
                decision && decision.message ? decision.message : ""
              );
              return;
            }
            if (
              decision &&
              decision.code &&
              typeof decision.show === "undefined" &&
              (decision.message || decision.data)
            ) {
              this.log("Decide REST error:", decision.code, decision.message || "");
              return;
            }
            this.handleDecision(decision);
          }.bind(this)
        )
        .catch((error) => {
          this.state.decisionPending = false;
          this.log("Decision error:", error);
        });
    },

    /**
     * Handle decision response
     */
    handleDecision: function (decision) {
      this.log("Decision received:", decision);

      this.state.decisionPending = false;

      if (decision.show && decision.campaign) {
        if (this.state.popupShown) {
          this.log("Popup already shown, skipping");
          return;
        }
        if (typeof CROPopup === "undefined") {
          this.log(
            "CROPopup not available – ensure cro-popup.js is loaded before cro-controller"
          );
          this.state.decisionPending = false;
          return;
        }
        this.state.popupShown = true;
        try {
          var shown = this.showPopup(decision.campaign);
          if (shown === false) {
            this.state.popupShown = false;
          }
        } catch (err) {
          this.log("Error showing popup:", err);
          this.state.popupShown = false;
        }
        this.state.decisionPending = false;
      } else {
        this.log("Decision: do not show -", decision.reason);
      }

      // Debug mode
      if (this.config.debug && decision.debug) {
        console.group("CRO Decision Debug");
        console.log("Decision:", decision);
        console.log("Debug Log:", decision.debug);
        console.groupEnd();
      }
    },

    /**
     * POST dismiss to persist visitor state and receive fallback campaign + delay.
     */
    recordDismissAjax: function (campaignId) {
      const ajaxUrl = this.config.ajaxUrl || "";
      const nonce = this.config.publicNonce || "";
      if (!ajaxUrl || !nonce) {
        return Promise.resolve(null);
      }
      const fd = new FormData();
      fd.append("action", "cro_record_dismiss");
      fd.append("nonce", nonce);
      fd.append("campaign_id", String(campaignId));
      return fetch(ajaxUrl, {
        method: "POST",
        body: fd,
        credentials: "same-origin",
      }).then((r) => r.json());
    },

    /**
     * Run server decide for a single fallback campaign (after dismiss delay).
     */
    runDecideFallback: function (fallbackCampaignId) {
      const ajaxUrl = this.config.ajaxUrl || "";
      const nonce = this.config.publicNonce || "";
      if (!ajaxUrl || !nonce) {
        return;
      }
      const ctx = this.state.lastDecidePayload || {};
      const fd = new FormData();
      fd.append("action", "cro_decide_fallback");
      fd.append("nonce", nonce);
      fd.append("campaign_id", String(fallbackCampaignId));
      fd.append("decision_context", JSON.stringify(ctx));
      fetch(ajaxUrl, {
        method: "POST",
        body: fd,
        credentials: "same-origin",
      })
        .then((r) => r.json())
        .then((json) => {
          if (!json || !json.success || !json.data) {
            return;
          }
          this.handleDecision(json.data);
        })
        .catch((err) => this.log("Fallback decide error:", err));
    },

    /**
     * After dismiss: server records state, track event, optional delayed fallback popup.
     */
    onCampaignDismissed: function (campaignId) {
      const dispatchDismissed = () => {
        document.dispatchEvent(
          new CustomEvent("cro:campaign_dismissed", {
            detail: { campaignId: campaignId },
          })
        );
      };

      this.recordDismissAjax(campaignId)
        .then((json) => {
          this.trackEvent("dismiss", campaignId);
          dispatchDismissed();
          const d = json && json.success && json.data ? json.data : {};
          const fb = d.fallback_campaign_id;
          const delaySec = parseInt(d.fallback_delay_seconds, 10);
          if (fb && !isNaN(delaySec) && delaySec >= 0) {
            window.setTimeout(() => {
              this.runDecideFallback(fb);
            }, delaySec * 1000);
          }
        })
        .catch(() => {
          this.trackEvent("dismiss", campaignId);
          dispatchDismissed();
        });
    },

    /**
     * Show a specific campaign by ID
     */
    showCampaign: function (campaignId) {
      fetch(this.getRestUrl() + "cro/v1/campaign/" + campaignId, {
        headers: {
          "X-WP-Nonce": this.config.nonce,
        },
      })
        .then((response) => response.json())
        .then((campaign) => {
          if (campaign && campaign.id) {
            this.state.popupShown = true;
            var shown = this.showPopup(campaign);
            if (shown === false) {
              this.state.popupShown = false;
            }
          }
        })
        .catch((error) => {
          this.log("Error loading campaign:", error);
        });
    },

    /**
     * Show popup (popupShown is already set by handleDecision to prevent race).
     */
    showPopup: function (campaign) {
      if (
        typeof window.croCrossTab !== "undefined" &&
        campaign &&
        campaign.id &&
        window.croCrossTab.wasShownInAnyTab(campaign.id)
      ) {
        this.log(
          "Campaign already shown in another tab, skipping:",
          campaign.id
        );
        return false;
      }

      this.state.currentCampaign = campaign;

      this.log("Showing popup:", campaign.name);

      // Ensure only one popup: remove ANY existing overlay and popup (including not-yet-visible)
      document
        .querySelectorAll("body > .cro-overlay, body > .cro-popup")
        .forEach(function (el) {
          el.remove();
        });
      document.body.classList.remove("cro-popup-open");

      // Create popup instance
      const popup = new CROPopup(campaign, {
        onShow: () => {
          this.trackEvent("impression", campaign.id);
          document.dispatchEvent(
            new CustomEvent("cro:campaign_shown", {
              detail: { campaignId: campaign.id, campaignName: campaign.name },
            })
          );
        },
        onClose: (reason) => {
          this.state.popupShown = false;
          this.state.currentCampaign = null;

          if (reason === "dismiss") {
            this.onCampaignDismissed(campaign.id);
          }
        },
        onConvert: (type, data) => {
          this.trackEvent("conversion", campaign.id, { type, data });
          document.dispatchEvent(
            new CustomEvent("cro:campaign_converted", {
              detail: { campaignId: campaign.id, conversionType: type },
            })
          );
        },
        onEmailCapture: (email) => {
          this.trackEvent("email_capture", campaign.id, { email });
          document.dispatchEvent(
            new CustomEvent("cro:email_captured", {
              detail: { email },
            })
          );
        },
      });

      popup.show();

      if (typeof window.croCrossTab !== "undefined" && campaign && campaign.id) {
        window.croCrossTab.notifyCampaignShown(campaign.id);
      }

      return true;
    },

    /**
     * Track event
     */
    trackEvent: function (eventType, campaignId, data = {}) {
      const eventData = {
        event_type: eventType,
        campaign_id: campaignId,
        page_url: window.location.href,
        timestamp: Date.now(),
        ...data,
      };

      // Send to server (Blob with application/json so server parses body; sendBeacon with string uses text/plain)
      const blob = new Blob([JSON.stringify(eventData)], {
        type: "application/json",
      });
      navigator.sendBeacon(this.getRestUrl() + "cro/v1/track", blob);
    },

    /**
     * Utility: Get scroll percentage
     */
    getScrollPercent: function () {
      const h = document.documentElement;
      const b = document.body;
      const st = "scrollTop";
      const sh = "scrollHeight";
      return ((h[st] || b[st]) / ((h[sh] || b[sh]) - h.clientHeight)) * 100;
    },

    /**
     * Utility: Throttle function
     */
    throttle: function (func, limit) {
      let inThrottle;
      return function (...args) {
        if (!inThrottle) {
          func.apply(this, args);
          inThrottle = true;
          setTimeout(() => (inThrottle = false), limit);
        }
      };
    },

    /**
     * Utility: Log with prefix
     */
    log: function (...args) {
      if (this.config.debug) {
        console.log("[CRO]", ...args);
      }
    },
  };

  // Expose globally
  window.CROController = CROController;

  /**
   * Boot when DOM is ready. If this script loads after DOMContentLoaded (defer/async optimizers),
   * listening only on DOMContentLoaded would skip init and /cro/v1/decide would never run.
   */
  function bootCroController() {
    if (window.croConfig && !CROController.state.initialized) {
      CROController.init(window.croConfig);
    }
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootCroController);
  } else {
    bootCroController();
  }

  // Fallback: init when cro:init fires (cro-core.js dispatches after requestIdleCallback; handles async/defer).
  window.addEventListener("cro:init", function () {
    if (window.croConfig && !CROController.state.initialized) {
      CROController.init(window.croConfig);
    }
  });
})();
