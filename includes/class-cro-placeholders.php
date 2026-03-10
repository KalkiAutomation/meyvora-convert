<?php
/**
 * Dynamic Content Placeholders
 *
 * Replace placeholders with real data (cart, user, product, store, coupon, time).
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CRO_Placeholders class.
 *
 * Replaces placeholders in content with real data; supports conditionals.
 */
class CRO_Placeholders {

	/**
	 * All available placeholders: tag => method name.
	 *
	 * @var array<string, string>
	 */
	private static $placeholders = array(
		// Cart placeholders
		'{cart_total}'                => 'get_cart_total',
		'{cart_subtotal}'             => 'get_cart_subtotal',
		'{cart_items}'                => 'get_cart_items_count',
		'{cart_savings}'              => 'get_cart_savings',
		'{amount_to_free_shipping}'   => 'get_amount_to_free_shipping',

		// User placeholders
		'{first_name}'                => 'get_user_first_name',
		'{last_name}'                 => 'get_user_last_name',
		'{user_name}'                 => 'get_user_display_name',
		'{user_email}'                => 'get_user_email',

		// Product placeholders (on product pages)
		'{product_name}'              => 'get_current_product_name',
		'{product_price}'             => 'get_current_product_price',
		'{product_sale_price}'         => 'get_current_product_sale_price',
		'{product_discount}'          => 'get_current_product_discount',

		// Store placeholders
		'{store_name}'                => 'get_store_name',
		'{currency}'                  => 'get_currency_symbol',

		// Coupon placeholders
		'{coupon_code}'               => 'get_coupon_code',
		'{coupon_discount}'           => 'get_coupon_discount',
		'{coupon_discount_amount}'    => 'get_coupon_discount_amount',

		// Time placeholders
		'{current_date}'              => 'get_current_date',
		'{current_day}'               => 'get_current_day',
		'{current_month}'             => 'get_current_month',
		'{current_year}'              => 'get_current_year',
	);

	/**
	 * Context data for processing (campaign, coupon_code, etc.).
	 *
	 * @var array
	 */
	private static $context = array();

	/**
	 * Process content and replace placeholders.
	 *
	 * @param string $content Content containing placeholders.
	 * @param array  $context Optional. Context (e.g. coupon_code, campaign).
	 * @return string Processed content.
	 */
	public static function process( $content, $context = array() ) {
		$content = is_string( $content ) ? $content : '';
		self::$context = is_array( $context ) ? $context : array();

		foreach ( self::$placeholders as $placeholder => $method ) {
			if ( strpos( $content, $placeholder ) !== false ) {
				$value = call_user_func( array( __CLASS__, $method ) );
				$content = str_replace( $placeholder, (string) $value, $content );
			}
		}

		// Process conditional placeholders {if:condition}...{/if}
		$content = self::process_conditionals( $content );

		return $content;
	}

	/**
	 * Process conditional content blocks.
	 *
	 * @param string $content Content with {if:xxx}...{/if} blocks.
	 * @return string
	 */
	private static function process_conditionals( $content ) {
		$pattern = '/\{if:(\w+)\}(.*?)\{\/if\}/s';

		return (string) preg_replace_callback( $pattern, function ( $matches ) {
			$condition     = isset( $matches[1] ) ? $matches[1] : '';
			$inner_content = isset( $matches[2] ) ? $matches[2] : '';
			if ( self::evaluate_condition( $condition ) ) {
				return $inner_content;
			}
			return '';
		}, $content );
	}

	/**
	 * Evaluate a condition for {if:condition}...{/if}.
	 *
	 * @param string $condition Condition key (e.g. cart_has_items, logged_in).
	 * @return bool
	 */
	private static function evaluate_condition( $condition ) {
		switch ( $condition ) {
			case 'cart_has_items':
				return function_exists( 'WC' ) && WC()->cart && WC()->cart->get_cart_contents_count() > 0;

			case 'cart_empty':
				return ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->get_cart_contents_count() === 0;

			case 'logged_in':
				return is_user_logged_in();

			case 'logged_out':
				return ! is_user_logged_in();

			case 'has_coupon':
				return ! empty( self::$context['coupon_code'] );

			case 'is_sale':
				global $product;
				return $product && is_a( $product, 'WC_Product' ) && $product->is_on_sale();

			default:
				return false;
		}
	}

	/**
	 * Get list of available placeholders for UI.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function get_available() {
		return array(
			__( 'Cart', 'meyvora-convert' )         => array(
				'{cart_total}'              => __( 'Cart total with currency (e.g., $125.00)', 'meyvora-convert' ),
				'{cart_subtotal}'           => __( 'Cart subtotal before discounts', 'meyvora-convert' ),
				'{cart_items}'               => __( 'Number of items in cart', 'meyvora-convert' ),
				'{cart_savings}'             => __( 'Amount saved with applied coupon', 'meyvora-convert' ),
				'{amount_to_free_shipping}'  => __( 'Amount needed for free shipping', 'meyvora-convert' ),
			),
			__( 'User', 'meyvora-convert' )         => array(
				'{first_name}'               => __( 'User first name (or "Friend" if unknown)', 'meyvora-convert' ),
				'{last_name}'                => __( 'User last name', 'meyvora-convert' ),
				'{user_name}'                => __( 'User display name', 'meyvora-convert' ),
				'{user_email}'               => __( 'User email address', 'meyvora-convert' ),
			),
			__( 'Product', 'meyvora-convert' )      => array(
				'{product_name}'             => __( 'Current product name (on product pages)', 'meyvora-convert' ),
				'{product_price}'            => __( 'Current product price', 'meyvora-convert' ),
				'{product_sale_price}'       => __( 'Product sale price', 'meyvora-convert' ),
				'{product_discount}'         => __( 'Product discount percentage', 'meyvora-convert' ),
			),
			__( 'Store', 'meyvora-convert' )        => array(
				'{store_name}'               => __( 'Your store name', 'meyvora-convert' ),
				'{currency}'                 => __( 'Currency symbol (e.g., $)', 'meyvora-convert' ),
			),
			__( 'Coupon', 'meyvora-convert' )       => array(
				'{coupon_code}'              => __( 'The coupon code being offered', 'meyvora-convert' ),
				'{coupon_discount}'           => __( 'Coupon discount (e.g., 10% or $5)', 'meyvora-convert' ),
			),
			__( 'Conditionals', 'meyvora-convert' ) => array(
				'{if:cart_has_items}...{/if}' => __( 'Show content only if cart has items', 'meyvora-convert' ),
				'{if:logged_in}...{/if}'      => __( 'Show content only to logged in users', 'meyvora-convert' ),
				'{if:has_coupon}...{/if}'     => __( 'Show content only if campaign has coupon', 'meyvora-convert' ),
			),
		);
	}

	// ==========================================
	// PLACEHOLDER METHODS
	// ==========================================

	/**
	 * @return string
	 */
	private static function get_cart_total() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || ! function_exists( 'wc_price' ) ) {
			return '0';
		}
		return wc_price( WC()->cart->get_total( 'raw' ) );
	}

	/**
	 * @return string
	 */
	private static function get_cart_subtotal() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || ! function_exists( 'wc_price' ) ) {
			return '0';
		}
		return wc_price( WC()->cart->get_subtotal() );
	}

	/**
	 * @return string
	 */
	private static function get_cart_items_count() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '0';
		}
		return (string) WC()->cart->get_cart_contents_count();
	}

	/**
	 * @return string
	 */
	private static function get_cart_savings() {
		$coupon_code = isset( self::$context['coupon_code'] ) ? self::$context['coupon_code'] : '';
		if ( ! $coupon_code || ! function_exists( 'WC' ) || ! WC()->cart || ! function_exists( 'wc_price' ) ) {
			return function_exists( 'wc_price' ) ? wc_price( 0 ) : '0';
		}
		if ( ! class_exists( 'WC_Coupon' ) ) {
			return function_exists( 'wc_price' ) ? wc_price( 0 ) : '0';
		}

		$coupon = new WC_Coupon( $coupon_code );
		if ( ! $coupon->get_id() ) {
			return function_exists( 'wc_price' ) ? wc_price( 0 ) : '0';
		}

		$discount_type  = $coupon->get_discount_type();
		$amount         = (float) $coupon->get_amount();
		$cart_subtotal  = (float) WC()->cart->get_subtotal();

		if ( $discount_type === 'percent' ) {
			$savings = $cart_subtotal * ( $amount / 100 );
		} else {
			$savings = min( $amount, $cart_subtotal );
		}

		return wc_price( $savings );
	}

	/**
	 * @return string
	 */
	private static function get_amount_to_free_shipping() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}

		$threshold = self::get_free_shipping_threshold();
		if ( ! $threshold ) {
			return '';
		}

		$cart_subtotal = (float) WC()->cart->get_subtotal();
		$remaining     = max( 0.0, $threshold - $cart_subtotal );

		if ( $remaining <= 0 ) {
			return __( 'You qualify for free shipping!', 'meyvora-convert' );
		}

		return function_exists( 'wc_price' ) ? wc_price( $remaining ) : (string) $remaining;
	}

	/**
	 * Get free shipping minimum order amount (from first free_shipping method found, or filter).
	 *
	 * @return float
	 */
	private static function get_free_shipping_threshold() {
		$threshold = 0.0;

		if ( function_exists( 'WC' ) && class_exists( 'WC_Shipping_Zones' ) && method_exists( 'WC_Shipping_Zones', 'get_zones' ) ) {
			$zones = WC_Shipping_Zones::get_zones();
			if ( is_array( $zones ) ) {
				foreach ( $zones as $zone ) {
					$methods = array();
					if ( is_object( $zone ) && method_exists( $zone, 'get_shipping_methods' ) ) {
						$methods = $zone->get_shipping_methods();
					} elseif ( is_array( $zone ) && isset( $zone['shipping_methods'] ) ) {
						$methods = $zone['shipping_methods'];
					}
					if ( ! is_array( $methods ) ) {
						continue;
					}
					foreach ( $methods as $method ) {
						$id = is_object( $method ) ? ( isset( $method->id ) ? $method->id : '' ) : ( isset( $method['id'] ) ? $method['id'] : '' );
						if ( $id !== 'free_shipping' ) {
							continue;
						}
						$min = is_object( $method ) ? ( isset( $method->min_amount ) ? $method->min_amount : null ) : ( isset( $method['min_amount'] ) ? $method['min_amount'] : null );
						if ( $min !== null && $min !== '' ) {
							$threshold = (float) $min;
							break 2;
						}
						// WC_Shipping_Free::instance_id etc – some versions use instance_settings
						if ( is_object( $method ) && method_exists( $method, 'get_option' ) ) {
							$min = $method->get_option( 'min_amount' );
							if ( $min !== null && $min !== '' ) {
								$threshold = (float) $min;
								break 2;
							}
						}
					}
				}
			}
		}

		return (float) apply_filters( 'cro_free_shipping_threshold', $threshold );
	}

	/**
	 * @return string
	 */
	private static function get_user_first_name() {
		if ( ! is_user_logged_in() ) {
			return __( 'Friend', 'meyvora-convert' );
		}

		$user       = wp_get_current_user();
		$first_name = isset( $user->first_name ) ? trim( (string) $user->first_name ) : '';
		if ( $first_name === '' && ! empty( $user->ID ) ) {
			$first_name = trim( (string) get_user_meta( $user->ID, 'billing_first_name', true ) );
		}
		return $first_name !== '' ? $first_name : ( isset( $user->display_name ) ? (string) $user->display_name : __( 'Friend', 'meyvora-convert' ) );
	}

	/**
	 * @return string
	 */
	private static function get_user_last_name() {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$user = wp_get_current_user();
		$last = isset( $user->last_name ) ? trim( (string) $user->last_name ) : '';
		if ( $last === '' && ! empty( $user->ID ) ) {
			$last = trim( (string) get_user_meta( $user->ID, 'billing_last_name', true ) );
		}
		return $last;
	}

	/**
	 * @return string
	 */
	private static function get_user_display_name() {
		if ( ! is_user_logged_in() ) {
			return __( 'Guest', 'meyvora-convert' );
		}
		$user = wp_get_current_user();
		return isset( $user->display_name ) ? (string) $user->display_name : __( 'Guest', 'meyvora-convert' );
	}

	/**
	 * @return string
	 */
	private static function get_user_email() {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$user = wp_get_current_user();
		return isset( $user->user_email ) ? (string) $user->user_email : '';
	}

	/**
	 * @return string
	 */
	private static function get_current_product_name() {
		global $product;
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return '';
		}
		return $product->get_name();
	}

	/**
	 * @return string
	 */
	private static function get_current_product_price() {
		global $product;
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return '';
		}
		return $product->get_price_html();
	}

	/**
	 * @return string
	 */
	private static function get_current_product_sale_price() {
		global $product;
		if ( ! $product || ! is_a( $product, 'WC_Product' ) || ! $product->is_on_sale() ) {
			return '';
		}
		return function_exists( 'wc_price' ) ? wc_price( $product->get_sale_price() ) : (string) $product->get_sale_price();
	}

	/**
	 * @return string
	 */
	private static function get_current_product_discount() {
		global $product;
		if ( ! $product || ! is_a( $product, 'WC_Product' ) || ! $product->is_on_sale() ) {
			return '';
		}

		$regular = (float) $product->get_regular_price();
		$sale    = (float) $product->get_sale_price();

		if ( $regular <= 0 ) {
			return '';
		}

		$discount = ( ( $regular - $sale ) / $regular ) * 100;
		return (string) round( $discount ) . '%';
	}

	/**
	 * @return string
	 */
	private static function get_store_name() {
		return (string) get_bloginfo( 'name' );
	}

	/**
	 * @return string
	 */
	private static function get_currency_symbol() {
		return function_exists( 'get_woocommerce_currency_symbol' ) ? (string) get_woocommerce_currency_symbol() : '';
	}

	/**
	 * @return string
	 */
	private static function get_coupon_code() {
		return isset( self::$context['coupon_code'] ) ? (string) self::$context['coupon_code'] : '';
	}

	/**
	 * @return string
	 */
	private static function get_coupon_discount() {
		$coupon_code = isset( self::$context['coupon_code'] ) ? self::$context['coupon_code'] : '';
		if ( ! $coupon_code || ! class_exists( 'WC_Coupon' ) ) {
			return '';
		}

		$coupon = new WC_Coupon( $coupon_code );
		if ( ! $coupon->get_id() ) {
			return '';
		}

		$discount_type = $coupon->get_discount_type();
		$amount        = $coupon->get_amount();

		if ( $discount_type === 'percent' ) {
			return (string) $amount . '%';
		}
		return function_exists( 'wc_price' ) ? wc_price( $amount ) : (string) $amount;
	}

	/**
	 * @return string
	 */
	private static function get_coupon_discount_amount() {
		return self::get_cart_savings();
	}

	/**
	 * @return string
	 */
	private static function get_current_date() {
		return (string) current_time( get_option( 'date_format' ) );
	}

	/**
	 * @return string
	 */
	private static function get_current_day() {
		return (string) current_time( 'l' );
	}

	/**
	 * @return string
	 */
	private static function get_current_month() {
		return (string) current_time( 'F' );
	}

	/**
	 * @return string
	 */
	private static function get_current_year() {
		return (string) current_time( 'Y' );
	}
}
