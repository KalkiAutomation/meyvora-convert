<?php
/**
 * System status checks for Meyvora Convert (WooCommerce, HPOS, DB, cron, REST, cache).
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CRO_System_Status class.
 */
class CRO_System_Status {

	const REPORT_HEADER = 'Meyvora Convert System Status';

	/** @var string Object cache group for read-through DB queries. */
	private const DB_READ_CACHE_GROUP = 'meyvora_cro';

	/** @var int Read-through TTL (seconds). */
	private const DB_READ_CACHE_TTL = 300;

	/**
	 * @param string                    $descriptor 2–4 word slug.
	 * @param array<int|string|float> $params     Params.
	 * @return string
	 */
	private static function read_cache_key( string $descriptor, array $params ): string {
		return 'meyvora_cro_' . md5( $descriptor . '_' . implode( '_', array_map( 'strval', $params ) ) );
	}

	/**
	 * Run all checks and return structured results.
	 *
	 * @return array[] Each item: 'label' => string, 'status' => 'ok'|'warning'|'error', 'message' => string, 'detail' => string (optional).
	 */
	public static function run_checks() {
		$checks = array();

		$checks[] = self::check_woocommerce();
		$checks[] = self::check_hpos();
		$checks[] = self::check_cart_checkout_blocks();
		$checks[] = self::check_rest_endpoints();
		$checks[] = self::check_db_tables();
		$checks[] = self::check_ai_configuration();
		$checks[] = self::check_webhook_configuration();
		$checks[] = self::check_klaviyo_integration();
		$checks[] = self::check_mailchimp_integration();
		$checks[] = self::check_active_boosters();
		$checks[] = self::check_asset_loading_mode();
		$checks[] = self::check_cron();
		$checks[] = self::check_cache_plugins();
		$checks[] = self::check_wc_subscriptions();
		$checks[] = self::check_multisite();

		return $checks;
	}

	/**
	 * Run "Verify Install Package" checks: tables, blocks build assets, asset loading not site-wide.
	 * Used by Tools page and WP-CLI.
	 *
	 * @return array[] Each item: 'label' => string, 'pass' => bool, 'message' => string.
	 */
	public static function run_verify_package() {
		$results = array();

		// 1) Required tables exist
		$missing_tables = array();
		if ( class_exists( 'CRO_Database' ) ) {
			foreach ( CRO_Database::get_required_table_names() as $name ) {
				$table = CRO_Database::get_table( $name );
				if ( ! CRO_Database::table_exists( $table ) ) {
					$missing_tables[] = $table;
				}
			}
		} else {
			$missing_tables[] = __( 'CRO_Database not loaded', 'meyvora-convert' );
		}
		$results[] = array(
			'label'   => __( 'Required database tables', 'meyvora-convert' ),
			'pass'    => empty( $missing_tables ),
			'message' => empty( $missing_tables )
				? __( 'All CRO tables present', 'meyvora-convert' )
				/* translators: %s is a comma-separated list of missing database table names. */
				: sprintf( __( 'Missing: %s', 'meyvora-convert' ), implode( ', ', $missing_tables ) ),
		);

		// 2) Blocks build assets exist
		$build_dir = defined( 'CRO_PLUGIN_DIR' ) ? CRO_PLUGIN_DIR . 'blocks/cart-checkout-extension/build/' : '';
		$has_js   = $build_dir && is_readable( $build_dir . 'index.js' );
		$has_php  = $build_dir && is_readable( $build_dir . 'index.asset.php' );
		$results[] = array(
			'label'   => __( 'Blocks build assets', 'meyvora-convert' ),
			'pass'    => $has_js && $has_php,
			'message' => $has_js && $has_php
				? __( 'index.js and index.asset.php present', 'meyvora-convert' )
				: sprintf(
					/* translators: %s is a comma-separated list of missing build asset file names. */
					__( 'Missing: %s', 'meyvora-convert' ),
					implode( ', ', array_filter( array(
						$has_js ? '' : 'blocks/cart-checkout-extension/build/index.js',
						$has_php ? '' : 'blocks/cart-checkout-extension/build/index.asset.php',
					) ) )
				),
		);

		// 3) Key features don't enqueue assets site-wide (conditional loading)
		$forced_global = false;
		if ( function_exists( 'apply_filters' ) ) {
			$forced_global = apply_filters( 'cro_should_enqueue_assets', null, 'global' );
		}
		$results[] = array(
			'label'   => __( 'Asset loading not site-wide', 'meyvora-convert' ),
			'pass'    => true !== $forced_global,
			'message' => true === $forced_global
				? __( 'Assets are enqueued site-wide (filter override). Prefer conditional loading.', 'meyvora-convert' )
				: __( 'Conditional loading (WooCommerce/feature pages only)', 'meyvora-convert' ),
		);

		return $results;
	}

	/**
	 * Run "Verify Installation" checks for System Status page.
	 * Order: WooCommerce active, Blocks integration registered, Blocks build assets,
	 * REST offer endpoints, Required DB tables (run create_tables if missing), Email scheduler, wp_mail capability.
	 *
	 * @return array[] Each item: 'label' => string, 'pass' => bool, 'message' => string.
	 */
	public static function run_verify_installation() {
		global $wpdb;
		$results = array();

		// 1) WooCommerce active
		$wc_active = class_exists( 'WooCommerce' ) && function_exists( 'WC' );
		$results[] = array(
			'label'   => __( 'WooCommerce active', 'meyvora-convert' ),
			'pass'    => $wc_active,
			'message' => $wc_active
				/* translators: %s is the WooCommerce plugin version number. */
				? ( defined( 'WC_VERSION' ) ? sprintf( __( 'Active (version %s)', 'meyvora-convert' ), WC_VERSION ) : __( 'Active', 'meyvora-convert' ) )
				: __( 'WooCommerce is not active.', 'meyvora-convert' ),
		);

		// 2) Blocks integration registered (CRO IntegrationInterface with WooCommerce Blocks)
		$integration_class = class_exists( 'CRO_Blocks_Integration_WC' );
		$integration_hook  = has_action( 'woocommerce_blocks_cart_block_registration' ) || has_action( 'woocommerce_blocks_checkout_block_registration' );
		$blocks_registered = $integration_class && $integration_hook;
		$results[] = array(
			'label'   => __( 'Blocks integration registered', 'meyvora-convert' ),
			'pass'    => $blocks_registered,
			'message' => $blocks_registered
				? __( 'CRO Blocks integration is registered with WooCommerce Blocks.', 'meyvora-convert' )
				: ( $integration_class
					? __( 'Integration class exists but registration hooks not found (WooCommerce Blocks may be inactive).', 'meyvora-convert' )
					: __( 'CRO Blocks integration class not loaded.', 'meyvora-convert' ) ),
		);

		// 3) Blocks build assets exist
		$build_dir = defined( 'CRO_PLUGIN_DIR' ) ? CRO_PLUGIN_DIR . 'blocks/cart-checkout-extension/build/' : '';
		$has_js    = $build_dir && is_readable( $build_dir . 'index.js' );
		$has_php   = $build_dir && is_readable( $build_dir . 'index.asset.php' );
		$results[] = array(
			'label'   => __( 'Blocks build assets exist', 'meyvora-convert' ),
			'pass'    => $has_js && $has_php,
			'message' => $has_js && $has_php
				? __( 'index.js and index.asset.php present.', 'meyvora-convert' )
				: sprintf(
					/* translators: %s: list of missing asset file paths. */
					__( 'Missing: %s', 'meyvora-convert' ),
					implode( ', ', array_filter( array(
						$has_js ? '' : 'blocks/cart-checkout-extension/build/index.js',
						$has_php ? '' : 'blocks/cart-checkout-extension/build/index.asset.php',
					) ) )
				),
		);

		// 4) REST offer endpoints reachable (if present)
		$offer_url = rest_url( 'cro/v1/offer' );
		$offer_resp = wp_remote_get(
			$offer_url,
			array(
				'timeout'   => 8,
				'sslverify' => false,
			)
		);
		$offer_code = wp_remote_retrieve_response_code( $offer_resp );
		$offer_err  = is_wp_error( $offer_resp ) ? $offer_resp->get_error_message() : '';
		$offer_ok   = ! is_wp_error( $offer_resp ) && in_array( (int) $offer_code, array( 200, 401, 403 ), true );
		$results[] = array(
			'label'   => __( 'REST offer endpoints reachable', 'meyvora-convert' ),
			'pass'    => $offer_ok,
			'message' => $offer_ok
			/* translators: %d is the HTTP response code. */
				? sprintf( __( 'Reachable (HTTP %d)', 'meyvora-convert' ), $offer_code )
				: ( $offer_err ? $offer_err : sprintf( /* translators: %s is the HTTP response code. */ __( 'Unexpected response: HTTP %s', 'meyvora-convert' ), $offer_code ) ),
		);

		// 5) Required DB tables exist; if missing run create_tables() and show result + last_error
		$missing_tables = array();
		if ( class_exists( 'CRO_Database' ) ) {
			foreach ( CRO_Database::get_required_table_names() as $name ) {
				$table = CRO_Database::get_table( $name );
				if ( ! CRO_Database::table_exists( $table ) ) {
					$missing_tables[] = $table;
				}
			}
		}
		if ( ! empty( $missing_tables ) && class_exists( 'CRO_Database' ) ) {
			$delta_output = array();
			$ok = CRO_Database::create_tables( $delta_output );
			$last_error = $wpdb->last_error;
			$dbdelta_str = ! empty( $delta_output ) ? ' dbDelta: ' . wp_json_encode( $delta_output ) : '';
			$results[] = array(
				'label'   => __( 'Required DB tables exist', 'meyvora-convert' ),
				'pass'    => $ok,
				'message' => $ok
					? __( 'Tables created or updated successfully.', 'meyvora-convert' ) . ( $dbdelta_str ? ' ' . trim( $dbdelta_str ) : '' )
					: sprintf(
						/* translators: 1: list of missing tables, 2: db error and optional dbDelta */
						__( 'Missing: %1$s. create_tables() failed. %2$s%3$s', 'meyvora-convert' ),
						implode( ', ', $missing_tables ),
						$last_error ? ( __( 'last_error:', 'meyvora-convert' ) . ' ' . $last_error ) : __( 'Check DB user permissions.', 'meyvora-convert' ),
						$dbdelta_str
					),
			);
		} else {
			$results[] = array(
				'label'   => __( 'Required DB tables exist', 'meyvora-convert' ),
				'pass'    => true,
				'message' => __( 'All CRO tables present.', 'meyvora-convert' ),
			);
		}

		// 6) Email scheduler available (Action Scheduler else wp-cron fallback)
		$action_scheduler = function_exists( 'as_has_scheduled_action' );
		$results[] = array(
			'label'   => __( 'Email scheduler available', 'meyvora-convert' ),
			'pass'    => true,
			'message' => $action_scheduler
				? __( 'Action Scheduler available (WooCommerce).', 'meyvora-convert' )
				: __( 'wp-cron fallback (Action Scheduler not detected).', 'meyvora-convert' ),
		);

		// 7) Test email send capability (wp_mail) basic check
		$wp_mail_ok = is_callable( 'wp_mail' );
		$results[] = array(
			'label'   => __( 'Test email send capability (wp_mail)', 'meyvora-convert' ),
			'pass'    => $wp_mail_ok,
			'message' => $wp_mail_ok
				? __( 'wp_mail() is available.', 'meyvora-convert' )
				: __( 'wp_mail() is not callable.', 'meyvora-convert' ),
		);

		// 8) SelectWoo assets (for admin selects)
		$selectwoo_ok = false;
		if ( class_exists( 'WooCommerce' ) ) {
			$selectwoo_css = plugins_url( 'assets/css/select2.css', 'woocommerce/woocommerce.php' );
			$selectwoo_js  = plugins_url( 'assets/js/selectWoo/selectWoo.full.min.js', 'woocommerce/woocommerce.php' );
			$selectwoo_ok  = ! empty( $selectwoo_css ) && ! empty( $selectwoo_js );
		}
		$results[] = array(
			'label'   => __( 'SelectWoo assets (admin)', 'meyvora-convert' ),
			'pass'    => $selectwoo_ok,
			'message' => $selectwoo_ok
				? __( 'SelectWoo CSS and JS available.', 'meyvora-convert' )
				: __( 'WooCommerce or SelectWoo assets not found. Admin selects may fall back to native.', 'meyvora-convert' ),
		);

		// 9) Campaign builder assets
		$builder_css = defined( 'CRO_PLUGIN_DIR' ) && is_readable( CRO_PLUGIN_DIR . 'admin/css/cro-campaign-builder.css' );
		$builder_js  = defined( 'CRO_PLUGIN_DIR' ) && is_readable( CRO_PLUGIN_DIR . 'admin/js/cro-campaign-builder.js' );
		$results[] = array(
			'label'   => __( 'Campaign builder assets exist', 'meyvora-convert' ),
			'pass'    => $builder_css && $builder_js,
			'message' => ( $builder_css && $builder_js )
				? __( 'cro-campaign-builder.css and .js present.', 'meyvora-convert' )
				: sprintf(
					/* translators: %s is a comma-separated list of missing asset file names. */
					__( 'Missing: %s', 'meyvora-convert' ),
					implode( ', ', array_filter( array(
						$builder_css ? '' : 'admin/css/cro-campaign-builder.css',
						$builder_js ? '' : 'admin/js/cro-campaign-builder.js',
					) ) )
				),
		);

		// 10) UI & Builder Health: no legacy CRO admin CSS (design system is source of truth)
		$legacy_cro_css = array();
		$cro_css_handles = array();
		if ( isset( $GLOBALS['wp_styles'] ) && $GLOBALS['wp_styles'] instanceof \WP_Styles ) {
			$done = array_merge( $GLOBALS['wp_styles']->done, $GLOBALS['wp_styles']->queue );
			$legacy_handles = array( 'cro-admin', 'cro-admin-ui', 'cro-admin-modern', 'cro-admin-brand-identity' );
			foreach ( $legacy_handles as $handle ) {
				if ( in_array( $handle, $done, true ) ) {
					$legacy_cro_css[] = $handle;
				}
			}
			foreach ( $done as $handle ) {
				if ( strpos( $handle, 'cro' ) !== false && isset( $GLOBALS['wp_styles']->registered[ $handle ] ) ) {
					$cro_css_handles[] = $handle;
				}
			}
		}
		$results[] = array(
			'label'   => __( 'UI & Builder: no legacy CRO admin CSS', 'meyvora-convert' ),
			'pass'    => empty( $legacy_cro_css ),
			'message' => empty( $legacy_cro_css )
				? ( empty( $cro_css_handles ) ? __( 'Design system and page-specific only.', 'meyvora-convert' ) : sprintf( /* translators: %s is a comma-separated list of CSS handles. */ __( 'CRO CSS loaded: %s', 'meyvora-convert' ), implode( ', ', $cro_css_handles ) ) )
				: sprintf(
				/* translators: %s is a comma-separated list of CSS handle names. */
					__( 'Legacy CSS enqueued (remove from code): %s', 'meyvora-convert' ),
					implode( ', ', $legacy_cro_css )
				),
		);

		// 11) Builder script registered (in register_campaign_builder_assets); enqueued on page=cro-campaign-edit or cro-campaign-builder
		$builder_registered = wp_script_is( 'cro-campaign-builder', 'registered' );
		$results[] = array(
			'label'   => __( 'Campaign builder script registered', 'meyvora-convert' ),
			'pass'    => $builder_registered,
			'message' => $builder_registered
				? __( 'Builder JS registered; enqueued on campaign edit/builder pages.', 'meyvora-convert' )
				: __( 'cro-campaign-builder script not registered. Check register_campaign_builder_assets.', 'meyvora-convert' ),
		);

		return $results;
	}

	/**
	 * WooCommerce active.
	 *
	 * @return array
	 */
	private static function check_woocommerce() {
		$active = class_exists( 'WooCommerce' ) && function_exists( 'WC' );
		$version = $active && defined( 'WC_VERSION' ) ? WC_VERSION : '';
		return array(
			'label'   => __( 'WooCommerce active', 'meyvora-convert' ),
			'status'  => $active ? 'ok' : 'warning',
			'message'  => $active
				? ( $version ? sprintf( /* translators: %s is the WooCommerce version number. */ __( 'Active (version %s)', 'meyvora-convert' ), $version ) : __( 'Active', 'meyvora-convert' ) )
				: __( 'Not active', 'meyvora-convert' ),
			'detail'  => $active ? '' : __( 'Some features (shipping bar, sticky cart, cart/checkout optimizer) require WooCommerce.', 'meyvora-convert' ),
		);
	}

	/**
	 * HPOS (High-Performance Order Storage) compatibility.
	 *
	 * @return array
	 */
	private static function check_hpos() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array(
				'label'   => __( 'WooCommerce HPOS', 'meyvora-convert' ),
				'status'  => 'warning',
				'message'  => __( 'N/A — WooCommerce not active', 'meyvora-convert' ),
				'detail'  => '',
			);
		}
		$hpos_enabled = false;
		if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) && method_exists( 'Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' ) ) {
			$hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		}
		return array(
			'label'   => __( 'HPOS enabled/disabled', 'meyvora-convert' ),
			'status'  => 'ok',
			'message'  => $hpos_enabled ? __( 'HPOS enabled', 'meyvora-convert' ) : __( 'HPOS disabled (classic order tables)', 'meyvora-convert' ),
			'detail'  => __( 'Meyvora Convert is compatible with both HPOS and classic storage.', 'meyvora-convert' ),
		);
	}

	/**
	 * Cart/Checkout block detection (WooCommerce block-based cart/checkout).
	 *
	 * @return array
	 */
	private static function check_cart_checkout_blocks() {
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_page_id' ) ) {
			return array(
				'label'   => __( 'Cart/Checkout block detection', 'meyvora-convert' ),
				'status'  => 'warning',
				'message'  => __( 'N/A — WooCommerce not active', 'meyvora-convert' ),
				'detail'  => '',
			);
		}
		$cart_block = false;
		$checkout_block = false;
		if ( class_exists( 'WC_Blocks_Utils' ) && method_exists( 'WC_Blocks_Utils', 'has_block_in_page' ) ) {
			$cart_page_id = wc_get_page_id( 'cart' );
			$checkout_page_id = wc_get_page_id( 'checkout' );
			if ( $cart_page_id > 0 ) {
				$cart_block = WC_Blocks_Utils::has_block_in_page( $cart_page_id, 'woocommerce/cart' );
			}
			if ( $checkout_page_id > 0 ) {
				$checkout_block = WC_Blocks_Utils::has_block_in_page( $checkout_page_id, 'woocommerce/checkout' );
			}
		}
		$parts = array();
		if ( $cart_block ) {
			$parts[] = __( 'Cart: block', 'meyvora-convert' );
		} else {
			$parts[] = __( 'Cart: shortcode', 'meyvora-convert' );
		}
		if ( $checkout_block ) {
			$parts[] = __( 'Checkout: block', 'meyvora-convert' );
		} else {
			$parts[] = __( 'Checkout: shortcode', 'meyvora-convert' );
		}
		return array(
			'label'   => __( 'Cart/Checkout block detection', 'meyvora-convert' ),
			'status'  => 'ok',
			'message'  => implode( ' · ', $parts ),
			'detail'  => '',
		);
	}

	/**
	 * CRO custom tables status (all plugin tables).
	 *
	 * @return array
	 */
	private static function check_db_tables() {
		global $wpdb;
		// Table names must match CRO_Database::create_tables() (prefix-safe).
		$tables = array(
			'cro_campaigns'      => $wpdb->prefix . 'cro_campaigns',
			'cro_events'         => $wpdb->prefix . 'cro_events',
			'cro_emails'          => $wpdb->prefix . 'cro_emails',
			'cro_settings'       => $wpdb->prefix . 'cro_settings',
			'cro_ab_tests'        => $wpdb->prefix . 'cro_ab_tests',
			'cro_ab_variations'   => $wpdb->prefix . 'cro_ab_variations',
			'cro_ab_assignments'  => $wpdb->prefix . 'cro_ab_assignments',
			'cro_daily_stats'     => $wpdb->prefix . 'cro_daily_stats',
			'cro_offers'          => $wpdb->prefix . 'cro_offers',
			'cro_offer_logs'      => $wpdb->prefix . 'cro_offer_logs',
		);
		$present = array();
		$missing = array();
		foreach ( $tables as $name => $full ) {
			$ck_table = self::read_cache_key( 'table_exists_like', array( $full ) );
			$found    = false;
			$tbl_var  = wp_cache_get( $ck_table, self::DB_READ_CACHE_GROUP, false, $found );
			if ( ! $found ) {
				$tbl_var = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				wp_cache_set( $ck_table, $tbl_var, self::DB_READ_CACHE_GROUP, self::DB_READ_CACHE_TTL );
			}
			$exists = ( $tbl_var === $full );
			if ( $exists ) {
				$present[] = $name;
			} else {
				$missing[] = $full;
			}
		}
		if ( empty( $missing ) ) {
			$col_warnings = array();
			$campaigns_tbl = $wpdb->prefix . 'cro_campaigns';
			$offers_tbl    = $wpdb->prefix . 'cro_offers';
			$ck_fb = self::read_cache_key( 'show_column_like', array( $campaigns_tbl, 'fallback_delay_seconds' ) );
			$fbf = false;
			$has_fb = wp_cache_get( $ck_fb, self::DB_READ_CACHE_GROUP, false, $fbf );
			if ( ! $fbf ) {
				$has_fb = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $campaigns_tbl, 'fallback_delay_seconds' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				wp_cache_set( $ck_fb, $has_fb, self::DB_READ_CACHE_GROUP, self::DB_READ_CACHE_TTL );
			}
			if ( ! $has_fb ) {
				$col_warnings[] = __( 'cro_campaigns.fallback_delay_seconds missing (run Repair Database Tables).', 'meyvora-convert' );
			}
			$ck_co = self::read_cache_key( 'show_column_like', array( $offers_tbl, 'conflict_ids' ) );
			$fco   = false;
			$has_conflict = wp_cache_get( $ck_co, self::DB_READ_CACHE_GROUP, false, $fco );
			if ( ! $fco ) {
				$has_conflict = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $offers_tbl, 'conflict_ids' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				wp_cache_set( $ck_co, $has_conflict, self::DB_READ_CACHE_GROUP, self::DB_READ_CACHE_TTL );
			}
			if ( ! $has_conflict ) {
				$col_warnings[] = __( 'cro_offers.conflict_ids missing (run Repair Database Tables).', 'meyvora-convert' );
			}
			if ( ! empty( $col_warnings ) ) {
				return array(
					'label'   => __( 'Custom tables status', 'meyvora-convert' ),
					'status'  => 'warning',
					'message' => implode( ' ', $col_warnings ),
					'detail'  => __( 'Use “Repair Database Tables” on the System Status page to apply migrations (CRO_Activator::maybe_upgrade_tables).', 'meyvora-convert' ),
				);
			}
			return array(
				'label'   => __( 'Custom tables status', 'meyvora-convert' ),
				'status'  => 'ok',
				'message'  => __( 'All CRO tables present', 'meyvora-convert' ),
				'detail'  => implode( ', ', $present ),
			);
		}
		return array(
			'label'   => __( 'Custom tables status', 'meyvora-convert' ),
			'status'  => count( $missing ) === count( $tables ) ? 'error' : 'warning',
		/* translators: %s is a comma-separated list of missing database table names. */
			'message'  => sprintf( __( 'Missing: %s', 'meyvora-convert' ), implode( ', ', $missing ) ),
			'detail'  => __( 'Deactivate and reactivate the plugin to create tables, or run the activation routine.', 'meyvora-convert' ),
		);
	}

	/**
	 * AI: any feature enabled requires a configured API key.
	 *
	 * @return array
	 */
	private static function check_ai_configuration() {
		$ai_enabled = false;
		$ai_features = array( 'feature_copy', 'feature_emails', 'feature_insights', 'feature_offers', 'feature_ab', 'feature_chat' );
		if ( function_exists( 'cro_settings' ) ) {
			foreach ( $ai_features as $f ) {
				if ( 'yes' === cro_settings()->get( 'ai', $f, 'yes' ) ) {
					$ai_enabled = true;
					break;
				}
			}
		}
		if ( ! $ai_enabled ) {
			return array(
				'label'   => __( 'AI features', 'meyvora-convert' ),
				'status'  => 'ok',
				'message' => __( 'AI features disabled.', 'meyvora-convert' ),
				'detail'  => '',
			);
		}
		$configured = class_exists( 'CRO_AI_Client' ) && CRO_AI_Client::is_configured();
		return array(
			'label'   => __( 'AI configuration (Anthropic API key)', 'meyvora-convert' ),
			'status'  => $configured ? 'ok' : 'warning',
			'message' => $configured
				? __( 'API key configured. Test connection in Settings → AI.', 'meyvora-convert' )
				: __( 'AI features are enabled but no API key is set. Add your key in Settings → AI.', 'meyvora-convert' ),
			'detail'  => '',
		);
	}

	/**
	 * Webhook endpoints (optional integration).
	 *
	 * @return array
	 */
	private static function check_webhook_configuration() {
		if ( ! class_exists( 'CRO_Webhook' ) || ! function_exists( 'cro_settings' ) ) {
			return array(
				'label'   => __( 'Webhook endpoints', 'meyvora-convert' ),
				'status'  => 'ok',
				'message' => __( 'Webhook class not loaded.', 'meyvora-convert' ),
				'detail'  => '',
			);
		}
		$endpoints = CRO_Webhook::get_endpoints();
		$active    = 0;
		foreach ( $endpoints as $ep ) {
			if ( ! empty( $ep['active'] ) ) {
				++$active;
			}
		}
		return array(
			'label'   => __( 'Webhook endpoints', 'meyvora-convert' ),
			'status'  => 'ok',
			'message' => $active > 0
				/* translators: %d: number of active webhook endpoints */
				? sprintf( __( '%d active endpoint(s) configured.', 'meyvora-convert' ), $active )
				: __( 'No active webhook endpoints (optional).', 'meyvora-convert' ),
			'detail'  => __( 'Configure outbound webhooks under Developer settings if you need external integrations.', 'meyvora-convert' ),
		);
	}

	/**
	 * Klaviyo integration (optional).
	 *
	 * @return array
	 */
	private static function check_klaviyo_integration() {
		$int_settings = function_exists( 'cro_settings' ) ? cro_settings()->get_group( 'integrations' ) : array();
		$enabled      = ! empty( $int_settings['klaviyo_enabled'] );
		if ( ! $enabled ) {
			return array(
				'label'   => __( 'Klaviyo integration', 'meyvora-convert' ),
				'status'  => 'ok',
				'message' => __( 'Klaviyo integration disabled.', 'meyvora-convert' ),
				'detail'  => '',
			);
		}
		$klaviyo_key  = ! empty( $int_settings['klaviyo_api_key_enc'] ) && class_exists( 'CRO_Security' )
			? CRO_Security::decrypt_secret( $int_settings['klaviyo_api_key_enc'] )
			: '';
		$klaviyo_list = isset( $int_settings['klaviyo_list_id'] ) ? trim( (string) $int_settings['klaviyo_list_id'] ) : '';
		$ok           = $klaviyo_key !== '' && $klaviyo_list !== '';
		return array(
			'label'   => __( 'Klaviyo integration', 'meyvora-convert' ),
			'status'  => $ok ? 'ok' : 'warning',
			'message' => $ok
				? __( 'API key and List ID configured.', 'meyvora-convert' )
				: __( 'Enabled but API key or List ID is missing — go to Settings → Integrations.', 'meyvora-convert' ),
			'detail'  => '',
		);
	}

	/**
	 * Mailchimp integration (optional).
	 *
	 * @return array
	 */
	private static function check_mailchimp_integration() {
		$int_settings = function_exists( 'cro_settings' ) ? cro_settings()->get_group( 'integrations' ) : array();
		$mc_enabled   = ! empty( $int_settings['mailchimp_enabled'] );
		if ( ! $mc_enabled ) {
			return array(
				'label'   => __( 'Mailchimp integration', 'meyvora-convert' ),
				'status'  => 'ok',
				'message' => __( 'Mailchimp integration disabled.', 'meyvora-convert' ),
				'detail'  => '',
			);
		}
		$mc_key_enc = isset( $int_settings['mailchimp_api_key_enc'] ) ? $int_settings['mailchimp_api_key_enc'] : '';
		$mc_list    = isset( $int_settings['mailchimp_list_id'] ) ? trim( (string) $int_settings['mailchimp_list_id'] ) : '';
		$mc_key     = $mc_key_enc !== '' && class_exists( 'CRO_Security' )
			? CRO_Security::decrypt_secret( $mc_key_enc )
			: '';
		$ok         = $mc_key !== '' && $mc_list !== '';
		return array(
			'label'   => __( 'Mailchimp integration', 'meyvora-convert' ),
			'status'  => $ok ? 'ok' : 'warning',
			'message' => $ok
				? __( 'API key and Audience ID configured.', 'meyvora-convert' )
				: __( 'Enabled but API key or Audience ID is missing — go to Settings → Integrations.', 'meyvora-convert' ),
			'detail'  => '',
		);
	}

	/**
	 * Active boosters list (enabled features: sticky cart, shipping bar, trust badges, stock urgency, etc.).
	 *
	 * @return array
	 */
	private static function check_active_boosters() {
		if ( ! function_exists( 'cro_settings' ) ) {
			return array(
				'label'   => __( 'Active boosters list', 'meyvora-convert' ),
				'status'  => 'warning',
				'message'  => __( 'Settings unavailable', 'meyvora-convert' ),
				'detail'  => '',
			);
		}
		$settings = cro_settings();
		$boosters = array(
			'sticky_cart'     => __( 'Sticky add-to-cart', 'meyvora-convert' ),
			'shipping_bar'    => __( 'Free shipping bar', 'meyvora-convert' ),
			'trust_badges'    => __( 'Trust badges', 'meyvora-convert' ),
			'stock_urgency'   => __( 'Stock urgency', 'meyvora-convert' ),
			'campaigns'       => __( 'Conversion campaigns', 'meyvora-convert' ),
			'cart_optimizer'  => __( 'Cart optimizer', 'meyvora-convert' ),
			'checkout_optimizer' => __( 'Checkout optimizer', 'meyvora-convert' ),
		);
		$active = array();
		foreach ( $boosters as $key => $label ) {
			if ( $settings->is_feature_enabled( $key ) ) {
				$active[] = $label;
			}
		}
		$message = empty( $active ) ? __( 'None enabled', 'meyvora-convert' ) : implode( ', ', $active );
		return array(
			'label'   => __( 'Active boosters list', 'meyvora-convert' ),
			'status'  => 'ok',
			'message'  => $message,
			'detail'  => '',
		);
	}

	/**
	 * Plugin asset loading mode (conditional vs global).
	 *
	 * @return array
	 */
	private static function check_asset_loading_mode() {
		$forced_global = false;
		if ( function_exists( 'apply_filters' ) ) {
			$forced_global = apply_filters( 'cro_should_enqueue_assets', null, 'global' );
		}
		if ( true === $forced_global ) {
			$mode = __( 'Global (filter override)', 'meyvora-convert' );
		} elseif ( false === $forced_global ) {
			$mode = __( 'Disabled (filter override)', 'meyvora-convert' );
		} else {
			$mode = __( 'Conditional — WooCommerce and feature pages only', 'meyvora-convert' );
		}
		return array(
			'label'   => __( 'Plugin asset loading mode', 'meyvora-convert' ),
			'status'  => 'ok',
			'message'  => $mode,
			'detail'  => __( 'Assets load only where CRO features are active unless overridden by cro_should_enqueue_assets.', 'meyvora-convert' ),
		);
	}

	/**
	 * Cron status (if plugin uses cron).
	 *
	 * @return array
	 */
	private static function check_cron() {
		$crons = _get_cron_array();
		$cro_hooks = array( 'cro_process_background_queue', 'cro_cleanup_old_events', 'cro_aggregate_daily_stats' );
		$found = array();
		foreach ( $cro_hooks as $hook ) {
			foreach ( $crons as $ts => $jobs ) {
				if ( isset( $jobs[ $hook ] ) ) {
					$found[ $hook ] = $ts;
					break;
				}
			}
		}
		if ( empty( $found ) ) {
			return array(
				'label'   => __( 'Cron (scheduled tasks)', 'meyvora-convert' ),
				'status'  => 'warning',
				'message'  => __( 'No CRO cron events scheduled', 'meyvora-convert' ),
				'detail'  => __( 'Background processing and daily cleanup may not run. Ensure WP-Cron is not disabled, or use a system cron.', 'meyvora-convert' ),
			);
		}
		$next = min( array_values( $found ) );
		$next_readable = $next ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next ) : '—';
		return array(
			'label'   => __( 'Cron (scheduled tasks)', 'meyvora-convert' ),
			'status'  => 'ok',
				/* translators: %s is the date and time the next scheduled task will run. */
		'message'  => sprintf( __( 'Scheduled (next: %s)', 'meyvora-convert' ), $next_readable ),
			'detail'  => implode( ', ', array_keys( $found ) ),
		);
	}

	/**
	 * REST endpoints reachable.
	 *
	 * @return array
	 */
	private static function check_rest_endpoints() {
		$url = rest_url( 'meyvora-convert/v1/campaigns' );
		$resp = wp_remote_get( $url, array(
			'timeout' => 10,
			'sslverify' => false,
			'headers' => array(),
		) );
		$code = wp_remote_retrieve_response_code( $resp );
		$body = wp_remote_retrieve_body( $resp );
		if ( is_wp_error( $resp ) ) {
			return array(
				'label'   => __( 'REST API reachable', 'meyvora-convert' ),
				'status'  => 'error',
				'message'  => $resp->get_error_message(),
				'detail'  => $url,
			);
		}
		// 200 = success, 401 = auth required (endpoint exists)
		if ( in_array( (int) $code, array( 200, 401 ), true ) ) {
			return array(
				'label'   => __( 'REST API reachable', 'meyvora-convert' ),
				'status'  => 'ok',
			/* translators: %d is the HTTP response code. */
				'message'  => sprintf( __( 'Reachable (HTTP %d)', 'meyvora-convert' ), $code ),
				'detail'  => $url,
			);
		}
		return array(
			'label'   => __( 'REST API reachable', 'meyvora-convert' ),
			'status'  => ( $code >= 500 || $code === 0 ) ? 'error' : 'warning',
				/* translators: %d is the HTTP response code. */
		'message'  => sprintf( __( 'Unexpected response: HTTP %d', 'meyvora-convert' ), $code ),
			'detail'  => $url,
		);
	}

	/**
	 * Cache plugin detection.
	 *
	 * @return array
	 */
	private static function check_cache_plugins() {
		$detected = array();
		$checks = array(
			'WP Super Cache'     => defined( 'WPSUPERCACHE' ) || function_exists( 'wp_super_cache_text_domain' ),
			'W3 Total Cache'     => defined( 'W3TC' ) || ( defined( 'W3TC_DIR' ) && W3TC_DIR ),
			'WP Rocket'          => defined( 'WP_ROCKET_VERSION' ),
			'LiteSpeed Cache'   => defined( 'LSCWP_VERS' ) || class_exists( 'LiteSpeed\Purge' ),
			'WP Fastest Cache'   => defined( 'WPFC_WP_PLUGIN_DIR' ) || function_exists( 'wpfc_clear_all_cache' ),
			'Cache Enabler'      => defined( 'CACHE_ENABLER_VERSION' ),
			'SG Optimizer'       => class_exists( 'SG_CachePress_Environment' ) || defined( 'SG_CACHEPRESS_VERSION' ),
			'Autoptimize'       => defined( 'AUTOPTIMIZE_PLUGIN_VERSION' ),
		);
		foreach ( $checks as $name => $active ) {
			if ( $active ) {
				$detected[] = $name;
			}
		}
		if ( empty( $detected ) ) {
			return array(
				'label'   => __( 'Cache plugins', 'meyvora-convert' ),
				'status'  => 'ok',
				'message'  => __( 'None detected', 'meyvora-convert' ),
				'detail'  => __( 'No known page/cache plugins detected. If you use object or fragment caching, exclude CRO cookies/endpoints if needed.', 'meyvora-convert' ),
			);
		}
		return array(
			'label'   => __( 'Cache plugins', 'meyvora-convert' ),
			'status'  => 'warning',
			'message'  => implode( ', ', $detected ),
			'detail'  => __( 'Ensure REST and campaign endpoints are not heavily cached; exclude CRO_Visitor_State cookie from cache key if applicable.', 'meyvora-convert' ),
		);
	}

	private static function check_wc_subscriptions() {
		$active = class_exists( 'WC_Subscriptions' );
		return array(
			'label'   => __( 'WooCommerce Subscriptions', 'meyvora-convert' ),
			'status'  => 'ok',
			'message' => $active
				? __( 'Active — renewal carts and orders are excluded from CRO tracking and offers.', 'meyvora-convert' )
				: __( 'Not installed.', 'meyvora-convert' ),
		);
	}

	private static function check_multisite() {
		if ( ! is_multisite() ) {
			return array(
				'label'   => __( 'Multisite', 'meyvora-convert' ),
				'status'  => 'ok',
				'message' => __( 'Single-site installation.', 'meyvora-convert' ),
			);
		}
		return array(
			'label'   => __( 'Multisite', 'meyvora-convert' ),
			'status'  => 'ok',
			'message' => __( 'Multisite detected. Plugin is per-site only — each site has isolated tables and settings. Network-wide activation is blocked.', 'meyvora-convert' ),
		);
	}

	/**
	 * Build a plain-text report for support (copyable).
	 *
	 * @param array[] $checks Result of run_checks().
	 * @return string
	 */
	public static function build_report( $checks = null ) {
		if ( $checks === null ) {
			$checks = self::run_checks();
		}
		$lines = array();
		$lines[] = '=== ' . self::REPORT_HEADER . ' ===';
		$lines[] = '';
		$lines[] = 'Generated: ' . gmdate( 'Y-m-d H:i:s' ) . ' UTC';
		$lines[] = 'Site: ' . home_url();
		$lines[] = 'WP: ' . get_bloginfo( 'version' );
		$lines[] = 'PHP: ' . PHP_VERSION;
		$lines[] = 'Meyvora Convert: ' . ( defined( 'CRO_VERSION' ) ? CRO_VERSION : 'n/a' );
		if ( class_exists( 'WooCommerce' ) && defined( 'WC_VERSION' ) ) {
			$lines[] = 'WooCommerce: ' . WC_VERSION;
		}
		$lines[] = '';
		$lines[] = '--- Checks ---';
		foreach ( $checks as $c ) {
			$status = isset( $c['status'] ) ? strtoupper( $c['status'] ) : '?';
			$label  = isset( $c['label'] ) ? $c['label'] : '';
			$msg    = isset( $c['message'] ) ? $c['message'] : '';
			$lines[] = '[' . $status . '] ' . $label . ': ' . $msg;
			if ( ! empty( $c['detail'] ) ) {
				$lines[] = '    ' . $c['detail'];
			}
		}
		$lines[] = '';
		$lines[] = '=== End report ===';
		return implode( "\n", $lines );
	}
}
