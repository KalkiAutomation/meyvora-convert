<?php
/**
 * Filter bad analytics data
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CRO_Analytics_Filter class.
 *
 * Filters bots, admins, prefetch, and deduplicates impressions.
 */
class CRO_Analytics_Filter {

	/**
	 * Bot user-agent substrings.
	 *
	 * @var array
	 */
	private $bot_patterns = array(
		'bot',
		'crawl',
		'spider',
		'slurp',
		'search',
		'archiver',
		'facebookexternalhit',
		'linkedinbot',
		'twitterbot',
		'pingdom',
		'uptimerobot',
		'gtmetrix',
		'googlebot',
		'bingbot',
		'yandex',
		'baidu',
		'duckduck',
	);

	/**
	 * Check if request should be tracked.
	 *
	 * @return bool
	 */
	public function should_track() {
		// Skip bots.
		if ( $this->is_bot() ) {
			return false;
		}

		// Skip admins (if setting enabled).
		if ( $this->is_admin_user() ) {
			return false;
		}

		// Skip prefetch/prerender.
		if ( $this->is_prefetch() ) {
			return false;
		}

		// Skip AJAX crawlers.
		if ( $this->is_ajax_crawler() ) {
			return false;
		}

		return true;
	}

	/**
	 * Detect bot user agents.
	 *
	 * @return bool
	 */
	public function is_bot() {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';

		if ( empty( $user_agent ) ) {
			return true; // No UA is suspicious.
		}

		foreach ( $this->bot_patterns as $pattern ) {
			if ( strpos( $user_agent, (string) $pattern ) !== false ) {
				return true;
			}
		}

		// Check for headless browsers.
		if ( strpos( $user_agent, 'headless' ) !== false ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if current user is admin (and exclude if setting enabled).
	 *
	 * @return bool
	 */
	public function is_admin_user() {
		if ( ! function_exists( 'cro_settings' ) ) {
			return false;
		}
		if ( ! cro_settings()->get( 'general', 'exclude_admins', true ) ) {
			return false;
		}

		return current_user_can( 'manage_meyvora_convert' );
	}

	/**
	 * Check for prefetch requests.
	 *
	 * @return bool
	 */
	public function is_prefetch() {
		// Check for prefetch header.
		if ( isset( $_SERVER['HTTP_X_MOZ'] ) && 'prefetch' === $_SERVER['HTTP_X_MOZ'] ) {
			return true;
		}

		// Check for purpose header.
		$purpose = isset( $_SERVER['HTTP_PURPOSE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_PURPOSE'] ) ) : ( isset( $_SERVER['HTTP_X_PURPOSE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_PURPOSE'] ) ) : '' );
		if ( in_array( $purpose, array( 'prefetch', 'prerender', 'preview' ), true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check for AJAX crawler.
	 *
	 * @return bool
	 */
	public function is_ajax_crawler() {
		$is_ajax = isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && 'XMLHttpRequest' === $_SERVER['HTTP_X_REQUESTED_WITH'];
		return $is_ajax && $this->is_bot();
	}

	/**
	 * Generate deduplication key for impression.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $visitor_id  Visitor ID.
	 * @param string $page_url    Page URL.
	 * @return string MD5 hash.
	 */
	public function get_impression_key( $campaign_id, $visitor_id, $page_url ) {
		return md5( (string) $campaign_id . (string) $visitor_id . (string) $page_url . gmdate( 'Y-m-d-H' ) ); // Hourly dedup.
	}

	/**
	 * Check if impression already recorded (deduplication).
	 * Sets transient on first call for this key to prevent duplicates for 1 hour.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $visitor_id  Visitor ID.
	 * @param string $page_url    Page URL.
	 * @return bool True if duplicate (already recorded this hour).
	 */
	public function is_duplicate_impression( $campaign_id, $visitor_id, $page_url ) {
		$key         = $this->get_impression_key( $campaign_id, $visitor_id, $page_url );
		$transient_key = 'cro_imp_' . $key;

		if ( get_transient( $transient_key ) ) {
			return true;
		}

		// Set transient to prevent duplicates (1 hour).
		set_transient( $transient_key, 1, HOUR_IN_SECONDS );

		return false;
	}
}
