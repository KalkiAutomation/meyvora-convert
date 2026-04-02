<?php
/**
 * Admin boosters page – sticky cart, shipping bar, trust badges
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = meyvc_settings();

// Handle form submission.
$nonce_valid = isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'meyvc_boosters_nonce' );
if ( isset( $_POST['meyvc_save_boosters'] ) && $nonce_valid ) {

	// Sticky Cart Settings.
	$settings->set( 'general', 'sticky_cart_enabled', ! empty( $_POST['sticky_cart_enabled'] ) );
	$settings->set( 'sticky_cart', 'show_on_mobile_only', ! empty( $_POST['sticky_cart_mobile_only'] ) );
	$settings->set( 'sticky_cart', 'show_after_scroll', absint( $_POST['sticky_cart_scroll'] ?? 100 ) );
	$settings->set( 'sticky_cart', 'show_product_image', ! empty( $_POST['sticky_cart_show_image'] ) );
	$settings->set( 'sticky_cart', 'show_product_title', ! empty( $_POST['sticky_cart_show_title'] ) );
	$settings->set( 'sticky_cart', 'show_price', ! empty( $_POST['sticky_cart_show_price'] ) );
	$settings->set( 'sticky_cart', 'tone', sanitize_text_field( wp_unslash( $_POST['sticky_cart_tone'] ?? 'neutral' ) ) );
	$settings->set( 'sticky_cart', 'button_text', sanitize_text_field( wp_unslash( $_POST['sticky_cart_button_text'] ?? '' ) ) );
	$settings->set( 'sticky_cart', 'bg_color', sanitize_hex_color( wp_unslash( $_POST['sticky_cart_bg_color'] ?? '#ffffff' ) ) ?: '#ffffff' );
	$settings->set( 'sticky_cart', 'button_bg_color', sanitize_hex_color( wp_unslash( $_POST['sticky_cart_button_color'] ?? '#333333' ) ) ?: '#333333' );

	// Shipping Bar Settings.
	$settings->set( 'general', 'shipping_bar_enabled', ! empty( $_POST['shipping_bar_enabled'] ) );
	$settings->set( 'shipping_bar', 'use_woo_threshold', ! empty( $_POST['shipping_bar_use_woo'] ) );
	$settings->set( 'shipping_bar', 'threshold', isset( $_POST['shipping_bar_threshold'] ) ? floatval( wp_unslash( $_POST['shipping_bar_threshold'] ) ) : 0 );
	$settings->set( 'shipping_bar', 'tone', sanitize_text_field( wp_unslash( $_POST['shipping_bar_tone'] ?? 'neutral' ) ) );
	$settings->set( 'shipping_bar', 'message_progress', sanitize_text_field( wp_unslash( $_POST['shipping_bar_message_progress'] ?? '' ) ) );
	$settings->set( 'shipping_bar', 'message_achieved', sanitize_text_field( wp_unslash( $_POST['shipping_bar_message_achieved'] ?? '' ) ) );
	$settings->set( 'shipping_bar', 'position', sanitize_text_field( wp_unslash( $_POST['shipping_bar_position'] ?? 'top' ) ) );
	$settings->set( 'shipping_bar', 'bg_color', sanitize_hex_color( wp_unslash( $_POST['shipping_bar_bg_color'] ?? '#f7f7f7' ) ) ?: '#f7f7f7' );
	$settings->set( 'shipping_bar', 'bar_color', sanitize_hex_color( wp_unslash( $_POST['shipping_bar_bar_color'] ?? '#333333' ) ) ?: '#333333' );

	// Shipping bar – show on pages.
	$show_on = array();
	if ( ! empty( $_POST['shipping_bar_show_product'] ) ) {
		$show_on[] = 'product';
	}
	if ( ! empty( $_POST['shipping_bar_show_cart'] ) ) {
		$show_on[] = 'cart';
	}
	if ( ! empty( $_POST['shipping_bar_show_shop'] ) ) {
		$show_on[] = 'shop';
	}
	$settings->set( 'shipping_bar', 'show_on_pages', $show_on );

	// Stock urgency.
	$settings->set( 'stock_urgency', 'tone', sanitize_text_field( wp_unslash( $_POST['stock_urgency_tone'] ?? 'neutral' ) ) );
	$settings->set( 'stock_urgency', 'message_template', sanitize_text_field( wp_unslash( $_POST['stock_urgency_message'] ?? '' ) ) );
	$settings->set( 'boosters', 'stock_urgency_threshold',
		max( 1, min( 100, absint( $_POST['stock_urgency_threshold'] ?? 10 ) ) )
	);

	// Trust Badges.
	$settings->set( 'general', 'trust_badges_enabled', ! empty( $_POST['trust_badges_enabled'] ) );

	// Product recommendations.
	$settings->set( 'general', 'recommendations_enabled', ! empty( $_POST['recommendations_enabled'] ) );

	// Social proof.
	$settings->set( 'general', 'social_proof_enabled', ! empty( $_POST['social_proof_enabled'] ) );
	$settings->set( 'social_proof', 'window_hours', max( 1, min( 168, absint( $_POST['social_proof_window_hours'] ?? 24 ) ) ) );
	$settings->set( 'social_proof', 'min_quantity', max( 1, absint( $_POST['social_proof_min_quantity'] ?? 1 ) ) );
	$settings->set( 'social_proof', 'message_template', sanitize_text_field( wp_unslash( $_POST['social_proof_message'] ?? '' ) ) );
	$settings->set( 'social_proof', 'viewing_counter_enabled', ! empty( $_POST['social_proof_viewing_counter'] ) );
	$settings->set( 'social_proof', 'viewing_min', max( 2, absint( $_POST['social_proof_viewing_min'] ?? 2 ) ) );
	$settings->set( 'social_proof', 'viewing_max', max( 3, absint( $_POST['social_proof_viewing_max'] ?? 9 ) ) );
	$settings->set( 'social_proof', 'viewing_template', sanitize_text_field( wp_unslash( $_POST['social_proof_viewing_template'] ?? '' ) ) );
	$settings->set( 'social_proof', 'toast_enabled', ! empty( $_POST['social_proof_toast_enabled'] ) );
	$settings->set( 'social_proof', 'toast_pages', sanitize_key( wp_unslash( $_POST['social_proof_toast_pages'] ?? 'product' ) ) );
	$settings->set( 'social_proof', 'toast_initial_delay', max( 0, absint( $_POST['social_proof_toast_initial_delay'] ?? 8 ) ) );
	$settings->set( 'social_proof', 'toast_interval', max( 3, absint( $_POST['social_proof_toast_interval'] ?? 12 ) ) );
	$settings->set( 'social_proof', 'toast_template', sanitize_text_field( wp_unslash( $_POST['social_proof_toast_template'] ?? '' ) ) );

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved!', 'meyvora-convert' ) . '</p></div>';
}

// Get current settings.
$sticky_cart     = $settings->get_sticky_cart_settings();
$shipping_bar    = $settings->get_shipping_bar_settings();
$stock_urgency   = $settings->get_stock_urgency_settings();
$social_proof    = wp_parse_args(
	$settings->get_group( 'social_proof' ),
	array(
		'window_hours'            => 24,
		'min_quantity'            => 1,
		'message_template'        => /* translators: 1: purchase count, 2: number of hours in the window. */ __( '{count} people bought this in the last {hours} hours.', 'meyvora-convert' ),
		'viewing_counter_enabled' => false,
		'viewing_min'             => 2,
		'viewing_max'             => 9,
		'viewing_template'        => /* translators: %d: number of people viewing the product. */ __( '%d people are viewing this right now', 'meyvora-convert' ),
		'toast_enabled'           => false,
		'toast_pages'             => 'product',
		'toast_initial_delay'    => 8,
		'toast_interval'          => 12,
		'toast_template'          => /* translators: 1: customer first name or placeholder, 2: city/region, 3: product name. */ __( '{name} from {location} just bought {product}', 'meyvora-convert' ),
	)
);
$default_copy_tones = class_exists( 'MEYVC_Default_Copy' ) ? MEYVC_Default_Copy::get_tones() : array( 'neutral' => __( 'Neutral', 'meyvora-convert' ), 'urgent' => __( 'Urgent', 'meyvora-convert' ), 'friendly' => __( 'Friendly', 'meyvora-convert' ) );

// Sticky cart admin preview: default button labels per tone (for live JS when field is empty).
$sticky_cart_btn_defaults = array();
if ( class_exists( 'MEYVC_Default_Copy' ) ) {
	foreach ( array_keys( $default_copy_tones ) as $tone_key ) {
		$sticky_cart_btn_defaults[ $tone_key ] = MEYVC_Default_Copy::get( 'sticky_cart', $tone_key, 'button_text' );
	}
}
$sc_tone_cur      = isset( $sticky_cart['tone'] ) ? $sticky_cart['tone'] : 'neutral';
$sc_preview_btn   = ! empty( $sticky_cart['button_text'] )
	? (string) $sticky_cart['button_text']
	: ( class_exists( 'MEYVC_Default_Copy' ) ? MEYVC_Default_Copy::get( 'sticky_cart', $sc_tone_cur, 'button_text' ) : __( 'Add to cart', 'meyvora-convert' ) );
$sc_preview_title = __( 'Sample product name', 'meyvora-convert' );
$sc_preview_price = function_exists( 'wc_price' ) ? wc_price( 29.99 ) : esc_html( number_format( 29.99, 2 ) );
$sc_preview_img   = function_exists( 'wc_placeholder_img' ) ? wc_placeholder_img( 'thumbnail' ) : '';

// Get WooCommerce free shipping threshold (used in UI + shipping bar preview).
$woo_threshold = 0;
if ( function_exists( 'WC_Shipping_Zones' ) && class_exists( 'WC_Shipping_Zones' ) ) {
	$zones = WC_Shipping_Zones::get_zones();
	foreach ( $zones as $zone ) {
		$methods = ( is_object( $zone ) && method_exists( $zone, 'get_shipping_methods' ) )
			? $zone->get_shipping_methods()
			: array();
		foreach ( $methods as $method ) {
			if ( is_object( $method ) && isset( $method->id ) && 'free_shipping' === $method->id ) {
				$min = isset( $method->min_amount ) ? $method->min_amount : ( isset( $method->instance_settings['min_amount'] ) ? $method->instance_settings['min_amount'] : 0 );
				$woo_threshold = floatval( $min );
				break 2;
			}
		}
	}
}

// Shipping bar admin preview: demo cart at 50% of threshold (matches JS live preview).
$sb_preview_use_woo = ! empty( $shipping_bar['use_woo_threshold'] );
$sb_preview_th      = ( $sb_preview_use_woo && $woo_threshold > 0 ) ? (float) $woo_threshold : floatval( $shipping_bar['threshold'] ?? 0 );
if ( $sb_preview_th <= 0 ) {
	$sb_preview_th = 50.0;
}
$sb_preview_cart         = $sb_preview_th * 0.5;
$sb_preview_remaining    = max( 0, $sb_preview_th - $sb_preview_cart );
$sb_preview_progress_pct = $sb_preview_th > 0 ? min( 100, ( $sb_preview_cart / $sb_preview_th ) * 100 ) : 0;
$sb_prev_tone            = isset( $shipping_bar['tone'] ) ? $shipping_bar['tone'] : 'neutral';
$sb_progress_tpl         = ( isset( $shipping_bar['message_progress'] ) && (string) $shipping_bar['message_progress'] !== '' )
	? (string) $shipping_bar['message_progress']
	: ( class_exists( 'MEYVC_Default_Copy' ) ? MEYVC_Default_Copy::get( 'shipping_bar', $sb_prev_tone, 'progress' ) : __( 'Add {amount} more for free shipping', 'meyvora-convert' ) );
$sb_preview_message      = str_replace( '{amount}', function_exists( 'wc_price' ) ? wc_price( $sb_preview_remaining ) : esc_html( number_format( (float) $sb_preview_remaining, 2 ) ), $sb_progress_tpl );
?>

	<form method="post" id="meyvc-boosters-form">
		<?php wp_nonce_field( 'meyvc_boosters_nonce' ); ?>

		<!-- Sticky Add-to-Cart Section -->
		<div class="meyvc-settings-section">
			<div class="meyvc-section-header">
				<h2>
					<?php echo wp_kses( MEYVC_Icons::svg( 'shopping-cart', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?>

					<?php esc_html_e( 'Sticky Add-to-Cart Bar', 'meyvora-convert' ); ?>
				</h2>
				<label class="meyvc-toggle">
					<input type="checkbox" name="sticky_cart_enabled" value="1"
						<?php checked( $settings->is_feature_enabled( 'sticky_cart' ) ); ?> />
					<span class="meyvc-toggle-slider"></span>
				</label>
			</div>

			<p class="meyvc-section-description">
				<?php esc_html_e( 'Shows a sticky bar on product pages so the Add to Cart button is always visible while scrolling.', 'meyvora-convert' ); ?>
			</p>

			<div class="meyvc-settings-fields">
				<div class="meyvc-fields-grid">
					<div class="meyvc-field meyvc-col-12">
						<div class="meyvc-field__control">
							<label>
								<input type="checkbox" name="sticky_cart_mobile_only" value="1"
									<?php checked( ! empty( $sticky_cart['show_on_mobile_only'] ) ); ?> />
								<?php esc_html_e( 'Show on mobile devices only (recommended)', 'meyvora-convert' ); ?>
							</label>
						</div>
						<span class="meyvc-help"><?php esc_html_e( 'Desktop users typically don\'t need this as the page is shorter.', 'meyvora-convert' ); ?></span>
					</div>
					<div class="meyvc-field meyvc-col-12">
						<label for="sticky_cart_scroll" class="meyvc-field__label"><?php esc_html_e( 'Show After Scrolling', 'meyvora-convert' ); ?></label>
						<div class="meyvc-field__control">
							<input type="number" id="sticky_cart_scroll" name="sticky_cart_scroll"
								value="<?php echo esc_attr( $sticky_cart['show_after_scroll'] ); ?>"
								min="0" max="1000" class="small-text" /> px
						</div>
						<span class="meyvc-help"><?php esc_html_e( 'How far the user must scroll before the bar appears.', 'meyvora-convert' ); ?></span>
					</div>
					<div class="meyvc-field meyvc-col-12">
						<span class="meyvc-field__label"><?php esc_html_e( 'Content', 'meyvora-convert' ); ?></span>
						<div class="meyvc-field__control">
							<label>
								<input type="checkbox" name="sticky_cart_show_image" value="1"
									<?php checked( ! empty( $sticky_cart['show_product_image'] ) ); ?> />
								<?php esc_html_e( 'Show product image', 'meyvora-convert' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="sticky_cart_show_title" value="1"
									<?php checked( ! empty( $sticky_cart['show_product_title'] ) ); ?> />
								<?php esc_html_e( 'Show product title', 'meyvora-convert' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="sticky_cart_show_price" value="1"
									<?php checked( ! empty( $sticky_cart['show_price'] ) ); ?> />
								<?php esc_html_e( 'Show price', 'meyvora-convert' ); ?>
							</label>
						</div>
					</div>
					<div class="meyvc-field meyvc-col-6">
						<label for="sticky_cart_tone" class="meyvc-field__label"><?php esc_html_e( 'Tone', 'meyvora-convert' ); ?></label>
						<div class="meyvc-field__control">
							<select name="sticky_cart_tone" id="sticky_cart_tone" class="meyvc-selectwoo" data-placeholder="<?php esc_attr_e( 'Tone', 'meyvora-convert' ); ?>">
								<?php foreach ( $default_copy_tones as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( isset( $sticky_cart['tone'] ) ? $sticky_cart['tone'] : 'neutral', $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<span class="meyvc-help"><?php esc_html_e( 'Affects default button copy when left blank.', 'meyvora-convert' ); ?></span>
					</div>
					<div class="meyvc-field meyvc-col-6">
						<label for="sticky_cart_button_text" class="meyvc-field__label"><?php esc_html_e( 'Button text', 'meyvora-convert' ); ?></label>
						<div class="meyvc-field__control">
							<input type="text" id="sticky_cart_button_text" name="sticky_cart_button_text"
								value="<?php echo esc_attr( $sticky_cart['button_text'] ); ?>"
								class="regular-text" placeholder="<?php echo esc_attr( class_exists( 'MEYVC_Default_Copy' ) ? MEYVC_Default_Copy::get( 'sticky_cart', isset( $sticky_cart['tone'] ) ? $sticky_cart['tone'] : 'neutral', 'button_text' ) : __( 'Add to cart', 'meyvora-convert' ) ); ?>" />
						</div>
						<span class="meyvc-help"><?php esc_html_e( 'Leave empty to use the default for the selected tone.', 'meyvora-convert' ); ?></span>
					</div>
					<div class="meyvc-field meyvc-col-12">
						<span class="meyvc-field__label"><?php esc_html_e( 'Colors', 'meyvora-convert' ); ?></span>
						<div class="meyvc-field__control">
							<label>
								<?php esc_html_e( 'Background:', 'meyvora-convert' ); ?>
								<input type="text" name="sticky_cart_bg_color"
									value="<?php echo esc_attr( $sticky_cart['bg_color'] ); ?>"
									class="meyvc-color-picker" />
							</label>
							<br><br>
							<label>
								<?php esc_html_e( 'Button:', 'meyvora-convert' ); ?>
								<input type="text" name="sticky_cart_button_color"
									value="<?php echo esc_attr( $sticky_cart['button_bg_color'] ); ?>"
									class="meyvc-color-picker" />
							</label>
						</div>
					</div>
				</div>
			</div>

			<div class="meyvc-preview-box">
				<h4><?php esc_html_e( 'Preview', 'meyvora-convert' ); ?></h4>
				<div
					class="meyvc-sticky-cart-preview"
					id="meyvc-sticky-cart-preview-wrap"
					aria-hidden="true"
					data-default-buttons="<?php echo esc_attr( wp_json_encode( $sticky_cart_btn_defaults ) ); ?>"
					data-sample-title="<?php echo esc_attr( $sc_preview_title ); ?>"
				>
					<div
						id="meyvc-sticky-cart-preview-bar"
						class="meyvc-sticky-cart meyvc-sticky-cart--admin-preview meyvc-sticky-cart-visible"
					>
						<div
							class="meyvc-sticky-cart-inner"
							id="meyvc-sticky-cart-preview-inner"
							style="background-color: <?php echo esc_attr( $sticky_cart['bg_color'] ); ?>;"
						>
							<div
								class="meyvc-sticky-cart-image"
								id="meyvc-sticky-cart-preview-image-wrap"
								style="<?php echo ! empty( $sticky_cart['show_product_image'] ) ? '' : 'display:none;'; ?>"
							>
								<?php
								if ( $sc_preview_img ) {
									echo wp_kses_post( $sc_preview_img );
								} else {
									echo '<span class="meyvc-sticky-cart-preview-img-fallback" aria-hidden="true"></span>';
								}
								?>
							</div>
							<div class="meyvc-sticky-cart-info">
								<span
									class="meyvc-sticky-cart-title"
									id="meyvc-sticky-cart-preview-title"
									style="<?php echo empty( $sticky_cart['show_product_title'] ) ? 'display:none;' : ''; ?>"
								><?php echo esc_html( $sc_preview_title ); ?></span>
								<span
									class="meyvc-sticky-cart-price"
									id="meyvc-sticky-cart-preview-price"
									style="<?php echo empty( $sticky_cart['show_price'] ) ? 'display:none;' : ''; ?>"
								><?php echo wp_kses_post( $sc_preview_price ); ?></span>
							</div>
							<div class="meyvc-sticky-cart-action">
								<button
									type="button"
									class="meyvc-sticky-cart-button"
									id="meyvc-sticky-cart-preview-btn"
									disabled
									style="background-color: <?php echo esc_attr( $sticky_cart['button_bg_color'] ); ?>; color: <?php echo esc_attr( isset( $sticky_cart['button_text_color'] ) ? $sticky_cart['button_text_color'] : '#ffffff' ); ?>;"
								><?php echo esc_html( $sc_preview_btn ); ?></button>
							</div>
						</div>
					</div>
					<p class="meyvc-sticky-cart-preview-hint description">
						<?php esc_html_e( 'Approximate look on product pages. Edit fields above to update.', 'meyvora-convert' ); ?>
					</p>
				</div>
			</div>
		</div>

		<!-- Free Shipping Bar Section -->
		<div class="meyvc-settings-section">
			<div class="meyvc-section-header">
				<h2>
					<?php echo wp_kses( MEYVC_Icons::svg( 'truck', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?>

					<?php esc_html_e( 'Free Shipping Progress Bar', 'meyvora-convert' ); ?>
				</h2>
				<label class="meyvc-toggle">
					<input type="checkbox" name="shipping_bar_enabled" value="1"
						<?php checked( $settings->is_feature_enabled( 'shipping_bar' ) ); ?> />
					<span class="meyvc-toggle-slider"></span>
				</label>
			</div>

			<p class="meyvc-section-description">
				<?php esc_html_e( 'Shows customers how close they are to qualifying for free shipping.', 'meyvora-convert' ); ?>
			</p>

			<div class="meyvc-settings-fields">
				<div class="meyvc-fields-grid">
					<div class="meyvc-field meyvc-col-12">
						<span class="meyvc-field__label"><?php esc_html_e( 'Threshold', 'meyvora-convert' ); ?></span>
						<div class="meyvc-field__control">
							<label>
								<input type="checkbox" id="shipping_bar_use_woo" name="shipping_bar_use_woo" value="1"
									<?php checked( ! empty( $shipping_bar['use_woo_threshold'] ) ); ?> />
								<?php
								printf(
									/* translators: %s: formatted price */
									esc_html__( 'Use WooCommerce free shipping threshold (%s)', 'meyvora-convert' ),
									wp_kses_post( wc_price( $woo_threshold ) )
								);
								?>
							</label>
							<br><br>
							<label>
								<?php esc_html_e( 'Or set custom threshold:', 'meyvora-convert' ); ?>
								<?php echo esc_html( get_woocommerce_currency_symbol() ); ?>
								<input type="number" id="shipping_bar_threshold" name="shipping_bar_threshold"
									value="<?php echo esc_attr( $shipping_bar['threshold'] ); ?>"
									min="0" step="0.01" class="small-text" />
							</label>
						</div>
					</div>
					<div class="meyvc-field meyvc-col-6">
						<label for="shipping_bar_tone" class="meyvc-field__label"><?php esc_html_e( 'Tone', 'meyvora-convert' ); ?></label>
						<div class="meyvc-field__control">
							<select name="shipping_bar_tone" id="shipping_bar_tone" class="meyvc-selectwoo" data-placeholder="<?php esc_attr_e( 'Tone', 'meyvora-convert' ); ?>">
								<?php foreach ( $default_copy_tones as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( isset( $shipping_bar['tone'] ) ? $shipping_bar['tone'] : 'neutral', $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<span class="meyvc-help"><?php esc_html_e( 'Affects default messages when left blank.', 'meyvora-convert' ); ?></span>
					</div>
					<div class="meyvc-field meyvc-col-6">
						<label for="shipping_bar_position" class="meyvc-field__label"><?php esc_html_e( 'Position', 'meyvora-convert' ); ?></label>
						<div class="meyvc-field__control">
							<select id="shipping_bar_position" name="shipping_bar_position" class="meyvc-selectwoo" data-placeholder="<?php esc_attr_e( 'Top of page (sticky)', 'meyvora-convert' ); ?>">
								<option value="top" <?php selected( $shipping_bar['position'], 'top' ); ?>>
									<?php esc_html_e( 'Top of page (sticky)', 'meyvora-convert' ); ?>
								</option>
								<option value="above_cart" <?php selected( $shipping_bar['position'], 'above_cart' ); ?>>
									<?php esc_html_e( 'Above cart/add-to-cart', 'meyvora-convert' ); ?>
								</option>
								<option value="below_cart" <?php selected( $shipping_bar['position'], 'below_cart' ); ?>>
									<?php esc_html_e( 'Below cart total', 'meyvora-convert' ); ?>
								</option>
							</select>
						</div>
					</div>
					<div class="meyvc-field meyvc-col-12">
						<label for="shipping_bar_message_progress" class="meyvc-field__label"><?php esc_html_e( 'Progress message', 'meyvora-convert' ); ?></label>
						<div class="meyvc-field__control">
							<input type="text" id="shipping_bar_message_progress" name="shipping_bar_message_progress"
								value="<?php echo esc_attr( $shipping_bar['message_progress'] ); ?>"
								class="large-text" placeholder="<?php echo esc_attr( class_exists( 'MEYVC_Default_Copy' ) ? MEYVC_Default_Copy::get( 'shipping_bar', isset( $shipping_bar['tone'] ) ? $shipping_bar['tone'] : 'neutral', 'progress' ) : __( 'Add {amount} more for free shipping', 'meyvora-convert' ) ); ?>" />
						</div>
						<span class="meyvc-help"><?php esc_html_e( 'Placeholder: {amount} — remaining amount needed for free shipping.', 'meyvora-convert' ); ?></span>
					</div>
					<div class="meyvc-field meyvc-col-12">
						<label for="shipping_bar_message_achieved" class="meyvc-field__label"><?php esc_html_e( 'Success message', 'meyvora-convert' ); ?></label>
						<div class="meyvc-field__control">
							<input type="text" id="shipping_bar_message_achieved" name="shipping_bar_message_achieved"
								value="<?php echo esc_attr( $shipping_bar['message_achieved'] ); ?>"
								class="large-text" placeholder="<?php echo esc_attr( class_exists( 'MEYVC_Default_Copy' ) ? MEYVC_Default_Copy::get( 'shipping_bar', isset( $shipping_bar['tone'] ) ? $shipping_bar['tone'] : 'neutral', 'achieved' ) : __( 'You\'ve got free shipping', 'meyvora-convert' ) ); ?>" />
						</div>
						<span class="meyvc-help"><?php esc_html_e( 'Shown when cart qualifies for free shipping.', 'meyvora-convert' ); ?></span>
					</div>
					<div class="meyvc-field meyvc-col-12">
						<span class="meyvc-field__label"><?php esc_html_e( 'Show On', 'meyvora-convert' ); ?></span>
						<div class="meyvc-field__control">
							<label>
								<input type="checkbox" name="shipping_bar_show_product" value="1"
									<?php checked( in_array( 'product', (array) $shipping_bar['show_on_pages'], true ) ); ?> />
								<?php esc_html_e( 'Product pages', 'meyvora-convert' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="shipping_bar_show_cart" value="1"
									<?php checked( in_array( 'cart', (array) $shipping_bar['show_on_pages'], true ) ); ?> />
								<?php esc_html_e( 'Cart page', 'meyvora-convert' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="shipping_bar_show_shop" value="1"
									<?php checked( in_array( 'shop', (array) $shipping_bar['show_on_pages'], true ) ); ?> />
								<?php esc_html_e( 'Shop/Category pages', 'meyvora-convert' ); ?>
							</label>
						</div>
					</div>
					<div class="meyvc-field meyvc-col-12">
						<span class="meyvc-field__label"><?php esc_html_e( 'Colors', 'meyvora-convert' ); ?></span>
						<div class="meyvc-field__control">
							<label>
								<?php esc_html_e( 'Background:', 'meyvora-convert' ); ?>
								<input type="text" id="shipping_bar_bg_color" name="shipping_bar_bg_color"
									value="<?php echo esc_attr( $shipping_bar['bg_color'] ); ?>"
									class="meyvc-color-picker" />
							</label>
							<br><br>
							<label>
								<?php esc_html_e( 'Progress bar:', 'meyvora-convert' ); ?>
								<input type="text" id="shipping_bar_bar_color" name="shipping_bar_bar_color"
									value="<?php echo esc_attr( $shipping_bar['bar_color'] ); ?>"
									class="meyvc-color-picker" />
							</label>
						</div>
					</div>
				</div>
			</div>

			<div class="meyvc-preview-box">
				<h4><?php esc_html_e( 'Preview', 'meyvora-convert' ); ?></h4>
				<div
					class="meyvc-shipping-bar-preview"
					id="meyvc-shipping-bar-preview-wrap"
					aria-hidden="true"
					data-woo-threshold="<?php echo esc_attr( (string) $woo_threshold ); ?>"
				>
					<div
						class="meyvc-shipping-bar meyvc-shipping-bar--admin-preview"
						id="meyvc-shipping-bar-preview"
						style="background-color: <?php echo esc_attr( $shipping_bar['bg_color'] ); ?>;"
					>
						<div class="meyvc-shipping-bar-inner">
							<span class="meyvc-shipping-bar-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'truck', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>
							<span class="meyvc-shipping-bar-message" id="meyvc-shipping-bar-preview-message"><?php echo wp_kses_post( $sb_preview_message ); ?></span>
						</div>
						<div class="meyvc-shipping-bar-progress" id="meyvc-shipping-bar-preview-progress-wrap">
							<div
								class="meyvc-shipping-bar-fill"
								id="meyvc-shipping-bar-preview-fill"
								style="width: <?php echo esc_attr( (string) round( $sb_preview_progress_pct, 2 ) ); ?>%; background-color: <?php echo esc_attr( $shipping_bar['bar_color'] ); ?>;"
							></div>
						</div>
					</div>
					<p class="meyvc-shipping-bar-preview-hint description">
						<?php esc_html_e( 'Sample: cart at 50% of your threshold. Edit fields above to update.', 'meyvora-convert' ); ?>
					</p>
				</div>
			</div>
		</div>

		<!-- Low Stock Urgency Section -->
		<div class="meyvc-settings-section">
			<div class="meyvc-section-header">
				<h2>
					<?php echo wp_kses( MEYVC_Icons::svg( 'alert', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?>

					<?php esc_html_e( 'Low Stock Urgency', 'meyvora-convert' ); ?>
				</h2>
			</div>
			<p class="meyvc-section-description">
				<?php esc_html_e( 'Shows a message on product pages when stock is low (e.g. "Only 3 left"). Honest urgency without fake scarcity.', 'meyvora-convert' ); ?>
			</p>
			<div class="meyvc-settings-fields">
				<div class="meyvc-fields-grid">
					<div class="meyvc-field meyvc-col-6">
						<label for="stock_urgency_tone" class="meyvc-field__label"><?php esc_html_e( 'Tone', 'meyvora-convert' ); ?></label>
						<div class="meyvc-field__control">
							<select name="stock_urgency_tone" id="stock_urgency_tone" class="meyvc-selectwoo" data-placeholder="<?php esc_attr_e( 'Tone', 'meyvora-convert' ); ?>">
								<?php foreach ( $default_copy_tones as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( isset( $stock_urgency['tone'] ) ? $stock_urgency['tone'] : 'neutral', $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					<div class="meyvc-field meyvc-col-6">
						<label for="stock_urgency_message" class="meyvc-field__label"><?php esc_html_e( 'Message', 'meyvora-convert' ); ?></label>
						<div class="meyvc-field__control">
							<input type="text" id="stock_urgency_message" name="stock_urgency_message"
								value="<?php echo esc_attr( $stock_urgency['message_template'] ); ?>"
								class="large-text" placeholder="<?php echo esc_attr( class_exists( 'MEYVC_Default_Copy' ) ? MEYVC_Default_Copy::get( 'stock_urgency', isset( $stock_urgency['tone'] ) ? $stock_urgency['tone'] : 'neutral', 'message' ) : __( '{count} left in stock', 'meyvora-convert' ) ); ?>" />
						</div>
						<span class="meyvc-help"><?php esc_html_e( 'Placeholder: {count} — number of items left in stock. Leave empty for default.', 'meyvora-convert' ); ?></span>
					</div>
					<div class="meyvc-field meyvc-col-6">
						<label class="meyvc-field__label" for="stock-urgency-threshold">
							<?php esc_html_e( 'Show urgency when stock is below', 'meyvora-convert' ); ?>
						</label>
						<div class="meyvc-field__control">
							<input type="number" id="stock-urgency-threshold"
								name="stock_urgency_threshold" min="1" max="100"
								value="<?php echo esc_attr( meyvc_settings()->get( 'boosters', 'stock_urgency_threshold', 10 ) ); ?>"
								style="width:80px;" />
							<span class="meyvc-help"><?php esc_html_e( 'units (default: 10)', 'meyvora-convert' ); ?></span>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Trust Badges Section -->
		<div class="meyvc-settings-section">
			<div class="meyvc-section-header">
				<h2>
					<?php echo wp_kses( MEYVC_Icons::svg( 'shield', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?>

					<?php esc_html_e( 'Trust Badges', 'meyvora-convert' ); ?>
				</h2>
				<label class="meyvc-toggle">
					<input type="checkbox" name="trust_badges_enabled" value="1"
						<?php checked( $settings->is_feature_enabled( 'trust_badges' ) ); ?> />
					<span class="meyvc-toggle-slider"></span>
				</label>
			</div>

			<p class="meyvc-section-description">
				<?php esc_html_e( 'Show trust badges (secure checkout, free shipping, returns) on product, cart, and checkout to build confidence.', 'meyvora-convert' ); ?>
			</p>
		</div>

		<div class="meyvc-settings-section">
			<div class="meyvc-section-header">
				<h2>
					<?php echo wp_kses( MEYVC_Icons::svg( 'shopping-cart', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?>

					<?php esc_html_e( 'Product recommendations', 'meyvora-convert' ); ?>
				</h2>
				<label class="meyvc-toggle">
					<input type="checkbox" name="recommendations_enabled" value="1"
						<?php checked( $settings->is_feature_enabled( 'recommendations' ) ); ?> />
					<span class="meyvc-toggle-slider"></span>
				</label>
			</div>
			<p class="meyvc-section-description">
				<?php esc_html_e( 'Show “Frequently bought together” on product pages and “You might also like” on the cart, based on co-purchase history.', 'meyvora-convert' ); ?>
			</p>
		</div>

		<div class="meyvc-settings-section">
			<div class="meyvc-section-header">
				<h2><?php esc_html_e( 'Social proof', 'meyvora-convert' ); ?></h2>
				<label class="meyvc-toggle">
					<input type="checkbox" name="social_proof_enabled" value="1"
						<?php checked( $settings->is_feature_enabled( 'social_proof' ) ); ?> />
					<span class="meyvc-toggle-slider"></span>
				</label>
			</div>
			<p class="meyvc-section-description"><?php esc_html_e( 'Show recent purchase volume on product pages (from completed/processing orders).', 'meyvora-convert' ); ?></p>
			<div class="meyvc-fields-grid">
				<div class="meyvc-field meyvc-col-6">
					<label class="meyvc-field__label"><?php esc_html_e( 'Rolling window (hours)', 'meyvora-convert' ); ?></label>
					<input type="number" name="social_proof_window_hours" min="1" max="168" class="small-text" value="<?php echo esc_attr( (string) $social_proof['window_hours'] ); ?>" />
				</div>
				<div class="meyvc-field meyvc-col-6">
					<label class="meyvc-field__label"><?php esc_html_e( 'Minimum units sold to show', 'meyvora-convert' ); ?></label>
					<input type="number" name="social_proof_min_quantity" min="1" class="small-text" value="<?php echo esc_attr( (string) $social_proof['min_quantity'] ); ?>" />
				</div>
				<div class="meyvc-field meyvc-col-12">
					<label class="meyvc-field__label"><?php esc_html_e( 'Message', 'meyvora-convert' ); ?></label>
					<input type="text" name="social_proof_message" class="large-text" value="<?php echo esc_attr( (string) $social_proof['message_template'] ); ?>" />
					<span class="meyvc-help"><?php esc_html_e( 'Placeholders: {count}, {hours}', 'meyvora-convert' ); ?></span>
				</div>
				<div class="meyvc-field meyvc-col-12">
					<label class="meyvc-checkbox-card">
						<input type="checkbox" name="social_proof_viewing_counter" value="1" <?php checked( ! empty( $social_proof['viewing_counter_enabled'] ) ); ?> />
						<span class="meyvc-checkbox-content">
							<strong><?php esc_html_e( '“People viewing” counter', 'meyvora-convert' ); ?></strong>
							<span><?php esc_html_e( 'Shows a rotating viewer count on product pages (client-side).', 'meyvora-convert' ); ?></span>
						</span>
					</label>
				</div>
				<div class="meyvc-field meyvc-col-6">
					<label class="meyvc-field__label"><?php esc_html_e( 'Viewing count min', 'meyvora-convert' ); ?></label>
					<input type="number" name="social_proof_viewing_min" min="2" class="small-text" value="<?php echo esc_attr( (string) $social_proof['viewing_min'] ); ?>" />
				</div>
				<div class="meyvc-field meyvc-col-6">
					<label class="meyvc-field__label"><?php esc_html_e( 'Viewing count max', 'meyvora-convert' ); ?></label>
					<input type="number" name="social_proof_viewing_max" min="3" class="small-text" value="<?php echo esc_attr( (string) $social_proof['viewing_max'] ); ?>" />
				</div>
				<div class="meyvc-field meyvc-col-12">
					<label class="meyvc-field__label"><?php esc_html_e( 'Viewing message', 'meyvora-convert' ); ?></label>
					<input type="text" name="social_proof_viewing_template" class="large-text" value="<?php echo esc_attr( (string) $social_proof['viewing_template'] ); ?>" />
					<span class="meyvc-help"><?php
					/* translators: %d: placeholder token to insert the viewer count in the message. */
					esc_html_e( 'Use %d for the number.', 'meyvora-convert' );
					?></span>
				</div>
				<div class="meyvc-field meyvc-col-12">
					<label class="meyvc-checkbox-card">
						<input type="checkbox" name="social_proof_toast_enabled" value="1" <?php checked( ! empty( $social_proof['toast_enabled'] ) ); ?> />
						<span class="meyvc-checkbox-content">
							<strong><?php esc_html_e( 'Recent purchase toast', 'meyvora-convert' ); ?></strong>
							<span><?php esc_html_e( 'Bottom-left notification with anonymised recent orders.', 'meyvora-convert' ); ?></span>
						</span>
					</label>
				</div>
				<div class="meyvc-field meyvc-col-12">
					<label class="meyvc-field__label"><?php esc_html_e( 'Toast pages', 'meyvora-convert' ); ?></label>
					<select name="social_proof_toast_pages">
						<option value="product" <?php selected( (string) $social_proof['toast_pages'], 'product' ); ?>><?php esc_html_e( 'Product only', 'meyvora-convert' ); ?></option>
						<option value="home" <?php selected( (string) $social_proof['toast_pages'], 'home' ); ?>><?php esc_html_e( 'Home only', 'meyvora-convert' ); ?></option>
						<option value="both" <?php selected( (string) $social_proof['toast_pages'], 'both' ); ?>><?php esc_html_e( 'Home and product', 'meyvora-convert' ); ?></option>
						<option value="all" <?php selected( (string) $social_proof['toast_pages'], 'all' ); ?>><?php esc_html_e( 'All pages', 'meyvora-convert' ); ?></option>
					</select>
				</div>
				<div class="meyvc-field meyvc-col-6">
					<label class="meyvc-field__label"><?php esc_html_e( 'Toast first delay (seconds)', 'meyvora-convert' ); ?></label>
					<input type="number" name="social_proof_toast_initial_delay" min="0" class="small-text" value="<?php echo esc_attr( (string) $social_proof['toast_initial_delay'] ); ?>" />
				</div>
				<div class="meyvc-field meyvc-col-6">
					<label class="meyvc-field__label"><?php esc_html_e( 'Toast interval (seconds)', 'meyvora-convert' ); ?></label>
					<input type="number" name="social_proof_toast_interval" min="3" class="small-text" value="<?php echo esc_attr( (string) $social_proof['toast_interval'] ); ?>" />
				</div>
				<div class="meyvc-field meyvc-col-12">
					<label class="meyvc-field__label"><?php esc_html_e( 'Toast template', 'meyvora-convert' ); ?></label>
					<input type="text" name="social_proof_toast_template" class="large-text" value="<?php echo esc_attr( (string) $social_proof['toast_template'] ); ?>" />
					<span class="meyvc-help"><?php esc_html_e( 'Placeholders: {name}, {location}, {product}', 'meyvora-convert' ); ?></span>
				</div>
			</div>
		</div>

		<?php submit_button( __( 'Save All Settings', 'meyvora-convert' ), 'primary', 'meyvc_save_boosters' ); ?>

	</form>
