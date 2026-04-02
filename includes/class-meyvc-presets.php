<?php
/**
 * Preset Library for campaigns and boosters.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MEYVC_Presets class.
 */
class MEYVC_Presets {

	/**
	 * Get all available presets.
	 *
	 * @return array
	 */
	public static function get_all() {
		$presets = self::get_preset_definitions();
		return array_values( $presets );
	}

	/**
	 * Get a single preset by ID.
	 *
	 * @param string $id Preset ID.
	 * @return array|null Preset array or null.
	 */
	public static function get( $id ) {
		$presets = self::get_preset_definitions();
		return isset( $presets[ $id ] ) ? $presets[ $id ] : null;
	}

	/**
	 * Apply a preset: write settings and optionally create a campaign.
	 *
	 * @param string $id Preset ID.
	 * @return array{ success: bool, message: string, campaign_id?: int }
	 */
	public static function apply( $id ) {
		$preset = self::get( $id );
		if ( ! $preset ) {
			return array( 'success' => false, 'message' => __( 'Preset not found.', 'meyvora-convert' ) );
		}

		if ( ! function_exists( 'meyvc_settings' ) ) {
			return array( 'success' => false, 'message' => __( 'Settings not available.', 'meyvora-convert' ) );
		}

		$settings = meyvc_settings();

		// 1. Enable/disable features
		$all_features = array( 'campaigns', 'sticky_cart', 'shipping_bar', 'trust_badges', 'cart_optimizer', 'checkout_optimizer', 'stock_urgency' );
		$enable      = isset( $preset['features'] ) ? (array) $preset['features'] : array();
		foreach ( $all_features as $feature ) {
			$settings->set( 'general', $feature . '_enabled', in_array( $feature, $enable, true ) );
		}

		// 2. Apply group settings
		if ( ! empty( $preset['settings'] ) && is_array( $preset['settings'] ) ) {
			foreach ( $preset['settings'] as $group => $pairs ) {
				if ( ! is_array( $pairs ) ) {
					continue;
				}
				foreach ( $pairs as $key => $value ) {
					$settings->set( $group, $key, $value );
				}
			}
		}

		// 3. Optional: create campaign
		$campaign_id = null;
		if ( ! empty( $preset['campaign'] ) && is_array( $preset['campaign'] ) && class_exists( 'MEYVC_Campaign' ) ) {
			$campaign_data = wp_parse_args(
				$preset['campaign'],
				array(
					'name'             => sanitize_text_field( $preset['name'] ?? '' ),
					'status'           => 'draft',
					'campaign_type'    => 'exit_intent',
					'template_type'    => 'centered',
					'trigger_settings' => array(),
					'content'          => array(),
					'styling'          => array(),
					'targeting_rules'  => array(),
					'display_rules'    => array(),
				)
			);
			$campaign_id = MEYVC_Campaign::create( $campaign_data );
		}

		$message = isset( $preset['apply_message'] ) ? $preset['apply_message'] : __( 'Preset applied successfully.', 'meyvora-convert' );
		if ( $campaign_id ) {
			$message .= ' ' . __( 'A new campaign was created.', 'meyvora-convert' );
		}

		return array(
			'success'     => true,
			'message'     => $message,
			'campaign_id' => $campaign_id,
		);
	}

	/**
	 * Preset definitions (id => preset array).
	 *
	 * @return array
	 */
	private static function get_preset_definitions() {
		return array(
			'free_shipping_bar' => array(
				'id'          => 'free_shipping_bar',
				'name'        => __( 'Free Shipping Bar', 'meyvora-convert' ),
				'description' => __( 'Shows a progress bar toward free shipping on product, cart, and shop. Encourages higher order value.', 'meyvora-convert' ),
				'features'    => array( 'shipping_bar' ),
				'settings'    => array(
					'shipping_bar' => array(
						'use_woo_threshold' => true,
						'threshold'         => 0,
						'tone'              => 'neutral',
						'message_progress'  => '',
						'message_achieved'  => '',
						'show_on_pages'     => array( 'product', 'cart', 'shop' ),
						'position'          => 'top',
						'bg_color'          => '#f7f7f7',
						'bar_color'         => '#333333',
					),
				),
				'apply_message' => __( 'Free shipping bar is now enabled on product, cart, and shop.', 'meyvora-convert' ),
			),

			'low_stock_urgency' => array(
				'id'          => 'low_stock_urgency',
				'name'        => __( 'Low Stock Urgency', 'meyvora-convert' ),
				'description' => __( 'Displays "Only X left!" on product pages when stock is low. Drives urgency without a campaign.', 'meyvora-convert' ),
				'features'    => array( 'stock_urgency' ),
				'settings'    => array(),
				'apply_message' => __( 'Low stock urgency messages are now enabled on product pages.', 'meyvora-convert' ),
			),

			'trust_badges_checkout' => array(
				'id'          => 'trust_badges_checkout',
				'name'        => __( 'Trust Badges & Checkout', 'meyvora-convert' ),
				'description' => __( 'Trust badges on product and cart; secure checkout message and badge on checkout to reduce friction.', 'meyvora-convert' ),
				'features'    => array( 'trust_badges', 'checkout_optimizer' ),
				'settings'    => array(
					'checkout_optimizer' => array(
						'show_trust_message' => true,
						'trust_message_text' => __( 'Secure checkout – your data is protected.', 'meyvora-convert' ),
						'show_secure_badge'  => true,
						'show_guarantee'     => true,
						'guarantee_text'     => __( '30-day money-back guarantee', 'meyvora-convert' ),
					),
				),
				'apply_message' => __( 'Trust badges and checkout trust elements are now enabled.', 'meyvora-convert' ),
			),

			'sticky_cta_minimal' => array(
				'id'          => 'sticky_cta_minimal',
				'name'        => __( 'Sticky CTA Minimal', 'meyvora-convert' ),
				'description' => __( 'Minimal sticky add-to-cart bar on product pages (mobile-first). Clean look, no image.', 'meyvora-convert' ),
				'features'    => array( 'sticky_cart' ),
				'settings'    => array(
					'sticky_cart' => array(
						'show_on_mobile_only' => true,
						'show_after_scroll'   => 150,
						'show_product_image'  => false,
						'show_product_title'  => true,
						'show_price'          => true,
						'button_text'         => __( 'Add to Cart', 'meyvora-convert' ),
						'bg_color'            => '#ffffff',
						'button_bg_color'     => '#333333',
						'button_text_color'   => '#ffffff',
					),
				),
				'apply_message' => __( 'Minimal sticky add-to-cart bar is now enabled.', 'meyvora-convert' ),
			),

			'exit_intent_email' => array(
				'id'          => 'exit_intent_email',
				'name'        => __( 'Exit Intent Email', 'meyvora-convert' ),
				'description' => __( 'Exit-intent popup that captures email and offers a discount. Targets product and cart pages.', 'meyvora-convert' ),
				'features'    => array( 'campaigns' ),
				'settings'    => array(),
				'campaign'    => array(
					'name'             => __( 'Exit Intent – Email Capture', 'meyvora-convert' ),
					'campaign_type'    => 'exit_intent',
					'template_type'    => 'centered',
					'status'           => 'draft',
					'trigger_settings' => array(
						'type'                => 'exit_intent',
						'sensitivity'         => 'medium',
						'require_interaction' => true,
					),
					'content' => array(
						'headline'          => __( 'Wait! Get 10% Off', 'meyvora-convert' ),
						'subheadline'       => __( 'Enter your email for a discount code.', 'meyvora-convert' ),
						'show_email_field'   => true,
						'email_placeholder'  => __( 'Your email', 'meyvora-convert' ),
						'show_coupon'        => true,
						'coupon_code'        => 'SAVE10',
						'coupon_label'       => __( 'Use code: SAVE10', 'meyvora-convert' ),
						'cta_text'           => __( 'Send My Code', 'meyvora-convert' ),
						'show_dismiss_link'  => true,
						'dismiss_text'       => __( 'No thanks', 'meyvora-convert' ),
					),
					'styling' => array(
						'bg_color'          => '#ffffff',
						'text_color'        => '#333333',
						'headline_color'    => '#000000',
						'button_bg_color'   => '#333333',
						'button_text_color' => '#ffffff',
						'border_radius'     => 8,
					),
					'targeting_rules' => array(
						'pages' => array(
							'type'           => 'specific',
							'include'        => array( 'product', 'cart' ),
							'excluded_pages' => array( 'checkout' ),
						),
					),
					'display_rules' => array(
						'frequency' => 'once_per_session',
					),
				),
				'apply_message' => __( 'Exit intent email campaign created. Review and activate it in Campaigns.', 'meyvora-convert' ),
			),

			'cart_upsell_reminder' => array(
				'id'          => 'cart_upsell_reminder',
				'name'        => __( 'Cart Upsell Reminder', 'meyvora-convert' ),
				'description' => __( 'Time-based popup on cart page reminding visitors of free shipping or an offer. Good for cart abandoners.', 'meyvora-convert' ),
				'features'    => array( 'campaigns' ),
				'settings'    => array(),
				'campaign'    => array(
					'name'             => __( 'Cart – Free Shipping Reminder', 'meyvora-convert' ),
					'campaign_type'    => 'time_trigger',
					'template_type'    => 'centered',
					'status'           => 'draft',
					'trigger_settings' => array(
						'type'              => 'time_trigger',
						'time_delay_seconds' => 15,
						'require_interaction' => false,
					),
					'content' => array(
						'headline'          => __( 'You\'re so close!', 'meyvora-convert' ),
						'subheadline'       => __( 'Add a bit more to your cart to get free shipping.', 'meyvora-convert' ),
						'show_email_field'   => false,
						'show_coupon'        => false,
						'cta_text'           => __( 'Continue to Cart', 'meyvora-convert' ),
						'show_dismiss_link'  => true,
					),
					'styling' => array(
						'bg_color'          => '#ffffff',
						'button_bg_color'   => '#333333',
						'button_text_color' => '#ffffff',
						'border_radius'     => 8,
					),
					'targeting_rules' => array(
						'pages' => array(
							'type'           => 'specific',
							'include'        => array( 'cart' ),
							'excluded_pages' => array(),
						),
					),
					'display_rules' => array(
						'frequency' => 'once_per_session',
					),
				),
				'apply_message' => __( 'Cart upsell reminder campaign created. Review and activate it in Campaigns.', 'meyvora-convert' ),
			),

			'quick_boost' => array(
				'id'          => 'quick_boost',
				'name'        => __( 'Quick Boost', 'meyvora-convert' ),
				'description' => __( 'Shipping bar + sticky add-to-cart + trust badges. Ideal first setup for new stores.', 'meyvora-convert' ),
				'features'    => array( 'shipping_bar', 'sticky_cart', 'trust_badges' ),
				'settings'    => array(
					'shipping_bar' => array(
						'use_woo_threshold' => true,
						'show_on_pages'     => array( 'product', 'cart', 'shop' ),
						'message_progress'  => __( 'You are {amount} away from free shipping!', 'meyvora-convert' ),
						'message_achieved'  => __( 'You qualify for free shipping!', 'meyvora-convert' ),
					),
					'sticky_cart' => array(
						'show_on_mobile_only' => true,
						'show_product_image'  => true,
						'show_product_title'  => true,
						'show_price'          => true,
						'button_text'         => __( 'Add to Cart', 'meyvora-convert' ),
					),
				),
				'apply_message' => __( 'Quick boost preset applied: shipping bar, sticky cart, and trust badges enabled.', 'meyvora-convert' ),
			),

			'conversion_stack' => array(
				'id'          => 'conversion_stack',
				'name'        => __( 'Conversion Stack', 'meyvora-convert' ),
				'description' => __( 'Full stack: shipping bar, sticky cart, trust badges, cart optimizer, and one exit-intent campaign. Maximize conversions.', 'meyvora-convert' ),
				'features'    => array( 'shipping_bar', 'sticky_cart', 'trust_badges', 'cart_optimizer', 'campaigns' ),
				'settings'    => array(
					'shipping_bar' => array(
						'use_woo_threshold' => true,
						'show_on_pages'     => array( 'product', 'cart', 'shop' ),
					),
					'cart_optimizer' => array(
						'show_trust_under_total' => true,
						'trust_message'         => __( 'Secure payment · Fast shipping · Easy returns', 'meyvora-convert' ),
						'show_urgency'           => true,
						'urgency_message'       => __( 'Items in your cart are in high demand!', 'meyvora-convert' ),
					),
				),
				'campaign' => array(
					'name'             => __( 'Exit Intent – Special Offer', 'meyvora-convert' ),
					'campaign_type'    => 'exit_intent',
					'template_type'    => 'centered',
					'status'           => 'draft',
					'trigger_settings' => array( 'type' => 'exit_intent', 'sensitivity' => 'medium' ),
					'content' => array(
						'headline'           => __( 'Wait! Don\'t leave yet', 'meyvora-convert' ),
						'subheadline'        => __( 'We have a special offer for you.', 'meyvora-convert' ),
						'show_email_field'    => true,
						'show_coupon'         => true,
						'coupon_code'        => 'WELCOME10',
						'coupon_label'       => 'Use code: WELCOME10',
						'cta_text'            => __( 'Claim My Discount', 'meyvora-convert' ),
						'show_dismiss_link'   => true,
					),
					'styling' => array(
						'bg_color' => '#ffffff',
						'button_bg_color' => '#333333',
						'button_text_color' => '#ffffff',
						'border_radius' => 8,
					),
					'targeting_rules' => array(
						'pages' => array(
							'type'           => 'specific',
							'include'        => array( 'product', 'cart' ),
							'excluded_pages' => array( 'checkout' ),
						),
					),
					'display_rules' => array( 'frequency' => 'once_per_session' ),
				),
				'apply_message' => __( 'Conversion stack applied. One exit-intent campaign was created – review and activate in Campaigns.', 'meyvora-convert' ),
			),
		);
	}

	/**
	 * Industry / vertical packs with inline campaigns and booster keys.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_industry_packs() {
		return array(
			array(
				'id'          => 'fashion',
				'name'        => __( 'Fashion & Apparel', 'meyvora-convert' ),
				'description' => __( 'Welcome discount, size-worry exit, and AOV booster for clothing stores.', 'meyvora-convert' ),
				'boosters'    => array( 'shipping_bar', 'trust_badges', 'sticky_cart' ),
				'campaigns'   => array(
					array(
						'name'            => __( 'New visitor — 10% off first order', 'meyvora-convert' ),
						'type'            => 'popup',
						'template_type'   => 'centered',
						'trigger_rules'   => array( 'type' => 'time', 'delay' => 8 ),
						'targeting_rules' => array(
							'must' => array(
								array( 'field' => 'user.order_count', 'operator' => 'equals', 'value' => '0' ),
							),
						),
						'content'         => array(
							'headline'         => __( 'Welcome! Here\'s 10% off your first order', 'meyvora-convert' ),
							'subheadline'      => __( 'Join thousands of happy shoppers', 'meyvora-convert' ),
							'cta_text'         => __( 'Get my discount', 'meyvora-convert' ),
							'show_email_field' => true,
						),
					),
					array(
						'name'            => __( 'Cart exit — free returns reassurance', 'meyvora-convert' ),
						'type'            => 'popup',
						'template_type'   => 'slide-bottom',
						'trigger_rules'   => array( 'type' => 'exit_intent' ),
						'targeting_rules' => array(
							'must' => array(
								array( 'field' => 'page_type', 'operator' => 'equals', 'value' => 'cart' ),
							),
						),
						'content'         => array(
							'headline'    => __( 'Not sure about the size?', 'meyvora-convert' ),
							'subheadline' => __( 'Free returns on all orders — no risk!', 'meyvora-convert' ),
							'cta_text'    => __( 'Complete my order', 'meyvora-convert' ),
						),
					),
				),
			),
			array(
				'id'          => 'electronics',
				'name'        => __( 'Electronics & Tech', 'meyvora-convert' ),
				'description' => __( 'Warranty trust nudge, cart abandonment offer, and checkout reassurance.', 'meyvora-convert' ),
				'boosters'    => array( 'shipping_bar', 'trust_badges', 'sticky_cart' ),
				'campaigns'   => array(
					array(
						'name'            => __( 'Product exit — warranty reminder', 'meyvora-convert' ),
						'type'            => 'popup',
						'template_type'   => 'corner',
						'trigger_rules'   => array( 'type' => 'exit_intent' ),
						'targeting_rules' => array(
							'must' => array(
								array( 'field' => 'page_type', 'operator' => 'equals', 'value' => 'product' ),
							),
						),
						'content'         => array(
							'headline'    => __( 'This item includes a 2-year warranty', 'meyvora-convert' ),
							'subheadline' => __( 'Free support included. Buy with confidence.', 'meyvora-convert' ),
							'cta_text'    => __( 'Add to cart', 'meyvora-convert' ),
						),
					),
					array(
						'name'            => __( 'Returning visitor — loyalty discount', 'meyvora-convert' ),
						'type'            => 'popup',
						'template_type'   => 'slide-bottom',
						'trigger_rules'   => array( 'type' => 'time', 'delay' => 15 ),
						'targeting_rules' => array(
							'must' => array(
								array( 'field' => 'user.order_count', 'operator' => '>', 'value' => 0 ),
							),
						),
						'content'         => array(
							'headline'         => __( 'Welcome back! Here\'s 5% off your next order', 'meyvora-convert' ),
							'subheadline'      => __( 'Thanks for being a loyal customer.', 'meyvora-convert' ),
							'cta_text'         => __( 'Claim offer', 'meyvora-convert' ),
							'show_email_field' => false,
						),
					),
				),
			),
			array(
				'id'          => 'beauty',
				'name'        => __( 'Beauty & Skincare', 'meyvora-convert' ),
				'description' => __( 'Ingredient trust popup, email capture for skincare tips, and free sample nudge.', 'meyvora-convert' ),
				'boosters'    => array( 'shipping_bar', 'trust_badges' ),
				'campaigns'   => array(
					array(
						'name'            => __( 'Email capture — skincare tips', 'meyvora-convert' ),
						'type'            => 'popup',
						'template_type'   => 'centered-image-left',
						'trigger_rules'   => array( 'type' => 'scroll', 'scroll_percent' => 40 ),
						'targeting_rules' => array(
							'must' => array(
								array( 'field' => 'page_type', 'operator' => 'equals', 'value' => 'product' ),
							),
						),
						'content'         => array(
							'headline'         => __( 'Get your personalised skincare guide', 'meyvora-convert' ),
							'subheadline'      => __( 'Free tips from our beauty experts, straight to your inbox.', 'meyvora-convert' ),
							'cta_text'         => __( 'Send me the guide', 'meyvora-convert' ),
							'show_email_field' => true,
						),
					),
					array(
						'name'            => __( 'Cart exit — free sample offer', 'meyvora-convert' ),
						'type'            => 'popup',
						'template_type'   => 'centered',
						'trigger_rules'   => array( 'type' => 'exit_intent' ),
						'targeting_rules' => array(
							'must' => array(
								array( 'field' => 'page_type', 'operator' => 'equals', 'value' => 'cart' ),
							),
						),
						'content'         => array(
							'headline'    => __( 'Wait — get a free sample with your order', 'meyvora-convert' ),
							'subheadline' => __( 'Add code SAMPLE at checkout.', 'meyvora-convert' ),
							'cta_text'    => __( 'Claim my sample', 'meyvora-convert' ),
						),
					),
				),
			),
			array(
				'id'          => 'food',
				'name'        => __( 'Food & Drink', 'meyvora-convert' ),
				'description' => __( 'Subscription upsell, bundle nudge, and first-order welcome for food stores.', 'meyvora-convert' ),
				'boosters'    => array( 'shipping_bar', 'sticky_cart' ),
				'campaigns'   => array(
					array(
						'name'            => __( 'First order welcome — 15% off', 'meyvora-convert' ),
						'type'            => 'popup',
						'template_type'   => 'bottom-bar',
						'trigger_rules'   => array( 'type' => 'page_load' ),
						'targeting_rules' => array(
							'must' => array(
								array( 'field' => 'user.order_count', 'operator' => 'equals', 'value' => '0' ),
							),
						),
						'content'         => array(
							'headline'         => __( '15% off your first order', 'meyvora-convert' ),
							'subheadline'      => __( 'Enter your email for the code.', 'meyvora-convert' ),
							'cta_text'         => __( 'Get my code', 'meyvora-convert' ),
							'show_email_field' => true,
						),
					),
					array(
						'name'            => __( 'Bundle nudge — save more', 'meyvora-convert' ),
						'type'            => 'popup',
						'template_type'   => 'slide-bottom',
						'trigger_rules'   => array( 'type' => 'time', 'delay' => 20 ),
						'targeting_rules' => array(
							'must' => array(
								array( 'field' => 'cart.item_count', 'operator' => 'equals', 'value' => '1' ),
							),
						),
						'content'         => array(
							'headline'    => __( 'Add one more item and save 10%', 'meyvora-convert' ),
							'subheadline' => __( 'Mix and match any products in your cart.', 'meyvora-convert' ),
							'cta_text'    => __( 'Keep shopping', 'meyvora-convert' ),
						),
					),
				),
			),
			array(
				'id'          => 'subscription',
				'name'        => __( 'Subscription & Membership', 'meyvora-convert' ),
				'description' => __( 'Upgrade nudge, renewal reminder, and member-only discount for subscription stores.', 'meyvora-convert' ),
				'boosters'    => array( 'trust_badges', 'sticky_cart' ),
				'campaigns'   => array(
					array(
						'name'            => __( 'Upgrade nudge — save 20% annually', 'meyvora-convert' ),
						'type'            => 'popup',
						'template_type'   => 'centered',
						'trigger_rules'   => array( 'type' => 'time', 'delay' => 10 ),
						'targeting_rules' => array(
							'must' => array(
								array( 'field' => 'user.logged_in', 'operator' => 'equals', 'value' => true ),
							),
						),
						'content'         => array(
							'headline'    => __( 'Switch to annual and save 20%', 'meyvora-convert' ),
							'subheadline' => __( 'Lock in your rate and never worry about monthly billing.', 'meyvora-convert' ),
							'cta_text'    => __( 'Switch now', 'meyvora-convert' ),
						),
					),
					array(
						'name'            => __( 'Member-only exit offer', 'meyvora-convert' ),
						'type'            => 'popup',
						'template_type'   => 'slide-bottom',
						'trigger_rules'   => array( 'type' => 'exit_intent' ),
						'targeting_rules' => array(
							'must' => array(
								array( 'field' => 'page_type', 'operator' => 'in', 'value' => array( 'cart', 'checkout' ) ),
								array( 'field' => 'user.logged_in', 'operator' => 'equals', 'value' => true ),
							),
						),
						'content'         => array(
							'headline'    => __( 'Members get an extra 10% off today', 'meyvora-convert' ),
							'subheadline' => __( 'Logged-in members only. Applied automatically.', 'meyvora-convert' ),
							'cta_text'    => __( 'Claim my discount', 'meyvora-convert' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Apply an industry pack (boosters + inline campaigns).
	 *
	 * @param string $pack_id Pack id from get_industry_packs().
	 * @return array{ success: bool, message: string, campaign_ids?: int[] }
	 */
	public static function apply_industry_pack( $pack_id ) {
		$pack_id = sanitize_key( (string) $pack_id );
		foreach ( self::get_industry_packs() as $pack ) {
			if ( empty( $pack['id'] ) || $pack['id'] !== $pack_id ) {
				continue;
			}

			$created_ids = array();
			$messages    = array();

			if ( ! empty( $pack['boosters'] ) && is_array( $pack['boosters'] ) && function_exists( 'meyvc_settings' ) ) {
				$labels = array();
				foreach ( $pack['boosters'] as $booster_key ) {
					$key = sanitize_key( (string) $booster_key );
					$opt = self::industry_booster_to_option( $key );
					if ( $opt !== '' ) {
						meyvc_settings()->set( 'general', $opt, true );
						$labels[] = $key;
					}
				}
				if ( ! empty( $labels ) ) {
					$messages[] = sprintf(
						/* translators: %s: comma-separated booster keys */
						__( 'Enabled boosters: %s', 'meyvora-convert' ),
						implode( ', ', $labels )
					);
				}
			}

			if ( ! empty( $pack['campaigns'] ) && is_array( $pack['campaigns'] ) && class_exists( 'MEYVC_Campaign' ) ) {
				foreach ( $pack['campaigns'] as $campaign_data ) {
					$row = self::industry_pack_campaign_create_data( $campaign_data );
					if ( null === $row ) {
						continue;
					}
					$new_id = MEYVC_Campaign::create( $row );
					if ( $new_id ) {
						$created_ids[] = (int) $new_id;
					}
				}
				$messages[] = sprintf(
					/* translators: %d: number of campaigns */
					_n( 'Created %d campaign', 'Created %d campaigns', count( $created_ids ), 'meyvora-convert' ),
					count( $created_ids )
				);
			}

			return array(
				'success'      => true,
				'message'      => implode( '. ', array_filter( $messages ) ),
				'campaign_ids' => $created_ids,
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Pack not found.', 'meyvora-convert' ),
		);
	}

	/**
	 * Map pack booster slug to general setting key.
	 *
	 * @param string $key Booster key.
	 * @return string Setting key or empty.
	 */
	private static function industry_booster_to_option( $key ) {
		$map = array(
			'shipping_bar' => 'shipping_bar_enabled',
			'trust_badges' => 'trust_badges_enabled',
			'sticky_cart'  => 'sticky_cart_enabled',
		);
		return isset( $map[ $key ] ) ? $map[ $key ] : '';
	}

	/**
	 * Build MEYVC_Campaign::create row from inline pack campaign definition.
	 *
	 * @param array $campaign_data Pack campaign.
	 * @return array|null
	 */
	private static function industry_pack_campaign_create_data( array $campaign_data ) {
		if ( empty( $campaign_data['name'] ) || ! class_exists( 'MEYVC_Campaign_Model' ) ) {
			return null;
		}

		$model         = MEYVC_Campaign_Model::create_new();
		$trigger_in    = isset( $campaign_data['trigger_rules'] ) && is_array( $campaign_data['trigger_rules'] ) ? $campaign_data['trigger_rules'] : array();
		$t_type        = isset( $trigger_in['type'] ) ? (string) $trigger_in['type'] : 'exit_intent';
		$campaign_type  = 'exit_intent';
		$trigger_settings = is_array( $model->trigger_rules ) ? $model->trigger_rules : array();
		$trigger_settings = array_merge( $trigger_settings, $trigger_in );

		switch ( $t_type ) {
			case 'scroll':
				$campaign_type = 'scroll_trigger';
				if ( isset( $trigger_in['scroll_percent'] ) ) {
					$trigger_settings['scroll_depth_percent'] = (int) $trigger_in['scroll_percent'];
				}
				$trigger_settings['type'] = 'scroll';
				break;
			case 'time':
				$campaign_type                   = 'time_trigger';
				$delay                           = isset( $trigger_in['delay'] ) ? (int) $trigger_in['delay'] : 30;
				$trigger_settings['time_delay_seconds'] = max( 0, $delay );
				$trigger_settings['type']        = 'time';
				break;
			case 'page_load':
				$campaign_type                   = 'time_trigger';
				$trigger_settings['time_delay_seconds'] = 0;
				$trigger_settings['type']        = 'time';
				break;
			case 'exit_intent':
			default:
				$campaign_type            = 'exit_intent';
				$trigger_settings['type'] = 'exit_intent';
				break;
		}

		$content = isset( $campaign_data['content'] ) && is_array( $campaign_data['content'] ) ? $campaign_data['content'] : array();
		$content = array_merge( (array) $model->content, $content );

		$targeting = isset( $campaign_data['targeting_rules'] ) && is_array( $campaign_data['targeting_rules'] ) ? $campaign_data['targeting_rules'] : array();

		return array(
			'name'             => sanitize_text_field( $campaign_data['name'] ),
			'status'           => 'active',
			'campaign_type'    => $campaign_type,
			'template_type'    => sanitize_key( $campaign_data['template_type'] ?? 'centered' ),
			'trigger_settings' => $trigger_settings,
			'content'          => $content,
			'targeting_rules'  => $targeting,
			'display_rules'    => is_array( $model->frequency_rules ) ? $model->frequency_rules : array(),
		);
	}
}
