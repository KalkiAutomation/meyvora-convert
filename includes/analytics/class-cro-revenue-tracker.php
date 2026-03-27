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
	 * @return void
	 */
	private static function flush_meyvora_cro_read_cache() {
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'meyvora_cro' );
		}
	}

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

		if ( function_exists( 'cro_settings' ) && ! cro_settings()->get( 'analytics', 'track_revenue', true ) ) {
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
		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );
		$cache_key_conv = 'meyvora_cro_' . md5( serialize( array( 'revenue_session_conversions_24h', $events_table, $session_id, $since ) ) );
		$conversions    = wp_cache_get( $cache_key_conv, 'meyvora_cro' );
		if ( false === $conversions ) {
			$conversions = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT DISTINCT source_type, source_id, coupon_code 
				FROM %i 
				WHERE session_id = %s 
				AND event_type = \'conversion\'
				AND created_at >= %s
				ORDER BY created_at DESC',
					$events_table,
					$session_id,
					$since
				)
			);
			wp_cache_set( $cache_key_conv, $conversions, 'meyvora_cro', 300 );
		}
		if ( ! is_array( $conversions ) ) {
			$conversions = array();
		}

		if ( empty( $conversions ) ) {
			return;
		}

		$order_total  = floatval( $order->get_total() );
		$order_coupons = $order->get_coupon_codes();

		$track_coupons = ! function_exists( 'cro_settings' ) || cro_settings()->get( 'analytics', 'track_coupons', true );

		foreach ( $conversions as $conversion ) {
			$attributed = false;

			// Check if coupon from campaign was used.
			$order_coupons_safe = is_array( $order_coupons ) ? $order_coupons : array();
			if ( $track_coupons && ! empty( $conversion->coupon_code ) && in_array( (string) $conversion->coupon_code, $order_coupons_safe, true ) ) {
				$attributed = true;
			}

			// Check if conversion happened (email capture, CTA click).
			if ( 'campaign' === $conversion->source_type && ! $attributed ) {
				// Attribute to the campaign that converted the visitor.
				$attributed = true;
			}

			if ( $attributed && 'campaign' === $conversion->source_type && $conversion->source_id ) {
				// Update campaign revenue.
				$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
					$wpdb->prepare(
						'UPDATE %i 
						SET revenue_attributed = revenue_attributed + %f 
						WHERE id = %d',
						$campaigns_table,
						$order_total,
						$conversion->source_id
					)
				);
				if ( class_exists( 'CRO_Database' ) ) {
					CRO_Database::invalidate_table_cache_after_write( $campaigns_table );
				}
				self::flush_meyvora_cro_read_cache();

				// Log the attribution.
				$ev_updated = $wpdb->update( $events_table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
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
				if ( false !== $ev_updated ) {
					if ( class_exists( 'CRO_Database' ) ) {
						CRO_Database::invalidate_table_cache_after_write( $events_table );
					}
					self::flush_meyvora_cro_read_cache();
				}

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
	 * Clamp days argument for date range queries (avoids strtotime injection / huge scans).
	 *
	 * @param mixed $days Raw days value.
	 * @return int Between 1 and 730.
	 */
	private static function normalize_days_range( $days ) {
		$d = (int) $days;
		return max( 1, min( 730, $d ) );
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
		$days         = self::normalize_days_range( $days );
		$date_limit   = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days' ) );

		$cache_key_sum = 'meyvora_cro_' . md5( serialize( array( 'revenue_stats_sum_order_value', $events_table, $date_limit ) ) );
		$total_revenue = wp_cache_get( $cache_key_sum, 'meyvora_cro' );
		if ( false === $total_revenue ) {
			$total_revenue = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT COALESCE(SUM(order_value), 0) FROM %i 
				WHERE event_type = \'conversion\' 
				AND order_value > 0 
				AND created_at >= %s',
					$events_table,
					$date_limit
				)
			);
			wp_cache_set( $cache_key_sum, $total_revenue, 'meyvora_cro', 300 );
		}
		$cache_key_cnt = 'meyvora_cro_' . md5( serialize( array( 'revenue_stats_distinct_orders', $events_table, $date_limit ) ) );
		$order_count   = wp_cache_get( $cache_key_cnt, 'meyvora_cro' );
		if ( false === $order_count ) {
			$order_count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT COUNT(DISTINCT order_id) FROM %i 
				WHERE event_type = \'conversion\' 
				AND order_id IS NOT NULL 
				AND created_at >= %s',
					$events_table,
					$date_limit
				)
			);
			wp_cache_set( $cache_key_cnt, $order_count, 'meyvora_cro', 300 );
		}

		return array(
			'total_revenue' => isset( $total_revenue ) ? floatval( $total_revenue ) : 0.0,
			'order_count'   => isset( $order_count ) ? (int) $order_count : 0,
		);
	}
}
