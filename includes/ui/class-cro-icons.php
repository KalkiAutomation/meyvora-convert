<?php
/**
 * Lucide SVG icons for CRO admin and frontend.
 * All icons use currentColor and are safe for inline output.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CRO_Icons
 */
class CRO_Icons {

	/**
	 * Allowed SVG attribute names for merging into the root <svg>.
	 *
	 * @var string[]
	 */
	private static $allowed_attrs = array( 'class', 'aria-hidden', 'role', 'width', 'height', 'style' );

	/**
	 * Lucide-style icons (viewBox 0 0 24 24, stroke currentColor, stroke-width 2).
	 * Path data from Lucide (https://lucide.dev), MIT.
	 *
	 * @var array<string, string> Icon name => inner SVG markup (paths only).
	 */
	private static $icons = array(
		'settings'   => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
		'sparkles'   => '<path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/><path d="M5 3v4"/><path d="M19 17v4"/><path d="M3 5h4"/><path d="M17 19h4"/>',
		'tag'        => '<path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/>',
		'mail'       => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
		'chart'      => '<path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
		'check'      => '<path d="M20 6 9 17l-5-5"/>',
		'alert'      => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
		'trash'      => '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/>',
		'edit'       => '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/>',
		'plus'       => '<path d="M5 12h14"/><path d="M12 5v14"/>',
		'search'     => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
		// Extra icons for replacing all emoji across admin/front
		'file'       => '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/>',
		'user'       => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
		'smartphone' => '<rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/>',
		'monitor'    => '<rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/>',
		'shopping-cart' => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>',
		'target'     => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
		'link'       => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
		'calendar'   => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/>',
		'palette'    => '<circle cx="13.5" cy="6.5" r=".5"/><circle cx="17.5" cy="10.5" r=".5"/><circle cx="8.5" cy="7.5" r=".5"/><circle cx="6.5" cy="12.5" r=".5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.648 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.648-1.648h1.875c.926 0 1.648-.746 1.648-1.648 0-.926-.746-1.648-1.648-1.648H15.5"/>',
		'zap'        => '<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/>',
		'door-open'  => '<path d="M13 4h3a2 2 0 0 1 2 2v14"/><path d="M2 20h3"/><path d="M13 20h9"/><path d="M10 12v.01"/><path d="M13 4.562v14.876a2 2 0 0 1-1.106 1.789L6 21.5"/><path d="M6 21.5V4.562a2 2 0 0 1 1.106-1.789L10 2.5"/>',
		'scroll'     => '<path d="M8 3h8a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M8 8h8"/><path d="M8 12h8"/>',
		'moon'       => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
		'pointer'    => '<path d="M3 3l7.07 16.97 2.51-7.39 7.39-2.51L3 3z"/><path d="m13 13 6 6"/>',
		'eye'        => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
		'mouse-pointer' => '<path d="m3 3 7.07 16.97 2.51-7.39 7.39-2.51L3 3z"/><path d="m13 13 6 6"/>',
		'trending-up' => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
		'dollar-sign' => '<line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
		'truck'      => '<path d="M5 18H3c-.6 0-1-.4-1-1V7c0-.6.4-1 1-1h10c.6 0 1 .4 1 1v11"/><path d="M14 9h4l4 4v4c0 .6-.4 1-1 1h-2"/><circle cx="7" cy="18" r="2"/><path d="M15 18H9"/><circle cx="17" cy="18" r="2"/>',
		'trophy'     => '<path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 15.5v4"/><path d="M14 15.5v4"/><path d="M18 9a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2"/><path d="M12 2v4"/><path d="M12 22v-4"/><path d="m8 15 4 4 4-4"/>',
		'lock'       => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
		'refresh'    => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
		'undo'       => '<path d="M3 10h10a5 5 0 0 1 5 5v2"/><path d="M3 10 8 5l5 5"/>',
		'info'       => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
		'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
		'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
		'clock'        => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
		'x'            => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
		'arrow-right'  => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
		'arrow-left'   => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
		'chevron-up'   => '<path d="m18 15-6-6-6 6"/>',
		'chevron-left' => '<path d="m15 18-6-6 6-6"/>',
		'upload'       => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/>',
		'image'        => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
		'shield'       => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
		'tablet'       => '<rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M12 18h.01"/>',
		'copy'         => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M15 2H9a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2Z"/>',
		'pencil'       => '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/>',
		'external-link' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="m21 3-9 9"/>',
		'shuffle'    => '<path d="m18 14 4 4-4 4"/><path d="m18 2 4 4-4 4"/><path d="M2 18h1.973a4 4 0 0 0 3.3-1.7l5.454-8.6a4 4 0 0 1 3.3-1.7H22"/><path d="M2 6h1.972a4 4 0 0 1 3.6 2.2"/><path d="M22 18h-6.041a4 4 0 0 1-3.3-1.8l-.359-.45"/>',
	);

	/**
	 * Cached KSES allowlist: post tags plus Lucide SVG elements (svg, path, circle, etc.).
	 * Use with svg_kses() / svg_kses_html() — wp_kses_post() strips inline SVG by default.
	 *
	 * @var array<string, array<string, bool>>|null
	 */
	private static $svg_kses_allowed = null;

	/**
	 * KSES allowlist for Lucide inline SVG output.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function get_svg_kses_allowed() {
		if ( self::$svg_kses_allowed !== null ) {
			return self::$svg_kses_allowed;
		}
		$svg_tags = array(
			'svg'      => array(
				'xmlns'           => true,
				'viewbox'         => true,
				'viewBox'         => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'aria-hidden'     => true,
				'role'            => true,
				'width'           => true,
				'height'          => true,
				'class'           => true,
				'style'           => true,
			),
			'path'     => array( 'd' => true ),
			'circle'   => array( 'cx' => true, 'cy' => true, 'r' => true ),
			'rect'     => array( 'width' => true, 'height' => true, 'x' => true, 'y' => true, 'rx' => true, 'ry' => true ),
			'line'     => array( 'x1' => true, 'x2' => true, 'y1' => true, 'y2' => true ),
			'polyline' => array( 'points' => true ),
		);
		self::$svg_kses_allowed = array_merge( wp_kses_allowed_html( 'post' ), $svg_tags );
		return self::$svg_kses_allowed;
	}

	/**
	 * Return icon SVG run through wp_kses with SVG allowed (unlike wp_kses_post).
	 *
	 * @param string               $name  Icon name.
	 * @param array<string, mixed> $attrs Optional svg attributes.
	 * @return string
	 */
	public static function svg_kses( $name, $attrs = array() ) {
		return wp_kses( self::svg( $name, $attrs ), self::get_svg_kses_allowed() );
	}

	/**
	 * Sanitize an already-built Lucide SVG string (e.g. from svg() with dynamic class).
	 *
	 * @param string $html SVG fragment.
	 * @return string
	 */
	public static function svg_kses_html( $html ) {
		return is_string( $html ) ? wp_kses( $html, self::get_svg_kses_allowed() ) : '';
	}

	/**
	 * Return safe inline SVG markup for an icon.
	 *
	 * @param string $name Icon name (e.g. 'settings', 'check', 'alert').
	 * @param array  $attrs Optional attributes for the <svg> (e.g. ['class' => 'cro-ico']). Only allowed keys are used.
	 * @return string Escaped SVG markup, or empty string if icon unknown.
	 */
	public static function svg( $name, $attrs = array() ) {
		$name = is_string( $name ) ? trim( $name ) : '';
		if ( $name === '' || ! isset( self::$icons[ $name ] ) ) {
			return '';
		}
		$svg_attrs = array(
			'xmlns'           => 'http://www.w3.org/2000/svg',
			'viewBox'         => '0 0 24 24',
			'fill'            => 'none',
			'stroke'          => 'currentColor',
			'stroke-width'    => '2',
			'stroke-linecap'   => 'round',
			'stroke-linejoin'  => 'round',
			'aria-hidden'     => 'true',
		);
		foreach ( self::$allowed_attrs as $key ) {
			if ( isset( $attrs[ $key ] ) && is_scalar( $attrs[ $key ] ) ) {
				$svg_attrs[ $key ] = $attrs[ $key ];
			}
		}
		$attr_string = '';
		foreach ( $svg_attrs as $k => $v ) {
			$attr_string .= ' ' . esc_attr( $k ) . '="' . esc_attr( (string) $v ) . '"';
		}
		return '<svg' . $attr_string . '>' . self::$icons[ $name ] . '</svg>';
	}
}
