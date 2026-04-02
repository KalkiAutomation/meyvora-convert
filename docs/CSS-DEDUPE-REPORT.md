# CRO Toolkit – CSS Deduplication Report

## Summary

- **Duplicated selectors**: Layout (header, nav, content), cards, fields, tables, tabs, buttons, empty states, form controls appear in multiple files with overlapping or conflicting rules.
- **Single source of truth**: `admin/css/meyvc-admin-design-system.css` (new) holds tokens, layout, and shared components. Other admin CSS should only add page-specific overrides.
- **Conflicts**: `meyvc-admin.css`, `meyvc-admin-modern.css`, `meyvc-admin-ui.css`, and `meyvc-admin-brand-identity.css` all define header/content max-width, padding, and component styles; load order determines winner and causes brittle overrides.

---

## 1. Duplicated selectors

| Selector / pattern                                                              | Files                                                                | Recommendation                                                     |
| ------------------------------------------------------------------------------- | -------------------------------------------------------------------- | ------------------------------------------------------------------ |
| `:root` tokens (spacing, radius, shadow, colors, input height)                  | meyvc-admin-ui.css, meyvc-admin-modern.css, meyvc-admin-brand-identity.css | Keep in **meyvc-admin-design-system.css** only                       |
| `.meyvc-admin-layout__header`, `__header-inner`                                   | meyvc-admin-modern.css, meyvc-admin-ui.css, meyvc-admin-brand-identity.css | Design system only; use `.meyvc-admin-container` inside inner        |
| `.meyvc-admin-layout__nav`, `__nav-inner`, `__content-wrap`, `__content`          | meyvc-admin-modern.css, meyvc-admin-ui.css, meyvc-admin-brand-identity.css | Design system only                                                 |
| `.meyvc-card`, `.meyvc-card__header`, `.meyvc-card__body`                             | meyvc-admin-ui.css, meyvc-admin-modern.css, meyvc-admin-brand-identity.css | Design system only                                                 |
| `.meyvc-field`, `.meyvc-field__label`, `.meyvc-field__control`, `.meyvc-help`           | meyvc-admin-ui.css, meyvc-admin-modern.css, meyvc-admin-brand-identity.css | Design system only                                                 |
| `.meyvc-admin-layout__content input[...]:focus`, `select:focus`, `textarea:focus` | meyvc-admin-modern.css, meyvc-admin-brand-identity.css                   | Design system only; exclude `.select2-search__field`               |
| `.meyvc-ui-header`, `.meyvc-ui-header__title`, `__subtitle`, `__actions`            | meyvc-admin-ui.css, meyvc-admin-modern.css, meyvc-admin-brand-identity.css | Design system only                                                 |
| `.meyvc-ui-nav__list`, `.meyvc-ui-nav__link`, `.meyvc-ui-nav__link--active`           | meyvc-admin-modern.css, meyvc-admin-ui.css, meyvc-admin-brand-identity.css | Design system only                                                 |
| `.meyvc-modern-table`, `.widefat` in content                                      | meyvc-admin-modern.css, meyvc-admin-ui.css (meyvc-table)                   | Design system: one table style                                     |
| `.meyvc-empty-state` / `.meyvc-empty`                                               | meyvc-admin-modern.css, meyvc-admin-ui.css                               | Design system: `.meyvc-empty-state`                                  |
| `.meyvc-kpi`, `.meyvc-kpi__item`                                                    | meyvc-admin-ui.css, meyvc-admin.css (meyvc-stat-card)                      | Design system: KPI cards                                           |
| `max-width: 1200px` on page wrappers                                            | meyvc-admin.css, meyvc-offers.css                                        | Remove; use layout container max-width (1920px) from design system |
| Buttons (`.button-primary`, `.meyvc-ui-btn-primary`)                              | meyvc-admin-modern.css, meyvc-admin-ui.css                               | Design system only                                                 |

---

## 2. Conflicts

- **Header padding**: meyvc-admin-ui uses `padding-bottom: 23px`, meyvc-admin-modern uses `padding-top/bottom: var(--meyvc-modern-space-xl)`. **Resolution**: 8px grid in design system (e.g. 24px/32px).
- **Content border-radius**: Some files use `border-radius: 0 0 6px 6px` on content, others none. **Resolution**: Design system defines radius on nav + content as one strip.
- **Form control height**: 42px is set in modern and brand-identity; ui has no height. **Resolution**: 42px in design system; inputs/selects 42px, exclude Select2 search field from focus ring.

---

## 3. Where rules should live

| Area                                                                                  | File                                 | Contents                                                         |
| ------------------------------------------------------------------------------------- | ------------------------------------ | ---------------------------------------------------------------- |
| Tokens, layout, components, forms, tables, tabs, buttons, badges, empty states, toast | **meyvc-admin-design-system.css**      | All shared CRO admin UI                                          |
| Page max-width / legacy wrappers (minimal)                                            | **meyvc-admin.css**                    | Only if still needed for non-layout pages; else strip to minimal |
| SelectWoo height + z-index                                                            | **meyvc-admin-selectwoo-override.css** | No change (only z-index !important)                              |
| Offers: drawer, offer list, toast animations                                          | **meyvc-offers.css**                   | Offers-only; remove any duplicate layout/card/field rules        |
| Campaign builder: builder wrap, panels                                                | **meyvc-campaign-builder.css**         | Builder-only                                                     |
| Analytics: charts, date range                                                         | **meyvc-analytics.css**                | Analytics-only                                                   |

**meyvc-admin-modern.css / meyvc-admin-ui.css / meyvc-admin-brand-identity.css**: No longer enqueued; design system replaces them. Files can remain in repo for reference or be emptied to avoid accidental re-enqueue.

---

## 4. Enqueue order (after change)

1. `meyvc-admin-design-system.css` (global, all CRO admin pages)
2. `meyvc-admin.css` (minimal base if any)
3. SelectWoo (`select2`) when Woo present
4. `meyvc-admin-selectwoo-override.css` (after select2)
5. Page-specific: `meyvc-offers.css`, `meyvc-analytics.css`, `meyvc-campaign-builder.css` only on their pages

---

## 5. Specificity and !important

- **Avoid** new !important except: SelectWoo dropdown z-index in `meyvc-admin-selectwoo-override.css` (already present).
- Design system uses single class names (e.g. `.meyvc-admin-container`, `.meyvc-card`) so no high specificity needed.
- Remove inline styles from admin partials where possible; use classes from design system.
