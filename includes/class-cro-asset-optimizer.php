<?php
/**
 * CRO Asset Optimizer
 *
 * Optimizes CSS and JS loading
 *
 * @package Meyvora_Convert
 */

defined( 'ABSPATH' ) || exit;

class CRO_Asset_Optimizer {

	/**
	 * Initialize hooks
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'conditional_assets' ), 5 );
		add_filter( 'script_loader_tag', array( __CLASS__, 'defer_scripts' ), 10, 2 );
		add_action( 'wp_head', array( __CLASS__, 'preload_assets' ), 1 );
		add_action( 'wp_head', array( __CLASS__, 'inline_critical_css' ), 5 );
	}

	/**
	 * Only load assets when needed: Woo-relevant or campaign-targeted pages; not checkout; has active campaigns.
	 */
	public static function conditional_assets() {
		if ( is_admin() ) {
			return;
		}
		if ( ! apply_filters( 'cro_is_enabled', true ) ) {
			return;
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return;
		}
		if ( class_exists( 'CRO_Public' ) && ! CRO_Public::should_enqueue_assets( 'global' ) ) {
			return;
		}

		$has_campaigns = false;
		if ( class_exists( 'CRO_Cache' ) && method_exists( 'CRO_Cache', 'remember' ) ) {
			$has_campaigns = CRO_Cache::remember( 'has_active_campaigns', 300, function () {
				global $wpdb;
				$table = $wpdb->prefix . 'cro_campaigns';
				return (bool) $wpdb->get_var( "SELECT 1 FROM {$table} WHERE status = 'active' LIMIT 1" );
			} );
		} else {
			global $wpdb;
			$table        = $wpdb->prefix . 'cro_campaigns';
			$has_campaigns = (bool) $wpdb->get_var( "SELECT 1 FROM {$table} WHERE status = 'active' LIMIT 1" );
		}

		if ( ! $has_campaigns ) {
			return;
		}

		self::enqueue_assets();
	}

	/**
	 * Enqueue optimized assets
	 */
	private static function enqueue_assets() {
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		$css_file = "public/css/cro-popup{$suffix}.css";
		$css_path = defined( 'CRO_PLUGIN_DIR' ) ? CRO_PLUGIN_DIR . $css_file : '';
		if ( ! $css_path || ! file_exists( $css_path ) ) {
			$css_file = 'public/css/cro-popup.css';
		}
		wp_enqueue_style(
			'cro-popup',
			( defined( 'CRO_PLUGIN_URL' ) ? CRO_PLUGIN_URL : '' ) . $css_file,
			array(),
			defined( 'CRO_VERSION' ) ? CRO_VERSION : '1.0.0'
		);

		$js_file = "public/js/cro-bundle{$suffix}.js";
		$js_path = defined( 'CRO_PLUGIN_DIR' ) ? CRO_PLUGIN_DIR . $js_file : '';
		if ( ! $js_path || ! file_exists( $js_path ) ) {
			$js_file = 'public/js/cro-controller.js';
		}
		wp_enqueue_script(
			'meyvora-convert',
			( defined( 'CRO_PLUGIN_URL' ) ? CRO_PLUGIN_URL : '' ) . $js_file,
			array(),
			defined( 'CRO_VERSION' ) ? CRO_VERSION : '1.0.0',
			true
		);
	}

	/**
	 * Defer non-critical scripts
	 *
	 * @param string $tag    Script tag.
	 * @param string $handle Handle.
	 * @return string
	 */
	public static function defer_scripts( $tag, $handle ) {
		$defer_scripts = array( 'meyvora-convert', 'cro-controller', 'cro-popup', 'cro-signals' );

		if ( in_array( $handle, $defer_scripts, true ) ) {
			return str_replace( ' src', ' defer src', $tag );
		}
		return $tag;
	}

	/**
	 * Preload critical assets. Use the same file resolution as enqueue (no .min if file missing).
	 */
	public static function preload_assets() {
		if ( is_admin() ) {
			return;
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return;
		}
		if ( ! defined( 'CRO_PLUGIN_URL' ) || ! defined( 'CRO_PLUGIN_DIR' ) ) {
			return;
		}
		$suffix   = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		$css_file = 'public/css/cro-popup' . $suffix . '.css';
		if ( ! file_exists( CRO_PLUGIN_DIR . $css_file ) ) {
			$css_file = 'public/css/cro-popup.css';
		}
		?>
		<link rel="preload" href="<?php echo esc_url( CRO_PLUGIN_URL . $css_file ); ?>" as="style">
		<?php
	}

	/**
	 * Inline critical CSS for above-the-fold popup styles
	 */
	public static function inline_critical_css() {
		if ( is_admin() ) {
			return;
		}
		?>
		<style id="cro-critical-css">
		.cro-overlay{position:fixed;top:0;left:0;right:0;bottom:0;z-index:999998;opacity:0;transition:opacity .3s}
		.cro-popup{position:fixed;z-index:999999;opacity:0;transition:opacity .3s,transform .3s}
		.cro-popup--visible,.cro-overlay--visible{opacity:1}
		body.cro-popup-open{overflow:hidden}
		</style>
		<?php
	}

	/**
	 * Generate minified bundle (for build process)
	 *
	 * @return bool
	 */
	public static function generate_bundle() {
		$js_files = array(
			'cro-signals.js',
			'cro-animations.js',
			'cro-popup.js',
			'cro-controller.js',
		);

		$bundle = '';
		$dir    = defined( 'CRO_PLUGIN_DIR' ) ? CRO_PLUGIN_DIR : '';

		foreach ( $js_files as $file ) {
			$path = $dir . 'public/js/' . $file;
			if ( file_exists( $path ) && is_readable( $path ) ) {
				$bundle .= file_get_contents( $path ) . "\n";
			}
		}

		$out = $dir . 'public/js/cro-bundle.js';
		if ( $dir === '' || ! is_writable( dirname( $out ) ) ) {
			return false;
		}
		return (bool) file_put_contents( $out, $bundle );
	}
}

add_action( 'init', array( 'CRO_Asset_Optimizer', 'init' ), 5 );
