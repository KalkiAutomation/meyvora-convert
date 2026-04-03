<?php
/**
 * Spin-to-win wheel popup (server-rendered / preview).
 *
 * @package Meyvora_Convert
 */
defined( 'ABSPATH' ) || exit;


// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template/view: variables are file-local.
$content     = is_array( $campaign ) ? ( $campaign['content'] ?? array() ) : ( $campaign->content ?? array() );
$campaign_id = is_array( $campaign ) ? ( $campaign['id'] ?? 0 ) : ( $campaign->id ?? 0 );
$is_preview  = ! empty( $campaign['is_preview'] );

$headline    = isset( $content['headline'] ) ? $content['headline'] : __( 'Try your luck!', 'meyvora-convert' );
$subheadline = isset( $content['subheadline'] ) ? $content['subheadline'] : __( 'Spin for a chance to win a discount', 'meyvora-convert' );
$cta_text    = isset( $content['cta_text'] ) ? $content['cta_text'] : __( 'Spin now', 'meyvora-convert' );

$slices = apply_filters(
	'meyvc_wheel_slices',
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

$classes = array( 'meyvc-popup', 'meyvc-popup--gamified-wheel' );
if ( $is_preview ) {
	$classes[] = 'meyvc-popup--preview';
	$classes[] = 'meyvc-popup--active';
}
?>

<?php if ( $is_preview ) : ?>
<div class="meyvc-preview-viewport">
	<div class="meyvc-preview-overlay">
<?php endif; ?>

<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
	role="dialog"
	aria-modal="true"
	aria-labelledby="meyvc-wheel-headline-<?php echo esc_attr( (string) $campaign_id ); ?>"
	data-campaign-id="<?php echo esc_attr( (string) $campaign_id ); ?>">

	<button type="button" class="meyvc-popup__close" aria-label="<?php esc_attr_e( 'Close', 'meyvora-convert' ); ?>" data-action="close">&#10005;</button>

	<div class="meyvc-wheel-body">
		<h2 class="meyvc-wheel-headline" id="meyvc-wheel-headline-<?php echo esc_attr( (string) $campaign_id ); ?>">
			<?php echo wp_kses_post( $headline ); ?>
		</h2>
		<?php if ( $subheadline ) : ?>
			<p class="meyvc-wheel-sub"><?php echo wp_kses_post( $subheadline ); ?></p>
		<?php endif; ?>

		<div class="meyvc-wheel-wrap" aria-hidden="true">
			<canvas id="meyvc-wheel-canvas-<?php echo esc_attr( (string) $campaign_id ); ?>"
					width="300" height="300"
					data-slices="<?php echo esc_attr( wp_json_encode( $slices ) ); ?>">
			</canvas>
			<div class="meyvc-wheel-pointer">&#9660;</div>
		</div>

		<div class="meyvc-wheel-email-step">
			<input type="email" class="meyvc-wheel-email" placeholder="<?php esc_attr_e( 'Enter your email to spin', 'meyvora-convert' ); ?>" aria-label="<?php esc_attr_e( 'Email address', 'meyvora-convert' ); ?>" />
			<button type="button" class="meyvc-popup__cta meyvc-wheel-spin-btn" data-campaign-id="<?php echo esc_attr( (string) $campaign_id ); ?>">
				<?php echo esc_html( $cta_text ); ?>
			</button>
		</div>

		<div class="meyvc-wheel-result" style="display:none;" aria-live="polite">
			<p class="meyvc-wheel-result-text"></p>
			<p class="meyvc-wheel-coupon-code" style="display:none;"></p>
		</div>
	</div>
</div>

<?php if ( $is_preview ) : ?>
	</div>
</div>
<?php endif; ?>
