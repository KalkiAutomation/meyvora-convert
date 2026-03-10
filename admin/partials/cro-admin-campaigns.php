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

// Show notices
if ( ! empty( $_GET['deleted'] ) ) {
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Campaign deleted.', 'meyvora-convert' ) . '</p></div>';
}

// Show error notices (sanitized)
$campaign_error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
if ( $campaign_error === 'duplicate_failed' ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'Failed to duplicate campaign.', 'meyvora-convert' ) . '</p></div>';
} elseif ( $campaign_error === 'invalid_nonce' ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid security check. Please try again.', 'meyvora-convert' ) . '</p></div>';
} elseif ( $campaign_error === 'unauthorized' ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to perform that action.', 'meyvora-convert' ) . '</p></div>';
}

$campaigns = CRO_Campaign::get_all();

if ( ! empty( $_GET['cro_bulk_done'] ) ) {
	echo '<div class="notice notice-success is-dismissible"><p>'
		. esc_html__( 'Bulk action applied successfully.', 'meyvora-convert' )
		. '</p></div>';
}
?>

	<?php if ( empty( $campaigns ) ) : ?>
		<div class="cro-table-empty-state">
			<span class="cro-table-empty-state__icon" aria-hidden="true"><?php echo CRO_Icons::svg( 'sparkles', array( 'class' => 'cro-ico' ) ); ?></span>
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
						<tr>
							<td><input type="checkbox" name="campaign_ids[]" value="<?php echo esc_attr( $campaign['id'] ); ?>" class="cro-campaign-cb" /></td>
							<td><?php echo esc_html( (string) ( $campaign['id'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $campaign['name'] ?? '' ) ); ?></td>
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
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cro-campaigns&action=duplicate&id=' . $campaign['id'] ), 'cro_duplicate_campaign' ) ); ?>"><?php esc_html_e( 'Duplicate', 'meyvora-convert' ); ?></a>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cro-campaigns&action=delete&id=' . $campaign['id'] ), 'cro_delete_campaign_' . $campaign['id'] ) ); ?>" class="cro-table-action-link delete-link" onclick="return confirm('<?php esc_attr_e( 'Are you sure?', 'meyvora-convert' ); ?>');"><?php esc_html_e( 'Delete', 'meyvora-convert' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		</form>
		<script>
		document.getElementById('cro-select-all').addEventListener('change', function() {
			var cbs = document.querySelectorAll('.cro-campaign-cb');
			for (var i = 0; i < cbs.length; i++) { cbs[i].checked = this.checked; }
		});
		</script>
	<?php endif; ?>
