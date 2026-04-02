<?php
/**
 * Shared admin layout renderer for Meyvora Convert pages.
 * Provides full-width header, horizontal tab nav, and content container.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MEYVC_Admin_Layout
 */
class MEYVC_Admin_Layout {

	/**
	 * Default max width for content (aligned with WP admin).
	 *
	 * @var string
	 */
	const CONTENT_MAX_WIDTH = '1920px';

	/**
	 * Get nav items (page slug => label, url). Same on every page.
	 *
	 * @return array<string, array{label: string, url: string}>
	 */
	public static function get_nav_items() {
		return array(
			'meyvora-convert'         => array(
				'label' => __( 'Dashboard', 'meyvora-convert' ),
				'url'   => admin_url( 'admin.php?page=meyvora-convert' ),
			),
			'meyvc-offers'          => array(
				'label' => __( 'Offers', 'meyvora-convert' ),
				'url'   => admin_url( 'admin.php?page=meyvc-offers' ),
			),
			'meyvc-abandoned-carts' => array(
				'label' => __( 'Abandoned Carts', 'meyvora-convert' ),
				'url'   => admin_url( 'admin.php?page=meyvc-abandoned-carts' ),
			),
			'meyvc-abandoned-cart'  => array(
				'label' => __( 'Abandoned Cart Emails', 'meyvora-convert' ),
				'url'   => admin_url( 'admin.php?page=meyvc-abandoned-cart' ),
			),
			'meyvc-cart'            => array(
				'label' => __( 'Cart Optimizer', 'meyvora-convert' ),
				'url'   => admin_url( 'admin.php?page=meyvc-cart' ),
			),
			'meyvc-checkout'        => array(
				'label' => __( 'Checkout Optimizer', 'meyvora-convert' ),
				'url'   => admin_url( 'admin.php?page=meyvc-checkout' ),
			),
			'meyvc-boosters'        => array(
				'label' => __( 'Boosters', 'meyvora-convert' ),
				'url'   => admin_url( 'admin.php?page=meyvc-boosters' ),
			),
			'meyvc-analytics'       => array(
				'label' => __( 'Analytics', 'meyvora-convert' ),
				'url'   => admin_url( 'admin.php?page=meyvc-analytics' ),
			),
			'meyvc-settings'        => array(
				'label' => __( 'Settings', 'meyvora-convert' ),
				'url'   => admin_url( 'admin.php?page=meyvc-settings' ),
			),
			'meyvc-system-status'   => array(
				'label' => __( 'System Status', 'meyvora-convert' ),
				'url'   => admin_url( 'admin.php?page=meyvc-system-status' ),
			),
			'meyvc-tools'           => array(
				'label' => __( 'Tools', 'meyvora-convert' ),
				'url'   => admin_url( 'admin.php?page=meyvc-tools' ),
			),
		);
	}

	/**
	 * Render a full admin page with shared layout.
	 *
	 * @param array $args {
	 *     @type string      $title                Page title (h1).
	 *     @type string      $subtitle             Optional. Description under title.
	 *     @type string      $active_tab           Current page slug (e.g. meyvc-offers, meyvc-system-status).
	 *     @type array       $primary_cta          Optional. { label, link } or { label, form_id } or { label, button_id }.
	 *     @type string      $content_partial_path Path to partial for main content (no wrap/header/nav).
	 *     @type string      $wrap_class           Optional. Extra class for .wrap (e.g. meyvc-admin-offers).
	 * }
	 */
	public static function render_page( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'title'                => '',
				'subtitle'             => '',
				'active_tab'           => '',
				'primary_cta'         => null,
				'content_partial_path' => '',
				'wrap_class'           => '',
				'header_pills'         => array(),
			)
		);

		$active_tab = is_string( $args['active_tab'] ) ? $args['active_tab'] : '';
		$wrap_class = 'meyvc-admin-layout meyvc-ui-page' . ( $args['wrap_class'] !== '' ? ' ' . esc_attr( $args['wrap_class'] ) : '' );

		// So nav partial can use it.
		$GLOBALS['meyvc_admin_active_tab'] = $active_tab;

		echo '<div class="wrap ' . esc_attr( $wrap_class ) . '">';

		// Full-width header
		self::render_header( $args['title'], $args['subtitle'], $args['primary_cta'], $args['header_pills'] );

		// Sentinel for sticky nav shadow (1px above nav)
		echo '<div class="meyvc-admin-layout__nav-sentinel" id="meyvc-admin-layout-nav-sentinel" aria-hidden="true"></div>';

		// Full-width nav (aligned with content via inner container)
		self::render_nav( $active_tab );

		// Content: single inner container for consistent padding (one .wrap at top only)
		echo '<div class="meyvc-admin-layout__content-wrap">';
		echo '<div class="meyvc-admin-layout__content meyvc-ui-content meyvc-ui-inner">';

		if ( $args['content_partial_path'] !== '' && is_readable( $args['content_partial_path'] ) ) {
			include $args['content_partial_path'];
		}

		echo '</div></div>';
		echo '</div>';
	}

	/**
	 * Output header block (title, subtitle, primary CTA aligned right).
	 * Optional header_pills: array of strings (each wrapped in .meyvc-pill) shown on the right above the CTA.
	 *
	 * @param string       $title        Page title (H1).
	 * @param string       $subtitle     Optional. Description line under title.
	 * @param array|null   $primary_cta  Optional. { label, link } | { label, form_id } | { label, button_id [, attributes ] }.
	 * @param array        $header_pills Optional. Array of pill label strings (e.g. array( '3/5 offers used' )).
	 */
	private static function render_header( $title, $subtitle = '', $primary_cta = null, $header_pills = array() ) {
		echo '<header class="meyvc-admin-layout__header meyvc-ui-header">';
		echo '<div class="meyvc-admin-layout__header-inner">';

		// Left: brand mark + title.
		echo '<div class="meyvc-ui-header__brand">';
		echo '<div class="meyvc-ui-header__logo" aria-hidden="true">';
		echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>';
		echo '</div>';

		echo '<div class="meyvc-ui-header__text">';
		echo '<h1 class="meyvc-ui-header__title">' . esc_html( $title ) . '</h1>';
		if ( $subtitle !== '' ) {
			echo '<p class="meyvc-ui-header__subtitle">' . esc_html( $subtitle ) . '</p>';
		}
		echo '</div>';
		echo '</div>';

		// Right: pills + action buttons.
		echo '<div class="meyvc-ui-header__right">';

		if ( ! empty( $header_pills ) && is_array( $header_pills ) ) {
			echo '<div class="meyvc-ui-header__pills">';
			foreach ( $header_pills as $pill ) {
				if ( is_string( $pill ) && $pill !== '' ) {
					echo '<span class="meyvc-pill">' . esc_html( $pill ) . '</span>';
				} elseif ( is_array( $pill ) && isset( $pill['label'] ) ) {
					$class = 'meyvc-pill' . ( ! empty( $pill['class'] ) ? ' ' . esc_attr( $pill['class'] ) : '' );
					echo '<span class="' . esc_attr( $class ) . '">' . esc_html( $pill['label'] ) . '</span>';
				}
			}
			echo '</div>';
		}

		if ( ! empty( $primary_cta ) && isset( $primary_cta['label'] ) ) {
			echo '<div class="meyvc-ui-header__actions">';
			$attrs = isset( $primary_cta['attributes'] ) && is_array( $primary_cta['attributes'] ) ? $primary_cta['attributes'] : array();
			$cta_url = '';
			if ( ! empty( $primary_cta['link'] ) ) {
				$cta_url = $primary_cta['link'];
			} elseif ( ! empty( $primary_cta['href'] ) ) {
				$cta_url = $primary_cta['href'];
			}
			if ( $cta_url !== '' ) {
				echo '<a href="' . esc_url( $cta_url ) . '" class="button button-primary meyvc-ui-btn-primary">' . esc_html( $primary_cta['label'] ) . '</a>';
			} elseif ( ! empty( $primary_cta['form_id'] ) ) {
				echo '<button type="submit" form="' . esc_attr( $primary_cta['form_id'] ) . '" class="button button-primary meyvc-ui-btn-primary">' . esc_html( $primary_cta['label'] ) . '</button>';
			} elseif ( ! empty( $primary_cta['button_id'] ) ) {
				echo '<button type="button" id="' . esc_attr( $primary_cta['button_id'] ) . '" class="button button-primary meyvc-ui-btn-primary"';
				foreach ( $attrs as $attr_key => $attr_val ) {
					echo ' ' . esc_attr( $attr_key ) . '="' . esc_attr( $attr_val ) . '"';
				}
				echo '>' . esc_html( $primary_cta['label'] ) . '</button>';
			}
			echo '</div>';
		}

		echo '</div>';
		echo '</div>';
		echo '</header>';
	}

	/**
	 * Output horizontal tab nav (full width, inner aligned with content).
	 *
	 * @param string $active_tab Current page slug.
	 */
	private static function render_nav( $active_tab ) {
		$nav_items = self::get_nav_items();
		echo '<nav class="meyvc-admin-layout__nav meyvc-ui-nav" aria-label="' . esc_attr__( 'CRO sections', 'meyvora-convert' ) . '">';
		echo '<div class="meyvc-admin-layout__nav-inner">';
		echo '<ul class="meyvc-ui-nav__list" role="list">';
		foreach ( $nav_items as $page_slug => $item ) {
			$active = ( $active_tab === $page_slug ) ? ' meyvc-ui-nav__link--active' : '';
			echo '<li class="meyvc-ui-nav__item">';
			echo '<a href="' . esc_url( $item['url'] ) . '" class="meyvc-ui-nav__link' . esc_attr( $active ) . '">' . esc_html( $item['label'] ) . '</a>';
			echo '</li>';
		}
		echo '</ul></div></nav>';
	}
}
