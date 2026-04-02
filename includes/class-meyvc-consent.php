<?php
/**
 * Cookie consent integration.
 *
 * Auto-detects active consent plugins and wires up the meyvc_consent_allows_popup filter.
 * Supported: Complianz, CookieYes, Borlabs Cookie, Cookiebot, GDPR Cookie Compliance.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MEYVC_Consent
 */
class MEYVC_Consent {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		if ( ! self::consent_checking_enabled() ) {
			return;
		}
		add_filter( 'meyvc_consent_allows_popup', array( __CLASS__, 'check_consent' ), 10, 1 );
	}

	/**
	 * Whether the store admin has enabled consent checking.
	 */
	private static function consent_checking_enabled(): bool {
		if ( ! function_exists( 'meyvc_settings' ) ) {
			return false;
		}
		return (bool) meyvc_settings()->get( 'general', 'require_cookie_consent', false );
	}

	/**
	 * Check whether the visitor has given marketing/functional consent.
	 * Returns true (allow popups) when consent is given or detection is inconclusive.
	 *
	 * @param bool $allow Default allow from core.
	 * @return bool
	 */
	public static function check_consent( $allow = true ): bool {
		unset( $allow );

		// 1. Complianz (GDPR/CCPA) — cmplz_has_consent().
		if ( function_exists( 'cmplz_has_consent' ) ) {
			return (bool) cmplz_has_consent( 'marketing' );
		}

		// 2. CookieYes — cookie name cky-consent, value 'yes'.
		if ( defined( 'CKY_DB_VERSION' ) || class_exists( 'Cookie_Law_Info' ) ) {
			$val = isset( $_COOKIE['cky-consent'] )
				? sanitize_text_field( wp_unslash( $_COOKIE['cky-consent'] ) )
				: '';
			if ( $val !== '' ) {
				return $val === 'yes';
			}
		}

		// 3. Borlabs Cookie — BorlabsCookie class or _borlabs-cookie cookie.
		if ( class_exists( '\BorlabsCookie\Cookie\Frontend\Consent' ) ) {
			try {
				$consent = \BorlabsCookie\Cookie\Frontend\Consent::getInstance();
				if ( $consent && method_exists( $consent, 'hasConsent' ) ) {
					return (bool) $consent->hasConsent( 'marketing' );
				}
			} catch ( \Throwable $e ) {
				// Optional: site-specific logging; empty pipeline is fine — cookie fallback runs next.
				do_action( 'meyvc_borlabs_consent_api_error', $e );
			}
		}
		$borlabs_cookie = filter_input( INPUT_COOKIE, '_borlabs-cookie', FILTER_UNSAFE_RAW );
		if ( is_string( $borlabs_cookie ) && $borlabs_cookie !== '' ) {
			$borlabs = urldecode( wp_unslash( $borlabs_cookie ) );
			$raw     = json_decode( sanitize_text_field( $borlabs ), true );
			return isset( $raw['consents']['marketing'] ) ? (bool) $raw['consents']['marketing'] : false;
		}

		// 4. Cookiebot — CookieConsent object set as cookie.
		if ( isset( $_COOKIE['CookieConsent'] ) ) {
			$val     = sanitize_text_field( wp_unslash( $_COOKIE['CookieConsent'] ) );
			$decoded = json_decode( urldecode( $val ), true );
			if ( is_array( $decoded ) ) {
				return isset( $decoded['marketing'] ) ? (bool) $decoded['marketing'] : false;
			}
		}

		// 5. GDPR Cookie Compliance (Moove) — moove_gdpr_popup cookie.
		if ( isset( $_COOKIE['moove_gdpr_popup'] ) ) {
			$val     = sanitize_text_field( wp_unslash( $_COOKIE['moove_gdpr_popup'] ) );
			$decoded = json_decode( $val, true );
			if ( is_array( $decoded ) ) {
				// Strict equivalent of legacy `third_party == '1'` (bool true / int 1 / string '1' / float 1.0).
				return isset( $decoded['third_party'] )
					&& (string) $decoded['third_party'] === '1';
			}
		}

		// No consent plugin detected — allow by default so non-EU stores are unaffected.
		return true;
	}
}
