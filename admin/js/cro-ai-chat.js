(function ($) {
	"use strict";

	var STORAGE_KEY = "cro_ai_chat_history";
	var cfg = typeof croAiChat !== "undefined" ? croAiChat : null;

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
		var $panel = $("#cro-aichat-panel");
		var $launcher = $("#cro-aichat-launcher");
		var $navToggle = $("#cro-aichat-nav-toggle");
		if (!$panel.length) {
			return;
		}
		var show = typeof open === "boolean" ? open : !$panel.is(":visible");
		if (show) {
			$panel.css("display", "flex").attr("aria-hidden", "false");
			$launcher.attr("aria-expanded", "true");
			$navToggle.attr("aria-expanded", "true");
			setTimeout(function () {
				$("#cro-aichat-input").trigger("focus");
			}, 100);
		} else {
			$panel.hide().attr("aria-hidden", "true");
			$launcher.attr("aria-expanded", "false");
			$navToggle.attr("aria-expanded", "false");
		}
	}

	function appendBubble(role, htmlOrText, isHtml) {
		var $wrap = $('<div class="cro-aichat-msg-wrap cro-aichat-msg-wrap--' + role + '"></div>');
		var $b = $('<div class="cro-aichat-bubble cro-aichat-bubble--' + role + '"></div>');
		if (isHtml) {
			$b.html(htmlOrText);
		} else {
			$b.text(htmlOrText);
		}
		$wrap.append($b);
		$("#cro-aichat-messages").append($wrap);
		scrollToBottom();
	}

	function scrollToBottom() {
		var $el = $("#cro-aichat-messages");
		if ($el.length) {
			$el.scrollTop($el[0].scrollHeight);
		}
	}

	function hideStarters() {
		$("#cro-aichat-starters").addClass("cro-aichat-starters--hidden");
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
		var $send = $("#cro-aichat-send");
		var $input = $("#cro-aichat-input");
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
		if (!$("#cro-aichat-panel").length) {
			return;
		}

		restoreFromStorage();

		$(document).on("click", "#cro-aichat-launcher", function () {
			togglePanel();
		});
		$(document).on("keydown", "#cro-aichat-launcher", function (e) {
			if (e.key === "Enter" || e.key === " ") {
				e.preventDefault();
				togglePanel();
			}
		});

		$(document).on("click", "#cro-aichat-nav-toggle", function () {
			togglePanel(true);
		});

		$(document).on("click", "#cro-aichat-close", function () {
			togglePanel(false);
		});

		$(document).on("click", "#cro-aichat-send", function () {
			var v = $("#cro-aichat-input").val();
			$("#cro-aichat-input").val("");
			sendMessage(v);
		});

		$("#cro-aichat-input").on("keydown", function (e) {
			if (e.key === "Enter") {
				e.preventDefault();
				var v = $(this).val();
				$(this).val("");
				sendMessage(v);
			}
		});

		$(document).on("click", ".cro-aichat-pill", function () {
			sendMessage($(this).text());
		});
	});
})(jQuery);
