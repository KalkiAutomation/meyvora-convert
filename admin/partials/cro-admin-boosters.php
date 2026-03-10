<?php
/**
 * Admin boosters page – sticky cart, shipping bar, trust badges
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

$settings = cro_settings();

// Handle form submission.
$nonce_valid = isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'cro_boosters_nonce' );
if ( isset( $_POST['cro_save_boosters'] ) && $nonce_valid ) {

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
	$settings->set( 'shipping_bar', 'threshold', isset( $_POST['shipping_bar_threshold'] ) ? floatval( $_POST['shipping_bar_threshold'] ) : 0 );
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

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved!', 'meyvora-convert' ) . '</p></div>';
}

// Get current settings.
$sticky_cart     = $settings->get_sticky_cart_settings();
$shipping_bar    = $settings->get_shipping_bar_settings();
$stock_urgency   = $settings->get_stock_urgency_settings();
$default_copy_tones = class_exists( 'CRO_Default_Copy' ) ? CRO_Default_Copy::get_tones() : array( 'neutral' => __( 'Neutral', 'meyvora-convert' ), 'urgent' => __( 'Urgent', 'meyvora-convert' ), 'friendly' => __( 'Friendly', 'meyvora-convert' ) );

// Get WooCommerce free shipping threshold.
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
?>

	<form method="post" id="cro-boosters-form">
		<?php wp_nonce_field( 'cro_boosters_nonce' ); ?>

		<!-- Sticky Add-to-Cart Section -->
		<div class="cro-settings-section">
			<div class="cro-section-header">
				<h2>
					<?php echo CRO_Icons::svg( 'shopping-cart', array( 'class' => 'cro-ico' ) ); ?>
					<?php esc_html_e( 'Sticky Add-to-Cart Bar', 'meyvora-convert' ); ?>
				</h2>
				<label class="cro-toggle">
					<input type="checkbox" name="sticky_cart_enabled" value="1"
						<?php checked( $settings->is_feature_enabled( 'sticky_cart' ) ); ?> />
					<span class="cro-toggle-slider"></span>
				</label>
			</div>

			<p class="cro-section-description">
				<?php esc_html_e( 'Shows a sticky bar on product pages so the Add to Cart button is always visible while scrolling.', 'meyvora-convert' ); ?>
			</p>

			<div class="cro-settings-fields">
				<div class="cro-fields-grid">
					<div class="cro-field cro-col-12">
						<div class="cro-field__control">
							<label>
								<input type="checkbox" name="sticky_cart_mobile_only" value="1"
									<?php checked( ! empty( $sticky_cart['show_on_mobile_only'] ) ); ?> />
								<?php esc_html_e( 'Show on mobile devices only (recommended)', 'meyvora-convert' ); ?>
							</label>
						</div>
						<span class="cro-help"><?php esc_html_e( 'Desktop users typically don\'t need this as the page is shorter.', 'meyvora-convert' ); ?></span>
					</div>
					<div class="cro-field cro-col-12">
						<label for="sticky_cart_scroll" class="cro-field__label"><?php esc_html_e( 'Show After Scrolling', 'meyvora-convert' ); ?></label>
						<div class="cro-field__control">
							<input type="number" id="sticky_cart_scroll" name="sticky_cart_scroll"
								value="<?php echo esc_attr( $sticky_cart['show_after_scroll'] ); ?>"
								min="0" max="1000" class="small-text" /> px
						</div>
						<span class="cro-help"><?php esc_html_e( 'How far the user must scroll before the bar appears.', 'meyvora-convert' ); ?></span>
					</div>
					<div class="cro-field cro-col-12">
						<span class="cro-field__label"><?php esc_html_e( 'Content', 'meyvora-convert' ); ?></span>
						<div class="cro-field__control">
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
					<div class="cro-field cro-col-6">
						<label for="sticky_cart_tone" class="cro-field__label"><?php esc_html_e( 'Tone', 'meyvora-convert' ); ?></label>
						<div class="cro-field__control">
							<select name="sticky_cart_tone" id="sticky_cart_tone" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Tone', 'meyvora-convert' ); ?>">
								<?php foreach ( $default_copy_tones as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( isset( $sticky_cart['tone'] ) ? $sticky_cart['tone'] : 'neutral', $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<span class="cro-help"><?php esc_html_e( 'Affects default button copy when left blank.', 'meyvora-convert' ); ?></span>
					</div>
					<div class="cro-field cro-col-6">
						<label for="sticky_cart_button_text" class="cro-field__label"><?php esc_html_e( 'Button text', 'meyvora-convert' ); ?></label>
						<div class="cro-field__control">
							<input type="text" id="sticky_cart_button_text" name="sticky_cart_button_text"
								value="<?php echo esc_attr( $sticky_cart['button_text'] ); ?>"
								class="regular-text" placeholder="<?php echo esc_attr( class_exists( 'CRO_Default_Copy' ) ? CRO_Default_Copy::get( 'sticky_cart', isset( $sticky_cart['tone'] ) ? $sticky_cart['tone'] : 'neutral', 'button_text' ) : __( 'Add to cart', 'meyvora-convert' ) ); ?>" />
						</div>
						<span class="cro-help"><?php esc_html_e( 'Leave empty to use the default for the selected tone.', 'meyvora-convert' ); ?></span>
					</div>
					<div class="cro-field cro-col-12">
						<span class="cro-field__label"><?php esc_html_e( 'Colors', 'meyvora-convert' ); ?></span>
						<div class="cro-field__control">
							<label>
								<?php esc_html_e( 'Background:', 'meyvora-convert' ); ?>
								<input type="text" name="sticky_cart_bg_color"
									value="<?php echo esc_attr( $sticky_cart['bg_color'] ); ?>"
									class="cro-color-picker" />
							</label>
							<br><br>
							<label>
								<?php esc_html_e( 'Button:', 'meyvora-convert' ); ?>
								<input type="text" name="sticky_cart_button_color"
									value="<?php echo esc_attr( $sticky_cart['button_bg_color'] ); ?>"
									class="cro-color-picker" />
							</label>
						</div>
					</div>
				</div>
			</div>

			<div class="cro-preview-box">
				<h4><?php esc_html_e( 'Preview', 'meyvora-convert' ); ?></h4>
				<div class="cro-sticky-cart-preview" aria-hidden="true">
					<!-- JavaScript can render live preview here -->
				</div>
			</div>
		</div>

		<!-- Free Shipping Bar Section -->
		<div class="cro-settings-section">
			<div class="cro-section-header">
				<h2>
					<?php echo CRO_Icons::svg( 'truck', array( 'class' => 'cro-ico' ) ); ?>
					<?php esc_html_e( 'Free Shipping Progress Bar', 'meyvora-convert' ); ?>
				</h2>
				<label class="cro-toggle">
					<input type="checkbox" name="shipping_bar_enabled" value="1"
						<?php checked( $settings->is_feature_enabled( 'shipping_bar' ) ); ?> />
					<span class="cro-toggle-slider"></span>
				</label>
			</div>

			<p class="cro-section-description">
				<?php esc_html_e( 'Shows customers how close they are to qualifying for free shipping.', 'meyvora-convert' ); ?>
			</p>

			<div class="cro-settings-fields">
				<div class="cro-fields-grid">
					<div class="cro-field cro-col-12">
						<span class="cro-field__label"><?php esc_html_e( 'Threshold', 'meyvora-convert' ); ?></span>
						<div class="cro-field__control">
							<label>
								<input type="checkbox" name="shipping_bar_use_woo" value="1"
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
								<input type="number" name="shipping_bar_threshold"
									value="<?php echo esc_attr( $shipping_bar['threshold'] ); ?>"
									min="0" step="0.01" class="small-text" />
							</label>
						</div>
					</div>
					<div class="cro-field cro-col-6">
						<label for="shipping_bar_tone" class="cro-field__label"><?php esc_html_e( 'Tone', 'meyvora-convert' ); ?></label>
						<div class="cro-field__control">
							<select name="shipping_bar_tone" id="shipping_bar_tone" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Tone', 'meyvora-convert' ); ?>">
								<?php foreach ( $default_copy_tones as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( isset( $shipping_bar['tone'] ) ? $shipping_bar['tone'] : 'neutral', $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<span class="cro-help"><?php esc_html_e( 'Affects default messages when left blank.', 'meyvora-convert' ); ?></span>
					</div>
					<div class="cro-field cro-col-6">
						<label for="shipping_bar_position" class="cro-field__label"><?php esc_html_e( 'Position', 'meyvora-convert' ); ?></label>
						<div class="cro-field__control">
							<select id="shipping_bar_position" name="shipping_bar_position" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Top of page (sticky)', 'meyvora-convert' ); ?>">
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
					<div class="cro-field cro-col-12">
						<label for="shipping_bar_message_progress" class="cro-field__label"><?php esc_html_e( 'Progress message', 'meyvora-convert' ); ?></label>
						<div class="cro-field__control">
							<input type="text" id="shipping_bar_message_progress" name="shipping_bar_message_progress"
								value="<?php echo esc_attr( $shipping_bar['message_progress'] ); ?>"
								class="large-text" placeholder="<?php echo esc_attr( class_exists( 'CRO_Default_Copy' ) ? CRO_Default_Copy::get( 'shipping_bar', isset( $shipping_bar['tone'] ) ? $shipping_bar['tone'] : 'neutral', 'progress' ) : __( 'Add {amount} more for free shipping', 'meyvora-convert' ) ); ?>" />
						</div>
						<span class="cro-help"><?php esc_html_e( 'Placeholder: {amount} — remaining amount needed for free shipping.', 'meyvora-convert' ); ?></span>
					</div>
					<div class="cro-field cro-col-12">
						<label for="shipping_bar_message_achieved" class="cro-field__label"><?php esc_html_e( 'Success message', 'meyvora-convert' ); ?></label>
						<div class="cro-field__control">
							<input type="text" id="shipping_bar_message_achieved" name="shipping_bar_message_achieved"
								value="<?php echo esc_attr( $shipping_bar['message_achieved'] ); ?>"
								class="large-text" placeholder="<?php echo esc_attr( class_exists( 'CRO_Default_Copy' ) ? CRO_Default_Copy::get( 'shipping_bar', isset( $shipping_bar['tone'] ) ? $shipping_bar['tone'] : 'neutral', 'achieved' ) : __( 'You\'ve got free shipping', 'meyvora-convert' ) ); ?>" />
						</div>
						<span class="cro-help"><?php esc_html_e( 'Shown when cart qualifies for free shipping.', 'meyvora-convert' ); ?></span>
					</div>
					<div class="cro-field cro-col-12">
						<span class="cro-field__label"><?php esc_html_e( 'Show On', 'meyvora-convert' ); ?></span>
						<div class="cro-field__control">
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
					<div class="cro-field cro-col-12">
						<span class="cro-field__label"><?php esc_html_e( 'Colors', 'meyvora-convert' ); ?></span>
						<div class="cro-field__control">
							<label>
								<?php esc_html_e( 'Background:', 'meyvora-convert' ); ?>
								<input type="text" name="shipping_bar_bg_color"
									value="<?php echo esc_attr( $shipping_bar['bg_color'] ); ?>"
									class="cro-color-picker" />
							</label>
							<br><br>
							<label>
								<?php esc_html_e( 'Progress bar:', 'meyvora-convert' ); ?>
								<input type="text" name="shipping_bar_bar_color"
									value="<?php echo esc_attr( $shipping_bar['bar_color'] ); ?>"
									class="cro-color-picker" />
							</label>
						</div>
					</div>
				</div>
			</div>

			<div class="cro-preview-box">
				<h4><?php esc_html_e( 'Preview', 'meyvora-convert' ); ?></h4>
				<div class="cro-shipping-bar-preview" aria-hidden="true">
					<!-- JavaScript can render live preview here -->
				</div>
			</div>
		</div>

		<!-- Low Stock Urgency Section -->
		<div class="cro-settings-section">
			<div class="cro-section-header">
				<h2>
					<?php echo CRO_Icons::svg( 'alert', array( 'class' => 'cro-ico' ) ); ?>
					<?php esc_html_e( 'Low Stock Urgency', 'meyvora-convert' ); ?>
				</h2>
			</div>
			<p class="cro-section-description">
				<?php esc_html_e( 'Shows a message on product pages when stock is low (e.g. "Only 3 left"). Honest urgency without fake scarcity.', 'meyvora-convert' ); ?>
			</p>
			<div class="cro-settings-fields">
				<div class="cro-fields-grid">
					<div class="cro-field cro-col-6">
						<label for="stock_urgency_tone" class="cro-field__label"><?php esc_html_e( 'Tone', 'meyvora-convert' ); ?></label>
						<div class="cro-field__control">
							<select name="stock_urgency_tone" id="stock_urgency_tone" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Tone', 'meyvora-convert' ); ?>">
								<?php foreach ( $default_copy_tones as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( isset( $stock_urgency['tone'] ) ? $stock_urgency['tone'] : 'neutral', $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					<div class="cro-field cro-col-6">
						<label for="stock_urgency_message" class="cro-field__label"><?php esc_html_e( 'Message', 'meyvora-convert' ); ?></label>
						<div class="cro-field__control">
							<input type="text" id="stock_urgency_message" name="stock_urgency_message"
								value="<?php echo esc_attr( $stock_urgency['message_template'] ); ?>"
								class="large-text" placeholder="<?php echo esc_attr( class_exists( 'CRO_Default_Copy' ) ? CRO_Default_Copy::get( 'stock_urgency', isset( $stock_urgency['tone'] ) ? $stock_urgency['tone'] : 'neutral', 'message' ) : __( '{count} left in stock', 'meyvora-convert' ) ); ?>" />
						</div>
						<span class="cro-help"><?php esc_html_e( 'Placeholder: {count} — number of items left in stock. Leave empty for default.', 'meyvora-convert' ); ?></span>
					</div>
					<div class="cro-field cro-col-6">
						<label class="cro-field__label" for="stock-urgency-threshold">
							<?php esc_html_e( 'Show urgency when stock is below', 'meyvora-convert' ); ?>
						</label>
						<div class="cro-field__control">
							<input type="number" id="stock-urgency-threshold"
								name="stock_urgency_threshold" min="1" max="100"
								value="<?php echo esc_attr( cro_settings()->get( 'boosters', 'stock_urgency_threshold', 10 ) ); ?>"
								style="width:80px;" />
							<span class="cro-help"><?php esc_html_e( 'units (default: 10)', 'meyvora-convert' ); ?></span>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Trust Badges Section -->
		<div class="cro-settings-section">
			<div class="cro-section-header">
				<h2>
					<?php echo CRO_Icons::svg( 'shield', array( 'class' => 'cro-ico' ) ); ?>
					<?php esc_html_e( 'Trust Badges', 'meyvora-convert' ); ?>
				</h2>
				<label class="cro-toggle">
					<input type="checkbox" name="trust_badges_enabled" value="1"
						<?php checked( $settings->is_feature_enabled( 'trust_badges' ) ); ?> />
					<span class="cro-toggle-slider"></span>
				</label>
			</div>

			<p class="cro-section-description">
				<?php esc_html_e( 'Show trust badges (secure checkout, free shipping, returns) on product, cart, and checkout to build confidence.', 'meyvora-convert' ); ?>
			</p>
		</div>

		<?php submit_button( __( 'Save All Settings', 'meyvora-convert' ), 'primary', 'cro_save_boosters' ); ?>

	</form>
