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
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class CRO_Abandoned_Cart_Email_Capture
 */
class CRO_Abandoned_Cart_Email_Capture {

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
		if ( ! function_exists( 'cro_settings' ) ) {
			return false;
		}
		$opts = cro_settings()->get_abandoned_cart_settings();
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
			'cro_abandoned_cart_reminder',
			array(
				'type'     => 'checkbox',
				'class'    => array( 'form-row-wide', 'cro-abandoned-cart-reminder' ),
				'label'    => __( 'Email me a reminder if I don\'t finish checkout', 'meyvora-convert' ),
				'default'  => 0,
			),
			$checkout->get_value( 'cro_abandoned_cart_reminder' )
		);
		echo '<div class="cro-abandoned-cart-checkout-notice" data-cro-reminder-checkbox-wrap style="margin-bottom: 1em;"></div>';
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
		<div class="cro-abandoned-cart-cart-capture" data-cro-cart-email-capture>
			<div class="cro-cart-reminder-box">
				<p class="cro-cart-reminder-title"><?php esc_html_e( 'Get a reminder?', 'meyvora-convert' ); ?></p>
				<p class="cro-cart-reminder-desc"><?php esc_html_e( 'We can send you a quick email reminder if you leave your cart.', 'meyvora-convert' ); ?></p>
				<p class="form-row form-row-wide">
					<label for="cro_cart_reminder_email">
						<input type="email" id="cro_cart_reminder_email" name="cro_cart_reminder_email" class="input-text" placeholder="<?php esc_attr_e( 'Your email', 'meyvora-convert' ); ?>" />
					</label>
				</p>
				<p class="form-row form-row-wide">
					<label class="cro-consent-label">
						<input type="checkbox" id="cro_cart_reminder_consent" name="cro_cart_reminder_consent" value="1" />
						<?php esc_html_e( 'Send me a reminder', 'meyvora-convert' ); ?>
					</label>
				</p>
				<p class="form-row">
					<button type="button" class="button cro-cart-reminder-save"><?php esc_html_e( 'Save', 'meyvora-convert' ); ?></button>
				</p>
				<p class="cro-cart-reminder-feedback" data-cro-feedback style="display: none;"></p>
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
			'cro-abandoned-cart-capture',
			CRO_PLUGIN_URL . 'public/js/cro-abandoned-cart-capture' . cro_asset_min_suffix() . '.js',
			array( 'jquery' ),
			defined( 'CRO_VERSION' ) ? CRO_VERSION : '1.0.0',
			true
		);
		wp_localize_script(
			'cro-abandoned-cart-capture',
			'croAbandonedCartCapture',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'cro_abandoned_cart_email' ),
			)
		);
		if ( $is_cart ) {
			wp_add_inline_style( 'woocommerce-general', '.cro-abandoned-cart-cart-capture { margin-bottom: 1.5em; } .cro-cart-reminder-box { padding: 1em; background: #f8f8f8; border-radius: 4px; } .cro-cart-reminder-title { margin-top: 0; font-weight: 600; } .cro-cart-reminder-desc { margin-bottom: 0.5em; color: #555; } .cro-consent-label { display: inline-block; } .cro-cart-reminder-feedback.success { color: #0a0; } .cro-cart-reminder-feedback.error { color: #c00; }' );
		}
	}
}
