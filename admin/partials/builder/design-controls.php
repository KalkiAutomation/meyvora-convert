<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Design controls partial for the campaign builder.
 * Expects $campaign_data (object with optional styling array).
 */
$styling = ( is_object( $campaign_data ) && isset( $campaign_data->styling ) && is_array( $campaign_data->styling ) )
	? $campaign_data->styling
	: array();
$wheel_slices_json = isset( $wheel_slices_json ) ? (string) $wheel_slices_json : '';
?>

<div class="meyvc-design-controls">

	<!-- Color Scheme -->
	<div class="meyvc-control-group">
		<h3><?php esc_html_e( 'Colors', 'meyvora-convert' ); ?></h3>

		<div class="meyvc-color-grid">
			<div class="meyvc-color-field">
				<label><?php esc_html_e( 'Background', 'meyvora-convert' ); ?></label>
				<input type="text" class="meyvc-color-picker" id="design-bg-color"
					   value="<?php echo esc_attr( $styling['bg_color'] ?? '#ffffff' ); ?>" />
			</div>

			<div class="meyvc-color-field">
				<label><?php esc_html_e( 'Text', 'meyvora-convert' ); ?></label>
				<input type="text" class="meyvc-color-picker" id="design-text-color"
					   value="<?php echo esc_attr( $styling['text_color'] ?? '#333333' ); ?>" />
			</div>

			<div class="meyvc-color-field">
				<label><?php esc_html_e( 'Headline', 'meyvora-convert' ); ?></label>
				<input type="text" class="meyvc-color-picker" id="design-headline-color"
					   value="<?php echo esc_attr( $styling['headline_color'] ?? '#000000' ); ?>" />
			</div>

			<div class="meyvc-color-field">
				<label><?php esc_html_e( 'Button Background', 'meyvora-convert' ); ?></label>
				<input type="text" class="meyvc-color-picker" id="design-button-bg"
					   value="<?php echo esc_attr( $styling['button_bg_color'] ?? '#333333' ); ?>" />
			</div>

			<div class="meyvc-color-field">
				<label><?php esc_html_e( 'Button Text', 'meyvora-convert' ); ?></label>
				<input type="text" class="meyvc-color-picker" id="design-button-text"
					   value="<?php echo esc_attr( $styling['button_text_color'] ?? '#ffffff' ); ?>" />
			</div>

			<div class="meyvc-color-field">
				<label><?php esc_html_e( 'Overlay', 'meyvora-convert' ); ?></label>
				<input type="text" class="meyvc-color-picker" id="design-overlay-color"
					   value="<?php echo esc_attr( $styling['overlay_color'] ?? '#000000' ); ?>" />
			</div>
		</div>
	</div>

	<!-- Overlay Opacity -->
	<div class="meyvc-control-group">
		<label><?php esc_html_e( 'Overlay Opacity', 'meyvora-convert' ); ?></label>
		<div class="meyvc-range-slider">
			<input type="range" id="design-overlay-opacity"
				   min="0" max="100"
				   value="<?php echo esc_attr( (string) ( $styling['overlay_opacity'] ?? 50 ) ); ?>" />
			<span class="meyvc-range-value"><span id="overlay-opacity-value"><?php echo esc_html( (string) ( $styling['overlay_opacity'] ?? 50 ) ); ?></span>%</span>
		</div>
	</div>

	<!-- Border Radius -->
	<div class="meyvc-control-group">
		<label><?php esc_html_e( 'Border Radius', 'meyvora-convert' ); ?></label>
		<div class="meyvc-range-slider">
			<input type="range" id="design-border-radius"
				   min="0" max="30"
				   value="<?php echo esc_attr( (string) ( $styling['border_radius'] ?? 8 ) ); ?>" />
			<span class="meyvc-range-value"><span id="border-radius-value"><?php echo esc_html( (string) ( $styling['border_radius'] ?? 8 ) ); ?></span>px</span>
		</div>
	</div>

	<!-- Popup Size -->
	<div class="meyvc-control-group">
		<label><?php esc_html_e( 'Popup Size', 'meyvora-convert' ); ?></label>
		<div class="meyvc-size-options">
			<label class="meyvc-size-option">
				<input type="radio" name="design-size" value="small"
					   <?php checked( $styling['size'] ?? 'medium', 'small' ); ?> />
				<span><?php esc_html_e( 'Small', 'meyvora-convert' ); ?></span>
			</label>
			<label class="meyvc-size-option">
				<input type="radio" name="design-size" value="medium"
					   <?php checked( $styling['size'] ?? 'medium', 'medium' ); ?> />
				<span><?php esc_html_e( 'Medium', 'meyvora-convert' ); ?></span>
			</label>
			<label class="meyvc-size-option">
				<input type="radio" name="design-size" value="large"
					   <?php checked( $styling['size'] ?? 'medium', 'large' ); ?> />
				<span><?php esc_html_e( 'Large', 'meyvora-convert' ); ?></span>
			</label>
			<label class="meyvc-size-option">
				<input type="radio" name="design-size" value="fullscreen"
					   <?php checked( $styling['size'] ?? 'medium', 'fullscreen' ); ?> />
				<span><?php esc_html_e( 'Fullscreen', 'meyvora-convert' ); ?></span>
			</label>
		</div>
	</div>

	<!-- Animation -->
	<div class="meyvc-control-group">
		<label><?php esc_html_e( 'Animation', 'meyvora-convert' ); ?></label>
		<select id="design-animation" class="meyvc-selectwoo" data-placeholder="<?php esc_attr_e( 'Fade In', 'meyvora-convert' ); ?>">
			<option value="fade" <?php selected( $styling['animation'] ?? 'fade', 'fade' ); ?>>
				<?php esc_html_e( 'Fade In', 'meyvora-convert' ); ?>
			</option>
			<option value="slide-up" <?php selected( $styling['animation'] ?? 'fade', 'slide-up' ); ?>>
				<?php esc_html_e( 'Slide Up', 'meyvora-convert' ); ?>
			</option>
			<option value="slide-down" <?php selected( $styling['animation'] ?? 'fade', 'slide-down' ); ?>>
				<?php esc_html_e( 'Slide Down', 'meyvora-convert' ); ?>
			</option>
			<option value="zoom" <?php selected( $styling['animation'] ?? 'fade', 'zoom' ); ?>>
				<?php esc_html_e( 'Zoom In', 'meyvora-convert' ); ?>
			</option>
			<option value="bounce" <?php selected( $styling['animation'] ?? 'fade', 'bounce' ); ?>>
				<?php esc_html_e( 'Bounce', 'meyvora-convert' ); ?>
			</option>
			<option value="none" <?php selected( $styling['animation'] ?? 'fade', 'none' ); ?>>
				<?php esc_html_e( 'None', 'meyvora-convert' ); ?>
			</option>
		</select>
	</div>

	<!-- Position (for non-centered templates) -->
	<?php
	$position = $styling['position'] ?? 'center';
	?>
	<div class="meyvc-control-group" id="position-control">
		<label><?php esc_html_e( 'Position', 'meyvora-convert' ); ?></label>
		<div class="meyvc-position-grid">
			<button type="button" data-position="top-left" class="meyvc-position-btn<?php echo $position === 'top-left' ? ' active' : ''; ?>">↖</button>
			<button type="button" data-position="top-center" class="meyvc-position-btn<?php echo $position === 'top-center' ? ' active' : ''; ?>"><?php echo wp_kses( MEYVC_Icons::svg( 'chevron-up', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></button>

			<button type="button" data-position="top-right" class="meyvc-position-btn<?php echo $position === 'top-right' ? ' active' : ''; ?>">↗</button>
			<button type="button" data-position="center-left" class="meyvc-position-btn<?php echo $position === 'center-left' ? ' active' : ''; ?>"><?php echo wp_kses( MEYVC_Icons::svg( 'chevron-left', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></button>

			<button type="button" data-position="center" class="meyvc-position-btn<?php echo $position === 'center' ? ' active' : ''; ?>">•</button>
			<button type="button" data-position="center-right" class="meyvc-position-btn<?php echo $position === 'center-right' ? ' active' : ''; ?>"><?php echo wp_kses( MEYVC_Icons::svg( 'chevron-right', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></button>

			<button type="button" data-position="bottom-left" class="meyvc-position-btn<?php echo $position === 'bottom-left' ? ' active' : ''; ?>">↙</button>
			<button type="button" data-position="bottom-center" class="meyvc-position-btn<?php echo $position === 'bottom-center' ? ' active' : ''; ?>"><?php echo wp_kses( MEYVC_Icons::svg( 'chevron-down', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></button>

			<button type="button" data-position="bottom-right" class="meyvc-position-btn<?php echo $position === 'bottom-right' ? ' active' : ''; ?>">↘</button>
		</div>
		<input type="hidden" id="design-position" value="<?php echo esc_attr( $position ); ?>" />
	</div>

	<!-- Custom CSS -->
	<div class="meyvc-control-group">
		<label><?php esc_html_e( 'Custom CSS', 'meyvora-convert' ); ?></label>
		<textarea id="design-custom-css"
				  rows="5"
				  placeholder="<?php esc_attr_e( '.meyvc-popup { /* your styles */ }', 'meyvora-convert' ); ?>"
		><?php echo esc_textarea( $styling['custom_css'] ?? '' ); ?></textarea>
	</div>

	<div class="meyvc-design-section" id="meyvc-wheel-config-section" style="display:none;">
		<h4><?php esc_html_e( 'Wheel slices', 'meyvora-convert' ); ?></h4>
		<p class="meyvc-hint"><?php esc_html_e( 'Configure each segment. "Win" slices give the visitor a coupon.', 'meyvora-convert' ); ?></p>
		<table class="widefat" id="meyvc-wheel-slices-table" style="max-width:640px;">
			<thead><tr>
				<th style="width:24px;"></th>
				<th><?php esc_html_e( 'Label', 'meyvora-convert' ); ?></th>
				<th><?php esc_html_e( 'Type', 'meyvora-convert' ); ?></th>
				<th><?php esc_html_e( 'Colour', 'meyvora-convert' ); ?></th>
				<th style="width:40px;"></th>
			</tr></thead>
			<tbody id="meyvc-wheel-slices-body"></tbody>
		</table>
		<p><button type="button" class="button" id="meyvc-wheel-add-slice"><?php esc_html_e( '+ Add slice', 'meyvora-convert' ); ?></button></p>
		<input type="hidden" name="wheel_slices" id="meyvc-wheel-slices-json" value="<?php echo esc_attr( $wheel_slices_json ); ?>" />
	</div>

</div>
