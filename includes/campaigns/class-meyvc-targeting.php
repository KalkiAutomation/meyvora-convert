<?php
/**
 * Campaign targeting – evaluate targeting rules
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MEYVC_Targeting class.
 *
 * @deprecated Legacy targeting class — the decision engine uses MEYVC_Rule_Engine directly.
 *             This class is kept for backward compatibility with third-party code only.
 *             Do not add new targeting logic here.
 */
class MEYVC_Targeting {

	/**
	 * Campaign data (array or object).
	 *
	 * @var array|object
	 */
	private $campaign;

	/**
	 * Constructor. Optional for backward compatibility with should_display().
	 *
	 * @param array|object $campaign Campaign data.
	 */
	public function __construct( $campaign = null ) {
		$this->campaign = $campaign;
	}

	/**
	 * Evaluate if campaign should show for current context.
	 *
	 * @param array|object $campaign Campaign data (must have targeting_rules or targeting).
	 * @param array        $context  Context: page_type, time_on_page, scroll_depth, has_interacted,
	 *                               cart_has_items, cart_value, is_new_visitor, device_type.
	 * @return bool
	 */
	public function evaluate( $campaign, $context ) {
		if ( function_exists( '_deprecated_function' ) ) {
			_deprecated_function( __METHOD__, '1.0.0', 'MEYVC_Rule_Engine' );
		}

		// Schedule check: respect start/end dates and day/hour restrictions.
		if ( class_exists( 'MEYVC_Campaign_Model' ) ) {
			$model = null;
			if ( $campaign instanceof MEYVC_Campaign_Model ) {
				$model = $campaign;
			} elseif ( is_array( $campaign ) ) {
				$model = MEYVC_Campaign_Model::from_db_row( $campaign );
			}
			if ( $model && ! $model->is_within_schedule() ) {
				return false;
			}
		}

		$rules = $this->get_targeting_rules( $campaign );

		// Page targeting.
		if ( ! $this->check_pages( $rules['pages'] ?? array(), $context ) ) {
			return false;
		}

		// Behavioral targeting.
		if ( ! $this->check_behavior( $rules['behavior'] ?? array(), $context ) ) {
			return false;
		}

		// Visitor targeting.
		if ( ! $this->check_visitor( $rules['visitor'] ?? array(), $context ) ) {
			return false;
		}

		// Device targeting.
		if ( ! $this->check_device( $rules['device'] ?? array(), $context ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if campaign should be displayed (backward compat).
	 * Builds context from current request and calls evaluate().
	 *
	 * @return bool
	 */
	public function should_display() {
		if ( null === $this->campaign ) {
			return false;
		}

		$context = $this->get_current_context();
		return $this->evaluate( $this->campaign, $context );
	}

	/**
	 * Get targeting rules from campaign (array or object).
	 *
	 * @param array|object $campaign Campaign data.
	 * @return array
	 */
	private function get_targeting_rules( $campaign ) {
		if ( is_array( $campaign ) ) {
			return $campaign['targeting_rules'] ?? $campaign['targeting'] ?? array();
		}
		return isset( $campaign->targeting_rules ) ? (array) $campaign->targeting_rules : ( isset( $campaign->targeting ) ? (array) $campaign->targeting : array() );
	}

	/**
	 * Build context from current request (for should_display).
	 *
	 * @return array
	 */
	private function get_current_context() {
		$page_type = '';
		if ( function_exists( 'is_woocommerce' ) ) {
			if ( is_shop() ) {
				$page_type = 'shop';
			} elseif ( is_product_category() ) {
				$page_type = 'product_category';
			} elseif ( is_product() ) {
				$page_type = 'product';
			} elseif ( is_cart() ) {
				$page_type = 'cart';
			} elseif ( is_checkout() ) {
				$page_type = 'checkout';
			}
		}
		if ( empty( $page_type ) && is_front_page() ) {
			$page_type = 'home';
		}

		$device = wp_is_mobile() ? 'mobile' : 'desktop';

		$cart_value   = 0;
		$cart_has_items = false;
		if ( function_exists( 'WC' ) && WC()->cart ) {
			$cart_value    = (float) WC()->cart->get_total( 'edit' );
			$cart_has_items = WC()->cart->get_cart_contents_count() > 0;
		}

		$is_new = ! isset( $_COOKIE['meyvc_visit_count'] ) && ! is_user_logged_in();

		return array(
			'page_type'       => $page_type,
			'time_on_page'    => (int) ( $GLOBALS['meyvc_time_on_page'] ?? 0 ),
			'scroll_depth'    => (int) ( $GLOBALS['meyvc_scroll_depth'] ?? 0 ),
			'has_interacted'  => ! empty( $GLOBALS['meyvc_has_interacted'] ),
			'cart_has_items'  => $cart_has_items,
			'cart_value'      => $cart_value,
			'is_new_visitor'  => $is_new,
			'device_type'     => $device,
		);
	}

	/**
	 * Check page targeting rules.
	 *
	 * @param array $rules   Page rules (include, excluded_pages).
	 * @param array $context Context with page_type.
	 * @return bool
	 */
	private function check_pages( $rules, $context ) {
		if ( empty( $rules ) ) {
			return true;
		}

		$page_type = $context['page_type'] ?? '';

		// Check exclusions first.
		$exclude = class_exists( 'MEYVC_Campaign_Model' ) ? MEYVC_Campaign_Model::get_pages_excluded_slugs( $rules ) : array();
		if ( is_array( $exclude ) && in_array( $page_type, $exclude, true ) ) {
			return false;
		}

		// Check inclusions.
		$include = $rules['include'] ?? array();
		if ( ! empty( $include ) && is_array( $include ) && ! in_array( $page_type, $include, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check behavioral targeting rules.
	 *
	 * @param array $rules   Behavior rules.
	 * @param array $context Context with time_on_page, scroll_depth, has_interacted, cart_has_items, cart_value.
	 * @return bool
	 */
	private function check_behavior( $rules, $context ) {
		if ( empty( $rules ) ) {
			return true;
		}

		// Time on page.
		$min_time = (int) ( $rules['min_time_on_page'] ?? 0 );
		if ( $min_time > 0 ) {
			$time_on_page = (int) ( $context['time_on_page'] ?? 0 );
			if ( $time_on_page < $min_time ) {
				return false;
			}
		}

		// Scroll depth.
		$min_scroll = (int) ( $rules['min_scroll_depth'] ?? 0 );
		if ( $min_scroll > 0 ) {
			$scroll_depth = (int) ( $context['scroll_depth'] ?? 0 );
			if ( $scroll_depth < $min_scroll ) {
				return false;
			}
		}

		// Require interaction.
		if ( ! empty( $rules['require_interaction'] ) ) {
			if ( empty( $context['has_interacted'] ) ) {
				return false;
			}
		}

		// Cart status.
		$cart_status = $rules['cart_status'] ?? 'any';
		if ( 'any' !== $cart_status ) {
			$has_items = ! empty( $context['cart_has_items'] );
			if ( 'has_items' === $cart_status && ! $has_items ) {
				return false;
			}
			if ( 'empty' === $cart_status && $has_items ) {
				return false;
			}
		}

		// Cart value.
		$cart_value = (float) ( $context['cart_value'] ?? 0 );

		$min_value = (float) ( $rules['cart_min_value'] ?? 0 );
		if ( $min_value > 0 && $cart_value < $min_value ) {
			return false;
		}

		$max_value = (float) ( $rules['cart_max_value'] ?? 0 );
		if ( $max_value > 0 && $cart_value > $max_value ) {
			return false;
		}

		return true;
	}

	/**
	 * Check visitor type targeting.
	 *
	 * @param array $rules   Visitor rules (type: all|new|returning).
	 * @param array $context Context with is_new_visitor.
	 * @return bool
	 */
	private function check_visitor( $rules, $context ) {
		if ( empty( $rules ) ) {
			return true;
		}

		$visitor_type = $rules['type'] ?? 'all';

		if ( 'all' === $visitor_type ) {
			return true;
		}

		$is_new = ! empty( $context['is_new_visitor'] );

		if ( 'new' === $visitor_type && ! $is_new ) {
			return false;
		}

		if ( 'returning' === $visitor_type && $is_new ) {
			return false;
		}

		return true;
	}

	/**
	 * Check device targeting.
	 *
	 * @param array $rules   Device rules (desktop, mobile, tablet as booleans).
	 * @param array $context Context with device_type.
	 * @return bool
	 */
	private function check_device( $rules, $context ) {
		if ( empty( $rules ) ) {
			return true;
		}

		$device = $context['device_type'] ?? 'desktop';

		// If all devices disabled, default to show (nothing specified).
		$desktop = ! empty( $rules['desktop'] );
		$mobile  = ! empty( $rules['mobile'] );
		$tablet  = ! empty( $rules['tablet'] );
		if ( ! $desktop && ! $mobile && ! $tablet ) {
			return true;
		}

		if ( 'desktop' === $device && ! $desktop ) {
			return false;
		}

		if ( 'mobile' === $device && ! $mobile ) {
			return false;
		}

		if ( 'tablet' === $device && ! $tablet ) {
			return false;
		}

		return true;
	}
}
