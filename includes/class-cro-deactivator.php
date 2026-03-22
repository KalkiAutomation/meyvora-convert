<?php
/**
 * Fired during plugin deactivation
 *
 * @package Meyvora_Convert
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Fired during plugin deactivation.
 */
class CRO_Deactivator {

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
		wp_clear_scheduled_hook( 'cro_process_background_queue' );
		wp_clear_scheduled_hook( 'cro_cleanup_old_events' );
		wp_clear_scheduled_hook( 'cro_aggregate_daily_stats' );
		wp_clear_scheduled_hook( 'cro_check_ab_winners' );
		wp_clear_scheduled_hook( 'cro_send_abandoned_cart_reminders' );

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

		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_cro_%'
			OR option_name LIKE '_transient_timeout_cro_%'"
		);
	}
}
