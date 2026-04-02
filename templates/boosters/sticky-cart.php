<?php
/**
 * Sticky cart template
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
	return;
}

$cart = WC()->cart;
$cart_total = $cart->get_total( 'edit' );
$cart_count = $cart->get_cart_contents_count();
?>

<div class="meyvc-sticky-cart">
	<div class="meyvc-sticky-cart-content">
		<div class="meyvc-sticky-cart-info">
			<span class="meyvc-sticky-cart-count">
				<?php
				printf(
					/* translators: %d: cart item count */
					esc_html( _n( '%d item', '%d items', $cart_count, 'meyvora-convert' ) ),
					esc_html( number_format_i18n( (int) $cart_count ) )
				);
				?>
			</span>
			<span class="meyvc-sticky-cart-total meyvc-cart-total">
				<?php echo wp_kses_post( wc_price( $cart_total ) ); ?>
			</span>
		</div>
		<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="meyvc-sticky-cart-button">
			<?php esc_html_e( 'View Cart', 'meyvora-convert' ); ?>
		</a>
	</div>
</div>
