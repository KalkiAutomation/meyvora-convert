/**
 * Popup functionality – dispatches CustomEvents for developers to integrate
 * (cro:campaign_shown, cro:campaign_dismissed, cro:campaign_converted, cro:email_captured,
 * cro:coupon_copied, cro:coupon_applied).
 * Includes CROPopup class for controller-driven popups and event handlers for server-rendered popups.
 *
 * @package Meyvora_Convert
 */

/**
 * CROPopup Class - Renders and manages popup lifecycle
 */
class CROPopup {
  constructor(campaign, callbacks = {}) {
    this.campaign = campaign;
    this.callbacks = callbacks;
    this.element = null;
    this.overlay = null;
    this.accessibility = null;
    this.countdownInterval = null;
  }

  show() {
    this.render();
    this.bindEvents();
    this.initCountdown();
    this.initAccessibility();

    // Remove any other overlay/popup so only one is ever visible
    document
      .querySelectorAll("body > .cro-overlay, body > .cro-popup")
      .forEach((el) => {
        if (el !== this.overlay && el !== this.element) el.remove();
      });
    document.body.classList.remove("cro-popup-open");

    document.body.appendChild(this.overlay);
    document.body.appendChild(this.element);
    document.body.classList.add("cro-popup-open");

    requestAnimationFrame(() => {
      this.overlay.classList.add("cro-overlay--visible");
      this.element.classList.add("cro-popup--visible");
    });

    if (this.callbacks.onShow) {
      this.callbacks.onShow();
    }
  }

  close(reason = "close") {
    if (this._closing) return;
    this._closing = true;

    this.overlay.style.pointerEvents = "none";
    this.element.style.pointerEvents = "none";

    if (this.escHandler) {
      document.removeEventListener("keydown", this.escHandler);
      this.escHandler = null;
    }

    const self = this;
    requestAnimationFrame(function () {
      self.element.classList.add("cro-popup--closing");
      self.overlay.classList.remove("cro-overlay--visible");
      self.element.classList.remove("cro-popup--visible");
    });

    setTimeout(() => {
      self.destroy();
      if (self.callbacks.onClose) {
        self.callbacks.onClose(reason);
      }
    }, 300);
  }

  destroy() {
    if (this.countdownInterval) {
      clearInterval(this.countdownInterval);
      this.countdownInterval = null;
    }
    if (this._trapHandler && this.element) {
      this.element.removeEventListener("keydown", this._trapHandler);
      this._trapHandler = null;
    }
    if (this._copyCouponHandler && this.element) {
      this.element.removeEventListener("click", this._copyCouponHandler);
      this._copyCouponHandler = null;
    }
    if (this._previouslyFocused && this._previouslyFocused.focus) {
      try {
        this._previouslyFocused.focus();
      } catch (e) {}
      this._previouslyFocused = null;
    }
    if (this.escHandler) {
      document.removeEventListener("keydown", this.escHandler);
      this.escHandler = null;
    }
    if (this.overlay && this.overlay.parentNode) {
      this.overlay.parentNode.removeChild(this.overlay);
      this.overlay = null;
    }
    if (this.element && this.element.parentNode) {
      this.element.parentNode.removeChild(this.element);
      this.element = null;
    }
    document.body.classList.remove("cro-popup-open");
  }

  render() {
    const content = this.campaign.content || {};
    const styling = this.campaign.styling || {};
    const templateRaw =
      this.campaign.template || this.campaign.template_type || "centered";
    let template = String(templateRaw).replace(/\s+/g, "-").replace(/_/g, "-");
    // Match PHP/preview class names: centered-image-left -> image-left, centered-image-right -> image-right
    if (template === "centered-image-left") template = "image-left";
    if (template === "centered-image-right") template = "image-right";

    this.overlay = document.createElement("div");
    this.overlay.className = "cro-overlay";
    const overlayColor = styling.overlay_color || "#000000";
    const overlayOpacity =
      (styling.overlay_opacity != null ? styling.overlay_opacity : 50) / 100;
    this.overlay.style.backgroundColor =
      overlayColor.indexOf("rgba") === 0
        ? overlayColor
        : "rgba(0,0,0," + overlayOpacity + ")";

    this.element = document.createElement("div");
    this.element.className =
      "cro-popup cro-popup--" +
      template +
      " cro-popup--" +
      (styling.size || "medium");
    this.element.setAttribute("role", "dialog");
    this.element.setAttribute("aria-modal", "true");
    this.element.dataset.campaignId = this.campaign.id;

    if (styling.animation && styling.animation !== "none") {
      this.element.classList.add("cro-animate--" + styling.animation);
    }

    if (styling.bg_color) {
      this.element.style.backgroundColor = styling.bg_color;
    }
    if (styling.text_color) {
      this.element.style.color = styling.text_color;
    }
    if (styling.border_radius) {
      this.element.style.borderRadius = styling.border_radius + "px";
    }

    var brandOverride = this.campaign.brand_styles_override;
    if (brandOverride && brandOverride.use) {
      if (brandOverride.primary_color) {
        this.element.style.setProperty("--cro-primary", brandOverride.primary_color);
        this.element.style.setProperty("--cro-primary-color", brandOverride.primary_color);
      }
      if (brandOverride.secondary_color) {
        this.element.style.setProperty("--cro-secondary-color", brandOverride.secondary_color);
      }
      if (brandOverride.button_radius !== undefined && brandOverride.button_radius !== "") {
        var radiusPx = brandOverride.button_radius + "px";
        this.element.style.setProperty("--cro-radius", radiusPx);
        this.element.style.setProperty("--cro-button-radius", radiusPx);
      }
      if (brandOverride.font_size_scale !== undefined && brandOverride.font_size_scale !== "") {
        this.element.style.setProperty("--cro-font-size-scale", String(brandOverride.font_size_scale));
      }
    }

    this.element.innerHTML = this.buildHTML(content, styling);
  }

  buildHTML(content, styling) {
    const templateRaw =
      this.campaign.template || this.campaign.template_type || "centered";
    let tmplKey = String(templateRaw).replace(/\s+/g, "-").replace(/_/g, "-");
    if (tmplKey === "gamified-wheel") {
      return this.buildGamifiedWheelHTML(content, styling);
    }

    let html = "";

    html +=
      '<button type="button" class="cro-popup__close" aria-label="Close" data-action="close">' +
      '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
      '<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>' +
      "</svg></button>";

    if (content.image_url) {
      html +=
        '<div class="cro-popup__image"><img src="' +
        this.escapeHtml(content.image_url) +
        '" alt="" loading="eager" /></div>';
    }

    html += '<div class="cro-popup__inner">';

    if (content.headline) {
      const headlineStyle = styling.headline_color
        ? ' style="color: ' + this.escapeHtml(styling.headline_color) + '"'
        : "";
      html +=
        '<h2 class="cro-popup__headline" id="cro-headline-' +
        this.campaign.id +
        '"' +
        headlineStyle +
        ">" +
        this.escapeHtml(this.processPlaceholders(content.headline)) +
        "</h2>";
    }

    if (content.subheadline) {
      html +=
        '<p class="cro-popup__subheadline">' +
        this.escapeHtml(this.processPlaceholders(content.subheadline)) +
        "</p>";
    }

    if (content.body) {
      html += '<div class="cro-popup__body">' + content.body + "</div>";
    }

    if (content.show_countdown) {
      const minutes = content.countdown_minutes || 15;
      const mStr = String(minutes).padStart(2, "0");
      html +=
        '<div class="cro-popup__countdown" data-minutes="' +
        minutes +
        '">' +
        '<span class="cro-popup__countdown-label">Offer ends in</span>' +
        '<span class="cro-popup__countdown-timer">' +
        '<span class="cro-countdown-minutes">' +
        mStr +
        '</span>:<span class="cro-countdown-seconds">00</span></span></div>';
    }

    const couponActions = ["apply_coupon", "apply_coupon_checkout", "copy_coupon"];
    const ctaAction = content.cta_action || "close";
    const showCouponBlock =
      (content.show_coupon || couponActions.indexOf(ctaAction) !== -1) &&
      content.coupon_code;
    if (showCouponBlock) {
      const label =
        content.coupon_label ||
        content.coupon_display_text ||
        "Your code";
      html +=
        '<div class="cro-popup__coupon">' +
        '<span class="cro-popup__coupon-label">' +
        this.escapeHtml(label) +
        "</span>" +
        '<code class="cro-popup__coupon-code" data-code="' +
        this.escapeHtml(content.coupon_code) +
        '">' +
        this.escapeHtml(content.coupon_code) +
        "</code>" +
        '<button type="button" class="cro-popup__coupon-copy" data-action="copy-coupon" aria-label="Copy coupon code">Copy</button>' +
        "</div>";
    }

    if (content.show_email_field) {
      const placeholder = content.email_placeholder || "Enter your email";
      const ctaText = content.cta_text || "Subscribe";
      const successMsg = content.success_message || "Thanks for subscribing!";
      html +=
        '<form class="cro-popup__email-form" data-action="email-capture">' +
        '<div class="cro-email__input-wrapper">' +
        '<input type="email" class="cro-email__input" placeholder="' +
        this.escapeHtml(placeholder) +
        '" required />' +
        '<button type="submit" class="cro-email__submit" style="' +
        this.getButtonStyles(styling) +
        '">' +
        this.escapeHtml(ctaText) +
        "</button></div>" +
        '<p class="cro-email__error" style="display:none;"></p>' +
        '<p class="cro-email__success" style="display:none;">' +
        this.escapeHtml(successMsg) +
        "</p></form>";
    } else if (content.cta_text) {
      html +=
        '<button type="button" class="cro-popup__cta" data-action="cta" style="' +
        this.getButtonStyles(styling) +
        '">' +
        this.escapeHtml(content.cta_text) +
        "</button>";
    }

    if (content.show_dismiss_link !== false) {
      html +=
        '<a href="#" class="cro-popup__dismiss" data-action="dismiss">' +
        this.escapeHtml(content.dismiss_text || "No thanks") +
        "</a>";
    }

    html += "</div>";
    return html;
  }

  buildGamifiedWheelHTML(content, styling) {
    const c = content || {};
    const id = this.campaign.id;
    const headline = c.headline || "Try your luck!";
    const sub = c.subheadline || "";
    const cta = c.cta_text || "Spin now";
    const defaultSlices = [
      { label: "10% off", type: "win", color: "#2563eb" },
      { label: "Try again", type: "lose", color: "#e5e7eb" },
      { label: "5% off", type: "win", color: "#7c3aed" },
      { label: "Try again", type: "lose", color: "#e5e7eb" },
      { label: "Free ship", type: "win", color: "#059669" },
      { label: "Try again", type: "lose", color: "#e5e7eb" },
    ];
    const slices =
      Array.isArray(c.wheel_slices) && c.wheel_slices.length
        ? c.wheel_slices
        : defaultSlices;
    const slicesJson = this.escapeHtml(JSON.stringify(slices));

    let html =
      '<button type="button" class="cro-popup__close" aria-label="Close" data-action="close">' +
      '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
      '<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>';
    html += '<div class="cro-wheel-body">';
    html +=
      '<h2 class="cro-wheel-headline" id="cro-wheel-headline-' +
      id +
      '">' +
      this.escapeHtml(this.processPlaceholders(headline)) +
      "</h2>";
    if (sub) {
      html +=
        '<p class="cro-wheel-sub">' +
        this.escapeHtml(this.processPlaceholders(sub)) +
        "</p>";
    }
    html += '<div class="cro-wheel-wrap" aria-hidden="true">';
    html +=
      '<canvas id="cro-wheel-canvas-' +
      id +
      '" width="300" height="300" data-slices="' +
      slicesJson +
      '"></canvas>';
    html += '<div class="cro-wheel-pointer">&#9660;</div></div>';
    html += '<div class="cro-wheel-email-step">';
    html +=
      '<input type="email" class="cro-wheel-email" placeholder="Enter your email to spin" aria-label="Email" />';
    html +=
      '<button type="button" class="cro-popup__cta cro-wheel-spin-btn" style="' +
      this.getButtonStyles(styling) +
      '" data-campaign-id="' +
      id +
      '">' +
      this.escapeHtml(cta) +
      "</button></div>";
    html +=
      '<div class="cro-wheel-result" style="display:none;" aria-live="polite">';
    html += '<p class="cro-wheel-result-text"></p>';
    html += '<p class="cro-wheel-coupon-code" style="display:none;"></p>';
    html += "</div></div>";
    return html;
  }

  bindEvents() {
    const self = this;

    this.element
      .querySelector('[data-action="close"]')
      ?.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        self.close("close");
      });

    this.element
      .querySelector('[data-action="dismiss"]')
      ?.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        self.close("dismiss");
      });

    this.overlay.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      self.close("dismiss");
    });

    this.element
      .querySelector('[data-action="cta"]')
      ?.addEventListener("click", function (e) {
        const content = self.campaign.content || {};
        const action = content.cta_action || "close";
        const couponCode = content.coupon_code || "";

        if (self.callbacks.onConvert) {
          self.callbacks.onConvert("cta_click", { action: action });
        }

        switch (action) {
          case "url":
            if (content.cta_url) {
              const tgt =
                content.cta_url_target === "_blank" ? "_blank" : "_self";
              if (tgt === "_blank") {
                window.open(content.cta_url, "_blank");
              } else {
                window.location.href = content.cta_url;
              }
            } else {
              self.close("convert");
            }
            break;
          case "cart":
            window.location.href =
              (window.croConfig && window.croConfig.cartUrl) || "/cart";
            break;
          case "checkout":
            window.location.href =
              (window.croConfig && window.croConfig.checkoutUrl) ||
              "/checkout";
            break;
          case "apply_coupon":
            if (self._applyingCoupon) return;
            if (couponCode) {
              self._applyingCoupon = true;
              const btn = e.currentTarget;
              const originalText = btn.textContent;
              btn.disabled = true;
              btn.textContent = "Applying…";
              self.applyCouponToCart(
                couponCode,
                function () {
                  self._applyingCoupon = false;
                  btn.textContent = "✓ Applied!";
                  setTimeout(function () {
                    self.close("convert");
                  }, 1200);
                },
                function (errMsg, errBody) {
                  self._applyingCoupon = false;
                  btn.disabled = false;
                  btn.textContent = originalText;
                  self._showCtaError(
                    errMsg || "Could not apply coupon.",
                    errBody
                  );
                }
              );
            } else {
              self.close("convert");
            }
            break;
          case "apply_coupon_checkout":
            if (self._applyingCoupon) return;
            if (couponCode) {
              self._applyingCoupon = true;
              const btnCh = e.currentTarget;
              const origCh = btnCh.textContent;
              btnCh.disabled = true;
              btnCh.textContent = "Applying…";
              self.applyCouponToCart(
                couponCode,
                function () {
                  self._applyingCoupon = false;
                  window.location.href =
                    (window.croConfig && window.croConfig.checkoutUrl) ||
                    "/checkout";
                },
                function (errMsg, errBody) {
                  self._applyingCoupon = false;
                  btnCh.disabled = false;
                  btnCh.textContent = origCh;
                  self._showCtaError(
                    errMsg || "Could not apply coupon.",
                    errBody
                  );
                  setTimeout(function () {
                    window.location.href =
                      (window.croConfig && window.croConfig.checkoutUrl) ||
                      "/checkout";
                  }, 2000);
                }
              );
            } else {
              window.location.href =
                (window.croConfig && window.croConfig.checkoutUrl) ||
                "/checkout";
            }
            break;
          case "copy_coupon":
            if (couponCode) {
              const btnCp = e.currentTarget;
              const origCp = btnCp.textContent;
              navigator.clipboard
                .writeText(couponCode)
                .then(function () {
                  btnCp.textContent = "✓ Copied!";
                  btnCp.classList.add("cro-cta--copied");
                  document.dispatchEvent(
                    new CustomEvent("cro:coupon_copied", {
                      detail: {
                        couponCode: couponCode,
                        campaignId: self.campaign.id,
                      },
                    })
                  );
                  setTimeout(function () {
                    btnCp.textContent = origCp;
                    btnCp.classList.remove("cro-cta--copied");
                    self.close("convert");
                  }, 1500);
                })
                .catch(function () {
                  self._copyFallback(couponCode);
                  self.close("convert");
                });
            }
            break;
          default:
            self.close("convert");
        }
      });

    this._copyCouponHandler = function (ev) {
      const btn = ev.target.closest(
        ".cro-popup__coupon-copy, [data-action='copy-coupon']"
      );
      if (!btn || !self.element.contains(btn)) return;
      ev.preventDefault();
      const code =
        (self.campaign.content && self.campaign.content.coupon_code) || "";
      if (!code) return;
      navigator.clipboard
        .writeText(code)
        .then(function () {
          btn.textContent = "Copied!";
          btn.classList.add("cro-popup__coupon-copy--success");
          document.dispatchEvent(
            new CustomEvent("cro:coupon_copied", {
              detail: {
                couponCode: code,
                campaignId: self.campaign.id,
              },
            })
          );
          setTimeout(function () {
            btn.textContent = "Copy";
            btn.classList.remove("cro-popup__coupon-copy--success");
          }, 2000);
        })
        .catch(function () {
          self._copyFallback(code);
        });
    };
    this.element.addEventListener("click", this._copyCouponHandler);

    const form = this.element.querySelector('[data-action="email-capture"]');
    if (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        const input = form.querySelector('input[type="email"]');
        const email = input && input.value;

        if (!email || !self.validateEmail(email)) {
          self.showEmailError("Please enter a valid email address");
          return;
        }
        self.submitEmail(email, form);
      });
    }

    this.escHandler = function (e) {
      if (e.key === "Escape") {
        self.close("dismiss");
      }
    };
    document.addEventListener("keydown", this.escHandler);
  }

  initCountdown() {
    const self = this;
    const countdownEl = this.element.querySelector(".cro-popup__countdown");
    if (!countdownEl) return;

    let minutes = parseInt(countdownEl.dataset.minutes, 10) || 15;
    let seconds = 0;

    const sessionKey = "cro_countdown_" + this.campaign.id;
    const stored = sessionStorage.getItem(sessionKey);
    if (stored) {
      try {
        const data = JSON.parse(stored);
        const elapsed = Math.floor((Date.now() - data.started) / 1000);
        const remaining = data.minutes * 60 - elapsed;
        if (remaining > 0) {
          minutes = Math.floor(remaining / 60);
          seconds = remaining % 60;
        } else {
          minutes = 0;
          seconds = 0;
        }
      } catch (err) {}
    } else {
      sessionStorage.setItem(
        sessionKey,
        JSON.stringify({ started: Date.now(), minutes: minutes })
      );
    }

    const minutesEl = countdownEl.querySelector(
      ".cro-countdown-minutes, .cro-countdown__minutes"
    );
    const secondsEl = countdownEl.querySelector(
      ".cro-countdown-seconds, .cro-countdown__seconds"
    );
    if (!minutesEl || !secondsEl) return;

    const updateDisplay = function () {
      minutesEl.textContent = String(minutes).padStart(2, "0");
      secondsEl.textContent = String(seconds).padStart(2, "0");
      if (minutes === 0 && seconds < 60) {
        countdownEl.classList.add("cro-countdown--urgent");
      }
    };

    updateDisplay();

    this.countdownInterval = setInterval(function () {
      if (seconds > 0) {
        seconds--;
      } else if (minutes > 0) {
        minutes--;
        seconds = 59;
      } else {
        clearInterval(self.countdownInterval);
        self.countdownInterval = null;
        countdownEl.classList.add("cro-countdown--expired");
        return;
      }
      updateDisplay();
    }, 1000);
  }

  initAccessibility() {
    const self = this;
    const headlineId = "cro-headline-" + this.campaign.id;
    this.element.setAttribute("aria-labelledby", headlineId);

    this._previouslyFocused = document.activeElement;

    const focusableSelectors =
      'button, input, [href], select, textarea, [tabindex]:not([tabindex="-1"])';
    function getFocusable() {
      return Array.from(self.element.querySelectorAll(focusableSelectors)).filter(
        function (el) {
          return !el.disabled && el.offsetParent !== null;
        }
      );
    }

    setTimeout(function () {
      const focusable = getFocusable();
      if (focusable.length) {
        focusable[0].focus();
      }
    }, 100);

    this._trapHandler = function (e) {
      if (e.key !== "Tab") return;
      const focusable = getFocusable();
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    };
    this.element.addEventListener("keydown", this._trapHandler);
  }

  submitEmail(email, form) {
    const submitBtn = form.querySelector(".cro-email__submit");
    const originalText = submitBtn ? submitBtn.textContent : "";
    if (submitBtn) {
      submitBtn.textContent = "Sending...";
      submitBtn.disabled = true;
    }

    const url =
      window.croConfig && window.croConfig.restUrl
        ? window.croConfig.restUrl + "cro/v1/email"
        : "";
    if (!url) {
      if (submitBtn) {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
      }
      this.showEmailError("Configuration error.");
      return;
    }

    fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": window.croConfig.nonce || "",
      },
      body: JSON.stringify({ email: email, campaign_id: this.campaign.id }),
    })
      .then(function (response) {
        return response.json();
      })
      .then(
        function (data) {
          if (data.success) {
            this.showEmailSuccess();
            if (this.callbacks.onEmailCapture) {
              this.callbacks.onEmailCapture(email);
            }
            if (
              this.campaign.content &&
              this.campaign.content.auto_apply_coupon &&
              this.campaign.content.coupon_code
            ) {
              this.applyCouponToCart(this.campaign.content.coupon_code);
            }
          } else {
            this.showEmailError(data.message || "Something went wrong");
            if (submitBtn) {
              submitBtn.textContent = originalText;
              submitBtn.disabled = false;
            }
          }
        }.bind(this)
      )
      .catch(
        function () {
          this.showEmailError("Network error. Please try again.");
          if (submitBtn) {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
          }
        }.bind(this)
      );
  }

  showEmailError(message) {
    const errorEl = this.element.querySelector(".cro-email__error");
    if (errorEl) {
      errorEl.textContent = message;
      errorEl.style.display = "block";
    }
  }

  showEmailSuccess() {
    const form = this.element.querySelector(".cro-popup__email-form");
    const successEl = this.element.querySelector(".cro-email__success");
    if (form && successEl) {
      const wrapper = form.querySelector(".cro-email__input-wrapper");
      if (wrapper) wrapper.style.display = "none";
      successEl.style.display = "block";
    }
  }

  _showCtaError(message, errPayload) {
    errPayload = errPayload || {};
    const loginUrl =
      (errPayload.data && errPayload.data.login_url) ||
      (window.croConfig && window.croConfig.loginUrl);
    const useLoginLink =
      errPayload.code === "forbidden" &&
      loginUrl &&
      message &&
      message.indexOf("Login") !== -1;

    let errEl = this.element.querySelector(".cro-cta__error");
    if (!errEl) {
      errEl = document.createElement("p");
      errEl.className = "cro-cta__error";
      errEl.style.cssText =
        "color:#dc2626;font-size:.85em;margin:.5em 0 0;";
      const cta = this.element.querySelector('[data-action="cta"]');
      if (cta) {
        cta.insertAdjacentElement("afterend", errEl);
      }
    }
    if (useLoginLink) {
      errEl.innerHTML = "";
      const a = document.createElement("a");
      a.href = loginUrl;
      a.textContent = "Login";
      a.rel = "noopener noreferrer";
      errEl.appendChild(a);
      errEl.appendChild(
        document.createTextNode(" to your account to use this coupon.")
      );
    } else {
      errEl.textContent = message;
    }
    errEl.style.display = "block";
    setTimeout(function () {
      errEl.style.display = "none";
    }, 4000);
  }

  _copyFallback(text) {
    const ta = document.createElement("textarea");
    ta.value = text;
    ta.style.cssText = "position:fixed;opacity:0;pointer-events:none;";
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand("copy");
    } catch (e) {}
    document.body.removeChild(ta);
  }

  applyCouponToCart(code, onSuccess, onError) {
    const restUrl =
      window.croConfig && window.croConfig.restUrl
        ? window.croConfig.restUrl + "cro/v1/offer/apply"
        : null;

    if (!restUrl) {
      if (onError) {
        onError("Configuration error: restUrl missing.");
      }
      return;
    }

    const self = this;
    fetch(restUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": (window.croConfig && window.croConfig.nonce) || "",
      },
      body: JSON.stringify({ coupon_code: code }),
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (data) {
        if (data && data.success) {
          if (onSuccess) {
            onSuccess(data);
          }
          document.dispatchEvent(
            new CustomEvent("cro:coupon_applied", {
              detail: {
                couponCode: code,
                cartFragments: data.cart_fragments || null,
              },
            })
          );
          if (typeof jQuery !== "undefined" && jQuery.fn.wc_cart_fragments) {
            jQuery(document.body).trigger("wc_fragment_refresh");
          }
        } else if (onError) {
          onError(
            (data && data.message) || "Coupon could not be applied.",
            data
          );
        }
      })
      .catch(function () {
        if (onError) {
          onError("Network error applying coupon.");
        }
      });
  }

  validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  processPlaceholders(text) {
    if (!text) return "";
    var config = window.croConfig || {};
    var ctx = config.context || {};
    var cart = ctx.cart || {};
    var currency = config.currency || "$";
    var total = cart.total
      ? currency + parseFloat(cart.total).toFixed(2)
      : "";
    return (text + "")
      .replace(/{store_name}/g, config.siteName || "")
      .replace(/{cart_total}/g, total)
      .replace(/{site_url}/g, config.siteUrl || "");
  }

  getButtonStyles(styling) {
    const styles = [];
    if (styling.button_bg_color) {
      styles.push("background-color: " + styling.button_bg_color);
    }
    if (styling.button_text_color) {
      styles.push("color: " + styling.button_text_color);
    }
    if (styling.border_radius != null && styling.border_radius !== "") {
      const radius = parseInt(styling.border_radius, 10);
      styles.push("border-radius: " + Math.floor(radius / 2) + "px");
    }
    return styles.join("; ");
  }

  escapeHtml(text) {
    if (!text) return "";
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }
}

window.CROPopup = CROPopup;

(function ($) {
  "use strict";

  /**
   * CROAccessibility – keyboard navigation, focus trapping, ARIA support.
   */
  function CROAccessibility(popup) {
    this.popup = popup;
    this.focusableElements = null;
    this.firstFocusable = null;
    this.lastFocusable = null;
    this.previouslyFocused = null;
    this.boundHandleKeydown = null;
  }

  CROAccessibility.prototype.init = function () {
    var self = this;

    // Store currently focused element
    this.previouslyFocused = document.activeElement;

    // Find focusable elements within popup content (not overlay)
    var $content = $(this.popup).find(".cro-popup-content");
    if (!$content.length) {
      $content = $(this.popup);
    }

    this.focusableElements = $content
      .find(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      )
      .filter(":visible")
      .toArray();

    if (this.focusableElements.length === 0) {
      return;
    }

    this.firstFocusable = this.focusableElements[0];
    this.lastFocusable =
      this.focusableElements[this.focusableElements.length - 1];

    // Set initial focus (small delay to ensure popup is visible)
    setTimeout(function () {
      if (self.firstFocusable) {
        self.firstFocusable.focus();
      }
    }, 50);

    // Add keyboard listener
    this.boundHandleKeydown = this.handleKeydown.bind(this);
    this.popup.addEventListener("keydown", this.boundHandleKeydown);

    // Set ARIA attributes
    this.popup.setAttribute("role", "dialog");
    this.popup.setAttribute("aria-modal", "true");

    // Find heading for aria-labelledby
    var $heading = $content.find("h1, h2, h3, h4, h5, h6").first();
    if ($heading.length) {
      var headingId = $heading.attr("id");
      if (!headingId) {
        headingId = "cro-popup-headline-" + Date.now();
        $heading.attr("id", headingId);
      }
      this.popup.setAttribute("aria-labelledby", headingId);
    }
  };

  CROAccessibility.prototype.handleKeydown = function (e) {
    // Escape closes popup
    if (e.key === "Escape" || e.keyCode === 27) {
      e.preventDefault();
      e.stopPropagation();
      this.close();
      return;
    }

    // Tab trapping
    if (e.key === "Tab" || e.keyCode === 9) {
      if (this.focusableElements.length === 0) {
        return;
      }

      if (e.shiftKey) {
        // Shift + Tab
        if (document.activeElement === this.firstFocusable) {
          e.preventDefault();
          if (this.lastFocusable) {
            this.lastFocusable.focus();
          }
        }
      } else {
        // Tab
        if (document.activeElement === this.lastFocusable) {
          e.preventDefault();
          if (this.firstFocusable) {
            this.firstFocusable.focus();
          }
        }
      }
    }

    // Enter on close button
    if (
      (e.key === "Enter" || e.keyCode === 13) &&
      ($(document.activeElement).hasClass("cro-popup-close") ||
        $(document.activeElement).hasClass("cro-popup__close"))
    ) {
      e.preventDefault();
      this.close();
    }
  };

  CROAccessibility.prototype.close = function () {
    // Return focus to previous element
    if (this.previouslyFocused && this.previouslyFocused.focus) {
      try {
        this.previouslyFocused.focus();
      } catch (e) {
        // Fallback if element is no longer focusable
      }
    }

    // Trigger close via existing close handler
    var $closeBtn = $(this.popup)
      .find(".cro-popup-close, .cro-popup__close")
      .first();
    if ($closeBtn.length) {
      $closeBtn.trigger("click");
    }
  };

  CROAccessibility.prototype.destroy = function () {
    if (this.boundHandleKeydown) {
      this.popup.removeEventListener("keydown", this.boundHandleKeydown);
    }
    this.popup.removeAttribute("role");
    this.popup.removeAttribute("aria-modal");
    this.popup.removeAttribute("aria-labelledby");
  };

  // Store accessibility instances per popup
  var accessibilityInstances = {};

  function dispatchCROEvent(eventName, detail) {
    detail = detail || {};
    try {
      document.dispatchEvent(new CustomEvent(eventName, { detail: detail }));
    } catch (e) {
      var ev = document.createEvent("CustomEvent");
      if (ev && ev.initCustomEvent) {
        ev.initCustomEvent(eventName, true, true, detail);
        document.dispatchEvent(ev);
      }
    }
  }

  function getPopupDetail($popup) {
    var campaignId = $popup.data("campaign-id") || "";
    var campaignName = $popup.data("campaign-name") || "";
    var templateType = $popup.data("template-type") || "";
    if (!templateType && $popup.attr("class")) {
      var m = $popup
        .attr("class")
        .match(
          /cro-popup--(centered|corner|slide-bottom|exit-intent|centered-image-left|centered-image-right|image-right)/
        );
      templateType = m ? m[1] : "centered";
    }
    return {
      campaignId: campaignId,
      campaignName: campaignName,
      templateType: templateType,
    };
  }

  $(document).ready(function () {
    var $popups = $(".cro-popup");

    if ($popups.length === 0) {
      return;
    }

    // Preview mode: show preview popup immediately (admin campaign preview in new tab)
    var $preview = $popups.filter(".cro-popup-preview");
    if ($preview.length) {
      var popupEl = $preview[0];
      $preview.addClass("cro-popup-active");
      $("body").addClass("cro-popup-open");
      if (window.croUX && window.croUX.shouldReduceMotion()) {
        $preview.addClass("cro-reduced-motion");
      }
      if (!accessibilityInstances[popupEl]) {
        var accessibility = new CROAccessibility(popupEl);
        accessibility.init();
        accessibilityInstances[popupEl] = accessibility;
      }
      var d = getPopupDetail($preview);
      dispatchCROEvent("cro:campaign_shown", {
        campaignId: d.campaignId,
        campaignName: d.campaignName,
        templateType: d.templateType,
      });
    }

    // Handle exit intent – show first exit-intent popup and dispatch cro:campaign_shown (respect UX rules)
    // Skip if CROController is active (controller handles all triggers and shows one popup via REST)
    $(document).on("cro:exit-intent", function () {
      if (
        window.CROController &&
        window.CROController.state &&
        window.CROController.state.initialized
      ) {
        return;
      }
      if (window.croUX && !window.croUX.canShowPopup()) {
        return;
      }
      var $popup = $popups.filter(".cro-popup-exit-intent").first();
      if ($popup.length) {
        var popupEl = $popup[0];
        $popup.addClass("cro-popup-active");
        $("body").addClass("cro-popup-open");
        if (window.croUX && window.croUX.shouldReduceMotion()) {
          $popup.addClass("cro-reduced-motion");
        }

        // Initialize accessibility
        if (!accessibilityInstances[popupEl]) {
          var accessibility = new CROAccessibility(popupEl);
          accessibility.init();
          accessibilityInstances[popupEl] = accessibility;
        }

        var d = getPopupDetail($popup);
        dispatchCROEvent("cro:campaign_shown", {
          campaignId: d.campaignId,
          campaignName: d.campaignName,
          templateType: d.templateType,
        });
        var campaignId = $popup.data("campaign-id");
        if (campaignId) {
          trackEvent(campaignId, "popup_viewed");
        }
      }
    });

    // Helper to close popup and clean up
    function closePopup($popup) {
      var popupEl = $popup[0];
      var campaignId = $popup.data("campaign-id");

      // Clean up accessibility
      if (accessibilityInstances[popupEl]) {
        accessibilityInstances[popupEl].destroy();
        delete accessibilityInstances[popupEl];
      }

      $popup.removeClass("cro-popup-active");
      var $wrapper = $popup.closest(".cro-popup-preview-wrapper");
      if ($wrapper.length) {
        $wrapper.remove();
      }
      // Remove body scroll lock when no popup is active
      if ($(".cro-popup.cro-popup-active").length === 0) {
        $("body").removeClass("cro-popup-open");
      }
      if (campaignId) {
        trackEvent(campaignId, "popup_closed");
        dispatchCROEvent("cro:campaign_dismissed", { campaignId: campaignId });
      }
    }

    // Close popup – dispatch cro:campaign_dismissed (.cro-popup-close and .cro-popup__close for template compatibility)
    $(document).on(
      "click",
      ".cro-popup-close, .cro-popup__close",
      function (e) {
        e.preventDefault();
        closePopup($(this).closest(".cro-popup"));
      }
    );

    // Close on overlay click (overlay may be inside popup or sibling in .cro-popup-preview-wrapper)
    $(document).on("click", ".cro-popup-overlay", function (e) {
      if (e.target !== this) return;
      var $popup = $(this).siblings(".cro-popup").first();
      if (!$popup.length) {
        $popup = $(this).closest(".cro-popup");
      }
      if ($popup.length) {
        closePopup($popup);
      }
    });

    // CTA click = conversion – dispatch cro:campaign_converted
    $(document).on(
      "click",
      '.cro-popup .cro-popup-button, .cro-popup [data-cro-action="convert"]',
      function (e) {
        var $popup = $(this).closest(".cro-popup");
        var campaignId = $popup.data("campaign-id");
        if (campaignId) {
          dispatchCROEvent("cro:campaign_converted", {
            campaignId: campaignId,
            conversionType: "cta_click",
            timestamp: Date.now(),
          });
        }
      }
    );

    // Email capture – dispatch cro:email_captured on submit of form with email inside popup
    $(document).on("submit", ".cro-popup form", function (e) {
      var $form = $(this);
      var $email = $form
        .find('input[type="email"], input[name*="email"]')
        .first();
      if ($email.length && $email.val()) {
        var $popup = $form.closest(".cro-popup");
        var campaignId = $popup.length ? $popup.data("campaign-id") : "";
        dispatchCROEvent("cro:email_captured", {
          email: $email.val(),
          campaignId: campaignId || "",
        });
      }
    });

    // Coupon copied – dispatch cro:coupon_copied when copy button/element is used
    $(document).on(
      "click",
      ".cro-popup .cro-popup__coupon-copy, .cro-popup [data-action='copy-coupon']",
      function (e) {
        var $btn = $(this);
        var code = $btn.data("coupon-code") || $btn.data("cro-copy") || "";
        if (!code) {
          var $codeEl = $btn
            .closest(".cro-popup")
            .find(".cro-coupon-code, [data-coupon-code]")
            .first();
          code = $codeEl.length
            ? $codeEl.data("coupon-code") || $codeEl.text().trim()
            : "";
        }
        if (code) {
          var $popup = $btn.closest(".cro-popup");
          var campaignId = $popup.length ? $popup.data("campaign-id") : "";
          dispatchCROEvent("cro:coupon_copied", {
            couponCode: code,
            campaignId: campaignId || "",
          });
        }
      }
    );

    // Track popup view and initialize accessibility for popups that are already active on load
    $popups.each(function () {
      var $popup = $(this);
      var popupEl = $popup[0];
      var campaignId = $popup.data("campaign-id");
      if ($popup.hasClass("cro-popup-active")) {
        // Initialize accessibility for already-active popups
        if (!accessibilityInstances[popupEl]) {
          var accessibility = new CROAccessibility(popupEl);
          accessibility.init();
          accessibilityInstances[popupEl] = accessibility;
        }
        if (campaignId) {
          trackEvent(campaignId, "popup_viewed");
        }
      }
    });
  });

  /**
   * Track event
   */
  function trackEvent(campaignId, eventType) {
    $.ajax({
      url: croPopup.ajaxUrl,
      type: "POST",
      data: {
        action: "cro_track_event",
        nonce: croPopup.nonce,
        campaign_id: campaignId,
        event_type: eventType,
      },
    });
  }
})(jQuery);
