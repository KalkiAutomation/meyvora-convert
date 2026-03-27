<?php
/**
 * Abandoned cart tracking for WooCommerce.
 *
 * Tracks carts in {$wpdb->prefix}cro_abandoned_carts. Only tracks when cart has at least one item.
 * Updates last_activity_at on cart change. For logged-in users, stores user_id and email.
 * Guest email is captured only when provided (e.g. checkout field – see separate task).
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class CRO_Abandoned_Cart_Tracker
 */
class CRO_Abandoned_Cart_Tracker {

	/**
	 * Allowed status values.
	 */
	const STATUS_ACTIVE       = 'active';
	const STATUS_RECOVERED    = 'recovered';
	const STATUS_EMAILED      = 'emailed';
	const STATUS_UNSUBSCRIBED = 'unsubscribed';

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	private static $table_short_name = 'abandoned_carts';

	/**
	 * Invalidate Meyvora object-cache reads after writes when group flush is available.
	 *
	 * @return void
	 */
	private static function flush_meyvora_cro_read_cache() {
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'meyvora_cro' );
		}
	}

	/**
	 * Register WooCommerce cart hooks.
	 */
	public function __construct() {
		add_action( 'woocommerce_cart_updated', array( $this, 'maybe_track_cart' ), 10, 0 );
		add_action( 'woocommerce_add_to_cart', array( $this, 'maybe_track_cart' ), 10, 0 );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'maybe_track_cart' ), 10, 0 );
		add_action( 'woocommerce_applied_coupon', array( $this, 'maybe_track_cart' ), 10, 0 );
		add_action( 'woocommerce_removed_coupon', array( $this, 'maybe_track_cart' ), 10, 0 );
		add_action( 'woocommerce_cart_emptied', array( $this, 'on_cart_emptied' ), 10, 0 );
		add_action( 'wp_ajax_cro_save_abandoned_cart_email', array( __CLASS__, 'ajax_save_email_consent' ) );
		add_action( 'wp_ajax_nopriv_cro_save_abandoned_cart_email', array( __CLASS__, 'ajax_save_email_consent' ) );
		add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'on_order_created' ), 10, 1 );
		add_action( 'init', array( __CLASS__, 'handle_unsubscribe_request' ) );
		add_action( 'wp_footer', array( __CLASS__, 'unsubscribe_notice' ) );
	}

	/**
	 * Hook: When an order is placed, find matching abandoned cart, mark recovered, cancel scheduled reminders.
	 *
	 * @param WC_Order $order The order that was created.
	 */
	public static function on_order_created( $order ) {
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return;
		}
		$session_key = '';
		$cart_hash   = '';
		if ( function_exists( 'WC' ) && WC()->session ) {
			$sid = WC()->session->get_customer_id();
			$session_key = is_string( $sid ) ? $sid : '';
		}
		if ( function_exists( 'WC' ) && WC()->cart ) {
			$cart_hash = WC()->cart->get_cart_hash();
		}
		$user_id = $order->get_customer_id();
		$email   = $order->get_billing_email();

		$row = self::find_abandoned_cart_for_order( $session_key, $user_id ? (int) $user_id : null, $email, $cart_hash );
		if ( ! $row ) {
			return;
		}
		$cart_row_id = (int) $row->id;
		self::mark_recovered_by_id( $cart_row_id );
		self::cancel_scheduled_reminders( $cart_row_id );
		if ( function_exists( 'do_action' ) ) {
			do_action( 'cro_abandoned_cart_recovered', $cart_row_id, (int) $order->get_id() );
		}
	}

	/**
	 * AJAX: Save guest email + consent for abandoned cart reminders. No silent capture; consent required when require_opt_in is on.
	 */
	public static function ajax_save_email_consent() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cro_abandoned_cart_email' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh and try again.', 'meyvora-convert' ) ) );
		}
		$rl_ip = class_exists( 'CRO_Security' ) ? CRO_Security::get_client_ip() : '';
		if ( class_exists( 'CRO_Security' ) && ! CRO_Security::check_rate_limit( 'cro_ajax_' . sanitize_key( current_action() ) . '_' . $rl_ip, 20, 60 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please slow down.', 'meyvora-convert' ) ), 429 );
		}
		if ( ! function_exists( 'cro_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Settings not available.', 'meyvora-convert' ) ) );
		}
		$opts = cro_settings()->get_abandoned_cart_settings();
		if ( empty( $opts['enable_abandoned_cart_emails'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Abandoned cart reminders are not enabled.', 'meyvora-convert' ) ) );
		}
		$consent = ! empty( $_POST['consent'] );
		if ( ! empty( $opts['require_opt_in'] ) && ! $consent ) {
			wp_send_json_error( array( 'message' => __( 'Consent is required to save your email for reminders.', 'meyvora-convert' ) ) );
		}
		$email = isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '';
		if ( $consent && ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'meyvora-convert' ) ) );
		}
		$updated = self::update_email_and_consent_for_session( $email, $consent );
		if ( $updated ) {
			wp_send_json_success( array( 'message' => __( 'Saved.', 'meyvora-convert' ) ) );
		}
		wp_send_json_error( array( 'message' => __( 'Could not save. You may need to add an item to your cart first.', 'meyvora-convert' ) ) );
	}

	/**
	 * Get the abandoned carts table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		return CRO_Database::get_table( self::$table_short_name );
	}

	/**
	 * Called when cart is updated (add/remove/coupon). Track only if cart has at least 1 item; update last_activity_at.
	 */
	public function maybe_track_cart() {
		// Skip WooCommerce Subscriptions renewal carts — don't treat as abandoned.
		if ( function_exists( 'WC' ) && WC()->cart && class_exists( 'WC_Subscriptions_Cart' ) ) {
			foreach ( WC()->cart->get_cart() as $item ) {
				if ( ! empty( $item['subscription_renewal'] ) ) {
					return;
				}
			}
		}

		if ( ! $this->is_woo_ready() ) {
			return;
		}

		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return;
		}

		$this->upsert_cart();
	}

	/**
	 * Called when cart is emptied. We do not create/update when cart has no items; previous record is left as-is for analytics.
	 */
	public function on_cart_emptied() {
		// No upsert when empty; existing row remains. Next cart activity will upsert again.
	}

	/**
	 * Check WooCommerce and session are available.
	 *
	 * @return bool
	 */
	private function is_woo_ready() {
		if ( ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart ) {
			return false;
		}
		return true;
	}

	/**
	 * Get current session key (unique per guest or logged-in user session).
	 *
	 * @return string
	 */
	private function get_session_key() {
		if ( ! WC()->session ) {
			return '';
		}
		$id = WC()->session->get_customer_id();
		return is_string( $id ) ? $id : (string) $id;
	}

	/**
	 * Build cart payload for cart_json: items, qty, totals, coupons.
	 *
	 * @return array
	 */
	private function build_cart_payload() {
		$cart = WC()->cart;
		$items = array();
		foreach ( $cart->get_cart() as $item_key => $item ) {
			$product = $item['data'];
			$items[] = array(
				'key'        => $item_key,
				'product_id' => $item['product_id'],
				'variation_id' => isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0,
				'quantity'   => (int) $item['quantity'],
				'line_total' => isset( $item['line_total'] ) ? (float) $item['line_total'] : 0,
				'line_tax'   => isset( $item['line_tax'] ) ? (float) $item['line_tax'] : 0,
				'name'       => $product ? $product->get_name() : '',
			);
		}

		$totals = array(
			'subtotal'     => (float) $cart->get_subtotal(),
			'subtotal_tax' => (float) $cart->get_subtotal_tax(),
			'discount_total' => (float) $cart->get_discount_total(),
			'discount_tax' => (float) $cart->get_discount_tax(),
			'shipping_total' => (float) $cart->get_shipping_total(),
			'shipping_tax' => (float) $cart->get_shipping_tax(),
			'cart_contents_total' => (float) $cart->get_cart_contents_total(),
			'cart_contents_tax' => (float) $cart->get_cart_contents_tax(),
			'total'        => (float) $cart->get_total( 'edit' ),
			'total_tax'    => (float) $cart->get_total_tax(),
		);

		$coupons = array_values( $cart->get_applied_coupons() );

		return array(
			'items'   => $items,
			'totals'  => $totals,
			'coupons' => $coupons,
		);
	}

	/**
	 * Insert or update abandoned cart row. Enforce: track only if cart has at least 1 item; update last_activity_at.
	 */
	private function upsert_cart() {
		if ( ! class_exists( 'CRO_Database' ) || ! CRO_Database::table_exists( self::get_table_name() ) ) {
			return;
		}

		$session_key = $this->get_session_key();
		if ( $session_key === '' ) {
			return;
		}

		$cart = WC()->cart;
		if ( $cart->is_empty() ) {
			return;
		}

		$cart_hash  = $cart->get_cart_hash();
		$cart_json  = wp_json_encode( $this->build_cart_payload() );
		$currency   = get_woocommerce_currency();
		$now        = current_time( 'mysql' );

		$user_id = get_current_user_id();
		$email   = null;
		if ( $user_id ) {
			$user = get_userdata( $user_id );
			$email = $user && ! empty( $user->user_email ) ? $user->user_email : null;
		}

		global $wpdb;
		$table = self::get_table_name();

		$cache_key_existing = 'meyvora_cro_' . md5( serialize( array( 'abandoned_cart_session_snapshot', $table, $session_key ) ) );
		$existing           = wp_cache_get( $cache_key_existing, 'meyvora_cro' );
		if ( false === $existing ) {
			$existing = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT id, user_id, email, status FROM %i WHERE session_key = %s LIMIT 1',
					$table,
					$session_key
				),
				ARRAY_A
			);
			wp_cache_set( $cache_key_existing, $existing, 'meyvora_cro', 300 );
		}

		$data = array(
			'session_key'       => $session_key,
			'user_id'           => $user_id ? $user_id : null,
			'email'             => $email,
			'cart_hash'         => $cart_hash,
			'cart_json'         => $cart_json,
			'currency'          => $currency,
			'last_activity_at'  => $now,
			'updated_at'        => $now,
		);

		// Preserve status unless we're creating (default active).
		if ( $existing ) {
			$data['status'] = in_array( $existing['status'], array( self::STATUS_ACTIVE, self::STATUS_RECOVERED, self::STATUS_EMAILED, self::STATUS_UNSUBSCRIBED ), true )
				? $existing['status']
				: self::STATUS_ACTIVE;
			// When logged in, refresh user_id and email on update.
			if ( $user_id ) {
				$data['user_id'] = $user_id;
				$data['email']   = $email;
			}
			$upd = $wpdb->update( $table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
				$data,
				array( 'session_key' => $session_key ),
				array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%s' )
			);
			if ( false !== $upd ) {
				if ( class_exists( 'CRO_Database' ) ) {
					CRO_Database::invalidate_table_cache_after_write( $table );
				}
				self::flush_meyvora_cro_read_cache();
			}
		} else {
			$data['status'] = self::STATUS_ACTIVE;
			$data['created_at'] = $now;
			$ins = $wpdb->insert( $table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
				$data,
				array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
			if ( false !== $ins ) {
				if ( class_exists( 'CRO_Database' ) ) {
					CRO_Database::invalidate_table_cache_after_write( $table );
				}
				self::flush_meyvora_cro_read_cache();
			}
			$new_id = (int) $wpdb->insert_id;
			if ( $new_id > 0 && function_exists( 'do_action' ) ) {
				do_action( 'cro_abandoned_cart_created', $new_id );
			}
		}

		$row_id = $existing ? (int) $existing['id'] : (int) $wpdb->insert_id;
		if ( $row_id && class_exists( 'CRO_Abandoned_Cart_Reminder' ) ) {
			CRO_Abandoned_Cart_Reminder::schedule_reminder_if_needed( $row_id );
		}
	}

	/**
	 * Update email for current session (e.g. when guest provides email at checkout).
	 * Call from checkout or other code when email is captured.
	 *
	 * @param string $email Email address.
	 * @return bool True if updated.
	 */
	public static function update_email_for_session( $email ) {
		return self::update_email_and_consent_for_session( $email, true );
	}

	/**
	 * Update email and consent for current session. Only stores email when consent is true.
	 *
	 * @param string $email   Email address.
	 * @param bool   $consent Whether the user opted in to reminder emails.
	 * @return bool True if updated.
	 */
	public static function update_email_and_consent_for_session( $email, $consent ) {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return false;
		}
		$session_key = WC()->session->get_customer_id();
		if ( ! is_string( $session_key ) || $session_key === '' ) {
			return false;
		}
		$consent = (bool) $consent;
		// Only store email when consent given. When consent false, only update email_consent flag.
		$email_to_store = ( $consent && is_email( $email ) ) ? sanitize_email( $email ) : null;
		global $wpdb;
		$table = self::get_table_name();
		if ( ! CRO_Database::table_exists( $table ) ) {
			return false;
		}
		$data = array(
			'email_consent' => $consent ? 1 : 0,
			'updated_at'    => current_time( 'mysql' ),
		);
		$format = array( '%d', '%s' );
		if ( $consent && $email_to_store ) {
			$data['email'] = $email_to_store;
			$format[]      = '%s';
		}
		$rows = $wpdb->update( $table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
			$data,
			array( 'session_key' => $session_key ),
			$format,
			array( '%s' )
		);
		if ( false !== $rows ) {
			if ( class_exists( 'CRO_Database' ) ) {
				CRO_Database::invalidate_table_cache_after_write( $table );
			}
			self::flush_meyvora_cro_read_cache();
		}
		return $rows !== false;
	}

	/**
	 * Find an abandoned cart record matching the order: by session_key, then user_id, then email + cart_hash.
	 * Only returns rows that are not already recovered.
	 *
	 * @param string     $session_key Current session key (from WC session at checkout).
	 * @param int|null   $user_id     Order customer id.
	 * @param string     $email       Order billing email.
	 * @param string     $cart_hash   Cart hash at checkout (optional; used with email).
	 * @return object|null Abandoned cart row or null.
	 */
	public static function find_abandoned_cart_for_order( $session_key, $user_id, $email, $cart_hash = '' ) {
		global $wpdb;
		$table = self::get_table_name();
		if ( ! CRO_Database::table_exists( $table ) ) {
			return null;
		}
		$recovered = self::STATUS_RECOVERED;

		// 1) Match by session_key.
		if ( is_string( $session_key ) && $session_key !== '' ) {
			$cache_key_sess = 'meyvora_cro_' . md5( serialize( array( 'abandoned_cart_order_by_session', $table, $session_key, $recovered ) ) );
			$row            = wp_cache_get( $cache_key_sess, 'meyvora_cro' );
			if ( false === $row ) {
				$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
					$wpdb->prepare(
						'SELECT * FROM %i WHERE session_key = %s AND status != %s LIMIT 1',
						$table,
						$session_key,
						$recovered
					),
					OBJECT
				);
				wp_cache_set( $cache_key_sess, $row, 'meyvora_cro', 300 );
			}
			if ( $row ) {
				return $row;
			}
		}

		// 2) Match by user_id (logged-in).
		if ( $user_id > 0 ) {
			$cache_key_user = 'meyvora_cro_' . md5( serialize( array( 'abandoned_cart_order_by_user', $table, $user_id, $recovered ) ) );
			$row            = wp_cache_get( $cache_key_user, 'meyvora_cro' );
			if ( false === $row ) {
				$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
					$wpdb->prepare(
						'SELECT * FROM %i WHERE user_id = %d AND status != %s ORDER BY last_activity_at DESC LIMIT 1',
						$table,
						$user_id,
						$recovered
					),
					OBJECT
				);
				wp_cache_set( $cache_key_user, $row, 'meyvora_cro', 300 );
			}
			if ( $row ) {
				return $row;
			}
		}

		// 3) Match by email + cart_hash.
		if ( is_email( $email ) && $cart_hash !== '' ) {
			$email_s = sanitize_email( $email );
			$cache_key_eh = 'meyvora_cro_' . md5( serialize( array( 'abandoned_cart_order_by_email_hash', $table, $email_s, $cart_hash, $recovered ) ) );
			$row          = wp_cache_get( $cache_key_eh, 'meyvora_cro' );
			if ( false === $row ) {
				$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
					$wpdb->prepare(
						'SELECT * FROM %i WHERE email = %s AND cart_hash = %s AND status != %s LIMIT 1',
						$table,
						$email_s,
						$cart_hash,
						$recovered
					),
					OBJECT
				);
				wp_cache_set( $cache_key_eh, $row, 'meyvora_cro', 300 );
			}
			if ( $row ) {
				return $row;
			}
		}

		// 3b) Match by email only if no cart_hash (e.g. cart already emptied).
		if ( is_email( $email ) ) {
			$email_s = sanitize_email( $email );
			$cache_key_em = 'meyvora_cro_' . md5( serialize( array( 'abandoned_cart_order_by_email', $table, $email_s, $recovered ) ) );
			$row          = wp_cache_get( $cache_key_em, 'meyvora_cro' );
			if ( false === $row ) {
				$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
					$wpdb->prepare(
						'SELECT * FROM %i WHERE email = %s AND status != %s ORDER BY last_activity_at DESC LIMIT 1',
						$table,
						$email_s,
						$recovered
					),
					OBJECT
				);
				wp_cache_set( $cache_key_em, $row, 'meyvora_cro', 300 );
			}
			if ( $row ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Mark abandoned cart as recovered by id. Sets status = recovered, recovered_at = now.
	 *
	 * @param int $id Abandoned cart row id.
	 * @return bool True if updated.
	 */
	public static function mark_recovered_by_id( $id ) {
		if ( $id <= 0 ) {
			return false;
		}
		global $wpdb;
		$table = self::get_table_name();
		$rows  = $wpdb->update( $table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
			array(
				'status'       => self::STATUS_RECOVERED,
				'recovered_at' => current_time( 'mysql' ),
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
		if ( false !== $rows ) {
			if ( class_exists( 'CRO_Database' ) ) {
				CRO_Database::invalidate_table_cache_after_write( $table );
			}
			self::flush_meyvora_cro_read_cache();
		}
		return $rows > 0;
	}

	/**
	 * Cancel any scheduled reminder actions for this abandoned cart id.
	 * Uses Action Scheduler when available (WooCommerce); hook name cro_abandoned_cart_reminder, group cro_abandoned_cart.
	 *
	 * @param int $abandoned_cart_id Abandoned cart row id.
	 */
	public static function cancel_scheduled_reminders( $abandoned_cart_id ) {
		if ( $abandoned_cart_id <= 0 ) {
			return;
		}
		$hook  = 'cro_abandoned_cart_reminder';
		$group = 'cro_abandoned_cart';
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			foreach ( array( 1, 2, 3 ) as $n ) {
				as_unschedule_all_actions( $hook, array( $abandoned_cart_id, $n ), $group );
			}
		}
	}

	/**
	 * Mark cart as recovered (order placed). Call from order creation.
	 *
	 * @param string $session_key Session key that placed the order.
	 * @return bool True if updated.
	 */
	public static function mark_recovered( $session_key ) {
		if ( ! is_string( $session_key ) || $session_key === '' ) {
			return false;
		}
		global $wpdb;
		$table = self::get_table_name();
		$rows  = $wpdb->update( $table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
			array(
				'status'       => self::STATUS_RECOVERED,
				'recovered_at' => current_time( 'mysql' ),
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'session_key' => $session_key ),
			array( '%s', '%s', '%s' ),
			array( '%s' )
		);
		if ( false !== $rows ) {
			if ( class_exists( 'CRO_Database' ) ) {
				CRO_Database::invalidate_table_cache_after_write( $table );
			}
			self::flush_meyvora_cro_read_cache();
		}
		return $rows > 0;
	}

	/**
	 * Get record by session key.
	 *
	 * @param string $session_key Session key.
	 * @return object|null Row or null.
	 */
	public static function get_by_session( $session_key ) {
		if ( ! is_string( $session_key ) || $session_key === '' ) {
			return null;
		}
		global $wpdb;
		$table = self::get_table_name();
		$cache_key = 'meyvora_cro_' . md5( serialize( array( 'abandoned_cart_by_session', $table, $session_key ) ) );
		$row       = wp_cache_get( $cache_key, 'meyvora_cro' );
		if ( false === $row ) {
			$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT * FROM %i WHERE session_key = %s LIMIT 1',
					$table,
					$session_key
				),
				OBJECT
			);
			wp_cache_set( $cache_key, $row, 'meyvora_cro', 300 );
		}
		return $row;
	}

	/**
	 * Get abandoned cart row by id (for admin).
	 *
	 * @param int $id Row id.
	 * @return object|null
	 */
	public static function get_row_by_id( $id ) {
		if ( $id <= 0 ) {
			return null;
		}
		global $wpdb;
		$table = self::get_table_name();
		if ( ! class_exists( 'CRO_Database' ) || ! CRO_Database::table_exists( $table ) ) {
			return null;
		}
		$cache_key = 'meyvora_cro_' . md5( serialize( array( 'abandoned_cart_row_by_id', $table, $id ) ) );
		$row       = wp_cache_get( $cache_key, 'meyvora_cro' );
		if ( false === $row ) {
			$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT * FROM %i WHERE id = %d LIMIT 1',
					$table,
					$id
				),
				OBJECT
			);
			wp_cache_set( $cache_key, $row, 'meyvora_cro', 300 );
		}
		return $row;
	}

	/**
	 * Get paginated list of abandoned carts for admin.
	 *
	 * @param array $args status_filter (all|active|emailed|recovered), segment (all|high|standard), search (email like), per_page, page (1-based).
	 * @return array{ items: object[], total: int }
	 */
	public static function get_list( array $args = array() ) {
		global $wpdb;
		$table = self::get_table_name();
		if ( ! class_exists( 'CRO_Database' ) || ! CRO_Database::table_exists( $table ) ) {
			return array( 'items' => array(), 'total' => 0 );
		}
		$status_filter = isset( $args['status_filter'] ) ? sanitize_text_field( $args['status_filter'] ) : 'all';
		$search        = isset( $args['search'] ) ? trim( sanitize_text_field( $args['search'] ) ) : '';
		$per_page      = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 20;
		$page          = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset        = ( $page - 1 ) * $per_page;

		$status_filter = in_array( $status_filter, array( 'all', 'active', 'recovered', 'emailed' ), true )
			? $status_filter
			: 'all';
		$search_like = $search !== '' ? '%' . $wpdb->esc_like( $search ) . '%' : '';

		$segment = isset( $args['segment'] ) ? sanitize_key( $args['segment'] ) : 'all';
		$segment = in_array( $segment, array( 'all', 'high', 'standard' ), true ) ? $segment : 'all';
		$threshold = 100.0;
		if ( function_exists( 'cro_settings' ) ) {
			$threshold = max( 0.0, (float) cro_settings()->get( 'abandoned_cart', 'high_value_threshold', 100 ) );
		}

		// SQL must be literal in prepare() for static analysis (Plugin Check / PHPCS); do not assign to a variable first.
		$cache_key_total = 'meyvora_cro_' . md5( serialize( array( 'abandoned_cart_admin_list_total', $table, $status_filter, $search_like, $segment, $threshold ) ) );
		$total           = wp_cache_get( $cache_key_total, 'meyvora_cro' );
		if ( false === $total ) {
			$total = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE ( %s = 'all'
					OR ( %s = 'active' AND status = %s )
					OR ( %s = 'recovered' AND status = %s )
					OR ( %s = 'emailed' AND ( email_1_sent_at IS NOT NULL OR email_2_sent_at IS NOT NULL OR email_3_sent_at IS NOT NULL ) )
				) AND ( %s = '' OR email LIKE %s )
				AND ( %s = 'all'
					OR ( %s = 'high' AND CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(cart_json, '$.totals.total')),'0') AS DECIMAL(12,2)) >= %f )
					OR ( %s = 'standard' AND CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(cart_json, '$.totals.total')),'0') AS DECIMAL(12,2)) < %f )
				)",
					$table,
					$status_filter,
					$status_filter,
					self::STATUS_ACTIVE,
					$status_filter,
					self::STATUS_RECOVERED,
					$status_filter,
					$search_like,
					$search_like,
					$segment,
					$segment,
					$threshold,
					$segment,
					$threshold
				)
			);
			wp_cache_set( $cache_key_total, $total, 'meyvora_cro', 300 );
		} else {
			$total = (int) $total;
		}

		$cache_key_items = 'meyvora_cro_' . md5( serialize( array( 'abandoned_cart_admin_list_items', $table, $status_filter, $search_like, $segment, $threshold, $per_page, $offset ) ) );
		$items           = wp_cache_get( $cache_key_items, 'meyvora_cro' );
		if ( false === $items ) {
			$items = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					"SELECT * FROM %i WHERE ( %s = 'all'
					OR ( %s = 'active' AND status = %s )
					OR ( %s = 'recovered' AND status = %s )
					OR ( %s = 'emailed' AND ( email_1_sent_at IS NOT NULL OR email_2_sent_at IS NOT NULL OR email_3_sent_at IS NOT NULL ) )
				) AND ( %s = '' OR email LIKE %s )
				AND ( %s = 'all'
					OR ( %s = 'high' AND CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(cart_json, '$.totals.total')),'0') AS DECIMAL(12,2)) >= %f )
					OR ( %s = 'standard' AND CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(cart_json, '$.totals.total')),'0') AS DECIMAL(12,2)) < %f )
				) ORDER BY last_activity_at DESC LIMIT %d OFFSET %d",
					$table,
					$status_filter,
					$status_filter,
					self::STATUS_ACTIVE,
					$status_filter,
					self::STATUS_RECOVERED,
					$status_filter,
					$search_like,
					$search_like,
					$segment,
					$segment,
					$threshold,
					$segment,
					$threshold,
					$per_page,
					$offset
				),
				OBJECT
			);
			if ( ! is_array( $items ) ) {
				$items = array();
			}
			wp_cache_set( $cache_key_items, $items, 'meyvora_cro', 300 );
		} else {
			$items = is_array( $items ) ? $items : array();
		}

		return array( 'items' => $items ? $items : array(), 'total' => $total );
	}

	/**
	 * Handle one-click unsubscribe from cart recovery emails (GDPR / CAN-SPAM).
	 * Validates a signed token, sets cart status to 'unsubscribed', and redirects.
	 */
	public static function handle_unsubscribe_request() {
		$action = filter_input( INPUT_GET, 'cro_action', FILTER_UNSAFE_RAW );
		$action = is_string( $action ) ? sanitize_key( $action ) : '';
		if ( $action !== 'unsubscribe_cart' ) {
			return;
		}

		$cart_id = filter_input( INPUT_GET, 'cart_id', FILTER_VALIDATE_INT );
		$cart_id = $cart_id ? absint( $cart_id ) : 0;
		$token_raw = filter_input( INPUT_GET, 'token', FILTER_UNSAFE_RAW );
		$token     = is_string( $token_raw ) ? sanitize_text_field( wp_unslash( $token_raw ) ) : '';

		if ( ! $cart_id || ! $token ) {
			return;
		}

		global $wpdb;
		$table = self::get_table_name();

		if ( ! class_exists( 'CRO_Database' ) || ! CRO_Database::table_exists( $table ) ) {
			return;
		}

		$cache_key_unsub = 'meyvora_cro_' . md5( serialize( array( 'abandoned_cart_unsub_lookup', $table, $cart_id ) ) );
		$cart            = wp_cache_get( $cache_key_unsub, 'meyvora_cro' );
		if ( false === $cart ) {
			$cart = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT id, email FROM %i WHERE id = %d',
					$table,
					$cart_id
				)
			);
			wp_cache_set( $cache_key_unsub, $cart, 'meyvora_cro', 300 );
		}

		if ( ! $cart ) {
			return;
		}

		$expected = wp_hash( $cart->email . '|' . $cart->id . '|unsubscribe' );
		if ( ! hash_equals( $expected, $token ) ) {
			return;
		}

		$unsub = $wpdb->update( $table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
			array( 'status' => self::STATUS_UNSUBSCRIBED, 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $cart_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		if ( false !== $unsub ) {
			if ( class_exists( 'CRO_Database' ) ) {
				CRO_Database::invalidate_table_cache_after_write( $table );
			}
			self::flush_meyvora_cro_read_cache();
		}

		self::cancel_scheduled_reminders( $cart_id );

		wp_safe_redirect( add_query_arg( 'cro_unsubscribed', '1', home_url( '/' ) ) );
		exit;
	}

	/**
	 * Show a front-end confirmation banner after unsubscribing.
	 */
	public static function unsubscribe_notice() {
		$flag = filter_input( INPUT_GET, 'cro_unsubscribed', FILTER_UNSAFE_RAW );
		if ( ! is_string( $flag ) || sanitize_key( $flag ) !== '1' ) {
			return;
		}
		echo '<div style="background:#d4edda;color:#155724;padding:12px 20px;text-align:center;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;font-size:14px;">'
			. esc_html__( "You've been unsubscribed from cart recovery emails.", 'meyvora-convert' )
			. '</div>';
	}
}
