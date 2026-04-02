<?php
/**
 * Safe guest email capture for abandoned cart reminders.
 *
 * Option A (checkout): "Email me a reminder if I don't finish checkout" checkbox – stores billing email when checked.
 * Option B (cart): Optional email field + "Send me a reminder" consent checkbox.
 * No silent capture; consent is stored (email_consent). Respects enable_abandoned_cart_emails and require_opt_in.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MEYVC_Abandoned_Cart_Email_Capture
 */
class MEYVC_Abandoned_Cart_Email_Capture {

	/**
	 * Register hooks for checkout (Option A) and cart (Option B).
	 */
	public function __construct() {
		add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'render_checkout_reminder_checkbox' ), 10, 1 );
		add_action( 'woocommerce_before_cart_collaterals', array( $this, 'render_cart_email_capture' ), 15 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 20 );
	}

	/**
	 * Whether abandoned cart email capture is enabled and we should show UI.
	 *
	 * @return bool
	 */
	private function is_enabled() {
		if ( ! function_exists( 'meyvc_settings' ) ) {
			return false;
		}
		$opts = meyvc_settings()->get_abandoned_cart_settings();
		return ! empty( $opts['enable_abandoned_cart_emails'] );
	}

	/**
	 * Option A: Render "Email me a reminder if I don't finish checkout" checkbox (guests only, after billing form).
	 *
	 * @param WC_Checkout $checkout Checkout instance.
	 */
	public function render_checkout_reminder_checkbox( $checkout ) {
		if ( ! $this->is_enabled() || is_user_logged_in() ) {
			return;
		}
		woocommerce_form_field(
			'meyvc_abandoned_cart_reminder',
			array(
				'type'     => 'checkbox',
				'class'    => array( 'form-row-wide', 'meyvc-abandoned-cart-reminder' ),
				'label'    => __( 'Email me a reminder if I don\'t finish checkout', 'meyvora-convert' ),
				'default'  => 0,
			),
			$checkout->get_value( 'meyvc_abandoned_cart_reminder' )
		);
		echo '<div class="meyvc-abandoned-cart-checkout-notice" data-meyvc-reminder-checkbox-wrap style="margin-bottom: 1em;"></div>';
	}

	/**
	 * Option B: Render email + consent on cart page (when enabled and cart has items).
	 */
	public function render_cart_email_capture() {
		if ( ! $this->is_enabled() || is_user_logged_in() ) {
			return;
		}
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}
		?>
		<div class="meyvc-abandoned-cart-cart-capture" data-meyvc-cart-email-capture>
			<div class="meyvc-cart-reminder-box">
				<p class="meyvc-cart-reminder-title"><?php esc_html_e( 'Get a reminder?', 'meyvora-convert' ); ?></p>
				<p class="meyvc-cart-reminder-desc"><?php esc_html_e( 'We can send you a quick email reminder if you leave your cart.', 'meyvora-convert' ); ?></p>
				<p class="form-row form-row-wide">
					<label for="meyvc_cart_reminder_email">
						<input type="email" id="meyvc_cart_reminder_email" name="meyvc_cart_reminder_email" class="input-text" placeholder="<?php esc_attr_e( 'Your email', 'meyvora-convert' ); ?>" />
					</label>
				</p>
				<p class="form-row form-row-wide">
					<label class="meyvc-consent-label">
						<input type="checkbox" id="meyvc_cart_reminder_consent" name="meyvc_cart_reminder_consent" value="1" />
						<?php esc_html_e( 'Send me a reminder', 'meyvora-convert' ); ?>
					</label>
				</p>
				<p class="form-row">
					<button type="button" class="button meyvc-cart-reminder-save"><?php esc_html_e( 'Save', 'meyvora-convert' ); ?></button>
				</p>
				<p class="meyvc-cart-reminder-feedback" data-meyvc-feedback style="display: none;"></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue script and styles on cart and checkout when feature enabled.
	 */
	public function enqueue_scripts() {
		if ( ! $this->is_enabled() ) {
			return;
		}
		$is_cart     = function_exists( 'is_cart' ) && is_cart();
		$is_checkout = function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url();
		if ( ! $is_cart && ! $is_checkout ) {
			return;
		}
		wp_enqueue_script(
			'meyvc-abandoned-cart-capture',
			MEYVC_PLUGIN_URL . 'public/js/meyvc-abandoned-cart-capture' . meyvc_asset_min_suffix() . '.js',
			array( 'jquery' ),
			defined( 'MEYVC_VERSION' ) ? MEYVC_VERSION : '1.0.0',
			true
		);
		wp_localize_script(
			'meyvc-abandoned-cart-capture',
			'meyvcAbandonedCartCapture',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'meyvc_abandoned_cart_email' ),
			)
		);
		if ( $is_cart ) {
			wp_add_inline_style( 'woocommerce-general', '.meyvc-abandoned-cart-cart-capture { margin-bottom: 1.5em; } .meyvc-cart-reminder-box { padding: 1em; background: #f8f8f8; border-radius: 4px; } .meyvc-cart-reminder-title { margin-top: 0; font-weight: 600; } .meyvc-cart-reminder-desc { margin-bottom: 0.5em; color: #555; } .meyvc-consent-label { display: inline-block; } .meyvc-cart-reminder-feedback.success { color: #0a0; } .meyvc-cart-reminder-feedback.error { color: #c00; }' );
		}
	}
}
