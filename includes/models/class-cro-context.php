<?php
/**
 * Context snapshot model
 *
 * Request/visit context: page_type, device, cart, user, request, time, and
 * behavioral placeholders (updated from JS). Supports get() with dot notation
 * and matches() for rule evaluation.
 *
 * @package Meyvora_Convert
 */
// phpcs:disable WordPress.Security.NonceVerification.Recommended

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CRO_Context class.
 *
 * Model for request/visit context (page, device, cart, user, request, time, behavior).
 */
class CRO_Context {

	/**
	 * Raw context data (nested array).
	 *
	 * @var array
	 */
	private $data = array();

	/**
	 * Build context automatically from current request.
	 */
	public function __construct() {
		$this->data = array(
			'page_type'   => $this->detect_page_type(),
			'device_type' => $this->detect_device_type(),
			'cart'        => $this->build_cart_data(),
			'user'        => $this->build_user_data(),
			'request'     => $this->build_request_data(),
			'time'        => $this->build_time_data(),
			'behavior'    => $this->build_behavior_placeholder(),
		);
	}

	/**
	 * Detect page type (home, shop, product, cart, checkout, etc.).
	 *
	 * @return string
	 */
	private function detect_page_type() {
		if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
			if ( function_exists( 'is_shop' ) && is_shop() && ! is_product_category() ) {
				return 'shop';
			}
			if ( function_exists( 'is_product_category' ) && is_product_category() ) {
				return 'product_category';
			}
			if ( function_exists( 'is_product' ) && is_product() ) {
				return 'product';
			}
			if ( function_exists( 'is_cart' ) && is_cart() ) {
				return 'cart';
			}
			if ( function_exists( 'is_checkout' ) && is_checkout() ) {
				return 'checkout';
			}
			if ( function_exists( 'is_account_page' ) && is_account_page() ) {
				return 'account';
			}
		}
		if ( is_front_page() ) {
			return 'home';
		}
		if ( is_singular( 'post' ) ) {
			return 'post';
		}
		if ( is_page() ) {
			return 'page';
		}
		return 'other';
	}

	/**
	 * Detect device type (desktop, mobile, tablet).
	 *
	 * @return string
	 */
	private function detect_device_type() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$ua = strtolower( $ua );

		if ( strpos( $ua, 'ipad' ) !== false ) {
			return 'tablet';
		}
		if ( strpos( $ua, 'tablet' ) !== false || ( strpos( $ua, 'android' ) !== false && strpos( $ua, 'mobile' ) === false ) ) {
			return 'tablet';
		}
		if ( function_exists( 'wp_is_mobile' ) && wp_is_mobile() ) {
			return 'mobile';
		}
		return 'desktop';
	}

	/**
	 * Build WooCommerce cart data (total, items, categories, sale items, low stock).
	 *
	 * @return array
	 */
	private function build_cart_data() {
		$empty = array(
			'total'         => 0.0,
			'item_count'    => 0,
			'categories'   => array(),
			'sale_item_count' => 0,
			'low_stock_count' => 0,
			'has_items'    => false,
		);

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $empty;
		}

		$cart = WC()->cart;
		$total = (float) $cart->get_total( 'edit' );
		$contents = $cart->get_cart();
		$item_count = 0;
		$categories = array();
		$product_ids = array();
		$sale_count = 0;
		$low_stock_count = 0;

		foreach ( $contents as $item ) {
			$item_count++;
			$product = $item['data'] ?? null;
			if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
				continue;
			}
			$pid = $product->get_id();
			if ( $pid ) {
				$product_ids[ $pid ] = true;
			}
			if ( $product->is_on_sale() ) {
				$sale_count++;
			}
			if ( $product->managing_stock() && $product->get_stock_quantity() !== null && $product->get_stock_quantity() <= (int) get_option( 'woocommerce_notify_low_stock_amount', 2 ) ) {
				$low_stock_count++;
			}
			$cat_ids = $product->get_category_ids();
			foreach ( $cat_ids as $cid ) {
				$categories[ $cid ] = true;
			}
		}

		return array(
			'total'            => $total,
			'item_count'       => $item_count,
			'categories'       => array_keys( $categories ),
			'product_ids'      => array_keys( $product_ids ),
			'sale_item_count'  => $sale_count,
			'low_stock_count'  => $low_stock_count,
			'has_items'        => $item_count > 0,
		);
	}

	/**
	 * Build user data (logged_in, id, role, is_admin).
	 *
	 * @return array
	 */
	private function build_user_data() {
		$logged_in = is_user_logged_in();
		$id = 0;
		$role = '';
		$is_admin = false;
		if ( $logged_in ) {
			$user = wp_get_current_user();
			$id = (int) $user->ID;
			$roles = (array) $user->roles;
			$role = ! empty( $roles[0] ) ? $roles[0] : '';
			$is_admin = user_can( $user, 'manage_options' );
		}
		return array(
			'logged_in' => $logged_in,
			'id'        => $id,
			'role'      => $role,
			'is_admin'  => $is_admin,
		);
	}

	/**
	 * Build request data (referrer, UTM params, visitor_type).
	 *
	 * @return array
	 */
	private function build_request_data() {
		$referrer = '';
		if ( ! empty( $_SERVER['HTTP_REFERER'] ) && is_string( $_SERVER['HTTP_REFERER'] ) ) {
			$referrer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		}
		$utm = array();
		$keys = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' );
		foreach ( $keys as $k ) {
			if ( isset( $_GET[ $k ] ) && is_string( $_GET[ $k ] ) ) {
				$utm[ $k ] = sanitize_text_field( wp_unslash( $_GET[ $k ] ) );
			}
		}
		$visitor_type = $this->detect_visitor_type();
		return array(
			'referrer'       => $referrer,
			'utm'            => $utm,
			'visitor_type'   => $visitor_type,
			'session_count'  => 0,
		);
	}

	/**
	 * Detect new vs returning visitor (cookie-based).
	 *
	 * @return string 'new' or 'returning'
	 */
	private function detect_visitor_type() {
		$count = isset( $_COOKIE['cro_visit_count'] ) ? absint( $_COOKIE['cro_visit_count'] ) : 0;
		if ( $count <= 1 ) {
			return 'new';
		}
		return 'returning';
	}

	/**
	 * Build time data (hour, day, timestamp).
	 *
	 * @return array
	 */
	private function build_time_data() {
		$ts = time();
		$hour = (int) gmdate( 'G', $ts );
		$day = (int) gmdate( 'w', $ts );
		$day_name = gmdate( 'l', $ts );
		return array(
			'hour'      => $hour,
			'day'       => $day,
			'day_name'  => $day_name,
			'timestamp' => $ts,
		);
	}

	/**
	 * Placeholder for behavioral data (updated from JS / REST trigger_data).
	 *
	 * @return array
	 */
	private function build_behavior_placeholder() {
		return array(
			'time_on_page'   => 0,
			'scroll_depth'   => 0,
			'has_interacted' => false,
			'idle_seconds'   => 0,
		);
	}

	/**
	 * Get a value by dot notation (e.g. 'cart.total', 'user.logged_in').
	 *
	 * @param string     $path    Dot-separated path.
	 * @param mixed|null $default Default if not found.
	 * @return mixed
	 */
	public function get( $path, $default = null ) {
		if ( ! is_string( $path ) || $path === '' ) {
			return $default;
		}
		$keys = explode( '.', $path );
		$cur = $this->data;
		foreach ( $keys as $key ) {
			$key = trim( $key );
			if ( $key === '' ) {
				return $default;
			}
			if ( ! is_array( $cur ) || ! array_key_exists( $key, $cur ) ) {
				return $default;
			}
			$cur = $cur[ $key ];
		}
		return $cur;
	}

	/**
	 * Set a value by dot notation (e.g. for behavioral updates from JS).
	 *
	 * @param string $path  Dot-separated path.
	 * @param mixed  $value Value to set.
	 */
	public function set( $path, $value ) {
		if ( ! is_string( $path ) || $path === '' ) {
			return;
		}
		$keys = explode( '.', $path );
		$cur = &$this->data;
		$max = count( $keys ) - 1;
		foreach ( $keys as $i => $key ) {
			$key = trim( $key );
			if ( $key === '' ) {
				return;
			}
			if ( $i === $max ) {
				$cur[ $key ] = $value;
				return;
			}
			if ( ! isset( $cur[ $key ] ) || ! is_array( $cur[ $key ] ) ) {
				$cur[ $key ] = array();
			}
			$cur = &$cur[ $key ];
		}
	}

	/**
	 * Evaluate a rule: does the value at $path match $operator and $value?
	 *
	 * Supported operators: =, !=, >, <, >=, <=, in, not_in, contains, exists, regex.
	 *
	 * @param string $path     Dot-separated path (e.g. 'cart.total', 'page_type').
	 * @param string $operator Operator.
	 * @param mixed  $value    Compare value (e.g. 'product', 50, ['mobile','tablet']).
	 * @return bool
	 */
	public function matches( $path, $operator, $value ) {
		$actual = $this->get( $path );

		switch ( $operator ) {
			case '=':
			case 'equals':
				return $actual == $value;
			case '!=':
			case 'not_equals':
				return $actual != $value;
			case '>':
				return $this->compare_numeric( $actual, $value ) > 0;
			case '<':
				return $this->compare_numeric( $actual, $value ) < 0;
			case '>=':
				return $this->compare_numeric( $actual, $value ) >= 0;
			case '<=':
				return $this->compare_numeric( $actual, $value ) <= 0;
			case 'in':
				$arr = is_array( $value ) ? $value : array( $value );
				return in_array( $actual, $arr, true ) || in_array( (string) $actual, array_map( 'strval', $arr ), true );
			case 'not_in':
				$arr = is_array( $value ) ? $value : array( $value );
				return ! in_array( $actual, $arr, true ) && ! in_array( (string) $actual, array_map( 'strval', $arr ), true );
			case 'contains':
				if ( is_array( $actual ) ) {
					$needle = $value;
					return in_array( $needle, $actual, true ) || in_array( (string) $needle, array_map( 'strval', $actual ), true );
				}
				return is_string( $actual ) && ( is_string( $value ) && strpos( $actual, (string) $value ) !== false );
			case 'exists':
				return $actual !== null && $actual !== '' && ( ! is_array( $actual ) || count( $actual ) > 0 );
			case 'not_exists':
				return $actual === null || $actual === '' || ( is_array( $actual ) && count( $actual ) === 0 );
			case 'regex':
				if ( ! is_string( $value ) || ! is_scalar( $actual ) ) {
					return false;
				}
				return (bool) preg_match( $value, (string) $actual );
			default:
				return false;
		}
	}

	/**
	 * Compare two values numerically for matches().
	 *
	 * @param mixed $a First value.
	 * @param mixed $b Second value.
	 * @return int -1, 0, or 1.
	 */
	private function compare_numeric( $a, $b ) {
		$a = is_numeric( $a ) ? (float) $a : 0;
		$b = is_numeric( $b ) ? (float) $b : 0;
		if ( $a < $b ) {
			return -1;
		}
		if ( $a > $b ) {
			return 1;
		}
		return 0;
	}

	/**
	 * Get full raw data (for debugging).
	 *
	 * @return array
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * Export safe subset for frontend/JavaScript (no sensitive user info).
	 *
	 * @return array
	 */
	public function to_frontend_array() {
		$cart = $this->data['cart'] ?? array();
		$user = $this->data['user'] ?? array();
		$request = $this->data['request'] ?? array();
		$time = $this->data['time'] ?? array();
		$behavior = $this->data['behavior'] ?? array();

		return array(
			'page_type'   => $this->data['page_type'] ?? 'other',
			'device_type' => $this->data['device_type'] ?? 'desktop',
			'cart'        => array(
				'total'            => (float) ( $cart['total'] ?? 0 ),
				'item_count'       => (int) ( $cart['item_count'] ?? 0 ),
				'categories'       => array_values( $cart['categories'] ?? array() ),
				'product_ids'      => array_values( $cart['product_ids'] ?? array() ),
				'sale_item_count'  => (int) ( $cart['sale_item_count'] ?? 0 ),
				'low_stock_count'  => (int) ( $cart['low_stock_count'] ?? 0 ),
				'has_items'        => ! empty( $cart['has_items'] ),
			),
			'user'        => array(
				'logged_in' => ! empty( $user['logged_in'] ),
				'is_admin'  => ! empty( $user['is_admin'] ),
				'role'      => (string) ( $user['role'] ?? '' ),
			),
			'request'     => array(
				'referrer'       => (string) ( $request['referrer'] ?? '' ),
				'utm'            => isset( $request['utm'] ) && is_array( $request['utm'] ) ? $request['utm'] : array(),
				'visitor_type'   => (string) ( $request['visitor_type'] ?? 'new' ),
				'session_count'  => (int) ( $request['session_count'] ?? 0 ),
			),
			'time'        => array(
				'hour'      => (int) ( $time['hour'] ?? 0 ),
				'day'       => (int) ( $time['day'] ?? 0 ),
				'day_name'  => (string) ( $time['day_name'] ?? '' ),
				'timestamp' => (int) ( $time['timestamp'] ?? 0 ),
			),
			'behavior'    => array(
				'time_on_page'   => (int) ( $behavior['time_on_page'] ?? 0 ),
				'scroll_depth'   => (int) ( $behavior['scroll_depth'] ?? 0 ),
				'has_interacted' => ! empty( $behavior['has_interacted'] ),
			),
		);
	}
}

/**
 * Get request-scoped context instance (cached per request for fast rule evaluation).
 *
 * @return CRO_Context
 */
function cro_get_request_context() {
	static $context = null;
	if ( $context === null ) {
		$context = new CRO_Context();
	}
	return $context;
}
