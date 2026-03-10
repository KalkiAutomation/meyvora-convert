<?php
/**
 * Create New A/B Test Page
 *
 * @package Meyvora_Convert
 */

defined( 'ABSPATH' ) || exit;

// Check for form submission
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['cro_create_ab_test'] ) ) {
	check_admin_referer( 'cro_create_ab_test' );

	if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
		wp_die( esc_html__( 'Unauthorized', 'meyvora-convert' ) );
	}

	$ab_model = new CRO_AB_Test();

	$data = array(
		'name'               => sanitize_text_field( $_POST['test_name'] ?? '' ),
		'campaign_id'        => absint( $_POST['campaign_id'] ?? 0 ),
		'metric'             => sanitize_text_field( $_POST['metric'] ?? 'conversion_rate' ),
		'min_sample_size'    => absint( $_POST['min_sample_size'] ?? 200 ),
		'confidence_level'   => absint( $_POST['confidence_level'] ?? 95 ),
		'auto_apply_winner'  => isset( $_POST['auto_apply_winner'] ),
	);
	$test_id = $ab_model->create( $data );

	if ( ! is_wp_error( $test_id ) ) {
		do_action( 'cro_abtest_created', (int) $test_id, $data );
		wp_safe_redirect( admin_url( 'admin.php?page=cro-ab-test-view&id=' . $test_id . '&message=created' ) );
		exit;
	}
}

// Get campaigns for dropdown
global $wpdb;
$campaigns_table = $wpdb->prefix . 'cro_campaigns';
$campaigns       = $wpdb->get_results( "SELECT id, name, status FROM {$campaigns_table} ORDER BY name ASC" );
?>

	<form method="post" class="cro-form">
		<?php wp_nonce_field( 'cro_create_ab_test' ); ?>

		<div class="cro-form-card">
			<h2><?php esc_html_e( 'Test Details', 'meyvora-convert' ); ?></h2>

			<div class="cro-fields-grid cro-fields-grid--1col">
				<div class="cro-field cro-col-12">
					<label for="test_name" class="cro-field__label"><?php esc_html_e( 'Test Name', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<input type="text"
							   id="test_name"
							   name="test_name"
							   class="regular-text"
							   required
							   placeholder="<?php esc_attr_e( 'e.g., Homepage Popup - Headline Test', 'meyvora-convert' ); ?>" />
					</div>
					<span class="cro-help"><?php esc_html_e( 'A descriptive name for this test', 'meyvora-convert' ); ?></span>
				</div>
				<div class="cro-field cro-col-12">
					<label for="campaign_id" class="cro-field__label"><?php esc_html_e( 'Campaign to Test', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<select id="campaign_id" name="campaign_id" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Select a campaign...', 'meyvora-convert' ); ?>" required>
							<option value=""><?php esc_html_e( 'Select a campaign...', 'meyvora-convert' ); ?></option>
							<?php foreach ( $campaigns as $campaign ) : ?>
							<option value="<?php echo esc_attr( $campaign->id ); ?>">
								<?php echo esc_html( $campaign->name ); ?>
								(<?php echo esc_html( $campaign->status ); ?>)
							</option>
							<?php endforeach; ?>
						</select>
					</div>
					<span class="cro-help"><?php esc_html_e( 'This campaign will be the "Control" version', 'meyvora-convert' ); ?></span>
				</div>
			</div>
		</div>

		<div class="cro-form-card">
			<h2><?php esc_html_e( 'Test Settings', 'meyvora-convert' ); ?></h2>

			<div class="cro-fields-grid cro-fields-grid--1col">
				<div class="cro-field cro-col-12">
					<label for="metric" class="cro-field__label"><?php esc_html_e( 'Primary Metric', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<select id="metric" name="metric" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Conversion Rate', 'meyvora-convert' ); ?>">
							<option value="conversion_rate"><?php esc_html_e( 'Conversion Rate', 'meyvora-convert' ); ?></option>
							<option value="revenue_per_visitor"><?php esc_html_e( 'Revenue per Visitor', 'meyvora-convert' ); ?></option>
						</select>
					</div>
					<span class="cro-help"><?php esc_html_e( 'What metric to optimize for', 'meyvora-convert' ); ?></span>
				</div>
				<div class="cro-field cro-col-12">
					<label for="min_sample_size" class="cro-field__label"><?php esc_html_e( 'Minimum Sample Size', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<input type="number"
							   id="min_sample_size"
							   name="min_sample_size"
							   value="200"
							   min="50"
							   max="10000"
							   class="small-text" />
						<span><?php esc_html_e( 'impressions per variation', 'meyvora-convert' ); ?></span>
					</div>
					<span class="cro-help"><?php esc_html_e( 'Minimum visitors before results are considered reliable', 'meyvora-convert' ); ?></span>
				</div>
				<div class="cro-field cro-col-12">
					<label for="confidence_level" class="cro-field__label"><?php esc_html_e( 'Confidence Level', 'meyvora-convert' ); ?></label>
					<div class="cro-field__control">
						<select id="confidence_level" name="confidence_level" class="cro-selectwoo" data-placeholder="95% (Recommended)">
							<option value="80">80%</option>
							<option value="85">85%</option>
							<option value="90">90%</option>
							<option value="95" selected>95% (Recommended)</option>
							<option value="99">99%</option>
						</select>
					</div>
					<span class="cro-help"><?php esc_html_e( 'Statistical confidence required to declare a winner', 'meyvora-convert' ); ?></span>
				</div>
				<div class="cro-field cro-col-12">
					<div class="cro-field__control">
						<label>
							<input type="checkbox" name="auto_apply_winner" value="1" />
							<?php esc_html_e( 'Automatically apply winning variation to original campaign', 'meyvora-convert' ); ?>
						</label>
					</div>
					<span class="cro-help"><?php esc_html_e( 'When a winner is detected, automatically update the campaign', 'meyvora-convert' ); ?></span>
				</div>
			</div>
		</div>

		<div class="cro-form-card cro-info-card">
			<h3><?php esc_html_e( 'What happens next?', 'meyvora-convert' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'After creating the test, you\'ll be able to add variations', 'meyvora-convert' ); ?></li>
				<li><?php esc_html_e( 'Each variation can have different content, styling, or offers', 'meyvora-convert' ); ?></li>
				<li><?php esc_html_e( 'Traffic will be split between variations automatically', 'meyvora-convert' ); ?></li>
				<li><?php esc_html_e( 'Results will show statistical significance when reached', 'meyvora-convert' ); ?></li>
			</ol>
		</div>

		<p class="submit">
			<button type="submit" name="cro_create_ab_test" class="button button-primary button-large">
				<?php esc_html_e( 'Create A/B Test', 'meyvora-convert' ); ?>
			</button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cro-ab-tests' ) ); ?>" class="button button-large">
				<?php esc_html_e( 'Cancel', 'meyvora-convert' ); ?>
			</a>
		</p>
	</form>

<style>
.cro-form-card {
	background: #fff;
	padding: 20px 25px;
	margin-bottom: 32px;
	border-radius: 8px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.cro-form-card h2 {
	margin-top: 0;
	padding-bottom: 15px;
	border-bottom: 1px solid #eee;
}

.cro-info-card {
	background: #f5f5f5;
	border-left: 4px solid #333;
}

.cro-info-card h3 {
	margin-top: 0;
	color: #333;
}

.cro-info-card ol {
	margin: 0;
	padding-left: 20px;
}

.cro-info-card li {
	margin-bottom: 8px;
}

.cro-back-link {
	text-decoration: none;
	color: #666;
	font-size: 14px;
	font-weight: normal;
}

.cro-back-link:hover {
	color: #333;
}
</style>
