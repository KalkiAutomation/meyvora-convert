/**
 * Initialize SelectWoo on CRO admin multi-selects (.meyvc-select-woo).
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
		var placeholder = (typeof meyvcSelectWoo !== 'undefined' && meyvcSelectWoo.placeholder) ? meyvcSelectWoo.placeholder : 'Search or select…';
		var ajaxUrl = (typeof meyvcSelectWoo !== 'undefined' && meyvcSelectWoo.ajaxUrl) ? meyvcSelectWoo.ajaxUrl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
		var $drawer = $('#meyvc-offer-drawer');
		var drawerOpen = $drawer.length && $drawer.hasClass('is-open');

		$('.meyvc-select-woo').each(function () {
			var $el = $(this);
			if ($el.data('selectWoo')) {
				return;
			}
			// Skip selects inside the offer drawer when drawer is closed (avoids hidden init bugs).
			if ($el.closest('#meyvc-offer-drawer').length && !drawerOpen) {
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
			if ($el.closest('#meyvc-offer-drawer').length) {
				opts.dropdownParent = $('#meyvc-offer-drawer .meyvc-offer-drawer-panel');
			}
			if ($el.hasClass('meyvc-select-products') && $el.data('action') === 'meyvc_search_products' && ajaxUrl) {
				opts.ajax = {
					url: ajaxUrl,
					dataType: 'json',
					delay: 250,
					data: function (params) {
						return {
							action: 'meyvc_search_products',
							term: params.term || ''
						};
					},
					processResults: function (data) {
						return { results: (data && data.results) ? data.results : (Array.isArray(data) ? data : []) };
					}
				};
				opts.placeholder = (typeof meyvcSelectWoo !== 'undefined' && meyvcSelectWoo.searchProducts) ? meyvcSelectWoo.searchProducts : 'Search products…';
				opts.minimumInputLength = 1;
			}
			$el.selectWoo(opts);
		});
	}

	$(function () {
		initSelectWoo();
	});

	// Re-init when new content is added (e.g. offer drawer already in DOM but SelectWoo runs before drawer is visible).
	$(document).on('meyvc-select-woo-init', function () {
		initSelectWoo();
	});
})(jQuery);
