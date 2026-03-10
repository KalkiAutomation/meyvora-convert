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
		return $row;
	}

	/**
	 * Get one offer by ID.
	 *
	 * @param int $id Offer ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		$table = self::get_offers_table();
		$row   = CRO_Database::get_row(
			"SELECT * FROM {$table} WHERE id = %d",
			array( absint( $id ) ),
			OBJECT
		);
		return $row ? self::decode_offer_row( $row ) : null;
	}

	/**
	 * Get all offers, optionally filtered by status. Order: priority ASC, id ASC.
	 *
	 * @param string|null $status Optional. 'active', 'inactive', or null for all.
	 * @return array
	 */
	public static function get_all( $status = null ) {
		$table = self::get_offers_table();
		$sql   = "SELECT * FROM {$table}";
		$args  = array();
		if ( $status !== null && $status !== '' ) {
			$sql   .= " WHERE status = %s";
			$args[] = sanitize_text_field( $status );
		}
		$sql .= " ORDER BY priority ASC, id ASC";
		$rows = CRO_Database::get_results( $sql, $args, OBJECT );
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
		$table = self::get_logs_table();
		$limit = absint( $limit );
		$limit = $limit > 0 ? $limit : 100;
		$sql   = "SELECT * FROM {$table}";
		$args  = array();
		if ( $offer_id !== null && $offer_id !== '' ) {
			$sql   .= " WHERE offer_id = %d";
			$args[] = absint( $offer_id );
		}
		$sql .= " ORDER BY created_at DESC LIMIT %d";
		$args[] = $limit;
		$rows = CRO_Database::get_results( $sql, $args, OBJECT );
		return is_array( $rows ) ? $rows : array();
	}
}
