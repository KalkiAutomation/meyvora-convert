<?php
/**
 * Trust badges booster
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Trust badges class.
 */
class CRO_Trust_Badges {

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
		if ( ! function_exists( 'cro_settings' ) || ! cro_settings()->is_feature_enabled( 'trust_badges' ) ) {
			return;
		}
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_trust_badges' ), 25 );
		add_action( 'woocommerce_after_cart_totals', array( $this, 'render_trust_badges' ) );
		add_action( 'woocommerce_review_order_after_order_total', array( $this, 'render_trust_badges' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Enqueue trust badges styles. Only on WooCommerce pages; respects cro_should_enqueue_assets filter.
	 */
	public function enqueue_styles() {
		if ( ! class_exists( 'CRO_Public' ) || ! CRO_Public::should_enqueue_assets( 'trust_badges' ) ) {
			return;
		}
		if ( ! function_exists( 'is_woocommerce' ) || ! is_woocommerce() ) {
			return;
		}

		wp_enqueue_style(
			'cro-boosters',
			CRO_PLUGIN_URL . 'public/css/cro-boosters.css',
			array(),
			CRO_VERSION
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
			echo $html;
		}
	}

	/**
	 * Return trust badges HTML (for block injection and reuse).
	 *
	 * @return string
	 */
	public static function get_html() {
		$template = defined( 'CRO_PLUGIN_DIR' ) ? CRO_PLUGIN_DIR . 'templates/boosters/trust-badges.php' : '';
		if ( ! $template || ! file_exists( $template ) ) {
			return '';
		}
		ob_start();
		include $template;
		return ob_get_clean();
	}

}
