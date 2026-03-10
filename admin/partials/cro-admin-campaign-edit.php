<?php
/**
 * Admin campaign edit page
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

$campaign_id = isset( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : 0;
$campaign = $campaign_id > 0 ? CRO_Campaign::get( $campaign_id ) : null;

// Show success notice if campaign was duplicated
if ( isset( $_GET['duplicated'] ) && $_GET['duplicated'] == '1' ) {
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Campaign duplicated successfully!', 'meyvora-convert' ) . '</p></div>';
}

// Get targeting rules with defaults.
$default_targeting = CRO_Activator::get_default_targeting_rules();
$targeting = isset( $campaign['targeting_rules'] ) ? $campaign['targeting_rules'] : ( isset( $campaign['targeting'] ) ? $campaign['targeting'] : $default_targeting );
$targeting = wp_parse_args( $targeting, $default_targeting );

// Handle save
if ( isset( $_POST['cro_save_campaign'] ) && isset( $_POST['cro_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['cro_nonce'] ), 'cro_save_campaign' ) ) {
	$data = array(
		'name'          => sanitize_text_field( wp_unslash( $_POST['campaign_name'] ?? '' ) ),
		'campaign_type' => sanitize_text_field( wp_unslash( $_POST['campaign_type'] ?? 'exit_intent' ) ),
		'status'        => sanitize_text_field( wp_unslash( $_POST['campaign_status'] ?? 'draft' ) ),
	);

	// Process targeting data.
	if ( isset( $_POST['targeting'] ) && is_array( $_POST['targeting'] ) ) {
		$targeting_data = array();

		// Behavior.
		if ( isset( $_POST['targeting']['behavior'] ) ) {
			$behavior = array();
			$behavior['min_time_on_page']   = isset( $_POST['targeting']['behavior']['min_time_on_page'] ) ? absint( $_POST['targeting']['behavior']['min_time_on_page'] ) : 0;
			$behavior['min_scroll_depth']    = isset( $_POST['targeting']['behavior']['min_scroll_depth'] ) ? absint( $_POST['targeting']['behavior']['min_scroll_depth'] ) : 0;
			$behavior['require_interaction'] = ! empty( $_POST['targeting']['behavior']['require_interaction'] );
			$behavior['cart_status']         = isset( $_POST['targeting']['behavior']['cart_status'] ) ? sanitize_text_field( wp_unslash( $_POST['targeting']['behavior']['cart_status'] ) ) : 'any';
			$behavior['cart_min_value']      = isset( $_POST['targeting']['behavior']['cart_min_value'] ) ? floatval( $_POST['targeting']['behavior']['cart_min_value'] ) : 0;
			$behavior['cart_max_value']      = isset( $_POST['targeting']['behavior']['cart_max_value'] ) ? floatval( $_POST['targeting']['behavior']['cart_max_value'] ) : 0;
			$targeting_data['behavior']     = $behavior;
		}

		// Visitor.
		if ( isset( $_POST['targeting']['visitor'] ) ) {
			$visitor = array();
			$visitor['type'] = isset( $_POST['targeting']['visitor']['type'] ) ? sanitize_text_field( wp_unslash( $_POST['targeting']['visitor']['type'] ) ) : 'all';
			$targeting_data['visitor'] = $visitor;
		}

		// Device.
		if ( isset( $_POST['targeting']['device'] ) ) {
			$device = array();
			$device['desktop'] = ! empty( $_POST['targeting']['device']['desktop'] );
			$device['mobile']  = ! empty( $_POST['targeting']['device']['mobile'] );
			$device['tablet']  = ! empty( $_POST['targeting']['device']['tablet'] );
			$targeting_data['device'] = $device;
		}

		// Merge with existing targeting (preserve pages, schedule, etc.).
		$data['targeting_rules'] = wp_parse_args( $targeting_data, $targeting );
	}

	if ( $campaign_id > 0 ) {
		CRO_Campaign::update( $campaign_id, $data );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Campaign updated.', 'meyvora-convert' ) . '</p></div>';
		$campaign = CRO_Campaign::get( $campaign_id );
		// Reload targeting after update.
		$targeting = isset( $campaign['targeting_rules'] ) ? $campaign['targeting_rules'] : ( isset( $campaign['targeting'] ) ? $campaign['targeting'] : $default_targeting );
		$targeting = wp_parse_args( $targeting, $default_targeting );
	} else {
		$new_id = CRO_Campaign::create( $data );
		if ( $new_id ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Campaign created.', 'meyvora-convert' ) . '</p></div>';
			wp_safe_redirect( admin_url( 'admin.php?page=cro-campaign-edit&campaign_id=' . $new_id ) );
			exit;
		}
	}
}
?>

<h1><?php echo esc_html( $campaign_id > 0 ? __( 'Edit Campaign', 'meyvora-convert' ) : __( 'Add New Campaign', 'meyvora-convert' ) ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=cro-campaigns' ) ); ?>" class="page-title-action"><?php esc_html_e( '&larr; Back to Campaigns', 'meyvora-convert' ); ?></a>

	<form method="post">
		<?php wp_nonce_field( 'cro_save_campaign', 'cro_nonce' ); ?>

		<div class="cro-settings-section">
			<div class="cro-fields-grid cro-fields-grid--1col">
				<div class="cro-field cro-col-12">
					<label for="campaign_name" class="cro-field__label"><?php esc_html_e( 'Campaign Name', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<input type="text" id="campaign_name" name="campaign_name" value="<?php echo esc_attr( $campaign ? $campaign['name'] : '' ); ?>" class="regular-text" required>
					</div>
				</div>
				<div class="cro-field cro-col-12">
					<label for="campaign_type" class="cro-field__label"><?php esc_html_e( 'Campaign Type', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<select id="campaign_type" name="campaign_type" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Exit Intent', 'meyvora-convert' ); ?>">
							<option value="exit_intent" <?php selected( $campaign ? ( $campaign['campaign_type'] ?? 'exit_intent' ) : 'exit_intent', 'exit_intent' ); ?>><?php esc_html_e( 'Exit Intent', 'meyvora-convert' ); ?></option>
							<option value="scroll_trigger" <?php selected( $campaign ? ( $campaign['campaign_type'] ?? '' ) : '', 'scroll_trigger' ); ?>><?php esc_html_e( 'Scroll Trigger', 'meyvora-convert' ); ?></option>
							<option value="time_trigger" <?php selected( $campaign ? ( $campaign['campaign_type'] ?? '' ) : '', 'time_trigger' ); ?>><?php esc_html_e( 'Time Trigger', 'meyvora-convert' ); ?></option>
							<option value="manual" <?php selected( $campaign ? ( $campaign['campaign_type'] ?? '' ) : '', 'manual' ); ?>><?php esc_html_e( 'Manual', 'meyvora-convert' ); ?></option>
						</select>
					</div>
				</div>
				<div class="cro-field cro-col-12">
					<label for="campaign_status" class="cro-field__label"><?php esc_html_e( 'Status', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<select id="campaign_status" name="campaign_status" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Draft', 'meyvora-convert' ); ?>">
							<option value="draft" <?php selected( $campaign ? $campaign['status'] : 'draft', 'draft' ); ?>><?php esc_html_e( 'Draft', 'meyvora-convert' ); ?></option>
							<option value="active" <?php selected( $campaign ? $campaign['status'] : 'draft', 'active' ); ?>><?php esc_html_e( 'Active', 'meyvora-convert' ); ?></option>
						</select>
					</div>
				</div>
			</div>
		</div>

		<!-- Behavioral Targeting Section -->
		<div class="cro-settings-section">
			<h2>
				<?php echo CRO_Icons::svg( 'user', array( 'class' => 'cro-ico' ) ); ?>
				<?php esc_html_e( 'Behavioral Targeting', 'meyvora-convert' ); ?>
			</h2>
			<p class="cro-section-description">
				<?php esc_html_e( 'Show campaigns based on how visitors interact with your site, not just which page they are on.', 'meyvora-convert' ); ?>
			</p>

			<div class="cro-fields-grid cro-fields-grid--1col">
				<div class="cro-field cro-col-12">
					<label class="cro-field__label"><?php esc_html_e( 'Time on Page', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<label>
							<?php esc_html_e( 'Minimum time before trigger:', 'meyvora-convert' ); ?>
							<input type="number" name="targeting[behavior][min_time_on_page]"
								value="<?php echo esc_attr( $targeting['behavior']['min_time_on_page'] ?? 0 ); ?>"
								min="0" max="300" class="small-text" />
							<?php esc_html_e( 'seconds', 'meyvora-convert' ); ?>
						</label>
					</div>
					<span class="cro-help"><?php esc_html_e( 'Wait until visitor has been on page this long. 0 = no minimum.', 'meyvora-convert' ); ?></span>
				</div>
				<div class="cro-field cro-col-12">
					<label class="cro-field__label"><?php esc_html_e( 'Scroll Depth', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<label>
							<?php esc_html_e( 'Minimum scroll depth:', 'meyvora-convert' ); ?>
							<input type="number" name="targeting[behavior][min_scroll_depth]"
								value="<?php echo esc_attr( $targeting['behavior']['min_scroll_depth'] ?? 0 ); ?>"
								min="0" max="100" class="small-text" />
							%
						</label>
					</div>
					<span class="cro-help"><?php esc_html_e( 'Visitor must scroll this far before trigger. 0 = no minimum.', 'meyvora-convert' ); ?></span>
				</div>
				<div class="cro-field cro-col-12">
					<div class="cro-field__control">
						<label>
							<input type="checkbox" name="targeting[behavior][require_interaction]" value="1"
								<?php checked( ! empty( $targeting['behavior']['require_interaction'] ) ); ?> />
							<?php esc_html_e( 'Require at least one click/tap before triggering', 'meyvora-convert' ); ?>
						</label>
					</div>
					<span class="cro-help"><?php esc_html_e( 'Ensures visitor is engaged, not just bouncing.', 'meyvora-convert' ); ?></span>
				</div>
				<div class="cro-field cro-col-12">
					<label for="cart_status" class="cro-field__label"><?php esc_html_e( 'Cart Status', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<select id="cart_status" name="targeting[behavior][cart_status]" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Any (show regardless of cart)', 'meyvora-convert' ); ?>">
							<option value="any" <?php selected( $targeting['behavior']['cart_status'] ?? 'any', 'any' ); ?>>
								<?php esc_html_e( 'Any (show regardless of cart)', 'meyvora-convert' ); ?>
							</option>
							<option value="has_items" <?php selected( $targeting['behavior']['cart_status'] ?? '', 'has_items' ); ?>>
								<?php esc_html_e( 'Cart has items', 'meyvora-convert' ); ?>
							</option>
							<option value="empty" <?php selected( $targeting['behavior']['cart_status'] ?? '', 'empty' ); ?>>
								<?php esc_html_e( 'Cart is empty', 'meyvora-convert' ); ?>
							</option>
						</select>
					</div>
				</div>
				<div class="cro-field cro-col-12">
					<label class="cro-field__label"><?php esc_html_e( 'Cart Value', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<label>
							<?php esc_html_e( 'Minimum:', 'meyvora-convert' ); ?>
							<?php echo esc_html( get_woocommerce_currency_symbol() ); ?>
							<input type="number" name="targeting[behavior][cart_min_value]"
								value="<?php echo esc_attr( $targeting['behavior']['cart_min_value'] ?? 0 ); ?>"
								min="0" step="0.01" class="small-text" />
						</label>
						&nbsp;&nbsp;
						<label>
							<?php esc_html_e( 'Maximum:', 'meyvora-convert' ); ?>
							<?php echo esc_html( get_woocommerce_currency_symbol() ); ?>
							<input type="number" name="targeting[behavior][cart_max_value]"
								value="<?php echo esc_attr( $targeting['behavior']['cart_max_value'] ?? 0 ); ?>"
								min="0" step="0.01" class="small-text" />
						</label>
					</div>
					<span class="cro-help"><?php esc_html_e( 'Set to 0 for no limit.', 'meyvora-convert' ); ?></span>
				</div>
				<div class="cro-field cro-col-12">
					<label for="visitor_type" class="cro-field__label"><?php esc_html_e( 'Visitor Type', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<select id="visitor_type" name="targeting[visitor][type]" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'All visitors', 'meyvora-convert' ); ?>">
							<option value="all" <?php selected( $targeting['visitor']['type'] ?? 'all', 'all' ); ?>>
								<?php esc_html_e( 'All visitors', 'meyvora-convert' ); ?>
							</option>
							<option value="new" <?php selected( $targeting['visitor']['type'] ?? '', 'new' ); ?>>
								<?php esc_html_e( 'New visitors only (first visit)', 'meyvora-convert' ); ?>
							</option>
							<option value="returning" <?php selected( $targeting['visitor']['type'] ?? '', 'returning' ); ?>>
								<?php esc_html_e( 'Returning visitors only', 'meyvora-convert' ); ?>
							</option>
						</select>
					</div>
				</div>
				<div class="cro-field cro-col-12">
					<label class="cro-field__label"><?php esc_html_e( 'Device', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<label>
							<input type="checkbox" name="targeting[device][desktop]" value="1"
								<?php checked( ! empty( $targeting['device']['desktop'] ) ); ?> />
							<?php esc_html_e( 'Desktop', 'meyvora-convert' ); ?>
						</label>
						&nbsp;&nbsp;
						<label>
							<input type="checkbox" name="targeting[device][mobile]" value="1"
								<?php checked( ! empty( $targeting['device']['mobile'] ) ); ?> />
							<?php esc_html_e( 'Mobile', 'meyvora-convert' ); ?>
						</label>
						&nbsp;&nbsp;
						<label>
							<input type="checkbox" name="targeting[device][tablet]" value="1"
								<?php checked( ! empty( $targeting['device']['tablet'] ) ); ?> />
							<?php esc_html_e( 'Tablet', 'meyvora-convert' ); ?>
						</label>
					</div>
				</div>
			</div>
		</div>

		<?php submit_button( __( 'Save Campaign', 'meyvora-convert' ), 'primary', 'cro_save_campaign' ); ?>
	</form>
