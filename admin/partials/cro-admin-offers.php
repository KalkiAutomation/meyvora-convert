<?php
/**
 * Admin Offers page – configure dynamic offers (option: cro_dynamic_offers) and test which offer matches.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once CRO_PLUGIN_DIR . 'admin/partials/cro-admin-ai-panel.php';

$max_offers = 5;
$option_key = 'cro_dynamic_offers';

/**
 * Return default empty offer structure.
 *
 * @return array
 */
$cro_empty_offer = function () use ( $max_offers ) {
	return array(
		'headline'                      => '',
		'description'                   => '',
		'min_cart_total'                => 0,
		'max_cart_total'                => 0,
		'min_items'                     => 0,
		'first_time_customer'           => false,
		'returning_customer_min_orders' => 0,
		'lifetime_spend_min'            => 0,
		'allowed_roles'                 => array(),
		'excluded_roles'                => array(),
		'reward_type'                   => 'percent',
		'reward_amount'                 => 10,
		'coupon_ttl_hours'              => 48,
		'priority'                      => 10,
		'enabled'                       => false,
		'individual_use'                => false,
		'rate_limit_hours'              => 6,
		'max_coupons_per_visitor'       => 1,
		'exclude_sale_items'            => false,
		'include_categories'            => array(),
		'exclude_categories'            => array(),
		'include_products'              => array(),
		'exclude_products'              => array(),
		'cart_contains_category'        => array(),
		'min_qty_for_category'         => array(),
		'apply_to_categories'          => array(),
		'apply_to_products'             => array(),
		'per_category_discount'        => array(),
		'conflict_offer_ids'           => array(),
	);
};

/**
 * Build rule summary (1–2 lines) from an offer.
 *
 * @param array $o Offer data.
 * @return string
 */
$cro_format_price_plain = function ( $amount ) {
	$formatted = number_format_i18n( (float) $amount, 2 );
	if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
		$formatted = get_woocommerce_currency_symbol() . $formatted;
	}
	return $formatted;
};
$cro_rule_summary = function ( $o ) use ( $cro_format_price_plain ) {
	$parts = array();
	if ( ! empty( $o['min_cart_total'] ) ) {
		$parts[] = sprintf( /* translators: %s is the formatted minimum cart total. */ __( 'Cart ≥ %s', 'meyvora-convert' ), $cro_format_price_plain( $o['min_cart_total'] ) );
	}
	if ( ! empty( $o['max_cart_total'] ) ) {
		$parts[] = sprintf( /* translators: %s is the formatted maximum cart total. */ __( 'Cart ≤ %s', 'meyvora-convert' ), $cro_format_price_plain( $o['max_cart_total'] ) );
	}
	if ( ! empty( $o['min_items'] ) ) {
		$parts[] = sprintf( /* translators: %d is the minimum number of cart items. */ _n( '%d item', '%d items', $o['min_items'], 'meyvora-convert' ), $o['min_items'] );
	}
	if ( ! empty( $o['first_time_customer'] ) ) {
		$parts[] = __( 'First-time customer', 'meyvora-convert' );
	}
	if ( ! empty( $o['returning_customer_min_orders'] ) ) {
		$parts[] = sprintf( /* translators: %d is the minimum number of previous orders. */ __( 'Returning: %d+ orders', 'meyvora-convert' ), $o['returning_customer_min_orders'] );
	}
	if ( ! empty( $o['lifetime_spend_min'] ) ) {
		$parts[] = sprintf( /* translators: %s is the formatted minimum lifetime spend. */ __( 'Lifetime spend ≥ %s', 'meyvora-convert' ), $cro_format_price_plain( $o['lifetime_spend_min'] ) );
	}
	if ( empty( $parts ) ) {
		return __( 'Any cart', 'meyvora-convert' );
	}
	return implode( ' · ', $parts );
};

/**
 * Build reward summary (1 line) from an offer.
 *
 * @param array $o Offer data.
 * @return string
 */
$cro_reward_summary = function ( $o ) {
	$type   = isset( $o['reward_type'] ) ? $o['reward_type'] : 'percent';
	$amount = isset( $o['reward_amount'] ) ? (float) $o['reward_amount'] : 0;
	if ( $type === 'free_shipping' ) {
		return __( 'Free shipping', 'meyvora-convert' );
	}
	if ( $type === 'percent' ) {
		return sprintf( /* translators: %s is the percentage discount value. */ __( '%s%% off', 'meyvora-convert' ), $amount );
	}
	if ( $type === 'fixed' ) {
		$formatted = number_format_i18n( (float) $amount, 2 );
		if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
			$formatted = get_woocommerce_currency_symbol() . $formatted;
		}
		return sprintf( /* translators: %s is the formatted fixed discount amount. */ __( '%s off', 'meyvora-convert' ), $formatted );
	}
	return __( 'Discount', 'meyvora-convert' );
};

// Migration: ensure legacy static-form offers (no id) are migrated to dynamic format and flag set.
$migration_done = (int) get_option( 'cro_offers_migrated', 0 ) === 1;
if ( ! $migration_done ) {
	$legacy = get_option( $option_key, array() );
	if ( is_array( $legacy ) && ! empty( $legacy ) ) {
		$legacy = array_pad( $legacy, $max_offers, array() );
		$dirty = false;
		foreach ( $legacy as $idx => $o ) {
			if ( ! is_array( $o ) ) {
				continue;
			}
			$name = isset( $o['headline'] ) ? trim( (string) $o['headline'] ) : ( isset( $o['name'] ) ? trim( (string) $o['name'] ) : '' );
			if ( $name !== '' && empty( $o['id'] ) ) {
				$legacy[ $idx ]['id']         = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : ( 'cro_' . uniqid( '', true ) );
				$legacy[ $idx ]['updated_at'] = gmdate( 'c' );
				$dirty                        = true;
			}
		}
		if ( $dirty ) {
			update_option( $option_key, $legacy );
		}
	}
	update_option( 'cro_offers_migrated', 1 );
}

// Duplicate offer (POST fallback): copy to first empty slot. Enforce max_offers server-side.
if ( isset( $_POST['cro_duplicate_offer'] ) && isset( $_POST['cro_offers_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cro_offers_nonce'] ) ), 'cro_offers_nonce' ) ) {
	$idx = isset( $_POST['cro_offer_index'] ) ? absint( $_POST['cro_offer_index'] ) : -1;
	if ( $idx >= 0 && $idx < $max_offers ) {
		$offers_raw = get_option( $option_key, array() );
		if ( is_array( $offers_raw ) ) {
			$offers_raw = array_pad( $offers_raw, $max_offers, array() );
			$used = 0;
			foreach ( $offers_raw as $o ) {
				if ( is_array( $o ) && trim( (string) ( $o['headline'] ?? '' ) ) !== '' ) {
					$used++;
				}
			}
			if ( $used >= $max_offers ) {
				wp_safe_redirect( add_query_arg( array( 'page' => 'cro-offers', 'cro_error' => 'limit' ), admin_url( 'admin.php' ) ) );
				exit;
			}
			$src = isset( $offers_raw[ $idx ] ) && is_array( $offers_raw[ $idx ] ) ? $offers_raw[ $idx ] : array();
			if ( ! empty( $src['headline'] ) ) {
				for ( $j = 0; $j < $max_offers; $j++ ) {
					$slot = isset( $offers_raw[ $j ] ) && is_array( $offers_raw[ $j ] ) ? $offers_raw[ $j ] : array();
					if ( empty( trim( (string) ( $slot['headline'] ?? '' ) ) ) ) {
						$copy = $src;
						$copy['headline'] = trim( $src['headline'] ) . ' (' . __( 'Copy', 'meyvora-convert' ) . ')';
						$copy['id']       = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : ( 'cro_' . uniqid( '', true ) );
						$copy['updated_at'] = gmdate( 'c' );
						$offers_raw[ $j ] = $copy;
						update_option( $option_key, $offers_raw );
						wp_safe_redirect( add_query_arg( array( 'page' => 'cro-offers', 'cro_duplicated' => '1' ), admin_url( 'admin.php' ) ) );
						exit;
					}
				}
			}
		}
	}
}

// Delete offer is handled via AJAX (cro_offer_delete); no POST fallback.

// Toggle offer enabled.
if ( isset( $_POST['cro_toggle_offer'] ) && isset( $_POST['cro_offers_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cro_offers_nonce'] ) ), 'cro_offers_nonce' ) ) {
	$idx = isset( $_POST['cro_offer_index'] ) ? absint( $_POST['cro_offer_index'] ) : -1;
	if ( $idx >= 0 && $idx < $max_offers ) {
		$offers_raw = get_option( $option_key, array() );
		if ( is_array( $offers_raw ) ) {
			$offers_raw = array_pad( $offers_raw, $max_offers, array() );
			$slot = isset( $offers_raw[ $idx ] ) && is_array( $offers_raw[ $idx ] ) ? $offers_raw[ $idx ] : array();
			$slot = array_merge( $cro_empty_offer(), $slot );
			$slot['enabled'] = empty( $slot['enabled'] );
			$offers_raw[ $idx ] = $slot;
			update_option( $option_key, $offers_raw );
			wp_safe_redirect( add_query_arg( array( 'page' => 'cro-offers', 'cro_toggled' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}
	}
}

$offers = get_option( $option_key, array() );
if ( ! is_array( $offers ) ) {
	$offers = array();
}
$offers = array_pad( $offers, $max_offers, array() );

// Ensure each used offer has an id (for AJAX duplicate / reorder).
$offers_dirty = false;
foreach ( $offers as $idx => $o ) {
	if ( ! is_array( $o ) ) {
		continue;
	}
	if ( trim( (string) ( $o['headline'] ?? '' ) ) !== '' && empty( $o['id'] ) ) {
		$offers[ $idx ]['id']         = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : ( 'cro_' . uniqid( '', true ) );
		$offers[ $idx ]['updated_at'] = gmdate( 'c' );
		$offers_dirty                 = true;
	}
}
if ( $offers_dirty ) {
	update_option( $option_key, $offers );
}

$offers_used_count = 0;
$first_empty_slot  = 0;
for ( $idx = 0; $idx < $max_offers; $idx++ ) {
	$oo = isset( $offers[ $idx ] ) && is_array( $offers[ $idx ] ) ? $offers[ $idx ] : array();
	if ( ! empty( trim( (string) ( $oo['headline'] ?? '' ) ) ) ) {
		$offers_used_count++;
	}
}
for ( $idx = 0; $idx < $max_offers; $idx++ ) {
	$oo = isset( $offers[ $idx ] ) && is_array( $offers[ $idx ] ) ? $offers[ $idx ] : array();
	if ( empty( trim( (string) ( $oo['headline'] ?? '' ) ) ) ) {
		$first_empty_slot = $idx;
		break;
	}
}

$wp_roles = function_exists( 'wp_roles' ) ? wp_roles() : null;
$role_names = $wp_roles && isset( $wp_roles->roles ) ? array_keys( $wp_roles->roles ) : array( 'administrator', 'customer', 'subscriber' );

$cro_conflict_offer_choices = array();
for ( $ci = 0; $ci < $max_offers; $ci++ ) {
	$co = isset( $offers[ $ci ] ) && is_array( $offers[ $ci ] ) ? $offers[ $ci ] : array();
	$hn = trim( (string) ( $co['headline'] ?? '' ) );
	$oid = isset( $co['id'] ) ? (string) $co['id'] : '';
	if ( $hn !== '' && $oid !== '' ) {
		$cro_conflict_offer_choices[] = array(
			'id'   => $oid,
			'name' => $hn,
		);
	}
}

$product_categories = array();
if ( taxonomy_exists( 'product_cat' ) ) {
	$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name' ) );
	if ( is_array( $terms ) && ! is_wp_error( $terms ) ) {
		foreach ( $terms as $t ) {
			$product_categories[] = array( 'id' => (int) $t->term_id, 'name' => $t->name );
		}
	}
}

$offers_for_js = array();
for ( $idx = 0; $idx < $max_offers; $idx++ ) {
	$o = isset( $offers[ $idx ] ) && is_array( $offers[ $idx ] ) ? $offers[ $idx ] : array();
	$offers_for_js[] = array_merge( $cro_empty_offer(), $o );
}
?>

	<?php if ( isset( $_GET['cro_duplicated'] ) ) : ?>
		<div class="cro-ui-notice cro-ui-toast-placeholder" role="status"><p><?php esc_html_e( 'Offer duplicated.', 'meyvora-convert' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['cro_deleted'] ) ) : ?>
		<div class="cro-ui-notice cro-ui-toast-placeholder" role="status"><p><?php esc_html_e( 'Offer deleted.', 'meyvora-convert' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['cro_toggled'] ) ) : ?>
		<div class="cro-ui-notice cro-ui-toast-placeholder" role="status"><p><?php esc_html_e( 'Offer status updated.', 'meyvora-convert' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['cro_error'] ) && $_GET['cro_error'] === 'limit' ) : ?>
		<div class="cro-ui-notice cro-ui-notice--error" role="alert"><p><?php esc_html_e( 'Offer limit reached (5). Cannot duplicate.', 'meyvora-convert' ); ?></p></div>
	<?php endif; ?>

	<div id="cro-ui-toast-container" class="cro-ui-toast-container" aria-live="polite" aria-label="<?php esc_attr_e( 'Notifications', 'meyvora-convert' ); ?>"></div>
	<div id="cro-offers-toast" class="cro-offers-toast cro-hidden" role="status"></div>

	<div class="cro-offers-bar cro-bar cro-offers-toolbar">
		<span class="cro-offers-count cro-bar__count"><?php echo (int) $offers_used_count; ?>/<?php echo (int) $max_offers; ?> <?php esc_html_e( 'offers used', 'meyvora-convert' ); ?></span>
		<?php if ( $offers_used_count > 0 ) : ?>
			<button type="button" id="cro-offers-check-conflicts" class="button button-secondary cro-offers-check-conflicts"><?php esc_html_e( 'Check for conflicts', 'meyvora-convert' ); ?></button>
		<?php endif; ?>
	</div>
	<div id="cro-offers-conflict-notices" class="cro-offers-conflict-notices" role="region" aria-label="<?php esc_attr_e( 'Offer conflict warnings', 'meyvora-convert' ); ?>"></div>
	<?php
	$ai_offer_suggest_ready = class_exists( 'CRO_AI_Client' ) && CRO_AI_Client::is_configured()
		&& function_exists( 'cro_settings' ) && 'yes' === cro_settings()->get( 'ai', 'feature_offers', 'yes' )
		&& class_exists( 'WooCommerce' );
	?>
	<div class="cro-ai-suggest-offer-wrap" style="margin:12px 0">
		<button type="button" id="cro-ai-suggest-offer-btn" class="button button-secondary" <?php disabled( ! $ai_offer_suggest_ready || $offers_used_count >= $max_offers ); ?>>
			<?php esc_html_e( '✦ AI Suggest Next Offer', 'meyvora-convert' ); ?>
		</button>
		<span class="spinner" id="cro-ai-suggest-offer-spinner" style="float:none;margin-left:8px;vertical-align:middle;visibility:hidden"></span>
		<?php if ( ! $ai_offer_suggest_ready ) : ?>
			<p class="description" style="margin:8px 0 0"><?php esc_html_e( 'Add your API key and enable AI offers under Settings → AI.', 'meyvora-convert' ); ?></p>
		<?php elseif ( $offers_used_count >= $max_offers ) : ?>
			<p class="description" style="margin:8px 0 0"><?php esc_html_e( 'All offer slots are full. Delete an offer to use AI suggest.', 'meyvora-convert' ); ?></p>
		<?php endif; ?>
		<div id="cro-ai-offer-suggestion-card" class="cro-card" style="display:none;margin-top:16px;max-width:720px">
			<div class="cro-card__body">
				<h3 id="cro-ai-suggest-name" class="cro-ai-suggest-name" style="margin:0 0 12px;font-size:18px"></h3>
				<p id="cro-ai-suggest-rationale" class="description" style="margin:0 0 12px;line-height:1.5"></p>
				<p id="cro-ai-suggest-condisc" style="margin:0 0 8px;font-weight:600"></p>
				<p id="cro-ai-suggest-impact" class="description" style="margin:0 0 16px"></p>
				<button type="button" id="cro-ai-suggest-create-btn" class="button button-primary"><?php esc_html_e( 'Create this offer', 'meyvora-convert' ); ?></button>
			</div>
		</div>
		<p id="cro-ai-suggest-error" class="description" style="display:none;color:#b32d2e;margin-top:8px"></p>
	</div>

	<div class="cro-card">
				<?php if ( $offers_used_count === 0 ) : ?>
				<div class="cro-card__body">
					<div class="cro-ui-empty-state">
						<span class="cro-ui-empty-state__icon" aria-hidden="true"><?php echo wp_kses( CRO_Icons::svg( 'tag', array( 'class' => 'cro-ico' ) ), CRO_Icons::get_svg_kses_allowed() ); ?></span>

						<h2 class="cro-ui-empty-state__title"><?php esc_html_e( 'No offers yet', 'meyvora-convert' ); ?></h2>
						<div class="cro-ui-empty-state__desc"><?php esc_html_e( 'Create your first offer to show a dynamic reward on cart and checkout.', 'meyvora-convert' ); ?></div>
						<div class="cro-ui-empty-state__actions">
							<button type="button" class="button button-primary cro-ui-btn-primary cro-offers-empty-cta" data-cro-drawer="add"><?php esc_html_e( 'Create your first offer', 'meyvora-convert' ); ?></button>
					</div>
				</div>
				</div>
				<?php else : ?>
				<div class="cro-card__body">
		<?php
		$offers_sorted = array();
		for ( $i = 0; $i < $max_offers; $i++ ) {
			$o = isset( $offers[ $i ] ) && is_array( $offers[ $i ] ) ? $offers[ $i ] : array();
			$o = wp_parse_args( $o, array(
				'headline'                      => '',
				'description'                   => '',
				'min_cart_total'                => 0,
				'max_cart_total'                => 0,
				'min_items'                     => 0,
				'first_time_customer'           => false,
				'returning_customer_min_orders' => 0,
				'lifetime_spend_min'            => 0,
				'allowed_roles'                 => array(),
				'excluded_roles'                => array(),
				'reward_type'                   => 'percent',
				'reward_amount'                 => 10,
				'coupon_ttl_hours'              => 48,
				'priority'                      => 10 + $i,
				'enabled'                       => false,
			) );
			if ( trim( (string) $o['headline'] ) !== '' ) {
				$offers_sorted[] = array( 'index' => $i, 'offer' => $o );
			}
		}
		usort( $offers_sorted, function ( $a, $b ) {
			$pa = isset( $a['offer']['priority'] ) ? (int) $a['offer']['priority'] : 10;
			$pb = isset( $b['offer']['priority'] ) ? (int) $b['offer']['priority'] : 10;
			return $pa !== $pb ? ( $pa - $pb ) : ( $a['index'] - $b['index'] );
		} );
		?>
		<div class="cro-offers-grid">
			<?php foreach ( $offers_sorted as $item ) : ?>
				<?php
				$i = $item['index'];
				$o = $item['offer'];
				$rule_summary   = class_exists( 'CRO_Offer_Presenter' ) ? CRO_Offer_Presenter::summarize_conditions( $o ) : $cro_rule_summary( $o );
				$reward_summary = class_exists( 'CRO_Offer_Presenter' ) ? CRO_Offer_Presenter::summarize_reward( $o ) : $cro_reward_summary( $o );
				$conflict_count = ! empty( $o['conflict_offer_ids'] ) && is_array( $o['conflict_offer_ids'] ) ? count( $o['conflict_offer_ids'] ) : 0;
				?>
				<div class="cro-offer-card" data-offer-index="<?php echo (int) $i; ?>" data-offer-id="<?php echo esc_attr( (string) ( $o['id'] ?? '' ) ); ?>" data-priority="<?php echo (int) $o['priority']; ?>" draggable="true">
					<div class="cro-offer-card-main">
						<div class="cro-offer-card-head">
							<span class="cro-offer-card-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'meyvora-convert' ); ?>" aria-hidden="true"></span>
							<h3 class="cro-offer-card-name"><?php echo esc_html( $o['headline'] ); ?></h3>
							<span class="cro-offer-card-status cro-offer-card-status--<?php echo ! empty( $o['enabled'] ) ? 'active' : 'inactive'; ?>">
								<?php echo ! empty( $o['enabled'] ) ? esc_html__( 'Active', 'meyvora-convert' ) : esc_html__( 'Inactive', 'meyvora-convert' ); ?>
							</span>
						</div>
						<div class="cro-offer-card-rule"><?php echo esc_html( $rule_summary ); ?></div>
						<div class="cro-offer-card-reward"><?php echo esc_html( $reward_summary ); ?></div>
						<div class="cro-offer-card-priority">
							<?php
							/* translators: %s: priority number */
							echo esc_html( sprintf( __( 'Priority: %s', 'meyvora-convert' ), $o['priority'] ) );
							?>
						</div>
						<div class="cro-offer-card-conflicts-row">
							<span class="cro-offer-card-conflicts-label"><?php esc_html_e( 'Conflicts', 'meyvora-convert' ); ?></span>
							<?php if ( $conflict_count > 0 ) : ?>
								<span class="cro-offer-conflict-badge" title="<?php esc_attr_e( 'Cannot combine with other offers', 'meyvora-convert' ); ?>">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: number of conflicting offers */
											_n( '%d conflict', '%d conflicts', $conflict_count, 'meyvora-convert' ),
											$conflict_count
										)
									);
									?>
								</span>
							<?php else : ?>
								<span class="cro-offer-conflict-badge cro-offer-conflict-badge--none" aria-hidden="true">—</span>
							<?php endif; ?>
						</div>
					</div>
					<div class="cro-offer-card-actions">
						<span class="cro-offer-card-move-btns">
							<button type="button" class="button button-small cro-offer-move-up" data-cro-offer-index="<?php echo (int) $i; ?>" title="<?php esc_attr_e( 'Move up', 'meyvora-convert' ); ?>" aria-label="<?php esc_attr_e( 'Move up', 'meyvora-convert' ); ?>"><?php echo wp_kses( CRO_Icons::svg( 'chevron-up', array( 'class' => 'cro-ico' ) ), CRO_Icons::get_svg_kses_allowed() ); ?></button>

							<button type="button" class="button button-small cro-offer-move-down" data-cro-offer-index="<?php echo (int) $i; ?>" title="<?php esc_attr_e( 'Move down', 'meyvora-convert' ); ?>" aria-label="<?php esc_attr_e( 'Move down', 'meyvora-convert' ); ?>"><?php echo wp_kses( CRO_Icons::svg( 'chevron-down', array( 'class' => 'cro-ico' ) ), CRO_Icons::get_svg_kses_allowed() ); ?></button>

						</span>
						<form method="post" class="cro-offer-card-toggle-form">
							<?php wp_nonce_field( 'cro_offers_nonce', 'cro_offers_nonce' ); ?>
							<input type="hidden" name="cro_toggle_offer" value="1" />
							<input type="hidden" name="cro_offer_index" value="<?php echo (int) $i; ?>" />
							<label class="cro-offer-card-toggle">
								<input type="checkbox" <?php checked( ! empty( $o['enabled'] ) ); ?> onchange="this.form.submit()" />
								<span class="cro-offer-card-toggle-slider"></span>
							</label>
						</form>
						<button type="button" class="button button-small cro-offer-card-edit" data-cro-offer-index="<?php echo (int) $i; ?>"><?php echo wp_kses( CRO_Icons::svg( 'pencil', array( 'class' => 'cro-ico' ) ), CRO_Icons::get_svg_kses_allowed() ); ?> <?php esc_html_e( 'Edit', 'meyvora-convert' ); ?></button>

						<?php if ( $offers_used_count < $max_offers ) : ?>
							<button type="button" class="button button-small cro-offer-card-duplicate" data-cro-offer-id="<?php echo esc_attr( (string) ( $o['id'] ?? '' ) ); ?>" data-cro-offer-index="<?php echo (int) $i; ?>"><?php echo wp_kses( CRO_Icons::svg( 'plus', array( 'class' => 'cro-ico' ) ), CRO_Icons::get_svg_kses_allowed() ); ?> <?php esc_html_e( 'Duplicate', 'meyvora-convert' ); ?></button>

						<?php endif; ?>
						<button type="button" class="button button-small cro-offer-card-delete" data-cro-offer-id="<?php echo esc_attr( (string) ( $o['id'] ?? '' ) ); ?>" data-cro-offer-index="<?php echo (int) $i; ?>"><?php echo wp_kses( CRO_Icons::svg( 'trash', array( 'class' => 'cro-ico' ) ), CRO_Icons::get_svg_kses_allowed() ); ?> <?php esc_html_e( 'Delete', 'meyvora-convert' ); ?></button>

					</div>
				</div>
			<?php endforeach; ?>
		</div>
				</div><!-- .cro-card__body -->
			<?php endif; ?>
			</div><!-- .cro-card -->

	<!-- Offer Builder drawer -->
	<div id="cro-offer-drawer" class="cro-offer-drawer" aria-hidden="true">
		<div class="cro-offer-drawer-backdrop" aria-hidden="true"></div>
		<div class="cro-offer-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="cro-offer-drawer-title" aria-label="<?php esc_attr_e( 'Offer form', 'meyvora-convert' ); ?>">
			<div class="cro-offer-drawer-header">
				<h2 class="cro-offer-drawer-title" id="cro-offer-drawer-title"><?php esc_html_e( 'Add Offer', 'meyvora-convert' ); ?></h2>
				<button type="button" class="cro-offer-drawer-close" aria-label="<?php esc_attr_e( 'Close', 'meyvora-convert' ); ?>"><?php echo wp_kses( CRO_Icons::svg( 'x', array( 'class' => 'cro-ico' ) ), CRO_Icons::get_svg_kses_allowed() ); ?></button>

			</div>
			<form id="cro-offer-drawer-form" class="cro-offer-drawer-form">
				<?php wp_nonce_field( 'cro_save_offer_nonce', 'cro_save_offer_nonce' ); ?>
				<input type="hidden" name="cro_offer_index" id="cro-drawer-offer-index" value="" />

				<p id="cro-ai-offer-prefill-notice" class="notice notice-info" style="display:none;margin:0 0 12px;padding:8px 12px" role="status"></p>

				<div class="cro-hidden" aria-hidden="true">
					<input type="hidden" id="cro-offer-name" value="" />
					<input type="hidden" id="cro-condition-type" value="" />
					<input type="hidden" id="cro-condition-value" value="" />
					<input type="hidden" id="cro-discount-type" value="" />
					<input type="hidden" id="cro-discount-value" value="" />
				</div>

				<div class="cro-offer-drawer-summary-bar" id="cro-offer-drawer-summary-bar" aria-live="polite">
					<span class="cro-offer-drawer-summary-label"><?php esc_html_e( 'Summary', 'meyvora-convert' ); ?></span>
					<div id="cro-drawer-offer-summary" class="cro-drawer-offer-summary"></div>
				</div>

				<div class="cro-offer-drawer-sections-wrap">
				<section class="cro-offer-drawer-section" id="cro-drawer-section-basics" data-section="basics">
					<button type="button" class="cro-offer-drawer-section__header" aria-expanded="true" aria-controls="cro-drawer-section-basics-body">
						<span class="cro-offer-drawer-section-title"><?php esc_html_e( 'Basics', 'meyvora-convert' ); ?></span>
						<span class="cro-offer-drawer-section__toggle" aria-hidden="true"><?php echo wp_kses( CRO_Icons::svg( 'chevron-down', array( 'class' => 'cro-ico' ) ), CRO_Icons::get_svg_kses_allowed() ); ?></span>

					</button>
					<div class="cro-offer-drawer-section__body" id="cro-drawer-section-basics-body" style="max-height: 400px;">
					<div class="cro-offer-drawer-section__body-inner">
					<div class="cro-grid cro-grid--12">
						<div class="cro-field cro-col-12">
							<label for="cro-drawer-headline" class="cro-field__label"><?php esc_html_e( 'Offer name', 'meyvora-convert' ); ?> <span class="required">*</span></label>
							<div class="cro-field__control">
								<input type="text" name="cro_drawer_headline" id="cro-drawer-headline" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. 10% off your order', 'meyvora-convert' ); ?>" required />
							</div>
						</div>
						<div class="cro-field cro-col-12">
							<label for="cro-drawer-description" class="cro-field__label"><?php esc_html_e( 'Description (optional)', 'meyvora-convert' ); ?></label>
							<div class="cro-field__control">
								<textarea name="cro_drawer_description" id="cro-drawer-description" rows="2" class="large-text"></textarea>
							</div>
						</div>
						<div class="cro-field cro-field--toggle cro-col-12">
							<div class="cro-field__control">
								<label class="cro-offer-drawer-toggle">
									<input type="checkbox" name="cro_drawer_enabled" id="cro-drawer-enabled" value="1" />
									<span class="cro-offer-drawer-toggle-slider"></span>
									<?php esc_html_e( 'Active', 'meyvora-convert' ); ?>
								</label>
							</div>
						</div>
						<div class="cro-field cro-col-6">
							<label for="cro-drawer-priority" class="cro-field__label">
								<span class="cro-field__label-wrap"><?php esc_html_e( 'Priority', 'meyvora-convert' ); ?>
									<button type="button" class="cro-field-help-trigger" data-tooltip="<?php esc_attr_e( 'Lower number = higher priority. First matching offer wins.', 'meyvora-convert' ); ?>" aria-label="<?php esc_attr_e( 'Help', 'meyvora-convert' ); ?>"><?php echo wp_kses( CRO_Icons::svg( 'info', array( 'class' => 'cro-ico' ) ), CRO_Icons::get_svg_kses_allowed() ); ?></button>

								</span>
							</label>
							<div class="cro-field__control">
								<input type="number" name="cro_drawer_priority" id="cro-drawer-priority" min="0" value="10" />
							</div>
						</div>
					</div>
					</div>
					</div>
				</section>

				<section class="cro-offer-drawer-section" id="cro-drawer-section-conditions" data-section="conditions">
					<button type="button" class="cro-offer-drawer-section__header" aria-expanded="true" aria-controls="cro-drawer-section-conditions-body">
						<span class="cro-offer-drawer-section-title"><?php esc_html_e( 'Conditions', 'meyvora-convert' ); ?></span>
						<span class="cro-offer-drawer-section__toggle" aria-hidden="true"><?php echo wp_kses( CRO_Icons::svg( 'chevron-down', array( 'class' => 'cro-ico' ) ), CRO_Icons::get_svg_kses_allowed() ); ?></span>

					</button>
					<div class="cro-offer-drawer-section__body" id="cro-drawer-section-conditions-body" style="max-height: 1200px;">
					<div class="cro-offer-drawer-section__body-inner">
					<div class="cro-grid cro-grid--12">
						<div class="cro-field cro-col-6">
							<label for="cro-drawer-min-cart-total" class="cro-field__label"><?php esc_html_e( 'Min cart total', 'meyvora-convert' ); ?></label>
							<div class="cro-field__control">
								<input type="number" name="cro_drawer_min_cart_total" id="cro-drawer-min-cart-total" min="0" step="0.01" value="0" />
							</div>
						</div>
						<div class="cro-field cro-col-6">
							<label for="cro-drawer-max-cart-total" class="cro-field__label"><?php esc_html_e( 'Max cart total (optional)', 'meyvora-convert' ); ?></label>
							<div class="cro-field__control">
								<input type="number" name="cro_drawer_max_cart_total" id="cro-drawer-max-cart-total" min="0" step="0.01" value="" placeholder="<?php esc_attr_e( 'Optional', 'meyvora-convert' ); ?>" />
							</div>
						</div>
						<div class="cro-field cro-col-6">
							<label for="cro-drawer-min-items" class="cro-field__label"><?php esc_html_e( 'Min items', 'meyvora-convert' ); ?></label>
							<div class="cro-field__control">
								<input type="number" name="cro_drawer_min_items" id="cro-drawer-min-items" min="0" value="0" />
							</div>
						</div>
						<div class="cro-field cro-field--toggle cro-col-6">
							<div class="cro-field__control">
								<label class="cro-offer-drawer-toggle">
									<input type="checkbox" name="cro_drawer_exclude_sale_items" id="cro-drawer-exclude-sale-items" value="1" />
									<span class="cro-offer-drawer-toggle-slider"></span>
									<?php esc_html_e( 'Exclude sale items', 'meyvora-convert' ); ?>
								</label>
							</div>
						</div>
						<div class="cro-field cro-col-6">
							<label for="cro-drawer-include-categories" class="cro-field__label"><?php esc_html_e( 'Include categories only', 'meyvora-convert' ); ?></label>
							<div class="cro-field__control">
								<select name="cro_drawer_include_categories[]" id="cro-drawer-include-categories" multiple class="cro-drawer-select cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Select categories…', 'meyvora-convert' ); ?>">
									<?php foreach ( $product_categories as $pc ) : ?>
										<option value="<?php echo esc_attr( $pc['id'] ); ?>"><?php echo esc_html( $pc['name'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<span class="cro-help"><?php esc_html_e( 'Cart must contain only products from these categories.', 'meyvora-convert' ); ?></span>
						</div>
						<div class="cro-field cro-col-6">
							<label for="cro-drawer-exclude-categories" class="cro-field__label"><?php esc_html_e( 'Exclude categories', 'meyvora-convert' ); ?></label>
							<div class="cro-field__control">
								<select name="cro_drawer_exclude_categories[]" id="cro-drawer-exclude-categories" multiple class="cro-drawer-select cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Select categories…', 'meyvora-convert' ); ?>">
									<?php foreach ( $product_categories as $pc ) : ?>
										<option value="<?php echo esc_attr( $pc['id'] ); ?>"><?php echo esc_html( $pc['name'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
						<div class="cro-field cro-col-6">
							<label for="cro-drawer-include-products" class="cro-field__label"><?php esc_html_e( 'Include products', 'meyvora-convert' ); ?></label>
							<div class="cro-field__control">
								<select name="cro_drawer_include_products[]" id="cro-drawer-include-products" multiple class="cro-drawer-select cro-selectwoo cro-select-products cro-select-min--wide" data-placeholder="<?php esc_attr_e( 'Search products…', 'meyvora-convert' ); ?>" data-action="cro_search_products"></select>
							</div>
							<span class="cro-help"><?php esc_html_e( 'Cart must contain at least one of these products.', 'meyvora-convert' ); ?></span>
						</div>
						<div class="cro-field cro-col-6">
							<label for="cro-drawer-exclude-products" class="cro-field__label"><?php esc_html_e( 'Exclude products', 'meyvora-convert' ); ?></label>
							<div class="cro-field__control">
								<select name="cro_drawer_exclude_products[]" id="cro-drawer-exclude-products" multiple class="cro-drawer-select cro-selectwoo cro-select-products cro-select-min--wide" data-placeholder="<?php esc_attr_e( 'Search products…', 'meyvora-convert' ); ?>" data-action="cro_search_products"></select>
							</div>
							<span class="cro-help"><?php esc_html_e( 'Offer does not match if cart contains any of these.', 'meyvora-convert' ); ?></span>
						</div>
						<div class="cro-field cro-col-12">
							<label for="cro-drawer-cart-contains-category" class="cro-field__label"><?php esc_html_e( 'Cart contains category', 'meyvora-convert' ); ?></label>
							<div class="cro-field__control">
								<select name="cro_drawer_cart_contains_category[]" id="cro-drawer-cart-contains-category" multiple class="cro-drawer-select cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Select categories…', 'meyvora-convert' ); ?>">
									<?php foreach ( $product_categories as $pc ) : ?>
										<option value="<?php echo esc_attr( $pc['id'] ); ?>"><?php echo esc_html( $pc['name'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<span class="cro-help"><?php esc_html_e( 'Cart must have at least one product in one of these categories.', 'meyvora-convert' ); ?></span>
						</div>
						<div class="cro-field cro-col-12">
							<label for="cro-drawer-min-qty-for-category" class="cro-field__label"><?php esc_html_e( 'Min qty for category (optional)', 'meyvora-convert' ); ?></label>
							<div class="cro-field__control">
								<textarea name="cro_drawer_min_qty_for_category" id="cro-drawer-min-qty-for-category" rows="2" class="large-text code" placeholder="<?php esc_attr_e( 'One per line: category_id:min_qty e.g. 15:2', 'meyvora-convert' ); ?>"></textarea>
							</div>
						</div>
						<div class="cro-field cro-field--toggle cro-col-6">
							<div class="cro-field__control">
								<label class="cro-offer-drawer-toggle">
									<input type="checkbox" name="cro_drawer_first_time_customer" id="cro-drawer-first-time" value="1" />
									<span class="cro-offer-drawer-toggle-slider"></span>
									<?php esc_html_e( 'First-time customer only', 'meyvora-convert' ); ?>
								</label>
							</div>
						</div>
						<div class="cro-field cro-field--toggle cro-col-6">
							<div class="cro-field__control">
								<label class="cro-offer-drawer-toggle" for="cro-drawer-returning-toggle">
									<input type="checkbox" id="cro-drawer-returning-toggle" aria-controls="cro-drawer-returning-min-wrap" />
									<span class="cro-offer-drawer-toggle-slider"></span>
									<?php esc_html_e( 'Returning customer (min orders)', 'meyvora-convert' ); ?>
								</label>
							</div>
						</div>
						<div class="cro-field cro-drawer-returning-min-wrap cro-hidden cro-col-6" id="cro-drawer-returning-min-wrap">
							<label for="cro-drawer-returning-min-orders" class="cro-field__label"><?php esc_html_e( 'Min orders', 'meyvora-convert' ); ?></label>
							<div class="cro-field__control">
								<input type="number" name="cro_drawer_returning_customer_min_orders" id="cro-drawer-returning-min-orders" min="0" value="0" />
							</div>
						</div>
						<div class="cro-field cro-col-6">
							<label for="cro-drawer-lifetime-spend" class="cro-field__label"><?php esc_html_e( 'Min lifetime spend', 'meyvora-convert' ); ?></label>
							<div class="cro-field__control">
								<input type="number" name="cro_drawer_lifetime_spend_min" id="cro-drawer-lifetime-spend" min="0" step="0.01" value="0" />
							</div>
						</div>
						<div class="cro-field cro-col-6">
							<label for="cro-drawer-allowed-roles" class="cro-field__label"><?php esc_html_e( 'Allowed roles', 'meyvora-convert' ); ?></label>
							<div class="cro-field__control">
								<select name="cro_drawer_allowed_roles[]" id="cro-drawer-allowed-roles" multiple class="cro-drawer-select cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Any role', 'meyvora-convert' ); ?>">
									<option value=""><?php esc_html_e( '— Any —', 'meyvora-convert' ); ?></option>
									<?php foreach ( $role_names as $role ) : ?>
										<option value="<?php echo esc_attr( $role ); ?>"><?php echo esc_html( $role ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
						<div class="cro-field cro-col-6">
							<label for="cro-drawer-excluded-roles" class="cro-field__label"><?php esc_html_e( 'Excluded roles', 'meyvora-convert' ); ?></label>
							<div class="cro-field__control">
								<select name="cro_drawer_excluded_roles[]" id="cro-drawer-excluded-roles" multiple class="cro-drawer-select cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Select roles…', 'meyvora-convert' ); ?>">
									<?php foreach ( $role_names as $role ) : ?>
										<option value="<?php echo esc_attr( $role ); ?>"><?php echo esc_html( $role ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
						</div>
						<div class="cro-field cro-col-12">
							<label for="cro-drawer-conflict-offers" class="cro-field__label"><?php esc_html_e( 'Cannot combine with', 'meyvora-convert' ); ?></label>
							<div class="cro-field__control">
								<select name="cro_drawer_conflict_offer_ids[]" id="cro-drawer-conflict-offers" multiple class="cro-drawer-select cro-selectwoo cro-select-woo" style="width:100%" data-placeholder="<?php esc_attr_e( 'Select offers…', 'meyvora-convert' ); ?>">
									<?php foreach ( $cro_conflict_offer_choices as $cco ) : ?>
										<option value="<?php echo esc_attr( $cco['id'] ); ?>"><?php echo esc_html( $cco['name'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<span class="cro-help"><?php esc_html_e( 'Selected offers will never fire together with this one.', 'meyvora-convert' ); ?></span>
						</div>
					</div>
					</div>
				</section>

				<section class="cro-offer-drawer-section" id="cro-drawer-section-reward" data-section="reward">
					<button type="button" class="cro-offer-drawer-section__header" aria-expanded="true" aria-controls="cro-drawer-section-reward-body">
						<span class="cro-offer-drawer-section-title"><?php esc_html_e( 'Reward', 'meyvora-convert' ); ?></span>
						<span class="cro-offer-drawer-section__toggle" aria-hidden="true"><?php echo wp_kses( CRO_Icons::svg( 'chevron-down', array( 'class' => 'cro-ico' ) ), CRO_Icons::get_svg_kses_allowed() ); ?></span>

					</button>
					<div class="cro-offer-drawer-section__body" id="cro-drawer-section-reward-body">
					<div class="cro-offer-drawer-section__body-inner">
					<div class="cro-grid cro-grid--12">
						<div class="cro-field cro-col-6">
							<label for="cro-drawer-reward-type" class="cro-field__label"><?php esc_html_e( 'Type', 'meyvora-convert' ); ?></label>
							<div class="cro-field__control">
								<select name="cro_drawer_reward_type" id="cro-drawer-reward-type" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Percent discount', 'meyvora-convert' ); ?>">
									<option value="percent"><?php esc_html_e( 'Percent discount', 'meyvora-convert' ); ?></option>
									<option value="fixed"><?php esc_html_e( 'Fixed discount', 'meyvora-convert' ); ?></option>
									<option value="free_shipping"><?php esc_html_e( 'Free shipping', 'meyvora-convert' ); ?></option>
								</select>
							</div>
						</div>
						<div class="cro-field cro-drawer-reward-amount-wrap cro-col-6">
							<label for="cro-drawer-reward-amount" class="cro-field__label"><?php esc_html_e( 'Amount', 'meyvora-convert' ); ?></label>
							<div class="cro-field__control">
								<input type="number" name="cro_drawer_reward_amount" id="cro-drawer-reward-amount" min="0" step="0.01" value="10" />
								<span class="cro-drawer-reward-suffix">%</span>
							</div>
						</div>
						<div class="cro-field cro-col-6">
							<label for="cro-drawer-coupon-ttl" class="cro-field__label"><?php esc_html_e( 'Coupon TTL (hours)', 'meyvora-convert' ); ?></label>
							<div class="cro-field__control">
								<input type="number" name="cro_drawer_coupon_ttl_hours" id="cro-drawer-coupon-ttl" min="1" max="720" value="48" />
							</div>
						</div>
						<div class="cro-field cro-field--toggle cro-col-6">
							<div class="cro-field__control">
								<label class="cro-offer-drawer-check">
									<input type="checkbox" name="cro_drawer_individual_use" id="cro-drawer-individual-use" value="1" />
									<?php esc_html_e( 'Individual use only (coupon cannot be combined with others)', 'meyvora-convert' ); ?>
								</label>
							</div>
						</div>
						<div class="cro-field cro-col-12">
							<label for="cro-drawer-apply-to-categories" class="cro-field__label"><?php esc_html_e( 'Apply discount to categories', 'meyvora-convert' ); ?></label>
							<div class="cro-field__control">
								<select name="cro_drawer_apply_to_categories[]" id="cro-drawer-apply-to-categories" multiple class="cro-drawer-select cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Select categories…', 'meyvora-convert' ); ?>">
									<?php foreach ( $product_categories as $pc ) : ?>
										<option value="<?php echo esc_attr( $pc['id'] ); ?>"><?php echo esc_html( $pc['name'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<span class="cro-help"><?php esc_html_e( 'Restrict generated coupon to these categories (optional).', 'meyvora-convert' ); ?></span>
						</div>
						<div class="cro-field cro-col-12">
							<label for="cro-drawer-apply-to-products" class="cro-field__label"><?php esc_html_e( 'Apply discount to products', 'meyvora-convert' ); ?></label>
							<div class="cro-field__control">
								<select name="cro_drawer_apply_to_products[]" id="cro-drawer-apply-to-products" multiple class="cro-drawer-select cro-selectwoo cro-select-products cro-select-min--wide" data-placeholder="<?php esc_attr_e( 'Search products…', 'meyvora-convert' ); ?>" data-action="cro_search_products"></select>
							</div>
							<span class="cro-help"><?php esc_html_e( 'Restrict generated coupon to these products (optional).', 'meyvora-convert' ); ?></span>
						</div>
						<div class="cro-field cro-col-12">
							<label for="cro-drawer-per-category-discount" class="cro-field__label"><?php esc_html_e( 'Per-category discount (optional)', 'meyvora-convert' ); ?></label>
							<div class="cro-field__control">
								<div id="cro-drawer-per-category-discount" class="cro-drawer-per-category-discount-list"></div>
							</div>
							<span class="cro-help"><?php esc_html_e( 'Category → amount. Overrides single amount for matching cart category.', 'meyvora-convert' ); ?></span>
						</div>
					</div>
					</div>
					</div>
				</section>

				<section class="cro-offer-drawer-section" id="cro-drawer-section-limits" data-section="limits">
					<button type="button" class="cro-offer-drawer-section__header" aria-expanded="true" aria-controls="cro-drawer-section-limits-body">
						<span class="cro-offer-drawer-section-title"><?php esc_html_e( 'Limits', 'meyvora-convert' ); ?></span>
						<span class="cro-offer-drawer-section__toggle" aria-hidden="true"><?php echo wp_kses( CRO_Icons::svg( 'chevron-down', array( 'class' => 'cro-ico' ) ), CRO_Icons::get_svg_kses_allowed() ); ?></span>

					</button>
					<div class="cro-offer-drawer-section__body" id="cro-drawer-section-limits-body" style="max-height: 200px;">
					<div class="cro-offer-drawer-section__body-inner">
					<div class="cro-grid cro-grid--12">
						<div class="cro-field cro-col-6">
							<label for="cro-drawer-rate-limit-hours" class="cro-field__label">
								<span class="cro-field__label-wrap"><?php esc_html_e( 'Rate limit (hours)', 'meyvora-convert' ); ?>
									<button type="button" class="cro-field-help-trigger" data-tooltip="<?php esc_attr_e( 'Hours before same visitor can see this offer again (default 6).', 'meyvora-convert' ); ?>" aria-label="<?php esc_attr_e( 'Help', 'meyvora-convert' ); ?>"><?php echo wp_kses( CRO_Icons::svg( 'info', array( 'class' => 'cro-ico' ) ), CRO_Icons::get_svg_kses_allowed() ); ?></button>

								</span>
							</label>
							<div class="cro-field__control">
								<input type="number" name="cro_drawer_rate_limit_hours" id="cro-drawer-rate-limit-hours" min="0" value="6" />
							</div>
						</div>
						<div class="cro-field cro-col-6">
							<label for="cro-drawer-max-coupons-per-visitor" class="cro-field__label"><?php esc_html_e( 'Max coupons per visitor per offer', 'meyvora-convert' ); ?></label>
							<div class="cro-field__control">
								<input type="number" name="cro_drawer_max_coupons_per_visitor" id="cro-drawer-max-coupons-per-visitor" min="0" value="1" />
							</div>
						</div>
					</div>
					</div>
					</div>
				</section>
				</div>

				<div class="cro-offer-drawer-footer">
					<button type="button" class="button cro-offer-drawer-cancel"><?php esc_html_e( 'Cancel', 'meyvora-convert' ); ?></button>
					<button type="submit" class="button button-primary cro-offer-drawer-save"><?php esc_html_e( 'Save Offer', 'meyvora-convert' ); ?></button>
				</div>
			</form>
		</div>
	</div>

	<!-- Test Offer panel -->
	<div class="cro-settings-section cro-offer-test-panel">
		<h2><?php esc_html_e( 'Test Offer', 'meyvora-convert' ); ?></h2>
		<div class="cro-section-description cro-section-desc"><?php esc_html_e( 'Simulate a visitor to see which offer would match and why.', 'meyvora-convert' ); ?></div>
		<div class="cro-offer-test-form-wrap cro-grid cro-grid--gap-3 cro-grid--2">
			<div class="cro-field">
				<label class="cro-field__label" for="cro-test-cart-total"><?php esc_html_e( 'Cart total', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<input type="number" id="cro-test-cart-total" name="cart_total" value="50" min="0" step="0.01" class="small-text" />
				</div>
			</div>
			<div class="cro-field">
				<label class="cro-field__label" for="cro-test-items-count"><?php esc_html_e( 'Items count', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<input type="number" id="cro-test-items-count" name="cart_items_count" value="1" min="0" class="small-text" />
				</div>
			</div>
			<div class="cro-field">
				<label class="cro-field__label" for="cro-test-is-logged-in"><?php esc_html_e( 'Guest vs Logged-in', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<select id="cro-test-is-logged-in" name="is_logged_in" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Guest', 'meyvora-convert' ); ?>">
						<option value="0"><?php esc_html_e( 'Guest', 'meyvora-convert' ); ?></option>
						<option value="1"><?php esc_html_e( 'Logged-in', 'meyvora-convert' ); ?></option>
					</select>
				</div>
			</div>
			<div class="cro-field">
				<label class="cro-field__label" for="cro-test-order-count"><?php esc_html_e( 'Order count', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<input type="number" id="cro-test-order-count" name="order_count" value="0" min="0" class="small-text" />
				</div>
			</div>
			<div class="cro-field">
				<label class="cro-field__label" for="cro-test-lifetime-spend"><?php esc_html_e( 'Lifetime spend', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<input type="number" id="cro-test-lifetime-spend" name="lifetime_spend" value="0" min="0" step="0.01" class="small-text" />
				</div>
			</div>
			<div class="cro-field">
				<label class="cro-field__label" for="cro-test-user-role"><?php esc_html_e( 'Role', 'meyvora-convert' ); ?></label>
				<div class="cro-field__control">
					<select id="cro-test-user-role" name="user_role" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( '— Any —', 'meyvora-convert' ); ?>">
						<option value=""><?php esc_html_e( '— Any —', 'meyvora-convert' ); ?></option>
						<?php foreach ( $role_names as $role ) : ?>
							<option value="<?php echo esc_attr( $role ); ?>"><?php echo esc_html( $role ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="cro-help"><?php esc_html_e( 'Used when Logged-in is selected.', 'meyvora-convert' ); ?></p>
				</div>
			</div>
		</div>
		<div class="cro-mt-2">
			<button type="button" class="button button-secondary" id="cro-offer-test-run"><?php esc_html_e( 'Run Test', 'meyvora-convert' ); ?></button>
		</div>
		<div id="cro-offer-test-output" class="cro-offer-test-output cro-hidden" aria-live="polite">
			<div class="cro-offer-test-result-match"></div>
			<div class="cro-offer-test-result-conditions"></div>
		</div>
		<div id="cro-offer-test-no-match" class="cro-offer-test-output cro-offer-test-no-match cro-hidden" aria-live="polite"></div>
	</div>

<?php $cro_json_hex = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT; ?>
<script type="application/json" id="cro-offers-page-data"><?php echo wp_json_encode( array(
	'offersData'           => $offers_for_js,
	'maxOffers'            => (int) $max_offers,
	'usedCount'            => (int) $offers_used_count,
	'nonce'                => wp_create_nonce( 'cro_offers_nonce' ),
	'productCategories'    => array_values( $product_categories ),
	'offerConflictChoices' => array_values( $cro_conflict_offer_choices ),
), $cro_json_hex ); ?></script>
