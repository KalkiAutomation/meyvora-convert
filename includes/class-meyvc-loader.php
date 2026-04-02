<?php
/**
 * The file that defines the core plugin class
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The core plugin class.
 */
class MEYVC_Loader {

	/**
	 * The loader that's responsible for maintaining and registering all hooks.
	 *
	 * @var MEYVC_Loader
	 */
	protected $loader;

	/**
	 * Define the core functionality of the plugin.
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_campaign_hooks();
		$this->define_booster_hooks();
		$this->define_cart_hooks();
		$this->define_checkout_hooks();
		$this->define_offer_hooks();
		$this->define_analytics_hooks();
		$this->define_blocks_integration();
		$this->define_woo_ab_conversion_hooks();
		$this->define_abandoned_cart_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 */
	private function load_dependencies() {
		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-activator.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-deactivator.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-settings.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-attribution.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-system-status.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-default-copy.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-database.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-validator.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-edge-cases.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-cache.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-lazy-loader.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-query-optimizer.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-background-processor.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-resource-manager.php';

		// Preset library (used by admin Presets page)
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-presets.php';
		// Quick Launch (recommended setup in one click)
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-quick-launch.php';

		// Campaign classes
		require_once MEYVC_PLUGIN_DIR . 'includes/campaigns/class-meyvc-campaign.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/campaigns/class-meyvc-campaign-display.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/campaigns/class-meyvc-targeting.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/campaigns/class-meyvc-tracker.php';

		// Shortcodes (campaign render)
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-shortcodes.php';

		// Decision engine and visitor state are loaded in the main plugin file (meyvora-convert.php).

		// Offer guard (coupon and offer protection)
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-offer-guard.php';

		// Theme and plugin compatibility
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-compatibility.php';

		// Security utilities
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-security.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-consent.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-recommendations.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-geo.php';

		// Hooks reference (documentation only)
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-hooks.php';

		// UX protection rules
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-ux-rules.php';

		// Booster classes
		require_once MEYVC_PLUGIN_DIR . 'includes/boosters/class-meyvc-sticky-cart.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/boosters/class-meyvc-shipping-bar.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/boosters/class-meyvc-trust-badges.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/boosters/class-meyvc-stock-urgency.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/boosters/class-meyvc-social-proof.php';

		// Cart classes
		require_once MEYVC_PLUGIN_DIR . 'includes/cart/class-meyvc-cart-optimizer.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/cart/class-meyvc-cart-messages.php';

		// Abandoned cart tracking
		require_once MEYVC_PLUGIN_DIR . 'includes/abandoned/class-meyvc-abandoned-cart-tracker.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/abandoned/class-meyvc-abandoned-cart-email-capture.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/abandoned/class-meyvc-abandoned-cart-reminder.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/abandoned/class-meyvc-abandoned-cart-coupon.php';

		// Checkout classes
		require_once MEYVC_PLUGIN_DIR . 'includes/checkout/class-meyvc-checkout-optimizer.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/checkout/class-meyvc-checkout-fields.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/checkout/class-meyvc-post-purchase.php';

		// Sequences + ESP integrations
		require_once MEYVC_PLUGIN_DIR . 'includes/sequences/class-meyvc-sequence-engine.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/integrations/class-meyvc-klaviyo.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/integrations/class-meyvc-mailchimp.php';

		// Classic cart/checkout asset loader (trust, offer banner, shipping – assets only when needed)
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-classic-cart-checkout.php';

		// Analytics classes
		require_once MEYVC_PLUGIN_DIR . 'includes/analytics/class-meyvc-analytics.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/analytics/class-meyvc-revenue-tracker.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/analytics/class-meyvc-offer-attribution.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/analytics/class-meyvc-analytics-filter.php';

		// Insights (rule-based recommendations)
		require_once MEYVC_PLUGIN_DIR . 'includes/insights/class-meyvc-insights.php';

		// A/B Testing classes
		require_once MEYVC_PLUGIN_DIR . 'includes/ab-testing/class-meyvc-ab-test.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/ab-testing/class-meyvc-ab-statistics.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/ab-testing/class-meyvc-woo-ab-conversion.php';

		// Frontend asset loading
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-frontend.php';

		// UI (icons for admin and frontend)
		require_once MEYVC_PLUGIN_DIR . 'includes/ui/class-meyvc-icons.php';

		// Templates helper (used by popup templates)
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-templates.php';

		// Placeholders (used by popup templates for {cart_total}, etc.)
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-placeholders.php';

		// Offers (model + tables: meyvc_offers, meyvc_offer_logs)
		require_once MEYVC_PLUGIN_DIR . 'includes/offers/class-meyvc-offer-model.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/offers/class-meyvc-offer-rules.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/offers/class-meyvc-offer-engine.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/offers/class-meyvc-offer-banner.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/offers/class-meyvc-offer-presenter.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/offers/class-meyvc-offer-schema.php';

		// REST API
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-rest-api.php';

		// WooCommerce Blocks (Gutenberg) cart/checkout integration
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-blocks-integration.php';
		// WooCommerce Blocks IntegrationInterface (scripts/styles/data for Cart/Checkout blocks)
		require_once MEYVC_PLUGIN_DIR . 'includes/blocks/class-meyvc-blocks-integration.php';

		// Gutenberg block: Meyvora Convert / Campaign
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-gutenberg-block.php';

		// Admin
		require_once MEYVC_PLUGIN_DIR . 'admin/class-meyvc-admin-ui.php';
		require_once MEYVC_PLUGIN_DIR . 'admin/class-meyvc-admin-layout.php';
		require_once MEYVC_PLUGIN_DIR . 'admin/class-meyvc-admin.php';
		require_once MEYVC_PLUGIN_DIR . 'admin/class-meyvc-offers-admin-ajax.php';

		// AJAX (product/page search, campaigns list, campaign save)
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-onboarding.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-dashboard-metrics.php';
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-ajax.php';

		// Public
		require_once MEYVC_PLUGIN_DIR . 'public/class-meyvc-public.php';

		new MEYVC_Frontend();
		add_action( 'init', array( 'MEYVC_Consent', 'init' ), 2 );

		// Initialize REST API
		new MEYVC_REST_API();
		
		// Run database migrations if needed
		if ( class_exists( 'MEYVC_Activator' ) ) {
			MEYVC_Activator::maybe_upgrade_tables();
		}
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 */
	private function set_locale() {
		// Translations are loaded automatically by WordPress from the plugin header Text Domain.
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 */
	private function define_admin_hooks() {
		$plugin_admin = new MEYVC_Admin();
		new MEYVC_Offers_Admin_Ajax();
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_classic_editor_campaign_assets' ) );
		add_action( 'media_buttons', array( $plugin_admin, 'render_media_button_meyvc_campaign' ), 15 );
		// Use priority 20 to ensure menu appears after WooCommerce menus
		add_action( 'admin_menu', array( $plugin_admin, 'add_admin_menu' ), 20 );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality.
	 */
	private function define_public_hooks() {
		$plugin_public = new MEYVC_Public();
		add_action( 'wp_enqueue_scripts', array( $plugin_public, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $plugin_public, 'enqueue_scripts' ) );
	}

	/**
	 * Register all of the hooks related to campaigns.
	 */
	private function define_campaign_hooks() {
		$campaign_display = new MEYVC_Campaign_Display();
		$campaign_tracker = new MEYVC_Tracker();
		new MEYVC_Sequence_Engine();
		add_action( 'init', array( 'MEYVC_Shortcodes', 'init' ) );
	}

	/**
	 * Register all of the hooks related to boosters.
	 */
	private function define_booster_hooks() {
		$sticky_cart = new MEYVC_Sticky_Cart();
		$shipping_bar = new MEYVC_Shipping_Bar();
		$trust_badges = new MEYVC_Trust_Badges();
		$stock_urgency = new MEYVC_Stock_Urgency();
		new MEYVC_Social_Proof();
		new MEYVC_Recommendations();
	}

	/**
	 * Register all of the hooks related to cart optimization.
	 */
	private function define_cart_hooks() {
		$cart_optimizer = new MEYVC_Cart_Optimizer();
		$cart_messages = new MEYVC_Cart_Messages();
	}

	/**
	 * Register all of the hooks related to checkout optimization.
	 */
	private function define_checkout_hooks() {
		$checkout_optimizer = new MEYVC_Checkout_Optimizer();
		$checkout_fields = new MEYVC_Checkout_Fields();
		new MEYVC_Post_Purchase();
		new MEYVC_Klaviyo();
		new MEYVC_Mailchimp();
	}

	/**
	 * Register offer banner (classic cart/checkout) and related hooks.
	 * Classic cart/checkout CSS loads only on cart/checkout when any CRO feature is enabled.
	 */
	private function define_offer_hooks() {
		new MEYVC_Offer_Banner();
		new MEYVC_Classic_Cart_Checkout();
	}

	/**
	 * Register all of the hooks related to analytics.
	 */
	private function define_analytics_hooks() {
		$analytics = new MEYVC_Analytics();
		$revenue_tracker = new MEYVC_Revenue_Tracker();
		new MEYVC_Offer_Attribution();
		// Invalidate attribution cache when tracking data changes.
		if ( class_exists( 'MEYVC_Insights' ) ) {
			add_action( 'meyvc_event_tracked', array( 'MEYVC_Insights', 'invalidate_attribution_cache' ) );
			add_action( 'meyvc_offer_log_inserted', array( 'MEYVC_Insights', 'invalidate_attribution_cache' ) );
			add_action( 'meyvc_ab_conversion_recorded', array( 'MEYVC_Insights', 'invalidate_attribution_cache' ) );
		}
	}

	/**
	 * Register WooCommerce Blocks integration so cart/checkout optimizers work with block-based cart and checkout.
	 */
	private function define_blocks_integration() {
		// IntegrationInterface: registers scripts, styles, and get_script_data for Cart/Checkout blocks.
		add_action(
			'woocommerce_blocks_cart_block_registration',
			function ( $integration_registry ) {
				$integration_registry->register( new MEYVC_Blocks_Integration_WC() );
			}
		);
		add_action(
			'woocommerce_blocks_checkout_block_registration',
			function ( $integration_registry ) {
				$integration_registry->register( new MEYVC_Blocks_Integration_WC() );
			}
		);
		// Legacy: injects CRO HTML via render_block and enqueues styles on cart/checkout (fallback).
		new MEYVC_Blocks_Integration();
		MEYVC_Gutenberg_Block::init();
	}

	/**
	 * Register WooCommerce A/B conversion tracking (order paid/completed → record_conversion).
	 * Runs at init so WooCommerce is loaded; no-op if WooCommerce not active.
	 */
	private function define_woo_ab_conversion_hooks() {
		add_action( 'init', array( $this, 'register_woo_ab_conversion' ), 20 );
	}

	/**
	 * Register abandoned cart tracking (WooCommerce cart → meyvc_abandoned_carts table).
	 */
	private function define_abandoned_cart_hooks() {
		new MEYVC_Abandoned_Cart_Tracker();
		new MEYVC_Abandoned_Cart_Email_Capture();
		new MEYVC_Abandoned_Cart_Reminder();
	}

	/**
	 * Register MEYVC_Woo_AB_Conversion hooks when WooCommerce is available.
	 */
	public function register_woo_ab_conversion() {
		if ( function_exists( 'wc_get_order' ) && class_exists( 'MEYVC_Woo_AB_Conversion' ) ) {
			MEYVC_Woo_AB_Conversion::register();
		}
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	public function run() {
		// Plugin is initialized through hooks
	}
}
