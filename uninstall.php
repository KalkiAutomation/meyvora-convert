<?php
/**
 * Fired when the plugin is uninstalled (deleted).
 * Removes all plugin data only if the option meyvc_remove_data_on_uninstall is set to 'yes'.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( get_option( 'meyvc_remove_data_on_uninstall' ) !== 'yes' ) {
	return;
}

( function () {
	global $wpdb;

	$table_suffixes = array(
		'meyvc_campaigns',
		'meyvc_events',
		'meyvc_emails',
		'meyvc_settings',
		'meyvc_ab_tests',
		'meyvc_ab_variations',
		'meyvc_ab_assignments',
		'meyvc_daily_stats',
		'meyvc_offers',
		'meyvc_offer_logs',
		'meyvc_abandoned_carts',
		'meyvc_campaign_sequences',
		'meyvc_sequence_enrollments',
		// Legacy table names (pre–2.0.0 prefix migration).
		'cro_campaigns',
		'cro_events',
		'cro_emails',
		'cro_settings',
		'cro_ab_tests',
		'cro_ab_variations',
		'cro_ab_assignments',
		'cro_daily_stats',
		'cro_offers',
		'cro_offer_logs',
		'cro_abandoned_carts',
		'cro_campaign_sequences',
		'cro_sequence_enrollments',
	);

	foreach ( $table_suffixes as $suffix ) {
		$table = esc_sql( $wpdb->prefix . sanitize_key( $suffix ) ); // Sanitize table name before use in SQL.
		$wpdb->query( 'DROP TABLE IF EXISTS `' . $table . '`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- Intentional DROP TABLE in uninstall; table name sanitized via esc_sql() and sanitize_key().
	}

	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}

	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name LIKE %s', $wpdb->options, 'meyvc_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional DROP TABLE in uninstall; table name sanitized.
	if ( function_exists( 'wp_cache_flush_group' ) ) {
		wp_cache_flush_group( 'meyvora_meyvc' );
	} else {
		wp_cache_delete( 'meyvora_meyvc_uninstall_' . md5( 'delete_options_meyvc_' ), 'meyvora_meyvc' );
	}

	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name LIKE %s', $wpdb->options, '_transient_meyvc_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional DROP TABLE in uninstall; table name sanitized.
	if ( function_exists( 'wp_cache_flush_group' ) ) {
		wp_cache_flush_group( 'meyvora_meyvc' );
	} else {
		wp_cache_delete( 'meyvora_meyvc_uninstall_' . md5( 'delete_options_transient_meyvc_' ), 'meyvora_meyvc' );
	}

	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name LIKE %s', $wpdb->options, '_transient_timeout_meyvc_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional DROP TABLE in uninstall; table name sanitized.
	if ( function_exists( 'wp_cache_flush_group' ) ) {
		wp_cache_flush_group( 'meyvora_meyvc' );
	} else {
		wp_cache_delete( 'meyvora_meyvc_uninstall_' . md5( 'delete_options_transient_timeout_meyvc_' ), 'meyvora_meyvc' );
	}

	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE meta_key LIKE %s', $wpdb->usermeta, 'meyvc_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional DROP TABLE in uninstall; table name sanitized.
	if ( function_exists( 'wp_cache_flush_group' ) ) {
		wp_cache_flush_group( 'meyvora_meyvc' );
	} else {
		wp_cache_delete( 'meyvora_meyvc_uninstall_' . md5( 'delete_usermeta_meyvc_' ), 'meyvora_meyvc' );
	}
} )();

wp_clear_scheduled_hook( 'meyvc_daily_cleanup' );
wp_clear_scheduled_hook( 'meyvc_process_background_queue' );
wp_clear_scheduled_hook( 'meyvc_cleanup_old_events' );
wp_clear_scheduled_hook( 'meyvc_aggregate_daily_stats' );
wp_clear_scheduled_hook( 'meyvc_check_ab_winners' );
wp_clear_scheduled_hook( 'meyvc_send_abandoned_cart_reminders' );
wp_clear_scheduled_hook( 'meyvc_deliver_webhook' );

delete_option( 'meyvc_dynamic_offers' );
delete_option( 'meyvc_remove_data_on_uninstall' );

$meyvc_upload_dir = wp_upload_dir();
if ( empty( $meyvc_upload_dir['error'] ) && ! empty( $meyvc_upload_dir['basedir'] ) ) {
	$meyvc_log_dir = trailingslashit( $meyvc_upload_dir['basedir'] ) . 'meyvora-convert/';
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	WP_Filesystem();
	global $wp_filesystem;
	if ( $wp_filesystem && $wp_filesystem->exists( $meyvc_log_dir ) ) {
		$wp_filesystem->delete( $meyvc_log_dir, true );
	}
}
