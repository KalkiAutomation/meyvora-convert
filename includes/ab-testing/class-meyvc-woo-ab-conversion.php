<?php
/**
 * WooCommerce A/B conversion tracking
 *
 * On order paid/completed, attributes the conversion to the visitor's assigned
 * A/B variation (from MEYVC_Visitor_State cookie) and records it via MEYVC_AB_Test.
 * Prevents duplicate conversion counts per order (order meta guard).
 *
 * Revenue: we use the order total (WC_Order::get_total()), i.e. the final amount
 * paid including tax, shipping, and discounts—not the cart subtotal.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MEYVC_Woo_AB_Conversion class.
 *
 * Hooks WooCommerce order paid/completed and calls MEYVC_AB_Test::record_conversion
 * when the visitor has an A/B attribution in cookie. One conversion per order (guarded by order meta).
 */
class MEYVC_Woo_AB_Conversion {

	/** Order meta key: set when we have recorded A/B conversion for this order (prevent duplicate). */
	const META_RECORDED = '_meyvc_ab_conversion_recorded';

	/**
	 * Register WooCommerce hooks. Call from loader when WooCommerce is active.
	 */
	public static function register() {
		// Fires when payment is complete (covers most paid orders).
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'on_order_paid' ), 10, 1 );
		// Fires when order status changes to completed (covers free orders or flows that skip payment_complete).
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_order_completed' ), 10, 1 );
	}

	/**
	 * Handle order payment complete.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function on_order_paid( $order_id ) {
		self::maybe_record_conversion( $order_id );
	}

	/**
	 * Handle order status completed (e.g. free orders).
	 *
	 * @param int $order_id Order ID.
	 */
	public static function on_order_completed( $order_id ) {
		self::maybe_record_conversion( $order_id );
	}

	/**
	 * Record A/B conversion for the order at most once per order ID.
	 * Uses visitor cookie attribution; revenue = order total (see method body).
	 *
	 * @param int $order_id Order ID.
	 */
	private static function maybe_record_conversion( $order_id ) {
		$order_id = absint( $order_id );
		if ( ! $order_id ) {
			return;
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		// Prevent duplicate conversion counts for this order
		if ( $order->get_meta( self::META_RECORDED ) ) {
			return;
		}

		if ( ! class_exists( 'MEYVC_Visitor_State' ) || ! class_exists( 'MEYVC_AB_Test' ) ) {
			return;
		}

		$visitor_state = MEYVC_Visitor_State::get_instance();
		$visitor_id    = $visitor_state->get_visitor_id();
		if ( $visitor_id === '' ) {
			return;
		}

		$attribution = $visitor_state->get_ab_attribution();
		if ( $attribution === null || empty( $attribution['variation_id'] ) ) {
			return;
		}

		$variation_id = (int) $attribution['variation_id'];
		if ( $variation_id <= 0 ) {
			return;
		}

		// Revenue: order total (includes tax, shipping, discounts). Stored as float in CRO.
		$revenue = 0.0;
		if ( is_callable( array( $order, 'get_total' ) ) ) {
			$revenue = (float) $order->get_total();
		}

		$ab_model = new MEYVC_AB_Test();
		$ab_model->record_conversion( $variation_id, $revenue );

		$order->update_meta_data( self::META_RECORDED, '1' );
		$order->save();
	}
}
