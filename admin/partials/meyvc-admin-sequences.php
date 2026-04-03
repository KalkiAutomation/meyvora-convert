<?php
/**
 * Campaign sequences — list and editor.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template/view: variables are file-local.
$sequences = class_exists( 'MEYVC_Sequence_Engine' ) ? MEYVC_Sequence_Engine::get_all() : array();
$campaigns = class_exists( 'MEYVC_Campaign' ) ? MEYVC_Campaign::get_all() : array();

$campaign_names = array();
foreach ( $campaigns as $c ) {
	$cid = isset( $c['id'] ) ? (int) $c['id'] : 0;
	if ( $cid > 0 ) {
		$campaign_names[ $cid ] = (string) ( $c['name'] ?? '#' . $cid );
	}
}

if ( MEYVC_Security::get_query_var( 'sequence_saved' ) !== '' ) {
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Sequence saved.', 'meyvora-convert' ) . '</p></div>';
}
if ( MEYVC_Security::get_query_var( 'sequence_deleted' ) !== '' ) {
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Sequence deleted.', 'meyvora-convert' ) . '</p></div>';
}
$err = MEYVC_Security::get_query_var_key( 'sequence_error' );
if ( $err ) {
	$err_msgs = array(
		'invalid_nonce' => __( 'Invalid security check.', 'meyvora-convert' ),
		'invalid'       => __( 'Please enter a name, trigger campaign, and at least one follow-up step with a campaign.', 'meyvora-convert' ),
		'save_failed'   => __( 'Could not save sequence.', 'meyvora-convert' ),
		'unavailable'   => __( 'Sequences are not available (database tables may be missing).', 'meyvora-convert' ),
	);
	$msg = isset( $err_msgs[ $err ] ) ? $err_msgs[ $err ] : __( 'Something went wrong.', 'meyvora-convert' );
	echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
}

$edit_id = MEYVC_Security::get_query_var_absint( 'edit' );
$editing = null;
if ( $edit_id > 0 ) {
	foreach ( $sequences as $row ) {
		if ( isset( $row->id ) && (int) $row->id === $edit_id ) {
			$editing = $row;
			break;
		}
	}
}

$form_name     = $editing ? (string) $editing->name : '';
$form_trigger  = $editing ? (int) $editing->trigger_campaign_id : 0;
$form_status   = $editing ? (string) $editing->status : 'draft';
$form_steps    = array();
if ( $editing && ! empty( $editing->steps ) ) {
	$decoded = json_decode( (string) $editing->steps, true );
	if ( is_array( $decoded ) ) {
		foreach ( $decoded as $st ) {
			if ( ! is_array( $st ) ) {
				continue;
			}
			$cid  = isset( $st['campaign_id'] ) ? absint( $st['campaign_id'] ) : 0;
			$sec  = isset( $st['delay_seconds'] ) ? max( 0, (int) $st['delay_seconds'] ) : 0;
			$hours = round( $sec / 3600, 4 );
			if ( $cid > 0 ) {
				$form_steps[] = array( 'campaign_id' => $cid, 'delay_hours' => $hours );
			}
		}
	}
}
if ( empty( $form_steps ) ) {
	$form_steps[] = array( 'campaign_id' => 0, 'delay_hours' => 0 );
}
?>

<div class="meyvc-sequences-admin">
	<p class="description">
		<?php esc_html_e( 'When a visitor converts on the trigger campaign (or submits email, if applicable), they are enrolled. Each step waits the delay, then the follow-up campaign is eligible to show. Delays are per visitor session.', 'meyvora-convert' ); ?>
	</p>

	<h2><?php echo $editing ? esc_html__( 'Edit sequence', 'meyvora-convert' ) : esc_html__( 'New sequence', 'meyvora-convert' ); ?></h2>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="meyvc-sequences-form">
		<?php wp_nonce_field( 'meyvc_save_sequence', 'meyvc_sequence_nonce' ); ?>
		<input type="hidden" name="action" value="meyvc_save_sequence" />
		<input type="hidden" name="sequence_id" value="<?php echo $editing ? esc_attr( (string) $editing->id ) : '0'; ?>" />

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="sequence_name"><?php esc_html_e( 'Name', 'meyvora-convert' ); ?></label></th>
				<td><input name="sequence_name" id="sequence_name" type="text" class="regular-text" required value="<?php echo esc_attr( $form_name ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="trigger_campaign_id"><?php esc_html_e( 'Trigger campaign', 'meyvora-convert' ); ?></label></th>
				<td>
					<select name="trigger_campaign_id" id="trigger_campaign_id" required>
						<option value=""><?php esc_html_e( '— Select —', 'meyvora-convert' ); ?></option>
						<?php foreach ( $campaigns as $c ) : ?>
							<?php
							$cid = isset( $c['id'] ) ? (int) $c['id'] : 0;
							if ( $cid <= 0 ) {
								continue;
							}
							?>
							<option value="<?php echo esc_attr( (string) $cid ); ?>" <?php selected( $form_trigger, $cid ); ?>><?php echo esc_html( (string) ( $c['name'] ?? '' ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="sequence_status"><?php esc_html_e( 'Status', 'meyvora-convert' ); ?></label></th>
				<td>
					<select name="sequence_status" id="sequence_status">
						<option value="draft" <?php selected( $form_status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'meyvora-convert' ); ?></option>
						<option value="active" <?php selected( $form_status, 'active' ); ?>><?php esc_html_e( 'Active', 'meyvora-convert' ); ?></option>
						<option value="paused" <?php selected( $form_status, 'paused' ); ?>><?php esc_html_e( 'Paused', 'meyvora-convert' ); ?></option>
					</select>
				</td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Follow-up steps', 'meyvora-convert' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Delay is hours after the previous step (the first step runs after the trigger conversion). Use 0 for immediate.', 'meyvora-convert' ); ?></p>
		<table class="widefat striped" id="meyvc-sequence-steps" style="max-width:720px;">
			<thead>
				<tr>
					<th style="width:32px;"></th>
					<th style="width:2.5em;"><?php esc_html_e( '#', 'meyvora-convert' ); ?></th>
					<th><?php esc_html_e( 'Campaign', 'meyvora-convert' ); ?></th>
					<th><?php esc_html_e( 'Delay (hours)', 'meyvora-convert' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $form_steps as $idx => $step ) : ?>
					<tr class="meyvc-sequence-step-row">
						<td class="meyvc-sequence-drag-handle" style="cursor:grab;color:var(--meyvc-text-muted, #646970);padding:0 8px;font-size:18px;user-select:none;" title="<?php esc_attr_e( 'Drag to reorder', 'meyvora-convert' ); ?>">&#8597;</td>
						<td><span class="meyvc-sequence-step-num"><?php echo esc_html( (string) ( $idx + 1 ) ); ?></span></td>
						<td>
							<select name="step_campaign_id[]"<?php echo 0 === $idx ? ' required' : ''; ?>>
								<option value=""><?php esc_html_e( '— Select —', 'meyvora-convert' ); ?></option>
								<?php foreach ( $campaigns as $c ) : ?>
									<?php
									$cid = isset( $c['id'] ) ? (int) $c['id'] : 0;
									if ( $cid <= 0 ) {
										continue;
									}
									?>
									<option value="<?php echo esc_attr( (string) $cid ); ?>" <?php selected( (int) $step['campaign_id'], $cid ); ?>><?php echo esc_html( (string) ( $c['name'] ?? '' ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><input type="number" name="step_delay_hours[]" step="0.01" min="0" class="small-text" value="<?php echo esc_attr( (string) $step['delay_hours'] ); ?>" /></td>
						<td><button type="button" class="button meyvc-sequence-remove-step" <?php echo count( $form_steps ) <= 1 ? 'style="visibility:hidden"' : ''; ?> aria-label="<?php esc_attr_e( 'Remove step', 'meyvora-convert' ); ?>"><?php esc_html_e( 'Remove', 'meyvora-convert' ); ?></button></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p><button type="button" class="button" id="meyvc-sequence-add-step"><?php esc_html_e( 'Add step', 'meyvora-convert' ); ?></button></p>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save sequence', 'meyvora-convert' ); ?></button>
			<?php if ( $editing ) : ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=meyvc-sequences' ) ); ?>"><?php esc_html_e( 'Cancel', 'meyvora-convert' ); ?></a>
			<?php endif; ?>
		</p>
	</form>

	<hr />

	<h2><?php esc_html_e( 'All sequences', 'meyvora-convert' ); ?></h2>
	<?php if ( empty( $sequences ) ) : ?>
		<p><?php esc_html_e( 'No sequences yet.', 'meyvora-convert' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'meyvora-convert' ); ?></th>
					<th><?php esc_html_e( 'Name', 'meyvora-convert' ); ?></th>
					<th><?php esc_html_e( 'Trigger', 'meyvora-convert' ); ?></th>
					<th><?php esc_html_e( 'Steps', 'meyvora-convert' ); ?></th>
					<th><?php esc_html_e( 'Status', 'meyvora-convert' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'meyvora-convert' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $sequences as $row ) : ?>
					<?php
					$sid = isset( $row->id ) ? (int) $row->id : 0;
					$tid = isset( $row->trigger_campaign_id ) ? (int) $row->trigger_campaign_id : 0;
					$st  = isset( $row->steps ) ? json_decode( (string) $row->steps, true ) : array();
					$nsteps = is_array( $st ) ? count( $st ) : 0;
					$tname = $campaign_names[ $tid ] ?? ( $tid ? '#' . $tid : '—' );
					?>
					<tr>
						<td><?php echo esc_html( (string) $sid ); ?></td>
						<td><?php echo esc_html( (string) $row->name ); ?></td>
						<td><?php echo esc_html( $tname ); ?></td>
						<td><?php echo esc_html( (string) $nsteps ); ?></td>
						<td><?php echo esc_html( (string) $row->status ); ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=meyvc-sequences&edit=' . $sid ) ); ?>"><?php esc_html_e( 'Edit', 'meyvora-convert' ); ?></a>
							<?php
							$del = wp_nonce_url(
								admin_url( 'admin-post.php?action=meyvc_delete_sequence&sequence_id=' . $sid ),
								'meyvc_delete_sequence_' . $sid
							);
							?>
							&nbsp;|&nbsp;<a href="<?php echo esc_url( $del ); ?>" class="submitdelete" onclick="return confirm('<?php echo esc_js( __( 'Delete this sequence and its enrollments?', 'meyvora-convert' ) ); ?>');"><?php esc_html_e( 'Delete', 'meyvora-convert' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<?php ob_start(); ?>
<tr class="meyvc-sequence-step-row meyvc-sequence-step-template meyvc-hidden" hidden>
	<td class="meyvc-sequence-drag-handle" style="cursor:grab;color:var(--meyvc-text-muted, #646970);padding:0 8px;font-size:18px;user-select:none;" title="<?php esc_attr_e( 'Drag to reorder', 'meyvora-convert' ); ?>">&#8597;</td>
	<td><span class="meyvc-sequence-step-num">1</span></td>
	<td>
		<select name="step_campaign_id[]">
			<option value=""><?php esc_html_e( '— Select —', 'meyvora-convert' ); ?></option>
			<?php foreach ( $campaigns as $c ) : ?>
				<?php
				$cid = isset( $c['id'] ) ? (int) $c['id'] : 0;
				if ( $cid <= 0 ) {
					continue;
				}
				?>
				<option value="<?php echo esc_attr( (string) $cid ); ?>"><?php echo esc_html( (string) ( $c['name'] ?? '' ) ); ?></option>
			<?php endforeach; ?>
		</select>
	</td>
	<td><input type="number" name="step_delay_hours[]" step="0.01" min="0" class="small-text" value="0" /></td>
	<td><button type="button" class="button meyvc-sequence-remove-step" aria-label="<?php esc_attr_e( 'Remove step', 'meyvora-convert' ); ?>"><?php esc_html_e( 'Remove', 'meyvora-convert' ); ?></button></td>
</tr>
<?php
$template_html = ob_get_clean();
$allowed_seq_step = array(
	'tr'     => array( 'class' => true, 'hidden' => true, 'style' => true ),
	'td'     => array( 'class' => true, 'style' => true, 'title' => true ),
	'span'   => array( 'class' => true ),
	'select' => array( 'name' => true, 'class' => true ),
	'option' => array( 'value' => true, 'selected' => true ),
	'input'  => array(
		'type'  => true,
		'name'  => true,
		'step'  => true,
		'min'   => true,
		'class' => true,
		'value' => true,
	),
	'button' => array(
		'type'         => true,
		'class'        => true,
		'aria-label'   => true,
	),
);
?>

<table class="meyvc-hidden" aria-hidden="true" style="display:none;"><tbody id="meyvc-sequence-step-template-wrap"><?php echo wp_kses( $template_html, $allowed_seq_step ); ?></tbody></table>
