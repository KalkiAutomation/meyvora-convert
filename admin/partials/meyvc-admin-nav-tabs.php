<?php
/**
 * Nav bar action: toggles the Meyvora AI chat panel (hooked from MEYVC_Admin_UI::render_tabs via meyvc_admin_nav_actions).
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template/view: variables are file-local.
?>
<button type="button" id="meyvc-aichat-nav-toggle" class="button meyvc-aichat-nav-toggle" aria-expanded="false" aria-controls="meyvc-aichat-panel">
	<?php esc_html_e( '✦ Ask AI', 'meyvora-convert' ); ?>
</button>
