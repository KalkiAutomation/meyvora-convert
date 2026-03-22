<?php
/**
 * Stock urgency booster
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Stock urgency class.
 */
class CRO_Stock_Urgency {

	/**
	 * Initialize stock urgency.
	 */
	public function __construct() {
		if ( ! cro_settings()->is_feature_enabled( 'stock_urgency' ) ) {
			return;
		}

		add_action( 'woocommerce_single_product_summary', array( $this, 'render_stock_urgency' ), 15 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Enqueue stock urgency styles. Only on product pages; respects cro_should_enqueue_assets filter.
	 */
	public function enqueue_styles() {
		if ( ! class_exists( 'CRO_Public' ) || ! CRO_Public::should_enqueue_assets( 'stock_urgency' ) ) {
			return;
		}
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
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
	 * Render stock urgency message.
	 */
	public function render_stock_urgency() {
		global $product;

		if ( ! $product || ! $product->managing_stock() ) {
			return;
		}

		$stock_quantity = $product->get_stock_quantity();
		$threshold = (int) apply_filters(
			'cro_stock_urgency_threshold',
			(int) cro_settings()->get( 'boosters', 'stock_urgency_threshold', 10 )
		);
		if ( ! $stock_quantity || $stock_quantity > $threshold ) {
			return;
		}

		$settings = function_exists( 'cro_settings' ) ? cro_settings()->get_stock_urgency_settings() : array();
		$tone     = isset( $settings['tone'] ) ? $settings['tone'] : 'neutral';
		$template = isset( $settings['message_template'] ) && (string) $settings['message_template'] !== ''
			? (string) $settings['message_template']
			: ( class_exists( 'CRO_Default_Copy' ) ? CRO_Default_Copy::get( 'stock_urgency', $tone, 'message' ) : __( '{count} left in stock', 'meyvora-convert' ) );
		$message = str_replace( '{count}', (string) $stock_quantity, $template );
		$render_context = array( 'product_id' => $product_id, 'stock_quantity' => $stock_quantity, 'message' => $message );
		do_action( 'cro_frontend_before_render', 'stock_urgency', $render_context );

		echo '<div class="cro-stock-urgency">';
		echo '<span class="cro-stock-urgency-icon">' . CRO_Icons::svg_kses( 'alert', array( 'class' => 'cro-ico' ) ) . '</span>';
		echo '<span class="cro-stock-urgency-message">' . esc_html( $message ) . '</span>';
		echo '</div>';
		do_action( 'cro_frontend_after_render', 'stock_urgency', $render_context );
	}
}
