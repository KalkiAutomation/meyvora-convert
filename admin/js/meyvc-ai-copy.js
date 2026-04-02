(function ($) {
	"use strict";

	function getTemplateType() {
		var $sel = $(".meyvc-builder-wrap .meyvc-template-card.selected");
		if (!$sel.length) {
			$sel = $(".meyvc-template-card.selected");
		}
		if ($sel.length) {
			var t = $sel.data("template") || $sel.attr("data-template");
			if (t) {
				return String(t);
			}
		}
		var $named = $('[name="template_type"]');
		if ($named.length) {
			var nv = $named.val();
			if (nv) {
				return String(nv);
			}
		}
		var $tt = $("#meyvc-template-type");
		if ($tt.length && $tt.val()) {
			return String($tt.val());
		}
		try {
			var raw = $("#campaign-data").val();
			if (raw) {
				var d = JSON.parse(raw);
				if (d && d.template) {
					return String(d.template);
				}
				if (d && d.template_type) {
					return String(d.template_type);
				}
			}
		} catch (e) {}
		return "centered";
	}

	function getPageType() {
		var $m = $("#targeting-page-mode");
		if ($m.length && $m.val()) {
			return String($m.val());
		}
		var $named = $('[name="targeting_rules[page_type]"]');
		if ($named.length && $named.val()) {
			return String($named.val());
		}
		return "all";
	}

	function getOfferType() {
		if (!$("#content-show-coupon").is(":checked")) {
			return "";
		}
		var parts = [];
		var code = ($("#content-coupon-code").val() || "").trim();
		var text = ($("#content-coupon-label").val() || "").trim();
		if (text) {
			parts.push(text);
		}
		if (code) {
			parts.push(code);
		}
		return parts.join(" — ");
	}

	function runGenerate() {
		var $bar = $(".meyvc-ai-copy-bar");
		if (!$bar.length) {
			return;
		}
		var $btn = $("#meyvc-ai-generate-btn");
		var $spin = $bar.find(".meyvc-ai-spinner");
		var $err = $(".meyvc-ai-error");
		var $regen = $(".meyvc-ai-regen");

		if (typeof meyvcAiCopy === "undefined" || !meyvcAiCopy.nonce) {
			return;
		}

		var goal = ($("#meyvc-ai-goal").val() || "").trim();
		if (!goal) {
			$err.text(meyvcAiCopy.strings.goalRequired || "").show();
			return;
		}

		$err.hide().text("");
		var tt = getTemplateType();
		var pt = getPageType();
		if (
			meyvcAiCopy &&
			meyvcAiCopy.debug &&
			(!tt || tt === "centered") &&
			(!pt || pt === "all")
		) {
			console.warn(
				"[CRO AI copy] template_type and page_type look generic; check selectors vs builder.",
				{ template_type: tt, page_type: pt }
			);
		}
		$btn.prop("disabled", true);
		$spin.css("display", "inline-block").addClass("is-active");

		$.ajax({
			url: meyvcAiCopy.ajaxUrl,
			type: "POST",
			dataType: "json",
			data: {
				action: meyvcAiCopy.action,
				_wpnonce: meyvcAiCopy.nonce,
				goal: goal,
				template_type: tt,
				page_type: pt,
				offer_type: getOfferType(),
			},
		})
			.done(function (res) {
				if (res && res.success && res.data) {
					var d = res.data;
					$("#content-headline")
						.val(d.headline || "")
						.trigger("input")
						.trigger("change");
					$("#content-body")
						.val(d.body || "")
						.trigger("input")
						.trigger("change");
					$("#content-cta-text")
						.val(d.cta || "")
						.trigger("input")
						.trigger("change");
					$regen.show();
					$err.hide();
				} else {
					var msg =
						(res && res.data && res.data.message) ||
						(meyvcAiCopy.strings.genericError || "Error");
					$err.text(msg).show();
				}
			})
			.fail(function (xhr) {
				var msg = meyvcAiCopy.strings.genericError || "Error";
				if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					msg = xhr.responseJSON.data.message;
				}
				$err.text(msg).show();
			})
			.always(function () {
				$btn.prop("disabled", false);
				$spin.css("display", "none").removeClass("is-active");
			});
	}

	$(function () {
		if (!$(".meyvc-builder-wrap").length || !$(".meyvc-ai-copy-bar").length) {
			return;
		}

		$(document).on("click", "#meyvc-ai-generate-btn", function (e) {
			e.preventDefault();
			runGenerate();
		});

		$(document).on("click", "#meyvc-ai-regen-link", function (e) {
			e.preventDefault();
			runGenerate();
		});
	});
})(jQuery);
