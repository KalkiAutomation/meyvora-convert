<?php
/**
 * Email Form Partial
 * 
 * @var array  $content     Campaign content
 * @var string $campaign_id Campaign ID
 */
defined( 'ABSPATH' ) || exit;

$placeholder  = isset( $content['email_placeholder'] ) ? $content['email_placeholder'] : __( 'Enter your email', 'meyvora-convert' );
$button_text  = isset( $content['email_button_text'] ) ? $content['email_button_text'] : __( 'Subscribe', 'meyvora-convert' );
$campaign_id  = isset( $campaign_id ) ? $campaign_id : '';
?>
<form class="cro-popup__email-form" data-campaign-id="<?php echo esc_attr( $campaign_id ); ?>">
    <div class="cro-popup__email-row">
        <input type="email" 
               class="cro-popup__email-input" 
               name="email" 
               placeholder="<?php echo esc_attr( $placeholder ); ?>" 
               required 
               autocomplete="email">
        <button type="submit" class="cro-popup__email-submit">
            <?php echo esc_html( $button_text ); ?>
        </button>
    </div>
    <div class="cro-popup__email-error" style="display: none;"></div>
    <div class="cro-popup__email-success" style="display: none;">
        <?php esc_html_e( 'Thank you for subscribing!', 'meyvora-convert' ); ?>
    </div>
</form>
