/**
 * Meyvora Convert – Cart & Checkout Blocks extension (Slot/Fill).
 *
 * Renders into WooCommerce Blocks slots (cart sidebar, checkout order summary, place order area):
 * - TrustStrip: icons + microcopy (secure checkout, fast shipping, easy returns)
 * - GuaranteeNote: near place order (checkout)
 * - ShippingProgress: "You're X away from free shipping" (live from cart + threshold)
 * - Optional Urgency: "Limited stock" only when provided via settings
 * - Offer banner (when enabled)
 *
 * Data: getSetting('meyvora-convert_data') for config (threshold, labels, enabled flags).
 * Cart: Store API cart from slot props – updates live as cart totals change.
 * No PHP hooks relied upon for Blocks; script data is from IntegrationInterface get_script_data().
 */
import { createElement, useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import { __ } from '@wordpress/i18n';

const { ExperimentalOrderMeta, ExperimentalOrderShippingPackages } =
	window.wc?.blocksCheckout || {};

const BANNER_WINDOW_SECONDS = 86400; // 24h

function getCROData() {
	try {
		const settings = window.wc?.wcSettings;
		if ( settings && typeof settings.getSetting === 'function' ) {
			return settings.getSetting( 'meyvora-convert_data', {} );
		}
		if ( window.wcSettings && window.wcSettings['meyvora-convert_data'] ) {
			return window.wcSettings['meyvora-convert_data'];
		}
	} catch ( e ) {
		// ignore
	}
	return {};
}

let _debugLogged = false;

// Fixed badge shown when debug is true (Blocks debug mode).
function DebugBadge() {
	return createElement(
		'div',
		{
			className: 'cro-blocks-debug-badge',
			style: {
				position: 'fixed',
				bottom: '12px',
				right: '12px',
				zIndex: 999998,
				padding: '6px 10px',
				fontSize: '12px',
				fontFamily: 'sans-serif',
				background: '#1d2327',
				color: '#f0f0f1',
				borderRadius: '4px',
				boxShadow: '0 2px 8px rgba(0,0,0,0.2)',
			},
		},
		__( 'Meyvora Convert Blocks Extension Loaded', 'meyvora-convert' )
	);
}

function getBannerViews( cookieName ) {
	try {
		const name = ( cookieName || 'cro_banner_views' ) + '=';
		const ca = typeof document !== 'undefined' ? document.cookie.split( ';' ) : [];
		for ( let i = 0; i < ca.length; i++ ) {
			let c = ca[ i ];
			while ( c.charAt( 0 ) === ' ' ) c = c.substring( 1, c.length );
			if ( c.indexOf( name ) !== 0 ) continue;
			const raw = c.substring( name.length, c.length );
			let decoded;
			try {
				decoded = JSON.parse( decodeURIComponent( raw ) );
			} catch ( e1 ) {
				try {
					decoded = JSON.parse( raw );
				} catch ( e2 ) {
					return {};
				}
			}
			if ( ! decoded || typeof decoded !== 'object' ) return {};
			const cutoff = Math.floor( Date.now() / 1000 ) - BANNER_WINDOW_SECONDS;
			const out = {};
			for ( const key of [ 'shipping_bar', 'trust', 'urgency', 'offer' ] ) {
				const list = Array.isArray( decoded[ key ] ) ? decoded[ key ] : [];
				out[ key ] = list.filter( ( ts ) => ( ts | 0 ) >= cutoff );
			}
			return out;
		}
	} catch ( e ) {
		// ignore
	}
	return {};
}

function canShowBanner( type, maxPer24h, cookieName ) {
	if ( ! maxPer24h || maxPer24h <= 0 ) return true;
	const views = getBannerViews( cookieName );
	const list = views[ type ] || [];
	return list.length < maxPer24h;
}

function recordBannerShow( type, cookieName ) {
	const name = cookieName || 'cro_banner_views';
	const views = getBannerViews( name );
	if ( ! Array.isArray( views[ type ] ) ) views[ type ] = [];
	views[ type ].push( Math.floor( Date.now() / 1000 ) );
	if ( views[ type ].length > 100 ) views[ type ] = views[ type ].slice( -100 );
	try {
		const val = encodeURIComponent( JSON.stringify( views ) );
		const days = 30;
		const expires = new Date( Date.now() + days * 86400 * 1000 ).toUTCString();
		document.cookie = name + '=' + val + ';path=/;expires=' + expires + ';SameSite=Lax';
	} catch ( e ) {
		// ignore
	}
}

// Default trust labels (secure checkout, fast shipping, easy returns).
const DEFAULT_TRUST_LABELS = [
	__( 'Secure checkout', 'meyvora-convert' ),
	__( 'Fast shipping', 'meyvora-convert' ),
	__( 'Easy returns', 'meyvora-convert' ),
];
const TRUST_ICONS = [ '🔒', '🚚', '↩' ];

function parseTrustLabels( trustMessage ) {
	if ( ! trustMessage || typeof trustMessage !== 'string' ) {
		return DEFAULT_TRUST_LABELS;
	}
	const trimmed = trustMessage.trim();
	const parts = trimmed.split( /[\-\–\—\/\|]\s*/ ).map( ( s ) => s.trim() ).filter( Boolean );
	if ( parts.length >= 3 ) {
		return parts.slice( 0, 3 );
	}
	if ( parts.length === 1 ) {
		return [ trimmed, DEFAULT_TRUST_LABELS[1], DEFAULT_TRUST_LABELS[2] ];
	}
	if ( parts.length === 2 ) {
		return [ parts[0], parts[1], DEFAULT_TRUST_LABELS[2] ];
	}
	return DEFAULT_TRUST_LABELS;
}

// TrustStrip: icons + microcopy (secure checkout, fast shipping, easy returns). Cart & checkout. Respects frequency cap.
function TrustStrip( { context, cartOptimizerEnabled, checkoutOptimizerEnabled, cartSettings, checkoutSettings, bannerFrequencyCapMax, bannerViewsCookieName } ) {
	const recorded = useRef( false );
	const isCart = context === 'woocommerce/cart';
	const isCheckout = context === 'woocommerce/checkout';
	let enabled = false;
	let trustMessage = '';
	if ( isCart && cartOptimizerEnabled && cartSettings?.show_trust_under_total ) {
		enabled = true;
		trustMessage = cartSettings.trust_message || ( DEFAULT_TRUST_LABELS.join( ' - ' ) );
	}
	if ( isCheckout && checkoutOptimizerEnabled && ( checkoutSettings?.show_secure_badge || checkoutSettings?.show_trust_message ) ) {
		enabled = true;
		if ( checkoutSettings.show_secure_badge ) {
			trustMessage = __( 'Secure Checkout', 'meyvora-convert' );
		}
		if ( checkoutSettings.show_trust_message && checkoutSettings.trust_message_text ) {
			trustMessage = trustMessage ? `${ trustMessage } – ${ checkoutSettings.trust_message_text }` : checkoutSettings.trust_message_text;
		}
		if ( ! trustMessage ) {
			trustMessage = DEFAULT_TRUST_LABELS.join( ' - ' );
		}
	}
	if ( ! enabled || ! trustMessage ) return null;
	if ( ! canShowBanner( 'trust', bannerFrequencyCapMax || 0, bannerViewsCookieName ) ) return null;
	if ( ! recorded.current ) {
		recordBannerShow( 'trust', bannerViewsCookieName );
		recorded.current = true;
	}
	const labels = parseTrustLabels( trustMessage );
	const cn = isCheckout ? 'cro-blocks-slot cro-checkout-trust cro-blocks-trust cro-blocks-trust-strip' : 'cro-blocks-slot cro-cart-trust cro-blocks-trust cro-blocks-trust-strip';
	return createElement(
		'div',
		{ className: cn },
		createElement(
			'ul',
			{ className: 'cro-blocks-trust__list', style: { listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexWrap: 'wrap', gap: '0.75em 1.25em', fontSize: '0.9em', color: '#555' } },
			labels.map( ( label, i ) =>
				createElement(
					'li',
					{ key: i, className: 'cro-blocks-trust__item', style: { display: 'flex', alignItems: 'center', gap: '0.35em' } },
					createElement( 'span', { className: 'cro-blocks-trust__icon', 'aria-hidden': 'true' }, TRUST_ICONS[i] || '•' ),
					createElement( 'span', { className: 'cro-blocks-trust__label' }, label )
				)
			)
		)
	);
}

// Optional Urgency: "Limited stock" (or custom message) only when enabled and provided via settings. Cart. Respects frequency cap.
function UrgencyMessage( { context, cartOptimizerEnabled, cartSettings, bannerFrequencyCapMax, bannerViewsCookieName } ) {
	const recorded = useRef( false );
	if ( context !== 'woocommerce/cart' || ! cartOptimizerEnabled || ! cartSettings?.show_urgency ) {
		return null;
	}
	if ( ! canShowBanner( 'urgency', bannerFrequencyCapMax || 0, bannerViewsCookieName ) ) return null;
	if ( ! recorded.current ) {
		recordBannerShow( 'urgency', bannerViewsCookieName );
		recorded.current = true;
	}
	const urgencyType = cartSettings.urgency_type || 'demand';
	const customMessage = cartSettings.urgency_message || '';
	const message =
		urgencyType === 'stock'
			? ( customMessage || __( 'Limited stock', 'meyvora-convert' ) )
			: ( customMessage || __( 'Items in your cart are in high demand!', 'meyvora-convert' ) );
	if ( ! message ) return null;
	return createElement(
		'div',
		{ className: 'cro-blocks-slot cro-cart-urgency cro-blocks-urgency' },
		createElement( 'p', {}, message )
	);
}

// GuaranteeNote: shown near place order area (checkout order summary). Checkout only.
function GuaranteeNote( { context, checkoutOptimizerEnabled, checkoutSettings } ) {
	if ( context !== 'woocommerce/checkout' || ! checkoutOptimizerEnabled || ! checkoutSettings?.show_guarantee ) {
		return null;
	}
	const text = checkoutSettings.guarantee_text || __( '30-day money-back guarantee', 'meyvora-convert' );
	if ( ! text ) return null;
	return createElement(
		'div',
		{ className: 'cro-blocks-slot cro-guarantee cro-blocks-guarantee cro-blocks-guarantee-note' },
		createElement( 'span', { className: 'cro-guarantee-icon', 'aria-hidden': 'true' }, '✓' ),
		createElement( 'span', { className: 'cro-guarantee-text' }, text )
	);
}

// OfferBanner: GET /cro/v1/offer on mount and when cart changes; headline + description + Apply; POST /cro/v1/offer/apply on click. Reactive to cart; respects position (cart/checkout/both) and frequency cap.
function OfferBanner( {
	cart,
	context,
	restUrl,
	restNonce,
	offerBannerEnabled,
	enableDynamicOffers,
	offerBannerPosition,
	bannerFrequencyCapMax,
	bannerViewsCookieName,
} ) {
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ applied, setApplied ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ applying, setApplying ] = useState( false );
	const offerRecorded = useRef( false );

	const isCart = context === 'woocommerce/cart';
	const isCheckout = context === 'woocommerce/checkout';
	const showOnCart = offerBannerPosition === 'cart' || offerBannerPosition === 'both';
	const showOnCheckout = offerBannerPosition === 'checkout' || offerBannerPosition === 'both';
	const visible = ( isCart && showOnCart ) || ( isCheckout && showOnCheckout );
	const enabled = !!( enableDynamicOffers !== false && offerBannerEnabled && visible && restUrl );

	const cartKey = ( cart?.cart_hash || cart?.totals?.total_price ) ?? '';
	const fetchOffer = useCallback( () => {
		if ( ! enabled ) return;
		setLoading( true );
		setError( null );
		fetch( restUrl + '/offer', { credentials: 'same-origin' } )
			.then( ( res ) => res.json() )
			.then( ( response ) => {
				setData( response );
				setLoading( false );
			} )
			.catch( ( err ) => {
				setError( err && err.message ? err.message : __( 'Could not load offer.', 'meyvora-convert' ) );
				setData( null );
				setLoading( false );
			} );
	}, [ enabled, restUrl ] );

	useEffect( () => {
		fetchOffer();
	}, [ fetchOffer, cartKey ] );

	const applyCoupon = useCallback( () => {
		const code = data?.coupon_code;
		if ( ! code || ! data?.can_apply || applying ) return;
		setApplying( true );
		setError( null );

		fetch( restUrl + '/offer/apply', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': restNonce || '',
			},
			body: JSON.stringify( { coupon_code: code } ),
		} )
			.then( ( res ) => res.json().then( ( body ) => ( { ok: res.ok, status: res.status, body } ) ) )
			.then( ( { ok, status, body: bodyData } ) => {
				if ( ok && bodyData?.success ) {
					setApplied( true );
					setApplying( false );
					fetchOffer();
					if ( typeof window.dispatchEvent === 'function' ) {
						window.dispatchEvent( new CustomEvent( 'wc_fragments_refreshed' ) );
					}
					return;
				}
				const msg = bodyData?.message || bodyData?.code || 'apply_failed';
				let userMessage = __( 'Could not apply coupon. Please try again.', 'meyvora-convert' );
				if ( msg === 'forbidden' || status === 403 ) {
					userMessage = __( 'This coupon is not assigned to you.', 'meyvora-convert' );
				} else if ( msg === 'invalid_coupon' || msg === 'This coupon is not valid.' || status === 404 ) {
					userMessage = __( 'This coupon is invalid or has expired.', 'meyvora-convert' );
				} else if ( msg === 'rate_limited' || status === 429 ) {
					userMessage = __( 'Too many attempts. Please try again later.', 'meyvora-convert' );
				} else if ( typeof bodyData?.message === 'string' && bodyData.message.length > 0 ) {
					userMessage = bodyData.message;
				}
				setError( userMessage );
				setApplying( false );
			} )
			.catch( ( err ) => {
				setError( err && err.message ? err.message : __( 'Could not apply coupon. Please try again.', 'meyvora-convert' ) );
				setApplying( false );
			} );
	}, [ data, applying, restUrl, restNonce, fetchOffer ] );

	if ( ! enabled ) return null;
	if ( loading && ! data ) {
		return createElement( 'div', { className: 'cro-blocks-slot cro-blocks-offer cro-blocks-offer--loading' }, createElement( 'p', {}, __( 'Loading…', 'meyvora-convert' ) ) );
	}
	if ( ! data?.eligible || ! data?.offer ) return null;
	if ( ! canShowBanner( 'offer', bannerFrequencyCapMax || 0, bannerViewsCookieName ) ) return null;
	if ( ! offerRecorded.current ) {
		recordBannerShow( 'offer', bannerViewsCookieName );
		offerRecorded.current = true;
	}

	const offer = data.offer;
	const headline = offer.headline || __( 'A discount', 'meyvora-convert' );
	const description = offer.description || '';

	if ( applied || ! data.can_apply ) {
		return createElement(
			'div',
			{ className: 'cro-blocks-slot cro-blocks-offer cro-blocks-offer--applied' },
			createElement( 'p', { className: 'cro-blocks-offer__headline' }, headline ),
			createElement( 'p', { className: 'cro-blocks-offer__success' }, __( 'Discount applied.', 'meyvora-convert' ) )
		);
	}

	return createElement(
		'div',
		{ className: 'cro-blocks-slot cro-blocks-offer' },
		createElement( 'p', { className: 'cro-blocks-offer__headline' }, headline ),
		description ? createElement( 'p', { className: 'cro-blocks-offer__description' }, description ) : null,
		createElement(
			'button',
			{
				type: 'button',
				className: 'button cro-blocks-offer__apply',
				onClick: applyCoupon,
				disabled: applying,
			},
			applying ? __( 'Applying…', 'meyvora-convert' ) : __( 'Apply coupon', 'meyvora-convert' )
		),
		error ? createElement( 'p', { className: 'cro-blocks-offer__error', role: 'alert' }, error ) : null
	);
}

// ShippingProgress: "You're X away from free shipping" – uses Store API cart from slot props (updates live). Threshold from settings data. Respects frequency cap.
function ShippingProgress( { cart, freeShippingThreshold, bannerFrequencyCapMax, bannerViewsCookieName } ) {
	const recorded = useRef( false );
	if ( ! cart || ! freeShippingThreshold || freeShippingThreshold <= 0 ) {
		return null;
	}
	if ( ! canShowBanner( 'shipping_bar', bannerFrequencyCapMax || 0, bannerViewsCookieName ) ) {
		return null;
	}
	if ( ! recorded.current ) {
		recordBannerShow( 'shipping_bar', bannerViewsCookieName );
		recorded.current = true;
	}
	const totals = cart.totals || {};
	const totalPrice = parseInt( totals.total_price || '0', 10 ) / 100;
	const minorUnit = parseInt( totals.currency_minor_unit || '2', 10 );
	const total = totalPrice / Math.pow( 10, minorUnit );
	if ( total >= freeShippingThreshold ) {
		return createElement(
			'div',
			{ className: 'cro-blocks-slot cro-blocks-shipping-progress cro-blocks-shipping-achieved' },
			createElement( 'p', { className: 'cro-blocks-shipping-achieved-message' }, __( "You've got free shipping!", 'meyvora-convert' ) )
		);
	}
	const remaining = freeShippingThreshold - total;
	const percentage = Math.min( 100, ( total / freeShippingThreshold ) * 100 );
	const amountFormatted = typeof remaining.toFixed === 'function' ? remaining.toFixed( 2 ) : String( remaining );
	const message = __( "You're {{amount}} away from free shipping", 'meyvora-convert' ).replace( '{{amount}}', amountFormatted );
	return createElement(
		'div',
		{ className: 'cro-blocks-slot cro-blocks-shipping-progress' },
		createElement( 'p', { className: 'cro-blocks-shipping-message' }, message ),
		createElement(
			'div',
			{ className: 'cro-blocks-shipping-bar-wrap' },
			createElement( 'div', {
				className: 'cro-blocks-shipping-bar',
				style: { width: `${ percentage }%` },
			} )
		)
	);
}

// Single fill for ExperimentalOrderMeta: trust, urgency (cart), guarantee (checkout).
// Receives { context, extensions, cart } from the slot (reactive when cart changes).
function OrderMetaFill( props ) {
	const { context = '', cart = null } = props;
	const data = getCROData();
	const {
		cartOptimizerEnabled,
		checkoutOptimizerEnabled,
		cartSettings = {},
		checkoutSettings = {},
	} = data;

	const capMax = data.bannerFrequencyCapMax != null ? data.bannerFrequencyCapMax : 0;
	const capCookie = data.bannerViewsCookieName || 'cro_banner_views';
	const children = [
		createElement( TrustStrip, {
			context,
			cartOptimizerEnabled,
			checkoutOptimizerEnabled,
			cartSettings,
			checkoutSettings,
			bannerFrequencyCapMax: capMax,
			bannerViewsCookieName: capCookie,
		} ),
		createElement( UrgencyMessage, {
			context,
			cartOptimizerEnabled,
			cartSettings,
			bannerFrequencyCapMax: capMax,
			bannerViewsCookieName: capCookie,
		} ),
		createElement( GuaranteeNote, {
			context,
			checkoutOptimizerEnabled,
			checkoutSettings,
		} ),
		createElement( OfferBanner, {
			cart,
			context,
			restUrl: data.restUrl || '',
			restNonce: data.restNonce || '',
			offerBannerEnabled: !! data.offerBannerEnabled,
			enableDynamicOffers: data.enableDynamicOffers !== false,
			offerBannerPosition: data.offerBannerPosition || 'both',
			bannerFrequencyCapMax: capMax,
			bannerViewsCookieName: capCookie,
		} ),
		createElement( ShippingProgress, {
			cart,
			freeShippingThreshold: data.freeShippingThreshold || 0,
			bannerFrequencyCapMax: capMax,
			bannerViewsCookieName: capCookie,
		} ),
	].filter( Boolean );

	if ( children.length === 0 ) return null;

	return createElement(
		'div',
		{ className: 'cro-blocks-order-meta-fill' },
		children
	);
}

// Fill for ExperimentalOrderShippingPackages: ShippingProgress in shipping area (cart sidebar / checkout). Cart from slot = Store API, updates live.
function ShippingPackagesFill( props ) {
	const { cart = null } = props;
	const data = getCROData();
	if ( ! data.cartOptimizerEnabled && ! data.checkoutOptimizerEnabled ) return null;
	const threshold = data.freeShippingThreshold || 0;
	if ( threshold <= 0 ) return null;
	const capMax = data.bannerFrequencyCapMax != null ? data.bannerFrequencyCapMax : 0;
	const capCookie = data.bannerViewsCookieName || 'cro_banner_views';

	return createElement(
		'div',
		{ className: 'cro-blocks-shipping-packages-fill' },
		createElement( ShippingProgress, {
			cart,
			freeShippingThreshold: threshold,
			bannerFrequencyCapMax: capMax,
			bannerViewsCookieName: capCookie,
		} )
	);
}

function render() {
	if ( ! ExperimentalOrderMeta && ! ExperimentalOrderShippingPackages ) {
		return null;
	}

	const data = getCROData();
	const debug = data.debug === true;
	if ( debug && ! _debugLogged ) {
		console.log( 'Meyvora Convert Blocks Extension – settings:', data );
		_debugLogged = true;
	}

	return createElement(
		createElement.Fragment,
		{},
		debug ? createElement( DebugBadge ) : null,
		ExperimentalOrderMeta &&
			createElement(
				ExperimentalOrderMeta,
				{},
				createElement( OrderMetaFill, {} )
			),
		ExperimentalOrderShippingPackages &&
			createElement(
				ExperimentalOrderShippingPackages,
				{},
				createElement( ShippingPackagesFill, {} )
			)
	);
}

// Register for both Cart and Checkout blocks so Slot/Fills render on both.
registerPlugin( 'meyvora-convert-cart-checkout-checkout', {
	render,
	scope: 'woocommerce-checkout',
} );
registerPlugin( 'meyvora-convert-cart-checkout-cart', {
	render,
	scope: 'woocommerce-cart',
} );
