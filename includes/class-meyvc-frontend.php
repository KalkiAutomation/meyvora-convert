<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Frontend asset loading (campaign popup styles + unified window.meyvcConfig).
 *
 * Scripts for campaigns are enqueued by MEYVC_Public; this class outputs config and popup-related CSS.
 */
class MEYVC_Frontend {

	/** @var string Object cache group for read-through DB queries. */
	private const DB_READ_CACHE_GROUP = 'meyvora_meyvc';

	/** @var int Read-through TTL (seconds). */
	private const DB_READ_CACHE_TTL = 300;

	/**
	 * @param string                    $descriptor 2–4 word slug.
	 * @param array<int|string|float> $params     Params.
	 * @return string
	 */
	private static function read_cache_key( string $descriptor, array $params ): string {
		return 'meyvora_meyvc_' . md5( $descriptor . '_' . implode( '_', array_map( 'strval', $params ) ) );
	}

	/**
	 * Cached should_load() result for this request.
	 *
	 * @var bool|null
	 */
	private static $should_load_cache = null;

	/**
	 * Whether footer config was already printed.
	 *
	 * @var bool
	 */
	private static $config_output = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_config_script' ), 100 );
	}

	/**
	 * Whether there is at least one active campaign (cached per request + transient).
	 *
	 * @return bool
	 */
	public static function has_active_campaigns() {
		static $cached_true = null;
		// Only cache a positive result permanently; false may become true later in the same request (e.g. after table creation).
		if ( $cached_true === true ) {
			return true;
		}
		if ( ! class_exists( 'MEYVC_Database' ) ) {
			return false;
		}
		$table = MEYVC_Database::get_table( 'campaigns' );
		if ( ! MEYVC_Database::table_exists( $table ) ) {
			return false;
		}
		$cached = get_transient( 'meyvc_has_active_campaigns' );
		if ( false !== $cached ) {
			$result = ( '1' === (string) $cached );
			if ( $result ) {
				$cached_true = true;
			}
			return $result;
		}
		global $wpdb;
		$ck     = self::read_cache_key( 'active_campaigns_count', array( $table ) );
		$found  = false;
		$count  = wp_cache_get( $ck, self::DB_READ_CACHE_GROUP, false, $found );
		if ( ! $found ) {
			$count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE status = %s LIMIT 1',
					$table,
					'active'
				)
			);
			wp_cache_set( $ck, $count, self::DB_READ_CACHE_GROUP, self::DB_READ_CACHE_TTL );
		} else {
			$count = (int) $count;
		}
		$result = $count > 0;
		set_transient( 'meyvc_has_active_campaigns', $result ? '1' : '0', 5 * MINUTE_IN_SECONDS );
		if ( $result ) {
			$cached_true = true;
		}
		return $result;
	}

	/**
	 * Whether campaign controller assets and rich config should load.
	 *
	 * @return bool
	 */
	private function should_load() {
		if ( null !== self::$should_load_cache ) {
			return self::$should_load_cache;
		}
		if ( is_admin() ) {
			self::$should_load_cache = false;
			return false;
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			self::$should_load_cache = false;
			return false;
		}
		if ( ! function_exists( 'meyvc_settings' ) ) {
			self::$should_load_cache = false;
			return false;
		}
		$settings = meyvc_settings();
		if ( ! $settings || ! $settings->get( 'general', 'plugin_enabled', true ) ) {
			self::$should_load_cache = false;
			return false;
		}
		if ( class_exists( 'MEYVC_Public' ) && MEYVC_Public::is_campaign_preview_static() ) {
			if ( ! MEYVC_Public::should_enqueue_assets( 'campaigns' ) ) {
				self::$should_load_cache = false;
				return false;
			}
			self::$should_load_cache = true;
			return true;
		}
		// [meyvc_campaign] embeds need full meyvcConfig + popup CSS even when no row is "active" for global decide().
		if ( class_exists( 'MEYVC_Shortcodes' ) && MEYVC_Shortcodes::current_page_has_campaign_shortcode() ) {
			if ( class_exists( 'MEYVC_Public' ) && MEYVC_Public::should_enqueue_assets( 'campaigns' ) ) {
				self::$should_load_cache = true;
				return true;
			}
		}
		if ( ! self::has_active_campaigns() ) {
			self::$should_load_cache = false;
			return false;
		}
		if ( class_exists( 'MEYVC_Public' ) && ! MEYVC_Public::should_enqueue_assets( 'campaigns' ) ) {
			self::$should_load_cache = false;
			return false;
		}
		self::$should_load_cache = true;
		return true;
	}

	/**
	 * Enqueue popup-related styles only (scripts are handled by MEYVC_Public).
	 */
	public function enqueue_assets() {
		if ( ! $this->should_load() ) {
			return;
		}
		$popup_deps = array();
		if ( function_exists( 'meyvc_settings' ) && 'yes' === meyvc_settings()->get( 'general', 'load_google_fonts', 'no' ) ) {
			wp_enqueue_style(
				'meyvc-dm-sans-fonts',
				MEYVC_PLUGIN_URL . 'public/css/meyvc-dm-sans-fonts.css',
				array(),
				MEYVC_VERSION
			);
			$popup_deps[] = 'meyvc-dm-sans-fonts';
		}
		wp_enqueue_style(
			'meyvc-popup',
			MEYVC_PLUGIN_URL . 'public/css/meyvc-popup' . meyvc_asset_min_suffix() . '.css',
			$popup_deps,
			MEYVC_VERSION
		);
		wp_enqueue_style(
			'meyvc-animations',
			MEYVC_PLUGIN_URL . 'public/css/meyvc-animations' . meyvc_asset_min_suffix() . '.css',
			array( 'meyvc-popup' ),
			MEYVC_VERSION
		);
	}

	/**
	 * Print window.meyvcConfig / window.meyvcTemplates via wp_add_inline_script (before meyvc-public) when assets load.
	 */
	public function enqueue_frontend_config_script() {
		if ( is_admin() ) {
			return;
		}
		if ( self::$config_output ) {
			return;
		}
		if ( ! function_exists( 'meyvc_settings' ) ) {
			return;
		}
		if ( ! class_exists( 'MEYVC_Public' ) || ! MEYVC_Public::should_load_frontend_assets() ) {
			return;
		}
		if ( ! meyvc_settings()->get( 'general', 'plugin_enabled', true ) ) {
			return;
		}
		if ( ! wp_script_is( 'meyvc-public', 'enqueued' ) ) {
			return;
		}

		$settings = meyvc_settings();
		$features = array(
			'exitIntent'  => (bool) $settings->get( 'general', 'campaigns_enabled', true ),
			'stickyCart'  => $settings->is_feature_enabled( 'sticky_cart' ),
			'shippingBar' => $settings->is_feature_enabled( 'shipping_bar' ),
		);

		$login_page_url = '';
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$login_page_url = wc_get_page_permalink( 'myaccount' );
		}
		if ( ! is_string( $login_page_url ) || $login_page_url === '' ) {
			$login_page_url = wp_login_url();
		}

		$config = array(
			'features'       => $features,
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'debugMode'      => (bool) $settings->get( 'general', 'debug_mode', false ),
			'errorReporting' => (bool) $settings->get( 'general', 'debug_mode', false ),
			'logErrorNonce'  => wp_create_nonce( 'meyvc_log_error' ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'restUrl'        => rest_url(),
			'publicNonce'    => wp_create_nonce( 'meyvc_public_actions' ),
			'siteUrl'        => home_url(),
			'cartUrl'        => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '/cart',
			'checkoutUrl'    => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '/checkout',
			'loginUrl'       => $login_page_url,
		);

		$templates = array();
		$full      = $this->should_load();

		if ( $full ) {
			$context = function_exists( 'meyvc_get_request_context' ) ? meyvc_get_request_context() : ( class_exists( 'MEYVC_Context' ) ? new MEYVC_Context() : null );
			$visitor = class_exists( 'MEYVC_Visitor_State' ) ? MEYVC_Visitor_State::get_instance() : null;
			$config  = array_merge(
				$config,
				array(
					'nonce'    => wp_create_nonce( 'wp_rest' ),
					'siteUrl'  => home_url(),
					'siteName' => get_bloginfo( 'name' ),
					'currency' => function_exists( 'get_woocommerce_currency_symbol' )
						? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' )
						: '$',
					'debug'    => current_user_can( 'manage_meyvora_convert' ) && $settings->get( 'general', 'debug_mode', false ),
					'context'  => $context && method_exists( $context, 'to_frontend_array' ) ? $context->to_frontend_array() : array(),
					'visitor'  => $visitor && method_exists( $visitor, 'to_frontend_array' ) ? $visitor->to_frontend_array() : array(),
				)
			);
			if ( class_exists( 'MEYVC_Templates' ) && method_exists( 'MEYVC_Templates', 'get_all' ) ) {
				foreach ( MEYVC_Templates::get_all() as $key => $template ) {
					$templates[ $key ] = array(
						'supports' => isset( $template['supports'] ) ? $template['supports'] : array(),
						'type'     => isset( $template['type'] ) ? $template['type'] : 'popup',
					);
				}
			}
		}

		self::$config_output = true;
		$json_flags          = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
		$inline              = 'window.meyvcConfig = ' . wp_json_encode( $config, $json_flags ) . ';';
		if ( $full ) {
			$inline .= "\n" . 'window.meyvcTemplates = ' . wp_json_encode( $templates, $json_flags ) . ';';
		}
		wp_add_inline_script( 'meyvc-public', $inline, 'before' );
	}
}
