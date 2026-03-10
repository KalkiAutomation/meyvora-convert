<?php
/**
 * Analytics Data Model
 */
class CRO_Analytics {
    
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
        $analytics = new self();
        $summary   = $analytics->get_summary( $date_from, $date_to );

        global $wpdb;
        $events_table = $wpdb->prefix . 'cro_events';
        $coupons      = 0;
        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $events_table ) );
        if ( $table_exists ) {
            $coupons = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$events_table} WHERE DATE(created_at) BETWEEN %s AND %s AND event_type = 'conversion' AND coupon_code IS NOT NULL AND coupon_code != ''",
                $date_from,
                $date_to
            ) );
        }

        return array(
            'revenue_attributed'   => $summary['revenue'],
            'total_conversions'    => $summary['conversions'],
            'total_impressions'    => $summary['impressions'],
            'conversion_rate'      => $summary['conversion_rate'],
            'emails_captured'      => $summary['emails'],
            'coupons_redeemed'     => $coupons,
        );
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
        global $wpdb;

        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $this->events_table ) );
        if ( ! $table_exists ) {
            return $this->empty_summary();
        }

        $date_from = $date_from ?: wp_date( 'Y-m-d', strtotime( '-30 days' ) );
        $date_to   = $date_to ?: wp_date( 'Y-m-d' );
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
     * Internal summary with optional campaign filter.
     *
     * @param string     $date_from    Y-m-d.
     * @param string     $date_to      Y-m-d.
     * @param int|null   $campaign_id  Optional. Filter by campaign.
     * @return array
     */
    private function get_summary_internal( $date_from, $date_to, $campaign_id = null ) {
        global $wpdb;

        $campaign_where = '';
        $campaign_join  = '';
        if ( $campaign_id !== null && $campaign_id > 0 ) {
            $campaign_id  = absint( $campaign_id );
            $campaign_where = $wpdb->prepare( " AND source_type = 'campaign' AND source_id = %d", $campaign_id );
        }
        $booster_where = ''; // Sticky/shipping are not campaign-scoped.

        $where = $wpdb->prepare(
            "WHERE DATE(created_at) BETWEEN %s AND %s",
            $date_from,
            $date_to
        );

        $impressions = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->events_table} {$where} AND event_type = 'impression' {$campaign_where}"
        );

        $clicks = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->events_table} {$where} AND event_type = 'interaction' AND source_type = 'campaign' " . ( $campaign_id ? $wpdb->prepare( 'AND source_id = %d', $campaign_id ) : '' )
        );

        $conversions = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->events_table} {$where} AND event_type = 'conversion' {$campaign_where}"
        );

        $revenue = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(order_value), 0) FROM {$this->events_table} {$where} AND event_type = 'conversion' {$campaign_where}"
        );

        $emails = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->events_table} {$where} AND event_type = 'conversion' AND email IS NOT NULL {$campaign_where}"
        );

        $sticky_cart_adds = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->events_table} {$where} AND source_type = 'sticky_cart' AND event_type = 'interaction'"
        );

        $shipping_bar_interactions = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->events_table} {$where} AND source_type = 'shipping_bar' AND event_type = 'interaction'"
        );

        $conversion_rate = $impressions > 0 ? ( $conversions / $impressions ) * 100 : 0;
        $ctr = $impressions > 0 ? ( $clicks / $impressions ) * 100 : 0;
        $rpv = $impressions > 0 ? $revenue / $impressions : 0;

        $days = ( strtotime( $date_to ) - strtotime( $date_from ) ) / 86400;
        $prev_from = wp_date( 'Y-m-d', strtotime( $date_from . " -{$days} days" ) );
        $prev_to   = wp_date( 'Y-m-d', strtotime( $date_from . ' -1 day' ) );
        $prev_where = $wpdb->prepare(
            "WHERE DATE(created_at) BETWEEN %s AND %s",
            $prev_from,
            $prev_to
        );
        $prev_campaign_where = $campaign_id !== null && $campaign_id > 0 ? $wpdb->prepare( " AND source_type = 'campaign' AND source_id = %d", $campaign_id ) : '';
        $prev_impressions = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->events_table} {$prev_where} AND event_type = 'impression' {$prev_campaign_where}"
        );
        $prev_conversions = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->events_table} {$prev_where} AND event_type = 'conversion' {$prev_campaign_where}"
        );
        $prev_revenue = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(order_value), 0) FROM {$this->events_table} {$prev_where} AND event_type = 'conversion' {$prev_campaign_where}"
        );
        $prev_emails = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->events_table} {$prev_where} AND event_type = 'conversion' AND email IS NOT NULL {$prev_campaign_where}"
        );

        return array(
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

        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $this->events_table ) );
        if ( ! $table_exists ) {
            return array();
        }

        $campaign_where = '';
        if ( $campaign_id !== null && $campaign_id > 0 ) {
            $campaign_id = absint( $campaign_id );
            $campaign_where = $wpdb->prepare( " AND source_type = 'campaign' AND source_id = %d", $campaign_id );
        }

        $sql = "SELECT 
                DATE(created_at) as date,
                SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions,
                SUM(CASE WHEN event_type = 'conversion' THEN 1 ELSE 0 END) as conversions,
                SUM(CASE WHEN event_type = 'conversion' THEN order_value ELSE 0 END) as revenue
            FROM {$this->events_table}
            WHERE DATE(created_at) BETWEEN %s AND %s {$campaign_where}
            GROUP BY DATE(created_at)
            ORDER BY date ASC";
        $results = $wpdb->get_results( $wpdb->prepare( $sql, $date_from, $date_to ), ARRAY_A );

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

        return $filled;
    }
    
    /**
     * Get campaign performance
     */
    public function get_campaign_performance($date_from, $date_to, $limit = 10) {
        global $wpdb;
        
        // Check if tables exist
        $events_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->events_table));
        $campaigns_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->campaigns_table));
        
        if (!$events_exists || !$campaigns_exists) {
            return array();
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                c.id,
                c.name,
                c.status,
                COUNT(CASE WHEN e.event_type = 'impression' THEN 1 END) as impressions,
                COUNT(CASE WHEN e.event_type = 'conversion' THEN 1 END) as conversions,
                COALESCE(SUM(CASE WHEN e.event_type = 'conversion' THEN e.order_value END), 0) as revenue,
                COUNT(CASE WHEN e.event_type = 'conversion' AND e.email IS NOT NULL AND e.email != '' THEN 1 END) as emails
            FROM {$this->campaigns_table} c
            LEFT JOIN {$this->events_table} e ON e.source_type = 'campaign' AND e.source_id = c.id 
                AND DATE(e.created_at) BETWEEN %s AND %s
            GROUP BY c.id
            ORDER BY revenue DESC
            LIMIT %d",
            $date_from, $date_to, $limit
        ), ARRAY_A);
    }
    
    /**
     * Get device breakdown
     */
    public function get_device_stats($date_from, $date_to) {
        global $wpdb;
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->events_table));
        
        if (!$table_exists) {
            return array();
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                COALESCE(device_type, 'unknown') as device,
                COUNT(CASE WHEN event_type = 'impression' THEN 1 END) as impressions,
                COUNT(CASE WHEN event_type = 'conversion' THEN 1 END) as conversions
            FROM {$this->events_table}
            WHERE DATE(created_at) BETWEEN %s AND %s
            GROUP BY device_type",
            $date_from, $date_to
        ), ARRAY_A);
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

        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $this->events_table ) );
        if ( ! $table_exists ) {
            return array();
        }

        $campaign_where = '';
        if ( $campaign_id !== null && $campaign_id > 0 ) {
            $campaign_id = absint( $campaign_id );
            $campaign_where = $wpdb->prepare( " AND source_type = 'campaign' AND source_id = %d", $campaign_id );
        }

        $sql = "SELECT 
                page_url,
                COUNT(CASE WHEN event_type = 'impression' THEN 1 END) as impressions,
                COUNT(CASE WHEN event_type = 'conversion' THEN 1 END) as conversions,
                COALESCE(SUM(CASE WHEN event_type = 'conversion' THEN order_value END), 0) as revenue
            FROM {$this->events_table}
            WHERE DATE(created_at) BETWEEN %s AND %s {$campaign_where}
            GROUP BY page_url
            ORDER BY conversions DESC
            LIMIT %d";
        return $wpdb->get_results( $wpdb->prepare( $sql, $date_from, $date_to, $limit ), ARRAY_A );
    }

    /**
     * Get list of campaigns for filter dropdown.
     *
     * @return array Array of id => name.
     */
    public function get_campaigns_list() {
        global $wpdb;
        $campaigns_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $this->campaigns_table ) );
        if ( ! $campaigns_exists ) {
            return array();
        }
        $rows = $wpdb->get_results( "SELECT id, name FROM {$this->campaigns_table} ORDER BY name ASC", ARRAY_A );
        $list = array();
        foreach ( $rows as $row ) {
            $list[ (int) $row['id'] ] = $row['name'];
        }
        return $list;
    }

    /**
     * Export events for CSV (raw rows: created_at, event_type, source_type, source_id, session_id, user_id, page_type, page_url, metadata).
     *
     * @param string   $date_from   Y-m-d.
     * @param string   $date_to     Y-m-d.
     * @param int|null $campaign_id Optional. Filter by campaign.
     * @return array
     */
    public function export_events_for_csv( $date_from, $date_to, $campaign_id = null ) {
        global $wpdb;

        $events_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $this->events_table ) );
        if ( ! $events_exists ) {
            return array();
        }

        $campaign_where = '';
        if ( $campaign_id !== null && $campaign_id > 0 ) {
            $campaign_id   = absint( $campaign_id );
            $campaign_where = $wpdb->prepare( " AND e.source_type = 'campaign' AND e.source_id = %d", $campaign_id );
        }

        $sql = "SELECT 
                e.created_at,
                e.event_type,
                e.source_type,
                e.source_id,
                e.session_id,
                e.user_id,
                e.page_type,
                e.page_url,
                e.metadata
            FROM {$this->events_table} e
            WHERE DATE(e.created_at) BETWEEN %s AND %s {$campaign_where}
            ORDER BY e.created_at ASC";
        return $wpdb->get_results( $wpdb->prepare( $sql, $date_from, $date_to ), ARRAY_A );
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

        $events_table = $this->events_table;
        $events_ok    = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $events_table ) ) === $events_table;
        $offer_logs   = $wpdb->prefix . 'cro_offer_logs';
        $logs_ok      = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $offer_logs ) ) === $offer_logs;

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
            $imp_conv = $wpdb->get_results( $wpdb->prepare(
                "SELECT DATE(created_at) AS d, 
                    SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) AS impressions,
                    SUM(CASE WHEN event_type = 'conversion' THEN 1 ELSE 0 END) AS conversions,
                    SUM(CASE WHEN event_type = 'interaction' AND source_type = 'campaign' THEN 1 ELSE 0 END) AS campaign_clicks
                FROM {$events_table} WHERE DATE(created_at) BETWEEN %s AND %s GROUP BY DATE(created_at)",
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
            $ab = $wpdb->get_results( $wpdb->prepare(
                "SELECT DATE(created_at) AS d, COUNT(*) AS cnt FROM {$events_table} 
                WHERE DATE(created_at) BETWEEN %s AND %s AND event_type = 'impression' AND metadata IS NOT NULL AND (metadata LIKE %s OR metadata LIKE %s)
                GROUP BY DATE(created_at)",
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
            $has_action = $wpdb->get_var( "SHOW COLUMNS FROM {$offer_logs} LIKE 'action'" );
            $action_where = $has_action ? " AND action = 'applied'" : '';
            $apply = $wpdb->get_results( $wpdb->prepare(
                "SELECT DATE(created_at) AS d, COUNT(*) AS cnt FROM {$offer_logs} WHERE DATE(created_at) BETWEEN %s AND %s {$action_where} GROUP BY DATE(created_at)",
                $date_from,
                $date_to
            ), ARRAY_A );
            foreach ( is_array( $apply ) ? $apply : array() as $row ) {
                $d = $row['d'] ?? '';
                if ( isset( $days[ $d ] ) ) {
                    $days[ $d ]['offer_applies'] = (int) ( $row['cnt'] ?? 0 );
                }
            }
        }

        return array_values( $days );
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
        $analytics = new self();
        $events_table = $analytics->events_table;
        $campaigns_table = $analytics->campaigns_table;
        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $events_table ) );
        if ( ! $table_exists ) {
            return array();
        }
        $limit = max( 1, min( 50, (int) $limit ) );
        $sql = $wpdb->prepare(
            "SELECT e.created_at, e.event_type, e.source_type, c.name AS campaign_name, e.order_value AS revenue
             FROM {$events_table} e
             LEFT JOIN {$campaigns_table} c ON e.source_type = 'campaign' AND e.source_id = c.id
             ORDER BY e.created_at DESC
             LIMIT %d",
            $limit
        );
        $rows = $wpdb->get_results( $sql, ARRAY_A );
        return is_array( $rows ) ? $rows : array();
    }
}
