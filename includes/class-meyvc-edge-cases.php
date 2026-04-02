<?php
/**
 * CRO Edge Case Handlers
 *
 * Handle all edge cases and unusual situations
 *
 * @package Meyvora_Convert
 */

defined( 'ABSPATH' ) || exit;

class MEYVC_Edge_Cases {

	/** @var string Object cache group for read-through DB queries. */
	private const DB_READ_CACHE_GROUP = 'meyvora_meyvc';

	/** @var int Read-through TTL (seconds). */
	private const DB_READ_CACHE_TTL = 300;

	/**
	 * @param string                    $descriptor 2–4 word slug.
	 * @param array<int|string|float> $params     Params.
	 * @return string
	 */
	private static function read_cache_key( string $descriptor, array $params ): string {
		return 'meyvora_meyvc_' . md5( $descriptor . '_' . implode( '_', array_map( 'strval', $params ) ) );
	}

	/**
	 * Initialize edge case handlers
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'check_woocommerce' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'check_database_tables' ), 20 );
		add_filter( 'option_meyvc_settings', array( __CLASS__, 'validate_settings' ) );
		add_action( 'woocommerce_cart_loaded_from_session', array( __CLASS__, 'handle_cart_edge_cases' ) );
		add_action( 'wp_ajax_meyvc_handle_edge_case', array( __CLASS__, 'ajax_edge_case_handler' ) );
		add_action( 'wp_ajax_nopriv_meyvc_handle_edge_case', array( __CLASS__, 'ajax_edge_case_handler' ) );
	}

	/**
	 * Check if WooCommerce is active
	 */
	public static function check_woocommerce() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', function () {
				?>
				<div class="notice notice-error">
					<p>
						<strong><?php esc_html_e( 'Meyvora Convert', 'meyvora-convert' ); ?>:</strong>
						<?php esc_html_e( 'WooCommerce is required but not active. Please activate WooCommerce to use Meyvora Convert.', 'meyvora-convert' ); ?>
					</p>
				</div>
				<?php
			} );
			add_filter( 'meyvc_is_enabled', '__return_false' );
		}
	}

	/**
	 * Check if database tables exist and are valid
	 */
	public static function check_database_tables() {
		global $wpdb;

		if ( get_transient( 'meyvc_tables_ok' ) ) {
			return;
		}

		if ( ! class_exists( 'MEYVC_Database' ) ) {
			return;
		}

		$required_tables = array(
			$wpdb->prefix . 'meyvc_campaigns',
			$wpdb->prefix . 'meyvc_events',
		);

		foreach ( $required_tables as $table ) {
			if ( ! MEYVC_Database::table_exists( $table ) ) {
				if ( class_exists( 'MEYVC_Error_Handler' ) ) {
					MEYVC_Error_Handler::log( 'WARNING', 'Missing database table, attempting recreation', array( 'table' => $table ) );
				}

				if ( ! defined( 'MEYVC_PLUGIN_DIR' ) ) {
					return;
				}
				require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-activator.php';
				MEYVC_Activator::activate();

				if ( ! MEYVC_Database::table_exists( $table ) ) {
					$table_esc = $table;
					add_action( 'admin_notices', function () use ( $table_esc ) {
						?>
						<div class="notice notice-error">
							<p>
								<strong><?php esc_html_e( 'Meyvora Convert', 'meyvora-convert' ); ?>:</strong>
								<?php
								printf(
									/* translators: %s: table name */
									esc_html__( 'Database table %s is missing and could not be created. Please deactivate and reactivate the plugin.', 'meyvora-convert' ),
									'<code>' . esc_html( $table_esc ) . '</code>'
								);
								?>
							</p>
						</div>
						<?php
					} );
				}
				break;
			}
		}

		$tables_verified = true;
		foreach ( $required_tables as $table ) {
			if ( ! MEYVC_Database::table_exists( $table ) ) {
				$tables_verified = false;
				break;
			}
		}
		if ( $tables_verified ) {
			set_transient( 'meyvc_tables_ok', 1, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Validate and repair corrupt settings
	 *
	 * @param mixed $settings Settings value (option).
	 * @return array
	 */
	public static function validate_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			if ( class_exists( 'MEYVC_Error_Handler' ) ) {
				MEYVC_Error_Handler::log( 'WARNING', 'Corrupt settings detected, resetting to defaults' );
			}
			return self::get_default_settings();
		}

		$defaults = self::get_default_settings();
		foreach ( $defaults as $key => $default_value ) {
			if ( ! isset( $settings[ $key ] ) ) {
				$settings[ $key ] = $default_value;
			}
		}
		return $settings;
	}

	/**
	 * Get default settings structure
	 *
	 * @return array
	 */
	private static function get_default_settings() {
		return array(
			'general' => array(
				'enabled'        => true,
				'debug_mode'     => false,
				'exclude_admins' => true,
			),
			'popup'   => array(
				'max_per_session' => 2,
				'cooldown_hours'  => 1,
			),
		);
	}

	/**
	 * Handle cart edge cases
	 */
	public static function handle_cart_edge_cases() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		try {
			$cart = WC()->cart;

			if ( method_exists( $cart, 'get_total' ) ) {
				$total = $cart->get_total( 'raw' );
				if ( is_numeric( $total ) && $total < 0 && class_exists( 'MEYVC_Error_Handler' ) ) {
					MEYVC_Error_Handler::log( 'WARNING', 'Negative cart total detected', array( 'total' => $total ) );
				}
			}

			if ( method_exists( $cart, 'get_cart' ) ) {
				foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
					$data = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
					if ( ! $data || ! ( is_object( $data ) && method_exists( $data, 'exists' ) && $data->exists() ) ) {
						if ( class_exists( 'MEYVC_Error_Handler' ) ) {
							MEYVC_Error_Handler::log( 'INFO', 'Cart contains deleted product', array(
								'product_id' => isset( $cart_item['product_id'] ) ? $cart_item['product_id'] : 0,
							) );
						}
					}
				}
			}
		} catch ( Exception $e ) {
			if ( class_exists( 'MEYVC_Error_Handler' ) ) {
				MEYVC_Error_Handler::log( 'ERROR', 'Cart edge case handler error', array(
					'message' => $e->getMessage(),
				) );
			}
		}
	}

	/**
	 * Handle missing visitor ID
	 *
	 * @return string Visitor ID.
	 */
	public static function ensure_visitor_id() {
		if ( ! class_exists( 'MEYVC_Visitor_State' ) ) {
			return wp_generate_uuid4();
		}

		$visitor = MEYVC_Visitor_State::get_instance();
		$vid     = $visitor->get_visitor_id();

		if ( empty( $vid ) ) {
			if ( method_exists( $visitor, 'set_visitor_id' ) ) {
				$vid = wp_generate_uuid4();
				$visitor->set_visitor_id( $vid );
			} else {
				$vid = wp_generate_uuid4();
			}
			if ( class_exists( 'MEYVC_Error_Handler' ) ) {
				MEYVC_Error_Handler::log( 'INFO', 'Generated new visitor ID', array( 'visitor_id' => $vid ) );
			}
		}

		return $vid;
	}

	/**
	 * Handle missing campaign gracefully
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return object|null Campaign row or null.
	 */
	public static function get_campaign_safe( $campaign_id ) {
		if ( ! $campaign_id || ! is_numeric( $campaign_id ) ) {
			return null;
		}

		global $wpdb;
		$table    = $wpdb->prefix . 'meyvc_campaigns';
		$ck       = self::read_cache_key( 'campaign_by_id', array( (int) $campaign_id ) );
		$found    = false;
		$campaign = wp_cache_get( $ck, self::DB_READ_CACHE_GROUP, false, $found );
		if ( ! $found ) {
			$campaign = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
				$wpdb->prepare(
					'SELECT * FROM %i WHERE id = %d',
					$table,
					(int) $campaign_id
				)
			);
			wp_cache_set( $ck, $campaign, self::DB_READ_CACHE_GROUP, self::DB_READ_CACHE_TTL );
		}

		if ( ! $campaign ) {
			if ( class_exists( 'MEYVC_Error_Handler' ) ) {
				MEYVC_Error_Handler::log( 'WARNING', 'Campaign not found', array( 'campaign_id' => $campaign_id ) );
			}
			return null;
		}

		if ( empty( $campaign->content ) ) {
			$campaign->content = '{}';
		}
		if ( empty( $campaign->styling ) ) {
			$campaign->styling = '{}';
		}
		return $campaign;
	}

	/**
	 * Prevent duplicate impressions (concurrent requests)
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $visitor_id  Visitor ID.
	 * @return bool True if already recorded (duplicate), false if new.
	 */
	public static function prevent_duplicate_impressions( $campaign_id, $visitor_id ) {
		$key = 'meyvc_impression_' . (int) $campaign_id . '_' . sanitize_key( $visitor_id ) . '_' . gmdate( 'Y-m-d-H' );

		if ( get_transient( $key ) ) {
			return true;
		}
		set_transient( $key, true, HOUR_IN_SECONDS );
		return false;
	}

	/**
	 * Get safe timestamp (timezone-aware or fallback)
	 *
	 * @return int Unix timestamp.
	 */
	public static function get_safe_timestamp() {
		try {
			return (int) current_time( 'timestamp' );
		} catch ( Exception $e ) {
			return time();
		}
	}

	/**
	 * Check multisite compatibility
	 *
	 * @return bool
	 */
	public static function is_multisite_compatible() {
		if ( ! is_multisite() ) {
			return true;
		}
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active_for_network( defined( 'MEYVC_PLUGIN_BASENAME' ) ? MEYVC_PLUGIN_BASENAME : plugin_basename( dirname( __DIR__ ) . '/meyvora-convert.php' ) );
	}

	/**
	 * AJAX edge case handler
	 */
	public static function ajax_edge_case_handler() {
		$action = isset( $_POST['edge_action'] ) ? sanitize_text_field( wp_unslash( $_POST['edge_action'] ) ) : '';

		// Require nonce for all actions
		if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'meyvc_edge_case' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce', 'meyvora-convert' ) ), 403 );
		}
		$rl_ip = class_exists( 'MEYVC_Security' ) ? MEYVC_Security::get_client_ip() : '';
		if ( class_exists( 'MEYVC_Security' ) && ! MEYVC_Security::check_rate_limit( 'meyvc_ajax_' . sanitize_key( current_action() ) . '_' . $rl_ip, 20, 60 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please slow down.', 'meyvora-convert' ) ), 429 );
		}

		switch ( $action ) {
			case 'reset_visitor':
				if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
					wp_send_json_error( array( 'message' => __( 'Unauthorized', 'meyvora-convert' ) ), 403 );
				}
				if ( class_exists( 'MEYVC_Visitor_State' ) ) {
					$visitor = MEYVC_Visitor_State::get_instance();
					if ( method_exists( $visitor, 'reset' ) ) {
						$visitor->reset();
					}
				}
				wp_send_json_success();
				break;

			case 'clear_cache':
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( array( 'message' => __( 'Unauthorized', 'meyvora-convert' ) ), 403 );
				}
				if ( function_exists( 'wp_cache_flush' ) ) {
					wp_cache_flush();
				}
				wp_send_json_success();
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Unknown action', 'meyvora-convert' ) ) );
		}
	}
}

add_action( 'plugins_loaded', array( 'MEYVC_Edge_Cases', 'init' ), 15 );
