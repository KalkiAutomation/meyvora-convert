/**
 * Spin-to-win wheel (gamified-wheel template).
 *
 * @package Meyvora_Convert
 */
(function ($) {
  "use strict";

  function getSpinConfig() {
    return window.croSpinWheel || {};
  }

  function CROSpinWheel(popup) {
    this.$popup = $(popup);
    this.campaignId = this.$popup.data("campaign-id");
    var canvasId = "cro-wheel-canvas-" + this.campaignId;
    this.canvas = document.getElementById(canvasId);
    if (!this.canvas || !this.canvas.getContext) {
      return;
    }
    this.ctx = this.canvas.getContext("2d");
    this.slices = [];
    try {
      this.slices = JSON.parse(this.canvas.dataset.slices || "[]");
    } catch (e) {
      this.slices = [];
    }
    if (!this.slices.length) {
      return;
    }
    this.numSlices = this.slices.length;
    this.sliceAngle = (2 * Math.PI) / this.numSlices;
    this.currentAngle = 0;
    this.spinning = false;
    this.hasSpun = false;
    this.draw(0);
    this.bindEvents();
  }

  CROSpinWheel.prototype.draw = function (rotation) {
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

  CROSpinWheel.prototype.bindEvents = function () {
    var self = this;
    this.$popup.on("click", ".cro-wheel-spin-btn", function () {
      if (self.spinning || self.hasSpun) return;
      var email = self.$popup.find(".cro-wheel-email").val().trim();
      if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        self.$popup.find(".cro-wheel-email").focus();
        return;
      }
      self.fetchTokenAndSpin(email);
    });
  };

  CROSpinWheel.prototype.fetchTokenAndSpin = function (email) {
    var self = this;
    var $btn = this.$popup.find(".cro-wheel-spin-btn");
    var cfg = getSpinConfig();
    $btn.prop("disabled", true);
    $.post(
      cfg.ajaxUrl || "",
      {
        action: "cro_spin_init",
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
          self.draw(0);
        }
        self.spin(email);
      }
    ).fail(function () {
      $btn.prop("disabled", false);
    });
  };

  CROSpinWheel.prototype.spin = function (email) {
    var self = this;
    this.spinning = true;
    this.hasSpun = true;
    var winIndex =
      typeof self.winningIndex === "number" ? self.winningIndex : 0;
    var targetAngle =
      2 * Math.PI * 5 +
      (2 * Math.PI - winIndex * self.sliceAngle - self.sliceAngle / 2);
    var start = null;
    var duration = 4000;
    var startRot = self.currentAngle;
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
        self.currentAngle = (startRot + targetAngle) % (2 * Math.PI);
        self.spinning = false;
        self.onSpinEnd(email, winIndex);
      }
    })(performance.now());
  };

  CROSpinWheel.prototype.onSpinEnd = function (email, sliceIndex) {
    var self = this;
    var slice = self.slices[sliceIndex] || {};
    var i18n = window.cro_spin_i18n || {};
    self.$popup.find(".cro-wheel-email-step").hide();
    var $result = self.$popup.find(".cro-wheel-result").show();
    $result.find(".cro-wheel-result-text").text(
      slice.type === "win"
        ? (i18n.you_won || "You won: ") + (slice.label || "")
        : i18n.try_again || "Better luck next time!"
    );
    var cfg = getSpinConfig();
    $.post(cfg.ajaxUrl || "", {
      action: "cro_spin_capture",
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
          .find(".cro-wheel-coupon-code")
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
          .find(".cro-wheel-result-text")
          .text(i18n.try_again || "Better luck next time!");
      }
    });
  };

  function maybeInitWheel(popupEl) {
    if (!popupEl || !$(popupEl).hasClass("cro-popup--gamified-wheel")) {
      return;
    }
    if (popupEl.dataset.croWheelInit) {
      return;
    }
    popupEl.dataset.croWheelInit = "1";
    new CROSpinWheel(popupEl);
  }

  document.addEventListener("cro:campaign_shown", function (ev) {
    var d = ev.detail || {};
    var id = d.campaignId;
    if (!id) {
      return;
    }
    var el = document.querySelector(
      '.cro-popup--gamified-wheel[data-campaign-id="' + id + '"]'
    );
    maybeInitWheel(el);
  });

  $(function () {
    $(".cro-popup--gamified-wheel.cro-popup--preview").each(function () {
      maybeInitWheel(this);
    });
  });
})(jQuery);
