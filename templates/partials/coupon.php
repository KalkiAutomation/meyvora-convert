<?php
/**
 * Coupon Code Partial
 * 
 * @var array $content Campaign content
 */
defined( 'ABSPATH' ) || exit;

$coupon_code = isset( $content['coupon_code'] ) ? $content['coupon_code'] : '';
$inline      = isset( $inline ) && $inline;

if ( empty( $coupon_code ) ) {
    return;
}
?>
<div class="cro-popup__coupon<?php echo $inline ? ' cro-popup__coupon--inline' : ''; ?>">
    <?php if ( ! $inline ) : ?>
    <span class="cro-popup__coupon-label"><?php esc_html_e( 'Your code', 'meyvora-convert' ); ?></span>
    <?php endif; ?>
    <code class="cro-popup__coupon-code" data-code="<?php echo esc_attr( $coupon_code ); ?>">
        <?php echo esc_html( $coupon_code ); ?>
    </code>
</div>
