<?php
/**
 * Shared Admin UI layout system for Meyvora Convert.
 * Provides full-width header, horizontal tab nav on every page, and content helpers.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MEYVC_Admin_UI
 */
class MEYVC_Admin_UI {

	const CONTENT_MAX_WIDTH = '1920px';

	/**
	 * Nav items (slug => label, url). Shown on every CRO admin page.
	 *
	 * @return array<string, array{label: string, url: string}>
	 */
	public static function get_nav_items() {
		return array(
			'meyvora-convert'         => array(
				'label' => __( 'Dashboard', 'meyvora-convert' ),
				'url'   => admin_url( 'admin.php?page=meyvora-convert' ),
			),
			'meyvc-presets'         => array(
				'label' => __( 'Presets', 'meyvora-convert' ),
				'url'   => admin_url( 'admin.php?page=meyvc-presets' ),
			),
			'meyvc-campaigns'       => array(
				'label' => __( 'Campaigns', 'meyvora-convert' ),
				'url'   => admin_url( 'admin.php?page=meyvc-campaigns' ),
			),
			'meyvc-sequences'       => array(
				'label' => __( 'Sequences', 'meyvora-convert' ),
				'url'   => admin_url( 'admin.php?page=meyvc-sequences' ),
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
			'meyvc-ab-tests'        => array(
				'label' => __( 'A/B Tests', 'meyvora-convert' ),
				'url'   => admin_url( 'admin.php?page=meyvc-ab-tests' ),
			),
			'meyvc-analytics'       => array(
				'label' => __( 'Analytics', 'meyvora-convert' ),
				'url'   => admin_url( 'admin.php?page=meyvc-analytics' ),
			),
			'meyvc-insights'        => array(
				'label' => __( 'Insights', 'meyvora-convert' ),
				'url'   => admin_url( 'admin.php?page=meyvc-insights' ),
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
			'meyvc-developer'       => array(
				'label' => __( 'Developer', 'meyvora-convert' ),
				'url'   => admin_url( 'admin.php?page=meyvc-developer' ),
			),
		);
	}

	/**
	 * Render a full admin page with shared layout (header, tabs, content).
	 *
	 * @param array $args {
	 *     @type string      $title           Page title (h1).
	 *     @type string      $subtitle        Optional. Description under title.
	 *     @type string      $active_tab      Current tab slug (e.g. meyvc-offers, meyvora-convert).
	 *     @type array|null  $primary_action  Optional. { label, href } or { label, form_id } or { label, button_id [, attributes ] }.
	 *     @type string      $content_partial Path to partial (absolute or relative to plugin dir).
	 *     @type string      $wrap_class      Optional. Extra class for .wrap.
	 *     @type array       $header_pills     Optional. Array of pill label strings for right side (e.g. array( '3/5 offers used' )).
	 * }
	 */
	public static function render_page( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'title'           => '',
				'subtitle'        => '',
				'active_tab'      => '',
				'primary_action'  => null,
				'content_partial' => '',
				'wrap_class'      => '',
				'header_meta'     => array(),
				'header_pills'    => array(),
			)
		);

		$active_tab  = is_string( $args['active_tab'] ) ? $args['active_tab'] : '';
		$wrap_class  = 'meyvc-admin-layout meyvc-ui-page' . ( $args['wrap_class'] !== '' ? ' ' . esc_attr( $args['wrap_class'] ) : '' );
		$partial_path = $args['content_partial'];
		if ( $partial_path !== '' && strpos( $partial_path, '/' ) !== 0 && strpos( $partial_path, ':' ) === false ) {
			$partial_path = MEYVC_PLUGIN_DIR . $partial_path;
		}

		$GLOBALS['meyvc_admin_active_tab'] = $active_tab;
		$page_slug = $active_tab;

		do_action( 'meyvc_admin_before_page', $page_slug );

		// Top-level wrapper: no .wrap here; only MEYVC_Admin_Layout::render_page() outputs .wrap. Margin applied via .meyvc-admin-layout.
		echo '<div class="' . esc_attr( $wrap_class ) . '">';

		$primary_action = apply_filters( 'meyvc_admin_primary_action', $args['primary_action'], $page_slug );
		self::render_header(
			$args['title'],
			$args['subtitle'],
			$primary_action,
			$args['header_meta'],
			$args['header_pills']
		);

		/* Sentinel for sticky nav shadow (above nav) */
		echo '<div class="meyvc-admin-layout__nav-sentinel" id="meyvc-admin-layout-nav-sentinel" aria-hidden="true"></div>';

		self::render_tabs( $active_tab );

		echo '<div class="meyvc-admin-layout__content-wrap">';
		echo '<div class="meyvc-admin-container meyvc-admin-layout__content meyvc-ui-content meyvc-ui-inner">';

		if ( $partial_path !== '' && is_readable( $partial_path ) ) {
			include $partial_path;
		}

		echo '</div></div>';
		echo '</div>';

		do_action( 'meyvc_admin_after_page', $page_slug );
	}

	/**
	 * Output header (title, subtitle, primary action, optional header_meta and header_pills).
	 *
	 * @param string       $title           Page title.
	 * @param string       $subtitle        Optional.
	 * @param array|null   $primary_action  Optional. label + href | form_id | button_id [+ attributes].
	 * @param array        $header_meta     Optional. Extra items (e.g. array of HTML strings) under subtitle on left.
	 * @param array        $header_pills    Optional. Array of pill label strings for right side (e.g. array( '3/5 offers used' )).
	 */
	private static function render_header( $title, $subtitle = '', $primary_action = null, $header_meta = array(), $header_pills = array() ) {
		echo '<header class="meyvc-admin-layout__header meyvc-ui-header">';
		echo '<div class="meyvc-admin-layout__header-inner">';

		echo '<div class="meyvc-ui-header__brand">';
		echo '<div class="meyvc-ui-header__logo" aria-hidden="true">';
		echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>';
		echo '</div>';

		echo '<div class="meyvc-ui-header__text">';
		echo '<h1 class="meyvc-ui-header__title">' . esc_html( $title ) . '</h1>';
		if ( $subtitle !== '' ) {
			echo '<p class="meyvc-ui-header__subtitle">' . esc_html( $subtitle ) . '</p>';
		}
		if ( ! empty( $header_meta ) && is_array( $header_meta ) ) {
			echo '<div class="meyvc-ui-header__meta">';
			foreach ( $header_meta as $meta ) {
				if ( is_string( $meta ) ) {
					echo wp_kses_post( $meta );
				}
			}
			echo '</div>';
		}
		echo '</div>';
		echo '</div>';

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
		if ( ! empty( $primary_action ) && isset( $primary_action['label'] ) ) {
			echo '<div class="meyvc-ui-header__actions">';
			$attrs = isset( $primary_action['attributes'] ) && is_array( $primary_action['attributes'] ) ? $primary_action['attributes'] : array();
			$cta_url = '';
			if ( ! empty( $primary_action['href'] ) ) {
				$cta_url = $primary_action['href'];
			} elseif ( ! empty( $primary_action['link'] ) ) {
				$cta_url = $primary_action['link'];
			}
			if ( $cta_url !== '' ) {
				echo '<a href="' . esc_url( $cta_url ) . '" class="button button-primary meyvc-ui-btn-primary">' . esc_html( $primary_action['label'] ) . '</a>';
			} elseif ( ! empty( $primary_action['form_id'] ) ) {
				echo '<button type="submit" form="' . esc_attr( $primary_action['form_id'] ) . '" class="button button-primary meyvc-ui-btn-primary">' . esc_html( $primary_action['label'] ) . '</button>';
			} elseif ( ! empty( $primary_action['button_id'] ) ) {
				echo '<button type="button" id="' . esc_attr( $primary_action['button_id'] ) . '" class="button button-primary meyvc-ui-btn-primary"';
				foreach ( $attrs as $attr_key => $attr_val ) {
					echo ' ' . esc_attr( $attr_key ) . '="' . esc_attr( $attr_val ) . '"';
				}
				echo '>' . esc_html( $primary_action['label'] ) . '</button>';
			}
			echo '</div>';
		}
		echo '</div>';
		echo '</div>';
		echo '</header>';
	}

	/**
	 * Output horizontal tab nav (full width, consistent spacing). Shown on every CRO page.
	 *
	 * @param string $active_tab Current page/tab slug.
	 */
	public static function render_tabs( $active_tab ) {
		$nav_items = apply_filters( 'meyvc_admin_tabs', self::get_nav_items() );
		echo '<nav class="meyvc-admin-layout__nav meyvc-ui-nav meyvc-ui-nav--tabs" aria-label="' . esc_attr__( 'CRO sections', 'meyvora-convert' ) . '">';
		echo '<div class="meyvc-admin-container meyvc-admin-layout__nav-inner meyvc-admin-layout__nav-inner--with-chat-toggle">';
		echo '<ul class="meyvc-ui-nav__list" role="list">';
		foreach ( $nav_items as $page_slug => $item ) {
			$active = ( $active_tab === $page_slug ) ? ' meyvc-ui-nav__link--active' : '';
			echo '<li class="meyvc-ui-nav__item">';
			echo '<a href="' . esc_url( $item['url'] ) . '" class="meyvc-ui-nav__link' . esc_attr( $active ) . '">' . esc_html( $item['label'] ) . '</a>';
			echo '</li>';
		}
		echo '</ul>';
		do_action( 'meyvc_admin_nav_actions' );
		echo '</div></nav>';
	}

	/**
	 * Render a card block (title, body, optional actions).
	 *
	 * @param string $title        Card heading.
	 * @param string $content_html Card body HTML.
	 * @param string $actions_html Optional. Actions area HTML (e.g. buttons).
	 */
	public static function render_card( $title, $content_html, $actions_html = '' ) {
		echo '<div class="meyvc-card meyvc-ui-card">';
		echo '<header class="meyvc-card__header meyvc-ui-card__header"><h2 class="meyvc-card__title">' . esc_html( $title ) . '</h2></header>';
		echo '<div class="meyvc-card__body meyvc-ui-card__body">';
		echo wp_kses_post( $content_html );
		if ( $actions_html !== '' ) {
			echo '<div class="meyvc-card__actions meyvc-ui-card__actions">' . wp_kses_post( $actions_html ) . '</div>';
		}
		echo '</div></div>';
	}

	/**
	 * Render a KPI-style card (label, value, optional hint).
	 *
	 * @param string $label Label (e.g. "Conversions").
	 * @param string $value Main value (e.g. "1,234").
	 * @param string $hint  Optional. Hint or secondary text.
	 */
	public static function render_kpi_card( $label, $value, $hint = '' ) {
		echo '<div class="meyvc-kpi-card meyvc-ui-kpi-card">';
		echo '<span class="meyvc-kpi-card__label">' . esc_html( $label ) . '</span>';
		echo '<span class="meyvc-kpi-card__value">' . esc_html( $value ) . '</span>';
		if ( $hint !== '' ) {
			echo '<span class="meyvc-kpi-card__hint">' . esc_html( $hint ) . '</span>';
		}
		echo '</div>';
	}
}
