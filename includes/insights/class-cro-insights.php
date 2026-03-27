<?php
/**
 * CRO Insights — rule-based recommendations and analytics slices for the Insights admin tab.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CRO_Insights
 */
class CRO_Insights {

	const DAYS = 30;

	/** @var int Transient TTL seconds (3 hours). */
	const CACHE_TTL = 10800;

	/**
	 * Cached insight cards for the selected window.
	 *
	 * @param int $days Number of days (7–90).
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_insights( $days = self::DAYS ) {
		$days = self::normalize_days( (int) $days );
		$key  = 'cro_insights_' . $days . 'd';
		$hit  = get_transient( $key );
		if ( is_array( $hit ) ) {
			return $hit;
		}

		$cards = array_filter(
			array(
				self::insight_top_campaign( $days ),
				self::insight_underperforming( $days ),
				self::insight_best_time( $days ),
				self::insight_worst_time( $days ),
				self::insight_offer_no_conversion( $days ),
				self::insight_high_value_abandoned( $days ),
				self::insight_ab_test_ready( $days ),
				self::insight_booster_no_lift( $days ),
				self::insight_checkout_drop( $days ),
				self::insight_email_growth( $days ),
				self::insight_device_gap( $days ),
				self::insight_recovery_email_drop( $days ),
			)
		);
		$cards = array_values( $cards );
		usort(
			$cards,
			static function ( $a, $b ) {
				$pa = isset( $a['priority'] ) ? (int) $a['priority'] : 3;
				$pb = isset( $b['priority'] ) ? (int) $b['priority'] : 3;
				if ( $pa !== $pb ) {
					return $pa <=> $pb;
				}
				return strcmp( (string) ( $a['id'] ?? '' ), (string) ( $b['id'] ?? '' ) );
			}
		);
		$cards = apply_filters( 'cro_insights_cards', $cards, $days );
		if ( ! is_array( $cards ) ) {
			$cards = array();
		}
		set_transient( $key, $cards, self::CACHE_TTL );
		return $cards;
	}

	/**
	 * @param int $days Window days.
	 * @return int
	 */
	private static function normalize_days( int $days ): int {
		if ( $days < 7 ) {
			return 7;
		}
		if ( $days > 90 ) {
			return 90;
		}
		return $days;
	}

	/**
	 * Current vs previous period metrics.
	 *
	 * @param int $days Window length.
	 * @return array<string, array{current: float|int, previous: float|int, change_pct: float|null}>
	 */
	public static function get_period_comparison( int $days = self::DAYS ): array {
		$days      = self::normalize_days( $days );
		$date_to   = wp_date( 'Y-m-d' );
		$date_from = wp_date( 'Y-m-d', strtotime( '-' . $days . ' days' ) );
		$prev_to   = wp_date( 'Y-m-d', strtotime( $date_from . ' -1 day' ) );
		$prev_from = wp_date( 'Y-m-d', strtotime( $prev_to . ' -' . $days . ' days' ) );

		$analytics = new CRO_Analytics();
		$cur       = $analytics->get_summary( $date_from, $date_to );
		$prev      = $analytics->get_summary( $prev_from, $prev_to );

		$ab_cur  = self::get_abandoned_recovery_snapshot( $date_from, $date_to );
		$ab_prev = self::get_abandoned_recovery_snapshot( $prev_from, $prev_to );

		$keys = array( 'impressions', 'conversions', 'revenue', 'emails', 'recovery_rate' );
		$out  = array();
		foreach ( $keys as $k ) {
			if ( 'recovery_rate' === $k ) {
				$c = $ab_cur['rate'];
				$p = $ab_prev['rate'];
			} elseif ( 'revenue' === $k ) {
				$c = isset( $cur['revenue'] ) ? (float) $cur['revenue'] : 0.0;
				$p = isset( $prev['revenue'] ) ? (float) $prev['revenue'] : 0.0;
			} elseif ( 'emails' === $k ) {
				$c = isset( $cur['emails'] ) ? (int) $cur['emails'] : 0;
				$p = isset( $prev['emails'] ) ? (int) $prev['emails'] : 0;
			} else {
				$c = isset( $cur[ $k ] ) ? (int) $cur[ $k ] : 0;
				$p = isset( $prev[ $k ] ) ? (int) $prev[ $k ] : 0;
			}
			$out[ $k ] = array(
				'current'    => $c,
				'previous'   => $p,
				'change_pct' => self::pct_change( (float) $c, (float) $p ),
			);
		}
		return $out;
	}

	/**
	 * @param float $cur Current.
	 * @param float $prev Previous.
	 * @return float|null
	 */
	private static function pct_change( float $cur, float $prev ): ?float {
		if ( $prev == 0.0 ) {
			return null;
		}
		return round( ( ( $cur - $prev ) / $prev ) * 100, 1 );
	}

	/**
	 * @param string $from Y-m-d.
	 * @param string $to   Y-m-d.
	 * @return array{abandoned: int, recovered: int, rate: float}
	 */
	private static function get_abandoned_recovery_snapshot( string $from, string $to ): array {
		$cached = CRO_Database::object_cache_get( 'insights_ab_recovery', array( $from, $to ) );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		$t = $wpdb->prefix . 'cro_abandoned_carts';
		if ( ! CRO_Database::table_exists( $t ) ) {
			$empty = array( 'abandoned' => 0, 'recovered' => 0, 'rate' => 0.0 );
			CRO_Database::object_cache_set( 'insights_ab_recovery', array( $from, $to ), $empty );
			return $empty;
		}
		$cache_key_abandoned = 'meyvora_cro_' . md5( serialize( array( 'ab_recovery_abandoned_count', $t, $from, $to ) ) );
		$abandoned           = wp_cache_get( $cache_key_abandoned, 'meyvora_cro' );
		if ( false === $abandoned ) {
			$abandoned = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY)',
					$t,
					$from,
					$to
				)
			);
			wp_cache_set( $cache_key_abandoned, $abandoned, 'meyvora_cro', 300 );
		} else {
			$abandoned = (int) $abandoned;
		}
		$cache_key_recovered = 'meyvora_cro_' . md5( serialize( array( 'ab_recovery_recovered_count', $t, $from, $to ) ) );
		$recovered           = wp_cache_get( $cache_key_recovered, 'meyvora_cro' );
		if ( false === $recovered ) {
			$recovered = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE recovered_at IS NOT NULL AND recovered_at >= %s AND recovered_at < DATE_ADD(%s, INTERVAL 1 DAY)',
					$t,
					$from,
					$to
				)
			);
			wp_cache_set( $cache_key_recovered, $recovered, 'meyvora_cro', 300 );
		} else {
			$recovered = (int) $recovered;
		}
		$rate      = $abandoned > 0 ? round( ( $recovered / $abandoned ) * 100, 2 ) : 0.0;
		$out       = array(
			'abandoned' => $abandoned,
			'recovered' => $recovered,
			'rate'      => $rate,
		);
		CRO_Database::object_cache_set( 'insights_ab_recovery', array( $from, $to ), $out );
		return $out;
	}

	/**
	 * Funnel counts for campaign popups.
	 *
	 * @param int $days Window days.
	 * @return array{sessions_with_impression: int, popup_clicks: int, emails_captured: int, orders: int}
	 */
	public static function get_funnel_data( int $days = self::DAYS ): array {
		$days      = self::normalize_days( $days );
		$date_to   = wp_date( 'Y-m-d' );
		$date_from = wp_date( 'Y-m-d', strtotime( '-' . $days . ' days' ) );
		$cached    = CRO_Database::object_cache_get( 'insights_funnel', array( $days, $date_from, $date_to ) );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		$t = $wpdb->prefix . 'cro_events';
		if ( ! CRO_Database::table_exists( $t ) ) {
			$empty = array(
				'sessions_with_impression' => 0,
				'popup_clicks'             => 0,
				'emails_captured'          => 0,
				'orders'                   => self::fallback_order_count( $date_from, $date_to ),
			);
			CRO_Database::object_cache_set( 'insights_funnel', array( $days, $date_from, $date_to ), $empty );
			return $empty;
		}
		$cache_key_sessions_imp = 'meyvora_cro_' . md5( serialize( array( 'funnel_sessions_impression', $t, $date_from, $date_to ) ) );
		$sessions_imp           = wp_cache_get( $cache_key_sessions_imp, 'meyvora_cro' );
		if ( false === $sessions_imp ) {
			$sessions_imp = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT session_id) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = 'impression' AND source_type = 'campaign'",
					$t,
					$date_from,
					$date_to
				)
			);
			wp_cache_set( $cache_key_sessions_imp, $sessions_imp, 'meyvora_cro', 300 );
		} else {
			$sessions_imp = (int) $sessions_imp;
		}
		$cache_key_clicks = 'meyvora_cro_' . md5( serialize( array( 'funnel_sessions_clicks', $t, $date_from, $date_to ) ) );
		$clicks           = wp_cache_get( $cache_key_clicks, 'meyvora_cro' );
		if ( false === $clicks ) {
			$clicks = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT session_id) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = 'interaction' AND source_type = 'campaign'",
					$t,
					$date_from,
					$date_to
				)
			);
			wp_cache_set( $cache_key_clicks, $clicks, 'meyvora_cro', 300 );
		} else {
			$clicks = (int) $clicks;
		}
		$cache_key_emails = 'meyvora_cro_' . md5( serialize( array( 'funnel_emails_captured', $t, $date_from, $date_to ) ) );
		$emails           = wp_cache_get( $cache_key_emails, 'meyvora_cro' );
		if ( false === $emails ) {
			$emails = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = 'conversion' AND source_type = 'campaign' AND email IS NOT NULL AND email != ''",
					$t,
					$date_from,
					$date_to
				)
			);
			wp_cache_set( $cache_key_emails, $emails, 'meyvora_cro', 300 );
		} else {
			$emails = (int) $emails;
		}
		$cache_key_orders = 'meyvora_cro_' . md5( serialize( array( 'funnel_orders_count', $t, $date_from, $date_to ) ) );
		$orders           = wp_cache_get( $cache_key_orders, 'meyvora_cro' );
		if ( false === $orders ) {
			$orders = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = 'conversion' AND source_type = 'campaign' AND order_id IS NOT NULL AND order_id > 0",
					$t,
					$date_from,
					$date_to
				)
			);
			wp_cache_set( $cache_key_orders, $orders, 'meyvora_cro', 300 );
		} else {
			$orders = (int) $orders;
		}
		if ( $orders < 1 && function_exists( 'wc_get_orders' ) ) {
			$orders = self::fallback_order_count( $date_from, $date_to );
		}
		$out = array(
			'sessions_with_impression' => $sessions_imp,
			'popup_clicks'             => $clicks,
			'emails_captured'          => $emails,
			'orders'                   => $orders,
		);
		CRO_Database::object_cache_set( 'insights_funnel', array( $days, $date_from, $date_to ), $out );
		return $out;
	}

	/**
	 * @param string $from Y-m-d.
	 * @param string $to   Y-m-d.
	 * @return int
	 */
	private static function fallback_order_count( string $from, string $to ): int {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 0;
		}
		try {
			$ts_from = strtotime( $from . ' 00:00:00' );
			$ts_to   = strtotime( $to . ' 23:59:59' );
			if ( ! $ts_from || ! $ts_to ) {
				return 0;
			}
			$orders = wc_get_orders(
				array(
					'limit'          => 500,
					'status'         => array( 'wc-completed', 'wc-processing' ),
					'date_created'   => $ts_from . '...' . $ts_to,
					'return'         => 'ids',
					'paginate'       => false,
				)
			);
			return is_array( $orders ) ? count( $orders ) : 0;
		} catch ( \Throwable $e ) {
			return 0;
		}
	}

	/**
	 * 7×24 matrix: Monday index 0 .. Sunday 6, hour 0–23 = conversion counts.
	 *
	 * @param int $days Window days.
	 * @return array<int, array<int, int>>
	 */
	public static function get_hourly_heatmap( int $days = self::DAYS ): array {
		$matrix = array();
		for ( $d = 0; $d < 7; $d++ ) {
			$matrix[ $d ] = array_fill( 0, 24, 0 );
		}
		$days      = self::normalize_days( $days );
		$date_to   = wp_date( 'Y-m-d' );
		$date_from = wp_date( 'Y-m-d', strtotime( '-' . $days . ' days' ) );
		$cached    = CRO_Database::object_cache_get( 'insights_hourly_heatmap', array( $days, $date_from, $date_to ) );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		$t = $wpdb->prefix . 'cro_events';
		if ( ! CRO_Database::table_exists( $t ) ) {
			CRO_Database::object_cache_set( 'insights_hourly_heatmap', array( $days, $date_from, $date_to ), $matrix );
			return $matrix;
		}
		$cache_key_heatmap = 'meyvora_cro_' . md5( serialize( array( 'hourly_heatmap_conversions_by_dow_hr', $t, $date_from, $date_to ) ) );
		$rows              = wp_cache_get( $cache_key_heatmap, 'meyvora_cro' );
		if ( false === $rows ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					"SELECT WEEKDAY(created_at) AS dow, HOUR(created_at) AS hr, COUNT(*) AS c
				FROM %i
				WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = 'conversion'
				GROUP BY dow, hr",
					$t,
					$date_from,
					$date_to
				),
				ARRAY_A
			);
			if ( ! is_array( $rows ) ) {
				$rows = array();
			}
			wp_cache_set( $cache_key_heatmap, $rows, 'meyvora_cro', 300 );
		} else {
			$rows = is_array( $rows ) ? $rows : array();
		}
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$dow = isset( $row['dow'] ) ? (int) $row['dow'] : -1;
			$hr  = isset( $row['hr'] ) ? (int) $row['hr'] : -1;
			$c   = isset( $row['c'] ) ? (int) $row['c'] : 0;
			if ( $dow >= 0 && $dow <= 6 && $hr >= 0 && $hr <= 23 ) {
				$matrix[ $dow ][ $hr ] = $c;
			}
		}
		CRO_Database::object_cache_set( 'insights_hourly_heatmap', array( $days, $date_from, $date_to ), $matrix );
		return $matrix;
	}

	/**
	 * All campaigns with metrics, sorted by conversion_rate DESC.
	 *
	 * @param int $days Window days.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_campaign_comparison( int $days = self::DAYS ): array {
		$days      = self::normalize_days( $days );
		$date_to   = wp_date( 'Y-m-d' );
		$date_from = wp_date( 'Y-m-d', strtotime( '-' . $days . ' days' ) );
		$analytics = new CRO_Analytics();
		$rows      = $analytics->get_campaign_performance( $date_from, $date_to, 500 );
		$out       = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$imp = (int) ( $row['impressions'] ?? 0 );
			$cv  = (int) ( $row['conversions'] ?? 0 );
			$rate = $imp > 0 ? round( ( $cv / $imp ) * 100, 2 ) : 0.0;
			$out[] = array(
				'id'               => (int) ( $row['id'] ?? 0 ),
				'name'             => isset( $row['name'] ) ? sanitize_text_field( (string) $row['name'] ) : '',
				'impressions'      => $imp,
				'conversions'      => $cv,
				'conversion_rate'  => $rate,
				'revenue_attributed' => isset( $row['revenue'] ) ? (float) $row['revenue'] : 0.0,
				'emails_captured'  => isset( $row['emails'] ) ? (int) $row['emails'] : 0,
			);
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return ( $b['conversion_rate'] <=> $a['conversion_rate'] );
			}
		);
		return $out;
	}

	/**
	 * Per-offer stats for the window.
	 *
	 * @param int $days Window days.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_offer_performance( int $days = self::DAYS ): array {
		global $wpdb;
		$days      = self::normalize_days( $days );
		$date_to   = wp_date( 'Y-m-d' );
		$date_from = wp_date( 'Y-m-d', strtotime( '-' . $days . ' days' ) );
		$cached    = CRO_Database::object_cache_get( 'insights_offer_perf', array( $days, $date_from, $date_to ) );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}
		$logs      = $wpdb->prefix . 'cro_offer_logs';
		$offers    = $wpdb->prefix . 'cro_offers';
		if ( ! CRO_Database::table_exists( $logs ) || ! CRO_Database::table_exists( $offers ) ) {
			CRO_Database::object_cache_set( 'insights_offer_perf', array( $days, $date_from, $date_to ), array() );
			return array();
		}
		$cache_key_offer_logs_col = 'meyvora_cro_' . md5( serialize( array( 'offer_logs_action_column', $logs ) ) );
		$has_action_raw           = wp_cache_get( $cache_key_offer_logs_col, 'meyvora_cro' );
		if ( false === $has_action_raw ) {
			$has_action_raw = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $logs, 'action' )
			);
			wp_cache_set( $cache_key_offer_logs_col, $has_action_raw ? 1 : 0, 'meyvora_cro', 300 );
		}
		$has_action = (bool) (int) $has_action_raw;
		if ( $has_action ) {
			$cache_key_offer_perf_wa = 'meyvora_cro_' . md5( serialize( array( 'offer_perf_with_action', $logs, $date_from, $date_to ) ) );
			$rows                    = wp_cache_get( $cache_key_offer_perf_wa, 'meyvora_cro' );
			if ( false === $rows ) {
				$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
					$wpdb->prepare(
						'SELECT l.offer_id, COUNT(*) AS applies,
				SUM(CASE WHEN l.order_id IS NOT NULL AND l.order_id > 0 THEN 1 ELSE 0 END) AS orders_with_coupon
				FROM %i l WHERE l.created_at >= %s AND l.created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND l.action = %s GROUP BY l.offer_id',
						$logs,
						$date_from,
						$date_to,
						'applied'
					),
					ARRAY_A
				);
				if ( ! is_array( $rows ) ) {
					$rows = array();
				}
				wp_cache_set( $cache_key_offer_perf_wa, $rows, 'meyvora_cro', 300 );
			} else {
				$rows = is_array( $rows ) ? $rows : array();
			}
		} else {
			$cache_key_offer_perf_na = 'meyvora_cro_' . md5( serialize( array( 'offer_perf_no_action', $logs, $date_from, $date_to ) ) );
			$rows                    = wp_cache_get( $cache_key_offer_perf_na, 'meyvora_cro' );
			if ( false === $rows ) {
				$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
					$wpdb->prepare(
						'SELECT l.offer_id, COUNT(*) AS applies,
				SUM(CASE WHEN l.order_id IS NOT NULL AND l.order_id > 0 THEN 1 ELSE 0 END) AS orders_with_coupon
				FROM %i l WHERE l.created_at >= %s AND l.created_at < DATE_ADD(%s, INTERVAL 1 DAY) GROUP BY l.offer_id',
						$logs,
						$date_from,
						$date_to
					),
					ARRAY_A
				);
				if ( ! is_array( $rows ) ) {
					$rows = array();
				}
				wp_cache_set( $cache_key_offer_perf_na, $rows, 'meyvora_cro', 300 );
			} else {
				$rows = is_array( $rows ) ? $rows : array();
			}
		}

		$list = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$oid    = (int) ( $row['offer_id'] ?? 0 );
			$applies = (int) ( $row['applies'] ?? 0 );
			$ordcnt  = (int) ( $row['orders_with_coupon'] ?? 0 );
			$cache_key_offer_name = 'meyvora_cro_' . md5( serialize( array( 'offer_name_by_id', $offers, $oid ) ) );
			$name                 = wp_cache_get( $cache_key_offer_name, 'meyvora_cro' );
			if ( false === $name ) {
				$name = (string) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
					$wpdb->prepare( 'SELECT name FROM %i WHERE id = %d', $offers, $oid )
				);
				wp_cache_set( $cache_key_offer_name, $name, 'meyvora_cro', 300 );
			} else {
				$name = (string) $name;
			}
			$revenue = self::sum_revenue_for_offer_orders( $logs, $oid, $date_from, $date_to );
			$roi     = $applies > 0 ? round( $revenue / $applies, 2 ) : 0.0;
			$list[]  = array(
				'offer_id'               => $oid,
				'name'                   => $name ? sanitize_text_field( (string) $name ) : __( 'Unknown offer', 'meyvora-convert' ),
				'applies'                => $applies,
				'orders_with_coupon'     => $ordcnt,
				'revenue_from_coupon_orders' => $revenue,
				'roi'                    => $roi,
			);
		}
		CRO_Database::object_cache_set( 'insights_offer_perf', array( $days, $date_from, $date_to ), $list );
		return $list;
	}

	/**
	 * Sum order totals for distinct order_ids linked to an offer in the window (capped).
	 *
	 * @param string $logs      Table name.
	 * @param int    $offer_id  Offer ID.
	 * @param string $date_from Y-m-d.
	 * @param string $date_to   Y-m-d.
	 * @return float
	 */
	private static function sum_revenue_for_offer_orders( string $logs, int $offer_id, string $date_from, string $date_to ): float {
		$cached = CRO_Database::object_cache_get( 'insights_offer_rev_sum', array( $logs, $offer_id, $date_from, $date_to ) );
		if ( false !== $cached && is_numeric( $cached ) ) {
			return (float) $cached;
		}
		global $wpdb;
		$cache_key_rev_ids = 'meyvora_cro_' . md5( serialize( array( 'offer_rev_order_ids', $logs, $offer_id, $date_from, $date_to ) ) );
		$ids               = wp_cache_get( $cache_key_rev_ids, 'meyvora_cro' );
		if ( false === $ids ) {
			$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					'SELECT DISTINCT order_id FROM %i WHERE offer_id = %d AND order_id IS NOT NULL AND order_id > 0 AND created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) LIMIT 300',
					$logs,
					$offer_id,
					$date_from,
					$date_to
				)
			);
			if ( ! is_array( $ids ) ) {
				$ids = array();
			}
			wp_cache_set( $cache_key_rev_ids, $ids, 'meyvora_cro', 300 );
		} else {
			$ids = is_array( $ids ) ? $ids : array();
		}
		$sum = 0.0;
		if ( ! function_exists( 'wc_get_order' ) || ! is_array( $ids ) ) {
			return $sum;
		}
		foreach ( $ids as $oid ) {
			$oid = absint( $oid );
			if ( $oid < 1 ) {
				continue;
			}
			$order = wc_get_order( $oid );
			if ( $order ) {
				$sum += (float) $order->get_total();
			}
		}
		CRO_Database::object_cache_set( 'insights_offer_rev_sum', array( $logs, $offer_id, $date_from, $date_to ), $sum );
		return $sum;
	}

	/**
	 * Abandoned cart aggregates.
	 *
	 * @param int $days Window days.
	 * @return array<string, mixed>
	 */
	public static function get_abandoned_cart_stats( int $days = self::DAYS ): array {
		global $wpdb;
		$days      = self::normalize_days( $days );
		$date_to   = wp_date( 'Y-m-d' );
		$date_from = wp_date( 'Y-m-d', strtotime( '-' . $days . ' days' ) );
		$cached    = CRO_Database::object_cache_get( 'insights_ab_cart_stats', array( $days, $date_from, $date_to ) );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}
		$t         = $wpdb->prefix . 'cro_abandoned_carts';
		$empty     = array(
			'total_abandoned'        => 0,
			'total_recovered'        => 0,
			'recovery_rate'          => 0.0,
			'avg_value'              => 0.0,
			'by_value_band'          => array(
				'0-50'   => 0,
				'50-100' => 0,
				'100-200'=> 0,
				'200+'   => 0,
			),
			'email_1_recovery_rate'  => null,
			'email_2_recovery_rate'  => null,
			'email_3_recovery_rate'  => null,
		);
		if ( ! CRO_Database::table_exists( $t ) ) {
			CRO_Database::object_cache_set( 'insights_ab_cart_stats', array( $days, $date_from, $date_to ), $empty );
			return $empty;
		}

		$cache_key_total_ab = 'meyvora_cro_' . md5( serialize( array( 'ab_cart_total_abandoned', $t, $date_from, $date_to ) ) );
		$total_abandoned    = wp_cache_get( $cache_key_total_ab, 'meyvora_cro' );
		if ( false === $total_abandoned ) {
			$total_abandoned = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY)',
					$t,
					$date_from,
					$date_to
				)
			);
			wp_cache_set( $cache_key_total_ab, $total_abandoned, 'meyvora_cro', 300 );
		} else {
			$total_abandoned = (int) $total_abandoned;
		}
		$cache_key_total_rec = 'meyvora_cro_' . md5( serialize( array( 'ab_cart_total_recovered', $t, $date_from, $date_to ) ) );
		$total_recovered     = wp_cache_get( $cache_key_total_rec, 'meyvora_cro' );
		if ( false === $total_recovered ) {
			$total_recovered = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE recovered_at IS NOT NULL AND recovered_at >= %s AND recovered_at < DATE_ADD(%s, INTERVAL 1 DAY)',
					$t,
					$date_from,
					$date_to
				)
			);
			wp_cache_set( $cache_key_total_rec, $total_recovered, 'meyvora_cro', 300 );
		} else {
			$total_recovered = (int) $total_recovered;
		}
		$rate            = $total_abandoned > 0 ? round( ( $total_recovered / $total_abandoned ) * 100, 2 ) : 0.0;

		$cache_key_avg_cart = 'meyvora_cro_' . md5( serialize( array( 'ab_cart_avg_cart_total', $t, $date_from, $date_to ) ) );
		$avg                = wp_cache_get( $cache_key_avg_cart, 'meyvora_cro' );
		if ( false === $avg ) {
			$raw = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					"SELECT AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(cart_json, '$.totals.total')) AS DECIMAL(12,2))) FROM %i
				WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND JSON_VALID(cart_json) AND JSON_EXTRACT(cart_json, '$.totals.total') IS NOT NULL",
					$t,
					$date_from,
					$date_to
				)
			);
			$avg = is_numeric( $raw ) ? (float) $raw : 0.0;
			wp_cache_set( $cache_key_avg_cart, $avg, 'meyvora_cro', 300 );
		} else {
			$avg = is_numeric( $avg ) ? (float) $avg : 0.0;
		}
		$avg_val = is_numeric( $avg ) ? round( (float) $avg, 2 ) : 0.0;

		$cache_key_band_0_50 = 'meyvora_cro_' . md5( serialize( array( 'ab_cart_band_0_50', $t, $date_from, $date_to ) ) );
		$band_0_50           = wp_cache_get( $cache_key_band_0_50, 'meyvora_cro' );
		if ( false === $band_0_50 ) {
			$band_0_50 = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND JSON_VALID(cart_json)
					AND CAST(JSON_UNQUOTE(JSON_EXTRACT(cart_json, '$.totals.total')) AS DECIMAL(12,2)) >= 0
					AND CAST(JSON_UNQUOTE(JSON_EXTRACT(cart_json, '$.totals.total')) AS DECIMAL(12,2)) < 50",
					$t,
					$date_from,
					$date_to
				)
			);
			wp_cache_set( $cache_key_band_0_50, $band_0_50, 'meyvora_cro', 300 );
		} else {
			$band_0_50 = (int) $band_0_50;
		}
		$cache_key_band_50_100 = 'meyvora_cro_' . md5( serialize( array( 'ab_cart_band_50_100', $t, $date_from, $date_to ) ) );
		$band_50_100           = wp_cache_get( $cache_key_band_50_100, 'meyvora_cro' );
		if ( false === $band_50_100 ) {
			$band_50_100 = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND JSON_VALID(cart_json)
					AND CAST(JSON_UNQUOTE(JSON_EXTRACT(cart_json, '$.totals.total')) AS DECIMAL(12,2)) >= 50
					AND CAST(JSON_UNQUOTE(JSON_EXTRACT(cart_json, '$.totals.total')) AS DECIMAL(12,2)) < 100",
					$t,
					$date_from,
					$date_to
				)
			);
			wp_cache_set( $cache_key_band_50_100, $band_50_100, 'meyvora_cro', 300 );
		} else {
			$band_50_100 = (int) $band_50_100;
		}
		$cache_key_band_100_200 = 'meyvora_cro_' . md5( serialize( array( 'ab_cart_band_100_200', $t, $date_from, $date_to ) ) );
		$band_100_200           = wp_cache_get( $cache_key_band_100_200, 'meyvora_cro' );
		if ( false === $band_100_200 ) {
			$band_100_200 = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND JSON_VALID(cart_json)
					AND CAST(JSON_UNQUOTE(JSON_EXTRACT(cart_json, '$.totals.total')) AS DECIMAL(12,2)) >= 100
					AND CAST(JSON_UNQUOTE(JSON_EXTRACT(cart_json, '$.totals.total')) AS DECIMAL(12,2)) < 200",
					$t,
					$date_from,
					$date_to
				)
			);
			wp_cache_set( $cache_key_band_100_200, $band_100_200, 'meyvora_cro', 300 );
		} else {
			$band_100_200 = (int) $band_100_200;
		}
		$cache_key_band_200_plus = 'meyvora_cro_' . md5( serialize( array( 'ab_cart_band_200_plus', $t, $date_from, $date_to ) ) );
		$band_200_plus           = wp_cache_get( $cache_key_band_200_plus, 'meyvora_cro' );
		if ( false === $band_200_plus ) {
			$band_200_plus = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND JSON_VALID(cart_json)
					AND CAST(JSON_UNQUOTE(JSON_EXTRACT(cart_json, '$.totals.total')) AS DECIMAL(12,2)) >= 200",
					$t,
					$date_from,
					$date_to
				)
			);
			wp_cache_set( $cache_key_band_200_plus, $band_200_plus, 'meyvora_cro', 300 );
		} else {
			$band_200_plus = (int) $band_200_plus;
		}
		$bands = array(
			'0-50'    => $band_0_50,
			'50-100'  => $band_50_100,
			'100-200' => $band_100_200,
			'200+'    => $band_200_plus,
		);

		$e1 = self::recovery_rate_for_email_step( $t, 1, $date_from, $date_to );
		$e2 = self::recovery_rate_for_email_step( $t, 2, $date_from, $date_to );
		$e3 = self::recovery_rate_for_email_step( $t, 3, $date_from, $date_to );

		$out = array(
			'total_abandoned'       => $total_abandoned,
			'total_recovered'       => $total_recovered,
			'recovery_rate'         => $rate,
			'avg_value'             => $avg_val,
			'by_value_band'         => $bands,
			'email_1_recovery_rate' => $e1,
			'email_2_recovery_rate' => $e2,
			'email_3_recovery_rate' => $e3,
		);
		CRO_Database::object_cache_set( 'insights_ab_cart_stats', array( $days, $date_from, $date_to ), $out );
		return $out;
	}

	/**
	 * Recovery rate among carts that received a given reminder (sent_at set).
	 *
	 * @param string $table     Table name.
	 * @param int    $step      1–3.
	 * @param string $date_from Y-m-d.
	 * @param string $date_to   Y-m-d.
	 * @return float|null
	 */
	private static function recovery_rate_for_email_step( string $table, int $step, string $date_from, string $date_to ): ?float {
		$params = array( $table, $step, $date_from, $date_to );
		$cached = CRO_Database::object_cache_get( 'insights_recovery_email_step', $params );
		if ( false !== $cached && is_array( $cached ) && isset( $cached['_nil'] ) ) {
			return $cached['_nil'] ? null : (float) ( $cached['f'] ?? 0 );
		}
		global $wpdb;
		$cols = array(
			1 => 'email_1_sent_at',
			2 => 'email_2_sent_at',
			3 => 'email_3_sent_at',
		);
		if ( ! isset( $cols[ $step ] ) ) {
			CRO_Database::object_cache_set( 'insights_recovery_email_step', $params, array( '_nil' => true ) );
			return null;
		}
		$col = $cols[ $step ];
		$cache_key_recovery_sent = 'meyvora_cro_' . md5( serialize( array( 'recovery_email_sent_count', $table, $col, $date_from, $date_to, $step ) ) );
		$sent                    = wp_cache_get( $cache_key_recovery_sent, 'meyvora_cro' );
		if ( false === $sent ) {
			$sent = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE %i >= %s AND %i < DATE_ADD(%s, INTERVAL 1 DAY) AND %i IS NOT NULL',
					$table,
					$col,
					$date_from,
					$col,
					$date_to,
					$col
				)
			);
			wp_cache_set( $cache_key_recovery_sent, $sent, 'meyvora_cro', 300 );
		} else {
			$sent = (int) $sent;
		}
		if ( $sent < 1 ) {
			CRO_Database::object_cache_set( 'insights_recovery_email_step', $params, array( '_nil' => true ) );
			return null;
		}
		$cache_key_recovery_rec = 'meyvora_cro_' . md5( serialize( array( 'recovery_email_recovered_count', $table, $col, $date_from, $date_to, $step ) ) );
		$rec                    = wp_cache_get( $cache_key_recovery_rec, 'meyvora_cro' );
		if ( false === $rec ) {
			$rec = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE %i >= %s AND %i < DATE_ADD(%s, INTERVAL 1 DAY) AND %i IS NOT NULL AND recovered_at IS NOT NULL',
					$table,
					$col,
					$date_from,
					$col,
					$date_to,
					$col
				)
			);
			wp_cache_set( $cache_key_recovery_rec, $rec, 'meyvora_cro', 300 );
		} else {
			$rec = (int) $rec;
		}
		$rate = round( ( $rec / $sent ) * 100, 2 );
		CRO_Database::object_cache_set( 'insights_recovery_email_step', $params, array( '_nil' => false, 'f' => $rate ) );
		return $rate;
	}

	/**
	 * Delete cached insight transients for common day ranges.
	 */
	public static function bust_insights_cache(): void {
		$days_list = apply_filters(
			'cro_insights_cached_day_keys',
			array( 7, 14, 21, 30, 45, 60, 75, 90 )
		);
		if ( ! is_array( $days_list ) ) {
			$days_list = array( 7, 14, 21, 30, 45, 60, 75, 90 );
		}
		foreach ( $days_list as $d ) {
			$d = absint( $d );
			if ( $d > 0 ) {
				delete_transient( 'cro_insights_' . $d . 'd' );
			}
		}
	}

	/**
	 * Bump attribution cache epoch and clear insights cache.
	 */
	public static function invalidate_attribution_cache(): void {
		update_option( 'cro_attribution_cache_epoch', (int) get_option( 'cro_attribution_cache_epoch', 0 ) + 1 );
		self::bust_insights_cache();
	}

	/**
	 * @param string $cache_key_suffix Cache key.
	 * @return string
	 */
	private static function get_attribution_cache_key( $cache_key_suffix ) {
		$epoch = (int) get_option( 'cro_attribution_cache_epoch', 0 );
		return 'cro_attribution_' . $cache_key_suffix . '_v' . $epoch;
	}

	/**
	 * Attribution snapshot (unchanged contract for admin).
	 *
	 * @return array
	 */
	public static function get_attribution() {
		$window_days = (int) apply_filters( 'cro_tracking_attribution_window_days', 7 );
		$window_days = $window_days >= 1 && $window_days <= 90 ? $window_days : 7;

		$cache_key = self::get_attribution_cache_key( (string) $window_days );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && array_key_exists( 'window_days', $cached ) ) {
			return $cached;
		}

		try {
			$result = self::get_attribution_uncached( $window_days );
			$ttl    = (int) apply_filters( 'cro_insights_cache_ttl', 600 );
			$ttl    = $ttl >= 60 ? $ttl : 600;
			set_transient( $cache_key, $result, $ttl );
			return $result;
		} catch ( \Throwable $e ) {
			return self::attribution_empty_response( $window_days, true );
		}
	}

	/**
	 * @param int  $window_days Window days.
	 * @param bool $not_enough_data Flag.
	 * @return array
	 */
	private static function attribution_empty_response( $window_days, $not_enough_data = false ) {
		$out = array(
			'window_days'       => $window_days,
			'total_conversions' => 0,
			'total_impressions' => 0,
			'top_campaigns'     => array(),
			'top_offers'        => array(),
			'top_ab_tests'      => array(),
		);
		if ( $not_enough_data ) {
			$out['not_enough_data'] = true;
		}
		return $out;
	}

	/**
	 * @param int $window_days Days.
	 * @return array
	 * @throws \Throwable On DB error.
	 */
	private static function get_attribution_uncached( $window_days ) {
		$epoch = (int) get_option( 'cro_attribution_cache_epoch', 0 );
		$ck    = CRO_Database::object_cache_get( 'insights_attr_uncached', array( (int) $window_days, $epoch ) );
		if ( false !== $ck && is_array( $ck ) ) {
			return $ck;
		}
		$date_to   = wp_date( 'Y-m-d' );
		$date_from = wp_date( 'Y-m-d', strtotime( "-{$window_days} days" ) );

		global $wpdb;
		$events_table = $wpdb->prefix . 'cro_events';
		if ( ! CRO_Database::table_exists( $events_table ) ) {
			$empty = self::attribution_empty_response( $window_days, true );
			CRO_Database::object_cache_set( 'insights_attr_uncached', array( (int) $window_days, $epoch ), $empty );
			return $empty;
		}

		$cache_key_attr_conv = 'meyvora_cro_' . md5( serialize( array( 'attr_total_conversions', $events_table, $date_from, $date_to ) ) );
		$total_conversions     = wp_cache_get( $cache_key_attr_conv, 'meyvora_cro' );
		if ( false === $total_conversions ) {
			$total_conversions = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = 'conversion'",
					$events_table,
					$date_from,
					$date_to
				)
			);
			wp_cache_set( $cache_key_attr_conv, $total_conversions, 'meyvora_cro', 300 );
		} else {
			$total_conversions = (int) $total_conversions;
		}
		$cache_key_attr_imp = 'meyvora_cro_' . md5( serialize( array( 'attr_total_impressions', $events_table, $date_from, $date_to ) ) );
		$total_impressions  = wp_cache_get( $cache_key_attr_imp, 'meyvora_cro' );
		if ( false === $total_impressions ) {
			$total_impressions = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = 'impression'",
					$events_table,
					$date_from,
					$date_to
				)
			);
			wp_cache_set( $cache_key_attr_imp, $total_impressions, 'meyvora_cro', 300 );
		} else {
			$total_impressions = (int) $total_impressions;
		}
		if ( $wpdb->last_error ) {
			$db_err = (string) $wpdb->last_error;
			throw new \RuntimeException( esc_html( wp_strip_all_tags( $db_err ) ) );
		}

		$top_campaigns = array();
		$analytics     = new CRO_Analytics();
		$campaign_rows = $analytics->get_campaign_performance( $date_from, $date_to, 3 );
		foreach ( is_array( $campaign_rows ) ? $campaign_rows : array() as $row ) {
			$conv = (int) ( $row['conversions'] ?? 0 );
			if ( $conv > 0 ) {
				$top_campaigns[] = array(
					'name'        => $row['name'] ?? __( 'Unknown', 'meyvora-convert' ),
					'conversions' => $conv,
					'url'         => admin_url( 'admin.php?page=cro-campaigns' ),
				);
			}
		}

		$logs   = $wpdb->prefix . 'cro_offer_logs';
		$offers = $wpdb->prefix . 'cro_offers';
		$top_offers = array();
		if ( CRO_Database::table_exists( $logs ) && CRO_Database::table_exists( $offers ) ) {
			$cache_key_attr_offer_col = 'meyvora_cro_' . md5( serialize( array( 'offer_logs_action_column_attr', $logs ) ) );
			$has_action_raw_attr      = wp_cache_get( $cache_key_attr_offer_col, 'meyvora_cro' );
			if ( false === $has_action_raw_attr ) {
				$has_action_raw_attr = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
					$wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $logs, 'action' )
				);
				wp_cache_set( $cache_key_attr_offer_col, $has_action_raw_attr ? 1 : 0, 'meyvora_cro', 300 );
			}
			$has_action = (bool) (int) $has_action_raw_attr;
			$top_offers = self::get_top_offers_attribution( $events_table, $logs, $offers, $date_from, $date_to, $has_action );
		}

		$top_ab_tests   = array();
		$ab_tests_table = $wpdb->prefix . 'cro_ab_tests';
		$ab_var_table   = $wpdb->prefix . 'cro_ab_variations';
		if ( CRO_Database::table_exists( $ab_tests_table ) && CRO_Database::table_exists( $ab_var_table ) ) {
			$cache_key_top_ab = 'meyvora_cro_' . md5( serialize( array( 'attr_top_ab_tests', $ab_tests_table, $ab_var_table ) ) );
			$rows             = wp_cache_get( $cache_key_top_ab, 'meyvora_cro' );
			if ( false === $rows ) {
				$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
					$wpdb->prepare(
						'SELECT t.id, t.name, COALESCE(SUM(v.conversions), 0) AS conversions
				FROM %i t
				LEFT JOIN %i v ON v.test_id = t.id
				GROUP BY t.id ORDER BY conversions DESC LIMIT 3',
						$ab_tests_table,
						$ab_var_table
					),
					ARRAY_A
				);
				if ( ! is_array( $rows ) ) {
					$rows = array();
				}
				wp_cache_set( $cache_key_top_ab, $rows, 'meyvora_cro', 300 );
			} else {
				$rows = is_array( $rows ) ? $rows : array();
			}
			if ( $wpdb->last_error ) {
				$db_err = (string) $wpdb->last_error;
				throw new \RuntimeException( esc_html( wp_strip_all_tags( $db_err ) ) );
			}
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$conv = (int) ( $row['conversions'] ?? 0 );
				if ( $conv > 0 ) {
					$top_ab_tests[] = array(
						'name'        => $row['name'] ?? __( 'Unknown', 'meyvora-convert' ),
						'conversions' => $conv,
						'url'         => admin_url( 'admin.php?page=cro-ab-test-view&id=' . (int) $row['id'] ),
					);
				}
			}
		}

		$attr_out = array(
			'window_days'       => $window_days,
			'total_conversions' => $total_conversions,
			'total_impressions' => $total_impressions,
			'top_campaigns'     => $top_campaigns,
			'top_offers'        => $top_offers,
			'top_ab_tests'      => $top_ab_tests,
		);
		CRO_Database::object_cache_set( 'insights_attr_uncached', array( (int) $window_days, $epoch ), $attr_out );
		return $attr_out;
	}

	/**
	 * @param string $events_table Events table.
	 * @param string $logs         Logs table.
	 * @param string $offers       Offers table.
	 * @param string $date_from    From date.
	 * @param string $date_to      To date.
	 * @param bool $require_applied_action When true, only count logs with action = applied.
	 * @return array
	 */
	private static function get_top_offers_attribution( $events_table, $logs, $offers, $date_from, $date_to, $require_applied_action = false ) {
		$top_key = array( $events_table, $logs, $offers, $date_from, $date_to, $require_applied_action ? 1 : 0 );
		$cached  = CRO_Database::object_cache_get( 'insights_top_offers_attr', $top_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		$list = array();

		$cache_key_ctype = 'meyvora_cro_' . md5( serialize( array( 'events_source_type_column_type', DB_NAME, $events_table, 'source_type' ) ) );
		$ctype_raw         = wp_cache_get( $cache_key_ctype, 'meyvora_cro' );
		if ( false === $ctype_raw ) {
			$col = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					'SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
					DB_NAME,
					$events_table,
					'source_type'
				)
			);
			$ctype = ( $col && is_string( $col->COLUMN_TYPE ) ) ? $col->COLUMN_TYPE : '';
			wp_cache_set( $cache_key_ctype, $ctype, 'meyvora_cro', 300 );
		} else {
			$ctype = (string) $ctype_raw;
		}
		$has_offer_type = $ctype !== '' && strpos( $ctype, 'offer' ) !== false;

		if ( $has_offer_type ) {
			$cache_key_top_ev = 'meyvora_cro_' . md5( serialize( array( 'top_offers_events_by_offer', $events_table, $date_from, $date_to ) ) );
			$rows             = wp_cache_get( $cache_key_top_ev, 'meyvora_cro' );
			if ( false === $rows ) {
				$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
					$wpdb->prepare(
						"SELECT e.source_id AS offer_id, COUNT(*) AS conversions
					FROM %i e
					WHERE e.created_at >= %s AND e.created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND e.source_type = 'offer' AND e.event_type = 'conversion'
					GROUP BY e.source_id ORDER BY conversions DESC LIMIT 3",
						$events_table,
						$date_from,
						$date_to
					),
					ARRAY_A
				);
				if ( ! is_array( $rows ) ) {
					$rows = array();
				}
				wp_cache_set( $cache_key_top_ev, $rows, 'meyvora_cro', 300 );
			} else {
				$rows = is_array( $rows ) ? $rows : array();
			}
		} else {
			$rows = array();
		}

		if ( $wpdb->last_error ) {
			$db_err = (string) $wpdb->last_error;
			throw new \RuntimeException( esc_html( wp_strip_all_tags( $db_err ) ) );
		}

		foreach ( is_array( $rows ) ? $rows : array() as $r ) {
			$offer_id    = (int) ( $r['offer_id'] ?? 0 );
			$conversions = (int) ( $r['conversions'] ?? 0 );
			$cache_key_nm_attr = 'meyvora_cro_' . md5( serialize( array( 'offer_name_row_attr', $offers, $offer_id ) ) );
			$name_cache        = wp_cache_get( $cache_key_nm_attr, 'meyvora_cro' );
			if ( false === $name_cache ) {
				$name_row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
					$wpdb->prepare( 'SELECT name FROM %i WHERE id = %d', $offers, $offer_id ), ARRAY_A
				);
				$stored_nm = null === $name_row ? array( '__cro_null_row__' => true ) : $name_row;
				wp_cache_set( $cache_key_nm_attr, $stored_nm, 'meyvora_cro', 300 );
			} elseif ( is_array( $name_cache ) && isset( $name_cache['__cro_null_row__'] ) ) {
				$name_row = null;
			} else {
				$name_row = is_array( $name_cache ) ? $name_cache : null;
			}
			$name_row = is_array( $name_row ) ? $name_row : array();
			if ( $require_applied_action ) {
				$cache_key_ap_applied = 'meyvora_cro_' . md5( serialize( array( 'offer_applies_applied', $logs, $offer_id, $date_from, $date_to ) ) );
				$applies              = wp_cache_get( $cache_key_ap_applied, 'meyvora_cro' );
				if ( false === $applies ) {
					$applies = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
						$wpdb->prepare(
							"SELECT COUNT(*) FROM %i l WHERE l.offer_id = %d AND l.created_at >= %s AND l.created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND l.action = %s",
							$logs,
							$offer_id,
							$date_from,
							$date_to,
							'applied'
						)
					);
					wp_cache_set( $cache_key_ap_applied, $applies, 'meyvora_cro', 300 );
				} else {
					$applies = (int) $applies;
				}
			} else {
				$cache_key_ap_any = 'meyvora_cro_' . md5( serialize( array( 'offer_applies_any', $logs, $offer_id, $date_from, $date_to ) ) );
				$applies            = wp_cache_get( $cache_key_ap_any, 'meyvora_cro' );
				if ( false === $applies ) {
					$applies = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
						$wpdb->prepare(
							'SELECT COUNT(*) FROM %i l WHERE l.offer_id = %d AND l.created_at >= %s AND l.created_at < DATE_ADD(%s, INTERVAL 1 DAY)',
							$logs,
							$offer_id,
							$date_from,
							$date_to
						)
					);
					wp_cache_set( $cache_key_ap_any, $applies, 'meyvora_cro', 300 );
				} else {
					$applies = (int) $applies;
				}
			}
			$rate = null;
			if ( $applies > 0 ) {
				$rate = round( ( $conversions / $applies ) * 100 );
			}
			$list[] = array(
				'name'        => $name_row['name'] ?? __( 'Unknown', 'meyvora-convert' ),
				'conversions' => $conversions,
				'applies'     => $applies,
				'rate'        => $rate,
				'url'         => admin_url( 'admin.php?page=cro-offers' ),
			);
		}

		if ( empty( $list ) ) {
			if ( $require_applied_action ) {
				$cache_key_fb_applied = 'meyvora_cro_' . md5( serialize( array( 'top_offers_fallback_applied', $logs, $offers, $date_from, $date_to ) ) );
				$rows                 = wp_cache_get( $cache_key_fb_applied, 'meyvora_cro' );
				if ( false === $rows ) {
					$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
						$wpdb->prepare(
							"SELECT o.id AS offer_id, o.name, COUNT(l.id) AS applies FROM %i l
					INNER JOIN %i o ON o.id = l.offer_id
					WHERE l.created_at >= %s AND l.created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND l.action = %s
					GROUP BY l.offer_id ORDER BY applies DESC LIMIT 3",
							$logs,
							$offers,
							$date_from,
							$date_to,
							'applied'
						),
						ARRAY_A
					);
					if ( ! is_array( $rows ) ) {
						$rows = array();
					}
					wp_cache_set( $cache_key_fb_applied, $rows, 'meyvora_cro', 300 );
				} else {
					$rows = is_array( $rows ) ? $rows : array();
				}
			} else {
				$cache_key_fb_any = 'meyvora_cro_' . md5( serialize( array( 'top_offers_fallback_any', $logs, $offers, $date_from, $date_to ) ) );
				$rows             = wp_cache_get( $cache_key_fb_any, 'meyvora_cro' );
				if ( false === $rows ) {
					$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
						$wpdb->prepare(
							'SELECT o.id AS offer_id, o.name, COUNT(l.id) AS applies FROM %i l
					INNER JOIN %i o ON o.id = l.offer_id
					WHERE l.created_at >= %s AND l.created_at < DATE_ADD(%s, INTERVAL 1 DAY)
					GROUP BY l.offer_id ORDER BY applies DESC LIMIT 3',
							$logs,
							$offers,
							$date_from,
							$date_to
						),
						ARRAY_A
					);
					if ( ! is_array( $rows ) ) {
						$rows = array();
					}
					wp_cache_set( $cache_key_fb_any, $rows, 'meyvora_cro', 300 );
				} else {
					$rows = is_array( $rows ) ? $rows : array();
				}
			}
			if ( $wpdb->last_error ) {
				$db_err = (string) $wpdb->last_error;
				throw new \RuntimeException( esc_html( wp_strip_all_tags( $db_err ) ) );
			}
			foreach ( is_array( $rows ) ? $rows : array() as $r ) {
				$list[] = array(
					'name'        => $r['name'] ?? __( 'Unknown', 'meyvora-convert' ),
					'conversions' => 0,
					'applies'     => (int) ( $r['applies'] ?? 0 ),
					'rate'        => null,
					'url'         => admin_url( 'admin.php?page=cro-offers' ),
				);
			}
		}

		CRO_Database::object_cache_set( 'insights_top_offers_attr', $top_key, $list );
		return $list;
	}

	// ——— Insight builders ———

	/**
	 * @param array<string, mixed> $extra Extra keys.
	 * @return array<string, mixed>
	 */
	private static function make_card( string $id, string $type, string $category, int $priority, string $title, string $description, string $fix_url, string $fix_label, string $metric = '', string $metric_label = '', array $extra = array() ): array {
		return array_merge(
			array(
				'id'           => $id,
				'type'         => $type,
				'category'     => $category,
				'priority'     => max( 1, min( 3, $priority ) ),
				'title'        => $title,
				'description'  => $description,
				'fix_url'      => $fix_url,
				'fix_label'    => $fix_label,
				'metric'       => $metric,
				'metric_label' => $metric_label,
			),
			$extra
		);
	}

	private static function insight_top_campaign( int $days ): ?array {
		$rows = self::get_campaign_comparison( $days );
		if ( empty( $rows ) ) {
			return null;
		}
		$top = $rows[0];
		if ( (int) $top['impressions'] < 10 ) {
			return null;
		}
		return self::make_card(
			'top-campaign',
			'top',
			'campaign',
			2,
			__( 'Best converting campaign', 'meyvora-convert' ),
			sprintf(
				/* translators: %1$s: campaign name, %2$s: conversion rate */
				__( '%1$s leads with a %2$s conversion rate in this period. Double down on what works.', 'meyvora-convert' ),
				$top['name'],
				number_format_i18n( $top['conversion_rate'], 2 ) . '%'
			),
			admin_url( 'admin.php?page=cro-campaign-edit&campaign_id=' . (int) $top['id'] ),
			__( 'Edit campaign', 'meyvora-convert' ),
			(string) $top['conversion_rate'] . '%',
			__( 'Conv. rate', 'meyvora-convert' )
		);
	}

	private static function insight_underperforming( int $days ): ?array {
		$date_to   = wp_date( 'Y-m-d' );
		$date_from = wp_date( 'Y-m-d', strtotime( '-' . self::normalize_days( $days ) . ' days' ) );
		$analytics = new CRO_Analytics();
		$rows      = $analytics->get_campaign_performance( $date_from, $date_to, 50 );
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$imp  = (int) ( $row['impressions'] ?? 0 );
			$conv = (int) ( $row['conversions'] ?? 0 );
			$rate = $imp > 0 ? ( $conv / $imp ) * 100 : 0;
			if ( $imp >= 50 && $conv < 2 && $rate < 3 ) {
				return self::make_card(
					'underperforming-campaign',
					'underperforming',
					'campaign',
					1,
					__( 'Campaign is underperforming', 'meyvora-convert' ),
					sprintf(
						/* translators: 1: name, 2: impressions, 3: conversions */
						__( '%1$s has %2$s impressions but only %3$s conversions (under 3%%). Refresh copy, offer, or triggers.', 'meyvora-convert' ),
						sanitize_text_field( (string) ( $row['name'] ?? '' ) ),
						number_format_i18n( $imp ),
						number_format_i18n( $conv )
					),
					admin_url( 'admin.php?page=cro-campaign-edit&campaign_id=' . (int) ( $row['id'] ?? 0 ) ),
					__( 'Improve campaign', 'meyvora-convert' ),
					number_format_i18n( $rate, 2 ) . '%',
					__( 'Conv. rate', 'meyvora-convert' )
				);
			}
		}
		return null;
	}

	private static function insight_best_time( int $days ): ?array {
		$hm = self::get_hourly_heatmap( $days );
		$max = 0;
		$bd = $bh = 0;
		for ( $d = 0; $d < 7; $d++ ) {
			for ( $h = 0; $h < 24; $h++ ) {
				if ( $hm[ $d ][ $h ] > $max ) {
					$max = $hm[ $d ][ $h ];
					$bd  = $d;
					$bh  = $h;
				}
			}
		}
		if ( $max < 1 ) {
			return null;
		}
		$days_en = array( __( 'Monday', 'meyvora-convert' ), __( 'Tuesday', 'meyvora-convert' ), __( 'Wednesday', 'meyvora-convert' ), __( 'Thursday', 'meyvora-convert' ), __( 'Friday', 'meyvora-convert' ), __( 'Saturday', 'meyvora-convert' ), __( 'Sunday', 'meyvora-convert' ) );
		return self::make_card(
			'best-time',
			'opportunity',
			'general',
			2,
			__( 'Peak conversion window', 'meyvora-convert' ),
			sprintf(
				/* translators: 1: weekday, 2: hour */
				__( 'Most conversions happen on %1$s around %2$s:00. Schedule campaigns or boosts for that window.', 'meyvora-convert' ),
				$days_en[ $bd ],
				str_pad( (string) $bh, 2, '0', STR_PAD_LEFT )
			),
			admin_url( 'admin.php?page=cro-campaigns' ),
			__( 'Campaigns', 'meyvora-convert' ),
			(string) (int) $max,
			__( 'Conversions (cell)', 'meyvora-convert' )
		);
	}

	private static function insight_worst_time( int $days ): ?array {
		$hm = self::get_hourly_heatmap( $days );
		$min = PHP_INT_MAX;
		$wd = $wh = -1;
		$found = false;
		for ( $d = 0; $d < 7; $d++ ) {
			for ( $h = 0; $h < 24; $h++ ) {
				$c = $hm[ $d ][ $h ];
				if ( $c > 0 && $c < $min ) {
					$min   = $c;
					$wd    = $d;
					$wh    = $h;
					$found = true;
				}
			}
		}
		if ( ! $found ) {
			return null;
		}
		$days_en = array( __( 'Monday', 'meyvora-convert' ), __( 'Tuesday', 'meyvora-convert' ), __( 'Wednesday', 'meyvora-convert' ), __( 'Thursday', 'meyvora-convert' ), __( 'Friday', 'meyvora-convert' ), __( 'Saturday', 'meyvora-convert' ), __( 'Sunday', 'meyvora-convert' ) );
		return self::make_card(
			'worst-time',
			'warning',
			'general',
			2,
			__( 'Quiet conversion slot', 'meyvora-convert' ),
			sprintf(
				/* translators: 1: weekday, 2: hour */
				__( '%1$s around %2$s:00 sees the fewest conversions. Consider pausing aggressive triggers then or testing different messaging.', 'meyvora-convert' ),
				$days_en[ $wd ],
				str_pad( (string) $wh, 2, '0', STR_PAD_LEFT )
			),
			admin_url( 'admin.php?page=cro-campaigns' ),
			__( 'Adjust triggers', 'meyvora-convert' ),
			(string) (int) $min,
			__( 'Conversions (cell)', 'meyvora-convert' )
		);
	}

	private static function insight_offer_no_conversion( int $days ): ?array {
		foreach ( self::get_offer_performance( $days ) as $o ) {
			if ( (int) $o['applies'] >= 10 && (int) $o['orders_with_coupon'] < 1 ) {
				return self::make_card(
					'offer-no-order',
					'warning',
					'offer',
					1,
					__( 'Offer applies but few orders', 'meyvora-convert' ),
					sprintf(
						/* translators: %s: offer name */
						__( '%s is applied often but rarely completes to an order. Review minimum spend, exclusions, and coupon stacking.', 'meyvora-convert' ),
						$o['name']
					),
					admin_url( 'admin.php?page=cro-offers' ),
					__( 'Review offers', 'meyvora-convert' ),
					(string) (int) $o['applies'],
					__( 'Applies', 'meyvora-convert' )
				);
			}
		}
		return null;
	}

	private static function insight_high_value_abandoned( int $days ): ?array {
		$st = self::get_abandoned_cart_stats( $days );
		$hi = (int) ( $st['by_value_band']['200+'] ?? 0 );
		if ( $hi < 3 ) {
			return null;
		}
		$avg = (float) ( $st['avg_value'] ?? 0 );
		return self::make_card(
			'high-value-abandoned',
			'opportunity',
			'abandoned_cart',
			1,
			__( 'High-value carts slipping away', 'meyvora-convert' ),
			sprintf(
				/* translators: 1: count high-value carts, 2: average cart value */
				__( 'You have %1$s abandoned carts over $200 (avg cart ~%2$s). Prioritize recovery rules and incentives for this band.', 'meyvora-convert' ),
				number_format_i18n( $hi ),
				function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $avg ) ) : (string) $avg
			),
			admin_url( 'admin.php?page=cro-abandoned-cart' ),
			__( 'Email settings', 'meyvora-convert' ),
			(string) $hi,
			__( '$200+ carts', 'meyvora-convert' )
		);
	}

	private static function insight_ab_test_ready( int $days ): ?array {
		unset( $days );
		global $wpdb;
		$campaigns_table = $wpdb->prefix . 'cro_campaigns';
		$ab_table        = $wpdb->prefix . 'cro_ab_tests';
		if ( ! CRO_Database::table_exists( $campaigns_table ) || ! CRO_Database::table_exists( $ab_table ) ) {
			return null;
		}
		$cache_key_ab_ready = 'meyvora_cro_' . md5( serialize( array( 'ab_test_ready_campaign', $campaigns_table, $ab_table ) ) );
		$row_cache           = wp_cache_get( $cache_key_ab_ready, 'meyvora_cro' );
		if ( false === $row_cache ) {
			$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					"SELECT c.id, c.name FROM %i c
			WHERE c.created_at <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)
			AND NOT EXISTS (
				SELECT 1 FROM %i ab WHERE ab.original_campaign_id = c.id AND ab.status = 'running'
			)
			LIMIT 1",
					$campaigns_table,
					$ab_table
				),
				ARRAY_A
			);
			$stored_ab = null === $row ? array( '__cro_null_row__' => true ) : $row;
			wp_cache_set( $cache_key_ab_ready, $stored_ab, 'meyvora_cro', 300 );
		} elseif ( is_array( $row_cache ) && isset( $row_cache['__cro_null_row__'] ) ) {
			$row = null;
		} else {
			$row = is_array( $row_cache ) ? $row_cache : null;
		}
		if ( ! $row ) {
			return null;
		}
		return self::make_card(
			'ab-test-ready',
			'action',
			'ab_test',
			2,
			__( 'Ready for an A/B test', 'meyvora-convert' ),
			sprintf(
				/* translators: %s: campaign name */
				__( '%s has been live for 30+ days without a running A/B test. Validate headline or offer changes with split traffic.', 'meyvora-convert' ),
				sanitize_text_field( (string) ( $row['name'] ?? '' ) )
			),
			admin_url( 'admin.php?page=cro-ab-test-new&campaign_id=' . (int) $row['id'] ),
			__( 'Create A/B test', 'meyvora-convert' ),
			'30+',
			__( 'Days live', 'meyvora-convert' )
		);
	}

	private static function insight_booster_no_lift( int $days ): ?array {
		$date_to   = wp_date( 'Y-m-d' );
		$date_from = wp_date( 'Y-m-d', strtotime( '-' . self::normalize_days( $days ) . ' days' ) );
		global $wpdb;
		$ev = $wpdb->prefix . 'cro_events';
		if ( ! CRO_Database::table_exists( $ev ) ) {
			return null;
		}
		$cache_key_booster_imp = 'meyvora_cro_' . md5( serialize( array( 'booster_shipping_impressions', $ev, $date_from, $date_to ) ) );
		$imp                   = wp_cache_get( $cache_key_booster_imp, 'meyvora_cro' );
		if ( false === $imp ) {
			$imp = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND source_type = 'shipping_bar' AND event_type = 'impression'",
					$ev,
					$date_from,
					$date_to
				)
			);
			wp_cache_set( $cache_key_booster_imp, $imp, 'meyvora_cro', 300 );
		} else {
			$imp = (int) $imp;
		}
		$cache_key_booster_int = 'meyvora_cro_' . md5( serialize( array( 'booster_shipping_interactions', $ev, $date_from, $date_to ) ) );
		$int                   = wp_cache_get( $cache_key_booster_int, 'meyvora_cro' );
		if ( false === $int ) {
			$int = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND source_type = 'shipping_bar' AND event_type = 'interaction'",
					$ev,
					$date_from,
					$date_to
				)
			);
			wp_cache_set( $cache_key_booster_int, $int, 'meyvora_cro', 300 );
		} else {
			$int = (int) $int;
		}
		if ( $imp < 30 ) {
			return null;
		}
		$rate = $imp > 0 ? ( $int / $imp ) * 100 : 0;
		if ( $rate >= 10 ) {
			return null;
		}
		return self::make_card(
			'booster-no-lift',
			'warning',
			'booster',
			2,
			__( 'Shipping bar engagement is low', 'meyvora-convert' ),
			sprintf(
				/* translators: 1: impressions, 2: interaction rate */
				__( 'The shipping bar had %1$s impressions but only a %2$s interaction rate. Lower the threshold or clarify the message.', 'meyvora-convert' ),
				number_format_i18n( $imp ),
				number_format_i18n( $rate, 1 ) . '%'
			),
			admin_url( 'admin.php?page=cro-boosters' ),
			__( 'Boosters', 'meyvora-convert' ),
			number_format_i18n( $rate, 1 ) . '%',
			__( 'Interaction rate', 'meyvora-convert' )
		);
	}

	private static function insight_checkout_drop( int $days ): ?array {
		$date_to   = wp_date( 'Y-m-d' );
		$date_from = wp_date( 'Y-m-d', strtotime( '-' . self::normalize_days( $days ) . ' days' ) );
		global $wpdb;
		$ev = $wpdb->prefix . 'cro_events';
		if ( ! CRO_Database::table_exists( $ev ) ) {
			return null;
		}
		$cache_key_chk_imp = 'meyvora_cro_' . md5( serialize( array( 'checkout_impressions', $ev, $date_from, $date_to ) ) );
		$imp               = wp_cache_get( $cache_key_chk_imp, 'meyvora_cro' );
		if ( false === $imp ) {
			$imp = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = 'impression' AND (page_type = %s OR page_url LIKE %s)",
					$ev,
					$date_from,
					$date_to,
					'checkout',
					'%checkout%'
				)
			);
			wp_cache_set( $cache_key_chk_imp, $imp, 'meyvora_cro', 300 );
		} else {
			$imp = (int) $imp;
		}
		$cache_key_chk_conv = 'meyvora_cro_' . md5( serialize( array( 'checkout_conversions', $ev, $date_from, $date_to ) ) );
		$conv               = wp_cache_get( $cache_key_chk_conv, 'meyvora_cro' );
		if ( false === $conv ) {
			$conv = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = 'conversion' AND (page_type = %s OR page_url LIKE %s)",
					$ev,
					$date_from,
					$date_to,
					'checkout',
					'%checkout%'
				)
			);
			wp_cache_set( $cache_key_chk_conv, $conv, 'meyvora_cro', 300 );
		} else {
			$conv = (int) $conv;
		}
		if ( $imp < 50 ) {
			return null;
		}
		$rate = $imp > 0 ? ( $conv / $imp ) * 100 : 0;
		if ( $rate >= 5 ) {
			return null;
		}
		return self::make_card(
			'checkout-drop',
			'warning',
			'checkout',
			1,
			__( 'Checkout engagement gap', 'meyvora-convert' ),
			sprintf(
				/* translators: 1: impressions, 2: conversion rate */
				__( 'Checkout-tagged impressions hit %1$s but conversion rate is only %2$s. Review trust badges, shipping clarity, and distractions.', 'meyvora-convert' ),
				number_format_i18n( $imp ),
				number_format_i18n( $rate, 2 ) . '%'
			),
			admin_url( 'admin.php?page=cro-checkout' ),
			__( 'Checkout optimizer', 'meyvora-convert' ),
			number_format_i18n( $rate, 2 ) . '%',
			__( 'Checkout conv.', 'meyvora-convert' )
		);
	}

	private static function insight_email_growth( int $days ): ?array {
		$cmp = self::get_period_comparison( $days );
		if ( ! isset( $cmp['emails'] ) ) {
			return null;
		}
		$ch = $cmp['emails']['change_pct'];
		if ( $ch === null || $ch < 15 ) {
			return null;
		}
		return self::make_card(
			'email-growth',
			'top',
			'general',
			3,
			__( 'Email capture is accelerating', 'meyvora-convert' ),
			sprintf(
				/* translators: %s: percent change */
				__( 'Emails captured from conversions are up %s%% vs the prior period. Keep forms short and incentives clear.', 'meyvora-convert' ),
				number_format_i18n( $ch, 1 )
			),
			admin_url( 'admin.php?page=cro-analytics' ),
			__( 'View analytics', 'meyvora-convert' ),
			( $ch >= 0 ? '+' : '' ) . number_format_i18n( $ch, 1 ) . '%',
			__( 'Change', 'meyvora-convert' )
		);
	}

	private static function insight_device_gap( int $days ): ?array {
		$date_to   = wp_date( 'Y-m-d' );
		$date_from = wp_date( 'Y-m-d', strtotime( '-' . self::normalize_days( $days ) . ' days' ) );
		global $wpdb;
		$ev = $wpdb->prefix . 'cro_events';
		if ( ! CRO_Database::table_exists( $ev ) ) {
			return null;
		}
		$cache_key_device_gap = 'meyvora_cro_' . md5( serialize( array( 'device_gap_by_device', $ev, $date_from, $date_to ) ) );
		$rows                 = wp_cache_get( $cache_key_device_gap, 'meyvora_cro' );
		if ( false === $rows ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above with wp_cache_get/set.
				$wpdb->prepare(
					"SELECT device_type,
					SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) AS imp,
					SUM(CASE WHEN event_type = 'conversion' THEN 1 ELSE 0 END) AS cv
				FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND device_type IN ('mobile','desktop')
				GROUP BY device_type",
					$ev,
					$date_from,
					$date_to
				),
				ARRAY_A
			);
			if ( ! is_array( $rows ) ) {
				$rows = array();
			}
			wp_cache_set( $cache_key_device_gap, $rows, 'meyvora_cro', 300 );
		} else {
			$rows = is_array( $rows ) ? $rows : array();
		}
		$m_rate = $d_rate = null;
		foreach ( is_array( $rows ) ? $rows : array() as $r ) {
			$imp = (int) ( $r['imp'] ?? 0 );
			$cv  = (int) ( $r['cv'] ?? 0 );
			$rate = $imp > 0 ? ( $cv / $imp ) * 100 : 0.0;
			if ( ( $r['device_type'] ?? '' ) === 'mobile' ) {
				$m_rate = $rate;
			}
			if ( ( $r['device_type'] ?? '' ) === 'desktop' ) {
				$d_rate = $rate;
			}
		}
		if ( $m_rate === null || $d_rate === null || $d_rate < 0.001 ) {
			return null;
		}
		if ( $m_rate <= 0 || ( $d_rate / max( $m_rate, 0.0001 ) ) < 2 ) {
			return null;
		}
		return self::make_card(
			'device-gap',
			'warning',
			'general',
			2,
			__( 'Mobile converts much slower than desktop', 'meyvora-convert' ),
			sprintf(
				/* translators: 1: mobile rate, 2: desktop rate */
				__( 'Mobile conversion rate is %1$s vs %2$s on desktop. Simplify mobile popups and test larger tap targets.', 'meyvora-convert' ),
				number_format_i18n( $m_rate, 2 ) . '%',
				number_format_i18n( $d_rate, 2 ) . '%'
			),
			admin_url( 'admin.php?page=cro-campaigns' ),
			__( 'Campaigns', 'meyvora-convert' ),
			number_format_i18n( $d_rate / max( $m_rate, 0.0001 ), 1 ) . '×',
			__( 'Desktop vs mobile', 'meyvora-convert' )
		);
	}

	private static function insight_recovery_email_drop( int $days ): ?array {
		$st = self::get_abandoned_cart_stats( $days );
		$r1 = $st['email_1_recovery_rate'];
		$r2 = $st['email_2_recovery_rate'];
		$r3 = $st['email_3_recovery_rate'];
		$map = array();
		if ( $r1 !== null ) {
			$map[1] = $r1;
		}
		if ( $r2 !== null ) {
			$map[2] = $r2;
		}
		if ( $r3 !== null ) {
			$map[3] = $r3;
		}
		if ( count( $map ) < 2 ) {
			return null;
		}
		$min_step = array_keys( $map, min( $map ), true );
		$step     = (int) reset( $min_step );
		$min_rate = $map[ $step ];
		return self::make_card(
			'recovery-email-drop',
			'action',
			'abandoned_cart',
			2,
			__( 'Weakest recovery email', 'meyvora-convert' ),
			sprintf(
				/* translators: 1: email number 1-3, 2: recovery rate */
				__( 'Email %1$d has the lowest recovery rate (%2$s%%). Refresh subject lines, timing, or incentive in that step.', 'meyvora-convert' ),
				$step,
				number_format_i18n( $min_rate, 2 )
			),
			admin_url( 'admin.php?page=cro-abandoned-cart' ),
			__( 'Edit emails', 'meyvora-convert' ),
			number_format_i18n( $min_rate, 2 ) . '%',
			__( 'Recovery rate', 'meyvora-convert' )
		);
	}
}
