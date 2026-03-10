<?php
/**
 * Quick Launch: apply recommended CRO setup in one go.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Apply a quick-launch preset (e.g. 'recommended').
 * Enables shipping bar, sticky add-to-cart, and optionally stock urgency with sensible defaults.
 * Saves all settings in one transaction.
 *
 * @param string $preset Preset id. Currently only 'recommended' is supported.
 * @return bool True if applied successfully, false on invalid preset or failure.
 */
function cro_quick_launch_apply( $preset ) {
	if ( $preset !== 'recommended' ) {
		return false;
	}

	if ( ! function_exists( 'cro_settings' ) || ! class_exists( 'CRO_Database' ) ) {
		return false;
	}

	$settings = cro_settings();
	$woo_product_page_exists = class_exists( 'WooCommerce' );

	CRO_Database::begin_transaction();

	$ok = true;
	// General: enable features.
	$ok = $settings->set( 'general', 'shipping_bar_enabled', true ) && $ok;
	$ok = $settings->set( 'general', 'sticky_cart_enabled', true ) && $ok;
	if ( $woo_product_page_exists ) {
		$ok = $settings->set( 'general', 'stock_urgency_enabled', true ) && $ok;
	}

	// Offer banner: enable on cart and checkout.
	if ( method_exists( $settings, 'get_offer_banner_settings' ) ) {
		$ok = $settings->set( 'offer_banner', 'enable_offer_banner', true ) && $ok;
		$ok = $settings->set( 'offer_banner', 'banner_position', 'both' ) && $ok;
	}

	// Shipping bar: sensible defaults and default free shipping threshold.
	$tone = class_exists( 'CRO_Default_Copy' ) ? CRO_Default_Copy::TONE_NEUTRAL : 'neutral';
	$ok = $settings->set( 'shipping_bar', 'use_woo_threshold', false ) && $ok;
	$ok = $settings->set( 'shipping_bar', 'threshold', 50 ) && $ok;
	$ok = $settings->set( 'shipping_bar', 'tone', $tone ) && $ok;
	$ok = $settings->set( 'shipping_bar', 'message_progress', '' ) && $ok;
	$ok = $settings->set( 'shipping_bar', 'message_achieved', '' ) && $ok;
	$ok = $settings->set( 'shipping_bar', 'position', 'top' ) && $ok;
	$ok = $settings->set( 'shipping_bar', 'show_on_pages', array( 'product', 'cart' ) ) && $ok;
	$ok = $settings->set( 'shipping_bar', 'bg_color', '#f7f7f7' ) && $ok;
	$ok = $settings->set( 'shipping_bar', 'bar_color', '#333333' ) && $ok;
	$ok = $settings->set( 'shipping_bar', 'icon', 'truck' ) && $ok;

	// Sticky cart: sensible defaults.
	$ok = $settings->set( 'sticky_cart', 'show_on_mobile_only', true ) && $ok;
	$ok = $settings->set( 'sticky_cart', 'show_after_scroll', 100 ) && $ok;
	$ok = $settings->set( 'sticky_cart', 'show_product_image', true ) && $ok;
	$ok = $settings->set( 'sticky_cart', 'show_product_title', true ) && $ok;
	$ok = $settings->set( 'sticky_cart', 'show_price', true ) && $ok;
	$ok = $settings->set( 'sticky_cart', 'tone', $tone ) && $ok;
	$ok = $settings->set( 'sticky_cart', 'button_text', '' ) && $ok;
	$ok = $settings->set( 'sticky_cart', 'bg_color', '#ffffff' ) && $ok;
	$ok = $settings->set( 'sticky_cart', 'button_bg_color', '#333333' ) && $ok;
	$ok = $settings->set( 'sticky_cart', 'position', 'bottom' ) && $ok;

	// Stock urgency: sensible defaults.
	if ( $woo_product_page_exists ) {
		$ok = $settings->set( 'stock_urgency', 'tone', $tone ) && $ok;
		$ok = $settings->set( 'stock_urgency', 'message_template', '' ) && $ok;
	}

	if ( $ok ) {
		CRO_Database::commit();
	} else {
		CRO_Database::rollback();
	}
	return $ok;
}
