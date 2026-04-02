<?php
/**
 * Visitor geo (country) for targeting rules.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MEYVC_Geo class.
 *
 * Resolves ISO 3166-1 alpha-2 country when possible (WooCommerce geolocation, Cloudflare header).
 */
class MEYVC_Geo {

	/**
	 * Best-effort country code for the current request.
	 *
	 * @return string Two-letter uppercase, or empty if unknown.
	 */
	public static function detect_country() {
		$country = self::from_cloudflare();
		if ( $country !== '' ) {
			return $country;
		}
		if ( class_exists( 'WC_Geolocation' ) ) {
			$loc = WC_Geolocation::geolocate_ip();
			if ( is_array( $loc ) && ! empty( $loc['country'] ) && is_string( $loc['country'] ) ) {
				$c = strtoupper( substr( sanitize_text_field( $loc['country'] ), 0, 2 ) );
				if ( strlen( $c ) === 2 ) {
					return $c;
				}
			}
		}
		return apply_filters( 'meyvc_geo_country_code', '', null );
	}

	/**
	 * @return string
	 */
	private static function from_cloudflare() {
		if ( empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) || ! is_string( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
			return '';
		}
		$c = strtoupper( substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ), 0, 2 ) );
		if ( strlen( $c ) !== 2 || 'XX' === $c || 'T1' === $c ) {
			return '';
		}
		return $c;
	}

	/**
	 * Normalize array of country codes to uppercase 2-letter.
	 *
	 * @param array $codes Raw codes.
	 * @return string[]
	 */
	public static function normalize_countries( $codes ) {
		$out = array();
		if ( ! is_array( $codes ) ) {
			return $out;
		}
		foreach ( $codes as $c ) {
			$c = strtoupper( substr( sanitize_text_field( (string) $c ), 0, 2 ) );
			if ( strlen( $c ) === 2 ) {
				$out[] = $c;
			}
		}
		return array_values( array_unique( $out ) );
	}
}
