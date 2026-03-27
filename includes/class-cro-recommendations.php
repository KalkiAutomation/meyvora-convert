<?php
/**
 * Product recommendations based on co-purchase history.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * CRO_Recommendations class.
 */
class CRO_Recommendations {

	const CACHE_TTL = DAY_IN_SECONDS;

	/** @var string Object cache group for read-through DB queries. */
	private const DB_READ_CACHE_GROUP = 'meyvora_cro';

	/** @var int Read-through TTL (seconds). */
	private const DB_READ_CACHE_TTL = 300;

	/**
	 * @param string                    $descriptor 2–4 word slug.
	 * @param array<int|string|float> $params     Params.
	 * @return string
	 */
	private static function read_cache_key( string $descriptor, array $params ): string {
		return 'meyvora_cro_' . md5( $descriptor . '_' . implode( '_', array_map( 'strval', $params ) ) );
	}

	/**
	 * Cached result of wc_order_product_lookup existence (per request).
	 *
	 * @var bool|null
	 */
	private static $lookup_exists = null;

	/**
	 * Whether WooCommerce order product lookup table exists (one SHOW TABLES per request).
	 */
	private static function lookup_table_exists(): bool {
		if ( null !== self::$lookup_exists ) {
			return self::$lookup_exists;
		}
		global $wpdb;
		$table    = $wpdb->prefix . 'wc_order_product_lookup';
		$ck       = self::read_cache_key( 'wc_order_product_lookup_exists', array( $table ) );
		$found    = false;
		$tbl_name = wp_cache_get( $ck, self::DB_READ_CACHE_GROUP, false, $found );
		if ( ! $found ) {
			$tbl_name = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
			wp_cache_set( $ck, $tbl_name, self::DB_READ_CACHE_GROUP, self::DB_READ_CACHE_TTL );
		}
		self::$lookup_exists = ( $tbl_name === $table );

		return self::$lookup_exists;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! function_exists( 'cro_settings' ) || ! cro_settings()->is_feature_enabled( 'recommendations' ) ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'woocommerce_after_single_product_summary', array( $this, 'render_frequently_bought' ), 15 );
		add_action( 'woocommerce_after_cart_table', array( $this, 'render_cart_recommendations' ), 10 );
	}

	/**
	 * Booster stylesheet for recommendation layout.
	 */
	public function enqueue_styles(): void {
		if ( ! class_exists( 'CRO_Public' ) || ! CRO_Public::should_enqueue_assets( 'campaigns' ) ) {
			return;
		}
		$load = ( function_exists( 'is_product' ) && is_product() ) || ( function_exists( 'is_cart' ) && is_cart() );
		if ( ! $load ) {
			return;
		}
		wp_enqueue_style(
			'cro-boosters',
			CRO_PLUGIN_URL . 'public/css/cro-boosters' . cro_asset_min_suffix() . '.css',
			array(),
			CRO_VERSION
		);
	}

	/**
	 * Render "Frequently bought together" on product page.
	 */
	public function render_frequently_bought(): void {
		global $product;
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}
		$recs = $this->get_co_purchased( (int) $product->get_id(), 4 );
		if ( empty( $recs ) ) {
			return;
		}
		echo '<section class="cro-recommendations cro-frequently-bought">';
		echo '<h3 class="cro-recommendations__title">' . esc_html__( 'Frequently bought together', 'meyvora-convert' ) . '</h3>';
		echo '<ul class="cro-recommendations__list">';
		foreach ( $recs as $pid ) {
			$p = wc_get_product( $pid );
			if ( ! $p || ! $p->is_purchasable() || ! $p->is_visible() ) {
				continue;
			}
			echo '<li class="cro-recommendations__item">';
			echo '<a href="' . esc_url( get_permalink( $pid ) ) . '">' . wp_kses_post( $p->get_image( 'woocommerce_thumbnail' ) ) . '</a>';
			echo '<a class="cro-recommendations__name" href="' . esc_url( get_permalink( $pid ) ) . '">' . esc_html( $p->get_name() ) . '</a>';
			echo '<span class="cro-recommendations__price">' . wp_kses_post( $p->get_price_html() ) . '</span>';
			echo '</li>';
		}
		echo '</ul>';
		echo '</section>';
	}

	/**
	 * Render "You might also like" on cart page.
	 */
	public function render_cart_recommendations(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		$cart_ids = array_map(
			static function ( $item ) {
				return (int) $item['product_id'];
			},
			WC()->cart->get_cart()
		);
		if ( empty( $cart_ids ) ) {
			return;
		}
		$recs = $this->get_co_purchased( $cart_ids[0], 4 );
		$recs = array_values( array_diff( $recs, $cart_ids ) );
		if ( empty( $recs ) ) {
			return;
		}
		echo '<div class="cro-recommendations cro-cart-recommendations"><h4>' . esc_html__( 'You might also like', 'meyvora-convert' ) . '</h4><ul class="cro-recommendations__list">';
		foreach ( array_slice( $recs, 0, 3 ) as $pid ) {
			$p = wc_get_product( $pid );
			if ( ! $p || ! $p->is_purchasable() || ! $p->is_visible() ) {
				continue;
			}
			echo '<li class="cro-recommendations__item">';
			echo '<a href="' . esc_url( get_permalink( $pid ) ) . '">' . wp_kses_post( $p->get_image( 'woocommerce_thumbnail' ) ) . '</a>';
			echo '<a href="' . esc_url( get_permalink( $pid ) ) . '">' . esc_html( $p->get_name() ) . '</a>';
			echo '<span>' . wp_kses_post( $p->get_price_html() ) . '</span>';
			echo '</li>';
		}
		echo '</ul></div>';
	}

	/**
	 * Get product IDs frequently co-purchased with the given product.
	 * Uses wc_order_product_lookup for HPOS compatibility.
	 *
	 * @param int $product_id Source product ID.
	 * @param int $limit      Max results.
	 * @return int[]
	 */
	public function get_co_purchased( int $product_id, int $limit = 4 ): array {
		$cache_key = 'cro_rec_' . $product_id . '_' . $limit;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$lookup = $wpdb->prefix . 'wc_order_product_lookup';

		if ( ! self::lookup_table_exists() ) {
			set_transient( $cache_key, array(), self::CACHE_TTL );
			return array();
		}

		// Table names via %i (WordPress 6.2+) — avoids interpolating $lookup into SQL strings.
		$ck_res = self::read_cache_key( 'co_purchased_product_ids', array( $lookup, $product_id, $limit ) );
		$results = wp_cache_get( $ck_res, self::DB_READ_CACHE_GROUP );
		if ( false === $results ) {
			$results = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT product_id FROM (
					SELECT b.product_id, COUNT(*) AS co_cnt
					FROM %i a
					INNER JOIN %i b ON a.order_id = b.order_id AND b.product_id != a.product_id
					WHERE a.product_id = %d AND b.product_id > 0
					GROUP BY b.product_id
					ORDER BY co_cnt DESC
					LIMIT %d
				) t',
					$lookup,
					$lookup,
					$product_id,
					$limit
				)
			);
			if ( ! is_array( $results ) ) {
				$results = array();
			}
			wp_cache_set( $ck_res, $results, self::DB_READ_CACHE_GROUP, self::DB_READ_CACHE_TTL );
		}

		$ids = array_map( 'absint', is_array( $results ) ? $results : array() );
		set_transient( $cache_key, $ids, self::CACHE_TTL );
		return $ids;
	}
}
