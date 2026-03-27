<?php
/**
 * Admin page: Abandoned Cart Emails – templates, delays, opt-in, preview, test send.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$settings = cro_settings();
$opts    = $settings->get_abandoned_cart_settings();
$currency_code = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';

$opts    = wp_parse_args( $opts, array(
	'enable_abandoned_cart_emails' => false,
	'require_opt_in'               => true,
	'email_1_delay_hours'          => 1,
	'email_2_delay_hours'          => 24,
	'email_3_delay_hours'          => 72,
	'high_value_threshold'         => 100,
	'email_subject_template'       => __( 'You left something in your cart – {store_name}', 'meyvora-convert' ),
	'email_body_template'          => '',
) );

$body_placeholder = $settings->get_abandoned_cart_email_body_default();
$body_value       = trim( (string) $opts['email_body_template'] ) !== '' ? $opts['email_body_template'] : $body_placeholder;

// Handle form save.
$nonce_ok = isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'cro_abandoned_cart_save' );
if ( isset( $_POST['cro_save_abandoned_cart'] ) && $nonce_ok ) {
	$settings->set( 'abandoned_cart', 'enable_abandoned_cart_emails', ! empty( $_POST['cro_abandoned_cart_enabled'] ) );
	$settings->set( 'abandoned_cart', 'require_opt_in', ! empty( $_POST['cro_abandoned_cart_require_opt_in'] ) );
	$settings->set( 'abandoned_cart', 'email_1_delay_hours', max( 0, absint( sanitize_text_field( wp_unslash( $_POST['cro_email_1_delay_hours'] ?? 1 ) ) ) ) );
	$settings->set( 'abandoned_cart', 'email_2_delay_hours', max( 0, absint( sanitize_text_field( wp_unslash( $_POST['cro_email_2_delay_hours'] ?? 24 ) ) ) ) );
	$settings->set( 'abandoned_cart', 'email_3_delay_hours', max( 0, absint( sanitize_text_field( wp_unslash( $_POST['cro_email_3_delay_hours'] ?? 72 ) ) ) ) );
	$settings->set( 'abandoned_cart', 'high_value_threshold', max( 0, (float) sanitize_text_field( wp_unslash( $_POST['cro_high_value_threshold'] ?? '100' ) ) ) );
	$settings->set( 'abandoned_cart', 'email_subject_template', isset( $_POST['cro_email_subject_template'] ) ? sanitize_text_field( wp_unslash( $_POST['cro_email_subject_template'] ) ) : $opts['email_subject_template'] );
	$settings->set( 'abandoned_cart', 'email_body_template', isset( $_POST['cro_email_body_template'] ) ? wp_kses_post( wp_unslash( $_POST['cro_email_body_template'] ) ) : '' );
	$brand_hex = isset( $_POST['email_brand_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['email_brand_color'] ) ) : '';
	$settings->set( 'abandoned_cart', 'email_brand_color', $brand_hex ? $brand_hex : '#2563eb' );
	$opts = $settings->get_abandoned_cart_settings();
	$opts = wp_parse_args( $opts, array( 'email_subject_template' => '', 'email_body_template' => '' ) );
	$body_value = trim( (string) $opts['email_body_template'] ) !== '' ? $opts['email_body_template'] : $body_placeholder;
	echo '<div class="cro-ui-notice cro-ui-toast-placeholder" role="status"><p>' . esc_html__( 'Abandoned cart email settings saved.', 'meyvora-convert' ) . '</p></div>';
}

?>

	<div id="cro-ui-toast-container" class="cro-ui-toast-container" aria-live="polite" aria-label="<?php esc_attr_e( 'Notifications', 'meyvora-convert' ); ?>"></div>

	<form method="post" id="cro-abandoned-cart-form">
		<?php wp_nonce_field( 'cro_abandoned_cart_save' ); ?>

		<div class="cro-settings-section">
			<h2><?php esc_html_e( 'General', 'meyvora-convert' ); ?></h2>
			<div class="cro-fields-grid cro-fields-grid--1col">
				<div class="cro-field cro-col-12">
					<div class="cro-field__control">
						<label>
							<input type="checkbox" name="cro_abandoned_cart_enabled" value="1" <?php checked( ! empty( $opts['enable_abandoned_cart_emails'] ) ); ?> />
							<?php esc_html_e( 'Enable abandoned cart reminder emails', 'meyvora-convert' ); ?>
						</label>
					</div>
				</div>
				<div class="cro-field cro-col-12">
					<div class="cro-field__control">
						<label>
							<input type="checkbox" name="cro_abandoned_cart_require_opt_in" value="1" <?php checked( ! empty( $opts['require_opt_in'] ) ); ?> />
							<?php esc_html_e( 'Only send to visitors who opted in (e.g. checkbox on cart)', 'meyvora-convert' ); ?>
						</label>
					</div>
				</div>
				<div class="cro-field cro-col-12">
					<label class="cro-field__label"><?php esc_html_e( 'Email delays', 'meyvora-convert' ); ?></label>
					<div class="cro-delay-grid">
						<div class="cro-delay-item">
							<label class="cro-delay-label" for="cro_email_1_delay_hours"><?php esc_html_e( 'Email 1', 'meyvora-convert' ); ?></label>
							<div class="cro-delay-input-wrap">
								<input type="number" id="cro_email_1_delay_hours" name="cro_email_1_delay_hours" value="<?php echo esc_attr( (string) $opts['email_1_delay_hours'] ); ?>" min="0" class="cro-delay-hours-input" />
								<span class="cro-delay-unit"><?php esc_html_e( 'hours', 'meyvora-convert' ); ?></span>
							</div>
						</div>
						<div class="cro-delay-item">
							<label class="cro-delay-label" for="cro_email_2_delay_hours"><?php esc_html_e( 'Email 2', 'meyvora-convert' ); ?></label>
							<div class="cro-delay-input-wrap">
								<input type="number" id="cro_email_2_delay_hours" name="cro_email_2_delay_hours" value="<?php echo esc_attr( (string) $opts['email_2_delay_hours'] ); ?>" min="0" class="cro-delay-hours-input" />
								<span class="cro-delay-unit"><?php esc_html_e( 'hours', 'meyvora-convert' ); ?></span>
							</div>
						</div>
						<div class="cro-delay-item">
							<label class="cro-delay-label" for="cro_email_3_delay_hours"><?php esc_html_e( 'Email 3', 'meyvora-convert' ); ?></label>
							<div class="cro-delay-input-wrap">
								<input type="number" id="cro_email_3_delay_hours" name="cro_email_3_delay_hours" value="<?php echo esc_attr( (string) $opts['email_3_delay_hours'] ); ?>" min="0" class="cro-delay-hours-input" />
								<span class="cro-delay-unit"><?php esc_html_e( 'hours', 'meyvora-convert' ); ?></span>
							</div>
						</div>
					</div>
					<span class="cro-help"><?php esc_html_e( 'Hours after cart abandonment to send each reminder (e.g. 1, 24, 72). High-value carts use a faster built-in sequence (0.5h, 4h, 24h).', 'meyvora-convert' ); ?></span>
				</div>
				<div class="cro-field cro-col-12">
					<label for="cro_high_value_threshold" class="cro-field__label">
						<?php
						$cur_label = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : $currency_code;
						echo esc_html(
							sprintf(
								/* translators: %s: store currency symbol or code. */
								__( 'High-value cart threshold (%s)', 'meyvora-convert' ),
								$cur_label ? $cur_label : $currency_code
							)
						);
						?>
					</label>
					<div class="cro-field__control">
						<input type="number" id="cro_high_value_threshold" name="cro_high_value_threshold" value="<?php echo esc_attr( (string) (float) $opts['high_value_threshold'] ); ?>" min="0" step="1" class="small-text" />
					</div>
					<span class="cro-help"><?php esc_html_e( 'Carts at or above this value receive a faster, more personalised email sequence.', 'meyvora-convert' ); ?></span>
				</div>
			</div>
		</div>

		<div class="cro-settings-section">
			<h2><?php esc_html_e( 'Email templates', 'meyvora-convert' ); ?></h2>
			<div class="cro-fields-grid cro-fields-grid--1col">
				<div class="cro-field cro-col-12">
					<label for="cro-email-brand-color" class="cro-field__label"><?php esc_html_e( 'Email header colour', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<input type="color" id="cro-email-brand-color" name="email_brand_color"
							value="<?php echo esc_attr( cro_settings()->get( 'abandoned_cart', 'email_brand_color', '#2563eb' ) ); ?>" />
						<p class="cro-help"><?php esc_html_e( 'Background colour for the email header bar.', 'meyvora-convert' ); ?></p>
					</div>
				</div>
				<div class="cro-field cro-col-12">
					<label for="cro_email_subject_template" class="cro-field__label"><?php esc_html_e( 'Subject', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<input type="text" id="cro_email_subject_template" name="cro_email_subject_template" value="<?php echo esc_attr( $opts['email_subject_template'] ); ?>" class="large-text" />
					</div>
				</div>
				<div class="cro-field cro-col-12">
					<label for="cro_email_body_template" class="cro-field__label"><?php esc_html_e( 'Body (HTML)', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<div class="cro-email-body-editor-wrap">
							<p class="cro-help cro-mb-1"><?php esc_html_e( 'Insert placeholders below. Leave empty to use the default template.', 'meyvora-convert' ); ?></p>
							<div class="cro-email-placeholder-buttons">
								<?php
								$placeholders = array(
									'first_name'   => __( 'First name', 'meyvora-convert' ),
									'cart_total'   => __( 'Cart total', 'meyvora-convert' ),
									'cart_items'   => __( 'Cart items', 'meyvora-convert' ),
									'checkout_url' => __( 'Checkout URL', 'meyvora-convert' ),
									'coupon_code'  => __( 'Coupon code', 'meyvora-convert' ),
									'discount_text'=> __( 'Discount text', 'meyvora-convert' ),
									'store_name'   => __( 'Store name', 'meyvora-convert' ),
								);
								foreach ( $placeholders as $key => $label ) :
									$token = '{' . $key . '}';
								?>
									<button type="button" class="button button-small cro-email-insert-token" data-token="<?php echo esc_attr( $token ); ?>" title="<?php echo esc_attr( $token ); ?>"><?php echo esc_html( $token ); ?></button>
								<?php endforeach; ?>
							</div>
							<?php
							$editor_id = 'cro_email_body_template';
							$editor_settings = array(
								'teeny'         => true,
								'media_buttons' => false,
								'quicktags'     => false,
								'textarea_name' => $editor_id,
								'wpautop'       => false,
								'tinymce'       => array(
									'toolbar1' => 'bold,italic,link,bullist,numlist',
									'toolbar2' => '',
									'toolbar3' => '',
									'toolbar4' => '',
								),
								'editor_css'    => '',
								'dfw'           => false,
								'drag_drop_upload' => false,
							);
							wp_editor( $body_value, $editor_id, $editor_settings );
							?>
							<p class="cro-help cro-mt-1">
								<button type="button" id="cro_reset_body_template" class="button button-small"><?php esc_html_e( 'Reset to default template', 'meyvora-convert' ); ?></button>
							</p>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="cro-settings-section">
			<h2><?php esc_html_e( 'Send test email', 'meyvora-convert' ); ?></h2>
			<div class="cro-fields-grid cro-fields-grid--1col">
				<div class="cro-field cro-col-12">
					<label for="cro_test_email_to" class="cro-field__label"><?php esc_html_e( 'Send test email to', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<div class="cro-inline-input-group">
							<input type="email" id="cro_test_email_to" value="" class="cro-inline-input-group__input" placeholder="<?php esc_attr_e( 'email@example.com', 'meyvora-convert' ); ?>" />
							<button type="button" id="cro_send_test_email" class="button cro-inline-input-group__btn"><?php esc_html_e( 'Send test email', 'meyvora-convert' ); ?></button>
						</div>
						<div id="cro_test_email_notice" class="cro-test-email-notice notice is-dismissible cro-hidden cro-mt-2" role="alert"></div>
					</div>
				</div>
			</div>
		</div>

		<p class="submit">
			<button type="submit" name="cro_save_abandoned_cart" class="button button-primary cro-ui-btn-primary"><?php esc_html_e( 'Save settings', 'meyvora-convert' ); ?></button>
		</p>
	</form>

	<div class="cro-settings-section cro-ai-preview-section">
		<div class="cro-section-header">
			<h2>
				<svg class="cro-ico cro-ico--md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>
				<?php esc_html_e( 'AI email preview (by cart)', 'meyvora-convert' ); ?>
			</h2>
		</div>
		<p class="cro-section-description"><?php esc_html_e( 'Enter an abandoned cart row ID from Meyvora Convert → Abandoned Carts → View. Requires an Anthropic API key and "AI Abandoned Cart Emails" enabled under Settings → AI.', 'meyvora-convert' ); ?></p>
		<div class="cro-ai-preview-controls">
			<div class="cro-inline-input-group">
				<input type="number" id="cro-ai-preview-cart-id" min="1" step="1" class="cro-inline-input-group__input" placeholder="<?php esc_attr_e( 'Cart ID', 'meyvora-convert' ); ?>" aria-label="<?php esc_attr_e( 'Abandoned cart ID', 'meyvora-convert' ); ?>" />
			</div>
			<div class="cro-ai-preview-btns">
				<button type="button" class="button button-small cro-ai-preview-email" data-email="1">✦ <?php esc_html_e( 'Preview Email 1', 'meyvora-convert' ); ?></button>
				<button type="button" class="button button-small cro-ai-preview-email" data-email="2">✦ <?php esc_html_e( 'Preview Email 2', 'meyvora-convert' ); ?></button>
				<button type="button" class="button button-small cro-ai-preview-email" data-email="3">✦ <?php esc_html_e( 'Preview Email 3', 'meyvora-convert' ); ?></button>
			</div>
		</div>
	</div>

	<div class="cro-ui-card cro-settings-section cro-preview-section">
		<h2><?php esc_html_e( 'Preview', 'meyvora-convert' ); ?></h2>
		<p class="description cro-mb-2"><?php esc_html_e( 'Renders the current subject and body with sample placeholder values. Use "Refresh preview" after editing.', 'meyvora-convert' ); ?></p>
		<button type="button" id="cro_refresh_preview" class="button"><?php esc_html_e( 'Refresh preview', 'meyvora-convert' ); ?></button>
		<div id="cro_preview_wrapper" class="cro-email-preview-wrapper">
			<div id="cro_preview_subject" class="cro-preview-subject"></div>
			<iframe id="cro_preview_iframe" class="cro-preview-iframe" title="<?php esc_attr_e( 'Email body preview', 'meyvora-convert' ); ?>"></iframe>
		</div>
	</div>
