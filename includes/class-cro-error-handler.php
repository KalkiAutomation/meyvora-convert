<?php
/**
 * CRO Error Handler
 *
 * Centralized error handling, logging, and recovery
 *
 * @package Meyvora_Convert
 */

defined( 'ABSPATH' ) || exit;

class CRO_Error_Handler {

	/** @var string Log file path */
	private static $log_file;

	/** @var bool Debug mode */
	private static $debug_mode = false;

	/** @var array Error counts for rate limiting */
	private static $error_counts = array();

	/**
	 * Initialize error handler
	 */
	public static function init() {
		self::$log_file = self::get_log_file_path();
		self::$debug_mode = defined( 'WP_DEBUG' ) && WP_DEBUG;

		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_emergency_notice' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_clear_emergency' ) );

		// Register shutdown function for fatal errors (avoid set_error_handler — not recommended for production / PHPCS).
		register_shutdown_function( array( __CLASS__, 'handle_shutdown' ) );
	}

	/**
	 * Log file path — under uploads/meyvora-convert/ (not web-servable when server honors .htaccess).
	 *
	 * @return string
	 */
	private static function get_log_file_path() {
		$upload_dir = wp_upload_dir();
		if ( empty( $upload_dir['error'] ) && ! empty( $upload_dir['basedir'] ) ) {
			$log_file = trailingslashit( $upload_dir['basedir'] ) . 'meyvora-convert/errors.log';
			$log_dir  = dirname( $log_file );
			if ( ! is_dir( $log_dir ) ) {
				wp_mkdir_p( $log_dir );
				// Block direct web access (Apache; nginx should deny /wp-content/uploads/ execution separately).
				$ht = $log_dir . '/.htaccess';
				if ( ! is_file( $ht ) ) {
					file_put_contents( $ht, "Deny from all\n" );
				}
				$idx = $log_dir . '/index.php';
				if ( ! is_file( $idx ) ) {
					file_put_contents( $idx, '<?php // Silence is golden.' );
				}
			}
			return $log_file;
		}
		return WP_CONTENT_DIR . '/meyvora-convert-errors.log';
	}

	/**
	 * Shutdown handler for fatal errors
	 */
	public static function handle_shutdown() {
		$error = error_get_last();

		if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			if ( strpos( $error['file'], 'meyvora-convert' ) !== false ) {
				self::log( 'FATAL', $error['message'], array(
					'file' => $error['file'],
					'line' => $error['line'],
				) );

				// Try to deactivate gracefully
				self::emergency_deactivate();
			}
		}
	}

	/**
	 * WordPress Filesystem API (direct) for log I/O.
	 *
	 * @return \WP_Filesystem_Base|null
	 */
	private static function get_wp_filesystem() {
		global $wp_filesystem;

		if ( ! empty( $wp_filesystem ) ) {
			return $wp_filesystem;
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! WP_Filesystem( false, false, true ) ) {
			return null;
		}

		return $wp_filesystem;
	}

	/**
	 * Log error/warning/info
	 *
	 * @param string $level   Log level.
	 * @param string $message Message.
	 * @param array  $context Optional context.
	 */
	public static function log( $level, $message, $context = array() ) {
		// Rate limiting: max 100 errors per hour
		$hour = gmdate( 'Y-m-d-H' );
		if ( ! isset( self::$error_counts[ $hour ] ) ) {
			self::$error_counts = array( $hour => 0 );
		}

		if ( self::$error_counts[ $hour ] >= 100 ) {
			return; // Stop logging to prevent disk fill
		}

		self::$error_counts[ $hour ]++;

		$timestamp  = current_time( 'Y-m-d H:i:s' );
		$context_str = ! empty( $context ) ? ' | ' . wp_json_encode( $context ) : '';

		$log_entry = "[{$timestamp}] [{$level}] {$message}{$context_str}" . PHP_EOL;

		$fs  = self::get_wp_filesystem();
		$dir = dirname( self::$log_file );
		if ( $fs && $fs->is_dir( $dir ) && $fs->is_writable( $dir ) ) {
			$prev = '';
			if ( $fs->exists( self::$log_file ) ) {
				$read = $fs->get_contents( self::$log_file );
				$prev = is_string( $read ) ? $read : '';
			}
			$fs->put_contents( self::$log_file, $prev . $log_entry );

			if ( $fs->exists( self::$log_file ) && (int) $fs->size( self::$log_file ) > 5 * 1024 * 1024 ) {
				self::rotate_log();
			}
		}

	}

	/**
	 * Rotate log file
	 */
	private static function rotate_log() {
		$fs = self::get_wp_filesystem();
		if ( ! $fs || ! $fs->exists( self::$log_file ) ) {
			return;
		}

		$backup = self::$log_file . '.' . gmdate( 'Y-m-d-His' ) . '.bak';
		if ( ! $fs->move( self::$log_file, $backup ) ) {
			$fs->put_contents( self::$log_file, '' );
			return;
		}

		$backups = glob( self::$log_file . '.*.bak' );
		if ( is_array( $backups ) && count( $backups ) > 3 ) {
			usort( $backups, function( $a, $b ) {
				return (int) filemtime( $a ) - (int) filemtime( $b );
			} );
			$to_remove = array_slice( $backups, 0, -3 );
			foreach ( $to_remove as $path ) {
				if ( is_string( $path ) && $path !== '' ) {
					wp_delete_file( $path );
				}
			}
		}
	}

	/**
	 * Emergency deactivation on fatal error
	 */
	private static function emergency_deactivate() {
		// Set transient to show admin notice
		set_transient( 'cro_fatal_error', true, HOUR_IN_SECONDS );

		// Don't actually deactivate - just disable frontend
		update_option( 'cro_emergency_disabled', true );
	}

	/**
	 * Check if emergency disabled
	 *
	 * @return bool
	 */
	public static function is_emergency_disabled() {
		return (bool) get_option( 'cro_emergency_disabled', false );
	}

	/**
	 * Re-enable after emergency
	 */
	public static function clear_emergency() {
		delete_option( 'cro_emergency_disabled' );
		delete_transient( 'cro_fatal_error' );
	}

	/**
	 * Show an admin notice when emergency disable is active (fatal error path).
	 *
	 * @return void
	 */
	public static function maybe_show_emergency_notice() {
		if ( ! self::is_emergency_disabled() ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$clear_url = wp_nonce_url(
			add_query_arg( 'cro_clear_emergency', '1', admin_url() ),
			'cro_clear_emergency'
		);
		echo '<div class="notice notice-error"><p>';
		printf(
			/* translators: %s: clear link HTML */
			esc_html__( 'Meyvora Convert: A fatal error was detected and campaigns have been disabled automatically. %s', 'meyvora-convert' ),
			'<a href="' . esc_url( $clear_url ) . '">' . esc_html__( 'Click here to re-enable', 'meyvora-convert' ) . '</a>'
		);
		echo '</p></div>';
	}

	/**
	 * Clear emergency disable when an administrator follows the re-enable link.
	 *
	 * @return void
	 */
	public static function maybe_clear_emergency() {
		if ( ! isset( $_GET['cro_clear_emergency'] ) ) {
			return;
		}
		if ( ! check_admin_referer( 'cro_clear_emergency' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		self::clear_emergency();
		wp_safe_redirect( admin_url() );
		exit;
	}

	/**
	 * Get recent log entries
	 *
	 * @param int $lines Number of lines to return.
	 * @return array
	 */
	public static function get_recent_logs( $lines = 50 ) {
		if ( ! file_exists( self::$log_file ) ) {
			return array();
		}

		$file = new SplFileObject( self::$log_file );
		$file->seek( PHP_INT_MAX );
		$total_lines = $file->key();

		$start = max( 0, $total_lines - $lines );
		$logs  = array();

		$file->seek( $start );
		while ( ! $file->eof() ) {
			$line = trim( $file->fgets() );
			if ( $line !== '' ) {
				$logs[] = $line;
			}
		}

		return array_reverse( $logs );
	}

	/**
	 * Wrap a callback with error handling
	 *
	 * @param callable    $callback Callback to execute.
	 * @param mixed       $default  Default return on exception.
	 * @param string      $context  Context string for logging.
	 * @return mixed
	 */
	public static function safe_execute( $callback, $default = null, $context = '' ) {
		try {
			return $callback();
		} catch ( Exception $e ) {
			self::log( 'EXCEPTION', $e->getMessage(), array(
				'context' => $context,
				'trace'   => $e->getTraceAsString(),
			) );
			return $default;
		} catch ( Error $e ) {
			self::log( 'ERROR', $e->getMessage(), array(
				'context' => $context,
				'trace'   => $e->getTraceAsString(),
			) );
			return $default;
		}
	}
}

add_action( 'plugins_loaded', array( 'CRO_Error_Handler', 'init' ), 1 );
