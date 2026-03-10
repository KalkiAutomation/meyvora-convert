<?php
/**
 * Onboarding checklist – shown after activation or via "Restart onboarding" in Settings.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$settings           = function_exists( 'cro_settings' ) ? cro_settings() : null;
$shipping_bar_on    = $settings ? $settings->is_feature_enabled( 'shipping_bar' ) : false;
$sticky_cart_on     = $settings ? $settings->is_feature_enabled( 'sticky_cart' ) : false;
$create_campaign_url = admin_url( 'admin.php?page=cro-campaign-edit' );
?>

<div class="cro-onboarding cro-onboarding-checklist">
	<form method="post" action="" id="cro-onboarding-form">
		<?php wp_nonce_field( 'cro_onboarding', 'cro_onboarding_nonce' ); ?>
		<input type="hidden" name="cro_onboarding_checklist" value="1" />

		<ul class="cro-onboarding-checklist-list">
			<li class="cro-checklist-item <?php echo $shipping_bar_on ? 'done' : ''; ?>">
				<span class="cro-checklist-num">1</span>
				<label class="cro-checklist-label">
					<input type="checkbox" name="feature_shipping_bar" value="1" <?php checked( $shipping_bar_on ); ?> />
					<?php esc_html_e( 'Enable Shipping Bar', 'meyvora-convert' ); ?>
				</label>
				<span class="cro-checklist-desc"><?php esc_html_e( 'Free shipping progress bar on product, cart, and shop.', 'meyvora-convert' ); ?></span>
			</li>
			<li class="cro-checklist-item <?php echo $sticky_cart_on ? 'done' : ''; ?>">
				<span class="cro-checklist-num">2</span>
				<label class="cro-checklist-label">
					<input type="checkbox" name="feature_sticky_cart" value="1" <?php checked( $sticky_cart_on ); ?> />
					<?php esc_html_e( 'Enable Sticky Add to Cart', 'meyvora-convert' ); ?>
				</label>
				<span class="cro-checklist-desc"><?php esc_html_e( 'Sticky bar on product pages so Add to Cart is always visible.', 'meyvora-convert' ); ?></span>
			</li>
			<li class="cro-checklist-item">
				<span class="cro-checklist-num">3</span>
				<span class="cro-checklist-label"><?php esc_html_e( 'Create first Campaign', 'meyvora-convert' ); ?></span>
				<a href="<?php echo esc_url( $create_campaign_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Create campaign', 'meyvora-convert' ); ?></a>
				<span class="cro-checklist-desc"><?php esc_html_e( 'Exit intent popups, bars, or slide-ins to capture leads and boost conversions.', 'meyvora-convert' ); ?></span>
			</li>
		</ul>

		<p class="cro-onboarding-actions">
			<button type="submit" name="cro_onboarding_done" value="1" class="button button-primary button-hero"><?php esc_html_e( 'Go to dashboard', 'meyvora-convert' ); ?></button>
		</p>
	</form>

	<p class="cro-onboarding-skip">
		<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=meyvora-convert&cro_skip_onboarding=1' ), 'cro_skip_onboarding' ) ); ?>"><?php esc_html_e( 'Skip and go to dashboard', 'meyvora-convert' ); ?></a>
	</p>
</div>
