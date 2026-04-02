<?php
/**
 * Campaign tracking
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Campaign tracker class.
 */
class MEYVC_Tracker {

	/**
	 * Initialize tracking hooks.
	 */
	public function __construct() {
		add_action( 'wp_ajax_meyvc_track_event', array( $this, 'track_event' ) );
		add_action( 'wp_ajax_nopriv_meyvc_track_event', array( $this, 'track_event' ) );
		add_action( 'wp_ajax_meyvc_record_dismiss', array( $this, 'record_dismiss' ) );
		add_action( 'wp_ajax_nopriv_meyvc_record_dismiss', array( $this, 'record_dismiss' ) );
		add_action( 'wp_ajax_meyvc_decide_fallback', array( $this, 'decide_fallback' ) );
		add_action( 'wp_ajax_nopriv_meyvc_decide_fallback', array( $this, 'decide_fallback' ) );
	}

	/** Allowed source types for events. */
	const SOURCE_TYPES = array( 'campaign', 'sticky_cart', 'shipping_bar', 'trust_badge', 'offer' );

	/** Booster event types (stored as interaction with event name in metadata). */
	const BOOSTER_EVENT_TYPES = array(
		'booster_sticky_atc_click',
		'booster_shipping_progress_reached',
		'booster_trust_badge_view',
	);

	/**
	 * Track an event (called by REST API or other code).
	 *
	 * Optional event_data keys: ab_test_id, variation_id (for A/B reporting), revenue (for conversion).
	 * When event_type is impression or conversion and variation_id is set, AB tables are updated too.
	 *
	 * @param string $event_type   Event type (e.g. impression, dismiss, conversion, interaction, sticky_cart_add).
	 * @param int    $campaign_id  Campaign ID (can be 0 when source_type is not campaign).
	 * @param array  $event_data   Optional extra data (page_url, timestamp, ab_test_id, variation_id, revenue, etc.).
	 * @param string $source_type  Optional. One of campaign, sticky_cart, shipping_bar, trust_badge, offer. Default campaign.
	 * @param int    $source_id    Optional. Source ID (defaults to campaign_id when source_type is campaign).
	 * @return bool True if saved, false otherwise.
	 */
	public function track( $event_type, $campaign_id, $event_data = array(), $source_type = 'campaign', $source_id = null ) {
		$event_type  = sanitize_text_field( (string) $event_type );
		$campaign_id = absint( $campaign_id );
		$source_type = in_array( $source_type, self::SOURCE_TYPES, true ) ? $source_type : 'campaign';
		$source_id   = $source_id !== null ? absint( $source_id ) : ( $source_type === 'campaign' ? $campaign_id : 0 );
		if ( ! $event_type ) {
			return false;
		}
		if ( $source_type === 'campaign' && ! $campaign_id ) {
			return false;
		}
		if ( $source_type === 'offer' && $source_id <= 0 ) {
			return false;
		}
		$event_data = $this->sanitize_event_data( is_array( $event_data ) ? $event_data : array() );
		$result = $this->save_event( $campaign_id, $event_type, $event_data, $source_type, $source_id );
		if ( $result && class_exists( 'MEYVC_AB_Test' ) ) {
			$this->maybe_update_ab_tables( $event_type, $event_data );
		}
		return $result;
	}

	/**
	 * Track a booster event (sticky ATC, shipping progress, trust badge view).
	 * Stores in the existing meyvc_events table as event_type 'interaction' with event name in metadata.
	 *
	 * @param string $event_name One of: booster_sticky_atc_click, booster_shipping_progress_reached, booster_trust_badge_view.
	 * @param array  $context    Optional. Keys: source_type (sticky_cart|shipping_bar|trust_badge), source_id (int), plus any extra data (e.g. page_url) merged into event_data.
	 * @return bool True if saved, false otherwise.
	 */
	public static function track_booster_event( $event_name, $context = array() ) {
		$event_name = sanitize_text_field( (string) $event_name );
		if ( ! in_array( $event_name, self::BOOSTER_EVENT_TYPES, true ) ) {
			return false;
		}
		$context = is_array( $context ) ? $context : array();
		$source_type = isset( $context['source_type'] ) && in_array( $context['source_type'], self::SOURCE_TYPES, true )
			? $context['source_type']
			: self::infer_booster_source_type( $event_name );
		$source_id = isset( $context['source_id'] ) ? absint( $context['source_id'] ) : 0;
		$event_data = $context;
		unset( $event_data['source_type'], $event_data['source_id'] );
		$tracker = new self();
		return $tracker->track( $event_name, 0, $event_data, $source_type, $source_id );
	}

	/**
	 * Infer source_type from booster event name.
	 *
	 * @param string $event_name Booster event type.
	 * @return string One of SOURCE_TYPES (non-campaign).
	 */
	private static function infer_booster_source_type( $event_name ) {
		if ( strpos( $event_name, 'sticky' ) !== false ) {
			return 'sticky_cart';
		}
		if ( strpos( $event_name, 'shipping' ) !== false ) {
			return 'shipping_bar';
		}
		if ( strpos( $event_name, 'trust_badge' ) !== false ) {
			return 'trust_badge';
		}
		return 'sticky_cart';
	}

	/**
	 * Track an event via AJAX.
	 */
	public function track_event() {
		check_ajax_referer( 'meyvc-track-event', 'nonce' );

		// Raw IP used intentionally for rate-limit key only — never stored; not subject to anonymise_ip setting.
		// Rate limit by IP to prevent abuse (30 requests per 60 seconds per IP).
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		if ( class_exists( 'MEYVC_Security' ) && ! MEYVC_Security::check_rate_limit( 'meyvc_track_' . $ip, 30, 60 ) ) {
			wp_send_json_error( array( 'message' => __( 'Rate limit exceeded.', 'meyvora-convert' ) ), 429 );
		}

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( wp_unslash( $_POST['campaign_id'] ) ) : 0;
		$event_type  = isset( $_POST['event_type'] ) ? sanitize_text_field( wp_unslash( $_POST['event_type'] ) ) : '';
		$raw_source  = isset( $_POST['source_type'] ) ? sanitize_text_field( wp_unslash( $_POST['source_type'] ) ) : 'campaign';
		$source_type = in_array( $raw_source, self::SOURCE_TYPES, true ) ? $raw_source : 'campaign';
		$source_id   = $source_type === 'campaign' ? $campaign_id : 0;

		// jQuery may send event_data as a nested array (not JSON string); use filter_input_array to avoid raw $_POST.
		$post_all = filter_input_array( INPUT_POST );
		if ( ! is_array( $post_all ) ) {
			$post_all = array();
		}
		$raw_event_data = array_key_exists( 'event_data', $post_all ) ? wp_unslash( $post_all['event_data'] ) : array();
		if ( is_string( $raw_event_data ) && $raw_event_data !== '' ) {
			// Decode event data; all keys and values are sanitised in sanitize_event_data() immediately below.
			$raw_event_data = json_decode( sanitize_textarea_field( $raw_event_data ), true );
		} elseif ( is_array( $raw_event_data ) ) {
			$raw_event_data = map_deep( $raw_event_data, 'sanitize_text_field' );
		} else {
			$raw_event_data = array();
		}
		$event_data = is_array( $raw_event_data ) ? $raw_event_data : array();
		$event_data = $this->sanitize_event_data( $event_data );

		if ( empty( $event_type ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'meyvora-convert' ) ) );
		}
		if ( $source_type === 'campaign' && empty( $campaign_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'meyvora-convert' ) ) );
		}

		$result = $this->save_event( $campaign_id, $event_type, $event_data, $source_type, $source_id );

		if ( $result && class_exists( 'MEYVC_AB_Test' ) ) {
			$this->maybe_update_ab_tables( $event_type, $event_data );
		}
		if ( $result && $source_type === 'campaign' && $campaign_id > 0 ) {
			$click_events = array( 'conversion', 'email_capture', 'email_captured', 'cta_click' );
			if ( in_array( $event_type, $click_events, true ) && class_exists( 'MEYVC_Visitor_State' ) ) {
				$visitor = MEYVC_Visitor_State::get_instance();
				$visitor->record_conversion( $campaign_id );
				$visitor->record_campaign_click( $campaign_id );
				$visitor->save();
			}
		}

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Event tracked.', 'meyvora-convert' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to track event.', 'meyvora-convert' ) ) );
		}
	}

	/**
	 * Save event to database (meyvc_events table).
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $event_type  Event type (impression, dismiss, conversion, email_capture, interaction, sticky_cart_add, shipping_bar_progress).
	 * @param array  $event_data  Event data (page_url, email, etc.).
	 * @param string $source_type Source: campaign, sticky_cart, shipping_bar, trust_badge, offer.
	 * @param int    $source_id   Source ID.
	 * @return bool
	 */
	private function save_event( $campaign_id, $event_type, $event_data = array(), $source_type = 'campaign', $source_id = 0 ) {
		global $wpdb;

		$raw_event_type = sanitize_text_field( (string) $event_type );

		if ( class_exists( 'MEYVC_Analytics_Filter' ) ) {
			$filter = new MEYVC_Analytics_Filter();
			if ( ! $filter->should_track() ) {
				return false;
			}
			if ( 'campaign' === $source_type && $campaign_id > 0 && 'impression' === $raw_event_type ) {
				$visitor_id = $this->get_session_id();
				$page_url   = isset( $event_data['page_url'] ) ? sanitize_text_field( wp_unslash( $event_data['page_url'] ) ) : '';
				if ( $filter->is_duplicate_impression( $campaign_id, $visitor_id, $page_url ) ) {
					return false;
				}
			}
		}

		if ( class_exists( 'MEYVC_Security' ) ) {
			$event_data['visitor_ip'] = MEYVC_Security::get_visitor_ip();
		}

		$table_name = $wpdb->prefix . 'meyvc_events';

		// Map event_type to table enum: impression, conversion, dismiss, interaction.
		$map = array(
			'impression'                      => 'impression',
			'dismiss'                         => 'dismiss',
			'conversion'                      => 'conversion',
			'email_capture'                   => 'conversion',
			'email_captured'                  => 'conversion',
			'interaction'                     => 'interaction',
			'sticky_cart_add'                 => 'interaction',
			'shipping_bar_progress'           => 'interaction',
			'booster_sticky_atc_click'        => 'interaction',
			'booster_shipping_progress_reached' => 'interaction',
			'booster_trust_badge_view'        => 'interaction',
		);
		$event_type   = $raw_event_type;
		$db_event_type = isset( $map[ $event_type ] ) ? $map[ $event_type ] : 'interaction';
		$source_type  = in_array( $source_type, self::SOURCE_TYPES, true ) ? $source_type : 'campaign';
		$source_id    = absint( $source_id );

		$user_id    = get_current_user_id();
		$session_id = $this->get_session_id();
		$page_url   = isset( $event_data['page_url'] ) ? sanitize_text_field( wp_unslash( $event_data['page_url'] ) ) : '';
		if ( strlen( $page_url ) > 500 ) {
			$page_url = substr( $page_url, 0, 500 );
		}

		$insert_data = array(
			'event_type'   => $db_event_type,
			'source_type'  => $source_type,
			'source_id'    => $source_id,
			'session_id'   => sanitize_text_field( $session_id ),
			'user_id'      => $user_id > 0 ? $user_id : null,
			'page_url'     => $page_url !== '' ? $page_url : null,
			'metadata'     => maybe_serialize( $event_data ),
		);

		if ( $db_event_type === 'conversion' && ( $event_type === 'email_capture' || $event_type === 'email_captured' ) ) {
			$insert_data['conversion_type'] = 'email_capture';
			$insert_data['email']            = isset( $event_data['email'] ) ? sanitize_email( $event_data['email'] ) : null;
		}

		// Store original event type in metadata for booster and other custom events (queryable later).
		if ( in_array( $event_type, self::BOOSTER_EVENT_TYPES, true ) ) {
			$event_data['event_name'] = $event_type;
			$insert_data['metadata']   = maybe_serialize( $event_data );
		}

		// For conversion events, persist order_id and revenue (order_value) when provided (e.g. offer attribution).
		if ( $db_event_type === 'conversion' ) {
			if ( isset( $event_data['order_id'] ) && $event_data['order_id'] !== '' ) {
				$insert_data['order_id'] = absint( $event_data['order_id'] );
			}
			if ( isset( $event_data['revenue'] ) && $event_data['revenue'] !== '' ) {
				$insert_data['order_value'] = (float) $event_data['revenue'];
			}
		}

		$result = $wpdb->insert( $table_name, $insert_data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.

		if ( false !== $result && class_exists( 'MEYVC_Database' ) ) {
			MEYVC_Database::invalidate_table_cache_after_write( $table_name );
		}

		if ( $result && function_exists( 'do_action' ) ) {
			$event = array_merge(
				array(
					'event_type'  => $event_type,
					'campaign_id' => $campaign_id,
					'source_type' => $source_type,
					'source_id'   => $source_id,
				),
				$insert_data
			);
			do_action( 'meyvc_event_tracked', $event );
		}

		return false !== $result;
	}

	/**
	 * Get or create session ID.
	 * Avoids session_start() in REST/CLI where it can cause headers-already-sent or strict errors.
	 *
	 * @return string
	 */
	/**
	 * Public session key (same cookie/transient logic as track events). Used by sequences and webhooks.
	 *
	 * @return string
	 */
	public static function client_session_id() {
		$tracker = new self();
		return $tracker->get_session_id();
	}

	private function get_session_id() {
		$cookie_name = 'meyvc_sess';

		if ( isset( $_COOKIE[ $cookie_name ] ) ) {
			$sid = sanitize_text_field( wp_unslash( (string) $_COOKIE[ $cookie_name ] ) );
			if ( preg_match( '/^[a-f0-9\-]{36}$/i', $sid ) ) {
				return $sid;
			}
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			// Raw IP used for session key derivation only — not stored in analytics tables.
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
			$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
			$key = 'meyvc_sid_' . substr( md5( $ip . '|' . $ua ), 0, 32 );
			$sid = get_transient( $key );
			if ( $sid !== false && is_string( $sid ) && preg_match( '/^[a-f0-9\-]{36}$/i', $sid ) ) {
				return $sid;
			}
			$sid = wp_generate_uuid4();
			set_transient( $key, $sid, 2 * HOUR_IN_SECONDS );
			return $sid;
		}

		$sid = wp_generate_uuid4();
		if ( ! headers_sent() ) {
			setcookie(
				$cookie_name,
				$sid,
				time() + 2 * HOUR_IN_SECONDS,
				defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
				defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
				is_ssl(),
				true
			);
		}

		return $sid;
	}

	/**
	 * Sanitize event data. ab_test_id and variation_id stored as integers; revenue as float.
	 *
	 * @param array $data Event data.
	 * @return array
	 */
	private function sanitize_event_data( $data ) {
		if ( ! is_array( $data ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $data as $key => $value ) {
			$key = is_string( $key ) ? sanitize_key( $key ) : $key;
			if ( $key === 'ab_test_id' || $key === 'variation_id' ) {
				$v = absint( $value );
				if ( $v > 0 ) {
					$sanitized[ $key ] = $v;
				}
				continue;
			}
			if ( $key === 'revenue' ) {
				$sanitized[ $key ] = (float) $value;
				continue;
			}
			if ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_event_data( $value );
			} elseif ( is_scalar( $value ) ) {
				$sanitized[ $key ] = sanitize_text_field( (string) $value );
			}
		}

		return $sanitized;
	}

	/**
	 * When event_type is impression or conversion and variation_id is present, update A/B tables.
	 * Backward compatible: no-op when variation_id is absent.
	 *
	 * @param string $event_type  Event type.
	 * @param array  $event_data  Sanitized event data (may contain variation_id, ab_test_id, revenue).
	 */
	private function maybe_update_ab_tables( $event_type, $event_data ) {
		$variation_id = isset( $event_data['variation_id'] ) ? (int) $event_data['variation_id'] : 0;
		if ( $variation_id <= 0 ) {
			return;
		}
		if ( ! class_exists( 'MEYVC_AB_Test' ) ) {
			return;
		}
		$ab_model = new MEYVC_AB_Test();
		if ( $event_type === 'impression' ) {
			$ab_model->record_impression( $variation_id );
			return;
		}
		if ( $event_type === 'conversion' ) {
			$revenue = isset( $event_data['revenue'] ) ? (float) $event_data['revenue'] : 0.0;
			$ab_model->record_conversion( $variation_id, $revenue );
		}
	}

	/**
	 * AJAX: record popup dismiss in visitor cookie; return configured fallback campaign + delay.
	 */
	public function record_dismiss() {
		check_ajax_referer( 'meyvc_public_actions', 'nonce' );

		// Raw IP used intentionally for rate-limit key only — never stored; not subject to anonymise_ip setting.
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		if ( class_exists( 'MEYVC_Security' ) && ! MEYVC_Security::check_rate_limit( 'meyvc_dismiss_' . $ip, 40, 60 ) ) {
			wp_send_json_error( array( 'message' => __( 'Rate limit exceeded.', 'meyvora-convert' ) ), 429 );
		}

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( wp_unslash( $_POST['campaign_id'] ) ) : 0;
		if ( $campaign_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid campaign.', 'meyvora-convert' ) ) );
		}

		if ( ! class_exists( 'MEYVC_Visitor_State' ) || ! class_exists( 'MEYVC_Campaign' ) || ! class_exists( 'MEYVC_Campaign_Model' ) ) {
			wp_send_json_error( array( 'message' => __( 'Service unavailable.', 'meyvora-convert' ) ) );
		}

		$visitor = MEYVC_Visitor_State::get_instance();
		$visitor->record_campaign_dismissed( $campaign_id );
		$visitor->save();

		if ( class_exists( 'MEYVC_Webhook' ) ) {
			$row = MEYVC_Campaign::get( $campaign_id );
			MEYVC_Webhook::dispatch(
				'meyvora.campaign.dismiss',
				array(
					'campaign_id'      => $campaign_id,
					'campaign_name'    => isset( $row['name'] ) ? $row['name'] : '',
					'campaign_type'    => isset( $row['campaign_type'] ) ? $row['campaign_type'] : '',
					'template_type'    => isset( $row['template_type'] ) ? $row['template_type'] : '',
					'page_url'         => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
					'page_type'        => '',
					'device_type'      => '',
					'session_id'       => '',
					'user_id'          => get_current_user_id() ? get_current_user_id() : null,
					'conversion_value' => null,
					'order_id'         => null,
				)
			);
		}

		$fb_id   = null;
		$delay   = 0;
		$row     = MEYVC_Campaign::get( $campaign_id );
		if ( is_array( $row ) ) {
			$model = MEYVC_Campaign_Model::from_db_row( $row );
			$fid   = (int) ( $model->fallback_id ?? 0 );
			if ( $fid > 0 ) {
				$fb_id = $fid;
				$delay = $model->get_fallback_delay();
			}
		}

		wp_send_json_success(
			array(
				'fallback_campaign_id'   => $fb_id,
				'fallback_delay_seconds' => $delay,
			)
		);
	}

	/**
	 * AJAX: run decision pipeline for one campaign (dismiss→fallback chain).
	 */
	public function decide_fallback() {
		check_ajax_referer( 'meyvc_public_actions', 'nonce' );

		// Raw IP used intentionally for rate-limit key only — never stored; not subject to anonymise_ip setting.
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		if ( class_exists( 'MEYVC_Security' ) && ! MEYVC_Security::check_rate_limit( 'meyvc_fb_decide_' . $ip, 30, 60 ) ) {
			wp_send_json_error( array( 'message' => __( 'Rate limit exceeded.', 'meyvora-convert' ) ), 429 );
		}

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( wp_unslash( $_POST['campaign_id'] ) ) : 0;
		if ( $campaign_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid campaign.', 'meyvora-convert' ) ) );
		}

		if ( ! class_exists( 'MEYVC_REST_API' ) ) {
			wp_send_json_error( array( 'message' => __( 'Service unavailable.', 'meyvora-convert' ) ) );
		}

		MEYVC_REST_API::ensure_decision_engine_loaded();

		if ( ! function_exists( 'meyvc_decide' ) ) {
			wp_send_json_error( array( 'message' => __( 'Decision engine not available.', 'meyvora-convert' ) ) );
		}

		$dc_raw = filter_input( INPUT_POST, 'decision_context', FILTER_UNSAFE_RAW );
		$raw    = is_string( $dc_raw ) ? sanitize_textarea_field( wp_unslash( $dc_raw ) ) : '';
		$body = array();
		if ( is_string( $raw ) && $raw !== '' ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$body = $decoded;
			}
		}

		$parsed       = MEYVC_REST_API::parse_decide_request_body( $body );
		$visitor      = MEYVC_Visitor_State::get_instance();
		$decision     = meyvc_decide()->decide(
			$parsed['context'],
			$visitor,
			$parsed['signals'],
			$parsed['trigger_type'],
			array(
				'single_campaign_id'        => $campaign_id,
				'ignore_session_popup_cap'  => true,
				'skip_pageview_shown_check' => true,
			)
		);

		$payload = array(
			'show'         => $decision->show,
			'campaign_id'  => $decision->campaign_id,
			'campaign'     => null,
			'reason'       => $decision->reason,
			'reason_code'  => $decision->reason_code,
		);

		if ( $decision->show && $decision->campaign !== null ) {
			if ( is_object( $decision->campaign ) && method_exists( $decision->campaign, 'to_frontend_array' ) ) {
				$payload['campaign'] = $decision->campaign->to_frontend_array();
			} elseif ( is_array( $decision->campaign ) ) {
				$payload['campaign'] = $decision->campaign;
			} else {
				$payload['campaign'] = array( 'id' => $decision->campaign_id );
			}
		}

		// Persist shown state to cookie so frequency rules work on subsequent requests.
		if ( $decision->show && $decision->campaign_id ) {
			$visitor->record_campaign_shown( (int) $decision->campaign_id );
			$visitor->save();
		}

		if ( $decision->show && $decision->ab_test_id !== null && $decision->variation_id !== null ) {
			$payload['ab_test_id']   = (int) $decision->ab_test_id;
			$payload['variation_id'] = (int) $decision->variation_id;
			$payload['is_control']   = (bool) $decision->is_control;
			$visitor->set_ab_attribution( (int) $decision->ab_test_id, (int) $decision->variation_id );
		}

		if ( $decision->show && $decision->variation_id !== null ) {
			MEYVC_REST_API::record_ab_impression_once( $visitor, (int) $decision->variation_id, $parsed['pageview_id'] );
		}

		wp_send_json_success( $payload );
	}
}
