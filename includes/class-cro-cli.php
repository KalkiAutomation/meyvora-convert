<?php
/**
 * WP-CLI commands for Meyvora Convert.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	exit;
}

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

/**
 * Meyvora Convert WP-CLI commands.
 */
class CRO_CLI_Command {

	/**
	 * Verify install package: required tables, blocks build assets, asset loading not site-wide.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format: table, csv, json, yaml. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cro verify-package
	 *     wp cro verify-package --format=json
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Associative args.
	 */
	public function verify_package( $args, $assoc_args ) {
		if ( ! class_exists( 'CRO_System_Status' ) ) {
			WP_CLI::error( 'CRO_System_Status not loaded.' );
		}
		$results = CRO_System_Status::run_verify_package();
		$all_pass = true;
		$rows = array();
		foreach ( $results as $item ) {
			if ( ! empty( $item['pass'] ) ) {
				$status = 'pass';
			} else {
				$status = 'fail';
				$all_pass = false;
			}
			$rows[] = array(
				'check'   => isset( $item['label'] ) ? $item['label'] : '',
				'status'  => $status,
				'message' => isset( $item['message'] ) ? $item['message'] : '',
			);
		}
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		WP_CLI\Utils\format_items( $format, $rows, array( 'check', 'status', 'message' ) );
		if ( ! $all_pass ) {
			WP_CLI::error( 'One or more checks failed.', array( 'exit' => 1 ) );
		}
		WP_CLI::success( 'All checks passed.' );
	}

	/**
	 * Show abandoned cart reminder segment, threshold, and planned email times.
	 *
	 * ## OPTIONS
	 *
	 * [--cart-id=<id>]
	 * : Abandoned cart row id (required).
	 *
	 * ## EXAMPLES
	 *
	 *     wp cro test-abandoned-segment --cart-id=12
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Associative args.
	 */
	public function test_abandoned_segment( $args, $assoc_args ) {
		$cart_id = isset( $assoc_args['cart-id'] ) ? absint( $assoc_args['cart-id'] ) : 0;
		if ( $cart_id <= 0 ) {
			WP_CLI::error( 'Pass --cart-id=<id> (abandoned cart row id).' );
		}
		if ( ! class_exists( 'CRO_Abandoned_Cart_Tracker' ) || ! class_exists( 'CRO_Abandoned_Cart_Reminder' ) ) {
			WP_CLI::error( 'Abandoned cart classes not loaded.' );
		}
		$row = CRO_Abandoned_Cart_Tracker::get_row_by_id( $cart_id );
		if ( ! $row ) {
			WP_CLI::error( sprintf( 'No abandoned cart row with id %d.', $cart_id ) );
		}
		$debug = CRO_Abandoned_Cart_Reminder::get_schedule_debug( $row );
		WP_CLI::log( 'Cart ID: ' . $cart_id );
		WP_CLI::log( 'Segment: ' . $debug['segment'] );
		WP_CLI::log( 'Threshold: ' . $debug['threshold'] );
		WP_CLI::log( 'Delay hours — email 1: ' . $debug['delays_hours'][1] . ', email 2: ' . $debug['delays_hours'][2] . ', email 3: ' . $debug['delays_hours'][3] );
		WP_CLI::log( 'Planned send (site local) — email 1: ' . $debug['planned_local'][1] );
		WP_CLI::log( 'Planned send (site local) — email 2: ' . $debug['planned_local'][2] );
		WP_CLI::log( 'Planned send (site local) — email 3: ' . $debug['planned_local'][3] );
		WP_CLI::success( 'Done.' );
	}
}

WP_CLI::add_command( 'cro', 'CRO_CLI_Command' );
