<?php
/**
 * AI-generated campaign popup copy (headline, body, CTA).
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MEYVC_AI_Copy_Generator
 */
class MEYVC_AI_Copy_Generator {

	const AJAX_ACTION = 'meyvc_ai_generate_copy';

	/**
	 * Register admin AJAX handler.
	 */
	public function register_ajax(): void {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_generate_copy' ) );
	}

	/**
	 * AJAX: meyvc_ai_generate_copy
	 */
	public function ajax_generate_copy(): void {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'meyvora-convert' ) ), 403 );
		}

		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'meyvc_ai_generate_copy' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'meyvora-convert' ) ), 403 );
		}

		if ( function_exists( 'meyvc_settings' ) && 'yes' !== meyvc_settings()->get( 'ai', 'feature_copy', 'yes' ) ) {
			wp_send_json_error( array( 'message' => __( 'AI Copy Generator is disabled in Settings → AI.', 'meyvora-convert' ) ) );
		}

		if ( ! class_exists( 'MEYVC_AI_Client' ) || ! MEYVC_AI_Client::is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'Add your Anthropic API key in Settings → AI', 'meyvora-convert' ) ) );
		}

		if ( ! MEYVC_AI_Rate_Limiter::check( 'copy_generate', 30 ) ) {
			$reset = MEYVC_AI_Rate_Limiter::get_reset_time( 'copy_generate' );
			$mins  = max( 1, (int) ceil( ( $reset - time() ) / 60 ) );
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: minutes until the rate limit resets */
						__( 'Limit reached. Try again in %d minutes.', 'meyvora-convert' ),
						$mins
					),
				)
			);
		}

		$goal          = isset( $_POST['goal'] ) ? sanitize_text_field( wp_unslash( $_POST['goal'] ) ) : '';
		$template_type = isset( $_POST['template_type'] ) ? sanitize_text_field( wp_unslash( $_POST['template_type'] ) ) : '';
		$page_type     = isset( $_POST['page_type'] ) ? sanitize_text_field( wp_unslash( $_POST['page_type'] ) ) : '';
		$offer_type    = isset( $_POST['offer_type'] ) ? sanitize_text_field( wp_unslash( $_POST['offer_type'] ) ) : '';

		if ( '' === $goal ) {
			wp_send_json_error( array( 'message' => __( 'Please describe your campaign goal.', 'meyvora-convert' ) ) );
		}

		$result = $this->generate(
			array(
				'goal'          => $goal,
				'template_type' => $template_type,
				'page_type'     => $page_type,
				'offer_type'    => $offer_type,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		MEYVC_AI_Rate_Limiter::increment( 'copy_generate' );

		wp_send_json_success(
			array(
				'headline' => $result['headline'],
				'body'     => $result['body'],
				'cta'      => $result['cta'],
			)
		);
	}

	/**
	 * Build copy via Claude.
	 *
	 * @param array $context Keys: goal, template_type, page_type, optional offer_type.
	 * @return array|WP_Error {
	 *     @type string $headline
	 *     @type string $body
	 *     @type string $cta
	 * }
	 */
	public function generate( array $context ) {
		$goal          = isset( $context['goal'] ) ? sanitize_text_field( (string) $context['goal'] ) : '';
		$template_type = isset( $context['template_type'] ) ? sanitize_text_field( (string) $context['template_type'] ) : '';
		$page_type     = isset( $context['page_type'] ) ? sanitize_text_field( (string) $context['page_type'] ) : '';
		$offer_type    = isset( $context['offer_type'] ) ? sanitize_text_field( (string) $context['offer_type'] ) : '';

		if ( '' === $goal ) {
			return new WP_Error( 'missing_goal', __( 'Goal is required.', 'meyvora-convert' ) );
		}

		if ( '' === $template_type ) {
			$template_type = 'popup';
		}
		if ( '' === $page_type ) {
			$page_type = 'all pages';
		}

		if ( ! class_exists( 'MEYVC_AI_Client' ) || ! MEYVC_AI_Client::is_configured() ) {
			return new WP_Error( 'not_configured', __( 'Add your Anthropic API key in Settings → AI', 'meyvora-convert' ) );
		}

		$offer_sentence = '';
		if ( '' !== $offer_type ) {
			$offer_sentence = sprintf(
				/* translators: %s: offer description */
				__( 'Offer: %s. ', 'meyvora-convert' ),
				$offer_type
			);
		}

		$system = 'You are a conversion copywriter for WooCommerce stores. Write short, high-converting popup copy. Always respond with valid JSON only, no markdown: {"headline": "...", "body": "...", "cta": "..."}. Headline: max 10 words. Body: max 25 words. CTA button text: max 4 words.';

		$user = sprintf(
			'Store goal: %s. Popup type: %s. Page context: %s. %sWrite compelling copy to maximise conversions.',
			$goal,
			$template_type,
			$page_type,
			$offer_sentence
		);

		try {
			$raw = MEYVC_AI_Client::request(
				$system,
				array(
					array(
						'role'    => 'user',
						'content' => $user,
					),
				),
				512
			);
		} catch ( \Throwable $e ) {
			return new WP_Error( 'ai_error', __( 'Could not reach the AI service. Please try again.', 'meyvora-convert' ) );
		}

		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$parsed = MEYVC_AI_Client::parse_json_response( $raw );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$headline = isset( $parsed['headline'] ) && is_string( $parsed['headline'] ) ? trim( wp_kses_decode_entities( $parsed['headline'] ) ) : '';
		$body     = isset( $parsed['body'] ) && is_string( $parsed['body'] ) ? trim( wp_kses_decode_entities( $parsed['body'] ) ) : '';
		$cta      = isset( $parsed['cta'] ) && is_string( $parsed['cta'] ) ? trim( wp_kses_decode_entities( $parsed['cta'] ) ) : '';

		if ( '' === $headline || '' === $cta ) {
			return new WP_Error( 'parse_error', __( 'AI returned incomplete copy. Try again.', 'meyvora-convert' ) );
		}

		return array(
			'headline' => $headline,
			'body'     => $body,
			'cta'      => $cta,
		);
	}
}
