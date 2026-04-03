<?php
/**
 * Floating AI chat shell (Meyvora Convert admin).
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template/view: variables are file-local.
?>
<div id="meyvc-aichat-launcher" class="meyvc-aichat-launcher" role="button" tabindex="0" aria-expanded="false" aria-controls="meyvc-aichat-panel">
	<?php esc_html_e( '✦ Ask AI', 'meyvora-convert' ); ?>
</div>

<div id="meyvc-aichat-panel" class="meyvc-aichat-panel" style="display:none" role="dialog" aria-modal="false" aria-labelledby="meyvc-aichat-panel-title" aria-hidden="true">
	<div class="meyvc-aichat-header">
		<span id="meyvc-aichat-panel-title"><?php esc_html_e( 'Meyvora AI', 'meyvora-convert' ); ?></span>
		<button type="button" id="meyvc-aichat-close" class="meyvc-aichat-close" aria-label="<?php esc_attr_e( 'Close', 'meyvora-convert' ); ?>">✕</button>
	</div>
	<div id="meyvc-aichat-messages" class="meyvc-aichat-messages">
		<div id="meyvc-aichat-starters" class="meyvc-aichat-starters">
			<button type="button" class="meyvc-aichat-pill"><?php esc_html_e( 'Which campaign is performing best?', 'meyvora-convert' ); ?></button>
			<button type="button" class="meyvc-aichat-pill"><?php esc_html_e( 'What is my cart recovery rate?', 'meyvora-convert' ); ?></button>
			<button type="button" class="meyvc-aichat-pill"><?php esc_html_e( 'Which offer drives the most revenue?', 'meyvora-convert' ); ?></button>
			<button type="button" class="meyvc-aichat-pill"><?php esc_html_e( 'Where should I focus to improve conversions?', 'meyvora-convert' ); ?></button>
		</div>
	</div>
	<div class="meyvc-aichat-input-row">
		<label for="meyvc-aichat-input" class="screen-reader-text"><?php esc_html_e( 'Message', 'meyvora-convert' ); ?></label>
		<input type="text" id="meyvc-aichat-input" class="meyvc-aichat-input" placeholder="<?php esc_attr_e( 'Ask anything about your store…', 'meyvora-convert' ); ?>" autocomplete="off" />
		<button type="button" id="meyvc-aichat-send" class="button button-primary"><?php esc_html_e( 'Send', 'meyvora-convert' ); ?></button>
	</div>
	<div class="meyvc-aichat-footer"><?php esc_html_e( 'Answers based on your last 30 days data', 'meyvora-convert' ); ?></div>
</div>
