/**
 * Admin JavaScript
 */
(function($) {
	'use strict';

	/**
	 * Boosters: live preview for Free shipping bar (50% cart demo).
	 */
	function croFormatPreviewPrice(amount) {
		var sym = typeof croAdmin !== 'undefined' && croAdmin.currency ? croAdmin.currency : '$';
		var dec = 2;
		if (typeof croAdmin !== 'undefined' && croAdmin.priceDecimals != null) {
			dec = parseInt(croAdmin.priceDecimals, 10);
			if (isNaN(dec)) {
				dec = 2;
			}
		}
		var n = parseFloat(amount, 10);
		if (isNaN(n)) {
			n = 0;
		}
		return sym + n.toFixed(dec);
	}

	function updateCroShippingBarPreview() {
		var $wrap = $('#cro-shipping-bar-preview-wrap');
		var $bar = $('#cro-shipping-bar-preview');
		if (!$wrap.length || !$bar.length) {
			return;
		}
		var useWoo = $('#shipping_bar_use_woo').is(':checked');
		var wooTh = parseFloat($wrap.data('woo-threshold'), 10);
		if (isNaN(wooTh)) {
			wooTh = 0;
		}
		var customTh = parseFloat($('#shipping_bar_threshold').val(), 10);
		if (isNaN(customTh)) {
			customTh = 0;
		}
		var th = useWoo && wooTh > 0 ? wooTh : customTh;
		if (th <= 0) {
			th = 50;
		}
		var cart = th * 0.5;
		var remaining = Math.max(0, th - cart);
		var pct = Math.min(100, (cart / th) * 100);

		var msgRaw = $('#shipping_bar_message_progress').val();
		if (!msgRaw) {
			msgRaw = $('#shipping_bar_message_progress').attr('placeholder') || '';
		}
		var formatted = croFormatPreviewPrice(remaining);
		var line = (msgRaw || '').split('{amount}').join(formatted);
		$('#cro-shipping-bar-preview-message').text(line);

		var bg = $('input[name="shipping_bar_bg_color"]').val() || '#f7f7f7';
		var barColor = $('input[name="shipping_bar_bar_color"]').val() || '#333333';
		$bar.css('background-color', bg);
		$('#cro-shipping-bar-preview-fill').css({
			width: pct + '%',
			'background-color': barColor
		});
		$('#cro-shipping-bar-preview-progress-wrap').show();
	}

	/**
	 * Boosters: live preview for Sticky add-to-cart bar (matches storefront markup).
	 */
	function updateCroStickyCartPreview() {
		var $wrap = $('#cro-sticky-cart-preview-wrap');
		if (!$wrap.length) {
			return;
		}
		var defaults = {};
		try {
			defaults = JSON.parse($wrap.attr('data-default-buttons') || '{}');
		} catch (e) {
			defaults = {};
		}
		var tone = $('#sticky_cart_tone').val() || 'neutral';
		var btnRaw = ($('#sticky_cart_button_text').val() || '').trim();
		var btnText = btnRaw;
		if (!btnText) {
			btnText = defaults[tone] || defaults.neutral || 'Add to cart';
		}
		$('#cro-sticky-cart-preview-btn').text(btnText);

		var bg = $('input[name="sticky_cart_bg_color"]').val() || '#ffffff';
		var btnBg = $('input[name="sticky_cart_button_color"]').val() || '#333333';
		$('#cro-sticky-cart-preview-inner').css('background-color', bg);
		$('#cro-sticky-cart-preview-btn').css({
			'background-color': btnBg,
			color: '#ffffff'
		});

		$('#cro-sticky-cart-preview-image-wrap').toggle($('input[name="sticky_cart_show_image"]').is(':checked'));
		$('#cro-sticky-cart-preview-title').toggle($('input[name="sticky_cart_show_title"]').is(':checked'));
		$('#cro-sticky-cart-preview-price').toggle($('input[name="sticky_cart_show_price"]').is(':checked'));
	}

	$(document).ready(function() {
		// Initialize color pickers on boosters/settings pages.
		if ($('.cro-color-picker').length && $.fn.wpColorPicker) {
			$('.cro-color-picker').wpColorPicker({
				change: function() {
					$(document).trigger('croShippingBarPreviewUpdate');
					$(document).trigger('croStickyCartPreviewUpdate');
				},
				clear: function() {
					$(document).trigger('croShippingBarPreviewUpdate');
					$(document).trigger('croStickyCartPreviewUpdate');
				}
			});
		}

		$(document).on(
			'input change',
			'#shipping_bar_message_progress, #shipping_bar_threshold, #shipping_bar_use_woo',
			updateCroShippingBarPreview
		);
		$(document).on('change', '#shipping_bar_tone', updateCroShippingBarPreview);
		$(document).on('croShippingBarPreviewUpdate', updateCroShippingBarPreview);

		if ($('#cro-shipping-bar-preview-wrap').length) {
			updateCroShippingBarPreview();
		}

		$(document).on(
			'input change',
			'#sticky_cart_button_text, #sticky_cart_tone, input[name="sticky_cart_show_image"], input[name="sticky_cart_show_title"], input[name="sticky_cart_show_price"]',
			updateCroStickyCartPreview
		);
		$(document).on('select2:select', '#sticky_cart_tone', updateCroStickyCartPreview);
		$(document).on('croStickyCartPreviewUpdate', updateCroStickyCartPreview);

		if ($('#cro-sticky-cart-preview-wrap').length) {
			updateCroStickyCartPreview();
		}

		// Sticky nav: add .is-stuck when nav has scrolled past sentinel (for shadow).
		var sentinel = document.getElementById('cro-admin-layout-nav-sentinel');
		var nav = sentinel ? sentinel.nextElementSibling : null;
		if (sentinel && nav && nav.classList.contains('cro-admin-layout__nav')) {
			var observer = new IntersectionObserver(
				function(entries) {
					entries.forEach(function(entry) {
						if (entry.target === sentinel) {
							if (entry.intersectionRatio === 0) {
								nav.classList.add('is-stuck');
							} else {
								nav.classList.remove('is-stuck');
							}
						}
					});
				},
				{ root: null, rootMargin: '0px', threshold: 0 }
			);
			observer.observe(sentinel);
		}

		// Cart optimizer: per-category discount rows.
		$('.cro-per-category-discount-list').on('click', '.cro-add-per-cat', function() {
			var $list = $(this).closest('.cro-per-category-discount-list');
			var $first = $list.find('.cro-per-cat-row').first();
			if (!$first.length) {
				return;
			}
			var rm =
				typeof croAdmin !== 'undefined' && croAdmin.strings && croAdmin.strings.remove ? croAdmin.strings.remove : 'Remove';
			var ph =
				typeof croAdmin !== 'undefined' && croAdmin.strings && croAdmin.strings.categoryShort ? croAdmin.strings.categoryShort : 'Category…';
			var $row = $first.clone();
			$row.find('select').val('');
			$row.find('input[type="number"]').val('');
			$row.find('.cro-remove-per-cat').remove();
			$row.append(' <button type="button" class="button cro-remove-per-cat">' + rm + '</button>');
			$row.insertBefore($list.find('.cro-add-per-cat'));
			if ($.fn.selectWoo) {
				$row.find('select.cro-selectwoo').selectWoo('destroy').off('select2:unselect');
				$list.find('.cro-per-cat-select').each(function() {
					if (!$(this).data('selectWoo')) {
						$(this).selectWoo({ width: 'resolve', allowClear: true, placeholder: ph });
					}
				});
			}
		});
		$('.cro-per-category-discount-list').on('click', '.cro-remove-per-cat', function() {
			$(this).closest('.cro-per-cat-row').remove();
		});

		var croSelectAll = document.getElementById('cro-select-all');
		if (croSelectAll) {
			croSelectAll.addEventListener('change', function() {
				var cbs = document.querySelectorAll('.cro-campaign-cb');
				for (var i = 0; i < cbs.length; i++) {
					cbs[i].checked = this.checked;
				}
			});
		}

		var croFallbackSel = document.getElementById('fallback_id');
		var croFallbackRow = document.getElementById('cro-fallback-delay-row');
		if (croFallbackSel && croFallbackRow) {
			function croToggleFallbackDelay() {
				croFallbackRow.style.display = parseInt(croFallbackSel.value, 10) > 0 ? '' : 'none';
			}
			croFallbackSel.addEventListener('change', croToggleFallbackDelay);
			croToggleFallbackDelay();
		}

		(function croPresetsPreviewModal() {
			var modal = document.getElementById('cro-preset-preview-modal');
			if (!modal) {
				return;
			}
			document.querySelectorAll('.cro-preset-preview-btn').forEach(function(btn) {
				btn.addEventListener('click', function() {
					var id = this.getAttribute('data-preset-id');
					var content = document.getElementById('cro-preset-preview-' + id);
					var body = document.getElementById('cro-preset-preview-body');
					if (content && body) {
						body.innerHTML = content.innerHTML;
						modal.classList.remove('cro-hidden');
					}
				});
			});
			function closeModal() {
				modal.classList.add('cro-hidden');
			}
			var backdrop = document.querySelector('#cro-preset-preview-modal .cro-preset-modal-backdrop');
			if (backdrop) {
				backdrop.addEventListener('click', closeModal);
			}
			var closeBtn = document.querySelector('#cro-preset-preview-modal .cro-preset-modal-close');
			if (closeBtn) {
				closeBtn.addEventListener('click', closeModal);
			}
			var closeBtn2 = document.querySelector('#cro-preset-preview-modal .cro-preset-modal-close-btn');
			if (closeBtn2) {
				closeBtn2.addEventListener('click', closeModal);
			}
			document.addEventListener('keydown', function(e) {
				if (e.key === 'Escape' && !modal.classList.contains('cro-hidden')) {
					closeModal();
				}
			});
		})();

		if (typeof croAdmin !== 'undefined') {
			var notice = document.getElementById('cro-industry-pack-notice');
			$(document).on('click', '.cro-industry-pack-apply', function() {
				var packId = this.getAttribute('data-pack-id');
				if (!packId || !notice) {
					return;
				}
				var $btn = $(this);
				$btn.prop('disabled', true);
				$.post(croAdmin.ajaxUrl, {
					action: 'cro_apply_industry_pack',
					nonce: croAdmin.nonce,
					pack_id: packId
				}).done(function(res) {
					notice.style.display = 'block';
					notice.classList.remove('cro-hidden');
					var errText = (res && res.data && res.data.message) ? res.data.message : (croAdmin.strings && croAdmin.strings.error ? croAdmin.strings.error : 'Error');
					if (res && res.success && res.data && res.data.message) {
						notice.classList.remove('notice-error');
						notice.classList.add('notice-success', 'is-dismissible');
						var msg = document.createElement('p');
						msg.appendChild(document.createTextNode(res.data.message));
						notice.innerHTML = '';
						notice.appendChild(msg);
						if (res.data.campaign_ids && res.data.campaign_ids.length) {
							res.data.campaign_ids.forEach(function(cid) {
								msg.appendChild(document.createTextNode(' '));
								var a = document.createElement('a');
								a.href = croAdmin.adminUrl + '?page=cro-campaign-edit&campaign_id=' + encodeURIComponent(cid);
								a.textContent = 'Campaign #' + cid;
								msg.appendChild(a);
							});
						}
					} else {
						notice.classList.remove('notice-success');
						notice.classList.add('notice-error', 'is-dismissible');
						notice.innerHTML = '<p></p>';
						notice.querySelector('p').appendChild(document.createTextNode(errText));
					}
				}).fail(function() {
					notice.style.display = 'block';
					notice.classList.remove('notice-success', 'cro-hidden');
					notice.classList.add('notice-error', 'is-dismissible');
					notice.innerHTML = '<p>' + (croAdmin.strings && croAdmin.strings.error ? croAdmin.strings.error : 'Error') + '</p>';
				}).always(function() {
					$btn.prop('disabled', false);
				});
			});
		}

		var $croAiKey = $('#cro-ai-api-key');
		if ($croAiKey.length && typeof croAdmin !== 'undefined' && croAdmin.aiApiKey) {
			var aiK = croAdmin.aiApiKey;
			var $toggle = $('#cro-ai-api-key-toggle');
			$toggle.on('click', function() {
				var isPwd = $croAiKey.attr('type') === 'password';
				$croAiKey.attr('type', isPwd ? 'text' : 'password');
				$toggle.attr('aria-pressed', isPwd ? 'true' : 'false');
				$toggle.text(isPwd ? aiK.hide : aiK.show);
			});
			$('#cro-ai-test-connection').on('click', function() {
				var $btn = $(this);
				var $fb = $('#cro-ai-test-feedback');
				if (!croAdmin.aiTestNonce) {
					return;
				}
				$btn.prop('disabled', true);
				$fb.removeClass('cro-ai-test-feedback--ok cro-ai-test-feedback--err').attr('hidden', true).text('');
				$.ajax({
					url: croAdmin.ajaxUrl,
					type: 'POST',
					dataType: 'json',
					data: { action: 'cro_ai_test_connection', nonce: croAdmin.aiTestNonce }
				}).done(function(res) {
					if (res && res.success && res.data && res.data.model) {
						var okTxt = croAdmin.aiStrings && croAdmin.aiStrings.testOk ? croAdmin.aiStrings.testOk + ' ' : '';
						$fb.text(okTxt + '(' + res.data.model + ')').addClass('cro-ai-test-feedback--ok').removeAttr('hidden');
						var $st = $('.cro-ai-connection-status');
						$st.empty();
						$st.append($('<span class="cro-ai-connection-status__icon cro-ai-connection-status__icon--ok dashicons dashicons-yes" aria-hidden="true"></span>'));
						$st.append($('<span class="screen-reader-text"></span>').text(aiK.verifiedSr));
					} else {
						var msg = (res && res.data && res.data.message) ? res.data.message : (croAdmin.aiStrings && croAdmin.aiStrings.testFail ? croAdmin.aiStrings.testFail : 'Error');
						$fb.text(msg).addClass('cro-ai-test-feedback--err').removeAttr('hidden');
					}
				}).fail(function(xhr) {
					var msg = croAdmin.aiStrings && croAdmin.aiStrings.testFail ? croAdmin.aiStrings.testFail : 'Error';
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						msg = xhr.responseJSON.data.message;
					}
					$fb.text(msg).addClass('cro-ai-test-feedback--err').removeAttr('hidden');
				}).always(function() {
					$btn.prop('disabled', false);
				});
			});
		}

		(function croSequenceStepsInit() {
			var table = document.getElementById('cro-sequence-steps');
			var addBtn = document.getElementById('cro-sequence-add-step');
			var wrap = document.getElementById('cro-sequence-step-template-wrap');
			if (!table || !addBtn || !wrap) {
				return;
			}
			function renumberStepLabels() {
				var rows = table.querySelectorAll('tbody .cro-sequence-step-row');
				rows.forEach(function(row, i) {
					var label = row.querySelector('.cro-sequence-step-num');
					if (label) {
						label.textContent = String(i + 1);
					}
				});
			}
			function refreshRemoveButtons() {
				var rows = table.querySelectorAll('tbody .cro-sequence-step-row');
				var show = rows.length > 1;
				rows.forEach(function(row) {
					var btn = row.querySelector('.cro-sequence-remove-step');
					if (btn) {
						btn.style.visibility = show ? 'visible' : 'hidden';
					}
				});
			}
			addBtn.addEventListener('click', function() {
				var tpl = wrap.querySelector('.cro-sequence-step-template');
				if (!tpl) {
					return;
				}
				var clone = tpl.cloneNode(true);
				clone.classList.remove('cro-sequence-step-template', 'cro-hidden');
				clone.removeAttribute('hidden');
				table.querySelector('tbody').appendChild(clone);
				renumberStepLabels();
				refreshRemoveButtons();
			});
			table.addEventListener('click', function(e) {
				if (!e.target.classList.contains('cro-sequence-remove-step')) {
					return;
				}
				var row = e.target.closest('.cro-sequence-step-row');
				if (!row || table.querySelectorAll('tbody .cro-sequence-step-row').length <= 1) {
					return;
				}
				row.remove();
				renumberStepLabels();
				refreshRemoveButtons();
			});
			refreshRemoveButtons();
		})();

		(function croSequenceSortable() {
			var tbody = document.querySelector('#cro-sequence-steps tbody');
			if (!tbody || typeof Sortable === 'undefined') {
				return;
			}
			var tableEl = document.getElementById('cro-sequence-steps');
			Sortable.create(tbody, {
				handle: '.cro-sequence-drag-handle',
				animation: 150,
				ghostClass: 'cro-sequence-row-ghost',
				onEnd: function() {
					if (!tableEl) {
						return;
					}
					var rows = tableEl.querySelectorAll('tbody .cro-sequence-step-row');
					rows.forEach(function(row, i) {
						var label = row.querySelector('.cro-sequence-step-num');
						if (label) {
							label.textContent = String(i + 1);
						}
					});
				}
			});
		})();

		(function croAbandonedCartEmailInit() {
			var form = document.getElementById('cro-abandoned-cart-form');
			if (!form || typeof croAbandonedCart === 'undefined') {
				return;
			}
			var AC = croAbandonedCart;
			var S = AC.strings || {};
			var subjectInput = document.getElementById('cro_email_subject_template');
			var editorId = 'cro_email_body_template';
			var previewSubject = document.getElementById('cro_preview_subject');
			var previewIframe = document.getElementById('cro_preview_iframe');
			var refreshBtn = document.getElementById('cro_refresh_preview');
			var testTo = document.getElementById('cro_test_email_to');
			var sendTestBtn = document.getElementById('cro_send_test_email');
			var testNotice = document.getElementById('cro_test_email_notice');

			function getBodyContent() {
				if (typeof tinymce !== 'undefined') {
					var ed = tinymce.get(editorId);
					if (ed && !ed.isHidden()) {
						return ed.getContent();
					}
				}
				var ta = document.getElementById(editorId);
				return ta ? ta.value : '';
			}

			function setBodyContent(html) {
				if (typeof tinymce !== 'undefined') {
					var ed2 = tinymce.get(editorId);
					if (ed2) {
						ed2.setContent(html || '');
					}
				}
				var ta2 = document.getElementById(editorId);
				if (ta2) {
					ta2.value = html || '';
				}
			}

			function insertTokenAtCursor(token) {
				if (typeof tinymce !== 'undefined') {
					var ed3 = tinymce.get(editorId);
					if (ed3 && !ed3.isHidden()) {
						ed3.insertContent(token, { format: 'html' });
						return;
					}
				}
				var ta3 = document.getElementById(editorId);
				if (!ta3) {
					return;
				}
				var start = ta3.selectionStart;
				var end = ta3.selectionEnd;
				var text = ta3.value;
				ta3.value = text.slice(0, start) + token + text.slice(end);
				ta3.selectionStart = ta3.selectionEnd = start + token.length;
				ta3.focus();
			}

			function getNonce() {
				return AC.nonce ? AC.nonce : '';
			}

			function updatePreview() {
				var subject = subjectInput ? subjectInput.value : '';
				var body = getBodyContent();
				var nonce = getNonce();
				if (!nonce) {
					return;
				}
				var data = new FormData();
				data.append('action', 'cro_abandoned_cart_preview');
				data.append('nonce', nonce);
				data.append('subject', subject);
				data.append('body', body);

				fetch(AC.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
					.then(function(r) { return r.json(); })
					.then(function(res) {
						if (res.success && res.data) {
							if (previewSubject) {
								previewSubject.textContent = res.data.subject || '';
							}
							if (previewIframe && previewIframe.contentDocument) {
								previewIframe.contentDocument.open();
								previewIframe.contentDocument.write(res.data.body || '');
								previewIframe.contentDocument.close();
							}
						}
					}).catch(function() {});
			}

			if (refreshBtn) {
				refreshBtn.addEventListener('click', updatePreview);
			}

			function showTestNotice(success, message) {
				if (!testNotice) {
					return;
				}
				testNotice.style.display = 'block';
				testNotice.className = 'cro-test-email-notice notice is-dismissible ' + (success ? 'notice-success' : 'notice-error');
				testNotice.setAttribute('role', 'alert');
				var p = document.createElement('p');
				p.textContent = message || '';
				testNotice.innerHTML = '';
				testNotice.appendChild(p);
			}

			if (sendTestBtn && testTo) {
				sendTestBtn.addEventListener('click', function() {
					var to = (testTo.value || '').trim();
					if (!to) {
						showTestNotice(false, S.emailRequired || '');
						return;
					}
					if (testNotice) {
						testNotice.style.display = 'none';
					}
					sendTestBtn.disabled = true;
					var data = new FormData();
					data.append('action', 'cro_abandoned_cart_send_test');
					data.append('nonce', getNonce());
					data.append('to', to);
					data.append('subject', subjectInput ? subjectInput.value : '');
					data.append('body', getBodyContent());

					fetch(AC.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
						.then(function(r) { return r.json(); })
						.then(function(res) {
							sendTestBtn.disabled = false;
							var msg = (res.data && res.data.message) ? res.data.message : (res.success ? (S.testSent || '') : (S.testFail || ''));
							showTestNotice(!!res.success, msg);
						}).catch(function() {
							sendTestBtn.disabled = false;
							showTestNotice(false, S.requestFail || '');
						});
				});
			}

			document.querySelectorAll('.cro-email-insert-token').forEach(function(btn) {
				btn.addEventListener('click', function() {
					var token = btn.getAttribute('data-token');
					if (token) {
						insertTokenAtCursor(token);
					}
				});
			});

			var resetBtn = document.getElementById('cro_reset_body_template');
			if (resetBtn && AC.defaultBodyTemplate !== undefined) {
				resetBtn.addEventListener('click', function() {
					if (window.confirm(S.confirmReset || '')) {
						setBodyContent(AC.defaultBodyTemplate);
						updatePreview();
					}
				});
			}

			if (getNonce()) {
				setTimeout(updatePreview, 300);
			}
		})();

		(function croAbandonedCartsListDrawer() {
			var cfgEl = document.getElementById('cro-abandoned-carts-list-config');
			if (!cfgEl || !cfgEl.textContent) {
				return;
			}
			var cfg;
			try {
				cfg = JSON.parse(cfgEl.textContent);
			} catch (eCfg) {
				return;
			}
			var S = cfg.strings || {};
			var nonce = cfg.nonce || '';
			var ajaxUrl = cfg.ajaxUrl || '';
			var drawer = document.getElementById('cro-ac-drawer');
			var drawerContent = document.getElementById('cro-ac-drawer-content');
			var drawerLoading = document.getElementById('cro-ac-drawer-loading');
			var closeBtn = drawer ? drawer.querySelector('.cro-ac-drawer__close') : null;
			var backdrop = drawer ? drawer.querySelector('.cro-ac-drawer__backdrop') : null;

			function openDrawer() {
				if (!drawer) {
					return;
				}
				drawer.removeAttribute('hidden');
				drawer.setAttribute('aria-hidden', 'false');
				document.body.style.overflow = 'hidden';
			}
			function closeDrawer() {
				if (!drawer) {
					return;
				}
				drawer.setAttribute('hidden', '');
				drawer.setAttribute('aria-hidden', 'true');
				document.body.style.overflow = '';
			}
			function renderDrawer(data) {
				if (!drawerContent) {
					return;
				}
				var currency = data.currency || '';
				var total = data.cart_total != null ? currency + ' ' + Number(data.cart_total).toFixed(2) : '—';
				var html = '<p><strong>' + (data.email || '—') + '</strong></p>';
				if (data.segment_label) {
					var segClass = data.segment === 'high' ? 'cro-ac-segment cro-ac-segment--high' : 'cro-ac-segment cro-ac-segment--standard';
					html += '<p class="cro-ac-drawer-segment"><strong>' + (S.segment || 'Segment') + ':</strong> <span class="' + segClass + '">' + String(data.segment_label) + '</span></p>';
				}
				if (data.schedule && data.schedule.planned_local) {
					var pl = data.schedule.planned_local;
					html += '<h3>' + (S.plannedTitle || '') + '</h3><ul>';
					html += '<li>' + (S.email1 || '') + ': ' + (pl[1] || '—') + '</li>';
					html += '<li>' + (S.email2 || '') + ': ' + (pl[2] || '—') + '</li>';
					html += '<li>' + (S.email3 || '') + ': ' + (pl[3] || '—') + '</li></ul>';
				}
				html += '<h3>' + (S.cartItems || '') + '</h3>';
				if (data.cart_items && data.cart_items.length) {
					html += '<ul>';
					data.cart_items.forEach(function(it) {
						html += '<li>' + (it.name || '') + ' × ' + (it.quantity || 1) + '</li>';
					});
					html += '</ul><p><strong>' + (S.total || '') + ':</strong> ' + total + '</p>';
				} else {
					html += '<p>—</p>';
				}
				html += '<h3>' + (S.checkoutTitle || '') + '</h3>';
				html += '<a href="' + (data.checkout_url || '#') + '" class="button button-primary cro-ac-drawer-checkout" target="_blank" rel="noopener">' + (S.openCheckout || '') + '</a>';
				html += '<h3>' + (S.emailLog || '') + '</h3><ul>';
				var log = data.email_log || {};
				html += '<li>Email 1: ' + (log.email_1 || (S.notSent || '')) + '</li>';
				html += '<li>Email 2: ' + (log.email_2 || (S.notSent || '')) + '</li>';
				html += '<li>Email 3: ' + (log.email_3 || (S.notSent || '')) + '</li></ul>';
				html += '<h3>' + (S.coupon || '') + '</h3><p>' + (data.discount_coupon || '—') + '</p>';
				var cid = parseInt(data.id, 10) || 0;
				if (cid) {
					html += '<h3>' + (S.aiPreviewTitle || '') + '</h3>';
					html += '<p class="description" style="margin-bottom:8px;">' + (S.aiPreviewDesc || '') + '</p>';
					html += '<p style="margin:0 0 8px;">';
					html += '<button type="button" class="button button-small cro-ai-preview-email" data-cart-id="' + cid + '" data-email="1">' + (S.previewBtn1 || '') + '</button> ';
					html += '<button type="button" class="button button-small cro-ai-preview-email" data-cart-id="' + cid + '" data-email="2">' + (S.previewBtn2 || '') + '</button> ';
					html += '<button type="button" class="button button-small cro-ai-preview-email" data-cart-id="' + cid + '" data-email="3">' + (S.previewBtn3 || '') + '</button>';
					html += '</p>';
				}
				drawerContent.innerHTML = html;
				drawerContent.style.display = 'block';
				if (drawerLoading) {
					drawerLoading.style.display = 'none';
				}
			}

			document.addEventListener('click', function(e) {
				var btn = e.target && e.target.closest('.cro-ac-btn-detail');
				if (!btn || !btn.dataset.id) {
					return;
				}
				e.preventDefault();
				var id = btn.dataset.id;
				openDrawer();
				if (drawerContent) {
					drawerContent.style.display = 'none';
				}
				if (drawerLoading) {
					drawerLoading.style.display = 'block';
				}
				var formData = new FormData();
				formData.append('action', 'cro_abandoned_cart_drawer');
				formData.append('nonce', nonce);
				formData.append('id', id);
				fetch(ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
					.then(function(r) { return r.json(); })
					.then(function(res) {
						if (res.success && res.data) {
							renderDrawer(res.data);
						} else if (drawerContent) {
							drawerContent.innerHTML = '<p>' + (S.loadError || '') + '</p>';
							drawerContent.style.display = 'block';
							if (drawerLoading) {
								drawerLoading.style.display = 'none';
							}
						}
					})
					.catch(function() {
						if (drawerContent) {
							drawerContent.innerHTML = '<p>' + (S.requestFailed || '') + '</p>';
							drawerContent.style.display = 'block';
						}
						if (drawerLoading) {
							drawerLoading.style.display = 'none';
						}
					});
			});

			if (closeBtn) {
				closeBtn.addEventListener('click', closeDrawer);
			}
			if (backdrop) {
				backdrop.addEventListener('click', closeDrawer);
			}
		})();

		var copyReportBtn = document.getElementById('cro-copy-report');
		if (typeof Chart !== 'undefined') {
			var chartDataEl = document.getElementById('cro-ab-chart-data');
			var chartCanvas = document.getElementById('cro-ab-variation-chart');
			if (chartDataEl && chartCanvas && chartDataEl.textContent) {
				try {
					var ch = JSON.parse(chartDataEl.textContent);
					if (ch && Array.isArray(ch.labels) && ch.labels.length) {
						new Chart(chartCanvas, {
							type: 'bar',
							data: {
								labels: ch.labels,
								datasets: [
									{
										label: ch.labelImpressions || 'Impressions',
										data: ch.impressions || [],
										backgroundColor: 'rgba(51,51,51,0.55)'
									},
									{
										label: ch.labelConversions || 'Conversions',
										data: ch.conversions || [],
										backgroundColor: 'rgba(34,197,94,0.6)'
									}
								]
							},
							options: {
								responsive: true,
								scales: {
									x: { stacked: false },
									y: { beginAtZero: true }
								}
							}
						});
					}
				} catch (errAbChart) {}
			}
		}

		if (copyReportBtn) {
			copyReportBtn.addEventListener('click', function () {
				var report =
					copyReportBtn.getAttribute('data-report') ||
					(document.getElementById('cro-report-text') ? document.getElementById('cro-report-text').value : '');
				if (!report) {
					return;
				}
				var copiedLabel =
					typeof croAdmin !== 'undefined' && croAdmin.copied_label
						? croAdmin.copied_label
						: typeof croAdmin !== 'undefined' && croAdmin.strings && croAdmin.strings.copied
							? croAdmin.strings.copied
							: 'Copied!';
				var prevText = copyReportBtn.textContent;
				function flashCopied() {
					copyReportBtn.textContent = copiedLabel;
					setTimeout(function () {
						copyReportBtn.textContent = prevText;
					}, 2000);
				}
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(report).then(flashCopied);
				} else {
					var ta = document.getElementById('cro-report-text');
					if (ta) {
						ta.select();
						document.execCommand('copy');
						flashCopied();
					}
				}
			});
		}
	});

})(jQuery);
