<?php
/**
 * Cart optimizer
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cart optimizer class.
 */
class MEYVC_Cart_Optimizer {

	/**
	 * Initialize cart optimizer.
	 */
	public function __construct() {
		add_action( 'woocommerce_before_cart', array( $this, 'add_cart_upsells' ) );
		add_action( 'woocommerce_before_cart', array( $this, 'render_trust_urgency_top' ), 5 );
		add_action( 'woocommerce_cart_collaterals', array( $this, 'add_cart_cross_sells' ), 15 );
		add_filter( 'woocommerce_cart_item_quantity', array( $this, 'optimize_quantity_input' ), 10, 3 );
		add_action( 'woocommerce_after_cart_table', array( $this, 'render_benefits_bottom' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_exit_nudge' ), 25 );
		add_action( 'meyvc_cart_upsells', array( $this, 'render_cart_upsells' ) );
		add_action( 'meyvc_cart_cross_sells', array( $this, 'render_cart_cross_sells' ) );
	}

	/**
	 * Add upsells to cart page.
	 */
	public function add_cart_upsells() {
		// Upsells can be added here
		do_action( 'meyvc_cart_upsells' );
	}

	/**
	 * Add cross-sells to cart page.
	 */
	public function add_cart_cross_sells() {
		// Cross-sells are handled by WooCommerce by default
		do_action( 'meyvc_cart_cross_sells' );
	}

	/**
	 * Optimize quantity input.
	 *
	 * @param string $product_quantity Quantity input HTML.
	 * @param string $cart_item_key    Cart item key.
	 * @param array  $cart_item        Cart item data.
	 * @return string
	 */
	public function optimize_quantity_input( $product_quantity, $cart_item_key, $cart_item ) {
		// Add plus/minus buttons or other optimizations
		return apply_filters( 'meyvc_quantity_input', $product_quantity, $cart_item_key, $cart_item );
	}

	/**
	 * Render trust and urgency at the top of the cart (classic cart only; block cart uses MEYVC_Blocks_Integration).
	 * Respects banner frequency cap (max N times per visitor per 24h).
	 */
	public function render_trust_urgency_top() {
		if ( ! function_exists( 'meyvc_settings' ) || ! meyvc_settings()->is_feature_enabled( 'cart_optimizer' ) ) {
			return;
		}
		$settings = meyvc_settings()->get_cart_optimizer_settings();
		$cap      = meyvc_settings()->get_banner_frequency_settings();
		$max_24h  = (int) ( $cap['max_per_24h'] ?? 0 );
		$visitor  = class_exists( 'MEYVC_Visitor_State' ) ? MEYVC_Visitor_State::get_instance() : null;
		$parts    = array();

		if ( ! empty( $settings['show_trust_under_total'] ) ) {
			$can_show = ! $visitor || $visitor->can_show_banner( 'trust', $max_24h );
			if ( $can_show ) {
				$msg   = isset( $settings['trust_message'] ) && (string) $settings['trust_message'] !== ''
					? (string) $settings['trust_message']
					: __( 'Secure payment - Fast shipping - Easy returns', 'meyvora-convert' );
				$parts[] = '<div class="meyvc-cart-trust meyvc-blocks-trust"><p>' . esc_html( $msg ) . '</p></div>';
				if ( $visitor ) {
					$visitor->record_banner_show( 'trust' );
				}
			}
		}

		if ( ! empty( $settings['show_urgency'] ) ) {
			$can_show = ! $visitor || $visitor->can_show_banner( 'urgency', $max_24h );
			if ( $can_show ) {
				$msg   = isset( $settings['urgency_message'] ) && (string) $settings['urgency_message'] !== ''
					? (string) $settings['urgency_message']
					: __( 'Items in your cart are in high demand!', 'meyvora-convert' );
				$parts[] = '<div class="meyvc-cart-urgency meyvc-blocks-urgency"><p>' . esc_html( $msg ) . '</p></div>';
				if ( $visitor ) {
					$visitor->record_banner_show( 'urgency' );
				}
			}
		}

		if ( empty( $parts ) ) {
			return;
		}
		echo '<div class="meyvc-blocks-cart-prefix meyvc-cart-optimizer-block">' . wp_kses_post( implode( "\n", $parts ) ) . '</div>';
	}

	/**
	 * Render benefits list below the cart table (classic cart only; block cart uses MEYVC_Blocks_Integration).
	 */
	public function render_benefits_bottom() {
		if ( ! function_exists( 'meyvc_settings' ) || ! meyvc_settings()->is_feature_enabled( 'cart_optimizer' ) ) {
			return;
		}
		$settings = meyvc_settings()->get_cart_optimizer_settings();
		if ( empty( $settings['show_benefits'] ) ) {
			return;
		}
		$benefits = (array) ( $settings['benefits_list'] ?? array() );
		$benefits = array_filter( array_map( 'trim', $benefits ) );
		if ( empty( $benefits ) ) {
			return;
		}
		echo '<div class="meyvc-blocks-cart-suffix meyvc-cart-optimizer-block"><ul class="meyvc-cart-benefits meyvc-blocks-benefits"><li>' . implode( '</li><li>', array_map( 'esc_html', $benefits ) ) . '</li></ul></div>';
	}

	/**
	 * Enqueue exit-intent nudge script on cart/checkout when enabled. Once per session, mobile-safe.
	 */
	public function enqueue_exit_nudge() {
		if ( ! function_exists( 'is_cart' ) || ! function_exists( 'is_checkout' ) ) {
			return;
		}
		if ( ! is_cart() && ! is_checkout() ) {
			return;
		}
		if ( ! function_exists( 'meyvc_settings' ) || ! meyvc_settings()->is_feature_enabled( 'cart_optimizer' ) ) {
			return;
		}
		$settings = meyvc_settings()->get_cart_optimizer_settings();
		if ( empty( $settings['exit_intent_nudge'] ) ) {
			return;
		}

		$checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '';
		if ( is_checkout() ) {
			$checkout_url = ''; // On checkout, CTA can scroll to place order or stay as-is; use # to close.
			$checkout_url = apply_filters( 'meyvc_exit_nudge_checkout_cta_url', '#', true );
		} else {
			$checkout_url = apply_filters( 'meyvc_exit_nudge_checkout_cta_url', $checkout_url, false );
		}

		wp_enqueue_script(
			'meyvc-cart-exit-nudge',
			defined( 'MEYVC_PLUGIN_URL' ) ? MEYVC_PLUGIN_URL . 'public/js/meyvc-cart-exit-nudge' . meyvc_asset_min_suffix() . '.js' : '',
			array(),
			defined( 'MEYVC_VERSION' ) ? MEYVC_VERSION : '1.0',
			true
		);

		$message = isset( $settings['exit_intent_message'] ) && (string) $settings['exit_intent_message'] !== ''
			? (string) $settings['exit_intent_message']
			: __( 'Complete your order now — your discount is ready', 'meyvora-convert' );
		$cta     = isset( $settings['exit_intent_cta'] ) && (string) $settings['exit_intent_cta'] !== ''
			? (string) $settings['exit_intent_cta']
			: __( 'Complete order', 'meyvora-convert' );

		wp_localize_script(
			'meyvc-cart-exit-nudge',
			'meyvcCartExitNudgeConfig',
			array(
				'enabled'      => true,
				'message'      => $message,
				'ctaText'      => $cta,
				'checkoutUrl'  => $checkout_url ?: '#',
			)
		);

		wp_register_style( 'meyvc-cart-exit-nudge', false, array(), MEYVC_VERSION );
		wp_enqueue_style( 'meyvc-cart-exit-nudge' );
		$css = '.meyvc-exit-nudge{position:fixed;inset:0;z-index:999999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.4);opacity:0;visibility:hidden;transition:opacity .25s ease,visibility .25s ease}.meyvc-exit-nudge--visible{opacity:1;visibility:visible}.meyvc-exit-nudge__box{position:relative;max-width:360px;margin:1rem;padding:1.5rem;background:#fff;border-radius:8px;box-shadow:0 4px 24px rgba(0,0,0,.15);text-align:center}.meyvc-exit-nudge__message{margin:0 0 1rem;font-size:1.1rem;line-height:1.4;color:#333}.meyvc-exit-nudge__cta{display:inline-block;margin-bottom:.5rem;padding:.6rem 1.2rem;font-size:1rem;text-decoration:none;color:#fff;background:#333;border-radius:4px;border:none;cursor:pointer}.meyvc-exit-nudge__cta:hover{color:#fff;background:#555}.meyvc-exit-nudge__close{position:absolute;top:.5rem;right:.5rem;width:32px;height:32px;padding:0;font-size:1.5rem;line-height:1;color:#666;background:none;border:none;cursor:pointer}.meyvc-exit-nudge__close:hover{color:#333}';
		wp_add_inline_style( 'meyvc-cart-exit-nudge', $css );
	}

	/**
	 * Render upsell products on the cart page.
	 */
	public function render_cart_upsells() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		$settings = function_exists( 'meyvc_settings' )
			? meyvc_settings()->get( 'cart_optimizer', 'upsells', array() )
			: array();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}
		$upsell_ids = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			$product = $item['data'] ?? null;
			if ( $product && method_exists( $product, 'get_upsell_ids' ) ) {
				$upsell_ids = array_merge( $upsell_ids, $product->get_upsell_ids() );
			}
		}
		$upsell_ids = array_unique( array_filter( $upsell_ids ) );
		if ( empty( $upsell_ids ) ) {
			return;
		}
		$limit   = max( 1, (int) ( $settings['limit'] ?? 3 ) );
		$upsells = array_slice( $upsell_ids, 0, $limit );
		$heading = ! empty( $settings['heading'] )
			? sanitize_text_field( $settings['heading'] )
			: __( 'You might also like', 'meyvora-convert' );
		echo '<div class="meyvc-cart-upsells">';
		echo '<h3 class="meyvc-cart-upsells__heading">' . esc_html( $heading ) . '</h3>';
		echo '<ul class="meyvc-cart-upsells__list products">';
		foreach ( $upsells as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product || ! $product->is_visible() || ! $product->is_purchasable() ) {
				continue;
			}
			wc_get_template_part( 'content', 'product' );
		}
		echo '</ul></div>';
	}

	/**
	 * Render cross-sell products on the cart page.
	 */
	public function render_cart_cross_sells() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		$settings = function_exists( 'meyvc_settings' )
			? meyvc_settings()->get( 'cart_optimizer', 'cross_sells', array() )
			: array();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}
		$cross_sell_ids = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			$product = $item['data'] ?? null;
			if ( $product && method_exists( $product, 'get_cross_sell_ids' ) ) {
				$cross_sell_ids = array_merge( $cross_sell_ids, $product->get_cross_sell_ids() );
			}
		}
		$cross_sell_ids = array_unique( array_filter( $cross_sell_ids ) );
		if ( empty( $cross_sell_ids ) ) {
			return;
		}
		$limit       = max( 1, (int) ( $settings['limit'] ?? 3 ) );
		$cross_sells = array_slice( $cross_sell_ids, 0, $limit );
		$heading     = ! empty( $settings['heading'] )
			? sanitize_text_field( $settings['heading'] )
			: __( 'Customers also bought', 'meyvora-convert' );
		echo '<div class="meyvc-cart-cross-sells">';
		echo '<h3 class="meyvc-cart-cross-sells__heading">' . esc_html( $heading ) . '</h3>';
		echo '<ul class="meyvc-cart-cross-sells__list products">';
		foreach ( $cross_sells as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product || ! $product->is_visible() || ! $product->is_purchasable() ) {
				continue;
			}
			wc_get_template_part( 'content', 'product' );
		}
		echo '</ul></div>';
	}
}
