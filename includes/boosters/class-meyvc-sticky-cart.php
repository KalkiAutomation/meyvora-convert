<?php
/**
 * Sticky cart booster
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MEYVC_Sticky_Cart class.
 */
class MEYVC_Sticky_Cart {

	/**
	 * Sticky cart settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings = meyvc_settings()->get_sticky_cart_settings();

		if ( ! meyvc_settings()->is_feature_enabled( 'sticky_cart' ) ) {
			return;
		}

		add_action( 'wp_footer', array( $this, 'render_sticky_bar' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_action( 'wp_ajax_meyvc_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_meyvc_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
	}

	/**
	 * Enqueue sticky cart assets. Only on product pages; respects meyvc_should_enqueue_assets filter.
	 */
	public function enqueue_assets() {
		if ( ! class_exists( 'MEYVC_Public' ) || ! MEYVC_Public::should_enqueue_assets( 'sticky_cart' ) ) {
			return;
		}
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		// Check mobile-only setting.
		if ( ! empty( $this->settings['show_on_mobile_only'] ) && ! wp_is_mobile() ) {
			return;
		}

		wp_enqueue_style(
			'meyvc-sticky-cart',
			MEYVC_PLUGIN_URL . 'public/css/meyvc-sticky-cart' . meyvc_asset_min_suffix() . '.css',
			array(),
			MEYVC_VERSION
		);

		wp_enqueue_script(
			'meyvc-sticky-cart',
			MEYVC_PLUGIN_URL . 'public/js/meyvc-sticky-cart' . meyvc_asset_min_suffix() . '.js',
			array( 'jquery' ),
			MEYVC_VERSION,
			true
		);

		global $product;

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		wp_localize_script(
			'meyvc-sticky-cart',
			'meyvcStickyCart',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'meyvc_add_to_cart' ),
				'settings' => $this->settings,
				'product'  => array(
					'id'        => $product->get_id(),
					'name'      => $product->get_name(),
					'price'     => $product->get_price_html(),
					'image'     => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ),
					'type'      => $product->get_type(),
					'in_stock'  => $product->is_in_stock(),
				),
				'cartUrl'  => wc_get_cart_url(),
				'i18n'     => array(
					'adding'    => __( 'Adding...', 'meyvora-convert' ),
					'added'     => __( 'Added!', 'meyvora-convert' ),
					'view_cart' => __( 'View Cart', 'meyvora-convert' ),
				),
			)
		);

		wp_localize_script(
			'meyvc-sticky-cart',
			'meyvcTrackerData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'meyvc-track-event' ),
			)
		);

		$inline_tracker = "window.meyvcTracker = window.meyvcTracker || {}; meyvcTracker.ajaxUrl = (typeof meyvcTrackerData !== 'undefined' && meyvcTrackerData.ajaxUrl) ? meyvcTrackerData.ajaxUrl : ''; meyvcTracker.nonce = (typeof meyvcTrackerData !== 'undefined' && meyvcTrackerData.nonce) ? meyvcTrackerData.nonce : ''; meyvcTracker.track = function(eventType, data) { if (!meyvcTracker.ajaxUrl || !meyvcTracker.nonce) return; var d = { action: 'meyvc_track_event', nonce: meyvcTracker.nonce, event_type: eventType, campaign_id: 0, source_type: 'sticky_cart', event_data: data || {} }; if (typeof jQuery !== 'undefined') jQuery.post(meyvcTracker.ajaxUrl, d); };";
		wp_add_inline_script( 'meyvc-sticky-cart', $inline_tracker, 'after' );
	}

	/**
	 * AJAX handler: add product to cart.
	 */
	public function ajax_add_to_cart() {
		check_ajax_referer( 'meyvc_add_to_cart', 'nonce' );

		$rl_ip = class_exists( 'MEYVC_Security' ) ? MEYVC_Security::get_client_ip() : '';
		if ( class_exists( 'MEYVC_Security' ) && ! MEYVC_Security::check_rate_limit( 'meyvc_ajax_' . sanitize_key( current_action() ) . '_' . $rl_ip, 20, 60 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please slow down.', 'meyvora-convert' ) ), 429 );
		}
		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$quantity   = isset( $_POST['quantity'] ) ? max( 1, absint( wp_unslash( $_POST['quantity'] ) ) ) : 1;

		if ( ! $product_id || ! function_exists( 'WC' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'meyvora-convert' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			wp_send_json_error( array( 'message' => __( 'Product cannot be added.', 'meyvora-convert' ) ) );
		}

		$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity );

		if ( false === $cart_item_key ) {
			wp_send_json_error( array( 'message' => __( 'Could not add to cart.', 'meyvora-convert' ) ) );
		}

		// Return fragments and cart hash for WooCommerce compatibility.
		$data = array(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core filter name.
			'fragments' => apply_filters( 'woocommerce_add_to_cart_fragments', array() ),
			'cart_hash' => WC()->cart->get_cart_hash(),
		);

		wp_send_json_success( $data );
	}

	/**
	 * Render sticky bar in footer.
	 */
	public function render_sticky_bar() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		if ( ! empty( $this->settings['show_on_mobile_only'] ) && ! wp_is_mobile() ) {
			return;
		}

		global $product;

		if ( ! $product || ! is_a( $product, 'WC_Product' ) || ! $product->is_in_stock() ) {
			return;
		}

		$bg_color   = esc_attr( $this->settings['bg_color'] ?? '#ffffff' );
		$button_bg  = esc_attr( $this->settings['button_bg_color'] ?? '#333333' );
		$button_text_color = esc_attr( $this->settings['button_text_color'] ?? '#ffffff' );
		?>
		<div id="meyvc-sticky-cart" class="meyvc-sticky-cart">
			<div class="meyvc-sticky-cart-inner" style="background-color: <?php echo esc_attr( $bg_color ); ?>;">

				<?php if ( ! empty( $this->settings['show_product_image'] ) ) : ?>
				<div class="meyvc-sticky-cart-image">
					<?php echo wp_kses_post( $product->get_image( 'thumbnail' ) ); ?>
				</div>
				<?php endif; ?>

				<div class="meyvc-sticky-cart-info">
					<?php if ( ! empty( $this->settings['show_product_title'] ) ) : ?>
					<span class="meyvc-sticky-cart-title"><?php echo esc_html( $product->get_name() ); ?></span>
					<?php endif; ?>

					<?php if ( ! empty( $this->settings['show_price'] ) ) : ?>
					<span class="meyvc-sticky-cart-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
					<?php endif; ?>
				</div>

				<div class="meyvc-sticky-cart-action">
					<?php if ( $product->is_type( 'simple' ) ) : ?>
					<?php
					$btn_label = isset( $this->settings['button_text'] ) && (string) $this->settings['button_text'] !== ''
						? (string) $this->settings['button_text']
						: ( class_exists( 'MEYVC_Default_Copy' ) ? MEYVC_Default_Copy::get( 'sticky_cart', isset( $this->settings['tone'] ) ? $this->settings['tone'] : 'neutral', 'button_text' ) : __( 'Add to cart', 'meyvora-convert' ) );
					?>
					<button type="button" class="meyvc-sticky-cart-button"
							data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
							style="background-color: <?php echo esc_attr( $button_bg ); ?>; color: <?php echo esc_attr( $button_text_color ); ?>;">
						<?php echo esc_html( $btn_label ); ?>
					</button>
					<?php else : ?>
					<a href="#product-<?php echo esc_attr( $product->get_id() ); ?>" class="meyvc-sticky-cart-button meyvc-scroll-to-options"
						style="background-color: <?php echo esc_attr( $button_bg ); ?>; color: <?php echo esc_attr( $button_text_color ); ?>;">
						<?php esc_html_e( 'Select Options', 'meyvora-convert' ); ?>
					</a>
					<?php endif; ?>
				</div>

			</div>
		</div>
		<?php
	}
}
