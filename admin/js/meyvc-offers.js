/**
 * CRO Offers admin – Offer Builder drawer, AJAX save, cards update.
 */
(function () {
	function applyOffersBootstrap( d ) {
		if ( ! d || typeof d !== 'object' ) {
			return;
		}
		if ( Array.isArray( d.offersData ) ) {
			window.meyvcOffersData = d.offersData;
		}
		if ( d.maxOffers != null ) {
			window.meyvcOffersMaxOffers = d.maxOffers;
		}
		if ( d.usedCount != null ) {
			window.meyvcOffersUsedCount = d.usedCount;
		}
		if ( d.nonce ) {
			window.meyvcOffersNonce = d.nonce;
		}
		if ( d.productCategories ) {
			window.meyvcOffersProductCategories = d.productCategories;
		}
		if ( d.offerConflictChoices ) {
			window.meyvcOfferConflictChoices = d.offerConflictChoices;
		}
	}
	if ( typeof meyvcOffersPageData !== 'undefined' && meyvcOffersPageData ) {
		applyOffersBootstrap( meyvcOffersPageData );
		return;
	}
	var el = document.getElementById( 'meyvc-offers-page-data' );
	if ( el && el.textContent ) {
		try {
			applyOffersBootstrap( JSON.parse( el.textContent ) );
		} catch ( e ) {
			// Invalid bootstrap JSON; meyvc-offers.js falls back to empty defaults.
		}
	}
} )();

(function ($) {
	'use strict';

	var drawer = $('#meyvc-offer-drawer');
	var form = $('#meyvc-offer-drawer-form');
	var offerIndexInput = $('#meyvc-drawer-offer-index');
	var drawerTitle = $('#meyvc-offer-drawer-title');
	var rewardTypeSelect = $('#meyvc-drawer-reward-type');
	var rewardAmountWrap = form.find('.meyvc-drawer-reward-amount-wrap');
	var rewardSuffix = form.find('.meyvc-drawer-reward-suffix');

	var maxOffers = parseInt(window.meyvcOffersMaxOffers, 10) || 5;
	var offersData = Array.isArray(window.meyvcOffersData) ? window.meyvcOffersData : [];
	var usedCount = parseInt(window.meyvcOffersUsedCount, 10) || 0;
	if ( usedCount >= maxOffers ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			var b = document.getElementById( 'meyvc-offers-add-btn' );
			if ( b ) {
				b.disabled = true;
			}
		} );
	}
	var drawerReturnFocusEl = null;

	var i18n = (window.meyvcOffersI18n || {});
var checkIcon = (i18n.checkIcon != null && i18n.checkIcon !== '') ? i18n.checkIcon : '✓';
var crossIcon = (i18n.crossIcon != null && i18n.crossIcon !== '') ? i18n.crossIcon : '✗';

	function getDrawerFocusables() {
		var panel = document.querySelector('#meyvc-offer-drawer .meyvc-offer-drawer-panel');
		if (!panel) return [];
		var selector = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
		var nodes = panel.querySelectorAll(selector);
		return Array.prototype.filter.call(nodes, function (el) {
			return el.offsetParent !== null && (el.getAttribute('tabindex') !== '-1' || /^(INPUT|SELECT|TEXTAREA|BUTTON|A)$/.test(el.tagName));
		});
	}

	function trapDrawerFocus(e) {
		if (e.key !== 'Tab' || !drawer.hasClass('is-open')) return;
		var focusables = getDrawerFocusables();
		if (focusables.length === 0) return;
		var first = focusables[0];
		var last = focusables[focusables.length - 1];
		if (e.shiftKey) {
			if (document.activeElement === first) {
				e.preventDefault();
				last.focus();
			}
		} else {
			if (document.activeElement === last) {
				e.preventDefault();
				first.focus();
			}
		}
	}

	function openDrawer(mode, index, triggerEl) {
		if (mode === 'edit' && (index < 0 || index >= maxOffers)) return;
		drawerReturnFocusEl = triggerEl && triggerEl.length ? triggerEl[0] : (typeof triggerEl === 'object' && triggerEl && triggerEl.nodeType ? triggerEl : null);
		clearInlineErrors();
		var offer = (mode === 'edit' && offersData[index]) ? offersData[index] : getEmptyOffer(index);
		offerIndexInput.val(mode === 'add' ? '' : String(index));
		drawerTitle.text(mode === 'add' ? (i18n.addOffer || 'Add Offer') : (i18n.editOffer || 'Edit Offer'));
		populateForm(offer);
		toggleRewardAmountVisibility(offer.reward_type || 'percent');
		updateOfferSummary();
		drawer.addClass('is-open').attr('aria-hidden', 'false');
		$(document).on('keydown.meyvcDrawerTrap', trapDrawerFocus);
		setTimeout(function () {
			var first = getDrawerFocusables()[0];
			if (first) first.focus();
			else $('#meyvc-drawer-headline').focus();
			var panel = document.querySelector('#meyvc-offer-drawer .meyvc-offer-drawer-panel');
			if (panel && window.MEYVC_SelectWoo && typeof window.MEYVC_SelectWoo.initWithin === 'function') {
				window.MEYVC_SelectWoo.initWithin(panel);
				$(panel).find('.meyvc-selectwoo, .meyvc-select-woo').each(function () {
					var $s = $(this);
					if ($s.data('selectWoo')) {
						$s.trigger('change.select2');
					}
				});
			} else {
				$(document).trigger('meyvc-select-woo-init');
			}
			setTimeout(updateDrawerSectionMaxHeights, 50);
		}, 0);
	}

	function closeDrawer() {
		drawer.removeClass('is-open').attr('aria-hidden', 'true');
		$(document).off('keydown.meyvcDrawerTrap', trapDrawerFocus);
		if (drawerReturnFocusEl && typeof drawerReturnFocusEl.focus === 'function') {
			try {
				drawerReturnFocusEl.focus();
			} catch (err) {}
			drawerReturnFocusEl = null;
		}
	}

	/**
	 * Recalculate max-height for drawer section bodies (e.g. reward body after layout/per-cat changes).
	 * For each expanded section, sets max-height to content scrollHeight so layout is correct.
	 */
	function updateDrawerSectionMaxHeights() {
		if (!drawer.hasClass('is-open')) return;
		var panel = document.querySelector('#meyvc-offer-drawer .meyvc-offer-drawer-panel');
		if (!panel) return;
		$(panel).find('.meyvc-offer-drawer-section').each(function () {
			var $section = $(this);
			var $body = $section.find('.meyvc-offer-drawer-section__body').first();
			if (!$body.length) return;
			if ($section.hasClass('is-collapsed')) {
				$body.css('max-height', '');
				return;
			}
			var h = $body[0].scrollHeight;
			$body.css('max-height', h + 'px');
		});
	}

	function buildPerCategoryDiscountRow(catId, amount) {
		var cats = window.meyvcOffersProductCategories || [];
		var opts = cats.map(function (c) {
			return '<option value="' + escapeHtml(String(c.id)) + '"' + (String(c.id) === String(catId) ? ' selected' : '') + '>' + escapeHtml(c.name) + '</option>';
		}).join('');
		var row = '<div class="meyvc-drawer-per-cat-row">' +
			'<select name="meyvc_drawer_per_category_discount_cat[]" class="meyvc-drawer-select meyvc-selectwoo meyvc-per-cat-select" data-placeholder="' + (i18n.categoryPlaceholder || 'Category…') + '">' +
			'<option value="">' + (i18n.selectCategory || '— Select —') + '</option>' + opts + '</select>' +
			'<input type="number" name="meyvc_drawer_per_category_discount_amount[]" value="' + (amount !== undefined && amount !== '' ? escapeHtml(String(amount)) : '') + '" min="0" step="0.01" placeholder="' + (i18n.amount || 'Amount') + '" class="small-text meyvc-drawer-per-cat-amount" />' +
			'<button type="button" class="button meyvc-drawer-remove-per-cat">' + (i18n.remove || 'Remove') + '</button></div>';
		return row;
	}

	function buildPerCategoryDiscountList(perCategoryDiscount) {
		var $container = $('#meyvc-drawer-per-category-discount');
		if (!$container.length) return;
		$container.empty();
		var list = perCategoryDiscount && typeof perCategoryDiscount === 'object' && !Array.isArray(perCategoryDiscount) ? perCategoryDiscount : {};
		var keys = Object.keys(list);
		if (keys.length === 0) {
			$container.append(buildPerCategoryDiscountRow('', ''));
		} else {
			keys.forEach(function (catId) {
				$container.append(buildPerCategoryDiscountRow(catId, list[catId]));
			});
		}
		$container.append('<button type="button" class="button meyvc-drawer-add-per-cat">' + (i18n.addCategoryDiscount || 'Add category discount') + '</button>');
		if (window.MEYVC_SelectWoo && typeof window.MEYVC_SelectWoo.initWithin === 'function') {
			window.MEYVC_SelectWoo.initWithin($container[0]);
		}
	}

	$('#meyvc-drawer-per-category-discount').on('click', '.meyvc-drawer-add-per-cat', function () {
		var $container = $('#meyvc-drawer-per-category-discount');
		$container.find('.meyvc-drawer-add-per-cat').remove();
		$container.append(buildPerCategoryDiscountRow('', ''));
		$container.append('<button type="button" class="button meyvc-drawer-add-per-cat">' + (i18n.addCategoryDiscount || 'Add category discount') + '</button>');
		$(document).trigger('meyvc-select-woo-init');
		setTimeout(updateDrawerSectionMaxHeights, 80);
	});

	$('#meyvc-drawer-per-category-discount').on('click', '.meyvc-drawer-remove-per-cat', function () {
		$(this).closest('.meyvc-drawer-per-cat-row').remove();
		setTimeout(updateDrawerSectionMaxHeights, 50);
	});

	function getEmptyOffer(slotIndex) {
		return {
			headline: '',
			description: '',
			min_cart_total: 0,
			max_cart_total: 0,
			min_items: 0,
			first_time_customer: false,
			returning_customer_min_orders: 0,
			lifetime_spend_min: 0,
			allowed_roles: [],
			excluded_roles: [],
			reward_type: 'percent',
			reward_amount: 10,
			coupon_ttl_hours: 48,
			priority: 10 + (slotIndex >= 0 ? slotIndex : 0),
			enabled: false,
			individual_use: false,
			rate_limit_hours: 6,
			max_coupons_per_visitor: 1,
			exclude_sale_items: false,
			include_categories: [],
			exclude_categories: [],
			include_products: [],
			exclude_products: [],
			cart_contains_category: [],
			min_qty_for_category: {},
			apply_to_categories: [],
			apply_to_products: [],
			per_category_discount: {},
			conflict_offer_ids: []
		};
	}

	function rebuildConflictOfferSelect(excludeId) {
		var $sel = $('#meyvc-drawer-conflict-offers');
		if (!$sel.length) {
			return;
		}
		var choices = Array.isArray(window.meyvcOfferConflictChoices) ? window.meyvcOfferConflictChoices : [];
		var ex = excludeId != null && excludeId !== '' ? String(excludeId) : '';
		$sel.empty();
		choices.forEach(function (c) {
			if (!c || c.id == null) {
				return;
			}
			if (ex && String(c.id) === ex) {
				return;
			}
			$sel.append($('<option/>', { value: String(c.id), text: c.name || String(c.id) }));
		});
	}

	function populateForm(offer) {
		offer = offer || getEmptyOffer(0);
		var excludeConflict = (offer.id != null && String(offer.id) !== '') ? String(offer.id) : '';
		rebuildConflictOfferSelect(excludeConflict);
		$('#meyvc-drawer-headline').val(offer.headline || '');
		$('#meyvc-drawer-description').val(offer.description || '');
		$('#meyvc-drawer-enabled').prop('checked', !!offer.enabled);
		$('#meyvc-drawer-priority').val(offer.priority !== undefined ? offer.priority : 10);
		$('#meyvc-drawer-min-cart-total').val(offer.min_cart_total !== undefined ? offer.min_cart_total : 0);
		$('#meyvc-drawer-max-cart-total').val(offer.max_cart_total > 0 ? offer.max_cart_total : '');
		$('#meyvc-drawer-min-items').val(offer.min_items !== undefined ? offer.min_items : 0);
		$('#meyvc-drawer-first-time').prop('checked', !!offer.first_time_customer);
		var returningMin = offer.returning_customer_min_orders !== undefined ? parseInt(offer.returning_customer_min_orders, 10) : 0;
		$('#meyvc-drawer-returning-toggle').prop('checked', returningMin > 0);
		$('#meyvc-drawer-returning-min-orders').val(returningMin > 0 ? returningMin : 0);
		$('#meyvc-drawer-lifetime-spend').val(offer.lifetime_spend_min !== undefined ? offer.lifetime_spend_min : 0);
		$('#meyvc-drawer-allowed-roles').val(Array.isArray(offer.allowed_roles) ? offer.allowed_roles : []);
		$('#meyvc-drawer-excluded-roles').val(Array.isArray(offer.excluded_roles) ? offer.excluded_roles : []);
		$('#meyvc-drawer-reward-type').val(offer.reward_type || 'percent');
		$('#meyvc-drawer-reward-amount').val(offer.reward_amount !== undefined ? offer.reward_amount : 10);
		$('#meyvc-drawer-coupon-ttl').val(offer.coupon_ttl_hours > 0 ? offer.coupon_ttl_hours : 48);
		$('#meyvc-drawer-individual-use').prop('checked', !!offer.individual_use);
		$('#meyvc-drawer-rate-limit-hours').val(offer.rate_limit_hours !== undefined ? offer.rate_limit_hours : 6);
		$('#meyvc-drawer-max-coupons-per-visitor').val(offer.max_coupons_per_visitor !== undefined ? offer.max_coupons_per_visitor : 1);
		$('#meyvc-drawer-exclude-sale-items').prop('checked', !!offer.exclude_sale_items);
		$('#meyvc-drawer-include-categories').val(Array.isArray(offer.include_categories) ? offer.include_categories.map(String) : []);
		$('#meyvc-drawer-exclude-categories').val(Array.isArray(offer.exclude_categories) ? offer.exclude_categories.map(String) : []);
		$('#meyvc-drawer-include-products').val(Array.isArray(offer.include_products) ? offer.include_products.map(String) : []);
		$('#meyvc-drawer-exclude-products').val(Array.isArray(offer.exclude_products) ? offer.exclude_products.map(String) : []);
		$('#meyvc-drawer-cart-contains-category').val(Array.isArray(offer.cart_contains_category) ? offer.cart_contains_category.map(String) : []);
		var mqfc = offer.min_qty_for_category;
		if (mqfc && typeof mqfc === 'object' && !Array.isArray(mqfc)) {
			$('#meyvc-drawer-min-qty-for-category').val(Object.keys(mqfc).map(function (k) { return k + ':' + mqfc[k]; }).join('\n'));
		} else {
			$('#meyvc-drawer-min-qty-for-category').val('');
		}
		$('#meyvc-drawer-apply-to-categories').val(Array.isArray(offer.apply_to_categories) ? offer.apply_to_categories.map(String) : []);
		$('#meyvc-drawer-apply-to-products').val(Array.isArray(offer.apply_to_products) ? offer.apply_to_products.map(String) : []);
		buildPerCategoryDiscountList(offer.per_category_discount || {});
		var confIds = Array.isArray(offer.conflict_offer_ids) ? offer.conflict_offer_ids.map(String) : [];
		$('#meyvc-drawer-conflict-offers').val(confIds);
		// Sync SelectWoo (if present) after setting values
		$('#meyvc-offer-drawer-panel .meyvc-selectwoo').trigger('change');
		$(document).trigger('meyvc-select-woo-init');
		updateReturningVisibility();
	}

	function updateReturningVisibility() {
		var on = $('#meyvc-drawer-returning-toggle').prop('checked');
		var $wrap = $('#meyvc-drawer-returning-min-wrap');
		if (on) {
			$wrap.show();
		} else {
			$wrap.hide();
			$('#meyvc-drawer-returning-min-orders').val(0);
		}
	}

	function formatMoney(amount) {
		var cur = (window.meyvcAdmin && window.meyvcAdmin.currency) ? window.meyvcAdmin.currency : '';
		var n = parseFloat(amount, 10);
		if (isNaN(n)) return '0' + cur;
		return (Math.round(n * 100) / 100).toFixed(2) + cur;
	}

	function buildOfferSummary() {
		var bullet = i18n.summaryBullet || ' • ';
		var arrow = i18n.summaryArrow || ' → ';
		var headline = ($('#meyvc-drawer-headline').val() || '').trim();
		var titleStr = headline ? headline : (i18n.newOffer || 'New offer');
		var parts = [];
		var minCart = parseFloat($('#meyvc-drawer-min-cart-total').val(), 10) || 0;
		var maxCart = parseFloat($('#meyvc-drawer-max-cart-total').val(), 10) || 0;
		var minItems = parseInt($('#meyvc-drawer-min-items').val(), 10) || 0;
		var excludeSale = $('#meyvc-drawer-exclude-sale-items').prop('checked');
		if (minCart > 0 && maxCart > 0) {
			parts.push((i18n.summaryCartRange || 'Cart %s – %s').replace('%s', formatMoney(minCart)).replace('%s', formatMoney(maxCart)));
		} else if (minCart > 0) {
			parts.push((i18n.summaryCartMin || 'Cart ≥ %s').replace('%s', formatMoney(minCart)));
		}
		if (minItems > 0) {
			parts.push((i18n.summaryItems || '%d items').replace('%d', String(minItems)));
		}
		if (excludeSale) {
			parts.push(i18n.summaryExcludeSale || 'Exclude sale items');
		}
		if ($('#meyvc-drawer-first-time').prop('checked')) {
			parts.push(i18n.summaryFirstTime || 'First-time customer');
		}
		if ($('#meyvc-drawer-returning-toggle').prop('checked')) {
			var minOrd = parseInt($('#meyvc-drawer-returning-min-orders').val(), 10) || 1;
			parts.push((i18n.summaryReturning || 'Returning customer (≥%d orders)').replace('%d', String(minOrd)));
		}
		var lifetime = parseFloat($('#meyvc-drawer-lifetime-spend').val(), 10) || 0;
		if (lifetime > 0) {
			parts.push((i18n.summaryLifetime || 'Lifetime spend ≥ %s').replace('%s', formatMoney(lifetime)));
		}
		var ruleStr = parts.length ? parts.join(bullet) : (i18n.summaryCartMin || 'Cart ≥ %s').replace('%s', formatMoney(0));
		var rewardType = $('#meyvc-drawer-reward-type').val();
		var rewardAmount = parseFloat($('#meyvc-drawer-reward-amount').val(), 10) || 0;
		var rewardStr;
		if (rewardType === 'free_shipping') {
			rewardStr = i18n.summaryRewardShip || 'Free shipping';
		} else if (rewardType === 'percent') {
			rewardStr = (i18n.summaryRewardPct || '%s% off').replace('%s', String(rewardAmount));
		} else {
			rewardStr = (i18n.summaryRewardFix || '%s off').replace('%s', formatMoney(rewardAmount));
		}
		var ttl = parseInt($('#meyvc-drawer-coupon-ttl').val(), 10) || 48;
		var expiresStr = (i18n.summaryExpires || 'Expires %sh').replace('%s', String(ttl));
		return titleStr + bullet + ruleStr + arrow + rewardStr + bullet + expiresStr;
	}

	function updateOfferSummary() {
		var $el = $('#meyvc-drawer-offer-summary');
		var $bar = $('#meyvc-offer-drawer-summary-bar');
		if (!$el.length) return;
		$el.text(buildOfferSummary());
		if ($bar.length) {
			$bar.addClass('meyvc-offer-drawer-summary-bar--updated');
			clearTimeout(updateOfferSummary._flashTimer);
			updateOfferSummary._flashTimer = setTimeout(function () {
				$bar.removeClass('meyvc-offer-drawer-summary-bar--updated');
			}, 400);
		}
	}

	function toggleRewardAmountVisibility(rewardType) {
		if (rewardType === 'free_shipping') {
			rewardAmountWrap.hide();
		} else {
			rewardAmountWrap.show();
			rewardSuffix.text(rewardType === 'percent' ? '%' : (window.meyvcAdmin && window.meyvcAdmin.currency ? window.meyvcAdmin.currency : ''));
		}
	}

	function clearInlineErrors() {
		form.find('.meyvc-field').removeClass('has-error');
		form.find('.meyvc-offer-drawer-field-error').remove();
	}

		function showInlineError(fieldSelector, message, noFocus) {
			var $field = form.find(fieldSelector).first();
			var $wrap = $field.closest('.meyvc-field');
			if (!$wrap.length) return;
			$wrap.addClass('has-error');
			var $err = $wrap.find('.meyvc-offer-drawer-field-error');
			if (!$err.length) $err = $('<span class="meyvc-offer-drawer-field-error" role="alert"></span>').appendTo($wrap);
			$err.text(message).show();
			if (!noFocus) $field.focus();
		}

		// Map schema error keys to drawer field selectors for server validation errors.
		var offerErrorKeyToSelector = {
			headline: '#meyvc-drawer-headline',
			priority: '#meyvc-drawer-priority',
			reward_type: '#meyvc-drawer-reward-type',
			reward_amount: '#meyvc-drawer-reward-amount',
			coupon_ttl_hours: '#meyvc-drawer-coupon-ttl',
			rate_limit_hours: '#meyvc-drawer-rate-limit-hours',
			max_coupons_per_visitor: '#meyvc-drawer-max-coupons-per-visitor',
			conflict_offer_ids: '#meyvc-drawer-conflict-offers'
		};

		function showFieldErrors(errors) {
			if (!errors || typeof errors !== 'object') return;
			clearInlineErrors();
			var firstSelector = null;
			Object.keys(errors).forEach(function (key) {
				var selector = offerErrorKeyToSelector[key] || ('#meyvc-drawer-' + key.replace(/_/g, '-'));
				showInlineError(selector, errors[key], true);
				if (!firstSelector) firstSelector = selector;
			});
			if (firstSelector) form.find(firstSelector).focus();
		}

	function validateForm() {
		clearInlineErrors();
		var headline = ($('#meyvc-drawer-headline').val() || '').trim();
		if (!headline) {
			showInlineError('#meyvc-drawer-headline', i18n.nameRequired || 'Offer name is required.');
			return false;
		}
		var priority = parseInt($('#meyvc-drawer-priority').val(), 10);
		if (isNaN(priority)) {
			showInlineError('#meyvc-drawer-priority', i18n.priorityInteger || 'Priority must be a number.');
			return false;
		}
		var rewardType = $('#meyvc-drawer-reward-type').val();
		var rewardAmount = parseFloat($('#meyvc-drawer-reward-amount').val(), 10);
		if (rewardType === 'percent' && (isNaN(rewardAmount) || rewardAmount < 1 || rewardAmount > 100)) {
			showInlineError('#meyvc-drawer-reward-amount', i18n.percent1To100 || 'Percent discount must be between 1 and 100.');
			return false;
		}
		if (rewardType === 'fixed' && (isNaN(rewardAmount) || rewardAmount < 0)) {
			showInlineError('#meyvc-drawer-reward-amount', i18n.fixedMinZero || 'Fixed discount must be 0 or greater.');
			return false;
		}
		var ttl = parseInt($('#meyvc-drawer-coupon-ttl').val(), 10);
		if (isNaN(ttl) || ttl < 1) {
			showInlineError('#meyvc-drawer-coupon-ttl', i18n.ttlMin1 || 'Coupon TTL must be at least 1 hour.');
			return false;
		}
		return true;
	}

	function showToast(message) {
		var $container = $('#meyvc-ui-toast-container');
		if ($container.length) {
			var $item = $('<div class="meyvc-ui-toast" role="status"></div>').text(message);
			$container.append($item);
			setTimeout(function () {
				$item.fadeOut(200, function () { $item.remove(); });
			}, 4000);
			return;
		}
		var $fallback = $('#meyvc-offers-toast');
		if (!$fallback.length) {
			$fallback = $('<div id="meyvc-offers-toast" class="meyvc-offers-toast" role="status" aria-live="polite"></div>').appendTo('.meyvc-offers-page');
		}
		var $item = $('<div class="meyvc-offers-toast-item" role="status"></div>').text(message);
		$fallback.append($item);
		setTimeout(function () {
			$item.addClass('meyvc-offers-toast-out');
			setTimeout(function () { $item.remove(); }, 220);
		}, 4000);
	}

	function showNotice(message, type) {
		if (type === 'success') {
			showToast(message);
			return;
		}
		var notice = $('<div class="notice notice-' + (type || 'error') + ' is-dismissible"><p></p></div>');
		notice.find('p').text(message);
		$('.meyvc-offers-page .meyvc-offers-header').first().after(notice);
		setTimeout(function () {
			notice.fadeOut(function () { notice.remove(); });
		}, 4000);
	}

	function renderCard(item) {
		var o = item.offer;
		var statusClass = o.enabled ? 'active' : 'inactive';
		var statusText = o.enabled ? (i18n.active || 'Active') : (i18n.inactive || 'Inactive');
		var priorityText = (i18n.priorityLabel || 'Priority: %s').replace('%s', o.priority);
		var offerId = (o.id != null && o.id !== '') ? String(o.id) : '';
		var moveUpIcon = (i18n.moveUpIcon != null && i18n.moveUpIcon !== '') ? i18n.moveUpIcon : '↑';
		var moveDownIcon = (i18n.moveDownIcon != null && i18n.moveDownIcon !== '') ? i18n.moveDownIcon : '↓';
		var editIcon = (i18n.editIcon != null && i18n.editIcon !== '') ? i18n.editIcon : '';
		var duplicateIcon = (i18n.duplicateIcon != null && i18n.duplicateIcon !== '') ? i18n.duplicateIcon : '';
		var deleteIcon = (i18n.deleteIcon != null && i18n.deleteIcon !== '') ? i18n.deleteIcon : '';
		var editBtn = '<button type="button" class="button button-small meyvc-offer-card-edit" data-meyvc-offer-index="' + item.index + '">' + editIcon + ' ' + (i18n.edit || 'Edit') + '</button>';
		var confN = Array.isArray(o.conflict_offer_ids) ? o.conflict_offer_ids.length : 0;
		var conflictsLabel = i18n.conflictsLabel || 'Conflicts';
		var confBlock = '';
		if (confN > 0) {
			var badgeText = (i18n.conflictsBadge || '%d conflicts').replace('%d', String(confN));
			confBlock = '<div class="meyvc-offer-card-conflicts-row"><span class="meyvc-offer-card-conflicts-label">' + escapeHtml(conflictsLabel) + '</span> <span class="meyvc-offer-conflict-badge">' + escapeHtml(badgeText) + '</span></div>';
		} else {
			confBlock = '<div class="meyvc-offer-card-conflicts-row"><span class="meyvc-offer-card-conflicts-label">' + escapeHtml(conflictsLabel) + '</span> <span class="meyvc-offer-conflict-badge meyvc-offer-conflict-badge--none" aria-hidden="true">—</span></div>';
		}
		var moveUpDown = '<span class="meyvc-offer-card-move-btns">' +
			'<button type="button" class="button button-small meyvc-offer-move-up" data-meyvc-offer-index="' + item.index + '" title="' + escapeHtml(i18n.moveUp || 'Move up') + '" aria-label="' + escapeHtml(i18n.moveUp || 'Move up') + '">' + moveUpIcon + '</button>' +
			'<button type="button" class="button button-small meyvc-offer-move-down" data-meyvc-offer-index="' + item.index + '" title="' + escapeHtml(i18n.moveDown || 'Move down') + '" aria-label="' + escapeHtml(i18n.moveDown || 'Move down') + '">' + moveDownIcon + '</button></span>';
		var duplicateBtn = usedCount < maxOffers ? '<button type="button" class="button button-small meyvc-offer-card-duplicate" data-meyvc-offer-id="' + escapeHtml(offerId) + '" data-meyvc-offer-index="' + item.index + '">' + duplicateIcon + ' ' + (i18n.duplicate || 'Duplicate') + '</button>' : '';
		var deleteBtn = '<button type="button" class="button button-small meyvc-offer-card-delete" data-meyvc-offer-id="' + escapeHtml(offerId) + '" data-meyvc-offer-index="' + item.index + '">' + deleteIcon + ' ' + (i18n.delete || 'Delete') + '</button>';
		var toggleForm = '<form method="post" class="meyvc-offer-card-toggle-form">' +
			'<input type="hidden" name="meyvc_offers_nonce" value="' + (window.meyvcOffersNonce || '') + '">' +
			'<input type="hidden" name="meyvc_toggle_offer" value="1">' +
			'<input type="hidden" name="meyvc_offer_index" value="' + item.index + '">' +
			'<label class="meyvc-offer-card-toggle">' +
			'<input type="checkbox" ' + (o.enabled ? 'checked' : '') + ' onchange="this.form.submit()">' +
			'<span class="meyvc-offer-card-toggle-slider"></span></label></form>';
		return '<div class="meyvc-offer-card" data-offer-index="' + item.index + '" data-offer-id="' + escapeHtml(offerId) + '" data-priority="' + (o.priority || 10) + '" draggable="true">' +
			'<div class="meyvc-offer-card-main">' +
			'<div class="meyvc-offer-card-head">' +
			'<span class="meyvc-offer-card-drag-handle" title="' + escapeHtml(i18n.dragToReorder || 'Drag to reorder') + '" aria-hidden="true"></span>' +
			'<h3 class="meyvc-offer-card-name">' + escapeHtml(o.headline) + '</h3>' +
			'<span class="meyvc-offer-card-status meyvc-offer-card-status--' + statusClass + '">' + statusText + '</span>' +
			'</div>' +
			'<p class="meyvc-offer-card-rule">' + escapeHtml(item.rule_summary) + '</p>' +
			'<p class="meyvc-offer-card-reward">' + escapeHtml(item.reward_summary) + '</p>' +
			'<p class="meyvc-offer-card-priority">' + escapeHtml(priorityText) + '</p>' +
			confBlock +
			'</div>' +
			'<div class="meyvc-offer-card-actions">' +
			moveUpDown + toggleForm + editBtn + duplicateBtn + deleteBtn +
			'</div></div>';
	}

	function escapeHtml(text) {
		if (text == null) return '';
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	function submitOffer() {
		if (!validateForm()) return;
		var $saveBtn = form.find('.meyvc-offer-drawer-save');
		var originalText = $saveBtn.text();
		$saveBtn.prop('disabled', true).text(i18n.saving || 'Saving...');

		var formData = form.serialize();
		var ajaxUrl = (window.meyvcAdmin && window.meyvcAdmin.ajaxUrl) ? window.meyvcAdmin.ajaxUrl : (window.ajaxurl || '/wp-admin/admin-ajax.php');

		$.post(ajaxUrl, formData + '&action=meyvc_save_offer')
			.done(function (response) {
				if (response && response.success && response.data) {
					updatePageFromResponse(response.data);
					closeDrawer();
					showNotice(i18n.saved || 'Offer saved.', 'success');
				} else {
					var data = (response && response.data) ? response.data : {};
					if (data.offers !== undefined || data.offers_used_count !== undefined) {
						updatePageFromResponse(data);
					}
					if (data.errors && typeof data.errors === 'object') {
						showFieldErrors(data.errors);
					}
					var msg = data.message || (i18n.error || 'Error occurred');
					showNotice(msg, 'error');
				}
			})
			.fail(function (xhr) {
				var data = (xhr.responseJSON && xhr.responseJSON.data) ? xhr.responseJSON.data : {};
				if (data.offers !== undefined || data.offers_used_count !== undefined) {
					updatePageFromResponse(data);
				}
				if (data.errors && typeof data.errors === 'object') {
					showFieldErrors(data.errors);
				}
				var msg = data.message || (i18n.error || 'Error occurred');
				showNotice(msg, 'error');
			})
			.always(function () {
				$saveBtn.prop('disabled', false).text(originalText);
			});
	}

	function syncConflictChoicesFromOffersData() {
		var choices = [];
		(offersData || []).forEach(function (slot) {
			if (!slot || !String(slot.headline || '').trim()) {
				return;
			}
			if (slot.id) {
				choices.push({ id: String(slot.id), name: String(slot.headline) });
			}
		});
		window.meyvcOfferConflictChoices = choices;
	}

	function toggleCheckConflictsButton() {
		var container = $('.meyvc-offers-page');
		var $bar = container.find('.meyvc-offers-toolbar');
		if (!$bar.length) {
			return;
		}
		if (usedCount > 0) {
			if (!$bar.find('#meyvc-offers-check-conflicts').length) {
				var $btn = $('<button type="button" id="meyvc-offers-check-conflicts" class="button button-secondary meyvc-offers-check-conflicts"></button>');
				$btn.text(i18n.checkConflicts || 'Check for conflicts');
				$bar.append($btn);
			}
		} else {
			$bar.find('#meyvc-offers-check-conflicts').remove();
		}
	}

	// When response has offers, we need to show empty state if 0 or grid if 1+. Use server-returned count/max, not cached DOM state.
	function updatePageFromResponse(data) {
		if (data.max_offers !== undefined) {
			maxOffers = parseInt(data.max_offers, 10) || maxOffers;
			window.meyvcOffersMaxOffers = maxOffers;
		}
		usedCount = data.offers_used_count !== undefined ? parseInt(data.offers_used_count, 10) : usedCount;
		window.meyvcOffersUsedCount = usedCount;

		// Update offersData for edit drawer: merge returned offers into slots; clear deleted slot if index provided.
		if (data.index !== undefined) {
			offersData[data.index] = {};
		}
		if (Array.isArray(data.offers)) {
			data.offers.forEach(function (item) {
				offersData[item.index] = item.offer;
			});
		}
		syncConflictChoicesFromOffersData();

		var container = $('.meyvc-offers-page');
		var countEl = container.find('.meyvc-offers-count');
		countEl.text(usedCount + '/' + maxOffers + ' ' + (i18n.offersUsed || 'offers used'));

		if (usedCount >= maxOffers) {
			container.find('.meyvc-offers-add-btn').prop('disabled', true).removeAttr('data-meyvc-drawer');
			if (!container.find('.meyvc-offers-limit-note').length) {
				container.find('.meyvc-offers-header-actions').prepend('<span class="meyvc-offers-limit-note">' + (i18n.limitReached || 'Offer limit reached (5).') + '</span>');
			}
		} else {
			container.find('.meyvc-offers-add-btn').prop('disabled', false).attr('data-meyvc-drawer', 'add');
			container.find('.meyvc-offers-limit-note').remove();
		}

		if (!Array.isArray(data.offers) || data.offers.length === 0) {
			// Show empty state, hide grid.
			var gridParent = container.find('.meyvc-offers-grid').parent();
			if (gridParent.length && container.find('.meyvc-offers-empty').length === 0) {
				gridParent.html(
					'<div class="meyvc-offers-empty">' +
					'<div class="meyvc-offers-empty-illustration" aria-hidden="true"></div>' +
					'<p class="meyvc-offers-empty-title">' + (i18n.noOffersYet || 'No offers yet') + '</p>' +
					'<p class="meyvc-offers-empty-desc">' + (i18n.emptyDesc || 'Create your first offer to show a dynamic reward on cart and checkout.') + '</p>' +
					'<button type="button" class="button button-primary meyvc-offers-empty-cta" data-meyvc-drawer="add">' + (i18n.createFirst || 'Create your first offer') + '</button>' +
					'</div>'
				);
			}
			toggleCheckConflictsButton();
			return;
		}

		// Replace grid content with new cards.
		var grid = container.find('.meyvc-offers-grid');
		if (grid.length) {
			grid.html(data.offers.map(renderCard).join(''));
		} else {
			// Currently showing empty state; replace with grid (new node needs listeners).
			container.find('.meyvc-offers-empty').replaceWith(
				'<div class="meyvc-offers-grid">' + data.offers.map(renderCard).join('') + '</div>'
			);
			initOfferReorder();
		}
		toggleCheckConflictsButton();
	}

	$(function () {
		syncConflictChoicesFromOffersData();

		$(document).on('click', '#meyvc-offers-check-conflicts', function () {
			var $btn = $(this);
			var ajaxUrl = (window.meyvcAdmin && window.meyvcAdmin.ajaxUrl) ? window.meyvcAdmin.ajaxUrl : (window.ajaxurl || '/wp-admin/admin-ajax.php');
			var nonce = i18n.reorderNonce || '';
			var prevText = $btn.text();
			$btn.prop('disabled', true).text(i18n.checkConflictsRunning || 'Checking…');
			$.post(ajaxUrl, { action: 'meyvc_detect_offer_conflicts', nonce: nonce })
				.done(function (res) {
					var $holder = $('#meyvc-offers-conflict-notices');
					$holder.empty();
					if (res && res.success && res.data) {
						var w = res.data.warnings;
						if (!w || !w.length) {
							var $ok = $('<div class="notice notice-success is-dismissible"></div>');
							$ok.append($('<p></p>').text(i18n.noConflictCycles || 'No circular conflict chains found.'));
							var $d0 = $('<button type="button" class="notice-dismiss"></button>');
							$d0.append($('<span class="screen-reader-text"></span>').text(i18n.dismiss || 'Dismiss'));
							$ok.append($d0);
							$d0.on('click', function () { $ok.remove(); });
							$holder.append($ok);
							return;
						}
						w.forEach(function (msg) {
							var $n = $('<div class="notice notice-warning is-dismissible"></div>');
							$n.append($('<p></p>').text(msg));
							var $d = $('<button type="button" class="notice-dismiss"></button>');
							$d.append($('<span class="screen-reader-text"></span>').text(i18n.dismiss || 'Dismiss'));
							$n.append($d);
							$d.on('click', function () { $n.remove(); });
							$holder.append($n);
						});
					}
				})
				.fail(function () {
					showNotice(i18n.checkConflictsFail || 'Could not check conflicts.', 'error');
				})
				.always(function () {
					$btn.prop('disabled', false).text(prevText);
				});
		});

		$(document).on('click', '[data-meyvc-drawer="add"]', function () {
			openDrawer('add', undefined, $(this));
		});

		$(document).on('click', '.meyvc-offer-card-edit', function () {
			var index = parseInt($(this).data('meyvc-offer-index'), 10);
			openDrawer('edit', index, $(this));
		});

		drawer.find('.meyvc-offer-drawer-close, .meyvc-offer-drawer-backdrop, .meyvc-offer-drawer-cancel').on('click', closeDrawer);

		// Accordion: collapse/expand drawer sections
		$(document).on('click', '#meyvc-offer-drawer .meyvc-offer-drawer-section__header', function () {
			var $btn = $(this);
			var $section = $btn.closest('.meyvc-offer-drawer-section');
			var $body = $section.find('.meyvc-offer-drawer-section__body').first();
			var isCollapsed = $section.hasClass('is-collapsed');
			if (isCollapsed) {
				$section.removeClass('is-collapsed');
				$body.css('max-height', $body[0].scrollHeight + 'px');
				$btn.attr('aria-expanded', 'true');
			} else {
				$section.addClass('is-collapsed');
				$body.css('max-height', '');
				$btn.attr('aria-expanded', 'false');
			}
		});

		rewardTypeSelect.on('change', function () {
			toggleRewardAmountVisibility($(this).val());
			updateOfferSummary();
		});

		$('#meyvc-drawer-returning-toggle').on('change', function () {
			updateReturningVisibility();
			updateOfferSummary();
		});

		form.on('input keyup', '#meyvc-drawer-headline', updateOfferSummary);
		form.on('input change keyup', '#meyvc-drawer-min-cart-total, #meyvc-drawer-max-cart-total, #meyvc-drawer-min-items, #meyvc-drawer-returning-min-orders, #meyvc-drawer-lifetime-spend, #meyvc-drawer-reward-amount, #meyvc-drawer-coupon-ttl, #meyvc-drawer-priority, #meyvc-drawer-rate-limit-hours, #meyvc-drawer-max-coupons-per-visitor', updateOfferSummary);
		form.on('change', '#meyvc-drawer-exclude-sale-items, #meyvc-drawer-first-time, #meyvc-drawer-reward-type, #meyvc-drawer-enabled', updateOfferSummary);
		$(document).on('change select2:select select2:unselect', '#meyvc-offer-drawer .meyvc-selectwoo', updateOfferSummary);

		form.on('submit', function (e) {
			e.preventDefault();
			submitOffer();
		});

		// Close on Escape (focus returns to trigger via closeDrawer)
		$(document).on('keydown', function (e) {
			if (e.key === 'Escape' && drawer.hasClass('is-open')) {
				e.preventDefault();
				closeDrawer();
			}
		});

		// Delete offer via AJAX (with confirm)
		$(document).on('click', '.meyvc-offer-card-delete', function () {
			var id = $(this).data('meyvc-offer-id');
			if (!id) return;
			var name = $(this).closest('.meyvc-offer-card').find('.meyvc-offer-card-name').first().text().trim() || (i18n.offer || 'Offer');
			var msg = (i18n.deleteConfirmName || 'Delete offer "%s"?').replace('%s', name);
			if (!confirm(msg)) return;
			var $btn = $(this);
			$btn.prop('disabled', true);
			var ajaxUrl = (window.meyvcAdmin && window.meyvcAdmin.ajaxUrl) ? window.meyvcAdmin.ajaxUrl : (window.ajaxurl || '/wp-admin/admin-ajax.php');
			$.post(ajaxUrl, {
				action: 'meyvc_offer_delete',
				nonce: (window.meyvcOffersI18n && window.meyvcOffersI18n.reorderNonce) || '',
				id: id
			}).done(function (response) {
				if (response && response.success && response.data) {
					updatePageFromResponse(response.data);
					initOfferReorder();
					showNotice(i18n.deletedNotice || 'Offer deleted.', 'success');
				} else {
					showNotice((response && response.data && response.data.message) ? response.data.message : (i18n.error || 'Error occurred'), 'error');
				}
			}).fail(function (xhr) {
				var data = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
				showNotice(data.message || (i18n.error || 'Error occurred'), 'error');
			}).always(function () {
				$btn.prop('disabled', false);
			});
		});

		// Duplicate offer via AJAX (max 5 enforced server-side)
		$(document).on('click', '.meyvc-offer-card-duplicate', function () {
			var id = $(this).data('meyvc-offer-id');
			if (!id) return;
			var $btn = $(this);
			$btn.prop('disabled', true);
			var ajaxUrl = (window.meyvcAdmin && window.meyvcAdmin.ajaxUrl) ? window.meyvcAdmin.ajaxUrl : (window.ajaxurl || '/wp-admin/admin-ajax.php');
			$.post(ajaxUrl, {
				action: 'meyvc_offer_duplicate',
				nonce: (window.meyvcOffersI18n && window.meyvcOffersI18n.reorderNonce) || '',
				id: id
			}).done(function (response) {
				if (response && response.success && response.data) {
					updatePageFromResponse(response.data);
					initOfferReorder();
					showNotice(i18n.duplicatedNotice || 'Offer duplicated.', 'success');
				} else {
					var msg = (response && response.data && response.data.message) ? response.data.message : (i18n.reorderError || 'Could not save order.');
					showNotice(msg, 'error');
				}
			}).fail(function (xhr) {
				var data = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
				showNotice(data.message || (i18n.reorderError || 'Could not duplicate.'), 'error');
			}).always(function () {
				$btn.prop('disabled', false);
			});
		});

		// Move Up / Move Down: reorder and save via same endpoint
		$(document).on('click', '.meyvc-offer-move-up', function () {
			var grid = document.querySelector('.meyvc-offers-grid');
			if (!grid) return;
			var cards = grid.querySelectorAll('.meyvc-offer-card');
			var idx = parseInt($(this).data('meyvc-offer-index'), 10);
			var order = [];
			for (var i = 0; i < cards.length; i++) {
				order.push(parseInt(cards[i].getAttribute('data-offer-index'), 10));
			}
			var pos = order.indexOf(idx);
			if (pos <= 0) return;
			order.splice(pos, 1);
			order.splice(pos - 1, 0, idx);
			if (window.meyvcSaveReorderWithOrder) window.meyvcSaveReorderWithOrder(grid, order);
		});
		$(document).on('click', '.meyvc-offer-move-down', function () {
			var grid = document.querySelector('.meyvc-offers-grid');
			if (!grid) return;
			var cards = grid.querySelectorAll('.meyvc-offer-card');
			var idx = parseInt($(this).data('meyvc-offer-index'), 10);
			var order = [];
			for (var i = 0; i < cards.length; i++) {
				order.push(parseInt(cards[i].getAttribute('data-offer-index'), 10));
			}
			var pos = order.indexOf(idx);
			if (pos < 0 || pos >= order.length - 1) return;
			order.splice(pos, 1);
			order.splice(pos + 1, 0, idx);
			if (window.meyvcSaveReorderWithOrder) window.meyvcSaveReorderWithOrder(grid, order);
		});

		// Test Offer: Run Test button
		$('#meyvc-offer-test-run').on('click', function () {
			var $btn = $(this);
			var cartTotal = parseFloat($('#meyvc-test-cart-total').val(), 10) || 0;
			var itemsCount = parseInt($('#meyvc-test-items-count').val(), 10) || 0;
			var isLoggedIn = $('#meyvc-test-is-logged-in').val() === '1';
			var orderCount = parseInt($('#meyvc-test-order-count').val(), 10) || 0;
			var lifetimeSpend = parseFloat($('#meyvc-test-lifetime-spend').val(), 10) || 0;
			var userRole = ($('#meyvc-test-user-role').val() || '').trim();

			var payload = {
				action: 'meyvc_offer_test',
				nonce: (window.meyvcOffersI18n && window.meyvcOffersI18n.reorderNonce) || '',
				cart_total: cartTotal,
				cart_items_count: itemsCount,
				is_logged_in: isLoggedIn ? '1' : '0',
				order_count: orderCount,
				lifetime_spend: lifetimeSpend,
				user_role: userRole
			};

			var ajaxUrl = (window.meyvcAdmin && window.meyvcAdmin.ajaxUrl) ? window.meyvcAdmin.ajaxUrl : (window.ajaxurl || '/wp-admin/admin-ajax.php');
			var $matchBlock = $('#meyvc-offer-test-output');
			var $noMatchBlock = $('#meyvc-offer-test-no-match');

			$btn.prop('disabled', true).text(i18n.runTestLabel || 'Running...');
			$matchBlock.hide();
			$noMatchBlock.hide();

			$.post(ajaxUrl, payload)
				.done(function (response) {
					if (!response || !response.success) {
						$noMatchBlock.addClass('notice notice-error').html('<p>' + escapeHtml((response && response.data && response.data.message) ? response.data.message : (i18n.error || 'Error occurred')) + '</p>').show();
						return;
					}
					var data = response.data || {};
					var match = data.match;
					var checks = Array.isArray(data.checks) ? data.checks : (Array.isArray(data.condition_results) ? data.condition_results : []);
					var message = data.message || '';
					var suggestions = Array.isArray(data.suggestions) ? data.suggestions : [];

					if (match) {
						var matchHtml = '<div class="meyvc-offer-test-match">';
						matchHtml += '<p class="meyvc-offer-test-match-title"><strong>' + escapeHtml(i18n.matchingOffer || 'Matching offer:') + '</strong></p>';
						matchHtml += '<ul class="meyvc-offer-test-match-list">';
						matchHtml += '<li><strong>' + escapeHtml(i18n.name || 'Name') + ':</strong> ' + escapeHtml(match.name || '-') + '</li>';
						if (match.rule_summary) matchHtml += '<li><strong>' + escapeHtml(i18n.rule || 'Rule') + ':</strong> ' + escapeHtml(match.rule_summary) + '</li>';
						if (match.reward_summary) matchHtml += '<li><strong>' + escapeHtml(i18n.reward || 'Reward') + ':</strong> ' + escapeHtml(match.reward_summary) + '</li>';
						matchHtml += '</ul></div>';
						$matchBlock.find('.meyvc-offer-test-result-match').html(matchHtml);

						var condHtml = '<p class="meyvc-offer-test-checks-title"><strong>' + escapeHtml(i18n.why || 'Checks:') + '</strong></p><ul class="meyvc-offer-test-checks">';
						checks.forEach(function (c) {
							var label = (c.label != null && c.label !== '') ? c.label : (c.key || '');
							var passed = c.passed === true;
							var iconClass = passed ? 'meyvc-offer-test-check--pass' : 'meyvc-offer-test-check--fail';
							var icon = passed ? checkIcon : crossIcon;
							var expected = (c.expected != null && c.expected !== '') ? String(c.expected) : '';
							var actual = (c.actual != null && c.actual !== '') ? String(c.actual) : '';
							var expl = '';
							if (!passed && (expected || actual)) {
								expl = ' <span class="meyvc-offer-test-check-detail">(' + (i18n.expectedLabel || 'Expected:') + ' ' + escapeHtml(expected) + (actual ? '; ' + (i18n.actualLabel || 'Actual:') + ' ' + escapeHtml(actual) : '') + ')</span>';
							}
							condHtml += '<li class="meyvc-offer-test-check ' + iconClass + '"><span class="meyvc-offer-test-check-icon" aria-hidden="true">' + icon + '</span> ' + escapeHtml(label) + expl + '</li>';
						});
						condHtml += '</ul>';
						$matchBlock.find('.meyvc-offer-test-result-conditions').html(condHtml);
						$matchBlock.show();
					} else {
						var noMatchHtml = '<p class="meyvc-offer-test-no-match-msg">' + escapeHtml(message || (i18n.noEligibleOffer || 'No eligible offer')) + '</p>';
						if (suggestions.length > 0) {
							noMatchHtml += '<p class="meyvc-offer-test-suggestions-title">' + escapeHtml(i18n.suggestionsLabel || 'Suggestions:') + '</p><ul class="meyvc-offer-test-suggestions">';
							suggestions.forEach(function (s) {
								noMatchHtml += '<li>' + escapeHtml(s) + '</li>';
							});
							noMatchHtml += '</ul>';
						}
						$noMatchBlock.removeClass('notice notice-error').addClass('notice notice-warning').html(noMatchHtml).show();
					}
				})
				.fail(function (xhr) {
					var errMsg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : (i18n.error || 'Error occurred');
					$noMatchBlock.addClass('notice notice-error').html('<p>' + escapeHtml(errMsg) + '</p>').show();
				})
				.always(function () {
					$btn.prop('disabled', false).text(i18n.runTest || 'Run Test');
				});
		});

		// Drag-and-drop reorder (vanilla JS, only from handle)
		initOfferReorder();
	});

	function initOfferReorder() {
		var grid = document.querySelector('.meyvc-offers-grid');
		if (!grid) return;

		var draggedCard = null;
		var priorityLabel = (window.meyvcOffersI18n && window.meyvcOffersI18n.priorityLabel) ? window.meyvcOffersI18n.priorityLabel : 'Priority: %s';

		function getCardIndex(card) {
			var idx = card && card.getAttribute && card.getAttribute('data-offer-index');
			return idx !== null && idx !== undefined ? parseInt(idx, 10) : -1;
		}

		function onlyHandle(e) {
			var t = e.target;
			while (t && t !== grid) {
				if (t.classList && t.classList.contains('meyvc-offer-card-drag-handle')) return true;
				t = t.parentNode;
			}
			return false;
		}

		grid.addEventListener('dragstart', function (e) {
			if (!onlyHandle(e)) { e.preventDefault(); return; }
			var card = e.target.closest && e.target.closest('.meyvc-offer-card');
			if (!card) return;
			draggedCard = card;
			e.dataTransfer.setData('text/plain', getCardIndex(card));
			e.dataTransfer.effectAllowed = 'move';
			card.classList.add('meyvc-offer-card-dragging');
		});

		grid.addEventListener('dragend', function (e) {
			if (draggedCard) draggedCard.classList.remove('meyvc-offer-card-dragging');
			draggedCard = null;
		});

		grid.addEventListener('dragover', function (e) {
			e.preventDefault();
			e.dataTransfer.dropEffect = 'move';
			var card = e.target.closest && e.target.closest('.meyvc-offer-card');
			if (card && card !== draggedCard) card.classList.add('meyvc-offer-card-drag-over');
		});

		grid.addEventListener('dragleave', function (e) {
			var card = e.target.closest && e.target.closest('.meyvc-offer-card');
			if (card) card.classList.remove('meyvc-offer-card-drag-over');
		});

		grid.addEventListener('drop', function (e) {
			e.preventDefault();
			var card = e.target.closest && e.target.closest('.meyvc-offer-card');
			if (!card || !draggedCard || card === draggedCard) {
				document.querySelectorAll('.meyvc-offer-card-drag-over').forEach(function (el) { el.classList.remove('meyvc-offer-card-drag-over'); });
				return;
			}
			card.classList.remove('meyvc-offer-card-drag-over');
			// Insert dragged card before the drop target
			grid.insertBefore(draggedCard, card);
			saveReorder(grid);
		});

		function saveReorder(container) {
			var cards = container.querySelectorAll('.meyvc-offer-card');
			var order = [];
			for (var i = 0; i < cards.length; i++) {
				var idx = getCardIndex(cards[i]);
				if (idx >= 0) order.push(idx);
			}
			if (order.length === 0) return;
			saveReorderWithOrder(container, order);
		}

		window.meyvcSaveReorderWithOrder = function (container, order) {
			if (!order || order.length === 0) return;
			var formData = new FormData();
			formData.append('action', 'meyvc_offer_reorder');
			formData.append('nonce', (window.meyvcOffersI18n && window.meyvcOffersI18n.reorderNonce) || '');
			order.forEach(function (idx) { formData.append('order[]', idx); });

			var xhr = new XMLHttpRequest();
			xhr.open('POST', (window.meyvcAdmin && window.meyvcAdmin.ajaxUrl) || window.ajaxurl || '/wp-admin/admin-ajax.php');
			xhr.onload = function () {
				var res;
				try { res = JSON.parse(xhr.responseText); } catch (err) { res = {}; }
				if (res.success && res.data) {
					if (res.data.offers && Array.isArray(res.data.offers) && res.data.offers.length > 0 && window.jQuery) {
						usedCount = res.data.offers_used_count !== undefined ? parseInt(res.data.offers_used_count, 10) : usedCount;
						var containerEl = document.querySelector('.meyvc-offers-page');
						if (containerEl) {
							var grid = containerEl.querySelector('.meyvc-offers-grid');
							if (grid) {
								grid.innerHTML = res.data.offers.map(renderCard).join('');
								initOfferReorder();
							}
						}
						var countEl = document.querySelector('.meyvc-offers-page .meyvc-offers-count');
						if (countEl) countEl.textContent = usedCount + '/' + maxOffers + ' ' + (i18n.offersUsed || 'offers used');
					} else if (res.data.priorities) {
						var priorities = res.data.priorities;
						container.querySelectorAll('.meyvc-offer-card').forEach(function (el) {
							var idx = getCardIndex(el);
							var p = priorities[idx];
							if (p !== undefined) {
								el.setAttribute('data-priority', p);
								var labelEl = el.querySelector('.meyvc-offer-card-priority');
								if (labelEl) labelEl.textContent = priorityLabel.replace('%s', String(p));
							}
						});
					}
					if (window.jQuery) {
						var msg = (window.meyvcOffersI18n && window.meyvcOffersI18n.reorderSaved) || 'Order saved.';
						window.jQuery('.meyvc-offers-page .meyvc-offers-header').first().after(
							'<div class="notice notice-success is-dismissible"><p>' + msg + '</p></div>'
						);
					}
				} else {
					var errMsg = (window.meyvcOffersI18n && window.meyvcOffersI18n.reorderError) || 'Could not save order.';
					if (window.jQuery) {
						window.jQuery('.meyvc-offers-page .meyvc-offers-header').first().after(
							'<div class="notice notice-error is-dismissible"><p>' + errMsg + '</p></div>'
						);
					}
				}
			};
			xhr.onerror = function () {
				var errMsg = (window.meyvcOffersI18n && window.meyvcOffersI18n.reorderError) || 'Could not save order.';
				if (window.jQuery) {
					window.jQuery('.meyvc-offers-page .meyvc-offers-header').first().after(
						'<div class="notice notice-error is-dismissible"><p>' + errMsg + '</p></div>'
					);
				}
			};
			xhr.send(formData);
		};

		function saveReorderWithOrder(container, order) {
			window.meyvcSaveReorderWithOrder(container, order);
		}
	}
})(jQuery);
