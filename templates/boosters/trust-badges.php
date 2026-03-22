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
		<span class="cro-trust-badge-icon"><?php echo CRO_Icons::svg_kses( 'lock', array( 'class' => 'cro-ico' ) ); ?></span>

		<span class="cro-trust-badge-text"><?php esc_html_e( 'Secure Checkout', 'meyvora-convert' ); ?></span>
	</div>
	<div class="cro-trust-badge">
		<span class="cro-trust-badge-icon"><?php echo CRO_Icons::svg_kses( 'truck', array( 'class' => 'cro-ico' ) ); ?></span>

		<span class="cro-trust-badge-text"><?php esc_html_e( 'Free Shipping', 'meyvora-convert' ); ?></span>
	</div>
	<div class="cro-trust-badge">
		<span class="cro-trust-badge-icon"><?php echo CRO_Icons::svg_kses( 'undo', array( 'class' => 'cro-ico' ) ); ?></span>

		<span class="cro-trust-badge-text"><?php esc_html_e( 'Easy Returns', 'meyvora-convert' ); ?></span>
	</div>
</div>
