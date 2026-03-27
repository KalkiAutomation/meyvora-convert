<?php
/**
 * Anthropic Claude API client for Meyvora Convert AI features.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CRO_AI_Client
 */
class CRO_AI_Client {

	const API_URL     = 'https://api.anthropic.com/v1/messages';
	const MODEL       = 'claude-sonnet-4-6';
	const API_VERSION = '2023-06-01';
	const TIMEOUT     = 30;

	/**
	 * Stored API key from settings (empty if unset).
	 *
	 * @return string
	 */
	public static function get_api_key(): string {
		if ( ! function_exists( 'cro_settings' ) ) {
			return '';
		}
		$enc = cro_settings()->get( 'ai', 'anthropic_api_key_enc', '' );
		if ( is_string( $enc ) && $enc !== '' && class_exists( 'CRO_Security' ) ) {
			$decrypted = CRO_Security::decrypt_secret( $enc );
			if ( is_string( $decrypted ) && $decrypted !== '' ) {
				return $decrypted;
			}
		}
		$key = cro_settings()->get( 'ai', 'anthropic_api_key', '' );
		return is_string( $key ) ? $key : '';
	}

	/**
	 * Whether an API key is saved.
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {
		return '' !== self::get_api_key();
	}

	/**
	 * Send a messages request to Anthropic.
	 *
	 * @param string               $system     System prompt.
	 * @param array<int, array>    $messages   Message objects (role, content).
	 * @param int                  $max_tokens Max tokens to generate.
	 * @return array|WP_Error Decoded JSON body on success, or WP_Error.
	 */
	public static function request( string $system, array $messages, int $max_tokens = 1000 ) {
		$api_key = self::get_api_key();
		if ( '' === $api_key ) {
			return new WP_Error(
				'not_configured',
				__( 'Anthropic API key is not configured.', 'meyvora-convert' )
			);
		}

		$body = array(
			'model'       => self::MODEL,
			'max_tokens'  => max( 1, $max_tokens ),
			'messages'    => $messages,
		);
		if ( '' !== $system ) {
			$body['system'] = $system;
		}

		$response = wp_safe_remote_post(
			self::API_URL,
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $api_key,
					'anthropic-version' => self::API_VERSION,
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$msg = $response->get_error_message();
			if ( false !== stripos( $msg, 'timed out' ) || false !== stripos( $msg, 'timeout' ) ) {
				return new WP_Error( 'timeout', __( 'AI request timed out', 'meyvora-convert' ) );
			}
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			$err_msg = __( 'AI API request failed.', 'meyvora-convert' );
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) && ! empty( $decoded['error']['message'] ) && is_string( $decoded['error']['message'] ) ) {
				$err_msg = $decoded['error']['message'];
			}
			return new WP_Error(
				'api_error',
				$err_msg,
				array( 'status' => $code )
			);
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'parse_error',
				__( 'Could not parse AI API response.', 'meyvora-convert' )
			);
		}

		return $data;
	}

	/**
	 * First text block from a messages API response body.
	 *
	 * @param array $response Decoded response from request().
	 * @return string
	 */
	public static function extract_text( array $response ): string {
		if ( empty( $response['content'] ) || ! is_array( $response['content'] ) ) {
			return '';
		}
		foreach ( $response['content'] as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			if ( isset( $block['type'] ) && 'text' === $block['type'] && isset( $block['text'] ) && is_string( $block['text'] ) ) {
				return $block['text'];
			}
		}
		return '';
	}

	/**
	 * Parse model output as JSON (strips optional markdown fences).
	 *
	 * @param array $response Decoded response from request().
	 * @return array|WP_Error
	 */
	public static function parse_json_response( array $response ) {
		$text = self::extract_text( $response );
		$text = trim( $text );
		if ( preg_match( '/^```(?:json)?\s*\R?(.*?)\R?```\s*$/is', $text, $m ) ) {
			$text = trim( $m[1] );
		}
		$decoded = json_decode( $text, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return new WP_Error(
				'parse_error',
				__( 'Could not parse AI response as JSON.', 'meyvora-convert' )
			);
		}
		return $decoded;
	}
}
