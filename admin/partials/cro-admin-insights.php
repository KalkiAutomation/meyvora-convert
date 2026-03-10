<?php
/**
 * Admin partial: Insights tab – actionable cards with Fix CTAs.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$days   = isset( $_GET['cro_insights_days'] ) ? absint( $_GET['cro_insights_days'] ) : 30;
$days   = $days >= 7 && $days <= 90 ? $days : 30;
$insights = class_exists( 'CRO_Insights' ) ? CRO_Insights::get_insights( $days ) : array();
$attribution = class_exists( 'CRO_Insights' ) ? CRO_Insights::get_attribution() : null;

$export_max_days    = (int) apply_filters( 'cro_export_max_days', 90 );
$export_default_days = 30;
$export_to          = isset( $_GET['cro_export_to'] ) ? sanitize_text_field( wp_unslash( $_GET['cro_export_to'] ) ) : gmdate( 'Y-m-d' );
$export_from        = isset( $_GET['cro_export_from'] ) ? sanitize_text_field( wp_unslash( $_GET['cro_export_from'] ) ) : gmdate( 'Y-m-d', strtotime( "-{$export_default_days} days" ) );
$ts_from            = strtotime( $export_from );
$ts_to              = strtotime( $export_to );
if ( $ts_from === false || $ts_to === false || $ts_to < $ts_from ) {
	$export_from = gmdate( 'Y-m-d', strtotime( "-{$export_default_days} days" ) );
	$export_to   = gmdate( 'Y-m-d' );
}
$export_base = array(
	'page'     => 'cro-analytics',
	'action'   => 'export',
	'from'     => $export_from,
	'to'       => $export_to,
	'_wpnonce' => wp_create_nonce( 'cro_export' ),
);
$export_url_events  = add_query_arg( array_merge( $export_base, array( 'format' => 'events' ) ), admin_url( 'admin.php' ) );
$export_url_daily   = add_query_arg( array_merge( $export_base, array( 'format' => 'daily' ) ), admin_url( 'admin.php' ) );
?>

<div class="cro-insights-page">
	<?php if ( $attribution !== null ) : ?>
	<div class="cro-card cro-attribution-block">
		<header class="cro-card__header">
			<h2 class="cro-card__title"><?php esc_html_e( 'Attribution', 'meyvora-convert' ); ?></h2>
			<p class="cro-card__subtitle cro-muted">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: number of days */
						__( 'Last %1$d days', 'meyvora-convert' ),
						$attribution['window_days']
					)
				);
				?>
			</p>
		</header>
		<div class="cro-card__body">
			<?php if ( ! empty( $attribution['not_enough_data'] ) ) : ?>
			<p class="cro-muted cro-attribution-not-enough"><?php esc_html_e( 'Not enough data yet.', 'meyvora-convert' ); ?></p>
			<?php else : ?>
			<div class="cro-attribution-totals">
				<span class="cro-attribution-total">
					<strong><?php echo esc_html( number_format_i18n( $attribution['total_conversions'] ) ); ?></strong>
					<?php esc_html_e( 'Conversions', 'meyvora-convert' ); ?>
				</span>
				<span class="cro-attribution-total">
					<strong><?php echo esc_html( number_format_i18n( $attribution['total_impressions'] ) ); ?></strong>
					<?php esc_html_e( 'Impressions', 'meyvora-convert' ); ?>
				</span>
			</div>
			<div class="cro-attribution-grid">
				<?php if ( ! empty( $attribution['top_campaigns'] ) ) : ?>
				<div class="cro-attribution-col">
					<h3 class="cro-attribution-col__title"><?php esc_html_e( 'Top campaigns', 'meyvora-convert' ); ?></h3>
					<ol class="cro-attribution-list">
						<?php foreach ( $attribution['top_campaigns'] as $i => $item ) : ?>
						<li>
							<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a>
							<span class="cro-attribution-count"><?php echo esc_html( number_format_i18n( $item['conversions'] ) ); ?></span>
						</li>
						<?php endforeach; ?>
					</ol>
				</div>
				<?php endif; ?>
				<?php if ( ! empty( $attribution['top_offers'] ) ) : ?>
				<div class="cro-attribution-col">
					<h3 class="cro-attribution-col__title"><?php esc_html_e( 'Top offers', 'meyvora-convert' ); ?></h3>
					<ol class="cro-attribution-list">
						<?php foreach ( $attribution['top_offers'] as $item ) : ?>
						<li>
							<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a>
							<span class="cro-attribution-count">
								<?php
								echo esc_html( number_format_i18n( $item['conversions'] ?? 0 ) );
								esc_html_e( ' conversions', 'meyvora-convert' );
								if ( ! empty( $item['applies'] ) ) {
									echo ' · ';
									echo esc_html( number_format_i18n( $item['applies'] ) );
									esc_html_e( ' applies', 'meyvora-convert' );
									if ( isset( $item['rate'] ) && $item['rate'] !== null ) {
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
				<div class="cro-attribution-col">
					<h3 class="cro-attribution-col__title"><?php esc_html_e( 'Top A/B tests', 'meyvora-convert' ); ?></h3>
					<ol class="cro-attribution-list">
						<?php foreach ( $attribution['top_ab_tests'] as $item ) : ?>
						<li>
							<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a>
							<span class="cro-attribution-count"><?php echo esc_html( number_format_i18n( $item['conversions'] ) ); ?></span>
						</li>
						<?php endforeach; ?>
					</ol>
				</div>
				<?php endif; ?>
			</div>
			<?php if ( empty( $attribution['top_campaigns'] ) && empty( $attribution['top_offers'] ) && empty( $attribution['top_ab_tests'] ) ) : ?>
			<p class="cro-muted"><?php esc_html_e( 'No attribution data yet. Conversions will appear here as you track campaigns, offers, and A/B tests.', 'meyvora-convert' ); ?></p>
			<?php endif; ?>
			<?php endif; ?>
			<div class="cro-attribution-export">
				<p class="cro-export-range-label"><?php esc_html_e( 'Export range', 'meyvora-convert' ); ?></p>
				<div class="cro-export-quick-range">
					<a href="<?php echo esc_url( add_query_arg( array( 'cro_export_from' => gmdate( 'Y-m-d', strtotime( '-7 days' ) ), 'cro_export_to' => gmdate( 'Y-m-d' ) ), admin_url( 'admin.php?page=cro-insights' ) ) ); ?>" class="button button-small"><?php esc_html_e( '7 days', 'meyvora-convert' ); ?></a>
					<a href="<?php echo esc_url( add_query_arg( array( 'cro_export_from' => gmdate( 'Y-m-d', strtotime( '-30 days' ) ), 'cro_export_to' => gmdate( 'Y-m-d' ) ), admin_url( 'admin.php?page=cro-insights' ) ) ); ?>" class="button button-small"><?php esc_html_e( '30 days', 'meyvora-convert' ); ?></a>
					<a href="<?php echo esc_url( add_query_arg( array( 'cro_export_from' => gmdate( 'Y-m-d', strtotime( '-90 days' ) ), 'cro_export_to' => gmdate( 'Y-m-d' ) ), admin_url( 'admin.php?page=cro-insights' ) ) ); ?>" class="button button-small"><?php esc_html_e( '90 days', 'meyvora-convert' ); ?></a>
				</div>
				<form method="get" class="cro-export-date-form" style="display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap;">
					<input type="hidden" name="page" value="cro-insights" />
					<label class="screen-reader-text"><?php esc_html_e( 'From', 'meyvora-convert' ); ?></label>
					<input type="date" name="cro_export_from" value="<?php echo esc_attr( $export_from ); ?>" />
					<span><?php esc_html_e( 'to', 'meyvora-convert' ); ?></span>
					<label class="screen-reader-text"><?php esc_html_e( 'To', 'meyvora-convert' ); ?></label>
					<input type="date" name="cro_export_to" value="<?php echo esc_attr( $export_to ); ?>" />
					<button type="submit" class="button button-small"><?php esc_html_e( 'Apply', 'meyvora-convert' ); ?></button>
				</form>
				<p class="cro-export-buttons" style="margin: 12px 0 0;">
					<a href="<?php echo esc_url( $export_url_events ); ?>" class="button"><?php esc_html_e( 'Export events CSV', 'meyvora-convert' ); ?></a>
					<a href="<?php echo esc_url( $export_url_daily ); ?>" class="button"><?php esc_html_e( 'Daily summary CSV', 'meyvora-convert' ); ?></a>
					<span class="cro-muted" style="font-size: 12px;"><?php echo esc_html( sprintf( __( 'Max %d days.', 'meyvora-convert' ), $export_max_days ) ); ?></span>
				</p>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<div class="cro-card">
		<header class="cro-card__header">
			<h2 class="cro-card__title"><?php esc_html_e( 'Insights', 'meyvora-convert' ); ?></h2>
		</header>
		<div class="cro-card__body">
			<p class="cro-muted" style="margin-bottom: 16px;">
				<?php esc_html_e( 'Rule-based recommendations from your tracking data. Use the Fix links to improve performance.', 'meyvora-convert' ); ?>
			</p>

			<?php if ( empty( $insights ) ) : ?>
				<div class="cro-empty-state">
					<span class="cro-empty-state__icon" aria-hidden="true"><?php echo class_exists( 'CRO_Icons' ) ? CRO_Icons::svg( 'trending-up', array( 'class' => 'cro-ico cro-ico--lg' ) ) : ''; ?></span>
					<h3 class="cro-empty-state__title"><?php esc_html_e( 'No insights yet', 'meyvora-convert' ); ?></h3>
					<p class="cro-empty-state__text">
						<?php esc_html_e( 'As impressions and conversions are tracked, we’ll show top performers, underperforming campaigns, and next best actions here.', 'meyvora-convert' ); ?>
					</p>
					<p class="cro-empty-state__actions">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=cro-campaigns' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Campaigns', 'meyvora-convert' ); ?></a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=cro-offers' ) ); ?>" class="button"><?php esc_html_e( 'Offers', 'meyvora-convert' ); ?></a>
					</p>
				</div>
			<?php else : ?>
				<ul class="cro-insights-list" style="list-style: none; padding: 0; margin: 0; display: grid; gap: 16px;">
					<?php foreach ( $insights as $item ) : ?>
						<li class="cro-card" style="margin-bottom: 0;">
							<div class="cro-card__body" style="display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 16px;">
								<div style="flex: 1; min-width: 0;">
									<?php
									$badge_class = 'cro-badge';
									if ( $item['type'] === 'top' ) {
										$badge_class .= ' cro-badge--success';
									} elseif ( $item['type'] === 'underperforming' ) {
										$badge_class .= ' cro-badge--warning';
									}
									?>
									<span class="<?php echo esc_attr( $badge_class ); ?>">
										<?php
										echo esc_html(
											$item['type'] === 'top' ? __( 'Top', 'meyvora-convert' ) : ( $item['type'] === 'underperforming' ? __( 'Underperforming', 'meyvora-convert' ) : __( 'Action', 'meyvora-convert' ) )
										);
										?>
									</span>
									<h3 style="margin: 8px 0 4px; font-size: 14px;"><?php echo esc_html( $item['title'] ); ?></h3>
									<p style="margin: 0; font-size: 13px; color: var(--cro-text-muted); line-height: 1.5;"><?php echo esc_html( $item['description'] ); ?></p>
								</div>
								<?php if ( ! empty( $item['fix_url'] ) ) : ?>
									<a href="<?php echo esc_url( $item['fix_url'] ); ?>" class="button button-primary"><?php echo esc_html( $item['fix_label'] ); ?></a>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
</div>
