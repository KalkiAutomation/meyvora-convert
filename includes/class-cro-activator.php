<?php
/**
 * Fired during plugin activation
 *
 * @package Meyvora_Convert
 */
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Database schema version.
 */
define( 'CRO_DB_VERSION', '1.8.0' );

/**
 * Fired during plugin activation.
 */
class CRO_Activator {

	/**
	 * Activate the plugin.
	 */
	public static function activate() {
		// Check WooCommerce dependency (defined in main plugin file).
		if ( function_exists( 'cro_activation_check' ) ) {
			cro_activation_check();
		} elseif ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( CRO_PLUGIN_BASENAME );
			wp_die(
				esc_html__( 'Meyvora Convert for WooCommerce requires WooCommerce to be installed and active.', 'meyvora-convert' ),
				esc_html__( 'Plugin Activation Error', 'meyvora-convert' ),
				array( 'back_link' => true )
			);
		}

		// Create all database tables via CRO_Database (single source of truth for schema).
		if ( class_exists( 'CRO_Database' ) ) {
			CRO_Database::create_tables();
		}

		// Set plugin version option for future migrations.
		if ( defined( 'CRO_VERSION' ) ) {
			update_option( 'cro_version', CRO_VERSION );
		}
		update_option( 'cro_db_version', CRO_DB_VERSION );

		// Set default settings (uses cro_settings table)
		self::set_default_settings();

		// Set default options
		self::set_default_options();

		self::ensure_capability();

		// Flag for post-activation redirect to onboarding (only when onboarding not already completed).
		if ( ! get_option( 'cro_onboarding_completed', false ) ) {
			set_transient( 'cro_activation_redirect', true, 30 );
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
	 * Upgrade tables if schema/plugin version changed. Call on plugin load for migrations.
	 */
	public static function maybe_upgrade_tables() {
		global $wpdb;
		$installed_db_version = get_option( 'cro_db_version', '0' );

		if ( version_compare( $installed_db_version, CRO_DB_VERSION, '<' ) ) {
			if ( class_exists( 'CRO_Database' ) ) {
				CRO_Database::create_tables();
			}
			update_option( 'cro_db_version', CRO_DB_VERSION );
		}

		if ( defined( 'CRO_VERSION' ) ) {
			update_option( 'cro_version', CRO_VERSION );
		}

		// One-time: ensure template_type is VARCHAR so values like centered-image-left, slide-bottom can be stored.
		$table_campaigns = $wpdb->prefix . 'cro_campaigns';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_campaigns ) ) === $table_campaigns ) {
			$col = $wpdb->get_row( $wpdb->prepare(
				"SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'template_type'",
				DB_NAME,
				$table_campaigns
			) );
			if ( $col && strpos( strtoupper( $col->COLUMN_TYPE ), 'ENUM' ) === 0 ) {
				$wpdb->query( "ALTER TABLE {$table_campaigns} MODIFY COLUMN template_type VARCHAR(64) DEFAULT 'centered'" );
			}
		}

		// One-time (1.5.0): add email_consent to abandoned_carts if missing.
		$table_abandoned = $wpdb->prefix . 'cro_abandoned_carts';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_abandoned ) ) === $table_abandoned ) {
			$col = $wpdb->get_row( $wpdb->prepare(
				"SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'email_consent'",
				DB_NAME,
				$table_abandoned
			) );
			if ( ! $col ) {
				$wpdb->query( "ALTER TABLE {$table_abandoned} ADD COLUMN email_consent tinyint(1) NOT NULL DEFAULT 0 AFTER email" );
			}
		}

		// One-time (1.6.0): add last_error to abandoned_carts if missing.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_abandoned ) ) === $table_abandoned ) {
			$col = $wpdb->get_row( $wpdb->prepare(
				"SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'last_error'",
				DB_NAME,
				$table_abandoned
			) );
			if ( ! $col ) {
				$wpdb->query( "ALTER TABLE {$table_abandoned} ADD COLUMN last_error varchar(500) DEFAULT NULL AFTER email_3_sent_at" );
			}
		}

		// One-time (1.8.0): add fallback_delay_seconds to cro_campaigns if missing.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_campaigns ) ) === $table_campaigns ) {
			$col = $wpdb->get_row( $wpdb->prepare(
				"SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'fallback_delay_seconds'",
				DB_NAME,
				$table_campaigns
			) );
			if ( ! $col ) {
				$wpdb->query( "ALTER TABLE {$table_campaigns} ADD COLUMN fallback_delay_seconds int(11) NOT NULL DEFAULT 5 AFTER fallback_id" );
			}
		}

		// One-time (1.7.0): add conflict_ids to cro_offers if missing (JSON array of offer IDs).
		$table_offers = $wpdb->prefix . 'cro_offers';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_offers ) ) === $table_offers ) {
			$col = $wpdb->get_row( $wpdb->prepare(
				"SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'conflict_ids'",
				DB_NAME,
				$table_offers
			) );
			if ( ! $col ) {
				$wpdb->query( "ALTER TABLE {$table_offers} ADD COLUMN conflict_ids text DEFAULT NULL AFTER usage_rules_json" );
			}
		}

		// Tracking table indexes for attribution/analytics performance (only if not present).
		self::maybe_add_tracking_indexes();

		// Add 'offer' to cro_events.source_type enum for offer conversion attribution.
		self::maybe_add_offer_source_type();
	}

	/**
	 * Add indexes to tracking tables if missing. Safe to run multiple times.
	 */
	public static function maybe_add_tracking_indexes() {
		global $wpdb;

		$events_table = $wpdb->prefix . 'cro_events';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $events_table ) ) === $events_table ) {
			$idx = $wpdb->get_var( $wpdb->prepare(
				"SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = 'event_type_created_at'",
				DB_NAME,
				$events_table
			) );
			if ( ! $idx ) {
				$wpdb->query( "ALTER TABLE {$events_table} ADD KEY event_type_created_at (event_type, created_at)" );
			}
		}

		$offer_logs_table = $wpdb->prefix . 'cro_offer_logs';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $offer_logs_table ) ) === $offer_logs_table ) {
			$idx = $wpdb->get_var( $wpdb->prepare(
				"SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = 'offer_id_created_at'",
				DB_NAME,
				$offer_logs_table
			) );
			if ( ! $idx ) {
				$wpdb->query( "ALTER TABLE {$offer_logs_table} ADD KEY offer_id_created_at (offer_id, created_at)" );
			}
			$has_action = $wpdb->get_var( "SHOW COLUMNS FROM {$offer_logs_table} LIKE 'action'" );
			if ( $has_action ) {
				$idx = $wpdb->get_var( $wpdb->prepare(
					"SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = 'action_created_at'",
					DB_NAME,
					$offer_logs_table
				) );
				if ( ! $idx ) {
					$wpdb->query( "ALTER TABLE {$offer_logs_table} ADD KEY action_created_at (action, created_at)" );
				}
			}
		}

		$ab_var_table = $wpdb->prefix . 'cro_ab_variations';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ab_var_table ) ) === $ab_var_table ) {
			$idx = $wpdb->get_var( $wpdb->prepare(
				"SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = 'conversions'",
				DB_NAME,
				$ab_var_table
			) );
			if ( ! $idx ) {
				$wpdb->query( "ALTER TABLE {$ab_var_table} ADD KEY conversions (conversions)" );
			}
		}
	}

	/**
	 * Add 'offer' to cro_events.source_type enum if not present. Safe to run multiple times.
	 */
	public static function maybe_add_offer_source_type() {
		global $wpdb;
		$events_table = $wpdb->prefix . 'cro_events';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $events_table ) ) !== $events_table ) {
			return;
		}
		$col = $wpdb->get_row( $wpdb->prepare(
			"SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'source_type'",
			DB_NAME,
			$events_table
		) );
		if ( $col && is_string( $col->COLUMN_TYPE ) && strpos( $col->COLUMN_TYPE, 'offer' ) === false ) {
			$wpdb->query( "ALTER TABLE {$events_table} MODIFY COLUMN source_type enum('campaign','sticky_cart','shipping_bar','trust_badge','offer') NOT NULL" );
		}
	}

	/**
	 * Run dbDelta for all tables.
	 *
	 * @param string $from_version Previous schema version (empty on initial install).
	 */
	private static function run_db_delta( $from_version = '0' ) {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		if ( empty( $charset_collate ) ) {
			$charset_collate = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Table 1: campaigns
		$table_campaigns = $wpdb->prefix . 'cro_campaigns';
		// Migrate template_type from enum to varchar so any template key (e.g. slide-bottom, centered-image-left) can be stored.
		if ( version_compare( $from_version, '1.3.0', '<' ) ) {
			$wpdb->query( "ALTER TABLE {$table_campaigns} MODIFY COLUMN template_type VARCHAR(64) DEFAULT 'centered'" );
		}
		$sql_campaigns   = "CREATE TABLE $table_campaigns (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			status enum('active','paused','draft') DEFAULT 'draft',
			campaign_type enum('exit_intent','scroll_trigger','time_trigger','manual') DEFAULT 'exit_intent',
			template_type varchar(64) DEFAULT 'centered',
			trigger_settings longtext,
			content longtext NOT NULL,
			styling longtext,
			targeting_rules longtext,
			display_rules longtext,
			priority int(11) DEFAULT 10,
			offer_rules longtext,
			schedule longtext,
			cooldown int(11) DEFAULT 3600,
			fallback_id bigint(20) DEFAULT 0,
			fallback_delay_seconds int(11) DEFAULT 5,
			impressions bigint(20) unsigned DEFAULT 0,
			conversions bigint(20) unsigned DEFAULT 0,
			revenue_attributed decimal(12,2) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY campaign_type (campaign_type)
		) $charset_collate;";
		dbDelta( $sql_campaigns );

		// Table 2: events
		$table_events = $wpdb->prefix . 'cro_events';
		$sql_events   = "CREATE TABLE $table_events (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type enum('impression','conversion','dismiss','interaction') NOT NULL,
			source_type enum('campaign','sticky_cart','shipping_bar','trust_badge','offer') NOT NULL,
			source_id bigint(20) unsigned DEFAULT NULL,
			session_id varchar(64) NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			page_url varchar(500),
			page_type varchar(50),
			device_type enum('desktop','mobile','tablet') DEFAULT 'desktop',
			cart_value decimal(10,2) DEFAULT NULL,
			cart_items int DEFAULT NULL,
			conversion_type varchar(50) DEFAULT NULL,
			email varchar(255) DEFAULT NULL,
			coupon_code varchar(50) DEFAULT NULL,
			order_id bigint(20) unsigned DEFAULT NULL,
			order_value decimal(10,2) DEFAULT NULL,
			metadata longtext,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY source_type_id (source_type, source_id),
			KEY event_type (event_type),
			KEY session_id (session_id),
			KEY created_at (created_at),
			KEY order_id (order_id)
		) $charset_collate;";
		dbDelta( $sql_events );

		// Table 3: emails
		$table_emails = $wpdb->prefix . 'cro_emails';
		$sql_emails   = "CREATE TABLE $table_emails (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(255) NOT NULL,
			source_type varchar(50) NOT NULL,
			source_id bigint(20) unsigned DEFAULT NULL,
			source_name varchar(255),
			page_url varchar(500),
			cart_value decimal(10,2) DEFAULT NULL,
			coupon_offered varchar(50) DEFAULT NULL,
			wc_customer_id bigint(20) unsigned DEFAULT NULL,
			converted_to_order tinyint(1) DEFAULT 0,
			first_order_id bigint(20) unsigned DEFAULT NULL,
			first_order_value decimal(10,2) DEFAULT NULL,
			subscribed_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY source_type_id (source_type, source_id),
			KEY converted_to_order (converted_to_order)
		) $charset_collate;";
		dbDelta( $sql_emails );

		// Table 4: settings
		$table_settings = $wpdb->prefix . 'cro_settings';
		$sql_settings   = "CREATE TABLE $table_settings (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			setting_group varchar(50) NOT NULL,
			setting_key varchar(100) NOT NULL,
			setting_value longtext,
			autoload enum('yes','no') DEFAULT 'yes',
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY group_key (setting_group, setting_key),
			KEY autoload (autoload)
		) $charset_collate;";
		dbDelta( $sql_settings );

		// A/B Test table
		$ab_tests_table = $wpdb->prefix . 'cro_ab_tests';
		$sql_ab_tests = "CREATE TABLE {$ab_tests_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			status ENUM('draft', 'running', 'paused', 'completed') DEFAULT 'draft',
			original_campaign_id BIGINT(20) UNSIGNED NOT NULL,
			metric VARCHAR(50) DEFAULT 'conversion_rate',
			min_sample_size INT DEFAULT 200,
			confidence_level INT DEFAULT 95,
			winner_variation_id BIGINT(20) UNSIGNED DEFAULT NULL,
			auto_apply_winner TINYINT(1) DEFAULT 0,
			started_at DATETIME DEFAULT NULL,
			completed_at DATETIME DEFAULT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY original_campaign (original_campaign_id),
			KEY status (status)
		) $charset_collate;";

		dbDelta($sql_ab_tests);

		// A/B Test Variations table
		$ab_variations_table = $wpdb->prefix . 'cro_ab_variations';
		$sql_ab_variations = "CREATE TABLE {$ab_variations_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			test_id BIGINT(20) UNSIGNED NOT NULL,
			name VARCHAR(100) NOT NULL,
			is_control TINYINT(1) DEFAULT 0,
			traffic_weight INT DEFAULT 50,
			campaign_data LONGTEXT NOT NULL,
			impressions INT DEFAULT 0,
			conversions INT DEFAULT 0,
			revenue DECIMAL(10,2) DEFAULT 0.00,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY test_id (test_id),
			KEY is_control (is_control)
		) $charset_collate;";

		dbDelta( $sql_ab_variations );

		// A/B Test Assignments table (visitor → variation per test; one row per test_id + visitor_id)
		$ab_assignments_table = $wpdb->prefix . 'cro_ab_assignments';
		$sql_ab_assignments = "CREATE TABLE {$ab_assignments_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			test_id BIGINT(20) UNSIGNED NOT NULL,
			visitor_id VARCHAR(64) NOT NULL,
			variation_id BIGINT(20) UNSIGNED NOT NULL,
			assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY test_id_visitor_id (test_id, visitor_id)
		) $charset_collate;";

		dbDelta( $sql_ab_assignments );
	}

	/**
	 * Set default settings in cro_settings table on activation.
	 * Only sets values when the key does not already exist, so user changes persist on re-activation.
	 */
	public static function set_default_settings() {
		require_once CRO_PLUGIN_DIR . 'includes/class-cro-settings.php';
		$settings = CRO_Settings::get_instance();

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
			'cro_version'            => CRO_VERSION,
			'cro_enable_analytics'   => true,
			'cro_enable_sticky_cart' => true,
			'cro_enable_shipping_bar' => true,
			'cro_enable_trust_badges' => true,
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
		$tone = class_exists( 'CRO_Default_Copy' ) ? CRO_Default_Copy::TONE_NEUTRAL : 'neutral';
		$exit = class_exists( 'CRO_Default_Copy' ) ? CRO_Default_Copy::get_map( 'exit_intent' ) : array();
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
			'coupon_display_text' => __( 'Use code at checkout', 'meyvora-convert' ),
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
				'type'    => 'specific',
				'include' => array( 'cart', 'product' ),
				// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
				'exclude' => array( 'checkout', 'my-account' ),
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
