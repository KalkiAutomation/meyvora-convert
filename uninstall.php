<?php
/**
 * Fired when the plugin is uninstalled (deleted).
 * Removes all plugin data only if the option cro_remove_data_on_uninstall is set to 'yes'.
 *
 * @package Meyvora_Convert
 */
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( get_option( 'cro_remove_data_on_uninstall' ) !== 'yes' ) {
	return;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'cro_campaigns',
	$wpdb->prefix . 'cro_events',
	$wpdb->prefix . 'cro_emails',
	$wpdb->prefix . 'cro_settings',
	$wpdb->prefix . 'cro_ab_tests',
	$wpdb->prefix . 'cro_ab_variations',
	$wpdb->prefix . 'cro_ab_assignments',
	$wpdb->prefix . 'cro_daily_stats',
	$wpdb->prefix . 'cro_offers',
	$wpdb->prefix . 'cro_offer_logs',
	$wpdb->prefix . 'cro_abandoned_carts',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}

$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'cro_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cro_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_cro_%'" );
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'cro_%'" );

wp_clear_scheduled_hook( 'cro_daily_cleanup' );
wp_clear_scheduled_hook( 'cro_process_background_queue' );
wp_clear_scheduled_hook( 'cro_cleanup_old_events' );
wp_clear_scheduled_hook( 'cro_aggregate_daily_stats' );
wp_clear_scheduled_hook( 'cro_check_ab_winners' );
wp_clear_scheduled_hook( 'cro_send_abandoned_cart_reminders' );

delete_option( 'cro_dynamic_offers' );
delete_option( 'cro_remove_data_on_uninstall' );
