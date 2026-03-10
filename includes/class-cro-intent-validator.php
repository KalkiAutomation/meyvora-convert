<?php
/**
 * Validate trigger signals
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CRO_Intent_Validator class.
 *
 * Validates exit intent and other trigger signals to reduce false positives.
 */
class CRO_Intent_Validator {

	/**
	 * Confidence threshold (0-1).
	 *
	 * @var float
	 */
	private $confidence_threshold = 0.6; // 60% confidence required.

	/**
	 * Validate exit intent signal quality.
	 *
	 * @param array $signal_data Signal data from JavaScript.
	 * @return array Validation result with valid, confidence, and reasons.
	 */
	public function validate_exit_intent( $signal_data ) {
		$confidence = 0;
		$reasons = array();

		// Factor 1: Mouse velocity (desktop).
		if ( isset( $signal_data['mouse_velocity'] ) ) {
			$velocity = abs( $signal_data['mouse_velocity'] );
			if ( $velocity > 500 ) {
				$confidence += 0.3;
				$reasons[] = 'high_velocity';
			} elseif ( $velocity > 200 ) {
				$confidence += 0.2;
				$reasons[] = 'medium_velocity';
			}
		}

		// Factor 2: Exit direction (top of viewport).
		if ( ! empty( $signal_data['exit_from_top'] ) ) {
			$confidence += 0.25;
			$reasons[] = 'exit_top';
		}

		// Factor 3: Time on page (more time = more confidence).
		$time_on_page = $signal_data['time_on_page'] ?? 0;
		if ( $time_on_page > 30 ) {
			$confidence += 0.2;
			$reasons[] = 'engaged_time';
		} elseif ( $time_on_page > 10 ) {
			$confidence += 0.1;
			$reasons[] = 'some_time';
		}

		// Factor 4: User has interacted.
		if ( ! empty( $signal_data['has_interacted'] ) ) {
			$confidence += 0.15;
			$reasons[] = 'interacted';
		}

		// Factor 5: Not during fast scroll (penalize).
		if ( ! empty( $signal_data['is_fast_scrolling'] ) ) {
			$confidence -= 0.3;
			$reasons[] = 'fast_scroll_penalty';
		}

		// Factor 6: Not focused on form (penalize).
		if ( ! empty( $signal_data['is_typing'] ) ) {
			$confidence -= 0.5;
			$reasons[] = 'typing_penalty';
		}

		// Clamp confidence between 0 and 1.
		$confidence = max( 0, min( 1, $confidence ) );

		return array(
			'valid'      => $confidence >= $this->confidence_threshold,
			'confidence' => round( $confidence, 2 ),
			'threshold'  => $this->confidence_threshold,
			'reasons'    => $reasons,
		);
	}

	/**
	 * Validate mobile exit intent (different signals).
	 *
	 * @param array $signal_data Signal data from JavaScript.
	 * @return array Validation result with valid, confidence, and reasons.
	 */
	public function validate_mobile_exit( $signal_data ) {
		$confidence = 0;
		$reasons = array();

		// Factor 1: Rapid scroll up.
		if ( ! empty( $signal_data['rapid_scroll_up'] ) ) {
			$scroll_velocity = $signal_data['scroll_velocity'] ?? 0;
			if ( $scroll_velocity > 1000 ) {
				$confidence += 0.3;
				$reasons[] = 'rapid_scroll';
			}
		}

		// Factor 2: Tab/app switch.
		if ( ! empty( $signal_data['visibility_hidden'] ) ) {
			$confidence += 0.25;
			$reasons[] = 'tab_switch';
		}

		// Factor 3: Back button attempt.
		if ( ! empty( $signal_data['back_button'] ) ) {
			$confidence += 0.35;
			$reasons[] = 'back_button';
		}

		// Factor 4: Time on page.
		$time_on_page = $signal_data['time_on_page'] ?? 0;
		if ( $time_on_page > 20 ) {
			$confidence += 0.15;
			$reasons[] = 'engaged';
		}

		// Penalties.
		if ( ! empty( $signal_data['is_typing'] ) ) {
			$confidence -= 0.5;
			$reasons[] = 'typing_penalty';
		}

		if ( ! empty( $signal_data['is_scrolling_fast'] ) ) {
			$confidence -= 0.2;
			$reasons[] = 'scanning_penalty';
		}

		$confidence = max( 0, min( 1, $confidence ) );

		return array(
			'valid'      => $confidence >= $this->confidence_threshold,
			'confidence' => round( $confidence, 2 ),
			'reasons'    => $reasons,
		);
	}

	/**
	 * Set confidence threshold.
	 *
	 * @param float $threshold Threshold value (0-1).
	 */
	public function set_threshold( $threshold ) {
		$this->confidence_threshold = max( 0, min( 1, $threshold ) );
	}

	/**
	 * Get current confidence threshold.
	 *
	 * @return float
	 */
	public function get_threshold() {
		return $this->confidence_threshold;
	}
}
