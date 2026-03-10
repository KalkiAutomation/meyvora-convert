<?php
/**
 * Theme and plugin compatibility
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CRO_Compatibility class.
 *
 * Detects checkout type, theme, conflicting plugins, and provides overrides.
 */
class CRO_Compatibility {

	/**
	 * Singleton instance.
	 *
	 * @var CRO_Compatibility|null
	 */
	private static $instance = null;

	/**
	 * Detected conflicts.
	 *
	 * @var array
	 */
	private $detected_conflicts = array();

	/**
	 * Get singleton instance.
	 *
	 * @return CRO_Compatibility
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'detect_environment' ) );
		add_action( 'admin_notices', array( $this, 'show_compatibility_notices' ) );
	}

	/**
	 * Detect checkout type, cart type, theme, and conflicting plugins.
	 */
	public function detect_environment() {
		$this->detect_checkout_type();
		$this->detect_cart_type();
		$this->detect_theme();
		$this->detect_conflicting_plugins();
	}

	/**
	 * Detect WooCommerce checkout type (classic vs blocks).
	 */
	public function detect_checkout_type() {
		if ( ! function_exists( 'cro_settings' ) || ! function_exists( 'wc_get_page_id' ) ) {
			return;
		}

		// Check for checkout blocks.
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Package' ) ) {
			$checkout_page_id = wc_get_page_id( 'checkout' );
			$checkout_page    = get_post( $checkout_page_id );

			if ( $checkout_page && has_block( 'woocommerce/checkout', $checkout_page ) ) {
				cro_settings()->set( 'compatibility', 'checkout_type', 'blocks' );
				return;
			}
		}

		cro_settings()->set( 'compatibility', 'checkout_type', 'classic' );
	}

	/**
	 * Detect WooCommerce cart type (classic vs blocks).
	 */
	public function detect_cart_type() {
		if ( ! function_exists( 'cro_settings' ) || ! function_exists( 'wc_get_page_id' ) ) {
			return;
		}

		if ( class_exists( 'Automattic\WooCommerce\Blocks\Package' ) ) {
			$cart_page_id = wc_get_page_id( 'cart' );
			$cart_page    = $cart_page_id ? get_post( $cart_page_id ) : null;

			if ( $cart_page && has_block( 'woocommerce/cart', $cart_page ) ) {
				cro_settings()->set( 'compatibility', 'cart_type', 'blocks' );
				return;
			}
		}

		cro_settings()->set( 'compatibility', 'cart_type', 'classic' );
	}

	/**
	 * Get cart type.
	 *
	 * @return string 'blocks' or 'classic'
	 */
	public function get_cart_type() {
		return function_exists( 'cro_settings' ) ? cro_settings()->get( 'compatibility', 'cart_type', 'classic' ) : 'classic';
	}

	/**
	 * Check if using block cart.
	 *
	 * @return bool
	 */
	public function is_block_cart() {
		return $this->get_cart_type() === 'blocks';
	}

	/**
	 * Get checkout type.
	 *
	 * @return string 'blocks' or 'classic'
	 */
	public function get_checkout_type() {
		return function_exists( 'cro_settings' ) ? cro_settings()->get( 'compatibility', 'checkout_type', 'classic' ) : 'classic';
	}

	/**
	 * Check if using block checkout.
	 *
	 * @return bool
	 */
	public function is_block_checkout() {
		return $this->get_checkout_type() === 'blocks';
	}

	/**
	 * Detect current theme and known compatibility overrides.
	 */
	public function detect_theme() {
		if ( ! function_exists( 'cro_settings' ) ) {
			return;
		}

		$theme      = wp_get_theme();
		$theme_slug = $theme->get_stylesheet();

		// Known themes that need special handling.
		$special_themes = array(
			'storefront'   => array( 'popup_z_index' => 999999 ),
			'astra'       => array( 'sticky_cart_bottom' => '60px' ),
			'flavflavor'  => array( 'shipping_bar_position' => 'fixed' ),
			'divi'        => array( 'popup_container' => '#page-container' ),
			'avada'       => array( 'popup_z_index' => 9999999 ),
			'oceanwp'     => array(),
			'generatepress' => array(),
		);

		if ( isset( $special_themes[ $theme_slug ] ) ) {
			cro_settings()->set( 'compatibility', 'theme_overrides', $special_themes[ $theme_slug ] );
		} else {
			cro_settings()->set( 'compatibility', 'theme_overrides', array() );
		}

		cro_settings()->set( 'compatibility', 'theme', $theme_slug );
	}

	/**
	 * Detect potentially conflicting plugins.
	 */
	public function detect_conflicting_plugins() {
		$conflicts = array();

		if ( ! function_exists( 'is_plugin_active' ) ) {
			if ( is_admin() && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			} else {
				$this->detected_conflicts = $conflicts;
				if ( function_exists( 'cro_settings' ) ) {
					cro_settings()->set( 'compatibility', 'conflicts', $conflicts );
				}
				return;
			}
		}

		// Other popup plugins.
		$popup_plugins = array(
			'optinmonster/optinmonster.php' => 'OptinMonster',
			'popup-maker/popup-maker.php'   => 'Popup Maker',
			'convertpro/convertpro.php'     => 'Convert Pro',
			'elementor-pro/elementor-pro.php' => 'Elementor Pro (popups)',
			'sumo/starter/starter.php'      => 'SUMO',
		);

		foreach ( $popup_plugins as $plugin_file => $name ) {
			if ( is_plugin_active( $plugin_file ) ) {
				$conflicts[] = array(
					'plugin'  => $name,
					'type'    => 'popup',
					'message' => sprintf(
						/* translators: %s: plugin name */
						__( '%1$s detected. Consider disabling its popups on pages where Meyvora Convert campaigns run.', 'meyvora-convert' ),
						$name
					),
				);
			}
		}

		// Caching plugins that might cache popups.
		$caching_plugins = array(
			'w3-total-cache/w3-total-cache.php' => 'W3 Total Cache',
			'wp-super-cache/wp-cache.php'      => 'WP Super Cache',
			'wp-rocket/wp-rocket.php'          => 'WP Rocket',
			'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache',
		);

		foreach ( $caching_plugins as $plugin_file => $name ) {
			if ( is_plugin_active( $plugin_file ) ) {
				$conflicts[] = array(
					'plugin'  => $name,
					'type'    => 'cache',
					'message' => sprintf(
						/* translators: %s: plugin name */
						__( '%1$s detected. Ensure dynamic content is excluded from caching.', 'meyvora-convert' ),
						$name
					),
				);
			}
		}

		// WooCommerce Subscriptions detection
		if ( class_exists( 'WC_Subscriptions' ) || is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) ) {
			if ( function_exists( 'cro_settings' ) ) {
				cro_settings()->set( 'compatibility', 'wc_subscriptions_active', true );
			}
		}

		$this->detected_conflicts = $conflicts;

		if ( function_exists( 'cro_settings' ) ) {
			cro_settings()->set( 'compatibility', 'conflicts', $conflicts );
		}
	}

	/**
	 * Show compatibility notices in admin.
	 */
	public function show_compatibility_notices() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || strpos( (string) ( $screen->id ?? '' ), 'cro-' ) === false ) {
			return;
		}

		if ( ! function_exists( 'cro_settings' ) ) {
			return;
		}

		$conflicts = cro_settings()->get( 'compatibility', 'conflicts', array() );

		foreach ( $conflicts as $conflict ) {
			if ( isset( $conflict['type'] ) && $conflict['type'] === 'popup' && ! empty( $conflict['message'] ) ) {
				echo '<div class="notice notice-warning is-dismissible"><p><strong>Meyvora Convert:</strong> ';
				echo esc_html( $conflict['message'] );
				echo '</p></div>';
			}
		}
	}

	/**
	 * Get CSS overrides for current theme.
	 *
	 * @return string CSS string.
	 */
	public function get_theme_css_overrides() {
		if ( ! function_exists( 'cro_settings' ) ) {
			return '';
		}

		$overrides = cro_settings()->get( 'compatibility', 'theme_overrides', array() );
		$css       = '';

		if ( ! empty( $overrides['popup_z_index'] ) ) {
			$z = absint( $overrides['popup_z_index'] );
			$css .= ".cro-overlay { z-index: {$z} !important; }\n";
		}

		if ( ! empty( $overrides['sticky_cart_bottom'] ) ) {
			$bottom = sanitize_text_field( $overrides['sticky_cart_bottom'] );
			$css   .= ".cro-sticky-cart { bottom: {$bottom} !important; }\n";
		}

		return $css;
	}
}

add_action( 'plugins_loaded', function() {
	if ( function_exists( 'cro_settings' ) ) {
		CRO_Compatibility::get_instance();
	}
}, 25 );
