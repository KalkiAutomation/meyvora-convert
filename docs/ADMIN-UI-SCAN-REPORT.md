# CRO Toolkit – Admin UI Scan Report

**Generated:** Scan of `admin/class-meyvc-admin.php` and admin partials for pages, partials, form fields, and SelectWoo usage.

---

## 1) Admin pages (slugs) registered in `admin/class-meyvc-admin.php`

| Slug | Menu title | Callback / Rendered as |
|------|------------|-------------------------|
| `meyvc-toolkit` | CRO Toolkit / Dashboard | `render_dashboard` → onboarding or dashboard |
| `meyvc-presets` | Presets | `render_presets` |
| `meyvc-campaigns` | Campaigns | `render_campaigns` |
| `meyvc-campaign-edit` | Edit Campaign (hidden) | `render_campaign_builder` |
| `meyvc-boosters` | On-Page Boosters | `render_boosters` |
| `meyvc-cart` | Cart Optimizer | `render_cart_optimizer` |
| `meyvc-abandoned-carts` | Abandoned Carts | `render_abandoned_carts_list` |
| `meyvc-abandoned-cart` | Abandoned Cart Emails | `render_abandoned_cart_emails` |
| `meyvc-offers` | Offers | `render_offers` |
| `meyvc-checkout` | Checkout Optimizer | `render_checkout_optimizer` |
| `meyvc-ab-tests` | A/B Tests | `render_ab_tests` |
| `meyvc-ab-test-new` | Create A/B Test (hidden) | `render_ab_test_new` |
| `meyvc-ab-test-view` | View A/B Test (hidden) | `render_ab_test_view` |
| `meyvc-analytics` | Analytics | `render_analytics` |
| `meyvc-settings` | Settings | `render_settings` |
| `meyvc-system-status` | System Status | `render_system_status` |
| `meyvc-tools` | Tools (Import / Export) | `render_tools` |

---

## 2) Partials used to render those pages

| Page slug | Primary content partial(s) | Notes |
|-----------|----------------------------|--------|
| `meyvc-toolkit` | `meyvc-admin-onboarding.php` or `meyvc-admin-dashboard.php` | Depends on onboarding state |
| `meyvc-presets` | `meyvc-admin-presets.php` | Direct include |
| `meyvc-campaigns` | `meyvc-admin-campaigns.php` | Via `MEYVC_Admin_Layout::render_page` |
| `meyvc-campaign-edit` | `meyvc-admin-campaign-builder.php` | Includes builder sub-partials below |
| `meyvc-boosters` | `meyvc-admin-boosters.php` | Via layout |
| `meyvc-cart` | `meyvc-admin-cart.php` | Via layout |
| `meyvc-abandoned-carts` | `meyvc-admin-abandoned-carts-list.php` | Via layout |
| `meyvc-abandoned-cart` | `meyvc-admin-abandoned-cart.php` | Via layout |
| `meyvc-offers` | `meyvc-admin-offers.php` | Via layout |
| `meyvc-checkout` | `meyvc-admin-checkout.php` | Via layout |
| `meyvc-ab-tests` | `meyvc-admin-ab-tests.php` | Via layout |
| `meyvc-ab-test-new` | `meyvc-admin-ab-test-new.php` | Via layout |
| `meyvc-ab-test-view` | `meyvc-admin-ab-test-view.php` | Via layout |
| `meyvc-analytics` | `meyvc-admin-analytics.php` | Via layout |
| `meyvc-settings` | `meyvc-admin-settings.php` | Via layout |
| `meyvc-system-status` | `meyvc-admin-system-status.php` | Via layout |
| `meyvc-tools` | `meyvc-admin-tools.php` | Via layout |

**Builder sub-partials** (included by `meyvc-admin-campaign-builder.php`):

- `admin/partials/builder/design-controls.php`
- `admin/partials/builder/trigger-controls.php`
- `admin/partials/builder/targeting-controls.php`
- `admin/partials/builder/display-controls.php`

**Unused / legacy partial:** `meyvc-admin-campaign-edit.php` – not referenced by `class-meyvc-admin.php`; campaign edit page uses `meyvc-admin-campaign-builder.php`. Contains its own form (name, type, status, targeting). Consider removing or wiring if still needed.

---

## 3) All `<select>` and `<input>` fields in admin partials

### SelectWoo init class

- **Init class:** `meyvc-select-woo` (see `admin/js/meyvc-select-woo-init.js`).
- Selects with `meyvc-select-woo` are enhanced by SelectWoo when the script runs on CRO admin pages.

### By file

**`admin/partials/meyvc-admin-offers.php`**

| Type | Selector / ID / class | SelectWoo? |
|------|------------------------|------------|
| input hidden | `name="meyvc_toggle_offer"`, `name="meyvc_offer_index"` | — |
| input checkbox | toggle offer | — |
| input hidden | `#meyvc-drawer-offer-index` | — |
| input text | `#meyvc-drawer-headline`, `.regular-text` | — |
| input checkbox | `#meyvc-drawer-enabled` | — |
| input number | `#meyvc-drawer-priority` | — |
| input number | `#meyvc-drawer-min-cart-total`, `#meyvc-drawer-max-cart-total`, `#meyvc-drawer-min-items` | — |
| input checkbox | `#meyvc-drawer-exclude-sale-items` | — |
| **select** | `#meyvc-drawer-include-categories`, `.meyvc-drawer-select.meyvc-select-woo` | ✅ Yes |
| **select** | `#meyvc-drawer-exclude-categories`, `.meyvc-drawer-select.meyvc-select-woo` | ✅ Yes |
| **select** | `#meyvc-drawer-include-products`, `.meyvc-select-woo.meyvc-select-products` | ✅ Yes |
| **select** | `#meyvc-drawer-exclude-products`, `.meyvc-select-woo.meyvc-select-products` | ✅ Yes |
| **select** | `#meyvc-drawer-cart-contains-category`, `.meyvc-select-woo` | ✅ Yes |
| input checkbox | `#meyvc-drawer-first-time`, `#meyvc-drawer-returning-toggle` | — |
| input number | `#meyvc-drawer-returning-min-orders`, `#meyvc-drawer-lifetime-spend` | — |
| **select** | `#meyvc-drawer-allowed-roles`, `.meyvc-select-woo` | ✅ Yes |
| **select** | `#meyvc-drawer-excluded-roles`, `.meyvc-select-woo` | ✅ Yes |
| **select** | `#meyvc-drawer-reward-type` | ❌ No |
| input number | `#meyvc-drawer-reward-amount`, `#meyvc-drawer-coupon-ttl` | — |
| input checkbox | `#meyvc-drawer-individual-use` | — |
| **select** | `#meyvc-drawer-apply-to-categories`, `.meyvc-select-woo` | ✅ Yes |
| **select** | `#meyvc-drawer-apply-to-products`, `.meyvc-select-woo.meyvc-select-products` | ✅ Yes |
| input number | `#meyvc-drawer-rate-limit-hours`, `#meyvc-drawer-max-coupons-per-visitor` | — |
| input number | `#meyvc-test-cart-total`, `#meyvc-test-items-count`, etc. | — |
| **select** | `#meyvc-test-is-logged-in` | ❌ No |
| **select** | `#meyvc-test-user-role` | ❌ No |

**`admin/partials/meyvc-admin-analytics.php`**

| Type | Selector / ID / class | SelectWoo? |
|------|------------------------|------------|
| input hidden | `name="page"` | — |
| input date | `name="from"`, `name="to"` | — |
| **select** | `#meyvc-campaign-filter`, `name="campaign_id"` | ❌ No |

**`admin/partials/meyvc-admin-dashboard.php`**

| Type | Selector / ID / class | SelectWoo? |
|------|------------------------|------------|
| input hidden | `name="meyvc_quick_launch"` | — |

**`admin/partials/meyvc-admin-system-status.php`**

| Type | Selector / ID / class | SelectWoo? |
|------|------------------------|------------|
| input hidden | `name="meyvc_verify_installation"`, `name="meyvc_repair_tables"` | — |

**`admin/partials/meyvc-admin-campaigns.php`**

| Type | Selector / ID / class | SelectWoo? |
|------|------------------------|------------|
| input hidden | `name="meyvc_action"`, `name="campaign_id"` | — |

**`admin/partials/meyvc-admin-abandoned-carts-list.php`**

| Type | Selector / ID / class | SelectWoo? |
|------|------------------------|------------|
| input hidden | `name="page"`, `name="status_filter"` | — |
| input search | `#meyvc-ac-search`, `name="search"` | — |

**`admin/partials/meyvc-admin-campaign-edit.php`** (not used by main menu; may be legacy)

| Type | Selector / ID / class | SelectWoo? |
|------|------------------------|------------|
| input text | `#campaign_name`, `.regular-text` | — |
| **select** | `#campaign_type`, `name="campaign_type"` | ❌ No |
| **select** | `#campaign_status`, `name="campaign_status"` | ❌ No |
| input number | `targeting[behavior][min_time_on_page]`, etc. | — |
| input checkbox | `targeting[behavior][require_interaction]`, device toggles | — |
| **select** | `#cart_status`, `name="targeting[behavior][cart_status]"` | ❌ No |
| **select** | `#visitor_type`, `name="targeting[visitor][type]"` | ❌ No |

**`admin/partials/meyvc-admin-presets.php`**

| Type | Selector / ID / class | SelectWoo? |
|------|------------------------|------------|
| input hidden | `name="meyvc_apply_preset"`, `name="preset_id"` | — |

**`admin/partials/meyvc-admin-campaign-builder.php`**

| Type | Selector / ID / class | SelectWoo? |
|------|------------------------|------------|
| input text | `#campaign-name`, `.meyvc-campaign-name-input` | — |
| **select** | `#campaign-status` | ❌ No |
| input hidden | `#content-image`, `#campaign-id`, `#campaign-data` | — |
| **select** | `#content-tone` | ❌ No |
| input text/url/checkbox/number | content fields (headline, CTA, countdown, etc.) | — |
| **select** | `#content-cta-action` | ❌ No |
| **select** | `#content-countdown-type` | ❌ No |

**`admin/partials/meyvc-admin-ab-test-new.php`**

| Type | Selector / ID / class | SelectWoo? |
|------|------------------------|------------|
| input text | campaign name field | — |
| **select** | `#campaign_id`, `name="campaign_id"` | ❌ No |
| **select** | `#metric`, `name="metric"` | ❌ No |
| input number | sample size | — |
| **select** | `#confidence_level`, `name="confidence_level"` | ❌ No |
| input checkbox | `name="auto_apply_winner"` | — |

**`admin/partials/meyvc-admin-boosters.php`**

| Type | Selector / ID / class | SelectWoo? |
|------|------------------------|------------|
| input checkbox | `sticky_cart_enabled`, `sticky_cart_mobile_only`, etc. | — |
| input number | `#sticky_cart_scroll` | — |
| **select** | `#sticky_cart_tone`, `name="sticky_cart_tone"` | ❌ No |
| input text | `#sticky_cart_button_text`, colors | — |
| **select** | `#shipping_bar_tone`, `name="shipping_bar_tone"` | ❌ No |
| input number | `shipping_bar_threshold` | — |
| input text | `#shipping_bar_message_progress`, `#shipping_bar_message_achieved` | — |
| **select** | `#shipping_bar_position`, `name="shipping_bar_position"` | ❌ No |
| **select** | `#stock_urgency_tone`, `name="stock_urgency_tone"` | ❌ No |
| input text | `#stock_urgency_message` | — |
| input checkbox | `trust_badges_enabled` | — |

**`admin/partials/meyvc-admin-checkout.php`**

| Type | Selector / ID / class | SelectWoo? |
|------|------------------------|------------|
| input checkbox | `checkout_enabled`, `remove_company`, etc. | — |
| input text | `name="trust_message"`, `name="guarantee_text"` | — |

**`admin/partials/meyvc-admin-abandoned-cart.php`**

| Type | Selector / ID / class | SelectWoo? |
|------|------------------------|------------|
| input checkbox | `meyvc_abandoned_cart_enabled`, `meyvc_abandoned_cart_require_opt_in` | — |
| input number | `meyvc_email_1_delay_hours`, etc. | — |
| input text | `#meyvc_email_subject_template`, `.large-text` | — |
| input email | `#meyvc_test_email_to`, `.regular-text` | — |

**`admin/partials/meyvc-admin-cart.php`**

| Type | Selector / ID / class | SelectWoo? |
|------|------------------------|------------|
| input checkbox | `cart_enabled`, `show_trust`, etc. | — |
| input text | `#trust_message`, `#urgency_message`, `#checkout_text` | — |
| **select** | `#urgency_type`, `name="urgency_type"` | ❌ No |
| **select** | `name="meyvc_discount_type"` | ❌ No |
| **select** | `name="meyvc_include_categories[]"`, `.meyvc-select-woo` | ✅ Yes |
| **select** | `name="meyvc_exclude_categories[]"`, `.meyvc-select-woo` | ✅ Yes |
| **select** | `name="meyvc_include_products[]"`, `.meyvc-select-woo.meyvc-select-products` | ✅ Yes |
| **select** | `name="meyvc_exclude_products[]"`, `.meyvc-select-woo.meyvc-select-products` | ✅ Yes |
| **select** | `name="meyvc_per_category_discount_cat[]"`, `.meyvc-select-woo.meyvc-per-cat-select` | ✅ Yes |
| **select** | `name="meyvc_generate_coupon_for_email"` | ❌ No |
| **select** | `#offer_banner_position`, `name="offer_banner_position"` | ❌ No |

**`admin/partials/meyvc-admin-tools.php`**

| Type | Selector / ID / class | SelectWoo? |
|------|------------------------|------------|
| input hidden | verify, page, action, meyvc_import | — |
| input file | `#meyvc-import-file`, `name="import_file"` | — |
| **select** | `#meyvc-export-campaign`, `name="campaign_id"`, `.regular-text` | ❌ No |

**`admin/partials/meyvc-admin-settings.php`**

| Type | Selector / ID / class | SelectWoo? |
|------|------------------------|------------|
| input checkbox | `enable_analytics`, `debug_mode`, etc. | — |
| **select** | `#meyvc-font-size-scale`, `name="font_size_scale"` | ❌ No |
| **select** | `#meyvc-font-family`, `name="font_family"` | ❌ No |
| **select** | `#meyvc-animation-speed`, `name="animation_speed"` | ❌ No |

**`admin/partials/meyvc-admin-ab-test-view.php`**

| Type | Selector / ID / class | SelectWoo? |
|------|------------------------|------------|
| input text | view/detail fields | — |
| input number | view/detail fields | — |

**`admin/partials/builder/targeting-controls.php`** (Select2, not SelectWoo)

| Type | Selector / ID / class | SelectWoo? |
|------|------------------------|------------|
| **select** | `#targeting-page-mode` | ❌ No (uses Select2 in JS) |
| **select** | `#targeting-specific-pages`, `.meyvc-select2` | ❌ No (Select2) |
| **select** | `#targeting-visitor-type` | ❌ No |
| **select** | `#targeting-cart-status` | ❌ No |
| **select** | `#targeting-cart-contains`, `#targeting-cart-category`, etc., `.meyvc-select2` | ❌ No (Select2) |
| **select** | `.meyvc-rule-field`, `.meyvc-rule-operator` | ❌ No |

**`admin/partials/builder/design-controls.php`**

| Type | Selector / ID / class | SelectWoo? |
|------|------------------------|------------|
| **select** | `#design-animation` | ❌ No |

**`admin/partials/builder/display-controls.php`**

| Type | Selector / ID / class | SelectWoo? |
|------|------------------------|------------|
| **select** | `#display-frequency` | ❌ No |
| **select** | `#display-frequency-period-unit` | ❌ No |
| **select** | `#display-brand-font-scale` | ❌ No |
| **select** | `#display-auto-pause` | ❌ No |
| **select** | `#display-after-conversion` | ❌ No |
| **select** | `#display-followup-campaign` | ❌ No |

**`admin/partials/builder/trigger-controls.php`**

| Type | Selector / ID / class | SelectWoo? |
|------|------------------------|------------|
| **select** | `#trigger-sensitivity` | ❌ No |

**`admin/partials/meyvc-admin-onboarding.php`**

| Type | Selector / ID / class | SelectWoo? |
|------|------------------------|------------|
| input hidden | `name="meyvc_onboarding_checklist"` | — |
| input checkbox | `feature_shipping_bar`, `feature_sticky_cart` | — |

**Classic editor modal (in `class-meyvc-admin.php`, not a partial)**

| Type | Selector / ID / class | SelectWoo? |
|------|------------------------|------------|
| **select** | `#meyvc-campaign-select` (Insert CRO Campaign) | ❌ No |

---

## 4) Selects: already have SelectWoo vs not

### Already using SelectWoo (class `meyvc-select-woo`)

- **meyvc-admin-offers.php:**  
  `#meyvc-drawer-include-categories`, `#meyvc-drawer-exclude-categories`, `#meyvc-drawer-include-products`, `#meyvc-drawer-exclude-products`, `#meyvc-drawer-cart-contains-category`, `#meyvc-drawer-allowed-roles`, `#meyvc-drawer-excluded-roles`, `#meyvc-drawer-apply-to-categories`, `#meyvc-drawer-apply-to-products`.
- **meyvc-admin-cart.php:**  
  `meyvc_include_categories[]`, `meyvc_exclude_categories[]`, `meyvc_include_products[]`, `meyvc_exclude_products[]`, `meyvc_per_category_discount_cat[]` (`.meyvc-select-woo`).

### Selects without SelectWoo (candidates for conversion or consistency)

- **meyvc-admin-offers.php:**  
  `#meyvc-drawer-reward-type`, `#meyvc-test-is-logged-in`, `#meyvc-test-user-role`
- **meyvc-admin-analytics.php:**  
  `#meyvc-campaign-filter`
- **meyvc-admin-campaign-edit.php:**  
  `#campaign_type`, `#campaign_status`, `#cart_status`, `#visitor_type`
- **meyvc-admin-campaign-builder.php:**  
  `#campaign-status`, `#content-tone`, `#content-cta-action`, `#content-countdown-type`
- **meyvc-admin-ab-test-new.php:**  
  `#campaign_id`, `#metric`, `#confidence_level`
- **meyvc-admin-boosters.php:**  
  `#sticky_cart_tone`, `#shipping_bar_tone`, `#shipping_bar_position`, `#stock_urgency_tone`
- **meyvc-admin-cart.php:**  
  `#urgency_type`, `meyvc_discount_type`, `meyvc_generate_coupon_for_email`, `#offer_banner_position`
- **meyvc-admin-tools.php:**  
  `#meyvc-export-campaign`
- **meyvc-admin-settings.php:**  
  `#meyvc-font-size-scale`, `#meyvc-font-family`, `#meyvc-animation-speed`
- **Builder partials:**  
  All selects in `targeting-controls.php`, `design-controls.php`, `display-controls.php`, `trigger-controls.php` use **Select2** (`.meyvc-select2` / `$().select2()` in `meyvc-campaign-builder.js`), not SelectWoo. Unify only if you standardize on SelectWoo for the whole admin.
- **class-meyvc-admin.php (modal):**  
  `#meyvc-campaign-select` (Add CRO Campaign in classic editor).

---

## 5) Checklist: files to update for UI consistency and SelectWoo conversion

Use this as a working checklist. “Add SelectWoo” = add class `meyvc-select-woo` and optional `data-placeholder` where appropriate; ensure page is under a CRO admin hook so `meyvc-select-woo-init.js` runs.

### High impact (single/dropdown selects that would benefit from SelectWoo)

- [ ] **admin/partials/meyvc-admin-offers.php**  
  - Add `meyvc-select-woo` (and placeholder) to: `#meyvc-drawer-reward-type`, `#meyvc-test-is-logged-in`, `#meyvc-test-user-role`.
- [ ] **admin/partials/meyvc-admin-analytics.php**  
  - Add `meyvc-select-woo` to `#meyvc-campaign-filter`.
- [ ] **admin/partials/meyvc-admin-tools.php**  
  - Add `meyvc-select-woo` to `#meyvc-export-campaign`.
- [ ] **admin/partials/meyvc-admin-settings.php**  
  - Add `meyvc-select-woo` to `#meyvc-font-size-scale`, `#meyvc-font-family`, `#meyvc-animation-speed`.
- [ ] **admin/class-meyvc-admin.php**  
  - Add `meyvc-select-woo` to `#meyvc-campaign-select` in the “Add CRO Campaign” thickbox modal (ensure SelectWoo is enqueued on post edit screen if you use it there, or keep native select).

### Medium impact (forms with several dropdowns)

- [ ] **admin/partials/meyvc-admin-boosters.php**  
  - Add `meyvc-select-woo` to: `#sticky_cart_tone`, `#shipping_bar_tone`, `#shipping_bar_position`, `#stock_urgency_tone`.
- [ ] **admin/partials/meyvc-admin-cart.php**  
  - Add `meyvc-select-woo` to: `#urgency_type`, `name="meyvc_discount_type"`, `meyvc_generate_coupon_for_email`, `#offer_banner_position`.
- [ ] **admin/partials/meyvc-admin-ab-test-new.php**  
  - Add `meyvc-select-woo` to: `#campaign_id`, `#metric`, `#confidence_level`.

### Lower priority / different context

- [ ] **admin/partials/meyvc-admin-campaign-builder.php**  
  - Add `meyvc-select-woo` to: `#campaign-status`, `#content-tone`, `#content-cta-action`, `#content-countdown-type` (only if you want SelectWoo in the visual builder; currently no SelectWoo there).
- [ ] **admin/partials/meyvc-admin-campaign-edit.php**  
  - If this partial is ever used again: add `meyvc-select-woo` to `#campaign_type`, `#campaign_status`, `#cart_status`, `#visitor_type`.

### Builder: Select2 vs SelectWoo

- [ ] **admin/partials/builder/*.php** and **admin/js/meyvc-campaign-builder.js**  
  - Decide: keep Select2 for builder only, or migrate `.meyvc-select2` to SelectWoo (class `meyvc-select-woo`, and swap `meyvc-campaign-builder.js` from `select2` to `selectWoo` and ensure SelectWoo is enqueued on `meyvc-campaign-edit`).  
  - If migrating: update `admin/partials/builder/targeting-controls.php`, `design-controls.php`, `display-controls.php`, `trigger-controls.php` and JS init.

### General UI consistency

- [ ] **admin/css/meyvc-admin-ui.css** (or relevant CSS)  
  - Ensure native `<select>` and SelectWoo-enhanced selects share consistent width, spacing, and focus styles where appropriate.
- [ ] **admin/js/meyvc-select-woo-init.js**  
  - No change needed for the above; already inits `.meyvc-select-woo`. Optionally add `data-placeholder` for new selects.
- [ ] **Enqueue**  
  - SelectWoo is enqueued for hooks containing `meyvc-`; post.php/post-new.php (classic editor modal) do not load it by default—add enqueue for that context if `#meyvc-campaign-select` gets `meyvc-select-woo`.

---

## Summary

- **17 admin slugs**; **16+ primary partials** (+ builder sub-partials; `meyvc-admin-campaign-edit.php` exists but is not used by the main menu).
- **Selects with SelectWoo:** Offers drawer (9) and Cart optimizer (5 multi-select + per-category row).
- **Selects without SelectWoo:** ~30+ across analytics, tools, settings, boosters, cart (single selects), campaign edit/builder, A/B test, and classic editor modal.
- **Builder:** Uses Select2 (`.meyvc-select2`); standardizing on SelectWoo would require JS and partial updates in builder partials.

Use the checklist above to add `meyvc-select-woo` and shared styling for a consistent admin UI and SelectWoo conversion.
