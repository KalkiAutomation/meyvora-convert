<?php
/**
 * Checkout fields customization
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checkout fields class.
 */
class MEYVC_Checkout_Fields {

	/**
	 * Initialize checkout fields.
	 */
	public function __construct() {
		add_filter( 'woocommerce_checkout_fields', array( $this, 'customize_fields' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_custom_fields' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_custom_fields' ) );
	}

	/**
	 * Customize checkout fields.
	 *
	 * @param array $fields Checkout fields.
	 * @return array
	 */
	public function customize_fields( $fields ) {
		// Make phone field optional if configured
		if ( get_option( 'meyvc_checkout_phone_optional', false ) ) {
			$fields['billing']['billing_phone']['required'] = false;
		}

		// Reorder fields
		$fields['billing']['billing_email']['priority'] = 10;
		$fields['billing']['billing_phone']['priority']  = 20;

		return $fields;
	}

	/**
	 * Validate custom fields.
	 */
	public function validate_custom_fields() {
		// Add custom validation logic here
		do_action( 'meyvc_validate_checkout_fields' );
	}

	/**
	 * Save custom fields to order.
	 *
	 * @param int $order_id Order ID.
	 */
	public function save_custom_fields( $order_id ) {
		// Save custom fields
		do_action( 'meyvc_save_checkout_fields', $order_id );
	}
}
