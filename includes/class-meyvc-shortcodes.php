<?php
/**
 * Shortcodes for Meyvora Convert
 *
 * [meyvc_campaign id="123"] – Renders a campaign by ID anywhere.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MEYVC_Shortcodes class.
 */
class MEYVC_Shortcodes {

	/**
	 * Whether the shortcode was used on the current request (for conditional asset loading).
	 *
	 * @var bool
	 */
	private static $shortcode_used = false;

	/**
	 * Initialize shortcodes and asset hooks.
	 */
	public static function init() {
		add_shortcode( 'meyvc_campaign', array( __CLASS__, 'render_campaign' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_campaign_assets' ), 20 );
	}

	/**
	 * Render [meyvc_campaign id="123"] shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Safe HTML or empty string.
	 */
	public static function render_campaign( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			'meyvc_campaign'
		);

		$id = absint( $atts['id'] );
		if ( $id <= 0 ) {
			return self::maybe_admin_notice( __( 'Invalid campaign ID.', 'meyvora-convert' ), true );
		}

		if ( ! class_exists( 'MEYVC_Campaign' ) || ! class_exists( 'MEYVC_Templates' ) ) {
			return self::maybe_admin_notice( __( 'Campaign system not available.', 'meyvora-convert' ), true );
		}

		$row = MEYVC_Campaign::get( $id );
		if ( ! $row || ! is_array( $row ) ) {
			return self::maybe_admin_notice(
				/* translators: %d: campaign ID */
				sprintf( __( 'Campaign #%d not found.', 'meyvora-convert' ), $id ),
				true
			);
		}

		// Only show active campaigns to non-admins; admins can see drafts with a notice.
		$status = isset( $row['status'] ) ? (string) $row['status'] : 'draft';
		$is_admin = current_user_can( 'manage_meyvora_convert' );
		if ( $status !== 'active' && ! $is_admin ) {
			return '';
		}

		self::$shortcode_used = true;

		// Ensure placeholders and templates are available (they may already be loaded by loader).
		if ( ! class_exists( 'MEYVC_Placeholders' ) ) {
			require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-placeholders.php';
		}
		if ( ! class_exists( 'MEYVC_Templates' ) ) {
			require_once MEYVC_PLUGIN_DIR . 'includes/class-meyvc-templates.php';
		}

		$template_key = isset( $row['template_type'] ) ? sanitize_key( (string) $row['template_type'] ) : 'centered';
		$template_key = str_replace( array( ' ', '_' ), '-', $template_key );
		if ( ! MEYVC_Templates::get( $template_key ) ) {
			$template_key = 'centered';
		}

		// Build campaign object for templates (content/styling already unserialized by MEYVC_Campaign::get).
		$campaign_arr = array(
			'id'                    => $row['id'],
			'content'               => isset( $row['content'] ) && is_array( $row['content'] ) ? $row['content'] : array(),
			'styling'               => isset( $row['styling'] ) && is_array( $row['styling'] ) ? $row['styling'] : array(),
			'brand_styles_override' => null,
			'display_rules'         => isset( $row['display_rules'] ) && is_array( $row['display_rules'] ) ? $row['display_rules'] : array(),
		);
		if ( ! empty( $row['display_rules']['brand_styles_override'] ) && is_array( $row['display_rules']['brand_styles_override'] ) && ! empty( $row['display_rules']['brand_styles_override']['use'] ) ) {
			$campaign_arr['brand_styles_override'] = $row['display_rules']['brand_styles_override'];
		}
		$campaign = (object) $campaign_arr;

		$html = MEYVC_Templates::render( $template_key, $campaign );
		if ( $html === '' ) {
			return self::maybe_admin_notice( __( 'Campaign could not be rendered.', 'meyvora-convert' ), $is_admin );
		}

		// Wrap in container so we can show it inline (visible) and scope shortcode-only CSS.
		$wrapper = '<div class="meyvc-campaign-shortcode" data-campaign-id="' . esc_attr( (string) $id ) . '">';
		$wrapper .= $html;
		$wrapper .= '</div>';

		if ( $status !== 'active' && $is_admin ) {
			$wrapper = '<p class="meyvc-shortcode-notice" style="margin-bottom:0.5em;font-size:12px;color:#856404;background:#fff3cd;padding:6px 10px;border-radius:4px;">'
				. esc_html__( 'Campaign is not active. Only admins see this.', 'meyvora-convert' )
				. '</p>' . $wrapper;
		}

		return $wrapper;
	}

	/**
	 * Return empty string for non-admins, or a small admin-only notice.
	 *
	 * @param string $message Notice text.
	 * @param bool   $admin_only If true, show only to users with manage_meyvora_convert.
	 * @return string
	 */
	private static function maybe_admin_notice( $message, $admin_only = true ) {
		if ( $admin_only && ! current_user_can( 'manage_meyvora_convert' ) ) {
			return '';
		}
		return '<p class="meyvc-shortcode-notice" style="font-size:12px;color:#856404;background:#fff3cd;padding:6px 10px;border-radius:4px;">' . esc_html( $message ) . '</p>';
	}

	/**
	 * Enqueue campaign CSS/JS only when the shortcode is present on the page.
	 */
	public static function maybe_enqueue_campaign_assets() {
		if ( is_admin() ) {
			return;
		}
		if ( ! self::page_has_shortcode() ) {
			return;
		}

		$version = defined( 'MEYVC_VERSION' ) ? MEYVC_VERSION : '1.0.0';

		wp_enqueue_style(
			'meyvc-popup',
			MEYVC_PLUGIN_URL . 'public/css/meyvc-popup' . meyvc_asset_min_suffix() . '.css',
			array(),
			$version
		);

		wp_enqueue_style(
			'meyvc-animations',
			MEYVC_PLUGIN_URL . 'public/css/meyvc-animations' . meyvc_asset_min_suffix() . '.css',
			array( 'meyvc-popup' ),
			$version
		);

		// Same stack as MEYVC_Public::enqueue_scripts() for campaigns (signals + controller + /decide), not meyvc-popup alone.
		if ( class_exists( 'MEYVC_Public' ) ) {
			MEYVC_Public::enqueue_campaign_scripts_for_shortcode();
		}

		// Inline CSS so shortcode-rendered popup is visible (no overlay; display inline).
		$inline = '
			.meyvc-campaign-shortcode .meyvc-popup {
				position: relative !important;
				opacity: 1 !important;
				visibility: visible !important;
				transform: none !important;
				inset: auto !important;
				top: auto !important;
				left: auto !important;
				right: auto !important;
				bottom: auto !important;
			}
			.meyvc-campaign-shortcode .meyvc-popup--centered,
			.meyvc-campaign-shortcode .meyvc-popup--minimal,
			.meyvc-campaign-shortcode .meyvc-popup--image-left,
			.meyvc-campaign-shortcode .meyvc-popup--image-right,
			.meyvc-campaign-shortcode .meyvc-popup--fullscreen {
				transform: none !important;
			}
		';
		wp_add_inline_style( 'meyvc-popup', $inline );
	}

	/**
	 * Whether this request will render [meyvc_campaign] (already rendered or present in queried post content).
	 *
	 * @return bool
	 */
	public static function current_page_has_campaign_shortcode() {
		if ( self::$shortcode_used ) {
			return true;
		}
		$post = get_post( get_queried_object_id() );
		if ( ! $post || ! isset( $post->post_content ) ) {
			return false;
		}
		return has_shortcode( $post->post_content, 'meyvc_campaign' );
	}

	/**
	 * Check if the current page content contains [meyvc_campaign].
	 *
	 * @return bool
	 */
	private static function page_has_shortcode() {
		return self::current_page_has_campaign_shortcode();
	}
}
