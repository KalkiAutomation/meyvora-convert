<?php
/**
 * Trust badges booster
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trust badges class.
 */
class MEYVC_Trust_Badges {

	/**
	 * Whether trust badges have been rendered this request (avoids duplicate output).
	 *
	 * @var bool
	 */
	private $rendered = false;

	/**
	 * Initialize trust badges.
	 */
	public function __construct() {
		if ( ! function_exists( 'meyvc_settings' ) || ! meyvc_settings()->is_feature_enabled( 'trust_badges' ) ) {
			return;
		}
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_trust_badges' ), 25 );
		add_action( 'woocommerce_after_cart_totals', array( $this, 'render_trust_badges' ) );
		add_action( 'woocommerce_review_order_after_order_total', array( $this, 'render_trust_badges' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Enqueue trust badges styles. Only on WooCommerce pages; respects meyvc_should_enqueue_assets filter.
	 */
	public function enqueue_styles() {
		if ( ! class_exists( 'MEYVC_Public' ) || ! MEYVC_Public::should_enqueue_assets( 'trust_badges' ) ) {
			return;
		}
		if ( ! function_exists( 'is_woocommerce' ) || ! is_woocommerce() ) {
			return;
		}

		wp_enqueue_style(
			'meyvc-boosters',
			MEYVC_PLUGIN_URL . 'public/css/meyvc-boosters' . meyvc_asset_min_suffix() . '.css',
			array(),
			MEYVC_VERSION
		);
	}

	/**
	 * Render trust badges (used by WooCommerce hooks).
	 */
	public function render_trust_badges() {
		if ( $this->rendered ) {
			return;
		}
		$html = self::get_html();
		if ( $html !== '' ) {
			$this->rendered = true;
			echo wp_kses_post( $html );
		}
	}

	/**
	 * Return trust badges HTML (for block injection and reuse).
	 *
	 * @return string
	 */
	public static function get_html() {
		$template = defined( 'MEYVC_PLUGIN_DIR' ) ? MEYVC_PLUGIN_DIR . 'templates/boosters/trust-badges.php' : '';
		if ( ! $template || ! file_exists( $template ) ) {
			return '';
		}
		ob_start();
		include $template;
		return ob_get_clean();
	}

}
