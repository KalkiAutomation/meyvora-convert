<?php
/**
 * Sticky cart template
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
$cart_count = $cart->get_cart_contents_count();
?>

<div class="cro-sticky-cart">
	<div class="cro-sticky-cart-content">
		<div class="cro-sticky-cart-info">
			<span class="cro-sticky-cart-count">
				<?php
				printf(
					/* translators: %d: cart item count */
					esc_html( _n( '%d item', '%d items', $cart_count, 'meyvora-convert' ) ),
					$cart_count
				);
				?>
			</span>
			<span class="cro-sticky-cart-total cro-cart-total">
				<?php echo wp_kses_post( wc_price( $cart_total ) ); ?>
			</span>
		</div>
		<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="cro-sticky-cart-button">
			<?php esc_html_e( 'View Cart', 'meyvora-convert' ); ?>
		</a>
	</div>
</div>
