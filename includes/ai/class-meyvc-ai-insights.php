<?php
/**
 * AI-generated narrative insights for the Insights admin tab (aggregate data only).
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MEYVC_AI_Insights
 */
class MEYVC_AI_Insights {

	const CACHE_TTL        = 6 * HOUR_IN_SECONDS;
	const COOLDOWN_ACTION  = 'insights_analyse';
	const COOLDOWN_SECONDS = 60;

	/**
	 * Transient key for cached analysis.
	 *
	 * @param int $days Period length in days.
	 * @return string
	 */
	public static function transient_key( int $days ): string {
		return 'meyvc_ai_insights_' . $days . 'd';
	}

	/**
	 * Remove cached insights for a period length.
	 *
	 * @param int $days Period days (7–90).
	 */
	public function bust_cache( int $days = 30 ): void {
		$days = self::normalize_days( $days );
		delete_transient( self::transient_key( $days ) );
	}

	/**
	 * Build snapshot, optionally call API, cache and return insight cards.
	 *
	 * @param int  $days          Period in days (clamped 7–90).
	 * @param bool $force_refresh Skip reading cache; still writes cache after API success.
	 * @return array|WP_Error     On success: array with keys insights (list of cards), elapsed_seconds (float|null), from_cache (bool).
	 */
	public function analyse( int $days = 30, bool $force_refresh = false ) {
		$days = self::normalize_days( $days );

		if ( ! $force_refresh ) {
			$cached = get_transient( self::transient_key( $days ) );
			if ( is_array( $cached ) && isset( $cached['insights'] ) && is_array( $cached['insights'] ) ) {
				return array(
					'insights'        => $cached['insights'],
					'elapsed_seconds' => isset( $cached['elapsed_seconds'] ) ? (float) $cached['elapsed_seconds'] : null,
					'from_cache'      => true,
				);
			}
		}

		if ( ! class_exists( 'MEYVC_AI_Client' ) || ! MEYVC_AI_Client::is_configured() ) {
			return new WP_Error(
				'not_configured',
				__( 'Add your Anthropic API key in Settings → AI', 'meyvora-convert' )
			);
		}

		if ( function_exists( 'meyvc_settings' ) && 'yes' !== meyvc_settings()->get( 'ai', 'feature_insights', 'yes' ) ) {
			return new WP_Error(
				'feature_disabled',
				__( 'AI insights are disabled in Settings → AI.', 'meyvora-convert' )
			);
		}

		if ( ! class_exists( 'MEYVC_AI_Rate_Limiter' ) || ! MEYVC_AI_Rate_Limiter::cooldown_ok( self::COOLDOWN_ACTION, self::COOLDOWN_SECONDS ) ) {
			$wait = class_exists( 'MEYVC_AI_Rate_Limiter' ) ? MEYVC_AI_Rate_Limiter::cooldown_remaining( self::COOLDOWN_ACTION, self::COOLDOWN_SECONDS ) : self::COOLDOWN_SECONDS;
			return new WP_Error(
				'rate_limited',
				__( 'Please wait before running another AI analysis.', 'meyvora-convert' ),
				array( 'retry_after' => $wait )
			);
		}

		$snapshot = $this->build_snapshot( $days );

		$json_snapshot = wp_json_encode( $snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json_snapshot ) ) {
			return new WP_Error( 'encode_error', __( 'Could not prepare data for AI.', 'meyvora-convert' ) );
		}

		$system = 'You are a conversion rate optimisation analyst for a WooCommerce store. '
			. 'Analyse the provided store data and return a JSON array of 4-6 insight objects. '
			. 'Each insight: { '
			. '"priority": "high|medium|low", '
			. '"category": "campaign|offer|abandoned_cart|ab_test|general", '
			. '"title": "short title max 8 words", '
			. '"finding": "1-2 sentence finding based only on the data", '
			. '"action": "1 specific actionable recommendation", '
			. '"metric": "key metric that supports this insight" '
			. '}. '
			. 'Return ONLY the JSON array. No markdown. No preamble. No explanation.';

		$user = 'Store data for the last ' . (int) $days . ' days: ' . $json_snapshot
			. ' Identify the most important patterns, problems, and opportunities.';

		$t0 = microtime( true );
		$response = MEYVC_AI_Client::request(
			$system,
			array(
				array(
					'role'    => 'user',
					'content' => $user,
				),
			),
			4096
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$parsed = MEYVC_AI_Client::parse_json_response( $response );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$insights = $this->validate_insights_list( $parsed );
		if ( is_wp_error( $insights ) ) {
			return $insights;
		}

		MEYVC_AI_Rate_Limiter::cooldown_set( self::COOLDOWN_ACTION );

		$elapsed = round( microtime( true ) - $t0, 2 );

		$payload = array(
			'insights'        => $insights,
			'elapsed_seconds' => $elapsed,
			'from_cache'      => false,
		);

		set_transient(
			self::transient_key( $days ),
			array(
				'insights'        => $insights,
				'elapsed_seconds' => $elapsed,
			),
			self::CACHE_TTL
		);

		return $payload;
	}

	/**
	 * Register admin AJAX handlers.
	 */
	public function register_ajax(): void {
		add_action( 'wp_ajax_meyvc_ai_analyse', array( $this, 'ajax_analyse' ) );
		add_action( 'wp_ajax_meyvc_ai_bust_insights_cache', array( $this, 'ajax_bust_insights_cache' ) );
	}

	/**
	 * AJAX: run or peek insights analysis.
	 */
	public function ajax_analyse(): void {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'meyvora-convert' ) ), 403 );
		}
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'meyvc_ai_analyse' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'meyvora-convert' ) ), 403 );
		}

		$days    = isset( $_POST['days'] ) ? absint( $_POST['days'] ) : 30;
		$peek    = ! empty( $_POST['peek'] );
		$refresh = ! empty( $_POST['refresh'] );

		if ( $peek ) {
			$days   = self::normalize_days( $days );
			$cached = get_transient( self::transient_key( $days ) );
			if ( is_array( $cached ) && isset( $cached['insights'] ) && is_array( $cached['insights'] ) ) {
				wp_send_json_success(
					array(
						'insights'        => $cached['insights'],
						'elapsed_seconds' => isset( $cached['elapsed_seconds'] ) ? (float) $cached['elapsed_seconds'] : null,
						'cached'          => true,
					)
				);
			}
			wp_send_json_success(
				array(
					'insights'        => array(),
					'elapsed_seconds' => null,
					'cached'          => false,
				)
			);
		}

		$result = $this->analyse( $days, $refresh );
		if ( is_wp_error( $result ) ) {
			$data = array( 'message' => $result->get_error_message() );
			if ( 'rate_limited' === $result->get_error_code() ) {
				$edata = $result->get_error_data();
				if ( is_array( $edata ) && isset( $edata['retry_after'] ) ) {
					$data['retry_after'] = (int) $edata['retry_after'];
				}
			}
			wp_send_json_error( $data );
		}

		wp_send_json_success(
			array(
				'insights'        => $result['insights'],
				'elapsed_seconds' => $result['elapsed_seconds'],
				'cached'          => ! empty( $result['from_cache'] ),
			)
		);
	}

	/**
	 * AJAX: clear cached insights for selected period.
	 */
	public function ajax_bust_insights_cache(): void {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'meyvora-convert' ) ), 403 );
		}
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'meyvc_ai_bust_insights_cache' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'meyvora-convert' ) ), 403 );
		}
		$days = isset( $_POST['days'] ) ? absint( $_POST['days'] ) : 30;
		$this->bust_cache( $days );
		if ( class_exists( 'MEYVC_Insights' ) ) {
			MEYVC_Insights::bust_insights_cache();
		}
		wp_send_json_success( array( 'ok' => true ) );
	}

	/**
	 * @param int $days Raw days value.
	 * @return int Clamped 7–90.
	 */
	private static function normalize_days( int $days ): int {
		if ( $days < 7 || $days > 90 ) {
			return 30;
		}
		return $days;
	}

	/**
	 * @param array $parsed Decoded JSON (must be a list of insight objects).
	 * @return array|WP_Error
	 */
	private function validate_insights_list( array $parsed ) {
		if ( isset( $parsed['insights'] ) && is_array( $parsed['insights'] ) ) {
			$parsed = $parsed['insights'];
		}
		if ( array_keys( $parsed ) !== range( 0, count( $parsed ) - 1 ) ) {
			return new WP_Error(
				'invalid_shape',
				__( 'AI response was not a JSON array.', 'meyvora-convert' )
			);
		}
		$allowed_priority = array( 'high', 'medium', 'low' );
		$allowed_category = array( 'campaign', 'offer', 'abandoned_cart', 'ab_test', 'general' );
		$out              = array();
		foreach ( $parsed as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$priority = isset( $row['priority'] ) ? sanitize_key( (string) $row['priority'] ) : '';
			$category = isset( $row['category'] ) ? sanitize_key( (string) $row['category'] ) : '';
			if ( ! in_array( $priority, $allowed_priority, true ) ) {
				$priority = 'medium';
			}
			if ( ! in_array( $category, $allowed_category, true ) ) {
				$category = 'general';
			}
			$title   = isset( $row['title'] ) ? sanitize_text_field( (string) $row['title'] ) : '';
			$finding = isset( $row['finding'] ) ? sanitize_text_field( (string) $row['finding'] ) : '';
			$action  = isset( $row['action'] ) ? sanitize_text_field( (string) $row['action'] ) : '';
			$metric  = isset( $row['metric'] ) ? sanitize_text_field( (string) $row['metric'] ) : '';
			if ( '' === $title || '' === $finding || '' === $action ) {
				continue;
			}
			$out[] = array(
				'priority' => $priority,
				'category' => $category,
				'title'    => $title,
				'finding'  => $finding,
				'action'   => $action,
				'metric'   => $metric,
			);
			if ( count( $out ) >= 6 ) {
				break;
			}
		}
		if ( count( $out ) < 1 ) {
			return new WP_Error(
				'invalid_insights',
				__( 'AI did not return usable insights.', 'meyvora-convert' )
			);
		}
		return $out;
	}

	/**
	 * Aggregate snapshot (no PII).
	 *
	 * @param int $days Period days.
	 * @return array
	 */
	private function build_snapshot( int $days ) {
		$date_to   = wp_date( 'Y-m-d' );
		$date_from = wp_date( 'Y-m-d', strtotime( '-' . (int) $days . ' days' ) );

		$analytics = new MEYVC_Analytics();
		$summary_raw = $analytics->get_summary( $date_from, $date_to );
		$summary     = self::scrub_summary( $summary_raw );

		$campaign_rows = $analytics->get_campaign_performance( $date_from, $date_to, 10 );
		$top_campaigns = array();
		if ( is_array( $campaign_rows ) ) {
			foreach ( $campaign_rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$top_campaigns[] = array(
					'id'           => isset( $row['id'] ) ? (int) $row['id'] : 0,
					'name'         => isset( $row['name'] ) ? sanitize_text_field( (string) $row['name'] ) : '',
					'status'       => isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '',
					'impressions'  => isset( $row['impressions'] ) ? (int) $row['impressions'] : 0,
					'conversions'  => isset( $row['conversions'] ) ? (int) $row['conversions'] : 0,
					'revenue'      => isset( $row['revenue'] ) ? (float) $row['revenue'] : 0.0,
					'emails_count' => isset( $row['emails'] ) ? (int) $row['emails'] : 0,
				);
			}
		}

		$device_rows = $analytics->get_device_stats( $date_from, $date_to );
		$device_breakdown = array();
		if ( is_array( $device_rows ) ) {
			foreach ( $device_rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$device_breakdown[] = array(
					'device'      => isset( $row['device'] ) ? sanitize_key( (string) $row['device'] ) : 'unknown',
					'impressions' => isset( $row['impressions'] ) ? (int) $row['impressions'] : 0,
					'conversions' => isset( $row['conversions'] ) ? (int) $row['conversions'] : 0,
				);
			}
		}

		$top_offers = $this->query_top_offers( $date_from, $date_to, 5 );
		$ab_info    = $this->query_ab_tests_summary();
		$abandoned  = $this->query_abandoned_stats( $date_from, $date_to );
		$daily_trend = $this->query_daily_trend_last_14( $date_to );

		return array(
			'period_days'        => $days,
			'summary'            => $summary,
			'top_10_campaigns'   => $top_campaigns,
			'top_5_offers'       => $top_offers,
			'abandoned_carts'    => $abandoned,
			'ab_tests'           => $ab_info,
			'device_breakdown'   => $device_breakdown,
			'daily_trend'        => $daily_trend,
		);
	}

	/**
	 * Remove formatted / redundant fields from analytics summary (numbers only).
	 *
	 * @param array $summary Raw summary from MEYVC_Analytics::get_summary().
	 * @return array
	 */
	private static function scrub_summary( array $summary ): array {
		$keys = array(
			'impressions',
			'impressions_change',
			'clicks',
			'ctr',
			'conversions',
			'conversions_change',
			'conversion_rate',
			'revenue',
			'revenue_change',
			'emails',
			'rpv',
			'sticky_cart_adds',
			'shipping_bar_interactions',
			'prev_conversions',
			'prev_impressions',
			'prev_revenue',
			'prev_emails',
		);
		$out = array();
		foreach ( $keys as $k ) {
			if ( array_key_exists( $k, $summary ) ) {
				$out[ $k ] = $summary[ $k ];
			}
		}
		return $out;
	}

	/**
	 * Top offers by log volume in period (coupon generations); orders = logs with order_id.
	 *
	 * @param string $date_from Y-m-d.
	 * @param string $date_to   Y-m-d.
	 * @param int    $limit     Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function query_top_offers( string $date_from, string $date_to, int $limit ): array {
		global $wpdb;
		$offers = MEYVC_Database::get_table( 'offers' );
		$logs   = MEYVC_Database::get_table( 'offer_logs' );
		if ( ! MEYVC_Database::table_exists( $offers ) || ! MEYVC_Database::table_exists( $logs ) ) {
			return array();
		}
		$limit = max( 1, min( 20, $limit ) );
		$cache_key_top_offers = 'meyvora_meyvc_' . md5( serialize( array( 'ai_insights_top_offers_by_period', $offers, $logs, $date_from, $date_to, $limit ) ) );
		$rows                 = wp_cache_get( $cache_key_top_offers, 'meyvora_meyvc' );
		if ( false === $rows ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT o.id, o.name,
			COUNT(l.id) AS applies,
			SUM(CASE WHEN l.order_id IS NOT NULL AND l.order_id > 0 THEN 1 ELSE 0 END) AS orders
			FROM %i o
			INNER JOIN %i l ON l.offer_id = o.id
			WHERE l.created_at >= %s AND l.created_at < DATE_ADD(%s, INTERVAL 1 DAY)
			GROUP BY o.id, o.name
			ORDER BY applies DESC
			LIMIT %d',
					$offers,
					$logs,
					$date_from,
					$date_to,
					$limit
				),
				ARRAY_A
			);
			wp_cache_set( $cache_key_top_offers, $rows, 'meyvora_meyvc', 300 );
		}
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}
		$out = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'id'      => isset( $row['id'] ) ? (int) $row['id'] : 0,
				'name'    => isset( $row['name'] ) ? sanitize_text_field( (string) $row['name'] ) : '',
				'applies' => isset( $row['applies'] ) ? (int) $row['applies'] : 0,
				'orders'  => isset( $row['orders'] ) ? (int) $row['orders'] : 0,
			);
		}
		return $out;
	}

	/**
	 * Running test count and recent completed tests with winner label (aggregate).
	 *
	 * @return array<string, mixed>
	 */
	private function query_ab_tests_summary(): array {
		global $wpdb;
		$tests       = MEYVC_Database::get_table( 'ab_tests' );
		$variations  = MEYVC_Database::get_table( 'ab_variations' );
		$running     = 0;
		$completed   = array();
		if ( ! MEYVC_Database::table_exists( $tests ) ) {
			return array(
				'running_count'       => 0,
				'completed_with_winner' => array(),
			);
		}
		$cache_key_running = 'meyvora_meyvc_' . md5( serialize( array( 'ai_insights_ab_tests_running_count', $tests, 'running' ) ) );
		$running_raw       = wp_cache_get( $cache_key_running, 'meyvora_meyvc' );
		if ( false === $running_raw ) {
			$running_raw = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE status = %s',
					$tests,
					'running'
				)
			);
			wp_cache_set( $cache_key_running, $running_raw, 'meyvora_meyvc', 300 );
		}
		$running = (int) $running_raw;

		if ( MEYVC_Database::table_exists( $variations ) ) {
			$cache_key_completed_var = 'meyvora_meyvc_' . md5( serialize( array( 'ai_insights_ab_completed_with_variations', $tests, $variations, 'completed' ) ) );
			$rows                    = wp_cache_get( $cache_key_completed_var, 'meyvora_meyvc' );
			if ( false === $rows ) {
				$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
					$wpdb->prepare(
						'SELECT t.id, t.name AS test_name, t.winner_variation_id, v.name AS winner_variation_name
				FROM %i t
				LEFT JOIN %i v ON v.id = t.winner_variation_id
				WHERE t.status = %s AND t.winner_variation_id IS NOT NULL AND t.winner_variation_id > 0
				ORDER BY t.completed_at DESC
				LIMIT 12',
						$tests,
						$variations,
						'completed'
					),
					ARRAY_A
				);
				wp_cache_set( $cache_key_completed_var, $rows, 'meyvora_meyvc', 300 );
			}
			if ( ! is_array( $rows ) ) {
				$rows = array();
			}
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$completed[] = array(
						'test_id'                 => isset( $row['id'] ) ? (int) $row['id'] : 0,
						'test_name'               => isset( $row['test_name'] ) ? sanitize_text_field( (string) $row['test_name'] ) : '',
						'winner_variation_id'     => isset( $row['winner_variation_id'] ) ? (int) $row['winner_variation_id'] : 0,
						'winner_variation_name'   => isset( $row['winner_variation_name'] ) ? sanitize_text_field( (string) $row['winner_variation_name'] ) : '',
					);
				}
			}
		} else {
			$cache_key_completed_only = 'meyvora_meyvc_' . md5( serialize( array( 'ai_insights_ab_completed_tests_only', $tests, 'completed' ) ) );
			$rows                     = wp_cache_get( $cache_key_completed_only, 'meyvora_meyvc' );
			if ( false === $rows ) {
				$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
					$wpdb->prepare(
						'SELECT id, name AS test_name, winner_variation_id FROM %i WHERE status = %s AND winner_variation_id IS NOT NULL AND winner_variation_id > 0 ORDER BY completed_at DESC LIMIT 12',
						$tests,
						'completed'
					),
					ARRAY_A
				);
				wp_cache_set( $cache_key_completed_only, $rows, 'meyvora_meyvc', 300 );
			}
			if ( ! is_array( $rows ) ) {
				$rows = array();
			}
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$completed[] = array(
						'test_id'               => isset( $row['id'] ) ? (int) $row['id'] : 0,
						'test_name'             => isset( $row['test_name'] ) ? sanitize_text_field( (string) $row['test_name'] ) : '',
						'winner_variation_id'   => isset( $row['winner_variation_id'] ) ? (int) $row['winner_variation_id'] : 0,
						'winner_variation_name' => '',
					);
				}
			}
		}

		return array(
			'running_count'           => $running,
			'completed_with_winner'   => $completed,
		);
	}

	/**
	 * Abandoned cart aggregates for carts created in period.
	 *
	 * @param string $date_from Y-m-d.
	 * @param string $date_to   Y-m-d.
	 * @return array<string, float|int>
	 */
	private function query_abandoned_stats( string $date_from, string $date_to ): array {
		global $wpdb;
		$table = MEYVC_Database::get_table( 'abandoned_carts' );
		if ( ! MEYVC_Database::table_exists( $table ) ) {
			return array(
				'total'          => 0,
				'recovered'      => 0,
				'recovery_rate'  => 0.0,
				'avg_value'      => 0.0,
			);
		}
		$cache_key_abandoned_totals = 'meyvora_meyvc_' . md5( serialize( array( 'ai_insights_abandoned_totals_period', $table, $date_from, $date_to, 'recovered' ) ) );
		$cached_abandoned_totals    = wp_cache_get( $cache_key_abandoned_totals, 'meyvora_meyvc' );
		if ( false === $cached_abandoned_totals ) {
			$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT
					COUNT(*) AS total,
					SUM(CASE WHEN status = %s THEN 1 ELSE 0 END) AS recovered
				FROM %i
				WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY)',
					'recovered',
					$table,
					$date_from,
					$date_to
				),
				ARRAY_A
			);
			wp_cache_set( $cache_key_abandoned_totals, $row, 'meyvora_meyvc', 300 );
		} else {
			$row = is_array( $cached_abandoned_totals ) ? $cached_abandoned_totals : null;
		}
		$total     = ( $row && isset( $row['total'] ) ) ? (int) $row['total'] : 0;
		$recovered = ( $row && isset( $row['recovered'] ) ) ? (int) $row['recovered'] : 0;
		$rate      = $total > 0 ? round( ( $recovered / $total ) * 100, 2 ) : 0.0;
		$avg       = $this->query_abandoned_avg_cart_value( $table, $date_from, $date_to );

		return array(
			'total'         => $total,
			'recovered'     => $recovered,
			'recovery_rate' => $rate,
			'avg_value'     => $avg,
		);
	}

	/**
	 * Average cart total from cart_json (MySQL JSON) with PHP fallback.
	 *
	 * @param string $table     Full table name.
	 * @param string $date_from Y-m-d.
	 * @param string $date_to   Y-m-d.
	 * @return float
	 */
	private function query_abandoned_avg_cart_value( string $table, string $date_from, string $date_to ): float {
		global $wpdb;
		$sum = 0.0;
		$n   = 0;
		$cache_key_cart_json = 'meyvora_meyvc_' . md5( serialize( array( 'ai_insights_abandoned_cart_json_rows', $table, $date_from, $date_to ) ) );
		$rows                = wp_cache_get( $cache_key_cart_json, 'meyvora_meyvc' );
		if ( false === $rows ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT cart_json FROM %i WHERE created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY) AND cart_json IS NOT NULL AND cart_json != \'\' LIMIT 5000',
					$table,
					$date_from,
					$date_to
				),
				ARRAY_A
			);
			wp_cache_set( $cache_key_cart_json, $rows, 'meyvora_meyvc', 300 );
		}
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}
		if ( is_array( $rows ) ) {
			foreach ( $rows as $r ) {
				if ( empty( $r['cart_json'] ) ) {
					continue;
				}
				$data = json_decode( $r['cart_json'], true );
				if ( is_array( $data ) && isset( $data['totals']['total'] ) && is_numeric( $data['totals']['total'] ) ) {
					$sum += (float) $data['totals']['total'];
					++$n;
				}
			}
		}
		return $n > 0 ? round( $sum / $n, 2 ) : 0.0;
	}

	/**
	 * Last 14 days aggregated from meyvc_daily_stats (all campaigns).
	 *
	 * @param string $date_to Y-m-d end date.
	 * @return array<int, array<string, mixed>>
	 */
	private function query_daily_trend_last_14( string $date_to ): array {
		global $wpdb;
		$stats = MEYVC_Database::get_table( 'daily_stats' );
		if ( ! MEYVC_Database::table_exists( $stats ) ) {
			return array();
		}
		$end_ts   = strtotime( $date_to . ' 00:00:00' );
		$start_ts = strtotime( '-13 days', $end_ts );
		$from     = gmdate( 'Y-m-d', $start_ts );
		$cache_key_daily_trend = 'meyvora_meyvc_' . md5( serialize( array( 'ai_insights_daily_stats_trend', $stats, $from, $date_to ) ) );
		$rows                  = wp_cache_get( $cache_key_daily_trend, 'meyvora_meyvc' );
		if ( false === $rows ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT date,
					SUM(impressions) AS impressions,
					SUM(conversions) AS conversions,
					SUM(revenue) AS revenue
				FROM %i
				WHERE date BETWEEN %s AND %s
				GROUP BY date
				ORDER BY date ASC',
					$stats,
					$from,
					$date_to
				),
				ARRAY_A
			);
			wp_cache_set( $cache_key_daily_trend, $rows, 'meyvora_meyvc', 300 );
		}
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}
		$out = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'date'        => isset( $row['date'] ) ? sanitize_text_field( (string) $row['date'] ) : '',
				'impressions' => isset( $row['impressions'] ) ? (int) $row['impressions'] : 0,
				'conversions' => isset( $row['conversions'] ) ? (int) $row['conversions'] : 0,
				'revenue'     => isset( $row['revenue'] ) ? (float) $row['revenue'] : 0.0,
			);
		}
		return $out;
	}
}

