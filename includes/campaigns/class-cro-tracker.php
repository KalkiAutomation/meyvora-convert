<?php
/**
 * Campaign tracking
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Campaign tracker class.
 */
class CRO_Tracker {

	/**
	 * Initialize tracking hooks.
	 */
	public function __construct() {
		add_action( 'wp_ajax_cro_track_event', array( $this, 'track_event' ) );
		add_action( 'wp_ajax_nopriv_cro_track_event', array( $this, 'track_event' ) );
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
		if ( $result && class_exists( 'CRO_AB_Test' ) ) {
			$this->maybe_update_ab_tables( $event_type, $event_data );
		}
		return $result;
	}

	/**
	 * Track a booster event (sticky ATC, shipping progress, trust badge view).
	 * Stores in the existing cro_events table as event_type 'interaction' with event name in metadata.
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
		check_ajax_referer( 'cro-track-event', 'nonce' );

		// Rate limit by IP to prevent abuse (30 requests per 60 seconds per IP).
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		if ( class_exists( 'CRO_Security' ) && ! CRO_Security::check_rate_limit( 'cro_track_' . $ip, 30, 60 ) ) {
			wp_send_json_error( array( 'message' => __( 'Rate limit exceeded.', 'meyvora-convert' ) ), 429 );
		}

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( wp_unslash( $_POST['campaign_id'] ) ) : 0;
		$event_type  = isset( $_POST['event_type'] ) ? sanitize_text_field( wp_unslash( $_POST['event_type'] ) ) : '';
		$raw_source  = isset( $_POST['source_type'] ) ? sanitize_text_field( wp_unslash( $_POST['source_type'] ) ) : 'campaign';
		$source_type = in_array( $raw_source, self::SOURCE_TYPES, true ) ? $raw_source : 'campaign';
		$source_id   = $source_type === 'campaign' ? $campaign_id : 0;

		$raw_event_data = isset( $_POST['event_data'] ) ? $_POST['event_data'] : array();
		if ( is_string( $raw_event_data ) ) {
			$raw_event_data = json_decode( wp_unslash( $raw_event_data ), true );
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

		if ( $result && class_exists( 'CRO_AB_Test' ) ) {
			$this->maybe_update_ab_tables( $event_type, $event_data );
		}
		if ( $result && $source_type === 'campaign' && $campaign_id > 0 ) {
			$click_events = array( 'conversion', 'email_capture', 'email_captured', 'cta_click' );
			if ( in_array( $event_type, $click_events, true ) && class_exists( 'CRO_Visitor_State' ) ) {
				$visitor = CRO_Visitor_State::get_instance();
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
	 * Save event to database (cro_events table).
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

		$table_name = $wpdb->prefix . 'cro_events';

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
		$event_type   = sanitize_text_field( (string) $event_type );
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

		$result = $wpdb->insert( $table_name, $insert_data );

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
			do_action( 'cro_event_tracked', $event );
		}

		return false !== $result;
	}

	/**
	 * Get or create session ID.
	 * Avoids session_start() in REST/CLI where it can cause headers-already-sent or strict errors.
	 *
	 * @return string
	 */
	private function get_session_id() {
		if ( ! headers_sent() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			if ( ! session_id() ) {
				@session_start(); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			}
			if ( isset( $_SESSION['cro_session_id'] ) ) {
				return sanitize_text_field( (string) $_SESSION['cro_session_id'] );
			}
			$_SESSION['cro_session_id'] = wp_generate_uuid4();
			return (string) $_SESSION['cro_session_id'];
		}
		// REST / CLI: use cookie or transient keyed by IP so events from same visitor group.
		$key = 'cro_sid_' . ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
		$sid = get_transient( $key );
		if ( $sid !== false && is_string( $sid ) ) {
			return $sid;
		}
		$sid = wp_generate_uuid4();
		set_transient( $key, $sid, 3600 );
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
		if ( ! class_exists( 'CRO_AB_Test' ) ) {
			return;
		}
		$ab_model = new CRO_AB_Test();
		if ( $event_type === 'impression' ) {
			$ab_model->record_impression( $variation_id );
			return;
		}
		if ( $event_type === 'conversion' ) {
			$revenue = isset( $event_data['revenue'] ) ? (float) $event_data['revenue'] : 0.0;
			$ab_model->record_conversion( $variation_id, $revenue );
		}
	}
}
