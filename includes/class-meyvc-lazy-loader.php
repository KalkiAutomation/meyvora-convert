<?php
/**
 * CRO Lazy Loader
 *
 * Deferred loading of heavy components
 *
 * @package Meyvora_Convert
 */

defined( 'ABSPATH' ) || exit;

class MEYVC_Lazy_Loader {

	/** @var array Registered lazy loaders (name => callable) */
	private static $loaders = array();

	/** @var array Loaded components (name => instance) */
	private static $loaded = array();

	/**
	 * Register a lazy loader
	 *
	 * @param string   $name     Component name.
	 * @param callable $callback Callback that returns the component (no args).
	 */
	public static function register( $name, $callback ) {
		$name = sanitize_key( (string) $name );
		if ( $name !== '' && is_callable( $callback ) ) {
			self::$loaders[ $name ] = $callback;
		}
	}

	/**
	 * Load a component by name (run callback once, then cache).
	 *
	 * @param string $name Component name.
	 * @return mixed Cached instance or null if not registered.
	 */
	public static function load( $name ) {
		$name = sanitize_key( (string) $name );

		if ( isset( self::$loaded[ $name ] ) ) {
			return self::$loaded[ $name ];
		}

		if ( ! isset( self::$loaders[ $name ] ) ) {
			return null;
		}

		$callback = self::$loaders[ $name ];
		self::$loaded[ $name ] = call_user_func( $callback );
		return self::$loaded[ $name ];
	}

	/**
	 * Check if component is already loaded
	 *
	 * @param string $name Component name.
	 * @return bool
	 */
	public static function is_loaded( $name ) {
		return isset( self::$loaded[ sanitize_key( (string) $name ) ] );
	}

	/**
	 * Initialize default lazy loaders
	 */
	public static function init() {
		self::register( 'analytics', function () {
			return class_exists( 'MEYVC_Analytics' ) ? new MEYVC_Analytics() : null;
		} );

		self::register( 'ab_test', function () {
			return class_exists( 'MEYVC_AB_Test' ) ? new MEYVC_AB_Test() : null;
		} );

		self::register( 'templates', function () {
			if ( class_exists( 'MEYVC_Templates' ) && method_exists( 'MEYVC_Templates', 'get_all' ) ) {
				return MEYVC_Templates::get_all();
			}
			return array();
		} );

		self::register( 'placeholders', function () {
			return class_exists( 'MEYVC_Placeholders' ) ? new MEYVC_Placeholders() : null;
		} );
	}
}

add_action( 'init', array( 'MEYVC_Lazy_Loader', 'init' ), 5 );
