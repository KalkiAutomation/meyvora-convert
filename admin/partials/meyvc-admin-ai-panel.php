<?php
/**
 * Collapsible AI Recommendations sidebar (Dashboard, Campaigns, Offers).
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template/view: variables are file-local.
$insights_url  = admin_url( 'admin.php?page=meyvc-insights' );
$offers_url    = admin_url( 'admin.php?page=meyvc-offers' );
$campaign_new  = admin_url( 'admin.php?page=meyvc-campaign-edit' );
$campaigns_url = admin_url( 'admin.php?page=meyvc-campaigns' );
$settings_ai    = admin_url( 'admin.php?page=meyvc-settings&settings_tab=ai' );
?>
<aside class="meyvc-ai-panel" id="meyvc-ai-panel" aria-label="<?php esc_attr_e( 'AI recommendations', 'meyvora-convert' ); ?>">
	<button type="button" class="meyvc-ai-panel__toggle" id="meyvc-ai-panel-toggle" aria-expanded="false">
		<span class="meyvc-ai-panel__toggle-dot" id="meyvc-ai-panel-status-dot"></span>
		<span class="meyvc-ai-panel__toggle-text"><?php esc_html_e( 'AI', 'meyvora-convert' ); ?></span>
	</button>
	<div class="meyvc-ai-panel__drawer" id="meyvc-ai-panel-drawer" hidden>
		<div class="meyvc-ai-panel__head">
			<h3 class="meyvc-ai-panel__title"><?php esc_html_e( 'AI recommendations', 'meyvora-convert' ); ?></h3>
			<p class="meyvc-ai-panel__status-msg" id="meyvc-ai-panel-config-msg"><?php esc_html_e( 'Loading…', 'meyvora-convert' ); ?></p>
		</div>

		<div class="meyvc-ai-panel__card" id="meyvc-ai-panel-top-card">
			<p class="meyvc-ai-panel__muted"><?php esc_html_e( 'Top insight loads after page render…', 'meyvora-convert' ); ?></p>
		</div>

		<div class="meyvc-ai-panel__actions">
			<button type="button" class="button button-small" id="meyvc-ai-panel-suggest-offer"><?php esc_html_e( 'Suggest next best offer', 'meyvora-convert' ); ?></button>
			<button type="button" class="button button-small" id="meyvc-ai-panel-run-analysis"><?php esc_html_e( 'Run AI analysis', 'meyvora-convert' ); ?></button>
			<a href="<?php echo esc_url( $insights_url ); ?>" class="button button-small"><?php esc_html_e( 'Open Insights tab', 'meyvora-convert' ); ?></a>
		</div>

		<div class="meyvc-ai-panel__copy">
			<label class="meyvc-ai-panel__label" for="meyvc-ai-panel-copy-goal"><?php esc_html_e( 'Describe your popup goal', 'meyvora-convert' ); ?></label>
			<input type="text" class="widefat" id="meyvc-ai-panel-copy-goal" placeholder="<?php esc_attr_e( 'e.g. Exit discount for first-time visitors', 'meyvora-convert' ); ?>" />
			<button type="button" class="button button-primary button-small" id="meyvc-ai-panel-generate-copy"><?php esc_html_e( 'Generate copy', 'meyvora-convert' ); ?></button>
			<div id="meyvc-ai-panel-copy-result" class="meyvc-ai-panel__copy-result" hidden></div>
		</div>

		<p class="meyvc-ai-panel__usage" id="meyvc-ai-panel-usage"></p>
	</div>
</aside>

<div id="meyvc-ai-panel-modals" class="meyvc-ai-panel-modals"></div>
