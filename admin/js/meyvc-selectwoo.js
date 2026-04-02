/**
 * Single initializer for SelectWoo on CRO admin.
 * Finds all selects with class .meyvc-selectwoo (or .meyvc-select-woo for backward compat)
 * and initializes SelectWoo with width: resolve, allowClear when placeholder exists,
 * and supports multiple + searchable (including AJAX product/category/page search).
 * Use MEYVC_SelectWoo.initWithin(containerElement) when opening a drawer/modal so
 * selects inside get dropdownParent set to the panel and dropdown appears above overlay.
 *
 * @package Meyvora_Convert
 */

(function ($) {
	'use strict';

	var SELECTOR = '.meyvc-selectwoo, .meyvc-select-woo';
	var defaultPlaceholder = (typeof meyvcSelectWoo !== 'undefined' && meyvcSelectWoo.placeholder) ? meyvcSelectWoo.placeholder : 'Search or select…';
	var ajaxUrl = (typeof meyvcSelectWoo !== 'undefined' && meyvcSelectWoo.ajaxUrl) ? meyvcSelectWoo.ajaxUrl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '');

	function debugLog(message, detail) {
		if (window.meyvcDebugSelectWoo) {
			var args = ['[CRO SelectWoo] ' + message];
			if (detail !== undefined) {
				args.push(detail);
			}
			console.log.apply(console, args);
		}
	}

	function getDropdownParent($el) {
		if ($el.closest('#meyvc-offer-drawer').length) {
			return $('#meyvc-offer-drawer .meyvc-offer-drawer-panel')[0] || null;
		}
		if ($el.closest('.meyvc-builder-content').length || $el.closest('.meyvc-builder-wrap').length) {
			return document.body;
		}
		return null;
	}

	function buildSelectWooOptions($el, dropdownParent) {
		var placeholder = $el.attr('data-placeholder') || $el.data('placeholder') || defaultPlaceholder;
		var opts = {
			width: '100%',
			allowClear: !!placeholder,
			placeholder: placeholder,
			language: {
				noResults: function () {
					return $el.attr('data-no-results') || $el.data('no-results') || 'No results found';
				},
				searching: function () {
					return $el.attr('data-searching') || $el.data('searching') || 'Searching…';
				}
			}
		};
		if (dropdownParent) {
			opts.dropdownParent = $(dropdownParent);
		}
		var dataOpts = $el.attr('data-meyvc-selectwoo-opts') || $el.data('meyvc-selectwoo-opts');
		if (dataOpts && typeof dataOpts === 'string') {
			try {
				dataOpts = JSON.parse(dataOpts);
			} catch (e) {
				dataOpts = null;
			}
		}
		if (dataOpts && typeof dataOpts === 'object') {
			$.extend(true, opts, dataOpts);
		}
		var context = { id: $el.attr('id') || '', name: $el.attr('name') || '', inDrawer: $el.closest('#meyvc-offer-drawer').length > 0 };
		if (typeof window.meyvcSelectWooOptionsFilter === 'function') {
			opts = window.meyvcSelectWooOptionsFilter(opts, context) || opts;
		}
		var action = $el.attr('data-action') || $el.data('action');
		if (action === 'meyvc_search_products' && ajaxUrl) {
			opts.ajax = {
				url: ajaxUrl,
				dataType: 'json',
				delay: 250,
				data: function (params) {
					return {
						action: 'meyvc_search_products',
						term: params.term || '',
						nonce: (typeof meyvcAdmin !== 'undefined' && meyvcAdmin.nonce) ? meyvcAdmin.nonce : ''
					};
				},
				processResults: function (data) {
					var list = (data && data.results) ? data.results : (data && data.data ? data.data : (Array.isArray(data) ? data : []));
					return { results: list };
				}
			};
			opts.minimumInputLength = 1;
			opts.placeholder = (typeof meyvcSelectWoo !== 'undefined' && meyvcSelectWoo.searchProducts) ? meyvcSelectWoo.searchProducts : (placeholder || 'Search products…');
		} else if (action === 'meyvc_search_pages' && ajaxUrl) {
			opts.ajax = {
				url: ajaxUrl,
				dataType: 'json',
				delay: 250,
				data: function (params) {
					return {
						action: 'meyvc_search_pages',
						search: params.term || '',
						nonce: (typeof meyvcAdmin !== 'undefined' && meyvcAdmin.nonce) ? meyvcAdmin.nonce : ''
					};
				},
				processResults: function (data) {
					var list = (data && data.data) ? data.data : (data && data.results ? data.results : (Array.isArray(data) ? data : []));
					return { results: list };
				}
			};
			opts.minimumInputLength = 2;
		}
		return opts;
	}

	/**
	 * Initialize a single SelectWoo element. No-op if already initialized (guards against double-init).
	 *
	 * @param {jQuery} $el - Element matching SELECTOR.
	 * @param {HTMLElement|jQuery|null} [dropdownParent] - Optional dropdown parent (e.g. drawer panel). If omitted, derived from DOM context.
	 * @param {string} [source] - Optional label for debug (e.g. 'initSelectWoo', 'MutationObserver', 'initWithin').
	 */
	function initOne($el, dropdownParent, source) {
		if (typeof $.fn.selectWoo === 'undefined') {
			return;
		}
		if (!$el.length || !$el.is(SELECTOR)) {
			return;
		}
		if ($el.data('selectWoo')) {
			return;
		}
		var parentEl = dropdownParent != null ? (dropdownParent && dropdownParent.jquery ? dropdownParent[0] : dropdownParent) : getDropdownParent($el);
		var opts = buildSelectWooOptions($el, parentEl ? $(parentEl) : null);
		$el.selectWoo(opts);
		debugLog('init', {
			source: source || 'initOne',
			id: $el.attr('id') || null,
			name: $el.attr('name') || null,
			dropdownParent: parentEl ? (parentEl.id ? '#' + parentEl.id : parentEl.className || 'element') : 'default'
		});
	}

	function initSelectWoo() {
		if (typeof $.fn.selectWoo === 'undefined') {
			return;
		}
		var $drawer = $('#meyvc-offer-drawer');
		var drawerOpen = $drawer.length && $drawer.hasClass('is-open');

		$(SELECTOR).each(function () {
			var $el = $(this);
			if ($el.data('selectWoo')) {
				return;
			}
			if ($el.closest('#meyvc-offer-drawer').length && !drawerOpen) {
				return;
			}
			initOne($el, null, 'initSelectWoo');
		});
	}

	function startMutationObserver() {
		if (typeof MutationObserver === 'undefined') {
			return;
		}
		var root = document.body;
		var observer = new MutationObserver(function (mutations) {
			for (var i = 0; i < mutations.length; i++) {
				var added = mutations[i].addedNodes;
				for (var j = 0; j < added.length; j++) {
					var node = added[j];
					if (node.nodeType !== 1) {
						continue;
					}
					var $node = $(node);
					var $matches = $node.find(SELECTOR).addBack().filter(SELECTOR);
					$matches.each(function () {
						initOne($(this), null, 'MutationObserver');
					});
				}
			}
		});
		observer.observe(root, { childList: true, subtree: true });
	}

	/**
	 * Initialize SelectWoo only within a container (e.g. offer drawer panel).
	 * Use when opening a drawer/modal so dropdownParent is set to the panel and dropdown appears above overlay.
	 * If a select was already initialized while hidden, it is destroyed and re-initialized so width and dropdown work correctly.
	 *
	 * @param {HTMLElement|jQuery} containerElement - Container to search for .meyvc-selectwoo selects (e.g. drawer panel).
	 * @param {HTMLElement|jQuery} [dropdownParentElement] - Element to append dropdown to (default: .meyvc-offer-drawer-panel if container is inside #meyvc-offer-drawer).
	 */
	function initWithin(containerElement, dropdownParentElement) {
		if (typeof $.fn.selectWoo === 'undefined') {
			return;
		}
		var $container = $(containerElement);
		if (!$container.length) {
			return;
		}
		var parentEl = null;
		if (dropdownParentElement) {
			parentEl = $(dropdownParentElement)[0] || dropdownParentElement;
		} else if ($container.closest('#meyvc-offer-drawer').length) {
			parentEl = $container.closest('#meyvc-offer-drawer').find('.meyvc-offer-drawer-panel').first()[0] || null;
		}
		var inDrawer = !!parentEl;

		$container.find(SELECTOR).each(function () {
			var $el = $(this);
			if ($el.data('selectWoo')) {
				if (inDrawer) {
					$el.selectWoo('destroy');
				} else {
					return;
				}
			}
			initOne($el, parentEl, 'initWithin');
		});
	}

	window.MEYVC_SelectWoo = {
		initWithin: initWithin
	};

	$(function () {
		initSelectWoo();
		startMutationObserver();
	});

	$(document).on('meyvc-select-woo-init', function () {
		initSelectWoo();
	});
})(jQuery);
