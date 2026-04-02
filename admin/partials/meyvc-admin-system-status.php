<?php
/**
 * System Status admin page (Meyvora Convert → System Status).
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'MEYVC_System_Status' ) ) {
	require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-system-status.php';
}

$checks = MEYVC_System_Status::run_checks();
$report_text = MEYVC_System_Status::build_report( $checks );
$run_test_url = add_query_arg( array( 'page' => 'meyvc-system-status', 'run' => '1' ), admin_url( 'admin.php' ) );
$meyvc_repair_raw = filter_input( INPUT_GET, 'meyvc_repair', FILTER_UNSAFE_RAW );
$meyvc_repair     = is_string( $meyvc_repair_raw ) ? sanitize_key( $meyvc_repair_raw ) : '';
$meyvc_repair_err_raw = filter_input( INPUT_GET, 'meyvc_repair_error', FILTER_UNSAFE_RAW );
$meyvc_repair_error   = is_string( $meyvc_repair_err_raw ) ? sanitize_text_field( wp_unslash( $meyvc_repair_err_raw ) ) : '';
$verify_installation_results = get_transient( 'meyvc_verify_installation_results' );
if ( $verify_installation_results !== false ) {
	delete_transient( 'meyvc_verify_installation_results' );
}
$verify_raw = filter_input( INPUT_GET, 'meyvc_verify_installation', FILTER_UNSAFE_RAW );
$verify_raw = is_string( $verify_raw ) ? sanitize_key( $verify_raw ) : '';
$verify_installation_done = ( $verify_raw === '1' );
$verify_installation_fail = ( $verify_raw === '0' );
?>

<?php if ( $meyvc_repair === '1' ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Database tables repaired successfully. All CRO tables have been created or updated.', 'meyvora-convert' ); ?></p>
			<?php if ( $meyvc_repair_error !== '' ) : ?>
				<p><?php esc_html_e( 'Database message:', 'meyvora-convert' ); ?> <code><?php echo esc_html( $meyvc_repair_error ); ?></code></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
	<?php if ( $meyvc_repair === '0' ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'Repair failed.', 'meyvora-convert' ); ?></p>
			<?php if ( $meyvc_repair_error !== '' ) : ?>
				<p><code><?php echo esc_html( $meyvc_repair_error ); ?></code></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
	<?php if ( $verify_installation_fail ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'Verify Installation was not run (invalid request or insufficient permissions).', 'meyvora-convert' ); ?></p>
		</div>
	<?php endif; ?>
	<?php if ( $verify_installation_done && $verify_installation_results !== false && is_array( $verify_installation_results ) ) : ?>
		<div class="notice notice-info is-dismissible">
			<p><strong><?php esc_html_e( 'Verify Installation results', 'meyvora-convert' ); ?></strong></p>
			<ul class="meyvc-verify-installation-list meyvc-list-plain meyvc-mt-1">
				<?php
				$all_pass = true;
				foreach ( $verify_installation_results as $item ) {
					if ( empty( $item['pass'] ) ) {
						$all_pass = false;
						break;
					}
				}
				foreach ( $verify_installation_results as $item ) :
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
					<span class="meyvc-status-ok meyvc-fw-600"><?php esc_html_e( 'All checks passed.', 'meyvora-convert' ); ?></span>
				<?php else : ?>
					<span class="meyvc-status-warn meyvc-fw-600"><?php esc_html_e( 'One or more checks failed.', 'meyvora-convert' ); ?></span>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="meyvc-system-status-actions meyvc-mb-2">
		<a href="<?php echo esc_url( $run_test_url ); ?>" class="button button-primary">
			<?php esc_html_e( 'Run self-test', 'meyvora-convert' ); ?>
		</a>
		<button type="button" class="button" id="meyvc-copy-report" data-report="<?php echo esc_attr( $report_text ); ?>">
			<?php esc_html_e( 'Copy report to clipboard', 'meyvora-convert' ); ?>
		</button>
		<form method="post" action="" id="meyvc-verify-installation-form" class="meyvc-inline-form">
			<?php wp_nonce_field( 'meyvc_verify_installation', 'meyvc_verify_installation_nonce' ); ?>
			<input type="hidden" name="meyvc_verify_installation" value="1" />
			<button type="submit" class="button"><?php esc_html_e( 'Verify Installation', 'meyvora-convert' ); ?></button>
		</form>
		<form method="post" action="" class="meyvc-inline-form">
			<?php wp_nonce_field( 'meyvc_repair_tables', 'meyvc_repair_nonce' ); ?>
			<input type="hidden" name="meyvc_repair_tables" value="1" />
			<button type="submit" class="button"><?php esc_html_e( 'Repair Database Tables', 'meyvora-convert' ); ?></button>
		</form>
	</div>

	<div class="meyvc-table-wrap meyvc-max-w-800">
	<table class="meyvc-table widefat striped meyvc-system-status-table">
		<thead>
			<tr>
				<th class="meyvc-col-check"><?php esc_html_e( 'Check', 'meyvora-convert' ); ?></th>
				<th class="meyvc-col-status"><?php esc_html_e( 'Status', 'meyvora-convert' ); ?></th>
				<th><?php esc_html_e( 'Details', 'meyvora-convert' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $checks as $c ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $c['label'] ); ?></strong></td>
					<td>
						<?php
						$status = $c['status'];
						$badge = 'ok' === $status ? 'meyvc-status-ok' : ( 'error' === $status ? 'meyvc-status-error' : 'meyvc-status-warning' );
						$label = 'ok' === $status ? __( 'OK', 'meyvora-convert' ) : ( 'error' === $status ? __( 'Error', 'meyvora-convert' ) : __( 'Warning', 'meyvora-convert' ) );
						?>
						<span class="meyvc-status-badge <?php echo esc_attr( $badge ); ?>"><?php echo esc_html( $label ); ?></span>
					</td>
					<td>
						<?php echo esc_html( $c['message'] ); ?>
						<?php if ( ! empty( $c['detail'] ) ) : ?>
							<br><span class="description"><?php echo esc_html( $c['detail'] ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	</div>

	<div class="meyvc-system-status-report-box meyvc-mt-3 meyvc-max-w-800">
		<label for="meyvc-report-text" class="screen-reader-text"><?php esc_attr_e( 'Report text for support', 'meyvora-convert' ); ?></label>
		<textarea id="meyvc-report-text" class="large-text code" rows="18" readonly><?php echo esc_textarea( $report_text ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Copy the text above and paste it when contacting support.', 'meyvora-convert' ); ?>
		</p>
	</div>
