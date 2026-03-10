<?php
/**
 * Decision engine
 *
 * Central brain: decides which campaign (if any) to show for a given context,
 * visitor state, and intent signals. Executes 8 steps in order, logs every step
 * to the decision's debug_log, returns early on any failure. Only ONE campaign
 * per pageview.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CRO_Decision_Engine class.
 *
 * Singleton. decide() runs: consent → visitor suppression → active campaigns →
 * targeting → intent score/threshold → priority → frequency → final decision.
 */
class CRO_Decision_Engine {

	/**
	 * Singleton instance.
	 *
	 * @var CRO_Decision_Engine|null
	 */
	private static $instance = null;

	/**
	 * Whether a campaign was shown this pageview (only one per request).
	 *
	 * @var bool
	 */
	private $shown_this_pageview = false;

	/**
	 * Get singleton instance.
	 *
	 * @return CRO_Decision_Engine
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Main entry: run the 8-step decision pipeline.
	 *
	 * @param CRO_Context      $context       Request/visit context.
	 * @param CRO_Visitor_State $visitor_state Visitor state (cookie/session).
	 * @param array            $intent_signals Active intent signals (e.g. exit_mouse, time_on_page).
	 * @param string           $trigger_type  Optional. Current trigger that fired (exit_intent, time, scroll, inactivity, page_load, click).
	 * @return CRO_Decision
	 */
	public function decide( CRO_Context $context, CRO_Visitor_State $visitor_state, array $intent_signals = array(), $trigger_type = '' ) {
		$decision = new CRO_Decision( false, null, '', 'pending' );

		// Step 1: Consent check
		if ( ! $this->check_consent() ) {
			$decision->log( 'SKIP', __( 'Consent not given; popups disabled.', 'meyvora-convert' ), array( 'step' => 1 ) );
			$decision->reason = __( 'Consent not given.', 'meyvora-convert' );
			$decision->reason_code = 'consent';
			return $decision;
		}
		$decision->log( 'INFO', __( 'Consent OK.', 'meyvora-convert' ), array( 'step' => 1 ) );

		// Step 2: Visitor suppression (admin, shown this pageview, max per session, post-conversion, checkout)
		$suppression = $this->check_visitor_suppression( $context, $visitor_state );
		if ( $suppression !== null ) {
			$decision->log( 'SKIP', $suppression, array( 'step' => 2 ) );
			$decision->reason = $suppression;
			$decision->reason_code = 'suppression';
			return $decision;
		}
		$decision->log( 'INFO', __( 'Visitor suppression OK.', 'meyvora-convert' ), array( 'step' => 2 ) );

		// Step 3: Get active campaigns from database
		$campaigns = $this->get_active_campaigns();
		if ( empty( $campaigns ) ) {
			$decision->log( 'SKIP', __( 'No active campaigns.', 'meyvora-convert' ), array( 'step' => 3 ) );
			$decision->reason = __( 'No active campaigns.', 'meyvora-convert' );
			$decision->reason_code = 'no_campaigns';
			return $decision;
		}
		$decision->log( 'INFO', sprintf( __( 'Found %d active campaign(s).', 'meyvora-convert' ), count( $campaigns ) ), array( 'step' => 3, 'count' => count( $campaigns ) ) );

		// Step 4: Evaluate each campaign against targeting rules
		$rule_engine = new CRO_Rule_Engine();
		$eligible = array();
		foreach ( $campaigns as $campaign ) {
			$rules = $this->targeting_rules_for_engine( $campaign );
			$result = $rule_engine->evaluate( $rules, $context );
			$decision->record_campaign_result( $campaign->id, $result['passed'], $result['details'] );
			if ( $result['passed'] ) {
				$eligible[] = $campaign;
				$decision->log( 'RULE', sprintf( __( 'Campaign %d passed targeting.', 'meyvora-convert' ), $campaign->id ), array( 'campaign_id' => $campaign->id ) );
			} else {
				$decision->log( 'RULE', sprintf( __( 'Campaign %d failed targeting.', 'meyvora-convert' ), $campaign->id ), array( 'campaign_id' => $campaign->id, 'details' => $result['details'] ) );
			}
		}

		if ( empty( $eligible ) ) {
			$fallback = $this->get_fallback_campaign();
			if ( $fallback !== null ) {
				$eligible = array( $fallback );
				$decision->log( 'INFO', __( 'Using fallback campaign.', 'meyvora-convert' ), array( 'campaign_id' => $fallback->id ) );
			}
		}
		if ( empty( $eligible ) ) {
			$decision->log( 'SKIP', __( 'No campaigns passed targeting.', 'meyvora-convert' ), array( 'step' => 4 ) );
			$decision->reason = __( 'No matching campaigns.', 'meyvora-convert' );
			$decision->reason_code = 'no_targeting_match';
			return $decision;
		}

		// Filter by trigger type so only campaigns matching the current trigger are considered
		if ( $trigger_type !== '' && $trigger_type !== null ) {
			$eligible = $this->filter_eligible_by_trigger( $eligible, $trigger_type, $context, $decision );
			if ( empty( $eligible ) ) {
				$decision->log( 'SKIP', __( 'No campaigns match current trigger.', 'meyvora-convert' ), array( 'step' => 'trigger_filter', 'trigger_type' => $trigger_type ) );
				$decision->reason = __( 'No campaign for this trigger.', 'meyvora-convert' );
				$decision->reason_code = 'trigger_mismatch';
				return $decision;
			}
		}

		$decision->log( 'INFO', sprintf( __( '%d campaign(s) passed targeting.', 'meyvora-convert' ), count( $eligible ) ), array( 'step' => 4 ) );

		// Step 5: Intent score and threshold (for exit_intent) or treat trigger condition as met (time/scroll/inactivity/page_load)
		$trigger_only_types = array( 'time', 'scroll', 'inactivity', 'page_load', 'click' );
		$passed_intent = array();
		if ( in_array( $trigger_type, $trigger_only_types, true ) ) {
			// Trigger condition was already validated in filter_eligible_by_trigger; skip intent scoring
			foreach ( $eligible as $campaign ) {
				$passed_intent[] = array( 'campaign' => $campaign, 'score' => 100, 'threshold' => 0 );
			}
			$decision->log( 'INFO', sprintf( __( '%d campaign(s) passed (trigger condition met).', 'meyvora-convert' ), count( $passed_intent ) ), array( 'step' => 5, 'trigger_type' => $trigger_type ) );
		} else {
			$intent_scorer = new CRO_Intent_Scorer();
			foreach ( $eligible as $campaign ) {
				$threshold = is_numeric( $campaign->get_intent_threshold() ) ? (int) $campaign->get_intent_threshold() : 50;
				$score_result = $intent_scorer->calculate_score( $intent_signals, array() );
				$score = isset( $score_result['score'] ) ? (float) $score_result['score'] : 0;
				$meets = $intent_scorer->meets_threshold( $score, $threshold );
				if ( $meets ) {
					$passed_intent[] = array( 'campaign' => $campaign, 'score' => $score, 'threshold' => $threshold );
					$decision->log( 'UX', sprintf( __( 'Campaign %d passed intent (score %s >= %s).', 'meyvora-convert' ), $campaign->id, $score, $threshold ), array( 'campaign_id' => $campaign->id, 'score' => $score, 'threshold' => $threshold ) );
				} else {
					$decision->log( 'UX', sprintf( __( 'Campaign %d failed intent (score %s < %s).', 'meyvora-convert' ), $campaign->id, $score, $threshold ), array( 'campaign_id' => $campaign->id, 'score' => $score, 'threshold' => $threshold ) );
				}
			}
		}

		if ( empty( $passed_intent ) ) {
			$decision->log( 'SKIP', __( 'No campaign met intent threshold.', 'meyvora-convert' ), array( 'step' => 5 ) );
			$decision->reason = __( 'Intent threshold not met.', 'meyvora-convert' );
			$decision->reason_code = 'intent_threshold';
			return $decision;
		}

		$decision->log( 'INFO', sprintf( __( '%d campaign(s) passed intent.', 'meyvora-convert' ), count( $passed_intent ) ), array( 'step' => 5 ) );

		// Step 6: Priority resolution (highest priority wins)
		usort(
			$passed_intent,
			function ( $a, $b ) {
				$p_a = isset( $a['campaign']->priority ) ? (int) $a['campaign']->priority : 10;
				$p_b = isset( $b['campaign']->priority ) ? (int) $b['campaign']->priority : 10;
				return $p_b - $p_a;
			}
		);
		$winner_entry = $passed_intent[0];
		$winner = $winner_entry['campaign'];
		$decision->log( 'INFO', sprintf( __( 'Priority winner: campaign %d.', 'meyvora-convert' ), $winner->id ), array( 'step' => 6, 'campaign_id' => $winner->id, 'priority' => $winner->priority ) );

		// Step 7: Frequency check (once_ever, once_per_session, etc.)
		if ( ! $this->check_frequency( $winner, $visitor_state ) ) {
			$decision->log( 'SKIP', sprintf( __( 'Campaign %d suppressed by frequency.', 'meyvora-convert' ), $winner->id ), array( 'step' => 7 ) );
			$decision->reason = __( 'Frequency limit reached.', 'meyvora-convert' );
			$decision->reason_code = 'frequency';
			return $decision;
		}
		$decision->log( 'INFO', __( 'Frequency OK for winner.', 'meyvora-convert' ), array( 'step' => 7 ) );

		// Offer eligibility (e.g. coupon one-per-visitor) — gate before show
		if ( ! $this->check_offer_eligibility( $winner, $visitor_state ) ) {
			$decision->log( 'SKIP', sprintf( __( 'Campaign %d failed offer eligibility.', 'meyvora-convert' ), $winner->id ), array( 'step' => 'offer' ) );
			$decision->reason = __( 'Offer not eligible.', 'meyvora-convert' );
			$decision->reason_code = 'offer_eligibility';
			return $decision;
		}

		// A/B test: if there is a running test for the winner campaign, select variation and replace payload
		$campaign_to_show = $winner;
		if ( class_exists( 'CRO_AB_Test' ) && class_exists( 'CRO_Campaign_Model' ) ) {
			$ab_model    = new CRO_AB_Test();
			$active_test = $ab_model->get_active_for_campaign( $winner->id );
			if ( $active_test && ! empty( $active_test->id ) ) {
				$visitor_id = $visitor_state->get_visitor_id();
				$variation  = $ab_model->select_variation( (int) $active_test->id, $visitor_id );
				if ( $variation && ! empty( $variation->campaign_data ) ) {
					$campaign_row = json_decode( $variation->campaign_data, true );
					if ( is_array( $campaign_row ) ) {
						$campaign_to_show = CRO_Campaign_Model::from_db_row( $campaign_row );
						$decision->ab_test_id   = (int) $active_test->id;
						$decision->variation_id = (int) $variation->id;
						$decision->is_control   = ! empty( $variation->is_control );
						// Impression recorded in REST decide handler (once per pageview via transient guard)
						$decision->log( 'INFO', sprintf( __( 'A/B test %d: showing variation %d (control: %s).', 'meyvora-convert' ), $decision->ab_test_id, $decision->variation_id, $decision->is_control ? 'yes' : 'no' ), array( 'step' => 'ab_test', 'ab_test_id' => $decision->ab_test_id, 'variation_id' => $decision->variation_id ) );
					}
				}
			}
		}

		// Step 8: Return final decision — only one campaign per pageview
		$this->mark_shown( $winner->id );
		$decision->show         = true;
		$decision->campaign     = $campaign_to_show;
		$decision->campaign_id = $winner->id;
		$decision->reason      = __( 'Campaign selected.', 'meyvora-convert' );
		$decision->reason_code = 'show';
		$decision->set_intent( $winner_entry['score'], $winner_entry['threshold'] );
		$decision->log( 'SUCCESS', sprintf( __( 'Show campaign %d.', 'meyvora-convert' ), $winner->id ), array( 'step' => 8, 'campaign_id' => $winner->id ) );
		return $decision;
	}

	/**
	 * Consent check (e.g. plugin/cookie consent from settings).
	 *
	 * @return bool True if consent allows popups.
	 */
	public function check_consent() {
		if ( ! function_exists( 'cro_settings' ) ) {
			return true;
		}
		$enabled = cro_settings()->get( 'general', 'plugin_enabled', true );
		if ( ! $enabled ) {
			return false;
		}
		return (bool) apply_filters( 'cro_consent_allows_popup', true );
	}

	/**
	 * Visitor suppression: admin, shown this pageview, max per session, post-conversion, checkout.
	 *
	 * @param CRO_Context      $context       Context.
	 * @param CRO_Visitor_State $visitor_state Visitor state.
	 * @return string|null Null if OK to show; otherwise suppression reason string.
	 */
	public function check_visitor_suppression( CRO_Context $context, CRO_Visitor_State $visitor_state ) {
		// Admin exclusion
		if ( function_exists( 'cro_settings' ) && cro_settings()->get( 'general', 'exclude_admins', true ) ) {
			if ( $context->get( 'user.is_admin', false ) ) {
				return __( 'Admins are excluded.', 'meyvora-convert' );
			}
		}

		// Already shown this pageview (only one per request)
		if ( $this->shown_this_pageview ) {
			return __( 'Already shown this pageview.', 'meyvora-convert' );
		}

		// Max popups per session
		if ( function_exists( 'cro_settings' ) ) {
			$max = (int) cro_settings()->get( 'general', 'max_popups_per_session', 3 );
			if ( $max > 0 && $visitor_state->get_session_shown_count() >= $max ) {
				return __( 'Max popups per session reached.', 'meyvora-convert' );
			}
		}

		// Post-conversion suppression
		$last_conversion = $visitor_state->get_last_conversion_time();
		if ( $last_conversion !== null ) {
			$window = (int) apply_filters( 'cro_conversion_suppression_window', DAY_IN_SECONDS );
			if ( ( time() - $last_conversion ) < $window ) {
				return __( 'Within post-conversion suppression window.', 'meyvora-convert' );
			}
		}

		// Checkout page: never show
		if ( $context->get( 'page_type', '' ) === 'checkout' ) {
			return __( 'Checkout page; popups disabled.', 'meyvora-convert' );
		}

		return null;
	}

	/**
	 * Get active campaigns from database as CRO_Campaign_Model instances.
	 *
	 * @return CRO_Campaign_Model[]
	 */
	public function get_active_campaigns() {
		if ( ! class_exists( 'CRO_Campaign' ) ) {
			return array();
		}
		$rows = CRO_Campaign::get_all( array( 'status' => 'active' ) );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$models = array();
		foreach ( $rows as $row ) {
			if ( ! class_exists( 'CRO_Campaign_Model' ) ) {
				continue;
			}
			$model = CRO_Campaign_Model::from_db_row( $row );
			if ( $model->is_active() && $model->is_within_schedule() ) {
				$models[] = $model;
			}
		}
		return $models;
	}

	/**
	 * Filter eligible campaigns by current trigger type and trigger condition.
	 *
	 * @param array         $eligible     Campaigns that passed targeting.
	 * @param string        $trigger_type exit_intent, time, scroll, inactivity, page_load, click.
	 * @param CRO_Context   $context      Context (behavior.time_on_page, behavior.scroll_depth, etc.).
	 * @param CRO_Decision  $decision     Decision object for logging.
	 * @return array Filtered list of campaigns that match this trigger.
	 */
	private function filter_eligible_by_trigger( array $eligible, $trigger_type, CRO_Context $context, CRO_Decision $decision ) {
		$out = array();
		$trigger_type = (string) $trigger_type;
		// page_load: treat as time with 0 seconds (show immediately / time campaigns with delay <= 0 or 1)
		$effective_trigger = ( $trigger_type === 'page_load' ) ? 'time' : $trigger_type;

		foreach ( $eligible as $campaign ) {
			$rules = isset( $campaign->trigger_rules ) && is_array( $campaign->trigger_rules ) ? $campaign->trigger_rules : array();
			$camp_type = (string) ( $rules['type'] ?? 'exit_intent' );

			if ( $camp_type !== $effective_trigger && ( $trigger_type !== 'page_load' || $camp_type !== 'time' ) ) {
				$decision->log( 'RULE', sprintf( __( 'Campaign %d skipped: trigger type %s does not match %s.', 'meyvora-convert' ), $campaign->id, $camp_type, $trigger_type ), array( 'campaign_id' => $campaign->id, 'trigger_type' => $trigger_type ) );
				continue;
			}

			// Time: require delay_seconds <= current time_on_page
			if ( $effective_trigger === 'time' || $camp_type === 'time' ) {
				$delay = (int) ( $rules['time_delay_seconds'] ?? $rules['delay_seconds'] ?? $rules['time_on_page_seconds'] ?? 0 );
				$time_on_page = (int) $context->get( 'behavior.time_on_page', 0 );
				if ( $time_on_page < $delay ) {
					$decision->log( 'RULE', sprintf( __( 'Campaign %d skipped: time on page %d < delay %d.', 'meyvora-convert' ), $campaign->id, $time_on_page, $delay ), array( 'campaign_id' => $campaign->id ) );
					continue;
				}
			}

			// Scroll: require scroll_depth >= campaign scroll_depth_percent
			if ( $effective_trigger === 'scroll' || $camp_type === 'scroll' ) {
				$required = (int) ( $rules['scroll_depth_percent'] ?? $rules['scroll_depth'] ?? 50 );
				$scroll_depth = (int) $context->get( 'behavior.scroll_depth', 0 );
				if ( $scroll_depth < $required ) {
					$decision->log( 'RULE', sprintf( __( 'Campaign %d skipped: scroll depth %d < required %d.', 'meyvora-convert' ), $campaign->id, $scroll_depth, $required ), array( 'campaign_id' => $campaign->id ) );
					continue;
				}
			}

			// Inactivity: require idle_seconds >= campaign idle_seconds
			if ( $effective_trigger === 'inactivity' || $camp_type === 'inactivity' ) {
				$required = (int) ( $rules['idle_seconds'] ?? $rules['idle_time'] ?? 30 );
				$idle = (int) $context->get( 'behavior.idle_seconds', 0 );
				if ( $idle < $required ) {
					$decision->log( 'RULE', sprintf( __( 'Campaign %d skipped: idle %d < required %d.', 'meyvora-convert' ), $campaign->id, $idle, $required ), array( 'campaign_id' => $campaign->id ) );
					continue;
				}
			}

			$out[] = $campaign;
		}
		return $out;
	}

	/**
	 * Check offer/coupon eligibility (e.g. one-per-visitor).
	 *
	 * @param CRO_Campaign_Model $campaign       Campaign model.
	 * @param CRO_Visitor_State   $visitor_state Visitor state.
	 * @return bool True if offer can be shown.
	 */
	public function check_offer_eligibility( $campaign, CRO_Visitor_State $visitor_state ) {
		$offer = $campaign->offer_rules;
		if ( ! is_array( $offer ) ) {
			return true;
		}
		if ( ! empty( $offer['one_per_visitor'] ) ) {
			$code = isset( $offer['coupon_code'] ) ? $offer['coupon_code'] : '';
			if ( $code !== '' && $visitor_state->has_used_coupon( $code ) ) {
				return false;
			}
		}
		return (bool) apply_filters( 'cro_offer_eligibility', true, $campaign, $visitor_state );
	}

	/**
	 * Check frequency: max X per Y period, cooldown after conversion/click, then once_ever, once_per_session, etc.
	 *
	 * @param CRO_Campaign_Model|object $campaign       Campaign (with frequency_rules or display_rules).
	 * @param CRO_Visitor_State          $visitor_state Visitor state.
	 * @return bool True if frequency allows showing.
	 */
	public function check_frequency( $campaign, CRO_Visitor_State $visitor_state ) {
		$rules = array();
		if ( is_object( $campaign ) && isset( $campaign->frequency_rules ) && is_array( $campaign->frequency_rules ) ) {
			$rules = $campaign->frequency_rules;
		} elseif ( is_object( $campaign ) && isset( $campaign->display_rules ) && is_array( $campaign->display_rules ) ) {
			$rules = $campaign->display_rules;
		}
		$campaign_id = isset( $campaign->id ) ? (int) $campaign->id : 0;
		$now = time();

		// 1. Frequency cap: max X times per visitor per Y hours/days
		$max_per_visitor = isset( $rules['max_impressions_per_visitor'] ) ? (int) $rules['max_impressions_per_visitor'] : 0;
		if ( $max_per_visitor > 0 ) {
			$period_value = isset( $rules['frequency_period_value'] ) ? (int) $rules['frequency_period_value'] : 24;
			$period_unit  = isset( $rules['frequency_period_unit'] ) ? $rules['frequency_period_unit'] : 'hours';
			$period_seconds = $period_value * ( $period_unit === 'days' ? DAY_IN_SECONDS : HOUR_IN_SECONDS );
			$count = $visitor_state->get_impression_count_in_window( $campaign_id, $period_seconds );
			if ( $count >= $max_per_visitor ) {
				return false;
			}
		}

		// 2. Cooldown after conversion
		$cooldown_conv = isset( $rules['cooldown_after_conversion_seconds'] ) ? (int) $rules['cooldown_after_conversion_seconds'] : 0;
		if ( $cooldown_conv > 0 ) {
			$conv_time = $visitor_state->get_campaign_conversion_time( $campaign_id );
			if ( $conv_time !== null && ( $now - $conv_time ) < $cooldown_conv ) {
				return false;
			}
		}

		// 3. Cooldown after click (CTA)
		$cooldown_click = isset( $rules['cooldown_after_click_seconds'] ) ? (int) $rules['cooldown_after_click_seconds'] : 0;
		if ( $cooldown_click > 0 ) {
			$click_time = $visitor_state->get_campaign_last_click( $campaign_id );
			if ( $click_time !== null && ( $now - $click_time ) < $cooldown_click ) {
				return false;
			}
		}

		// 4. Legacy frequency: once_ever, once_per_session, once_per_day, once_per_x_days
		$frequency = isset( $rules['frequency'] ) ? $rules['frequency'] : 'once_per_session';
		$last_shown = $visitor_state->get_campaign_last_shown( $campaign_id );
		if ( $last_shown === null ) {
			return true;
		}

		switch ( $frequency ) {
			case 'once_ever':
				return false;
			case 'once_per_session':
				return ! $visitor_state->was_shown_this_session( $campaign_id );
			case 'once_per_day':
				return ( $now - $last_shown ) >= DAY_IN_SECONDS;
			case 'once_per_x_days':
				$days = (int) ( isset( $rules['frequency_days'] ) ? $rules['frequency_days'] : 7 );
				return ( $now - $last_shown ) >= ( $days * DAY_IN_SECONDS );
			default:
				return true;
		}
	}

	/**
	 * Get fallback campaign from settings (if active).
	 *
	 * @return CRO_Campaign_Model|null
	 */
	public function get_fallback_campaign() {
		if ( ! function_exists( 'cro_settings' ) || ! class_exists( 'CRO_Campaign' ) || ! class_exists( 'CRO_Campaign_Model' ) ) {
			return null;
		}
		$fallback_id = (int) cro_settings()->get( 'general', 'fallback_campaign_id', 0 );
		if ( $fallback_id <= 0 ) {
			return null;
		}
		$row = CRO_Campaign::get( $fallback_id );
		if ( ! $row || ( is_array( $row ) && ( $row['status'] ?? '' ) !== 'active' ) ) {
			return null;
		}
		$model = CRO_Campaign_Model::from_db_row( $row );
		return $model->is_active() ? $model : null;
	}

	/**
	 * Mark that a campaign was shown this pageview (only one per request).
	 *
	 * @param int $campaign_id Campaign ID.
	 */
	public function mark_shown( $campaign_id ) {
		$this->shown_this_pageview = true;
		do_action( 'cro_campaign_shown_this_pageview', $campaign_id );
	}

	/**
	 * Build targeting rules in Rule Engine format (must/should/must_not) from campaign model.
	 *
	 * @param CRO_Campaign_Model $campaign Campaign model.
	 * @return array{ must: array, should: array, must_not: array }
	 */
	private function targeting_rules_for_engine( $campaign ) {
		$must = array();
		$must_not = array();
		$tr = isset( $campaign->targeting_rules ) && is_array( $campaign->targeting_rules ) ? $campaign->targeting_rules : array();

		// If already in rule-engine shape, use it.
		if ( isset( $tr['must'] ) || isset( $tr['should'] ) || isset( $tr['must_not'] ) ) {
			return array(
				'must'     => isset( $tr['must'] ) && is_array( $tr['must'] ) ? $tr['must'] : array(),
				'should'   => isset( $tr['should'] ) && is_array( $tr['should'] ) ? $tr['should'] : array(),
				'must_not' => isset( $tr['must_not'] ) && is_array( $tr['must_not'] ) ? $tr['must_not'] : array(),
			);
		}

		// Build from pages include/exclude (map category -> product_category for WooCommerce)
		$pages = isset( $tr['pages'] ) && is_array( $tr['pages'] ) ? $tr['pages'] : array();
		$include = isset( $pages['include'] ) && is_array( $pages['include'] ) ? $pages['include'] : array( 'cart', 'product' );
		$exclude = isset( $pages['exclude'] ) && is_array( $pages['exclude'] ) ? $pages['exclude'] : array();
		$include = array_map( array( __CLASS__, 'map_page_type' ), $include );
		$exclude = array_map( array( __CLASS__, 'map_page_type' ), $exclude );
		$page_mode = isset( $tr['page_mode'] ) ? $tr['page_mode'] : ( ! empty( $include ) ? 'include' : ( ! empty( $exclude ) ? 'exclude' : 'all' ) );
		if ( $page_mode === 'include' && ! empty( $include ) ) {
			$must[] = array( 'type' => 'page_type_in', 'value' => array_values( array_unique( $include ) ) );
		}
		if ( $page_mode === 'exclude' && ! empty( $exclude ) ) {
			$must[] = array( 'type' => 'page_type_not_in', 'value' => array_values( array_unique( $exclude ) ) );
		}
		if ( $page_mode !== 'include' && $page_mode !== 'exclude' && ! empty( $exclude ) ) {
			$must_not[] = array( 'type' => 'page_type_in', 'value' => array_values( array_unique( $exclude ) ) );
		}

		// Device
		$device = isset( $tr['device'] ) && is_array( $tr['device'] ) ? $tr['device'] : array();
		$allowed = array();
		if ( ! empty( $device['desktop'] ) ) {
			$allowed[] = 'desktop';
		}
		if ( ! empty( $device['mobile'] ) ) {
			$allowed[] = 'mobile';
		}
		if ( ! empty( $device['tablet'] ) ) {
			$allowed[] = 'tablet';
		}
		if ( ! empty( $allowed ) ) {
			$must[] = array( 'type' => 'device_type_in', 'value' => $allowed );
		}

		// Behavior: min_time_on_page, min_scroll_depth, require_interaction
		$behavior = isset( $tr['behavior'] ) && is_array( $tr['behavior'] ) ? $tr['behavior'] : array();
		$min_time = isset( $behavior['min_time_on_page'] ) ? (int) $behavior['min_time_on_page'] : 0;
		if ( $min_time > 0 ) {
			$must[] = array( 'type' => 'time_on_page_gte', 'value' => $min_time );
		}
		$min_scroll = isset( $behavior['min_scroll_depth'] ) ? (int) $behavior['min_scroll_depth'] : 0;
		if ( $min_scroll > 0 ) {
			$must[] = array( 'type' => 'scroll_depth_gte', 'value' => $min_scroll );
		}
		if ( ! empty( $behavior['require_interaction'] ) ) {
			$must[] = array( 'type' => 'has_interacted', 'value' => true );
		}

		// Cart value range
		$cart_min = isset( $behavior['cart_min_value'] ) ? (float) $behavior['cart_min_value'] : 0;
		$cart_max = isset( $behavior['cart_max_value'] ) ? (float) $behavior['cart_max_value'] : 0;
		if ( $cart_min > 0 && $cart_max > 0 ) {
			$must[] = array( 'type' => 'cart_total_between', 'value' => array( 'min' => $cart_min, 'max' => $cart_max ) );
		} elseif ( $cart_min > 0 ) {
			$must[] = array( 'type' => 'cart_total_gte', 'value' => $cart_min );
		} elseif ( $cart_max > 0 ) {
			$must[] = array( 'type' => 'cart_total_lte', 'value' => $cart_max );
		}

		// Cart product/category include/exclude
		$cart_include_product = isset( $behavior['cart_contains_product'] ) && is_array( $behavior['cart_contains_product'] ) ? array_map( 'intval', $behavior['cart_contains_product'] ) : ( isset( $behavior['cart_contains'] ) && is_array( $behavior['cart_contains'] ) ? array_map( 'intval', $behavior['cart_contains'] ) : array() );
		$cart_exclude_product = isset( $behavior['cart_exclude_product'] ) && is_array( $behavior['cart_exclude_product'] ) ? array_map( 'intval', $behavior['cart_exclude_product'] ) : array();
		$cart_include_category = isset( $behavior['cart_contains_category'] ) && is_array( $behavior['cart_contains_category'] ) ? array_map( 'intval', $behavior['cart_contains_category'] ) : array();
		$cart_exclude_category = isset( $behavior['cart_exclude_category'] ) && is_array( $behavior['cart_exclude_category'] ) ? array_map( 'intval', $behavior['cart_exclude_category'] ) : array();
		if ( ! empty( $cart_include_product ) ) {
			$must[] = array( 'type' => 'cart_has_product', 'value' => $cart_include_product );
		}
		if ( ! empty( $cart_exclude_product ) ) {
			$must_not[] = array( 'type' => 'cart_has_product_not', 'value' => $cart_exclude_product );
		}
		if ( ! empty( $cart_include_category ) ) {
			$must[] = array( 'type' => 'cart_has_category', 'value' => $cart_include_category );
		}
		if ( ! empty( $cart_exclude_category ) ) {
			$must_not[] = array( 'type' => 'cart_has_category_not', 'value' => $cart_exclude_category );
		}

		// Visitor type (new vs returning)
		$visitor = isset( $tr['visitor'] ) && is_array( $tr['visitor'] ) ? $tr['visitor'] : array();
		$visitor_type = isset( $visitor['type'] ) ? $visitor['type'] : 'all';
		if ( $visitor_type === 'new' ) {
			$must[] = array( 'type' => 'visitor_new', 'value' => true );
		} elseif ( $visitor_type === 'returning' ) {
			$must[] = array( 'type' => 'visitor_returning', 'value' => true );
		}

		// UTM conditions
		if ( ! empty( $tr['utm_source'] ) && is_string( $tr['utm_source'] ) ) {
			$must[] = array( 'type' => 'utm_param_equals', 'value' => array( 'param' => 'utm_source', 'value' => $tr['utm_source'] ) );
		}
		if ( ! empty( $tr['utm_medium'] ) && is_string( $tr['utm_medium'] ) ) {
			$must[] = array( 'type' => 'utm_param_equals', 'value' => array( 'param' => 'utm_medium', 'value' => $tr['utm_medium'] ) );
		}
		if ( ! empty( $tr['utm_campaign'] ) && is_string( $tr['utm_campaign'] ) ) {
			$must[] = array( 'type' => 'utm_param_equals', 'value' => array( 'param' => 'utm_campaign', 'value' => $tr['utm_campaign'] ) );
		}

		return array( 'must' => $must, 'should' => array(), 'must_not' => $must_not );
	}

	/**
	 * Map UI page type to context page_type (e.g. category -> product_category).
	 *
	 * @param string $page_type Page type from UI.
	 * @return string
	 */
	private static function map_page_type( $page_type ) {
		$map = array(
			'category' => 'product_category',
			'blog'     => 'post',
		);
		return isset( $map[ $page_type ] ) ? $map[ $page_type ] : $page_type;
	}
}

/**
 * Global accessor for the decision engine.
 *
 * Use: cro_decide()->decide( $context, $visitor_state, $intent_signals )
 *
 * @return CRO_Decision_Engine
 */
function cro_decide() {
	return CRO_Decision_Engine::get_instance();
}
