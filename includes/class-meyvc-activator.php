<?php
/**
 * Fired during plugin activation
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database schema version.
 */
define( 'MEYVC_DB_VERSION', '2.0.0' );

/**
 * Fired during plugin activation.
 */
class MEYVC_Activator {

	/**
	 * Activate the plugin.
	 */
	public static function activate() {
		// Check WooCommerce dependency (defined in main plugin file).
		if ( function_exists( 'meyvc_activation_check' ) ) {
			meyvc_activation_check();
		} elseif ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( MEYVC_PLUGIN_BASENAME );
			wp_die(
				esc_html__( 'Meyvora Convert for WooCommerce requires WooCommerce to be installed and active.', 'meyvora-convert' ),
				esc_html__( 'Plugin Activation Error', 'meyvora-convert' ),
				array( 'back_link' => true )
			);
		}

		// Create all database tables via MEYVC_Database (single source of truth for schema).
		if ( class_exists( 'MEYVC_Database' ) ) {
			MEYVC_Database::create_tables();
		}

		// Set plugin version option for future migrations.
		if ( defined( 'MEYVC_VERSION' ) ) {
			update_option( 'meyvc_version', MEYVC_VERSION );
		}
		update_option( 'meyvc_db_version', MEYVC_DB_VERSION );
		delete_transient( 'meyvc_tables_ok' );

		// Set default settings (uses meyvc_settings table)
		self::set_default_settings();

		// Set default options
		self::set_default_options();

		self::ensure_capability();

		// Flag for post-activation redirect to onboarding (only when onboarding not already completed).
		if ( ! get_option( 'meyvc_onboarding_complete', false ) && ! get_option( 'meyvc_onboarding_completed', false ) ) {
			set_transient( 'meyvc_activation_redirect', true, 30 );
		}

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Ensure administrator and shop_manager have manage_meyvora_convert. Call on activation and on plugin load
	 * so existing installs (updated without reactivation) get the capability and the admin menu appears.
	 */
	public static function ensure_capability() {
		foreach ( array( 'administrator', 'shop_manager' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				$role->add_cap( 'manage_meyvora_convert' );
				$role->remove_cap( 'manage_cro_toolkit' );
			}
		}
	}

	/**
	 * Upgrade tables if schema version changed. Applies schema via MEYVC_Database::create_tables() (dbDelta).
	 */
	public static function maybe_upgrade_tables() {
		self::maybe_migrate_legacy_cro_prefix();

		$installed_db_version = get_option( 'meyvc_db_version', '0' );
		if ( '0' === $installed_db_version || 0 === $installed_db_version ) {
			$legacy_db = get_option( 'cro_db_version', '0' );
			if ( $legacy_db && '0' !== (string) $legacy_db ) {
				$installed_db_version = $legacy_db;
			}
		}

		if ( version_compare( (string) $installed_db_version, MEYVC_DB_VERSION, '<' ) ) {
			if ( class_exists( 'MEYVC_Database' ) ) {
				MEYVC_Database::create_tables();
			}
			update_option( 'meyvc_db_version', MEYVC_DB_VERSION );
		}

		if ( defined( 'MEYVC_VERSION' ) ) {
			update_option( 'meyvc_version', MEYVC_VERSION );
		}
	}

	/**
	 * One-time migration from legacy cro_* DB tables, options, transients, and cron hooks (WordPress.org prefix rules).
	 *
	 * @return void
	 */
	private static function maybe_migrate_legacy_cro_prefix() {
		if ( get_option( 'meyvc_legacy_cro_migrated' ) === 'yes' ) {
			return;
		}

		global $wpdb;

		$suffix_map = array(
			'cro_campaigns'            => 'meyvc_campaigns',
			'cro_events'               => 'meyvc_events',
			'cro_emails'               => 'meyvc_emails',
			'cro_settings'             => 'meyvc_settings',
			'cro_ab_tests'             => 'meyvc_ab_tests',
			'cro_ab_variations'        => 'meyvc_ab_variations',
			'cro_ab_assignments'       => 'meyvc_ab_assignments',
			'cro_daily_stats'          => 'meyvc_daily_stats',
			'cro_offers'               => 'meyvc_offers',
			'cro_offer_logs'           => 'meyvc_offer_logs',
			'cro_abandoned_carts'      => 'meyvc_abandoned_carts',
			'cro_campaign_sequences'   => 'meyvc_campaign_sequences',
			'cro_sequence_enrollments' => 'meyvc_sequence_enrollments',
		);

		foreach ( $suffix_map as $old_suffix => $new_suffix ) {
			$old_t = $wpdb->prefix . $old_suffix;
			$new_t = $wpdb->prefix . $new_suffix;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration: SHOW TABLES during upgrade.
			$old_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_t ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration: SHOW TABLES during upgrade.
			$new_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_t ) );
			if ( $old_exists === $old_t && $new_exists !== $new_t ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration: RENAME TABLE with escaped names from suffix map.
				$wpdb->query( 'RENAME TABLE `' . esc_sql( $old_t ) . '` TO `' . esc_sql( $new_t ) . '`' );
			}
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration: bulk option_name updates.
		$wpdb->query( "UPDATE {$wpdb->options} SET option_name = REPLACE(option_name, 'cro_', 'meyvc_') WHERE option_name LIKE 'cro\\_%'" );
		$wpdb->query( "UPDATE {$wpdb->options} SET option_name = REPLACE(option_name, '_transient_cro_', '_transient_meyvc_') WHERE option_name LIKE '\\_transient\\_cro\\_%'" );
		$wpdb->query( "UPDATE {$wpdb->options} SET option_name = REPLACE(option_name, '_transient_timeout_cro_', '_transient_timeout_meyvc_') WHERE option_name LIKE '\\_transient\\_timeout\\_cro\\_%'" );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$legacy_cron = array(
			'cro_process_background_queue',
			'cro_cleanup_old_events',
			'cro_aggregate_daily_stats',
			'cro_check_ab_winners',
			'cro_daily_cleanup',
			'cro_send_abandoned_cart_reminders',
			'cro_deliver_webhook',
		);
		foreach ( $legacy_cron as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}

		if ( ! get_option( 'meyvc_db_version' ) && get_option( 'cro_db_version' ) ) {
			update_option( 'meyvc_db_version', get_option( 'cro_db_version' ), false );
		}

		update_option( 'meyvc_legacy_cro_migrated', 'yes', true );
		wp_cache_flush();
	}

	/**
	 * Set default settings in meyvc_settings table on activation.
	 * Only sets values when the key does not already exist, so user changes persist on re-activation.
	 */
	public static function set_default_settings() {
		require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-settings.php';
		$settings = MEYVC_Settings::get_instance();

		$defaults = array(
			'general' => array(
				'plugin_enabled'             => true,
				'campaigns_enabled'          => true,
				'sticky_cart_enabled'        => false,
				'shipping_bar_enabled'       => false,
				'trust_badges_enabled'       => false,
				'cart_optimizer_enabled'     => false,
				'checkout_optimizer_enabled' => false,
				'exclude_admins'             => false,
				'debug_mode'                 => false,
			),
			'styles'  => array(
				'primary_color'    => '#333333',
				'secondary_color'   => '#555555',
				'button_radius'     => 8,
				'font_size_scale'   => 1,
				'font_family'       => 'inherit',
				'border_radius'     => 8,
				'spacing'           => 8,
				'animation_speed'   => 'normal',
			),
			'analytics' => array(
				'track_revenue'        => true,
				'track_coupons'        => true,
				'data_retention_days'   => 90,
				'anonymise_ip'          => true,
			),
		);

		foreach ( $defaults as $group => $keys ) {
			foreach ( $keys as $key => $value ) {
				// Only set if not already present (preserve user settings on re-activation).
				if ( $settings->get( $group, $key, null ) === null ) {
					$settings->set( $group, $key, $value );
				}
			}
		}
	}

	/**
	 * Set default options.
	 */
	private static function set_default_options() {
		$defaults = array(
			'meyvc_version'            => MEYVC_VERSION,
			'meyvc_enable_analytics'   => true,
			'meyvc_enable_sticky_cart' => true,
			'meyvc_enable_shipping_bar' => true,
			'meyvc_enable_trust_badges' => true,
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}

	/**
	 * Get default trigger_settings JSON structure.
	 *
	 * @return array
	 */
	public static function get_default_trigger_settings() {
		return array(
			'type'                    => 'exit_intent',
			'sensitivity'             => 'medium',
			'delay_seconds'           => 3,
			'scroll_depth_percent'    => 50,
			'time_delay_seconds'      => 30,
			'idle_seconds'            => 30,
			'require_interaction'     => true,
			'disable_on_fast_scroll'  => true,
		);
	}

	/**
	 * Get default content JSON structure.
	 *
	 * @return array
	 */
	public static function get_default_content() {
		$tone = class_exists( 'MEYVC_Default_Copy' ) ? MEYVC_Default_Copy::TONE_NEUTRAL : 'neutral';
		$exit = class_exists( 'MEYVC_Default_Copy' ) ? MEYVC_Default_Copy::get_map( 'exit_intent' ) : array();
		$neutral = isset( $exit[ $tone ] ) ? $exit[ $tone ] : array();
		return array(
			'tone'                => $tone,
			'headline'            => isset( $neutral['headline'] ) ? $neutral['headline'] : __( 'Before you go', 'meyvora-convert' ),
			'subheadline'         => isset( $neutral['subheadline'] ) ? $neutral['subheadline'] : __( 'Here\'s a small thank-you for visiting', 'meyvora-convert' ),
			'body'                => '',
			'image_url'           => '',
			'cta_text'            => isset( $neutral['cta_text'] ) ? $neutral['cta_text'] : __( 'Claim offer', 'meyvora-convert' ),
			'cta_url'             => '',
			'show_email_field'    => true,
			'email_placeholder'   => __( 'Enter your email', 'meyvora-convert' ),
			'show_coupon'         => true,
			'coupon_code'         => 'SAVE10',
			'coupon_label'        => __( 'Use code at checkout', 'meyvora-convert' ),
			'show_dismiss_link'   => true,
			'dismiss_text'        => isset( $neutral['dismiss_text'] ) ? $neutral['dismiss_text'] : __( 'No thanks', 'meyvora-convert' ),
			'show_countdown'      => false,
			'countdown_minutes'   => 15,
			'success_message'     => __( 'Check your email for your code.', 'meyvora-convert' ),
		);
	}

	/**
	 * Get default styling JSON structure.
	 *
	 * @return array
	 */
	public static function get_default_styling() {
		return array(
			'bg_color'         => '#ffffff',
			'text_color'       => '#333333',
			'headline_color'   => '#000000',
			'button_bg_color'  => '#333333',
			'button_text_color'=> '#ffffff',
			'overlay_color'    => '#000000',
			'overlay_opacity'  => 50,
			'border_radius'     => 8,
			'font_family'      => 'inherit',
		);
	}

	/**
	 * Get default targeting_rules JSON structure.
	 *
	 * @return array
	 */
	public static function get_default_targeting_rules() {
		return array(
			'pages'    => array(
				'type'           => 'specific',
				'include'        => array( 'cart', 'product' ),
				'excluded_pages' => array( 'checkout', 'my-account' ),
			),
			'behavior' => array(
				'min_time_on_page'   => 0,
				'min_scroll_depth'   => 0,
				'require_interaction'=> false,
				'cart_status'        => 'has_items',
				'cart_min_value'     => 0,
				'cart_max_value'     => 0,
			),
			'visitor'  => array(
				'type'           => 'all',
				'first_visit_only'=> false,
				'returning_only' => false,
			),
			'device'    => array(
				'desktop' => true,
				'mobile'  => true,
				'tablet'  => true,
			),
			'schedule' => array(
				'enabled'     => false,
				'start_date'  => '',
				'end_date'     => '',
				'days_of_week' => array( 0, 1, 2, 3, 4, 5, 6 ),
				'hours'        => array( 'start' => 0, 'end' => 24 ),
			),
		);
	}

	/**
	 * Get default display_rules JSON structure.
	 *
	 * @return array
	 */
	public static function get_default_display_rules() {
		return array(
			'frequency'                 => 'once_per_session',
			'frequency_days'            => 7,
			'max_impressions_per_visitor'=> 0,
			'priority'                  => 10,
		);
	}
}
