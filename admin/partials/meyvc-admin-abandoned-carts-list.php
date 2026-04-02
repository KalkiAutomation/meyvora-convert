<?php
/**
 * Admin page: Abandoned Carts list – table, filters, search, pagination, row actions, detail drawer.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'MEYVC_Abandoned_Cart_Tracker' ) ) {
	echo '<div class="meyvc-admin-message"><p>' . esc_html__( 'Abandoned cart module is not available.', 'meyvora-convert' ) . '</p></div>';
	return;
}

$status_filter = MEYVC_Security::get_query_var( 'status_filter' );
$status_filter = $status_filter !== '' ? $status_filter : 'all';
$segment_filter = MEYVC_Security::get_query_var_key( 'segment' );
$segment_filter = in_array( $segment_filter, array( 'all', 'high', 'standard' ), true ) ? $segment_filter : 'all';
$search        = trim( MEYVC_Security::get_query_var( 'search' ) );
$paged         = max( 1, MEYVC_Security::get_query_var_absint( 'paged' ) );
$per_page      = 20;

$result = MEYVC_Abandoned_Cart_Tracker::get_list( array(
	'status_filter' => $status_filter,
	'segment'       => $segment_filter,
	'search'        => $search,
	'per_page'      => $per_page,
	'page'          => $paged,
) );
$items   = $result['items'];
$total   = $result['total'];
$pages   = $total > 0 ? (int) ceil( $total / $per_page ) : 0;

$list_url = admin_url( 'admin.php?page=meyvc-abandoned-carts' );
$nonce = wp_create_nonce( 'meyvc_abandoned_carts_list' );
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
$cancel_url = add_query_arg( $action_query, admin_url( 'admin-post.php?action=meyvc_abandoned_cart_cancel_reminders' ) );
$recovered_url = add_query_arg( $action_query, admin_url( 'admin-post.php?action=meyvc_abandoned_cart_mark_recovered' ) );
$resend_url = add_query_arg( $action_query, admin_url( 'admin-post.php?action=meyvc_abandoned_cart_resend' ) );

$meyvc_notice = MEYVC_Security::get_query_var( 'meyvc_notice' );
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
	if ( $row->status !== MEYVC_Abandoned_Cart_Tracker::STATUS_ACTIVE ) {
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

			<?php if ( $meyvc_notice && isset( $notices[ $meyvc_notice ] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notices[ $meyvc_notice ] ); ?></p></div>
			<?php endif; ?>

			<div class="meyvc-card">
				<div class="meyvc-card__body">
			<div class="meyvc-ac-list-toolbar">
				<div class="meyvc-ac-list-toolbar__primary">
					<ul class="meyvc-ac-list-filters" role="tablist">
						<li><a href="<?php echo esc_url( add_query_arg( array( 'status_filter' => 'all', 'paged' => 1, 'segment' => $segment_filter ), $list_url ) ); ?>" class="button <?php echo $status_filter === 'all' ? 'button-primary' : ''; ?>"><?php esc_html_e( 'All', 'meyvora-convert' ); ?></a></li>
						<li><a href="<?php echo esc_url( add_query_arg( array( 'status_filter' => 'active', 'paged' => 1, 'segment' => $segment_filter ), $list_url ) ); ?>" class="button <?php echo $status_filter === 'active' ? 'button-primary' : ''; ?>"><?php esc_html_e( 'Active', 'meyvora-convert' ); ?></a></li>
						<li><a href="<?php echo esc_url( add_query_arg( array( 'status_filter' => 'emailed', 'paged' => 1, 'segment' => $segment_filter ), $list_url ) ); ?>" class="button <?php echo $status_filter === 'emailed' ? 'button-primary' : ''; ?>"><?php esc_html_e( 'Emailed', 'meyvora-convert' ); ?></a></li>
						<li><a href="<?php echo esc_url( add_query_arg( array( 'status_filter' => 'recovered', 'paged' => 1, 'segment' => $segment_filter ), $list_url ) ); ?>" class="button <?php echo $status_filter === 'recovered' ? 'button-primary' : ''; ?>"><?php esc_html_e( 'Recovered', 'meyvora-convert' ); ?></a></li>
					</ul>
				</div>
				<div class="meyvc-ac-list-toolbar__secondary">
					<form method="get" class="meyvc-ac-segment-filter" action="<?php echo esc_url( $list_url ); ?>">
						<input type="hidden" name="page" value="meyvc-abandoned-carts" />
						<?php if ( $status_filter !== 'all' ) : ?>
							<input type="hidden" name="status_filter" value="<?php echo esc_attr( $status_filter ); ?>" />
						<?php endif; ?>
						<?php if ( $search !== '' ) : ?>
							<input type="hidden" name="search" value="<?php echo esc_attr( $search ); ?>" />
						<?php endif; ?>
						<label for="meyvc-ac-segment" class="screen-reader-text"><?php esc_html_e( 'Segment', 'meyvora-convert' ); ?></label>
						<select name="segment" id="meyvc-ac-segment" onchange="this.form.submit()">
							<option value="all" <?php selected( $segment_filter, 'all' ); ?>><?php esc_html_e( 'All segments', 'meyvora-convert' ); ?></option>
							<option value="high" <?php selected( $segment_filter, 'high' ); ?>><?php esc_html_e( 'High Value', 'meyvora-convert' ); ?></option>
							<option value="standard" <?php selected( $segment_filter, 'standard' ); ?>><?php esc_html_e( 'Standard', 'meyvora-convert' ); ?></option>
						</select>
					</form>
					<form method="get" class="meyvc-ac-list-search" action="<?php echo esc_url( $list_url ); ?>">
						<input type="hidden" name="page" value="meyvc-abandoned-carts" />
						<?php if ( $status_filter !== 'all' ) : ?>
							<input type="hidden" name="status_filter" value="<?php echo esc_attr( $status_filter ); ?>" />
						<?php endif; ?>
						<?php if ( $segment_filter !== 'all' ) : ?>
							<input type="hidden" name="segment" value="<?php echo esc_attr( $segment_filter ); ?>" />
						<?php endif; ?>
						<label for="meyvc-ac-search" class="screen-reader-text"><?php esc_html_e( 'Search by email', 'meyvora-convert' ); ?></label>
						<input type="search" id="meyvc-ac-search" name="search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by email…', 'meyvora-convert' ); ?>" />
						<button type="submit" class="button"><?php esc_html_e( 'Search', 'meyvora-convert' ); ?></button>
					</form>
				</div>
			</div>

			<?php if ( empty( $items ) ) : ?>
					<div class="meyvc-ui-empty-state">
						<span class="meyvc-ui-empty-state__icon" aria-hidden="true"><?php echo wp_kses( MEYVC_Icons::svg( 'shopping-cart', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

						<h2 class="meyvc-ui-empty-state__title"><?php esc_html_e( 'No abandoned carts', 'meyvora-convert' ); ?></h2>
						<p class="meyvc-ui-empty-state__desc"><?php esc_html_e( 'No carts match your filters. Carts will appear here when customers leave items without checking out.', 'meyvora-convert' ); ?></p>
						<div class="meyvc-ui-empty-state__actions">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=meyvc-abandoned-cart' ) ); ?>" class="button button-primary meyvc-ui-btn-primary"><?php esc_html_e( 'Configure email reminders', 'meyvora-convert' ); ?></a>
						</div>
					</div>
			<?php else : ?>
				<div class="meyvc-table-wrap meyvc-ac-list-table-wrap">
					<table class="meyvc-table meyvc-ac-list-table">
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
								$seg = class_exists( 'MEYVC_Abandoned_Cart_Reminder' ) ? MEYVC_Abandoned_Cart_Reminder::get_segment_for_row( $row ) : 'standard';
							?>
								<tr data-id="<?php echo esc_attr( $row->id ); ?>">
									<td>
										<?php echo esc_html( $row->email ? $row->email : '—' ); ?>
										<?php if ( ! empty( $row->user_id ) ) : ?>
											<br><span class="meyvc-ac-user-id">ID: <?php echo absint( $row->user_id ); ?></span>
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
											<span class="meyvc-ac-segment meyvc-ac-segment--high"><?php esc_html_e( 'High Value', 'meyvora-convert' ); ?></span>
										<?php else : ?>
											<span class="meyvc-ac-segment meyvc-ac-segment--standard"><?php esc_html_e( 'Standard', 'meyvora-convert' ); ?></span>
										<?php endif; ?>
									</td>
									<td><?php echo absint( $info['count'] ); ?></td>
									<td><?php echo $row->last_activity_at ? esc_html( $row->last_activity_at ) : '—'; ?></td>
									<td>
										<span class="meyvc-ac-status meyvc-ac-status--<?php echo esc_attr( $row->status ); ?>"><?php echo esc_html( ucfirst( $row->status ) ); ?></span>
									</td>
									<td><?php echo absint( $emails_sent ); ?></td>
									<td><?php echo $row->discount_coupon ? esc_html( $row->discount_coupon ) : '—'; ?></td>
									<td class="meyvc-table-actions meyvc-ac-actions">
										<button type="button" class="meyvc-table-action-link meyvc-ac-btn-detail" data-id="<?php echo esc_attr( $row->id ); ?>"><?php esc_html_e( 'View', 'meyvora-convert' ); ?></button>
										<?php if ( $allow_resend ) : ?>
											<a href="<?php echo esc_url( add_query_arg( array( 'id' => $row->id, '_wpnonce' => $nonce ), $resend_url ) ); ?>"><?php esc_html_e( 'Resend', 'meyvora-convert' ); ?></a>
										<?php endif; ?>
										<?php if ( $row->status === MEYVC_Abandoned_Cart_Tracker::STATUS_ACTIVE ) : ?>
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
					$paginate_args = array( 'page' => 'meyvc-abandoned-carts' );
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
					<div class="meyvc-ac-pagination tablenav bottom">
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
				</div><!-- .meyvc-card__body -->
			</div><!-- .meyvc-card -->
			<?php endif; ?>

	<!-- Detail drawer -->
	<div id="meyvc-ac-drawer" class="meyvc-ac-drawer" role="dialog" aria-modal="true" aria-labelledby="meyvc-ac-drawer-title" hidden>
		<div class="meyvc-ac-drawer__backdrop"></div>
		<div class="meyvc-ac-drawer__panel">
			<header class="meyvc-ac-drawer__header">
				<h2 id="meyvc-ac-drawer-title"><?php esc_html_e( 'Cart details', 'meyvora-convert' ); ?></h2>
				<button type="button" class="meyvc-ac-drawer__close" aria-label="<?php esc_attr_e( 'Close', 'meyvora-convert' ); ?>"><?php echo wp_kses( MEYVC_Icons::svg( 'x', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></button>

			</header>
			<div class="meyvc-ac-drawer__body">
				<div id="meyvc-ac-drawer-loading" class="meyvc-ac-drawer__loading"><?php esc_html_e( 'Loading…', 'meyvora-convert' ); ?></div>
				<div id="meyvc-ac-drawer-content" class="meyvc-ac-drawer__content meyvc-hidden"></div>
			</div>
		</div>
	</div>

<?php
wp_localize_script(
	'meyvc-admin',
	'meyvcAbandonedCartsListConfig',
	array(
		'listUrl' => $list_url,
		'nonce'   => $nonce,
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'strings' => array(
			'segment'        => __( 'Segment', 'meyvora-convert' ),
			'plannedTitle'   => __( 'Planned send times (from last activity)', 'meyvora-convert' ),
			'email1'         => __( 'Email 1', 'meyvora-convert' ),
			'email2'         => __( 'Email 2', 'meyvora-convert' ),
			'email3'         => __( 'Email 3', 'meyvora-convert' ),
			'cartItems'      => __( 'Cart items', 'meyvora-convert' ),
			'total'          => __( 'Total', 'meyvora-convert' ),
			'checkoutTitle'  => __( 'Checkout link', 'meyvora-convert' ),
			'openCheckout'   => __( 'Open checkout', 'meyvora-convert' ),
			'emailLog'       => __( 'Email log', 'meyvora-convert' ),
			'notSent'        => __( 'Not sent', 'meyvora-convert' ),
			'coupon'         => __( 'Coupon', 'meyvora-convert' ),
			'aiPreviewTitle' => __( 'AI email preview', 'meyvora-convert' ),
			'aiPreviewDesc'  => __( 'Uses the same AI content as live sends (cached 24 hours). Regenerate clears cache for that slot.', 'meyvora-convert' ),
			'previewBtn1'    => __( '✦ Preview AI Email 1', 'meyvora-convert' ),
			'previewBtn2'    => __( '✦ Preview AI Email 2', 'meyvora-convert' ),
			'previewBtn3'    => __( '✦ Preview AI Email 3', 'meyvora-convert' ),
			'loadError'      => __( 'Could not load details.', 'meyvora-convert' ),
			'requestFailed'  => __( 'Request failed.', 'meyvora-convert' ),
		),
	)
);
?>
