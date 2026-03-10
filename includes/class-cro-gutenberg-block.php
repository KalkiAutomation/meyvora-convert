<?php
/**
 * Gutenberg block: Meyvora Convert / Campaign
 *
 * Dynamic block that renders on the server using the same shortcode renderer.
 * Saves only campaignId; works on Classic pages via shortcode.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CRO_Gutenberg_Block class.
 */
class CRO_Gutenberg_Block {

	/**
	 * Block name (namespace/name).
	 *
	 * @var string
	 */
	const BLOCK_NAME = 'meyvora-convert/campaign';

	/**
	 * Initialize the block registration and editor assets.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );
	}

	/**
	 * Register the Campaign block (dynamic, server-rendered).
	 */
	public static function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$block_json = defined( 'CRO_PLUGIN_DIR' ) ? CRO_PLUGIN_DIR . 'blocks/campaign/block.json' : '';
		if ( ! $block_json || ! file_exists( $block_json ) ) {
			return;
		}

		register_block_type(
			$block_json,
			array(
				'render_callback' => array( __CLASS__, 'render_campaign_block' ),
				'editor_script'   => 'meyvora-convert-campaign-block',
			)
		);
	}

	/**
	 * Render the Campaign block on the frontend (and in editor ServerSideRender).
	 * Uses the same output as the [cro_campaign] shortcode.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Block content (empty for dynamic).
	 * @param WP_Block $block      Block instance.
	 * @return string
	 */
	public static function render_campaign_block( $attributes, $content, $block ) {
		$id = isset( $attributes['campaignId'] ) ? absint( $attributes['campaignId'] ) : 0;
		if ( $id <= 0 ) {
			return '';
		}
		return do_shortcode( '[cro_campaign id="' . $id . '"]' );
	}

	/**
	 * Enqueue editor-only script and pass campaigns for the dropdown.
	 */
	public static function enqueue_editor_assets() {
		$script_asset_path = CRO_PLUGIN_DIR . 'blocks/campaign/index.asset.php';
		$script_path      = CRO_PLUGIN_DIR . 'blocks/campaign/index.js';
		$script_url       = CRO_PLUGIN_URL . 'blocks/campaign/index.js';

		if ( ! file_exists( $script_path ) ) {
			return;
		}

		$dependencies = array(
			'wp-blocks',
			'wp-element',
			'wp-block-editor',
			'wp-components',
			'wp-server-side-render',
		);

		$version = defined( 'CRO_VERSION' ) ? CRO_VERSION : '1.0.0';
		if ( file_exists( $script_asset_path ) ) {
			$asset = include $script_asset_path;
			if ( ! empty( $asset['dependencies'] ) ) {
				$dependencies = $asset['dependencies'];
			}
			if ( ! empty( $asset['version'] ) ) {
				$version = $asset['version'];
			}
		}

		wp_register_script(
			'meyvora-convert-campaign-block',
			$script_url,
			$dependencies,
			$version
		);

		$campaigns = self::get_campaigns_for_block();
		wp_localize_script(
			'meyvora-convert-campaign-block',
			'croCampaignBlock',
			array(
				'campaigns' => $campaigns,
			)
		);

		wp_enqueue_script( 'meyvora-convert-campaign-block' );
	}

	/**
	 * Get list of campaigns for the block dropdown (id, name, status).
	 *
	 * @return array
	 */
	private static function get_campaigns_for_block() {
		if ( ! class_exists( 'CRO_Campaign' ) ) {
			return array();
		}
		$rows = CRO_Campaign::get_all( array( 'limit' => 200 ) );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$list = array();
		foreach ( $rows as $row ) {
			$list[] = array(
				'id'     => isset( $row['id'] ) ? (int) $row['id'] : 0,
				'name'   => isset( $row['name'] ) ? (string) $row['name'] : '',
				'status' => isset( $row['status'] ) ? (string) $row['status'] : 'draft',
			);
		}
		return $list;
	}
}
