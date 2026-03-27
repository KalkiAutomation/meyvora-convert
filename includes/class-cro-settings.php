<?php
/**
 * Centralized settings management
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CRO_Settings class.
 *
 * Manages all plugin settings via the cro_settings table.
 */
class CRO_Settings {

	/**
	 * @return void
	 */
	private static function flush_meyvora_cro_read_cache() {
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'meyvora_cro' );
		}
	}

	/**
	 * Singleton instance.
	 *
	 * @var CRO_Settings|null
	 */
	private static $instance = null;

	/**
	 * In-memory settings cache.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Settings table name.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Whether DB self-heal (create_tables) was already attempted this request (avoids loops on reload).
	 *
	 * @var bool
	 */
	private static $settings_repair_attempted = false;

	/**
	 * Whether shutdown seed/reload was scheduled after repair.
	 *
	 * @var bool
	 */
	private static $repair_shutdown_scheduled = false;

	/**
	 * Get singleton instance.
	 *
	 * @return CRO_Settings
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'cro_settings';
		$this->load_settings();
	}

	// Loads all settings at once to avoid per-key queries (N+1 prevention).
	/**
	 * Load all settings from database.
	 */
	private function load_settings() {
		global $wpdb;

		$cache_key = 'meyvora_cro_' . md5( serialize( array( 'settings_all_rows', $this->table_name ) ) );
		$results   = wp_cache_get( $cache_key, 'meyvora_cro' );
		if ( false === $results ) {
			$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT setting_group, setting_key, setting_value FROM %i',
					$this->table_name
				),
				OBJECT
			);

			if ( is_array( $results ) ) {
				wp_cache_set( $cache_key, $results, 'meyvora_cro', 300 );
			}

			// Self-heal: if query failed (e.g. missing table), create tables and retry once per request.
			if ( null === $results && $wpdb->last_error && class_exists( 'CRO_Database' ) && ! self::$settings_repair_attempted ) {
				self::$settings_repair_attempted = true;
				CRO_Database::create_tables();
				// Defer defaults — CRO_Activator::set_default_settings() uses CRO_Settings::get_instance()
				// and cannot run synchronously while this constructor is still running.
				if ( class_exists( 'CRO_Activator' ) && ! self::$repair_shutdown_scheduled ) {
					self::$repair_shutdown_scheduled = true;
					add_action(
						'shutdown',
						static function () {
							if ( class_exists( 'CRO_Activator' ) ) {
								CRO_Activator::set_default_settings();
							}
							if ( class_exists( 'CRO_Settings' ) ) {
								CRO_Settings::get_instance()->reload();
							}
						},
						1
					);
				}
				$results = wp_cache_get( $cache_key, 'meyvora_cro' );
				if ( false === $results ) {
					$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
						$wpdb->prepare(
							'SELECT setting_group, setting_key, setting_value FROM %i',
							$this->table_name
						),
						OBJECT
					);
					if ( is_array( $results ) ) {
						wp_cache_set( $cache_key, $results, 'meyvora_cro', 300 );
					}
				}
			}
		}

		if ( null === $results || ! is_array( $results ) ) {
			return;
		}

		foreach ( $results as $row ) {
			$this->settings[ $row->setting_group ][ $row->setting_key ] = maybe_unserialize( $row->setting_value );
		}
	}

	/**
	 * Get a single setting value.
	 *
	 * @param string $group   Setting group (e.g. 'general', 'sticky_cart').
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if not found.
	 * @return mixed
	 */
	public function get( $group, $key, $default = null ) {
		// Ensure group and key are strings
		$group = $group ? (string) $group : '';
		$key = $key ? (string) $key : '';
		
		if ( ! $group || ! $key ) {
			return $default;
		}
		
		if ( isset( $this->settings[ $group ][ $key ] ) ) {
			return $this->settings[ $group ][ $key ];
		}

		return $default;
	}

	/**
	 * Reload all settings from the database (clears in-memory cache and loads again).
	 */
	public function reload() {
		$this->settings = array();
		$this->load_settings();
	}

	/**
	 * Set a single setting value.
	 *
	 * @param string $group   Setting group.
	 * @param string $key     Setting key.
	 * @param mixed  $value   Setting value.
	 * @param string $autoload 'yes' or 'no'.
	 * @return bool
	 */
	public function set( $group, $key, $value, $autoload = 'yes' ) {
		global $wpdb;

		$serialized = maybe_serialize( $value );

		$result = $wpdb->replace( $this->table_name, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
			array(
				'setting_group'  => $group,
				'setting_key'    => $key,
				'setting_value'  => $serialized,
				'autoload'       => $autoload,
			),
			array( '%s', '%s', '%s', '%s' )
		);

		if ( false !== $result ) {
			self::flush_meyvora_cro_read_cache();
			$this->settings[ $group ][ $key ] = $value;
			return true;
		}

		return false;
	}

	/**
	 * Get all settings in a group.
	 *
	 * @param string $group Setting group.
	 * @return array
	 */
	public function get_group( $group ) {
		return isset( $this->settings[ $group ] ) ? $this->settings[ $group ] : array();
	}

	/**
	 * Set multiple settings in a group.
	 *
	 * @param string $group  Setting group.
	 * @param array  $values Key => value pairs.
	 */
	public function set_group( $group, $values ) {
		foreach ( $values as $key => $value ) {
			$this->set( $group, $key, $value );
		}
	}

	/**
	 * Delete a single setting.
	 *
	 * @param string $group Setting group.
	 * @param string $key   Setting key.
	 * @return bool
	 */
	public function delete( $group, $key ) {
		global $wpdb;

		$result = $wpdb->delete( $this->table_name, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write operation; caching not applicable.
			array(
				'setting_group' => $group,
				'setting_key'   => $key,
			),
			array( '%s', '%s' )
		);

		if ( false !== $result ) {
			if ( class_exists( 'CRO_Database' ) ) {
				CRO_Database::invalidate_table_cache_after_write( $this->table_name );
			}
			self::flush_meyvora_cro_read_cache();
			unset( $this->settings[ $group ][ $key ] );
			return true;
		}

		return false;
	}

	/**
	 * Check if a feature is enabled.
	 *
	 * @param string $feature Feature slug (e.g. 'sticky_cart', 'shipping_bar').
	 * @return bool
	 */
	public function is_feature_enabled( $feature ) {
		if ( empty( $feature ) || ! is_string( $feature ) ) {
			return false;
		}
		// Campaigns default on when unset (matches activation seed); other features default off.
		$default = ( 'campaigns' === $feature ) ? true : false;
		return (bool) $this->get( 'general', $feature . '_enabled', $default );
	}

	/**
	 * Get sticky cart settings with defaults.
	 *
	 * @return array
	 */
	public function get_sticky_cart_settings() {
		return wp_parse_args(
			$this->get_group( 'sticky_cart' ),
			$this->get_sticky_cart_defaults()
		);
	}

	/**
	 * Get shipping bar settings with defaults.
	 *
	 * @return array
	 */
	public function get_shipping_bar_settings() {
		return wp_parse_args(
			$this->get_group( 'shipping_bar' ),
			$this->get_shipping_bar_defaults()
		);
	}

	/**
	 * Get stock urgency settings with defaults.
	 *
	 * @return array
	 */
	public function get_stock_urgency_settings() {
		return wp_parse_args(
			$this->get_group( 'stock_urgency' ),
			$this->get_stock_urgency_defaults()
		);
	}

	/**
	 * Get cart optimizer settings with defaults.
	 *
	 * @return array
	 */
	public function get_cart_optimizer_settings() {
		return wp_parse_args(
			$this->get_group( 'cart_optimizer' ),
			$this->get_cart_optimizer_defaults()
		);
	}

	/**
	 * Default cart optimizer settings.
	 *
	 * @return array
	 */
	private function get_cart_optimizer_defaults() {
		return array(
			'show_trust_under_total' => false,
			'trust_message'         => __( 'Secure payment - Fast shipping - Easy returns', 'meyvora-convert' ),
			'show_urgency'           => false,
			'urgency_message'        => __( 'Items in your cart are in high demand!', 'meyvora-convert' ),
			'show_benefits'          => false,
			'benefits_list'          => array(),
			'sticky_checkout_button'  => false,
			'checkout_button_text'   => __( 'Proceed to Checkout', 'meyvora-convert' ),
			'exit_intent_nudge'       => false,
			'exit_intent_message'     => __( 'Complete your order now — your discount is ready', 'meyvora-convert' ),
			'exit_intent_cta'         => __( 'Complete order', 'meyvora-convert' ),
		);
	}

	/**
	 * Get checkout optimizer settings with defaults.
	 *
	 * @return array
	 */
	public function get_checkout_settings() {
		return wp_parse_args(
			$this->get_group( 'checkout_optimizer' ),
			$this->get_checkout_defaults()
		);
	}

	/**
	 * Default sticky cart settings.
	 *
	 * @return array
	 */
	private function get_sticky_cart_defaults() {
		$tone = class_exists( 'CRO_Default_Copy' ) ? CRO_Default_Copy::TONE_NEUTRAL : 'neutral';
		return array(
			'enabled'             => false,
			'show_on_mobile_only' => true,
			'show_after_scroll'   => 100,
			'show_product_image'  => true,
			'show_product_title'   => true,
			'show_price'          => true,
			'show_quantity'       => false,
			'tone'                => $tone,
			'button_text'         => '',
			'bg_color'            => '#ffffff',
			'text_color'          => '#333333',
			'button_bg_color'      => '#333333',
			'button_text_color'    => '#ffffff',
			'position'            => 'bottom',
		);
	}

	/**
	 * Default shipping bar settings.
	 *
	 * @return array
	 */
	private function get_stock_urgency_defaults() {
		$tone = class_exists( 'CRO_Default_Copy' ) ? CRO_Default_Copy::TONE_NEUTRAL : 'neutral';
		return array(
			'tone'             => $tone,
			'message_template' => '',
		);
	}

	private function get_shipping_bar_defaults() {
		$tone = class_exists( 'CRO_Default_Copy' ) ? CRO_Default_Copy::TONE_NEUTRAL : 'neutral';
		return array(
			'enabled'             => false,
			'threshold'           => 0,
			'use_woo_threshold'   => true,
			'tone'                => $tone,
			'message_progress'    => '',
			'message_achieved'    => '',
			'show_on_pages'       => array( 'product', 'cart' ),
			'position'            => 'top',
			'bg_color'            => '#f7f7f7',
			'text_color'           => '#333333',
			'bar_color'            => '#333333',
			'icon'                 => 'truck',
		);
	}

	/**
	 * Default checkout optimizer settings.
	 *
	 * @return array
	 */
	private function get_checkout_defaults() {
		return array(
			'enabled'                  => false,
			'remove_company_field'     => false,
			'remove_address_2'         => false,
			'remove_phone'             => false,
			'remove_order_notes'       => false,
			'move_coupon_to_top'       => false,
			'add_trust_message'        => false,
			'show_trust_message'       => false,
			'trust_message_text'       => __( 'Secure checkout - Your data is protected', 'meyvora-convert' ),
			'show_secure_badge'        => false,
			'show_guarantee'            => false,
			'guarantee_text'            => __( '30-day money-back guarantee', 'meyvora-convert' ),
			'autofocus_first_field'    => true,
			'inline_field_validation'  => true,
			'inline_validation'        => true,
			'prefill_from_last_order'  => true,
			'show_express_checkout_prompt' => true,
			'express_prompt_text'      => __( 'Save time: use Apple Pay, Google Pay, or PayPal at the next step if your store offers them.', 'meyvora-convert' ),
			'show_progress_steps'      => false,
			'post_purchase_enabled'    => false,
			'post_purchase_product_ids'=> '',
			'post_purchase_discount_percent' => 0,
			'post_purchase_headline'   => __( 'Complete the look — add this to your next order', 'meyvora-convert' ),
			'post_purchase_subhead'    => '',
		);
	}

	/**
	 * Get global styles settings with defaults.
	 *
	 * @return array
	 */
	public function get_styles_settings() {
		return wp_parse_args(
			$this->get_group( 'styles' ),
			$this->get_styles_defaults()
		);
	}

	/**
	 * Default global styles settings.
	 *
	 * @return array
	 */
	private function get_styles_defaults() {
		return array(
			'primary_color'     => '#333333',
			'secondary_color'   => '#555555',
			'button_radius'     => 8,
			'font_size_scale'   => 1,
			'font_family'       => 'inherit',
			'border_radius'     => 8,
			'spacing'           => 8,
			'animation_speed'   => 'normal',
		);
	}

	/**
	 * Get offer banner settings with defaults.
	 *
	 * @return array enable_offer_banner (bool), banner_position (string: 'cart', 'checkout', 'both').
	 */
	public function get_offer_banner_settings() {
		return wp_parse_args(
			$this->get_group( 'offer_banner' ),
			$this->get_offer_banner_defaults()
		);
	}

	/**
	 * Default offer banner settings.
	 *
	 * @return array
	 */
	private function get_offer_banner_defaults() {
		return array(
			'enable_offer_banner' => false,
			'banner_position'     => 'cart',
		);
	}

	/**
	 * Get abandoned cart reminder settings with defaults.
	 *
	 * @return array enable_abandoned_cart_emails (bool), require_opt_in (bool, default true).
	 */
	public function get_abandoned_cart_settings() {
		return wp_parse_args(
			$this->get_group( 'abandoned_cart' ),
			$this->get_abandoned_cart_defaults()
		);
	}

	/**
	 * Default abandoned cart settings.
	 *
	 * @return array
	 */
	private function get_abandoned_cart_defaults() {
		return array(
			'enable_abandoned_cart_emails'   => false,
			'require_opt_in'                 => true,
			'email_1_delay_hours'            => 1,
			'email_2_delay_hours'            => 24,
			'email_3_delay_hours'            => 72,
			'enable_discount_in_emails'      => false,
			'discount_type'                  => 'percent',
			'discount_amount'                => 10,
			'coupon_ttl_hours'               => 48,
			'minimum_cart_total'             => '',
			'exclude_sale_items'             => false,
			'include_categories'             => array(),
			'exclude_categories'             => array(),
			'include_products'               => array(),
			'exclude_products'               => array(),
			'per_category_discount'          => array(),
			'generate_coupon_for_email'     => 1,
			'per_product_custom_discount'   => array(),
			'email_subject_template'        => __( 'You left something in your cart – {store_name}', 'meyvora-convert' ),
			'email_body_template'           => '',
			'email_brand_color'             => '#2563eb',
			'high_value_threshold'          => 100,
		);
	}

	/**
	 * Default email body template (plain HTML, mobile-friendly). Used when email_body_template is empty.
	 *
	 * @return string
	 */
	public function get_abandoned_cart_email_body_default() {
		return '<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><meta charset="UTF-8"></head><body style="margin:0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;font-size:16px;line-height:1.5;color:#333;">'
			. '<div style="max-width:600px;margin:0 auto;padding:20px;">'
			. '<p>Hi {first_name},</p>'
			. '<p>You left items in your cart on {store_name}.</p>'
			. '<p><strong>Your cart:</strong></p>'
			. '<p>{cart_items}</p>'
			. '<p><strong>Cart total: {cart_total}</strong></p>'
			. '<p><a href="{checkout_url}" style="display:inline-block;padding:12px 24px;background:#333;color:#fff;text-decoration:none;border-radius:6px;display: inline-block;">Complete your purchase</a></p>'
			. '{discount_text}'
			. '<p>Thanks,<br>{store_name}</p>'
			. '</div></body></html>';
	}

	/**
	 * Get banner frequency cap settings (max shows per visitor per 24h).
	 *
	 * @return array max_per_24h (int, 0 = unlimited).
	 */
	public function get_banner_frequency_settings() {
		return wp_parse_args(
			$this->get_group( 'banner_frequency' ),
			$this->get_banner_frequency_defaults()
		);
	}

	/**
	 * Default banner frequency settings.
	 *
	 * @return array
	 */
	private function get_banner_frequency_defaults() {
		return array(
			'max_per_24h' => 0,
		);
	}
}

/**
 * Global accessor for CRO settings.
 *
 * @return CRO_Settings
 */
function cro_settings() {
	return CRO_Settings::get_instance();
}
