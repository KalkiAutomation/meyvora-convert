<?php
/**
 * Plugin Name: Meyvora Convert – Conversion Rate Optimizer for WooCommerce
 * Description: Complete conversion rate optimization for WooCommerce — exit intent popups, abandoned cart recovery, sticky cart, shipping bar, trust badges, dynamic offers, A/B testing, and analytics. Built for Meyvora stores and beyond.
 * Version: 1.0.0
 * Author: kalkiautomation
 * Author URI: https://kalkiautomation.com/
 * License: GPL v2 or later
 * Text Domain: meyvora-convert
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 5.0
 * WC tested up to: 10.6
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Currently plugin version (from plugin header for cache-busting on release).
 */
if ( ! function_exists( 'get_plugin_data' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
$meyvc_plugin_file_header = get_plugin_data( __FILE__, false, false );
define( 'MEYVC_VERSION', ! empty( $meyvc_plugin_file_header['Version'] ) ? $meyvc_plugin_file_header['Version'] : '1.0.0' );
unset( $meyvc_plugin_file_header );
define( 'MEYVC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MEYVC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MEYVC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'MEYVORA_CONVERT_VERSION', MEYVC_VERSION );

if ( ! function_exists( 'meyvc_asset_min_suffix' ) ) {
	/**
	 * Empty string when SCRIPT_DEBUG is true; '.min' otherwise (for .min.js / .min.css).
	 *
	 * @return string
	 */
	function meyvc_asset_min_suffix() {
		return ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
	}
}

// Error handler (load early so plugin errors are caught)
require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-error-handler.php';

// Models (security first: context uses MEYVC_Security::get_query_var for UTM GET params).
require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-security.php';
require_once MEYVC_PLUGIN_DIR . 'includes/models/class-meyvc-visitor-state.php';
require_once MEYVC_PLUGIN_DIR . 'includes/models/class-meyvc-context.php';
require_once MEYVC_PLUGIN_DIR . 'includes/models/class-meyvc-campaign-model.php';

// Engine
require_once MEYVC_PLUGIN_DIR . 'includes/engine/class-meyvc-rule-engine.php';
require_once MEYVC_PLUGIN_DIR . 'includes/engine/class-meyvc-intent-scorer.php';
require_once MEYVC_PLUGIN_DIR . 'includes/engine/class-meyvc-decision.php';
// Canonical decision engine — do not re-add includes/class-meyvc-decision-engine.php
require_once MEYVC_PLUGIN_DIR . 'includes/engine/class-meyvc-decision-engine.php';

// A/B Testing
require_once MEYVC_PLUGIN_DIR . 'includes/ab-testing/class-meyvc-ab-test.php';
require_once MEYVC_PLUGIN_DIR . 'includes/ab-testing/class-meyvc-ab-statistics.php';

// AI classes: lazy-loaded on first use (reduces front-end and unrelated admin overhead).
spl_autoload_register(
	function ( $class ) {
		$map = array(
			'MEYVC_AI_Client'           => 'ai/class-meyvc-ai-client.php',
			'MEYVC_AI_Rate_Limiter'     => 'ai/class-meyvc-ai-rate-limiter.php',
			'MEYVC_AI_Copy_Generator'   => 'ai/class-meyvc-ai-copy-generator.php',
			'MEYVC_AI_Email_Writer'     => 'ai/class-meyvc-ai-email-writer.php',
			'MEYVC_AI_Insights'         => 'ai/class-meyvc-ai-insights.php',
			'MEYVC_AI_Offer_Suggester'  => 'ai/class-meyvc-ai-offer-suggester.php',
			'MEYVC_AI_AB_Hypothesis'    => 'ai/class-meyvc-ai-ab-hypothesis.php',
			'MEYVC_AI_Chat'             => 'ai/class-meyvc-ai-chat.php',
		);
		if ( isset( $map[ $class ] ) ) {
			require_once MEYVC_PLUGIN_DIR . 'includes/' . $map[ $class ];
		}
	}
);

add_action( 'plugins_loaded', function() {
	MEYVC_Visitor_State::get_instance();
}, 5 );

add_action( 'shutdown', function() {
	MEYVC_Visitor_State::get_instance()->save();
} );

// Persist "campaign shown" to visitor state (cookie) so frequency rules suppress on next request.
add_action( 'meyvc_campaign_shown_this_pageview', function( $campaign_id ) {
	$visitor = MEYVC_Visitor_State::get_instance();
	$visitor->record_campaign_shown( (int) $campaign_id );
	$visitor->save();
}, 10, 1 );

/**
 * Declare compatibility with WooCommerce HPOS (High-Performance Order Storage).
 * Meyvora Convert uses wc_get_order() and order CRUD APIs only, not post/postmeta.
 */
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

// Load activator and database before activation hook so callback exists and create_tables() is available.
require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-database.php';
require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-activator.php';

// Ensure manage_meyvora_convert capability is granted to admin/shop_manager on every load (fixes menu for existing installs that didn't reactivate).
add_action( 'init', array( 'MEYVC_Activator', 'ensure_capability' ), 1 );

require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-privacy.php';
add_action( 'init', array( 'MEYVC_Privacy', 'init' ), 5 );

register_activation_hook( __FILE__, 'meyvc_handle_activation' );

function meyvc_handle_activation( $network_wide ) {
	if ( $network_wide && is_multisite() ) {
		// Deactivate self to prevent partial state, then show a clear error.
		deactivate_plugins( MEYVC_PLUGIN_BASENAME );
		wp_die(
			esc_html__( 'Meyvora Convert does not support network-wide activation. Please activate it individually on each site from that site\'s Plugins page.', 'meyvora-convert' ),
			esc_html__( 'Network Activation Not Supported', 'meyvora-convert' ),
			array( 'back_link' => true )
		);
	}
	MEYVC_Activator::activate();
}

// Multisite: create tables for new sites when plugin is per-site active.
add_action( 'wp_initialize_site', function( $new_site ) {
	if ( ! is_plugin_active_for_network( MEYVC_PLUGIN_BASENAME ) && is_plugin_active( MEYVC_PLUGIN_BASENAME ) ) {
		switch_to_blog( $new_site->blog_id );
		if ( class_exists( 'MEYVC_Database' ) ) {
			MEYVC_Database::create_tables();
		}
		restore_current_blog();
	}
} );

register_deactivation_hook( __FILE__, 'meyvc_deactivate_plugin' );

/**
 * Check if WooCommerce is active
 */
function meyvc_is_woocommerce_active() {
	return class_exists( 'WooCommerce' ) || is_plugin_active( 'woocommerce/woocommerce.php' );
}

/**
 * Show admin notice if WooCommerce is not active
 */
function meyvc_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Meyvora Convert requires WooCommerce to be installed and active.', 'meyvora-convert' ); ?></p>
	</div>
	<?php
}

/**
 * Prevent activation if WooCommerce is not active (called from MEYVC_Activator::activate).
 */
function meyvc_activation_check() {
	if ( ! meyvc_is_woocommerce_active() ) {
		deactivate_plugins( MEYVC_PLUGIN_BASENAME );
		wp_die(
			esc_html__( 'Meyvora Convert requires WooCommerce to be installed and active. Please install WooCommerce first.', 'meyvora-convert' ),
			esc_html__( 'Plugin Activation Error', 'meyvora-convert' ),
			array( 'back_link' => true )
		);
	}
}

/**
 * Prevent WooCommerce deactivation while plugin is active
 */
function meyvc_prevent_woocommerce_deactivation( $plugin ) {
	if ( 'woocommerce/woocommerce.php' === $plugin && is_plugin_active( MEYVC_PLUGIN_BASENAME ) ) {
		wp_die(
			esc_html__( 'Meyvora Convert for WooCommerce requires WooCommerce. Please deactivate Meyvora Convert first.', 'meyvora-convert' ),
			esc_html__( 'Plugin Deactivation Error', 'meyvora-convert' ),
			array( 'back_link' => true )
		);
	}
}
add_action( 'deactivate_plugin', 'meyvc_prevent_woocommerce_deactivation', 10, 1 );

/**
 * Show admin notice if WooCommerce is missing
 */
function meyvc_admin_notices() {
	if ( ! meyvc_is_woocommerce_active() ) {
		add_action( 'admin_notices', 'meyvc_woocommerce_missing_notice' );
	}
}
add_action( 'admin_init', 'meyvc_admin_notices' );

/**
 * Add Settings link on Plugins list (before Deactivate), linking to Meyvora dashboard.
 */
function meyvc_plugin_action_links( $links ) {
	if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
		return $links;
	}
	$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=meyvora-convert' ) ) . '">' . esc_html__( 'Settings', 'meyvora-convert' ) . '</a>';
	return array_merge( array( 'settings' => $settings_link ), $links );
}
add_filter( 'plugin_action_links_' . MEYVC_PLUGIN_BASENAME, 'meyvc_plugin_action_links', 10, 1 );

/**
 * The code that runs during plugin deactivation.
 */
function meyvc_deactivate_plugin() {
	require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-deactivator.php';
	MEYVC_Deactivator::deactivate();
}

/**
 * Check WooCommerce before loading plugin
 * Use plugins_loaded hook to ensure WooCommerce is loaded first
 */
function meyvc_init_plugin() {
	if ( ! meyvc_is_woocommerce_active() ) {
		return;
	}
	
	// Run DB upgrade routine on plugin load (for migrations after updates)
	require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-activator.php';
	MEYVC_Activator::maybe_upgrade_tables();

	/**
	 * The core plugin class that is used to define internationalization,
	 * admin-specific hooks, and public-facing site hooks.
	 */
	require MEYVC_PLUGIN_DIR . 'includes/class-meyvc-loader.php';

	require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-webhook.php';
	MEYVC_Webhook::init();

	$meyvc_loader = new MEYVC_Loader();
	$meyvc_loader->run();

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-cli.php';
	}
}

// Run plugin at init priority 1 so textdomain (init 0) runs first and WooCommerce is ready (WP 6.7 "translation too early" + WC get_continents safe).
add_action( 'init', 'meyvc_init_plugin', 1 );

/**
 * Performance monitoring for debug mode
 */
if ( defined( 'MEYVC_DEBUG' ) && MEYVC_DEBUG ) {
	add_action( 'shutdown', function () {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			return;
		}

		$stats = array(
			'memory'  => class_exists( 'MEYVC_Resource_Manager' ) && method_exists( 'MEYVC_Resource_Manager', 'get_memory_stats' )
				? MEYVC_Resource_Manager::get_memory_stats()
				: array(),
			'time'    => class_exists( 'MEYVC_Resource_Manager' ) && method_exists( 'MEYVC_Resource_Manager', 'get_execution_time' )
				? MEYVC_Resource_Manager::get_execution_time()
				: 0,
			'cache'   => class_exists( 'MEYVC_Cache' ) && method_exists( 'MEYVC_Cache', 'get_stats' )
				? MEYVC_Cache::get_stats()
				: array(),
			'queries' => function_exists( 'get_num_queries' ) ? get_num_queries() : 0,
		);

		if ( class_exists( 'MEYVC_Error_Handler' ) && method_exists( 'MEYVC_Error_Handler', 'log' ) ) {
			MEYVC_Error_Handler::log( 'PERF', 'Request stats', $stats );
		}
	} );
}
