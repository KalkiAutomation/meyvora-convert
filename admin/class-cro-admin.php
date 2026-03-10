<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The admin-specific functionality of the plugin.
 */
class CRO_Admin {

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
		// Front-end error reporting (graceful error handling).
		add_action( 'wp_ajax_cro_log_error', array( $this, 'handle_log_error' ) );
		add_action( 'wp_ajax_nopriv_cro_log_error', array( $this, 'handle_log_error' ) );
		// Offers: save single offer via drawer (AJAX).
		add_action( 'wp_ajax_cro_save_offer', array( $this, 'ajax_save_offer' ) );
		// Abandoned cart emails: preview and send test (AJAX).
		add_action( 'wp_ajax_cro_abandoned_cart_preview', array( $this, 'ajax_abandoned_cart_preview' ) );
		add_action( 'wp_ajax_cro_abandoned_cart_send_test', array( $this, 'ajax_abandoned_cart_send_test' ) );
		// Abandoned carts list: row actions and drawer.
		add_action( 'admin_post_cro_abandoned_cart_cancel_reminders', array( $this, 'handle_abandoned_cart_cancel_reminders' ) );
		add_action( 'admin_post_cro_abandoned_cart_mark_recovered', array( $this, 'handle_abandoned_cart_mark_recovered' ) );
		add_action( 'admin_post_cro_abandoned_cart_resend', array( $this, 'handle_abandoned_cart_resend' ) );
		add_action( 'wp_ajax_cro_abandoned_cart_drawer', array( $this, 'ajax_abandoned_cart_drawer' ) );
		add_action( 'wp_ajax_cro_search_products', array( $this, 'ajax_search_products' ) );
		add_action( 'admin_footer', array( $this, 'render_admin_debug_panel' ), 999 );
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
			'cro-presets',
			array( $this, 'render_presets' )
		);

		// Campaigns
		add_submenu_page(
			'meyvora-convert',
			__( 'Campaigns', 'meyvora-convert' ),
			__( 'Campaigns', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'cro-campaigns',
			array( $this, 'render_campaigns' )
		);

		// Campaign Builder (hidden from menu)
		add_submenu_page(
			null, // Hidden
			__( 'Edit Campaign', 'meyvora-convert' ),
			__( 'Edit Campaign', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'cro-campaign-edit',
			array( $this, 'render_campaign_builder' )
		);

		// On-Page Boosters
		add_submenu_page(
			'meyvora-convert',
			__( 'On-Page Boosters', 'meyvora-convert' ),
			__( 'On-Page Boosters', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'cro-boosters',
			array( $this, 'render_boosters' )
		);

		// Cart Optimizer
		add_submenu_page(
			'meyvora-convert',
			__( 'Cart Optimizer', 'meyvora-convert' ),
			__( 'Cart Optimizer', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'cro-cart',
			array( $this, 'render_cart_optimizer' )
		);

		// Abandoned Carts (list)
		add_submenu_page(
			'meyvora-convert',
			__( 'Abandoned Carts', 'meyvora-convert' ),
			__( 'Abandoned Carts', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'cro-abandoned-carts',
			array( $this, 'render_abandoned_carts_list' )
		);

		// Abandoned Cart Emails (templates/settings)
		add_submenu_page(
			'meyvora-convert',
			__( 'Abandoned Cart Emails', 'meyvora-convert' ),
			__( 'Abandoned Cart Emails', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'cro-abandoned-cart',
			array( $this, 'render_abandoned_cart_emails' )
		);

		// Offers (dynamic offers config)
		add_submenu_page(
			'meyvora-convert',
			__( 'Offers', 'meyvora-convert' ),
			__( 'Offers', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'cro-offers',
			array( $this, 'render_offers' )
		);

		// Checkout Optimizer
		add_submenu_page(
			'meyvora-convert',
			__( 'Checkout Optimizer', 'meyvora-convert' ),
			__( 'Checkout Optimizer', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'cro-checkout',
			array( $this, 'render_checkout_optimizer' )
		);

		// A/B Tests
		add_submenu_page(
			'meyvora-convert',
			__( 'A/B Tests', 'meyvora-convert' ),
			__( 'A/B Tests', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'cro-ab-tests',
			array( $this, 'render_ab_tests' )
		);

		// New A/B Test (hidden from menu but accessible)
		add_submenu_page(
			null, // Hidden from menu
			__( 'Create A/B Test', 'meyvora-convert' ),
			__( 'Create A/B Test', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'cro-ab-test-new',
			array( $this, 'render_ab_test_new' )
		);

		// View A/B Test (hidden from menu but accessible)
		add_submenu_page(
			null, // Hidden from menu
			__( 'View A/B Test', 'meyvora-convert' ),
			__( 'View A/B Test', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'cro-ab-test-view',
			array( $this, 'render_ab_test_view' )
		);

		// Analytics
		add_submenu_page(
			'meyvora-convert',
			__( 'Analytics', 'meyvora-convert' ),
			__( 'Analytics', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'cro-analytics',
			array( $this, 'render_analytics' )
		);

		// Insights
		add_submenu_page(
			'meyvora-convert',
			__( 'Insights', 'meyvora-convert' ),
			__( 'Insights', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'cro-insights',
			array( $this, 'render_insights' )
		);

		// Settings
		add_submenu_page(
			'meyvora-convert',
			__( 'Settings', 'meyvora-convert' ),
			__( 'Settings', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'cro-settings',
			array( $this, 'render_settings' )
		);

		// System Status (under Settings in menu order)
		add_submenu_page(
			'meyvora-convert',
			__( 'System Status', 'meyvora-convert' ),
			__( 'System Status', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'cro-system-status',
			array( $this, 'render_system_status' )
		);

		// Tools → Import / Export
		add_submenu_page(
			'meyvora-convert',
			__( 'Import / Export', 'meyvora-convert' ),
			__( 'Tools', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'cro-tools',
			array( $this, 'render_tools' )
		);

		// Developer (hooks, templates)
		add_submenu_page(
			'meyvora-convert',
			__( 'Developer', 'meyvora-convert' ),
			__( 'Developer', 'meyvora-convert' ),
			'manage_meyvora_convert',
			'cro-developer',
			array( $this, 'render_developer' )
		);
	}

	/**
	 * Render Presets library page.
	 */
	public function render_presets() {
		CRO_Admin_UI::render_page( array(
			'title'           => get_admin_page_title() ?: __( 'Presets', 'meyvora-convert' ),
			'subtitle'        => '',
			'active_tab'      => 'cro-presets',
			'primary_action'  => null,
			'content_partial' => CRO_PLUGIN_DIR . 'admin/partials/cro-admin-presets.php',
			'wrap_class'      => 'cro-presets-page',
		) );
	}

	/**
	 * Render dashboard (main CRO page). Shows onboarding wizard when onboarding=1 or when not yet completed.
	 */
	public function render_dashboard() {
		$onboarding_request   = isset( $_GET['cro_onboarding'] ) && (string) $_GET['cro_onboarding'] === '1';
		$onboarding_completed = get_option( 'cro_onboarding_completed', false );

		if ( $onboarding_request || ! $onboarding_completed ) {
			CRO_Admin_UI::render_page( array(
				'title'           => __( 'Welcome to Meyvora Convert', 'meyvora-convert' ),
				'subtitle'        => __( 'Complete these steps to get started. You can change settings anytime.', 'meyvora-convert' ),
				'active_tab'      => 'meyvora-convert',
				'primary_action'  => null,
				'content_partial' => CRO_PLUGIN_DIR . 'admin/partials/cro-admin-onboarding.php',
				'wrap_class'      => 'cro-onboarding-page',
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
		CRO_Admin_UI::render_page( array(
			'title'           => get_admin_page_title() ?: __( 'Abandoned Carts', 'meyvora-convert' ),
			'subtitle'        => '',
			'active_tab'      => 'cro-abandoned-carts',
			'primary_action'  => array( 'label' => __( 'Settings', 'meyvora-convert' ), 'href' => admin_url( 'admin.php?page=cro-abandoned-cart' ) ),
			'content_partial' => CRO_PLUGIN_DIR . 'admin/partials/cro-admin-abandoned-carts-list.php',
			'wrap_class'      => 'cro-abandoned-carts-list',
		) );
	}

	/**
	 * Render Abandoned Cart Emails admin page (templates/settings).
	 */
	public function render_abandoned_cart_emails() {
		CRO_Admin_UI::render_page( array(
			'title'           => get_admin_page_title() ?: __( 'Abandoned Cart Emails', 'meyvora-convert' ),
			'subtitle'        => __( 'Configure reminder emails sent when a customer leaves items in their cart. Use the placeholders below in subject and body.', 'meyvora-convert' ),
			'active_tab'      => 'cro-abandoned-cart',
			'primary_action'  => array( 'label' => __( 'Save settings', 'meyvora-convert' ), 'form_id' => 'cro-abandoned-cart-form' ),
			'content_partial' => CRO_PLUGIN_DIR . 'admin/partials/cro-admin-abandoned-cart.php',
			'wrap_class'      => 'cro-abandoned-cart-emails',
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
		CRO_Admin_UI::render_page( array(
			'title'           => get_admin_page_title() ?: __( 'A/B Tests', 'meyvora-convert' ),
			'subtitle'        => '',
			'active_tab'      => 'cro-ab-tests',
			'primary_action'  => array( 'label' => __( 'New A/B Test', 'meyvora-convert' ), 'href' => admin_url( 'admin.php?page=cro-ab-test-new' ) ),
			'content_partial' => CRO_PLUGIN_DIR . 'admin/partials/cro-admin-ab-tests.php',
			'wrap_class'      => 'cro-ab-tests-page',
		) );
	}

	/**
	 * Render new A/B test page
	 */
	public function render_ab_test_new() {
		CRO_Admin_UI::render_page( array(
			'title'           => __( 'New A/B Test', 'meyvora-convert' ),
			'subtitle'        => '',
			'active_tab'      => 'cro-ab-tests',
			'content_partial' => CRO_PLUGIN_DIR . 'admin/partials/cro-admin-ab-test-new.php',
			'wrap_class'      => 'cro-ab-test-new',
		) );
	}

	/**
	 * Render A/B test detail page
	 */
	public function render_ab_test_view() {
		CRO_Admin_UI::render_page( array(
			'title'           => __( 'A/B Test', 'meyvora-convert' ),
			'subtitle'        => '',
			'active_tab'      => 'cro-ab-tests',
			'content_partial' => CRO_PLUGIN_DIR . 'admin/partials/cro-admin-ab-test-view.php',
			'wrap_class'      => 'cro-ab-test-view',
		) );
	}

	/**
	 * Render Analytics page (with layout and Export CSV header CTA).
	 */
	public function render_analytics() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		if ( $action === 'export' ) {
			$this->handle_csv_export();
			return;
		}
		$date_from   = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : date( 'Y-m-d', strtotime( '-30 days' ) );
		$date_to     = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : date( 'Y-m-d' );
		$campaign_id = isset( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : null;
		if ( $campaign_id === 0 ) {
			$campaign_id = null;
		}
		$export_url = add_query_arg(
			array(
				'page'     => 'cro-analytics',
				'action'   => 'export',
				'format'   => 'events',
				'from'     => $date_from,
				'to'       => $date_to,
				'_wpnonce' => wp_create_nonce( 'cro_export' ),
			),
			admin_url( 'admin.php' )
		);
		if ( $campaign_id !== null ) {
			$export_url = add_query_arg( 'campaign_id', $campaign_id, $export_url );
		}
		CRO_Admin_UI::render_page( array(
			'title'           => get_admin_page_title() ?: __( 'Analytics', 'meyvora-convert' ),
			'subtitle'        => __( 'Track impressions, conversions, and revenue from campaigns and offers.', 'meyvora-convert' ),
			'active_tab'      => 'cro-analytics',
			'primary_action'  => array( 'label' => __( 'Export events CSV', 'meyvora-convert' ), 'href' => $export_url ),
			'content_partial' => CRO_PLUGIN_DIR . 'admin/partials/cro-admin-analytics.php',
			'wrap_class'      => 'cro-analytics-page',
		) );
	}

	/**
	 * Render Insights page (actionable cards from tracking data).
	 */
	public function render_insights() {
		CRO_Admin_UI::render_page( array(
			'title'           => get_admin_page_title() ?: __( 'Insights', 'meyvora-convert' ),
			'subtitle'        => __( 'Actionable recommendations from your campaigns, offers, and boosters.', 'meyvora-convert' ),
			'active_tab'      => 'cro-insights',
			'primary_action'  => null,
			'content_partial' => CRO_PLUGIN_DIR . 'admin/partials/cro-admin-insights.php',
			'wrap_class'      => 'cro-insights-page',
			'header_pills'    => array( __( 'Last 7 days', 'meyvora-convert' ) ),
		) );
	}

	/**
	 * Register campaign builder script and style early (admin_init priority 1)
	 * so Verify Installation can detect wp_script_is('cro-campaign-builder', 'registered').
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
			'cro-campaign-builder',
			CRO_PLUGIN_URL . 'admin/js/cro-campaign-builder.js',
			array( 'jquery', 'cro-admin', 'wp-api-fetch' ),
			CRO_VERSION,
			true
		);
		wp_register_style(
			'cro-campaign-builder',
			CRO_PLUGIN_URL . 'admin/css/cro-campaign-builder.css',
			array(),
			CRO_VERSION
		);
	}

	/**
	 * Register styles for CRO admin pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_styles( $hook ) {
		$hook = (string) ( $hook ?? '' );
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$is_cro_hook = ( strpos( $hook, 'cro-' ) !== false || strpos( $hook, 'cro_' ) !== false || strpos( $hook, 'meyvora-convert' ) !== false );
		$is_cro_page = ( $page !== '' && ( strpos( $page, 'cro-' ) !== false || strpos( $page, 'cro_' ) !== false || $page === 'meyvora-convert' ) );
		if ( ! $is_cro_hook && ! $is_cro_page ) {
			return;
		}

		// Add Google Fonts (DM Sans) for campaign builder preview and admin UI.
		wp_enqueue_style(
			'cro-google-fonts',
			'https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap',
			array(),
			null
		);

		// Design system is source of truth; legacy css (cro-admin, cro-admin-ui, cro-admin-modern, cro-admin-brand-identity) not enqueued.
		wp_enqueue_style(
			'cro-admin-design-system',
			CRO_PLUGIN_URL . 'admin/css/cro-admin-design-system.css',
			array(),
			CRO_VERSION
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
				'cro-admin-selectwoo-override',
				CRO_PLUGIN_URL . 'admin/css/cro-admin-selectwoo-override.css',
				array( 'select2', 'cro-admin-design-system' ),
				CRO_VERSION
			);
		}

		// Page-specific CSS only (design system provides base). Use both hook and page for hidden/submenu screens.
		$admin_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$is_analytics = ( strpos( $hook, 'cro-analytics' ) !== false || $admin_page === 'cro-analytics' );
		$is_offers    = ( strpos( $hook, 'cro-offers' ) !== false || $admin_page === 'cro-offers' );
		$is_insights  = ( strpos( $hook, 'cro-insights' ) !== false || $admin_page === 'cro-insights' );

		if ( $is_analytics ) {
			wp_enqueue_style(
				'cro-analytics',
				CRO_PLUGIN_URL . 'admin/css/cro-analytics.css',
				array( 'cro-admin-design-system' ),
				CRO_VERSION
			);
		}
		if ( $is_offers ) {
			wp_enqueue_style(
				'cro-offers',
				CRO_PLUGIN_URL . 'admin/css/cro-offers.css',
				array( 'cro-admin-design-system' ),
				CRO_VERSION
			);
		}
		if ( $is_insights ) {
			wp_enqueue_style(
				'cro-admin-insights',
				CRO_PLUGIN_URL . 'admin/css/cro-admin-insights.css',
				array( 'cro-admin-design-system' ),
				CRO_VERSION
			);
		}

		// Dashboard (main Meyvora Convert page): KPI cards, quick actions, activity list.
		if ( $hook === 'toplevel_page_meyvora-convert' ) {
			wp_enqueue_style(
				'cro-admin-dashboard',
				CRO_PLUGIN_URL . 'admin/css/cro-admin-dashboard.css',
				array( 'cro-admin-design-system' ),
				CRO_VERSION
			);
		}

		// Campaign builder/edit page (hidden submenu: hook can vary; check both hook and page).
		if ( strpos( $hook, 'cro-campaign-edit' ) !== false || strpos( $hook, 'cro-campaign-builder' ) !== false
			|| $admin_page === 'cro-campaign-edit' || $admin_page === 'cro-campaign-builder' ) {
			wp_enqueue_style(
				'cro-popup',
				CRO_PLUGIN_URL . 'public/css/cro-popup.css',
				array(),
				CRO_VERSION
			);
			wp_enqueue_style(
				'cro-campaign-builder',
				CRO_PLUGIN_URL . 'admin/css/cro-campaign-builder.css',
				array( 'cro-admin-design-system', 'cro-popup' ),
				CRO_VERSION
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
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$is_cro_hook = ( strpos( $hook, 'cro-' ) !== false || strpos( $hook, 'cro_' ) !== false || strpos( $hook, 'meyvora-convert' ) !== false );
		$is_cro_page = ( $page !== '' && ( strpos( $page, 'cro-' ) !== false || strpos( $page, 'cro_' ) !== false || $page === 'meyvora-convert' ) );
		if ( ! $is_cro_hook && ! $is_cro_page ) {
			return;
		}

		wp_enqueue_script(
			'cro-admin',
			CRO_PLUGIN_URL . 'admin/js/cro-admin.js',
			array( 'jquery', 'wp-color-picker' ),
			CRO_VERSION,
			true
		);
		wp_enqueue_media();

		$cro_debug = get_option( 'cro_admin_debug', false ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG );
		wp_localize_script(
			'cro-admin',
			'croAdmin',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'adminUrl'  => admin_url( 'admin.php' ),
				'siteUrl'   => get_site_url(),
				'restUrl'   => rest_url( 'meyvora-convert/v1/' ),
				'nonce'     => wp_create_nonce( 'cro_admin_nonce' ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'currency'  => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '',
				'debug'     => (bool) apply_filters( 'cro_admin_debug', $cro_debug ),
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
				),
			)
		);

		// Campaign builder: enqueue on builder pages only (script/style registered in register_campaign_builder_assets).
		$current_page_slug = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$is_builder_page = ( strpos( $hook, 'cro-campaign-edit' ) !== false )
			|| ( strpos( $hook, 'cro-campaign-builder' ) !== false )
			|| $current_page_slug === 'cro-campaign-edit'
			|| $current_page_slug === 'cro-campaign-builder';

		if ( $is_builder_page ) {
			wp_enqueue_media();
			wp_enqueue_script( 'wp-api-fetch' );
			wp_localize_script(
				'wp-api-fetch',
				'wpApiSettings',
				array(
					'root'  => esc_url_raw( rest_url() ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
				)
			);
			wp_enqueue_style(
				'cro-popup',
				CRO_PLUGIN_URL . 'public/css/cro-popup.css',
				array(),
				CRO_VERSION
			);
			wp_enqueue_style(
				'cro-campaign-builder',
				CRO_PLUGIN_URL . 'admin/css/cro-campaign-builder.css',
				array( 'cro-admin-design-system', 'cro-popup' ),
				CRO_VERSION
			);
			wp_enqueue_script( 'cro-campaign-builder' );
			wp_localize_script(
				'cro-campaign-builder',
				'croBuilderIcons',
				array(
					'remove' => class_exists( 'CRO_Icons' ) ? \CRO_Icons::svg( 'x', array( 'class' => 'cro-ico' ) ) : '×',
					'upload' => class_exists( 'CRO_Icons' ) ? \CRO_Icons::svg( 'upload', array( 'class' => 'cro-ico' ) ) : '',
					'image'  => class_exists( 'CRO_Icons' ) ? \CRO_Icons::svg( 'image', array( 'class' => 'cro-ico' ) ) : '',
					'check'  => class_exists( 'CRO_Icons' ) ? \CRO_Icons::svg( 'check', array( 'class' => 'cro-ico' ) ) : '',
				)
			);
		}

		// SelectWoo + cro-selectwoo.js: enqueue on ALL CRO admin pages (caller already filtered by hook cro- or cro_).
		if ( ! wp_script_is( 'selectWoo', 'registered' ) && class_exists( 'WooCommerce' ) ) {
			$selectwoo_js = plugins_url( 'assets/js/selectWoo/selectWoo.full.min.js', 'woocommerce/woocommerce.php' );
			if ( $selectwoo_js ) {
				wp_register_script( 'selectWoo', $selectwoo_js, array( 'jquery' ), '1.0.6', true );
			}
		}
		if ( wp_script_is( 'selectWoo', 'registered' ) ) {
			wp_enqueue_script( 'selectWoo' );
			wp_enqueue_script(
				'cro-selectwoo',
				CRO_PLUGIN_URL . 'admin/js/cro-selectwoo.js',
				array( 'jquery', 'selectWoo' ),
				CRO_VERSION,
				true
			);
			wp_localize_script(
				'cro-selectwoo',
				'croSelectWoo',
				array(
					'placeholder'    => __( 'Search or select…', 'meyvora-convert' ),
					'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
					'searchProducts'  => __( 'Search products…', 'meyvora-convert' ),
				)
			);
		}

		// Offers page: drawer + AJAX save. Use page slug so it loads when page=cro-offers.
		$admin_page_scripts = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( strpos( $hook, 'cro-offers' ) !== false || $admin_page_scripts === 'cro-offers' ) {
			wp_enqueue_script(
				'cro-offers',
				CRO_PLUGIN_URL . 'admin/js/cro-offers.js',
				array( 'jquery' ),
				CRO_VERSION,
				true
			);
			wp_localize_script(
				'cro-offers',
				'croOffersI18n',
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
					'deleteConfirmName' => __( 'Delete offer "%s"?', 'meyvora-convert' ),
					'close'             => __( 'Close', 'meyvora-convert' ),
					'notifications'     => __( 'Notifications', 'meyvora-convert' ),
					'offersUsed'      => __( 'offers used', 'meyvora-convert' ),
					'limitReached'    => __( 'Offer limit reached (5).', 'meyvora-convert' ),
					'noOffersYet'     => __( 'No offers yet', 'meyvora-convert' ),
					'emptyDesc'       => __( 'Create your first offer to show a dynamic reward on cart and checkout.', 'meyvora-convert' ),
					'createFirst'     => __( 'Create your first offer', 'meyvora-convert' ),
					'priorityLabel'   => __( 'Priority: %s', 'meyvora-convert' ),
					'reorderNonce'    => wp_create_nonce( 'cro_offers_ajax' ),
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
					'summaryCartMin'   => __( 'Cart ≥ %s', 'meyvora-convert' ),
					'summaryCartRange' => __( 'Cart %s – %s', 'meyvora-convert' ),
					'summaryItems'     => __( '%d items', 'meyvora-convert' ),
					'summaryFirstTime' => __( 'First-time customer', 'meyvora-convert' ),
					'summaryReturning' => __( 'Returning customer (≥%d orders)', 'meyvora-convert' ),
					'summaryLifetime'  => __( 'Lifetime spend ≥ %s', 'meyvora-convert' ),
					'summaryRewardPct' => __( '%s%% off', 'meyvora-convert' ),
					'summaryRewardFix' => __( '%s off', 'meyvora-convert' ),
					'summaryRewardShip'=> __( 'Free shipping', 'meyvora-convert' ),
					'summaryExpires'   => __( 'Expires %sh', 'meyvora-convert' ),
					'summaryExcludeSale' => __( 'Exclude sale items', 'meyvora-convert' ),
					'summaryBullet'    => ' • ',
					'summaryArrow'     => ' → ',
					'newOffer'         => __( 'New offer', 'meyvora-convert' ),
					'checkIcon'        => class_exists( 'CRO_Icons' ) ? \CRO_Icons::svg( 'check', array( 'class' => 'cro-ico' ) ) : '✓',
					'crossIcon'         => class_exists( 'CRO_Icons' ) ? \CRO_Icons::svg( 'x', array( 'class' => 'cro-ico' ) ) : '✗',
					'moveUpIcon'        => class_exists( 'CRO_Icons' ) ? \CRO_Icons::svg( 'chevron-up', array( 'class' => 'cro-ico' ) ) : '↑',
					'moveDownIcon'      => class_exists( 'CRO_Icons' ) ? \CRO_Icons::svg( 'chevron-down', array( 'class' => 'cro-ico' ) ) : '↓',
					'editIcon'          => class_exists( 'CRO_Icons' ) ? \CRO_Icons::svg( 'pencil', array( 'class' => 'cro-ico' ) ) : '',
					'duplicateIcon'     => class_exists( 'CRO_Icons' ) ? \CRO_Icons::svg( 'plus', array( 'class' => 'cro-ico' ) ) : '',
					'deleteIcon'        => class_exists( 'CRO_Icons' ) ? \CRO_Icons::svg( 'trash', array( 'class' => 'cro-ico' ) ) : '',
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
			'cro-classic-editor-campaign',
			CRO_PLUGIN_URL . 'admin/js/cro-classic-editor-campaign.js',
			array( 'jquery', 'thickbox' ),
			CRO_VERSION,
			true
		);

		wp_localize_script(
			'cro-classic-editor-campaign',
			'croCampaignClassic',
			array(
				'modalTitle' => __( 'Insert CRO Campaign', 'meyvora-convert' ),
			)
		);
	}

	/**
	 * Add "Add CRO Campaign" button above editor (media_buttons). Only for users with manage_meyvora_convert.
	 */
	public function render_media_button_cro_campaign() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			return;
		}

		$campaigns = array();
		if ( class_exists( 'CRO_Campaign' ) ) {
			$rows = CRO_Campaign::get_all( array( 'limit' => 200 ) );
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
		<button type="button" id="cro-insert-campaign-btn" class="button" title="<?php esc_attr_e( 'Insert a Meyvora Convert campaign shortcode', 'meyvora-convert' ); ?>">
			<?php echo class_exists( 'CRO_Icons' ) ? \CRO_Icons::svg( 'plus', array( 'class' => 'cro-ico' ) ) : ''; ?>
			<?php esc_html_e( 'Add CRO Campaign', 'meyvora-convert' ); ?>
		</button>
		<div id="cro-campaign-modal-content" class="cro-is-hidden">
			<div class="cro-campaign-modal-inner">
				<p>
					<label for="cro-campaign-select"><?php esc_html_e( 'Select campaign:', 'meyvora-convert' ); ?></label>
				</p>
				<select id="cro-campaign-select" class="cro-modern-select cro-selectwoo" data-placeholder="<?php esc_attr_e( '— Select campaign —', 'meyvora-convert' ); ?>">
					<option value="0"><?php esc_html_e( '— Select campaign —', 'meyvora-convert' ); ?></option>
					<?php foreach ( $campaigns as $c ) : ?>
						<option value="<?php echo esc_attr( (string) $c['id'] ); ?>">
							<?php echo esc_html( $c['name'] ? $c['name'] : sprintf( __( 'Campaign #%d', 'meyvora-convert' ), $c['id'] ) ); ?>
							<?php if ( $c['status'] !== 'active' ) : ?>
								(<?php echo esc_html( $c['status'] ); ?>)
							<?php endif; ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="cro-modal-actions">
					<button type="button" id="cro-campaign-insert" class="button button-primary"><?php esc_html_e( 'Insert shortcode', 'meyvora-convert' ); ?></button>
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
		CRO_Admin_UI::render_page( array(
			'title'           => __( 'Meyvora Convert Dashboard', 'meyvora-convert' ),
			'subtitle'        => __( 'Overview of conversions, revenue, and active conversion tools.', 'meyvora-convert' ),
			'active_tab'      => 'meyvora-convert',
			'primary_action'  => null,
			'content_partial' => CRO_PLUGIN_DIR . 'admin/partials/cro-admin-dashboard.php',
			'wrap_class'      => 'cro-dashboard',
		) );
	}

	/**
	 * Display campaigns list page.
	 */
	public function display_campaigns() {
		$pills = array();
		if ( class_exists( 'CRO_Campaign' ) && method_exists( 'CRO_Campaign', 'get_all' ) ) {
			$campaigns = CRO_Campaign::get_all();
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
		CRO_Admin_UI::render_page( array(
			'title'           => get_admin_page_title() ?: __( 'Campaigns', 'meyvora-convert' ),
			'subtitle'        => '',
			'active_tab'      => 'cro-campaigns',
			'content_partial' => CRO_PLUGIN_DIR . 'admin/partials/cro-admin-campaigns.php',
			'wrap_class'      => 'cro-campaigns-page',
			'header_pills'    => $pills,
		) );
	}

	/**
	 * Display campaign editor (hidden menu, linked from campaigns list).
	 */
	public function display_campaign_editor() {
		CRO_Admin_UI::render_page( array(
			'title'           => __( 'Edit Campaign', 'meyvora-convert' ),
			'subtitle'        => '',
			'active_tab'      => 'cro-campaigns',
			'content_partial' => CRO_PLUGIN_DIR . 'admin/partials/cro-admin-campaign-builder.php',
			'wrap_class'      => 'cro-campaign-builder',
		) );
	}

	/**
	 * Display on-page boosters page.
	 */
	public function display_boosters() {
		CRO_Admin_UI::render_page( array(
			'title'           => __( 'On-Page Conversion Boosters', 'meyvora-convert' ),
			'subtitle'        => __( 'These elements appear on your product and cart pages to encourage conversions.', 'meyvora-convert' ),
			'active_tab'      => 'cro-boosters',
			'primary_action'  => array( 'label' => __( 'Save changes', 'meyvora-convert' ), 'form_id' => 'cro-boosters-form' ),
			'content_partial' => CRO_PLUGIN_DIR . 'admin/partials/cro-admin-boosters.php',
			'wrap_class'      => 'cro-boosters-page',
		) );
	}

	/**
	 * Display cart optimizer page.
	 */
	public function display_cart_optimizer() {
		CRO_Admin_UI::render_page( array(
			'title'           => __( 'Cart Page Optimizer', 'meyvora-convert' ),
			'subtitle'        => __( 'The cart page is high-intent real estate. Use it to build confidence and reduce hesitation.', 'meyvora-convert' ),
			'active_tab'      => 'cro-cart',
			'primary_action'  => array( 'label' => __( 'Save settings', 'meyvora-convert' ), 'form_id' => 'cro-cart-form' ),
			'content_partial' => CRO_PLUGIN_DIR . 'admin/partials/cro-admin-cart.php',
			'wrap_class'      => 'cro-cart-page',
		) );
	}

	/**
	 * Display offers page (dynamic offers config + test panel).
	 */
	public function display_offers() {
		$offers = get_option( 'cro_dynamic_offers', array() );
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
		CRO_Admin_UI::render_page( array(
			'title'           => __( 'Offers', 'meyvora-convert' ),
			'subtitle'        => __( 'Show a single dynamic offer on cart and checkout based on rules and priority.', 'meyvora-convert' ),
			'active_tab'      => 'cro-offers',
			'primary_action'  => array(
				'label'      => __( '+ Add Offer', 'meyvora-convert' ),
				'button_id'  => 'cro-offers-add-btn',
				'attributes' => array( 'data-cro-drawer' => 'add' ),
			),
			'content_partial' => CRO_PLUGIN_DIR . 'admin/partials/cro-admin-offers.php',
			'wrap_class'      => 'cro-offers-page',
			'header_pills'    => $pills,
		) );
	}

	/**
	 * Display checkout optimizer page.
	 */
	public function display_checkout_optimizer() {
		CRO_Admin_UI::render_page( array(
			'title'           => __( 'Checkout Page Optimizer', 'meyvora-convert' ),
			'subtitle'        => __( 'Reduce friction and build trust on the checkout page.', 'meyvora-convert' ),
			'active_tab'      => 'cro-checkout',
			'primary_action'  => array( 'label' => __( 'Save settings', 'meyvora-convert' ), 'form_id' => 'cro-checkout-form' ),
			'content_partial' => CRO_PLUGIN_DIR . 'admin/partials/cro-admin-checkout.php',
			'wrap_class'      => 'cro-checkout-page',
		) );
	}

	/**
	 * Display A/B tests page.
	 */
	public function display_ab_tests() {
		CRO_Admin_UI::render_page( array(
			'title'           => get_admin_page_title() ?: __( 'A/B Tests', 'meyvora-convert' ),
			'subtitle'        => '',
			'active_tab'      => 'cro-ab-tests',
			'primary_action'  => array( 'label' => __( 'New A/B Test', 'meyvora-convert' ), 'href' => admin_url( 'admin.php?page=cro-ab-test-new' ) ),
			'content_partial' => CRO_PLUGIN_DIR . 'admin/partials/cro-admin-ab-tests.php',
			'wrap_class'      => 'cro-ab-tests-page',
		) );
	}

	/**
	 * Display settings page.
	 */
	public function display_settings() {
		CRO_Admin_UI::render_page( array(
			'title'           => get_admin_page_title() ?: __( 'Settings', 'meyvora-convert' ),
			'subtitle'        => __( 'Configure analytics, debug, and data. Run a self-test from System Status for support.', 'meyvora-convert' ),
			'active_tab'      => 'cro-settings',
			'primary_action'  => null,
			'content_partial' => CRO_PLUGIN_DIR . 'admin/partials/cro-admin-settings.php',
			'wrap_class'      => 'cro-admin-settings',
		) );
	}

	/**
	 * Render System Status page.
	 */
	public function render_system_status() {
		CRO_Admin_UI::render_page( array(
			'title'           => get_admin_page_title() ?: __( 'System Status', 'meyvora-convert' ),
			'subtitle'        => __( 'Environment and compatibility checks for Meyvora Convert. Use the report below when contacting support.', 'meyvora-convert' ),
			'active_tab'      => 'cro-system-status',
			'primary_action'  => array( 'label' => __( 'Verify Installation', 'meyvora-convert' ), 'form_id' => 'cro-verify-installation-form' ),
			'content_partial' => CRO_PLUGIN_DIR . 'admin/partials/cro-admin-system-status.php',
			'wrap_class'      => 'cro-admin-system-status',
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
		CRO_Admin_UI::render_page( array(
			'title'           => get_admin_page_title() ?: __( 'Import / Export', 'meyvora-convert' ),
			'subtitle'        => '',
			'active_tab'      => 'cro-tools',
			'content_partial' => CRO_PLUGIN_DIR . 'admin/partials/cro-admin-tools.php',
			'wrap_class'      => 'cro-admin-tools',
		) );
	}

	/**
	 * Render Developer (hooks, templates) page.
	 */
	public function render_developer() {
		CRO_Admin_UI::render_page( array(
			'title'           => get_admin_page_title() ?: __( 'Developer', 'meyvora-convert' ),
			'subtitle'        => __( 'Actions, filters, and template overrides for extending Meyvora Convert.', 'meyvora-convert' ),
			'active_tab'      => 'cro-developer',
			'content_partial' => CRO_PLUGIN_DIR . 'admin/partials/cro-admin-developer.php',
			'wrap_class'      => 'cro-admin-developer',
		) );
	}

	/**
	 * Redirect to onboarding after first activation (once per install).
	 */
	public function handle_activation_redirect() {
		if ( ! get_transient( 'cro_activation_redirect' ) || ! current_user_can( 'manage_meyvora_convert' ) ) {
			return;
		}
		delete_transient( 'cro_activation_redirect' );
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'meyvora-convert' && isset( $_GET['cro_onboarding'] ) && (string) $_GET['cro_onboarding'] === '1' ) {
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=meyvora-convert&cro_onboarding=1' ) );
		exit;
	}

	/**
	 * Handle "Restart Onboarding" from Settings: clear flag and redirect to wizard.
	 */
	public function handle_onboarding_restart() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		if ( $action !== 'cro_restart_onboarding' ) {
			return;
		}
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			return;
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cro_restart_onboarding' ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'invalid_nonce', admin_url( 'admin.php?page=cro-settings' ) ) );
			exit;
		}
		update_option( 'cro_onboarding_completed', false );
		wp_safe_redirect( admin_url( 'admin.php?page=meyvora-convert&cro_onboarding=1' ) );
		exit;
	}

	/**
	 * Handle "Skip" onboarding: mark completed and redirect to dashboard.
	 */
	public function handle_onboarding_skip() {
		if ( ! isset( $_GET['cro_skip_onboarding'] ) || ! current_user_can( 'manage_meyvora_convert' ) ) {
			return;
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cro_skip_onboarding' ) ) {
			return;
		}
		update_option( 'cro_onboarding_completed', true );
		wp_safe_redirect( admin_url( 'admin.php?page=meyvora-convert' ) );
		exit;
	}

	/**
	 * Handle onboarding checklist form: save toggles and/or mark complete.
	 */
	public function handle_onboarding_save() {
		if ( ! isset( $_POST['cro_onboarding_checklist'] ) || ! current_user_can( 'manage_meyvora_convert' ) ) {
			return;
		}
		$nonce = isset( $_POST['cro_onboarding_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['cro_onboarding_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cro_onboarding' ) ) {
			return;
		}

		$settings = function_exists( 'cro_settings' ) ? cro_settings() : null;
		if ( $settings ) {
			$settings->set( 'general', 'shipping_bar_enabled', ! empty( $_POST['feature_shipping_bar'] ) );
			$settings->set( 'general', 'sticky_cart_enabled', ! empty( $_POST['feature_sticky_cart'] ) );
		}

		if ( ! empty( $_POST['cro_onboarding_done'] ) ) {
			update_option( 'cro_onboarding_completed', true );
			wp_safe_redirect( admin_url( 'admin.php?page=meyvora-convert&onboarding_done=1' ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=meyvora-convert&cro_onboarding=1' ) );
		exit;
	}

	/**
	 * Handle Apply Preset: apply preset and redirect back to Presets page.
	 */
	public function handle_apply_preset() {
		$action = isset( $_POST['cro_apply_preset'] ) ? 'post' : ( isset( $_GET['action'] ) && sanitize_text_field( wp_unslash( $_GET['action'] ) ) === 'cro_apply_preset' ? 'get' : '' );
		if ( ! $action ) {
			return;
		}
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'unauthorized', admin_url( 'admin.php?page=cro-presets' ) ) );
			exit;
		}
		$nonce_key = $action === 'post' ? 'cro_preset_nonce' : '_wpnonce';
		$nonce_val = $action === 'post' ? ( isset( $_POST['cro_preset_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['cro_preset_nonce'] ) ) : '' ) : ( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '' );
		if ( ! wp_verify_nonce( $nonce_val, 'cro_apply_preset' ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'invalid_nonce', admin_url( 'admin.php?page=cro-presets' ) ) );
			exit;
		}
		$preset_id = $action === 'post' ? ( isset( $_POST['preset_id'] ) ? sanitize_text_field( wp_unslash( $_POST['preset_id'] ) ) : '' ) : ( isset( $_GET['preset_id'] ) ? sanitize_text_field( wp_unslash( $_GET['preset_id'] ) ) : '' );
		if ( ! $preset_id || ! class_exists( 'CRO_Presets' ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'invalid_preset', admin_url( 'admin.php?page=cro-presets' ) ) );
			exit;
		}
		$result = CRO_Presets::apply( $preset_id );
		if ( ! empty( $result['success'] ) ) {
			$url = admin_url( 'admin.php?page=cro-presets&preset_applied=1&message=' . rawurlencode( $result['message'] ) );
			if ( ! empty( $result['campaign_id'] ) ) {
				$url = add_query_arg( 'campaign_id', (int) $result['campaign_id'], $url );
			}
			wp_safe_redirect( $url );
			exit;
		}
		wp_safe_redirect( add_query_arg( 'error', 'apply_failed', admin_url( 'admin.php?page=cro-presets' ) ) );
		exit;
	}

	/**
	 * Handle Quick Launch (recommended CRO setup in one click).
	 */
	public function handle_quick_launch() {
		if ( ! isset( $_POST['cro_quick_launch'] ) || sanitize_text_field( wp_unslash( $_POST['cro_quick_launch'] ) ) !== 'recommended' ) {
			return;
		}
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			return;
		}
		if ( ! isset( $_POST['cro_quick_launch_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cro_quick_launch_nonce'] ) ), 'cro_quick_launch' ) ) {
			return;
		}
		if ( ! function_exists( 'cro_quick_launch_apply' ) ) {
			return;
		}
		$applied = cro_quick_launch_apply( 'recommended' );
		$url = admin_url( 'admin.php?page=meyvora-convert' );
		if ( $applied ) {
			$url = add_query_arg( 'cro_quick_launch_done', '1', $url );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Handle export request (Tools → Export: selected campaign as JSON, no analytics).
	 */
	public function handle_export() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		if ( $action !== 'cro_export' ) {
			return;
		}

		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_die( esc_html__( 'You do not have permission to export.', 'meyvora-convert' ), 403 );
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cro_export' ) ) {
			wp_die( esc_html__( 'Invalid security check. Please try again.', 'meyvora-convert' ), 403 );
		}

		$campaign_id = isset( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : 0;
		if ( $campaign_id < 1 || ! class_exists( 'CRO_Campaign' ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'no_campaign', admin_url( 'admin.php?page=cro-tools' ) ) );
			exit;
		}

		$raw = CRO_Campaign::get( $campaign_id );
		if ( ! $raw || empty( $raw['name'] ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'not_found', admin_url( 'admin.php?page=cro-tools' ) ) );
			exit;
		}

		// Strip analytics and identifiers for export.
		$export_campaign = $raw;
		unset( $export_campaign['id'], $export_campaign['impressions'], $export_campaign['conversions'], $export_campaign['revenue_attributed'], $export_campaign['created_at'], $export_campaign['updated_at'], $export_campaign['settings'], $export_campaign['targeting'] );

		$export_data = array(
			'version'     => defined( 'CRO_VERSION' ) ? CRO_VERSION : '1.0.0',
			'exported_at' => current_time( 'mysql' ),
			'campaigns'   => array( $export_campaign ),
		);

		$filename = 'cro-campaign-' . sanitize_file_name( $raw['name'] ) . '-' . gmdate( 'Y-m-d' ) . '.json';

		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );

		echo wp_json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/**
	 * Handle CSV export
	 */
	public function handle_csv_export() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		$page   = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( $action !== 'export' || $page !== 'cro-analytics' ) {
			return;
		}

		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'unauthorized', admin_url( 'admin.php?page=cro-analytics' ) ) );
			exit;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cro_export' ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'invalid_nonce', admin_url( 'admin.php?page=cro-analytics' ) ) );
			exit;
		}

		$default_days = 30;
		$date_from   = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : date( 'Y-m-d', strtotime( "-{$default_days} days" ) );
		$date_to     = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : date( 'Y-m-d' );
		$max_days    = (int) apply_filters( 'cro_export_max_days', 90 );
		$max_days    = $max_days >= 1 && $max_days <= 365 ? $max_days : 90;
		$ts_from     = strtotime( $date_from );
		$ts_to       = strtotime( $date_to );
		if ( $ts_from === false || $ts_to === false || $ts_to < $ts_from ) {
			$date_from = date( 'Y-m-d', strtotime( "-{$default_days} days" ) );
			$date_to   = date( 'Y-m-d' );
		} else {
			$range_days = (int) ( ( $ts_to - $ts_from ) / 86400 ) + 1;
			if ( $range_days > $max_days ) {
				$date_from = date( 'Y-m-d', strtotime( $date_to . " -{$max_days} days" ) );
			}
		}
		$campaign_id = isset( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : null;
		if ( $campaign_id === 0 ) {
			$campaign_id = null;
		}

		$export_format = isset( $_GET['format'] ) ? sanitize_text_field( wp_unslash( $_GET['format'] ) ) : 'events';
		if ( ! in_array( $export_format, array( 'events', 'daily' ), true ) ) {
			$export_format = 'events';
		}

		$analytics = new CRO_Analytics();

		if ( $export_format === 'daily' ) {
			$rows = $analytics->get_daily_summary_for_export( $date_from, $date_to );
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="cro-daily-summary-' . sanitize_file_name( $date_from ) . '-to-' . sanitize_file_name( $date_to ) . '.csv"' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
			$output = fopen( 'php://output', 'w' );
			fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
			fputcsv( $output, array( 'day', 'impressions', 'conversions', 'offer_applies', 'campaign_clicks', 'ab_exposures' ) );
			foreach ( $rows as $row ) {
				fputcsv( $output, array(
					$row['day'],
					(int) $row['impressions'],
					(int) $row['conversions'],
					(int) $row['offer_applies'],
					(int) $row['campaign_clicks'],
					(int) $row['ab_exposures'],
				) );
			}
			fclose( $output );
			exit;
		}

		$data = $analytics->export_events_for_csv( $date_from, $date_to, $campaign_id );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="cro-events-' . sanitize_file_name( $date_from ) . '-to-' . sanitize_file_name( $date_to ) . '.csv"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		fputcsv( $output, array( 'date_time', 'event_type', 'object_type', 'object_id', 'variant', 'page_type', 'page_url', 'user_id', 'session_key', 'meta_json' ) );

		foreach ( $data as $row ) {
			$created_at = isset( $row['created_at'] ) ? $row['created_at'] : '';
			$date_utc   = $created_at && function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i:s', strtotime( $created_at ), new \DateTimeZone( 'UTC' ) ) : $created_at;
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

			fputcsv( $output, array(
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
			) );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Handle import request (Tools → Import: validate JSON, insert as new campaigns via CRO_Campaign::create).
	 */
	/**
	 * Handle "Verify Install Package" from Tools page. Runs checks and redirects back with results in transient.
	 */
	public function handle_verify_package() {
		if ( ! isset( $_POST['cro_verify_package'] ) || (int) $_POST['cro_verify_package'] !== 1 ) {
			return;
		}
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_safe_redirect( add_query_arg( 'cro_verify', '0', admin_url( 'admin.php?page=cro-tools' ) ) );
			exit;
		}
		$nonce = isset( $_POST['cro_verify_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['cro_verify_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cro_verify_package' ) ) {
			wp_safe_redirect( add_query_arg( 'cro_verify', '0', admin_url( 'admin.php?page=cro-tools' ) ) );
			exit;
		}
		$results = class_exists( 'CRO_System_Status' ) ? CRO_System_Status::run_verify_package() : array();
		set_transient( 'cro_verify_results', $results, 60 );
		wp_safe_redirect( add_query_arg( 'cro_verify', '1', admin_url( 'admin.php?page=cro-tools' ) ) );
		exit;
	}

	/**
	 * Handle "Verify Installation" from System Status page. Runs checks (tables, blocks build, Woo, blocks) and redirects back with results.
	 */
	public function handle_verify_installation() {
		if ( ! isset( $_POST['cro_verify_installation'] ) || (int) $_POST['cro_verify_installation'] !== 1 ) {
			return;
		}
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_safe_redirect( add_query_arg( 'cro_verify_installation', '0', admin_url( 'admin.php?page=cro-system-status' ) ) );
			exit;
		}
		$nonce = isset( $_POST['cro_verify_installation_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['cro_verify_installation_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cro_verify_installation' ) ) {
			wp_safe_redirect( add_query_arg( 'cro_verify_installation', '0', admin_url( 'admin.php?page=cro-system-status' ) ) );
			exit;
		}
		$results = class_exists( 'CRO_System_Status' ) ? CRO_System_Status::run_verify_installation() : array();
		set_transient( 'cro_verify_installation_results', $results, 60 );
		wp_safe_redirect( add_query_arg( 'cro_verify_installation', '1', admin_url( 'admin.php?page=cro-system-status' ) ) );
		exit;
	}

	/**
	 * Save CRO Admin Debug toggle (Tools page). Only for users with manage_meyvora_convert.
	 */
	public function handle_save_admin_debug() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			return;
		}
		if ( ! isset( $_POST['cro_admin_debug_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cro_admin_debug_nonce'] ) ), 'cro_admin_debug' ) ) {
			return;
		}
		$page = isset( $_POST['page'] ) ? sanitize_text_field( wp_unslash( $_POST['page'] ) ) : '';
		if ( $page !== 'cro-tools' ) {
			return;
		}
		$enabled = isset( $_POST['cro_admin_debug'] ) && (int) $_POST['cro_admin_debug'] === 1;
		update_option( 'cro_admin_debug', $enabled );
		wp_safe_redirect( add_query_arg( 'cro_admin_debug_saved', $enabled ? '1' : '0', admin_url( 'admin.php?page=cro-tools' ) ) );
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
		if ( ! get_option( 'cro_admin_debug', false ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$hook   = $screen ? $screen->id : '';
		$page   = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$is_cro = ( $page !== '' && ( strpos( $page, 'cro-' ) !== false || strpos( $page, 'cro_' ) !== false ) )
			|| ( strpos( $hook, 'cro-' ) !== false || strpos( $hook, 'cro_' ) !== false );
		if ( ! $is_cro ) {
			return;
		}

		$styles = array();
		if ( isset( $GLOBALS['wp_styles'] ) && $GLOBALS['wp_styles'] instanceof \WP_Styles ) {
			$done = array_merge( $GLOBALS['wp_styles']->done, $GLOBALS['wp_styles']->queue );
			foreach ( $done as $handle ) {
				if ( strpos( $handle, 'cro' ) !== false && isset( $GLOBALS['wp_styles']->registered[ $handle ] ) ) {
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
				if ( strpos( $handle, 'cro' ) !== false && isset( $GLOBALS['wp_scripts']->registered[ $handle ] ) ) {
					$obj   = $GLOBALS['wp_scripts']->registered[ $handle ];
					$src   = isset( $obj->src ) ? $obj->src : '';
					$scripts[] = $handle . ( $src ? ' → ' . preg_replace( '#^https?://[^/]+/#', '/', $src ) : '' );
				}
			}
		}

		$is_builder = ( strpos( $page, 'cro-campaign-edit' ) !== false || strpos( $page, 'cro-campaign-builder' ) !== false );
		?>
		<div id="cro-admin-debug-panel" class="cro-admin-debug-panel" style="position:fixed;bottom:0;right:0;max-width:360px;max-height:50vh;overflow:auto;background:#1d1d1d;color:#e0e0e0;font-size:11px;padding:10px;z-index:999999;border-radius:8px 0 0 0;box-shadow:0 -2px 10px rgba(0,0,0,.3);">
			<details open>
				<summary style="cursor:pointer;font-weight:600;margin-bottom:6px;">CRO Admin Debug</summary>
				<p style="margin:4px 0;"><strong>CSS (CRO):</strong></p>
				<ul style="margin:0 0 8px 0;padding-left:16px;list-style:disc;">
					<?php foreach ( $styles as $s ) : ?>
						<li><?php echo esc_html( $s ); ?></li>
					<?php endforeach; ?>
					<?php if ( empty( $styles ) ) : ?>
						<li><?php esc_html_e( 'None', 'meyvora-convert' ); ?></li>
					<?php endif; ?>
				</ul>
				<p style="margin:4px 0;"><strong>JS (CRO):</strong></p>
				<ul style="margin:0 0 8px 0;padding-left:16px;list-style:disc;">
					<?php foreach ( $scripts as $s ) : ?>
						<li><?php echo esc_html( $s ); ?></li>
					<?php endforeach; ?>
					<?php if ( empty( $scripts ) ) : ?>
						<li><?php esc_html_e( 'None', 'meyvora-convert' ); ?></li>
					<?php endif; ?>
				</ul>
				<?php if ( $is_builder ) : ?>
					<p style="margin:4px 0;"><strong>Builder init:</strong> <span id="cro-admin-debug-builder-status">…</span></p>
				<?php endif; ?>
			</details>
		</div>
		<?php if ( $is_builder ) : ?>
		<script>
		(function(){
			function setStatus(){
				var el = document.getElementById('cro-admin-debug-builder-status');
				if (!el) return;
				if (typeof window.croBuilderInitStatus !== 'undefined') {
					var s = window.croBuilderInitStatus;
					el.textContent = (s.status === 'OK') ? 'OK' : ('FAIL: ' + (s.reason || 'unknown'));
				} else {
					el.textContent = 'FAIL: not set (script error or not run)';
				}
			}
			if (document.readyState === 'complete') setStatus(); else window.addEventListener('load', setStatus);
			setTimeout(setStatus, 1500);
		})();
		</script>
		<?php endif; ?>
		<?php
	}

	public function handle_import() {
		if ( ! isset( $_POST['cro_import'] ) || ! wp_verify_nonce( $_POST['cro_import_nonce'] ?? '', 'cro_import' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'meyvora-convert' ) );
		}

		$json_string = '';
		if ( ! empty( $_POST['import_json'] ) && is_string( $_POST['import_json'] ) ) {
			$json_string = wp_unslash( $_POST['import_json'] );
		} elseif ( isset( $_FILES['import_file'] ) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK && is_readable( $_FILES['import_file']['tmp_name'] ) ) {
			$json_string = file_get_contents( $_FILES['import_file']['tmp_name'] );
		}

		if ( $json_string === '' ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Please upload a file or paste JSON.', 'meyvora-convert' ) . '</p></div>';
			} );
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

		if ( ! class_exists( 'CRO_Campaign' ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Campaign module unavailable.', 'meyvora-convert' ) . '</p></div>';
			} );
			return;
		}

		$imported = 0;
		foreach ( $import_data['campaigns'] as $campaign ) {
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
			$id = CRO_Campaign::create( $data );
			if ( $id ) {
				$imported++;
			}
		}

		$imported_count = $imported;
		add_action( 'admin_notices', function() use ( $imported_count ) {
			echo '<div class="notice notice-success"><p>' . esc_html( sprintf(
				/* translators: %d: number of campaigns imported */
				__( 'Successfully imported %d campaign(s).', 'meyvora-convert' ),
				$imported_count
			) ) . '</p></div>';
		} );
	}

	/**
	 * Self-heal missing DB tables on admin load (at most once per 12 hours). Admins only.
	 */
	public function run_selfheal_tables() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			return;
		}
		if ( class_exists( 'CRO_Database' ) ) {
			CRO_Database::maybe_selfheal_tables();
		}
	}

	/**
	 * Handle repair database tables (System Status → Repair Database Tables).
	 */
	public function handle_repair_tables() {
		if ( ! isset( $_POST['cro_repair_tables'] ) || (int) $_POST['cro_repair_tables'] !== 1 ) {
			return;
		}

		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_safe_redirect( add_query_arg( 'cro_repair', '0', admin_url( 'admin.php?page=cro-system-status' ) ) );
			exit;
		}

		$nonce = isset( $_POST['cro_repair_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['cro_repair_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cro_repair_tables' ) ) {
			wp_safe_redirect( add_query_arg( 'cro_repair', '0', admin_url( 'admin.php?page=cro-system-status' ) ) );
			exit;
		}

		global $wpdb;
		$ok = class_exists( 'CRO_Database' ) && CRO_Database::create_tables();
		$last_error = $wpdb->last_error;

		$url = admin_url( 'admin.php?page=cro-system-status' );
		if ( $ok ) {
			$url = add_query_arg( 'cro_repair', '1', $url );
		} else {
			$url = add_query_arg( 'cro_repair', '0', $url );
		}
		if ( $last_error !== '' ) {
			$url = add_query_arg( 'cro_repair_error', rawurlencode( $last_error ), $url );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Handle bulk activate / pause / delete for campaigns.
	 */
	public function handle_bulk_campaigns() {
		if ( empty( $_POST['cro_bulk_action'] ) || empty( $_POST['campaign_ids'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cro_bulk_nonce'] ?? '' ) ), 'cro_bulk_campaigns' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			return;
		}

		$action = sanitize_key( $_POST['cro_bulk_action'] );
		$ids    = array_map( 'absint', (array) $_POST['campaign_ids'] );
		$ids    = array_filter( $ids );
		if ( empty( $ids ) ) {
			return;
		}

		global $wpdb;
		$table       = $wpdb->prefix . 'cro_campaigns';
		$placeholder = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		if ( 'activate' === $action ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='active' WHERE id IN ({$placeholder})", ...$ids ) );
		} elseif ( 'pause' === $action ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='paused' WHERE id IN ({$placeholder})", ...$ids ) );
		} elseif ( 'delete' === $action ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholder})", ...$ids ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=cro-campaigns&cro_bulk_done=1' ) );
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
				wp_safe_redirect( add_query_arg( 'error', 'unauthorized', admin_url( 'admin.php?page=cro-campaigns' ) ) );
				exit;
			}

			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'cro_duplicate_campaign' ) ) {
				wp_safe_redirect( add_query_arg( 'error', 'invalid_nonce', admin_url( 'admin.php?page=cro-campaigns' ) ) );
				exit;
			}

			$new_id = CRO_Campaign::duplicate_campaign( $id );

			if ( is_wp_error( $new_id ) ) {
				wp_safe_redirect( add_query_arg( 'error', 'duplicate_failed', admin_url( 'admin.php?page=cro-campaigns' ) ) );
			} else {
				wp_safe_redirect( admin_url( 'admin.php?page=cro-campaign-edit&campaign_id=' . $new_id . '&duplicated=1' ) );
			}
			exit;
		}

		if ( 'delete' === $action ) {
			if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
				wp_safe_redirect( add_query_arg( 'error', 'unauthorized', admin_url( 'admin.php?page=cro-campaigns' ) ) );
				exit;
			}

			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'cro_delete_campaign_' . $id ) ) {
				wp_safe_redirect( add_query_arg( 'error', 'invalid_nonce', admin_url( 'admin.php?page=cro-campaigns' ) ) );
				exit;
			}

			CRO_Campaign::delete( $id );
			wp_safe_redirect( admin_url( 'admin.php?page=cro-campaigns&deleted=1' ) );
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

		$ab_model = new CRO_AB_Test();
		$redirect_error = admin_url( 'admin.php?page=cro-ab-tests' );
		$redirect_error = add_query_arg( 'error', 'invalid_nonce', $redirect_error );

		switch ( $action ) {
			case 'start':
				if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
					wp_safe_redirect( add_query_arg( 'error', 'unauthorized', admin_url( 'admin.php?page=cro-ab-tests' ) ) );
					exit;
				}
				$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
				if ( ! wp_verify_nonce( $nonce, 'start_ab_test' ) ) {
					wp_safe_redirect( $redirect_error );
					exit;
				}
				$ab_model->start( $test_id );
				wp_safe_redirect( admin_url( 'admin.php?page=cro-ab-tests&message=started' ) );
				exit;

			case 'pause':
				if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
					wp_safe_redirect( add_query_arg( 'error', 'unauthorized', admin_url( 'admin.php?page=cro-ab-tests' ) ) );
					exit;
				}
				$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
				if ( ! wp_verify_nonce( $nonce, 'pause_ab_test' ) ) {
					wp_safe_redirect( $redirect_error );
					exit;
				}
				$ab_model->pause( $test_id );
				wp_safe_redirect( admin_url( 'admin.php?page=cro-ab-tests&message=paused' ) );
				exit;

			case 'complete':
				if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
					wp_safe_redirect( add_query_arg( 'error', 'unauthorized', admin_url( 'admin.php?page=cro-ab-tests' ) ) );
					exit;
				}
				$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
				if ( ! wp_verify_nonce( $nonce, 'complete_ab_test' ) ) {
					wp_safe_redirect( $redirect_error );
					exit;
				}
				$test      = $ab_model->get( $test_id );
				$stats     = class_exists( 'CRO_AB_Statistics' ) ? CRO_AB_Statistics::calculate( $test ) : array( 'has_winner' => false, 'winner' => null );
				$winner_id = ! empty( $stats['has_winner'] ) && ! empty( $stats['winner'] ) ? $stats['winner']['variation_id'] : null;
				$ab_model->complete( $test_id, $winner_id );
				wp_safe_redirect( admin_url( 'admin.php?page=cro-ab-tests&message=completed' ) );
				exit;

			case 'delete':
				if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
					wp_safe_redirect( add_query_arg( 'error', 'unauthorized', admin_url( 'admin.php?page=cro-ab-tests' ) ) );
					exit;
				}
				$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
				if ( ! wp_verify_nonce( $nonce, 'delete_ab_test' ) ) {
					wp_safe_redirect( $redirect_error );
					exit;
				}
				$ab_model->delete( $test_id );
				wp_safe_redirect( admin_url( 'admin.php?page=cro-ab-tests&message=deleted' ) );
				exit;

			case 'apply_winner':
				if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
					wp_safe_redirect( add_query_arg( 'error', 'unauthorized', admin_url( 'admin.php?page=cro-ab-tests' ) ) );
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
				wp_safe_redirect( admin_url( 'admin.php?page=cro-ab-tests&message=winner_applied' ) );
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
		global $wpdb;

		$ab_model = new CRO_AB_Test();
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

		if ( $variation_data ) {
			$campaigns_table = $wpdb->prefix . 'cro_campaigns';
			$update_data = array();
			if ( ! empty( $variation_data['content'] ) ) {
				$update_data['content'] = is_string( $variation_data['content'] ) ? $variation_data['content'] : wp_json_encode( $variation_data['content'] );
			}
			if ( ! empty( $variation_data['styling'] ) ) {
				$update_data['styling'] = is_string( $variation_data['styling'] ) ? $variation_data['styling'] : wp_json_encode( $variation_data['styling'] );
			}
			if ( ! empty( $update_data ) ) {
				$wpdb->update(
					$campaigns_table,
					$update_data,
					array( 'id' => $test->original_campaign_id ),
					null,
					array( '%d' )
				);
			}
		}

		$ab_model->complete( $test_id, $winner_variation_id );
	}

	/**
	 * Handle front-end error logging (graceful error handling).
	 */
	public function handle_log_error() {
		// Only when error reporting is enabled and nonce valid.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cro_log_error' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}

		$raw = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			wp_send_json_success();
		}

		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[Meyvora Convert] JS Error: ' . ( isset( $data['message'] ) ? $data['message'] : '' ) );
			if ( ! empty( $data['url'] ) ) {
				error_log( '[Meyvora Convert] URL: ' . $data['url'] );
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

		$nonce = isset( $_POST['cro_save_offer_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['cro_save_offer_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cro_save_offer_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce. Please refresh and try again.', 'meyvora-convert' ) ), 403 );
		}

		$max_offers = 5;
		$option_key = 'cro_dynamic_offers';

		$offers = get_option( $option_key, array() );
		if ( ! is_array( $offers ) ) {
			$offers = array();
		}
		$offers = array_pad( $offers, $max_offers, array() );

		$offer_index = isset( $_POST['cro_offer_index'] ) ? sanitize_text_field( wp_unslash( $_POST['cro_offer_index'] ) ) : '';
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
							'rule_summary'   => class_exists( 'CRO_Offer_Presenter' ) ? CRO_Offer_Presenter::summarize_conditions( $o ) : self::get_offer_rule_summary( $o ),
							'reward_summary' => class_exists( 'CRO_Offer_Presenter' ) ? CRO_Offer_Presenter::summarize_reward( $o ) : self::get_offer_reward_summary( $o ),
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

		$raw = array(
			'headline'                      => isset( $_POST['cro_drawer_headline'] ) ? sanitize_text_field( wp_unslash( $_POST['cro_drawer_headline'] ) ) : '',
			'priority'                      => isset( $_POST['cro_drawer_priority'] ) ? (int) $_POST['cro_drawer_priority'] : 10,
			'enabled'                       => ! empty( $_POST['cro_drawer_enabled'] ),
			'description'                   => isset( $_POST['cro_drawer_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cro_drawer_description'] ) ) : '',
			'min_cart_total'                => isset( $_POST['cro_drawer_min_cart_total'] ) && is_numeric( $_POST['cro_drawer_min_cart_total'] ) ? (float) $_POST['cro_drawer_min_cart_total'] : 0,
			'max_cart_total'                => isset( $_POST['cro_drawer_max_cart_total'] ) && is_numeric( $_POST['cro_drawer_max_cart_total'] ) ? (float) $_POST['cro_drawer_max_cart_total'] : 0,
			'min_items'                     => isset( $_POST['cro_drawer_min_items'] ) && is_numeric( $_POST['cro_drawer_min_items'] ) ? (int) $_POST['cro_drawer_min_items'] : 0,
			'first_time_customer'           => ! empty( $_POST['cro_drawer_first_time_customer'] ),
			'returning_customer_min_orders' => isset( $_POST['cro_drawer_returning_customer_min_orders'] ) && is_numeric( $_POST['cro_drawer_returning_customer_min_orders'] ) ? (int) $_POST['cro_drawer_returning_customer_min_orders'] : 0,
			'lifetime_spend_min'            => isset( $_POST['cro_drawer_lifetime_spend_min'] ) && is_numeric( $_POST['cro_drawer_lifetime_spend_min'] ) ? (float) $_POST['cro_drawer_lifetime_spend_min'] : 0,
			'allowed_roles'                 => isset( $_POST['cro_drawer_allowed_roles'] ) && is_array( $_POST['cro_drawer_allowed_roles'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['cro_drawer_allowed_roles'] ) ) : array(),
			'excluded_roles'                => isset( $_POST['cro_drawer_excluded_roles'] ) && is_array( $_POST['cro_drawer_excluded_roles'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['cro_drawer_excluded_roles'] ) ) : array(),
			'exclude_sale_items'             => ! empty( $_POST['cro_drawer_exclude_sale_items'] ),
			'include_categories'             => isset( $_POST['cro_drawer_include_categories'] ) && is_array( $_POST['cro_drawer_include_categories'] ) ? array_map( 'absint', wp_unslash( $_POST['cro_drawer_include_categories'] ) ) : array(),
			'exclude_categories'             => isset( $_POST['cro_drawer_exclude_categories'] ) && is_array( $_POST['cro_drawer_exclude_categories'] ) ? array_map( 'absint', wp_unslash( $_POST['cro_drawer_exclude_categories'] ) ) : array(),
			'include_products'               => isset( $_POST['cro_drawer_include_products'] ) ? array_filter( array_map( 'absint', array_map( 'trim', explode( ',', (string) wp_unslash( $_POST['cro_drawer_include_products'] ) ) ) ) ) : array(),
			'exclude_products'               => isset( $_POST['cro_drawer_exclude_products'] ) ? array_filter( array_map( 'absint', array_map( 'trim', explode( ',', (string) wp_unslash( $_POST['cro_drawer_exclude_products'] ) ) ) ) ) : array(),
			'cart_contains_category'         => isset( $_POST['cro_drawer_cart_contains_category'] ) && is_array( $_POST['cro_drawer_cart_contains_category'] ) ? array_map( 'absint', wp_unslash( $_POST['cro_drawer_cart_contains_category'] ) ) : array(),
			'min_qty_for_category'           => isset( $_POST['cro_drawer_min_qty_for_category'] ) ? self::parse_min_qty_for_category( wp_unslash( $_POST['cro_drawer_min_qty_for_category'] ) ) : array(),
			'apply_to_categories'            => isset( $_POST['cro_drawer_apply_to_categories'] ) && is_array( $_POST['cro_drawer_apply_to_categories'] ) ? array_map( 'absint', wp_unslash( $_POST['cro_drawer_apply_to_categories'] ) ) : array(),
			'apply_to_products'              => isset( $_POST['cro_drawer_apply_to_products'] ) && is_array( $_POST['cro_drawer_apply_to_products'] ) ? array_map( 'absint', wp_unslash( $_POST['cro_drawer_apply_to_products'] ) ) : ( isset( $_POST['cro_drawer_apply_to_products'] ) ? array_filter( array_map( 'absint', array_map( 'trim', explode( ',', (string) wp_unslash( $_POST['cro_drawer_apply_to_products'] ) ) ) ) ) : array() ),
			'per_category_discount'         => self::parse_per_category_discount_post( isset( $_POST['cro_drawer_per_category_discount_cat'] ) ? $_POST['cro_drawer_per_category_discount_cat'] : array(), isset( $_POST['cro_drawer_per_category_discount_amount'] ) ? $_POST['cro_drawer_per_category_discount_amount'] : array() ),
			'reward_type'                    => isset( $_POST['cro_drawer_reward_type'] ) ? sanitize_text_field( wp_unslash( $_POST['cro_drawer_reward_type'] ) ) : 'percent',
			'reward_amount'                  => isset( $_POST['cro_drawer_reward_amount'] ) ? (float) $_POST['cro_drawer_reward_amount'] : 10,
			'coupon_ttl_hours'              => isset( $_POST['cro_drawer_coupon_ttl_hours'] ) ? absint( $_POST['cro_drawer_coupon_ttl_hours'] ) : 48,
			'individual_use'                => ! empty( $_POST['cro_drawer_individual_use'] ),
			'rate_limit_hours'              => isset( $_POST['cro_drawer_rate_limit_hours'] ) && is_numeric( $_POST['cro_drawer_rate_limit_hours'] ) ? absint( $_POST['cro_drawer_rate_limit_hours'] ) : 6,
			'max_coupons_per_visitor'       => isset( $_POST['cro_drawer_max_coupons_per_visitor'] ) && is_numeric( $_POST['cro_drawer_max_coupons_per_visitor'] ) ? absint( $_POST['cro_drawer_max_coupons_per_visitor'] ) : 1,
		);
		$allowed_raw = isset( $_POST['cro_drawer_allowed_roles'] ) && is_array( $_POST['cro_drawer_allowed_roles'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['cro_drawer_allowed_roles'] ) ) : array();
		$raw['allowed_roles'] = array_values( array_filter( $allowed_raw, function ( $v ) { return $v !== ''; } ) );
		$excluded_raw = isset( $_POST['cro_drawer_excluded_roles'] ) && is_array( $_POST['cro_drawer_excluded_roles'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['cro_drawer_excluded_roles'] ) ) : array();
		$raw['excluded_roles'] = array_values( array_filter( $excluded_raw, function ( $v ) { return $v !== ''; } ) );

		if ( class_exists( 'CRO_Offer_Schema' ) ) {
			$offer = CRO_Offer_Schema::sanitize_offer( $raw );
			$valid = CRO_Offer_Schema::validate_offer( $offer );
			if ( is_wp_error( $valid ) ) {
				$errors = CRO_Offer_Schema::errors_to_array( $valid );
				$offers_used_count = 0;
				$result_offers = array();
				foreach ( $offers as $i => $o ) {
					$o = is_array( $o ) ? $o : array();
					if ( ! empty( trim( (string) ( $o['headline'] ?? '' ) ) ) ) {
						$offers_used_count++;
						$result_offers[] = array(
							'index'          => $i,
							'offer'          => $o,
							'rule_summary'   => class_exists( 'CRO_Offer_Presenter' ) ? CRO_Offer_Presenter::summarize_conditions( $o ) : self::get_offer_rule_summary( $o ),
							'reward_summary' => class_exists( 'CRO_Offer_Presenter' ) ? CRO_Offer_Presenter::summarize_reward( $o ) : self::get_offer_reward_summary( $o ),
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
			);
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
					'rule_summary'   => class_exists( 'CRO_Offer_Presenter' ) ? CRO_Offer_Presenter::summarize_conditions( $o ) : self::get_offer_rule_summary( $o ),
					'reward_summary' => class_exists( 'CRO_Offer_Presenter' ) ? CRO_Offer_Presenter::summarize_reward( $o ) : self::get_offer_reward_summary( $o ),
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
	 * AJAX: Abandoned cart email preview. Returns subject + body with sample placeholders.
	 * Template content is sanitized with wp_kses_post; placeholders are replaced with safe sample values.
	 */
	public function ajax_abandoned_cart_preview() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'meyvora-convert' ) ), 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cro_abandoned_cart_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'meyvora-convert' ) ), 403 );
		}
		$subject_tpl = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$body_tpl    = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';
		if ( trim( (string) $body_tpl ) === '' && function_exists( 'cro_settings' ) ) {
			$body_tpl = cro_settings()->get_abandoned_cart_email_body_default();
		}
		if ( trim( (string) $subject_tpl ) === '' && function_exists( 'cro_settings' ) ) {
			$opts      = cro_settings()->get_abandoned_cart_settings();
			$subject_tpl = isset( $opts['email_subject_template'] ) ? sanitize_text_field( $opts['email_subject_template'] ) : __( 'You left something in your cart – {store_name}', 'meyvora-convert' );
		}
		$values  = class_exists( 'CRO_Abandoned_Cart_Reminder' ) ? CRO_Abandoned_Cart_Reminder::get_placeholder_values( null, 'SAMPLE10', true ) : array();
		$subject = class_exists( 'CRO_Abandoned_Cart_Reminder' ) ? CRO_Abandoned_Cart_Reminder::replace_placeholders( $subject_tpl, $values ) : $subject_tpl;
		$body    = class_exists( 'CRO_Abandoned_Cart_Reminder' ) ? CRO_Abandoned_Cart_Reminder::replace_placeholders( $body_tpl, $values ) : $body_tpl;
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
		if ( ! wp_verify_nonce( $nonce, 'cro_abandoned_cart_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'meyvora-convert' ) ), 403 );
		}
		$to = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
		if ( ! is_email( $to ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'meyvora-convert' ) ), 400 );
		}
		$subject_tpl = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$body_tpl    = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';
		if ( trim( (string) $body_tpl ) === '' && function_exists( 'cro_settings' ) ) {
			$body_tpl = cro_settings()->get_abandoned_cart_email_body_default();
		}
		if ( trim( (string) $subject_tpl ) === '' && function_exists( 'cro_settings' ) ) {
			$opts       = cro_settings()->get_abandoned_cart_settings();
			$subject_tpl = isset( $opts['email_subject_template'] ) ? sanitize_text_field( $opts['email_subject_template'] ) : __( 'You left something in your cart – {store_name}', 'meyvora-convert' );
		}
		$values  = class_exists( 'CRO_Abandoned_Cart_Reminder' ) ? CRO_Abandoned_Cart_Reminder::get_placeholder_values( null, 'SAMPLE10', true ) : array();
		$subject = class_exists( 'CRO_Abandoned_Cart_Reminder' ) ? CRO_Abandoned_Cart_Reminder::replace_placeholders( $subject_tpl, $values ) : $subject_tpl;
		$body    = class_exists( 'CRO_Abandoned_Cart_Reminder' ) ? CRO_Abandoned_Cart_Reminder::replace_placeholders( $body_tpl, $values ) : $body_tpl;
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
		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
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
	 * admin_post: Cancel scheduled reminders for an abandoned cart.
	 */
	public function handle_abandoned_cart_cancel_reminders() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'meyvora-convert' ), 403 );
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'cro_abandoned_carts_list' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'meyvora-convert' ), 403 );
		}
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( $id > 0 && class_exists( 'CRO_Abandoned_Cart_Tracker' ) ) {
			CRO_Abandoned_Cart_Tracker::cancel_scheduled_reminders( $id );
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
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'cro_abandoned_carts_list' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'meyvora-convert' ), 403 );
		}
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( $id > 0 && class_exists( 'CRO_Abandoned_Cart_Tracker' ) ) {
			CRO_Abandoned_Cart_Tracker::mark_recovered_by_id( $id );
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
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'cro_abandoned_carts_list' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'meyvora-convert' ), 403 );
		}
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$sent = false;
		if ( $id > 0 && class_exists( 'CRO_Abandoned_Cart_Reminder' ) ) {
			$sent = CRO_Abandoned_Cart_Reminder::send_reminder_immediately( $id, 1 );
		}
		$this->redirect_abandoned_carts_list( $sent ? 'resend_ok' : 'resend_fail' );
	}

	/**
	 * Redirect back to abandoned carts list with optional message.
	 *
	 * @param string|null $message Key for notice (cancel_reminders, mark_recovered, resend_ok, resend_fail).
	 */
	private function redirect_abandoned_carts_list( $message = null ) {
		$base = admin_url( 'admin.php?page=cro-abandoned-carts' );
		$args = array();
		if ( isset( $_GET['status_filter'] ) && is_string( $_GET['status_filter'] ) ) {
			$args['status_filter'] = sanitize_text_field( wp_unslash( $_GET['status_filter'] ) );
		}
		if ( isset( $_GET['search'] ) && is_string( $_GET['search'] ) ) {
			$args['search'] = rawurlencode( sanitize_text_field( wp_unslash( $_GET['search'] ) ) );
		}
		if ( isset( $_GET['paged'] ) && absint( $_GET['paged'] ) > 0 ) {
			$args['paged'] = absint( $_GET['paged'] );
		}
		if ( $message ) {
			$args['cro_notice'] = $message;
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
		if ( ! wp_verify_nonce( $nonce, 'cro_abandoned_carts_list' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'meyvora-convert' ) ), 403 );
		}
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( $id <= 0 || ! class_exists( 'CRO_Abandoned_Cart_Tracker' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid cart.', 'meyvora-convert' ) ), 400 );
		}
		$row = CRO_Abandoned_Cart_Tracker::get_row_by_id( $id );
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
		wp_send_json_success( array(
			'id'            => (int) $row->id,
			'email'         => $row->email,
			'cart_items'    => $items,
			'currency'      => $currency,
			'cart_total'    => $cart_total_val,
			'checkout_url'  => $checkout_url,
			'email_log'     => $email_log,
			'discount_coupon' => ! empty( $row->discount_coupon ) ? $row->discount_coupon : null,
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
	 * @param array $cat_ids  Array of category IDs (e.g. from cro_drawer_per_category_discount_cat[]).
	 * @param array $amounts  Array of amounts (e.g. from cro_drawer_per_category_discount_amount[]).
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
			$parts[] = sprintf( __( 'Cart ≥ %s', 'meyvora-convert' ), $fmt( $o['min_cart_total'] ) );
		}
		if ( ! empty( $o['max_cart_total'] ) ) {
			$parts[] = sprintf( __( 'Cart ≤ %s', 'meyvora-convert' ), $fmt( $o['max_cart_total'] ) );
		}
		if ( ! empty( $o['min_items'] ) ) {
			$parts[] = sprintf( _n( '%d item', '%d items', $o['min_items'], 'meyvora-convert' ), $o['min_items'] );
		}
		if ( ! empty( $o['first_time_customer'] ) ) {
			$parts[] = __( 'First-time customer', 'meyvora-convert' );
		}
		if ( ! empty( $o['returning_customer_min_orders'] ) ) {
			$parts[] = sprintf( __( 'Returning: %d+ orders', 'meyvora-convert' ), $o['returning_customer_min_orders'] );
		}
		if ( ! empty( $o['lifetime_spend_min'] ) ) {
			$parts[] = sprintf( __( 'Lifetime spend ≥ %s', 'meyvora-convert' ), $fmt( $o['lifetime_spend_min'] ) );
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
			return sprintf( __( '%s%% off', 'meyvora-convert' ), $amount );
		}
		if ( $type === 'fixed' ) {
			$formatted = number_format_i18n( (float) $amount, 2 );
			if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
				$formatted = get_woocommerce_currency_symbol() . $formatted;
			}
			return sprintf( __( '%s off', 'meyvora-convert' ), $formatted );
		}
		return __( 'Discount', 'meyvora-convert' );
	}
}
