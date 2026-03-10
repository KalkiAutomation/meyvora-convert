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
}

WP_CLI::add_command( 'cro', 'CRO_CLI_Command' );
