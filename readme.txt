=== Meyvora Convert – Conversion Rate Optimizer for WooCommerce ===

Contributors: niketthapa
Tags: woocommerce, conversion, popup, exit intent, abandoned cart
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
Requires Plugins: woocommerce
WC tested up to: 10.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Conversion rate optimization for WooCommerce: exit intent popups, sticky cart, shipping bar, dynamic offers, A/B testing, and analytics.

== Description ==

Meyvora Convert adds conversion-focused features to your WooCommerce store without bloat:

* **Conversion campaigns** – Exit intent and scroll-triggered popups to capture emails and offer coupons
* **On-page boosters** – Sticky add-to-cart, free shipping progress bar, trust badges, low-stock urgency
* **Cart optimizer** – Trust strip, urgency messaging, and optional offer banner on cart
* **Checkout optimizer** – Secure checkout badge, guarantee note, trust strip on checkout
* **Dynamic offers** – Rule-based personalized coupons (cart threshold, first-time/returning customer, lifetime spend, roles)
* **Blocks support** – All conversion elements render inside WooCommerce Cart and Checkout blocks (Gutenberg)
* **Classic support** – Same elements via hooks on classic shortcode cart/checkout
* **Editor support** – Insert campaigns via shortcode [meyvc_campaign id="123"] or the Gutenberg block "Meyvora Convert / Campaign"; Classic editor "Add Meyvora Convert Campaign" button

Performance-first: assets load only on WooCommerce and feature-relevant pages unless overridden by the `meyvc_should_enqueue_assets` filter. No "Pro" or upgrade prompts.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via WordPress admin → Plugins → Add New → Upload.
2. Activate "Meyvora Convert" from the Plugins screen.
3. Ensure WooCommerce is installed and active.
4. Go to Meyvora Convert in the admin menu to configure campaigns, boosters, cart/checkout settings, and offers.

== Third Party Services ==

This plugin optionally connects to the following external services. All
connections are opt-in and only made when you have explicitly enabled and
configured the relevant feature.

**Anthropic (Claude AI)**
Used when you enable AI features and enter an API key under Settings → AI.
Requests are sent to api.anthropic.com. The plugin sends store context you
choose (for example anonymous aggregate stats or campaign copy you type in
the UI). No bulk customer PII is sent automatically.
Privacy policy: https://www.anthropic.com/legal/privacy
Terms of service: https://www.anthropic.com/legal/aup

**Klaviyo**
Used when you enable the Klaviyo integration under Settings → Integrations.
When a visitor submits their email in a campaign popup, their email address
is sent to a.klaviyo.com to create or update a profile and subscribe them to
your chosen Klaviyo list. No data is sent until a visitor actively submits
their email.
Privacy policy: https://www.klaviyo.com/legal/privacy
Terms of service: https://www.klaviyo.com/legal/terms

**Mailchimp**
Used when you enable the Mailchimp integration under Settings → Integrations.
When a visitor submits their email in a campaign popup, their email address
is sent to your Mailchimp data centre (*.api.mailchimp.com) to subscribe them
to your chosen audience. No data is sent until a visitor actively submits
their email.
Privacy policy: https://mailchimp.com/legal/privacy/
Terms of service: https://mailchimp.com/legal/terms/

**DM Sans (bundled fonts)**
Used only when you enable "Load Google Fonts" under Settings → General
(disabled by default). When enabled, the plugin loads DM Sans from WOFF2
files shipped inside the plugin (no external font requests).
Font license: SIL Open Font License (see packages from https://github.com/fontsource/font-files).

**SortableJS** (bundled, no external connection)
Used in the admin campaign builder and sequences admin for drag-to-reorder.
Loaded locally from the plugin — no external requests.
Source and license: https://github.com/SortableJS/Sortable (MIT License)

== Source Code ==

This plugin uses compiled JavaScript for the WooCommerce Blocks checkout extension.
The uncompiled source code is available at:
https://github.com/niket-thapa/meyvora-convert

Build tools used: Node.js, npm, webpack (@wordpress/scripts)

To build: run `npm install && npm run build` inside `blocks/cart-checkout-extension/`

== Source Code and Build Tools (detail) ==

The compiled file `blocks/cart-checkout-extension/build/index.js` is
generated from the source files in `blocks/cart-checkout-extension/src/`
using `@wordpress/scripts` (webpack).

To rebuild:
1. Run `npm install` in the `blocks/cart-checkout-extension/` directory.
2. Run `npm run build` to generate production assets or `npm run start`
   for development with watch mode.

All other plugin files are plain PHP, JavaScript, and CSS with no build step.

== Frequently Asked Questions ==

= Does this plugin support WordPress Multisite? =

Yes, with per-site activation only. Activate Meyvora Convert on each site
individually from that site's Plugins page. Network-wide (bulk) activation
is blocked. Each site on the network gets its own isolated database tables,
campaigns, and settings.

= Does this work with WooCommerce Blocks (block-based cart/checkout)? =

Yes. The plugin registers an Integration with WooCommerce Blocks so trust strip, guarantee note, shipping progress, and offer banner render inside both Cart and Checkout block pages. Enable "Blocks debug mode" in Settings to confirm the extension is loaded (shows a small badge on cart/checkout).

= Can I use the classic shortcode cart and checkout? =

Yes. The same conversion elements (trust strip, shipping progress, offer banner, etc.) are rendered via WooCommerce hooks when you use the classic cart and checkout shortcodes.

= How do I show a campaign on a specific page? =

Use the shortcode `[meyvc_campaign id="123"]` with your campaign ID, or add the "Meyvora Convert / Campaign" block (Gutenberg) or use "Add Meyvora Convert Campaign" in the Classic editor and pick a campaign.

= Are generated offer coupons secure? =

Yes. Coupons use the format MYV-{offer_id}-{random6}, are single-use, and are rate-limited (one per visitor per offer per 6 hours). Admins and shop managers do not receive generated coupons.

== Screenshots ==

1. Dashboard overview — KPI cards showing conversions, revenue influenced, active A/B tests, and abandoned carts
2. Visual campaign builder with live template preview, trigger settings, and targeting rules
3. Cart optimizer — trust strip, free shipping progress bar, and urgency messaging
4. Checkout optimizer — secure checkout badge, guarantee note, and trust elements
5. Dynamic offers rule builder with cart threshold, customer type, and lifetime spend conditions
6. System Status panel — WooCommerce compatibility, DB table health, cron status, and conflict detection

== Privacy Policy ==

Meyvora Convert stores the following data to operate its features:

* **Visitor state cookie** (`meyvc_visitor_state`): stores which campaigns a visitor has seen or dismissed. Contains no personally identifiable information. Expires after 30 days.
* **Abandoned cart emails**: stored in the plugin database only when a visitor voluntarily submits their email address. Requires explicit consent before storage.
* **Analytics events**: anonymised impression and conversion events (campaign ID, page type, device type). IP addresses are only stored when full analytics tracking is enabled by the site owner, and can be further anonymised (last octet truncated) using the "Anonymise IP addresses" setting.
* **Klaviyo integration** (opt-in): when enabled, visitor email addresses submitted through campaign popups are transmitted to Klaviyo servers (a.klaviyo.com). See Klaviyo's privacy policy at https://www.klaviyo.com/legal/privacy.
* **Mailchimp integration** (opt-in): when enabled, visitor email addresses submitted through campaign popups are transmitted to Mailchimp servers (*.api.mailchimp.com). See Mailchimp's privacy policy at https://mailchimp.com/legal/privacy/.
* **AI features** (opt-in): when enabled, store context data entered by the site owner is transmitted to Anthropic servers (api.anthropic.com). No customer PII is sent automatically. See https://www.anthropic.com/legal/privacy.

Meyvora Convert supports WordPress's built-in personal data export and erasure tools (Tools → Export Personal Data / Erase Personal Data). Use these tools to export or erase any personal data stored in the plugin's database tables for a given email address.

== Changelog ==

= 1.0.0 =
* First public release — conversion campaigns (exit intent, scroll, time, spin-to-win wheel with server-signed tokens), boosters (sticky cart, shipping bar, trust badges, stock urgency, social proof, recommendations), cart/checkout optimizations, dynamic offers, A/B testing, abandoned cart email recovery, sequences, geo and UTM/referrer targeting, cookie-consent awareness, analytics and live dashboard panel, REST API, shortcode and block, Klaviyo/Mailchimp integrations, optional AI (Anthropic), onboarding and presets, System Status and uninstall options.

== Upgrade Notice ==

= 1.0.0 =
First public release of Meyvora Convert for WooCommerce.
