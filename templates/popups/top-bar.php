<?php
/**
 * Top Bar Template
 * 
 * Horizontal bar fixed to top of viewport
 *
 * @package Meyvora_Convert
 */
defined( 'ABSPATH' ) || exit;

// Extract data
$content     = is_array( $campaign ) ? ( $campaign['content'] ?? [] ) : ( $campaign->content ?? [] );
$styling     = is_array( $campaign ) ? ( $campaign['styling'] ?? [] ) : ( $campaign->styling ?? [] );
$campaign_id = is_array( $campaign ) ? ( $campaign['id'] ?? '' ) : ( $campaign->id ?? '' );
$is_preview  = ! empty( $campaign['is_preview'] );

// Build classes
$classes = [ 'meyvc-popup', 'meyvc-popup--top-bar' ];
if ( $is_preview ) {
    $classes[] = 'meyvc-popup--preview';
    $classes[] = 'meyvc-popup--active';
}
?>
<?php if ( $is_preview ) : ?>
<div class="meyvc-preview-viewport meyvc-preview-viewport--bar">
<?php endif; ?>

<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
     role="alert"
     data-campaign-id="<?php echo esc_attr( $campaign_id ); ?>"
     style="<?php echo esc_attr( MEYVC_Templates::get_inline_styles( $styling, $campaign ) ); ?>">
    
    <!-- Content -->
    <div class="meyvc-popup__inner">
        
        <?php if ( ! empty( $content['headline'] ) ) : ?>
        <span class="meyvc-popup__headline"
            <?php if ( ! empty( $styling['headline_color'] ) ) : ?>
            style="color: <?php echo esc_attr( $styling['headline_color'] ); ?>"
            <?php endif; ?>>
            <?php echo esc_html( MEYVC_Placeholders::process( $content['headline'] ) ); ?>
        </span>
        <?php endif; ?>
        
        <?php if ( ! empty( $content['show_countdown'] ) ) : ?>
        <?php $inline = true; ?>
        <?php include MEYVC_PLUGIN_DIR . 'templates/partials/countdown.php'; ?>
        <?php endif; ?>
        
        <?php
        $meyvc_coupon_actions = array( 'apply_coupon', 'apply_coupon_checkout', 'copy_coupon' );
        $meyvc_show_coupon_block = ( ! empty( $content['show_coupon'] ) || in_array( $content['cta_action'] ?? '', $meyvc_coupon_actions, true ) ) && ! empty( $content['coupon_code'] );
        if ( $meyvc_show_coupon_block ) :
            $inline = true;
            include MEYVC_PLUGIN_DIR . 'templates/partials/coupon.php';
        endif;
        ?>
        
        <?php if ( ! empty( $content['cta_text'] ) ) : ?>
        <button type="button" class="meyvc-popup__cta" data-action="cta"
                style="<?php echo esc_attr( MEYVC_Templates::get_button_styles( $styling ) ); ?>">
            <?php echo esc_html( $content['cta_text'] ); ?>
        </button>
        <?php endif; ?>
        
    </div>
    
    <!-- Close Button -->
    <button type="button" class="meyvc-popup__close" aria-label="<?php esc_attr_e( 'Close', 'meyvora-convert' ); ?>" data-action="close">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 6L6 18M6 6l12 12"/>
        </svg>
    </button>
</div>

<?php if ( $is_preview ) : ?>
</div>
<?php endif; ?>
