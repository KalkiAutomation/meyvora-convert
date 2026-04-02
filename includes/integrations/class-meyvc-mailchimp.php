<?php
/**
 * Mailchimp Marketing API — add/update list member.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MEYVC_Mailchimp class.
 */
class MEYVC_Mailchimp {

	/** @var string Object cache group for read-through DB queries. */
	private const DB_READ_CACHE_GROUP = 'meyvora_meyvc';

	/** @var int Read-through TTL (seconds). */
	private const DB_READ_CACHE_TTL = 300;

	/**
	 * @param string                    $descriptor 2–4 word slug.
	 * @param array<int|string|float> $params     Params.
	 * @return string
	 */
	private function read_cache_key( string $descriptor, array $params ): string {
		return 'meyvora_meyvc_' . md5( $descriptor . '_' . implode( '_', array_map( 'strval', $params ) ) );
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$settings = meyvc_settings()->get_group( 'integrations' );
		if ( empty( $settings['mailchimp_enabled'] ) ) {
			return;
		}
		add_action( 'meyvc_email_captured', array( $this, 'on_email' ), 30, 3 );
		add_action( 'meyvc_abandoned_cart_created', array( $this, 'on_abandoned' ), 30, 1 );
	}

	/**
	 * @param string $email       Email.
	 * @param int    $campaign_id Campaign.
	 * @param array  $context     Context.
	 */
	public function on_email( $email, $campaign_id, $context = array() ) {
		unset( $campaign_id, $context );
		$this->upsert_member( (string) $email, 'email_captured' );
	}

	/**
	 * @param int $cart_row_id Abandoned cart row.
	 */
	public function on_abandoned( $cart_row_id ) {
		$cart_row_id = absint( $cart_row_id );
		if ( ! $cart_row_id ) {
			return;
		}
		global $wpdb;
		$t       = $wpdb->prefix . 'meyvc_abandoned_carts';
		$ck      = $this->read_cache_key( 'abandoned_cart_email', array( $t, $cart_row_id ) );
		$found   = false;
		$email   = wp_cache_get( $ck, self::DB_READ_CACHE_GROUP, false, $found );
		if ( ! $found ) {
			$email = $wpdb->get_var( $wpdb->prepare( 'SELECT email FROM %i WHERE id = %d', $t, $cart_row_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
			wp_cache_set( $ck, $email, self::DB_READ_CACHE_GROUP, self::DB_READ_CACHE_TTL );
		}
		if ( is_string( $email ) && is_email( $email ) ) {
			$this->upsert_member( $email, 'abandoned_cart' );
		}
	}

	/**
	 * @param string $email  Email.
	 * @param string $source Tag.
	 */
	private function upsert_member( $email, $source ) {
		if ( ! is_email( $email ) ) {
			return;
		}
		$settings = meyvc_settings()->get_group( 'integrations' );
		$list_id  = isset( $settings['mailchimp_list_id'] ) ? sanitize_text_field( (string) $settings['mailchimp_list_id'] ) : '';
		$key_enc  = isset( $settings['mailchimp_api_key_enc'] ) ? (string) $settings['mailchimp_api_key_enc'] : '';
		$dc       = isset( $settings['mailchimp_dc'] ) ? sanitize_key( (string) $settings['mailchimp_dc'] ) : '';
		if ( $list_id === '' || $key_enc === '' || ! class_exists( 'MEYVC_Security' ) ) {
			return;
		}
		$api_key = MEYVC_Security::decrypt_secret( $key_enc );
		if ( $api_key === '' ) {
			return;
		}
		if ( $dc === '' && strpos( $api_key, '-' ) !== false ) {
			$parts = explode( '-', $api_key );
			$dc    = sanitize_key( end( $parts ) );
		}
		if ( $dc === '' ) {
			return;
		}

		$hash = md5( strtolower( $email ) );
		$url  = 'https://' . $dc . '.api.mailchimp.com/3.0/lists/' . rawurlencode( $list_id ) . '/members/' . $hash;

		$double_optin = ! empty( $settings['mailchimp_double_optin'] );
		$new_status   = $double_optin ? 'pending' : 'subscribed';

		$body = wp_json_encode(
			array(
				'email_address' => $email,
				'status_if_new' => $new_status,
				'status'        => $new_status,
			)
		);

		$response = wp_remote_put(
			$url,
			array(
				'timeout' => 12,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Authorization' => 'Basic ' . base64_encode( 'anystring:' . $api_key ),
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) && class_exists( 'MEYVC_Error_Handler' ) ) {
			MEYVC_Error_Handler::log( 'MAILCHIMP', $response->get_error_message(), array( 'email' => $email ) );
		}
	}
}
