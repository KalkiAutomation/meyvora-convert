<?php
/**
 * AI-generated A/B challenger hypotheses for low-converting campaigns.
 *
 * @package Meyvora_Convert
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- AI eligibility reads only.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CRO_AI_AB_Hypothesis
 */
class CRO_AI_AB_Hypothesis {

	const RATE_ACTION = 'ab_hypothesis';
	const RATE_LIMIT  = 20;

	/**
	 * Whether campaign qualifies for AI ideas (analytics window).
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return bool
	 */
	public static function campaign_is_low_converting( int $campaign_id ): bool {
		$campaign_id = absint( $campaign_id );
		if ( $campaign_id < 1 || ! class_exists( 'CRO_Analytics' ) ) {
			return false;
		}
		$analytics = new CRO_Analytics();
		$date_to   = wp_date( 'Y-m-d' );
		$date_from = wp_date( 'Y-m-d', strtotime( '-90 days' ) );
		$summary   = $analytics->get_summary( $date_from, $date_to, $campaign_id );
		$imp       = isset( $summary['impressions'] ) ? (int) $summary['impressions'] : 0;
		$conv      = isset( $summary['conversions'] ) ? (int) $summary['conversions'] : 0;
		if ( $imp < 50 ) {
			return false;
		}
		$rate = $imp > 0 ? ( $conv / $imp ) * 100 : 0;
		return $rate < 3;
	}

	/**
	 * Campaign IDs with impressions ≥ 50 and conversion rate &lt; 3% over the last 90 days (matches campaign_is_low_converting; single query, no N+1).
	 *
	 * @return array<int, true> Map of campaign_id => true.
	 */
	public static function get_campaign_ids_low_converting_90d(): array {
		global $wpdb;
		$table     = $wpdb->prefix . 'cro_events';
		$date_to   = wp_date( 'Y-m-d' );
		$date_from = wp_date( 'Y-m-d', strtotime( '-90 days' ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT source_id,
					SUM(CASE WHEN event_type = \'impression\' THEN 1 ELSE 0 END) AS imp,
					SUM(CASE WHEN event_type = \'conversion\' THEN 1 ELSE 0 END) AS conv
				FROM %i
				WHERE DATE(created_at) BETWEEN %s AND %s
				AND source_type = \'campaign\'
				AND event_type IN (\'impression\', \'conversion\')
				GROUP BY source_id
				HAVING imp >= 50 AND conv * 100 < imp * 3',
				$table,
				$date_from,
				$date_to
			),
			ARRAY_A
		);
		$out = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $r ) {
				$cid = isset( $r['source_id'] ) ? (int) $r['source_id'] : 0;
				if ( $cid > 0 ) {
					$out[ $cid ] = true;
				}
			}
		}
		return $out;
	}

	/**
	 * Generate exactly two variant suggestions.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array|WP_Error Keys: variants (list of 2), baseline (headline, body, cta for diffs).
	 */
	public function generate( int $campaign_id ) {
		$campaign_id = absint( $campaign_id );
		if ( $campaign_id < 1 ) {
			return new WP_Error( 'invalid_id', __( 'Invalid campaign.', 'meyvora-convert' ) );
		}
		if ( ! class_exists( 'CRO_AI_Client' ) || ! CRO_AI_Client::is_configured() ) {
			return new WP_Error(
				'not_configured',
				__( 'Add your Anthropic API key in Settings → AI', 'meyvora-convert' )
			);
		}
		if ( function_exists( 'cro_settings' ) && 'yes' !== cro_settings()->get( 'ai', 'feature_ab', 'yes' ) ) {
			return new WP_Error(
				'feature_disabled',
				__( 'AI A/B features are disabled in Settings → AI.', 'meyvora-convert' )
			);
		}
		if ( ! self::campaign_is_low_converting( $campaign_id ) ) {
			return new WP_Error(
				'not_eligible',
				__( 'This campaign is not eligible for AI test ideas.', 'meyvora-convert' )
			);
		}
		if ( ! class_exists( 'CRO_AI_Rate_Limiter' ) || ! CRO_AI_Rate_Limiter::check( self::RATE_ACTION, self::RATE_LIMIT ) ) {
			return new WP_Error(
				'rate_limited',
				__( 'Too many AI requests. Try again later.', 'meyvora-convert' )
			);
		}

		global $wpdb;
		$table = $wpdb->prefix . 'cro_campaigns';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$table,
				$campaign_id
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'not_found', __( 'Campaign not found.', 'meyvora-convert' ) );
		}

		$analytics  = new CRO_Analytics();
		$date_to    = wp_date( 'Y-m-d' );
		$date_from  = wp_date( 'Y-m-d', strtotime( '-90 days' ) );
		$summary    = $analytics->get_summary( $date_from, $date_to, $campaign_id );
		$imp        = isset( $summary['impressions'] ) ? (int) $summary['impressions'] : 0;
		$conv       = isset( $summary['conversions'] ) ? (int) $summary['conversions'] : 0;
		$rate       = $imp > 0 ? round( ( $conv / $imp ) * 100, 2 ) : 0;

		$baseline        = $this->get_content_baseline( $row );
		$trigger_raw     = isset( $row['trigger_settings'] ) ? $row['trigger_settings'] : '';
		$trigger_decoded = is_string( $trigger_raw ) ? json_decode( $trigger_raw, true ) : null;
		if ( ! is_array( $trigger_decoded ) ) {
			$trigger_decoded = array();
		}
		$body_for_api = substr( $baseline['body'], 0, 2000 );

		$campaign_payload = array(
			'id'               => (int) $row['id'],
			'name'             => sanitize_text_field( (string) ( $row['name'] ?? '' ) ),
			'campaign_type'    => sanitize_key( (string) ( $row['campaign_type'] ?? '' ) ),
			'template_type'    => sanitize_text_field( (string) ( $row['template_type'] ?? '' ) ),
			'headline'         => $baseline['headline'],
			'body'             => $body_for_api,
			'cta_text'         => $baseline['cta'],
			'trigger_settings' => $trigger_decoded,
		);

		$campaign_json = wp_json_encode( $campaign_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $campaign_json ) ) {
			return new WP_Error( 'encode_error', __( 'Could not prepare campaign data.', 'meyvora-convert' ) );
		}

		$system = 'You are a CRO specialist designing A/B test variants for WooCommerce popups. '
			. 'Respond with a JSON array of exactly 2 variant objects, no markdown: '
			. '[{ "name": "Variant name max 4 words", "hypothesis": "what we think will improve and why (2 sentences)", '
			. '"change_type": "headline|cta|timing|template|targeting", "new_headline": "proposed headline", '
			. '"new_body": "proposed body copy", "new_cta": "proposed CTA text", '
			. '"change_summary": "one sentence describing the key change" }, ...]';

		$user = sprintf(
			'Campaign: %s. This campaign has %s%% conversion rate from %d impressions. '
			. 'Suggest 2 meaningful A/B test variants to improve conversions. '
			. 'Make the variants meaningfully different from each other.',
			$campaign_json,
			(string) $rate,
			$imp
		);

		$response = CRO_AI_Client::request(
			$system,
			array(
				array(
					'role'    => 'user',
					'content' => $user,
				),
			),
			4096
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$parsed = CRO_AI_Client::parse_json_response( $response );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$variants = $this->validate_variants_list( $parsed );
		if ( is_wp_error( $variants ) ) {
			return $variants;
		}

		CRO_AI_Rate_Limiter::increment( self::RATE_ACTION );

		return array(
			'variants' => $variants,
			'baseline' => $baseline,
		);
	}

	/**
	 * Current popup copy for before/after UI.
	 *
	 * @param array $row Campaign DB row.
	 * @return array{headline: string, body: string, cta: string}
	 */
	private function get_content_baseline( array $row ): array {
		$content = json_decode( isset( $row['content'] ) ? (string) $row['content'] : '{}', true );
		if ( ! is_array( $content ) ) {
			$content = array();
		}
		$body = '';
		if ( ! empty( $content['body'] ) && is_string( $content['body'] ) ) {
			$body = $content['body'];
		} elseif ( ! empty( $content['subheadline'] ) && is_string( $content['subheadline'] ) ) {
			$body = $content['subheadline'];
		}
		return array(
			'headline' => isset( $content['headline'] ) ? sanitize_text_field( (string) $content['headline'] ) : '',
			'body'     => sanitize_textarea_field( wp_strip_all_tags( $body ) ),
			'cta'      => isset( $content['cta_text'] ) ? sanitize_text_field( (string) $content['cta_text'] ) : '',
		);
	}

	/**
	 * @param array $parsed Top-level JSON (array of 2 objects).
	 * @return array|WP_Error
	 */
	private function validate_variants_list( array $parsed ) {
		if ( array_keys( $parsed ) !== range( 0, count( $parsed ) - 1 ) ) {
			return new WP_Error( 'invalid_shape', __( 'AI response was not a JSON array.', 'meyvora-convert' ) );
		}
		if ( count( $parsed ) !== 2 ) {
			return new WP_Error( 'invalid_count', __( 'AI must return exactly 2 variants.', 'meyvora-convert' ) );
		}
		$allowed_types = array( 'headline', 'cta', 'timing', 'template', 'targeting' );
		$out           = array();
		foreach ( $parsed as $item ) {
			if ( ! is_array( $item ) ) {
				return new WP_Error( 'invalid_item', __( 'Invalid variant in AI response.', 'meyvora-convert' ) );
			}
			$name = isset( $item['name'] ) ? sanitize_text_field( (string) $item['name'] ) : '';
			if ( $name === '' ) {
				return new WP_Error( 'invalid_item', __( 'Each variant needs a name.', 'meyvora-convert' ) );
			}
			$hypothesis = isset( $item['hypothesis'] ) ? sanitize_textarea_field( (string) $item['hypothesis'] ) : '';
			$ctype      = isset( $item['change_type'] ) ? sanitize_key( (string) $item['change_type'] ) : 'headline';
			if ( ! in_array( $ctype, $allowed_types, true ) ) {
				$ctype = 'headline';
			}
			$out[] = array(
				'name'           => $name,
				'hypothesis'     => $hypothesis,
				'change_type'    => $ctype,
				'new_headline'   => isset( $item['new_headline'] ) ? sanitize_text_field( (string) $item['new_headline'] ) : '',
				'new_body'       => isset( $item['new_body'] ) ? sanitize_textarea_field( (string) $item['new_body'] ) : '',
				'new_cta'        => isset( $item['new_cta'] ) ? sanitize_text_field( (string) $item['new_cta'] ) : '',
				'change_summary' => isset( $item['change_summary'] ) ? sanitize_text_field( (string) $item['change_summary'] ) : '',
			);
		}
		return $out;
	}

	/**
	 * Register AJAX action.
	 */
	public function register_ajax(): void {
		add_action( 'wp_ajax_cro_ai_ab_hypothesis', array( $this, 'ajax_ab_hypothesis' ) );
	}

	/**
	 * AJAX: return variants JSON for a campaign.
	 */
	public function ajax_ab_hypothesis(): void {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'meyvora-convert' ) ), 403 );
		}
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cro_ai_ab_hypothesis' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'meyvora-convert' ) ), 403 );
		}
		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
		$result      = $this->generate( $campaign_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success(
			array(
				'variants'    => $result['variants'],
				'baseline'    => $result['baseline'],
				'campaign_id' => $campaign_id,
			)
		);
	}

	/**
	 * After creating an A/B test, add challenger variation from POST payload (base64 JSON).
	 *
	 * @param int    $test_id     New test ID.
	 * @param int    $campaign_id Original campaign ID.
	 * @param string $payload_b64 Base64-encoded JSON variant object.
	 * @return bool Whether a variation was added.
	 */
	public static function maybe_add_challenger_variation( int $test_id, int $campaign_id, string $payload_b64 ): bool {
		$test_id     = absint( $test_id );
		$campaign_id = absint( $campaign_id );
		if ( $test_id < 1 || $campaign_id < 1 || $payload_b64 === '' ) {
			return false;
		}
		$json = base64_decode( $payload_b64, true );
		if ( false === $json || ! is_string( $json ) ) {
			return false;
		}
		$var = json_decode( $json, true );
		if ( ! is_array( $var ) ) {
			return false;
		}
		$name = isset( $var['name'] ) ? sanitize_text_field( (string) $var['name'] ) : '';
		if ( $name === '' ) {
			return false;
		}
		$headline = isset( $var['new_headline'] ) ? sanitize_text_field( (string) $var['new_headline'] ) : '';
		$body     = isset( $var['new_body'] ) ? sanitize_textarea_field( (string) $var['new_body'] ) : '';
		$cta      = isset( $var['new_cta'] ) ? sanitize_text_field( (string) $var['new_cta'] ) : '';

		global $wpdb;
		$table    = $wpdb->prefix . 'cro_campaigns';
		$original = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$table,
				$campaign_id
			),
			ARRAY_A
		);
		if ( ! $original ) {
			return false;
		}
		$content = json_decode( $original['content'] ?? '{}', true );
		if ( ! is_array( $content ) ) {
			$content = array();
		}
		if ( $headline !== '' ) {
			$content['headline'] = $headline;
		}
		if ( $body !== '' ) {
			$content['subheadline'] = $body;
		}
		if ( $cta !== '' ) {
			$content['cta_text'] = $cta;
		}
		$variation_data            = $original;
		$variation_data['content'] = wp_json_encode( $content );

		$ab = new CRO_AB_Test();
		$vid = $ab->add_variation(
			$test_id,
			array(
				'name'           => $name,
				'traffic_weight' => 50,
				'campaign_data'  => wp_json_encode( $variation_data ),
			)
		);
		return (bool) $vid;
	}
}

// phpcs:enable
