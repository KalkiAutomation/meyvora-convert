<?php
/**
 * Offer rules: pure evaluation helpers for configuration-driven offer conditions.
 * No DB, no coupons — rule_key => callable( $value, $context ) returning bool.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MEYVC_Offer_Rules class.
 */
class MEYVC_Offer_Rules {

	/**
	 * Get the default set of rule evaluators.
	 * Each entry is rule_key => callable( $value, $context ) returning bool.
	 * Context keys: cart_total, cart_items_count, user_id, is_logged_in, user_role, order_count, lifetime_spend, visitor_id.
	 *
	 * @return array<string, callable>
	 */
	public static function get_evaluators() {
		return array(
			'min_cart_total'                => array( __CLASS__, 'evaluate_min_cart_total' ),
			'max_cart_total'                => array( __CLASS__, 'evaluate_max_cart_total' ),
			'min_items'                     => array( __CLASS__, 'evaluate_min_items' ),
			'first_time_customer'          => array( __CLASS__, 'evaluate_first_time_customer' ),
			'returning_customer_min_orders' => array( __CLASS__, 'evaluate_returning_customer_min_orders' ),
			'returning_customer'            => array( __CLASS__, 'evaluate_returning_customer_min_orders' ), // Backward compat.
			'lifetime_spend_min'            => array( __CLASS__, 'evaluate_lifetime_spend_min' ),
			'allowed_roles'                 => array( __CLASS__, 'evaluate_allowed_roles' ),
			'excluded_roles'                => array( __CLASS__, 'evaluate_excluded_roles' ),
			'user_roles'                    => array( __CLASS__, 'evaluate_user_roles_legacy' ), // Backward compat: { include, exclude }.
			'include_categories'            => array( __CLASS__, 'evaluate_include_categories' ),
			'exclude_categories'            => array( __CLASS__, 'evaluate_exclude_categories' ),
			'include_products'              => array( __CLASS__, 'evaluate_include_products' ),
			'exclude_products'              => array( __CLASS__, 'evaluate_exclude_products' ),
			'exclude_sale_items'            => array( __CLASS__, 'evaluate_exclude_sale_items' ),
			'min_qty_for_category'         => array( __CLASS__, 'evaluate_min_qty_for_category' ),
			'cart_contains_category'       => array( __CLASS__, 'evaluate_cart_contains_category' ),
		);
	}

	/**
	 * Evaluate a single rule.
	 *
	 * @param string $key     Rule key (e.g. min_cart_total).
	 * @param mixed  $value   Rule value from offer conditions config.
	 * @param array  $context Context (cart_total, cart_items_count, user_id, is_logged_in, user_role, order_count, lifetime_spend, visitor_id).
	 * @param array|null $evaluators Optional. Map of rule_key => callable. Default from get_evaluators() (not filtered here).
	 * @return bool True if rule passes or key has no evaluator (treated as pass).
	 */
	public static function evaluate( $key, $value, array $context, ?array $evaluators = null ) {
		if ( $evaluators === null ) {
			$evaluators = self::get_evaluators();
		}
		if ( ! isset( $evaluators[ $key ] ) || ! is_callable( $evaluators[ $key ] ) ) {
			return true;
		}
		return (bool) call_user_func( $evaluators[ $key ], $value, $context );
	}

	/**
	 * Evaluate all rules for an offer. All must pass (AND).
	 *
	 * @param array $conditions Associative array rule_key => value.
	 * @param array      $context    Context array.
	 * @param array|null $evaluators Optional. Map of rule_key => callable.
	 * @return bool
	 */
	public static function evaluate_all( array $conditions, array $context, ?array $evaluators = null ) {
		if ( empty( $conditions ) ) {
			return true;
		}
		if ( $evaluators === null ) {
			$evaluators = self::get_evaluators();
		}
		foreach ( $conditions as $key => $value ) {
			if ( ! self::evaluate( (string) $key, $value, $context, $evaluators ) ) {
				return false;
			}
		}
		return true;
	}

	// --- Built-in evaluators (pure: $value + $context → bool) ---

	/** @param mixed $value Min cart total (number). */
	public static function evaluate_min_cart_total( $value, array $context ) {
		$min   = is_numeric( $value ) ? (float) $value : 0.0;
		$total = isset( $context['cart_total'] ) && is_numeric( $context['cart_total'] ) ? (float) $context['cart_total'] : 0.0;
		return $total >= $min;
	}

	/** @param mixed $value Max cart total (number). 0 or empty = no max. */
	public static function evaluate_max_cart_total( $value, array $context ) {
		$max = is_numeric( $value ) ? (float) $value : 0.0;
		if ( $max <= 0 ) {
			return true;
		}
		$total = isset( $context['cart_total'] ) && is_numeric( $context['cart_total'] ) ? (float) $context['cart_total'] : 0.0;
		return $total <= $max;
	}

	/** @param mixed $value Min cart items count. */
	public static function evaluate_min_items( $value, array $context ) {
		$min   = is_numeric( $value ) ? (int) $value : 0;
		$count = isset( $context['cart_items_count'] ) ? (int) $context['cart_items_count'] : 0;
		return $count >= $min;
	}

	/** @param mixed $value Truthy = only first-time customers (order_count < 1). */
	public static function evaluate_first_time_customer( $value, array $context ) {
		if ( ! $value ) {
			return true;
		}
		$orders = isset( $context['order_count'] ) ? (int) $context['order_count'] : 0;
		return $orders < 1;
	}

	/** @param mixed $value Min order count (returning customer: order_count >= N). */
	public static function evaluate_returning_customer_min_orders( $value, array $context ) {
		$n      = is_numeric( $value ) ? (int) $value : 1;
		$orders = isset( $context['order_count'] ) ? (int) $context['order_count'] : 0;
		return $orders >= $n;
	}

	/** @param mixed $value Min lifetime spend (number). */
	public static function evaluate_lifetime_spend_min( $value, array $context ) {
		$min   = is_numeric( $value ) ? (float) $value : 0.0;
		$spend = isset( $context['lifetime_spend'] ) && is_numeric( $context['lifetime_spend'] ) ? (float) $context['lifetime_spend'] : 0.0;
		return $spend >= $min;
	}

	/** @param mixed $value Array of role slugs. Pass if user_role is in list (or not logged in and list empty = no restriction). */
	public static function evaluate_allowed_roles( $value, array $context ) {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return true;
		}
		$role = isset( $context['user_role'] ) ? (string) $context['user_role'] : '';
		return in_array( $role, $value, true );
	}

	/** @param mixed $value Array of role slugs. Pass if user_role is not in list. */
	public static function evaluate_excluded_roles( $value, array $context ) {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return true;
		}
		$role = isset( $context['user_role'] ) ? (string) $context['user_role'] : '';
		return ! in_array( $role, $value, true );
	}

	/**
	 * Legacy user_roles: value with 'include' and/or 'exclude' arrays.
	 *
	 * @param mixed $value Array with 'include' and/or 'exclude' (array of role slugs).
	 */
	public static function evaluate_user_roles_legacy( $value, array $context ) {
		if ( ! is_array( $value ) ) {
			return true;
		}
		$exclude = isset( $value['exclude'] ) && is_array( $value['exclude'] ) ? $value['exclude'] : null;
		$include = isset( $value['include'] ) && is_array( $value['include'] ) ? $value['include'] : null;
		$role    = isset( $context['user_role'] ) ? (string) $context['user_role'] : '';
		if ( $exclude !== null && in_array( $role, $exclude, true ) ) {
			return false;
		}
		if ( $include !== null && ! empty( $include ) && ! in_array( $role, $include, true ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Get cart_items from context (array of product_id, quantity, category_ids, on_sale).
	 *
	 * @param array $context Context from build_context().
	 * @return array
	 */
	private static function get_cart_items( array $context ) {
		return isset( $context['cart_items'] ) && is_array( $context['cart_items'] ) ? $context['cart_items'] : array();
	}

	/** @param mixed $value Array of category term IDs. Pass if every cart product is in at least one of these categories. */
	public static function evaluate_include_categories( $value, array $context ) {
		$allowed = self::normalize_int_array( $value );
		if ( empty( $allowed ) ) {
			return true;
		}
		$items = self::get_cart_items( $context );
		if ( empty( $items ) ) {
			return false;
		}
		foreach ( $items as $item ) {
			$cats = isset( $item['category_ids'] ) && is_array( $item['category_ids'] ) ? $item['category_ids'] : array();
			if ( empty( array_intersect( $cats, $allowed ) ) ) {
				return false;
			}
		}
		return true;
	}

	/** @param mixed $value Array of category term IDs. Pass if no cart product is in any of these. */
	public static function evaluate_exclude_categories( $value, array $context ) {
		$excluded = self::normalize_int_array( $value );
		if ( empty( $excluded ) ) {
			return true;
		}
		$items = self::get_cart_items( $context );
		foreach ( $items as $item ) {
			$cats = isset( $item['category_ids'] ) && is_array( $item['category_ids'] ) ? $item['category_ids'] : array();
			if ( ! empty( array_intersect( $cats, $excluded ) ) ) {
				return false;
			}
		}
		return true;
	}

	/** @param mixed $value Array of product IDs. Pass if cart contains at least one of these products. */
	public static function evaluate_include_products( $value, array $context ) {
		$allowed = self::normalize_int_array( $value );
		if ( empty( $allowed ) ) {
			return true;
		}
		$items = self::get_cart_items( $context );
		if ( empty( $items ) ) {
			return false;
		}
		$cart_product_ids = array();
		foreach ( $items as $item ) {
			if ( isset( $item['product_id'] ) ) {
				$cart_product_ids[] = (int) $item['product_id'];
			}
		}
		return ! empty( array_intersect( $cart_product_ids, $allowed ) );
	}

	/** @param mixed $value Array of product IDs. Pass if cart does not contain any of these. */
	public static function evaluate_exclude_products( $value, array $context ) {
		$excluded = self::normalize_int_array( $value );
		if ( empty( $excluded ) ) {
			return true;
		}
		$items = self::get_cart_items( $context );
		foreach ( $items as $item ) {
			if ( isset( $item['product_id'] ) && in_array( (int) $item['product_id'], $excluded, true ) ) {
				return false;
			}
		}
		return true;
	}

	/** @param mixed $value Truthy = cart must not contain any sale items. */
	public static function evaluate_exclude_sale_items( $value, array $context ) {
		if ( ! $value ) {
			return true;
		}
		$items = self::get_cart_items( $context );
		foreach ( $items as $item ) {
			if ( ! empty( $item['on_sale'] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param mixed $value Array of category_id => min_qty (or [['category_id'=>x,'min_qty'=>y]]). Pass if each category has total qty >= min.
	 */
	public static function evaluate_min_qty_for_category( $value, array $context ) {
		$rules = self::normalize_min_qty_for_category( $value );
		if ( empty( $rules ) ) {
			return true;
		}
		$items   = self::get_cart_items( $context );
		$qty_by_cat = array();
		foreach ( $items as $item ) {
			$qty = isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;
			$cats = isset( $item['category_ids'] ) && is_array( $item['category_ids'] ) ? $item['category_ids'] : array();
			foreach ( $cats as $cat_id ) {
				$cat_id = (int) $cat_id;
				$qty_by_cat[ $cat_id ] = isset( $qty_by_cat[ $cat_id ] ) ? $qty_by_cat[ $cat_id ] + $qty : $qty;
			}
		}
		foreach ( $rules as $cat_id => $min_qty ) {
			$total = isset( $qty_by_cat[ $cat_id ] ) ? (int) $qty_by_cat[ $cat_id ] : 0;
			if ( $total < $min_qty ) {
				return false;
			}
		}
		return true;
	}

	/** @param mixed $value Array of category term IDs. Pass if cart has at least one product in any of these. */
	public static function evaluate_cart_contains_category( $value, array $context ) {
		$categories = self::normalize_int_array( $value );
		if ( empty( $categories ) ) {
			return true;
		}
		$items = self::get_cart_items( $context );
		if ( empty( $items ) ) {
			return false;
		}
		foreach ( $items as $item ) {
			$cats = isset( $item['category_ids'] ) && is_array( $item['category_ids'] ) ? $item['category_ids'] : array();
			if ( ! empty( array_intersect( $cats, $categories ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Normalize value to array of positive integers.
	 *
	 * @param mixed $value Array or comma-separated string.
	 * @return int[]
	 */
	private static function normalize_int_array( $value ) {
		if ( is_array( $value ) ) {
			return array_values( array_filter( array_map( 'absint', $value ), function ( $v ) {
				return $v > 0;
			} ) );
		}
		if ( is_string( $value ) ) {
			return array_values( array_filter( array_map( 'absint', explode( ',', $value ) ), function ( $v ) {
				return $v > 0;
			} ) );
		}
		return array();
	}

	/**
	 * Normalize min_qty_for_category to map category_id => min_qty.
	 *
	 * @param mixed $value Associative array [ cat_id => min_qty ] or list of [ category_id, min_qty ] or [ ['category_id'=>x,'min_qty'=>y] ].
	 * @return array<int,int>
	 */
	private static function normalize_min_qty_for_category( $value ) {
		$out = array();
		if ( ! is_array( $value ) ) {
			return $out;
		}
		foreach ( $value as $k => $v ) {
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
