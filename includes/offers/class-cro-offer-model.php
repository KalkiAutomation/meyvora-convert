<?php
/**
 * Offer model: CRUD for cro_offers and audit log for cro_offer_logs.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CRO_Offer_Model class.
 */
class CRO_Offer_Model {

	/**
	 * Decoded row for instance helpers (conflict API).
	 *
	 * @var object
	 */
	private $row;

	/**
	 * Use CRO_Offer_Model::from_row( $row ) for conflict helpers.
	 *
	 * @param object $row Row from get() / get_all().
	 */
	private function __construct( $row ) {
		$this->row = is_object( $row ) ? $row : (object) array();
	}

	/**
	 * Wrap a DB offer row for instance methods (get_conflict_ids, has_conflict_with).
	 *
	 * @param object $row Row from CRO_Offer_Model::get() or list entry.
	 * @return self
	 */
	public static function from_row( $row ) {
		return new self( $row );
	}

	/**
	 * Conflict offer IDs stored on this row (decoded from conflict_ids JSON).
	 *
	 * @return int[]
	 */
	public function get_conflict_ids() {
		return self::normalize_conflict_ids_on_row( $this->row );
	}

	/**
	 * Whether this offer lists another offer ID as a conflict.
	 *
	 * @param int $other_id Other offer ID.
	 * @return bool
	 */
	public function has_conflict_with( $other_id ) {
		$other_id = (int) $other_id;
		if ( $other_id <= 0 ) {
			return false;
		}
		return in_array( $other_id, $this->get_conflict_ids(), true );
	}

	/**
	 * True if either offer lists the other in conflict_ids (bidirectional).
	 *
	 * @param int $id_a Offer ID.
	 * @param int $id_b Offer ID.
	 * @return bool
	 */
	public static function get_conflicting_pair( $id_a, $id_b ) {
		$id_a = (int) $id_a;
		$id_b = (int) $id_b;
		if ( $id_a <= 0 || $id_b <= 0 || $id_a === $id_b ) {
			return false;
		}
		$row_a = self::get( $id_a );
		$row_b = self::get( $id_b );
		if ( ! $row_a || ! $row_b ) {
			return false;
		}
		$wa = self::from_row( $row_a );
		$wb = self::from_row( $row_b );
		return $wa->has_conflict_with( $id_b ) || $wb->has_conflict_with( $id_a );
	}

	/**
	 * Normalize conflict_ids on a row object to a list of positive integers.
	 *
	 * @param object $row DB row.
	 * @return int[]
	 */
	public static function normalize_conflict_ids_on_row( $row ) {
		if ( ! is_object( $row ) || ! isset( $row->conflict_ids ) ) {
			return array();
		}
		$raw = $row->conflict_ids;
		if ( is_array( $raw ) ) {
			return array_values( array_filter( array_map( 'absint', $raw ) ) );
		}
		if ( is_string( $raw ) && $raw !== '' ) {
			$decoded = json_decode( $raw, true );
			return is_array( $decoded ) ? array_values( array_filter( array_map( 'absint', $decoded ) ) ) : array();
		}
		return array();
	}

	/**
	 * Offers table name (full, with prefix).
	 *
	 * @return string
	 */
	public static function get_offers_table() {
		return CRO_Database::get_table( 'offers' );
	}

	/**
	 * Offer logs table name (full, with prefix).
	 *
	 * @return string
	 */
	public static function get_logs_table() {
		return CRO_Database::get_table( 'offer_logs' );
	}

	/**
	 * Decode JSON columns on a single offer row.
	 *
	 * @param object $row Row from DB.
	 * @return object
	 */
	private static function decode_offer_row( $row ) {
		if ( ! is_object( $row ) ) {
			return $row;
		}
		foreach ( array( 'conditions_json', 'reward_json', 'usage_rules_json' ) as $col ) {
			if ( isset( $row->$col ) && is_string( $row->$col ) ) {
				$decoded = json_decode( $row->$col, true );
				$row->$col = $decoded !== null ? $decoded : array();
			}
		}
		if ( isset( $row->conflict_ids ) && is_string( $row->conflict_ids ) && $row->conflict_ids !== '' ) {
			$decoded = json_decode( $row->conflict_ids, true );
			$row->conflict_ids = is_array( $decoded ) ? array_values( array_filter( array_map( 'absint', $decoded ) ) ) : array();
		} elseif ( ! isset( $row->conflict_ids ) || $row->conflict_ids === null || $row->conflict_ids === '' ) {
			$row->conflict_ids = array();
		} elseif ( isset( $row->conflict_ids ) && ! is_array( $row->conflict_ids ) ) {
			$row->conflict_ids = array();
		} elseif ( isset( $row->conflict_ids ) && is_array( $row->conflict_ids ) ) {
			$row->conflict_ids = array_values( array_filter( array_map( 'absint', $row->conflict_ids ) ) );
		}
		return $row;
	}

	/**
	 * Get one offer by ID.
	 *
	 * @param int $id Offer ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = self::get_offers_table();
		$oid   = absint( $id );
		$cache_key   = 'meyvora_cro_' . md5( serialize( array( 'offer_row_by_id', $table, $oid ) ) );
		$cached_row  = wp_cache_get( $cache_key, 'meyvora_cro' );
		if ( false === $cached_row ) {
			$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT * FROM %i WHERE id = %d',
					$table,
					$oid
				),
				OBJECT
			);
			wp_cache_set( $cache_key, $row, 'meyvora_cro', 300 );
		} else {
			$row = is_object( $cached_row ) ? $cached_row : null;
		}
		return $row ? self::decode_offer_row( $row ) : null;
	}

	/**
	 * Get all offers, optionally filtered by status. Order: priority ASC, id ASC.
	 *
	 * @param string|null $status Optional. 'active', 'inactive', or null for all.
	 * @return array
	 */
	public static function get_all( $status = null ) {
		global $wpdb;

		$table = self::get_offers_table();
		if ( $status !== null && $status !== '' ) {
			$status_s  = sanitize_text_field( $status );
			$cache_key = 'meyvora_cro_' . md5( serialize( array( 'offer_list_by_status', $table, $status_s ) ) );
			$rows      = wp_cache_get( $cache_key, 'meyvora_cro' );
			if ( false === $rows ) {
				$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
					$wpdb->prepare(
						'SELECT * FROM %i WHERE status = %s ORDER BY priority ASC, id ASC',
						$table,
						$status_s
					),
					OBJECT
				);
				wp_cache_set( $cache_key, $rows, 'meyvora_cro', 300 );
			}
		} else {
			$cache_key = 'meyvora_cro_' . md5( serialize( array( 'offer_list_all_ordered', $table ) ) );
			$rows      = wp_cache_get( $cache_key, 'meyvora_cro' );
			if ( false === $rows ) {
				$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
					$wpdb->prepare(
						'SELECT * FROM %i ORDER BY priority ASC, id ASC',
						$table
					),
					OBJECT
				);
				wp_cache_set( $cache_key, $rows, 'meyvora_cro', 300 );
			}
		}
		if ( ! is_array( $rows ) ) {
			return array();
		}
		return array_map( array( __CLASS__, 'decode_offer_row' ), $rows );
	}

	/**
	 * Get active offers sorted by priority (ascending), then id.
	 *
	 * @return array
	 */
	public static function get_active() {
		return self::get_all( 'active' );
	}

	/**
	 * Create an offer.
	 *
	 * @param array $data Keys: name, status (optional, default inactive), priority (optional), conditions_json (array/object), reward_json (array/object), usage_rules_json (array/object).
	 * @return int|false Insert ID or false.
	 */
	public static function create( $data ) {
		$table = self::get_offers_table();
		$defaults = array(
			'name'              => '',
			'status'            => 'inactive',
			'priority'           => 10,
			'conditions_json'   => array(),
			'reward_json'       => array(),
			'usage_rules_json'  => array(),
			'conflict_ids'      => array(),
		);
		$data = wp_parse_args( $data, $defaults );
		$data['name']    = sanitize_text_field( $data['name'] );
		$data['status']  = in_array( $data['status'], array( 'active', 'inactive' ), true ) ? $data['status'] : 'inactive';
		$data['priority'] = (int) $data['priority'];
		// CRO_Database::insert/sanitize_data will encode arrays to JSON.
		return CRO_Database::insert( $table, $data );
	}

	/**
	 * Update an offer by ID.
	 *
	 * @param int   $id   Offer ID.
	 * @param array $data Keys to update (name, status, priority, conditions_json, reward_json, usage_rules_json). JSON fields can be array/object (will be encoded).
	 * @return int|false Rows affected or false.
	 */
	public static function update( $id, $data ) {
		$table = self::get_offers_table();
		$id    = absint( $id );
		if ( $id === 0 ) {
			return false;
		}
		if ( isset( $data['name'] ) ) {
			$data['name'] = sanitize_text_field( $data['name'] );
		}
		if ( isset( $data['status'] ) ) {
			$data['status'] = in_array( $data['status'], array( 'active', 'inactive' ), true ) ? $data['status'] : 'inactive';
		}
		if ( isset( $data['priority'] ) ) {
			$data['priority'] = (int) $data['priority'];
		}
		// CRO_Database::update/sanitize_data will encode arrays to JSON.
		return CRO_Database::update( $table, $data, array( 'id' => $id ), null, array( '%d' ) );
	}

	/**
	 * Delete an offer by ID.
	 *
	 * @param int $id Offer ID.
	 * @return int|false Rows affected or false.
	 */
	public static function delete( $id ) {
		$table = self::get_offers_table();
		$id    = absint( $id );
		if ( $id === 0 ) {
			return false;
		}
		return CRO_Database::delete( $table, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Insert an offer log entry (audit for generated coupons).
	 *
	 * @param int         $offer_id    Offer ID.
	 * @param string|null $visitor_id  Visitor/session ID.
	 * @param int|null    $user_id     User ID if logged in.
	 * @param string|null $coupon_code Generated coupon code.
	 * @param string|null $applied_at  Datetime when applied (Y-m-d H:i:s) or null.
	 * @param int|null    $order_id    Order ID when applied, or null.
	 * @return int|false Insert ID or false.
	 */
	public static function log_insert( $offer_id, $visitor_id = null, $user_id = null, $coupon_code = null, $applied_at = null, $order_id = null ) {
		$table = self::get_logs_table();
		$data  = array(
			'offer_id'    => absint( $offer_id ),
			'visitor_id'  => $visitor_id !== null ? sanitize_text_field( $visitor_id ) : null,
			'user_id'     => $user_id !== null ? absint( $user_id ) : null,
			'coupon_code' => $coupon_code !== null ? sanitize_text_field( $coupon_code ) : null,
			'applied_at'  => $applied_at,
			'order_id'    => $order_id !== null ? absint( $order_id ) : null,
		);
		$result = CRO_Database::insert( $table, $data );
		if ( $result && function_exists( 'do_action' ) ) {
			do_action( 'cro_offer_log_inserted', $offer_id );
		}
		return $result;
	}

	/**
	 * Get log entries for an offer (or all). Newest first.
	 *
	 * @param int|null $offer_id Optional. Filter by offer ID.
	 * @param int      $limit    Max rows. Default 100.
	 * @return array
	 */
	public static function get_logs( $offer_id = null, $limit = 100 ) {
		global $wpdb;

		$table = self::get_logs_table();
		$limit = absint( $limit );
		$limit = $limit > 0 ? $limit : 100;
		if ( $offer_id !== null && $offer_id !== '' ) {
			$oid       = absint( $offer_id );
			$cache_key = 'meyvora_cro_' . md5( serialize( array( 'offer_logs_by_offer_id', $table, $oid, $limit ) ) );
			$rows      = wp_cache_get( $cache_key, 'meyvora_cro' );
			if ( false === $rows ) {
				$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
					$wpdb->prepare(
						'SELECT * FROM %i WHERE offer_id = %d ORDER BY created_at DESC LIMIT %d',
						$table,
						$oid,
						$limit
					),
					OBJECT
				);
				wp_cache_set( $cache_key, $rows, 'meyvora_cro', 300 );
			}
		} else {
			$cache_key = 'meyvora_cro_' . md5( serialize( array( 'offer_logs_recent_all', $table, $limit ) ) );
			$rows      = wp_cache_get( $cache_key, 'meyvora_cro' );
			if ( false === $rows ) {
				$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
					$wpdb->prepare(
						'SELECT * FROM %i ORDER BY created_at DESC LIMIT %d',
						$table,
						$limit
					),
					OBJECT
				);
				wp_cache_set( $cache_key, $rows, 'meyvora_cro', 300 );
			}
		}
		return is_array( $rows ) ? $rows : array();
	}
}
