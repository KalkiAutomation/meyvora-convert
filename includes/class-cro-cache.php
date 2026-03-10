<?php
/**
 * CRO Cache Manager
 *
 * Multi-layer caching for optimal performance
 *
 * @package Meyvora_Convert
 */

defined( 'ABSPATH' ) || exit;

class CRO_Cache {

	/** @var string Cache group */
	const GROUP = 'meyvora_convert';

	/** @var int Default TTL (1 hour) */
	const DEFAULT_TTL = 3600;

	/** @var array Runtime cache */
	private static $runtime_cache = array();

	/**
	 * Get cached value (runtime → object cache → transient).
	 *
	 * @param string $key     Cache key.
	 * @param mixed  $default Default if not found.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$key = self::sanitize_key( $key );

		if ( isset( self::$runtime_cache[ $key ] ) ) {
			return self::$runtime_cache[ $key ];
		}

		$value = wp_cache_get( $key, self::GROUP );

		if ( $value !== false ) {
			self::$runtime_cache[ $key ] = $value;
			return $value;
		}

		$value = get_transient( 'cro_' . $key );

		if ( $value !== false ) {
			self::$runtime_cache[ $key ] = $value;
			wp_cache_set( $key, $value, self::GROUP, self::DEFAULT_TTL );
			return $value;
		}

		return $default;
	}

	/**
	 * Set cached value across all layers.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to store.
	 * @param int|null $ttl TTL in seconds (default DEFAULT_TTL).
	 * @return bool
	 */
	public static function set( $key, $value, $ttl = null ) {
		$key = self::sanitize_key( $key );
		$ttl = $ttl !== null ? (int) $ttl : self::DEFAULT_TTL;

		self::$runtime_cache[ $key ] = $value;
		wp_cache_set( $key, $value, self::GROUP, $ttl );
		set_transient( 'cro_' . $key, $value, $ttl );

		return true;
	}

	/**
	 * Delete cached value from all layers.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public static function delete( $key ) {
		$key = self::sanitize_key( $key );

		unset( self::$runtime_cache[ $key ] );
		wp_cache_delete( $key, self::GROUP );
		delete_transient( 'cro_' . $key );

		return true;
	}

	/**
	 * Clear all CRO cache (runtime, object cache, transients).
	 *
	 * @return bool
	 */
	public static function flush() {
		self::$runtime_cache = array();

		wp_cache_flush();

		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_cro_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_cro_' ) . '%'
			)
		);

		return true;
	}

	/**
	 * Get active campaigns (cached, 5 min).
	 *
	 * @return array
	 */
	public static function get_active_campaigns() {
		$key      = 'active_campaigns';
		$campaigns = self::get( $key );

		if ( $campaigns === null ) {
			global $wpdb;
			$table     = $wpdb->prefix . 'cro_campaigns';
			$campaigns = $wpdb->get_results(
				"SELECT * FROM {$table} WHERE status = 'active' ORDER BY priority DESC",
				ARRAY_A
			);
			$campaigns = is_array( $campaigns ) ? $campaigns : array();
			self::set( $key, $campaigns, 300 );
		}

		return $campaigns;
	}

	/**
	 * Invalidate campaigns-related cache.
	 */
	public static function invalidate_campaigns() {
		self::delete( 'active_campaigns' );
		self::delete( 'campaign_count' );
	}

	/**
	 * Get campaign by ID (cached, 5 min).
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array|null Campaign row or null.
	 */
	public static function get_campaign( $campaign_id ) {
		$campaign_id = (int) $campaign_id;
		$key         = 'campaign_' . $campaign_id;
		$campaign    = self::get( $key );

		if ( $campaign === null ) {
			global $wpdb;
			$table    = $wpdb->prefix . 'cro_campaigns';
			$campaign = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE id = %d",
					$campaign_id
				),
				ARRAY_A
			);
			if ( $campaign ) {
				self::set( $key, $campaign, 300 );
			}
		}

		return $campaign;
	}

	/**
	 * Remember callback result (get or compute and store).
	 *
	 * @param string   $key      Cache key.
	 * @param int      $ttl      TTL in seconds.
	 * @param callable $callback Callback that returns value to cache.
	 * @return mixed
	 */
	public static function remember( $key, $ttl, $callback ) {
		$value = self::get( $key );

		if ( $value === null ) {
			$value = $callback();
			self::set( $key, $value, $ttl );
		}

		return $value;
	}

	/**
	 * Get cache statistics (for debug).
	 *
	 * @return array
	 */
	public static function get_stats() {
		global $wpdb;

		$transient_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_cro_' ) . '%'
			)
		);

		return array(
			'runtime_items'   => count( self::$runtime_cache ),
			'transient_items' => $transient_count,
			'runtime_size'    => strlen( serialize( self::$runtime_cache ) ),
		);
	}

	/**
	 * Sanitize cache key for use in transients and wp_cache.
	 *
	 * @param string $key Raw key.
	 * @return string
	 */
	private static function sanitize_key( $key ) {
		return sanitize_key( (string) $key );
	}
}
