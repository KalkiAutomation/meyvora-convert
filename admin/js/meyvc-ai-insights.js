(function ($) {
	"use strict";

	var cfg = typeof meyvcAiInsights !== "undefined" ? meyvcAiInsights : null;

	function getDays() {
		var $el = $("#meyvc-ai-insights-section");
		var d = parseInt($el.attr("data-days"), 10);
		return d >= 7 && d <= 90 ? d : 30;
	}

	function setLoading(on) {
		var $btn = $("#meyvc-ai-analyse-btn");
		var $ref = $("#meyvc-ai-refresh-btn");
		$btn.prop("disabled", on || !(cfg && cfg.aiReady));
		$ref.prop("disabled", on);
	}

	function renderInsights(insights) {
		var $out = $("#meyvc-ai-insights-output");
		$out.empty();
		if (!insights || !insights.length) {
			$out.append(
				$("<p class='description'></p>").text(
					(cfg.strings && cfg.strings.emptyHint) || ""
				)
			);
			return;
		}
		insights.forEach(function (row) {
			var p = (row.priority || "medium").toLowerCase();
			if (p !== "high" && p !== "medium" && p !== "low") {
				p = "medium";
			}
			var $card = $("<div class='meyvc-ai-insight-card'></div>");
			var $badges = $("<div class='meyvc-ai-insight-card__badges'></div>");
			$badges.append(
				$("<span class='meyvc-ai-insight-priority'></span>")
					.addClass("meyvc-ai-insight-priority--" + p)
					.text(p)
			);
			$badges.append(
				$("<span class='meyvc-ai-insight-category'></span>").text(
					row.category || "general"
				)
			);
			$card.append($badges);
			$card.append($("<h4></h4>").text(row.title || ""));
			$card.append(
				$("<p class='meyvc-ai-insight-finding'></p>").text(row.finding || "")
			);
			$card.append(
				$("<p class='meyvc-ai-insight-action'></p>").text(row.action || "")
			);
			if (row.metric) {
				$card.append(
					$("<p class='meyvc-ai-insight-metric'></p>").text(row.metric)
				);
			}
			$out.append($card);
		});
	}

	function setMeta(cached, elapsedSeconds) {
		var $m = $("#meyvc-ai-insights-meta");
		var s = (cfg && cfg.strings) || {};
		$m.empty();
		if (cached) {
			$m.hide();
			return;
		}
		if (typeof elapsedSeconds === "number" && elapsedSeconds >= 0) {
			var tpl = s.generatedIn || "";
			$m.text(tpl.replace("%s", String(elapsedSeconds))).show();
		} else {
			$m.hide();
		}
	}

	function setCacheUi(showCached) {
		if (showCached) {
			$("#meyvc-ai-cache-note").show();
			$("#meyvc-ai-refresh-btn").show();
		} else {
			$("#meyvc-ai-cache-note").hide();
		}
	}

	function postAjax(payload, done, fail) {
		if (!cfg || !cfg.ajaxUrl) {
			return;
		}
		$.ajax({
			url: cfg.ajaxUrl,
			type: "POST",
			dataType: "json",
			data: payload,
		})
			.done(done)
			.fail(fail);
	}

	function handleSuccess(res) {
		if (!res || !res.success || !res.data) {
			return false;
		}
		var d = res.data;
		if (d.insights) {
			renderInsights(d.insights);
		}
		setMeta(!!d.cached, d.elapsed_seconds);
		setCacheUi(!!d.cached);
		if (d.insights && d.insights.length) {
			$("#meyvc-ai-refresh-btn").show();
		}
		return true;
	}

	function peek() {
		if (!cfg || !cfg.aiReady) {
			return;
		}
		postAjax(
			{
				action: cfg.actions.analyse,
				_wpnonce: cfg.nonces.analyse,
				days: getDays(),
				peek: 1,
			},
			function (res) {
				handleSuccess(res);
			},
			function () {}
		);
	}

	function runAnalyse(refresh) {
		if (!cfg || !cfg.aiReady) {
			return;
		}
		setLoading(true);
		postAjax(
			{
				action: cfg.actions.analyse,
				_wpnonce: cfg.nonces.analyse,
				days: getDays(),
				refresh: refresh ? 1 : 0,
			},
			function (res) {
				setLoading(false);
				if (handleSuccess(res)) {
					return;
				}
				var msg =
					(res && res.data && res.data.message) ||
					(cfg.strings && cfg.strings.error) ||
					"";
				if (
					res &&
					res.data &&
					typeof res.data.retry_after === "number" &&
					cfg.strings &&
					cfg.strings.waitSeconds
				) {
					msg = cfg.strings.waitSeconds.replace(
						"%d",
						String(res.data.retry_after)
					);
				}
				$("#meyvc-ai-insights-output").html(
					$("<p class='description' style='color:#b32d2e'></p>").text(msg)
				);
			},
			function () {
				setLoading(false);
				$("#meyvc-ai-insights-output").html(
					$("<p class='description' style='color:#b32d2e'></p>").text(
						(cfg.strings && cfg.strings.error) || ""
					)
				);
			}
		);
	}

	function bust() {
		if (!cfg) {
			return;
		}
		postAjax(
			{
				action: cfg.actions.bust,
				_wpnonce: cfg.nonces.bust,
				days: getDays(),
			},
			function (res) {
				if (res && res.success) {
					$("#meyvc-ai-cache-note").hide();
					$("#meyvc-ai-refresh-btn").hide();
					$("#meyvc-ai-insights-meta").hide().empty();
					$("#meyvc-ai-insights-output").html(
						$("<p class='description'></p>").text(
							(cfg.strings && cfg.strings.cacheCleared) || ""
						)
					);
				}
			},
			function () {}
		);
	}

	$(function () {
		if (!$("#meyvc-ai-insights-section").length) {
			return;
		}
		if (!cfg || !cfg.aiReady) {
			return;
		}
		peek();
		$(document).on("click", "#meyvc-ai-analyse-btn", function (e) {
			e.preventDefault();
			runAnalyse(false);
		});
		$(document).on("click", "#meyvc-ai-refresh-btn", function (e) {
			e.preventDefault();
			runAnalyse(true);
		});
		$(document).on("click", "#meyvc-ai-bust", function (e) {
			e.preventDefault();
			bust();
		});
	});
})(jQuery);
