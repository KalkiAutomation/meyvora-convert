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
	 * @return array{ passed: bool, details: array, failed_conditions: string[] } Result with 'passed', 'details', and 'failed_conditions'.
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
			$row_passed = ! $result['passed'];
			$msg        = $result['message'] ?? '';
			// Inner rule matched => visitor hit a forbidden condition.
			if ( $result['passed'] ) {
				$msg = ( $msg !== '' ? $msg : self::format_rule_stub( array( 'group' => 'must_not', 'rule' => $rule ) ) );
				$msg = 'must_not (forbidden matched): ' . $msg;
			}
			$details[] = array(
				'group'   => 'must_not',
				'rule'    => $rule,
				'passed'  => $row_passed,
				'message' => $msg,
			);
			if ( $result['passed'] ) {
				$passed = false;
			}
		}

		return array(
			'passed'  => $passed,
			'details' => $details,
			'failed_conditions' => self::failed_conditions_list( $details ),
		);
	}

	/**
	 * Human-readable list of rule rows that failed (must / should / must_not).
	 *
	 * @param array $details Rows from evaluate(): group, rule, passed, message.
	 * @return string[]
	 */
	public static function failed_conditions_list( array $details ) {
		$out = array();
		foreach ( $details as $d ) {
			if ( ! is_array( $d ) || ! empty( $d['passed'] ) ) {
				continue;
			}
			$msg = isset( $d['message'] ) && $d['message'] !== '' ? $d['message'] : self::format_rule_stub( $d );
			$prefix = isset( $d['group'] ) ? '[' . $d['group'] . '] ' : '';
			$out[]  = $prefix . $msg;
		}
		return $out;
	}

	/**
	 * Fallback label when a rule row has no message.
	 *
	 * @param array $d Detail row.
	 * @return string
	 */
	private static function format_rule_stub( array $d ) {
		$g = isset( $d['group'] ) ? $d['group'] : 'rule';
		$r = isset( $d['rule'] ) && is_array( $d['rule'] ) ? $d['rule'] : array();
		if ( isset( $r['type'] ) ) {
			return sprintf( '%s:%s', $g, $r['type'] );
		}
		if ( isset( $r['field'] ) ) {
			$val = $r['value'] ?? null;
			$val_s = is_scalar( $val ) || $val === null ? (string) wp_json_encode( $val ) : wp_json_encode( $val );
			return sprintf( '%s %s %s', $r['field'], $r['operator'] ?? '=', $val_s );
		}
		return $g . ': failed';
	}

	/**
	 * Result for special rules with optional failure explanation.
	 *
	 * @param bool   $passed   Whether the rule passed.
	 * @param string $fail_msg Message when failed.
	 * @return array{ passed: bool, message: string }
	 */
	private function special_rule_result( $passed, $fail_msg ) {
		return array(
			'passed'  => (bool) $passed,
			'message' => $passed ? '' : (string) $fail_msg,
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
		$message = '';
		if ( ! $passed && is_object( $context ) && method_exists( $context, 'get' ) ) {
			$actual = $context->get( $field, null );
			$message = sprintf(
				'%s %s %s — actual: %s',
				$field,
				$operator,
				wp_json_encode( $value ),
				wp_json_encode( $actual )
			);
		}
		return array( 'passed' => $passed, 'message' => $message );
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
				return $this->special_rule_result( $passed, sprintf( 'page_type_in: page_type is "%s", need one of %s', $page, wp_json_encode( $arr ) ) );

			case 'page_type_not_in':
				$page = call_user_func( $get, 'page_type', '' );
				$arr = is_array( $value ) ? $value : array( $value );
				$passed = ! in_array( $page, $arr, true ) && ! in_array( (string) $page, array_map( 'strval', $arr ), true );
				return $this->special_rule_result( $passed, sprintf( 'page_type_not_in: page_type is "%s", must not be one of %s', $page, wp_json_encode( $arr ) ) );

			case 'device_type_in':
				$device = call_user_func( $get, 'device_type', '' );
				$arr = is_array( $value ) ? $value : array( $value );
				$passed = in_array( $device, $arr, true ) || in_array( (string) $device, array_map( 'strval', $arr ), true );
				return $this->special_rule_result( $passed, sprintf( 'device_type_in: device is "%s", need one of %s', $device, wp_json_encode( $arr ) ) );

			case 'device_type_not_in':
				$device = call_user_func( $get, 'device_type', '' );
				$arr = is_array( $value ) ? $value : array( $value );
				$passed = ! in_array( $device, $arr, true ) && ! in_array( (string) $device, array_map( 'strval', $arr ), true );
				return $this->special_rule_result( $passed, sprintf( 'device_type_not_in: device is "%s", must not be one of %s', $device, wp_json_encode( $arr ) ) );

			case 'cart_has_items':
				$has = call_user_func( $get, 'cart.has_items', false );
				$passed = (bool) $has;
				return $this->special_rule_result( $passed, 'cart_has_items: cart is empty' );

			case 'cart_empty':
				$has = call_user_func( $get, 'cart.has_items', false );
				$passed = ! (bool) $has;
				return $this->special_rule_result( $passed, 'cart_empty: cart has items' );

			case 'cart_total_gte':
				$total = (float) call_user_func( $get, 'cart.total', 0 );
				$threshold = is_numeric( $value ) ? (float) $value : 0;
				$passed = $total >= $threshold;
				return $this->special_rule_result( $passed, sprintf( 'cart_total_gte: total %s < %s', $total, $threshold ) );

			case 'cart_total_lte':
				$total = (float) call_user_func( $get, 'cart.total', 0 );
				$threshold = is_numeric( $value ) ? (float) $value : 0;
				$passed = $total <= $threshold;
				return $this->special_rule_result( $passed, sprintf( 'cart_total_lte: total %s > %s', $total, $threshold ) );

			case 'cart_total_between':
				$total = (float) call_user_func( $get, 'cart.total', 0 );
				$min = isset( $value['min'] ) ? (float) $value['min'] : 0;
				$max = isset( $value['max'] ) ? (float) $value['max'] : PHP_FLOAT_MAX;
				$passed = $total >= $min && $total <= $max;
				return $this->special_rule_result( $passed, sprintf( 'cart_total_between: total %s not in [%s,%s]', $total, $min, $max ) );

			case 'cart_has_category':
				$cats = call_user_func( $get, 'cart.categories', array() );
				$cats = is_array( $cats ) ? $cats : array();
				$ids = is_array( $value ) ? $value : array( $value );
				$passed = count( array_intersect( array_map( 'intval', $cats ), array_map( 'intval', $ids ) ) ) > 0;
				return $this->special_rule_result( $passed, sprintf( 'cart_has_category: cart has no category in %s', wp_json_encode( array_map( 'intval', $ids ) ) ) );

			case 'cart_has_product':
				$ids = is_array( $value ) ? $value : array( $value );
				$ids = array_map( 'intval', $ids );
				$cart_ids = array_map( 'intval', (array) call_user_func( $get, 'cart.product_ids', array() ) );
				$passed = count( array_intersect( $ids, $cart_ids ) ) > 0;
				return $this->special_rule_result( $passed, sprintf( 'cart_has_product: cart product ids %s missing %s', wp_json_encode( $cart_ids ), wp_json_encode( $ids ) ) );

			case 'cart_has_product_not':
				$ids = is_array( $value ) ? $value : array( $value );
				$ids = array_map( 'intval', $ids );
				$cart_ids = array_map( 'intval', (array) call_user_func( $get, 'cart.product_ids', array() ) );
				$passed = count( array_intersect( $ids, $cart_ids ) ) === 0;
				return $this->special_rule_result( $passed, sprintf( 'cart_has_product_not: cart contains excluded product id(s) %s', wp_json_encode( array_intersect( $ids, $cart_ids ) ) ) );

			case 'cart_has_category_not':
				$cats = call_user_func( $get, 'cart.categories', array() );
				$cats = is_array( $cats ) ? array_map( 'intval', $cats ) : array();
				$exclude_ids = is_array( $value ) ? array_map( 'intval', $value ) : array( (int) $value );
				$passed = count( array_intersect( $cats, $exclude_ids ) ) === 0;
				return $this->special_rule_result( $passed, sprintf( 'cart_has_category_not: cart has excluded category id(s) %s', wp_json_encode( array_intersect( $cats, $exclude_ids ) ) ) );

			case 'visitor_new':
				$vt = call_user_func( $get, 'request.visitor_type', 'new' );
				$passed = $vt === 'new';
				return $this->special_rule_result( $passed, sprintf( 'visitor_new: visitor_type is "%s"', $vt ) );

			case 'visitor_returning':
				$vt = call_user_func( $get, 'request.visitor_type', 'new' );
				$passed = $vt === 'returning';
				return $this->special_rule_result( $passed, sprintf( 'visitor_returning: visitor_type is "%s"', $vt ) );

			case 'utm_param_equals':
				$param = isset( $value['param'] ) ? sanitize_key( $value['param'] ) : ( is_string( $value ) ? 'utm_source' : '' );
				$expected = isset( $value['value'] ) ? $value['value'] : ( is_array( $value ) ? '' : $value );
				$utm = call_user_func( $get, 'request.utm', array() );
				$utm = is_array( $utm ) ? $utm : array();
				$actual = isset( $utm[ $param ] ) ? (string) $utm[ $param ] : '';
				$expected = is_string( $expected ) ? $expected : (string) $expected;
				$operator = isset( $value['operator'] ) ? sanitize_key( (string) $value['operator'] ) : 'equals';
				switch ( $operator ) {
					case 'not_equals':
						$passed = ( $actual !== $expected );
						break;
					case 'contains':
						$passed = $expected !== '' && strpos( strtolower( $actual ), strtolower( $expected ) ) !== false;
						break;
					case 'not_contains':
						$passed = $expected === '' || strpos( strtolower( $actual ), strtolower( $expected ) ) === false;
						break;
					case 'is_empty':
						$passed = ( $actual === '' );
						break;
					case 'is_not_empty':
						$passed = ( $actual !== '' );
						break;
					default:
						$passed = $actual !== '' && $actual === $expected;
						break;
				}
				return $this->special_rule_result( $passed, sprintf( 'utm_param_equals: %s is "%s", need "%s"', $param, $actual, $expected ) );

			case 'utm_param_exists':
				$param = isset( $value['param'] ) ? sanitize_key( $value['param'] ) : ( is_string( $value ) ? 'utm_source' : '' );
				$utm = call_user_func( $get, 'request.utm', array() );
				$utm = is_array( $utm ) ? $utm : array();
				$actual = isset( $utm[ $param ] ) ? (string) $utm[ $param ] : '';
				$passed = $actual !== '';
				return $this->special_rule_result( $passed, sprintf( 'utm_param_exists: %s is empty', $param ) );

			case 'user_logged_in':
				$logged = call_user_func( $get, 'user.logged_in', false );
				$wanted = $value !== false && $value !== null && $value !== '';
				$passed = (bool) $logged === (bool) $wanted;
				return $this->special_rule_result( $passed, sprintf( 'user_logged_in: logged_in=%s, need %s', $logged ? 'true' : 'false', $wanted ? 'true' : 'false' ) );

			case 'user_role_in':
				$role = call_user_func( $get, 'user.role', '' );
				$arr = is_array( $value ) ? $value : array( $value );
				$passed = in_array( (string) $role, array_map( 'strval', $arr ), true );
				return $this->special_rule_result( $passed, sprintf( 'user_role_in: role "%s" not in %s', $role, wp_json_encode( $arr ) ) );

			case 'user_is_admin':
				$is_admin = call_user_func( $get, 'user.is_admin', false );
				$passed = (bool) $is_admin === (bool) $value;
				return $this->special_rule_result( $passed, sprintf( 'user_is_admin: is_admin=%s, need %s', $is_admin ? 'true' : 'false', $value ? 'true' : 'false' ) );

			case 'time_on_page_gte':
				$sec = (int) call_user_func( $get, 'behavior.time_on_page', 0 );
				$threshold = is_numeric( $value ) ? (int) $value : 0;
				$passed = $sec >= $threshold;
				return $this->special_rule_result( $passed, sprintf( 'time_on_page_gte: %ds on page < %ds', $sec, $threshold ) );

			case 'scroll_depth_gte':
				$pct = (int) call_user_func( $get, 'behavior.scroll_depth', 0 );
				$threshold = is_numeric( $value ) ? (int) $value : 0;
				$passed = $pct >= $threshold;
				return $this->special_rule_result( $passed, sprintf( 'scroll_depth_gte: %d%% < %d%%', $pct, $threshold ) );

			case 'has_interacted':
				$interacted = call_user_func( $get, 'behavior.has_interacted', false );
				$passed = (bool) $interacted === (bool) $value;
				return $this->special_rule_result( $passed, sprintf( 'has_interacted: %s, need %s', $interacted ? 'true' : 'false', $value ? 'true' : 'false' ) );

			case 'cart_item_count_gte':
				$count = (int) call_user_func( $get, 'cart.item_count', 0 );
				$need = (int) $value;
				$passed = $count >= $need;
				return $this->special_rule_result( $passed, sprintf( 'cart_item_count_gte: count %d < %d', $count, $need ) );

			case 'cart_item_count_lte':
				$count = (int) call_user_func( $get, 'cart.item_count', 0 );
				$need = (int) $value;
				$passed = $count <= $need;
				return $this->special_rule_result( $passed, sprintf( 'cart_item_count_lte: count %d > %d', $count, $need ) );

			case 'cart_has_sale_items':
				$sale = (int) call_user_func( $get, 'cart.sale_item_count', 0 );
				$passed = $sale > 0;
				return $this->special_rule_result( $passed, 'cart_has_sale_items: no sale items in cart' );

			case 'pages_viewed_gte':
				$pv = (int) call_user_func( $get, 'visitor.pages_viewed', 0 );
				$threshold = is_numeric( $value ) ? (int) $value : 0;
				$passed = $pv >= $threshold;
				return $this->special_rule_result( $passed, sprintf( 'pages_viewed_gte: %d pages < %d', $pv, $threshold ) );

			case 'session_count_gte':
				$sessions = (int) call_user_func( $get, 'request.session_count', 0 );
				$need = (int) $value;
				$passed = $sessions >= $need;
				return $this->special_rule_result( $passed, sprintf( 'session_count_gte: %d sessions < %d', $sessions, $need ) );

			case 'session_count_lte':
				$sessions = (int) call_user_func( $get, 'request.session_count', 0 );
				$max = (int) $value;
				$passed = $max > 0 && $sessions <= $max;
				return $this->special_rule_result( $passed, sprintf( 'session_count_lte: %d sessions > %d', $sessions, $max ) );

			case 'geo_country_in':
				$country = strtoupper( (string) call_user_func( $get, 'request.geo_country', '' ) );
				$arr = is_array( $value ) ? $value : array( $value );
				$arr = array_map(
					static function ( $c ) {
						return strtoupper( substr( sanitize_text_field( (string) $c ), 0, 2 ) );
					},
					$arr
				);
				$arr = array_values( array_filter( $arr, static function ( $c ) {
					return strlen( $c ) === 2;
				} ) );
				$passed = $country !== '' && in_array( $country, $arr, true );
				return $this->special_rule_result( $passed, sprintf( 'geo_country_in: country "%s", need one of %s', $country, wp_json_encode( $arr ) ) );

			case 'geo_country_not_in':
				$country = strtoupper( (string) call_user_func( $get, 'request.geo_country', '' ) );
				$arr = is_array( $value ) ? $value : array( $value );
				$arr = array_map(
					static function ( $c ) {
						return strtoupper( substr( sanitize_text_field( (string) $c ), 0, 2 ) );
					},
					$arr
				);
				$arr = array_values( array_filter( $arr, static function ( $c ) {
					return strlen( $c ) === 2;
				} ) );
				$passed = $country === '' || ! in_array( $country, $arr, true );
				return $this->special_rule_result( $passed, sprintf( 'geo_country_not_in: country "%s" is excluded %s', $country, wp_json_encode( $arr ) ) );

			case 'context_string_rule':
				$path     = isset( $value['path'] ) ? (string) $value['path'] : '';
				$operator = isset( $value['operator'] ) ? (string) $value['operator'] : 'equals';
				$expected = isset( $value['value'] ) ? ( is_string( $value['value'] ) ? $value['value'] : (string) $value['value'] ) : '';
				if ( $path === '' ) {
					return array( 'passed' => false, 'message' => 'context_string_rule: missing path' );
				}
				$actual_string = (string) call_user_func( $get, $path, '' );
				$passed        = $this->match_context_string( $actual_string, $operator, $expected );
				return $this->special_rule_result(
					$passed,
					sprintf( 'context_string_rule: %s %s %s — actual: %s', $path, $operator, $expected, $actual_string )
				);

			default:
				return array( 'passed' => false, 'message' => 'unknown_type_' . $type );
		}
	}

	/**
	 * String comparison for context_string_rule (UTM / referrer targeting).
	 *
	 * @param string $actual   Value from context.
	 * @param string $operator equals|not_equals|contains|not_contains|starts_with|is_empty|is_not_empty.
	 * @param string $expected Operand.
	 * @return bool
	 */
	private function match_context_string( $actual, $operator, $expected ) {
		$actual   = strtolower( trim( (string) $actual ) );
		$expected = strtolower( trim( (string) $expected ) );
		switch ( $operator ) {
			case 'equals':
				return $actual === $expected;
			case 'not_equals':
				return $actual !== $expected;
			case 'contains':
				return $expected !== '' && strpos( $actual, $expected ) !== false;
			case 'not_contains':
				return $expected === '' || strpos( $actual, $expected ) === false;
			case 'starts_with':
				return $expected !== '' && strpos( $actual, $expected ) === 0;
			case 'is_empty':
				return $actual === '';
			case 'is_not_empty':
				return $actual !== '';
			default:
				return false;
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
