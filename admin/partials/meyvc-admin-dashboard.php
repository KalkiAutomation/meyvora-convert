<?php
/**
 * Admin dashboard - main overview page (modern layout).
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template/view: variables are file-local.
$settings = function_exists( 'meyvc_settings' ) ? meyvc_settings() : null;

require_once MEYVC_PLUGIN_DIR . 'admin/partials/meyvc-admin-ai-panel.php';

// Extended metrics (30d / 90d).
$recovered_revenue_30d   = class_exists( 'MEYVC_Dashboard_Metrics' ) ? MEYVC_Dashboard_Metrics::get_recovered_revenue_30d() : 0.0;
$emails_captured_30d     = class_exists( 'MEYVC_Dashboard_Metrics' ) ? MEYVC_Dashboard_Metrics::get_emails_captured_30d() : 0;
$offer_conversions_30d   = class_exists( 'MEYVC_Dashboard_Metrics' ) ? MEYVC_Dashboard_Metrics::get_offer_conversions_30d() : 0;
$store_avg_order_90d     = class_exists( 'MEYVC_Dashboard_Metrics' ) ? MEYVC_Dashboard_Metrics::get_avg_order_value_90d() : 0.0;
$projected_annual_uplift = ( $recovered_revenue_30d * 12 ) + ( $offer_conversions_30d * $store_avg_order_90d );
$projected_tooltip       = __( 'Estimated annual uplift uses (recovered cart revenue from the last 30 days × 12) plus (offer conversions in the last 30 days × your store’s average order value from completed/processing orders in the last 90 days). This is a rough projection, not a guarantee.', 'meyvora-convert' );
$recent_wins             = class_exists( 'MEYVC_Dashboard_Metrics' ) ? MEYVC_Dashboard_Metrics::get_recent_wins( 5 ) : array();

// KPI: Active offers
$active_offers_count = 0;
if ( class_exists( 'MEYVC_Offer_Model' ) && method_exists( 'MEYVC_Offer_Model', 'get_active' ) ) {
	$active_offers_count = count( MEYVC_Offer_Model::get_active() );
}

// KPI: Active A/B tests (status = running)
$active_ab_tests_count = 0;
global $wpdb;
$ab_tests_table = $wpdb->prefix . 'meyvc_ab_tests';
if ( class_exists( 'MEYVC_Database' ) && MEYVC_Database::table_exists( $ab_tests_table ) ) {
	$cache_key_ab = 'meyvora_meyvc_' . md5( serialize( array( 'admin_dashboard_ab_tests_running_count', $ab_tests_table, 'running' ) ) );
	$cached_count = wp_cache_get( $cache_key_ab, 'meyvora_meyvc' );
	if ( false === $cached_count ) {
		$active_ab_tests_count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $ab_tests_table, 'running' )
		);
		wp_cache_set( $cache_key_ab, $active_ab_tests_count, 'meyvora_meyvc', 300 );
	} else {
		$active_ab_tests_count = (int) $cached_count;
	}
}

// KPI: Abandoned carts (active, not recovered)
$abandoned_carts_active = 0;
if ( class_exists( 'MEYVC_Abandoned_Cart_Tracker' ) && method_exists( 'MEYVC_Abandoned_Cart_Tracker', 'get_list' ) ) {
	$list = MEYVC_Abandoned_Cart_Tracker::get_list( array( 'status_filter' => 'active', 'per_page' => 1, 'page' => 1 ) );
	$abandoned_carts_active = isset( $list['total'] ) ? (int) $list['total'] : 0;
}

// KPI: Revenue influenced (last 30 days)
$revenue_influenced = 0;
$revenue_available = false;
if ( class_exists( 'MEYVC_Analytics' ) && method_exists( 'MEYVC_Analytics', 'get_overview_stats' ) ) {
	$stats = MEYVC_Analytics::get_overview_stats( 30 );
	$revenue_influenced = isset( $stats['revenue_attributed'] ) ? (float) $stats['revenue_attributed'] : 0;
	$revenue_available = true;
}

// Recent activity (last 10 events)
$recent_events = array();
if ( class_exists( 'MEYVC_Analytics' ) && method_exists( 'MEYVC_Analytics', 'get_recent_events' ) ) {
	$recent_events = MEYVC_Analytics::get_recent_events( 10 );
}

$onboarding_done   = MEYVC_Security::get_query_var( 'onboarding_done' ) === '1';
$quick_launch_done = MEYVC_Security::get_query_var( 'meyvc_quick_launch_done' ) === '1';
?>

<?php if ( $onboarding_done ) : ?>
	<div class="meyvc-ui-notice meyvc-ui-toast-placeholder" role="status">
		<p><?php esc_html_e( 'Setup complete! Your conversion tools are ready.', 'meyvora-convert' ); ?></p>
	</div>
<?php endif; ?>
<?php if ( $quick_launch_done ) : ?>
	<div class="meyvc-ui-notice meyvc-ui-toast-placeholder" role="status">
		<p>
			<?php esc_html_e( 'Recommended CRO setup applied.', 'meyvora-convert' ); ?>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview your store', 'meyvora-convert' ); ?></a>
		</p>
	</div>
<?php endif; ?>

<div class="meyvc-dashboard-modern">
	<!-- KPI cards row -->
	<div class="meyvc-dashboard-kpi-row">
		<div class="meyvc-dashboard-kpi-card">
			<span class="meyvc-dashboard-kpi-card__icon"><?php echo wp_kses( MEYVC_Icons::svg( 'tag', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

			<div class="meyvc-dashboard-kpi-card__content">
				<span class="meyvc-dashboard-kpi-card__value"><?php echo esc_html( number_format_i18n( $active_offers_count ) ); ?></span>
				<span class="meyvc-dashboard-kpi-card__label"><?php esc_html_e( 'Active offers', 'meyvora-convert' ); ?></span>
			</div>
		</div>
		<div class="meyvc-dashboard-kpi-card">
			<span class="meyvc-dashboard-kpi-card__icon"><?php echo wp_kses( MEYVC_Icons::svg( 'target', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

			<div class="meyvc-dashboard-kpi-card__content">
				<span class="meyvc-dashboard-kpi-card__value"><?php echo esc_html( number_format_i18n( $active_ab_tests_count ) ); ?></span>
				<span class="meyvc-dashboard-kpi-card__label"><?php esc_html_e( 'Active A/B tests', 'meyvora-convert' ); ?></span>
			</div>
		</div>
		<div class="meyvc-dashboard-kpi-card">
			<span class="meyvc-dashboard-kpi-card__icon"><?php echo wp_kses( MEYVC_Icons::svg( 'shopping-cart', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

			<div class="meyvc-dashboard-kpi-card__content">
				<span class="meyvc-dashboard-kpi-card__value"><?php echo esc_html( number_format_i18n( $abandoned_carts_active ) ); ?></span>
				<span class="meyvc-dashboard-kpi-card__label"><?php esc_html_e( 'Abandoned carts (active)', 'meyvora-convert' ); ?></span>
			</div>
		</div>
		<?php if ( $revenue_available ) : ?>
		<div class="meyvc-dashboard-kpi-card">
			<span class="meyvc-dashboard-kpi-card__icon"><?php echo wp_kses( MEYVC_Icons::svg( 'dollar-sign', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

			<div class="meyvc-dashboard-kpi-card__content">
				<span class="meyvc-dashboard-kpi-card__value"><?php echo function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $revenue_influenced ) ) : esc_html( number_format_i18n( $revenue_influenced, 2 ) ); ?></span>
				<span class="meyvc-dashboard-kpi-card__label"><?php esc_html_e( 'Revenue influenced (30d)', 'meyvora-convert' ); ?></span>
			</div>
		</div>
		<?php endif; ?>
	</div>

	<div class="meyvc-card meyvc-live-panel" id="meyvc-live-panel">
		<header class="meyvc-card__header meyvc-live-panel__header">
			<span class="meyvc-live-panel__pulse" aria-hidden="true">
				<span class="meyvc-live-dot" id="meyvc-live-dot"></span>
			</span>
			<h2 class="meyvc-card__title meyvc-live-panel__heading"><?php esc_html_e( 'Live — last 60 minutes', 'meyvora-convert' ); ?></h2>
			<span class="meyvc-live-panel__updated" id="meyvc-live-updated"></span>
		</header>
		<div class="meyvc-card__body meyvc-live-panel__body">
			<div class="meyvc-live-stats" id="meyvc-live-stats">
				<div class="meyvc-live-stat">
					<span class="meyvc-live-val" id="meyvc-live-impressions">—</span>
					<span class="meyvc-live-lbl"><?php esc_html_e( 'Impressions', 'meyvora-convert' ); ?></span>
				</div>
				<div class="meyvc-live-stat">
					<span class="meyvc-live-val" id="meyvc-live-conversions">—</span>
					<span class="meyvc-live-lbl"><?php esc_html_e( 'Conversions', 'meyvora-convert' ); ?></span>
				</div>
				<div class="meyvc-live-stat">
					<span class="meyvc-live-val" id="meyvc-live-emails">—</span>
					<span class="meyvc-live-lbl"><?php esc_html_e( 'Emails captured', 'meyvora-convert' ); ?></span>
				</div>
				<div class="meyvc-live-stat">
					<span class="meyvc-live-val" id="meyvc-live-carts">—</span>
					<span class="meyvc-live-lbl"><?php esc_html_e( 'Carts recovered', 'meyvora-convert' ); ?></span>
				</div>
			</div>
		</div>
	</div>

	<div class="meyvc-dashboard-kpi-row meyvc-dashboard-kpi-row--secondary">
		<div class="meyvc-dashboard-kpi-card">
			<div class="meyvc-dashboard-kpi-card__content">
				<span class="meyvc-dashboard-kpi-card__value"><?php echo function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $recovered_revenue_30d ) ) : esc_html( number_format_i18n( $recovered_revenue_30d, 2 ) ); ?></span>
				<span class="meyvc-dashboard-kpi-card__label"><?php esc_html_e( 'Recovered revenue (30d)', 'meyvora-convert' ); ?></span>
			</div>
		</div>
		<div class="meyvc-dashboard-kpi-card">
			<div class="meyvc-dashboard-kpi-card__content">
				<span class="meyvc-dashboard-kpi-card__value"><?php echo esc_html( number_format_i18n( $emails_captured_30d ) ); ?></span>
				<span class="meyvc-dashboard-kpi-card__label"><?php esc_html_e( 'Emails captured (30d)', 'meyvora-convert' ); ?></span>
			</div>
		</div>
		<div class="meyvc-dashboard-kpi-card">
			<div class="meyvc-dashboard-kpi-card__content">
				<span class="meyvc-dashboard-kpi-card__value"><?php echo esc_html( number_format_i18n( $offer_conversions_30d ) ); ?></span>
				<span class="meyvc-dashboard-kpi-card__label"><?php esc_html_e( 'Offer conversions (30d)', 'meyvora-convert' ); ?></span>
			</div>
		</div>
		<div class="meyvc-dashboard-kpi-card">
			<div class="meyvc-dashboard-kpi-card__content">
				<span class="meyvc-dashboard-kpi-card__value"><?php echo function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $projected_annual_uplift ) ) : esc_html( number_format_i18n( $projected_annual_uplift, 2 ) ); ?></span>
				<span class="meyvc-dashboard-kpi-card__label">
					<?php esc_html_e( 'Estimated annual uplift', 'meyvora-convert' ); ?>
					<span class="dashicons dashicons-info-outline meyvc-dashboard-kpi-card__tip" title="<?php echo esc_attr( $projected_tooltip ); ?>"></span>
				</span>
			</div>
		</div>
	</div>

	<!-- Quick actions -->
	<div class="meyvc-dashboard-actions">
		<h3 class="meyvc-dashboard-actions__title"><?php esc_html_e( 'Quick actions', 'meyvora-convert' ); ?></h3>
		<div class="meyvc-dashboard-actions__list meyvc-dashboard-actions__list--scroll">
			<button type="button" class="meyvc-dashboard-action-btn button" id="meyvc-dash-run-ai-analysis"><?php esc_html_e( 'Run AI analysis', 'meyvora-convert' ); ?></button>
			<button type="button" class="meyvc-dashboard-action-btn button" id="meyvc-dash-suggest-offer"><?php esc_html_e( 'Suggest new offer', 'meyvora-convert' ); ?></button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=meyvc-campaign-edit' ) ); ?>" class="meyvc-dashboard-action-btn button button-primary"><?php esc_html_e( 'Create campaign', 'meyvora-convert' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=meyvc-abandoned-carts' ) ); ?>" class="meyvc-dashboard-action-btn button"><?php esc_html_e( 'View abandoned carts', 'meyvora-convert' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=meyvc-offers' ) ); ?>" class="meyvc-dashboard-action-btn button"><?php esc_html_e( 'Add offer', 'meyvora-convert' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=meyvc-ab-test-new' ) ); ?>" class="meyvc-dashboard-action-btn button"><?php esc_html_e( 'Create A/B test', 'meyvora-convert' ); ?></a>
		</div>
	</div>

	<div class="meyvc-dashboard-card meyvc-dashboard-wins">
		<header class="meyvc-dashboard-card__header">
			<h3 class="meyvc-dashboard-card__title"><?php esc_html_e( 'Recent wins', 'meyvora-convert' ); ?></h3>
		</header>
		<div class="meyvc-dashboard-card__body">
			<?php if ( empty( $recent_wins ) ) : ?>
				<p class="meyvc-dashboard-activity-empty"><?php esc_html_e( 'Recoveries and offer conversions will appear here.', 'meyvora-convert' ); ?></p>
			<?php else : ?>
				<ul class="meyvc-dashboard-activity-list">
					<?php foreach ( $recent_wins as $win ) : ?>
						<li class="meyvc-dashboard-activity-item">
							<span class="meyvc-dashboard-activity-item__label">
								<?php echo esc_html( 'recovered' === ( $win['type'] ?? '' ) ? '🛒 ' . ( $win['label'] ?? '' ) : '🏷️ ' . ( $win['label'] ?? '' ) ); ?>
								<?php if ( ! empty( $win['amount'] ) ) : ?>
									<strong><?php echo esc_html( $win['amount'] ); ?></strong>
								<?php endif; ?>
							</span>
							<time class="meyvc-dashboard-activity-item__time"><?php echo esc_html( $win['time'] ?? '' ); ?></time>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>

	<!-- Recent activity -->
	<div class="meyvc-dashboard-card meyvc-dashboard-activity">
		<header class="meyvc-dashboard-card__header">
			<span class="meyvc-dashboard-card__icon"><?php echo wp_kses( MEYVC_Icons::svg( 'chart', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

			<h3 class="meyvc-dashboard-card__title"><?php esc_html_e( 'Recent activity', 'meyvora-convert' ); ?></h3>
		</header>
		<div class="meyvc-dashboard-card__body">
			<?php if ( empty( $recent_events ) ) : ?>
				<div class="meyvc-dashboard-activity-empty">
					<p><?php esc_html_e( 'No recent events yet. Events appear here when campaigns are shown or visitors convert.', 'meyvora-convert' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=meyvc-campaigns' ) ); ?>" class="button"><?php esc_html_e( 'View campaigns', 'meyvora-convert' ); ?></a>
				</div>
			<?php else : ?>
				<ul class="meyvc-dashboard-activity-list">
					<?php foreach ( $recent_events as $event ) : ?>
						<?php
						$created = isset( $event['created_at'] ) ? $event['created_at'] : '';
						$event_type = isset( $event['event_type'] ) ? $event['event_type'] : 'interaction';
						$campaign_name = isset( $event['campaign_name'] ) ? $event['campaign_name'] : '';
						$source_type = isset( $event['source_type'] ) ? $event['source_type'] : '';
						$revenue = isset( $event['revenue'] ) ? (float) $event['revenue'] : null;
						$label = $campaign_name ? $campaign_name : ( $source_type ? ucfirst( str_replace( '_', ' ', $source_type ) ) : __( 'Event', 'meyvora-convert' ) );
						if ( $event_type === 'impression' ) {
							$action_text = __( 'View', 'meyvora-convert' );
						} elseif ( $event_type === 'conversion' ) {
							$action_text = $revenue > 0 ? sprintf( /* translators: %s is the formatted revenue amount for the conversion. */ __( 'Conversion (+%s)', 'meyvora-convert' ), function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $revenue ) ) : number_format_i18n( $revenue, 2 ) ) : __( 'Conversion', 'meyvora-convert' );
						} elseif ( $event_type === 'dismiss' ) {
							$action_text = __( 'Dismissed', 'meyvora-convert' );
						} else {
							$action_text = ucfirst( $event_type );
						}
						$time_ago = $created ? human_time_diff( strtotime( $created ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'meyvora-convert' ) : '';
						?>
						<li class="meyvc-dashboard-activity-item">
							<span class="meyvc-dashboard-activity-item__label"><?php echo esc_html( $label ); ?></span>
							<span class="meyvc-dashboard-activity-item__action"><?php echo esc_html( $action_text ); ?></span>
							<time class="meyvc-dashboard-activity-item__time" datetime="<?php echo esc_attr( $created ); ?>"><?php echo esc_html( $time_ago ); ?></time>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>

	<!-- Quick launch (when few features active) -->
	<?php
	$active_features = array(
		'campaigns'          => $settings && method_exists( $settings, 'is_feature_enabled' ) ? $settings->is_feature_enabled( 'campaigns' ) : true,
		'sticky_cart'        => $settings && method_exists( $settings, 'is_feature_enabled' ) ? $settings->is_feature_enabled( 'sticky_cart' ) : false,
		'shipping_bar'       => $settings && method_exists( $settings, 'is_feature_enabled' ) ? $settings->is_feature_enabled( 'shipping_bar' ) : false,
		'cart_optimizer'     => $settings && method_exists( $settings, 'is_feature_enabled' ) ? $settings->is_feature_enabled( 'cart_optimizer' ) : false,
		'checkout_optimizer' => $settings && method_exists( $settings, 'is_feature_enabled' ) ? $settings->is_feature_enabled( 'checkout_optimizer' ) : false,
	);
	$active_count = count( array_filter( $active_features ) );
	?>
	<?php if ( $active_count < 3 && ! $quick_launch_done ) : ?>
		<div class="meyvc-dashboard-card meyvc-dashboard-cta">
			<div class="meyvc-dashboard-card__body">
				<p><?php esc_html_e( 'Launch the recommended CRO setup in one click: shipping bar, checkout trust, and a sample campaign.', 'meyvora-convert' ); ?></p>
				<form method="post" class="meyvc-inline-form">
					<?php wp_nonce_field( 'meyvc_quick_launch', 'meyvc_quick_launch_nonce' ); ?>
					<input type="hidden" name="meyvc_quick_launch" value="recommended" />
					<button type="submit" class="button button-primary meyvc-ui-btn-primary">
						<?php echo wp_kses( MEYVC_Icons::svg( 'zap', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?>

						<?php esc_html_e( 'Launch recommended CRO setup', 'meyvora-convert' ); ?>
					</button>
				</form>
			</div>
		</div>
	<?php endif; ?>
</div>
