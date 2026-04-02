<?php
/**
 * GDPR Privacy API integration.
 * Registers personal data exporter and eraser with WordPress Tools → Privacy.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MEYVC_Privacy {

    /**
     * @return void
     */
    private static function privacy_flush_read_cache() {
        if ( function_exists( 'wp_cache_flush_group' ) ) {
            wp_cache_flush_group( 'meyvora_meyvc' );
        }
    }

    public static function init() {
        add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
        add_filter( 'wp_privacy_personal_data_erasers',   array( __CLASS__, 'register_eraser' ) );
    }

    public static function register_exporter( $exporters ) {
        $exporters['meyvora-convert'] = array(
            'exporter_friendly_name' => __( 'Meyvora Convert', 'meyvora-convert' ),
            'callback'               => array( __CLASS__, 'export_user_data' ),
        );
        return $exporters;
    }

    public static function register_eraser( $erasers ) {
        $erasers['meyvora-convert'] = array(
            'eraser_friendly_name' => __( 'Meyvora Convert', 'meyvora-convert' ),
            'callback'             => array( __CLASS__, 'erase_user_data' ),
        );
        return $erasers;
    }

    public static function export_user_data( $email_address, $page = 1 ) {
        global $wpdb;
        $export_items = array();

        // Export abandoned cart records
        $table = $wpdb->prefix . 'meyvc_abandoned_carts';
        $cache_key_abandoned_table = 'meyvora_meyvc_' . md5( serialize( array( 'privacy_show_table_abandoned_export', $table ) ) );
        $abandoned_exists          = wp_cache_get( $cache_key_abandoned_table, 'meyvora_meyvc' );
        if ( false === $abandoned_exists ) {
            $abandoned_exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
                $wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
            );
            wp_cache_set( $cache_key_abandoned_table, $abandoned_exists, 'meyvora_meyvc', 300 );
        }
        if ( $abandoned_exists === $table ) {
            $cache_key_abandoned_rows = 'meyvora_meyvc_' . md5( serialize( array( 'privacy_export_abandoned_by_email', $table, $email_address ) ) );
            $rows                     = wp_cache_get( $cache_key_abandoned_rows, 'meyvora_meyvc' );
            if ( false === $rows ) {
                $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
                    $wpdb->prepare(
                        'SELECT id, cart_total, status, created_at, last_activity_at FROM %i WHERE email = %s',
                        $table,
                        $email_address
                    )
                );
                if ( ! is_array( $rows ) ) {
                    $rows = array();
                }
                wp_cache_set( $cache_key_abandoned_rows, $rows, 'meyvora_meyvc', 300 );
            } else {
                $rows = is_array( $rows ) ? $rows : array();
            }
            foreach ( $rows as $row ) {
                $export_items[] = array(
                    'group_id'    => 'meyvc_abandoned_carts',
                    'group_label' => __( 'Meyvora Convert — Abandoned Carts', 'meyvora-convert' ),
                    'item_id'     => 'abandoned-cart-' . $row->id,
                    'data'        => array(
                        array( 'name' => __( 'Cart ID', 'meyvora-convert' ),       'value' => $row->id ),
                        array( 'name' => __( 'Cart Total', 'meyvora-convert' ),    'value' => $row->cart_total ),
                        array( 'name' => __( 'Status', 'meyvora-convert' ),        'value' => $row->status ),
                        array( 'name' => __( 'Created', 'meyvora-convert' ),       'value' => $row->created_at ),
                        array( 'name' => __( 'Last Activity', 'meyvora-convert' ), 'value' => $row->last_activity_at ),
                    ),
                );
            }
        }

        // Export captured emails
        $table_emails = $wpdb->prefix . 'meyvc_emails';
        $cache_key_emails_table = 'meyvora_meyvc_' . md5( serialize( array( 'privacy_show_table_captured_emails_export', $table_emails ) ) );
        $emails_exists          = wp_cache_get( $cache_key_emails_table, 'meyvora_meyvc' );
        if ( false === $emails_exists ) {
            $emails_exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
                $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_emails )
            );
            wp_cache_set( $cache_key_emails_table, $emails_exists, 'meyvora_meyvc', 300 );
        }
        if ( $emails_exists === $table_emails ) {
            $cache_key_captured_rows = 'meyvora_meyvc_' . md5( serialize( array( 'privacy_export_captured_emails', $table_emails, $email_address ) ) );
            $rows                    = wp_cache_get( $cache_key_captured_rows, 'meyvora_meyvc' );
            if ( false === $rows ) {
                $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
                    $wpdb->prepare(
                        'SELECT id, created_at FROM %i WHERE email = %s',
                        $table_emails,
                        $email_address
                    )
                );
                if ( ! is_array( $rows ) ) {
                    $rows = array();
                }
                wp_cache_set( $cache_key_captured_rows, $rows, 'meyvora_meyvc', 300 );
            } else {
                $rows = is_array( $rows ) ? $rows : array();
            }
            foreach ( $rows as $row ) {
                $export_items[] = array(
                    'group_id'    => 'meyvc_emails',
                    'group_label' => __( 'Meyvora Convert — Captured Emails', 'meyvora-convert' ),
                    'item_id'     => 'meyvc-email-' . $row->id,
                    'data'        => array(
                        array( 'name' => __( 'Email', 'meyvora-convert' ),   'value' => $email_address ),
                        array( 'name' => __( 'Captured', 'meyvora-convert' ), 'value' => $row->created_at ),
                    ),
                );
            }
        }

        // Export AI usage meta (logged-in users).
        $user = get_user_by( 'email', $email_address );
        if ( $user ) {
            $ai_actions = array( 'copy_generate', 'insights_analyse', 'offer_suggest', 'ab_hypothesis', 'chat', 'abandoned_email_preview' );
            $ai_items   = array();
            foreach ( $ai_actions as $action ) {
                $usage = get_user_meta( $user->ID, 'meyvc_ai_usage_' . $action, true );
                if ( is_array( $usage ) && isset( $usage['count'] ) && (int) $usage['count'] > 0 ) {
                    $ai_items[] = array(
                        'name'  => sprintf(
                            /* translators: %s: internal AI action key (e.g. copy_generate, chat). */
                            __( 'AI requests (%s)', 'meyvora-convert' ),
                            $action
                        ),
                        'value' => (int) $usage['count'],
                    );
                }
            }
            if ( $ai_items ) {
                $export_items[] = array(
                    'group_id'    => 'meyvc_ai_usage',
                    'group_label' => __( 'Meyvora Convert — AI Usage', 'meyvora-convert' ),
                    'item_id'     => 'meyvc-ai-' . $user->ID,
                    'data'        => $ai_items,
                );
            }
        }

        // Export analytics events by user_id
        if ( $user ) {
            $table_events = $wpdb->prefix . 'meyvc_events';
            $cache_key_events_table_export = 'meyvora_meyvc_' . md5( serialize( array( 'privacy_show_table_events_export', $table_events ) ) );
            $events_exists                 = wp_cache_get( $cache_key_events_table_export, 'meyvora_meyvc' );
            if ( false === $events_exists ) {
                $events_exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
                    $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_events )
                );
                wp_cache_set( $cache_key_events_table_export, $events_exists, 'meyvora_meyvc', 300 );
            }
            if ( $events_exists === $table_events ) {
                $cache_key_events_rows = 'meyvora_meyvc_' . md5( serialize( array( 'privacy_export_events_by_user', $table_events, $user->ID ) ) );
                $rows                  = wp_cache_get( $cache_key_events_rows, 'meyvora_meyvc' );
                if ( false === $rows ) {
                    $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
                        $wpdb->prepare(
                            'SELECT id, event_type, page_url, created_at FROM %i WHERE user_id = %d LIMIT 100',
                            $table_events,
                            $user->ID
                        )
                    );
                    if ( ! is_array( $rows ) ) {
                        $rows = array();
                    }
                    wp_cache_set( $cache_key_events_rows, $rows, 'meyvora_meyvc', 300 );
                } else {
                    $rows = is_array( $rows ) ? $rows : array();
                }
                foreach ( $rows as $row ) {
                    $export_items[] = array(
                        'group_id'    => 'meyvc_events',
                        'group_label' => __( 'Meyvora Convert — Analytics Events', 'meyvora-convert' ),
                        'item_id'     => 'meyvc-event-' . $row->id,
                        'data'        => array(
                            array( 'name' => __( 'Event Type', 'meyvora-convert' ), 'value' => $row->event_type ),
                            array( 'name' => __( 'Page URL', 'meyvora-convert' ),   'value' => $row->page_url ),
                            array( 'name' => __( 'Date', 'meyvora-convert' ),       'value' => $row->created_at ),
                        ),
                    );
                }
            }
        }

        return array( 'data' => $export_items, 'done' => true );
    }

    public static function erase_user_data( $email_address, $page = 1 ) {
        global $wpdb;
        $items_removed  = 0;
        $items_retained = 0;
        $messages       = array();

        // Anonymize abandoned cart rows (keep for order recovery stats, remove PII)
        $table = $wpdb->prefix . 'meyvc_abandoned_carts';
        $cache_key_abandoned_table_erase = 'meyvora_meyvc_' . md5( serialize( array( 'privacy_show_table_abandoned_erase', $table ) ) );
        $abandoned_exists                = wp_cache_get( $cache_key_abandoned_table_erase, 'meyvora_meyvc' );
        if ( false === $abandoned_exists ) {
            $abandoned_exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
                $wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
            );
            wp_cache_set( $cache_key_abandoned_table_erase, $abandoned_exists, 'meyvora_meyvc', 300 );
        }
        if ( $abandoned_exists === $table ) {
            $cache_key_abandoned_count = 'meyvora_meyvc_' . md5( serialize( array( 'privacy_erase_abandoned_count_by_email', $table, $email_address ) ) );
            $count_raw                  = wp_cache_get( $cache_key_abandoned_count, 'meyvora_meyvc' );
            if ( false === $count_raw ) {
                $count_raw = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
                    $wpdb->prepare(
                        'SELECT COUNT(*) FROM %i WHERE email = %s', $table, $email_address
                    )
                );
                wp_cache_set( $cache_key_abandoned_count, $count_raw, 'meyvora_meyvc', 300 );
            }
            $count = (int) $count_raw;
            if ( $count > 0 ) {
                $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
                    $wpdb->prepare(
                        'UPDATE %i SET email = %s, user_id = 0, cart_contents = %s WHERE email = %s',
                        $table,
                        '[deleted]', '{}', $email_address
                    ) );
                if ( class_exists( 'MEYVC_Database' ) ) {
                    MEYVC_Database::invalidate_table_cache_after_write( $table );
                }
                self::privacy_flush_read_cache();
                $items_removed += $count;
            }
        }

        // Hard-delete captured emails
        $table_emails = $wpdb->prefix . 'meyvc_emails';
        $cache_key_emails_table_erase = 'meyvora_meyvc_' . md5( serialize( array( 'privacy_show_table_captured_emails_erase', $table_emails ) ) );
        $emails_exists                = wp_cache_get( $cache_key_emails_table_erase, 'meyvora_meyvc' );
        if ( false === $emails_exists ) {
            $emails_exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
                $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_emails )
            );
            wp_cache_set( $cache_key_emails_table_erase, $emails_exists, 'meyvora_meyvc', 300 );
        }
        if ( $emails_exists === $table_emails ) {
            $deleted = $wpdb->delete( $table_emails, array( 'email' => $email_address ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
            if ( false !== $deleted ) {
                if ( class_exists( 'MEYVC_Database' ) ) {
                    MEYVC_Database::invalidate_table_cache_after_write( $table_emails );
                }
                self::privacy_flush_read_cache();
            }
            $items_removed += (int) $deleted;
        }

        // Delete analytics events by user_id; erase AI usage meta.
        $user = get_user_by( 'email', $email_address );
        if ( $user ) {
            $ai_actions = array( 'copy_generate', 'insights_analyse', 'offer_suggest', 'ab_hypothesis', 'chat', 'abandoned_email_preview' );
            foreach ( $ai_actions as $action ) {
                delete_user_meta( $user->ID, 'meyvc_ai_usage_' . $action );
                delete_user_meta( $user->ID, 'meyvc_ai_last_ts_' . $action );
            }

            $table_events = $wpdb->prefix . 'meyvc_events';
            $cache_key_events_table_erase = 'meyvora_meyvc_' . md5( serialize( array( 'privacy_show_table_events_erase', $table_events ) ) );
            $events_exists                  = wp_cache_get( $cache_key_events_table_erase, 'meyvora_meyvc' );
            if ( false === $events_exists ) {
                $events_exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
                    $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_events )
                );
                wp_cache_set( $cache_key_events_table_erase, $events_exists, 'meyvora_meyvc', 300 );
            }
            if ( $events_exists === $table_events ) {
                $deleted = $wpdb->delete( $table_events, array( 'user_id' => $user->ID ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
                if ( false !== $deleted ) {
                    if ( class_exists( 'MEYVC_Database' ) ) {
                        MEYVC_Database::invalidate_table_cache_after_write( $table_events );
                    }
                    self::privacy_flush_read_cache();
                }
                $items_removed += (int) $deleted;
            }

            $table_assign = $wpdb->prefix . 'meyvc_ab_assignments';
            $cache_key_assign_table_erase = 'meyvora_meyvc_' . md5( serialize( array( 'privacy_show_table_ab_assignments_erase', $table_assign ) ) );
            $assign_exists                  = wp_cache_get( $cache_key_assign_table_erase, 'meyvora_meyvc' );
            if ( false === $assign_exists ) {
                $assign_exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
                    $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_assign )
                );
                wp_cache_set( $cache_key_assign_table_erase, $assign_exists, 'meyvora_meyvc', 300 );
            }
            if ( $assign_exists === $table_assign ) {
                $deleted = $wpdb->delete( $table_assign, array( 'user_id' => $user->ID ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
                if ( false !== $deleted ) {
                    if ( class_exists( 'MEYVC_Database' ) ) {
                        MEYVC_Database::invalidate_table_cache_after_write( $table_assign );
                    }
                    self::privacy_flush_read_cache();
                }
                $items_removed += (int) $deleted;
            }
        }

        return array(
            'items_removed'  => $items_removed,
            'items_retained' => $items_retained,
            'messages'       => $messages,
            'done'           => true,
        );
    }
}
