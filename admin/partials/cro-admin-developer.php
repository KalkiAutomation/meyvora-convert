<?php
/**
 * Developer tab: hooks reference and template overrides.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$doc = class_exists( 'CRO_Hooks' ) ? CRO_Hooks::get_hooks_documentation() : array( 'actions' => array(), 'filters' => array() );
$actions = isset( $doc['actions'] ) && is_array( $doc['actions'] ) ? $doc['actions'] : array();
$filters = isset( $doc['filters'] ) && is_array( $doc['filters'] ) ? $doc['filters'] : array();

$cro_webhook_endpoints = class_exists( 'CRO_Webhook' ) ? CRO_Webhook::get_endpoints() : array();
$cro_webhook_logs      = class_exists( 'CRO_Webhook' ) ? CRO_Webhook::get_delivery_logs() : array();
$cro_webhook_events    = class_exists( 'CRO_Webhook' ) ? CRO_Webhook::event_names() : array();
$cro_webhook_labels    = array(
	'meyvora.campaign.impression'        => __( 'Campaign — Impression', 'meyvora-convert' ),
	'meyvora.campaign.conversion'       => __( 'Campaign — Conversion (order)', 'meyvora-convert' ),
	'meyvora.campaign.dismiss'          => __( 'Campaign — Dismiss', 'meyvora-convert' ),
	'meyvora.offer.applied'             => __( 'Offer — Coupon applied', 'meyvora-convert' ),
	'meyvora.offer.converted'           => __( 'Offer — Order completed', 'meyvora-convert' ),
	'meyvora.abandoned_cart.created'    => __( 'Abandoned cart — Created', 'meyvora-convert' ),
	'meyvora.abandoned_cart.recovered'  => __( 'Abandoned cart — Recovered', 'meyvora-convert' ),
	'meyvora.abandoned_cart.email_sent' => __( 'Abandoned cart — Recovery email sent', 'meyvora-convert' ),
	'meyvora.ab_test.winner_decided'    => __( 'A/B test — Winner decided', 'meyvora-convert' ),
	'meyvora.email.captured'            => __( 'Email captured (popup)', 'meyvora-convert' ),
);
$cro_webhook_wildcards = array(
	'meyvora.campaign.*'       => __( 'All campaign events (impression, conversion, dismiss)', 'meyvora-convert' ),
	'meyvora.offer.*'         => __( 'All offer events', 'meyvora-convert' ),
	'meyvora.abandoned_cart.*' => __( 'All abandoned cart events', 'meyvora-convert' ),
);
?>

<!-- Webhooks -->
<?php if ( class_exists( 'CRO_Webhook' ) ) : ?>
<div class="cro-card cro-developer-section">
	<header class="cro-card__header cro-developer-section__header">
		<span class="cro-section-icon"><?php echo wp_kses( CRO_Icons::svg( 'link', array( 'class' => 'cro-ico' ) ), CRO_Icons::get_svg_kses_allowed() ); ?></span>
		<h2 class="cro-card__title"><?php esc_html_e( 'Webhooks', 'meyvora-convert' ); ?></h2>
	</header>
	<div class="cro-card__body">
		<p class="cro-section-desc"><?php esc_html_e( 'Send signed JSON payloads to your own endpoints when key events occur. Payloads use the meyvora.* event namespace and never include raw email addresses.', 'meyvora-convert' ); ?></p>
		<p>
			<button type="button" class="button button-primary js-cro-webhook-toggle-add"><?php esc_html_e( 'Add endpoint', 'meyvora-convert' ); ?></button>
		</p>

		<div id="cro-webhook-form-panel" class="cro-webhook-form-panel">
			<form id="cro-webhook-endpoint-form">
				<input type="hidden" name="endpoint_id" value="" />
				<p>
					<label><strong><?php esc_html_e( 'Endpoint URL', 'meyvora-convert' ); ?></strong></label><br />
					<input type="url" name="url" class="large-text" required placeholder="https://example.com/hooks/meyvora" />
					<span class="description"><?php esc_html_e( 'HTTPS recommended. Invalid URLs are rejected.', 'meyvora-convert' ); ?></span>
				</p>
				<p>
					<label><strong><?php esc_html_e( 'Signing secret', 'meyvora-convert' ); ?></strong></label><br />
					<input type="text" name="secret" class="large-text" autocomplete="off" placeholder="<?php esc_attr_e( 'Min 16 characters; leave blank when editing to keep current secret', 'meyvora-convert' ); ?>" />
					<button type="button" class="button js-cro-webhook-generate-secret"><?php esc_html_e( 'Generate', 'meyvora-convert' ); ?></button>
					<span class="description"><?php esc_html_e( 'Verify requests with HMAC-SHA256 over the raw JSON body (header X-Meyvora-Signature: sha256=…).', 'meyvora-convert' ); ?></span>
				</p>
				<p>
					<label><input type="checkbox" name="webhook_active" value="1" checked /> <?php esc_html_e( 'Active', 'meyvora-convert' ); ?></label>
				</p>
				<fieldset>
					<legend><strong><?php esc_html_e( 'Events', 'meyvora-convert' ); ?></strong></legend>
					<div class="cro-webhook-events-grid">
						<?php foreach ( $cro_webhook_events as $ev ) : ?>
						<label>
							<input type="checkbox" name="events[]" value="<?php echo esc_attr( $ev ); ?>" />
							<?php echo esc_html( $cro_webhook_labels[ $ev ] ?? $ev ); ?>
						</label>
						<?php endforeach; ?>
						<?php foreach ( $cro_webhook_wildcards as $w => $wlabel ) : ?>
						<label>
							<input type="checkbox" name="events[]" value="<?php echo esc_attr( $w ); ?>" />
							<?php echo esc_html( $wlabel ); ?>
						</label>
						<?php endforeach; ?>
					</div>
				</fieldset>
				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save endpoint', 'meyvora-convert' ); ?></button>
				</p>
			</form>
		</div>

		<table class="widefat striped cro-webhook-endpoints-table" style="margin-top:16px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'URL', 'meyvora-convert' ); ?></th>
					<th><?php esc_html_e( 'Events', 'meyvora-convert' ); ?></th>
					<th><?php esc_html_e( 'Active', 'meyvora-convert' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'meyvora-convert' ); ?></th>
					<th><?php esc_html_e( 'Recent deliveries', 'meyvora-convert' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $cro_webhook_endpoints ) ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No webhook endpoints yet.', 'meyvora-convert' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $cro_webhook_endpoints as $ep ) : ?>
						<?php
						$eid    = isset( $ep['id'] ) ? (string) $ep['id'] : '';
						$eurl   = isset( $ep['url'] ) ? (string) $ep['url'] : '';
						$evs    = isset( $ep['events'] ) && is_array( $ep['events'] ) ? $ep['events'] : array();
						$active = ! empty( $ep['active'] );
						$logs   = ( $eid !== '' && isset( $cro_webhook_logs[ $eid ] ) && is_array( $cro_webhook_logs[ $eid ] ) ) ? $cro_webhook_logs[ $eid ] : array();
						$logs10 = array_slice( $logs, 0, 10 );
						?>
					<tr
						data-id="<?php echo esc_attr( $eid ); ?>"
						data-url="<?php echo esc_attr( $eurl ); ?>"
						data-active="<?php echo $active ? '1' : '0'; ?>"
						data-events="<?php echo esc_attr( wp_json_encode( $evs ) ); ?>"
					>
						<td><code><?php echo esc_html( CRO_Webhook::mask_url( $eurl ) ); ?></code></td>
						<td><?php echo esc_html( (string) count( $evs ) ); ?></td>
						<td><label><input type="checkbox" class="js-cro-webhook-active" <?php checked( $active ); ?> /></label></td>
						<td>
							<button type="button" class="button button-small js-cro-webhook-test"><?php esc_html_e( 'Test', 'meyvora-convert' ); ?></button>
							<button type="button" class="button button-small js-cro-webhook-edit"><?php esc_html_e( 'Edit', 'meyvora-convert' ); ?></button>
							<button type="button" class="button button-small js-cro-webhook-delete"><?php esc_html_e( 'Delete', 'meyvora-convert' ); ?></button>
						</td>
						<td>
							<?php if ( empty( $logs10 ) ) : ?>
								<span class="cro-muted">—</span>
							<?php else : ?>
								<button type="button" class="button-link js-cro-webhook-toggle-logs"><?php esc_html_e( 'Show / hide', 'meyvora-convert' ); ?></button>
								<a href="#" class="js-cro-webhook-view-all-logs" data-id="<?php echo esc_attr( $eid ); ?>"><?php esc_html_e( 'View all logs', 'meyvora-convert' ); ?></a>
								<div class="cro-webhook-log-expand">
									<table class="widefat" style="margin-top:8px;">
										<thead><tr>
											<th><?php esc_html_e( 'Time', 'meyvora-convert' ); ?></th>
											<th><?php esc_html_e( 'Event', 'meyvora-convert' ); ?></th>
											<th><?php esc_html_e( 'Status', 'meyvora-convert' ); ?></th>
											<th><?php esc_html_e( 'ms', 'meyvora-convert' ); ?></th>
										</tr></thead>
										<tbody>
										<?php foreach ( $logs10 as $log ) : ?>
											<?php
											$st = isset( $log['status'] ) ? (int) $log['status'] : 0;
											$ok = $st >= 200 && $st < 300;
											$ts = isset( $log['t'] ) ? (int) $log['t'] : 0;
											?>
											<tr class="<?php echo $ok ? 'cro-webhook-log--ok' : 'cro-webhook-log--err'; ?>">
												<td><?php echo esc_html( $ts > 0 ? wp_date( 'Y-m-d H:i:s', $ts ) : '—' ); ?></td>
												<td><?php echo esc_html( isset( $log['event'] ) ? (string) $log['event'] : '' ); ?></td>
												<td><?php echo esc_html( (string) $st ); ?></td>
												<td><?php echo esc_html( isset( $log['ms'] ) ? (string) (int) $log['ms'] : '0' ); ?></td>
											</tr>
											<?php if ( ! empty( $log['error'] ) ) : ?>
											<tr><td colspan="4" style="font-size:12px;color:#c5221f;"><?php echo esc_html( (string) $log['error'] ); ?></td></tr>
											<?php endif; ?>
										<?php endforeach; ?>
										</tbody>
									</table>
								</div>
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<div id="cro-webhook-logs-modal" class="cro-webhook-logs-modal" hidden role="dialog" aria-modal="true" aria-labelledby="cro-webhook-logs-modal-title">
	<div class="cro-webhook-logs-modal__box">
		<div class="cro-webhook-logs-modal__head">
			<h3 id="cro-webhook-logs-modal-title"><?php esc_html_e( 'Delivery log (last 50)', 'meyvora-convert' ); ?></h3>
			<button type="button" class="button js-cro-webhook-logs-modal-close"><?php esc_html_e( 'Close', 'meyvora-convert' ); ?></button>
		</div>
		<div class="cro-webhook-logs-modal__body"></div>
	</div>
</div>
<?php endif; ?>

<!-- Template overrides -->
<div class="cro-card cro-developer-section">
		<header class="cro-card__header cro-developer-section__header">
			<span class="cro-section-icon"><?php echo wp_kses( CRO_Icons::svg( 'file', array( 'class' => 'cro-ico' ) ), CRO_Icons::get_svg_kses_allowed() ); ?></span>

			<h2 class="cro-card__title"><?php esc_html_e( 'Template overrides', 'meyvora-convert' ); ?></h2>
		</header>
		<div class="cro-card__body">
			<p class="cro-section-desc"><?php esc_html_e( 'Campaign popup templates can be overridden by copying them into your theme.', 'meyvora-convert' ); ?></p>
			<div class="cro-developer-code-block cro-mb-2">
				<code>get_stylesheet_directory() . '/meyvora-convert/templates/<var>template-key</var>.php'</code>
			</div>
			<p><?php esc_html_e( 'Example: to override the centered popup, create this file in your theme:', 'meyvora-convert' ); ?></p>
			<div class="cro-developer-code-block cro-mb-2">
				<code>wp-content/themes/your-theme/meyvora-convert/templates/centered.php</code>
			</div>
			<p><?php esc_html_e( 'Available template keys match the popup names in the campaign builder (e.g. centered, corner, slide-bottom, top-bar). The plugin falls back to the built-in template if the file is missing.', 'meyvora-convert' ); ?></p>
			<p class="cro-mt-2">
				<strong><?php esc_html_e( 'Filter:', 'meyvora-convert' ); ?></strong>
				<code>cro_popup_template</code> — <?php esc_html_e( 'Change the template file path programmatically. Params: $template_path, $template_type.', 'meyvora-convert' ); ?>
			</p>
		</div>
	</div>

	<!-- Actions -->
	<div class="cro-card cro-developer-section">
		<header class="cro-card__header cro-developer-section__header">
			<span class="cro-section-icon"><?php echo wp_kses( CRO_Icons::svg( 'zap', array( 'class' => 'cro-ico' ) ), CRO_Icons::get_svg_kses_allowed() ); ?></span>

			<h2 class="cro-card__title"><?php esc_html_e( 'Actions', 'meyvora-convert' ); ?></h2>
		</header>
		<div class="cro-card__body">
			<p class="cro-section-desc"><?php esc_html_e( 'Fire your code at key points. Use add_action( \'hook_name\', $callback, $priority, $accepted_args ).', 'meyvora-convert' ); ?></p>
			<div class="cro-developer-hooks-list">
				<?php foreach ( $actions as $hook_name => $info ) : ?>
					<?php
					$desc   = isset( $info['description'] ) ? $info['description'] : '';
					$params = isset( $info['params'] ) ? $info['params'] : array();
					$example = isset( $info['example'] ) ? $info['example'] : '';
					?>
					<div class="cro-developer-hook-item">
						<code class="cro-developer-hook-name"><?php echo esc_html( $hook_name ); ?></code>
						<?php
						if ( ! empty( $params ) ) {
							$param_names = array_map( function ( $v, $k ) {
								return is_int( $k ) ? $v : $k;
							}, $params, array_keys( $params ) );
							?>
							<span class="cro-developer-hook-params">( <?php echo esc_html( implode( ', ', $param_names ) ); ?> )</span>
						<?php } ?>
						<?php if ( $desc !== '' ) : ?>
							<p class="cro-developer-hook-desc"><?php echo esc_html( $desc ); ?></p>
						<?php endif; ?>
						<?php if ( $example !== '' ) : ?>
							<pre class="cro-developer-snippet"><code><?php echo esc_html( $example ); ?></code></pre>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<!-- Filters -->
	<div class="cro-card cro-developer-section">
		<header class="cro-card__header cro-developer-section__header">
			<span class="cro-section-icon"><?php echo wp_kses( CRO_Icons::svg( 'settings', array( 'class' => 'cro-ico' ) ), CRO_Icons::get_svg_kses_allowed() ); ?></span>

			<h2 class="cro-card__title"><?php esc_html_e( 'Filters', 'meyvora-convert' ); ?></h2>
		</header>
		<div class="cro-card__body">
			<p class="cro-section-desc"><?php esc_html_e( 'Modify data before it is used. Use add_filter( \'hook_name\', $callback, $priority, $accepted_args ). Return the modified value.', 'meyvora-convert' ); ?></p>
			<div class="cro-developer-hooks-list">
				<?php foreach ( $filters as $hook_name => $info ) : ?>
					<?php
					$desc   = isset( $info['description'] ) ? $info['description'] : '';
					$params = isset( $info['params'] ) ? $info['params'] : array();
					$return = isset( $info['return'] ) ? $info['return'] : '';
					$example = isset( $info['example'] ) ? $info['example'] : '';
					?>
					<div class="cro-developer-hook-item">
						<code class="cro-developer-hook-name"><?php echo esc_html( $hook_name ); ?></code>
						<?php
						if ( ! empty( $params ) ) {
							$param_names = array_map( function ( $v, $k ) {
								return is_int( $k ) ? $v : $k;
							}, $params, array_keys( $params ) );
							?>
							<span class="cro-developer-hook-params">( <?php echo esc_html( implode( ', ', $param_names ) ); ?> )</span>
						<?php } ?>
						<?php if ( $return !== '' ) : ?>
							<span class="cro-developer-hook-return">→ <?php echo esc_html( $return ); ?></span>
						<?php endif; ?>
						<?php if ( $desc !== '' ) : ?>
							<p class="cro-developer-hook-desc"><?php echo esc_html( $desc ); ?></p>
						<?php endif; ?>
						<?php if ( $example !== '' ) : ?>
							<pre class="cro-developer-snippet"><code><?php echo esc_html( $example ); ?></code></pre>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<!-- Example snippets -->
	<div class="cro-card cro-developer-section">
		<header class="cro-card__header cro-developer-section__header">
			<span class="cro-section-icon"><?php echo wp_kses( CRO_Icons::svg( 'edit', array( 'class' => 'cro-ico' ) ), CRO_Icons::get_svg_kses_allowed() ); ?></span>

			<h2 class="cro-card__title"><?php esc_html_e( 'Example snippets', 'meyvora-convert' ); ?></h2>
		</header>
		<div class="cro-card__body">
			<div class="cro-developer-snippets-grid">
				<div class="cro-developer-snippet-card">
					<h4><?php esc_html_e( 'Add custom admin tab', 'meyvora-convert' ); ?></h4>
					<pre class="cro-developer-snippet"><code>add_filter( 'cro_admin_tabs', function( $tabs ) {
	$tabs['my-tab'] = array(
		'label' => 'My Tab',
		'url'   => admin_url( 'admin.php?page=my-tab' )
	);
	return $tabs;
});</code></pre>
				</div>
				<div class="cro-developer-snippet-card">
					<h4><?php esc_html_e( 'Track when campaign is shown', 'meyvora-convert' ); ?></h4>
					<pre class="cro-developer-snippet"><code>add_action( 'cro_campaign_shown', function( $campaign_id, $visitor_id ) {
	// Send to analytics
	gtag( 'event', 'cro_popup_shown', array( 'campaign_id' => $campaign_id ) );
}, 10, 2);</code></pre>
				</div>
				<div class="cro-developer-snippet-card">
					<h4><?php esc_html_e( 'Modify offer context', 'meyvora-convert' ); ?></h4>
					<pre class="cro-developer-snippet"><code>add_filter( 'cro_offer_context', function( $context ) {
	$context['custom_field'] = get_user_meta( get_current_user_id(), 'my_meta', true );
	return $context;
});</code></pre>
				</div>
				<div class="cro-developer-snippet-card">
					<h4><?php esc_html_e( 'Override popup template path', 'meyvora-convert' ); ?></h4>
					<pre class="cro-developer-snippet"><code>add_filter( 'cro_popup_template', function( $path, $template_type ) {
	if ( $template_type === 'centered' ) {
		return get_stylesheet_directory() . '/my-popup.php';
	}
	return $path;
}, 10, 2);</code></pre>
				</div>
			</div>
		</div>
	</div>
