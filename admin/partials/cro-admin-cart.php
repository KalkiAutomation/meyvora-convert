<?php
/**
 * Admin cart optimization page – cart page optimizations
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

$settings = cro_settings();

// Handle form submission.
$nonce_valid = isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'cro_cart_nonce' );
if ( isset( $_POST['cro_save_cart'] ) && $nonce_valid ) {

	$settings->set( 'general', 'cart_optimizer_enabled', ! empty( $_POST['cart_enabled'] ) );

	// Trust message.
	$settings->set( 'cart_optimizer', 'show_trust_under_total', ! empty( $_POST['show_trust'] ) );
	$settings->set( 'cart_optimizer', 'trust_message', sanitize_text_field( wp_unslash( $_POST['trust_message'] ?? '' ) ) );

	// Urgency.
	$settings->set( 'cart_optimizer', 'show_urgency', ! empty( $_POST['show_urgency'] ) );
	$settings->set( 'cart_optimizer', 'urgency_message', sanitize_text_field( wp_unslash( $_POST['urgency_message'] ?? '' ) ) );
	$settings->set( 'cart_optimizer', 'urgency_type', sanitize_text_field( wp_unslash( $_POST['urgency_type'] ?? 'demand' ) ) );

	// Benefits list.
	$settings->set( 'cart_optimizer', 'show_benefits', ! empty( $_POST['show_benefits'] ) );
	$benefits_raw = isset( $_POST['benefits_list'] ) ? wp_unslash( $_POST['benefits_list'] ) : '';
	$benefits     = array_filter( array_map( 'sanitize_text_field', explode( "\n", (string) ( $benefits_raw ?? '' ) ) ) );
	$settings->set( 'cart_optimizer', 'benefits_list', $benefits );

	// Checkout button.
	$settings->set( 'cart_optimizer', 'sticky_checkout_button', ! empty( $_POST['sticky_checkout'] ) );
	$settings->set( 'cart_optimizer', 'checkout_button_text', sanitize_text_field( wp_unslash( $_POST['checkout_text'] ?? '' ) ) );

	// Exit-intent nudge (cart/checkout, once per session, mobile-safe).
	$settings->set( 'cart_optimizer', 'exit_intent_nudge', ! empty( $_POST['exit_intent_nudge'] ) );
	$settings->set( 'cart_optimizer', 'exit_intent_message', sanitize_text_field( wp_unslash( $_POST['exit_intent_message'] ?? '' ) ) );
	$settings->set( 'cart_optimizer', 'exit_intent_cta', sanitize_text_field( wp_unslash( $_POST['exit_intent_cta'] ?? '' ) ) );

	// Upsells.
	$settings->set( 'cart_optimizer', 'upsells', array(
		'enabled' => ! empty( $_POST['cart_upsells_enabled'] ),
		'heading' => sanitize_text_field( wp_unslash( $_POST['cart_upsells_heading'] ?? '' ) ),
		'limit'   => max( 1, min( 6, absint( $_POST['cart_upsells_limit'] ?? 3 ) ) ),
	) );

	// Cross-sells.
	$settings->set( 'cart_optimizer', 'cross_sells', array(
		'enabled' => ! empty( $_POST['cart_cross_sells_enabled'] ),
		'heading' => sanitize_text_field( wp_unslash( $_POST['cart_cross_sells_heading'] ?? '' ) ),
		'limit'   => max( 1, min( 6, absint( $_POST['cart_cross_sells_limit'] ?? 3 ) ) ),
	) );

	// Offer banner (classic cart/checkout).
	if ( method_exists( $settings, 'get_offer_banner_settings' ) ) {
		$settings->set( 'offer_banner', 'enable_offer_banner', ! empty( $_POST['offer_banner_enabled'] ) );
		$pos = isset( $_POST['offer_banner_position'] ) ? sanitize_text_field( wp_unslash( $_POST['offer_banner_position'] ) ) : 'cart';
		if ( ! in_array( $pos, array( 'cart', 'checkout', 'both' ), true ) ) {
			$pos = 'cart';
		}
		$settings->set( 'offer_banner', 'banner_position', $pos );
	}

	// Banner frequency cap (max shows per visitor per 24h for shipping bar, trust, urgency, offer).
	if ( method_exists( $settings, 'get_banner_frequency_settings' ) ) {
		$max = isset( $_POST['banner_frequency_max_per_24h'] ) ? absint( $_POST['banner_frequency_max_per_24h'] ) : 0;
		$settings->set( 'banner_frequency', 'max_per_24h', $max );
	}

	// Abandoned cart reminders: enable + require opt-in + email delay hours + discount rules.
	if ( method_exists( $settings, 'get_abandoned_cart_settings' ) ) {
		$settings->set( 'abandoned_cart', 'enable_abandoned_cart_emails', ! empty( $_POST['cro_abandoned_cart_emails'] ) );
		$settings->set( 'abandoned_cart', 'require_opt_in', ! empty( $_POST['cro_abandoned_cart_require_opt_in'] ) );
		$settings->set( 'abandoned_cart', 'email_1_delay_hours', max( 0, (int) ( $_POST['cro_email_1_delay_hours'] ?? 1 ) ) );
		$settings->set( 'abandoned_cart', 'email_2_delay_hours', max( 0, (int) ( $_POST['cro_email_2_delay_hours'] ?? 24 ) ) );
		$settings->set( 'abandoned_cart', 'email_3_delay_hours', max( 0, (int) ( $_POST['cro_email_3_delay_hours'] ?? 72 ) ) );
		$settings->set( 'abandoned_cart', 'enable_discount_in_emails', ! empty( $_POST['cro_enable_discount_in_emails'] ) );
		$discount_type = isset( $_POST['cro_discount_type'] ) ? sanitize_text_field( wp_unslash( $_POST['cro_discount_type'] ) ) : 'percent';
		if ( ! in_array( $discount_type, array( 'percent', 'fixed_cart', 'free_shipping' ), true ) ) {
			$discount_type = 'percent';
		}
		$settings->set( 'abandoned_cart', 'discount_type', $discount_type );
		$settings->set( 'abandoned_cart', 'discount_amount', isset( $_POST['cro_discount_amount'] ) ? ( function_exists( 'wc_format_decimal' ) ? wc_format_decimal( wp_unslash( $_POST['cro_discount_amount'] ) ) : (float) $_POST['cro_discount_amount'] ) : 10 );
		$settings->set( 'abandoned_cart', 'coupon_ttl_hours', max( 1, (int) ( $_POST['cro_coupon_ttl_hours'] ?? 48 ) ) );
		$min_cart = isset( $_POST['cro_minimum_cart_total'] ) ? trim( (string) wp_unslash( $_POST['cro_minimum_cart_total'] ) ) : '';
		$settings->set( 'abandoned_cart', 'minimum_cart_total', $min_cart !== '' && is_numeric( $min_cart ) ? ( function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $min_cart ) : (float) $min_cart ) : '' );
		$settings->set( 'abandoned_cart', 'exclude_sale_items', ! empty( $_POST['cro_exclude_sale_items'] ) );
		$include_cat = isset( $_POST['cro_include_categories'] ) && is_array( $_POST['cro_include_categories'] ) ? array_map( 'absint', wp_unslash( $_POST['cro_include_categories'] ) ) : array();
		$settings->set( 'abandoned_cart', 'include_categories', array_values( array_filter( $include_cat ) ) );
		$exclude_cat = isset( $_POST['cro_exclude_categories'] ) && is_array( $_POST['cro_exclude_categories'] ) ? array_map( 'absint', wp_unslash( $_POST['cro_exclude_categories'] ) ) : array();
		$settings->set( 'abandoned_cart', 'exclude_categories', array_values( array_filter( $exclude_cat ) ) );
		$include_prod = isset( $_POST['cro_include_products'] ) && is_array( $_POST['cro_include_products'] ) ? array_map( 'absint', wp_unslash( $_POST['cro_include_products'] ) ) : array();
		$settings->set( 'abandoned_cart', 'include_products', array_values( array_filter( $include_prod ) ) );
		$exclude_prod = isset( $_POST['cro_exclude_products'] ) && is_array( $_POST['cro_exclude_products'] ) ? array_map( 'absint', wp_unslash( $_POST['cro_exclude_products'] ) ) : array();
		$settings->set( 'abandoned_cart', 'exclude_products', array_values( array_filter( $exclude_prod ) ) );
		$per_cat = array();
		if ( isset( $_POST['cro_per_category_discount_cat'] ) && isset( $_POST['cro_per_category_discount_amount'] ) && is_array( $_POST['cro_per_category_discount_cat'] ) && is_array( $_POST['cro_per_category_discount_amount'] ) ) {
			$cats = array_map( 'absint', wp_unslash( $_POST['cro_per_category_discount_cat'] ) );
			$amts = wp_unslash( $_POST['cro_per_category_discount_amount'] );
			foreach ( $cats as $idx => $cat_id ) {
				if ( $cat_id > 0 && isset( $amts[ $idx ] ) && is_numeric( $amts[ $idx ] ) ) {
					$per_cat[ $cat_id ] = (float) $amts[ $idx ];
				}
			}
		}
		$settings->set( 'abandoned_cart', 'per_category_discount', $per_cat );
		$settings->set( 'abandoned_cart', 'generate_coupon_for_email', max( 1, min( 3, (int) ( $_POST['cro_generate_coupon_for_email'] ?? 1 ) ) ) );
	}

	echo '<div class="cro-ui-notice cro-ui-toast-placeholder" role="status"><p>' . esc_html__( 'Cart settings saved!', 'meyvora-convert' ) . '</p></div>';
}

$cart_settings = wp_parse_args(
	$settings->get_group( 'cart_optimizer' ),
	array(
		'show_trust_under_total' => false,
		'trust_message'           => __( 'Secure payment - Fast shipping - Easy returns', 'meyvora-convert' ),
		'show_urgency'            => false,
		'urgency_message'         => __( 'Items in your cart are in high demand!', 'meyvora-convert' ),
		'urgency_type'            => 'demand',
		'show_benefits'           => false,
		'benefits_list'           => array(
			__( 'Free shipping on orders over $50', 'meyvora-convert' ),
			__( '30-day returns', 'meyvora-convert' ),
			__( 'Secure checkout', 'meyvora-convert' ),
		),
		'sticky_checkout_button'  => false,
		'checkout_button_text'    => __( 'Proceed to Checkout', 'meyvora-convert' ),
		'exit_intent_nudge'       => false,
		'exit_intent_message'     => __( 'Complete your order now — your discount is ready', 'meyvora-convert' ),
		'exit_intent_cta'         => __( 'Complete order', 'meyvora-convert' ),
	)
);
?>

	<form method="post" id="cro-cart-form">
		<?php wp_nonce_field( 'cro_cart_nonce' ); ?>

		<!-- Master Toggle -->
		<div class="cro-master-toggle">
			<label class="cro-toggle-large">
				<span class="cro-toggle">
					<input type="checkbox" name="cart_enabled" value="1"
						<?php checked( $settings->is_feature_enabled( 'cart_optimizer' ) ); ?> />
					<span class="cro-toggle-slider"></span>
				</span>
				<span class="cro-toggle-label">
					<?php esc_html_e( 'Enable Cart Optimizations', 'meyvora-convert' ); ?>
				</span>
			</label>
		</div>

		<!-- Trust Message Section -->
		<div class="cro-settings-section">
			<h2>
				<?php echo CRO_Icons::svg( 'shield', array( 'class' => 'cro-ico' ) ); ?>
				<?php esc_html_e( 'Trust Message', 'meyvora-convert' ); ?>
			</h2>

			<div class="cro-fields-grid cro-fields-grid--1col">
				<div class="cro-field cro-col-12">
					<div class="cro-field__control">
						<label>
							<input type="checkbox" name="show_trust" value="1"
								<?php checked( ! empty( $cart_settings['show_trust_under_total'] ) ); ?> />
							<?php esc_html_e( 'Show trust message under cart total', 'meyvora-convert' ); ?>
						</label>
					</div>
				</div>
				<div class="cro-field cro-col-12">
					<label for="trust_message" class="cro-field__label"><?php esc_html_e( 'Message', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<input type="text" id="trust_message" name="trust_message"
							value="<?php echo esc_attr( $cart_settings['trust_message'] ); ?>"
							class="large-text" />
					</div>
					<span class="cro-help"><?php esc_html_e( 'Use checkmarks or emojis for visual appeal.', 'meyvora-convert' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Urgency Section -->
		<div class="cro-settings-section">
			<h2>
				<?php echo CRO_Icons::svg( 'alert', array( 'class' => 'cro-ico' ) ); ?>
				<?php esc_html_e( 'Urgency Messaging', 'meyvora-convert' ); ?>
			</h2>
			<p class="cro-section-description">
				<?php esc_html_e( 'Create honest urgency without fake countdown timers.', 'meyvora-convert' ); ?>
			</p>

			<div class="cro-fields-grid">
				<div class="cro-field cro-col-12">
					<div class="cro-field__control">
						<label>
							<input type="checkbox" name="show_urgency" value="1"
								<?php checked( ! empty( $cart_settings['show_urgency'] ) ); ?> />
							<?php esc_html_e( 'Show urgency message on cart page', 'meyvora-convert' ); ?>
						</label>
					</div>
				</div>
				<div class="cro-field cro-col-6">
					<label for="urgency_type" class="cro-field__label"><?php esc_html_e( 'Type', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<select id="urgency_type" name="urgency_type" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'High demand message', 'meyvora-convert' ); ?>">
							<option value="demand" <?php selected( $cart_settings['urgency_type'], 'demand' ); ?>>
								<?php esc_html_e( 'High demand message', 'meyvora-convert' ); ?>
							</option>
							<option value="stock" <?php selected( $cart_settings['urgency_type'], 'stock' ); ?>>
								<?php esc_html_e( 'Low stock warning (real data)', 'meyvora-convert' ); ?>
							</option>
							<option value="custom" <?php selected( $cart_settings['urgency_type'], 'custom' ); ?>>
								<?php esc_html_e( 'Custom message', 'meyvora-convert' ); ?>
							</option>
						</select>
					</div>
				</div>
				<div class="cro-field cro-col-6">
					<label for="urgency_message" class="cro-field__label"><?php esc_html_e( 'Message', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<input type="text" id="urgency_message" name="urgency_message"
							value="<?php echo esc_attr( $cart_settings['urgency_message'] ); ?>"
							class="large-text" />
					</div>
				</div>
			</div>
		</div>

		<!-- Benefits List Section -->
		<div class="cro-settings-section">
			<h2>
				<?php echo CRO_Icons::svg( 'check', array( 'class' => 'cro-ico' ) ); ?>
				<?php esc_html_e( 'Benefits List', 'meyvora-convert' ); ?>
			</h2>

			<div class="cro-fields-grid cro-fields-grid--1col">
				<div class="cro-field cro-col-12">
					<div class="cro-field__control">
						<label>
							<input type="checkbox" name="show_benefits" value="1"
								<?php checked( ! empty( $cart_settings['show_benefits'] ) ); ?> />
							<?php esc_html_e( 'Show benefits list near checkout button', 'meyvora-convert' ); ?>
						</label>
					</div>
				</div>
				<div class="cro-field cro-col-12">
					<label for="benefits_list" class="cro-field__label"><?php esc_html_e( 'Benefits', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<textarea id="benefits_list" name="benefits_list" rows="4" class="large-text"
							placeholder="<?php esc_attr_e( 'One benefit per line', 'meyvora-convert' ); ?>"
						><?php echo esc_textarea( implode( "\n", (array) $cart_settings['benefits_list'] ) ); ?></textarea>
					</div>
					<span class="cro-help"><?php esc_html_e( 'Enter one benefit per line. Keep them short.', 'meyvora-convert' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Checkout Button Section -->
		<div class="cro-settings-section">
			<h2>
				<?php echo CRO_Icons::svg( 'shopping-cart', array( 'class' => 'cro-ico' ) ); ?>
				<?php esc_html_e( 'Checkout Button', 'meyvora-convert' ); ?>
			</h2>

			<div class="cro-fields-grid cro-fields-grid--1col">
				<div class="cro-field cro-col-12">
					<div class="cro-field__control">
						<label>
							<input type="checkbox" name="sticky_checkout" value="1"
								<?php checked( ! empty( $cart_settings['sticky_checkout_button'] ) ); ?> />
							<?php esc_html_e( 'Make checkout button sticky on mobile', 'meyvora-convert' ); ?>
						</label>
					</div>
					<span class="cro-help">
						<?php esc_html_e( 'Keeps the checkout button visible while scrolling cart on mobile.', 'meyvora-convert' ); ?>
					</span>
				</div>
				<div class="cro-field cro-col-12">
					<label for="checkout_text" class="cro-field__label"><?php esc_html_e( 'Button Text', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<input type="text" id="checkout_text" name="checkout_text"
							value="<?php echo esc_attr( $cart_settings['checkout_button_text'] ); ?>"
							class="regular-text" />
					</div>
				</div>
			</div>
		</div>

		<!-- Exit-intent nudge (cart/checkout, once per session, mobile-safe) -->
		<div class="cro-settings-section">
			<h2>
				<?php echo CRO_Icons::svg( 'door-open', array( 'class' => 'cro-ico' ) ); ?>
				<?php esc_html_e( 'Exit-intent nudge', 'meyvora-convert' ); ?>
			</h2>
			<p class="cro-section-description">
				<?php esc_html_e( 'Show a gentle overlay on cart/checkout when the visitor is about to leave (desktop: mouse toward top; mobile: once after a short delay). No email capture — just message + CTA. Once per session.', 'meyvora-convert' ); ?>
			</p>
			<div class="cro-fields-grid cro-fields-grid--1col">
				<div class="cro-field cro-col-12">
					<div class="cro-field__control">
						<label>
							<input type="checkbox" name="exit_intent_nudge" value="1"
								<?php checked( ! empty( $cart_settings['exit_intent_nudge'] ) ); ?> />
							<?php esc_html_e( 'Show exit-intent nudge on cart and checkout', 'meyvora-convert' ); ?>
						</label>
					</div>
				</div>
				<div class="cro-field cro-col-12">
					<label for="exit_intent_message" class="cro-field__label"><?php esc_html_e( 'Message', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<input type="text" id="exit_intent_message" name="exit_intent_message"
							value="<?php echo esc_attr( $cart_settings['exit_intent_message'] ); ?>"
							class="large-text" />
					</div>
					<span class="cro-help"><?php esc_html_e( 'E.g. “Complete your order now — your discount is ready”', 'meyvora-convert' ); ?></span>
				</div>
				<div class="cro-field cro-col-12">
					<label for="exit_intent_cta" class="cro-field__label"><?php esc_html_e( 'Button text', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<input type="text" id="exit_intent_cta" name="exit_intent_cta"
							value="<?php echo esc_attr( $cart_settings['exit_intent_cta'] ); ?>"
							class="regular-text" />
					</div>
					<span class="cro-help"><?php esc_html_e( 'CTA label, e.g. “Complete order”. On cart goes to checkout; on checkout closes the nudge.', 'meyvora-convert' ); ?></span>
				</div>
			</div>
		</div>

		<?php
		$offer_banner_settings = method_exists( $settings, 'get_offer_banner_settings' ) ? $settings->get_offer_banner_settings() : array( 'enable_offer_banner' => false, 'banner_position' => 'cart' );
		$offer_banner_settings = wp_parse_args( $offer_banner_settings, array( 'enable_offer_banner' => false, 'banner_position' => 'cart' ) );
		$banner_frequency_settings = method_exists( $settings, 'get_banner_frequency_settings' ) ? $settings->get_banner_frequency_settings() : array( 'max_per_24h' => 0 );
		$banner_frequency_settings = wp_parse_args( $banner_frequency_settings, array( 'max_per_24h' => 0 ) );
		?>
		<!-- Banner frequency cap (shipping bar, trust, urgency, offer) -->
		<div class="cro-settings-section">
			<h2>
				<?php echo CRO_Icons::svg( 'eye', array( 'class' => 'cro-ico' ) ); ?>
				<?php esc_html_e( 'Banner frequency cap', 'meyvora-convert' ); ?>
			</h2>
			<p class="cro-section-description">
				<?php esc_html_e( 'Limit how often each banner/message is shown per visitor (per 24 hours). Applies to shipping bar, trust message, urgency message, and offer banner (classic and blocks). 0 = unlimited.', 'meyvora-convert' ); ?>
			</p>
			<div class="cro-fields-grid cro-fields-grid--1col">
				<div class="cro-field cro-col-12">
					<label for="banner_frequency_max_per_24h" class="cro-field__label"><?php esc_html_e( 'Max shows per 24h', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<input type="number" id="banner_frequency_max_per_24h" name="banner_frequency_max_per_24h" min="0" max="100" value="<?php echo esc_attr( (string) $banner_frequency_settings['max_per_24h'] ); ?>" class="small-text" />
					</div>
					<span class="cro-help"><?php esc_html_e( '0 = unlimited. E.g. 5 = each banner type is shown at most 5 times per visitor per 24 hours.', 'meyvora-convert' ); ?></span>
				</div>
			</div>
		</div>

		<?php
		$abandoned_cart_settings = method_exists( $settings, 'get_abandoned_cart_settings' ) ? $settings->get_abandoned_cart_settings() : array();
		$abandoned_cart_settings = wp_parse_args( $abandoned_cart_settings, array(
			'enable_abandoned_cart_emails' => false,
			'require_opt_in' => true,
			'email_1_delay_hours' => 1,
			'email_2_delay_hours' => 24,
			'email_3_delay_hours' => 72,
			'enable_discount_in_emails' => false,
			'discount_type' => 'percent',
			'discount_amount' => 10,
			'coupon_ttl_hours' => 48,
			'minimum_cart_total' => '',
			'exclude_sale_items' => false,
			'include_categories' => array(),
			'exclude_categories' => array(),
			'include_products' => array(),
			'exclude_products' => array(),
			'per_category_discount' => array(),
			'generate_coupon_for_email' => 1,
		) );
		$product_categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		if ( ! is_array( $product_categories ) ) {
			$product_categories = array();
		}
		?>
		<!-- Abandoned cart reminders -->
		<div class="cro-settings-section">
			<h2>
				<?php echo CRO_Icons::svg( 'mail', array( 'class' => 'cro-ico' ) ); ?>
				<?php esc_html_e( 'Abandoned cart reminders', 'meyvora-convert' ); ?>
			</h2>
			<p class="cro-section-description">
				<?php esc_html_e( 'Capture guest email for reminder emails only with explicit consent. Checkout: optional “Email me a reminder” checkbox. Cart: optional email field + “Send me a reminder” checkbox. Sent timestamps and last error are stored in the abandoned carts table.', 'meyvora-convert' ); ?>
			</p>
			<div class="cro-fields-grid cro-fields-grid--1col">
				<div class="cro-field cro-col-12">
					<label class="cro-field__label"><?php esc_html_e( 'Enable', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<label>
							<input type="checkbox" name="cro_abandoned_cart_emails" value="1"
								<?php checked( ! empty( $abandoned_cart_settings['enable_abandoned_cart_emails'] ) ); ?> />
							<?php esc_html_e( 'Enable abandoned cart email capture (checkout + cart)', 'meyvora-convert' ); ?>
						</label>
					</div>
				</div>
				<div class="cro-field cro-col-12">
					<label class="cro-field__label"><?php esc_html_e( 'Require opt-in', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<label>
							<input type="checkbox" name="cro_abandoned_cart_require_opt_in" value="1"
								<?php checked( ! empty( $abandoned_cart_settings['require_opt_in'] ) ); ?> />
							<?php esc_html_e( 'Require opt-in (default: on). Do not capture email without consent.', 'meyvora-convert' ); ?>
						</label>
						<span class="cro-help"><?php esc_html_e( 'Recommended for compliance. When on, email is stored only when the guest checks the reminder checkbox.', 'meyvora-convert' ); ?></span>
					</div>
				</div>
				<div class="cro-field cro-col-12">
					<label class="cro-field__label"><?php esc_html_e( 'Email schedule (hours after last activity)', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<label><?php esc_html_e( 'Email #1', 'meyvora-convert' ); ?></label>
						<input type="number" name="cro_email_1_delay_hours" value="<?php echo esc_attr( (string) $abandoned_cart_settings['email_1_delay_hours'] ); ?>" min="0" max="168" class="small-text" /> <?php esc_html_e( 'hours', 'meyvora-convert' ); ?>
						<br />
						<label><?php esc_html_e( 'Email #2', 'meyvora-convert' ); ?></label>
						<input type="number" name="cro_email_2_delay_hours" value="<?php echo esc_attr( (string) $abandoned_cart_settings['email_2_delay_hours'] ); ?>" min="0" max="720" class="small-text" /> <?php esc_html_e( 'hours', 'meyvora-convert' ); ?>
						<br />
						<label><?php esc_html_e( 'Email #3', 'meyvora-convert' ); ?></label>
						<input type="number" name="cro_email_3_delay_hours" value="<?php echo esc_attr( (string) $abandoned_cart_settings['email_3_delay_hours'] ); ?>" min="0" max="720" class="small-text" /> <?php esc_html_e( 'hours', 'meyvora-convert' ); ?>
						<span class="cro-help"><?php esc_html_e( 'Defaults: 1, 24, 72. Only sends if status=active, consent=true, email exists, cart not recovered, and no more than 3 emails sent.', 'meyvora-convert' ); ?></span>
					</div>
				</div>
				<div class="cro-field cro-col-12">
					<label class="cro-field__label"><?php esc_html_e( 'Discount in emails', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<label>
							<input type="checkbox" name="cro_enable_discount_in_emails" value="1"
								<?php checked( ! empty( $abandoned_cart_settings['enable_discount_in_emails'] ) ); ?> />
							<?php esc_html_e( 'Enable discount coupon in reminder emails', 'meyvora-convert' ); ?>
						</label>
						<span class="cro-help"><?php esc_html_e( 'Generate a single-use coupon when sending a reminder (configurable which email). Same coupon is reused for later emails.', 'meyvora-convert' ); ?></span>
					</div>
				</div>
				<div class="cro-field cro-col-12 cro-discount-rules">
					<label class="cro-field__label"><?php esc_html_e( 'Discount rules', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<div>
							<label><?php esc_html_e( 'Type', 'meyvora-convert' ); ?></label>
							<select name="cro_discount_type" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Percentage', 'meyvora-convert' ); ?>">
								<option value="percent" <?php selected( $abandoned_cart_settings['discount_type'], 'percent' ); ?>><?php esc_html_e( 'Percentage', 'meyvora-convert' ); ?></option>
								<option value="fixed_cart" <?php selected( $abandoned_cart_settings['discount_type'], 'fixed_cart' ); ?>><?php esc_html_e( 'Fixed amount', 'meyvora-convert' ); ?></option>
								<option value="free_shipping" <?php selected( $abandoned_cart_settings['discount_type'], 'free_shipping' ); ?>><?php esc_html_e( 'Free shipping', 'meyvora-convert' ); ?></option>
							</select>
							<label><?php esc_html_e( 'Amount', 'meyvora-convert' ); ?></label>
							<input type="number" name="cro_discount_amount" value="<?php echo esc_attr( $abandoned_cart_settings['discount_amount'] ); ?>" min="0" step="0.01" class="small-text" /> <span class="description"><?php esc_html_e( '(ignored for free shipping)', 'meyvora-convert' ); ?></span>
						</div>
						<div>
							<label><?php esc_html_e( 'Coupon TTL (hours)', 'meyvora-convert' ); ?></label>
							<input type="number" name="cro_coupon_ttl_hours" value="<?php echo esc_attr( (string) $abandoned_cart_settings['coupon_ttl_hours'] ); ?>" min="1" max="720" class="small-text" />
							<label><?php esc_html_e( 'Minimum cart total', 'meyvora-convert' ); ?></label>
							<input type="text" name="cro_minimum_cart_total" value="<?php echo esc_attr( $abandoned_cart_settings['minimum_cart_total'] ); ?>" class="small-text" placeholder="<?php esc_attr_e( 'Optional', 'meyvora-convert' ); ?>" />
						</div>
						<div>
							<label>
								<input type="checkbox" name="cro_exclude_sale_items" value="1" <?php checked( ! empty( $abandoned_cart_settings['exclude_sale_items'] ) ); ?> />
								<?php esc_html_e( 'Exclude sale items', 'meyvora-convert' ); ?>
							</label>
						</div>
						<div>
							<label><?php esc_html_e( 'Include categories (restrict to)', 'meyvora-convert' ); ?></label><br />
							<select name="cro_include_categories[]" multiple="multiple" class="cro-select-multi cro-selectwoo cro-select-min" data-placeholder="<?php esc_attr_e( 'Select categories…', 'meyvora-convert' ); ?>">
								<?php foreach ( $product_categories as $cat ) : ?>
									<option value="<?php echo esc_attr( (string) $cat->term_id ); ?>" <?php selected( in_array( (int) $cat->term_id, (array) $abandoned_cart_settings['include_categories'], true ) ); ?>><?php echo esc_html( $cat->name ); ?></option>
								<?php endforeach; ?>
							</select>
							<span class="description"><?php esc_html_e( 'Leave empty for all. Hold Ctrl/Cmd to select multiple.', 'meyvora-convert' ); ?></span>
						</div>
						<div>
							<label><?php esc_html_e( 'Exclude categories', 'meyvora-convert' ); ?></label><br />
							<select name="cro_exclude_categories[]" multiple="multiple" class="cro-select-multi cro-selectwoo cro-select-min" data-placeholder="<?php esc_attr_e( 'Select categories…', 'meyvora-convert' ); ?>">
								<?php foreach ( $product_categories as $cat ) : ?>
									<option value="<?php echo esc_attr( (string) $cat->term_id ); ?>" <?php selected( in_array( (int) $cat->term_id, (array) $abandoned_cart_settings['exclude_categories'], true ) ); ?>><?php echo esc_html( $cat->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div>
							<label><?php esc_html_e( 'Include products (restrict to)', 'meyvora-convert' ); ?></label><br />
							<select name="cro_include_products[]" multiple="multiple" class="cro-select-multi cro-selectwoo cro-select-products cro-select-min--wide" data-placeholder="<?php esc_attr_e( 'Search products…', 'meyvora-convert' ); ?>" data-action="cro_search_products">
								<?php
								$include_prod_ids = (array) $abandoned_cart_settings['include_products'];
								if ( ! empty( $include_prod_ids ) && function_exists( 'wc_get_products' ) ) :
									$prods = wc_get_products( array( 'include' => $include_prod_ids, 'limit' => -1, 'return' => 'ids' ) );
									foreach ( array_intersect( $include_prod_ids, $prods ) as $pid ) :
										$p = wc_get_product( $pid );
										if ( $p ) :
								?>
									<option value="<?php echo esc_attr( (string) $pid ); ?>" selected="selected"><?php echo esc_html( $p->get_name() ); ?></option>
								<?php endif; endforeach; endif; ?>
							</select>
							<span class="description"><?php esc_html_e( 'Leave empty for all. Discount applies only to these products.', 'meyvora-convert' ); ?></span>
						</div>
						<div>
							<label><?php esc_html_e( 'Exclude products', 'meyvora-convert' ); ?></label><br />
							<select name="cro_exclude_products[]" multiple="multiple" class="cro-select-multi cro-selectwoo cro-select-products cro-select-min--wide" data-placeholder="<?php esc_attr_e( 'Search products…', 'meyvora-convert' ); ?>" data-action="cro_search_products">
								<?php
								$exclude_prod_ids = (array) $abandoned_cart_settings['exclude_products'];
								if ( ! empty( $exclude_prod_ids ) && function_exists( 'wc_get_products' ) ) :
									$prods = wc_get_products( array( 'include' => $exclude_prod_ids, 'limit' => -1, 'return' => 'ids' ) );
									foreach ( array_intersect( $exclude_prod_ids, $prods ) as $pid ) :
										$p = wc_get_product( $pid );
										if ( $p ) :
								?>
									<option value="<?php echo esc_attr( (string) $pid ); ?>" selected="selected"><?php echo esc_html( $p->get_name() ); ?></option>
								<?php endif; endforeach; endif; ?>
							</select>
						</div>
						<div>
							<label><?php esc_html_e( 'Per-category discount (optional)', 'meyvora-convert' ); ?></label><br />
							<span class="description"><?php esc_html_e( 'Category → amount. Overrides single amount when set. One coupon will use the first matching category amount.', 'meyvora-convert' ); ?></span>
							<div class="cro-per-category-discount-list cro-mt-1">
								<?php
								$pcd = isset( $abandoned_cart_settings['per_category_discount'] ) && is_array( $abandoned_cart_settings['per_category_discount'] ) ? $abandoned_cart_settings['per_category_discount'] : array();
								$pcd = array_filter( $pcd, function ( $v, $k ) { return (int) $k > 0 && is_numeric( $v ); }, ARRAY_FILTER_USE_BOTH );
								if ( empty( $pcd ) ) :
									$pcd = array( '' => '' );
								endif;
								$pcd_index = 0;
								foreach ( $pcd as $pcd_cat_id => $pcd_amt ) :
								?>
								<div class="cro-per-cat-row cro-mb-1">
									<select name="cro_per_category_discount_cat[]" class="cro-selectwoo cro-per-cat-select cro-select-min" data-placeholder="<?php esc_attr_e( 'Category…', 'meyvora-convert' ); ?>">
										<option value=""><?php esc_html_e( '— Select —', 'meyvora-convert' ); ?></option>
										<?php foreach ( $product_categories as $cat ) : ?>
											<option value="<?php echo esc_attr( (string) $cat->term_id ); ?>" <?php selected( (int) $pcd_cat_id === (int) $cat->term_id ); ?>><?php echo esc_html( $cat->name ); ?></option>
										<?php endforeach; ?>
									</select>
									<input type="number" name="cro_per_category_discount_amount[]" value="<?php echo esc_attr( $pcd_amt ); ?>" min="0" step="0.01" class="small-text cro-input-num" placeholder="<?php esc_attr_e( 'Amount', 'meyvora-convert' ); ?>" />
									<?php if ( $pcd_index > 0 ) : ?>
										<button type="button" class="button cro-remove-per-cat"><?php esc_html_e( 'Remove', 'meyvora-convert' ); ?></button>
									<?php endif; ?>
								</div>
								<?php $pcd_index++; endforeach; ?>
								<button type="button" class="button cro-add-per-cat"><?php esc_html_e( 'Add category discount', 'meyvora-convert' ); ?></button>
							</div>
						</div>
						<div>
							<label><?php esc_html_e( 'Generate coupon for email', 'meyvora-convert' ); ?></label>
							<select name="cro_generate_coupon_for_email" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Email #1 only', 'meyvora-convert' ); ?>">
								<option value="1" <?php selected( (int) ( $abandoned_cart_settings['generate_coupon_for_email'] ?? 1 ), 1 ); ?>><?php esc_html_e( 'Email #1 only', 'meyvora-convert' ); ?></option>
								<option value="2" <?php selected( (int) ( $abandoned_cart_settings['generate_coupon_for_email'] ?? 1 ), 2 ); ?>><?php esc_html_e( 'Email #2 only', 'meyvora-convert' ); ?></option>
								<option value="3" <?php selected( (int) ( $abandoned_cart_settings['generate_coupon_for_email'] ?? 1 ), 3 ); ?>><?php esc_html_e( 'Email #3 only', 'meyvora-convert' ); ?></option>
							</select>
							<span class="description"><?php esc_html_e( 'Coupon is created once and reused in later emails.', 'meyvora-convert' ); ?></span>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Upsells on Cart Page -->
		<div class="cro-settings-section">
			<h2>
				<?php echo CRO_Icons::svg( 'trending-up', array( 'class' => 'cro-ico' ) ); ?>
				<?php esc_html_e( 'Upsells on Cart Page', 'meyvora-convert' ); ?>
			</h2>
			<?php $upsell_settings = (array) $settings->get( 'cart_optimizer', 'upsells', array() ); ?>
			<div class="cro-fields-grid cro-fields-grid--1col">
				<div class="cro-field cro-col-12">
					<div class="cro-field__control">
						<label>
							<input type="checkbox" name="cart_upsells_enabled" value="1"
								<?php checked( ! empty( $upsell_settings['enabled'] ) ); ?> />
							<?php esc_html_e( 'Show upsell products on cart page', 'meyvora-convert' ); ?>
						</label>
					</div>
				</div>
				<div class="cro-field cro-col-6">
					<label for="cart_upsells_heading" class="cro-field__label"><?php esc_html_e( 'Heading', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<input type="text" id="cart_upsells_heading" name="cart_upsells_heading" class="regular-text"
							value="<?php echo esc_attr( $upsell_settings['heading'] ?? '' ); ?>"
							placeholder="<?php esc_attr_e( 'You might also like', 'meyvora-convert' ); ?>" />
					</div>
				</div>
				<div class="cro-field cro-col-6">
					<label for="cart_upsells_limit" class="cro-field__label"><?php esc_html_e( 'Max products', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<input type="number" id="cart_upsells_limit" name="cart_upsells_limit" min="1" max="6" class="small-text"
							value="<?php echo esc_attr( $upsell_settings['limit'] ?? 3 ); ?>" />
					</div>
				</div>
			</div>
		</div>

		<!-- Cross-Sells on Cart Page -->
		<div class="cro-settings-section">
			<h2>
				<?php echo CRO_Icons::svg( 'shuffle', array( 'class' => 'cro-ico' ) ); ?>
				<?php esc_html_e( 'Cross-Sells on Cart Page', 'meyvora-convert' ); ?>
			</h2>
			<?php $cross_sell_settings = (array) $settings->get( 'cart_optimizer', 'cross_sells', array() ); ?>
			<div class="cro-fields-grid cro-fields-grid--1col">
				<div class="cro-field cro-col-12">
					<div class="cro-field__control">
						<label>
							<input type="checkbox" name="cart_cross_sells_enabled" value="1"
								<?php checked( ! empty( $cross_sell_settings['enabled'] ) ); ?> />
							<?php esc_html_e( 'Show cross-sell products on cart page', 'meyvora-convert' ); ?>
						</label>
					</div>
				</div>
				<div class="cro-field cro-col-6">
					<label for="cart_cross_sells_heading" class="cro-field__label"><?php esc_html_e( 'Heading', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<input type="text" id="cart_cross_sells_heading" name="cart_cross_sells_heading" class="regular-text"
							value="<?php echo esc_attr( $cross_sell_settings['heading'] ?? '' ); ?>"
							placeholder="<?php esc_attr_e( 'Customers also bought', 'meyvora-convert' ); ?>" />
					</div>
				</div>
				<div class="cro-field cro-col-6">
					<label for="cart_cross_sells_limit" class="cro-field__label"><?php esc_html_e( 'Max products', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<input type="number" id="cart_cross_sells_limit" name="cart_cross_sells_limit" min="1" max="6" class="small-text"
							value="<?php echo esc_attr( $cross_sell_settings['limit'] ?? 3 ); ?>" />
					</div>
				</div>
			</div>
		</div>

		<!-- Offer Banner Section (classic cart/checkout) -->
		<div class="cro-settings-section">
			<h2>
				<?php echo CRO_Icons::svg( 'tag', array( 'class' => 'cro-ico' ) ); ?>
				<?php esc_html_e( 'Offer Banner', 'meyvora-convert' ); ?>
			</h2>
			<p class="cro-section-description">
				<?php esc_html_e( 'Show a “You qualify for X% off — Apply coupon” banner on classic cart/checkout. Coupon is generated by the offer engine when the visitor qualifies.', 'meyvora-convert' ); ?>
			</p>
			<div class="cro-fields-grid cro-fields-grid--1col">
				<div class="cro-field cro-col-12">
					<div class="cro-field__control">
						<label>
							<input type="checkbox" name="offer_banner_enabled" value="1"
								<?php checked( ! empty( $offer_banner_settings['enable_offer_banner'] ) ); ?> />
							<?php esc_html_e( 'Show offer banner on cart / checkout', 'meyvora-convert' ); ?>
						</label>
					</div>
				</div>
				<div class="cro-field cro-col-12">
					<label for="offer_banner_position" class="cro-field__label"><?php esc_html_e( 'Position', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<select id="offer_banner_position" name="offer_banner_position" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Cart only', 'meyvora-convert' ); ?>">
							<option value="cart" <?php selected( $offer_banner_settings['banner_position'], 'cart' ); ?>><?php esc_html_e( 'Cart only', 'meyvora-convert' ); ?></option>
							<option value="checkout" <?php selected( $offer_banner_settings['banner_position'], 'checkout' ); ?>><?php esc_html_e( 'Checkout only', 'meyvora-convert' ); ?></option>
							<option value="both" <?php selected( $offer_banner_settings['banner_position'], 'both' ); ?>><?php esc_html_e( 'Cart and checkout', 'meyvora-convert' ); ?></option>
						</select>
					</div>
					<span class="cro-help"><?php esc_html_e( 'Where to show the “Apply coupon” offer banner.', 'meyvora-convert' ); ?></span>
				</div>
			</div>
		</div>

		<?php submit_button( __( 'Save Cart Settings', 'meyvora-convert' ), 'primary', 'cro_save_cart' ); ?>

	</form>

<script>
(function($) {
	$('.cro-per-category-discount-list').on('click', '.cro-add-per-cat', function() {
		var $list = $(this).closest('.cro-per-category-discount-list');
		var $first = $list.find('.cro-per-cat-row').first();
		if (!$first.length) return;
		var $row = $first.clone();
		$row.find('select').val('');
		$row.find('input[type="number"]').val('');
		$row.find('.cro-remove-per-cat').remove();
		$row.append(' <button type="button" class="button cro-remove-per-cat"><?php echo esc_js( __( 'Remove', 'meyvora-convert' ) ); ?></button>');
		$row.insertBefore($list.find('.cro-add-per-cat'));
		if ($.fn.selectWoo) {
			$row.find('select.cro-selectwoo').selectWoo('destroy').off('select2:unselect');
			$list.find('.cro-per-cat-select').each(function() {
				if (!$(this).data('selectWoo')) $(this).selectWoo({ width: 'resolve', allowClear: true, placeholder: '<?php echo esc_js( __( 'Category…', 'meyvora-convert' ) ); ?>' });
			});
		}
	});
	$('.cro-per-category-discount-list').on('click', '.cro-remove-per-cat', function() {
		$(this).closest('.cro-per-cat-row').remove();
	});
})(jQuery);
</script>
