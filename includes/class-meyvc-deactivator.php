<?php
/**
 * Fired during plugin deactivation
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin deactivation.
 */
class MEYVC_Deactivator {

	/**
	 * Deactivate the plugin.
	 */
	public static function deactivate() {
		// Remove custom capability on deactivation.
		foreach ( array( 'administrator', 'shop_manager' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				$role->remove_cap( 'manage_meyvora_convert' );
			}
		}

		// Clear scheduled events.
		wp_clear_scheduled_hook( 'meyvc_daily_cleanup' );
		wp_clear_scheduled_hook( 'meyvc_process_background_queue' );
		wp_clear_scheduled_hook( 'meyvc_cleanup_old_events' );
		wp_clear_scheduled_hook( 'meyvc_aggregate_daily_stats' );
		wp_clear_scheduled_hook( 'meyvc_check_ab_winners' );
		wp_clear_scheduled_hook( 'meyvc_send_abandoned_cart_reminders' );
		wp_clear_scheduled_hook( 'meyvc_deliver_webhook' );

		// Clear CRO transients.
		self::clear_transients();

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Note: Do not delete database tables or options on deactivate.
		// Only do that on uninstall.
	}

	/**
	 * Delete all CRO-related transients.
	 */
	private static function clear_transients() {
		global $wpdb;

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_meyvc_%'
			OR option_name LIKE '_transient_timeout_meyvc_%'"
		);
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'meyvora_meyvc' );
		}
	}
}
