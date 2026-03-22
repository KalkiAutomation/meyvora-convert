<?php
/**
 * Checkout optimizer
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CRO_Checkout_Optimizer class.
 */
class CRO_Checkout_Optimizer {

	/**
	 * Checkout optimizer settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! cro_settings()->is_feature_enabled( 'checkout_optimizer' ) ) {
			return;
		}

		$this->settings = cro_settings()->get_checkout_settings();

		// Field removals.
		add_filter( 'woocommerce_checkout_fields', array( $this, 'modify_checkout_fields' ), 20 );

		// Coupon repositioning.
		if ( ! empty( $this->settings['move_coupon_to_top'] ) ) {
			remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
			add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'render_coupon_form' ), 5 );
		}

		// Trust elements.
		if ( ! empty( $this->settings['show_trust_message'] ) || ! empty( $this->settings['show_secure_badge'] ) ) {
			add_action( 'woocommerce_review_order_before_payment', array( $this, 'render_trust_elements' ), 5 );
		}

		// Guarantee message.
		if ( ! empty( $this->settings['show_guarantee'] ) ) {
			add_action( 'woocommerce_review_order_after_submit', array( $this, 'render_guarantee' ) );
		}

		// Auto-focus.
		if ( ! empty( $this->settings['autofocus_first_field'] ) ) {
			add_action( 'wp_footer', array( $this, 'add_autofocus_script' ) );
		}

		// Inline validation.
		if ( ! empty( $this->settings['inline_validation'] ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_validation_script' ) );
		}
	}

	/**
	 * Modify checkout fields based on settings.
	 *
	 * @param array $fields Checkout fields.
	 * @return array
	 */
	public function modify_checkout_fields( $fields ) {
		// Remove company field.
		if ( ! empty( $this->settings['remove_company_field'] ) ) {
			unset( $fields['billing']['billing_company'] );
			unset( $fields['shipping']['shipping_company'] );
		}

		// Remove address line 2.
		if ( ! empty( $this->settings['remove_address_2'] ) ) {
			unset( $fields['billing']['billing_address_2'] );
			unset( $fields['shipping']['shipping_address_2'] );
		}

		// Remove phone.
		if ( ! empty( $this->settings['remove_phone'] ) ) {
			unset( $fields['billing']['billing_phone'] );
		}

		// Remove order notes.
		if ( ! empty( $this->settings['remove_order_notes'] ) ) {
			unset( $fields['order']['order_comments'] );
		}

		return $fields;
	}

	/**
	 * Render coupon form at top of checkout.
	 */
	public function render_coupon_form() {
		if ( ! function_exists( 'wc_coupons_enabled' ) || ! wc_coupons_enabled() ) {
			return;
		}
		?>
		<div class="cro-coupon-form-wrapper">
			<div class="cro-coupon-toggle">
				<a href="#" class="cro-coupon-toggle-link">
					<?php esc_html_e( 'Have a coupon?', 'meyvora-convert' ); ?>
				</a>
			</div>
			<div class="cro-coupon-form" style="display: none;">
				<form class="checkout_coupon woocommerce-form-coupon" method="post">
					<p><?php esc_html_e( 'Enter your coupon code below.', 'meyvora-convert' ); ?></p>
					<p class="form-row form-row-first">
						<input type="text" name="coupon_code" class="input-text" 
							   placeholder="<?php esc_attr_e( 'Coupon code', 'meyvora-convert' ); ?>" />
					</p>
					<p class="form-row form-row-last">
						<button type="submit" class="button" name="apply_coupon" value="<?php esc_attr_e( 'Apply', 'meyvora-convert' ); ?>">
							<?php esc_html_e( 'Apply', 'meyvora-convert' ); ?>
						</button>
					</p>
					<div class="clear"></div>
				</form>
			</div>
		</div>
		<script>
		jQuery(function($) {
			$('.cro-coupon-toggle-link').on('click', function(e) {
				e.preventDefault();
				$('.cro-coupon-form').slideToggle();
			});
		});
		</script>
		<?php
	}

	/**
	 * Render trust elements before payment section.
	 */
	public function render_trust_elements() {
		$context = array( 'settings' => $this->settings );
		do_action( 'cro_frontend_before_render', 'checkout_trust', $context );
		?>
		<div class="cro-checkout-trust">
			<?php if ( ! empty( $this->settings['show_secure_badge'] ) ) : ?>
			<div class="cro-secure-badge">
				<span class="cro-secure-icon"><?php echo CRO_Icons::svg_kses( 'lock', array( 'class' => 'cro-ico' ) ); ?></span>
				<span class="cro-secure-text"><?php esc_html_e( 'Secure Checkout', 'meyvora-convert' ); ?></span>
			</div>
			<?php endif; ?>

			<?php if ( ! empty( $this->settings['show_trust_message'] ) ) : ?>
			<div class="cro-trust-message">
				<?php echo esc_html( $this->settings['trust_message_text'] ?? __( 'Secure checkout - Your data is protected', 'meyvora-convert' ) ); ?>
			</div>
			<?php endif; ?>
		</div>
		<?php
		do_action( 'cro_frontend_after_render', 'checkout_trust', $context );
	}

	/**
	 * Render guarantee message after submit button.
	 */
	public function render_guarantee() {
		$guarantee_text = $this->settings['guarantee_text'] ?? __( '30-day money-back guarantee', 'meyvora-convert' );
		if ( empty( $guarantee_text ) ) {
			return;
		}
		$context = array( 'guarantee_text' => $guarantee_text );
		do_action( 'cro_frontend_before_render', 'checkout_guarantee', $context );
		?>
		<div class="cro-guarantee">
			<span class="cro-guarantee-icon"><?php echo CRO_Icons::svg_kses( 'check', array( 'class' => 'cro-ico' ) ); ?></span>
			<span class="cro-guarantee-text"><?php echo esc_html( $guarantee_text ); ?></span>
		</div>
		<?php
		do_action( 'cro_frontend_after_render', 'checkout_guarantee', $context );
	}

	/**
	 * Add autofocus script for first empty field.
	 */
	public function add_autofocus_script() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		?>
		<script>
		jQuery(function($) {
			// Find first empty visible input.
			var $fields = $('.woocommerce-checkout input[type="text"], .woocommerce-checkout input[type="email"]').filter(':visible');
			$fields.each(function() {
				if ($(this).val() === '') {
					$(this).focus();
					return false;
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Enqueue inline validation script. Only on checkout; respects cro_should_enqueue_assets filter.
	 */
	public function enqueue_validation_script() {
		if ( ! class_exists( 'CRO_Public' ) || ! CRO_Public::should_enqueue_assets( 'checkout' ) ) {
			return;
		}
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		wp_enqueue_style(
			'cro-checkout',
			CRO_PLUGIN_URL . 'public/css/cro-checkout.css',
			array(),
			CRO_VERSION
		);

		wp_add_inline_script(
			'wc-checkout',
			'
			jQuery(function($) {
				// Add validation classes on blur.
				$(document.body).on("blur", ".woocommerce-checkout input, .woocommerce-checkout select", function() {
					var $field = $(this);
					var $wrapper = $field.closest(".form-row");
					
					if ($field.val() !== "") {
						// Basic validation.
						var isValid = true;
						
						if ($field.attr("type") === "email") {
							isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test($field.val());
						}
						
						if (isValid) {
							$wrapper.removeClass("cro-field-error").addClass("cro-field-valid");
						} else {
							$wrapper.removeClass("cro-field-valid").addClass("cro-field-error");
						}
					} else {
						$wrapper.removeClass("cro-field-valid cro-field-error");
					}
				});
			});
			'
		);
	}
}
