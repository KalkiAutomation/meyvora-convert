<?php
/**
 * CRO Query Optimizer
 *
 * Optimizes database queries for better performance
 *
 * @package Meyvora_Convert
 */
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter

defined( 'ABSPATH' ) || exit;

class CRO_Query_Optimizer {

	/**
	 * Get optimized campaigns for decision engine (cache + PHP pre-filter).
	 *
	 * @param object $context Context with get( key ).
	 * @return array|null Campaign rows, or null if optimization cannot run (caller should load campaigns normally).
	 */
	public static function get_campaigns_for_decision( $context ) {
		if ( ! class_exists( 'CRO_Cache' ) ) {
			return null;
		}

		$campaigns = CRO_Cache::get_active_campaigns();
		if ( empty( $campaigns ) ) {
			return array();
		}

		$page_type = is_object( $context ) && method_exists( $context, 'get' )
			? $context->get( 'page_type' )
			: null;
		$device    = is_object( $context ) && method_exists( $context, 'get' )
			? $context->get( 'device_type' )
			: null;
		$page_type = $page_type !== null ? (string) $page_type : '';
		$device    = $device !== null ? (string) $device : '';

		$out = array();
		foreach ( $campaigns as $campaign ) {
			if ( ! is_array( $campaign ) ) {
				continue;
			}
			if ( ! class_exists( 'CRO_Campaign_Model' ) ) {
				$out[] = $campaign;
				continue;
			}
			$model = CRO_Campaign_Model::from_db_row( $campaign );
			if ( ! $model->is_active() || ! $model->is_within_schedule() ) {
				continue;
			}
			if ( ! self::page_device_prefilter_matches( $model, $page_type, $device ) ) {
				continue;
			}
			$out[] = $campaign;
		}

		return $out;
	}

	/**
	 * Lightweight page/device pre-filter aligned with CRO_Decision_Engine::targeting_rules_for_engine() (must rules only).
	 *
	 * @param CRO_Campaign_Model $model     Campaign model.
	 * @param string             $page_type Current page type from context.
	 * @param string             $device    Current device type from context.
	 * @return bool True if this campaign might still pass full rule evaluation.
	 */
	private static function page_device_prefilter_matches( $model, $page_type, $device ) {
		$tr = isset( $model->targeting_rules ) && is_array( $model->targeting_rules ) ? $model->targeting_rules : array();
		if ( isset( $tr['must'] ) || isset( $tr['should'] ) || isset( $tr['must_not'] ) ) {
			return true;
		}

		$pages = isset( $tr['pages'] ) && is_array( $tr['pages'] ) ? $tr['pages'] : array();
		$include = isset( $pages['include'] ) && is_array( $pages['include'] ) ? $pages['include'] : array();
		$exclude = isset( $pages['exclude'] ) && is_array( $pages['exclude'] ) ? $pages['exclude'] : array();
		$include = array_map( array( __CLASS__, 'map_page_type' ), $include );
		$exclude = array_map( array( __CLASS__, 'map_page_type' ), $exclude );
		$page_mode = isset( $tr['page_mode'] ) ? $tr['page_mode'] : 'all';
		if ( $page_mode === 'include' && ! empty( $include ) ) {
			if ( ! in_array( $page_type, $include, true ) ) {
				return false;
			}
		} elseif ( $page_mode === 'exclude' && ! empty( $exclude ) ) {
			if ( in_array( $page_type, $exclude, true ) ) {
				return false;
			}
		}

		$device_rules = isset( $tr['device'] ) && is_array( $tr['device'] ) ? $tr['device'] : array();
		$allowed      = array();
		if ( ! empty( $device_rules['desktop'] ) ) {
			$allowed[] = 'desktop';
		}
		if ( ! empty( $device_rules['mobile'] ) ) {
			$allowed[] = 'mobile';
		}
		if ( ! empty( $device_rules['tablet'] ) ) {
			$allowed[] = 'tablet';
		}
		if ( ! empty( $allowed ) && ! in_array( $device, $allowed, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Map admin/UI page slugs to engine page_type values (same as CRO_Decision_Engine).
	 *
	 * @param string $page_type Raw page type.
	 * @return string
	 */
	private static function map_page_type( $page_type ) {
		$map = array(
			'category' => 'product_category',
			'blog'     => 'post',
		);
		return isset( $map[ $page_type ] ) ? $map[ $page_type ] : $page_type;
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
		$table = esc_sql( $wpdb->prefix . 'cro_events' );
		foreach ( $events as $event ) {
			$event_type  = isset( $event['event_type'] ) ? sanitize_key( $event['event_type'] ) : '';
			$event_type  = $event_type !== '' ? $event_type : 'impression';
			$device_type = isset( $event['device_type'] ) ? sanitize_key( $event['device_type'] ) : '';
			$device_type = $device_type !== '' ? $device_type : 'desktop';
			$page_url    = isset( $event['page_url'] ) ? esc_url_raw( $event['page_url'] ) : '';

			$wpdb->insert(
				$table,
				array(
					'event_type' => $event_type,
					'source_type'=> 'campaign',
					'source_id'  => isset( $event['campaign_id'] ) ? (int) $event['campaign_id'] : 0,
					'session_id' => isset( $event['visitor_id'] ) ? substr( sanitize_text_field( $event['visitor_id'] ), 0, 64 ) : '',
					'page_url'   => substr( $page_url, 0, 500 ),
					'device_type'=> $device_type,
					'order_value'=> isset( $event['revenue'] ) ? (float) $event['revenue'] : 0.0,
					'created_at' => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%d', '%s', '%s', '%s', '%f', '%s' )
			);
		}
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
			$table = esc_sql( $wpdb->prefix . $table_suffix );

			foreach ( $table_indexes as $index_name => $columns ) {
				$safe_index_name = sanitize_key( $index_name );
				$column_list = array();
				foreach ( explode( ',', $columns ) as $column ) {
					$column_list[] = sanitize_key( trim( $column ) );
				}
				$safe_columns = implode( ', ', $column_list );
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM information_schema.statistics 
						WHERE table_schema = DATABASE() 
						AND table_name = %s 
						AND index_name = %s",
						$table,
						$safe_index_name
					)
				);

				if ( ! $exists ) {
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
					$wpdb->query( "CREATE INDEX {$safe_index_name} ON {$table} ({$safe_columns})" );
				}
			}
		}
	}
}
