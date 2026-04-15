/**
 * Spin-to-win wheel (gamified-wheel template).
 *
 * @package Meyvora_Convert
 */
(function ($) {
  "use strict";

  function getSpinConfig() {
    return window.meyvcSpinWheel || {};
  }

  function MEYVCSpinWheel(popup) {
    this.$popup = $(popup);
    // Use the attribute / dataset, not jQuery .data("campaign-id"): jQuery maps data-* to
    // camelCase internally, so .data("campaign-id") is often undefined while data-campaign-id exists.
    var root = this.$popup[0];
    this.campaignId =
      (root && root.getAttribute("data-campaign-id")) ||
      this.$popup.attr("data-campaign-id") ||
      "";
    var canvasId = "meyvc-wheel-canvas-" + this.campaignId;
    this.canvas = document.getElementById(canvasId);
    if (!this.canvas || !this.canvas.getContext) {
      return;
    }
    this.ctx = this.canvas.getContext("2d");
    this.slices = [];
    try {
      var raw =
        this.canvas.getAttribute("data-slices") ||
        this.canvas.dataset.slices ||
        "[]";
      raw = String(raw).replace(/&quot;/g, '"');
      this.slices = JSON.parse(raw);
    } catch (e) {
      this.slices = [];
    }
    if (!this.slices.length) {
      // Use default placeholder slices so wheel renders; real slices come from spin_init AJAX
      this.slices = [
        { label: "Win!", type: "win", color: "#2563eb" },
        { label: "Try again", type: "lose", color: "#e5e7eb" },
        { label: "Win!", type: "win", color: "#7c3aed" },
        { label: "Try again", type: "lose", color: "#e5e7eb" },
        { label: "Win!", type: "win", color: "#059669" },
        { label: "Try again", type: "lose", color: "#e5e7eb" }
      ];
    }
    this.numSlices = this.slices.length;
    this.sliceAngle = (2 * Math.PI) / this.numSlices;
    this.currentAngle = 0;
    this.spinning = false;
    this.hasSpun = false;
    this.draw(0);
    this.bindEvents();
    if (root) {
      root.dataset.meyvcWheelInit = "1";
    }
  }

  MEYVCSpinWheel.prototype.draw = function (rotation) {
    var ctx = this.ctx;
    var cx = this.canvas.width / 2;
    var cy = this.canvas.height / 2;
    var r = cx - 4;
    ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
    for (var i = 0; i < this.numSlices; i++) {
      var startAngle = rotation + i * this.sliceAngle;
      var endAngle = startAngle + this.sliceAngle;
      ctx.beginPath();
      ctx.moveTo(cx, cy);
      ctx.arc(cx, cy, r, startAngle, endAngle);
      ctx.closePath();
      ctx.fillStyle = this.slices[i].color || "#cccccc";
      ctx.fill();
      ctx.strokeStyle = "#ffffff";
      ctx.lineWidth = 2;
      ctx.stroke();
      ctx.save();
      ctx.translate(cx, cy);
      ctx.rotate(startAngle + this.sliceAngle / 2);
      ctx.textAlign = "right";
      ctx.fillStyle = "#ffffff";
      ctx.font = "bold 13px sans-serif";
      ctx.shadowColor = "rgba(0,0,0,0.3)";
      ctx.shadowBlur = 2;
      ctx.fillText(this.slices[i].label, r - 10, 5);
      ctx.restore();
    }
    ctx.beginPath();
    ctx.arc(cx, cy, 18, 0, 2 * Math.PI);
    ctx.fillStyle = "#ffffff";
    ctx.fill();
  };

  MEYVCSpinWheel.prototype.bindEvents = function () {
    var self = this;
    this.$popup.on("click", ".meyvc-wheel-spin-btn", function () {
      if (self.spinning || self.hasSpun) return;
      var email = self.$popup.find(".meyvc-wheel-email").val().trim();
      if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        self.$popup.find(".meyvc-wheel-email").focus();
        return;
      }
      self.fetchTokenAndSpin(email);
    });
  };

  MEYVCSpinWheel.prototype.fetchTokenAndSpin = function (email) {
    var self = this;
    var $btn = this.$popup.find(".meyvc-wheel-spin-btn");
    var cfg = getSpinConfig();
    $btn.prop("disabled", true);
    self.hasSpun = true;
    var ajaxUrl = cfg.ajaxUrl || window.ajaxurl || "";
    if (!ajaxUrl) { $btn.prop("disabled", false); self.hasSpun = false; return; }
    $.post(
      ajaxUrl,
      {
        action: "meyvc_spin_init",
        nonce: cfg.nonce || "",
        campaign_id: self.campaignId,
      },
      function (r) {
        if (!r || !r.success || !r.data) {
          $btn.prop("disabled", false);
          return;
        }
        self.spinToken = r.data.token;
        self.spinHour = r.data.hour;
        self.winningIndex = r.data.winning_index;
        if (Array.isArray(r.data.slices) && r.data.slices.length) {
          self.slices = r.data.slices;
          self.numSlices = self.slices.length;
          self.sliceAngle = (2 * Math.PI) / self.numSlices;
          self.currentAngle = 0;
          self.draw(0);
        }
        self.spin(email);
      }
    ).fail(function () {
      $btn.prop("disabled", false);
      self.hasSpun = false;
    });
  };

  MEYVCSpinWheel.prototype.spin = function (email) {
    var self = this;
    this.spinning = true;
    this.hasSpun = true;
    var winIndex =
      typeof self.winningIndex === "number" ? self.winningIndex : 0;
    // Canvas: angle 0 = 3 o'clock; slices start at rotation + i*sliceAngle clockwise.
    // The wheel pointer in the UI is at 12 o'clock = 3π/2 (not 3 o'clock).
    var twoPi = 2 * Math.PI;
    var pointerAngle = (3 * Math.PI) / 2;
    var destRotation =
      pointerAngle - (winIndex + 0.5) * self.sliceAngle;
    while (destRotation < 0) destRotation += twoPi;
    while (destRotation >= twoPi) destRotation -= twoPi;
    var startRot = self.currentAngle;
    var startNorm = startRot % twoPi;
    if (startNorm < 0) startNorm += twoPi;
    var delta = destRotation - startNorm;
    if (delta < 0) delta += twoPi;
    var fullSpins = 5 * twoPi;
    var targetAngle = fullSpins + delta;
    var start = null;
    var duration = 4000;
    function easeOut(t) {
      return 1 - Math.pow(1 - t, 4);
    }
    (function animate(ts) {
      if (!start) start = ts;
      var progress = Math.min((ts - start) / duration, 1);
      self.draw(startRot + targetAngle * easeOut(progress));
      if (progress < 1) {
        requestAnimationFrame(animate);
      } else {
        var end = startRot + targetAngle;
        self.currentAngle = end - twoPi * Math.floor(end / twoPi);
        if (self.currentAngle < 0) self.currentAngle += twoPi;
        self.spinning = false;
        self.onSpinEnd(email, winIndex);
      }
    })(performance.now());
  };

  MEYVCSpinWheel.prototype.onSpinEnd = function (email, sliceIndex) {
    var self = this;
    var slice = self.slices[sliceIndex] || {};
    var i18n = window.meyvc_spin_i18n || {};
    self.$popup.find(".meyvc-wheel-email-step").hide();
    var $result = self.$popup.find(".meyvc-wheel-result").show();
    $result.find(".meyvc-wheel-result-text").text(
      slice.type === "win"
        ? (i18n.you_won || "You won: ") + (slice.label || "")
        : i18n.try_again || "Better luck next time!"
    );
    var cfg = getSpinConfig();
    var spinAjaxUrl = cfg.ajaxUrl || window.ajaxurl || "";
    $.post(spinAjaxUrl, {
      action: "meyvc_spin_capture",
      nonce: cfg.nonce || "",
      email: email,
      campaign_id: self.campaignId,
      spin_token: self.spinToken || "",
      spin_hour: self.spinHour || "",
      win_index: self.winningIndex,
      slice_label: slice.label || "",
    }).done(function (r) {
      if (r && r.success && r.data && r.data.coupon_code) {
        $result
          .find(".meyvc-wheel-coupon-code")
          .text(
            (r.data.coupon_code_label || "Your code") +
              ": " +
              r.data.coupon_code
          )
          .show();
      } else if (
        r &&
        r.success &&
        r.data &&
        r.data.is_win === false
      ) {
        $result
          .find(".meyvc-wheel-result-text")
          .text(i18n.try_again || "Better luck next time!");
      }
    });
  };

  function maybeInitWheel(popupEl) {
    if (!popupEl || !$(popupEl).hasClass("meyvc-popup--gamified-wheel")) {
      return;
    }
    if (popupEl.dataset.meyvcWheelInit) {
      return;
    }
    var campaignId =
      popupEl.getAttribute("data-campaign-id") ||
      popupEl.dataset.campaignId ||
      "";
    var canvasId = "meyvc-wheel-canvas-" + campaignId;
    var attempts = 0;
    function tryInit() {
      if (document.getElementById(canvasId)) {
        new MEYVCSpinWheel(popupEl);
      } else if (++attempts < 30) {
        requestAnimationFrame(tryInit);
      }
    }
    tryInit();
  }

  document.addEventListener("meyvc:campaign_shown", function (ev) {
    var d = ev.detail || {};
    var id = d.campaignId;
    if (!id) {
      return;
    }
    var el = document.querySelector(
      '.meyvc-popup--gamified-wheel[data-campaign-id="' + id + '"]'
    );
    maybeInitWheel(el);
  });

  $(function () {
    // Draw any wheel already in the DOM (shortcode, preview link, or before controller fires).
    $(".meyvc-popup--gamified-wheel").each(function () {
      maybeInitWheel(this);
    });
  });
})(jQuery);
