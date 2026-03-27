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
		$req_uri = filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_UNSAFE_RAW );
		if ( is_string( $req_uri ) && $req_uri !== '' ) {
			$path = sanitize_text_field( wp_unslash( $req_uri ) );
		}
		// Also check rest_route query param (e.g. ?rest_route=/cro/v1/decide)
		$rest_route = filter_input( INPUT_GET, 'rest_route', FILTER_UNSAFE_RAW );
		if ( is_string( $rest_route ) && $rest_route !== '' ) {
			$path = '/' . ltrim( sanitize_text_field( wp_unslash( $rest_route ) ), '/' );
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

		// Public endpoint — intentionally unauthenticated. Storefront visitors call this (rate-limited in the callback).
		register_rest_route(
			'cro/v1',
			'/decide',
			array(
				'methods'             => 'POST',
				'callback'            => $this->safe_endpoint( array( $this, 'decide' ) ),
				'permission_callback' => '__return_true',
				'args'                => array(),
			)
		);

		// Public endpoint — intentionally unauthenticated. Fetches campaign payload for display (rate-limited in the callback).
		register_rest_route(
			'cro/v1',
			'/campaign/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => $this->safe_endpoint( array( $this, 'get_campaign_by_id' ) ),
				'permission_callback' => '__return_true',
			)
		);

		// Public endpoint — intentionally unauthenticated. Analytics/tracking from the storefront (rate-limited in the callback).
		register_rest_route(
			'cro/v1',
			'/track',
			array(
				'methods'             => 'POST',
				'callback'            => $this->safe_endpoint( array( $this, 'track_event' ) ),
				'permission_callback' => '__return_true',
			)
		);

		// Public endpoint — intentionally unauthenticated. Email capture from campaign modals (rate-limited in the callback).
		register_rest_route(
			'cro/v1',
			'/email',
			array(
				'methods'             => 'POST',
				'callback'            => $this->safe_endpoint( array( $this, 'capture_email' ) ),
				'permission_callback' => '__return_true',
			)
		);

		// Public endpoint — intentionally unauthenticated. Offer resolution for blocks/storefront (rate-limited in the callback). GET returns best offer + coupon code + preview.
		register_rest_route(
			'cro/v1',
			'/offer',
			array(
				'methods'             => 'GET',
				'callback'            => $this->safe_endpoint( array( $this, 'get_offer' ) ),
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
	 * Normalize client cart for rule engine (canonical CRO shape or WooCommerce Store API / cart blocks).
	 *
	 * @param array $cart_in Raw context.cart from JSON.
	 * @return array<string, mixed>|null
	 */
	private static function normalize_client_cart_for_decide( array $cart_in ) {
		$cart_in = apply_filters( 'cro_rest_decide_client_cart_raw', $cart_in );

		// Canonical shape from croConfig / CRO_Context::to_frontend_array().
		if ( isset( $cart_in['total'] ) || isset( $cart_in['item_count'] ) || isset( $cart_in['product_ids'] ) || isset( $cart_in['categories'] ) || isset( $cart_in['has_items'] ) ) {
			$out = array(
				'total'            => isset( $cart_in['total'] ) ? (float) $cart_in['total'] : 0.0,
				'item_count'       => isset( $cart_in['item_count'] ) ? absint( $cart_in['item_count'] ) : 0,
				'categories'       => isset( $cart_in['categories'] ) && is_array( $cart_in['categories'] ) ? array_map( 'intval', $cart_in['categories'] ) : array(),
				'product_ids'      => isset( $cart_in['product_ids'] ) && is_array( $cart_in['product_ids'] ) ? array_map( 'intval', $cart_in['product_ids'] ) : array(),
				'sale_item_count'  => isset( $cart_in['sale_item_count'] ) ? absint( $cart_in['sale_item_count'] ) : 0,
				'low_stock_count'  => isset( $cart_in['low_stock_count'] ) ? absint( $cart_in['low_stock_count'] ) : 0,
				'has_items'        => false,
			);
			if ( isset( $cart_in['has_items'] ) ) {
				$out['has_items'] = (bool) $cart_in['has_items'];
			} else {
				$out['has_items'] = $out['item_count'] > 0 || $out['total'] > 0;
			}
			return apply_filters( 'cro_rest_decide_client_cart_normalized', $out, $cart_in );
		}

		// WooCommerce Store API / cart blocks (totals.total_price in minor currency units).
		if ( isset( $cart_in['totals'] ) && is_array( $cart_in['totals'] ) && isset( $cart_in['totals']['total_price'] ) ) {
			$minor = isset( $cart_in['totals']['currency_minor_unit'] ) ? max( 0, (int) $cart_in['totals']['currency_minor_unit'] ) : 2;
			$raw   = is_numeric( $cart_in['totals']['total_price'] ) ? (string) $cart_in['totals']['total_price'] : '0';
			$total = (float) $raw / pow( 10, $minor );
			$items = isset( $cart_in['items'] ) && is_array( $cart_in['items'] ) ? $cart_in['items'] : array();
			$line_count = count( $items );
			$product_ids = array();
			foreach ( $items as $item ) {
				if ( isset( $item['id'] ) ) {
					$product_ids[] = (int) $item['id'];
				}
			}
			$items_count = isset( $cart_in['items_count'] ) ? (int) $cart_in['items_count'] : 0;
			// Match WC cart line count vs total quantity: use max so cart_item_count_gte behaves sensibly.
			$item_count = max( $line_count, $items_count );

			$out = array(
				'total'            => $total,
				'item_count'       => $item_count,
				'categories'       => array(),
				'product_ids'      => array_values( array_unique( array_filter( $product_ids ) ) ),
				'sale_item_count'  => 0,
				'low_stock_count'  => 0,
				'has_items'        => $total > 0.00001 || $line_count > 0 || $items_count > 0,
			);
			return apply_filters( 'cro_rest_decide_client_cart_normalized', $out, $cart_in );
		}

		return null;
	}

	/**
	 * Parse JSON body for /decide (shared with AJAX fallback decide).
	 *
	 * @param array $body Request body (signals, context, trigger_type, trigger_data, pageview_id).
	 *                    Merges behavior, request, visitor from the client. page_type/device_type are not in is_shop() during REST — allowlisted client values override server detection.
	 *                    context.cart is merged for REST (no WC session); supports CRO snapshot or Woo Store API cart blocks.
	 *                    trigger_data maps to behavior: seconds|time_on_page|time → time_on_page; depth|scroll_depth → scroll_depth; idle_seconds|idle → idle_seconds.
	 * @return array{ context: CRO_Context, signals: array, trigger_type: string, pageview_id: string }
	 */
	public static function parse_decide_request_body( array $body ) {
		$signals      = isset( $body['signals'] ) && is_array( $body['signals'] ) ? $body['signals'] : array();
		$body_context = isset( $body['context'] ) && is_array( $body['context'] ) ? $body['context'] : array();
		$trigger_type = isset( $body['trigger_type'] ) ? sanitize_key( (string) $body['trigger_type'] ) : '';
		$trigger_data = isset( $body['trigger_data'] ) && is_array( $body['trigger_data'] ) ? $body['trigger_data'] : array();
		$pageview_id  = isset( $body['pageview_id'] ) ? sanitize_text_field( (string) $body['pageview_id'] ) : '';
		$pageview_id  = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $pageview_id );
		$pageview_id  = substr( $pageview_id, 0, 64 );

		$context = new CRO_Context();

		// Only trust these keys from JS — everything else (page_type, cart, user, device) is server-detected.
		$js_trusted_keys = array( 'behavior', 'request', 'visitor' );

		foreach ( $body_context as $key => $value ) {
			$key = sanitize_key( $key );
			if ( $key !== '' && in_array( $key, $js_trusted_keys, true ) ) {
				$context->set( $key, $value );
			}
		}

		// REST /decide is not the storefront request — is_product() is false, so server page_type is wrong. Trust allowlisted client snapshot.
		$allowed_page_types = array(
			'home',
			'shop',
			'product',
			'product_category',
			'category',
			'cart',
			'checkout',
			'account',
			'page',
			'post',
			'blog',
			'other',
		);
		if ( isset( $body_context['page_type'] ) ) {
			$pt = sanitize_key( (string) $body_context['page_type'] );
			if ( 'category' === $pt ) {
				$pt = 'product_category';
			}
			if ( $pt !== '' && in_array( $pt, $allowed_page_types, true ) ) {
				$context->set( 'page_type', $pt );
			}
		}
		$allowed_devices = array( 'desktop', 'mobile', 'tablet' );
		if ( isset( $body_context['device_type'] ) ) {
			$dt = sanitize_key( (string) $body_context['device_type'] );
			if ( $dt !== '' && in_array( $dt, $allowed_devices, true ) ) {
				$context->set( 'device_type', $dt );
			}
		}

		// REST has no Woo session cart like a normal page; trust client cart snapshot (same as page_type).
		if ( isset( $body_context['cart'] ) && is_array( $body_context['cart'] ) ) {
			$merged_cart = self::normalize_client_cart_for_decide( $body_context['cart'] );
			if ( ! empty( $merged_cart ) && is_array( $merged_cart ) ) {
				$context->set( 'cart', $merged_cart );
			}
		}

		$current_page_type = $context->get( 'page_type', '' );
		if ( ( $current_page_type === '' || $current_page_type === 'other' ) && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$page_type = self::infer_page_type_from_referer( sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) );
			if ( $page_type !== '' ) {
				$context->set( 'page_type', $page_type );
			}
		}
		if ( ! empty( $trigger_data ) ) {
			// Map trigger_data to behavior.* (canonical keys: seconds, depth, idle_seconds; aliases for older clients).
			if ( isset( $trigger_data['seconds'] ) ) {
				$context->set( 'behavior.time_on_page', (int) $trigger_data['seconds'] );
			} elseif ( isset( $trigger_data['time_on_page'] ) ) {
				$context->set( 'behavior.time_on_page', (int) $trigger_data['time_on_page'] );
			} elseif ( isset( $trigger_data['time'] ) ) {
				$context->set( 'behavior.time_on_page', (int) $trigger_data['time'] );
			}
			if ( isset( $trigger_data['depth'] ) ) {
				$context->set( 'behavior.scroll_depth', (int) $trigger_data['depth'] );
			} elseif ( isset( $trigger_data['scroll_depth'] ) ) {
				$context->set( 'behavior.scroll_depth', (int) $trigger_data['scroll_depth'] );
			}
			if ( isset( $trigger_data['idle_seconds'] ) ) {
				$context->set( 'behavior.idle_seconds', (int) $trigger_data['idle_seconds'] );
			} elseif ( isset( $trigger_data['idle'] ) ) {
				$context->set( 'behavior.idle_seconds', (int) $trigger_data['idle'] );
			}
		}
		if ( $trigger_type === 'page_load' ) {
			$context->set( 'behavior.time_on_page', 0 );
		}

		// If scroll depth is still 0 but signals/trigger_data disagree (e.g. behavior merged before trigger_data), use signals.
		if ( (int) $context->get( 'behavior.scroll_depth', 0 ) < 1 && isset( $signals['scroll_depth'] ) ) {
			$context->set( 'behavior.scroll_depth', (int) $signals['scroll_depth'] );
		}

		return array(
			'context'      => $context,
			'signals'      => $signals,
			'trigger_type' => $trigger_type,
			'pageview_id'  => $pageview_id,
		);
	}

	/**
	 * Whether this REST request may receive full decide() debug (same checks as payload inclusion).
	 *
	 * context.user.is_admin in JSON does not authenticate the HTTP request. Use logged-in WP user
	 * (cookies + X-WP-Nonce), or define CRO_DECIDE_DEBUG_SECRET and send matching decide_debug_secret in JSON.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param array           $body    Parsed JSON body.
	 * @return bool
	 */
	private static function rest_can_return_decide_debug( $request, array $body ) {
		if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_meyvora_convert' ) ) {
			return true;
		}
		if ( defined( 'CRO_DECIDE_DEBUG_SECRET' ) && is_string( CRO_DECIDE_DEBUG_SECRET ) && CRO_DECIDE_DEBUG_SECRET !== ''
			&& isset( $body['decide_debug_secret'] ) && is_string( $body['decide_debug_secret'] )
			&& hash_equals( CRO_DECIDE_DEBUG_SECRET, $body['decide_debug_secret'] ) ) {
			return true;
		}
		return (bool) apply_filters( 'cro_rest_decide_debug_allowed', false, $request, $body );
	}

	/**
	 * Decide which campaign (if any) to show. POST /cro/v1/decide.
	 *
	 * Request body: { "signals": {...}, "context": {...}, "trigger_type", "trigger_data", "pageview_id" }.
	 * pageview_id: optional; stable ID per page load (e.g. UUID) for A/B impression dedupe.
	 * Response: { show, campaign_id, campaign, reason, reason_code, ab_test_id?, variation_id?, is_control?, debug? }.
	 * With "debug": true, the response includes full debug only if this HTTP request is authenticated as
	 * an admin (WordPress cookies + X-WP-Nonce for REST), OR you send "decide_debug_secret" matching
	 * CRO_DECIDE_DEBUG_SECRET in wp-config.php. context.user.is_admin in JSON does not grant debug.
	 * If debug was requested but rejected, the response includes debug_request_rejected with a reason.
	 *
	 * @param WP_REST_Request $request Request object (JSON body: signals, context, trigger_type, trigger_data, pageview_id, etc.).
	 * @return WP_REST_Response|WP_Error
	 */
	public function decide( $request ) {
		self::ensure_decision_engine_loaded();

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
		if ( empty( $body ) ) {
			$raw = $request->get_body();
			if ( is_string( $raw ) && $raw !== '' ) {
				$decoded = json_decode( $raw, true );
				if ( is_array( $decoded ) ) {
					$body = $decoded;
				}
			}
		}
		$parsed         = self::parse_decide_request_body( $body );
		$context        = $parsed['context'];
		$signals        = $parsed['signals'];
		$trigger_type   = $parsed['trigger_type'];
		$pageview_id    = $parsed['pageview_id'];

		$visitor_state = CRO_Visitor_State::get_instance();
		$decision     = cro_decide()->decide( $context, $visitor_state, $signals, $trigger_type, array() );

		$wants_debug          = ! empty( $body['debug'] ) || ! empty( $body['decide_debug'] );
		$can_see_debug        = self::rest_can_return_decide_debug( $request, $body );
		$debug_mode           = function_exists( 'cro_settings' ) && $can_see_debug && cro_settings()->get( 'general', 'debug_mode', false );
		$request_debug        = $wants_debug && $can_see_debug;
		$include_decide_debug = $debug_mode || $request_debug;

		if ( $include_decide_debug && is_object( $decision ) && method_exists( $decision, 'log' ) && ! $decision->show && $trigger_type === '' ) {
			$decision->log(
				'WARN',
				__( 'trigger_type was empty — JSON body may not have been parsed. Check Content-Type header and WAF rules.', 'meyvora-convert' ),
				array( 'step' => 0 )
			);
		}

		$payload = array(
			'show'        => $decision->show,
			'campaign_id' => $decision->campaign_id,
			'campaign'    => null,
			'reason'      => $decision->reason,
			'reason_code' => $decision->reason_code,
		);

		if ( $decision->show && (int) $decision->fallback_campaign_id > 0 ) {
			$payload['fallback_campaign_id']   = (int) $decision->fallback_campaign_id;
			$payload['fallback_delay_seconds'] = (int) $decision->fallback_delay_seconds;
		}

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
			self::record_ab_impression_once( $visitor_state, (int) $decision->variation_id, $pageview_id );
		}

		if ( $include_decide_debug && is_object( $decision ) && method_exists( $decision, 'to_debug_array' ) ) {
			$payload['debug'] = $decision->to_debug_array();
		} elseif ( $wants_debug && ! $include_decide_debug ) {
			$payload['debug_request_rejected'] = array(
				'reason'  => 'not_authenticated',
				'message' => __( 'Debug is not included: this REST request is not authenticated as a WordPress admin. Send the wordpress_logged_in cookie and X-WP-Nonce (from wp-api), or add define( \'CRO_DECIDE_DEBUG_SECRET\', \'your-secret\' ); to wp-config.php and pass the same value as "decide_debug_secret" in the JSON body. Note: context.user.is_admin in the JSON body does not authenticate the request.', 'meyvora-convert' ),
			);
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
	public static function record_ab_impression_once( CRO_Visitor_State $visitor_state, $variation_id, $pageview_id = '' ) {
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
	public static function infer_page_type_from_referer( $referer ) {
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

		$row = CRO_Campaign::get( $id );

		if ( ! $row ) {
			return new WP_Error( 'not_found', __( 'Campaign not found.', 'meyvora-convert' ), array( 'status' => 404 ) );
		}

		// Only serve active campaigns to public visitors.
		$status = isset( $row['status'] ) ? (string) $row['status'] : '';
		if ( $status !== 'active' ) {
			return new WP_Error( 'not_found', __( 'Campaign not found.', 'meyvora-convert' ), array( 'status' => 404 ) );
		}

		// Build a model and return only the data needed by the frontend popup renderer.
		// Strip server-side targeting/frequency/schedule rules — frontend only needs
		// content, styling, and trigger configuration.
		if ( class_exists( 'CRO_Campaign_Model' ) ) {
			$model = CRO_Campaign_Model::from_db_row( $row );
			$data  = $model->to_frontend_array();
			unset( $data['targeting_rules'], $data['frequency_rules'], $data['schedule'],
				$data['fallback_id'], $data['fallback_delay_seconds'],
				$data['brand_styles_override'] );
			return new WP_REST_Response( $data, 200 );
		}

		return new WP_Error( 'unavailable', __( 'Campaign system unavailable.', 'meyvora-convert' ), array( 'status' => 503 ) );
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

		$wpdb->replace( $emails_table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
			array(
				'email' => $email,
				'source_type' => 'campaign',
				'source_id' => $campaign_id,
				'page_url' => $body['page_url'] ?? '',
				'coupon_offered' => $body['coupon_code'] ?? null,
			),
			array( '%s', '%s', '%d', '%s', '%s' )
		);
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'meyvora_cro' );
		}

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

		$resolved = CRO_Offer_Engine::get_matched_offers_resolved( $context );
		if ( ! empty( $resolved[0] ) ) {
			$matched_offer_obj = $resolved[0];
			$matched_payload   = CRO_Offer_Engine::offer_to_payload( $matched_offer_obj );
			$matched_preview   = CRO_Offer_Engine::preview_offer( $matched_offer_obj, $context );
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
	 * Ensure WooCommerce cart is loaded. REST requests often have WC()->cart unset until wc_load_cart().
	 *
	 * @return bool True if cart is available.
	 */
	private function ensure_wc_cart_loaded() {
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return false;
		}
		if ( WC()->cart ) {
			return true;
		}
		if ( ! function_exists( 'wc_load_cart' ) ) {
			return false;
		}
		if ( ! did_action( 'woocommerce_init' ) ) {
			return false;
		}
		wc_load_cart();

		return (bool) WC()->cart;
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
		if ( ! $this->ensure_wc_cart_loaded() ) {
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
			if ( 0 === $user_id ) {
				$login_url = '';
				if ( function_exists( 'wc_get_page_permalink' ) ) {
					$login_url = wc_get_page_permalink( 'myaccount' );
				}
				if ( ! is_string( $login_url ) || $login_url === '' ) {
					$login_url = wp_login_url();
				}
				return $this->offer_error(
					'forbidden',
					__( 'Login to your account to use this coupon.', 'meyvora-convert' ),
					403,
					array( 'login_url' => $login_url )
				);
			}
			return $this->offer_error(
				'forbidden',
				__( 'This coupon is not assigned to your account.', 'meyvora-convert' ),
				403
			);
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
	private function offer_error( $code, $message, $status = 400, $extra = array() ) {
		$data = array_merge( array( 'status' => (int) $status ), is_array( $extra ) ? $extra : array() );
		return new WP_Error( $code, $message, $data );
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
	public static function ensure_decision_engine_loaded() {
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
	 * Rate limiting per IP. Uses separate buckets so /decide is not starved by /track, /offer, etc.
	 *
	 * Filters:
	 * - `cro_rest_decide_rate_limit_max` (default 500) — POST /decide only.
	 * - `cro_rest_decide_rate_limit_window_seconds` (default 60).
	 * - `cro_rest_rate_limit_max` (default 120) — other public CRO REST routes.
	 * - `cro_rest_rate_limit_window_seconds` (default 60).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool True if under limit.
	 */
	private function check_rate_limit( $request ) {
		$ip    = $this->get_client_ip();
		$route = is_object( $request ) && method_exists( $request, 'get_route' ) ? (string) $request->get_route() : '';

		$bucket = 'rest';
		if ( strpos( $route, '/decide' ) !== false ) {
			$bucket = 'decide';
		} elseif ( strpos( $route, '/track' ) !== false ) {
			$bucket = 'track';
		}

		if ( 'decide' === $bucket ) {
			$max    = (int) apply_filters( 'cro_rest_decide_rate_limit_max', 500 );
			$window = (int) apply_filters( 'cro_rest_decide_rate_limit_window_seconds', 60 );
		} else {
			$max    = (int) apply_filters( 'cro_rest_rate_limit_max', 120 );
			$window = (int) apply_filters( 'cro_rest_rate_limit_window_seconds', 60 );
		}

		$max    = max( 10, $max );
		$window = max( 10, $window );

		$key   = 'cro_rate_' . md5( $ip . '|' . $bucket );
		$count = (int) get_transient( $key );

		if ( $count >= $max ) {
			return false;
		}
		set_transient( $key, $count + 1, $window );
		return true;
	}

	/**
	 * Client IP for rate limiting. Trusts forwarded headers only when REMOTE_ADDR is a trusted proxy.
	 *
	 * @return string IP address or '0.0.0.0' if none valid.
	 */
	private function get_client_ip() {
		$remote = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '0.0.0.0';

		$trusted_proxies = apply_filters(
			'cro_trusted_proxy_ips',
			array(
				'127.0.0.1',
				'::1',
			)
		);
		if ( ! is_array( $trusted_proxies ) ) {
			$trusted_proxies = array();
		}

		if ( in_array( $remote, $trusted_proxies, true ) ) {
			foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP' ) as $header ) {
				if ( empty( $_SERVER[ $header ] ) || ! is_string( $_SERVER[ $header ] ) ) {
					continue;
				}
				$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
				$ip  = trim( $ips[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		if ( filter_var( $remote, FILTER_VALIDATE_IP ) ) {
			return $remote;
		}

		return '0.0.0.0';
	}
}
