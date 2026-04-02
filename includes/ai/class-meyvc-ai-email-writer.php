<?php
/**
 * AI-generated abandoned cart recovery email content (cached per cart + slot).
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MEYVC_AI_Email_Writer
 */
class MEYVC_AI_Email_Writer {

	const TRANSIENT_PREFIX = 'meyvc_ai_email_';
	const CACHE_TTL        = DAY_IN_SECONDS;

	/**
	 * Transient key for cached AI output.
	 *
	 * @param int $cart_id      Abandoned cart row id.
	 * @param int $email_number 1–3.
	 * @return string
	 */
	public static function transient_key( int $cart_id, int $email_number ): string {
		return self::TRANSIENT_PREFIX . $cart_id . '_' . $email_number;
	}

	/**
	 * Cached AI email if present and valid.
	 *
	 * @param int $cart_id      Row id in meyvc_abandoned_carts.
	 * @param int $email_number 1, 2, or 3.
	 * @return array|false      Keys: subject, preheader, body_html; or false.
	 */
	public function get_cached( int $cart_id, int $email_number ) {
		if ( $cart_id <= 0 || $email_number < 1 || $email_number > 3 ) {
			return false;
		}
		$data = get_transient( self::transient_key( $cart_id, $email_number ) );
		if ( ! is_array( $data ) || empty( $data['subject'] ) || empty( $data['body_html'] ) ) {
			return false;
		}
		if ( ! isset( $data['preheader'] ) ) {
			$data['preheader'] = '';
		}
		return $data;
	}

	/**
	 * Delete cached AI email (e.g. admin regenerate).
	 *
	 * @param int $cart_id      Row id.
	 * @param int $email_number 1–3.
	 */
	public static function delete_cache( int $cart_id, int $email_number ): void {
		if ( $cart_id <= 0 || $email_number < 1 || $email_number > 3 ) {
			return;
		}
		delete_transient( self::transient_key( $cart_id, $email_number ) );
	}

	/**
	 * Register admin AJAX handlers.
	 */
	public function register_ajax(): void {
		add_action( 'wp_ajax_meyvc_ai_preview_email', array( $this, 'ajax_preview_email' ) );
		add_action( 'wp_ajax_meyvc_ai_bust_email_cache', array( $this, 'ajax_bust_email_cache' ) );
	}

	/**
	 * AJAX: preview AI email for a cart row.
	 */
	public function ajax_preview_email(): void {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'meyvora-convert' ) ), 403 );
		}
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'meyvc_ai_preview_email' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'meyvora-convert' ) ), 403 );
		}
		if ( function_exists( 'meyvc_settings' ) && 'yes' !== meyvc_settings()->get( 'ai', 'feature_emails', 'yes' ) ) {
			wp_send_json_error( array( 'message' => __( 'AI abandoned cart emails are disabled in Settings → AI.', 'meyvora-convert' ) ) );
		}
		if ( ! class_exists( 'MEYVC_AI_Client' ) || ! MEYVC_AI_Client::is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'Add your Anthropic API key in Settings → AI', 'meyvora-convert' ) ) );
		}

		$cart_id      = isset( $_POST['cart_id'] ) ? absint( $_POST['cart_id'] ) : 0;
		$email_number = isset( $_POST['email_number'] ) ? absint( $_POST['email_number'] ) : 0;
		if ( $cart_id <= 0 || $email_number < 1 || $email_number > 3 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid cart or email number.', 'meyvora-convert' ) ), 400 );
		}
		if ( ! class_exists( 'MEYVC_Abandoned_Cart_Tracker' ) || ! MEYVC_Database::table_exists( MEYVC_Abandoned_Cart_Tracker::get_table_name() ) ) {
			wp_send_json_error( array( 'message' => __( 'Abandoned cart data is not available.', 'meyvora-convert' ) ), 500 );
		}
		$row = MEYVC_Abandoned_Cart_Tracker::get_row_by_id( $cart_id );
		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'Cart not found.', 'meyvora-convert' ) ), 404 );
		}

		$cached = $this->get_cached( $cart_id, $email_number );
		if ( is_array( $cached ) ) {
			wp_send_json_success( $cached );
			return;
		}

		if ( ! MEYVC_AI_Rate_Limiter::check( 'abandoned_email_preview', 40 ) ) {
			$reset = MEYVC_AI_Rate_Limiter::get_reset_time( 'abandoned_email_preview' );
			$mins  = max( 1, (int) ceil( ( $reset - time() ) / 60 ) );
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: minutes */
						__( 'Preview limit reached. Try again in %d minutes.', 'meyvora-convert' ),
						$mins
					),
				)
			);
		}

		$result = $this->generate_email( $cart_id, $email_number, null );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		MEYVC_AI_Rate_Limiter::increment( 'abandoned_email_preview' );
		wp_send_json_success( $result );
	}

	/**
	 * AJAX: delete transient so next preview/regenerate fetches fresh AI copy.
	 */
	public function ajax_bust_email_cache(): void {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'meyvora-convert' ) ), 403 );
		}
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'meyvc_ai_bust_email_cache' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'meyvora-convert' ) ), 403 );
		}
		$cart_id      = isset( $_POST['cart_id'] ) ? absint( $_POST['cart_id'] ) : 0;
		$email_number = isset( $_POST['email_number'] ) ? absint( $_POST['email_number'] ) : 0;
		if ( $cart_id <= 0 || $email_number < 1 || $email_number > 3 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid cart or email number.', 'meyvora-convert' ) ), 400 );
		}
		self::delete_cache( $cart_id, $email_number );
		wp_send_json_success( array( 'deleted' => true ) );
	}

	/**
	 * Human-readable timing line for the prompt.
	 *
	 * @param int $email_number 1–3.
	 * @return string
	 */
	private static function timing_description( int $email_number ): string {
		switch ( $email_number ) {
			case 1:
				return 'sent 1 hour after abandonment — customer may still be deciding';
			case 2:
				return 'sent 24 hours later — gentle reminder, emphasise what they are missing out on';
			case 3:
				return 'sent 72 hours later — last chance, create urgency, highlight any coupon';
			default:
				return '';
		}
	}

	/**
	 * Coupon code string for the prompt ("none" if absent).
	 *
	 * @param object      $row              DB row.
	 * @param int         $email_number     1–3.
	 * @param string|null $recovery_coupon  Coupon passed from send pipeline (optional).
	 * @return string
	 */
	private static function coupon_for_prompt( $row, int $email_number, $recovery_coupon ): string {
		if ( is_string( $recovery_coupon ) && $recovery_coupon !== '' ) {
			return $recovery_coupon;
		}
		if ( ! empty( $row->discount_coupon ) && class_exists( 'MEYVC_Abandoned_Cart_Coupon' ) && MEYVC_Abandoned_Cart_Coupon::is_coupon_usable( $row->discount_coupon ) ) {
			return trim( (string) $row->discount_coupon );
		}
		return 'none';
	}

	/**
	 * Allowed HTML for AI body (paragraphs only).
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	private static function sanitize_body_html( string $html ): string {
		return wp_kses(
			$html,
			array(
				'p' => array(
					'class' => true,
				),
			)
		);
	}

	/**
	 * Build and cache AI email content.
	 *
	 * @param int         $cart_id         meyvc_abandoned_carts.id.
	 * @param int         $email_number    1, 2, or 3.
	 * @param string|null $recovery_coupon Coupon code for this send (optional).
	 * @return array|WP_Error Keys: subject, preheader, body_html.
	 */
	public function generate_email( int $cart_id, int $email_number, $recovery_coupon = null ) {
		if ( $cart_id <= 0 || $email_number < 1 || $email_number > 3 ) {
			return new WP_Error( 'invalid_args', __( 'Invalid cart or email number.', 'meyvora-convert' ) );
		}
		if ( ! class_exists( 'MEYVC_Abandoned_Cart_Tracker' ) ) {
			return new WP_Error( 'no_tracker', __( 'Abandoned cart module is unavailable.', 'meyvora-convert' ) );
		}
		$row = MEYVC_Abandoned_Cart_Tracker::get_row_by_id( $cart_id );
		if ( ! $row ) {
			return new WP_Error( 'not_found', __( 'Cart not found.', 'meyvora-convert' ) );
		}
		if ( ! class_exists( 'MEYVC_AI_Client' ) || ! MEYVC_AI_Client::is_configured() ) {
			return new WP_Error( 'not_configured', __( 'Add your Anthropic API key in Settings → AI', 'meyvora-convert' ) );
		}

		$currency = ! empty( $row->currency ) ? (string) $row->currency : ( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD' );
		$total    = 0.0;
		$names    = array();
		$data     = ! empty( $row->cart_json ) ? json_decode( $row->cart_json, true ) : null;
		if ( is_array( $data ) ) {
			if ( isset( $data['totals']['total'] ) ) {
				$total = (float) $data['totals']['total'];
			}
			if ( ! empty( $data['items'] ) && is_array( $data['items'] ) ) {
				foreach ( $data['items'] as $item ) {
					$n   = isset( $item['name'] ) ? sanitize_text_field( (string) $item['name'] ) : __( 'Item', 'meyvora-convert' );
					$qty = isset( $item['quantity'] ) ? max( 1, (int) $item['quantity'] ) : 1;
					$names[] = $qty > 1 ? sprintf( '%s × %d', $n, $qty ) : $n;
				}
			}
		}
		$items_list = $names ? implode( ', ', $names ) : __( 'Unknown items', 'meyvora-convert' );

		$first = __( 'there', 'meyvora-convert' );
		if ( ! empty( $row->user_id ) ) {
			$user = get_userdata( (int) $row->user_id );
			if ( $user && ! empty( $user->first_name ) ) {
				$first = sanitize_text_field( $user->first_name );
			}
		}

		$coupon_code = self::coupon_for_prompt( $row, $email_number, $recovery_coupon );
		$timing      = self::timing_description( $email_number );

		$system = 'You are an email copywriter for an ecommerce store. Write a short, warm abandoned cart recovery email. Respond with valid JSON only, no markdown: {"subject": "...", "preheader": "...", "body_html": "..."}. Subject: max 60 chars. Preheader: max 90 chars. Body HTML: 3-4 short paragraphs using <p> tags only, no inline styles, no images.';

		$user_prompt = sprintf(
			"Customer first name: %s.\nAbandoned cart total: %s %s.\nItems abandoned: %s.\nThis is email %d of 3.\nEmail timing context: %s.\nRecovery coupon code: %s.\nWrite the recovery email.",
			$first,
			$currency,
			number_format_i18n( $total, 2 ),
			$items_list,
			$email_number,
			$timing,
			$coupon_code
		);

		try {
			$raw = MEYVC_AI_Client::request(
				$system,
				array(
					array(
						'role'    => 'user',
						'content' => $user_prompt,
					),
				),
				1024
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

		$subject   = isset( $parsed['subject'] ) && is_string( $parsed['subject'] ) ? trim( wp_kses_decode_entities( $parsed['subject'] ) ) : '';
		$preheader = isset( $parsed['preheader'] ) && is_string( $parsed['preheader'] ) ? trim( wp_kses_decode_entities( $parsed['preheader'] ) ) : '';
		$body_raw  = isset( $parsed['body_html'] ) && is_string( $parsed['body_html'] ) ? $parsed['body_html'] : '';

		$subject   = sanitize_text_field( $subject );
		$preheader = sanitize_text_field( $preheader );
		if ( strlen( $subject ) > 60 ) {
			$subject = substr( $subject, 0, 57 ) . '...';
		}
		if ( strlen( $preheader ) > 90 ) {
			$preheader = substr( $preheader, 0, 87 ) . '...';
		}

		$body_html = self::sanitize_body_html( $body_raw );
		if ( '' === trim( wp_strip_all_tags( $body_html ) ) || '' === $subject ) {
			return new WP_Error( 'parse_error', __( 'AI returned incomplete email content. Try again.', 'meyvora-convert' ) );
		}

		$out = array(
			'subject'    => $subject,
			'preheader'  => $preheader,
			'body_html'  => $body_html,
		);

		set_transient( self::transient_key( $cart_id, $email_number ), $out, self::CACHE_TTL );

		return $out;
	}
}
