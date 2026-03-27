<?php
/**
 * System Status admin page (Meyvora Convert → System Status).
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'CRO_System_Status' ) ) {
	require_once CRO_PLUGIN_DIR . 'includes/class-cro-system-status.php';
}

$checks = CRO_System_Status::run_checks();
$report_text = CRO_System_Status::build_report( $checks );
$run_test_url = add_query_arg( array( 'page' => 'cro-system-status', 'run' => '1' ), admin_url( 'admin.php' ) );
$cro_repair_raw = filter_input( INPUT_GET, 'cro_repair', FILTER_UNSAFE_RAW );
$cro_repair     = is_string( $cro_repair_raw ) ? sanitize_key( $cro_repair_raw ) : '';
$cro_repair_err_raw = filter_input( INPUT_GET, 'cro_repair_error', FILTER_UNSAFE_RAW );
$cro_repair_error   = is_string( $cro_repair_err_raw ) ? sanitize_text_field( wp_unslash( $cro_repair_err_raw ) ) : '';
$verify_installation_results = get_transient( 'cro_verify_installation_results' );
if ( $verify_installation_results !== false ) {
	delete_transient( 'cro_verify_installation_results' );
}
$verify_raw = filter_input( INPUT_GET, 'cro_verify_installation', FILTER_UNSAFE_RAW );
$verify_raw = is_string( $verify_raw ) ? sanitize_key( $verify_raw ) : '';
$verify_installation_done = ( $verify_raw === '1' );
$verify_installation_fail = ( $verify_raw === '0' );
?>

<?php if ( $cro_repair === '1' ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Database tables repaired successfully. All CRO tables have been created or updated.', 'meyvora-convert' ); ?></p>
			<?php if ( $cro_repair_error !== '' ) : ?>
				<p><?php esc_html_e( 'Database message:', 'meyvora-convert' ); ?> <code><?php echo esc_html( $cro_repair_error ); ?></code></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
	<?php if ( $cro_repair === '0' ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'Repair failed.', 'meyvora-convert' ); ?></p>
			<?php if ( $cro_repair_error !== '' ) : ?>
				<p><code><?php echo esc_html( $cro_repair_error ); ?></code></p>
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
			<ul class="cro-verify-installation-list cro-list-plain cro-mt-1">
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
							<span class="cro-status-ok" aria-hidden="true"><?php echo wp_kses( CRO_Icons::svg( 'check', array( 'class' => 'cro-ico' ) ), CRO_Icons::get_svg_kses_allowed() ); ?></span>

						<?php else : ?>
							<span class="cro-status-warn" aria-hidden="true"><?php echo wp_kses( CRO_Icons::svg( 'alert', array( 'class' => 'cro-ico' ) ), CRO_Icons::get_svg_kses_allowed() ); ?></span>

						<?php endif; ?>
						<strong><?php echo esc_html( $label ); ?></strong>: <?php echo esc_html( $message ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="cro-mt-1">
				<?php if ( $all_pass ) : ?>
					<span class="cro-status-ok cro-fw-600"><?php esc_html_e( 'All checks passed.', 'meyvora-convert' ); ?></span>
				<?php else : ?>
					<span class="cro-status-warn cro-fw-600"><?php esc_html_e( 'One or more checks failed.', 'meyvora-convert' ); ?></span>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="cro-system-status-actions cro-mb-2">
		<a href="<?php echo esc_url( $run_test_url ); ?>" class="button button-primary">
			<?php esc_html_e( 'Run self-test', 'meyvora-convert' ); ?>
		</a>
		<button type="button" class="button" id="cro-copy-report" data-report="<?php echo esc_attr( $report_text ); ?>">
			<?php esc_html_e( 'Copy report to clipboard', 'meyvora-convert' ); ?>
		</button>
		<form method="post" action="" id="cro-verify-installation-form" class="cro-inline-form">
			<?php wp_nonce_field( 'cro_verify_installation', 'cro_verify_installation_nonce' ); ?>
			<input type="hidden" name="cro_verify_installation" value="1" />
			<button type="submit" class="button"><?php esc_html_e( 'Verify Installation', 'meyvora-convert' ); ?></button>
		</form>
		<form method="post" action="" class="cro-inline-form">
			<?php wp_nonce_field( 'cro_repair_tables', 'cro_repair_nonce' ); ?>
			<input type="hidden" name="cro_repair_tables" value="1" />
			<button type="submit" class="button"><?php esc_html_e( 'Repair Database Tables', 'meyvora-convert' ); ?></button>
		</form>
	</div>

	<div class="cro-table-wrap cro-max-w-800">
	<table class="cro-table widefat striped cro-system-status-table">
		<thead>
			<tr>
				<th class="cro-col-check"><?php esc_html_e( 'Check', 'meyvora-convert' ); ?></th>
				<th class="cro-col-status"><?php esc_html_e( 'Status', 'meyvora-convert' ); ?></th>
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
						$badge = 'ok' === $status ? 'cro-status-ok' : ( 'error' === $status ? 'cro-status-error' : 'cro-status-warning' );
						$label = 'ok' === $status ? __( 'OK', 'meyvora-convert' ) : ( 'error' === $status ? __( 'Error', 'meyvora-convert' ) : __( 'Warning', 'meyvora-convert' ) );
						?>
						<span class="cro-status-badge <?php echo esc_attr( $badge ); ?>"><?php echo esc_html( $label ); ?></span>
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

	<div class="cro-system-status-report-box cro-mt-3 cro-max-w-800">
		<label for="cro-report-text" class="screen-reader-text"><?php esc_attr_e( 'Report text for support', 'meyvora-convert' ); ?></label>
		<textarea id="cro-report-text" class="large-text code" rows="18" readonly><?php echo esc_textarea( $report_text ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Copy the text above and paste it when contacting support.', 'meyvora-convert' ); ?>
		</p>
	</div>
