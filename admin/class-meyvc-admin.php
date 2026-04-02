<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The admin-specific functionality of the plugin.
 */
class MEYVC_Admin {

	/**
	 * Sanitized GET query string (uses filter_input for static analysis).
	 *
	 * @param string $key     Query parameter name.
	 * @param string $default Default if missing.
	 * @return string
	 */
	private static function get_get_text( $key, $default = '' ) {
		$raw = filter_input( INPUT_GET, $key, FILTER_UNSAFE_RAW );
		if ( ! is_string( $raw ) ) {
			return $default;
		}
		return sanitize_text_field( wp_unslash( $raw ) );
	}

	/**
	 * Positive integer from GET (0 if missing/invalid).
	 *
	 * @param string $key Query parameter name.
	 * @return int
	 */
	private static function get_get_absint( $key ) {
		$raw = filter_input( INPUT_GET, $key, FILTER_UNSAFE_RAW );
		if ( ! is_string( $raw ) ) {
			return 0;
		}
		return absint( wp_unslash( $raw ) );
	}

	/**
	 * Whether the URL requests onboarding mode (?meyvc_onboarding=1).
	 *
	 * @return bool
	 */
	private static function is_meyvc_onboarding_query() {
		$raw = filter_input( INPUT_GET, 'meyvc_onboarding', FILTER_UNSAFE_RAW );
		return is_string( $raw ) && wp_unslash( $raw ) === '1';
	}

	/**
	 * Initialize the class and set its properties.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize admin hooks.
	 */
	private function init() {
		// Admin hooks are registered in the loader.
		add_action( 'admin_init', array( $this, 'register_campaign_builder_assets' ), 1 );
		add_action( 'admin_init', array( $this, 'handle_activation_redirect' ) );
		add_action( 'admin_init', array( $this, 'handle_onboarding_restart' ) );
		add_action( 'admin_init', array( $this, 'handle_onboarding_skip' ) );
		add_action( 'admin_init', array( $this, 'handle_onboarding_save' ) );
		add_action( 'admin_init', array( $this, 'handle_apply_preset' ) );
		add_action( 'admin_init', array( $this, 'handle_quick_launch' ) );
		add_action( 'admin_init', array( $this, 'handle_export' ) );
		add_action( 'admin_init', array( $this, 'handle_csv_export' ) );
		add_action( 'admin_init', array( $this, 'handle_import' ) );
		add_action( 'admin_init', array( $this, 'handle_verify_package' ) );
		add_action( 'admin_init', array( $this, 'handle_verify_installation' ) );
		add_action( 'admin_init', array( $this, 'handle_save_admin_debug' ) );
		add_action( 'admin_init', array( $this, 'handle_repair_tables' ) );
		add_action( 'admin_init', array( $this, 'run_selfheal_tables' ) );
		add_action( 'admin_init', array( $this, 'handle_bulk_campaigns' ) );
		add_action( 'admin_init', array( $this, 'handle_campaign_actions' ) );
		add_action( 'admin_init', array( $this, 'handle_ab_test_actions' ) );
		add_action( 'admin_post_meyvc_save_sequence', array( $this, 'handle_save_sequence' ) );
		add_action( 'admin_post_meyvc_delete_sequence', array( $this, 'handle_delete_sequence' ) );
		// Front-end error reporting (graceful error handling).
		add_action( 'wp_ajax_meyvc_log_error', array( $this, 'handle_log_error' ) );
		add_action( 'wp_ajax_nopriv_meyvc_log_error', array( $this, 'handle_log_error' ) );
		// Offers: save single offer via drawer (AJAX).
		add_action( 'wp_ajax_meyvc_save_offer', array( $this, 'ajax_save_offer' ) );
		// Abandoned cart emails: preview and send test (AJAX).
		add_action( 'wp_ajax_meyvc_abandoned_cart_preview', array( $this, 'ajax_abandoned_cart_preview' ) );
		add_action( 'wp_ajax_meyvc_abandoned_cart_send_test', array( $this, 'ajax_abandoned_cart_send_test' ) );
		// Abandoned carts list: row actions and drawer.
		add_action( 'admin_post_meyvc_abandoned_cart_cancel_reminders', array( $this, 'handle_abandoned_cart_cancel_reminders' ) );
		add_action( 'admin_post_meyvc_abandoned_cart_mark_recovered', array( $this, 'handle_abandoned_cart_mark_recovered' ) );
		add_action( 'admin_post_meyvc_abandoned_cart_resend', array( $this, 'handle_abandoned_cart_resend' ) );
		add_action( 'wp_ajax_meyvc_abandoned_cart_drawer', array( $this, 'ajax_abandoned_cart_drawer' ) );
		add_action( 'wp_ajax_meyvc_search_products', array( $this, 'ajax_search_products' ) );
		add_action( 'wp_ajax_meyvc_ai_test_connection', array( $this, 'ajax_ai_test_connection' ) );
		add_action( 'wp_ajax_meyvc_set_attribution_model', array( $this, 'ajax_set_attribution_model' ) );
		add_action( 'wp_ajax_meyvc_get_attributed_revenue', array( $this, 'ajax_get_attributed_revenue' ) );
		add_action( 'wp_ajax_meyvc_get_campaign_revenue_chart', array( $this, 'ajax_get_campaign_revenue_chart' ) );
		if ( class_exists( 'MEYVC_AI_Copy_Generator' ) ) {
			$meyvc_ai_copy_gen = new MEYVC_AI_Copy_Generator();
			$meyvc_ai_copy_gen->register_ajax();
		}
		if ( class_exists( 'MEYVC_AI_Email_Writer' ) ) {
			$meyvc_ai_email_writer = new MEYVC_AI_Email_Writer();
			$meyvc_ai_email_writer->register_ajax();
		}
		if ( class_exists( 'MEYVC_AI_Insights' ) ) {
			$meyvc_ai_insights = new MEYVC_AI_Insights();
			$meyvc_ai_insights->register_ajax();
		}
		if ( class_exists( 'MEYVC_AI_Offer_Suggester' ) ) {
			$meyvc_ai_offer_suggester = new MEYVC_AI_Offer_Suggester();
			$meyvc_ai_offer_suggester->register_ajax();
		}
		if ( class_exists( 'MEYVC_AI_AB_Hypothesis' ) ) {
			$meyvc_ai_ab_hypothesis = new MEYVC_AI_AB_Hypothesis();
			$meyvc_ai_ab_hypothesis->register_ajax();
		}
		if ( class_exists( 'MEYVC_AI_Chat' ) ) {
			$meyvc_ai_chat = new MEYVC_AI_Chat();
			$meyvc_ai_chat->register_ajax();
		}
		add_action( 'meyvc_admin_nav_actions', array( $this, 'render_ai_chat_nav_toggle' ) );
		add_action( 'meyvc_admin_after_page', array( $this, 'render_ai_chat_shell' ) );
		add_action( 'admin_footer', array( $this, 'render_admin_debug_panel' ), 15 );
	}

	/**
	 * Register the administration menu. Organized by feature area.
	 */
	public function add_admin_menu() {
		$this->register_menus();
	}

	/**
	 * Register admin menus
	 */
	public function register_menus() {
		// Main menu
		add_menu_page(
			__( 'Meyvora Convert', 'meyvora-convert' ),
			__( 'Meyvora Convert', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'meyvora-convert',
			array( $this, 'render_dashboard' ),
			'dashicons-chart-area',
			56
		);

		// Dashboard (same as main)
		add_submenu_page(
			'meyvora-convert',
			__( 'Dashboard', 'meyvora-convert' ),
			__( 'Dashboard', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'meyvora-convert',
			array( $this, 'render_dashboard' )
		);

		// Presets
		add_submenu_page(
			'meyvora-convert',
			__( 'Presets', 'meyvora-convert' ),
			__( 'Presets', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'meyvc-presets',
			array( $this, 'render_presets' )
		);

		// Campaign sequences
		add_submenu_page(
			'meyvora-convert',
			__( 'Campaign Sequences', 'meyvora-convert' ),
			__( 'Sequences', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'meyvc-sequences',
			array( $this, 'render_sequences' )
		);

		// Campaigns
		add_submenu_page(
			'meyvora-convert',
			__( 'Campaigns', 'meyvora-convert' ),
			__( 'Campaigns', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'meyvc-campaigns',
			array( $this, 'render_campaigns' )
		);

		// Campaign Builder (hidden from menu)
		add_submenu_page(
			null, // Hidden
			__( 'Edit Campaign', 'meyvora-convert' ),
			__( 'Edit Campaign', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'meyvc-campaign-edit',
			array( $this, 'render_campaign_builder' )
		);

		// On-Page Boosters
		add_submenu_page(
			'meyvora-convert',
			__( 'On-Page Boosters', 'meyvora-convert' ),
			__( 'On-Page Boosters', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'meyvc-boosters',
			array( $this, 'render_boosters' )
		);

		// Cart Optimizer
		add_submenu_page(
			'meyvora-convert',
			__( 'Cart Optimizer', 'meyvora-convert' ),
			__( 'Cart Optimizer', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'meyvc-cart',
			array( $this, 'render_cart_optimizer' )
		);

		// Abandoned Carts (list)
		add_submenu_page(
			'meyvora-convert',
			__( 'Abandoned Carts', 'meyvora-convert' ),
			__( 'Abandoned Carts', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'meyvc-abandoned-carts',
			array( $this, 'render_abandoned_carts_list' )
		);

		// Abandoned Cart Emails (templates/settings)
		add_submenu_page(
			'meyvora-convert',
			__( 'Abandoned Cart Emails', 'meyvora-convert' ),
			__( 'Abandoned Cart Emails', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'meyvc-abandoned-cart',
			array( $this, 'render_abandoned_cart_emails' )
		);

		// Offers (dynamic offers config)
		add_submenu_page(
			'meyvora-convert',
			__( 'Offers', 'meyvora-convert' ),
			__( 'Offers', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'meyvc-offers',
			array( $this, 'render_offers' )
		);

		// Checkout Optimizer
		add_submenu_page(
			'meyvora-convert',
			__( 'Checkout Optimizer', 'meyvora-convert' ),
			__( 'Checkout Optimizer', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'meyvc-checkout',
			array( $this, 'render_checkout_optimizer' )
		);

		// A/B Tests
		add_submenu_page(
			'meyvora-convert',
			__( 'A/B Tests', 'meyvora-convert' ),
			__( 'A/B Tests', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'meyvc-ab-tests',
			array( $this, 'render_ab_tests' )
		);

		// New A/B Test (hidden from menu but accessible)
		add_submenu_page(
			null, // Hidden from menu
			__( 'Create A/B Test', 'meyvora-convert' ),
			__( 'Create A/B Test', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'meyvc-ab-test-new',
			array( $this, 'render_ab_test_new' )
		);

		// View A/B Test (hidden from menu but accessible)
		add_submenu_page(
			null, // Hidden from menu
			__( 'View A/B Test', 'meyvora-convert' ),
			__( 'View A/B Test', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'meyvc-ab-test-view',
			array( $this, 'render_ab_test_view' )
		);

		// Analytics
		add_submenu_page(
			'meyvora-convert',
			__( 'Analytics', 'meyvora-convert' ),
			__( 'Analytics', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'meyvc-analytics',
			array( $this, 'render_analytics' )
		);

		// Insights
		add_submenu_page(
			'meyvora-convert',
			__( 'Insights', 'meyvora-convert' ),
			__( 'Insights', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'meyvc-insights',
			array( $this, 'render_insights' )
		);

		// Settings
		add_submenu_page(
			'meyvora-convert',
			__( 'Settings', 'meyvora-convert' ),
			__( 'Settings', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'meyvc-settings',
			array( $this, 'render_settings' )
		);

		// System Status (under Settings in menu order)
		add_submenu_page(
			'meyvora-convert',
			__( 'System Status', 'meyvora-convert' ),
			__( 'System Status', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'meyvc-system-status',
			array( $this, 'render_system_status' )
		);

		// Tools → Import / Export
		add_submenu_page(
			'meyvora-convert',
			__( 'Import / Export', 'meyvora-convert' ),
			__( 'Tools', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'meyvc-tools',
			array( $this, 'render_tools' )
		);

		// Developer (hooks, templates)
		add_submenu_page(
			'meyvora-convert',
			__( 'Developer', 'meyvora-convert' ),
			__( 'Developer', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'meyvc-developer',
			array( $this, 'render_developer' )
		);
	}

	/**
	 * Nav bar control to open the AI chat panel.
	 */
	public function render_ai_chat_nav_toggle(): void {
		if ( ! ( class_exists( 'MEYVC_AI_Client' ) && MEYVC_AI_Client::is_configured()
			&& function_exists( 'meyvc_settings' )
			&& 'yes' === meyvc_settings()->get( 'ai', 'feature_chat', 'yes' ) ) ) {
			return;
		}
		$path = MEYVC_PLUGIN_DIR . 'admin/partials/meyvc-admin-nav-tabs.php';
		if ( is_readable( $path ) ) {
			include $path;
		}
	}

	/**
	 * Fixed-position AI chat launcher and panel markup.
	 */
	public function render_ai_chat_shell(): void {
		if ( ! ( class_exists( 'MEYVC_AI_Client' ) && MEYVC_AI_Client::is_configured()
			&& function_exists( 'meyvc_settings' )
			&& 'yes' === meyvc_settings()->get( 'ai', 'feature_chat', 'yes' ) ) ) {
			return;
		}
		$path = MEYVC_PLUGIN_DIR . 'admin/partials/meyvc-admin-ai-chat.php';
		if ( is_readable( $path ) ) {
			include $path;
		}
	}

	/**
	 * Render Presets library page.
	 */
	public function render_presets() {
		MEYVC_Admin_UI::render_page( array(
			'title'           => get_admin_page_title() ?: __( 'Presets', 'meyvora-convert' ),
			'subtitle'        => '',
			'active_tab'      => 'meyvc-presets',
			'primary_action'  => null,
			'content_partial' => MEYVC_PLUGIN_DIR . 'admin/partials/meyvc-admin-presets.php',
			'wrap_class'      => 'meyvc-presets-page',
		) );
	}

	/**
	 * Campaign sequences admin.
	 */
	public function render_sequences() {
		MEYVC_Admin_UI::render_page( array(
			'title'           => get_admin_page_title() ?: __( 'Campaign sequences', 'meyvora-convert' ),
			'subtitle'        => __( 'After a visitor converts on a campaign, schedule follow-up campaigns.', 'meyvora-convert' ),
			'active_tab'      => 'meyvc-sequences',
			'primary_action'  => null,
			'content_partial' => MEYVC_PLUGIN_DIR . 'admin/partials/meyvc-admin-sequences.php',
			'wrap_class'      => 'meyvc-sequences-page',
		) );
	}

	/**
	 * Render dashboard (main CRO page). Shows onboarding wizard when onboarding=1 or when not yet completed.
	 */
	public function render_dashboard() {
		$onboarding_request   = self::is_meyvc_onboarding_query();
		$onboarding_completed = function_exists( 'meyvc_is_onboarding_complete' ) && meyvc_is_onboarding_complete();

		if ( $onboarding_request || ! $onboarding_completed ) {
			MEYVC_Admin_UI::render_page( array(
				'title'           => __( 'Welcome to Meyvora Convert', 'meyvora-convert' ),
				'subtitle'        => __( 'Complete these steps to get started. You can change settings anytime.', 'meyvora-convert' ),
				'active_tab'      => 'meyvora-convert',
				'primary_action'  => null,
				'content_partial' => MEYVC_PLUGIN_DIR . 'admin/partials/meyvc-admin-onboarding.php',
				'wrap_class'      => 'meyvc-onboarding-page',
			) );
			return;
		}

		$this->display_dashboard();
	}

	/**
	 * Render campaigns list page
	 */
	public function render_campaigns() {
		$this->display_campaigns();
	}

	/**
	 * Render campaign builder / editor (hidden menu)
	 */
	public function render_campaign_builder() {
		$this->display_campaign_editor();
	}

	/**
	 * Render on-page boosters page
	 */
	public function render_boosters() {
		$this->display_boosters();
	}

	/**
	 * Render cart optimizer page
	 */
	public function render_cart_optimizer() {
		$this->display_cart_optimizer();
	}

	/**
	 * Render Abandoned Carts list admin page.
	 */
	public function render_abandoned_carts_list() {
		MEYVC_Admin_UI::render_page( array(
			'title'           => get_admin_page_title() ?: __( 'Abandoned Carts', 'meyvora-convert' ),
			'subtitle'        => '',
			'active_tab'      => 'meyvc-abandoned-carts',
			'primary_action'  => array( 'label' => __( 'Settings', 'meyvora-convert' ), 'href' => admin_url( 'admin.php?page=meyvc-abandoned-cart' ) ),
			'content_partial' => MEYVC_PLUGIN_DIR . 'admin/partials/meyvc-admin-abandoned-carts-list.php',
			'wrap_class'      => 'meyvc-abandoned-carts-list',
		) );
	}

	/**
	 * Render Abandoned Cart Emails admin page (templates/settings).
	 */
	public function render_abandoned_cart_emails() {
		MEYVC_Admin_UI::render_page( array(
			'title'           => get_admin_page_title() ?: __( 'Abandoned Cart Emails', 'meyvora-convert' ),
			'subtitle'        => __( 'Configure reminder emails sent when a customer leaves items in their cart. Use the placeholders below in subject and body.', 'meyvora-convert' ),
			'active_tab'      => 'meyvc-abandoned-cart',
			'primary_action'  => array( 'label' => __( 'Save settings', 'meyvora-convert' ), 'form_id' => 'meyvc-abandoned-cart-form' ),
			'content_partial' => MEYVC_PLUGIN_DIR . 'admin/partials/meyvc-admin-abandoned-cart.php',
			'wrap_class'      => 'meyvc-abandoned-cart-emails',
		) );
	}

	/**
	 * Render offers page (dynamic offers config).
	 */
	public function render_offers() {
		$this->display_offers();
	}

	/**
	 * Render checkout optimizer page
	 */
	public function render_checkout_optimizer() {
		$this->display_checkout_optimizer();
	}

	/**
	 * Render settings page
	 */
	public function render_settings() {
		$this->display_settings();
	}

	/**
	 * Render A/B Tests list page
	 */
	public function render_ab_tests() {
		$this->handle_ab_test_actions();
		MEYVC_Admin_UI::render_page( array(
			'title'           => get_admin_page_title() ?: __( 'A/B Tests', 'meyvora-convert' ),
			'subtitle'        => '',
			'active_tab'      => 'meyvc-ab-tests',
			'primary_action'  => array( 'label' => __( 'New A/B Test', 'meyvora-convert' ), 'href' => admin_url( 'admin.php?page=meyvc-ab-test-new' ) ),
			'content_partial' => MEYVC_PLUGIN_DIR . 'admin/partials/meyvc-admin-ab-tests.php',
			'wrap_class'      => 'meyvc-ab-tests-page',
		) );
	}

	/**
	 * Render new A/B test page
	 */
	public function render_ab_test_new() {
		MEYVC_Admin_UI::render_page( array(
			'title'           => __( 'New A/B Test', 'meyvora-convert' ),
			'subtitle'        => '',
			'active_tab'      => 'meyvc-ab-tests',
			'content_partial' => MEYVC_PLUGIN_DIR . 'admin/partials/meyvc-admin-ab-test-new.php',
			'wrap_class'      => 'meyvc-ab-test-new',
		) );
	}

	/**
	 * Render A/B test detail page
	 */
	public function render_ab_test_view() {
		MEYVC_Admin_UI::render_page( array(
			'title'           => __( 'A/B Test', 'meyvora-convert' ),
			'subtitle'        => '',
			'active_tab'      => 'meyvc-ab-tests',
			'content_partial' => MEYVC_PLUGIN_DIR . 'admin/partials/meyvc-admin-ab-test-view.php',
			'wrap_class'      => 'meyvc-ab-test-view',
		) );
	}

	/**
	 * Render Analytics page (with layout and Export CSV header CTA).
	 */
	public function render_analytics() {
		$action = self::get_get_text( 'action' );
		if ( $action === 'export' ) {
			$this->handle_csv_export();
			return;
		}
		$date_from   = self::get_get_text( 'from' );
		$date_to     = self::get_get_text( 'to' );
		if ( $date_from === '' ) {
			$date_from = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		}
		if ( $date_to === '' ) {
			$date_to = gmdate( 'Y-m-d' );
		}
		$campaign_id = self::get_get_absint( 'campaign_id' );
		$campaign_id = $campaign_id > 0 ? $campaign_id : null;
		$export_url = add_query_arg(
			array(
				'page'     => 'meyvc-analytics',
				'action'   => 'export',
				'format'   => 'events',
				'from'     => $date_from,
				'to'       => $date_to,
				'_wpnonce' => wp_create_nonce( 'meyvc_export' ),
			),
			admin_url( 'admin.php' )
		);
		if ( $campaign_id !== null ) {
			$export_url = add_query_arg( 'campaign_id', $campaign_id, $export_url );
		}
		MEYVC_Admin_UI::render_page( array(
			'title'           => get_admin_page_title() ?: __( 'Analytics', 'meyvora-convert' ),
			'subtitle'        => __( 'Track impressions, conversions, and revenue from campaigns and offers.', 'meyvora-convert' ),
			'active_tab'      => 'meyvc-analytics',
			'primary_action'  => array( 'label' => __( 'Export events CSV', 'meyvora-convert' ), 'href' => $export_url ),
			'content_partial' => MEYVC_PLUGIN_DIR . 'admin/partials/meyvc-admin-analytics.php',
			'wrap_class'      => 'meyvc-analytics-page',
		) );
	}

	/**
	 * Render Insights page (actionable cards from tracking data).
	 */
	public function render_insights() {
		MEYVC_Admin_UI::render_page( array(
			'title'           => get_admin_page_title() ?: __( 'Insights', 'meyvora-convert' ),
			'subtitle'        => __( 'Actionable recommendations from your campaigns, offers, and boosters.', 'meyvora-convert' ),
			'active_tab'      => 'meyvc-insights',
			'primary_action'  => null,
			'content_partial' => MEYVC_PLUGIN_DIR . 'admin/partials/meyvc-admin-insights.php',
			'wrap_class'      => 'meyvc-insights-page',
			'header_pills'    => array( __( 'Last 7 days', 'meyvora-convert' ) ),
		) );
	}

	/**
	 * Register campaign builder script and style early (admin_init priority 1)
	 * so Verify Installation can detect wp_script_is('meyvc-campaign-builder', 'registered').
	 * Enqueue happens in enqueue_scripts() only on builder pages.
	 */
	public function register_campaign_builder_assets() {
		if ( ! wp_script_is( 'wp-api-fetch', 'registered' ) ) {
			wp_register_script(
				'wp-api-fetch',
				includes_url( 'js/api-fetch' . ( ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min' ) . '.js' ),
				array(),
				'20230710',
				true
			);
		}
		wp_register_script(
			'meyvc-sortable',
			MEYVC_PLUGIN_URL . 'admin/js/vendor/sortable.min.js',
			array(),
			'1.15.2',
			true
		);
		wp_register_script(
			'meyvc-campaign-builder',
			MEYVC_PLUGIN_URL . 'admin/js/meyvc-campaign-builder.js',
			array( 'jquery', 'meyvc-admin', 'wp-api-fetch', 'meyvc-sortable' ),
			MEYVC_VERSION,
			true
		);
		wp_register_style(
			'meyvc-campaign-builder',
			MEYVC_PLUGIN_URL . 'admin/css/meyvc-campaign-builder.css',
			array(),
			MEYVC_VERSION
		);
	}

	/**
	 * Register styles for CRO admin pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_styles( $hook ) {
		$hook = (string) ( $hook ?? '' );
		$page = self::get_get_text( 'page' );
		$is_meyvc_hook = ( strpos( $hook, 'meyvc-' ) !== false || strpos( $hook, 'meyvc_' ) !== false || strpos( $hook, 'meyvora-convert' ) !== false );
		$is_meyvc_page = ( $page !== '' && ( strpos( $page, 'meyvc-' ) !== false || strpos( $page, 'meyvc_' ) !== false || $page === 'meyvora-convert' ) );
		if ( ! $is_meyvc_hook && ! $is_meyvc_page ) {
			return;
		}

		// DM Sans from bundled WOFF2 (no external font requests).
		wp_enqueue_style(
			'meyvc-dm-sans-fonts',
			MEYVC_PLUGIN_URL . 'admin/css/meyvc-dm-sans-fonts.css',
			array(),
			MEYVC_VERSION
		);

		// Design system is source of truth (includes AI chat .meyvc-aichat-* styles). Enqueued for all Meyvora Convert admin screens that pass the guard above ($is_meyvc_hook || $is_meyvc_page), not only the campaign builder.
		wp_enqueue_style(
			'meyvc-admin-design-system',
			MEYVC_PLUGIN_URL . 'admin/css/meyvc-admin-design-system.css',
			array( 'meyvc-dm-sans-fonts' ),
			MEYVC_VERSION
		);

		wp_enqueue_style(
			'meyvc-admin-ui',
			MEYVC_PLUGIN_URL . 'admin/css/meyvc-admin-ui.css',
			array( 'meyvc-admin-design-system' ),
			MEYVC_VERSION
		);

		// SelectWoo/Select2: ensure available on all CRO admin pages.
		if ( ! wp_style_is( 'select2', 'registered' ) && class_exists( 'WooCommerce' ) ) {
			$select2_css = plugins_url( 'assets/css/select2.css', 'woocommerce/woocommerce.php' );
			if ( $select2_css ) {
				wp_register_style( 'select2', $select2_css, array(), '4.0.3' );
			}
		}
		if ( wp_style_is( 'select2', 'registered' ) ) {
			wp_enqueue_style( 'select2' );
		}

		// SelectWoo override: 42px height, dropdown z-index (only z-index uses !important).
		if ( wp_style_is( 'select2', 'registered' ) ) {
			wp_enqueue_style(
				'meyvc-admin-selectwoo-override',
				MEYVC_PLUGIN_URL . 'admin/css/meyvc-admin-selectwoo-override.css',
				array( 'select2', 'meyvc-admin-design-system' ),
				MEYVC_VERSION
			);
		}

		// Page-specific CSS only (design system provides base). Use both hook and page for hidden/submenu screens.
		$admin_page = self::get_get_text( 'page' );
		$is_analytics = ( strpos( $hook, 'meyvc-analytics' ) !== false || $admin_page === 'meyvc-analytics' );
		$is_offers    = ( strpos( $hook, 'meyvc-offers' ) !== false || $admin_page === 'meyvc-offers' );
		$is_insights  = ( strpos( $hook, 'meyvc-insights' ) !== false || $admin_page === 'meyvc-insights' );

		if ( $is_analytics ) {
			wp_enqueue_style(
				'meyvc-analytics',
				MEYVC_PLUGIN_URL . 'admin/css/meyvc-analytics.css',
				array( 'meyvc-admin-design-system' ),
				MEYVC_VERSION
			);
		}
		if ( $is_offers ) {
			wp_enqueue_style(
				'meyvc-offers',
				MEYVC_PLUGIN_URL . 'admin/css/meyvc-offers.css',
				array( 'meyvc-admin-design-system' ),
				MEYVC_VERSION
			);
		}
		if ( $is_insights ) {
			wp_enqueue_style(
				'meyvc-admin-insights',
				MEYVC_PLUGIN_URL . 'admin/css/meyvc-admin-insights.css',
				array( 'meyvc-admin-design-system' ),
				MEYVC_VERSION
			);
		}

		// Dashboard (main Meyvora Convert page): KPI cards, quick actions, activity list.
		if ( $hook === 'toplevel_page_meyvora-convert' ) {
			wp_enqueue_style(
				'meyvc-admin-dashboard',
				MEYVC_PLUGIN_URL . 'admin/css/meyvc-admin-dashboard.css',
				array( 'meyvc-admin-design-system' ),
				MEYVC_VERSION
			);
			$show_ob_wizard = function_exists( 'meyvc_is_onboarding_complete' ) && (
				! meyvc_is_onboarding_complete()
				|| self::is_meyvc_onboarding_query()
			);
			if ( $show_ob_wizard ) {
				wp_enqueue_style(
					'meyvc-onboarding-wizard',
					MEYVC_PLUGIN_URL . 'admin/css/meyvc-onboarding-wizard.css',
					array( 'meyvc-admin-design-system' ),
					MEYVC_VERSION
				);
			}
		}

		// Campaign builder/edit page (hidden submenu: hook can vary; check both hook and page).
		if ( strpos( $hook, 'meyvc-campaign-edit' ) !== false || strpos( $hook, 'meyvc-campaign-builder' ) !== false
			|| $admin_page === 'meyvc-campaign-edit' || $admin_page === 'meyvc-campaign-builder' ) {
			wp_enqueue_style(
				'meyvc-popup',
				MEYVC_PLUGIN_URL . 'public/css/meyvc-popup' . meyvc_asset_min_suffix() . '.css',
				array(),
				MEYVC_VERSION
			);
			wp_enqueue_style(
				'meyvc-campaign-builder',
				MEYVC_PLUGIN_URL . 'admin/css/meyvc-campaign-builder.css',
				array( 'meyvc-admin-design-system', 'meyvc-popup' ),
				MEYVC_VERSION
			);
		}

		wp_enqueue_style( 'wp-color-picker' );
	}

	/**
	 * Register scripts for CRO admin pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		$hook = (string) ( $hook ?? '' );
		$page = self::get_get_text( 'page' );
		$is_meyvc_hook = ( strpos( $hook, 'meyvc-' ) !== false || strpos( $hook, 'meyvc_' ) !== false || strpos( $hook, 'meyvora-convert' ) !== false );
		$is_meyvc_page = ( $page !== '' && ( strpos( $page, 'meyvc-' ) !== false || strpos( $page, 'meyvc_' ) !== false || $page === 'meyvora-convert' ) );
		if ( ! $is_meyvc_hook && ! $is_meyvc_page ) {
			return;
		}

		if ( in_array( $page, array( 'meyvc-analytics', 'meyvc-ab-test-view' ), true ) ) {
			wp_enqueue_script(
				'chart-js',
				MEYVC_PLUGIN_URL . 'admin/js/vendor/chart.umd.min.js',
				array(),
				'4.4.0',
				true
			);
		}

		if ( 'meyvc-sequences' === $page ) {
			wp_enqueue_script(
				'sortablejs',
				MEYVC_PLUGIN_URL . 'admin/js/vendor/sortable.min.js',
				array(),
				'1.15.2',
				true
			);
		}

		if ( $page === 'meyvc-analytics' ) {
			wp_enqueue_script(
				'meyvc-analytics-page',
				MEYVC_PLUGIN_URL . 'admin/js/meyvc-analytics-page.js',
				array( 'jquery', 'chart-js' ),
				MEYVC_VERSION,
				true
			);
			wp_localize_script(
				'meyvc-analytics-page',
				'meyvcAnalyticsPage',
				array(
					'nonce'               => wp_create_nonce( 'meyvc_admin_nonce' ),
					'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
					'setAttributionNonce' => wp_create_nonce( 'meyvc_set_attribution_model' ),
					'getRevenueNonce'     => wp_create_nonce( 'meyvc_get_campaign_revenue_chart' ),
				)
			);
		}

		if ( $page === 'meyvc-admin-developer' ) {
			wp_enqueue_script(
				'meyvc-developer-webhooks',
				MEYVC_PLUGIN_URL . 'admin/js/meyvc-developer-webhooks.js',
				array( 'jquery' ),
				MEYVC_VERSION,
				true
			);
			wp_localize_script(
				'meyvc-developer-webhooks',
				'meyvcDeveloperWebhooks',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'meyvc_webhooks_admin' ),
					'strings' => array(
						'confirmDelete' => __( 'Delete this webhook endpoint?', 'meyvora-convert' ),
						'testOk'        => __( 'Connection OK.', 'meyvora-convert' ),
						'testFail'      => __( 'Request failed.', 'meyvora-convert' ),
						'error'         => __( 'Something went wrong.', 'meyvora-convert' ),
						'loading'       => __( 'Loading…', 'meyvora-convert' ),
						'colTime'       => __( 'Time', 'meyvora-convert' ),
						'colEvent'      => __( 'Event', 'meyvora-convert' ),
						'colStatus'     => __( 'Status', 'meyvora-convert' ),
						'colMs'         => __( 'Response time (ms)', 'meyvora-convert' ),
						'colError'      => __( 'Error', 'meyvora-convert' ),
					),
				)
			);
		}

		$meyvc_admin_script_deps = array( 'jquery', 'wp-color-picker' );
		if ( 'meyvc-ab-test-view' === $page ) {
			$meyvc_admin_script_deps[] = 'chart-js';
		}
		wp_enqueue_script(
			'meyvc-admin',
			MEYVC_PLUGIN_URL . 'admin/js/meyvc-admin.js',
			$meyvc_admin_script_deps,
			MEYVC_VERSION,
			true
		);
		wp_enqueue_media();

		$meyvc_debug = get_option( 'meyvc_admin_debug', false ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG );
		$meyvc_admin_l10n = array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'adminUrl'  => admin_url( 'admin.php' ),
			'siteUrl'   => get_site_url(),
			'restUrl'   => rest_url( 'meyvora-convert/v1/' ),
			'nonce'     => wp_create_nonce( 'meyvc_admin_nonce' ),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
			'currency'       => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '',
			'priceDecimals'  => function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2,
			'debug'          => (bool) apply_filters( 'meyvc_admin_debug', $meyvc_debug ),
			'strings'   => array(
				'confirmDelete' => __( 'Are you sure?', 'meyvora-convert' ),
				'saving'        => __( 'Saving...', 'meyvora-convert' ),
				'saved'         => __( 'Saved!', 'meyvora-convert' ),
				'error'         => __( 'Error occurred', 'meyvora-convert' ),
				'selectImage'   => __( 'Select or Upload Image', 'meyvora-convert' ),
				'useImage'      => __( 'Use this image', 'meyvora-convert' ),
				'clickToUpload' => __( 'Click to upload', 'meyvora-convert' ),
				'previewError'  => __( 'Preview could not be opened. Please try again.', 'meyvora-convert' ),
				'copied'        => __( 'Copied!', 'meyvora-convert' ),
				'remove'        => __( 'Remove', 'meyvora-convert' ),
				'categoryShort' => __( 'Category…', 'meyvora-convert' ),
			),
			'copied_label' => __( 'Copied!', 'meyvora-convert' ),
		);
		if ( 'meyvc-settings' === $page ) {
			$meyvc_admin_l10n['aiTestNonce'] = wp_create_nonce( 'meyvc_ai_test_connection' );
			$meyvc_admin_l10n['aiStrings']   = array(
				'testing'  => __( 'Testing…', 'meyvora-convert' ),
				'testOk'   => __( 'Connection successful.', 'meyvora-convert' ),
				'testFail' => __( 'Connection failed.', 'meyvora-convert' ),
			);
			$meyvc_admin_l10n['aiApiKey']    = array(
				'verifiedSr' => __( 'API key saved and connection verified', 'meyvora-convert' ),
				'show'       => __( 'Show', 'meyvora-convert' ),
				'hide'       => __( 'Hide', 'meyvora-convert' ),
			);
		}
		wp_localize_script(
			'meyvc-admin',
			'meyvcAdmin',
			$meyvc_admin_l10n
		);

		if ( 'meyvc-abandoned-cart' === $page && function_exists( 'meyvc_settings' ) ) {
			$meyvc_ac_cart_json = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
			wp_add_inline_script(
				'meyvc-admin',
				'window.meyvcAbandonedCart = ' . wp_json_encode(
					array(
						'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
						'nonce'               => wp_create_nonce( 'meyvc_abandoned_cart_nonce' ),
						'defaultBodyTemplate' => meyvc_settings()->get_abandoned_cart_email_body_default(),
						'strings'             => array(
							'confirmReset'  => __( 'Replace the current body with the default template?', 'meyvora-convert' ),
							'emailRequired' => __( 'Please enter an email address.', 'meyvora-convert' ),
							'testSent'      => __( 'Test email sent.', 'meyvora-convert' ),
							'testFail'      => __( 'Failed to send.', 'meyvora-convert' ),
							'requestFail'   => __( 'Request failed. Please try again.', 'meyvora-convert' ),
						),
					),
					$meyvc_ac_cart_json
				) . ';',
				'before'
			);
		}

		$show_ob_wizard_scripts = 'meyvora-convert' === $page && function_exists( 'meyvc_is_onboarding_complete' ) && (
			! meyvc_is_onboarding_complete()
			|| self::is_meyvc_onboarding_query()
		);
		if ( $show_ob_wizard_scripts ) {
			wp_enqueue_script(
				'meyvc-onboarding-wizard',
				MEYVC_PLUGIN_URL . 'admin/js/meyvc-onboarding-wizard.js',
				array( 'jquery' ),
				MEYVC_VERSION,
				true
			);
			wp_localize_script(
				'meyvc-onboarding-wizard',
				'meyvcOnboardingWizard',
				array(
					'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
					'nonce'        => wp_create_nonce( 'meyvc_admin_nonce' ),
					'dashboardUrl' => admin_url( 'admin.php?page=meyvora-convert' ),
					'checklists'   => array(
						'recover_abandoned' => array(
							__( 'Abandoned cart emails are enabled — review templates under Abandoned Cart Emails.', 'meyvora-convert' ),
							__( 'Spot-check sticky cart and shipping bar on a product and cart page.', 'meyvora-convert' ),
							__( 'Open Campaigns and preview your new recovery popups.', 'meyvora-convert' ),
							__( 'Run a test order to confirm tracking and recovery flows.', 'meyvora-convert' ),
							__( 'Optional: connect webhooks or AI insights in Settings.', 'meyvora-convert' ),
						),
						'grow_email'        => array(
							__( 'Confirm new-visitor popups look on-brand in the campaign builder.', 'meyvora-convert' ),
							__( 'Trust badges are on — verify they appear on product pages.', 'meyvora-convert' ),
							__( 'Test email capture from an incognito window.', 'meyvora-convert' ),
							__( 'Review frequency caps under campaign or global settings if popups feel heavy.', 'meyvora-convert' ),
							__( 'Optional: enable AI copy suggestions for faster iterations.', 'meyvora-convert' ),
						),
						'increase_aov'     => array(
							__( 'Check the shipping bar threshold matches your free-shipping rules.', 'meyvora-convert' ),
							__( 'Browse a product on mobile to see the sticky add-to-cart bar.', 'meyvora-convert' ),
							__( 'Edit the cart boost campaign copy to match your offers.', 'meyvora-convert' ),
							__( 'Add trust badges content (returns, guarantee) in Boosters.', 'meyvora-convert' ),
							__( 'Watch revenue influenced on the dashboard after a few days.', 'meyvora-convert' ),
						),
						'reduce_checkout'  => array(
							__( 'Open Checkout settings and adjust trust messaging if needed.', 'meyvora-convert' ),
							__( 'Walk through checkout as a customer — confirm reassurance popup timing.', 'meyvora-convert' ),
							__( 'Review the cart scroll nudge campaign and CTA link.', 'meyvora-convert' ),
							__( 'Enable payment methods you advertise in trust copy.', 'meyvora-convert' ),
							__( 'Optional: run an A/B test on checkout headline copy later.', 'meyvora-convert' ),
						),
						'default'          => array(
							__( 'Review new campaigns under Campaigns.', 'meyvora-convert' ),
							__( 'Confirm boosted features under On-Page Boosters.', 'meyvora-convert' ),
							__( 'Check Settings → General for global toggles.', 'meyvora-convert' ),
							__( 'Place a test order to validate the full funnel.', 'meyvora-convert' ),
							__( 'Explore analytics after you have a few days of traffic.', 'meyvora-convert' ),
						),
					),
					'strings'      => array(
						'error'    => __( 'Something went wrong. Please try again.', 'meyvora-convert' ),
						'ajaxFail' => __( 'Could not reach the server. Check your connection and try again.', 'meyvora-convert' ),
					),
				)
			);
		}

		$ai_panel_pages = array( 'meyvora-convert', 'meyvc-campaigns', 'meyvc-offers' );
		if ( in_array( $page, $ai_panel_pages, true ) ) {
			wp_enqueue_style(
				'meyvc-admin-ai-panel',
				MEYVC_PLUGIN_URL . 'admin/css/meyvc-admin-ai-panel.css',
				array( 'meyvc-admin-design-system' ),
				MEYVC_VERSION
			);
			wp_enqueue_script(
				'meyvc-admin-ai-panel',
				MEYVC_PLUGIN_URL . 'admin/js/meyvc-admin-ai-panel.js',
				array( 'jquery' ),
				MEYVC_VERSION,
				true
			);
			wp_localize_script(
				'meyvc-admin-ai-panel',
				'meyvcAiPanel',
				array(
					'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
					'nonce'             => wp_create_nonce( 'meyvc_admin_nonce' ),
					'insightsUrl'       => admin_url( 'admin.php?page=meyvc-insights' ),
					'offersUrl'         => admin_url( 'admin.php?page=meyvc-offers' ),
					'campaignNewUrl'    => admin_url( 'admin.php?page=meyvc-campaign-edit' ),
					'nonceAiAnalyse'    => wp_create_nonce( 'meyvc_ai_analyse' ),
					'nonceSuggestOffer' => wp_create_nonce( 'meyvc_ai_suggest_offer' ),
					'nonceGenerateCopy' => wp_create_nonce( 'meyvc_ai_generate_copy' ),
					'settingsAiLink'    => sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url( admin_url( 'admin.php?page=meyvc-settings&settings_tab=ai' ) ),
						esc_html__( 'Add API key in Settings → AI', 'meyvora-convert' )
					),
					'strings'           => array(
						'close'           => __( 'Close', 'meyvora-convert' ),
						'apply'           => __( 'Apply this', 'meyvora-convert' ),
						'aiReady'         => __( 'AI is configured.', 'meyvora-convert' ),
						'noInsight'       => __( 'No rule-based insights yet. Collect more traffic or open the Insights tab.', 'meyvora-convert' ),
						'error'           => __( 'Something went wrong.', 'meyvora-convert' ),
						'suggestionTitle' => __( 'Suggested offer', 'meyvora-convert' ),
						'createOffer'     => __( 'Create this offer', 'meyvora-convert' ),
						'needGoal'        => __( 'Describe your popup goal first.', 'meyvora-convert' ),
						'useThis'         => __( 'Use this in campaign builder', 'meyvora-convert' ),
					),
				)
			);
		}

		if ( 'meyvora-convert' === $page ) {
			wp_enqueue_script(
				'meyvc-dashboard-actions',
				MEYVC_PLUGIN_URL . 'admin/js/meyvc-dashboard-actions.js',
				array( 'jquery' ),
				MEYVC_VERSION,
				true
			);
			wp_localize_script(
				'meyvc-dashboard-actions',
				'meyvcDashboardActions',
				array(
					'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
					'insightsUrl'       => admin_url( 'admin.php?page=meyvc-insights' ),
					'offersUrl'         => admin_url( 'admin.php?page=meyvc-offers' ),
					'nonceAiAnalyse'    => wp_create_nonce( 'meyvc_ai_analyse' ),
					'nonceSuggestOffer' => wp_create_nonce( 'meyvc_ai_suggest_offer' ),
					'strings'           => array(
						'error'       => __( 'Something went wrong.', 'meyvora-convert' ),
						'openOffers'  => __( 'Open the Offers page to create this offer?', 'meyvora-convert' ),
					),
				)
			);
			wp_localize_script(
				'meyvc-dashboard-actions',
				'meyvcDashboardLive',
				array(
					'nonce'   => wp_create_nonce( 'meyvc_admin_nonce' ),
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				)
			);
		}

		wp_enqueue_script(
			'meyvc-ai-chat',
			MEYVC_PLUGIN_URL . 'admin/js/meyvc-ai-chat.js',
			array( 'jquery' ),
			MEYVC_VERSION,
			true
		);
		$ai_chat_ready = class_exists( 'MEYVC_AI_Client' )
			&& MEYVC_AI_Client::is_configured()
			&& function_exists( 'meyvc_settings' )
			&& 'yes' === meyvc_settings()->get( 'ai', 'feature_chat', 'yes' );
		wp_localize_script(
			'meyvc-ai-chat',
			'meyvcAiChat',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'meyvc_ai_chat' ),
				'action'  => 'meyvc_ai_chat',
				'aiReady' => (bool) $ai_chat_ready,
				'strings' => array(
					'configure' => __( 'Add your Anthropic API key in Settings → AI.', 'meyvora-convert' ),
					'error'     => __( 'Something went wrong. Please try again.', 'meyvora-convert' ),
				),
			)
		);

		// Campaign builder: enqueue on builder pages only (script/style registered in register_campaign_builder_assets).
		$current_page_slug = self::get_get_text( 'page' );
		$is_builder_page = ( strpos( $hook, 'meyvc-campaign-edit' ) !== false )
			|| ( strpos( $hook, 'meyvc-campaign-builder' ) !== false )
			|| $current_page_slug === 'meyvc-campaign-edit'
			|| $current_page_slug === 'meyvc-campaign-builder';

		if ( $is_builder_page ) {
			wp_enqueue_media();
			wp_enqueue_script( 'wp-api-fetch' );
			wp_enqueue_style(
				'meyvc-popup',
				MEYVC_PLUGIN_URL . 'public/css/meyvc-popup' . meyvc_asset_min_suffix() . '.css',
				array(),
				MEYVC_VERSION
			);
			wp_enqueue_style(
				'meyvc-campaign-builder',
				MEYVC_PLUGIN_URL . 'admin/css/meyvc-campaign-builder.css',
				array( 'meyvc-admin-design-system', 'meyvc-popup' ),
				MEYVC_VERSION
			);
			wp_enqueue_script( 'meyvc-campaign-builder' );
			wp_enqueue_script(
				'meyvc-ai-copy',
				MEYVC_PLUGIN_URL . 'admin/js/meyvc-ai-copy.js',
				array( 'jquery', 'meyvc-campaign-builder' ),
				MEYVC_VERSION,
				true
			);
			$meyvc_ai_copy_debug = get_option( 'meyvc_admin_debug', false ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG );
			wp_localize_script(
				'meyvc-ai-copy',
				'meyvcAiCopy',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'meyvc_ai_generate_copy' ),
					'action'  => 'meyvc_ai_generate_copy',
					'debug'   => (bool) apply_filters( 'meyvc_admin_debug', $meyvc_ai_copy_debug ),
					'strings' => array(
						'goalRequired'  => __( 'Please describe your campaign goal.', 'meyvora-convert' ),
						'genericError'  => __( 'Something went wrong. Please try again.', 'meyvora-convert' ),
					),
				)
			);
			wp_localize_script(
				'meyvc-campaign-builder',
				'meyvcBuilderIcons',
				array(
					'remove' => class_exists( 'MEYVC_Icons' ) ? wp_kses( MEYVC_Icons::svg( 'x', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ) : '×',
					'upload' => class_exists( 'MEYVC_Icons' ) ? wp_kses( MEYVC_Icons::svg( 'upload', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ) : '',
					'image'  => class_exists( 'MEYVC_Icons' ) ? wp_kses( MEYVC_Icons::svg( 'image', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ) : '',
					'check'  => class_exists( 'MEYVC_Icons' ) ? wp_kses( MEYVC_Icons::svg( 'check', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ) : '',
				)
			);
			wp_localize_script(
				'meyvc-campaign-builder',
				'meyvcBuilderAjax',
				array(
					'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
					'nonce'          => wp_create_nonce( 'meyvc_admin_nonce' ),
					'wheelWinLabel'  => __( 'Win', 'meyvora-convert' ),
					'wheelLoseLabel' => __( 'Lose', 'meyvora-convert' ),
					'strings'        => array(
						'showIframePreview' => __( 'Show live preview (iframe)', 'meyvora-convert' ),
						'hideIframePreview' => __( 'Hide live preview (iframe)', 'meyvora-convert' ),
					),
				)
			);
			wp_localize_script(
				'meyvc-campaign-builder',
				'meyvcCampaignBuilder',
				array(
					'restRoot'  => esc_url_raw( rest_url() ),
					'restNonce' => wp_create_nonce( 'wp_rest' ),
				)
			);
		}

		// Abandoned carts list + email settings: AI email preview modal.
		if ( 'meyvc-abandoned-carts' === $page || 'meyvc-abandoned-cart' === $page ) {
			wp_enqueue_script(
				'meyvc-ai-email-preview',
				MEYVC_PLUGIN_URL . 'admin/js/meyvc-ai-email-preview.js',
				array( 'jquery' ),
				MEYVC_VERSION,
				true
			);
			wp_localize_script(
				'meyvc-ai-email-preview',
				'meyvcAiEmailPreview',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonces'  => array(
						'preview' => wp_create_nonce( 'meyvc_ai_preview_email' ),
						'bust'    => wp_create_nonce( 'meyvc_ai_bust_email_cache' ),
					),
					'actions' => array(
						'preview' => 'meyvc_ai_preview_email',
						'bust'    => 'meyvc_ai_bust_email_cache',
					),
					'strings' => array(
						'loading'     => __( 'Loading…', 'meyvora-convert' ),
						'error'       => __( 'Could not load preview.', 'meyvora-convert' ),
						'regenerate'  => __( 'Regenerate', 'meyvora-convert' ),
						'close'       => __( 'Close', 'meyvora-convert' ),
						'preheader'   => __( 'Preheader', 'meyvora-convert' ),
						'subject'     => __( 'Subject', 'meyvora-convert' ),
						'body'        => __( 'Body', 'meyvora-convert' ),
						'modalTitle'  => __( 'AI abandoned cart email', 'meyvora-convert' ),
						'needCartId'  => __( 'Enter a valid abandoned cart ID (from the Abandoned Carts list).', 'meyvora-convert' ),
					),
				)
			);
		}

		if ( 'meyvc-insights' === $page ) {
			wp_enqueue_script(
				'meyvc-insights-page',
				MEYVC_PLUGIN_URL . 'admin/js/meyvc-insights-page.js',
				array( 'jquery' ),
				MEYVC_VERSION,
				true
			);
			wp_enqueue_script(
				'meyvc-ai-insights',
				MEYVC_PLUGIN_URL . 'admin/js/meyvc-ai-insights.js',
				array( 'jquery' ),
				MEYVC_VERSION,
				true
			);
			$ai_insights_ready = class_exists( 'MEYVC_AI_Client' ) && MEYVC_AI_Client::is_configured()
				&& function_exists( 'meyvc_settings' ) && 'yes' === meyvc_settings()->get( 'ai', 'feature_insights', 'yes' );
			wp_localize_script(
				'meyvc-ai-insights',
				'meyvcAiInsights',
				array(
					'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
					'nonces'   => array(
						'analyse' => wp_create_nonce( 'meyvc_ai_analyse' ),
						'bust'    => wp_create_nonce( 'meyvc_ai_bust_insights_cache' ),
					),
					'actions'  => array(
						'analyse' => 'meyvc_ai_analyse',
						'bust'    => 'meyvc_ai_bust_insights_cache',
					),
					'aiReady'  => (bool) $ai_insights_ready,
					'strings'  => array(
						'loading'        => __( 'Analysing…', 'meyvora-convert' ),
						'error'          => __( 'Something went wrong. Please try again.', 'meyvora-convert' ),
						/* translators: %s: number of seconds (decimal). */
						'generatedIn'    => __( 'Generated in %ss', 'meyvora-convert' ),
						'cached'         => __( 'Cached', 'meyvora-convert' ),
						'clearCache'     => __( 'Clear cache', 'meyvora-convert' ),
						/* translators: %d: cooldown seconds (integer). */
						'waitSeconds'    => __( 'Please wait %d seconds before another analysis.', 'meyvora-convert' ),
						'configureAi'    => __( 'Configure your API key and enable AI insights under Settings → AI.', 'meyvora-convert' ),
						'emptyHint'      => __( 'Click “Analyse with AI” to generate insights from your data.', 'meyvora-convert' ),
						'cacheCleared'   => __( 'Cache cleared. Run an analysis to fetch new insights.', 'meyvora-convert' ),
					),
				)
			);
		}

		if ( 'meyvc-campaigns' === $page ) {
			wp_enqueue_script(
				'meyvc-ai-ab-hypothesis',
				MEYVC_PLUGIN_URL . 'admin/js/meyvc-ai-ab-hypothesis.js',
				array( 'jquery' ),
				MEYVC_VERSION,
				true
			);
			$ai_ab_ready = class_exists( 'MEYVC_AI_Client' ) && MEYVC_AI_Client::is_configured()
				&& function_exists( 'meyvc_settings' ) && 'yes' === meyvc_settings()->get( 'ai', 'feature_ab', 'yes' );
			wp_localize_script(
				'meyvc-ai-ab-hypothesis',
				'meyvcAiAbHypothesis',
				array(
					'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
					'newAbBaseUrl' => admin_url( 'admin.php?page=meyvc-ab-test-new' ),
					'nonce'        => wp_create_nonce( 'meyvc_ai_ab_hypothesis' ),
					'action'       => 'meyvc_ai_ab_hypothesis',
					'aiReady'      => (bool) $ai_ab_ready,
					'strings'      => array(
						'loading'        => __( 'Generating ideas…', 'meyvora-convert' ),
						'error'          => __( 'Could not load AI suggestions.', 'meyvora-convert' ),
						'before'         => __( 'Current', 'meyvora-convert' ),
						'after'          => __( 'Proposed', 'meyvora-convert' ),
						'startTest'      => __( 'Start test with this variant', 'meyvora-convert' ),
						'configure'      => __( 'Enable AI A/B features and add an API key under Settings → AI.', 'meyvora-convert' ),
						'hypothesis'     => __( 'Hypothesis', 'meyvora-convert' ),
						'changeSummary'  => __( 'Change', 'meyvora-convert' ),
						'changeType'     => __( 'Focus', 'meyvora-convert' ),
						'headline'       => __( 'Headline', 'meyvora-convert' ),
						'body'           => __( 'Body', 'meyvora-convert' ),
						'cta'            => __( 'CTA', 'meyvora-convert' ),
					),
				)
			);
		}

		// SelectWoo + meyvc-selectwoo.js: enqueue on ALL CRO admin pages (caller already filtered by hook meyvc- or meyvc_).
		if ( ! wp_script_is( 'selectWoo', 'registered' ) && class_exists( 'WooCommerce' ) ) {
			$selectwoo_js = plugins_url( 'assets/js/selectWoo/selectWoo.full.min.js', 'woocommerce/woocommerce.php' );
			if ( $selectwoo_js ) {
				wp_register_script( 'selectWoo', $selectwoo_js, array( 'jquery' ), '1.0.6', true );
			}
		}
		if ( wp_script_is( 'selectWoo', 'registered' ) ) {
			wp_enqueue_script( 'selectWoo' );
			wp_enqueue_script(
				'meyvc-selectwoo',
				MEYVC_PLUGIN_URL . 'admin/js/meyvc-selectwoo.js',
				array( 'jquery', 'selectWoo' ),
				MEYVC_VERSION,
				true
			);
			wp_localize_script(
				'meyvc-selectwoo',
				'meyvcSelectWoo',
				array(
					'placeholder'    => __( 'Search or select…', 'meyvora-convert' ),
					'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
					'searchProducts'  => __( 'Search products…', 'meyvora-convert' ),
				)
			);
		}

		// Offers page: drawer + AJAX save. Use page slug so it loads when page=meyvc-offers.
		$admin_page_scripts = self::get_get_text( 'page' );
		if ( strpos( $hook, 'meyvc-offers' ) !== false || $admin_page_scripts === 'meyvc-offers' ) {
			wp_enqueue_script(
				'meyvc-offers',
				MEYVC_PLUGIN_URL . 'admin/js/meyvc-offers.js',
				array( 'jquery' ),
				MEYVC_VERSION,
				true
			);
			wp_localize_script(
				'meyvc-offers',
				'meyvcOffersI18n',
				array(
					'addOffer'        => __( 'Add Offer', 'meyvora-convert' ),
					'editOffer'       => __( 'Edit Offer', 'meyvora-convert' ),
					'nameRequired'    => __( 'Offer name is required.', 'meyvora-convert' ),
					'priorityInteger' => __( 'Priority must be a number.', 'meyvora-convert' ),
					'percent1To100'   => __( 'Percent discount must be between 1 and 100.', 'meyvora-convert' ),
					'fixedMinZero'    => __( 'Fixed discount must be 0 or greater.', 'meyvora-convert' ),
					'ttlMin1'         => __( 'Coupon TTL must be at least 1 hour.', 'meyvora-convert' ),
					'saving'          => __( 'Saving...', 'meyvora-convert' ),
					'saved'           => __( 'Offer saved.', 'meyvora-convert' ),
					'error'           => __( 'Error occurred', 'meyvora-convert' ),
					'active'          => __( 'Active', 'meyvora-convert' ),
					'inactive'        => __( 'Inactive', 'meyvora-convert' ),
					'edit'            => __( 'Edit', 'meyvora-convert' ),
					'duplicate'       => __( 'Duplicate', 'meyvora-convert' ),
					'delete'          => __( 'Delete', 'meyvora-convert' ),
					'offer'             => __( 'Offer', 'meyvora-convert' ),
					'deleteConfirm'     => __( 'Delete this offer?', 'meyvora-convert' ),
					/* translators: %s is the offer name. */
					'deleteConfirmName' => __( 'Delete offer "%s"?', 'meyvora-convert' ),
					'close'             => __( 'Close', 'meyvora-convert' ),
					'notifications'     => __( 'Notifications', 'meyvora-convert' ),
					'offersUsed'      => __( 'offers used', 'meyvora-convert' ),
					'limitReached'    => __( 'Offer limit reached (5).', 'meyvora-convert' ),
					'noOffersYet'     => __( 'No offers yet', 'meyvora-convert' ),
					'emptyDesc'       => __( 'Create your first offer to show a dynamic reward on cart and checkout.', 'meyvora-convert' ),
					'createFirst'     => __( 'Create your first offer', 'meyvora-convert' ),
					/* translators: %s is the priority value. */
					'priorityLabel'   => __( 'Priority: %s', 'meyvora-convert' ),
					'reorderNonce'    => wp_create_nonce( 'meyvc_offers_ajax' ),
					'reorderSaved'    => __( 'Order saved.', 'meyvora-convert' ),
					'reorderError'    => __( 'Could not save order.', 'meyvora-convert' ),
					'dragToReorder'   => __( 'Drag to reorder', 'meyvora-convert' ),
					'moveUp'           => __( 'Move up', 'meyvora-convert' ),
					'moveDown'         => __( 'Move down', 'meyvora-convert' ),
					'duplicatedNotice' => __( 'Offer duplicated.', 'meyvora-convert' ),
					'deletedNotice'    => __( 'Offer deleted.', 'meyvora-convert' ),
					'runTest'         => __( 'Run Test', 'meyvora-convert' ),
					'runTestLabel'    => __( 'Running...', 'meyvora-convert' ),
					'matchingOffer'   => __( 'Matching offer:', 'meyvora-convert' ),
					'name'            => __( 'Name', 'meyvora-convert' ),
					'rule'            => __( 'Rule', 'meyvora-convert' ),
					'reward'          => __( 'Reward', 'meyvora-convert' ),
					'why'             => __( 'Checks:', 'meyvora-convert' ),
					'noOfferMatches'  => __( 'No offer matches this context.', 'meyvora-convert' ),
					'noEligibleOffer' => __( 'No eligible offer', 'meyvora-convert' ),
					'suggestionsLabel'=> __( 'Suggestions:', 'meyvora-convert' ),
					'expectedLabel'   => __( 'Expected', 'meyvora-convert' ),
					'actualLabel'     => __( 'Actual', 'meyvora-convert' ),
					/* translators: %s is the formatted minimum cart total amount. */
					'summaryCartMin'   => __( 'Cart ≥ %s', 'meyvora-convert' ),
					/* translators: %1$s is the minimum cart total, %2$s is the maximum cart total. */
					'summaryCartRange' => __( 'Cart %1$s – %2$s', 'meyvora-convert' ),
					/* translators: %d is the number of cart items. */
					'summaryItems'     => __( '%d items', 'meyvora-convert' ),
					'summaryFirstTime' => __( 'First-time customer', 'meyvora-convert' ),
					/* translators: %d is the minimum number of previous orders. */
					'summaryReturning' => __( 'Returning customer (≥%d orders)', 'meyvora-convert' ),
					/* translators: %s is the formatted minimum lifetime spend amount. */
					'summaryLifetime'  => __( 'Lifetime spend ≥ %s', 'meyvora-convert' ),
					/* translators: %s is the percentage discount value. */
					'summaryRewardPct' => __( '%s%% off', 'meyvora-convert' ),
					/* translators: %s is the formatted fixed discount amount. */
					'summaryRewardFix' => __( '%s off', 'meyvora-convert' ),
					'summaryRewardShip'=> __( 'Free shipping', 'meyvora-convert' ),
					/* translators: %s is the number of hours until expiry. */
					'summaryExpires'   => __( 'Expires %sh', 'meyvora-convert' ),
					'summaryExcludeSale' => __( 'Exclude sale items', 'meyvora-convert' ),
					'summaryBullet'    => ' • ',
					'summaryArrow'     => ' → ',
					'newOffer'         => __( 'New offer', 'meyvora-convert' ),
					'checkIcon'        => class_exists( 'MEYVC_Icons' ) ? wp_kses( MEYVC_Icons::svg( 'check', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ) : '✓',
					'crossIcon'         => class_exists( 'MEYVC_Icons' ) ? wp_kses( MEYVC_Icons::svg( 'x', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ) : '✗',
					'moveUpIcon'        => class_exists( 'MEYVC_Icons' ) ? wp_kses( MEYVC_Icons::svg( 'chevron-up', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ) : '↑',
					'moveDownIcon'      => class_exists( 'MEYVC_Icons' ) ? wp_kses( MEYVC_Icons::svg( 'chevron-down', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ) : '↓',
					'editIcon'          => class_exists( 'MEYVC_Icons' ) ? wp_kses( MEYVC_Icons::svg( 'pencil', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ) : '',
					'duplicateIcon'     => class_exists( 'MEYVC_Icons' ) ? wp_kses( MEYVC_Icons::svg( 'plus', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ) : '',
					'deleteIcon'        => class_exists( 'MEYVC_Icons' ) ? wp_kses( MEYVC_Icons::svg( 'trash', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ) : '',
					'checkConflicts'    => __( 'Check for conflicts', 'meyvora-convert' ),
					'checkConflictsRunning' => __( 'Checking…', 'meyvora-convert' ),
					'noConflictCycles'  => __( 'No circular conflict chains found among active offers.', 'meyvora-convert' ),
					'checkConflictsFail' => __( 'Could not check conflicts.', 'meyvora-convert' ),
					'conflictsLabel'    => __( 'Conflicts', 'meyvora-convert' ),
					/* translators: %d: conflict count */
					'conflictsBadge'    => __( '%d conflicts', 'meyvora-convert' ),
					'dismiss'           => __( 'Dismiss', 'meyvora-convert' ),
				)
			);

			$offers_opt   = get_option( 'meyvc_dynamic_offers', array() );
			$offers_opt   = is_array( $offers_opt ) ? array_pad( $offers_opt, 5, array() ) : array_pad( array(), 5, array() );
			$offers_used  = 0;
			$first_empty  = 0;
			$found_empty  = false;
			for ( $oi = 0; $oi < 5; $oi++ ) {
				$oo = isset( $offers_opt[ $oi ] ) && is_array( $offers_opt[ $oi ] ) ? $offers_opt[ $oi ] : array();
				if ( ! empty( trim( (string) ( $oo['headline'] ?? '' ) ) ) ) {
					++$offers_used;
				} elseif ( ! $found_empty ) {
					$first_empty = $oi;
					$found_empty = true;
				}
			}
			wp_enqueue_script(
				'meyvc-ai-offer-suggest',
				MEYVC_PLUGIN_URL . 'admin/js/meyvc-ai-offer-suggest.js',
				array( 'jquery', 'meyvc-offers' ),
				MEYVC_VERSION,
				true
			);
			$ai_offer_ready = class_exists( 'MEYVC_AI_Client' ) && MEYVC_AI_Client::is_configured()
				&& function_exists( 'meyvc_settings' ) && 'yes' === meyvc_settings()->get( 'ai', 'feature_offers', 'yes' )
				&& class_exists( 'WooCommerce' );
			wp_localize_script(
				'meyvc-ai-offer-suggest',
				'meyvcAiOfferSuggest',
				array(
					'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
					'nonce'          => wp_create_nonce( 'meyvc_ai_suggest_offer' ),
					'action'         => 'meyvc_ai_suggest_offer',
					'aiReady'        => (bool) $ai_offer_ready,
					'firstEmptySlot' => (int) $first_empty,
					'offersUsed'     => (int) $offers_used,
					'maxOffers'      => 5,
					'atCapacity'     => $offers_used >= 5,
					'strings'        => array(
						'loading'       => __( 'Analysing your offers…', 'meyvora-convert' ),
						'error'         => __( 'Could not get a suggestion. Please try again.', 'meyvora-convert' ),
						'createOffer'   => __( 'Create this offer', 'meyvora-convert' ),
						'conditionLbl'  => __( 'Condition', 'meyvora-convert' ),
						'discountLbl'   => __( 'Discount', 'meyvora-convert' ),
						'impactLbl'     => __( 'Expected impact', 'meyvora-convert' ),
						'prefillNotice' => __( 'Pre-filled by AI · Review before saving', 'meyvora-convert' ),
						'full'          => __( 'All offer slots are full. Delete or edit an existing offer first.', 'meyvora-convert' ),
						'configure'     => __( 'Add your API key and enable AI offers under Settings → AI.', 'meyvora-convert' ),
					),
				)
			);
		}
	}

	/**
	 * Enqueue Classic Editor campaign button script and thickbox only on post edit screens.
	 * Only for users with manage_meyvora_convert.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_classic_editor_campaign_assets( $hook ) {
		$hook = (string) ( $hook ?? '' );
		if ( $hook !== 'post.php' && $hook !== 'post-new.php' ) {
			return;
		}
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			return;
		}

		wp_enqueue_script( 'thickbox' );
		wp_enqueue_style( 'thickbox' );

		wp_enqueue_script(
			'meyvc-classic-editor-campaign',
			MEYVC_PLUGIN_URL . 'admin/js/meyvc-classic-editor-campaign.js',
			array( 'jquery', 'thickbox' ),
			MEYVC_VERSION,
			true
		);

		wp_localize_script(
			'meyvc-classic-editor-campaign',
			'meyvcCampaignClassic',
			array(
				'modalTitle' => __( 'Insert CRO Campaign', 'meyvora-convert' ),
			)
		);
	}

	/**
	 * Add "Add CRO Campaign" button above editor (media_buttons). Only for users with manage_meyvora_convert.
	 */
	public function render_media_button_meyvc_campaign() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			return;
		}

		$campaigns = array();
		if ( class_exists( 'MEYVC_Campaign' ) ) {
			$rows = MEYVC_Campaign::get_all( array( 'limit' => 200 ) );
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$campaigns[] = array(
						'id'     => isset( $row['id'] ) ? (int) $row['id'] : 0,
						'name'   => isset( $row['name'] ) ? (string) $row['name'] : '',
						'status' => isset( $row['status'] ) ? (string) $row['status'] : 'draft',
					);
				}
			}
		}

		?>
		<button type="button" id="meyvc-insert-campaign-btn" class="button" title="<?php esc_attr_e( 'Insert a Meyvora Convert campaign shortcode', 'meyvora-convert' ); ?>">
			<?php echo class_exists( 'MEYVC_Icons' ) ? wp_kses( MEYVC_Icons::svg( 'plus', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ) : ''; ?>
			<?php esc_html_e( 'Add CRO Campaign', 'meyvora-convert' ); ?>
		</button>
		<div id="meyvc-campaign-modal-content" class="meyvc-is-hidden">
			<div class="meyvc-campaign-modal-inner">
				<p>
					<label for="meyvc-campaign-select"><?php esc_html_e( 'Select campaign:', 'meyvora-convert' ); ?></label>
				</p>
				<select id="meyvc-campaign-select" class="meyvc-modern-select meyvc-selectwoo" data-placeholder="<?php esc_attr_e( '— Select campaign —', 'meyvora-convert' ); ?>">
					<option value="0"><?php esc_html_e( '— Select campaign —', 'meyvora-convert' ); ?></option>
					<?php foreach ( $campaigns as $c ) : ?>
						<option value="<?php echo esc_attr( (string) $c['id'] ); ?>">
							<?php echo esc_html( $c['name'] ? $c['name'] : sprintf( /* translators: %d is the campaign ID number. */ __( 'Campaign #%d', 'meyvora-convert' ), $c['id'] ) ); ?>
							<?php if ( $c['status'] !== 'active' ) : ?>
								(<?php echo esc_html( $c['status'] ); ?>)
							<?php endif; ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="meyvc-modal-actions">
					<button type="button" id="meyvc-campaign-insert" class="button button-primary"><?php esc_html_e( 'Insert shortcode', 'meyvora-convert' ); ?></button>
					<button type="button" class="button" onclick="tb_remove();"><?php esc_html_e( 'Cancel', 'meyvora-convert' ); ?></button>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Display dashboard page.
	 */
	public function display_dashboard() {
		MEYVC_Admin_UI::render_page( array(
			'title'           => __( 'Meyvora Convert Dashboard', 'meyvora-convert' ),
			'subtitle'        => __( 'Overview of conversions, revenue, and active conversion tools.', 'meyvora-convert' ),
			'active_tab'      => 'meyvora-convert',
			'primary_action'  => null,
			'content_partial' => MEYVC_PLUGIN_DIR . 'admin/partials/meyvc-admin-dashboard.php',
			'wrap_class'      => 'meyvc-dashboard',
		) );
	}

	/**
	 * Display campaigns list page.
	 */
	public function display_campaigns() {
		$pills = array();
		if ( class_exists( 'MEYVC_Campaign' ) && method_exists( 'MEYVC_Campaign', 'get_all' ) ) {
			$campaigns = MEYVC_Campaign::get_all();
			$campaigns = is_array( $campaigns ) ? $campaigns : array();
			$active = 0;
			foreach ( $campaigns as $c ) {
				if ( isset( $c['status'] ) && $c['status'] === 'active' ) {
					$active++;
				}
			}
			$pills[] = sprintf(
				/* translators: %d: number of active campaigns */
				__( 'Active: %d', 'meyvora-convert' ),
				$active
			);
		}
		MEYVC_Admin_UI::render_page( array(
			'title'           => get_admin_page_title() ?: __( 'Campaigns', 'meyvora-convert' ),
			'subtitle'        => '',
			'active_tab'      => 'meyvc-campaigns',
			'primary_action'  => array(
				'label' => __( 'Add New Campaign', 'meyvora-convert' ),
				'href'  => admin_url( 'admin.php?page=meyvc-campaign-edit' ),
			),
			'content_partial' => MEYVC_PLUGIN_DIR . 'admin/partials/meyvc-admin-campaigns.php',
			'wrap_class'      => 'meyvc-campaigns-page',
			'header_pills'    => $pills,
		) );
	}

	/**
	 * Display campaign editor (hidden menu, linked from campaigns list).
	 */
	public function display_campaign_editor() {
		MEYVC_Admin_UI::render_page( array(
			'title'           => __( 'Edit Campaign', 'meyvora-convert' ),
			'subtitle'        => '',
			'active_tab'      => 'meyvc-campaigns',
			'content_partial' => MEYVC_PLUGIN_DIR . 'admin/partials/meyvc-admin-campaign-builder.php',
			'wrap_class'      => 'meyvc-campaign-builder',
		) );
	}

	/**
	 * Display on-page boosters page.
	 */
	public function display_boosters() {
		MEYVC_Admin_UI::render_page( array(
			'title'           => __( 'On-Page Conversion Boosters', 'meyvora-convert' ),
			'subtitle'        => __( 'These elements appear on your product and cart pages to encourage conversions.', 'meyvora-convert' ),
			'active_tab'      => 'meyvc-boosters',
			'primary_action'  => array( 'label' => __( 'Save changes', 'meyvora-convert' ), 'form_id' => 'meyvc-boosters-form' ),
			'content_partial' => MEYVC_PLUGIN_DIR . 'admin/partials/meyvc-admin-boosters.php',
			'wrap_class'      => 'meyvc-boosters-page',
		) );
	}

	/**
	 * Display cart optimizer page.
	 */
	public function display_cart_optimizer() {
		MEYVC_Admin_UI::render_page( array(
			'title'           => __( 'Cart Page Optimizer', 'meyvora-convert' ),
			'subtitle'        => __( 'The cart page is high-intent real estate. Use it to build confidence and reduce hesitation.', 'meyvora-convert' ),
			'active_tab'      => 'meyvc-cart',
			'primary_action'  => array( 'label' => __( 'Save settings', 'meyvora-convert' ), 'form_id' => 'meyvc-cart-form' ),
			'content_partial' => MEYVC_PLUGIN_DIR . 'admin/partials/meyvc-admin-cart.php',
			'wrap_class'      => 'meyvc-cart-page',
		) );
	}

	/**
	 * Display offers page (dynamic offers config + test panel).
	 */
	public function display_offers() {
		$offers = get_option( 'meyvc_dynamic_offers', array() );
		$offers = is_array( $offers ) ? $offers : array();
		$max_offers = 5;
		$used = count( $offers );
		$pills = array();
		$pills[] = sprintf(
			/* translators: 1: number of offers used, 2: max offers */
			__( '%1$d/%2$d offers used', 'meyvora-convert' ),
			$used,
			$max_offers
		);
		MEYVC_Admin_UI::render_page( array(
			'title'           => __( 'Offers', 'meyvora-convert' ),
			'subtitle'        => __( 'Show a single dynamic offer on cart and checkout based on rules and priority.', 'meyvora-convert' ),
			'active_tab'      => 'meyvc-offers',
			'primary_action'  => array(
				'label'      => __( '+ Add Offer', 'meyvora-convert' ),
				'button_id'  => 'meyvc-offers-add-btn',
				'attributes' => array( 'data-meyvc-drawer' => 'add' ),
			),
			'content_partial' => MEYVC_PLUGIN_DIR . 'admin/partials/meyvc-admin-offers.php',
			'wrap_class'      => 'meyvc-offers-page',
			'header_pills'    => $pills,
		) );
	}

	/**
	 * Display checkout optimizer page.
	 */
	public function display_checkout_optimizer() {
		MEYVC_Admin_UI::render_page( array(
			'title'           => __( 'Checkout Page Optimizer', 'meyvora-convert' ),
			'subtitle'        => __( 'Reduce friction and build trust on the checkout page.', 'meyvora-convert' ),
			'active_tab'      => 'meyvc-checkout',
			'primary_action'  => array( 'label' => __( 'Save settings', 'meyvora-convert' ), 'form_id' => 'meyvc-checkout-form' ),
			'content_partial' => MEYVC_PLUGIN_DIR . 'admin/partials/meyvc-admin-checkout.php',
			'wrap_class'      => 'meyvc-checkout-page',
		) );
	}

	/**
	 * Display A/B tests page.
	 */
	public function display_ab_tests() {
		MEYVC_Admin_UI::render_page( array(
			'title'           => get_admin_page_title() ?: __( 'A/B Tests', 'meyvora-convert' ),
			'subtitle'        => '',
			'active_tab'      => 'meyvc-ab-tests',
			'primary_action'  => array( 'label' => __( 'New A/B Test', 'meyvora-convert' ), 'href' => admin_url( 'admin.php?page=meyvc-ab-test-new' ) ),
			'content_partial' => MEYVC_PLUGIN_DIR . 'admin/partials/meyvc-admin-ab-tests.php',
			'wrap_class'      => 'meyvc-ab-tests-page',
		) );
	}

	/**
	 * Display settings page.
	 */
	public function display_settings() {
		MEYVC_Admin_UI::render_page( array(
			'title'           => get_admin_page_title() ?: __( 'Settings', 'meyvora-convert' ),
			'subtitle'        => __( 'Configure analytics, debug, and data. Run a self-test from System Status for support.', 'meyvora-convert' ),
			'active_tab'      => 'meyvc-settings',
			'primary_action'  => null,
			'content_partial' => MEYVC_PLUGIN_DIR . 'admin/partials/meyvc-admin-settings.php',
			'wrap_class'      => 'meyvc-admin-settings',
		) );
	}

	/**
	 * Render System Status page.
	 */
	public function render_system_status() {
		MEYVC_Admin_UI::render_page( array(
			'title'           => get_admin_page_title() ?: __( 'System Status', 'meyvora-convert' ),
			'subtitle'        => __( 'Environment and compatibility checks for Meyvora Convert. Use the report below when contacting support.', 'meyvora-convert' ),
			'active_tab'      => 'meyvc-system-status',
			'primary_action'  => array( 'label' => __( 'Verify Installation', 'meyvora-convert' ), 'form_id' => 'meyvc-verify-installation-form' ),
			'content_partial' => MEYVC_PLUGIN_DIR . 'admin/partials/meyvc-admin-system-status.php',
			'wrap_class'      => 'meyvc-admin-system-status',
		) );
	}

	/**
	 * Display System Status partial (checks + copyable report). Called via render_system_status.
	 */
	public function display_system_status() {
		$this->render_system_status();
	}

	/**
	 * Render Tools (Import / Export) page.
	 */
	public function render_tools() {
		MEYVC_Admin_UI::render_page( array(
			'title'           => get_admin_page_title() ?: __( 'Import / Export', 'meyvora-convert' ),
			'subtitle'        => '',
			'active_tab'      => 'meyvc-tools',
			'content_partial' => MEYVC_PLUGIN_DIR . 'admin/partials/meyvc-admin-tools.php',
			'wrap_class'      => 'meyvc-admin-tools',
		) );
	}

	/**
	 * Render Developer (hooks, templates) page.
	 */
	public function render_developer() {
		MEYVC_Admin_UI::render_page( array(
			'title'           => get_admin_page_title() ?: __( 'Developer', 'meyvora-convert' ),
			'subtitle'        => __( 'Actions, filters, and template overrides for extending Meyvora Convert.', 'meyvora-convert' ),
			'active_tab'      => 'meyvc-developer',
			'content_partial' => MEYVC_PLUGIN_DIR . 'admin/partials/meyvc-admin-developer.php',
			'wrap_class'      => 'meyvc-admin-developer',
		) );
	}

	/**
	 * Redirect to onboarding after first activation (once per install).
	 */
	public function handle_activation_redirect() {
		if ( ! get_transient( 'meyvc_activation_redirect' ) || ! current_user_can( 'manage_meyvora_convert' ) ) {
			return;
		}
		delete_transient( 'meyvc_activation_redirect' );
		if ( self::get_get_text( 'page' ) === 'meyvora-convert' && self::is_meyvc_onboarding_query() ) {
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=meyvora-convert&meyvc_onboarding=1' ) );
		exit;
	}

	/**
	 * Handle "Restart Onboarding" from Settings: clear flag and redirect to wizard.
	 */
	public function handle_onboarding_restart() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		if ( $action !== 'meyvc_restart_onboarding' ) {
			return;
		}
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			return;
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'meyvc_restart_onboarding' ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'invalid_nonce', admin_url( 'admin.php?page=meyvc-settings' ) ) );
			exit;
		}
		update_option( 'meyvc_onboarding_complete', false );
		update_option( 'meyvc_onboarding_completed', false );
		wp_safe_redirect( admin_url( 'admin.php?page=meyvora-convert&meyvc_onboarding=1' ) );
		exit;
	}

	/**
	 * Handle "Skip" onboarding: mark completed and redirect to dashboard.
	 */
	public function handle_onboarding_skip() {
		if ( ! isset( $_GET['meyvc_skip_onboarding'] ) || ! current_user_can( 'manage_meyvora_convert' ) ) {
			return;
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'meyvc_skip_onboarding' ) ) {
			return;
		}
		if ( function_exists( 'meyvc_mark_onboarding_complete' ) ) {
			meyvc_mark_onboarding_complete();
		} else {
			update_option( 'meyvc_onboarding_complete', true );
			update_option( 'meyvc_onboarding_completed', true );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=meyvora-convert' ) );
		exit;
	}

	/**
	 * Handle onboarding checklist form: save toggles and/or mark complete.
	 */
	public function handle_onboarding_save() {
		if ( ! isset( $_POST['meyvc_onboarding_checklist'] ) || ! current_user_can( 'manage_meyvora_convert' ) ) {
			return;
		}
		$nonce = isset( $_POST['meyvc_onboarding_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['meyvc_onboarding_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'meyvc_onboarding' ) ) {
			return;
		}

		$settings = function_exists( 'meyvc_settings' ) ? meyvc_settings() : null;
		if ( $settings ) {
			$settings->set( 'general', 'shipping_bar_enabled', ! empty( $_POST['feature_shipping_bar'] ) );
			$settings->set( 'general', 'sticky_cart_enabled', ! empty( $_POST['feature_sticky_cart'] ) );
		}

		if ( ! empty( $_POST['meyvc_onboarding_done'] ) ) {
			if ( function_exists( 'meyvc_mark_onboarding_complete' ) ) {
				meyvc_mark_onboarding_complete();
			} else {
				update_option( 'meyvc_onboarding_complete', true );
				update_option( 'meyvc_onboarding_completed', true );
			}
			wp_safe_redirect( admin_url( 'admin.php?page=meyvora-convert&onboarding_done=1' ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=meyvora-convert&meyvc_onboarding=1' ) );
		exit;
	}

	/**
	 * Handle Apply Preset: apply preset and redirect back to Presets page.
	 */
	public function handle_apply_preset() {
		$action = isset( $_POST['meyvc_apply_preset'] ) ? 'post' : ( isset( $_GET['action'] ) && sanitize_text_field( wp_unslash( $_GET['action'] ) ) === 'meyvc_apply_preset' ? 'get' : '' );
		if ( ! $action ) {
			return;
		}
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'unauthorized', admin_url( 'admin.php?page=meyvc-presets' ) ) );
			exit;
		}
		$nonce_key = $action === 'post' ? 'meyvc_preset_nonce' : '_wpnonce';
		$nonce_val = $action === 'post' ? ( isset( $_POST['meyvc_preset_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['meyvc_preset_nonce'] ) ) : '' ) : ( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '' );
		if ( ! wp_verify_nonce( $nonce_val, 'meyvc_apply_preset' ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'invalid_nonce', admin_url( 'admin.php?page=meyvc-presets' ) ) );
			exit;
		}
		$preset_id = $action === 'post' ? ( isset( $_POST['preset_id'] ) ? sanitize_text_field( wp_unslash( $_POST['preset_id'] ) ) : '' ) : ( isset( $_GET['preset_id'] ) ? sanitize_text_field( wp_unslash( $_GET['preset_id'] ) ) : '' );
		if ( ! $preset_id || ! class_exists( 'MEYVC_Presets' ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'invalid_preset', admin_url( 'admin.php?page=meyvc-presets' ) ) );
			exit;
		}
		$result = MEYVC_Presets::apply( $preset_id );
		if ( ! empty( $result['success'] ) ) {
			$url = admin_url( 'admin.php?page=meyvc-presets&preset_applied=1&message=' . rawurlencode( $result['message'] ) );
			if ( ! empty( $result['campaign_id'] ) ) {
				$url = add_query_arg( 'campaign_id', (int) $result['campaign_id'], $url );
			}
			wp_safe_redirect( $url );
			exit;
		}
		wp_safe_redirect( add_query_arg( 'error', 'apply_failed', admin_url( 'admin.php?page=meyvc-presets' ) ) );
		exit;
	}

	/**
	 * Save a campaign sequence (admin_post).
	 */
	public function handle_save_sequence() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_die( esc_html__( 'You do not have permission to save sequences.', 'meyvora-convert' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'meyvc_save_sequence', 'meyvc_sequence_nonce' );
		if ( ! class_exists( 'MEYVC_Sequence_Engine' ) ) {
			wp_safe_redirect( add_query_arg( 'sequence_error', 'unavailable', admin_url( 'admin.php?page=meyvc-sequences' ) ) );
			exit;
		}
		$post_in = filter_input_array( INPUT_POST );
		if ( ! is_array( $post_in ) ) {
			$post_in = array();
		}
		$name = isset( $post_in['sequence_name'] ) ? sanitize_text_field( wp_unslash( (string) $post_in['sequence_name'] ) ) : '';
		$trigger = isset( $post_in['trigger_campaign_id'] ) ? absint( $post_in['trigger_campaign_id'] ) : 0;
		$status  = isset( $post_in['sequence_status'] ) ? sanitize_key( wp_unslash( (string) $post_in['sequence_status'] ) ) : 'draft';
		$id      = isset( $post_in['sequence_id'] ) ? absint( $post_in['sequence_id'] ) : 0;

		$step_campaigns = isset( $post_in['step_campaign_id'] ) && is_array( $post_in['step_campaign_id'] ) ? array_map( 'absint', wp_unslash( $post_in['step_campaign_id'] ) ) : array();
		$step_delays_raw = isset( $post_in['step_delay_hours'] ) && is_array( $post_in['step_delay_hours'] ) ? wp_unslash( $post_in['step_delay_hours'] ) : array();
		$step_delays     = map_deep( $step_delays_raw, 'sanitize_text_field' );
		$steps          = array();
		$max            = max( count( $step_campaigns ), count( $step_delays ) );
		for ( $i = 0; $i < $max; $i++ ) {
			$cid = isset( $step_campaigns[ $i ] ) ? (int) $step_campaigns[ $i ] : 0;
			if ( $cid <= 0 ) {
				continue;
			}
			$h_raw = isset( $step_delays[ $i ] ) ? (string) $step_delays[ $i ] : '0';
			$h     = is_numeric( $h_raw ) ? (float) $h_raw : 0.0;
			$steps[] = array(
				'campaign_id'    => $cid,
				'delay_seconds'  => (int) round( max( 0, $h ) * 3600 ),
			);
		}

		if ( $name === '' || $trigger <= 0 || empty( $steps ) ) {
			wp_safe_redirect( add_query_arg( 'sequence_error', 'invalid', admin_url( 'admin.php?page=meyvc-sequences' ) ) );
			exit;
		}

		$result = MEYVC_Sequence_Engine::save(
			array(
				'id'                  => $id,
				'name'                => $name,
				'trigger_campaign_id' => $trigger,
				'status'              => $status,
				'steps_json'          => wp_json_encode( $steps ),
			)
		);

		if ( false === $result || $result === 0 ) {
			wp_safe_redirect( add_query_arg( 'sequence_error', 'save_failed', admin_url( 'admin.php?page=meyvc-sequences' ) ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=meyvc-sequences&sequence_saved=1' ) );
		exit;
	}

	/**
	 * Delete a campaign sequence (admin_post).
	 */
	public function handle_delete_sequence() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete sequences.', 'meyvora-convert' ), '', array( 'response' => 403 ) );
		}
		$id = isset( $_GET['sequence_id'] ) ? absint( $_GET['sequence_id'] ) : 0;
		if ( ! $id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'meyvc_delete_sequence_' . $id ) ) {
			wp_safe_redirect( add_query_arg( 'sequence_error', 'invalid_nonce', admin_url( 'admin.php?page=meyvc-sequences' ) ) );
			exit;
		}
		if ( class_exists( 'MEYVC_Sequence_Engine' ) ) {
			MEYVC_Sequence_Engine::delete( $id );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=meyvc-sequences&sequence_deleted=1' ) );
		exit;
	}

	/**
	 * Handle Quick Launch (recommended CRO setup in one click).
	 */
	public function handle_quick_launch() {
		if ( ! isset( $_POST['meyvc_quick_launch'] ) || sanitize_text_field( wp_unslash( $_POST['meyvc_quick_launch'] ) ) !== 'recommended' ) {
			return;
		}
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			return;
		}
		if ( ! isset( $_POST['meyvc_quick_launch_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['meyvc_quick_launch_nonce'] ) ), 'meyvc_quick_launch' ) ) {
			return;
		}
		if ( ! function_exists( 'meyvc_quick_launch_apply' ) ) {
			return;
		}
		$applied = meyvc_quick_launch_apply( 'recommended' );
		$url = admin_url( 'admin.php?page=meyvora-convert' );
		if ( $applied ) {
			$url = add_query_arg( 'meyvc_quick_launch_done', '1', $url );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Handle export request (Tools → Export: selected campaign as JSON, no analytics).
	 */
	public function handle_export() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		if ( $action !== 'meyvc_export' ) {
			return;
		}

		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_die( esc_html__( 'You do not have permission to export.', 'meyvora-convert' ), 403 );
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'meyvc_export' ) ) {
			wp_die( esc_html__( 'Invalid security check. Please try again.', 'meyvora-convert' ), 403 );
		}

		$campaign_id = isset( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : 0;
		if ( $campaign_id < 1 || ! class_exists( 'MEYVC_Campaign' ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'no_campaign', admin_url( 'admin.php?page=meyvc-tools' ) ) );
			exit;
		}

		$raw = MEYVC_Campaign::get( $campaign_id );
		if ( ! $raw || empty( $raw['name'] ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'not_found', admin_url( 'admin.php?page=meyvc-tools' ) ) );
			exit;
		}

		// Strip analytics and identifiers for export.
		$export_campaign = $raw;
		unset( $export_campaign['id'], $export_campaign['impressions'], $export_campaign['conversions'], $export_campaign['revenue_attributed'], $export_campaign['created_at'], $export_campaign['updated_at'], $export_campaign['settings'], $export_campaign['targeting'] );

		$export_data = array(
			'version'     => defined( 'MEYVC_VERSION' ) ? MEYVC_VERSION : '1.0.0',
			'exported_at' => current_time( 'mysql' ),
			'campaigns'   => array( $export_campaign ),
		);

		$filename = 'meyvc-campaign-' . sanitize_file_name( $raw['name'] ) . '-' . gmdate( 'Y-m-d' ) . '.json';

		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );

		echo wp_json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/**
	 * Build ZIP with multiple analytics CSV files (Full Report).
	 *
	 * @param MEYVC_Analytics $analytics    Analytics instance.
	 * @param string        $date_from   Y-m-d.
	 * @param string        $date_to     Y-m-d.
	 * @param int|null      $campaign_id Optional campaign filter for campaign/top_pages exports.
	 */
	private function export_analytics_zip_report( MEYVC_Analytics $analytics, $date_from, $date_to, $campaign_id ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'zip_unavailable', admin_url( 'admin.php?page=meyvc-analytics' ) ) );
			exit;
		}

		$tmp = wp_tempnam( 'meyvc-analytics-report-' );
		if ( ! $tmp ) {
			wp_safe_redirect( add_query_arg( 'error', 'zip_failed', admin_url( 'admin.php?page=meyvc-analytics' ) ) );
			exit;
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			if ( is_string( $tmp ) && file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			wp_safe_redirect( add_query_arg( 'error', 'zip_failed', admin_url( 'admin.php?page=meyvc-analytics' ) ) );
			exit;
		}

		$meyvc_put_csv = static function ( $headers, $rows ) {
			return class_exists( 'MEYVC_Security' ) ? MEYVC_Security::build_csv_document( $headers, $rows ) : '';
		};

		$campaign_rows = array();
		$campaigns     = $analytics->get_campaign_performance( $date_from, $date_to, 500 );
		foreach ( is_array( $campaigns ) ? $campaigns : array() as $c ) {
			$imp = (int) ( $c['impressions'] ?? 0 );
			$cnv = (int) ( $c['conversions'] ?? 0 );
			$rate = $imp > 0 ? round( ( $cnv / $imp ) * 100, 4 ) : 0;
			$campaign_rows[] = array(
				(int) ( $c['id'] ?? 0 ),
				(string) ( $c['name'] ?? '' ),
				(string) ( $c['status'] ?? '' ),
				$imp,
				$cnv,
				$rate,
				(float) ( $c['revenue'] ?? 0 ),
				(int) ( $c['emails'] ?? 0 ),
			);
		}
		$zip->addFromString(
			'campaigns.csv',
			$meyvc_put_csv(
				array( 'campaign_id', 'name', 'status', 'impressions', 'conversions', 'conversion_rate_pct', 'revenue', 'emails_captured' ),
				$campaign_rows
			)
		);

		$offer_rows = array();
		$offers     = $analytics->get_offer_revenue_attribution( $date_from, $date_to );
		foreach ( is_array( $offers ) ? $offers : array() as $o ) {
			$offer_rows[] = array(
				(int) ( $o['offer_id'] ?? 0 ),
				(string) ( $o['offer_name'] ?? '' ),
				(int) ( $o['total_orders'] ?? 0 ),
				(float) ( $o['total_revenue'] ?? 0 ),
			);
		}
		$zip->addFromString(
			'offers.csv',
			$meyvc_put_csv(
				array( 'offer_id', 'offer_name', 'total_orders', 'total_revenue' ),
				$offer_rows
			)
		);

		$cohort_rows = array();
		$cohorts     = $analytics->get_cohort_recovery( $date_from, $date_to );
		foreach ( is_array( $cohorts ) ? $cohorts : array() as $w ) {
			$cohort_rows[] = array(
				(string) ( $w['week_label'] ?? '' ),
				(int) ( $w['total_abandoned'] ?? 0 ),
				(int) ( $w['recovered'] ?? 0 ),
				(float) ( $w['recovery_rate'] ?? 0 ),
			);
		}
		$zip->addFromString(
			'cohort_recovery.csv',
			$meyvc_put_csv(
				array( 'week', 'abandoned', 'recovered', 'recovery_rate_pct' ),
				$cohort_rows
			)
		);

		$cart_rows = array();
		$carts     = $analytics->get_abandoned_carts_export_rows( $date_from, $date_to, 2000 );
		foreach ( is_array( $carts ) ? $carts : array() as $ac ) {
			$cart_rows[] = array(
				(int) ( $ac['id'] ?? 0 ),
				(string) ( $ac['created_at'] ?? '' ),
				(string) ( $ac['status'] ?? '' ),
				(string) ( $ac['recovered_at'] ?? '' ),
				(int) ( $ac['has_email'] ?? 0 ),
			);
		}
		$zip->addFromString(
			'abandoned_carts.csv',
			$meyvc_put_csv(
				array( 'id', 'created_at', 'status', 'recovered_at', 'has_email' ),
				$cart_rows
			)
		);

		$page_rows = array();
		$pages     = $analytics->get_top_pages( $date_from, $date_to, 500, $campaign_id );
		foreach ( is_array( $pages ) ? $pages : array() as $p ) {
			$imp = (int) ( $p['impressions'] ?? 0 );
			$cnv = (int) ( $p['conversions'] ?? 0 );
			$rate = $imp > 0 ? round( ( $cnv / $imp ) * 100, 4 ) : 0;
			$page_rows[] = array(
				(string) ( $p['page_url'] ?? '' ),
				$imp,
				$cnv,
				$rate,
				(int) ( $p['emails_captured'] ?? 0 ),
			);
		}
		$zip->addFromString(
			'top_pages.csv',
			$meyvc_put_csv(
				array( 'page_url', 'impressions', 'conversions', 'conversion_rate_pct', 'emails_captured' ),
				$page_rows
			)
		);

		$zip->close();

		$filename = 'meyvc-full-report-' . sanitize_file_name( $date_from ) . '-to-' . sanitize_file_name( $date_to ) . '.zip';
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;
		if ( $wp_filesystem && is_string( $tmp ) && $tmp !== '' && $wp_filesystem->exists( $tmp ) ) {
			$zip_body = $wp_filesystem->get_contents( $tmp );
			if ( is_string( $zip_body ) && $zip_body !== '' ) {
				$wp_filesystem->put_contents( 'php://output', $zip_body );
			}
		}
		wp_delete_file( $tmp );
		exit;
	}

	/**
	 * Handle CSV export
	 */
	public function handle_csv_export() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		$page   = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( $action !== 'export' || $page !== 'meyvc-analytics' ) {
			return;
		}

		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'unauthorized', admin_url( 'admin.php?page=meyvc-analytics' ) ) );
			exit;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'meyvc_export' ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'invalid_nonce', admin_url( 'admin.php?page=meyvc-analytics' ) ) );
			exit;
		}

		$default_days = 30;
		$date_from   = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : gmdate( 'Y-m-d', strtotime( "-{$default_days} days" ) );
		$date_to     = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : gmdate( 'Y-m-d' );
		$max_days    = (int) apply_filters( 'meyvc_export_max_days', 90 );
		$max_days    = $max_days >= 1 && $max_days <= 365 ? $max_days : 90;
		$ts_from     = strtotime( $date_from );
		$ts_to       = strtotime( $date_to );
		if ( $ts_from === false || $ts_to === false || $ts_to < $ts_from ) {
			$date_from = gmdate( 'Y-m-d', strtotime( "-{$default_days} days" ) );
			$date_to   = gmdate( 'Y-m-d' );
		} else {
			$range_days = (int) ( ( $ts_to - $ts_from ) / 86400 ) + 1;
			if ( $range_days > $max_days ) {
				$date_from = gmdate( 'Y-m-d', strtotime( $date_to . " -{$max_days} days" ) );
			}
		}
		$campaign_id = isset( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : null;
		if ( $campaign_id === 0 ) {
			$campaign_id = null;
		}

		$export_format = isset( $_GET['format'] ) ? sanitize_text_field( wp_unslash( $_GET['format'] ) ) : 'events';
		if ( ! in_array( $export_format, array( 'events', 'daily', 'zip_report' ), true ) ) {
			$export_format = 'events';
		}

		$analytics = new MEYVC_Analytics();

		if ( $export_format === 'zip_report' ) {
			$this->export_analytics_zip_report( $analytics, $date_from, $date_to, $campaign_id );
		}

		if ( $export_format === 'daily' ) {
			$rows    = $analytics->get_daily_summary_for_export( $date_from, $date_to );
			$csv_doc = '';
			if ( class_exists( 'MEYVC_Security' ) ) {
				$data_rows = array();
				foreach ( $rows as $row ) {
					$data_rows[] = array(
						$row['day'],
						(int) $row['impressions'],
						(int) $row['conversions'],
						(int) $row['offer_applies'],
						(int) $row['campaign_clicks'],
						(int) $row['ab_exposures'],
					);
				}
				$csv_doc = MEYVC_Security::build_csv_document(
					array( 'day', 'impressions', 'conversions', 'offer_applies', 'campaign_clicks', 'ab_exposures' ),
					$data_rows
				);
			}
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="meyvc-daily-summary-' . sanitize_file_name( $date_from ) . '-to-' . sanitize_file_name( $date_to ) . '.csv"' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();
			global $wp_filesystem;
			if ( $wp_filesystem && $csv_doc !== '' ) {
				$wp_filesystem->put_contents( 'php://output', $csv_doc );
			}
			exit;
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="meyvc-events-' . sanitize_file_name( $date_from ) . '-to-' . sanitize_file_name( $date_to ) . '.csv"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$event_rows = array();
		$analytics->walk_export_events_rows(
			$date_from,
			$date_to,
			$campaign_id,
			function ( $row ) use ( &$event_rows ) {
				$created_at  = isset( $row['created_at'] ) ? $row['created_at'] : '';
				$date_utc    = $created_at && function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i:s', strtotime( $created_at ), new \DateTimeZone( 'UTC' ) ) : $created_at;
				$source_type = isset( $row['source_type'] ) ? $row['source_type'] : '';
				$metadata    = isset( $row['metadata'] ) ? $row['metadata'] : '';
				$meta        = is_string( $metadata ) ? ( maybe_unserialize( $metadata ) ?: array() ) : ( is_array( $metadata ) ? $metadata : array() );
				$ab_test_id  = isset( $meta['ab_test_id'] ) ? (int) $meta['ab_test_id'] : 0;
				$variation   = isset( $meta['variation_id'] ) ? (int) $meta['variation_id'] : '';
				$object_type = $ab_test_id > 0 ? 'ab_test' : $source_type;
				$object_id   = $ab_test_id > 0 ? $ab_test_id : ( isset( $row['source_id'] ) ? (int) $row['source_id'] : '' );
				$user_id     = isset( $row['user_id'] ) && (int) $row['user_id'] > 0 ? (int) $row['user_id'] : '';
				$session_id  = isset( $row['session_id'] ) ? $row['session_id'] : '';
				$session_key = $session_id !== '' ? substr( hash( 'sha256', $session_id ), 0, 16 ) : '';
				$meta_json   = '';
				if ( $metadata !== '' && $metadata !== null ) {
					$meta_json = is_array( $meta ) ? wp_json_encode( $meta ) : ( is_string( $metadata ) && ( $metadata[0] === '{' || $metadata[0] === '[' ) ? $metadata : wp_json_encode( array( 'raw' => $metadata ) ) );
				}

				$event_rows[] = array(
					$date_utc,
					isset( $row['event_type'] ) ? $row['event_type'] : '',
					$object_type,
					$object_id,
					$variation,
					isset( $row['page_type'] ) ? $row['page_type'] : '',
					isset( $row['page_url'] ) ? $row['page_url'] : '',
					$user_id,
					$session_key,
					$meta_json,
				);
			}
		);

		$csv_doc = class_exists( 'MEYVC_Security' )
			? MEYVC_Security::build_csv_document(
				array( 'date_time', 'event_type', 'object_type', 'object_id', 'variant', 'page_type', 'page_url', 'user_id', 'session_key', 'meta_json' ),
				$event_rows
			)
			: '';
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;
		if ( $wp_filesystem && $csv_doc !== '' ) {
			$wp_filesystem->put_contents( 'php://output', $csv_doc );
		}
		exit;
	}

	/**
	 * Handle import request (Tools → Import: validate JSON, insert as new campaigns via MEYVC_Campaign::create).
	 */
	/**
	 * Handle "Verify Install Package" from Tools page. Runs checks and redirects back with results in transient.
	 */
	public function handle_verify_package() {
		if ( ! isset( $_POST['meyvc_verify_package'] ) || (int) $_POST['meyvc_verify_package'] !== 1 ) {
			return;
		}
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_safe_redirect( add_query_arg( 'meyvc_verify', '0', admin_url( 'admin.php?page=meyvc-tools' ) ) );
			exit;
		}
		$nonce = isset( $_POST['meyvc_verify_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['meyvc_verify_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'meyvc_verify_package' ) ) {
			wp_safe_redirect( add_query_arg( 'meyvc_verify', '0', admin_url( 'admin.php?page=meyvc-tools' ) ) );
			exit;
		}
		$results = class_exists( 'MEYVC_System_Status' ) ? MEYVC_System_Status::run_verify_package() : array();
		set_transient( 'meyvc_verify_results', $results, 60 );
		wp_safe_redirect( add_query_arg( 'meyvc_verify', '1', admin_url( 'admin.php?page=meyvc-tools' ) ) );
		exit;
	}

	/**
	 * Handle "Verify Installation" from System Status page. Runs checks (tables, blocks build, Woo, blocks) and redirects back with results.
	 */
	public function handle_verify_installation() {
		if ( ! isset( $_POST['meyvc_verify_installation'] ) || (int) $_POST['meyvc_verify_installation'] !== 1 ) {
			return;
		}
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_safe_redirect( add_query_arg( 'meyvc_verify_installation', '0', admin_url( 'admin.php?page=meyvc-system-status' ) ) );
			exit;
		}
		$nonce = isset( $_POST['meyvc_verify_installation_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['meyvc_verify_installation_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'meyvc_verify_installation' ) ) {
			wp_safe_redirect( add_query_arg( 'meyvc_verify_installation', '0', admin_url( 'admin.php?page=meyvc-system-status' ) ) );
			exit;
		}
		$results = class_exists( 'MEYVC_System_Status' ) ? MEYVC_System_Status::run_verify_installation() : array();
		set_transient( 'meyvc_verify_installation_results', $results, 60 );
		wp_safe_redirect( add_query_arg( 'meyvc_verify_installation', '1', admin_url( 'admin.php?page=meyvc-system-status' ) ) );
		exit;
	}

	/**
	 * Save CRO Admin Debug toggle (Tools page). Only for users with manage_meyvora_convert.
	 */
	public function handle_save_admin_debug() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			return;
		}
		if ( ! isset( $_POST['meyvc_admin_debug_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['meyvc_admin_debug_nonce'] ) ), 'meyvc_admin_debug' ) ) {
			return;
		}
		$page = isset( $_POST['page'] ) ? sanitize_text_field( wp_unslash( $_POST['page'] ) ) : '';
		if ( $page !== 'meyvc-tools' ) {
			return;
		}
		$enabled = isset( $_POST['meyvc_admin_debug'] ) && (int) $_POST['meyvc_admin_debug'] === 1;
		update_option( 'meyvc_admin_debug', $enabled );
		wp_safe_redirect( add_query_arg( 'meyvc_admin_debug_saved', $enabled ? '1' : '0', admin_url( 'admin.php?page=meyvc-tools' ) ) );
		exit;
	}

	/**
	 * Render CRO Admin Debug panel in footer (only when option enabled and user can manage_meyvora_convert).
	 * Shows enqueued CSS/JS and builder init status.
	 */
	public function render_admin_debug_panel() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			return;
		}
		if ( ! get_option( 'meyvc_admin_debug', false ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$hook   = $screen ? $screen->id : '';
		$page   = self::get_get_text( 'page' );
		$is_cro = ( $page !== '' && ( strpos( $page, 'meyvc-' ) !== false || strpos( $page, 'meyvc_' ) !== false ) )
			|| ( strpos( $hook, 'meyvc-' ) !== false || strpos( $hook, 'meyvc_' ) !== false );
		if ( ! $is_cro ) {
			return;
		}

		$styles = array();
		if ( isset( $GLOBALS['wp_styles'] ) && $GLOBALS['wp_styles'] instanceof \WP_Styles ) {
			$done = array_merge( $GLOBALS['wp_styles']->done, $GLOBALS['wp_styles']->queue );
			foreach ( $done as $handle ) {
				if ( strpos( $handle, 'meyvc' ) !== false && isset( $GLOBALS['wp_styles']->registered[ $handle ] ) ) {
					$obj = $GLOBALS['wp_styles']->registered[ $handle ];
					$src = isset( $obj->src ) ? $obj->src : '';
					$styles[] = $handle . ( $src ? ' → ' . preg_replace( '#^https?://[^/]+/#', '/', $src ) : '' );
				}
			}
		}
		$scripts = array();
		if ( isset( $GLOBALS['wp_scripts'] ) && $GLOBALS['wp_scripts'] instanceof \WP_Scripts ) {
			$done = array_merge( $GLOBALS['wp_scripts']->done, $GLOBALS['wp_scripts']->queue );
			foreach ( $done as $handle ) {
				if ( strpos( $handle, 'meyvc' ) !== false && isset( $GLOBALS['wp_scripts']->registered[ $handle ] ) ) {
					$obj   = $GLOBALS['wp_scripts']->registered[ $handle ];
					$src   = isset( $obj->src ) ? $obj->src : '';
					$scripts[] = $handle . ( $src ? ' → ' . preg_replace( '#^https?://[^/]+/#', '/', $src ) : '' );
				}
			}
		}

		$is_builder = ( strpos( $page, 'meyvc-campaign-edit' ) !== false || strpos( $page, 'meyvc-campaign-builder' ) !== false );
		?>
		<div id="meyvc-admin-debug-panel" class="meyvc-admin-debug-panel" style="position:fixed;bottom:0;right:0;max-width:360px;max-height:50vh;overflow:auto;background:#1d1d1d;color:#e0e0e0;font-size:11px;padding:10px;z-index:999999;border-radius:8px 0 0 0;box-shadow:0 -2px 10px rgba(0,0,0,.3);">
			<details open>
				<summary style="cursor:pointer;font-weight:600;margin-bottom:6px;">Meyvora Convert Admin Debug</summary>
				<p style="margin:4px 0;"><strong>CSS (Meyvora Convert):</strong></p>
				<ul style="margin:0 0 8px 0;padding-left:16px;list-style:disc;">
					<?php foreach ( $styles as $s ) : ?>
						<li><?php echo esc_html( $s ); ?></li>
					<?php endforeach; ?>
					<?php if ( empty( $styles ) ) : ?>
						<li><?php esc_html_e( 'None', 'meyvora-convert' ); ?></li>
					<?php endif; ?>
				</ul>
				<p style="margin:4px 0;"><strong>JS (Meyvora Convert):</strong></p>
				<ul style="margin:0 0 8px 0;padding-left:16px;list-style:disc;">
					<?php foreach ( $scripts as $s ) : ?>
						<li><?php echo esc_html( $s ); ?></li>
					<?php endforeach; ?>
					<?php if ( empty( $scripts ) ) : ?>
						<li><?php esc_html_e( 'None', 'meyvora-convert' ); ?></li>
					<?php endif; ?>
				</ul>
				<?php if ( $is_builder ) : ?>
					<p style="margin:4px 0;"><strong>Builder init:</strong> <span id="meyvc-admin-debug-builder-status">…</span></p>
				<?php endif; ?>
			</details>
		</div>
		<?php if ( $is_builder ) : ?>
			<?php
			wp_add_inline_script(
				'meyvc-admin',
				'(function(){
					function meyvcDebugSetBuilderStatus(){
						var el = document.getElementById("meyvc-admin-debug-builder-status");
						if (!el) return;
						if (typeof window.meyvcBuilderInitStatus !== "undefined") {
							var s = window.meyvcBuilderInitStatus;
							el.textContent = (s.status === "OK") ? "OK" : ("FAIL: " + (s.reason || "unknown"));
						} else {
							el.textContent = "FAIL: not set (script error or not run)";
						}
					}
					if (document.readyState === "complete") {
						meyvcDebugSetBuilderStatus();
					} else {
						window.addEventListener("load", meyvcDebugSetBuilderStatus);
					}
					setTimeout(meyvcDebugSetBuilderStatus, 1500);
				})();'
			);
			?>
		<?php endif; ?>
		<?php
	}

	public function handle_import() {
		if ( ! isset( $_POST['meyvc_import'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['meyvc_import_nonce'] ?? '' ) ), 'meyvc_import' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'meyvora-convert' ) );
		}

		$max_paste_bytes = 500000;
		$max_file_bytes  = 1048576;
		$max_campaigns   = 50;

		$json_string = '';
		$import_json_raw = filter_input( INPUT_POST, 'import_json', FILTER_UNSAFE_RAW );
		if ( is_string( $import_json_raw ) && $import_json_raw !== '' ) {
			$pasted = wp_unslash( $import_json_raw );
			if ( strlen( $pasted ) > $max_paste_bytes ) {
				add_action(
					'admin_notices',
					function() {
						echo '<div class="notice notice-error"><p>' . esc_html__( 'Pasted JSON is too large. Maximum size is 500 KB.', 'meyvora-convert' ) . '</p></div>';
					}
				);
				return;
			}
			$json_string = $pasted;
		} else {
			$files = filter_input_array( INPUT_FILES );
			if ( is_array( $files ) && isset( $files['import_file'] ) && is_array( $files['import_file'] ) ) {
				$import_file = $files['import_file'];
				$file_error  = isset( $import_file['error'] ) ? (int) $import_file['error'] : UPLOAD_ERR_NO_FILE;
				if ( $file_error === UPLOAD_ERR_OK && isset( $import_file['tmp_name'] ) && is_string( $import_file['tmp_name'] ) && $import_file['tmp_name'] !== '' ) {
					if ( isset( $import_file['size'] ) && (int) $import_file['size'] > $max_file_bytes ) {
						add_action(
							'admin_notices',
							function() {
								echo '<div class="notice notice-error"><p>' . esc_html__( 'Import file is too large. Maximum size is 1 MB.', 'meyvora-convert' ) . '</p></div>';
							}
						);
						return;
					}
					$tmp_path = $import_file['tmp_name'];
					if ( is_readable( $tmp_path ) ) {
						$json_string = file_get_contents( $tmp_path );
					}
				}
			}
		}

		if ( $json_string === '' ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Please upload a file or paste JSON.', 'meyvora-convert' ) . '</p></div>';
			} );
			return;
		}

		if ( strlen( $json_string ) > $max_file_bytes ) {
			add_action(
				'admin_notices',
				function() {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Import data is too large. Maximum size is 1 MB.', 'meyvora-convert' ) . '</p></div>';
				}
			);
			return;
		}

		$import_data = json_decode( $json_string, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid JSON.', 'meyvora-convert' ) . '</p></div>';
			} );
			return;
		}

		if ( ! isset( $import_data['campaigns'] ) || ! is_array( $import_data['campaigns'] ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid format: expected a "campaigns" array.', 'meyvora-convert' ) . '</p></div>';
			} );
			return;
		}

		if ( ! class_exists( 'MEYVC_Campaign' ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Campaign module unavailable.', 'meyvora-convert' ) . '</p></div>';
			} );
			return;
		}

		$total_in_file = count( $import_data['campaigns'] );
		$campaigns_to_import = array_slice( $import_data['campaigns'], 0, $max_campaigns );
		$truncated           = $total_in_file > $max_campaigns;

		$imported = 0;
		foreach ( $campaigns_to_import as $campaign ) {
			if ( ! is_array( $campaign ) ) {
				continue;
			}
			$name = isset( $campaign['name'] ) && is_string( $campaign['name'] ) && trim( $campaign['name'] ) !== ''
				? trim( $campaign['name'] ) . ' (Imported)'
				: __( 'Unnamed Campaign (Imported)', 'meyvora-convert' );
			$data = array(
				'name'             => $name,
				'status'           => 'draft',
				'campaign_type'    => isset( $campaign['campaign_type'] ) ? $campaign['campaign_type'] : ( isset( $campaign['type'] ) ? $campaign['type'] : 'exit_intent' ),
				'template_type'    => isset( $campaign['template_type'] ) ? $campaign['template_type'] : ( isset( $campaign['template'] ) ? $campaign['template'] : 'centered' ),
				'trigger_settings' => isset( $campaign['trigger_settings'] ) && is_array( $campaign['trigger_settings'] ) ? $campaign['trigger_settings'] : array(),
				'content'          => isset( $campaign['content'] ) && is_array( $campaign['content'] ) ? $campaign['content'] : array(),
				'styling'          => isset( $campaign['styling'] ) && is_array( $campaign['styling'] ) ? $campaign['styling'] : array(),
				'targeting_rules'  => isset( $campaign['targeting_rules'] ) && is_array( $campaign['targeting_rules'] ) ? $campaign['targeting_rules'] : array(),
				'display_rules'    => isset( $campaign['display_rules'] ) && is_array( $campaign['display_rules'] ) ? $campaign['display_rules'] : array(),
			);
			$data = $this->sanitize_imported_campaign_payload( $data );
			$id   = MEYVC_Campaign::create( $data );
			if ( $id ) {
				++$imported;
			}
		}

		$imported_count = $imported;
		add_action(
			'admin_notices',
			function() use ( $imported_count, $truncated, $max_campaigns ) {
				echo '<div class="notice notice-success"><p>' . esc_html(
					sprintf(
						/* translators: %d: number of campaigns imported */
						__( 'Successfully imported %d campaign(s).', 'meyvora-convert' ),
						$imported_count
					)
				) . '</p>';
				if ( $truncated ) {
					echo '<p>' . esc_html(
						sprintf(
							/* translators: %d: max campaigns per import */
							__( 'Only the first %d campaigns were imported. Split larger exports into multiple files.', 'meyvora-convert' ),
							$max_campaigns
						)
					) . '</p>';
				}
				echo '</div>';
			}
		);
	}

	/**
	 * Recursively sanitize array values for import (matches MEYVC_Ajax::sanitize_array_recursive).
	 *
	 * @param mixed $data Data to sanitize.
	 * @return mixed
	 */
	private function sanitize_array_recursive_import( $data ) {
		if ( ! is_array( $data ) ) {
			if ( is_int( $data ) || is_float( $data ) ) {
				return $data;
			}
			return sanitize_text_field( (string) $data );
		}
		$out = array();
		foreach ( $data as $k => $v ) {
			$key           = is_string( $k ) ? sanitize_key( $k ) : $k;
			$out[ $key ] = $this->sanitize_array_recursive_import( $v );
		}
		return $out;
	}

	/**
	 * Sanitize imported campaign payload: recursive text stripping plus safe HTML in content.body (wp_kses_post).
	 *
	 * @param array $data Campaign data for MEYVC_Campaign::create.
	 * @return array
	 */
	private function sanitize_imported_campaign_payload( array $data ) {
		$data['name'] = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';
		$data['campaign_type'] = isset( $data['campaign_type'] ) ? sanitize_key( (string) $data['campaign_type'] ) : 'exit_intent';
		$data['template_type'] = isset( $data['template_type'] ) ? sanitize_key( (string) $data['template_type'] ) : 'centered';

		$body_raw = '';
		if ( isset( $data['content'] ) && is_array( $data['content'] ) && isset( $data['content']['body'] ) ) {
			$body_raw = is_string( $data['content']['body'] ) ? $data['content']['body'] : '';
		}

		if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
			$data['content'] = $this->sanitize_array_recursive_import( $data['content'] );
			$data['content']['body'] = wp_kses_post( wp_unslash( $body_raw ) );
		}

		if ( isset( $data['trigger_settings'] ) && is_array( $data['trigger_settings'] ) ) {
			$data['trigger_settings'] = $this->sanitize_array_recursive_import( $data['trigger_settings'] );
		}
		if ( isset( $data['styling'] ) && is_array( $data['styling'] ) ) {
			$data['styling'] = $this->sanitize_array_recursive_import( $data['styling'] );
		}
		if ( isset( $data['targeting_rules'] ) && is_array( $data['targeting_rules'] ) ) {
			$data['targeting_rules'] = $this->sanitize_array_recursive_import( $data['targeting_rules'] );
		}
		if ( isset( $data['display_rules'] ) && is_array( $data['display_rules'] ) ) {
			$data['display_rules'] = $this->sanitize_array_recursive_import( $data['display_rules'] );
		}

		return $data;
	}

	/**
	 * Self-heal missing DB tables on admin load (at most once per 12 hours). Admins only.
	 */
	public function run_selfheal_tables() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			return;
		}
		if ( class_exists( 'MEYVC_Database' ) ) {
			MEYVC_Database::maybe_selfheal_tables();
		}
	}

	/**
	 * Handle repair database tables (System Status → Repair Database Tables).
	 */
	public function handle_repair_tables() {
		if ( ! isset( $_POST['meyvc_repair_tables'] ) || (int) $_POST['meyvc_repair_tables'] !== 1 ) {
			return;
		}

		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_safe_redirect( add_query_arg( 'meyvc_repair', '0', admin_url( 'admin.php?page=meyvc-system-status' ) ) );
			exit;
		}

		$nonce = isset( $_POST['meyvc_repair_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['meyvc_repair_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'meyvc_repair_tables' ) ) {
			wp_safe_redirect( add_query_arg( 'meyvc_repair', '0', admin_url( 'admin.php?page=meyvc-system-status' ) ) );
			exit;
		}

		global $wpdb;
		$ok = class_exists( 'MEYVC_Database' ) && MEYVC_Database::create_tables();
		$last_error = $wpdb->last_error;

		$url = admin_url( 'admin.php?page=meyvc-system-status' );
		if ( $ok ) {
			$url = add_query_arg( 'meyvc_repair', '1', $url );
		} else {
			$url = add_query_arg( 'meyvc_repair', '0', $url );
		}
		if ( $last_error !== '' ) {
			$url = add_query_arg( 'meyvc_repair_error', rawurlencode( $last_error ), $url );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Handle bulk activate / pause / delete for campaigns.
	 */
	public function handle_bulk_campaigns() {
		if ( empty( $_POST['meyvc_bulk_action'] ) || empty( $_POST['campaign_ids'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['meyvc_bulk_nonce'] ?? '' ) ), 'meyvc_bulk_campaigns' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			return;
		}

		$action = sanitize_key( $_POST['meyvc_bulk_action'] );
		$ids    = array_map( 'absint', (array) $_POST['campaign_ids'] );
		$ids    = array_filter( $ids );
		if ( empty( $ids ) ) {
			return;
		}

		if ( ! class_exists( 'MEYVC_Campaign' ) ) {
			return;
		}

		foreach ( $ids as $cid ) {
			if ( 'activate' === $action ) {
				MEYVC_Campaign::update( $cid, array( 'status' => 'active' ) );
			} elseif ( 'pause' === $action ) {
				MEYVC_Campaign::update( $cid, array( 'status' => 'paused' ) );
			} elseif ( 'delete' === $action ) {
				MEYVC_Campaign::delete( $cid );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=meyvc-campaigns&meyvc_bulk_done=1' ) );
		exit;
	}

	/**
	 * Handle campaign actions (duplicate, etc.).
	 */
	public function handle_campaign_actions() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! $action || ! $id ) {
			return;
		}

		if ( 'duplicate' === $action ) {
			if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
				wp_safe_redirect( add_query_arg( 'error', 'unauthorized', admin_url( 'admin.php?page=meyvc-campaigns' ) ) );
				exit;
			}

			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'meyvc_duplicate_campaign' ) ) {
				wp_safe_redirect( add_query_arg( 'error', 'invalid_nonce', admin_url( 'admin.php?page=meyvc-campaigns' ) ) );
				exit;
			}

			$new_id = MEYVC_Campaign::duplicate_campaign( $id );

			if ( is_wp_error( $new_id ) ) {
				wp_safe_redirect( add_query_arg( 'error', 'duplicate_failed', admin_url( 'admin.php?page=meyvc-campaigns' ) ) );
			} else {
				wp_safe_redirect( admin_url( 'admin.php?page=meyvc-campaign-edit&campaign_id=' . $new_id . '&duplicated=1' ) );
			}
			exit;
		}

		if ( 'delete' === $action ) {
			if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
				wp_safe_redirect( add_query_arg( 'error', 'unauthorized', admin_url( 'admin.php?page=meyvc-campaigns' ) ) );
				exit;
			}

			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'meyvc_delete_campaign_' . $id ) ) {
				wp_safe_redirect( add_query_arg( 'error', 'invalid_nonce', admin_url( 'admin.php?page=meyvc-campaigns' ) ) );
				exit;
			}

			MEYVC_Campaign::delete( $id );
			wp_safe_redirect( admin_url( 'admin.php?page=meyvc-campaigns&deleted=1' ) );
			exit;
		}
	}

	/**
	 * Handle A/B test actions (start, pause, complete, delete, apply_winner).
	 */
	public function handle_ab_test_actions() {
		$action  = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		$test_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! $action || ! $test_id ) {
			return;
		}

		$ab_model = new MEYVC_AB_Test();
		$redirect_error = admin_url( 'admin.php?page=meyvc-ab-tests' );
		$redirect_error = add_query_arg( 'error', 'invalid_nonce', $redirect_error );

		switch ( $action ) {
			case 'start':
				if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
					wp_safe_redirect( add_query_arg( 'error', 'unauthorized', admin_url( 'admin.php?page=meyvc-ab-tests' ) ) );
					exit;
				}
				$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
				if ( ! wp_verify_nonce( $nonce, 'start_ab_test' ) ) {
					wp_safe_redirect( $redirect_error );
					exit;
				}
				$ab_model->start( $test_id );
				wp_safe_redirect( admin_url( 'admin.php?page=meyvc-ab-tests&message=started' ) );
				exit;

			case 'pause':
				if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
					wp_safe_redirect( add_query_arg( 'error', 'unauthorized', admin_url( 'admin.php?page=meyvc-ab-tests' ) ) );
					exit;
				}
				$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
				if ( ! wp_verify_nonce( $nonce, 'pause_ab_test' ) ) {
					wp_safe_redirect( $redirect_error );
					exit;
				}
				$ab_model->pause( $test_id );
				wp_safe_redirect( admin_url( 'admin.php?page=meyvc-ab-tests&message=paused' ) );
				exit;

			case 'complete':
				if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
					wp_safe_redirect( add_query_arg( 'error', 'unauthorized', admin_url( 'admin.php?page=meyvc-ab-tests' ) ) );
					exit;
				}
				$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
				if ( ! wp_verify_nonce( $nonce, 'complete_ab_test' ) ) {
					wp_safe_redirect( $redirect_error );
					exit;
				}
				$test      = $ab_model->get( $test_id );
				$stats     = class_exists( 'MEYVC_AB_Statistics' ) ? MEYVC_AB_Statistics::calculate( $test ) : array( 'has_winner' => false, 'winner' => null );
				$winner_id = ! empty( $stats['has_winner'] ) && ! empty( $stats['winner'] ) ? $stats['winner']['variation_id'] : null;
				$ab_model->complete( $test_id, $winner_id );
				wp_safe_redirect( admin_url( 'admin.php?page=meyvc-ab-tests&message=completed' ) );
				exit;

			case 'delete':
				if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
					wp_safe_redirect( add_query_arg( 'error', 'unauthorized', admin_url( 'admin.php?page=meyvc-ab-tests' ) ) );
					exit;
				}
				$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
				if ( ! wp_verify_nonce( $nonce, 'delete_ab_test' ) ) {
					wp_safe_redirect( $redirect_error );
					exit;
				}
				$ab_model->delete( $test_id );
				wp_safe_redirect( admin_url( 'admin.php?page=meyvc-ab-tests&message=deleted' ) );
				exit;

			case 'apply_winner':
				if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
					wp_safe_redirect( add_query_arg( 'error', 'unauthorized', admin_url( 'admin.php?page=meyvc-ab-tests' ) ) );
					exit;
				}
				$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
				if ( ! wp_verify_nonce( $nonce, 'apply_winner' ) ) {
					wp_safe_redirect( $redirect_error );
					exit;
				}
				$winner_id = isset( $_GET['winner'] ) ? absint( $_GET['winner'] ) : 0;
				if ( $winner_id ) {
					$this->apply_ab_winner( $test_id, $winner_id );
				}
				wp_safe_redirect( admin_url( 'admin.php?page=meyvc-ab-tests&message=winner_applied' ) );
				exit;
		}
	}

	/**
	 * Apply winning variation to original campaign
	 *
	 * @param int $test_id              A/B test ID.
	 * @param int $winner_variation_id  Winning variation ID.
	 */
	private function apply_ab_winner( $test_id, $winner_variation_id ) {
		$ab_model = new MEYVC_AB_Test();
		$test = $ab_model->get( $test_id );
		$variation = $ab_model->get_variation( $winner_variation_id );

		if ( ! $test || ! $variation ) {
			return;
		}

		// If control won, just complete the test
		if ( ! empty( $variation->is_control ) ) {
			$ab_model->complete( $test_id, $winner_variation_id );
			return;
		}

		// Apply variation data to original campaign
		$variation_data = json_decode( $variation->campaign_data, true );

		if ( $variation_data && class_exists( 'MEYVC_Campaign' ) ) {
			$update_data = array();
			if ( ! empty( $variation_data['content'] ) ) {
				$c = $variation_data['content'];
				if ( is_array( $c ) ) {
					$update_data['content'] = $c;
				} elseif ( is_string( $c ) ) {
					$decoded_content = json_decode( $c, true );
					$update_data['content'] = ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded_content ) ) ? $decoded_content : $c;
				} else {
					$update_data['content'] = wp_json_encode( $c );
				}
			}
			if ( ! empty( $variation_data['styling'] ) ) {
				$s = $variation_data['styling'];
				if ( is_array( $s ) ) {
					$update_data['styling'] = $s;
				} elseif ( is_string( $s ) ) {
					$decoded_styling = json_decode( $s, true );
					$update_data['styling'] = ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded_styling ) ) ? $decoded_styling : $s;
				} else {
					$update_data['styling'] = wp_json_encode( $s );
				}
			}
			if ( ! empty( $update_data ) ) {
				MEYVC_Campaign::update( (int) $test->original_campaign_id, $update_data );
			}
		}

		$ab_model->complete( $test_id, $winner_variation_id );
	}

	/**
	 * Handle front-end error logging (graceful error handling).
	 */
	public function handle_log_error() {
		$nonce_raw = filter_input( INPUT_POST, 'nonce', FILTER_UNSAFE_RAW );
		$nonce     = is_string( $nonce_raw ) ? sanitize_text_field( wp_unslash( $nonce_raw ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'meyvc_log_error' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}

		// Rate limit per IP — nonce is public in meyvcConfig; prevents log-flooding DoS.
		if ( class_exists( 'MEYVC_Security' ) ) {
			$ip = MEYVC_Security::get_client_ip();
			if ( ! MEYVC_Security::check_rate_limit( 'meyvc_log_error_' . $ip, 10, 60 ) ) {
				wp_send_json_success();
				return;
			}
		}

		$data_raw = filter_input( INPUT_POST, 'data', FILTER_UNSAFE_RAW );
		$raw        = is_string( $data_raw ) ? wp_unslash( $data_raw ) : '';
		if ( strlen( $raw ) > 4096 ) {
			wp_send_json_success();
			return;
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			wp_send_json_success();
			return;
		}

		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG && class_exists( 'MEYVC_Error_Handler' ) ) {
			$message = isset( $data['message'] ) ? substr( (string) $data['message'], 0, 500 ) : '';
			$url     = isset( $data['url'] ) ? substr( (string) $data['url'], 0, 300 ) : '';
			if ( $message ) {
				MEYVC_Error_Handler::log( 'DEBUG', '[Meyvora Convert] JS Error: ' . $message );
			}
			if ( $url ) {
				MEYVC_Error_Handler::log( 'DEBUG', '[Meyvora Convert] URL: ' . $url );
			}
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: Save a single offer (drawer save). Capability and nonce checked.
	 */
	public function ajax_save_offer() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'meyvora-convert' ) ), 403 );
		}

		$nonce_raw = filter_input( INPUT_POST, 'meyvc_save_offer_nonce', FILTER_UNSAFE_RAW );
		$nonce     = is_string( $nonce_raw ) ? sanitize_text_field( wp_unslash( $nonce_raw ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'meyvc_save_offer_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce. Please refresh and try again.', 'meyvora-convert' ) ), 403 );
		}

		$post_in = filter_input_array( INPUT_POST );
		if ( ! is_array( $post_in ) ) {
			$post_in = array();
		}

		$max_offers = 5;
		$option_key = 'meyvc_dynamic_offers';

		$offers = get_option( $option_key, array() );
		if ( ! is_array( $offers ) ) {
			$offers = array();
		}
		$offers = array_pad( $offers, $max_offers, array() );

		$offer_index = isset( $post_in['meyvc_offer_index'] ) ? sanitize_text_field( wp_unslash( (string) $post_in['meyvc_offer_index'] ) ) : '';
		if ( $offer_index === '' ) {
			// Add: use first empty slot. Enforce MAX_OFFERS server-side.
			for ( $i = 0; $i < $max_offers; $i++ ) {
				$slot = isset( $offers[ $i ] ) && is_array( $offers[ $i ] ) ? $offers[ $i ] : array();
				if ( empty( trim( (string) ( $slot['headline'] ?? '' ) ) ) ) {
					$offer_index = $i;
					break;
				}
			}
			if ( $offer_index === '' ) {
				$offers_used_count = 0;
				$result_offers = array();
				foreach ( $offers as $i => $o ) {
					$o = is_array( $o ) ? $o : array();
					if ( ! empty( trim( (string) ( $o['headline'] ?? '' ) ) ) ) {
						$offers_used_count++;
						$result_offers[] = array(
							'index'          => $i,
							'offer'          => $o,
							'rule_summary'   => class_exists( 'MEYVC_Offer_Presenter' ) ? MEYVC_Offer_Presenter::summarize_conditions( $o ) : self::get_offer_rule_summary( $o ),
							'reward_summary' => class_exists( 'MEYVC_Offer_Presenter' ) ? MEYVC_Offer_Presenter::summarize_reward( $o ) : self::get_offer_reward_summary( $o ),
						);
					}
				}
				wp_send_json_error( array(
					'message'          => __( 'Offer limit reached (5).', 'meyvora-convert' ),
					'offers'           => $result_offers,
					'offers_used_count' => $offers_used_count,
					'max_offers'       => $max_offers,
				), 400 );
			}
		} else {
			$offer_index = absint( $offer_index );
			if ( $offer_index < 0 || $offer_index >= $max_offers ) {
				wp_send_json_error( array( 'message' => __( 'Invalid offer.', 'meyvora-convert' ) ), 400 );
			}
		}

		$mq_post = isset( $post_in['meyvc_drawer_min_qty_for_category'] ) ? wp_unslash( $post_in['meyvc_drawer_min_qty_for_category'] ) : '';
		$mq_fc   = is_string( $mq_post ) ? self::parse_min_qty_for_category( $mq_post ) : array();

		$raw = array(
			'headline'                      => isset( $post_in['meyvc_drawer_headline'] ) ? sanitize_text_field( wp_unslash( (string) $post_in['meyvc_drawer_headline'] ) ) : '',
			'priority'                      => isset( $post_in['meyvc_drawer_priority'] ) ? (int) $post_in['meyvc_drawer_priority'] : 10,
			'enabled'                       => ! empty( $post_in['meyvc_drawer_enabled'] ),
			'description'                   => isset( $post_in['meyvc_drawer_description'] ) ? sanitize_textarea_field( wp_unslash( (string) $post_in['meyvc_drawer_description'] ) ) : '',
			'min_cart_total'                => isset( $post_in['meyvc_drawer_min_cart_total'] ) && is_numeric( $post_in['meyvc_drawer_min_cart_total'] ) ? (float) $post_in['meyvc_drawer_min_cart_total'] : 0,
			'max_cart_total'                => isset( $post_in['meyvc_drawer_max_cart_total'] ) && is_numeric( $post_in['meyvc_drawer_max_cart_total'] ) ? (float) $post_in['meyvc_drawer_max_cart_total'] : 0,
			'min_items'                     => isset( $post_in['meyvc_drawer_min_items'] ) && is_numeric( $post_in['meyvc_drawer_min_items'] ) ? (int) $post_in['meyvc_drawer_min_items'] : 0,
			'first_time_customer'           => ! empty( $post_in['meyvc_drawer_first_time_customer'] ),
			'returning_customer_min_orders' => isset( $post_in['meyvc_drawer_returning_customer_min_orders'] ) && is_numeric( $post_in['meyvc_drawer_returning_customer_min_orders'] ) ? (int) $post_in['meyvc_drawer_returning_customer_min_orders'] : 0,
			'lifetime_spend_min'            => isset( $post_in['meyvc_drawer_lifetime_spend_min'] ) && is_numeric( $post_in['meyvc_drawer_lifetime_spend_min'] ) ? (float) $post_in['meyvc_drawer_lifetime_spend_min'] : 0,
			'allowed_roles'                 => isset( $post_in['meyvc_drawer_allowed_roles'] ) && is_array( $post_in['meyvc_drawer_allowed_roles'] ) ? array_map( 'sanitize_text_field', wp_unslash( $post_in['meyvc_drawer_allowed_roles'] ) ) : array(),
			'excluded_roles'                => isset( $post_in['meyvc_drawer_excluded_roles'] ) && is_array( $post_in['meyvc_drawer_excluded_roles'] ) ? array_map( 'sanitize_text_field', wp_unslash( $post_in['meyvc_drawer_excluded_roles'] ) ) : array(),
			'exclude_sale_items'             => ! empty( $post_in['meyvc_drawer_exclude_sale_items'] ),
			'include_categories'             => isset( $post_in['meyvc_drawer_include_categories'] ) && is_array( $post_in['meyvc_drawer_include_categories'] ) ? array_map( 'absint', wp_unslash( $post_in['meyvc_drawer_include_categories'] ) ) : array(),
			'exclude_categories'             => isset( $post_in['meyvc_drawer_exclude_categories'] ) && is_array( $post_in['meyvc_drawer_exclude_categories'] ) ? array_map( 'absint', wp_unslash( $post_in['meyvc_drawer_exclude_categories'] ) ) : array(),
			'include_products'               => isset( $post_in['meyvc_drawer_include_products'] ) ? array_filter( array_map( 'absint', array_map( 'trim', explode( ',', (string) wp_unslash( (string) $post_in['meyvc_drawer_include_products'] ) ) ) ) ) : array(),
			'exclude_products'               => isset( $post_in['meyvc_drawer_exclude_products'] ) ? array_filter( array_map( 'absint', array_map( 'trim', explode( ',', (string) wp_unslash( (string) $post_in['meyvc_drawer_exclude_products'] ) ) ) ) ) : array(),
			'cart_contains_category'         => isset( $post_in['meyvc_drawer_cart_contains_category'] ) && is_array( $post_in['meyvc_drawer_cart_contains_category'] ) ? array_map( 'absint', wp_unslash( $post_in['meyvc_drawer_cart_contains_category'] ) ) : array(),
			'min_qty_for_category'           => $mq_fc,
			'apply_to_categories'            => isset( $post_in['meyvc_drawer_apply_to_categories'] ) && is_array( $post_in['meyvc_drawer_apply_to_categories'] ) ? array_map( 'absint', wp_unslash( $post_in['meyvc_drawer_apply_to_categories'] ) ) : array(),
			'apply_to_products'              => isset( $post_in['meyvc_drawer_apply_to_products'] ) && is_array( $post_in['meyvc_drawer_apply_to_products'] ) ? array_map( 'absint', wp_unslash( $post_in['meyvc_drawer_apply_to_products'] ) ) : ( isset( $post_in['meyvc_drawer_apply_to_products'] ) ? array_filter( array_map( 'absint', array_map( 'trim', explode( ',', (string) wp_unslash( (string) $post_in['meyvc_drawer_apply_to_products'] ) ) ) ) ) : array() ),
			'per_category_discount'         => self::parse_per_category_discount_post(
				isset( $post_in['meyvc_drawer_per_category_discount_cat'] ) && is_array( $post_in['meyvc_drawer_per_category_discount_cat'] ) ? wp_unslash( $post_in['meyvc_drawer_per_category_discount_cat'] ) : array(),
				isset( $post_in['meyvc_drawer_per_category_discount_amount'] ) && is_array( $post_in['meyvc_drawer_per_category_discount_amount'] ) ? wp_unslash( $post_in['meyvc_drawer_per_category_discount_amount'] ) : array()
			),
			'reward_type'                    => isset( $post_in['meyvc_drawer_reward_type'] ) ? sanitize_text_field( wp_unslash( (string) $post_in['meyvc_drawer_reward_type'] ) ) : 'percent',
			'reward_amount'                  => isset( $post_in['meyvc_drawer_reward_amount'] ) ? (float) $post_in['meyvc_drawer_reward_amount'] : 10,
			'coupon_ttl_hours'              => isset( $post_in['meyvc_drawer_coupon_ttl_hours'] ) ? absint( $post_in['meyvc_drawer_coupon_ttl_hours'] ) : 48,
			'individual_use'                => ! empty( $post_in['meyvc_drawer_individual_use'] ),
			'rate_limit_hours'              => isset( $post_in['meyvc_drawer_rate_limit_hours'] ) && is_numeric( $post_in['meyvc_drawer_rate_limit_hours'] ) ? absint( $post_in['meyvc_drawer_rate_limit_hours'] ) : 6,
			'max_coupons_per_visitor'       => isset( $post_in['meyvc_drawer_max_coupons_per_visitor'] ) && is_numeric( $post_in['meyvc_drawer_max_coupons_per_visitor'] ) ? absint( $post_in['meyvc_drawer_max_coupons_per_visitor'] ) : 1,
			'conflict_offer_ids'            => isset( $post_in['meyvc_drawer_conflict_offer_ids'] ) && is_array( $post_in['meyvc_drawer_conflict_offer_ids'] ) ? array_map( 'sanitize_text_field', wp_unslash( $post_in['meyvc_drawer_conflict_offer_ids'] ) ) : array(),
		);
		$allowed_raw = isset( $post_in['meyvc_drawer_allowed_roles'] ) && is_array( $post_in['meyvc_drawer_allowed_roles'] ) ? array_map( 'sanitize_text_field', wp_unslash( $post_in['meyvc_drawer_allowed_roles'] ) ) : array();
		$raw['allowed_roles'] = array_values( array_filter( $allowed_raw, function ( $v ) { return $v !== ''; } ) );
		$excluded_raw = isset( $post_in['meyvc_drawer_excluded_roles'] ) && is_array( $post_in['meyvc_drawer_excluded_roles'] ) ? array_map( 'sanitize_text_field', wp_unslash( $post_in['meyvc_drawer_excluded_roles'] ) ) : array();
		$raw['excluded_roles'] = array_values( array_filter( $excluded_raw, function ( $v ) { return $v !== ''; } ) );

		if ( class_exists( 'MEYVC_Offer_Schema' ) ) {
			$offer = MEYVC_Offer_Schema::sanitize_offer( $raw );
			$valid = MEYVC_Offer_Schema::validate_offer( $offer );
			if ( is_wp_error( $valid ) ) {
				$errors = MEYVC_Offer_Schema::errors_to_array( $valid );
				$offers_used_count = 0;
				$result_offers = array();
				foreach ( $offers as $i => $o ) {
					$o = is_array( $o ) ? $o : array();
					if ( ! empty( trim( (string) ( $o['headline'] ?? '' ) ) ) ) {
						$offers_used_count++;
						$result_offers[] = array(
							'index'          => $i,
							'offer'          => $o,
							'rule_summary'   => class_exists( 'MEYVC_Offer_Presenter' ) ? MEYVC_Offer_Presenter::summarize_conditions( $o ) : self::get_offer_rule_summary( $o ),
							'reward_summary' => class_exists( 'MEYVC_Offer_Presenter' ) ? MEYVC_Offer_Presenter::summarize_reward( $o ) : self::get_offer_reward_summary( $o ),
						);
					}
				}
				wp_send_json_error( array(
					'message'          => __( 'Validation failed.', 'meyvora-convert' ),
					'errors'           => $errors,
					'offers'           => $result_offers,
					'offers_used_count' => $offers_used_count,
					'max_offers'       => $max_offers,
				), 400 );
			}
		} else {
			$offer = array(
				'headline'                      => isset( $raw['headline'] ) ? $raw['headline'] : '',
				'description'                   => isset( $raw['description'] ) ? $raw['description'] : '',
				'min_cart_total'                => isset( $raw['min_cart_total'] ) ? $raw['min_cart_total'] : 0,
				'max_cart_total'                => isset( $raw['max_cart_total'] ) && $raw['max_cart_total'] > 0 ? $raw['max_cart_total'] : 0,
				'min_items'                     => isset( $raw['min_items'] ) ? $raw['min_items'] : 0,
				'first_time_customer'           => ! empty( $raw['first_time_customer'] ),
				'returning_customer_min_orders' => isset( $raw['returning_customer_min_orders'] ) ? $raw['returning_customer_min_orders'] : 0,
				'lifetime_spend_min'            => isset( $raw['lifetime_spend_min'] ) ? $raw['lifetime_spend_min'] : 0,
				'allowed_roles'                 => isset( $raw['allowed_roles'] ) ? $raw['allowed_roles'] : array(),
				'excluded_roles'                => isset( $raw['excluded_roles'] ) ? $raw['excluded_roles'] : array(),
				'reward_type'                   => isset( $raw['reward_type'] ) ? $raw['reward_type'] : 'percent',
				'reward_amount'                 => isset( $raw['reward_amount'] ) ? $raw['reward_amount'] : 10,
				'coupon_ttl_hours'              => isset( $raw['coupon_ttl_hours'] ) && $raw['coupon_ttl_hours'] > 0 ? $raw['coupon_ttl_hours'] : 48,
				'priority'                      => isset( $raw['priority'] ) ? $raw['priority'] : 10,
				'enabled'                       => ! empty( $raw['enabled'] ),
				'individual_use'                => ! empty( $raw['individual_use'] ),
				'rate_limit_hours'              => isset( $raw['rate_limit_hours'] ) && $raw['rate_limit_hours'] >= 0 ? $raw['rate_limit_hours'] : 6,
				'max_coupons_per_visitor'       => isset( $raw['max_coupons_per_visitor'] ) ? $raw['max_coupons_per_visitor'] : 1,
				'exclude_sale_items'            => ! empty( $raw['exclude_sale_items'] ),
				'include_categories'            => isset( $raw['include_categories'] ) ? $raw['include_categories'] : array(),
				'exclude_categories'            => isset( $raw['exclude_categories'] ) ? $raw['exclude_categories'] : array(),
				'include_products'             => isset( $raw['include_products'] ) ? $raw['include_products'] : array(),
				'exclude_products'              => isset( $raw['exclude_products'] ) ? $raw['exclude_products'] : array(),
				'cart_contains_category'       => isset( $raw['cart_contains_category'] ) ? $raw['cart_contains_category'] : array(),
				'min_qty_for_category'         => isset( $raw['min_qty_for_category'] ) ? $raw['min_qty_for_category'] : array(),
				'apply_to_categories'          => isset( $raw['apply_to_categories'] ) ? $raw['apply_to_categories'] : array(),
				'apply_to_products'            => isset( $raw['apply_to_products'] ) ? $raw['apply_to_products'] : array(),
				'per_category_discount'        => isset( $raw['per_category_discount'] ) && is_array( $raw['per_category_discount'] ) ? $raw['per_category_discount'] : array(),
				'conflict_offer_ids'          => isset( $raw['conflict_offer_ids'] ) && is_array( $raw['conflict_offer_ids'] ) ? array_map( 'sanitize_text_field', $raw['conflict_offer_ids'] ) : array(),
			);
		}

		$prev = isset( $offers[ $offer_index ] ) && is_array( $offers[ $offer_index ] ) ? $offers[ $offer_index ] : array();
		if ( ! empty( $prev['id'] ) ) {
			$offer['id'] = sanitize_text_field( (string) $prev['id'] );
		} else {
			$offer['id'] = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : ( 'meyvc_' . uniqid( '', true ) );
		}
		$offer['updated_at'] = gmdate( 'c' );
		if ( ! empty( $offer['conflict_offer_ids'] ) && ! empty( $offer['id'] ) ) {
			$sid = (string) $offer['id'];
			$offer['conflict_offer_ids'] = array_values( array_filter( $offer['conflict_offer_ids'], function ( $x ) use ( $sid ) {
				return (string) $x !== $sid && (string) $x !== '';
			} ) );
		}

		$offers[ $offer_index ] = $offer;
		update_option( $option_key, $offers );

		$offers_used_count = 0;
		$result_offers = array();
		foreach ( $offers as $i => $o ) {
			$o = is_array( $o ) ? $o : array();
			if ( ! empty( trim( (string) ( $o['headline'] ?? '' ) ) ) ) {
				$offers_used_count++;
				$result_offers[] = array(
					'index'          => $i,
					'offer'          => $o,
					'rule_summary'   => class_exists( 'MEYVC_Offer_Presenter' ) ? MEYVC_Offer_Presenter::summarize_conditions( $o ) : self::get_offer_rule_summary( $o ),
					'reward_summary' => class_exists( 'MEYVC_Offer_Presenter' ) ? MEYVC_Offer_Presenter::summarize_reward( $o ) : self::get_offer_reward_summary( $o ),
				);
			}
		}

		wp_send_json_success( array(
			'offer_index'        => $offer_index,
			'offers_used_count'  => $offers_used_count,
			'max_offers'         => $max_offers,
			'offers'             => $result_offers,
		) );
	}

	/**
	 * AJAX: Verify Anthropic API key with a minimal messages request.
	 */
	public function ajax_ai_test_connection() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'meyvora-convert' ) ), 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'meyvc_ai_test_connection' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'meyvora-convert' ) ), 403 );
		}
		if ( ! class_exists( 'MEYVC_AI_Client' ) || ! function_exists( 'meyvc_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'AI client is not available.', 'meyvora-convert' ) ), 500 );
		}
		if ( ! MEYVC_AI_Client::is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'Add and save an API key before testing.', 'meyvora-convert' ) ) );
		}
		$result = MEYVC_AI_Client::request(
			'',
			array(
				array(
					'role'    => 'user',
					'content' => 'ping',
				),
			),
			1
		);
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			$code = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;
			if ( $code >= 400 && $code < 500 ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ), $code );
			}
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 502 );
		}
		meyvc_settings()->set( 'ai', 'connection_verified', 'yes' );
		wp_send_json_success(
			array(
				'model' => MEYVC_AI_Client::MODEL,
			)
		);
	}

	/**
	 * AJAX: Abandoned cart email preview. Returns subject + body with sample placeholders.
	 * Template content is sanitized with wp_kses_post; placeholders are replaced with safe sample values.
	 */
	public function ajax_abandoned_cart_preview() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'meyvora-convert' ) ), 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'meyvc_abandoned_cart_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'meyvora-convert' ) ), 403 );
		}
		$subject_tpl = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$body_tpl    = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';
		if ( trim( (string) $body_tpl ) === '' && function_exists( 'meyvc_settings' ) ) {
			$body_tpl = meyvc_settings()->get_abandoned_cart_email_body_default();
		}
		if ( trim( (string) $subject_tpl ) === '' && function_exists( 'meyvc_settings' ) ) {
			$opts      = meyvc_settings()->get_abandoned_cart_settings();
			$subject_tpl = isset( $opts['email_subject_template'] ) ? sanitize_text_field( $opts['email_subject_template'] ) : __( 'You left something in your cart – {store_name}', 'meyvora-convert' );
		}
		$values  = class_exists( 'MEYVC_Abandoned_Cart_Reminder' ) ? MEYVC_Abandoned_Cart_Reminder::get_placeholder_values( null, 'SAMPLE10', true ) : array();
		$subject = class_exists( 'MEYVC_Abandoned_Cart_Reminder' ) ? MEYVC_Abandoned_Cart_Reminder::replace_placeholders( $subject_tpl, $values ) : $subject_tpl;
		$body    = class_exists( 'MEYVC_Abandoned_Cart_Reminder' ) ? MEYVC_Abandoned_Cart_Reminder::replace_placeholders( $body_tpl, $values ) : $body_tpl;
		wp_send_json_success( array( 'subject' => $subject, 'body' => $body ) );
	}

	/**
	 * AJAX: Send test abandoned cart email to given address.
	 * Template content is sanitized with wp_kses_post; placeholders are replaced with safe sample values.
	 */
	public function ajax_abandoned_cart_send_test() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'meyvora-convert' ) ), 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'meyvc_abandoned_cart_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'meyvora-convert' ) ), 403 );
		}
		$to = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
		if ( ! is_email( $to ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'meyvora-convert' ) ), 400 );
		}
		$subject_tpl = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$body_tpl    = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';
		if ( trim( (string) $body_tpl ) === '' && function_exists( 'meyvc_settings' ) ) {
			$body_tpl = meyvc_settings()->get_abandoned_cart_email_body_default();
		}
		if ( trim( (string) $subject_tpl ) === '' && function_exists( 'meyvc_settings' ) ) {
			$opts       = meyvc_settings()->get_abandoned_cart_settings();
			$subject_tpl = isset( $opts['email_subject_template'] ) ? sanitize_text_field( $opts['email_subject_template'] ) : __( 'You left something in your cart – {store_name}', 'meyvora-convert' );
		}
		$values  = class_exists( 'MEYVC_Abandoned_Cart_Reminder' ) ? MEYVC_Abandoned_Cart_Reminder::get_placeholder_values( null, 'SAMPLE10', true ) : array();
		$subject = class_exists( 'MEYVC_Abandoned_Cart_Reminder' ) ? MEYVC_Abandoned_Cart_Reminder::replace_placeholders( $subject_tpl, $values ) : $subject_tpl;
		$body    = class_exists( 'MEYVC_Abandoned_Cart_Reminder' ) ? MEYVC_Abandoned_Cart_Reminder::replace_placeholders( $body_tpl, $values ) : $body_tpl;
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$sent    = wp_mail( $to, $subject, $body, $headers );
		if ( ! $sent ) {
			global $phpmailer;
			$err = is_object( $phpmailer ) && isset( $phpmailer->ErrorInfo ) ? $phpmailer->ErrorInfo : __( 'wp_mail failed', 'meyvora-convert' );
			wp_send_json_error( array( 'message' => $err ), 500 );
		}
		wp_send_json_success( array( 'message' => __( 'Test email sent.', 'meyvora-convert' ) ) );
	}

	/**
	 * AJAX: Search products for SelectWoo (abandoned cart / offers product selects).
	 * Expects GET term (search string). Returns JSON array of { id, text } for Select2/SelectWoo.
	 */
	public function ajax_search_products() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json( array() );
		}
		$term = self::get_get_text( 'term' );
		$results = array();
		if ( function_exists( 'wc_get_products' ) && strlen( $term ) >= 1 ) {
			$products = wc_get_products( array(
				'status'   => 'publish',
				'limit'    => 20,
				's'         => $term,
				'orderby'   => 'title',
				'order'     => 'ASC',
			) );
			foreach ( $products as $product ) {
				if ( $product && is_callable( array( $product, 'get_id' ) ) ) {
					$results[] = array(
						'id'   => (string) $product->get_id(),
						'text' => $product->get_name(),
					);
				}
			}
		}
		wp_send_json( array( 'results' => $results ) );
	}

	/**
	 * Markup for KPI period-over-period change (matches meyvora_kpi_change in analytics partial).
	 *
	 * @param float|int $current  Current value.
	 * @param float|int $previous Previous value.
	 * @return string
	 */
	private function meyvc_format_kpi_change_markup( $current, $previous ) {
		if ( ! $previous ) {
			return '';
		}
		$pct  = round( ( ( (float) $current - (float) $previous ) / (float) $previous ) * 100 );
		$dir  = $pct >= 0 ? 'up' : 'down';
		$icon = $pct >= 0 ? '↑' : '↓';
		return sprintf(
			'<span class="meyvc-kpi-change meyvc-kpi-change--%s">%s %s%%</span>',
			esc_attr( $dir ),
			esc_html( $icon ),
			esc_html( (string) abs( $pct ) )
		);
	}

	/**
	 * Build Revenue influenced KPI tooltip (attribution).
	 *
	 * @param string $model_label Translated model label.
	 * @return string Plain text for title attribute.
	 */
	private function meyvc_revenue_attribution_tooltip_text( $model_label ) {
		return implode(
			' ',
			array_filter(
				array(
					__( 'Shows revenue from orders where Meyvora Convert had a touchpoint.', 'meyvora-convert' ),
					sprintf(
						/* translators: %s: attribution model label, e.g. "Last touch" */
						__( 'Attribution model: %s.', 'meyvora-convert' ),
						$model_label
					),
					__( 'Change model using the selector above.', 'meyvora-convert' ),
				)
			)
		);
	}

	/**
	 * AJAX: Save analytics attribution model.
	 */
	public function ajax_set_attribution_model() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'meyvora-convert' ) ), 403 );
		}
		check_ajax_referer( 'meyvc_set_attribution_model', '_wpnonce' );
		if ( ! class_exists( 'MEYVC_Attribution' ) ) {
			wp_send_json_error( array( 'message' => __( 'Attribution is unavailable.', 'meyvora-convert' ) ), 500 );
		}
		$raw = isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '';
		$m   = MEYVC_Attribution::normalize_model( $raw );
		MEYVC_Attribution::set_model( $m );
		wp_send_json_success(
			array(
				'model'       => $m,
				'model_label' => MEYVC_Attribution::get_model_label( $m ),
			)
		);
	}

	/**
	 * AJAX: Revenue influenced KPI + RPV after attribution change.
	 */
	public function ajax_get_attributed_revenue() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'meyvora-convert' ) ), 403 );
		}
		check_ajax_referer( 'meyvc_get_campaign_revenue_chart', '_wpnonce' );
		if ( ! class_exists( 'MEYVC_Attribution' ) || ! class_exists( 'MEYVC_Analytics' ) ) {
			wp_send_json_error( array( 'message' => __( 'Attribution is unavailable.', 'meyvora-convert' ) ), 500 );
		}
		$from        = isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
		$to          = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';
		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
		$campaign_id = $campaign_id > 0 ? $campaign_id : null;
		$model_raw   = isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '';
		$model       = $model_raw !== '' ? MEYVC_Attribution::normalize_model( $model_raw ) : MEYVC_Attribution::get_current_model();

		list( $prev_from, $prev_to ) = MEYVC_Attribution::get_comparison_period( $from, $to );

		$current  = MEYVC_Attribution::get_total_campaign_attributed_revenue( $from, $to, $model, $campaign_id );
		$previous = MEYVC_Attribution::get_total_campaign_attributed_revenue( $prev_from, $prev_to, $model, $campaign_id );

		$analytics = new MEYVC_Analytics();
		$summary   = $analytics->get_summary( $from, $to, $campaign_id );
		$imp       = (int) ( $summary['impressions'] ?? 0 );
		$rpv       = $imp > 0 ? $current / $imp : 0.0;

		$label = MEYVC_Attribution::get_model_label( $model );

		wp_send_json_success(
			array(
				'revenue'          => $current,
				'revenue_html'     => function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $current ) ) : esc_html( (string) $current ),
				'revenue_formatted' => function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $current ) ) : esc_html( (string) $current ),
				'change_html'      => $this->meyvc_format_kpi_change_markup( $current, $previous ),
				'rpv_html'         => function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $rpv ) ) : esc_html( (string) $rpv ),
				'model'            => $model,
				'model_label'      => $label,
				'tooltip'          => $this->meyvc_revenue_attribution_tooltip_text( $label ),
			)
		);
	}

	/**
	 * AJAX: Campaign / offer revenue chart datasets for selected attribution model.
	 */
	public function ajax_get_campaign_revenue_chart() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'meyvora-convert' ) ), 403 );
		}
		check_ajax_referer( 'meyvc_get_campaign_revenue_chart', '_wpnonce' );
		if ( ! class_exists( 'MEYVC_Attribution' ) ) {
			wp_send_json_error( array( 'message' => __( 'Attribution is unavailable.', 'meyvora-convert' ) ), 500 );
		}
		$from        = isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
		$to          = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';
		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
		$campaign_id = $campaign_id > 0 ? $campaign_id : null;
		$model_raw   = isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '';
		$model       = $model_raw !== '' ? MEYVC_Attribution::normalize_model( $model_raw ) : MEYVC_Attribution::get_current_model();

		$by_camp = MEYVC_Attribution::get_campaign_chart_rows( $from, $to, $model, $campaign_id, 10 );
		$by_off  = array();
		$off_raw = MEYVC_Attribution::get_offer_attribution( $from, $to, $model );
		$n       = 0;
		foreach ( $off_raw as $row ) {
			$by_off[] = array(
				'label'   => (string) ( $row['name'] ?? '' ),
				'revenue' => (float) ( $row['revenue'] ?? 0 ),
			);
			++$n;
			if ( $n >= 10 ) {
				break;
			}
		}

		$label = MEYVC_Attribution::get_model_label( $model );

		wp_send_json_success(
			array(
				'revenueByCampaign' => $by_camp,
				'revenueByOffer'    => $by_off,
				'model'             => $model,
				'model_label'       => $label,
				'tooltip'           => $this->meyvc_revenue_attribution_tooltip_text( $label ),
			)
		);
	}

	/**
	 * admin_post: Cancel scheduled reminders for an abandoned cart.
	 */
	public function handle_abandoned_cart_cancel_reminders() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'meyvora-convert' ), 403 );
		}
		$nonce_raw = filter_input( INPUT_GET, '_wpnonce', FILTER_UNSAFE_RAW );
		$nonce     = is_string( $nonce_raw ) ? sanitize_text_field( wp_unslash( $nonce_raw ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'meyvc_abandoned_carts_list' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'meyvora-convert' ), 403 );
		}
		$id = self::get_get_absint( 'id' );
		if ( $id > 0 && class_exists( 'MEYVC_Abandoned_Cart_Tracker' ) ) {
			MEYVC_Abandoned_Cart_Tracker::cancel_scheduled_reminders( $id );
		}
		$this->redirect_abandoned_carts_list( 'cancel_reminders' );
	}

	/**
	 * admin_post: Mark abandoned cart as recovered.
	 */
	public function handle_abandoned_cart_mark_recovered() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'meyvora-convert' ), 403 );
		}
		$nonce_raw = filter_input( INPUT_GET, '_wpnonce', FILTER_UNSAFE_RAW );
		$nonce     = is_string( $nonce_raw ) ? sanitize_text_field( wp_unslash( $nonce_raw ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'meyvc_abandoned_carts_list' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'meyvora-convert' ), 403 );
		}
		$id = self::get_get_absint( 'id' );
		if ( $id > 0 && class_exists( 'MEYVC_Abandoned_Cart_Tracker' ) ) {
			MEYVC_Abandoned_Cart_Tracker::mark_recovered_by_id( $id );
		}
		$this->redirect_abandoned_carts_list( 'mark_recovered' );
	}

	/**
	 * admin_post: Resend reminder email 1 for an abandoned cart.
	 */
	public function handle_abandoned_cart_resend() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'meyvora-convert' ), 403 );
		}
		$nonce_raw = filter_input( INPUT_GET, '_wpnonce', FILTER_UNSAFE_RAW );
		$nonce     = is_string( $nonce_raw ) ? sanitize_text_field( wp_unslash( $nonce_raw ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'meyvc_abandoned_carts_list' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'meyvora-convert' ), 403 );
		}
		$id = self::get_get_absint( 'id' );
		$sent = false;
		if ( $id > 0 && class_exists( 'MEYVC_Abandoned_Cart_Reminder' ) ) {
			$sent = MEYVC_Abandoned_Cart_Reminder::send_reminder_immediately( $id, 1 );
		}
		$this->redirect_abandoned_carts_list( $sent ? 'resend_ok' : 'resend_fail' );
	}

	/**
	 * Redirect back to abandoned carts list with optional message.
	 *
	 * @param string|null $message Key for notice (cancel_reminders, mark_recovered, resend_ok, resend_fail).
	 */
	private function redirect_abandoned_carts_list( $message = null ) {
		$base = admin_url( 'admin.php?page=meyvc-abandoned-carts' );
		$args = array();
		$status_filter = self::get_get_text( 'status_filter' );
		if ( $status_filter !== '' ) {
			$args['status_filter'] = $status_filter;
		}
		$search = self::get_get_text( 'search' );
		if ( $search !== '' ) {
			$args['search'] = rawurlencode( $search );
		}
		$paged = self::get_get_absint( 'paged' );
		if ( $paged > 0 ) {
			$args['paged'] = $paged;
		}
		if ( $message ) {
			$args['meyvc_notice'] = $message;
		}
		wp_safe_redirect( add_query_arg( $args, $base ) );
		exit;
	}

	/**
	 * AJAX: Abandoned cart drawer content (cart items, checkout link, email log, coupon).
	 */
	public function ajax_abandoned_cart_drawer() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'meyvora-convert' ) ), 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'meyvc_abandoned_carts_list' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'meyvora-convert' ) ), 403 );
		}
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( $id <= 0 || ! class_exists( 'MEYVC_Abandoned_Cart_Tracker' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid cart.', 'meyvora-convert' ) ), 400 );
		}
		$row = MEYVC_Abandoned_Cart_Tracker::get_row_by_id( $id );
		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'Cart not found.', 'meyvora-convert' ) ), 404 );
		}
		$currency = ! empty( $row->currency ) ? $row->currency : ( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '' );
		$items = array();
		$data = ! empty( $row->cart_json ) ? json_decode( $row->cart_json, true ) : null;
		$cart_total_val = null;
		if ( is_array( $data ) ) {
			if ( ! empty( $data['items'] ) ) {
				foreach ( $data['items'] as $item ) {
					$items[] = array(
						'name'     => isset( $item['name'] ) ? $item['name'] : __( 'Item', 'meyvora-convert' ),
						'quantity' => isset( $item['quantity'] ) ? (int) $item['quantity'] : 1,
						'price'    => isset( $item['price'] ) ? $item['price'] : null,
						'total'    => isset( $item['total'] ) ? $item['total'] : null,
					);
				}
			}
			$cart_total_val = isset( $data['totals']['total'] ) ? (float) $data['totals']['total'] : null;
		}
		$checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' );
		$email_log = array(
			'email_1' => $row->email_1_sent_at ? $row->email_1_sent_at : null,
			'email_2' => $row->email_2_sent_at ? $row->email_2_sent_at : null,
			'email_3' => $row->email_3_sent_at ? $row->email_3_sent_at : null,
		);
		$segment      = 'standard';
		$schedule     = array();
		$segment_label = __( 'Standard', 'meyvora-convert' );
		if ( class_exists( 'MEYVC_Abandoned_Cart_Reminder' ) ) {
			$segment       = MEYVC_Abandoned_Cart_Reminder::get_cart_segment( $row );
			$segment_label = ( $segment === 'high' )
				? __( 'High Value', 'meyvora-convert' )
				: __( 'Standard', 'meyvora-convert' );
			$schedule = MEYVC_Abandoned_Cart_Reminder::get_schedule_debug( $row );
		}
		wp_send_json_success( array(
			'id'              => (int) $row->id,
			'email'           => $row->email,
			'cart_items'      => $items,
			'currency'        => $currency,
			'cart_total'      => $cart_total_val,
			'checkout_url'    => $checkout_url,
			'email_log'       => $email_log,
			'discount_coupon' => ! empty( $row->discount_coupon ) ? $row->discount_coupon : null,
			'segment'         => $segment,
			'segment_label'   => $segment_label,
			'schedule'        => $schedule,
		) );
	}

	/**
	 * Build rule summary string for an offer (for AJAX response).
	 *
	 * @param array $o Offer data.
	 * @return string
	 */
	/**
	 * Parse min_qty_for_category from textarea (one per line: category_id:min_qty).
	 *
	 * @param string $text Raw input.
	 * @return array<int,int> Map category_id => min_qty.
	 */
	public static function parse_min_qty_for_category( $text ) {
		$out = array();
		$text = is_string( $text ) ? $text : '';
		$lines = array_filter( array_map( 'trim', explode( "\n", $text ) ) );
		foreach ( $lines as $line ) {
			if ( strpos( $line, ':' ) !== false ) {
				list( $cat_id, $min_qty ) = array_map( 'trim', explode( ':', $line, 2 ) );
				$cat_id  = absint( $cat_id );
				$min_qty = max( 0, (int) $min_qty );
				if ( $cat_id > 0 && $min_qty > 0 ) {
					$out[ $cat_id ] = $min_qty;
				}
			}
		}
		return $out;
	}

	/**
	 * Parse per_category_discount from POST arrays (category IDs and amounts).
	 *
	 * @param array $cat_ids  Array of category IDs (e.g. from meyvc_drawer_per_category_discount_cat[]).
	 * @param array $amounts  Array of amounts (e.g. from meyvc_drawer_per_category_discount_amount[]).
	 * @return array<int, float> Map category_id => amount.
	 */
	public static function parse_per_category_discount_post( $cat_ids, $amounts ) {
		$out = array();
		if ( ! is_array( $cat_ids ) || ! is_array( $amounts ) ) {
			return $out;
		}
		$cat_ids  = array_map( 'absint', wp_unslash( $cat_ids ) );
		$amounts  = array_map( function ( $v ) { return is_numeric( $v ) ? (float) $v : null; }, wp_unslash( $amounts ) );
		foreach ( $cat_ids as $idx => $cat_id ) {
			if ( $cat_id > 0 && isset( $amounts[ $idx ] ) && $amounts[ $idx ] !== null ) {
				$out[ $cat_id ] = $amounts[ $idx ];
			}
		}
		return $out;
	}

	public static function get_offer_rule_summary( $o ) {
		$parts = array();
		$fmt = function ( $amount ) {
			return number_format_i18n( (float) $amount, 2 ) . ( function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '' );
		};
		if ( ! empty( $o['min_cart_total'] ) ) {
			$parts[] = sprintf( /* translators: %s is the formatted minimum cart total. */ __( 'Cart ≥ %s', 'meyvora-convert' ), $fmt( $o['min_cart_total'] ) );
		}
		if ( ! empty( $o['max_cart_total'] ) ) {
			$parts[] = sprintf( /* translators: %s is the formatted maximum cart total. */ __( 'Cart ≤ %s', 'meyvora-convert' ), $fmt( $o['max_cart_total'] ) );
		}
		if ( ! empty( $o['min_items'] ) ) {
			$parts[] = sprintf( /* translators: %d is the minimum number of cart items. */ _n( '%d item', '%d items', $o['min_items'], 'meyvora-convert' ), $o['min_items'] );
		}
		if ( ! empty( $o['first_time_customer'] ) ) {
			$parts[] = __( 'First-time customer', 'meyvora-convert' );
		}
		if ( ! empty( $o['returning_customer_min_orders'] ) ) {
			$parts[] = sprintf( /* translators: %d is the minimum number of previous orders. */ __( 'Returning: %d+ orders', 'meyvora-convert' ), $o['returning_customer_min_orders'] );
		}
		if ( ! empty( $o['lifetime_spend_min'] ) ) {
			$parts[] = sprintf( /* translators: %s is the formatted minimum lifetime spend. */ __( 'Lifetime spend ≥ %s', 'meyvora-convert' ), $fmt( $o['lifetime_spend_min'] ) );
		}
		return empty( $parts ) ? __( 'Any cart', 'meyvora-convert' ) : implode( ' · ', $parts );
	}

	/**
	 * Build reward summary string for an offer (for AJAX response).
	 *
	 * @param array $o Offer data.
	 * @return string
	 */
	public static function get_offer_reward_summary( $o ) {
		$type = isset( $o['reward_type'] ) ? $o['reward_type'] : 'percent';
		$amount = isset( $o['reward_amount'] ) ? (float) $o['reward_amount'] : 0;
		if ( $type === 'free_shipping' ) {
			return __( 'Free shipping', 'meyvora-convert' );
		}
		if ( $type === 'percent' ) {
			return sprintf( /* translators: %s is the percentage discount value. */ __( '%s%% off', 'meyvora-convert' ), $amount );
		}
		if ( $type === 'fixed' ) {
			$formatted = number_format_i18n( (float) $amount, 2 );
			if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
				$formatted = get_woocommerce_currency_symbol() . $formatted;
			}
			return sprintf( /* translators: %s is the formatted fixed discount amount. */ __( '%s off', 'meyvora-convert' ), $formatted );
		}
		return __( 'Discount', 'meyvora-convert' );
	}
}
