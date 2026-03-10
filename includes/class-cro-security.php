<?php
/**
 * Security utilities
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CRO_Security class.
 *
 * Nonce verification, capability checks, sanitization, rate limiting, and secure export.
 */
class CRO_Security {

	/**
	 * Verify AJAX nonce.
	 *
	 * Sends JSON error and exits if missing or invalid.
	 *
	 * @param string $action Nonce action.
	 * @return true
	 */
	public static function verify_ajax_nonce( $action = 'cro_ajax_nonce' ) {
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';

		if ( empty( $nonce ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing nonce', 'meyvora-convert' ) ), 403 );
		}

		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce', 'meyvora-convert' ) ), 403 );
		}

		return true;
	}

	/**
	 * Verify REST nonce.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	public static function verify_rest_nonce( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Invalid nonce', 'meyvora-convert' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Check admin capability; wp_die on failure.
	 *
	 * @param string $cap Capability to check (default manage_meyvora_convert).
	 * @return true
	 */
	public static function check_admin_cap( $cap = 'manage_meyvora_convert' ) {
		if ( ! current_user_can( $cap ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'meyvora-convert' ),
				esc_html__( 'Permission Denied', 'meyvora-convert' ),
				array( 'response' => 403 )
			);
		}

		return true;
	}

	/**
	 * Sanitize campaign content (allows limited HTML).
	 *
	 * @param string $content Raw content.
	 * @return string Sanitized HTML.
	 */
	public static function sanitize_campaign_content( $content ) {
		$allowed_html = array(
			'strong' => array(),
			'em'     => array(),
			'b'      => array(),
			'i'      => array(),
			'br'     => array(),
			'span'   => array(
				'class' => array(),
				'style' => array(),
			),
			'a'      => array(
				'href'   => array(),
				'title'  => array(),
				'target' => array(),
				'rel'    => array(),
			),
			'p'      => array( 'class' => array() ),
		);

		return wp_kses( $content, $allowed_html );
	}

	/**
	 * Sanitize and validate email input.
	 *
	 * @param string $email Email address.
	 * @return string|false Sanitized email or false if invalid.
	 */
	public static function sanitize_email_input( $email ) {
		$email = sanitize_email( is_string( $email ) ? $email : (string) ( $email ?? '' ) );

		if ( ! is_email( $email ) ) {
			return false;
		}

		$parts = explode( '@', (string) $email );
		if ( count( $parts ) !== 2 ) {
			return false;
		}

		$disposable_domains = apply_filters(
			'cro_disposable_email_domains',
			array( 'tempmail.com', 'throwaway.com', 'mailinator.com', 'guerrillamail.com' )
		);
		$disposable_domains = is_array( $disposable_domains ) ? $disposable_domains : array();

		$domain = strtolower( (string) ( $parts[1] ?? '' ) );
		if ( in_array( $domain, $disposable_domains, true ) ) {
			return false;
		}

		return $email;
	}

	/**
	 * Escape output for JavaScript.
	 *
	 * @param string $string String to escape.
	 * @return string
	 */
	public static function esc_js_string( $string ) {
		return esc_js( $string );
	}

	/**
	 * Generate secure random token.
	 *
	 * @param int $length Byte length (output is 2× in hex).
	 * @return string Hex string.
	 */
	public static function generate_token( $length = 32 ) {
		return bin2hex( random_bytes( (int) max( 1, $length / 2 ) ) );
	}

	/**
	 * Rate limit check by key.
	 *
	 * @param string $key    Unique key (e.g. IP or user id).
	 * @param int    $limit  Max requests in window.
	 * @param int    $window Window in seconds.
	 * @return bool True if under limit, false if exceeded.
	 */
	public static function check_rate_limit( $key, $limit = 10, $window = 60 ) {
		$transient_key = 'cro_rate_' . md5( $key );
		$current       = get_transient( $transient_key );

		if ( false === $current ) {
			set_transient( $transient_key, 1, $window );
			return true;
		}

		if ( (int) $current >= (int) $limit ) {
			return false;
		}

		set_transient( $transient_key, (int) $current + 1, $window );
		return true;
	}

	/**
	 * Secure CSV export (cap check, nonce, sanitized filename, formula injection protection).
	 *
	 * Sends headers and output then exits.
	 *
	 * @param string $filename Filename for download.
	 * @param array  $headers  Column headers.
	 * @param array  $data     Rows of data.
	 */
	public static function export_csv_secure( $filename, $headers, $data ) {
		self::check_admin_cap( 'manage_meyvora_convert' );

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cro_export' ) ) {
			wp_die( esc_html__( 'Invalid request', 'meyvora-convert' ), '', array( 'response' => 403 ) );
		}

		$filename = sanitize_file_name( is_string( $filename ) ? $filename : (string) ( $filename ?? '' ) );
		if ( $filename === '' ) {
			$filename = 'cro-export.csv';
		}
		if ( substr( (string) $filename, -4 ) !== '.csv' ) {
			$filename .= '.csv';
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// BOM for Excel compatibility.
		echo "\xEF\xBB\xBF";

		$output = fopen( 'php://output', 'w' );
		if ( ! $output ) {
			return;
		}

		fputcsv( $output, $headers );

		foreach ( $data as $row ) {
			$sanitized_row = array_map(
				function( $cell ) {
					$cell = (string) $cell;
					// Prevent formula injection.
					if ( preg_match( '/^[=+\-@]/', $cell ) ) {
						$cell = "'" . $cell;
					}
					return $cell;
				},
				$row
			);
			fputcsv( $output, $sanitized_row );
		}

		fclose( $output );
		exit;
	}
}
