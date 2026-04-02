(function ($) {
	"use strict";

	var cfg =
		typeof meyvcAiEmailPreview !== "undefined" ? meyvcAiEmailPreview : null;
	var state = { cartId: 0, emailNum: 1 };

	function ensureModal() {
		if ($("#meyvc-ai-email-modal").length) {
			return;
		}
		var s = cfg && cfg.strings ? cfg.strings : {};
		var title = s.modalTitle || "AI abandoned cart email";
		var html =
			'<div id="meyvc-ai-email-modal" class="meyvc-ai-email-modal" role="dialog" aria-modal="true" style="display:none;position:fixed;inset:0;z-index:100099;overflow:auto;">' +
			'<div class="meyvc-ai-email-modal__backdrop" tabindex="-1" style="position:fixed;inset:0;background:rgba(0,0,0,0.45);"></div>' +
			'<div class="meyvc-ai-email-modal__panel" style="position:relative;z-index:100100;max-width:640px;width:92%;margin:32px auto;background:#fff;padding:20px 24px;border-radius:4px;box-shadow:0 4px 24px rgba(0,0,0,0.2);">' +
			'<h2 id="meyvc-ai-email-modal-heading" class="meyvc-ai-email-modal__title"></h2>' +
			'<p class="meyvc-ai-email-modal__row"><strong class="meyvc-ai-email-modal__label meyvc-ai-email-modal__label--subject"></strong> <span id="meyvc-ai-email-modal-subject"></span></p>' +
			'<p class="meyvc-ai-email-modal__row"><strong class="meyvc-ai-email-modal__label meyvc-ai-email-modal__label--preheader"></strong> <span id="meyvc-ai-email-modal-preheader"></span></p>' +
			'<div class="meyvc-ai-email-modal__body-label"></div>' +
			'<iframe id="meyvc-ai-email-modal-frame" class="meyvc-ai-email-modal__frame" title="" style="width:100%;min-height:280px;border:1px solid #c3c4c7;border-radius:2px;"></iframe>' +
			'<p class="meyvc-ai-email-modal__actions">' +
			'<button type="button" id="meyvc-ai-email-modal-regen" class="button"></button> ' +
			'<button type="button" id="meyvc-ai-email-modal-close" class="button"></button>' +
			"</p>" +
			'<p id="meyvc-ai-email-modal-error" class="meyvc-ai-email-modal__error" style="display:none;color:#b32d2e;"></p>' +
			"</div>" +
			"</div>";
		$("body").append(html);
		$("#meyvc-ai-email-modal-heading").text(title);
		$(".meyvc-ai-email-modal__label--subject").text(s.subject || "Subject");
		$(".meyvc-ai-email-modal__label--preheader").text(s.preheader || "Preheader");
		$(".meyvc-ai-email-modal__body-label").text(s.body || "Body");
		$("#meyvc-ai-email-modal-frame").attr("title", s.body || "Body");
		$("#meyvc-ai-email-modal-regen").text(s.regenerate || "Regenerate");
		$("#meyvc-ai-email-modal-close").text(s.close || "Close");

		$(document).on("click", "#meyvc-ai-email-modal-close, .meyvc-ai-email-modal__backdrop", function (e) {
			e.preventDefault();
			$("#meyvc-ai-email-modal").hide();
		});
		$(document).on("click", "#meyvc-ai-email-modal-regen", function (e) {
			e.preventDefault();
			runPreview(state.cartId, state.emailNum, true);
		});
	}

	function showModal(data) {
		ensureModal();
		$("#meyvc-ai-email-modal-error").hide().text("");
		$("#meyvc-ai-email-modal-subject").text(data.subject || "");
		$("#meyvc-ai-email-modal-preheader").text(data.preheader || "");
		var iframe = document.getElementById("meyvc-ai-email-modal-frame");
		if (iframe && iframe.contentDocument) {
			iframe.contentDocument.open();
			iframe.contentDocument.write(data.body_html || "");
			iframe.contentDocument.close();
		}
		$("#meyvc-ai-email-modal").css("display", "block");
	}

	function showError(msg) {
		ensureModal();
		$("#meyvc-ai-email-modal-error").text(msg || "").show();
		$("#meyvc-ai-email-modal").css("display", "block");
	}

	function runPreview(cartId, emailNum, bustFirst) {
		if (!cfg || !cfg.ajaxUrl || !cfg.nonces) {
			return;
		}
		cartId = parseInt(cartId, 10) || 0;
		emailNum = parseInt(emailNum, 10) || 1;
		if (cartId < 1 || emailNum < 1 || emailNum > 3) {
			window.alert(cfg.strings.needCartId || "");
			return;
		}
		state.cartId = cartId;
		state.emailNum = emailNum;

		ensureModal();
		$("#meyvc-ai-email-modal-error").hide().text("");
		$("#meyvc-ai-email-modal-subject").text(cfg.strings.loading || "…");
		$("#meyvc-ai-email-modal-preheader").text("");
		var iframe = document.getElementById("meyvc-ai-email-modal-frame");
		if (iframe && iframe.contentDocument) {
			iframe.contentDocument.open();
			iframe.contentDocument.write("");
			iframe.contentDocument.close();
		}
		$("#meyvc-ai-email-modal").css("display", "block");

		function doPreview() {
			$.ajax({
				url: cfg.ajaxUrl,
				type: "POST",
				dataType: "json",
				data: {
					action: cfg.actions.preview,
					_wpnonce: cfg.nonces.preview,
					cart_id: cartId,
					email_number: emailNum,
				},
			})
				.done(function (res) {
					if (res && res.success && res.data) {
						showModal(res.data);
					} else {
						var m =
							(res && res.data && res.data.message) ||
							(cfg.strings.error || "Error");
						showError(m);
					}
				})
				.fail(function (xhr) {
					var m = cfg.strings.error || "Error";
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						m = xhr.responseJSON.data.message;
					}
					showError(m);
				});
		}

		if (bustFirst) {
			$.ajax({
				url: cfg.ajaxUrl,
				type: "POST",
				dataType: "json",
				data: {
					action: cfg.actions.bust,
					_wpnonce: cfg.nonces.bust,
					cart_id: cartId,
					email_number: emailNum,
				},
			}).always(function () {
				doPreview();
			});
		} else {
			doPreview();
		}
	}

	$(function () {
		if (!cfg) {
			return;
		}
		cfg.strings = cfg.strings || {};
		if (!cfg.strings.needCartId) {
			cfg.strings.needCartId = "Enter a cart ID.";
		}
		if (!cfg.strings.modalTitle) {
			cfg.strings.modalTitle = "AI abandoned cart email";
		}

		$(document).on("click", ".meyvc-ai-preview-email", function (e) {
			e.preventDefault();
			var $b = $(this);
			var cartId = $b.data("cart-id");
			if (!cartId) {
				cartId = $("#meyvc-ai-preview-cart-id").val();
			}
			var emailNum = parseInt($b.data("email"), 10) || 1;
			runPreview(cartId, emailNum, false);
		});
	});
})(jQuery);
