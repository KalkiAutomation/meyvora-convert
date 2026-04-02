<?php
/**
 * Campaign sequences: enroll after conversion, inject next campaign when due.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MEYVC_Sequence_Engine class.
 */
class MEYVC_Sequence_Engine {

	/**
	 * Invalidate Meyvora object-cache reads after writes when group flush is available.
	 *
	 * @return void
	 */
	private static function sequence_flush_read_cache() {
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'meyvora_meyvc' );
		}
	}

	/**
	 * Constructor — hooks only if tables exist.
	 */
	public function __construct() {
		global $wpdb;
		$t            = $wpdb->prefix . 'meyvc_sequence_enrollments';
		$cache_key    = 'meyvora_meyvc_' . md5( serialize( array( 'sequence_enrollments_table_exists', $t ) ) );
		$table_exists = wp_cache_get( $cache_key, 'meyvora_meyvc' );
		if ( false === $table_exists ) {
			$table_exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $t )
			);
			wp_cache_set( $cache_key, $table_exists, 'meyvora_meyvc', 300 );
		}
		if ( $table_exists !== $t ) {
			return;
		}

		add_action( 'meyvc_event_tracked', array( $this, 'on_event_tracked' ), 25, 1 );
		add_filter( 'meyvc_decision_active_campaigns', array( $this, 'inject_pending_campaign' ), 8, 3 );
	}

	/**
	 * @param array $event Event payload from MEYVC_Tracker.
	 */
	public function on_event_tracked( $event ) {
		if ( ! is_array( $event ) ) {
			return;
		}
		$type = isset( $event['event_type'] ) ? sanitize_text_field( (string) $event['event_type'] ) : '';

		if ( 'impression' === $type ) {
			$this->maybe_advance_on_impression( $event );
			return;
		}

		if ( ! in_array( $type, array( 'conversion', 'email_capture', 'email_captured' ), true ) ) {
			return;
		}

		$campaign_id = isset( $event['campaign_id'] ) ? absint( $event['campaign_id'] ) : 0;
		if ( $campaign_id <= 0 ) {
			return;
		}

		$key = class_exists( 'MEYVC_Tracker' ) ? MEYVC_Tracker::client_session_id() : '';
		if ( $key === '' ) {
			return;
		}

		global $wpdb;
		$seq_table = $wpdb->prefix . 'meyvc_campaign_sequences';
		$en_table  = $wpdb->prefix . 'meyvc_sequence_enrollments';

		$cache_key_seq = 'meyvora_meyvc_' . md5( serialize( array( 'sequences_active_by_trigger', $seq_table, 'active', $campaign_id ) ) );
		$sequences     = wp_cache_get( $cache_key_seq, 'meyvora_meyvc' );
		if ( false === $sequences ) {
			$sequences = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT * FROM %i WHERE status = %s AND trigger_campaign_id = %d',
					$seq_table,
					'active',
					$campaign_id
				)
			);
			if ( ! is_array( $sequences ) ) {
				$sequences = array();
			}
			wp_cache_set( $cache_key_seq, $sequences, 'meyvora_meyvc', 300 );
		} else {
			$sequences = is_array( $sequences ) ? $sequences : array();
		}
		if ( ! is_array( $sequences ) ) {
			return;
		}

		foreach ( $sequences as $seq ) {
			$sid = isset( $seq->id ) ? (int) $seq->id : 0;
			if ( ! $sid ) {
				continue;
			}
			$cache_key_ex = 'meyvora_meyvc_' . md5( serialize( array( 'enrollment_id_by_visitor', $en_table, $sid, $key, 'active' ) ) );
			$exists       = wp_cache_get( $cache_key_ex, 'meyvora_meyvc' );
			if ( false === $exists ) {
				$exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
					$wpdb->prepare(
						'SELECT id FROM %i WHERE sequence_id = %d AND visitor_key = %s AND status = %s',
						$en_table,
						$sid,
						$key,
						'active'
					)
				);
				wp_cache_set( $cache_key_ex, $exists, 'meyvora_meyvc', 300 );
			}
			if ( $exists ) {
				continue;
			}
			$steps = isset( $seq->steps ) ? json_decode( (string) $seq->steps, true ) : array();
			if ( ! is_array( $steps ) || empty( $steps ) ) {
				continue;
			}
			$first = $steps[0];
			$next_cid = isset( $first['campaign_id'] ) ? absint( $first['campaign_id'] ) : 0;
			if ( ! $next_cid ) {
				continue;
			}
			$delay = isset( $first['delay_seconds'] ) ? max( 0, (int) $first['delay_seconds'] ) : 0;
			$run_at = gmdate( 'Y-m-d H:i:s', time() + $delay );

			$ins = $wpdb->insert( $en_table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
				array(
					'sequence_id'         => $sid,
					'visitor_key'         => $key,
					'step_index'          => 0,
					'next_run_at'         => $run_at,
					'pending_campaign_id' => $next_cid,
					'status'              => 'active',
				),
				array( '%d', '%s', '%d', '%s', '%d', '%s' )
			);
			if ( false !== $ins ) {
				if ( class_exists( 'MEYVC_Database' ) ) {
					MEYVC_Database::invalidate_table_cache_after_write( $en_table );
				}
				self::sequence_flush_read_cache();
			}
		}
	}

	/**
	 * When the pending campaign is shown, advance to the next step.
	 *
	 * @param array $event Event.
	 */
	private function maybe_advance_on_impression( array $event ) {
		$campaign_id = isset( $event['campaign_id'] ) ? absint( $event['campaign_id'] ) : 0;
		if ( $campaign_id <= 0 ) {
			return;
		}
		$key = class_exists( 'MEYVC_Tracker' ) ? MEYVC_Tracker::client_session_id() : '';
		if ( $key === '' ) {
			return;
		}

		global $wpdb;
		$en_table  = $wpdb->prefix . 'meyvc_sequence_enrollments';
		$seq_table = $wpdb->prefix . 'meyvc_campaign_sequences';

		$cache_key_row = 'meyvora_meyvc_' . md5( serialize( array( 'enrollment_pending_impression', $en_table, $seq_table, 'active', $key, 'active', $campaign_id ) ) );
		$row           = wp_cache_get( $cache_key_row, 'meyvora_meyvc' );
		if ( false === $row ) {
			$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT e.* FROM %i e
			INNER JOIN %i s ON s.id = e.sequence_id AND s.status = %s
			WHERE e.visitor_key = %s AND e.status = %s AND e.pending_campaign_id = %d
			LIMIT 1',
					$en_table,
					$seq_table,
					'active',
					$key,
					'active',
					$campaign_id
				)
			);
			wp_cache_set( $cache_key_row, $row, 'meyvora_meyvc', 300 );
		}

		if ( ! $row || empty( $row->id ) ) {
			return;
		}

		$cache_key_seq_row = 'meyvora_meyvc_' . md5( serialize( array( 'campaign_sequence_by_id', $seq_table, (int) $row->sequence_id ) ) );
		$seq               = wp_cache_get( $cache_key_seq_row, 'meyvora_meyvc' );
		if ( false === $seq ) {
			$seq = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $seq_table, (int) $row->sequence_id )
			);
			wp_cache_set( $cache_key_seq_row, $seq, 'meyvora_meyvc', 300 );
		}
		if ( ! $seq ) {
			return;
		}
		$steps = isset( $seq->steps ) ? json_decode( (string) $seq->steps, true ) : array();
		if ( ! is_array( $steps ) ) {
			$steps = array();
		}
		$next_index = (int) $row->step_index + 1;
		if ( $next_index >= count( $steps ) ) {
			$upd = $wpdb->update( $en_table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
				array( 'status' => 'completed', 'pending_campaign_id' => 0 ),
				array( 'id' => (int) $row->id ),
				array( '%s', '%d' ),
				array( '%d' )
			);
			if ( false !== $upd ) {
				if ( class_exists( 'MEYVC_Database' ) ) {
					MEYVC_Database::invalidate_table_cache_after_write( $en_table );
				}
				self::sequence_flush_read_cache();
			}
			return;
		}
		$next_step = $steps[ $next_index ];
		$next_cid  = isset( $next_step['campaign_id'] ) ? absint( $next_step['campaign_id'] ) : 0;
		$delay     = isset( $next_step['delay_seconds'] ) ? max( 0, (int) $next_step['delay_seconds'] ) : 0;
		$run_at    = gmdate( 'Y-m-d H:i:s', time() + $delay );

		$upd2 = $wpdb->update( $en_table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
			array(
				'step_index'          => $next_index,
				'pending_campaign_id' => $next_cid,
				'next_run_at'         => $run_at,
			),
			array( 'id' => (int) $row->id ),
			array( '%d', '%d', '%s' ),
			array( '%d' )
		);
		if ( false !== $upd2 ) {
			if ( class_exists( 'MEYVC_Database' ) ) {
				MEYVC_Database::invalidate_table_cache_after_write( $en_table );
			}
			self::sequence_flush_read_cache();
		}
	}

	/**
	 * Prepend due sequence campaign so it competes in the normal decision pipeline.
	 *
	 * @param MEYVC_Campaign_Model[] $campaigns     Active campaigns.
	 * @param MEYVC_Context           $context       Context.
	 * @param MEYVC_Visitor_State     $visitor_state Visitor.
	 * @return MEYVC_Campaign_Model[]
	 */
	public function inject_pending_campaign( $campaigns, $context, $visitor_state ) {
		unset( $context, $visitor_state );
		if ( ! is_array( $campaigns ) ) {
			return $campaigns;
		}
		$key = class_exists( 'MEYVC_Tracker' ) ? MEYVC_Tracker::client_session_id() : '';
		if ( $key === '' || ! class_exists( 'MEYVC_Campaign' ) || ! class_exists( 'MEYVC_Campaign_Model' ) ) {
			return $campaigns;
		}

		global $wpdb;
		$en_table  = $wpdb->prefix . 'meyvc_sequence_enrollments';
		$seq_table = $wpdb->prefix . 'meyvc_campaign_sequences';

		$now = current_time( 'mysql', true );
		$cache_key_due = 'meyvora_meyvc_' . md5( serialize( array( 'enrollment_due_inject', $en_table, $seq_table, 'active', $key, 'active', $now ) ) );
		$row           = wp_cache_get( $cache_key_due, 'meyvora_meyvc' );
		if ( false === $row ) {
			$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT e.* FROM %i e
			INNER JOIN %i s ON s.id = e.sequence_id AND s.status = %s
			WHERE e.visitor_key = %s AND e.status = %s AND e.pending_campaign_id > 0 AND e.next_run_at <= %s
			ORDER BY e.next_run_at ASC LIMIT 1',
					$en_table,
					$seq_table,
					'active',
					$key,
					'active',
					$now
				)
			);
			wp_cache_set( $cache_key_due, $row, 'meyvora_meyvc', 300 );
		}

		if ( ! $row || empty( $row->pending_campaign_id ) ) {
			return $campaigns;
		}

		$cid = (int) $row->pending_campaign_id;
		$db  = MEYVC_Campaign::get( $cid );
		if ( ! is_array( $db ) || empty( $db['id'] ) ) {
			return $campaigns;
		}
		$model = MEYVC_Campaign_Model::from_db_row( $db );
		if ( ! $model->is_active() || ! $model->is_within_schedule() ) {
			return $campaigns;
		}

		array_unshift( $campaigns, $model );
		return $campaigns;
	}

	/**
	 * Admin: list sequences.
	 *
	 * @return object[]
	 */
	public static function get_all() {
		global $wpdb;
		$t = $wpdb->prefix . 'meyvc_campaign_sequences';
		$cache_key_te = 'meyvora_meyvc_' . md5( serialize( array( 'campaign_sequences_table_exists', $t ) ) );
		$table_exists = wp_cache_get( $cache_key_te, 'meyvora_meyvc' );
		if ( false === $table_exists ) {
			$table_exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $t )
			);
			wp_cache_set( $cache_key_te, $table_exists, 'meyvora_meyvc', 300 );
		}
		if ( $table_exists !== $t ) {
			return array();
		}
		$cache_key_all = 'meyvora_meyvc_' . md5( serialize( array( 'campaign_sequences_all_rows', $t ) ) );
		$rows          = wp_cache_get( $cache_key_all, 'meyvora_meyvc' );
		if ( false === $rows ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC', $t )
			);
			if ( ! is_array( $rows ) ) {
				$rows = array();
			}
			wp_cache_set( $cache_key_all, $rows, 'meyvora_meyvc', 300 );
		} else {
			$rows = is_array( $rows ) ? $rows : array();
		}
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Save sequence from admin.
	 *
	 * @param array $data name, trigger_campaign_id, steps_json, status, id optional.
	 * @return int|false ID or false.
	 */
	public static function save( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meyvc_campaign_sequences';
		$name  = sanitize_text_field( $data['name'] ?? '' );
		if ( $name === '' ) {
			return false;
		}
		$trigger = absint( $data['trigger_campaign_id'] ?? 0 );
		$status  = sanitize_key( $data['status'] ?? 'draft' );
		if ( ! in_array( $status, array( 'draft', 'active', 'paused' ), true ) ) {
			$status = 'draft';
		}
		$steps_raw = isset( $data['steps_json'] ) ? wp_unslash( (string) $data['steps_json'] ) : '[]';
		$steps     = json_decode( $steps_raw, true );
		if ( ! is_array( $steps ) ) {
			return false;
		}
		$steps_enc = wp_json_encode( $steps );

		$id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		if ( $id ) {
			$upd = $wpdb->update( $table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
				array(
					'name'                => $name,
					'status'              => $status,
					'trigger_campaign_id' => $trigger,
					'steps'               => $steps_enc,
				),
				array( 'id' => $id ),
				array( '%s', '%s', '%d', '%s' ),
				array( '%d' )
			);
			if ( false !== $upd ) {
				if ( class_exists( 'MEYVC_Database' ) ) {
					MEYVC_Database::invalidate_table_cache_after_write( $table );
				}
				self::sequence_flush_read_cache();
			}
			return $id;
		}

		$ins = $wpdb->insert( $table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
			array(
				'name'                => $name,
				'status'              => $status,
				'trigger_campaign_id' => $trigger,
				'steps'               => $steps_enc,
			),
			array( '%s', '%s', '%d', '%s' )
		);
		if ( false !== $ins ) {
			if ( class_exists( 'MEYVC_Database' ) ) {
				MEYVC_Database::invalidate_table_cache_after_write( $table );
			}
			self::sequence_flush_read_cache();
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete sequence and enrollments.
	 *
	 * @param int $id Sequence ID.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = absint( $id );
		if ( ! $id ) {
			return;
		}
		$en_table = $wpdb->prefix . 'meyvc_sequence_enrollments';
		$seq_tbl  = $wpdb->prefix . 'meyvc_campaign_sequences';
		$wpdb->delete( $en_table, array( 'sequence_id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
		if ( class_exists( 'MEYVC_Database' ) ) {
			MEYVC_Database::invalidate_table_cache_after_write( $en_table );
		}
		$wpdb->delete( $seq_tbl, array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
		if ( class_exists( 'MEYVC_Database' ) ) {
			MEYVC_Database::invalidate_table_cache_after_write( $seq_tbl );
		}
		self::sequence_flush_read_cache();
	}
}
