<?php
/**
 * Stock urgency booster
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stock urgency class.
 */
class MEYVC_Stock_Urgency {

	/**
	 * Initialize stock urgency.
	 */
	public function __construct() {
		if ( ! meyvc_settings()->is_feature_enabled( 'stock_urgency' ) ) {
			return;
		}

		add_action( 'woocommerce_single_product_summary', array( $this, 'render_stock_urgency' ), 15 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Enqueue stock urgency styles. Only on product pages; respects meyvc_should_enqueue_assets filter.
	 */
	public function enqueue_styles() {
		if ( ! class_exists( 'MEYVC_Public' ) || ! MEYVC_Public::should_enqueue_assets( 'stock_urgency' ) ) {
			return;
		}
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
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
	 * Render stock urgency message.
	 */
	public function render_stock_urgency() {
		global $product;

		if ( ! $product || ! $product->managing_stock() ) {
			return;
		}

		$stock_quantity = $product->get_stock_quantity();
		$threshold = (int) apply_filters(
			'meyvc_stock_urgency_threshold',
			(int) meyvc_settings()->get( 'boosters', 'stock_urgency_threshold', 10 )
		);
		if ( ! $stock_quantity || $stock_quantity > $threshold ) {
			return;
		}

		$settings = function_exists( 'meyvc_settings' ) ? meyvc_settings()->get_stock_urgency_settings() : array();
		$tone     = isset( $settings['tone'] ) ? $settings['tone'] : 'neutral';
		$template = isset( $settings['message_template'] ) && (string) $settings['message_template'] !== ''
			? (string) $settings['message_template']
			: ( class_exists( 'MEYVC_Default_Copy' ) ? MEYVC_Default_Copy::get( 'stock_urgency', $tone, 'message' ) : __( '{count} left in stock', 'meyvora-convert' ) );
		$message = str_replace( '{count}', (string) $stock_quantity, $template );
		$render_context = array( 'product_id' => $product_id, 'stock_quantity' => $stock_quantity, 'message' => $message );
		do_action( 'meyvc_frontend_before_render', 'stock_urgency', $render_context );

		echo '<div class="meyvc-stock-urgency">';
		echo '<span class="meyvc-stock-urgency-icon">' . wp_kses( MEYVC_Icons::svg( 'alert', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ) . '</span>';
		echo '<span class="meyvc-stock-urgency-message">' . esc_html( $message ) . '</span>';
		echo '</div>';
		do_action( 'meyvc_frontend_after_render', 'stock_urgency', $render_context );
	}
}
