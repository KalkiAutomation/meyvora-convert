<?php
/**
 * Campaign CRUD operations
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Campaign CRUD class.
 */
class CRO_Campaign {

	/**
	 * Get all campaigns.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public static function get_all( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status' => '',
			'type'   => '',
			'limit'  => -1,
			'offset' => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$table_name = $wpdb->prefix . 'cro_campaigns';
		$where      = array( '1=1' );
		$where_args = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]      = 'status = %s';
			$where_args[] = $args['status'];
		}

		if ( ! empty( $args['type'] ) || ! empty( $args['campaign_type'] ) ) {
			$type = ! empty( $args['campaign_type'] ) ? $args['campaign_type'] : $args['type'];
			$where[]      = 'campaign_type = %s';
			$where_args[] = $type;
		}

		$where_clause = implode( ' AND ', $where );

		$query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY created_at DESC";

		if ( $args['limit'] > 0 ) {
			$query .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $args['limit'], $args['offset'] );
		}

		if ( ! empty( $where_args ) ) {
			$query = $wpdb->prepare( $query, $where_args );
		}

		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Get a single campaign by ID.
	 *
	 * @param int $id Campaign ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'cro_campaigns';

		$campaign = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		if ( $campaign ) {
			$campaign['settings']       = maybe_unserialize( $campaign['trigger_settings'] ?? '' );
			$campaign['content']         = maybe_unserialize( $campaign['content'] ?? '' );
			$campaign['styling']         = maybe_unserialize( $campaign['styling'] ?? '' );
			$campaign['targeting_rules'] = maybe_unserialize( $campaign['targeting_rules'] ?? '' );
			$campaign['display_rules']   = maybe_unserialize( $campaign['display_rules'] ?? '' );
			// Backward compatibility.
			$campaign['targeting'] = $campaign['targeting_rules'];
		}

		return $campaign;
	}

	/**
	 * Create a new campaign.
	 *
	 * @param array $data Campaign data.
	 * @return int|false Campaign ID on success, false on failure.
	 */
	public static function create( $data ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'cro_campaigns';

		$defaults = array(
			'name'            => '',
			'campaign_type'   => 'exit_intent',
			'status'          => 'draft',
			'template_type'   => 'centered',
			'trigger_settings'=> array(),
			'content'         => array(),
			'styling'         => array(),
			'targeting_rules' => array(),
			'display_rules'   => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		$template_type = isset( $data['template_type'] ) && $data['template_type'] !== '' ? sanitize_key( $data['template_type'] ) : ( isset( $data['template'] ) && $data['template'] !== '' ? sanitize_key( $data['template'] ) : 'centered' );

		// Sanitize data
		$insert_data = array(
			'name'            => sanitize_text_field( $data['name'] ),
			'campaign_type'   => sanitize_text_field( $data['campaign_type'] ?? 'exit_intent' ),
			'status'           => sanitize_text_field( $data['status'] ),
			'template_type'   => $template_type,
			'trigger_settings'=> maybe_serialize( $data['trigger_settings'] ?? array() ),
			'content'          => maybe_serialize( $data['content'] ?? array() ),
			'styling'         => maybe_serialize( $data['styling'] ?? array() ),
			'targeting_rules' => maybe_serialize( $data['targeting_rules'] ?? array() ),
			'display_rules'   => maybe_serialize( $data['display_rules'] ?? array() ),
		);

		$result = $wpdb->insert( $table_name, $insert_data );

		if ( $result ) {
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Update a campaign.
	 *
	 * @param int   $id   Campaign ID.
	 * @param array $data Campaign data.
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'cro_campaigns';

		$update_data = array();

		if ( isset( $data['name'] ) ) {
			$update_data['name'] = sanitize_text_field( $data['name'] );
		}

		if ( isset( $data['campaign_type'] ) ) {
			$update_data['campaign_type'] = sanitize_text_field( $data['campaign_type'] );
		} elseif ( isset( $data['type'] ) ) {
			$update_data['campaign_type'] = sanitize_text_field( $data['type'] );
		}

		if ( isset( $data['template_type'] ) && $data['template_type'] !== '' ) {
			$update_data['template_type'] = sanitize_key( $data['template_type'] );
		} elseif ( isset( $data['template'] ) && $data['template'] !== '' ) {
			$update_data['template_type'] = sanitize_key( $data['template'] );
		}

		if ( isset( $data['status'] ) ) {
			$update_data['status'] = sanitize_text_field( $data['status'] );
		}

		if ( isset( $data['trigger_settings'] ) ) {
			$update_data['trigger_settings'] = maybe_serialize( $data['trigger_settings'] );
		}

		if ( isset( $data['content'] ) ) {
			$update_data['content'] = maybe_serialize( $data['content'] );
		}

		if ( isset( $data['styling'] ) ) {
			$update_data['styling'] = maybe_serialize( $data['styling'] );
		}

		if ( isset( $data['targeting_rules'] ) ) {
			$update_data['targeting_rules'] = maybe_serialize( $data['targeting_rules'] );
		}

		if ( isset( $data['targeting'] ) ) {
			// Backward compatibility.
			$update_data['targeting_rules'] = maybe_serialize( $data['targeting'] );
		}

		if ( isset( $data['display_rules'] ) ) {
			$update_data['display_rules'] = maybe_serialize( $data['display_rules'] );
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$format = array_fill( 0, count( $update_data ), '%s' );

		$result = $wpdb->update(
			$table_name,
			$update_data,
			array( 'id' => $id ),
			$format,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a campaign.
	 *
	 * @param int $id Campaign ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'cro_campaigns';

		$result = $wpdb->delete(
			$table_name,
			array( 'id' => $id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Duplicate a campaign.
	 *
	 * @param int $id Campaign ID to duplicate.
	 * @return int|WP_Error New campaign ID on success, WP_Error on failure.
	 */
	public static function duplicate_campaign( $id ) {
		global $wpdb;

		$campaign = self::get( $id );

		if ( ! $campaign ) {
			return new WP_Error( 'not_found', __( 'Campaign not found', 'meyvora-convert' ) );
		}

		$table_name = $wpdb->prefix . 'cro_campaigns';

		// Prepare new campaign data.
		$new_data = array(
			'name'              => ( $campaign['name'] ?? __( 'Unnamed Campaign', 'meyvora-convert' ) ) . ' (Copy)',
			'status'            => 'draft',
			'campaign_type'     => $campaign['campaign_type'] ?? $campaign['type'] ?? 'exit_intent',
			'template_type'     => $campaign['template_type'] ?? 'centered',
			'trigger_settings'   => maybe_serialize( $campaign['trigger_settings'] ?? $campaign['settings'] ?? array() ),
			'content'           => maybe_serialize( $campaign['content'] ?? array() ),
			'styling'           => maybe_serialize( $campaign['styling'] ?? array() ),
			'targeting_rules'   => maybe_serialize( $campaign['targeting_rules'] ?? $campaign['targeting'] ?? array() ),
			'display_rules'     => maybe_serialize( $campaign['display_rules'] ?? array() ),
			'impressions'       => 0,
			'conversions'      => 0,
			'revenue_attributed' => 0,
		);

		$result = $wpdb->insert( $table_name, $new_data );

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to duplicate campaign', 'meyvora-convert' ) );
		}

		return $wpdb->insert_id;
	}
}
