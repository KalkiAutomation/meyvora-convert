<?php
/**
 * A/B Test Detail View with Variation Management
 *
 * @package Meyvora_Convert
 */

defined( 'ABSPATH' ) || exit;


// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template/view: variables are file-local.
$test_id = MEYVC_Security::get_query_var_absint( 'id' );
$ab_model = new MEYVC_AB_Test();
$test     = $ab_model->get( $test_id );

if ( ! $test ) {
	wp_die( esc_html__( 'Test not found', 'meyvora-convert' ) );
}

// Handle add variation
if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['meyvc_add_variation'] ) ) {
	check_admin_referer( 'meyvc_add_variation' );

	if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
		wp_die( esc_html__( 'Unauthorized', 'meyvora-convert' ) );
	}

	global $wpdb;
	$campaigns_table = $wpdb->prefix . 'meyvc_campaigns';
	$cache_key_orig_a = 'meyvora_meyvc_' . md5( serialize( array( 'ab_test_view_original_campaign_array', $campaigns_table, $test->original_campaign_id ) ) );
	$cached_original  = wp_cache_get( $cache_key_orig_a, 'meyvora_meyvc' );
	if ( false === $cached_original ) {
		$original = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$campaigns_table,
				$test->original_campaign_id
			),
			ARRAY_A
		);
		wp_cache_set( $cache_key_orig_a, $original, 'meyvora_meyvc', 300 );
	} else {
		$original = is_array( $cached_original ) ? $cached_original : null;
	}

	if ( $original ) {
		$variation_data = $original;
		$content        = json_decode( $original['content'], true );
		if ( ! is_array( $content ) ) {
			$content = array();
		}
		if ( ! empty( $_POST['variation_headline'] ) ) {
			$content['headline'] = sanitize_text_field( wp_unslash( $_POST['variation_headline'] ) );
		}
		if ( ! empty( $_POST['variation_subheadline'] ) ) {
			$content['subheadline'] = sanitize_text_field( wp_unslash( $_POST['variation_subheadline'] ) );
		}
		if ( ! empty( $_POST['variation_cta'] ) ) {
			$content['cta_text'] = sanitize_text_field( wp_unslash( $_POST['variation_cta'] ) );
		}
		$variation_data['content'] = wp_json_encode( $content );

		$ab_model->add_variation( $test_id, array(
			'name'           => sanitize_text_field( wp_unslash( $_POST['variation_name'] ?? '' ) ),
			'traffic_weight' => absint( $_POST['traffic_weight'] ?? 50 ),
			'campaign_data'   => wp_json_encode( $variation_data ),
		) );
		wp_safe_redirect( admin_url( 'admin.php?page=meyvc-ab-test-view&id=' . $test_id . '&message=variation_added' ) );
		exit;
	}
}

// Refresh test data
$test  = $ab_model->get( $test_id );
$stats = null;
if ( $test->status === 'running' || $test->status === 'paused' || $test->status === 'completed' ) {
	$stats = class_exists( 'MEYVC_AB_Statistics' ) ? MEYVC_AB_Statistics::calculate( $test ) : null;
}
$enough_data = $stats && ! empty( $stats['enough_data'] );

$status_message = $stats && method_exists( 'MEYVC_AB_Statistics', 'get_status_message' )
	? MEYVC_AB_Statistics::get_status_message( $stats, $test )
	: '';

global $wpdb;
$campaigns_table    = $wpdb->prefix . 'meyvc_campaigns';
$cache_key_orig_o   = 'meyvora_meyvc_' . md5( serialize( array( 'ab_test_view_original_campaign_object', $campaigns_table, $test->original_campaign_id ) ) );
$cached_orig_camp   = wp_cache_get( $cache_key_orig_o, 'meyvora_meyvc' );
if ( false === $cached_orig_camp ) {
	$original_campaign = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached above.
		$wpdb->prepare(
			'SELECT * FROM %i WHERE id = %d',
			$campaigns_table,
			$test->original_campaign_id
		)
	);
	wp_cache_set( $cache_key_orig_o, $original_campaign, 'meyvora_meyvc', 300 );
} else {
	$original_campaign = is_object( $cached_orig_camp ) ? $cached_orig_camp : null;
}
$original_content = $original_campaign ? json_decode( $original_campaign->content, true ) : array();
if ( ! is_array( $original_content ) ) {
	$original_content = array();
}
?>

	<?php
	$notice_msg_key = MEYVC_Security::get_query_var_key( 'message' );
	if ( $notice_msg_key !== '' ) :
		$messages = array(
			'created'          => __( 'A/B Test created! Now add variations below.', 'meyvora-convert' ),
			'variation_added'  => __( 'Variation added successfully.', 'meyvora-convert' ),
		);
		?>
	<div class="notice notice-success is-dismissible">
		<p><?php echo esc_html( $messages[ $notice_msg_key ] ?? '' ); ?></p>
	</div>
	<?php endif; ?>

	<?php
	$insufficient_data = ( $test->status === 'running' || $test->status === 'paused' ) && ! $enough_data;
	?>
	<?php if ( $insufficient_data ) : ?>
	<div class="meyvc-ab-warning-banner" role="alert">
		<span class="meyvc-ab-warning-icon" aria-hidden="true"><?php echo wp_kses( MEYVC_Icons::svg( 'alert', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?></span>

		<div class="meyvc-ab-warning-content">
			<strong><?php esc_html_e( 'Not enough data yet', 'meyvora-convert' ); ?></strong>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: minimum sample size per variation */
						__( 'Each variation needs at least %s impressions before results are statistically reliable. Keep the test running.', 'meyvora-convert' ),
						number_format_i18n( (int) $test->min_sample_size )
					)
				);
				?>
			</p>
		</div>
	</div>
	<?php endif; ?>

	<?php if ( $status_message ) : ?>
	<div class="meyvc-test-status-box <?php echo ( $stats && ! empty( $stats['has_winner'] ) ) ? 'has-winner' : ''; ?> <?php echo ! $enough_data ? 'meyvc-test-status-box--insufficient' : ''; ?>">
		<p><?php echo esc_html( $status_message ); ?></p>
		<?php if ( $stats && isset( $stats['confidence_level'] ) ) : ?>
			<p class="meyvc-test-meta"><?php esc_html_e( 'Confidence level for this test:', 'meyvora-convert' ); ?> <strong><?php echo esc_html( (int) $stats['confidence_level'] ); ?>%</strong></p>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<div class="meyvc-test-actions">
		<?php if ( $test->status === 'draft' ) : ?>
			<?php if ( count( $test->variations ) >= 2 ) : ?>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=meyvc-ab-tests&action=start&id=' . $test->id ), 'start_ab_test' ) ); ?>"
			   class="button button-primary button-large">
				<?php esc_html_e( 'Start Test', 'meyvora-convert' ); ?>
			</a>
			<?php else : ?>
			<button class="button button-primary button-large" disabled title="<?php esc_attr_e( 'Add at least one variation first', 'meyvora-convert' ); ?>">
				<?php esc_html_e( 'Start Test', 'meyvora-convert' ); ?>
			</button>
			<span class="description"><?php esc_html_e( 'Add at least one variation to start the test', 'meyvora-convert' ); ?></span>
			<?php endif; ?>
		<?php elseif ( $test->status === 'running' ) : ?>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=meyvc-ab-tests&action=pause&id=' . $test->id ), 'pause_ab_test' ) ); ?>"
			   class="button">
				<?php esc_html_e( 'Pause Test', 'meyvora-convert' ); ?>
			</a>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=meyvc-ab-tests&action=complete&id=' . $test->id ), 'complete_ab_test' ) ); ?>"
			   class="button"
			   onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to end this test?', 'meyvora-convert' ) ); ?>');">
				<?php esc_html_e( 'End Test', 'meyvora-convert' ); ?>
			</a>
		<?php elseif ( $test->status === 'paused' ) : ?>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=meyvc-ab-tests&action=start&id=' . $test->id ), 'start_ab_test' ) ); ?>"
			   class="button button-primary">
				<?php esc_html_e( 'Resume Test', 'meyvora-convert' ); ?>
			</a>
		<?php endif; ?>

		<?php if ( $stats && ! empty( $stats['has_winner'] ) && ! empty( $stats['enough_data'] ) && $test->status === 'running' && ! empty( $stats['winner']['variation_id'] ) ) : ?>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=meyvc-ab-tests&action=apply_winner&id=' . $test->id . '&winner=' . $stats['winner']['variation_id'] ), 'apply_winner' ) ); ?>"
			   class="button button-primary"
			   onclick="return confirm('<?php echo esc_js( __( 'This will update the original campaign with the winning variation. Continue?', 'meyvora-convert' ) ); ?>');">
				<?php esc_html_e( 'Apply Winner & End Test', 'meyvora-convert' ); ?>
			</a>
		<?php endif; ?>

		<?php if ( current_user_can( 'manage_meyvora_convert' ) ) : ?>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=meyvc-ab-tests&action=delete&id=' . $test->id ), 'delete_ab_test' ) ); ?>"
			   class="button"
			   onclick="return confirm('<?php echo esc_js( __( 'Delete this A/B test? This cannot be undone.', 'meyvora-convert' ) ); ?>');">
				<?php esc_html_e( 'Delete', 'meyvora-convert' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<h2><?php esc_html_e( 'Variations', 'meyvora-convert' ); ?></h2>

	<?php
	$variations_chart = isset( $test->variations ) && is_array( $test->variations ) ? $test->variations : array();
	$chart_labels     = array();
	$chart_impr       = array();
	$chart_conv       = array();
	foreach ( $variations_chart as $vch ) {
		$chart_labels[] = isset( $vch->name ) ? (string) $vch->name : '';
		$chart_impr[]   = isset( $vch->impressions ) ? (int) $vch->impressions : 0;
		$chart_conv[]   = isset( $vch->conversions ) ? (int) $vch->conversions : 0;
	}
	?>
	<?php if ( count( $chart_labels ) > 0 ) : ?>
	<div class="meyvc-ab-chart-card meyvc-card" style="margin-bottom:1.5rem;">
		<canvas id="meyvc-ab-variation-chart" height="120" aria-label="<?php esc_attr_e( 'Variations impressions and conversions', 'meyvora-convert' ); ?>"></canvas>
	</div>
	<?php
	wp_localize_script(
		'meyvc-admin',
		'meyvcAbChartData',
		array(
			'labels'           => $chart_labels,
			'impressions'      => $chart_impr,
			'conversions'      => $chart_conv,
			'labelImpressions' => __( 'Impressions', 'meyvora-convert' ),
			'labelConversions' => __( 'Conversions', 'meyvora-convert' ),
		)
	);
	?>
	<?php endif; ?>

	<div class="meyvc-variations-grid">
		<?php
		$variations = isset( $test->variations ) && is_array( $test->variations ) ? $test->variations : array();
		foreach ( $variations as $variation ) :
			$is_control = ! empty( $variation->is_control );
			$is_winner  = $stats && ! empty( $stats['has_winner'] ) && ! empty( $stats['winner']['variation_id'] ) && (int) $stats['winner']['variation_id'] === (int) $variation->id;
			$var_stats  = $is_control ? ( $stats['control'] ?? null ) : ( $stats['challengers'][ $variation->id ] ?? null );
		?>
		<div class="meyvc-variation-card <?php echo $is_control ? 'is-control' : ''; ?> <?php echo $is_winner ? 'is-winner' : ''; ?>">
			<div class="meyvc-variation-header">
				<?php if ( $is_winner ) : ?>
					<span class="meyvc-badge meyvc-badge--winner"><?php echo wp_kses( MEYVC_Icons::svg( 'trophy', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ); ?> <?php esc_html_e( 'Winner', 'meyvora-convert' ); ?></span>

				<?php elseif ( $is_control ) : ?>
					<span class="meyvc-badge meyvc-badge--control"><?php esc_html_e( 'Control', 'meyvora-convert' ); ?></span>
				<?php else : ?>
					<span class="meyvc-badge"><?php esc_html_e( 'Challenger', 'meyvora-convert' ); ?></span>
				<?php endif; ?>
				<h3><?php echo esc_html( $variation->name ); ?></h3>
				<span class="meyvc-traffic-weight"><?php echo esc_html( (int) $variation->traffic_weight ); ?>% <?php esc_html_e( 'traffic', 'meyvora-convert' ); ?></span>
			</div>

			<div class="meyvc-variation-stats">
				<div class="meyvc-stat">
					<span class="meyvc-stat-value"><?php echo esc_html( number_format_i18n( (int) $variation->impressions ) ); ?></span>
					<span class="meyvc-stat-label"><?php esc_html_e( 'Impressions', 'meyvora-convert' ); ?></span>
				</div>
				<div class="meyvc-stat">
					<span class="meyvc-stat-value"><?php echo esc_html( number_format_i18n( (int) $variation->conversions ) ); ?></span>
					<span class="meyvc-stat-label"><?php esc_html_e( 'Conversions', 'meyvora-convert' ); ?></span>
				</div>
				<div class="meyvc-stat meyvc-stat--highlight">
					<span class="meyvc-stat-value">
						<?php
						$impressions = (int) $variation->impressions;
						$conversions = (int) $variation->conversions;
						$rate        = $impressions > 0 ? ( $conversions / $impressions ) * 100 : 0;
						echo esc_html( number_format( $rate, 2 ) . '%' );
						?>
					</span>
					<span class="meyvc-stat-label"><?php esc_html_e( 'Conv. Rate', 'meyvora-convert' ); ?></span>
				</div>
				<div class="meyvc-stat">
					<span class="meyvc-stat-value">
						<?php
						$rev = (float) ( $variation->revenue ?? 0 );
						if ( function_exists( 'wc_price' ) ) {
							echo wp_kses_post( wc_price( $rev ) );
						} else {
							echo esc_html( number_format( $rev, 2 ) );
						}
						?>
					</span>
					<span class="meyvc-stat-label"><?php esc_html_e( 'Revenue', 'meyvora-convert' ); ?></span>
				</div>
			</div>

			<?php
			$var_enough_data = (int) $variation->impressions >= (int) $test->min_sample_size;
			?>
			<?php if ( $is_control ) : ?>
			<div class="meyvc-variation-comparison">
				<?php if ( ! $var_enough_data ) : ?>
					<div class="meyvc-variation-not-enough-data">
						<?php esc_html_e( 'Not enough data', 'meyvora-convert' ); ?>
						<?php
						echo ' ';
						echo esc_html(
							sprintf(
								/* translators: 1: current impressions, 2: required min */
								__( '(%1$s / %2$s impressions)', 'meyvora-convert' ),
								number_format_i18n( (int) $variation->impressions ),
								number_format_i18n( (int) $test->min_sample_size )
							)
						);
						?>
					</div>
				<?php else : ?>
					<span class="meyvc-baseline"><?php esc_html_e( 'Baseline for comparison', 'meyvora-convert' ); ?></span>
				<?php endif; ?>
			</div>
			<?php elseif ( ! $is_control && $var_stats && is_array( $var_stats ) ) : ?>
			<div class="meyvc-variation-comparison">
				<?php if ( ! $var_enough_data ) : ?>
					<div class="meyvc-variation-not-enough-data">
						<?php esc_html_e( 'Not enough data', 'meyvora-convert' ); ?>
						<?php
						echo ' ';
						echo esc_html(
							sprintf(
								/* translators: 1: current impressions, 2: required min */
								__( '(%1$s / %2$s impressions)', 'meyvora-convert' ),
								number_format_i18n( (int) $variation->impressions ),
								number_format_i18n( (int) $test->min_sample_size )
							)
						);
						?>
					</div>
				<?php else : ?>
					<div class="meyvc-significance-row">
						<span class="meyvc-significance-label"><?php esc_html_e( 'Significance', 'meyvora-convert' ); ?>:</span>
						<span class="meyvc-significance-value <?php echo ! empty( $var_stats['is_significant'] ) ? 'significant' : 'not-significant'; ?>">
							<?php echo ! empty( $var_stats['is_significant'] ) ? esc_html__( 'Significant', 'meyvora-convert' ) : esc_html__( 'Not significant', 'meyvora-convert' ); ?>
						</span>
					</div>
					<div class="meyvc-improvement <?php echo ( $var_stats['improvement'] ?? 0 ) >= 0 ? 'positive' : 'negative'; ?>">
						<?php echo esc_html( $var_stats['improvement_formatted'] ?? '0%' ); ?>
						<?php esc_html_e( 'vs Control', 'meyvora-convert' ); ?>
					</div>
					<div class="meyvc-confidence">
						<span class="meyvc-confidence-label"><?php esc_html_e( 'Confidence', 'meyvora-convert' ); ?>:</span>
						<div class="meyvc-confidence-bar">
							<div class="meyvc-confidence-fill meyvc-bar-fill <?php echo ! empty( $var_stats['is_significant'] ) ? 'significant' : ''; ?>"
								 style="--meyvc-bar-width: <?php echo esc_attr( min( 100, (float) ( $var_stats['confidence'] ?? 0 ) ) ); ?>%"></div>
						</div>
						<span class="meyvc-confidence-value"><?php echo esc_html( number_format( (float) ( $var_stats['confidence'] ?? 0 ), 1 ) ); ?>%</span>
					</div>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>
	</div>

	<?php if ( $test->status === 'draft' ) : ?>
	<div class="meyvc-add-variation">
		<h2><?php esc_html_e( 'Add New Variation', 'meyvora-convert' ); ?></h2>

		<form method="post" class="meyvc-variation-form">
			<?php wp_nonce_field( 'meyvc_add_variation' ); ?>

			<div class="meyvc-form-row">
				<div class="meyvc-form-col">
					<label for="variation_name"><?php esc_html_e( 'Variation Name', 'meyvora-convert' ); ?></label>
					<input type="text"
						   id="variation_name"
						   name="variation_name"
						   required
						   placeholder="<?php esc_attr_e( 'e.g., Version B - New Headline', 'meyvora-convert' ); ?>" />
				</div>
				<div class="meyvc-form-col meyvc-form-col--small">
					<label for="traffic_weight"><?php esc_html_e( 'Traffic %', 'meyvora-convert' ); ?></label>
					<input type="number"
						   id="traffic_weight"
						   name="traffic_weight"
						   value="50"
						   min="1"
						   max="100" />
				</div>
			</div>

			<h4><?php esc_html_e( 'What to Change', 'meyvora-convert' ); ?></h4>
			<p class="description"><?php esc_html_e( 'Leave blank to keep the original value', 'meyvora-convert' ); ?></p>

			<div class="meyvc-form-row">
				<div class="meyvc-form-col">
					<label for="variation_headline"><?php esc_html_e( 'Headline', 'meyvora-convert' ); ?></label>
					<input type="text"
						   id="variation_headline"
						   name="variation_headline"
						   placeholder="<?php echo esc_attr( $original_content['headline'] ?? '' ); ?>" />
					<span class="meyvc-original">
						<?php esc_html_e( 'Original:', 'meyvora-convert' ); ?>
						<?php echo esc_html( $original_content['headline'] ?? 'N/A' ); ?>
					</span>
				</div>
			</div>

			<div class="meyvc-form-row">
				<div class="meyvc-form-col">
					<label for="variation_subheadline"><?php esc_html_e( 'Subheadline', 'meyvora-convert' ); ?></label>
					<input type="text"
						   id="variation_subheadline"
						   name="variation_subheadline"
						   placeholder="<?php echo esc_attr( $original_content['subheadline'] ?? '' ); ?>" />
				</div>
			</div>

			<div class="meyvc-form-row">
				<div class="meyvc-form-col">
					<label for="variation_cta"><?php esc_html_e( 'CTA Button Text', 'meyvora-convert' ); ?></label>
					<input type="text"
						   id="variation_cta"
						   name="variation_cta"
						   placeholder="<?php echo esc_attr( $original_content['cta_text'] ?? '' ); ?>" />
				</div>
			</div>

			<p class="submit">
				<button type="submit" name="meyvc_add_variation" class="button button-primary">
					<?php esc_html_e( 'Add Variation', 'meyvora-convert' ); ?>
				</button>
			</p>
		</form>
	</div>
	<?php endif; ?>

	<div class="meyvc-test-settings">
		<h2><?php esc_html_e( 'Test Settings', 'meyvora-convert' ); ?></h2>
		<div class="meyvc-fields-grid meyvc-fields-grid--1col">
			<div class="meyvc-field meyvc-col-12">
				<span class="meyvc-field__label"><?php esc_html_e( 'Original Campaign', 'meyvora-convert' ); ?></span>
				<div class="meyvc-field__control">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=meyvc-campaign-edit&campaign_id=' . (int) $test->original_campaign_id ) ); ?>">
						<?php echo esc_html( $original_campaign->name ?? 'N/A' ); ?>
					</a>
				</div>
			</div>
			<div class="meyvc-field meyvc-col-12">
				<span class="meyvc-field__label"><?php esc_html_e( 'Primary Metric', 'meyvora-convert' ); ?></span>
				<div class="meyvc-field__control"><?php echo esc_html( ucwords( str_replace( '_', ' ', $test->metric ) ) ); ?></div>
			</div>
			<div class="meyvc-field meyvc-col-12">
				<span class="meyvc-field__label"><?php esc_html_e( 'Minimum Sample Size', 'meyvora-convert' ); ?></span>
				<div class="meyvc-field__control"><?php echo esc_html( number_format( (int) $test->min_sample_size ) ); ?> <?php esc_html_e( 'per variation', 'meyvora-convert' ); ?></div>
			</div>
			<div class="meyvc-field meyvc-col-12">
				<span class="meyvc-field__label"><?php esc_html_e( 'Confidence Level', 'meyvora-convert' ); ?></span>
				<div class="meyvc-field__control"><?php echo esc_html( (int) $test->confidence_level ); ?>%</div>
			</div>
			<div class="meyvc-field meyvc-col-12">
				<span class="meyvc-field__label"><?php esc_html_e( 'Auto-apply Winner', 'meyvora-convert' ); ?></span>
				<div class="meyvc-field__control">
					<?php
					echo $test->auto_apply_winner
						? wp_kses( MEYVC_Icons::svg( 'check', array( 'class' => 'meyvc-ico' ) ), MEYVC_Icons::get_svg_kses_allowed() ) . ' ' . esc_html__( 'Yes', 'meyvora-convert' )
						: esc_html__( 'No', 'meyvora-convert' );
					?>
				</div>
			</div>
			<?php if ( ! empty( $test->started_at ) ) : ?>
			<div class="meyvc-field meyvc-col-12">
				<span class="meyvc-field__label"><?php esc_html_e( 'Started', 'meyvora-convert' ); ?></span>
				<div class="meyvc-field__control"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $test->started_at ) ) ); ?></div>
			</div>
			<?php endif; ?>
			<?php if ( ! empty( $test->completed_at ) ) : ?>
			<div class="meyvc-field meyvc-col-12">
				<span class="meyvc-field__label"><?php esc_html_e( 'Completed', 'meyvora-convert' ); ?></span>
				<div class="meyvc-field__control"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $test->completed_at ) ) ); ?></div>
			</div>
			<?php endif; ?>
		</div>
	</div>
