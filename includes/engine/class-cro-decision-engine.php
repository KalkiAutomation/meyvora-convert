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
 * Singleton. decide() runs: consent → UX form signals → visitor suppression → active campaigns →
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
	 * @param array            $options       Optional. single_campaign_id (int): evaluate only this campaign. ignore_session_popup_cap (bool): skip max popups per session. skip_pageview_shown_check (bool): allow second decide in same request (rare).
	 * @return CRO_Decision
	 */
	public function decide( CRO_Context $context, CRO_Visitor_State $visitor_state, array $intent_signals = array(), $trigger_type = '', array $options = array() ) {
		$decision = new CRO_Decision( false, null, '', 'pending' );

		// Visitor metrics for targeting rules (session count, page views). JS may send visitor.pages_viewed; take max with server count.
		$context->set( 'request.session_count', $visitor_state->get_session_count() );
		$pv_server = $visitor_state->get_pages_viewed();
		$pv_js     = (int) $context->get( 'visitor.pages_viewed', 0 );
		$context->set( 'visitor.pages_viewed', max( $pv_js, $pv_server ) );

		// Step 1: Consent check
		if ( ! $this->check_consent() ) {
			$decision->log( 'SKIP', __( 'Consent not given; popups disabled.', 'meyvora-convert' ), array( 'step' => 1 ) );
			$decision->reason = __( 'Consent not given.', 'meyvora-convert' );
			$decision->reason_code = 'consent';
			return $decision;
		}
		$decision->log( 'INFO', __( 'Consent OK.', 'meyvora-convert' ), array( 'step' => 1 ) );

		// Step 1b: UX — do not show while user is typing or a form field is focused (signals from cro-signals.js).
		if ( class_exists( 'CRO_UX_Rules' ) && ! empty( $intent_signals ) ) {
			$ux = new CRO_UX_Rules();
			if ( $ux->is_form_interaction_blocked( $intent_signals ) ) {
				$decision->log( 'SKIP', __( 'UX rule blocked: form interaction.', 'meyvora-convert' ), array( 'step' => '1b' ) );
				$decision->reason      = __( 'UX protection active.', 'meyvora-convert' );
				$decision->reason_code = 'ux_block';
				return $decision;
			}
		}

		// Step 2: Visitor suppression (admin, shown this pageview, max per session, post-conversion, checkout)
		$suppression = $this->check_visitor_suppression( $context, $visitor_state, $options );
		if ( $suppression !== null ) {
			$decision->log( 'SKIP', $suppression, array( 'step' => 2 ) );
			$decision->reason = $suppression;
			$decision->reason_code = 'suppression';
			return $decision;
		}
		$decision->log( 'INFO', __( 'Visitor suppression OK.', 'meyvora-convert' ), array( 'step' => 2 ) );

		// Step 3: Get active campaigns from database (or a single campaign for dismiss→fallback flow)
		$single_id = isset( $options['single_campaign_id'] ) ? (int) $options['single_campaign_id'] : 0;
		if ( $single_id > 0 ) {
			$campaigns = $this->get_single_active_campaign( $single_id );
		} else {
			$campaigns = $this->get_active_campaigns( $context );
		}
		$campaigns = apply_filters( 'cro_decision_active_campaigns', $campaigns, $context, $visitor_state );
		if ( empty( $campaigns ) ) {
			$decision->log( 'SKIP', __( 'No active campaigns.', 'meyvora-convert' ), array( 'step' => 3 ) );
			$decision->reason = __( 'No active campaigns.', 'meyvora-convert' );
			$decision->reason_code = 'no_campaigns';
			return $decision;
		}
		$decision->log( 'INFO', sprintf( /* translators: %d is the number of active campaigns found. */ __( 'Found %d active campaign(s).', 'meyvora-convert' ), count( $campaigns ) ), array( 'step' => 3, 'count' => count( $campaigns ) ) );

		// Step 4: Evaluate each campaign against targeting rules
		$rule_engine = new CRO_Rule_Engine();
		$eligible = array();
		foreach ( $campaigns as $campaign ) {
			$rules = $this->targeting_rules_for_engine( $campaign );
			$result = $rule_engine->evaluate( $rules, $context );
			$detail_payload = array(
				'details'            => $result['details'],
				'failed_conditions'  => isset( $result['failed_conditions'] ) ? $result['failed_conditions'] : array(),
			);
			$decision->record_campaign_result( $campaign->id, $result['passed'], $detail_payload );
			if ( ! $result['passed'] ) {
				$decision->log(
					'RULE',
					sprintf( /* translators: %d is the campaign ID. */ __( 'Campaign %d failed targeting.', 'meyvora-convert' ), $campaign->id ),
					array(
						'campaign_id'         => $campaign->id,
						'failed_conditions'   => isset( $result['failed_conditions'] ) ? $result['failed_conditions'] : array(),
						'details'             => $result['details'],
					)
				);
				continue;
			}
			$freq               = isset( $campaign->frequency_rules ) && is_array( $campaign->frequency_rules ) ? $campaign->frequency_rules : array();
			$dismissal_cooldown = isset( $freq['dismissal_cooldown_seconds'] ) ? (int) $freq['dismissal_cooldown_seconds'] : 0;
			if ( $visitor_state->was_dismissed( (int) $campaign->id, $dismissal_cooldown ) ) {
				$decision->log(
					'RULE',
					sprintf(
						/* translators: 1: campaign ID, 2: cooldown seconds (0 = permanent). */
						__( 'Campaign %1$d skipped: dismissed (cooldown %2$ds).', 'meyvora-convert' ),
						$campaign->id,
						$dismissal_cooldown
					),
					array( 'campaign_id' => $campaign->id, 'cooldown' => $dismissal_cooldown )
				);
				continue;
			}
			$eligible[] = $campaign;
			$decision->log( 'RULE', sprintf( /* translators: %d is the campaign ID. */ __( 'Campaign %d passed targeting.', 'meyvora-convert' ), $campaign->id ), array( 'campaign_id' => $campaign->id ) );
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

		$decision->log( 'INFO', sprintf( /* translators: %d is the number of campaigns that passed targeting. */ __( '%d campaign(s) passed targeting.', 'meyvora-convert' ), count( $eligible ) ), array( 'step' => 4 ) );

		// Step 5: Intent score and threshold (optional / legacy) or treat trigger condition as met.
		// exit_intent: must skip intent scoring — the controller fires exit_intent on viewport-top mouseout,
		// while the scorer expects exit_mouse velocity & collector state that often do not match, so scores
		// stayed below threshold and popups never showed.
		$trigger_only_types = array( 'exit_intent', 'mobile_exit', 'time', 'scroll', 'inactivity', 'page_load', 'click' );
		$passed_intent = array();
		if ( in_array( $trigger_type, $trigger_only_types, true ) ) {
			// Trigger condition was already validated in filter_eligible_by_trigger; skip intent scoring
			foreach ( $eligible as $campaign ) {
				$passed_intent[] = array( 'campaign' => $campaign, 'score' => 100, 'threshold' => 0 );
			}
			$decision->log( 'INFO', sprintf( /* translators: %d is the number of campaigns that passed the trigger check. */ __( '%d campaign(s) passed (trigger condition met).', 'meyvora-convert' ), count( $passed_intent ) ), array( 'step' => 5, 'trigger_type' => $trigger_type ) );
		} else {
			$intent_scorer = new CRO_Intent_Scorer();
			foreach ( $eligible as $campaign ) {
				$threshold = is_numeric( $campaign->get_intent_threshold() ) ? (int) $campaign->get_intent_threshold() : 50;
				$score_result = $intent_scorer->calculate_score( $intent_signals, array() );
				$score = isset( $score_result['score'] ) ? (float) $score_result['score'] : 0;
				$meets = $intent_scorer->meets_threshold( $score, $threshold );
				if ( $meets ) {
					$passed_intent[] = array( 'campaign' => $campaign, 'score' => $score, 'threshold' => $threshold );
					$decision->log( 'UX', sprintf( /* translators: %1$d is the campaign ID, %2$s is the intent score, %3$s is the threshold. */ __( 'Campaign %1$d passed intent (score %2$s >= %3$s).', 'meyvora-convert' ), $campaign->id, $score, $threshold ), array( 'campaign_id' => $campaign->id, 'score' => $score, 'threshold' => $threshold ) );
				} else {
					$decision->log( 'UX', sprintf( /* translators: %1$d is the campaign ID, %2$s is the intent score, %3$s is the threshold. */ __( 'Campaign %1$d failed intent (score %2$s < %3$s).', 'meyvora-convert' ), $campaign->id, $score, $threshold ), array( 'campaign_id' => $campaign->id, 'score' => $score, 'threshold' => $threshold ) );
				}
			}
		}

		if ( empty( $passed_intent ) ) {
			$decision->log( 'SKIP', __( 'No campaign met intent threshold.', 'meyvora-convert' ), array( 'step' => 5 ) );
			$decision->reason = __( 'Intent threshold not met.', 'meyvora-convert' );
			$decision->reason_code = 'intent_threshold';
			return $decision;
		}

		$decision->log( 'INFO', sprintf( /* translators: %d is the number of campaigns that passed the intent check. */ __( '%d campaign(s) passed intent.', 'meyvora-convert' ), count( $passed_intent ) ), array( 'step' => 5 ) );

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
		$decision->log( 'INFO', sprintf( /* translators: %d is the winning campaign ID. */ __( 'Priority winner: campaign %d.', 'meyvora-convert' ), $winner->id ), array( 'step' => 6, 'campaign_id' => $winner->id, 'priority' => $winner->priority ) );

		// Step 7: Frequency check (once_ever, once_per_session, etc.)
		if ( ! $this->check_frequency( $winner, $visitor_state ) ) {
			$decision->log( 'SKIP', sprintf( /* translators: %d is the campaign ID. */ __( 'Campaign %d suppressed by frequency.', 'meyvora-convert' ), $winner->id ), array( 'step' => 7 ) );
			$decision->reason = __( 'Frequency limit reached.', 'meyvora-convert' );
			$decision->reason_code = 'frequency';
			return $decision;
		}
		$decision->log( 'INFO', __( 'Frequency OK for winner.', 'meyvora-convert' ), array( 'step' => 7 ) );

		// Offer eligibility (e.g. coupon one-per-visitor) — gate before show
		if ( ! $this->check_offer_eligibility( $winner, $visitor_state ) ) {
			$decision->log( 'SKIP', sprintf( /* translators: %d is the campaign ID. */ __( 'Campaign %d failed offer eligibility.', 'meyvora-convert' ), $winner->id ), array( 'step' => 'offer' ) );
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
						$decision->log( 'INFO', sprintf( /* translators: %1$d is the A/B test ID, %2$d is the variation ID, %3$s is whether it is the control variant (yes/no). */ __( 'A/B test %1$d: showing variation %2$d (control: %3$s).', 'meyvora-convert' ), $decision->ab_test_id, $decision->variation_id, $decision->is_control ? 'yes' : 'no' ), array( 'step' => 'ab_test', 'ab_test_id' => $decision->ab_test_id, 'variation_id' => $decision->variation_id ) );
					}
				}
			}
		}

		// Step 8: Return final decision — only one campaign per pageview
		$this->mark_shown( $winner->id );
		$decision->show         = true;
		$decision->campaign     = $campaign_to_show;
		$decision->campaign_id = $winner->id;
		$winner_fallback_id = isset( $winner->fallback_id ) ? (int) $winner->fallback_id : 0;
		if ( $winner_fallback_id > 0 && method_exists( $winner, 'get_fallback_delay' ) ) {
			$decision->fallback_campaign_id   = $winner_fallback_id;
			$decision->fallback_delay_seconds = $winner->get_fallback_delay();
		}
		$decision->reason      = __( 'Campaign selected.', 'meyvora-convert' );
		$decision->reason_code = 'show';
		$decision->set_intent( $winner_entry['score'], $winner_entry['threshold'] );
		$decision->log(
			'SUCCESS',
			sprintf(
				/* translators: %d is the campaign ID. */
				__( 'Show campaign %d.', 'meyvora-convert' ),
				$winner->id
			),
			array( 'step' => 8, 'campaign_id' => $winner->id )
		);
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
	 * @param array             $options       Optional. ignore_session_popup_cap, skip_pageview_shown_check.
	 * @return string|null Null if OK to show; otherwise suppression reason string.
	 */
	public function check_visitor_suppression( CRO_Context $context, CRO_Visitor_State $visitor_state, array $options = array() ) {
		// Admin exclusion (optional; skip when debug mode is on so shop owners can test while logged in).
		if ( function_exists( 'cro_settings' ) && cro_settings()->get( 'general', 'exclude_admins', false ) ) {
			if ( $context->get( 'user.is_admin', false ) ) {
				$debug_on = cro_settings()->get( 'general', 'debug_mode', false );
				if ( ! $debug_on && apply_filters( 'cro_exclude_admins_from_campaigns', true, $context ) ) {
					return __( 'Admins are excluded. Enable debug_mode in settings to test as admin.', 'meyvora-convert' );
				}
			}
		}

		// Already shown this pageview (only one per request)
		if ( empty( $options['skip_pageview_shown_check'] ) && $this->shown_this_pageview ) {
			return __( 'Already shown this pageview.', 'meyvora-convert' );
		}

		// Max popups per session
		if ( empty( $options['ignore_session_popup_cap'] ) && function_exists( 'cro_settings' ) ) {
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
	 * When {@see CRO_Query_Optimizer} is available and context is passed, uses cache + PHP pre-filter
	 * (schedule, page type, device) before building models.
	 *
	 * @param CRO_Context|null $context Optional. Request context for query optimizer.
	 * @return CRO_Campaign_Model[]
	 */
	public function get_active_campaigns( $context = null ) {
		if ( ! class_exists( 'CRO_Campaign' ) ) {
			return array();
		}
		$rows = null;
		if ( class_exists( 'CRO_Query_Optimizer' ) && is_object( $context ) && method_exists( $context, 'get' ) ) {
			$filtered = CRO_Query_Optimizer::get_campaigns_for_decision( $context );
			if ( $filtered !== null ) {
				$rows = $filtered;
			}
		}
		if ( $rows === null ) {
			$rows = CRO_Campaign::get_all( array( 'status' => 'active' ) );
		}
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
	 * Load one campaign by ID if active and within schedule (for fallback-only decide).
	 *
	 * @param int $id Campaign ID.
	 * @return CRO_Campaign_Model[]
	 */
	private function get_single_active_campaign( $id ) {
		$id = (int) $id;
		if ( $id <= 0 || ! class_exists( 'CRO_Campaign' ) || ! class_exists( 'CRO_Campaign_Model' ) ) {
			return array();
		}
		$row = CRO_Campaign::get( $id );
		if ( ! is_array( $row ) || empty( $row['id'] ) ) {
			return array();
		}
		$model = CRO_Campaign_Model::from_db_row( $row );
		if ( ! $model->is_active() || ! $model->is_within_schedule() ) {
			return array();
		}
		return array( $model );
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

			$trigger_matches = ( $camp_type === $effective_trigger ) || ( 'page_load' === $trigger_type && 'time' === $camp_type );
			$device_type     = (string) $context->get( 'device_type', '' );
			$is_handheld     = in_array( $device_type, array( 'mobile', 'tablet' ), true );

			// Mobile exit-intent signal is delivered as trigger exit_intent from the controller; allow mobile_exit campaigns on handheld.
			if ( ! $trigger_matches && 'exit_intent' === $trigger_type && 'mobile_exit' === $camp_type && $is_handheld ) {
				$trigger_matches = true;
			}

			if ( ! $trigger_matches ) {
				$decision->log( 'RULE', sprintf( /* translators: %1$d is the campaign ID, %2$s is the campaign trigger type, %3$s is the current page trigger type. */ __( 'Campaign %1$d skipped: trigger type %2$s does not match %3$s.', 'meyvora-convert' ), $campaign->id, $camp_type, $trigger_type ), array( 'campaign_id' => $campaign->id, 'trigger_type' => $trigger_type ) );
				continue;
			}

			// Time: require delay_seconds <= current time_on_page
			if ( $effective_trigger === 'time' || $camp_type === 'time' ) {
				$delay = (int) ( $rules['time_delay_seconds'] ?? $rules['delay_seconds'] ?? $rules['time_on_page_seconds'] ?? 0 );
				$time_on_page = (int) $context->get( 'behavior.time_on_page', 0 );
				if ( $time_on_page < $delay ) {
					$decision->log( 'RULE', sprintf( /* translators: %1$d is the campaign ID, %2$d is the current time on page in seconds, %3$d is the required delay in seconds. */ __( 'Campaign %1$d skipped: time on page %2$d < delay %3$d.', 'meyvora-convert' ), $campaign->id, $time_on_page, $delay ), array( 'campaign_id' => $campaign->id ) );
					continue;
				}
			}

			// Scroll: require scroll_depth >= campaign scroll_depth_percent
			if ( $effective_trigger === 'scroll' || $camp_type === 'scroll' ) {
				$required = (int) ( $rules['scroll_depth_percent'] ?? $rules['scroll_depth'] ?? 50 );
				$scroll_depth = (int) $context->get( 'behavior.scroll_depth', 0 );
				if ( $scroll_depth < $required ) {
					$decision->log( 'RULE', sprintf( /* translators: %1$d is the campaign ID, %2$d is the current scroll depth percentage, %3$d is the required scroll depth percentage. */ __( 'Campaign %1$d skipped: scroll depth %2$d < required %3$d.', 'meyvora-convert' ), $campaign->id, $scroll_depth, $required ), array( 'campaign_id' => $campaign->id ) );
					continue;
				}
			}

			// Inactivity: require idle_seconds >= campaign idle_seconds
			if ( $effective_trigger === 'inactivity' || $camp_type === 'inactivity' ) {
				$required = (int) ( $rules['idle_seconds'] ?? $rules['idle_time'] ?? 30 );
				$idle = (int) $context->get( 'behavior.idle_seconds', 0 );
				if ( $idle < $required ) {
					$decision->log( 'RULE', sprintf( /* translators: %1$d is the campaign ID, %2$d is the current idle time in seconds, %3$d is the required idle time in seconds. */ __( 'Campaign %1$d skipped: idle %2$d < required %3$d.', 'meyvora-convert' ), $campaign->id, $idle, $required ), array( 'campaign_id' => $campaign->id ) );
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
			case 'once_per_week':
				return ( $now - $last_shown ) >= ( 7 * DAY_IN_SECONDS );
			case 'always':
				return true;
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
		$include = isset( $pages['include'] ) && is_array( $pages['include'] ) ? $pages['include'] : array();
		$exclude = CRO_Campaign_Model::get_pages_excluded_slugs( $pages );
		$include = array_map( array( __CLASS__, 'map_page_type' ), $include );
		$exclude = array_map( array( __CLASS__, 'map_page_type' ), $exclude );
		// Respect saved page_mode. Do not infer from default pages.include (Bug: 'all' was forced to 'include').
		$page_mode = isset( $tr['page_mode'] ) ? $tr['page_mode'] : 'all';
		if ( $page_mode === 'include' && ! empty( $include ) ) {
			$must[] = array( 'type' => 'page_type_in', 'value' => array_values( array_unique( $include ) ) );
		} elseif ( $page_mode === 'exclude' && ! empty( $exclude ) ) {
			$must_not[] = array( 'type' => 'page_type_in', 'value' => array_values( array_unique( $exclude ) ) );
		}
		// page_mode 'all' → no page-type must rules (checkout still suppressed elsewhere).

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

		// Geo (ISO2 list from builder).
		if ( class_exists( 'CRO_Geo' ) && ! empty( $tr['geo_countries'] ) && is_array( $tr['geo_countries'] ) ) {
			$geo = CRO_Geo::normalize_countries( $tr['geo_countries'] );
			if ( ! empty( $geo ) ) {
				$must[] = array( 'type' => 'geo_country_in', 'value' => $geo );
			}
		}

		// Behavior: min_time_on_page, min_scroll_depth, require_interaction
		$behavior = isset( $tr['behavior'] ) && is_array( $tr['behavior'] ) ? $tr['behavior'] : array();

		// Cart status (builder saves cart_status; must map here — was only handled in legacy CRO_Targeting).
		$cart_status = isset( $behavior['cart_status'] ) ? sanitize_key( (string) $behavior['cart_status'] ) : 'any';
		if ( 'has_items' === $cart_status ) {
			$must[] = array( 'type' => 'cart_has_items', 'value' => true );
		} elseif ( 'empty' === $cart_status ) {
			$must[] = array( 'type' => 'cart_empty', 'value' => true );
		}

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

		// Cart item count range
		$cart_min_items = isset( $behavior['cart_min_items'] ) && $behavior['cart_min_items'] !== '' ? (int) $behavior['cart_min_items'] : 0;
		$cart_max_items = isset( $behavior['cart_max_items'] ) && $behavior['cart_max_items'] !== '' ? (int) $behavior['cart_max_items'] : 0;
		if ( $cart_min_items > 0 ) {
			$must[] = array( 'type' => 'cart_item_count_gte', 'value' => $cart_min_items );
		}
		if ( $cart_max_items > 0 ) {
			$must[] = array( 'type' => 'cart_item_count_lte', 'value' => $cart_max_items );
		}
		if ( ! empty( $behavior['cart_has_sale_only'] ) ) {
			$must[] = array( 'type' => 'cart_has_sale_items', 'value' => true );
		}
		$min_pages = isset( $behavior['min_pages_viewed'] ) && $behavior['min_pages_viewed'] !== '' ? (int) $behavior['min_pages_viewed'] : 0;
		if ( $min_pages > 0 ) {
			$must[] = array( 'type' => 'pages_viewed_gte', 'value' => $min_pages );
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

		$min_sessions = isset( $visitor['min_sessions'] ) && $visitor['min_sessions'] !== '' ? (int) $visitor['min_sessions'] : 0;
		$max_sessions = isset( $visitor['max_sessions'] ) && $visitor['max_sessions'] !== '' ? (int) $visitor['max_sessions'] : 0;
		if ( $min_sessions > 0 ) {
			$must[] = array( 'type' => 'session_count_gte', 'value' => $min_sessions );
		}
		if ( $max_sessions > 0 ) {
			$must[] = array( 'type' => 'session_count_lte', 'value' => $max_sessions );
		}

		// UTM (optional per-field operator: utm_*_op).
		$utm_keys = array(
			'utm_source'   => array( 'path' => 'request.utm_source', 'param' => 'utm_source' ),
			'utm_medium'   => array( 'path' => 'request.utm_medium', 'param' => 'utm_medium' ),
			'utm_campaign' => array( 'path' => 'request.utm_campaign', 'param' => 'utm_campaign' ),
		);
		foreach ( $utm_keys as $field => $spec ) {
			$op  = isset( $tr[ $field . '_op' ] ) ? sanitize_key( (string) $tr[ $field . '_op' ] ) : '';
			$val = isset( $tr[ $field ] ) ? (string) $tr[ $field ] : '';
			if ( $op === '' && $val !== '' ) {
				$must[] = array(
					'type'  => 'utm_param_equals',
					'value' => array( 'param' => $spec['param'], 'value' => $val ),
				);
				continue;
			}
			if ( $op === '' ) {
				continue;
			}
			if ( 'is_empty' === $op ) {
				$must[] = array(
					'type'  => 'context_string_rule',
					'value' => array(
						'path'     => $spec['path'],
						'operator' => 'is_empty',
						'value'    => '',
					),
				);
				continue;
			}
			if ( $val === '' && ! in_array( $op, array( 'is_empty', 'is_not_empty' ), true ) ) {
				continue;
			}
			if ( 'is_not_empty' === $op ) {
				$must[] = array(
					'type'  => 'context_string_rule',
					'value' => array(
						'path'     => $spec['path'],
						'operator' => 'is_not_empty',
						'value'    => '',
					),
				);
				continue;
			}
			$must[] = array(
				'type'  => 'context_string_rule',
				'value' => array(
					'path'     => $spec['path'],
					'operator' => $op,
					'value'    => $val,
				),
			);
		}

		// Referrer domain (referrer_op optional; default contains when only referrer text is set).
		$ref_op  = isset( $tr['referrer_op'] ) ? sanitize_key( (string) $tr['referrer_op'] ) : '';
		$ref_val = isset( $tr['referrer'] ) ? sanitize_text_field( (string) $tr['referrer'] ) : '';
		$needle  = str_replace( 'www.', '', strtolower( $ref_val ) );
		if ( $ref_op === '' && $ref_val !== '' ) {
			$must[] = array(
				'type'  => 'context_string_rule',
				'value' => array(
					'path'     => 'request.referrer_domain',
					'operator' => 'contains',
					'value'    => $needle,
				),
			);
		} elseif ( 'is_empty' === $ref_op ) {
			$must[] = array(
				'type'  => 'context_string_rule',
				'value' => array(
					'path'     => 'request.referrer_domain',
					'operator' => 'is_empty',
					'value'    => '',
				),
			);
		} elseif ( 'is_not_empty' === $ref_op ) {
			$must[] = array(
				'type'  => 'context_string_rule',
				'value' => array(
					'path'     => 'request.referrer_domain',
					'operator' => 'is_not_empty',
					'value'    => '',
				),
			);
		} elseif ( $ref_op !== '' && $ref_val !== '' && in_array( $ref_op, array( 'equals', 'not_equals', 'contains', 'not_contains', 'starts_with' ), true ) ) {
			$must[] = array(
				'type'  => 'context_string_rule',
				'value' => array(
					'path'     => 'request.referrer_domain',
					'operator' => $ref_op,
					'value'    => $needle,
				),
			);
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
 * Use: cro_decide()->decide( $context, $visitor_state, $intent_signals, $trigger_type, $options )
 *
 * @return CRO_Decision_Engine
 */
function cro_decide() {
	return CRO_Decision_Engine::get_instance();
}
