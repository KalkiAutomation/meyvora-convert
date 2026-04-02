<?php
/**
 * Checkout optimizer
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MEYVC_Checkout_Optimizer class.
 */
class MEYVC_Checkout_Optimizer {

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
		if ( ! meyvc_settings()->is_feature_enabled( 'checkout_optimizer' ) ) {
			return;
		}

		$this->settings = meyvc_settings()->get_checkout_settings();

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

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_optimizer_script' ), 15 );

		// Inline validation.
		if ( ! empty( $this->settings['inline_validation'] ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_validation_script' ) );
		}

		if ( ! empty( $this->settings['prefill_from_last_order'] ) ) {
			add_filter( 'woocommerce_checkout_get_value', array( $this, 'prefill_from_last_order' ), 10, 2 );
		}

		if ( ! empty( $this->settings['show_express_checkout_prompt'] ) ) {
			add_action( 'woocommerce_before_checkout_form', array( $this, 'render_express_prompt' ), 2 );
		}

		if ( ! empty( $this->settings['show_progress_steps'] ) ) {
			add_action( 'woocommerce_before_checkout_form', array( $this, 'render_progress_steps' ), 1 );
		}
	}

	/**
	 * Prefill empty checkout fields from the customer's most recent order.
	 *
	 * @param string     $value Current value.
	 * @param string     $input Field key.
	 * @return string|null
	 */
	public function prefill_from_last_order( $value, $input ) {
		if ( ( $value !== null && $value !== '' ) || ! is_string( $input ) || $input === '' ) {
			return $value;
		}
		$user_id = get_current_user_id();
		if ( ! $user_id || ! function_exists( 'wc_get_orders' ) ) {
			return $value;
		}
		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => 1,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'status'      => array( 'wc-completed', 'wc-processing' ),
			)
		);
		$order = ! empty( $orders[0] ) && is_a( $orders[0], 'WC_Order' ) ? $orders[0] : null;
		if ( ! $order ) {
			return $value;
		}
		$map = array(
			'billing_first_name'   => $order->get_billing_first_name(),
			'billing_last_name'    => $order->get_billing_last_name(),
			'billing_company'      => $order->get_billing_company(),
			'billing_address_1'    => $order->get_billing_address_1(),
			'billing_address_2'    => $order->get_billing_address_2(),
			'billing_city'         => $order->get_billing_city(),
			'billing_state'        => $order->get_billing_state(),
			'billing_postcode'     => $order->get_billing_postcode(),
			'billing_country'      => $order->get_billing_country(),
			'billing_phone'        => $order->get_billing_phone(),
			'billing_email'        => $order->get_billing_email(),
			'shipping_first_name'  => $order->get_shipping_first_name(),
			'shipping_last_name'   => $order->get_shipping_last_name(),
			'shipping_company'     => $order->get_shipping_company(),
			'shipping_address_1'   => $order->get_shipping_address_1(),
			'shipping_address_2'   => $order->get_shipping_address_2(),
			'shipping_city'        => $order->get_shipping_city(),
			'shipping_state'       => $order->get_shipping_state(),
			'shipping_postcode'    => $order->get_shipping_postcode(),
			'shipping_country'     => $order->get_shipping_country(),
		);
		return isset( $map[ $input ] ) && $map[ $input ] !== '' ? $map[ $input ] : $value;
	}

	/**
	 * Lightweight express-wallet prompt (tracks one interaction per session).
	 */
	public function render_express_prompt() {
		$text = isset( $this->settings['express_prompt_text'] ) ? (string) $this->settings['express_prompt_text'] : '';
		if ( $text === '' ) {
			return;
		}
		if ( function_exists( 'WC' ) && WC()->session ) {
			$key = 'meyvc_express_prompt_tracked';
			if ( ! WC()->session->get( $key ) ) {
				WC()->session->set( $key, 1 );
				if ( class_exists( 'MEYVC_Tracker' ) ) {
					$t = new MEYVC_Tracker();
					$page_url = '';
					$req_uri  = filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_UNSAFE_RAW );
					if ( is_string( $req_uri ) && $req_uri !== '' ) {
						$page_url = esc_url_raw( home_url( wp_unslash( $req_uri ) ) );
					}
					$t->track(
						'checkout_express_prompt',
						0,
						array(
							'page_url' => $page_url,
						),
						'trust_badge',
						0
					);
				}
			}
		}
		echo '<div class="meyvc-checkout-express-prompt"><p>' . esc_html( $text ) . '</p></div>';
	}

	/**
	 * Simple 3-step progress indicator (informational).
	 */
	public function render_progress_steps() {
		?>
		<ol class="meyvc-checkout-progress" aria-label="<?php esc_attr_e( 'Checkout progress', 'meyvora-convert' ); ?>">
			<li class="is-active"><span><?php esc_html_e( 'Details', 'meyvora-convert' ); ?></span></li>
			<li><span><?php esc_html_e( 'Shipping & payment', 'meyvora-convert' ); ?></span></li>
			<li><span><?php esc_html_e( 'Confirmation', 'meyvora-convert' ); ?></span></li>
		</ol>
		<?php
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
		<div class="meyvc-coupon-form-wrapper">
			<div class="meyvc-coupon-toggle">
				<a href="#" class="meyvc-coupon-toggle-link">
					<?php esc_html_e( 'Have a coupon?', 'meyvora-convert' ); ?>
				</a>
			</div>
			<div class="meyvc-coupon-form" style="display: none;">
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
		<?php
	}

	/**
	 * Render trust elements before payment section.
	 */
	public function render_trust_elements() {
		$context = array( 'settings' => $this->settings );
		do_action( 'meyvc_frontend_before_render', 'checkout_trust', $context );
		?>
		<div class="meyvc-checkout-trust">
			<?php if ( ! empty( $this->settings['show_secure_badge'] ) ) : ?>
			<div class="meyvc-secure-badge">
				<span class="meyvc-secure-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'lock', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>
				<span class="meyvc-secure-text"><?php esc_html_e( 'Secure Checkout', 'meyvora-convert' ); ?></span>
			</div>
			<?php endif; ?>

			<?php if ( ! empty( $this->settings['show_trust_message'] ) ) : ?>
			<div class="meyvc-trust-message">
				<?php echo esc_html( $this->settings['trust_message_text'] ?? __( 'Secure checkout - Your data is protected', 'meyvora-convert' ) ); ?>
			</div>
			<?php endif; ?>
		</div>
		<?php
		do_action( 'meyvc_frontend_after_render', 'checkout_trust', $context );
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
		do_action( 'meyvc_frontend_before_render', 'checkout_guarantee', $context );
		?>
		<div class="meyvc-guarantee">
			<span class="meyvc-guarantee-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'check', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>
			<span class="meyvc-guarantee-text"><?php echo esc_html( $guarantee_text ); ?></span>
		</div>
		<?php
		do_action( 'meyvc_frontend_after_render', 'checkout_guarantee', $context );
	}

	/**
	 * Enqueue classic checkout helper JS (coupon toggle + optional autofocus). No inline scripts.
	 */
	public function enqueue_checkout_optimizer_script() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		if ( ! class_exists( 'MEYVC_Public' ) || ! MEYVC_Public::should_enqueue_assets( 'checkout' ) ) {
			return;
		}
		$need_coupon = ! empty( $this->settings['move_coupon_to_top'] );
		$need_focus  = ! empty( $this->settings['autofocus_first_field'] );
		if ( ! $need_coupon && ! $need_focus ) {
			return;
		}
		wp_enqueue_script(
			'meyvc-checkout-optimizer',
			MEYVC_PLUGIN_URL . 'public/js/meyvc-checkout-optimizer' . meyvc_asset_min_suffix() . '.js',
			array( 'jquery' ),
			MEYVC_VERSION,
			true
		);
	}

	/**
	 * Enqueue inline validation script. Only on checkout; respects meyvc_should_enqueue_assets filter.
	 */
	public function enqueue_validation_script() {
		if ( ! class_exists( 'MEYVC_Public' ) || ! MEYVC_Public::should_enqueue_assets( 'checkout' ) ) {
			return;
		}
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		wp_enqueue_style(
			'meyvc-checkout',
			MEYVC_PLUGIN_URL . 'public/css/meyvc-checkout' . meyvc_asset_min_suffix() . '.css',
			array(),
			MEYVC_VERSION
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
							$wrapper.removeClass("meyvc-field-error").addClass("meyvc-field-valid");
						} else {
							$wrapper.removeClass("meyvc-field-valid").addClass("meyvc-field-error");
						}
					} else {
						$wrapper.removeClass("meyvc-field-valid meyvc-field-error");
					}
				});
			});
			'
		);
	}
}
