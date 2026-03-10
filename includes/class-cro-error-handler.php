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
		self::$log_file  = WP_CONTENT_DIR . '/meyvora-convert-errors.log';
		self::$debug_mode = defined( 'WP_DEBUG' ) && WP_DEBUG;

		// Set custom error handler for CRO operations
		set_error_handler( array( __CLASS__, 'handle_error' ), E_ALL );

		// Register shutdown function for fatal errors
		register_shutdown_function( array( __CLASS__, 'handle_shutdown' ) );
	}

	/**
	 * Custom error handler
	 *
	 * @param int    $severity Error severity.
	 * @param string $message  Error message.
	 * @param string $file     File path.
	 * @param int    $line     Line number.
	 * @return bool
	 */
	public static function handle_error( $severity, $message, $file, $line ) {
		// Only handle errors from our plugin
		if ( strpos( $file, 'meyvora-convert' ) === false ) {
			return false; // Let PHP handle it
		}

		$error_type = self::get_error_type( $severity );

		// Log the error
		self::log( $error_type, $message, array(
			'file' => $file,
			'line' => $line,
		) );

		// In debug mode, also trigger PHP's error handler
		if ( self::$debug_mode ) {
			return false;
		}

		// Suppress non-fatal errors in production
		return true;
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
	 * Log error/warning/info
	 *
	 * @param string $level   Log level.
	 * @param string $message Message.
	 * @param array  $context Optional context.
	 */
	public static function log( $level, $message, $context = array() ) {
		// Rate limiting: max 100 errors per hour
		$hour = date( 'Y-m-d-H' );
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

		// Write to log file
		if ( is_writable( dirname( self::$log_file ) ) ) {
			file_put_contents( self::$log_file, $log_entry, FILE_APPEND | LOCK_EX );

			// Rotate log if too large (> 5MB)
			if ( file_exists( self::$log_file ) && filesize( self::$log_file ) > 5 * 1024 * 1024 ) {
				self::rotate_log();
			}
		}

		// Also log to WordPress debug.log if enabled
		if ( self::$debug_mode ) {
			error_log( "[Meyvora Convert] [{$level}] {$message}" );
		}
	}

	/**
	 * Rotate log file
	 */
	private static function rotate_log() {
		$backup = self::$log_file . '.' . date( 'Y-m-d-His' ) . '.bak';
		rename( self::$log_file, $backup );

		// Keep only last 3 backups
		$backups = glob( self::$log_file . '.*.bak' );
		if ( is_array( $backups ) && count( $backups ) > 3 ) {
			usort( $backups, function( $a, $b ) {
				return (int) filemtime( $a ) - (int) filemtime( $b );
			} );
			$to_remove = array_slice( $backups, 0, -3 );
			foreach ( $to_remove as $path ) {
				if ( is_file( $path ) ) {
					unlink( $path );
				}
			}
		}
	}

	/**
	 * Get error type name
	 *
	 * @param int $severity Error severity constant.
	 * @return string
	 */
	private static function get_error_type( $severity ) {
		$types = array(
			E_ERROR             => 'ERROR',
			E_WARNING           => 'WARNING',
			E_NOTICE            => 'NOTICE',
			E_DEPRECATED        => 'DEPRECATED',
			E_USER_ERROR        => 'USER_ERROR',
			E_USER_WARNING      => 'USER_WARNING',
			E_USER_NOTICE       => 'USER_NOTICE',
		);

		return $types[ $severity ] ?? 'UNKNOWN';
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
