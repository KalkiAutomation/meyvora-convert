<?php
/**
 * Cart messages template
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
	return;
}

$cart = WC()->cart;
$cart_total = $cart->get_total( 'edit' );
$free_shipping_threshold = apply_filters( 'cro_free_shipping_threshold', 0 );

if ( $free_shipping_threshold <= 0 || $cart_total >= $free_shipping_threshold ) {
	return;
}

$remaining = $free_shipping_threshold - $cart_total;
?>

<div class="cro-cart-message cro-free-shipping">
	<p>
		<?php
		printf(
			/* translators: %s: remaining amount */
			esc_html__( 'Add %s more to get free shipping!', 'meyvora-convert' ),
			wp_kses_post( wc_price( $remaining ) )
		);
		?>
	</p>
</div>
