<?php
/**
 * Multi-touch attribution for campaign and offer revenue.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MEYVC_Attribution
 */
class MEYVC_Attribution {

	const FIRST_TOUCH = 'first';
	const LAST_TOUCH  = 'last';
	const LINEAR      = 'linear';

	/**
	 * Valid model values.
	 *
	 * @return string[]
	 */
	private static function valid_models() {
		return array( self::FIRST_TOUCH, self::LAST_TOUCH, self::LINEAR );
	}

	/**
	 * Normalize model string.
	 *
	 * @param string $model Raw model.
	 * @return string
	 */
	public static function normalize_model( $model ) {
		$m = is_string( $model ) ? $model : '';
		return in_array( $m, self::valid_models(), true ) ? $m : self::LAST_TOUCH;
	}

	/**
	 * Human-readable label for a model.
	 *
	 * @param string|null $model Model or null for current.
	 * @return string
	 */
	public static function get_model_label( $model = null ) {
		$m = null === $model ? self::get_current_model() : self::normalize_model( (string) $model );
		switch ( $m ) {
			case self::FIRST_TOUCH:
				return __( 'First touch', 'meyvora-convert' );
			case self::LINEAR:
				return __( 'Linear', 'meyvora-convert' );
			case self::LAST_TOUCH:
			default:
				return __( 'Last touch', 'meyvora-convert' );
		}
	}

	/**
	 * Persisted attribution model.
	 *
	 * @return string
	 */
	public static function get_current_model() {
		if ( ! function_exists( 'meyvc_settings' ) ) {
			return self::LAST_TOUCH;
		}
		$raw = meyvc_settings()->get( 'analytics', 'attribution_model', self::LAST_TOUCH );
		return self::normalize_model( (string) $raw );
	}

	/**
	 * Save attribution model.
	 *
	 * @param string $model One of FIRST_TOUCH, LAST_TOUCH, LINEAR.
	 * @return void
	 */
	public static function set_model( $model ) {
		$m = self::normalize_model( (string) $model );
		if ( ! function_exists( 'meyvc_settings' ) ) {
			return;
		}
		meyvc_settings()->set( 'analytics', 'attribution_model', $m );
		if ( class_exists( 'MEYVC_Insights' ) ) {
			MEYVC_Insights::invalidate_attribution_cache();
		}
	}

	/**
	 * Campaign revenue by attribution model.
	 *
	 * @param string      $from         Y-m-d.
	 * @param string      $to           Y-m-d.
	 * @param string      $model        Model constant value.
	 * @param int|null    $campaign_id  Optional. When set, only that campaign appears in results.
	 * @return array<int, array{name: string, revenue: float, orders: int}> Keyed by campaign ID.
	 */
	public static function get_revenue_by_campaign( $from, $to, $model = self::LAST_TOUCH, $campaign_id = null ) {
		global $wpdb;

		$model       = self::normalize_model( (string) $model );
		$from        = self::sanitize_date( $from );
		$to          = self::sanitize_date( $to );
		$campaign_id = ( null !== $campaign_id && (int) $campaign_id > 0 ) ? absint( $campaign_id ) : null;

		$cached = MEYVC_Database::object_cache_get( 'attr_rev_by_camp', array( $from, $to, $model, $campaign_id ) );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			MEYVC_Database::object_cache_set( 'attr_rev_by_camp', array( $from, $to, $model, $campaign_id ), array() );
			return array();
		}

		$events_table    = esc_sql( $wpdb->prefix . 'meyvc_events' );
		$campaigns_table = esc_sql( $wpdb->prefix . 'meyvc_campaigns' );
		$events_ok       = MEYVC_Database::table_exists( $wpdb->prefix . 'meyvc_events' );
		if ( ! $events_ok ) {
			MEYVC_Database::object_cache_set( 'attr_rev_by_camp', array( $from, $to, $model, $campaign_id ), array() );
			return array();
		}

		$campaigns_ok = MEYVC_Database::table_exists( $wpdb->prefix . 'meyvc_campaigns' );

		$et = $wpdb->prefix . 'meyvc_events';
		if ( $campaign_id ) {
			$cache_key = 'meyvora_meyvc_' . md5( serialize( array( 'attr_order_ids_by_campaign', $et, $from, $to, $campaign_id ) ) );
			$order_ids = wp_cache_get( $cache_key, 'meyvora_meyvc' );
			if ( false === $order_ids ) {
				$res = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
					$wpdb->prepare(
						'SELECT DISTINCT e.order_id FROM %i e
			WHERE e.event_type = \'conversion\' AND e.source_type = \'campaign\'
			AND e.order_id IS NOT NULL AND e.order_id > 0
			AND e.created_at >= %s AND e.created_at < DATE_ADD(%s, INTERVAL 1 DAY)
			AND e.source_id = %d',
						$et,
						$from,
						$to,
						$campaign_id
					)
				);
				if ( $wpdb->last_error ) {
					$order_ids = array();
				} else {
					$order_ids = is_array( $res ) ? $res : array();
				}
				wp_cache_set( $cache_key, $order_ids, 'meyvora_meyvc', 300 );
			} else {
				$order_ids = is_array( $order_ids ) ? $order_ids : array();
			}
		} else {
			$cache_key = 'meyvora_meyvc_' . md5( serialize( array( 'attr_order_ids_in_range', $et, $from, $to ) ) );
			$order_ids = wp_cache_get( $cache_key, 'meyvora_meyvc' );
			if ( false === $order_ids ) {
				$res = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
					$wpdb->prepare(
						'SELECT DISTINCT e.order_id FROM %i e
			WHERE e.event_type = \'conversion\' AND e.source_type = \'campaign\'
			AND e.order_id IS NOT NULL AND e.order_id > 0
			AND e.created_at >= %s AND e.created_at < DATE_ADD(%s, INTERVAL 1 DAY)',
						$et,
						$from,
						$to
					)
				);
				if ( $wpdb->last_error ) {
					$order_ids = array();
				} else {
					$order_ids = is_array( $res ) ? $res : array();
				}
				wp_cache_set( $cache_key, $order_ids, 'meyvora_meyvc', 300 );
			} else {
				$order_ids = is_array( $order_ids ) ? $order_ids : array();
			}
		}
		if ( ! is_array( $order_ids ) ) {
			return array();
		}

		$agg = array();

		foreach ( $order_ids as $oid_raw ) {
			$oid = absint( $oid_raw );
			if ( $oid <= 0 ) {
				continue;
			}
			$order = wc_get_order( $oid );
			if ( ! $order ) {
				continue;
			}
			$created = $order->get_date_created();
			if ( ! $created ) {
				continue;
			}
			$order_date = $created->date( 'Y-m-d' );
			if ( strcmp( $order_date, $from ) < 0 || strcmp( $order_date, $to ) > 0 ) {
				continue;
			}

			$order_cutoff = $created->date( 'Y-m-d H:i:s' );
			$total        = (float) $order->get_total();
			if ( $total < 0 ) {
				$total = 0.0;
			}

			$credits = self::credit_campaigns_for_order( $oid, $order_cutoff, $model, $events_table, $total );
			foreach ( $credits as $cid => $amount ) {
				$cid = absint( $cid );
				if ( $cid <= 0 || $amount <= 0 ) {
					continue;
				}
				if ( $campaign_id && $cid !== $campaign_id ) {
					continue;
				}
				if ( ! isset( $agg[ $cid ] ) ) {
					$agg[ $cid ] = array(
						'revenue' => 0.0,
						'orders'  => array(),
					);
				}
				$agg[ $cid ]['revenue']    += $amount;
				$agg[ $cid ]['orders'][ $oid ] = true;
			}
		}

		$name_map = self::load_campaign_names( array_keys( $agg ), $wpdb->prefix . 'meyvc_campaigns', $campaigns_ok );

		$out = array();
		foreach ( $agg as $cid => $row ) {
			$out[ (int) $cid ] = array(
				'name'    => isset( $name_map[ $cid ] ) ? $name_map[ $cid ] : sprintf(
					/* translators: %d: campaign id */
					__( 'Campaign #%d', 'meyvora-convert' ),
					(int) $cid
				),
				'revenue' => round( (float) $row['revenue'], 4 ),
				'orders'  => count( $row['orders'] ),
			);
		}
		uasort(
			$out,
			static function ( $a, $b ) {
				return ( $b['revenue'] <=> $a['revenue'] );
			}
		);

		MEYVC_Database::object_cache_set( 'attr_rev_by_camp', array( $from, $to, $model, $campaign_id ), $out );
		return $out;
	}

	/**
	 * Total attributed campaign revenue (sum of get_revenue_by_campaign).
	 *
	 * @param string   $from        Y-m-d.
	 * @param string   $to          Y-m-d.
	 * @param string   $model       Model.
	 * @param int|null $campaign_id Optional.
	 * @return float
	 */
	public static function get_total_campaign_attributed_revenue( $from, $to, $model = self::LAST_TOUCH, $campaign_id = null ) {
		$sum = 0.0;
		foreach ( self::get_revenue_by_campaign( $from, $to, $model, $campaign_id ) as $row ) {
			$sum += (float) ( $row['revenue'] ?? 0 );
		}
		return round( $sum, 4 );
	}

	/**
	 * Previous period for KPI comparison (matches MEYVC_Analytics::get_summary_internal window).
	 *
	 * @param string $date_from Y-m-d.
	 * @param string $date_to   Y-m-d.
	 * @return array{0: string, 1: string} prev_from, prev_to.
	 */
	public static function get_comparison_period( $date_from, $date_to ) {
		$from = self::sanitize_date( $date_from );
		$to   = self::sanitize_date( $date_to );
		$days = ( strtotime( $to ) - strtotime( $from ) ) / 86400;
		$prev_from = wp_date( 'Y-m-d', strtotime( $from . " -{$days} days" ) );
		$prev_to   = wp_date( 'Y-m-d', strtotime( $from . ' -1 day' ) );
		return array( $prev_from, $prev_to );
	}

	/**
	 * Offer revenue by attribution model (orders linked in meyvc_offer_logs).
	 *
	 * @param string $from  Y-m-d.
	 * @param string $to    Y-m-d.
	 * @param string $model Model.
	 * @return array<int, array{name: string, revenue: float, orders: int}>
	 */
	public static function get_offer_attribution( $from, $to, $model = self::LAST_TOUCH ) {
		global $wpdb;

		$model = self::normalize_model( (string) $model );
		$from  = self::sanitize_date( $from );
		$to    = self::sanitize_date( $to );

		$cached = MEYVC_Database::object_cache_get( 'attr_offer_attr', array( $from, $to, $model ) );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			MEYVC_Database::object_cache_set( 'attr_offer_attr', array( $from, $to, $model ), array() );
			return array();
		}

		$logs_table   = $wpdb->prefix . 'meyvc_offer_logs';
		$offers_table = $wpdb->prefix . 'meyvc_offers';
		$events_table = $wpdb->prefix . 'meyvc_events';

		$logs_ok = MEYVC_Database::table_exists( $logs_table );
		$off_ok  = MEYVC_Database::table_exists( $offers_table );
		$ev_ok   = MEYVC_Database::table_exists( $events_table );
		if ( ! $logs_ok || ! $off_ok || ! $ev_ok ) {
			MEYVC_Database::object_cache_set( 'attr_offer_attr', array( $from, $to, $model ), array() );
			return array();
		}

		$cache_key = 'meyvora_meyvc_' . md5( serialize( array( 'attr_offer_log_order_ids', $logs_table, $from, $to ) ) );
		$rows      = wp_cache_get( $cache_key, 'meyvora_meyvc' );
		if ( false === $rows ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					"SELECT DISTINCT l.order_id FROM %i l
				WHERE l.order_id IS NOT NULL AND l.order_id > 0
				AND l.created_at >= %s AND l.created_at < DATE_ADD(%s, INTERVAL 1 DAY)",
					$logs_table,
					$from,
					$to
				),
				ARRAY_A
			);
			if ( ! is_array( $rows ) ) {
				$rows = array();
			}
			wp_cache_set( $cache_key, $rows, 'meyvora_meyvc', 300 );
		} else {
			$rows = is_array( $rows ) ? $rows : array();
		}
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$agg = array();

		foreach ( $rows as $r ) {
			$oid = isset( $r['order_id'] ) ? absint( $r['order_id'] ) : 0;
			if ( $oid <= 0 ) {
				continue;
			}
			$order = wc_get_order( $oid );
			if ( ! $order ) {
				continue;
			}
			$created = $order->get_date_created();
			if ( ! $created ) {
				continue;
			}
			$order_date = $created->date( 'Y-m-d' );
			if ( strcmp( $order_date, $from ) < 0 || strcmp( $order_date, $to ) > 0 ) {
				continue;
			}

			$order_cutoff = $created->date( 'Y-m-d H:i:s' );
			$total        = (float) $order->get_total();
			if ( $total < 0 ) {
				$total = 0.0;
			}

			$credits = self::credit_offers_for_order( $oid, $order_cutoff, $model, $events_table, $logs_table, $total );
			foreach ( $credits as $offer_id => $amount ) {
				$offer_id = absint( $offer_id );
				if ( $offer_id <= 0 || $amount <= 0 ) {
					continue;
				}
				if ( ! isset( $agg[ $offer_id ] ) ) {
					$agg[ $offer_id ] = array(
						'revenue' => 0.0,
						'orders'  => array(),
					);
				}
				$agg[ $offer_id ]['revenue']      += $amount;
				$agg[ $offer_id ]['orders'][ $oid ] = true;
			}
		}

		$name_map = self::load_offer_names( array_keys( $agg ), $wpdb->prefix . 'meyvc_offers' );

		$out = array();
		foreach ( $agg as $oid_key => $row ) {
			$out[ (int) $oid_key ] = array(
				'name'    => isset( $name_map[ $oid_key ] ) ? $name_map[ $oid_key ] : sprintf(
					/* translators: %d: offer id */
					__( 'Offer #%d', 'meyvora-convert' ),
					(int) $oid_key
				),
				'revenue' => round( (float) $row['revenue'], 4 ),
				'orders'  => count( $row['orders'] ),
			);
		}
		uasort(
			$out,
			static function ( $a, $b ) {
				return ( $b['revenue'] <=> $a['revenue'] );
			}
		);

		MEYVC_Database::object_cache_set( 'attr_offer_attr', array( $from, $to, $model ), $out );
		return $out;
	}

	/**
	 * Chart payload: top N campaigns by revenue.
	 *
	 * @param string      $from        Y-m-d.
	 * @param string      $to          Y-m-d.
	 * @param string      $model       Model.
	 * @param int|null    $campaign_id Optional filter.
	 * @param int         $limit       Max rows.
	 * @return array<int, array{label: string, revenue: float}>
	 */
	public static function get_campaign_chart_rows( $from, $to, $model = self::LAST_TOUCH, $campaign_id = null, $limit = 10 ) {
		$limit = max( 1, min( 50, (int) $limit ) );
		$rows  = self::get_revenue_by_campaign( $from, $to, $model, $campaign_id );
		$out   = array();
		$n     = 0;
		foreach ( $rows as $row ) {
			$out[] = array(
				'label'   => (string) ( $row['name'] ?? '' ),
				'revenue' => (float) ( $row['revenue'] ?? 0 ),
			);
			++$n;
			if ( $n >= $limit ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * @param string $d Y-m-d.
	 * @return string
	 */
	private static function sanitize_date( $d ) {
		$s = is_string( $d ) ? trim( $d ) : '';
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s ) ) {
			return $s;
		}
		return gmdate( 'Y-m-d' );
	}

	/**
	 * @param int[]  $ids            Campaign IDs.
	 * @param string $campaigns_table Full table name (for %i), e.g. wp_meyvc_campaigns.
	 * @param bool   $table_ok       Table exists.
	 * @return array<int, string>
	 */
	private static function load_campaign_names( array $ids, $campaigns_table, $table_ok ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( ! $table_ok || empty( $ids ) ) {
			return array();
		}
		$ck = MEYVC_Database::object_cache_get( 'attr_load_camp_names', array( $campaigns_table, $ids ) );
		if ( false !== $ck && is_array( $ck ) ) {
			return $ck;
		}
		$cache_key = 'meyvora_meyvc_' . md5( serialize( array( 'attr_campaign_names_by_ids', $campaigns_table, $ids ) ) );
		$rows      = wp_cache_get( $cache_key, 'meyvora_meyvc' );
		if ( false === $rows ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT id, name FROM %i WHERE id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')',
					...array_merge( array( $campaigns_table ), $ids )
				),
				ARRAY_A
			);
			if ( ! is_array( $rows ) ) {
				$rows = array();
			}
			wp_cache_set( $cache_key, $rows, 'meyvora_meyvc', 300 );
		} else {
			$rows = is_array( $rows ) ? $rows : array();
		}
		$map          = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$map[ absint( $row['id'] ?? 0 ) ] = (string) ( $row['name'] ?? '' );
		}
		MEYVC_Database::object_cache_set( 'attr_load_camp_names', array( $campaigns_table, $ids ), $map );
		return $map;
	}

	/**
	 * @param int[]  $ids Offer IDs.
	 * @param string $offers_table Full table name (for %i), e.g. wp_meyvc_offers.
	 * @return array<int, string>
	 */
	private static function load_offer_names( array $ids, $offers_table ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( empty( $ids ) ) {
			return array();
		}
		$ck = MEYVC_Database::object_cache_get( 'attr_load_offer_names', array( $offers_table, $ids ) );
		if ( false !== $ck && is_array( $ck ) ) {
			return $ck;
		}
		$cache_key = 'meyvora_meyvc_' . md5( serialize( array( 'attr_offer_names_by_ids', $offers_table, $ids ) ) );
		$rows      = wp_cache_get( $cache_key, 'meyvora_meyvc' );
		if ( false === $rows ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT id, name FROM %i WHERE id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')',
					...array_merge( array( $offers_table ), $ids )
				),
				ARRAY_A
			);
			if ( ! is_array( $rows ) ) {
				$rows = array();
			}
			wp_cache_set( $cache_key, $rows, 'meyvora_meyvc', 300 );
		} else {
			$rows = is_array( $rows ) ? $rows : array();
		}
		$map          = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$map[ absint( $row['id'] ?? 0 ) ] = (string) ( $row['name'] ?? '' );
		}
		MEYVC_Database::object_cache_set( 'attr_load_offer_names', array( $offers_table, $ids ), $map );
		return $map;
	}

	/**
	 * Campaign credits for one order.
	 *
	 * @param int    $order_id      Order ID.
	 * @param string $order_cutoff  'Y-m-d H:i:s' — impressions must be <= this.
	 * @param string $model         Model.
	 * @param string $events_table  Escaped table name.
	 * @param float  $total         Order total (WC).
	 * @return array<int, float> campaign_id => amount.
	 */
	private static function credit_campaigns_for_order( $order_id, $order_cutoff, $model, $events_table, $total ) {
		global $wpdb;

		$total = (float) $total;
		if ( $total < 0 ) {
			$total = 0.0;
		}
		$ck_params = array( (int) $order_id, (string) $order_cutoff, (string) $model, (string) $events_table, $total );
		$cc_key    = MEYVC_Database::object_cache_get( 'attr_credit_camp', $ck_params );
		if ( false !== $cc_key && is_array( $cc_key ) ) {
			return $cc_key;
		}

		$cache_key_sess = 'meyvora_meyvc_' . md5( serialize( array( 'attr_conversion_session_campaign', $events_table, $order_id, 'conversion', 'campaign' ) ) );
		$session_row    = wp_cache_get( $cache_key_sess, 'meyvora_meyvc' );
		if ( false === $session_row ) {
			$session_row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT session_id, source_id FROM %i
				WHERE order_id = %d AND event_type = %s AND source_type = %s
				ORDER BY created_at DESC LIMIT 1',
					$events_table,
					$order_id,
					'conversion',
					'campaign'
				),
				ARRAY_A
			);
			wp_cache_set( $cache_key_sess, $session_row, 'meyvora_meyvc', 300 );
		}

		$session_id = '';
		if ( is_array( $session_row ) ) {
			$session_id = (string) ( $session_row['session_id'] ?? '' );
		}
		if ( $session_id === '' ) {
			$session_id = self::session_from_order_metadata( $order_id, $events_table );
		}

		$fallback_cid = 0;
		if ( is_array( $session_row ) && isset( $session_row['source_id'] ) ) {
			$fallback_cid = absint( $session_row['source_id'] );
		}

		if ( $session_id === '' ) {
			$out = $fallback_cid > 0 && $total > 0 ? array( $fallback_cid => $total ) : array();
			MEYVC_Database::object_cache_set( 'attr_credit_camp', $ck_params, $out );
			return $out;
		}

		$cache_key_imps = 'meyvora_meyvc_' . md5( serialize( array( 'attr_campaign_impressions_session', $events_table, $session_id, 'impression', 'campaign', $order_cutoff ) ) );
		$imps           = wp_cache_get( $cache_key_imps, 'meyvora_meyvc' );
		if ( false === $imps ) {
			$imps = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT source_id, created_at FROM %i
				WHERE session_id = %s AND event_type = %s AND source_type = %s
				AND source_id IS NOT NULL AND source_id > 0
				AND created_at <= %s
				ORDER BY created_at ASC',
					$events_table,
					$session_id,
					'impression',
					'campaign',
					$order_cutoff
				),
				ARRAY_A
			);
			if ( ! is_array( $imps ) ) {
				$imps = array();
			}
			wp_cache_set( $cache_key_imps, $imps, 'meyvora_meyvc', 300 );
		} else {
			$imps = is_array( $imps ) ? $imps : array();
		}

		if ( ! is_array( $imps ) || empty( $imps ) ) {
			$out = $fallback_cid > 0 && $total > 0 ? array( $fallback_cid => $total ) : array();
			MEYVC_Database::object_cache_set( 'attr_credit_camp', $ck_params, $out );
			return $out;
		}

		$dist = self::distribute_amount( $imps, $total, $model );
		MEYVC_Database::object_cache_set( 'attr_credit_camp', $ck_params, $dist );
		return $dist;
	}

	/**
	 * Offer credits for one order.
	 *
	 * @param int    $order_id     Order ID.
	 * @param string $order_cutoff Order datetime.
	 * @param string $model        Model.
	 * @param string $events_table Events table (escaped).
	 * @param string $logs_table   Offer logs table (escaped).
	 * @param float  $total        Order total.
	 * @return array<int, float> offer_id => amount.
	 */
	private static function credit_offers_for_order( $order_id, $order_cutoff, $model, $events_table, $logs_table, $total ) {
		global $wpdb;

		$cache_key_log = 'meyvora_meyvc_' . md5( serialize( array( 'attr_offer_log_latest', $logs_table, $order_id ) ) );
		$log_row       = wp_cache_get( $cache_key_log, 'meyvora_meyvc' );
		if ( false === $log_row ) {
			$log_row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT offer_id FROM %i WHERE order_id = %d ORDER BY id DESC LIMIT 1',
					$logs_table,
					$order_id
				),
				ARRAY_A
			);
			wp_cache_set( $cache_key_log, $log_row, 'meyvora_meyvc', 300 );
		}
		$fallback_oid = is_array( $log_row ) ? absint( $log_row['offer_id'] ?? 0 ) : 0;

		$cache_key_sess_o = 'meyvora_meyvc_' . md5( serialize( array( 'attr_conversion_session_offer', $events_table, $order_id, 'conversion', 'offer' ) ) );
		$session_row      = wp_cache_get( $cache_key_sess_o, 'meyvora_meyvc' );
		if ( false === $session_row ) {
			$session_row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT session_id, source_id FROM %i
				WHERE order_id = %d AND event_type = %s AND source_type = %s
				ORDER BY created_at DESC LIMIT 1',
					$events_table,
					$order_id,
					'conversion',
					'offer'
				),
				ARRAY_A
			);
			wp_cache_set( $cache_key_sess_o, $session_row, 'meyvora_meyvc', 300 );
		}

		$session_id = '';
		if ( is_array( $session_row ) ) {
			$session_id = (string) ( $session_row['session_id'] ?? '' );
		}
		if ( $session_id === '' ) {
			$session_id = self::session_from_offer_events( $order_id, $events_table );
		}

		if ( is_array( $session_row ) && isset( $session_row['source_id'] ) && absint( $session_row['source_id'] ) > 0 ) {
			$fallback_oid = absint( $session_row['source_id'] );
		}

		$total = (float) $total;
		if ( $total < 0 ) {
			$total = 0.0;
		}

		if ( $session_id === '' ) {
			return $fallback_oid > 0 && $total > 0 ? array( $fallback_oid => $total ) : array();
		}

		$cache_key_imps_o = 'meyvora_meyvc_' . md5( serialize( array( 'attr_offer_impressions_session', $events_table, $session_id, 'impression', 'offer', $order_cutoff ) ) );
		$imps             = wp_cache_get( $cache_key_imps_o, 'meyvora_meyvc' );
		if ( false === $imps ) {
			$imps = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT source_id, created_at FROM %i
				WHERE session_id = %s AND event_type = %s AND source_type = %s
				AND source_id IS NOT NULL AND source_id > 0
				AND created_at <= %s
				ORDER BY created_at ASC',
					$events_table,
					$session_id,
					'impression',
					'offer',
					$order_cutoff
				),
				ARRAY_A
			);
			if ( ! is_array( $imps ) ) {
				$imps = array();
			}
			wp_cache_set( $cache_key_imps_o, $imps, 'meyvora_meyvc', 300 );
		} else {
			$imps = is_array( $imps ) ? $imps : array();
		}

		if ( ! is_array( $imps ) || empty( $imps ) ) {
			return $fallback_oid > 0 && $total > 0 ? array( $fallback_oid => $total ) : array();
		}

		return self::distribute_amount( $imps, $total, $model );
	}

	/**
	 * @param int    $order_id     Order ID.
	 * @param string $events_table Escaped.
	 * @return string Session or empty.
	 */
	private static function session_from_order_metadata( $order_id, $events_table ) {
		global $wpdb;
		$cache_key_meta = 'meyvora_meyvc_' . md5( serialize( array( 'attr_events_metadata_by_order', $events_table, $order_id ) ) );
		$rows           = wp_cache_get( $cache_key_meta, 'meyvora_meyvc' );
		if ( false === $rows ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT metadata FROM %i WHERE order_id = %d AND metadata IS NOT NULL AND metadata != %s LIMIT 20',
					$events_table,
					$order_id,
					''
				),
				ARRAY_A
			);
			if ( ! is_array( $rows ) ) {
				$rows = array();
			}
			wp_cache_set( $cache_key_meta, $rows, 'meyvora_meyvc', 300 );
		} else {
			$rows = is_array( $rows ) ? $rows : array();
		}
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$meta = maybe_unserialize( $row['metadata'] ?? null );
			if ( ! is_array( $meta ) ) {
				continue;
			}
			if ( ! empty( $meta['session_id'] ) && is_string( $meta['session_id'] ) ) {
				return sanitize_text_field( $meta['session_id'] );
			}
		}
		return '';
	}

	/**
	 * @param int    $order_id     Order ID.
	 * @param string $events_table Escaped.
	 * @return string Session or empty.
	 */
	private static function session_from_offer_events( $order_id, $events_table ) {
		global $wpdb;
		$cache_key_sid = 'meyvora_meyvc_' . md5( serialize( array( 'attr_offer_session_ids_by_order', $events_table, $order_id, 'offer' ) ) );
		$rows          = wp_cache_get( $cache_key_sid, 'meyvora_meyvc' );
		if ( false === $rows ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT session_id FROM %i WHERE order_id = %d AND source_type = %s ORDER BY id DESC LIMIT 5',
					$events_table,
					$order_id,
					'offer'
				),
				ARRAY_A
			);
			if ( ! is_array( $rows ) ) {
				$rows = array();
			}
			wp_cache_set( $cache_key_sid, $rows, 'meyvora_meyvc', 300 );
		} else {
			$rows = is_array( $rows ) ? $rows : array();
		}
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$sid = (string) ( $row['session_id'] ?? '' );
			if ( $sid !== '' ) {
				return $sid;
			}
		}
		return self::session_from_order_metadata( $order_id, $events_table );
	}

	/**
	 * Build credits from impression rows.
	 *
	 * @param array  $imps   Rows with source_id, created_at (ordered ASC).
	 * @param float  $total  Order total.
	 * @param string $model  Model.
	 * @return array<int, float>
	 */
	private static function distribute_amount( array $imps, $total, $model ) {
		$total = (float) $total;
		if ( $total <= 0 ) {
			return array();
		}

		if ( self::LINEAR === $model ) {
			$n = count( $imps );
			if ( $n <= 0 ) {
				return array();
			}
			$per   = round( $total / $n, 6 );
			$acc   = array();
			$given = 0.0;
			$i     = 0;
			foreach ( $imps as $row ) {
				++$i;
				$cid = absint( $row['source_id'] ?? 0 );
				if ( $cid <= 0 ) {
					continue;
				}
				$slice = ( $i === $n ) ? round( $total - $given, 4 ) : $per;
				$given += $slice;
				if ( ! isset( $acc[ $cid ] ) ) {
					$acc[ $cid ] = 0.0;
				}
				$acc[ $cid ] += $slice;
			}
			return $acc;
		}

		$pick = null;
		if ( self::FIRST_TOUCH === $model ) {
			$pick = $imps[0];
		} else {
			$pick = $imps[ count( $imps ) - 1 ];
		}
		$cid = absint( $pick['source_id'] ?? 0 );
		return $cid > 0 ? array( $cid => $total ) : array();
	}
}
