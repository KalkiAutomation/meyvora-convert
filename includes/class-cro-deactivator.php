<?php
/**
 * Fired during plugin deactivation
 *
 * @package Meyvora_Convert
 */

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
		wp_clear_scheduled_hook( 'cro_daily_cleanup' );
		wp_clear_scheduled_hook( 'cro_analytics_aggregate' );
		wp_clear_scheduled_hook( 'cro_daily_analytics' );

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
