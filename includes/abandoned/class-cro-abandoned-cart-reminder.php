<?php
/**
 * Abandoned cart reminder emails: scheduling and sending.
 *
 * Uses Action Scheduler if available (WooCommerce), else wp-cron.
 * Schedule: from last_activity_at — standard uses settings (default 1h / 24h / 72h); high-value carts use 0.5h / 4h / 24h.
 * Only sends if status=active, email_consent=1, email exists, cart not recovered, and email_N not already sent.
 * Logs sent timestamps (email_1/2/3_sent_at) and last_error in DB.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class CRO_Abandoned_Cart_Reminder
 */
class CRO_Abandoned_Cart_Reminder {

	const HOOK  = 'cro_abandoned_cart_reminder';
	const GROUP = 'cro_abandoned_cart';
	const MAX_EMAILS = 3;
	const MAX_REMINDERS = 3;

	/**
	 * @return void
	 */
	private static function reminder_flush_read_cache() {
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'meyvora_cro' );
		}
	}

	/**
	 * Register the action hook and (for wp-cron fallback) ensure cron is scheduled.
	 */
	public function __construct() {
		add_action( self::HOOK, array( __CLASS__, 'send_reminder_callback' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'maybe_schedule_recurring_cron' ), 20 );
		add_action( 'cro_abandoned_cart_process_due_reminders', array( __CLASS__, 'process_due_reminders' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_interval' ) );
	}

	/**
	 * Add 15-minute cron interval for reminder processing (wp-cron fallback).
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function add_cron_interval( $schedules ) {
		if ( isset( $schedules['cro_every_fifteen_minutes'] ) ) {
			return $schedules;
		}
		$schedules['cro_every_fifteen_minutes'] = array(
			'interval' => 15 * 60,
			'display'  => __( 'Every 15 minutes', 'meyvora-convert' ),
		);
		return $schedules;
	}

	/**
	 * Ensure we have a recurring wp-cron event to process due reminders (fallback when Action Scheduler not used).
	 */
	public static function maybe_schedule_recurring_cron() {
		if ( function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}
		if ( wp_next_scheduled( 'cro_abandoned_cart_process_due_reminders' ) ) {
			return;
		}
		wp_schedule_event( time(), 'cro_every_fifteen_minutes', 'cro_abandoned_cart_process_due_reminders' );
	}

	/**
	 * Schedule a single reminder (email N) at timestamp. Uses Action Scheduler or wp-cron.
	 *
	 * @param int $abandoned_cart_id Row id.
	 * @param int $email_number      1, 2, or 3.
	 * @param int $run_timestamp     Unix timestamp when to run.
	 * @return bool True if scheduled.
	 */
	public static function schedule_reminder( $abandoned_cart_id, $email_number, $run_timestamp ) {
		if ( $abandoned_cart_id <= 0 || $email_number < 1 || $email_number > self::MAX_EMAILS ) {
			return false;
		}
		$args = array( $abandoned_cart_id, $email_number );
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( $run_timestamp, self::HOOK, $args, self::GROUP );
			return true;
		}
		wp_schedule_single_event( $run_timestamp, self::HOOK, $args );
		return true;
	}

	/**
	 * If the cart qualifies, schedule the first reminder (email 1). Call after tracker upserts a cart.
	 *
	 * @param int $abandoned_cart_id Row id.
	 */
	public static function schedule_reminder_if_needed( $abandoned_cart_id ) {
		if ( ! function_exists( 'cro_settings' ) ) {
			return;
		}
		$opts = cro_settings()->get_abandoned_cart_settings();
		if ( empty( $opts['enable_abandoned_cart_emails'] ) ) {
			return;
		}
		$row = self::get_row( $abandoned_cart_id );
		if ( ! $row ) {
			return;
		}
		if ( ! self::row_can_receive_reminder( $row ) ) {
			return;
		}
		if ( ! empty( $row->email_1_sent_at ) ) {
			return;
		}
		$segment     = self::get_segment( self::cart_row_to_array( $row ) );
		$delay_hours = self::get_delay_hours( 1, $segment );
		$run_at      = strtotime( $row->last_activity_at ) + (int) round( $delay_hours * HOUR_IN_SECONDS );
		if ( $run_at <= time() ) {
			$run_at = time() + 60;
		}
		self::schedule_reminder( $abandoned_cart_id, 1, $run_at );
	}

	/**
	 * Callback for the reminder action: send email N, log, then schedule N+1 if applicable.
	 *
	 * @param int $abandoned_cart_id Row id.
	 * @param int $email_number      1, 2, or 3.
	 */
	public static function send_reminder_callback( $abandoned_cart_id, $email_number ) {
		$email_number = (int) $email_number;
		if ( $abandoned_cart_id <= 0 || $email_number < 1 || $email_number > self::MAX_EMAILS ) {
			return;
		}
		$row = self::get_row( $abandoned_cart_id );
		if ( ! $row ) {
			return;
		}
		if ( ! self::row_can_receive_reminder( $row ) ) {
			return;
		}
		// Throttle: never send more than MAX_REMINDERS emails per abandoned cart.
		$reminder_count = isset( $row->reminder_count ) ? (int) $row->reminder_count : 0;
		if ( $reminder_count >= self::MAX_REMINDERS ) {
			return; // Already sent maximum reminders
		}
		$sent_col = 'email_' . $email_number . '_sent_at';
		if ( ! empty( $row->$sent_col ) ) {
			return;
		}
		$opts = function_exists( 'cro_settings' ) ? cro_settings()->get_abandoned_cart_settings() : array();
		$generate_for = isset( $opts['generate_coupon_for_email'] ) ? max( 1, min( 3, (int) $opts['generate_coupon_for_email'] ) ) : 1;
		$coupon_code  = null;
		if ( ! empty( $opts['enable_discount_in_emails'] ) && class_exists( 'CRO_Abandoned_Cart_Coupon' ) ) {
			if ( (int) $email_number === (int) $generate_for ) {
				$coupon_code = CRO_Abandoned_Cart_Coupon::get_or_create_coupon( $row, $opts );
			} elseif ( ! empty( $row->discount_coupon ) && CRO_Abandoned_Cart_Coupon::is_coupon_usable( $row->discount_coupon ) ) {
				$coupon_code = trim( (string) $row->discount_coupon );
			}
		}
		$sent = self::send_email( $row, $email_number, $coupon_code );
		global $wpdb;
		$table = CRO_Abandoned_Cart_Tracker::get_table_name();
		if ( $sent ) {
			$u1 = $wpdb->update( $table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
				array( $sent_col => current_time( 'mysql' ), 'last_error' => null, 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $abandoned_cart_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
			if ( false !== $u1 ) {
				if ( class_exists( 'CRO_Database' ) ) {
					CRO_Database::invalidate_table_cache_after_write( $table );
				}
				self::reminder_flush_read_cache();
			}
			$rc = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
				$wpdb->prepare(
					'UPDATE %i SET reminder_count = reminder_count + 1 WHERE id = %d',
					$table,
					$abandoned_cart_id
				) );
			if ( false !== $rc ) {
				if ( class_exists( 'CRO_Database' ) ) {
					CRO_Database::invalidate_table_cache_after_write( $table );
				}
				self::reminder_flush_read_cache();
			}
			// Schedule next email if N < 3.
			if ( $email_number < self::MAX_EMAILS ) {
				$next        = $email_number + 1;
				$segment     = self::get_segment( self::cart_row_to_array( $row ) );
				$delay_hours = self::get_delay_hours( $next, $segment );
				$run_at      = strtotime( $row->last_activity_at ) + (int) round( $delay_hours * HOUR_IN_SECONDS );
				if ( $run_at <= time() ) {
					$run_at = time() + 60;
				}
				self::schedule_reminder( $abandoned_cart_id, $next, $run_at );
			}
			if ( function_exists( 'do_action' ) ) {
				do_action( 'cro_abandoned_cart_email_sent', $abandoned_cart_id, $email_number );
			}
		} else {
			$err = self::get_last_send_error();
			$u2  = $wpdb->update( $table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
				array( 'last_error' => $err ? substr( $err, 0, 500 ) : __( 'Send failed', 'meyvora-convert' ), 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $abandoned_cart_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			if ( false !== $u2 ) {
				if ( class_exists( 'CRO_Database' ) ) {
					CRO_Database::invalidate_table_cache_after_write( $table );
				}
				self::reminder_flush_read_cache();
			}
		}
	}

	/**
	 * Admin-only: send one reminder email immediately (e.g. "Resend email 1"). Does not check if that email was already sent.
	 *
	 * @param int $cart_id       Abandoned cart row id.
	 * @param int $email_number  1, 2, or 3.
	 * @return bool True if sent, false otherwise.
	 */
	public static function send_reminder_immediately( $cart_id, $email_number = 1 ) {
		$email_number = (int) $email_number;
		if ( $cart_id <= 0 || $email_number < 1 || $email_number > self::MAX_EMAILS ) {
			return false;
		}
		$row = class_exists( 'CRO_Abandoned_Cart_Tracker' ) ? CRO_Abandoned_Cart_Tracker::get_row_by_id( $cart_id ) : null;
		if ( ! $row ) {
			return false;
		}
		if ( ! self::row_can_receive_reminder( $row ) ) {
			return false;
		}
		// Throttle: never send more than MAX_REMINDERS emails per abandoned cart.
		$reminder_count = isset( $row->reminder_count ) ? (int) $row->reminder_count : 0;
		if ( $reminder_count >= self::MAX_REMINDERS ) {
			return false; // Already sent maximum reminders
		}
		$opts = function_exists( 'cro_settings' ) ? cro_settings()->get_abandoned_cart_settings() : array();
		$generate_for = isset( $opts['generate_coupon_for_email'] ) ? max( 1, min( 3, (int) $opts['generate_coupon_for_email'] ) ) : 1;
		$coupon_code  = null;
		if ( ! empty( $opts['enable_discount_in_emails'] ) && class_exists( 'CRO_Abandoned_Cart_Coupon' ) ) {
			if ( (int) $email_number === (int) $generate_for ) {
				$coupon_code = CRO_Abandoned_Cart_Coupon::get_or_create_coupon( $row, $opts );
			} elseif ( ! empty( $row->discount_coupon ) && CRO_Abandoned_Cart_Coupon::is_coupon_usable( $row->discount_coupon ) ) {
				$coupon_code = trim( (string) $row->discount_coupon );
			}
		}
		$sent = self::send_email( $row, $email_number, $coupon_code );
		if ( $sent ) {
			$sent_col = 'email_' . $email_number . '_sent_at';
			global $wpdb;
			$table = CRO_Abandoned_Cart_Tracker::get_table_name();
			$u3    = $wpdb->update( $table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
				array( $sent_col => current_time( 'mysql' ), 'last_error' => null, 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $cart_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
			if ( false !== $u3 ) {
				if ( class_exists( 'CRO_Database' ) ) {
					CRO_Database::invalidate_table_cache_after_write( $table );
				}
				self::reminder_flush_read_cache();
			}
			$rc2 = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
				$wpdb->prepare(
					'UPDATE %i SET reminder_count = reminder_count + 1 WHERE id = %d',
					$table,
					$cart_id
				) );
			if ( false !== $rc2 ) {
				if ( class_exists( 'CRO_Database' ) ) {
					CRO_Database::invalidate_table_cache_after_write( $table );
				}
				self::reminder_flush_read_cache();
			}
			if ( function_exists( 'do_action' ) ) {
				do_action( 'cro_abandoned_cart_email_sent', $cart_id, $email_number );
			}
		}
		return $sent;
	}

	/**
	 * Process due reminders (wp-cron fallback: find carts due and run the hook manually).
	 */
	public static function process_due_reminders() {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			return;
		}
		$opts = function_exists( 'cro_settings' ) ? cro_settings()->get_abandoned_cart_settings() : array();
		if ( empty( $opts['enable_abandoned_cart_emails'] ) ) {
			return;
		}

		$lock_key = 'cro_reminder_cron_lock';
		if ( get_transient( $lock_key ) ) {
			return;
		}
		set_transient( $lock_key, 1, 5 * MINUTE_IN_SECONDS );

		try {
		global $wpdb;
		$table = CRO_Abandoned_Cart_Tracker::get_table_name();
		if ( ! CRO_Database::table_exists( $table ) ) {
			return;
		}
		$now    = current_time( 'mysql' );
		$active = CRO_Abandoned_Cart_Tracker::STATUS_ACTIVE;
		$ts_now = time();

		// Broad fetch then per-row segment delays (min high-value delay: 0.5h → 30 min; email 2 min 4h; email 3 min 24h).
		$cache_key_due1 = 'meyvora_cro_' . md5( serialize( array( 'abandoned_due_reminders_email_one', $table, $active, $now ) ) );
		$due1           = wp_cache_get( $cache_key_due1, 'meyvora_cro' );
		if ( false === $due1 ) {
			$due1 = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT * FROM %i WHERE status = %s AND email_consent = 1 AND email IS NOT NULL AND email != \'\' AND email_1_sent_at IS NULL AND last_activity_at <= DATE_SUB(%s, INTERVAL 30 MINUTE) LIMIT 40',
					$table,
					$active,
					$now
				),
				OBJECT
			);
			if ( ! is_array( $due1 ) ) {
				$due1 = array();
			}
			wp_cache_set( $cache_key_due1, $due1, 'meyvora_cro', 300 );
		} else {
			$due1 = is_array( $due1 ) ? $due1 : array();
		}
		foreach ( $due1 ? $due1 : array() as $r ) {
			$seg   = self::get_segment( self::cart_row_to_array( $r ) );
			$need  = self::get_delay_hours( 1, $seg );
			$due_ts = strtotime( $r->last_activity_at ) + (int) round( $need * HOUR_IN_SECONDS );
			if ( $due_ts <= $ts_now ) {
				do_action( self::HOOK, (int) $r->id, 1 );
			}
		}

		$cache_key_due2 = 'meyvora_cro_' . md5( serialize( array( 'abandoned_due_reminders_email_two', $table, $active, $now ) ) );
		$due2           = wp_cache_get( $cache_key_due2, 'meyvora_cro' );
		if ( false === $due2 ) {
			$due2 = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT * FROM %i WHERE status = %s AND email_consent = 1 AND email IS NOT NULL AND email != \'\' AND email_1_sent_at IS NOT NULL AND email_2_sent_at IS NULL AND last_activity_at <= DATE_SUB(%s, INTERVAL 4 HOUR) LIMIT 40',
					$table,
					$active,
					$now
				),
				OBJECT
			);
			if ( ! is_array( $due2 ) ) {
				$due2 = array();
			}
			wp_cache_set( $cache_key_due2, $due2, 'meyvora_cro', 300 );
		} else {
			$due2 = is_array( $due2 ) ? $due2 : array();
		}
		foreach ( $due2 ? $due2 : array() as $r ) {
			$seg    = self::get_segment( self::cart_row_to_array( $r ) );
			$need   = self::get_delay_hours( 2, $seg );
			$due_ts = strtotime( $r->last_activity_at ) + (int) round( $need * HOUR_IN_SECONDS );
			if ( $due_ts <= $ts_now ) {
				do_action( self::HOOK, (int) $r->id, 2 );
			}
		}

		$cache_key_due3 = 'meyvora_cro_' . md5( serialize( array( 'abandoned_due_reminders_email_three', $table, $active, $now ) ) );
		$due3           = wp_cache_get( $cache_key_due3, 'meyvora_cro' );
		if ( false === $due3 ) {
			$due3 = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT * FROM %i WHERE status = %s AND email_consent = 1 AND email IS NOT NULL AND email != \'\' AND email_2_sent_at IS NOT NULL AND email_3_sent_at IS NULL AND last_activity_at <= DATE_SUB(%s, INTERVAL 24 HOUR) LIMIT 40',
					$table,
					$active,
					$now
				),
				OBJECT
			);
			if ( ! is_array( $due3 ) ) {
				$due3 = array();
			}
			wp_cache_set( $cache_key_due3, $due3, 'meyvora_cro', 300 );
		} else {
			$due3 = is_array( $due3 ) ? $due3 : array();
		}
		foreach ( $due3 ? $due3 : array() as $r ) {
			$seg    = self::get_segment( self::cart_row_to_array( $r ) );
			$need   = self::get_delay_hours( 3, $seg );
			$due_ts = strtotime( $r->last_activity_at ) + (int) round( $need * HOUR_IN_SECONDS );
			if ( $due_ts <= $ts_now ) {
				do_action( self::HOOK, (int) $r->id, 3 );
			}
		}

		} finally {
			delete_transient( $lock_key );
		}
	}

	/**
	 * Normalize a DB row to an array for segment detection.
	 *
	 * @param object|array $row Cart row.
	 * @return array{cart_json: string}
	 */
	private static function cart_row_to_array( $row ) {
		if ( is_object( $row ) ) {
			return array( 'cart_json' => isset( $row->cart_json ) ? (string) $row->cart_json : '' );
		}
		if ( is_array( $row ) ) {
			return array( 'cart_json' => isset( $row['cart_json'] ) ? (string) $row['cart_json'] : '' );
		}
		return array( 'cart_json' => '' );
	}

	/**
	 * Segment from cart total vs high-value threshold setting.
	 *
	 * @param array $cart_row Must include cart_json.
	 * @return string 'high'|'standard'
	 */
	private static function get_segment( array $cart_row ) {
		$data = isset( $cart_row['cart_json'] ) ? json_decode( (string) $cart_row['cart_json'], true ) : null;
		$total = ( is_array( $data ) && isset( $data['totals']['total'] ) ) ? (float) $data['totals']['total'] : 0.0;
		$threshold = 100.0;
		if ( function_exists( 'cro_settings' ) ) {
			$threshold = (float) cro_settings()->get( 'abandoned_cart', 'high_value_threshold', 100 );
		}
		$threshold = max( 0.0, $threshold );
		return $total >= $threshold ? 'high' : 'standard';
	}

	/**
	 * Public segment helper for admin, CLI, and reports.
	 *
	 * @param object|array $row Abandoned cart row.
	 * @return string 'high'|'standard'
	 */
	public static function get_cart_segment( $row ) {
		return self::get_segment( self::cart_row_to_array( $row ) );
	}

	/**
	 * Segment label for an abandoned cart row (admin lists, exports).
	 *
	 * @param object|array $row DB row or array with cart_json.
	 * @return string 'high'|'standard'
	 */
	public static function get_segment_for_row( $row ): string {
		return self::get_cart_segment( $row );
	}

	/**
	 * Delay in hours from last_activity_at until email N should send.
	 *
	 * @param int    $email_number 1–3.
	 * @param string $segment      'high'|'standard'.
	 * @return float
	 */
	private static function get_delay_hours( $email_number, $segment ) {
		$email_number = (int) $email_number;
		$segment      = ( $segment === 'high' ) ? 'high' : 'standard';
		if ( $segment === 'high' ) {
			$map = array(
				1 => 0.5,
				2 => 4.0,
				3 => 24.0,
			);
			return isset( $map[ $email_number ] ) ? (float) $map[ $email_number ] : 1.0;
		}
		$opts      = function_exists( 'cro_settings' ) ? cro_settings()->get_abandoned_cart_settings() : array();
		$key       = 'email_' . $email_number . '_delay_hours';
		$fallbacks = array( 1 => 1, 2 => 24, 3 => 72 );
		$def       = isset( $fallbacks[ $email_number ] ) ? (int) $fallbacks[ $email_number ] : 1;
		$h         = isset( $opts[ $key ] ) ? (int) $opts[ $key ] : $def;
		return (float) max( 0, $h );
	}

	/**
	 * Planned Unix send times (from last_activity_at) for each email slot.
	 *
	 * @param object $row DB row with last_activity_at and cart_json.
	 * @return array{ segment: string, times: array<int,int> }
	 */
	public static function get_planned_send_times_unix( $row ) {
		$seg = self::get_segment( self::cart_row_to_array( $row ) );
		$base = ( is_object( $row ) && ! empty( $row->last_activity_at ) ) ? strtotime( $row->last_activity_at ) : time();
		$times = array();
		for ( $n = 1; $n <= self::MAX_EMAILS; $n++ ) {
			$h            = self::get_delay_hours( $n, $seg );
			$times[ $n ] = $base + (int) round( $h * HOUR_IN_SECONDS );
		}
		return array(
			'segment' => $seg,
			'times'   => $times,
		);
	}

	/**
	 * Human-readable schedule for admin / CLI (delays + planned local times from last_activity_at).
	 *
	 * @param object $row DB row.
	 * @return array{ segment: string, delays_hours: array<int,float>, planned_local: array<int,string>, threshold: float }
	 */
	public static function get_schedule_debug( $row ) {
		$seg = self::get_segment( self::cart_row_to_array( $row ) );
		$delays = array();
		for ( $n = 1; $n <= self::MAX_EMAILS; $n++ ) {
			$delays[ $n ] = self::get_delay_hours( $n, $seg );
		}
		$unix_wrap = self::get_planned_send_times_unix( $row );
		$planned   = array();
		foreach ( $unix_wrap['times'] as $n => $ts ) {
			$planned[ $n ] = function_exists( 'wp_date' )
				? wp_date( 'Y-m-d H:i', $ts )
				: gmdate( 'Y-m-d H:i', $ts );
		}
		$threshold = 100.0;
		if ( function_exists( 'cro_settings' ) ) {
			$threshold = max( 0.0, (float) cro_settings()->get( 'abandoned_cart', 'high_value_threshold', 100 ) );
		}
		return array(
			'segment'       => $seg,
			'delays_hours'  => $delays,
			'planned_local' => $planned,
			'threshold'     => $threshold,
		);
	}

	/**
	 * Default subject for high-value segment (before placeholder replacement).
	 *
	 * @param int $email_number 1–3.
	 * @return string
	 */
	private static function get_high_value_subject_template( $email_number ) {
		switch ( (int) $email_number ) {
			case 1:
				return __( 'We saved your cart — and we want to make it worth your while', 'meyvora-convert' );
			case 2:
				return __( 'Your {cart_total} cart is waiting — here is something special', 'meyvora-convert' );
			case 3:
				return __( 'Last chance: your cart expires soon', 'meyvora-convert' );
			default:
				return __( 'You left something in your cart – {store_name}', 'meyvora-convert' );
		}
	}

	/**
	 * Get abandoned cart row by id.
	 *
	 * @param int $id Row id.
	 * @return object|null
	 */
	private static function get_row( $id ) {
		global $wpdb;
		$table = CRO_Abandoned_Cart_Tracker::get_table_name();
		if ( ! CRO_Database::table_exists( $table ) ) {
			return null;
		}
		$cache_key = 'meyvora_cro_' . md5( serialize( array( 'abandoned_cart_row_reminder', $table, $id ) ) );
		$row       = wp_cache_get( $cache_key, 'meyvora_cro' );
		if ( false === $row ) {
			$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d LIMIT 1', $table, $id ),
				OBJECT
			);
			wp_cache_set( $cache_key, $row, 'meyvora_cro', 300 );
		}
		return $row;
	}

	/**
	 * Whether the row is eligible to receive a reminder: active, consent, email, not recovered.
	 *
	 * @param object $row DB row.
	 * @return bool
	 */
	private static function row_can_receive_reminder( $row ) {
		if ( $row->status !== CRO_Abandoned_Cart_Tracker::STATUS_ACTIVE ) {
			return false;
		}
		if ( empty( $row->email_consent ) ) {
			return false;
		}
		if ( empty( $row->email ) || ! is_email( $row->email ) ) {
			return false;
		}
		if ( ! empty( $row->recovered_at ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Build placeholder values for template replacement. Used when sending or previewing.
	 *
	 * @param object|null $row             Abandoned cart row (or null for sample).
	 * @param string|null $coupon_code    Optional coupon code.
	 * @param bool        $html           Whether cart_items should be HTML (for email body).
	 * @param string      $unsubscribe_url Optional unsubscribe URL for {unsubscribe_url} placeholder.
	 * @return array Map of placeholder name => value.
	 */
	public static function get_placeholder_values( ?object $row = null, $coupon_code = null, $html = true, $unsubscribe_url = '' ) {
		$store_name   = get_bloginfo( 'name' );
		$checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' );
		$first_name   = __( 'there', 'meyvora-convert' );
		$cart_items   = __( 'Your cart items', 'meyvora-convert' );
		$cart_total   = '';
		if ( $row ) {
			if ( ! empty( $row->user_id ) ) {
				$user = get_userdata( $row->user_id );
				if ( $user && ! empty( $user->first_name ) ) {
					$first_name = $user->first_name;
				}
			}
			$cart_items = $html ? self::get_cart_summary_html( $row ) : self::get_cart_summary( $row );
			$cart_total = self::get_cart_total_formatted( $row );
		} else {
			$cart_items = $html ? '<ul><li>Sample Product x 1</li><li>Another Item x 2</li></ul>' : "Sample Product x 1\nAnother Item x 2";
			$currency   = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
			$cart_total  = $currency . ' 49.00';
		}
		$discount_text = '';
		if ( ! empty( $coupon_code ) ) {
			/* translators: %1$s is the coupon code. */
		$discount_text = '<p>' . sprintf( __( 'Use code <strong>%1$s</strong> at checkout for your discount.', 'meyvora-convert' ), esc_html( $coupon_code ) ) . '</p>';
		}
		return array(
			'first_name'      => $first_name,
			'cart_items'      => $cart_items,
			'cart_total'      => $cart_total,
			'checkout_url'    => $checkout_url,
			'coupon_code'     => $coupon_code ? $coupon_code : '',
			'discount_text'   => $discount_text,
			'store_name'      => $store_name,
			'unsubscribe_url' => $unsubscribe_url,
		);
	}

	/**
	 * Replace placeholders in a string.
	 *
	 * @param string $text   Subject or body with {placeholder} tokens.
	 * @param array  $values From get_placeholder_values().
	 * @return string
	 */
	public static function replace_placeholders( $text, array $values ) {
		foreach ( $values as $key => $val ) {
			$text = str_replace( '{' . $key . '}', $val, $text );
		}
		return $text;
	}

	/**
	 * Get cart total formatted (e.g. "USD 99.00") from row.
	 *
	 * @param object $row Abandoned cart row.
	 * @return string
	 */
	private static function get_cart_total_formatted( $row ) {
		if ( empty( $row->cart_json ) ) {
			return '';
		}
		$data = json_decode( $row->cart_json, true );
		$currency = ! empty( $row->currency ) ? $row->currency : ( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD' );
		$total = isset( $data['totals']['total'] ) ? (float) $data['totals']['total'] : 0;
		return $currency . ' ' . number_format_i18n( $total, 2 );
	}

	/**
	 * Get cart summary as HTML (for email body).
	 *
	 * @param object $row Abandoned cart row.
	 * @return string
	 */
	private static function get_cart_summary_html( $row ) {
		if ( empty( $row->cart_json ) ) {
			return '<p>' . esc_html( __( 'Your cart items', 'meyvora-convert' ) ) . '</p>';
		}
		$data = json_decode( $row->cart_json, true );
		if ( ! is_array( $data ) || empty( $data['items'] ) ) {
			return '<p>' . esc_html( __( 'Your cart items', 'meyvora-convert' ) ) . '</p>';
		}
		$out = '<ul style="margin:0.5em 0;padding-left:1.2em;">';
		foreach ( $data['items'] as $item ) {
			$name = isset( $item['name'] ) ? esc_html( $item['name'] ) : esc_html( __( 'Item', 'meyvora-convert' ) );
			$qty  = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
			$out .= '<li>' . $name . ' x ' . $qty . '</li>';
		}
		$out .= '</ul>';
		return $out;
	}

	/**
	 * Build and send one reminder email. Uses templates from settings when set; otherwise fallback. Returns true on success.
	 *
	 * @param object    $row          Abandoned cart row.
	 * @param int       $email_number 1, 2, or 3.
	 * @param string|null $coupon_code Optional coupon code to include.
	 * @return bool
	 */
	private static function send_email( $row, $email_number, $coupon_code = null ) {
		$to   = $row->email;
		$opts = function_exists( 'cro_settings' ) ? cro_settings()->get_abandoned_cart_settings() : array();
		$segment = self::get_segment( self::cart_row_to_array( $row ) );
		if ( $segment === 'high' ) {
			$subject = self::get_high_value_subject_template( $email_number );
		} elseif ( isset( $opts['email_subject_template'] ) && trim( (string) $opts['email_subject_template'] ) !== '' ) {
			$subject = $opts['email_subject_template'];
		} else {
			$subject = __( 'You left something in your cart – {store_name}', 'meyvora-convert' );
		}
		$body_tpl = isset( $opts['email_body_template'] ) && trim( (string) $opts['email_body_template'] ) !== ''
			? $opts['email_body_template']
			: ( function_exists( 'cro_settings' ) ? cro_settings()->get_abandoned_cart_email_body_default() : '' );

		$unsubscribe_token = wp_hash( $row->email . '|' . $row->id . '|unsubscribe' );
		$unsubscribe_url   = add_query_arg( array(
			'cro_action' => 'unsubscribe_cart',
			'cart_id'    => (int) $row->id,
			'token'      => $unsubscribe_token,
		), home_url( '/' ) );

		$values = self::get_placeholder_values( $row, $coupon_code, true, $unsubscribe_url );

		$use_ai = class_exists( 'CRO_AI_Client' )
			&& CRO_AI_Client::is_configured()
			&& class_exists( 'CRO_AI_Email_Writer' )
			&& function_exists( 'cro_settings' )
			&& 'yes' === cro_settings()->get( 'ai', 'feature_emails', 'yes' );

		$ai_bundle = null;
		if ( $use_ai ) {
			try {
				$writer = new CRO_AI_Email_Writer();
				$cached = $writer->get_cached( (int) $row->id, (int) $email_number );
				if ( is_array( $cached ) ) {
					$ai_bundle = $cached;
				} else {
					$gen = $writer->generate_email( (int) $row->id, (int) $email_number, $coupon_code );
					if ( ! is_wp_error( $gen ) ) {
						$ai_bundle = $gen;
					} else {
						self::log_ai_email_failure( (int) $row->id, $gen->get_error_message() );
					}
				}
			} catch ( \Throwable $e ) {
				self::log_ai_email_failure( (int) $row->id, $e->getMessage() );
			}
		}

		if ( is_array( $ai_bundle ) && ! empty( $ai_bundle['subject'] ) && ! empty( $ai_bundle['body_html'] ) ) {
			$subject   = (string) $ai_bundle['subject'];
			$preheader = isset( $ai_bundle['preheader'] ) ? (string) $ai_bundle['preheader'] : '';
			$body_core = (string) $ai_bundle['body_html'];
			$pre_block = '';
			if ( $preheader !== '' ) {
				$pre_block = '<div style="display:none!important;visibility:hidden;mso-hide:all;font-size:1px;color:#ffffff;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">' . esc_html( $preheader ) . '</div>';
			}
			$cta_block = '<p><a href="' . esc_url( $values['checkout_url'] ) . '">' . esc_html__( 'Complete your order', 'meyvora-convert' ) . '</a></p>';
			if ( ! empty( $values['discount_text'] ) ) {
				$cta_block .= $values['discount_text'];
			}
			$body = $pre_block . $body_core . $cta_block;
		} else {
			$subject = self::replace_placeholders( $subject, $values );
			$body    = self::replace_placeholders( $body_tpl, $values );
		}

		// Build the full email message using the HTML wrapper template.
		$store_name  = get_bloginfo( 'name' );
		$store_url   = home_url( '/' );
		$footer_text = __( "You're receiving this email because you left items in your cart.", 'meyvora-convert' );

		$email_template = apply_filters(
			'cro_abandoned_cart_email_template',
			CRO_PLUGIN_DIR . 'templates/emails/abandoned-cart.php'
		);

		ob_start();
		if ( is_readable( $email_template ) ) {
			$body_content = $body;
			include $email_template;
		} else {
			echo '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:sans-serif;padding:20px;">';
			echo wp_kses_post( $body );
			echo '<p style="font-size:11px;color:#999;margin-top:32px;">' . esc_html( $footer_text );
			echo ' <a href="' . esc_url( $unsubscribe_url ) . '">' . esc_html__( 'Unsubscribe', 'meyvora-convert' ) . '</a></p>';
			echo '</body></html>';
		}
		$message = ob_get_clean();

		self::set_last_send_error( null );
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'List-Unsubscribe: <' . esc_url( $unsubscribe_url ) . '>',
		);
		$result = wp_mail( $to, $subject, $message, $headers );
		if ( ! $result ) {
			global $phpmailer;
			$err = is_object( $phpmailer ) && isset( $phpmailer->ErrorInfo )
				? $phpmailer->ErrorInfo
				: __( 'wp_mail failed', 'meyvora-convert' );
			self::set_last_send_error( $err );
		}
		return $result;
	}

	/**
	 * Log AI email generation failure and persist a short note on the cart row (cleared on successful send).
	 *
	 * @param int    $cart_id Abandoned cart id.
	 * @param string $message Error message.
	 */
	private static function log_ai_email_failure( $cart_id, $message ) {
		$cart_id = (int) $cart_id;
		if ( $cart_id <= 0 ) {
			return;
		}
		if ( class_exists( 'CRO_Error_Handler' ) && is_callable( array( 'CRO_Error_Handler', 'log' ) ) ) {
			CRO_Error_Handler::log(
				'AI_EMAIL',
				'Abandoned cart AI email fallback to static template',
				array(
					'cart_id' => $cart_id,
					'message' => $message,
				)
			);
		}
		if ( ! class_exists( 'CRO_Abandoned_Cart_Tracker' ) || ! class_exists( 'CRO_Database' ) ) {
			return;
		}
		$table = CRO_Abandoned_Cart_Tracker::get_table_name();
		if ( ! CRO_Database::table_exists( $table ) ) {
			return;
		}
		global $wpdb;
		$msg = 'AI email: ' . substr( wp_strip_all_tags( (string) $message ), 0, 450 );
		$u4  = $wpdb->update( $table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
			array(
				'last_error' => $msg,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $cart_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		if ( false !== $u4 ) {
			CRO_Database::invalidate_table_cache_after_write( $table );
			self::reminder_flush_read_cache();
		}
	}

	/**
	 * Get a short text summary of the cart from cart_json.
	 *
	 * @param object $row Abandoned cart row.
	 * @return string
	 */
	private static function get_cart_summary( $row ) {
		if ( empty( $row->cart_json ) ) {
			return __( 'Your cart items', 'meyvora-convert' );
		}
		$data = json_decode( $row->cart_json, true );
		if ( ! is_array( $data ) || empty( $data['items'] ) ) {
			return __( 'Your cart items', 'meyvora-convert' );
		}
		$lines = array();
		$currency = ! empty( $row->currency ) ? $row->currency : get_woocommerce_currency();
		foreach ( $data['items'] as $item ) {
			$name = isset( $item['name'] ) ? $item['name'] : __( 'Item', 'meyvora-convert' );
			$qty = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
			$lines[] = sprintf( '%s x %d', $name, $qty );
		}
		if ( ! empty( $data['totals']['total'] ) ) {
			$lines[] = sprintf( /* translators: %1$s is the currency code, %2$s is the formatted total amount. */ __( 'Total: %1$s %2$s', 'meyvora-convert' ), $currency, number_format_i18n( (float) $data['totals']['total'], 2 ) );
		}
		return implode( "\n", $lines );
	}

	private static $last_send_error = null;

	private static function set_last_send_error( $err ) {
		self::$last_send_error = $err;
	}

	private static function get_last_send_error() {
		return self::$last_send_error;
	}
}
