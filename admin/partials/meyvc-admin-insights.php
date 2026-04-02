<?php
/**
 * Admin partial: Insights — period KPIs, cards, campaign table, heatmap, funnel, abandoned stats, attribution, AI.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$days = MEYVC_Security::get_query_var_absint( 'meyvc_insights_days' );
if ( ! $days ) {
	$days = 30;
}
$days = $days >= 7 && $days <= 90 ? $days : 30;

$insights_class = class_exists( 'MEYVC_Insights' );

$comparison  = $insights_class ? MEYVC_Insights::get_period_comparison( $days ) : array();
$funnel      = $insights_class ? MEYVC_Insights::get_funnel_data( $days ) : array();
$heatmap     = $insights_class ? MEYVC_Insights::get_hourly_heatmap( $days ) : array();
$campaigns   = $insights_class ? MEYVC_Insights::get_campaign_comparison( $days ) : array();
$ab_stats    = $insights_class ? MEYVC_Insights::get_abandoned_cart_stats( $days ) : array();
$insights    = $insights_class ? MEYVC_Insights::get_insights( $days ) : array();
$attribution = $insights_class ? MEYVC_Insights::get_attribution() : null;

$heatmap_max = 1;
foreach ( $heatmap as $r ) {
	foreach ( $r as $c ) {
		$heatmap_max = max( $heatmap_max, (int) $c );
	}
}

$export_max_days     = (int) apply_filters( 'meyvc_export_max_days', 90 );
$export_default_days = 30;
$export_to           = MEYVC_Security::get_query_var( 'meyvc_export_to' );
$export_to           = $export_to !== '' ? $export_to : gmdate( 'Y-m-d' );
$export_from         = MEYVC_Security::get_query_var( 'meyvc_export_from' );
$export_from         = $export_from !== '' ? $export_from : gmdate( 'Y-m-d', strtotime( "-{$export_default_days} days" ) );
$ts_from             = strtotime( $export_from );
$ts_to               = strtotime( $export_to );
if ( false === $ts_from || false === $ts_to || $ts_to < $ts_from ) {
	$export_from = gmdate( 'Y-m-d', strtotime( "-{$export_default_days} days" ) );
	$export_to   = gmdate( 'Y-m-d' );
}
$export_base = array(
	'page'     => 'meyvc-analytics',
	'action'   => 'export',
	'from'     => $export_from,
	'to'       => $export_to,
	'_wpnonce' => wp_create_nonce( 'meyvc_export' ),
);
$export_url_events = add_query_arg( array_merge( $export_base, array( 'format' => 'events' ) ), admin_url( 'admin.php' ) );
$export_url_daily  = add_query_arg( array_merge( $export_base, array( 'format' => 'daily' ) ), admin_url( 'admin.php' ) );

/**
 * @param float|null $chg Change percent.
 * @return array{text: string, class: string}
 */
$insights_fmt_change = static function ( $chg ) {
	if ( null === $chg ) {
		return array(
			'text'  => '—',
			'class' => 'meyvc-period-kpi__change--neutral',
		);
	}
	$up    = (float) $chg >= 0;
	$arrow = $up ? '↑' : '↓';
	return array(
		'text'  => $arrow . ' ' . number_format_i18n( abs( (float) $chg ), 1 ) . '%',
		'class' => $up ? 'meyvc-period-kpi__change--up' : 'meyvc-period-kpi__change--down',
	);
};

$s1 = isset( $funnel['sessions_with_impression'] ) ? (int) $funnel['sessions_with_impression'] : 0;
$s2 = isset( $funnel['popup_clicks'] ) ? (int) $funnel['popup_clicks'] : 0;
$s3 = isset( $funnel['emails_captured'] ) ? (int) $funnel['emails_captured'] : 0;
$s4 = isset( $funnel['orders'] ) ? (int) $funnel['orders'] : 0;

$funnel_pct = static function ( $cur, $prev ) {
	if ( $prev <= 0 ) {
		return null;
	}
	return round( ( $cur / $prev ) * 100, 1 );
};
$funnel_drop = static function ( $cur, $prev ) {
	if ( $prev <= 0 ) {
		return null;
	}
	return round( ( 1 - ( $cur / $prev ) ) * 100, 1 );
};

$day_labels = array(
	__( 'Mon', 'meyvora-convert' ),
	__( 'Tue', 'meyvora-convert' ),
	__( 'Wed', 'meyvora-convert' ),
	__( 'Thu', 'meyvora-convert' ),
	__( 'Fri', 'meyvora-convert' ),
	__( 'Sat', 'meyvora-convert' ),
	__( 'Sun', 'meyvora-convert' ),
);

$icon_for_type = static function ( $type ) {
	$map = array(
		'top'             => 'trophy',
		'underperforming' => 'alert',
		'action'          => 'zap',
		'warning'         => 'alert',
		'opportunity'     => 'sparkles',
	);
	$name = isset( $map[ $type ] ) ? $map[ $type ] : 'info';
	return class_exists( 'MEYVC_Icons' ) ? MEYVC_Icons::svg( $name, array( 'class' => 'meyvc-insight-card__icon-svg' ) ) : '';
};
?>

<div class="meyvc-insights-page">
	<div class="meyvc-insights-toolbar">
		<p class="meyvc-insights-toolbar__label"><?php esc_html_e( 'Period', 'meyvora-convert' ); ?></p>
		<div class="meyvc-insights-toolbar__presets">
			<?php foreach ( array( 7 => __( '7 days', 'meyvora-convert' ), 30 => __( '30 days', 'meyvora-convert' ), 90 => __( '90 days', 'meyvora-convert' ) ) as $d => $label ) : ?>
				<a class="button button-small <?php echo $days === $d ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'meyvc_insights_days', $d, admin_url( 'admin.php?page=meyvc-insights' ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</div>
	</div>

	<section class="meyvc-insights-section" aria-labelledby="meyvc-insights-period-heading">
		<h2 id="meyvc-insights-period-heading" class="meyvc-insights-section__title"><?php esc_html_e( 'Period comparison', 'meyvora-convert' ); ?></h2>
		<div class="meyvc-period-kpi__row">
			<?php
			$kpi_defs = array(
				'impressions'   => array( 'label' => __( 'Impressions', 'meyvora-convert' ), 'format' => 'int' ),
				'conversions'   => array( 'label' => __( 'Conversions', 'meyvora-convert' ), 'format' => 'int' ),
				'revenue'       => array( 'label' => __( 'Revenue', 'meyvora-convert' ), 'format' => 'money' ),
				'emails'        => array( 'label' => __( 'Emails captured', 'meyvora-convert' ), 'format' => 'int' ),
				'recovery_rate' => array( 'label' => __( 'Cart recovery rate', 'meyvora-convert' ), 'format' => 'pct' ),
			);
			foreach ( $kpi_defs as $key => $def ) :
				$row = isset( $comparison[ $key ] ) ? $comparison[ $key ] : array( 'current' => 0, 'previous' => 0, 'change_pct' => null );
				$cur = $row['current'];
				$prv = $row['previous'];
				$chg = $insights_fmt_change( isset( $row['change_pct'] ) ? $row['change_pct'] : null );
				if ( 'money' === $def['format'] && function_exists( 'wc_price' ) ) {
					$cur_out = wp_kses_post( wc_price( (float) $cur ) );
					$prv_out = wp_kses_post( wc_price( (float) $prv ) );
				} elseif ( 'pct' === $def['format'] ) {
					$cur_out = esc_html( number_format_i18n( (float) $cur, 2 ) . '%' );
					$prv_out = esc_html( number_format_i18n( (float) $prv, 2 ) . '%' );
				} else {
					$cur_out = esc_html( number_format_i18n( (int) $cur ) );
					$prv_out = esc_html( number_format_i18n( (int) $prv ) );
				}
				?>
				<div class="meyvc-period-kpi">
					<span class="meyvc-period-kpi__name"><?php echo esc_html( $def['label'] ); ?></span>
					<span class="meyvc-period-kpi__current"><?php echo wp_kses_post( $cur_out ); ?></span>
					<span class="meyvc-period-kpi__previous"><?php esc_html_e( 'Previous:', 'meyvora-convert' ); ?> <?php echo wp_kses_post( $prv_out ); ?></span>
					<span class="meyvc-period-kpi__change <?php echo esc_attr( $chg['class'] ); ?>"><?php echo esc_html( $chg['text'] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="meyvc-insights-section" aria-labelledby="meyvc-insights-cards-heading">
		<h2 id="meyvc-insights-cards-heading" class="meyvc-insights-section__title"><?php esc_html_e( 'Insight cards', 'meyvora-convert' ); ?></h2>
		<?php if ( empty( $insights ) ) : ?>
			<p class="meyvc-insights-empty"><?php esc_html_e( 'No rule-based insights match your data for this period yet.', 'meyvora-convert' ); ?></p>
		<?php else : ?>
			<ul class="meyvc-insight-grid">
				<?php foreach ( $insights as $item ) : ?>
					<?php
					$pri = isset( $item['priority'] ) ? (int) $item['priority'] : 3;
					$cls = 'meyvc-insight-card meyvc-insight-card--type-' . sanitize_html_class( $item['type'] ?? 'action' );
					if ( 1 === $pri ) {
						$cls .= ' meyvc-insight-card--priority-high';
					}
					?>
					<li class="<?php echo esc_attr( $cls ); ?>">
						<div class="meyvc-insight-card__head">
							<span class="meyvc-insight-card__icon" aria-hidden="true"><?php echo class_exists( 'MEYVC_Icons' ) ? wp_kses( $icon_for_type( $item['type'] ?? 'action' ), MEYVC_Icons::get_svg_kses_allowed() ) : ''; ?></span>
							<span class="meyvc-insight-card__category"><?php echo esc_html( $item['category'] ?? '' ); ?></span>
							<span class="meyvc-insight-card__priority" title="<?php esc_attr_e( 'Priority (1 = highest)', 'meyvora-convert' ); ?>">P<?php echo esc_html( (string) $pri ); ?></span>
						</div>
						<h3 class="meyvc-insight-card__title"><?php echo esc_html( $item['title'] ?? '' ); ?></h3>
						<p class="meyvc-insight-card__desc"><?php echo esc_html( $item['description'] ?? '' ); ?></p>
						<?php if ( ! empty( $item['metric'] ) || ! empty( $item['metric_label'] ) ) : ?>
							<p class="meyvc-insight-card__metric">
								<span class="meyvc-insight-card__metric-badge"><?php echo esc_html( $item['metric'] ?? '' ); ?></span>
								<?php if ( ! empty( $item['metric_label'] ) ) : ?>
									<span class="meyvc-insight-card__metric-label"><?php echo esc_html( $item['metric_label'] ); ?></span>
								<?php endif; ?>
							</p>
						<?php endif; ?>
						<?php if ( ! empty( $item['fix_url'] ) ) : ?>
							<p class="meyvc-insight-card__cta">
								<a class="button button-primary" href="<?php echo esc_url( $item['fix_url'] ); ?>"><?php echo esc_html( ( $item['fix_label'] ?? __( 'Fix', 'meyvora-convert' ) ) . ' →' ); ?></a>
							</p>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>

	<section class="meyvc-insights-section" aria-labelledby="meyvc-insights-campaign-heading">
		<h2 id="meyvc-insights-campaign-heading" class="meyvc-insights-section__title"><?php esc_html_e( 'Campaign comparison', 'meyvora-convert' ); ?></h2>
		<?php if ( empty( $campaigns ) ) : ?>
			<p class="meyvc-insights-empty"><?php esc_html_e( 'No campaign data yet for this period.', 'meyvora-convert' ); ?></p>
		<?php else : ?>
			<div class="meyvc-insights-table-wrap">
				<table class="meyvc-insights-table js-meyvc-campaign-compare">
					<thead>
						<tr>
							<th data-sort-key="name" data-sort-type="text" class="meyvc-insights-table__sortable"><?php esc_html_e( 'Campaign', 'meyvora-convert' ); ?></th>
							<th data-sort-key="imp" data-sort-type="number" class="meyvc-insights-table__sortable"><?php esc_html_e( 'Impressions', 'meyvora-convert' ); ?></th>
							<th data-sort-key="conv" data-sort-type="number" class="meyvc-insights-table__sortable"><?php esc_html_e( 'Conversions', 'meyvora-convert' ); ?></th>
							<th data-sort-key="rate" data-sort-type="number" class="meyvc-insights-table__sortable"><?php esc_html_e( 'Rate', 'meyvora-convert' ); ?></th>
							<th data-sort-key="rev" data-sort-type="number" class="meyvc-insights-table__sortable"><?php esc_html_e( 'Revenue', 'meyvora-convert' ); ?></th>
							<th data-sort-key="em" data-sort-type="number" class="meyvc-insights-table__sortable"><?php esc_html_e( 'Emails', 'meyvora-convert' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $campaigns as $c ) : ?>
							<tr>
								<td data-sort="name" data-sort-raw="<?php echo esc_attr( strtolower( $c['name'] ?? '' ) ); ?>"><?php echo esc_html( $c['name'] ?? '' ); ?></td>
								<td data-sort="imp" data-sort-raw="<?php echo esc_attr( (string) (int) ( $c['impressions'] ?? 0 ) ); ?>"><?php echo esc_html( number_format_i18n( (int) ( $c['impressions'] ?? 0 ) ) ); ?></td>
								<td data-sort="conv" data-sort-raw="<?php echo esc_attr( (string) (int) ( $c['conversions'] ?? 0 ) ); ?>"><?php echo esc_html( number_format_i18n( (int) ( $c['conversions'] ?? 0 ) ) ); ?></td>
								<td data-sort="rate" data-sort-raw="<?php echo esc_attr( (string) (float) ( $c['conversion_rate'] ?? 0 ) ); ?>"><?php echo esc_html( number_format_i18n( (float) ( $c['conversion_rate'] ?? 0 ), 2 ) . '%' ); ?></td>
								<td data-sort="rev" data-sort-raw="<?php echo esc_attr( (string) (float) ( $c['revenue_attributed'] ?? 0 ) ); ?>"><?php echo function_exists( 'wc_price' ) ? wp_kses_post( wc_price( (float) ( $c['revenue_attributed'] ?? 0 ) ) ) : esc_html( (string) (float) ( $c['revenue_attributed'] ?? 0 ) ); ?></td>
								<td data-sort="em" data-sort-raw="<?php echo esc_attr( (string) (int) ( $c['emails_captured'] ?? 0 ) ); ?>"><?php echo esc_html( number_format_i18n( (int) ( $c['emails_captured'] ?? 0 ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="meyvc-insights-hint"><?php esc_html_e( 'Click a column header to sort.', 'meyvora-convert' ); ?></p>
		<?php endif; ?>
	</section>

	<section class="meyvc-insights-section" aria-labelledby="meyvc-insights-heatmap-heading">
		<h2 id="meyvc-insights-heatmap-heading" class="meyvc-insights-section__title"><?php esc_html_e( 'Conversion heatmap', 'meyvora-convert' ); ?></h2>
		<p class="meyvc-insights-muted"><?php esc_html_e( 'Conversions by weekday and hour (store timezone).', 'meyvora-convert' ); ?></p>
		<div class="meyvc-heatmap" role="grid" aria-label="<?php esc_attr_e( 'Conversions by day and hour', 'meyvora-convert' ); ?>">
			<div class="meyvc-heatmap__grid">
				<div class="meyvc-heatmap__corner" aria-hidden="true"></div>
				<?php for ( $h = 0; $h < 24; $h++ ) : ?>
					<div class="meyvc-heatmap__colhead" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $h ) ); ?></div>
				<?php endfor; ?>
				<?php for ( $d = 0; $d < 7; $d++ ) : ?>
					<div class="meyvc-heatmap__rowlabel"><?php echo esc_html( $day_labels[ $d ] ); ?></div>
					<?php for ( $h = 0; $h < 24; $h++ ) : ?>
						<?php
						$cnt = isset( $heatmap[ $d ][ $h ] ) ? (int) $heatmap[ $d ][ $h ] : 0;
						$op  = $heatmap_max > 0 && $cnt > 0 ? round( $cnt / $heatmap_max, 3 ) : 0;
						$bg  = $cnt > 0 ? 'rgba(26,115,232,' . (string) $op . ')' : '#ffffff';
						$tip = sprintf(
							/* translators: 1: weekday, 2: hour, 3: count */
							__( '%1$s %2$s:00 — %3$s conversions', 'meyvora-convert' ),
							$day_labels[ $d ],
							sprintf( '%02d', $h ),
							number_format_i18n( $cnt )
						);
						?>
						<div class="meyvc-heatmap__cell" role="gridcell" style="background-color: <?php echo esc_attr( $bg ); ?>;" title="<?php echo esc_attr( $tip ); ?>"><?php echo $cnt > 0 ? esc_html( (string) $cnt ) : ''; ?></div>
					<?php endfor; ?>
				<?php endfor; ?>
			</div>
		</div>
	</section>

	<section class="meyvc-insights-section" aria-labelledby="meyvc-insights-funnel-heading">
		<h2 id="meyvc-insights-funnel-heading" class="meyvc-insights-section__title"><?php esc_html_e( 'Popup funnel', 'meyvora-convert' ); ?></h2>
		<p class="meyvc-insights-muted"><?php esc_html_e( 'Campaign impressions → clicks → emails on conversion → orders linked to conversions.', 'meyvora-convert' ); ?></p>
		<div class="meyvc-funnel" role="list">
			<?php
			$steps = array(
				array(
					'key'   => 'imp',
					'label' => __( 'Impressions', 'meyvora-convert' ),
					'count' => $s1,
					'prev'  => null,
					'icon'  => 'eye',
				),
				array(
					'key'   => 'clk',
					'label' => __( 'Clicks', 'meyvora-convert' ),
					'count' => $s2,
					'prev'  => $s1,
					'icon'  => 'pointer',
				),
				array(
					'key'   => 'em',
					'label' => __( 'Emails', 'meyvora-convert' ),
					'count' => $s3,
					'prev'  => $s2,
					'icon'  => 'mail',
				),
				array(
					'key'   => 'ord',
					'label' => __( 'Orders', 'meyvora-convert' ),
					'count' => $s4,
					'prev'  => $s3,
					'icon'  => 'shopping-cart',
				),
			);
			foreach ( $steps as $i => $st ) :
				$pct_prev = null === $st['prev'] ? null : $funnel_pct( $st['count'], $st['prev'] );
				$drop     = null === $st['prev'] ? null : $funnel_drop( $st['count'], $st['prev'] );
				$icon_svg = class_exists( 'MEYVC_Icons' ) ? MEYVC_Icons::svg( $st['icon'], array( 'class' => 'meyvc-funnel__icon-svg' ) ) : '';
				?>
				<?php if ( $i > 0 && null !== $drop ) : ?>
					<div class="meyvc-funnel__drop" role="presentation">
						<span class="meyvc-funnel__drop-arrow">↓</span>
						<span class="meyvc-funnel__drop-text">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: percentage */
									__( '%s%% drop-off', 'meyvora-convert' ),
									number_format_i18n( $drop, 1 )
								)
							);
							?>
						</span>
					</div>
				<?php endif; ?>
				<div class="meyvc-funnel__step" role="listitem">
					<span class="meyvc-funnel__icon" aria-hidden="true"><?php echo class_exists( 'MEYVC_Icons' ) ? wp_kses( $icon_svg, MEYVC_Icons::get_svg_kses_allowed() ) : ''; ?></span>
					<span class="meyvc-funnel__label"><?php echo esc_html( $st['label'] ); ?></span>
					<span class="meyvc-funnel__count"><?php echo esc_html( number_format_i18n( $st['count'] ) ); ?></span>
					<?php if ( null !== $pct_prev ) : ?>
						<span class="meyvc-funnel__pct">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: percent of previous step */
									__( '%s%% of previous', 'meyvora-convert' ),
									number_format_i18n( (float) $pct_prev, 1 )
								)
							);
							?>
						</span>
					<?php else : ?>
						<span class="meyvc-funnel__pct meyvc-funnel__pct--muted">—</span>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="meyvc-insights-section" aria-labelledby="meyvc-insights-ab-heading">
		<h2 id="meyvc-insights-ab-heading" class="meyvc-insights-section__title"><?php esc_html_e( 'Abandoned carts', 'meyvora-convert' ); ?></h2>
		<div class="meyvc-ab-grid">
			<div class="meyvc-ab-panel">
				<h3 class="meyvc-ab-panel__title"><?php esc_html_e( 'Summary', 'meyvora-convert' ); ?></h3>
				<ul class="meyvc-ab-summary">
					<li><span><?php esc_html_e( 'Abandoned (period)', 'meyvora-convert' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) ( $ab_stats['total_abandoned'] ?? 0 ) ) ); ?></strong></li>
					<li><span><?php esc_html_e( 'Recovered (period)', 'meyvora-convert' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) ( $ab_stats['total_recovered'] ?? 0 ) ) ); ?></strong></li>
					<li><span><?php esc_html_e( 'Recovery rate', 'meyvora-convert' ); ?></span><strong><?php echo esc_html( number_format_i18n( (float) ( $ab_stats['recovery_rate'] ?? 0 ), 2 ) . '%' ); ?></strong></li>
					<li><span><?php esc_html_e( 'Avg cart value', 'meyvora-convert' ); ?></span><strong><?php echo function_exists( 'wc_price' ) ? wp_kses_post( wc_price( (float) ( $ab_stats['avg_value'] ?? 0 ) ) ) : esc_html( (string) (float) ( $ab_stats['avg_value'] ?? 0 ) ); ?></strong></li>
				</ul>
			</div>
			<div class="meyvc-ab-panel">
				<h3 class="meyvc-ab-panel__title"><?php esc_html_e( 'By cart value', 'meyvora-convert' ); ?></h3>
				<table class="meyvc-insights-table meyvc-insights-table--compact">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Band', 'meyvora-convert' ); ?></th>
							<th><?php esc_html_e( 'Carts', 'meyvora-convert' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$bands = isset( $ab_stats['by_value_band'] ) && is_array( $ab_stats['by_value_band'] ) ? $ab_stats['by_value_band'] : array();
						$labels = array(
							'0-50'    => '$0–50',
							'50-100'  => '$50–100',
							'100-200' => '$100–200',
							'200+'    => '$200+',
						);
						foreach ( $labels as $bk => $bl ) :
							$bv = isset( $bands[ $bk ] ) ? (int) $bands[ $bk ] : 0;
							?>
							<tr>
								<td><?php echo esc_html( $bl ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $bv ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<div class="meyvc-ab-panel">
				<h3 class="meyvc-ab-panel__title"><?php esc_html_e( 'Recovery by email step', 'meyvora-convert' ); ?></h3>
				<table class="meyvc-insights-table meyvc-insights-table--compact">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Email', 'meyvora-convert' ); ?></th>
							<th><?php esc_html_e( 'Recovery rate', 'meyvora-convert' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( array( 1, 2, 3 ) as $ei ) :
							$rk = 'email_' . $ei . '_recovery_rate';
							$rv = isset( $ab_stats[ $rk ] ) ? $ab_stats[ $rk ] : null;
							?>
							<tr>
								<td><?php echo esc_html( sprintf( /* translators: %d: email sequence number */ __( 'Email %d', 'meyvora-convert' ), $ei ) ); ?></td>
								<td><?php echo null === $rv ? '—' : esc_html( number_format_i18n( (float) $rv, 2 ) . '%' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</section>

	<?php if ( $attribution !== null ) : ?>
	<div class="meyvc-card meyvc-attribution-block">
		<header class="meyvc-card__header">
			<h2 class="meyvc-card__title">
				<?php esc_html_e( 'Attribution', 'meyvora-convert' ); ?>
				<?php if ( class_exists( 'MEYVC_Attribution' ) ) : ?>
				<span class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: attribution model label */
							__( 'Model: %s ·', 'meyvora-convert' ),
							MEYVC_Attribution::get_model_label()
						)
					);
					?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=meyvc-analytics' ) ); ?>"><?php esc_html_e( 'Change in Analytics', 'meyvora-convert' ); ?></a>
				</span>
				<?php endif; ?>
			</h2>
			<p class="meyvc-card__subtitle meyvc-muted">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %1$d: attribution window length in days. */
						__( 'Last %1$d days', 'meyvora-convert' ),
						(int) ( $attribution['window_days'] ?? 7 )
					)
				);
				?>
			</p>
		</header>
		<div class="meyvc-card__body">
			<?php if ( ! empty( $attribution['not_enough_data'] ) ) : ?>
			<p class="meyvc-muted meyvc-attribution-not-enough"><?php esc_html_e( 'Not enough data yet.', 'meyvora-convert' ); ?></p>
			<?php else : ?>
			<div class="meyvc-attribution-totals">
				<span class="meyvc-attribution-total">
					<strong><?php echo esc_html( number_format_i18n( (int) ( $attribution['total_conversions'] ?? 0 ) ) ); ?></strong>
					<?php esc_html_e( 'Conversions', 'meyvora-convert' ); ?>
				</span>
				<span class="meyvc-attribution-total">
					<strong><?php echo esc_html( number_format_i18n( (int) ( $attribution['total_impressions'] ?? 0 ) ) ); ?></strong>
					<?php esc_html_e( 'Impressions', 'meyvora-convert' ); ?>
				</span>
			</div>
			<div class="meyvc-attribution-grid">
				<?php if ( ! empty( $attribution['top_campaigns'] ) ) : ?>
				<div class="meyvc-attribution-col">
					<h3 class="meyvc-attribution-col__title"><?php esc_html_e( 'Top campaigns', 'meyvora-convert' ); ?></h3>
					<ol class="meyvc-attribution-list">
						<?php foreach ( $attribution['top_campaigns'] as $item ) : ?>
						<li>
							<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a>
							<span class="meyvc-attribution-count"><?php echo esc_html( number_format_i18n( $item['conversions'] ) ); ?></span>
						</li>
						<?php endforeach; ?>
					</ol>
				</div>
				<?php endif; ?>
				<?php if ( ! empty( $attribution['top_offers'] ) ) : ?>
				<div class="meyvc-attribution-col">
					<h3 class="meyvc-attribution-col__title"><?php esc_html_e( 'Top offers', 'meyvora-convert' ); ?></h3>
					<ol class="meyvc-attribution-list">
						<?php foreach ( $attribution['top_offers'] as $item ) : ?>
						<li>
							<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a>
							<span class="meyvc-attribution-count">
								<?php
								echo esc_html( number_format_i18n( $item['conversions'] ?? 0 ) );
								esc_html_e( ' conversions', 'meyvora-convert' );
								if ( ! empty( $item['applies'] ) ) {
									echo ' · ';
									echo esc_html( number_format_i18n( $item['applies'] ) );
									esc_html_e( ' applies', 'meyvora-convert' );
									if ( isset( $item['rate'] ) && null !== $item['rate'] ) {
										echo ' (' . esc_html( number_format_i18n( $item['rate'] ) ) . '% ' . esc_html__( 'apply→convert', 'meyvora-convert' ) . ')';
									}
								}
								?>
							</span>
						</li>
						<?php endforeach; ?>
					</ol>
				</div>
				<?php endif; ?>
				<?php if ( ! empty( $attribution['top_ab_tests'] ) ) : ?>
				<div class="meyvc-attribution-col">
					<h3 class="meyvc-attribution-col__title"><?php esc_html_e( 'Top A/B tests', 'meyvora-convert' ); ?></h3>
					<ol class="meyvc-attribution-list">
						<?php foreach ( $attribution['top_ab_tests'] as $item ) : ?>
						<li>
							<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a>
							<span class="meyvc-attribution-count"><?php echo esc_html( number_format_i18n( $item['conversions'] ) ); ?></span>
						</li>
						<?php endforeach; ?>
					</ol>
				</div>
				<?php endif; ?>
			</div>
			<?php if ( empty( $attribution['top_campaigns'] ) && empty( $attribution['top_offers'] ) && empty( $attribution['top_ab_tests'] ) ) : ?>
			<p class="meyvc-muted"><?php esc_html_e( 'No attribution data yet. Conversions will appear here as you track campaigns, offers, and A/B tests.', 'meyvora-convert' ); ?></p>
			<?php endif; ?>
			<?php endif; ?>
			<div class="meyvc-attribution-export">
				<p class="meyvc-export-range-label"><?php esc_html_e( 'Export range', 'meyvora-convert' ); ?></p>
				<div class="meyvc-export-quick-range">
					<a href="<?php echo esc_url( add_query_arg( array( 'meyvc_export_from' => gmdate( 'Y-m-d', strtotime( '-7 days' ) ), 'meyvc_export_to' => gmdate( 'Y-m-d' ) ), admin_url( 'admin.php?page=meyvc-insights' ) ) ); ?>" class="button button-small"><?php esc_html_e( '7 days', 'meyvora-convert' ); ?></a>
					<a href="<?php echo esc_url( add_query_arg( array( 'meyvc_export_from' => gmdate( 'Y-m-d', strtotime( '-30 days' ) ), 'meyvc_export_to' => gmdate( 'Y-m-d' ) ), admin_url( 'admin.php?page=meyvc-insights' ) ) ); ?>" class="button button-small"><?php esc_html_e( '30 days', 'meyvora-convert' ); ?></a>
					<a href="<?php echo esc_url( add_query_arg( array( 'meyvc_export_from' => gmdate( 'Y-m-d', strtotime( '-90 days' ) ), 'meyvc_export_to' => gmdate( 'Y-m-d' ) ), admin_url( 'admin.php?page=meyvc-insights' ) ) ); ?>" class="button button-small"><?php esc_html_e( '90 days', 'meyvora-convert' ); ?></a>
				</div>
				<form method="get" class="meyvc-export-date-form" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
					<input type="hidden" name="page" value="meyvc-insights" />
					<label class="screen-reader-text" for="meyvc_export_from"><?php esc_html_e( 'From', 'meyvora-convert' ); ?></label>
					<input type="date" id="meyvc_export_from" name="meyvc_export_from" value="<?php echo esc_attr( $export_from ); ?>" />
					<span><?php esc_html_e( 'to', 'meyvora-convert' ); ?></span>
					<label class="screen-reader-text" for="meyvc_export_to"><?php esc_html_e( 'To', 'meyvora-convert' ); ?></label>
					<input type="date" id="meyvc_export_to" name="meyvc_export_to" value="<?php echo esc_attr( $export_to ); ?>" />
					<button type="submit" class="button button-small"><?php esc_html_e( 'Apply', 'meyvora-convert' ); ?></button>
				</form>
				<p class="meyvc-export-buttons">
					<a href="<?php echo esc_url( $export_url_events ); ?>" class="button"><?php esc_html_e( 'Export events CSV', 'meyvora-convert' ); ?></a>
					<a href="<?php echo esc_url( $export_url_daily ); ?>" class="button"><?php esc_html_e( 'Daily summary CSV', 'meyvora-convert' ); ?></a>
					<span class="meyvc-muted"><?php
						/* translators: %d: maximum export range in days. */
						echo esc_html( sprintf( __( 'Max %d days.', 'meyvora-convert' ), $export_max_days ) );
					?></span>
				</p>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<?php
	$ai_insights_ready = class_exists( 'MEYVC_AI_Client' ) && MEYVC_AI_Client::is_configured()
		&& function_exists( 'meyvc_settings' ) && 'yes' === meyvc_settings()->get( 'ai', 'feature_insights', 'yes' );
	?>
	<div id="meyvc-ai-insights-section" class="meyvc-card meyvc-insights-ai-card" data-days="<?php echo esc_attr( (string) $days ); ?>" data-ai-ready="<?php echo $ai_insights_ready ? '1' : '0'; ?>">
		<div class="meyvc-insights-ai-card__head">
			<h2 class="meyvc-card__title"><?php esc_html_e( '✦ AI Analysis', 'meyvora-convert' ); ?></h2>
			<div class="meyvc-insights-ai-card__actions">
				<button type="button" id="meyvc-ai-analyse-btn" class="button button-primary" <?php disabled( ! $ai_insights_ready ); ?>><?php esc_html_e( 'Analyse with AI', 'meyvora-convert' ); ?></button>
				<button type="button" id="meyvc-ai-refresh-btn" class="button" style="display:none"><?php esc_html_e( '↻ Refresh', 'meyvora-convert' ); ?></button>
				<span id="meyvc-ai-cache-note" class="description" style="display:none">
					<?php esc_html_e( 'Cached', 'meyvora-convert' ); ?>
					&middot;
					<a href="#" id="meyvc-ai-bust"><?php esc_html_e( 'Clear cache', 'meyvora-convert' ); ?></a>
				</span>
			</div>
		</div>
		<div class="meyvc-card__body">
			<?php if ( ! $ai_insights_ready ) : ?>
				<p class="description"><?php esc_html_e( 'Configure your API key and enable AI insights under Settings → AI.', 'meyvora-convert' ); ?></p>
			<?php endif; ?>
			<p id="meyvc-ai-insights-meta" class="description" style="display:none;margin:0 0 12px"></p>
			<div id="meyvc-ai-insights-output">
				<p class="description"><?php esc_html_e( 'Click “Analyse with AI” to generate insights from your data.', 'meyvora-convert' ); ?></p>
			</div>
		</div>
	</div>
</div>
