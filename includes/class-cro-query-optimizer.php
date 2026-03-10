<?php
/**
 * CRO Query Optimizer
 *
 * Optimizes database queries for better performance
 *
 * @package Meyvora_Convert
 */

defined( 'ABSPATH' ) || exit;

class CRO_Query_Optimizer {

	/**
	 * Get optimized campaigns for decision engine (cache + PHP pre-filter).
	 *
	 * @param object $context Context with get( key ).
	 * @return array Campaign rows (array format).
	 */
	public static function get_campaigns_for_decision( $context ) {
		if ( ! class_exists( 'CRO_Cache' ) ) {
			return array();
		}

		$campaigns = CRO_Cache::get_active_campaigns();
		if ( empty( $campaigns ) ) {
			return array();
		}

		$page_type = is_object( $context ) && method_exists( $context, 'get' ) ? $context->get( 'page.type' ) : null;
		$device    = is_object( $context ) && method_exists( $context, 'get' ) ? $context->get( 'device.type' ) : null;

		return array_filter( $campaigns, function ( $campaign ) use ( $page_type, $device ) {
			if ( ! self::is_within_schedule( $campaign ) ) {
				return false;
			}
			return true;
		} );
	}

	/**
	 * Check if campaign is within its schedule.
	 *
	 * @param array $campaign Campaign row (with schedule JSON).
	 * @return bool
	 */
	private static function is_within_schedule( $campaign ) {
		$schedule = json_decode( $campaign['schedule'] ?? '{}', true );
		if ( ! is_array( $schedule ) ) {
			$schedule = array();
		}

		if ( empty( $schedule['enabled'] ) ) {
			return true;
		}

		$now = (int) current_time( 'timestamp' );

		if ( ! empty( $schedule['start_date'] ) ) {
			$start = strtotime( $schedule['start_date'] );
			if ( $start !== false && $now < $start ) {
				return false;
			}
		}

		if ( ! empty( $schedule['end_date'] ) ) {
			$end = strtotime( $schedule['end_date'] . ' 23:59:59' );
			if ( $end !== false && $now > $end ) {
				return false;
			}
		}

		if ( ! empty( $schedule['days_of_week'] ) && is_array( $schedule['days_of_week'] ) ) {
			$today = (int) date( 'w' );
			if ( ! in_array( $today, $schedule['days_of_week'], true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Batch insert events. Event keys: event_type, campaign_id (→ source_id), visitor_id (→ session_id), page_url, device_type, revenue (→ order_value).
	 * Table uses source_type, source_id, session_id, order_value.
	 *
	 * @param array $events Array of event arrays.
	 */
	public static function batch_insert_events( $events ) {
		if ( empty( $events ) || ! is_array( $events ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'cro_events';

		$values       = array();
		$placeholders = array();

		foreach ( $events as $event ) {
			$placeholders[] = "(%s, %s, %d, %s, %s, %s, %f, %s)";
			$values[]       = $event['event_type'] ?? 'impression';
			$values[]       = 'campaign';
			$values[]       = isset( $event['campaign_id'] ) ? (int) $event['campaign_id'] : 0;
			$values[]       = isset( $event['visitor_id'] ) ? substr( sanitize_text_field( $event['visitor_id'] ), 0, 64 ) : '';
			$values[]       = isset( $event['page_url'] ) ? substr( sanitize_text_field( $event['page_url'] ), 0, 500 ) : '';
			$values[]       = isset( $event['device_type'] ) ? sanitize_text_field( $event['device_type'] ) : 'desktop';
			$values[]       = isset( $event['revenue'] ) ? (float) $event['revenue'] : 0.0;
			$values[]       = current_time( 'mysql' );
		}

		$sql = "INSERT INTO {$table} (event_type, source_type, source_id, session_id, page_url, device_type, order_value, created_at) VALUES " . implode( ', ', $placeholders );
		$wpdb->query( $wpdb->prepare( $sql, $values ) );
	}

	/**
	 * Optimized analytics summary (single query, date range).
	 *
	 * @param string $date_from Y-m-d.
	 * @param string $date_to   Y-m-d.
	 * @return array impressions, conversions, revenue, emails.
	 */
	public static function get_analytics_summary( $date_from, $date_to ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cro_events';

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					COUNT(CASE WHEN event_type = 'impression' THEN 1 END) AS impressions,
					COUNT(CASE WHEN event_type = 'conversion' THEN 1 END) AS conversions,
					COALESCE(SUM(CASE WHEN event_type = 'conversion' THEN order_value END), 0) AS revenue,
					COUNT(CASE WHEN event_type = 'conversion' AND email IS NOT NULL AND email != '' THEN 1 END) AS emails
				FROM {$table}
				WHERE created_at BETWEEN %s AND %s",
				$date_from . ' 00:00:00',
				$date_to . ' 23:59:59'
			),
			ARRAY_A
		);

		return is_array( $result ) ? $result : array(
			'impressions' => 0,
			'conversions' => 0,
			'revenue'     => 0,
			'emails'      => 0,
		);
	}

	/**
	 * Add database indexes if missing (matches actual table columns).
	 */
	public static function ensure_indexes() {
		global $wpdb;

		$indexes = array(
			'cro_events'   => array(
				'idx_event_type'   => 'event_type',
				'idx_source_id'    => 'source_id',
				'idx_created_at'   => 'created_at',
				'idx_composite'    => 'event_type, created_at',
			),
			'cro_campaigns' => array(
				'idx_status'   => 'status',
				'idx_priority' => 'priority',
			),
		);

		foreach ( $indexes as $table_suffix => $table_indexes ) {
			$table = $wpdb->prefix . $table_suffix;

			foreach ( $table_indexes as $index_name => $columns ) {
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM information_schema.statistics 
						WHERE table_schema = DATABASE() 
						AND table_name = %s 
						AND index_name = %s",
						$table,
						$index_name
					)
				);

				if ( ! $exists ) {
					$wpdb->query( "CREATE INDEX {$index_name} ON {$table} ({$columns})" );
				}
			}
		}
	}
}
