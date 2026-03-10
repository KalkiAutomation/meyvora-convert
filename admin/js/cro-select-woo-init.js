/**
 * Initialize SelectWoo on CRO admin multi-selects (.cro-select-woo).
 * Only runs when WooCommerce SelectWoo is available; no-op otherwise.
 *
 * @package Meyvora_Convert
 */

(function ($) {
	'use strict';

	function initSelectWoo() {
		if (typeof $.fn.selectWoo === 'undefined') {
			return;
		}
		var placeholder = (typeof croSelectWoo !== 'undefined' && croSelectWoo.placeholder) ? croSelectWoo.placeholder : 'Search or select…';
		var ajaxUrl = (typeof croSelectWoo !== 'undefined' && croSelectWoo.ajaxUrl) ? croSelectWoo.ajaxUrl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
		var $drawer = $('#cro-offer-drawer');
		var drawerOpen = $drawer.length && $drawer.hasClass('is-open');

		$('.cro-select-woo').each(function () {
			var $el = $(this);
			if ($el.data('selectWoo')) {
				return;
			}
			// Skip selects inside the offer drawer when drawer is closed (avoids hidden init bugs).
			if ($el.closest('#cro-offer-drawer').length && !drawerOpen) {
				return;
			}
			var opts = {
				width: 'resolve',
				allowClear: true,
				placeholder: $el.data('placeholder') || placeholder,
				language: {
					noResults: function () {
						return $el.data('no-results') || 'No results found';
					},
					searching: function () {
						return $el.data('searching') || 'Searching…';
					}
				}
			};
			// Dropdown inside drawer panel so it appears above overlay and scrolls with panel.
			if ($el.closest('#cro-offer-drawer').length) {
				opts.dropdownParent = $('#cro-offer-drawer .cro-offer-drawer-panel');
			}
			if ($el.hasClass('cro-select-products') && $el.data('action') === 'cro_search_products' && ajaxUrl) {
				opts.ajax = {
					url: ajaxUrl,
					dataType: 'json',
					delay: 250,
					data: function (params) {
						return {
							action: 'cro_search_products',
							term: params.term || ''
						};
					},
					processResults: function (data) {
						return { results: (data && data.results) ? data.results : (Array.isArray(data) ? data : []) };
					}
				};
				opts.placeholder = (typeof croSelectWoo !== 'undefined' && croSelectWoo.searchProducts) ? croSelectWoo.searchProducts : 'Search products…';
				opts.minimumInputLength = 1;
			}
			$el.selectWoo(opts);
		});
	}

	$(function () {
		initSelectWoo();
	});

	// Re-init when new content is added (e.g. offer drawer already in DOM but SelectWoo runs before drawer is visible).
	$(document).on('cro-select-woo-init', function () {
		initSelectWoo();
	});
})(jQuery);
