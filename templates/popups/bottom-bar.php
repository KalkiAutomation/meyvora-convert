<?php
/**
 * Bottom Bar Template
 * 
 * Horizontal bar fixed to bottom of viewport
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
$classes = [ 'cro-popup', 'cro-popup--bottom-bar' ];
if ( $is_preview ) {
    $classes[] = 'cro-popup--preview';
    $classes[] = 'cro-popup--active';
}
?>
<?php if ( $is_preview ) : ?>
<div class="cro-preview-viewport cro-preview-viewport--bar cro-preview-viewport--bar-bottom">
<?php endif; ?>

<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
     role="alert"
     data-campaign-id="<?php echo esc_attr( $campaign_id ); ?>"
     style="<?php echo esc_attr( CRO_Templates::get_inline_styles( $styling, $campaign ) ); ?>">
    
    <!-- Content -->
    <div class="cro-popup__inner">
        
        <?php if ( ! empty( $content['headline'] ) ) : ?>
        <span class="cro-popup__headline"
            <?php if ( ! empty( $styling['headline_color'] ) ) : ?>
            style="color: <?php echo esc_attr( $styling['headline_color'] ); ?>"
            <?php endif; ?>>
            <?php echo esc_html( CRO_Placeholders::process( $content['headline'] ) ); ?>
        </span>
        <?php endif; ?>
        
        <?php if ( ! empty( $content['show_countdown'] ) ) : ?>
        <?php $inline = true; ?>
        <?php include CRO_PLUGIN_DIR . 'templates/partials/countdown.php'; ?>
        <?php endif; ?>
        
        <?php if ( ! empty( $content['show_coupon'] ) && ! empty( $content['coupon_code'] ) ) : ?>
        <?php $inline = true; ?>
        <?php include CRO_PLUGIN_DIR . 'templates/partials/coupon.php'; ?>
        <?php endif; ?>
        
        <?php if ( ! empty( $content['cta_text'] ) ) : ?>
        <button type="button" class="cro-popup__cta" data-action="cta"
                style="<?php echo esc_attr( CRO_Templates::get_button_styles( $styling ) ); ?>">
            <?php echo esc_html( $content['cta_text'] ); ?>
        </button>
        <?php endif; ?>
        
    </div>
    
    <!-- Close Button -->
    <button type="button" class="cro-popup__close" aria-label="<?php esc_attr_e( 'Close', 'meyvora-convert' ); ?>" data-action="close">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 6L6 18M6 6l12 12"/>
        </svg>
    </button>
</div>

<?php if ( $is_preview ) : ?>
</div>
<?php endif; ?>
