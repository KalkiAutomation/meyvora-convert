<?php
/**
 * A/B Test Detail View with Variation Management
 *
 * @package Meyvora_Convert
 */

defined( 'ABSPATH' ) || exit;

$test_id  = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
$ab_model = new CRO_AB_Test();
$test     = $ab_model->get( $test_id );

if ( ! $test ) {
	wp_die( esc_html__( 'Test not found', 'meyvora-convert' ) );
}

// Handle add variation
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['cro_add_variation'] ) ) {
	check_admin_referer( 'cro_add_variation' );

	if ( ! current_user_can( 'manage_meyvora_convert' ) ) {
		wp_die( esc_html__( 'Unauthorized', 'meyvora-convert' ) );
	}

	global $wpdb;
	$campaigns_table = $wpdb->prefix . 'cro_campaigns';
	$original        = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM {$campaigns_table} WHERE id = %d",
		$test->original_campaign_id
	), ARRAY_A );

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
		wp_safe_redirect( admin_url( 'admin.php?page=cro-ab-test-view&id=' . $test_id . '&message=variation_added' ) );
		exit;
	}
}

// Refresh test data
$test  = $ab_model->get( $test_id );
$stats = null;
if ( $test->status === 'running' || $test->status === 'paused' || $test->status === 'completed' ) {
	$stats = class_exists( 'CRO_AB_Statistics' ) ? CRO_AB_Statistics::calculate( $test ) : null;
}
$enough_data = $stats && ! empty( $stats['enough_data'] );

$status_message = $stats && method_exists( 'CRO_AB_Statistics', 'get_status_message' )
	? CRO_AB_Statistics::get_status_message( $stats, $test )
	: '';

global $wpdb;
$campaigns_table   = $wpdb->prefix . 'cro_campaigns';
$original_campaign = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$campaigns_table} WHERE id = %d",
	$test->original_campaign_id
) );
$original_content = $original_campaign ? json_decode( $original_campaign->content, true ) : array();
if ( ! is_array( $original_content ) ) {
	$original_content = array();
}
?>

	<?php if ( isset( $_GET['message'] ) ) : ?>
	<div class="notice notice-success is-dismissible">
		<p>
			<?php
			$messages = array(
				'created'         => __( 'A/B Test created! Now add variations below.', 'meyvora-convert' ),
				'variation_added'  => __( 'Variation added successfully.', 'meyvora-convert' ),
			);
			$msg_key = sanitize_text_field( wp_unslash( $_GET['message'] ) );
			echo esc_html( $messages[ $msg_key ] ?? '' );
			?>
		</p>
	</div>
	<?php endif; ?>

	<?php
	$insufficient_data = ( $test->status === 'running' || $test->status === 'paused' ) && ! $enough_data;
	?>
	<?php if ( $insufficient_data ) : ?>
	<div class="cro-ab-warning-banner" role="alert">
		<span class="cro-ab-warning-icon" aria-hidden="true"><?php echo CRO_Icons::svg( 'alert', array( 'class' => 'cro-ico' ) ); ?></span>
		<div class="cro-ab-warning-content">
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
	<div class="cro-test-status-box <?php echo ( $stats && ! empty( $stats['has_winner'] ) ) ? 'has-winner' : ''; ?> <?php echo ! $enough_data ? 'cro-test-status-box--insufficient' : ''; ?>">
		<p><?php echo esc_html( $status_message ); ?></p>
		<?php if ( $stats && isset( $stats['confidence_level'] ) ) : ?>
			<p class="cro-test-meta"><?php esc_html_e( 'Confidence level for this test:', 'meyvora-convert' ); ?> <strong><?php echo esc_html( (int) $stats['confidence_level'] ); ?>%</strong></p>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<div class="cro-test-actions">
		<?php if ( $test->status === 'draft' ) : ?>
			<?php if ( count( $test->variations ) >= 2 ) : ?>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cro-ab-tests&action=start&id=' . $test->id ), 'start_ab_test' ) ); ?>"
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
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cro-ab-tests&action=pause&id=' . $test->id ), 'pause_ab_test' ) ); ?>"
			   class="button">
				<?php esc_html_e( 'Pause Test', 'meyvora-convert' ); ?>
			</a>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cro-ab-tests&action=complete&id=' . $test->id ), 'complete_ab_test' ) ); ?>"
			   class="button"
			   onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to end this test?', 'meyvora-convert' ) ); ?>');">
				<?php esc_html_e( 'End Test', 'meyvora-convert' ); ?>
			</a>
		<?php elseif ( $test->status === 'paused' ) : ?>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cro-ab-tests&action=start&id=' . $test->id ), 'start_ab_test' ) ); ?>"
			   class="button button-primary">
				<?php esc_html_e( 'Resume Test', 'meyvora-convert' ); ?>
			</a>
		<?php endif; ?>

		<?php if ( $stats && ! empty( $stats['has_winner'] ) && ! empty( $stats['enough_data'] ) && $test->status === 'running' && ! empty( $stats['winner']['variation_id'] ) ) : ?>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cro-ab-tests&action=apply_winner&id=' . $test->id . '&winner=' . $stats['winner']['variation_id'] ), 'apply_winner' ) ); ?>"
			   class="button button-primary"
			   onclick="return confirm('<?php echo esc_js( __( 'This will update the original campaign with the winning variation. Continue?', 'meyvora-convert' ) ); ?>');">
				<?php esc_html_e( 'Apply Winner & End Test', 'meyvora-convert' ); ?>
			</a>
		<?php endif; ?>

		<?php if ( current_user_can( 'manage_meyvora_convert' ) ) : ?>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cro-ab-tests&action=delete&id=' . $test->id ), 'delete_ab_test' ) ); ?>"
			   class="button"
			   onclick="return confirm('<?php echo esc_js( __( 'Delete this A/B test? This cannot be undone.', 'meyvora-convert' ) ); ?>');">
				<?php esc_html_e( 'Delete', 'meyvora-convert' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<h2><?php esc_html_e( 'Variations', 'meyvora-convert' ); ?></h2>

	<div class="cro-variations-grid">
		<?php
		$variations = isset( $test->variations ) && is_array( $test->variations ) ? $test->variations : array();
		foreach ( $variations as $variation ) :
			$is_control = ! empty( $variation->is_control );
			$is_winner  = $stats && ! empty( $stats['has_winner'] ) && ! empty( $stats['winner']['variation_id'] ) && (int) $stats['winner']['variation_id'] === (int) $variation->id;
			$var_stats  = $is_control ? ( $stats['control'] ?? null ) : ( $stats['challengers'][ $variation->id ] ?? null );
		?>
		<div class="cro-variation-card <?php echo $is_control ? 'is-control' : ''; ?> <?php echo $is_winner ? 'is-winner' : ''; ?>">
			<div class="cro-variation-header">
				<?php if ( $is_winner ) : ?>
					<span class="cro-badge cro-badge--winner"><?php echo CRO_Icons::svg( 'trophy', array( 'class' => 'cro-ico' ) ); ?> <?php esc_html_e( 'Winner', 'meyvora-convert' ); ?></span>
				<?php elseif ( $is_control ) : ?>
					<span class="cro-badge cro-badge--control"><?php esc_html_e( 'Control', 'meyvora-convert' ); ?></span>
				<?php else : ?>
					<span class="cro-badge"><?php esc_html_e( 'Challenger', 'meyvora-convert' ); ?></span>
				<?php endif; ?>
				<h3><?php echo esc_html( $variation->name ); ?></h3>
				<span class="cro-traffic-weight"><?php echo esc_html( (int) $variation->traffic_weight ); ?>% <?php esc_html_e( 'traffic', 'meyvora-convert' ); ?></span>
			</div>

			<div class="cro-variation-stats">
				<div class="cro-stat">
					<span class="cro-stat-value"><?php echo esc_html( number_format_i18n( (int) $variation->impressions ) ); ?></span>
					<span class="cro-stat-label"><?php esc_html_e( 'Impressions', 'meyvora-convert' ); ?></span>
				</div>
				<div class="cro-stat">
					<span class="cro-stat-value"><?php echo esc_html( number_format_i18n( (int) $variation->conversions ) ); ?></span>
					<span class="cro-stat-label"><?php esc_html_e( 'Conversions', 'meyvora-convert' ); ?></span>
				</div>
				<div class="cro-stat cro-stat--highlight">
					<span class="cro-stat-value">
						<?php
						$impressions = (int) $variation->impressions;
						$conversions = (int) $variation->conversions;
						$rate        = $impressions > 0 ? ( $conversions / $impressions ) * 100 : 0;
						echo esc_html( number_format( $rate, 2 ) . '%' );
						?>
					</span>
					<span class="cro-stat-label"><?php esc_html_e( 'Conv. Rate', 'meyvora-convert' ); ?></span>
				</div>
				<div class="cro-stat">
					<span class="cro-stat-value">
						<?php
						$rev = (float) ( $variation->revenue ?? 0 );
						if ( function_exists( 'wc_price' ) ) {
							echo wp_kses_post( wc_price( $rev ) );
						} else {
							echo esc_html( number_format( $rev, 2 ) );
						}
						?>
					</span>
					<span class="cro-stat-label"><?php esc_html_e( 'Revenue', 'meyvora-convert' ); ?></span>
				</div>
			</div>

			<?php
			$var_enough_data = (int) $variation->impressions >= (int) $test->min_sample_size;
			?>
			<?php if ( $is_control ) : ?>
			<div class="cro-variation-comparison">
				<?php if ( ! $var_enough_data ) : ?>
					<div class="cro-variation-not-enough-data">
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
					<span class="cro-baseline"><?php esc_html_e( 'Baseline for comparison', 'meyvora-convert' ); ?></span>
				<?php endif; ?>
			</div>
			<?php elseif ( ! $is_control && $var_stats && is_array( $var_stats ) ) : ?>
			<div class="cro-variation-comparison">
				<?php if ( ! $var_enough_data ) : ?>
					<div class="cro-variation-not-enough-data">
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
					<div class="cro-significance-row">
						<span class="cro-significance-label"><?php esc_html_e( 'Significance', 'meyvora-convert' ); ?>:</span>
						<span class="cro-significance-value <?php echo ! empty( $var_stats['is_significant'] ) ? 'significant' : 'not-significant'; ?>">
							<?php echo ! empty( $var_stats['is_significant'] ) ? esc_html__( 'Significant', 'meyvora-convert' ) : esc_html__( 'Not significant', 'meyvora-convert' ); ?>
						</span>
					</div>
					<div class="cro-improvement <?php echo ( $var_stats['improvement'] ?? 0 ) >= 0 ? 'positive' : 'negative'; ?>">
						<?php echo esc_html( $var_stats['improvement_formatted'] ?? '0%' ); ?>
						<?php esc_html_e( 'vs Control', 'meyvora-convert' ); ?>
					</div>
					<div class="cro-confidence">
						<span class="cro-confidence-label"><?php esc_html_e( 'Confidence', 'meyvora-convert' ); ?>:</span>
						<div class="cro-confidence-bar">
							<div class="cro-confidence-fill cro-bar-fill <?php echo ! empty( $var_stats['is_significant'] ) ? 'significant' : ''; ?>"
								 style="--cro-bar-width: <?php echo esc_attr( min( 100, (float) ( $var_stats['confidence'] ?? 0 ) ) ); ?>%"></div>
						</div>
						<span class="cro-confidence-value"><?php echo esc_html( number_format( (float) ( $var_stats['confidence'] ?? 0 ), 1 ) ); ?>%</span>
					</div>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>
	</div>

	<?php if ( $test->status === 'draft' ) : ?>
	<div class="cro-add-variation">
		<h2><?php esc_html_e( 'Add New Variation', 'meyvora-convert' ); ?></h2>

		<form method="post" class="cro-variation-form">
			<?php wp_nonce_field( 'cro_add_variation' ); ?>

			<div class="cro-form-row">
				<div class="cro-form-col">
					<label for="variation_name"><?php esc_html_e( 'Variation Name', 'meyvora-convert' ); ?></label>
					<input type="text"
						   id="variation_name"
						   name="variation_name"
						   required
						   placeholder="<?php esc_attr_e( 'e.g., Version B - New Headline', 'meyvora-convert' ); ?>" />
				</div>
				<div class="cro-form-col cro-form-col--small">
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

			<div class="cro-form-row">
				<div class="cro-form-col">
					<label for="variation_headline"><?php esc_html_e( 'Headline', 'meyvora-convert' ); ?></label>
					<input type="text"
						   id="variation_headline"
						   name="variation_headline"
						   placeholder="<?php echo esc_attr( $original_content['headline'] ?? '' ); ?>" />
					<span class="cro-original">
						<?php esc_html_e( 'Original:', 'meyvora-convert' ); ?>
						<?php echo esc_html( $original_content['headline'] ?? 'N/A' ); ?>
					</span>
				</div>
			</div>

			<div class="cro-form-row">
				<div class="cro-form-col">
					<label for="variation_subheadline"><?php esc_html_e( 'Subheadline', 'meyvora-convert' ); ?></label>
					<input type="text"
						   id="variation_subheadline"
						   name="variation_subheadline"
						   placeholder="<?php echo esc_attr( $original_content['subheadline'] ?? '' ); ?>" />
				</div>
			</div>

			<div class="cro-form-row">
				<div class="cro-form-col">
					<label for="variation_cta"><?php esc_html_e( 'CTA Button Text', 'meyvora-convert' ); ?></label>
					<input type="text"
						   id="variation_cta"
						   name="variation_cta"
						   placeholder="<?php echo esc_attr( $original_content['cta_text'] ?? '' ); ?>" />
				</div>
			</div>

			<p class="submit">
				<button type="submit" name="cro_add_variation" class="button button-primary">
					<?php esc_html_e( 'Add Variation', 'meyvora-convert' ); ?>
				</button>
			</p>
		</form>
	</div>
	<?php endif; ?>

	<div class="cro-test-settings">
		<h2><?php esc_html_e( 'Test Settings', 'meyvora-convert' ); ?></h2>
		<div class="cro-fields-grid cro-fields-grid--1col">
			<div class="cro-field cro-col-12">
				<span class="cro-field__label"><?php esc_html_e( 'Original Campaign', 'meyvora-convert' ); ?></span>
				<div class="cro-field__control">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=cro-campaign-edit&campaign_id=' . (int) $test->original_campaign_id ) ); ?>">
						<?php echo esc_html( $original_campaign->name ?? 'N/A' ); ?>
					</a>
				</div>
			</div>
			<div class="cro-field cro-col-12">
				<span class="cro-field__label"><?php esc_html_e( 'Primary Metric', 'meyvora-convert' ); ?></span>
				<div class="cro-field__control"><?php echo esc_html( ucwords( str_replace( '_', ' ', $test->metric ) ) ); ?></div>
			</div>
			<div class="cro-field cro-col-12">
				<span class="cro-field__label"><?php esc_html_e( 'Minimum Sample Size', 'meyvora-convert' ); ?></span>
				<div class="cro-field__control"><?php echo esc_html( number_format( (int) $test->min_sample_size ) ); ?> <?php esc_html_e( 'per variation', 'meyvora-convert' ); ?></div>
			</div>
			<div class="cro-field cro-col-12">
				<span class="cro-field__label"><?php esc_html_e( 'Confidence Level', 'meyvora-convert' ); ?></span>
				<div class="cro-field__control"><?php echo esc_html( (int) $test->confidence_level ); ?>%</div>
			</div>
			<div class="cro-field cro-col-12">
				<span class="cro-field__label"><?php esc_html_e( 'Auto-apply Winner', 'meyvora-convert' ); ?></span>
				<div class="cro-field__control"><?php echo $test->auto_apply_winner ? CRO_Icons::svg( 'check', array( 'class' => 'cro-ico' ) ) . ' ' . esc_html__( 'Yes', 'meyvora-convert' ) : esc_html__( 'No', 'meyvora-convert' ); ?></div>
			</div>
			<?php if ( ! empty( $test->started_at ) ) : ?>
			<div class="cro-field cro-col-12">
				<span class="cro-field__label"><?php esc_html_e( 'Started', 'meyvora-convert' ); ?></span>
				<div class="cro-field__control"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $test->started_at ) ) ); ?></div>
			</div>
			<?php endif; ?>
			<?php if ( ! empty( $test->completed_at ) ) : ?>
			<div class="cro-field cro-col-12">
				<span class="cro-field__label"><?php esc_html_e( 'Completed', 'meyvora-convert' ); ?></span>
				<div class="cro-field__control"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $test->completed_at ) ) ); ?></div>
			</div>
			<?php endif; ?>
		</div>
	</div>

<style>
.cro-ab-warning-banner {
	display: flex;
	align-items: flex-start;
	gap: 12px;
	background: #fff8e5;
	border: 1px solid #d4a012;
	border-left-width: 4px;
	padding: 14px 20px;
	margin: 20px 0;
	border-radius: 4px;
}

.cro-ab-warning-icon {
	font-size: 20px;
	line-height: 1.2;
}

.cro-ab-warning-content {
	flex: 1;
}

.cro-ab-warning-content strong {
	display: block;
	margin-bottom: 4px;
	color: #333;
}

.cro-ab-warning-content p {
	margin: 0;
	font-size: 13px;
	color: #50575e;
}

.cro-test-status-box {
	background: #f5f5f5;
	border-left: 4px solid #333;
	padding: 15px 20px;
	margin: 20px 0;
}

.cro-test-status-box.has-winner {
	background: #e8e8e8;
	border-color: #333;
}

.cro-test-status-box--insufficient {
	border-left-color: #d4a012;
}

.cro-test-meta {
	margin: 10px 0 0;
	font-size: 12px;
	color: #666;
}

.cro-variation-not-enough-data {
	font-size: 13px;
	color: #646970;
	font-style: italic;
	padding: 8px 0;
}

.cro-significance-row {
	margin-bottom: 8px;
}

.cro-significance-label {
	font-size: 12px;
	color: #666;
	margin-right: 6px;
}

.cro-significance-value.significant {
	color: #1e4620;
	font-weight: 600;
}

.cro-significance-value.not-significant {
	color: #646970;
}

.cro-confidence-label {
	display: block;
	font-size: 12px;
	color: #666;
	margin-bottom: 4px;
}

.cro-confidence-value {
	font-size: 13px;
	margin-top: 4px;
	display: block;
}

.cro-test-actions {
	margin: 20px 0;
	display: flex;
	gap: 10px;
	align-items: center;
}

.cro-variations-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
	gap: 20px;
	margin: 20px 0;
}

.cro-variation-card {
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 8px;
	padding: 20px;
}

.cro-variation-card.is-control {
	border-color: #333;
}

.cro-variation-card.is-winner {
	border-color: #333;
	box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.15);
}

.cro-variation-header {
	margin-bottom: 15px;
}

.cro-variation-header h3 {
	margin: 5px 0;
}

.cro-badge {
	display: inline-block;
	padding: 3px 8px;
	font-size: 11px;
	border-radius: 3px;
	background: #f0f0f1;
}

.cro-badge--control {
	background: #e8e8e8;
	color: #333;
}

.cro-badge--winner {
	background: #e0e0e0;
	color: #333;
}

.cro-traffic-weight {
	font-size: 12px;
	color: #666;
}

.cro-variation-stats {
	display: flex;
	gap: 20px;
	margin-bottom: 15px;
}

.cro-stat {
	text-align: center;
}

.cro-stat-value {
	display: block;
	font-size: 24px;
	font-weight: 600;
}

.cro-stat--highlight .cro-stat-value {
	color: #333;
}

.cro-stat-label {
	font-size: 11px;
	color: #666;
}

.cro-variation-comparison {
	padding-top: 15px;
	border-top: 1px solid #eee;
}

.cro-improvement {
	font-weight: 600;
	margin-bottom: 10px;
}

.cro-improvement.positive {
	color: #333;
}

.cro-improvement.negative {
	color: #555;
}

.cro-confidence-bar {
	height: 8px;
	background: #eee;
	border-radius: 4px;
	overflow: hidden;
	margin-bottom: 5px;
}

.cro-confidence-fill {
	height: 100%;
	background: #999;
	transition: width 0.3s;
}

.cro-confidence-fill.significant {
	background: #333;
}

.cro-baseline {
	color: #666;
	font-style: italic;
}

.cro-add-variation {
	background: #fff;
	padding: 25px;
	border-radius: 8px;
	margin-top: 30px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.cro-form-row {
	display: flex;
	gap: 20px;
	margin-bottom: 15px;
}

.cro-form-col {
	flex: 1;
}

.cro-form-col--small {
	flex: 0 0 120px;
}

.cro-form-col label {
	display: block;
	margin-bottom: 5px;
	font-weight: 500;
}

.cro-form-col input {
	width: 100%;
	padding: 8px 12px;
}

.cro-original {
	display: block;
	font-size: 12px;
	color: #666;
	margin-top: 5px;
}

.cro-test-settings {
	background: #fff;
	padding: 25px;
	border-radius: 8px;
	margin-top: 30px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.cro-test-settings h2 {
	margin-top: 0;
}

.cro-back-link {
	text-decoration: none;
	color: #666;
	font-size: 14px;
	font-weight: normal;
}

/* Status badges use global .cro-status styles (pill + semantic colors) */
</style>
