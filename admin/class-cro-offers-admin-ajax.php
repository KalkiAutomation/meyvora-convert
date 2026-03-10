<?php
/**
 * Secure admin AJAX handlers for offers (list, create, update, delete, duplicate, toggle).
 * Capability: manage_meyvora_convert. Nonce required. Option: cro_dynamic_offers (max 5 offers).
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class CRO_Offers_Admin_Ajax
 */
class CRO_Offers_Admin_Ajax {

	const OPTION_KEY   = 'cro_dynamic_offers';
	const MAX_OFFERS   = 5;
	const NONCE_ACTION = 'cro_offers_ajax';

	/**
	 * Register AJAX actions.
	 */
	public function __construct() {
		add_action( 'wp_ajax_cro_offer_list', array( $this, 'handle_list' ) );
		add_action( 'wp_ajax_cro_offer_create', array( $this, 'handle_create' ) );
		add_action( 'wp_ajax_cro_offer_update', array( $this, 'handle_update' ) );
		add_action( 'wp_ajax_cro_offer_delete', array( $this, 'handle_delete' ) );
		add_action( 'wp_ajax_cro_offer_duplicate', array( $this, 'handle_duplicate' ) );
		add_action( 'wp_ajax_cro_offer_toggle_active', array( $this, 'handle_toggle_active' ) );
		add_action( 'wp_ajax_cro_offer_reorder', array( $this, 'handle_reorder' ) );
		add_action( 'wp_ajax_cro_offer_test', array( $this, 'handle_test' ) );
	}

	/**
	 * Check capability and nonce; send JSON error on failure.
	 *
	 * @return bool True if authorized.
	 */
	private function auth() {
		if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
			$this->send_error( __( 'You do not have permission.', 'meyvora-convert' ), 403 );
			return false;
		}
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			$this->send_error( __( 'Invalid nonce. Please refresh and try again.', 'meyvora-convert' ), 403 );
			return false;
		}
		return true;
	}

	/**
	 * Send JSON error and exit.
	 *
	 * @param string       $message Error message.
	 * @param int          $code    HTTP status code.
	 * @param array<string, string> $errors Optional field-keyed validation errors for UI.
	 */
	private function send_error( $message, $code = 400, $errors = array() ) {
		$data = array(
			'message' => $message,
			'offers'  => $this->get_offers_normalized(),
		);
		if ( ! empty( $errors ) ) {
			$data['errors'] = $errors;
		}
		wp_send_json_error( $data, $code );
	}

	/**
	 * Send JSON success with offers.
	 *
	 * @param array $extra Optional extra data (e.g. created id).
	 */
	private function send_success( $extra = array() ) {
		$data = array_merge( array( 'offers' => $this->get_offers_normalized() ), $extra );
		wp_send_json_success( $data );
	}

	/**
	 * Get raw offers from option (flat format with id, updated_at). Pad to MAX_OFFERS.
	 * Ensures each used offer has an id (migrates legacy offers without id).
	 *
	 * @return array
	 */
	private function get_offers_raw() {
		$offers = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $offers ) ) {
			$offers = array();
		}
		$offers = array_pad( $offers, self::MAX_OFFERS, array() );
		$dirty = false;
		foreach ( $offers as $i => $o ) {
			if ( ! is_array( $o ) ) {
				$offers[ $i ] = array();
				continue;
			}
			$name = isset( $o['headline'] ) ? trim( (string) $o['headline'] ) : ( isset( $o['name'] ) ? trim( (string) $o['name'] ) : '' );
			if ( $name !== '' && empty( $o['id'] ) ) {
				$offers[ $i ]['id']         = $this->generate_id();
				$offers[ $i ]['updated_at'] = isset( $o['updated_at'] ) ? $o['updated_at'] : gmdate( 'c' );
				$dirty = true;
			}
		}
		if ( $dirty ) {
			update_option( self::OPTION_KEY, $offers );
		}
		return $offers;
	}

	/**
	 * Save raw offers to option (only non-empty slots up to MAX_OFFERS, then pad).
	 *
	 * @param array $offers Array of offer arrays (flat format).
	 */
	private function save_offers_raw( $offers ) {
		$out = array();
		for ( $i = 0; $i < self::MAX_OFFERS; $i++ ) {
			$out[] = isset( $offers[ $i ] ) && is_array( $offers[ $i ] ) ? $offers[ $i ] : array();
		}
		update_option( self::OPTION_KEY, $out );
	}

	/**
	 * Convert raw (flat) offer to normalized shape: id, name, enabled, priority, conditions, reward, limits, updated_at.
	 *
	 * @param array $raw Single offer from option (flat keys).
	 * @return array
	 */
	private function normalize_offer( $raw ) {
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		$name = isset( $raw['headline'] ) ? (string) $raw['headline'] : ( isset( $raw['name'] ) ? (string) $raw['name'] : '' );
		return array(
			'id'         => isset( $raw['id'] ) ? sanitize_text_field( $raw['id'] ) : $this->generate_id(),
			'name'       => $name,
			'enabled'    => ! empty( $raw['enabled'] ),
			'priority'   => isset( $raw['priority'] ) ? (int) $raw['priority'] : 10,
			'conditions' => array(
				'min_cart_total'                => isset( $raw['min_cart_total'] ) ? (float) $raw['min_cart_total'] : 0,
				'max_cart_total'                => isset( $raw['max_cart_total'] ) ? (float) $raw['max_cart_total'] : 0,
				'min_items'                     => isset( $raw['min_items'] ) ? (int) $raw['min_items'] : 0,
				'first_time_customer'           => ! empty( $raw['first_time_customer'] ),
				'returning_customer_min_orders' => isset( $raw['returning_customer_min_orders'] ) ? (int) $raw['returning_customer_min_orders'] : 0,
				'lifetime_spend_min'            => isset( $raw['lifetime_spend_min'] ) ? (float) $raw['lifetime_spend_min'] : 0,
				'allowed_roles'                 => isset( $raw['allowed_roles'] ) && is_array( $raw['allowed_roles'] ) ? array_map( 'sanitize_text_field', $raw['allowed_roles'] ) : array(),
				'excluded_roles'                => isset( $raw['excluded_roles'] ) && is_array( $raw['excluded_roles'] ) ? array_map( 'sanitize_text_field', $raw['excluded_roles'] ) : array(),
				'description'                   => isset( $raw['description'] ) ? sanitize_textarea_field( $raw['description'] ) : '',
			),
			'reward' => array(
				'type'            => isset( $raw['reward_type'] ) && in_array( $raw['reward_type'], array( 'percent', 'fixed', 'free_shipping' ), true ) ? $raw['reward_type'] : 'percent',
				'amount'          => isset( $raw['reward_amount'] ) ? (float) $raw['reward_amount'] : 10,
				'coupon_ttl_hours' => isset( $raw['coupon_ttl_hours'] ) ? absint( $raw['coupon_ttl_hours'] ) : 48,
				'individual_use'   => ! empty( $raw['individual_use'] ),
			),
			'limits' => array(
				'rate_limit_hours'        => isset( $raw['rate_limit_hours'] ) ? absint( $raw['rate_limit_hours'] ) : 6,
				'max_coupons_per_visitor'  => isset( $raw['max_coupons_per_visitor'] ) ? absint( $raw['max_coupons_per_visitor'] ) : 1,
				'exclude_sale_items'       => ! empty( $raw['exclude_sale_items'] ),
			),
			'updated_at' => isset( $raw['updated_at'] ) ? sanitize_text_field( $raw['updated_at'] ) : '',
		);
	}

	/**
	 * Get all offers in normalized shape (id, name, enabled, priority, conditions, reward, limits, updated_at).
	 * Only returns slots that have a name/headline (used slots).
	 *
	 * @return array
	 */
	private function get_offers_normalized() {
		$raw = $this->get_offers_raw();
		$out = array();
		foreach ( $raw as $r ) {
			$name = isset( $r['headline'] ) ? trim( (string) $r['headline'] ) : ( isset( $r['name'] ) ? trim( (string) $r['name'] ) : '' );
			if ( $name !== '' ) {
				$out[] = $this->normalize_offer( $r );
			}
		}
		return $out;
	}

	/**
	 * Flatten normalized offer (from request) to raw format for storage + engine compatibility.
	 *
	 * @param array  $n     Normalized offer (id, name, enabled, priority, conditions, reward, limits).
	 * @param string $id    Optional id (use existing or generate).
	 * @param string $time  Optional updated_at.
	 * @return array
	 */
	private function flatten_offer( $n, $id = null, $time = null ) {
		$conditions = isset( $n['conditions'] ) && is_array( $n['conditions'] ) ? $n['conditions'] : array();
		$reward     = isset( $n['reward'] ) && is_array( $n['reward'] ) ? $n['reward'] : array();
		$limits     = isset( $n['limits'] ) && is_array( $n['limits'] ) ? $n['limits'] : array();
		$time       = $time ? $time : gmdate( 'c' );
		return array(
			'id'                                => $id ? sanitize_text_field( $id ) : $this->generate_id(),
			'updated_at'                        => $time,
			'headline'                           => isset( $n['name'] ) ? sanitize_text_field( $n['name'] ) : '',
			'description'                        => isset( $conditions['description'] ) ? sanitize_textarea_field( $conditions['description'] ) : '',
			'enabled'                            => ! empty( $n['enabled'] ),
			'priority'                           => isset( $n['priority'] ) ? (int) $n['priority'] : 10,
			'min_cart_total'                     => isset( $conditions['min_cart_total'] ) ? (float) $conditions['min_cart_total'] : 0,
			'max_cart_total'                     => isset( $conditions['max_cart_total'] ) ? (float) $conditions['max_cart_total'] : 0,
			'min_items'                          => isset( $conditions['min_items'] ) ? (int) $conditions['min_items'] : 0,
			'first_time_customer'                => ! empty( $conditions['first_time_customer'] ),
			'returning_customer_min_orders'      => isset( $conditions['returning_customer_min_orders'] ) ? (int) $conditions['returning_customer_min_orders'] : 0,
			'lifetime_spend_min'                 => isset( $conditions['lifetime_spend_min'] ) ? (float) $conditions['lifetime_spend_min'] : 0,
			'allowed_roles'                      => isset( $conditions['allowed_roles'] ) && is_array( $conditions['allowed_roles'] ) ? array_map( 'sanitize_text_field', $conditions['allowed_roles'] ) : array(),
			'excluded_roles'                     => isset( $conditions['excluded_roles'] ) && is_array( $conditions['excluded_roles'] ) ? array_map( 'sanitize_text_field', $conditions['excluded_roles'] ) : array(),
			'reward_type'                        => isset( $reward['type'] ) && in_array( $reward['type'], array( 'percent', 'fixed', 'free_shipping' ), true ) ? $reward['type'] : 'percent',
			'reward_amount'                      => isset( $reward['amount'] ) ? (float) $reward['amount'] : 10,
			'coupon_ttl_hours'                    => isset( $reward['coupon_ttl_hours'] ) ? absint( $reward['coupon_ttl_hours'] ) : 48,
			'individual_use'                     => ! empty( $reward['individual_use'] ),
			'rate_limit_hours'                   => isset( $limits['rate_limit_hours'] ) ? absint( $limits['rate_limit_hours'] ) : 6,
			'max_coupons_per_visitor'             => isset( $limits['max_coupons_per_visitor'] ) ? absint( $limits['max_coupons_per_visitor'] ) : 1,
			'exclude_sale_items'                 => ! empty( $limits['exclude_sale_items'] ),
		);
	}

	/**
	 * Generate a unique id for an offer (uuid or uniqid).
	 *
	 * @return string
	 */
	private function generate_id() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}
		return 'cro_' . uniqid( '', true );
	}

	/**
	 * Sanitize array of strings from request (e.g. allowed_roles).
	 *
	 * @param mixed $input Request value.
	 * @return array
	 */
	private function sanitize_string_array( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'sanitize_text_field', wp_unslash( $input ) ), function ( $v ) {
			return $v !== '';
		} ) );
	}

	/**
	 * Find index of offer by id in raw list.
	 *
	 * @param array  $offers Raw offers array.
	 * @param string $id     Offer id.
	 * @return int|false Index or false.
	 */
	private function find_index_by_id( $offers, $id ) {
		$id = sanitize_text_field( $id );
		foreach ( $offers as $i => $o ) {
			if ( is_array( $o ) && isset( $o['id'] ) && $o['id'] === $id ) {
				return $i;
			}
		}
		return false;
	}

	/**
	 * Find first empty slot (no headline/name).
	 *
	 * @param array $offers Raw offers.
	 * @return int|false
	 */
	private function find_first_empty_slot( $offers ) {
		foreach ( $offers as $i => $o ) {
			$name = isset( $o['headline'] ) ? trim( (string) $o['headline'] ) : ( isset( $o['name'] ) ? trim( (string) $o['name'] ) : '' );
			if ( $name === '' ) {
				return $i;
			}
		}
		return false;
	}

	/**
	 * Count used slots.
	 *
	 * @param array $offers Raw offers.
	 * @return int
	 */
	private function count_used( $offers ) {
		$n = 0;
		foreach ( $offers as $o ) {
			$name = isset( $o['headline'] ) ? trim( (string) $o['headline'] ) : ( isset( $o['name'] ) ? trim( (string) $o['name'] ) : '' );
			if ( $name !== '' ) {
				$n++;
			}
		}
		return $n;
	}

	/**
	 * Get offers for display: array of { index, offer, rule_summary, reward_summary } sorted by priority (asc = top first).
	 *
	 * @return array{offers: array, offers_used_count: int, max_offers: int}
	 */
	private function get_offers_for_display() {
		$raw   = $this->get_offers_raw();
		$items = array();
		foreach ( $raw as $i => $o ) {
			$name = isset( $o['headline'] ) ? trim( (string) $o['headline'] ) : ( isset( $o['name'] ) ? trim( (string) $o['name'] ) : '' );
			if ( $name === '' ) {
				continue;
			}
			$items[] = array(
				'index'          => $i,
				'offer'          => $o,
				'rule_summary'   => class_exists( 'CRO_Offer_Presenter' ) ? CRO_Offer_Presenter::summarize_conditions( $o ) : '',
				'reward_summary' => class_exists( 'CRO_Offer_Presenter' ) ? CRO_Offer_Presenter::summarize_reward( $o ) : '',
			);
		}
		usort( $items, function ( $a, $b ) {
			$pa = isset( $a['offer']['priority'] ) ? (int) $a['offer']['priority'] : 10;
			$pb = isset( $b['offer']['priority'] ) ? (int) $b['offer']['priority'] : 10;
			if ( $pa !== $pb ) {
				return $pa - $pb;
			}
			return $a['index'] - $b['index'];
		} );
		return array(
			'offers'           => $items,
			'offers_used_count' => count( $items ),
			'max_offers'       => self::MAX_OFFERS,
		);
	}

	/**
	 * AJAX: List offers. Returns display list (index, offer, rule_summary, reward_summary) sorted by priority.
	 */
	public function handle_list() {
		if ( ! $this->auth() ) {
			return;
		}
		wp_send_json_success( $this->get_offers_for_display() );
	}

	/**
	 * AJAX: Create offer. POST body: offer (normalized object). Max 5 offers.
	 */
	public function handle_create() {
		if ( ! $this->auth() ) {
			return;
		}
		$offers = $this->get_offers_raw();
		if ( $this->count_used( $offers ) >= self::MAX_OFFERS ) {
			$this->send_error( __( 'Offer limit reached (5).', 'meyvora-convert' ), 400 );
			return;
		}
		$slot = $this->find_first_empty_slot( $offers );
		if ( $slot === false ) {
			$this->send_error( __( 'Offer limit reached (5).', 'meyvora-convert' ), 400 );
			return;
		}
		$input = isset( $_POST['offer'] ) ? wp_unslash( $_POST['offer'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( is_string( $input ) ) {
			$input = json_decode( $input, true );
		}
		if ( ! is_array( $input ) ) {
			$this->send_error( __( 'Invalid offer data.', 'meyvora-convert' ), 400 );
			return;
		}
		$flat = class_exists( 'CRO_Offer_Schema' ) ? CRO_Offer_Schema::sanitize_offer( $input ) : $this->flatten_offer( $input );
		$valid = class_exists( 'CRO_Offer_Schema' ) ? CRO_Offer_Schema::validate_offer( $flat ) : true;
		if ( is_wp_error( $valid ) ) {
			$this->send_error( __( 'Validation failed.', 'meyvora-convert' ), 400, CRO_Offer_Schema::errors_to_array( $valid ) );
			return;
		}
		$flat['id']         = $this->generate_id();
		$flat['updated_at'] = gmdate( 'c' );
		$offers[ $slot ]    = $flat;
		$this->save_offers_raw( $offers );
		$this->send_success( array( 'id' => $flat['id'], 'index' => $slot ) );
	}

	/**
	 * AJAX: Update offer. POST: id, offer (normalized object).
	 */
	public function handle_update() {
		if ( ! $this->auth() ) {
			return;
		}
		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		if ( $id === '' ) {
			$this->send_error( __( 'Offer id is required.', 'meyvora-convert' ), 400 );
			return;
		}
		$offers = $this->get_offers_raw();
		$index  = $this->find_index_by_id( $offers, $id );
		if ( $index === false ) {
			$this->send_error( __( 'Offer not found.', 'meyvora-convert' ), 404 );
			return;
		}
		$input = isset( $_POST['offer'] ) ? wp_unslash( $_POST['offer'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( is_string( $input ) ) {
			$input = json_decode( $input, true );
		}
		if ( ! is_array( $input ) ) {
			$this->send_error( __( 'Invalid offer data.', 'meyvora-convert' ), 400 );
			return;
		}
		$flat = class_exists( 'CRO_Offer_Schema' ) ? CRO_Offer_Schema::sanitize_offer( $input ) : $this->flatten_offer( $input, $id, gmdate( 'c' ) );
		$valid = class_exists( 'CRO_Offer_Schema' ) ? CRO_Offer_Schema::validate_offer( $flat ) : true;
		if ( is_wp_error( $valid ) ) {
			$this->send_error( __( 'Validation failed.', 'meyvora-convert' ), 400, CRO_Offer_Schema::errors_to_array( $valid ) );
			return;
		}
		$time            = gmdate( 'c' );
		$flat['id']      = $id;
		$flat['updated_at'] = $time;
		$offers[ $index ] = $flat;
		$this->save_offers_raw( $offers );
		$this->send_success( array( 'index' => $index ) );
	}

	/**
	 * AJAX: Delete offer. POST: id.
	 */
	public function handle_delete() {
		if ( ! $this->auth() ) {
			return;
		}
		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		if ( $id === '' ) {
			$this->send_error( __( 'Offer id is required.', 'meyvora-convert' ), 400 );
			return;
		}
		$offers = $this->get_offers_raw();
		$index  = $this->find_index_by_id( $offers, $id );
		if ( $index === false ) {
			$this->send_error( __( 'Offer not found.', 'meyvora-convert' ), 404 );
			return;
		}
		$offers[ $index ] = array();
		$this->save_offers_raw( $offers );
		$data = array_merge( $this->get_offers_for_display(), array( 'index' => $index ) );
		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Duplicate offer. POST: id. Creates copy in first empty slot.
	 */
	public function handle_duplicate() {
		if ( ! $this->auth() ) {
			return;
		}
		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		if ( $id === '' ) {
			$this->send_error( __( 'Offer id is required.', 'meyvora-convert' ), 400 );
			return;
		}
		$offers = $this->get_offers_raw();
		$index  = $this->find_index_by_id( $offers, $id );
		if ( $index === false ) {
			$this->send_error( __( 'Offer not found.', 'meyvora-convert' ), 404 );
			return;
		}
		if ( $this->count_used( $offers ) >= self::MAX_OFFERS ) {
			$this->send_error( __( 'Offer limit reached (5).', 'meyvora-convert' ), 400 );
			return;
		}
		$slot = $this->find_first_empty_slot( $offers );
		if ( $slot === false ) {
			$this->send_error( __( 'Offer limit reached (5).', 'meyvora-convert' ), 400 );
			return;
		}
		$src  = $offers[ $index ];
		$copy = $src;
		$copy['headline'] = ( isset( $copy['headline'] ) ? $copy['headline'] : '' ) . ' (' . __( 'Copy', 'meyvora-convert' ) . ')';
		if ( class_exists( 'CRO_Offer_Schema' ) ) {
			$copy = CRO_Offer_Schema::sanitize_offer( $copy );
			$valid = CRO_Offer_Schema::validate_offer( $copy );
			if ( is_wp_error( $valid ) ) {
				$this->send_error( __( 'Validation failed.', 'meyvora-convert' ), 400, CRO_Offer_Schema::errors_to_array( $valid ) );
				return;
			}
		}
		$copy['id']         = $this->generate_id();
		$copy['updated_at'] = gmdate( 'c' );
		$offers[ $slot ]    = $copy;
		$this->save_offers_raw( $offers );
		$data = array_merge( $this->get_offers_for_display(), array( 'id' => $copy['id'], 'index' => $slot ) );
		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Toggle offer active. POST: id.
	 */
	public function handle_toggle_active() {
		if ( ! $this->auth() ) {
			return;
		}
		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		if ( $id === '' ) {
			$this->send_error( __( 'Offer id is required.', 'meyvora-convert' ), 400 );
			return;
		}
		$offers = $this->get_offers_raw();
		$index  = $this->find_index_by_id( $offers, $id );
		if ( $index === false ) {
			$this->send_error( __( 'Offer not found.', 'meyvora-convert' ), 404 );
			return;
		}
		$offers[ $index ]['enabled'] = empty( $offers[ $index ]['enabled'] );
		$offers[ $index ]['updated_at'] = gmdate( 'c' );
		$this->save_offers_raw( $offers );
		$this->send_success( array( 'index' => $index, 'enabled' => $offers[ $index ]['enabled'] ) );
	}

	/**
	 * AJAX: Reorder offers. POST: order[] = array of offer indices in new order (first = highest priority).
	 * Priorities normalized to 10, 20, 30, ...
	 */
	public function handle_reorder() {
		if ( ! $this->auth() ) {
			return;
		}
		$order = isset( $_POST['order'] ) ? wp_unslash( $_POST['order'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( is_string( $order ) ) {
			$order = json_decode( $order, true );
		}
		if ( ! is_array( $order ) ) {
			$this->send_error( __( 'Invalid order.', 'meyvora-convert' ), 400 );
			return;
		}
		$order = array_map( 'absint', $order );
		$order = array_values( array_filter( $order, function ( $i ) {
			return $i >= 0 && $i < self::MAX_OFFERS;
		} ) );
		$offers = $this->get_offers_raw();
		$used_indices = array();
		foreach ( $offers as $i => $o ) {
			$name = isset( $o['headline'] ) ? trim( (string) $o['headline'] ) : ( isset( $o['name'] ) ? trim( (string) $o['name'] ) : '' );
			if ( $name !== '' ) {
				$used_indices[] = $i;
			}
		}
		sort( $used_indices );
		$order_sorted = $order;
		sort( $order_sorted );
		if ( $order_sorted !== $used_indices ) {
			$this->send_error( __( 'Order must contain each offer index exactly once.', 'meyvora-convert' ), 400 );
			return;
		}
		$time = gmdate( 'c' );
		$priority = 10;
		foreach ( $order as $index ) {
			if ( isset( $offers[ $index ] ) && is_array( $offers[ $index ] ) && trim( (string) ( $offers[ $index ]['headline'] ?? $offers[ $index ]['name'] ?? '' ) ) !== '' ) {
				$offers[ $index ]['priority'] = $priority;
				$offers[ $index ]['updated_at'] = $time;
				$priority += 10;
			}
		}
		$this->save_offers_raw( $offers );
		$priorities = array();
		foreach ( $order as $pos => $index ) {
			$priorities[ $index ] = 10 * ( $pos + 1 );
		}
		$data = array_merge( $this->get_offers_for_display(), array( 'priorities' => $priorities ) );
		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Test offer match. POST: cart_total, cart_items_count, is_logged_in, order_count, lifetime_spend, user_role.
	 * Uses CRO_Offer_Engine::preview_offer for shared evaluation. Returns: match, checks, or suggestions when no match.
	 */
	public function handle_test() {
		if ( ! $this->auth() ) {
			return;
		}
		if ( ! class_exists( 'CRO_Offer_Engine' ) ) {
			wp_send_json_error( array( 'message' => __( 'Offer engine not available.', 'meyvora-convert' ) ), 400 );
			return;
		}
		$context = $this->get_test_context_from_request();
		$offers  = CRO_Offer_Engine::get_active_offers();

		$matched_offer   = null;
		$matched_payload = null;
		$matched_preview = null;

		foreach ( $offers as $offer ) {
			$preview = CRO_Offer_Engine::preview_offer( $offer, $context );
			if ( ! empty( $preview['passed'] ) ) {
				$matched_offer   = $offer;
				$matched_payload = CRO_Offer_Engine::offer_to_payload( $offer );
				$matched_preview = $preview;
				break;
			}
		}

		if ( ! $matched_offer ) {
			$suggestions = array();
			$first = reset( $offers );
			if ( $first ) {
				$conditions = CRO_Offer_Engine::get_conditions_from_offer( $first );
				foreach ( $conditions as $key => $value ) {
					if ( ! CRO_Offer_Engine::evaluate_condition( (string) $key, $value, $context ) ) {
						$sug = CRO_Offer_Engine::condition_suggestion( (string) $key, $value, $context );
						if ( $sug !== '' ) {
							$suggestions[] = $sug;
						}
					}
				}
			}
			wp_send_json_success( array(
				'match'       => null,
				'checks'      => array(),
				'message'     => __( 'No eligible offer', 'meyvora-convert' ),
				'suggestions' => $suggestions,
			) );
			return;
		}

		$flat = array(
			'headline' => $matched_payload['headline'],
			'min_cart_total' => null,
			'max_cart_total' => null,
			'min_items' => null,
			'first_time_customer' => null,
			'returning_customer_min_orders' => null,
			'lifetime_spend_min' => null,
			'allowed_roles' => array(),
			'excluded_roles' => array(),
			'reward_type' => isset( $matched_payload['reward']['type'] ) ? $matched_payload['reward']['type'] : 'percent',
			'reward_amount' => isset( $matched_payload['reward']['amount'] ) ? $matched_payload['reward']['amount'] : 0,
			'coupon_ttl_hours' => 48,
		);
		$conditions = CRO_Offer_Engine::get_conditions_from_offer( $matched_offer );
		foreach ( $conditions as $k => $v ) {
			if ( array_key_exists( $k, $flat ) ) {
				$flat[ $k ] = $v;
			}
		}
		$rule_summary   = class_exists( 'CRO_Offer_Presenter' ) ? CRO_Offer_Presenter::summarize_conditions( $flat ) : $matched_payload['headline'];
		$reward_summary = class_exists( 'CRO_Offer_Presenter' ) ? CRO_Offer_Presenter::summarize_reward( $flat ) : '';

		wp_send_json_success( array(
			'match'  => array(
				'id'             => isset( $matched_payload['id'] ) ? $matched_payload['id'] : '',
				'name'           => $matched_payload['headline'],
				'description'   => isset( $matched_payload['description'] ) ? $matched_payload['description'] : '',
				'priority'      => $matched_payload['priority'],
				'rule_summary'   => $rule_summary,
				'reward_summary' => $reward_summary,
			),
			'checks' => isset( $matched_preview['checks'] ) ? $matched_preview['checks'] : array(),
		) );
	}

	/**
	 * Build test context array from POST (cart_total, cart_items_count, is_logged_in, order_count, lifetime_spend, user_role).
	 *
	 * @return array
	 */
	private function get_test_context_from_request() {
		$cart_total   = isset( $_POST['cart_total'] ) && is_numeric( $_POST['cart_total'] ) ? (float) $_POST['cart_total'] : 0.0;
		$items_count  = isset( $_POST['cart_items_count'] ) && is_numeric( $_POST['cart_items_count'] ) ? (int) $_POST['cart_items_count'] : 0;
		$is_logged_in = ! empty( $_POST['is_logged_in'] );
		$order_count  = isset( $_POST['order_count'] ) && is_numeric( $_POST['order_count'] ) ? (int) $_POST['order_count'] : 0;
		$lifetime     = isset( $_POST['lifetime_spend'] ) && is_numeric( $_POST['lifetime_spend'] ) ? (float) $_POST['lifetime_spend'] : 0.0;
		$user_role    = isset( $_POST['user_role'] ) ? sanitize_text_field( wp_unslash( $_POST['user_role'] ) ) : '';
		if ( ! $is_logged_in ) {
			$user_role = '';
		}
		return array(
			'cart_total'       => $cart_total,
			'cart_items_count' => $items_count,
			'user_id'          => $is_logged_in ? 1 : 0,
			'is_logged_in'     => $is_logged_in,
			'user_role'        => $user_role,
			'order_count'      => $order_count,
			'lifetime_spend'   => $lifetime,
			'visitor_id'       => 'test',
		);
	}
}
