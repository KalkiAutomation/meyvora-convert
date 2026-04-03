<?php
/**
 * Create New A/B Test Page
 *
 * @package Meyvora_Convert
 */

defined( 'ABSPATH' ) || exit;


// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template/view: variables are file-local.
$ai_prefill_campaign_id = isset( $_GET['campaign_id'] ) ? absint( wp_unslash( $_GET['campaign_id'] ) ) : 0;
$ai_prefill             = null;
if ( isset( $_GET['ai_variant'] ) ) {
	$raw_b64 = isset( $_GET['ai_variant'] ) ? sanitize_text_field( wp_unslash( $_GET['ai_variant'] ) ) : '';
	if ( is_string( $raw_b64 ) && $raw_b64 !== '' ) {
		$decoded = base64_decode( $raw_b64, true );
		if ( false !== $decoded && is_string( $decoded ) ) {
			$json_str = $decoded;
			$var      = json_decode( $json_str, true );
			if ( ! is_array( $var ) ) {
				$var = json_decode( rawurldecode( $json_str ), true );
			}
			if ( is_array( $var ) && ! empty( $var['name'] ) ) {
				$ai_prefill = array(
					'variation_name' => sanitize_text_field( (string) $var['name'] ),
					'headline'       => isset( $var['new_headline'] ) ? sanitize_text_field( (string) $var['new_headline'] ) : '',
					'body'           => isset( $var['new_body'] ) ? sanitize_textarea_field( (string) $var['new_body'] ) : '',
					'cta'            => isset( $var['new_cta'] ) ? sanitize_text_field( (string) $var['new_cta'] ) : '',
				);
			}
		}
	}
}

// Check for form submission
if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['meyvc_create_ab_test'] ) ) {
	check_admin_referer( 'meyvc_create_ab_test' );

	if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
		wp_die( esc_html__( 'Unauthorized', 'meyvora-convert' ) );
	}

	$ab_model = new MEYVC_AB_Test();

	$data = array(
		'name'               => sanitize_text_field( wp_unslash( $_POST['test_name'] ?? '' ) ),
		'campaign_id'        => absint( $_POST['campaign_id'] ?? 0 ),
		'metric'             => sanitize_text_field( wp_unslash( $_POST['metric'] ?? 'conversion_rate' ) ),
		'min_sample_size'    => absint( $_POST['min_sample_size'] ?? 200 ),
		'confidence_level'   => absint( $_POST['confidence_level'] ?? 95 ),
		'auto_apply_winner'  => isset( $_POST['auto_apply_winner'] ),
	);
	$test_id = $ab_model->create( $data );

	if ( ! is_wp_error( $test_id ) ) {
		$campaign_id_post    = absint( $_POST['campaign_id'] ?? 0 );
		$ai_variation_name   = isset( $_POST['ai_variation_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ai_variation_name'] ) ) : '';
		if ( $ai_variation_name !== '' && $campaign_id_post > 0 && class_exists( 'MEYVC_AI_AB_Hypothesis' ) ) {
			$payload = array(
				'name'         => $ai_variation_name,
				'new_headline' => isset( $_POST['ai_variation_headline'] ) ? sanitize_text_field( wp_unslash( $_POST['ai_variation_headline'] ) ) : '',
				'new_body'     => isset( $_POST['ai_variation_body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ai_variation_body'] ) ) : '',
				'new_cta'      => isset( $_POST['ai_variation_cta'] ) ? sanitize_text_field( wp_unslash( $_POST['ai_variation_cta'] ) ) : '',
			);
			$json_enc = wp_json_encode( $payload );
			if ( is_string( $json_enc ) ) {
				MEYVC_AI_AB_Hypothesis::maybe_add_challenger_variation( (int) $test_id, $campaign_id_post, base64_encode( $json_enc ) );
			}
		}
		do_action( 'meyvc_abtest_created', (int) $test_id, $data );
		wp_safe_redirect( admin_url( 'admin.php?page=meyvc-ab-test-view&id=' . $test_id . '&message=created' ) );
		exit;
	}
}

// Get campaigns for dropdown
global $wpdb;
$campaigns_table = $wpdb->prefix . 'meyvc_campaigns';
$cache_key_camp = 'meyvora_meyvc_' . md5( serialize( array( 'admin_ab_test_new_campaigns_dropdown', $campaigns_table ) ) );
$campaigns      = wp_cache_get( $cache_key_camp, 'meyvora_meyvc' );
if ( false === $campaigns ) {
	$campaigns = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
		$wpdb->prepare( 'SELECT id, name, status FROM %i ORDER BY name ASC', $campaigns_table )
	);
	if ( ! is_array( $campaigns ) ) {
		$campaigns = array();
	}
	wp_cache_set( $cache_key_camp, $campaigns, 'meyvora_meyvc', 300 );
}
$test_name_value = '';
if ( is_array( $ai_prefill ) && ! empty( $ai_prefill['variation_name'] ) ) {
	$test_name_value = 'AI: ' . $ai_prefill['variation_name'];
}
?>

	<?php if ( is_array( $ai_prefill ) ) : ?>
	<div class="notice notice-info is-dismissible">
		<p><?php esc_html_e( 'Pre-filled by AI suggestion · Review all fields before saving', 'meyvora-convert' ); ?></p>
	</div>
	<?php endif; ?>

	<form method="post" class="meyvc-form">
		<?php wp_nonce_field( 'meyvc_create_ab_test' ); ?>

		<div class="meyvc-form-card">
			<h2><?php esc_html_e( 'Test Details', 'meyvora-convert' ); ?></h2>

			<div class="meyvc-fields-grid meyvc-fields-grid--1col">
				<div class="meyvc-field meyvc-col-12">
					<label for="test_name" class="meyvc-field__label"><?php esc_html_e( 'Test Name', 'meyvora-convert' ); ?></label>
					<div class="meyvc-field__control">
						<input type="text"
							   id="test_name"
							   name="test_name"
							   class="regular-text"
							   required
							   value="<?php echo esc_attr( $test_name_value ); ?>"
							   placeholder="<?php esc_attr_e( 'e.g., Homepage Popup - Headline Test', 'meyvora-convert' ); ?>" />
					</div>
					<span class="meyvc-help"><?php esc_html_e( 'A descriptive name for this test', 'meyvora-convert' ); ?></span>
				</div>
				<div class="meyvc-field meyvc-col-12">
					<label for="campaign_id" class="meyvc-field__label"><?php esc_html_e( 'Campaign to Test', 'meyvora-convert' ); ?></label>
					<div class="meyvc-field__control">
						<select id="campaign_id" name="campaign_id" class="meyvc-selectwoo" data-placeholder="<?php esc_attr_e( 'Select a campaign...', 'meyvora-convert' ); ?>" required>
							<option value=""><?php esc_html_e( 'Select a campaign...', 'meyvora-convert' ); ?></option>
							<?php foreach ( $campaigns as $campaign ) : ?>
							<option value="<?php echo esc_attr( (string) $campaign->id ); ?>"
								<?php selected( (int) $campaign->id, $ai_prefill_campaign_id ); ?>>
								<?php echo esc_html( $campaign->name ); ?>
								(<?php echo esc_html( $campaign->status ); ?>)
							</option>
							<?php endforeach; ?>
						</select>
					</div>
					<span class="meyvc-help"><?php esc_html_e( 'This campaign will be the "Control" version', 'meyvora-convert' ); ?></span>
				</div>
			</div>
		</div>

		<?php if ( is_array( $ai_prefill ) ) : ?>
		<div class="meyvc-form-card meyvc-ai-challenger-card">
			<h2><?php esc_html_e( 'Challenger variation', 'meyvora-convert' ); ?></h2>
			<p class="description"><?php esc_html_e( 'This variation is created when you save the test. Edit copy below if needed.', 'meyvora-convert' ); ?></p>
			<div class="meyvc-fields-grid meyvc-fields-grid--1col">
				<div class="meyvc-field meyvc-col-12">
					<label for="ai_variation_name" class="meyvc-field__label"><?php esc_html_e( 'Variation name', 'meyvora-convert' ); ?></label>
					<div class="meyvc-field__control">
						<input type="text" id="ai_variation_name" name="ai_variation_name" class="regular-text" value="<?php echo esc_attr( $ai_prefill['variation_name'] ); ?>" />
					</div>
				</div>
				<div class="meyvc-field meyvc-col-12">
					<label for="ai_variation_headline" class="meyvc-field__label"><?php esc_html_e( 'Headline', 'meyvora-convert' ); ?></label>
					<div class="meyvc-field__control">
						<input type="text" id="ai_variation_headline" name="ai_variation_headline" class="large-text" value="<?php echo esc_attr( $ai_prefill['headline'] ); ?>" />
					</div>
				</div>
				<div class="meyvc-field meyvc-col-12">
					<label for="ai_variation_body" class="meyvc-field__label"><?php esc_html_e( 'Body', 'meyvora-convert' ); ?></label>
					<div class="meyvc-field__control">
						<textarea id="ai_variation_body" name="ai_variation_body" class="large-text" rows="4"><?php echo esc_textarea( $ai_prefill['body'] ); ?></textarea>
					</div>
				</div>
				<div class="meyvc-field meyvc-col-12">
					<label for="ai_variation_cta" class="meyvc-field__label"><?php esc_html_e( 'CTA', 'meyvora-convert' ); ?></label>
					<div class="meyvc-field__control">
						<input type="text" id="ai_variation_cta" name="ai_variation_cta" class="regular-text" value="<?php echo esc_attr( $ai_prefill['cta'] ); ?>" />
					</div>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<div class="meyvc-form-card">
			<h2><?php esc_html_e( 'Test Settings', 'meyvora-convert' ); ?></h2>

			<div class="meyvc-fields-grid meyvc-fields-grid--1col">
				<div class="meyvc-field meyvc-col-12">
					<label for="metric" class="meyvc-field__label"><?php esc_html_e( 'Primary Metric', 'meyvora-convert' ); ?></label>
					<div class="meyvc-field__control">
						<select id="metric" name="metric" class="meyvc-selectwoo" data-placeholder="<?php esc_attr_e( 'Conversion Rate', 'meyvora-convert' ); ?>">
							<option value="conversion_rate"><?php esc_html_e( 'Conversion Rate', 'meyvora-convert' ); ?></option>
							<option value="revenue_per_visitor"><?php esc_html_e( 'Revenue per Visitor', 'meyvora-convert' ); ?></option>
						</select>
					</div>
					<span class="meyvc-help"><?php esc_html_e( 'What metric to optimize for', 'meyvora-convert' ); ?></span>
				</div>
				<div class="meyvc-field meyvc-col-12">
					<label for="min_sample_size" class="meyvc-field__label"><?php esc_html_e( 'Minimum Sample Size', 'meyvora-convert' ); ?></label>
					<div class="meyvc-field__control">
						<input type="number"
							   id="min_sample_size"
							   name="min_sample_size"
							   value="200"
							   min="50"
							   max="10000"
							   class="small-text" />
						<span><?php esc_html_e( 'impressions per variation', 'meyvora-convert' ); ?></span>
					</div>
					<span class="meyvc-help"><?php esc_html_e( 'Minimum visitors before results are considered reliable', 'meyvora-convert' ); ?></span>
				</div>
				<div class="meyvc-field meyvc-col-12">
					<label for="confidence_level" class="meyvc-field__label"><?php esc_html_e( 'Confidence Level', 'meyvora-convert' ); ?></label>
					<div class="meyvc-field__control">
						<select id="confidence_level" name="confidence_level" class="meyvc-selectwoo" data-placeholder="95% (Recommended)">
							<option value="80">80%</option>
							<option value="85">85%</option>
							<option value="90">90%</option>
							<option value="95" selected>95% (Recommended)</option>
							<option value="99">99%</option>
						</select>
					</div>
					<span class="meyvc-help"><?php esc_html_e( 'Statistical confidence required to declare a winner', 'meyvora-convert' ); ?></span>
				</div>
				<div class="meyvc-field meyvc-col-12">
					<div class="meyvc-field__control">
						<label>
							<input type="checkbox" name="auto_apply_winner" value="1" />
							<?php esc_html_e( 'Automatically apply winning variation to original campaign', 'meyvora-convert' ); ?>
						</label>
					</div>
					<span class="meyvc-help"><?php esc_html_e( 'When a winner is detected, automatically update the campaign', 'meyvora-convert' ); ?></span>
				</div>
			</div>
		</div>

		<div class="meyvc-form-card meyvc-info-card">
			<h3><?php esc_html_e( 'What happens next?', 'meyvora-convert' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'After creating the test, you\'ll be able to add variations', 'meyvora-convert' ); ?></li>
				<li><?php esc_html_e( 'Each variation can have different content, styling, or offers', 'meyvora-convert' ); ?></li>
				<li><?php esc_html_e( 'Traffic will be split between variations automatically', 'meyvora-convert' ); ?></li>
				<li><?php esc_html_e( 'Results will show statistical significance when reached', 'meyvora-convert' ); ?></li>
			</ol>
		</div>

		<p class="submit">
			<button type="submit" name="meyvc_create_ab_test" class="button button-primary button-large">
				<?php esc_html_e( 'Create A/B Test', 'meyvora-convert' ); ?>
			</button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=meyvc-ab-tests' ) ); ?>" class="button button-large">
				<?php esc_html_e( 'Cancel', 'meyvora-convert' ); ?>
			</a>
		</p>
	</form>
