<?php
/**
 * Default copy by feature and tone (Neutral / Urgent / Friendly).
 * All strings are benefit-first, concise, non-pushy. Use placeholders: {amount}, {count}, etc.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CRO_Default_Copy class.
 */
class CRO_Default_Copy {

	const TONE_NEUTRAL = 'neutral';
	const TONE_URGENT  = 'urgent';
	const TONE_FRIENDLY = 'friendly';

	/**
	 * Get default copy for a feature and tone.
	 *
	 * @param string $feature Feature: shipping_bar, stock_urgency, sticky_cart, exit_intent.
	 * @param string $tone    Tone: neutral, urgent, friendly.
	 * @param string $key     Key: progress, achieved, message, button_text, headline, subheadline, cta_text, dismiss_text, etc.
	 * @return string
	 */
	public static function get( $feature, $tone, $key ) {
		$tone = in_array( $tone, array( self::TONE_NEUTRAL, self::TONE_URGENT, self::TONE_FRIENDLY ), true ) ? $tone : self::TONE_NEUTRAL;
		$map  = self::get_map( $feature );
		if ( isset( $map[ $tone ][ $key ] ) ) {
			return $map[ $tone ][ $key ];
		}
		if ( isset( $map[ self::TONE_NEUTRAL ][ $key ] ) ) {
			return $map[ self::TONE_NEUTRAL ][ $key ];
		}
		return '';
	}

	/**
	 * Get all default copy for a feature (by tone).
	 *
	 * @param string $feature Feature slug.
	 * @return array [ tone => [ key => string ] ]
	 */
	public static function get_map( $feature ) {
		switch ( $feature ) {
			case 'shipping_bar':
				return array(
					self::TONE_NEUTRAL => array(
						'progress'  => __( 'Add {amount} more for free shipping', 'meyvora-convert' ),
						'achieved'  => __( 'You\'ve got free shipping', 'meyvora-convert' ),
					),
					self::TONE_URGENT => array(
						'progress'  => __( 'Only {amount} away from free shipping', 'meyvora-convert' ),
						'achieved'  => __( 'Free shipping unlocked!', 'meyvora-convert' ),
					),
					self::TONE_FRIENDLY => array(
						'progress'  => __( 'Just {amount} away from free shipping', 'meyvora-convert' ),
						'achieved'  => __( 'Free shipping on us', 'meyvora-convert' ),
					),
				);
			case 'stock_urgency':
				return array(
					self::TONE_NEUTRAL => array(
						'message' => __( '{count} left in stock', 'meyvora-convert' ),
					),
					self::TONE_URGENT => array(
						'message' => __( 'Only {count} left', 'meyvora-convert' ),
					),
					self::TONE_FRIENDLY => array(
						'message' => __( 'Just {count} left — grab yours', 'meyvora-convert' ),
					),
				);
			case 'sticky_cart':
				return array(
					self::TONE_NEUTRAL => array(
						'button_text' => __( 'Add to cart', 'meyvora-convert' ),
					),
					self::TONE_URGENT => array(
						'button_text' => __( 'Add to cart — limited stock', 'meyvora-convert' ),
					),
					self::TONE_FRIENDLY => array(
						'button_text' => __( 'Add to cart', 'meyvora-convert' ),
					),
				);
			case 'exit_intent':
				return array(
					self::TONE_NEUTRAL => array(
						'headline'     => __( 'Before you go', 'meyvora-convert' ),
						'subheadline'  => __( 'Here\'s a small thank-you for visiting', 'meyvora-convert' ),
						'cta_text'     => __( 'Claim offer', 'meyvora-convert' ),
						'dismiss_text' => __( 'No thanks', 'meyvora-convert' ),
					),
					self::TONE_URGENT => array(
						'headline'     => __( 'Wait — one more thing', 'meyvora-convert' ),
						'subheadline'  => __( 'Save on your order today', 'meyvora-convert' ),
						'cta_text'     => __( 'Get my discount', 'meyvora-convert' ),
						'dismiss_text' => __( 'No thanks', 'meyvora-convert' ),
					),
					self::TONE_FRIENDLY => array(
						'headline'     => __( 'We\'d love to treat you', 'meyvora-convert' ),
						'subheadline'  => __( 'Here\'s something special for you', 'meyvora-convert' ),
						'cta_text'     => __( 'Yes please', 'meyvora-convert' ),
						'dismiss_text' => __( 'Maybe next time', 'meyvora-convert' ),
					),
				);
			default:
				return array();
		}
	}

	/**
	 * Valid tones.
	 *
	 * @return array
	 */
	public static function get_tones() {
		return array(
			self::TONE_NEUTRAL  => __( 'Neutral', 'meyvora-convert' ),
			self::TONE_URGENT   => __( 'Urgent', 'meyvora-convert' ),
			self::TONE_FRIENDLY => __( 'Friendly', 'meyvora-convert' ),
		);
	}
}
