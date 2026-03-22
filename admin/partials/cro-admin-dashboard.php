<?php
/**
 * Admin dashboard - main overview page (modern layout).
 *
 * @package Meyvora_Convert
 */
// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

if ( ! defined( 'WPINC' ) ) {
	die;
}

$settings = function_exists( 'cro_settings' ) ? cro_settings() : null;

// KPI: Active offers
$active_offers_count = 0;
if ( class_exists( 'CRO_Offer_Model' ) && method_exists( 'CRO_Offer_Model', 'get_active' ) ) {
	$active_offers_count = count( CRO_Offer_Model::get_active() );
}

// KPI: Active A/B tests (status = running)
$active_ab_tests_count = 0;
global $wpdb;
$ab_tests_table = $wpdb->prefix . 'cro_ab_tests';
if ( class_exists( 'CRO_Database' ) && CRO_Database::table_exists( $ab_tests_table ) ) {
	$active_ab_tests_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$ab_tests_table} WHERE status = 'running'" );
}

// KPI: Abandoned carts (active, not recovered)
$abandoned_carts_active = 0;
if ( class_exists( 'CRO_Abandoned_Cart_Tracker' ) && method_exists( 'CRO_Abandoned_Cart_Tracker', 'get_list' ) ) {
	$list = CRO_Abandoned_Cart_Tracker::get_list( array( 'status_filter' => 'active', 'per_page' => 1, 'page' => 1 ) );
	$abandoned_carts_active = isset( $list['total'] ) ? (int) $list['total'] : 0;
}

// KPI: Revenue influenced (last 30 days)
$revenue_influenced = 0;
$revenue_available = false;
if ( class_exists( 'CRO_Analytics' ) && method_exists( 'CRO_Analytics', 'get_overview_stats' ) ) {
	$stats = CRO_Analytics::get_overview_stats( 30 );
	$revenue_influenced = isset( $stats['revenue_attributed'] ) ? (float) $stats['revenue_attributed'] : 0;
	$revenue_available = true;
}

// Recent activity (last 10 events)
$recent_events = array();
if ( class_exists( 'CRO_Analytics' ) && method_exists( 'CRO_Analytics', 'get_recent_events' ) ) {
	$recent_events = CRO_Analytics::get_recent_events( 10 );
}

$onboarding_done = isset( $_GET['onboarding_done'] ) && (string) $_GET['onboarding_done'] === '1';
$quick_launch_done = isset( $_GET['cro_quick_launch_done'] ) && (string) $_GET['cro_quick_launch_done'] === '1';
?>

<?php if ( $onboarding_done ) : ?>
	<div class="cro-ui-notice cro-ui-toast-placeholder" role="status">
		<p><?php esc_html_e( 'Setup complete! Your conversion tools are ready.', 'meyvora-convert' ); ?></p>
	</div>
<?php endif; ?>
<?php if ( $quick_launch_done ) : ?>
	<div class="cro-ui-notice cro-ui-toast-placeholder" role="status">
		<p>
			<?php esc_html_e( 'Recommended CRO setup applied.', 'meyvora-convert' ); ?>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview your store', 'meyvora-convert' ); ?></a>
		</p>
	</div>
<?php endif; ?>

<div class="cro-dashboard-modern">
	<!-- KPI cards row -->
	<div class="cro-dashboard-kpi-row">
		<div class="cro-dashboard-kpi-card">
			<span class="cro-dashboard-kpi-card__icon"><?php echo CRO_Icons::svg_kses( 'tag', array( 'class' => 'cro-ico' ) ); ?></span>

			<div class="cro-dashboard-kpi-card__content">
				<span class="cro-dashboard-kpi-card__value"><?php echo esc_html( number_format_i18n( $active_offers_count ) ); ?></span>
				<span class="cro-dashboard-kpi-card__label"><?php esc_html_e( 'Active offers', 'meyvora-convert' ); ?></span>
			</div>
		</div>
		<div class="cro-dashboard-kpi-card">
			<span class="cro-dashboard-kpi-card__icon"><?php echo CRO_Icons::svg_kses( 'target', array( 'class' => 'cro-ico' ) ); ?></span>

			<div class="cro-dashboard-kpi-card__content">
				<span class="cro-dashboard-kpi-card__value"><?php echo esc_html( number_format_i18n( $active_ab_tests_count ) ); ?></span>
				<span class="cro-dashboard-kpi-card__label"><?php esc_html_e( 'Active A/B tests', 'meyvora-convert' ); ?></span>
			</div>
		</div>
		<div class="cro-dashboard-kpi-card">
			<span class="cro-dashboard-kpi-card__icon"><?php echo CRO_Icons::svg_kses( 'shopping-cart', array( 'class' => 'cro-ico' ) ); ?></span>

			<div class="cro-dashboard-kpi-card__content">
				<span class="cro-dashboard-kpi-card__value"><?php echo esc_html( number_format_i18n( $abandoned_carts_active ) ); ?></span>
				<span class="cro-dashboard-kpi-card__label"><?php esc_html_e( 'Abandoned carts (active)', 'meyvora-convert' ); ?></span>
			</div>
		</div>
		<?php if ( $revenue_available ) : ?>
		<div class="cro-dashboard-kpi-card">
			<span class="cro-dashboard-kpi-card__icon"><?php echo CRO_Icons::svg_kses( 'dollar-sign', array( 'class' => 'cro-ico' ) ); ?></span>

			<div class="cro-dashboard-kpi-card__content">
				<span class="cro-dashboard-kpi-card__value"><?php echo function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $revenue_influenced ) ) : esc_html( number_format_i18n( $revenue_influenced, 2 ) ); ?></span>
				<span class="cro-dashboard-kpi-card__label"><?php esc_html_e( 'Revenue influenced (30d)', 'meyvora-convert' ); ?></span>
			</div>
		</div>
		<?php endif; ?>
	</div>

	<!-- Quick actions -->
	<div class="cro-dashboard-actions">
		<h3 class="cro-dashboard-actions__title"><?php esc_html_e( 'Quick actions', 'meyvora-convert' ); ?></h3>
		<div class="cro-dashboard-actions__list">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cro-offers' ) ); ?>" class="cro-dashboard-action-btn button button-primary">
				<?php echo CRO_Icons::svg_kses( 'plus', array( 'class' => 'cro-ico' ) ); ?>

				<?php esc_html_e( 'Add Offer', 'meyvora-convert' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cro-ab-test-new' ) ); ?>" class="cro-dashboard-action-btn button">
				<?php echo CRO_Icons::svg_kses( 'plus', array( 'class' => 'cro-ico' ) ); ?>

				<?php esc_html_e( 'Create A/B test', 'meyvora-convert' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cro-system-status' ) ); ?>" class="cro-dashboard-action-btn button">
				<?php echo CRO_Icons::svg_kses( 'check', array( 'class' => 'cro-ico' ) ); ?>

				<?php esc_html_e( 'Verify Installation', 'meyvora-convert' ); ?>
			</a>
		</div>
	</div>

	<!-- Recent activity -->
	<div class="cro-dashboard-card cro-dashboard-activity">
		<header class="cro-dashboard-card__header">
			<span class="cro-dashboard-card__icon"><?php echo CRO_Icons::svg_kses( 'chart', array( 'class' => 'cro-ico' ) ); ?></span>

			<h3 class="cro-dashboard-card__title"><?php esc_html_e( 'Recent activity', 'meyvora-convert' ); ?></h3>
		</header>
		<div class="cro-dashboard-card__body">
			<?php if ( empty( $recent_events ) ) : ?>
				<div class="cro-dashboard-activity-empty">
					<p><?php esc_html_e( 'No recent events yet. Events appear here when campaigns are shown or visitors convert.', 'meyvora-convert' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=cro-campaigns' ) ); ?>" class="button"><?php esc_html_e( 'View campaigns', 'meyvora-convert' ); ?></a>
				</div>
			<?php else : ?>
				<ul class="cro-dashboard-activity-list">
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
						<li class="cro-dashboard-activity-item">
							<span class="cro-dashboard-activity-item__label"><?php echo esc_html( $label ); ?></span>
							<span class="cro-dashboard-activity-item__action"><?php echo esc_html( $action_text ); ?></span>
							<time class="cro-dashboard-activity-item__time" datetime="<?php echo esc_attr( $created ); ?>"><?php echo esc_html( $time_ago ); ?></time>
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
		<div class="cro-dashboard-card cro-dashboard-cta">
			<div class="cro-dashboard-card__body">
				<p><?php esc_html_e( 'Launch the recommended CRO setup in one click: shipping bar, checkout trust, and a sample campaign.', 'meyvora-convert' ); ?></p>
				<form method="post" class="cro-inline-form">
					<?php wp_nonce_field( 'cro_quick_launch', 'cro_quick_launch_nonce' ); ?>
					<input type="hidden" name="cro_quick_launch" value="recommended" />
					<button type="submit" class="button button-primary cro-ui-btn-primary">
						<?php echo CRO_Icons::svg_kses( 'zap', array( 'class' => 'cro-ico' ) ); ?>

						<?php esc_html_e( 'Launch recommended CRO setup', 'meyvora-convert' ); ?>
					</button>
				</form>
			</div>
		</div>
	<?php endif; ?>
</div>
