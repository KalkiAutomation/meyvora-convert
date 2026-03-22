<?php
/**
 * Multi-touch attribution for campaign and offer revenue.
 *
 * @package Meyvora_Convert
 */
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CRO_Attribution
 */
class CRO_Attribution {

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
		if ( ! function_exists( 'cro_settings' ) ) {
			return self::LAST_TOUCH;
		}
		$raw = cro_settings()->get( 'analytics', 'attribution_model', self::LAST_TOUCH );
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
		if ( ! function_exists( 'cro_settings' ) ) {
			return;
		}
		cro_settings()->set( 'analytics', 'attribution_model', $m );
		if ( class_exists( 'CRO_Insights' ) ) {
			CRO_Insights::invalidate_attribution_cache();
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

		if ( ! function_exists( 'wc_get_order' ) ) {
			return array();
		}

		$events_table    = esc_sql( $wpdb->prefix . 'cro_events' );
		$campaigns_table = esc_sql( $wpdb->prefix . 'cro_campaigns' );
		$events_ok       = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $events_table ) ) === $events_table;
		if ( ! $events_ok ) {
			return array();
		}

		$campaigns_ok = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $campaigns_table ) ) === $campaigns_table;

		$et = $wpdb->prefix . 'cro_events';
		if ( $campaign_id ) {
			$order_ids = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT DISTINCT e.order_id FROM %i e
			WHERE e.event_type = \'conversion\' AND e.source_type = \'campaign\'
			AND e.order_id IS NOT NULL AND e.order_id > 0
			AND DATE(e.created_at) BETWEEN %s AND %s
			AND e.source_id = %d',
					$et,
					$from,
					$to,
					$campaign_id
				)
			);
		} else {
			$order_ids = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT DISTINCT e.order_id FROM %i e
			WHERE e.event_type = \'conversion\' AND e.source_type = \'campaign\'
			AND e.order_id IS NOT NULL AND e.order_id > 0
			AND DATE(e.created_at) BETWEEN %s AND %s',
					$et,
					$from,
					$to
				)
			);
		}
		if ( $wpdb->last_error || ! is_array( $order_ids ) ) {
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

		$name_map = self::load_campaign_names( array_keys( $agg ), $wpdb->prefix . 'cro_campaigns', $campaigns_ok );

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
	 * Previous period for KPI comparison (matches CRO_Analytics::get_summary_internal window).
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
	 * Offer revenue by attribution model (orders linked in cro_offer_logs).
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

		if ( ! function_exists( 'wc_get_order' ) ) {
			return array();
		}

		$logs_table   = esc_sql( $wpdb->prefix . 'cro_offer_logs' );
		$offers_table = esc_sql( $wpdb->prefix . 'cro_offers' );
		$events_table = esc_sql( $wpdb->prefix . 'cro_events' );

		$logs_ok = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $logs_table ) ) === $logs_table;
		$off_ok  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $offers_table ) ) === $offers_table;
		$ev_ok   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $events_table ) ) === $events_table;
		if ( ! $logs_ok || ! $off_ok || ! $ev_ok ) {
			return array();
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT l.order_id FROM {$logs_table} l
				WHERE l.order_id IS NOT NULL AND l.order_id > 0
				AND DATE(l.created_at) BETWEEN %s AND %s",
				$from,
				$to
			),
			ARRAY_A
		);
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

		$name_map = self::load_offer_names( array_keys( $agg ), $wpdb->prefix . 'cro_offers' );

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
	 * @param string $campaigns_table Full table name (for %i), e.g. wp_cro_campaigns.
	 * @param bool   $table_ok       Table exists.
	 * @return array<int, string>
	 */
	private static function load_campaign_names( array $ids, $campaigns_table, $table_ok ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( ! $table_ok || empty( $ids ) ) {
			return array();
		}
		$id_list = implode( ',', $ids );
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IN list: comma-separated absint IDs; table via %i.
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, name FROM %i WHERE id IN (' . $id_list . ')',
				$campaigns_table
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$map          = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$map[ absint( $row['id'] ?? 0 ) ] = (string) ( $row['name'] ?? '' );
		}
		return $map;
	}

	/**
	 * @param int[]  $ids Offer IDs.
	 * @param string $offers_table Full table name (for %i), e.g. wp_cro_offers.
	 * @return array<int, string>
	 */
	private static function load_offer_names( array $ids, $offers_table ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( empty( $ids ) ) {
			return array();
		}
		$id_list = implode( ',', $ids );
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IN list: comma-separated absint IDs; table via %i.
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, name FROM %i WHERE id IN (' . $id_list . ')',
				$offers_table
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$map          = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$map[ absint( $row['id'] ?? 0 ) ] = (string) ( $row['name'] ?? '' );
		}
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

		$session_row = $wpdb->get_row(
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

		$total = (float) $total;
		if ( $total < 0 ) {
			$total = 0.0;
		}

		if ( $session_id === '' ) {
			return $fallback_cid > 0 && $total > 0 ? array( $fallback_cid => $total ) : array();
		}

		$imps = $wpdb->get_results(
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

		if ( ! is_array( $imps ) || empty( $imps ) ) {
			return $fallback_cid > 0 && $total > 0 ? array( $fallback_cid => $total ) : array();
		}

		return self::distribute_amount( $imps, $total, $model );
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

		$log_row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT offer_id FROM %i WHERE order_id = %d ORDER BY id DESC LIMIT 1',
				$logs_table,
				$order_id
			),
			ARRAY_A
		);
		$fallback_oid = is_array( $log_row ) ? absint( $log_row['offer_id'] ?? 0 ) : 0;

		$session_row = $wpdb->get_row(
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

		$imps = $wpdb->get_results(
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
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT metadata FROM %i WHERE order_id = %d AND metadata IS NOT NULL AND metadata != %s LIMIT 20',
				$events_table,
				$order_id,
				''
			),
			ARRAY_A
		);
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
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT session_id FROM %i WHERE order_id = %d AND source_type = %s ORDER BY id DESC LIMIT 5',
				$events_table,
				$order_id,
				'offer'
			),
			ARRAY_A
		);
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
