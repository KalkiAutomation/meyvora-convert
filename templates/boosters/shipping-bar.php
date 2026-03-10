<?php
/**
 * Shipping bar template
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
$percentage = ( $cart_total / $free_shipping_threshold ) * 100;
?>

<div class="cro-shipping-bar">
	<p>
		<?php
		if ( $remaining > 0 ) {
			printf(
				/* translators: %s: remaining amount */
				esc_html__( 'Add %s more to get free shipping!', 'meyvora-convert' ),
				wp_kses_post( wc_price( $remaining ) )
			);
		}
		?>
	</p>
	<div class="cro-shipping-bar-progress">
		<div class="cro-progress-bar" style="width: <?php echo esc_attr( $percentage ); ?>%;"></div>
	</div>
</div>
