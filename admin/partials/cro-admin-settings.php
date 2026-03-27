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

$cro_settings_tab = isset( $_GET['settings_tab'] ) ? sanitize_key( wp_unslash( $_GET['settings_tab'] ) ) : 'general';
if ( ! in_array( $cro_settings_tab, array( 'general', 'styles', 'analytics', 'ai' ), true ) ) {
	$cro_settings_tab = 'general';
}

// Handle save: each tab posts `cro_settings_tab` so we only update that section (avoids wiping other groups).
if ( isset( $_POST['cro_save_settings'] ) && ! isset( $_POST['cro_save_ai_settings'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cro_nonce'] ?? '' ) ), 'cro_save_settings' ) ) {
	$save_tab = isset( $_POST['cro_settings_tab'] ) ? sanitize_key( wp_unslash( $_POST['cro_settings_tab'] ) ) : '';
	if ( '' === $save_tab ) {
		// Back-compat if an old cached form omits the hidden field.
		if ( isset( $_POST['primary_color'] ) || isset( $_POST['button_radius'] ) || isset( $_POST['font_size_scale'] ) ) {
			$save_tab = 'styles';
		} elseif ( isset( $_POST['enable_analytics'] ) || isset( $_POST['analytics_anonymise_ip'] ) || isset( $_POST['data_retention_days'] ) || isset( $_POST['track_revenue'] ) || isset( $_POST['track_coupons'] ) ) {
			$save_tab = 'analytics';
		} else {
			$save_tab = 'general';
		}
	}
	if ( ! in_array( $save_tab, array( 'general', 'styles', 'analytics' ), true ) ) {
		$save_tab = 'general';
	}

	if ( 'general' === $save_tab ) {
		// Partial saves: sub-forms on this tab must not wipe other general / integration keys.
		if ( ! empty( $_POST['cro_save_advanced_only'] ) ) {
			$max_popups = isset( $_POST['max_popups_per_session'] ) ? absint( wp_unslash( $_POST['max_popups_per_session'] ) ) : 3;
			cro_settings()->set( 'general', 'max_popups_per_session', min( 100, $max_popups ) );

			$min_time = isset( $_POST['min_time_before_popup'] ) ? absint( wp_unslash( $_POST['min_time_before_popup'] ) ) : 3;
			cro_settings()->set( 'general', 'min_time_before_popup', min( 600, $min_time ) );

			$max_disc = isset( $_POST['max_discount_percent'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['max_discount_percent'] ) ) : 25;
			cro_settings()->set( 'general', 'max_discount_percent', max( 0, min( 100, $max_disc ) ) );

			$fb_id = isset( $_POST['fallback_campaign_id'] ) ? absint( wp_unslash( $_POST['fallback_campaign_id'] ) ) : 0;
			if ( $fb_id > 0 && class_exists( 'CRO_Campaign' ) ) {
				$row = CRO_Campaign::get( $fb_id );
				if ( ! $row || ( isset( $row['status'] ) && 'active' !== $row['status'] ) ) {
					$fb_id = 0;
				}
			}
			cro_settings()->set( 'general', 'fallback_campaign_id', $fb_id );
		} elseif ( ! empty( $_POST['cro_save_campaigns_only'] ) ) {
			if ( isset( $_POST['cro_settings']['campaigns']['intent_score_threshold'] ) ) {
				$val = absint( wp_unslash( $_POST['cro_settings']['campaigns']['intent_score_threshold'] ) );
				$val = max( 0, min( 100, $val ) );
				cro_settings()->set( 'campaigns', 'intent_score_threshold', $val );
			}
		} elseif ( ! empty( $_POST['cro_save_integrations_only'] ) ) {
			cro_settings()->set(
				'integrations',
				'webhook_url',
				esc_url_raw( wp_unslash( $_POST['webhook_url'] ?? '' ) )
			);
			cro_settings()->set(
				'integrations',
				'webhook_events',
				array_map( 'sanitize_key', (array) ( $_POST['webhook_events'] ?? array() ) )
			);
			cro_settings()->set( 'integrations', 'klaviyo_enabled', ! empty( $_POST['klaviyo_enabled'] ) );
			cro_settings()->set( 'integrations', 'klaviyo_list_id', sanitize_text_field( wp_unslash( $_POST['klaviyo_list_id'] ?? '' ) ) );
			if ( ! empty( $_POST['klaviyo_api_key'] ) && class_exists( 'CRO_Security' ) ) {
				$enc = CRO_Security::encrypt_secret( sanitize_text_field( wp_unslash( $_POST['klaviyo_api_key'] ) ) );
				if ( $enc !== '' ) {
					cro_settings()->set( 'integrations', 'klaviyo_api_key_enc', $enc );
				}
			}
			cro_settings()->set( 'integrations', 'mailchimp_enabled', ! empty( $_POST['mailchimp_enabled'] ) );
			cro_settings()->set( 'integrations', 'mailchimp_list_id', sanitize_text_field( wp_unslash( $_POST['mailchimp_list_id'] ?? '' ) ) );
			cro_settings()->set( 'integrations', 'mailchimp_double_optin', ! empty( $_POST['mailchimp_double_optin'] ) );
			cro_settings()->set( 'integrations', 'mailchimp_dc', sanitize_key( wp_unslash( $_POST['mailchimp_dc'] ?? '' ) ) );
			if ( ! empty( $_POST['mailchimp_api_key'] ) && class_exists( 'CRO_Security' ) ) {
				$enc = CRO_Security::encrypt_secret( sanitize_text_field( wp_unslash( $_POST['mailchimp_api_key'] ) ) );
				if ( $enc !== '' ) {
					cro_settings()->set( 'integrations', 'mailchimp_api_key_enc', $enc );
				}
			}
		} else {
			// Main “General” card only (plugin toggles, fonts, uninstall — not Campaigns / Integrations / Advanced).
			update_option( 'cro_remove_data_on_uninstall', ! empty( $_POST['remove_data_on_uninstall'] ) ? 'yes' : 'no' );
			cro_settings()->set( 'general', 'plugin_enabled', ! empty( $_POST['plugin_enabled'] ) );
			cro_settings()->set( 'general', 'campaigns_enabled', ! empty( $_POST['campaigns_enabled'] ) );
			cro_settings()->set( 'general', 'exclude_admins', ! empty( $_POST['exclude_admins'] ) );
			cro_settings()->set( 'general', 'require_cookie_consent', ! empty( $_POST['require_cookie_consent'] ) );
			cro_settings()->set( 'general', 'debug_mode', ! empty( $_POST['debug_mode'] ) );
			cro_settings()->set( 'general', 'blocks_debug_mode', ! empty( $_POST['blocks_debug_mode'] ) );
			cro_settings()->set( 'general', 'load_google_fonts', ! empty( $_POST['load_google_fonts'] ) ? 'yes' : 'no' );
		}
	}

	if ( 'styles' === $save_tab ) {
		cro_settings()->set( 'styles', 'primary_color', sanitize_hex_color( wp_unslash( $_POST['primary_color'] ?? '#333333' ) ) ?: '#333333' );
		cro_settings()->set( 'styles', 'secondary_color', sanitize_hex_color( wp_unslash( $_POST['secondary_color'] ?? '#555555' ) ) ?: '#555555' );
		cro_settings()->set( 'styles', 'button_radius', absint( wp_unslash( $_POST['button_radius'] ?? 8 ) ) );
		cro_settings()->set( 'styles', 'border_radius', absint( wp_unslash( $_POST['button_radius'] ?? 8 ) ) );
		cro_settings()->set( 'styles', 'spacing', absint( wp_unslash( $_POST['spacing'] ?? 8 ) ) );
		cro_settings()->set( 'styles', 'font_size_scale', (float) sanitize_text_field( wp_unslash( $_POST['font_size_scale'] ?? '1' ) ) );
		cro_settings()->set( 'styles', 'font_family', sanitize_text_field( wp_unslash( $_POST['font_family'] ?? 'inherit' ) ) );
		cro_settings()->set( 'styles', 'animation_speed', sanitize_text_field( wp_unslash( $_POST['animation_speed'] ?? 'normal' ) ) );
	}

	if ( 'analytics' === $save_tab ) {
		update_option( 'cro_enable_analytics', isset( $_POST['enable_analytics'] ) ? 1 : 0 );
		cro_settings()->set( 'analytics', 'anonymise_ip', ! empty( $_POST['analytics_anonymise_ip'] ) );
		cro_settings()->set( 'analytics', 'track_revenue', ! empty( $_POST['track_revenue'] ) );
		cro_settings()->set( 'analytics', 'track_coupons', ! empty( $_POST['track_coupons'] ) );
		cro_settings()->set( 'analytics', 'ab_winner_email_enabled', ! empty( $_POST['ab_winner_email_enabled'] ) );
		cro_settings()->set( 'analytics', 'ab_winner_notify_email', sanitize_email( wp_unslash( $_POST['ab_winner_notify_email'] ?? '' ) ) );
		$retention = isset( $_POST['data_retention_days'] ) ? absint( $_POST['data_retention_days'] ) : 90;
		cro_settings()->set( 'analytics', 'data_retention_days', max( 7, min( 730, $retention ) ) );
	}

	echo '<div class="cro-ui-notice cro-ui-toast-placeholder" role="status"><p>' . esc_html__( 'Settings saved.', 'meyvora-convert' ) . '</p></div>';
}

// AI settings (separate form; does not wipe API key when field left blank).
if ( isset( $_POST['cro_save_ai_settings'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cro_nonce'] ?? '' ) ), 'cro_save_settings' ) ) {
	$key_input = isset( $_POST['anthropic_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['anthropic_api_key'] ) ) : '';
	if ( '' !== $key_input ) {
		$encrypted = class_exists( 'CRO_Security' ) ? CRO_Security::encrypt_secret( $key_input ) : $key_input;
		cro_settings()->set( 'ai', 'anthropic_api_key_enc', $encrypted );
		cro_settings()->delete( 'ai', 'anthropic_api_key' );
		cro_settings()->set( 'ai', 'connection_verified', 'no' );
	}
	$ai_feature_keys = array( 'feature_copy', 'feature_emails', 'feature_insights', 'feature_offers', 'feature_ab', 'feature_chat' );
	foreach ( $ai_feature_keys as $fk ) {
		$post_key = 'ai_' . $fk;
		cro_settings()->set( 'ai', $fk, ! empty( $_POST[ $post_key ] ) ? 'yes' : 'no' );
	}
	echo '<div class="cro-ui-notice cro-ui-toast-placeholder" role="status"><p>' . esc_html__( 'Settings saved.', 'meyvora-convert' ) . '</p></div>';
}
?>

<nav class="cro-ui-nav cro-settings-inner-nav" aria-label="<?php esc_attr_e( 'Settings sections', 'meyvora-convert' ); ?>">
	<ul class="cro-ui-nav__list" role="list">
		<li class="cro-ui-nav__item">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cro-settings&settings_tab=general' ) ); ?>" class="cro-ui-nav__link<?php echo ( 'general' === $cro_settings_tab ) ? ' cro-ui-nav__link--active' : ''; ?>">
				<?php esc_html_e( 'General', 'meyvora-convert' ); ?>
			</a>
		</li>
		<li class="cro-ui-nav__item">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cro-settings&settings_tab=styles' ) ); ?>" class="cro-ui-nav__link<?php echo ( 'styles' === $cro_settings_tab ) ? ' cro-ui-nav__link--active' : ''; ?>">
				<?php esc_html_e( 'Styles', 'meyvora-convert' ); ?>
			</a>
		</li>
		<li class="cro-ui-nav__item">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cro-settings&settings_tab=analytics' ) ); ?>" class="cro-ui-nav__link<?php echo ( 'analytics' === $cro_settings_tab ) ? ' cro-ui-nav__link--active' : ''; ?>">
				<?php esc_html_e( 'Analytics', 'meyvora-convert' ); ?>
			</a>
		</li>
		<li class="cro-ui-nav__item">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cro-settings&settings_tab=ai' ) ); ?>" class="cro-ui-nav__link<?php echo ( 'ai' === $cro_settings_tab ) ? ' cro-ui-nav__link--active' : ''; ?>">
				<?php esc_html_e( 'AI', 'meyvora-convert' ); ?>
			</a>
		</li>
	</ul>
</nav>

<?php if ( 'general' === $cro_settings_tab ) : ?>
<?php
$cro_advanced_campaigns = array();
if ( class_exists( 'CRO_Campaign' ) ) {
	$cro_advanced_campaigns = CRO_Campaign::get_all( array( 'limit' => 500, 'status' => 'active' ) );
}
$cro_adv_max_popups = (int) cro_settings()->get( 'general', 'max_popups_per_session', 3 );
$cro_adv_min_time   = (int) cro_settings()->get( 'general', 'min_time_before_popup', 3 );
$cro_adv_max_disc   = (float) cro_settings()->get( 'general', 'max_discount_percent', 25 );
$cro_adv_fallback   = (int) cro_settings()->get( 'general', 'fallback_campaign_id', 0 );
?>
<div class="cro-card">
	<header class="cro-card__header"><h2><?php esc_html_e( 'General', 'meyvora-convert' ); ?></h2></header>
	<div class="cro-card__body">
		<form method="post" id="cro-settings-form">
			<?php wp_nonce_field( 'cro_save_settings', 'cro_nonce' ); ?>
			<input type="hidden" name="cro_save_settings" value="1" />
			<input type="hidden" name="cro_settings_tab" value="general" />

			<div class="cro-field">
				<label class="cro-field__label"><?php esc_html_e( 'Plugin', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<label>
						<input type="checkbox" name="plugin_enabled" value="1" <?php checked( cro_settings()->get( 'general', 'plugin_enabled', true ) ); ?> />
						<?php esc_html_e( 'Enable Meyvora Convert on the storefront', 'meyvora-convert' ); ?>
					</label>
					<p class="cro-help"><?php esc_html_e( 'When off, campaigns, boosters, and storefront features do not run.', 'meyvora-convert' ); ?></p>
				</div>
			</div>

			<div class="cro-field">
				<div class="cro-field__control">
					<label>
						<input type="checkbox" name="campaigns_enabled" value="1" <?php checked( cro_settings()->get( 'general', 'campaigns_enabled', true ) ); ?> />
						<?php esc_html_e( 'Enable campaigns (popups & exit intent)', 'meyvora-convert' ); ?>
					</label>
				</div>
			</div>

			<div class="cro-field">
				<div class="cro-field__control">
					<label>
						<input type="checkbox" name="exclude_admins" value="1" <?php checked( cro_settings()->get( 'general', 'exclude_admins', false ) ); ?> />
						<?php esc_html_e( 'Exclude administrators from campaigns and analytics tracking', 'meyvora-convert' ); ?>
					</label>
					<p class="cro-help"><?php esc_html_e( 'Useful while testing so your visits do not affect stats.', 'meyvora-convert' ); ?></p>
				</div>
			</div>

			<div class="cro-field">
				<div class="cro-field__control">
					<label>
						<input type="checkbox" id="cro-require-consent" name="require_cookie_consent" value="1" <?php checked( cro_settings()->get( 'general', 'require_cookie_consent', false ) ); ?> />
						<?php esc_html_e( 'Cookie consent required', 'meyvora-convert' ); ?>
					</label>
					<p class="cro-help"><?php esc_html_e( 'Only show popups and set cookies after visitor consent (GDPR/CCPA). Auto-detects Complianz, CookieYes, Borlabs Cookie, Cookiebot, and Moove GDPR.', 'meyvora-convert' ); ?></p>
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
				<div class="cro-field__control">
					<label>
						<input type="checkbox" name="load_google_fonts" value="1" <?php checked( cro_settings()->get( 'general', 'load_google_fonts', 'no' ), 'yes' ); ?> />
						<?php esc_html_e( 'Load DM Sans from Google Fonts (only needed if your popup font is set to DM Sans)', 'meyvora-convert' ); ?>
					</label>
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
	<header class="cro-card__header"><h2><?php esc_html_e( 'Campaigns', 'meyvora-convert' ); ?></h2></header>
	<div class="cro-card__body">
		<form method="post">
			<?php wp_nonce_field( 'cro_save_settings', 'cro_nonce' ); ?>
			<input type="hidden" name="cro_save_settings" value="1" />
			<input type="hidden" name="cro_save_campaigns_only" value="1" />
			<input type="hidden" name="cro_settings_tab" value="general" />
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
	<header class="cro-card__header"><h2><?php esc_html_e( 'Advanced', 'meyvora-convert' ); ?></h2></header>
	<div class="cro-card__body">
		<p class="cro-section-desc"><?php esc_html_e( 'Fine-tune session limits, timing, offer caps, and a global fallback campaign when no other campaign wins the decision.', 'meyvora-convert' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'cro_save_settings', 'cro_nonce' ); ?>
			<input type="hidden" name="cro_save_settings" value="1" />
			<input type="hidden" name="cro_save_advanced_only" value="1" />
			<input type="hidden" name="cro_settings_tab" value="general" />

			<div class="cro-field">
				<label class="cro-field__label" for="cro_max_popups_per_session"><?php esc_html_e( 'Max popups per session', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<input
						type="number"
						id="cro_max_popups_per_session"
						name="max_popups_per_session"
						value="<?php echo esc_attr( (string) $cro_adv_max_popups ); ?>"
						min="0"
						max="100"
						step="1"
						class="small-text"
					/>
					<p class="cro-help"><?php esc_html_e( 'Maximum number of campaign popups per visitor session. Use 0 for no limit. Default: 3.', 'meyvora-convert' ); ?></p>
				</div>
			</div>

			<div class="cro-field">
				<label class="cro-field__label" for="cro_min_time_before_popup"><?php esc_html_e( 'Minimum time on page (seconds)', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<input
						type="number"
						id="cro_min_time_before_popup"
						name="min_time_before_popup"
						value="<?php echo esc_attr( (string) $cro_adv_min_time ); ?>"
						min="0"
						max="600"
						step="1"
						class="small-text"
					/>
					<p class="cro-help"><?php esc_html_e( 'Seconds the visitor must be on the page before a popup may show (UX guard). Use 0 to allow immediately. Default: 3.', 'meyvora-convert' ); ?></p>
				</div>
			</div>

			<div class="cro-field">
				<label class="cro-field__label" for="cro_max_discount_percent"><?php esc_html_e( 'Maximum offer discount (%)', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<input
						type="number"
						id="cro_max_discount_percent"
						name="max_discount_percent"
						value="<?php echo esc_attr( (string) $cro_adv_max_disc ); ?>"
						min="0"
						max="100"
						step="0.5"
						class="small-text"
					/>
					<p class="cro-help"><?php esc_html_e( 'Upper bound for dynamic offer discounts relative to cart total. Default: 25.', 'meyvora-convert' ); ?></p>
				</div>
			</div>

			<div class="cro-field">
				<label class="cro-field__label" for="cro_fallback_campaign_id"><?php esc_html_e( 'Global fallback campaign', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<select id="cro_fallback_campaign_id" name="fallback_campaign_id" class="regular-text">
						<option value="0" <?php selected( $cro_adv_fallback, 0 ); ?>><?php esc_html_e( '— None —', 'meyvora-convert' ); ?></option>
						<?php
						foreach ( $cro_advanced_campaigns as $cro_ac_row ) {
							$cid = isset( $cro_ac_row['id'] ) ? (int) $cro_ac_row['id'] : 0;
							if ( $cid <= 0 ) {
								continue;
							}
							$cname = isset( $cro_ac_row['name'] ) ? (string) $cro_ac_row['name'] : '#' . $cid;
							echo '<option value="' . esc_attr( (string) $cid ) . '" ' . selected( $cro_adv_fallback, $cid, false ) . '>' . esc_html( $cname ) . '</option>';
						}
						?>
					</select>
					<p class="cro-help"><?php esc_html_e( 'If the engine would show no campaign, this active campaign may be used as a last resort (separate from per-campaign “after dismiss” fallbacks).', 'meyvora-convert' ); ?></p>
				</div>
			</div>

			<?php submit_button( __( 'Save Advanced Settings', 'meyvora-convert' ) ); ?>
		</form>
	</div>
</div>

<div class="cro-card">
	<header class="cro-card__header"><h2><?php esc_html_e( 'Onboarding', 'meyvora-convert' ); ?></h2></header>
	<div class="cro-card__body">
		<div class="cro-field">
			<label class="cro-field__label"><?php esc_html_e( 'Restart setup wizard', 'meyvora-convert' ); ?></label>
			<div class="cro-field__control">
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cro-settings&action=cro_restart_onboarding' ), 'cro_restart_onboarding' ) ); ?>" class="button"><?php esc_html_e( 'Restart setup wizard', 'meyvora-convert' ); ?></a>
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
			<input type="hidden" name="cro_save_integrations_only" value="1" />
			<input type="hidden" name="cro_settings_tab" value="general" />

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

			<h3 class="cro-mt-3"><?php esc_html_e( 'Klaviyo', 'meyvora-convert' ); ?></h3>
			<div class="cro-field">
				<label><input type="checkbox" name="klaviyo_enabled" value="1" <?php checked( cro_settings()->get( 'integrations', 'klaviyo_enabled', false ) ); ?> /> <?php esc_html_e( 'Subscribe emails to Klaviyo when captured or abandoned cart is stored', 'meyvora-convert' ); ?></label>
			</div>
			<div class="cro-field">
				<label class="cro-field__label" for="cro-klaviyo-list"><?php esc_html_e( 'List ID', 'meyvora-convert' ); ?></label>
				<input type="text" id="cro-klaviyo-list" name="klaviyo_list_id" class="regular-text" value="<?php echo esc_attr( cro_settings()->get( 'integrations', 'klaviyo_list_id', '' ) ); ?>" />
			</div>
			<div class="cro-field">
				<label class="cro-field__label" for="cro-klaviyo-key"><?php esc_html_e( 'Private API key', 'meyvora-convert' ); ?></label>
				<input type="password" id="cro-klaviyo-key" name="klaviyo_api_key" class="regular-text" autocomplete="new-password" placeholder="<?php esc_attr_e( 'Leave blank to keep existing', 'meyvora-convert' ); ?>" />
				<p class="cro-help"><?php esc_html_e( 'Stored encrypted. Uses Klaviyo REST API (revision 2024-10-15).', 'meyvora-convert' ); ?></p>
			</div>

			<h3 class="cro-mt-3"><?php esc_html_e( 'Mailchimp', 'meyvora-convert' ); ?></h3>
			<div class="cro-field">
				<label><input type="checkbox" name="mailchimp_enabled" value="1" <?php checked( cro_settings()->get( 'integrations', 'mailchimp_enabled', false ) ); ?> /> <?php esc_html_e( 'Subscribe emails via Mailchimp Marketing API', 'meyvora-convert' ); ?></label>
			</div>
			<div class="cro-field">
				<label class="cro-field__label" for="cro-mc-list"><?php esc_html_e( 'Audience / List ID', 'meyvora-convert' ); ?></label>
				<input type="text" id="cro-mc-list" name="mailchimp_list_id" class="regular-text" value="<?php echo esc_attr( cro_settings()->get( 'integrations', 'mailchimp_list_id', '' ) ); ?>" />
			</div>
			<div class="cro-field">
				<label class="cro-field__label" for="cro-mc-double-optin"><?php esc_html_e( 'Double opt-in', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<label>
						<input type="checkbox" id="cro-mc-double-optin" name="mailchimp_double_optin" value="1"
							<?php checked( cro_settings()->get( 'integrations', 'mailchimp_double_optin', false ) ); ?> />
						<?php esc_html_e( 'Send a confirmation email before subscribing (recommended for GDPR compliance)', 'meyvora-convert' ); ?>
					</label>
					<p class="cro-help"><?php esc_html_e( 'When enabled, new subscribers receive a confirmation email and are added as "pending" until they confirm.', 'meyvora-convert' ); ?></p>
				</div>
			</div>
			<div class="cro-field">
				<label class="cro-field__label" for="cro-mc-dc"><?php esc_html_e( 'Data center (optional if key ends with -us1)', 'meyvora-convert' ); ?></label>
				<input type="text" id="cro-mc-dc" name="mailchimp_dc" class="small-text" value="<?php echo esc_attr( cro_settings()->get( 'integrations', 'mailchimp_dc', '' ) ); ?>" placeholder="us1" />
			</div>
			<div class="cro-field">
				<label class="cro-field__label" for="cro-mc-key"><?php esc_html_e( 'API key', 'meyvora-convert' ); ?></label>
				<input type="password" id="cro-mc-key" name="mailchimp_api_key" class="regular-text" autocomplete="new-password" placeholder="<?php esc_attr_e( 'Leave blank to keep existing', 'meyvora-convert' ); ?>" />
			</div>

			<?php submit_button( __( 'Save Integration Settings', 'meyvora-convert' ) ); ?>
		</form>
	</div>
</div>
<?php endif; ?>

<?php if ( 'styles' === $cro_settings_tab ) : ?>
<div class="cro-card">
	<header class="cro-card__header"><h2><?php esc_html_e( 'Brand Styles', 'meyvora-convert' ); ?></h2></header>
	<div class="cro-card__body">
		<p class="cro-section-desc"><?php esc_html_e( 'Global styles for popups, shipping bar, sticky cart, and trust badges. Override per campaign in the campaign editor.', 'meyvora-convert' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'cro_save_settings', 'cro_nonce' ); ?>
			<input type="hidden" name="cro_save_settings" value="1" />
			<input type="hidden" name="cro_settings_tab" value="styles" />

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
				<div class="cro-field__control cro-field__control--flex">
					<input type="number" id="cro-button-radius" name="button_radius" value="<?php echo esc_attr( (string) ( cro_settings()->get( 'styles', 'button_radius', 8 ) ?: cro_settings()->get( 'styles', 'border_radius', 8 ) ) ); ?>" min="0" max="30" class="small-text" /> px
					<p class="cro-help"><?php esc_html_e( 'Border radius for buttons and pill elements.', 'meyvora-convert' ); ?></p>
				</div>
			</div>

			<div class="cro-field">
				<label class="cro-field__label" for="cro-spacing"><?php esc_html_e( 'Spacing', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control cro-field__control--flex">
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
<?php endif; ?>

<?php if ( 'analytics' === $cro_settings_tab ) : ?>
<div class="cro-card">
	<header class="cro-card__header"><h2><?php esc_html_e( 'Analytics', 'meyvora-convert' ); ?></h2></header>
	<div class="cro-card__body">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=cro-settings&settings_tab=analytics' ) ); ?>">
			<?php wp_nonce_field( 'cro_save_settings', 'cro_nonce' ); ?>
			<input type="hidden" name="cro_save_settings" value="1" />
			<input type="hidden" name="cro_settings_tab" value="analytics" />

			<div class="cro-field">
				<div class="cro-field__control">
					<label>
						<input type="checkbox" id="enable_analytics" name="enable_analytics" value="1" <?php checked( get_option( 'cro_enable_analytics', true ), 1 ); ?> />
						<?php esc_html_e( 'Enable analytics tracking', 'meyvora-convert' ); ?>
					</label>
				</div>
			</div>

			<div class="cro-field">
				<div class="cro-field__control">
					<label>
						<input type="checkbox" id="cro-anonymise-ip" name="analytics_anonymise_ip" value="1" <?php checked( cro_settings()->get( 'analytics', 'anonymise_ip', false ) ); ?> />
						<?php esc_html_e( 'Anonymise IP addresses', 'meyvora-convert' ); ?>
					</label>
					<p class="cro-help"><?php esc_html_e( 'Truncate the last octet of IPv4 (or mask IPv6) before storing in event metadata. Recommended for GDPR.', 'meyvora-convert' ); ?></p>
				</div>
			</div>

			<div class="cro-field">
				<div class="cro-field__control">
					<label>
						<input type="checkbox" name="track_revenue" value="1" <?php checked( cro_settings()->get( 'analytics', 'track_revenue', true ) ); ?> />
						<?php esc_html_e( 'Attribute order revenue to campaigns (thank-you page)', 'meyvora-convert' ); ?>
					</label>
				</div>
			</div>

			<div class="cro-field">
				<div class="cro-field__control">
					<label>
						<input type="checkbox" name="track_coupons" value="1" <?php checked( cro_settings()->get( 'analytics', 'track_coupons', true ) ); ?> />
						<?php esc_html_e( 'Use campaign coupon codes when matching orders to conversions', 'meyvora-convert' ); ?>
					</label>
				</div>
			</div>

			<div class="cro-field">
				<div class="cro-field__control">
					<label>
						<input type="checkbox" name="ab_winner_email_enabled" value="1" <?php checked( cro_settings()->get( 'analytics', 'ab_winner_email_enabled', true ) ); ?> />
						<?php esc_html_e( 'Email the shop when an A/B winner is applied to the live campaign', 'meyvora-convert' ); ?>
					</label>
				</div>
			</div>
			<div class="cro-field">
				<label class="cro-field__label" for="cro-ab-winner-email"><?php esc_html_e( 'Notify email (optional)', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<input type="email" id="cro-ab-winner-email" name="ab_winner_notify_email" class="regular-text" value="<?php echo esc_attr( cro_settings()->get( 'analytics', 'ab_winner_notify_email', '' ) ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email', '' ) ); ?>" />
					<p class="cro-help"><?php esc_html_e( 'Defaults to the WordPress admin email if empty.', 'meyvora-convert' ); ?></p>
				</div>
			</div>

			<div class="cro-field">
				<label class="cro-field__label" for="cro-data-retention-days"><?php esc_html_e( 'Event data retention', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control cro-field__control--flex">
					<input type="number" id="cro-data-retention-days" name="data_retention_days" class="small-text" min="7" max="730" step="1"
						value="<?php echo esc_attr( (string) cro_settings()->get( 'analytics', 'data_retention_days', 90 ) ); ?>" />
					<span class="cro-help"><?php esc_html_e( 'days (older rows are removed by the daily cleanup task)', 'meyvora-convert' ); ?></span>
				</div>
			</div>

			<?php submit_button( __( 'Save Analytics Settings', 'meyvora-convert' ) ); ?>
		</form>
	</div>
</div>
<?php endif; ?>

<?php if ( 'ai' === $cro_settings_tab ) : ?>
	<?php
	$ai_has_key        = class_exists( 'CRO_AI_Client' ) && CRO_AI_Client::is_configured();
	$ai_feature_default = $ai_has_key ? 'yes' : 'no';
	$ai_ok             = $ai_has_key && ( 'yes' === cro_settings()->get( 'ai', 'connection_verified', 'no' ) );
	?>
<div class="cro-card" id="cro-settings-ai">
	<header class="cro-card__header"><h2><?php esc_html_e( 'AI', 'meyvora-convert' ); ?></h2></header>
	<div class="cro-card__body">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=cro-settings&settings_tab=ai' ) ); ?>">
			<?php wp_nonce_field( 'cro_save_settings', 'cro_nonce' ); ?>
			<input type="hidden" name="cro_save_settings" value="1" />
			<input type="hidden" name="cro_save_ai_settings" value="1" />

			<div class="cro-field">
				<label class="cro-field__label" for="cro-ai-api-key"><?php esc_html_e( 'Anthropic API Key', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control cro-field__control--flex cro-ai-api-key-row">
					<input type="password" name="anthropic_api_key" id="cro-ai-api-key" class="regular-text" value="" autocomplete="off" spellcheck="false"
						placeholder="<?php echo $ai_has_key ? esc_attr__( 'Saved key on file — enter new key to replace', 'meyvora-convert' ) : esc_attr__( 'sk-ant-api03-...', 'meyvora-convert' ); ?>" />
					<button type="button" class="button" id="cro-ai-api-key-toggle" aria-pressed="false"><?php esc_html_e( 'Show', 'meyvora-convert' ); ?></button>
					<span class="cro-ai-connection-status" role="status">
						<?php if ( $ai_ok ) : ?>
							<span class="cro-ai-connection-status__icon cro-ai-connection-status__icon--ok dashicons dashicons-yes" aria-hidden="true"></span>
							<span class="screen-reader-text"><?php esc_html_e( 'API key saved and connection verified', 'meyvora-convert' ); ?></span>
						<?php else : ?>
							<span class="cro-ai-connection-status__icon cro-ai-connection-status__icon--pending dashicons dashicons-minus" aria-hidden="true"></span>
							<span class="screen-reader-text"><?php esc_html_e( 'Not configured or not verified', 'meyvora-convert' ); ?></span>
						<?php endif; ?>
					</span>
					<button type="button" class="button button-secondary" id="cro-ai-test-connection"><?php esc_html_e( 'Test connection', 'meyvora-convert' ); ?></button>
				</div>
				<p class="cro-help" id="cro-ai-test-feedback" hidden></p>
				<p class="cro-help"><?php esc_html_e( 'The key is stored in the database and is never shown after saving. Leave the field blank to keep the current key.', 'meyvora-convert' ); ?></p>
			</div>

			<div class="cro-field">
				<label class="cro-field__label"><?php esc_html_e( 'AI features', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<label style="display:block; margin-bottom:6px;">
						<input type="checkbox" name="ai_feature_copy" value="1" <?php checked( 'yes', cro_settings()->get( 'ai', 'feature_copy', $ai_feature_default ) ); ?> />
						<?php esc_html_e( 'AI Copy Generator', 'meyvora-convert' ); ?>
					</label>
					<label style="display:block; margin-bottom:6px;">
						<input type="checkbox" name="ai_feature_emails" value="1" <?php checked( 'yes', cro_settings()->get( 'ai', 'feature_emails', $ai_feature_default ) ); ?> />
						<?php esc_html_e( 'AI Abandoned Cart Emails', 'meyvora-convert' ); ?>
					</label>
					<label style="display:block; margin-bottom:6px;">
						<input type="checkbox" name="ai_feature_insights" value="1" <?php checked( 'yes', cro_settings()->get( 'ai', 'feature_insights', $ai_feature_default ) ); ?> />
						<?php esc_html_e( 'AI Insights Analyst', 'meyvora-convert' ); ?>
					</label>
					<label style="display:block; margin-bottom:6px;">
						<input type="checkbox" name="ai_feature_offers" value="1" <?php checked( 'yes', cro_settings()->get( 'ai', 'feature_offers', $ai_feature_default ) ); ?> />
						<?php esc_html_e( 'AI Offer Suggester', 'meyvora-convert' ); ?>
					</label>
					<label style="display:block; margin-bottom:6px;">
						<input type="checkbox" name="ai_feature_ab" value="1" <?php checked( 'yes', cro_settings()->get( 'ai', 'feature_ab', $ai_feature_default ) ); ?> />
						<?php esc_html_e( 'AI A/B Test Hypotheses', 'meyvora-convert' ); ?>
					</label>
					<label style="display:block; margin-bottom:6px;">
						<input type="checkbox" name="ai_feature_chat" value="1" <?php checked( 'yes', cro_settings()->get( 'ai', 'feature_chat', $ai_feature_default ) ); ?> />
						<?php esc_html_e( 'Admin AI Chat', 'meyvora-convert' ); ?>
					</label>
				</div>
			</div>

			<p class="cro-section-desc"><?php esc_html_e( 'AI features use the Anthropic Claude API. Usage is billed by Anthropic to your API account.', 'meyvora-convert' ); ?></p>

			<?php submit_button( __( 'Save AI Settings', 'meyvora-convert' ) ); ?>
		</form>
	</div>
</div>
<?php endif; ?>
