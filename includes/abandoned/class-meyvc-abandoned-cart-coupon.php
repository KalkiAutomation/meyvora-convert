<?php
/**
 * Dynamic coupon generation for abandoned cart reminders.
 *
 * Generates single-use coupon when preparing to send reminder (configurable: default Email #1).
 * Stores code on abandoned cart record; reuses same coupon for later emails.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MEYVC_Abandoned_Cart_Coupon
 */
class MEYVC_Abandoned_Cart_Coupon {

	/**
	 * Get or create a coupon for this abandoned cart. Reuses existing discount_coupon if valid.
	 *
	 * @param object $row  Abandoned cart row (id, user_id, email, discount_coupon, ...).
	 * @param array  $opts Settings from get_abandoned_cart_settings().
	 * @return string|null Coupon code or null.
	 */
	public static function get_or_create_coupon( $row, array $opts ) {
		if ( empty( $opts['enable_discount_in_emails'] ) ) {
			return null;
		}
		if ( ! class_exists( 'WC_Coupon' ) ) {
			return null;
		}
		// Reuse existing code if still valid.
		if ( ! empty( $row->discount_coupon ) ) {
			$code = trim( (string) $row->discount_coupon );
			if ( $code !== '' && self::is_coupon_usable( $code ) ) {
				return $code;
			}
		}
		$code = self::create_coupon( $row, $opts );
		if ( $code ) {
			global $wpdb;
			$table = MEYVC_Abandoned_Cart_Tracker::get_table_name();
			$upd   = $wpdb->update( $table, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation; caching not applicable.
				array( 'discount_coupon' => $code, 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => (int) $row->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			if ( false !== $upd ) {
				if ( class_exists( 'MEYVC_Database' ) ) {
					MEYVC_Database::invalidate_table_cache_after_write( $table );
				}
				if ( function_exists( 'wp_cache_flush_group' ) ) {
					wp_cache_flush_group( 'meyvora_meyvc' );
				}
			}
		}
		return $code;
	}

	/**
	 * Check if a coupon code exists and is still usable (not expired, usage limit not reached).
	 *
	 * @param string $code Coupon code.
	 * @return bool
	 */
	public static function is_coupon_usable( $code ) {
		if ( ! function_exists( 'wc_get_coupon_id_by_code' ) ) {
			return false;
		}
		$id = wc_get_coupon_id_by_code( $code );
		if ( ! $id ) {
			return false;
		}
		$coupon = new WC_Coupon( $code );
		if ( ! $coupon->get_id() ) {
			return false;
		}
		if ( $coupon->get_date_expires() && $coupon->get_date_expires()->getTimestamp() < time() ) {
			return false;
		}
		$limit = $coupon->get_usage_limit();
		if ( $limit > 0 && $coupon->get_usage_count() >= $limit ) {
			return false;
		}
		return true;
	}

	/**
	 * Create a new WooCommerce coupon for this abandoned cart. Single-use, tied to user/email.
	 *
	 * @param object $row  Abandoned cart row.
	 * @param array  $opts Settings (discount_type, discount_amount, coupon_ttl_hours, etc.).
	 * @return string|null Coupon code or null on failure.
	 */
	private static function create_coupon( $row, array $opts ) {
		$code = self::generate_unique_code( (int) $row->id );
		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		if ( $coupon->get_id() > 0 ) {
			return null;
		}

		$discount_type = isset( $opts['discount_type'] ) ? sanitize_text_field( $opts['discount_type'] ) : 'percent';
		$valid_types   = array_keys( wc_get_coupon_types() );
		if ( ! in_array( $discount_type, $valid_types, true ) ) {
			if ( 'free_shipping' === $discount_type ) {
				$discount_type = 'fixed_cart';
			} else {
				$discount_type = 'percent';
			}
		}
		$amount = isset( $opts['discount_amount'] ) ? wc_format_decimal( $opts['discount_amount'] ) : '10';

		if ( 'free_shipping' === ( isset( $opts['discount_type'] ) ? $opts['discount_type'] : '' ) ) {
			$coupon->set_discount_type( 'fixed_cart' );
			$coupon->set_amount( 0 );
			$coupon->set_free_shipping( true );
		} else {
			$coupon->set_discount_type( $discount_type );
			$coupon->set_amount( $amount );
		}

		$coupon->set_usage_limit( 1 );
		$coupon->set_usage_limit_per_user( ! empty( $row->user_id ) ? 1 : 0 );

		$ttl = isset( $opts['coupon_ttl_hours'] ) ? max( 1, (int) $opts['coupon_ttl_hours'] ) : 48;
		$coupon->set_date_expires( time() + ( $ttl * HOUR_IN_SECONDS ) );

		if ( ! empty( $opts['minimum_cart_total'] ) && is_numeric( $opts['minimum_cart_total'] ) ) {
			$coupon->set_minimum_amount( (string) wc_format_decimal( $opts['minimum_cart_total'] ) );
		}
		if ( ! empty( $opts['exclude_sale_items'] ) ) {
			$coupon->set_exclude_sale_items( true );
		}
		$include_cat = isset( $opts['include_categories'] ) && is_array( $opts['include_categories'] ) ? $opts['include_categories'] : array();
		$exclude_cat = isset( $opts['exclude_categories'] ) && is_array( $opts['exclude_categories'] ) ? $opts['exclude_categories'] : array();
		$include_prod = isset( $opts['include_products'] ) && is_array( $opts['include_products'] ) ? $opts['include_products'] : array();
		$exclude_prod = isset( $opts['exclude_products'] ) && is_array( $opts['exclude_products'] ) ? $opts['exclude_products'] : array();
		$per_cat_discount = isset( $opts['per_category_discount'] ) && is_array( $opts['per_category_discount'] ) ? $opts['per_category_discount'] : array();
		$per_cat_discount = array_filter( $per_cat_discount, function ( $v, $k ) { return is_numeric( $k ) && absint( $k ) > 0 && is_numeric( $v ); }, ARRAY_FILTER_USE_BOTH );
		if ( ! empty( $per_cat_discount ) ) {
			$coupon->set_product_categories( array_map( 'absint', array_keys( $per_cat_discount ) ) );
			$amount = (string) wc_format_decimal( reset( $per_cat_discount ) );
			if ( 'free_shipping' !== ( isset( $opts['discount_type'] ) ? $opts['discount_type'] : '' ) ) {
				$coupon->set_amount( $amount );
			}
		} else {
			if ( ! empty( $include_cat ) ) {
				$coupon->set_product_categories( array_map( 'absint', $include_cat ) );
			}
		}
		if ( ! empty( $exclude_cat ) ) {
			$coupon->set_excluded_product_categories( array_map( 'absint', $exclude_cat ) );
		}
		if ( ! empty( $include_prod ) ) {
			$coupon->set_product_ids( array_map( 'absint', $include_prod ) );
		}
		if ( ! empty( $exclude_prod ) ) {
			$coupon->set_excluded_product_ids( array_map( 'absint', $exclude_prod ) );
		}

		$coupon->set_individual_use( false );
		$coupon->save();
		if ( ! $coupon->get_id() ) {
			return null;
		}
		update_post_meta( $coupon->get_id(), '_meyvc_abandoned_cart_id', (int) $row->id );
		return $code;
	}

	/**
	 * Generate a unique coupon code for abandoned cart.
	 *
	 * @param int $abandoned_cart_id Row id.
	 * @return string
	 */
	private static function generate_unique_code( $abandoned_cart_id ) {
		$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		do {
			$rand = '';
			for ( $i = 0; $i < 6; $i++ ) {
				$rand .= $chars[ wp_rand( 0, strlen( $chars ) - 1 ) ];
			}
			$code = 'CRO-AC-' . $abandoned_cart_id . '-' . $rand;
		} while ( function_exists( 'wc_get_coupon_id_by_code' ) && wc_get_coupon_id_by_code( $code ) );
		return $code;
	}
}
