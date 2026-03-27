<?php
/**
 * Spin-to-win wheel popup (server-rendered / preview).
 *
 * @package Meyvora_Convert
 */
defined( 'ABSPATH' ) || exit;

$content     = is_array( $campaign ) ? ( $campaign['content'] ?? array() ) : ( $campaign->content ?? array() );
$campaign_id = is_array( $campaign ) ? ( $campaign['id'] ?? 0 ) : ( $campaign->id ?? 0 );
$is_preview  = ! empty( $campaign['is_preview'] );

$headline    = isset( $content['headline'] ) ? $content['headline'] : __( 'Try your luck!', 'meyvora-convert' );
$subheadline = isset( $content['subheadline'] ) ? $content['subheadline'] : __( 'Spin for a chance to win a discount', 'meyvora-convert' );
$cta_text    = isset( $content['cta_text'] ) ? $content['cta_text'] : __( 'Spin now', 'meyvora-convert' );

$slices = apply_filters(
	'cro_wheel_slices',
	array(
		array( 'label' => '10% off', 'type' => 'win', 'color' => '#2563eb' ),
		array( 'label' => 'Try again', 'type' => 'lose', 'color' => '#e5e7eb' ),
		array( 'label' => '5% off', 'type' => 'win', 'color' => '#7c3aed' ),
		array( 'label' => 'Try again', 'type' => 'lose', 'color' => '#e5e7eb' ),
		array( 'label' => 'Free ship', 'type' => 'win', 'color' => '#059669' ),
		array( 'label' => 'Try again', 'type' => 'lose', 'color' => '#e5e7eb' ),
	),
	(int) $campaign_id
);

$classes = array( 'cro-popup', 'cro-popup--gamified-wheel' );
if ( $is_preview ) {
	$classes[] = 'cro-popup--preview';
	$classes[] = 'cro-popup--active';
}
?>

<?php if ( $is_preview ) : ?>
<div class="cro-preview-viewport">
	<div class="cro-preview-overlay">
<?php endif; ?>

<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
	role="dialog"
	aria-modal="true"
	aria-labelledby="cro-wheel-headline-<?php echo esc_attr( (string) $campaign_id ); ?>"
	data-campaign-id="<?php echo esc_attr( (string) $campaign_id ); ?>">

	<button type="button" class="cro-popup__close" aria-label="<?php esc_attr_e( 'Close', 'meyvora-convert' ); ?>" data-action="close">&#10005;</button>

	<div class="cro-wheel-body">
		<h2 class="cro-wheel-headline" id="cro-wheel-headline-<?php echo esc_attr( (string) $campaign_id ); ?>">
			<?php echo wp_kses_post( $headline ); ?>
		</h2>
		<?php if ( $subheadline ) : ?>
			<p class="cro-wheel-sub"><?php echo wp_kses_post( $subheadline ); ?></p>
		<?php endif; ?>

		<div class="cro-wheel-wrap" aria-hidden="true">
			<canvas id="cro-wheel-canvas-<?php echo esc_attr( (string) $campaign_id ); ?>"
					width="300" height="300"
					data-slices="<?php echo esc_attr( wp_json_encode( $slices ) ); ?>">
			</canvas>
			<div class="cro-wheel-pointer">&#9660;</div>
		</div>

		<div class="cro-wheel-email-step">
			<input type="email" class="cro-wheel-email" placeholder="<?php esc_attr_e( 'Enter your email to spin', 'meyvora-convert' ); ?>" aria-label="<?php esc_attr_e( 'Email address', 'meyvora-convert' ); ?>" />
			<button type="button" class="cro-popup__cta cro-wheel-spin-btn" data-campaign-id="<?php echo esc_attr( (string) $campaign_id ); ?>">
				<?php echo esc_html( $cta_text ); ?>
			</button>
		</div>

		<div class="cro-wheel-result" style="display:none;" aria-live="polite">
			<p class="cro-wheel-result-text"></p>
			<p class="cro-wheel-coupon-code" style="display:none;"></p>
		</div>
	</div>
</div>

<?php if ( $is_preview ) : ?>
	</div>
</div>
<?php endif; ?>
