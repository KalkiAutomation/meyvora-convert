<?php
/**
 * Post-purchase upsell template.
 *
 * Variables (see MEYVC_Post_Purchase::render_upsell):
 *
 * @var int             $order_id
 * @var WC_Product[]    $products
 * @var string          $headline
 * @var string          $sub
 * @var float           $discount
 * @var string          $coupon_code
 * @var string          $nonce
 * @var string          $ajax_url
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template/view: variables are file-local.
?>
<div class="meyvc-post-purchase-upsell" data-order-id="<?php echo esc_attr( (string) $order_id ); ?>">
	<h3 class="meyvc-post-purchase-upsell__title"><?php echo esc_html( $headline ); ?></h3>
	<?php if ( $sub ) : ?>
		<p class="meyvc-post-purchase-upsell__sub"><?php echo esc_html( $sub ); ?></p>
	<?php endif; ?>
	<?php if ( $discount > 0 && $coupon_code ) : ?>
		<p class="meyvc-post-purchase-upsell__coupon">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: percent, 2: coupon code */
					__( 'Use code %2$s for %1$s%% off when you add to cart from here.', 'meyvora-convert' ),
					number_format_i18n( $discount ),
					$coupon_code
				)
			);
			?>
		</p>
	<?php endif; ?>
	<ul class="meyvc-post-purchase-upsell__list">
		<?php foreach ( $products as $p ) : ?>
			<?php if ( ! is_a( $p, 'WC_Product' ) ) { continue; } ?>
			<li class="meyvc-post-purchase-upsell__item">
				<?php
				$thumb = $p->get_image( 'thumbnail' );
				if ( $thumb ) {
					echo wp_kses_post( $thumb );
				}
				?>
				<span class="meyvc-post-purchase-upsell__name"><?php echo esc_html( $p->get_name() ); ?></span>
				<span class="meyvc-post-purchase-upsell__price"><?php echo wp_kses_post( $p->get_price_html() ); ?></span>
				<button type="button" class="button meyvc-post-purchase-add"
					data-product-id="<?php echo esc_attr( (string) $p->get_id() ); ?>"
					data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php esc_html_e( 'Add to cart', 'meyvora-convert' ); ?>
				</button>
			</li>
		<?php endforeach; ?>
	</ul>
	<p class="meyvc-post-purchase-upsell__no-thanks">
		<a href="#" class="meyvc-post-purchase-dismiss"><?php esc_html_e( 'No thanks', 'meyvora-convert' ); ?></a>
	</p>
</div>
