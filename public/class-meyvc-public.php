<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The public-facing functionality of the plugin.
 */
class MEYVC_Public {

	/**
	 * Initialize the class and set its properties.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize public hooks.
	 */
	private function init() {
		// Public hooks are registered in the loader
	}

	/**
	 * Whether the current request is a WooCommerce-relevant page (product, cart, checkout, shop, category).
	 *
	 * @return bool
	 */
	public static function is_woo_relevant_page() {
		if ( ! function_exists( 'is_woocommerce' ) ) {
			return false;
		}
		return is_shop() || is_product() || is_product_category() || is_cart() || is_checkout();
	}

	/**
	 * Whether any active campaign could show on the current page (Woo pages, front page, or filter).
	 *
	 * Campaign scripts must load on any public template where targeting might match (posts, pages,
	 * archives, search). Previously only Woo + front page loaded assets, so campaigns targeting
	 * “all pages” or blog content never received meyvc-controller or /decide calls.
	 *
	 * @return bool
	 */
	public static function has_campaign_for_current_page() {
		if ( self::is_woo_relevant_page() ) {
			return true;
		}
		if ( is_front_page() && function_exists( 'meyvc_settings' ) && (bool) meyvc_settings()->get( 'general', 'campaigns_enabled', true ) ) {
			return true;
		}
		if ( ! is_admin() && ! is_feed() ) {
			if ( is_singular() || is_home() || is_archive() || is_search() ) {
				return true;
			}
		}
		return apply_filters( 'meyvc_campaign_targets_current_page', false );
	}

	/**
	 * Whether assets should be enqueued for the given context. Use for conditional loading.
	 *
	 * @param string $context Optional. 'global', 'campaigns', 'sticky_cart', 'shipping_bar', 'trust_badges', 'stock_urgency', 'checkout', 'cart', or 'default'.
	 * @return bool
	 */
	public static function should_enqueue_assets( $context = 'default' ) {
		if ( is_admin() ) {
			return apply_filters( 'meyvc_should_enqueue_assets', false, $context );
		}
		$pagenow = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
		if ( in_array( $pagenow, array( 'wp-login.php', 'wp-register.php' ), true ) ) {
			return apply_filters( 'meyvc_should_enqueue_assets', false, $context );
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return apply_filters( 'meyvc_should_enqueue_assets', false, $context );
		}
		if ( wp_doing_ajax() ) {
			return apply_filters( 'meyvc_should_enqueue_assets', false, $context );
		}

		$should = self::is_woo_relevant_page();
		if ( ! $should && self::is_campaign_preview_static() ) {
			$should = true;
		}
		if ( ! $should && in_array( $context, array( 'global', 'campaigns', 'default' ), true ) && self::has_campaign_for_current_page() ) {
			$should = true;
		}

		return apply_filters( 'meyvc_should_enqueue_assets', $should, $context );
	}

	/**
	 * Whether the current request is a valid campaign preview (signed token + expiry).
	 * Used to load assets for preview links; does not require logged-in user.
	 *
	 * @return bool
	 */
	public static function is_campaign_preview_static() {
		if ( MEYVC_Security::get_query_var( 'meyvc_preview' ) !== '1' ) {
			return false;
		}
		$preview_id = MEYVC_Security::get_query_var( 'preview_id' );
		$token        = MEYVC_Security::get_query_var( 'meyvc_token' );
		$expiry       = MEYVC_Security::get_query_var_absint( 'meyvc_expiry' );
		if ( ! $preview_id || ! $token || ! $expiry ) {
			return false;
		}
		return class_exists( 'MEYVC_Campaign_Display' ) && MEYVC_Campaign_Display::validate_preview_token( $preview_id, $token, $expiry );
	}

	/**
	 * Whether any active campaign uses the gamified-wheel template.
	 * Result is cached for the request lifetime.
	 *
	 * @return bool
	 */
	private static function has_wheel_campaign(): bool {
		static $result = null;
		if ( null !== $result ) {
			return $result;
		}
		if ( class_exists( 'MEYVC_Cache' ) ) {
			$campaigns = MEYVC_Cache::get_active_campaigns();
			if ( is_array( $campaigns ) ) {
				foreach ( $campaigns as $c ) {
					$tpl = is_array( $c ) ? ( $c['template_type'] ?? '' ) : ( $c->template_type ?? '' );
					if ( 'gamified-wheel' === $tpl ) {
						$result = true;
						return true;
					}
				}
				$result = false;
				return false;
			}
		}
		$result = true;
		return true;
	}

	/**
	 * Whether front-end assets should be loaded (performance gate).
	 *
	 * @return bool
	 */
	public function should_load_assets() {
		return self::should_load_frontend_assets();
	}

	/**
	 * Static gate for scripts, styles, and unified meyvcConfig output (matches should_load_assets).
	 *
	 * @return bool
	 */
	public static function should_load_frontend_assets() {
		if ( ! self::should_enqueue_assets( 'global' ) ) {
			return false;
		}

		if ( ! function_exists( 'meyvc_settings' ) ) {
			return false;
		}
		if ( ! meyvc_settings()->get( 'general', 'plugin_enabled', true ) ) {
			return false;
		}

		$features = array( 'campaigns', 'sticky_cart', 'shipping_bar', 'cart_optimizer', 'checkout_optimizer', 'trust_badges', 'stock_urgency', 'recommendations' );
		foreach ( $features as $feature ) {
			if ( meyvc_settings()->is_feature_enabled( $feature ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the current request is an admin campaign preview (meyvc_preview=1 & campaign_data).
	 *
	 * @return bool
	 */
	private function is_campaign_preview_request() {
		return self::is_campaign_preview_static();
	}

	/**
	 * Add brand colour/spacing CSS variables as inline style on 'meyvc-boosters' (must run after that handle is enqueued).
	 */
	private function add_brand_css_variables_inline() {
		if ( ! function_exists( 'meyvc_settings' ) ) {
			return;
		}
		$styles    = meyvc_settings()->get_styles_settings();
		$primary   = sanitize_hex_color( $styles['primary_color'] ?? '#333333' ) ?: '#333333';
		$secondary = sanitize_hex_color( $styles['secondary_color'] ?? '#555555' ) ?: '#555555';
		$radius    = absint( $styles['button_radius'] ?? $styles['border_radius'] ?? 8 );
		$spacing   = absint( $styles['spacing'] ?? 8 );
		$spacing   = $spacing >= 2 && $spacing <= 32 ? $spacing : 8;
		$scale     = (float) ( $styles['font_size_scale'] ?? 1 );
		$scale     = $scale >= 0.5 && $scale <= 2 ? $scale : 1;
		$radius_px  = (int) $radius . 'px';
		$spacing_px = (int) $spacing . 'px';

		$css = ':root {' . "\n"
			. '  --meyvc-primary: ' . esc_attr( $primary ) . ";\n"
			. '  --meyvc-radius: ' . esc_attr( $radius_px ) . ";\n"
			. '  --meyvc-spacing: ' . esc_attr( $spacing_px ) . ";\n"
			. '  --meyvc-font-size-scale: ' . (float) $scale . ";\n"
			. '  --meyvc-primary-color: var(--meyvc-primary);' . "\n"
			. '  --meyvc-secondary-color: ' . esc_attr( $secondary ) . ";\n"
			. '  --meyvc-button-radius: var(--meyvc-radius);' . "\n"
			. "}\n";

		wp_add_inline_style( 'meyvc-boosters', $css );
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 * Only loads on Woo-relevant pages, campaign preview, or when a campaign targets the current page.
	 */
	public function enqueue_styles() {
		if ( ! self::should_enqueue_assets( 'global' ) ) {
			return;
		}
		if ( ! $this->should_load_assets() ) {
			return;
		}

		// Popup typography + meyvc-popup.css are enqueued by MEYVC_Frontend when campaigns load (avoids duplicate handles).

		wp_enqueue_style(
			'meyvc-boosters',
			MEYVC_PLUGIN_URL . 'public/css/meyvc-boosters' . meyvc_asset_min_suffix() . '.css',
			array(),
			MEYVC_VERSION,
			'all'
		);

		$this->add_brand_css_variables_inline();

		// Add global styles as inline CSS.
		$styles = meyvc_settings()->get_styles_settings();
		$css    = $this->generate_global_styles_css( $styles );
		// Add theme compatibility overrides when available.
		if ( class_exists( 'MEYVC_Compatibility' ) && method_exists( 'MEYVC_Compatibility', 'get_instance' ) ) {
			$overrides = MEYVC_Compatibility::get_instance()->get_theme_css_overrides();
			if ( $overrides ) {
				$css .= "\n/* Theme compatibility */\n" . $overrides;
			}
		}
		wp_add_inline_style( 'meyvc-boosters', $css );
	}

	/**
	 * Generate CSS from global style settings.
	 *
	 * @param array $styles Style settings.
	 * @return string CSS string.
	 */
	private function generate_global_styles_css( $styles ) {
		$animation_speed = $this->get_animation_speed_css( $styles['animation_speed'] ?? 'normal' );
		$font_family     = $this->get_font_family_css( $styles['font_family'] ?? 'inherit' );
		$scale           = (float) ( $styles['font_size_scale'] ?? 1 );
		$scale           = $scale >= 0.5 && $scale <= 2 ? $scale : 1;
		$font_size_base  = round( $scale * 16, 2 ) . 'px';

		$css = "
		/* Meyvora Convert – use Brand Styles vars from wp_head */
		:root {
			--meyvc-animation-speed: {$animation_speed};
			--meyvc-font-size-base: {$font_size_base};
		}

		.meyvc-popup,
		.meyvc-sticky-cart,
		.meyvc-shipping-bar,
		.meyvc-checkout-trust,
		.meyvc-guarantee {
			font-size: var(--meyvc-font-size-base);
			{$font_family}
		}

		.meyvc-popup-button,
		.meyvc-sticky-cart-button,
		.meyvc-shipping-bar-fill,
		.meyvc-checkout-trust .meyvc-secure-badge {
			border-radius: var(--meyvc-radius);
		}

		.meyvc-popup-button,
		.meyvc-sticky-cart-button {
			background-color: var(--meyvc-primary) !important;
		}

		.meyvc-popup,
		.meyvc-sticky-cart,
		.meyvc-shipping-bar {
			transition: all var(--meyvc-animation-speed);
		}
		";

		return $css;
	}

	/**
	 * Get font family CSS based on setting.
	 *
	 * @param string $font_family Font family setting.
	 * @return string CSS font-family declaration.
	 */
	private function get_font_family_css( $font_family ) {
		switch ( $font_family ) {
			case 'system':
				return 'font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;';
			case 'arial':
				return 'font-family: Arial, Helvetica, sans-serif;';
			case 'georgia':
				return 'font-family: Georgia, "Times New Roman", serif;';
			case 'inherit':
			default:
				return ''; // Inherit from theme.
		}
	}

	/**
	 * Get animation speed CSS value.
	 *
	 * @param string $speed Animation speed setting.
	 * @return string CSS transition duration.
	 */
	private function get_animation_speed_css( $speed ) {
		switch ( $speed ) {
			case 'fast':
				return '150ms';
			case 'slow':
				return '500ms';
			case 'none':
				return '0ms';
			case 'normal':
			default:
				return '300ms';
		}
	}

	/**
	 * Map of script handle => [ relative path from plugin root, dependencies ].
	 *
	 * @return array
	 */
	public static function get_frontend_script_specs(): array {
		$min = meyvc_asset_min_suffix();
		return array(
			'meyvc-public'       => array( "public/js/meyvc-public{$min}.js", array() ),
			'meyvc-core'         => array( "public/js/meyvc-core{$min}.js", array( 'meyvc-public' ) ),
			'meyvc-exit-intent'  => array( "public/js/meyvc-exit-intent{$min}.js", array( 'jquery', 'meyvc-public' ) ),
			'meyvc-ux-detector'  => array( "public/js/meyvc-ux-detector{$min}.js", array() ),
			'meyvc-signals'      => array( "public/js/meyvc-signals{$min}.js", array() ),
			'meyvc-animations'   => array( "public/js/meyvc-animations{$min}.js", array() ),
			'meyvc-popup'        => array( "public/js/meyvc-popup{$min}.js", array( 'jquery', 'meyvc-public', 'meyvc-ux-detector', 'meyvc-animations' ) ),
			'meyvc-controller'   => array( "public/js/meyvc-controller{$min}.js", array( 'meyvc-popup', 'meyvc-signals' ) ),
			'meyvc-spin-wheel'   => array( "public/js/meyvc-spin-wheel{$min}.js", array( 'jquery', 'meyvc-controller' ) ),
			'meyvc-sticky-cart'  => array( "public/js/meyvc-sticky-cart{$min}.js", array( 'jquery', 'meyvc-public' ) ),
			'meyvc-shipping-bar' => array( "public/js/meyvc-shipping-bar{$min}.js", array( 'jquery', 'meyvc-public' ) ),
		);
	}

	/**
	 * Enqueue the full campaign stack (through meyvc-controller.js) for [meyvc_campaign] and similar embeds.
	 *
	 * Does not require MEYVC_Frontend::has_active_campaigns() — shortcode embeds a specific campaign and still need
	 * signals, popup, REST /decide, and tracking. Main front page flow keeps the DB gate in enqueue_scripts().
	 *
	 * @return void
	 */
	public static function enqueue_campaign_scripts_for_shortcode() {
		if ( is_admin() || ! function_exists( 'meyvc_settings' ) ) {
			return;
		}
		if ( ! self::should_enqueue_assets( 'global' ) ) {
			return;
		}
		if ( ! meyvc_settings()->get( 'general', 'plugin_enabled', true ) ) {
			return;
		}
		if ( ! (bool) meyvc_settings()->get( 'general', 'campaigns_enabled', true ) ) {
			return;
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return;
		}

		$handles = array(
			'meyvc-public',
			'meyvc-core',
			'meyvc-exit-intent',
			'meyvc-ux-detector',
			'meyvc-signals',
			'meyvc-animations',
			'meyvc-popup',
			'meyvc-controller',
		);
		if ( self::has_wheel_campaign() ) {
			$handles[] = 'meyvc-spin-wheel';
		}
		$specs = self::get_frontend_script_specs();
		foreach ( $handles as $handle ) {
			if ( ! isset( $specs[ $handle ] ) ) {
				continue;
			}
			list( $path, $deps ) = $specs[ $handle ];
			wp_enqueue_script(
				$handle,
				MEYVC_PLUGIN_URL . $path,
				$deps,
				MEYVC_VERSION,
				true
			);
		}

		if ( in_array( 'meyvc-spin-wheel', $handles, true ) ) {
			wp_localize_script(
				'meyvc-spin-wheel',
				'meyvcSpinWheel',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'meyvc_public_actions' ),
				)
			);
			wp_localize_script(
				'meyvc-spin-wheel',
				'meyvc_spin_i18n',
				array(
					'you_won'   => __( 'You won: ', 'meyvora-convert' ),
					'try_again' => __( 'Better luck next time!', 'meyvora-convert' ),
				)
			);
		}

		wp_localize_script(
			'meyvc-popup',
			'meyvcPopup',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'meyvc-track-event' ),
			)
		);

		$can_decide_debug = current_user_can( 'manage_options' ) || current_user_can( 'manage_meyvora_convert' );
		wp_localize_script(
			'meyvc-public',
			'meyvcPublic',
			array(
				'debugMode'   => meyvc_settings()->get( 'general', 'debug_mode', false ),
				'isAdmin'     => current_user_can( 'manage_options' ),
				'decideDebug' => $can_decide_debug && meyvc_settings()->get( 'general', 'debug_mode', false ),
			)
		);
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 * Only enqueues scripts needed for enabled features and current page (Woo / campaign-relevant only).
	 */
	public function enqueue_scripts() {
		if ( ! self::should_enqueue_assets( 'global' ) ) {
			return;
		}
		if ( ! $this->should_load_assets() ) {
			return;
		}

		$scripts_needed = array();

		// Core (always when any feature is on).
		$scripts_needed[] = 'meyvc-public';

		// Deferred bootstrap (runs after DOM / requestIdleCallback).
		$scripts_needed[] = 'meyvc-core';

		// Exit intent + popup + controller: only when campaigns can run (active in DB or preview) on relevant pages.
		$campaign_scripts =
			$this->is_campaign_preview_request()
			|| (
				(bool) meyvc_settings()->get( 'general', 'campaigns_enabled', true )
				&& ( ! function_exists( 'is_checkout' ) || ! is_checkout() )
				&& self::has_campaign_for_current_page()
				&& class_exists( 'MEYVC_Frontend' )
				&& MEYVC_Frontend::has_active_campaigns()
			);
		if ( $campaign_scripts ) {
			$scripts_needed[] = 'meyvc-exit-intent';
			$scripts_needed[] = 'meyvc-ux-detector';
			$scripts_needed[] = 'meyvc-signals';
			$scripts_needed[] = 'meyvc-animations';
			$scripts_needed[] = 'meyvc-popup';
			$scripts_needed[] = 'meyvc-controller';
			if ( self::has_wheel_campaign() ) {
				$scripts_needed[] = 'meyvc-spin-wheel';
			}
		}

		// Sticky cart (product pages only; respect conditional loading filter).
		if ( meyvc_settings()->is_feature_enabled( 'sticky_cart' ) && self::should_enqueue_assets( 'sticky_cart' ) && function_exists( 'is_product' ) && is_product() ) {
			$scripts_needed[] = 'meyvc-sticky-cart';
		}

		// Shipping bar (product, cart, shop, category; respect conditional loading filter).
		if ( meyvc_settings()->is_feature_enabled( 'shipping_bar' ) && self::should_enqueue_assets( 'shipping_bar' ) ) {
			$load_shipping = ( function_exists( 'is_product' ) && is_product() )
				|| ( function_exists( 'is_cart' ) && is_cart() )
				|| ( function_exists( 'is_shop' ) && is_shop() )
				|| ( function_exists( 'is_product_category' ) && is_product_category() );
			if ( $load_shipping ) {
				$scripts_needed[] = 'meyvc-shipping-bar';
			}
		}

		$script_srcs = self::get_frontend_script_specs();

		foreach ( $scripts_needed as $handle ) {
			if ( ! isset( $script_srcs[ $handle ] ) ) {
				continue;
			}
			list( $path, $deps ) = $script_srcs[ $handle ];
			wp_enqueue_script(
				$handle,
				MEYVC_PLUGIN_URL . $path,
				$deps,
				MEYVC_VERSION,
				true
			);
		}

		if ( in_array( 'meyvc-spin-wheel', is_array( $scripts_needed ) ? $scripts_needed : array(), true ) ) {
			wp_localize_script(
				'meyvc-spin-wheel',
				'meyvcSpinWheel',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'meyvc_public_actions' ),
				)
			);
			wp_localize_script(
				'meyvc-spin-wheel',
				'meyvc_spin_i18n',
				array(
					'you_won'   => __( 'You won: ', 'meyvora-convert' ),
					'try_again' => __( 'Better luck next time!', 'meyvora-convert' ),
				)
			);
		}

		// Localize meyvcPopup when popup is loaded.
		if ( in_array( 'meyvc-popup', is_array( $scripts_needed ) ? $scripts_needed : array(), true ) ) {
			wp_localize_script(
				'meyvc-popup',
				'meyvcPopup',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'meyvc-track-event' ),
				)
			);
		}

		// meyvcConfig is printed once in MEYVC_Frontend::enqueue_frontend_config_script() (inline before meyvc-public) to avoid overwriting REST nonce vs error-log nonce.
		$can_decide_debug = current_user_can( 'manage_options' ) || current_user_can( 'manage_meyvora_convert' );
		wp_localize_script(
			'meyvc-public',
			'meyvcPublic',
			array(
				'debugMode'   => meyvc_settings()->get( 'general', 'debug_mode', false ),
				'isAdmin'     => current_user_can( 'manage_options' ),
				'decideDebug' => $can_decide_debug && meyvc_settings()->get( 'general', 'debug_mode', false ),
			)
		);
	}
}

/**
 * Check if the current request is a WooCommerce-relevant page (product, cart, checkout, shop, category).
 *
 * @return bool
 */
function meyvc_is_woo_relevant_page() {
	return class_exists( 'MEYVC_Public' ) && MEYVC_Public::is_woo_relevant_page();
}

/**
 * Check if CRO assets should be enqueued for the given context. Developers can override via filter meyvc_should_enqueue_assets.
 *
 * @param string $context Optional. 'global', 'campaigns', 'sticky_cart', 'shipping_bar', 'trust_badges', 'stock_urgency', 'checkout', 'cart', or 'default'.
 * @return bool
 */
function meyvc_should_enqueue_assets( $context = 'default' ) {
	return class_exists( 'MEYVC_Public' ) && MEYVC_Public::should_enqueue_assets( $context );
}
