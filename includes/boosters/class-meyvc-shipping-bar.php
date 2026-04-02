<?php
/**
 * Shipping bar booster
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MEYVC_Shipping_Bar class.
 */
class MEYVC_Shipping_Bar {

	/**
	 * Shipping bar settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Free shipping threshold amount (null = not yet computed, defer until first use to avoid calling WC too early).
	 *
	 * @var float|null
	 */
	private $threshold = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings = meyvc_settings()->get_shipping_bar_settings();

		if ( ! meyvc_settings()->is_feature_enabled( 'shipping_bar' ) ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_meyvc_get_cart_total', array( $this, 'ajax_get_cart_total' ) );
		add_action( 'wp_ajax_nopriv_meyvc_get_cart_total', array( $this, 'ajax_get_cart_total' ) );

		$this->add_display_hooks();
	}

	/**
	 * Get threshold (Woo or custom). Computed lazily on first use so WooCommerce is ready (avoids get_continents() on null).
	 *
	 * @return float
	 */
	private function get_threshold() {
		if ( $this->threshold !== null ) {
			return (float) $this->threshold;
		}
		if ( ! empty( $this->settings['use_woo_threshold'] ) ) {
			$this->threshold = $this->get_woo_free_shipping_threshold();
		} else {
			$this->threshold = floatval( $this->settings['threshold'] ?? 0 );
		}
		return (float) $this->threshold;
	}

	/**
	 * Get free shipping threshold from WooCommerce zones.
	 * Only runs after woocommerce_init so WC countries/continents are ready (avoids "get_continents() on null").
	 *
	 * @return float
	 */
	private function get_woo_free_shipping_threshold() {
		$threshold = 0.0;

		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return $threshold;
		}
		if ( ! did_action( 'woocommerce_init' ) ) {
			return $threshold;
		}

		$shipping_zones = WC_Shipping_Zones::get_zones();
		foreach ( $shipping_zones as $zone ) {
			$methods = ( is_object( $zone ) && method_exists( $zone, 'get_shipping_methods' ) )
				? $zone->get_shipping_methods()
				: ( isset( $zone['shipping_methods'] ) ? $zone['shipping_methods'] : array() );
			foreach ( $methods as $method ) {
				$id = is_object( $method ) ? ( $method->id ?? '' ) : ( $method['id'] ?? '' );
				if ( 'free_shipping' !== $id ) {
					continue;
				}
				$enabled = is_object( $method ) ? ( $method->enabled ?? '' ) : ( $method['enabled'] ?? '' );
				if ( 'yes' !== $enabled ) {
					continue;
				}
				$min = 0;
				if ( is_object( $method ) ) {
					$min = isset( $method->min_amount ) ? floatval( $method->min_amount ) : ( isset( $method->instance_settings['min_amount'] ) ? floatval( $method->instance_settings['min_amount'] ) : 0 );
				} else {
					$min = isset( $method['min_amount'] ) ? floatval( $method['min_amount'] ) : 0;
				}
				if ( $min > 0 ) {
					$threshold = $min;
					break 2;
				}
			}
		}

		if ( $threshold <= 0 ) {
			$zone_0 = WC_Shipping_Zones::get_zone( 0 );
			if ( $zone_0 && is_object( $zone_0 ) && method_exists( $zone_0, 'get_shipping_methods' ) ) {
				foreach ( $zone_0->get_shipping_methods() as $method ) {
					if ( ( is_object( $method ) ? ( $method->id ?? '' ) : '' ) !== 'free_shipping' ) {
						continue;
					}
					$enabled = is_object( $method ) ? ( $method->enabled ?? '' ) : '';
					if ( 'yes' !== $enabled ) {
						continue;
					}
					$min = isset( $method->min_amount ) ? floatval( $method->min_amount ) : ( isset( $method->instance_settings['min_amount'] ) ? floatval( $method->instance_settings['min_amount'] ) : 0 );
					if ( $min > 0 ) {
						$threshold = $min;
						break;
					}
				}
			}
		}

		return floatval( $threshold );
	}

	/**
	 * Register display hooks based on position and show_on_pages.
	 */
	private function add_display_hooks() {
		$show_on = (array) ( $this->settings['show_on_pages'] ?? array() );
		$position = $this->settings['position'] ?? 'top';

		if ( in_array( 'product', $show_on, true ) ) {
			if ( 'top' === $position ) {
				add_action( 'woocommerce_before_single_product', array( $this, 'render_bar' ) );
			} else {
				add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'render_bar' ) );
			}
		}

		if ( in_array( 'cart', $show_on, true ) ) {
			if ( 'top' === $position ) {
				add_action( 'woocommerce_before_cart', array( $this, 'render_bar' ) );
			} elseif ( 'above_cart' === $position ) {
				add_action( 'woocommerce_before_cart_table', array( $this, 'render_bar' ) );
			} else {
				add_action( 'woocommerce_cart_totals_before_order_total', array( $this, 'render_bar_in_totals' ) );
			}
		}

		if ( in_array( 'shop', $show_on, true ) ) {
			add_action( 'woocommerce_before_shop_loop', array( $this, 'render_bar' ) );
		}
	}

	/**
	 * Enqueue shipping bar assets. Only on product/cart/shop/category; respects meyvc_should_enqueue_assets filter.
	 */
	public function enqueue_assets() {
		if ( ! class_exists( 'MEYVC_Public' ) || ! MEYVC_Public::should_enqueue_assets( 'shipping_bar' ) ) {
			return;
		}
		if ( $this->get_threshold() <= 0 || ! $this->should_show() ) {
			return;
		}

		wp_enqueue_style(
			'meyvc-shipping-bar',
			MEYVC_PLUGIN_URL . 'public/css/meyvc-boosters' . meyvc_asset_min_suffix() . '.css',
			array(),
			MEYVC_VERSION
		);
		wp_enqueue_script(
			'meyvc-shipping-bar',
			MEYVC_PLUGIN_URL . 'public/js/meyvc-shipping-bar' . meyvc_asset_min_suffix() . '.js',
			array( 'jquery' ),
			MEYVC_VERSION,
			true
		);

		wp_localize_script(
			'meyvc-shipping-bar',
			'meyvcShippingBar',
			array(
				'threshold' => $this->get_threshold(),
				'cartTotal' => $this->get_cart_total(),
				'settings'  => $this->settings,
				'currency'  => function_exists( 'get_woocommerce_currency_symbol' ) ? (string) get_woocommerce_currency_symbol() : '',
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'meyvc_shipping_bar' ),
			)
		);

		wp_localize_script(
			'meyvc-shipping-bar',
			'meyvcTrackerData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'meyvc-track-event' ),
			)
		);

		$inline_tracker = "window.meyvcTracker = window.meyvcTracker || {}; meyvcTracker.ajaxUrl = (typeof meyvcTrackerData !== 'undefined' && meyvcTrackerData.ajaxUrl) ? meyvcTrackerData.ajaxUrl : ''; meyvcTracker.nonce = (typeof meyvcTrackerData !== 'undefined' && meyvcTrackerData.nonce) ? meyvcTrackerData.nonce : ''; meyvcTracker.track = meyvcTracker.track || function(eventType, data) { if (!meyvcTracker.ajaxUrl || !meyvcTracker.nonce) return; var d = { action: 'meyvc_track_event', nonce: meyvcTracker.nonce, event_type: eventType, campaign_id: 0, source_type: 'shipping_bar', event_data: data || {} }; if (typeof jQuery !== 'undefined') jQuery.post(meyvcTracker.ajaxUrl, d); };";
		wp_add_inline_script( 'meyvc-shipping-bar', $inline_tracker, 'after' );
	}

	/**
	 * AJAX handler: return current cart subtotal for shipping bar updates.
	 */
	public function ajax_get_cart_total() {
		check_ajax_referer( 'meyvc_shipping_bar', 'nonce' );

		$rl_ip = class_exists( 'MEYVC_Security' ) ? MEYVC_Security::get_client_ip() : '';
		if ( class_exists( 'MEYVC_Security' ) && ! MEYVC_Security::check_rate_limit( 'meyvc_ajax_' . sanitize_key( current_action() ) . '_' . $rl_ip, 20, 60 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please slow down.', 'meyvora-convert' ) ), 429 );
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'Cart not available.', 'meyvora-convert' ), 'total' => 0 ) );
		}

		$total = floatval( WC()->cart->get_subtotal() );
		wp_send_json_success( array( 'total' => $total ) );
	}

	/**
	 * Whether the shipping bar should be shown on this request.
	 *
	 * @return bool
	 */
	private function should_show() {
		$show_on = (array) ( $this->settings['show_on_pages'] ?? array() );

		if ( in_array( 'product', $show_on, true ) && function_exists( 'is_product' ) && is_product() ) {
			return true;
		}
		if ( in_array( 'cart', $show_on, true ) && function_exists( 'is_cart' ) && is_cart() ) {
			return true;
		}
		if ( in_array( 'shop', $show_on, true ) && function_exists( 'is_shop' ) && ( is_shop() || is_product_category() ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get current cart subtotal.
	 *
	 * @return float
	 */
	private function get_cart_total() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0.0;
		}
		return floatval( WC()->cart->get_subtotal() );
	}

	/**
	 * Render the shipping bar. Respects banner frequency cap (max N times per visitor per 24h).
	 */
	public function render_bar() {
		$th = $this->get_threshold();
		if ( $th <= 0 ) {
			return;
		}
		$cap     = function_exists( 'meyvc_settings' ) ? meyvc_settings()->get_banner_frequency_settings() : array();
		$max_24h = (int) ( $cap['max_per_24h'] ?? 0 );
		if ( $max_24h > 0 && class_exists( 'MEYVC_Visitor_State' ) ) {
			$visitor = MEYVC_Visitor_State::get_instance();
			if ( ! $visitor->can_show_banner( 'shipping_bar', $max_24h ) ) {
				return;
			}
		}
		$cart_total = $this->get_cart_total();
		$remaining  = max( 0, $th - $cart_total );
		$progress   = $th > 0 ? min( 100, ( $cart_total / $th ) * 100 ) : 0;
		$achieved   = $remaining <= 0;

		$tone = isset( $this->settings['tone'] ) ? $this->settings['tone'] : 'neutral';
		$progress_msg  = isset( $this->settings['message_progress'] ) && (string) $this->settings['message_progress'] !== ''
			? (string) $this->settings['message_progress']
			: ( class_exists( 'MEYVC_Default_Copy' ) ? MEYVC_Default_Copy::get( 'shipping_bar', $tone, 'progress' ) : __( 'Add {amount} more for free shipping', 'meyvora-convert' ) );
		$achieved_msg  = isset( $this->settings['message_achieved'] ) && (string) $this->settings['message_achieved'] !== ''
			? (string) $this->settings['message_achieved']
			: ( class_exists( 'MEYVC_Default_Copy' ) ? MEYVC_Default_Copy::get( 'shipping_bar', $tone, 'achieved' ) : __( 'You\'ve got free shipping', 'meyvora-convert' ) );
		$message = $achieved
			? $achieved_msg
			: str_replace( '{amount}', wc_price( $remaining ), $progress_msg );

		$bg_color  = sanitize_hex_color( $this->settings['bg_color'] ?? '#f7f7f7' ) ?: '#f7f7f7';
		$bar_color = sanitize_hex_color( $this->settings['bar_color'] ?? '#333333' ) ?: '#333333';
		$render_context = array( 'achieved' => $achieved, 'remaining' => $remaining, 'message' => $message );
		do_action( 'meyvc_frontend_before_render', 'shipping_bar', $render_context );
		?>
		<div class="meyvc-shipping-bar" style="background-color: <?php echo esc_attr( $bg_color ); ?>;">
			<div class="meyvc-shipping-bar-inner">
				<span class="meyvc-shipping-bar-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'truck', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>
				<span class="meyvc-shipping-bar-message"><?php echo wp_kses_post( $message ); ?></span>
			</div>
			<?php if ( ! $achieved ) : ?>
			<div class="meyvc-shipping-bar-progress">
				<div class="meyvc-shipping-bar-fill"
					 style="width: <?php echo esc_attr( $progress ); ?>%; background-color: <?php echo esc_attr( $bar_color ); ?>;"></div>
			</div>
			<?php endif; ?>
		</div>
		<?php
		do_action( 'meyvc_frontend_after_render', 'shipping_bar', $render_context );
		if ( $max_24h > 0 && class_exists( 'MEYVC_Visitor_State' ) ) {
			MEYVC_Visitor_State::get_instance()->record_banner_show( 'shipping_bar' );
		}
	}

	/**
	 * Render shipping bar inside cart totals table.
	 */
	public function render_bar_in_totals() {
		echo '<tr class="meyvc-shipping-bar-row"><td colspan="2">';
		$this->render_bar();
		echo '</td></tr>';
	}
}
