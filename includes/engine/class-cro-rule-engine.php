<?php
/**
 * Rule engine
 *
 * Evaluates rules against CRO_Context. Supports must (AND), should (OR), must_not (NOT)
 * groups, field/operator/value rules, and type-based special rules.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CRO_Rule_Engine class.
 *
 * Evaluates targeting and display rules for campaigns against context.
 */
class CRO_Rule_Engine {

	/**
	 * Evaluate a rules array against context.
	 *
	 * Rule groups: must (all must pass), should (at least one must pass), must_not (none must pass).
	 *
	 * @param array       $rules   Rules array with optional keys 'must', 'should', 'must_not'. Each is an array of rules.
	 * @param CRO_Context $context Context instance.
	 * @return array{ passed: bool, details: array } Result with 'passed' and 'details' (per-rule results).
	 */
	public function evaluate( $rules, $context ) {
		$rules   = is_array( $rules ) ? $rules : array();
		$details = array();
		$passed  = true;

		$must     = isset( $rules['must'] ) && is_array( $rules['must'] ) ? $rules['must'] : array();
		$should   = isset( $rules['should'] ) && is_array( $rules['should'] ) ? $rules['should'] : array();
		$must_not = isset( $rules['must_not'] ) && is_array( $rules['must_not'] ) ? $rules['must_not'] : array();

		foreach ( $must as $rule ) {
			$result = $this->evaluate_rule( $rule, $context );
			$details[] = array(
				'group'  => 'must',
				'rule'   => $rule,
				'passed' => $result['passed'],
				'message' => $result['message'] ?? '',
			);
			if ( ! $result['passed'] ) {
				$passed = false;
			}
		}

		$should_passed = empty( $should );
		foreach ( $should as $rule ) {
			$result = $this->evaluate_rule( $rule, $context );
			$details[] = array(
				'group'  => 'should',
				'rule'   => $rule,
				'passed' => $result['passed'],
				'message' => $result['message'] ?? '',
			);
			if ( $result['passed'] ) {
				$should_passed = true;
			}
		}
		if ( ! $should_passed ) {
			$passed = false;
		}

		foreach ( $must_not as $rule ) {
			$result = $this->evaluate_rule( $rule, $context );
			$details[] = array(
				'group'  => 'must_not',
				'rule'   => $rule,
				'passed' => ! $result['passed'],
				'message' => $result['message'] ?? '',
			);
			if ( $result['passed'] ) {
				$passed = false;
			}
		}

		return array(
			'passed'  => $passed,
			'details' => $details,
		);
	}

	/**
	 * Evaluate a single rule (field/operator/value or type-based).
	 *
	 * @param array       $rule    Rule: { field, operator, value } or { type, value }.
	 * @param CRO_Context $context Context instance.
	 * @return array{ passed: bool, message?: string }
	 */
	public function evaluate_rule( $rule, $context ) {
		if ( ! is_array( $rule ) ) {
			return array( 'passed' => false, 'message' => 'invalid_rule' );
		}
		if ( isset( $rule['type'] ) ) {
			return $this->evaluate_special_rule( $rule, $context );
		}
		$field    = isset( $rule['field'] ) ? $rule['field'] : '';
		$operator = isset( $rule['operator'] ) ? $rule['operator'] : '=';
		$value    = isset( $rule['value'] ) ? $rule['value'] : null;
		if ( $field === '' && $operator === '=' && $value === null ) {
			return array( 'passed' => false, 'message' => 'missing_field' );
		}
		$passed = false;
		if ( is_object( $context ) && method_exists( $context, 'matches' ) ) {
			$passed = $context->matches( $field, $operator, $value );
		}
		return array( 'passed' => $passed );
	}

	/**
	 * Evaluate a type-based special rule.
	 *
	 * @param array       $rule    Rule with 'type' and 'value'.
	 * @param CRO_Context $context Context instance.
	 * @return array{ passed: bool, message?: string }
	 */
	public function evaluate_special_rule( $rule, $context ) {
		$type  = isset( $rule['type'] ) ? (string) $rule['type'] : '';
		$value = isset( $rule['value'] ) ? $rule['value'] : null;

		if ( ! is_object( $context ) || ! method_exists( $context, 'get' ) ) {
			return array( 'passed' => false, 'message' => 'missing_context' );
		}

		$get = array( $context, 'get' );

		switch ( $type ) {
			case 'page_type_in':
				$page = call_user_func( $get, 'page_type', '' );
				$arr = is_array( $value ) ? $value : array( $value );
				$passed = in_array( $page, $arr, true ) || in_array( (string) $page, array_map( 'strval', $arr ), true );
				return array( 'passed' => $passed );

			case 'page_type_not_in':
				$page = call_user_func( $get, 'page_type', '' );
				$arr = is_array( $value ) ? $value : array( $value );
				$passed = ! in_array( $page, $arr, true ) && ! in_array( (string) $page, array_map( 'strval', $arr ), true );
				return array( 'passed' => $passed );

			case 'device_type_in':
				$device = call_user_func( $get, 'device_type', '' );
				$arr = is_array( $value ) ? $value : array( $value );
				$passed = in_array( $device, $arr, true ) || in_array( (string) $device, array_map( 'strval', $arr ), true );
				return array( 'passed' => $passed );

			case 'device_type_not_in':
				$device = call_user_func( $get, 'device_type', '' );
				$arr = is_array( $value ) ? $value : array( $value );
				$passed = ! in_array( $device, $arr, true ) && ! in_array( (string) $device, array_map( 'strval', $arr ), true );
				return array( 'passed' => $passed );

			case 'cart_has_items':
				$has = call_user_func( $get, 'cart.has_items', false );
				$passed = (bool) $has;
				return array( 'passed' => $passed );

			case 'cart_empty':
				$has = call_user_func( $get, 'cart.has_items', false );
				$passed = ! (bool) $has;
				return array( 'passed' => $passed );

			case 'cart_total_gte':
				$total = (float) call_user_func( $get, 'cart.total', 0 );
				$threshold = is_numeric( $value ) ? (float) $value : 0;
				return array( 'passed' => $total >= $threshold );

			case 'cart_total_lte':
				$total = (float) call_user_func( $get, 'cart.total', 0 );
				$threshold = is_numeric( $value ) ? (float) $value : 0;
				return array( 'passed' => $total <= $threshold );

			case 'cart_total_between':
				$total = (float) call_user_func( $get, 'cart.total', 0 );
				$min = isset( $value['min'] ) ? (float) $value['min'] : 0;
				$max = isset( $value['max'] ) ? (float) $value['max'] : PHP_FLOAT_MAX;
				return array( 'passed' => $total >= $min && $total <= $max );

			case 'cart_has_category':
				$cats = call_user_func( $get, 'cart.categories', array() );
				$cats = is_array( $cats ) ? $cats : array();
				$ids = is_array( $value ) ? $value : array( $value );
				$passed = count( array_intersect( array_map( 'intval', $cats ), array_map( 'intval', $ids ) ) ) > 0;
				return array( 'passed' => $passed );

			case 'cart_has_product':
				$ids = is_array( $value ) ? $value : array( $value );
				$ids = array_map( 'intval', $ids );
				$cart_ids = array_map( 'intval', (array) call_user_func( $get, 'cart.product_ids', array() ) );
				$passed = count( array_intersect( $ids, $cart_ids ) ) > 0;
				return array( 'passed' => $passed );

			case 'cart_has_product_not':
				$ids = is_array( $value ) ? $value : array( $value );
				$ids = array_map( 'intval', $ids );
				$cart_ids = array_map( 'intval', (array) call_user_func( $get, 'cart.product_ids', array() ) );
				$passed = count( array_intersect( $ids, $cart_ids ) ) === 0;
				return array( 'passed' => $passed );

			case 'cart_has_category_not':
				$cats = call_user_func( $get, 'cart.categories', array() );
				$cats = is_array( $cats ) ? array_map( 'intval', $cats ) : array();
				$exclude_ids = is_array( $value ) ? array_map( 'intval', $value ) : array( (int) $value );
				$passed = count( array_intersect( $cats, $exclude_ids ) ) === 0;
				return array( 'passed' => $passed );

			case 'visitor_new':
				$vt = call_user_func( $get, 'request.visitor_type', 'new' );
				return array( 'passed' => $vt === 'new' );

			case 'visitor_returning':
				$vt = call_user_func( $get, 'request.visitor_type', 'new' );
				return array( 'passed' => $vt === 'returning' );

			case 'utm_param_equals':
				$param = isset( $value['param'] ) ? sanitize_key( $value['param'] ) : ( is_string( $value ) ? 'utm_source' : '' );
				$expected = isset( $value['value'] ) ? $value['value'] : ( is_array( $value ) ? '' : $value );
				$utm = call_user_func( $get, 'request.utm', array() );
				$utm = is_array( $utm ) ? $utm : array();
				$actual = isset( $utm[ $param ] ) ? (string) $utm[ $param ] : '';
				$expected = is_string( $expected ) ? $expected : (string) $expected;
				return array( 'passed' => $actual !== '' && $actual === $expected );

			case 'utm_param_exists':
				$param = isset( $value['param'] ) ? sanitize_key( $value['param'] ) : ( is_string( $value ) ? 'utm_source' : '' );
				$utm = call_user_func( $get, 'request.utm', array() );
				$utm = is_array( $utm ) ? $utm : array();
				$actual = isset( $utm[ $param ] ) ? (string) $utm[ $param ] : '';
				return array( 'passed' => $actual !== '' );

			case 'user_logged_in':
				$logged = call_user_func( $get, 'user.logged_in', false );
				$wanted = $value !== false && $value !== null && $value !== '';
				return array( 'passed' => (bool) $logged === (bool) $wanted );

			case 'user_role_in':
				$role = call_user_func( $get, 'user.role', '' );
				$arr = is_array( $value ) ? $value : array( $value );
				$passed = in_array( (string) $role, array_map( 'strval', $arr ), true );
				return array( 'passed' => $passed );

			case 'user_is_admin':
				$is_admin = call_user_func( $get, 'user.is_admin', false );
				return array( 'passed' => (bool) $is_admin === (bool) $value );

			case 'time_on_page_gte':
				$sec = (int) call_user_func( $get, 'behavior.time_on_page', 0 );
				$threshold = is_numeric( $value ) ? (int) $value : 0;
				return array( 'passed' => $sec >= $threshold );

			case 'scroll_depth_gte':
				$pct = (int) call_user_func( $get, 'behavior.scroll_depth', 0 );
				$threshold = is_numeric( $value ) ? (int) $value : 0;
				return array( 'passed' => $pct >= $threshold );

			case 'has_interacted':
				$interacted = call_user_func( $get, 'behavior.has_interacted', false );
				return array( 'passed' => (bool) $interacted === (bool) $value );

			default:
				return array( 'passed' => false, 'message' => 'unknown_type_' . $type );
		}
	}

	/**
	 * Create a standard rule (field / operator / value).
	 *
	 * @param string $field    Dot path (e.g. 'cart.total', 'page_type').
	 * @param string $operator Operator: =, !=, >, <, >=, <=, in, not_in, contains, exists, regex.
	 * @param mixed  $value    Compare value.
	 * @return array
	 */
	public static function rule( $field, $operator, $value ) {
		return array(
			'field'    => $field,
			'operator' => $operator,
			'value'    => $value,
		);
	}

	/**
	 * Create a type-based special rule.
	 *
	 * @param string     $type  Type (e.g. page_type_in, device_type_in, cart_has_items).
	 * @param mixed|null $value Value for the type.
	 * @return array
	 */
	public static function type_rule( $type, $value = null ) {
		return array(
			'type'  => $type,
			'value' => $value,
		);
	}
}
