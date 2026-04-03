<?php
/**
 * Coupon Code Partial
 * 
 * @var array $content Campaign content
 */
defined( 'ABSPATH' ) || exit;


// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template/view: variables are file-local.
$coupon_code = isset( $content['coupon_code'] ) ? $content['coupon_code'] : '';
$inline      = isset( $inline ) && $inline;
$coupon_label = '';
if ( ! empty( $content['coupon_label'] ) ) {
	$coupon_label = (string) $content['coupon_label'];
} elseif ( ! empty( $content['coupon_display_text'] ) ) {
	$coupon_label = (string) $content['coupon_display_text'];
}

if ( empty( $coupon_code ) ) {
	return;
}
?>
<div class="meyvc-popup__coupon<?php echo $inline ? ' meyvc-popup__coupon--inline' : ''; ?>">
	<?php if ( ! $inline ) : ?>
	<span class="meyvc-popup__coupon-label">
		<?php echo esc_html( $coupon_label !== '' ? $coupon_label : __( 'Your code', 'meyvora-convert' ) ); ?>
	</span>
	<?php endif; ?>
	<code class="meyvc-popup__coupon-code" data-code="<?php echo esc_attr( $coupon_code ); ?>">
		<?php echo esc_html( $coupon_code ); ?>
	</code>
	<button type="button" class="meyvc-popup__coupon-copy" data-action="copy-coupon"
			aria-label="<?php esc_attr_e( 'Copy coupon code', 'meyvora-convert' ); ?>">
		<?php esc_html_e( 'Copy', 'meyvora-convert' ); ?>
	</button>
</div>
