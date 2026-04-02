<?php
/**
 * Outbound webhooks: structured events, HMAC signing, async delivery, retries, logs.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MEYVC_Webhook
 */
class MEYVC_Webhook {

	const SETTINGS_GROUP   = 'webhooks';
	const ENDPOINTS_KEY    = 'endpoints';
	const DELIVERY_LOG_KEY = 'delivery_logs';
	const SCHEMA_VERSION   = '1.0';
	const HOOK_DELIVER     = 'meyvc_deliver_webhook';

	/** @var string Object cache group for read-through DB queries. */
	private const DB_READ_CACHE_GROUP = 'meyvora_meyvc';

	/** @var int Read-through TTL (seconds). */
	private const DB_READ_CACHE_TTL = 300;

	/**
	 * @param string                    $descriptor 2–4 word slug.
	 * @param array<int|string|float> $params     Params.
	 * @return string
	 */
	private static function read_cache_key( string $descriptor, array $params ): string {
		return 'meyvora_meyvc_' . md5( $descriptor . '_' . implode( '_', array_map( 'strval', $params ) ) );
	}

	/** @var string[] */
	public static function event_names() {
		$defaults = array(
			'meyvora.campaign.impression',
			'meyvora.campaign.conversion',
			'meyvora.campaign.dismiss',
			'meyvora.offer.applied',
			'meyvora.offer.converted',
			'meyvora.abandoned_cart.created',
			'meyvora.abandoned_cart.recovered',
			'meyvora.abandoned_cart.email_sent',
			'meyvora.ab_test.winner_decided',
			'meyvora.email.captured',
		);
		return (array) apply_filters( 'meyvc_webhook_event_names', $defaults );
	}

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'meyvc_event_tracked', array( __CLASS__, 'on_event_tracked' ), 20, 1 );
		add_action( 'meyvc_email_captured', array( __CLASS__, 'on_email_captured' ), 20, 3 );
		add_action( 'meyvc_ab_test_winner_found', array( __CLASS__, 'on_ab_winner_found' ), 20, 2 );
		add_action( 'meyvc_abandoned_cart_created', array( __CLASS__, 'on_abandoned_cart_created' ), 20, 1 );
		add_action( 'meyvc_abandoned_cart_recovered', array( __CLASS__, 'on_abandoned_cart_recovered' ), 20, 2 );
		add_action( 'meyvc_abandoned_cart_email_sent', array( __CLASS__, 'on_abandoned_cart_email_sent' ), 20, 2 );
		add_action( 'woocommerce_applied_coupon', array( __CLASS__, 'on_wc_applied_coupon' ), 20, 1 );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'on_offer_order_placed' ), 20, 1 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_offer_order_placed' ), 20, 1 );
		add_action( self::HOOK_DELIVER, array( __CLASS__, 'run_deliver_scheduled' ), 10, 4 );
		add_action( 'wp_ajax_meyvc_save_webhook_endpoint', array( __CLASS__, 'ajax_save_endpoint' ) );
		add_action( 'wp_ajax_meyvc_delete_webhook_endpoint', array( __CLASS__, 'ajax_delete_endpoint' ) );
		add_action( 'wp_ajax_meyvc_toggle_webhook_endpoint', array( __CLASS__, 'ajax_toggle_endpoint' ) );
		add_action( 'wp_ajax_meyvc_test_webhook_endpoint', array( __CLASS__, 'ajax_test_endpoint' ) );
		add_action( 'wp_ajax_meyvc_get_webhook_logs', array( __CLASS__, 'ajax_get_logs' ) );
	}

	/**
	 * Load endpoint definitions.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_endpoints() {
		if ( ! function_exists( 'meyvc_settings' ) ) {
			return array();
		}
		$raw = meyvc_settings()->get( self::SETTINGS_GROUP, self::ENDPOINTS_KEY, array() );
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : array();
		}
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Persist endpoints.
	 *
	 * @param array $endpoints List of endpoint objects.
	 */
	public static function save_endpoints( array $endpoints ) {
		if ( function_exists( 'meyvc_settings' ) ) {
			meyvc_settings()->set( self::SETTINGS_GROUP, self::ENDPOINTS_KEY, array_values( $endpoints ) );
		}
	}

	/**
	 * Delivery logs keyed by endpoint id.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public static function get_delivery_logs() {
		if ( ! function_exists( 'meyvc_settings' ) ) {
			return array();
		}
		$raw = meyvc_settings()->get( self::SETTINGS_GROUP, self::DELIVERY_LOG_KEY, array() );
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : array();
		}
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Append a log line (max 50 per endpoint). No PII / no body.
	 *
	 * @param string $endpoint_id Endpoint UUID.
	 * @param string $event_name  Event name.
	 * @param int    $status      HTTP status or 0.
	 * @param int    $ms          Round-trip ms.
	 * @param string $error       Short error (optional).
	 */
	public static function append_delivery_log( $endpoint_id, $event_name, $status, $ms, $error = '' ) {
		$endpoint_id = sanitize_text_field( (string) $endpoint_id );
		if ( $endpoint_id === '' ) {
			return;
		}
		$logs       = self::get_delivery_logs();
		$entry      = array(
			't'       => time(),
			'event'   => sanitize_text_field( (string) $event_name ),
			'status'  => (int) $status,
			'ms'      => (int) $ms,
			'error'   => $error !== '' ? sanitize_text_field( substr( $error, 0, 500 ) ) : '',
		);
		$bucket     = isset( $logs[ $endpoint_id ] ) && is_array( $logs[ $endpoint_id ] ) ? $logs[ $endpoint_id ] : array();
		array_unshift( $bucket, $entry );
		$logs[ $endpoint_id ] = array_slice( $bucket, 0, 50 );
		if ( function_exists( 'meyvc_settings' ) ) {
			meyvc_settings()->set( self::SETTINGS_GROUP, self::DELIVERY_LOG_KEY, $logs );
		}
	}

	/**
	 * Whether an endpoint subscribes to an event (exact or wildcard suffix .*).
	 *
	 * @param string   $event_name Full event e.g. meyvora.campaign.impression.
	 * @param string[] $patterns   Patterns from endpoint.
	 */
	public static function event_matches( $event_name, array $patterns ) {
		foreach ( $patterns as $p ) {
			$p = (string) $p;
			if ( $p === '' ) {
				continue;
			}
			if ( $p === $event_name ) {
				return true;
			}
			if ( strlen( $p ) > 2 && substr( $p, -2 ) === '.*' ) {
				$prefix = substr( $p, 0, -1 );
				if ( strpos( $event_name, $prefix ) === 0 ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Queue webhook deliveries for all matching endpoints.
	 *
	 * @param string $event_name   Full meyvora.* name.
	 * @param array  $payload      Event-specific payload (inside envelope).
	 */
	public static function dispatch( $event_name, array $payload ) {
		$event_name = sanitize_text_field( (string) $event_name );
		if ( $event_name === '' ) {
			return;
		}
		$payload = apply_filters( 'meyvc_webhook_inner_payload', $payload, $event_name );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		foreach ( self::get_endpoints() as $ep ) {
			if ( empty( $ep['active'] ) ) {
				continue;
			}
			$id = isset( $ep['id'] ) ? (string) $ep['id'] : '';
			if ( $id === '' ) {
				continue;
			}
			$events = isset( $ep['events'] ) && is_array( $ep['events'] ) ? $ep['events'] : array();
			if ( ! self::event_matches( $event_name, $events ) ) {
				continue;
			}
			self::schedule_deliver( $id, $event_name, $payload, 0, 0 );
		}
	}

	/**
	 * Schedule async delivery (Action Scheduler or WP-Cron).
	 *
	 * @param string $endpoint_id Endpoint id.
	 * @param string $event_name  Event.
	 * @param array  $inner       Payload.
	 * @param int    $attempt     Retry attempt index 0..3.
	 * @param int    $delay_sec   Delay before run.
	 */
	public static function schedule_deliver( $endpoint_id, $event_name, array $inner, $attempt = 0, $delay_sec = 0 ) {
		$ts   = time() + max( 0, (int) $delay_sec );
		$args = array( $endpoint_id, $event_name, $inner, (int) $attempt );
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( $ts, self::HOOK_DELIVER, $args, 'meyvora-convert-webhooks' );
		} else {
			wp_schedule_single_event( $ts, self::HOOK_DELIVER, $args );
		}
	}

	/**
	 * Cron / AS callback.
	 *
	 * @param string $endpoint_id Endpoint id.
	 * @param string $event_name  Event name.
	 * @param array  $inner       Inner payload.
	 * @param int    $attempt     Attempt index.
	 */
	public static function run_deliver_scheduled( $endpoint_id, $event_name, $inner, $attempt = 0 ) {
		if ( ! is_array( $inner ) ) {
			$inner = array();
		}
		self::deliver( (string) $endpoint_id, (string) $event_name, $inner, (int) $attempt );
	}

	/**
	 * Perform HTTP delivery with retries.
	 *
	 * @param string $endpoint_id Endpoint id.
	 * @param string $event_name  Event name.
	 * @param array  $inner       Inner payload.
	 * @param int    $attempt     0 = first try; up to 3 retries (4 HTTP attempts total).
	 * @return bool True on 2xx.
	 */
	public static function deliver( $endpoint_id, $event_name, array $inner, $attempt = 0 ) {
		$ep = self::find_endpoint( $endpoint_id );
		if ( ! $ep || empty( $ep['url'] ) || empty( $ep['secret'] ) ) {
			return false;
		}
		$url    = esc_url_raw( (string) $ep['url'] );
		$secret = (string) $ep['secret'];

		$body_arr = apply_filters( 'meyvc_webhook_envelope', self::build_envelope( $event_name, $inner ), $event_name, $inner );
		if ( ! is_array( $body_arr ) ) {
			$body_arr = self::build_envelope( $event_name, $inner );
		}
		$json = wp_json_encode( $body_arr );
		if ( ! is_string( $json ) ) {
			$json = '{}';
		}

		$sig      = hash_hmac( 'sha256', $json, $secret );
		$start    = microtime( true );
		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout'     => 15,
				'blocking'    => true,
				'headers'     => array(
					'Content-Type'        => 'application/json',
					'X-Meyvora-Signature' => 'sha256=' . $sig,
					'X-Meyvora-Event'     => $event_name,
				),
				'body'        => $json,
				'data_format' => 'body',
				'sslverify'   => true,
			)
		);
		$ms = (int) round( ( microtime( true ) - $start ) * 1000 );

		$code = 0;
		$err  = '';
		if ( is_wp_error( $response ) ) {
			$err = $response->get_error_message();
		} else {
			$code = (int) wp_remote_retrieve_response_code( $response );
		}

		$ok = $code >= 200 && $code < 300;
		self::append_delivery_log( $endpoint_id, $event_name, $code ? $code : ( $err ? -1 : 0 ), $ms, $err );

		if ( ! $ok && $attempt < 3 ) {
			$backoffs = array( 30, 120, 300 );
			$wait     = isset( $backoffs[ $attempt ] ) ? (int) $backoffs[ $attempt ] : 300;
			self::schedule_deliver( $endpoint_id, $event_name, $inner, $attempt + 1, $wait );
		}

		return $ok;
	}

	/**
	 * Synchronous deliver (e.g. test ping). No automatic retries here.
	 *
	 * @param string $endpoint_id Endpoint id.
	 * @param string $event_name  Event.
	 * @param array  $inner       Inner payload.
	 * @return array{ ok: bool, status: int, ms: int, error: string }
	 */
	public static function deliver_sync_once( $endpoint_id, $event_name, array $inner ) {
		$ep = self::find_endpoint( $endpoint_id );
		if ( ! $ep || empty( $ep['url'] ) || empty( $ep['secret'] ) ) {
			return array( 'ok' => false, 'status' => 0, 'ms' => 0, 'error' => __( 'Endpoint not found.', 'meyvora-convert' ) );
		}
		$url    = esc_url_raw( (string) $ep['url'] );
		$secret = (string) $ep['secret'];

		$body_arr = apply_filters( 'meyvc_webhook_envelope', self::build_envelope( $event_name, $inner ), $event_name, $inner );
		if ( ! is_array( $body_arr ) ) {
			$body_arr = self::build_envelope( $event_name, $inner );
		}
		$json = wp_json_encode( $body_arr );
		if ( ! is_string( $json ) ) {
			$json = '{}';
		}
		$sig      = hash_hmac( 'sha256', $json, $secret );
		$start    = microtime( true );
		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout'     => 15,
				'blocking'    => true,
				'headers'     => array(
					'Content-Type'        => 'application/json',
					'X-Meyvora-Signature' => 'sha256=' . $sig,
					'X-Meyvora-Event'     => $event_name,
				),
				'body'        => $json,
				'data_format' => 'body',
				'sslverify'   => true,
			)
		);
		$ms = (int) round( ( microtime( true ) - $start ) * 1000 );

		if ( is_wp_error( $response ) ) {
			$msg = $response->get_error_message();
			self::append_delivery_log( $endpoint_id, $event_name, -1, $ms, $msg );
			return array( 'ok' => false, 'status' => 0, 'ms' => $ms, 'error' => $msg );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$ok   = $code >= 200 && $code < 300;
		self::append_delivery_log( $endpoint_id, $event_name, $code, $ms, $ok ? '' : __( 'Non-success HTTP status.', 'meyvora-convert' ) );
		return array(
			'ok'     => $ok,
			'status' => $code,
			'ms'     => $ms,
			'error'  => $ok ? '' : __( 'Non-success HTTP status.', 'meyvora-convert' ),
		);
	}

	/**
	 * @param string $event_name Event.
	 * @param array  $inner      Payload.
	 * @return array<string, mixed>
	 */
	public static function build_envelope( $event_name, array $inner ) {
		return array(
			'event'     => $event_name,
			'version'   => self::SCHEMA_VERSION,
			'timestamp' => gmdate( 'c' ),
			'site_url'  => get_site_url(),
			'payload'   => $inner,
		);
	}

	/**
	 * @param string $id Endpoint id.
	 * @return array<string, mixed>|null
	 */
	private static function find_endpoint( $id ) {
		foreach ( self::get_endpoints() as $ep ) {
			if ( isset( $ep['id'] ) && (string) $ep['id'] === (string) $id ) {
				return $ep;
			}
		}
		return null;
	}

	/**
	 * @param array $event meyvc_event_tracked payload.
	 */
	public static function on_event_tracked( $event ) {
		if ( ! is_array( $event ) ) {
			return;
		}
		$source_type = isset( $event['source_type'] ) ? (string) $event['source_type'] : '';

		if ( $source_type === 'offer' ) {
			$db_type = isset( $event['event_type'] ) ? (string) $event['event_type'] : '';
			if ( $db_type === 'conversion' ) {
				$inner = self::build_offer_converted_payload( $event );
				self::dispatch( 'meyvora.offer.converted', $inner );
			}
			return;
		}

		if ( $source_type !== 'campaign' ) {
			return;
		}

		$db_type         = isset( $event['event_type'] ) ? (string) $event['event_type'] : '';
		$conversion_type = isset( $event['conversion_type'] ) ? (string) $event['conversion_type'] : '';

		if ( $db_type === 'conversion' && $conversion_type === 'email_capture' ) {
			$inner = self::build_email_captured_payload_from_event( $event );
			self::dispatch( 'meyvora.email.captured', $inner );
			return;
		}

		if ( $db_type === 'impression' ) {
			self::dispatch( 'meyvora.campaign.impression', self::build_campaign_payload_from_event( $event ) );
			return;
		}
		if ( $db_type === 'dismiss' ) {
			self::dispatch( 'meyvora.campaign.dismiss', self::build_campaign_payload_from_event( $event ) );
			return;
		}
		if ( $db_type === 'conversion' ) {
			self::dispatch( 'meyvora.campaign.conversion', self::build_campaign_payload_from_event( $event ) );
		}
	}

	/**
	 * @param string     $email                Email (not forwarded to webhook).
	 * @param int|array  $campaign_id_or_data  Campaign ID or legacy full context array.
	 * @param array      $data                 Extra context when second arg is campaign ID.
	 */
	public static function on_email_captured( $email, $campaign_id_or_data = null, $data = null ) {
		$campaign_id = 0;
		$extra       = array();
		if ( is_array( $campaign_id_or_data ) ) {
			$extra       = $campaign_id_or_data;
			$campaign_id = isset( $extra['campaign_id'] ) ? absint( $extra['campaign_id'] ) : 0;
		} else {
			$campaign_id = absint( $campaign_id_or_data );
			$extra       = is_array( $data ) ? $data : array();
		}
		$cname       = '';
		if ( $campaign_id && class_exists( 'MEYVC_Campaign' ) ) {
			$c = MEYVC_Campaign::get( $campaign_id );
			if ( is_array( $c ) && ! empty( $c['name'] ) ) {
				$cname = (string) $c['name'];
			}
		}
		self::dispatch(
			'meyvora.email.captured',
			array(
				'source_campaign_id'   => $campaign_id,
				'source_campaign_name' => $cname,
				'page_url'             => isset( $extra['page_url'] ) ? esc_url_raw( (string) $extra['page_url'] ) : '',
				'cart_value'           => isset( $extra['cart_value'] ) ? (float) $extra['cart_value'] : null,
				'has_email'            => true,
			)
		);
	}

	/**
	 * @param object $test  Test row.
	 * @param array  $stats Stats from MEYVC_AB_Statistics::calculate.
	 */
	public static function on_ab_winner_found( $test, $stats ) {
		if ( ! is_object( $test ) || ! is_array( $stats ) ) {
			return;
		}
		if ( empty( $stats['has_winner'] ) || empty( $stats['winner'] ) || ! is_array( $stats['winner'] ) ) {
			return;
		}
		$w = $stats['winner'];
		$total_imp = 0;
		$total_cv  = 0;
		if ( ! empty( $test->variations ) && is_array( $test->variations ) ) {
			foreach ( $test->variations as $v ) {
				$total_imp += isset( $v->impressions ) ? (int) $v->impressions : 0;
				$total_cv  += isset( $v->conversions ) ? (int) $v->conversions : 0;
			}
		}
		self::dispatch(
			'meyvora.ab_test.winner_decided',
			array(
				'test_id'               => isset( $test->id ) ? (int) $test->id : 0,
				'test_name'             => isset( $test->name ) ? sanitize_text_field( (string) $test->name ) : '',
				'winner_variation_id'   => isset( $w['variation_id'] ) ? (int) $w['variation_id'] : 0,
				'winner_name'           => isset( $w['variation_name'] ) ? sanitize_text_field( (string) $w['variation_name'] ) : '',
				'confidence_level'      => isset( $stats['confidence_level'] ) ? (int) $stats['confidence_level'] : 0,
				'total_impressions'     => $total_imp,
				'total_conversions'     => $total_cv,
			)
		);
	}

	/**
	 * @param int $cart_id Row id.
	 */
	public static function on_abandoned_cart_created( $cart_id ) {
		$row = self::get_abandoned_row( (int) $cart_id );
		if ( ! $row ) {
			return;
		}
		self::dispatch( 'meyvora.abandoned_cart.created', self::build_abandoned_cart_payload( $row ) );
	}

	/**
	 * @param int $cart_id Row id.
	 * @param int $order_id WC order id.
	 */
	public static function on_abandoned_cart_recovered( $cart_id, $order_id ) {
		$row = self::get_abandoned_row( (int) $cart_id );
		if ( ! $row ) {
			return;
		}
		$inner                    = self::build_abandoned_cart_payload( $row );
		$inner['recovery_order_id'] = absint( $order_id );
		self::dispatch( 'meyvora.abandoned_cart.recovered', $inner );
	}

	/**
	 * @param int $cart_id      Row id.
	 * @param int $email_number 1–3.
	 */
	public static function on_abandoned_cart_email_sent( $cart_id, $email_number ) {
		$row = self::get_abandoned_row( (int) $cart_id );
		if ( ! $row ) {
			return;
		}
		$inner               = self::build_abandoned_cart_payload( $row );
		$inner['email_number'] = max( 1, min( 3, (int) $email_number ) );
		self::dispatch( 'meyvora.abandoned_cart.email_sent', $inner );
	}

	/**
	 * @param string $coupon_code Coupon code.
	 */
	public static function on_wc_applied_coupon( $coupon_code ) {
		$coupon_code = is_string( $coupon_code ) ? trim( $coupon_code ) : '';
		if ( $coupon_code === '' || ! function_exists( 'wc_get_coupon_id_by_code' ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		$coupon_id = wc_get_coupon_id_by_code( $coupon_code );
		if ( ! $coupon_id ) {
			return;
		}
		$offer_id = get_post_meta( $coupon_id, '_meyvc_offer_id', true );
		$offer_id = $offer_id !== '' && $offer_id !== false ? absint( $offer_id ) : 0;
		if ( $offer_id <= 0 ) {
			return;
		}
		$offer_name = '';
		if ( class_exists( 'MEYVC_Offer_Model' ) ) {
			$o = MEYVC_Offer_Model::get( $offer_id );
			if ( is_object( $o ) && isset( $o->name ) ) {
				$offer_name = (string) $o->name;
			}
		}
		$cart       = WC()->cart;
		$cart_total = (float) $cart->get_total( 'edit' );
		$discount   = 0.0;
		if ( is_callable( array( $cart, 'get_coupon_discount_totals' ) ) ) {
			$totals = $cart->get_coupon_discount_totals();
			if ( is_array( $totals ) && isset( $totals[ $coupon_code ] ) ) {
				$discount = (float) $totals[ $coupon_code ];
			}
		}
		$visitor_id = '';
		if ( class_exists( 'MEYVC_Visitor_State' ) ) {
			$vs = MEYVC_Visitor_State::get_instance();
			if ( method_exists( $vs, 'get_visitor_id' ) ) {
				$visitor_id = (string) $vs->get_visitor_id();
			}
		}
		$uid = get_current_user_id();
		self::dispatch(
			'meyvora.offer.applied',
			array(
				'offer_id'         => $offer_id,
				'offer_name'       => $offer_name,
				'coupon_code'      => sanitize_text_field( $coupon_code ),
				'cart_total'       => $cart_total,
				'discount_amount'  => $discount,
				'user_id'          => $uid > 0 ? $uid : null,
				'visitor_id'       => $visitor_id !== '' ? $visitor_id : null,
				'order_id'         => null,
			)
		);
	}

	/**
	 * When an order is paid/completed, dispatch meyvora.offer.converted for Meyvora offer coupons (MYV- prefix).
	 *
	 * @param int $order_id Order ID.
	 */
	public static function on_offer_order_placed( $order_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$order_id = absint( $order_id );
		if ( $order_id < 1 ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$done = $order->get_meta( '_meyvc_meyvora_offer_conv_codes', true );
		$done = is_array( $done ) ? $done : array();
		$any  = false;
		foreach ( $order->get_coupon_codes() as $code ) {
			$code = is_string( $code ) ? trim( $code ) : '';
			if ( $code === '' || stripos( $code, 'myv-' ) !== 0 ) {
				continue;
			}
			$ck = strtolower( $code );
			if ( ! empty( $done[ $ck ] ) ) {
				continue;
			}
			$offer_id = 0;
			$offer_name = '';
			global $wpdb;
			if ( class_exists( 'MEYVC_Database' ) ) {
				$logs_table = MEYVC_Database::get_table( 'offer_logs' );
				$ck         = self::read_cache_key( 'offer_id_by_coupon', array( $logs_table, $code ) );
				$found      = false;
				$row        = wp_cache_get( $ck, self::DB_READ_CACHE_GROUP, false, $found );
				if ( ! $found ) {
					$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
						$wpdb->prepare(
							'SELECT offer_id FROM %i WHERE coupon_code = %s ORDER BY id DESC LIMIT 1',
							$logs_table,
							$code
						)
					);
					wp_cache_set( $ck, $row, self::DB_READ_CACHE_GROUP, self::DB_READ_CACHE_TTL );
				}
				if ( $row && isset( $row->offer_id ) ) {
					$offer_id = (int) $row->offer_id;
				}
			}
			if ( $offer_id > 0 && class_exists( 'MEYVC_Offer_Model' ) ) {
				$om = MEYVC_Offer_Model::get( $offer_id );
				if ( is_object( $om ) && isset( $om->name ) ) {
					$offer_name = (string) $om->name;
				}
			}
			self::dispatch(
				'meyvora.offer.converted',
				array(
					'offer_id'        => $offer_id,
					'offer_name'      => sanitize_text_field( $offer_name ),
					'coupon_code'     => sanitize_text_field( $code ),
					'cart_total'      => (float) $order->get_total(),
					'discount_amount' => (float) $order->get_discount_total(),
					'user_id'         => $order->get_user_id() ?: null,
					'visitor_id'      => '',
					'order_id'        => $order_id,
				)
			);
			$done[ $ck ] = 1;
			$any         = true;
		}
		if ( $any ) {
			$order->update_meta_data( '_meyvc_meyvora_offer_conv_codes', $done );
			$order->save();
		}
	}

	/**
	 * @param array $event Tracked event row merge.
	 * @return array<string, mixed>
	 */
	private static function build_campaign_payload_from_event( array $event ) {
		$campaign_id = isset( $event['campaign_id'] ) ? absint( $event['campaign_id'] ) : ( isset( $event['source_id'] ) ? absint( $event['source_id'] ) : 0 );
		$meta        = array();
		if ( ! empty( $event['metadata'] ) ) {
			$meta = maybe_unserialize( $event['metadata'] );
		}
		$meta = is_array( $meta ) ? $meta : array();

		$cname         = '';
		$ctype         = '';
		$template_type = '';
		if ( $campaign_id && class_exists( 'MEYVC_Campaign' ) ) {
			$c = MEYVC_Campaign::get( $campaign_id );
			if ( is_array( $c ) ) {
				$cname         = isset( $c['name'] ) ? (string) $c['name'] : '';
				$ctype         = isset( $c['campaign_type'] ) ? (string) $c['campaign_type'] : '';
				$template_type = isset( $c['template_type'] ) ? (string) $c['template_type'] : '';
			}
		}

		$page_type   = isset( $meta['page_type'] ) ? sanitize_text_field( (string) $meta['page_type'] ) : '';
		$device_type = isset( $meta['device'] ) ? sanitize_text_field( (string) $meta['device'] ) : ( isset( $meta['device_type'] ) ? sanitize_text_field( (string) $meta['device_type'] ) : '' );
		if ( $device_type === '' && isset( $event['device_type'] ) ) {
			$device_type = sanitize_text_field( (string) $event['device_type'] );
		}

		$page_url = isset( $event['page_url'] ) ? esc_url_raw( (string) $event['page_url'] ) : ( isset( $meta['page_url'] ) ? esc_url_raw( (string) $meta['page_url'] ) : '' );

		$uid = isset( $event['user_id'] ) && $event['user_id'] !== null ? absint( $event['user_id'] ) : 0;

		$order_id         = isset( $event['order_id'] ) && $event['order_id'] !== null ? absint( $event['order_id'] ) : null;
		$conversion_value = null;
		if ( isset( $event['order_value'] ) && $event['order_value'] !== null && $event['order_value'] !== '' ) {
			$conversion_value = (float) $event['order_value'];
		} elseif ( isset( $meta['revenue'] ) ) {
			$conversion_value = (float) $meta['revenue'];
		}

		return array(
			'campaign_id'      => $campaign_id,
			'campaign_name'    => $cname,
			'campaign_type'    => $ctype,
			'template_type'    => $template_type,
			'page_url'         => $page_url,
			'page_type'        => $page_type,
			'device_type'      => $device_type,
			'session_id'       => isset( $event['session_id'] ) ? sanitize_text_field( (string) $event['session_id'] ) : '',
			'user_id'          => $uid > 0 ? $uid : null,
			'conversion_value' => $conversion_value,
			'order_id'         => $order_id > 0 ? $order_id : null,
		);
	}

	/**
	 * @param array $event Event row.
	 * @return array<string, mixed>
	 */
	private static function build_email_captured_payload_from_event( array $event ) {
		$campaign_id = isset( $event['campaign_id'] ) ? absint( $event['campaign_id'] ) : ( isset( $event['source_id'] ) ? absint( $event['source_id'] ) : 0 );
		$meta        = array();
		if ( ! empty( $event['metadata'] ) ) {
			$meta = maybe_unserialize( $event['metadata'] );
		}
		$meta = is_array( $meta ) ? $meta : array();

		$cname = '';
		if ( $campaign_id && class_exists( 'MEYVC_Campaign' ) ) {
			$c = MEYVC_Campaign::get( $campaign_id );
			if ( is_array( $c ) && ! empty( $c['name'] ) ) {
				$cname = (string) $c['name'];
			}
		}
		$cart_val = null;
		if ( isset( $meta['cart_value'] ) ) {
			$cart_val = (float) $meta['cart_value'];
		} elseif ( isset( $meta['cart_total'] ) ) {
			$cart_val = (float) $meta['cart_total'];
		}
		return array(
			'source_campaign_id'   => $campaign_id,
			'source_campaign_name' => $cname,
			'page_url'             => isset( $event['page_url'] ) ? esc_url_raw( (string) $event['page_url'] ) : ( isset( $meta['page_url'] ) ? esc_url_raw( (string) $meta['page_url'] ) : '' ),
			'cart_value'           => $cart_val,
			'has_email'            => true,
		);
	}

	/**
	 * @param array $event Tracked offer conversion.
	 * @return array<string, mixed>
	 */
	private static function build_offer_converted_payload( array $event ) {
		$offer_id = isset( $event['source_id'] ) ? absint( $event['source_id'] ) : 0;
		$name     = '';
		if ( $offer_id && class_exists( 'MEYVC_Offer_Model' ) ) {
			$o = MEYVC_Offer_Model::get( $offer_id );
			if ( is_object( $o ) && isset( $o->name ) ) {
				$name = (string) $o->name;
			}
		}
		$meta = array();
		if ( ! empty( $event['metadata'] ) ) {
			$meta = maybe_unserialize( $event['metadata'] );
		}
		$meta = is_array( $meta ) ? $meta : array();

		$coupon = isset( $meta['coupon_code'] ) ? sanitize_text_field( (string) $meta['coupon_code'] ) : '';
		if ( $coupon === '' && isset( $event['coupon_code'] ) ) {
			$coupon = sanitize_text_field( (string) $event['coupon_code'] );
		}

		$cart_total = isset( $meta['cart_total'] ) ? (float) $meta['cart_total'] : null;
		$discount   = isset( $meta['discount_amount'] ) ? (float) $meta['discount_amount'] : null;
		if ( isset( $event['order_value'] ) && $event['order_value'] !== null && $event['order_value'] !== '' ) {
			$cart_total = (float) $event['order_value'];
		}

		$uid = isset( $event['user_id'] ) && $event['user_id'] !== null ? absint( $event['user_id'] ) : 0;
		$vid = isset( $event['session_id'] ) ? sanitize_text_field( (string) $event['session_id'] ) : '';

		$order_id = isset( $event['order_id'] ) && $event['order_id'] !== null ? absint( $event['order_id'] ) : null;

		return array(
			'offer_id'        => $offer_id,
			'offer_name'      => $name,
			'coupon_code'     => $coupon,
			'cart_total'      => $cart_total,
			'discount_amount' => $discount,
			'user_id'         => $uid > 0 ? $uid : null,
			'visitor_id'      => $vid !== '' ? $vid : null,
			'order_id'        => $order_id > 0 ? $order_id : null,
		);
	}

	/**
	 * @param object $row DB row.
	 * @return array<string, mixed>
	 */
	private static function build_abandoned_cart_payload( $row ) {
		$data = isset( $row->cart_json ) ? json_decode( (string) $row->cart_json, true ) : null;
		$data = is_array( $data ) ? $data : array();
		$items_out = array();
		$item_count = 0;
		if ( ! empty( $data['items'] ) && is_array( $data['items'] ) ) {
			foreach ( $data['items'] as $it ) {
				++$item_count;
				$items_out[] = array(
					'name'     => isset( $it['name'] ) ? sanitize_text_field( (string) $it['name'] ) : '',
					'quantity' => isset( $it['quantity'] ) ? (int) $it['quantity'] : 0,
					'price'    => isset( $it['line_total'] ) ? (float) $it['line_total'] : 0.0,
				);
			}
		}
		$total = isset( $data['totals']['total'] ) ? (float) $data['totals']['total'] : 0.0;
		$uid   = isset( $row->user_id ) ? absint( $row->user_id ) : 0;
		$has_email = ! empty( $row->email ) && is_string( $row->email ) && $row->email !== '';

		return array(
			'cart_id'     => isset( $row->id ) ? (int) $row->id : 0,
			'cart_total'  => $total,
			'currency'    => isset( $row->currency ) ? sanitize_text_field( (string) $row->currency ) : '',
			'item_count'  => $item_count,
			'items'       => $items_out,
			'email'       => null,
			'has_email'   => (bool) $has_email,
			'user_id'     => $uid > 0 ? $uid : null,
		);
	}

	/**
	 * @param int $id Row id.
	 * @return object|null
	 */
	private static function get_abandoned_row( $id ) {
		if ( $id <= 0 || ! class_exists( 'MEYVC_Abandoned_Cart_Tracker' ) ) {
			return null;
		}
		return MEYVC_Abandoned_Cart_Tracker::get_row_by_id( $id );
	}

	// ——— Admin AJAX ———

	/**
	 * Verify capability and nonce.
	 *
	 * @param string $nonce_action Nonce action.
	 * @return bool
	 */
	/**
	 * Validate webhook URL; warn on non-HTTPS.
	 *
	 * @param string $url Raw URL.
	 * @return array{ ok: bool, url: string, warning: string }
	 */
	public static function validate_endpoint_url( $url ) {
		$url = esc_url_raw( trim( (string) $url ) );
		if ( $url === '' ) {
			return array( 'ok' => false, 'url' => '', 'warning' => '' );
		}
		$valid = filter_var( $url, FILTER_VALIDATE_URL );
		if ( ! $valid ) {
			return array( 'ok' => false, 'url' => '', 'warning' => '' );
		}
		$warning = '';
		if ( strpos( strtolower( $url ), 'https://' ) !== 0 ) {
			$warning = __( 'Using a non-HTTPS URL is not recommended.', 'meyvora-convert' );
		}
		return array( 'ok' => true, 'url' => $url, 'warning' => $warning );
	}

	public static function ajax_save_endpoint() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'meyvora-convert' ) ), 403 );
		}
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'meyvc_webhooks_admin' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'meyvora-convert' ) ), 403 );
		}
		$id          = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$url_raw     = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		$secret      = isset( $_POST['secret'] ) ? sanitize_text_field( wp_unslash( $_POST['secret'] ) ) : '';
		$active      = ! empty( $_POST['active'] );
		$events = array();
		if ( isset( $_POST['events_json'] ) ) {
			$events_json_raw = sanitize_textarea_field( wp_unslash( (string) $_POST['events_json'] ) );
			$decoded         = json_decode( $events_json_raw, true );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $e ) {
					$e = sanitize_text_field( (string) $e );
					if ( $e !== '' ) {
						$events[] = $e;
					}
				}
			}
		} else {
			$events_in = isset( $_POST['events'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['events'] ) ) : array();
			if ( is_array( $events_in ) ) {
				foreach ( $events_in as $e ) {
					$e = sanitize_text_field( (string) $e );
					if ( $e !== '' ) {
						$events[] = $e;
					}
				}
			}
		}
		$v = self::validate_endpoint_url( $url_raw );
		if ( ! $v['ok'] ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid URL.', 'meyvora-convert' ) ), 400 );
		}
		$list   = self::get_endpoints();
		$is_new = ( $id === '' );
		if ( $is_new && strlen( $secret ) < 16 ) {
			wp_send_json_error( array( 'message' => __( 'Secret must be at least 16 characters.', 'meyvora-convert' ) ), 400 );
		}
		if ( $is_new ) {
			$id = wp_generate_uuid4();
			$list[] = array(
				'id'     => $id,
				'url'    => $v['url'],
				'secret' => $secret,
				'active' => $active,
				'events' => $events,
			);
		} else {
			$found = false;
			foreach ( $list as $i => $ep ) {
				if ( isset( $ep['id'] ) && (string) $ep['id'] === $id ) {
					$list[ $i ]['url']    = $v['url'];
					$list[ $i ]['active'] = $active;
					$list[ $i ]['events'] = $events;
					if ( strlen( $secret ) >= 16 ) {
						$list[ $i ]['secret'] = $secret;
					}
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				wp_send_json_error( array( 'message' => __( 'Endpoint not found.', 'meyvora-convert' ) ), 404 );
			}
		}
		self::save_endpoints( $list );
		wp_send_json_success(
			array(
				'id'      => $id,
				'warning' => $v['warning'],
				'message' => __( 'Saved.', 'meyvora-convert' ),
			)
		);
	}

	public static function ajax_delete_endpoint() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'meyvora-convert' ) ), 403 );
		}
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'meyvc_webhooks_admin' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'meyvora-convert' ) ), 403 );
		}
		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		if ( $id === '' ) {
			wp_send_json_error( array( 'message' => __( 'Invalid id.', 'meyvora-convert' ) ), 400 );
		}
		$list = array();
		foreach ( self::get_endpoints() as $ep ) {
			if ( isset( $ep['id'] ) && (string) $ep['id'] !== $id ) {
				$list[] = $ep;
			}
		}
		self::save_endpoints( $list );
		$logs = self::get_delivery_logs();
		unset( $logs[ $id ] );
		if ( function_exists( 'meyvc_settings' ) ) {
			meyvc_settings()->set( self::SETTINGS_GROUP, self::DELIVERY_LOG_KEY, $logs );
		}
		wp_send_json_success( array( 'message' => __( 'Deleted.', 'meyvora-convert' ) ) );
	}

	public static function ajax_toggle_endpoint() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'meyvora-convert' ) ), 403 );
		}
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'meyvc_webhooks_admin' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'meyvora-convert' ) ), 403 );
		}
		$id     = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$active = ! empty( $_POST['active'] );
		if ( $id === '' ) {
			wp_send_json_error( array( 'message' => __( 'Invalid id.', 'meyvora-convert' ) ), 400 );
		}
		$list = self::get_endpoints();
		foreach ( $list as $i => $ep ) {
			if ( isset( $ep['id'] ) && (string) $ep['id'] === $id ) {
				$list[ $i ]['active'] = $active;
				self::save_endpoints( $list );
				wp_send_json_success( array( 'active' => $active ) );
			}
		}
		wp_send_json_error( array( 'message' => __( 'Endpoint not found.', 'meyvora-convert' ) ), 404 );
	}

	public static function ajax_test_endpoint() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'meyvora-convert' ) ), 403 );
		}
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'meyvc_webhooks_admin' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'meyvora-convert' ) ), 403 );
		}
		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		if ( $id === '' ) {
			wp_send_json_error( array( 'message' => __( 'Invalid id.', 'meyvora-convert' ) ), 400 );
		}
		$result = self::deliver_sync_once(
			$id,
			'meyvora.test',
			array( 'message' => __( 'Connection OK', 'meyvora-convert' ) )
		);
		wp_send_json_success( $result );
	}

	public static function ajax_get_logs() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'meyvora-convert' ) ), 403 );
		}
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'meyvc_webhooks_admin' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'meyvora-convert' ) ), 403 );
		}
		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		if ( $id === '' ) {
			wp_send_json_error( array( 'message' => __( 'Invalid id.', 'meyvora-convert' ) ), 400 );
		}
		$logs = self::get_delivery_logs();
		$rows = isset( $logs[ $id ] ) && is_array( $logs[ $id ] ) ? $logs[ $id ] : array();
		$out  = array();
		foreach ( $rows as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$t = isset( $r['t'] ) ? (int) $r['t'] : 0;
			$r['t_display'] = $t > 0 ? wp_date( 'Y-m-d H:i:s', $t ) : '';
			$out[]          = $r;
		}
		wp_send_json_success( array( 'logs' => $out ) );
	}

	/**
	 * Mask URL for display.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function mask_url( $url ) {
		$url = (string) $url;
		if ( strlen( $url ) <= 25 ) {
			return $url;
		}
		return substr( $url, 0, 25 ) . '…';
	}
}

