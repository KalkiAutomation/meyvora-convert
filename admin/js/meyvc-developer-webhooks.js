/**
 * Developer settings: webhook endpoints UI.
 */
(function ($) {
	'use strict';

	function post(action, data) {
		return $.ajax({
			url: meyvcDeveloperWebhooks.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: $.extend(
				{
					action: action,
					nonce: meyvcDeveloperWebhooks.nonce,
				},
				data || {}
			),
		});
	}

	function genSecret32() {
		try {
			var buf = new Uint8Array(16);
			window.crypto.getRandomValues(buf);
			var hex = '';
			for (var i = 0; i < buf.length; i++) {
				hex += ('0' + buf[i].toString(16)).slice(-2);
			}
			return hex;
		} catch (e) {
			return '';
		}
	}

	function escapeHtml(s) {
		var d = document.createElement('div');
		d.textContent = s;
		return d.innerHTML;
	}

	$(function () {
		var $panel = $('#meyvc-webhook-form-panel');
		var $form = $('#meyvc-webhook-endpoint-form');

		$('.js-meyvc-webhook-toggle-add').on('click', function () {
			$panel.slideToggle(200);
			$form[0].reset();
			$form.find('input[name="endpoint_id"]').val('');
			$form.find('input[name="webhook_active"]').prop('checked', true);
			$form.find('input[name="events[]"]').prop('checked', false);
			$form.find('input[name="secret"]').prop('required', true);
		});

		$('.js-meyvc-webhook-generate-secret').on('click', function () {
			var s = genSecret32();
			if (s) {
				$form.find('input[name="secret"]').val(s);
			}
		});

		$form.on('submit', function (ev) {
			ev.preventDefault();
			var id = $form.find('input[name="endpoint_id"]').val();
			var events = [];
			$form.find('input[name="events[]"]:checked').each(function () {
				events.push($(this).val());
			});
			post('meyvc_save_webhook_endpoint', {
				id: id,
				url: $form.find('input[name="url"]').val(),
				secret: $form.find('input[name="secret"]').val(),
				active: $form.find('input[name="webhook_active"]').is(':checked') ? 1 : 0,
				events_json: JSON.stringify(events),
			}).done(function (res) {
				if (res && res.success) {
					window.location.reload();
				} else {
					window.alert((res && res.data && res.data.message) || meyvcDeveloperWebhooks.strings.error);
				}
			});
		});

		$(document).on('click', '.js-meyvc-webhook-edit', function () {
			var $tr = $(this).closest('tr');
			$panel.slideDown(200);
			$form.find('input[name="secret"]').prop('required', false);
			$form.find('input[name="endpoint_id"]').val($tr.data('id'));
			$form.find('input[name="url"]').val($tr.data('url'));
			$form.find('input[name="secret"]').val('');
			$form.find('input[name="webhook_active"]').prop('checked', !!$tr.data('active'));
			var evs = [];
			try {
				evs = JSON.parse($tr.attr('data-events') || '[]') || [];
			} catch (e2) {
				evs = [];
			}
			$form.find('input[name="events[]"]').each(function () {
				var v = $(this).val();
				$(this).prop('checked', evs.indexOf(v) !== -1);
			});
			$('html, body').animate({ scrollTop: $panel.offset().top - 40 }, 200);
		});

		$(document).on('click', '.js-meyvc-webhook-delete', function () {
			if (!window.confirm(meyvcDeveloperWebhooks.strings.confirmDelete)) {
				return;
			}
			var id = $(this).closest('tr').data('id');
			post('meyvc_delete_webhook_endpoint', { id: id }).done(function (res) {
				if (res && res.success) {
					window.location.reload();
				}
			});
		});

		$(document).on('change', '.js-meyvc-webhook-active', function () {
			var $cb = $(this);
			var id = $cb.closest('tr').data('id');
			post('meyvc_toggle_webhook_endpoint', { id: id, active: $cb.is(':checked') ? 1 : 0 }).fail(function () {
				$cb.prop('checked', !$cb.is(':checked'));
			});
		});

		$(document).on('click', '.js-meyvc-webhook-test', function () {
			var id = $(this).closest('tr').data('id');
			var $btn = $(this);
			$btn.prop('disabled', true);
			post('meyvc_test_webhook_endpoint', { id: id })
				.done(function (res) {
					if (res && res.success && res.data) {
						var d = res.data;
						var msg =
							d.ok
								? meyvcDeveloperWebhooks.strings.testOk + ' HTTP ' + d.status + ' (' + d.ms + ' ms)'
								: meyvcDeveloperWebhooks.strings.testFail + ' ' + (d.error || '') + ' (' + d.ms + ' ms)';
						window.alert(msg);
					} else {
						window.alert(meyvcDeveloperWebhooks.strings.error);
					}
				})
				.always(function () {
					$btn.prop('disabled', false);
				});
		});

		$(document).on('click', '.js-meyvc-webhook-toggle-logs', function () {
			var $det = $(this).closest('td').find('.meyvc-webhook-log-expand');
			$det.toggle();
		});

		$(document).on('click', '.js-meyvc-webhook-view-all-logs', function (e) {
			e.preventDefault();
			var id = $(this).data('id');
			var $modal = $('#meyvc-webhook-logs-modal');
			var $body = $modal.find('.meyvc-webhook-logs-modal__body');
			$body.html('<p>' + escapeHtml(meyvcDeveloperWebhooks.strings.loading) + '</p>');
			$modal.attr('hidden', false);
			post('meyvc_get_webhook_logs', { id: id }).done(function (res) {
				if (!res || !res.success || !res.data || !res.data.logs) {
					$body.html('<p>' + escapeHtml(meyvcDeveloperWebhooks.strings.error) + '</p>');
					return;
				}
				var rows = res.data.logs;
				var html = '<table class="widefat striped"><thead><tr><th>' + escapeHtml(meyvcDeveloperWebhooks.strings.colTime) + '</th><th>' + escapeHtml(meyvcDeveloperWebhooks.strings.colEvent) + '</th><th>' + escapeHtml(meyvcDeveloperWebhooks.strings.colStatus) + '</th><th>' + escapeHtml(meyvcDeveloperWebhooks.strings.colMs) + '</th><th>' + escapeHtml(meyvcDeveloperWebhooks.strings.colError) + '</th></tr></thead><tbody>';
				rows.forEach(function (r) {
					var st = parseInt(r.status, 10);
					var ok = st >= 200 && st < 300;
					html +=
						'<tr class="' +
						(ok ? 'meyvc-webhook-log--ok' : 'meyvc-webhook-log--err') +
						'"><td>' +
						escapeHtml(r.t_display || '') +
						'</td><td>' +
						escapeHtml(r.event || '') +
						'</td><td>' +
						escapeHtml(String(r.status)) +
						'</td><td>' +
						escapeHtml(String(r.ms)) +
						'</td><td>' +
						escapeHtml(r.error || '') +
						'</td></tr>';
				});
				html += '</tbody></table>';
				$body.html(html);
			});
		});

		$(document).on('click', '.js-meyvc-webhook-logs-modal-close', function () {
			$('#meyvc-webhook-logs-modal').attr('hidden', true);
		});

		$('#meyvc-webhook-logs-modal').on('click', function (e) {
			if (e.target === this) {
				$(this).attr('hidden', true);
			}
		});
	});
})(jQuery);
