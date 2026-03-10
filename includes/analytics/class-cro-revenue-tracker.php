<?php
/**
 * Revenue tracker
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CRO_Revenue_Tracker class.
 */
class CRO_Revenue_Tracker {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Track when order is completed.
		add_action( 'woocommerce_thankyou', array( $this, 'attribute_order_revenue' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'confirm_attribution' ), 10, 1 );
	}

	/**
	 * Attribute order revenue to campaigns based on session conversions.
	 *
	 * @param int $order_id Order ID.
	 */
	public function attribute_order_revenue( $order_id ) {
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Check if already attributed.
		if ( $order->get_meta( '_cro_attributed' ) ) {
			return;
		}

		// Get session ID from cookie.
		$session_id = isset( $_COOKIE['cro_session_id'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['cro_session_id'] ) ) : '';

		if ( empty( $session_id ) ) {
			return;
		}

		global $wpdb;
		$events_table    = $wpdb->prefix . 'cro_events';
		$campaigns_table = $wpdb->prefix . 'cro_campaigns';

		// Find conversions for this session.
		$conversions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT source_type, source_id, coupon_code 
				FROM {$events_table} 
				WHERE session_id = %s 
				AND event_type = 'conversion'
				AND created_at >= %s
				ORDER BY created_at DESC",
				$session_id,
				gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) )
			)
		);

		if ( empty( $conversions ) ) {
			return;
		}

		$order_total  = floatval( $order->get_total() );
		$order_coupons = $order->get_coupon_codes();

		foreach ( $conversions as $conversion ) {
			$attributed = false;

			// Check if coupon from campaign was used.
			$order_coupons_safe = is_array( $order_coupons ) ? $order_coupons : array();
			if ( ! empty( $conversion->coupon_code ) && in_array( (string) $conversion->coupon_code, $order_coupons_safe, true ) ) {
				$attributed = true;
			}

			// Check if conversion happened (email capture, CTA click).
			if ( 'campaign' === $conversion->source_type && ! $attributed ) {
				// Attribute to the campaign that converted the visitor.
				$attributed = true;
			}

			if ( $attributed && 'campaign' === $conversion->source_type && $conversion->source_id ) {
				// Update campaign revenue.
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$campaigns_table} 
						SET revenue_attributed = revenue_attributed + %f 
						WHERE id = %d",
						$order_total,
						$conversion->source_id
					)
				);

				// Log the attribution.
				$wpdb->update(
					$events_table,
					array(
						'order_id'    => $order_id,
						'order_value' => $order_total,
					),
					array(
						'session_id'  => $session_id,
						'source_type' => $conversion->source_type,
						'source_id'   => $conversion->source_id,
						'event_type'  => 'conversion',
					),
					array( '%d', '%f' ),
					array( '%s', '%s', '%d', '%s' )
				);

				// Mark order as attributed.
				$order->update_meta_data( '_cro_attributed', true );
				$order->update_meta_data( '_cro_campaign_id', $conversion->source_id );
				$order->save();

				break; // Only attribute to one campaign.
			}
		}
	}

	/**
	 * Confirm attribution when order is marked complete.
	 *
	 * @param int $order_id Order ID.
	 */
	public function confirm_attribution( $order_id ) {
		// Additional confirmation when order is marked complete.
		// Could be used for more advanced attribution logic.
	}

	/**
	 * Get revenue stats for the analytics admin page (last N days).
	 *
	 * @param int $days Number of days to include. Default 30.
	 * @return array{total_revenue: float, order_count: int}
	 */
	public static function get_revenue_stats( $days = 30 ) {
		global $wpdb;
		$events_table = $wpdb->prefix . 'cro_events';
		$date_limit   = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$total_revenue = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(order_value), 0) FROM {$events_table} 
				WHERE event_type = 'conversion' 
				AND order_value > 0 
				AND created_at >= %s",
				$date_limit
			)
		);
		$order_count   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT order_id) FROM {$events_table} 
				WHERE event_type = 'conversion' 
				AND order_id IS NOT NULL 
				AND created_at >= %s",
				$date_limit
			)
		);

		return array(
			'total_revenue' => isset( $total_revenue ) ? floatval( $total_revenue ) : 0.0,
			'order_count'   => isset( $order_count ) ? (int) $order_count : 0,
		);
	}
}
