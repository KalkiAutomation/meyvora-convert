<?php
/**
 * Admin AI chat backed by a 30-day analytics snapshot.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MEYVC_AI_Chat
 */
class MEYVC_AI_Chat {

	const RATE_ACTION = 'chat';
	const RATE_LIMIT  = 20;

	const MAX_MESSAGES = 20;

	/**
	 * Run chat turn: snapshot + history + new user message → assistant reply.
	 *
	 * @param string               $message Plain-text user message (already strip_tags on entry).
	 * @param array<int, array>    $history Prior turns: { role: user|assistant, content: string }.
	 * @return array|WP_Error { reply: string (wp_kses_post), history: array }
	 */
	public function chat( string $message, array $history ) {
		$message = wp_strip_all_tags( $message );
		$message = trim( $message );
		if ( '' === $message ) {
			return new WP_Error( 'empty_message', __( 'Please enter a message.', 'meyvora-convert' ) );
		}

		if ( ! class_exists( 'MEYVC_AI_Client' ) || ! MEYVC_AI_Client::is_configured() ) {
			return new WP_Error(
				'not_configured',
				__( 'Add your Anthropic API key in Settings → AI.', 'meyvora-convert' )
			);
		}

		if ( ! class_exists( 'MEYVC_AI_Rate_Limiter' ) || ! MEYVC_AI_Rate_Limiter::check( self::RATE_ACTION, self::RATE_LIMIT ) ) {
			return new WP_Error(
				'rate_limited',
				__( 'Too many AI messages this hour. Try again later.', 'meyvora-convert' )
			);
		}

		$sanitized_history = $this->sanitize_history( $history );
		$sanitized_history[] = array(
			'role'    => 'user',
			'content' => $message,
		);
		$api_messages = array_slice( $sanitized_history, -self::MAX_MESSAGES );
		while ( ! empty( $api_messages ) && isset( $api_messages[0]['role'] ) && 'user' !== $api_messages[0]['role'] ) {
			array_shift( $api_messages );
		}
		if ( empty( $api_messages ) ) {
			$api_messages = array(
				array(
					'role'    => 'user',
					'content' => $message,
				),
			);
		}

		$snapshot = $this->build_snapshot();
		$snapshot_json = wp_json_encode( $snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $snapshot_json ) ) {
			return new WP_Error( 'snapshot_error', __( 'Could not build data snapshot.', 'meyvora-convert' ) );
		}

		$system = 'You are the Meyvora Convert AI assistant for a WooCommerce store owner. '
			. 'You have access to this 30-day data snapshot: ' . $snapshot_json . '. '
			. 'Answer questions about conversion performance, campaigns, offers, and abandoned carts. '
			. 'Be concise: 2-4 sentences max per answer. '
			. 'Never make up numbers. If something is not in the data, say so. '
			. 'Format numbers clearly: \'3.2% conversion rate\', \'$1,240 revenue\'.';

		$response = MEYVC_AI_Client::request( $system, $api_messages, 1024 );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$text = MEYVC_AI_Client::extract_text( $response );
		$text = is_string( $text ) ? trim( $text ) : '';

		MEYVC_AI_Rate_Limiter::increment( self::RATE_ACTION );

		$sanitized_history[] = array(
			'role'    => 'assistant',
			'content' => $text,
		);
		$out_history = array_slice( $sanitized_history, -self::MAX_MESSAGES );

		return array(
			'reply'   => wp_kses_post( $text ),
			'history' => $out_history,
		);
	}

	/**
	 * @param array $history Raw history from client.
	 * @return array<int, array{role: string, content: string}>
	 */
	private function sanitize_history( array $history ): array {
		$out = array();
		foreach ( $history as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$role = isset( $item['role'] ) ? (string) $item['role'] : '';
			if ( ! in_array( $role, array( 'user', 'assistant' ), true ) ) {
				continue;
			}
			$content = isset( $item['content'] ) ? wp_strip_all_tags( (string) $item['content'] ) : '';
			$content = trim( $content );
			if ( '' === $content ) {
				continue;
			}
			if ( strlen( $content ) > 8000 ) {
				$content = substr( $content, 0, 8000 );
			}
			$out[] = array(
				'role'    => $role,
				'content' => $content,
			);
		}
		return array_slice( $out, -self::MAX_MESSAGES );
	}

	/**
	 * Lightweight 30-day snapshot: store-wide summary + top 5 campaigns by revenue.
	 *
	 * @return array
	 */
	private function build_snapshot(): array {
		$date_to   = wp_date( 'Y-m-d' );
		$date_from = wp_date( 'Y-m-d', strtotime( '-30 days' ) );

		if ( ! class_exists( 'MEYVC_Analytics' ) ) {
			return array(
				'date_from'                => $date_from,
				'date_to'                  => $date_to,
				'summary'                  => array(),
				'top_campaigns_by_revenue' => array(),
				'note'                     => 'Analytics not available.',
			);
		}

		$analytics = new MEYVC_Analytics();
		$summary   = $analytics->get_summary( $date_from, $date_to );
		$top       = MEYVC_Analytics::get_top_campaigns( 30, 5 );

		$campaigns = array();
		foreach ( $top as $c ) {
			if ( ! is_object( $c ) ) {
				continue;
			}
			$campaigns[] = array(
				'id'                  => isset( $c->id ) ? (int) $c->id : 0,
				'name'                => isset( $c->name ) ? sanitize_text_field( (string) $c->name ) : '',
				'impressions'         => isset( $c->impressions ) ? (int) $c->impressions : 0,
				'conversions'         => isset( $c->conversions ) ? (int) $c->conversions : 0,
				'conversion_rate_pct' => isset( $c->conversion_rate ) ? (float) $c->conversion_rate : 0.0,
				'revenue'             => isset( $c->revenue_attributed ) ? (float) $c->revenue_attributed : 0.0,
			);
		}

		return array(
			'date_from' => $date_from,
			'date_to'   => $date_to,
			'summary'   => array(
				'impressions'         => isset( $summary['impressions'] ) ? (int) $summary['impressions'] : 0,
				'clicks'              => isset( $summary['clicks'] ) ? (int) $summary['clicks'] : 0,
				'conversions'         => isset( $summary['conversions'] ) ? (int) $summary['conversions'] : 0,
				'conversion_rate_pct' => isset( $summary['conversion_rate'] ) ? round( (float) $summary['conversion_rate'], 2 ) : 0.0,
				'revenue'             => isset( $summary['revenue'] ) ? (float) $summary['revenue'] : 0.0,
				'emails_captured'     => isset( $summary['emails'] ) ? (int) $summary['emails'] : 0,
			),
			'top_campaigns_by_revenue' => $campaigns,
			'data_scope'               => 'Campaign rows are Meyvora Convert campaign events (top 5 by revenue in the period). Offer-level and abandoned-cart recovery metrics are not in this snapshot unless implied by summary fields (e.g. conversions/revenue).',
		);
	}

	/**
	 * Register AJAX handler.
	 */
	public function register_ajax(): void {
		add_action( 'wp_ajax_meyvc_ai_chat', array( $this, 'ajax_chat' ) );
	}

	/**
	 * AJAX: send message, return reply + history.
	 */
	public function ajax_chat(): void {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'meyvora-convert' ) ), 403 );
		}

		check_ajax_referer( 'meyvc_ai_chat', '_wpnonce' );

		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$message = wp_strip_all_tags( (string) $message );
		$message = trim( $message );
		if ( strlen( $message ) > 4000 ) {
			$message = substr( $message, 0, 4000 );
		}

		$history_raw = isset( $_POST['history'] ) ? sanitize_textarea_field( wp_unslash( $_POST['history'] ) ) : '[]';
		$history     = json_decode( is_string( $history_raw ) ? $history_raw : '[]', true );
		if ( ! is_array( $history ) ) {
			$history = array();
		}

		$result = $this->chat( $message, $history );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}
}
