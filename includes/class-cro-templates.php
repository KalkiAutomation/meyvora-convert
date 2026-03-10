<?php
/**
 * Template System
 *
 * Manages popup templates with previews and configurations.
 *
 * @package Meyvora_Convert
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CRO_Templates class.
 *
 * Manages popup templates with previews and configurations.
 */
class CRO_Templates {

	/**
	 * Get all available templates
	 *
	 * @return array
	 */
	public static function get_all() {
		$templates = array(

			// ==========================================
			// POPUP TEMPLATES (Centered/Modal)
			// ==========================================

			'centered'           => array(
				'name'            => __( 'Centered Modal', 'meyvora-convert' ),
				'description'     => __( 'Classic centered popup with overlay', 'meyvora-convert' ),
				'type'            => 'popup',
				'category'        => 'modal',
				'preview_image'   => '',
				'supports'        => array( 'image', 'headline', 'subheadline', 'body', 'cta', 'email', 'coupon', 'countdown', 'dismiss' ),
				'default_styling' => array(
					'position'        => 'center',
					'size'            => 'medium',
					'animation'       => 'fade',
					'overlay_opacity' => 50,
				),
			),

			'centered-image-left'  => array(
				'name'            => __( 'Image Left', 'meyvora-convert' ),
				'description'     => __( 'Two-column layout with image on left side', 'meyvora-convert' ),
				'type'            => 'popup',
				'category'        => 'modal',
				'preview_image'   => '',
				'supports'        => array( 'image', 'headline', 'subheadline', 'body', 'cta', 'email', 'coupon', 'countdown', 'dismiss' ),
				'default_styling' => array(
					'position'        => 'center',
					'size'            => 'large',
					'animation'       => 'fade',
					'layout'          => 'two-column-left',
				),
			),

			'centered-image-right' => array(
				'name'            => __( 'Image Right', 'meyvora-convert' ),
				'description'     => __( 'Two-column layout with image on right side', 'meyvora-convert' ),
				'type'            => 'popup',
				'category'        => 'modal',
				'preview_image'   => '',
				'supports'        => array( 'image', 'headline', 'subheadline', 'body', 'cta', 'email', 'coupon', 'countdown', 'dismiss' ),
				'default_styling' => array(
					'position'        => 'center',
					'size'            => 'large',
					'animation'       => 'fade',
					'layout'          => 'two-column-right',
				),
			),

			'centered-image-top'  => array(
				'name'            => __( 'Image Top', 'meyvora-convert' ),
				'description'     => __( 'Large image at top with content below', 'meyvora-convert' ),
				'type'            => 'popup',
				'category'        => 'modal',
				'preview_image'   => '',
				'supports'        => array( 'image', 'headline', 'subheadline', 'body', 'cta', 'email', 'coupon', 'countdown', 'dismiss' ),
				'default_styling' => array(
					'position'        => 'center',
					'size'            => 'medium',
					'animation'       => 'zoom',
					'layout'          => 'image-top',
				),
			),

			'fullscreen'          => array(
				'name'            => __( 'Fullscreen', 'meyvora-convert' ),
				'description'     => __( 'Full-screen takeover for maximum impact', 'meyvora-convert' ),
				'type'            => 'popup',
				'category'        => 'modal',
				'preview_image'   => '',
				'supports'        => array( 'image', 'headline', 'subheadline', 'body', 'cta', 'email', 'coupon', 'countdown', 'dismiss' ),
				'default_styling' => array(
					'position'        => 'center',
					'size'            => 'fullscreen',
					'animation'       => 'fade',
				),
			),

			'minimal'             => array(
				'name'            => __( 'Minimal', 'meyvora-convert' ),
				'description'     => __( 'Clean, simple design focused on message', 'meyvora-convert' ),
				'type'            => 'popup',
				'category'        => 'modal',
				'preview_image'   => '',
				'supports'        => array( 'headline', 'subheadline', 'body', 'cta', 'coupon', 'dismiss' ),
				'default_styling' => array(
					'position'        => 'center',
					'size'            => 'small',
					'animation'       => 'fade',
				),
			),

			// ==========================================
			// SLIDE-IN TEMPLATES
			// ==========================================

			'slide-bottom'        => array(
				'name'            => __( 'Bottom Slide', 'meyvora-convert' ),
				'description'     => __( 'Slides up from bottom of screen', 'meyvora-convert' ),
				'type'            => 'slide',
				'category'        => 'slide',
				'preview_image'   => '',
				'supports'        => array( 'headline', 'subheadline', 'body', 'cta', 'coupon', 'dismiss' ),
				'default_styling' => array(
					'position'        => 'bottom-center',
					'size'            => 'auto',
					'animation'       => 'slide-up',
					'overlay'         => false,
				),
			),

			'slide-bottom-left'   => array(
				'name'            => __( 'Bottom Left Corner', 'meyvora-convert' ),
				'description'     => __( 'Small notification in bottom left corner', 'meyvora-convert' ),
				'type'            => 'slide',
				'category'        => 'slide',
				'preview_image'   => '',
				'supports'        => array( 'headline', 'body', 'cta', 'coupon', 'dismiss' ),
				'default_styling' => array(
					'position'        => 'bottom-left',
					'size'            => 'small',
					'animation'       => 'slide-up',
					'overlay'         => false,
				),
			),

			'slide-bottom-right'  => array(
				'name'            => __( 'Bottom Right Corner', 'meyvora-convert' ),
				'description'     => __( 'Small notification in bottom right corner', 'meyvora-convert' ),
				'type'            => 'slide',
				'category'        => 'slide',
				'preview_image'   => '',
				'supports'        => array( 'headline', 'body', 'cta', 'coupon', 'dismiss' ),
				'default_styling' => array(
					'position'        => 'bottom-right',
					'size'            => 'small',
					'animation'       => 'slide-up',
					'overlay'         => false,
				),
			),

			// ==========================================
			// BAR TEMPLATES
			// ==========================================

			'top-bar'             => array(
				'name'            => __( 'Top Bar', 'meyvora-convert' ),
				'description'     => __( 'Sticky bar at top of page', 'meyvora-convert' ),
				'type'            => 'bar',
				'category'        => 'bar',
				'preview_image'   => '',
				'supports'        => array( 'headline', 'cta', 'coupon', 'countdown' ),
				'default_styling' => array(
					'position'        => 'top',
					'size'            => 'bar',
					'animation'       => 'slide-down',
					'overlay'         => false,
				),
			),

			'bottom-bar'          => array(
				'name'            => __( 'Bottom Bar', 'meyvora-convert' ),
				'description'     => __( 'Sticky bar at bottom of page', 'meyvora-convert' ),
				'type'            => 'bar',
				'category'        => 'bar',
				'preview_image'   => '',
				'supports'        => array( 'headline', 'cta', 'coupon', 'countdown' ),
				'default_styling' => array(
					'position'        => 'bottom',
					'size'            => 'bar',
					'animation'       => 'slide-up',
					'overlay'         => false,
				),
			),

		);

		return apply_filters( 'cro_templates', $templates );
	}

	/**
	 * Get templates that have an existing PHP file for use in the campaign builder.
	 * Only these should be shown so selecting a template actually renders that layout.
	 * Uses empty preview_image so the builder shows a text placeholder (avoids 404 when assets missing).
	 *
	 * @return array Associative array of template_key => template config.
	 */
	public static function get_available_for_builder() {
		$popup_dir = CRO_PLUGIN_DIR . 'templates/popups/';
		$all       = self::get_all();
		$available = array();

		foreach ( $all as $key => $config ) {
			$file = $popup_dir . $key . '.php';
			if ( file_exists( $file ) ) {
				$config['preview_image'] = ''; // Use text placeholder; assets/images/templates may not exist.
				$available[ $key ]       = $config;
			}
		}

		return apply_filters( 'cro_templates_available_for_builder', $available );
	}

	/**
	 * Get single template
	 *
	 * @param string $template_key Template key.
	 * @return array|null Template config or null.
	 */
	public static function get( $template_key ) {
		$templates = self::get_all();
		return isset( $templates[ $template_key ] ) ? $templates[ $template_key ] : null;
	}

	/**
	 * Get templates by type
	 *
	 * @param string $type Template type (popup, slide, bar, gamified).
	 * @return array
	 */
	public static function get_by_type( $type ) {
		$templates = self::get_all();
		return array_filter( $templates, function ( $t ) use ( $type ) {
			return ( isset( $t['type'] ) && $t['type'] === $type );
		} );
	}

	/**
	 * Get templates by category
	 *
	 * @param string $category Template category.
	 * @return array
	 */
	public static function get_by_category( $category ) {
		$templates = self::get_all();
		return array_filter( $templates, function ( $t ) use ( $category ) {
			return ( isset( $t['category'] ) && $t['category'] === $category );
		} );
	}

	/**
	 * Check if template supports a feature
	 *
	 * @param string $template_key Template key.
	 * @param string $feature      Feature key.
	 * @return bool
	 */
	public static function supports( $template_key, $feature ) {
		$template = self::get( $template_key );
		if ( ! $template ) {
			return false;
		}
		return in_array( $feature, isset( $template['supports'] ) ? $template['supports'] : array(), true );
	}

	/**
	 * Get template file path
	 *
	 * @param string $template_key Template key.
	 * @return string File path.
	 */
	public static function get_template_file( $template_key ) {
		$template = self::get( $template_key );
		if ( ! $template ) {
			$template_key = 'centered';
		}

		// Check theme override
		$theme_file = get_stylesheet_directory() . '/meyvora-convert/templates/' . $template_key . '.php';
		if ( file_exists( $theme_file ) ) {
			return $theme_file;
		}

		// Check plugin templates
		$plugin_file = CRO_PLUGIN_DIR . 'templates/popups/' . $template_key . '.php';
		if ( file_exists( $plugin_file ) ) {
			return $plugin_file;
		}

		// Fallback to centered
		return CRO_PLUGIN_DIR . 'templates/popups/centered.php';
	}

	/**
	 * Render template HTML
	 *
	 * @param string $template_key Template key.
	 * @param object $campaign     Campaign model or object with content, styling.
	 * @return string HTML output.
	 */
	public static function render( $template_key, $campaign ) {
		$template_file = self::get_template_file( $template_key );

		// Make campaign data available to template
		$content         = is_object( $campaign ) && isset( $campaign->content ) ? $campaign->content : array();
		$styling         = is_object( $campaign ) && isset( $campaign->styling ) ? $campaign->styling : array();
		$template_config = self::get( $template_key );

		ob_start();
		include $template_file;
		return ob_get_clean();
	}

	/**
	 * Get template categories for UI
	 *
	 * @return array
	 */
	public static function get_categories() {
		return array(
			'modal' => __( 'Modal Popups', 'meyvora-convert' ),
			'slide' => __( 'Slide-ins', 'meyvora-convert' ),
			'bar'   => __( 'Notification Bars', 'meyvora-convert' ),
		);
	}

	/**
	 * Get inline styles for popup container (optionally with brand override CSS vars).
	 *
	 * @param array       $styling  Campaign styling.
	 * @param array|object $campaign Optional. Campaign with brand_styles_override.
	 * @return string
	 */
	public static function get_inline_styles( $styling, $campaign = null ) {
		$styles = array();

		if ( ! empty( $styling['bg_color'] ) ) {
			$styles[] = 'background-color: ' . $styling['bg_color'];
		}

		if ( ! empty( $styling['text_color'] ) ) {
			$styles[] = 'color: ' . $styling['text_color'];
		}

		if ( ! empty( $styling['border_radius'] ) ) {
			$styles[] = 'border-radius: ' . intval( $styling['border_radius'] ) . 'px';
		}

		$override = null;
		if ( $campaign !== null ) {
			$override = is_array( $campaign )
				? ( $campaign['brand_styles_override'] ?? ( isset( $campaign['display_rules']['brand_styles_override'] ) ? $campaign['display_rules']['brand_styles_override'] : null ) )
				: ( $campaign->brand_styles_override ?? null );
		}
		if ( ! empty( $override ) && is_array( $override ) && ! empty( $override['use'] ) ) {
			$styles[] = self::get_brand_override_style_string( $override );
		}

		return implode( '; ', $styles );
	}

	/**
	 * Get CSS custom property string for brand style overrides (for use in inline style).
	 *
	 * @param array $override brand_styles_override (use, primary_color, secondary_color, button_radius, font_size_scale).
	 * @return string
	 */
	public static function get_brand_override_style_string( $override ) {
		$vars = array();
		if ( ! empty( $override['primary_color'] ) && sanitize_hex_color( $override['primary_color'] ) ) {
			$hex = sanitize_hex_color( $override['primary_color'] );
			$vars[] = '--cro-primary: ' . $hex;
			$vars[] = '--cro-primary-color: ' . $hex;
		}
		if ( ! empty( $override['secondary_color'] ) && sanitize_hex_color( $override['secondary_color'] ) ) {
			$vars[] = '--cro-secondary-color: ' . sanitize_hex_color( $override['secondary_color'] );
		}
		if ( isset( $override['button_radius'] ) && $override['button_radius'] !== '' ) {
			$px = (int) $override['button_radius'] . 'px';
			$vars[] = '--cro-radius: ' . $px;
			$vars[] = '--cro-button-radius: ' . $px;
		}
		if ( isset( $override['font_size_scale'] ) && $override['font_size_scale'] !== '' ) {
			$scale = (float) $override['font_size_scale'];
			$scale = $scale >= 0.5 && $scale <= 2 ? $scale : 1;
			$vars[] = '--cro-font-size-scale: ' . $scale;
		}
		return implode( '; ', $vars );
	}

	/**
	 * Get inline styles for button
	 */
	public static function get_button_styles($styling) {
		$styles = array();
		
		if (!empty($styling['button_bg_color'])) {
			$styles[] = 'background-color: ' . $styling['button_bg_color'];
		}
		
		if (!empty($styling['button_text_color'])) {
			$styles[] = 'color: ' . $styling['button_text_color'];
		}
		
		if (!empty($styling['border_radius'])) {
			$styles[] = 'border-radius: ' . intval($styling['border_radius'] / 2) . 'px';
		}
		
		return implode('; ', $styles);
	}
}
