(function ($) {
	"use strict";

	var cfg = typeof croAiOfferSuggest !== "undefined" ? croAiOfferSuggest : null;
	var lastSuggestion = null;

	function setSpinner(on) {
		var $sp = $("#cro-ai-suggest-offer-spinner");
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
		$("#cro-offer-name").val(s.name || "");
		$("#cro-condition-type").val(s.condition_type || "");
		$("#cro-condition-value").val(String(s.condition_value != null ? s.condition_value : ""));
		$("#cro-discount-type").val(s.discount_type || "");
		$("#cro-discount-value").val(
			s.discount_value != null ? String(s.discount_value) : ""
		);
	}

	function applySuggestionToDrawer(s) {
		applyMirrorFields(s);

		$("#cro-drawer-headline").val(s.name || "");

		var ct = (s.condition_type || "cart_total").toLowerCase();
		var cv = parseFloat(String(s.condition_value || "0").replace(/[^0-9.]/g, "")) || 0;

		$("#cro-drawer-min-cart-total").val(0);
		$("#cro-drawer-first-time").prop("checked", false);
		$("#cro-drawer-returning-toggle").prop("checked", false);
		$("#cro-drawer-returning-min-orders").val(0);
		$("#cro-drawer-lifetime-spend").val(0);
		$("#cro-drawer-returning-min-wrap").addClass("cro-hidden");

		if (ct === "cart_total") {
			$("#cro-drawer-min-cart-total").val(cv > 0 ? cv : 0);
		} else if (ct === "first_order") {
			$("#cro-drawer-first-time").prop("checked", true);
		} else if (ct === "returning_customer") {
			var mo = parseInt(String(s.condition_value || "2"), 10);
			if (isNaN(mo) || mo < 1) {
				mo = 2;
			}
			$("#cro-drawer-returning-toggle").prop("checked", true);
			$("#cro-drawer-returning-min-orders").val(mo);
			$("#cro-drawer-returning-min-wrap").removeClass("cro-hidden");
		} else if (ct === "lifetime_spend") {
			$("#cro-drawer-lifetime-spend").val(cv > 0 ? cv : 0);
		}

		var dt = s.discount_type === "fixed" ? "fixed" : "percent";
		$("#cro-drawer-reward-type").val(dt).trigger("change");
		var dv =
			s.discount_value != null ? parseFloat(String(s.discount_value), 10) : 10;
		if (isNaN(dv)) {
			dv = 10;
		}
		$("#cro-drawer-reward-amount").val(dv);

		$("#cro-drawer-returning-toggle").trigger("change");
		$("#cro-drawer-first-time").trigger("change");
		$("#cro-drawer-min-cart-total").trigger("keyup");
		$("#cro-drawer-lifetime-spend").trigger("keyup");
		$("#cro-drawer-headline").trigger("input");
		$("#cro-drawer-reward-amount").trigger("keyup");

		var $rt = $("#cro-drawer-reward-type");
		if ($rt.data("selectWoo")) {
			$rt.trigger("change.select2");
		}
	}

	function showPrefillNotice() {
		var msg =
			(cfg && cfg.strings && cfg.strings.prefillNotice) ||
			"Pre-filled by AI · Review before saving";
		var $n = $("#cro-ai-offer-prefill-notice");
		$n.text(msg).show();
	}

	function openAddDrawerThenApply() {
		var $trigger = $('[data-cro-drawer="add"]').not(":disabled").first();
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
			var panel = document.querySelector(".cro-offer-drawer-panel");
			if (panel && typeof panel.scrollIntoView === "function") {
				panel.scrollIntoView({ behavior: "smooth", block: "nearest" });
			}
		}, 150);
	}

	$(function () {
		if (!cfg || !$("#cro-ai-suggest-offer-btn").length) {
			return;
		}

		$(document).on("click", "#cro-ai-suggest-offer-btn", function (e) {
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
			$("#cro-ai-suggest-error").hide().text("");
			$("#cro-ai-suggest-offer-btn").prop("disabled", true);
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
						$("#cro-ai-suggest-error").text(msg).show();
						return;
					}
					var s = res.data.suggestion;
					lastSuggestion = s;
					$("#cro-ai-suggest-name").text(s.name || "");
					$("#cro-ai-suggest-rationale").text(s.rationale || "");
					$("#cro-ai-suggest-condisc").text(formatConditionLine(s));
					$("#cro-ai-suggest-impact").text(
						(cfg.strings && cfg.strings.impactLbl
							? cfg.strings.impactLbl + ": "
							: "") + (s.expected_impact || "")
					);
					$("#cro-ai-offer-suggestion-card").show();
				})
				.fail(function (xhr) {
					var msg =
						(xhr.responseJSON &&
							xhr.responseJSON.data &&
							xhr.responseJSON.data.message) ||
						(cfg.strings && cfg.strings.error) ||
						"";
					$("#cro-ai-suggest-error").text(msg).show();
				})
				.always(function () {
					setSpinner(false);
					$("#cro-ai-suggest-offer-btn").prop("disabled", false);
				});
		});

		$(document).on("click", "#cro-ai-suggest-create-btn", function (e) {
			e.preventDefault();
			if (!lastSuggestion) {
				return;
			}
			openAddDrawerThenApply();
		});
	});
})(jQuery);
