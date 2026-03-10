<?php
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
?>

<div class="cro-display-controls">

	<!-- Frequency -->
	<div class="cro-rule-section">
		<h3>
			<span class="cro-section-icon"><?php echo CRO_Icons::svg( 'refresh', array( 'class' => 'cro-ico' ) ); ?></span>
			<?php esc_html_e( 'Frequency', 'meyvora-convert' ); ?>
		</h3>

		<div class="cro-control-group">
			<label><?php esc_html_e( 'Show this campaign:', 'meyvora-convert' ); ?></label>
			<select id="display-frequency" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Once per session', 'meyvora-convert' ); ?>">
				<option value="once_per_session" <?php selected( $freq['frequency'] ?? 'once_per_session', 'once_per_session' ); ?>><?php esc_html_e( 'Once per session', 'meyvora-convert' ); ?></option>
				<option value="once_per_day" <?php selected( $freq['frequency'] ?? 'once_per_session', 'once_per_day' ); ?>><?php esc_html_e( 'Once per day', 'meyvora-convert' ); ?></option>
				<option value="once_per_week" <?php selected( $freq['frequency'] ?? 'once_per_session', 'once_per_week' ); ?>><?php esc_html_e( 'Once per week', 'meyvora-convert' ); ?></option>
				<option value="once_per_x_days" <?php selected( $freq['frequency'] ?? 'once_per_session', 'once_per_x_days' ); ?>><?php esc_html_e( 'Once per X days', 'meyvora-convert' ); ?></option>
				<option value="once_ever" <?php selected( $freq['frequency'] ?? 'once_per_session', 'once_ever' ); ?>><?php esc_html_e( 'Once ever (per visitor)', 'meyvora-convert' ); ?></option>
				<option value="always" <?php selected( $freq['frequency'] ?? 'once_per_session', 'always' ); ?>><?php esc_html_e( 'Every time (no limit)', 'meyvora-convert' ); ?></option>
			</select>
		</div>

		<div class="cro-control-group cro-conditional" data-show-when="display-frequency=once_per_x_days">
			<label><?php esc_html_e( 'Number of days:', 'meyvora-convert' ); ?></label>
			<input type="number" id="display-frequency-days" min="1" max="365" value="<?php echo esc_attr( (string) ( $freq['frequency_days'] ?? 7 ) ); ?>" />
		</div>

		<div class="cro-control-group">
			<label><?php esc_html_e( 'After dismissal, wait:', 'meyvora-convert' ); ?></label>
			<div class="cro-input-with-suffix">
				<input type="number" id="display-cooldown" min="0" max="168" value="<?php echo esc_attr( (string) $cooldown_hours ); ?>" />
				<span class="cro-suffix"><?php esc_html_e( 'hours before showing again', 'meyvora-convert' ); ?></span>
			</div>
			<p class="cro-hint"><?php esc_html_e( 'Prevents annoying visitors who closed the popup', 'meyvora-convert' ); ?></p>
		</div>

		<div class="cro-control-group">
			<label><?php esc_html_e( 'Show max X times per visitor per Y period:', 'meyvora-convert' ); ?></label>
			<div class="cro-frequency-cap-row">
				<input type="number" id="display-max-impressions" min="0" value="<?php echo esc_attr( (string) ( $freq['max_impressions_per_visitor'] ?? 0 ) ); ?>" placeholder="<?php esc_attr_e( 'Unlimited', 'meyvora-convert' ); ?>" title="<?php esc_attr_e( 'Max times', 'meyvora-convert' ); ?>" />
				<span class="cro-cap-sep"><?php esc_html_e( 'times per', 'meyvora-convert' ); ?></span>
				<input type="number" id="display-frequency-period-value" min="1" max="365" value="<?php echo esc_attr( (string) ( $freq['frequency_period_value'] ?? 24 ) ); ?>" title="<?php esc_attr_e( 'Period value', 'meyvora-convert' ); ?>" />
				<select id="display-frequency-period-unit" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'hours', 'meyvora-convert' ); ?>">
					<option value="hours" <?php selected( $freq['frequency_period_unit'] ?? 'hours', 'hours' ); ?>><?php esc_html_e( 'hours', 'meyvora-convert' ); ?></option>
					<option value="days" <?php selected( $freq['frequency_period_unit'] ?? 'hours', 'days' ); ?>><?php esc_html_e( 'days', 'meyvora-convert' ); ?></option>
				</select>
			</div>
			<p class="cro-hint"><?php esc_html_e( '0 = unlimited. Example: 3 times per 24 hours.', 'meyvora-convert' ); ?></p>
		</div>

		<div class="cro-control-group">
			<label><?php esc_html_e( 'Cooldown after conversion:', 'meyvora-convert' ); ?></label>
			<div class="cro-input-with-suffix">
				<input type="number" id="display-cooldown-conversion" min="0" max="8760" value="<?php echo esc_attr( (string) ( (int) ( ( $freq['cooldown_after_conversion_seconds'] ?? 0 ) / 3600 ) ) ); ?>" />
				<span class="cro-suffix"><?php esc_html_e( 'hours (0 = none)', 'meyvora-convert' ); ?></span>
			</div>
			<p class="cro-hint"><?php esc_html_e( 'Do not show again for this long after visitor converts', 'meyvora-convert' ); ?></p>
		</div>

		<div class="cro-control-group">
			<label><?php esc_html_e( 'Cooldown after CTA click:', 'meyvora-convert' ); ?></label>
			<div class="cro-input-with-suffix">
				<input type="number" id="display-cooldown-click" min="0" max="8760" value="<?php echo esc_attr( (string) ( (int) ( ( $freq['cooldown_after_click_seconds'] ?? 3600 ) / 3600 ) ) ); ?>" />
				<span class="cro-suffix"><?php esc_html_e( 'hours (0 = none)', 'meyvora-convert' ); ?></span>
			</div>
			<p class="cro-hint"><?php esc_html_e( 'Do not show again for this long after visitor clicks the CTA', 'meyvora-convert' ); ?></p>
		</div>

		<div class="cro-control-group cro-brand-override">
			<label>
				<input type="checkbox" id="display-brand-override-use" <?php checked( ! empty( $brand_override['use'] ) ); ?> />
				<?php esc_html_e( 'Override brand styles for this campaign', 'meyvora-convert' ); ?>
			</label>
			<p class="cro-hint"><?php esc_html_e( 'Use different primary/secondary colors, button radius, or font scale for this campaign only.', 'meyvora-convert' ); ?></p>
		</div>
		<div class="cro-control-group cro-conditional" data-show-when="display-brand-override-use=checked">
			<label><?php esc_html_e( 'Override primary color', 'meyvora-convert' ); ?></label>
			<input type="text" id="display-brand-primary" class="cro-color-picker" value="<?php echo esc_attr( $brand_override['primary_color'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Leave empty for global', 'meyvora-convert' ); ?>" />
		</div>
		<div class="cro-control-group cro-conditional" data-show-when="display-brand-override-use=checked">
			<label><?php esc_html_e( 'Override secondary color', 'meyvora-convert' ); ?></label>
			<input type="text" id="display-brand-secondary" class="cro-color-picker" value="<?php echo esc_attr( $brand_override['secondary_color'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Leave empty for global', 'meyvora-convert' ); ?>" />
		</div>
		<div class="cro-control-group cro-conditional" data-show-when="display-brand-override-use=checked">
			<label><?php esc_html_e( 'Override button radius (px)', 'meyvora-convert' ); ?></label>
			<input type="number" id="display-brand-button-radius" min="0" max="30" value="<?php echo esc_attr( (string) ( $brand_override['button_radius'] ?? '' ) ); ?>" placeholder="8" />
		</div>
		<div class="cro-control-group cro-conditional" data-show-when="display-brand-override-use=checked">
			<label><?php esc_html_e( 'Override font size scale', 'meyvora-convert' ); ?></label>
			<select id="display-brand-font-scale" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Use global', 'meyvora-convert' ); ?>">
				<option value=""><?php esc_html_e( 'Use global', 'meyvora-convert' ); ?></option>
				<option value="0.875" <?php selected( $brand_override['font_size_scale'] ?? '', '0.875' ); ?>><?php esc_html_e( 'Small (0.875×)', 'meyvora-convert' ); ?></option>
				<option value="1" <?php selected( $brand_override['font_size_scale'] ?? '', '1' ); ?>><?php esc_html_e( 'Normal (1×)', 'meyvora-convert' ); ?></option>
				<option value="1.125" <?php selected( $brand_override['font_size_scale'] ?? '', '1.125' ); ?>><?php esc_html_e( 'Large (1.125×)', 'meyvora-convert' ); ?></option>
				<option value="1.25" <?php selected( $brand_override['font_size_scale'] ?? '', '1.25' ); ?>><?php esc_html_e( 'Extra large (1.25×)', 'meyvora-convert' ); ?></option>
			</select>
		</div>
	</div>

	<!-- Priority -->
	<div class="cro-rule-section">
		<h3>
			<span class="cro-section-icon"><?php echo CRO_Icons::svg( 'trending-up', array( 'class' => 'cro-ico' ) ); ?></span>
			<?php esc_html_e( 'Priority', 'meyvora-convert' ); ?>
		</h3>

		<div class="cro-control-group">
			<label><?php esc_html_e( 'Campaign priority:', 'meyvora-convert' ); ?></label>
			<?php $priority = (int) ( $freq['priority'] ?? ( isset( $campaign_data->priority ) ? (int) $campaign_data->priority : 10 ) ); ?>
			<div class="cro-range-slider">
				<input type="range" id="display-priority" min="1" max="100" value="<?php echo esc_attr( (string) $priority ); ?>" />
				<span class="cro-range-value"><span id="priority-value"><?php echo esc_html( (string) $priority ); ?></span></span>
			</div>
			<p class="cro-hint"><?php esc_html_e( 'Higher priority campaigns show first when multiple campaigns match the same visitor', 'meyvora-convert' ); ?></p>
		</div>

		<div class="cro-control-group">
			<label>
				<input type="checkbox" id="display-is-fallback" <?php checked( ! empty( $freq['is_fallback'] ) ); ?> />
				<?php esc_html_e( 'Use as fallback campaign', 'meyvora-convert' ); ?>
			</label>
			<p class="cro-hint"><?php esc_html_e( 'Shows when no other campaigns match', 'meyvora-convert' ); ?></p>
		</div>
	</div>

	<!-- Schedule -->
	<div class="cro-rule-section">
		<h3>
			<span class="cro-section-icon"><?php echo CRO_Icons::svg( 'calendar', array( 'class' => 'cro-ico' ) ); ?></span>
			<?php esc_html_e( 'Schedule', 'meyvora-convert' ); ?>
		</h3>

		<div class="cro-control-group">
			<label>
				<input type="checkbox" id="display-schedule-enabled" <?php checked( ! empty( $schedule['enabled'] ) ); ?> />
				<?php esc_html_e( 'Enable scheduling', 'meyvora-convert' ); ?>
			</label>
		</div>

		<div class="cro-schedule-options <?php echo ! empty( $schedule['enabled'] ) ? '' : 'cro-is-hidden'; ?>" id="schedule-options">

			<div class="cro-control-group">
				<label><?php esc_html_e( 'Date range:', 'meyvora-convert' ); ?></label>
				<div class="cro-date-range">
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

			<div class="cro-control-group">
				<label><?php esc_html_e( 'Days of week:', 'meyvora-convert' ); ?></label>
				<div class="cro-day-selector">
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
					<label class="cro-day-option">
						<input type="checkbox" name="schedule-days[]" value="<?php echo (int) $d; ?>" <?php checked( $checked ); ?> />
						<span><?php echo esc_html( $day_labels[ $d ] ); ?></span>
					</label>
					<?php endfor; ?>
				</div>
			</div>

			<div class="cro-control-group">
				<label><?php esc_html_e( 'Time of day:', 'meyvora-convert' ); ?></label>
				<div class="cro-time-range">
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
	<div class="cro-rule-section">
		<h3>
			<span class="cro-section-icon"><?php echo CRO_Icons::svg( 'target', array( 'class' => 'cro-ico' ) ); ?></span>
			<?php esc_html_e( 'Conversion Goal', 'meyvora-convert' ); ?>
		</h3>

		<div class="cro-control-group">
			<label><?php esc_html_e( 'Auto-pause campaign after:', 'meyvora-convert' ); ?></label>
			<select id="display-auto-pause" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Never (run indefinitely)', 'meyvora-convert' ); ?>">
				<option value="none" <?php selected( $freq['auto_pause_type'] ?? 'none', 'none' ); ?>><?php esc_html_e( 'Never (run indefinitely)', 'meyvora-convert' ); ?></option>
				<option value="conversions" <?php selected( $freq['auto_pause_type'] ?? 'none', 'conversions' ); ?>><?php esc_html_e( 'Reaching X conversions', 'meyvora-convert' ); ?></option>
				<option value="impressions" <?php selected( $freq['auto_pause_type'] ?? 'none', 'impressions' ); ?>><?php esc_html_e( 'Reaching X impressions', 'meyvora-convert' ); ?></option>
				<option value="revenue" <?php selected( $freq['auto_pause_type'] ?? 'none', 'revenue' ); ?>><?php esc_html_e( 'Generating X revenue', 'meyvora-convert' ); ?></option>
			</select>
		</div>

		<div class="cro-control-group cro-conditional" data-show-when="display-auto-pause=conversions">
			<label><?php esc_html_e( 'Target conversions:', 'meyvora-convert' ); ?></label>
			<input type="number" id="display-target-conversions" min="1" value="<?php echo esc_attr( (string) ( $freq['target_conversions'] ?? 100 ) ); ?>" />
		</div>

		<div class="cro-control-group cro-conditional" data-show-when="display-auto-pause=impressions">
			<label><?php esc_html_e( 'Target impressions:', 'meyvora-convert' ); ?></label>
			<input type="number" id="display-target-impressions" min="1" value="<?php echo esc_attr( (string) ( $freq['target_impressions'] ?? 1000 ) ); ?>" />
		</div>

		<div class="cro-control-group cro-conditional" data-show-when="display-auto-pause=revenue">
			<label><?php esc_html_e( 'Target revenue:', 'meyvora-convert' ); ?></label>
			<div class="cro-input-with-prefix">
				<span class="cro-prefix"><?php echo esc_html( $currency_sym ); ?></span>
				<input type="number" id="display-target-revenue" min="1" value="<?php echo esc_attr( (string) ( $freq['target_revenue'] ?? 10000 ) ); ?>" />
			</div>
		</div>
	</div>

	<!-- After Conversion -->
	<div class="cro-rule-section">
		<h3>
			<span class="cro-section-icon"><?php echo CRO_Icons::svg( 'check', array( 'class' => 'cro-ico' ) ); ?></span>
			<?php esc_html_e( 'After Conversion', 'meyvora-convert' ); ?>
		</h3>

		<div class="cro-control-group">
			<label><?php esc_html_e( 'When visitor converts:', 'meyvora-convert' ); ?></label>
			<select id="display-after-conversion" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Never show this campaign again', 'meyvora-convert' ); ?>">
				<option value="hide_forever" <?php selected( $freq['after_conversion'] ?? 'hide_forever', 'hide_forever' ); ?>><?php esc_html_e( 'Never show this campaign again', 'meyvora-convert' ); ?></option>
				<option value="hide_session" <?php selected( $freq['after_conversion'] ?? 'hide_forever', 'hide_session' ); ?>><?php esc_html_e( 'Hide for rest of session', 'meyvora-convert' ); ?></option>
				<option value="hide_days" <?php selected( $freq['after_conversion'] ?? 'hide_forever', 'hide_days' ); ?>><?php esc_html_e( 'Hide for X days', 'meyvora-convert' ); ?></option>
				<option value="show_different" <?php selected( $freq['after_conversion'] ?? 'hide_forever', 'show_different' ); ?>><?php esc_html_e( 'Show a different campaign', 'meyvora-convert' ); ?></option>
			</select>
		</div>

		<div class="cro-control-group cro-conditional" data-show-when="display-after-conversion=hide_days">
			<label><?php esc_html_e( 'Days to hide:', 'meyvora-convert' ); ?></label>
			<input type="number" id="display-hide-days" min="1" max="365" value="<?php echo esc_attr( (string) ( $freq['hide_days'] ?? 30 ) ); ?>" />
		</div>

		<div class="cro-control-group cro-conditional" data-show-when="display-after-conversion=show_different">
			<label><?php esc_html_e( 'Follow-up campaign:', 'meyvora-convert' ); ?></label>
			<select id="display-followup-campaign" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Select campaign...', 'meyvora-convert' ); ?>" data-selected="<?php echo esc_attr( (string) ( $freq['followup_campaign_id'] ?? '' ) ); ?>">
				<option value=""><?php esc_html_e( 'Select campaign...', 'meyvora-convert' ); ?></option>
				<!-- Populated via AJAX -->
			</select>
		</div>
	</div>

</div>
