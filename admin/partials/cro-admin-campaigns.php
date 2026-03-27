<?php
/**
 * Admin campaigns list page
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once CRO_PLUGIN_DIR . 'admin/partials/cro-admin-ai-panel.php';

// Show notices
if ( CRO_Security::get_query_var( 'deleted' ) !== '' ) {
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Campaign deleted.', 'meyvora-convert' ) . '</p></div>';
}

// Show error notices (sanitized)
$campaign_error = CRO_Security::get_query_var( 'error' );
if ( $campaign_error === 'duplicate_failed' ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'Failed to duplicate campaign.', 'meyvora-convert' ) . '</p></div>';
} elseif ( $campaign_error === 'invalid_nonce' ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid security check. Please try again.', 'meyvora-convert' ) . '</p></div>';
} elseif ( $campaign_error === 'unauthorized' ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to perform that action.', 'meyvora-convert' ) . '</p></div>';
}

$campaigns = CRO_Campaign::get_all();

// 90-day window, same thresholds as CRO_AI_AB_Hypothesis::campaign_is_low_converting (≥50 imp, &lt;3% CVR) — one aggregate query, not per row.
$ai_low_campaign_ids = class_exists( 'CRO_AI_AB_Hypothesis' )
	? CRO_AI_AB_Hypothesis::get_campaign_ids_low_converting_90d()
	: array();

$campaign_names_by_id = array();
$fallback_parent_names = array();
foreach ( $campaigns as $_c ) {
	$_id = (int) ( $_c['id'] ?? 0 );
	if ( $_id > 0 ) {
		$campaign_names_by_id[ $_id ] = (string) ( $_c['name'] ?? '' );
	}
}
foreach ( $campaigns as $_c ) {
	$_id = (int) ( $_c['id'] ?? 0 );
	$_fb = (int) ( $_c['fallback_id'] ?? 0 );
	if ( $_id > 0 && $_fb > 0 ) {
		if ( ! isset( $fallback_parent_names[ $_fb ] ) ) {
			$fallback_parent_names[ $_fb ] = array();
		}
		$fallback_parent_names[ $_fb ][] = (string) ( $_c['name'] ?? '' );
	}
}

if ( CRO_Security::get_query_var( 'cro_bulk_done' ) !== '' ) {
	echo '<div class="notice notice-success is-dismissible"><p>'
		. esc_html__( 'Bulk action applied successfully.', 'meyvora-convert' )
		. '</p></div>';
}
?>

	<?php if ( empty( $campaigns ) ) : ?>
		<div class="cro-table-empty-state">
			<span class="cro-table-empty-state__icon" aria-hidden="true"><?php echo wp_kses( CRO_Icons::svg( 'sparkles', array( 'class' => 'cro-ico' ) ), CRO_Icons::get_svg_kses_allowed() ); ?></span>

			<h3 class="cro-table-empty-state__title"><?php esc_html_e( 'No campaigns yet', 'meyvora-convert' ); ?></h3>
			<p class="cro-table-empty-state__text"><?php esc_html_e( 'Create your first campaign to show exit intent, scroll, or time-based offers to visitors.', 'meyvora-convert' ); ?></p>
			<p class="cro-mt-2">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cro-campaign-edit' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Add New Campaign', 'meyvora-convert' ); ?></a>
			</p>
		</div>
	<?php else : ?>
		<form method="post" id="cro-bulk-form">
			<?php wp_nonce_field( 'cro_bulk_campaigns', 'cro_bulk_nonce' ); ?>
			<div class="tablenav top" style="margin-bottom:8px;">
				<div class="alignleft actions bulkactions">
					<select name="cro_bulk_action">
						<option value=""><?php esc_html_e( 'Bulk Actions', 'meyvora-convert' ); ?></option>
						<option value="activate"><?php esc_html_e( 'Activate', 'meyvora-convert' ); ?></option>
						<option value="pause"><?php esc_html_e( 'Pause', 'meyvora-convert' ); ?></option>
						<option value="delete"><?php esc_html_e( 'Delete', 'meyvora-convert' ); ?></option>
					</select>
					<button type="submit" class="button"><?php esc_html_e( 'Apply', 'meyvora-convert' ); ?></button>
				</div>
			</div>
		<div class="cro-table-wrap">
			<table class="cro-table">
				<thead>
					<tr>
						<th class="check-column"><input type="checkbox" id="cro-select-all" /></th>
						<th><?php esc_html_e( 'ID', 'meyvora-convert' ); ?></th>
						<th><?php esc_html_e( 'Name', 'meyvora-convert' ); ?></th>
						<th><?php esc_html_e( 'Type', 'meyvora-convert' ); ?></th>
						<th><?php esc_html_e( 'Status', 'meyvora-convert' ); ?></th>
						<th><?php esc_html_e( 'Impressions', 'meyvora-convert' ); ?></th>
						<th><?php esc_html_e( 'Conv. Rate', 'meyvora-convert' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'meyvora-convert' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $campaigns as $campaign ) : ?>
						<?php
						$campaign_row_id = isset( $campaign['id'] ) ? (int) $campaign['id'] : 0;
						$show_ai_ideas = $campaign_row_id > 0 && ! empty( $ai_low_campaign_ids[ $campaign_row_id ] );
						?>
						<tr>
							<td><input type="checkbox" name="campaign_ids[]" value="<?php echo esc_attr( $campaign['id'] ); ?>" class="cro-campaign-cb" /></td>
							<td><?php echo esc_html( (string) ( $campaign['id'] ?? '' ) ); ?></td>
							<td>
								<?php echo esc_html( (string) ( $campaign['name'] ?? '' ) ); ?>
								<?php
								$row_cid = (int) ( $campaign['id'] ?? 0 );
								$row_fb  = (int) ( $campaign['fallback_id'] ?? 0 );
								if ( $row_fb > 0 && isset( $campaign_names_by_id[ $row_fb ] ) && $campaign_names_by_id[ $row_fb ] !== '' ) {
									echo '<br /><span class="description" style="display:block;margin-top:4px;">';
									echo esc_html( '→ ' . $campaign_names_by_id[ $row_fb ] );
									echo '</span>';
								}
								if ( $row_cid > 0 && ! empty( $fallback_parent_names[ $row_cid ] ) ) {
									$parent_list = array_unique( array_filter( $fallback_parent_names[ $row_cid ] ) );
									if ( ! empty( $parent_list ) ) {
										echo '<br /><span class="description" style="display:block;margin-top:4px;">';
										echo esc_html(
											sprintf(
												/* translators: %s: comma-separated parent campaign names */
												__( '← fallback for %s', 'meyvora-convert' ),
												implode( ', ', $parent_list )
											)
										);
										echo '</span>';
									}
								}
								?>
							</td>
							<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', (string) ( $campaign['campaign_type'] ?? $campaign['type'] ?? 'exit_intent' ) ) ) ); ?></td>
							<td><?php echo esc_html( (string) ( isset( $campaign['status'] ) ? ucfirst( $campaign['status'] ) : '' ) ); ?></td>
							<?php
							$imp  = (int) ( $campaign['impressions'] ?? 0 );
							$conv = (int) ( $campaign['conversions'] ?? 0 );
							$rate = $imp > 0 ? round( ( $conv / $imp ) * 100, 1 ) . '%' : '—';
							?>
							<td><?php echo esc_html( number_format_i18n( $imp ) ); ?></td>
							<td><?php echo esc_html( $rate ); ?></td>
							<td class="cro-table-actions">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=cro-campaign-edit&campaign_id=' . $campaign['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'meyvora-convert' ); ?></a>
								<?php if ( $show_ai_ideas ) : ?>
									<span class="cro-table-actions__sep" aria-hidden="true">|</span>
									<a href="#" class="js-cro-ai-ab-hypothesis-toggle" data-campaign-id="<?php echo esc_attr( (string) $campaign_row_id ); ?>"><?php esc_html_e( '✦ AI Test Ideas', 'meyvora-convert' ); ?></a>
								<?php endif; ?>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cro-campaigns&action=duplicate&id=' . $campaign['id'] ), 'cro_duplicate_campaign' ) ); ?>"><?php esc_html_e( 'Duplicate', 'meyvora-convert' ); ?></a>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cro-campaigns&action=delete&id=' . $campaign['id'] ), 'cro_delete_campaign_' . $campaign['id'] ) ); ?>" class="cro-table-action-link delete-link" onclick="return confirm('<?php esc_attr_e( 'Are you sure?', 'meyvora-convert' ); ?>');"><?php esc_html_e( 'Delete', 'meyvora-convert' ); ?></a>
							</td>
						</tr>
						<?php if ( $show_ai_ideas ) : ?>
						<tr id="cro-ai-hypothesis-panel-<?php echo esc_attr( (string) $campaign_row_id ); ?>" class="cro-ai-hypothesis-panel-wrap" style="display:none;">
							<td colspan="8">
								<div class="cro-ai-hypothesis-panel__slide" style="display:none;">
									<div class="cro-ai-hypothesis-panel__inner"></div>
								</div>
							</td>
						</tr>
						<?php endif; ?>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		</form>
	<?php endif; ?>
