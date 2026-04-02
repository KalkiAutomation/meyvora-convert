(function ($) {
	"use strict";

	var cfg = typeof meyvcAiOfferSuggest !== "undefined" ? meyvcAiOfferSuggest : null;
	var lastSuggestion = null;

	function setSpinner(on) {
		var $sp = $("#meyvc-ai-suggest-offer-spinner");
		if (!$sp.length) {
			return;
		}
		if (on) {
			$sp.css("visibility", "visible").addClass("is-active");
		} else {
			$sp.css("visibility", "hidden").removeClass("is-active");
		}
	}

	function formatConditionLine(s) {
		var ct = (s.condition_type || "").toLowerCase();
		var cv = String(s.condition_value || "");
		var map = {
			cart_total: "Cart total ≥ " + cv,
			first_order: "First-time customer",
			returning_customer: "Returning customer (≥ " + (parseInt(cv, 10) || 2) + " orders)",
			lifetime_spend: "Lifetime spend ≥ " + cv,
		};
		var cond = map[ct] || map.cart_total;
		var dt = s.discount_type === "fixed" ? "fixed amount" : "percent";
		var amt = s.discount_value;
		var disc =
			s.discount_type === "fixed"
				? amt + " fixed"
				: amt + "%";
		return cond + " · " + disc;
	}

	function applyMirrorFields(s) {
		$("#meyvc-offer-name").val(s.name || "");
		$("#meyvc-condition-type").val(s.condition_type || "");
		$("#meyvc-condition-value").val(String(s.condition_value != null ? s.condition_value : ""));
		$("#meyvc-discount-type").val(s.discount_type || "");
		$("#meyvc-discount-value").val(
			s.discount_value != null ? String(s.discount_value) : ""
		);
	}

	function applySuggestionToDrawer(s) {
		applyMirrorFields(s);

		$("#meyvc-drawer-headline").val(s.name || "");

		var ct = (s.condition_type || "cart_total").toLowerCase();
		var cv = parseFloat(String(s.condition_value || "0").replace(/[^0-9.]/g, "")) || 0;

		$("#meyvc-drawer-min-cart-total").val(0);
		$("#meyvc-drawer-first-time").prop("checked", false);
		$("#meyvc-drawer-returning-toggle").prop("checked", false);
		$("#meyvc-drawer-returning-min-orders").val(0);
		$("#meyvc-drawer-lifetime-spend").val(0);
		$("#meyvc-drawer-returning-min-wrap").addClass("meyvc-hidden");

		if (ct === "cart_total") {
			$("#meyvc-drawer-min-cart-total").val(cv > 0 ? cv : 0);
		} else if (ct === "first_order") {
			$("#meyvc-drawer-first-time").prop("checked", true);
		} else if (ct === "returning_customer") {
			var mo = parseInt(String(s.condition_value || "2"), 10);
			if (isNaN(mo) || mo < 1) {
				mo = 2;
			}
			$("#meyvc-drawer-returning-toggle").prop("checked", true);
			$("#meyvc-drawer-returning-min-orders").val(mo);
			$("#meyvc-drawer-returning-min-wrap").removeClass("meyvc-hidden");
		} else if (ct === "lifetime_spend") {
			$("#meyvc-drawer-lifetime-spend").val(cv > 0 ? cv : 0);
		}

		var dt = s.discount_type === "fixed" ? "fixed" : "percent";
		$("#meyvc-drawer-reward-type").val(dt).trigger("change");
		var dv =
			s.discount_value != null ? parseFloat(String(s.discount_value), 10) : 10;
		if (isNaN(dv)) {
			dv = 10;
		}
		$("#meyvc-drawer-reward-amount").val(dv);

		$("#meyvc-drawer-returning-toggle").trigger("change");
		$("#meyvc-drawer-first-time").trigger("change");
		$("#meyvc-drawer-min-cart-total").trigger("keyup");
		$("#meyvc-drawer-lifetime-spend").trigger("keyup");
		$("#meyvc-drawer-headline").trigger("input");
		$("#meyvc-drawer-reward-amount").trigger("keyup");

		var $rt = $("#meyvc-drawer-reward-type");
		if ($rt.data("selectWoo")) {
			$rt.trigger("change.select2");
		}
	}

	function showPrefillNotice() {
		var msg =
			(cfg && cfg.strings && cfg.strings.prefillNotice) ||
			"Pre-filled by AI · Review before saving";
		var $n = $("#meyvc-ai-offer-prefill-notice");
		$n.text(msg).show();
	}

	function openAddDrawerThenApply() {
		var $trigger = $('[data-meyvc-drawer="add"]').not(":disabled").first();
		if (!$trigger.length) {
			window.alert(
				(cfg && cfg.strings && cfg.strings.full) ||
					"All offer slots are full."
			);
			return;
		}
		$trigger.trigger("click");
		setTimeout(function () {
			if (lastSuggestion) {
				applySuggestionToDrawer(lastSuggestion);
				showPrefillNotice();
			}
			var panel = document.querySelector(".meyvc-offer-drawer-panel");
			if (panel && typeof panel.scrollIntoView === "function") {
				panel.scrollIntoView({ behavior: "smooth", block: "nearest" });
			}
		}, 150);
	}

	$(function () {
		if (!cfg || !$("#meyvc-ai-suggest-offer-btn").length) {
			return;
		}

		$(document).on("click", "#meyvc-ai-suggest-offer-btn", function (e) {
			e.preventDefault();
			if (!cfg.aiReady) {
				return;
			}
			if (cfg.atCapacity) {
				window.alert(
					(cfg.strings && cfg.strings.full) ||
						"All offer slots are full."
				);
				return;
			}
			$("#meyvc-ai-suggest-error").hide().text("");
			$("#meyvc-ai-suggest-offer-btn").prop("disabled", true);
			setSpinner(true);

			$.ajax({
				url: cfg.ajaxUrl,
				type: "POST",
				dataType: "json",
				data: {
					action: cfg.action,
					_wpnonce: cfg.nonce,
				},
			})
				.done(function (res) {
					if (!res || !res.success || !res.data || !res.data.suggestion) {
						var msg =
							(res && res.data && res.data.message) ||
							(cfg.strings && cfg.strings.error) ||
							"";
						$("#meyvc-ai-suggest-error").text(msg).show();
						return;
					}
					var s = res.data.suggestion;
					lastSuggestion = s;
					$("#meyvc-ai-suggest-name").text(s.name || "");
					$("#meyvc-ai-suggest-rationale").text(s.rationale || "");
					$("#meyvc-ai-suggest-condisc").text(formatConditionLine(s));
					$("#meyvc-ai-suggest-impact").text(
						(cfg.strings && cfg.strings.impactLbl
							? cfg.strings.impactLbl + ": "
							: "") + (s.expected_impact || "")
					);
					$("#meyvc-ai-offer-suggestion-card").show();
				})
				.fail(function (xhr) {
					var msg =
						(xhr.responseJSON &&
							xhr.responseJSON.data &&
							xhr.responseJSON.data.message) ||
						(cfg.strings && cfg.strings.error) ||
						"";
					$("#meyvc-ai-suggest-error").text(msg).show();
				})
				.always(function () {
					setSpinner(false);
					$("#meyvc-ai-suggest-offer-btn").prop("disabled", false);
				});
		});

		$(document).on("click", "#meyvc-ai-suggest-create-btn", function (e) {
			e.preventDefault();
			if (!lastSuggestion) {
				return;
			}
			openAddDrawerThenApply();
		});
	});
})(jQuery);
