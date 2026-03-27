<?php
/**
 * Analytics Data Model
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRO_Analytics {

    private const CACHE_GROUP = 'meyvora_cro';
    private const CACHE_TTL   = 300;

    private $events_table;
    private $campaigns_table;
    
    public function __construct() {
        global $wpdb;
        $this->events_table = $wpdb->prefix . 'cro_events';
        $this->campaigns_table = $wpdb->prefix . 'cro_campaigns';
    }

    /**
     * Get overview stats for dashboard (static helper).
     * Returns keys: revenue_attributed, total_conversions, total_impressions, conversion_rate, emails_captured, coupons_redeemed.
     *
     * @param int $days Number of days to include (default 30).
     * @return array
     */
    public static function get_overview_stats( $days = 30 ) {
        $date_to   = wp_date( 'Y-m-d' );
        $date_from = wp_date( 'Y-m-d', strtotime( "-{$days} days" ) );
        $cache_key = 'meyvora_cro_' . md5( 'overview_stats_' . serialize( array( (int) $days, $date_from, $date_to ) ) );
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return $cached;
        }

        $analytics = new self();
        $summary   = $analytics->get_summary( $date_from, $date_to );

        global $wpdb;
        $events_table = $wpdb->prefix . 'cro_events';
        $coupons_key  = 'meyvora_cro_' . md5( 'overview_stats_coupons_' . serialize( array( $date_from, $date_to ) ) );
        $coupons      = wp_cache_get( $coupons_key, self::CACHE_GROUP );
        if ( false === $coupons ) {
            $coupons = 0;
            if ( $analytics->cache_table_exists( $events_table ) ) {
                $coupons = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'conversion\' AND coupon_code IS NOT NULL AND coupon_code != \'\'',
                        $events_table,
                        $date_from,
                        $date_to
                    )
                );
            }
            wp_cache_set( $coupons_key, $coupons, self::CACHE_GROUP, self::CACHE_TTL );
        } else {
            $coupons = (int) $coupons;
        }

        $out = array(
            'revenue_attributed'   => $summary['revenue'],
            'total_conversions'    => $summary['conversions'],
            'total_impressions'    => $summary['impressions'],
            'conversion_rate'      => $summary['conversion_rate'],
            'emails_captured'      => $summary['emails'],
            'coupons_redeemed'     => $coupons,
        );
        wp_cache_set( $cache_key, $out, self::CACHE_GROUP, self::CACHE_TTL );
        return $out;
    }

    /**
     * Get top campaigns for dashboard (static helper).
     * Returns array of objects with id, name, impressions, conversions, conversion_rate, revenue_attributed.
     *
     * @param int $days  Number of days.
     * @param int $limit Max campaigns to return.
     * @return array
     */
    public static function get_top_campaigns( $days = 30, $limit = 5 ) {
        $date_to   = wp_date( 'Y-m-d' );
        $date_from = wp_date( 'Y-m-d', strtotime( "-{$days} days" ) );
        $analytics = new self();
        $rows      = $analytics->get_campaign_performance( $date_from, $date_to, $limit );
        $out       = array();
        foreach ( $rows as $row ) {
            $impressions = (int) ( $row['impressions'] ?? 0 );
            $conversions = (int) ( $row['conversions'] ?? 0 );
            $out[] = (object) array(
                'id'                  => (int) $row['id'],
                'name'                => $row['name'] ?? '',
                'impressions'         => $impressions,
                'conversions'         => $conversions,
                'conversion_rate'     => $impressions > 0 ? round( ( $conversions / $impressions ) * 100, 2 ) : 0,
                'revenue_attributed'  => (float) ( $row['revenue'] ?? 0 ),
            );
        }
        return $out;
    }
    
    /**
     * Get dashboard summary stats.
     *
     * @param string   $date_from   Y-m-d.
     * @param string   $date_to     Y-m-d.
     * @param int|null $campaign_id Optional. Filter by campaign.
     * @return array
     */
    public function get_summary( $date_from = null, $date_to = null, $campaign_id = null ) {
        $date_from   = $date_from ?: wp_date( 'Y-m-d', strtotime( '-30 days' ) );
        $date_to     = $date_to ?: wp_date( 'Y-m-d' );
        $campaign_id = ( $campaign_id !== null && $campaign_id > 0 ) ? absint( $campaign_id ) : null;

        $cache_key = 'meyvora_cro_' . md5( 'get_summary_' . serialize( array( $date_from, $date_to, $campaign_id ) ) );
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return $cached;
        }

        if ( ! $this->cache_table_exists( $this->events_table ) ) {
            $empty = $this->empty_summary();
            wp_cache_set( $cache_key, $empty, self::CACHE_GROUP, self::CACHE_TTL );
            return $empty;
        }

        return $this->get_summary_internal( $date_from, $date_to, $campaign_id );
    }

    /**
     * Empty summary structure.
     *
     * @return array
     */
    private function empty_summary() {
        return array(
            'impressions' => 0,
            'impressions_change' => 0,
            'clicks' => 0,
            'ctr' => 0,
            'conversions' => 0,
            'conversions_change' => 0,
            'conversion_rate' => 0,
            'revenue' => 0,
            'revenue_change' => 0,
            'revenue_formatted' => function_exists( 'wc_price' ) ? wc_price( 0 ) : '0',
            'emails' => 0,
            'rpv' => 0,
            'rpv_formatted' => function_exists( 'wc_price' ) ? wc_price( 0 ) : '0',
            'sticky_cart_adds' => 0,
            'shipping_bar_interactions' => 0,
            'prev_conversions' => 0,
            'prev_impressions' => 0,
            'prev_revenue'     => 0,
            'prev_emails'      => 0,
        );
    }

    /**
     * Whether a DB table exists (cached).
     *
     * @param string $table_name Full table name including prefix.
     * @return bool
     */
    private function cache_table_exists( $table_name ) {
        global $wpdb;
        $cache_key = 'meyvora_cro_' . md5( 'table_exists_' . serialize( array( $table_name ) ) );
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return (bool) $cached;
        }
        $exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
        wp_cache_set( $cache_key, $exists ? 1 : 0, self::CACHE_GROUP, self::CACHE_TTL );
        return $exists;
    }

    /**
     * Internal summary with optional campaign filter.
     *
     * @param string     $date_from    Y-m-d.
     * @param string     $date_to      Y-m-d.
     * @param int|null   $campaign_id  Optional. Filter by campaign.
     * @return array
     */
    private function get_summary_internal( $date_from, $date_to, $campaign_id = null ) {
        global $wpdb;

        $events_table = $this->events_table;
        $campaign_id   = ( $campaign_id !== null && $campaign_id > 0 ) ? absint( $campaign_id ) : null;

        $cache_key = 'meyvora_cro_' . md5( 'get_summary_' . serialize( array( $date_from, $date_to, $campaign_id ) ) );
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return $cached;
        }

        if ( $campaign_id ) {
            $k_imp = 'meyvora_cro_' . md5( 'summary_imp_' . serialize( array( $date_from, $date_to, $campaign_id ) ) );
            $impressions = wp_cache_get( $k_imp, self::CACHE_GROUP );
            if ( false === $impressions ) {
                $impressions = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'impression\' AND source_type = \'campaign\' AND source_id = %d',
                        $events_table,
                        $date_from,
                        $date_to,
                        $campaign_id
                    )
                );
                wp_cache_set( $k_imp, $impressions, self::CACHE_GROUP, self::CACHE_TTL );
            } else {
                $impressions = (int) $impressions;
            }
            $k_clk = 'meyvora_cro_' . md5( 'summary_clk_' . serialize( array( $date_from, $date_to, $campaign_id ) ) );
            $clicks = wp_cache_get( $k_clk, self::CACHE_GROUP );
            if ( false === $clicks ) {
                $clicks = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'interaction\' AND source_type = \'campaign\' AND source_id = %d',
                        $events_table,
                        $date_from,
                        $date_to,
                        $campaign_id
                    )
                );
                wp_cache_set( $k_clk, $clicks, self::CACHE_GROUP, self::CACHE_TTL );
            } else {
                $clicks = (int) $clicks;
            }
            $k_conv = 'meyvora_cro_' . md5( 'summary_conv_' . serialize( array( $date_from, $date_to, $campaign_id ) ) );
            $conversions = wp_cache_get( $k_conv, self::CACHE_GROUP );
            if ( false === $conversions ) {
                $conversions = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'conversion\' AND source_type = \'campaign\' AND source_id = %d',
                        $events_table,
                        $date_from,
                        $date_to,
                        $campaign_id
                    )
                );
                wp_cache_set( $k_conv, $conversions, self::CACHE_GROUP, self::CACHE_TTL );
            } else {
                $conversions = (int) $conversions;
            }
            $k_rev = 'meyvora_cro_' . md5( 'summary_rev_' . serialize( array( $date_from, $date_to, $campaign_id ) ) );
            $revenue = wp_cache_get( $k_rev, self::CACHE_GROUP );
            if ( false === $revenue ) {
                $revenue = (float) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT COALESCE(SUM(order_value), 0) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'conversion\' AND source_type = \'campaign\' AND source_id = %d',
                        $events_table,
                        $date_from,
                        $date_to,
                        $campaign_id
                    )
                );
                wp_cache_set( $k_rev, $revenue, self::CACHE_GROUP, self::CACHE_TTL );
            } else {
                $revenue = (float) $revenue;
            }
            $k_em = 'meyvora_cro_' . md5( 'summary_em_' . serialize( array( $date_from, $date_to, $campaign_id ) ) );
            $emails = wp_cache_get( $k_em, self::CACHE_GROUP );
            if ( false === $emails ) {
                $emails = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'conversion\' AND email IS NOT NULL AND source_type = \'campaign\' AND source_id = %d',
                        $events_table,
                        $date_from,
                        $date_to,
                        $campaign_id
                    )
                );
                wp_cache_set( $k_em, $emails, self::CACHE_GROUP, self::CACHE_TTL );
            } else {
                $emails = (int) $emails;
            }
        } else {
            $k_imp = 'meyvora_cro_' . md5( 'summary_imp_all_' . serialize( array( $date_from, $date_to ) ) );
            $impressions = wp_cache_get( $k_imp, self::CACHE_GROUP );
            if ( false === $impressions ) {
                $impressions = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'impression\'',
                        $events_table,
                        $date_from,
                        $date_to
                    )
                );
                wp_cache_set( $k_imp, $impressions, self::CACHE_GROUP, self::CACHE_TTL );
            } else {
                $impressions = (int) $impressions;
            }
            $k_clk = 'meyvora_cro_' . md5( 'summary_clk_all_' . serialize( array( $date_from, $date_to ) ) );
            $clicks = wp_cache_get( $k_clk, self::CACHE_GROUP );
            if ( false === $clicks ) {
                $clicks = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'interaction\' AND source_type = \'campaign\'',
                        $events_table,
                        $date_from,
                        $date_to
                    )
                );
                wp_cache_set( $k_clk, $clicks, self::CACHE_GROUP, self::CACHE_TTL );
            } else {
                $clicks = (int) $clicks;
            }
            $k_conv = 'meyvora_cro_' . md5( 'summary_conv_all_' . serialize( array( $date_from, $date_to ) ) );
            $conversions = wp_cache_get( $k_conv, self::CACHE_GROUP );
            if ( false === $conversions ) {
                $conversions = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'conversion\'',
                        $events_table,
                        $date_from,
                        $date_to
                    )
                );
                wp_cache_set( $k_conv, $conversions, self::CACHE_GROUP, self::CACHE_TTL );
            } else {
                $conversions = (int) $conversions;
            }
            $k_rev = 'meyvora_cro_' . md5( 'summary_rev_all_' . serialize( array( $date_from, $date_to ) ) );
            $revenue = wp_cache_get( $k_rev, self::CACHE_GROUP );
            if ( false === $revenue ) {
                $revenue = (float) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT COALESCE(SUM(order_value), 0) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'conversion\'',
                        $events_table,
                        $date_from,
                        $date_to
                    )
                );
                wp_cache_set( $k_rev, $revenue, self::CACHE_GROUP, self::CACHE_TTL );
            } else {
                $revenue = (float) $revenue;
            }
            $k_em = 'meyvora_cro_' . md5( 'summary_em_all_' . serialize( array( $date_from, $date_to ) ) );
            $emails = wp_cache_get( $k_em, self::CACHE_GROUP );
            if ( false === $emails ) {
                $emails = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'conversion\' AND email IS NOT NULL',
                        $events_table,
                        $date_from,
                        $date_to
                    )
                );
                wp_cache_set( $k_em, $emails, self::CACHE_GROUP, self::CACHE_TTL );
            } else {
                $emails = (int) $emails;
            }
        }

        $k_sticky = 'meyvora_cro_' . md5( 'summary_sticky_' . serialize( array( $date_from, $date_to ) ) );
        $sticky_cart_adds = wp_cache_get( $k_sticky, self::CACHE_GROUP );
        if ( false === $sticky_cart_adds ) {
            $sticky_cart_adds = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
            $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND source_type = \'sticky_cart\' AND event_type = \'interaction\'',
                    $events_table,
                    $date_from,
                    $date_to
                )
            );
            wp_cache_set( $k_sticky, $sticky_cart_adds, self::CACHE_GROUP, self::CACHE_TTL );
        } else {
            $sticky_cart_adds = (int) $sticky_cart_adds;
        }

        $k_ship = 'meyvora_cro_' . md5( 'summary_ship_' . serialize( array( $date_from, $date_to ) ) );
        $shipping_bar_interactions = wp_cache_get( $k_ship, self::CACHE_GROUP );
        if ( false === $shipping_bar_interactions ) {
            $shipping_bar_interactions = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
            $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND source_type = \'shipping_bar\' AND event_type = \'interaction\'',
                    $events_table,
                    $date_from,
                    $date_to
                )
            );
            wp_cache_set( $k_ship, $shipping_bar_interactions, self::CACHE_GROUP, self::CACHE_TTL );
        } else {
            $shipping_bar_interactions = (int) $shipping_bar_interactions;
        }

        $conversion_rate = $impressions > 0 ? ( $conversions / $impressions ) * 100 : 0;
        $ctr = $impressions > 0 ? ( $clicks / $impressions ) * 100 : 0;
        $rpv = $impressions > 0 ? $revenue / $impressions : 0;

        $days = ( strtotime( $date_to ) - strtotime( $date_from ) ) / 86400;
        $prev_from = wp_date( 'Y-m-d', strtotime( $date_from . " -{$days} days" ) );
        $prev_to   = wp_date( 'Y-m-d', strtotime( $date_from . ' -1 day' ) );
        if ( $campaign_id ) {
            $pk_imp = 'meyvora_cro_' . md5( 'summary_prev_imp_' . serialize( array( $prev_from, $prev_to, $campaign_id ) ) );
            $prev_impressions = wp_cache_get( $pk_imp, self::CACHE_GROUP );
            if ( false === $prev_impressions ) {
                $prev_impressions = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'impression\' AND source_type = \'campaign\' AND source_id = %d',
                        $events_table,
                        $prev_from,
                        $prev_to,
                        $campaign_id
                    )
                );
                wp_cache_set( $pk_imp, $prev_impressions, self::CACHE_GROUP, self::CACHE_TTL );
            } else {
                $prev_impressions = (int) $prev_impressions;
            }
            $pk_conv = 'meyvora_cro_' . md5( 'summary_prev_conv_' . serialize( array( $prev_from, $prev_to, $campaign_id ) ) );
            $prev_conversions = wp_cache_get( $pk_conv, self::CACHE_GROUP );
            if ( false === $prev_conversions ) {
                $prev_conversions = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'conversion\' AND source_type = \'campaign\' AND source_id = %d',
                        $events_table,
                        $prev_from,
                        $prev_to,
                        $campaign_id
                    )
                );
                wp_cache_set( $pk_conv, $prev_conversions, self::CACHE_GROUP, self::CACHE_TTL );
            } else {
                $prev_conversions = (int) $prev_conversions;
            }
            $pk_rev = 'meyvora_cro_' . md5( 'summary_prev_rev_' . serialize( array( $prev_from, $prev_to, $campaign_id ) ) );
            $prev_revenue = wp_cache_get( $pk_rev, self::CACHE_GROUP );
            if ( false === $prev_revenue ) {
                $prev_revenue = (float) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT COALESCE(SUM(order_value), 0) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'conversion\' AND source_type = \'campaign\' AND source_id = %d',
                        $events_table,
                        $prev_from,
                        $prev_to,
                        $campaign_id
                    )
                );
                wp_cache_set( $pk_rev, $prev_revenue, self::CACHE_GROUP, self::CACHE_TTL );
            } else {
                $prev_revenue = (float) $prev_revenue;
            }
            $pk_em = 'meyvora_cro_' . md5( 'summary_prev_em_' . serialize( array( $prev_from, $prev_to, $campaign_id ) ) );
            $prev_emails = wp_cache_get( $pk_em, self::CACHE_GROUP );
            if ( false === $prev_emails ) {
                $prev_emails = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'conversion\' AND email IS NOT NULL AND source_type = \'campaign\' AND source_id = %d',
                        $events_table,
                        $prev_from,
                        $prev_to,
                        $campaign_id
                    )
                );
                wp_cache_set( $pk_em, $prev_emails, self::CACHE_GROUP, self::CACHE_TTL );
            } else {
                $prev_emails = (int) $prev_emails;
            }
        } else {
            $pk_imp = 'meyvora_cro_' . md5( 'summary_prev_imp_all_' . serialize( array( $prev_from, $prev_to ) ) );
            $prev_impressions = wp_cache_get( $pk_imp, self::CACHE_GROUP );
            if ( false === $prev_impressions ) {
                $prev_impressions = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'impression\'',
                        $events_table,
                        $prev_from,
                        $prev_to
                    )
                );
                wp_cache_set( $pk_imp, $prev_impressions, self::CACHE_GROUP, self::CACHE_TTL );
            } else {
                $prev_impressions = (int) $prev_impressions;
            }
            $pk_conv = 'meyvora_cro_' . md5( 'summary_prev_conv_all_' . serialize( array( $prev_from, $prev_to ) ) );
            $prev_conversions = wp_cache_get( $pk_conv, self::CACHE_GROUP );
            if ( false === $prev_conversions ) {
                $prev_conversions = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'conversion\'',
                        $events_table,
                        $prev_from,
                        $prev_to
                    )
                );
                wp_cache_set( $pk_conv, $prev_conversions, self::CACHE_GROUP, self::CACHE_TTL );
            } else {
                $prev_conversions = (int) $prev_conversions;
            }
            $pk_rev = 'meyvora_cro_' . md5( 'summary_prev_rev_all_' . serialize( array( $prev_from, $prev_to ) ) );
            $prev_revenue = wp_cache_get( $pk_rev, self::CACHE_GROUP );
            if ( false === $prev_revenue ) {
                $prev_revenue = (float) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT COALESCE(SUM(order_value), 0) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'conversion\'',
                        $events_table,
                        $prev_from,
                        $prev_to
                    )
                );
                wp_cache_set( $pk_rev, $prev_revenue, self::CACHE_GROUP, self::CACHE_TTL );
            } else {
                $prev_revenue = (float) $prev_revenue;
            }
            $pk_em = 'meyvora_cro_' . md5( 'summary_prev_em_all_' . serialize( array( $prev_from, $prev_to ) ) );
            $prev_emails = wp_cache_get( $pk_em, self::CACHE_GROUP );
            if ( false === $prev_emails ) {
                $prev_emails = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'conversion\' AND email IS NOT NULL',
                        $events_table,
                        $prev_from,
                        $prev_to
                    )
                );
                wp_cache_set( $pk_em, $prev_emails, self::CACHE_GROUP, self::CACHE_TTL );
            } else {
                $prev_emails = (int) $prev_emails;
            }
        }

        $return = array(
            'impressions' => $impressions,
            'impressions_change' => $this->calc_change( $impressions, $prev_impressions ),
            'clicks' => $clicks,
            'ctr' => round( $ctr, 2 ),
            'conversions' => $conversions,
            'conversions_change' => $this->calc_change( $conversions, $prev_conversions ),
            'conversion_rate' => round( $conversion_rate, 2 ),
            'revenue' => $revenue,
            'revenue_change' => $this->calc_change( $revenue, $prev_revenue ),
            'revenue_formatted' => function_exists( 'wc_price' ) ? wc_price( $revenue ) : (string) $revenue,
            'emails' => $emails,
            'rpv' => round( $rpv, 2 ),
            'rpv_formatted' => function_exists( 'wc_price' ) ? wc_price( $rpv ) : (string) $rpv,
            'sticky_cart_adds' => $sticky_cart_adds,
            'shipping_bar_interactions' => $shipping_bar_interactions,
            'prev_conversions' => $prev_conversions,
            'prev_impressions' => $prev_impressions,
            'prev_revenue'     => $prev_revenue,
            'prev_emails'      => $prev_emails,
        );
        wp_cache_set( $cache_key, $return, self::CACHE_GROUP, self::CACHE_TTL );
        return $return;
    }

    private function calc_change( $current, $previous ) {
        if ($previous == 0) return $current > 0 ? 100 : 0;
        return round((($current - $previous) / $previous) * 100, 1);
    }
    
    /**
     * Get daily stats for chart.
     *
     * @param string   $date_from   Y-m-d.
     * @param string   $date_to     Y-m-d.
     * @param int|null $campaign_id Optional. Filter by campaign.
     * @return array
     */
    public function get_daily_stats( $date_from, $date_to, $campaign_id = null ) {
        global $wpdb;

        $campaign_id = ( $campaign_id !== null && $campaign_id > 0 ) ? absint( $campaign_id ) : null;
        $cache_key   = 'meyvora_cro_' . md5( 'daily_stats_' . serialize( array( $date_from, $date_to, $campaign_id ) ) );
        $cached      = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return $cached;
        }

        $events_table = $this->events_table;
        if ( ! $this->cache_table_exists( $events_table ) ) {
            wp_cache_set( $cache_key, array(), self::CACHE_GROUP, self::CACHE_TTL );
            return array();
        }

        $raw_key = 'meyvora_cro_' . md5( 'daily_stats_raw_' . serialize( array( $date_from, $date_to, $campaign_id ) ) );
        $results = wp_cache_get( $raw_key, self::CACHE_GROUP );
        if ( false === $results ) {
            if ( $campaign_id ) {
                $results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT 
                        DATE(created_at) as date,
                        SUM(CASE WHEN event_type = \'impression\' THEN 1 ELSE 0 END) as impressions,
                        SUM(CASE WHEN event_type = \'conversion\' THEN 1 ELSE 0 END) as conversions,
                        SUM(CASE WHEN event_type = \'conversion\' THEN order_value ELSE 0 END) as revenue
                    FROM %i
                    WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND source_type = \'campaign\' AND source_id = %d
                    GROUP BY DATE(created_at)
                    ORDER BY date ASC',
                        $events_table,
                        $date_from,
                        $date_to,
                        $campaign_id
                    ),
                    ARRAY_A
                );
            } else {
                $results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT 
                        DATE(created_at) as date,
                        SUM(CASE WHEN event_type = \'impression\' THEN 1 ELSE 0 END) as impressions,
                        SUM(CASE WHEN event_type = \'conversion\' THEN 1 ELSE 0 END) as conversions,
                        SUM(CASE WHEN event_type = \'conversion\' THEN order_value ELSE 0 END) as revenue
                    FROM %i
                    WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY date ASC',
                        $events_table,
                        $date_from,
                        $date_to
                    ),
                    ARRAY_A
                );
            }
            $results = is_array( $results ) ? $results : array();
            wp_cache_set( $raw_key, $results, self::CACHE_GROUP, self::CACHE_TTL );
        } else {
            $results = is_array( $results ) ? $results : array();
        }

        $data_map = array();
        foreach ( $results as $row ) {
            $data_map[ $row['date'] ] = $row;
        }

        $filled   = array();
        $current  = new DateTime( $date_from );
        $end      = new DateTime( $date_to );
        while ( $current <= $end ) {
            $date = $current->format( 'Y-m-d' );
            $filled[] = array(
                'date'        => $date,
                'label'       => $current->format( 'M j' ),
                'impressions' => (int) ( $data_map[ $date ]['impressions'] ?? 0 ),
                'conversions' => (int) ( $data_map[ $date ]['conversions'] ?? 0 ),
                'revenue'     => (float) ( $data_map[ $date ]['revenue'] ?? 0 ),
            );
            $current->modify( '+1 day' );
        }

        wp_cache_set( $cache_key, $filled, self::CACHE_GROUP, self::CACHE_TTL );
        return $filled;
    }
    
    /**
     * Get campaign performance
     */
    public function get_campaign_performance($date_from, $date_to, $limit = 10) {
        global $wpdb;
        $campaigns_table = $this->campaigns_table;
        $events_table    = $this->events_table;

        $cache_key = 'meyvora_cro_' . md5( 'campaign_performance_' . serialize( array( $date_from, $date_to, (int) $limit ) ) );
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return $cached;
        }
        
        // Check if tables exist
        $events_exists    = $this->cache_table_exists( $events_table );
        $campaigns_exists = $this->cache_table_exists( $campaigns_table );

        if ( ! $events_exists || ! $campaigns_exists ) {
            wp_cache_set( $cache_key, array(), self::CACHE_GROUP, self::CACHE_TTL );
            return array();
        }
        
        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
        $wpdb->prepare(
            'SELECT 
                c.id,
                c.name,
                c.status,
                COUNT(CASE WHEN e.event_type = \'impression\' THEN 1 END) as impressions,
                COUNT(CASE WHEN e.event_type = \'conversion\' THEN 1 END) as conversions,
                COALESCE(SUM(CASE WHEN e.event_type = \'conversion\' THEN e.order_value END), 0) as revenue,
                COUNT(CASE WHEN e.event_type = \'conversion\' AND e.email IS NOT NULL AND e.email != \'\' THEN 1 END) as emails
            FROM %i c
            LEFT JOIN %i e ON e.source_type = \'campaign\' AND e.source_id = c.id 
                AND e.created_at >= %s AND e.created_at < DATE_ADD(%s, INTERVAL 1 DAY)
            GROUP BY c.id
            ORDER BY revenue DESC
            LIMIT %d',
            $campaigns_table,
            $events_table,
            $date_from, $date_to, $limit
        ), ARRAY_A);
        wp_cache_set( $cache_key, $rows, self::CACHE_GROUP, self::CACHE_TTL );
        return $rows;
    }
    
    /**
     * Get device breakdown
     */
    public function get_device_stats($date_from, $date_to) {
        global $wpdb;
        $events_table = $this->events_table;

        $cache_key = 'meyvora_cro_' . md5( 'device_stats_' . serialize( array( $date_from, $date_to ) ) );
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return $cached;
        }
        
        // Check if table exists
        if ( ! $this->cache_table_exists( $events_table ) ) {
            wp_cache_set( $cache_key, array(), self::CACHE_GROUP, self::CACHE_TTL );
            return array();
        }

        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
        $wpdb->prepare(
            'SELECT 
                COALESCE(device_type, \'unknown\') as device,
                COUNT(CASE WHEN event_type = \'impression\' THEN 1 END) as impressions,
                COUNT(CASE WHEN event_type = \'conversion\' THEN 1 END) as conversions
            FROM %i
            WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY)
            GROUP BY device_type',
            $events_table,
            $date_from, $date_to
        ), ARRAY_A);
        wp_cache_set( $cache_key, $rows, self::CACHE_GROUP, self::CACHE_TTL );
        return $rows;
    }
    
    /**
     * Get top pages.
     *
     * @param string   $date_from   Y-m-d.
     * @param string   $date_to     Y-m-d.
     * @param int      $limit       Max rows.
     * @param int|null $campaign_id Optional. Filter by campaign.
     * @return array
     */
    public function get_top_pages( $date_from, $date_to, $limit = 10, $campaign_id = null ) {
        global $wpdb;

        $campaign_id = ( $campaign_id !== null && $campaign_id > 0 ) ? absint( $campaign_id ) : null;
        $cache_key   = 'meyvora_cro_' . md5( 'top_pages_' . serialize( array( $date_from, $date_to, $limit, $campaign_id ) ) );
        $cached      = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return $cached;
        }

        $events_table = $this->events_table;
        if ( ! $this->cache_table_exists( $events_table ) ) {
            wp_cache_set( $cache_key, array(), self::CACHE_GROUP, self::CACHE_TTL );
            return array();
        }

        if ( $campaign_id ) {
            $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
            $wpdb->prepare(
                    'SELECT 
                        page_url,
                        COUNT(CASE WHEN event_type = \'impression\' THEN 1 END) as impressions,
                        COUNT(CASE WHEN event_type = \'conversion\' THEN 1 END) as conversions,
                        COALESCE(SUM(CASE WHEN event_type = \'conversion\' THEN order_value END), 0) as revenue,
                        COUNT(CASE WHEN event_type = \'conversion\' AND email IS NOT NULL AND email != \'\' THEN 1 END) as emails_captured
                    FROM %i
                    WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND source_type = \'campaign\' AND source_id = %d
                    GROUP BY page_url
                    ORDER BY conversions DESC
                    LIMIT %d',
                    $events_table,
                    $date_from,
                    $date_to,
                    $campaign_id,
                    $limit
                ),
                ARRAY_A
            );
        } else {
            $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
            $wpdb->prepare(
                    'SELECT 
                        page_url,
                        COUNT(CASE WHEN event_type = \'impression\' THEN 1 END) as impressions,
                        COUNT(CASE WHEN event_type = \'conversion\' THEN 1 END) as conversions,
                        COALESCE(SUM(CASE WHEN event_type = \'conversion\' THEN order_value END), 0) as revenue,
                        COUNT(CASE WHEN event_type = \'conversion\' AND email IS NOT NULL AND email != \'\' THEN 1 END) as emails_captured
                    FROM %i
                    WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY)
                    GROUP BY page_url
                    ORDER BY conversions DESC
                    LIMIT %d',
                    $events_table,
                    $date_from,
                    $date_to,
                    $limit
                ),
                ARRAY_A
            );
        }
        wp_cache_set( $cache_key, $rows, self::CACHE_GROUP, self::CACHE_TTL );
        return $rows;
    }

    /**
     * Get list of campaigns for filter dropdown.
     *
     * @return array Array of id => name.
     */
    public function get_campaigns_list() {
        global $wpdb;

        $cache_key = 'meyvora_cro_' . md5( 'campaigns_list_' . serialize( array( 'v1' ) ) );
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return $cached;
        }

        $campaigns_table = $this->campaigns_table;
        if ( ! $this->cache_table_exists( $campaigns_table ) ) {
            wp_cache_set( $cache_key, array(), self::CACHE_GROUP, self::CACHE_TTL );
            return array();
        }
        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
        $wpdb->prepare(
                'SELECT id, name FROM %i ORDER BY name ASC LIMIT %d',
                $campaigns_table,
                500
            ),
            ARRAY_A
        );
        $list = array();
        foreach ( $rows as $row ) {
            $list[ (int) $row['id'] ] = $row['name'];
        }
        wp_cache_set( $cache_key, $list, self::CACHE_GROUP, self::CACHE_TTL );
        return $list;
    }

    /**
     * Stream export rows in chunks (avoids loading all events into memory).
     *
     * @param string        $date_from   Y-m-d.
     * @param string        $date_to     Y-m-d.
     * @param int|null      $campaign_id Optional campaign filter.
     * @param callable      $callback    Receives one row array per event.
     * @param int           $chunk_size  Rows per query.
     * @return void
     */
    public function walk_export_events_rows( $date_from, $date_to, $campaign_id, $callback, $chunk_size = 1000 ) {
        global $wpdb;

        if ( ! is_callable( $callback ) ) {
            return;
        }

        $events_table = $this->events_table;
        if ( ! $this->cache_table_exists( $events_table ) ) {
            return;
        }

        $campaign_id = ( $campaign_id !== null && $campaign_id > 0 ) ? absint( $campaign_id ) : null;
        $chunk_size    = max( 100, min( 5000, (int) $chunk_size ) );
        $offset        = 0;

        while ( true ) {
            $batch_key = 'meyvora_cro_' . md5( 'export_events_batch_' . serialize( array( $date_from, $date_to, $campaign_id, $chunk_size, $offset ) ) );
            $batch     = wp_cache_get( $batch_key, self::CACHE_GROUP );
            if ( false === $batch ) {
                if ( $campaign_id ) {
                    $batch = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                    $wpdb->prepare(
                            'SELECT 
                        e.created_at,
                        e.event_type,
                        e.source_type,
                        e.source_id,
                        e.session_id,
                        e.user_id,
                        e.page_type,
                        e.page_url,
                        e.metadata
                    FROM %i e
                        WHERE e.created_at >= %s AND e.created_at < DATE_ADD(%s, INTERVAL 1 DAY)
                          AND e.source_type = \'campaign\' AND e.source_id = %d
                        ORDER BY e.created_at ASC
                        LIMIT %d OFFSET %d',
                            $events_table,
                            $date_from,
                            $date_to,
                            $campaign_id,
                            $chunk_size,
                            $offset
                        ),
                        ARRAY_A
                    );
                } else {
                    $batch = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                    $wpdb->prepare(
                            'SELECT 
                        e.created_at,
                        e.event_type,
                        e.source_type,
                        e.source_id,
                        e.session_id,
                        e.user_id,
                        e.page_type,
                        e.page_url,
                        e.metadata
                    FROM %i e
                        WHERE e.created_at >= %s AND e.created_at < DATE_ADD(%s, INTERVAL 1 DAY)
                        ORDER BY e.created_at ASC
                        LIMIT %d OFFSET %d',
                            $events_table,
                            $date_from,
                            $date_to,
                            $chunk_size,
                            $offset
                        ),
                        ARRAY_A
                    );
                }
                wp_cache_set( $batch_key, is_array( $batch ) ? $batch : array(), self::CACHE_GROUP, self::CACHE_TTL );
            }

            if ( empty( $batch ) ) {
                break;
            }
            foreach ( $batch as $row ) {
                $callback( $row );
            }
            if ( count( $batch ) < $chunk_size ) {
                break;
            }
            $offset += $chunk_size;
        }
    }

    /**
     * Export events for CSV (raw rows). May use large memory on big sites; prefer walk_export_events_rows for streaming.
     *
     * @param string   $date_from   Y-m-d.
     * @param string   $date_to     Y-m-d.
     * @param int|null $campaign_id Optional. Filter by campaign.
     * @return array
     */
    public function export_events_for_csv( $date_from, $date_to, $campaign_id = null ) {
        $out = array();
        $this->walk_export_events_rows(
            $date_from,
            $date_to,
            $campaign_id,
            function ( $row ) use ( &$out ) {
                $out[] = $row;
            }
        );
        return $out;
    }

    /**
     * Get daily summary rows for CSV export: day, impressions, conversions, offer_applies, campaign_clicks, ab_exposures.
     *
     * @param string $date_from Y-m-d.
     * @param string $date_to   Y-m-d.
     * @return array<int, array{ day: string, impressions: int, conversions: int, offer_applies: int, campaign_clicks: int, ab_exposures: int }>
     */
    public function get_daily_summary_for_export( $date_from, $date_to ) {
        global $wpdb;

        $cache_key = 'meyvora_cro_' . md5( 'daily_summary_export_' . serialize( array( $date_from, $date_to ) ) );
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return $cached;
        }

        $events_table = $this->events_table;
        $events_ok    = $this->cache_table_exists( $events_table );
        $offer_logs   = $wpdb->prefix . 'cro_offer_logs';
        $logs_ok      = $this->cache_table_exists( $offer_logs );

        $days = array();
        $current = new DateTime( $date_from );
        $end     = new DateTime( $date_to );
        while ( $current <= $end ) {
            $days[ $current->format( 'Y-m-d' ) ] = array(
                'day'            => $current->format( 'Y-m-d' ),
                'impressions'    => 0,
                'conversions'    => 0,
                'offer_applies'  => 0,
                'campaign_clicks'=> 0,
                'ab_exposures'   => 0,
            );
            $current->modify( '+1 day' );
        }

        if ( $events_ok ) {
            $imp_conv = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
            $wpdb->prepare(
                'SELECT DATE(created_at) AS d, 
                    SUM(CASE WHEN event_type = \'impression\' THEN 1 ELSE 0 END) AS impressions,
                    SUM(CASE WHEN event_type = \'conversion\' THEN 1 ELSE 0 END) AS conversions,
                    SUM(CASE WHEN event_type = \'interaction\' AND source_type = \'campaign\' THEN 1 ELSE 0 END) AS campaign_clicks
                FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) GROUP BY DATE(created_at)',
                $events_table,
                $date_from,
                $date_to
            ), ARRAY_A );
            foreach ( is_array( $imp_conv ) ? $imp_conv : array() as $row ) {
                $d = $row['d'] ?? '';
                if ( isset( $days[ $d ] ) ) {
                    $days[ $d ]['impressions']     = (int) ( $row['impressions'] ?? 0 );
                    $days[ $d ]['conversions']     = (int) ( $row['conversions'] ?? 0 );
                    $days[ $d ]['campaign_clicks'] = (int) ( $row['campaign_clicks'] ?? 0 );
                }
            }
            $ab = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
            $wpdb->prepare(
                'SELECT DATE(created_at) AS d, COUNT(*) AS cnt FROM %i 
                WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'impression\' AND metadata IS NOT NULL AND (metadata LIKE %s OR metadata LIKE %s)
                GROUP BY DATE(created_at)',
                $events_table,
                $date_from,
                $date_to,
                '%variation_id%',
                '%ab_test_id%'
            ), ARRAY_A );
            foreach ( is_array( $ab ) ? $ab : array() as $row ) {
                $d = $row['d'] ?? '';
                if ( isset( $days[ $d ] ) ) {
                    $days[ $d ]['ab_exposures'] = (int) ( $row['cnt'] ?? 0 );
                }
            }
        }

        if ( $logs_ok ) {
            $has_action_key = 'meyvora_cro_' . md5( 'offer_logs_has_action_col_' . serialize( array( $offer_logs ) ) );
            $has_action     = wp_cache_get( $has_action_key, self::CACHE_GROUP );
            if ( false === $has_action ) {
                $has_action = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $offer_logs, 'action' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                wp_cache_set( $has_action_key, $has_action ? 1 : 0, self::CACHE_GROUP, self::CACHE_TTL );
            } else {
                $has_action = (bool) $has_action;
            }
            if ( $has_action ) {
                $apply = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT DATE(created_at) AS d, COUNT(*) AS cnt FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND action = \'applied\' GROUP BY DATE(created_at)',
                        $offer_logs,
                        $date_from,
                        $date_to
                    ),
                    ARRAY_A
                );
            } else {
                $apply = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT DATE(created_at) AS d, COUNT(*) AS cnt FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) GROUP BY DATE(created_at)',
                        $offer_logs,
                        $date_from,
                        $date_to
                    ),
                    ARRAY_A
                );
            }
            foreach ( is_array( $apply ) ? $apply : array() as $row ) {
                $d = $row['d'] ?? '';
                if ( isset( $days[ $d ] ) ) {
                    $days[ $d ]['offer_applies'] = (int) ( $row['cnt'] ?? 0 );
                }
            }
        }

        $out = array_values( $days );
        wp_cache_set( $cache_key, $out, self::CACHE_GROUP, self::CACHE_TTL );
        return $out;
    }

    /**
     * Export to CSV (legacy format). Prefer export_events_for_csv for new columns.
     *
     * @param string   $date_from   Y-m-d.
     * @param string   $date_to     Y-m-d.
     * @param int|null $campaign_id Optional. Filter by campaign.
     * @return array
     */
    public function export_csv( $date_from, $date_to, $campaign_id = null ) {
        return $this->export_events_for_csv( $date_from, $date_to, $campaign_id );
    }

    /**
     * Get the most recent events for dashboard "Recent activity".
     *
     * @param int $limit Max number of events (default 10).
     * @return array<int, array{ created_at: string, event_type: string, source_type: string, campaign_name: string|null, revenue: float|null }>
     */
    public static function get_recent_events( $limit = 10 ) {
        global $wpdb;
        $limit = max( 1, min( 50, (int) $limit ) );
        $cache_key = 'meyvora_cro_' . md5( 'recent_events_' . serialize( array( $limit ) ) );
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return $cached;
        }
        $analytics       = new self();
        $events_table    = $analytics->events_table;
        $campaigns_table = $analytics->campaigns_table;
        if ( ! $analytics->cache_table_exists( $events_table ) ) {
            wp_cache_set( $cache_key, array(), self::CACHE_GROUP, self::CACHE_TTL );
            return array();
        }
        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
        $wpdb->prepare(
                'SELECT e.created_at, e.event_type, e.source_type, c.name AS campaign_name, e.order_value AS revenue
                 FROM %i e
                 LEFT JOIN %i c ON e.source_type = \'campaign\' AND e.source_id = c.id
                 ORDER BY e.created_at DESC
                 LIMIT %d',
                $events_table,
                $campaigns_table,
                $limit
            ),
            ARRAY_A
        );
        $rows = is_array( $rows ) ? $rows : array();
        wp_cache_set( $cache_key, $rows, self::CACHE_GROUP, self::CACHE_TTL );
        return $rows;
    }

    /**
     * Whether WooCommerce uses the HPOS orders table.
     *
     * @return bool
     */
    private function wc_orders_use_hpos() {
        return class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }

    /**
     * Revenue from conversion events grouped by campaign (source_id).
     *
     * @param string   $date_from   Y-m-d.
     * @param string   $date_to     Y-m-d.
     * @param int|null $campaign_id Optional. Limit to one campaign.
     * @return array<int, array{ campaign_id: int, campaign_name: string, total_revenue: float, order_count: int }>
     */
    public function get_revenue_by_campaign( $date_from, $date_to, $campaign_id = null ) {
        global $wpdb;

        $campaign_id = ( $campaign_id !== null && $campaign_id > 0 ) ? absint( $campaign_id ) : null;
        $cache_key   = 'meyvora_cro_' . md5( 'revenue_by_campaign_' . serialize( array( $date_from, $date_to, $campaign_id ) ) );
        $cached      = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return $cached;
        }

        $events_table    = $this->events_table;
        $campaigns_table = $this->campaigns_table;
        $events_ok       = $this->cache_table_exists( $events_table );
        $campaigns_ok    = $this->cache_table_exists( $campaigns_table );
        if ( ! $events_ok ) {
            wp_cache_set( $cache_key, array(), self::CACHE_GROUP, self::CACHE_TTL );
            return array();
        }

        $et = $this->events_table;
        $ct = $this->campaigns_table;

        if ( $campaign_id ) {
            if ( $campaigns_ok ) {
                $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT
                        e.source_id AS campaign_id,
                        COALESCE(MAX(c.name), CONCAT(\'Campaign #\', e.source_id)) AS campaign_name,
                        COALESCE(SUM(e.order_value), 0) AS total_revenue,
                        COUNT(DISTINCT CASE WHEN e.order_id IS NOT NULL AND e.order_id > 0 THEN e.order_id ELSE e.id END) AS order_count
                    FROM %i e
                    LEFT JOIN %i c ON c.id = e.source_id
                    WHERE e.created_at >= %s AND e.created_at < DATE_ADD(%s, INTERVAL 1 DAY)
                    AND e.event_type = \'conversion\'
                    AND e.order_value IS NOT NULL
                    AND e.source_type = \'campaign\'
                    AND e.source_id = %d
                    GROUP BY e.source_id',
                        $et,
                        $ct,
                        $date_from,
                        $date_to,
                        $campaign_id
                    ),
                    ARRAY_A
                );
            } else {
                $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT
                        e.source_id AS campaign_id,
                        CONCAT(\'Campaign #\', e.source_id) AS campaign_name,
                        COALESCE(SUM(e.order_value), 0) AS total_revenue,
                        COUNT(DISTINCT CASE WHEN e.order_id IS NOT NULL AND e.order_id > 0 THEN e.order_id ELSE e.id END) AS order_count
                    FROM %i e
                    WHERE e.created_at >= %s AND e.created_at < DATE_ADD(%s, INTERVAL 1 DAY)
                    AND e.event_type = \'conversion\'
                    AND e.order_value IS NOT NULL
                    AND e.source_type = \'campaign\'
                    AND e.source_id = %d
                    GROUP BY e.source_id',
                        $et,
                        $date_from,
                        $date_to,
                        $campaign_id
                    ),
                    ARRAY_A
                );
            }
        } elseif ( $campaigns_ok ) {
            $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
            $wpdb->prepare(
                    'SELECT
                        e.source_id AS campaign_id,
                        COALESCE(MAX(c.name), CONCAT(\'Campaign #\', e.source_id)) AS campaign_name,
                        COALESCE(SUM(e.order_value), 0) AS total_revenue,
                        COUNT(DISTINCT CASE WHEN e.order_id IS NOT NULL AND e.order_id > 0 THEN e.order_id ELSE e.id END) AS order_count
                    FROM %i e
                    LEFT JOIN %i c ON c.id = e.source_id
                    WHERE e.created_at >= %s AND e.created_at < DATE_ADD(%s, INTERVAL 1 DAY)
                    AND e.event_type = \'conversion\'
                    AND e.order_value IS NOT NULL
                    AND e.source_type = \'campaign\'
                    AND e.source_id IS NOT NULL AND e.source_id > 0
                    GROUP BY e.source_id
                    ORDER BY total_revenue DESC',
                    $et,
                    $ct,
                    $date_from,
                    $date_to
                ),
                ARRAY_A
            );
        } else {
            $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
            $wpdb->prepare(
                    'SELECT
                        e.source_id AS campaign_id,
                        CONCAT(\'Campaign #\', e.source_id) AS campaign_name,
                        COALESCE(SUM(e.order_value), 0) AS total_revenue,
                        COUNT(DISTINCT CASE WHEN e.order_id IS NOT NULL AND e.order_id > 0 THEN e.order_id ELSE e.id END) AS order_count
                    FROM %i e
                    WHERE e.created_at >= %s AND e.created_at < DATE_ADD(%s, INTERVAL 1 DAY)
                    AND e.event_type = \'conversion\'
                    AND e.order_value IS NOT NULL
                    AND e.source_type = \'campaign\'
                    AND e.source_id IS NOT NULL AND e.source_id > 0
                    GROUP BY e.source_id
                    ORDER BY total_revenue DESC',
                    $et,
                    $date_from,
                    $date_to
                ),
                ARRAY_A
            );
        }

        $out = array();
        foreach ( is_array( $rows ) ? $rows : array() as $row ) {
            $out[] = array(
                'campaign_id'    => (int) ( $row['campaign_id'] ?? 0 ),
                'campaign_name'  => (string) ( $row['campaign_name'] ?? '' ),
                'total_revenue'  => (float) ( $row['total_revenue'] ?? 0 ),
                'order_count'    => (int) ( $row['order_count'] ?? 0 ),
            );
        }
        wp_cache_set( $cache_key, $out, self::CACHE_GROUP, self::CACHE_TTL );
        return $out;
    }

    /**
     * Conversion funnel counts from events (and cro_emails for capture step).
     *
     * @param string   $date_from   Y-m-d.
     * @param string   $date_to     Y-m-d.
     * @param int|null $campaign_id Optional. Restrict to campaign-sourced events.
     * @return array{ impressions: int, clicks: int, emails_captured: int, orders: int }
     */
    public function get_conversion_funnel( $date_from, $date_to, $campaign_id = null ) {
        global $wpdb;

        $campaign_id = ( $campaign_id !== null && $campaign_id > 0 ) ? absint( $campaign_id ) : null;
        $cache_key   = 'meyvora_cro_' . md5( 'conversion_funnel_' . serialize( array( $date_from, $date_to, $campaign_id ) ) );
        $cached      = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return $cached;
        }

        $events_table = $this->events_table;
        if ( ! $this->cache_table_exists( $events_table ) ) {
            $empty = array(
                'impressions'      => 0,
                'clicks'           => 0,
                'emails_captured'  => 0,
                'orders'           => 0,
            );
            wp_cache_set( $cache_key, $empty, self::CACHE_GROUP, self::CACHE_TTL );
            return $empty;
        }

        $et = $this->events_table;

        if ( $campaign_id ) {
            $impressions = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
            $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'impression\' AND source_type = %s AND source_id = %d',
                    $et,
                    $date_from,
                    $date_to,
                    'campaign',
                    $campaign_id
                )
            );
            $clicks      = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
            $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'interaction\' AND source_type = %s AND source_id = %d',
                    $et,
                    $date_from,
                    $date_to,
                    'campaign',
                    $campaign_id
                )
            );
            $orders      = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
            $wpdb->prepare(
                    'SELECT COUNT(DISTINCT CASE WHEN order_id IS NOT NULL AND order_id > 0 THEN order_id ELSE id END) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'conversion\' AND source_type = %s AND source_id = %d',
                    $et,
                    $date_from,
                    $date_to,
                    'campaign',
                    $campaign_id
                )
            );
        } else {
            $impressions = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
            $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'impression\'',
                    $et,
                    $date_from,
                    $date_to
                )
            );
            $clicks      = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
            $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'interaction\'',
                    $et,
                    $date_from,
                    $date_to
                )
            );
            $orders      = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
            $wpdb->prepare(
                    'SELECT COUNT(DISTINCT CASE WHEN order_id IS NOT NULL AND order_id > 0 THEN order_id ELSE id END) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'conversion\'',
                    $et,
                    $date_from,
                    $date_to
                )
            );
        }

        $emails_table = $wpdb->prefix . 'cro_emails';
        $emails_ok    = $this->cache_table_exists( $emails_table );
        $emails       = 0;
        if ( $emails_ok ) {
            if ( $campaign_id ) {
                $emails = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT COUNT(*) FROM %i WHERE subscribed_at >= %s AND subscribed_at < DATE_ADD(%s, INTERVAL 1 DAY) AND source_type = %s AND source_id = %d',
                        $emails_table,
                        $date_from,
                        $date_to,
                        'campaign',
                        $campaign_id
                    )
                );
            } else {
                $emails = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT COUNT(*) FROM %i WHERE subscribed_at >= %s AND subscribed_at < DATE_ADD(%s, INTERVAL 1 DAY)',
                        $emails_table,
                        $date_from,
                        $date_to
                    )
                );
            }
        } elseif ( $campaign_id ) {
            $emails = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
            $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'conversion\' AND email IS NOT NULL AND email != \'\' AND source_type = %s AND source_id = %d',
                    $et,
                    $date_from,
                    $date_to,
                    'campaign',
                    $campaign_id
                )
            );
        } else {
            $emails = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
            $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'conversion\' AND email IS NOT NULL AND email != \'\'',
                    $et,
                    $date_from,
                    $date_to
                )
            );
        }

        $return = array(
            'impressions'     => $impressions,
            'clicks'          => $clicks,
            'emails_captured' => $emails,
            'orders'          => $orders,
        );
        wp_cache_set( $cache_key, $return, self::CACHE_GROUP, self::CACHE_TTL );
        return $return;
    }

    /**
     * Weekly abandoned-cart recovery cohorts (up to 8 ISO weeks in range).
     *
     * @param string $date_from Y-m-d.
     * @param string $date_to   Y-m-d.
     * @return array<int, array{ week_label: string, total_abandoned: int, recovered: int, recovery_rate: float }>
     */
    public function get_cohort_recovery( $date_from, $date_to ) {
        global $wpdb;

        $cache_key = 'meyvora_cro_' . md5( 'cohort_recovery_' . serialize( array( $date_from, $date_to ) ) );
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return $cached;
        }

        $table    = $wpdb->prefix . 'cro_abandoned_carts';
        $table_ok = $this->cache_table_exists( $table );
        if ( ! $table_ok ) {
            wp_cache_set( $cache_key, array(), self::CACHE_GROUP, self::CACHE_TTL );
            return array();
        }

        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
        $wpdb->prepare(
                'SELECT 
                    YEARWEEK(created_at, 3) AS yw,
                    MIN(DATE(created_at)) AS week_start,
                    COUNT(*) AS total_abandoned,
                    SUM(CASE WHEN status = \'recovered\' OR recovered_at IS NOT NULL THEN 1 ELSE 0 END) AS recovered
                FROM %i
                WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY)
                GROUP BY YEARWEEK(created_at, 3)
                ORDER BY yw DESC
                LIMIT 8',
                $table,
                $date_from,
                $date_to
            ),
            ARRAY_A
        );

        $rows = is_array( $rows ) ? array_reverse( $rows ) : array();
        $out  = array();
        foreach ( $rows as $row ) {
            $total = (int) ( $row['total_abandoned'] ?? 0 );
            $rec   = (int) ( $row['recovered'] ?? 0 );
            $rate  = $total > 0 ? round( ( $rec / $total ) * 100, 2 ) : 0.0;
            $start = isset( $row['week_start'] ) ? (string) $row['week_start'] : '';
            $out[] = array(
                'week_label'       => $start
                    ? sprintf(
                        /* translators: %s: week start date */
                        __( 'Week of %s', 'meyvora-convert' ),
                        wp_date( 'M j, Y', strtotime( $start ) )
                    )
                    : '',
                'total_abandoned'  => $total,
                'recovered'        => $rec,
                'recovery_rate'    => $rate,
            );
        }
        wp_cache_set( $cache_key, $out, self::CACHE_GROUP, self::CACHE_TTL );
        return $out;
    }

    /**
     * Email capture rate: cro_emails rows in period / impressions × 100.
     *
     * @param string   $date_from   Y-m-d.
     * @param string   $date_to     Y-m-d.
     * @param int|null $campaign_id Optional.
     * @return float
     */
    public function get_email_capture_rate( $date_from, $date_to, $campaign_id = null ) {
        global $wpdb;

        $campaign_id = ( $campaign_id !== null && $campaign_id > 0 ) ? absint( $campaign_id ) : null;
        $cache_key   = 'meyvora_cro_' . md5( 'email_capture_rate_' . serialize( array( $date_from, $date_to, $campaign_id ) ) );
        $cached      = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return (float) $cached;
        }

        $events_table = $this->events_table;
        $events_ok    = $this->cache_table_exists( $events_table );
        if ( ! $events_ok ) {
            wp_cache_set( $cache_key, 0.0, self::CACHE_GROUP, self::CACHE_TTL );
            return 0.0;
        }
        if ( $campaign_id ) {
            $impressions = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
            $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'impression\' AND source_type = \'campaign\' AND source_id = %d',
                    $events_table,
                    $date_from,
                    $date_to,
                    $campaign_id
                )
            );
        } else {
            $impressions = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
            $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND event_type = \'impression\'',
                    $events_table,
                    $date_from,
                    $date_to
                )
            );
        }

        $emails_table = $wpdb->prefix . 'cro_emails';
        $emails_ok    = $this->cache_table_exists( $emails_table );
        $captures     = 0;
        if ( $emails_ok ) {
            if ( $campaign_id ) {
                $captures = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT COUNT(*) FROM %i WHERE subscribed_at >= %s AND subscribed_at < DATE_ADD(%s, INTERVAL 1 DAY) AND source_type = %s AND source_id = %d',
                        $emails_table,
                        $date_from,
                        $date_to,
                        'campaign',
                        $campaign_id
                    )
                );
            } else {
                $captures = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
                $wpdb->prepare(
                        'SELECT COUNT(*) FROM %i WHERE subscribed_at >= %s AND subscribed_at < DATE_ADD(%s, INTERVAL 1 DAY)',
                        $emails_table,
                        $date_from,
                        $date_to
                    )
                );
            }
        }

        if ( $impressions <= 0 ) {
            wp_cache_set( $cache_key, 0.0, self::CACHE_GROUP, self::CACHE_TTL );
            return 0.0;
        }
        $rate = round( ( $captures / $impressions ) * 100, 2 );
        wp_cache_set( $cache_key, $rate, self::CACHE_GROUP, self::CACHE_TTL );
        return $rate;
    }

    /**
     * Offer revenue from orders linked in cro_offer_logs (MYV- coupon prefix).
     *
     * @param string $date_from Y-m-d.
     * @param string $date_to   Y-m-d.
     * @return array<int, array{ offer_id: int, offer_name: string, total_orders: int, total_revenue: float }>
     */
    public function get_offer_revenue_attribution( $date_from, $date_to ) {
        global $wpdb;

        $cache_key = 'meyvora_cro_' . md5( 'offer_revenue_attr_' . serialize( array( $date_from, $date_to, $this->wc_orders_use_hpos() ? 1 : 0 ) ) );
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return $cached;
        }

        $logs_table   = $wpdb->prefix . 'cro_offer_logs';
        $offers_table = $wpdb->prefix . 'cro_offers';
        $logs_ok      = $this->cache_table_exists( $logs_table );
        $offers_ok    = $this->cache_table_exists( $offers_table );
        if ( ! $logs_ok || ! $offers_ok ) {
            wp_cache_set( $cache_key, array(), self::CACHE_GROUP, self::CACHE_TTL );
            return array();
        }

        $prefix_like = $wpdb->esc_like( 'MYV-' ) . '%';

        if ( $this->wc_orders_use_hpos() ) {
            $orders_table = $wpdb->prefix . 'wc_orders';
            $orders_ok    = $this->cache_table_exists( $orders_table );
            if ( ! $orders_ok ) {
                wp_cache_set( $cache_key, array(), self::CACHE_GROUP, self::CACHE_TTL );
                return array();
            }
            $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
            $wpdb->prepare(
                    'SELECT 
                        l.offer_id,
                        MAX(o.name) AS offer_name,
                        COUNT(DISTINCT l.order_id) AS total_orders,
                        COALESCE(SUM(CAST(ord.total_amount AS DECIMAL(12,4))), 0) AS total_revenue
                    FROM %i l
                    INNER JOIN %i o ON o.id = l.offer_id
                    INNER JOIN %i ord ON ord.id = l.order_id AND ord.type = %s AND ord.status NOT IN (\'trash\',\'draft\',\'auto-draft\')
                    WHERE l.created_at >= %s AND l.created_at < DATE_ADD(%s, INTERVAL 1 DAY)
                    AND l.order_id IS NOT NULL AND l.order_id > 0
                    AND l.coupon_code IS NOT NULL AND LOWER(l.coupon_code) LIKE LOWER(%s)
                    GROUP BY l.offer_id',
                    $logs_table,
                    $offers_table,
                    $orders_table,
                    'shop_order',
                    $date_from,
                    $date_to,
                    $prefix_like
                ),
                ARRAY_A
            );
        } else {
            $posts = $wpdb->posts;
            $pm    = $wpdb->postmeta;
            $rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
            $wpdb->prepare(
                    'SELECT 
                        l.offer_id,
                        MAX(o.name) AS offer_name,
                        COUNT(DISTINCT l.order_id) AS total_orders,
                        COALESCE(SUM(CAST(pm.meta_value AS DECIMAL(12,4))), 0) AS total_revenue
                    FROM %i l
                    INNER JOIN %i o ON o.id = l.offer_id
                    INNER JOIN %i p ON p.ID = l.order_id AND p.post_type = %s
                    INNER JOIN %i pm ON pm.post_id = p.ID AND pm.meta_key = %s
                    WHERE l.created_at >= %s AND l.created_at < DATE_ADD(%s, INTERVAL 1 DAY)
                    AND l.order_id IS NOT NULL AND l.order_id > 0
                    AND l.coupon_code IS NOT NULL AND LOWER(l.coupon_code) LIKE LOWER(%s)
                    GROUP BY l.offer_id',
                    $logs_table,
                    $offers_table,
                    $posts,
                    'shop_order',
                    $pm,
                    '_order_total',
                    $date_from,
                    $date_to,
                    $prefix_like
                ),
                ARRAY_A
            );
        }

        $out = array();
        foreach ( is_array( $rows ) ? $rows : array() as $row ) {
            $out[] = array(
                'offer_id'      => (int) ( $row['offer_id'] ?? 0 ),
                'offer_name'    => (string) ( $row['offer_name'] ?? '' ),
                'total_orders'  => (int) ( $row['total_orders'] ?? 0 ),
                'total_revenue' => (float) ( $row['total_revenue'] ?? 0 ),
            );
        }
        wp_cache_set( $cache_key, $out, self::CACHE_GROUP, self::CACHE_TTL );
        return $out;
    }

    /**
     * A/B tests summary for analytics (running, paused, completed in/around range).
     *
     * @param string $date_from Y-m-d.
     * @param string $date_to   Y-m-d.
     * @return array<int, array<string, mixed>>
     */
    public function get_ab_test_summary( $date_from, $date_to ) {
        global $wpdb;

        $cache_key = 'meyvora_cro_' . md5( 'ab_test_summary_' . serialize( array( $date_from, $date_to ) ) );
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return $cached;
        }

        if ( ! class_exists( 'CRO_AB_Test' ) || ! class_exists( 'CRO_AB_Statistics' ) ) {
            wp_cache_set( $cache_key, array(), self::CACHE_GROUP, self::CACHE_TTL );
            return array();
        }

        $tests_table = $wpdb->prefix . 'cro_ab_tests';
        $tests_ok    = $this->cache_table_exists( $tests_table );
        if ( ! $tests_ok ) {
            wp_cache_set( $cache_key, array(), self::CACHE_GROUP, self::CACHE_TTL );
            return array();
        }

        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
        $wpdb->prepare(
                'SELECT * FROM %i
                WHERE status != %s
                AND status IN (\'running\',\'paused\',\'completed\')
                ORDER BY 
                    CASE status WHEN \'running\' THEN 1 WHEN \'paused\' THEN 2 ELSE 3 END,
                    COALESCE(started_at, created_at) DESC
                LIMIT 40',
                $tests_table,
                'draft'
            ),
            ARRAY_A
        );
        $rows = apply_filters( 'cro_analytics_ab_test_summary_raw_rows', $rows, $date_from, $date_to );
        if ( ! is_array( $rows ) ) {
            $rows = array();
        }

        $model = new CRO_AB_Test();
        $out   = array();
        foreach ( is_array( $rows ) ? $rows : array() as $row ) {
            $tid = (int) ( $row['id'] ?? 0 );
            if ( ! $tid ) {
                continue;
            }
            $test = $model->get( $tid );
            if ( ! $test || empty( $test->variations ) ) {
                continue;
            }
            $stats = in_array( $test->status, array( 'running', 'paused', 'completed' ), true )
                ? CRO_AB_Statistics::calculate( $test )
                : null;

            $winner_name = '—';
            if ( is_array( $stats ) && ! empty( $stats['has_winner'] ) && ! empty( $stats['winner']['variation_name'] ) ) {
                $winner_name = $stats['winner']['variation_name'];
            }

            if ( ! CRO_AB_Statistics::has_reached_sample_size( $test ) ) {
                $sig_label = __( 'Pending data', 'meyvora-convert' );
            } elseif ( is_array( $stats ) && ! empty( $stats['has_winner'] ) ) {
                $sig_label = __( 'Significant', 'meyvora-convert' );
            } else {
                $sig_label = __( 'Not significant', 'meyvora-convert' );
            }

            $vars_out = array();
            foreach ( $test->variations as $v ) {
                $imp = (int) ( $v->impressions ?? 0 );
                $conv = (int) ( $v->conversions ?? 0 );
                $rate = $imp > 0 ? round( ( $conv / $imp ) * 100, 2 ) : 0.0;
                $vars_out[] = array(
                    'id'           => (int) $v->id,
                    'name'         => (string) $v->name,
                    'impressions'  => $imp,
                    'conversions'  => $conv,
                    'rate'         => $rate,
                    'rate_display' => number_format_i18n( $rate, 2 ) . '%',
                );
            }

            $started = ! empty( $test->started_at )
                ? wp_date( get_option( 'date_format' ), strtotime( $test->started_at ) )
                : '—';

            $out[] = array(
                'id'              => $tid,
                'name'            => (string) $test->name,
                'status'          => (string) $test->status,
                'variants_count'  => count( $test->variations ),
                'winner'          => $winner_name,
                'significance'    => $sig_label,
                'started'         => $started,
                'variations'      => $vars_out,
            );
        }

        wp_cache_set( $cache_key, $out, self::CACHE_GROUP, self::CACHE_TTL );
        return $out;
    }

    /**
     * Abandoned cart rows for CSV export (no raw email).
     *
     * @param string $date_from Y-m-d.
     * @param string $date_to   Y-m-d.
     * @param int    $limit     Max rows.
     * @return array<int, array<string, mixed>>
     */
    public function get_abandoned_carts_export_rows( $date_from, $date_to, $limit = 2000 ) {
        global $wpdb;

        $limit = max( 1, min( 10000, absint( $limit ) ) );

        $cache_key = 'meyvora_cro_' . md5( 'abandoned_carts_export_' . serialize( array( $date_from, $date_to, $limit ) ) );
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return $cached;
        }

        $table    = $wpdb->prefix . 'cro_abandoned_carts';
        $table_ok = $this->cache_table_exists( $table );
        if ( ! $table_ok ) {
            wp_cache_set( $cache_key, array(), self::CACHE_GROUP, self::CACHE_TTL );
            return array();
        }

        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
        $wpdb->prepare(
                'SELECT id, created_at, status, recovered_at,
                    CASE WHEN email IS NOT NULL AND email != \'\' THEN 1 ELSE 0 END AS has_email
                FROM %i
                WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY)
                ORDER BY created_at DESC
                LIMIT %d',
                $table,
                $date_from,
                $date_to,
                $limit
            ),
            ARRAY_A
        );
        wp_cache_set( $cache_key, is_array( $rows ) ? $rows : array(), self::CACHE_GROUP, self::CACHE_TTL );
        return is_array( $rows ) ? $rows : array();
    }
}
