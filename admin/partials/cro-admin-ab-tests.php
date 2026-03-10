<?php
$ab_model = new CRO_AB_Test();
$tests = $ab_model->get_all();
$tests = is_array( $tests ) ? $tests : array();

// Admin notices for action errors and success
$ab_error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
$ab_msg   = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
if ( $ab_error === 'invalid_nonce' ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid security check. Please try again.', 'meyvora-convert' ) . '</p></div>';
} elseif ( $ab_error === 'unauthorized' ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to perform that action.', 'meyvora-convert' ) . '</p></div>';
} elseif ( $ab_msg ) {
	$messages = array(
		'started'        => __( 'A/B test started.', 'meyvora-convert' ),
		'paused'         => __( 'A/B test paused.', 'meyvora-convert' ),
		'completed'      => __( 'A/B test completed.', 'meyvora-convert' ),
		'winner_applied' => __( 'Winner applied and test completed.', 'meyvora-convert' ),
		'deleted'        => __( 'A/B test deleted.', 'meyvora-convert' ),
	);
	if ( isset( $messages[ $ab_msg ] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $ab_msg ] ) . '</p></div>';
	}
}
?>

    <?php if (empty($tests)) : ?>
    <div class="cro-empty-state">
        <h2><?php esc_html_e('No A/B Tests Yet', 'meyvora-convert'); ?></h2>
        <p><?php esc_html_e('Test different versions of your campaigns to find what converts best.', 'meyvora-convert'); ?></p>
        <a href="<?php echo admin_url('admin.php?page=cro-ab-test-new'); ?>" class="button button-primary">
            <?php esc_html_e('Create Your First Test', 'meyvora-convert'); ?>
        </a>
    </div>
    <?php else : ?>
    
    <div class="cro-table-wrap">
    <table class="cro-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Test Name', 'meyvora-convert' ); ?></th>
                <th><?php esc_html_e( 'Status', 'meyvora-convert' ); ?></th>
                <th><?php esc_html_e( 'Variations', 'meyvora-convert' ); ?></th>
                <th><?php esc_html_e( 'Impressions', 'meyvora-convert' ); ?></th>
                <th><?php esc_html_e( 'Revenue', 'meyvora-convert' ); ?></th>
                <th><?php esc_html_e( 'Result', 'meyvora-convert' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'meyvora-convert' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $tests as $test ) :
                $variations        = $ab_model->get_variations( $test->id );
                $total_impressions = array_sum( array_column( $variations, 'impressions' ) );
                $total_revenue     = array_sum( array_column( $variations, 'revenue' ) );
                $test->variations  = $variations;
                $stats             = ( $test->status === 'running' || $test->status === 'paused' || $test->status === 'completed' ) && class_exists( 'CRO_AB_Statistics' )
                    ? CRO_AB_Statistics::calculate( $test )
                    : null;
                $result_label      = class_exists( 'CRO_AB_Statistics' ) ? CRO_AB_Statistics::get_data_state_label( $test, $stats ) : '—';
                $enough_data       = class_exists( 'CRO_AB_Statistics' ) && CRO_AB_Statistics::has_reached_sample_size( $test );
            ?>
            <tr>
                <td>
                    <strong>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=cro-ab-test-view&id=' . $test->id ) ); ?>">
                            <?php echo esc_html( $test->name ); ?>
                        </a>
                    </strong>
                </td>
                <td>
                    <span class="cro-status cro-status--<?php echo esc_attr( $test->status ); ?>">
                        <?php echo esc_html( ucfirst( $test->status ) ); ?>
                    </span>
                </td>
                <td><?php echo absint( count( $variations ) ); ?></td>
                <td><?php echo esc_html( number_format_i18n( $total_impressions ) ); ?></td>
                <td>
                    <?php
                    if ( function_exists( 'wc_price' ) ) {
                        echo wp_kses_post( wc_price( $total_revenue ) );
                    } else {
                        echo esc_html( number_format( (float) $total_revenue, 2 ) );
                    }
                    ?>
                </td>
                <td>
                    <?php if ( ! $enough_data && ( $test->status === 'running' || $test->status === 'paused' ) ) : ?>
                        <span class="cro-result-insufficient"><?php esc_html_e( 'Not enough data', 'meyvora-convert' ); ?></span>
                    <?php elseif ( $stats && ! empty( $stats['has_winner'] ) && ! empty( $stats['winner']['variation_name'] ) ) : ?>
                        <span class="cro-winner"><?php echo CRO_Icons::svg( 'trophy', array( 'class' => 'cro-ico' ) ); ?> <?php echo esc_html( $stats['winner']['variation_name'] ); ?></span>
                    <?php else : ?>
                        <?php echo esc_html( $result_label ); ?>
                    <?php endif; ?>
                </td>
                <td class="cro-table-actions">
                    <?php if ( $test->status === 'draft' ) : ?>
                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cro-ab-tests&action=start&id=' . $test->id ), 'start_ab_test' ) ); ?>" class="button button-small button-primary"><?php esc_html_e( 'Start', 'meyvora-convert' ); ?></a>
                    <?php elseif ( $test->status === 'running' ) : ?>
                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cro-ab-tests&action=pause&id=' . $test->id ), 'pause_ab_test' ) ); ?>"><?php esc_html_e( 'Pause', 'meyvora-convert' ); ?></a>
                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cro-ab-tests&action=complete&id=' . $test->id ), 'complete_ab_test' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'End this test?', 'meyvora-convert' ) ); ?>');"><?php esc_html_e( 'Complete', 'meyvora-convert' ); ?></a>
                        <?php if ( $enough_data && $stats && ! empty( $stats['has_winner'] ) && ! empty( $stats['winner']['variation_id'] ) ) : ?>
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cro-ab-tests&action=apply_winner&id=' . $test->id . '&winner=' . (int) $stats['winner']['variation_id'] ), 'apply_winner' ) ); ?>" class="button button-small button-primary" onclick="return confirm('<?php echo esc_js( __( 'Apply winner and end test?', 'meyvora-convert' ) ); ?>');"><?php esc_html_e( 'Apply Winner', 'meyvora-convert' ); ?></a>
                        <?php endif; ?>
                    <?php elseif ( $test->status === 'paused' ) : ?>
                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cro-ab-tests&action=start&id=' . $test->id ), 'start_ab_test' ) ); ?>" class="button button-small button-primary"><?php esc_html_e( 'Resume', 'meyvora-convert' ); ?></a>
                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cro-ab-tests&action=complete&id=' . $test->id ), 'complete_ab_test' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'End this test?', 'meyvora-convert' ) ); ?>');"><?php esc_html_e( 'Complete', 'meyvora-convert' ); ?></a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=cro-ab-test-view&id=' . $test->id ) ); ?>"><?php esc_html_e( 'View', 'meyvora-convert' ); ?></a>
                    <?php if ( current_user_can( 'manage_meyvora_convert' ) ) : ?>
                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cro-ab-tests&action=delete&id=' . $test->id ), 'delete_ab_test' ) ); ?>"
                           class="cro-action-delete"
                           onclick="return confirm('<?php echo esc_js( __( 'Delete this A/B test? This cannot be undone.', 'meyvora-convert' ) ); ?>');"><?php esc_html_e( 'Delete', 'meyvora-convert' ); ?></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php endif; ?>
