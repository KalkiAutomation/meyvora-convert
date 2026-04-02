<?php
/**
 * Admin Presets library page
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$presets = class_exists( 'MEYVC_Presets' ) ? MEYVC_Presets::get_all() : array();

$preset_applied      = MEYVC_Security::get_query_var( 'preset_applied' ) === '1';
$applied_message     = MEYVC_Security::get_query_var( 'message' );
$applied_campaign_id = MEYVC_Security::get_query_var_absint( 'campaign_id' );

$error = MEYVC_Security::get_query_var( 'error' );
$error_messages = array(
	'invalid_nonce'   => __( 'Invalid security check. Please try again.', 'meyvora-convert' ),
	'unauthorized'    => __( 'You do not have permission to apply presets.', 'meyvora-convert' ),
	'invalid_preset'  => __( 'Preset not found or invalid.', 'meyvora-convert' ),
	'apply_failed'    => __( 'Failed to apply preset.', 'meyvora-convert' ),
);
$feature_labels = array(
	'campaigns'         => __( 'Conversion campaigns', 'meyvora-convert' ),
	'sticky_cart'       => __( 'Sticky add-to-cart', 'meyvora-convert' ),
	'shipping_bar'      => __( 'Free shipping bar', 'meyvora-convert' ),
	'trust_badges'      => __( 'Trust badges', 'meyvora-convert' ),
	'cart_optimizer'    => __( 'Cart optimizer', 'meyvora-convert' ),
	'checkout_optimizer'=> __( 'Checkout optimizer', 'meyvora-convert' ),
	'stock_urgency'     => __( 'Low stock urgency', 'meyvora-convert' ),
);

$industry_packs = class_exists( 'MEYVC_Presets' ) ? MEYVC_Presets::get_industry_packs() : array();
?>

<div class="meyvc-admin-presets">
	<p class="meyvc-presets-intro">
		<?php esc_html_e( 'Apply ready-made configurations for boosters and campaigns. Each preset enables specific features and can create a default campaign.', 'meyvora-convert' ); ?>
	</p>

	<?php if ( $preset_applied && $applied_message ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $applied_message ); ?>
				<?php if ( $applied_campaign_id ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=meyvc-campaign-edit&campaign_id=' . $applied_campaign_id ) ); ?>"><?php esc_html_e( 'Edit campaign', 'meyvora-convert' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $error && isset( $error_messages[ $error ] ) ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error_messages[ $error ] ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $industry_packs ) ) : ?>
		<h2 class="meyvc-presets-section-title"><?php esc_html_e( 'Industry packs', 'meyvora-convert' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Apply several presets at once for a vertical or use case.', 'meyvora-convert' ); ?></p>
		<div class="meyvc-presets-grid meyvc-industry-packs-grid">
			<?php foreach ( $industry_packs as $pack ) : ?>
				<?php
				$pid   = isset( $pack['id'] ) ? (string) $pack['id'] : '';
				$pname = isset( $pack['name'] ) ? (string) $pack['name'] : '';
				$pdesc = isset( $pack['description'] ) ? (string) $pack['description'] : '';
				?>
				<div class="meyvc-preset-card" data-pack-id="<?php echo esc_attr( $pid ); ?>">
					<div class="meyvc-preset-card-inner">
						<h3 class="meyvc-preset-name"><?php echo esc_html( $pname ); ?></h3>
						<p class="meyvc-preset-desc"><?php echo esc_html( $pdesc ); ?></p>
						<div class="meyvc-preset-actions">
							<button type="button" class="button button-primary meyvc-industry-pack-apply" data-pack-id="<?php echo esc_attr( $pid ); ?>"><?php esc_html_e( 'Apply pack', 'meyvora-convert' ); ?></button>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<div id="meyvc-industry-pack-notice" class="notice meyvc-hidden" style="display:none;margin-top:12px;" role="status"></div>
	<?php endif; ?>

	<h2 class="meyvc-presets-section-title"><?php esc_html_e( 'Single presets', 'meyvora-convert' ); ?></h2>

	<div class="meyvc-presets-grid">
		<?php foreach ( $presets as $preset ) : ?>
			<?php
			$features_list = isset( $preset['features'] ) ? (array) $preset['features'] : array();
			$has_campaign  = ! empty( $preset['campaign'] );
			?>
			<div class="meyvc-preset-card" data-preset-id="<?php echo esc_attr( $preset['id'] ); ?>">
				<div class="meyvc-preset-card-inner">
					<h3 class="meyvc-preset-name"><?php echo esc_html( $preset['name'] ); ?></h3>
					<p class="meyvc-preset-desc"><?php echo esc_html( $preset['description'] ); ?></p>
					<div class="meyvc-preset-meta">
						<span class="meyvc-preset-features">
							<?php
							$labels = array();
							foreach ( $features_list as $f ) {
								$labels[] = isset( $feature_labels[ $f ] ) ? $feature_labels[ $f ] : $f;
							}
							echo esc_html( implode( ', ', $labels ) );
							?>
						</span>
						<?php if ( $has_campaign ) : ?>
							<span class="meyvc-preset-badge"><?php esc_html_e( 'Creates campaign', 'meyvora-convert' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="meyvc-preset-actions">
						<form method="post" action="" class="meyvc-preset-apply-form meyvc-inline-form">
							<?php wp_nonce_field( 'meyvc_apply_preset', 'meyvc_preset_nonce' ); ?>
							<input type="hidden" name="meyvc_apply_preset" value="1" />
							<input type="hidden" name="preset_id" value="<?php echo esc_attr( $preset['id'] ); ?>" />
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply preset', 'meyvora-convert' ); ?></button>
						</form>
						<button type="button" class="button meyvc-preset-preview-btn" data-preset-id="<?php echo esc_attr( $preset['id'] ); ?>">
							<?php esc_html_e( 'Preview preset', 'meyvora-convert' ); ?>
						</button>
					</div>
				</div>
				<div id="meyvc-preset-preview-<?php echo esc_attr( $preset['id'] ); ?>" class="meyvc-preset-preview-content meyvc-hidden" aria-hidden="true">
					<h4><?php echo esc_html( $preset['name'] ); ?></h4>
					<p><?php echo esc_html( $preset['description'] ); ?></p>
					<p><strong><?php esc_html_e( 'Enables:', 'meyvora-convert' ); ?></strong> <?php echo esc_html( implode( ', ', $labels ) ); ?></p>
					<?php if ( $has_campaign ) : ?>
						<p><strong><?php esc_html_e( 'Creates campaign:', 'meyvora-convert' ); ?></strong> <?php echo esc_html( $preset['campaign']['name'] ?? __( 'Yes', 'meyvora-convert' ) ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( empty( $presets ) ) : ?>
		<p><?php esc_html_e( 'No presets available.', 'meyvora-convert' ); ?></p>
	<?php endif; ?>
</div>

<!-- Preview modal -->
<div id="meyvc-preset-preview-modal" class="meyvc-preset-modal meyvc-hidden" role="dialog" aria-labelledby="meyvc-preset-preview-title" aria-modal="true">
	<div class="meyvc-preset-modal-backdrop"></div>
	<div class="meyvc-preset-modal-content">
		<button type="button" class="meyvc-preset-modal-close" aria-label="<?php esc_attr_e( 'Close', 'meyvora-convert' ); ?>"><?php echo wp_kses( MEYVC_Icons::svg( 'x', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></button>

		<h2 id="meyvc-preset-preview-title" class="meyvc-preset-modal-title"><?php esc_html_e( 'Preset preview', 'meyvora-convert' ); ?></h2>
		<div id="meyvc-preset-preview-body" class="meyvc-preset-modal-body"></div>
		<div class="meyvc-preset-modal-footer">
			<button type="button" class="button meyvc-preset-modal-close-btn"><?php esc_html_e( 'Close', 'meyvora-convert' ); ?></button>
		</div>
	</div>
</div>
