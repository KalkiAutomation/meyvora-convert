<?php
/**
 * Admin page: Abandoned Carts list – table, filters, search, pagination, row actions, detail drawer.
 *
 * @package Meyvora_Convert
 */
// phpcs:disable WordPress.Security.NonceVerification.Recommended

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'CRO_Abandoned_Cart_Tracker' ) ) {
	echo '<div class="cro-admin-message"><p>' . esc_html__( 'Abandoned cart module is not available.', 'meyvora-convert' ) . '</p></div>';
	return;
}

$status_filter = isset( $_GET['status_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['status_filter'] ) ) : 'all';
$segment_filter = isset( $_GET['segment'] ) ? sanitize_key( wp_unslash( $_GET['segment'] ) ) : 'all';
$segment_filter = in_array( $segment_filter, array( 'all', 'high', 'standard' ), true ) ? $segment_filter : 'all';
$search        = isset( $_GET['search'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['search'] ) ) ) : '';
$paged         = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$per_page      = 20;

$result = CRO_Abandoned_Cart_Tracker::get_list( array(
	'status_filter' => $status_filter,
	'segment'       => $segment_filter,
	'search'        => $search,
	'per_page'      => $per_page,
	'page'          => $paged,
) );
$items   = $result['items'];
$total   = $result['total'];
$pages   = $total > 0 ? (int) ceil( $total / $per_page ) : 0;

$list_url = admin_url( 'admin.php?page=cro-abandoned-carts' );
$nonce = wp_create_nonce( 'cro_abandoned_carts_list' );
$action_query = array( '_wpnonce' => $nonce );
if ( $status_filter !== 'all' ) {
	$action_query['status_filter'] = $status_filter;
}
if ( $search !== '' ) {
	$action_query['search'] = $search;
}
if ( $paged > 1 ) {
	$action_query['paged'] = $paged;
}
if ( $segment_filter !== 'all' ) {
	$action_query['segment'] = $segment_filter;
}
$cancel_url = add_query_arg( $action_query, admin_url( 'admin-post.php?action=cro_abandoned_cart_cancel_reminders' ) );
$recovered_url = add_query_arg( $action_query, admin_url( 'admin-post.php?action=cro_abandoned_cart_mark_recovered' ) );
$resend_url = add_query_arg( $action_query, admin_url( 'admin-post.php?action=cro_abandoned_cart_resend' ) );

$cro_notice = isset( $_GET['cro_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['cro_notice'] ) ) : '';
$notices = array(
	'cancel_reminders' => __( 'Reminders cancelled.', 'meyvora-convert' ),
	'mark_recovered'   => __( 'Cart marked as recovered.', 'meyvora-convert' ),
	'resend_ok'        => __( 'Reminder email sent.', 'meyvora-convert' ),
	'resend_fail'      => __( 'Could not send reminder (cart may be recovered or ineligible).', 'meyvora-convert' ),
);
$currency_symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';

/**
 * Helper: count emails sent for a row.
 *
 * @param object $row DB row.
 * @return int
 */
$count_emails_sent = function( $row ) {
	$n = 0;
	if ( ! empty( $row->email_1_sent_at ) ) { $n++; }
	if ( ! empty( $row->email_2_sent_at ) ) { $n++; }
	if ( ! empty( $row->email_3_sent_at ) ) { $n++; }
	return $n;
};

/**
 * Helper: cart total and item count from cart_json.
 *
 * @param object $row DB row.
 * @return array{ total: float|null, count: int }
 */
$cart_info = function( $row ) {
	$total = null;
	$count = 0;
	if ( ! empty( $row->cart_json ) ) {
		$data = json_decode( $row->cart_json, true );
		if ( is_array( $data ) ) {
			$total = isset( $data['totals']['total'] ) ? (float) $data['totals']['total'] : null;
			if ( ! empty( $data['items'] ) && is_array( $data['items'] ) ) {
				foreach ( $data['items'] as $item ) {
					$count += isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
				}
			}
		}
	}
	return array( 'total' => $total, 'count' => $count );
};

/**
 * Helper: can resend (active, has email, consent).
 *
 * @param object $row DB row.
 * @return bool
 */
$can_resend = function( $row ) {
	if ( $row->status !== CRO_Abandoned_Cart_Tracker::STATUS_ACTIVE ) {
		return false;
	}
	if ( empty( $row->email_consent ) || empty( $row->email ) || ! is_email( $row->email ) ) {
		return false;
	}
	if ( ! empty( $row->recovered_at ) ) {
		return false;
	}
	return true;
};
?>

			<?php if ( $cro_notice && isset( $notices[ $cro_notice ] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notices[ $cro_notice ] ); ?></p></div>
			<?php endif; ?>

			<div class="cro-card">
				<div class="cro-card__body">
			<div class="cro-ac-list-toolbar">
				<ul class="cro-ac-list-filters" role="tablist">
					<li><a href="<?php echo esc_url( add_query_arg( array( 'status_filter' => 'all', 'paged' => 1, 'segment' => $segment_filter ), $list_url ) ); ?>" class="button <?php echo $status_filter === 'all' ? 'button-primary' : ''; ?>"><?php esc_html_e( 'All', 'meyvora-convert' ); ?></a></li>
					<li><a href="<?php echo esc_url( add_query_arg( array( 'status_filter' => 'active', 'paged' => 1, 'segment' => $segment_filter ), $list_url ) ); ?>" class="button <?php echo $status_filter === 'active' ? 'button-primary' : ''; ?>"><?php esc_html_e( 'Active', 'meyvora-convert' ); ?></a></li>
					<li><a href="<?php echo esc_url( add_query_arg( array( 'status_filter' => 'emailed', 'paged' => 1, 'segment' => $segment_filter ), $list_url ) ); ?>" class="button <?php echo $status_filter === 'emailed' ? 'button-primary' : ''; ?>"><?php esc_html_e( 'Emailed', 'meyvora-convert' ); ?></a></li>
					<li><a href="<?php echo esc_url( add_query_arg( array( 'status_filter' => 'recovered', 'paged' => 1, 'segment' => $segment_filter ), $list_url ) ); ?>" class="button <?php echo $status_filter === 'recovered' ? 'button-primary' : ''; ?>"><?php esc_html_e( 'Recovered', 'meyvora-convert' ); ?></a></li>
				</ul>
				<form method="get" class="cro-ac-segment-filter" action="<?php echo esc_url( $list_url ); ?>">
					<input type="hidden" name="page" value="cro-abandoned-carts" />
					<?php if ( $status_filter !== 'all' ) : ?>
						<input type="hidden" name="status_filter" value="<?php echo esc_attr( $status_filter ); ?>" />
					<?php endif; ?>
					<?php if ( $search !== '' ) : ?>
						<input type="hidden" name="search" value="<?php echo esc_attr( $search ); ?>" />
					<?php endif; ?>
					<label for="cro-ac-segment" class="screen-reader-text"><?php esc_html_e( 'Segment', 'meyvora-convert' ); ?></label>
					<select name="segment" id="cro-ac-segment" onchange="this.form.submit()">
						<option value="all" <?php selected( $segment_filter, 'all' ); ?>><?php esc_html_e( 'All segments', 'meyvora-convert' ); ?></option>
						<option value="high" <?php selected( $segment_filter, 'high' ); ?>><?php esc_html_e( 'High Value', 'meyvora-convert' ); ?></option>
						<option value="standard" <?php selected( $segment_filter, 'standard' ); ?>><?php esc_html_e( 'Standard', 'meyvora-convert' ); ?></option>
					</select>
				</form>
				<form method="get" class="cro-ac-list-search" action="<?php echo esc_url( $list_url ); ?>">
					<input type="hidden" name="page" value="cro-abandoned-carts" />
					<?php if ( $status_filter !== 'all' ) : ?>
						<input type="hidden" name="status_filter" value="<?php echo esc_attr( $status_filter ); ?>" />
					<?php endif; ?>
					<?php if ( $segment_filter !== 'all' ) : ?>
						<input type="hidden" name="segment" value="<?php echo esc_attr( $segment_filter ); ?>" />
					<?php endif; ?>
					<label for="cro-ac-search" class="screen-reader-text"><?php esc_html_e( 'Search by email', 'meyvora-convert' ); ?></label>
					<input type="search" id="cro-ac-search" name="search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by email…', 'meyvora-convert' ); ?>" />
					<button type="submit" class="button"><?php esc_html_e( 'Search', 'meyvora-convert' ); ?></button>
				</form>
			</div>

			<?php if ( empty( $items ) ) : ?>
					<div class="cro-ui-empty-state">
						<span class="cro-ui-empty-state__icon" aria-hidden="true"><?php echo CRO_Icons::svg_kses( 'shopping-cart', array( 'class' => 'cro-ico' ) ); ?></span>

						<h2 class="cro-ui-empty-state__title"><?php esc_html_e( 'No abandoned carts', 'meyvora-convert' ); ?></h2>
						<p class="cro-ui-empty-state__desc"><?php esc_html_e( 'No carts match your filters. Carts will appear here when customers leave items without checking out.', 'meyvora-convert' ); ?></p>
						<div class="cro-ui-empty-state__actions">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=cro-abandoned-cart' ) ); ?>" class="button button-primary cro-ui-btn-primary"><?php esc_html_e( 'Configure email reminders', 'meyvora-convert' ); ?></a>
						</div>
					</div>
			<?php else : ?>
				<div class="cro-table-wrap cro-ac-list-table-wrap">
					<table class="cro-table cro-ac-list-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Email / User', 'meyvora-convert' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Cart Total', 'meyvora-convert' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Segment', 'meyvora-convert' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Items', 'meyvora-convert' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Last Activity', 'meyvora-convert' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Status', 'meyvora-convert' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Emails Sent', 'meyvora-convert' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Coupon', 'meyvora-convert' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Actions', 'meyvora-convert' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $items as $row ) :
								$info = $cart_info( $row );
								$emails_sent = $count_emails_sent( $row );
								$allow_resend = $can_resend( $row );
								$seg = class_exists( 'CRO_Abandoned_Cart_Reminder' ) ? CRO_Abandoned_Cart_Reminder::get_segment_for_row( $row ) : 'standard';
							?>
								<tr data-id="<?php echo esc_attr( $row->id ); ?>">
									<td>
										<?php echo esc_html( $row->email ? $row->email : '—' ); ?>
										<?php if ( ! empty( $row->user_id ) ) : ?>
											<br><span class="cro-ac-user-id">ID: <?php echo absint( $row->user_id ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<?php
										if ( $info['total'] !== null && $currency_symbol ) {
											echo esc_html( $currency_symbol . number_format_i18n( $info['total'], 2 ) );
										} else {
											echo '—';
										}
										?>
									</td>
									<td>
										<?php if ( $seg === 'high' ) : ?>
											<span class="cro-ac-segment cro-ac-segment--high"><?php esc_html_e( 'High Value', 'meyvora-convert' ); ?></span>
										<?php else : ?>
											<span class="cro-ac-segment cro-ac-segment--standard"><?php esc_html_e( 'Standard', 'meyvora-convert' ); ?></span>
										<?php endif; ?>
									</td>
									<td><?php echo absint( $info['count'] ); ?></td>
									<td><?php echo $row->last_activity_at ? esc_html( $row->last_activity_at ) : '—'; ?></td>
									<td>
										<span class="cro-ac-status cro-ac-status--<?php echo esc_attr( $row->status ); ?>"><?php echo esc_html( ucfirst( $row->status ) ); ?></span>
									</td>
									<td><?php echo absint( $emails_sent ); ?></td>
									<td><?php echo $row->discount_coupon ? esc_html( $row->discount_coupon ) : '—'; ?></td>
									<td class="cro-table-actions cro-ac-actions">
										<button type="button" class="cro-table-action-link cro-ac-btn-detail" data-id="<?php echo esc_attr( $row->id ); ?>"><?php esc_html_e( 'View', 'meyvora-convert' ); ?></button>
										<?php if ( $allow_resend ) : ?>
											<a href="<?php echo esc_url( add_query_arg( array( 'id' => $row->id, '_wpnonce' => $nonce ), $resend_url ) ); ?>"><?php esc_html_e( 'Resend', 'meyvora-convert' ); ?></a>
										<?php endif; ?>
										<?php if ( $row->status === CRO_Abandoned_Cart_Tracker::STATUS_ACTIVE ) : ?>
											<a href="<?php echo esc_url( add_query_arg( array( 'id' => $row->id, '_wpnonce' => $nonce ), $cancel_url ) ); ?>"><?php esc_html_e( 'Cancel reminders', 'meyvora-convert' ); ?></a>
											<a href="<?php echo esc_url( add_query_arg( array( 'id' => $row->id, '_wpnonce' => $nonce ), $recovered_url ) ); ?>"><?php esc_html_e( 'Mark recovered', 'meyvora-convert' ); ?></a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<?php if ( $pages > 1 ) : ?>
					<?php
					$paginate_args = array( 'page' => 'cro-abandoned-carts' );
					if ( $status_filter !== 'all' ) {
						$paginate_args['status_filter'] = $status_filter;
					}
					if ( $segment_filter !== 'all' ) {
						$paginate_args['segment'] = $segment_filter;
					}
					if ( $search !== '' ) {
						$paginate_args['search'] = $search;
					}
					$prev_url = $paged > 1 ? add_query_arg( array_merge( $paginate_args, array( 'paged' => $paged - 1 ) ), $list_url ) : '';
					$next_url = $paged < $pages ? add_query_arg( array_merge( $paginate_args, array( 'paged' => $paged + 1 ) ), $list_url ) : '';
					?>
					<div class="cro-ac-pagination tablenav bottom">
						<div class="tablenav-pages">
							<span class="displaying-num"><?php echo esc_html( sprintf( /* translators: %s is the formatted number of items. */ _n( '%s item', '%s items', $total, 'meyvora-convert' ), number_format_i18n( $total ) ) ); ?></span>
							<span class="pagination-links">
								<?php if ( $prev_url ) : ?>
									<a class="prev-page button" href="<?php echo esc_url( $prev_url ); ?>"><?php esc_html_e( '&laquo; Previous', 'meyvora-convert' ); ?></a>
								<?php else : ?>
									<span class="tablenav-pages-navspan button disabled"><?php esc_html_e( '&laquo; Previous', 'meyvora-convert' ); ?></span>
								<?php endif; ?>
								<span class="paging-input">
									<label for="current-page-selector" class="screen-reader-text"><?php esc_html_e( 'Current page', 'meyvora-convert' ); ?></label>
									<span class="tablenav-paging-text"><?php echo esc_html( $paged ); ?> <?php esc_html_e( 'of', 'meyvora-convert' ); ?> <span class="total-pages"><?php echo esc_html( $pages ); ?></span></span>
								</span>
								<?php if ( $next_url ) : ?>
									<a class="next-page button" href="<?php echo esc_url( $next_url ); ?>"><?php esc_html_e( 'Next &raquo;', 'meyvora-convert' ); ?></a>
								<?php else : ?>
									<span class="tablenav-pages-navspan button disabled"><?php esc_html_e( 'Next &raquo;', 'meyvora-convert' ); ?></span>
								<?php endif; ?>
							</span>
						</div>
					</div>
				<?php endif; ?>
				</div><!-- .cro-card__body -->
			</div><!-- .cro-card -->
			<?php endif; ?>

	<!-- Detail drawer -->
	<div id="cro-ac-drawer" class="cro-ac-drawer" role="dialog" aria-modal="true" aria-labelledby="cro-ac-drawer-title" hidden>
		<div class="cro-ac-drawer__backdrop"></div>
		<div class="cro-ac-drawer__panel">
			<header class="cro-ac-drawer__header">
				<h2 id="cro-ac-drawer-title"><?php esc_html_e( 'Cart details', 'meyvora-convert' ); ?></h2>
				<button type="button" class="cro-ac-drawer__close" aria-label="<?php esc_attr_e( 'Close', 'meyvora-convert' ); ?>"><?php echo CRO_Icons::svg_kses( 'x', array( 'class' => 'cro-ico' ) ); ?></button>

			</header>
			<div class="cro-ac-drawer__body">
				<div id="cro-ac-drawer-loading" class="cro-ac-drawer__loading"><?php esc_html_e( 'Loading…', 'meyvora-convert' ); ?></div>
				<div id="cro-ac-drawer-content" class="cro-ac-drawer__content cro-hidden"></div>
			</div>
		</div>
	</div>

<style>
.cro-ac-list-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 16px; margin-bottom: 24px; }
.cro-ac-list-filters { list-style: none; margin: 0; padding: 0; display: flex; gap: 8px; }
.cro-ac-list-filters li { margin: 0; }
.cro-ac-segment-filter select { min-width: 140px; }
.cro-ac-segment { display: inline-block; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 3px; text-transform: uppercase; letter-spacing: 0.02em; }
.cro-ac-segment--high { background: #fff8e5; color: #996800; border: 1px solid #f0c14b; }
.cro-ac-segment--standard { background: #f0f0f1; color: #50575e; border: 1px solid #c3c4c7; }
.cro-ac-list-search { display: flex; gap: 8px; margin-left: auto; }
.cro-ac-list-search input[type="search"] { min-width: 200px; }
.cro-ac-list-toolbar + .cro-ui-empty-state { margin-top: 0; }
.cro-ac-list-table-wrap { margin-top: 0; }
.cro-ac-actions .cro-ac-btn-detail { font-size: 12px; }
.cro-ac-status--active { color: #00a32a; }
.cro-ac-status--recovered { color: #2271b1; }
.cro-ac-status--emailed { color: #d63638; }
.cro-ac-user-id { font-size: 11px; color: #646970; }
.cro-ac-pagination { margin-top: 24px; }
.cro-ac-drawer { position: fixed; inset: 0; z-index: 100000; display: flex; align-items: stretch; justify-content: flex-end; visibility: hidden; }
.cro-ac-drawer[aria-hidden="false"] { visibility: visible; }
.cro-ac-drawer__backdrop { position: absolute; inset: 0; background: rgba(0,0,0,0.4); }
.cro-ac-drawer__panel { position: relative; width: 100%; max-width: 440px; background: #fff; box-shadow: -4px 0 20px rgba(0,0,0,0.15); display: flex; flex-direction: column; }
.cro-ac-drawer__header { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid #c3c4c7; }
.cro-ac-drawer__header h2 { margin: 0; font-size: 1.25rem; }
.cro-ac-drawer__close { background: none; border: none; font-size: 24px; line-height: 1; cursor: pointer; padding: 4px; color: #50575e; }
.cro-ac-drawer__body { flex: 1; overflow-y: auto; padding: 20px; }
.cro-ac-drawer__loading { color: #646970; }
.cro-ac-drawer__content h3 { margin: 16px 0 8px; font-size: 13px; }
.cro-ac-drawer__content h3:first-child { margin-top: 0; }
.cro-ac-drawer-segment { margin: 4px 0 12px; }
.cro-ac-drawer__content ul { margin: 0 0 12px; padding-left: 20px; }
.cro-ac-drawer__content .cro-ac-drawer-checkout { display: inline-block; margin-top: 8px; }
</style>

<script>
(function() {
	var listUrl = <?php echo wp_json_encode( $list_url ); ?>;
	var nonce = <?php echo wp_json_encode( $nonce ); ?>;
	var drawer = document.getElementById('cro-ac-drawer');
	var drawerContent = document.getElementById('cro-ac-drawer-content');
	var drawerLoading = document.getElementById('cro-ac-drawer-loading');
	var closeBtn = drawer && drawer.querySelector('.cro-ac-drawer__close');
	var backdrop = drawer && drawer.querySelector('.cro-ac-drawer__backdrop');

	function openDrawer() {
		if (!drawer) return;
		drawer.removeAttribute('hidden');
		drawer.setAttribute('aria-hidden', 'false');
		document.body.style.overflow = 'hidden';
	}
	function closeDrawer() {
		if (!drawer) return;
		drawer.setAttribute('hidden', '');
		drawer.setAttribute('aria-hidden', 'true');
		document.body.style.overflow = '';
	}
	function renderDrawer(data) {
		if (!drawerContent) return;
		var currency = data.currency || '';
		var total = data.cart_total != null ? currency + ' ' + Number(data.cart_total).toFixed(2) : '—';
		var html = '<p><strong>' + (data.email || '—') + '</strong></p>';
		if (data.segment_label) {
			var segClass = data.segment === 'high' ? 'cro-ac-segment cro-ac-segment--high' : 'cro-ac-segment cro-ac-segment--standard';
			html += '<p class="cro-ac-drawer-segment"><strong><?php echo esc_js( __( 'Segment', 'meyvora-convert' ) ); ?>:</strong> <span class="' + segClass + '">' + String(data.segment_label) + '</span></p>';
		}
		if (data.schedule && data.schedule.planned_local) {
			var pl = data.schedule.planned_local;
			html += '<h3><?php echo esc_js( __( 'Planned send times (from last activity)', 'meyvora-convert' ) ); ?></h3><ul>';
			html += '<li><?php echo esc_js( __( 'Email 1', 'meyvora-convert' ) ); ?>: ' + (pl[1] || '—') + '</li>';
			html += '<li><?php echo esc_js( __( 'Email 2', 'meyvora-convert' ) ); ?>: ' + (pl[2] || '—') + '</li>';
			html += '<li><?php echo esc_js( __( 'Email 3', 'meyvora-convert' ) ); ?>: ' + (pl[3] || '—') + '</li></ul>';
		}
		html += '<h3><?php echo esc_js( __( 'Cart items', 'meyvora-convert' ) ); ?></h3>';
		if (data.cart_items && data.cart_items.length) {
			html += '<ul>';
			data.cart_items.forEach(function(it) {
				html += '<li>' + (it.name || '') + ' × ' + (it.quantity || 1) + '</li>';
			});
			html += '</ul><p><strong><?php echo esc_js( __( 'Total', 'meyvora-convert' ) ); ?>:</strong> ' + total + '</p>';
		} else {
			html += '<p>—</p>';
		}
		html += '<h3><?php echo esc_js( __( 'Checkout link', 'meyvora-convert' ) ); ?></h3>';
		html += '<a href="' + (data.checkout_url || '#') + '" class="button button-primary cro-ac-drawer-checkout" target="_blank" rel="noopener"><?php echo esc_js( __( 'Open checkout', 'meyvora-convert' ) ); ?></a>';
		html += '<h3><?php echo esc_js( __( 'Email log', 'meyvora-convert' ) ); ?></h3><ul>';
		var log = data.email_log || {};
		html += '<li>Email 1: ' + (log.email_1 || '<?php echo esc_js( __( 'Not sent', 'meyvora-convert' ) ); ?>') + '</li>';
		html += '<li>Email 2: ' + (log.email_2 || '<?php echo esc_js( __( 'Not sent', 'meyvora-convert' ) ); ?>') + '</li>';
		html += '<li>Email 3: ' + (log.email_3 || '<?php echo esc_js( __( 'Not sent', 'meyvora-convert' ) ); ?>') + '</li></ul>';
		html += '<h3><?php echo esc_js( __( 'Coupon', 'meyvora-convert' ) ); ?></h3><p>' + (data.discount_coupon || '—') + '</p>';
		var cid = parseInt(data.id, 10) || 0;
		if (cid) {
			html += '<h3><?php echo esc_js( __( 'AI email preview', 'meyvora-convert' ) ); ?></h3>';
			html += '<p class="description" style="margin-bottom:8px;"><?php echo esc_js( __( 'Uses the same AI content as live sends (cached 24 hours). Regenerate clears cache for that slot.', 'meyvora-convert' ) ); ?></p>';
			html += '<p style="margin:0 0 8px;">';
			html += '<button type="button" class="button button-small cro-ai-preview-email" data-cart-id="' + cid + '" data-email="1"><?php echo esc_js( __( '✦ Preview AI Email 1', 'meyvora-convert' ) ); ?></button> ';
			html += '<button type="button" class="button button-small cro-ai-preview-email" data-cart-id="' + cid + '" data-email="2"><?php echo esc_js( __( '✦ Preview AI Email 2', 'meyvora-convert' ) ); ?></button> ';
			html += '<button type="button" class="button button-small cro-ai-preview-email" data-cart-id="' + cid + '" data-email="3"><?php echo esc_js( __( '✦ Preview AI Email 3', 'meyvora-convert' ) ); ?></button>';
			html += '</p>';
		}
		drawerContent.innerHTML = html;
		drawerContent.style.display = 'block';
		drawerLoading.style.display = 'none';
	}

	document.addEventListener('click', function(e) {
		var btn = e.target && e.target.closest('.cro-ac-btn-detail');
		if (!btn || !btn.dataset.id) return;
		e.preventDefault();
		var id = btn.dataset.id;
		openDrawer();
		drawerContent.style.display = 'none';
		drawerLoading.style.display = 'block';
		var formData = new FormData();
		formData.append('action', 'cro_abandoned_cart_drawer');
		formData.append('nonce', nonce);
		formData.append('id', id);
		fetch(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, { method: 'POST', body: formData, credentials: 'same-origin' })
			.then(function(r) { return r.json(); })
			.then(function(res) {
				if (res.success && res.data) renderDrawer(res.data);
				else { drawerContent.innerHTML = '<p><?php echo esc_js( __( 'Could not load details.', 'meyvora-convert' ) ); ?></p>'; drawerContent.style.display = 'block'; drawerLoading.style.display = 'none'; }
			})
			.catch(function() {
				drawerContent.innerHTML = '<p><?php echo esc_js( __( 'Request failed.', 'meyvora-convert' ) ); ?></p>';
				drawerContent.style.display = 'block';
				drawerLoading.style.display = 'none';
			});
	});

	if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
	if (backdrop) backdrop.addEventListener('click', closeDrawer);
})();
</script>
