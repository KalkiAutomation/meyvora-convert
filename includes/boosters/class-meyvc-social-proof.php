<?php
/**
 * Social proof booster (recent purchase activity on product pages).
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MEYVC_Social_Proof class.
 */
class MEYVC_Social_Proof {

	/** @var string Object cache group for read-through DB queries. */
	private const DB_READ_CACHE_GROUP = 'meyvora_meyvc';

	/** @var int Read-through TTL (seconds). */
	private const DB_READ_CACHE_TTL = 300;

	/**
	 * @param string                    $descriptor 2–4 word slug.
	 * @param array<int|string|float> $params     Params.
	 * @return string
	 */
	private function read_cache_key( string $descriptor, array $params ): string {
		return 'meyvora_meyvc_' . md5( $descriptor . '_' . implode( '_', array_map( 'strval', $params ) ) );
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! function_exists( 'meyvc_settings' ) || ! meyvc_settings()->is_feature_enabled( 'social_proof' ) ) {
			return;
		}
		add_action( 'woocommerce_single_product_summary', array( $this, 'render' ), 35 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_social_proof_frontend_scripts' ) );
		// Bust recent-purchases cache when a new order completes.
		add_action( 'woocommerce_order_status_completed', array( $this, 'bust_purchases_cache' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'bust_purchases_cache' ) );
		$this->maybe_init_viewing_counter();
	}

	/**
	 * Styles (reuse boosters stylesheet pattern).
	 */
	public function enqueue() {
		if ( ! class_exists( 'MEYVC_Public' ) || ! MEYVC_Public::should_enqueue_assets( 'campaigns' ) ) {
			return;
		}
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		wp_enqueue_style(
			'meyvc-boosters',
			MEYVC_PLUGIN_URL . 'public/css/meyvc-boosters' . meyvc_asset_min_suffix() . '.css',
			array(),
			MEYVC_VERSION
		);
	}

	/**
	 * Output social proof line.
	 */
	public function render() {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return;
		}
		global $product;
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}
		$settings = wp_parse_args(
			meyvc_settings()->get_group( 'social_proof' ),
			array(
				'window_hours'   => 24,
				'min_quantity'   => 1,
				'message_template' => __( '{count} people bought this in the last {hours} hours.', 'meyvora-convert' ),
			)
		);
		$hours = max( 1, min( 168, absint( $settings['window_hours'] ) ) );
		$min_q = max( 1, absint( $settings['min_quantity'] ) );

		$count = $this->get_recent_sales_count( (int) $product->get_id(), $hours );
		if ( $count < $min_q ) {
			return;
		}

		$msg = str_replace(
			array( '{count}', '{hours}' ),
			array( number_format_i18n( $count ), number_format_i18n( $hours ) ),
			(string) $settings['message_template']
		);

		echo '<div class="meyvc-social-proof"><span class="meyvc-social-proof__text">' . esc_html( $msg ) . '</span></div>';
	}

	/**
	 * Register the "viewing this" counter hook (JS-driven, product pages only).
	 */
	private function maybe_init_viewing_counter() {
		$settings = meyvc_settings()->get_group( 'social_proof' );
		if ( empty( $settings['viewing_counter_enabled'] ) ) {
			return;
		}
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_viewing_counter' ), 36 );
	}

	/**
	 * Output the viewing counter placeholder (populated by JS).
	 */
	public function render_viewing_counter() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		echo '<div class="meyvc-viewing-counter" id="meyvc-viewing-counter" style="display:none;" aria-live="polite"></div>';
	}

	/**
	 * Enqueue toast + viewing-counter script; pass data via wp_localize_script (no inline JS).
	 */
	public function enqueue_social_proof_frontend_scripts() {
		$settings = meyvc_settings()->get_group( 'social_proof' );

		$need_viewing = ! empty( $settings['viewing_counter_enabled'] ) && function_exists( 'is_product' ) && is_product();
		$need_toast   = ! empty( $settings['toast_enabled'] );
		$toast_rows   = array();

		if ( $need_toast ) {
			$pages_setting = isset( $settings['toast_pages'] ) ? (string) $settings['toast_pages'] : 'product';
			$show_toast    = ( 'all' === $pages_setting )
				|| ( 'product' === $pages_setting && function_exists( 'is_product' ) && is_product() )
				|| ( 'home' === $pages_setting && is_front_page() )
				|| ( 'both' === $pages_setting && (
					( function_exists( 'is_product' ) && is_product() ) || is_front_page()
				) );
			if ( ! $show_toast ) {
				$need_toast = false;
			} else {
				$toast_rows = $this->get_recent_purchases( 10 );
				if ( empty( $toast_rows ) ) {
					$need_toast = false;
				}
			}
		}

		if ( ! $need_viewing && ! $need_toast ) {
			return;
		}

		wp_enqueue_script(
			'meyvc-social-proof-toast',
			MEYVC_PLUGIN_URL . 'public/js/meyvc-social-proof-toast' . meyvc_asset_min_suffix() . '.js',
			array(),
			MEYVC_VERSION,
			true
		);

		if ( $need_viewing ) {
			$min  = max( 2, absint( $settings['viewing_min'] ?? 2 ) );
			$max  = max( $min + 1, absint( $settings['viewing_max'] ?? 9 ) );
			$tmpl = isset( $settings['viewing_template'] ) && is_string( $settings['viewing_template'] ) && $settings['viewing_template'] !== ''
				? $settings['viewing_template']
				: /* translators: %d: number of people viewing the product. */
				__( '%d people are viewing this right now', 'meyvora-convert' );
			wp_localize_script(
				'meyvc-social-proof-toast',
				'meyvcViewingCounter',
				array(
					'min'  => (int) $min,
					'max'  => (int) $max,
					'tmpl' => $tmpl,
				)
			);
		}

		if ( $need_toast ) {
			wp_localize_script(
				'meyvc-social-proof-toast',
				'meyvc_social_proof',
				array(
					'purchases'      => $toast_rows,
					'initial_delay'  => absint( $settings['toast_initial_delay'] ?? 8 ) * 1000,
					'interval'       => absint( $settings['toast_interval'] ?? 12 ) * 1000,
					'toast_template' => isset( $settings['toast_template'] ) && is_string( $settings['toast_template'] ) ? $settings['toast_template'] : (
						/* translators: 1: customer first name or placeholder, 2: city/region, 3: product name. */
						__( '{name} from {location} just bought {product}', 'meyvora-convert' )
					),
				)
			);
		}
	}

	/**
	 * Clear the recent-purchases transient cache.
	 */
	public function bust_purchases_cache(): void {
		delete_transient( 'meyvc_sp_recent_purchases_10' );
	}

	/**
	 * Fetch recent completed orders for the toast: first name, city/country, product name.
	 * HPOS-safe via wc_get_orders(). Results cached for 15 minutes.
	 *
	 * @param int $limit Max orders to process.
	 * @return array<int, array{name:string,location:string,product:string}>
	 */
	private function get_recent_purchases( int $limit = 10 ): array {
		$cache_key = 'meyvc_sp_recent_purchases_' . $limit;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$orders = wc_get_orders(
			array(
				'limit'   => absint( $limit ),
				'status'  => array( 'wc-completed', 'wc-processing' ),
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);

		$out = array();
		foreach ( $orders as $order ) {
			if ( ! is_a( $order, 'WC_Order' ) ) {
				continue;
			}

			// GDPR: allow store owners to exclude specific orders.
			if ( ! apply_filters( 'meyvc_social_proof_include_order', true, $order ) ) {
				continue;
			}

			$first_name = $order->get_billing_first_name();
			if ( $first_name === '' ) {
				continue;
			}

			$location = $order->get_billing_city();
			if ( $location === '' ) {
				$location = $order->get_billing_country();
			}
			if ( $location === '' ) {
				continue;
			}

			$items = $order->get_items();
			if ( empty( $items ) ) {
				continue;
			}

			$item         = reset( $items );
			$product_name = is_object( $item ) && method_exists( $item, 'get_name' ) ? $item->get_name() : '';
			if ( $product_name === '' ) {
				continue;
			}

			$out[] = array(
				'name'     => sanitize_text_field( $first_name ),
				'location' => sanitize_text_field( $location ),
				'product'  => sanitize_text_field( $product_name ),
			);
		}

		set_transient( $cache_key, $out, 15 * MINUTE_IN_SECONDS );
		return $out;
	}

	/**
	 * Sum line-item qty for a product in paid orders in the last N hours.
	 * HPOS-safe: queries wc_orders first, falls back to posts table.
	 *
	 * @param int $product_id Product ID.
	 * @param int $hours      Window in hours.
	 * @return int
	 */
	private function get_recent_sales_count( $product_id, $hours ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return 0;
		}

		$cache_key = 'meyvc_sp_' . $product_id . '_' . $hours;
		$cached    = get_transient( $cache_key );
		if ( $cached !== false && is_numeric( $cached ) ) {
			return (int) $cached;
		}

		global $wpdb;
		$since = gmdate( 'Y-m-d H:i:s', time() - $hours * 3600 );
		$sum   = 0;

		$hpos_table = $wpdb->prefix . 'wc_orders';
		$ck_hpos    = $this->read_cache_key( 'wc_hpos_table_exists', array( $hpos_table ) );
		$found_h    = false;
		$hpos_tbl   = wp_cache_get( $ck_hpos, self::DB_READ_CACHE_GROUP, false, $found_h );
		if ( ! $found_h ) {
			$hpos_tbl = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hpos_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
			wp_cache_set( $ck_hpos, $hpos_tbl, self::DB_READ_CACHE_GROUP, self::DB_READ_CACHE_TTL );
		}
		$hpos_exists = ( $hpos_tbl === $hpos_table );

		$t_items = $wpdb->prefix . 'woocommerce_order_items';
		$t_itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';

		if ( $hpos_exists ) {
			$ck_sum = $this->read_cache_key(
				'recent_sales_sum_hpos',
				array( $product_id, $hours, $since, $hpos_table, $t_items, $t_itemmeta )
			);
			$sum = wp_cache_get( $ck_sum, self::DB_READ_CACHE_GROUP );
			if ( false === $sum ) {
				$sum = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
					$wpdb->prepare(
						'SELECT SUM( CAST( qty.meta_value AS UNSIGNED ) )
					FROM %i oi
					INNER JOIN %i pid
						ON pid.order_item_id = oi.order_item_id
						AND pid.meta_key = %s
						AND pid.meta_value = %d
					INNER JOIN %i qty
						ON qty.order_item_id = oi.order_item_id
						AND qty.meta_key = %s
					INNER JOIN %i o
						ON o.id = oi.order_id
					WHERE oi.order_item_type = %s
					AND o.type = %s
					AND o.status IN (\'wc-completed\',\'wc-processing\',\'wc-on-hold\')
					AND o.date_created_gmt >= %s',
						$t_items,
						$t_itemmeta,
						'_product_id',
						$product_id,
						$t_itemmeta,
						'_qty',
						$hpos_table,
						'line_item',
						'shop_order',
						$since
					)
				);
				wp_cache_set( $ck_sum, $sum, self::DB_READ_CACHE_GROUP, self::DB_READ_CACHE_TTL );
			} else {
				$sum = (int) $sum;
			}
		} else {
			$ck_sum = $this->read_cache_key(
				'recent_sales_sum_posts',
				array( $product_id, $hours, $since, $t_items, $t_itemmeta, $wpdb->posts )
			);
			$sum = wp_cache_get( $ck_sum, self::DB_READ_CACHE_GROUP );
			if ( false === $sum ) {
				$sum = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
					$wpdb->prepare(
						'SELECT SUM( CAST( qty.meta_value AS UNSIGNED ) )
					FROM %i oi
					INNER JOIN %i pid
						ON pid.order_item_id = oi.order_item_id
						AND pid.meta_key = %s
						AND pid.meta_value = %d
					INNER JOIN %i qty
						ON qty.order_item_id = oi.order_item_id
						AND qty.meta_key = %s
					INNER JOIN %i p
						ON p.ID = oi.order_id
					WHERE oi.order_item_type = %s
					AND p.post_type = %s
					AND p.post_status IN (\'wc-completed\',\'wc-processing\',\'wc-on-hold\')
					AND p.post_date_gmt >= %s',
						$t_items,
						$t_itemmeta,
						'_product_id',
						$product_id,
						$t_itemmeta,
						'_qty',
						$wpdb->posts,
						'line_item',
						'shop_order',
						$since
					)
				);
				wp_cache_set( $ck_sum, $sum, self::DB_READ_CACHE_GROUP, self::DB_READ_CACHE_TTL );
			} else {
				$sum = (int) $sum;
			}
		}

		set_transient( $cache_key, $sum, 10 * MINUTE_IN_SECONDS );
		return $sum;
	}
}
