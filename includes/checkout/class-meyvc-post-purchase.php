<?php
/**
 * Post-purchase upsell (thank-you page).
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MEYVC_Post_Purchase class.
 */
class MEYVC_Post_Purchase {

	const SESSION_COUPON_KEY = 'meyvc_post_purchase_coupon';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$settings = meyvc_settings()->get_checkout_settings();
		if ( empty( $settings['post_purchase_enabled'] ) || ! function_exists( 'WC' ) ) {
			return;
		}

		add_action( 'woocommerce_thankyou', array( $this, 'render_upsell' ), 25 );

		add_action( 'wp_ajax_meyvc_post_purchase_add', array( $this, 'ajax_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_meyvc_post_purchase_add', array( $this, 'ajax_add_to_cart' ) );

		add_action( 'woocommerce_add_to_cart', array( $this, 'maybe_apply_session_coupon' ), 20, 6 );
	}

	/**
	 * Ensure session coupon is applied after AJAX add-to-cart.
	 *
	 * @param string     $cart_item_key Key.
	 * @param int        $product_id    Product ID.
	 * @param int        $quantity     Qty.
	 * @param int        $variation_id Variation.
	 * @param array      $variation    Variation data.
	 * @param array      $cart_item_data Extra.
	 */
	public function maybe_apply_session_coupon( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		if ( ! WC()->session || ! wc_coupons_enabled() ) {
			return;
		}
		$code = WC()->session->get( self::SESSION_COUPON_KEY );
		if ( ! is_string( $code ) || $code === '' ) {
			return;
		}
		if ( WC()->cart && ! WC()->cart->has_discount( $code ) ) {
			WC()->cart->apply_coupon( $code );
		}
	}

	/**
	 * Render upsell block on thank-you page.
	 *
	 * @param int $order_id Order ID.
	 */
	public function render_upsell( $order_id ) {
		$order_id = absint( $order_id );
		if ( ! $order_id ) {
			return;
		}

		$settings = meyvc_settings()->get_checkout_settings();
		$ids      = isset( $settings['post_purchase_product_ids'] ) ? $settings['post_purchase_product_ids'] : '';
		if ( is_string( $ids ) ) {
			$ids = array_filter( array_map( 'absint', preg_split( '/[\s,]+/', $ids ) ) );
		}
		if ( ! is_array( $ids ) ) {
			$ids = array();
		}

		if ( empty( $ids ) ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$ids = $this->discover_upsell_products( $order );
			}
		}

		$products = array();
		foreach ( $ids as $pid ) {
			$p = wc_get_product( $pid );
			if ( $p && $p->is_purchasable() && $p->is_in_stock() ) {
				$products[] = $p;
			}
		}
		if ( empty( $products ) ) {
			return;
		}

		$discount = isset( $settings['post_purchase_discount_percent'] ) ? (float) $settings['post_purchase_discount_percent'] : 0;
		$headline = isset( $settings['post_purchase_headline'] ) ? $settings['post_purchase_headline'] : __( 'Add this to your next order', 'meyvora-convert' );
		$sub      = isset( $settings['post_purchase_subhead'] ) ? $settings['post_purchase_subhead'] : '';

		$coupon_code = '';
		if ( $discount > 0 && $discount <= 90 ) {
			$coupon_code = $this->get_or_create_upsell_coupon( $discount );
			if ( $coupon_code && WC()->session ) {
				WC()->session->set( self::SESSION_COUPON_KEY, $coupon_code );
			}
		}

		$this->track_upsell(
			'post_purchase_impression',
			$order_id,
			array(
				'product_ids' => array_map(
					static function ( $p ) {
						return is_a( $p, 'WC_Product' ) ? $p->get_id() : 0;
					},
					$products
				),
			)
		);

		wp_enqueue_script( 'jquery' );
		$nonce = wp_create_nonce( 'meyvc_post_purchase' );

		wp_enqueue_script(
			'meyvc-post-purchase-upsell',
			MEYVC_PLUGIN_URL . 'public/js/meyvc-post-purchase-upsell.js',
			array( 'jquery' ),
			MEYVC_VERSION,
			true
		);
		wp_localize_script(
			'meyvc-post-purchase-upsell',
			'meyvcPostPurchaseUpsell',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => 'meyvc_post_purchase_add',
				'i18n'    => array(
					'adding' => __( 'Adding…', 'meyvora-convert' ),
					'added'  => __( 'Added!', 'meyvora-convert' ),
					'add'    => __( 'Add to cart', 'meyvora-convert' ),
					'error'  => __( 'Something went wrong. Please try again.', 'meyvora-convert' ),
				),
			)
		);

		$template_args = array(
			'order_id'    => $order_id,
			'products'    => $products,
			'headline'    => $headline,
			'sub'         => $sub,
			'discount'    => $discount,
			'coupon_code' => $coupon_code,
			'nonce'       => $nonce,
			'ajax_url'    => admin_url( 'admin-ajax.php' ),
		);

		$template = apply_filters(
			'meyvc_post_purchase_upsell_template',
			MEYVC_PLUGIN_DIR . 'templates/post-purchase/upsell.php'
		);
		$child = locate_template( array( 'meyvora-convert/post-purchase/upsell.php' ) );
		if ( is_string( $child ) && $child !== '' && is_readable( $child ) ) {
			$template = $child;
		}

		if ( is_string( $template ) && $template !== '' && is_readable( $template ) ) {
			extract( $template_args, EXTR_SKIP );
			include $template;
		}
	}

	/**
	 * Discover upsell product IDs automatically for a given order.
	 *
	 * @param WC_Order $order Order.
	 * @return int[]
	 */
	private function discover_upsell_products( $order ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return array();
		}

		$purchased_ids     = array();
		$purchased_cat_ids = array();

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}
			$pid             = (int) $product->get_id();
			$purchased_ids[] = $pid;
			$cat_ids         = wp_get_post_terms( $pid, 'product_cat', array( 'fields' => 'ids' ) );
			if ( is_array( $cat_ids ) && ! is_wp_error( $cat_ids ) ) {
				$purchased_cat_ids = array_merge( $purchased_cat_ids, $cat_ids );
			}
		}

		$purchased_ids     = array_unique( array_filter( $purchased_ids ) );
		$purchased_cat_ids = array_unique( array_filter( $purchased_cat_ids ) );

		$upsell_ids = array();
		foreach ( $purchased_ids as $pid ) {
			$p = wc_get_product( $pid );
			if ( $p ) {
				$upsell_ids = array_merge( $upsell_ids, (array) $p->get_upsell_ids() );
			}
		}
		$upsell_ids = array_diff( array_unique( array_filter( array_map( 'absint', $upsell_ids ) ) ), $purchased_ids );
		foreach ( $upsell_ids as $uid ) {
			$up = wc_get_product( $uid );
			if ( $up && $up->is_purchasable() && $up->is_in_stock() ) {
				return array( $uid );
			}
		}

		if ( ! empty( $purchased_cat_ids ) ) {
			$cat_slugs = array();
			foreach ( $purchased_cat_ids as $tid ) {
				$term = get_term( (int) $tid, 'product_cat' );
				if ( $term && ! is_wp_error( $term ) ) {
					$cat_slugs[] = $term->slug;
				}
			}
			$cat_slugs = array_unique( array_filter( $cat_slugs ) );
			if ( ! empty( $cat_slugs ) ) {
				$product_ids = wc_get_products(
					array(
						'status'   => 'publish',
						'limit'    => 24,
						'orderby'  => 'date',
						'order'    => 'DESC',
						'category' => $cat_slugs,
						'return'   => 'ids',
					)
				);
				if ( is_array( $product_ids ) ) {
					// Exclude purchased IDs in PHP (avoid post__not_in / NOT IN in SQL).
					$candidate_ids = array_values(
						array_diff( array_map( 'absint', $product_ids ), $purchased_ids )
					);
					foreach ( $candidate_ids as $pid ) {
						$p = wc_get_product( $pid );
						if ( $p && $p->is_purchasable() && $p->is_in_stock() ) {
							return array( $pid );
						}
					}
				}
			}
		}

		// Avoid post__not_in / SQL NOT IN: fetch product IDs only, then remove purchased IDs in PHP.
		// Tradeoff: we request a fixed batch (posts_per_page). If most of those rows are purchased or
		// not purchasable, we may scan fewer viable candidates than the batch size—acceptable for this fallback.
		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 24,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$candidate_ids = array_values(
			array_diff(
				array_map( 'absint', $query->posts ),
				$purchased_ids
			)
		);
		foreach ( $candidate_ids as $post_id ) {
			$p = wc_get_product( $post_id );
			if ( $p && $p->is_purchasable() && $p->is_in_stock() ) {
				return array( $post_id );
			}
		}

		return array();
	}

	/**
	 * AJAX: add upsell product to cart.
	 */
	public function ajax_add_to_cart() {
		check_ajax_referer( 'meyvc_post_purchase', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		if ( ! $product_id || ! function_exists( 'WC' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'meyvora-convert' ) ) );
		}

		$added = WC()->cart->add_to_cart( $product_id, 1 );
		if ( ! $added ) {
			wp_send_json_error( array( 'message' => __( 'Could not add to cart.', 'meyvora-convert' ) ) );
		}

		$code = WC()->session ? WC()->session->get( self::SESSION_COUPON_KEY ) : '';
		if ( is_string( $code ) && $code !== '' && WC()->cart && wc_coupons_enabled() && ! WC()->cart->has_discount( $code ) ) {
			WC()->cart->apply_coupon( $code );
		}

		$this->track_upsell( 'post_purchase_add_to_cart', 0, array( 'product_id' => $product_id ) );

		wp_send_json_success( array( 'message' => __( 'Added to cart.', 'meyvora-convert' ) ) );
	}

	/**
	 * One reusable coupon for post-purchase % discount (storewide cap via Woo).
	 *
	 * @param float $percent Percent 1–90.
	 * @return string Coupon code.
	 */
	private function get_or_create_upsell_coupon( $percent ) {
		if ( ! class_exists( 'WC_Coupon' ) ) {
			return '';
		}
		$exist = get_option( 'meyvc_post_purchase_coupon_' . (int) $percent, '' );
		if ( is_string( $exist ) && $exist !== '' ) {
			$c = new WC_Coupon( $exist );
			if ( $c->get_id() ) {
				return $exist;
			}
		}

		$code = strtoupper( 'MEYVC-PP-' . wp_generate_password( 6, false ) );
		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( $percent );
		$coupon->set_individual_use( false );
		$coupon->set_usage_limit( 0 );
		$coupon->save();
		update_option( 'meyvc_post_purchase_coupon_' . (int) $percent, $code, false );

		return $code;
	}

	/**
	 * Track as interaction in meyvc_events (non-campaign source).
	 *
	 * @param string $name      Event name stored in metadata.
	 * @param int    $order_id  Related order or 0.
	 * @param array  $extra     Extra metadata.
	 */
	private function track_upsell( $name, $order_id, $extra = array() ) {
		if ( ! class_exists( 'MEYVC_Tracker' ) ) {
			return;
		}
		$tracker = new MEYVC_Tracker();
		$page_url = '';
		$req_uri  = filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_UNSAFE_RAW );
		if ( is_string( $req_uri ) && $req_uri !== '' ) {
			$page_url = esc_url_raw( home_url( wp_unslash( $req_uri ) ) );
		}
		$data    = array_merge(
			array(
				'event_subtype' => $name,
				'order_id'      => $order_id,
				'page_url'      => $page_url,
			),
			$extra
		);
		$tracker->track( $name, 0, $data, 'trust_badge', 0 );
	}
}
