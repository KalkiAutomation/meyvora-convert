<?php
/**
 * CRO Input Validator
 *
 * Comprehensive input validation for all user inputs
 *
 * @package Meyvora_Convert
 */

defined( 'ABSPATH' ) || exit;

class MEYVC_Validator {

	/** @var array Validation errors */
	private static $errors = array();

	/**
	 * Validate campaign data
	 *
	 * @param array $data Campaign data.
	 * @return bool True if valid.
	 */
	public static function validate_campaign( $data ) {
		self::$errors = array();

		if ( empty( $data['name'] ) ) {
			self::$errors['name'] = __( 'Campaign name is required', 'meyvora-convert' );
		} elseif ( strlen( $data['name'] ) > 255 ) {
			self::$errors['name'] = __( 'Campaign name is too long (max 255 characters)', 'meyvora-convert' );
		}

		$valid_statuses = array( 'draft', 'active', 'paused', 'archived' );
		if ( ! empty( $data['status'] ) && ! in_array( $data['status'], $valid_statuses, true ) ) {
			self::$errors['status'] = __( 'Invalid campaign status', 'meyvora-convert' );
		}

		if ( isset( $data['priority'] ) ) {
			$priority = (int) $data['priority'];
			if ( $priority < 1 || $priority > 100 ) {
				self::$errors['priority'] = __( 'Priority must be between 1 and 100', 'meyvora-convert' );
			}
		}

		if ( ! empty( $data['content'] ) ) {
			$content = is_array( $data['content'] ) ? $data['content'] : json_decode( $data['content'], true );

			if ( $content ) {
				$allowed_cta_actions = array(
					'close',
					'url',
					'cart',
					'checkout',
					'apply_coupon',
					'apply_coupon_checkout',
					'copy_coupon',
				);
				if ( isset( $content['cta_action'] ) && ! in_array( (string) $content['cta_action'], $allowed_cta_actions, true ) ) {
					self::$errors['cta_action'] = __( 'Invalid CTA action.', 'meyvora-convert' );
				}

				if ( ( $content['cta_action'] ?? '' ) === 'url' && '' === trim( (string) ( $content['cta_url'] ?? '' ) ) ) {
					self::$errors['cta_url'] = __( 'Enter a URL for the Go to URL action.', 'meyvora-convert' );
				}

				$coupon_cta_actions = array( 'apply_coupon', 'apply_coupon_checkout', 'copy_coupon' );
				$code_raw             = isset( $content['coupon_code'] ) ? trim( (string) $content['coupon_code'] ) : '';
				if ( ! empty( $content['cta_action'] ) && in_array( $content['cta_action'], $coupon_cta_actions, true ) && '' === $code_raw ) {
					self::$errors['coupon_code'] = __( 'A coupon code is required for the selected CTA action.', 'meyvora-convert' );
				} elseif ( '' !== $code_raw && ! self::is_valid_coupon_code( $content['coupon_code'] ) ) {
					self::$errors['coupon_code'] = __( 'Invalid coupon code format', 'meyvora-convert' );
				}

				if ( isset( $content['countdown_minutes'] ) ) {
					$minutes = (int) $content['countdown_minutes'];
					if ( $minutes < 1 || $minutes > 1440 ) {
						self::$errors['countdown_minutes'] = __( 'Countdown must be between 1 and 1440 minutes', 'meyvora-convert' );
					}
				}

				if ( ! empty( $content['cta_url'] ) && ( $content['cta_action'] ?? '' ) === 'url' ) {
					if ( ! filter_var( $content['cta_url'], FILTER_VALIDATE_URL ) ) {
						self::$errors['cta_url'] = __( 'Invalid URL format', 'meyvora-convert' );
					}
				}
			}
		}

		if ( ! empty( $data['styling'] ) ) {
			$styling = is_array( $data['styling'] ) ? $data['styling'] : json_decode( $data['styling'], true );

			if ( $styling ) {
				$color_fields = array( 'bg_color', 'text_color', 'headline_color', 'button_bg_color', 'button_text_color' );
				foreach ( $color_fields as $field ) {
					if ( ! empty( $styling[ $field ] ) && ! self::is_valid_color( $styling[ $field ] ) ) {
						/* translators: %s is the name of the color setting field. */
				self::$errors[ $field ] = sprintf( __( 'Invalid color format for %s', 'meyvora-convert' ), $field );
					}
				}

				if ( isset( $styling['border_radius'] ) ) {
					$radius = (int) $styling['border_radius'];
					if ( $radius < 0 || $radius > 50 ) {
						self::$errors['border_radius'] = __( 'Border radius must be between 0 and 50', 'meyvora-convert' );
					}
				}
			}
		}

		return empty( self::$errors );
	}

	/**
	 * Validate A/B test data
	 *
	 * @param array $data A/B test data.
	 * @return bool True if valid.
	 */
	public static function validate_ab_test( $data ) {
		self::$errors = array();

		if ( empty( $data['name'] ) ) {
			self::$errors['name'] = __( 'Test name is required', 'meyvora-convert' );
		}

		if ( empty( $data['campaign_id'] ) || ! is_numeric( $data['campaign_id'] ) ) {
			self::$errors['campaign_id'] = __( 'Valid campaign is required', 'meyvora-convert' );
		}

		if ( isset( $data['min_sample_size'] ) ) {
			$sample = (int) $data['min_sample_size'];
			if ( $sample < 10 || $sample > 100000 ) {
				self::$errors['min_sample_size'] = __( 'Sample size must be between 10 and 100,000', 'meyvora-convert' );
			}
		}

		if ( isset( $data['confidence_level'] ) ) {
			$valid_levels = array( 80, 85, 90, 95, 99 );
			if ( ! in_array( (int) $data['confidence_level'], $valid_levels, true ) ) {
				self::$errors['confidence_level'] = __( 'Invalid confidence level', 'meyvora-convert' );
			}
		}

		return empty( self::$errors );
	}

	/**
	 * Validate email address
	 *
	 * @param string $email Email address.
	 * @return bool True if valid.
	 */
	public static function validate_email( $email ) {
		if ( empty( $email ) ) {
			return false;
		}

		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			return false;
		}

		$disposable_domains = array(
			'tempmail.com',
			'throwaway.com',
			'mailinator.com',
			'guerrillamail.com',
			'10minutemail.com',
			'temp-mail.org',
		);

		$domain = strtolower( substr( strrchr( $email, '@' ), 1 ) );
		if ( in_array( $domain, $disposable_domains, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate color hex code
	 *
	 * @param string $color Hex color (e.g. #fff or #ffffff).
	 * @return int|false 1 if valid, 0 or false otherwise.
	 */
	public static function is_valid_color( $color ) {
		return preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color );
	}

	/**
	 * Validate coupon code format
	 *
	 * @param string $code Coupon code.
	 * @return int|false 1 if valid.
	 */
	public static function is_valid_coupon_code( $code ) {
		return preg_match( '/^[A-Za-z0-9_-]{3,50}$/', $code );
	}

	/**
	 * Sanitize and validate JSON
	 *
	 * @param string|array $json JSON string or array.
	 * @return array|null Decoded array or null on error.
	 */
	public static function validate_json( $json ) {
		if ( is_array( $json ) ) {
			return $json;
		}

		$decoded = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			if ( class_exists( 'MEYVC_Error_Handler' ) ) {
				MEYVC_Error_Handler::log( 'VALIDATION', 'Invalid JSON', array(
					'error' => json_last_error_msg(),
				) );
			}
			return null;
		}

		return $decoded;
	}

	/**
	 * Validate date range
	 *
	 * @param string $from Start date.
	 * @param string $to   End date.
	 * @return bool True if valid (max 1 year range).
	 */
	public static function validate_date_range( $from, $to ) {
		$from_time = strtotime( $from );
		$to_time   = strtotime( $to );

		if ( ! $from_time || ! $to_time ) {
			return false;
		}

		if ( $from_time > $to_time ) {
			return false;
		}

		if ( ( $to_time - $from_time ) > ( 365 * 24 * 60 * 60 ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get validation errors
	 *
	 * @return array
	 */
	public static function get_errors() {
		return self::$errors;
	}

	/**
	 * Get first error message
	 *
	 * @return string|null
	 */
	public static function get_first_error() {
		$first = reset( self::$errors );
		return $first !== false ? $first : null;
	}

	/**
	 * Sanitize targeting rules
	 *
	 * @param array|string $rules Rules array or JSON string.
	 * @return array Sanitized rules with must, should, must_not keys.
	 */
	public static function sanitize_targeting_rules( $rules ) {
		if ( ! is_array( $rules ) ) {
			$rules = json_decode( $rules, true );
		}

		if ( ! $rules ) {
			return array(
				'must'      => array(),
				'should'    => array(),
				'must_not'  => array(),
			);
		}

		$sanitized = array(
			'must'      => array(),
			'should'    => array(),
			'must_not'  => array(),
		);

		foreach ( array( 'must', 'should', 'must_not' ) as $group ) {
			if ( ! empty( $rules[ $group ] ) && is_array( $rules[ $group ] ) ) {
				foreach ( $rules[ $group ] as $rule ) {
					$sanitized[ $group ][] = self::sanitize_rule( $rule );
				}
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize single rule
	 *
	 * @param array $rule Rule array.
	 * @return array
	 */
	private static function sanitize_rule( $rule ) {
		$valid_operators = array( '=', '!=', '>', '<', '>=', '<=', 'in', 'not_in', 'contains', 'not_contains', 'exists', 'not_exists' );

		$sanitized = array();

		if ( isset( $rule['type'] ) ) {
			$sanitized['type'] = sanitize_key( $rule['type'] );
		}

		if ( isset( $rule['field'] ) ) {
			$sanitized['field'] = sanitize_text_field( $rule['field'] );
		}

		if ( isset( $rule['operator'] ) ) {
			$sanitized['operator'] = in_array( $rule['operator'], $valid_operators, true ) ? $rule['operator'] : '=';
		}

		if ( isset( $rule['value'] ) ) {
			if ( is_array( $rule['value'] ) ) {
				$sanitized['value'] = array_map( 'sanitize_text_field', $rule['value'] );
			} else {
				$sanitized['value'] = sanitize_text_field( $rule['value'] );
			}
		}

		return $sanitized;
	}
}
