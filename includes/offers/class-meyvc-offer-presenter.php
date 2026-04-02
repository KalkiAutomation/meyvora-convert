<?php
/**
 * Human-readable summaries for offers (conditions, reward). Max 80 chars, safe for output.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MEYVC_Offer_Presenter
 */
class MEYVC_Offer_Presenter {

	const MAX_LENGTH = 80;

	/**
	 * Get conditions array from offer (supports flat or nested format).
	 *
	 * @param array $offer Offer data (flat keys or conditions/reward/limits).
	 * @return array
	 */
	private static function get_conditions( $offer ) {
		if ( ! is_array( $offer ) ) {
			return array();
		}
		if ( isset( $offer['conditions'] ) && is_array( $offer['conditions'] ) ) {
			return $offer['conditions'];
		}
		return array(
			'min_cart_total'                => isset( $offer['min_cart_total'] ) ? $offer['min_cart_total'] : 0,
			'max_cart_total'                => isset( $offer['max_cart_total'] ) ? $offer['max_cart_total'] : 0,
			'min_items'                     => isset( $offer['min_items'] ) ? $offer['min_items'] : 0,
			'first_time_customer'           => ! empty( $offer['first_time_customer'] ),
			'returning_customer_min_orders' => isset( $offer['returning_customer_min_orders'] ) ? (int) $offer['returning_customer_min_orders'] : 0,
			'lifetime_spend_min'            => isset( $offer['lifetime_spend_min'] ) ? $offer['lifetime_spend_min'] : 0,
			'allowed_roles'                 => isset( $offer['allowed_roles'] ) ? $offer['allowed_roles'] : array(),
			'excluded_roles'                => isset( $offer['excluded_roles'] ) ? $offer['excluded_roles'] : array(),
			'include_categories'            => isset( $offer['include_categories'] ) ? $offer['include_categories'] : array(),
			'exclude_categories'            => isset( $offer['exclude_categories'] ) ? $offer['exclude_categories'] : array(),
			'include_products'              => isset( $offer['include_products'] ) ? $offer['include_products'] : array(),
			'exclude_products'              => isset( $offer['exclude_products'] ) ? $offer['exclude_products'] : array(),
			'exclude_sale_items'            => ! empty( $offer['exclude_sale_items'] ),
			'min_qty_for_category'         => isset( $offer['min_qty_for_category'] ) ? $offer['min_qty_for_category'] : array(),
			'cart_contains_category'        => isset( $offer['cart_contains_category'] ) ? $offer['cart_contains_category'] : array(),
		);
	}

	/**
	 * Get reward array from offer (supports flat or nested format).
	 *
	 * @param array $offer Offer data.
	 * @return array
	 */
	private static function get_reward( $offer ) {
		if ( ! is_array( $offer ) ) {
			return array();
		}
		if ( isset( $offer['reward'] ) && is_array( $offer['reward'] ) ) {
			return $offer['reward'];
		}
		return array(
			'type'            => isset( $offer['reward_type'] ) ? $offer['reward_type'] : 'percent',
			'amount'          => isset( $offer['reward_amount'] ) ? (float) $offer['reward_amount'] : 0,
			'coupon_ttl_hours' => isset( $offer['coupon_ttl_hours'] ) ? (int) $offer['coupon_ttl_hours'] : 48,
			'individual_use'   => ! empty( $offer['individual_use'] ),
		);
	}

	/**
	 * Format price for display (plain text, no HTML).
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	private static function format_price( $amount ) {
		$formatted = number_format_i18n( (float) $amount, 2 );
		if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
			$formatted = get_woocommerce_currency_symbol() . $formatted;
		}
		return $formatted;
	}

	/**
	 * Truncate string to max length (safe, no HTML).
	 *
	 * @param string $s   Text.
	 * @param int    $max Max length.
	 * @return string
	 */
	private static function truncate( $s, $max = self::MAX_LENGTH ) {
		$s = is_string( $s ) ? $s : '';
		if ( strlen( $s ) <= $max ) {
			return $s;
		}
		return substr( $s, 0, $max - 3 ) . '...';
	}

	/**
	 * Human-readable conditions summary. Max 80 chars, safe for esc_html().
	 *
	 * Examples:
	 * - "Cart ≥ $50 • Returning customer (≥2 orders)"
	 * - "VIP spend ≥ $500 • Logged-in only"
	 *
	 * @param array $offer Offer data (flat or nested).
	 * @return string
	 */
	public static function summarize_conditions( $offer ) {
		$c = self::get_conditions( $offer );
		$parts = array();

		$min_cart = isset( $c['min_cart_total'] ) ? (float) $c['min_cart_total'] : 0;
		$max_cart = isset( $c['max_cart_total'] ) ? (float) $c['max_cart_total'] : 0;
		if ( $min_cart > 0 && $max_cart > 0 ) {
			$parts[] = sprintf(
				/* translators: 1: min price, 2: max price */
				__( 'Cart %1$s–%2$s', 'meyvora-convert' ),
				self::format_price( $min_cart ),
				self::format_price( $max_cart )
			);
		} elseif ( $min_cart > 0 ) {
			$parts[] = sprintf(
				/* translators: %s: minimum price */
				__( 'Cart ≥ %s', 'meyvora-convert' ),
				self::format_price( $min_cart )
			);
		} elseif ( $max_cart > 0 ) {
			$parts[] = sprintf(
				/* translators: %s: maximum price */
				__( 'Cart ≤ %s', 'meyvora-convert' ),
				self::format_price( $max_cart )
			);
		}

		$min_items = isset( $c['min_items'] ) ? (int) $c['min_items'] : 0;
		if ( $min_items > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: number of items */
				_n( '%d item', '%d items', $min_items, 'meyvora-convert' ),
				$min_items
			);
		}

		if ( ! empty( $c['first_time_customer'] ) ) {
			$parts[] = __( 'First-time customer', 'meyvora-convert' );
		}

		$returning = isset( $c['returning_customer_min_orders'] ) ? (int) $c['returning_customer_min_orders'] : 0;
		if ( $returning > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: minimum order count */
				__( 'Returning customer (≥%d orders)', 'meyvora-convert' ),
				$returning
			);
		}

		$lifetime = isset( $c['lifetime_spend_min'] ) ? (float) $c['lifetime_spend_min'] : 0;
		if ( $lifetime > 0 ) {
			$parts[] = sprintf(
				/* translators: %s: minimum spend amount */
				__( 'VIP spend ≥ %s', 'meyvora-convert' ),
				self::format_price( $lifetime )
			);
		}

		$allowed = isset( $c['allowed_roles'] ) && is_array( $c['allowed_roles'] ) ? array_filter( $c['allowed_roles'] ) : array();
		$excluded = isset( $c['excluded_roles'] ) && is_array( $c['excluded_roles'] ) ? array_filter( $c['excluded_roles'] ) : array();
		if ( ! empty( $allowed ) && ! in_array( 'guest', array_map( 'strtolower', $allowed ), true ) ) {
			$parts[] = __( 'Logged-in only', 'meyvora-convert' );
		} elseif ( ! empty( $excluded ) ) {
			$parts[] = __( 'Role restrictions', 'meyvora-convert' );
		}

		$inc_cats = isset( $c['include_categories'] ) && is_array( $c['include_categories'] ) ? array_filter( array_map( 'absint', $c['include_categories'] ) ) : array();
		if ( ! empty( $inc_cats ) ) {
			$parts[] = sprintf( /* translators: %d is the number of included product categories. */ __( 'Categories only (%d)', 'meyvora-convert' ), count( $inc_cats ) );
		}
		$exc_cats = isset( $c['exclude_categories'] ) && is_array( $c['exclude_categories'] ) ? array_filter( array_map( 'absint', $c['exclude_categories'] ) ) : array();
		if ( ! empty( $exc_cats ) ) {
			$parts[] = sprintf( /* translators: %d is the number of excluded product categories. */ __( 'Excl. categories (%d)', 'meyvora-convert' ), count( $exc_cats ) );
		}
		$inc_prod = isset( $c['include_products'] ) && is_array( $c['include_products'] ) ? array_filter( array_map( 'absint', $c['include_products'] ) ) : array();
		if ( ! empty( $inc_prod ) ) {
			$parts[] = sprintf( /* translators: %d is the number of included products. */ __( 'Products only (%d)', 'meyvora-convert' ), count( $inc_prod ) );
		}
		$exc_prod = isset( $c['exclude_products'] ) && is_array( $c['exclude_products'] ) ? array_filter( array_map( 'absint', $c['exclude_products'] ) ) : array();
		if ( ! empty( $exc_prod ) ) {
			$parts[] = sprintf( /* translators: %d is the number of excluded products. */ __( 'Excl. products (%d)', 'meyvora-convert' ), count( $exc_prod ) );
		}
		if ( ! empty( $c['exclude_sale_items'] ) ) {
			$parts[] = __( 'No sale items', 'meyvora-convert' );
		}
		$min_qty_cat = isset( $c['min_qty_for_category'] ) && is_array( $c['min_qty_for_category'] ) ? $c['min_qty_for_category'] : array();
		if ( ! empty( $min_qty_cat ) ) {
			$n = count( $min_qty_cat );
			$parts[] = sprintf( /* translators: %d is the number of category rules. */ _n( 'Min qty for category (%d)', 'Min qty for categories (%d)', $n, 'meyvora-convert' ), $n );
		}
		$cart_cat = isset( $c['cart_contains_category'] ) && is_array( $c['cart_contains_category'] ) ? array_filter( array_map( 'absint', $c['cart_contains_category'] ) ) : array();
		if ( ! empty( $cart_cat ) ) {
			$parts[] = sprintf( /* translators: %d is the number of required cart categories. */ __( 'Cart has category (%d)', 'meyvora-convert' ), count( $cart_cat ) );
		}

		if ( empty( $parts ) ) {
			return __( 'Any cart', 'meyvora-convert' );
		}

		$sep = ' • ';
		$out = implode( $sep, $parts );
		return self::truncate( $out );
	}

	/**
	 * Human-readable reward summary. Max 80 chars, safe for esc_html().
	 *
	 * Examples:
	 * - "10% off • Expires in 48h"
	 * - "$5 off • Single-use"
	 * - "Free shipping • Expires in 24h"
	 *
	 * @param array $offer Offer data (flat or nested).
	 * @return string
	 */
	public static function summarize_reward( $offer ) {
		$r = self::get_reward( $offer );
		$type   = isset( $r['type'] ) ? $r['type'] : 'percent';
		$amount = isset( $r['amount'] ) ? (float) $r['amount'] : 0;
		$ttl    = isset( $r['coupon_ttl_hours'] ) ? (int) $r['coupon_ttl_hours'] : 48;
		$single = ! empty( $r['individual_use'] );

		$reward_part = '';
		if ( $type === 'free_shipping' ) {
			$reward_part = __( 'Free shipping', 'meyvora-convert' );
		} elseif ( $type === 'percent' ) {
			$reward_part = sprintf(
				/* translators: %s: percentage number */
				__( '%s%% off', 'meyvora-convert' ),
				number_format_i18n( $amount, 0 )
			);
		} elseif ( $type === 'fixed' ) {
			$reward_part = sprintf(
				/* translators: %s: fixed amount (e.g. $5) */
				__( '%s off', 'meyvora-convert' ),
				self::format_price( $amount )
			);
		} else {
			$reward_part = __( 'Discount', 'meyvora-convert' );
		}

		$extra = array();
		if ( $ttl > 0 ) {
			$extra[] = sprintf(
				/* translators: %d: hours */
				__( 'Expires in %dh', 'meyvora-convert' ),
				$ttl
			);
		}
		if ( $single ) {
			$extra[] = __( 'Single-use', 'meyvora-convert' );
		}
		$apply_cats = isset( $offer['apply_to_categories'] ) && is_array( $offer['apply_to_categories'] ) ? array_filter( array_map( 'absint', $offer['apply_to_categories'] ) ) : array();
		$apply_prod = isset( $offer['apply_to_products'] ) && is_array( $offer['apply_to_products'] ) ? array_filter( array_map( 'absint', $offer['apply_to_products'] ) ) : array();
		if ( ! empty( $apply_cats ) ) {
			$extra[] = sprintf( /* translators: %d is the number of categories the offer applies to. */ _n( 'Applies to %d category', 'Applies to %d categories', count( $apply_cats ), 'meyvora-convert' ), count( $apply_cats ) );
		}
		if ( ! empty( $apply_prod ) ) {
			$extra[] = sprintf( /* translators: %d is the number of products the offer applies to. */ _n( 'Applies to %d product', 'Applies to %d products', count( $apply_prod ), 'meyvora-convert' ), count( $apply_prod ) );
		}

		if ( ! empty( $extra ) ) {
			$reward_part .= ' • ' . implode( ' • ', $extra );
		}

		return self::truncate( $reward_part );
	}
}
