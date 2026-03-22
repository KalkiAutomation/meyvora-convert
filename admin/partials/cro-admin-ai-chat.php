<?php
/**
 * Floating AI chat shell (Meyvora Convert admin).
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="cro-aichat-launcher" class="cro-aichat-launcher" role="button" tabindex="0" aria-expanded="false" aria-controls="cro-aichat-panel">
	<?php esc_html_e( '✦ Ask AI', 'meyvora-convert' ); ?>
</div>

<div id="cro-aichat-panel" class="cro-aichat-panel" style="display:none" role="dialog" aria-modal="false" aria-labelledby="cro-aichat-panel-title" aria-hidden="true">
	<div class="cro-aichat-header">
		<span id="cro-aichat-panel-title"><?php esc_html_e( 'Meyvora AI', 'meyvora-convert' ); ?></span>
		<button type="button" id="cro-aichat-close" class="cro-aichat-close" aria-label="<?php esc_attr_e( 'Close', 'meyvora-convert' ); ?>">✕</button>
	</div>
	<div id="cro-aichat-messages" class="cro-aichat-messages">
		<div id="cro-aichat-starters" class="cro-aichat-starters">
			<button type="button" class="cro-aichat-pill"><?php esc_html_e( 'Which campaign is performing best?', 'meyvora-convert' ); ?></button>
			<button type="button" class="cro-aichat-pill"><?php esc_html_e( 'What is my cart recovery rate?', 'meyvora-convert' ); ?></button>
			<button type="button" class="cro-aichat-pill"><?php esc_html_e( 'Which offer drives the most revenue?', 'meyvora-convert' ); ?></button>
			<button type="button" class="cro-aichat-pill"><?php esc_html_e( 'Where should I focus to improve conversions?', 'meyvora-convert' ); ?></button>
		</div>
	</div>
	<div class="cro-aichat-input-row">
		<label for="cro-aichat-input" class="screen-reader-text"><?php esc_html_e( 'Message', 'meyvora-convert' ); ?></label>
		<input type="text" id="cro-aichat-input" class="cro-aichat-input" placeholder="<?php esc_attr_e( 'Ask anything about your store…', 'meyvora-convert' ); ?>" autocomplete="off" />
		<button type="button" id="cro-aichat-send" class="button button-primary"><?php esc_html_e( 'Send', 'meyvora-convert' ); ?></button>
	</div>
	<div class="cro-aichat-footer"><?php esc_html_e( 'Answers based on your last 30 days data', 'meyvora-convert' ); ?></div>
</div>
