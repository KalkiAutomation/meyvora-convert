<?php
/**
 * AJAX Handlers
 *
 * Handles AJAX requests for product/page search, campaigns list, etc.
 *
 * @package Meyvora_Convert
 */
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CRO_Ajax class.
 *
 * AJAX handler for searching products and pages, getting campaigns list.
 */
class CRO_Ajax {

	/**
	 * Constructor. Register AJAX actions.
	 */
	public function __construct() {
		add_action( 'wp_ajax_cro_search_products', array( $this, 'search_products' ) );
		add_action( 'wp_ajax_cro_search_pages', array( $this, 'search_pages' ) );
		add_action( 'wp_ajax_cro_get_campaigns', array( $this, 'get_campaigns' ) );
		add_action( 'wp_ajax_cro_save_campaign', array( $this, 'save_campaign' ) );
	}

	/**
	 * Recursively sanitize array values (scalars: sanitize_text_field; nested arrays: recurse).
	 *
	 * @param mixed $data Data to sanitize (array or scalar).
	 * @return mixed Sanitized data.
	 */
	private function sanitize_array_recursive( $data ) {
		if ( ! is_array( $data ) ) {
			if ( is_int( $data ) || is_float( $data ) ) {
				return $data;
			}
			return sanitize_text_field( (string) $data );
		}
		$out = array();
		foreach ( $data as $k => $v ) {
			$key = is_string( $k ) ? sanitize_key( $k ) : $k;
			$out[ $key ] = $this->sanitize_array_recursive( $v );
		}
		return $out;
	}

	/**
	 * Search products for Select2
	 */
	public function search_products() {
		check_ajax_referer( 'cro_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'meyvora-convert' ) ) );
		}

		$search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';

		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => 20,
			's'              => $search,
			'post_status'    => 'publish',
		);

		$query  = new WP_Query( $args );
		$results = array();

		foreach ( $query->posts as $post ) {
			$results[] = array(
				'id'   => (int) $post->ID,
				'text' => sanitize_text_field( $post->post_title ),
			);
		}

		wp_send_json_success( $results );
	}

	/**
	 * Search pages for Select2
	 */
	public function search_pages() {
		check_ajax_referer( 'cro_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'meyvora-convert' ) ) );
		}

		$search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';

		$args = array(
			'post_type'      => array( 'page', 'post', 'product' ),
			'posts_per_page' => 20,
			's'              => $search,
			'post_status'    => 'publish',
		);

		$query   = new WP_Query( $args );
		$results = array();

		foreach ( $query->posts as $post ) {
			$post_type_obj = get_post_type_object( $post->post_type );
			$type_label    = $post_type_obj && isset( $post_type_obj->labels->singular_name ) ? $post_type_obj->labels->singular_name : ucfirst( $post->post_type );
			$results[]     = array(
				'id'   => (int) $post->ID,
				'text' => sprintf( '[%s] %s', esc_html( $type_label ), sanitize_text_field( $post->post_title ) ),
			);
		}

		wp_send_json_success( $results );
	}

	/**
	 * Get campaigns list for dropdowns
	 */
	public function get_campaigns() {
		check_ajax_referer( 'cro_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'meyvora-convert' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'cro_campaigns';

		// Verify table exists (basic check)
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			wp_send_json_success( array() );
		}

		$campaigns = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name, status FROM {$table} ORDER BY name ASC"
			)
		);

		$results = array();
		if ( is_array( $campaigns ) ) {
			foreach ( $campaigns as $campaign ) {
				$results[] = array(
					'id'   => isset( $campaign->id ) ? (int) $campaign->id : 0,
					'text' => sprintf(
						'%s (%s)',
						esc_html( isset( $campaign->name ) ? $campaign->name : '' ),
						esc_html( isset( $campaign->status ) ? $campaign->status : 'draft' )
					),
				);
			}
		}

		wp_send_json_success( $results );
	}

	/**
	 * Save campaign (create or update) from Visual Campaign Builder.
	 */
	public function save_campaign() {
		check_ajax_referer( 'cro_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'meyvora-convert' ) ) );
		}

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( wp_unslash( $_POST['campaign_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw         = isset( $_POST['data'] ) && is_string( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : '';
		$data        = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid campaign data', 'meyvora-convert' ) ) );
		}

		$data = $this->sanitize_array_recursive( $data );

		$name            = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';
		$status          = isset( $data['status'] ) ? sanitize_text_field( (string) $data['status'] ) : 'draft';
		$campaign_type   = isset( $data['type'] ) ? sanitize_text_field( (string) $data['type'] ) : ( isset( $data['template'] ) ? sanitize_text_field( (string) $data['template'] ) : 'exit_intent' );
		$template_type   = isset( $data['template'] ) ? sanitize_key( (string) $data['template'] ) : '';
		$trigger_rules   = isset( $data['trigger_rules'] ) && is_array( $data['trigger_rules'] ) ? $data['trigger_rules'] : array();
		$content         = isset( $data['content'] ) && is_array( $data['content'] ) ? $data['content'] : array();
		$styling         = isset( $data['styling'] ) && is_array( $data['styling'] ) ? $data['styling'] : array();
		$targeting_rules = isset( $data['targeting_rules'] ) && is_array( $data['targeting_rules'] ) ? $data['targeting_rules'] : array();
		$frequency_rules = isset( $data['frequency_rules'] ) && is_array( $data['frequency_rules'] ) ? $data['frequency_rules'] : array();
		$schedule        = isset( $data['schedule'] ) && is_array( $data['schedule'] ) ? $data['schedule'] : array();
		$brand_override  = isset( $data['brand_styles_override'] ) && is_array( $data['brand_styles_override'] ) ? $data['brand_styles_override'] : null;

		if ( ! empty( $schedule ) && isset( $targeting_rules['schedule'] ) === false ) {
			$targeting_rules['schedule'] = $schedule;
		}

		$display_rules = $frequency_rules;
		if ( $brand_override && ! empty( $brand_override['use'] ) ) {
			$display_rules['brand_styles_override'] = $brand_override;
		}

		$payload = array(
			'name'              => $name,
			'status'             => $status,
			'campaign_type'      => $campaign_type,
			'template_type'      => $template_type,
			'trigger_settings'   => $trigger_rules,
			'content'            => $content,
			'styling'            => $styling,
			'targeting_rules'    => $targeting_rules,
			'display_rules'      => $display_rules,
			'fallback_id'        => isset( $data['fallback_id'] ) ? absint( $data['fallback_id'] ) : 0,
			'fallback_delay_seconds' => isset( $data['fallback_delay_seconds'] ) ? min( 300, max( 0, (int) $data['fallback_delay_seconds'] ) ) : 5,
		);

		if ( ! class_exists( 'CRO_Campaign' ) ) {
			wp_send_json_error( array( 'message' => __( 'Campaign module not available', 'meyvora-convert' ) ) );
		}

		if ( $campaign_id ) {
			$ok = CRO_Campaign::update( $campaign_id, $payload );
			if ( ! $ok ) {
				wp_send_json_error( array( 'message' => __( 'Update failed', 'meyvora-convert' ) ) );
			}
			wp_send_json_success( array( 'id' => $campaign_id ) );
		}

		$new_id = CRO_Campaign::create( $payload );
		if ( $new_id === false ) {
			wp_send_json_error( array( 'message' => __( 'Create failed', 'meyvora-convert' ) ) );
		}
		wp_send_json_success( array( 'id' => (int) $new_id ) );
	}
}

// Initialize when file is loaded (loader requires this file).
new CRO_Ajax();
