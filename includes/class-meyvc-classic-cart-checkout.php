<?php
/**
 * Classic Cart/Checkout: enqueue assets only when cart or checkout and CRO features are enabled.
 * Trust strip, offer banner, and shipping progress are rendered by Cart_Optimizer, Checkout_Optimizer,
 * MEYVC_Offer_Banner, and MEYVC_Shipping_Bar; this class ensures shared CSS loads only when needed.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MEYVC_Classic_Cart_Checkout class.
 */
class MEYVC_Classic_Cart_Checkout {

	/**
	 * Constructor: enqueue assets only on cart/checkout when any CRO cart/checkout feature is on.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 15 );
	}

	/**
	 * Enqueue classic cart/checkout CSS only when needed (cart or checkout page + feature enabled).
	 */
	public function enqueue_assets() {
		if ( ! function_exists( 'is_cart' ) || ! function_exists( 'is_checkout' ) ) {
			return;
		}
		if ( ! is_cart() && ! is_checkout() ) {
			return;
		}
		if ( ! $this->any_classic_feature_enabled() ) {
			return;
		}

		wp_enqueue_style(
			'meyvc-classic-cart-checkout',
			MEYVC_PLUGIN_URL . 'public/css/meyvc-classic-cart-checkout' . meyvc_asset_min_suffix() . '.css',
			array(),
			defined( 'MEYVC_VERSION' ) ? MEYVC_VERSION : '1.0.0'
		);
	}

	/**
	 * Whether any CRO classic cart/checkout feature is enabled (cart optimizer, checkout optimizer, offer banner).
	 *
	 * @return bool
	 */
	private function any_classic_feature_enabled() {
		if ( ! function_exists( 'meyvc_settings' ) ) {
			return false;
		}
		$settings = meyvc_settings();
		if ( $settings->is_feature_enabled( 'cart_optimizer' ) ) {
			return true;
		}
		if ( $settings->is_feature_enabled( 'checkout_optimizer' ) ) {
			return true;
		}
		if ( method_exists( $settings, 'get_offer_banner_settings' ) ) {
			$ob = $settings->get_offer_banner_settings();
			if ( ! empty( $ob['enable_offer_banner'] ) ) {
				return true;
			}
		}
		return false;
	}
}
