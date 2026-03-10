<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The public-facing functionality of the plugin.
 */
class CRO_Public {

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
	 * @return bool
	 */
	public static function has_campaign_for_current_page() {
		if ( self::is_woo_relevant_page() ) {
			return true;
		}
		if ( is_front_page() && function_exists( 'cro_settings' ) && cro_settings()->is_feature_enabled( 'campaigns' ) ) {
			return true;
		}
		return apply_filters( 'cro_campaign_targets_current_page', false );
	}

	/**
	 * Whether assets should be enqueued for the given context. Use for conditional loading.
	 *
	 * @param string $context Optional. 'global', 'campaigns', 'sticky_cart', 'shipping_bar', 'trust_badges', 'stock_urgency', 'checkout', 'cart', or 'default'.
	 * @return bool
	 */
	public static function should_enqueue_assets( $context = 'default' ) {
		if ( is_admin() ) {
			return apply_filters( 'cro_should_enqueue_assets', false, $context );
		}
		$pagenow = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
		if ( in_array( $pagenow, array( 'wp-login.php', 'wp-register.php' ), true ) ) {
			return apply_filters( 'cro_should_enqueue_assets', false, $context );
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return apply_filters( 'cro_should_enqueue_assets', false, $context );
		}
		if ( wp_doing_ajax() ) {
			return apply_filters( 'cro_should_enqueue_assets', false, $context );
		}

		$should = self::is_woo_relevant_page();
		if ( ! $should && self::is_campaign_preview_static() ) {
			$should = true;
		}
		if ( ! $should && in_array( $context, array( 'global', 'campaigns', 'default' ), true ) && self::has_campaign_for_current_page() ) {
			$should = true;
		}

		return apply_filters( 'cro_should_enqueue_assets', $should, $context );
	}

	/**
	 * Whether the current request is a valid campaign preview (signed token + expiry).
	 * Used to load assets for preview links; does not require logged-in user.
	 *
	 * @return bool
	 */
	public static function is_campaign_preview_static() {
		if ( ! isset( $_GET['cro_preview'] ) || (string) $_GET['cro_preview'] !== '1' ) {
			return false;
		}
		$preview_id = isset( $_GET['preview_id'] ) ? sanitize_text_field( wp_unslash( $_GET['preview_id'] ) ) : '';
		$token      = isset( $_GET['cro_token'] ) ? sanitize_text_field( wp_unslash( $_GET['cro_token'] ) ) : '';
		$expiry     = isset( $_GET['cro_expiry'] ) ? (int) $_GET['cro_expiry'] : 0;
		if ( ! $preview_id || ! $token || ! $expiry ) {
			return false;
		}
		return class_exists( 'CRO_Campaign_Display' ) && CRO_Campaign_Display::validate_preview_token( $preview_id, $token, $expiry );
	}

	/**
	 * Whether front-end assets should be loaded (performance gate).
	 *
	 * @return bool
	 */
	public function should_load_assets() {
		if ( ! self::should_enqueue_assets( 'global' ) ) {
			return false;
		}

		// Don't load if plugin disabled.
		if ( ! function_exists( 'cro_settings' ) ) {
			return false;
		}
		if ( ! cro_settings()->get( 'general', 'plugin_enabled', true ) ) {
			return false;
		}

		// Check if any feature is enabled.
		$features = array( 'campaigns', 'sticky_cart', 'shipping_bar', 'cart_optimizer', 'checkout_optimizer', 'trust_badges', 'stock_urgency' );
		foreach ( $features as $feature ) {
			if ( cro_settings()->is_feature_enabled( $feature ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the current request is an admin campaign preview (cro_preview=1 & campaign_data).
	 *
	 * @return bool
	 */
	private function is_campaign_preview_request() {
		return self::is_campaign_preview_static();
	}

	/**
	 * Output Brand Styles CSS variables in wp_head so all CRO elements can use them.
	 */
	public function print_brand_styles_vars() {
		if ( ! self::should_enqueue_assets( 'global' ) || ! $this->should_load_assets() ) {
			return;
		}
		if ( ! function_exists( 'cro_settings' ) ) {
			return;
		}
		$styles  = cro_settings()->get_styles_settings();
		$primary = sanitize_hex_color( $styles['primary_color'] ?? '#333333' ) ?: '#333333';
		$secondary = sanitize_hex_color( $styles['secondary_color'] ?? '#555555' ) ?: '#555555';
		$radius  = absint( $styles['button_radius'] ?? $styles['border_radius'] ?? 8 );
		$spacing = absint( $styles['spacing'] ?? 8 );
		$spacing = $spacing >= 2 && $spacing <= 32 ? $spacing : 8;
		$scale   = (float) ( $styles['font_size_scale'] ?? 1 );
		$scale   = $scale >= 0.5 && $scale <= 2 ? $scale : 1;
		$radius_px = (int) $radius . 'px';
		$spacing_px = (int) $spacing . 'px';
		echo '<style id="cro-brand-vars">' . "\n";
		echo ':root {' . "\n";
		echo '  --cro-primary: ' . esc_attr( $primary ) . ";\n";
		echo '  --cro-radius: ' . esc_attr( $radius_px ) . ";\n";
		echo '  --cro-spacing: ' . esc_attr( $spacing_px ) . ";\n";
		echo '  --cro-font-size-scale: ' . (float) $scale . ";\n";
		echo '  --cro-primary-color: var(--cro-primary);' . "\n";
		echo '  --cro-secondary-color: ' . esc_attr( $secondary ) . ";\n";
		echo '  --cro-button-radius: var(--cro-radius);' . "\n";
		echo "}\n</style>\n";
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

		// Add Google Fonts (DM Sans) for popup typography.
		wp_enqueue_style(
			'cro-google-fonts',
			'https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'cro-popup',
			CRO_PLUGIN_URL . 'public/css/cro-popup.css',
			array(),
			CRO_VERSION,
			'all'
		);

		wp_enqueue_style(
			'cro-boosters',
			CRO_PLUGIN_URL . 'public/css/cro-boosters.css',
			array(),
			CRO_VERSION,
			'all'
		);

		// Add global styles as inline CSS.
		$styles = cro_settings()->get_styles_settings();
		$css    = $this->generate_global_styles_css( $styles );
		// Add theme compatibility overrides when available.
		if ( class_exists( 'CRO_Compatibility' ) && method_exists( 'CRO_Compatibility', 'get_instance' ) ) {
			$overrides = CRO_Compatibility::get_instance()->get_theme_css_overrides();
			if ( $overrides ) {
				$css .= "\n/* Theme compatibility */\n" . $overrides;
			}
		}
		wp_add_inline_style( 'cro-boosters', $css );
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
			--cro-animation-speed: {$animation_speed};
			--cro-font-size-base: {$font_size_base};
		}

		.cro-popup,
		.cro-sticky-cart,
		.cro-shipping-bar,
		.cro-checkout-trust,
		.cro-guarantee {
			font-size: var(--cro-font-size-base);
			{$font_family}
		}

		.cro-popup-button,
		.cro-sticky-cart-button,
		.cro-shipping-bar-fill,
		.cro-checkout-trust .cro-secure-badge {
			border-radius: var(--cro-radius);
		}

		.cro-popup-button,
		.cro-sticky-cart-button {
			background-color: var(--cro-primary) !important;
		}

		.cro-popup,
		.cro-sticky-cart,
		.cro-shipping-bar {
			transition: all var(--cro-animation-speed);
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
		$scripts_needed[] = 'cro-public';

		// Deferred bootstrap (runs after DOM / requestIdleCallback).
		$scripts_needed[] = 'cro-core';

		// Exit intent + popup: only on Woo/campaign-relevant pages (not checkout) or preview.
		if ( $this->is_campaign_preview_request() ) {
			$scripts_needed[] = 'cro-exit-intent';
			$scripts_needed[] = 'cro-ux-detector';
			$scripts_needed[] = 'cro-popup';
		} elseif ( cro_settings()->is_feature_enabled( 'campaigns' ) && ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) && self::has_campaign_for_current_page() ) {
			$scripts_needed[] = 'cro-exit-intent';
			$scripts_needed[] = 'cro-ux-detector';
			$scripts_needed[] = 'cro-popup';
		}

		// Sticky cart (product pages only; respect conditional loading filter).
		if ( cro_settings()->is_feature_enabled( 'sticky_cart' ) && self::should_enqueue_assets( 'sticky_cart' ) && function_exists( 'is_product' ) && is_product() ) {
			$scripts_needed[] = 'cro-sticky-cart';
		}

		// Shipping bar (product, cart, shop, category; respect conditional loading filter).
		if ( cro_settings()->is_feature_enabled( 'shipping_bar' ) && self::should_enqueue_assets( 'shipping_bar' ) ) {
			$load_shipping = ( function_exists( 'is_product' ) && is_product() )
				|| ( function_exists( 'is_cart' ) && is_cart() )
				|| ( function_exists( 'is_shop' ) && is_shop() )
				|| ( function_exists( 'is_product_category' ) && is_product_category() );
			if ( $load_shipping ) {
				$scripts_needed[] = 'cro-shipping-bar';
			}
		}

		// Build dependencies: cro-public first, cro-core after public, then feature scripts.
		$script_srcs = array(
			'cro-public'       => array( 'public/js/cro-public.js', array() ),
			'cro-core'        => array( 'public/js/cro-core.js', array( 'cro-public' ) ),
			'cro-exit-intent' => array( 'public/js/cro-exit-intent.js', array( 'jquery', 'cro-public' ) ),
			'cro-ux-detector' => array( 'public/js/cro-ux-detector.js', array() ),
			'cro-popup'       => array( 'public/js/cro-popup.js', array( 'jquery', 'cro-public', 'cro-ux-detector' ) ),
			'cro-sticky-cart' => array( 'public/js/cro-sticky-cart.js', array( 'jquery', 'cro-public' ) ),
			'cro-shipping-bar'=> array( 'public/js/cro-shipping-bar.js', array( 'jquery', 'cro-public' ) ),
		);

		foreach ( $scripts_needed as $handle ) {
			if ( ! isset( $script_srcs[ $handle ] ) ) {
				continue;
			}
			list( $path, $deps ) = $script_srcs[ $handle ];
			wp_enqueue_script(
				$handle,
				CRO_PLUGIN_URL . $path,
				$deps,
				CRO_VERSION,
				true
			);
		}

		// Localize croPopup when popup is loaded.
		if ( in_array( 'cro-popup', is_array( $scripts_needed ) ? $scripts_needed : array(), true ) ) {
			wp_localize_script(
				'cro-popup',
				'croPopup',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'cro-track-event' ),
				)
			);
		}

		// Localize croPublic and croConfig for core/public.
		$features = array(
			'exitIntent' => cro_settings()->is_feature_enabled( 'campaigns' ),
			'stickyCart' => cro_settings()->is_feature_enabled( 'sticky_cart' ),
			'shippingBar'=> cro_settings()->is_feature_enabled( 'shipping_bar' ),
		);
		wp_localize_script(
			'cro-public',
			'croPublic',
			array(
				'debugMode' => cro_settings()->get( 'general', 'debug_mode', false ),
				'isAdmin'   => current_user_can( 'manage_options' ),
			)
		);
		wp_localize_script(
			'cro-public',
			'croConfig',
			array(
				'features'        => $features,
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'errorReporting'  => (bool) cro_settings()->get( 'general', 'debug_mode', false ),
				'nonce'           => wp_create_nonce( 'cro_log_error' ),
			)
		);
	}
}

/**
 * Check if the current request is a WooCommerce-relevant page (product, cart, checkout, shop, category).
 *
 * @return bool
 */
function cro_is_woo_relevant_page() {
	return class_exists( 'CRO_Public' ) && CRO_Public::is_woo_relevant_page();
}

/**
 * Check if CRO assets should be enqueued for the given context. Developers can override via filter cro_should_enqueue_assets.
 *
 * @param string $context Optional. 'global', 'campaigns', 'sticky_cart', 'shipping_bar', 'trust_badges', 'stock_urgency', 'checkout', 'cart', or 'default'.
 * @return bool
 */
function cro_should_enqueue_assets( $context = 'default' ) {
	return class_exists( 'CRO_Public' ) && CRO_Public::should_enqueue_assets( $context );
}
