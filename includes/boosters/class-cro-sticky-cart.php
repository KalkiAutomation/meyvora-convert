<?php
/**
 * Sticky cart booster
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CRO_Sticky_Cart class.
 */
class CRO_Sticky_Cart {

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
		$this->settings = cro_settings()->get_sticky_cart_settings();

		if ( ! cro_settings()->is_feature_enabled( 'sticky_cart' ) ) {
			return;
		}

		add_action( 'wp_footer', array( $this, 'render_sticky_bar' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_action( 'wp_ajax_cro_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_cro_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
	}

	/**
	 * Enqueue sticky cart assets. Only on product pages; respects cro_should_enqueue_assets filter.
	 */
	public function enqueue_assets() {
		if ( ! class_exists( 'CRO_Public' ) || ! CRO_Public::should_enqueue_assets( 'sticky_cart' ) ) {
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
			'cro-sticky-cart',
			CRO_PLUGIN_URL . 'public/css/cro-sticky-cart' . cro_asset_min_suffix() . '.css',
			array(),
			CRO_VERSION
		);

		wp_enqueue_script(
			'cro-sticky-cart',
			CRO_PLUGIN_URL . 'public/js/cro-sticky-cart' . cro_asset_min_suffix() . '.js',
			array( 'jquery' ),
			CRO_VERSION,
			true
		);

		global $product;

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		wp_localize_script(
			'cro-sticky-cart',
			'croStickyCart',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'cro_add_to_cart' ),
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
			'cro-sticky-cart',
			'croTrackerData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cro-track-event' ),
			)
		);

		$inline_tracker = "window.croTracker = window.croTracker || {}; croTracker.ajaxUrl = (typeof croTrackerData !== 'undefined' && croTrackerData.ajaxUrl) ? croTrackerData.ajaxUrl : ''; croTracker.nonce = (typeof croTrackerData !== 'undefined' && croTrackerData.nonce) ? croTrackerData.nonce : ''; croTracker.track = function(eventType, data) { if (!croTracker.ajaxUrl || !croTracker.nonce) return; var d = { action: 'cro_track_event', nonce: croTracker.nonce, event_type: eventType, campaign_id: 0, source_type: 'sticky_cart', event_data: data || {} }; if (typeof jQuery !== 'undefined') jQuery.post(croTracker.ajaxUrl, d); };";
		wp_add_inline_script( 'cro-sticky-cart', $inline_tracker, 'after' );
	}

	/**
	 * AJAX handler: add product to cart.
	 */
	public function ajax_add_to_cart() {
		check_ajax_referer( 'cro_add_to_cart', 'nonce' );

		$rl_ip = class_exists( 'CRO_Security' ) ? CRO_Security::get_client_ip() : '';
		if ( class_exists( 'CRO_Security' ) && ! CRO_Security::check_rate_limit( 'cro_ajax_' . sanitize_key( current_action() ) . '_' . $rl_ip, 20, 60 ) ) {
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
		<div id="cro-sticky-cart" class="cro-sticky-cart">
			<div class="cro-sticky-cart-inner" style="background-color: <?php echo esc_attr( $bg_color ); ?>;">

				<?php if ( ! empty( $this->settings['show_product_image'] ) ) : ?>
				<div class="cro-sticky-cart-image">
					<?php echo wp_kses_post( $product->get_image( 'thumbnail' ) ); ?>
				</div>
				<?php endif; ?>

				<div class="cro-sticky-cart-info">
					<?php if ( ! empty( $this->settings['show_product_title'] ) ) : ?>
					<span class="cro-sticky-cart-title"><?php echo esc_html( $product->get_name() ); ?></span>
					<?php endif; ?>

					<?php if ( ! empty( $this->settings['show_price'] ) ) : ?>
					<span class="cro-sticky-cart-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
					<?php endif; ?>
				</div>

				<div class="cro-sticky-cart-action">
					<?php if ( $product->is_type( 'simple' ) ) : ?>
					<?php
					$btn_label = isset( $this->settings['button_text'] ) && (string) $this->settings['button_text'] !== ''
						? (string) $this->settings['button_text']
						: ( class_exists( 'CRO_Default_Copy' ) ? CRO_Default_Copy::get( 'sticky_cart', isset( $this->settings['tone'] ) ? $this->settings['tone'] : 'neutral', 'button_text' ) : __( 'Add to cart', 'meyvora-convert' ) );
					?>
					<button type="button" class="cro-sticky-cart-button"
							data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
							style="background-color: <?php echo esc_attr( $button_bg ); ?>; color: <?php echo esc_attr( $button_text_color ); ?>;">
						<?php echo esc_html( $btn_label ); ?>
					</button>
					<?php else : ?>
					<a href="#product-<?php echo esc_attr( $product->get_id() ); ?>" class="cro-sticky-cart-button cro-scroll-to-options"
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
