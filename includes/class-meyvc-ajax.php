<?php
/**
 * AJAX Handlers
 *
 * Handles AJAX requests for product/page search, campaigns list, etc.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MEYVC_Ajax class.
 *
 * AJAX handler for searching products and pages, getting campaigns list.
 */
class MEYVC_Ajax {

	/**
	 * @return void
	 */
	private static function meyvc_ajax_flush_read_cache() {
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'meyvora_meyvc' );
		}
	}

	/**
	 * Constructor. Register AJAX actions.
	 */
	public function __construct() {
		add_action( 'wp_ajax_meyvc_search_products', array( $this, 'search_products' ) );
		add_action( 'wp_ajax_meyvc_search_pages', array( $this, 'search_pages' ) );
		add_action( 'wp_ajax_meyvc_get_campaigns', array( $this, 'get_campaigns' ) );
		add_action( 'wp_ajax_meyvc_save_campaign', array( $this, 'save_campaign' ) );
		add_action( 'wp_ajax_meyvc_onboarding_configure', array( $this, 'onboarding_configure' ) );
		add_action( 'wp_ajax_meyvc_onboarding_save_state', array( $this, 'onboarding_save_state' ) );
		add_action( 'wp_ajax_meyvc_onboarding_finish', array( $this, 'onboarding_finish' ) );
		add_action( 'wp_ajax_meyvc_load_ai_panel_data', array( $this, 'load_ai_panel_data' ) );
		add_action( 'wp_ajax_meyvc_preview_campaign', array( $this, 'preview_campaign' ) );
		add_action( 'wp_ajax_meyvc_apply_industry_pack', array( $this, 'apply_industry_pack' ) );
		add_action( 'wp_ajax_meyvc_spin_init', array( $this, 'spin_init' ) );
		add_action( 'wp_ajax_nopriv_meyvc_spin_init', array( $this, 'spin_init' ) );
		add_action( 'wp_ajax_meyvc_spin_capture', array( $this, 'spin_capture' ) );
		add_action( 'wp_ajax_nopriv_meyvc_spin_capture', array( $this, 'spin_capture' ) );
		add_action( 'wp_ajax_meyvc_live_stats', array( $this, 'live_stats' ) );
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
		check_ajax_referer( 'meyvc_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'meyvora-convert' ) ) );
		}

		$search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';

		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => 20,
			's'              => $search,
			'post_status'    => 'publish',
			'no_found_rows'  => true,
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
		check_ajax_referer( 'meyvc_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'meyvora-convert' ) ) );
		}

		$search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';

		$args = array(
			'post_type'      => array( 'page', 'post', 'product' ),
			'posts_per_page' => 20,
			's'              => $search,
			'post_status'    => 'publish',
			'no_found_rows'  => true,
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
		check_ajax_referer( 'meyvc_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'meyvora-convert' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'meyvc_campaigns';

		if ( ! class_exists( 'MEYVC_Database' ) || ! MEYVC_Database::table_exists( $table ) ) {
			wp_send_json_success( array() );
		}

		$cache_key = 'meyvora_meyvc_' . md5( serialize( array( 'ajax_campaigns_dropdown_list', $table ) ) );
		$campaigns = wp_cache_get( $cache_key, 'meyvora_meyvc' );
		if ( false === $campaigns ) {
			$campaigns = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT id, name, status FROM %i ORDER BY name ASC',
					$table
				)
			);
			wp_cache_set( $cache_key, $campaigns, 'meyvora_meyvc', 300 );
		}
		if ( ! is_array( $campaigns ) ) {
			$campaigns = array();
		}

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
		check_ajax_referer( 'meyvc_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'meyvora-convert' ) ) );
		}

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( wp_unslash( $_POST['campaign_id'] ) ) : 0;
		$raw      = isset( $_POST['data'] ) && is_string( $_POST['data'] ) ? sanitize_textarea_field( wp_unslash( $_POST['data'] ) ) : '';
		$wheel_slices_post = isset( $_POST['wheel_slices'] ) && is_string( $_POST['wheel_slices'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wheel_slices'] ) ) : '';
		// Decode campaign data; all fields are sanitised individually before DB insertion below.
		$data_raw = json_decode( $raw, true );
		if ( ! is_array( $data_raw ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid campaign data', 'meyvora-convert' ) ) );
		}

		// Trigger type from raw JSON (before sanitize_key / sanitize_text_field on nested keys) — authoritative for campaign_type + trigger_settings.type.
		$trigger_slugs = array( 'exit_intent', 'mobile_exit', 'scroll', 'time', 'inactivity', 'click', 'page_load' );
		$tr_type_direct = '';
		if ( isset( $data_raw['trigger_rules'] ) && is_array( $data_raw['trigger_rules'] ) && isset( $data_raw['trigger_rules']['type'] ) ) {
			$tr_type_direct = sanitize_text_field( (string) $data_raw['trigger_rules']['type'] );
		}

		$data = $this->sanitize_array_recursive( $data_raw );

		$name          = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';
		$status        = isset( $data['status'] ) ? sanitize_text_field( (string) $data['status'] ) : 'draft';
		$trigger_rules = isset( $data['trigger_rules'] ) && is_array( $data['trigger_rules'] ) ? $data['trigger_rules'] : array();
		// DB campaign_type must reflect trigger. Prefer unsanitized-path trigger_rules.type, then sanitized, then top-level type.
		$campaign_type = '';
		if ( $tr_type_direct !== '' ) {
			if ( in_array( $tr_type_direct, $trigger_slugs, true ) ) {
				$campaign_type = $tr_type_direct;
			} elseif ( 'scroll_trigger' === $tr_type_direct ) {
				$campaign_type = 'scroll';
			} elseif ( 'time_trigger' === $tr_type_direct ) {
				$campaign_type = 'time';
			}
		}
		if ( $campaign_type === '' && ! empty( $trigger_rules['type'] ) ) {
			$tr_type = sanitize_text_field( (string) $trigger_rules['type'] );
			if ( in_array( $tr_type, $trigger_slugs, true ) ) {
				$campaign_type = $tr_type;
			} elseif ( 'scroll_trigger' === $tr_type ) {
				$campaign_type = 'scroll';
			} elseif ( 'time_trigger' === $tr_type ) {
				$campaign_type = 'time';
			}
		}
		$raw_type = isset( $data['type'] ) ? sanitize_text_field( (string) $data['type'] ) : '';
		if ( $campaign_type === '' && $raw_type !== '' && in_array( $raw_type, $trigger_slugs, true ) ) {
			$campaign_type = $raw_type;
		} elseif ( $campaign_type === '' && 'scroll_trigger' === $raw_type ) {
			$campaign_type = 'scroll';
		} elseif ( $campaign_type === '' && 'time_trigger' === $raw_type ) {
			$campaign_type = 'time';
		}
		if ( $campaign_type === '' || ! in_array( $campaign_type, $trigger_slugs, true ) ) {
			$campaign_type = 'exit_intent';
		}
		$trigger_rules['type'] = $campaign_type;
		$template_type = isset( $data['template'] ) ? sanitize_key( (string) $data['template'] ) : '';
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

		if ( ! class_exists( 'MEYVC_Campaign' ) ) {
			wp_send_json_error( array( 'message' => __( 'Campaign module not available', 'meyvora-convert' ) ) );
		}

		$save_response = array(
			'id'            => 0,
			'campaign_type' => $campaign_type,
			'trigger_rules' => $trigger_rules,
		);

		if ( $campaign_id ) {
			$ok = MEYVC_Campaign::update( $campaign_id, $payload );
			if ( ! $ok ) {
				wp_send_json_error( array( 'message' => __( 'Update failed', 'meyvora-convert' ) ) );
			}
			$save_response['id'] = $campaign_id;
			$this->save_wheel_slices_from_post( $campaign_id, $wheel_slices_post );
			wp_send_json_success( $save_response );
		}

		$new_id = MEYVC_Campaign::create( $payload );
		if ( $new_id === false ) {
			wp_send_json_error( array( 'message' => __( 'Create failed', 'meyvora-convert' ) ) );
		}
		$save_response['id'] = (int) $new_id;
		$this->save_wheel_slices_from_post( (int) $new_id, $wheel_slices_post );
		wp_send_json_success( $save_response );
	}

	/**
	 * Persist wheel_slices JSON from builder POST (gamified-wheel).
	 *
	 * @param int    $campaign_id       Campaign ID.
	 * @param string $wheel_slices_raw JSON from POST (already unslashed + sanitized by caller).
	 */
	private function save_wheel_slices_from_post( int $campaign_id, string $wheel_slices_raw = '' ): void {
		if ( $campaign_id <= 0 || $wheel_slices_raw === '' ) {
			return;
		}
		// Decode wheel slices; each slice label and color are sanitised below.
		$decoded = json_decode( $wheel_slices_raw, true );
		if ( ! is_array( $decoded ) || empty( $decoded ) ) {
			return;
		}
		$clean = array();
		foreach ( $decoded as $slice ) {
			if ( ! is_array( $slice ) ) {
				continue;
			}
			$type  = isset( $slice['type'] ) && in_array( $slice['type'], array( 'win', 'lose' ), true ) ? $slice['type'] : 'lose';
			$color = isset( $slice['color'] ) ? sanitize_hex_color( (string) $slice['color'] ) : '#e5e7eb';
			$clean[] = array(
				'label' => isset( $slice['label'] ) ? sanitize_text_field( (string) $slice['label'] ) : '',
				'type'  => $type,
				'color' => $color ? $color : '#e5e7eb',
			);
		}
		if ( empty( $clean ) ) {
			return;
		}
		global $wpdb;
		$campaigns = $wpdb->prefix . 'meyvc_campaigns';
		$updated   = $wpdb->update( $campaigns, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
			array( 'wheel_slices' => wp_json_encode( $clean ) ),
			array( 'id' => $campaign_id ),
			array( '%s' ),
			array( '%d' )
		);
		if ( false !== $updated ) {
			if ( class_exists( 'MEYVC_Database' ) ) {
				MEYVC_Database::invalidate_table_cache_after_write( $campaigns );
			}
			self::meyvc_ajax_flush_read_cache();
		}
	}

	/**
	 * Save wizard UI state (step) to wp_options.
	 */
	public function onboarding_save_state() {
		check_ajax_referer( 'meyvc_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'meyvora-convert' ) ) );
		}

		$raw = isset( $_POST['state'] ) && is_string( $_POST['state'] ) ? sanitize_textarea_field( wp_unslash( $_POST['state'] ) ) : '';
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid state', 'meyvora-convert' ) ) );
		}
		$clean = array(
			'step' => isset( $decoded['step'] ) ? min( 4, max( 1, (int) $decoded['step'] ) ) : 1,
		);
		if ( isset( $decoded['goal'] ) ) {
			$clean['goal'] = sanitize_key( (string) $decoded['goal'] );
		}
		if ( isset( $decoded['profile'] ) && is_array( $decoded['profile'] ) ) {
			$clean['profile'] = array(
				'store_type'       => isset( $decoded['profile']['store_type'] ) ? sanitize_key( (string) $decoded['profile']['store_type'] ) : '',
				'aov_range'        => isset( $decoded['profile']['aov_range'] ) ? sanitize_key( (string) $decoded['profile']['aov_range'] ) : '',
				'monthly_visitors' => isset( $decoded['profile']['monthly_visitors'] ) ? sanitize_key( (string) $decoded['profile']['monthly_visitors'] ) : '',
			);
		}
		update_option( 'meyvc_onboarding_wizard_state', wp_json_encode( $clean ), false );
		wp_send_json_success( array( 'saved' => true ) );
	}

	/**
	 * Enable features and create starter campaigns from onboarding selections.
	 */
	public function onboarding_configure() {
		check_ajax_referer( 'meyvc_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'meyvora-convert' ) ) );
		}

		$goal = isset( $_POST['goal'] ) ? sanitize_key( wp_unslash( $_POST['goal'] ) ) : '';
		$allowed_goals = array( 'recover_abandoned', 'grow_email', 'increase_aov', 'reduce_checkout' );
		if ( ! in_array( $goal, $allowed_goals, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid goal', 'meyvora-convert' ) ) );
		}

		$profile_raw = isset( $_POST['profile'] ) && is_string( $_POST['profile'] ) ? sanitize_textarea_field( wp_unslash( $_POST['profile'] ) ) : '';
		$profile_in  = json_decode( $profile_raw, true );
		$profile     = is_array( $profile_in ) ? $profile_in : array();
		$profile     = array(
			'store_type'       => isset( $profile['store_type'] ) ? sanitize_key( (string) $profile['store_type'] ) : '',
			'aov_range'        => isset( $profile['aov_range'] ) ? sanitize_key( (string) $profile['aov_range'] ) : '',
			'monthly_visitors' => isset( $profile['monthly_visitors'] ) ? sanitize_key( (string) $profile['monthly_visitors'] ) : '',
		);

		if ( class_exists( 'MEYVC_Onboarding' ) ) {
			MEYVC_Onboarding::save_profile_option( $goal, $profile );
			$result = MEYVC_Onboarding::configure( $goal, $profile );
		} else {
			$result = array( 'features' => array(), 'campaigns' => array() );
		}

		$labels = array();
		foreach ( $result['features'] as $f ) {
			$labels[] = $f;
		}
		foreach ( $result['campaigns'] as $c ) {
			if ( ! empty( $c['name'] ) ) {
				$labels[] = sprintf(
					/* translators: %s: campaign name */
					__( 'Campaign: %s', 'meyvora-convert' ),
					$c['name']
				);
			}
		}

		wp_send_json_success(
			array(
				'configured' => $labels,
				'raw'        => $result,
			)
		);
	}

	/**
	 * Mark onboarding complete after wizard step 4.
	 */
	public function onboarding_finish() {
		check_ajax_referer( 'meyvc_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'meyvora-convert' ) ) );
		}

		if ( function_exists( 'meyvc_mark_onboarding_complete' ) ) {
			meyvc_mark_onboarding_complete();
		}

		wp_send_json_success(
			array(
				'redirect' => admin_url( 'admin.php?page=meyvora-convert&onboarding_done=1' ),
			)
		);
	}

	/**
	 * Lazy-load AI sidebar panel: top rule-based insight + AI status (non-blocking for page render).
	 */
	public function load_ai_panel_data() {
		check_ajax_referer( 'meyvc_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'meyvora-convert' ) ) );
		}

		$from_cache = false;
		$insight    = null;
		if ( class_exists( 'MEYVC_Insights' ) ) {
			$key        = 'meyvc_insights_' . MEYVC_Insights::DAYS . 'd';
			$from_cache = false !== get_transient( $key );
			$cards      = MEYVC_Insights::get_insights( MEYVC_Insights::DAYS );
			if ( ! empty( $cards[0] ) && is_array( $cards[0] ) ) {
				$c = $cards[0];
				$insight = array(
					'title'       => isset( $c['title'] ) ? $c['title'] : '',
					'description' => isset( $c['description'] ) ? $c['description'] : '',
					'fix_url'     => isset( $c['fix_url'] ) ? $c['fix_url'] : '',
					'fix_label'   => isset( $c['fix_label'] ) ? $c['fix_label'] : __( 'Apply', 'meyvora-convert' ),
				);
			}
		}

		$ai_ok = class_exists( 'MEYVC_AI_Client' ) && MEYVC_AI_Client::is_configured();
		$usage = '';
		if ( class_exists( 'MEYVC_AI_Rate_Limiter' ) && get_current_user_id() > 0 ) {
			$rem = MEYVC_AI_Rate_Limiter::get_remaining( 'copy_generate', 30 );
			$usage = sprintf(
				/* translators: 1: remaining AI calls in rolling window, 2: window label */
				__( '%1$d copy generations left in the current hour window.', 'meyvora-convert' ),
				$rem
			);
		}

		wp_send_json_success(
			array(
				'top_insight' => $insight,
				'from_cache'  => $from_cache,
				'ai_ready'    => (bool) $ai_ok,
				'usage_note'  => $usage,
			)
		);
	}

	/**
	 * Return server-rendered campaign HTML for builder live preview.
	 */
	public function preview_campaign() {
		check_ajax_referer( 'meyvc_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'meyvora-convert' ) ) );
		}
		$raw = isset( $_POST['data'] ) && is_string( $_POST['data'] ) ? sanitize_textarea_field( wp_unslash( $_POST['data'] ) ) : '';
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data', 'meyvora-convert' ) ) );
		}
		if ( ! class_exists( 'MEYVC_Templates' ) ) {
			wp_send_json_error( array( 'message' => __( 'Templates not loaded', 'meyvora-convert' ) ) );
		}
		$html = MEYVC_Templates::render_campaign_preview_html( $this->sanitize_array_recursive( $data ) );
		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Apply an industry pack (multiple presets).
	 */
	public function apply_industry_pack() {
		check_ajax_referer( 'meyvc_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'meyvora-convert' ) ) );
		}
		$pack_id = isset( $_POST['pack_id'] ) ? sanitize_key( wp_unslash( $_POST['pack_id'] ) ) : '';
		if ( ! class_exists( 'MEYVC_Presets' ) ) {
			wp_send_json_error( array( 'message' => __( 'Presets unavailable', 'meyvora-convert' ) ) );
		}
		$result = MEYVC_Presets::apply_industry_pack( $pack_id );
		if ( empty( $result['success'] ) ) {
			wp_send_json_error( array( 'message' => isset( $result['message'] ) ? $result['message'] : __( 'Apply failed', 'meyvora-convert' ) ) );
		}
		wp_send_json_success( $result );
	}

	/**
	 * Initialize spin wheel: server picks winning slice index and returns HMAC token (public AJAX).
	 */
	public function spin_init(): void {
		check_ajax_referer( 'meyvc_public_actions', 'nonce' );

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( wp_unslash( $_POST['campaign_id'] ) ) : 0;

		if ( class_exists( 'MEYVC_Security' ) ) {
			$rl_key = 'meyvc_spin_init_' . md5( MEYVC_Security::get_client_ip() . '|' . $campaign_id );
			if ( ! MEYVC_Security::check_rate_limit( $rl_key, 5, 3600 ) ) {
				wp_send_json_error( array( 'message' => __( 'Too many attempts. Please try again later.', 'meyvora-convert' ) ) );
			}
		}

		$slices = array_values( $this->get_wheel_slices( $campaign_id ) );
		if ( empty( $slices ) ) {
			wp_send_json_error( array( 'message' => __( 'Wheel not configured.', 'meyvora-convert' ) ) );
		}

		$win_indices = array_keys(
			array_filter(
				$slices,
				static function ( $s ) {
					return is_array( $s ) && ( $s['type'] ?? 'lose' ) === 'win';
				}
			)
		);
		$seed = crc32( (string) $campaign_id . '|' . ( class_exists( 'MEYVC_Security' ) ? MEYVC_Security::get_client_ip() : '' ) . '|' . gmdate( 'YmdH' ) );
		$winning_index = ! empty( $win_indices ) ? $win_indices[ abs( (int) ( $seed % count( $win_indices ) ) ) ] : 0;

		$hour    = gmdate( 'YmdH' );
		$secret  = wp_salt( 'auth' );
		$payload = $campaign_id . '|' . $winning_index . '|' . $hour;
		$token   = hash_hmac( 'sha256', $payload, $secret );

		wp_send_json_success(
			array(
				'slices'        => $slices,
				'winning_index' => $winning_index,
				'token'         => $token,
				'hour'          => $hour,
			)
		);
	}

	/**
	 * Wheel slices for a campaign (DB override or defaults).
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array<int, array<string, string>>
	 */
	private function get_wheel_slices( int $campaign_id ): array {
		$defaults = array(
			array( 'label' => '10% off', 'type' => 'win', 'color' => '#2563eb' ),
			array( 'label' => 'Try again', 'type' => 'lose', 'color' => '#e5e7eb' ),
			array( 'label' => '5% off', 'type' => 'win', 'color' => '#7c3aed' ),
			array( 'label' => 'Try again', 'type' => 'lose', 'color' => '#e5e7eb' ),
			array( 'label' => 'Free ship', 'type' => 'win', 'color' => '#059669' ),
			array( 'label' => 'Try again', 'type' => 'lose', 'color' => '#e5e7eb' ),
		);
		if ( $campaign_id > 0 ) {
			global $wpdb;
			$table     = $wpdb->prefix . 'meyvc_campaigns';
			$cache_key = 'meyvora_meyvc_' . md5( serialize( array( 'ajax_wheel_slices_json_column', $table, $campaign_id ) ) );
			$raw       = wp_cache_get( $cache_key, 'meyvora_meyvc' );
			if ( false === $raw ) {
				$raw = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
					$wpdb->prepare( 'SELECT wheel_slices FROM %i WHERE id = %d', $table, $campaign_id )
				);
				wp_cache_set( $cache_key, $raw, 'meyvora_meyvc', 300 );
			}
			if ( $raw ) {
				$decoded = json_decode( (string) $raw, true );
				if ( is_array( $decoded ) && ! empty( $decoded ) ) {
					return $decoded;
				}
			}
		}

		return (array) apply_filters( 'meyvc_wheel_slices', $defaults, $campaign_id );
	}

	/**
	 * Spin-to-win email capture and optional coupon (public AJAX). Requires valid HMAC from spin_init.
	 */
	public function spin_capture(): void {
		check_ajax_referer( 'meyvc_public_actions', 'nonce' );

		$email       = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( wp_unslash( $_POST['campaign_id'] ) ) : 0;
		$token       = isset( $_POST['spin_token'] ) ? sanitize_text_field( wp_unslash( $_POST['spin_token'] ) ) : '';
		$hour        = isset( $_POST['spin_hour'] ) ? sanitize_text_field( wp_unslash( $_POST['spin_hour'] ) ) : '';
		$win_index   = isset( $_POST['win_index'] ) ? absint( wp_unslash( $_POST['win_index'] ) ) : -1;
		$slice_label = isset( $_POST['slice_label'] ) ? sanitize_text_field( wp_unslash( $_POST['slice_label'] ) ) : '';

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid email.', 'meyvora-convert' ) ) );
		}

		if ( class_exists( 'MEYVC_Security' ) ) {
			$rl_key = 'meyvc_spin_cap_' . md5( $email . '|' . MEYVC_Security::get_client_ip() );
			if ( ! MEYVC_Security::check_rate_limit( $rl_key, 3, 3600 ) ) {
				wp_send_json_error( array( 'message' => __( 'Too many attempts.', 'meyvora-convert' ) ) );
			}
		}

		$secret           = wp_salt( 'auth' );
		$expected_payload = $campaign_id . '|' . $win_index . '|' . $hour;
		$expected_token   = hash_hmac( 'sha256', $expected_payload, $secret );

		if ( ! hash_equals( $expected_token, $token ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid spin token.', 'meyvora-convert' ) ) );
		}

		$token_dt = \DateTime::createFromFormat( 'YmdH', $hour, new \DateTimeZone( 'UTC' ) );
		if ( ! $token_dt || ( time() - $token_dt->getTimestamp() ) > 7200 ) {
			wp_send_json_error( array( 'message' => __( 'Spin token expired. Please refresh and try again.', 'meyvora-convert' ) ) );
		}

		$slices = $this->get_wheel_slices( $campaign_id );
		$slices = array_values( $slices );
		$is_win = isset( $slices[ $win_index ] ) && is_array( $slices[ $win_index ] ) && ( $slices[ $win_index ]['type'] ?? 'lose' ) === 'win';

		do_action( 'meyvc_email_captured', $email, $campaign_id, array( 'source' => 'spin_wheel', 'slice' => $slice_label, 'is_win' => $is_win ) );

		$response = array( 'captured' => true, 'is_win' => $is_win );

		if ( $is_win && class_exists( 'MEYVC_Offer_Engine' ) ) {
			$coupon_code = MEYVC_Offer_Engine::get_or_create_coupon_for_best_offer();
			if ( is_string( $coupon_code ) && $coupon_code !== '' ) {
				$response['coupon_code']       = $coupon_code;
				$response['coupon_code_label'] = __( 'Your code', 'meyvora-convert' );
			}
		}

		wp_send_json_success( $response );
	}

	/**
	 * Dashboard live stats (last 60 minutes).
	 */
	public function live_stats() {
		check_ajax_referer( 'meyvc_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'meyvora-convert' ) ) );
		}

		global $wpdb;
		$events_table = $wpdb->prefix . 'meyvc_events';
		$ac_table     = $wpdb->prefix . 'meyvc_abandoned_carts';

		$stats = array(
			'impressions'     => 0,
			'conversions'     => 0,
			'emails'          => 0,
			'carts_recovered' => 0,
		);

		if ( class_exists( 'MEYVC_Database' ) && MEYVC_Database::table_exists( $events_table ) ) {
			$cache_key_live = 'meyvora_meyvc_' . md5( serialize( array( 'ajax_live_stats_events_window', $events_table, 60 ) ) );
			$cached_live    = wp_cache_get( $cache_key_live, 'meyvora_meyvc' );
			if ( false === $cached_live ) {
				$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
					$wpdb->prepare(
						'SELECT 
						SUM(event_type = \'impression\') AS impressions,
						SUM(event_type = \'conversion\' AND IFNULL(conversion_type, \'\') <> %s) AS conversions,
						SUM(event_type = \'conversion\' AND conversion_type = %s) AS emails
					FROM %i
					WHERE created_at >= ( UTC_TIMESTAMP() - INTERVAL %d MINUTE )',
						'email_capture',
						'email_capture',
						$events_table,
						60
					),
					ARRAY_A
				);
				wp_cache_set( $cache_key_live, $row, 'meyvora_meyvc', 300 );
			} else {
				$row = is_array( $cached_live ) ? $cached_live : null;
			}
			if ( is_array( $row ) ) {
				$stats['impressions'] = (int) ( $row['impressions'] ?? 0 );
				$stats['conversions'] = (int) ( $row['conversions'] ?? 0 );
				$stats['emails']      = (int) ( $row['emails'] ?? 0 );
			}
		}

		if ( class_exists( 'MEYVC_Database' ) && MEYVC_Database::table_exists( $ac_table ) ) {
			$cache_key_ac = 'meyvora_meyvc_' . md5( serialize( array( 'ajax_live_stats_recovered_carts', $ac_table, 'recovered', 60 ) ) );
			$cr_raw         = wp_cache_get( $cache_key_ac, 'meyvora_meyvc' );
			if ( false === $cr_raw ) {
				$cr_raw = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
					$wpdb->prepare(
						'SELECT COUNT(*) FROM %i
					WHERE status = %s AND recovered_at IS NOT NULL AND recovered_at >= ( UTC_TIMESTAMP() - INTERVAL %d MINUTE )',
						$ac_table,
						'recovered',
						60
					)
				);
				wp_cache_set( $cache_key_ac, $cr_raw, 'meyvora_meyvc', 300 );
			}
			$stats['carts_recovered'] = (int) $cr_raw;
		}

		wp_send_json_success( $stats );
	}
}

// Initialize when file is loaded (loader requires this file).
new MEYVC_Ajax();
