<?php
/**
 * Campaign display logic
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Campaign display class.
 */
class CRO_Campaign_Display {

	/** Preview token expiration in seconds (30 minutes). */
	const PREVIEW_EXPIRY_SECONDS = 1800;

	/**
	 * Initialize the display hooks.
	 */
	public function __construct() {
		add_action( 'wp_footer', array( $this, 'render_campaigns' ) );
	}

	/**
	 * Generate a signed preview token for a preview_id and expiry timestamp.
	 * Only call from admin/API when storing preview data.
	 *
	 * @param string $preview_id      Preview ID (e.g. cro_xxx).
	 * @param int    $expiry_timestamp Unix timestamp when the token expires.
	 * @return string Token string (HMAC).
	 */
	public static function generate_preview_token( $preview_id, $expiry_timestamp ) {
		$secret = defined( 'AUTH_KEY' ) ? AUTH_KEY : ( defined( 'LOGGED_IN_KEY' ) ? LOGGED_IN_KEY : wp_salt( 'auth' ) );
		$payload = $preview_id . '|' . (int) $expiry_timestamp;
		return hash_hmac( 'sha256', $payload, $secret );
	}

	/**
	 * Validate a preview token. Returns true only if token matches and not expired.
	 *
	 * @param string $preview_id       Preview ID from request.
	 * @param string $token            Token from request.
	 * @param int    $expiry_timestamp Expiry timestamp from request.
	 * @return bool
	 */
	public static function validate_preview_token( $preview_id, $token, $expiry_timestamp ) {
		if ( empty( $preview_id ) || empty( $token ) || empty( $expiry_timestamp ) ) {
			return false;
		}
		$expiry = (int) $expiry_timestamp;
		if ( $expiry <= 0 || time() > $expiry ) {
			return false;
		}
		$expected = self::generate_preview_token( $preview_id, $expiry );
		return is_string( $token ) && hash_equals( $expected, $token );
	}

	/**
	 * Render active campaigns.
	 * Popups are only output for admin preview with valid signed token. On the frontend, popups are shown only when
	 * the REST /decide endpoint returns show: true with campaign data (controller creates them via JS).
	 */
	public function render_campaigns() {
		// Preview: show campaign only when valid signed token + preview_id (data from transient; no GET injection).
		if ( $this->is_preview_request() ) {
			$preview_campaign = $this->get_preview_campaign_from_request();
			if ( $preview_campaign ) {
				$this->render_campaign( $preview_campaign );
			}
			return;
		}

		// Frontend: do not output popup HTML here. The controller requests /cro/v1/decide and
		// only creates/shows a popup when the API returns show: true with campaign data.
	}

	/**
	 * Check if current request is a valid campaign preview (signed token + expiry, no capability required for link holder).
	 *
	 * @return bool
	 */
	private function is_preview_request() {
		if ( ! isset( $_GET['cro_preview'] ) || (string) $_GET['cro_preview'] !== '1' ) {
			return false;
		}
		$preview_id = isset( $_GET['preview_id'] ) ? sanitize_text_field( wp_unslash( $_GET['preview_id'] ) ) : '';
		$token      = isset( $_GET['cro_token'] ) ? sanitize_text_field( wp_unslash( $_GET['cro_token'] ) ) : '';
		$expiry     = isset( $_GET['cro_expiry'] ) ? (int) $_GET['cro_expiry'] : 0;
		if ( ! $preview_id || ! $token || ! $expiry ) {
			return false;
		}
		if ( ! preg_match( '/^cro_[a-zA-Z0-9]+$/', $preview_id ) ) {
			return false;
		}
		return self::validate_preview_token( $preview_id, $token, $expiry );
	}

	/**
	 * Build campaign array from preview_id only (data from transient). Never use GET campaign_data to prevent injection.
	 *
	 * @return array|null Campaign array or null if invalid.
	 */
	private function get_preview_campaign_from_request() {
		$preview_id = isset( $_GET['preview_id'] ) ? sanitize_text_field( wp_unslash( $_GET['preview_id'] ) ) : '';
		if ( ! preg_match( '/^cro_[a-zA-Z0-9]+$/', $preview_id ) ) {
			return null;
		}
		$data = get_transient( 'cro_preview_' . $preview_id );
		if ( ! is_array( $data ) ) {
			return null;
		}

		$template_raw = isset( $data['template'] ) ? $data['template'] : ( isset( $data['template_type'] ) ? $data['template_type'] : 'centered' );
		$template    = sanitize_key( is_string( $template_raw ) ? $template_raw : 'centered' );
		$template    = str_replace( array( ' ', '_' ), '-', $template );
		$content     = isset( $data['content'] ) && is_array( $data['content'] ) ? $data['content'] : array();
		$styling     = isset( $data['styling'] ) && is_array( $data['styling'] ) ? $data['styling'] : array();

		return array(
			'id'         => 'preview',
			'type'       => 'popup',
			'is_preview' => true,
			'settings'   => array( 'template' => $template ),
			'content'    => $content,
			'styling'    => $styling,
		);
	}

	/**
	 * Render a single campaign.
	 *
	 * @param array $campaign Campaign data.
	 */
	private function render_campaign( $campaign ) {
		$this->ensure_popup_dependencies_loaded();

		// Normalize campaign from get_all() (raw row with serialized content/styling).
		if ( isset( $campaign['content'] ) && ! is_array( $campaign['content'] ) ) {
			$campaign['content'] = maybe_unserialize( $campaign['content'] );
		}
		if ( isset( $campaign['styling'] ) && ! is_array( $campaign['styling'] ) ) {
			$campaign['styling'] = maybe_unserialize( $campaign['styling'] );
		}
		if ( empty( $campaign['settings'] ) && ! empty( $campaign['template_type'] ) ) {
			$campaign['settings'] = array( 'template' => $campaign['template_type'] );
		} elseif ( ! empty( $campaign['trigger_settings'] ) && ! is_array( $campaign['trigger_settings'] ) ) {
			$campaign['settings'] = maybe_unserialize( $campaign['trigger_settings'] );
		}
		if ( ! is_array( $campaign['content'] ) ) {
			$campaign['content'] = array();
		}
		if ( ! is_array( $campaign['styling'] ) ) {
			$campaign['styling'] = array();
		}

		$type     = isset( $campaign['type'] ) ? $campaign['type'] : 'popup';
		$template = $this->get_template( $type, $campaign );

		if ( $template ) {
			include $template;
		}
	}

	/**
	 * Ensure CRO_Placeholders and CRO_Templates are loaded before popup templates are included.
	 * Prevents "Class not found" when templates run on wp_footer.
	 */
	private function ensure_popup_dependencies_loaded() {
		if ( ! class_exists( 'CRO_Placeholders' ) ) {
			require_once CRO_PLUGIN_DIR . 'includes/class-cro-placeholders.php';
		}
		if ( ! class_exists( 'CRO_Templates' ) ) {
			require_once CRO_PLUGIN_DIR . 'includes/class-cro-templates.php';
		}
	}

	/**
	 * Get template file for campaign type.
	 *
	 * @param string $type     Campaign type.
	 * @param array  $campaign Campaign data.
	 * @return string|false Template path or false.
	 */
	private function get_template( $type, $campaign ) {
		$settings      = isset( $campaign['settings'] ) ? $campaign['settings'] : array();
		$template_name = isset( $settings['template'] ) ? $settings['template'] : ( isset( $campaign['template_type'] ) ? $campaign['template_type'] : 'centered' );
		$template_name = is_string( $template_name ) ? str_replace( array( ' ', '_' ), '-', $template_name ) : 'centered';

		$template_paths = array(
			'popup' => CRO_PLUGIN_DIR . 'templates/popups/' . $template_name . '.php',
		);

		if ( isset( $template_paths[ $type ] ) ) {
			if ( file_exists( $template_paths[ $type ] ) ) {
				return $template_paths[ $type ];
			}
			// Fallback to centered when template file does not exist (e.g. preview with unsaved template name).
			$fallback = CRO_PLUGIN_DIR . 'templates/popups/centered.php';
			if ( $type === 'popup' && file_exists( $fallback ) ) {
				return $fallback;
			}
		}

		return false;
	}
}
