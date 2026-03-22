<?php
/**
 * Offer engine: evaluate offers against context and return the best matching offer by priority.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CRO_Offer_Engine class.
 */
class CRO_Offer_Engine {

	/**
	 * Build context for offer evaluation (cart, customer, visitor).
	 *
	 * Keys: cart_total, cart_items_count, user_id, is_logged_in, user_role, order_count, lifetime_spend, visitor_id.
	 *
	 * @return array Context array. Filtered by cro_offer_context.
	 */
	public static function build_context() {
		$context = array(
			'cart_total'       => 0.0,
			'cart_items_count' => 0,
			'user_id'          => 0,
			'is_logged_in'      => false,
			'user_role'        => '',
			'order_count'      => 0,
			'lifetime_spend'   => 0.0,
			'visitor_id'       => '',
		);

		// Cart.
		$context['cart_items'] = array();
		if ( function_exists( 'WC' ) && WC()->cart ) {
			$cart = WC()->cart;
			$context['cart_total']       = (float) $cart->get_total( 'edit' );
			$context['cart_items_count'] = (int) $cart->get_cart_contents_count();
			$context['cart_items']       = self::build_cart_items_context( $cart );
		}

		// User / customer.
		$context['is_logged_in'] = is_user_logged_in();
		$context['user_id']      = get_current_user_id();
		if ( $context['user_id'] > 0 ) {
			$user = get_userdata( $context['user_id'] );
			if ( $user && ! empty( $user->roles ) && is_array( $user->roles ) ) {
				$context['user_role'] = (string) reset( $user->roles );
			}
			if ( function_exists( 'wc_get_customer_order_count' ) ) {
				$context['order_count'] = (int) wc_get_customer_order_count( $context['user_id'] );
			}
			if ( function_exists( 'wc_get_customer_total_spent' ) ) {
				$spent = wc_get_customer_total_spent( $context['user_id'] );
				$context['lifetime_spend'] = is_numeric( $spent ) ? (float) $spent : 0.0;
			}
		}

		// Visitor ID (CRO_Visitor_State).
		if ( class_exists( 'CRO_Visitor_State' ) ) {
			$visitor = CRO_Visitor_State::get_instance();
			$context['visitor_id'] = (string) $visitor->get_visitor_id();
		}

		return apply_filters( 'cro_offer_context', $context );
	}

	/**
	 * Build cart items array for context: product_id, quantity, category_ids, on_sale.
	 *
	 * @param WC_Cart $cart WooCommerce cart.
	 * @return array<int, array{product_id: int, quantity: int, category_ids: int[], on_sale: bool}>
	 */
	public static function build_cart_items_context( $cart ) {
		$items = array();
		if ( ! is_object( $cart ) || ! method_exists( $cart, 'get_cart' ) ) {
			return $items;
		}
		foreach ( $cart->get_cart() as $item ) {
			$product_id = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
			$quantity   = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 0;
			if ( ! $product_id ) {
				continue;
			}
			$product = isset( $item['data'] ) && is_object( $item['data'] ) ? $item['data'] : ( function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null );
			$category_ids = array();
			$on_sale      = false;
			if ( $product && is_a( $product, 'WC_Product' ) ) {
				if ( method_exists( $product, 'get_category_ids' ) ) {
					$category_ids = array_map( 'absint', (array) $product->get_category_ids() );
				}
				$on_sale = $product->is_on_sale();
			}
			$items[] = array(
				'product_id'    => $product_id,
				'quantity'     => $quantity,
				'category_ids' => $category_ids,
				'on_sale'      => $on_sale,
			);
		}
		return $items;
	}

	/**
	 * Get registered rule evaluators (from CRO_Offer_Rules + filter).
	 * Filter: cro_offer_rules — modify or extend rule_key => callable( $value, $context ).
	 *
	 * @return array<string, callable>
	 */
	public static function get_rule_evaluators() {
		$evaluators = class_exists( 'CRO_Offer_Rules' ) ? CRO_Offer_Rules::get_evaluators() : array();
		return apply_filters( 'cro_offer_rules', $evaluators );
	}

	/**
	 * Evaluate a single rule.
	 *
	 * @param string $key     Rule key.
	 * @param mixed  $value   Rule value from offer conditions config.
	 * @param array  $context Context from build_context().
	 * @return bool
	 */
	public static function evaluate_condition( $key, $value, array $context ) {
		$evaluators = self::get_rule_evaluators();
		return class_exists( 'CRO_Offer_Rules' )
			? CRO_Offer_Rules::evaluate( $key, $value, $context, $evaluators )
			: true;
	}

	/**
	 * Evaluate all rules for an offer. All must pass (AND).
	 *
	 * @param array $conditions conditions_json (rule_key => value).
	 * @param array $context    Context from build_context().
	 * @return bool
	 */
	public static function evaluate_conditions( array $conditions, array $context ) {
		if ( empty( $conditions ) ) {
			return true;
		}
		$evaluators = self::get_rule_evaluators();
		return class_exists( 'CRO_Offer_Rules' )
			? CRO_Offer_Rules::evaluate_all( $conditions, $context, $evaluators )
			: true;
	}

	/**
	 * Get conditions array from an offer (object with conditions_json or flat array).
	 *
	 * @param object|array $offer Offer object or flat config.
	 * @return array<string, mixed> Rule key => value.
	 */
	public static function get_conditions_from_offer( $offer ) {
		if ( is_object( $offer ) && isset( $offer->conditions_json ) && is_array( $offer->conditions_json ) ) {
			return $offer->conditions_json;
		}
		if ( ! is_array( $offer ) ) {
			return array();
		}
		$flat_keys = array(
			'min_cart_total', 'max_cart_total', 'min_items', 'first_time_customer',
			'returning_customer_min_orders', 'returning_customer', 'lifetime_spend_min',
			'allowed_roles', 'excluded_roles',
			'include_categories', 'exclude_categories', 'include_products', 'exclude_products',
			'exclude_sale_items', 'min_qty_for_category', 'cart_contains_category',
		);
		$conditions = array();
		foreach ( $flat_keys as $key ) {
			if ( array_key_exists( $key, $offer ) ) {
				$conditions[ $key ] = $offer[ $key ];
			}
		}
		return $conditions;
	}

	/**
	 * Format amount for display (Woo-style, plain text). Used in preview labels/expected/actual.
	 *
	 * @param float|int|string $amount Amount.
	 * @return string
	 */
	public static function format_amount_display( $amount ) {
		if ( function_exists( 'wc_price' ) ) {
			return wp_strip_all_tags( wc_price( (float) $amount ) );
		}
		$sym = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';
		return number_format_i18n( (float) $amount, 2 ) . $sym;
	}

	/**
	 * Human-readable label for a condition (for preview checks).
	 *
	 * @param string $key   Rule key.
	 * @param mixed  $value Rule value.
	 * @return string
	 */
	public static function condition_label( $key, $value ) {
		switch ( $key ) {
			case 'min_cart_total':
				return sprintf( /* translators: %s is the formatted cart total amount. */ __( 'Cart ≥ %s', 'meyvora-convert' ), self::format_amount_display( $value ) );
			case 'max_cart_total':
				return sprintf( /* translators: %s is the formatted cart total amount. */ __( 'Cart ≤ %s', 'meyvora-convert' ), self::format_amount_display( $value ) );
			case 'min_items':
				return sprintf( /* translators: %d is the number of cart items. */ _n( '%d item', '%d items', (int) $value, 'meyvora-convert' ), (int) $value );
			case 'first_time_customer':
				return __( 'First-time customer', 'meyvora-convert' );
			case 'returning_customer_min_orders':
			case 'returning_customer':
				return sprintf( /* translators: %d is the minimum number of previous orders. */ __( 'Returning customer (≥%d orders)', 'meyvora-convert' ), (int) $value );
			case 'lifetime_spend_min':
				return sprintf( /* translators: %s is the formatted lifetime spend amount. */ __( 'Lifetime spend ≥ %s', 'meyvora-convert' ), self::format_amount_display( $value ) );
			case 'allowed_roles':
				return is_array( $value ) && ! empty( $value ) ? __( 'Allowed roles', 'meyvora-convert' ) . ': ' . implode( ', ', array_map( 'esc_html', $value ) ) : __( 'Allowed roles', 'meyvora-convert' );
			case 'excluded_roles':
				return is_array( $value ) && ! empty( $value ) ? __( 'Excluded roles', 'meyvora-convert' ) . ': ' . implode( ', ', array_map( 'esc_html', $value ) ) : __( 'Excluded roles', 'meyvora-convert' );
			case 'include_categories':
				return is_array( $value ) && ! empty( $value ) ? sprintf( /* translators: %d is the number of selected product categories. */ __( 'Categories only (%d)', 'meyvora-convert' ), count( $value ) ) : __( 'Include categories', 'meyvora-convert' );
			case 'exclude_categories':
				return is_array( $value ) && ! empty( $value ) ? sprintf( /* translators: %d is the number of excluded product categories. */ __( 'Exclude categories (%d)', 'meyvora-convert' ), count( $value ) ) : __( 'Exclude categories', 'meyvora-convert' );
			case 'include_products':
				return is_array( $value ) && ! empty( $value ) ? sprintf( /* translators: %d is the number of included products. */ __( 'Include products (%d)', 'meyvora-convert' ), count( $value ) ) : __( 'Include products', 'meyvora-convert' );
			case 'exclude_products':
				return is_array( $value ) && ! empty( $value ) ? sprintf( /* translators: %d is the number of excluded products. */ __( 'Exclude products (%d)', 'meyvora-convert' ), count( $value ) ) : __( 'Exclude products', 'meyvora-convert' );
			case 'exclude_sale_items':
				return $value ? __( 'No sale items in cart', 'meyvora-convert' ) : __( 'Sale items allowed', 'meyvora-convert' );
			case 'min_qty_for_category':
				return is_array( $value ) && ! empty( $value ) ? sprintf( /* translators: %d is the number of category rules. */ __( 'Min qty per category (%d)', 'meyvora-convert' ), count( $value ) ) : __( 'Min qty for category', 'meyvora-convert' );
			case 'cart_contains_category':
				return is_array( $value ) && ! empty( $value ) ? sprintf( /* translators: %d is the number of required categories. */ __( 'Cart contains category (%d)', 'meyvora-convert' ), count( $value ) ) : __( 'Cart contains category', 'meyvora-convert' );
			default:
				return (string) $key;
		}
	}

	/**
	 * Expected vs actual strings for a condition (for preview checks).
	 *
	 * @param string $key     Rule key.
	 * @param mixed  $value   Rule value.
	 * @param array  $context Context (cart_total, cart_items_count, etc.).
	 * @return array{expected: string, actual: string}
	 */
	public static function condition_expected_actual( $key, $value, array $context ) {
		$expected = '';
		$actual   = '';
		switch ( $key ) {
			case 'min_cart_total':
			case 'max_cart_total':
				$expected = self::format_amount_display( is_numeric( $value ) ? $value : 0 );
				$actual   = self::format_amount_display( isset( $context['cart_total'] ) ? $context['cart_total'] : 0 );
				break;
			case 'min_items':
				$expected = (string) ( is_numeric( $value ) ? (int) $value : 0 );
				$actual   = (string) ( isset( $context['cart_items_count'] ) ? (int) $context['cart_items_count'] : 0 );
				break;
			case 'first_time_customer':
				$expected = $value ? __( '0 orders', 'meyvora-convert' ) : '-';
				$actual   = (string) ( isset( $context['order_count'] ) ? (int) $context['order_count'] : 0 ) . ' ' . __( 'orders', 'meyvora-convert' );
				break;
			case 'returning_customer_min_orders':
			case 'returning_customer':
				$expected = '≥ ' . ( is_numeric( $value ) ? (int) $value : 1 );
				$actual   = (string) ( isset( $context['order_count'] ) ? (int) $context['order_count'] : 0 );
				break;
			case 'lifetime_spend_min':
				$expected = self::format_amount_display( is_numeric( $value ) ? $value : 0 );
				$actual   = self::format_amount_display( isset( $context['lifetime_spend'] ) ? $context['lifetime_spend'] : 0 );
				break;
			case 'allowed_roles':
				$expected = is_array( $value ) && ! empty( $value ) ? implode( ', ', $value ) : __( 'Any', 'meyvora-convert' );
				$actual   = isset( $context['user_role'] ) && (string) $context['user_role'] !== '' ? (string) $context['user_role'] : ( ! empty( $context['is_logged_in'] ) ? __( '—', 'meyvora-convert' ) : __( 'Guest', 'meyvora-convert' ) );
				break;
			case 'excluded_roles':
				$expected = is_array( $value ) && ! empty( $value ) ? implode( ', ', $value ) : __( 'None', 'meyvora-convert' );
				$actual   = isset( $context['user_role'] ) && (string) $context['user_role'] !== '' ? (string) $context['user_role'] : ( ! empty( $context['is_logged_in'] ) ? __( '—', 'meyvora-convert' ) : __( 'Guest', 'meyvora-convert' ) );
				break;
			case 'include_categories':
			case 'exclude_categories':
			case 'cart_contains_category':
				$expected = is_array( $value ) ? implode( ', ', array_map( 'absint', $value ) ) : '—';
				$actual   = self::format_cart_categories_for_display( isset( $context['cart_items'] ) ? $context['cart_items'] : array() );
				break;
			case 'include_products':
			case 'exclude_products':
				$expected = is_array( $value ) ? implode( ', ', array_map( 'absint', $value ) ) : '—';
				$actual   = self::format_cart_products_for_display( isset( $context['cart_items'] ) ? $context['cart_items'] : array() );
				break;
			case 'exclude_sale_items':
				$expected = $value ? __( 'No sale items', 'meyvora-convert' ) : '—';
				$actual   = self::format_cart_has_sale_for_display( isset( $context['cart_items'] ) ? $context['cart_items'] : array() );
				break;
			case 'min_qty_for_category':
				$expected = is_array( $value ) ? wp_json_encode( $value ) : '—';
				$actual   = self::format_cart_categories_for_display( isset( $context['cart_items'] ) ? $context['cart_items'] : array() );
				break;
			default:
				$expected = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
				$actual   = isset( $context[ $key ] ) ? (string) $context[ $key ] : '—';
		}
		return array( 'expected' => $expected, 'actual' => $actual );
	}

	/**
	 * Suggestion string for a failed condition (when no offer matched).
	 *
	 * @param string $key     Rule key.
	 * @param mixed  $value   Rule value.
	 * @param array  $context Context.
	 * @return string
	 */
	public static function condition_suggestion( $key, $value, array $context ) {
		switch ( $key ) {
			case 'min_cart_total':
				return sprintf( /* translators: %s is the formatted minimum cart total amount. */ __( 'Increase cart total to at least %s', 'meyvora-convert' ), self::format_amount_display( is_numeric( $value ) ? $value : 0 ) );
			case 'max_cart_total':
				return sprintf( /* translators: %s is the formatted maximum cart total amount. */ __( 'Lower cart total to at most %s', 'meyvora-convert' ), self::format_amount_display( is_numeric( $value ) ? $value : 0 ) );
			case 'min_items':
				$n = is_numeric( $value ) ? (int) $value : 0;
				return sprintf( /* translators: %d is the minimum number of items to add. */ _n( 'Add at least %d item to cart', 'Add at least %d items to cart', $n, 'meyvora-convert' ), $n );
			case 'first_time_customer':
				return __( 'Use a first-time customer (0 orders)', 'meyvora-convert' );
			case 'returning_customer_min_orders':
			case 'returning_customer':
				return sprintf( /* translators: %d is the minimum number of past orders. */ __( 'Use a returning customer (≥%d orders)', 'meyvora-convert' ), is_numeric( $value ) ? (int) $value : 1 );
			case 'lifetime_spend_min':
				return sprintf( /* translators: %s is the formatted minimum lifetime spend amount. */ __( 'Increase lifetime spend to at least %s', 'meyvora-convert' ), self::format_amount_display( is_numeric( $value ) ? $value : 0 ) );
			case 'allowed_roles':
				return is_array( $value ) && ! empty( $value ) ? sprintf( /* translators: %s is a comma-separated list of allowed user roles. */ __( 'Log in as one of: %s', 'meyvora-convert' ), implode( ', ', $value ) ) : '';
			case 'excluded_roles':
				return is_array( $value ) && ! empty( $value ) ? sprintf( /* translators: %s is a comma-separated list of excluded user roles. */ __( 'Use a role not in: %s', 'meyvora-convert' ), implode( ', ', $value ) ) : '';
			case 'include_categories':
				return is_array( $value ) && ! empty( $value ) ? __( 'Add only products from the selected categories', 'meyvora-convert' ) : '';
			case 'exclude_categories':
				return is_array( $value ) && ! empty( $value ) ? __( 'Remove products from the excluded categories', 'meyvora-convert' ) : '';
			case 'include_products':
				return is_array( $value ) && ! empty( $value ) ? __( 'Add at least one of the selected products to cart', 'meyvora-convert' ) : '';
			case 'exclude_products':
				return is_array( $value ) && ! empty( $value ) ? __( 'Remove the excluded products from cart', 'meyvora-convert' ) : '';
			case 'exclude_sale_items':
				return $value ? __( 'Remove sale items from cart', 'meyvora-convert' ) : '';
			case 'min_qty_for_category':
				return is_array( $value ) && ! empty( $value ) ? __( 'Add more items from the required categories', 'meyvora-convert' ) : '';
			case 'cart_contains_category':
				return is_array( $value ) && ! empty( $value ) ? __( 'Add a product from one of the selected categories', 'meyvora-convert' ) : '';
			default:
				return '';
		}
	}

	/**
	 * Format cart items' category IDs for display in expected/actual.
	 *
	 * @param array $cart_items Context cart_items.
	 * @return string
	 */
	private static function format_cart_categories_for_display( array $cart_items ) {
		$ids = array();
		foreach ( $cart_items as $item ) {
			if ( ! empty( $item['category_ids'] ) && is_array( $item['category_ids'] ) ) {
				$ids = array_merge( $ids, $item['category_ids'] );
			}
		}
		$ids = array_unique( array_map( 'absint', $ids ) );
		return empty( $ids ) ? __( '—', 'meyvora-convert' ) : implode( ', ', $ids );
	}

	/**
	 * Format cart product IDs for display.
	 *
	 * @param array $cart_items Context cart_items.
	 * @return string
	 */
	private static function format_cart_products_for_display( array $cart_items ) {
		$ids = array();
		foreach ( $cart_items as $item ) {
			if ( ! empty( $item['product_id'] ) ) {
				$ids[] = (int) $item['product_id'];
			}
		}
		return empty( $ids ) ? __( '—', 'meyvora-convert' ) : implode( ', ', $ids );
	}

	/**
	 * Format whether cart has sale items for display.
	 *
	 * @param array $cart_items Context cart_items.
	 * @return string
	 */
	private static function format_cart_has_sale_for_display( array $cart_items ) {
		foreach ( $cart_items as $item ) {
			if ( ! empty( $item['on_sale'] ) ) {
				return __( 'Has sale items', 'meyvora-convert' );
			}
		}
		return __( 'No sale items', 'meyvora-convert' );
	}

	/**
	 * Preview an offer against a context: pass/fail and per-condition checks (label, passed, expected, actual).
	 * Shared evaluation path for admin Test panel and REST GET /cro/v1/offer.
	 *
	 * @param object|array $offer   Offer (object with conditions_json or flat array).
	 * @param array        $context Context (cart_total, cart_items_count, is_logged_in, user_role, order_count, lifetime_spend, etc.).
	 * @return array{passed: bool, checks: array<int, array{label: string, passed: bool, expected: string, actual: string}>}
	 */
	public static function preview_offer( $offer, array $context ) {
		$conditions = self::get_conditions_from_offer( $offer );
		$checks     = array();
		$all_passed = true;
		foreach ( $conditions as $key => $value ) {
			$passed = self::evaluate_condition( (string) $key, $value, $context );
			if ( ! $passed ) {
				$all_passed = false;
			}
			$ea = self::condition_expected_actual( (string) $key, $value, $context );
			$checks[] = array(
				'label'    => self::condition_label( (string) $key, $value ),
				'passed'   => (bool) $passed,
				'expected' => $ea['expected'],
				'actual'   => $ea['actual'],
			);
		}
		return array(
			'passed' => $all_passed,
			'checks' => $checks,
		);
	}

	/**
	 * Normalize reward from offer reward_json to payload shape: type (percent|fixed|free_shipping), amount (optional).
	 *
	 * @param array|object $reward_json reward_json from offer row.
	 * @return array { type: string, amount?: number }
	 */
	public static function normalize_reward( $reward_json ) {
		$reward = is_array( $reward_json ) ? $reward_json : (array) $reward_json;
		$type   = isset( $reward['discount_type'] ) ? (string) $reward['discount_type'] : 'percent';
		$amount = isset( $reward['amount'] ) && is_numeric( $reward['amount'] ) ? (float) $reward['amount'] : 0.0;
		if ( 'free_shipping' === $type ) {
			return array( 'type' => 'free_shipping' );
		}
		if ( in_array( $type, array( 'fixed_cart', 'fixed_product' ), true ) ) {
			return array( 'type' => 'fixed', 'amount' => $amount );
		}
		return array( 'type' => 'percent', 'amount' => $amount );
	}

	/**
	 * Build headline string from reward (e.g. "10% off", "Free shipping").
	 *
	 * @param array $reward Normalized reward (type, amount?).
	 * @return string
	 */
	public static function reward_to_headline( array $reward ) {
		$type   = isset( $reward['type'] ) ? $reward['type'] : 'percent';
		$amount = isset( $reward['amount'] ) ? $reward['amount'] : 0;
		if ( 'free_shipping' === $type ) {
			return __( 'Free shipping', 'meyvora-convert' );
		}
		if ( 'percent' === $type ) {
			return sprintf( /* translators: %s: percentage */ __( '%s off', 'meyvora-convert' ), absint( $amount ) . '%' );
		}
		if ( 'fixed' === $type && function_exists( 'wc_price' ) ) {
			return sprintf( /* translators: %s: formatted amount */ __( '%s off', 'meyvora-convert' ), wc_price( $amount ) );
		}
		if ( 'fixed' === $type ) {
			return sprintf( /* translators: %s is the discount amount. */ __( '%s off', 'meyvora-convert' ), (string) $amount );
		}
		return __( 'A discount', 'meyvora-convert' );
	}

	/**
	 * Build offer payload from offer row: id, headline, description, reward, priority.
	 *
	 * @param object $offer Offer row (id, name, priority, conditions_json, reward_json decoded).
	 * @return array
	 */
	public static function offer_to_payload( $offer ) {
		$reward_json = isset( $offer->reward_json ) && is_array( $offer->reward_json ) ? $offer->reward_json : array();
		$reward      = self::normalize_reward( $reward_json );
		$headline    = isset( $offer->headline ) && (string) $offer->headline !== '' ? (string) $offer->headline : self::reward_to_headline( $reward );
		$description = isset( $offer->name ) ? (string) $offer->name : '';
		return array(
			'id'          => isset( $offer->id ) ? (int) $offer->id : 0,
			'headline'    => $headline,
			'description' => $description,
			'reward'      => $reward,
			'priority'    => isset( $offer->priority ) ? (int) $offer->priority : 10,
		);
	}

	/**
	 * Stable key for conflict checks: option offers use opt:{uuid}, DB rows use db:{numeric_id}.
	 *
	 * @param object $offer Engine offer object.
	 * @return string
	 */
	public static function get_offer_stable_key( $offer ) {
		if ( ! is_object( $offer ) ) {
			return '';
		}
		if ( isset( $offer->cro_stable_key ) && is_string( $offer->cro_stable_key ) && $offer->cro_stable_key !== '' ) {
			return $offer->cro_stable_key;
		}
		if ( isset( $offer->id ) ) {
			return 'db:' . (int) $offer->id;
		}
		return '';
	}

	/**
	 * Partner stable keys this offer cannot combine with (one direction from stored rules).
	 *
	 * @param object $offer Engine offer object.
	 * @return string[]
	 */
	public static function get_offer_conflict_partner_keys( $offer ) {
		if ( ! is_object( $offer ) ) {
			return array();
		}
		if ( isset( $offer->cro_conflict_partner_keys ) && is_array( $offer->cro_conflict_partner_keys ) ) {
			return $offer->cro_conflict_partner_keys;
		}
		if ( class_exists( 'CRO_Offer_Model' ) ) {
			$ids = CRO_Offer_Model::normalize_conflict_ids_on_row( $offer );
			$keys = array();
			foreach ( $ids as $id ) {
				$keys[] = 'db:' . (int) $id;
			}
			return $keys;
		}
		return array();
	}

	/**
	 * Whether two offers conflict (either lists the other as a conflict partner).
	 *
	 * @param object $a Offer object.
	 * @param object $b Offer object.
	 * @return bool
	 */
	public static function offers_mutually_conflict( $a, $b ) {
		$ka = self::get_offer_stable_key( $a );
		$kb = self::get_offer_stable_key( $b );
		if ( $ka === '' || $kb === '' || $ka === $kb ) {
			return false;
		}
		$ta = self::get_offer_conflict_partner_keys( $a );
		$tb = self::get_offer_conflict_partner_keys( $b );
		return in_array( $kb, $ta, true ) || in_array( $ka, $tb, true );
	}

	/**
	 * Log skipped offer due to conflict with a higher-priority match.
	 *
	 * @param object $skipped Skipped offer.
	 * @param object $kept    Already selected offer it conflicts with.
	 */
	private static function log_offer_conflict_skip( $skipped, $kept ) {
		if ( ! class_exists( 'CRO_Error_Handler' ) ) {
			return;
		}
		$name = '';
		if ( is_object( $skipped ) ) {
			$name = isset( $skipped->headline ) && (string) $skipped->headline !== ''
				? (string) $skipped->headline
				: ( isset( $skipped->name ) ? (string) $skipped->name : '' );
		}
		CRO_Error_Handler::log(
			'OFFER_CONFLICT_SKIP',
			$name !== '' ? $name : 'offer',
			array(
				'skipped_key'   => self::get_offer_stable_key( $skipped ),
				'conflicts_with_key' => self::get_offer_stable_key( $kept ),
			)
		);
	}

	/**
	 * Offers that matched conditions, greedily filtered so mutual conflicts never appear together (priority order preserved).
	 *
	 * @param array $matched Matched offer objects in evaluation order.
	 * @return object[]
	 */
	public static function apply_conflict_resolution_to_matched( array $matched ) {
		$selected = array();
		foreach ( $matched as $offer ) {
			$skip = false;
			foreach ( $selected as $sel ) {
				if ( self::offers_mutually_conflict( $offer, $sel ) ) {
					$skip = true;
					self::log_offer_conflict_skip( $offer, $sel );
					break;
				}
			}
			if ( ! $skip ) {
				$selected[] = $offer;
			}
		}
		return $selected;
	}

	/**
	 * Active offers that match context, after conflict resolution (same order as get_best_offer priority).
	 *
	 * @param array $context Context from build_context().
	 * @return object[]
	 */
	public static function get_matched_offers_resolved( array $context ) {
		$offers  = self::get_active_offers();
		$matched = array();
		foreach ( $offers as $offer ) {
			$conditions = isset( $offer->conditions_json ) && is_array( $offer->conditions_json )
				? $offer->conditions_json
				: array();
			if ( self::evaluate_conditions( $conditions, $context ) ) {
				$matched[] = $offer;
			}
		}
		return self::apply_conflict_resolution_to_matched( $matched );
	}

	/**
	 * Get active offers: from option cro_dynamic_offers (if set) or from DB (CRO_Offer_Model).
	 * Option-based offers use numeric ids 9000, 9001, ... for coupon generation.
	 *
	 * Single source of truth: when the admin Offers drawer saves data, it updates `cro_dynamic_offers`.
	 * If that option is non-empty, it takes precedence and DB rows are not merged (conflict rules use
	 * `conflict_offer_ids` on those option entries). DB-backed offers use `conflict_ids` only when
	 * the option is empty.
	 *
	 * @return array List of offer objects (id, name, priority, conditions_json, reward_json, usage_rules_json).
	 */
	public static function get_active_offers() {
		$option_offers = get_option( 'cro_dynamic_offers', array() );
		if ( is_array( $option_offers ) && ! empty( $option_offers ) ) {
			$list = self::option_offers_to_objects( $option_offers );
			if ( ! empty( $list ) ) {
				return $list;
			}
		}
		if ( class_exists( 'CRO_Offer_Model' ) ) {
			return CRO_Offer_Model::get_active();
		}
		return array();
	}

	/**
	 * Convert cro_dynamic_offers option array to offer objects for the engine.
	 *
	 * @param array $option_offers Array of offer configs (headline, description, rules, reward_type, reward_amount, coupon_ttl_hours, priority, enabled).
	 * @return array
	 */
	public static function option_offers_to_objects( array $option_offers ) {
		$base_id = 9000;
		$out     = array();
		foreach ( $option_offers as $i => $cfg ) {
			if ( ! is_array( $cfg ) || empty( $cfg['enabled'] ) ) {
				continue;
			}
			$conditions = array();
			if ( isset( $cfg['min_cart_total'] ) && is_numeric( $cfg['min_cart_total'] ) && (float) $cfg['min_cart_total'] > 0 ) {
				$conditions['min_cart_total'] = (float) $cfg['min_cart_total'];
			}
			if ( isset( $cfg['returning_customer_min_orders'] ) && is_numeric( $cfg['returning_customer_min_orders'] ) ) {
				$conditions['returning_customer_min_orders'] = (int) $cfg['returning_customer_min_orders'];
			}
			if ( isset( $cfg['lifetime_spend_min'] ) && is_numeric( $cfg['lifetime_spend_min'] ) ) {
				$conditions['lifetime_spend_min'] = (float) $cfg['lifetime_spend_min'];
			}
			if ( ! empty( $cfg['allowed_roles'] ) && is_array( $cfg['allowed_roles'] ) ) {
				$conditions['allowed_roles'] = array_map( 'sanitize_text_field', $cfg['allowed_roles'] );
			}
			if ( ! empty( $cfg['excluded_roles'] ) && is_array( $cfg['excluded_roles'] ) ) {
				$conditions['excluded_roles'] = array_map( 'sanitize_text_field', $cfg['excluded_roles'] );
			}
			if ( ! empty( $cfg['first_time_customer'] ) ) {
				$conditions['first_time_customer'] = true;
			}
			if ( isset( $cfg['min_items'] ) && is_numeric( $cfg['min_items'] ) ) {
				$conditions['min_items'] = (int) $cfg['min_items'];
			}
			if ( isset( $cfg['max_cart_total'] ) && is_numeric( $cfg['max_cart_total'] ) && (float) $cfg['max_cart_total'] > 0 ) {
				$conditions['max_cart_total'] = (float) $cfg['max_cart_total'];
			}
			if ( ! empty( $cfg['include_categories'] ) && is_array( $cfg['include_categories'] ) ) {
				$conditions['include_categories'] = array_map( 'absint', $cfg['include_categories'] );
			}
			if ( ! empty( $cfg['exclude_categories'] ) && is_array( $cfg['exclude_categories'] ) ) {
				$conditions['exclude_categories'] = array_map( 'absint', $cfg['exclude_categories'] );
			}
			if ( ! empty( $cfg['include_products'] ) && is_array( $cfg['include_products'] ) ) {
				$conditions['include_products'] = array_map( 'absint', $cfg['include_products'] );
			}
			if ( ! empty( $cfg['exclude_products'] ) && is_array( $cfg['exclude_products'] ) ) {
				$conditions['exclude_products'] = array_map( 'absint', $cfg['exclude_products'] );
			}
			if ( ! empty( $cfg['exclude_sale_items'] ) ) {
				$conditions['exclude_sale_items'] = true;
			}
			if ( ! empty( $cfg['min_qty_for_category'] ) && is_array( $cfg['min_qty_for_category'] ) ) {
				$conditions['min_qty_for_category'] = $cfg['min_qty_for_category'];
			}
			if ( ! empty( $cfg['cart_contains_category'] ) && is_array( $cfg['cart_contains_category'] ) ) {
				$conditions['cart_contains_category'] = array_map( 'absint', $cfg['cart_contains_category'] );
			}

			$usage_rules = array();
			$usage_rules['product_categories']         = ! empty( $cfg['apply_to_categories'] ) && is_array( $cfg['apply_to_categories'] ) ? array_map( 'absint', $cfg['apply_to_categories'] ) : ( ! empty( $cfg['include_categories'] ) && is_array( $cfg['include_categories'] ) ? array_map( 'absint', $cfg['include_categories'] ) : array() );
			$usage_rules['excluded_product_categories'] = ! empty( $cfg['exclude_categories'] ) && is_array( $cfg['exclude_categories'] ) ? array_map( 'absint', $cfg['exclude_categories'] ) : array();
			$usage_rules['product_ids']               = ! empty( $cfg['apply_to_products'] ) && is_array( $cfg['apply_to_products'] ) ? array_map( 'absint', $cfg['apply_to_products'] ) : ( ! empty( $cfg['include_products'] ) && is_array( $cfg['include_products'] ) ? array_map( 'absint', $cfg['include_products'] ) : array() );
			$usage_rules['excluded_product_ids']      = ! empty( $cfg['exclude_products'] ) && is_array( $cfg['exclude_products'] ) ? array_map( 'absint', $cfg['exclude_products'] ) : array();
			if ( isset( $cfg['min_cart_total'] ) && is_numeric( $cfg['min_cart_total'] ) && (float) $cfg['min_cart_total'] > 0 ) {
				$usage_rules['minimum_amount'] = (float) $cfg['min_cart_total'];
			}
			if ( ! empty( $cfg['exclude_sale_items'] ) ) {
				$usage_rules['exclude_sale_items'] = true;
			}

			$reward_type = isset( $cfg['reward_type'] ) ? sanitize_text_field( $cfg['reward_type'] ) : 'percent';
			$amount      = isset( $cfg['reward_amount'] ) && is_numeric( $cfg['reward_amount'] ) ? (float) $cfg['reward_amount'] : 0;
			$ttl_hours   = isset( $cfg['coupon_ttl_hours'] ) && is_numeric( $cfg['coupon_ttl_hours'] ) ? absint( $cfg['coupon_ttl_hours'] ) : 48;
			$per_cat_discount = isset( $cfg['per_category_discount'] ) && is_array( $cfg['per_category_discount'] ) ? $cfg['per_category_discount'] : array();
			$per_cat_discount = array_filter( $per_cat_discount, function ( $v, $k ) { return is_numeric( $k ) && absint( $k ) > 0 && is_numeric( $v ); }, ARRAY_FILTER_USE_BOTH );
			if ( 'free_shipping' === $reward_type ) {
				$reward_json = array( 'discount_type' => 'free_shipping', 'amount' => 0, 'ttl_hours' => $ttl_hours );
			} elseif ( 'fixed' === $reward_type ) {
				$reward_json = array( 'discount_type' => 'fixed_cart', 'amount' => $amount, 'ttl_hours' => $ttl_hours );
			} else {
				$reward_json = array( 'discount_type' => 'percent', 'amount' => $amount, 'ttl_hours' => $ttl_hours );
			}
			if ( ! empty( $per_cat_discount ) ) {
				$reward_json['per_category_discount'] = array_map( 'wc_format_decimal', $per_cat_discount );
			}

			$obj = new \stdClass();
			$obj->id                = $base_id + $i;
			$obj->headline          = isset( $cfg['headline'] ) ? (string) $cfg['headline'] : '';
			$obj->name              = isset( $cfg['description'] ) ? (string) $cfg['description'] : '';
			$obj->priority          = isset( $cfg['priority'] ) ? (int) $cfg['priority'] : 10;
			$obj->conditions_json   = $conditions;
			$obj->reward_json       = $reward_json;
			$obj->usage_rules_json  = $usage_rules;
			$opt_id                 = isset( $cfg['id'] ) ? sanitize_text_field( (string) $cfg['id'] ) : '';
			$obj->cro_stable_key    = $opt_id !== '' ? 'opt:' . $opt_id : 'opt_slot:' . $i;
			$conf_ids               = isset( $cfg['conflict_offer_ids'] ) && is_array( $cfg['conflict_offer_ids'] ) ? $cfg['conflict_offer_ids'] : array();
			$partner_keys           = array();
			foreach ( $conf_ids as $cid ) {
				$cid = is_scalar( $cid ) ? sanitize_text_field( (string) $cid ) : '';
				if ( $cid !== '' && ( $opt_id === '' || $cid !== $opt_id ) ) {
					$partner_keys[] = is_numeric( $cid ) ? 'db:' . absint( $cid ) : 'opt:' . $cid;
				}
			}
			$obj->cro_conflict_partner_keys = array_values( array_unique( $partner_keys ) );
			$out[] = $obj;
		}
		usort( $out, function ( $a, $b ) {
			return $a->priority - $b->priority;
		} );
		return $out;
	}

	/**
	 * Build a single offer object from one option config (for get_offer_by_id).
	 *
	 * @param array $cfg Option config (same keys as cro_dynamic_offers item).
	 * @param int   $id  Offer id to assign (e.g. 9000 + index).
	 * @return \stdClass
	 */
	public static function option_single_to_object( array $cfg, $id ) {
		$conditions = array();
		if ( isset( $cfg['min_cart_total'] ) && is_numeric( $cfg['min_cart_total'] ) && (float) $cfg['min_cart_total'] > 0 ) {
			$conditions['min_cart_total'] = (float) $cfg['min_cart_total'];
		}
		if ( isset( $cfg['returning_customer_min_orders'] ) && is_numeric( $cfg['returning_customer_min_orders'] ) ) {
			$conditions['returning_customer_min_orders'] = (int) $cfg['returning_customer_min_orders'];
		}
		if ( isset( $cfg['lifetime_spend_min'] ) && is_numeric( $cfg['lifetime_spend_min'] ) ) {
			$conditions['lifetime_spend_min'] = (float) $cfg['lifetime_spend_min'];
		}
		if ( ! empty( $cfg['allowed_roles'] ) && is_array( $cfg['allowed_roles'] ) ) {
			$conditions['allowed_roles'] = array_map( 'sanitize_text_field', $cfg['allowed_roles'] );
		}
		if ( ! empty( $cfg['excluded_roles'] ) && is_array( $cfg['excluded_roles'] ) ) {
			$conditions['excluded_roles'] = array_map( 'sanitize_text_field', $cfg['excluded_roles'] );
		}
		if ( ! empty( $cfg['first_time_customer'] ) ) {
			$conditions['first_time_customer'] = true;
		}
		if ( isset( $cfg['min_items'] ) && is_numeric( $cfg['min_items'] ) ) {
			$conditions['min_items'] = (int) $cfg['min_items'];
		}
		if ( isset( $cfg['max_cart_total'] ) && is_numeric( $cfg['max_cart_total'] ) && (float) $cfg['max_cart_total'] > 0 ) {
			$conditions['max_cart_total'] = (float) $cfg['max_cart_total'];
		}
		if ( ! empty( $cfg['include_categories'] ) && is_array( $cfg['include_categories'] ) ) {
			$conditions['include_categories'] = array_map( 'absint', $cfg['include_categories'] );
		}
		if ( ! empty( $cfg['exclude_categories'] ) && is_array( $cfg['exclude_categories'] ) ) {
			$conditions['exclude_categories'] = array_map( 'absint', $cfg['exclude_categories'] );
		}
		if ( ! empty( $cfg['include_products'] ) && is_array( $cfg['include_products'] ) ) {
			$conditions['include_products'] = array_map( 'absint', $cfg['include_products'] );
		}
		if ( ! empty( $cfg['exclude_products'] ) && is_array( $cfg['exclude_products'] ) ) {
			$conditions['exclude_products'] = array_map( 'absint', $cfg['exclude_products'] );
		}
		if ( ! empty( $cfg['exclude_sale_items'] ) ) {
			$conditions['exclude_sale_items'] = true;
		}
		if ( ! empty( $cfg['min_qty_for_category'] ) && is_array( $cfg['min_qty_for_category'] ) ) {
			$conditions['min_qty_for_category'] = $cfg['min_qty_for_category'];
		}
		if ( ! empty( $cfg['cart_contains_category'] ) && is_array( $cfg['cart_contains_category'] ) ) {
			$conditions['cart_contains_category'] = array_map( 'absint', $cfg['cart_contains_category'] );
		}

		$usage_rules = array();
		$usage_rules['product_categories']          = ! empty( $cfg['apply_to_categories'] ) && is_array( $cfg['apply_to_categories'] ) ? array_map( 'absint', $cfg['apply_to_categories'] ) : ( ! empty( $cfg['include_categories'] ) && is_array( $cfg['include_categories'] ) ? array_map( 'absint', $cfg['include_categories'] ) : array() );
		$usage_rules['excluded_product_categories'] = ! empty( $cfg['exclude_categories'] ) && is_array( $cfg['exclude_categories'] ) ? array_map( 'absint', $cfg['exclude_categories'] ) : array();
		$usage_rules['product_ids']                  = ! empty( $cfg['apply_to_products'] ) && is_array( $cfg['apply_to_products'] ) ? array_map( 'absint', $cfg['apply_to_products'] ) : ( ! empty( $cfg['include_products'] ) && is_array( $cfg['include_products'] ) ? array_map( 'absint', $cfg['include_products'] ) : array() );
		$usage_rules['excluded_product_ids']        = ! empty( $cfg['exclude_products'] ) && is_array( $cfg['exclude_products'] ) ? array_map( 'absint', $cfg['exclude_products'] ) : array();
		if ( isset( $cfg['min_cart_total'] ) && is_numeric( $cfg['min_cart_total'] ) && (float) $cfg['min_cart_total'] > 0 ) {
			$usage_rules['minimum_amount'] = (float) $cfg['min_cart_total'];
		}
		if ( ! empty( $cfg['exclude_sale_items'] ) ) {
			$usage_rules['exclude_sale_items'] = true;
		}

		$reward_type = isset( $cfg['reward_type'] ) ? sanitize_text_field( $cfg['reward_type'] ) : 'percent';
		$amount      = isset( $cfg['reward_amount'] ) && is_numeric( $cfg['reward_amount'] ) ? (float) $cfg['reward_amount'] : 0;
		$ttl_hours   = isset( $cfg['coupon_ttl_hours'] ) && is_numeric( $cfg['coupon_ttl_hours'] ) ? absint( $cfg['coupon_ttl_hours'] ) : 48;
		$per_cat_discount = isset( $cfg['per_category_discount'] ) && is_array( $cfg['per_category_discount'] ) ? $cfg['per_category_discount'] : array();
		$per_cat_discount = array_filter( $per_cat_discount, function ( $v, $k ) { return is_numeric( $k ) && absint( $k ) > 0 && is_numeric( $v ); }, ARRAY_FILTER_USE_BOTH );
		if ( 'free_shipping' === $reward_type ) {
			$reward_json = array( 'discount_type' => 'free_shipping', 'amount' => 0, 'ttl_hours' => $ttl_hours );
		} elseif ( 'fixed' === $reward_type ) {
			$reward_json = array( 'discount_type' => 'fixed_cart', 'amount' => $amount, 'ttl_hours' => $ttl_hours );
		} else {
			$reward_json = array( 'discount_type' => 'percent', 'amount' => $amount, 'ttl_hours' => $ttl_hours );
		}
		if ( ! empty( $per_cat_discount ) ) {
			$reward_json['per_category_discount'] = array_map( 'wc_format_decimal', $per_cat_discount );
		}
		$obj = new \stdClass();
		$obj->id               = (int) $id;
		$obj->headline         = isset( $cfg['headline'] ) ? (string) $cfg['headline'] : '';
		$obj->name             = isset( $cfg['description'] ) ? (string) $cfg['description'] : '';
		$obj->priority         = isset( $cfg['priority'] ) ? (int) $cfg['priority'] : 10;
		$obj->conditions_json  = $conditions;
		$obj->reward_json      = $reward_json;
		$obj->usage_rules_json = $usage_rules;
		$slot_index            = (int) $id - 9000;
		$opt_id                = isset( $cfg['id'] ) ? sanitize_text_field( (string) $cfg['id'] ) : '';
		$obj->cro_stable_key   = $opt_id !== '' ? 'opt:' . $opt_id : 'opt_slot:' . max( 0, $slot_index );
		$conf_ids              = isset( $cfg['conflict_offer_ids'] ) && is_array( $cfg['conflict_offer_ids'] ) ? $cfg['conflict_offer_ids'] : array();
		$partner_keys          = array();
		foreach ( $conf_ids as $cid ) {
			$cid = is_scalar( $cid ) ? sanitize_text_field( (string) $cid ) : '';
			if ( $cid !== '' && ( $opt_id === '' || $cid !== $opt_id ) ) {
				$partner_keys[] = is_numeric( $cid ) ? 'db:' . absint( $cid ) : 'opt:' . $cid;
			}
		}
		$obj->cro_conflict_partner_keys = array_values( array_unique( $partner_keys ) );
		return $obj;
	}

	/**
	 * Get the best matching offer for the given context.
	 * Active offers are ordered by priority ASC; returns the first that passes all rules.
	 * No coupon generation in this method.
	 *
	 * @param array|null $context Optional. Context from build_context(); built automatically if null.
	 * @return array|null Payload { id, headline, description, reward, priority } or null.
	 */
	public static function get_best_offer( $context = null ) {
		// Don't show dynamic offers during subscription renewal checkout.
		if ( function_exists( 'WC' ) && WC()->cart && class_exists( 'WC_Subscriptions_Cart' ) ) {
			foreach ( WC()->cart->get_cart() as $item ) {
				if ( ! empty( $item['subscription_renewal'] ) ) {
					return null;
				}
			}
		}

		if ( $context === null ) {
			$context = self::build_context();
		}
		if ( ! is_array( $context ) ) {
			return null;
		}
		$resolved = self::get_matched_offers_resolved( $context );
		if ( ! empty( $resolved[0] ) ) {
			$payload = self::offer_to_payload( $resolved[0] );
			return apply_filters( 'cro_offer_best_offer', $payload, $context );
		}
		return apply_filters( 'cro_offer_best_offer', null, $context );
	}

	/**
	 * Get the best matching offer and optionally a generated coupon code for it.
	 * Does not generate for admins/shop managers; rate-limited per visitor/offer.
	 *
	 * @param array|null $context Optional. Context from build_context(); built if null.
	 * @return array { 'offer' => array|null, 'coupon_code' => string|null }
	 */
	public static function get_best_offer_with_coupon( $context = null ) {
		if ( $context === null ) {
			$context = self::build_context();
		}
		$offer = self::get_best_offer( $context );
		$code  = null;
		if ( $offer && is_array( $offer ) && ! empty( $offer['id'] ) ) {
			$code = self::get_or_create_coupon_for_offer( $offer, $context );
		}
		return array(
			'offer'       => $offer,
			'coupon_code' => $code,
		);
	}

	/**
	 * Hours within which we allow only one generated coupon per visitor per offer (rate limit).
	 *
	 * @return int
	 */
	public static function get_rate_limit_hours() {
		return (int) apply_filters( 'cro_offer_coupon_rate_limit_hours', 6 );
	}

	/**
	 * Default TTL in hours for generated coupon expiry (default 48h).
	 *
	 * @return int
	 */
	public static function get_coupon_ttl_hours() {
		return (int) apply_filters( 'cro_offer_coupon_ttl_hours', 48 );
	}

	/**
	 * Default TTL days for generated coupon expiry when not in reward_json (fallback).
	 *
	 * @return int
	 */
	public static function get_default_coupon_ttl_days() {
		return (int) apply_filters( 'cro_offer_coupon_ttl_days', 2 );
	}

	/**
	 * Get full offer row by ID (for coupon creation / logging when only payload is available).
	 * Supports option-based offers (id 9000, 9001, ...).
	 *
	 * @param int $id Offer ID.
	 * @return object|null Offer row or null.
	 */
	public static function get_offer_by_id( $id ) {
		$id = (int) $id;
		$base_id = 9000;
		if ( $id >= $base_id && $id < $base_id + 20 ) {
			$option_offers = get_option( 'cro_dynamic_offers', array() );
			if ( is_array( $option_offers ) ) {
				$index = $id - $base_id;
				if ( isset( $option_offers[ $index ] ) && is_array( $option_offers[ $index ] ) ) {
					return self::option_single_to_object( $option_offers[ $index ], $id );
				}
			}
		}
		return class_exists( 'CRO_Offer_Model' ) ? CRO_Offer_Model::get( $id ) : null;
	}

	/**
	 * Check if the current user is admin or shop manager (do not generate coupons for them).
	 *
	 * @param array $context Context from build_context().
	 * @return bool
	 */
	public static function is_admin_or_shop_manager( array $context ) {
		if ( empty( $context['user_id'] ) ) {
			return false;
		}
		return current_user_can( 'manage_meyvora_convert' ) || user_can( $context['user_id'], 'administrator' );
	}

	/**
	 * Get the most recent offer log for this visitor + offer within the rate-limit window.
	 *
	 * @param int    $offer_id   Offer ID.
	 * @param string $visitor_id Visitor ID.
	 * @return object|null Row with coupon_code, created_at or null.
	 */
	public static function get_recent_log_for_visitor_offer( $offer_id, $visitor_id ) {
		if ( ! class_exists( 'CRO_Offer_Model' ) || empty( $visitor_id ) ) {
			return null;
		}
		$table  = CRO_Offer_Model::get_logs_table();
		$hours  = self::get_rate_limit_hours();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $hours * HOUR_IN_SECONDS ) );
		$row    = CRO_Database::get_row(
			"SELECT id, coupon_code, created_at FROM {$table} WHERE offer_id = %d AND visitor_id = %s AND created_at > %s ORDER BY created_at DESC LIMIT 1",
			array( absint( $offer_id ), sanitize_text_field( $visitor_id ), $cutoff ),
			OBJECT
		);
		return $row ? $row : null;
	}

	/**
	 * Check if a coupon code exists and is still valid (not expired, usage limit not reached).
	 *
	 * @param string $code Coupon code.
	 * @return bool
	 */
	public static function is_coupon_code_usable( $code ) {
		if ( ! function_exists( 'wc_get_coupon_id_by_code' ) ) {
			return false;
		}
		$id = wc_get_coupon_id_by_code( $code );
		if ( ! $id ) {
			return false;
		}
		$coupon = new WC_Coupon( $code );
		if ( ! $coupon->get_id() ) {
			return false;
		}
		if ( $coupon->get_date_expires() && $coupon->get_date_expires()->getTimestamp() < time() ) {
			return false;
		}
		$limit = $coupon->get_usage_limit();
		if ( $limit > 0 && $coupon->get_usage_count() >= $limit ) {
			return false;
		}
		return true;
	}

	/**
	 * Generate a unique code: MYV-{offerId}-{random6}.
	 *
	 * @param int $offer_id Offer ID.
	 * @return string
	 */
	public static function generate_unique_code( $offer_id ) {
		$offer_id = absint( $offer_id );
		$chars    = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		do {
			$rand = '';
			for ( $i = 0; $i < 6; $i++ ) {
				$rand .= $chars[ wp_rand( 0, strlen( $chars ) - 1 ) ];
			}
			$code = 'MYV-' . $offer_id . '-' . $rand;
		} while ( function_exists( 'wc_get_coupon_id_by_code' ) && wc_get_coupon_id_by_code( $code ) );
		return $code;
	}

	/**
	 * Create a WooCommerce coupon from an offer's reward_json and optional usage_rules_json.
	 *
	 * @param object $offer  Offer row (reward_json, usage_rules_json decoded).
	 * @param array  $context Context from build_context().
	 * @return WC_Coupon|null Coupon object or null on failure.
	 */
	public static function create_coupon_for_offer( $offer, array $context ) {
		if ( ! class_exists( 'WC_Coupon' ) ) {
			return null;
		}
		$reward = isset( $offer->reward_json ) && is_array( $offer->reward_json ) ? $offer->reward_json : array();
		$usage  = isset( $offer->usage_rules_json ) && is_array( $offer->usage_rules_json ) ? $offer->usage_rules_json : array();

		$code   = self::generate_unique_code( $offer->id );
		$coupon = new WC_Coupon( $code );
		if ( $coupon->get_id() > 0 ) {
			return null;
		}

		$discount_type = isset( $reward['discount_type'] ) ? sanitize_text_field( $reward['discount_type'] ) : 'percent';
		$amount        = isset( $reward['amount'] ) ? wc_format_decimal( $reward['amount'] ) : '0';
		$per_cat       = isset( $reward['per_category_discount'] ) && is_array( $reward['per_category_discount'] ) ? $reward['per_category_discount'] : array();
		if ( ! empty( $per_cat ) && isset( $context['cart_items'] ) && is_array( $context['cart_items'] ) ) {
			$first_cat_in_cart = null;
			foreach ( $context['cart_items'] as $item ) {
				$cats = isset( $item['category_ids'] ) && is_array( $item['category_ids'] ) ? $item['category_ids'] : array();
				foreach ( $cats as $cid ) {
					$cid = (int) $cid;
					if ( isset( $per_cat[ $cid ] ) && $per_cat[ $cid ] !== '' ) {
						$first_cat_in_cart = $cid;
						break 2;
					}
				}
			}
			if ( $first_cat_in_cart !== null ) {
				$amount = wc_format_decimal( $per_cat[ $first_cat_in_cart ] );
			}
		}
		$valid_types = array_keys( wc_get_coupon_types() );
		if ( ! in_array( $discount_type, $valid_types, true ) ) {
			$discount_type = 'percent';
		}
		$ttl_hours = isset( $reward['ttl_hours'] ) && is_numeric( $reward['ttl_hours'] ) ? absint( $reward['ttl_hours'] ) : self::get_coupon_ttl_hours();
		$ttl_hours = $ttl_hours > 0 ? $ttl_hours : self::get_coupon_ttl_hours();
		$date_expires = time() + ( $ttl_hours * HOUR_IN_SECONDS );

		$coupon_args = array(
			'code'                   => $code,
			'discount_type'          => $discount_type,
			'amount'                 => $amount,
			'free_shipping'          => ( 'free_shipping' === $discount_type ),
			'individual_use'         => ! empty( $reward['individual_use'] ),
			'usage_limit'            => 1,
			'usage_limit_per_user'   => ! empty( $context['user_id'] ) ? 1 : 0,
			'date_expires'           => $date_expires,
			'minimum_amount'         => isset( $usage['minimum_amount'] ) && is_numeric( $usage['minimum_amount'] ) ? (string) $usage['minimum_amount'] : '',
			'product_ids'            => ! empty( $usage['product_ids'] ) && is_array( $usage['product_ids'] ) ? array_map( 'absint', $usage['product_ids'] ) : array(),
			'excluded_product_ids'   => ! empty( $usage['excluded_product_ids'] ) && is_array( $usage['excluded_product_ids'] ) ? array_map( 'absint', $usage['excluded_product_ids'] ) : array(),
			'product_categories'     => ! empty( $usage['product_categories'] ) && is_array( $usage['product_categories'] ) ? array_map( 'absint', $usage['product_categories'] ) : array(),
			'excluded_product_categories' => ! empty( $usage['excluded_product_categories'] ) && is_array( $usage['excluded_product_categories'] ) ? array_map( 'absint', $usage['excluded_product_categories'] ) : array(),
			'exclude_sale_items'     => ! empty( $usage['exclude_sale_items'] ),
		);
		$coupon_args = apply_filters( 'cro_offer_coupon_args', $coupon_args, $offer, $context );

		if ( ! empty( $coupon_args['free_shipping'] ) ) {
			$coupon->set_discount_type( 'fixed_cart' );
			$coupon->set_amount( 0 );
			$coupon->set_free_shipping( true );
		} else {
			$coupon->set_discount_type( isset( $coupon_args['discount_type'] ) ? (string) $coupon_args['discount_type'] : $discount_type );
			$coupon->set_amount( isset( $coupon_args['amount'] ) ? wc_format_decimal( $coupon_args['amount'] ) : $amount );
		}
		$coupon->set_individual_use( ! empty( $coupon_args['individual_use'] ) );
		$coupon->set_usage_limit( isset( $coupon_args['usage_limit'] ) ? absint( $coupon_args['usage_limit'] ) : 1 );
		$coupon->set_usage_limit_per_user( isset( $coupon_args['usage_limit_per_user'] ) ? absint( $coupon_args['usage_limit_per_user'] ) : ( ! empty( $context['user_id'] ) ? 1 : 0 ) );
		$coupon->set_date_expires( isset( $coupon_args['date_expires'] ) ? (int) $coupon_args['date_expires'] : $date_expires );

		if ( ! empty( $coupon_args['minimum_amount'] ) ) {
			$coupon->set_minimum_amount( (string) $coupon_args['minimum_amount'] );
		}
		if ( ! empty( $coupon_args['product_ids'] ) && is_array( $coupon_args['product_ids'] ) ) {
			$coupon->set_product_ids( array_map( 'absint', $coupon_args['product_ids'] ) );
		}
		if ( ! empty( $coupon_args['excluded_product_ids'] ) && is_array( $coupon_args['excluded_product_ids'] ) ) {
			$coupon->set_excluded_product_ids( array_map( 'absint', $coupon_args['excluded_product_ids'] ) );
		}
		if ( ! empty( $coupon_args['product_categories'] ) && is_array( $coupon_args['product_categories'] ) ) {
			$coupon->set_product_categories( array_map( 'absint', $coupon_args['product_categories'] ) );
		}
		if ( ! empty( $coupon_args['excluded_product_categories'] ) && is_array( $coupon_args['excluded_product_categories'] ) ) {
			$coupon->set_excluded_product_categories( array_map( 'absint', $coupon_args['excluded_product_categories'] ) );
		}
		if ( ! empty( $coupon_args['exclude_sale_items'] ) ) {
			$coupon->set_exclude_sale_items( true );
		}
		// When reward has per_category_discount, restrict coupon to those categories if not already set.
		if ( ! empty( $per_cat ) && is_array( $per_cat ) && empty( $coupon_args['product_categories'] ) ) {
			$cat_ids = array_map( 'absint', array_keys( $per_cat ) );
			$cat_ids = array_filter( $cat_ids );
			if ( ! empty( $cat_ids ) ) {
				$coupon->set_product_categories( $cat_ids );
			}
		}

		$coupon->save();
		$coupon_id = $coupon->get_id();
		if ( ! $coupon_id ) {
			return null;
		}

		$offer_id   = absint( $offer->id );
		$visitor_id = isset( $context['visitor_id'] ) ? sanitize_text_field( (string) $context['visitor_id'] ) : '';
		$user_id    = isset( $context['user_id'] ) ? absint( $context['user_id'] ) : 0;
		update_post_meta( $coupon_id, '_cro_offer_id', $offer_id );
		update_post_meta( $coupon_id, '_cro_visitor_id', $visitor_id );
		update_post_meta( $coupon_id, '_cro_user_id', $user_id );

		return $coupon;
	}

	/**
	 * Get or create a single-use coupon for the given offer and context.
	 * Rate-limited to 1 coupon per visitor per offer within get_rate_limit_hours() (default 6).
	 * Never generates for admins/shop managers. Returns null if cart empty or context invalid.
	 *
	 * @param array|object $offer   Offer payload (array with 'id') or full offer row object.
	 * @param array        $context Context from build_context() (must include visitor_id, user_id, etc.).
	 * @return string|null Coupon code or null.
	 */
	public static function get_or_create_coupon_for_offer( $offer, array $context ) {
		if ( ! is_array( $context ) || empty( $context ) ) {
			return null;
		}
		$context = self::sanitize_context( $context );
		if ( self::is_context_invalid_for_coupon( $context ) ) {
			return null;
		}
		if ( self::is_admin_or_shop_manager( $context ) ) {
			return null;
		}

		$offer_id = self::resolve_offer_id( $offer );
		if ( ! $offer_id ) {
			return null;
		}
		$offer_row = self::get_offer_by_id( $offer_id );
		if ( ! $offer_row ) {
			return null;
		}

		$visitor_id = isset( $context['visitor_id'] ) ? (string) $context['visitor_id'] : '';
		if ( $visitor_id === '' ) {
			return null;
		}

		// Rate limit: 1 coupon per visitor per offer within N hours (offer logs table).
		$recent = self::get_recent_log_for_visitor_offer( $offer_id, $visitor_id );
		if ( $recent && ! empty( $recent->coupon_code ) ) {
			$code = sanitize_text_field( $recent->coupon_code );
			if ( $code !== '' && self::is_coupon_code_usable( $code ) ) {
				return $code;
			}
		}

		$coupon = self::create_coupon_for_offer( $offer_row, $context );
		if ( ! $coupon ) {
			return null;
		}

		$code = $coupon->get_code();
		if ( $code === '' || ! is_string( $code ) ) {
			return null;
		}

		if ( class_exists( 'CRO_Offer_Model' ) ) {
			CRO_Offer_Model::log_insert(
				$offer_id,
				$visitor_id,
				! empty( $context['user_id'] ) ? (int) $context['user_id'] : null,
				$code,
				null,
				null
			);
		}

		do_action( 'cro_offer_coupon_generated', $code, $offer_row, $context );

		return $code;
	}

	/**
	 * Sanitize context array for safe use in coupon generation.
	 *
	 * @param array $context Raw context.
	 * @return array Sanitized context.
	 */
	private static function sanitize_context( array $context ) {
		$out = array();
		$out['cart_total']       = isset( $context['cart_total'] ) && is_numeric( $context['cart_total'] ) ? (float) $context['cart_total'] : 0.0;
		$out['cart_items_count'] = isset( $context['cart_items_count'] ) ? absint( $context['cart_items_count'] ) : 0;
		$out['user_id']          = isset( $context['user_id'] ) ? absint( $context['user_id'] ) : 0;
		$out['is_logged_in']     = ! empty( $context['is_logged_in'] );
		$out['user_role']        = isset( $context['user_role'] ) ? sanitize_text_field( (string) $context['user_role'] ) : '';
		$out['order_count']      = isset( $context['order_count'] ) ? absint( $context['order_count'] ) : 0;
		$out['lifetime_spend']   = isset( $context['lifetime_spend'] ) && is_numeric( $context['lifetime_spend'] ) ? (float) $context['lifetime_spend'] : 0.0;
		$out['visitor_id']       = isset( $context['visitor_id'] ) ? sanitize_text_field( (string) $context['visitor_id'] ) : '';
		return $out;
	}

	/**
	 * Whether context is invalid for coupon generation (e.g. empty cart).
	 *
	 * @param array $context Sanitized context.
	 * @return bool
	 */
	private static function is_context_invalid_for_coupon( array $context ) {
		$cart_items = isset( $context['cart_items_count'] ) ? (int) $context['cart_items_count'] : 0;
		return $cart_items < 1;
	}

	/**
	 * Resolve offer to an offer ID (from payload array or offer object).
	 *
	 * @param array|object $offer Offer payload or row.
	 * @return int 0 if invalid.
	 */
	private static function resolve_offer_id( $offer ) {
		if ( is_array( $offer ) && isset( $offer['id'] ) ) {
			return absint( $offer['id'] );
		}
		if ( is_object( $offer ) && isset( $offer->id ) ) {
			return absint( $offer->id );
		}
		return 0;
	}

	/**
	 * Get or create a coupon for the best matching offer.
	 * Rate-limited to one coupon per visitor per offer within X hours; never generates for admins/shop managers.
	 *
	 * @return string|null Coupon code or null.
	 */
	public static function get_or_create_coupon_for_best_offer() {
		$context = self::build_context();

		if ( self::is_admin_or_shop_manager( $context ) ) {
			return null;
		}

		$payload = self::get_best_offer( $context );
		if ( ! $payload || ! is_array( $payload ) || empty( $payload['id'] ) ) {
			return null;
		}

		$offer = self::get_offer_by_id( $payload['id'] );
		if ( ! $offer ) {
			return null;
		}

		$visitor_id = isset( $context['visitor_id'] ) ? $context['visitor_id'] : '';
		$recent     = self::get_recent_log_for_visitor_offer( $offer->id, $visitor_id );
		if ( $recent && ! empty( $recent->coupon_code ) ) {
			if ( self::is_coupon_code_usable( $recent->coupon_code ) ) {
				return $recent->coupon_code;
			}
		}

		$coupon = self::create_coupon_for_offer( $offer, $context );
		if ( ! $coupon ) {
			return null;
		}

		CRO_Offer_Model::log_insert(
			$offer->id,
			$visitor_id,
			! empty( $context['user_id'] ) ? $context['user_id'] : null,
			$coupon->get_code(),
			null,
			null
		);

		return $coupon->get_code();
	}
}
