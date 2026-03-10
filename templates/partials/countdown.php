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
<div class="cro-popup__countdown<?php echo $inline ? ' cro-popup__countdown--inline' : ''; ?>" 
     data-minutes="<?php echo esc_attr( $minutes ); ?>">
    <span class="cro-popup__countdown-label"><?php esc_html_e( 'Offer ends in', 'meyvora-convert' ); ?></span>
    <span class="cro-popup__countdown-timer">
        <span class="cro-countdown-minutes"><?php echo str_pad( $minutes, 2, '0', STR_PAD_LEFT ); ?></span>:<span class="cro-countdown-seconds">00</span>
    </span>
</div>
