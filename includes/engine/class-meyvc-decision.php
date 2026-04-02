<?php
/**
 * Decision object
 *
 * Value object for campaign resolution: show/don’t show, campaign, reason,
 * reason_code, cooldown, debug_log. Every decision is explainable with a
 * reason and full debug log.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MEYVC_Decision class.
 *
 * Value object representing a single campaign decision (show/skip, campaign, reason).
 */
class MEYVC_Decision {

	/** @var bool Whether to show a campaign. */
	public $show;

	/** @var object|array|null Campaign object or array (when show is true). */
	public $campaign;

	/** @var int|null Campaign ID. */
	public $campaign_id;

	/** @var string Human-readable reason. */
	public $reason;

	/** @var string Machine-readable reason code. */
	public $reason_code;

	/** @var bool Whether a cooldown was applied. */
	public $cooldown_applied;

	/** @var string|null Suppression reason when blocked. */
	public $suppression_reason;

	/** @var array Rule evaluation results (campaign_id / rule => passed / detail). */
	public $rule_results;

	/** @var float|null Intent score when available. */
	public $intent_score;

	/** @var float|null Intent threshold used. */
	public $intent_threshold;

	/** @var array Debug log entries: { time, type, message, data? }. */
	public $debug_log;

	/** @var float Creation timestamp. */
	public $timestamp;

	/** @var array Per-campaign evaluation results from record_campaign_result(). */
	public $evaluation_results;

	/** @var int|null A/B test ID when decision used an A/B variation. */
	public $ab_test_id;

	/** @var int|null Variation ID when decision used an A/B variation. */
	public $variation_id;

	/** @var bool|null Whether the shown variation is control (when in A/B test). */
	public $is_control;

	/** @var int|null Per-campaign fallback to show after dismiss (parent campaign id’s fallback_id). */
	public $fallback_campaign_id;

	/** @var int|null Delay in seconds before showing fallback_campaign_id. */
	public $fallback_delay_seconds;

	/**
	 * Constructor.
	 *
	 * @param bool        $show   Whether to show.
	 * @param object|null $campaign Campaign when showing.
	 * @param string      $reason   Human-readable reason.
	 * @param string      $reason_code Machine code.
	 */
	public function __construct( $show = false, $campaign = null, $reason = '', $reason_code = 'unknown' ) {
		$this->show               = (bool) $show;
		$this->campaign           = $campaign;
		$this->campaign_id        = $this->resolve_campaign_id( $campaign );
		$this->reason             = is_string( $reason ) ? $reason : '';
		$this->reason_code        = is_string( $reason_code ) ? $reason_code : 'unknown';
		$this->cooldown_applied   = false;
		$this->suppression_reason = null;
		$this->rule_results       = array();
		$this->intent_score       = null;
		$this->intent_threshold   = null;
		$this->debug_log          = array();
		$this->timestamp          = microtime( true );
		$this->evaluation_results = array();
		$this->ab_test_id         = null;
		$this->variation_id       = null;
		$this->is_control         = null;
		$this->fallback_campaign_id   = null;
		$this->fallback_delay_seconds = null;
	}

	/**
	 * Resolve campaign ID from campaign object/array.
	 *
	 * @param object|array|null $campaign Campaign.
	 * @return int|null
	 */
	private function resolve_campaign_id( $campaign ) {
		if ( $campaign === null ) {
			return null;
		}
		if ( is_object( $campaign ) && isset( $campaign->id ) ) {
			return (int) $campaign->id;
		}
		if ( is_array( $campaign ) && isset( $campaign['id'] ) ) {
			return (int) $campaign['id'];
		}
		return null;
	}

	/**
	 * Create a decision to show a campaign.
	 *
	 * @param object|array $campaign     Campaign to show.
	 * @param int|null     $campaign_id  Optional campaign ID (else from campaign).
	 * @param string       $reason       Human-readable reason.
	 * @param string       $reason_code  Machine code (default 'show').
	 * @return MEYVC_Decision
	 */
	public static function show( $campaign, $campaign_id = null, $reason = '', $reason_code = 'show' ) {
		$id = $campaign_id;
		if ( $id === null && $campaign !== null ) {
			if ( is_object( $campaign ) && isset( $campaign->id ) ) {
				$id = (int) $campaign->id;
			} elseif ( is_array( $campaign ) && isset( $campaign['id'] ) ) {
				$id = (int) $campaign['id'];
			}
		}
		$d = new self( true, $campaign, $reason ?: __( 'Campaign selected.', 'meyvora-convert' ), $reason_code );
		$d->campaign_id = $id;
		$d->log( 'SUCCESS', sprintf( 'Show campaign %s', $id !== null ? (string) $id : 'unknown' ), array( 'reason' => $reason, 'reason_code' => $reason_code ) );
		return $d;
	}

	/**
	 * Create a decision not to show any campaign.
	 *
	 * @param string $reason      Human-readable reason.
	 * @param string $reason_code Machine code (default 'skip').
	 * @return MEYVC_Decision
	 */
	public static function dont_show( $reason = '', $reason_code = 'skip' ) {
		$d = new self( false, null, $reason ?: __( 'No campaign to show.', 'meyvora-convert' ), $reason_code );
		$d->log( 'SKIP', $d->reason, array( 'reason_code' => $reason_code ) );
		return $d;
	}

	/**
	 * Add a debug log entry.
	 *
	 * @param string     $type    Log type (e.g. INFO, SKIP, SUCCESS, RULE, UX).
	 * @param string     $message Message.
	 * @param array|null $data    Optional extra data.
	 * @return MEYVC_Decision
	 */
	public function log( $type, $message, $data = null ) {
		$this->debug_log[] = array(
			'time'    => microtime( true ),
			'type'    => (string) $type,
			'message' => (string) $message,
			'data'    => $data,
		);
		return $this;
	}

	/**
	 * Record a per-campaign evaluation result.
	 *
	 * @param int         $campaign_id Campaign ID.
	 * @param bool        $passed      Whether the campaign passed evaluation.
	 * @param array|null  $detail      Optional detail (e.g. rule breakdown).
	 * @return MEYVC_Decision
	 */
	public function record_campaign_result( $campaign_id, $passed, $detail = null ) {
		$this->evaluation_results[] = array(
			'campaign_id' => (int) $campaign_id,
			'passed'      => (bool) $passed,
			'detail'      => $detail,
		);
		return $this;
	}

	/**
	 * Set cooldown-applied flag and optional suppression reason.
	 *
	 * @param bool        $applied Whether cooldown was applied.
	 * @param string|null $suppression_reason Optional reason.
	 * @return MEYVC_Decision
	 */
	public function set_cooldown( $applied, $suppression_reason = null ) {
		$this->cooldown_applied   = (bool) $applied;
		$this->suppression_reason = $suppression_reason !== null ? (string) $suppression_reason : null;
		return $this;
	}

	/**
	 * Set intent score and threshold (for explainability).
	 *
	 * @param float|null $score     Intent score.
	 * @param float|null $threshold Threshold used.
	 * @return MEYVC_Decision
	 */
	public function set_intent( $score, $threshold = null ) {
		$this->intent_score     = $score !== null ? (float) $score : null;
		$this->intent_threshold = $threshold !== null ? (float) $threshold : null;
		return $this;
	}

	/**
	 * Set rule evaluation results.
	 *
	 * @param array $results Map of rule/step => passed or detail.
	 * @return MEYVC_Decision
	 */
	public function set_rule_results( array $results ) {
		$this->rule_results = $results;
		return $this;
	}

	/**
	 * Get a human-readable summary of the decision.
	 *
	 * @return string
	 */
	public function get_summary() {
		$lines = array();
		$lines[] = $this->show
			? sprintf( 'Show campaign %s — %s (%s)', $this->campaign_id ?? '?', $this->reason, $this->reason_code )
			: sprintf( 'Do not show — %s (%s)', $this->reason, $this->reason_code );
		if ( $this->cooldown_applied && $this->suppression_reason ) {
			$lines[] = 'Cooldown/suppression: ' . $this->suppression_reason;
		}
		if ( $this->intent_score !== null ) {
			$lines[] = sprintf( 'Intent score: %s (threshold: %s)', $this->intent_score, $this->intent_threshold ?? 'n/a' );
		}
		foreach ( $this->debug_log as $entry ) {
			$lines[] = sprintf( '[%s] %s', $entry['type'], $entry['message'] );
		}
		return implode( "\n", $lines );
	}

	/**
	 * Export for JavaScript / API response (minimal, safe).
	 *
	 * @return array
	 */
	public function to_array() {
		$out = array(
			'show'        => $this->show,
			'campaign_id' => $this->campaign_id,
			'reason'      => $this->reason,
			'reason_code' => $this->reason_code,
		);
		if ( $this->show && $this->campaign !== null ) {
			if ( is_object( $this->campaign ) && method_exists( $this->campaign, 'to_frontend_array' ) ) {
				$out['campaign'] = $this->campaign->to_frontend_array();
			} elseif ( is_array( $this->campaign ) ) {
				$out['campaign'] = $this->campaign;
			} else {
				$out['campaign'] = array( 'id' => $this->campaign_id );
			}
		} else {
			$out['campaign'] = null;
		}
		if ( $this->cooldown_applied ) {
			$out['cooldown_applied'] = true;
			$out['suppression_reason'] = $this->suppression_reason;
		}
		if ( $this->ab_test_id !== null && $this->variation_id !== null ) {
			$out['ab_test_id']   = (int) $this->ab_test_id;
			$out['variation_id'] = (int) $this->variation_id;
			$out['is_control']   = (bool) $this->is_control;
		}
		return $out;
	}

	/**
	 * Export for admin debugging (full log and internals).
	 *
	 * @return array
	 */
	public function to_debug_array() {
		return array(
			'show'                => $this->show,
			'campaign_id'         => $this->campaign_id,
			'reason'              => $this->reason,
			'reason_code'         => $this->reason_code,
			'cooldown_applied'    => $this->cooldown_applied,
			'suppression_reason'  => $this->suppression_reason,
			'rule_results'        => $this->rule_results,
			'intent_score'        => $this->intent_score,
			'intent_threshold'    => $this->intent_threshold,
			'evaluation_results'  => $this->evaluation_results,
			'debug_log'           => $this->debug_log,
			'timestamp'           => $this->timestamp,
			'summary'             => $this->get_summary(),
			'ab_test_id'          => $this->ab_test_id,
			'variation_id'        => $this->variation_id,
			'is_control'          => $this->is_control,
		);
	}
}
