(function ($) {
  "use strict";

  function meyvcLog() {
    if (typeof meyvcAdmin !== "undefined" && meyvcAdmin.debug && typeof console !== "undefined" && console.log) {
      console.log.apply(console, ["[CRO Builder]"].concat(Array.prototype.slice.call(arguments)));
    }
  }

  if (typeof wp !== "undefined" && wp.apiFetch && typeof meyvcCampaignBuilder !== "undefined") {
    var wpApi = typeof window.wpApiSettings !== "undefined" ? window.wpApiSettings : null;
    if (!wpApi || !wpApi.nonce || !wpApi.root) {
      var restRoot = meyvcCampaignBuilder.restRoot || "";
      var restNonce = meyvcCampaignBuilder.restNonce || "";
      if (restRoot) {
        wp.apiFetch.use(wp.apiFetch.createRootURLMiddleware(restRoot));
      }
      if (restNonce) {
        wp.apiFetch.use(wp.apiFetch.createNonceMiddleware(restNonce));
      }
    }
  }

  var MEYVCBuilder = {
    campaignId: 0,
    campaignData: {},
    hasChanges: false,
    autoSaveTimeout: null,

    init: function () {
      var self = this;
      meyvcLog("init");
      var $data = $("#campaign-data");
      var $wrap = $(".meyvc-builder-wrap");
      if (typeof meyvcAdmin !== "undefined" && meyvcAdmin.debug && typeof console !== "undefined" && console.log) {
        console.log("[CRO Builder] root found:", !!$wrap.length);
        console.log("[CRO Builder] campaign-data found:", !!$data.length);
        console.log("[CRO Builder] nav found:", $(".meyvc-builder-nav .meyvc-nav-item").length);
        console.log("[CRO Builder] template grid found:", $(".meyvc-builder-wrap .meyvc-template-card").length);
        console.log("[CRO Builder] preview found:", $("#preview-frame").length);
      }
      if (!$wrap.length) {
        if (typeof window !== "undefined") window.meyvcBuilderInitStatus = { status: "FAIL", reason: "Builder wrap not found" };
        return;
      }
      if (!$data.length) {
        var $form = $wrap.closest("form");
        if ($form.length) $form.append('<input type="hidden" id="campaign-data" name="campaign_data" value=""/>');
        else $wrap.prepend('<input type="hidden" id="campaign-data" name="campaign_data" value=""/>');
        self.campaignData = { template: "centered", content: {}, styling: {}, trigger_rules: {}, targeting_rules: {}, frequency_rules: {}, schedule: {}, fallback_id: 0, fallback_delay_seconds: 5 };
        self.campaignId = parseInt($("#campaign-id").val(), 10) || 0;
        self.initNav();
        self.initTemplateSelection();
        self.initContentControls();
        self.initImageUpload();
        self.initDesignControls();
        self.initTriggerControls();
        self.populateTriggerFields();
        self.initTargetingControls();
        self.populateTargetingFields();
        self.initDisplayControls();
        self.initPreview();
        self.initSaveHandlers();
        self.initConditionalFields();
        self.updateContentFromFields();
        self.updateStylingFromFields();
        self.updatePreview();
        if (typeof window !== "undefined") window.meyvcBuilderInitStatus = { status: "OK" };
        return;
      }
      if ($data.val()) {
        try {
          var parsed = JSON.parse($data.val());
          self.campaignData = parsed && typeof parsed === "object" && !Array.isArray(parsed) ? parsed : {};
        } catch (e) {
          self.campaignData = {};
        }
      } else {
        self.campaignData = {};
      }
      self.campaignId = parseInt($("#campaign-id").val(), 10) || 0;
      if (!self.campaignData.content) {
        self.campaignData.content = {};
      }
      if (!self.campaignData.styling) {
        self.campaignData.styling = {};
      }
      if (!self.campaignData.trigger_rules) {
        self.campaignData.trigger_rules = {};
      }
      if (!self.campaignData.targeting_rules) {
        self.campaignData.targeting_rules = {};
      }
      if (!self.campaignData.frequency_rules) {
        self.campaignData.frequency_rules = {};
      }
      if (!self.campaignData.schedule) {
        self.campaignData.schedule = {};
      }
      if (self.campaignData.fallback_id == null) {
        self.campaignData.fallback_id = 0;
      }
      if (self.campaignData.fallback_delay_seconds == null) {
        self.campaignData.fallback_delay_seconds = 5;
      }

      self.initNav();
      self.initTemplateSelection();
      self.initContentControls();
      self.initImageUpload();
      self.initDesignControls();
      self.initTriggerControls();
      self.populateTriggerFields();
      self.initTargetingControls();
      self.populateTargetingFields();
      self.initDisplayControls();
      self.initPreview();
      self.initSaveHandlers();
      self.initConditionalFields();
      self.updateContentFromFields();
      self.updateStylingFromFields();
      self.updatePreview();
      if (typeof window !== "undefined") window.meyvcBuilderInitStatus = { status: "OK" };
    },

    initNav: function () {
      var self = this;
      $(document).on("click", ".meyvc-builder-nav .meyvc-nav-item", function (e) {
        e.preventDefault();
        if (!$(this).closest(".meyvc-builder-wrap").length) return;
        var section = $(this).data("section");
        meyvcLog("nav click", section);
        $(".meyvc-builder-nav .meyvc-nav-item").removeClass("active");
        $(this).addClass("active");
        $(".meyvc-builder-content .meyvc-section").removeClass("active");
        $("#section-" + section).addClass("active");
      });
    },

    initTemplateSelection: function () {
      var self = this;
      $(".meyvc-builder-wrap").on("click", ".meyvc-template-card", function (e) {
        e.preventDefault();
        e.stopPropagation();
        if ($(e.target).closest(".meyvc-template-preview-btn").length) return;
        var $card = $(e.target).closest(".meyvc-template-card");
        if (!$card.length) return;
        var template = $card.attr("data-template") || $card.data("template");
        if (!template) return;
        meyvcLog("template selected", template);
        $(".meyvc-builder-wrap .meyvc-template-card").removeClass("selected");
        $card.addClass("selected");
        self.campaignData.template = template;
        self.syncCampaignDataToHiddenInput();
        self.markChanged();
        self.updatePreview();
      });

      $(".meyvc-builder-wrap").on("click", ".meyvc-template-preview-btn", function (e) {
        e.preventDefault();
        e.stopPropagation();
        var template = $(this).attr("data-template") || $(this).data("template");
        if (!template) return;
        self.campaignData.template = template;
        self.syncCampaignDataToHiddenInput();
        self.updatePreview();
        self.openLivePreview();
      });
    },

    initContentControls: function () {
      var self = this;
      var ctaActionLabels = {
        close: "Got it",
        url: "Learn more",
        cart: "View cart",
        checkout: "Go to checkout",
        apply_coupon: "Apply discount",
        apply_coupon_checkout: "Apply & checkout",
        copy_coupon: "Copy code",
      };
      $(
        "#content-tone, #content-headline, #content-subheadline, #content-body, #content-image, #content-cta-text, #content-cta-url, #content-cta-action, #content-coupon-code, #content-coupon-label, #content-email-placeholder, #content-countdown-minutes, #content-dismiss-text"
      ).on("input change", function () {
        self.updateContentFromFields();
        self.markChanged();
        self.updatePreview();
      });
      $(
        "#content-show-coupon, #content-show-email, #content-show-countdown, #content-show-dismiss, #content-auto-apply-coupon"
      ).on("change", function () {
        self.updateContentFromFields();
        self.markChanged();
        self.updatePreview();
      });
      $("#content-cta-action").on("change", function () {
        var action = $(this).val();
        var $ctaInput = $("#content-cta-text");
        if (!$ctaInput.val() && ctaActionLabels[action]) {
          $ctaInput.attr("placeholder", ctaActionLabels[action]);
        }
        self.updateCtaActionUi();
      });
      (function syncCtaUi() {
        var action = $("#content-cta-action").val();
        var $ctaInput = $("#content-cta-text");
        if (!$ctaInput.val() && ctaActionLabels[action]) {
          $ctaInput.attr("placeholder", ctaActionLabels[action]);
        }
        self.updateCtaActionUi();
      })();
    },

    updateContentFromFields: function () {
      this.campaignData.content = {
        tone: $("#content-tone").val() || "neutral",
        headline: $("#content-headline").val(),
        subheadline: $("#content-subheadline").val(),
        body: $("#content-body").val(),
        image_url: $("#content-image").val() || "",
        cta_text: $("#content-cta-text").val(),
        cta_url: $("#content-cta-url").val(),
        cta_action: $("#content-cta-action").val(),
        show_coupon: $("#content-show-coupon").is(":checked"),
        coupon_code: $("#content-coupon-code").val(),
        coupon_label: $("#content-coupon-label").val(),
        auto_apply_coupon: $("#content-auto-apply-coupon").is(":checked"),
        show_email_field: $("#content-show-email").is(":checked"),
        email_placeholder: $("#content-email-placeholder").val(),
        show_countdown: $("#content-show-countdown").is(":checked"),
        countdown_minutes:
          parseInt($("#content-countdown-minutes").val(), 10) || 15,
        show_dismiss_link: $("#content-show-dismiss").is(":checked"),
        dismiss_text: $("#content-dismiss-text").val(),
      };
      this.markChanged();
    },

    initImageUpload: function () {
      var self = this;
      var $upload = $("#campaign-image-upload");
      var $preview = $("#image-preview");
      var $input = $("#content-image");
      var mediaFrame = null;

      if (!$upload.length || !$input.length) {
        return;
      }

      function getAttachmentUrl(attachment) {
        if (!attachment) return "";
        var att = attachment.toJSON ? attachment.toJSON() : attachment;
        if (att.url) return att.url;
        if (att.sizes && att.sizes.full && att.sizes.full.url)
          return att.sizes.full.url;
        if (att.attributes && att.attributes.url) return att.attributes.url;
        return "";
      }

      function openMediaModal() {
        if (typeof wp === "undefined" || !wp.media) {
          if (typeof console !== "undefined") {
            console.warn(
              "CRO: wp.media not available. Ensure you are on the campaign edit page."
            );
          }
          return;
        }
        if (mediaFrame) {
          mediaFrame.open();
          return;
        }
        var selectText =
          typeof meyvcAdmin !== "undefined" &&
          meyvcAdmin.strings &&
          meyvcAdmin.strings.useImage
            ? meyvcAdmin.strings.useImage
            : "Use this image";
        var titleText =
          typeof meyvcAdmin !== "undefined" &&
          meyvcAdmin.strings &&
          meyvcAdmin.strings.selectImage
            ? meyvcAdmin.strings.selectImage
            : "Select or Upload Image";
        mediaFrame = wp.media({
          title: titleText,
          library: { type: "image" },
          button: { text: selectText },
          multiple: false,
        });
        mediaFrame.on("select", function () {
          var selection = mediaFrame.state().get("selection");
          if (!selection || !selection.first) return;
          var attachment = selection.first();
          var url = getAttachmentUrl(attachment);
          if (url) {
            $input.val(url);
            $preview.html(
              '<img src="' +
                self.escapeHtml(url) +
                '" alt="" /><button type="button" class="meyvc-remove-image" aria-label="Remove">' + ((window.meyvcBuilderIcons && window.meyvcBuilderIcons.remove) || "×") + "</button>"
            );
            var $btn = $("#meyvc-select-image-btn");
            if ($btn.length)
              $btn.html(
                (window.meyvcBuilderIcons && window.meyvcBuilderIcons.image ? window.meyvcBuilderIcons.image + " " : "") + "Change image"
              );
            self.updateContentFromFields();
            self.markChanged();
            self.updatePreview();
          }
        });
        mediaFrame.open();
      }

      // Click on upload area (except remove button) opens media modal
      $upload.on("click", function (e) {
        if (
          $(e.target).closest(".meyvc-remove-image").length ||
          $(e.target).closest(".meyvc-select-image-btn").length
        ) {
          return;
        }
        e.preventDefault();
        e.stopPropagation();
        openMediaModal();
      });

      // Explicit "Select image" / "Change image" button
      $(document).on("click", "#meyvc-select-image-btn", function (e) {
        e.preventDefault();
        e.stopPropagation();
        openMediaModal();
      });

      // Remove image
      $(document).on("click", ".meyvc-remove-image", function (e) {
        e.preventDefault();
        e.stopPropagation();
        if ($(this).closest("#campaign-image-upload").length === 0) {
          return;
        }
        $input.val("");
        $preview.html(
          "<span class=\"meyvc-upload-placeholder\">" +
            (window.meyvcBuilderIcons && window.meyvcBuilderIcons.upload ? window.meyvcBuilderIcons.upload : "") +
            (typeof meyvcAdmin !== "undefined" &&
            meyvcAdmin.strings &&
            meyvcAdmin.strings.clickToUpload
              ? meyvcAdmin.strings.clickToUpload
              : "Click to upload") +
            "</span>"
        );
        var $btn = $("#meyvc-select-image-btn");
        if ($btn.length)
          $btn.html(
            (window.meyvcBuilderIcons && window.meyvcBuilderIcons.image ? window.meyvcBuilderIcons.image + " " : "") + "Select image"
          );
        self.updateContentFromFields();
        self.markChanged();
        self.updatePreview();
      });
    },

    initDesignControls: function () {
      var self = this;
      $(
        "#design-bg-color, #design-text-color, #design-headline-color, #design-button-bg, #design-button-text, #design-overlay-color, #design-overlay-opacity, #design-border-radius, #design-animation, #design-position, #design-custom-css"
      ).on("change input", function () {
        self.updateStylingFromFields();
        self.markChanged();
        self.updatePreview();
      });
      $('input[name="design-size"]').on("change", function () {
        self.updateStylingFromFields();
        self.markChanged();
        self.updatePreview();
      });
      $(".meyvc-position-btn").on("click", function (e) {
        e.preventDefault();
        $(".meyvc-position-btn").removeClass("active");
        $(this).addClass("active");
        $("#design-position").val($(this).data("position"));
        self.updateStylingFromFields();
        self.markChanged();
        self.updatePreview();
      });
      $("#design-overlay-opacity").on("input", function () {
        $("#overlay-opacity-value").text($(this).val());
        self.updateStylingFromFields();
        self.updatePreview();
      });
      $("#design-border-radius").on("input", function () {
        $("#border-radius-value").text($(this).val());
        self.updateStylingFromFields();
        self.updatePreview();
      });
    },

    updateStylingFromFields: function () {
      this.campaignData.styling = {
        bg_color: $("#design-bg-color").val(),
        text_color: $("#design-text-color").val(),
        headline_color: $("#design-headline-color").val(),
        button_bg_color: $("#design-button-bg").val(),
        button_text_color: $("#design-button-text").val(),
        overlay_color: $("#design-overlay-color").val(),
        overlay_opacity: parseInt($("#design-overlay-opacity").val(), 10) || 50,
        border_radius: parseInt($("#design-border-radius").val(), 10) || 8,
        size: $('input[name="design-size"]:checked').val(),
        animation: $("#design-animation").val(),
        position: $("#design-position").val(),
        custom_css: $("#design-custom-css").val(),
      };
      this.markChanged();
    },

    // === TRIGGER CONTROLS ===

    /** Radios for campaign trigger (scoped to builder so we never read duplicate names elsewhere). */
    getTriggerTypeInputs: function () {
      var $w = $(".meyvc-builder-wrap input[name='trigger-type']");
      return $w.length ? $w : $('input[name="trigger-type"]');
    },

    initTriggerControls: function () {
      var self = this;

      this.getTriggerTypeInputs().on("change", function () {
        var type = $(this).val();
        $(".meyvc-builder-wrap .meyvc-trigger-options").hide();
        $('.meyvc-builder-wrap .meyvc-trigger-options[data-trigger="' + type + '"]').show();
        self.updateTriggerFromFields();
        self.markChanged();
      });

      $(
        "#trigger-sensitivity, #trigger-scroll-depth, #trigger-time-delay, #trigger-idle-time, #trigger-click-selector, #trigger-delay"
      ).on("change input", function () {
        if (this.id === "trigger-scroll-depth") {
          $("#scroll-depth-value").text($(this).val());
        }
        self.updateTriggerFromFields();
        self.markChanged();
      });

      $("#trigger-mobile-exit").on("change", function () {
        self.updateTriggerFromFields();
        self.markChanged();
      });

      $("#show-intent-settings").on("change", function () {
        $("#intent-settings").toggle($(this).is(":checked"));
      });

      $("#trigger-intent-threshold").on("input", function () {
        $("#intent-threshold-value").text($(this).val());
      });

      $('.meyvc-signal-weight input[type="range"]').on("input", function () {
        $(this).siblings("span").text($(this).val());
        self.updateTriggerFromFields();
        self.markChanged();
      });

      // Show options for currently selected trigger type on load
      var initialType = self.getTriggerTypeInputs().filter(":checked").val();
      if (initialType) {
        $(".meyvc-builder-wrap .meyvc-trigger-options").hide();
        $('.meyvc-builder-wrap .meyvc-trigger-options[data-trigger="' + initialType + '"]').show();
      }
    },

    populateTriggerFields: function () {
      var tr = this.campaignData.trigger_rules || {};
      var t =
        (tr.type && String(tr.type)) ||
        (this.campaignData.type && String(this.campaignData.type)) ||
        "exit_intent";
      var $inputs = this.getTriggerTypeInputs();
      $inputs.prop("checked", false);
      $inputs.filter(function () {
        return $(this).val() === t;
      }).prop("checked", true);
      if (tr.time_delay_seconds !== undefined) {
        $("#trigger-time-delay").val(tr.time_delay_seconds);
      }
      if (tr.time_delay !== undefined && tr.time_delay_seconds === undefined) {
        $("#trigger-time-delay").val(tr.time_delay);
      }
      if (tr.scroll_depth_percent !== undefined) {
        $("#trigger-scroll-depth").val(tr.scroll_depth_percent);
        $("#scroll-depth-value").text(tr.scroll_depth_percent);
      }
      if (tr.scroll_depth !== undefined && tr.scroll_depth_percent === undefined) {
        $("#trigger-scroll-depth").val(tr.scroll_depth);
        $("#scroll-depth-value").text(tr.scroll_depth);
      }
      if (tr.idle_seconds !== undefined) {
        $("#trigger-idle-time").val(tr.idle_seconds);
      }
      if (tr.idle_time !== undefined && tr.idle_seconds === undefined) {
        $("#trigger-idle-time").val(tr.idle_time);
      }
      if (tr.sensitivity) {
        $("#trigger-sensitivity").val(tr.sensitivity);
      }
      if (tr.delay_seconds !== undefined) {
        $("#trigger-delay").val(tr.delay_seconds);
      }
      if (tr.click_selector) {
        $("#trigger-click-selector").val(tr.click_selector);
      }
      if (tr.intent_threshold !== undefined) {
        $("#trigger-intent-threshold").val(tr.intent_threshold);
        $("#intent-threshold-value").text(tr.intent_threshold);
      }
      if (tr.enable_mobile_exit !== undefined) {
        $("#trigger-mobile-exit").prop("checked", !!tr.enable_mobile_exit);
      }
      if (tr.use_custom_intent !== undefined) {
        $("#show-intent-settings").prop("checked", !!tr.use_custom_intent);
        $("#intent-settings").toggle(!!tr.use_custom_intent);
      }
      if (tr.intent_weights && typeof tr.intent_weights === "object") {
        Object.keys(tr.intent_weights).forEach(function (key) {
          var $r = $('.meyvc-signal-weight input[data-signal="' + key + '"]');
          if ($r.length) {
            var v = tr.intent_weights[key];
            $r.val(v);
            $r.siblings("span").first().text(String(v));
          }
        });
      }
      this.getTriggerTypeInputs().filter(":checked").trigger("change");
    },

    updateTriggerFromFields: function () {
      var triggerType =
        this.getTriggerTypeInputs().filter(":checked").val() || "exit_intent";
      this.campaignData.type = triggerType;
      this.campaignData.trigger_rules = {
        type: triggerType,
        sensitivity: $("#trigger-sensitivity").val(),
        enable_mobile_exit: $("#trigger-mobile-exit").is(":checked"),
        scroll_depth_percent:
          parseInt($("#trigger-scroll-depth").val(), 10) || 50,
        time_delay_seconds: parseInt($("#trigger-time-delay").val(), 10) || 10,
        idle_seconds: parseInt($("#trigger-idle-time").val(), 10) || 30,
        click_selector: $("#trigger-click-selector").val(),
        delay_seconds: parseInt($("#trigger-delay").val(), 10) || 0,
        use_custom_intent: $("#show-intent-settings").is(":checked"),
        intent_threshold:
          parseInt($("#trigger-intent-threshold").val(), 10) || 60,
        intent_weights: this.getIntentSignals(),
      };
    },

    getIntentSignals: function () {
      var weights = {};
      $('.meyvc-signal-weight input[type="range"]').each(function () {
        var key = $(this).data("signal");
        if (key) {
          weights[key] = parseInt($(this).val(), 10) || 0;
        }
      });
      return weights;
    },

    // === TARGETING CONTROLS ===

    initTargetingControls: function () {
      var self = this;

      $("#targeting-page-mode").on("change", function () {
        var mode = $(this).val();
        $("#page-include-selector, #page-exclude-selector").hide();
        if (mode === "include") {
          $("#page-include-selector").show();
        } else if (mode === "exclude") {
          $("#page-exclude-selector").show();
        }
        self.updateTargetingFromFields();
        self.markChanged();
      });

      var targetingFields = [
        "targeting-visitor-type",
        "targeting-cart-status",
        "targeting-cart-min",
        "targeting-cart-max",
        "targeting-min-time",
        "targeting-min-scroll",
        "targeting-referrer",
        "targeting-referrer-op",
        "targeting-utm-source",
        "targeting-utm-source-op",
        "targeting-utm-medium",
        "targeting-utm-medium-op",
        "targeting-utm-campaign",
        "targeting-utm-campaign-op",
        "targeting-min-sessions",
        "targeting-max-sessions",
        "targeting-min-pages",
      ];
      targetingFields.forEach(function (fieldId) {
        $("#" + fieldId).on("change input", function () {
          self.updateTargetingFromFields();
          self.markChanged();
        });
      });

      $(
        'input[name="pages[]"], input[name="exclude-pages[]"], input[name="devices[]"]'
      ).on("change", function () {
        self.updateTargetingFromFields();
        self.markChanged();
      });

      $("#targeting-exclude-purchased, #targeting-require-interaction").on(
        "change",
        function () {
          self.updateTargetingFromFields();
          self.markChanged();
        }
      );

      // SelectWoo for targeting selects is initialized by meyvc-selectwoo.js (.meyvc-selectwoo). Ensure init runs (e.g. if builder loads first).
      if ($.fn.selectWoo || $.fn.select2) {
        $(document).trigger("meyvc-select-woo-init");
      }
      $("#targeting-specific-pages, #targeting-cart-contains, #targeting-cart-category, #targeting-cart-exclude-product, #targeting-cart-exclude-category, #targeting-geo-countries").on("change", function () {
        self.updateTargetingFromFields();
        self.markChanged();
      });
    },

    populateTargetingFields: function () {
      var tr = this.campaignData.targeting_rules || {};
      if (tr.page_mode) {
        $("#targeting-page-mode").val(tr.page_mode).trigger("change");
      }
      var geo = tr.geo_countries;
      if (Array.isArray(geo) && geo.length) {
        $("#targeting-geo-countries").val(geo.map(String));
      } else {
        $("#targeting-geo-countries").val(null);
      }
      $("#targeting-geo-countries").trigger("change");

      $("#targeting-referrer").val(tr.referrer || "");
      $("#targeting-referrer-op").val(tr.referrer_op || "").trigger("change");
      $("#targeting-utm-source").val(tr.utm_source || "");
      $("#targeting-utm-source-op").val(tr.utm_source_op || "").trigger("change");
      $("#targeting-utm-medium").val(tr.utm_medium || "");
      $("#targeting-utm-medium-op").val(tr.utm_medium_op || "").trigger("change");
      $("#targeting-utm-campaign").val(tr.utm_campaign || "");
      $("#targeting-utm-campaign-op").val(tr.utm_campaign_op || "").trigger("change");
    },

    updateTargetingFromFields: function () {
      var pageMode = $("#targeting-page-mode").val() || "all";
      var rules = {
        audience_mode: $('input[name="targeting-mode"]:checked').val() || "all",
        page_mode: pageMode,
        pages: { include: [], excluded_pages: ["checkout"] },
        visitor: {
          type: $("#targeting-visitor-type").val() || "all",
          min_sessions: "",
          max_sessions: "",
        },
        device: { desktop: true, tablet: true, mobile: true },
        behavior: {},
        referrer: $("#targeting-referrer").val() || "",
        referrer_op: $("#targeting-referrer-op").val() || "",
        utm_source: $("#targeting-utm-source").val() || "",
        utm_source_op: $("#targeting-utm-source-op").val() || "",
        utm_medium: $("#targeting-utm-medium").val() || "",
        utm_medium_op: $("#targeting-utm-medium-op").val() || "",
        utm_campaign: $("#targeting-utm-campaign").val() || "",
        utm_campaign_op: $("#targeting-utm-campaign-op").val() || "",
        exclude_purchased: $("#targeting-exclude-purchased").is(":checked"),
      };

      if (pageMode === "include") {
        $('input[name="pages[]"]:checked').each(function () {
          rules.pages.include.push($(this).val());
        });
      } else if (pageMode === "exclude") {
        rules.pages.excluded_pages = ["checkout"];
        $('input[name="exclude-pages[]"]:checked').each(function () {
          if ($(this).val() !== "checkout") {
            rules.pages.excluded_pages.push($(this).val());
          }
        });
      }

      rules.visitor.type = $("#targeting-visitor-type").val() || "all";
      rules.visitor.min_sessions = $("#targeting-min-sessions").val() || "";
      rules.visitor.max_sessions = $("#targeting-max-sessions").val() || "";

      rules.device.desktop = $('input[name="devices[]"][value="desktop"]').is(
        ":checked"
      );
      rules.device.tablet = $('input[name="devices[]"][value="tablet"]').is(
        ":checked"
      );
      rules.device.mobile = $('input[name="devices[]"][value="mobile"]').is(
        ":checked"
      );

      var cartStatus = $("#targeting-cart-status").val();
      rules.behavior.cart_status = cartStatus || "any";
      rules.behavior.cart_min_value =
        parseFloat($("#targeting-cart-min").val()) || 0;
      rules.behavior.cart_max_value =
        parseFloat($("#targeting-cart-max").val()) || 0;
      rules.behavior.cart_min_items =
        parseInt($("#targeting-cart-min-items").val(), 10) || "";
      rules.behavior.cart_max_items =
        parseInt($("#targeting-cart-max-items").val(), 10) || "";
      rules.behavior.cart_has_sale_only = $("#targeting-cart-has-sale").is(
        ":checked"
      );
      var cartContains = $("#targeting-cart-contains").val();
      rules.behavior.cart_contains_product = Array.isArray(cartContains) ? cartContains : (cartContains ? [cartContains] : []);
      var cartCategory = $("#targeting-cart-category").val();
      rules.behavior.cart_contains_category = Array.isArray(cartCategory) ? cartCategory : (cartCategory ? [cartCategory] : []);
      var cartExcludeProduct = $("#targeting-cart-exclude-product").val();
      rules.behavior.cart_exclude_product = Array.isArray(cartExcludeProduct) ? cartExcludeProduct : (cartExcludeProduct ? [cartExcludeProduct] : []);
      var cartExcludeCategory = $("#targeting-cart-exclude-category").val();
      rules.behavior.cart_exclude_category = Array.isArray(cartExcludeCategory) ? cartExcludeCategory : (cartExcludeCategory ? [cartExcludeCategory] : []);
      rules.behavior.min_time_on_page =
        parseInt($("#targeting-min-time").val(), 10) || 0;
      rules.behavior.min_scroll_depth =
        parseInt($("#targeting-min-scroll").val(), 10) || 0;
      rules.behavior.min_pages_viewed =
        parseInt($("#targeting-min-pages").val(), 10) || 0;
      rules.behavior.require_interaction = $(
        "#targeting-require-interaction"
      ).is(":checked");

      var geoVal = $("#targeting-geo-countries").val();
      rules.geo_countries = Array.isArray(geoVal) ? geoVal : geoVal ? [geoVal] : [];

      this.campaignData.targeting_rules = rules;
      this.campaignData.targeting_rules.page_mode = pageMode;
    },

    // === DISPLAY RULES ===

    initDisplayControls: function () {
      var self = this;

      $("#display-frequency").on("change", function () {
        var freq = $(this).val();
        $(
          '.meyvc-conditional[data-show-when="display-frequency=once_per_x_days"]'
        ).toggle(freq === "once_per_x_days");
        self.updateDisplayFromFields();
        self.markChanged();
      });

      $(
        "#display-frequency-days, #display-cooldown, #display-max-impressions, #display-frequency-period-value, #display-frequency-period-unit, #display-cooldown-conversion, #display-cooldown-click, #display-priority, #display-brand-override-use, #display-brand-primary, #display-brand-secondary, #display-brand-button-radius, #display-brand-font-scale"
      ).on("change input", function () {
        self.updateDisplayFromFields();
        self.markChanged();
      });

      $("#display-schedule-enabled").on("change", function () {
        $("#schedule-options").toggle($(this).is(":checked"));
        self.updateDisplayFromFields();
        self.markChanged();
      });

      $(
        "#display-start-date, #display-end-date, #display-time-start, #display-time-end"
      ).on("change", function () {
        self.updateDisplayFromFields();
        self.markChanged();
      });

      $('input[name="schedule-days[]"]').on("change", function () {
        self.updateDisplayFromFields();
        self.markChanged();
      });

      $("#display-priority").on("input", function () {
        $("#priority-value").text($(this).val());
      });

      $("#display-fallback-id").on("change", function () {
        var v = parseInt($("#display-fallback-id").val(), 10) || 0;
        $("#meyvc-fallback-delay-row-builder").toggle(v > 0);
        self.updateDisplayFromFields();
        self.markChanged();
      });

      $("#display-fallback-delay").on("change input", function () {
        self.updateDisplayFromFields();
        self.markChanged();
      });

      $(
        "#display-is-fallback, #display-auto-pause, #display-after-conversion, #display-target-conversions, #display-target-impressions, #display-target-revenue, #display-hide-days, #display-followup-campaign"
      ).on("change input", function () {
        self.updateDisplayFromFields();
        self.markChanged();
      });

      var fbInit = parseInt(self.campaignData.fallback_id, 10) || 0;
      $("#display-fallback-id").val(String(fbInit));
      $("#display-fallback-delay").val(
        String(
          self.campaignData.fallback_delay_seconds != null
            ? self.campaignData.fallback_delay_seconds
            : 5
        )
      );
      $("#meyvc-fallback-delay-row-builder").toggle(fbInit > 0);
    },

    updateDisplayFromFields: function () {
      var cooldownHours = parseInt($("#display-cooldown").val(), 10) || 1;
      var cooldownConvHours = parseInt($("#display-cooldown-conversion").val(), 10) || 0;
      var cooldownClickHours = parseInt($("#display-cooldown-click").val(), 10) || 1;
      this.campaignData.frequency_rules = {
        frequency: $("#display-frequency").val() || "once_per_session",
        frequency_days: parseInt($("#display-frequency-days").val(), 10) || 7,
        dismissal_cooldown_seconds: cooldownHours * 3600,
        max_impressions_per_visitor:
          parseInt($("#display-max-impressions").val(), 10) || 0,
        frequency_period_value: parseInt($("#display-frequency-period-value").val(), 10) || 24,
        frequency_period_unit: $("#display-frequency-period-unit").val() || "hours",
        cooldown_after_conversion_seconds: cooldownConvHours * 3600,
        cooldown_after_click_seconds: cooldownClickHours * 3600,
        priority: parseInt($("#display-priority").val(), 10) || 10,
        is_fallback: $("#display-is-fallback").is(":checked"),
        auto_pause_type: $("#display-auto-pause").val() || "none",
        target_conversions:
          parseInt($("#display-target-conversions").val(), 10) || 100,
        target_impressions:
          parseInt($("#display-target-impressions").val(), 10) || 1000,
        target_revenue: parseFloat($("#display-target-revenue").val()) || 0,
        after_conversion:
          $("#display-after-conversion").val() || "hide_forever",
        hide_days: parseInt($("#display-hide-days").val(), 10) || 30,
        followup_campaign_id: $("#display-followup-campaign").val() || "",
      };

      var scheduleDays = [];
      $('input[name="schedule-days[]"]:checked').each(function () {
        scheduleDays.push(parseInt($(this).val(), 10));
      });

      var timeStart = $("#display-time-start").val() || "00:00";
      var timeEnd = $("#display-time-end").val() || "23:59";
      var startHours = parseInt(timeStart.split(":")[0], 10);
      var endHours =
        timeEnd === "23:59" ? 24 : parseInt(timeEnd.split(":")[0], 10);

      this.campaignData.schedule = {
        enabled: $("#display-schedule-enabled").is(":checked"),
        start_date: $("#display-start-date").val() || "",
        end_date: $("#display-end-date").val() || "",
        days_of_week: scheduleDays.length
          ? scheduleDays
          : [0, 1, 2, 3, 4, 5, 6],
        hours: { start: startHours, end: endHours },
      };

      var useBrandOverride = $("#display-brand-override-use").is(":checked");
      this.campaignData.brand_styles_override = {
        use: useBrandOverride,
        primary_color: useBrandOverride ? ($("#display-brand-primary").val() || "").trim() : "",
        secondary_color: useBrandOverride ? ($("#display-brand-secondary").val() || "").trim() : "",
        button_radius: useBrandOverride ? ($("#display-brand-button-radius").val() || "").trim() : "",
        font_size_scale: useBrandOverride ? ($("#display-brand-font-scale").val() || "").trim() : "",
      };

      this.campaignData.priority = this.campaignData.frequency_rules.priority;

      this.campaignData.fallback_id =
        parseInt($("#display-fallback-id").val(), 10) || 0;
      var fbd = parseInt($("#display-fallback-delay").val(), 10);
      if (isNaN(fbd)) {
        fbd = 5;
      }
      this.campaignData.fallback_delay_seconds = Math.max(
        0,
        Math.min(300, fbd)
      );
    },

    // === PREVIEW ===

    initPreview: function () {
      var self = this;
      var STORAGE_KEY = "meyvc_preview_device";
      var validDevices = ["desktop", "tablet", "mobile"];

      function applyDevice(device) {
        if (validDevices.indexOf(device) === -1) device = "desktop";
        var $frame = $("#preview-frame");
        var $container = $("#preview-container");
        var $buttons = $(".meyvc-preview-device-toggle button");
        $frame.removeClass("desktop tablet mobile").addClass(device);
        $container.removeClass("meyvc-preview-container--desktop meyvc-preview-container--tablet meyvc-preview-container--mobile").addClass("meyvc-preview-container--" + device);
        $buttons.removeClass("active").filter('[data-device="' + device + '"]').addClass("active");
        try {
          localStorage.setItem(STORAGE_KEY, device);
        } catch (e) {}
      }

      var saved = "desktop";
      try {
        saved = localStorage.getItem(STORAGE_KEY) || "desktop";
        if (validDevices.indexOf(saved) === -1) saved = "desktop";
      } catch (e) {}
      applyDevice(saved);

      $(document).on("click", ".meyvc-preview-device-toggle button", function () {
        var device = $(this).data("device");
        applyDevice(device);
      });

      $(document).on("click", "#preview-btn", function () {
        if ($(this).closest(".meyvc-builder-wrap").length) self.openLivePreview();
      });

      $(document).on("click", "#preview-new-tab-btn, #preview-panel-new-tab-btn", function () {
        if ($(this).closest(".meyvc-builder-wrap").length) self.openLivePreview();
      });

      $(document).on("click", "#copy-preview-link-btn", function () {
        if ($(this).closest(".meyvc-builder-wrap").length) self.copyPreviewLink();
      });
    },

    updatePreview: function () {
      var previewHtml = this.generatePreviewHtml();
      var template = (this.campaignData.template || "centered")
        .replace(/\s+/g, "-")
        .replace(/_/g, "-");
      var $frame = $("#preview-frame");
      if ($frame.length) {
        $frame.html(previewHtml);
        var classes = ($frame.attr("class") || "")
          .replace(/\bmeyvc-preview-frame--\S+/g, "")
          .replace(/\s+/g, " ")
          .trim();
        $frame.attr(
          "class",
          (classes + " meyvc-preview-frame--" + template).trim()
        );
      }
      var self = this;
      clearTimeout(this._serverPreviewTimer);
      this._serverPreviewTimer = setTimeout(function () {
        if (
          typeof meyvcBuilderAjax === "undefined" ||
          !meyvcBuilderAjax.ajaxUrl ||
          !meyvcBuilderAjax.nonce
        ) {
          return;
        }
        $.post(meyvcBuilderAjax.ajaxUrl, {
          action: "meyvc_preview_campaign",
          nonce: meyvcBuilderAjax.nonce,
          data: JSON.stringify(self.campaignData),
        }).done(function (r) {
          if (!r || !r.success || !r.data || !r.data.html) {
            return;
          }
          var html = r.data.html;
          var splitOn =
            $("#meyvc-builder-split").attr("data-mode") === "split";
          var iframe = document.getElementById("meyvc-builder-live-preview");
          if (splitOn && iframe) {
            var doc = iframe.contentDocument || iframe.contentWindow.document;
            doc.open();
            doc.write(html);
            doc.close();
          } else if ($frame.length) {
            $frame.html(html);
            var cl = ($frame.attr("class") || "")
              .replace(/\bmeyvc-preview-frame--\S+/g, "")
              .replace(/\s+/g, " ")
              .trim();
            $frame.attr(
              "class",
              (cl + " meyvc-preview-frame--" + template).trim()
            );
          }
        });
      }, 300);
    },

    generatePreviewHtml: function () {
      var data = this.campaignData;
      var content = data.content || {};
      var styling = data.styling || {};
      // Normalize template id for CSS class (e.g. top_bar -> top-bar)
      var template = (data.template || "centered")
        .replace(/\s+/g, "-")
        .replace(/_/g, "-");

      // Map template names to frontend CSS class names
      var templateClassMap = {
        centered: "centered",
        "centered-image-left": "image-left",
        "centered-image-right": "image-right",
        fullscreen: "fullscreen",
        "slide-bottom": "slide-bottom",
        corner: "corner",
        minimal: "minimal",
        "top-bar": "top-bar",
        "bottom-bar": "bottom-bar",
      };
      var frontendTemplate = templateClassMap[template] || template;

      // Build inline styles matching frontend MEYVC_Templates::get_inline_styles
      var popupStyleParts = [];
      if (styling.bg_color)
        popupStyleParts.push("background-color:" + styling.bg_color);
      if (styling.text_color)
        popupStyleParts.push("color:" + styling.text_color);
      if (styling.border_radius)
        popupStyleParts.push(
          "border-radius:" + parseInt(styling.border_radius, 10) + "px"
        );
      var popupStyles = popupStyleParts.join("; ");

      var headlineStyles = styling.headline_color
        ? "color:" + styling.headline_color
        : "";

      // Button styles matching frontend MEYVC_Templates::get_button_styles
      var buttonStyleParts = [];
      if (styling.button_bg_color)
        buttonStyleParts.push("background-color:" + styling.button_bg_color);
      if (styling.button_text_color)
        buttonStyleParts.push("color:" + styling.button_text_color);
      if (styling.border_radius)
        buttonStyleParts.push(
          "border-radius:" +
            Math.floor(parseInt(styling.border_radius, 10) / 2) +
            "px"
        );
      var buttonStyles = buttonStyleParts.join("; ");

      // Determine close button class (light for fullscreen)
      var closeClass =
        template === "fullscreen"
          ? "meyvc-popup__close meyvc-popup__close--light"
          : "meyvc-popup__close";
      var campaignId = data.id || "preview";

      // Viewport wrapper: match each PHP template (some have no overlay)
      var viewportClass = "meyvc-preview-viewport";
      if (template === "fullscreen")
        viewportClass += " meyvc-preview-viewport--fullscreen";
      else if (template === "top-bar" || template === "bottom-bar")
        viewportClass += " meyvc-preview-viewport--bar";
      if (template === "bottom-bar")
        viewportClass += " meyvc-preview-viewport--bar-bottom";
      else if (template === "slide-bottom")
        viewportClass += " meyvc-preview-viewport--slide-bottom";
      var html = '<div class="' + viewportClass + '">';
      // Bar, corner, and fullscreen PHP templates do not output overlay div
      var hasOverlay =
        template !== "top-bar" &&
        template !== "bottom-bar" &&
        template !== "corner" &&
        template !== "fullscreen";
      if (hasOverlay) {
        html += '<div class="meyvc-preview-overlay"></div>';
      }

      // Build popup HTML per template (structure and attributes differ)
      html += this.buildPreviewPopupByTemplate(
        template,
        frontendTemplate,
        content,
        styling,
        popupStyles,
        headlineStyles,
        buttonStyles,
        closeClass,
        campaignId
      );

      html += "</div>"; // close meyvc-preview-viewport
      return html;
    },

    buildPreviewPopupByTemplate: function (
      template,
      frontendTemplate,
      content,
      styling,
      popupStyles,
      headlineStyles,
      buttonStyles,
      closeClass,
      campaignId
    ) {
      var self = this;
      var popupAttrs =
        ' class="meyvc-popup meyvc-popup--' +
        frontendTemplate +
        ' meyvc-popup--active meyvc-popup--preview"';
      if (popupStyles) popupAttrs += ' style="' + popupStyles + '"';

      // Top Bar / Bottom Bar: role="alert" only, inner first, close last, no image
      if (template === "top-bar" || template === "bottom-bar") {
        popupAttrs +=
          ' role="alert" data-campaign-id="' +
          this.escapeHtml(campaignId) +
          '"';
        var inner = this.buildPreviewContentBlock(
          content,
          headlineStyles,
          buttonStyles,
          campaignId,
          template
        );
        return (
          "<div" +
          popupAttrs +
          '>\n    <!-- Content -->\n    <div class="meyvc-popup__inner">\n        ' +
          inner +
          "\n    </div>\n    <!-- Close Button -->\n    " +
          '<button type="button" class="' +
          closeClass +
          '" aria-label="Close" data-action="close">' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
          '<path d="M18 6L6 18M6 6l12 12"/>' +
          "</svg></button>\n</div>"
        );
      }

      // Corner: role="dialog" aria-modal="false" aria-labelledby, close first, no image
      if (template === "corner") {
        popupAttrs +=
          ' role="dialog" aria-modal="false" aria-labelledby="meyvc-headline-' +
          this.escapeHtml(campaignId) +
          '" data-campaign-id="' +
          this.escapeHtml(campaignId) +
          '"';
        var cornerInner = this.buildPreviewContentBlock(
          content,
          headlineStyles,
          buttonStyles,
          campaignId,
          template
        );
        return (
          "<div" +
          popupAttrs +
          ">\n    <!-- Close Button -->\n    " +
          '<button type="button" class="' +
          closeClass +
          '" aria-label="Close" data-action="close">' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
          '<path d="M18 6L6 18M6 6l12 12"/>' +
          '</svg></button>\n    <!-- Content -->\n    <div class="meyvc-popup__inner">\n        ' +
          cornerInner +
          "\n    </div>\n</div>"
        );
      }

      // Dialog templates: role="dialog" aria-modal="true" aria-labelledby, close first
      popupAttrs +=
        ' role="dialog" aria-modal="true" aria-labelledby="meyvc-headline-' +
        this.escapeHtml(campaignId) +
        '" data-campaign-id="' +
        this.escapeHtml(campaignId) +
        '"';

      var closeBtn =
        '<button type="button" class="' +
        closeClass +
        '" aria-label="Close" data-action="close">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
        '<path d="M18 6L6 18M6 6l12 12"/>' +
        "</svg></button>";

      var contentBlock = this.buildPreviewContentBlock(
        content,
        headlineStyles,
        buttonStyles,
        campaignId,
        template
      );

      // Centered: close, image (if), inner
      if (template === "centered") {
        var out =
          "<div" + popupAttrs + ">\n    <!-- Close Button -->\n    " + closeBtn;
        if (content.image_url) {
          out +=
            '\n    <!-- Image -->\n    <div class="meyvc-popup__image"><img src="' +
            this.escapeHtml(content.image_url) +
            '" alt=""></div>';
        }
        out +=
          '\n    <!-- Content -->\n    <div class="meyvc-popup__inner">\n        ' +
          contentBlock +
          "\n    </div>\n</div>";
        return out;
      }

      // Fullscreen: close, background image (if), inner
      if (template === "fullscreen") {
        var fs =
          "<div" + popupAttrs + ">\n    <!-- Close Button -->\n    " + closeBtn;
        if (content.image_url) {
          fs +=
            '\n    <!-- Background Image -->\n    <div class="meyvc-popup__background" style="background-image: url(\'' +
            this.escapeHtml(content.image_url) +
            "');\"></div>";
        }
        fs +=
          '\n    <!-- Content -->\n    <div class="meyvc-popup__inner">\n        ' +
          contentBlock +
          "\n    </div>\n</div>";
        return fs;
      }

      // Image left: close, image column, inner
      if (template === "centered-image-left") {
        var imgLeft =
          "<div" +
          popupAttrs +
          ">\n    <!-- Close Button -->\n    " +
          closeBtn +
          '\n    <!-- Image Column -->\n    <div class="meyvc-popup__image">';
        if (content.image_url) {
          imgLeft +=
            '<img src="' + this.escapeHtml(content.image_url) + '" alt="">';
        }
        imgLeft +=
          '</div>\n    <!-- Content Column -->\n    <div class="meyvc-popup__inner">\n        ' +
          contentBlock +
          "\n    </div>\n</div>";
        return imgLeft;
      }

      // Image right: close, image column, content column (same DOM order as frontend; CSS row-reverse shows image on right)
      if (template === "centered-image-right") {
        var imgRight =
          "<div" +
          popupAttrs +
          ">\n    <!-- Close Button -->\n    " +
          closeBtn +
          '\n    <!-- Image Column (appears on right due to flex-direction: row-reverse in CSS) -->\n    <div class="meyvc-popup__image">';
        if (content.image_url) {
          imgRight +=
            '\n        <img src="' +
            this.escapeHtml(content.image_url) +
            '" alt="">\n    ';
        }
        imgRight +=
          '</div>\n    <!-- Content Column -->\n    <div class="meyvc-popup__inner">\n        ' +
          contentBlock +
          "\n    </div>\n</div>";
        return imgRight;
      }

      // Minimal, slide-bottom: close, inner (no image)
      return (
        "<div" +
        popupAttrs +
        ">\n    <!-- Close Button -->\n    " +
        closeBtn +
        '\n    <!-- Content -->\n    <div class="meyvc-popup__inner">\n        ' +
        contentBlock +
        "\n    </div>\n</div>"
      );
    },

    buildPreviewContentBlock: function (
      content,
      headlineStyles,
      buttonStyles,
      campaignId,
      template
    ) {
      campaignId = campaignId || "preview";
      template = template || "centered";
      var html = "";
      var couponActs = [
        "apply_coupon",
        "apply_coupon_checkout",
        "copy_coupon",
      ];
      var ctaAct = content.cta_action || "close";
      var showCouponBlock =
        (content.show_coupon || couponActs.indexOf(ctaAct) !== -1) &&
        content.coupon_code;
      var couponLabel =
        content.coupon_label || content.coupon_display_text || "Your code";

      // --- Top Bar / Bottom Bar: span headline, inline countdown, inline coupon, CTA only ---
      if (template === "top-bar" || template === "bottom-bar") {
        if (content.headline) {
          html += '<span class="meyvc-popup__headline"';
          if (headlineStyles) html += ' style="' + headlineStyles + '"';
          html += ">" + this.escapeHtml(content.headline) + "</span>\n        ";
        }
        if (content.show_countdown) {
          var m = content.countdown_minutes || 15;
          var ms = String(m).length < 2 ? "0" + m : String(m);
          html +=
            '<div class="meyvc-popup__countdown meyvc-popup__countdown--inline" data-minutes="' +
            m +
            '">\n    <span class="meyvc-popup__countdown-label">Offer ends in</span>\n    <span class="meyvc-popup__countdown-timer">\n        <span class="meyvc-countdown-minutes">' +
            ms +
            '</span>:<span class="meyvc-countdown-seconds">00</span>\n    </span>\n</div>\n        ';
        }
        if (showCouponBlock) {
          html +=
            '<div class="meyvc-popup__coupon meyvc-popup__coupon--inline">' +
            '<code class="meyvc-popup__coupon-code" data-code="' +
            this.escapeHtml(content.coupon_code) +
            '">' +
            this.escapeHtml(content.coupon_code) +
            "</code></div>\n        ";
        }
        if (content.cta_text) {
          html +=
            '<button type="button" class="meyvc-popup__cta" data-action="cta"';
          if (buttonStyles) html += ' style="' + buttonStyles + '"';
          html += ">" + this.escapeHtml(content.cta_text) + "</button>";
        }
        return html;
      }

      // --- Corner: h3 headline, body, coupon, CTA, dismiss only if show_dismiss_link ---
      if (template === "corner") {
        if (content.headline) {
          html +=
            '<h3 class="meyvc-popup__headline" id="meyvc-headline-' +
            this.escapeHtml(campaignId) +
            '"';
          if (headlineStyles) html += ' style="' + headlineStyles + '"';
          html += ">" + this.escapeHtml(content.headline) + "</h3>\n        ";
        }
        if (content.body) {
          html +=
            '<div class="meyvc-popup__body">' +
            this.escapeHtml(content.body) +
            "</div>\n        ";
        }
        if (showCouponBlock) {
          html +=
            '<div class="meyvc-popup__coupon">' +
            '<span class="meyvc-popup__coupon-label">' +
            this.escapeHtml(couponLabel) +
            "</span>" +
            '<code class="meyvc-popup__coupon-code" data-code="' +
            this.escapeHtml(content.coupon_code) +
            '">' +
            this.escapeHtml(content.coupon_code) +
            "</code></div>\n        ";
        }
        if (content.cta_text) {
          html +=
            '<button type="button" class="meyvc-popup__cta" data-action="cta"';
          if (buttonStyles) html += ' style="' + buttonStyles + '"';
          html +=
            ">" + this.escapeHtml(content.cta_text) + "</button>\n        ";
        }
        if (content.show_dismiss_link) {
          html +=
            '<a href="#" class="meyvc-popup__dismiss" data-action="dismiss">' +
            this.escapeHtml(content.dismiss_text || "No thanks") +
            "</a>";
        }
        return html;
      }

      // --- Minimal: h2, subheadline, body, coupon, CTA, dismiss (no countdown) ---
      if (template === "minimal") {
        if (content.headline) {
          html +=
            '<h2 class="meyvc-popup__headline" id="meyvc-headline-' +
            this.escapeHtml(campaignId) +
            '"';
          if (headlineStyles) html += ' style="' + headlineStyles + '"';
          html += ">" + this.escapeHtml(content.headline) + "</h2>\n        ";
        }
        if (content.subheadline) {
          html +=
            '<p class="meyvc-popup__subheadline">' +
            this.escapeHtml(content.subheadline) +
            "</p>\n        ";
        }
        if (content.body) {
          html +=
            '<div class="meyvc-popup__body">' +
            this.escapeHtml(content.body) +
            "</div>\n        ";
        }
        if (showCouponBlock) {
          html +=
            '<div class="meyvc-popup__coupon">' +
            '<span class="meyvc-popup__coupon-label">' +
            this.escapeHtml(couponLabel) +
            "</span>" +
            '<code class="meyvc-popup__coupon-code" data-code="' +
            this.escapeHtml(content.coupon_code) +
            '">' +
            this.escapeHtml(content.coupon_code) +
            "</code></div>\n        ";
        }
        if (content.cta_text) {
          html +=
            '<button type="button" class="meyvc-popup__cta" data-action="cta"';
          if (buttonStyles) html += ' style="' + buttonStyles + '"';
          html +=
            ">" + this.escapeHtml(content.cta_text) + "</button>\n        ";
        }
        if (content.show_dismiss_link !== false) {
          html +=
            '<a href="#" class="meyvc-popup__dismiss" data-action="dismiss">' +
            this.escapeHtml(content.dismiss_text || "No thanks") +
            "</a>";
        }
        return html;
      }

      // --- Slide-bottom: h2, subheadline, body, countdown, coupon, email/cta, dismiss only if set ---
      if (template === "slide-bottom") {
        if (content.headline) {
          html +=
            '<h2 class="meyvc-popup__headline" id="meyvc-headline-' +
            this.escapeHtml(campaignId) +
            '"';
          if (headlineStyles) html += ' style="' + headlineStyles + '"';
          html += ">" + this.escapeHtml(content.headline) + "</h2>\n        ";
        }
        if (content.subheadline) {
          html +=
            '<p class="meyvc-popup__subheadline">' +
            this.escapeHtml(content.subheadline) +
            "</p>\n        ";
        }
        if (content.body) {
          html +=
            '<div class="meyvc-popup__body">' +
            this.escapeHtml(content.body) +
            "</div>\n        ";
        }
        if (content.show_countdown) {
          var min = content.countdown_minutes || 15;
          var mStr = String(min).length < 2 ? "0" + min : String(min);
          html +=
            '<div class="meyvc-popup__countdown" data-minutes="' +
            min +
            '">' +
            '<span class="meyvc-popup__countdown-label">Offer ends in</span>' +
            '<span class="meyvc-popup__countdown-timer">' +
            '<span class="meyvc-countdown-minutes">' +
            mStr +
            '</span>:<span class="meyvc-countdown-seconds">00</span>' +
            "</span></div>\n        ";
        }
        if (showCouponBlock) {
          html +=
            '<div class="meyvc-popup__coupon">' +
            '<span class="meyvc-popup__coupon-label">' +
            this.escapeHtml(couponLabel) +
            "</span>" +
            '<code class="meyvc-popup__coupon-code" data-code="' +
            this.escapeHtml(content.coupon_code) +
            '">' +
            this.escapeHtml(content.coupon_code) +
            "</code></div>\n        ";
        }
        if (content.show_email_field) {
          var ph = content.email_placeholder || "Enter your email";
          var btn = content.email_button_text || "Subscribe";
          html +=
            '<form class="meyvc-popup__email-form" data-campaign-id="' +
            this.escapeHtml(campaignId) +
            '">' +
            '<div class="meyvc-popup__email-row">' +
            '<input type="email" class="meyvc-popup__email-input" name="email" placeholder="' +
            this.escapeHtml(ph) +
            '" required autocomplete="email">' +
            '<button type="submit" class="meyvc-popup__email-submit">' +
            this.escapeHtml(btn) +
            "</button>" +
            "</div>" +
            '<div class="meyvc-popup__email-error" style="display: none;"></div>' +
            '<div class="meyvc-popup__email-success" style="display: none;">Thank you for subscribing!</div>' +
            "</form>\n        ";
        } else if (content.cta_text) {
          html +=
            '<button type="button" class="meyvc-popup__cta" data-action="cta"';
          if (buttonStyles) html += ' style="' + buttonStyles + '"';
          html +=
            ">" + this.escapeHtml(content.cta_text) + "</button>\n        ";
        }
        if (content.show_dismiss_link) {
          html +=
            '<a href="#" class="meyvc-popup__dismiss" data-action="dismiss">' +
            this.escapeHtml(content.dismiss_text || "No thanks") +
            "</a>";
        }
        return html;
      }

      // --- Centered, fullscreen, image-left, image-right: full content ---
      var headlineTag = template === "fullscreen" ? "h1" : "h2";
      if (content.headline) {
        html +=
          "<" +
          headlineTag +
          ' class="meyvc-popup__headline" id="meyvc-headline-' +
          this.escapeHtml(campaignId) +
          '"';
        if (headlineStyles) html += ' style="' + headlineStyles + '"';
        html +=
          ">" +
          this.escapeHtml(content.headline) +
          "</" +
          headlineTag +
          ">\n        ";
      }
      if (content.subheadline) {
        html +=
          '<p class="meyvc-popup__subheadline">' +
          this.escapeHtml(content.subheadline) +
          "</p>\n        ";
      }
      if (content.body) {
        html +=
          '<div class="meyvc-popup__body">' +
          this.escapeHtml(content.body) +
          "</div>\n        ";
      }
      if (content.show_countdown) {
        var mins = content.countdown_minutes || 15;
        var mPad = String(mins).length < 2 ? "0" + mins : String(mins);
        html +=
          '<div class="meyvc-popup__countdown" data-minutes="' +
          mins +
          '">' +
          '<span class="meyvc-popup__countdown-label">Offer ends in</span>' +
          '<span class="meyvc-popup__countdown-timer">' +
          '<span class="meyvc-countdown-minutes">' +
          mPad +
          '</span>:<span class="meyvc-countdown-seconds">00</span>' +
          "</span></div>\n        ";
      }
      if (showCouponBlock) {
        html +=
          '<div class="meyvc-popup__coupon">' +
          '<span class="meyvc-popup__coupon-label">' +
          this.escapeHtml(couponLabel) +
          "</span>" +
          '<code class="meyvc-popup__coupon-code" data-code="' +
          this.escapeHtml(content.coupon_code) +
          '">' +
          this.escapeHtml(content.coupon_code) +
          "</code></div>\n        ";
      }
      if (content.show_email_field) {
        var emailPh = content.email_placeholder || "Enter your email";
        var emailBtn = content.email_button_text || "Subscribe";
        html +=
          '<form class="meyvc-popup__email-form" data-campaign-id="' +
          this.escapeHtml(campaignId) +
          '">' +
          '<div class="meyvc-popup__email-row">' +
          '<input type="email" class="meyvc-popup__email-input" name="email" placeholder="' +
          this.escapeHtml(emailPh) +
          '" required autocomplete="email">' +
          '<button type="submit" class="meyvc-popup__email-submit">' +
          this.escapeHtml(emailBtn) +
          "</button>" +
          "</div>" +
          '<div class="meyvc-popup__email-error" style="display: none;"></div>' +
          '<div class="meyvc-popup__email-success" style="display: none;">Thank you for subscribing!</div>' +
          "</form>\n        ";
      } else if (content.cta_text) {
        html +=
          '<button type="button" class="meyvc-popup__cta" data-action="cta"';
        if (buttonStyles) html += ' style="' + buttonStyles + '"';
        html += ">" + this.escapeHtml(content.cta_text) + "</button>\n        ";
      }
      if (content.show_dismiss_link !== false) {
        html +=
          '<a href="#" class="meyvc-popup__dismiss" data-action="dismiss">' +
          this.escapeHtml(content.dismiss_text || "No thanks") +
          "</a>";
      }
      return html;
    },

    buildPreviewUrl: function (base, previewId, token, expiry) {
      var sep = base.indexOf("?") !== -1 ? "&" : "?";
      return (
        base.replace(/\/?$/, "") +
        sep +
        "meyvc_preview=1&preview_id=" +
        encodeURIComponent(previewId) +
        "&meyvc_token=" +
        encodeURIComponent(token) +
        "&meyvc_expiry=" +
        encodeURIComponent(String(expiry))
      );
    },

    showPreviewError: function (message) {
      var $notice = $("#meyvc-preview-error");
      var $msg = $notice.find(".meyvc-preview-error-message");
      if ($msg.length) $msg.text(message || "");
      $notice.show();
      $notice.find(".notice-dismiss").off("click").on("click", function () {
        $notice.hide();
      });
    },

    hidePreviewError: function () {
      $("#meyvc-preview-error").hide();
    },

    openLivePreview: function () {
      var self = this;
      this.hidePreviewError();
      var previewErrorDefault =
        typeof meyvcAdmin !== "undefined" && meyvcAdmin.strings && meyvcAdmin.strings.previewError
          ? meyvcAdmin.strings.previewError
          : "Preview could not be opened. Please try again.";

      if (typeof wp === "undefined" || !wp.apiFetch) {
        self.showPreviewError(previewErrorDefault + " (wp.apiFetch not loaded. Refresh the page.)");
        meyvcLog("preview error", "wp.apiFetch not available");
        return;
      }

      wp.apiFetch({
        path: "meyvora-convert/v1/preview",
        method: "POST",
        data: { campaign_data: this.campaignData },
      })
        .then(function (body) {
          var url = (body && body.preview_url) || null;
          if (!url && body && body.preview_id && body.meyvc_token && body.meyvc_expiry) {
            var base = (typeof meyvcAdmin !== "undefined" && meyvcAdmin.siteUrl) ? meyvcAdmin.siteUrl : (window.location.origin || "");
            url = self.buildPreviewUrl(base, body.preview_id, body.meyvc_token, body.meyvc_expiry);
          }
          if (url) {
            meyvcLog("preview url", url);
            window.open(url, "_blank");
          } else {
            meyvcLog("preview response missing url/fields", body);
            self.showPreviewError(previewErrorDefault);
          }
        })
        .catch(function (err) {
          meyvcLog("preview error", err);
          var msg = previewErrorDefault;
          if (err && typeof err === "object") {
            if (err.message) msg = err.message;
            else if (err.code) msg = err.code + (err.message ? ": " + err.message : "");
            else if (err.data && err.data.message) msg = err.data.message;
          } else if (typeof err === "string") {
            msg = err;
          }
          self.showPreviewError(msg);
        });
    },

    copyPreviewLink: function () {
      var self = this;
      this.hidePreviewError();
      var $btn = $("#copy-preview-link-btn");
      var originalText = $btn.html();
      var copiedStr = typeof meyvcAdmin !== "undefined" && meyvcAdmin.strings && meyvcAdmin.strings.copied ? meyvcAdmin.strings.copied : "Copied!";
      var previewErrorDefault =
        typeof meyvcAdmin !== "undefined" && meyvcAdmin.strings && meyvcAdmin.strings.previewError
          ? meyvcAdmin.strings.previewError
          : "Could not generate preview link.";

      function fallbackCopy(text) {
        var ta = document.createElement("textarea");
        ta.value = text;
        ta.setAttribute("readonly", "");
        ta.style.position = "fixed";
        ta.style.left = "-9999px";
        document.body.appendChild(ta);
        ta.select();
        try {
          document.execCommand("copy");
          $btn.html((window.meyvcBuilderIcons && window.meyvcBuilderIcons.check ? window.meyvcBuilderIcons.check + " " : "") + copiedStr);
          setTimeout(function () {
            $btn.html(originalText).prop("disabled", false);
          }, 2000);
        } catch (e) {
          $btn.prop("disabled", false);
          self.showPreviewError("Copy failed. Preview URL: " + text);
        }
        document.body.removeChild(ta);
      }

      $btn.prop("disabled", true);
      if (typeof wp === "undefined" || !wp.apiFetch) {
        self.showPreviewError(previewErrorDefault + " (wp.apiFetch not loaded. Refresh the page.)");
        $btn.prop("disabled", false);
        return;
      }

      wp.apiFetch({
        path: "meyvora-convert/v1/preview",
        method: "POST",
        data: { campaign_data: this.campaignData },
      })
        .then(function (body) {
          var url = (body && body.preview_url) || null;
          if (!url && body && body.preview_id && body.meyvc_token && body.meyvc_expiry) {
            var base = (typeof meyvcAdmin !== "undefined" && meyvcAdmin.siteUrl) ? meyvcAdmin.siteUrl : (window.location.origin || "");
            url = self.buildPreviewUrl(base, body.preview_id, body.meyvc_token, body.meyvc_expiry);
          }
          if (!url) {
            self.showPreviewError(previewErrorDefault);
            $btn.prop("disabled", false);
            return;
          }
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(
              function () {
                $btn.html((window.meyvcBuilderIcons && window.meyvcBuilderIcons.check ? window.meyvcBuilderIcons.check + " " : "") + copiedStr);
                setTimeout(function () { $btn.html(originalText).prop("disabled", false); }, 2000);
              },
              function () { fallbackCopy(url); }
            );
          } else {
            fallbackCopy(url);
          }
        })
        .catch(function (err) {
          meyvcLog("copy preview error", err);
          var msg = previewErrorDefault;
          if (err && typeof err === "object") {
            if (err.message) msg = err.message;
            else if (err.code) msg = err.code + (err.message ? ": " + err.message : "");
            else if (err.data && err.data.message) msg = err.data.message;
          } else if (typeof err === "string") msg = err;
          self.showPreviewError(msg);
          $btn.prop("disabled", false);
        });
    },

    // === SAVE HANDLERS ===

    initSaveHandlers: function () {
      var self = this;

      $("#campaign-name").on("input", function () {
        self.campaignData.name = $(this).val();
        self.markChanged();
      });

      $("#campaign-status").on("change", function () {
        self.campaignData.status = $(this).val();
        self.markChanged();
      });

      $("#save-campaign-btn").on("click", function () {
        self.saveCampaign();
      });

      $(document).on("keydown", function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === "s") {
          e.preventDefault();
          self.saveCampaign();
        }
      });

      $(window).on("beforeunload", function () {
        if (self.hasChanges) {
          return "You have unsaved changes. Are you sure you want to leave?";
        }
      });
    },

    markChanged: function () {
      this.hasChanges = true;
      $("#save-status").text("Unsaved changes").addClass("unsaved");
      clearTimeout(this.autoSaveTimeout);
      var self = this;
      this.autoSaveTimeout = setTimeout(function () {
        if (self.hasChanges && self.campaignId > 0) {
          self.saveCampaign(true);
        }
      }, 5000);
    },

    syncCampaignDataToHiddenInput: function () {
      var $input = $("#campaign-data");
      if ($input.length) {
        try {
          $input.val(JSON.stringify(this.campaignData));
        } catch (err) {}
      }
    },

    saveCampaign: function (silent) {
      var self = this;
      silent = !!silent;

      if (!silent && !this.validateCtaFields()) {
        $("#save-status").text("").removeClass("unsaved");
        return;
      }

      if (!silent) {
        $("#save-status").text("Saving...").removeClass("unsaved saved");
      }

      this.updateContentFromFields();
      this.updateStylingFromFields();
      this.updateTriggerFromFields();
      this.updateTargetingFromFields();
      this.updateDisplayFromFields();
      this.campaignData.name =
        ($("#campaign-name").val() || "").trim() || "Untitled Campaign";
      this.campaignData.status = $("#campaign-status").val() || "draft";
      var selectedTemplate = $(".meyvc-builder-wrap .meyvc-template-card.selected").attr("data-template") ||
        $(".meyvc-builder-wrap .meyvc-template-card.selected").data("template");
      if (selectedTemplate) {
        this.campaignData.template = selectedTemplate;
      }

      var $wheelJson = $("#meyvc-wheel-slices-json");
      if ($wheelJson.length && $("#meyvc-wheel-slices-body .meyvc-wheel-slice-row").length) {
        var wheelSlices = [];
        $("#meyvc-wheel-slices-body .meyvc-wheel-slice-row").each(function () {
          var $row = $(this);
          wheelSlices.push({
            label: $row.find(".meyvc-slice-label").val(),
            type: $row.find(".meyvc-slice-type").val(),
            color: $row.find(".meyvc-slice-color").val(),
          });
        });
        $wheelJson.val(JSON.stringify(wheelSlices));
      }

      var saveData = {
        action: "meyvc_save_campaign",
        nonce:
          typeof meyvcAdmin !== "undefined" && meyvcAdmin.nonce
            ? meyvcAdmin.nonce
            : "",
        campaign_id: this.campaignId,
        data: JSON.stringify(this.campaignData),
        wheel_slices: (function () {
          var $w = $("#meyvc-wheel-slices-json");
          return $w.length ? $w.val() || "" : "";
        })(),
      };

      $.ajax({
        url:
          typeof meyvcAdmin !== "undefined" && meyvcAdmin.ajaxUrl
            ? meyvcAdmin.ajaxUrl
            : "",
        type: "POST",
        data: saveData,
        success: function (response) {
          self.clearFieldErrors();
          if (response && response.success) {
            self.hasChanges = false;
            var rd = response.data || {};
            if (rd.trigger_rules && typeof rd.trigger_rules === "object") {
              self.campaignData.trigger_rules = rd.trigger_rules;
            }
            if (rd.campaign_type) {
              self.campaignData.type = rd.campaign_type;
              if (self.campaignData.trigger_rules) {
                self.campaignData.trigger_rules.type = rd.campaign_type;
              }
            } else if (self.campaignData.trigger_rules && self.campaignData.trigger_rules.type) {
              self.campaignData.type = self.campaignData.trigger_rules.type;
            }
            self.syncCampaignDataToHiddenInput();
            self.campaignId =
              rd.id != null && rd.id !== ""
                ? rd.id
                : self.campaignId;
            $("#campaign-id").val(self.campaignId);
            if (window.history.replaceState) {
              var adminUrl =
                typeof meyvcAdmin !== "undefined" && meyvcAdmin.adminUrl
                  ? meyvcAdmin.adminUrl
                  : window.location.href.split("?")[0] || "";
              var newUrl =
                adminUrl +
                (adminUrl.indexOf("?") >= 0 ? "&" : "?") +
                "page=meyvc-campaign-edit&id=" +
                self.campaignId;
              window.history.replaceState({}, "", newUrl);
            }
            if (!silent) {
              $("#save-status")
                .text("Saved!")
                .removeClass("unsaved")
                .addClass("saved");
              setTimeout(function () {
                $("#save-status").text("");
              }, 2000);
              self.showToast(
                typeof meyvcAdmin !== "undefined" && meyvcAdmin.strings && meyvcAdmin.strings.campaignSaved
                  ? meyvcAdmin.strings.campaignSaved
                  : "Campaign saved"
              );
            }
          } else {
            if (!silent) {
              $("#save-status").text("Error saving").addClass("error");
              var errors = response && response.data && response.data.errors;
              if (errors && typeof errors === "object") {
                self.showFieldErrors(errors);
              } else {
                var msg =
                  response && response.data && response.data.message
                    ? response.data.message
                    : "Failed to save campaign";
                self.showToast(msg, "error");
              }
            }
          }
        },
        error: function (xhr, status, err) {
          self.clearFieldErrors();
          if (!silent) {
            $("#save-status").text("Error saving").addClass("error");
            self.showToast("Failed to save campaign. Please try again.", "error");
          }
        },
      });
    },

    // === CONDITIONAL FIELDS ===

    updateCtaActionUi: function () {
      var couponCtaActions = ["apply_coupon", "apply_coupon_checkout"];
      var ctaAction = $("#content-cta-action").val();
      if (couponCtaActions.indexOf(ctaAction) !== -1) {
        $(".meyvc-auto-apply-row").hide();
      } else {
        $(".meyvc-auto-apply-row").show();
      }
    },

    validateCouponAction: function () {
      var action = $("#content-cta-action").val();
      var couponCode = ($("#content-coupon-code").val() || "").trim();
      var couponActions = ["apply_coupon", "apply_coupon_checkout", "copy_coupon"];
      if (couponActions.indexOf(action) !== -1 && !couponCode) {
        this.showToast(
          "A coupon code is required for the selected CTA action.",
          "error"
        );
        return false;
      }
      return true;
    },

    validateCtaFields: function () {
      if (!this.validateCouponAction()) {
        return false;
      }
      var action = $("#content-cta-action").val();
      if (action === "url" && !($("#content-cta-url").val() || "").trim()) {
        this.showToast(
          "Please enter a URL for the 'Go to URL' CTA action.",
          "error"
        );
        return false;
      }
      return true;
    },

    initConditionalFields: function () {
      var self = this;
      $("[data-show-when]").each(function () {
        var $field = $(this);
        var condition = $field.data("show-when");
        if (condition) {
          self.setupConditionalField($field, condition);
        }
      });
    },

    setupConditionalField: function ($field, condition) {
      var eq = (condition || "").indexOf("=");
      if (eq === -1) return;
      var targetId = condition.slice(0, eq);
      var targetValue = condition.slice(eq + 1);
      var $target = $("#" + targetId);
      if (!$target.length) return;

      var allowedValues =
        targetValue.indexOf(",") !== -1
          ? targetValue.split(",").map(function (s) {
              return s.trim();
            })
          : null;

      function checkCondition() {
        var show = false;
        if ($target.is(":checkbox")) {
          show = $target.is(":checked");
        } else if (allowedValues) {
          show = allowedValues.indexOf($target.val()) !== -1;
        } else {
          show = $target.val() === targetValue;
        }
        $field.toggle(show);
      }

      checkCondition();
      $target.on("change", checkCondition);
    },

    // === TOAST & VALIDATION ===

    showToast: function (message, type) {
      type = type || "success";
      var $container = $("#meyvc-builder-toast-container");
      if (!$container.length) {
        $container = $('<div id="meyvc-builder-toast-container" class="meyvc-ui-toast-container" aria-live="polite"></div>').appendTo(".meyvc-builder-wrap");
      }
      var $toast = $('<div class="meyvc-ui-toast" role="status"></div>').text(message);
      if (type === "error") {
        $toast.addClass("meyvc-ui-toast--error");
      }
      $container.append($toast);
      setTimeout(function () {
        $toast.remove();
      }, 4000);
    },

    clearFieldErrors: function () {
      $(".meyvc-builder-wrap .meyvc-field-group").removeClass("meyvc-has-error");
      $(".meyvc-builder-wrap .meyvc-field-error-message").remove();
    },

    showFieldErrors: function (errors) {
      this.clearFieldErrors();
      var self = this;
      $.each(errors, function (fieldId, message) {
        var $field = $("#" + fieldId);
        if (!$field.length) return;
        var $group = $field.closest(".meyvc-field-group");
        if (!$group.length) $group = $field.closest(".meyvc-control-group");
        if (!$group.length) $group = $field.parent();
        $group.addClass("meyvc-has-error");
        var $msg = $('<span class="meyvc-field-error-message"></span>').text(message);
        $group.append($msg);
      });
    },

    // === UTILITIES ===

    escapeHtml: function (text) {
      if (text == null) {
        return "";
      }
      var div = document.createElement("div");
      div.textContent = String(text);
      return div.innerHTML;
    },
  };

  $(document).ready(function () {
    if ($(".meyvc-builder-wrap").length) {
      MEYVCBuilder.init();
    }
    (function meyvcWheelSlicesInit($) {
      var $body = $("#meyvc-wheel-slices-body");
      if (!$body.length) {
        return;
      }
      var WL =
        typeof meyvcBuilderAjax !== "undefined" && meyvcBuilderAjax.wheelWinLabel
          ? meyvcBuilderAjax.wheelWinLabel
          : "Win";
      var LL =
        typeof meyvcBuilderAjax !== "undefined" && meyvcBuilderAjax.wheelLoseLabel
          ? meyvcBuilderAjax.wheelLoseLabel
          : "Lose";
      var DEFAULTS = [
        { label: "10% off", type: "win", color: "#2563eb" },
        { label: "Try again", type: "lose", color: "#e5e7eb" },
        { label: "5% off", type: "win", color: "#7c3aed" },
        { label: "Try again", type: "lose", color: "#e5e7eb" },
        { label: "Free ship", type: "win", color: "#059669" },
        { label: "Try again", type: "lose", color: "#e5e7eb" },
      ];
      function esc(s) {
        return $("<div>").text(s).html();
      }
      function renderSlices(slices) {
        var $b = $("#meyvc-wheel-slices-body").empty();
        $.each(slices, function (i, s) {
          $b.append(
            '<tr class="meyvc-wheel-slice-row">' +
              '<td style="cursor:grab;text-align:center;font-size:16px;color:#888" class="meyvc-slice-handle">&#8597;</td>' +
              '<td><input class="regular-text meyvc-slice-label" value="' +
              esc(s.label) +
              '"/></td>' +
              '<td><select class="meyvc-slice-type"><option value="win"' +
              (s.type === "win" ? " selected" : "") +
              ">" +
              esc(WL) +
              '</option><option value="lose"' +
              (s.type === "lose" ? " selected" : "") +
              ">" +
              esc(LL) +
              "</option></select></td>" +
              '<td><input type="color" class="meyvc-slice-color" value="' +
              esc(s.color || "#cccccc") +
              '"/></td>' +
              '<td><button type="button" class="button button-small meyvc-slice-remove">&#10005;</button></td>' +
              "</tr>"
          );
        });
        syncJson();
      }
      function readSlices() {
        var o = [];
        $("#meyvc-wheel-slices-body .meyvc-wheel-slice-row").each(function () {
          o.push({
            label: $(this).find(".meyvc-slice-label").val(),
            type: $(this).find(".meyvc-slice-type").val(),
            color: $(this).find(".meyvc-slice-color").val(),
          });
        });
        return o;
      }
      function syncJson() {
        $("#meyvc-wheel-slices-json").val(JSON.stringify(readSlices()));
      }
      function getSelectedTemplate() {
        var $sel = $(".meyvc-builder-wrap .meyvc-template-card.selected");
        return ($sel.attr("data-template") || $sel.data("template") || "").toString();
      }
      function checkTpl() {
        var t = getSelectedTemplate();
        $("#meyvc-wheel-config-section").toggle(t === "gamified-wheel");
      }
      $(document).on("click", ".meyvc-builder-wrap .meyvc-template-card", function () {
        setTimeout(checkTpl, 0);
      });
      $("#meyvc-wheel-add-slice").on("click", function () {
        renderSlices(readSlices().concat([{ label: "New", type: "lose", color: "#e5e7eb" }]));
      });
      $(document).on("click", ".meyvc-slice-remove", function () {
        $(this).closest("tr").remove();
        syncJson();
      });
      $(document).on("change input", ".meyvc-slice-label,.meyvc-slice-type,.meyvc-slice-color", syncJson);
      var tbody = document.getElementById("meyvc-wheel-slices-body");
      if (tbody && typeof Sortable !== "undefined") {
        Sortable.create(tbody, { handle: ".meyvc-slice-handle", animation: 120, onEnd: syncJson });
      }
      var saved = $("#meyvc-wheel-slices-json").val();
      var slices = DEFAULTS;
      try {
        var p = JSON.parse(saved);
        if (Array.isArray(p) && p.length) {
          slices = p;
        }
      } catch (e) {}
      renderSlices(slices);
      checkTpl();
    })(jQuery);
    $(document).on("click", "#meyvc-toggle-preview-split", function () {
      var $split = $("#meyvc-builder-split");
      var on = $split.attr("data-mode") !== "split";
      $split.attr("data-mode", on ? "split" : "edit");
      var $iframe = $("#meyvc-builder-live-preview");
      var $frame = $("#preview-frame");
      if (on) {
        $iframe.show();
        $frame.hide();
        if (typeof MEYVCBuilder !== "undefined" && MEYVCBuilder.updatePreview) {
          MEYVCBuilder.updatePreview();
        }
      } else {
        $iframe.hide();
        $frame.show();
      }
      var s =
        typeof meyvcBuilderAjax !== "undefined" && meyvcBuilderAjax.strings
          ? meyvcBuilderAjax.strings
          : {};
      $(this).text(
        on
          ? s.hideIframePreview || "Hide live preview (iframe)"
          : s.showIframePreview || "Show live preview (iframe)"
      );
    });
  });
})(jQuery);
