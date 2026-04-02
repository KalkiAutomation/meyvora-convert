<?php
/**
 * Onboarding wizard helpers and goal-based auto-configuration.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the setup wizard has been completed (supports legacy option key).
 *
 * @return bool
 */
function meyvc_is_onboarding_complete() {
	if ( get_option( 'meyvc_onboarding_complete', false ) ) {
		return true;
	}
	return (bool) get_option( 'meyvc_onboarding_completed', false );
}

/**
 * Mark onboarding as finished (writes canonical + legacy keys).
 *
 * @return void
 */
function meyvc_mark_onboarding_complete() {
	update_option( 'meyvc_onboarding_complete', true );
	update_option( 'meyvc_onboarding_completed', true );
}

/**
 * Apply goal + store profile: enable features and create starter campaigns.
 */
class MEYVC_Onboarding {

	/**
	 * Run auto-configuration.
	 *
	 * @param string               $goal    Goal slug: recover_abandoned|grow_email|increase_aov|reduce_checkout.
	 * @param array<string, mixed> $profile Keys: store_type, aov_range, monthly_visitors.
	 * @return array{ features: string[], campaigns: array<int, array{name:string,id:int}> }
	 */
	public static function configure( $goal, array $profile ) {
		$goal = sanitize_key( $goal );
		$settings = function_exists( 'meyvc_settings' ) ? meyvc_settings() : null;
		if ( ! $settings || ! class_exists( 'MEYVC_Campaign' ) ) {
			return array(
				'features'  => array(),
				'campaigns' => array(),
			);
		}

		$features_on = array();
		$campaigns_out = array();

		$settings->set( 'general', 'plugin_enabled', true );
		$settings->set( 'general', 'campaigns_enabled', true );

		switch ( $goal ) {
			case 'recover_abandoned':
				$settings->set( 'abandoned_cart', 'enable_abandoned_cart_emails', true );
				$features_on[] = __( 'Abandoned cart recovery emails', 'meyvora-convert' );
				$settings->set( 'general', 'sticky_cart_enabled', true );
				$features_on[] = __( 'Sticky Cart', 'meyvora-convert' );
				$settings->set( 'general', 'shipping_bar_enabled', true );
				$features_on[] = __( 'Shipping Bar', 'meyvora-convert' );
				$id1 = self::create_campaign(
					__( 'Cart rescue — complete your order', 'meyvora-convert' ),
					'exit_intent',
					'slide-bottom',
					array(
						'headline'         => __( 'Wait — your cart is waiting!', 'meyvora-convert' ),
						'subheadline'      => __( 'Complete checkout now and we\'ll hold your items.', 'meyvora-convert' ),
						'cta_text'         => __( 'Back to cart', 'meyvora-convert' ),
						'show_email_field' => true,
						'email_placeholder' => __( 'Email for a reminder', 'meyvora-convert' ),
						'show_coupon'      => false,
					),
					self::target_cart_has_items()
				);
				if ( $id1 ) {
					$campaigns_out[] = array( 'id' => $id1, 'name' => __( 'Cart rescue — complete your order', 'meyvora-convert' ) );
				}
				$id2 = self::create_campaign(
					__( 'Product browse — still deciding?', 'meyvora-convert' ),
					'time',
					'centered',
					array(
						'headline'         => __( 'Need a little nudge?', 'meyvora-convert' ),
						'subheadline'      => __( 'Join our list for tips and exclusive offers.', 'meyvora-convert' ),
						'cta_text'         => __( 'Subscribe', 'meyvora-convert' ),
						'show_email_field' => true,
						'show_coupon'      => false,
					),
					self::target_products_new_visitors(),
					array( 'time_delay_seconds' => 12 )
				);
				if ( $id2 ) {
					$campaigns_out[] = array( 'id' => $id2, 'name' => __( 'Product browse — still deciding?', 'meyvora-convert' ) );
				}
				break;

			case 'grow_email':
				$settings->set( 'general', 'trust_badges_enabled', true );
				$features_on[] = __( 'Trust Badges', 'meyvora-convert' );
				$id1 = self::create_campaign(
					__( 'Welcome — join our list', 'meyvora-convert' ),
					'time',
					'centered',
					array(
						'headline'         => __( 'Get updates & insider deals', 'meyvora-convert' ),
						'subheadline'      => __( 'Drop your email — no spam, unsubscribe anytime.', 'meyvora-convert' ),
						'cta_text'         => __( 'Sign me up', 'meyvora-convert' ),
						'show_email_field' => true,
						'show_coupon'      => false,
					),
					self::target_new_visitor_exclude_checkout(),
					array( 'time_delay_seconds' => 8 )
				);
				if ( $id1 ) {
					$campaigns_out[] = array( 'id' => $id1, 'name' => __( 'Welcome — join our list', 'meyvora-convert' ) );
				}
				$id2 = self::create_campaign(
					__( 'Exit offer — stay in touch', 'meyvora-convert' ),
					'exit_intent',
					'minimal',
					array(
						'headline'         => __( 'Before you go — one quick favor?', 'meyvora-convert' ),
						'subheadline'      => __( 'Leave your email and we\'ll send you something special.', 'meyvora-convert' ),
						'cta_text'         => __( 'Yes, keep me posted', 'meyvora-convert' ),
						'show_email_field' => true,
						'show_coupon'      => false,
					),
					self::target_new_visitor_exclude_checkout()
				);
				if ( $id2 ) {
					$campaigns_out[] = array( 'id' => $id2, 'name' => __( 'Exit offer — stay in touch', 'meyvora-convert' ) );
				}
				break;

			case 'increase_aov':
				$settings->set( 'general', 'shipping_bar_enabled', true );
				$features_on[] = __( 'Shipping Bar', 'meyvora-convert' );
				$settings->set( 'general', 'sticky_cart_enabled', true );
				$features_on[] = __( 'Sticky Cart', 'meyvora-convert' );
				$settings->set( 'general', 'trust_badges_enabled', true );
				$features_on[] = __( 'Trust Badges', 'meyvora-convert' );
				$id1 = self::create_campaign(
					__( 'Cart boost — add more, save on shipping', 'meyvora-convert' ),
					'exit_intent',
					'slide-bottom',
					array(
						'headline'    => __( 'You\'re close to unlocking a better deal', 'meyvora-convert' ),
						'subheadline' => __( 'Review your cart — sometimes one more item hits free shipping.', 'meyvora-convert' ),
						'cta_text'    => __( 'View cart', 'meyvora-convert' ),
						'show_email_field' => false,
						'show_coupon' => false,
					),
					self::target_cart_has_items()
				);
				if ( $id1 ) {
					$campaigns_out[] = array( 'id' => $id1, 'name' => __( 'Cart boost — add more, save on shipping', 'meyvora-convert' ) );
				}
				break;

			case 'reduce_checkout':
			default:
				$settings->set( 'general', 'checkout_optimizer_enabled', true );
				$features_on[] = __( 'Checkout Optimizer', 'meyvora-convert' );
				$settings->set( 'general', 'trust_badges_enabled', true );
				$features_on[] = __( 'Trust Badges', 'meyvora-convert' );
				$id1 = self::create_campaign(
					__( 'Checkout reassurance', 'meyvora-convert' ),
					'exit_intent',
					'centered',
					array(
						'headline'    => __( 'Almost there — secure checkout', 'meyvora-convert' ),
						'subheadline' => __( 'Your payment is protected. Questions? Our team can help.', 'meyvora-convert' ),
						'cta_text'    => __( 'Continue checkout', 'meyvora-convert' ),
						'show_email_field' => false,
						'show_coupon' => false,
					),
					self::target_checkout_only()
				);
				if ( $id1 ) {
					$campaigns_out[] = array( 'id' => $id1, 'name' => __( 'Checkout reassurance', 'meyvora-convert' ) );
				}
				$id2 = self::create_campaign(
					__( 'Cart-to-checkout nudge', 'meyvora-convert' ),
					'scroll',
					'top-bar',
					array(
						'headline'    => __( 'Ready when you are', 'meyvora-convert' ),
						'subheadline' => __( 'Checkout is quick — your items are saved.', 'meyvora-convert' ),
						'cta_text'    => __( 'Go to checkout', 'meyvora-convert' ),
						'show_email_field' => false,
						'show_coupon' => false,
					),
					self::target_cart_has_items(),
					array(
						'type'                 => 'scroll',
						'scroll_depth_percent' => 40,
					)
				);
				if ( $id2 ) {
					$campaigns_out[] = array( 'id' => $id2, 'name' => __( 'Cart-to-checkout nudge', 'meyvora-convert' ) );
				}
				break;
		}

		$features_on = array_values( array_unique( array_merge( $features_on, array( __( 'Conversion campaigns', 'meyvora-convert' ) ) ) ) );

		/**
		 * Filter onboarding auto-configuration results.
		 *
		 * @param array{features:string[],campaigns:array<int,array{id:int,name:string}>} $result  Features and campaigns.
		 * @param string                                                                   $goal    Goal slug.
		 * @param array<string,mixed>                                                     $profile Profile fields.
		 */
		$result = apply_filters(
			'meyvc_onboarding_configure_result',
			array(
				'features'  => $features_on,
				'campaigns' => $campaigns_out,
			),
			$goal,
			$profile
		);

		return $result;
	}

	/**
	 * Persist profile for analytics / Task 3+ (JSON in wp_options per spec).
	 *
	 * @param string               $goal    Goal slug.
	 * @param array<string, mixed> $profile Profile.
	 * @return void
	 */
	public static function save_profile_option( $goal, array $profile ) {
		$payload = array(
			'goal'              => sanitize_key( $goal ),
			'store_type'        => isset( $profile['store_type'] ) ? sanitize_key( (string) $profile['store_type'] ) : '',
			'aov_range'         => isset( $profile['aov_range'] ) ? sanitize_key( (string) $profile['aov_range'] ) : '',
			'monthly_visitors'  => isset( $profile['monthly_visitors'] ) ? sanitize_key( (string) $profile['monthly_visitors'] ) : '',
			'configured_at'     => current_time( 'mysql', true ),
		);
		update_option( 'meyvc_onboarding_profile', wp_json_encode( $payload ), false );
	}

	/**
	 * @param array<string, mixed> $trigger_overrides Merge into default trigger_rules.
	 */
	private static function create_campaign( $name, $campaign_type, $template, array $content, array $targeting_rules, array $trigger_overrides = array() ) {
		$model = MEYVC_Campaign_Model::create_new();
		$trigger = array_merge( $model->trigger_rules, array( 'type' => $campaign_type ), $trigger_overrides );

		$content_full = array_merge( $model->content, $content );
		$display = array_merge(
			$model->frequency_rules,
			array( 'priority' => 10 )
		);

		$data = array(
			'name'             => $name,
			'status'           => 'active',
			'campaign_type'    => $campaign_type,
			'template_type'    => $template,
			'trigger_settings' => $trigger,
			'content'          => $content_full,
			'styling'          => class_exists( 'MEYVC_Activator' ) ? MEYVC_Activator::get_default_styling() : $model->styling,
			'targeting_rules'  => $targeting_rules,
			'display_rules'    => $display,
		);

		$new_id = MEYVC_Campaign::create( $data );
		return $new_id ? (int) $new_id : 0;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function target_cart_has_items() {
		$model = MEYVC_Campaign_Model::create_new();
		$t = $model->targeting_rules;
		$t['page_mode'] = 'include';
		$t['pages']     = array(
			'type'           => 'specific',
			'include'        => array( 'cart' ),
			'excluded_pages' => array(),
		);
		$t['behavior']['cart_status'] = 'has_items';
		return $t;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function target_checkout_only() {
		$model = MEYVC_Campaign_Model::create_new();
		$t = $model->targeting_rules;
		$t['page_mode'] = 'include';
		$t['pages']     = array(
			'type'           => 'specific',
			'include'        => array( 'checkout' ),
			'excluded_pages' => array(),
		);
		return $t;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function target_products_new_visitors() {
		$model = MEYVC_Campaign_Model::create_new();
		$t = $model->targeting_rules;
		$t['page_mode'] = 'include';
		$t['pages']     = array(
			'type'           => 'specific',
			'include'        => array( 'product', 'shop', 'home', 'product_category' ),
			'excluded_pages' => array( 'checkout' ),
		);
		$t['visitor']['type'] = 'new';
		return $t;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function target_new_visitor_exclude_checkout() {
		$model = MEYVC_Campaign_Model::create_new();
		$t = $model->targeting_rules;
		$t['page_mode'] = 'exclude';
		$t['pages']     = array(
			'type'           => 'specific',
			'include'        => array(),
			'excluded_pages' => array( 'checkout' ),
		);
		$t['visitor']['type'] = 'new';
		return $t;
	}
}
