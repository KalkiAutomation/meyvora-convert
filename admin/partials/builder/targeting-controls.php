<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Targeting controls partial for the campaign builder.
 * Expects $campaign_data (object with optional targeting_rules array).
 */
$targeting = ( is_object( $campaign_data ) && isset( $campaign_data->targeting_rules ) && is_array( $campaign_data->targeting_rules ) )
	? $campaign_data->targeting_rules
	: array();
$pages    = isset( $targeting['pages'] ) && is_array( $targeting['pages'] ) ? $targeting['pages'] : array();
$behavior = isset( $targeting['behavior'] ) && is_array( $targeting['behavior'] ) ? $targeting['behavior'] : array();
$visitor  = isset( $targeting['visitor'] ) && is_array( $targeting['visitor'] ) ? $targeting['visitor'] : array();
$device   = isset( $targeting['device'] ) && is_array( $targeting['device'] ) ? $targeting['device'] : array();
$include_list = isset( $pages['include'] ) && is_array( $pages['include'] ) ? $pages['include'] : array();
$exclude_list = isset( $pages['exclude'] ) && is_array( $pages['exclude'] ) ? $pages['exclude'] : array();
$page_mode    = isset( $targeting['page_mode'] ) ? $targeting['page_mode'] : 'all';
$audience_mode = $targeting['audience_mode'] ?? 'all';
$currency_sym  = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';
?>

<div class="cro-targeting-controls">

	<!-- Targeting Mode -->
	<div class="cro-control-group">
		<label><?php esc_html_e( 'Show campaign to:', 'meyvora-convert' ); ?></label>
		<div class="cro-targeting-mode">
			<label class="cro-radio-card">
				<input type="radio" name="targeting-mode" value="all" <?php checked( $audience_mode, 'all' ); ?> />
				<span class="cro-radio-content">
					<strong><?php esc_html_e( 'Everyone', 'meyvora-convert' ); ?></strong>
					<span><?php esc_html_e( 'All visitors on selected pages', 'meyvora-convert' ); ?></span>
				</span>
			</label>
			<label class="cro-radio-card">
				<input type="radio" name="targeting-mode" value="rules" <?php checked( $audience_mode, 'rules' ); ?> />
				<span class="cro-radio-content">
					<strong><?php esc_html_e( 'Specific Visitors', 'meyvora-convert' ); ?></strong>
					<span><?php esc_html_e( 'Based on rules below', 'meyvora-convert' ); ?></span>
				</span>
			</label>
		</div>
	</div>

	<!-- Page Targeting -->
	<div class="cro-rule-section">
		<h3>
			<span class="cro-section-icon"><?php echo CRO_Icons::svg_kses( 'file', array( 'class' => 'cro-ico' ) ); ?></span>

			<?php esc_html_e( 'Page Targeting', 'meyvora-convert' ); ?>
		</h3>

		<div class="cro-control-group">
			<label><?php esc_html_e( 'Show on pages:', 'meyvora-convert' ); ?></label>
			<select id="targeting-page-mode" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'All pages', 'meyvora-convert' ); ?>">
				<option value="all" <?php selected( $page_mode, 'all' ); ?>><?php esc_html_e( 'All pages', 'meyvora-convert' ); ?></option>
				<option value="include" <?php selected( $page_mode, 'include' ); ?>><?php esc_html_e( 'Only specific pages', 'meyvora-convert' ); ?></option>
				<option value="exclude" <?php selected( $page_mode, 'exclude' ); ?>><?php esc_html_e( 'All pages except...', 'meyvora-convert' ); ?></option>
			</select>
		</div>

		<div class="cro-page-selector <?php echo $page_mode === 'include' ? '' : 'cro-is-hidden'; ?>" id="page-include-selector">
			<label><?php esc_html_e( 'Include these pages:', 'meyvora-convert' ); ?></label>
			<div class="cro-checkbox-grid">
				<label><input type="checkbox" name="pages[]" value="home" <?php checked( in_array( 'home', $include_list, true ) ); ?> /> <?php esc_html_e( 'Homepage', 'meyvora-convert' ); ?></label>
				<label><input type="checkbox" name="pages[]" value="shop" <?php checked( in_array( 'shop', $include_list, true ) ); ?> /> <?php esc_html_e( 'Shop page', 'meyvora-convert' ); ?></label>
				<label><input type="checkbox" name="pages[]" value="product" <?php checked( in_array( 'product', $include_list, true ) ); ?> /> <?php esc_html_e( 'Product pages', 'meyvora-convert' ); ?></label>
				<label><input type="checkbox" name="pages[]" value="category" <?php checked( in_array( 'category', $include_list, true ) ); ?> /> <?php esc_html_e( 'Category pages', 'meyvora-convert' ); ?></label>
				<label><input type="checkbox" name="pages[]" value="cart" <?php checked( in_array( 'cart', $include_list, true ) ); ?> /> <?php esc_html_e( 'Cart page', 'meyvora-convert' ); ?></label>
				<label><input type="checkbox" name="pages[]" value="blog" <?php checked( in_array( 'blog', $include_list, true ) ); ?> /> <?php esc_html_e( 'Blog posts', 'meyvora-convert' ); ?></label>
			</div>

			<div class="cro-specific-pages">
				<label><?php esc_html_e( 'Or select specific pages/products:', 'meyvora-convert' ); ?></label>
				<select id="targeting-specific-pages" multiple class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Search pages or products...', 'meyvora-convert' ); ?>" data-action="cro_search_pages">
					<!-- Populated via AJAX -->
				</select>
			</div>
		</div>

		<div class="cro-page-selector <?php echo $page_mode === 'exclude' ? '' : 'cro-is-hidden'; ?>" id="page-exclude-selector">
			<label><?php esc_html_e( 'Exclude these pages:', 'meyvora-convert' ); ?></label>
			<div class="cro-checkbox-grid">
				<label><input type="checkbox" name="exclude-pages[]" value="checkout" checked disabled />
					<?php esc_html_e( 'Checkout (always excluded)', 'meyvora-convert' ); ?>
				</label>
				<label><input type="checkbox" name="exclude-pages[]" value="cart" <?php checked( in_array( 'cart', $exclude_list, true ) ); ?> /> <?php esc_html_e( 'Cart page', 'meyvora-convert' ); ?></label>
				<label><input type="checkbox" name="exclude-pages[]" value="account" <?php checked( in_array( 'account', $exclude_list, true ) ); ?> /> <?php esc_html_e( 'My Account', 'meyvora-convert' ); ?></label>
			</div>
		</div>
	</div>

	<!-- Visitor Targeting -->
	<div class="cro-rule-section">
		<h3>
			<span class="cro-section-icon"><?php echo CRO_Icons::svg_kses( 'user', array( 'class' => 'cro-ico' ) ); ?></span>

			<?php esc_html_e( 'Visitor Targeting', 'meyvora-convert' ); ?>
		</h3>

		<div class="cro-control-group">
			<label><?php esc_html_e( 'Visitor type:', 'meyvora-convert' ); ?></label>
<select id="targeting-visitor-type" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'All visitors', 'meyvora-convert' ); ?>">
			<option value="all" <?php selected( $visitor['type'] ?? 'all', 'all' ); ?>><?php esc_html_e( 'All visitors', 'meyvora-convert' ); ?></option>
				<option value="new" <?php selected( $visitor['type'] ?? 'all', 'new' ); ?>><?php esc_html_e( 'New visitors only (first visit)', 'meyvora-convert' ); ?></option>
				<option value="returning" <?php selected( $visitor['type'] ?? 'all', 'returning' ); ?>><?php esc_html_e( 'Returning visitors only', 'meyvora-convert' ); ?></option>
				<option value="logged_in" <?php selected( $visitor['type'] ?? 'all', 'logged_in' ); ?>><?php esc_html_e( 'Logged in users only', 'meyvora-convert' ); ?></option>
				<option value="logged_out" <?php selected( $visitor['type'] ?? 'all', 'logged_out' ); ?>><?php esc_html_e( 'Logged out visitors only', 'meyvora-convert' ); ?></option>
			</select>
		</div>

		<div class="cro-control-group">
			<label>
				<input type="checkbox" id="targeting-exclude-purchased" <?php checked( ! empty( $targeting['exclude_purchased'] ) ); ?> />
				<?php esc_html_e( 'Exclude customers who already purchased', 'meyvora-convert' ); ?>
			</label>
		</div>

		<div class="cro-control-group">
			<label><?php esc_html_e( 'Session count:', 'meyvora-convert' ); ?></label>
			<div class="cro-range-inputs">
				<div class="cro-range-input">
					<span><?php esc_html_e( 'Min sessions:', 'meyvora-convert' ); ?></span>
					<input type="number" id="targeting-min-sessions" min="1" placeholder="1" value="<?php echo esc_attr( (string) ( $visitor['min_sessions'] ?? $targeting['min_sessions'] ?? '' ) ); ?>" />
				</div>
				<div class="cro-range-input">
					<span><?php esc_html_e( 'Max sessions:', 'meyvora-convert' ); ?></span>
					<input type="number" id="targeting-max-sessions" min="1" placeholder="∞" value="<?php echo esc_attr( (string) ( $visitor['max_sessions'] ?? $targeting['max_sessions'] ?? '' ) ); ?>" />
				</div>
			</div>
			<p class="cro-hint"><?php esc_html_e( 'Target visitors based on how many times they have visited', 'meyvora-convert' ); ?></p>
		</div>
	</div>

	<!-- Device Targeting -->
	<div class="cro-rule-section">
		<h3>
			<span class="cro-section-icon"><?php echo CRO_Icons::svg_kses( 'smartphone', array( 'class' => 'cro-ico' ) ); ?></span>

			<?php esc_html_e( 'Device Targeting', 'meyvora-convert' ); ?>
		</h3>

		<div class="cro-device-options">
			<label class="cro-device-option">
				<input type="checkbox" name="devices[]" value="desktop" <?php checked( $device['desktop'] ?? true ); ?> />
				<span class="cro-device-icon"><?php echo CRO_Icons::svg_kses( 'monitor', array( 'class' => 'cro-ico' ) ); ?></span>

				<span><?php esc_html_e( 'Desktop', 'meyvora-convert' ); ?></span>
			</label>
			<label class="cro-device-option">
				<input type="checkbox" name="devices[]" value="tablet" <?php checked( $device['tablet'] ?? true ); ?> />
				<span class="cro-device-icon"><?php echo CRO_Icons::svg_kses( 'tablet', array( 'class' => 'cro-ico' ) ); ?></span>

				<span><?php esc_html_e( 'Tablet', 'meyvora-convert' ); ?></span>
			</label>
			<label class="cro-device-option">
				<input type="checkbox" name="devices[]" value="mobile" <?php checked( $device['mobile'] ?? true ); ?> />
				<span class="cro-device-icon"><?php echo CRO_Icons::svg_kses( 'smartphone', array( 'class' => 'cro-ico' ) ); ?></span>

				<span><?php esc_html_e( 'Mobile', 'meyvora-convert' ); ?></span>
			</label>
		</div>
	</div>

	<!-- Cart Targeting -->
	<div class="cro-rule-section">
		<h3>
			<span class="cro-section-icon"><?php echo CRO_Icons::svg_kses( 'shopping-cart', array( 'class' => 'cro-ico' ) ); ?></span>

			<?php esc_html_e( 'Cart Targeting', 'meyvora-convert' ); ?>
		</h3>

		<div class="cro-control-group">
			<label><?php esc_html_e( 'Cart status:', 'meyvora-convert' ); ?></label>
			<select id="targeting-cart-status" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Any (with or without items)', 'meyvora-convert' ); ?>">
				<option value="any" <?php selected( $behavior['cart_status'] ?? 'any', 'any' ); ?>><?php esc_html_e( 'Any (with or without items)', 'meyvora-convert' ); ?></option>
				<option value="has_items" <?php selected( $behavior['cart_status'] ?? 'any', 'has_items' ); ?>><?php esc_html_e( 'Has items in cart', 'meyvora-convert' ); ?></option>
				<option value="empty" <?php selected( $behavior['cart_status'] ?? 'any', 'empty' ); ?>><?php esc_html_e( 'Cart is empty', 'meyvora-convert' ); ?></option>
			</select>
		</div>

		<div class="cro-control-group cro-conditional" data-show-when="targeting-cart-status=has_items">
			<label><?php esc_html_e( 'Cart value:', 'meyvora-convert' ); ?></label>
			<div class="cro-range-inputs">
				<div class="cro-range-input">
					<span><?php esc_html_e( 'Min:', 'meyvora-convert' ); ?></span>
					<input type="number" id="targeting-cart-min" min="0" step="1" placeholder="0" value="<?php echo esc_attr( (string) ( $behavior['cart_min_value'] ?? 0 ) ); ?>" />
					<span class="cro-currency"><?php echo esc_html( $currency_sym ); ?></span>
				</div>
				<div class="cro-range-input">
					<span><?php esc_html_e( 'Max:', 'meyvora-convert' ); ?></span>
					<input type="number" id="targeting-cart-max" min="0" step="1" placeholder="∞" value="<?php echo esc_attr( (string) ( $behavior['cart_max_value'] ?? '' ) ); ?>" />
					<span class="cro-currency"><?php echo esc_html( $currency_sym ); ?></span>
				</div>
			</div>
		</div>

		<div class="cro-control-group cro-conditional" data-show-when="targeting-cart-status=has_items">
			<label><?php esc_html_e( 'Cart item count:', 'meyvora-convert' ); ?></label>
			<div class="cro-range-inputs">
				<div class="cro-range-input">
					<span><?php esc_html_e( 'Min items:', 'meyvora-convert' ); ?></span>
					<input type="number" id="targeting-cart-min-items" min="0" placeholder="0" value="<?php echo esc_attr( (string) ( $behavior['cart_min_items'] ?? '' ) ); ?>" />
				</div>
				<div class="cro-range-input">
					<span><?php esc_html_e( 'Max items:', 'meyvora-convert' ); ?></span>
					<input type="number" id="targeting-cart-max-items" min="0" placeholder="∞" value="<?php echo esc_attr( (string) ( $behavior['cart_max_items'] ?? '' ) ); ?>" />
				</div>
			</div>
		</div>

		<div class="cro-control-group cro-conditional" data-show-when="targeting-cart-status=has_items">
			<label><?php esc_html_e( 'Cart contains product:', 'meyvora-convert' ); ?></label>
			<select id="targeting-cart-contains" multiple class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Any product', 'meyvora-convert' ); ?>" data-action="cro_search_products">
				<!-- Populated via AJAX -->
			</select>
		</div>

		<div class="cro-control-group cro-conditional" data-show-when="targeting-cart-status=has_items">
			<label><?php esc_html_e( 'Cart contains category:', 'meyvora-convert' ); ?></label>
			<select id="targeting-cart-category" multiple class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Any category', 'meyvora-convert' ); ?>">
				<?php
				if ( taxonomy_exists( 'product_cat' ) ) {
					$categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
					if ( ! is_wp_error( $categories ) ) {
						$selected_cats = isset( $behavior['cart_contains_category'] ) && is_array( $behavior['cart_contains_category'] ) ? $behavior['cart_contains_category'] : array();
						foreach ( $categories as $cat ) {
							$sel = in_array( (string) $cat->term_id, $selected_cats, true ) ? ' selected' : '';
							echo '<option value="' . esc_attr( (string) $cat->term_id ) . '"' . esc_attr( $sel ) . '>' . esc_html( $cat->name ) . '</option>';
						}
					}
				}
				?>
			</select>
		</div>

		<div class="cro-control-group cro-conditional" data-show-when="targeting-cart-status=has_items">
			<label><?php esc_html_e( 'Exclude if cart contains product:', 'meyvora-convert' ); ?></label>
			<select id="targeting-cart-exclude-product" multiple class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'None', 'meyvora-convert' ); ?>" data-action="cro_search_products">
				<!-- Populated via AJAX -->
			</select>
			<p class="cro-hint"><?php esc_html_e( 'Do not show when cart contains any of these products', 'meyvora-convert' ); ?></p>
		</div>

		<div class="cro-control-group cro-conditional" data-show-when="targeting-cart-status=has_items">
			<label><?php esc_html_e( 'Exclude if cart contains category:', 'meyvora-convert' ); ?></label>
			<select id="targeting-cart-exclude-category" multiple class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'None', 'meyvora-convert' ); ?>">
				<?php
				if ( taxonomy_exists( 'product_cat' ) ) {
					$categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
					if ( ! is_wp_error( $categories ) ) {
						$exclude_cats = isset( $behavior['cart_exclude_category'] ) && is_array( $behavior['cart_exclude_category'] ) ? $behavior['cart_exclude_category'] : array();
						foreach ( $categories as $cat ) {
							$sel = in_array( (string) $cat->term_id, $exclude_cats, true ) ? ' selected' : '';
							echo '<option value="' . esc_attr( (string) $cat->term_id ) . '"' . esc_attr( $sel ) . '>' . esc_html( $cat->name ) . '</option>';
						}
					}
				}
				?>
			</select>
			<p class="cro-hint"><?php esc_html_e( 'Do not show when cart contains any product from these categories', 'meyvora-convert' ); ?></p>
		</div>

		<div class="cro-control-group cro-conditional" data-show-when="targeting-cart-status=has_items">
			<label>
				<input type="checkbox" id="targeting-cart-has-sale" <?php checked( ! empty( $behavior['cart_has_sale_only'] ) ); ?> />
				<?php esc_html_e( 'Only if cart contains sale items', 'meyvora-convert' ); ?>
			</label>
		</div>
	</div>

	<!-- Behavioral Targeting -->
	<div class="cro-rule-section">
		<h3>
			<span class="cro-section-icon"><?php echo CRO_Icons::svg_kses( 'target', array( 'class' => 'cro-ico' ) ); ?></span>

			<?php esc_html_e( 'Behavioral Targeting', 'meyvora-convert' ); ?>
		</h3>

		<div class="cro-control-group">
			<label><?php esc_html_e( 'Minimum time on page:', 'meyvora-convert' ); ?></label>
			<div class="cro-input-with-suffix">
				<input type="number" id="targeting-min-time" min="0" max="300" value="<?php echo esc_attr( (string) ( $behavior['min_time_on_page'] ?? 0 ) ); ?>" />
				<span class="cro-suffix"><?php esc_html_e( 'seconds (0 = no minimum)', 'meyvora-convert' ); ?></span>
			</div>
		</div>

		<div class="cro-control-group">
			<label><?php esc_html_e( 'Minimum scroll depth:', 'meyvora-convert' ); ?></label>
			<div class="cro-range-slider">
				<?php $min_scroll = (int) ( $behavior['min_scroll_depth'] ?? 0 ); ?>
				<input type="range" id="targeting-min-scroll" min="0" max="100" value="<?php echo esc_attr( (string) $min_scroll ); ?>" />
				<span class="cro-range-value"><span id="min-scroll-value"><?php echo esc_html( (string) $min_scroll ); ?></span>% (0 = no minimum)</span>
			</div>
		</div>

		<div class="cro-control-group">
			<label><?php esc_html_e( 'Minimum pages viewed this session:', 'meyvora-convert' ); ?></label>
			<input type="number" id="targeting-min-pages" min="0" value="<?php echo esc_attr( (string) ( $behavior['min_pages_viewed'] ?? $targeting['min_pages_viewed'] ?? 0 ) ); ?>" />
		</div>

		<div class="cro-control-group">
			<label>
				<input type="checkbox" id="targeting-require-interaction" <?php checked( ! empty( $behavior['require_interaction'] ) ); ?> />
				<?php esc_html_e( 'Require at least one click/interaction', 'meyvora-convert' ); ?>
			</label>
			<p class="cro-hint"><?php esc_html_e( 'Ensures visitor is engaged, not just bouncing', 'meyvora-convert' ); ?></p>
		</div>
	</div>

	<!-- Traffic Source Targeting -->
	<div class="cro-rule-section">
		<h3>
			<span class="cro-section-icon"><?php echo CRO_Icons::svg_kses( 'link', array( 'class' => 'cro-ico' ) ); ?></span>

			<?php esc_html_e( 'Traffic Source', 'meyvora-convert' ); ?>
		</h3>

		<div class="cro-control-group">
			<label><?php esc_html_e( 'Referrer contains:', 'meyvora-convert' ); ?></label>
			<input type="text" id="targeting-referrer" placeholder="<?php esc_attr_e( 'e.g., google.com, facebook.com', 'meyvora-convert' ); ?>" value="<?php echo esc_attr( (string) ( $targeting['referrer'] ?? '' ) ); ?>" />
			<p class="cro-hint"><?php esc_html_e( 'Show only to visitors coming from specific sites', 'meyvora-convert' ); ?></p>
		</div>

		<div class="cro-control-group">
			<label><?php esc_html_e( 'UTM Source:', 'meyvora-convert' ); ?></label>
			<input type="text" id="targeting-utm-source" placeholder="<?php esc_attr_e( 'e.g., newsletter, facebook', 'meyvora-convert' ); ?>" value="<?php echo esc_attr( (string) ( $targeting['utm_source'] ?? '' ) ); ?>" />
		</div>

		<div class="cro-control-group">
			<label><?php esc_html_e( 'UTM Medium:', 'meyvora-convert' ); ?></label>
			<input type="text" id="targeting-utm-medium" placeholder="<?php esc_attr_e( 'e.g., email, cpc, social', 'meyvora-convert' ); ?>" value="<?php echo esc_attr( (string) ( $targeting['utm_medium'] ?? '' ) ); ?>" />
		</div>

		<div class="cro-control-group">
			<label><?php esc_html_e( 'UTM Campaign:', 'meyvora-convert' ); ?></label>
			<input type="text" id="targeting-utm-campaign" placeholder="<?php esc_attr_e( 'e.g., summer_sale, black_friday', 'meyvora-convert' ); ?>" value="<?php echo esc_attr( (string) ( $targeting['utm_campaign'] ?? '' ) ); ?>" />
		</div>
	</div>

	<!-- Advanced: Custom Rules Builder -->
	<div class="cro-rule-section cro-advanced">
		<h3>
			<span class="cro-section-icon"><?php echo CRO_Icons::svg_kses( 'settings', array( 'class' => 'cro-ico' ) ); ?></span>

			<?php esc_html_e( 'Advanced Rules', 'meyvora-convert' ); ?>
		</h3>

		<p class="cro-hint"><?php esc_html_e( 'Build custom rules using AND/OR logic for complex targeting scenarios', 'meyvora-convert' ); ?></p>

		<div class="cro-rule-builder" id="advanced-rules">

			<div class="cro-rule-groups">
				<!-- Rule groups will be added here dynamically -->
			</div>

			<button type="button" class="button" id="add-rule-group">
				<?php echo CRO_Icons::svg_kses( 'plus', array( 'class' => 'cro-ico' ) ); ?>

				<?php esc_html_e( 'Add Rule Group (OR)', 'meyvora-convert' ); ?>
			</button>
		</div>

		<!-- Rule Group Template (hidden) -->
		<template id="rule-group-template">
			<div class="cro-rule-group">
				<div class="cro-rule-group-header">
					<span class="cro-rule-group-logic"><?php esc_html_e( 'OR', 'meyvora-convert' ); ?></span>
					<button type="button" class="cro-remove-group" aria-label="<?php esc_attr_e( 'Remove', 'meyvora-convert' ); ?>"><?php echo CRO_Icons::svg_kses( 'x', array( 'class' => 'cro-ico' ) ); ?></button>

				</div>
				<div class="cro-rules-list">
					<!-- Rules will be added here -->
				</div>
				<button type="button" class="button button-small cro-add-rule">
					<?php echo CRO_Icons::svg_kses( 'plus', array( 'class' => 'cro-ico' ) ); ?>

					<?php esc_html_e( 'Add Condition (AND)', 'meyvora-convert' ); ?>
				</button>
			</div>
		</template>

		<!-- Rule Template (hidden) -->
		<template id="rule-template">
			<div class="cro-rule-item">
				<select class="cro-rule-field cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Select field', 'meyvora-convert' ); ?>">
					<optgroup label="<?php esc_attr_e( 'Page', 'meyvora-convert' ); ?>">
						<option value="page.type"><?php esc_html_e( 'Page Type', 'meyvora-convert' ); ?></option>
						<option value="page.id"><?php esc_html_e( 'Page ID', 'meyvora-convert' ); ?></option>
						<option value="page.url"><?php esc_html_e( 'Page URL', 'meyvora-convert' ); ?></option>
					</optgroup>
					<optgroup label="<?php esc_attr_e( 'Visitor', 'meyvora-convert' ); ?>">
						<option value="visitor.is_new"><?php esc_html_e( 'Is New Visitor', 'meyvora-convert' ); ?></option>
						<option value="visitor.session_count"><?php esc_html_e( 'Session Count', 'meyvora-convert' ); ?></option>
						<option value="user.logged_in"><?php esc_html_e( 'Is Logged In', 'meyvora-convert' ); ?></option>
					</optgroup>
					<optgroup label="<?php esc_attr_e( 'Cart', 'meyvora-convert' ); ?>">
						<option value="cart.total"><?php esc_html_e( 'Cart Total', 'meyvora-convert' ); ?></option>
						<option value="cart.item_count"><?php esc_html_e( 'Cart Item Count', 'meyvora-convert' ); ?></option>
						<option value="cart.has_items"><?php esc_html_e( 'Cart Has Items', 'meyvora-convert' ); ?></option>
					</optgroup>
					<optgroup label="<?php esc_attr_e( 'Behavior', 'meyvora-convert' ); ?>">
						<option value="behavior.time_on_page"><?php esc_html_e( 'Time on Page', 'meyvora-convert' ); ?></option>
						<option value="behavior.scroll_depth"><?php esc_html_e( 'Scroll Depth', 'meyvora-convert' ); ?></option>
					</optgroup>
				</select>
				<select class="cro-rule-operator cro-selectwoo" data-placeholder="=">
					<option value="=">=</option>
					<option value="!=">≠</option>
					<option value=">">&gt;</option>
					<option value="<">&lt;</option>
					<option value=">=">&gt;=</option>
					<option value="<=">&lt;=</option>
					<option value="contains"><?php esc_html_e( 'contains', 'meyvora-convert' ); ?></option>
					<option value="not_contains"><?php esc_html_e( 'not contains', 'meyvora-convert' ); ?></option>
				</select>
				<input type="text" class="cro-rule-value" placeholder="<?php esc_attr_e( 'Value', 'meyvora-convert' ); ?>" />
				<button type="button" class="cro-remove-rule" aria-label="<?php esc_attr_e( 'Remove', 'meyvora-convert' ); ?>"><?php echo CRO_Icons::svg_kses( 'x', array( 'class' => 'cro-ico' ) ); ?></button>

			</div>
		</template>
	</div>

</div>
