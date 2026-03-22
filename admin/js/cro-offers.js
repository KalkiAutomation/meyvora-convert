/**
 * CRO Offers admin – Offer Builder drawer, AJAX save, cards update.
 */
(function ($) {
	'use strict';

	var drawer = $('#cro-offer-drawer');
	var form = $('#cro-offer-drawer-form');
	var offerIndexInput = $('#cro-drawer-offer-index');
	var drawerTitle = $('#cro-offer-drawer-title');
	var rewardTypeSelect = $('#cro-drawer-reward-type');
	var rewardAmountWrap = form.find('.cro-drawer-reward-amount-wrap');
	var rewardSuffix = form.find('.cro-drawer-reward-suffix');

	var maxOffers = parseInt(window.croOffersMaxOffers, 10) || 5;
	var offersData = Array.isArray(window.croOffersData) ? window.croOffersData : [];
	var usedCount = parseInt(window.croOffersUsedCount, 10) || 0;
	var drawerReturnFocusEl = null;

	var i18n = (window.croOffersI18n || {});
var checkIcon = (i18n.checkIcon != null && i18n.checkIcon !== '') ? i18n.checkIcon : '✓';
var crossIcon = (i18n.crossIcon != null && i18n.crossIcon !== '') ? i18n.crossIcon : '✗';

	function getDrawerFocusables() {
		var panel = document.querySelector('#cro-offer-drawer .cro-offer-drawer-panel');
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
		$(document).on('keydown.croDrawerTrap', trapDrawerFocus);
		setTimeout(function () {
			var first = getDrawerFocusables()[0];
			if (first) first.focus();
			else $('#cro-drawer-headline').focus();
			var panel = document.querySelector('#cro-offer-drawer .cro-offer-drawer-panel');
			if (panel && window.CRO_SelectWoo && typeof window.CRO_SelectWoo.initWithin === 'function') {
				window.CRO_SelectWoo.initWithin(panel);
				$(panel).find('.cro-selectwoo, .cro-select-woo').each(function () {
					var $s = $(this);
					if ($s.data('selectWoo')) {
						$s.trigger('change.select2');
					}
				});
			} else {
				$(document).trigger('cro-select-woo-init');
			}
			setTimeout(updateDrawerSectionMaxHeights, 50);
		}, 0);
	}

	function closeDrawer() {
		drawer.removeClass('is-open').attr('aria-hidden', 'true');
		$(document).off('keydown.croDrawerTrap', trapDrawerFocus);
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
		var panel = document.querySelector('#cro-offer-drawer .cro-offer-drawer-panel');
		if (!panel) return;
		$(panel).find('.cro-offer-drawer-section').each(function () {
			var $section = $(this);
			var $body = $section.find('.cro-offer-drawer-section__body').first();
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
		var cats = window.croOffersProductCategories || [];
		var opts = cats.map(function (c) {
			return '<option value="' + escapeHtml(String(c.id)) + '"' + (String(c.id) === String(catId) ? ' selected' : '') + '>' + escapeHtml(c.name) + '</option>';
		}).join('');
		var row = '<div class="cro-drawer-per-cat-row">' +
			'<select name="cro_drawer_per_category_discount_cat[]" class="cro-drawer-select cro-selectwoo cro-per-cat-select" data-placeholder="' + (i18n.categoryPlaceholder || 'Category…') + '">' +
			'<option value="">' + (i18n.selectCategory || '— Select —') + '</option>' + opts + '</select>' +
			'<input type="number" name="cro_drawer_per_category_discount_amount[]" value="' + (amount !== undefined && amount !== '' ? escapeHtml(String(amount)) : '') + '" min="0" step="0.01" placeholder="' + (i18n.amount || 'Amount') + '" class="small-text cro-drawer-per-cat-amount" />' +
			'<button type="button" class="button cro-drawer-remove-per-cat">' + (i18n.remove || 'Remove') + '</button></div>';
		return row;
	}

	function buildPerCategoryDiscountList(perCategoryDiscount) {
		var $container = $('#cro-drawer-per-category-discount');
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
		$container.append('<button type="button" class="button cro-drawer-add-per-cat">' + (i18n.addCategoryDiscount || 'Add category discount') + '</button>');
		if (window.CRO_SelectWoo && typeof window.CRO_SelectWoo.initWithin === 'function') {
			window.CRO_SelectWoo.initWithin($container[0]);
		}
	}

	$('#cro-drawer-per-category-discount').on('click', '.cro-drawer-add-per-cat', function () {
		var $container = $('#cro-drawer-per-category-discount');
		$container.find('.cro-drawer-add-per-cat').remove();
		$container.append(buildPerCategoryDiscountRow('', ''));
		$container.append('<button type="button" class="button cro-drawer-add-per-cat">' + (i18n.addCategoryDiscount || 'Add category discount') + '</button>');
		$(document).trigger('cro-select-woo-init');
		setTimeout(updateDrawerSectionMaxHeights, 80);
	});

	$('#cro-drawer-per-category-discount').on('click', '.cro-drawer-remove-per-cat', function () {
		$(this).closest('.cro-drawer-per-cat-row').remove();
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
		var $sel = $('#cro-drawer-conflict-offers');
		if (!$sel.length) {
			return;
		}
		var choices = Array.isArray(window.croOfferConflictChoices) ? window.croOfferConflictChoices : [];
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
		$('#cro-drawer-headline').val(offer.headline || '');
		$('#cro-drawer-description').val(offer.description || '');
		$('#cro-drawer-enabled').prop('checked', !!offer.enabled);
		$('#cro-drawer-priority').val(offer.priority !== undefined ? offer.priority : 10);
		$('#cro-drawer-min-cart-total').val(offer.min_cart_total !== undefined ? offer.min_cart_total : 0);
		$('#cro-drawer-max-cart-total').val(offer.max_cart_total > 0 ? offer.max_cart_total : '');
		$('#cro-drawer-min-items').val(offer.min_items !== undefined ? offer.min_items : 0);
		$('#cro-drawer-first-time').prop('checked', !!offer.first_time_customer);
		var returningMin = offer.returning_customer_min_orders !== undefined ? parseInt(offer.returning_customer_min_orders, 10) : 0;
		$('#cro-drawer-returning-toggle').prop('checked', returningMin > 0);
		$('#cro-drawer-returning-min-orders').val(returningMin > 0 ? returningMin : 0);
		$('#cro-drawer-lifetime-spend').val(offer.lifetime_spend_min !== undefined ? offer.lifetime_spend_min : 0);
		$('#cro-drawer-allowed-roles').val(Array.isArray(offer.allowed_roles) ? offer.allowed_roles : []);
		$('#cro-drawer-excluded-roles').val(Array.isArray(offer.excluded_roles) ? offer.excluded_roles : []);
		$('#cro-drawer-reward-type').val(offer.reward_type || 'percent');
		$('#cro-drawer-reward-amount').val(offer.reward_amount !== undefined ? offer.reward_amount : 10);
		$('#cro-drawer-coupon-ttl').val(offer.coupon_ttl_hours > 0 ? offer.coupon_ttl_hours : 48);
		$('#cro-drawer-individual-use').prop('checked', !!offer.individual_use);
		$('#cro-drawer-rate-limit-hours').val(offer.rate_limit_hours !== undefined ? offer.rate_limit_hours : 6);
		$('#cro-drawer-max-coupons-per-visitor').val(offer.max_coupons_per_visitor !== undefined ? offer.max_coupons_per_visitor : 1);
		$('#cro-drawer-exclude-sale-items').prop('checked', !!offer.exclude_sale_items);
		$('#cro-drawer-include-categories').val(Array.isArray(offer.include_categories) ? offer.include_categories.map(String) : []);
		$('#cro-drawer-exclude-categories').val(Array.isArray(offer.exclude_categories) ? offer.exclude_categories.map(String) : []);
		$('#cro-drawer-include-products').val(Array.isArray(offer.include_products) ? offer.include_products.map(String) : []);
		$('#cro-drawer-exclude-products').val(Array.isArray(offer.exclude_products) ? offer.exclude_products.map(String) : []);
		$('#cro-drawer-cart-contains-category').val(Array.isArray(offer.cart_contains_category) ? offer.cart_contains_category.map(String) : []);
		var mqfc = offer.min_qty_for_category;
		if (mqfc && typeof mqfc === 'object' && !Array.isArray(mqfc)) {
			$('#cro-drawer-min-qty-for-category').val(Object.keys(mqfc).map(function (k) { return k + ':' + mqfc[k]; }).join('\n'));
		} else {
			$('#cro-drawer-min-qty-for-category').val('');
		}
		$('#cro-drawer-apply-to-categories').val(Array.isArray(offer.apply_to_categories) ? offer.apply_to_categories.map(String) : []);
		$('#cro-drawer-apply-to-products').val(Array.isArray(offer.apply_to_products) ? offer.apply_to_products.map(String) : []);
		buildPerCategoryDiscountList(offer.per_category_discount || {});
		var confIds = Array.isArray(offer.conflict_offer_ids) ? offer.conflict_offer_ids.map(String) : [];
		$('#cro-drawer-conflict-offers').val(confIds);
		// Sync SelectWoo (if present) after setting values
		$('#cro-offer-drawer-panel .cro-selectwoo').trigger('change');
		$(document).trigger('cro-select-woo-init');
		updateReturningVisibility();
	}

	function updateReturningVisibility() {
		var on = $('#cro-drawer-returning-toggle').prop('checked');
		var $wrap = $('#cro-drawer-returning-min-wrap');
		if (on) {
			$wrap.show();
		} else {
			$wrap.hide();
			$('#cro-drawer-returning-min-orders').val(0);
		}
	}

	function formatMoney(amount) {
		var cur = (window.croAdmin && window.croAdmin.currency) ? window.croAdmin.currency : '';
		var n = parseFloat(amount, 10);
		if (isNaN(n)) return '0' + cur;
		return (Math.round(n * 100) / 100).toFixed(2) + cur;
	}

	function buildOfferSummary() {
		var bullet = i18n.summaryBullet || ' • ';
		var arrow = i18n.summaryArrow || ' → ';
		var headline = ($('#cro-drawer-headline').val() || '').trim();
		var titleStr = headline ? headline : (i18n.newOffer || 'New offer');
		var parts = [];
		var minCart = parseFloat($('#cro-drawer-min-cart-total').val(), 10) || 0;
		var maxCart = parseFloat($('#cro-drawer-max-cart-total').val(), 10) || 0;
		var minItems = parseInt($('#cro-drawer-min-items').val(), 10) || 0;
		var excludeSale = $('#cro-drawer-exclude-sale-items').prop('checked');
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
		if ($('#cro-drawer-first-time').prop('checked')) {
			parts.push(i18n.summaryFirstTime || 'First-time customer');
		}
		if ($('#cro-drawer-returning-toggle').prop('checked')) {
			var minOrd = parseInt($('#cro-drawer-returning-min-orders').val(), 10) || 1;
			parts.push((i18n.summaryReturning || 'Returning customer (≥%d orders)').replace('%d', String(minOrd)));
		}
		var lifetime = parseFloat($('#cro-drawer-lifetime-spend').val(), 10) || 0;
		if (lifetime > 0) {
			parts.push((i18n.summaryLifetime || 'Lifetime spend ≥ %s').replace('%s', formatMoney(lifetime)));
		}
		var ruleStr = parts.length ? parts.join(bullet) : (i18n.summaryCartMin || 'Cart ≥ %s').replace('%s', formatMoney(0));
		var rewardType = $('#cro-drawer-reward-type').val();
		var rewardAmount = parseFloat($('#cro-drawer-reward-amount').val(), 10) || 0;
		var rewardStr;
		if (rewardType === 'free_shipping') {
			rewardStr = i18n.summaryRewardShip || 'Free shipping';
		} else if (rewardType === 'percent') {
			rewardStr = (i18n.summaryRewardPct || '%s% off').replace('%s', String(rewardAmount));
		} else {
			rewardStr = (i18n.summaryRewardFix || '%s off').replace('%s', formatMoney(rewardAmount));
		}
		var ttl = parseInt($('#cro-drawer-coupon-ttl').val(), 10) || 48;
		var expiresStr = (i18n.summaryExpires || 'Expires %sh').replace('%s', String(ttl));
		return titleStr + bullet + ruleStr + arrow + rewardStr + bullet + expiresStr;
	}

	function updateOfferSummary() {
		var $el = $('#cro-drawer-offer-summary');
		var $bar = $('#cro-offer-drawer-summary-bar');
		if (!$el.length) return;
		$el.text(buildOfferSummary());
		if ($bar.length) {
			$bar.addClass('cro-offer-drawer-summary-bar--updated');
			clearTimeout(updateOfferSummary._flashTimer);
			updateOfferSummary._flashTimer = setTimeout(function () {
				$bar.removeClass('cro-offer-drawer-summary-bar--updated');
			}, 400);
		}
	}

	function toggleRewardAmountVisibility(rewardType) {
		if (rewardType === 'free_shipping') {
			rewardAmountWrap.hide();
		} else {
			rewardAmountWrap.show();
			rewardSuffix.text(rewardType === 'percent' ? '%' : (window.croAdmin && window.croAdmin.currency ? window.croAdmin.currency : ''));
		}
	}

	function clearInlineErrors() {
		form.find('.cro-field').removeClass('has-error');
		form.find('.cro-offer-drawer-field-error').remove();
	}

		function showInlineError(fieldSelector, message, noFocus) {
			var $field = form.find(fieldSelector).first();
			var $wrap = $field.closest('.cro-field');
			if (!$wrap.length) return;
			$wrap.addClass('has-error');
			var $err = $wrap.find('.cro-offer-drawer-field-error');
			if (!$err.length) $err = $('<span class="cro-offer-drawer-field-error" role="alert"></span>').appendTo($wrap);
			$err.text(message).show();
			if (!noFocus) $field.focus();
		}

		// Map schema error keys to drawer field selectors for server validation errors.
		var offerErrorKeyToSelector = {
			headline: '#cro-drawer-headline',
			priority: '#cro-drawer-priority',
			reward_type: '#cro-drawer-reward-type',
			reward_amount: '#cro-drawer-reward-amount',
			coupon_ttl_hours: '#cro-drawer-coupon-ttl',
			rate_limit_hours: '#cro-drawer-rate-limit-hours',
			max_coupons_per_visitor: '#cro-drawer-max-coupons-per-visitor',
			conflict_offer_ids: '#cro-drawer-conflict-offers'
		};

		function showFieldErrors(errors) {
			if (!errors || typeof errors !== 'object') return;
			clearInlineErrors();
			var firstSelector = null;
			Object.keys(errors).forEach(function (key) {
				var selector = offerErrorKeyToSelector[key] || ('#cro-drawer-' + key.replace(/_/g, '-'));
				showInlineError(selector, errors[key], true);
				if (!firstSelector) firstSelector = selector;
			});
			if (firstSelector) form.find(firstSelector).focus();
		}

	function validateForm() {
		clearInlineErrors();
		var headline = ($('#cro-drawer-headline').val() || '').trim();
		if (!headline) {
			showInlineError('#cro-drawer-headline', i18n.nameRequired || 'Offer name is required.');
			return false;
		}
		var priority = parseInt($('#cro-drawer-priority').val(), 10);
		if (isNaN(priority)) {
			showInlineError('#cro-drawer-priority', i18n.priorityInteger || 'Priority must be a number.');
			return false;
		}
		var rewardType = $('#cro-drawer-reward-type').val();
		var rewardAmount = parseFloat($('#cro-drawer-reward-amount').val(), 10);
		if (rewardType === 'percent' && (isNaN(rewardAmount) || rewardAmount < 1 || rewardAmount > 100)) {
			showInlineError('#cro-drawer-reward-amount', i18n.percent1To100 || 'Percent discount must be between 1 and 100.');
			return false;
		}
		if (rewardType === 'fixed' && (isNaN(rewardAmount) || rewardAmount < 0)) {
			showInlineError('#cro-drawer-reward-amount', i18n.fixedMinZero || 'Fixed discount must be 0 or greater.');
			return false;
		}
		var ttl = parseInt($('#cro-drawer-coupon-ttl').val(), 10);
		if (isNaN(ttl) || ttl < 1) {
			showInlineError('#cro-drawer-coupon-ttl', i18n.ttlMin1 || 'Coupon TTL must be at least 1 hour.');
			return false;
		}
		return true;
	}

	function showToast(message) {
		var $container = $('#cro-ui-toast-container');
		if ($container.length) {
			var $item = $('<div class="cro-ui-toast" role="status"></div>').text(message);
			$container.append($item);
			setTimeout(function () {
				$item.fadeOut(200, function () { $item.remove(); });
			}, 4000);
			return;
		}
		var $fallback = $('#cro-offers-toast');
		if (!$fallback.length) {
			$fallback = $('<div id="cro-offers-toast" class="cro-offers-toast" role="status" aria-live="polite"></div>').appendTo('.cro-offers-page');
		}
		var $item = $('<div class="cro-offers-toast-item" role="status"></div>').text(message);
		$fallback.append($item);
		setTimeout(function () {
			$item.addClass('cro-offers-toast-out');
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
		$('.cro-offers-page .cro-offers-header').first().after(notice);
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
		var editBtn = '<button type="button" class="button button-small cro-offer-card-edit" data-cro-offer-index="' + item.index + '">' + editIcon + ' ' + (i18n.edit || 'Edit') + '</button>';
		var confN = Array.isArray(o.conflict_offer_ids) ? o.conflict_offer_ids.length : 0;
		var conflictsLabel = i18n.conflictsLabel || 'Conflicts';
		var confBlock = '';
		if (confN > 0) {
			var badgeText = (i18n.conflictsBadge || '%d conflicts').replace('%d', String(confN));
			confBlock = '<div class="cro-offer-card-conflicts-row"><span class="cro-offer-card-conflicts-label">' + escapeHtml(conflictsLabel) + '</span> <span class="cro-offer-conflict-badge">' + escapeHtml(badgeText) + '</span></div>';
		} else {
			confBlock = '<div class="cro-offer-card-conflicts-row"><span class="cro-offer-card-conflicts-label">' + escapeHtml(conflictsLabel) + '</span> <span class="cro-offer-conflict-badge cro-offer-conflict-badge--none" aria-hidden="true">—</span></div>';
		}
		var moveUpDown = '<span class="cro-offer-card-move-btns">' +
			'<button type="button" class="button button-small cro-offer-move-up" data-cro-offer-index="' + item.index + '" title="' + escapeHtml(i18n.moveUp || 'Move up') + '" aria-label="' + escapeHtml(i18n.moveUp || 'Move up') + '">' + moveUpIcon + '</button>' +
			'<button type="button" class="button button-small cro-offer-move-down" data-cro-offer-index="' + item.index + '" title="' + escapeHtml(i18n.moveDown || 'Move down') + '" aria-label="' + escapeHtml(i18n.moveDown || 'Move down') + '">' + moveDownIcon + '</button></span>';
		var duplicateBtn = usedCount < maxOffers ? '<button type="button" class="button button-small cro-offer-card-duplicate" data-cro-offer-id="' + escapeHtml(offerId) + '" data-cro-offer-index="' + item.index + '">' + duplicateIcon + ' ' + (i18n.duplicate || 'Duplicate') + '</button>' : '';
		var deleteBtn = '<button type="button" class="button button-small cro-offer-card-delete" data-cro-offer-id="' + escapeHtml(offerId) + '" data-cro-offer-index="' + item.index + '">' + deleteIcon + ' ' + (i18n.delete || 'Delete') + '</button>';
		var toggleForm = '<form method="post" class="cro-offer-card-toggle-form">' +
			'<input type="hidden" name="cro_offers_nonce" value="' + (window.croOffersNonce || '') + '">' +
			'<input type="hidden" name="cro_toggle_offer" value="1">' +
			'<input type="hidden" name="cro_offer_index" value="' + item.index + '">' +
			'<label class="cro-offer-card-toggle">' +
			'<input type="checkbox" ' + (o.enabled ? 'checked' : '') + ' onchange="this.form.submit()">' +
			'<span class="cro-offer-card-toggle-slider"></span></label></form>';
		return '<div class="cro-offer-card" data-offer-index="' + item.index + '" data-offer-id="' + escapeHtml(offerId) + '" data-priority="' + (o.priority || 10) + '" draggable="true">' +
			'<div class="cro-offer-card-main">' +
			'<div class="cro-offer-card-head">' +
			'<span class="cro-offer-card-drag-handle" title="' + escapeHtml(i18n.dragToReorder || 'Drag to reorder') + '" aria-hidden="true"></span>' +
			'<h3 class="cro-offer-card-name">' + escapeHtml(o.headline) + '</h3>' +
			'<span class="cro-offer-card-status cro-offer-card-status--' + statusClass + '">' + statusText + '</span>' +
			'</div>' +
			'<p class="cro-offer-card-rule">' + escapeHtml(item.rule_summary) + '</p>' +
			'<p class="cro-offer-card-reward">' + escapeHtml(item.reward_summary) + '</p>' +
			'<p class="cro-offer-card-priority">' + escapeHtml(priorityText) + '</p>' +
			confBlock +
			'</div>' +
			'<div class="cro-offer-card-actions">' +
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
		var $saveBtn = form.find('.cro-offer-drawer-save');
		var originalText = $saveBtn.text();
		$saveBtn.prop('disabled', true).text(i18n.saving || 'Saving...');

		var formData = form.serialize();
		var ajaxUrl = (window.croAdmin && window.croAdmin.ajaxUrl) ? window.croAdmin.ajaxUrl : (window.ajaxurl || '/wp-admin/admin-ajax.php');

		$.post(ajaxUrl, formData + '&action=cro_save_offer')
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
		window.croOfferConflictChoices = choices;
	}

	function toggleCheckConflictsButton() {
		var container = $('.cro-offers-page');
		var $bar = container.find('.cro-offers-toolbar');
		if (!$bar.length) {
			return;
		}
		if (usedCount > 0) {
			if (!$bar.find('#cro-offers-check-conflicts').length) {
				var $btn = $('<button type="button" id="cro-offers-check-conflicts" class="button button-secondary cro-offers-check-conflicts"></button>');
				$btn.text(i18n.checkConflicts || 'Check for conflicts');
				$bar.append($btn);
			}
		} else {
			$bar.find('#cro-offers-check-conflicts').remove();
		}
	}

	// When response has offers, we need to show empty state if 0 or grid if 1+. Use server-returned count/max, not cached DOM state.
	function updatePageFromResponse(data) {
		if (data.max_offers !== undefined) {
			maxOffers = parseInt(data.max_offers, 10) || maxOffers;
			window.croOffersMaxOffers = maxOffers;
		}
		usedCount = data.offers_used_count !== undefined ? parseInt(data.offers_used_count, 10) : usedCount;
		window.croOffersUsedCount = usedCount;

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

		var container = $('.cro-offers-page');
		var countEl = container.find('.cro-offers-count');
		countEl.text(usedCount + '/' + maxOffers + ' ' + (i18n.offersUsed || 'offers used'));

		if (usedCount >= maxOffers) {
			container.find('.cro-offers-add-btn').prop('disabled', true).removeAttr('data-cro-drawer');
			if (!container.find('.cro-offers-limit-note').length) {
				container.find('.cro-offers-header-actions').prepend('<span class="cro-offers-limit-note">' + (i18n.limitReached || 'Offer limit reached (5).') + '</span>');
			}
		} else {
			container.find('.cro-offers-add-btn').prop('disabled', false).attr('data-cro-drawer', 'add');
			container.find('.cro-offers-limit-note').remove();
		}

		if (!Array.isArray(data.offers) || data.offers.length === 0) {
			// Show empty state, hide grid.
			var gridParent = container.find('.cro-offers-grid').parent();
			if (gridParent.length && container.find('.cro-offers-empty').length === 0) {
				gridParent.html(
					'<div class="cro-offers-empty">' +
					'<div class="cro-offers-empty-illustration" aria-hidden="true"></div>' +
					'<p class="cro-offers-empty-title">' + (i18n.noOffersYet || 'No offers yet') + '</p>' +
					'<p class="cro-offers-empty-desc">' + (i18n.emptyDesc || 'Create your first offer to show a dynamic reward on cart and checkout.') + '</p>' +
					'<button type="button" class="button button-primary cro-offers-empty-cta" data-cro-drawer="add">' + (i18n.createFirst || 'Create your first offer') + '</button>' +
					'</div>'
				);
			}
			toggleCheckConflictsButton();
			return;
		}

		// Replace grid content with new cards.
		var grid = container.find('.cro-offers-grid');
		if (grid.length) {
			grid.html(data.offers.map(renderCard).join(''));
		} else {
			// Currently showing empty state; replace with grid (new node needs listeners).
			container.find('.cro-offers-empty').replaceWith(
				'<div class="cro-offers-grid">' + data.offers.map(renderCard).join('') + '</div>'
			);
			initOfferReorder();
		}
		toggleCheckConflictsButton();
	}

	$(function () {
		syncConflictChoicesFromOffersData();

		$(document).on('click', '#cro-offers-check-conflicts', function () {
			var $btn = $(this);
			var ajaxUrl = (window.croAdmin && window.croAdmin.ajaxUrl) ? window.croAdmin.ajaxUrl : (window.ajaxurl || '/wp-admin/admin-ajax.php');
			var nonce = i18n.reorderNonce || '';
			var prevText = $btn.text();
			$btn.prop('disabled', true).text(i18n.checkConflictsRunning || 'Checking…');
			$.post(ajaxUrl, { action: 'cro_detect_offer_conflicts', nonce: nonce })
				.done(function (res) {
					var $holder = $('#cro-offers-conflict-notices');
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

		$(document).on('click', '[data-cro-drawer="add"]', function () {
			openDrawer('add', undefined, $(this));
		});

		$(document).on('click', '.cro-offer-card-edit', function () {
			var index = parseInt($(this).data('cro-offer-index'), 10);
			openDrawer('edit', index, $(this));
		});

		drawer.find('.cro-offer-drawer-close, .cro-offer-drawer-backdrop, .cro-offer-drawer-cancel').on('click', closeDrawer);

		// Accordion: collapse/expand drawer sections
		$(document).on('click', '#cro-offer-drawer .cro-offer-drawer-section__header', function () {
			var $btn = $(this);
			var $section = $btn.closest('.cro-offer-drawer-section');
			var $body = $section.find('.cro-offer-drawer-section__body').first();
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

		$('#cro-drawer-returning-toggle').on('change', function () {
			updateReturningVisibility();
			updateOfferSummary();
		});

		form.on('input keyup', '#cro-drawer-headline', updateOfferSummary);
		form.on('input change keyup', '#cro-drawer-min-cart-total, #cro-drawer-max-cart-total, #cro-drawer-min-items, #cro-drawer-returning-min-orders, #cro-drawer-lifetime-spend, #cro-drawer-reward-amount, #cro-drawer-coupon-ttl, #cro-drawer-priority, #cro-drawer-rate-limit-hours, #cro-drawer-max-coupons-per-visitor', updateOfferSummary);
		form.on('change', '#cro-drawer-exclude-sale-items, #cro-drawer-first-time, #cro-drawer-reward-type, #cro-drawer-enabled', updateOfferSummary);
		$(document).on('change select2:select select2:unselect', '#cro-offer-drawer .cro-selectwoo', updateOfferSummary);

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
		$(document).on('click', '.cro-offer-card-delete', function () {
			var id = $(this).data('cro-offer-id');
			if (!id) return;
			var name = $(this).closest('.cro-offer-card').find('.cro-offer-card-name').first().text().trim() || (i18n.offer || 'Offer');
			var msg = (i18n.deleteConfirmName || 'Delete offer "%s"?').replace('%s', name);
			if (!confirm(msg)) return;
			var $btn = $(this);
			$btn.prop('disabled', true);
			var ajaxUrl = (window.croAdmin && window.croAdmin.ajaxUrl) ? window.croAdmin.ajaxUrl : (window.ajaxurl || '/wp-admin/admin-ajax.php');
			$.post(ajaxUrl, {
				action: 'cro_offer_delete',
				nonce: (window.croOffersI18n && window.croOffersI18n.reorderNonce) || '',
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
		$(document).on('click', '.cro-offer-card-duplicate', function () {
			var id = $(this).data('cro-offer-id');
			if (!id) return;
			var $btn = $(this);
			$btn.prop('disabled', true);
			var ajaxUrl = (window.croAdmin && window.croAdmin.ajaxUrl) ? window.croAdmin.ajaxUrl : (window.ajaxurl || '/wp-admin/admin-ajax.php');
			$.post(ajaxUrl, {
				action: 'cro_offer_duplicate',
				nonce: (window.croOffersI18n && window.croOffersI18n.reorderNonce) || '',
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
		$(document).on('click', '.cro-offer-move-up', function () {
			var grid = document.querySelector('.cro-offers-grid');
			if (!grid) return;
			var cards = grid.querySelectorAll('.cro-offer-card');
			var idx = parseInt($(this).data('cro-offer-index'), 10);
			var order = [];
			for (var i = 0; i < cards.length; i++) {
				order.push(parseInt(cards[i].getAttribute('data-offer-index'), 10));
			}
			var pos = order.indexOf(idx);
			if (pos <= 0) return;
			order.splice(pos, 1);
			order.splice(pos - 1, 0, idx);
			if (window.croSaveReorderWithOrder) window.croSaveReorderWithOrder(grid, order);
		});
		$(document).on('click', '.cro-offer-move-down', function () {
			var grid = document.querySelector('.cro-offers-grid');
			if (!grid) return;
			var cards = grid.querySelectorAll('.cro-offer-card');
			var idx = parseInt($(this).data('cro-offer-index'), 10);
			var order = [];
			for (var i = 0; i < cards.length; i++) {
				order.push(parseInt(cards[i].getAttribute('data-offer-index'), 10));
			}
			var pos = order.indexOf(idx);
			if (pos < 0 || pos >= order.length - 1) return;
			order.splice(pos, 1);
			order.splice(pos + 1, 0, idx);
			if (window.croSaveReorderWithOrder) window.croSaveReorderWithOrder(grid, order);
		});

		// Test Offer: Run Test button
		$('#cro-offer-test-run').on('click', function () {
			var $btn = $(this);
			var cartTotal = parseFloat($('#cro-test-cart-total').val(), 10) || 0;
			var itemsCount = parseInt($('#cro-test-items-count').val(), 10) || 0;
			var isLoggedIn = $('#cro-test-is-logged-in').val() === '1';
			var orderCount = parseInt($('#cro-test-order-count').val(), 10) || 0;
			var lifetimeSpend = parseFloat($('#cro-test-lifetime-spend').val(), 10) || 0;
			var userRole = ($('#cro-test-user-role').val() || '').trim();

			var payload = {
				action: 'cro_offer_test',
				nonce: (window.croOffersI18n && window.croOffersI18n.reorderNonce) || '',
				cart_total: cartTotal,
				cart_items_count: itemsCount,
				is_logged_in: isLoggedIn ? '1' : '0',
				order_count: orderCount,
				lifetime_spend: lifetimeSpend,
				user_role: userRole
			};

			var ajaxUrl = (window.croAdmin && window.croAdmin.ajaxUrl) ? window.croAdmin.ajaxUrl : (window.ajaxurl || '/wp-admin/admin-ajax.php');
			var $matchBlock = $('#cro-offer-test-output');
			var $noMatchBlock = $('#cro-offer-test-no-match');

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
						var matchHtml = '<div class="cro-offer-test-match">';
						matchHtml += '<p class="cro-offer-test-match-title"><strong>' + escapeHtml(i18n.matchingOffer || 'Matching offer:') + '</strong></p>';
						matchHtml += '<ul class="cro-offer-test-match-list">';
						matchHtml += '<li><strong>' + escapeHtml(i18n.name || 'Name') + ':</strong> ' + escapeHtml(match.name || '-') + '</li>';
						if (match.rule_summary) matchHtml += '<li><strong>' + escapeHtml(i18n.rule || 'Rule') + ':</strong> ' + escapeHtml(match.rule_summary) + '</li>';
						if (match.reward_summary) matchHtml += '<li><strong>' + escapeHtml(i18n.reward || 'Reward') + ':</strong> ' + escapeHtml(match.reward_summary) + '</li>';
						matchHtml += '</ul></div>';
						$matchBlock.find('.cro-offer-test-result-match').html(matchHtml);

						var condHtml = '<p class="cro-offer-test-checks-title"><strong>' + escapeHtml(i18n.why || 'Checks:') + '</strong></p><ul class="cro-offer-test-checks">';
						checks.forEach(function (c) {
							var label = (c.label != null && c.label !== '') ? c.label : (c.key || '');
							var passed = c.passed === true;
							var iconClass = passed ? 'cro-offer-test-check--pass' : 'cro-offer-test-check--fail';
							var icon = passed ? checkIcon : crossIcon;
							var expected = (c.expected != null && c.expected !== '') ? String(c.expected) : '';
							var actual = (c.actual != null && c.actual !== '') ? String(c.actual) : '';
							var expl = '';
							if (!passed && (expected || actual)) {
								expl = ' <span class="cro-offer-test-check-detail">(' + (i18n.expectedLabel || 'Expected:') + ' ' + escapeHtml(expected) + (actual ? '; ' + (i18n.actualLabel || 'Actual:') + ' ' + escapeHtml(actual) : '') + ')</span>';
							}
							condHtml += '<li class="cro-offer-test-check ' + iconClass + '"><span class="cro-offer-test-check-icon" aria-hidden="true">' + icon + '</span> ' + escapeHtml(label) + expl + '</li>';
						});
						condHtml += '</ul>';
						$matchBlock.find('.cro-offer-test-result-conditions').html(condHtml);
						$matchBlock.show();
					} else {
						var noMatchHtml = '<p class="cro-offer-test-no-match-msg">' + escapeHtml(message || (i18n.noEligibleOffer || 'No eligible offer')) + '</p>';
						if (suggestions.length > 0) {
							noMatchHtml += '<p class="cro-offer-test-suggestions-title">' + escapeHtml(i18n.suggestionsLabel || 'Suggestions:') + '</p><ul class="cro-offer-test-suggestions">';
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
		var grid = document.querySelector('.cro-offers-grid');
		if (!grid) return;

		var draggedCard = null;
		var priorityLabel = (window.croOffersI18n && window.croOffersI18n.priorityLabel) ? window.croOffersI18n.priorityLabel : 'Priority: %s';

		function getCardIndex(card) {
			var idx = card && card.getAttribute && card.getAttribute('data-offer-index');
			return idx !== null && idx !== undefined ? parseInt(idx, 10) : -1;
		}

		function onlyHandle(e) {
			var t = e.target;
			while (t && t !== grid) {
				if (t.classList && t.classList.contains('cro-offer-card-drag-handle')) return true;
				t = t.parentNode;
			}
			return false;
		}

		grid.addEventListener('dragstart', function (e) {
			if (!onlyHandle(e)) { e.preventDefault(); return; }
			var card = e.target.closest && e.target.closest('.cro-offer-card');
			if (!card) return;
			draggedCard = card;
			e.dataTransfer.setData('text/plain', getCardIndex(card));
			e.dataTransfer.effectAllowed = 'move';
			card.classList.add('cro-offer-card-dragging');
		});

		grid.addEventListener('dragend', function (e) {
			if (draggedCard) draggedCard.classList.remove('cro-offer-card-dragging');
			draggedCard = null;
		});

		grid.addEventListener('dragover', function (e) {
			e.preventDefault();
			e.dataTransfer.dropEffect = 'move';
			var card = e.target.closest && e.target.closest('.cro-offer-card');
			if (card && card !== draggedCard) card.classList.add('cro-offer-card-drag-over');
		});

		grid.addEventListener('dragleave', function (e) {
			var card = e.target.closest && e.target.closest('.cro-offer-card');
			if (card) card.classList.remove('cro-offer-card-drag-over');
		});

		grid.addEventListener('drop', function (e) {
			e.preventDefault();
			var card = e.target.closest && e.target.closest('.cro-offer-card');
			if (!card || !draggedCard || card === draggedCard) {
				document.querySelectorAll('.cro-offer-card-drag-over').forEach(function (el) { el.classList.remove('cro-offer-card-drag-over'); });
				return;
			}
			card.classList.remove('cro-offer-card-drag-over');
			// Insert dragged card before the drop target
			grid.insertBefore(draggedCard, card);
			saveReorder(grid);
		});

		function saveReorder(container) {
			var cards = container.querySelectorAll('.cro-offer-card');
			var order = [];
			for (var i = 0; i < cards.length; i++) {
				var idx = getCardIndex(cards[i]);
				if (idx >= 0) order.push(idx);
			}
			if (order.length === 0) return;
			saveReorderWithOrder(container, order);
		}

		window.croSaveReorderWithOrder = function (container, order) {
			if (!order || order.length === 0) return;
			var formData = new FormData();
			formData.append('action', 'cro_offer_reorder');
			formData.append('nonce', (window.croOffersI18n && window.croOffersI18n.reorderNonce) || '');
			order.forEach(function (idx) { formData.append('order[]', idx); });

			var xhr = new XMLHttpRequest();
			xhr.open('POST', (window.croAdmin && window.croAdmin.ajaxUrl) || window.ajaxurl || '/wp-admin/admin-ajax.php');
			xhr.onload = function () {
				var res;
				try { res = JSON.parse(xhr.responseText); } catch (err) { res = {}; }
				if (res.success && res.data) {
					if (res.data.offers && Array.isArray(res.data.offers) && res.data.offers.length > 0 && window.jQuery) {
						usedCount = res.data.offers_used_count !== undefined ? parseInt(res.data.offers_used_count, 10) : usedCount;
						var containerEl = document.querySelector('.cro-offers-page');
						if (containerEl) {
							var grid = containerEl.querySelector('.cro-offers-grid');
							if (grid) {
								grid.innerHTML = res.data.offers.map(renderCard).join('');
								initOfferReorder();
							}
						}
						var countEl = document.querySelector('.cro-offers-page .cro-offers-count');
						if (countEl) countEl.textContent = usedCount + '/' + maxOffers + ' ' + (i18n.offersUsed || 'offers used');
					} else if (res.data.priorities) {
						var priorities = res.data.priorities;
						container.querySelectorAll('.cro-offer-card').forEach(function (el) {
							var idx = getCardIndex(el);
							var p = priorities[idx];
							if (p !== undefined) {
								el.setAttribute('data-priority', p);
								var labelEl = el.querySelector('.cro-offer-card-priority');
								if (labelEl) labelEl.textContent = priorityLabel.replace('%s', String(p));
							}
						});
					}
					if (window.jQuery) {
						var msg = (window.croOffersI18n && window.croOffersI18n.reorderSaved) || 'Order saved.';
						window.jQuery('.cro-offers-page .cro-offers-header').first().after(
							'<div class="notice notice-success is-dismissible"><p>' + msg + '</p></div>'
						);
					}
				} else {
					var errMsg = (window.croOffersI18n && window.croOffersI18n.reorderError) || 'Could not save order.';
					if (window.jQuery) {
						window.jQuery('.cro-offers-page .cro-offers-header').first().after(
							'<div class="notice notice-error is-dismissible"><p>' + errMsg + '</p></div>'
						);
					}
				}
			};
			xhr.onerror = function () {
				var errMsg = (window.croOffersI18n && window.croOffersI18n.reorderError) || 'Could not save order.';
				if (window.jQuery) {
					window.jQuery('.cro-offers-page .cro-offers-header').first().after(
						'<div class="notice notice-error is-dismissible"><p>' + errMsg + '</p></div>'
					);
				}
			};
			xhr.send(formData);
		};

		function saveReorderWithOrder(container, order) {
			window.croSaveReorderWithOrder(container, order);
		}
	}
})(jQuery);
