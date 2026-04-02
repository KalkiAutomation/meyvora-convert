<?php
/**
 * Tools → Import / Export
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$campaigns = array();
if ( class_exists( 'MEYVC_Campaign' ) ) {
	$campaigns = MEYVC_Campaign::get_all( array( 'limit' => 500 ) );
}
$error             = MEYVC_Security::get_query_var( 'error' );
$admin_debug_saved = MEYVC_Security::get_query_var( 'meyvc_admin_debug_saved' );
$meyvc_admin_debug   = (bool) get_option( 'meyvc_admin_debug', false );
$verify_results = get_transient( 'meyvc_verify_results' );
if ( $verify_results !== false ) {
	delete_transient( 'meyvc_verify_results' );
}
?>

<?php if ( $error === 'no_campaign' ) : ?>
		<div class="notice notice-error"><p><?php esc_html_e( 'Please select a campaign to export.', 'meyvora-convert' ); ?></p></div>
	<?php endif; ?>
	<?php if ( $error === 'not_found' ) : ?>
		<div class="notice notice-error"><p><?php esc_html_e( 'Campaign not found.', 'meyvora-convert' ); ?></p></div>
	<?php endif; ?>
	<?php if ( $admin_debug_saved !== '' && current_user_can( 'manage_meyvora_convert' ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo $admin_debug_saved === '1' ? esc_html__( 'CRO Admin Debug enabled. Reload any CRO page to see the debug panel.', 'meyvora-convert' ) : esc_html__( 'CRO Admin Debug disabled.', 'meyvora-convert' ); ?></p></div>
	<?php endif; ?>
	<!-- CRO Admin Debug (manage_meyvora_convert only) -->
	<?php if ( current_user_can( 'manage_meyvora_convert' ) ) : ?>
	<div class="meyvc-card meyvc-tools-section meyvc-mt-2">
		<header class="meyvc-card__header"><h2><?php esc_html_e( 'CRO Admin Debug', 'meyvora-convert' ); ?></h2></header>
		<div class="meyvc-card__body">
			<p class="meyvc-section-desc"><?php esc_html_e( 'When enabled, a small panel at the bottom of CRO admin pages shows enqueued CSS/JS and campaign builder init status. Use for troubleshooting layout and builder issues.', 'meyvora-convert' ); ?></p>
			<form method="post" action="">
				<?php wp_nonce_field( 'meyvc_admin_debug', 'meyvc_admin_debug_nonce' ); ?>
				<input type="hidden" name="page" value="meyvc-tools" />
				<label><input type="checkbox" name="meyvc_admin_debug" value="1" <?php checked( $meyvc_admin_debug ); ?> /> <?php esc_html_e( 'Enable CRO Admin Debug', 'meyvora-convert' ); ?></label>
				<p><button type="submit" class="button button-secondary"><?php esc_html_e( 'Save', 'meyvora-convert' ); ?></button></p>
			</form>
		</div>
	</div>
	<?php endif; ?>
	<!-- Verify Install Package -->
	<div class="meyvc-card meyvc-tools-section meyvc-mt-2">
		<header class="meyvc-card__header"><h2><?php esc_html_e( 'Verify Install Package', 'meyvora-convert' ); ?></h2></header>
		<div class="meyvc-card__body">
			<p class="meyvc-section-desc"><?php esc_html_e( 'Check that required tables exist, blocks build assets are present, and assets are not enqueued site-wide.', 'meyvora-convert' ); ?></p>
			<form method="post" action="">
				<?php wp_nonce_field( 'meyvc_verify_package', 'meyvc_verify_nonce' ); ?>
				<input type="hidden" name="meyvc_verify_package" value="1" />
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Verify Install Package', 'meyvora-convert' ); ?></button></p>
			</form>
			<?php if ( $verify_results !== false && is_array( $verify_results ) ) : ?>
				<ul class="meyvc-list-plain meyvc-mt-2">
					<?php
					$all_pass = true;
					foreach ( $verify_results as $item ) {
						if ( ! empty( $item['pass'] ) ) {
							continue;
						}
						$all_pass = false;
						break;
					}
					foreach ( $verify_results as $item ) :
						$pass = ! empty( $item['pass'] );
						$label = isset( $item['label'] ) ? $item['label'] : '';
						$message = isset( $item['message'] ) ? $item['message'] : '';
					?>
						<li>
							<?php if ( $pass ) : ?>
								<span class="meyvc-status-ok" aria-hidden="true"><?php echo wp_kses( MEYVC_Icons::svg( 'check', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

							<?php else : ?>
								<span class="meyvc-status-warn" aria-hidden="true"><?php echo wp_kses( MEYVC_Icons::svg( 'alert', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

							<?php endif; ?>
							<strong><?php echo esc_html( $label ); ?></strong>: <?php echo esc_html( $message ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
				<p class="meyvc-mt-1">
					<?php if ( $all_pass ) : ?>
						<strong class="meyvc-status-ok"><?php esc_html_e( 'All checks passed.', 'meyvora-convert' ); ?></strong>
					<?php else : ?>
						<strong class="meyvc-status-warn"><?php esc_html_e( 'One or more checks failed.', 'meyvora-convert' ); ?></strong>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>
	</div>

	<div class="meyvc-tools-sections meyvc-max-w">
		<!-- Export -->
		<div class="meyvc-card meyvc-tools-section meyvc-mt-2">
		<header class="meyvc-card__header"><h2><?php esc_html_e( 'Export', 'meyvora-convert' ); ?></h2></header>
		<div class="meyvc-card__body">
			<p class="meyvc-section-desc"><?php esc_html_e( 'Export a campaign as JSON. Analytics data (impressions, conversions, revenue) is not included.', 'meyvora-convert' ); ?></p>
			<?php if ( empty( $campaigns ) ) : ?>
				<p><?php esc_html_e( 'No campaigns to export.', 'meyvora-convert' ); ?></p>
			<?php else : ?>
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" id="meyvc-export-form">
					<input type="hidden" name="page" value="meyvc-tools" />
					<input type="hidden" name="action" value="meyvc_export" />
					<?php wp_nonce_field( 'meyvc_export', '_wpnonce', true ); ?>
					<p>
						<label for="meyvc-export-campaign"><?php esc_html_e( 'Campaign', 'meyvora-convert' ); ?></label><br />
						<select name="campaign_id" id="meyvc-export-campaign" class="regular-text meyvc-selectwoo" data-placeholder="<?php esc_attr_e( '— Select a campaign —', 'meyvora-convert' ); ?>" required>
							<option value=""><?php esc_html_e( '— Select a campaign —', 'meyvora-convert' ); ?></option>
							<?php foreach ( $campaigns as $c ) : ?>
								<option value="<?php echo esc_attr( (string) $c['id'] ); ?>">
									<?php echo esc_html( isset( $c['name'] ) && $c['name'] !== '' ? $c['name'] : __( 'Unnamed', 'meyvora-convert' ) ); ?>
									(<?php echo esc_html( $c['status'] ?? 'draft' ); ?>)
								</option>
							<?php endforeach; ?>
						</select>
					</p>
					<p>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Export campaign', 'meyvora-convert' ); ?></button>
					</p>
				</form>
			<?php endif; ?>
		</div>
		</div>

		<!-- Import -->
		<div class="meyvc-card meyvc-tools-section meyvc-mt-2">
		<header class="meyvc-card__header"><h2><?php esc_html_e( 'Import', 'meyvora-convert' ); ?></h2></header>
		<div class="meyvc-card__body">
			<p class="meyvc-section-desc"><?php esc_html_e( 'Upload a campaign JSON file or paste JSON below. Campaigns are imported as new drafts.', 'meyvora-convert' ); ?></p>
			<form method="post" action="" enctype="multipart/form-data" id="meyvc-import-form">
				<?php wp_nonce_field( 'meyvc_import', 'meyvc_import_nonce' ); ?>
				<input type="hidden" name="meyvc_import" value="1" />
				<p>
					<label for="meyvc-import-file"><?php esc_html_e( 'Upload file', 'meyvora-convert' ); ?></label><br />
					<input type="file" name="import_file" id="meyvc-import-file" accept=".json,application/json" class="regular-text" />
				</p>
				<p class="description"><?php esc_html_e( 'Or paste JSON below (overrides file if both provided).', 'meyvora-convert' ); ?></p>
				<p>
					<label for="meyvc-import-json"><?php esc_html_e( 'Paste JSON', 'meyvora-convert' ); ?></label><br />
					<textarea name="import_json" id="meyvc-import-json" class="large-text code" rows="10" placeholder='{"campaigns": [{ "name": "...", ... }]}'></textarea>
				</p>
				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Import', 'meyvora-convert' ); ?></button>
				</p>
			</form>
		</div>
		</div>
	</div>
