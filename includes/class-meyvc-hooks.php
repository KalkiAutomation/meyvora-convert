<?php
/**
 * Meyvora Convert Hooks Reference
 *
 * Documents all action and filter hooks. Use these to extend functionality
 * without modifying core code.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MEYVC_Hooks class.
 *
 * Hook documentation and reference for developers.
 * Use get_hooks_documentation() for PHP actions/filters and get_js_events_documentation()
 * for JavaScript CustomEvents dispatched by the plugin.
 */
class MEYVC_Hooks {

	/**
	 * Get all documented PHP action and filter hooks.
	 *
	 * @return array Keys 'actions' and 'filters', each hook name => doc array.
	 */
	public static function get_hooks_documentation() {
		return array(

			'actions' => array(

				// Admin/UI.
				'meyvc_admin_before_page' => array(
					'description' => 'Fires before a CRO admin page content is rendered (after layout wrapper opens).',
					'params'      => array( '$page_slug' => 'Current admin page slug (e.g. meyvora-convert, meyvc-offers, meyvc-ab-tests).' ),
				),
				'meyvc_admin_after_page' => array(
					'description' => 'Fires after a CRO admin page content is rendered (before layout wrapper closes).',
					'params'      => array( '$page_slug' => 'Current admin page slug.' ),
				),
				'meyvc_offer_applied' => array(
					'description' => 'Fires when an offer coupon is applied (e.g. user applied the code).',
					'params'      => array( '$coupon_code', '$offer', '$context' ),
					'example'     => "add_action('meyvc_offer_applied', function(\$code, \$offer, \$context) {\n\t// Log or send to analytics\n}, 10, 3);",
				),
				'meyvc_offer_coupon_generated' => array(
					'description' => 'Fires when a coupon has been generated for an offer (single-use, rate-limited).',
					'params'      => array( '$coupon_code', '$offer', '$context' ),
				),
				'meyvc_abtest_created' => array(
					'description' => 'Fires when a new A/B test is created.',
					'params'      => array( '$test_id', '$data' => 'Submitted test data (name, campaign_id, metric, etc.).' ),
				),
				'meyvc_abtest_deleted' => array(
					'description' => 'Fires when an A/B test is deleted (variations and assignments removed).',
					'params'      => array( '$test_id' ),
				),
				'meyvc_frontend_before_render' => array(
					'description' => 'Fires before a frontend component is rendered (e.g. offer banner, checkout trust block).',
					'params'      => array( '$component' => 'Component identifier (e.g. offer_banner, checkout_trust).', '$context' => 'Array of context data.' ),
				),
				'meyvc_frontend_after_render' => array(
					'description' => 'Fires after a frontend component has been rendered.',
					'params'      => array( '$component', '$context' ),
				),

				// Campaign lifecycle.
				'meyvc_before_campaign_render' => array(
					'description' => 'Fires before a campaign popup is rendered.',
					'params'      => array( '$campaign', '$context' ),
					'example'     => "add_action('meyvc_before_campaign_render', function(\$campaign, \$context) {\n\t// Add custom tracking\n}, 10, 2);",
				),
				'meyvc_after_campaign_render' => array(
					'description' => 'Fires after a campaign popup is rendered.',
					'params'      => array( '$campaign', '$context' ),
				),
				'meyvc_campaign_impression' => array(
					'description' => 'Fires when a campaign impression is recorded (viewed).',
					'params'      => array( '$campaign_id', '$context' ),
				),
				'meyvc_campaign_builder_before' => array(
					'description' => 'Fires before the campaign builder UI is rendered (admin).',
					'params'      => array( '$campaign_id' => 'Current campaign ID or 0 for new.' ),
				),
				'meyvc_campaign_builder_after' => array(
					'description' => 'Fires after the campaign builder UI is rendered (admin).',
					'params'      => array( '$campaign_id' => 'Current campaign ID or 0 for new.' ),
				),
				'meyvc_campaign_shown' => array(
					'description' => 'Fires when a campaign is displayed to the user.',
					'params'      => array( '$campaign_id', '$visitor_id' ),
				),
				'meyvc_campaign_dismissed' => array(
					'description' => 'Fires when the user dismisses a campaign.',
					'params'      => array( '$campaign_id', '$visitor_id' ),
				),
				'meyvc_campaign_converted' => array(
					'description' => 'Fires when the user converts on a campaign.',
					'params'      => array( '$campaign_id', '$visitor_id', '$conversion_type' ),
				),

				// Email capture.
				'meyvc_email_captured' => array(
					'description' => 'Fires when an email is captured.',
					'params'      => array( '$email', '$campaign_id', '$context' ),
					'example'     => "add_action('meyvc_email_captured', function(\$email, \$campaign_id) {\n\t// Send to Mailchimp\n\tmailchimp_subscribe(\$email);\n}, 10, 2);",
				),

				// Coupon.
				'meyvc_coupon_displayed' => array(
					'description' => 'Fires when a coupon is shown in a popup.',
					'params'      => array( '$coupon_code', '$campaign_id' ),
				),
				'meyvc_coupon_applied' => array(
					'description' => 'Fires when a CRO coupon is applied to the cart.',
					'params'      => array( '$coupon_code', '$campaign_id' ),
				),

				// Analytics / Tracking.
				'meyvc_impression_tracked' => array(
					'description' => 'Fires after an impression is tracked.',
					'params'      => array( '$campaign_id', '$visitor_id' ),
				),
				'meyvc_conversion_tracked' => array(
					'description' => 'Fires after a conversion is tracked.',
					'params'      => array( '$campaign_id', '$visitor_id', '$order_id' ),
				),
				'meyvc_event_tracked' => array(
					'description' => 'Fires after any event is stored (impression, conversion, dismiss, interaction). Also invalidates attribution cache.',
					'params'      => array( '$event' => 'Array with event_type, source_type, source_id, etc.' ),
					'example'     => "add_action('meyvc_event_tracked', function(\$event) {\n\t// Forward to GA or CRM\n}, 10, 1);",
				),
				'meyvc_offer_log_inserted' => array(
					'description' => 'Fires after an offer log entry is inserted (audit / apply). Invalidates attribution cache.',
					'params'      => array( '$offer_id' => 'Offer ID that was logged.' ),
				),
				'meyvc_ab_conversion_recorded' => array(
					'description' => 'Fires after an A/B test variation conversion is recorded. Invalidates attribution cache.',
					'params'      => array( '$variation_id' => 'Variation ID.', '$revenue' => 'Revenue amount.' ),
				),
				'meyvc_ab_test_winner_applied' => array(
					'description' => 'Fires after a winning A/B variation is merged into the original live campaign (apply winner).',
					'params'      => array( '$test_id' => 'A/B test ID.', '$variation_id' => 'Winning variation row ID.', '$original_campaign_id' => 'Campaign ID that was updated.' ),
				),
			),

			'filters' => array(

				// Admin/UI.
				'meyvc_admin_tabs' => array(
					'description' => 'Filter the admin tab/nav items (slug => label, url) shown on every CRO admin page.',
					'params'      => array( '$tabs' => 'Array of tab slug => array( label, url ).' ),
					'return'      => '$tabs',
				),
				'meyvc_admin_selectwoo_options' => array(
					'description' => 'Filter SelectWoo options (e.g. when outputting data-meyvc-selectwoo-opts JSON on a select). Context: select_id, select_name, in_drawer.',
					'params'      => array( '$opts' => 'Options object for SelectWoo.', '$context' => 'Array with select_id, select_name, in_drawer.' ),
					'return'      => '$opts',
				),
				'meyvc_admin_primary_action' => array(
					'description' => 'Filter the primary action (e.g. "Add Offer", "New A/B Test") for the current admin page.',
					'params'      => array( '$action' => 'Array with label and href/form_id/button_id.', '$page_slug' => 'Current page slug.' ),
					'return'      => '$action',
				),

				// Offers.
				'meyvc_offer_context' => array(
					'description' => 'Filter the offer evaluation context (cart, visitor, user, etc.) before rules are evaluated.',
					'params'      => array( '$context' ),
					'return'      => '$context',
				),
				'meyvc_offer_rules' => array(
					'description' => 'Filter the registered offer rule evaluators (rule_key => callable).',
					'params'      => array( '$rules' ),
					'return'      => '$rules',
				),
				'meyvc_offer_best_offer' => array(
					'description' => 'Filter the best matching offer (or null) after evaluation.',
					'params'      => array( '$offer', '$context' ),
					'return'      => '$offer',
				),
				'meyvc_offer_coupon_args' => array(
					'description' => 'Filter the coupon arguments used when creating a coupon for an offer (code, discount_type, amount, expiry, etc.).',
					'params'      => array( '$args', '$offer', '$context' ),
					'return'      => '$args',
				),
				'meyvc_offer_attribution_logic' => array(
					'description' => 'Filter offer conversion attribution when an order completes. Use to add/remove offer_ids or change revenue. Default: offer_ids from CRO coupons used on the order and from offer_logs linked to the order.',
					'params'      => array(
						'$logic'   => 'Array with keys: offer_ids (int[]), revenue (float).',
						'$context' => 'Array: order_id, order (WC_Order), coupon_codes, offer_ids_from_coupons, offer_ids_from_logs.',
					),
					'return'     => '$logic (array with offer_ids and revenue)',
				),

				// A/B Tests.
				'meyvc_ab_exposure' => array(
					'description' => 'Fires when a visitor is exposed to an A/B test variant.',
					'params'      => array( '$test_id', '$variant', '$context' ),
				),
				'meyvc_ab_variant' => array(
					'description' => 'Filter the A/B test variant shown to the visitor.',
					'params'      => array( '$variant', '$test_id', '$context' ),
					'return'      => '$variant',
				),
				'meyvc_abtest_variant_assignment' => array(
					'description' => 'Filter the assigned variation for a visitor in an A/B test (override default weighted random).',
					'params'      => array( '$variant' => 'Variation object or null.', '$test_id', '$visitor_id' ),
					'return'      => '$variant',
				),

				// Frontend.
				'meyvc_should_enqueue_assets' => array(
					'description' => 'Filter whether to enqueue CRO frontend assets (scripts/styles) for the given context.',
					'params'      => array( '$bool', '$context' => 'e.g. campaigns, cart, checkout, default.' ),
					'return'      => 'bool',
				),
				'meyvc_consent_allows_popup' => array(
					'description' => 'Filter whether a visitor has given consent to show popups and set the visitor state cookie. Return false to suppress all popups. MEYVC_Consent hooks this when “Cookie consent required” is enabled in Settings.',
					'params'      => array( '$allow' => 'bool — default true. Return false to suppress.' ),
					'example'     => "add_filter('meyvc_consent_allows_popup', function( \$allow ) {\n\treturn my_cmp_has_marketing_consent() ? true : false;\n});",
				),

				// Targeting.
				'meyvc_targeting_rules' => array(
					'description' => 'Modify targeting rules before evaluation.',
					'params'     => array( '$rules', '$campaign', '$context' ),
					'return'     => '$rules',
					'example'    => "add_filter('meyvc_targeting_rules', function(\$rules, \$campaign, \$context) {\n\t\$rules['custom_condition'] = check_something();\n\treturn \$rules;\n}, 10, 3);",
				),
				'meyvc_should_show_campaign' => array(
					'description' => 'Final filter to allow or block campaign display.',
					'params'      => array( '$should_show', '$campaign', '$context' ),
					'return'      => 'bool',
				),
				'meyvc_decision_active_campaigns' => array(
					'description' => 'Filter the list of active campaign models before priority/rules (e.g. sequences prepend a due follow-up campaign).',
					'params'      => array( '$campaigns' => 'Array of MEYVC_Campaign_Model instances.', '$context' => 'MEYVC_Context instance.', '$visitor_state' => 'MEYVC_Visitor_State instance.' ),
					'return'      => '$campaigns',
				),

				// Content / Campaign render.
				'meyvc_campaign_render_html' => array(
					'description' => 'Filter the final HTML output for a campaign popup.',
					'params'      => array( '$html', '$campaign', '$context' ),
					'return'      => '$html',
				),
				'meyvc_campaign_available_templates' => array(
					'description' => 'Filter the list of available campaign templates (template key => label, preview_image, etc.).',
					'params'      => array( '$templates' => 'Array of template definitions.' ),
					'return'      => '$templates',
				),
				'meyvc_campaign_preview_html' => array(
					'description' => 'Filter the campaign preview HTML (e.g. in admin preview iframe).',
					'params'      => array( '$html', '$campaign' => 'Campaign data array or object.' ),
					'return'      => '$html',
				),
				'meyvc_campaign_content' => array(
					'description' => 'Modify campaign content before render.',
					'params'     => array( '$content', '$campaign' ),
					'return'     => '$content',
				),
				'meyvc_popup_template' => array(
					'description' => 'Change popup template file path.',
					'params'     => array( '$template_path', '$template_type' ),
					'return'     => '$template_path',
				),

				// Coupon logic.
				'meyvc_can_offer_coupon' => array(
					'description' => 'Filter whether a coupon can be offered.',
					'params'     => array( '$can_offer', '$coupon_code', '$context' ),
					'return'     => 'bool',
				),
				'meyvc_max_discount_percent' => array(
					'description' => 'Maximum discount percentage allowed.',
					'params'     => array( '$max_percent', '$cart_total' ),
					'return'     => 'int',
				),

				// Timing.
				'meyvc_dismissal_cooldown' => array(
					'description' => 'Cooldown period after dismiss (seconds).',
					'params'     => array( '$cooldown', '$campaign' ),
					'return'     => 'int',
				),
				'meyvc_conversion_suppression_window' => array(
					'description' => 'Suppression window after conversion (seconds).',
					'params'     => array( '$window' ),
					'return'     => 'int',
				),

				// Analytics.
				'meyvc_should_track' => array(
					'description' => 'Filter whether to track this request.',
					'params'     => array( '$should_track', '$event_type' ),
					'return'     => 'bool',
				),

				// Display.
				'meyvc_popup_classes' => array(
					'description' => 'Add custom CSS classes to the popup.',
					'params'     => array( '$classes', '$campaign' ),
					'return'     => 'array',
				),
				'meyvc_popup_styles' => array(
					'description' => 'Add inline styles to the popup.',
					'params'     => array( '$styles', '$campaign' ),
					'return'     => 'string',
				),
			),
		);
	}

	/**
	 * Get documented JavaScript CustomEvents dispatched by the plugin.
	 *
	 * Events are dispatched on document. Listen with document.addEventListener('meyvc:event_name', fn).
	 *
	 * @return array Event name => array( description, detail_keys, example ).
	 */
	public static function get_js_events_documentation() {
		return array(
			'meyvc:campaign_shown'   => array(
				'description' => 'Fires when a campaign popup is displayed.',
				'detail'      => array( 'campaignId', 'campaignName', 'templateType' ),
				'example'     => "document.addEventListener('meyvc:campaign_shown', function(e) {\n\tgtag('event', 'meyvc_popup_shown', { campaign_id: e.detail.campaignId });\n});",
			),
			'meyvc:campaign_dismissed' => array(
				'description' => 'Fires when the user closes a campaign popup.',
				'detail'      => array( 'campaignId' ),
				'example'     => "document.addEventListener('meyvc:campaign_dismissed', function(e) {\n\tconsole.log('Dismissed:', e.detail.campaignId);\n});",
			),
			'meyvc:campaign_converted' => array(
				'description' => 'Fires when the user converts (e.g. clicks CTA).',
				'detail'      => array( 'campaignId', 'conversionType', 'timestamp' ),
				'example'     => "document.addEventListener('meyvc:campaign_converted', function(e) {\n\tgtag('event', 'meyvc_conversion', { campaign_id: e.detail.campaignId });\n});",
			),
			'meyvc:email_captured'   => array(
				'description' => 'Fires when an email is submitted in a popup form.',
				'detail'      => array( 'email', 'campaignId' ),
				'example'     => "document.addEventListener('meyvc:email_captured', function(e) {\n\tconsole.log('Email:', e.detail.email);\n});",
			),
			'meyvc:coupon_copied'    => array(
				'description' => 'Fires when the user triggers copy-coupon in a popup.',
				'detail'      => array( 'couponCode', 'campaignId' ),
				'example'     => "document.addEventListener('meyvc:coupon_copied', function(e) {\n\tconsole.log('Copied:', e.detail.couponCode);\n});",
			),
		);
	}
}
