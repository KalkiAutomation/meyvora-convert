=== Meyvora Convert ===

Contributors: (your-username)
Tags: woocommerce, conversion, popup, exit intent, abandoned cart, sticky cart, shipping bar, trust badges, A/B testing, analytics
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight conversion rate optimization toolkit for WooCommerce: exit intent popups, sticky add-to-cart, free shipping bar, cart and checkout optimizers, dynamic offers, and trust elements. Works with block-based and classic cart/checkout.

== Description ==

Meyvora Convert adds conversion-focused features to your WooCommerce store without bloat:

* **Conversion campaigns** – Exit intent and scroll-triggered popups to capture emails and offer coupons
* **On-page boosters** – Sticky add-to-cart, free shipping progress bar, trust badges, low-stock urgency
* **Cart optimizer** – Trust strip, urgency messaging, and optional offer banner on cart
* **Checkout optimizer** – Secure checkout badge, guarantee note, trust strip on checkout
* **Dynamic offers** – Rule-based personalized coupons (cart threshold, first-time/returning customer, lifetime spend, roles)
* **Blocks support** – All CRO elements render inside WooCommerce Cart and Checkout blocks (Gutenberg)
* **Classic support** – Same elements via hooks on classic shortcode cart/checkout
* **Editor support** – Insert campaigns via shortcode [cro_campaign id="123"] or the Gutenberg block "Meyvora Convert / Campaign"; Classic editor "Add CRO Campaign" button

Performance-first: assets load only on WooCommerce and feature-relevant pages unless overridden by the `cro_should_enqueue_assets` filter. No "Pro" or upgrade prompts.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via WordPress admin → Plugins → Add New → Upload.
2. Activate "Meyvora Convert" from the Plugins screen.
3. Ensure WooCommerce is installed and active.
4. Go to Meyvora Convert in the admin menu to configure campaigns, boosters, cart/checkout settings, and offers.

== Frequently Asked Questions ==

= Does this plugin support WordPress Multisite? =

Yes, with per-site activation only. Activate Meyvora Convert on each site
individually from that site's Plugins page. Network-wide (bulk) activation
is blocked. Each site on the network gets its own isolated database tables,
campaigns, and settings.

= Does this work with WooCommerce Blocks (block-based cart/checkout)? =

Yes. The plugin registers an Integration with WooCommerce Blocks so trust strip, guarantee note, shipping progress, and offer banner render inside both Cart and Checkout block pages. Enable "Blocks debug mode" in Settings to confirm the extension is loaded (shows a small badge on cart/checkout).

= Can I use the classic shortcode cart and checkout? =

Yes. The same CRO elements (trust strip, shipping progress, offer banner, etc.) are rendered via WooCommerce hooks when you use the classic cart and checkout shortcodes.

= How do I show a campaign on a specific page? =

Use the shortcode `[cro_campaign id="123"]` with your campaign ID, or add the "Meyvora Convert / Campaign" block (Gutenberg) or use "Add CRO Campaign" in the Classic editor and pick a campaign.

= Are generated offer coupons secure? =

Yes. Coupons use the format CRO-{offer_id}-{random6}, are single-use, and are rate-limited (one per visitor per offer per 6 hours). Admins and shop managers do not receive generated coupons.

== Screenshots ==

1. Dashboard overview — KPI cards showing conversions, revenue influenced, active A/B tests, and abandoned carts
2. Visual campaign builder with live template preview, trigger settings, and targeting rules
3. Cart optimizer — trust strip, free shipping progress bar, and urgency messaging
4. Checkout optimizer — secure checkout badge, guarantee note, and trust elements
5. Dynamic offers rule builder with cart threshold, customer type, and lifetime spend conditions
6. System Status panel — WooCommerce compatibility, DB table health, cron status, and conflict detection

== Privacy Policy ==

Meyvora Convert stores the following data to operate its features:

* Visitor state cookie (`cro_visitor_state`): stores which campaigns a visitor has seen or dismissed. Contains no personally identifiable information. Expires after 30 days.
* Abandoned cart emails: stored in the database only when a visitor voluntarily submits their email address. Requires explicit consent before storage.
* Analytics events: anonymized impression and conversion events (campaign ID, page type, device type). IP addresses are only stored if full analytics tracking is enabled by the site owner.

Meyvora Convert supports WordPress's built-in personal data export and erasure tools (Tools → Export Personal Data / Erase Personal Data).

== Changelog ==

= 1.0.0 =
* Initial release.
* Conversion campaigns (exit intent, scroll, time trigger).
* On-page boosters: sticky cart, shipping bar, trust badges, stock urgency.
* Cart and checkout optimizers (trust strip, guarantee, shipping progress).
* WooCommerce Blocks integration (Cart and Checkout blocks).
* Classic cart/checkout support.
* Dynamic offers with rule builder and coupon generation (CRO-{id}-{random6}, rate limit, TTL).
* REST API: GET/POST offer, apply coupon.
* Shortcode [cro_campaign], Gutenberg block, Classic editor insert button.
* System Status, Verify Install Package, Repair Database Tables, self-heal missing tables.
* Quick Launch (recommended setup in one click).
* Uninstall option to remove all data.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
