<?php
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
		'<span class="cro-kpi-change cro-kpi-change--%s">%s %s%%</span>',
		esc_attr( $dir ),
		$icon,
		abs( $pct )
	);
}

$analytics = new CRO_Analytics();

$date_from   = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : date( 'Y-m-d', strtotime( '-30 days' ) );
$date_to     = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : date( 'Y-m-d' );
$campaign_id = isset( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : null;
if ( $campaign_id === 0 ) {
	$campaign_id = null;
}

$analytics_error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
if ( $analytics_error === 'invalid_nonce' ) {
	echo '<div class="cro-ui-notice cro-ui-notice--error" role="alert"><p>' . esc_html__( 'Invalid security check. Please try the export again.', 'meyvora-convert' ) . '</p></div>';
} elseif ( $analytics_error === 'unauthorized' ) {
	echo '<div class="cro-ui-notice cro-ui-notice--error" role="alert"><p>' . esc_html__( 'You do not have permission to export.', 'meyvora-convert' ) . '</p></div>';
}

$summary      = $analytics->get_summary( $date_from, $date_to, $campaign_id );
$daily_stats  = $analytics->get_daily_stats( $date_from, $date_to, $campaign_id );
$campaigns    = $analytics->get_campaign_performance( $date_from, $date_to );
$devices      = $analytics->get_device_stats( $date_from, $date_to );
$top_pages    = $analytics->get_top_pages( $date_from, $date_to, 5, $campaign_id );
$campaigns_list = $analytics->get_campaigns_list();

$export_max_days = (int) apply_filters( 'cro_export_max_days', 90 );
$export_url_events = add_query_arg(
	array(
		'page'     => 'cro-analytics',
		'action'   => 'export',
		'format'   => 'events',
		'from'     => $date_from,
		'to'       => $date_to,
		'_wpnonce' => wp_create_nonce( 'cro_export' ),
	),
	admin_url( 'admin.php' )
);
$export_url_daily = add_query_arg(
	array(
		'page'     => 'cro-analytics',
		'action'   => 'export',
		'format'   => 'daily',
		'from'     => $date_from,
		'to'       => $date_to,
		'_wpnonce' => wp_create_nonce( 'cro_export' ),
	),
	admin_url( 'admin.php' )
);
if ( $campaign_id !== null ) {
	$export_url_events = add_query_arg( 'campaign_id', $campaign_id, $export_url_events );
}

wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', array(), '4.4.0', true );
?>

	<!-- Filters: Date range + Campaign -->
	<div class="cro-analytics-filters">
		<form method="get" class="cro-date-form">
			<input type="hidden" name="page" value="cro-analytics" />

			<div class="cro-date-presets">
				<button type="button" class="button <?php echo $date_from === date( 'Y-m-d', strtotime( '-7 days' ) ) ? 'active' : ''; ?>"
					data-from="<?php echo esc_attr( date( 'Y-m-d', strtotime( '-7 days' ) ) ); ?>"
					data-to="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
					<?php esc_html_e( 'Last 7 days', 'meyvora-convert' ); ?>
				</button>
				<button type="button" class="button <?php echo $date_from === date( 'Y-m-d', strtotime( '-30 days' ) ) ? 'active' : ''; ?>"
					data-from="<?php echo esc_attr( date( 'Y-m-d', strtotime( '-30 days' ) ) ); ?>"
					data-to="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
					<?php esc_html_e( 'Last 30 days', 'meyvora-convert' ); ?>
				</button>
				<button type="button" class="button"
					data-from="<?php echo esc_attr( date( 'Y-m-d', strtotime( '-90 days' ) ) ); ?>"
					data-to="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
					<?php esc_html_e( 'Last 90 days', 'meyvora-convert' ); ?>
				</button>
			</div>

			<div class="cro-date-custom">
				<input type="date" name="from" value="<?php echo esc_attr( $date_from ); ?>" />
				<span><?php esc_html_e( 'to', 'meyvora-convert' ); ?></span>
				<input type="date" name="to" value="<?php echo esc_attr( $date_to ); ?>" />
				<select name="campaign_id" id="cro-campaign-filter" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'All campaigns', 'meyvora-convert' ); ?>">
					<option value=""><?php esc_html_e( 'All campaigns', 'meyvora-convert' ); ?></option>
					<?php foreach ( $campaigns_list as $cid => $cname ) : ?>
						<option value="<?php echo esc_attr( $cid ); ?>" <?php selected( $campaign_id, $cid ); ?>><?php echo esc_html( $cname ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply', 'meyvora-convert' ); ?></button>
			</div>

			<div class="cro-export-actions">
				<a href="<?php echo esc_url( $export_url_events ); ?>" class="button"><?php esc_html_e( 'Export events CSV', 'meyvora-convert' ); ?></a>
				<a href="<?php echo esc_url( $export_url_daily ); ?>" class="button"><?php esc_html_e( 'Daily summary CSV', 'meyvora-convert' ); ?></a>
				<span class="cro-muted" style="font-size: 12px;"><?php echo esc_html( sprintf( __( 'Max %d days.', 'meyvora-convert' ), $export_max_days ) ); ?></span>
			</div>
		</form>
	</div>

	<!-- KPI Cards -->
	<div class="cro-kpi-grid">
		<div class="cro-kpi-card">
			<div class="cro-kpi-icon"><?php echo CRO_Icons::svg( 'eye', array( 'class' => 'cro-ico' ) ); ?></div>
			<div class="cro-kpi-content">
				<span class="cro-kpi-value"><?php echo esc_html( number_format_i18n( $summary['impressions'] ) ); ?></span>
				<span class="cro-kpi-label"><?php esc_html_e( 'Impressions', 'meyvora-convert' ); ?></span>
				<?php echo meyvora_kpi_change( $summary['impressions'], $summary['prev_impressions'] ); ?>
			</div>
		</div>

		<div class="cro-kpi-card">
			<div class="cro-kpi-icon"><?php echo CRO_Icons::svg( 'mouse-pointer', array( 'class' => 'cro-ico' ) ); ?></div>
			<div class="cro-kpi-content">
				<span class="cro-kpi-value"><?php echo esc_html( number_format_i18n( $summary['clicks'] ) ); ?></span>
				<span class="cro-kpi-label"><?php esc_html_e( 'Clicks', 'meyvora-convert' ); ?></span>
			</div>
		</div>

		<div class="cro-kpi-card">
			<div class="cro-kpi-icon"><?php echo CRO_Icons::svg( 'trending-up', array( 'class' => 'cro-ico' ) ); ?></div>
			<div class="cro-kpi-content">
				<span class="cro-kpi-value"><?php echo esc_html( $summary['ctr'] ); ?>%</span>
				<span class="cro-kpi-label"><?php esc_html_e( 'CTR', 'meyvora-convert' ); ?></span>
			</div>
		</div>

		<div class="cro-kpi-card cro-kpi-card--revenue">
			<div class="cro-kpi-icon"><?php echo CRO_Icons::svg( 'dollar-sign', array( 'class' => 'cro-ico' ) ); ?></div>
			<div class="cro-kpi-content">
				<span class="cro-kpi-value"><?php echo wp_kses_post( $summary['revenue_formatted'] ); ?></span>
				<span class="cro-kpi-label"><?php esc_html_e( 'Revenue influenced', 'meyvora-convert' ); ?></span>
				<?php echo meyvora_kpi_change( $summary['revenue'], $summary['prev_revenue'] ); ?>
			</div>
		</div>

		<div class="cro-kpi-card">
			<div class="cro-kpi-icon"><?php echo CRO_Icons::svg( 'shopping-cart', array( 'class' => 'cro-ico' ) ); ?></div>
			<div class="cro-kpi-content">
				<span class="cro-kpi-value"><?php echo esc_html( number_format_i18n( $summary['sticky_cart_adds'] ) ); ?></span>
				<span class="cro-kpi-label"><?php esc_html_e( 'Add-to-cart from sticky', 'meyvora-convert' ); ?></span>
			</div>
		</div>

		<div class="cro-kpi-card">
			<div class="cro-kpi-icon"><?php echo CRO_Icons::svg( 'truck', array( 'class' => 'cro-ico' ) ); ?></div>
			<div class="cro-kpi-content">
				<span class="cro-kpi-value"><?php echo esc_html( number_format_i18n( $summary['shipping_bar_interactions'] ) ); ?></span>
				<span class="cro-kpi-label"><?php esc_html_e( 'Shipping bar interactions', 'meyvora-convert' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Secondary stats -->
	<div class="cro-stats-grid">
		<div class="cro-stat-card">
			<div class="cro-stat-icon"><?php echo CRO_Icons::svg( 'target', array( 'class' => 'cro-ico' ) ); ?></div>
			<div class="cro-stat-content">
				<span class="cro-stat-value"><?php echo esc_html( number_format_i18n( $summary['conversions'] ) ); ?></span>
				<span class="cro-stat-label"><?php esc_html_e( 'Conversions', 'meyvora-convert' ); ?></span>
				<?php echo meyvora_kpi_change( $summary['conversions'], $summary['prev_conversions'] ); ?>
			</div>
		</div>
		<div class="cro-stat-card">
			<div class="cro-stat-icon"><?php echo CRO_Icons::svg( 'chart', array( 'class' => 'cro-ico' ) ); ?></div>
			<div class="cro-stat-content">
				<span class="cro-stat-value"><?php echo esc_html( $summary['conversion_rate'] ); ?>%</span>
				<span class="cro-stat-label"><?php esc_html_e( 'Conversion Rate', 'meyvora-convert' ); ?></span>
			</div>
		</div>
		<div class="cro-stat-card">
			<div class="cro-stat-icon"><?php echo CRO_Icons::svg( 'mail', array( 'class' => 'cro-ico' ) ); ?></div>
			<div class="cro-stat-content">
				<span class="cro-stat-value"><?php echo esc_html( number_format_i18n( $summary['emails'] ) ); ?></span>
				<span class="cro-stat-label"><?php esc_html_e( 'Emails Captured', 'meyvora-convert' ); ?></span>
				<?php echo meyvora_kpi_change( $summary['emails'], $summary['prev_emails'] ); ?>
			</div>
		</div>
		<div class="cro-stat-card">
			<div class="cro-stat-icon"><?php echo CRO_Icons::svg( 'dollar-sign', array( 'class' => 'cro-ico' ) ); ?></div>
			<div class="cro-stat-content">
				<span class="cro-stat-value"><?php echo wp_kses_post( $summary['rpv_formatted'] ); ?></span>
				<span class="cro-stat-label"><?php esc_html_e( 'Revenue per Visitor', 'meyvora-convert' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Charts Row -->
	<div class="cro-charts-row">
		<div class="cro-chart-card cro-chart--main">
			<div class="cro-chart-header">
				<h3><?php esc_html_e( 'Performance Over Time', 'meyvora-convert' ); ?></h3>
				<div class="cro-chart-toggle">
					<button type="button" class="active" data-metric="conversions"><?php esc_html_e( 'Conversions', 'meyvora-convert' ); ?></button>
					<button type="button" data-metric="revenue"><?php esc_html_e( 'Revenue', 'meyvora-convert' ); ?></button>
					<button type="button" data-metric="impressions"><?php esc_html_e( 'Impressions', 'meyvora-convert' ); ?></button>
				</div>
			</div>
			<div class="cro-chart-body">
				<canvas id="cro-main-chart" height="300"></canvas>
			</div>
		</div>
		<div class="cro-chart-card cro-chart--device">
			<div class="cro-chart-header">
				<h3><?php esc_html_e( 'By Device', 'meyvora-convert' ); ?></h3>
			</div>
			<div class="cro-chart-body">
				<canvas id="cro-device-chart" height="200"></canvas>
			</div>
		</div>
	</div>

	<!-- Tables Row -->
	<div class="cro-tables-row">
		<div class="cro-table-card">
			<div class="cro-table-header">
				<h3><?php esc_html_e( 'Campaign Performance', 'meyvora-convert' ); ?></h3>
			</div>
			<div class="cro-table-wrap">
			<table class="cro-table widefat">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Campaign', 'meyvora-convert' ); ?></th>
						<th class="cro-table-num"><?php esc_html_e( 'Impressions', 'meyvora-convert' ); ?></th>
						<th class="cro-table-num"><?php esc_html_e( 'Conversions', 'meyvora-convert' ); ?></th>
						<th class="cro-table-num"><?php esc_html_e( 'Conv. Rate', 'meyvora-convert' ); ?></th>
						<th class="cro-table-num"><?php esc_html_e( 'Revenue', 'meyvora-convert' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $campaigns ) ) : ?>
					<tr class="cro-table-empty-row">
						<td colspan="5" class="cro-no-data"><?php esc_html_e( 'No data yet', 'meyvora-convert' ); ?></td>
					</tr>
					<?php else : ?>
					<?php foreach ( $campaigns as $campaign ) : ?>
					<?php $rate = $campaign['impressions'] > 0 ? round( ( $campaign['conversions'] / $campaign['impressions'] ) * 100, 2 ) : 0; ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $campaign['name'] ); ?></strong>
							<span class="cro-status cro-status--<?php echo esc_attr( $campaign['status'] ); ?>"><?php echo esc_html( $campaign['status'] ); ?></span>
						</td>
						<td class="cro-table-num"><?php echo esc_html( number_format_i18n( $campaign['impressions'] ) ); ?></td>
						<td class="cro-table-num"><?php echo esc_html( number_format_i18n( $campaign['conversions'] ) ); ?></td>
						<td class="cro-table-num"><?php echo esc_html( $rate ); ?>%</td>
						<td class="cro-table-num"><?php echo function_exists( 'wc_price' ) ? wc_price( $campaign['revenue'] ) : esc_html( $campaign['revenue'] ); ?></td>
					</tr>
					<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			</div>
		</div>
		<div class="cro-table-card">
			<div class="cro-table-header">
				<h3><?php esc_html_e( 'Top Converting Pages', 'meyvora-convert' ); ?></h3>
			</div>
			<div class="cro-table-wrap">
			<table class="cro-table widefat">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Page', 'meyvora-convert' ); ?></th>
						<th class="cro-table-num"><?php esc_html_e( 'Conversions', 'meyvora-convert' ); ?></th>
						<th class="cro-table-num"><?php esc_html_e( 'Revenue', 'meyvora-convert' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $top_pages ) ) : ?>
					<tr class="cro-table-empty-row">
						<td colspan="3" class="cro-no-data"><?php esc_html_e( 'No data yet', 'meyvora-convert' ); ?></td>
					</tr>
					<?php else : ?>
					<?php foreach ( $top_pages as $page ) : ?>
					<tr>
						<td class="cro-page-url" title="<?php echo esc_attr( $page['page_url'] ); ?>">
							<?php echo esc_html( wp_parse_url( $page['page_url'], PHP_URL_PATH ) ?: '/' ); ?>
						</td>
						<td class="cro-table-num"><?php echo esc_html( number_format_i18n( $page['conversions'] ) ); ?></td>
						<td class="cro-table-num"><?php echo function_exists( 'wc_price' ) ? wc_price( $page['revenue'] ) : esc_html( $page['revenue'] ); ?></td>
					</tr>
					<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			</div>
		</div>
	</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var dailyData = <?php echo wp_json_encode( $daily_stats ); ?>;
	var deviceData = <?php echo wp_json_encode( $devices ); ?>;

	var mainCtx = document.getElementById('cro-main-chart').getContext('2d');
	var mainChart = new Chart(mainCtx, {
		type: 'line',
		data: {
			labels: dailyData.map(function(d) { return d.label; }),
			datasets: [{
				label: '<?php echo esc_js( __( 'Conversions', 'meyvora-convert' ) ); ?>',
				data: dailyData.map(function(d) { return d.conversions; }),
				borderColor: '#333',
				backgroundColor: 'rgba(0, 0, 0, 0.08)',
				fill: true,
				tension: 0.3,
			}]
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			plugins: { legend: { display: false } },
			scales: { y: { beginAtZero: true } }
		}
	});

	document.querySelectorAll('.cro-chart-toggle button').forEach(function(btn) {
		btn.addEventListener('click', function() {
			document.querySelectorAll('.cro-chart-toggle button').forEach(function(b) { b.classList.remove('active'); });
			this.classList.add('active');
			var metric = this.dataset.metric;
			var colors = { conversions: '#333', revenue: '#555', impressions: '#888' };
			mainChart.data.datasets[0].data = dailyData.map(function(d) { return d[metric]; });
			mainChart.data.datasets[0].borderColor = colors[metric];
			mainChart.data.datasets[0].backgroundColor = colors[metric] + '20';
			mainChart.data.datasets[0].label = this.textContent;
			mainChart.update();
		});
	});

	var deviceCtx = document.getElementById('cro-device-chart').getContext('2d');
	new Chart(deviceCtx, {
		type: 'doughnut',
		data: {
			labels: deviceData.map(function(d) { return d.device.charAt(0).toUpperCase() + d.device.slice(1); }),
			datasets: [{ data: deviceData.map(function(d) { return d.conversions; }), backgroundColor: ['#333', '#555', '#888', '#aaa'] }]
		},
		options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
	});

	document.querySelectorAll('.cro-date-presets button').forEach(function(btn) {
		btn.addEventListener('click', function() {
			document.querySelector('input[name="from"]').value = this.dataset.from;
			document.querySelector('input[name="to"]').value = this.dataset.to;
			document.querySelector('.cro-date-form').submit();
		});
	});
});
</script>
