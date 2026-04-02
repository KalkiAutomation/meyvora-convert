<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Analytics Dashboard
 *
 * @package Meyvora_Convert
 */

function meyvora_kpi_change( $current, $previous ) {
	if ( ! $previous ) {
		return '';
	}
	$pct  = round( ( ( $current - $previous ) / $previous ) * 100 );
	$dir  = $pct >= 0 ? 'up' : 'down';
	$icon = $pct >= 0 ? '↑' : '↓';
	return sprintf(
		'<span class="meyvc-kpi-change meyvc-kpi-change--%s">%s %s%%</span>',
		esc_attr( $dir ),
		$icon,
		abs( $pct )
	);
}

$analytics = new MEYVC_Analytics();

$date_from = MEYVC_Security::get_query_var( 'from' );
$date_from = $date_from !== '' ? $date_from : gmdate( 'Y-m-d', strtotime( '-30 days' ) );
$date_to   = MEYVC_Security::get_query_var( 'to' );
$date_to   = $date_to !== '' ? $date_to : gmdate( 'Y-m-d' );
$campaign_id = MEYVC_Security::get_query_var_absint( 'campaign_id' );
if ( $campaign_id === 0 ) {
	$campaign_id = null;
}

$analytics_error = MEYVC_Security::get_query_var( 'error' );
if ( $analytics_error === 'invalid_nonce' ) {
	echo '<div class="meyvc-ui-notice meyvc-ui-notice--error" role="alert"><p>' . esc_html__( 'Invalid security check. Please try the export again.', 'meyvora-convert' ) . '</p></div>';
} elseif ( $analytics_error === 'unauthorized' ) {
	echo '<div class="meyvc-ui-notice meyvc-ui-notice--error" role="alert"><p>' . esc_html__( 'You do not have permission to export.', 'meyvora-convert' ) . '</p></div>';
} elseif ( $analytics_error === 'zip_unavailable' ) {
	echo '<div class="meyvc-ui-notice meyvc-ui-notice--error" role="alert"><p>' . esc_html__( 'ZIP export is not available on this server (ZipArchive missing).', 'meyvora-convert' ) . '</p></div>';
} elseif ( $analytics_error === 'zip_failed' ) {
	echo '<div class="meyvc-ui-notice meyvc-ui-notice--error" role="alert"><p>' . esc_html__( 'Could not build the ZIP export. Please try again.', 'meyvora-convert' ) . '</p></div>';
}

$summary           = $analytics->get_summary( $date_from, $date_to, $campaign_id );
$attribution_model = 'last';
if ( class_exists( 'MEYVC_Attribution' ) ) {
	$attribution_model = MEYVC_Attribution::get_current_model();
	list( $meyvc_prev_from, $meyvc_prev_to ) = MEYVC_Attribution::get_comparison_period( $date_from, $date_to );
	$attr_cur                            = MEYVC_Attribution::get_total_campaign_attributed_revenue( $date_from, $date_to, $attribution_model, $campaign_id );
	$attr_prev                           = MEYVC_Attribution::get_total_campaign_attributed_revenue( $meyvc_prev_from, $meyvc_prev_to, $attribution_model, $campaign_id );
	$summary['revenue']                  = $attr_cur;
	$summary['prev_revenue']             = $attr_prev;
	$pr                                  = (float) $attr_prev;
	$cr                                  = (float) $attr_cur;
	if ( 0.0 === $pr ) {
		$summary['revenue_change'] = $cr > 0 ? 100.0 : 0.0;
	} else {
		$summary['revenue_change'] = round( ( ( $cr - $pr ) / $pr ) * 100, 1 );
	}
	$summary['revenue_formatted'] = function_exists( 'wc_price' ) ? wc_price( $cr ) : (string) $cr;
	$imp                          = (int) $summary['impressions'];
	$rpv                          = $imp > 0 ? $cr / $imp : 0.0;
	$summary['rpv']               = round( $rpv, 2 );
	$summary['rpv_formatted']     = function_exists( 'wc_price' ) ? wc_price( $rpv ) : (string) $rpv;
}

$revenue_tooltip = '';
if ( class_exists( 'MEYVC_Attribution' ) ) {
	$attr_lbl        = MEYVC_Attribution::get_model_label( $attribution_model );
	$revenue_tooltip = implode(
		' ',
		array(
			__( 'Shows revenue from orders where Meyvora Convert had a touchpoint.', 'meyvora-convert' ),
			sprintf(
				/* translators: %s: attribution model label */
				__( 'Attribution model: %s.', 'meyvora-convert' ),
				$attr_lbl
			),
			__( 'Change model using the selector above.', 'meyvora-convert' ),
		)
	);
}

$daily_stats   = $analytics->get_daily_stats( $date_from, $date_to, $campaign_id );
$campaigns     = $analytics->get_campaign_performance( $date_from, $date_to );
$devices       = $analytics->get_device_stats( $date_from, $date_to );
$top_pages     = $analytics->get_top_pages( $date_from, $date_to, 12, $campaign_id );
$campaigns_list = $analytics->get_campaigns_list();

$panel_revenue_by_campaign = array();
$panel_revenue_by_offer    = array();
$panel_funnel              = array();
$panel_cohort              = array();
$panel_ab_summary          = array();
$panel_email_capture_rate  = null;
$err_panel_revenue         = false;
$err_panel_funnel          = false;
$err_panel_cohort          = false;
$err_panel_ab              = false;
$err_panel_email_rate      = false;
$err_rev_campaign          = false;
$err_rev_offer             = false;

try {
	if ( class_exists( 'MEYVC_Attribution' ) ) {
		$panel_revenue_by_campaign = array();
		$attr_camp                 = MEYVC_Attribution::get_revenue_by_campaign( $date_from, $date_to, $attribution_model, $campaign_id );
		foreach ( $attr_camp as $cid => $row ) {
			$panel_revenue_by_campaign[] = array(
				'campaign_id'   => (int) $cid,
				'campaign_name' => (string) ( $row['name'] ?? '' ),
				'total_revenue' => (float) ( $row['revenue'] ?? 0 ),
				'order_count'   => (int) ( $row['orders'] ?? 0 ),
			);
		}
	} else {
		$panel_revenue_by_campaign = $analytics->get_revenue_by_campaign( $date_from, $date_to, $campaign_id );
	}
} catch ( \Throwable $e ) {
	$panel_revenue_by_campaign = array();
	$err_rev_campaign          = true;
}

try {
	if ( class_exists( 'MEYVC_Attribution' ) ) {
		$panel_revenue_by_offer = array();
		$attr_off               = MEYVC_Attribution::get_offer_attribution( $date_from, $date_to, $attribution_model );
		foreach ( $attr_off as $oid => $row ) {
			$panel_revenue_by_offer[] = array(
				'offer_id'      => (int) $oid,
				'offer_name'    => (string) ( $row['name'] ?? '' ),
				'total_revenue' => (float) ( $row['revenue'] ?? 0 ),
				'total_orders'  => (int) ( $row['orders'] ?? 0 ),
			);
		}
	} else {
		$panel_revenue_by_offer = $analytics->get_offer_revenue_attribution( $date_from, $date_to );
	}
} catch ( \Throwable $e ) {
	$panel_revenue_by_offer = array();
	$err_rev_offer          = true;
}

$err_panel_revenue = $err_rev_campaign && $err_rev_offer;

try {
	$panel_funnel = $analytics->get_conversion_funnel( $date_from, $date_to, $campaign_id );
} catch ( \Throwable $e ) {
	$err_panel_funnel = true;
	$panel_funnel     = array(
		'impressions'     => 0,
		'clicks'          => 0,
		'emails_captured' => 0,
		'orders'          => 0,
	);
}

try {
	$panel_cohort = $analytics->get_cohort_recovery( $date_from, $date_to );
} catch ( \Throwable $e ) {
	$err_panel_cohort = true;
}

try {
	$panel_ab_summary = $analytics->get_ab_test_summary( $date_from, $date_to );
} catch ( \Throwable $e ) {
	$err_panel_ab = true;
}

try {
	$panel_email_capture_rate = $analytics->get_email_capture_rate( $date_from, $date_to, $campaign_id );
} catch ( \Throwable $e ) {
	$err_panel_email_rate = true;
}

$export_max_days = (int) apply_filters( 'meyvc_export_max_days', 90 );
$export_url_events = add_query_arg(
	array(
		'page'     => 'meyvc-analytics',
		'action'   => 'export',
		'format'   => 'events',
		'from'     => $date_from,
		'to'       => $date_to,
		'_wpnonce' => wp_create_nonce( 'meyvc_export' ),
	),
	admin_url( 'admin.php' )
);
$export_url_daily = add_query_arg(
	array(
		'page'     => 'meyvc-analytics',
		'action'   => 'export',
		'format'   => 'daily',
		'from'     => $date_from,
		'to'       => $date_to,
		'_wpnonce' => wp_create_nonce( 'meyvc_export' ),
	),
	admin_url( 'admin.php' )
);
$export_url_zip = add_query_arg(
	array(
		'page'     => 'meyvc-analytics',
		'action'   => 'export',
		'format'   => 'zip_report',
		'from'     => $date_from,
		'to'       => $date_to,
		'_wpnonce' => wp_create_nonce( 'meyvc_export' ),
	),
	admin_url( 'admin.php' )
);
if ( $campaign_id !== null ) {
	$export_url_events = add_query_arg( 'campaign_id', $campaign_id, $export_url_events );
	$export_url_zip    = add_query_arg( 'campaign_id', $campaign_id, $export_url_zip );
}

$revenue_chart_campaigns = array();
foreach ( array_slice( $panel_revenue_by_campaign, 0, 10 ) as $row ) {
	$revenue_chart_campaigns[] = array(
		'label'   => (string) ( $row['campaign_name'] ?? '' ),
		'revenue' => (float) ( $row['total_revenue'] ?? 0 ),
	);
}
$revenue_chart_offers = array();
foreach ( array_slice( $panel_revenue_by_offer, 0, 10 ) as $row ) {
	$revenue_chart_offers[] = array(
		'label'   => (string) ( $row['offer_name'] ?? '' ),
		'revenue' => (float) ( $row['total_revenue'] ?? 0 ),
	);
}

$currency_sym = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';

$bootstrap = array(
	'dailyStats'         => $daily_stats,
	'devices'            => $devices,
	'revenueByCampaign'  => $revenue_chart_campaigns,
	'revenueByOffer'     => $revenue_chart_offers,
	'strings'            => array(
		'conversions'     => __( 'Conversions', 'meyvora-convert' ),
		'revenue'         => __( 'Revenue', 'meyvora-convert' ),
		'currencyPrefix'  => $currency_sym ? $currency_sym . ' ' : '',
	),
);

$funnel_imp = (int) ( $panel_funnel['impressions'] ?? 0 );
$funnel_clk = (int) ( $panel_funnel['clicks'] ?? 0 );
$funnel_em  = (int) ( $panel_funnel['emails_captured'] ?? 0 );
$funnel_ord = (int) ( $panel_funnel['orders'] ?? 0 );

$funnel_pct = static function ( $step, $base ) {
	$base = (int) $base;
	$step = (int) $step;
	if ( $base <= 0 ) {
		return 0.0;
	}
	return round( ( $step / $base ) * 100, 2 );
};

$funnel_drop = static function ( $from, $to ) {
	$from = (int) $from;
	$to   = (int) $to;
	if ( $from <= 0 ) {
		return 0.0;
	}
	return round( ( 1 - ( $to / $from ) ) * 100, 2 );
};

$funnel_steps = array(
	array(
		'label' => __( 'Impressions', 'meyvora-convert' ),
		'count' => $funnel_imp,
		'pct'   => $funnel_imp > 0 ? 100.0 : 0.0,
		'color' => '#d0e8ff',
	),
	array(
		'label' => __( 'Clicks', 'meyvora-convert' ),
		'count' => $funnel_clk,
		'pct'   => $funnel_pct( $funnel_clk, $funnel_imp ),
		'color' => '#b6daf8',
	),
	array(
		'label' => __( 'Emails', 'meyvora-convert' ),
		'count' => $funnel_em,
		'pct'   => $funnel_pct( $funnel_em, $funnel_imp ),
		'color' => '#6fabed',
	),
	array(
		'label' => __( 'Orders', 'meyvora-convert' ),
		'count' => $funnel_ord,
		'pct'   => $funnel_pct( $funnel_ord, $funnel_imp ),
		'color' => '#1a73e8',
	),
);

$cohort_tot_ab = 0;
$cohort_tot_re = 0;
foreach ( $panel_cohort as $cw ) {
	$cohort_tot_ab += (int) ( $cw['total_abandoned'] ?? 0 );
	$cohort_tot_re += (int) ( $cw['recovered'] ?? 0 );
}
$cohort_tot_rate = $cohort_tot_ab > 0 ? round( ( $cohort_tot_re / $cohort_tot_ab ) * 100, 2 ) : 0.0;

?>

	<!-- Filters: Date range + Campaign -->
	<div class="meyvc-analytics-filters">
		<form method="get" class="meyvc-date-form">
			<input type="hidden" name="page" value="meyvc-analytics" />

			<div class="meyvc-date-presets">
				<button type="button" class="button <?php echo $date_from === gmdate( 'Y-m-d', strtotime( '-7 days' ) ) ? 'active' : ''; ?>"
					data-from="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '-7 days' ) ) ); ?>"
					data-to="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>">
					<?php esc_html_e( 'Last 7 days', 'meyvora-convert' ); ?>
				</button>
				<button type="button" class="button <?php echo $date_from === gmdate( 'Y-m-d', strtotime( '-30 days' ) ) ? 'active' : ''; ?>"
					data-from="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '-30 days' ) ) ); ?>"
					data-to="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>">
					<?php esc_html_e( 'Last 30 days', 'meyvora-convert' ); ?>
				</button>
				<button type="button" class="button"
					data-from="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '-90 days' ) ) ); ?>"
					data-to="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>">
					<?php esc_html_e( 'Last 90 days', 'meyvora-convert' ); ?>
				</button>
			</div>

			<div class="meyvc-date-custom">
				<input type="date" name="from" value="<?php echo esc_attr( $date_from ); ?>" />
				<span><?php esc_html_e( 'to', 'meyvora-convert' ); ?></span>
				<input type="date" name="to" value="<?php echo esc_attr( $date_to ); ?>" />
				<select name="campaign_id" id="meyvc-campaign-filter" class="meyvc-selectwoo" data-placeholder="<?php esc_attr_e( 'All campaigns', 'meyvora-convert' ); ?>">
					<option value=""><?php esc_html_e( 'All campaigns', 'meyvora-convert' ); ?></option>
					<?php foreach ( $campaigns_list as $cid => $cname ) : ?>
						<option value="<?php echo esc_attr( $cid ); ?>" <?php selected( $campaign_id, $cid ); ?>><?php echo esc_html( $cname ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply', 'meyvora-convert' ); ?></button>
			</div>

			<div class="meyvc-export-actions">
				<a href="<?php echo esc_url( $export_url_events ); ?>" class="button"><?php esc_html_e( 'Export events CSV', 'meyvora-convert' ); ?></a>
				<a href="<?php echo esc_url( $export_url_daily ); ?>" class="button"><?php esc_html_e( 'Daily summary CSV', 'meyvora-convert' ); ?></a>
				<?php if ( class_exists( 'ZipArchive' ) ) : ?>
				<a href="<?php echo esc_url( $export_url_zip ); ?>" class="button" title="<?php esc_attr_e( 'Downloads campaigns, offers, funnel, cohort, and top pages as CSVs.', 'meyvora-convert' ); ?>"><?php esc_html_e( 'Full Report (ZIP)', 'meyvora-convert' ); ?></a>
				<?php endif; ?>
				<span class="meyvc-muted" style="font-size: 12px;"><?php echo esc_html( sprintf( /* translators: %d is the maximum number of days for the export range. */ __( 'Max %d days.', 'meyvora-convert' ), $export_max_days ) ); ?></span>
			</div>
		</form>
	</div>

	<!-- KPI Cards -->
	<div class="meyvc-kpi-grid">
		<?php if ( class_exists( 'MEYVC_Attribution' ) ) : ?>
		<div class="meyvc-kpi-span-full">
			<div class="meyvc-attribution-selector" role="group" aria-label="<?php esc_attr_e( 'Attribution model', 'meyvora-convert' ); ?>">
				<span class="meyvc-attribution-label"><?php esc_html_e( 'Attribution:', 'meyvora-convert' ); ?> <span class="meyvc-attribution-model-label"><?php echo esc_html( MEYVC_Attribution::get_model_label( $attribution_model ) ); ?></span></span>
				<label class="meyvc-attribution-opt">
					<input type="radio" name="meyvc_attribution" value="first" <?php checked( $attribution_model, 'first' ); ?> />
					<?php esc_html_e( 'First touch', 'meyvora-convert' ); ?>
				</label>
				<label class="meyvc-attribution-opt">
					<input type="radio" name="meyvc_attribution" value="last" <?php checked( $attribution_model, 'last' ); ?> />
					<?php esc_html_e( 'Last touch', 'meyvora-convert' ); ?>
				</label>
				<label class="meyvc-attribution-opt">
					<input type="radio" name="meyvc_attribution" value="linear" <?php checked( $attribution_model, 'linear' ); ?> />
					<?php esc_html_e( 'Linear', 'meyvora-convert' ); ?>
				</label>
			</div>
		</div>
		<?php endif; ?>
		<div class="meyvc-kpi-card">
			<div class="meyvc-kpi-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'eye', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></div>

			<div class="meyvc-kpi-content">
				<span class="meyvc-kpi-value"><?php echo esc_html( number_format_i18n( $summary['impressions'] ) ); ?></span>
				<span class="meyvc-kpi-label"><?php esc_html_e( 'Impressions', 'meyvora-convert' ); ?></span>
				<?php echo wp_kses_post( meyvora_kpi_change( $summary['impressions'], $summary['prev_impressions'] ) ); ?>
			</div>
		</div>

		<div class="meyvc-kpi-card">
			<div class="meyvc-kpi-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'mouse-pointer', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></div>

			<div class="meyvc-kpi-content">
				<span class="meyvc-kpi-value"><?php echo esc_html( number_format_i18n( $summary['clicks'] ) ); ?></span>
				<span class="meyvc-kpi-label"><?php esc_html_e( 'Clicks', 'meyvora-convert' ); ?></span>
			</div>
		</div>

		<div class="meyvc-kpi-card">
			<div class="meyvc-kpi-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'trending-up', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></div>

			<div class="meyvc-kpi-content">
				<span class="meyvc-kpi-value"><?php echo esc_html( $summary['ctr'] ); ?>%</span>
				<span class="meyvc-kpi-label"><?php esc_html_e( 'CTR', 'meyvora-convert' ); ?></span>
			</div>
		</div>

		<div class="meyvc-kpi-card meyvc-kpi-card--revenue">
			<div class="meyvc-kpi-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'dollar-sign', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></div>

			<div class="meyvc-kpi-content">
				<span class="meyvc-kpi-value js-meyvc-revenue-kpi-value"><?php echo wp_kses_post( $summary['revenue_formatted'] ); ?></span>
				<span class="meyvc-kpi-label">
					<?php esc_html_e( 'Revenue influenced', 'meyvora-convert' ); ?>
					<?php if ( $revenue_tooltip !== '' ) : ?>
					<span class="dashicons dashicons-info meyvc-kpi-tip js-meyvc-revenue-kpi-tip" title="<?php echo esc_attr( $revenue_tooltip ); ?>"></span>
					<?php endif; ?>
				</span>
				<span class="js-meyvc-revenue-kpi-change"><?php echo wp_kses_post( meyvora_kpi_change( $summary['revenue'], $summary['prev_revenue'] ) ); ?></span>
			</div>
		</div>

		<div class="meyvc-kpi-card">
			<div class="meyvc-kpi-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'shopping-cart', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></div>

			<div class="meyvc-kpi-content">
				<span class="meyvc-kpi-value"><?php echo esc_html( number_format_i18n( $summary['sticky_cart_adds'] ) ); ?></span>
				<span class="meyvc-kpi-label"><?php esc_html_e( 'Add-to-cart from sticky', 'meyvora-convert' ); ?></span>
			</div>
		</div>

		<div class="meyvc-kpi-card">
			<div class="meyvc-kpi-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'truck', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></div>

			<div class="meyvc-kpi-content">
				<span class="meyvc-kpi-value"><?php echo esc_html( number_format_i18n( $summary['shipping_bar_interactions'] ) ); ?></span>
				<span class="meyvc-kpi-label"><?php esc_html_e( 'Shipping bar interactions', 'meyvora-convert' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Secondary stats -->
	<div class="meyvc-stats-grid">
		<div class="meyvc-stat-card">
			<div class="meyvc-stat-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'target', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></div>

			<div class="meyvc-stat-content">
				<span class="meyvc-stat-value"><?php echo esc_html( number_format_i18n( $summary['conversions'] ) ); ?></span>
				<span class="meyvc-stat-label"><?php esc_html_e( 'Conversions', 'meyvora-convert' ); ?></span>
				<?php echo wp_kses_post( meyvora_kpi_change( $summary['conversions'], $summary['prev_conversions'] ) ); ?>
			</div>
		</div>
		<div class="meyvc-stat-card">
			<div class="meyvc-stat-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'chart', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></div>

			<div class="meyvc-stat-content">
				<span class="meyvc-stat-value"><?php echo esc_html( $summary['conversion_rate'] ); ?>%</span>
				<span class="meyvc-stat-label"><?php esc_html_e( 'Conversion Rate', 'meyvora-convert' ); ?></span>
			</div>
		</div>
		<div class="meyvc-stat-card">
			<div class="meyvc-stat-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'mail', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></div>

			<div class="meyvc-stat-content">
				<span class="meyvc-stat-value"><?php echo esc_html( number_format_i18n( $summary['emails'] ) ); ?></span>
				<span class="meyvc-stat-label"><?php esc_html_e( 'Emails Captured', 'meyvora-convert' ); ?></span>
				<?php echo wp_kses_post( meyvora_kpi_change( $summary['emails'], $summary['prev_emails'] ) ); ?>
			</div>
		</div>
		<div class="meyvc-stat-card">
			<div class="meyvc-stat-icon"><?php echo wp_kses( MEYVC_Icons::svg( 'dollar-sign', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></div>

			<div class="meyvc-stat-content">
				<span class="meyvc-stat-value js-meyvc-rpv-stat-value"><?php echo wp_kses_post( $summary['rpv_formatted'] ); ?></span>
				<span class="meyvc-stat-label"><?php esc_html_e( 'Revenue per Visitor', 'meyvora-convert' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Charts Row -->
	<div class="meyvc-charts-row">
		<div class="meyvc-chart-card meyvc-chart--main">
			<div class="meyvc-chart-header">
				<h3><?php esc_html_e( 'Performance Over Time', 'meyvora-convert' ); ?></h3>
				<div class="meyvc-chart-toggle">
					<button type="button" class="active" data-metric="conversions"><?php esc_html_e( 'Conversions', 'meyvora-convert' ); ?></button>
					<button type="button" data-metric="revenue"><?php esc_html_e( 'Revenue', 'meyvora-convert' ); ?></button>
					<button type="button" data-metric="impressions"><?php esc_html_e( 'Impressions', 'meyvora-convert' ); ?></button>
				</div>
			</div>
			<div class="meyvc-chart-body">
				<canvas id="meyvc-main-chart" height="300"></canvas>
			</div>
		</div>
		<div class="meyvc-chart-card meyvc-chart--device">
			<div class="meyvc-chart-header">
				<h3><?php esc_html_e( 'By Device', 'meyvora-convert' ); ?></h3>
			</div>
			<div class="meyvc-chart-body">
				<canvas id="meyvc-device-chart" height="200"></canvas>
			</div>
		</div>
	</div>

	<!-- Tables Row -->
	<div class="meyvc-tables-row">
		<div class="meyvc-table-card">
			<div class="meyvc-table-header">
				<h3><?php esc_html_e( 'Campaign Performance', 'meyvora-convert' ); ?></h3>
			</div>
			<div class="meyvc-table-wrap">
			<table class="meyvc-table widefat">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Campaign', 'meyvora-convert' ); ?></th>
						<th class="meyvc-table-num"><?php esc_html_e( 'Impressions', 'meyvora-convert' ); ?></th>
						<th class="meyvc-table-num"><?php esc_html_e( 'Conversions', 'meyvora-convert' ); ?></th>
						<th class="meyvc-table-num"><?php esc_html_e( 'Conv. Rate', 'meyvora-convert' ); ?></th>
						<th class="meyvc-table-num"><?php esc_html_e( 'Revenue', 'meyvora-convert' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $campaigns ) ) : ?>
					<tr class="meyvc-table-empty-row">
						<td colspan="5" class="meyvc-no-data"><?php esc_html_e( 'No data yet', 'meyvora-convert' ); ?></td>
					</tr>
					<?php else : ?>
					<?php foreach ( $campaigns as $campaign ) : ?>
					<?php $rate = $campaign['impressions'] > 0 ? round( ( $campaign['conversions'] / $campaign['impressions'] ) * 100, 2 ) : 0; ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $campaign['name'] ); ?></strong>
							<span class="meyvc-status meyvc-status--<?php echo esc_attr( $campaign['status'] ); ?>"><?php echo esc_html( $campaign['status'] ); ?></span>
						</td>
						<td class="meyvc-table-num"><?php echo esc_html( number_format_i18n( $campaign['impressions'] ) ); ?></td>
						<td class="meyvc-table-num"><?php echo esc_html( number_format_i18n( $campaign['conversions'] ) ); ?></td>
						<td class="meyvc-table-num"><?php echo esc_html( $rate ); ?>%</td>
						<td class="meyvc-table-num"><?php echo function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $campaign['revenue'] ) ) : esc_html( $campaign['revenue'] ); ?></td>
					</tr>
					<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			</div>
		</div>
		<div class="meyvc-table-card">
			<div class="meyvc-table-header">
				<h3><?php esc_html_e( 'Top Converting Pages', 'meyvora-convert' ); ?></h3>
			</div>
			<div class="meyvc-table-wrap">
			<table class="meyvc-table widefat js-meyvc-top-pages-table">
				<thead>
					<tr>
						<th data-sort-key="url"><?php esc_html_e( 'Page URL', 'meyvora-convert' ); ?></th>
						<th class="meyvc-table-num" data-sort-key="impressions"><?php esc_html_e( 'Impressions', 'meyvora-convert' ); ?></th>
						<th class="meyvc-table-num" data-sort-key="conversions"><?php esc_html_e( 'Conversions', 'meyvora-convert' ); ?></th>
						<th class="meyvc-table-num" data-sort-key="rate"><?php esc_html_e( 'Rate', 'meyvora-convert' ); ?></th>
						<th class="meyvc-table-num" data-sort-key="emails"><?php esc_html_e( 'Emails', 'meyvora-convert' ); ?></th>
						<th class="meyvc-table-num"><?php esc_html_e( 'Actions', 'meyvora-convert' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $top_pages ) ) : ?>
					<tr class="meyvc-table-empty-row">
						<td colspan="6" class="meyvc-no-data"><?php esc_html_e( 'No data yet', 'meyvora-convert' ); ?></td>
					</tr>
					<?php else : ?>
					<?php foreach ( $top_pages as $tp ) : ?>
					<?php
					$purl      = isset( $tp['page_url'] ) ? (string) $tp['page_url'] : '';
					$imp       = (int) ( $tp['impressions'] ?? 0 );
					$conv      = (int) ( $tp['conversions'] ?? 0 );
					$emc       = (int) ( $tp['emails_captured'] ?? 0 );
					$rate_pg   = $imp > 0 ? round( ( $conv / $imp ) * 100, 2 ) : 0;
					$url_show  = $purl;
					if ( function_exists( 'mb_strlen' ) && mb_strlen( $url_show ) > 50 ) {
						$url_show = mb_substr( $url_show, 0, 47 ) . '…';
					} elseif ( strlen( $url_show ) > 50 ) {
						$url_show = substr( $url_show, 0, 47 ) . '…';
					}
					?>
					<tr>
						<td data-sort="url" data-sort-value="<?php echo esc_attr( $purl ); ?>" title="<?php echo esc_attr( $purl ); ?>">
							<?php echo esc_html( $url_show ); ?>
						</td>
						<td class="meyvc-table-num" data-sort="impressions" data-sort-value="<?php echo esc_attr( (string) $imp ); ?>"><?php echo esc_html( number_format_i18n( $imp ) ); ?></td>
						<td class="meyvc-table-num" data-sort="conversions" data-sort-value="<?php echo esc_attr( (string) $conv ); ?>"><?php echo esc_html( number_format_i18n( $conv ) ); ?></td>
						<td class="meyvc-table-num" data-sort="rate" data-sort-value="<?php echo esc_attr( (string) $rate_pg ); ?>"><?php echo esc_html( number_format_i18n( $rate_pg, 2 ) ); ?>%</td>
						<td class="meyvc-table-num" data-sort="emails" data-sort-value="<?php echo esc_attr( (string) $emc ); ?>"><?php echo esc_html( number_format_i18n( $emc ) ); ?></td>
						<td class="meyvc-table-num">
							<button type="button" class="button button-small js-meyvc-copy-url" data-url="<?php echo esc_attr( $purl ); ?>"><?php esc_html_e( 'Copy URL', 'meyvora-convert' ); ?></button>
						</td>
					</tr>
					<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			</div>
		</div>
	</div>

	<!-- Panel A: Revenue attribution -->
	<div class="meyvc-analytics-panel meyvc-analytics-panel--revenue">
		<div class="meyvc-chart-header meyvc-analytics-panel__header">
			<h3><?php esc_html_e( 'Revenue Attribution', 'meyvora-convert' ); ?></h3>
			<div class="meyvc-analytics-toggle-group">
				<button type="button" class="button js-meyvc-revenue-toggle active" data-mode="campaign"><?php esc_html_e( 'By Campaign', 'meyvora-convert' ); ?></button>
				<button type="button" class="button js-meyvc-revenue-toggle" data-mode="offer"><?php esc_html_e( 'By Offer', 'meyvora-convert' ); ?></button>
			</div>
		</div>
		<?php if ( $err_panel_revenue ) : ?>
			<p class="description"><?php esc_html_e( 'Data unavailable for this panel.', 'meyvora-convert' ); ?></p>
		<?php else : ?>
		<div class="meyvc-analytics-panel__chart" style="height:300px;position:relative;">
			<canvas id="meyvc-revenue-attribution-chart"></canvas>
		</div>
		<?php endif; ?>
	</div>

	<!-- Panel B: Funnel -->
	<div class="meyvc-analytics-panel meyvc-analytics-panel--funnel">
		<div class="meyvc-chart-header">
			<h3><?php esc_html_e( 'Conversion Funnel', 'meyvora-convert' ); ?></h3>
		</div>
		<?php if ( $err_panel_funnel ) : ?>
			<p class="description"><?php esc_html_e( 'Data unavailable for this panel.', 'meyvora-convert' ); ?></p>
		<?php else : ?>
		<?php if ( ! $err_panel_email_rate && null !== $panel_email_capture_rate ) : ?>
			<p class="description meyvc-analytics-funnel__meta">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: percentage */
						__( 'Email capture rate (subscribed / impressions): %s%%', 'meyvora-convert' ),
						number_format_i18n( (float) $panel_email_capture_rate, 2 )
					)
				);
				?>
			</p>
		<?php endif; ?>
		<div class="meyvc-funnel-visual">
			<?php foreach ( $funnel_steps as $i => $step ) : ?>
				<?php if ( $i > 0 ) : ?>
					<div class="meyvc-funnel-visual__drop" aria-hidden="true">
						<?php
						$prev_c = (int) ( $funnel_steps[ $i - 1 ]['count'] ?? 0 );
						$cur_c  = (int) ( $step['count'] ?? 0 );
						$drop   = $funnel_drop( $prev_c, $cur_c );
						echo esc_html(
							sprintf(
								/* translators: %s: percentage */
								__( '↓ %s%% did not proceed', 'meyvora-convert' ),
								number_format_i18n( $drop, 2 )
							)
						);
						?>
					</div>
				<?php endif; ?>
				<div class="meyvc-funnel-visual__step" style="<?php echo esc_attr( 'background-color:' . $step['color'] . ';' ); ?>">
					<span class="meyvc-funnel-visual__name"><?php echo esc_html( $step['label'] ); ?></span>
					<span class="meyvc-funnel-visual__count"><?php echo esc_html( number_format_i18n( (int) $step['count'] ) ); ?></span>
					<span class="meyvc-funnel-visual__pct"><?php echo esc_html( number_format_i18n( (float) $step['pct'], 2 ) ); ?>% <?php esc_html_e( 'of impressions', 'meyvora-convert' ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</div>

	<!-- Panel C: A/B summary -->
	<div class="meyvc-analytics-panel meyvc-analytics-panel--ab">
		<div class="meyvc-chart-header">
			<h3><?php esc_html_e( 'A/B Tests Summary', 'meyvora-convert' ); ?></h3>
		</div>
		<?php if ( $err_panel_ab ) : ?>
			<p class="description"><?php esc_html_e( 'Data unavailable for this panel.', 'meyvora-convert' ); ?></p>
		<?php elseif ( empty( $panel_ab_summary ) ) : ?>
			<p class="description"><?php esc_html_e( 'No A/B tests to show yet.', 'meyvora-convert' ); ?></p>
			<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=meyvc-ab-test-new' ) ); ?>"><?php esc_html_e( 'Start your first A/B test', 'meyvora-convert' ); ?></a></p>
		<?php else : ?>
		<div class="meyvc-table-wrap">
			<table class="meyvc-table widefat meyvc-ab-summary-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Test name', 'meyvora-convert' ); ?></th>
						<th><?php esc_html_e( 'Status', 'meyvora-convert' ); ?></th>
						<th class="meyvc-table-num"><?php esc_html_e( 'Variants', 'meyvora-convert' ); ?></th>
						<th><?php esc_html_e( 'Winner', 'meyvora-convert' ); ?></th>
						<th><?php esc_html_e( 'Significance', 'meyvora-convert' ); ?></th>
						<th><?php esc_html_e( 'Started', 'meyvora-convert' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $panel_ab_summary as $ab_row ) : ?>
					<tr class="js-meyvc-ab-summary-row" data-test-id="<?php echo esc_attr( (string) ( $ab_row['id'] ?? '' ) ); ?>">
						<td><strong><?php echo esc_html( $ab_row['name'] ?? '' ); ?></strong></td>
						<td><span class="meyvc-status meyvc-status--<?php echo esc_attr( $ab_row['status'] ?? '' ); ?>"><?php echo esc_html( ucfirst( (string) ( $ab_row['status'] ?? '' ) ) ); ?></span></td>
						<td class="meyvc-table-num"><?php echo esc_html( number_format_i18n( (int) ( $ab_row['variants_count'] ?? 0 ) ) ); ?></td>
						<td><?php echo esc_html( $ab_row['winner'] ?? '—' ); ?></td>
						<td><?php echo esc_html( $ab_row['significance'] ?? '—' ); ?></td>
						<td><?php echo esc_html( $ab_row['started'] ?? '—' ); ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=meyvc-ab-test-view&id=' . absint( $ab_row['id'] ?? 0 ) ) ); ?>"><?php esc_html_e( 'View full report →', 'meyvora-convert' ); ?></a>
						</td>
					</tr>
					<tr class="meyvc-ab-summary-expand js-meyvc-ab-expand" data-test-id="<?php echo esc_attr( (string) ( $ab_row['id'] ?? '' ) ); ?>" hidden>
						<td colspan="7">
							<table class="meyvc-table widefat">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Variant', 'meyvora-convert' ); ?></th>
										<th class="meyvc-table-num"><?php esc_html_e( 'Impressions', 'meyvora-convert' ); ?></th>
										<th class="meyvc-table-num"><?php esc_html_e( 'Conversions', 'meyvora-convert' ); ?></th>
										<th class="meyvc-table-num"><?php esc_html_e( 'Rate', 'meyvora-convert' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( (array) ( $ab_row['variations'] ?? array() ) as $v ) : ?>
									<tr>
										<td><?php echo esc_html( $v['name'] ?? '' ); ?></td>
										<td class="meyvc-table-num"><?php echo esc_html( number_format_i18n( (int) ( $v['impressions'] ?? 0 ) ) ); ?></td>
										<td class="meyvc-table-num"><?php echo esc_html( number_format_i18n( (int) ( $v['conversions'] ?? 0 ) ) ); ?></td>
										<td class="meyvc-table-num"><?php echo esc_html( $v['rate_display'] ?? '' ); ?></td>
									</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>
	</div>

	<!-- Panel D: Cohort recovery -->
	<div class="meyvc-analytics-panel meyvc-analytics-panel--cohort">
		<div class="meyvc-chart-header">
			<h3><?php esc_html_e( 'Weekly Cohort Recovery', 'meyvora-convert' ); ?></h3>
		</div>
		<?php if ( $err_panel_cohort ) : ?>
			<p class="description"><?php esc_html_e( 'Data unavailable for this panel.', 'meyvora-convert' ); ?></p>
		<?php elseif ( empty( $panel_cohort ) ) : ?>
			<p class="description"><?php esc_html_e( 'No cohort data for this period.', 'meyvora-convert' ); ?></p>
		<?php else : ?>
		<div class="meyvc-table-wrap">
			<table class="meyvc-table widefat meyvc-cohort-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Week', 'meyvora-convert' ); ?></th>
						<th class="meyvc-table-num"><?php esc_html_e( 'Abandoned', 'meyvora-convert' ); ?></th>
						<th class="meyvc-table-num"><?php esc_html_e( 'Recovered', 'meyvora-convert' ); ?></th>
						<th class="meyvc-table-num"><?php esc_html_e( 'Recovery rate', 'meyvora-convert' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $panel_cohort as $cw ) : ?>
					<?php
					$rr = (float) ( $cw['recovery_rate'] ?? 0 );
					$band = 'meyvc-cohort-rate--mid';
					if ( $rr < 10 ) {
						$band = 'meyvc-cohort-rate--low';
					} elseif ( $rr > 25 ) {
						$band = 'meyvc-cohort-rate--high';
					}
					?>
					<tr>
						<td><?php echo esc_html( $cw['week_label'] ?? '' ); ?></td>
						<td class="meyvc-table-num"><?php echo esc_html( number_format_i18n( (int) ( $cw['total_abandoned'] ?? 0 ) ) ); ?></td>
						<td class="meyvc-table-num"><?php echo esc_html( number_format_i18n( (int) ( $cw['recovered'] ?? 0 ) ) ); ?></td>
						<td class="meyvc-table-num <?php echo esc_attr( $band ); ?>"><?php echo esc_html( number_format_i18n( $rr, 2 ) ); ?>%</td>
					</tr>
					<?php endforeach; ?>
					<tr class="meyvc-cohort-total">
						<td><strong><?php esc_html_e( 'Total', 'meyvora-convert' ); ?></strong></td>
						<td class="meyvc-table-num"><strong><?php echo esc_html( number_format_i18n( $cohort_tot_ab ) ); ?></strong></td>
						<td class="meyvc-table-num"><strong><?php echo esc_html( number_format_i18n( $cohort_tot_re ) ); ?></strong></td>
						<td class="meyvc-table-num"><strong><?php echo esc_html( number_format_i18n( $cohort_tot_rate, 2 ) ); ?>%</strong></td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php endif; ?>
	</div>

<?php
wp_localize_script(
	'meyvc-analytics-page',
	'meyvcAnalyticsBootstrap',
	$bootstrap
);
?>
