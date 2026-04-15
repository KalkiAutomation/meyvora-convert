<?php
/**
 * Sticky cart booster
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MEYVC_Sticky_Cart {

	private $settings;

	public function __construct() {
		$this->settings = meyvc_settings()->get_sticky_cart_settings();

		if ( ! meyvc_settings()->is_feature_enabled( 'sticky_cart' ) ) {
			return;
		}

		add_action( 'wp_footer', array( $this, 'render_sticky_bar' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_localize_late' ), 99 );
		add_action( 'wp_ajax_meyvc_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_meyvc_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
	}

	private function get_current_product() {
		global $product;
		if ( $product && is_a( $product, 'WC_Product' ) ) {
			return $product;
		}
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		// Singular product: most reliable on FSE / early hooks (before $product global is set).
		if ( function_exists( 'is_product' ) && is_product() ) {
			$qid = (int) get_queried_object_id();
			if ( $qid ) {
				$maybe = wc_get_product( $qid );
				if ( $maybe && $maybe instanceof WC_Product ) {
					return $maybe;
				}
			}
		}
		$obj = get_queried_object();
		if ( $obj instanceof WP_Post ) {
			$maybe = wc_get_product( $obj->ID );
			if ( $maybe && $maybe instanceof WC_Product ) {
				return $maybe;
			}
		}
		global $post;
		if ( $post instanceof WP_Post ) {
			$maybe = wc_get_product( $post->ID );
			if ( $maybe && $maybe instanceof WC_Product ) {
				return $maybe;
			}
		}
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( $request_uri ) {
			$path_parsed  = wp_parse_url( $request_uri, PHP_URL_PATH );
			$path         = trim( (string) ( false !== $path_parsed ? $path_parsed : '' ), '/' );
			$segments     = explode( '/', $path );
			$last_segment = end( $segments );
			if ( $last_segment ) {
				$product_post = get_page_by_path( $last_segment, OBJECT, 'product' );
				if ( $product_post ) {
					$maybe = wc_get_product( $product_post->ID );
					if ( $maybe && $maybe instanceof WC_Product ) {
						return $maybe;
					}
				}
			}
		}
		return null;
	}

	private function is_product_page(): bool {
		if ( function_exists( 'is_product' ) && is_product() ) {
			return true;
		}
		$obj = get_queried_object();
		if ( $obj instanceof WP_Post && in_array( $obj->post_type, array( 'product', 'product_variation' ), true ) ) {
			return true;
		}
		global $post;
		if ( $post instanceof WP_Post && in_array( $post->post_type, array( 'product', 'product_variation' ), true ) ) {
			return true;
		}
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( $request_uri ) {
			$path_parsed  = wp_parse_url( $request_uri, PHP_URL_PATH );
			$path         = trim( (string) ( false !== $path_parsed ? $path_parsed : '' ), '/' );
			$segments     = explode( '/', $path );
			$last_segment = end( $segments );
			if ( $last_segment ) {
				$product_post = get_page_by_path( $last_segment, OBJECT, 'product' );
				if ( $product_post instanceof WP_Post ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Markup for variable product attribute dropdowns (IDs/names prefixed so they do not clash with the main add-to-cart form).
	 *
	 * @param WC_Product_Variable $product Variable product.
	 * @return void
	 */
	private function render_variable_attribute_selects( WC_Product_Variable $product ) {
		$attributes = $product->get_variation_attributes();
		if ( empty( $attributes ) ) {
			return;
		}
		echo '<div class="meyvc-sticky-variation-selects">';
		foreach ( $attributes as $attribute_name => $options ) {
			$label = wc_attribute_label( $attribute_name );
			ob_start();
			wc_dropdown_variation_attribute_options(
				array(
					'options'          => $options,
					'attribute'        => $attribute_name,
					'product'          => $product,
					'class'            => 'meyvc-sticky-cart-attr',
					'show_option_none' => sprintf(
						/* translators: %s: product attribute label (e.g. Color, Size). */
						__( 'Choose %s', 'meyvora-convert' ),
						$label
					),
				)
			);
			$html = ob_get_clean();
			// Unique id/name so we do not duplicate the main single-product variation selects.
			$html = preg_replace( '/\bid="([^"]*)"/', 'id="meyvc-sticky-$1"', $html, 1 );
			$html = preg_replace( '/\bname="([^"]*)"/', 'name="meyvc-sticky-$1"', $html, 1 );
			echo '<div class="meyvc-sticky-variation-row">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce outputs escaped dropdown.
		}
		echo '</div>';
	}

	public function enqueue_assets() {
		if ( ! class_exists( 'MEYVC_Public' ) || ! MEYVC_Public::should_enqueue_assets( 'sticky_cart' ) ) {
			return;
		}
		if ( ! $this->is_product_page() ) {
			return;
		}
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

		$product = $this->get_current_product();
		if ( ! $product ) {
			return;
		}
		$this->do_localize( $product );
	}

	public function maybe_localize_late() {
		if ( ! wp_script_is( 'meyvc-sticky-cart', 'enqueued' ) ) {
			return;
		}
		global $wp_scripts;
		$handle = 'meyvc-sticky-cart';
		if ( isset( $wp_scripts->registered[ $handle ] ) ) {
			$data = $wp_scripts->get_data( $handle, 'data' );
			if ( $data && strpos( (string) $data, 'meyvcStickyCart' ) !== false ) {
				return;
			}
		}
		$product = $this->get_current_product();
		if ( ! $product ) {
			return;
		}
		$this->do_localize( $product );
	}

	private function do_localize( WC_Product $product ) {
		$variations = array();
		if ( $product->is_type( 'variable' ) && $product instanceof WC_Product_Variable ) {
			$variations = $product->get_available_variations();
		}
		wp_localize_script(
			'meyvc-sticky-cart',
			'meyvcStickyCart',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'meyvc_add_to_cart' ),
				'settings'   => $this->settings,
				'product'    => array(
					'id'       => $product->get_id(),
					'name'     => $product->get_name(),
					'price'    => $product->get_price_html(),
					'image'    => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ),
					'type'     => $product->get_type(),
					'in_stock' => $product->is_in_stock(),
				),
				'variations' => $variations,
				'cartUrl'    => wc_get_cart_url(),
				'i18n'       => array(
					'adding'            => __( 'Adding...', 'meyvora-convert' ),
					'added'             => __( 'Added!', 'meyvora-convert' ),
					'view_cart'         => __( 'View Cart', 'meyvora-convert' ),
					'choose_variation'  => __( 'Please select all options.', 'meyvora-convert' ),
				),
			)
		);
		wp_localize_script( 'meyvc-sticky-cart', 'meyvcTrackerData', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'meyvc-track-event' ),
		) );
		$inline_tracker = "window.meyvcTracker = window.meyvcTracker || {}; meyvcTracker.ajaxUrl = (typeof meyvcTrackerData !== 'undefined' && meyvcTrackerData.ajaxUrl) ? meyvcTrackerData.ajaxUrl : ''; meyvcTracker.nonce = (typeof meyvcTrackerData !== 'undefined' && meyvcTrackerData.nonce) ? meyvcTrackerData.nonce : ''; meyvcTracker.track = function(eventType, data) { if (!meyvcTracker.ajaxUrl || !meyvcTracker.nonce) return; var d = { action: 'meyvc_track_event', nonce: meyvcTracker.nonce, event_type: eventType, campaign_id: 0, source_type: 'sticky_cart', event_data: data || {} }; if (typeof jQuery !== 'undefined') jQuery.post(meyvcTracker.ajaxUrl, d); };";
		wp_add_inline_script( 'meyvc-sticky-cart', $inline_tracker, 'after' );
	}

	public function ajax_add_to_cart() {
		check_ajax_referer( 'meyvc_add_to_cart', 'nonce' );
		$rl_ip = class_exists( 'MEYVC_Security' ) ? MEYVC_Security::get_client_ip() : '';
		if ( class_exists( 'MEYVC_Security' ) && ! MEYVC_Security::check_rate_limit( 'meyvc_ajax_' . sanitize_key( current_action() ) . '_' . $rl_ip, 20, 60 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please slow down.', 'meyvora-convert' ) ), 429 );
		}
		$product_id    = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$variation_id  = isset( $_POST['variation_id'] ) ? absint( wp_unslash( $_POST['variation_id'] ) ) : 0;
		$quantity      = isset( $_POST['quantity'] ) ? max( 1, absint( wp_unslash( $_POST['quantity'] ) ) ) : 1;
		if ( ! $product_id || ! function_exists( 'WC' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'meyvora-convert' ) ) );
		}
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			wp_send_json_error( array( 'message' => __( 'Product cannot be added.', 'meyvora-convert' ) ) );
		}

		$variation_data = array();
		foreach ( $_POST as $raw_key => $value ) {
			$key = is_string( $raw_key ) ? sanitize_text_field( wp_unslash( $raw_key ) ) : '';
			if ( $key === '' || strpos( $key, 'attribute_' ) !== 0 ) {
				continue;
			}
			$variation_data[ $key ] = wc_clean( wp_unslash( $value ) );
		}

		if ( $product->is_type( 'variable' ) ) {
			if ( ! $variation_id ) {
				wp_send_json_error( array( 'message' => __( 'Please choose a variation.', 'meyvora-convert' ) ) );
			}
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid variation.', 'meyvora-convert' ) ) );
			}
			if ( (int) $variation->get_parent_id() !== (int) $product_id ) {
				wp_send_json_error( array( 'message' => __( 'Invalid variation.', 'meyvora-convert' ) ) );
			}
			if ( ! $variation->is_purchasable() || ! $variation->is_in_stock() ) {
				wp_send_json_error( array( 'message' => __( 'This variation cannot be purchased.', 'meyvora-convert' ) ) );
			}
			$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation_data );
		} else {
			$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity );
		}

		if ( false === $cart_item_key ) {
			wp_send_json_error( array( 'message' => __( 'Could not add to cart.', 'meyvora-convert' ) ) );
		}
		$data = array(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			'fragments' => apply_filters( 'woocommerce_add_to_cart_fragments', array() ),
			'cart_hash' => WC()->cart->get_cart_hash(),
		);
		wp_send_json_success( $data );
	}

	public function render_sticky_bar() {
		if ( ! $this->is_product_page() ) {
			return;
		}
		if ( ! empty( $this->settings['show_on_mobile_only'] ) && ! wp_is_mobile() ) {
			return;
		}
		$product = $this->get_current_product();
		if ( ! $product || ! $product->is_in_stock() ) {
			return;
		}
		$bg_color          = esc_attr( $this->settings['bg_color'] ?? '#ffffff' );
		$button_bg         = esc_attr( $this->settings['button_bg_color'] ?? '#333333' );
		$button_text_color = esc_attr( $this->settings['button_text_color'] ?? '#ffffff' );
		?>
		<div id="meyvc-sticky-cart" class="meyvc-sticky-cart"
			data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'meyvc_add_to_cart' ) ); ?>"
			data-product-id="<?php echo esc_attr( (string) $product->get_id() ); ?>">
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
					<?php
					$btn_label = isset( $this->settings['button_text'] ) && (string) $this->settings['button_text'] !== ''
						? (string) $this->settings['button_text']
						: ( class_exists( 'MEYVC_Default_Copy' ) ? MEYVC_Default_Copy::get( 'sticky_cart', $this->settings['tone'] ?? 'neutral', 'button_text' ) : __( 'Add to cart', 'meyvora-convert' ) );
					?>
					<?php if ( $product->is_type( 'simple' ) ) : ?>
					<button type="button" class="meyvc-sticky-cart-button"
						data-product-id="<?php echo esc_attr( (string) $product->get_id() ); ?>"
						style="background-color: <?php echo esc_attr( $button_bg ); ?>; color: <?php echo esc_attr( $button_text_color ); ?>;">
						<?php echo esc_html( $btn_label ); ?>
					</button>
					<?php elseif ( $product->is_type( 'variable' ) && $product instanceof WC_Product_Variable ) : ?>
					<div class="meyvc-sticky-cart-variable-wrap">
						<?php $this->render_variable_attribute_selects( $product ); ?>
						<button type="button" class="meyvc-sticky-cart-button meyvc-sticky-cart-button--variable"
							disabled
							data-product-id="<?php echo esc_attr( (string) $product->get_id() ); ?>"
							data-variation-id=""
							style="background-color: <?php echo esc_attr( $button_bg ); ?>; color: <?php echo esc_attr( $button_text_color ); ?>;">
							<?php echo esc_html( $btn_label ); ?>
						</button>
					</div>
					<?php else : ?>
					<a href="#product-<?php echo esc_attr( (string) $product->get_id() ); ?>"
						class="meyvc-sticky-cart-button meyvc-scroll-to-options"
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
