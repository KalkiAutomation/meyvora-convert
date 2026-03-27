<?php
/**
 * Trust badges template
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
?>

<div class="cro-trust-badges">
	<div class="cro-trust-badge">
		<span class="cro-trust-badge-icon"><?php echo wp_kses( CRO_Icons::svg( 'lock', array( 'class' => 'cro-ico' ) ), CRO_Icons::get_svg_kses_allowed() ); ?></span>

		<span class="cro-trust-badge-text"><?php esc_html_e( 'Secure Checkout', 'meyvora-convert' ); ?></span>
	</div>
	<div class="cro-trust-badge">
		<span class="cro-trust-badge-icon"><?php echo wp_kses( CRO_Icons::svg( 'truck', array( 'class' => 'cro-ico' ) ), CRO_Icons::get_svg_kses_allowed() ); ?></span>

		<span class="cro-trust-badge-text"><?php esc_html_e( 'Free Shipping', 'meyvora-convert' ); ?></span>
	</div>
	<div class="cro-trust-badge">
		<span class="cro-trust-badge-icon"><?php echo wp_kses( CRO_Icons::svg( 'undo', array( 'class' => 'cro-ico' ) ), CRO_Icons::get_svg_kses_allowed() ); ?></span>

		<span class="cro-trust-badge-text"><?php esc_html_e( 'Easy Returns', 'meyvora-convert' ); ?></span>
	</div>
</div>
