<?php
/**
 * Per-user rolling-window rate limiting for AI actions.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MEYVC_AI_Rate_Limiter
 */
class MEYVC_AI_Rate_Limiter {

	const WINDOW_SECONDS = 3600;

	/**
	 * User meta key for an action.
	 *
	 * @param string $action Action slug.
	 * @return string
	 */
	private static function meta_key( string $action ): string {
		$action = sanitize_key( $action );
		if ( '' === $action ) {
			$action = 'default';
		}
		return 'meyvc_ai_usage_' . $action;
	}

	/**
	 * Current usage record for the logged-in user.
	 *
	 * @param string $action Action slug.
	 * @return array{count: int, window_start: int}
	 */
	private static function get_record( string $action ): array {
		$user_id = get_current_user_id();
		if ( $user_id < 1 ) {
			return array(
				'count'         => 0,
				'window_start'  => time(),
			);
		}
		$raw = get_user_meta( $user_id, self::meta_key( $action ), true );
		if ( ! is_array( $raw ) ) {
			return array(
				'count'         => 0,
				'window_start'  => time(),
			);
		}
		$count        = isset( $raw['count'] ) ? (int) $raw['count'] : 0;
		$window_start = isset( $raw['window_start'] ) ? (int) $raw['window_start'] : time();
		return array(
			'count'        => max( 0, $count ),
			'window_start' => $window_start,
		);
	}

	/**
	 * Normalize record to the current rolling hour (sliding window from window_start).
	 *
	 * @param array{count: int, window_start: int} $record Record.
	 * @return array{count: int, window_start: int}
	 */
	private static function roll_window( array $record ): array {
		$now = time();
		if ( ( $now - (int) $record['window_start'] ) >= self::WINDOW_SECONDS ) {
			return array(
				'count'        => 0,
				'window_start' => $now,
			);
		}
		return $record;
	}

	/**
	 * Whether the user is under the limit for this action.
	 *
	 * @param string $action Action slug.
	 * @param int    $limit  Max requests per window.
	 * @return bool
	 */
	public static function check( string $action, int $limit = 20 ): bool {
		$limit = max( 1, $limit );
		$rec   = self::roll_window( self::get_record( $action ) );
		return $rec['count'] < $limit;
	}

	/**
	 * Increment usage after a successful AI call.
	 *
	 * @param string $action Action slug.
	 */
	public static function increment( string $action ): void {
		$user_id = get_current_user_id();
		if ( $user_id < 1 ) {
			return;
		}
		$rec = self::roll_window( self::get_record( $action ) );
		++$rec['count'];
		update_user_meta( $user_id, self::meta_key( $action ), $rec );
	}

	/**
	 * Remaining calls in the current window.
	 *
	 * @param string $action Action slug.
	 * @param int    $limit  Max requests per window.
	 * @return int
	 */
	public static function get_remaining( string $action, int $limit = 20 ): int {
		$limit = max( 1, $limit );
		$rec   = self::roll_window( self::get_record( $action ) );
		return max( 0, $limit - (int) $rec['count'] );
	}

	/**
	 * Unix timestamp when the current window resets (end of rolling hour).
	 *
	 * @param string $action Action slug.
	 * @return int
	 */
	public static function get_reset_time( string $action ): int {
		$rec = self::roll_window( self::get_record( $action ) );
		return (int) $rec['window_start'] + self::WINDOW_SECONDS;
	}

	/**
	 * User meta key for minimum interval between calls (separate from hourly rolling count).
	 *
	 * @param string $action Action slug.
	 * @return string
	 */
	private static function cooldown_meta_key( string $action ): string {
		$action = sanitize_key( $action );
		if ( '' === $action ) {
			$action = 'default';
		}
		return 'meyvc_ai_last_ts_' . $action;
	}

	/**
	 * Whether at least $min_seconds have passed since the last cooldown_set() for this user/action.
	 *
	 * @param string $action       Action slug.
	 * @param int    $min_seconds Minimum seconds between calls.
	 * @return bool True if a new call is allowed.
	 */
	public static function cooldown_ok( string $action, int $min_seconds = 60 ): bool {
		$min_seconds = max( 1, $min_seconds );
		$user_id     = get_current_user_id();
		if ( $user_id < 1 ) {
			return false;
		}
		$last = (int) get_user_meta( $user_id, self::cooldown_meta_key( $action ), true );
		if ( $last <= 0 ) {
			return true;
		}
		return ( time() - $last ) >= $min_seconds;
	}

	/**
	 * Record that an action was performed now (for cooldown_ok).
	 *
	 * @param string $action Action slug.
	 */
	public static function cooldown_set( string $action ): void {
		$user_id = get_current_user_id();
		if ( $user_id < 1 ) {
			return;
		}
		update_user_meta( $user_id, self::cooldown_meta_key( $action ), time() );
	}

	/**
	 * Seconds until cooldown_ok becomes true (0 if allowed now).
	 *
	 * @param string $action       Action slug.
	 * @param int    $min_seconds Minimum seconds between calls.
	 * @return int
	 */
	public static function cooldown_remaining( string $action, int $min_seconds = 60 ): int {
		$min_seconds = max( 1, $min_seconds );
		$user_id     = get_current_user_id();
		if ( $user_id < 1 ) {
			return $min_seconds;
		}
		$last = (int) get_user_meta( $user_id, self::cooldown_meta_key( $action ), true );
		if ( $last <= 0 ) {
			return 0;
		}
		$elapsed = time() - $last;
		if ( $elapsed >= $min_seconds ) {
			return 0;
		}
		return $min_seconds - $elapsed;
	}
}
