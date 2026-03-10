<?php
/**
 * Trigger controls partial for the campaign builder.
 * Expects $campaign_data (object with optional trigger_rules array).
 */
$trigger = ( is_object( $campaign_data ) && isset( $campaign_data->trigger_rules ) && is_array( $campaign_data->trigger_rules ) )
	? $campaign_data->trigger_rules
	: array();
$intent_weights = isset( $trigger['intent_weights'] ) && is_array( $trigger['intent_weights'] ) ? $trigger['intent_weights'] : array();
?>

<div class="cro-trigger-controls">

	<!-- Trigger Type -->
	<div class="cro-control-group">
		<label><?php esc_html_e( 'Trigger Type', 'meyvora-convert' ); ?></label>

		<div class="cro-trigger-types">

			<label class="cro-trigger-option">
				<input type="radio" name="trigger-type" value="exit_intent"
					   <?php checked( $trigger['type'] ?? 'exit_intent', 'exit_intent' ); ?> />
				<div class="cro-trigger-card">
					<span class="cro-trigger-icon"><?php echo CRO_Icons::svg( 'door-open', array( 'class' => 'cro-ico' ) ); ?></span>
					<span class="cro-trigger-name"><?php esc_html_e( 'Exit Intent', 'meyvora-convert' ); ?></span>
					<span class="cro-trigger-desc"><?php esc_html_e( 'When visitor is about to leave', 'meyvora-convert' ); ?></span>
				</div>
			</label>

			<label class="cro-trigger-option">
				<input type="radio" name="trigger-type" value="scroll"
					   <?php checked( $trigger['type'] ?? 'exit_intent', 'scroll' ); ?> />
				<div class="cro-trigger-card">
					<span class="cro-trigger-icon"><?php echo CRO_Icons::svg( 'scroll', array( 'class' => 'cro-ico' ) ); ?></span>
					<span class="cro-trigger-name"><?php esc_html_e( 'Scroll Depth', 'meyvora-convert' ); ?></span>
					<span class="cro-trigger-desc"><?php esc_html_e( 'When visitor scrolls to a point', 'meyvora-convert' ); ?></span>
				</div>
			</label>

			<label class="cro-trigger-option">
				<input type="radio" name="trigger-type" value="time"
					   <?php checked( $trigger['type'] ?? 'exit_intent', 'time' ); ?> />
				<div class="cro-trigger-card">
					<span class="cro-trigger-icon"><?php echo CRO_Icons::svg( 'clock', array( 'class' => 'cro-ico' ) ); ?></span>
					<span class="cro-trigger-name"><?php esc_html_e( 'Time Delay', 'meyvora-convert' ); ?></span>
					<span class="cro-trigger-desc"><?php esc_html_e( 'After X seconds on page', 'meyvora-convert' ); ?></span>
				</div>
			</label>

			<label class="cro-trigger-option">
				<input type="radio" name="trigger-type" value="inactivity"
					   <?php checked( $trigger['type'] ?? 'exit_intent', 'inactivity' ); ?> />
				<div class="cro-trigger-card">
					<span class="cro-trigger-icon"><?php echo CRO_Icons::svg( 'moon', array( 'class' => 'cro-ico' ) ); ?></span>
					<span class="cro-trigger-name"><?php esc_html_e( 'Inactivity', 'meyvora-convert' ); ?></span>
					<span class="cro-trigger-desc"><?php esc_html_e( 'When visitor stops interacting', 'meyvora-convert' ); ?></span>
				</div>
			</label>

			<label class="cro-trigger-option">
				<input type="radio" name="trigger-type" value="click"
					   <?php checked( $trigger['type'] ?? 'exit_intent', 'click' ); ?> />
				<div class="cro-trigger-card">
					<span class="cro-trigger-icon"><?php echo CRO_Icons::svg( 'pointer', array( 'class' => 'cro-ico' ) ); ?></span>
					<span class="cro-trigger-name"><?php esc_html_e( 'Click', 'meyvora-convert' ); ?></span>
					<span class="cro-trigger-desc"><?php esc_html_e( 'When element is clicked', 'meyvora-convert' ); ?></span>
				</div>
			</label>

		</div>
	</div>

	<!-- Exit Intent Options -->
	<div class="cro-trigger-options" data-trigger="exit_intent">
		<div class="cro-control-group">
			<label><?php esc_html_e( 'Sensitivity', 'meyvora-convert' ); ?></label>
			<select id="trigger-sensitivity" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Medium (balanced)', 'meyvora-convert' ); ?>">
				<option value="low" <?php selected( $trigger['sensitivity'] ?? 'medium', 'low' ); ?>><?php esc_html_e( 'Low (fewer triggers)', 'meyvora-convert' ); ?></option>
				<option value="medium" <?php selected( $trigger['sensitivity'] ?? 'medium', 'medium' ); ?>><?php esc_html_e( 'Medium (balanced)', 'meyvora-convert' ); ?></option>
				<option value="high" <?php selected( $trigger['sensitivity'] ?? 'medium', 'high' ); ?>><?php esc_html_e( 'High (more triggers)', 'meyvora-convert' ); ?></option>
			</select>
		</div>

		<div class="cro-control-group">
			<label>
				<input type="checkbox" id="trigger-mobile-exit" <?php checked( isset( $trigger['enable_mobile_exit'] ) ? ! empty( $trigger['enable_mobile_exit'] ) : true ); ?> />
				<?php esc_html_e( 'Enable mobile exit detection', 'meyvora-convert' ); ?>
			</label>
			<p class="cro-hint"><?php esc_html_e( 'Detects back button, fast scroll up, and tab switch on mobile', 'meyvora-convert' ); ?></p>
		</div>
	</div>

	<!-- Scroll Options -->
	<div class="cro-trigger-options cro-is-hidden" data-trigger="scroll">
		<div class="cro-control-group">
			<label><?php esc_html_e( 'Trigger at scroll depth', 'meyvora-convert' ); ?></label>
			<div class="cro-range-slider">
				<?php $scroll_depth = (int) ( $trigger['scroll_depth_percent'] ?? $trigger['scroll_depth'] ?? 50 ); ?>
				<input type="range" id="trigger-scroll-depth" min="10" max="100" value="<?php echo esc_attr( (string) $scroll_depth ); ?>" />
				<span class="cro-range-value"><span id="scroll-depth-value"><?php echo esc_html( (string) $scroll_depth ); ?></span>%</span>
			</div>
		</div>
	</div>

	<!-- Time Options -->
	<div class="cro-trigger-options cro-is-hidden" data-trigger="time">
		<div class="cro-control-group">
			<label><?php esc_html_e( 'Show after', 'meyvora-convert' ); ?></label>
			<div class="cro-input-with-suffix">
				<input type="number" id="trigger-time-delay" min="1" max="300" value="<?php echo esc_attr( (string) ( $trigger['time_delay_seconds'] ?? $trigger['time_delay'] ?? 10 ) ); ?>" />
				<span class="cro-suffix"><?php esc_html_e( 'seconds', 'meyvora-convert' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Inactivity Options -->
	<div class="cro-trigger-options cro-is-hidden" data-trigger="inactivity">
		<div class="cro-control-group">
			<label><?php esc_html_e( 'Show after idle for', 'meyvora-convert' ); ?></label>
			<div class="cro-input-with-suffix">
				<input type="number" id="trigger-idle-time" min="5" max="120" value="<?php echo esc_attr( (string) ( $trigger['idle_seconds'] ?? $trigger['idle_time'] ?? 30 ) ); ?>" />
				<span class="cro-suffix"><?php esc_html_e( 'seconds', 'meyvora-convert' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Click Options -->
	<div class="cro-trigger-options cro-is-hidden" data-trigger="click">
		<div class="cro-control-group">
			<label><?php esc_html_e( 'CSS Selector', 'meyvora-convert' ); ?></label>
			<input type="text" id="trigger-click-selector" placeholder="<?php esc_attr_e( '.my-button, #special-link', 'meyvora-convert' ); ?>" value="<?php echo esc_attr( (string) ( $trigger['click_selector'] ?? '' ) ); ?>" />
			<p class="cro-hint"><?php esc_html_e( 'Enter CSS selector for elements that trigger the popup', 'meyvora-convert' ); ?></p>
		</div>
	</div>

	<!-- Delay After Trigger -->
	<div class="cro-control-group">
		<label><?php esc_html_e( 'Delay after trigger', 'meyvora-convert' ); ?></label>
		<div class="cro-input-with-suffix">
			<input type="number" id="trigger-delay" min="0" max="10" value="<?php echo esc_attr( (string) ( $trigger['delay_seconds'] ?? $trigger['delay'] ?? 0 ) ); ?>" />
			<span class="cro-suffix"><?php esc_html_e( 'seconds', 'meyvora-convert' ); ?></span>
		</div>
		<p class="cro-hint"><?php esc_html_e( 'Wait this long after trigger before showing popup', 'meyvora-convert' ); ?></p>
	</div>

	<!-- Intent Threshold (Advanced) -->
	<div class="cro-control-group cro-advanced-toggle">
		<label>
			<input type="checkbox" id="show-intent-settings" <?php checked( ! empty( $trigger['use_custom_intent'] ) ); ?> />
			<?php esc_html_e( 'Advanced: Customize Intent Scoring', 'meyvora-convert' ); ?>
		</label>
	</div>

	<div class="cro-advanced-options <?php echo ! empty( $trigger['use_custom_intent'] ) ? '' : 'cro-is-hidden'; ?>" id="intent-settings">
		<p class="cro-hint"><?php esc_html_e( 'Adjust weights for different exit signals. Higher = more important.', 'meyvora-convert' ); ?></p>

		<div class="cro-intent-signals">
			<?php $ew = (int) ( $intent_weights['exit_mouse'] ?? 40 ); ?>
			<div class="cro-signal-weight">
				<label><?php esc_html_e( 'Exit Mouse Movement', 'meyvora-convert' ); ?></label>
				<input type="range" min="0" max="60" value="<?php echo esc_attr( (string) $ew ); ?>" data-signal="exit_mouse" />
				<span><?php echo esc_html( (string) $ew ); ?></span>
			</div>
			<?php $suf = (int) ( $intent_weights['scroll_up_fast'] ?? 25 ); ?>
			<div class="cro-signal-weight">
				<label><?php esc_html_e( 'Fast Scroll Up', 'meyvora-convert' ); ?></label>
				<input type="range" min="0" max="60" value="<?php echo esc_attr( (string) $suf ); ?>" data-signal="scroll_up_fast" />
				<span><?php echo esc_html( (string) $suf ); ?></span>
			</div>
			<?php $it = (int) ( $intent_weights['idle_time'] ?? 20 ); ?>
			<div class="cro-signal-weight">
				<label><?php esc_html_e( 'Idle Time', 'meyvora-convert' ); ?></label>
				<input type="range" min="0" max="60" value="<?php echo esc_attr( (string) $it ); ?>" data-signal="idle_time" />
				<span><?php echo esc_html( (string) $it ); ?></span>
			</div>
			<?php $top = (int) ( $intent_weights['time_on_page'] ?? 15 ); ?>
			<div class="cro-signal-weight">
				<label><?php esc_html_e( 'Time on Page', 'meyvora-convert' ); ?></label>
				<input type="range" min="0" max="60" value="<?php echo esc_attr( (string) $top ); ?>" data-signal="time_on_page" />
				<span><?php echo esc_html( (string) $top ); ?></span>
			</div>
		</div>

		<div class="cro-control-group">
			<label><?php esc_html_e( 'Intent Threshold', 'meyvora-convert' ); ?></label>
			<div class="cro-range-slider">
				<?php $threshold = (int) ( $trigger['intent_threshold'] ?? 60 ); ?>
				<input type="range" id="trigger-intent-threshold" min="30" max="90" value="<?php echo esc_attr( (string) $threshold ); ?>" />
				<span class="cro-range-value"><span id="intent-threshold-value"><?php echo esc_html( (string) $threshold ); ?></span></span>
			</div>
			<p class="cro-hint"><?php esc_html_e( 'Combined signal score must reach this threshold to trigger', 'meyvora-convert' ); ?></p>
		</div>
	</div>

</div>
