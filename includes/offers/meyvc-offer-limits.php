<?php
/**
 * Dynamic offer slot limits (filterable).
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maximum number of dynamic offer slots stored in `meyvc_dynamic_offers`.
 *
 * Default 50. Use the `meyvc_max_dynamic_offers` filter to change (clamped between 1 and 200).
 *
 * @return int
 */
function meyvc_get_max_dynamic_offers() {
	$max = (int) apply_filters( 'meyvc_max_dynamic_offers', 50 );
	if ( $max < 1 ) {
		$max = 1;
	} elseif ( $max > 200 ) {
		$max = 200;
	}
	return $max;
}
