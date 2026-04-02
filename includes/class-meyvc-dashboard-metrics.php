<?php
/**
 * Dashboard KPI queries (30d window, HPOS-safe aggregates).
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MEYVC_Dashboard_Metrics
 */
class MEYVC_Dashboard_Metrics {

	/**
	 * Sum cart totals for abandoned carts recovered in the last 30 days (from cart_json.totals.total).
	 *
	 * @return float
	 */
	public static function get_recovered_revenue_30d() {
		global $wpdb;
		$table = $wpdb->prefix . 'meyvc_abandoned_carts';
		if ( ! class_exists( 'MEYVC_Database' ) || ! MEYVC_Database::table_exists( $table ) ) {
			return 0.0;
		}
		$cache_key = 'meyvora_meyvc_' . md5( serialize( array( 'dashboard_recovered_revenue_30d', $table ) ) );
		$cached    = wp_cache_get( $cache_key, 'meyvora_meyvc' );
		if ( false !== $cached ) {
			return (float) $cached;
		}
		$sum = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
			$wpdb->prepare(
				'SELECT SUM( CAST( COALESCE( JSON_UNQUOTE( JSON_EXTRACT( cart_json, \'$.totals.total\' ) ), \'0\' ) AS DECIMAL(12,2) ) )
				FROM %i
				WHERE status = %s
				AND recovered_at IS NOT NULL
				AND recovered_at >= ( UTC_TIMESTAMP() - INTERVAL 30 DAY )',
				$table,
				'recovered'
			)
		);
		if ( $wpdb->last_error || null === $sum ) {
			$php = self::sum_recovered_totals_php( $table );
			wp_cache_set( $cache_key, $php, 'meyvora_meyvc', 300 );
			return $php;
		}
		$out = (float) $sum;
		wp_cache_set( $cache_key, $out, 'meyvora_meyvc', 300 );
		return $out;
	}

	/**
	 * Fallback when JSON functions unavailable.
	 *
	 * @param string $table Full table name.
	 * @return float
	 */
	private static function sum_recovered_totals_php( $table ) {
		global $wpdb;
		$cache_key = 'meyvora_meyvc_' . md5( serialize( array( 'dashboard_recovered_cart_json_rows', $table ) ) );
		$rows      = wp_cache_get( $cache_key, 'meyvora_meyvc' );
		if ( false === $rows ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT cart_json FROM %i WHERE status = %s AND recovered_at IS NOT NULL AND recovered_at >= ( UTC_TIMESTAMP() - INTERVAL 30 DAY )',
					$table,
					'recovered'
				),
				ARRAY_A
			);
			wp_cache_set( $cache_key, $rows, 'meyvora_meyvc', 300 );
		}
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}
		$sum = 0.0;
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$j = isset( $row['cart_json'] ) ? $row['cart_json'] : '';
				$d = json_decode( (string) $j, true );
				if ( is_array( $d ) && isset( $d['totals']['total'] ) ) {
					$sum += (float) $d['totals']['total'];
				}
			}
		}
		return $sum;
	}

	/**
	 * Rows with email captured in last 30 days.
	 *
	 * @return int
	 */
	public static function get_emails_captured_30d() {
		global $wpdb;
		$table = $wpdb->prefix . 'meyvc_abandoned_carts';
		if ( ! class_exists( 'MEYVC_Database' ) || ! MEYVC_Database::table_exists( $table ) ) {
			return 0;
		}
		$cache_key = 'meyvora_meyvc_' . md5( serialize( array( 'dashboard_emails_captured_30d', $table, 30 ) ) );
		$result    = wp_cache_get( $cache_key, 'meyvora_meyvc' );
		if ( false === $result ) {
			$result = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i
				WHERE email IS NOT NULL AND email <> \'\'
				AND created_at >= ( UTC_TIMESTAMP() - INTERVAL %d DAY )',
					$table,
					30
				)
			);
			wp_cache_set( $cache_key, $result, 'meyvora_meyvc', 300 );
		}
		return (int) $result;
	}

	/**
	 * Offer-attributed conversions in last 30 days.
	 *
	 * @return int
	 */
	public static function get_offer_conversions_30d() {
		global $wpdb;
		$table = $wpdb->prefix . 'meyvc_events';
		if ( ! class_exists( 'MEYVC_Database' ) || ! MEYVC_Database::table_exists( $table ) ) {
			return 0;
		}
		$cache_key = 'meyvora_meyvc_' . md5( serialize( array( 'dashboard_offer_conversions_30d', $table, 'conversion', 'offer', 30 ) ) );
		$result    = wp_cache_get( $cache_key, 'meyvora_meyvc' );
		if ( false === $result ) {
			$result = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i
				WHERE event_type = %s
				AND source_type = %s
				AND created_at >= ( UTC_TIMESTAMP() - INTERVAL %d DAY )',
					$table,
					'conversion',
					'offer',
					30
				)
			);
			wp_cache_set( $cache_key, $result, 'meyvora_meyvc', 300 );
		}
		return (int) $result;
	}

	/**
	 * Average order total (HPOS) for completed/processing orders, last 90 days GMT.
	 *
	 * @return float
	 */
	public static function get_avg_order_value_90d() {
		global $wpdb;
		$orders = $wpdb->prefix . 'wc_orders';
		$cache_key_exists = 'meyvora_meyvc_' . md5( serialize( array( 'dashboard_wc_orders_table_exists', $orders ) ) );
		$table_exists     = wp_cache_get( $cache_key_exists, 'meyvora_meyvc' );
		if ( false === $table_exists ) {
			$table_exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $orders )
			);
			wp_cache_set( $cache_key_exists, $table_exists, 'meyvora_meyvc', 300 );
		}
		if ( $table_exists !== $orders ) {
			return 0.0;
		}
		$cache_key_avg = 'meyvora_meyvc_' . md5( serialize( array( 'dashboard_avg_order_value_90d', $orders, 90 ) ) );
		$avg           = wp_cache_get( $cache_key_avg, 'meyvora_meyvc' );
		if ( false === $avg ) {
			$avg = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT AVG(total_amount) FROM %i
				WHERE status IN (\'wc-completed\',\'wc-processing\')
				AND date_created_gmt >= ( UTC_TIMESTAMP() - INTERVAL %d DAY )',
					$orders,
					90
				)
			);
			wp_cache_set( $cache_key_avg, $avg, 'meyvora_meyvc', 300 );
		}
		return $avg ? (float) $avg : 0.0;
	}

	/**
	 * Recent “wins” for dashboard feed: recovered carts + offer conversions.
	 *
	 * @param int $limit Max rows.
	 * @return array<int, array{type:string,label:string,amount:string,time:string,ts:int}>
	 */
	public static function get_recent_wins( $limit = 5 ) {
		global $wpdb;
		$limit = max( 1, min( 20, (int) $limit ) );
		$ac     = $wpdb->prefix . 'meyvc_abandoned_carts';
		$events = $wpdb->prefix . 'meyvc_events';
		$offers = $wpdb->prefix . 'meyvc_offers';
		$out    = array();

		if ( class_exists( 'MEYVC_Database' ) && MEYVC_Database::table_exists( $ac ) ) {
			$cache_key_carts = 'meyvora_meyvc_' . md5( serialize( array( 'dashboard_recent_recovered_carts', $ac, 'recovered', $limit * 2 ) ) );
			$carts           = wp_cache_get( $cache_key_carts, 'meyvora_meyvc' );
			if ( false === $carts ) {
				$carts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
					$wpdb->prepare(
						'SELECT recovered_at, cart_json FROM %i
					WHERE status = %s AND recovered_at IS NOT NULL
					ORDER BY recovered_at DESC
					LIMIT %d',
						$ac,
						'recovered',
						$limit * 2
					),
					ARRAY_A
				);
				wp_cache_set( $cache_key_carts, $carts, 'meyvora_meyvc', 300 );
			}
			if ( ! is_array( $carts ) ) {
				$carts = array();
			}
			if ( is_array( $carts ) ) {
				foreach ( $carts as $row ) {
					$ts = isset( $row['recovered_at'] ) ? strtotime( $row['recovered_at'] . ' UTC' ) : 0;
					$amt = 0.0;
					$d   = json_decode( (string) ( $row['cart_json'] ?? '' ), true );
					if ( is_array( $d ) && isset( $d['totals']['total'] ) ) {
						$amt = (float) $d['totals']['total'];
					}
					$out[] = array(
						'type'   => 'recovered',
						'label'  => __( 'Cart recovered', 'meyvora-convert' ),
						'amount' => function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $amt ) ) : (string) round( $amt, 2 ),
						'time'   => $ts ? human_time_diff( $ts, current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'meyvora-convert' ) : '',
						'ts'     => $ts,
					);
				}
			}
		}

		if ( class_exists( 'MEYVC_Database' ) && MEYVC_Database::table_exists( $events ) && MEYVC_Database::table_exists( $offers ) ) {
			$cache_key_wins = 'meyvora_meyvc_' . md5( serialize( array( 'dashboard_recent_offer_conversions', $events, $offers, 'conversion', 'offer', $limit * 2 ) ) );
			$rows           = wp_cache_get( $cache_key_wins, 'meyvora_meyvc' );
			if ( false === $rows ) {
				$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
					$wpdb->prepare(
						'SELECT e.created_at, e.order_value, o.name AS offer_name
					FROM %i e
					LEFT JOIN %i o ON e.source_id = o.id
					WHERE e.event_type = %s AND e.source_type = %s
					ORDER BY e.created_at DESC
					LIMIT %d',
						$events,
						$offers,
						'conversion',
						'offer',
						$limit * 2
					),
					ARRAY_A
				);
				wp_cache_set( $cache_key_wins, $rows, 'meyvora_meyvc', 300 );
			}
			if ( ! is_array( $rows ) ) {
				$rows = array();
			}
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$ts = isset( $row['created_at'] ) ? strtotime( $row['created_at'] . ' UTC' ) : 0;
					$name = isset( $row['offer_name'] ) && (string) $row['offer_name'] !== ''
						? (string) $row['offer_name']
						: __( 'Offer', 'meyvora-convert' );
					$out[] = array(
						'type'   => 'offer',
						'label'  => sprintf(
							/* translators: %s: offer name */
							__( 'Offer applied — %s', 'meyvora-convert' ),
							$name
						),
						'amount' => '',
						'time'   => $ts ? human_time_diff( $ts, current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'meyvora-convert' ) : '',
						'ts'     => $ts,
					);
				}
			}
		}

		usort(
			$out,
			static function ( $a, $b ) {
				return (int) ( $b['ts'] ?? 0 ) <=> (int) ( $a['ts'] ?? 0 );
			}
		);

		return array_slice( $out, 0, $limit );
	}
}
