<?php
/**
 * Klaviyo list subscribe — uses Klaviyo REST API revision 2024-10-15.
 * Profile upsert → list relationship create (two-step flow).
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MEYVC_Klaviyo class.
 */
class MEYVC_Klaviyo {

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
	 * Register hooks.
	 */
	public function __construct() {
		$settings = meyvc_settings()->get_group( 'integrations' );
		if ( empty( $settings['klaviyo_enabled'] ) ) {
			return;
		}
		add_action( 'meyvc_email_captured', array( $this, 'on_email' ), 30, 3 );
		add_action( 'meyvc_abandoned_cart_created', array( $this, 'on_abandoned' ), 30, 1 );
	}

	/**
	 * Subscribe email when captured from a campaign.
	 *
	 * @param string $email       Email.
	 * @param int    $campaign_id Campaign.
	 * @param array  $context     Context.
	 */
	public function on_email( $email, $campaign_id, $context = array() ) {
		unset( $campaign_id, $context );
		$this->subscribe( (string) $email, 'email_captured' );
	}

	/**
	 * When an abandoned cart row is created with email.
	 *
	 * @param int $cart_row_id Row ID.
	 */
	public function on_abandoned( $cart_row_id ) {
		$cart_row_id = absint( $cart_row_id );
		if ( ! $cart_row_id ) {
			return;
		}
		global $wpdb;
		$t     = $wpdb->prefix . 'meyvc_abandoned_carts';
		$ck    = $this->read_cache_key( 'abandoned_cart_email', array( $t, $cart_row_id ) );
		$found = false;
		$email = wp_cache_get( $ck, self::DB_READ_CACHE_GROUP, false, $found );
		if ( ! $found ) {
			$email = $wpdb->get_var( $wpdb->prepare( 'SELECT email FROM %i WHERE id = %d', $t, $cart_row_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
			wp_cache_set( $ck, $email, self::DB_READ_CACHE_GROUP, self::DB_READ_CACHE_TTL );
		}
		if ( is_string( $email ) && is_email( $email ) ) {
			$this->subscribe( $email, 'abandoned_cart' );
		}
	}

	/**
	 * Subscribe an email address to the configured Klaviyo list.
	 * Uses Klaviyo REST API 2024-10-15.
	 *
	 * @param string $email  Email address.
	 * @param string $source Source tag (e.g. 'email_captured', 'abandoned_cart').
	 */
	private function subscribe( string $email, string $source ): void {
		if ( ! is_email( $email ) ) {
			return;
		}

		$settings = meyvc_settings()->get_group( 'integrations' );
		$list_id  = isset( $settings['klaviyo_list_id'] ) ? sanitize_text_field( (string) $settings['klaviyo_list_id'] ) : '';
		$key_enc  = isset( $settings['klaviyo_api_key_enc'] ) ? (string) $settings['klaviyo_api_key_enc'] : '';

		if ( $list_id === '' || $key_enc === '' || ! class_exists( 'MEYVC_Security' ) ) {
			return;
		}

		$api_key = MEYVC_Security::decrypt_secret( $key_enc );
		if ( $api_key === '' ) {
			return;
		}

		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Klaviyo-API-Key ' . $api_key,
			'revision'      => '2024-10-15',
		);

		// Step 1: Upsert the profile.
		$profile_body = wp_json_encode(
			array(
				'data' => array(
					'type'       => 'profile',
					'attributes' => array(
						'email'      => $email,
						'properties' => array(
							'source' => 'Meyvora Convert (' . $source . ')',
						),
					),
				),
			)
		);

		$profile_response = wp_remote_post(
			'https://a.klaviyo.com/api/profiles/',
			array(
				'timeout' => 12,
				'headers' => $headers,
				'body'    => $profile_body,
			)
		);

		if ( is_wp_error( $profile_response ) ) {
			if ( class_exists( 'MEYVC_Error_Handler' ) ) {
				MEYVC_Error_Handler::log( 'KLAVIYO', 'Profile upsert failed: ' . $profile_response->get_error_message(), array( 'email' => $email ) );
			}
			return;
		}

		$profile_code     = (int) wp_remote_retrieve_response_code( $profile_response );
		$profile_body_raw = wp_remote_retrieve_body( $profile_response );
		$profile_data     = json_decode( $profile_body_raw, true );

		// 201 = created, 409 = already exists (both are OK; get profile ID from response or conflict).
		if ( 409 === $profile_code ) {
			// Conflict: profile exists; extract ID from errors[0].meta.duplicate_profile_id
			$profile_id = isset( $profile_data['errors'][0]['meta']['duplicate_profile_id'] )
				? sanitize_text_field( (string) $profile_data['errors'][0]['meta']['duplicate_profile_id'] )
				: '';
		} elseif ( 201 === $profile_code ) {
			$profile_id = isset( $profile_data['data']['id'] )
				? sanitize_text_field( (string) $profile_data['data']['id'] )
				: '';
		} else {
			if ( class_exists( 'MEYVC_Error_Handler' ) ) {
				MEYVC_Error_Handler::log( 'KLAVIYO', 'Unexpected profile status: ' . $profile_code, array( 'email' => $email ) );
			}
			return;
		}

		if ( $profile_id === '' ) {
			return;
		}

		// Step 2: Subscribe the profile to the list.
		$sub_body = wp_json_encode(
			array(
				'data' => array(
					array(
						'type' => 'profile',
						'id'   => $profile_id,
					),
				),
			)
		);

		$sub_response = wp_remote_post(
			'https://a.klaviyo.com/api/lists/' . rawurlencode( $list_id ) . '/relationships/profiles/',
			array(
				'timeout' => 12,
				'headers' => $headers,
				'body'    => $sub_body,
			)
		);

		if ( is_wp_error( $sub_response ) && class_exists( 'MEYVC_Error_Handler' ) ) {
			MEYVC_Error_Handler::log( 'KLAVIYO', 'List subscribe failed: ' . $sub_response->get_error_message(), array( 'email' => $email, 'profile_id' => $profile_id ) );
		}
	}
}
