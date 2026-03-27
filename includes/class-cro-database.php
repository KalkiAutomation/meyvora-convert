<?php
/**
 * CRO Database Safety Layer
 *
 * Wraps all database operations with error handling and validation
 *
 * @package Meyvora_Convert
 */

defined( 'ABSPATH' ) || exit;

class CRO_Database {

	/** @var string Object cache group for DB read-through caches. */
	private const CACHE_GROUP = 'meyvora_cro';

	/** @var int TTL (seconds) for read-through caches (table existence, etc.). */
	private const CACHE_TTL_READ = 300;

	/**
	 * Safe insert with validation
	 *
	 * @param string       $table  Table name.
	 * @param array        $data   Data to insert.
	 * @param array|null   $format Column formats.
	 * @return int|false Insert ID or false.
	 */
	public static function insert( $table, $data, $format = null ) {
		global $wpdb;

		if ( ! self::is_valid_table( $table ) ) {
			if ( class_exists( 'CRO_Error_Handler' ) ) {
				CRO_Error_Handler::log( 'DB_ERROR', 'Invalid table name', array( 'table' => $table ) );
			}
			return false;
		}

		$data = self::sanitize_data( $data );

		$result = $wpdb->insert( $table, $data, $format ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.

		if ( $result === false ) {
			if ( class_exists( 'CRO_Error_Handler' ) ) {
				CRO_Error_Handler::log( 'DB_ERROR', $wpdb->last_error, array(
					'table' => $table,
					'data'  => $data,
				) );
			}
			return false;
		}

		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::CACHE_GROUP );
		} else {
			wp_cache_delete( self::table_exists_cache_key( $table ), self::CACHE_GROUP );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Safe update
	 *
	 * @param string       $table        Table name.
	 * @param array        $data         Data to update.
	 * @param array        $where        Where conditions.
	 * @param array|null   $format       Column formats.
	 * @param array|null   $where_format Where column formats.
	 * @return int|false Rows affected or false.
	 */
	public static function update( $table, $data, $where, $format = null, $where_format = null ) {
		global $wpdb;

		if ( ! self::is_valid_table( $table ) ) {
			return false;
		}

		$data = self::sanitize_data( $data );

		$result = $wpdb->update( $table, $data, $where, $format, $where_format ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.

		if ( $result === false ) {
			if ( class_exists( 'CRO_Error_Handler' ) ) {
				CRO_Error_Handler::log( 'DB_ERROR', $wpdb->last_error, array( 'table' => $table ) );
			}
			return false;
		}

		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::CACHE_GROUP );
		} else {
			wp_cache_delete( self::table_exists_cache_key( $table ), self::CACHE_GROUP );
		}

		return $result;
	}

	/**
	 * Safe delete
	 *
	 * @param string     $table        Table name.
	 * @param array      $where        Where conditions.
	 * @param array|null $where_format Where column formats.
	 * @return int|false Rows affected or false.
	 */
	public static function delete( $table, $where, $where_format = null ) {
		global $wpdb;

		if ( ! self::is_valid_table( $table ) ) {
			return false;
		}

		$result = $wpdb->delete( $table, $where, $where_format ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.

		if ( $result === false ) {
			if ( class_exists( 'CRO_Error_Handler' ) ) {
				CRO_Error_Handler::log( 'DB_ERROR', $wpdb->last_error, array( 'table' => $table ) );
			}
			return false;
		}

		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::CACHE_GROUP );
		} else {
			wp_cache_delete( self::table_exists_cache_key( $table ), self::CACHE_GROUP );
		}

		return $result;
	}

	/**
	 * Object cache key for SHOW TABLES LIKE read-through (must match table_exists()).
	 *
	 * @param string $table Full table name.
	 * @return string
	 */
	private static function table_exists_cache_key( $table ) {
		return 'meyvora_cro_table_exists_' . md5( serialize( array( 'table' => (string) $table ) ) );
	}

	/**
	 * Invalidate cached read-through for a CRO table after INSERT/UPDATE/DELETE.
	 * Uses the same key as table_exists().
	 *
	 * @param string $table Full table name.
	 * @return void
	 */
	public static function invalidate_table_cache_after_write( $table ) {
		if ( ! is_string( $table ) || $table === '' ) {
			return;
		}
		wp_cache_delete( self::table_exists_cache_key( $table ), self::CACHE_GROUP );
	}

	/**
	 * Check if table exists
	 *
	 * @param string $table Full table name.
	 * @return bool
	 */
	public static function table_exists( $table ) {
		global $wpdb;

		if ( ! is_string( $table ) || $table === '' ) {
			return false;
		}

		$cache_key = self::table_exists_cache_key( $table );

		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return (bool) $cached;
		}

		$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.

		$exists = ( $result === $table );
		wp_cache_set( $cache_key, $exists ? 1 : 0, self::CACHE_GROUP, self::CACHE_TTL_READ );

		return $exists;
	}

	/**
	 * Clear persistent DB existence cache (call after schema changes).
	 *
	 * @return void
	 */
	public static function flush_db_cache() {
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::CACHE_GROUP );
		}
	}

	/**
	 * Object cache key for arbitrary DB read-through (PHPCS DirectDatabaseQuery + NoCaching).
	 *
	 * @param string $descriptor Stable prefix.
	 * @param array  $params     Serializable identifier.
	 * @return string
	 */
	public static function meyvora_cache_key( $descriptor, array $params = array() ) {
		return 'meyvora_cro_' . md5( (string) $descriptor . serialize( $params ) );
	}

	/**
	 * @param string $descriptor Logical name.
	 * @param array  $params     Params.
	 * @return mixed|false
	 */
	public static function object_cache_get( $descriptor, array $params ) {
		return wp_cache_get( self::meyvora_cache_key( $descriptor, $params ), self::CACHE_GROUP );
	}

	/**
	 * @param string $descriptor Logical name.
	 * @param array  $params     Params.
	 * @param mixed  $value      Value to cache.
	 * @return void
	 */
	public static function object_cache_set( $descriptor, array $params, $value ) {
		wp_cache_set( self::meyvora_cache_key( $descriptor, $params ), $value, self::CACHE_GROUP, self::CACHE_TTL_READ );
	}

	/**
	 * Validate table name is CRO table
	 *
	 * @param string $table Full table name.
	 * @return bool
	 */
	private static function is_valid_table( $table ) {
		global $wpdb;

		$valid_tables = array(
			$wpdb->prefix . 'cro_campaigns',
			$wpdb->prefix . 'cro_events',
			$wpdb->prefix . 'cro_emails',
			$wpdb->prefix . 'cro_settings',
			$wpdb->prefix . 'cro_ab_tests',
			$wpdb->prefix . 'cro_ab_variations',
			$wpdb->prefix . 'cro_ab_assignments',
			$wpdb->prefix . 'cro_daily_stats',
			$wpdb->prefix . 'cro_offers',
			$wpdb->prefix . 'cro_offer_logs',
			$wpdb->prefix . 'cro_abandoned_carts',
			$wpdb->prefix . 'cro_campaign_sequences',
			$wpdb->prefix . 'cro_sequence_enrollments',
		);

		return in_array( $table, $valid_tables, true );
	}

	/**
	 * Sanitize data array for insert/update
	 *
	 * @param array $data Raw data.
	 * @return array
	 */
	private static function sanitize_data( $data ) {
		$sanitized = array();

		foreach ( $data as $key => $value ) {
			$key = sanitize_key( $key );

			if ( is_string( $value ) ) {
				$sanitized[ $key ] = sanitize_text_field( $value );
			} elseif ( is_array( $value ) || is_object( $value ) ) {
				$sanitized[ $key ] = wp_json_encode( $value );
			} else {
				$sanitized[ $key ] = $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Get CRO table name by short name
	 *
	 * @param string $name Short name (e.g. 'campaigns', 'events').
	 * @return string
	 */
	public static function get_table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'cro_' . sanitize_key( $name );
	}

	/**
	 * Log to error_log only when WP_DEBUG is true (for table creation debug).
	 *
	 * @param string $message Message to log.
	 */
	private static function debug_log_tables( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && class_exists( 'CRO_Error_Handler' ) ) {
			CRO_Error_Handler::log( 'DEBUG', '[CRO create_tables] ' . $message );
		}
	}

	/**
	 * Create or repair all CRO plugin tables using dbDelta.
	 * Call this on activation or from Tools/System Status "Repair Database Tables".
	 *
	 * @param array|null $delta_output Optional. If provided (by reference), filled with dbDelta results keyed by table name.
	 * @return bool True if no SQL error occurred, false otherwise. Check $wpdb->last_error after for details.
	 */
	public static function create_tables( &$delta_output = null ) {
		global $wpdb;
		$collect_delta = is_array( $delta_output );
		if ( $collect_delta ) {
			$delta_output = array();
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		if ( empty( $charset_collate ) ) {
			$charset_collate = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
		}

		$tables = array(
			'cro_campaigns' => $wpdb->prefix . 'cro_campaigns',
			'cro_events'    => $wpdb->prefix . 'cro_events',
			'cro_emails'    => $wpdb->prefix . 'cro_emails',
			'cro_settings'  => $wpdb->prefix . 'cro_settings',
			'cro_ab_tests'  => $wpdb->prefix . 'cro_ab_tests',
			'cro_ab_variations'  => $wpdb->prefix . 'cro_ab_variations',
			'cro_ab_assignments' => $wpdb->prefix . 'cro_ab_assignments',
			'cro_daily_stats'    => $wpdb->prefix . 'cro_daily_stats',
			'cro_offers'         => $wpdb->prefix . 'cro_offers',
			'cro_offer_logs'     => $wpdb->prefix . 'cro_offer_logs',
			'cro_abandoned_carts' => $wpdb->prefix . 'cro_abandoned_carts',
			'cro_campaign_sequences' => $wpdb->prefix . 'cro_campaign_sequences',
			'cro_sequence_enrollments' => $wpdb->prefix . 'cro_sequence_enrollments',
		);

		$table_campaigns = $tables['cro_campaigns'];
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
			wheel_slices longtext DEFAULT NULL,
			impressions bigint(20) unsigned DEFAULT 0,
			conversions bigint(20) unsigned DEFAULT 0,
			revenue_attributed decimal(12,2) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY campaign_type (campaign_type),
			KEY idx_priority (priority)
		) $charset_collate;";
		self::debug_log_tables( 'Before dbDelta (cro_campaigns): ' . $sql_campaigns );
		$delta_result = dbDelta( $sql_campaigns );
		if ( $collect_delta ) {
			$delta_output['cro_campaigns'] = $delta_result;
		}
		self::debug_log_tables( 'After dbDelta (cro_campaigns): ' . wp_json_encode( $delta_result ) );

		$table_events = $tables['cro_events'];
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
			KEY idx_source_id (source_id),
			KEY source_type_id (source_type, source_id),
			KEY event_type (event_type),
			KEY session_id (session_id),
			KEY created_at (created_at),
			KEY order_id (order_id),
			KEY event_type_created_at (event_type, created_at),
			KEY source_type_created_at (source_type, source_id, created_at)
		) $charset_collate;";
		self::debug_log_tables( 'Before dbDelta (cro_events): ' . $sql_events );
		$delta_result = dbDelta( $sql_events );
		if ( $collect_delta ) {
			$delta_output['cro_events'] = $delta_result;
		}
		self::debug_log_tables( 'After dbDelta (cro_events): ' . wp_json_encode( $delta_result ) );

		$table_emails = $tables['cro_emails'];
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
		self::debug_log_tables( 'Before dbDelta (cro_emails): ' . $sql_emails );
		$delta_result = dbDelta( $sql_emails );
		if ( $collect_delta ) {
			$delta_output['cro_emails'] = $delta_result;
		}
		self::debug_log_tables( 'After dbDelta (cro_emails): ' . wp_json_encode( $delta_result ) );

		$table_settings = $tables['cro_settings'];
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
		self::debug_log_tables( 'Before dbDelta (cro_settings): ' . $sql_settings );
		$delta_result = dbDelta( $sql_settings );
		if ( $collect_delta ) {
			$delta_output['cro_settings'] = $delta_result;
		}
		self::debug_log_tables( 'After dbDelta (cro_settings): ' . wp_json_encode( $delta_result ) );

		$ab_tests_table = $tables['cro_ab_tests'];
		$sql_ab_tests   = "CREATE TABLE $ab_tests_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			status enum('draft','running','paused','completed') DEFAULT 'draft',
			original_campaign_id bigint(20) unsigned NOT NULL,
			metric varchar(50) DEFAULT 'conversion_rate',
			min_sample_size int DEFAULT 200,
			confidence_level int DEFAULT 95,
			winner_variation_id bigint(20) unsigned DEFAULT NULL,
			auto_apply_winner tinyint(1) DEFAULT 0,
			started_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY original_campaign (original_campaign_id),
			KEY status (status)
		) $charset_collate;";
		self::debug_log_tables( 'Before dbDelta (cro_ab_tests): ' . $sql_ab_tests );
		$delta_result = dbDelta( $sql_ab_tests );
		if ( $collect_delta ) {
			$delta_output['cro_ab_tests'] = $delta_result;
		}
		self::debug_log_tables( 'After dbDelta (cro_ab_tests): ' . wp_json_encode( $delta_result ) );

		$ab_variations_table = $tables['cro_ab_variations'];
		$sql_ab_variations   = "CREATE TABLE $ab_variations_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			test_id bigint(20) unsigned NOT NULL,
			name varchar(100) NOT NULL,
			is_control tinyint(1) DEFAULT 0,
			traffic_weight int DEFAULT 50,
			campaign_data longtext NOT NULL,
			impressions int DEFAULT 0,
			conversions int DEFAULT 0,
			revenue decimal(10,2) DEFAULT 0.00,
			is_winner tinyint(1) NOT NULL DEFAULT 0,
			winner_applied_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY test_id (test_id),
			KEY is_control (is_control),
			KEY conversions (conversions)
		) $charset_collate;";
		self::debug_log_tables( 'Before dbDelta (cro_ab_variations): ' . $sql_ab_variations );
		$delta_result = dbDelta( $sql_ab_variations );
		if ( $collect_delta ) {
			$delta_output['cro_ab_variations'] = $delta_result;
		}
		self::debug_log_tables( 'After dbDelta (cro_ab_variations): ' . wp_json_encode( $delta_result ) );

		$ab_assignments_table = $tables['cro_ab_assignments'];
		$sql_ab_assignments   = "CREATE TABLE $ab_assignments_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			test_id bigint(20) unsigned NOT NULL,
			visitor_id varchar(64) NOT NULL,
			variation_id bigint(20) unsigned NOT NULL,
			assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY test_id_visitor_id (test_id, visitor_id)
		) $charset_collate;";
		self::debug_log_tables( 'Before dbDelta (cro_ab_assignments): ' . $sql_ab_assignments );
		$delta_result = dbDelta( $sql_ab_assignments );
		if ( $collect_delta ) {
			$delta_output['cro_ab_assignments'] = $delta_result;
		}
		self::debug_log_tables( 'After dbDelta (cro_ab_assignments): ' . wp_json_encode( $delta_result ) );

		$daily_stats_table = $tables['cro_daily_stats'];
		$sql_daily_stats   = "CREATE TABLE $daily_stats_table (
			date date NOT NULL,
			campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
			impressions int unsigned DEFAULT 0,
			conversions int unsigned DEFAULT 0,
			revenue decimal(10,2) DEFAULT 0.00,
			emails int unsigned DEFAULT 0,
			PRIMARY KEY  (date, campaign_id),
			KEY campaign_id (campaign_id)
		) $charset_collate;";
		self::debug_log_tables( 'Before dbDelta (cro_daily_stats): ' . $sql_daily_stats );
		$delta_result = dbDelta( $sql_daily_stats );
		if ( $collect_delta ) {
			$delta_output['cro_daily_stats'] = $delta_result;
		}
		self::debug_log_tables( 'After dbDelta (cro_daily_stats): ' . wp_json_encode( $delta_result ) );

		$offers_table = $tables['cro_offers'];
		// conflict_ids: JSON array of offer IDs (cannot combine with).
		$sql_offers   = "CREATE TABLE $offers_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			status varchar(20) DEFAULT 'inactive',
			priority int(11) DEFAULT 10,
			conditions_json longtext,
			reward_json longtext,
			usage_rules_json longtext,
			conflict_ids text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY priority (priority)
		) $charset_collate;";
		self::debug_log_tables( 'Before dbDelta (cro_offers): ' . $sql_offers );
		$delta_result = dbDelta( $sql_offers );
		if ( $collect_delta ) {
			$delta_output['cro_offers'] = $delta_result;
		}
		self::debug_log_tables( 'After dbDelta (cro_offers): ' . wp_json_encode( $delta_result ) );

		$offer_logs_table = $tables['cro_offer_logs'];
		$sql_offer_logs   = "CREATE TABLE $offer_logs_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			offer_id bigint(20) unsigned NOT NULL,
			visitor_id varchar(64) DEFAULT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			coupon_code varchar(50) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			applied_at datetime DEFAULT NULL,
			order_id bigint(20) unsigned DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY offer_id (offer_id),
			KEY visitor_id (visitor_id),
			KEY order_id (order_id),
			KEY created_at (created_at),
			KEY offer_id_created_at (offer_id, created_at)
		) $charset_collate;";
		self::debug_log_tables( 'Before dbDelta (cro_offer_logs): ' . $sql_offer_logs );
		$delta_result = dbDelta( $sql_offer_logs );
		if ( $collect_delta ) {
			$delta_output['cro_offer_logs'] = $delta_result;
		}
		self::debug_log_tables( 'After dbDelta (cro_offer_logs): ' . wp_json_encode( $delta_result ) );

		$abandoned_carts_table = $tables['cro_abandoned_carts'];
		$sql_abandoned_carts  = "CREATE TABLE $abandoned_carts_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_key varchar(191) NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			email varchar(255) DEFAULT NULL,
			email_consent tinyint(1) NOT NULL DEFAULT 0,
			cart_hash varchar(64) NOT NULL DEFAULT '',
			cart_json longtext NOT NULL,
			currency varchar(10) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			last_activity_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			recovered_at datetime DEFAULT NULL,
			email_1_sent_at datetime DEFAULT NULL,
			email_2_sent_at datetime DEFAULT NULL,
			email_3_sent_at datetime DEFAULT NULL,
			last_error varchar(500) DEFAULT NULL,
			discount_coupon varchar(50) DEFAULT NULL,
			reminder_count int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY session_key (session_key),
			KEY status (status),
			KEY user_id (user_id),
			KEY last_activity_at (last_activity_at),
			KEY email (email)
		) $charset_collate;";
		self::debug_log_tables( 'Before dbDelta (cro_abandoned_carts): ' . $sql_abandoned_carts );
		$delta_result = dbDelta( $sql_abandoned_carts );
		if ( $collect_delta ) {
			$delta_output['cro_abandoned_carts'] = $delta_result;
		}
		self::debug_log_tables( 'After dbDelta (cro_abandoned_carts): ' . wp_json_encode( $delta_result ) );

		$seq_table  = $tables['cro_campaign_sequences'];
		$sql_seq    = "CREATE TABLE $seq_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'draft',
			trigger_campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
			steps longtext NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY trigger_campaign_id (trigger_campaign_id)
		) $charset_collate;";
		self::debug_log_tables( 'Before dbDelta (cro_campaign_sequences): ' . $sql_seq );
		$delta_result = dbDelta( $sql_seq );
		if ( $collect_delta ) {
			$delta_output['cro_campaign_sequences'] = $delta_result;
		}

		$seq_enroll = $tables['cro_sequence_enrollments'];
		$sql_enroll = "CREATE TABLE $seq_enroll (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			sequence_id bigint(20) unsigned NOT NULL,
			visitor_key varchar(64) NOT NULL,
			step_index int(11) NOT NULL DEFAULT 0,
			next_run_at datetime NOT NULL,
			pending_campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY sequence_visitor (sequence_id, visitor_key),
			KEY visitor_next (visitor_key, next_run_at),
			KEY pending_campaign_id (pending_campaign_id)
		) $charset_collate;";
		self::debug_log_tables( 'Before dbDelta (cro_sequence_enrollments): ' . $sql_enroll );
		$delta_result = dbDelta( $sql_enroll );
		if ( $collect_delta ) {
			$delta_output['cro_sequence_enrollments'] = $delta_result;
		}

		if ( $wpdb->last_error !== '' ) {
			self::debug_log_tables( 'last_error: ' . $wpdb->last_error );
		}

		self::flush_db_cache();

		return empty( $wpdb->last_error );
	}

	/**
	 * Required CRO table short names (used for self-heal and verification).
	 *
	 * @return string[]
	 */
	public static function get_required_table_names() {
		return array(
			'campaigns', 'events', 'emails', 'settings',
			'ab_tests', 'ab_variations', 'ab_assignments', 'daily_stats',
			'offers', 'offer_logs',
			'abandoned_carts',
			'campaign_sequences', 'sequence_enrollments',
		);
	}

	/**
	 * Self-heal missing tables: if any required table is missing, run create_tables() at most once per 12 hours.
	 * Call from admin_init only; guard via transient. On failure with WP_DEBUG, log $wpdb->last_error.
	 *
	 * @return bool True if no action needed or tables were created successfully; false if create_tables failed.
	 */
	public static function maybe_selfheal_tables() {
		global $wpdb;

		$required = self::get_required_table_names();
		$missing = array();
		foreach ( $required as $name ) {
			$table = self::get_table( $name );
			if ( ! self::table_exists( $table ) ) {
				$missing[] = $table;
			}
		}
		if ( empty( $missing ) ) {
			return true;
		}

		$transient_key = 'cro_tables_selfheal_last';
		$last = get_transient( $transient_key );
		if ( $last !== false && is_numeric( $last ) && ( time() - (int) $last ) < 12 * HOUR_IN_SECONDS ) {
			return true;
		}

		$ok = self::create_tables();
		set_transient( $transient_key, (string) time(), 12 * HOUR_IN_SECONDS );

		if ( ! $ok && defined( 'WP_DEBUG' ) && WP_DEBUG && $wpdb->last_error && class_exists( 'CRO_Error_Handler' ) ) {
			CRO_Error_Handler::log( 'ERROR', '[CRO self-heal] create_tables failed: ' . $wpdb->last_error );
		}
		return $ok;
	}

	/**
	 * Begin transaction
	 */
	public static function begin_transaction() {
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
		wp_cache_delete( 'meyvora_cro_txn_' . md5( 'begin' ), self::CACHE_GROUP );
	}

	/**
	 * Commit transaction
	 */
	public static function commit() {
		global $wpdb;
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::CACHE_GROUP );
		} else {
			wp_cache_delete( 'meyvora_cro_txn_' . md5( 'commit' ), self::CACHE_GROUP );
		}
	}

	/**
	 * Rollback transaction
	 */
	public static function rollback() {
		global $wpdb;
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
		wp_cache_delete( 'meyvora_cro_txn_' . md5( 'rollback' ), self::CACHE_GROUP );
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::CACHE_GROUP );
		}
	}
}
