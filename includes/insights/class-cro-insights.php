<?php
/**
 * CRO Insights — rule-based recommendations and analytics slices for the Insights admin tab.
 *
 * @package Meyvora_Convert
 */
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

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
		global $wpdb;
		$t = $wpdb->prefix . 'cro_abandoned_carts';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) {
			return array( 'abandoned' => 0, 'recovered' => 0, 'rate' => 0.0 );
		}
		$abandoned = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$t} WHERE DATE(created_at) BETWEEN %s AND %s",
				$from,
				$to
			)
		);
		$recovered = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$t} WHERE recovered_at IS NOT NULL AND DATE(recovered_at) BETWEEN %s AND %s",
				$from,
				$to
			)
		);
		$rate      = $abandoned > 0 ? round( ( $recovered / $abandoned ) * 100, 2 ) : 0.0;
		return array(
			'abandoned' => $abandoned,
			'recovered' => $recovered,
			'rate'      => $rate,
		);
	}

	/**
	 * Funnel counts for campaign popups.
	 *
	 * @param int $days Window days.
	 * @return array{sessions_with_impression: int, popup_clicks: int, emails_captured: int, orders: int}
	 */
	public static function get_funnel_data( int $days = self::DAYS ): array {
		global $wpdb;
		$days      = self::normalize_days( $days );
		$date_to   = wp_date( 'Y-m-d' );
		$date_from = wp_date( 'Y-m-d', strtotime( '-' . $days . ' days' ) );
		$t         = $wpdb->prefix . 'cro_events';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) {
			return array(
				'sessions_with_impression' => 0,
				'popup_clicks'             => 0,
				'emails_captured'          => 0,
				'orders'                   => self::fallback_order_count( $date_from, $date_to ),
			);
		}
		$sessions_imp = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT session_id) FROM {$t} WHERE DATE(created_at) BETWEEN %s AND %s AND event_type = 'impression' AND source_type = 'campaign'",
				$date_from,
				$date_to
			)
		);
		$clicks       = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT session_id) FROM {$t} WHERE DATE(created_at) BETWEEN %s AND %s AND event_type = 'interaction' AND source_type = 'campaign'",
				$date_from,
				$date_to
			)
		);
		$emails       = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$t} WHERE DATE(created_at) BETWEEN %s AND %s AND event_type = 'conversion' AND source_type = 'campaign' AND email IS NOT NULL AND email != ''",
				$date_from,
				$date_to
			)
		);
		$orders       = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$t} WHERE DATE(created_at) BETWEEN %s AND %s AND event_type = 'conversion' AND source_type = 'campaign' AND order_id IS NOT NULL AND order_id > 0",
				$date_from,
				$date_to
			)
		);
		if ( $orders < 1 && function_exists( 'wc_get_orders' ) ) {
			$orders = self::fallback_order_count( $date_from, $date_to );
		}
		return array(
			'sessions_with_impression' => $sessions_imp,
			'popup_clicks'             => $clicks,
			'emails_captured'          => $emails,
			'orders'                   => $orders,
		);
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
		global $wpdb;
		$days      = self::normalize_days( $days );
		$date_to   = wp_date( 'Y-m-d' );
		$date_from = wp_date( 'Y-m-d', strtotime( '-' . $days . ' days' ) );
		$t         = $wpdb->prefix . 'cro_events';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) {
			return $matrix;
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT WEEKDAY(created_at) AS dow, HOUR(created_at) AS hr, COUNT(*) AS c
				FROM {$t}
				WHERE DATE(created_at) BETWEEN %s AND %s AND event_type = 'conversion'
				GROUP BY dow, hr",
				$date_from,
				$date_to
			),
			ARRAY_A
		);
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$dow = isset( $row['dow'] ) ? (int) $row['dow'] : -1;
			$hr  = isset( $row['hr'] ) ? (int) $row['hr'] : -1;
			$c   = isset( $row['c'] ) ? (int) $row['c'] : 0;
			if ( $dow >= 0 && $dow <= 6 && $hr >= 0 && $hr <= 23 ) {
				$matrix[ $dow ][ $hr ] = $c;
			}
		}
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
		$logs      = $wpdb->prefix . 'cro_offer_logs';
		$offers    = $wpdb->prefix . 'cro_offers';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $logs ) ) !== $logs
			|| $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $offers ) ) !== $offers ) {
			return array();
		}
		$has_action = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $logs, 'action' ) );
		if ( $has_action ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT l.offer_id, COUNT(*) AS applies,
				SUM(CASE WHEN l.order_id IS NOT NULL AND l.order_id > 0 THEN 1 ELSE 0 END) AS orders_with_coupon
				FROM %i l WHERE DATE(l.created_at) BETWEEN %s AND %s AND l.action = %s GROUP BY l.offer_id',
					$logs,
					$date_from,
					$date_to,
					'applied'
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT l.offer_id, COUNT(*) AS applies,
				SUM(CASE WHEN l.order_id IS NOT NULL AND l.order_id > 0 THEN 1 ELSE 0 END) AS orders_with_coupon
				FROM %i l WHERE DATE(l.created_at) BETWEEN %s AND %s GROUP BY l.offer_id',
					$logs,
					$date_from,
					$date_to
				),
				ARRAY_A
			);
		}

		$list = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$oid    = (int) ( $row['offer_id'] ?? 0 );
			$applies = (int) ( $row['applies'] ?? 0 );
			$ordcnt  = (int) ( $row['orders_with_coupon'] ?? 0 );
			$name    = $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM %i WHERE id = %d', $offers, $oid ) );
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
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT order_id FROM {$logs} WHERE offer_id = %d AND order_id IS NOT NULL AND order_id > 0 AND DATE(created_at) BETWEEN %s AND %s LIMIT 300",
				$offer_id,
				$date_from,
				$date_to
			)
		);
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
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) {
			return $empty;
		}

		$total_abandoned = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$t} WHERE DATE(created_at) BETWEEN %s AND %s",
				$date_from,
				$date_to
			)
		);
		$total_recovered = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$t} WHERE recovered_at IS NOT NULL AND DATE(recovered_at) BETWEEN %s AND %s",
				$date_from,
				$date_to
			)
		);
		$rate            = $total_abandoned > 0 ? round( ( $total_recovered / $total_abandoned ) * 100, 2 ) : 0.0;

		$avg = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(cart_json, '$.totals.total')) AS DECIMAL(12,2))) FROM {$t}
				WHERE DATE(created_at) BETWEEN %s AND %s AND JSON_VALID(cart_json) AND JSON_EXTRACT(cart_json, '$.totals.total') IS NOT NULL",
				$date_from,
				$date_to
			)
		);
		$avg_val = is_numeric( $avg ) ? round( (float) $avg, 2 ) : 0.0;

		$bands = array(
			'0-50'    => (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$t} WHERE DATE(created_at) BETWEEN %s AND %s AND JSON_VALID(cart_json)
					AND CAST(JSON_UNQUOTE(JSON_EXTRACT(cart_json, '$.totals.total')) AS DECIMAL(12,2)) >= 0
					AND CAST(JSON_UNQUOTE(JSON_EXTRACT(cart_json, '$.totals.total')) AS DECIMAL(12,2)) < 50",
					$date_from,
					$date_to
				)
			),
			'50-100'  => (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$t} WHERE DATE(created_at) BETWEEN %s AND %s AND JSON_VALID(cart_json)
					AND CAST(JSON_UNQUOTE(JSON_EXTRACT(cart_json, '$.totals.total')) AS DECIMAL(12,2)) >= 50
					AND CAST(JSON_UNQUOTE(JSON_EXTRACT(cart_json, '$.totals.total')) AS DECIMAL(12,2)) < 100",
					$date_from,
					$date_to
				)
			),
			'100-200' => (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$t} WHERE DATE(created_at) BETWEEN %s AND %s AND JSON_VALID(cart_json)
					AND CAST(JSON_UNQUOTE(JSON_EXTRACT(cart_json, '$.totals.total')) AS DECIMAL(12,2)) >= 100
					AND CAST(JSON_UNQUOTE(JSON_EXTRACT(cart_json, '$.totals.total')) AS DECIMAL(12,2)) < 200",
					$date_from,
					$date_to
				)
			),
			'200+'    => (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$t} WHERE DATE(created_at) BETWEEN %s AND %s AND JSON_VALID(cart_json)
					AND CAST(JSON_UNQUOTE(JSON_EXTRACT(cart_json, '$.totals.total')) AS DECIMAL(12,2)) >= 200",
					$date_from,
					$date_to
				)
			),
		);

		$e1 = self::recovery_rate_for_email_step( $t, 1, $date_from, $date_to );
		$e2 = self::recovery_rate_for_email_step( $t, 2, $date_from, $date_to );
		$e3 = self::recovery_rate_for_email_step( $t, 3, $date_from, $date_to );

		return array(
			'total_abandoned'       => $total_abandoned,
			'total_recovered'       => $total_recovered,
			'recovery_rate'         => $rate,
			'avg_value'             => $avg_val,
			'by_value_band'         => $bands,
			'email_1_recovery_rate' => $e1,
			'email_2_recovery_rate' => $e2,
			'email_3_recovery_rate' => $e3,
		);
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
		global $wpdb;
		$cols = array(
			1 => 'email_1_sent_at',
			2 => 'email_2_sent_at',
			3 => 'email_3_sent_at',
		);
		if ( ! isset( $cols[ $step ] ) ) {
			return null;
		}
		$col = $cols[ $step ];
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $col whitelisted (email_1_sent_at|2|3).
		$sent = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE DATE({$col}) BETWEEN %s AND %s AND {$col} IS NOT NULL",
				$date_from,
				$date_to
			)
		);
		if ( $sent < 1 ) {
			return null;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rec = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE DATE({$col}) BETWEEN %s AND %s AND {$col} IS NOT NULL AND recovered_at IS NOT NULL",
				$date_from,
				$date_to
			)
		);
		return round( ( $rec / $sent ) * 100, 2 );
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
		$date_to   = wp_date( 'Y-m-d' );
		$date_from = wp_date( 'Y-m-d', strtotime( "-{$window_days} days" ) );

		global $wpdb;
		$events_table = $wpdb->prefix . 'cro_events';
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $events_table ) );
		if ( $table_exists !== $events_table ) {
			return self::attribution_empty_response( $window_days, true );
		}

		$total_conversions = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$events_table} WHERE DATE(created_at) BETWEEN %s AND %s AND event_type = 'conversion'",
				$date_from,
				$date_to
			)
		);
		$total_impressions = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$events_table} WHERE DATE(created_at) BETWEEN %s AND %s AND event_type = 'impression'",
				$date_from,
				$date_to
			)
		);
		if ( $wpdb->last_error ) {
			throw new \RuntimeException( $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
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
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $logs ) ) === $logs
			&& $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $offers ) ) === $offers ) {
			$has_action   = $wpdb->get_var( "SHOW COLUMNS FROM {$logs} LIKE 'action'" );
			$action_where = $has_action ? " AND l.action = 'applied'" : '';
			$top_offers   = self::get_top_offers_attribution( $events_table, $logs, $offers, $date_from, $date_to, $action_where );
		}

		$top_ab_tests = array();
		$ab_tests_table = esc_sql( $wpdb->prefix . 'cro_ab_tests' );
		$ab_var_table   = esc_sql( $wpdb->prefix . 'cro_ab_variations' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ab_tests_table ) ) === $ab_tests_table
			&& $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ab_var_table ) ) === $ab_var_table ) {
			$rows = $wpdb->get_results(
				"SELECT t.id, t.name, COALESCE(SUM(v.conversions), 0) AS conversions
				FROM {$ab_tests_table} t
				LEFT JOIN {$ab_var_table} v ON v.test_id = t.id
				GROUP BY t.id ORDER BY conversions DESC LIMIT 3",
				ARRAY_A
			);
			if ( $wpdb->last_error ) {
				throw new \RuntimeException( $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
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

		return array(
			'window_days'       => $window_days,
			'total_conversions' => $total_conversions,
			'total_impressions' => $total_impressions,
			'top_campaigns'     => $top_campaigns,
			'top_offers'        => $top_offers,
			'top_ab_tests'      => $top_ab_tests,
		);
	}

	/**
	 * @param string $events_table Events table.
	 * @param string $logs         Logs table.
	 * @param string $offers       Offers table.
	 * @param string $date_from    From date.
	 * @param string $date_to      To date.
	 * @param string $action_where Fragment.
	 * @return array
	 */
	private static function get_top_offers_attribution( $events_table, $logs, $offers, $date_from, $date_to, $action_where = '' ) {
		global $wpdb;
		$list = array();

		$col = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
				DB_NAME,
				$events_table,
				'source_type'
			)
		);
		$has_offer_type = $col && is_string( $col->COLUMN_TYPE ) && strpos( $col->COLUMN_TYPE, 'offer' ) !== false;

		if ( $has_offer_type ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT e.source_id AS offer_id, COUNT(*) AS conversions
					FROM {$events_table} e
					WHERE DATE(e.created_at) BETWEEN %s AND %s AND e.source_type = 'offer' AND e.event_type = 'conversion'
					GROUP BY e.source_id ORDER BY conversions DESC LIMIT 3",
					$date_from,
					$date_to
				),
				ARRAY_A
			);
		} else {
			$rows = array();
		}

		if ( $wpdb->last_error ) {
			throw new \RuntimeException( $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		foreach ( is_array( $rows ) ? $rows : array() as $r ) {
			$offer_id    = (int) ( $r['offer_id'] ?? 0 );
			$conversions = (int) ( $r['conversions'] ?? 0 );
			$name_row    = $wpdb->get_row( $wpdb->prepare( "SELECT name FROM {$offers} WHERE id = %d", $offer_id ), ARRAY_A );
			$applies     = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$logs} l WHERE l.offer_id = %d AND DATE(l.created_at) BETWEEN %s AND %s {$action_where}",
					$offer_id,
					$date_from,
					$date_to
				)
			);
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
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT o.id AS offer_id, o.name, COUNT(l.id) AS applies FROM {$logs} l
					INNER JOIN {$offers} o ON o.id = l.offer_id
					WHERE DATE(l.created_at) BETWEEN %s AND %s {$action_where}
					GROUP BY l.offer_id ORDER BY applies DESC LIMIT 3",
					$date_from,
					$date_to
				),
				ARRAY_A
			);
			if ( $wpdb->last_error ) {
				throw new \RuntimeException( $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
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
		$c = esc_sql( $wpdb->prefix . 'cro_campaigns' );
		$t = esc_sql( $wpdb->prefix . 'cro_ab_tests' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'cro_campaigns' ) ) !== $wpdb->prefix . 'cro_campaigns' ) {
			return null;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names escaped via esc_sql.
		$row = $wpdb->get_row(
			"SELECT c.id, c.name FROM {$c} c
			WHERE c.created_at <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)
			AND NOT EXISTS (
				SELECT 1 FROM {$t} ab WHERE ab.original_campaign_id = c.id AND ab.status = 'running'
			)
			LIMIT 1",
			ARRAY_A
		);
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
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ev ) ) !== $ev ) {
			return null;
		}
		$imp = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$ev} WHERE DATE(created_at) BETWEEN %s AND %s AND source_type = 'shipping_bar' AND event_type = 'impression'",
				$date_from,
				$date_to
			)
		);
		$int = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$ev} WHERE DATE(created_at) BETWEEN %s AND %s AND source_type = 'shipping_bar' AND event_type = 'interaction'",
				$date_from,
				$date_to
			)
		);
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
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ev ) ) !== $ev ) {
			return null;
		}
		$imp = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$ev} WHERE DATE(created_at) BETWEEN %s AND %s AND event_type = 'impression' AND (page_type = %s OR page_url LIKE %s)",
				$date_from,
				$date_to,
				'checkout',
				'%checkout%'
			)
		);
		$conv = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$ev} WHERE DATE(created_at) BETWEEN %s AND %s AND event_type = 'conversion' AND (page_type = %s OR page_url LIKE %s)",
				$date_from,
				$date_to,
				'checkout',
				'%checkout%'
			)
		);
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
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ev ) ) !== $ev ) {
			return null;
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT device_type,
					SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) AS imp,
					SUM(CASE WHEN event_type = 'conversion' THEN 1 ELSE 0 END) AS cv
				FROM {$ev} WHERE DATE(created_at) BETWEEN %s AND %s AND device_type IN ('mobile','desktop')
				GROUP BY device_type",
				$date_from,
				$date_to
			),
			ARRAY_A
		);
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
