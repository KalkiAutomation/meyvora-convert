<?php
/**
 * Intent scoring system
 *
 * Weights signals (exit_mouse, scroll_up_fast, idle_time, time_on_page, etc.),
 * applies strength multipliers, normalizes to 0–100, and supports thresholds
 * and signal-quality validation.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MEYVC_Intent_Scorer — intent signal scoring for future trigger modes.
 *
 * NOTE: As of v1.x, all named trigger types (exit_intent, time, scroll,
 * inactivity, page_load, click) bypass this scorer entirely via the
 * trigger_only_types fast path in MEYVC_Decision_Engine::decide().
 * This class runs only when trigger_type is empty or unrecognised.
 */
class MEYVC_Intent_Scorer {

	/**
	 * Default signal weights (sum can exceed 100; normalized in score).
	 *
	 * @var array<string, int>
	 */
	private static $default_weights = array(
		'exit_mouse'     => 40,
		'scroll_up_fast' => 25,
		'idle_time'      => 20,
		'time_on_page'   => 15,
		'has_interacted' => 10,
		'tab_switch'     => 10,
		'exit_from_top'  => 5,
	);

	/**
	 * Default thresholds for when a signal is considered "fired".
	 *
	 * @var array<string, mixed>
	 */
	private static $default_thresholds = array(
		'exit_mouse'     => array( 'mouse_velocity_max' => -200, 'exit_from_top' => true ),
		'scroll_up_fast' => array( 'scroll_velocity_max' => -1000 ),
		'idle_time'      => array( 'seconds' => 10 ),
		'time_on_page'   => array( 'seconds' => 5 ),
		'has_interacted' => true,
		'tab_switch'     => array( 'visibility_hidden' => true ),
		'exit_from_top'  => array( 'exit_from_top' => true ),
	);

	/**
	 * Default score threshold to consider intent "met" (0–100).
	 *
	 * @var int
	 */
	private $default_threshold = 50;

	/**
	 * Weights and config (merged with defaults).
	 *
	 * @var array
	 */
	private $weights;

	/**
	 * Thresholds (merged with defaults).
	 *
	 * @var array
	 */
	private $thresholds;

	/**
	 * Constructor. Optional config overrides defaults.
	 *
	 * @param array $config Optional keys: weights, thresholds, default_threshold.
	 */
	public function __construct( array $config = array() ) {
		$this->weights    = isset( $config['weights'] ) && is_array( $config['weights'] )
			? array_merge( self::$default_weights, $config['weights'] )
			: self::$default_weights;
		$this->thresholds = isset( $config['thresholds'] ) && is_array( $config['thresholds'] )
			? array_merge( self::$default_thresholds, $config['thresholds'] )
			: self::$default_thresholds;
		$saved_threshold  = function_exists( 'meyvc_settings' ) ? (int) meyvc_settings()->get( 'campaigns', 'intent_score_threshold', 50 ) : 50;
		$this->default_threshold = isset( $config['default_threshold'] )
			? max( 0, min( 100, (int) $config['default_threshold'] ) )
			: max( 0, min( 100, $saved_threshold ) );
	}

	/**
	 * Calculate intent score from active signals.
	 *
	 * @param array $signals Active signals: signal_key => value (or true). Keys can be JS-style (e.g. exit_from_top, time_on_page, rapid_scroll_up).
	 * @param array $config  Optional overrides for this call: weights, default_threshold.
	 * @return array{ score: float, normalized: float, breakdown: array, details: array, raw_sum: float }
	 */
	public function calculate_score( array $signals, array $config = array() ) {
		$signals = $this->validate_signal_quality( $signals );
		if ( ! empty( $signals['_quality_reject'] ) ) {
			return array(
				'score'      => 0,
				'normalized' => 0,
				'breakdown'  => array(),
				'details'    => array(
					'rejected' => true,
					'reason'   => isset( $signals['_quality_reason'] ) ? $signals['_quality_reason'] : 'quality',
					'signals_in' => $signals,
				),
				'raw_sum'    => 0,
			);
		}
		$weights = isset( $config['weights'] ) && is_array( $config['weights'] )
			? array_merge( $this->weights, $config['weights'] )
			: $this->weights;

		$breakdown = array();
		$raw_sum   = 0.0;
		$max_sum   = 0.0;

		$alias = array(
			'exit_from_top'   => 'exit_mouse',
			'rapid_scroll_up' => 'scroll_up_fast',
			'visibility_hidden' => 'tab_switch',
		);

		foreach ( $weights as $signal_key => $weight ) {
			$value = null;
			if ( isset( $signals[ $signal_key ] ) ) {
				$value = $signals[ $signal_key ];
			} else {
				foreach ( $alias as $from => $to ) {
					if ( $to === $signal_key && isset( $signals[ $from ] ) ) {
						$value = $signals[ $from ];
						break;
					}
				}
			}
			if ( $value === null && $signal_key === 'time_on_page' && isset( $signals['time_on_page'] ) ) {
				$value = $signals['time_on_page'];
			}
			if ( $value === null && $signal_key === 'has_interacted' && isset( $signals['has_interacted'] ) ) {
				$value = $signals['has_interacted'];
			}

			$fired = $this->signal_fired( $signal_key, $value, $signals );
			$mult  = $fired ? $this->get_signal_multiplier( $signal_key, $value, $signals ) : 0.0;
			$contrib = ( $weight * $mult );
			$raw_sum += $contrib;
			$max_sum += (float) $weight * 1.5;

			$breakdown[ $signal_key ] = array(
				'weight'     => $weight,
				'multiplier' => $mult,
				'contribution' => $contrib,
				'fired'      => $fired,
				'value'      => $value,
			);
		}

		$normalized = $max_sum > 0 ? ( $raw_sum / $max_sum ) * 100 : 0;
		$normalized = max( 0, min( 100, round( $normalized, 1 ) ) );

		return array(
			'score'      => round( $normalized, 1 ),
			'normalized' => $normalized,
			'breakdown'  => $breakdown,
			'details'    => array(
				'raw_sum'   => round( $raw_sum, 2 ),
				'max_sum'   => round( $max_sum, 2 ),
				'signals_in' => $signals,
			),
			'raw_sum'    => $raw_sum,
		);
	}

	/**
	 * Whether a signal is considered "fired" given value and full signal set.
	 *
	 * @param string $signal_key Signal key.
	 * @param mixed  $value      Value for this signal.
	 * @param array  $all_signals All signals (for context).
	 * @return bool
	 */
	private function signal_fired( $signal_key, $value, array $all_signals ) {
		if ( $value === null || $value === false ) {
			return false;
		}
		$th = isset( $this->thresholds[ $signal_key ] ) ? $this->thresholds[ $signal_key ] : null;
		if ( $th === null ) {
			return (bool) $value;
		}
		if ( $th === true ) {
			return (bool) $value;
		}
		if ( ! is_array( $th ) ) {
			return (bool) $value;
		}
		switch ( $signal_key ) {
			case 'exit_mouse':
			case 'exit_from_top':
				$need_top = ! empty( $th['exit_from_top'] );
				$vel_ok   = true;
				if ( isset( $th['mouse_velocity_max'] ) && isset( $all_signals['mouse_velocity'] ) ) {
					$vel_ok = (float) $all_signals['mouse_velocity'] <= (float) $th['mouse_velocity_max'];
				}
				return ( $need_top ? ! empty( $all_signals['exit_from_top'] ) : true ) && (bool) $value && $vel_ok;
			case 'scroll_up_fast':
				$v = isset( $all_signals['scroll_velocity'] ) ? (float) $all_signals['scroll_velocity'] : ( is_numeric( $value ) ? (float) $value : 0 );
				$max = isset( $th['scroll_velocity_max'] ) ? (float) $th['scroll_velocity_max'] : -1000;
				return $v <= $max || (bool) $value;
			case 'idle_time':
				$sec = is_numeric( $value ) ? (int) $value : ( isset( $all_signals['idle_seconds'] ) ? (int) $all_signals['idle_seconds'] : 0 );
				$need = isset( $th['seconds'] ) ? (int) $th['seconds'] : 10;
				return $sec >= $need;
			case 'time_on_page':
				$sec = is_numeric( $value ) ? (int) $value : ( isset( $all_signals['time_on_page'] ) ? (int) $all_signals['time_on_page'] : 0 );
				$need = isset( $th['seconds'] ) ? (int) $th['seconds'] : 5;
				return $sec >= $need;
			case 'tab_switch':
				return ! empty( $all_signals['visibility_hidden'] ) || (bool) $value;
			default:
				return (bool) $value;
		}
	}

	/**
	 * Get signal strength multiplier (0–1.5) for weighting.
	 *
	 * @param string $signal_key  Signal key.
	 * @param mixed  $value       Value (seconds, velocity, bool).
	 * @param array  $all_signals All signals (for context).
	 * @return float
	 */
	public function get_signal_multiplier( $signal_key, $value = null, array $all_signals = array() ) {
		switch ( $signal_key ) {
			case 'exit_mouse':
			case 'exit_from_top':
				$vel = isset( $all_signals['mouse_velocity'] ) ? abs( (float) $all_signals['mouse_velocity'] ) : 0;
				if ( $vel > 800 ) {
					return 1.5;
				}
				if ( $vel > 400 ) {
					return 1.2;
				}
				if ( $vel > 200 ) {
					return 1.0;
				}
				return 0.6;
			case 'scroll_up_fast':
				$v = isset( $all_signals['scroll_velocity'] ) ? abs( (float) $all_signals['scroll_velocity'] ) : ( is_numeric( $value ) ? abs( (float) $value ) : 0 );
				if ( $v >= 2000 ) {
					return 1.4;
				}
				if ( $v >= 1000 ) {
					return 1.0;
				}
				if ( $v >= 500 ) {
					return 0.6;
				}
				return 0.3;
			case 'idle_time':
				$sec = is_numeric( $value ) ? (int) $value : ( isset( $all_signals['idle_seconds'] ) ? (int) $all_signals['idle_seconds'] : 0 );
				if ( $sec >= 60 ) {
					return 1.2;
				}
				if ( $sec >= 30 ) {
					return 1.0;
				}
				if ( $sec >= 10 ) {
					return 0.7;
				}
				return 0.4;
			case 'time_on_page':
				$sec = is_numeric( $value ) ? (int) $value : ( isset( $all_signals['time_on_page'] ) ? (int) $all_signals['time_on_page'] : 0 );
				if ( $sec >= 60 ) {
					return 1.3;
				}
				if ( $sec >= 30 ) {
					return 1.0;
				}
				if ( $sec >= 10 ) {
					return 0.7;
				}
				if ( $sec >= 5 ) {
					return 0.5;
				}
				return 0.2;
			case 'has_interacted':
				return (bool) $value ? 1.0 : 0.0;
			case 'tab_switch':
				return (bool) $value ? 1.0 : 0.0;
			default:
				return (bool) $value ? 1.0 : 0.0;
		}
	}

	/**
	 * Whether the score meets the given threshold.
	 *
	 * @param float    $score     Score (0–100).
	 * @param int|null $threshold Threshold (0–100). Null uses default.
	 * @return bool
	 */
	public function meets_threshold( $score, $threshold = null ) {
		$t = $threshold !== null ? max( 0, min( 100, (int) $threshold ) ) : $this->default_threshold;
		return (float) $score >= (float) $t;
	}

	/**
	 * Get recommended score threshold by page type.
	 *
	 * @param string $page_type Page type (e.g. checkout, cart, product, home).
	 * @return int Threshold 0–100.
	 */
	public static function get_recommended_threshold( $page_type ) {
		$t = array(
			'checkout'         => 75,
			'cart'             => 60,
			'product'          => 55,
			'product_category' => 50,
			'shop'             => 50,
			'home'             => 45,
			'page'             => 50,
			'post'             => 48,
			'account'          => 70,
			'other'            => 50,
		);
		$p = is_string( $page_type ) ? $page_type : 'other';
		return isset( $t[ $p ] ) ? $t[ $p ] : 50;
	}

	/**
	 * Validate signal quality and filter false positives.
	 *
	 * @param array $signals Raw signals (e.g. from JS).
	 * @return array Filtered/corrected signals and quality flags.
	 */
	public function validate_signal_quality( array $signals ) {
		$out = $signals;

		if ( ! empty( $signals['is_typing'] ) ) {
			$out['_quality_reject'] = true;
			$out['_quality_reason'] = 'typing';
			return $out;
		}

		if ( ! empty( $signals['is_fast_scrolling'] ) || ! empty( $signals['is_scrolling_fast'] ) ) {
			$out['time_on_page'] = isset( $out['time_on_page'] ) ? (int) $out['time_on_page'] : 0;
			$out['time_on_page'] = max( 0, $out['time_on_page'] - 10 );
			$out['_quality_penalty'] = 'fast_scroll';
		}

		if ( ! empty( $signals['visibility_hidden'] ) && ( empty( $signals['time_on_page'] ) || (int) $signals['time_on_page'] < 5 ) ) {
			$out['tab_switch'] = false;
			$out['_quality_penalty'] = isset( $out['_quality_penalty'] ) ? $out['_quality_penalty'] . ',tab_too_early' : 'tab_too_early';
		}

		$out['_validated'] = true;
		return $out;
	}

	/**
	 * Get default weights (for reference).
	 *
	 * @return array
	 */
	public static function get_default_weights() {
		return self::$default_weights;
	}

	/**
	 * Get default signal thresholds (for reference).
	 *
	 * @return array
	 */
	public static function get_default_thresholds() {
		return self::$default_thresholds;
	}
}
