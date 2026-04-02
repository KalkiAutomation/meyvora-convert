<?php
/**
 * Trust badges template
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="meyvc-trust-badges">
	<div class="meyvc-trust-badge">
		<span class="meyvc-trust-badge-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'lock', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

		<span class="meyvc-trust-badge-text"><?php esc_html_e( 'Secure Checkout', 'meyvora-convert' ); ?></span>
	</div>
	<div class="meyvc-trust-badge">
		<span class="meyvc-trust-badge-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'truck', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

		<span class="meyvc-trust-badge-text"><?php esc_html_e( 'Free Shipping', 'meyvora-convert' ); ?></span>
	</div>
	<div class="meyvc-trust-badge">
		<span class="meyvc-trust-badge-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'undo', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

		<span class="meyvc-trust-badge-text"><?php esc_html_e( 'Easy Returns', 'meyvora-convert' ); ?></span>
	</div>
</div>
