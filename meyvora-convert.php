<?php
/**
 * Plugin Name: Meyvora Convert – Conversion Rate Optimizer for WooCommerce
 * Plugin URI: https://meyvora.com/convert
 * Description: Complete conversion rate optimization for WooCommerce — exit intent popups, abandoned cart recovery, sticky cart, shipping bar, trust badges, dynamic offers, A/B testing, and analytics. Built for Meyvora stores and beyond.
 * Version: 1.0.0
 * Author: Kalki Automations
 * Author URI: https://kalkiautomations.com
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
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version (from plugin header for cache-busting on release).
 */
if ( ! function_exists( 'get_plugin_data' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
$_cro_data = get_plugin_data( __FILE__, false, false );
define( 'CRO_VERSION', ! empty( $_cro_data['Version'] ) ? $_cro_data['Version'] : '1.0.0' );
unset( $_cro_data );
define( 'CRO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CRO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CRO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'MEYVORA_CONVERT_VERSION', CRO_VERSION );

if ( ! function_exists( 'cro_asset_min_suffix' ) ) {
	/**
	 * Empty string when SCRIPT_DEBUG is true; '.min' otherwise (for .min.js / .min.css).
	 *
	 * @return string
	 */
	function cro_asset_min_suffix() {
		return ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
	}
}

// Error handler (load early so plugin errors are caught)
require_once CRO_PLUGIN_DIR . 'includes/class-cro-error-handler.php';

// Models (security first: context uses CRO_Security::get_query_var for UTM GET params).
require_once CRO_PLUGIN_DIR . 'includes/class-cro-security.php';
require_once CRO_PLUGIN_DIR . 'includes/models/class-cro-visitor-state.php';
require_once CRO_PLUGIN_DIR . 'includes/models/class-cro-context.php';
require_once CRO_PLUGIN_DIR . 'includes/models/class-cro-campaign-model.php';

// Engine
require_once CRO_PLUGIN_DIR . 'includes/engine/class-cro-rule-engine.php';
require_once CRO_PLUGIN_DIR . 'includes/engine/class-cro-intent-scorer.php';
require_once CRO_PLUGIN_DIR . 'includes/engine/class-cro-decision.php';
// Canonical decision engine — do not re-add includes/class-cro-decision-engine.php
require_once CRO_PLUGIN_DIR . 'includes/engine/class-cro-decision-engine.php';

// A/B Testing
require_once CRO_PLUGIN_DIR . 'includes/ab-testing/class-cro-ab-test.php';
require_once CRO_PLUGIN_DIR . 'includes/ab-testing/class-cro-ab-statistics.php';

// AI classes: lazy-loaded on first use (reduces front-end and unrelated admin overhead).
spl_autoload_register(
	function ( $class ) {
		$map = array(
			'CRO_AI_Client'           => 'ai/class-cro-ai-client.php',
			'CRO_AI_Rate_Limiter'     => 'ai/class-cro-ai-rate-limiter.php',
			'CRO_AI_Copy_Generator'   => 'ai/class-cro-ai-copy-generator.php',
			'CRO_AI_Email_Writer'     => 'ai/class-cro-ai-email-writer.php',
			'CRO_AI_Insights'         => 'ai/class-cro-ai-insights.php',
			'CRO_AI_Offer_Suggester'  => 'ai/class-cro-ai-offer-suggester.php',
			'CRO_AI_AB_Hypothesis'    => 'ai/class-cro-ai-ab-hypothesis.php',
			'CRO_AI_Chat'             => 'ai/class-cro-ai-chat.php',
		);
		if ( isset( $map[ $class ] ) ) {
			require_once CRO_PLUGIN_DIR . 'includes/' . $map[ $class ];
		}
	}
);

add_action( 'plugins_loaded', function() {
	CRO_Visitor_State::get_instance();
}, 5 );

add_action( 'shutdown', function() {
	CRO_Visitor_State::get_instance()->save();
} );

// Persist "campaign shown" to visitor state (cookie) so frequency rules suppress on next request.
add_action( 'cro_campaign_shown_this_pageview', function( $campaign_id ) {
	$visitor = CRO_Visitor_State::get_instance();
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
require_once CRO_PLUGIN_DIR . 'includes/class-cro-database.php';
require_once CRO_PLUGIN_DIR . 'includes/class-cro-activator.php';

// Ensure manage_meyvora_convert capability is granted to admin/shop_manager on every load (fixes menu for existing installs that didn't reactivate).
add_action( 'init', array( 'CRO_Activator', 'ensure_capability' ), 1 );

require_once CRO_PLUGIN_DIR . 'includes/class-cro-privacy.php';
add_action( 'init', array( 'CRO_Privacy', 'init' ), 5 );

register_activation_hook( __FILE__, 'cro_handle_activation' );

function cro_handle_activation( $network_wide ) {
	if ( $network_wide && is_multisite() ) {
		// Deactivate self to prevent partial state, then show a clear error.
		deactivate_plugins( CRO_PLUGIN_BASENAME );
		wp_die(
			esc_html__( 'Meyvora Convert does not support network-wide activation. Please activate it individually on each site from that site\'s Plugins page.', 'meyvora-convert' ),
			esc_html__( 'Network Activation Not Supported', 'meyvora-convert' ),
			array( 'back_link' => true )
		);
	}
	CRO_Activator::activate();
}

// Multisite: create tables for new sites when plugin is per-site active.
add_action( 'wp_initialize_site', function( $new_site ) {
	if ( ! is_plugin_active_for_network( CRO_PLUGIN_BASENAME ) && is_plugin_active( CRO_PLUGIN_BASENAME ) ) {
		switch_to_blog( $new_site->blog_id );
		if ( class_exists( 'CRO_Database' ) ) {
			CRO_Database::create_tables();
		}
		restore_current_blog();
	}
} );

register_deactivation_hook( __FILE__, 'deactivate_meyvora_convert' );

/**
 * Check if WooCommerce is active
 */
function cro_is_woocommerce_active() {
	return class_exists( 'WooCommerce' ) || is_plugin_active( 'woocommerce/woocommerce.php' );
}

/**
 * Show admin notice if WooCommerce is not active
 */
function cro_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Meyvora Convert requires WooCommerce to be installed and active.', 'meyvora-convert' ); ?></p>
	</div>
	<?php
}

/**
 * Prevent activation if WooCommerce is not active (called from CRO_Activator::activate).
 */
function cro_activation_check() {
	if ( ! cro_is_woocommerce_active() ) {
		deactivate_plugins( CRO_PLUGIN_BASENAME );
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
function cro_prevent_woocommerce_deactivation( $plugin ) {
	if ( 'woocommerce/woocommerce.php' === $plugin && is_plugin_active( CRO_PLUGIN_BASENAME ) ) {
		wp_die(
			esc_html__( 'Meyvora Convert for WooCommerce requires WooCommerce. Please deactivate Meyvora Convert first.', 'meyvora-convert' ),
			esc_html__( 'Plugin Deactivation Error', 'meyvora-convert' ),
			array( 'back_link' => true )
		);
	}
}
add_action( 'deactivate_plugin', 'cro_prevent_woocommerce_deactivation', 10, 1 );

/**
 * Show admin notice if WooCommerce is missing
 */
function cro_admin_notices() {
	if ( ! cro_is_woocommerce_active() ) {
		add_action( 'admin_notices', 'cro_woocommerce_missing_notice' );
	}
}
add_action( 'admin_init', 'cro_admin_notices' );

/**
 * Add Settings link on Plugins list (before Deactivate), linking to Meyvora dashboard.
 */
function cro_plugin_action_links( $links ) {
	if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
		return $links;
	}
	$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=meyvora-convert' ) ) . '">' . esc_html__( 'Settings', 'meyvora-convert' ) . '</a>';
	return array_merge( array( 'settings' => $settings_link ), $links );
}
add_filter( 'plugin_action_links_' . CRO_PLUGIN_BASENAME, 'cro_plugin_action_links', 10, 1 );

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_meyvora_convert() {
	require_once CRO_PLUGIN_DIR . 'includes/class-cro-deactivator.php';
	CRO_Deactivator::deactivate();
}

/**
 * Check WooCommerce before loading plugin
 * Use plugins_loaded hook to ensure WooCommerce is loaded first
 */
function cro_init_plugin() {
	if ( ! cro_is_woocommerce_active() ) {
		return;
	}
	
	// Run DB upgrade routine on plugin load (for migrations after updates)
	require_once CRO_PLUGIN_DIR . 'includes/class-cro-activator.php';
	CRO_Activator::maybe_upgrade_tables();

	/**
	 * The core plugin class that is used to define internationalization,
	 * admin-specific hooks, and public-facing site hooks.
	 */
	require CRO_PLUGIN_DIR . 'includes/class-cro-loader.php';

	require_once CRO_PLUGIN_DIR . 'includes/class-cro-webhook.php';
	CRO_Webhook::init();

	/**
	 * Begins execution of the plugin.
	 */
	function run_meyvora_convert() {
		$plugin = new CRO_Loader();
		$plugin->run();
	}
	run_meyvora_convert();

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-cli.php';
	}
}

// Run plugin at init priority 1 so textdomain (init 0) runs first and WooCommerce is ready (WP 6.7 "translation too early" + WC get_continents safe).
add_action( 'init', 'cro_init_plugin', 1 );

/**
 * Performance monitoring for debug mode
 */
if ( defined( 'CRO_DEBUG' ) && CRO_DEBUG ) {
	add_action( 'shutdown', function () {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			return;
		}

		$stats = array(
			'memory'  => class_exists( 'CRO_Resource_Manager' ) && method_exists( 'CRO_Resource_Manager', 'get_memory_stats' )
				? CRO_Resource_Manager::get_memory_stats()
				: array(),
			'time'    => class_exists( 'CRO_Resource_Manager' ) && method_exists( 'CRO_Resource_Manager', 'get_execution_time' )
				? CRO_Resource_Manager::get_execution_time()
				: 0,
			'cache'   => class_exists( 'CRO_Cache' ) && method_exists( 'CRO_Cache', 'get_stats' )
				? CRO_Cache::get_stats()
				: array(),
			'queries' => function_exists( 'get_num_queries' ) ? get_num_queries() : 0,
		);

		if ( class_exists( 'CRO_Error_Handler' ) && method_exists( 'CRO_Error_Handler', 'log' ) ) {
			CRO_Error_Handler::log( 'PERF', 'Request stats', $stats );
		}
	} );
}
