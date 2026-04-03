<?php
/**
 * Admin checkout optimization page – checkout friction killers
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template/view: variables are file-local.
$settings = meyvc_settings();

// Handle form submission.
$nonce_valid = isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'meyvc_checkout_nonce' );
if ( isset( $_POST['meyvc_save_checkout'] ) && $nonce_valid ) {

	$settings->set( 'general', 'checkout_optimizer_enabled', ! empty( $_POST['checkout_enabled'] ) );

	// Field removal toggles.
	$settings->set( 'checkout_optimizer', 'remove_company_field', ! empty( $_POST['remove_company'] ) );
	$settings->set( 'checkout_optimizer', 'remove_address_2', ! empty( $_POST['remove_address_2'] ) );
	$settings->set( 'checkout_optimizer', 'remove_phone', ! empty( $_POST['remove_phone'] ) );
	$settings->set( 'checkout_optimizer', 'remove_order_notes', ! empty( $_POST['remove_order_notes'] ) );

	// Optimizations.
	$settings->set( 'checkout_optimizer', 'move_coupon_to_top', ! empty( $_POST['move_coupon'] ) );
	$settings->set( 'checkout_optimizer', 'autofocus_first_field', ! empty( $_POST['autofocus'] ) );
	$settings->set( 'checkout_optimizer', 'inline_validation', ! empty( $_POST['inline_validation'] ) );

	// Trust elements.
	$settings->set( 'checkout_optimizer', 'show_trust_message', ! empty( $_POST['show_trust'] ) );
	$settings->set( 'checkout_optimizer', 'trust_message_text', sanitize_text_field( wp_unslash( $_POST['trust_message'] ?? '' ) ) );
	$settings->set( 'checkout_optimizer', 'show_secure_badge', ! empty( $_POST['show_secure_badge'] ) );
	$settings->set( 'checkout_optimizer', 'show_guarantee', ! empty( $_POST['show_guarantee'] ) );
	$settings->set( 'checkout_optimizer', 'guarantee_text', sanitize_text_field( wp_unslash( $_POST['guarantee_text'] ?? '' ) ) );

	$settings->set( 'checkout_optimizer', 'prefill_from_last_order', ! empty( $_POST['prefill_from_last_order'] ) );
	$settings->set( 'checkout_optimizer', 'show_express_checkout_prompt', ! empty( $_POST['show_express_checkout_prompt'] ) );
	$settings->set( 'checkout_optimizer', 'express_prompt_text', sanitize_text_field( wp_unslash( $_POST['express_prompt_text'] ?? '' ) ) );
	$settings->set( 'checkout_optimizer', 'show_progress_steps', ! empty( $_POST['show_progress_steps'] ) );
	$settings->set( 'checkout_optimizer', 'post_purchase_enabled', ! empty( $_POST['post_purchase_enabled'] ) );
	$settings->set( 'checkout_optimizer', 'post_purchase_product_ids', sanitize_text_field( wp_unslash( $_POST['post_purchase_product_ids'] ?? '' ) ) );
	$settings->set( 'checkout_optimizer', 'post_purchase_discount_percent', max( 0, min( 90, (float) sanitize_text_field( wp_unslash( $_POST['post_purchase_discount_percent'] ?? '0' ) ) ) ) );
	$settings->set( 'checkout_optimizer', 'post_purchase_headline', sanitize_text_field( wp_unslash( $_POST['post_purchase_headline'] ?? '' ) ) );
	$settings->set( 'checkout_optimizer', 'post_purchase_subhead', sanitize_text_field( wp_unslash( $_POST['post_purchase_subhead'] ?? '' ) ) );

	echo '<div class="meyvc-ui-notice meyvc-ui-toast-placeholder" role="status"><p>' . esc_html__( 'Checkout settings saved!', 'meyvora-convert' ) . '</p></div>';
}

$checkout = $settings->get_checkout_settings();
?>

			<div class="meyvc-ui-card meyvc-impact-notice">
				<?php echo wp_kses( MEYVC_Icons::svg( 'sparkles', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?>

				<p>
					<?php esc_html_e( 'Industry data: Removing unnecessary checkout fields can increase conversions by 20-30%.', 'meyvora-convert' ); ?>
				</p>
			</div>

	<form method="post" id="meyvc-checkout-form">
		<?php wp_nonce_field( 'meyvc_checkout_nonce' ); ?>

		<!-- Master Toggle -->
		<div class="meyvc-master-toggle">
			<label class="meyvc-toggle-large">
				<span class="meyvc-toggle">
					<input type="checkbox" name="checkout_enabled" value="1"
						<?php checked( $settings->is_feature_enabled( 'checkout_optimizer' ) ); ?> />
					<span class="meyvc-toggle-slider"></span>
				</span>
				<span class="meyvc-toggle-label">
					<?php esc_html_e( 'Enable Checkout Optimizations', 'meyvora-convert' ); ?>
				</span>
			</label>
		</div>

		<!-- Field Removal Section -->
		<div class="meyvc-settings-section">
			<h2>
				<?php echo wp_kses( MEYVC_Icons::svg( 'trash', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?>

				<?php esc_html_e( 'Remove Optional Fields', 'meyvora-convert' ); ?>
			</h2>
			<p class="meyvc-section-description">
				<?php esc_html_e( 'Every field you remove reduces abandonment. Only keep what you truly need.', 'meyvora-convert' ); ?>
			</p>

			<div class="meyvc-fields-grid meyvc-fields-grid--1col">
				<div class="meyvc-field meyvc-col-12">
					<span class="meyvc-field__label"><?php esc_html_e( 'Billing Fields', 'meyvora-convert' ); ?></span>
					<div class="meyvc-field__control">
						<label class="meyvc-checkbox-card">
							<input type="checkbox" name="remove_company" value="1"
								<?php checked( ! empty( $checkout['remove_company_field'] ) ); ?> />
							<span class="meyvc-checkbox-content">
								<strong><?php esc_html_e( 'Remove Company Name', 'meyvora-convert' ); ?></strong>
								<span><?php esc_html_e( 'Most B2C stores don\'t need this', 'meyvora-convert' ); ?></span>
							</span>
						</label>
						<label class="meyvc-checkbox-card">
							<input type="checkbox" name="remove_address_2" value="1"
								<?php checked( ! empty( $checkout['remove_address_2'] ) ); ?> />
							<span class="meyvc-checkbox-content">
								<strong><?php esc_html_e( 'Remove Address Line 2', 'meyvora-convert' ); ?></strong>
								<span><?php esc_html_e( 'Apartment/Suite can go in Address 1', 'meyvora-convert' ); ?></span>
							</span>
						</label>
						<label class="meyvc-checkbox-card">
							<input type="checkbox" name="remove_phone" value="1"
								<?php checked( ! empty( $checkout['remove_phone'] ) ); ?> />
							<span class="meyvc-checkbox-content">
								<strong><?php esc_html_e( 'Remove Phone Number', 'meyvora-convert' ); ?></strong>
								<span><?php esc_html_e( 'Unless needed for shipping/delivery', 'meyvora-convert' ); ?></span>
							</span>
						</label>
					</div>
				</div>
				<div class="meyvc-field meyvc-col-12">
					<span class="meyvc-field__label"><?php esc_html_e( 'Order Fields', 'meyvora-convert' ); ?></span>
					<div class="meyvc-field__control">
						<label class="meyvc-checkbox-card">
							<input type="checkbox" name="remove_order_notes" value="1"
								<?php checked( ! empty( $checkout['remove_order_notes'] ) ); ?> />
							<span class="meyvc-checkbox-content">
								<strong><?php esc_html_e( 'Remove Order Notes', 'meyvora-convert' ); ?></strong>
								<span><?php esc_html_e( 'Rarely used, adds visual clutter', 'meyvora-convert' ); ?></span>
							</span>
						</label>
					</div>
				</div>
			</div>
		</div>

		<!-- UX Improvements Section -->
		<div class="meyvc-settings-section">
			<h2>
				<?php echo wp_kses( MEYVC_Icons::svg( 'settings', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?>

				<?php esc_html_e( 'UX Improvements', 'meyvora-convert' ); ?>
			</h2>

			<div class="meyvc-fields-grid meyvc-fields-grid--1col">
				<div class="meyvc-field meyvc-col-12">
					<div class="meyvc-field__control">
						<label>
							<input type="checkbox" name="move_coupon" value="1"
								<?php checked( ! empty( $checkout['move_coupon_to_top'] ) ); ?> />
							<?php esc_html_e( 'Move coupon field above order summary', 'meyvora-convert' ); ?>
						</label>
					</div>
					<span class="meyvc-help"><?php esc_html_e( 'Makes it easier for customers with coupons to apply them.', 'meyvora-convert' ); ?></span>
				</div>
				<div class="meyvc-field meyvc-col-12">
					<div class="meyvc-field__control">
						<label>
							<input type="checkbox" name="autofocus" value="1"
								<?php checked( ! empty( $checkout['autofocus_first_field'] ) ); ?> />
							<?php esc_html_e( 'Auto-focus first empty field on page load', 'meyvora-convert' ); ?>
						</label>
					</div>
					<span class="meyvc-help"><?php esc_html_e( 'Saves one click and signals where to start.', 'meyvora-convert' ); ?></span>
				</div>
				<div class="meyvc-field meyvc-col-12">
					<div class="meyvc-field__control">
						<label>
							<input type="checkbox" name="inline_validation" value="1"
								<?php checked( ! empty( $checkout['inline_validation'] ) ); ?> />
							<?php esc_html_e( 'Show inline validation (green checkmarks)', 'meyvora-convert' ); ?>
						</label>
					</div>
					<span class="meyvc-help"><?php esc_html_e( 'Gives positive feedback as users complete fields correctly.', 'meyvora-convert' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Trust Elements Section -->
		<div class="meyvc-settings-section">
			<h2>
				<?php echo wp_kses( MEYVC_Icons::svg( 'shield', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?>

				<?php esc_html_e( 'Trust & Security Elements', 'meyvora-convert' ); ?>
			</h2>
			<p class="meyvc-section-description">
				<?php esc_html_e( 'Reassure customers at the moment they\'re about to enter payment info.', 'meyvora-convert' ); ?>
			</p>

			<div class="meyvc-fields-grid meyvc-fields-grid--1col">
				<div class="meyvc-field meyvc-col-12">
					<div class="meyvc-field__control">
						<label>
							<input type="checkbox" name="show_trust" value="1"
								<?php checked( ! empty( $checkout['show_trust_message'] ) ); ?> />
							<?php esc_html_e( 'Show trust message near payment section', 'meyvora-convert' ); ?>
						</label>
					</div>
					<div class="meyvc-field__control meyvc-mt-1">
						<input type="text" name="trust_message" id="checkout_trust_message"
							value="<?php echo esc_attr( $checkout['trust_message_text'] ); ?>"
							class="large-text"
							placeholder="<?php esc_attr_e( 'Secure checkout - Your data is protected', 'meyvora-convert' ); ?>" />
					</div>
				</div>
				<div class="meyvc-field meyvc-col-12">
					<div class="meyvc-field__control">
						<label>
							<input type="checkbox" name="show_secure_badge" value="1"
								<?php checked( ! empty( $checkout['show_secure_badge'] ) ); ?> />
							<?php esc_html_e( 'Show SSL/Secure checkout badge', 'meyvora-convert' ); ?>
						</label>
					</div>
				</div>
				<div class="meyvc-field meyvc-col-12">
					<div class="meyvc-field__control">
						<label>
							<input type="checkbox" name="show_guarantee" value="1"
								<?php checked( ! empty( $checkout['show_guarantee'] ) ); ?> />
							<?php esc_html_e( 'Show money-back guarantee message', 'meyvora-convert' ); ?>
						</label>
					</div>
					<div class="meyvc-field__control meyvc-mt-1">
						<input type="text" name="guarantee_text" id="checkout_guarantee_text"
							value="<?php echo esc_attr( $checkout['guarantee_text'] ); ?>"
							class="large-text"
							placeholder="<?php esc_attr_e( '30-day money-back guarantee', 'meyvora-convert' ); ?>" />
					</div>
				</div>
			</div>
		</div>

		<div class="meyvc-settings-section">
			<h2><?php esc_html_e( 'Checkout intelligence', 'meyvora-convert' ); ?></h2>
			<p class="meyvc-section-description"><?php esc_html_e( 'Reduce friction and set expectations without replacing your payment gateways.', 'meyvora-convert' ); ?></p>
			<div class="meyvc-fields-grid meyvc-fields-grid--1col">
				<label class="meyvc-checkbox-card">
					<input type="checkbox" name="prefill_from_last_order" value="1" <?php checked( ! empty( $checkout['prefill_from_last_order'] ) ); ?> />
					<span class="meyvc-checkbox-content"><strong><?php esc_html_e( 'Prefill checkout from last order', 'meyvora-convert' ); ?></strong>
						<span><?php esc_html_e( 'For logged-in customers, reuse address fields when the field is still empty.', 'meyvora-convert' ); ?></span></span>
				</label>
				<label class="meyvc-checkbox-card">
					<input type="checkbox" name="show_express_checkout_prompt" value="1" <?php checked( ! empty( $checkout['show_express_checkout_prompt'] ) ); ?> />
					<span class="meyvc-checkbox-content"><strong><?php esc_html_e( 'Express checkout prompt', 'meyvora-convert' ); ?></strong></span>
				</label>
				<div class="meyvc-field meyvc-col-12">
					<textarea name="express_prompt_text" class="large-text" rows="2"><?php echo esc_textarea( (string) ( $checkout['express_prompt_text'] ?? '' ) ); ?></textarea>
				</div>
				<label class="meyvc-checkbox-card">
					<input type="checkbox" name="show_progress_steps" value="1" <?php checked( ! empty( $checkout['show_progress_steps'] ) ); ?> />
					<span class="meyvc-checkbox-content"><strong><?php esc_html_e( 'Show checkout progress steps (Cart → Details → Payment → Confirm)', 'meyvora-convert' ); ?></strong></span>
				</label>
			</div>
		</div>

		<div class="meyvc-settings-section">
			<h2><?php esc_html_e( 'Post-purchase upsell', 'meyvora-convert' ); ?></h2>
			<p class="meyvc-section-description"><?php esc_html_e( 'Thank-you page: suggest products and optional one-time coupon.', 'meyvora-convert' ); ?></p>
			<label class="meyvc-checkbox-card">
				<input type="checkbox" name="post_purchase_enabled" value="1" <?php checked( ! empty( $checkout['post_purchase_enabled'] ) ); ?> />
				<span class="meyvc-checkbox-content"><strong><?php esc_html_e( 'Enable thank-you page upsell', 'meyvora-convert' ); ?></strong></span>
			</label>
			<div class="meyvc-field meyvc-col-12 meyvc-mt-2">
				<label class="meyvc-field__label"><?php esc_html_e( 'Product IDs (comma-separated)', 'meyvora-convert' ); ?></label>
				<input type="text" name="post_purchase_product_ids" class="regular-text" value="<?php echo esc_attr( (string) ( $checkout['post_purchase_product_ids'] ?? '' ) ); ?>" placeholder="123, 456" />
			</div>
			<div class="meyvc-field meyvc-col-12">
				<label class="meyvc-field__label"><?php esc_html_e( 'Coupon discount (%)', 'meyvora-convert' ); ?></label>
				<input type="number" name="post_purchase_discount_percent" class="small-text" min="0" max="90" step="1" value="<?php echo esc_attr( (string) ( $checkout['post_purchase_discount_percent'] ?? 0 ) ); ?>" />
			</div>
			<div class="meyvc-field meyvc-col-12">
				<label class="meyvc-field__label"><?php esc_html_e( 'Headline', 'meyvora-convert' ); ?></label>
				<input type="text" name="post_purchase_headline" class="large-text" value="<?php echo esc_attr( (string) ( $checkout['post_purchase_headline'] ?? '' ) ); ?>" />
			</div>
			<div class="meyvc-field meyvc-col-12">
				<label class="meyvc-field__label"><?php esc_html_e( 'Subheading', 'meyvora-convert' ); ?></label>
				<input type="text" name="post_purchase_subhead" class="large-text" value="<?php echo esc_attr( (string) ( $checkout['post_purchase_subhead'] ?? '' ) ); ?>" />
			</div>
		</div>

		<?php submit_button( __( 'Save Checkout Settings', 'meyvora-convert' ), 'primary', 'meyvc_save_checkout', false, array( 'class' => 'meyvc-ui-btn-primary' ) ); ?>

	</form>

