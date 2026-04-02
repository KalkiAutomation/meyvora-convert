<?php
/**
 * CRO Cache Manager
 *
 * Multi-layer caching for optimal performance
 *
 * @package Meyvora_Convert
 */

defined( 'ABSPATH' ) || exit;

class MEYVC_Cache {

	/** @var string Cache group */
	const GROUP = 'meyvora_convert';

	/** @var string Object cache group for read-through DB queries (PHPCS). */
	private const DB_READ_CACHE_GROUP = 'meyvora_meyvc';

	/** @var int Read-through TTL for DB queries (seconds). */
	private const DB_READ_CACHE_TTL = 300;

	/** @var int Default TTL (1 hour) */
	const DEFAULT_TTL = 3600;

	/** @var array Runtime cache */
	private static $runtime_cache = array();

	/**
	 * @param string                    $descriptor 2–4 word slug.
	 * @param array<int|string|float> $params     Params.
	 * @return string
	 */
	private static function read_cache_key( string $descriptor, array $params ): string {
		return 'meyvora_meyvc_' . md5( $descriptor . '_' . implode( '_', array_map( 'strval', $params ) ) );
	}

	/**
	 * @return void
	 */
	private static function flush_meyvora_meyvc_read_cache() {
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::DB_READ_CACHE_GROUP );
		}
	}

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

		$value = get_transient( 'meyvc_' . $key );

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
		set_transient( 'meyvc_' . $key, $value, $ttl );

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
		delete_transient( 'meyvc_' . $key );

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
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
			$wpdb->prepare(
				'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s',
				$wpdb->options,
				$wpdb->esc_like( '_transient_meyvc_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_meyvc_' ) . '%'
			)
		);
		self::flush_meyvora_meyvc_read_cache();

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
			$table      = $wpdb->prefix . 'meyvc_campaigns';
			$cache_key  = self::read_cache_key( 'active_campaigns', array( $table ) );
			$campaigns  = wp_cache_get( $cache_key, self::DB_READ_CACHE_GROUP );
			if ( false === $campaigns ) {
				$campaigns = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
					$wpdb->prepare(
						'SELECT * FROM %i WHERE status = %s ORDER BY priority DESC',
						$table,
						'active'
					),
					ARRAY_A
				);
				if ( ! is_array( $campaigns ) ) {
					$campaigns = array();
				}
				wp_cache_set( $cache_key, $campaigns, self::DB_READ_CACHE_GROUP, self::DB_READ_CACHE_TTL );
			}
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
		self::flush_meyvora_meyvc_read_cache();
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
			$table      = $wpdb->prefix . 'meyvc_campaigns';
			$cache_key  = self::read_cache_key( 'campaign_by_id', array( $campaign_id ) );
			$found      = false;
			$campaign   = wp_cache_get( $cache_key, self::DB_READ_CACHE_GROUP, false, $found );
			if ( ! $found ) {
				$campaign = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
					$wpdb->prepare(
						'SELECT * FROM %i WHERE id = %d',
						$table,
						$campaign_id
					),
					ARRAY_A
				);
				wp_cache_set( $cache_key, $campaign, self::DB_READ_CACHE_GROUP, self::DB_READ_CACHE_TTL );
			}
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

		$like_pattern = $wpdb->esc_like( '_transient_meyvc_' ) . '%';
		$cache_key    = self::read_cache_key( 'options_transient_meyvc_count', array( $wpdb->options, $like_pattern ) );
		$found        = false;
		$transient_count = wp_cache_get( $cache_key, self::DB_READ_CACHE_GROUP, false, $found );
		if ( ! $found ) {
			$transient_count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE option_name LIKE %s',
					$wpdb->options,
					$like_pattern
				)
			);
			wp_cache_set( $cache_key, $transient_count, self::DB_READ_CACHE_GROUP, self::DB_READ_CACHE_TTL );
		} else {
			$transient_count = (int) $transient_count;
		}

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
