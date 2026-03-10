<?php
/**
 * UX protection rules – gate campaigns by checkout, forms, motion, frequency, and timing.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CRO_UX_Rules class.
 *
 * Checks UX rules before allowing a campaign popup to show. Use should_allow_popup( $context )
 * when deciding whether to display a campaign.
 */
class CRO_UX_Rules {

	/**
	 * Check all UX rules before showing a campaign.
	 *
	 * @param array $context Context for rules: is_payment_form, is_checkout_form, is_typing,
	 *                       form_focused, prefers_reduced_motion, popups_shown_this_session,
	 *                       time_on_page.
	 * @return array {
	 *     @type bool   $allowed        Whether the popup is allowed.
	 *     @type string $reason         Optional. Block reason: checkout_interaction, form_interaction, max_popups, too_soon.
	 *     @type bool   $reduced_motion Optional. When allowed, true if prefers-reduced-motion should be honored.
	 * }
	 */
	public function should_allow_popup( $context ) {
		$context = is_array( $context ) ? $context : array();

		// Rule 1: Don't interrupt checkout process.
		if ( $this->is_checkout_interaction( $context ) ) {
			return array( 'allowed' => false, 'reason' => 'checkout_interaction' );
		}

		// Rule 2: Don't show during form interaction.
		if ( $this->is_form_interaction( $context ) ) {
			return array( 'allowed' => false, 'reason' => 'form_interaction' );
		}

		// Rule 3: Respect reduced motion preference.
		if ( $this->prefers_reduced_motion( $context ) ) {
			return array( 'allowed' => true, 'reduced_motion' => true );
		}

		// Rule 4: Don't spam (max popups per session).
		if ( $this->max_popups_reached( $context ) ) {
			return array( 'allowed' => false, 'reason' => 'max_popups' );
		}

		// Rule 5: Don't show immediately (min page time).
		if ( ! $this->min_page_time_reached( $context ) ) {
			return array( 'allowed' => false, 'reason' => 'too_soon' );
		}

		return array( 'allowed' => true );
	}

	/**
	 * Check if the user is interacting with checkout.
	 *
	 * @param array $context Context array.
	 * @return bool
	 */
	private function is_checkout_interaction( $context ) {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return false;
		}
		return ! empty( $context['is_payment_form'] ) || ! empty( $context['is_checkout_form'] );
	}

	/**
	 * Check if the user is interacting with any form.
	 *
	 * @param array $context Context array.
	 * @return bool
	 */
	private function is_form_interaction( $context ) {
		return ! empty( $context['is_typing'] ) || ! empty( $context['form_focused'] );
	}

	/**
	 * Check whether the context indicates prefers-reduced-motion.
	 *
	 * @param array $context Context array (e.g. from JS UX detector).
	 * @return bool
	 */
	private function prefers_reduced_motion( $context ) {
		return ! empty( $context['prefers_reduced_motion'] );
	}

	/**
	 * Check if max popups per session have already been shown.
	 *
	 * @param array $context Context array; expects 'popups_shown_this_session' (int).
	 * @return bool
	 */
	private function max_popups_reached( $context ) {
		if ( ! function_exists( 'cro_settings' ) ) {
			return false;
		}
		$max   = (int) cro_settings()->get( 'general', 'max_popups_per_session', 3 );
		$shown = isset( $context['popups_shown_this_session'] ) ? (int) $context['popups_shown_this_session'] : 0;
		return $shown >= $max && $max > 0;
	}

	/**
	 * Check if minimum time on page has been reached.
	 *
	 * @param array $context Context array; expects 'time_on_page' (seconds).
	 * @return bool
	 */
	private function min_page_time_reached( $context ) {
		if ( ! function_exists( 'cro_settings' ) ) {
			return true;
		}
		$min_time      = (int) cro_settings()->get( 'general', 'min_time_before_popup', 3 );
		$time_on_page  = isset( $context['time_on_page'] ) ? (int) $context['time_on_page'] : 0;
		return $time_on_page >= $min_time;
	}
}
