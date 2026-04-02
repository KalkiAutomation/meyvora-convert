/**
 * Classic Editor: "Add CRO Campaign" button and thickbox modal.
 * Inserts [meyvc_campaign id="X"] into TinyMCE or textarea.
 */
(function () {
	'use strict';

	function openModal() {
		var url = '#TB_inline?inlineId=meyvc-campaign-modal-content&width=420&height=240';
		if (typeof TB_show === 'function') {
			TB_show(typeof meyvcCampaignClassic !== 'undefined' && meyvcCampaignClassic.modalTitle ? meyvcCampaignClassic.modalTitle : 'Insert CRO Campaign', url);
		}
	}

	function insertShortcode(campaignId) {
		var shortcode = '[meyvc_campaign id="' + parseInt(campaignId, 10) + '"]';
		var editor = null;

		if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.hidden) {
			editor = tinymce.activeEditor;
			editor.execCommand('mceInsertContent', false, shortcode);
		} else {
			var textarea = document.getElementById('content');
			if (textarea) {
				var start = textarea.selectionStart;
				var end = textarea.selectionEnd;
				var text = textarea.value;
				textarea.value = text.slice(0, start) + shortcode + text.slice(end);
				textarea.selectionStart = textarea.selectionEnd = start + shortcode.length;
				textarea.focus();
			}
		}

		if (typeof tb_remove === 'function') {
			tb_remove();
		}
	}

	function init() {
		var btn = document.getElementById('meyvc-insert-campaign-btn');
		if (!btn) return;

		btn.addEventListener('click', function (e) {
			e.preventDefault();
			openModal();
		});

		// Use delegation so Insert works when modal content is inside thickbox overlay
		document.body.addEventListener('click', function (e) {
			if (e.target.id === 'meyvc-campaign-insert') {
				e.preventDefault();
				var selectEl = document.getElementById('meyvc-campaign-select');
				if (selectEl) {
					var id = selectEl.value;
					if (id && id !== '0') {
						insertShortcode(id);
					}
				}
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
