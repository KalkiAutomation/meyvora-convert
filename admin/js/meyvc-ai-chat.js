(function ($) {
	"use strict";

	var STORAGE_KEY = "meyvc_ai_chat_history";
	var cfg = typeof meyvcAiChat !== "undefined" ? meyvcAiChat : null;

	function getHistory() {
		try {
			var raw = window.sessionStorage.getItem(STORAGE_KEY);
			if (!raw) {
				return [];
			}
			var parsed = JSON.parse(raw);
			return Array.isArray(parsed) ? parsed : [];
		} catch (e) {
			return [];
		}
	}

	function setHistory(items) {
		try {
			window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(items));
		} catch (e) {
			/* ignore quota */
		}
	}

	function togglePanel(open) {
		var $panel = $("#meyvc-aichat-panel");
		var $launcher = $("#meyvc-aichat-launcher");
		var $navToggle = $("#meyvc-aichat-nav-toggle");
		if (!$panel.length) {
			return;
		}
		var show = typeof open === "boolean" ? open : !$panel.is(":visible");
		if (show) {
			$panel.css("display", "flex").attr("aria-hidden", "false");
			$launcher.attr("aria-expanded", "true");
			$navToggle.attr("aria-expanded", "true");
			setTimeout(function () {
				$("#meyvc-aichat-input").trigger("focus");
			}, 100);
		} else {
			$panel.hide().attr("aria-hidden", "true");
			$launcher.attr("aria-expanded", "false");
			$navToggle.attr("aria-expanded", "false");
		}
	}

	function appendBubble(role, htmlOrText, isHtml) {
		var $wrap = $('<div class="meyvc-aichat-msg-wrap meyvc-aichat-msg-wrap--' + role + '"></div>');
		var $b = $('<div class="meyvc-aichat-bubble meyvc-aichat-bubble--' + role + '"></div>');
		if (isHtml) {
			$b.html(htmlOrText);
		} else {
			$b.text(htmlOrText);
		}
		$wrap.append($b);
		$("#meyvc-aichat-messages").append($wrap);
		scrollToBottom();
	}

	function scrollToBottom() {
		var $el = $("#meyvc-aichat-messages");
		if ($el.length) {
			$el.scrollTop($el[0].scrollHeight);
		}
	}

	function hideStarters() {
		$("#meyvc-aichat-starters").addClass("meyvc-aichat-starters--hidden");
	}

	function sendMessage(text) {
		if (!cfg || !cfg.aiReady) {
			var msg =
				(cfg && cfg.strings && cfg.strings.configure) ||
				"Configure AI in settings.";
			appendBubble("assistant", msg, false);
			hideStarters();
			return;
		}
		text = $.trim(String(text || ""));
		if (!text) {
			return;
		}

		hideStarters();
		appendBubble("user", text, false);

		var history = getHistory();
		var $send = $("#meyvc-aichat-send");
		var $input = $("#meyvc-aichat-input");
		$send.prop("disabled", true);
		$input.prop("disabled", true);

		$.ajax({
			url: cfg.ajaxUrl,
			type: "POST",
			dataType: "json",
			data: {
				action: cfg.action,
				_wpnonce: cfg.nonce,
				message: text,
				history: JSON.stringify(history),
			},
		})
			.done(function (res) {
				if (!res || !res.success || !res.data) {
					var err =
						(res && res.data && res.data.message) ||
						(cfg.strings && cfg.strings.error) ||
						"Error.";
					appendBubble("assistant", err, false);
					return;
				}
				var reply = res.data.reply || "";
				appendBubble("assistant", reply, true);
				if (Array.isArray(res.data.history)) {
					setHistory(res.data.history);
				}
			})
			.fail(function () {
				appendBubble(
					"assistant",
					(cfg.strings && cfg.strings.error) || "Error.",
					false
				);
			})
			.always(function () {
				$send.prop("disabled", false);
				$input.prop("disabled", false).trigger("focus");
			});
	}

	function restoreFromStorage() {
		var history = getHistory();
		if (!history.length) {
			return;
		}
		hideStarters();
		history.forEach(function (row) {
			if (!row || !row.role || !row.content) {
				return;
			}
			if (row.role === "assistant" || row.role === "user") {
				appendBubble(row.role, row.content, false);
			}
		});
	}

	$(function () {
		if (!$("#meyvc-aichat-panel").length) {
			return;
		}

		restoreFromStorage();

		$(document).on("click", "#meyvc-aichat-launcher", function () {
			togglePanel();
		});
		$(document).on("keydown", "#meyvc-aichat-launcher", function (e) {
			if (e.key === "Enter" || e.key === " ") {
				e.preventDefault();
				togglePanel();
			}
		});

		$(document).on("click", "#meyvc-aichat-nav-toggle", function () {
			togglePanel(true);
		});

		$(document).on("click", "#meyvc-aichat-close", function () {
			togglePanel(false);
		});

		$(document).on("click", "#meyvc-aichat-send", function () {
			var v = $("#meyvc-aichat-input").val();
			$("#meyvc-aichat-input").val("");
			sendMessage(v);
		});

		$("#meyvc-aichat-input").on("keydown", function (e) {
			if (e.key === "Enter") {
				e.preventDefault();
				var v = $(this).val();
				$(this).val("");
				sendMessage(v);
			}
		});

		$(document).on("click", ".meyvc-aichat-pill", function () {
			sendMessage($(this).text());
		});
	});
})(jQuery);
