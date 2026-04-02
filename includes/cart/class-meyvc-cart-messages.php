<?php
/**
 * Cart messages
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cart messages class.
 */
class MEYVC_Cart_Messages {

	/**
	 * Initialize cart messages.
	 */
	public function __construct() {
		add_action( 'woocommerce_before_cart', array( $this, 'display_cart_messages' ) );
		add_action( 'woocommerce_after_cart_table', array( $this, 'display_cart_messages' ) );
	}

	/**
	 * Display cart messages.
	 */
	public function display_cart_messages() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$cart = WC()->cart;
		$cart_total = $cart->get_total( 'edit' );
		$free_shipping_threshold = $this->get_free_shipping_threshold();

		if ( $free_shipping_threshold > 0 && $cart_total < $free_shipping_threshold ) {
			$remaining = $free_shipping_threshold - $cart_total;
			$message = sprintf(
				/* translators: %s: remaining amount */
				esc_html__( 'Add %s more to get free shipping!', 'meyvora-convert' ),
				wc_price( $remaining )
			);

			$template = MEYVC_PLUGIN_DIR . 'templates/cart/cart-messages.php';
			if ( file_exists( $template ) ) {
				include $template;
			} else {
				echo '<div class="meyvc-cart-message meyvc-free-shipping">';
				echo '<p>' . esc_html( $message ) . '</p>';
				echo '</div>';
			}
		}
	}

	/**
	 * Get free shipping threshold.
	 *
	 * @return float
	 */
	private function get_free_shipping_threshold() {
		if ( ! function_exists( 'WC' ) ) {
			return 0;
		}

		$shipping_zones = WC_Shipping_Zones::get_zones();
		$threshold      = 0;

		foreach ( $shipping_zones as $zone ) {
			foreach ( $zone['shipping_methods'] as $method ) {
				if ( 'free_shipping' === $method->id && isset( $method->min_amount ) ) {
					$threshold = floatval( $method->min_amount );
					break 2;
				}
			}
		}

		return apply_filters( 'meyvc_free_shipping_threshold', $threshold );
	}
}
