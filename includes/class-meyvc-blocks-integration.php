<?php
/**
 * WooCommerce Blocks (Gutenberg) integration for Cart and Checkout.
 *
 * This class provides:
 * - Fallback HTML injection via render_block (deprecated; prefer slot-based UI from
 *   MEYVC_Blocks_Integration_WC + meyvc-blocks-cart-checkout.js when possible).
 * - Enqueue of CRO styles on cart/checkout so block pages look correct.
 *
 * Scripts and data for blocks are registered via MEYVC_Blocks_Integration_WC
 * (IntegrationInterface) so extension JS loads reliably with getSetting('meyvora-convert_data').
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MEYVC_Blocks_Integration class (legacy fallback).
 */
class MEYVC_Blocks_Integration {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Fallback: inject CRO HTML before/after cart and checkout blocks when slot-based UI is not used.
		add_filter( 'render_block', array( $this, 'inject_block_content' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_block_compat_assets' ), 20 );
	}

	/**
	 * Enqueue styles for block cart/checkout so CRO content looks correct. Only on cart/checkout; respects meyvc_should_enqueue_assets.
	 */
	public function enqueue_block_compat_assets() {
		if ( ! class_exists( 'MEYVC_Public' ) || ! MEYVC_Public::is_woo_relevant_page() ) {
			return;
		}
		if ( ! function_exists( 'meyvc_settings' ) ) {
			return;
		}
		$load_boosters = false;
		if ( function_exists( 'is_cart' ) && is_cart() && MEYVC_Public::should_enqueue_assets( 'cart' ) && meyvc_settings()->is_feature_enabled( 'cart_optimizer' ) ) {
			$load_boosters = true;
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() && MEYVC_Public::should_enqueue_assets( 'checkout' ) && meyvc_settings()->is_feature_enabled( 'checkout_optimizer' ) ) {
			$load_boosters = true;
		}
		if ( $load_boosters ) {
			wp_enqueue_style( 'meyvc-boosters', MEYVC_PLUGIN_URL . 'public/css/meyvc-boosters' . meyvc_asset_min_suffix() . '.css', array(), MEYVC_VERSION );
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() && meyvc_settings()->is_feature_enabled( 'checkout_optimizer' ) ) {
			wp_enqueue_style( 'meyvc-checkout', MEYVC_PLUGIN_URL . 'public/css/meyvc-checkout' . meyvc_asset_min_suffix() . '.css', array(), MEYVC_VERSION );
		}
	}

	/**
	 * Inject CRO content before/after WooCommerce cart or checkout blocks.
	 *
	 * Fallback when CRO UI is not rendered via block slots. Extension JS from
	 * MEYVC_Blocks_Integration_WC can enhance this markup (e.g. coupon toggle).
	 *
	 * @param string $block_content Block HTML.
	 * @param array  $block         Block data.
	 * @return string
	 */
	public function inject_block_content( $block_content, $block ) {
		$block_name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';

		if ( $block_name === 'woocommerce/cart' ) {
			return $this->get_cart_block_prefix() . $block_content . $this->get_cart_block_suffix();
		}

		if ( $block_name === 'woocommerce/checkout' ) {
			return $this->get_checkout_block_prefix() . $block_content . $this->get_checkout_block_suffix();
		}

		return $block_content;
	}

	/**
	 * HTML to prepend to the cart block (messages, trust, urgency above block).
	 *
	 * @return string
	 */
	private function get_cart_block_prefix() {
		if ( ! function_exists( 'meyvc_settings' ) || ! meyvc_settings()->is_feature_enabled( 'cart_optimizer' ) ) {
			return '';
		}

		$settings = meyvc_settings()->get_cart_optimizer_settings();
		$parts    = array();

		// Trust message (top).
		if ( ! empty( $settings['show_trust_under_total'] ) ) {
			$msg = isset( $settings['trust_message'] ) && (string) $settings['trust_message'] !== ''
				? (string) $settings['trust_message']
				: __( 'Secure payment - Fast shipping - Easy returns', 'meyvora-convert' );
			$parts[] = '<div class="meyvc-cart-trust meyvc-blocks-trust"><p>' . esc_html( $msg ) . '</p></div>';
		}

		// Urgency message (top).
		if ( ! empty( $settings['show_urgency'] ) ) {
			$msg = isset( $settings['urgency_message'] ) && (string) $settings['urgency_message'] !== ''
				? (string) $settings['urgency_message']
				: __( 'Items in your cart are in high demand!', 'meyvora-convert' );
			$parts[] = '<div class="meyvc-cart-urgency meyvc-blocks-urgency"><p>' . esc_html( $msg ) . '</p></div>';
		}

		// Free-shipping message (top).
		$parts[] = $this->get_cart_free_shipping_message();

		$html = implode( "\n", array_filter( $parts ) );
		if ( $html === '' ) {
			return '';
		}
		return '<div class="meyvc-blocks-cart-prefix meyvc-cart-optimizer-block">' . $html . '</div>';
	}

	/**
	 * HTML to append to the cart block (benefits only – below cart).
	 *
	 * @return string
	 */
	private function get_cart_block_suffix() {
		if ( ! function_exists( 'meyvc_settings' ) || ! meyvc_settings()->is_feature_enabled( 'cart_optimizer' ) ) {
			return '';
		}

		$settings = meyvc_settings()->get_cart_optimizer_settings();
		$parts    = array();

		// Benefits list (bottom).
		if ( ! empty( $settings['show_benefits'] ) ) {
			$benefits = (array) ( $settings['benefits_list'] ?? array() );
			$benefits = array_filter( array_map( 'trim', $benefits ) );
			if ( ! empty( $benefits ) ) {
				$parts[] = '<ul class="meyvc-cart-benefits meyvc-blocks-benefits"><li>' . implode( '</li><li>', array_map( 'esc_html', $benefits ) ) . '</li></ul>';
			}
		}

		$html = implode( "\n", array_filter( $parts ) );
		if ( $html === '' ) {
			return '';
		}
		return '<div class="meyvc-blocks-cart-suffix meyvc-cart-optimizer-block">' . $html . '</div>';
	}

	/**
	 * Free-shipping progress message for cart (same logic as MEYVC_Cart_Messages).
	 *
	 * @return string
	 */
	private function get_cart_free_shipping_message() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}

		$threshold = (float) apply_filters( 'meyvc_free_shipping_threshold', 0 );
		if ( $threshold <= 0 ) {
			return '';
		}

		$cart_total = (float) WC()->cart->get_total( 'edit' );
		if ( $cart_total >= $threshold ) {
			return '';
		}

		$remaining = $threshold - $cart_total;
		$message   = sprintf(
			/* translators: %s: remaining amount (HTML from wc_price) */
			__( 'Add %s more to get free shipping!', 'meyvora-convert' ),
			wp_kses_post( wc_price( $remaining ) )
		);
		return '<div class="meyvc-cart-message meyvc-free-shipping"><p>' . wp_kses_post( $message ) . '</p></div>';
	}

	/**
	 * Urgency message for cart.
	 *
	 * @return string
	 */
	private function get_cart_urgency_message() {
		$settings = meyvc_settings()->get_cart_optimizer_settings();
		if ( empty( $settings['show_urgency'] ) || empty( $settings['urgency_message'] ) ) {
			return '';
		}
		return '<div class="meyvc-cart-urgency"><p>' . esc_html( (string) $settings['urgency_message'] ) . '</p></div>';
	}

	/**
	 * HTML to prepend to the checkout block (coupon form, trust).
	 *
	 * @return string
	 */
	private function get_checkout_block_prefix() {
		if ( ! function_exists( 'meyvc_settings' ) || ! meyvc_settings()->is_feature_enabled( 'checkout_optimizer' ) ) {
			return '';
		}

		$settings = meyvc_settings()->get_checkout_settings();
		$parts    = array();

		// Coupon form at top (when "move coupon to top" is enabled).
		if ( ! empty( $settings['move_coupon_to_top'] ) && function_exists( 'wc_coupons_enabled' ) && wc_coupons_enabled() ) {
			$parts[] = $this->get_checkout_coupon_html();
		}

		// Trust / secure badge at top.
		if ( ! empty( $settings['show_secure_badge'] ) || ! empty( $settings['show_trust_message'] ) ) {
			$parts[] = $this->get_checkout_trust_html();
		}

		$html = implode( "\n", array_filter( $parts ) );
		if ( $html === '' ) {
			return '';
		}
		return '<div class="meyvc-blocks-checkout-prefix meyvc-checkout-optimizer-block">' . $html . '</div>';
	}

	/**
	 * HTML to append to the checkout block (guarantee, etc.).
	 *
	 * @return string
	 */
	private function get_checkout_block_suffix() {
		if ( ! function_exists( 'meyvc_settings' ) || ! meyvc_settings()->is_feature_enabled( 'checkout_optimizer' ) ) {
			return '';
		}

		$settings = meyvc_settings()->get_checkout_settings();
		if ( empty( $settings['show_guarantee'] ) ) {
			return '';
		}

		$text = $settings['guarantee_text'] ?? __( '30-day money-back guarantee', 'meyvora-convert' );
		if ( empty( $text ) ) {
			return '';
		}
		$icon = wp_kses( MEYVC_Icons::svg( 'check', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() );
		return '<div class="meyvc-blocks-checkout-suffix meyvc-checkout-optimizer-block"><div class="meyvc-guarantee"><span class="meyvc-guarantee-icon">' . $icon . '</span><span class="meyvc-guarantee-text">' . esc_html( (string) $text ) . '</span></div></div>';
	}

	/**
	 * Coupon form HTML for block checkout.
	 *
	 * @return string
	 */
	private function get_checkout_coupon_html() {
		ob_start();
		?>
		<div class="meyvc-coupon-form-wrapper meyvc-blocks-coupon">
			<div class="meyvc-coupon-toggle">
				<a href="#" class="meyvc-coupon-toggle-link"><?php esc_html_e( 'Have a coupon?', 'meyvora-convert' ); ?></a>
			</div>
			<div class="meyvc-coupon-form" style="display: none;">
				<form class="checkout_coupon woocommerce-form-coupon" method="post">
					<p><?php esc_html_e( 'Enter your coupon code below.', 'meyvora-convert' ); ?></p>
					<p class="form-row form-row-first">
						<input type="text" name="coupon_code" class="input-text" placeholder="<?php esc_attr_e( 'Coupon code', 'meyvora-convert' ); ?>" />
					</p>
					<p class="form-row form-row-last">
						<button type="submit" class="button" name="apply_coupon" value="<?php esc_attr_e( 'Apply', 'meyvora-convert' ); ?>"><?php esc_html_e( 'Apply', 'meyvora-convert' ); ?></button>
					</p>
					<div class="clear"></div>
				</form>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Trust/secure badge HTML for block checkout.
	 *
	 * @return string
	 */
	private function get_checkout_trust_html() {
		$settings = meyvc_settings()->get_checkout_settings();
		$out      = '<div class="meyvc-checkout-trust meyvc-blocks-trust">';
		if ( ! empty( $settings['show_secure_badge'] ) ) {
			$icon = wp_kses( MEYVC_Icons::svg( 'lock', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() );
			$out .= '<div class="meyvc-secure-badge"><span class="meyvc-secure-icon">' . $icon . '</span><span class="meyvc-secure-text">' . esc_html__( 'Secure Checkout', 'meyvora-convert' ) . '</span></div>';
		}
		if ( ! empty( $settings['show_trust_message'] ) ) {
			$msg = $settings['trust_message_text'] ?? __( 'Secure checkout - Your data is protected', 'meyvora-convert' );
			$out .= '<div class="meyvc-trust-message">' . esc_html( (string) $msg ) . '</div>';
		}
		$out .= '</div>';
		return $out;
	}
}
