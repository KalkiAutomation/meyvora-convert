<?php
/**
 * Visitor state model
 *
 * Compact cookie-based visitor state (vid, fs, sc, ls, etc.). Tracks sessions,
 * campaigns shown/dismissed/converted, email capture, and coupon use.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CRO_Visitor_State class.
 *
 * Model for persistent visitor state (sessions, campaigns shown, conversions).
 * Uses compact cookie keys and saves on shutdown via WordPress hook.
 */
class CRO_Visitor_State {

	/** Cookie name (compact). */
	const COOKIE_NAME = 'cro_vs';

	/** Cookie for banner view counts (readable by JS for blocks). Not httponly. */
	const BANNER_VIEWS_COOKIE = 'cro_banner_views';

	/** Cookie duration: 30 days. */
	const COOKIE_DURATION_DAYS = 30;

	/** Banner types for frequency capping (shipping_bar, trust, urgency, offer). */
	const BANNER_TYPES = array( 'shipping_bar', 'trust', 'urgency', 'offer' );

	/** Default rolling window for banner cap: 24 hours. */
	const BANNER_CAP_WINDOW_SECONDS = 86400;

	/** Session gap in seconds (30 min = new session). */
	const SESSION_GAP_SECONDS = 1800;

	/** Compact key map: internal state uses short keys. */
	const K_VID = 'vid';
	const K_FS  = 'fs';
	const K_SC  = 'sc';
	const K_LSS = 'lss';
	const K_LA  = 'la';
	const K_CS  = 'cs';
	const K_CD  = 'cd';
	const K_SS  = 'ss';
	const K_LC  = 'lc';
	const K_TC  = 'tc';
	const K_CC  = 'cc';
	const K_UC  = 'uc';
	const K_EM  = 'em';
	const K_CI  = 'ci'; // Campaign impressions: campaign_id => [ ts, ts, ... ] (rolling window)
	const K_CL  = 'cl'; // Campaign last click: campaign_id => ts
	const K_ABA = 'aba'; // A/B attribution: { test_id, variation_id } for last shown variation (order conversion)

	/**
	 * Singleton instance.
	 *
	 * @var CRO_Visitor_State|null
	 */
	private static $instance = null;

	/**
	 * In-memory state (compact keys).
	 *
	 * @var array
	 */
	private $state = array();

	/**
	 * Whether state has changed and must be persisted.
	 *
	 * @var bool
	 */
	private $dirty = false;

	/**
	 * Get singleton instance.
	 *
	 * @return CRO_Visitor_State
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Loads from cookie and registers shutdown save.
	 */
	private function __construct() {
		$this->load_state();
		add_action( 'shutdown', array( $this, 'save' ), 0 );
	}

	/**
	 * Load visitor state from cookie (compact keys).
	 */
	private function load_state() {
		if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			$raw = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
			$decoded = json_decode( base64_decode( $raw ), true );
			if ( is_array( $decoded ) ) {
				$this->state = $decoded;
			}
		}

		$now = time();
		if ( empty( $this->state[ self::K_VID ] ) ) {
			$this->state = array(
				self::K_VID => $this->generate_visitor_id(),
				self::K_FS  => $now,
				self::K_SC  => 1,
				self::K_LSS => $now,
				self::K_LA  => $now,
				self::K_CS  => array(),
				self::K_CD  => array(),
				self::K_SS  => array(),
				self::K_LC  => null,
				self::K_TC  => 0,
				self::K_CC  => array(),
				self::K_UC  => array(),
				self::K_EM  => array(),
				self::K_CI  => array(),
				self::K_CL  => array(),
			);
			$this->dirty = true;
			return;
		}

		// Ensure arrays exist.
		foreach ( array( self::K_CS, self::K_CD, self::K_SS, self::K_CC, self::K_UC, self::K_EM, self::K_CI, self::K_CL ) as $k ) {
			if ( ! isset( $this->state[ $k ] ) || ! is_array( $this->state[ $k ] ) ) {
				$this->state[ $k ] = array();
			}
		}
		// K_CL is campaign_id => timestamp (assoc); ensure it's array.
		if ( ! is_array( $this->state[ self::K_CL ] ) ) {
			$this->state[ self::K_CL ] = array();
		}

		// New session if last activity > 30 min ago.
		$last = (int) ( $this->state[ self::K_LA ] ?? 0 );
		if ( ( $now - $last ) > self::SESSION_GAP_SECONDS ) {
			$this->state[ self::K_SC ]  = (int) ( $this->state[ self::K_SC ] ?? 0 ) + 1;
			$this->state[ self::K_LSS ] = $now;
			$this->state[ self::K_SS ]  = array();
			$this->dirty = true;
		}

		$this->state[ self::K_LA ] = $now;
		if ( ! isset( $this->state[ self::K_TC ] ) ) {
			$this->state[ self::K_TC ] = 0;
		}
		if ( ! isset( $this->state[ self::K_LC ] ) ) {
			$this->state[ self::K_LC ] = null;
		}
	}

	/**
	 * Persist state to cookie (compact keys). No-op if headers sent or not dirty.
	 */
	public function save() {
		if ( $this->dirty && ! headers_sent() ) {
			$payload = base64_encode( wp_json_encode( $this->state ) );
			$duration = self::COOKIE_DURATION_DAYS * DAY_IN_SECONDS;
			setcookie(
				self::COOKIE_NAME,
				$payload,
				time() + $duration,
				defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
				defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
				is_ssl(),
				true
			);
			$this->dirty = false;
		}
	}

	/**
	 * Generate unique visitor ID.
	 *
	 * @return string
	 */
	private function generate_visitor_id() {
		return 'cro_' . bin2hex( random_bytes( 16 ) );
	}

	// --- Getters ---

	/**
	 * Get visitor ID.
	 *
	 * @return string
	 */
	public function get_visitor_id() {
		return (string) ( $this->state[ self::K_VID ] ?? '' );
	}

	/**
	 * Get A/B attribution (last variation shown, for order conversion).
	 *
	 * @return array|null { test_id: int, variation_id: int } or null if none.
	 */
	public function get_ab_attribution() {
		$aba = $this->state[ self::K_ABA ] ?? null;
		if ( ! is_array( $aba ) || empty( $aba['variation_id'] ) ) {
			return null;
		}
		return array(
			'test_id'     => (int) ( $aba['test_id'] ?? 0 ),
			'variation_id' => (int) $aba['variation_id'],
		);
	}

	/**
	 * Set A/B attribution so order conversion can be attributed to this variation.
	 * Call when decide returns show:true with an A/B variation (cookie persists to checkout).
	 *
	 * @param int $test_id     A/B test ID.
	 * @param int $variation_id Variation ID.
	 */
	public function set_ab_attribution( $test_id, $variation_id ) {
		$test_id     = absint( $test_id );
		$variation_id = absint( $variation_id );
		if ( ! $test_id || ! $variation_id ) {
			return;
		}
		$this->state[ self::K_ABA ] = array(
			'test_id'     => $test_id,
			'variation_id' => $variation_id,
		);
		$this->state[ self::K_LA ] = time();
		$this->dirty = true;
	}

	/**
	 * Get first-seen timestamp.
	 *
	 * @return int
	 */
	public function get_first_seen() {
		return (int) ( $this->state[ self::K_FS ] ?? 0 );
	}

	/**
	 * Get session count.
	 *
	 * @return int
	 */
	public function get_session_count() {
		return (int) ( $this->state[ self::K_SC ] ?? 0 );
	}

	/**
	 * Get last session start timestamp.
	 *
	 * @return int
	 */
	public function get_last_session_start() {
		return (int) ( $this->state[ self::K_LSS ] ?? 0 );
	}

	/**
	 * Get last activity timestamp.
	 *
	 * @return int
	 */
	public function get_last_activity() {
		return (int) ( $this->state[ self::K_LA ] ?? 0 );
	}

	/**
	 * Get last time a campaign was shown.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return int|null Timestamp or null if never shown.
	 */
	public function get_campaign_last_shown( $campaign_id ) {
		$id = (int) $campaign_id;
		$cs = $this->state[ self::K_CS ] ?? array();
		return isset( $cs[ $id ] ) ? (int) $cs[ $id ] : null;
	}

	/**
	 * Get last time a campaign was dismissed.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return int|null Timestamp or null if never dismissed.
	 */
	public function get_campaign_last_dismiss( $campaign_id ) {
		$id = (int) $campaign_id;
		$cd = $this->state[ self::K_CD ] ?? array();
		return isset( $cd[ $id ] ) ? (int) $cd[ $id ] : null;
	}

	/**
	 * Get last conversion timestamp.
	 *
	 * @return int|null Timestamp or null if never converted.
	 */
	public function get_last_conversion_time() {
		$lc = $this->state[ self::K_LC ] ?? null;
		return $lc !== null && $lc !== '' ? (int) $lc : null;
	}

	/**
	 * Get total conversions count.
	 *
	 * @return int
	 */
	public function get_total_conversions() {
		return (int) ( $this->state[ self::K_TC ] ?? 0 );
	}

	/**
	 * Get raw state (compact keys). For debugging/internal use.
	 *
	 * @return array
	 */
	public function get_state() {
		return $this->state;
	}

	// --- Query methods ---

	/**
	 * Whether the campaign was ever shown.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return bool
	 */
	public function was_campaign_shown( $campaign_id ) {
		return $this->get_campaign_last_shown( $campaign_id ) !== null;
	}

	/**
	 * Count of distinct campaigns shown this session.
	 *
	 * @return int
	 */
	public function get_session_shown_count() {
		$ss = $this->state[ self::K_SS ] ?? array();
		return is_array( $ss ) ? count( $ss ) : 0;
	}

	/**
	 * Whether the campaign was shown this session.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return bool
	 */
	public function was_shown_this_session( $campaign_id ) {
		$id = (int) $campaign_id;
		$ss = is_array( $this->state[ self::K_SS ] ?? null ) ? $this->state[ self::K_SS ] : array();
		return in_array( $id, $ss, true );
	}

	/**
	 * Whether the visitor has converted on this campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return bool
	 */
	public function has_converted_on_campaign( $campaign_id ) {
		$id = (int) $campaign_id;
		$cc = $this->state[ self::K_CC ] ?? array();
		return isset( $cc[ $id ] );
	}

	/**
	 * Get timestamp when visitor last converted on this campaign (for cooldown).
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return int|null Timestamp or null.
	 */
	public function get_campaign_conversion_time( $campaign_id ) {
		$id = (int) $campaign_id;
		$cc = $this->state[ self::K_CC ] ?? array();
		if ( ! isset( $cc[ $id ] ) ) {
			return null;
		}
		$ts = $cc[ $id ];
		return $ts !== null && $ts !== '' ? (int) $ts : null;
	}

	/**
	 * Whether the visitor has used this coupon.
	 *
	 * @param string $coupon_code Coupon code.
	 * @return bool
	 */
	public function has_used_coupon( $coupon_code ) {
		$code = strtolower( trim( (string) ( $coupon_code ?? '' ) ) );
		if ( $code === '' ) {
			return false;
		}
		$uc = is_array( $this->state[ self::K_UC ] ?? null ) ? $this->state[ self::K_UC ] : array();
		return in_array( $code, array_map( 'strtolower', $uc ), true );
	}

	/**
	 * Whether this is a new visitor (first session).
	 *
	 * @return bool
	 */
	public function is_new_visitor() {
		return (int) ( $this->state[ self::K_SC ] ?? 0 ) <= 1;
	}

	// --- Recorder methods ---

	/** Max impression timestamps to keep per campaign (for frequency cap). */
	const IMPRESSION_LIST_MAX = 100;

	/**
	 * Record that a campaign was shown.
	 *
	 * @param int $campaign_id Campaign ID.
	 */
	public function record_campaign_shown( $campaign_id ) {
		$id = (int) $campaign_id;
		$now = time();
		if ( ! isset( $this->state[ self::K_CS ] ) ) {
			$this->state[ self::K_CS ] = array();
		}
		$this->state[ self::K_CS ][ $id ] = $now;
		if ( ! isset( $this->state[ self::K_SS ] ) ) {
			$this->state[ self::K_SS ] = array();
		}
		if ( ! in_array( $id, $this->state[ self::K_SS ], true ) ) {
			$this->state[ self::K_SS ][] = $id;
		}
		$this->record_campaign_impression( $id );
		$this->state[ self::K_LA ] = $now;
		$this->dirty = true;
	}

	/**
	 * Record an impression for frequency capping (max X per Y period).
	 *
	 * @param int $campaign_id Campaign ID.
	 */
	public function record_campaign_impression( $campaign_id ) {
		$id = (int) $campaign_id;
		$now = time();
		if ( ! isset( $this->state[ self::K_CI ] ) || ! is_array( $this->state[ self::K_CI ] ) ) {
			$this->state[ self::K_CI ] = array();
		}
		if ( ! isset( $this->state[ self::K_CI ][ $id ] ) || ! is_array( $this->state[ self::K_CI ][ $id ] ) ) {
			$this->state[ self::K_CI ][ $id ] = array();
		}
		$this->state[ self::K_CI ][ $id ][] = $now;
		// Keep only last N timestamps to avoid cookie bloat.
		$list = $this->state[ self::K_CI ][ $id ];
		if ( count( $list ) > self::IMPRESSION_LIST_MAX ) {
			$this->state[ self::K_CI ][ $id ] = array_slice( $list, -self::IMPRESSION_LIST_MAX );
		}
		$this->dirty = true;
	}

	/**
	 * Get count of impressions for a campaign within the last $period_seconds.
	 *
	 * @param int $campaign_id   Campaign ID.
	 * @param int $period_seconds Rolling window in seconds.
	 * @return int
	 */
	public function get_impression_count_in_window( $campaign_id, $period_seconds ) {
		$id = (int) $campaign_id;
		$period_seconds = (int) $period_seconds;
		$ci = $this->state[ self::K_CI ] ?? array();
		if ( ! is_array( $ci ) || ! isset( $ci[ $id ] ) || ! is_array( $ci[ $id ] ) ) {
			return 0;
		}
		$now = time();
		$cutoff = $now - $period_seconds;
		$count = 0;
		foreach ( $ci[ $id ] as $ts ) {
			if ( (int) $ts >= $cutoff ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Get last click timestamp for a campaign (for cooldown after click).
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return int|null Timestamp or null.
	 */
	public function get_campaign_last_click( $campaign_id ) {
		$id = (int) $campaign_id;
		$cl = $this->state[ self::K_CL ] ?? array();
		if ( ! is_array( $cl ) || ! isset( $cl[ $id ] ) ) {
			return null;
		}
		$ts = $cl[ $id ];
		return $ts !== null && $ts !== '' ? (int) $ts : null;
	}

	/**
	 * Record that the visitor clicked the CTA (or converted) on a campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 */
	public function record_campaign_click( $campaign_id ) {
		$id = (int) $campaign_id;
		$now = time();
		if ( ! isset( $this->state[ self::K_CL ] ) || ! is_array( $this->state[ self::K_CL ] ) ) {
			$this->state[ self::K_CL ] = array();
		}
		$this->state[ self::K_CL ][ $id ] = $now;
		$this->state[ self::K_LA ] = $now;
		$this->dirty = true;
	}

	/**
	 * Record that a campaign was dismissed.
	 *
	 * @param int $campaign_id Campaign ID.
	 */
	public function record_campaign_dismissed( $campaign_id ) {
		$id = (int) $campaign_id;
		$now = time();
		if ( ! isset( $this->state[ self::K_CD ] ) ) {
			$this->state[ self::K_CD ] = array();
		}
		$this->state[ self::K_CD ][ $id ] = $now;
		$this->state[ self::K_LA ] = $now;
		$this->dirty = true;
	}

	/**
	 * Record a conversion, optionally for a campaign.
	 *
	 * @param int|null $campaign_id Campaign ID or null.
	 */
	public function record_conversion( $campaign_id = null ) {
		$now = time();
		$this->state[ self::K_LC ] = $now;
		$this->state[ self::K_TC ] = (int) ( $this->state[ self::K_TC ] ?? 0 ) + 1;
		if ( $campaign_id !== null ) {
			$id = (int) $campaign_id;
			if ( ! isset( $this->state[ self::K_CC ] ) ) {
				$this->state[ self::K_CC ] = array();
			}
			$this->state[ self::K_CC ][ $id ] = $now;
		}
		$this->state[ self::K_LA ] = $now;
		$this->dirty = true;
	}

	/**
	 * Record that an email was captured (e.g. for a campaign). No PII stored.
	 *
	 * @param int $campaign_id Campaign ID.
	 */
	public function record_email_captured( $campaign_id ) {
		$id = (int) $campaign_id;
		$now = time();
		if ( ! isset( $this->state[ self::K_EM ] ) ) {
			$this->state[ self::K_EM ] = array();
		}
		$this->state[ self::K_EM ][ $id ] = $now;
		$this->state[ self::K_LA ] = $now;
		$this->dirty = true;
	}

	/**
	 * Record that a coupon was used (for offer guard / abuse prevention).
	 *
	 * @param string $coupon_code Coupon code.
	 */
	public function record_coupon_used( $coupon_code ) {
		$code = strtolower( trim( (string) ( $coupon_code ?? '' ) ) );
		if ( $code === '' ) {
			return;
		}
		$uc = is_array( $this->state[ self::K_UC ] ?? null ) ? $this->state[ self::K_UC ] : array();
		if ( ! in_array( $code, array_map( 'strtolower', $uc ), true ) ) {
			$uc[] = $code;
			$this->state[ self::K_UC ] = $uc;
		}
		$this->state[ self::K_LA ] = time();
		$this->dirty = true;
	}

	// --- Export ---

	/**
	 * Export full state with expanded keys for internal/PHP use.
	 *
	 * @return array
	 */
	public function to_array() {
		$cs = $this->state[ self::K_CS ] ?? array();
		$cd = $this->state[ self::K_CD ] ?? array();
		$cc = $this->state[ self::K_CC ] ?? array();
		return array(
			'visitor_id'          => $this->get_visitor_id(),
			'first_seen'          => $this->get_first_seen(),
			'session_count'       => $this->get_session_count(),
			'last_session_start'  => $this->get_last_session_start(),
			'last_activity'       => $this->get_last_activity(),
			'campaigns_shown'     => $cs,
			'campaigns_dismissed' => $cd,
			'shown_this_session'  => $this->state[ self::K_SS ] ?? array(),
			'last_conversion'     => $this->get_last_conversion_time(),
			'total_conversions'   => $this->get_total_conversions(),
			'converted_campaigns' => $cc,
			'used_coupons'        => $this->state[ self::K_UC ] ?? array(),
			'emails_captured'     => $this->state[ self::K_EM ] ?? array(),
			'campaign_clicks'     => $this->state[ self::K_CL ] ?? array(),
		);
	}

	/**
	 * Export minimal, safe data for frontend/JS (no PII).
	 *
	 * @return array
	 */
	public function to_frontend_array() {
		return array(
			'visitorId'               => $this->get_visitor_id(),
			'sessionCount'            => $this->get_session_count(),
			'lastSessionStart'        => $this->get_last_session_start(),
			'lastActivity'            => $this->get_last_activity(),
			'campaignsShownThisSession' => array_values( $this->state[ self::K_SS ] ?? array() ),
			'isNewVisitor'            => $this->is_new_visitor(),
		);
	}

	// --- Banner frequency capping (separate cookie so JS can read/write for blocks) ---

	/**
	 * Get banner view timestamps from cookie (type => [ ts, ts, ... ]). Only includes last 24h per type.
	 *
	 * @return array<string, array<int>>
	 */
	public function get_banner_views_data() {
		$raw = isset( $_COOKIE[ self::BANNER_VIEWS_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::BANNER_VIEWS_COOKIE ] ) ) : '';
		$data = array();
		if ( $raw !== '' ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$data = $decoded;
			}
		}
		$cutoff = time() - self::BANNER_CAP_WINDOW_SECONDS;
		$out = array();
		foreach ( self::BANNER_TYPES as $type ) {
			$list = isset( $data[ $type ] ) && is_array( $data[ $type ] ) ? $data[ $type ] : array();
			$out[ $type ] = array_values( array_filter( array_map( 'intval', $list ), function ( $ts ) use ( $cutoff ) {
				return $ts >= $cutoff;
			} ) );
		}
		return $out;
	}

	/**
	 * Get count of times a banner type was shown in the last $window_seconds.
	 *
	 * @param string $type            Banner type (shipping_bar, trust, urgency, offer).
	 * @param int    $window_seconds  Rolling window in seconds. Default 24h.
	 * @return int
	 */
	public function get_banner_show_count( $type, $window_seconds = self::BANNER_CAP_WINDOW_SECONDS ) {
		if ( ! in_array( $type, self::BANNER_TYPES, true ) ) {
			return 0;
		}
		$data = $this->get_banner_views_data();
		$list = $data[ $type ] ?? array();
		if ( $window_seconds !== self::BANNER_CAP_WINDOW_SECONDS ) {
			$cutoff = time() - (int) $window_seconds;
			$list = array_values( array_filter( $list, function ( $ts ) use ( $cutoff ) {
				return $ts >= $cutoff;
			} ) );
		}
		return count( $list );
	}

	/**
	 * Whether the visitor can be shown this banner (under the cap).
	 *
	 * @param string $type          Banner type (shipping_bar, trust, urgency, offer).
	 * @param int    $max_per_24h   Max shows per 24h. 0 = unlimited.
	 * @return bool
	 */
	public function can_show_banner( $type, $max_per_24h = 0 ) {
		if ( (int) $max_per_24h <= 0 ) {
			return true;
		}
		return $this->get_banner_show_count( $type ) < (int) $max_per_24h;
	}

	/**
	 * Record that a banner was shown (persists to cookie; readable by JS).
	 *
	 * @param string $type Banner type (shipping_bar, trust, urgency, offer).
	 */
	public function record_banner_show( $type ) {
		if ( ! in_array( $type, self::BANNER_TYPES, true ) || headers_sent() ) {
			return;
		}
		$data = $this->get_banner_views_data();
		$now = time();
		if ( ! isset( $data[ $type ] ) || ! is_array( $data[ $type ] ) ) {
			$data[ $type ] = array();
		}
		$data[ $type ][] = $now;
		// Keep last 100 per type to avoid cookie size issues.
		if ( count( $data[ $type ] ) > 100 ) {
			$data[ $type ] = array_slice( $data[ $type ], -100 );
		}
		$payload = wp_json_encode( $data );
		$duration = self::COOKIE_DURATION_DAYS * DAY_IN_SECONDS;
		setcookie(
			self::BANNER_VIEWS_COOKIE,
			$payload,
			time() + $duration,
			defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
			defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
			is_ssl(),
			false
		);
	}

	/**
	 * Clear state and cookie (e.g. for testing).
	 */
	public function clear() {
		$this->state = array();
		$this->dirty = true;
		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE_NAME,
				'',
				time() - 3600,
				defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
				defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : ''
			);
			setcookie(
				self::BANNER_VIEWS_COOKIE,
				'',
				time() - 3600,
				defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
				defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : ''
			);
		}
	}
}
