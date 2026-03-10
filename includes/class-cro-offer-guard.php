<?php
/**
 * Coupon and offer protection
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CRO_Offer_Guard class.
 *
 * Validates whether coupons/offers can be shown to a visitor
 * and records usage to prevent abuse.
 */
class CRO_Offer_Guard {

	/**
	 * Validate if coupon can be offered to this visitor/cart.
	 *
	 * @param string $coupon_code Coupon code.
	 * @param array  $context     Context with cart_value, cart_product_ids, etc.
	 * @return true|WP_Error True if valid, WP_Error with reasons if not.
	 */
	public function can_offer_coupon( $coupon_code, $context ) {
		$errors = array();

		if ( empty( $coupon_code ) || ! is_string( $coupon_code ) ) {
			return new WP_Error( 'invalid_coupon', __( 'Invalid coupon code.', 'meyvora-convert' ) );
		}

		// Check 1: Coupon exists and is valid.
		$coupon = new WC_Coupon( $coupon_code );
		if ( ! $coupon->get_id() ) {
			return new WP_Error( 'invalid_coupon', __( 'Coupon does not exist.', 'meyvora-convert' ) );
		}

		// Check 2: Minimum cart value.
		$min_amount = $coupon->get_minimum_amount();
		$cart_total = isset( $context['cart_value'] ) ? floatval( $context['cart_value'] ) : 0;

		if ( $min_amount && $cart_total < floatval( $min_amount ) ) {
			$errors[] = sprintf(
				/* translators: %s: minimum amount */
				__( 'Cart value below minimum (%s)', 'meyvora-convert' ),
				wc_price( $min_amount )
			);
		}

		// Check 3: One coupon per visitor (prevent abuse).
		$visitor_state = CRO_Visitor_State::get_instance();
		if ( $this->visitor_already_used_coupon( $visitor_state, $coupon_code ) ) {
			$errors[] = __( 'Visitor already used this coupon.', 'meyvora-convert' );
		}

		// Check 4: Exclude sale items check.
		if ( $coupon->get_exclude_sale_items() ) {
			if ( $this->cart_has_only_sale_items( $context ) ) {
				$errors[] = __( 'Cart contains only sale items.', 'meyvora-convert' );
			}
		}

		// Check 5: Product exclusions.
		$excluded_products = $coupon->get_excluded_product_ids();
		if ( ! empty( $excluded_products ) ) {
			$cart_products = isset( $context['cart_product_ids'] ) && is_array( $context['cart_product_ids'] ) ? $context['cart_product_ids'] : array();
			if ( ! empty( $cart_products ) ) {
				$excluded_ids = array_map( 'intval', $excluded_products );
				$cart_ids    = array_map( 'intval', $cart_products );
				$all_excluded = count( array_diff( $cart_ids, $excluded_ids ) ) === 0;
				if ( $all_excluded ) {
					$errors[] = __( 'All cart items are excluded from coupon.', 'meyvora-convert' );
				}
			}
		}

		// Check 6: Usage limit not reached.
		$usage_limit = $coupon->get_usage_limit();
		if ( $usage_limit && $coupon->get_usage_count() >= $usage_limit ) {
			$errors[] = __( 'Coupon usage limit reached.', 'meyvora-convert' );
		}

		// Check 7: Expiry.
		$expiry = $coupon->get_date_expires();
		if ( $expiry && $expiry->getTimestamp() < time() ) {
			$errors[] = __( 'Coupon has expired.', 'meyvora-convert' );
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'coupon_invalid', implode( ', ', $errors ) );
		}

		return true;
	}

	/**
	 * Check if visitor already used this coupon type.
	 *
	 * @param CRO_Visitor_State $visitor_state Visitor state instance.
	 * @param string            $coupon_code   Coupon code.
	 * @return bool
	 */
	private function visitor_already_used_coupon( $visitor_state, $coupon_code ) {
		return $visitor_state->has_used_coupon( $coupon_code );
	}

	/**
	 * Record coupon usage for visitor.
	 *
	 * @param string $coupon_code Coupon code that was applied.
	 */
	public function record_coupon_used( $coupon_code ) {
		if ( empty( $coupon_code ) ) {
			return;
		}
		$visitor_state = CRO_Visitor_State::get_instance();
		$visitor_state->record_coupon_used( $coupon_code );
	}

	/**
	 * Check if cart has only sale items.
	 *
	 * @param array $context Context (may include precomputed cart data).
	 * @return bool
	 */
	private function cart_has_only_sale_items( $context ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		$cart = WC()->cart->get_cart();
		if ( empty( $cart ) ) {
			return false;
		}

		foreach ( $cart as $item ) {
			$product = isset( $item['data'] ) ? $item['data'] : null;
			if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
				continue;
			}
			if ( ! $product->is_on_sale() ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get maximum discount allowed for this cart (configurable cap).
	 *
	 * @param float $cart_total Cart total amount.
	 * @return float Maximum discount amount.
	 */
	public function get_max_discount( $cart_total ) {
		$max_percent = cro_settings()->get( 'general', 'max_discount_percent', 25 );
		$max_percent = max( 0, min( 100, floatval( $max_percent ) ) );
		return ( $cart_total * $max_percent ) / 100;
	}

	/**
	 * Validate discount amount is within allowed maximum.
	 *
	 * @param float $discount   Proposed discount amount.
	 * @param float $cart_total Cart total.
	 * @return bool True if discount is allowed.
	 */
	public function validate_discount_amount( $discount, $cart_total ) {
		$max = $this->get_max_discount( $cart_total );
		return $discount <= $max;
	}
}

/**
 * Record coupon usage when WooCommerce applies a coupon.
 */
add_action(
	'woocommerce_applied_coupon',
	function( $coupon_code ) {
		$guard = new CRO_Offer_Guard();
		$guard->record_coupon_used( $coupon_code );
	},
	10,
	1
);
