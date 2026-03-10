<?php
/**
 * GDPR Privacy API integration.
 * Registers personal data exporter and eraser with WordPress Tools → Privacy.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class CRO_Privacy {

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
        $table = $wpdb->prefix . 'cro_abandoned_carts';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, cart_total, status, created_at, last_activity_at FROM {$table} WHERE email = %s",
                $email_address
            ) );
            foreach ( $rows as $row ) {
                $export_items[] = array(
                    'group_id'    => 'cro_abandoned_carts',
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
        $table_emails = $wpdb->prefix . 'cro_emails';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_emails ) ) === $table_emails ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, created_at FROM {$table_emails} WHERE email = %s",
                $email_address
            ) );
            foreach ( $rows as $row ) {
                $export_items[] = array(
                    'group_id'    => 'cro_emails',
                    'group_label' => __( 'Meyvora Convert — Captured Emails', 'meyvora-convert' ),
                    'item_id'     => 'cro-email-' . $row->id,
                    'data'        => array(
                        array( 'name' => __( 'Email', 'meyvora-convert' ),   'value' => $email_address ),
                        array( 'name' => __( 'Captured', 'meyvora-convert' ), 'value' => $row->created_at ),
                    ),
                );
            }
        }

        // Export analytics events by user_id
        $user = get_user_by( 'email', $email_address );
        if ( $user ) {
            $table_events = $wpdb->prefix . 'cro_events';
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_events ) ) === $table_events ) {
                $rows = $wpdb->get_results( $wpdb->prepare(
                    "SELECT id, event_type, page_url, created_at FROM {$table_events} WHERE user_id = %d LIMIT 100",
                    $user->ID
                ) );
                foreach ( $rows as $row ) {
                    $export_items[] = array(
                        'group_id'    => 'cro_events',
                        'group_label' => __( 'Meyvora Convert — Analytics Events', 'meyvora-convert' ),
                        'item_id'     => 'cro-event-' . $row->id,
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
        $table = $wpdb->prefix . 'cro_abandoned_carts';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
            $count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE email = %s", $email_address
            ) );
            if ( $count > 0 ) {
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$table} SET email = %s, user_id = 0, cart_contents = %s WHERE email = %s",
                    '[deleted]', '{}', $email_address
                ) );
                $items_removed += $count;
            }
        }

        // Hard-delete captured emails
        $table_emails = $wpdb->prefix . 'cro_emails';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_emails ) ) === $table_emails ) {
            $deleted = $wpdb->delete( $table_emails, array( 'email' => $email_address ), array( '%s' ) );
            $items_removed += (int) $deleted;
        }

        // Delete analytics events by user_id
        $user = get_user_by( 'email', $email_address );
        if ( $user ) {
            $table_events = $wpdb->prefix . 'cro_events';
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_events ) ) === $table_events ) {
                $deleted = $wpdb->delete( $table_events, array( 'user_id' => $user->ID ), array( '%d' ) );
                $items_removed += (int) $deleted;
            }

            $table_assign = $wpdb->prefix . 'cro_ab_assignments';
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_assign ) ) === $table_assign ) {
                $deleted = $wpdb->delete( $table_assign, array( 'user_id' => $user->ID ), array( '%d' ) );
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
