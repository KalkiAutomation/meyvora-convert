<?php
/**
 * A/B Test Model
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class CRO_AB_Test {

	/** @var string */
	private $table;

	/** @var string */
	private $variations_table;

	/** @var string */
	private $assignments_table;

	public function __construct() {
		global $wpdb;
		$this->table             = $wpdb->prefix . 'cro_ab_tests';
		$this->variations_table  = $wpdb->prefix . 'cro_ab_variations';
		$this->assignments_table = $wpdb->prefix . 'cro_ab_assignments';
	}

	/**
	 * Create a new A/B test
	 *
	 * @param array $data Test data.
	 * @return int|WP_Error Test ID or error.
	 */
	public function create( $data ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			$this->table,
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
		$table   = $wpdb->prefix . 'cro_campaigns';
		$campaign = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$campaign_id
		), ARRAY_A );
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
		$inserted = $wpdb->insert(
			$this->variations_table,
			array(
				'test_id'        => $test_id,
				'name'           => sanitize_text_field( $data['name'] ?? '' ),
				'is_control'     => ! empty( $data['is_control'] ) ? 1 : 0,
				'traffic_weight' => absint( $data['traffic_weight'] ?? 50 ),
				'campaign_data'  => $campaign_data,
			),
			array( '%d', '%s', '%d', '%d', '%s' )
		);
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
		$test = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$this->table} WHERE id = %d",
			$test_id
		) );
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
		$table_exists = $wpdb->get_var( $wpdb->prepare(
			"SHOW TABLES LIKE %s",
			$this->variations_table
		) );
		if ( ! $table_exists ) {
			return array();
		}
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$this->variations_table} WHERE test_id = %d ORDER BY is_control DESC, id ASC",
			$test_id
		) );
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

		$table_exists = $wpdb->get_var( $wpdb->prepare(
			"SHOW TABLES LIKE %s",
			$this->table
		) );
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
			$status = sanitize_text_field( $args['status'] );
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$status,
				$limit,
				$offset
			) );
		}

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$this->table} WHERE 1=1 ORDER BY created_at DESC LIMIT %d OFFSET %d",
			$limit,
			$offset
		) );
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
		return $wpdb->update(
			$this->table,
			array(
				'status'     => 'running',
				'started_at' => current_time( 'mysql' ),
			),
			array( 'id' => $test_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
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
		return $wpdb->update(
			$this->table,
			array( 'status' => 'paused' ),
			array( 'id' => $test_id ),
			array( '%s' ),
			array( '%d' )
		);
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
		return $wpdb->update(
			$this->table,
			$data,
			array( 'id' => $test_id ),
			$format,
			array( '%d' )
		);
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
		return $wpdb->update(
			$this->table,
			array(
				'status'               => 'completed',
				'winner_variation_id'  => $winner_variation_id,
				'completed_at'         => current_time( 'mysql' ),
			),
			array( 'id' => $test_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
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
		$wpdb->delete( $this->assignments_table, array( 'test_id' => $test_id ), array( '%d' ) );
		$wpdb->delete( $this->variations_table, array( 'test_id' => $test_id ), array( '%d' ) );
		$result = $wpdb->delete( $this->table, array( 'id' => $test_id ), array( '%d' ) );
		if ( $result !== false ) {
			do_action( 'cro_abtest_deleted', $test_id );
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
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$this->variations_table} SET impressions = impressions + 1 WHERE id = %d",
			$variation_id
		) );
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
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$this->variations_table} SET conversions = conversions + 1, revenue = revenue + %f WHERE id = %d",
			$revenue,
			$variation_id
		) );
		if ( function_exists( 'do_action' ) ) {
			do_action( 'cro_ab_conversion_recorded', $variation_id, $revenue );
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
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$this->table} WHERE original_campaign_id = %d AND status = 'running' LIMIT 1",
			$campaign_id
		) );
	}

	/**
	 * Select variation for visitor (weighted random). Uses cro_ab_assignments for persistence.
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
			return apply_filters( 'cro_abtest_variant_assignment', $variation, $test_id, $visitor_id );
		}
		$total_weight = 0;
		foreach ( $test->variations as $variation ) {
			$total_weight += (int) $variation->traffic_weight;
		}
		$total_weight = max( 1, $total_weight );
		$random       = mt_rand( 1, $total_weight );
		$cumulative   = 0;
		foreach ( $test->variations as $variation ) {
			$cumulative += (int) $variation->traffic_weight;
			if ( $random <= $cumulative ) {
				$this->save_visitor_variation( $test_id, $visitor_id, (int) $variation->id );
				return apply_filters( 'cro_abtest_variant_assignment', $variation, $test_id, $visitor_id );
			}
		}
		$first = $test->variations[0];
		$this->save_visitor_variation( $test_id, $visitor_id, (int) $first->id );
		return apply_filters( 'cro_abtest_variant_assignment', $first, $test_id, $visitor_id );
	}
    
	/**
	 * Get the variation ID assigned to a visitor for a test (from cro_ab_assignments).
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
		$variation_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT variation_id FROM {$this->assignments_table} WHERE test_id = %d AND visitor_id = %s LIMIT 1",
			$test_id,
			$visitor_id
		) );
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
		$result = $wpdb->query( $wpdb->prepare(
			"INSERT INTO {$this->assignments_table} (test_id, visitor_id, variation_id, assigned_at) VALUES (%d, %s, %d, %s)
			ON DUPLICATE KEY UPDATE variation_id = VALUES(variation_id), assigned_at = VALUES(assigned_at)",
			$test_id,
			$visitor_id,
			$variation_id,
			$now
		) );
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
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$this->variations_table} WHERE id = %d",
			$variation_id
		) );
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
		$stats                = new CRO_AB_Statistics();
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
		$campaigns_table = $wpdb->prefix . 'cro_campaigns';
		$original_id    = (int) $test->original_campaign_id;
		$formats        = array_fill( 0, count( $data ), '%s' );
		$updated = $wpdb->update(
			$campaigns_table,
			$data,
			array( 'id' => $original_id ),
			$formats,
			array( '%d' )
		);

		if ( $updated !== false ) {
			$wpdb->update(
				$this->table,
				array( 'winner_variation_id' => $variation_id ),
				array( 'id' => $test_id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		return $updated !== false;
	}
}
