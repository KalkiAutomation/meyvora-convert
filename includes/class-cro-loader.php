<?php
/**
 * The file that defines the core plugin class
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The core plugin class.
 */
class CRO_Loader {

	/**
	 * The loader that's responsible for maintaining and registering all hooks.
	 *
	 * @var CRO_Loader
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
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-activator.php';
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-deactivator.php';
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-settings.php';
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-system-status.php';
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-default-copy.php';
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-database.php';
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-validator.php';
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-edge-cases.php';
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-cache.php';
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-lazy-loader.php';
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-query-optimizer.php';
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-asset-optimizer.php';
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-background-processor.php';
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-resource-manager.php';

		// Preset library (used by admin Presets page)
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-presets.php';
		// Quick Launch (recommended setup in one click)
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-quick-launch.php';

		// Campaign classes
		require_once CRO_PLUGIN_DIR . 'includes/campaigns/class-cro-campaign.php';
		require_once CRO_PLUGIN_DIR . 'includes/campaigns/class-cro-campaign-display.php';
		require_once CRO_PLUGIN_DIR . 'includes/campaigns/class-cro-targeting.php';
		require_once CRO_PLUGIN_DIR . 'includes/campaigns/class-cro-tracker.php';

		// Shortcodes (campaign render)
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-shortcodes.php';

		// Decision engine and visitor state are loaded in the main plugin file (meyvora-convert.php).

		// Intent validator (validate trigger signals)
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-intent-validator.php';

		// Offer guard (coupon and offer protection)
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-offer-guard.php';

		// Theme and plugin compatibility
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-compatibility.php';

		// Security utilities
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-security.php';

		// Hooks reference (documentation only)
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-hooks.php';

		// UX protection rules
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-ux-rules.php';

		// Booster classes
		require_once CRO_PLUGIN_DIR . 'includes/boosters/class-cro-sticky-cart.php';
		require_once CRO_PLUGIN_DIR . 'includes/boosters/class-cro-shipping-bar.php';
		require_once CRO_PLUGIN_DIR . 'includes/boosters/class-cro-trust-badges.php';
		require_once CRO_PLUGIN_DIR . 'includes/boosters/class-cro-stock-urgency.php';

		// Cart classes
		require_once CRO_PLUGIN_DIR . 'includes/cart/class-cro-cart-optimizer.php';
		require_once CRO_PLUGIN_DIR . 'includes/cart/class-cro-cart-messages.php';

		// Abandoned cart tracking
		require_once CRO_PLUGIN_DIR . 'includes/abandoned/class-cro-abandoned-cart-tracker.php';
		require_once CRO_PLUGIN_DIR . 'includes/abandoned/class-cro-abandoned-cart-email-capture.php';
		require_once CRO_PLUGIN_DIR . 'includes/abandoned/class-cro-abandoned-cart-reminder.php';
		require_once CRO_PLUGIN_DIR . 'includes/abandoned/class-cro-abandoned-cart-coupon.php';

		// Checkout classes
		require_once CRO_PLUGIN_DIR . 'includes/checkout/class-cro-checkout-optimizer.php';
		require_once CRO_PLUGIN_DIR . 'includes/checkout/class-cro-checkout-fields.php';

		// Classic cart/checkout asset loader (trust, offer banner, shipping – assets only when needed)
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-classic-cart-checkout.php';

		// Analytics classes
		require_once CRO_PLUGIN_DIR . 'includes/analytics/class-cro-analytics.php';
		require_once CRO_PLUGIN_DIR . 'includes/analytics/class-cro-revenue-tracker.php';
		require_once CRO_PLUGIN_DIR . 'includes/analytics/class-cro-offer-attribution.php';
		require_once CRO_PLUGIN_DIR . 'includes/analytics/class-cro-analytics-filter.php';

		// Insights (rule-based recommendations)
		require_once CRO_PLUGIN_DIR . 'includes/insights/class-cro-insights.php';

		// A/B Testing classes
		require_once CRO_PLUGIN_DIR . 'includes/ab-testing/class-cro-ab-test.php';
		require_once CRO_PLUGIN_DIR . 'includes/ab-testing/class-cro-ab-statistics.php';
		require_once CRO_PLUGIN_DIR . 'includes/ab-testing/class-cro-woo-ab-conversion.php';

		// Frontend asset loading
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-frontend.php';

		// UI (icons for admin and frontend)
		require_once CRO_PLUGIN_DIR . 'includes/ui/class-cro-icons.php';

		// Templates helper (used by popup templates)
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-templates.php';

		// Placeholders (used by popup templates for {cart_total}, etc.)
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-placeholders.php';

		// Offers (model + tables: cro_offers, cro_offer_logs)
		require_once CRO_PLUGIN_DIR . 'includes/offers/class-cro-offer-model.php';
		require_once CRO_PLUGIN_DIR . 'includes/offers/class-cro-offer-rules.php';
		require_once CRO_PLUGIN_DIR . 'includes/offers/class-cro-offer-engine.php';
		require_once CRO_PLUGIN_DIR . 'includes/offers/class-cro-offer-banner.php';
		require_once CRO_PLUGIN_DIR . 'includes/offers/class-cro-offer-presenter.php';
		require_once CRO_PLUGIN_DIR . 'includes/offers/class-cro-offer-schema.php';

		// REST API
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-rest-api.php';

		// WooCommerce Blocks (Gutenberg) cart/checkout integration
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-blocks-integration.php';
		// WooCommerce Blocks IntegrationInterface (scripts/styles/data for Cart/Checkout blocks)
		require_once CRO_PLUGIN_DIR . 'includes/blocks/class-cro-blocks-integration.php';

		// Gutenberg block: Meyvora Convert / Campaign
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-gutenberg-block.php';

		// Admin
		require_once CRO_PLUGIN_DIR . 'admin/class-cro-admin-ui.php';
		require_once CRO_PLUGIN_DIR . 'admin/class-cro-admin-layout.php';
		require_once CRO_PLUGIN_DIR . 'admin/class-cro-admin.php';
		require_once CRO_PLUGIN_DIR . 'admin/class-cro-offers-admin-ajax.php';

		// AJAX (product/page search, campaigns list, campaign save)
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-ajax.php';

		// Public
		require_once CRO_PLUGIN_DIR . 'public/class-cro-public.php';

		// Initialize REST API
		new CRO_REST_API();
		
		// Run database migrations if needed
		if ( class_exists( 'CRO_Activator' ) ) {
			CRO_Activator::maybe_upgrade_tables();
		}
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 */
	private function set_locale() {
		// Load translations at init priority 0 so they are available before any i18n (WP 6.7+)
		add_action( 'init', array( $this, 'load_textdomain' ), 0 );
	}

	/**
	 * Load plugin textdomain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'meyvora-convert',
			false,
			dirname( CRO_PLUGIN_BASENAME ) . '/languages/'
		);
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 */
	private function define_admin_hooks() {
		$plugin_admin = new CRO_Admin();
		new CRO_Offers_Admin_Ajax();
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_classic_editor_campaign_assets' ) );
		add_action( 'media_buttons', array( $plugin_admin, 'render_media_button_cro_campaign' ), 15 );
		// Use priority 20 to ensure menu appears after WooCommerce menus
		add_action( 'admin_menu', array( $plugin_admin, 'add_admin_menu' ), 20 );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality.
	 */
	private function define_public_hooks() {
		$plugin_public = new CRO_Public();
		add_action( 'wp_head', array( $plugin_public, 'print_brand_styles_vars' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $plugin_public, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $plugin_public, 'enqueue_scripts' ) );
	}

	/**
	 * Register all of the hooks related to campaigns.
	 */
	private function define_campaign_hooks() {
		$campaign_display = new CRO_Campaign_Display();
		$campaign_tracker = new CRO_Tracker();
		add_action( 'init', array( 'CRO_Shortcodes', 'init' ) );
	}

	/**
	 * Register all of the hooks related to boosters.
	 */
	private function define_booster_hooks() {
		$sticky_cart = new CRO_Sticky_Cart();
		$shipping_bar = new CRO_Shipping_Bar();
		$trust_badges = new CRO_Trust_Badges();
		$stock_urgency = new CRO_Stock_Urgency();
	}

	/**
	 * Register all of the hooks related to cart optimization.
	 */
	private function define_cart_hooks() {
		$cart_optimizer = new CRO_Cart_Optimizer();
		$cart_messages = new CRO_Cart_Messages();
	}

	/**
	 * Register all of the hooks related to checkout optimization.
	 */
	private function define_checkout_hooks() {
		$checkout_optimizer = new CRO_Checkout_Optimizer();
		$checkout_fields = new CRO_Checkout_Fields();
	}

	/**
	 * Register offer banner (classic cart/checkout) and related hooks.
	 * Classic cart/checkout CSS loads only on cart/checkout when any CRO feature is enabled.
	 */
	private function define_offer_hooks() {
		new CRO_Offer_Banner();
		new CRO_Classic_Cart_Checkout();
	}

	/**
	 * Register all of the hooks related to analytics.
	 */
	private function define_analytics_hooks() {
		$analytics = new CRO_Analytics();
		$revenue_tracker = new CRO_Revenue_Tracker();
		new CRO_Offer_Attribution();
		// Invalidate attribution cache when tracking data changes.
		if ( class_exists( 'CRO_Insights' ) ) {
			add_action( 'cro_event_tracked', array( 'CRO_Insights', 'invalidate_attribution_cache' ) );
			add_action( 'cro_offer_log_inserted', array( 'CRO_Insights', 'invalidate_attribution_cache' ) );
			add_action( 'cro_ab_conversion_recorded', array( 'CRO_Insights', 'invalidate_attribution_cache' ) );
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
				$integration_registry->register( new CRO_Blocks_Integration_WC() );
			}
		);
		add_action(
			'woocommerce_blocks_checkout_block_registration',
			function ( $integration_registry ) {
				$integration_registry->register( new CRO_Blocks_Integration_WC() );
			}
		);
		// Legacy: injects CRO HTML via render_block and enqueues styles on cart/checkout (fallback).
		new CRO_Blocks_Integration();
		CRO_Gutenberg_Block::init();
	}

	/**
	 * Register WooCommerce A/B conversion tracking (order paid/completed → record_conversion).
	 * Runs at init so WooCommerce is loaded; no-op if WooCommerce not active.
	 */
	private function define_woo_ab_conversion_hooks() {
		add_action( 'init', array( $this, 'register_woo_ab_conversion' ), 20 );
	}

	/**
	 * Register abandoned cart tracking (WooCommerce cart → cro_abandoned_carts table).
	 */
	private function define_abandoned_cart_hooks() {
		new CRO_Abandoned_Cart_Tracker();
		new CRO_Abandoned_Cart_Email_Capture();
		new CRO_Abandoned_Cart_Reminder();
	}

	/**
	 * Register CRO_Woo_AB_Conversion hooks when WooCommerce is available.
	 */
	public function register_woo_ab_conversion() {
		if ( function_exists( 'wc_get_order' ) && class_exists( 'CRO_Woo_AB_Conversion' ) ) {
			CRO_Woo_AB_Conversion::register();
		}
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	public function run() {
		// Plugin is initialized through hooks
	}
}
