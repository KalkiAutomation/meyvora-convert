<?php
/**
 * Abandoned cart reminder emails: scheduling and sending.
 *
 * Uses Action Scheduler if available (WooCommerce), else wp-cron.
 * Schedule: email 1 (default 1h), 2 (24h), 3 (72h) from last_activity_at.
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
		$delay_hours = isset( $opts['email_1_delay_hours'] ) ? max( 0, (int) $opts['email_1_delay_hours'] ) : 1;
		$run_at = strtotime( $row->last_activity_at ) + ( $delay_hours * HOUR_IN_SECONDS );
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
			$wpdb->update(
				$table,
				array( $sent_col => current_time( 'mysql' ), 'last_error' => null, 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $abandoned_cart_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$table} SET reminder_count = reminder_count + 1 WHERE id = %d",
				$abandoned_cart_id
			) );
			// Schedule next email if N < 3.
			if ( $email_number < self::MAX_EMAILS ) {
				$opts = function_exists( 'cro_settings' ) ? cro_settings()->get_abandoned_cart_settings() : array();
				$next = $email_number + 1;
				$key  = 'email_' . $next . '_delay_hours';
				$delay_hours = isset( $opts[ $key ] ) ? max( 0, (int) $opts[ $key ] ) : ( $next === 2 ? 24 : 72 );
				$run_at = strtotime( $row->last_activity_at ) + ( $delay_hours * HOUR_IN_SECONDS );
				if ( $run_at <= time() ) {
					$run_at = time() + 60;
				}
				self::schedule_reminder( $abandoned_cart_id, $next, $run_at );
			}
		} else {
			$err = self::get_last_send_error();
			$wpdb->update(
				$table,
				array( 'last_error' => $err ? substr( $err, 0, 500 ) : __( 'Send failed', 'meyvora-convert' ), 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $abandoned_cart_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
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
			$wpdb->update(
				$table,
				array( $sent_col => current_time( 'mysql' ), 'last_error' => null, 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $cart_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$table} SET reminder_count = reminder_count + 1 WHERE id = %d",
				$cart_id
			) );
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
		$d1 = isset( $opts['email_1_delay_hours'] ) ? max( 0, (int) $opts['email_1_delay_hours'] ) : 1;
		$d2 = isset( $opts['email_2_delay_hours'] ) ? max( 0, (int) $opts['email_2_delay_hours'] ) : 24;
		$d3 = isset( $opts['email_3_delay_hours'] ) ? max( 0, (int) $opts['email_3_delay_hours'] ) : 72;
		global $wpdb;
		$table = CRO_Abandoned_Cart_Tracker::get_table_name();
		if ( ! CRO_Database::table_exists( $table ) ) {
			return;
		}
		$now = current_time( 'mysql' );
		$active = CRO_Abandoned_Cart_Tracker::STATUS_ACTIVE;
		// Carts due for email 1: email_1_sent_at IS NULL, last_activity_at + d1 <= now, active, consent, email not null.
		$due1 = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, last_activity_at FROM {$table} WHERE status = %s AND email_consent = 1 AND email IS NOT NULL AND email != '' AND email_1_sent_at IS NULL AND last_activity_at <= DATE_SUB(%s, INTERVAL %d HOUR) LIMIT 20",
				$active,
				$now,
				$d1
			),
			OBJECT
		);
		foreach ( $due1 as $r ) {
			do_action( self::HOOK, (int) $r->id, 1 );
		}
		// Carts due for email 2: email_1_sent_at set, email_2_sent_at NULL, last_activity_at + d2 <= now.
		$due2 = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE status = %s AND email_consent = 1 AND email IS NOT NULL AND email != '' AND email_1_sent_at IS NOT NULL AND email_2_sent_at IS NULL AND last_activity_at <= DATE_SUB(%s, INTERVAL %d HOUR) LIMIT 20",
				$active,
				$now,
				$d2
			),
			OBJECT
		);
		foreach ( $due2 as $r ) {
			do_action( self::HOOK, (int) $r->id, 2 );
		}
		// Carts due for email 3.
		$due3 = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE status = %s AND email_consent = 1 AND email IS NOT NULL AND email != '' AND email_2_sent_at IS NOT NULL AND email_3_sent_at IS NULL AND last_activity_at <= DATE_SUB(%s, INTERVAL %d HOUR) LIMIT 20",
				$active,
				$now,
				$d3
			),
			OBJECT
		);
		foreach ( $due3 as $r ) {
			do_action( self::HOOK, (int) $r->id, 3 );
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
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ), OBJECT );
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
		$to      = $row->email;
		$opts    = function_exists( 'cro_settings' ) ? cro_settings()->get_abandoned_cart_settings() : array();
		$subject = isset( $opts['email_subject_template'] ) && trim( (string) $opts['email_subject_template'] ) !== ''
			? $opts['email_subject_template']
			: __( 'You left something in your cart – {store_name}', 'meyvora-convert' );
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
		$subject = self::replace_placeholders( $subject, $values );
		$body    = self::replace_placeholders( $body_tpl, $values );

		$message  = $body;
		$message .= '<p style="font-size:11px;color:#999999;text-align:center;margin-top:32px;border-top:1px solid #eeeeee;padding-top:16px;">'
			. esc_html__( "You're receiving this email because you left items in your cart.", 'meyvora-convert' )
			. '<br><a href="' . esc_url( $unsubscribe_url ) . '" style="color:#999999;text-decoration:underline;">'
			. esc_html__( 'Unsubscribe from cart recovery emails', 'meyvora-convert' )
			. '</a></p>';
		self::set_last_send_error( null );
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'List-Unsubscribe: <' . esc_url( $unsubscribe_url ) . '>',
		);
		$result  = wp_mail( $to, $subject, $message, $headers );
		if ( ! $result ) {
			global $phpmailer;
			$err = is_object( $phpmailer ) && isset( $phpmailer->ErrorInfo ) ? $phpmailer->ErrorInfo : __( 'wp_mail failed', 'meyvora-convert' );
			self::set_last_send_error( $err );
		}
		return $result;
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
			$lines[] = sprintf( __( 'Total: %s %s', 'meyvora-convert' ), $currency, number_format_i18n( (float) $data['totals']['total'], 2 ) );
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
