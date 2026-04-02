(function ($) {
	"use strict";

	var cfg =
		typeof meyvcAiAbHypothesis !== "undefined" ? meyvcAiAbHypothesis : null;

	function utf8ToBase64(str) {
		try {
			if (typeof TextEncoder !== "undefined") {
				var bytes = new TextEncoder().encode(str);
				var bin = "";
				for (var i = 0; i < bytes.length; i++) {
					bin += String.fromCharCode(bytes[i]);
				}
				return btoa(bin);
			}
		} catch (e) {
			/* fall through */
		}
		return btoa(unescape(encodeURIComponent(str)));
	}

	function escapeHtml(s) {
		var d = document.createElement("div");
		d.textContent = s == null ? "" : String(s);
		return d.innerHTML;
	}

	function renderDiffRow(label, before, after, s) {
		var b = before == null ? "" : String(before);
		var a = after == null ? "" : String(after);
		var $row = $("<div class='meyvc-ai-ab-diff-row'></div>");
		$row.append(
			$("<div class='meyvc-ai-ab-diff-label'></div>").text(label)
		);
		var $cols = $("<div class='meyvc-ai-ab-diff-cols'></div>");
		$cols.append(
			$("<div class='meyvc-ai-ab-diff-col meyvc-ai-ab-diff-col--before'></div>")
				.append(
					$("<span class='meyvc-ai-ab-diff-tag'></span>").text(
						(s && s.before) || ""
					)
				)
				.append($("<div class='meyvc-ai-ab-diff-text'></div>").text(b))
		);
		$cols.append(
			$("<div class='meyvc-ai-ab-diff-col meyvc-ai-ab-diff-col--after'></div>")
				.append(
					$("<span class='meyvc-ai-ab-diff-tag'></span>").text(
						(s && s.after) || ""
					)
				)
				.append($("<div class='meyvc-ai-ab-diff-text'></div>").text(a))
		);
		$row.append($cols);
		return $row;
	}

	function buildStartUrl(campaignId, variant) {
		var base = (cfg && cfg.newAbBaseUrl) || "";
		var sep = base.indexOf("?") >= 0 ? "&" : "?";
		var payload = {
			name: variant.name || "",
			hypothesis: variant.hypothesis || "",
			change_type: variant.change_type || "",
			new_headline: variant.new_headline || "",
			new_body: variant.new_body || "",
			new_cta: variant.new_cta || "",
			change_summary: variant.change_summary || "",
		};
		var b64 = utf8ToBase64(JSON.stringify(payload));
		return (
			base +
			sep +
			"campaign_id=" +
			encodeURIComponent(String(campaignId)) +
			"&ai_variant=" +
			encodeURIComponent(b64)
		);
	}

	function renderCards($inner, campaignId, variants, baseline) {
		var s = (cfg && cfg.strings) || {};
		var bl = baseline || {};
		$inner.empty();
		var $grid = $("<div class='meyvc-ai-ab-cards'></div>");
		variants.forEach(function (v) {
			var $card = $("<div class='meyvc-ai-ab-card'></div>");
			$card.append($("<h4 class='meyvc-ai-ab-card__title'></h4>").text(v.name || ""));
			$card.append(
				$("<p class='meyvc-ai-ab-card__meta'></p>").html(
					"<strong>" +
						escapeHtml(s.changeType || "") +
						":</strong> " +
						escapeHtml(v.change_type || "")
				)
			);
			$card.append(
				$("<p class='meyvc-ai-ab-card__hypothesis'></p>").html(
					"<strong>" +
						escapeHtml(s.hypothesis || "") +
						":</strong> " +
						escapeHtml(v.hypothesis || "")
				)
			);
			$card.append(
				$("<p class='meyvc-ai-ab-card__summary'></p>").html(
					"<strong>" +
						escapeHtml(s.changeSummary || "") +
						":</strong> " +
						escapeHtml(v.change_summary || "")
				)
			);
			var $diff = $("<div class='meyvc-ai-ab-diff'></div>");
			$diff.append(
				renderDiffRow(s.headline || "Headline", bl.headline, v.new_headline, s)
			);
			$diff.append(renderDiffRow(s.body || "Body", bl.body, v.new_body, s));
			$diff.append(renderDiffRow(s.cta || "CTA", bl.cta, v.new_cta, s));
			$card.append($diff);
			var $btn = $("<a class='button button-primary'></a>")
				.attr("href", buildStartUrl(campaignId, v))
				.text(s.startTest || "");
			$card.append($("<p class='meyvc-ai-ab-card__actions'></p>").append($btn));
			$grid.append($card);
		});
		$inner.append($grid);
	}

	function showConfigureOnly($inner) {
		var s = (cfg && cfg.strings) || {};
		$inner.empty();
		$inner.append(
			$("<p class='meyvc-ai-ab-panel__notice description'></p>").text(
				s.configure || ""
			)
		);
	}

	function loadVariants($tr, campaignId) {
		var $inner = $tr.find(".meyvc-ai-hypothesis-panel__inner");
		if (!cfg || !cfg.aiReady) {
			showConfigureOnly($inner);
			return;
		}
		$inner.html(
			"<p class='meyvc-ai-ab-panel__loading'><span class='spinner is-active' style='float:none;margin:0 8px 0 0;'></span>" +
				escapeHtml((cfg.strings && cfg.strings.loading) || "") +
				"</p>"
		);
		$.post(cfg.ajaxUrl, {
			action: cfg.action,
			_wpnonce: cfg.nonce,
			campaign_id: campaignId,
		})
			.done(function (res) {
				if (!res || !res.success || !res.data) {
					$inner.empty().append(
						$("<p class='notice notice-error'></p>").text(
							(cfg.strings && cfg.strings.error) || ""
						)
					);
					return;
				}
				var d = res.data;
				renderCards($inner, d.campaign_id || campaignId, d.variants || [], d.baseline || {});
				$inner.data("loaded", 1);
			})
			.fail(function () {
				$inner.empty().append(
					$("<p class='notice notice-error'></p>").text(
						(cfg.strings && cfg.strings.error) || ""
					)
				);
			});
	}

	$(function () {
		$(document).on("click", ".js-meyvc-ai-ab-hypothesis-toggle", function (e) {
			e.preventDefault();
			var id = $(this).data("campaign-id");
			var $panel = $("#meyvc-ai-hypothesis-panel-" + id);
			if (!$panel.length) {
				return;
			}
			var $inner = $panel.find(".meyvc-ai-hypothesis-panel__inner");
			var $slide = $panel.find(".meyvc-ai-hypothesis-panel__slide");
			var isOpen = $panel.hasClass("is-open");
			if (isOpen) {
				$slide.slideUp(200, function () {
					$panel.hide().removeClass("is-open");
				});
				return;
			}
			$panel.css("display", "table-row").addClass("is-open");
			$slide.hide().slideDown(200);
			if (!$inner.data("loaded")) {
				loadVariants($panel, id);
			}
		});
	});
})(jQuery);
