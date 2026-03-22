<?php
/**
 * Nav bar action: toggles the Meyvora AI chat panel (hooked from CRO_Admin_UI::render_tabs via cro_admin_nav_actions).
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<button type="button" id="cro-aichat-nav-toggle" class="button cro-aichat-nav-toggle" aria-expanded="false" aria-controls="cro-aichat-panel">
	<?php esc_html_e( '✦ Ask AI', 'meyvora-convert' ); ?>
</button>
