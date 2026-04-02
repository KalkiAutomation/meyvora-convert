<?php
/**
 * AI-suggested next dynamic offer from store context (no PII in prompts).
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MEYVC_AI_Offer_Suggester
 */
class MEYVC_AI_Offer_Suggester {

	const OPTION_KEY       = 'meyvc_dynamic_offers';
	const MAX_OFFERS       = 5;
	const SYNTHETIC_BASE   = 9000;
	const RATE_ACTION      = 'offer_suggest';
	const RATE_LIMIT       = 10;

	/** @var string Object cache group for read-through DB queries. */
	private const DB_READ_CACHE_GROUP = 'meyvora_meyvc';

	/** @var int Read-through TTL (seconds). */
	private const DB_READ_CACHE_TTL = 300;

	/**
	 * @param string                    $descriptor 2–4 word slug.
	 * @param array<int|string|float> $params     Params.
	 * @return string
	 */
	private function read_cache_key( string $descriptor, array $params ): string {
		return 'meyvora_meyvc_' . md5( $descriptor . '_' . implode( '_', array_map( 'strval', $params ) ) );
	}

	/**
	 * Build context, call Claude, return validated suggestion array.
	 *
	 * @return array|WP_Error Keys: name, rationale, condition_type, condition_value, discount_type, discount_value, expected_impact.
	 */
	public function suggest() {
		if ( ! class_exists( 'MEYVC_AI_Client' ) || ! MEYVC_AI_Client::is_configured() ) {
			return new WP_Error(
				'not_configured',
				__( 'Add your Anthropic API key in Settings → AI', 'meyvora-convert' )
			);
		}
		if ( function_exists( 'meyvc_settings' ) && 'yes' !== meyvc_settings()->get( 'ai', 'feature_offers', 'yes' ) ) {
			return new WP_Error(
				'feature_disabled',
				__( 'AI offer features are disabled in Settings → AI.', 'meyvora-convert' )
			);
		}
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new WP_Error(
				'no_wc',
				__( 'WooCommerce is required.', 'meyvora-convert' )
			);
		}
		if ( ! class_exists( 'MEYVC_AI_Rate_Limiter' ) || ! MEYVC_AI_Rate_Limiter::check( self::RATE_ACTION, self::RATE_LIMIT ) ) {
			return new WP_Error(
				'rate_limited',
				__( 'AI suggest limit reached for this hour. Try again later.', 'meyvora-convert' )
			);
		}

		$context = $this->build_context();
		$json    = wp_json_encode( $context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) {
			return new WP_Error( 'encode_error', __( 'Could not prepare store context.', 'meyvora-convert' ) );
		}

		$system = 'You are a WooCommerce promotions strategist. Suggest the single best new discount offer. '
			. 'Respond with valid JSON only, no markdown: '
			. '{ "name": "offer name max 6 words", "rationale": "2 sentences explaining why this gap exists", '
			. '"condition_type": "cart_total|first_order|returning_customer|lifetime_spend", '
			. '"condition_value": "numeric threshold as string", '
			. '"discount_type": "percent|fixed", "discount_value": 10, '
			. '"expected_impact": "one sentence" }.';

		$user = 'Store context: ' . $json . ' Suggest the highest-impact new offer NOT already covered by existing offers.';

		$response = MEYVC_AI_Client::request(
			$system,
			array(
				array(
					'role'    => 'user',
					'content' => $user,
				),
			),
			2048
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$parsed = MEYVC_AI_Client::parse_json_response( $response );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$out = $this->validate_suggestion( $parsed );
		if ( is_wp_error( $out ) ) {
			return $out;
		}

		MEYVC_AI_Rate_Limiter::increment( self::RATE_ACTION );

		return $out;
	}

	/**
	 * Register wp_ajax_meyvc_ai_suggest_offer.
	 */
	public function register_ajax(): void {
		add_action( 'wp_ajax_meyvc_ai_suggest_offer', array( $this, 'ajax_suggest_offer' ) );
	}

	/**
	 * AJAX handler.
	 */
	public function ajax_suggest_offer(): void {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'meyvora-convert' ) ), 403 );
		}
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'meyvc_ai_suggest_offer' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'meyvora-convert' ) ), 403 );
		}

		$result = $this->suggest();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'suggestion' => $result ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_context(): array {
		return array(
			'existing_active_offers'   => $this->get_existing_active_offers(),
			'avg_order_value'          => $this->get_avg_order_value_60d(),
			'first_time_order_rate'    => $this->get_first_time_order_rate_60d(),
			'avg_abandonment_value'    => $this->get_avg_abandonment_active(),
			'offer_conversion_rates'   => $this->get_offer_conversion_rates_30d(),
			'top_3_product_categories' => $this->get_top_product_categories_60d(),
		);
	}

	/**
	 * Active dynamic offers (enabled + named) with applies in last 30d.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_existing_active_offers(): array {
		$offers = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $offers ) ) {
			return array();
		}
		$offers = array_pad( $offers, self::MAX_OFFERS, array() );
		$out    = array();
		for ( $i = 0; $i < self::MAX_OFFERS; $i++ ) {
			$o = isset( $offers[ $i ] ) && is_array( $offers[ $i ] ) ? $offers[ $i ] : array();
			if ( empty( $o['enabled'] ) ) {
				continue;
			}
			$name = isset( $o['headline'] ) ? trim( (string) $o['headline'] ) : '';
			if ( $name === '' ) {
				continue;
			}
			$reward_type = isset( $o['reward_type'] ) ? sanitize_key( (string) $o['reward_type'] ) : 'percent';
			$summary     = class_exists( 'MEYVC_Offer_Presenter' ) ? MEYVC_Offer_Presenter::summarize_conditions( $o ) : '';
			$oid         = self::SYNTHETIC_BASE + $i;
			$out[]       = array(
				'slot_index'         => $i,
				'offer_id'           => $oid,
				'name'               => sanitize_text_field( $name ),
				'condition_summary'  => is_string( $summary ) ? sanitize_text_field( $summary ) : '',
				'reward_type'        => $reward_type,
				'applies_last_30d'   => $this->count_offer_applies_since( $oid, 30 ),
			);
		}
		return $out;
	}

	/**
	 * @param int $offer_id Synthetic offer id (9000 + index).
	 * @param int $days     Lookback days.
	 * @return int
	 */
	private function count_offer_applies_since( int $offer_id, int $days ): int {
		if ( ! class_exists( 'MEYVC_Offer_Model' ) ) {
			return 0;
		}
		$table = MEYVC_Offer_Model::get_logs_table();
		if ( ! MEYVC_Database::table_exists( $table ) ) {
			return 0;
		}
		global $wpdb;
		$cut = gmdate( 'Y-m-d', strtotime( '-' . absint( $days ) . ' days' ) );
		$ck  = $this->read_cache_key( 'offer_log_applies_count', array( $table, $offer_id, $cut ) );
		$found = false;
		$n   = wp_cache_get( $ck, self::DB_READ_CACHE_GROUP, false, $found );
		if ( ! $found ) {
			$n = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE offer_id = %d AND created_at >= %s',
					$table,
					$offer_id,
					$cut
				)
			);
			wp_cache_set( $ck, $n, self::DB_READ_CACHE_GROUP, self::DB_READ_CACHE_TTL );
		}
		return (int) $n;
	}

	/**
	 * Orders with order_id set in logs (MYV coupon redeemed) since date.
	 *
	 * @param int $offer_id Synthetic id.
	 * @param int $days     Lookback.
	 * @return int
	 */
	private function count_offer_orders_since( int $offer_id, int $days ): int {
		if ( ! class_exists( 'MEYVC_Offer_Model' ) ) {
			return 0;
		}
		$table = MEYVC_Offer_Model::get_logs_table();
		if ( ! MEYVC_Database::table_exists( $table ) ) {
			return 0;
		}
		global $wpdb;
		$cut = gmdate( 'Y-m-d', strtotime( '-' . absint( $days ) . ' days' ) );
		$ck  = $this->read_cache_key( 'offer_log_orders_count', array( $table, $offer_id, $cut ) );
		$found = false;
		$n   = wp_cache_get( $ck, self::DB_READ_CACHE_GROUP, false, $found );
		if ( ! $found ) {
			$n = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE offer_id = %d AND created_at >= %s AND order_id IS NOT NULL AND order_id > 0',
					$table,
					$offer_id,
					$cut
				)
			);
			wp_cache_set( $ck, $n, self::DB_READ_CACHE_GROUP, self::DB_READ_CACHE_TTL );
		}
		return (int) $n;
	}

	/**
	 * Applies vs orders per offer slot (named offers), last 30d.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_offer_conversion_rates_30d(): array {
		$offers = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $offers ) ) {
			return array();
		}
		$offers = array_pad( $offers, self::MAX_OFFERS, array() );
		$rates  = array();
		for ( $i = 0; $i < self::MAX_OFFERS; $i++ ) {
			$o = isset( $offers[ $i ] ) && is_array( $offers[ $i ] ) ? $offers[ $i ] : array();
			$name = isset( $o['headline'] ) ? trim( (string) $o['headline'] ) : '';
			if ( $name === '' ) {
				continue;
			}
			$oid     = self::SYNTHETIC_BASE + $i;
			$applies = $this->count_offer_applies_since( $oid, 30 );
			$orders  = $this->count_offer_orders_since( $oid, 30 );
			$rate    = $applies > 0 ? round( ( $orders / $applies ) * 100, 2 ) : 0.0;
			$rates[] = array(
				'offer_id'           => $oid,
				'name'               => sanitize_text_field( $name ),
				'applies_last_30d'   => $applies,
				'orders_last_30d'    => $orders,
				'apply_to_order_pct' => $rate,
				'note'               => 'Coupons use MYV-{offer_id}-*; orders counted from offer logs with order_id.',
			);
		}
		return $rates;
	}

	/**
	 * @return float
	 */
	private function get_avg_order_value_60d(): float {
		$orders = $this->get_wc_orders_sample( 60, 200 );
		if ( empty( $orders ) ) {
			return 0.0;
		}
		$sum = 0.0;
		$n   = 0;
		foreach ( $orders as $order ) {
			if ( ! is_object( $order ) || ! method_exists( $order, 'get_total' ) ) {
				continue;
			}
			$sum += (float) $order->get_total();
			++$n;
		}
		return $n > 0 ? round( $sum / $n, 2 ) : 0.0;
	}

	/**
	 * Share of sample orders where billing email appears only once in sample (proxy for first-time in window).
	 *
	 * @return float 0–1
	 */
	private function get_first_time_order_rate_60d(): float {
		$orders = $this->get_wc_orders_sample( 60, 250 );
		if ( empty( $orders ) ) {
			return 0.0;
		}
		$emails = array();
		foreach ( $orders as $order ) {
			if ( ! is_object( $order ) || ! method_exists( $order, 'get_billing_email' ) ) {
				continue;
			}
			$e = strtolower( trim( (string) $order->get_billing_email() ) );
			if ( $e === '' ) {
				continue;
			}
			if ( ! isset( $emails[ $e ] ) ) {
				$emails[ $e ] = 0;
			}
			++$emails[ $e ];
		}
		$with_email = 0;
		$first_only = 0;
		foreach ( $orders as $order ) {
			if ( ! is_object( $order ) || ! method_exists( $order, 'get_billing_email' ) ) {
				continue;
			}
			$e = strtolower( trim( (string) $order->get_billing_email() ) );
			if ( $e === '' ) {
				continue;
			}
			++$with_email;
			if ( 1 === (int) $emails[ $e ] ) {
				++$first_only;
			}
		}
		return $with_email > 0 ? round( $first_only / $with_email, 4 ) : 0.0;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function get_top_product_categories_60d(): array {
		$orders = $this->get_wc_orders_sample( 60, 200 );
		if ( empty( $orders ) ) {
			return array();
		}
		$counts = array();
		foreach ( $orders as $order ) {
			if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) {
				continue;
			}
			foreach ( $order->get_items() as $item ) {
				if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) ) {
					continue;
				}
				$pid = (int) $item->get_product_id();
				if ( $pid < 1 ) {
					continue;
				}
				$qty = method_exists( $item, 'get_quantity' ) ? (int) $item->get_quantity() : 1;
				$terms = get_the_terms( $pid, 'product_cat' );
				if ( ! is_array( $terms ) ) {
					continue;
				}
				foreach ( $terms as $term ) {
					if ( ! is_object( $term ) || ! isset( $term->term_id ) ) {
						continue;
					}
					$tid = (int) $term->term_id;
					if ( ! isset( $counts[ $tid ] ) ) {
						$counts[ $tid ] = array(
							'id'    => $tid,
							'name'  => sanitize_text_field( (string) $term->name ),
							'items' => 0,
						);
					}
					$counts[ $tid ]['items'] += $qty;
				}
			}
		}
		usort(
			$counts,
			function ( $a, $b ) {
				return (int) $b['items'] - (int) $a['items'];
			}
		);
		return array_slice( array_values( $counts ), 0, 3 );
	}

	/**
	 * @param int $days  Lookback.
	 * @param int $limit Max orders.
	 * @return array<\WC_Order>
	 */
	private function get_wc_orders_sample( int $days, int $limit ): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		$limit = max( 1, min( 500, $limit ) );
		$statuses = function_exists( 'wc_get_is_paid_statuses' ) ? wc_get_is_paid_statuses() : array( 'processing', 'completed' );
		$since = strtotime( '-' . absint( $days ) . ' days', time() );
		$orders = wc_get_orders(
			array(
				'limit'        => $limit,
				'status'       => $statuses,
				'date_created' => '>=' . $since,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'return'       => 'objects',
			)
		);
		return is_array( $orders ) ? $orders : array();
	}

	/**
	 * Average cart total for active abandoned carts (aggregate).
	 *
	 * @return float
	 */
	private function get_avg_abandonment_active(): float {
		$table = MEYVC_Database::get_table( 'abandoned_carts' );
		if ( ! MEYVC_Database::table_exists( $table ) ) {
			return 0.0;
		}
		global $wpdb;
		$sum = 0.0;
		$n   = 0;
		$ck  = $this->read_cache_key( 'abandoned_cart_json_rows', array( $table ) );
		$rows = wp_cache_get( $ck, self::DB_READ_CACHE_GROUP );
		if ( false === $rows ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT cart_json FROM %i WHERE status = %s AND cart_json IS NOT NULL AND cart_json != \'\' LIMIT 5000',
					$table,
					'active'
				),
				ARRAY_A
			);
			if ( ! is_array( $rows ) ) {
				$rows = array();
			}
			wp_cache_set( $ck, $rows, self::DB_READ_CACHE_GROUP, self::DB_READ_CACHE_TTL );
		}
		if ( ! is_array( $rows ) ) {
			return 0.0;
		}
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
		return $n > 0 ? round( $sum / $n, 2 ) : 0.0;
	}

	/**
	 * @param array $parsed Decoded JSON object.
	 * @return array|WP_Error
	 */
	private function validate_suggestion( array $parsed ) {
		$allowed_cond = array( 'cart_total', 'first_order', 'returning_customer', 'lifetime_spend' );
		$allowed_disc = array( 'percent', 'fixed' );

		$name = isset( $parsed['name'] ) ? sanitize_text_field( (string) $parsed['name'] ) : '';
		if ( $name === '' ) {
			return new WP_Error( 'invalid', __( 'AI did not return a valid offer name.', 'meyvora-convert' ) );
		}
		$rationale = isset( $parsed['rationale'] ) ? sanitize_text_field( (string) $parsed['rationale'] ) : '';
		if ( strlen( $rationale ) < 10 ) {
			return new WP_Error( 'invalid', __( 'AI did not return a valid rationale.', 'meyvora-convert' ) );
		}
		$cond_type = isset( $parsed['condition_type'] ) ? sanitize_key( (string) $parsed['condition_type'] ) : '';
		if ( ! in_array( $cond_type, $allowed_cond, true ) ) {
			$cond_type = 'cart_total';
		}
		$cond_val = isset( $parsed['condition_value'] ) ? sanitize_text_field( (string) $parsed['condition_value'] ) : '0';
		$cond_val = preg_replace( '/[^0-9.]/', '', $cond_val );
		if ( $cond_val === '' ) {
			$cond_val = '0';
		}

		$disc_type = isset( $parsed['discount_type'] ) ? sanitize_key( (string) $parsed['discount_type'] ) : 'percent';
		if ( ! in_array( $disc_type, $allowed_disc, true ) ) {
			$disc_type = 'percent';
		}
		$disc_val = isset( $parsed['discount_value'] ) ? floatval( $parsed['discount_value'] ) : 0;
		if ( $disc_val < 0 ) {
			$disc_val = 0;
		}
		if ( 'percent' === $disc_type && $disc_val > 100 ) {
			$disc_val = 100;
		}

		$impact = isset( $parsed['expected_impact'] ) ? sanitize_text_field( (string) $parsed['expected_impact'] ) : '';

		return array(
			'name'             => $name,
			'rationale'        => $rationale,
			'condition_type'   => $cond_type,
			'condition_value'  => $cond_val,
			'discount_type'    => $disc_type,
			'discount_value'   => round( $disc_val, 2 ),
			'expected_impact'  => $impact,
		);
	}
}

