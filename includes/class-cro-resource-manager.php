<?php
/**
 * CRO Resource Manager
 *
 * Manages memory and resource usage
 *
 * @package Meyvora_Convert
 */

defined( 'ABSPATH' ) || exit;

class CRO_Resource_Manager {

	/** @var int|null Memory limit in bytes */
	private static $memory_limit = null;

	/** @var int|null Start memory in bytes */
	private static $start_memory = null;

	/**
	 * Initialize (set memory limit and start memory)
	 */
	public static function init() {
		self::$memory_limit = self::get_memory_limit();
		self::$start_memory = memory_get_usage();
	}

	/**
	 * Get memory limit in bytes (from ini or default 128MB)
	 *
	 * @return int
	 */
	private static function get_memory_limit() {
		$limit = ini_get( 'memory_limit' );
		if ( $limit === false || $limit === '' ) {
			return 128 * 1024 * 1024;
		}
		if ( preg_match( '/^(\d+)(.)$/', $limit, $matches ) ) {
			$value = (int) $matches[1];
			switch ( strtoupper( $matches[2] ) ) {
				case 'G':
					$value *= 1024;
					// Fall through.
				case 'M':
					$value *= 1024;
					// Fall through.
				case 'K':
					$value *= 1024;
					break;
			}
			return $value;
		}
		return 128 * 1024 * 1024;
	}

	/**
	 * Ensure init has run (for lazy use)
	 */
	private static function ensure_init() {
		if ( self::$memory_limit === null ) {
			self::$memory_limit = self::get_memory_limit();
		}
		if ( self::$start_memory === null ) {
			self::$start_memory = memory_get_usage();
		}
	}

	/**
	 * Check if we're approaching memory limit (90% threshold)
	 *
	 * @return bool
	 */
	public static function is_memory_critical() {
		self::ensure_init();
		$current   = memory_get_usage();
		$threshold = self::$memory_limit * 0.9;
		return $current >= $threshold;
	}

	/**
	 * Get memory usage stats
	 *
	 * @return array current, current_mb, peak, peak_mb, limit, limit_mb, used_percent, since_start
	 */
	public static function get_memory_stats() {
		self::ensure_init();
		$current = memory_get_usage();
		$peak    = memory_get_peak_usage();
		$limit   = self::$memory_limit;

		return array(
			'current'       => $current,
			'current_mb'    => round( $current / 1024 / 1024, 2 ),
			'peak'          => $peak,
			'peak_mb'       => round( $peak / 1024 / 1024, 2 ),
			'limit'         => $limit,
			'limit_mb'      => round( $limit / 1024 / 1024, 2 ),
			'used_percent'  => $limit > 0 ? round( ( $current / $limit ) * 100, 1 ) : 0,
			'since_start'   => $current - self::$start_memory,
		);
	}

	/**
	 * Free up memory (flush caches, GC)
	 */
	public static function free_memory() {
		if ( class_exists( 'CRO_Cache' ) && method_exists( 'CRO_Cache', 'flush' ) ) {
			CRO_Cache::flush();
		} else {
			wp_cache_flush();
		}
		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		}
	}

	/**
	 * Get elapsed execution time in seconds
	 *
	 * @return float
	 */
	public static function get_execution_time() {
		$start = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : ( isset( $_SERVER['REQUEST_TIME'] ) ? (int) $_SERVER['REQUEST_TIME'] : microtime( true ) );
		return microtime( true ) - $start;
	}

	/**
	 * Check if we're running too long
	 *
	 * @param int $threshold Seconds (default 25).
	 * @return bool
	 */
	public static function is_time_critical( $threshold = 25 ) {
		return self::get_execution_time() >= (int) $threshold;
	}

	/**
	 * Safe iteration with resource checks (memory and time)
	 *
	 * @param array    $items      Items to iterate.
	 * @param callable $callback   Callback per item (receives item, returns value).
	 * @param int      $batch_size Chunk size.
	 * @return array Results from callback per item.
	 */
	public static function safe_iterate( $items, $callback, $batch_size = 100 ) {
		if ( ! is_array( $items ) || ! is_callable( $callback ) ) {
			return array();
		}
		$chunks  = array_chunk( $items, max( 1, (int) $batch_size ) );
		$results = array();

		foreach ( $chunks as $chunk ) {
			if ( self::is_memory_critical() || self::is_time_critical() ) {
				if ( class_exists( 'CRO_Error_Handler' ) ) {
					CRO_Error_Handler::log( 'WARNING', 'Resource limit reached during iteration' );
				}
				break;
			}
			foreach ( $chunk as $item ) {
				$results[] = $callback( $item );
			}
			if ( self::is_memory_critical() ) {
				self::free_memory();
			}
		}
		return $results;
	}
}

add_action( 'init', array( 'CRO_Resource_Manager', 'init' ), 1 );
