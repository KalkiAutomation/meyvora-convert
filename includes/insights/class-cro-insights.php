<?php
/**
 * CRO Insights – rule-based recommendations from tracking data.
 * Uses cro_events and cro_daily_stats. No ML; actionable cards with "Fix" CTAs.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class CRO_Insights
 */
class CRO_Insights {

	/**
	 * Number of days to analyze (default 30).
	 *
	 * @var int
	 */
	const DAYS = 30;

	/**
	 * Get actionable insight cards for the admin Insights tab.
	 * Each item: id, type (top|underperforming|action), title, description, fix_url, fix_label.
	 *
	 * @param int $days Number of days to analyze.
	 * @return array<int, array{id: string, type: string, title: string, description: string, fix_url: string, fix_label: string}>
	 */
	public static function get_insights( $days = self::DAYS ) {
		$insights = array();
		$date_to   = wp_date( 'Y-m-d' );
		$date_from = wp_date( 'Y-m-d', strtotime( "-{$days} days" ) );

		// Top performing offer (highest apply rate / conversion lift).
		$top_offer = self::get_top_performing_offer( $date_from, $date_to );
		if ( $top_offer ) {
			$insights[] = array(
				'id'          => 'top-offer',
				'type'        => 'top',
				'title'       => __( 'Top performing offer', 'meyvora-convert' ),
				'description' => sprintf(
					/* translators: 1: offer name, 2: apply count or metric */
					__( '%1$s is driving the most applies. Consider promoting it or creating similar rules.', 'meyvora-convert' ),
					esc_html( $top_offer['name'] ),
					$top_offer['applies']
				),
				'fix_url'     => admin_url( 'admin.php?page=cro-offers' ),
				'fix_label'   => __( 'View Offers', 'meyvora-convert' ),
			);
		}

		// Underperforming: high impressions, low clicks/conversions.
		$under = self::get_underperforming_campaign( $date_from, $date_to );
		if ( $under ) {
			$insights[] = array(
				'id'          => 'underperforming-campaign',
				'type'        => 'underperforming',
				'title'       => __( 'Campaign with low conversion', 'meyvora-convert' ),
				'description' => sprintf(
					/* translators: 1: campaign name, 2: impressions, 3: conversions */
					__( '%1$s has %2$s impressions but only %3$s conversions. Try a stronger CTA or different placement.', 'meyvora-convert' ),
					esc_html( $under['name'] ),
					$under['impressions'],
					$under['conversions']
				),
				'fix_url'     => admin_url( 'admin.php?page=cro-campaigns' ),
				'fix_label'   => __( 'Edit Campaigns', 'meyvora-convert' ),
			);
		}

		// Next best action: shipping bar shown but no lifts.
		$shipping_action = self::get_shipping_bar_action( $date_from, $date_to );
		if ( $shipping_action ) {
			$insights[] = array(
				'id'          => 'shipping-threshold',
				'type'        => 'action',
				'title'       => __( 'Adjust shipping bar threshold', 'meyvora-convert' ),
				'description' => $shipping_action,
				'fix_url'     => admin_url( 'admin.php?page=cro-boosters' ),
				'fix_label'   => __( 'Boosters settings', 'meyvora-convert' ),
			);
		}

		// Offer applied often but conversion low – coupon restrictions.
		$coupon_action = self::get_coupon_restriction_action( $date_from, $date_to );
		if ( $coupon_action ) {
			$insights[] = array(
				'id'          => 'coupon-restrictions',
				'type'        => 'action',
				'title'       => __( 'Review coupon restrictions', 'meyvora-convert' ),
				'description' => $coupon_action,
				'fix_url'     => admin_url( 'admin.php?page=cro-offers' ),
				'fix_label'   => __( 'View Offers', 'meyvora-convert' ),
			);
		}

		// Low CTR on campaigns – placement.
		$placement_action = self::get_placement_action( $date_from, $date_to );
		if ( $placement_action ) {
			$insights[] = array(
				'id'          => 'placement',
				'type'        => 'action',
				'title'       => __( 'Improve campaign placement', 'meyvora-convert' ),
				'description' => $placement_action,
				'fix_url'     => admin_url( 'admin.php?page=cro-campaigns' ),
				'fix_label'   => __( 'Campaigns', 'meyvora-convert' ),
			);
		}

		return array_slice( apply_filters( 'cro_insights_cards', $insights, $days ), 0, 6 );
	}

	/**
	 * Transient cache key for attribution (keyed by window days + epoch for invalidation).
	 *
	 * @param int $window_days Window days.
	 * @return string
	 */
	private static function get_attribution_cache_key( $window_days ) {
		$epoch = (int) get_option( 'cro_attribution_cache_epoch', 0 );
		return 'cro_attribution_' . $window_days . '_v' . $epoch;
	}

	/**
	 * Invalidate attribution cache. Call when events are tracked, offers applied, or AB conversions recorded.
	 */
	public static function invalidate_attribution_cache() {
		update_option( 'cro_attribution_cache_epoch', (int) get_option( 'cro_attribution_cache_epoch', 0 ) + 1 );
	}

	/**
	 * Get attribution data for Insights: top 3 by conversions (campaign / offer / AB test) and last N days totals.
	 * Uses filter cro_tracking_attribution_window_days (default 7). Cached via transient; TTL filterable (cro_insights_cache_ttl, default 600).
	 * On missing tables or errors returns array with not_enough_data true and empty lists (no fatals).
	 *
	 * @return array{ window_days: int, total_conversions: int, total_impressions: int, top_campaigns: array, top_offers: array, top_ab_tests: array, not_enough_data?: bool }
	 */
	public static function get_attribution() {
		$window_days = (int) apply_filters( 'cro_tracking_attribution_window_days', 7 );
		$window_days = $window_days >= 1 && $window_days <= 90 ? $window_days : 7;

		$cache_key = self::get_attribution_cache_key( $window_days );
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
	 * Empty attribution response (guardrail: tables missing or error).
	 *
	 * @param int  $window_days Window days.
	 * @param bool $not_enough_data Whether to set not_enough_data for UI.
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
	 * Build attribution data without cache. Used internally; throws on DB errors or missing tables.
	 *
	 * @param int $window_days Window days.
	 * @return array
	 * @throws \Throwable On query failure or timeout.
	 */
	private static function get_attribution_uncached( $window_days ) {
		$date_to   = wp_date( 'Y-m-d' );
		$date_from = wp_date( 'Y-m-d', strtotime( "-{$window_days} days" ) );

		$total_conversions = 0;
		$total_impressions = 0;
		$top_campaigns     = array();
		$top_offers        = array();
		$top_ab_tests      = array();

		global $wpdb;
		$events_table = $wpdb->prefix . 'cro_events';
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $events_table ) );
		if ( $table_exists !== $events_table ) {
			return self::attribution_empty_response( $window_days, true );
		}

		$total_conversions = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$events_table} WHERE DATE(created_at) BETWEEN %s AND %s AND event_type = 'conversion'",
			$date_from,
			$date_to
		) );
		$total_impressions = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$events_table} WHERE DATE(created_at) BETWEEN %s AND %s AND event_type = 'impression'",
			$date_from,
			$date_to
		) );
		if ( $wpdb->last_error ) {
			throw new \RuntimeException( 'Attribution query failed: ' . $wpdb->last_error );
		}

		$analytics       = new CRO_Analytics();
		$campaign_rows   = $analytics->get_campaign_performance( $date_from, $date_to, 3 );
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
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $logs ) ) === $logs
			&& $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $offers ) ) === $offers ) {
			$has_action   = $wpdb->get_var( "SHOW COLUMNS FROM {$logs} LIKE 'action'" );
			$action_where = $has_action ? " AND l.action = 'applied'" : '';
			$top_offers   = self::get_top_offers_attribution( $events_table, $logs, $offers, $date_from, $date_to, $action_where );
			$top_offers_list = array();
			foreach ( $top_offers as $row ) {
				$top_offers_list[] = array(
					'name'        => $row['name'] ?? __( 'Unknown', 'meyvora-convert' ),
					'conversions' => (int) ( $row['conversions'] ?? 0 ),
					'applies'     => (int) ( $row['applies'] ?? 0 ),
					'rate'        => isset( $row['rate'] ) ? $row['rate'] : null,
					'url'         => admin_url( 'admin.php?page=cro-offers' ),
				);
			}
			$top_offers = $top_offers_list;
		}

		$ab_tests_table = $wpdb->prefix . 'cro_ab_tests';
		$ab_var_table   = $wpdb->prefix . 'cro_ab_variations';
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
				throw new \RuntimeException( 'Attribution AB query failed: ' . $wpdb->last_error );
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
	 * Get top offers for attribution: rank by conversions first, with applies and apply→conversion rate.
	 *
	 * @param string $events_table  cro_events table name.
	 * @param string $logs          cro_offer_logs table name.
	 * @param string $offers        cro_offers table name.
	 * @param string $date_from     Y-m-d.
	 * @param string $date_to       Y-m-d.
	 * @param string $action_where  Optional. Extra WHERE fragment for logs (e.g. AND l.action = 'applied').
	 * @return array[] List of { name, conversions, applies, rate }.
	 */
	private static function get_top_offers_attribution( $events_table, $logs, $offers, $date_from, $date_to, $action_where = '' ) {
		global $wpdb;
		$list = array();

		// Check if events table supports source_type 'offer' (enum may not include it before migration).
		$col = $wpdb->get_row( $wpdb->prepare(
			"SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'source_type'",
			DB_NAME,
			$events_table
		) );
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
			throw new \RuntimeException( 'Attribution offer conversions query failed: ' . $wpdb->last_error );
		}

		foreach ( is_array( $rows ) ? $rows : array() as $r ) {
			$offer_id    = (int) ( $r['offer_id'] ?? 0 );
			$conversions = (int) ( $r['conversions'] ?? 0 );
			$name_row    = $wpdb->get_row( $wpdb->prepare( "SELECT name FROM {$offers} WHERE id = %d", $offer_id ), ARRAY_A );
			$applies     = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$logs} l WHERE l.offer_id = %d AND DATE(l.created_at) BETWEEN %s AND %s {$action_where}",
				$offer_id,
				$date_from,
				$date_to
			) );
			$rate = null;
			if ( $applies > 0 ) {
				$rate = round( ( $conversions / $applies ) * 100 );
			}
			$list[] = array(
				'name'        => $name_row['name'] ?? __( 'Unknown', 'meyvora-convert' ),
				'conversions' => $conversions,
				'applies'     => $applies,
				'rate'        => $rate,
			);
		}

		// If no conversion-based rows, fall back to top by applies (backward compatibility).
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
				throw new \RuntimeException( 'Attribution offer query failed: ' . $wpdb->last_error );
			}
			foreach ( is_array( $rows ) ? $rows : array() as $r ) {
				$list[] = array(
					'name'        => $r['name'] ?? __( 'Unknown', 'meyvora-convert' ),
					'conversions' => 0,
					'applies'     => (int) ( $r['applies'] ?? 0 ),
					'rate'        => null,
				);
			}
		}

		return $list;
	}

	/**
	 * Get top performing offer from offer_logs or events (apply/conversion).
	 *
	 * @param string $date_from Y-m-d.
	 * @param string $date_to   Y-m-d.
	 * @return array{name: string, applies: int}|null
	 */
	private static function get_top_performing_offer( $date_from, $date_to ) {
		global $wpdb;
		$logs = $wpdb->prefix . 'cro_offer_logs';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $logs ) ) !== $logs ) {
			return null;
		}
		$offers = $wpdb->prefix . 'cro_offers';
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT o.name, COUNT(l.id) AS applies FROM {$logs} l
				INNER JOIN {$offers} o ON o.id = l.offer_id
				WHERE DATE(l.created_at) BETWEEN %s AND %s AND l.action = 'applied'
				GROUP BY l.offer_id ORDER BY applies DESC LIMIT 1",
				$date_from,
				$date_to
			),
			ARRAY_A
		);
		return $row ? array( 'name' => $row['name'], 'applies' => (int) $row['applies'] ) : null;
	}

	/**
	 * Get underperforming campaign (high impressions, low conversions).
	 *
	 * @param string $date_from Y-m-d.
	 * @param string $date_to   Y-m-d.
	 * @return array{name: string, impressions: int, conversions: int}|null
	 */
	private static function get_underperforming_campaign( $date_from, $date_to ) {
		$analytics = new CRO_Analytics();
		$rows = $analytics->get_campaign_performance( $date_from, $date_to, 20 );
		foreach ( $rows as $row ) {
			$imp = (int) ( $row['impressions'] ?? 0 );
			$conv = (int) ( $row['conversions'] ?? 0 );
			if ( $imp >= 50 && $conv < 2 && $imp > $conv * 20 ) {
				return array(
					'name'         => $row['name'] ?? __( 'Unknown', 'meyvora-convert' ),
					'impressions'  => $imp,
					'conversions'  => $conv,
				);
			}
		}
		return null;
	}

	/**
	 * Suggest shipping bar threshold adjustment if bar has impressions but no interactions.
	 *
	 * @param string $date_from Y-m-d.
	 * @param string $date_to   Y-m-d.
	 * @return string Empty if no action.
	 */
	private static function get_shipping_bar_action( $date_from, $date_to ) {
		global $wpdb;
		$t = $wpdb->prefix . 'cro_events';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) {
			return '';
		}
		$imp = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$t} WHERE DATE(created_at) BETWEEN %s AND %s AND source_type = 'shipping_bar' AND event_type = 'impression'",
			$date_from,
			$date_to
		) );
		$int = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$t} WHERE DATE(created_at) BETWEEN %s AND %s AND source_type = 'shipping_bar' AND event_type = 'interaction'",
			$date_from,
			$date_to
		) );
		if ( $imp >= 30 && $int < 3 ) {
			return __( 'Shipping bar is shown often but rarely drives action. Lower the free-shipping threshold or make the message more compelling.', 'meyvora-convert' );
		}
		return '';
	}

	/**
	 * Suggest reviewing coupon restrictions if applies are high but conversions low.
	 *
	 * @param string $date_from Y-m-d.
	 * @param string $date_to   Y-m-d.
	 * @return string Empty if no action.
	 */
	private static function get_coupon_restriction_action( $date_from, $date_to ) {
		global $wpdb;
		$t = $wpdb->prefix . 'cro_events';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) {
			return '';
		}
		$convs_with_coupon = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$t} WHERE DATE(created_at) BETWEEN %s AND %s AND event_type = 'conversion' AND coupon_code IS NOT NULL AND coupon_code != ''",
			$date_from,
			$date_to
		) );
		$conversions = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$t} WHERE DATE(created_at) BETWEEN %s AND %s AND event_type = 'conversion'",
			$date_from,
			$date_to
		) );
		$applies = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$t} WHERE DATE(created_at) BETWEEN %s AND %s AND event_type = 'interaction' AND event_data LIKE %s",
			$date_from,
			$date_to,
			'%apply%'
		) );
		if ( $applies >= 10 && $conversions < $applies * 0.3 ) {
			return __( 'Coupons are applied often but conversion is low. Check minimum order amount, product restrictions, or expiry.', 'meyvora-convert' );
		}
		return '';
	}

	/**
	 * Suggest placement/CTA improvements if campaigns have low CTR.
	 *
	 * @param string $date_from Y-m-d.
	 * @param string $date_to   Y-m-d.
	 * @return string Empty if no action.
	 */
	private static function get_placement_action( $date_from, $date_to ) {
		$analytics = new CRO_Analytics();
		$rows = $analytics->get_campaign_performance( $date_from, $date_to, 5 );
		foreach ( $rows as $row ) {
			$imp = (int) ( $row['impressions'] ?? 0 );
			$conv = (int) ( $row['conversions'] ?? 0 );
			if ( $imp >= 100 && $imp > 0 && ( $conv / $imp ) < 0.02 ) {
				return __( 'Some campaigns have very low click-through. Try exit-intent or scroll triggers, or a clearer CTA.', 'meyvora-convert' );
			}
		}
		return '';
	}
}
