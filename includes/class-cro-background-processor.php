<?php
/**
 * CRO Background Processor
 *
 * Handles heavy tasks in background via WP Cron
 *
 * @package Meyvora_Convert
 */

defined( 'ABSPATH' ) || exit;

class CRO_Background_Processor {

	/** @var string Queue option name */
	const QUEUE_KEY = 'cro_background_queue';

	/**
	 * Initialize cron hooks and schedules
	 */
	public static function init() {
		add_action( 'cro_process_background_queue', array( __CLASS__, 'process_queue' ) );
		add_action( 'cro_cleanup_old_events', array( __CLASS__, 'cleanup_old_events' ) );
		add_action( 'cro_aggregate_daily_stats', array( __CLASS__, 'aggregate_daily_stats' ) );
		add_action( 'cro_check_ab_winners', array( __CLASS__, 'check_ab_winners' ) );

		if ( ! wp_next_scheduled( 'cro_process_background_queue' ) ) {
			wp_schedule_event( time(), 'every_minute', 'cro_process_background_queue' );
		}
		if ( ! wp_next_scheduled( 'cro_cleanup_old_events' ) ) {
			wp_schedule_event( time(), 'daily', 'cro_cleanup_old_events' );
		}
		if ( ! wp_next_scheduled( 'cro_aggregate_daily_stats' ) ) {
			wp_schedule_event( time(), 'daily', 'cro_aggregate_daily_stats' );
		}
		if ( ! wp_next_scheduled( 'cro_check_ab_winners' ) ) {
			wp_schedule_event( time(), 'daily', 'cro_check_ab_winners' );
		}

		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_interval' ) );
	}

	/**
	 * Check running A/B tests for a statistically significant winner and auto-apply if enabled.
	 */
	public static function check_ab_winners() {
		if ( ! class_exists( 'CRO_AB_Test' ) ) {
			return;
		}
		$ab             = new CRO_AB_Test();
		$running_tests  = $ab->get_all_by_status( 'running' );
		foreach ( $running_tests as $test ) {
			$ab->maybe_auto_apply_winner( $test->id );
		}
	}

	/**
	 * Add custom cron interval (every minute)
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function add_cron_interval( $schedules ) {
		$schedules['every_minute'] = array(
			'interval' => 60,
			'display'  => __( 'Every Minute', 'meyvora-convert' ),
		);
		return $schedules;
	}

	/**
	 * Add task to queue
	 *
	 * @param string $task Task name.
	 * @param array  $data Task data.
	 */
	public static function queue( $task, $data = array() ) {
		$queue   = get_option( self::QUEUE_KEY, array() );
		$queue[] = array(
			'task'  => $task,
			'data'  => $data,
			'added' => time(),
		);
		update_option( self::QUEUE_KEY, $queue );
	}

	/**
	 * Process background queue (max 10 items per run)
	 */
	public static function process_queue() {
		$queue = get_option( self::QUEUE_KEY, array() );
		if ( ! is_array( $queue ) ) {
			$queue = array();
		}
		if ( empty( $queue ) ) {
			return;
		}

		$to_process = array_splice( $queue, 0, 10 );
		update_option( self::QUEUE_KEY, $queue );

		foreach ( $to_process as $item ) {
			$task = isset( $item['task'] ) ? $item['task'] : '';
			$data = isset( $item['data'] ) && is_array( $item['data'] ) ? $item['data'] : array();
			self::process_task( $task, $data );
		}
	}

	/**
	 * Process individual task
	 *
	 * @param string $task Task name.
	 * @param array  $data Task data.
	 */
	private static function process_task( $task, $data ) {
		$callback = function () use ( $task, $data ) {
			switch ( $task ) {
				case 'track_event':
					self::task_track_event( $data );
					break;
				case 'send_email':
					self::task_send_email( $data );
					break;
				case 'sync_to_external':
					self::task_sync_to_external( $data );
					break;
				case 'calculate_ab_stats':
					self::task_calculate_ab_stats( $data );
					break;
			}
		};

		if ( class_exists( 'CRO_Error_Handler' ) && method_exists( 'CRO_Error_Handler', 'safe_execute' ) ) {
			CRO_Error_Handler::safe_execute( $callback, null, 'background_task_' . $task );
		} else {
			try {
				$callback();
			} catch ( Exception $e ) {
				if ( class_exists( 'CRO_Error_Handler' ) ) {
					CRO_Error_Handler::log( 'ERROR', $e->getMessage(), array( 'task' => $task ) );
				}
			}
		}
	}

	/**
	 * Task: Track event (insert into cro_events; schema uses source_type, source_id, session_id, order_value)
	 *
	 * @param array $data event_type, campaign_id, visitor_id, page_url, device, revenue.
	 */
	private static function task_track_event( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cro_events';

		$wpdb->insert(
			$table,
			array(
				'event_type'   => isset( $data['type'] ) ? sanitize_text_field( $data['type'] ) : 'impression',
				'source_type'  => 'campaign',
				'source_id'    => isset( $data['campaign_id'] ) ? absint( $data['campaign_id'] ) : null,
				'session_id'   => isset( $data['visitor_id'] ) ? substr( sanitize_text_field( $data['visitor_id'] ), 0, 64 ) : '',
				'page_url'     => isset( $data['page_url'] ) ? substr( sanitize_text_field( $data['page_url'] ), 0, 500 ) : null,
				'device_type'  => isset( $data['device'] ) ? sanitize_text_field( $data['device'] ) : 'desktop',
				'order_value'  => isset( $data['revenue'] ) ? (float) $data['revenue'] : null,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%f', '%s' )
		);
	}

	/**
	 * Task: Send email / fire email captured hook
	 *
	 * @param array $data email, etc.
	 */
	private static function task_send_email( $data ) {
		$email = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
		if ( $email ) {
			do_action( 'cro_email_captured', $email, $data );
		}
	}

	/**
	 * Task: Sync to external analytics
	 *
	 * @param array $data Payload.
	 */
	private static function task_sync_to_external( $data ) {
		do_action( 'cro_sync_external', $data );
	}

	/**
	 * Task: Calculate A/B test stats and fire winner hook if applicable
	 *
	 * @param array $data test_id.
	 */
	private static function task_calculate_ab_stats( $data ) {
		$test_id = isset( $data['test_id'] ) ? absint( $data['test_id'] ) : 0;
		if ( ! $test_id || ! class_exists( 'CRO_AB_Test' ) ) {
			return;
		}
		$ab_model = new CRO_AB_Test();
		$test     = $ab_model->get( $test_id );
		if ( ! $test || $test->status !== 'running' ) {
			return;
		}
		if ( ! class_exists( 'CRO_AB_Statistics' ) ) {
			return;
		}
		$stats = CRO_AB_Statistics::calculate( $test );
		if ( ! empty( $test->auto_apply_winner ) && ! empty( $stats['has_winner'] ) ) {
			do_action( 'cro_ab_test_winner_found', $test, $stats );
		}
	}

	/**
	 * Cleanup old events (keep 90 days), batch delete
	 */
	public static function cleanup_old_events() {
		global $wpdb;
		$table  = $wpdb->prefix . 'cro_events';
		$cutoff = gmdate( 'Y-m-d', strtotime( '-90 days' ) );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s LIMIT 10000",
				$cutoff
			)
		);

		if ( class_exists( 'CRO_Error_Handler' ) ) {
			CRO_Error_Handler::log( 'INFO', 'Cleaned up old events', array(
				'deleted' => $deleted,
				'cutoff'  => $cutoff,
			) );
		}

		if ( $deleted >= 10000 ) {
			wp_schedule_single_event( time() + 60, 'cro_cleanup_old_events' );
		}
	}

	/**
	 * Aggregate daily stats into cro_daily_stats if table exists (events table uses source_id, order_value)
	 */
	public static function aggregate_daily_stats() {
		global $wpdb;
		$events_table = $wpdb->prefix . 'cro_events';
		$stats_table  = $wpdb->prefix . 'cro_daily_stats';

		$stats_exists = $wpdb->get_var( $wpdb->prepare(
			'SHOW TABLES LIKE %s',
			$stats_table
		) );
		if ( $stats_exists !== $stats_table ) {
			return;
		}

		$yesterday = gmdate( 'Y-m-d', strtotime( '-1 day' ) );

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$stats_table} (date, campaign_id, impressions, conversions, revenue, emails)
				SELECT 
					DATE(created_at) AS date,
					source_id AS campaign_id,
					SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) AS impressions,
					SUM(CASE WHEN event_type = 'conversion' THEN 1 ELSE 0 END) AS conversions,
					SUM(CASE WHEN event_type = 'conversion' THEN COALESCE(order_value, 0) ELSE 0 END) AS revenue,
					SUM(CASE WHEN event_type = 'conversion' AND email IS NOT NULL AND email != '' THEN 1 ELSE 0 END) AS emails
				FROM {$events_table}
				WHERE source_type = 'campaign' AND DATE(created_at) = %s
				GROUP BY DATE(created_at), source_id
				ON DUPLICATE KEY UPDATE
					impressions = VALUES(impressions),
					conversions = VALUES(conversions),
					revenue = VALUES(revenue),
					emails = VALUES(emails)",
				$yesterday
			)
		);
	}
}

add_action( 'init', array( 'CRO_Background_Processor', 'init' ), 10 );
