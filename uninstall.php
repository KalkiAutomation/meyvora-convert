<?php
/**
 * Fired when the plugin is uninstalled (deleted).
 * Removes all plugin data only if the option cro_remove_data_on_uninstall is set to 'yes'.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( get_option( 'cro_remove_data_on_uninstall' ) !== 'yes' ) {
	return;
}

( function () {
	global $wpdb;

	$table_suffixes = array(
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

	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name LIKE %s', $wpdb->options, 'cro_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional DROP TABLE in uninstall; table name sanitized.
	if ( function_exists( 'wp_cache_flush_group' ) ) {
		wp_cache_flush_group( 'meyvora_cro' );
	} else {
		wp_cache_delete( 'meyvora_cro_uninstall_' . md5( 'delete_options_cro_' ), 'meyvora_cro' );
	}

	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name LIKE %s', $wpdb->options, '_transient_cro_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional DROP TABLE in uninstall; table name sanitized.
	if ( function_exists( 'wp_cache_flush_group' ) ) {
		wp_cache_flush_group( 'meyvora_cro' );
	} else {
		wp_cache_delete( 'meyvora_cro_uninstall_' . md5( 'delete_options_transient_cro_' ), 'meyvora_cro' );
	}

	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name LIKE %s', $wpdb->options, '_transient_timeout_cro_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional DROP TABLE in uninstall; table name sanitized.
	if ( function_exists( 'wp_cache_flush_group' ) ) {
		wp_cache_flush_group( 'meyvora_cro' );
	} else {
		wp_cache_delete( 'meyvora_cro_uninstall_' . md5( 'delete_options_transient_timeout_cro_' ), 'meyvora_cro' );
	}

	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE meta_key LIKE %s', $wpdb->usermeta, 'cro_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional DROP TABLE in uninstall; table name sanitized.
	if ( function_exists( 'wp_cache_flush_group' ) ) {
		wp_cache_flush_group( 'meyvora_cro' );
	} else {
		wp_cache_delete( 'meyvora_cro_uninstall_' . md5( 'delete_usermeta_cro_' ), 'meyvora_cro' );
	}
} )();

wp_clear_scheduled_hook( 'cro_daily_cleanup' );
wp_clear_scheduled_hook( 'cro_process_background_queue' );
wp_clear_scheduled_hook( 'cro_cleanup_old_events' );
wp_clear_scheduled_hook( 'cro_aggregate_daily_stats' );
wp_clear_scheduled_hook( 'cro_check_ab_winners' );
wp_clear_scheduled_hook( 'cro_send_abandoned_cart_reminders' );
wp_clear_scheduled_hook( 'cro_deliver_webhook' );

delete_option( 'cro_dynamic_offers' );
delete_option( 'cro_remove_data_on_uninstall' );

$upload_dir = wp_upload_dir();
if ( empty( $upload_dir['error'] ) && ! empty( $upload_dir['basedir'] ) ) {
	$log_dir = trailingslashit( $upload_dir['basedir'] ) . 'meyvora-convert/';
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	WP_Filesystem();
	global $wp_filesystem;
	if ( $wp_filesystem && $wp_filesystem->exists( $log_dir ) ) {
		$wp_filesystem->delete( $log_dir, true );
	}
}
foreach ( glob( WP_CONTENT_DIR . '/meyvora-convert-errors.log*.bak' ) ?: array() as $f ) {
	if ( is_string( $f ) && $f !== '' ) {
		wp_delete_file( $f );
	}
}
foreach ( array( WP_CONTENT_DIR . '/meyvora-convert-errors.log' ) as $f ) {
	if ( is_string( $f ) && $f !== '' && is_file( $f ) ) {
		wp_delete_file( $f );
	}
}
