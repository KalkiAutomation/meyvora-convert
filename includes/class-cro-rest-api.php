<?php
/**
 * REST API
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * REST API class.
 */
class CRO_REST_API {

	/**
	 * Initialize REST API.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_authentication_errors', array( $this, 'allow_public_routes_without_auth' ), 999 );
		add_filter( 'rest_pre_dispatch', array( $this, 'allow_public_routes_dispatch' ), 10, 3 );
	}

	/**
	 * Check if the current REST request is for a public CRO route.
	 *
	 * @param string $route Route from $request->get_route().
	 * @return bool
	 */
	private function is_public_cro_route( $route ) {
		if ( empty( $route ) || strpos( $route, '/cro/v1/' ) !== 0 ) {
			return false;
		}
		return $route === '/cro/v1/decide' || $route === '/cro/v1/track' || $route === '/cro/v1/offer'
			|| $route === '/cro/v1/offer/apply'
			|| preg_match( '#^/cro/v1/campaign/\d+$#', $route );
	}

	/**
	 * Allow unauthenticated requests to public CRO routes (decide, track, campaign by id).
	 *
	 * @param WP_Error|null|bool $result Error from another authentication handler.
	 * @return WP_Error|null|bool
	 */
	public function allow_public_routes_without_auth( $result ) {
		$path = '';
		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$path = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		}
		// Also check rest_route query param (e.g. ?rest_route=/cro/v1/decide)
		if ( ! empty( $_GET['rest_route'] ) ) {
			$path = '/' . ltrim( sanitize_text_field( wp_unslash( $_GET['rest_route'] ) ), '/' );
		}
		$path = preg_replace( '#\?.*$#', '', $path );
		$is_public = strpos( $path, 'cro/v1/decide' ) !== false
			|| strpos( $path, 'cro/v1/track' ) !== false
			|| strpos( $path, 'cro/v1/offer' ) !== false
			|| preg_match( '#cro/v1/campaign/\d+#', $path );
		if ( $is_public ) {
			// Allow unauthenticated: clear any auth error so permission_callback (__return_true) runs
			return null;
		}
		return $result;
	}

	/**
	 * If a 403 was returned for a public CRO route, allow dispatch so our callback runs.
	 *
	 * @param mixed            $result  Response to replace the requested version with.
	 * @param WP_REST_Server   $server  Server instance.
	 * @param WP_REST_Request  $request Request used to generate the response.
	 * @return mixed
	 */
	public function allow_public_routes_dispatch( $result, $server, $request ) {
		$route = $request->get_route();
		if ( ! $this->is_public_cro_route( $route ) ) {
			return $result;
		}
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;
			if ( $status === 403 ) {
				return null;
			}
		}
		return $result;
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			'meyvora-convert/v1',
			'/campaigns',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_campaigns' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			'meyvora-convert/v1',
			'/campaigns/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_campaign' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			'meyvora-convert/v1',
			'/analytics',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_analytics' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		// Store campaign data for preview (avoids URL length limits).
		register_rest_route(
			'meyvora-convert/v1',
			'/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'store_preview_data' ),
				'permission_callback' => array( $this, 'preview_permission_check' ),
				'args'                => array(
					'campaign_data' => array(
						'required'          => true,
						'type'              => 'object',
						'sanitize_callback' => null,
					),
				),
			)
		);

		register_rest_route(
			'cro/v1',
			'/decide',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'decide' ),
				'permission_callback' => '__return_true',
				'args'                => array(),
			)
		);

		register_rest_route(
			'cro/v1',
			'/campaign/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_campaign_by_id' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'cro/v1',
			'/track',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'track_event' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'cro/v1',
			'/email',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'capture_email' ),
				'permission_callback' => '__return_true',
			)
		);

		// Offer for blocks: GET returns best offer + coupon code + preview (pass/fail + reasons). Optional query params for context.
		register_rest_route(
			'cro/v1',
			'/offer',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_offer' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'cart_total'       => array( 'type' => 'number', 'minimum' => 0 ),
					'cart_items_count' => array( 'type' => 'integer', 'minimum' => 0 ),
					'is_logged_in'     => array( 'type' => 'boolean' ),
					'order_count'      => array( 'type' => 'integer', 'minimum' => 0 ),
					'lifetime_spend'   => array( 'type' => 'number', 'minimum' => 0 ),
					'user_role'        => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);

		register_rest_route(
			'cro/v1',
			'/offer/apply',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'apply_offer_coupon' ),
				'permission_callback' => array( $this, 'check_rest_nonce' ),
				'args'                => array(
					'coupon_code' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $v ) {
							return is_string( $v ) && trim( $v ) !== '';
						},
					),
				),
			)
		);
	}

	/**
	 * Check permissions.
	 *
	 * @return bool
	 */
	public function permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check permission for preview (store/load).
	 * REST API validates X-WP-Nonce (wp_rest nonce) before this runs.
	 *
	 * @return bool
	 */
	public function preview_permission_check() {
		return current_user_can( 'manage_meyvora_convert' );
	}

	/**
	 * Store campaign data in a transient and return preview_id and preview_url for use in preview links.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function store_preview_data( $request ) {
		$data = $request->get_param( 'campaign_data' );
		if ( ! is_array( $data ) ) {
			if ( is_string( $data ) ) {
				$data = json_decode( $data, true );
			}
			if ( ! is_array( $data ) ) {
				$body = $request->get_body();
				if ( ! empty( $body ) ) {
					$decoded = json_decode( $body, true );
					$data    = isset( $decoded['campaign_data'] ) ? $decoded['campaign_data'] : $decoded;
				}
			}
		}
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'invalid_data', __( 'Invalid campaign data.', 'meyvora-convert' ), array( 'status' => 400 ) );
		}
		$preview_id = 'cro_' . wp_generate_password( 16, false );
		$expiry_seconds = class_exists( 'CRO_Campaign_Display' ) ? CRO_Campaign_Display::PREVIEW_EXPIRY_SECONDS : 1800;
		$expiry_timestamp = time() + $expiry_seconds;
		set_transient( 'cro_preview_' . $preview_id, $data, $expiry_seconds );
		$token = class_exists( 'CRO_Campaign_Display' ) ? CRO_Campaign_Display::generate_preview_token( $preview_id, $expiry_timestamp ) : '';
		$base_url = home_url( '/' );
		$preview_url = add_query_arg(
			array(
				'cro_preview' => '1',
				'preview_id'  => $preview_id,
				'cro_token'   => $token,
				'cro_expiry'  => $expiry_timestamp,
			),
			$base_url
		);
		return new WP_REST_Response(
			array(
				'preview_id'   => $preview_id,
				'cro_token'    => $token,
				'cro_expiry'   => $expiry_timestamp,
				'preview_url'  => $preview_url,
			),
			200
		);
	}

	/**
	 * Get campaigns.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_campaigns( $request ) {
		$args = array(
			'status' => $request->get_param( 'status' ),
			'type'   => $request->get_param( 'type' ),
			'limit'  => $request->get_param( 'limit' ) ? intval( $request->get_param( 'limit' ) ) : -1,
		);

		$campaigns = CRO_Campaign::get_all( $args );

		return new WP_REST_Response( $campaigns, 200 );
	}

	/**
	 * Get single campaign.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_campaign( $request ) {
		$id       = intval( $request->get_param( 'id' ) );
		$campaign = CRO_Campaign::get( $id );

		if ( ! $campaign ) {
			return new WP_Error( 'not_found', __( 'Campaign not found.', 'meyvora-convert' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $campaign, 200 );
	}

	/**
	 * Get analytics.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_analytics( $request ) {
		$args = array(
			'campaign_id' => $request->get_param( 'campaign_id' ) ? intval( $request->get_param( 'campaign_id' ) ) : 0,
			'event_type'  => $request->get_param( 'event_type' ),
			'start_date'  => $request->get_param( 'start_date' ),
			'end_date'    => $request->get_param( 'end_date' ),
			'limit'       => $request->get_param( 'limit' ) ? intval( $request->get_param( 'limit' ) ) : 100,
		);

		$analytics = CRO_Analytics::get_data( $args );

		return new WP_REST_Response( $analytics, 200 );
	}

	/**
	 * Decide which campaign (if any) to show. POST /cro/v1/decide.
	 *
	 * Request body: { "signals": {...}, "behavior": {...}, "context": {...}, "trigger_type", "trigger_data", "pageview_id" }.
	 * pageview_id: optional; stable ID per page load (e.g. UUID) for A/B impression dedupe.
	 * Response: { show, campaign_id, campaign, reason, reason_code, ab_test_id?, variation_id?, is_control?, debug? }
	 *
	 * @param WP_REST_Request $request Request object (JSON body: signals, behavior, pageview_id, etc.).
	 * @return WP_REST_Response|WP_Error
	 */
	public function decide( $request ) {
		$this->ensure_decision_engine_loaded();

		if ( ! function_exists( 'cro_decide' ) ) {
			return new WP_Error(
				'cro_engine_unavailable',
				__( 'Decision engine not available.', 'meyvora-convert' ),
				array( 'status' => 503 )
			);
		}

		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = array();
		}
		$signals      = isset( $body['signals'] ) && is_array( $body['signals'] ) ? $body['signals'] : array();
		$behavior     = isset( $body['behavior'] ) && is_array( $body['behavior'] ) ? $body['behavior'] : array();
		$body_context = isset( $body['context'] ) && is_array( $body['context'] ) ? $body['context'] : array();
		$trigger_type = isset( $body['trigger_type'] ) ? sanitize_key( (string) $body['trigger_type'] ) : '';
		$trigger_data = isset( $body['trigger_data'] ) && is_array( $body['trigger_data'] ) ? $body['trigger_data'] : array();
		$pageview_id  = isset( $body['pageview_id'] ) ? sanitize_text_field( (string) $body['pageview_id'] ) : '';
		$pageview_id  = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $pageview_id );
		$pageview_id  = substr( $pageview_id, 0, 64 );

		$context = new CRO_Context();
		// Use context from the frontend (page the user is on). When decide is called via REST, the server request is the API call, not the page — so page_type etc. must come from the request body.
		foreach ( $body_context as $key => $value ) {
			$key = sanitize_key( $key );
			if ( $key !== '' ) {
				$context->set( $key, $value );
			}
		}
		// When decide is called via REST, the server request is the API call, not the visitor's page — so
		// page_type from CRO_Context constructor is wrong (e.g. "other"). Infer from Referer when
		// page_type is missing or generic so targeting (e.g. page_type_in) can match.
		$current_page_type = $context->get( 'page_type', '' );
		if ( ( $current_page_type === '' || $current_page_type === 'other' ) && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$page_type = $this->infer_page_type_from_referer( sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) );
			if ( $page_type !== '' ) {
				$context->set( 'page_type', $page_type );
			}
		}
		foreach ( $behavior as $key => $value ) {
			$path = 'behavior.' . sanitize_key( $key );
			$context->set( $path, $value );
		}
		// Merge trigger_data into context so targeting/intent can use time_on_page, scroll_depth, etc.
		if ( ! empty( $trigger_data ) ) {
			if ( isset( $trigger_data['seconds'] ) ) {
				$context->set( 'behavior.time_on_page', (int) $trigger_data['seconds'] );
			}
			if ( isset( $trigger_data['depth'] ) ) {
				$context->set( 'behavior.scroll_depth', (int) $trigger_data['depth'] );
			}
			if ( isset( $trigger_data['idle_seconds'] ) ) {
				$context->set( 'behavior.idle_seconds', (int) $trigger_data['idle_seconds'] );
			}
		}
		// For page_load, treat as time with 0 seconds so "show immediately" campaigns qualify.
		if ( $trigger_type === 'page_load' ) {
			$context->set( 'behavior.time_on_page', 0 );
		}

		$visitor_state = CRO_Visitor_State::get_instance();
		$decision     = cro_decide()->decide( $context, $visitor_state, $signals, $trigger_type );

		$payload = array(
			'show'        => $decision->show,
			'campaign_id' => $decision->campaign_id,
			'campaign'    => null,
			'reason'      => $decision->reason,
			'reason_code' => $decision->reason_code,
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

		// A/B test metadata for frontend tracking (ab_test_id, variation_id, is_control)
		if ( $decision->ab_test_id !== null && $decision->variation_id !== null ) {
			$payload['ab_test_id']   = (int) $decision->ab_test_id;
			$payload['variation_id'] = (int) $decision->variation_id;
			$payload['is_control']   = (bool) $decision->is_control;
			// Persist attribution to cookie so WooCommerce order conversion can attribute to this variation
			$visitor_state->set_ab_attribution( (int) $decision->ab_test_id, (int) $decision->variation_id );
		}

		// Record A/B impression once per pageview (REST layer = single place, no double-count)
		if ( $decision->show && $decision->variation_id !== null ) {
			$this->record_ab_impression_once( $visitor_state, (int) $decision->variation_id, $pageview_id );
		}

		$debug_mode = function_exists( 'cro_settings' ) && current_user_can( 'manage_options' ) && cro_settings()->get( 'general', 'debug_mode', false );
		if ( $debug_mode && is_object( $decision ) && method_exists( $decision, 'to_debug_array' ) ) {
			$payload['debug'] = $decision->to_debug_array();
		}

		return new WP_REST_Response( $payload, 200 );
	}

	/**
	 * Record A/B variation impression at most once per (visitor + pageview).
	 * Uses a transient keyed by visitor_id + pageview_id + variation_id to avoid double-counting
	 * when /decide is called multiple times in the same pageview (e.g. multiple triggers).
	 *
	 * @param CRO_Visitor_State $visitor_state Visitor state (for visitor_id).
	 * @param int               $variation_id Variation ID.
	 * @param string            $pageview_id  Optional. Frontend-provided pageview ID (one per page load).
	 */
	private function record_ab_impression_once( CRO_Visitor_State $visitor_state, $variation_id, $pageview_id = '' ) {
		if ( ! class_exists( 'CRO_AB_Test' ) || $variation_id <= 0 ) {
			return;
		}
		$visitor_id = $visitor_state->get_visitor_id();
		$visitor_id = is_string( $visitor_id ) ? $visitor_id : (string) $visitor_id;
		$pageview_id = is_string( $pageview_id ) ? $pageview_id : '';
		// Key: same visitor + same pageview + same variation = one impression per TTL window
		$hash = md5( $visitor_id . '|' . $pageview_id . '|' . $variation_id );
		$transient_key = 'cro_ab_imp_' . $hash;
		if ( get_transient( $transient_key ) ) {
			return;
		}
		set_transient( $transient_key, 1, 120 );
		$ab_model = new CRO_AB_Test();
		$ab_model->record_impression( $variation_id );
	}

	/**
	 * Infer page_type from Referer URL when frontend context is missing (e.g. croConfig not from CRO_Frontend).
	 *
	 * @param string $referer Referer URL (e.g. https://example.com/cart).
	 * @return string Page type: home, shop, product, product_category, cart, checkout, account, page, post, or other.
	 */
	private function infer_page_type_from_referer( $referer ) {
		if ( $referer === '' ) {
			return '';
		}
		$path = wp_parse_url( $referer, PHP_URL_PATH );
		$path = $path ? '/' . trim( $path, '/' ) : '/';
		$path_lower = strtolower( $path );
		if ( $path === '/' || $path === '' ) {
			return 'home';
		}
		if ( strpos( $path_lower, '/cart' ) !== false ) {
			return 'cart';
		}
		if ( strpos( $path_lower, '/checkout' ) !== false ) {
			return 'checkout';
		}
		if ( strpos( $path_lower, '/my-account' ) !== false || strpos( $path_lower, '/account' ) !== false ) {
			return 'account';
		}
		if ( strpos( $path_lower, '/product-category' ) !== false ) {
			return 'product_category';
		}
		if ( strpos( $path_lower, '/product/' ) !== false || preg_match( '#/product/[^/]+#', $path_lower ) ) {
			return 'product';
		}
		if ( strpos( $path_lower, '/shop' ) !== false ) {
			return 'shop';
		}
		return 'other';
	}

	/**
	 * Get campaign by ID for frontend. GET /cro/v1/campaign/{id}
	 */
	public function get_campaign_by_id( $request ) {
		$id = intval( $request->get_param( 'id' ) );
		
		if ( ! $id ) {
			return new WP_Error( 'invalid_id', __( 'Invalid campaign ID.', 'meyvora-convert' ), array( 'status' => 400 ) );
		}

		$campaign = CRO_Campaign::get( $id );
		
		if ( ! $campaign ) {
			return new WP_Error( 'not_found', __( 'Campaign not found.', 'meyvora-convert' ), array( 'status' => 404 ) );
		}

		// Convert to frontend format
		if ( is_object( $campaign ) && method_exists( $campaign, 'to_frontend_array' ) ) {
			return new WP_REST_Response( $campaign->to_frontend_array(), 200 );
		}

		return new WP_REST_Response( $campaign, 200 );
	}

	/**
	 * Track event. POST /cro/v1/track
	 *
	 * Body: event_type, campaign_id, [source_type], [source_id], [event_data fields].
	 * Optional A/B fields (sanitized and stored in event_data): ab_test_id, variation_id.
	 * When event_type is impression or conversion and variation_id is set, AB tables are updated.
	 */
	public function track_event( $request ) {
		$body = $request->get_json_params();
		// sendBeacon often sends JSON with Content-Type text/plain, so get_json_params() can be null.
		if ( ! is_array( $body ) ) {
			$raw = $request->get_body();
			if ( is_string( $raw ) && $raw !== '' ) {
				$body = json_decode( $raw, true );
			}
		}
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'invalid_data', __( 'Invalid request data.', 'meyvora-convert' ), array( 'status' => 400 ) );
		}

		$event_type  = sanitize_text_field( $body['event_type'] ?? '' );
		$campaign_id  = absint( $body['campaign_id'] ?? 0 );
		$source_type  = isset( $body['source_type'] ) && in_array( $body['source_type'], array( 'campaign', 'sticky_cart', 'shipping_bar', 'trust_badge' ), true ) ? $body['source_type'] : 'campaign';
		$source_id    = $source_type === 'campaign' ? $campaign_id : absint( $body['source_id'] ?? 0 );

		if ( ! $event_type ) {
			return new WP_Error( 'missing_data', __( 'Missing required fields.', 'meyvora-convert' ), array( 'status' => 400 ) );
		}
		if ( $source_type === 'campaign' && ! $campaign_id ) {
			return new WP_Error( 'missing_data', __( 'Missing required fields.', 'meyvora-convert' ), array( 'status' => 400 ) );
		}

		// Track via CRO_Tracker
		if ( class_exists( 'CRO_Tracker' ) ) {
			$tracker = new CRO_Tracker();
			$tracker->track( $event_type, $campaign_id, $body, $source_type, $source_id );
		}

		// Update visitor state for frequency capping: cooldown after conversion/click
		if ( $source_type === 'campaign' && $campaign_id > 0 && in_array( $event_type, array( 'conversion', 'email_capture', 'email_captured', 'cta_click' ), true ) ) {
			if ( class_exists( 'CRO_Visitor_State' ) ) {
				$visitor = CRO_Visitor_State::get_instance();
				$visitor->record_conversion( $campaign_id );
				$visitor->record_campaign_click( $campaign_id );
				$visitor->save();
			}
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Capture email. POST /cro/v1/email
	 */
	public function capture_email( $request ) {
		$body = $request->get_json_params();
		
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'invalid_data', __( 'Invalid request data.', 'meyvora-convert' ), array( 'status' => 400 ) );
		}

		$email = sanitize_email( $body['email'] ?? '' );
		$campaign_id = absint( $body['campaign_id'] ?? 0 );

		if ( ! $email || ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Invalid email address.', 'meyvora-convert' ), array( 'status' => 400 ) );
		}

		// Save email
		global $wpdb;
		$emails_table = $wpdb->prefix . 'cro_emails';
		
		$wpdb->replace(
			$emails_table,
			array(
				'email' => $email,
				'source_type' => 'campaign',
				'source_id' => $campaign_id,
				'page_url' => $body['page_url'] ?? '',
				'coupon_offered' => $body['coupon_code'] ?? null,
			),
			array( '%s', '%s', '%d', '%s', '%s' )
		);

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Permission check for offer/apply: valid REST nonce (from get_script_data).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function check_rest_nonce( $request ) {
		$nonce = null;
		if ( ! empty( $request->get_header( 'X-WP-Nonce' ) ) ) {
			$nonce = $request->get_header( 'X-WP-Nonce' );
		}
		if ( ! $nonce && isset( $_REQUEST['_wpnonce'] ) && is_string( $_REQUEST['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
		}
		return $nonce && wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * GET /cro/v1/offer — Returns best offer for context (live or from query params) with preview (pass/fail + checks).
	 * Uses CRO_Offer_Engine::preview_offer for shared evaluation with admin Test panel.
	 * Query params (optional): cart_total, cart_items_count, is_logged_in, order_count, lifetime_spend, user_role.
	 * Response: { eligible, offer, coupon_code, can_apply, preview: { passed, checks }, suggestions? }.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_offer( $request ) {
		$empty = array(
			'eligible'    => false,
			'offer'       => null,
			'coupon_code' => null,
			'can_apply'   => false,
			'preview'     => array( 'passed' => false, 'checks' => array() ),
			'suggestions' => array(),
		);

		if ( ! class_exists( 'CRO_Offer_Engine' ) ) {
			return new WP_REST_Response( $empty, 200 );
		}

		$context = $this->get_offer_context_from_request( $request );
		$offers  = CRO_Offer_Engine::get_active_offers();

		$matched_offer_obj = null;
		$matched_payload   = null;
		$matched_preview   = null;

		foreach ( $offers as $offer ) {
			$preview = CRO_Offer_Engine::preview_offer( $offer, $context );
			if ( ! empty( $preview['passed'] ) ) {
				$matched_offer_obj = $offer;
				$matched_payload   = CRO_Offer_Engine::offer_to_payload( $offer );
				$matched_preview   = $preview;
				break;
			}
		}

		if ( ! $matched_offer_obj || ! is_array( $matched_payload ) ) {
			$suggestions = array();
			$first_checks = array();
			$first = reset( $offers );
			if ( $first ) {
				$first_preview = CRO_Offer_Engine::preview_offer( $first, $context );
				$first_checks  = isset( $first_preview['checks'] ) ? $first_preview['checks'] : array();
				$conditions = CRO_Offer_Engine::get_conditions_from_offer( $first );
				foreach ( $conditions as $key => $value ) {
					if ( ! CRO_Offer_Engine::evaluate_condition( (string) $key, $value, $context ) ) {
						$sug = CRO_Offer_Engine::condition_suggestion( (string) $key, $value, $context );
						if ( $sug !== '' ) {
							$suggestions[] = $sug;
						}
					}
				}
			}
			$empty['preview']     = array( 'passed' => false, 'checks' => $first_checks );
			$empty['suggestions'] = $suggestions;
			return new WP_REST_Response( $empty, 200 );
		}

		// Coupon only when using live context (no override params).
		$use_live_context = $request->get_param( 'cart_total' ) === null && $request->get_param( 'cart_items_count' ) === null;
		$result = $use_live_context ? CRO_Offer_Engine::get_best_offer_with_coupon( null ) : array( 'offer' => $matched_payload, 'coupon_code' => null );
		$code   = isset( $result['coupon_code'] ) ? $result['coupon_code'] : null;

		$can_apply = ! empty( $code );
		if ( $can_apply && function_exists( 'WC' ) && WC()->cart ) {
			$applied = WC()->cart->get_applied_coupons();
			if ( in_array( wc_format_coupon_code( $code ), $applied, true ) ) {
				$can_apply = false;
			}
		}

		return new WP_REST_Response( array(
			'eligible'    => true,
			'offer'      => $matched_payload,
			'coupon_code' => $can_apply ? $code : null,
			'can_apply'  => $can_apply,
			'preview'    => array(
				'passed' => true,
				'checks' => isset( $matched_preview['checks'] ) ? $matched_preview['checks'] : array(),
			),
			'suggestions' => array(),
		), 200 );
	}

	/**
	 * Build offer context from REST request: query params if provided, else live build_context().
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array
	 */
	private function get_offer_context_from_request( $request ) {
		$cart_total   = $request->get_param( 'cart_total' );
		$items_count  = $request->get_param( 'cart_items_count' );
		$is_logged_in = $request->get_param( 'is_logged_in' );
		if ( $cart_total === null && $items_count === null && $is_logged_in === null ) {
			return class_exists( 'CRO_Offer_Engine' ) ? CRO_Offer_Engine::build_context() : array();
		}
		$order_count = $request->get_param( 'order_count' );
		$lifetime    = $request->get_param( 'lifetime_spend' );
		$user_role   = $request->get_param( 'user_role' );
		if ( $is_logged_in === false || $is_logged_in === '0' ) {
			$user_role = '';
		} elseif ( $user_role === null ) {
			$user_role = '';
		}
		return array(
			'cart_total'       => is_numeric( $cart_total ) ? (float) $cart_total : 0.0,
			'cart_items_count' => is_numeric( $items_count ) ? (int) $items_count : 0,
			'user_id'          => $is_logged_in ? ( get_current_user_id() ?: 1 ) : 0,
			'is_logged_in'     => (bool) $is_logged_in,
			'user_role'        => is_string( $user_role ) ? $user_role : '',
			'order_count'      => is_numeric( $order_count ) ? (int) $order_count : 0,
			'lifetime_spend'   => is_numeric( $lifetime ) ? (float) $lifetime : 0.0,
			'visitor_id'       => class_exists( 'CRO_Visitor_State' ) ? (string) CRO_Visitor_State::get_instance()->get_visitor_id() : '',
		);
	}

	/**
	 * POST /cro/v1/offer/apply — Applies CRO coupon to cart (nonce protected).
	 * Body: { coupon_code }. Validates coupon belongs to visitor/user via meta; rate-limited.
	 * Returns { success, message, coupon_code, cart_fragments? }.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function apply_offer_coupon( $request ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $this->offer_error( 'unavailable', __( 'Cart is unavailable.', 'meyvora-convert' ), 503 );
		}

		$code = $request->get_param( 'coupon_code' );
		if ( ! is_string( $code ) || trim( $code ) === '' ) {
			return $this->offer_error( 'invalid_coupon', __( 'Invalid or missing coupon code.', 'meyvora-convert' ), 400 );
		}
		$code = wc_format_coupon_code( sanitize_text_field( $code ) );

		// Rate limit apply attempts per visitor/IP.
		if ( ! $this->check_offer_apply_rate_limit() ) {
			return $this->offer_error( 'rate_limited', __( 'Too many attempts. Please try again later.', 'meyvora-convert' ), 429 );
		}

		$coupon_id = function_exists( 'wc_get_coupon_id_by_code' ) ? wc_get_coupon_id_by_code( $code ) : 0;
		if ( ! $coupon_id ) {
			return $this->offer_error( 'invalid_coupon', __( 'This coupon is not valid.', 'meyvora-convert' ), 404 );
		}

		$meta_visitor = get_post_meta( $coupon_id, '_cro_visitor_id', true );
		$meta_user    = (int) get_post_meta( $coupon_id, '_cro_user_id', true );
		$visitor_id   = class_exists( 'CRO_Visitor_State' ) ? (string) CRO_Visitor_State::get_instance()->get_visitor_id() : '';
		$user_id      = get_current_user_id();

		$allowed = false;
		if ( $user_id > 0 ) {
			$allowed = ( (int) $meta_user === $user_id ) || ( (string) $meta_visitor === $visitor_id && $visitor_id !== '' );
		} else {
			$allowed = (string) $meta_visitor === $visitor_id && $visitor_id !== '';
		}
		if ( ! $allowed ) {
			return $this->offer_error( 'forbidden', __( 'This coupon is not assigned to you.', 'meyvora-convert' ), 403 );
		}

		$applied = WC()->cart->get_applied_coupons();
		if ( in_array( $code, $applied, true ) ) {
			return new WP_REST_Response( array(
				'success'        => true,
				'message'        => __( 'Coupon already applied.', 'meyvora-convert' ),
				'coupon_code'    => $code,
				'cart_fragments' => $this->get_cart_fragments(),
			), 200 );
		}

		$result = WC()->cart->apply_coupon( $code );
		if ( is_wp_error( $result ) ) {
			/** @var \WP_Error $result */
			return new WP_REST_Response( array(
				'success' => false,
				'message' => $result->get_error_message(),
				'code'    => 'apply_failed',
			), 200 );
		}

		return new WP_REST_Response( array(
			'success'        => true,
			'message'        => __( 'Coupon applied.', 'meyvora-convert' ),
			'coupon_code'    => $code,
			'cart_fragments' => $this->get_cart_fragments(),
		), 200 );
	}

	/**
	 * Consistent error for offer endpoints.
	 *
	 * @param string $code    Error code.
	 * @param string $message User-facing message.
	 * @param int    $status  HTTP status.
	 * @return WP_Error
	 */
	private function offer_error( $code, $message, $status = 400 ) {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/**
	 * Rate limit for offer/apply: max attempts per visitor within a short window.
	 *
	 * @return bool True if under limit.
	 */
	private function check_offer_apply_rate_limit() {
		$visitor_id = class_exists( 'CRO_Visitor_State' ) ? (string) CRO_Visitor_State::get_instance()->get_visitor_id() : '';
		$key = 'cro_offer_apply_' . md5( $visitor_id . '|' . $this->get_client_ip() );
		$count = (int) get_transient( $key );
		$max   = (int) apply_filters( 'cro_offer_apply_rate_limit_max', 10 );
		$ttl   = (int) apply_filters( 'cro_offer_apply_rate_limit_ttl_seconds', 300 );
		if ( $max <= 0 ) {
			$max = 10;
		}
		if ( $ttl <= 0 ) {
			$ttl = 300;
		}
		if ( $count >= $max ) {
			return false;
		}
		set_transient( $key, $count + 1, $ttl );
		return true;
	}

	/**
	 * Get WooCommerce cart fragments for updated UI (totals, coupons list, etc.).
	 *
	 * @return array
	 */
	private function get_cart_fragments() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return array();
		}
		return apply_filters( 'woocommerce_add_to_cart_fragments', array() );
	}

	/**
	 * Load decision engine and dependencies if cro_decide() is not available.
	 * Requires includes/engine/ (Context, Decision, RuleEngine, IntentScorer, CampaignModel, DecisionEngine).
	 */
	private function ensure_decision_engine_loaded() {
		if ( function_exists( 'cro_decide' ) ) {
			return;
		}
		$dir = defined( 'CRO_PLUGIN_DIR' ) ? rtrim( (string) ( CRO_PLUGIN_DIR ?? '' ), '/' ) . '/' : dirname( plugin_dir_path( __FILE__ ) ) . '/';
		if ( ! class_exists( 'CRO_Context' ) ) {
			require_once $dir . 'models/class-cro-context.php';
		}
		if ( ! class_exists( 'CRO_Decision' ) ) {
			require_once $dir . 'engine/class-cro-decision.php';
		}
		if ( ! class_exists( 'CRO_Rule_Engine' ) ) {
			require_once $dir . 'engine/class-cro-rule-engine.php';
		}
		if ( ! class_exists( 'CRO_Intent_Scorer' ) ) {
			require_once $dir . 'engine/class-cro-intent-scorer.php';
		}
		if ( ! class_exists( 'CRO_Campaign_Model' ) ) {
			require_once $dir . 'models/class-cro-campaign-model.php';
		}
		// Canonical decision engine — do not re-add includes/class-cro-decision-engine.php
		if ( ! class_exists( 'CRO_Decision_Engine' ) ) {
			$engine_file = $dir . 'engine/class-cro-decision-engine.php';
			if ( file_exists( $engine_file ) ) {
				require_once $engine_file;
			}
		}
	}

	/**
	 * Wrap REST endpoint with error handling (rate limit, emergency check, try/catch).
	 *
	 * @param callable $callback Endpoint callback receiving WP_REST_Request.
	 * @return callable Wrapped callback.
	 */
	private function safe_endpoint( $callback ) {
		return function ( $request ) use ( $callback ) {
			try {
				if ( ! $this->check_rate_limit( $request ) ) {
					return new WP_Error(
						'rate_limited',
						__( 'Too many requests. Please try again later.', 'meyvora-convert' ),
						array( 'status' => 429 )
					);
				}

				if ( class_exists( 'CRO_Error_Handler' ) && CRO_Error_Handler::is_emergency_disabled() ) {
					return new WP_Error(
						'service_unavailable',
						__( 'Service temporarily unavailable.', 'meyvora-convert' ),
						array( 'status' => 503 )
					);
				}

				return $callback( $request );
			} catch ( Exception $e ) {
				if ( class_exists( 'CRO_Error_Handler' ) ) {
					CRO_Error_Handler::log( 'REST_ERROR', $e->getMessage(), array(
						'endpoint' => $request->get_route(),
						'trace'    => $e->getTraceAsString(),
					) );
				}
				return new WP_Error(
					'server_error',
					__( 'An error occurred. Please try again.', 'meyvora-convert' ),
					array( 'status' => 500 )
				);
			} catch ( Error $e ) {
				if ( class_exists( 'CRO_Error_Handler' ) ) {
					CRO_Error_Handler::log( 'REST_ERROR', $e->getMessage(), array(
						'endpoint' => $request->get_route(),
						'trace'    => $e->getTraceAsString(),
					) );
				}
				return new WP_Error(
					'server_error',
					__( 'An error occurred. Please try again.', 'meyvora-convert' ),
					array( 'status' => 500 )
				);
			}
		};
	}

	/**
	 * Rate limiting check (60 requests per minute per IP).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool True if under limit.
	 */
	private function check_rate_limit( $request ) {
		$ip   = $this->get_client_ip();
		$key  = 'cro_rate_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= 60 ) {
			return false;
		}
		set_transient( $key, $count + 1, 60 );
		return true;
	}

	/**
	 * Get client IP safely (Cloudflare, X-Forwarded-For, X-Real-IP, REMOTE_ADDR).
	 *
	 * @return string IP address or '0.0.0.0' if none valid.
	 */
	private function get_client_ip() {
		$headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) && is_string( $_SERVER[ $header ] ) ) {
				$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
				$ip  = trim( $ips[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '0.0.0.0';
	}
}
