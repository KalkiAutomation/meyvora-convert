<?php
/**
 * WooCommerce Blocks Integration (IntegrationInterface).
 * Registers scripts, styles, and script data for Cart and Checkout blocks so CRO
 * extension JS loads reliably and can access settings via getSetting('meyvora-convert_data').
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CRO Blocks Integration for WooCommerce Blocks (Cart / Checkout).
 *
 * Implements IntegrationInterface so scripts and data are enqueued when
 * Cart or Checkout blocks are on the page.
 */
class CRO_Blocks_Integration_WC implements \Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface {

	/**
	 * Script handle for the cart/checkout blocks extension (Slot/Fill) – built from blocks/cart-checkout-extension.
	 *
	 * @var string
	 */
	const SCRIPT_HANDLE = 'cro-blocks-cart-checkout-extension';

	/**
	 * Style handle for CRO boosters (cart/checkout block compat).
	 *
	 * @var string
	 */
	const STYLE_BOOSTERS_HANDLE = 'cro-blocks-boosters';

	/**
	 * Style handle for checkout-specific styles.
	 *
	 * @var string
	 */
	const STYLE_CHECKOUT_HANDLE = 'cro-blocks-checkout';

	/**
	 * The name of the integration (used for getSetting('meyvora-convert_data') in JS).
	 *
	 * @return string
	 */
	public function get_name() {
		return 'meyvora-convert';
	}

	/**
	 * Register scripts and styles. Called once per block (cart_block, checkout_block).
	 */
	public function initialize() {
		$this->register_styles();
		$this->register_script();
	}

	/**
	 * Register styles for block cart/checkout.
	 */
	private function register_styles() {
		wp_register_style(
			self::STYLE_BOOSTERS_HANDLE,
			CRO_PLUGIN_URL . 'public/css/cro-boosters' . cro_asset_min_suffix() . '.css',
			array(),
			CRO_VERSION
		);

		wp_register_style(
			self::STYLE_CHECKOUT_HANDLE,
			CRO_PLUGIN_URL . 'public/css/cro-checkout' . cro_asset_min_suffix() . '.css',
			array(),
			CRO_VERSION
		);
	}

	/**
	 * Register the cart/checkout blocks extension script (Slot/Fill).
	 * Uses build from blocks/cart-checkout-extension/build/; depends on wc-blocks-checkout and wc-settings.
	 */
	private function register_script() {
		$build_dir  = CRO_PLUGIN_DIR . 'blocks/cart-checkout-extension/build';
		$script_path = $build_dir . '/index.js';
		$asset_path  = $build_dir . '/index.asset.php';

		if ( ! file_exists( $script_path ) || ! file_exists( $asset_path ) ) {
			return;
		}

		$asset = include $asset_path;
		$deps  = isset( $asset['dependencies'] ) ? $asset['dependencies'] : array( 'wc-blocks-checkout', 'wc-settings' );
		$ver   = isset( $asset['version'] ) ? $asset['version'] : CRO_VERSION;

		wp_register_script(
			self::SCRIPT_HANDLE,
			CRO_PLUGIN_URL . 'blocks/cart-checkout-extension/build/index.js',
			$deps,
			$ver,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations(
				self::SCRIPT_HANDLE,
				'meyvora-convert',
				CRO_PLUGIN_DIR . 'languages'
			);
		}
	}

	/**
	 * Script handles to enqueue on the frontend when Cart or Checkout block is present.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		$script_path = CRO_PLUGIN_DIR . 'blocks/cart-checkout-extension/build/index.js';
		if ( file_exists( $script_path ) ) {
			return array( self::SCRIPT_HANDLE );
		}
		return array();
	}

	/**
	 * Editor script handles (none for this integration).
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return array();
	}

	/**
	 * Data for the block frontend, available in JS via getSetting('meyvora-convert_data').
	 * Includes plugin settings and offer/coupon config for cart and checkout.
	 *
	 * @return array All values must be serializable.
	 */
	public function get_script_data() {
		$cart_optimizer_enabled = false;
		$checkout_optimizer_enabled = false;
		$cart_settings = array();
		$checkout_settings = array();

		if ( function_exists( 'cro_settings' ) ) {
			$settings = cro_settings();
			$cart_optimizer_enabled = $settings->is_feature_enabled( 'cart_optimizer' );
			$checkout_optimizer_enabled = $settings->is_feature_enabled( 'checkout_optimizer' );
			if ( $cart_optimizer_enabled ) {
				$cart_settings = $settings->get_cart_optimizer_settings();
				$cart_settings = $this->sanitize_for_js( $cart_settings );
			}
			if ( $checkout_optimizer_enabled ) {
				$checkout_settings = $settings->get_checkout_settings();
				$checkout_settings = $this->sanitize_for_js( $checkout_settings );
			}
		}

		$free_shipping_threshold = (float) apply_filters( 'cro_free_shipping_threshold', 0 );

		$offer_banner_enabled = false;
		$offer_banner_position = 'both';
		$enable_dynamic_offers = true;
		if ( function_exists( 'cro_settings' ) && method_exists( cro_settings(), 'get_offer_banner_settings' ) ) {
			$ob = cro_settings()->get_offer_banner_settings();
			$offer_banner_enabled = ! empty( $ob['enable_offer_banner'] );
			$offer_banner_position = isset( $ob['banner_position'] ) && in_array( $ob['banner_position'], array( 'cart', 'checkout', 'both' ), true )
				? $ob['banner_position']
				: 'both';
		}
		$enable_dynamic_offers = (bool) apply_filters( 'cro_blocks_enable_dynamic_offers', $enable_dynamic_offers );

		$banner_cap_max   = 0;
		$banner_cap_cookie = class_exists( 'CRO_Visitor_State' ) ? CRO_Visitor_State::BANNER_VIEWS_COOKIE : 'cro_banner_views';
		if ( function_exists( 'cro_settings' ) && method_exists( cro_settings(), 'get_banner_frequency_settings' ) ) {
			$bf = cro_settings()->get_banner_frequency_settings();
			$banner_cap_max = (int) ( $bf['max_per_24h'] ?? 0 );
		}

		$rest_url = function_exists( 'rest_url' ) ? rest_url( 'cro/v1' ) : '';
		$rest_url = (string) $rest_url;

		$debug = false;
		if ( function_exists( 'cro_settings' ) ) {
			$debug = (bool) cro_settings()->get( 'general', 'blocks_debug_mode', false );
		}

		return array(
			'cartOptimizerEnabled'    => $cart_optimizer_enabled,
			'checkoutOptimizerEnabled' => $checkout_optimizer_enabled,
			'cartSettings'            => $cart_settings,
			'checkoutSettings'        => $checkout_settings,
			'couponsEnabled'          => function_exists( 'wc_coupons_enabled' ) && wc_coupons_enabled(),
			'freeShippingThreshold'   => $free_shipping_threshold,
			'offerBannerEnabled'      => $offer_banner_enabled,
			'enableDynamicOffers'    => $enable_dynamic_offers,
			'offerBannerPosition'    => $offer_banner_position,
			'restUrl'                 => $rest_url,
			'restNonce'               => wp_create_nonce( 'wp_rest' ),
			'bannerFrequencyCapMax'   => $banner_cap_max,
			'bannerViewsCookieName'   => $banner_cap_cookie,
			'debug'                   => $debug,
		);
	}

	/**
	 * Recursively ensure values are scalar or arrays of scalars (for JSON).
	 *
	 * @param array $arr Settings array.
	 * @return array
	 */
	private function sanitize_for_js( array $arr ) {
		$out = array();
		foreach ( $arr as $key => $value ) {
			if ( is_array( $value ) ) {
				$out[ $key ] = $this->sanitize_for_js( $value );
			} elseif ( is_scalar( $value ) || null === $value ) {
				$out[ $key ] = $value;
			}
		}
		return $out;
	}
}
