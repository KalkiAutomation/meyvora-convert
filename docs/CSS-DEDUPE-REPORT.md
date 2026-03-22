# CRO Toolkit – CSS Deduplication Report

## Summary

- **Duplicated selectors**: Layout (header, nav, content), cards, fields, tables, tabs, buttons, empty states, form controls appear in multiple files with overlapping or conflicting rules.
- **Single source of truth**: `admin/css/cro-admin-design-system.css` (new) holds tokens, layout, and shared components. Other admin CSS should only add page-specific overrides.
- **Conflicts**: `cro-admin.css`, `cro-admin-modern.css`, `cro-admin-ui.css`, and `cro-admin-brand-identity.css` all define header/content max-width, padding, and component styles; load order determines winner and causes brittle overrides.

---

## 1. Duplicated selectors

| Selector / pattern                                                              | Files                                                                | Recommendation                                                     |
| ------------------------------------------------------------------------------- | -------------------------------------------------------------------- | ------------------------------------------------------------------ |
| `:root` tokens (spacing, radius, shadow, colors, input height)                  | cro-admin-ui.css, cro-admin-modern.css, cro-admin-brand-identity.css | Keep in **cro-admin-design-system.css** only                       |
| `.cro-admin-layout__header`, `__header-inner`                                   | cro-admin-modern.css, cro-admin-ui.css, cro-admin-brand-identity.css | Design system only; use `.cro-admin-container` inside inner        |
| `.cro-admin-layout__nav`, `__nav-inner`, `__content-wrap`, `__content`          | cro-admin-modern.css, cro-admin-ui.css, cro-admin-brand-identity.css | Design system only                                                 |
| `.cro-card`, `.cro-card__header`, `.cro-card__body`                             | cro-admin-ui.css, cro-admin-modern.css, cro-admin-brand-identity.css | Design system only                                                 |
| `.cro-field`, `.cro-field__label`, `.cro-field__control`, `.cro-help`           | cro-admin-ui.css, cro-admin-modern.css, cro-admin-brand-identity.css | Design system only                                                 |
| `.cro-admin-layout__content input[...]:focus`, `select:focus`, `textarea:focus` | cro-admin-modern.css, cro-admin-brand-identity.css                   | Design system only; exclude `.select2-search__field`               |
| `.cro-ui-header`, `.cro-ui-header__title`, `__subtitle`, `__actions`            | cro-admin-ui.css, cro-admin-modern.css, cro-admin-brand-identity.css | Design system only                                                 |
| `.cro-ui-nav__list`, `.cro-ui-nav__link`, `.cro-ui-nav__link--active`           | cro-admin-modern.css, cro-admin-ui.css, cro-admin-brand-identity.css | Design system only                                                 |
| `.cro-modern-table`, `.widefat` in content                                      | cro-admin-modern.css, cro-admin-ui.css (cro-table)                   | Design system: one table style                                     |
| `.cro-empty-state` / `.cro-empty`                                               | cro-admin-modern.css, cro-admin-ui.css                               | Design system: `.cro-empty-state`                                  |
| `.cro-kpi`, `.cro-kpi__item`                                                    | cro-admin-ui.css, cro-admin.css (cro-stat-card)                      | Design system: KPI cards                                           |
| `max-width: 1200px` on page wrappers                                            | cro-admin.css, cro-offers.css                                        | Remove; use layout container max-width (1920px) from design system |
| Buttons (`.button-primary`, `.cro-ui-btn-primary`)                              | cro-admin-modern.css, cro-admin-ui.css                               | Design system only                                                 |

---

## 2. Conflicts

- **Header padding**: cro-admin-ui uses `padding-bottom: 23px`, cro-admin-modern uses `padding-top/bottom: var(--cro-modern-space-xl)`. **Resolution**: 8px grid in design system (e.g. 24px/32px).
- **Content border-radius**: Some files use `border-radius: 0 0 6px 6px` on content, others none. **Resolution**: Design system defines radius on nav + content as one strip.
- **Form control height**: 42px is set in modern and brand-identity; ui has no height. **Resolution**: 42px in design system; inputs/selects 42px, exclude Select2 search field from focus ring.

---

## 3. Where rules should live

| Area                                                                                  | File                                 | Contents                                                         |
| ------------------------------------------------------------------------------------- | ------------------------------------ | ---------------------------------------------------------------- |
| Tokens, layout, components, forms, tables, tabs, buttons, badges, empty states, toast | **cro-admin-design-system.css**      | All shared CRO admin UI                                          |
| Page max-width / legacy wrappers (minimal)                                            | **cro-admin.css**                    | Only if still needed for non-layout pages; else strip to minimal |
| SelectWoo height + z-index                                                            | **cro-admin-selectwoo-override.css** | No change (only z-index !important)                              |
| Offers: drawer, offer list, toast animations                                          | **cro-offers.css**                   | Offers-only; remove any duplicate layout/card/field rules        |
| Campaign builder: builder wrap, panels                                                | **cro-campaign-builder.css**         | Builder-only                                                     |
| Analytics: charts, date range                                                         | **cro-analytics.css**                | Analytics-only                                                   |

**cro-admin-modern.css / cro-admin-ui.css / cro-admin-brand-identity.css**: No longer enqueued; design system replaces them. Files can remain in repo for reference or be emptied to avoid accidental re-enqueue.

---

## 4. Enqueue order (after change)

1. `cro-admin-design-system.css` (global, all CRO admin pages)
2. `cro-admin.css` (minimal base if any)
3. SelectWoo (`select2`) when Woo present
4. `cro-admin-selectwoo-override.css` (after select2)
5. Page-specific: `cro-offers.css`, `cro-analytics.css`, `cro-campaign-builder.css` only on their pages

---

## 5. Specificity and !important

- **Avoid** new !important except: SelectWoo dropdown z-index in `cro-admin-selectwoo-override.css` (already present).
- Design system uses single class names (e.g. `.cro-admin-container`, `.cro-card`) so no high specificity needed.
- Remove inline styles from admin partials where possible; use classes from design system.
