<?php
/**
 * Admin settings page
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Handle save
if ( isset( $_POST['cro_save_settings'] ) && wp_verify_nonce( $_POST['cro_nonce'], 'cro_save_settings' ) ) {
	update_option( 'cro_enable_analytics', isset( $_POST['enable_analytics'] ) ? 1 : 0 );
	update_option( 'cro_remove_data_on_uninstall', ! empty( $_POST['remove_data_on_uninstall'] ) ? 'yes' : 'no' );
	cro_settings()->set( 'general', 'debug_mode', ! empty( $_POST['debug_mode'] ) );
	cro_settings()->set( 'general', 'blocks_debug_mode', ! empty( $_POST['blocks_debug_mode'] ) );

	// Save Brand Styles
	cro_settings()->set( 'styles', 'primary_color', sanitize_hex_color( $_POST['primary_color'] ?? '#333333' ) ?: '#333333' );
	cro_settings()->set( 'styles', 'secondary_color', sanitize_hex_color( $_POST['secondary_color'] ?? '#555555' ) ?: '#555555' );
	cro_settings()->set( 'styles', 'button_radius', absint( $_POST['button_radius'] ?? 8 ) );
	cro_settings()->set( 'styles', 'border_radius', absint( $_POST['button_radius'] ?? 8 ) );
	cro_settings()->set( 'styles', 'spacing', absint( $_POST['spacing'] ?? 8 ) );
	cro_settings()->set( 'styles', 'font_size_scale', (float) ( $_POST['font_size_scale'] ?? 1 ) );
	cro_settings()->set( 'styles', 'font_family', sanitize_text_field( $_POST['font_family'] ?? 'inherit' ) );
	cro_settings()->set( 'styles', 'animation_speed', sanitize_text_field( $_POST['animation_speed'] ?? 'normal' ) );

	// Campaigns: exit intent sensitivity threshold (0–100).
	if ( isset( $_POST['cro_settings']['campaigns']['intent_score_threshold'] ) ) {
		$val = absint( $_POST['cro_settings']['campaigns']['intent_score_threshold'] );
		$val = max( 0, min( 100, $val ) );
		cro_settings()->set( 'campaigns', 'intent_score_threshold', $val );
	}

	cro_settings()->set( 'integrations', 'webhook_url',
		esc_url_raw( wp_unslash( $_POST['webhook_url'] ?? '' ) ) );
	cro_settings()->set( 'integrations', 'webhook_events',
		array_map( 'sanitize_key', (array) ( $_POST['webhook_events'] ?? array() ) ) );

	echo '<div class="cro-ui-notice cro-ui-toast-placeholder" role="status"><p>' . esc_html__( 'Settings saved.', 'meyvora-convert' ) . '</p></div>';
}
?>

<div class="cro-card">
	<header class="cro-card__header"><h2><?php esc_html_e( 'General', 'meyvora-convert' ); ?></h2></header>
	<div class="cro-card__body">
		<form method="post" id="cro-settings-form">
			<?php wp_nonce_field( 'cro_save_settings', 'cro_nonce' ); ?>

			<div class="cro-field">
				<div class="cro-field__control">
					<label>
						<input type="checkbox" id="enable_analytics" name="enable_analytics" value="1" <?php checked( get_option( 'cro_enable_analytics', true ), 1 ); ?> />
						<?php esc_html_e( 'Enable analytics tracking', 'meyvora-convert' ); ?>
					</label>
				</div>
			</div>

			<div class="cro-field">
				<label class="cro-field__label"><?php esc_html_e( 'Debug Mode', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<label>
						<input type="checkbox" name="debug_mode" value="1" <?php checked( cro_settings()->get( 'general', 'debug_mode' ) ); ?> />
						<?php esc_html_e( 'Enable debug mode (admin only)', 'meyvora-convert' ); ?>
					</label>
					<p class="cro-help"><?php esc_html_e( 'Shows why campaigns did or didn\'t trigger. Only visible to admins.', 'meyvora-convert' ); ?></p>
				</div>
			</div>

			<div class="cro-field">
				<label class="cro-field__label"><?php esc_html_e( 'Blocks debug', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<label>
						<input type="checkbox" name="blocks_debug_mode" value="1" <?php checked( cro_settings()->get( 'general', 'blocks_debug_mode' ) ); ?> />
						<?php esc_html_e( 'Enable Blocks debug mode', 'meyvora-convert' ); ?>
					</label>
					<p class="cro-help"><?php esc_html_e( 'Shows a fixed badge on Cart/Checkout block pages and logs settings to the console so you can confirm the extension is loaded.', 'meyvora-convert' ); ?></p>
				</div>
			</div>

			<div class="cro-field">
				<label class="cro-field__label"><?php esc_html_e( 'Uninstall', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<label>
						<input type="checkbox" name="remove_data_on_uninstall" value="1" <?php checked( get_option( 'cro_remove_data_on_uninstall', 'no' ), 'yes' ); ?> />
						<?php esc_html_e( 'Remove all data when plugin is deleted', 'meyvora-convert' ); ?>
					</label>
					<p class="cro-help"><?php esc_html_e( 'If checked, deleting the plugin will remove campaigns, analytics, A/B tests, options, and transients. Leave unchecked to keep data.', 'meyvora-convert' ); ?></p>
				</div>
			</div>

			<?php submit_button( __( 'Save Settings', 'meyvora-convert' ) ); ?>
		</form>
	</div>
</div>

<div class="cro-card">
	<header class="cro-card__header"><h2><?php esc_html_e( 'Brand Styles', 'meyvora-convert' ); ?></h2></header>
	<div class="cro-card__body">
		<p class="cro-section-desc"><?php esc_html_e( 'Global styles for popups, shipping bar, sticky cart, and trust badges. Override per campaign in the campaign editor.', 'meyvora-convert' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'cro_save_settings', 'cro_nonce' ); ?>
			<input type="hidden" name="cro_save_settings" value="1" />

			<div class="cro-field">
				<label class="cro-field__label" for="cro-primary-color"><?php esc_html_e( 'Primary Color', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<input type="text" id="cro-primary-color" name="primary_color" value="<?php echo esc_attr( cro_settings()->get( 'styles', 'primary_color', '#333333' ) ); ?>" class="cro-color-picker" />
					<p class="cro-help"><?php esc_html_e( 'Buttons and primary accents.', 'meyvora-convert' ); ?></p>
				</div>
			</div>

			<div class="cro-field">
				<label class="cro-field__label" for="cro-secondary-color"><?php esc_html_e( 'Secondary Color', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<input type="text" id="cro-secondary-color" name="secondary_color" value="<?php echo esc_attr( cro_settings()->get( 'styles', 'secondary_color', '#555555' ) ); ?>" class="cro-color-picker" />
					<p class="cro-help"><?php esc_html_e( 'Secondary text and accents.', 'meyvora-convert' ); ?></p>
				</div>
			</div>

			<div class="cro-field">
				<label class="cro-field__label" for="cro-button-radius"><?php esc_html_e( 'Button Radius', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<input type="number" id="cro-button-radius" name="button_radius" value="<?php echo esc_attr( (string) ( cro_settings()->get( 'styles', 'button_radius', 8 ) ?: cro_settings()->get( 'styles', 'border_radius', 8 ) ) ); ?>" min="0" max="30" class="small-text" /> px
					<p class="cro-help"><?php esc_html_e( 'Border radius for buttons and pill elements.', 'meyvora-convert' ); ?></p>
				</div>
			</div>

			<div class="cro-field">
				<label class="cro-field__label" for="cro-spacing"><?php esc_html_e( 'Spacing', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<input type="number" id="cro-spacing" name="spacing" value="<?php echo esc_attr( (string) ( cro_settings()->get( 'styles', 'spacing', 8 ) ) ); ?>" min="2" max="32" class="small-text" /> px
					<p class="cro-help"><?php esc_html_e( 'Base spacing (padding, gaps) for CRO elements.', 'meyvora-convert' ); ?></p>
				</div>
			</div>

			<div class="cro-field">
				<label class="cro-field__label" for="cro-font-size-scale"><?php esc_html_e( 'Font Size Scale', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<select id="cro-font-size-scale" name="font_size_scale" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Normal (1×)', 'meyvora-convert' ); ?>">
						<option value="0.875" <?php selected( (string) ( cro_settings()->get( 'styles', 'font_size_scale', 1 ) ), '0.875' ); ?>><?php esc_html_e( 'Small (0.875×)', 'meyvora-convert' ); ?></option>
						<option value="1" <?php selected( (string) ( cro_settings()->get( 'styles', 'font_size_scale', 1 ) ), '1' ); ?>><?php esc_html_e( 'Normal (1×)', 'meyvora-convert' ); ?></option>
						<option value="1.125" <?php selected( (string) ( cro_settings()->get( 'styles', 'font_size_scale', 1 ) ), '1.125' ); ?>><?php esc_html_e( 'Large (1.125×)', 'meyvora-convert' ); ?></option>
						<option value="1.25" <?php selected( (string) ( cro_settings()->get( 'styles', 'font_size_scale', 1 ) ), '1.25' ); ?>><?php esc_html_e( 'Extra large (1.25×)', 'meyvora-convert' ); ?></option>
					</select>
					<p class="cro-help"><?php esc_html_e( 'Relative text size across CRO elements.', 'meyvora-convert' ); ?></p>
				</div>
			</div>

			<div class="cro-field">
				<label class="cro-field__label" for="cro-font-family"><?php esc_html_e( 'Font Family', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<select id="cro-font-family" name="font_family" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Inherit from theme', 'meyvora-convert' ); ?>">
						<option value="inherit" <?php selected( cro_settings()->get( 'styles', 'font_family', 'inherit' ), 'inherit' ); ?>><?php esc_html_e( 'Inherit from theme', 'meyvora-convert' ); ?></option>
						<option value="system" <?php selected( cro_settings()->get( 'styles', 'font_family' ), 'system' ); ?>><?php esc_html_e( 'System fonts', 'meyvora-convert' ); ?></option>
						<option value="arial" <?php selected( cro_settings()->get( 'styles', 'font_family' ), 'arial' ); ?>>Arial</option>
						<option value="georgia" <?php selected( cro_settings()->get( 'styles', 'font_family' ), 'georgia' ); ?>>Georgia</option>
					</select>
				</div>
			</div>

			<div class="cro-field">
				<label class="cro-field__label" for="cro-animation-speed"><?php esc_html_e( 'Animation Speed', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<select id="cro-animation-speed" name="animation_speed" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Normal (300ms)', 'meyvora-convert' ); ?>">
						<option value="fast" <?php selected( cro_settings()->get( 'styles', 'animation_speed', 'normal' ), 'fast' ); ?>><?php esc_html_e( 'Fast (150ms)', 'meyvora-convert' ); ?></option>
						<option value="normal" <?php selected( cro_settings()->get( 'styles', 'animation_speed', 'normal' ), 'normal' ); ?>><?php esc_html_e( 'Normal (300ms)', 'meyvora-convert' ); ?></option>
						<option value="slow" <?php selected( cro_settings()->get( 'styles', 'animation_speed', 'normal' ), 'slow' ); ?>><?php esc_html_e( 'Slow (500ms)', 'meyvora-convert' ); ?></option>
						<option value="none" <?php selected( cro_settings()->get( 'styles', 'animation_speed', 'normal' ), 'none' ); ?>><?php esc_html_e( 'No animations', 'meyvora-convert' ); ?></option>
					</select>
				</div>
			</div>

			<?php submit_button( __( 'Save Brand Styles', 'meyvora-convert' ) ); ?>
		</form>
	</div>
</div>

<div class="cro-card">
	<header class="cro-card__header"><h2><?php esc_html_e( 'Campaigns', 'meyvora-convert' ); ?></h2></header>
	<div class="cro-card__body">
		<form method="post">
			<?php wp_nonce_field( 'cro_save_settings', 'cro_nonce' ); ?>
			<input type="hidden" name="cro_save_settings" value="1" />
			<div class="cro-field">
				<label class="cro-field__label" for="cro_intent_score_threshold"><?php esc_html_e( 'Exit Intent Sensitivity', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<input
						type="number"
						id="cro_intent_score_threshold"
						name="cro_settings[campaigns][intent_score_threshold]"
						value="<?php echo esc_attr( (string) cro_settings()->get( 'campaigns', 'intent_score_threshold', 50 ) ); ?>"
						min="0"
						max="100"
						step="5"
						class="small-text"
					/>
					<p class="cro-help"><?php esc_html_e( 'Score threshold (0–100) before exit intent triggers. Lower = more sensitive. Default: 50.', 'meyvora-convert' ); ?></p>
				</div>
			</div>
			<?php submit_button( __( 'Save Settings', 'meyvora-convert' ) ); ?>
		</form>
	</div>
</div>

<div class="cro-card">
	<header class="cro-card__header"><h2><?php esc_html_e( 'Onboarding', 'meyvora-convert' ); ?></h2></header>
	<div class="cro-card__body">
		<div class="cro-field">
			<label class="cro-field__label"><?php esc_html_e( 'Restart onboarding', 'meyvora-convert' ); ?></label>
			<div class="cro-field__control">
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cro-settings&action=cro_restart_onboarding' ), 'cro_restart_onboarding' ) ); ?>" class="button"><?php esc_html_e( 'Restart onboarding', 'meyvora-convert' ); ?></a>
				<p class="cro-help"><?php esc_html_e( 'Show the setup checklist again after activation.', 'meyvora-convert' ); ?></p>
			</div>
		</div>
	</div>
</div>

<div class="cro-card">
	<header class="cro-card__header"><h2><?php esc_html_e( 'Import / Export', 'meyvora-convert' ); ?></h2></header>
	<div class="cro-card__body">
		<div class="cro-field">
			<label class="cro-field__label"><?php esc_html_e( 'Export', 'meyvora-convert' ); ?></label>
			<div class="cro-field__control">
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cro-settings&action=cro_export' ), 'cro_export' ) ); ?>" class="button"><?php esc_html_e( 'Export Campaigns & Settings', 'meyvora-convert' ); ?></a>
				<p class="cro-help"><?php esc_html_e( 'Download a JSON file with all campaigns and settings.', 'meyvora-convert' ); ?></p>
			</div>
		</div>
		<div class="cro-field">
			<label class="cro-field__label"><?php esc_html_e( 'Import', 'meyvora-convert' ); ?></label>
			<div class="cro-field__control">
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'cro_import', 'cro_import_nonce' ); ?>
					<input type="file" name="import_file" accept=".json" />
					<p class="cro-mt-2">
						<label>
							<input type="checkbox" name="import_settings" value="1" />
							<?php esc_html_e( 'Also import settings (will overwrite current settings)', 'meyvora-convert' ); ?>
						</label>
					</p>
					<button type="submit" name="cro_import" class="button"><?php esc_html_e( 'Import', 'meyvora-convert' ); ?></button>
				</form>
			</div>
		</div>
	</div>
</div>

<div class="cro-card">
	<header class="cro-card__header">
		<h2><?php esc_html_e( 'Integrations & Webhooks', 'meyvora-convert' ); ?></h2>
	</header>
	<div class="cro-card__body">
		<p class="cro-section-desc">
			<?php esc_html_e( 'Send CRO events to external tools. Compatible with Zapier, Klaviyo, Mailchimp, ActiveCampaign, or any custom HTTP endpoint.', 'meyvora-convert' ); ?>
		</p>
		<form method="post">
			<?php wp_nonce_field( 'cro_save_settings', 'cro_nonce' ); ?>
			<input type="hidden" name="cro_save_settings" value="1" />

			<div class="cro-field">
				<label class="cro-field__label" for="cro-webhook-url">
					<?php esc_html_e( 'Webhook URL', 'meyvora-convert' ); ?>
				</label>
				<div class="cro-field__control">
					<input type="url" id="cro-webhook-url" name="webhook_url" class="regular-text"
						   value="<?php echo esc_attr( cro_settings()->get( 'integrations', 'webhook_url', '' ) ); ?>"
						   placeholder="https://hooks.zapier.com/hooks/catch/..." />
					<p class="cro-help"><?php esc_html_e( 'A JSON POST request will be sent to this URL when selected events fire.', 'meyvora-convert' ); ?></p>
				</div>
			</div>

			<div class="cro-field">
				<label class="cro-field__label"><?php esc_html_e( 'Send on these events', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<?php
					$webhook_events = (array) cro_settings()->get( 'integrations', 'webhook_events', array( 'conversion' ) );
					$event_options  = array(
						'conversion'      => __( 'Campaign conversion (email submitted / coupon claimed)', 'meyvora-convert' ),
						'impression'      => __( 'Campaign impression (popup shown)', 'meyvora-convert' ),
						'coupon_applied'  => __( 'Coupon applied at checkout', 'meyvora-convert' ),
						'coupon_generated'=> __( 'Coupon generated for visitor', 'meyvora-convert' ),
					);
					foreach ( $event_options as $key => $label ) : ?>
						<label style="display:block; margin-bottom:6px;">
							<input type="checkbox" name="webhook_events[]"
								   value="<?php echo esc_attr( $key ); ?>"
								   <?php checked( in_array( $key, $webhook_events, true ) ); ?> />
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<?php submit_button( __( 'Save Integration Settings', 'meyvora-convert' ) ); ?>
		</form>
	</div>
</div>
