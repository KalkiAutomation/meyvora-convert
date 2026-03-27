<?php
/**
 * Minimal Template
 * 
 * Simple, clean popup design
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
$classes = [ 'cro-popup', 'cro-popup--minimal' ];
if ( $is_preview ) {
    $classes[] = 'cro-popup--preview';
    $classes[] = 'cro-popup--active';
}
?>
<?php if ( $is_preview ) : ?>
<div class="cro-preview-viewport">
    <div class="cro-preview-overlay"></div>
<?php endif; ?>

<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
     role="dialog"
     aria-modal="true"
     aria-labelledby="cro-headline-<?php echo esc_attr( $campaign_id ); ?>"
     data-campaign-id="<?php echo esc_attr( $campaign_id ); ?>"
     style="<?php echo esc_attr( CRO_Templates::get_inline_styles( $styling, $campaign ) ); ?>">
    
    <!-- Close Button -->
    <button type="button" class="cro-popup__close" aria-label="<?php esc_attr_e( 'Close', 'meyvora-convert' ); ?>" data-action="close">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 6L6 18M6 6l12 12"/>
        </svg>
    </button>
    
    <!-- Content -->
    <div class="cro-popup__inner">
        
        <?php if ( ! empty( $content['headline'] ) ) : ?>
        <h2 class="cro-popup__headline" id="cro-headline-<?php echo esc_attr( $campaign_id ); ?>"
            <?php if ( ! empty( $styling['headline_color'] ) ) : ?>
            style="color: <?php echo esc_attr( $styling['headline_color'] ); ?>"
            <?php endif; ?>>
            <?php echo esc_html( CRO_Placeholders::process( $content['headline'] ) ); ?>
        </h2>
        <?php endif; ?>
        
        <?php if ( ! empty( $content['subheadline'] ) ) : ?>
        <p class="cro-popup__subheadline">
            <?php echo esc_html( CRO_Placeholders::process( $content['subheadline'] ) ); ?>
        </p>
        <?php endif; ?>
        
        <?php if ( ! empty( $content['body'] ) ) : ?>
        <div class="cro-popup__body">
            <?php echo wp_kses_post( CRO_Placeholders::process( $content['body'] ) ); ?>
        </div>
        <?php endif; ?>
        
        <?php
        $cro_coupon_actions = array( 'apply_coupon', 'apply_coupon_checkout', 'copy_coupon' );
        $cro_show_coupon_block = ( ! empty( $content['show_coupon'] ) || in_array( $content['cta_action'] ?? '', $cro_coupon_actions, true ) ) && ! empty( $content['coupon_code'] );
        if ( $cro_show_coupon_block ) :
            include CRO_PLUGIN_DIR . 'templates/partials/coupon.php';
        endif;
        ?>
        
        <?php if ( ! empty( $content['cta_text'] ) ) : ?>
        <button type="button" class="cro-popup__cta" data-action="cta"
                style="<?php echo esc_attr( CRO_Templates::get_button_styles( $styling ) ); ?>">
            <?php echo esc_html( $content['cta_text'] ); ?>
        </button>
        <?php endif; ?>
        
        <?php if ( ! empty( $content['show_dismiss_link'] ) || ! isset( $content['show_dismiss_link'] ) ) : ?>
        <a href="#" class="cro-popup__dismiss" data-action="dismiss">
            <?php echo esc_html( $content['dismiss_text'] ?? __( 'No thanks', 'meyvora-convert' ) ); ?>
        </a>
        <?php endif; ?>
        
    </div>
</div>

<?php if ( $is_preview ) : ?>
</div>
<?php endif; ?>
