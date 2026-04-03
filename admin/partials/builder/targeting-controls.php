<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template/view: variables are file-local.
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
$geo_countries = isset( $targeting['geo_countries'] ) && is_array( $targeting['geo_countries'] ) ? $targeting['geo_countries'] : array();
$wc_countries = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_countries() : array();
$geo_countries = isset( $targeting['geo_countries'] ) && is_array( $targeting['geo_countries'] ) ? $targeting['geo_countries'] : array();
$include_list = isset( $pages['include'] ) && is_array( $pages['include'] ) ? $pages['include'] : array();
$exclude_list = MEYVC_Campaign_Model::get_pages_excluded_slugs( is_array( $pages ) ? $pages : array() );
$page_mode    = isset( $targeting['page_mode'] ) ? $targeting['page_mode'] : 'all';
$audience_mode = $targeting['audience_mode'] ?? 'all';
$currency_sym  = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';
?>

<div class="meyvc-targeting-controls">

	<!-- Targeting Mode -->
	<div class="meyvc-control-group">
		<label><?php esc_html_e( 'Show campaign to:', 'meyvora-convert' ); ?></label>
		<div class="meyvc-targeting-mode">
			<label class="meyvc-radio-card">
				<input type="radio" name="targeting-mode" value="all" <?php checked( $audience_mode, 'all' ); ?> />
				<span class="meyvc-radio-content">
					<strong><?php esc_html_e( 'Everyone', 'meyvora-convert' ); ?></strong>
					<span><?php esc_html_e( 'All visitors on selected pages', 'meyvora-convert' ); ?></span>
				</span>
			</label>
			<label class="meyvc-radio-card">
				<input type="radio" name="targeting-mode" value="rules" <?php checked( $audience_mode, 'rules' ); ?> />
				<span class="meyvc-radio-content">
					<strong><?php esc_html_e( 'Specific Visitors', 'meyvora-convert' ); ?></strong>
					<span><?php esc_html_e( 'Based on rules below', 'meyvora-convert' ); ?></span>
				</span>
			</label>
		</div>
	</div>

	<!-- Page Targeting -->
	<div class="meyvc-rule-section">
		<h3>
			<span class="meyvc-section-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'file', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

			<?php esc_html_e( 'Page Targeting', 'meyvora-convert' ); ?>
		</h3>

		<div class="meyvc-control-group">
			<label><?php esc_html_e( 'Show on pages:', 'meyvora-convert' ); ?></label>
			<select id="targeting-page-mode" class="meyvc-selectwoo" data-placeholder="<?php esc_attr_e( 'All pages', 'meyvora-convert' ); ?>">
				<option value="all" <?php selected( $page_mode, 'all' ); ?>><?php esc_html_e( 'All pages', 'meyvora-convert' ); ?></option>
				<option value="include" <?php selected( $page_mode, 'include' ); ?>><?php esc_html_e( 'Only specific pages', 'meyvora-convert' ); ?></option>
				<option value="exclude" <?php selected( $page_mode, 'exclude' ); ?>><?php esc_html_e( 'All pages except...', 'meyvora-convert' ); ?></option>
			</select>
		</div>

		<div class="meyvc-page-selector <?php echo $page_mode === 'include' ? '' : 'meyvc-is-hidden'; ?>" id="page-include-selector">
			<label><?php esc_html_e( 'Include these pages:', 'meyvora-convert' ); ?></label>
			<div class="meyvc-checkbox-grid">
				<label><input type="checkbox" name="pages[]" value="home" <?php checked( in_array( 'home', $include_list, true ) ); ?> /> <?php esc_html_e( 'Homepage', 'meyvora-convert' ); ?></label>
				<label><input type="checkbox" name="pages[]" value="shop" <?php checked( in_array( 'shop', $include_list, true ) ); ?> /> <?php esc_html_e( 'Shop page', 'meyvora-convert' ); ?></label>
				<label><input type="checkbox" name="pages[]" value="product" <?php checked( in_array( 'product', $include_list, true ) ); ?> /> <?php esc_html_e( 'Product pages', 'meyvora-convert' ); ?></label>
				<label><input type="checkbox" name="pages[]" value="category" <?php checked( in_array( 'category', $include_list, true ) ); ?> /> <?php esc_html_e( 'Category pages', 'meyvora-convert' ); ?></label>
				<label><input type="checkbox" name="pages[]" value="cart" <?php checked( in_array( 'cart', $include_list, true ) ); ?> /> <?php esc_html_e( 'Cart page', 'meyvora-convert' ); ?></label>
				<label><input type="checkbox" name="pages[]" value="blog" <?php checked( in_array( 'blog', $include_list, true ) ); ?> /> <?php esc_html_e( 'Blog posts', 'meyvora-convert' ); ?></label>
			</div>

			<div class="meyvc-specific-pages">
				<label><?php esc_html_e( 'Or select specific pages/products:', 'meyvora-convert' ); ?></label>
				<select id="targeting-specific-pages" multiple class="meyvc-selectwoo" data-placeholder="<?php esc_attr_e( 'Search pages or products...', 'meyvora-convert' ); ?>" data-action="meyvc_search_pages">
					<!-- Populated via AJAX -->
				</select>
			</div>
		</div>

		<div class="meyvc-page-selector <?php echo $page_mode === 'exclude' ? '' : 'meyvc-is-hidden'; ?>" id="page-exclude-selector">
			<label><?php esc_html_e( 'Exclude these pages:', 'meyvora-convert' ); ?></label>
			<div class="meyvc-checkbox-grid">
				<label><input type="checkbox" name="exclude-pages[]" value="checkout" checked disabled />
					<?php esc_html_e( 'Checkout (always excluded)', 'meyvora-convert' ); ?>
				</label>
				<label><input type="checkbox" name="exclude-pages[]" value="cart" <?php checked( in_array( 'cart', $exclude_list, true ) ); ?> /> <?php esc_html_e( 'Cart page', 'meyvora-convert' ); ?></label>
				<label><input type="checkbox" name="exclude-pages[]" value="account" <?php checked( in_array( 'account', $exclude_list, true ) ); ?> /> <?php esc_html_e( 'My Account', 'meyvora-convert' ); ?></label>
			</div>
		</div>
	</div>

	<!-- Visitor Targeting -->
	<div class="meyvc-rule-section">
		<h3>
			<span class="meyvc-section-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'user', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

			<?php esc_html_e( 'Visitor Targeting', 'meyvora-convert' ); ?>
		</h3>

		<div class="meyvc-control-group">
			<label><?php esc_html_e( 'Visitor type:', 'meyvora-convert' ); ?></label>
<select id="targeting-visitor-type" class="meyvc-selectwoo" data-placeholder="<?php esc_attr_e( 'All visitors', 'meyvora-convert' ); ?>">
			<option value="all" <?php selected( $visitor['type'] ?? 'all', 'all' ); ?>><?php esc_html_e( 'All visitors', 'meyvora-convert' ); ?></option>
				<option value="new" <?php selected( $visitor['type'] ?? 'all', 'new' ); ?>><?php esc_html_e( 'New visitors only (first visit)', 'meyvora-convert' ); ?></option>
				<option value="returning" <?php selected( $visitor['type'] ?? 'all', 'returning' ); ?>><?php esc_html_e( 'Returning visitors only', 'meyvora-convert' ); ?></option>
				<option value="logged_in" <?php selected( $visitor['type'] ?? 'all', 'logged_in' ); ?>><?php esc_html_e( 'Logged in users only', 'meyvora-convert' ); ?></option>
				<option value="logged_out" <?php selected( $visitor['type'] ?? 'all', 'logged_out' ); ?>><?php esc_html_e( 'Logged out visitors only', 'meyvora-convert' ); ?></option>
			</select>
		</div>

		<div class="meyvc-control-group">
			<label>
				<input type="checkbox" id="targeting-exclude-purchased" <?php checked( ! empty( $targeting['exclude_purchased'] ) ); ?> />
				<?php esc_html_e( 'Exclude customers who already purchased', 'meyvora-convert' ); ?>
			</label>
		</div>

		<div class="meyvc-control-group">
			<label><?php esc_html_e( 'Session count:', 'meyvora-convert' ); ?></label>
			<div class="meyvc-range-inputs">
				<div class="meyvc-range-input">
					<span><?php esc_html_e( 'Min sessions:', 'meyvora-convert' ); ?></span>
					<input type="number" id="targeting-min-sessions" min="1" placeholder="1" value="<?php echo esc_attr( (string) ( $visitor['min_sessions'] ?? $targeting['min_sessions'] ?? '' ) ); ?>" />
				</div>
				<div class="meyvc-range-input">
					<span><?php esc_html_e( 'Max sessions:', 'meyvora-convert' ); ?></span>
					<input type="number" id="targeting-max-sessions" min="1" placeholder="∞" value="<?php echo esc_attr( (string) ( $visitor['max_sessions'] ?? $targeting['max_sessions'] ?? '' ) ); ?>" />
				</div>
			</div>
			<p class="meyvc-hint"><?php esc_html_e( 'Target visitors based on how many times they have visited', 'meyvora-convert' ); ?></p>
		</div>
	</div>

	<!-- Device Targeting -->
	<div class="meyvc-rule-section">
		<h3>
			<span class="meyvc-section-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'smartphone', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

			<?php esc_html_e( 'Device Targeting', 'meyvora-convert' ); ?>
		</h3>

		<div class="meyvc-device-options">
			<label class="meyvc-device-option">
				<input type="checkbox" name="devices[]" value="desktop" <?php checked( $device['desktop'] ?? true ); ?> />
				<span class="meyvc-device-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'monitor', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

				<span><?php esc_html_e( 'Desktop', 'meyvora-convert' ); ?></span>
			</label>
			<label class="meyvc-device-option">
				<input type="checkbox" name="devices[]" value="tablet" <?php checked( $device['tablet'] ?? true ); ?> />
				<span class="meyvc-device-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'tablet', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

				<span><?php esc_html_e( 'Tablet', 'meyvora-convert' ); ?></span>
			</label>
			<label class="meyvc-device-option">
				<input type="checkbox" name="devices[]" value="mobile" <?php checked( $device['mobile'] ?? true ); ?> />
				<span class="meyvc-device-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'smartphone', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

				<span><?php esc_html_e( 'Mobile', 'meyvora-convert' ); ?></span>
			</label>
		</div>
	</div>

	<!-- Geo targeting -->
	<div class="meyvc-rule-section">
		<h3>
			<span class="meyvc-section-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'target', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>
			<?php esc_html_e( 'Geo targeting', 'meyvora-convert' ); ?>
		</h3>
		<p class="meyvc-hint"><?php esc_html_e( 'When set, the campaign only shows if the visitor’s country (WooCommerce geolocation or Cloudflare header) matches one of the selected countries.', 'meyvora-convert' ); ?></p>
		<div class="meyvc-control-group">
			<label for="targeting-geo-countries"><?php esc_html_e( 'Countries (leave empty for all)', 'meyvora-convert' ); ?></label>
			<select id="targeting-geo-countries" name="targeting-geo-countries[]" multiple class="meyvc-selectwoo" data-placeholder="<?php esc_attr_e( 'All countries', 'meyvora-convert' ); ?>">
				<?php foreach ( $wc_countries as $code => $label ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php echo in_array( (string) $code, array_map( 'strval', $geo_countries ), true ) ? 'selected="selected"' : ''; ?>><?php echo esc_html( $label . ' (' . $code . ')' ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>

	<!-- Cart Targeting -->
	<div class="meyvc-rule-section">
		<h3>
			<span class="meyvc-section-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'shopping-cart', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

			<?php esc_html_e( 'Cart Targeting', 'meyvora-convert' ); ?>
		</h3>

		<div class="meyvc-control-group">
			<label><?php esc_html_e( 'Cart status:', 'meyvora-convert' ); ?></label>
			<select id="targeting-cart-status" class="meyvc-selectwoo" data-placeholder="<?php esc_attr_e( 'Any (with or without items)', 'meyvora-convert' ); ?>">
				<option value="any" <?php selected( $behavior['cart_status'] ?? 'any', 'any' ); ?>><?php esc_html_e( 'Any (with or without items)', 'meyvora-convert' ); ?></option>
				<option value="has_items" <?php selected( $behavior['cart_status'] ?? 'any', 'has_items' ); ?>><?php esc_html_e( 'Has items in cart', 'meyvora-convert' ); ?></option>
				<option value="empty" <?php selected( $behavior['cart_status'] ?? 'any', 'empty' ); ?>><?php esc_html_e( 'Cart is empty', 'meyvora-convert' ); ?></option>
			</select>
		</div>

		<div class="meyvc-control-group meyvc-conditional" data-show-when="targeting-cart-status=has_items">
			<label><?php esc_html_e( 'Cart value:', 'meyvora-convert' ); ?></label>
			<div class="meyvc-range-inputs">
				<div class="meyvc-range-input">
					<span><?php esc_html_e( 'Min:', 'meyvora-convert' ); ?></span>
					<input type="number" id="targeting-cart-min" min="0" step="1" placeholder="0" value="<?php echo esc_attr( (string) ( $behavior['cart_min_value'] ?? 0 ) ); ?>" />
					<span class="meyvc-currency"><?php echo esc_html( $currency_sym ); ?></span>
				</div>
				<div class="meyvc-range-input">
					<span><?php esc_html_e( 'Max:', 'meyvora-convert' ); ?></span>
					<input type="number" id="targeting-cart-max" min="0" step="1" placeholder="∞" value="<?php echo esc_attr( (string) ( $behavior['cart_max_value'] ?? '' ) ); ?>" />
					<span class="meyvc-currency"><?php echo esc_html( $currency_sym ); ?></span>
				</div>
			</div>
		</div>

		<div class="meyvc-control-group meyvc-conditional" data-show-when="targeting-cart-status=has_items">
			<label><?php esc_html_e( 'Cart item count:', 'meyvora-convert' ); ?></label>
			<div class="meyvc-range-inputs">
				<div class="meyvc-range-input">
					<span><?php esc_html_e( 'Min items:', 'meyvora-convert' ); ?></span>
					<input type="number" id="targeting-cart-min-items" min="0" placeholder="0" value="<?php echo esc_attr( (string) ( $behavior['cart_min_items'] ?? '' ) ); ?>" />
				</div>
				<div class="meyvc-range-input">
					<span><?php esc_html_e( 'Max items:', 'meyvora-convert' ); ?></span>
					<input type="number" id="targeting-cart-max-items" min="0" placeholder="∞" value="<?php echo esc_attr( (string) ( $behavior['cart_max_items'] ?? '' ) ); ?>" />
				</div>
			</div>
		</div>

		<div class="meyvc-control-group meyvc-conditional" data-show-when="targeting-cart-status=has_items">
			<label><?php esc_html_e( 'Cart contains product:', 'meyvora-convert' ); ?></label>
			<select id="targeting-cart-contains" multiple class="meyvc-selectwoo" data-placeholder="<?php esc_attr_e( 'Any product', 'meyvora-convert' ); ?>" data-action="meyvc_search_products">
				<!-- Populated via AJAX -->
			</select>
		</div>

		<div class="meyvc-control-group meyvc-conditional" data-show-when="targeting-cart-status=has_items">
			<label><?php esc_html_e( 'Cart contains category:', 'meyvora-convert' ); ?></label>
			<select id="targeting-cart-category" multiple class="meyvc-selectwoo" data-placeholder="<?php esc_attr_e( 'Any category', 'meyvora-convert' ); ?>">
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

		<div class="meyvc-control-group meyvc-conditional" data-show-when="targeting-cart-status=has_items">
			<label><?php esc_html_e( 'Exclude if cart contains product:', 'meyvora-convert' ); ?></label>
			<select id="targeting-cart-exclude-product" multiple class="meyvc-selectwoo" data-placeholder="<?php esc_attr_e( 'None', 'meyvora-convert' ); ?>" data-action="meyvc_search_products">
				<!-- Populated via AJAX -->
			</select>
			<p class="meyvc-hint"><?php esc_html_e( 'Do not show when cart contains any of these products', 'meyvora-convert' ); ?></p>
		</div>

		<div class="meyvc-control-group meyvc-conditional" data-show-when="targeting-cart-status=has_items">
			<label><?php esc_html_e( 'Exclude if cart contains category:', 'meyvora-convert' ); ?></label>
			<select id="targeting-cart-exclude-category" multiple class="meyvc-selectwoo" data-placeholder="<?php esc_attr_e( 'None', 'meyvora-convert' ); ?>">
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
			<p class="meyvc-hint"><?php esc_html_e( 'Do not show when cart contains any product from these categories', 'meyvora-convert' ); ?></p>
		</div>

		<div class="meyvc-control-group meyvc-conditional" data-show-when="targeting-cart-status=has_items">
			<label>
				<input type="checkbox" id="targeting-cart-has-sale" <?php checked( ! empty( $behavior['cart_has_sale_only'] ) ); ?> />
				<?php esc_html_e( 'Only if cart contains sale items', 'meyvora-convert' ); ?>
			</label>
		</div>
	</div>

	<!-- Behavioral Targeting -->
	<div class="meyvc-rule-section">
		<h3>
			<span class="meyvc-section-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'target', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

			<?php esc_html_e( 'Behavioral Targeting', 'meyvora-convert' ); ?>
		</h3>

		<div class="meyvc-control-group">
			<label><?php esc_html_e( 'Minimum time on page:', 'meyvora-convert' ); ?></label>
			<div class="meyvc-input-with-suffix">
				<input type="number" id="targeting-min-time" min="0" max="300" value="<?php echo esc_attr( (string) ( $behavior['min_time_on_page'] ?? 0 ) ); ?>" />
				<span class="meyvc-suffix"><?php esc_html_e( 'seconds (0 = no minimum)', 'meyvora-convert' ); ?></span>
			</div>
		</div>

		<div class="meyvc-control-group">
			<label><?php esc_html_e( 'Minimum scroll depth:', 'meyvora-convert' ); ?></label>
			<div class="meyvc-range-slider">
				<?php $min_scroll = (int) ( $behavior['min_scroll_depth'] ?? 0 ); ?>
				<input type="range" id="targeting-min-scroll" min="0" max="100" value="<?php echo esc_attr( (string) $min_scroll ); ?>" />
				<span class="meyvc-range-value"><span id="min-scroll-value"><?php echo esc_html( (string) $min_scroll ); ?></span>% (0 = no minimum)</span>
			</div>
		</div>

		<div class="meyvc-control-group">
			<label><?php esc_html_e( 'Minimum pages viewed this session:', 'meyvora-convert' ); ?></label>
			<input type="number" id="targeting-min-pages" min="0" value="<?php echo esc_attr( (string) ( $behavior['min_pages_viewed'] ?? $targeting['min_pages_viewed'] ?? 0 ) ); ?>" />
		</div>

		<div class="meyvc-control-group">
			<label>
				<input type="checkbox" id="targeting-require-interaction" <?php checked( ! empty( $behavior['require_interaction'] ) ); ?> />
				<?php esc_html_e( 'Require at least one click/interaction', 'meyvora-convert' ); ?>
			</label>
			<p class="meyvc-hint"><?php esc_html_e( 'Ensures visitor is engaged, not just bouncing', 'meyvora-convert' ); ?></p>
		</div>
	</div>

	<!-- Traffic Source Targeting -->
	<div class="meyvc-rule-section">
		<h3>
			<span class="meyvc-section-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'link', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

			<?php esc_html_e( 'Traffic Source', 'meyvora-convert' ); ?>
		</h3>
		<p class="meyvc-hint"><?php esc_html_e( 'Show this campaign only to visitors from a specific traffic source. UTM values persist across pages in the same session after the first landing URL with utm_source.', 'meyvora-convert' ); ?></p>

		<table class="form-table meyvc-targeting-table" role="presentation">
			<?php
			$traffic_fields = array(
				'referrer'     => array(
					'label'       => __( 'Referrer domain (e.g. google.com)', 'meyvora-convert' ),
					'placeholder' => 'google.com',
				),
				'utm_source'   => array(
					'label'       => __( 'UTM source (e.g. google, facebook)', 'meyvora-convert' ),
					'placeholder' => 'newsletter',
				),
				'utm_medium'   => array(
					'label'       => __( 'UTM medium (e.g. cpc, email)', 'meyvora-convert' ),
					'placeholder' => 'cpc',
				),
				'utm_campaign' => array(
					'label'       => __( 'UTM campaign (e.g. summer_sale)', 'meyvora-convert' ),
					'placeholder' => 'summer_sale',
				),
			);
			foreach ( $traffic_fields as $field => $meta ) :
				$val = isset( $targeting[ $field ] ) ? (string) $targeting[ $field ] : '';
				$op  = isset( $targeting[ $field . '_op' ] ) ? (string) $targeting[ $field . '_op' ] : '';
				$id  = 'targeting-' . str_replace( '_', '-', $field );
				?>
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $meta['label'] ); ?></label></th>
				<td>
					<select id="<?php echo esc_attr( $id ); ?>-op" class="meyvc-targeting-op meyvc-selectwoo" data-placeholder="<?php esc_attr_e( 'Any', 'meyvora-convert' ); ?>">
						<option value="" <?php selected( $op, '' ); ?>><?php esc_html_e( '— Any —', 'meyvora-convert' ); ?></option>
						<option value="equals" <?php selected( $op, 'equals' ); ?>><?php esc_html_e( 'Equals', 'meyvora-convert' ); ?></option>
						<option value="contains" <?php selected( $op, 'contains' ); ?>><?php esc_html_e( 'Contains', 'meyvora-convert' ); ?></option>
						<option value="not_equals" <?php selected( $op, 'not_equals' ); ?>><?php esc_html_e( 'Not equals', 'meyvora-convert' ); ?></option>
						<option value="not_contains" <?php selected( $op, 'not_contains' ); ?>><?php esc_html_e( 'Does not contain', 'meyvora-convert' ); ?></option>
						<option value="starts_with" <?php selected( $op, 'starts_with' ); ?>><?php esc_html_e( 'Starts with', 'meyvora-convert' ); ?></option>
						<option value="is_empty" <?php selected( $op, 'is_empty' ); ?>><?php esc_html_e( 'Is empty (organic/direct)', 'meyvora-convert' ); ?></option>
						<option value="is_not_empty" <?php selected( $op, 'is_not_empty' ); ?>><?php esc_html_e( 'Is not empty', 'meyvora-convert' ); ?></option>
					</select>
					<input type="text" id="<?php echo esc_attr( $id ); ?>" class="regular-text"
						value="<?php echo esc_attr( $val ); ?>"
						placeholder="<?php echo esc_attr( $meta['placeholder'] ); ?>" />
				</td>
			</tr>
				<?php endforeach; ?>
		</table>
	</div>

	<!-- Advanced: Custom Rules Builder -->
	<div class="meyvc-rule-section meyvc-advanced">
		<h3>
			<span class="meyvc-section-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'settings', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

			<?php esc_html_e( 'Advanced Rules', 'meyvora-convert' ); ?>
		</h3>

		<p class="meyvc-hint"><?php esc_html_e( 'Build custom rules using AND/OR logic for complex targeting scenarios', 'meyvora-convert' ); ?></p>

		<div class="meyvc-rule-builder" id="advanced-rules">

			<div class="meyvc-rule-groups">
				<!-- Rule groups will be added here dynamically -->
			</div>

			<button type="button" class="button" id="add-rule-group">
				<?php echo wp_kses( MEYVC_Icons::svg( 'plus', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?>

				<?php esc_html_e( 'Add Rule Group (OR)', 'meyvora-convert' ); ?>
			</button>
		</div>

		<!-- Rule Group Template (hidden) -->
		<template id="rule-group-template">
			<div class="meyvc-rule-group">
				<div class="meyvc-rule-group-header">
					<span class="meyvc-rule-group-logic"><?php esc_html_e( 'OR', 'meyvora-convert' ); ?></span>
					<button type="button" class="meyvc-remove-group" aria-label="<?php esc_attr_e( 'Remove', 'meyvora-convert' ); ?>"><?php echo wp_kses( MEYVC_Icons::svg( 'x', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></button>

				</div>
				<div class="meyvc-rules-list">
					<!-- Rules will be added here -->
				</div>
				<button type="button" class="button button-small meyvc-add-rule">
					<?php echo wp_kses( MEYVC_Icons::svg( 'plus', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?>

					<?php esc_html_e( 'Add Condition (AND)', 'meyvora-convert' ); ?>
				</button>
			</div>
		</template>

		<!-- Rule Template (hidden) -->
		<template id="rule-template">
			<div class="meyvc-rule-item">
				<select class="meyvc-rule-field meyvc-selectwoo" data-placeholder="<?php esc_attr_e( 'Select field', 'meyvora-convert' ); ?>">
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
				<select class="meyvc-rule-operator meyvc-selectwoo" data-placeholder="=">
					<option value="=">=</option>
					<option value="!=">≠</option>
					<option value=">">&gt;</option>
					<option value="<">&lt;</option>
					<option value=">=">&gt;=</option>
					<option value="<=">&lt;=</option>
					<option value="contains"><?php esc_html_e( 'contains', 'meyvora-convert' ); ?></option>
					<option value="not_contains"><?php esc_html_e( 'not contains', 'meyvora-convert' ); ?></option>
				</select>
				<input type="text" class="meyvc-rule-value" placeholder="<?php esc_attr_e( 'Value', 'meyvora-convert' ); ?>" />
				<button type="button" class="meyvc-remove-rule" aria-label="<?php esc_attr_e( 'Remove', 'meyvora-convert' ); ?>"><?php echo wp_kses( MEYVC_Icons::svg( 'x', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></button>

			</div>
		</template>
	</div>

</div>
