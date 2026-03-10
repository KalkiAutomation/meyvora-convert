<?php
/**
 * Offer conversion attribution: record conversion when order uses CRO offer coupon or offer banner.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CRO_Offer_Attribution class.
 */
class CRO_Offer_Attribution {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_order_status_completed', array( $this, 'record_offer_conversions' ), 10, 1 );
	}

	/**
	 * When an order completes, attribute offer conversions if a CRO offer coupon was used or offer banner was applied.
	 *
	 * Records conversion events (event_type=conversion, object_type=offer, object_id=offer_id) with order_id and revenue in meta.
	 * Filter: cro_offer_attribution_logic allows overriding the attribution result.
	 *
	 * @param int $order_id Order ID.
	 */
	public function record_offer_conversions( $order_id ) {
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		$offer_ids = $this->get_offer_ids_for_order( $order );
		$revenue   = (float) $order->get_total();

		$context = array(
			'order_id'   => $order_id,
			'order'      => $order,
			'coupon_codes' => $order->get_coupon_codes(),
			'offer_ids_from_coupons' => $this->get_offer_ids_from_coupons( $order ),
			'offer_ids_from_logs'   => $this->get_offer_ids_from_logs( $order_id ),
		);

		$logic = array(
			'offer_ids' => array_values( array_unique( array_map( 'absint', $offer_ids ) ) ),
			'revenue'   => $revenue,
		);

		$logic = apply_filters( 'cro_offer_attribution_logic', $logic, $context );

		if ( empty( $logic['offer_ids'] ) || ! is_array( $logic['offer_ids'] ) ) {
			return;
		}

		$revenue = isset( $logic['revenue'] ) ? (float) $logic['revenue'] : $revenue;
		$tracker = new CRO_Tracker();

		foreach ( $logic['offer_ids'] as $offer_id ) {
			$offer_id = absint( $offer_id );
			if ( $offer_id <= 0 ) {
				continue;
			}
			$tracker->track(
				'conversion',
				0,
				array(
					'order_id' => $order_id,
					'revenue'  => $revenue,
				),
				'offer',
				$offer_id
			);
		}
	}

	/**
	 * Resolve offer IDs from order: used coupons with _cro_offer_id and/or offer_logs linked to this order.
	 *
	 * @param WC_Order $order Order.
	 * @return int[] Offer IDs.
	 */
	private function get_offer_ids_for_order( $order ) {
		$from_coupons = $this->get_offer_ids_from_coupons( $order );
		$from_logs    = $this->get_offer_ids_from_logs( $order->get_id() );
		return array_values( array_unique( array_merge( $from_coupons, $from_logs ) ) );
	}

	/**
	 * Get offer IDs for coupons used on the order (coupon post meta _cro_offer_id).
	 *
	 * @param WC_Order $order Order.
	 * @return int[]
	 */
	private function get_offer_ids_from_coupons( $order ) {
		$codes = $order->get_coupon_codes();
		if ( ! is_array( $codes ) || empty( $codes ) ) {
			return array();
		}

		$offer_ids = array();
		foreach ( $codes as $code ) {
			$code = is_string( $code ) ? trim( $code ) : '';
			if ( $code === '' ) {
				continue;
			}
			$coupon_id = function_exists( 'wc_get_coupon_id_by_code' ) ? wc_get_coupon_id_by_code( $code ) : 0;
			if ( ! $coupon_id ) {
				continue;
			}
			$offer_id = get_post_meta( $coupon_id, '_cro_offer_id', true );
			if ( $offer_id !== '' && $offer_id !== false ) {
				$offer_ids[] = absint( $offer_id );
			}
		}
		return $offer_ids;
	}

	/**
	 * Get offer IDs from cro_offer_logs where order_id matches (offer banner applied and linked to this order).
	 *
	 * @param int $order_id Order ID.
	 * @return int[]
	 */
	private function get_offer_ids_from_logs( $order_id ) {
		global $wpdb;
		$logs = $wpdb->prefix . 'cro_offer_logs';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $logs ) ) !== $logs ) {
			return array();
		}

		$col = $wpdb->get_var( "SHOW COLUMNS FROM {$logs} LIKE 'order_id'" );
		if ( ! $col ) {
			return array();
		}

		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT offer_id FROM {$logs} WHERE order_id = %d AND offer_id > 0",
				$order_id
			)
		);
		return is_array( $rows ) ? array_map( 'absint', $rows ) : array();
	}
}
