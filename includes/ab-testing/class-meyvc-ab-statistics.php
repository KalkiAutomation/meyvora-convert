<?php
/**
 * A/B Test Statistical Calculator
 * 
 * Uses Z-test for proportion comparison
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MEYVC_AB_Statistics {
    
    private static $z_scores = array(
        80 => 1.28,
        85 => 1.44,
        90 => 1.645,
        95 => 1.96,
        99 => 2.576,
    );
    
    /**
     * Calculate full statistics for a test
     */
    public static function calculate($test) {
        $variations = $test->variations;
        
        if (count($variations) < 2) {
            return null;
        }
        
        // Find control
        $control = null;
        $challengers = array();
        
        foreach ($variations as $variation) {
            if ($variation->is_control) {
                $control = $variation;
            } else {
                $challengers[] = $variation;
            }
        }
        
        if (!$control) {
            $control = $variations[0];
            $challengers = array_slice($variations, 1);
        }
        
        $min_sample_reached = self::has_reached_sample_size( $test );
        $results = array(
            'control' => self::get_variation_stats($control),
            'challengers' => array(),
            'has_winner' => false,
            'winner' => null,
            'confidence_level' => (int) $test->confidence_level,
            'min_sample_size' => (int) $test->min_sample_size,
            'enough_data' => $min_sample_reached,
        );

        foreach ($challengers as $challenger) {
            $stats = self::compare_variations($control, $challenger, $test->confidence_level);
            $results['challengers'][$challenger->id] = $stats;

            // Only declare a winner when minimum sample size is reached AND result is significant.
            if ( $min_sample_reached && $stats['is_significant'] && $stats['improvement'] > 0 ) {
                if ( ! $results['has_winner'] || $stats['improvement'] > $results['winner']['improvement'] ) {
                    $results['has_winner'] = true;
                    $results['winner'] = array(
                        'variation_id' => $challenger->id,
                        'variation_name' => $challenger->name,
                        'improvement' => $stats['improvement'],
                        'confidence' => $stats['confidence'],
                    );
                }
            }
        }

        return $results;
    }
    
    /**
     * Get stats for single variation (impressions, conversions, conversion rate, revenue).
     */
    public static function get_variation_stats( $variation ) {
        $impressions = max( 1, (int) $variation->impressions );
        $conversions = (int) $variation->conversions;
        $revenue     = (float) ( $variation->revenue ?? 0 );
        $rate        = $conversions / $impressions;
        $rpv         = $revenue / $impressions;
        return array(
            'impressions'               => (int) $variation->impressions,
            'conversions'                => $conversions,
            'revenue'                    => $revenue,
            'conversion_rate'             => $rate,
            'conversion_rate_formatted'   => number_format( $rate * 100, 2 ) . '%',
            'revenue_per_visitor'         => $rpv,
            'revenue_per_visitor_formatted' => function_exists( 'wc_price' ) ? wc_price( $rpv ) : number_format( $rpv, 2 ),
            'revenue_formatted'           => function_exists( 'wc_price' ) ? wc_price( $revenue ) : number_format( $revenue, 2 ),
        );
    }
    
    /**
     * Compare two variations using Z-test
     */
    public static function compare_variations($control, $challenger, $confidence_level = 95) {
        $n1 = max(1, $control->impressions);
        $n2 = max(1, $challenger->impressions);
        $p1 = $control->conversions / $n1;
        $p2 = $challenger->conversions / $n2;
        
        // Pooled proportion
        $p_pooled = ($control->conversions + $challenger->conversions) / ($n1 + $n2);
        
        // Standard error
        $se = sqrt($p_pooled * (1 - $p_pooled) * (1/$n1 + 1/$n2));
        
        if ($se == 0) {
            return array(
                'conversion_rate' => $p2,
                'conversion_rate_formatted' => number_format($p2 * 100, 2) . '%',
                'improvement' => 0,
                'improvement_formatted' => '0%',
                'confidence' => 0,
                'is_significant' => false,
                'impressions' => $challenger->impressions,
                'conversions' => $challenger->conversions,
            );
        }
        
        // Z-score
        $z = ($p2 - $p1) / $se;
        
        // P-value (two-tailed)
        $p_value = 2 * (1 - self::normal_cdf(abs($z)));
        
        // Confidence
        $confidence = (1 - $p_value) * 100;
        
        // Is significant?
        $z_threshold = self::$z_scores[$confidence_level] ?? 1.96;
        $is_significant = abs($z) >= $z_threshold;
        
        // Improvement
        $improvement = $p1 > 0 ? (($p2 - $p1) / $p1) * 100 : 0;
        
        return array(
            'conversion_rate' => $p2,
            'conversion_rate_formatted' => number_format($p2 * 100, 2) . '%',
            'improvement' => $improvement,
            'improvement_formatted' => ($improvement >= 0 ? '+' : '') . number_format($improvement, 1) . '%',
            'z_score' => round($z, 3),
            'p_value' => round($p_value, 4),
            'confidence' => round($confidence, 1),
            'is_significant' => $is_significant,
            'impressions' => $challenger->impressions,
            'conversions' => $challenger->conversions,
        );
    }
    
    /**
     * Normal CDF approximation
     */
    private static function normal_cdf($z) {
        $a1 =  0.254829592;
        $a2 = -0.284496736;
        $a3 =  1.421413741;
        $a4 = -1.453152027;
        $a5 =  1.061405429;
        $p  =  0.3275911;
        
        $sign = $z < 0 ? -1 : 1;
        $z = abs($z) / sqrt(2);
        
        $t = 1.0 / (1.0 + $p * $z);
        $y = 1.0 - ((((($a5 * $t + $a4) * $t) + $a3) * $t + $a2) * $t + $a1) * $t * exp(-$z * $z);
        
        return 0.5 * (1.0 + $sign * $y);
    }
    
    /**
     * Check if test reached minimum sample size
     */
    public static function has_reached_sample_size($test) {
        foreach ($test->variations as $variation) {
            if ($variation->impressions < $test->min_sample_size) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Get status message
     */
    public static function get_status_message($results, $test) {
        if ( ! self::has_reached_sample_size( $test ) ) {
            return sprintf(
                /* translators: %s: minimum sample size per variation */
                __( 'Not enough data. Each variation needs at least %s impressions before results are reliable.', 'meyvora-convert' ),
                number_format_i18n( (int) $test->min_sample_size )
            );
        }

        if ( ! empty( $results['has_winner'] ) && ! empty( $results['winner'] ) ) {
            return sprintf(
                /* translators: 1: variation name, 2: improvement percent, 3: confidence percent */
                __( 'Winner found! "%1$s" is performing %2$s better with %3$s%% confidence.', 'meyvora-convert' ),
                $results['winner']['variation_name'],
                number_format( $results['winner']['improvement'], 1 ) . '%',
                number_format( $results['winner']['confidence'], 0 )
            );
        }

        return __( 'No significant difference found yet. Keep the test running or increase traffic.', 'meyvora-convert' );
    }

    /**
     * Get the variation ID of the statistically significant winner for a test, or null if no winner yet.
     * Uses the test's confidence_level and min_sample_size; returns the challenger variation ID with highest improvement.
     *
     * @param int $test_id Test ID.
     * @return int|null Winner variation ID or null.
     */
    public static function get_winner( $test_id ) {
        if ( ! class_exists( 'MEYVC_AB_Test' ) ) {
            return null;
        }
        $ab   = new MEYVC_AB_Test();
        $test = $ab->get( $test_id );
        if ( ! $test || empty( $test->variations ) ) {
            return null;
        }
        $results = self::calculate( $test );
        if ( ! empty( $results['has_winner'] ) && ! empty( $results['winner']['variation_id'] ) ) {
            return (int) $results['winner']['variation_id'];
        }
        return null;
    }

    /**
     * Get short label for "enough data" state (for list view).
     *
     * @param object $test   Test object with variations and min_sample_size.
     * @param array  $results Result from calculate(), or null.
     * @return string
     */
    public static function get_data_state_label( $test, $results = null ) {
        if ( ! self::has_reached_sample_size( $test ) ) {
            return __( 'Not enough data', 'meyvora-convert' );
        }
        if ( $results && ! empty( $results['has_winner'] ) && ! empty( $results['winner'] ) ) {
            return $results['winner']['variation_name'];
        }
        if ( $results ) {
            return __( 'No winner yet', 'meyvora-convert' );
        }
        return '—';
    }
}
