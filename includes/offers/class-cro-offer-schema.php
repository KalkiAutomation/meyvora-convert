<?php
/**
 * Strict schema validator and sanitizer for dynamic offers.
 * Used before saving in CRUD handlers. Returns field-keyed errors for UI highlighting.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class CRO_Offer_Schema
 */
class CRO_Offer_Schema {

	const NAME_MAX_LENGTH       = 60;
	const PRIORITY_MIN          = 0;
	const PRIORITY_MAX          = 999;
	const REWARD_PERCENT_MIN    = 1;
	const REWARD_PERCENT_MAX    = 100;
	const REWARD_FIXED_MIN      = 0;
	const TTL_HOURS_MIN         = 1;
	const TTL_HOURS_MAX         = 720;
	const RATE_LIMIT_HOURS_MIN  = 1;
	const RATE_LIMIT_HOURS_MAX  = 168;
	const MAX_PER_VISITOR_MIN   = 1;
	const MAX_PER_VISITOR_MAX   = 10;

	const REWARD_TYPES = array( 'percent', 'fixed', 'free_shipping' );

	const CONDITIONS_KEYS = array(
		'min_cart_total',
		'max_cart_total',
		'min_items',
		'first_time',
		'first_time_customer',
		'returning_min_orders',
		'returning_customer_min_orders',
		'lifetime_spend_min',
		'allowed_roles',
		'excluded_roles',
		'exclude_sale_items',
		'include_categories',
		'exclude_categories',
		'include_products',
		'exclude_products',
		'min_qty_for_category',
		'cart_contains_category',
		'description',
	);

	/**
	 * Sanitize raw input (normalized or flat) into a single flat offer array with only allowed keys.
	 *
	 * @param array|mixed $raw Request data (normalized: name, conditions, reward, limits; or flat keys).
	 * @return array Flat offer suitable for storage and for validate_offer().
	 */
	public static function sanitize_offer( $raw ) {
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$flat = array();

		// Name / headline (accept both)
		$name = '';
		if ( isset( $raw['headline'] ) && is_scalar( $raw['headline'] ) ) {
			$name = sanitize_text_field( (string) $raw['headline'] );
		} elseif ( isset( $raw['name'] ) && is_scalar( $raw['name'] ) ) {
			$name = sanitize_text_field( (string) $raw['name'] );
		}
		$flat['headline'] = self::truncate_string( $name, self::NAME_MAX_LENGTH );

		// Priority (validate_offer enforces range)
		$flat['priority'] = isset( $raw['priority'] ) ? (int) $raw['priority'] : 10;

		// Enabled
		$flat['enabled'] = ! empty( $raw['enabled'] );

		// Conditions: from nested conditions or flat
		$conditions = isset( $raw['conditions'] ) && is_array( $raw['conditions'] ) ? $raw['conditions'] : $raw;
		$flat['min_cart_total']                = isset( $conditions['min_cart_total'] ) ? (float) $conditions['min_cart_total'] : 0;
		$flat['max_cart_total']                = isset( $conditions['max_cart_total'] ) ? (float) $conditions['max_cart_total'] : 0;
		$flat['min_items']                     = isset( $conditions['min_items'] ) ? absint( $conditions['min_items'] ) : 0;
		$first_time = isset( $conditions['first_time_customer'] ) ? $conditions['first_time_customer'] : ( isset( $conditions['first_time'] ) ? $conditions['first_time'] : false );
		$flat['first_time_customer']           = ! empty( $first_time );
		$returning = isset( $conditions['returning_customer_min_orders'] ) ? $conditions['returning_customer_min_orders'] : ( isset( $conditions['returning_min_orders'] ) ? $conditions['returning_min_orders'] : 0 );
		$flat['returning_customer_min_orders'] = absint( $returning );
		$flat['lifetime_spend_min']            = isset( $conditions['lifetime_spend_min'] ) ? (float) $conditions['lifetime_spend_min'] : 0;
		$flat['allowed_roles']                 = self::sanitize_string_array( isset( $conditions['allowed_roles'] ) ? $conditions['allowed_roles'] : array() );
		$flat['excluded_roles']                = self::sanitize_string_array( isset( $conditions['excluded_roles'] ) ? $conditions['excluded_roles'] : array() );
		$flat['exclude_sale_items']            = ! empty( $conditions['exclude_sale_items'] );
		$flat['include_categories']            = self::sanitize_int_array( isset( $conditions['include_categories'] ) ? $conditions['include_categories'] : array() );
		$flat['exclude_categories']            = self::sanitize_int_array( isset( $conditions['exclude_categories'] ) ? $conditions['exclude_categories'] : array() );
		$flat['include_products']              = self::sanitize_int_array( isset( $conditions['include_products'] ) ? $conditions['include_products'] : array() );
		$flat['exclude_products']               = self::sanitize_int_array( isset( $conditions['exclude_products'] ) ? $conditions['exclude_products'] : array() );
		$flat['min_qty_for_category']          = self::sanitize_min_qty_for_category( isset( $conditions['min_qty_for_category'] ) ? $conditions['min_qty_for_category'] : array() );
		$flat['cart_contains_category']        = self::sanitize_int_array( isset( $conditions['cart_contains_category'] ) ? $conditions['cart_contains_category'] : array() );
		$flat['description']                   = isset( $conditions['description'] ) ? sanitize_textarea_field( (string) $conditions['description'] ) : '';

		// Reward: from nested reward or flat
		$reward = isset( $raw['reward'] ) && is_array( $raw['reward'] ) ? $raw['reward'] : array();
		$type = isset( $reward['type'] ) ? $reward['type'] : ( isset( $raw['reward_type'] ) ? $raw['reward_type'] : 'percent' );
		$flat['reward_type'] = in_array( $type, self::REWARD_TYPES, true ) ? $type : 'percent';
		$flat['reward_amount'] = isset( $reward['amount'] ) ? (float) $reward['amount'] : ( isset( $raw['reward_amount'] ) ? (float) $raw['reward_amount'] : 10 );
		$ttl = isset( $reward['coupon_ttl_hours'] ) ? $reward['coupon_ttl_hours'] : ( isset( $reward['ttl_hours'] ) ? $reward['ttl_hours'] : ( isset( $raw['coupon_ttl_hours'] ) ? $raw['coupon_ttl_hours'] : 48 ) );
		$flat['coupon_ttl_hours'] = absint( $ttl );
		$flat['individual_use']   = ! empty( $reward['individual_use'] ) || ! empty( $raw['individual_use'] );

		// Reward restrictions (coupon applies to specific categories/products)
		$flat['apply_to_categories'] = self::sanitize_int_array( isset( $reward['apply_to_categories'] ) ? $reward['apply_to_categories'] : ( isset( $raw['apply_to_categories'] ) ? $raw['apply_to_categories'] : array() ) );
		$flat['apply_to_products']   = self::sanitize_int_array( isset( $reward['apply_to_products'] ) ? $reward['apply_to_products'] : ( isset( $raw['apply_to_products'] ) ? $raw['apply_to_products'] : array() ) );
		// Per-category discount: map category_id => amount (numeric).
		$per_cat = isset( $reward['per_category_discount'] ) ? $reward['per_category_discount'] : ( isset( $raw['per_category_discount'] ) ? $raw['per_category_discount'] : array() );
		if ( is_array( $per_cat ) ) {
			$flat['per_category_discount'] = array();
			foreach ( $per_cat as $k => $v ) {
				if ( is_numeric( $k ) && absint( $k ) > 0 && is_numeric( $v ) ) {
					$flat['per_category_discount'][ absint( $k ) ] = (float) $v;
				}
			}
		} else {
			$flat['per_category_discount'] = array();
		}

		// Limits (validate_offer enforces ranges)
		$limits = isset( $raw['limits'] ) && is_array( $raw['limits'] ) ? $raw['limits'] : $raw;
		$flat['rate_limit_hours']       = isset( $limits['rate_limit_hours'] ) ? absint( $limits['rate_limit_hours'] ) : ( isset( $raw['rate_limit_hours'] ) ? absint( $raw['rate_limit_hours'] ) : 6 );
		$max_pv                         = isset( $limits['max_coupons_per_visitor'] ) ? $limits['max_coupons_per_visitor'] : ( isset( $limits['max_per_visitor'] ) ? $limits['max_per_visitor'] : ( isset( $raw['max_coupons_per_visitor'] ) ? $raw['max_coupons_per_visitor'] : 1 ) );
		$flat['max_coupons_per_visitor'] = absint( $max_pv );

		$flat['conflict_offer_ids'] = self::sanitize_conflict_offer_ids( isset( $raw['conflict_offer_ids'] ) ? $raw['conflict_offer_ids'] : array() );

		return $flat;
	}

	/**
	 * Validate sanitized flat offer. Returns true or WP_Error with field keys as error codes.
	 *
	 * @param array $offer Sanitized flat offer (from sanitize_offer).
	 * @return true|WP_Error True if valid; WP_Error with one message per field (code = field key).
	 */
	public static function validate_offer( $offer ) {
		$err = new WP_Error();

		$name = isset( $offer['headline'] ) ? trim( (string) $offer['headline'] ) : '';
		if ( $name === '' ) {
			$err->add( 'headline', __( 'Offer name is required.', 'meyvora-convert' ) );
		}
		if ( mb_strlen( $name ) > self::NAME_MAX_LENGTH ) {
			$err->add( 'headline', sprintf( /* translators: %d is the maximum number of allowed characters. */ __( 'Name must be at most %d characters.', 'meyvora-convert' ), self::NAME_MAX_LENGTH ) );
		}

		$priority = isset( $offer['priority'] ) ? (int) $offer['priority'] : 10;
		if ( $priority < self::PRIORITY_MIN || $priority > self::PRIORITY_MAX ) {
			$err->add( 'priority', sprintf( /* translators: %1$d is the minimum value, %2$d is the maximum value. */ __( 'Priority must be between %1$d and %2$d.', 'meyvora-convert' ), self::PRIORITY_MIN, self::PRIORITY_MAX ) );
		}

		if ( array_key_exists( 'enabled', $offer ) && ! is_bool( $offer['enabled'] ) ) {
			$err->add( 'enabled', __( 'Enabled must be a boolean.', 'meyvora-convert' ) );
		}

		$reward_type = isset( $offer['reward_type'] ) ? $offer['reward_type'] : 'percent';
		if ( ! in_array( $reward_type, self::REWARD_TYPES, true ) ) {
			$err->add( 'reward_type', __( 'Reward type must be percent, fixed, or free_shipping.', 'meyvora-convert' ) );
		}

		$reward_amount = isset( $offer['reward_amount'] ) ? (float) $offer['reward_amount'] : 10;
		if ( $reward_type === 'percent' && ( $reward_amount < self::REWARD_PERCENT_MIN || $reward_amount > self::REWARD_PERCENT_MAX ) ) {
			$err->add( 'reward_amount', sprintf( /* translators: %1$d is the minimum percentage, %2$d is the maximum percentage. */ __( 'Percent discount must be between %1$d and %2$d.', 'meyvora-convert' ), self::REWARD_PERCENT_MIN, self::REWARD_PERCENT_MAX ) );
		}
		if ( $reward_type === 'fixed' && $reward_amount < self::REWARD_FIXED_MIN ) {
			$err->add( 'reward_amount', __( 'Fixed discount must be 0 or greater.', 'meyvora-convert' ) );
		}

		$ttl = isset( $offer['coupon_ttl_hours'] ) ? (int) $offer['coupon_ttl_hours'] : 48;
		if ( $ttl < self::TTL_HOURS_MIN || $ttl > self::TTL_HOURS_MAX ) {
			$err->add( 'coupon_ttl_hours', sprintf( /* translators: %1$d is the minimum hours, %2$d is the maximum hours. */ __( 'Coupon TTL must be between %1$d and %2$d hours.', 'meyvora-convert' ), self::TTL_HOURS_MIN, self::TTL_HOURS_MAX ) );
		}

		$rate_limit = isset( $offer['rate_limit_hours'] ) ? (int) $offer['rate_limit_hours'] : 6;
		if ( $rate_limit < self::RATE_LIMIT_HOURS_MIN || $rate_limit > self::RATE_LIMIT_HOURS_MAX ) {
			$err->add( 'rate_limit_hours', sprintf( /* translators: %1$d is the minimum hours, %2$d is the maximum hours. */ __( 'Rate limit must be between %1$d and %2$d hours.', 'meyvora-convert' ), self::RATE_LIMIT_HOURS_MIN, self::RATE_LIMIT_HOURS_MAX ) );
		}

		$max_pv = isset( $offer['max_coupons_per_visitor'] ) ? (int) $offer['max_coupons_per_visitor'] : 1;
		if ( $max_pv < self::MAX_PER_VISITOR_MIN || $max_pv > self::MAX_PER_VISITOR_MAX ) {
			$err->add( 'max_coupons_per_visitor', sprintf( /* translators: %1$d is the minimum value, %2$d is the maximum value. */ __( 'Max per visitor must be between %1$d and %2$d.', 'meyvora-convert' ), self::MAX_PER_VISITOR_MIN, self::MAX_PER_VISITOR_MAX ) );
		}

		$conf = isset( $offer['conflict_offer_ids'] ) ? $offer['conflict_offer_ids'] : array();
		if ( ! is_array( $conf ) ) {
			$err->add( 'conflict_offer_ids', __( 'Cannot combine with must be a list.', 'meyvora-convert' ) );
		} elseif ( count( $conf ) > 20 ) {
			$err->add( 'conflict_offer_ids', __( 'Too many conflict entries (max 20).', 'meyvora-convert' ) );
		}

		if ( $err->has_errors() ) {
			return $err;
		}
		return true;
	}

	/**
	 * Convert WP_Error to an associative array of field_key => message for JSON response.
	 *
	 * @param WP_Error $error Error object from validate_offer().
	 * @return array<string, string> Map of field key to message.
	 */
	public static function errors_to_array( WP_Error $error ) {
		$out = array();
		foreach ( $error->get_error_codes() as $code ) {
			$msg = $error->get_error_message( $code );
			if ( $msg !== '' ) {
				$out[ $code ] = $msg;
			}
		}
		return $out;
	}

	/**
	 * Truncate string to max length (mb-safe).
	 *
	 * @param string $s   Input.
	 * @param int    $max Max length.
	 * @return string
	 */
	private static function truncate_string( $s, $max ) {
		$s = (string) $s;
		if ( mb_strlen( $s ) <= $max ) {
			return $s;
		}
		return mb_substr( $s, 0, $max );
	}

	/**
	 * Sanitize array of strings (e.g. roles).
	 *
	 * @param mixed $input Input value.
	 * @return array
	 */
	private static function sanitize_string_array( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'sanitize_text_field', $input ), function ( $v ) {
			return $v !== '';
		} ) );
	}

	/**
	 * Sanitize array of positive integers (e.g. category IDs, product IDs).
	 *
	 * @param mixed $input Input value.
	 * @return int[]
	 */
	private static function sanitize_int_array( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'absint', $input ), function ( $v ) {
			return $v > 0;
		} ) );
	}

	/**
	 * Sanitize min_qty_for_category: map category_id => min_qty (positive ints).
	 *
	 * @param mixed $input Associative array or list of { category_id, min_qty }.
	 * @return array<int,int>
	 */
	/**
	 * Sanitize conflict_offer_ids: positive integers (DB) or non-empty strings (option UUID ids).
	 *
	 * @param mixed $input Raw list.
	 * @return array<int|string>
	 */
	private static function sanitize_conflict_offer_ids( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		$out = array();
		foreach ( $input as $v ) {
			if ( is_int( $v ) || ( is_string( $v ) && is_numeric( $v ) && strpos( $v, '.' ) === false ) ) {
				$n = absint( $v );
				if ( $n > 0 ) {
					$out[] = $n;
				}
			} elseif ( is_string( $v ) ) {
				$s = sanitize_text_field( $v );
				if ( $s !== '' ) {
					$out[] = $s;
				}
			}
		}
		return array_values( array_unique( $out, SORT_REGULAR ) );
	}

	private static function sanitize_min_qty_for_category( $input ) {
		$out = array();
		if ( ! is_array( $input ) ) {
			return $out;
		}
		foreach ( $input as $k => $v ) {
			if ( is_numeric( $k ) && is_numeric( $v ) ) {
				$cat_id  = absint( $k );
				$min_qty = max( 0, (int) $v );
				if ( $cat_id > 0 && $min_qty > 0 ) {
					$out[ $cat_id ] = $min_qty;
				}
			} elseif ( is_array( $v ) && isset( $v['category_id'] ) && isset( $v['min_qty'] ) ) {
				$cat_id  = absint( $v['category_id'] );
				$min_qty = max( 0, (int) $v['min_qty'] );
				if ( $cat_id > 0 && $min_qty > 0 ) {
					$out[ $cat_id ] = $min_qty;
				}
			}
		}
		return $out;
	}
}
