<?php
/**
 * Countdown Timer Partial
 * 
 * @var array $content Campaign content
 */
defined( 'ABSPATH' ) || exit;

$minutes = isset( $content['countdown_minutes'] ) ? intval( $content['countdown_minutes'] ) : 15;
$inline  = isset( $inline ) && $inline;
?>
<div class="meyvc-popup__countdown<?php echo $inline ? ' meyvc-popup__countdown--inline' : ''; ?>" 
     data-minutes="<?php echo esc_attr( $minutes ); ?>">
    <span class="meyvc-popup__countdown-label"><?php esc_html_e( 'Offer ends in', 'meyvora-convert' ); ?></span>
    <span class="meyvc-popup__countdown-timer">
        <span class="meyvc-countdown-minutes"><?php echo esc_html( str_pad( $minutes, 2, '0', STR_PAD_LEFT ) ); ?></span>:<span class="meyvc-countdown-seconds">00</span>
    </span>
</div>
