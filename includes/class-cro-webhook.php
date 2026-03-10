<?php
/**
 * Outbound webhook dispatcher.
 * Fires on CRO conversion events and POSTs JSON to a configured webhook URL.
 * Works with Zapier, Klaviyo, Mailchimp, and any HTTP endpoint.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class CRO_Webhook {

	public static function init() {
		add_action( 'cro_campaign_conversion',   array( __CLASS__, 'on_conversion' ),     10, 2 );
		add_action( 'cro_campaign_impression',   array( __CLASS__, 'on_impression' ),     10, 2 );
		add_action( 'cro_offer_applied',         array( __CLASS__, 'on_coupon_applied' ), 10, 3 );
		add_action( 'cro_offer_coupon_generated', array( __CLASS__, 'on_coupon_generated' ), 10, 3 );
	}

	/**
	 * Check if webhook should fire for this event key.
	 */
	private static function should_fire( $event_key ) {
		if ( ! function_exists( 'cro_settings' ) ) {
			return false;
		}
		$url    = cro_settings()->get( 'integrations', 'webhook_url', '' );
		$events = (array) cro_settings()->get( 'integrations', 'webhook_events', array() );
		return ! empty( $url ) && in_array( $event_key, $events, true );
	}

	/**
	 * Dispatch a fire-and-forget POST to the configured webhook URL.
	 */
	private static function dispatch( $event_key, array $payload ) {
		if ( ! self::should_fire( $event_key ) ) {
			return;
		}
		$url  = cro_settings()->get( 'integrations', 'webhook_url', '' );
		$merged_payload = array_merge(
			array(
				'event'    => $event_key,
				'site_url' => get_site_url(),
				'fired_at' => wp_date( 'c' ),
			),
			$payload
		);

		/**
		 * Filter the outbound webhook payload.
		 *
		 * @param array  $merged_payload Full payload array.
		 * @param string $event_key      Event name (conversion, impression, etc.).
		 */
		$merged_payload = apply_filters( 'cro_webhook_payload', $merged_payload, $event_key );

		$body = wp_json_encode( $merged_payload );
		wp_remote_post( $url, array(
			'body'        => $body,
			'headers'     => array( 'Content-Type' => 'application/json' ),
			'timeout'     => 5,
			'blocking'    => false,
			'data_format' => 'body',
			'sslverify'   => true,
		) );
	}

	public static function on_conversion( $campaign_id, $context ) {
		self::dispatch( 'conversion', array(
			'campaign_id' => (int) $campaign_id,
			'page_type'   => $context['page_type'] ?? '',
			'device'      => $context['device_type'] ?? '',
		) );
	}

	public static function on_impression( $campaign_id, $context ) {
		self::dispatch( 'impression', array(
			'campaign_id' => (int) $campaign_id,
			'page_type'   => $context['page_type'] ?? '',
		) );
	}

	public static function on_coupon_applied( $coupon_code, $offer, $context ) {
		self::dispatch( 'coupon_applied', array(
			'coupon_code' => sanitize_text_field( $coupon_code ),
			'offer_id'    => is_object( $offer ) ? (int) ( $offer->id ?? 0 ) : 0,
		) );
	}

	public static function on_coupon_generated( $coupon_code, $offer, $context ) {
		self::dispatch( 'coupon_generated', array(
			'coupon_code' => sanitize_text_field( $coupon_code ),
			'offer_id'    => is_object( $offer ) ? (int) ( $offer->id ?? 0 ) : 0,
		) );
	}
}
