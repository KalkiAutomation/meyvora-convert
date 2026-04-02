<?php
/**
 * Offer conversion attribution: record conversion when order uses CRO offer coupon or offer banner.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MEYVC_Offer_Attribution class.
 */
class MEYVC_Offer_Attribution {

	/** @var string Object cache group for read-through DB queries. */
	private const DB_READ_CACHE_GROUP = 'meyvora_meyvc';

	/** @var int Read-through TTL (seconds). */
	private const DB_READ_CACHE_TTL = 300;

	/**
	 * @param string                    $descriptor 2–4 word slug.
	 * @param array<int|string|float> $params     Params.
	 * @return string
	 */
	private static function read_cache_key( string $descriptor, array $params ): string {
		return 'meyvora_meyvc_' . md5( $descriptor . '_' . implode( '_', array_map( 'strval', $params ) ) );
	}

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
	 * Filter: meyvc_offer_attribution_logic allows overriding the attribution result.
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

		$logic = apply_filters( 'meyvc_offer_attribution_logic', $logic, $context );

		if ( empty( $logic['offer_ids'] ) || ! is_array( $logic['offer_ids'] ) ) {
			return;
		}

		$revenue = isset( $logic['revenue'] ) ? (float) $logic['revenue'] : $revenue;
		$tracker = new MEYVC_Tracker();

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
	 * Resolve offer IDs from order: used coupons with _meyvc_offer_id and/or offer_logs linked to this order.
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
	 * Get offer IDs for coupons used on the order (coupon post meta _meyvc_offer_id).
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
			$offer_id = get_post_meta( $coupon_id, '_meyvc_offer_id', true );
			if ( $offer_id !== '' && $offer_id !== false ) {
				$offer_ids[] = absint( $offer_id );
			}
		}
		return $offer_ids;
	}

	/**
	 * Get offer IDs from meyvc_offer_logs where order_id matches (offer banner applied and linked to this order).
	 *
	 * @param int $order_id Order ID.
	 * @return int[]
	 */
	private function get_offer_ids_from_logs( $order_id ) {
		global $wpdb;
		$logs = $wpdb->prefix . 'meyvc_offer_logs';
		$ck_t = self::read_cache_key( 'table_exists_like', array( $logs ) );
		$f_t  = false;
		$tbl  = wp_cache_get( $ck_t, self::DB_READ_CACHE_GROUP, false, $f_t );
		if ( ! $f_t ) {
			$tbl = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $logs ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
			wp_cache_set( $ck_t, $tbl, self::DB_READ_CACHE_GROUP, self::DB_READ_CACHE_TTL );
		}
		if ( $tbl !== $logs ) {
			return array();
		}

		$ck_c = self::read_cache_key( 'show_column_like', array( $logs, 'order_id' ) );
		$f_c  = false;
		$col  = wp_cache_get( $ck_c, self::DB_READ_CACHE_GROUP, false, $f_c );
		if ( ! $f_c ) {
			$col = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $logs, 'order_id' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
			wp_cache_set( $ck_c, $col, self::DB_READ_CACHE_GROUP, self::DB_READ_CACHE_TTL );
		}
		if ( ! $col ) {
			return array();
		}

		$ck_ids = self::read_cache_key( 'offer_ids_by_order', array( $logs, $order_id ) );
		$rows   = wp_cache_get( $ck_ids, self::DB_READ_CACHE_GROUP );
		if ( false === $rows ) {
			$rows = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT DISTINCT offer_id FROM %i WHERE order_id = %d AND offer_id > 0',
					$logs,
					$order_id
				)
			);
			if ( ! is_array( $rows ) ) {
				$rows = array();
			}
			wp_cache_set( $ck_ids, $rows, self::DB_READ_CACHE_GROUP, self::DB_READ_CACHE_TTL );
		}
		return is_array( $rows ) ? array_map( 'absint', $rows ) : array();
	}
}
