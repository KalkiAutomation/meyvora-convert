<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Display controls partial for the campaign builder.
 * Expects $campaign_data (object with optional frequency_rules and schedule).
 */
$freq    = ( is_object( $campaign_data ) && isset( $campaign_data->frequency_rules ) && is_array( $campaign_data->frequency_rules ) )
	? $campaign_data->frequency_rules
	: array();
$brand_override = ( is_object( $campaign_data ) && isset( $campaign_data->brand_styles_override ) && is_array( $campaign_data->brand_styles_override ) )
	? $campaign_data->brand_styles_override
	: array();
$schedule = ( is_object( $campaign_data ) && isset( $campaign_data->schedule ) && is_array( $campaign_data->schedule ) )
	? $campaign_data->schedule
	: array();
$days_of_week = isset( $schedule['days_of_week'] ) && is_array( $schedule['days_of_week'] ) ? $schedule['days_of_week'] : array( 0, 1, 2, 3, 4, 5, 6 );
$h_start = (int) ( $schedule['hours']['start'] ?? 0 );
$h_end   = (int) ( $schedule['hours']['end'] ?? 24 );
$display_time_start = ( $h_start >= 24 ) ? '23:59' : sprintf( '%02d:00', $h_start );
$display_time_end   = ( $h_end >= 24 ) ? '23:59' : sprintf( '%02d:00', $h_end );
$cooldown_hours = (int) ( ( $freq['dismissal_cooldown_seconds'] ?? 3600 ) / 3600 );
$currency_sym  = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';
$fb_id_builder    = ( is_object( $campaign_data ) && isset( $campaign_data->fallback_id ) ) ? (int) $campaign_data->fallback_id : 0;
$fb_delay_builder = ( is_object( $campaign_data ) && isset( $campaign_data->fallback_delay_seconds ) ) ? (int) $campaign_data->fallback_delay_seconds : 5;
$builder_cid      = ( is_object( $campaign_data ) && isset( $campaign_data->id ) ) ? (int) $campaign_data->id : 0;
$other_for_fallback = array();
if ( class_exists( 'MEYVC_Campaign' ) ) {
	foreach ( MEYVC_Campaign::get_all( array( 'limit' => 400 ) ) as $ob_row ) {
		if ( (int) ( $ob_row['id'] ?? 0 ) !== $builder_cid ) {
			$other_for_fallback[] = $ob_row;
		}
	}
}
?>

<div class="meyvc-display-controls">

	<div class="meyvc-rule-section">
		<h3>
			<span class="meyvc-section-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'sparkles', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>
			<?php esc_html_e( 'AI copy', 'meyvora-convert' ); ?>
		</h3>
		<p class="meyvc-hint"><?php esc_html_e( 'Generated text fills Headline, Body, and CTA on the Content tab.', 'meyvora-convert' ); ?></p>
		<div class="meyvc-ai-copy-bar" style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
			<input type="text" id="meyvc-ai-goal" placeholder="<?php esc_attr_e( 'e.g. recover abandoning visitors with 10% off', 'meyvora-convert' ); ?>"
				class="widefat" style="flex:1;min-width:200px" autocomplete="off" />
			<button type="button" id="meyvc-ai-generate-btn" class="button button-secondary">
				<?php esc_html_e( '✦ Generate with AI', 'meyvora-convert' ); ?>
			</button>
			<span class="meyvc-ai-spinner spinner" style="display:none;float:none;margin:0"></span>
		</div>
		<p class="meyvc-ai-error description" style="color:#cc1818;display:none"></p>
		<p class="meyvc-ai-regen" style="display:none;margin-top:8px">
			<a href="#" id="meyvc-ai-regen-link"><?php esc_html_e( 'Regenerate copy', 'meyvora-convert' ); ?></a>
		</p>
	</div>

	<!-- Frequency -->
	<div class="meyvc-rule-section">
		<h3>
			<span class="meyvc-section-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'refresh', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

			<?php esc_html_e( 'Frequency', 'meyvora-convert' ); ?>
		</h3>

		<div class="meyvc-control-group">
			<label><?php esc_html_e( 'Show this campaign:', 'meyvora-convert' ); ?></label>
			<select id="display-frequency" class="meyvc-selectwoo" data-placeholder="<?php esc_attr_e( 'Once per session', 'meyvora-convert' ); ?>">
				<option value="once_per_session" <?php selected( $freq['frequency'] ?? 'once_per_session', 'once_per_session' ); ?>><?php esc_html_e( 'Once per session', 'meyvora-convert' ); ?></option>
				<option value="once_per_day" <?php selected( $freq['frequency'] ?? 'once_per_session', 'once_per_day' ); ?>><?php esc_html_e( 'Once per day', 'meyvora-convert' ); ?></option>
				<option value="once_per_week" <?php selected( $freq['frequency'] ?? 'once_per_session', 'once_per_week' ); ?>><?php esc_html_e( 'Once per week', 'meyvora-convert' ); ?></option>
				<option value="once_per_x_days" <?php selected( $freq['frequency'] ?? 'once_per_session', 'once_per_x_days' ); ?>><?php esc_html_e( 'Once per X days', 'meyvora-convert' ); ?></option>
				<option value="once_ever" <?php selected( $freq['frequency'] ?? 'once_per_session', 'once_ever' ); ?>><?php esc_html_e( 'Once ever (per visitor)', 'meyvora-convert' ); ?></option>
				<option value="always" <?php selected( $freq['frequency'] ?? 'once_per_session', 'always' ); ?>><?php esc_html_e( 'Every time (no limit)', 'meyvora-convert' ); ?></option>
			</select>
		</div>

		<div class="meyvc-control-group meyvc-conditional" data-show-when="display-frequency=once_per_x_days">
			<label><?php esc_html_e( 'Number of days:', 'meyvora-convert' ); ?></label>
			<input type="number" id="display-frequency-days" min="1" max="365" value="<?php echo esc_attr( (string) ( $freq['frequency_days'] ?? 7 ) ); ?>" />
		</div>

		<div class="meyvc-control-group">
			<label><?php esc_html_e( 'After dismissal, wait:', 'meyvora-convert' ); ?></label>
			<div class="meyvc-input-with-suffix">
				<input type="number" id="display-cooldown" min="0" max="168" value="<?php echo esc_attr( (string) $cooldown_hours ); ?>" />
				<span class="meyvc-suffix"><?php esc_html_e( 'hours before showing again', 'meyvora-convert' ); ?></span>
			</div>
			<p class="meyvc-hint"><?php esc_html_e( 'Prevents annoying visitors who closed the popup', 'meyvora-convert' ); ?></p>
		</div>

		<div class="meyvc-control-group">
			<label><?php esc_html_e( 'Show max X times per visitor per Y period:', 'meyvora-convert' ); ?></label>
			<div class="meyvc-frequency-cap-row">
				<input type="number" id="display-max-impressions" min="0" value="<?php echo esc_attr( (string) ( $freq['max_impressions_per_visitor'] ?? 0 ) ); ?>" placeholder="<?php esc_attr_e( 'Unlimited', 'meyvora-convert' ); ?>" title="<?php esc_attr_e( 'Max times', 'meyvora-convert' ); ?>" />
				<span class="meyvc-cap-sep"><?php esc_html_e( 'times per', 'meyvora-convert' ); ?></span>
				<input type="number" id="display-frequency-period-value" min="1" max="365" value="<?php echo esc_attr( (string) ( $freq['frequency_period_value'] ?? 24 ) ); ?>" title="<?php esc_attr_e( 'Period value', 'meyvora-convert' ); ?>" />
				<select id="display-frequency-period-unit" class="meyvc-selectwoo" data-placeholder="<?php esc_attr_e( 'hours', 'meyvora-convert' ); ?>">
					<option value="hours" <?php selected( $freq['frequency_period_unit'] ?? 'hours', 'hours' ); ?>><?php esc_html_e( 'hours', 'meyvora-convert' ); ?></option>
					<option value="days" <?php selected( $freq['frequency_period_unit'] ?? 'hours', 'days' ); ?>><?php esc_html_e( 'days', 'meyvora-convert' ); ?></option>
				</select>
			</div>
			<p class="meyvc-hint"><?php esc_html_e( '0 = unlimited. Example: 3 times per 24 hours.', 'meyvora-convert' ); ?></p>
		</div>

		<div class="meyvc-control-group">
			<label><?php esc_html_e( 'Cooldown after conversion:', 'meyvora-convert' ); ?></label>
			<div class="meyvc-input-with-suffix">
				<input type="number" id="display-cooldown-conversion" min="0" max="8760" value="<?php echo esc_attr( (string) ( (int) ( ( $freq['cooldown_after_conversion_seconds'] ?? 0 ) / 3600 ) ) ); ?>" />
				<span class="meyvc-suffix"><?php esc_html_e( 'hours (0 = none)', 'meyvora-convert' ); ?></span>
			</div>
			<p class="meyvc-hint"><?php esc_html_e( 'Do not show again for this long after visitor converts', 'meyvora-convert' ); ?></p>
		</div>

		<div class="meyvc-control-group">
			<label><?php esc_html_e( 'Cooldown after CTA click:', 'meyvora-convert' ); ?></label>
			<div class="meyvc-input-with-suffix">
				<input type="number" id="display-cooldown-click" min="0" max="8760" value="<?php echo esc_attr( (string) ( (int) ( ( $freq['cooldown_after_click_seconds'] ?? 3600 ) / 3600 ) ) ); ?>" />
				<span class="meyvc-suffix"><?php esc_html_e( 'hours (0 = none)', 'meyvora-convert' ); ?></span>
			</div>
			<p class="meyvc-hint"><?php esc_html_e( 'Do not show again for this long after visitor clicks the CTA', 'meyvora-convert' ); ?></p>
		</div>

		<div class="meyvc-control-group meyvc-brand-override">
			<label>
				<input type="checkbox" id="display-brand-override-use" <?php checked( ! empty( $brand_override['use'] ) ); ?> />
				<?php esc_html_e( 'Override brand styles for this campaign', 'meyvora-convert' ); ?>
			</label>
			<p class="meyvc-hint"><?php esc_html_e( 'Use different primary/secondary colors, button radius, or font scale for this campaign only.', 'meyvora-convert' ); ?></p>
		</div>
		<div class="meyvc-control-group meyvc-conditional" data-show-when="display-brand-override-use=checked">
			<label><?php esc_html_e( 'Override primary color', 'meyvora-convert' ); ?></label>
			<input type="text" id="display-brand-primary" class="meyvc-color-picker" value="<?php echo esc_attr( $brand_override['primary_color'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Leave empty for global', 'meyvora-convert' ); ?>" />
		</div>
		<div class="meyvc-control-group meyvc-conditional" data-show-when="display-brand-override-use=checked">
			<label><?php esc_html_e( 'Override secondary color', 'meyvora-convert' ); ?></label>
			<input type="text" id="display-brand-secondary" class="meyvc-color-picker" value="<?php echo esc_attr( $brand_override['secondary_color'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Leave empty for global', 'meyvora-convert' ); ?>" />
		</div>
		<div class="meyvc-control-group meyvc-conditional" data-show-when="display-brand-override-use=checked">
			<label><?php esc_html_e( 'Override button radius (px)', 'meyvora-convert' ); ?></label>
			<input type="number" id="display-brand-button-radius" min="0" max="30" value="<?php echo esc_attr( (string) ( $brand_override['button_radius'] ?? '' ) ); ?>" placeholder="8" />
		</div>
		<div class="meyvc-control-group meyvc-conditional" data-show-when="display-brand-override-use=checked">
			<label><?php esc_html_e( 'Override font size scale', 'meyvora-convert' ); ?></label>
			<select id="display-brand-font-scale" class="meyvc-selectwoo" data-placeholder="<?php esc_attr_e( 'Use global', 'meyvora-convert' ); ?>">
				<option value=""><?php esc_html_e( 'Use global', 'meyvora-convert' ); ?></option>
				<option value="0.875" <?php selected( $brand_override['font_size_scale'] ?? '', '0.875' ); ?>><?php esc_html_e( 'Small (0.875×)', 'meyvora-convert' ); ?></option>
				<option value="1" <?php selected( $brand_override['font_size_scale'] ?? '', '1' ); ?>><?php esc_html_e( 'Normal (1×)', 'meyvora-convert' ); ?></option>
				<option value="1.125" <?php selected( $brand_override['font_size_scale'] ?? '', '1.125' ); ?>><?php esc_html_e( 'Large (1.125×)', 'meyvora-convert' ); ?></option>
				<option value="1.25" <?php selected( $brand_override['font_size_scale'] ?? '', '1.25' ); ?>><?php esc_html_e( 'Extra large (1.25×)', 'meyvora-convert' ); ?></option>
			</select>
		</div>
	</div>

	<!-- Priority -->
	<div class="meyvc-rule-section">
		<h3>
			<span class="meyvc-section-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'trending-up', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

			<?php esc_html_e( 'Priority', 'meyvora-convert' ); ?>
		</h3>

		<div class="meyvc-control-group">
			<label><?php esc_html_e( 'Campaign priority:', 'meyvora-convert' ); ?></label>
			<?php $priority = (int) ( $freq['priority'] ?? ( isset( $campaign_data->priority ) ? (int) $campaign_data->priority : 10 ) ); ?>
			<div class="meyvc-range-slider">
				<input type="range" id="display-priority" min="1" max="100" value="<?php echo esc_attr( (string) $priority ); ?>" />
				<span class="meyvc-range-value"><span id="priority-value"><?php echo esc_html( (string) $priority ); ?></span></span>
			</div>
			<p class="meyvc-hint"><?php esc_html_e( 'Higher priority campaigns show first when multiple campaigns match the same visitor', 'meyvora-convert' ); ?></p>
		</div>

		<div class="meyvc-control-group">
			<label>
				<input type="checkbox" id="display-is-fallback" <?php checked( ! empty( $freq['is_fallback'] ) ); ?> />
				<?php esc_html_e( 'Use as fallback campaign', 'meyvora-convert' ); ?>
			</label>
			<p class="meyvc-hint"><?php esc_html_e( 'Shows when no other campaigns match', 'meyvora-convert' ); ?></p>
		</div>
	</div>

	<!-- Schedule -->
	<div class="meyvc-rule-section">
		<h3>
			<span class="meyvc-section-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'calendar', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

			<?php esc_html_e( 'Schedule', 'meyvora-convert' ); ?>
		</h3>

		<div class="meyvc-control-group">
			<label>
				<input type="checkbox" id="display-schedule-enabled" <?php checked( ! empty( $schedule['enabled'] ) ); ?> />
				<?php esc_html_e( 'Enable scheduling', 'meyvora-convert' ); ?>
			</label>
		</div>

		<div class="meyvc-schedule-options <?php echo ! empty( $schedule['enabled'] ) ? '' : 'meyvc-is-hidden'; ?>" id="schedule-options">

			<div class="meyvc-control-group">
				<label><?php esc_html_e( 'Date range:', 'meyvora-convert' ); ?></label>
				<div class="meyvc-date-range">
					<div>
						<span><?php esc_html_e( 'Start:', 'meyvora-convert' ); ?></span>
						<input type="date" id="display-start-date" value="<?php echo esc_attr( (string) ( $schedule['start_date'] ?? '' ) ); ?>" />
					</div>
					<div>
						<span><?php esc_html_e( 'End:', 'meyvora-convert' ); ?></span>
						<input type="date" id="display-end-date" value="<?php echo esc_attr( (string) ( $schedule['end_date'] ?? '' ) ); ?>" />
					</div>
				</div>
			</div>

			<div class="meyvc-control-group">
				<label><?php esc_html_e( 'Days of week:', 'meyvora-convert' ); ?></label>
				<div class="meyvc-day-selector">
					<?php
					$day_labels = array(
						0 => __( 'Sun', 'meyvora-convert' ),
						1 => __( 'Mon', 'meyvora-convert' ),
						2 => __( 'Tue', 'meyvora-convert' ),
						3 => __( 'Wed', 'meyvora-convert' ),
						4 => __( 'Thu', 'meyvora-convert' ),
						5 => __( 'Fri', 'meyvora-convert' ),
						6 => __( 'Sat', 'meyvora-convert' ),
					);
					for ( $d = 0; $d <= 6; $d++ ) :
						$checked = in_array( $d, $days_of_week, true );
					?>
					<label class="meyvc-day-option">
						<input type="checkbox" name="schedule-days[]" value="<?php echo (int) $d; ?>" <?php checked( $checked ); ?> />
						<span><?php echo esc_html( $day_labels[ $d ] ); ?></span>
					</label>
					<?php endfor; ?>
				</div>
			</div>

			<div class="meyvc-control-group">
				<label><?php esc_html_e( 'Time of day:', 'meyvora-convert' ); ?></label>
				<div class="meyvc-time-range">
					<div>
						<span><?php esc_html_e( 'From:', 'meyvora-convert' ); ?></span>
						<input type="time" id="display-time-start" value="<?php echo esc_attr( $display_time_start ); ?>" />
					</div>
					<div>
						<span><?php esc_html_e( 'To:', 'meyvora-convert' ); ?></span>
						<input type="time" id="display-time-end" value="<?php echo esc_attr( $display_time_end ); ?>" />
					</div>
				</div>
			</div>

		</div>
	</div>

	<!-- Conversion Goal -->
	<div class="meyvc-rule-section">
		<h3>
			<span class="meyvc-section-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'target', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

			<?php esc_html_e( 'Conversion Goal', 'meyvora-convert' ); ?>
		</h3>

		<div class="meyvc-control-group">
			<label><?php esc_html_e( 'Auto-pause campaign after:', 'meyvora-convert' ); ?></label>
			<select id="display-auto-pause" class="meyvc-selectwoo" data-placeholder="<?php esc_attr_e( 'Never (run indefinitely)', 'meyvora-convert' ); ?>">
				<option value="none" <?php selected( $freq['auto_pause_type'] ?? 'none', 'none' ); ?>><?php esc_html_e( 'Never (run indefinitely)', 'meyvora-convert' ); ?></option>
				<option value="conversions" <?php selected( $freq['auto_pause_type'] ?? 'none', 'conversions' ); ?>><?php esc_html_e( 'Reaching X conversions', 'meyvora-convert' ); ?></option>
				<option value="impressions" <?php selected( $freq['auto_pause_type'] ?? 'none', 'impressions' ); ?>><?php esc_html_e( 'Reaching X impressions', 'meyvora-convert' ); ?></option>
				<option value="revenue" <?php selected( $freq['auto_pause_type'] ?? 'none', 'revenue' ); ?>><?php esc_html_e( 'Generating X revenue', 'meyvora-convert' ); ?></option>
			</select>
		</div>

		<div class="meyvc-control-group meyvc-conditional" data-show-when="display-auto-pause=conversions">
			<label><?php esc_html_e( 'Target conversions:', 'meyvora-convert' ); ?></label>
			<input type="number" id="display-target-conversions" min="1" value="<?php echo esc_attr( (string) ( $freq['target_conversions'] ?? 100 ) ); ?>" />
		</div>

		<div class="meyvc-control-group meyvc-conditional" data-show-when="display-auto-pause=impressions">
			<label><?php esc_html_e( 'Target impressions:', 'meyvora-convert' ); ?></label>
			<input type="number" id="display-target-impressions" min="1" value="<?php echo esc_attr( (string) ( $freq['target_impressions'] ?? 1000 ) ); ?>" />
		</div>

		<div class="meyvc-control-group meyvc-conditional" data-show-when="display-auto-pause=revenue">
			<label><?php esc_html_e( 'Target revenue:', 'meyvora-convert' ); ?></label>
			<div class="meyvc-input-with-prefix">
				<span class="meyvc-prefix"><?php echo esc_html( $currency_sym ); ?></span>
				<input type="number" id="display-target-revenue" min="1" value="<?php echo esc_attr( (string) ( $freq['target_revenue'] ?? 10000 ) ); ?>" />
			</div>
		</div>
	</div>

	<!-- After Conversion -->
	<div class="meyvc-rule-section">
		<h3>
			<span class="meyvc-section-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'check', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

			<?php esc_html_e( 'After Conversion', 'meyvora-convert' ); ?>
		</h3>

		<div class="meyvc-control-group">
			<label><?php esc_html_e( 'When visitor converts:', 'meyvora-convert' ); ?></label>
			<select id="display-after-conversion" class="meyvc-selectwoo" data-placeholder="<?php esc_attr_e( 'Never show this campaign again', 'meyvora-convert' ); ?>">
				<option value="hide_forever" <?php selected( $freq['after_conversion'] ?? 'hide_forever', 'hide_forever' ); ?>><?php esc_html_e( 'Never show this campaign again', 'meyvora-convert' ); ?></option>
				<option value="hide_session" <?php selected( $freq['after_conversion'] ?? 'hide_forever', 'hide_session' ); ?>><?php esc_html_e( 'Hide for rest of session', 'meyvora-convert' ); ?></option>
				<option value="hide_days" <?php selected( $freq['after_conversion'] ?? 'hide_forever', 'hide_days' ); ?>><?php esc_html_e( 'Hide for X days', 'meyvora-convert' ); ?></option>
				<option value="show_different" <?php selected( $freq['after_conversion'] ?? 'hide_forever', 'show_different' ); ?>><?php esc_html_e( 'Show a different campaign', 'meyvora-convert' ); ?></option>
			</select>
		</div>

		<div class="meyvc-control-group meyvc-conditional" data-show-when="display-after-conversion=hide_days">
			<label><?php esc_html_e( 'Days to hide:', 'meyvora-convert' ); ?></label>
			<input type="number" id="display-hide-days" min="1" max="365" value="<?php echo esc_attr( (string) ( $freq['hide_days'] ?? 30 ) ); ?>" />
		</div>

		<div class="meyvc-control-group meyvc-conditional" data-show-when="display-after-conversion=show_different">
			<label><?php esc_html_e( 'Follow-up campaign:', 'meyvora-convert' ); ?></label>
			<select id="display-followup-campaign" class="meyvc-selectwoo" data-placeholder="<?php esc_attr_e( 'Select campaign...', 'meyvora-convert' ); ?>" data-selected="<?php echo esc_attr( (string) ( $freq['followup_campaign_id'] ?? '' ) ); ?>">
				<option value=""><?php esc_html_e( 'Select campaign...', 'meyvora-convert' ); ?></option>
				<!-- Populated via AJAX -->
			</select>
		</div>
	</div>

	<!-- Dismiss fallback chain -->
	<div class="meyvc-rule-section">
		<h3>
			<span class="meyvc-section-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'refresh', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>
			<?php esc_html_e( 'Fallback after dismiss', 'meyvora-convert' ); ?>
		</h3>
		<p class="meyvc-hint"><?php esc_html_e( 'When a visitor closes this popup, optionally show another campaign after a delay.', 'meyvora-convert' ); ?></p>
		<div class="meyvc-control-group">
			<label for="display-fallback-id"><?php esc_html_e( 'If dismissed, show:', 'meyvora-convert' ); ?></label>
			<select id="display-fallback-id" class="meyvc-selectwoo" data-placeholder="<?php esc_attr_e( '— None —', 'meyvora-convert' ); ?>">
				<option value="0" <?php selected( $fb_id_builder, 0 ); ?>><?php esc_html_e( '— None —', 'meyvora-convert' ); ?></option>
				<?php foreach ( $other_for_fallback as $of ) : ?>
					<option value="<?php echo esc_attr( (string) ( $of['id'] ?? '' ) ); ?>" <?php selected( $fb_id_builder, (int) ( $of['id'] ?? 0 ) ); ?>>
						<?php echo esc_html( (string) ( $of['name'] ?? '' ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="meyvc-control-group" id="meyvc-fallback-delay-row-builder" style="<?php echo $fb_id_builder > 0 ? '' : 'display:none;'; ?>">
			<label for="display-fallback-delay"><?php esc_html_e( 'Delay (seconds):', 'meyvora-convert' ); ?></label>
			<input type="number" id="display-fallback-delay" min="0" max="300" value="<?php echo esc_attr( (string) $fb_delay_builder ); ?>" />
		</div>
	</div>

</div>
