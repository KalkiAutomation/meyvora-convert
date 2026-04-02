# CRO Toolkit – QA Checklist

Use this list to verify the plugin after changes or before release.

## Layout & design

- [ ] **Admin layout consistent** – Every CRO admin page (Dashboard, Offers, Campaigns, Analytics, Insights, Settings, etc.) shows the same header + horizontal tabs + content area.
- [ ] **Header/nav/content aligned** – Header inner, nav inner, and content use `.meyvc-admin-container`; same max-width (1920px) and horizontal padding (24px) on all pages.
- [ ] **Full width** – No cramped content; layout uses full available width up to the container max.
- [ ] **8px grid** – Spacing (margins, padding) follows 8px multiples where applicable.

## Forms & controls

- [ ] **Input/select height** – All text inputs and selects in `.meyvc-admin-layout__content` are 42px tall (except Select2 search field).
- [ ] **Focus ring** – Inputs, selects, textareas show a clear focus ring; Select2 `.select2-search__field` is excluded from custom focus styling.
- [ ] **Labels and help text** – Form fields use `.meyvc-field__label` and `.meyvc-help`; no inline styles for layout.

## Drawer & SelectWoo

- [ ] **Offer drawer alignment** – Offer builder drawer uses 12-col grid (e.g. `.meyvc-grid`, `.meyvc-col-6`, `.meyvc-col-12`); fields and labels align.
- [ ] **SelectWoo in drawer** – Selects inside the offer drawer use SelectWoo with `dropdownParent` so the dropdown is not clipped; z-index is correct (e.g. 1000001 for drawer).
- [ ] **SelectWoo on normal pages** – All CRO admin pages load SelectWoo; any select with class `.meyvc-selectwoo` initializes as SelectWoo.
- [ ] **SelectWoo after drawer open** – If a select is inside a drawer/modal, it is initialized after the drawer opens (so dropdownParent is the drawer).

## Campaign builder

- [ ] **Builder loads** – On campaign edit page (`meyvc-campaign-edit`) and any page slug in `meyvc-campaign-edit` / `meyvc-campaign-builder`, campaign builder CSS and JS are enqueued.
- [ ] **Preview works** – Campaign preview (desktop/mobile) opens and shows the correct template.
- [ ] **Campaigns in frontend** – Campaigns render correctly with Classic (shortcode) and Block-based cart/checkout when enabled.

## Tracking & Insights

- [ ] **Events record** – Impressions and conversions (and other event types) are stored in `meyvc_events` when campaigns/boosters are used.
- [ ] **Insights tab** – The Insights admin tab shows up to 6 actionable cards when data exists; empty state shows when there is no data.
- [ ] **Insight “Fix” links** – Each insight card’s “Fix” CTA links to the correct admin page (Offers, Campaigns, Boosters).

## Verify Installation (Tools / System Status)

- [ ] **Required tables** – Verify Installation reports all CRO tables present (or creates them).
- [ ] **SelectWoo assets** – Check reports WooCommerce SelectWoo CSS/JS when Woo is active.
- [ ] **Campaign builder assets** – Check reports campaign builder CSS/JS files present.
- [ ] **Blocks build** – Check reports `blocks/cart-checkout-extension/build/index.js` and `index.asset.php` when applicable.

## Hooks & Developer tab

- [ ] **Developer tab** – Lists all documented actions and filters with parameters and examples.
- [ ] **meyvc_event_tracked** – Fires after each event is stored (can be verified by adding a test action).
- [ ] **meyvc_admin_tabs** – Filtering `meyvc_admin_tabs` changes the horizontal nav items.

## Icons

- [ ] **Lucide icons** – Admin and frontend use `MEYVC_Icons::svg()` where applicable; no emoji for core UI (optional: replace remaining emoji in partials).
- [ ] **.meyvc-ico** – Inline SVG icons have consistent size (e.g. `.meyvc-ico--sm`, `.meyvc-ico--md`, `.meyvc-ico--lg`).

## Quick test flow

1. Open **Dashboard** → confirm header, tabs, one container alignment.
2. Open **Offers** → add/edit offer in drawer → confirm SelectWoo in drawer and grid alignment.
3. Open **Campaigns** → edit a campaign → confirm builder loads and preview works.
4. Open **Insights** → confirm empty state or insight cards with Fix links.
5. Open **System Status** → run **Verify Installation** → confirm all checks pass or show expected messages.
6. Open **Developer** → confirm hooks list and examples render.
