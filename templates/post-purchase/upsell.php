<?php
/**
 * Post-purchase upsell template.
 *
 * Variables (see CRO_Post_Purchase::render_upsell):
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
?>
<div class="cro-post-purchase-upsell" data-order-id="<?php echo esc_attr( (string) $order_id ); ?>">
	<h3 class="cro-post-purchase-upsell__title"><?php echo esc_html( $headline ); ?></h3>
	<?php if ( $sub ) : ?>
		<p class="cro-post-purchase-upsell__sub"><?php echo esc_html( $sub ); ?></p>
	<?php endif; ?>
	<?php if ( $discount > 0 && $coupon_code ) : ?>
		<p class="cro-post-purchase-upsell__coupon">
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
	<ul class="cro-post-purchase-upsell__list">
		<?php foreach ( $products as $p ) : ?>
			<?php if ( ! is_a( $p, 'WC_Product' ) ) { continue; } ?>
			<li class="cro-post-purchase-upsell__item">
				<?php
				$thumb = $p->get_image( 'thumbnail' );
				if ( $thumb ) {
					echo wp_kses_post( $thumb );
				}
				?>
				<span class="cro-post-purchase-upsell__name"><?php echo esc_html( $p->get_name() ); ?></span>
				<span class="cro-post-purchase-upsell__price"><?php echo wp_kses_post( $p->get_price_html() ); ?></span>
				<button type="button" class="button cro-post-purchase-add"
					data-product-id="<?php echo esc_attr( (string) $p->get_id() ); ?>"
					data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php esc_html_e( 'Add to cart', 'meyvora-convert' ); ?>
				</button>
			</li>
		<?php endforeach; ?>
	</ul>
	<p class="cro-post-purchase-upsell__no-thanks">
		<a href="#" class="cro-post-purchase-dismiss"><?php esc_html_e( 'No thanks', 'meyvora-convert' ); ?></a>
	</p>
</div>
<script>
jQuery(function($) {
	var ajaxUrl = <?php echo wp_json_encode( esc_url_raw( $ajax_url ) ); ?>;
	$('.cro-post-purchase-add').on('click', function() {
		var $btn = $(this);
		$btn.prop('disabled', true).text(<?php echo wp_json_encode( __( 'Adding…', 'meyvora-convert' ) ); ?>);
		$.post(ajaxUrl, {
			action:     'cro_post_purchase_add',
			nonce:      $btn.data('nonce'),
			product_id: $btn.data('product-id')
		}).done(function(r) {
			if (r && r.success) {
				$btn.text(<?php echo wp_json_encode( __( 'Added!', 'meyvora-convert' ) ); ?>);
			} else {
				$btn.prop('disabled', false).text(<?php echo wp_json_encode( __( 'Add to cart', 'meyvora-convert' ) ); ?>);
				window.alert((r && r.data && r.data.message) ? r.data.message : 'Error');
			}
		}).fail(function() {
			$btn.prop('disabled', false).text(<?php echo wp_json_encode( __( 'Add to cart', 'meyvora-convert' ) ); ?>);
		});
	});
	$('.cro-post-purchase-dismiss').on('click', function(e) {
		e.preventDefault();
		$(this).closest('.cro-post-purchase-upsell').slideUp(200);
	});
});
</script>
