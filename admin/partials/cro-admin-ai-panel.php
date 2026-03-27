<?php
/**
 * Collapsible AI Recommendations sidebar (Dashboard, Campaigns, Offers).
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$insights_url  = admin_url( 'admin.php?page=cro-insights' );
$offers_url    = admin_url( 'admin.php?page=cro-offers' );
$campaign_new  = admin_url( 'admin.php?page=cro-campaign-edit' );
$campaigns_url = admin_url( 'admin.php?page=cro-campaigns' );
$settings_ai    = admin_url( 'admin.php?page=cro-settings&settings_tab=ai' );
?>
<aside class="cro-ai-panel" id="cro-ai-panel" aria-label="<?php esc_attr_e( 'AI recommendations', 'meyvora-convert' ); ?>">
	<button type="button" class="cro-ai-panel__toggle" id="cro-ai-panel-toggle" aria-expanded="false">
		<span class="cro-ai-panel__toggle-dot" id="cro-ai-panel-status-dot"></span>
		<span class="cro-ai-panel__toggle-text"><?php esc_html_e( 'AI', 'meyvora-convert' ); ?></span>
	</button>
	<div class="cro-ai-panel__drawer" id="cro-ai-panel-drawer" hidden>
		<div class="cro-ai-panel__head">
			<h3 class="cro-ai-panel__title"><?php esc_html_e( 'AI recommendations', 'meyvora-convert' ); ?></h3>
			<p class="cro-ai-panel__status-msg" id="cro-ai-panel-config-msg"><?php esc_html_e( 'Loading…', 'meyvora-convert' ); ?></p>
		</div>

		<div class="cro-ai-panel__card" id="cro-ai-panel-top-card">
			<p class="cro-ai-panel__muted"><?php esc_html_e( 'Top insight loads after page render…', 'meyvora-convert' ); ?></p>
		</div>

		<div class="cro-ai-panel__actions">
			<button type="button" class="button button-small" id="cro-ai-panel-suggest-offer"><?php esc_html_e( 'Suggest next best offer', 'meyvora-convert' ); ?></button>
			<button type="button" class="button button-small" id="cro-ai-panel-run-analysis"><?php esc_html_e( 'Run AI analysis', 'meyvora-convert' ); ?></button>
			<a href="<?php echo esc_url( $insights_url ); ?>" class="button button-small"><?php esc_html_e( 'Open Insights tab', 'meyvora-convert' ); ?></a>
		</div>

		<div class="cro-ai-panel__copy">
			<label class="cro-ai-panel__label" for="cro-ai-panel-copy-goal"><?php esc_html_e( 'Describe your popup goal', 'meyvora-convert' ); ?></label>
			<input type="text" class="widefat" id="cro-ai-panel-copy-goal" placeholder="<?php esc_attr_e( 'e.g. Exit discount for first-time visitors', 'meyvora-convert' ); ?>" />
			<button type="button" class="button button-primary button-small" id="cro-ai-panel-generate-copy"><?php esc_html_e( 'Generate copy', 'meyvora-convert' ); ?></button>
			<div id="cro-ai-panel-copy-result" class="cro-ai-panel__copy-result" hidden></div>
		</div>

		<p class="cro-ai-panel__usage" id="cro-ai-panel-usage"></p>
	</div>
</aside>

<div id="cro-ai-panel-modals" class="cro-ai-panel-modals"></div>
