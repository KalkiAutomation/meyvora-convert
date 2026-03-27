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
	 * Read a GET query parameter with sanitization via filter_input().
	 *
	 * Avoids touching superglobals for read-only admin/query vars.
	 *
	 * @param string $key Query variable name.
	 * @return string Sanitized string; empty if missing.
	 */
	public static function get_query_var( string $key ): string {
		$raw = filter_input( INPUT_GET, $key, FILTER_UNSAFE_RAW );
		if ( null === $raw || false === $raw ) {
			return '';
		}
		$raw = is_string( $raw ) ? $raw : (string) $raw;

		return sanitize_text_field( wp_unslash( $raw ) );
	}

	/**
	 * Absint from GET (via get_query_var).
	 *
	 * @param string $key Query variable name.
	 * @return int
	 */
	public static function get_query_var_absint( string $key ): int {
		$s = self::get_query_var( $key );

		return '' === $s ? 0 : absint( $s );
	}

	/**
	 * Sanitize_key() on a GET value.
	 *
	 * @param string $key Query variable name.
	 * @return string
	 */
	public static function get_query_var_key( string $key ): string {
		$s = self::get_query_var( $key );

		return '' === $s ? '' : sanitize_key( $s );
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
	 * Client IP for rate limiting (REMOTE_ADDR only — not spoofable headers).
	 *
	 * @return string
	 */
	public static function get_client_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: 'unknown';
	}

	/**
	 * Visitor IP for analytics/storage (may be anonymised). Do not use for rate limiting — use get_client_ip().
	 *
	 * @return string
	 */
	public static function get_visitor_ip(): string {
		$ip = '';
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip    = trim( $parts[0] );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		$ip = filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';

		$anonymise = function_exists( 'cro_settings' ) && cro_settings()->get( 'analytics', 'anonymise_ip', false );
		if ( $anonymise && $ip !== '' ) {
			if ( strpos( $ip, ':' ) !== false ) {
				$parts = explode( ':', $ip );
				for ( $i = 3; $i < count( $parts ); $i++ ) {
					$parts[ $i ] = '0';
				}
				$ip = implode( ':', $parts );
			} else {
				$ip = preg_replace( '/\.\d+$/', '.0', $ip );
			}
		}

		return (string) $ip;
	}

	/**
	 * Rate limit check by key.
	 *
	 * Uses object cache increment when persistent object cache is available (atomic);
	 * otherwise falls back to transients (possible TOCTOU under extreme concurrency).
	 *
	 * @param string $key    Unique key (e.g. IP or user id).
	 * @param int    $limit  Max requests in window.
	 * @param int    $window Window in seconds.
	 * @return bool True if under limit, false if exceeded.
	 */
	public static function check_rate_limit( $key, $limit = 10, $window = 60 ) {
		$limit         = (int) max( 1, $limit );
		$window        = (int) max( 1, $window );
		$transient_key = 'cro_rate_' . md5( $key );
		$group         = 'cro_rate_limits';

		if ( wp_using_ext_object_cache() ) {
			if ( wp_cache_add( $transient_key, 1, $group, $window ) ) {
				return true;
			}
			$current = (int) wp_cache_get( $transient_key, $group );
			if ( $current >= $limit ) {
				return false;
			}
			$new = wp_cache_incr( $transient_key, 1, $group );
			if ( false === $new ) {
				return self::check_rate_limit_transient_fallback( $transient_key, $limit, $window );
			}
			return (int) $new <= $limit;
		}

		return self::check_rate_limit_transient_fallback( $transient_key, $limit, $window );
	}

	/**
	 * Transient-based rate limit (fallback when no persistent object cache).
	 *
	 * @param string $transient_key Key.
	 * @param int    $limit         Max.
	 * @param int    $window        Seconds.
	 * @return bool
	 */
	private static function check_rate_limit_transient_fallback( $transient_key, $limit, $window ) {
		$current = get_transient( $transient_key );

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
	 * Format one CSV record line (no stream). Matches common Excel/Spreadsheet formula-injection rules.
	 *
	 * @param array<int|string, string|int|float|bool|null> $cells Cell values.
	 * @return string
	 */
	public static function format_csv_line( $cells ) {
		$cells = array_values( $cells );
		$out   = array();
		foreach ( $cells as $cell ) {
			$cell = (string) $cell;
			if ( preg_match( '/^[=+\-@]/', $cell ) ) {
				$cell = "'" . $cell;
			}
			if ( preg_match( '/[",\n\r]/', $cell ) ) {
				$out[] = '"' . str_replace( '"', '""', $cell ) . '"';
			} else {
				$out[] = $cell;
			}
		}
		return implode( ',', $out );
	}

	/**
	 * Full CSV document: UTF-8 BOM, header row, data rows (newline-separated).
	 *
	 * @param array<int, string>                    $headers Header cells.
	 * @param array<int, array<int|string, mixed>> $rows    Data rows (each row is a list of cells).
	 * @return string
	 */
	public static function build_csv_document( $headers, $rows ) {
		$lines   = array();
		$lines[] = self::format_csv_line( $headers );
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$lines[] = self::format_csv_line( $row );
		}
		return "\xEF\xBB\xBF" . implode( "\n", $lines );
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

		$data_rows = array();
		foreach ( $data as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$data_rows[] = array_values( $row );
		}

		$doc = self::build_csv_document( $headers, $data_rows );
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;
		if ( $wp_filesystem ) {
			$wp_filesystem->put_contents( 'php://output', $doc );
		}
		exit;
	}

	/**
	 * Encrypt a secret for storage (AES-256-CBC). Empty input returns empty string.
	 *
	 * @param string $plaintext Secret.
	 * @return string Base64 blob or ''.
	 */
	public static function encrypt_secret( $plaintext ) {
		if ( ! is_string( $plaintext ) || $plaintext === '' || ! function_exists( 'openssl_encrypt' ) ) {
			return '';
		}
		$salt = wp_salt( 'auth' ) . ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' );
		$key  = substr( hash( 'sha256', $salt ), 0, 32 );
		$iv   = random_bytes( 16 );
		$raw  = openssl_encrypt( $plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		if ( $raw === false ) {
			return '';
		}
		return base64_encode( $iv . $raw );
	}

	/**
	 * Decrypt a value from encrypt_secret().
	 *
	 * @param string $encoded Stored value.
	 * @return string Plaintext or ''.
	 */
	public static function decrypt_secret( $encoded ) {
		if ( ! is_string( $encoded ) || $encoded === '' || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		$bin = base64_decode( $encoded, true );
		if ( $bin === false || strlen( $bin ) < 17 ) {
			return '';
		}
		$iv  = substr( $bin, 0, 16 );
		$ct  = substr( $bin, 16 );
		$salt = wp_salt( 'auth' ) . ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' );
		$key  = substr( hash( 'sha256', $salt ), 0, 32 );
		$plain = openssl_decrypt( $ct, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		return is_string( $plain ) ? $plain : '';
	}
}
