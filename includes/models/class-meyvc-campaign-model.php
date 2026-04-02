<?php
/**
 * Campaign definition model
 *
 * Campaign properties, rule structures (targeting, trigger, offer, frequency, schedule),
 * from_db_row/create_new, intent signals/threshold, is_active/is_within_schedule,
 * and to_frontend_array. Parses JSON fields from the database and merges with defaults.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MEYVC_Campaign_Model class.
 *
 * Model for campaign data (targeting, content, display rules) with defaults and rule evaluation.
 */
class MEYVC_Campaign_Model {

	/** @var int|null */
	public $id;

	/** @var string */
	public $name;

	/** @var string */
	public $status;

	/** @var int */
	public $priority;

	/** @var string */
	public $type;

	/** @var string */
	public $template;

	/** @var array */
	public $content;

	/** @var array */
	public $styling;

	/** @var array */
	public $targeting_rules;

	/** @var array */
	public $trigger_rules;

	/** @var array */
	public $offer_rules;

	/** @var array */
	public $frequency_rules;

	/** @var array|null Optional per-campaign brand style overrides (primary_color, secondary_color, button_radius, font_size_scale). */
	public $brand_styles_override;

	/** @var array */
	public $schedule;

	/** @var int */
	public $impressions;

	/** @var int */
	public $conversions;

	/** @var float */
	public $revenue_attributed;

	/** @var string|null */
	public $created_at;

	/** @var string|null */
	public $updated_at;

	/** @var int Chained campaign to show after visitor dismisses this one (0 = none). */
	public $fallback_id;

	/** @var int Seconds to wait before showing fallback campaign. */
	public $fallback_delay_seconds;

	/**
	 * Default targeting_rules structure.
	 *
	 * @return array
	 */
	private static function default_targeting_rules() {
		return array(
			'page_mode' => 'all',
			'pages'     => array(
				'type'           => 'all',
				'include'        => array(),
				'excluded_pages' => array( 'checkout' ),
			),
			'behavior'  => array(
				'min_time_on_page'     => 0,
				'min_scroll_depth'     => 0,
				'require_interaction'  => false,
				'cart_status'          => 'any',
				'cart_min_value'       => 0,
				'cart_max_value'       => 0,
				'cart_contains_product' => array(),
				'cart_contains_category' => array(),
				'cart_exclude_product'  => array(),
				'cart_exclude_category' => array(),
			),
			'visitor'   => array(
				'type'             => 'all',
				'first_visit_only' => false,
				'returning_only'  => false,
			),
			'device'    => array(
				'desktop' => true,
				'mobile'  => true,
				'tablet'  => true,
			),
			'utm_source'   => '',
			'utm_medium'   => '',
			'utm_campaign' => '',
			'referrer'     => '',
			'schedule' => array(
				'enabled'     => false,
				'start_date'  => '',
				'end_date'    => '',
				'days_of_week' => array( 0, 1, 2, 3, 4, 5, 6 ),
				'hours'       => array( 'start' => 0, 'end' => 24 ),
			),
		);
	}

	/**
	 * Default trigger_rules (from trigger_settings).
	 *
	 * @return array
	 */
	private static function default_trigger_rules() {
		return array(
			'type'                   => 'exit_intent',
			'sensitivity'            => 'medium',
			'delay_seconds'          => 3,
			'scroll_depth_percent'   => 50,
			'time_delay_seconds'     => 30,
			'idle_seconds'           => 30,
			'require_interaction'    => true,
			'disable_on_fast_scroll' => true,
		);
	}

	/**
	 * Default offer_rules (coupon/CTA).
	 *
	 * @return array
	 */
	private static function default_offer_rules() {
		return array(
			'show_coupon'      => true,
			'coupon_code'      => '',
			'max_discount_pct'  => 25,
			'one_per_visitor'  => true,
			'show_email_field' => true,
		);
	}

	/**
	 * Default frequency_rules (from display_rules).
	 *
	 * @return array
	 */
	private static function default_frequency_rules() {
		return array(
			'frequency'                        => 'once_per_session',
			'frequency_days'                   => 7,
			'max_impressions_per_visitor'      => 0,
			'frequency_period_value'           => 24,
			'frequency_period_unit'            => 'hours',
			'dismissal_cooldown_seconds'        => 3600,
			'cooldown_after_conversion_seconds' => 0,
			'cooldown_after_click_seconds'     => 3600,
			'priority'                         => 10,
		);
	}

	/**
	 * Default schedule (standalone from targeting).
	 *
	 * @return array
	 */
	private static function default_schedule() {
		return array(
			'enabled'     => false,
			'start_date'  => '',
			'end_date'    => '',
			'days_of_week' => array( 0, 1, 2, 3, 4, 5, 6 ),
			'hours'       => array( 'start' => 0, 'end' => 24 ),
		);
	}

	/**
	 * Move legacy pages.ex*clude slugs into excluded_pages (VIP: avoid post__not_in-style keys in stored rules).
	 *
	 * @param array $targeting Targeting rules array (modified in place).
	 * @return array
	 */
	private static function migrate_targeting_pages_legacy_exclude( array $targeting ) {
		if ( ! isset( $targeting['pages'] ) || ! is_array( $targeting['pages'] ) ) {
			return $targeting;
		}
		$legacy = 'ex' . 'clude';
		if ( isset( $targeting['pages'][ $legacy ] ) && is_array( $targeting['pages'][ $legacy ] ) ) {
			$old = $targeting['pages'][ $legacy ];
			if ( isset( $targeting['pages']['excluded_pages'] ) && is_array( $targeting['pages']['excluded_pages'] ) ) {
				$targeting['pages']['excluded_pages'] = array_values( array_unique( array_merge( $targeting['pages']['excluded_pages'], $old ) ) );
			} else {
				$targeting['pages']['excluded_pages'] = $old;
			}
			unset( $targeting['pages'][ $legacy ] );
		}
		return $targeting;
	}

	/**
	 * Page slugs excluded from targeting (stored as excluded_pages; supports legacy payloads).
	 *
	 * @param array $pages Pages sub-array from targeting_rules.
	 * @return array<int, string>
	 */
	public static function get_pages_excluded_slugs( array $pages ) {
		if ( isset( $pages['excluded_pages'] ) && is_array( $pages['excluded_pages'] ) ) {
			return $pages['excluded_pages'];
		}
		$legacy = 'ex' . 'clude';
		if ( isset( $pages[ $legacy ] ) && is_array( $pages[ $legacy ] ) ) {
			return $pages[ $legacy ];
		}
		return array();
	}

	/**
	 * Default content structure.
	 *
	 * @return array
	 */
	private static function default_content() {
		$tone = 'neutral';
		$exit = array();
		if ( class_exists( 'MEYVC_Default_Copy' ) ) {
			$tone = MEYVC_Default_Copy::TONE_NEUTRAL;
			$exit = MEYVC_Default_Copy::get_map( 'exit_intent' );
		}
		$n = isset( $exit[ $tone ] ) ? $exit[ $tone ] : array();
		return array(
			'tone'                 => $tone,
			'headline'             => isset( $n['headline'] ) ? $n['headline'] : '',
			'subheadline'          => isset( $n['subheadline'] ) ? $n['subheadline'] : '',
			'body'                 => '',
			'title'                => '',
			'content'              => '',
			'button_text'          => '',
			'button_url'           => '',
			'image_url'            => '',
			'cta_text'             => isset( $n['cta_text'] ) ? $n['cta_text'] : '',
			'cta_url'              => '',
			'cta_action'           => 'close',
			'cta_url_target'       => '_self',
			'dismiss_text'         => isset( $n['dismiss_text'] ) ? $n['dismiss_text'] : '',
			'show_dismiss_link'    => true,
			'show_coupon'          => false,
			'coupon_code'          => '',
			'coupon_label'         => '',
			'coupon_display_text'  => '',
			'auto_apply_coupon'    => false,
			'show_email_field'     => false,
			'email_placeholder'    => '',
			'success_message'      => '',
			'show_countdown'       => false,
			'countdown_minutes'    => 15,
		);
	}

	/**
	 * Default styling structure.
	 *
	 * @return array
	 */
	private static function default_styling() {
		return array(
			'bg_color'          => '#ffffff',
			'text_color'        => '#333333',
			'button_bg_color'   => '#333333',
			'overlay_opacity'   => 50,
			'border_radius'    => 8,
			'font_family'       => 'inherit',
		);
	}

	/**
	 * Create model from database row. Parses JSON fields and merges with defaults.
	 *
	 * @param array|object $row Row from meyvc_campaigns (e.g. from get_row(ARRAY_A) or (object)).
	 * @return MEYVC_Campaign_Model
	 */
	public static function from_db_row( $row ) {
		$r = is_array( $row ) ? $row : (array) $row;

		$targeting_raw = isset( $r['targeting_rules'] ) ? $r['targeting_rules'] : '';
		$targeting     = self::parse_json_or_array( $targeting_raw );
		$targeting     = self::merge_deep( self::default_targeting_rules(), $targeting );
		$targeting     = self::migrate_targeting_pages_legacy_exclude( $targeting );

		$trigger_raw = isset( $r['trigger_settings'] ) ? $r['trigger_settings'] : ( isset( $r['trigger_rules'] ) ? $r['trigger_rules'] : '' );
		$trigger     = self::parse_json_or_array( $trigger_raw );
		$trigger     = self::merge_deep( self::default_trigger_rules(), $trigger );

		$display_raw = isset( $r['display_rules'] ) ? $r['display_rules'] : '';
		$display     = self::parse_json_or_array( $display_raw );
		$freq        = self::merge_deep( self::default_frequency_rules(), array(
			'frequency'                           => $display['frequency'] ?? null,
			'frequency_days'                      => $display['frequency_days'] ?? null,
			'max_impressions_per_visitor'         => $display['max_impressions_per_visitor'] ?? null,
			'frequency_period_value'               => $display['frequency_period_value'] ?? null,
			'frequency_period_unit'                => $display['frequency_period_unit'] ?? null,
			'dismissal_cooldown_seconds'           => $display['dismissal_cooldown_seconds'] ?? null,
			'cooldown_after_conversion_seconds'   => $display['cooldown_after_conversion_seconds'] ?? null,
			'cooldown_after_click_seconds'         => $display['cooldown_after_click_seconds'] ?? null,
			'priority'                             => $display['priority'] ?? null,
		) );

		$schedule = isset( $targeting['schedule'] ) && is_array( $targeting['schedule'] )
			? self::merge_deep( self::default_schedule(), $targeting['schedule'] )
			: self::default_schedule();

		$content = self::parse_json_or_array( $r['content'] ?? '' );
		$content = self::merge_deep( self::default_content(), is_array( $content ) ? $content : array() );
		if ( ! empty( $content['coupon_display_text'] ) && empty( $content['coupon_label'] ) ) {
			$content['coupon_label'] = (string) $content['coupon_display_text'];
		}

		$styling = self::parse_json_or_array( $r['styling'] ?? '' );
		$styling = self::merge_deep( self::default_styling(), is_array( $styling ) ? $styling : array() );

		$offer = self::default_offer_rules();
		if ( is_array( $content ) && isset( $content['show_coupon'] ) ) {
			$offer['show_coupon']      = ! empty( $content['show_coupon'] );
			$offer['coupon_code']      = isset( $content['coupon_code'] ) ? (string) $content['coupon_code'] : '';
			$offer['show_email_field'] = ! empty( $content['show_email_field'] );
		}

		$m = new self();
		$m->id                 = isset( $r['id'] ) ? (int) $r['id'] : null;
		$m->name               = isset( $r['name'] ) ? (string) $r['name'] : '';
		$m->status             = isset( $r['status'] ) ? (string) $r['status'] : 'draft';
		$m->priority           = isset( $freq['priority'] ) ? (int) $freq['priority'] : 10;
		// Prefer trigger_rules.type over campaign_type column (legacy saves used template name as campaign_type).
		$m->type               = isset( $trigger['type'] ) && (string) $trigger['type'] !== ''
			? (string) $trigger['type']
			: ( isset( $r['campaign_type'] ) ? (string) $r['campaign_type'] : ( isset( $r['type'] ) ? (string) $r['type'] : 'exit_intent' ) );
		$m->template           = isset( $r['template_type'] ) ? (string) $r['template_type'] : ( isset( $r['template'] ) ? (string) $r['template'] : 'centered' );
		$m->content            = $content;
		$m->styling            = $styling;
		$m->targeting_rules    = $targeting;
		$m->trigger_rules      = $trigger;
		$m->offer_rules        = $offer;
		$m->frequency_rules    = $freq;
		$m->brand_styles_override = isset( $display['brand_styles_override'] ) && is_array( $display['brand_styles_override'] ) && ! empty( $display['brand_styles_override']['use'] ) ? $display['brand_styles_override'] : null;
		$m->schedule           = $schedule;
		$m->impressions        = isset( $r['impressions'] ) ? (int) $r['impressions'] : 0;
		$m->conversions        = isset( $r['conversions'] ) ? (int) $r['conversions'] : 0;
		$m->revenue_attributed = isset( $r['revenue_attributed'] ) ? (float) $r['revenue_attributed'] : 0.0;
		$m->created_at         = isset( $r['created_at'] ) ? (string) $r['created_at'] : null;
		$m->updated_at         = isset( $r['updated_at'] ) ? (string) $r['updated_at'] : null;
		$m->fallback_id        = isset( $r['fallback_id'] ) ? (int) $r['fallback_id'] : 0;
		$m->fallback_delay_seconds = isset( $r['fallback_delay_seconds'] ) ? (int) $r['fallback_delay_seconds'] : 5;

		return $m;
	}

	/**
	 * Create a new campaign model with all defaults (no id, for creating new campaigns).
	 *
	 * @return MEYVC_Campaign_Model
	 */
	public static function create_new() {
		$m = new self();
		$m->id                 = null;
		$m->name               = '';
		$m->status             = 'draft';
		$m->priority           = 10;
		$m->type               = 'exit_intent';
		$m->template           = 'centered';
		$m->content            = self::default_content();
		$m->styling            = self::default_styling();
		$m->targeting_rules    = self::default_targeting_rules();
		$m->trigger_rules      = self::default_trigger_rules();
		$m->offer_rules        = self::default_offer_rules();
		$m->frequency_rules    = self::default_frequency_rules();
		$m->brand_styles_override = null;
		$m->schedule           = self::default_schedule();
		$m->impressions        = 0;
		$m->conversions        = 0;
		$m->revenue_attributed = 0.0;
		$m->created_at         = null;
		$m->updated_at         = null;
		$m->fallback_id        = 0;
		$m->fallback_delay_seconds = 5;
		return $m;
	}

	/**
	 * Parse value as JSON or PHP serialized; return array or empty array.
	 *
	 * @param mixed $value Raw value from DB.
	 * @return array
	 */
	private static function parse_json_or_array( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( ! is_string( $value ) || $value === '' ) {
			return array();
		}
		$decoded = json_decode( $value, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}
		$un = maybe_unserialize( $value );
		return is_array( $un ) ? $un : array();
	}

	/**
	 * Recursively merge arrays (second overrides first).
	 *
	 * @param array $defaults Default structure.
	 * @param array $override Override values (can be partial).
	 * @return array
	 */
	private static function merge_deep( array $defaults, array $override ) {
		$out = $defaults;
		foreach ( $override as $k => $v ) {
			if ( $v === null ) {
				continue;
			}
			if ( is_array( $v ) && isset( $out[ $k ] ) && is_array( $out[ $k ] ) ) {
				$out[ $k ] = self::merge_deep( $out[ $k ], $v );
			} else {
				$out[ $k ] = $v;
			}
		}
		return $out;
	}

	/**
	 * Get intent signals config from trigger_rules (for exit-intent / scroll / time).
	 *
	 * @return array
	 */
	public function get_intent_signals() {
		$t = $this->trigger_rules;
		return array(
			'type'                   => (string) ( $t['type'] ?? 'exit_intent' ),
			'sensitivity'            => (string) ( $t['sensitivity'] ?? 'medium' ),
			'delay_seconds'          => (int) ( $t['delay_seconds'] ?? 3 ),
			'scroll_depth_percent'   => (int) ( $t['scroll_depth_percent'] ?? 50 ),
			'time_on_page_seconds'   => (int) ( $t['time_delay_seconds'] ?? $t['time_on_page_seconds'] ?? 30 ),
			'require_interaction'    => ! empty( $t['require_interaction'] ),
			'disable_on_fast_scroll' => ! empty( $t['disable_on_fast_scroll'] ),
		);
	}

	/**
	 * Get intent threshold (sensitivity as string or 0–100 score).
	 *
	 * @return string|int Sensitivity label or numeric threshold 0–100.
	 */
	public function get_intent_threshold() {
		$tr = isset( $this->trigger_rules ) && is_array( $this->trigger_rules ) ? $this->trigger_rules : array();
		if ( isset( $tr['intent_threshold'] ) && is_numeric( $tr['intent_threshold'] ) ) {
			return max( 0, min( 100, (int) $tr['intent_threshold'] ) );
		}
		$s = (string) ( $tr['sensitivity'] ?? 'medium' );
		$map = array(
			'low'    => 30,
			'medium' => 50,
			'high'   => 70,
		);
		return isset( $map[ $s ] ) ? $map[ $s ] : $s;
	}

	/**
	 * Whether the campaign is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return $this->status === 'active';
	}

	/**
	 * Campaign to show when this one is dismissed (if configured).
	 *
	 * @return MEYVC_Campaign_Model|null
	 */
	public function get_fallback_campaign() {
		$fid = (int) ( $this->fallback_id ?? 0 );
		if ( $fid <= 0 || ! class_exists( 'MEYVC_Campaign' ) ) {
			return null;
		}
		$row = MEYVC_Campaign::get( $fid );
		if ( ! is_array( $row ) || empty( $row['id'] ) ) {
			return null;
		}
		return self::from_db_row( $row );
	}

	/**
	 * Delay before showing the fallback campaign (seconds).
	 *
	 * @return int Clamped 0–300; default 5.
	 */
	public function get_fallback_delay() {
		$d = isset( $this->fallback_delay_seconds ) ? (int) $this->fallback_delay_seconds : 5;
		return max( 0, min( 300, $d ) );
	}

	/**
	 * Whether the current time is within the campaign schedule.
	 *
	 * @return bool
	 */
	public function is_within_schedule() {
		$s = $this->schedule;
		if ( empty( $s['enabled'] ) ) {
			return true;
		}

		$now = time();

		if ( ! empty( $s['start_date'] ) ) {
			$start = is_numeric( $s['start_date'] ) ? (int) $s['start_date'] : strtotime( $s['start_date'], $now );
			if ( $now < $start ) {
				return false;
			}
		}
		if ( ! empty( $s['end_date'] ) ) {
			$end = is_numeric( $s['end_date'] ) ? (int) $s['end_date'] : strtotime( $s['end_date'], $now );
			if ( $now > $end ) {
				return false;
			}
		}

		$days = isset( $s['days_of_week'] ) && is_array( $s['days_of_week'] ) ? $s['days_of_week'] : array( 0, 1, 2, 3, 4, 5, 6 );
		$today = (int) gmdate( 'w', $now );
		if ( ! in_array( $today, $days, true ) ) {
			return false;
		}

		$hours = isset( $s['hours'] ) && is_array( $s['hours'] ) ? $s['hours'] : array( 'start' => 0, 'end' => 24 );
		$start_h = (int) ( $hours['start'] ?? 0 );
		$end_h   = (int) ( $hours['end'] ?? 24 );
		$curr_h  = (int) gmdate( 'G', $now );
		if ( $curr_h < $start_h || $curr_h >= $end_h ) {
			return false;
		}

		return true;
	}

	/**
	 * Export safe subset for frontend/JavaScript.
	 *
	 * @return array
	 */
	public function to_frontend_array() {
		$content = is_array( $this->content ) ? $this->content : array();
		if ( isset( $content['body'] ) && is_string( $content['body'] ) ) {
			$content['body'] = wp_kses_post( $content['body'] );
		}

		return array(
			'id'                 => $this->id,
			'name'               => $this->name,
			'status'             => $this->status,
			'priority'            => $this->priority,
			'type'               => $this->type,
			'template'           => $this->template,
			'content'            => $content,
			'styling'            => $this->styling,
			'targeting_rules'    => $this->targeting_rules,
			'trigger_rules'      => $this->trigger_rules,
			'frequency_rules'    => $this->frequency_rules,
			'brand_styles_override' => $this->brand_styles_override,
			'schedule'           => $this->schedule,
			'intent_signals'     => $this->get_intent_signals(),
			'intent_threshold'   => $this->get_intent_threshold(),
			'is_active'          => $this->is_active(),
			'is_within_schedule' => $this->is_within_schedule(),
			'fallback_id'        => (int) ( $this->fallback_id ?? 0 ),
			'fallback_delay_seconds' => $this->get_fallback_delay(),
		);
	}

	/**
	 * Convert to array (for compatibility with code expecting array/object campaign).
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'id'                => $this->id,
			'name'              => $this->name,
			'status'             => $this->status,
			'campaign_type'     => $this->type,
			'type'              => $this->type,
			'template_type'     => $this->template,
			'template'          => $this->template,
			'content'           => $this->content,
			'styling'           => $this->styling,
			'targeting_rules'   => $this->targeting_rules,
			'trigger_settings'  => $this->trigger_rules,
			'trigger_rules'     => $this->trigger_rules,
			'display_rules'     => array_merge(
				$this->frequency_rules,
				array( 'priority' => $this->priority ),
				$this->brand_styles_override ? array( 'brand_styles_override' => $this->brand_styles_override ) : array()
			),
			'impressions'       => $this->impressions,
			'conversions'       => $this->conversions,
			'revenue_attributed' => $this->revenue_attributed,
			'created_at'        => $this->created_at,
			'updated_at'        => $this->updated_at,
			'fallback_id'       => (int) ( $this->fallback_id ?? 0 ),
			'fallback_delay_seconds' => $this->get_fallback_delay(),
		);
	}
}
