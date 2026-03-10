<?php
/**
 * Frontend offer banner for classic cart/checkout: "You qualify for X off — Apply coupon".
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CRO_Offer_Banner class.
 */
class CRO_Offer_Banner {

	/**
	 * Constructor: hooks and AJAX.
	 */
	public function __construct() {
		add_action( 'woocommerce_before_cart', array( $this, 'maybe_render_cart_banner' ), 8 );
		add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'maybe_render_checkout_banner' ), 8 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 25 );
		add_action( 'wp_ajax_cro_apply_offer_coupon', array( $this, 'ajax_apply_offer_coupon' ) );
		add_action( 'wp_ajax_nopriv_cro_apply_offer_coupon', array( $this, 'ajax_apply_offer_coupon' ) );
	}

	/**
	 * Get offer banner settings.
	 *
	 * @return array
	 */
	private function get_settings() {
		if ( ! function_exists( 'cro_settings' ) ) {
			return array( 'enable_offer_banner' => false, 'banner_position' => 'cart' );
		}
		return cro_settings()->get_offer_banner_settings();
	}

	/**
	 * Whether to show banner on cart (classic template).
	 *
	 * @return bool
	 */
	private function show_on_cart() {
		$s = $this->get_settings();
		if ( empty( $s['enable_offer_banner'] ) ) {
			return false;
		}
		$pos = isset( $s['banner_position'] ) ? $s['banner_position'] : 'cart';
		return in_array( $pos, array( 'cart', 'both' ), true );
	}

	/**
	 * Whether to show banner on checkout (classic template).
	 *
	 * @return bool
	 */
	private function show_on_checkout() {
		$s = $this->get_settings();
		if ( empty( $s['enable_offer_banner'] ) ) {
			return false;
		}
		$pos = isset( $s['banner_position'] ) ? $s['banner_position'] : 'cart';
		return in_array( $pos, array( 'checkout', 'both' ), true );
	}

	/**
	 * Render banner markup: headline + description + Apply coupon button (same offer engine + coupon as blocks).
	 *
	 * @param string $context 'cart' or 'checkout'.
	 */
	private function render_banner_markup( $context ) {
		if ( ! class_exists( 'CRO_Offer_Engine' ) ) {
			return;
		}
		$result = CRO_Offer_Engine::get_best_offer_with_coupon( null );
		$offer  = isset( $result['offer'] ) ? $result['offer'] : null;
		$code   = isset( $result['coupon_code'] ) ? $result['coupon_code'] : null;
		if ( ! $offer || ! is_array( $offer ) || ! $offer['headline'] ) {
			return;
		}
		$headline    = $offer['headline'];
		$description = isset( $offer['description'] ) ? (string) $offer['description'] : '';
		$can_apply   = ! empty( $code );
		if ( $can_apply && function_exists( 'WC' ) && WC()->cart ) {
			$applied = WC()->cart->get_applied_coupons();
			if ( in_array( wc_format_coupon_code( $code ), $applied, true ) ) {
				$can_apply = false;
			}
		}
		$nonce = wp_create_nonce( 'cro_apply_offer_coupon' );
		$url   = admin_url( 'admin-ajax.php' );
		$render_context = array( 'context' => $context, 'offer' => $offer, 'coupon_code' => $code );
		do_action( 'cro_frontend_before_render', 'offer_banner', $render_context );
		?>
		<div class="cro-offer-banner" data-context="<?php echo esc_attr( $context ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-ajax-url="<?php echo esc_attr( $url ); ?>"<?php echo $can_apply && $code ? ' data-coupon-code="' . esc_attr( $code ) . '"' : ''; ?>>
			<p class="cro-offer-banner__text cro-offer-banner__headline"><?php echo esc_html( $headline ); ?></p>
			<?php if ( $description !== '' ) : ?>
				<p class="cro-offer-banner__description"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
			<?php if ( $can_apply ) : ?>
			<p class="cro-offer-banner__actions">
				<button type="button" class="button cro-offer-apply-coupon"><?php esc_html_e( 'Apply coupon', 'meyvora-convert' ); ?></button>
			</p>
			<?php else : ?>
			<p class="cro-offer-banner__applied"><?php esc_html_e( 'Discount applied.', 'meyvora-convert' ); ?></p>
			<?php endif; ?>
			<p class="cro-offer-banner__msg cro-offer-banner__msg--success" style="display:none;"></p>
			<p class="cro-offer-banner__msg cro-offer-banner__msg--error" style="display:none;"></p>
		</div>
		<?php
		do_action( 'cro_frontend_after_render', 'offer_banner', $render_context );
	}

	/**
	 * Hook: woocommerce_before_cart. Respects banner frequency cap.
	 */
	public function maybe_render_cart_banner() {
		if ( ! $this->show_on_cart() ) {
			return;
		}
		$cap    = function_exists( 'cro_settings' ) ? cro_settings()->get_banner_frequency_settings() : array();
		$max_24h = (int) ( $cap['max_per_24h'] ?? 0 );
		if ( $max_24h > 0 && class_exists( 'CRO_Visitor_State' ) ) {
			$visitor = CRO_Visitor_State::get_instance();
			if ( ! $visitor->can_show_banner( 'offer', $max_24h ) ) {
				return;
			}
		}
		$this->render_banner_markup( 'cart' );
		if ( $max_24h > 0 && class_exists( 'CRO_Visitor_State' ) ) {
			CRO_Visitor_State::get_instance()->record_banner_show( 'offer' );
		}
	}

	/**
	 * Hook: woocommerce_checkout_before_customer_details. Respects banner frequency cap.
	 */
	public function maybe_render_checkout_banner() {
		if ( ! $this->show_on_checkout() ) {
			return;
		}
		$cap     = function_exists( 'cro_settings' ) ? cro_settings()->get_banner_frequency_settings() : array();
		$max_24h = (int) ( $cap['max_per_24h'] ?? 0 );
		if ( $max_24h > 0 && class_exists( 'CRO_Visitor_State' ) ) {
			$visitor = CRO_Visitor_State::get_instance();
			if ( ! $visitor->can_show_banner( 'offer', $max_24h ) ) {
				return;
			}
		}
		$this->render_banner_markup( 'checkout' );
		if ( $max_24h > 0 && class_exists( 'CRO_Visitor_State' ) ) {
			CRO_Visitor_State::get_instance()->record_banner_show( 'offer' );
		}
	}

	/**
	 * Enqueue script (and optional styles) on cart/checkout.
	 */
	public function enqueue_assets() {
		if ( ! function_exists( 'is_cart' ) || ! function_exists( 'is_checkout' ) ) {
			return;
		}
		if ( ! is_cart() && ! is_checkout() ) {
			return;
		}
		$s = $this->get_settings();
		if ( empty( $s['enable_offer_banner'] ) ) {
			return;
		}
		wp_enqueue_script(
			'cro-offer-banner',
			CRO_PLUGIN_URL . 'public/js/cro-offer-banner.js',
			array( 'jquery' ),
			CRO_VERSION,
			true
		);
		// Styles: cro-classic-cart-checkout.css is enqueued by CRO_Classic_Cart_Checkout when offer/cart/checkout feature is on.
	}

	/**
	 * AJAX: apply offer coupon. Accepts optional coupon_code (validates meta); else get/create from best offer. Returns fragments.
	 */
	public function ajax_apply_offer_coupon() {
		check_ajax_referer( 'cro_apply_offer_coupon', 'nonce' );

		if ( class_exists( 'CRO_Security' ) && ! CRO_Security::check_rate_limit( 'cro_ajax_' . sanitize_key( current_action() ), 20, 60 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please slow down.', 'meyvora-convert' ) ), 429 );
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'Cart unavailable.', 'meyvora-convert' ) ) );
		}

		$code = isset( $_POST['coupon_code'] ) && is_string( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';
		$code = $code !== '' ? wc_format_coupon_code( $code ) : '';

		if ( $code !== '' ) {
			// Validate coupon belongs to visitor/user (same as REST).
			$coupon_id = function_exists( 'wc_get_coupon_id_by_code' ) ? wc_get_coupon_id_by_code( $code ) : 0;
			if ( ! $coupon_id ) {
				wp_send_json_error( array( 'message' => __( 'This coupon is invalid or has expired.', 'meyvora-convert' ) ) );
			}
			$meta_visitor = get_post_meta( $coupon_id, '_cro_visitor_id', true );
			$meta_user    = (int) get_post_meta( $coupon_id, '_cro_user_id', true );
			$visitor_id   = class_exists( 'CRO_Visitor_State' ) ? (string) CRO_Visitor_State::get_instance()->get_visitor_id() : '';
			$user_id      = get_current_user_id();
			$allowed      = false;
			if ( $user_id > 0 ) {
				$allowed = ( (int) $meta_user === $user_id ) || ( (string) $meta_visitor === $visitor_id && $visitor_id !== '' );
			} else {
				$allowed = (string) $meta_visitor === $visitor_id && $visitor_id !== '';
			}
			if ( ! $allowed ) {
				wp_send_json_error( array( 'message' => __( 'This coupon is not assigned to you.', 'meyvora-convert' ) ) );
			}
		} else {
			// No code provided: get or create from best offer (same engine as blocks).
			$code = class_exists( 'CRO_Offer_Engine' ) ? CRO_Offer_Engine::get_or_create_coupon_for_best_offer() : null;
			if ( empty( $code ) ) {
				wp_send_json_error( array( 'message' => __( 'No offer available or rate limit reached.', 'meyvora-convert' ) ) );
			}
			$code = wc_format_coupon_code( $code );
		}

		$applied = WC()->cart->get_applied_coupons();
		if ( in_array( $code, $applied, true ) ) {
			$this->send_fragments_response( __( 'Coupon already applied.', 'meyvora-convert' ), true );
		}

		$result = WC()->cart->apply_coupon( $code );
		if ( is_wp_error( $result ) ) {
			/** @var \WP_Error $result */
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$this->send_fragments_response( __( 'Coupon applied.', 'meyvora-convert' ), true );
	}

	/**
	 * Send JSON success with cart fragments and cart_hash for frontend refresh.
	 *
	 * @param string $message Success message.
	 * @param bool   $success Success flag.
	 */
	private function send_fragments_response( $message, $success = true ) {
		$fragments = array();
		$cart_hash = '';
		if ( function_exists( 'WC' ) && WC()->cart ) {
			ob_start();
			woocommerce_mini_cart();
			$mini_cart = ob_get_clean();
			$fragments = apply_filters(
				'woocommerce_add_to_cart_fragments',
				array(
					'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
				)
			);
			$cart_hash = WC()->cart->get_cart_hash();
		}
		wp_send_json_success( array(
			'message'   => $message,
			'fragments' => $fragments,
			'cart_hash' => $cart_hash,
		) );
	}
}
