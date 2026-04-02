<?php
/**
 * A/B Test Model
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MEYVC_AB_Test {

	/** @var string */
	private $table;

	/** @var string */
	private $variations_table;

	/** @var string */
	private $assignments_table;

	public function __construct() {
		global $wpdb;
		$this->table             = $wpdb->prefix . 'meyvc_ab_tests';
		$this->variations_table  = $wpdb->prefix . 'meyvc_ab_variations';
		$this->assignments_table = $wpdb->prefix . 'meyvc_ab_assignments';
	}

	/**
	 * Invalidate MEYVC_Database table_exists cache for a table after a write.
	 *
	 * @param string $table Full table name.
	 * @return void
	 */
	private function bust_meyvc_table_cache( $table ) {
		if ( class_exists( 'MEYVC_Database' ) ) {
			MEYVC_Database::invalidate_table_cache_after_write( $table );
		}
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'meyvora_meyvc' );
		}
	}

	/**
	 * Create a new A/B test
	 *
	 * @param array $data Test data.
	 * @return int|WP_Error Test ID or error.
	 */
	public function create( $data ) {
		global $wpdb;

		$inserted = $wpdb->insert( $this->table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
			array(
				'name'                  => sanitize_text_field( $data['name'] ?? '' ),
				'original_campaign_id'  => absint( $data['campaign_id'] ?? 0 ),
				'metric'                => sanitize_text_field( $data['metric'] ?? 'conversion_rate' ),
				'min_sample_size'       => absint( $data['min_sample_size'] ?? 200 ),
				'confidence_level'      => absint( $data['confidence_level'] ?? 95 ),
				'auto_apply_winner'     => ! empty( $data['auto_apply_winner'] ) ? 1 : 0,
				'status'                => 'draft',
			),
			array( '%s', '%d', '%s', '%d', '%d', '%d', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'create_failed', __( 'Failed to create A/B test.', 'meyvora-convert' ) );
		}

		$this->bust_meyvc_table_cache( $this->table );

		$test_id = (int) $wpdb->insert_id;
		$campaign_id = absint( $data['campaign_id'] ?? 0 );

		if ( $campaign_id ) {
			$campaign = $this->get_campaign( $campaign_id );
			if ( $campaign ) {
				$this->add_variation( $test_id, array(
					'name'           => 'Control (Original)',
					'is_control'     => true,
					'traffic_weight' => 50,
					'campaign_data'  => $campaign,
				) );
			}
		}

		return $test_id;
	}
    
	/**
	 * Get campaign data as JSON string.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return string|null JSON or null.
	 */
	private function get_campaign( $campaign_id ) {
		global $wpdb;
		$campaign_id = absint( $campaign_id );
		if ( ! $campaign_id ) {
			return null;
		}
		$table      = $wpdb->prefix . 'meyvc_campaigns';
		$cache_key  = 'meyvora_meyvc_' . md5( serialize( array( 'ab_campaign_row_by_id', $table, $campaign_id ) ) );
		$campaign   = wp_cache_get( $cache_key, 'meyvora_meyvc' );
		if ( false === $campaign ) {
			$campaign = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT * FROM %i WHERE id = %d',
					$table,
					$campaign_id
				),
				ARRAY_A
			);
			wp_cache_set( $cache_key, $campaign, 'meyvora_meyvc', 300 );
		}
		return $campaign ? wp_json_encode( $campaign ) : null;
	}

	/**
	 * Add a variation to test
	 *
	 * @param int   $test_id Test ID.
	 * @param array $data    Variation data.
	 * @return int|false Variation ID or false.
	 */
	public function add_variation( $test_id, $data ) {
		global $wpdb;
		$test_id = absint( $test_id );
		if ( ! $test_id ) {
			return false;
		}
		$campaign_data = isset( $data['campaign_data'] ) ? $data['campaign_data'] : '';
		if ( is_array( $campaign_data ) || is_object( $campaign_data ) ) {
			$campaign_data = wp_json_encode( $campaign_data );
		}
		$inserted = $wpdb->insert( $this->variations_table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
			array(
				'test_id'        => $test_id,
				'name'           => sanitize_text_field( $data['name'] ?? '' ),
				'is_control'     => ! empty( $data['is_control'] ) ? 1 : 0,
				'traffic_weight' => absint( $data['traffic_weight'] ?? 50 ),
				'campaign_data'  => $campaign_data,
			),
			array( '%d', '%s', '%d', '%d', '%s' )
		);
		if ( $inserted ) {
			$this->bust_meyvc_table_cache( $this->variations_table );
		}
		return $inserted ? (int) $wpdb->insert_id : false;
	}
    
	/**
	 * Get test by ID
	 *
	 * @param int $test_id Test ID.
	 * @return object|null
	 */
	public function get( $test_id ) {
		global $wpdb;
		$test_id = absint( $test_id );
		if ( ! $test_id ) {
			return null;
		}
		$cache_key = 'meyvora_meyvc_' . md5( serialize( array( 'abtest_by_id', $this->table, $test_id ) ) );
		$test      = wp_cache_get( $cache_key, 'meyvora_meyvc' );
		if ( false === $test ) {
			$test = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT * FROM %i WHERE id = %d',
					$this->table,
					$test_id
				)
			);
			wp_cache_set( $cache_key, $test, 'meyvora_meyvc', 300 );
		}
		if ( $test ) {
			$test->variations = $this->get_variations( $test_id );
		}
		return $test;
	}

	/**
	 * Get variations for a test
	 *
	 * @param int $test_id Test ID.
	 * @return array
	 */
	public function get_variations( $test_id ) {
		global $wpdb;
		$test_id = absint( $test_id );
		if ( ! $test_id ) {
			return array();
		}
		$cache_key_var_exists = 'meyvora_meyvc_' . md5( serialize( array( 'ab_variations_table_exists', $this->variations_table ) ) );
		$table_exists         = wp_cache_get( $cache_key_var_exists, 'meyvora_meyvc' );
		if ( false === $table_exists ) {
			$table_exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SHOW TABLES LIKE %s',
					$this->variations_table
				)
			);
			wp_cache_set( $cache_key_var_exists, $table_exists, 'meyvora_meyvc', 300 );
		}
		if ( ! $table_exists ) {
			return array();
		}
		$cache_key_vars = 'meyvora_meyvc_' . md5( serialize( array( 'ab_variations_by_test', $this->variations_table, $test_id ) ) );
		$rows           = wp_cache_get( $cache_key_vars, 'meyvora_meyvc' );
		if ( false === $rows ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT * FROM %i WHERE test_id = %d ORDER BY is_control DESC, id ASC',
					$this->variations_table,
					$test_id
				)
			);
			if ( ! is_array( $rows ) ) {
				$rows = array();
			}
			wp_cache_set( $cache_key_vars, $rows, 'meyvora_meyvc', 300 );
		} else {
			$rows = is_array( $rows ) ? $rows : array();
		}
		return $rows;
	}
    
	/**
	 * Get all tests with a given status.
	 *
	 * @param string $status Status (e.g. 'running', 'draft', 'completed').
	 * @return array
	 */
	public function get_all_by_status( $status ) {
		return $this->get_all( array( 'status' => $status ) );
	}

	/**
	 * Get all tests
	 *
	 * @param array $args Query args (status, limit, offset).
	 * @return array
	 */
	public function get_all( $args = array() ) {
		global $wpdb;

		$cache_key_tests_exists = 'meyvora_meyvc_' . md5( serialize( array( 'ab_tests_table_exists', $this->table ) ) );
		$table_exists = wp_cache_get( $cache_key_tests_exists, 'meyvora_meyvc' );
		if ( false === $table_exists ) {
			$table_exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SHOW TABLES LIKE %s',
					$this->table
				)
			);
			wp_cache_set( $cache_key_tests_exists, $table_exists, 'meyvora_meyvc', 300 );
		}
		if ( ! $table_exists ) {
			return array();
		}

		$defaults = array(
			'status' => '',
			'limit'  => 20,
			'offset' => 0,
		);
		$args = wp_parse_args( $args, $defaults );
		$limit  = absint( $args['limit'] );
		$offset = absint( $args['offset'] );
		$limit  = $limit > 0 ? $limit : 20;
		$offset = $offset >= 0 ? $offset : 0;

		if ( ! empty( $args['status'] ) ) {
			$status    = sanitize_text_field( $args['status'] );
			$cache_key = 'meyvora_meyvc_' . md5( serialize( array( 'ab_tests_list_by_status', $this->table, $status, $limit, $offset ) ) );
			$result    = wp_cache_get( $cache_key, 'meyvora_meyvc' );
			if ( false === $result ) {
				$result = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
					$wpdb->prepare(
						'SELECT * FROM %i WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d',
						$this->table,
						$status,
						$limit,
						$offset
					)
				);
				if ( ! is_array( $result ) ) {
					$result = array();
				}
				wp_cache_set( $cache_key, $result, 'meyvora_meyvc', 300 );
			} else {
				$result = is_array( $result ) ? $result : array();
			}
			return $result;
		}

		$cache_key = 'meyvora_meyvc_' . md5( serialize( array( 'ab_tests_list_all', $this->table, $limit, $offset ) ) );
		$result    = wp_cache_get( $cache_key, 'meyvora_meyvc' );
		if ( false === $result ) {
			$result = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT * FROM %i WHERE 1=1 ORDER BY created_at DESC LIMIT %d OFFSET %d',
					$this->table,
					$limit,
					$offset
				)
			);
			if ( ! is_array( $result ) ) {
				$result = array();
			}
			wp_cache_set( $cache_key, $result, 'meyvora_meyvc', 300 );
		} else {
			$result = is_array( $result ) ? $result : array();
		}
		return $result;
	}
    
	/**
	 * Start a test
	 *
	 * @param int $test_id Test ID.
	 * @return int|false|WP_Error Rows affected, false, or error.
	 */
	public function start( $test_id ) {
		global $wpdb;
		$test_id = absint( $test_id );
		$test = $this->get( $test_id );
		if ( ! $test || ( isset( $test->variations ) && count( $test->variations ) < 2 ) ) {
			return new WP_Error( 'invalid_test', __( 'Test must have at least 2 variations.', 'meyvora-convert' ) );
		}
		$result = $wpdb->update( $this->table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
			array(
				'status'     => 'running',
				'started_at' => current_time( 'mysql' ),
			),
			array( 'id' => $test_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		if ( false !== $result ) {
			$this->bust_meyvc_table_cache( $this->table );
		}
		return $result;
	}

	/**
	 * Pause a test
	 *
	 * @param int $test_id Test ID.
	 * @return int|false
	 */
	public function pause( $test_id ) {
		global $wpdb;
		$test_id = absint( $test_id );
		$result  = $wpdb->update( $this->table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
			array( 'status' => 'paused' ),
			array( 'id' => $test_id ),
			array( '%s' ),
			array( '%d' )
		);
		if ( false !== $result ) {
			$this->bust_meyvc_table_cache( $this->table );
		}
		return $result;
	}

	/**
	 * Update test status (and set completed_at when status is 'completed').
	 *
	 * @param int    $test_id Test ID.
	 * @param string $status  Status (e.g. 'running', 'paused', 'completed').
	 * @return int|false
	 */
	public function update_status( $test_id, $status ) {
		global $wpdb;
		$test_id = absint( $test_id );
		$status  = sanitize_text_field( $status );
		$data    = array( 'status' => $status );
		$format  = array( '%s' );
		if ( $status === 'completed' ) {
			$data['completed_at'] = current_time( 'mysql' );
			$format[]             = '%s';
		}
		$result = $wpdb->update( $this->table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
			$data,
			array( 'id' => $test_id ),
			$format,
			array( '%d' )
		);
		if ( false !== $result ) {
			$this->bust_meyvc_table_cache( $this->table );
		}
		return $result;
	}

	/**
	 * Complete a test
	 *
	 * @param int      $test_id             Test ID.
	 * @param int|null $winner_variation_id Winner variation ID.
	 * @return int|false
	 */
	public function complete( $test_id, $winner_variation_id = null ) {
		global $wpdb;
		$test_id = absint( $test_id );
		$winner_variation_id = $winner_variation_id !== null ? absint( $winner_variation_id ) : null;
		$result              = $wpdb->update( $this->table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
			array(
				'status'               => 'completed',
				'winner_variation_id'  => $winner_variation_id,
				'completed_at'         => current_time( 'mysql' ),
			),
			array( 'id' => $test_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
		if ( false !== $result ) {
			$this->bust_meyvc_table_cache( $this->table );
		}
		return $result;
	}
    
	/**
	 * Delete an A/B test, its variations, and assignments
	 *
	 * @param int $test_id Test ID.
	 * @return bool
	 */
	public function delete( $test_id ) {
		global $wpdb;
		$test_id = absint( $test_id );
		if ( ! $test_id ) {
			return false;
		}
		$wpdb->delete( $this->assignments_table, array( 'test_id' => $test_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
		$this->bust_meyvc_table_cache( $this->assignments_table );
		$wpdb->delete( $this->variations_table, array( 'test_id' => $test_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
		$this->bust_meyvc_table_cache( $this->variations_table );
		$result = $wpdb->delete( $this->table, array( 'id' => $test_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
		if ( $result !== false ) {
			$this->bust_meyvc_table_cache( $this->table );
			do_action( 'meyvc_abtest_deleted', $test_id );
		}
		return $result !== false;
	}
    
	/**
	 * Record impression for variation
	 *
	 * @param int $variation_id Variation ID.
	 */
	public function record_impression( $variation_id ) {
		global $wpdb;
		$variation_id = absint( $variation_id );
		if ( ! $variation_id ) {
			return;
		}
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
			$wpdb->prepare(
				'UPDATE %i SET impressions = impressions + 1 WHERE id = %d',
				$this->variations_table,
				$variation_id
			) );
		$this->bust_meyvc_table_cache( $this->variations_table );
	}

	/**
	 * Record conversion for variation
	 *
	 * @param int   $variation_id Variation ID.
	 * @param float $revenue      Revenue amount.
	 */
	public function record_conversion( $variation_id, $revenue = 0 ) {
		global $wpdb;
		$variation_id = absint( $variation_id );
		$revenue      = (float) $revenue;
		if ( ! $variation_id ) {
			return;
		}
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
			$wpdb->prepare(
				'UPDATE %i SET conversions = conversions + 1, revenue = revenue + %f WHERE id = %d',
				$this->variations_table,
				$revenue,
				$variation_id
			) );
		$this->bust_meyvc_table_cache( $this->variations_table );
		if ( function_exists( 'do_action' ) ) {
			do_action( 'meyvc_ab_conversion_recorded', $variation_id, $revenue );
		}
	}

	/**
	 * Get active test for campaign
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return object|null
	 */
	public function get_active_for_campaign( $campaign_id ) {
		global $wpdb;
		$campaign_id = absint( $campaign_id );
		if ( ! $campaign_id ) {
			return null;
		}
		$cache_key = 'meyvora_meyvc_' . md5( serialize( array( 'ab_active_test_for_campaign', $this->table, $campaign_id ) ) );
		$row       = wp_cache_get( $cache_key, 'meyvora_meyvc' );
		if ( false === $row ) {
			$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT * FROM %i WHERE original_campaign_id = %d AND status = \'running\' LIMIT 1',
					$this->table,
					$campaign_id
				)
			);
			wp_cache_set( $cache_key, $row, 'meyvora_meyvc', 300 );
		}
		return $row;
	}

	/**
	 * Select variation for visitor (weighted random). Uses meyvc_ab_assignments for persistence.
	 *
	 * @param int    $test_id    Test ID.
	 * @param string $visitor_id Visitor identifier.
	 * @return object|null Variation object or null.
	 */
	public function select_variation( $test_id, $visitor_id ) {
		$test_id = absint( $test_id );
		if ( ! $test_id ) {
			return null;
		}
		$test = $this->get( $test_id );
		if ( ! $test || empty( $test->variations ) ) {
			return null;
		}
		$assigned = $this->get_visitor_variation( $test_id, $visitor_id );
		if ( $assigned ) {
			$variation = $this->get_variation( $assigned );
			return apply_filters( 'meyvc_abtest_variant_assignment', $variation, $test_id, $visitor_id );
		}
		$total_weight = 0;
		foreach ( $test->variations as $variation ) {
			$total_weight += (int) $variation->traffic_weight;
		}
		$total_weight = max( 1, $total_weight );
		$random       = wp_rand( 1, $total_weight );
		$cumulative   = 0;
		foreach ( $test->variations as $variation ) {
			$cumulative += (int) $variation->traffic_weight;
			if ( $random <= $cumulative ) {
				$this->save_visitor_variation( $test_id, $visitor_id, (int) $variation->id );
				return apply_filters( 'meyvc_abtest_variant_assignment', $variation, $test_id, $visitor_id );
			}
		}
		$first = $test->variations[0];
		$this->save_visitor_variation( $test_id, $visitor_id, (int) $first->id );
		return apply_filters( 'meyvc_abtest_variant_assignment', $first, $test_id, $visitor_id );
	}
    
	/**
	 * Get the variation ID assigned to a visitor for a test (from meyvc_ab_assignments).
	 * Uses UNIQUE (test_id, visitor_id) for lookup.
	 *
	 * @param int    $test_id    Test ID.
	 * @param string $visitor_id Visitor identifier (e.g. cookie/session ID).
	 * @return int|null Variation ID or null if not assigned.
	 */
	private function get_visitor_variation( $test_id, $visitor_id ) {
		global $wpdb;
		$test_id    = absint( $test_id );
		$visitor_id = self::sanitize_visitor_id( $visitor_id );
		if ( ! $test_id || $visitor_id === '' ) {
			return null;
		}
		$cache_key     = 'meyvora_meyvc_' . md5( serialize( array( 'ab_visitor_variation_assignment', $this->assignments_table, $test_id, $visitor_id ) ) );
		$variation_id  = wp_cache_get( $cache_key, 'meyvora_meyvc' );
		if ( false === $variation_id ) {
			$variation_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT variation_id FROM %i WHERE test_id = %d AND visitor_id = %s LIMIT 1',
					$this->assignments_table,
					$test_id,
					$visitor_id
				)
			);
			wp_cache_set( $cache_key, $variation_id, 'meyvora_meyvc', 300 );
		}
		return $variation_id !== null ? (int) $variation_id : null;
	}

	/**
	 * Save visitor → variation assignment (one row per test_id + visitor_id).
	 * Uses INSERT ... ON DUPLICATE KEY UPDATE so (test_id, visitor_id) stays unique.
	 *
	 * @param int    $test_id     Test ID.
	 * @param string $visitor_id  Visitor identifier.
	 * @param int    $variation_id Variation ID to assign.
	 * @return bool True on success.
	 */
	private function save_visitor_variation( $test_id, $visitor_id, $variation_id ) {
		global $wpdb;
		$test_id     = absint( $test_id );
		$variation_id = absint( $variation_id );
		$visitor_id  = self::sanitize_visitor_id( $visitor_id );
		if ( ! $test_id || ! $variation_id || $visitor_id === '' ) {
			return false;
		}
		$now = current_time( 'mysql' );
		$result = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
			$wpdb->prepare(
				'INSERT INTO %i (test_id, visitor_id, variation_id, assigned_at) VALUES (%d, %s, %d, %s)
			ON DUPLICATE KEY UPDATE variation_id = VALUES(variation_id), assigned_at = VALUES(assigned_at)',
				$this->assignments_table,
				$test_id,
				$visitor_id,
				$variation_id,
				$now
			) );
		if ( $result !== false ) {
			$this->bust_meyvc_table_cache( $this->assignments_table );
		}
		return $result !== false;
	}

	/**
	 * Sanitize visitor_id for DB (max 64 chars, alphanumeric + common safe chars).
	 *
	 * @param string $visitor_id Raw visitor ID.
	 * @return string Sanitized string, max 64 chars.
	 */
	private static function sanitize_visitor_id( $visitor_id ) {
		if ( ! is_string( $visitor_id ) && ! is_numeric( $visitor_id ) ) {
			return '';
		}
		$visitor_id = sanitize_text_field( (string) $visitor_id );
		$visitor_id = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $visitor_id );
		return substr( $visitor_id, 0, 64 );
	}
    
	/**
	 * Get a single variation by ID
	 *
	 * @param int $variation_id Variation ID.
	 * @return object|null
	 */
	public function get_variation( $variation_id ) {
		global $wpdb;
		$variation_id = absint( $variation_id );
		if ( ! $variation_id ) {
			return null;
		}
		$cache_key = 'meyvora_meyvc_' . md5( serialize( array( 'ab_variation_by_id', $this->variations_table, $variation_id ) ) );
		$row       = wp_cache_get( $cache_key, 'meyvora_meyvc' );
		if ( false === $row ) {
			$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT * FROM %i WHERE id = %d',
					$this->variations_table,
					$variation_id
				)
			);
			wp_cache_set( $cache_key, $row, 'meyvora_meyvc', 300 );
		}
		return $row;
	}

	/**
	 * If the test has auto_apply_winner and a statistically significant winner, apply the winner and mark test completed.
	 *
	 * @param int $test_id Test ID.
	 * @return bool True if winner was applied and test completed.
	 */
	public function maybe_auto_apply_winner( $test_id ) {
		$test = $this->get( $test_id );
		if ( ! $test || empty( $test->auto_apply_winner ) || $test->status !== 'running' ) {
			return false;
		}
		$stats                = new MEYVC_AB_Statistics();
		$winner_variation_id  = $stats->get_winner( $test_id );
		if ( ! $winner_variation_id ) {
			return false;
		}
		$this->apply_variation_as_winner( $test_id, $winner_variation_id );
		$this->update_status( $test_id, 'completed' );
		return true;
	}

	/**
	 * Apply a winning variation's campaign_data to the original campaign and store winner on test.
	 *
	 * @param int $test_id     Test ID.
	 * @param int $variation_id Winning variation ID.
	 * @return bool True on success.
	 */
	public function apply_variation_as_winner( $test_id, $variation_id ) {
		global $wpdb;
		$test_id     = absint( $test_id );
		$variation_id = absint( $variation_id );
		if ( ! $test_id || ! $variation_id ) {
			return false;
		}
		$test = $this->get( $test_id );
		if ( ! $test || empty( $test->original_campaign_id ) ) {
			return false;
		}
		$variation = $this->get_variation( $variation_id );
		if ( ! $variation || empty( $variation->campaign_data ) ) {
			return false;
		}
		$data = json_decode( $variation->campaign_data, true );
		if ( ! is_array( $data ) ) {
			return false;
		}
		unset( $data['id'], $data['created_at'] );
		$campaigns_table = $wpdb->prefix . 'meyvc_campaigns';
		$original_id    = (int) $test->original_campaign_id;
		$formats        = array_fill( 0, count( $data ), '%s' );
		$updated = $wpdb->update( $campaigns_table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
			$data,
			array( 'id' => $original_id ),
			$formats,
			array( '%d' )
		);

		if ( $updated !== false ) {
			$wpdb->update( $this->table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
				array( 'winner_variation_id' => $variation_id ),
				array( 'id' => $test_id ),
				array( '%d' ),
				array( '%d' )
			);
			$this->bust_meyvc_table_cache( $this->table );
			// Mark winning row for reporting (columns added in DB 1.9.0).
			$wpdb->update( $this->variations_table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
				array( 'is_winner' => 0 ),
				array( 'test_id' => $test_id ),
				array( '%d' ),
				array( '%d' )
			);
			$this->bust_meyvc_table_cache( $this->variations_table );
			$wpdb->update( $this->variations_table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
				array(
					'is_winner'         => 1,
					'winner_applied_at' => current_time( 'mysql' ),
				),
				array( 'id' => $variation_id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
			$this->bust_meyvc_table_cache( $this->variations_table );
			$this->bust_meyvc_table_cache( $campaigns_table );
			do_action( 'meyvc_ab_test_winner_applied', $test_id, $variation_id, $original_id );
			self::maybe_email_winner_applied( $test_id, $variation_id, $original_id );
		}

		return $updated !== false;
	}

	/**
	 * Email shop admin when a winning variation is applied to the live campaign.
	 *
	 * @param int $test_id       Test ID.
	 * @param int $variation_id  Variation ID.
	 * @param int $campaign_id   Original campaign ID.
	 */
	private static function maybe_email_winner_applied( $test_id, $variation_id, $campaign_id ) {
		if ( ! function_exists( 'meyvc_settings' ) || ! meyvc_settings()->get( 'analytics', 'ab_winner_email_enabled', true ) ) {
			return;
		}
		$to = meyvc_settings()->get( 'analytics', 'ab_winner_notify_email', '' );
		if ( ! is_string( $to ) || ! is_email( $to ) ) {
			$to = get_option( 'admin_email', '' );
		}
		if ( ! is_email( $to ) ) {
			return;
		}
		$subj = sprintf(
			/* translators: 1: site name */
			__( '[%1$s] A/B test winner applied', 'meyvora-convert' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);
		$body = sprintf(
			/* translators: 1: test id, 2: variation id, 3: campaign id */
			__( 'A/B test #%1$d: winning variation %2$d was applied to campaign #%3$d.', 'meyvora-convert' ),
			(int) $test_id,
			(int) $variation_id,
			(int) $campaign_id
		);
		wp_mail( $to, $subj, $body );
	}
}
