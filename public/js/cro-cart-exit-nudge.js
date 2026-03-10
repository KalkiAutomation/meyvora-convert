/**
 * Cart/checkout exit-intent nudge: "Complete your order now — your discount is ready".
 * Once per session, mobile-safe (desktop = mouseleave; mobile = once after delay).
 *
 * @package Meyvora_Convert
 */
(function () {
	'use strict';

	const STORAGE_KEY = 'cro_cart_exit_nudge_shown';
	const MOBILE_DELAY_MS = 15000;

	function isMobile() {
		return /Android|iPhone|iPad|iPod|webOS|BlackBerry|IEMobile|Opera Mini/i.test( navigator.userAgent ) || ( 'ontouchstart' in window );
	}

	function wasAlreadyShown() {
		try {
			return sessionStorage.getItem( STORAGE_KEY ) === '1';
		} catch ( e ) {
			return false;
		}
	}

	function markShown() {
		try {
			sessionStorage.setItem( STORAGE_KEY, '1' );
		} catch ( e ) {}
	}

	function showNudge( config ) {
		if ( ! config || ! config.message || ! config.checkoutUrl ) return;
		if ( wasAlreadyShown() ) return;
		markShown();

		const overlay = document.createElement( 'div' );
		overlay.className = 'cro-exit-nudge';
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-label', config.message );

		const box = document.createElement( 'div' );
		box.className = 'cro-exit-nudge__box';

		const p = document.createElement( 'p' );
		p.className = 'cro-exit-nudge__message';
		p.textContent = config.message;

		const cta = document.createElement( 'a' );
		cta.href = config.checkoutUrl || '#';
		cta.className = 'cro-exit-nudge__cta button';
		cta.textContent = config.ctaText || 'Complete order';
		if ( cta.href === '#' || cta.href.slice( -1 ) === '#' ) {
			cta.setAttribute( 'role', 'button' );
		}

		const close = document.createElement( 'button' );
		close.type = 'button';
		close.className = 'cro-exit-nudge__close';
		close.setAttribute( 'aria-label', 'Close' );
		close.innerHTML = '&times;';

		box.appendChild( p );
		box.appendChild( cta );
		box.appendChild( close );
		overlay.appendChild( box );

		function closeNudge() {
			overlay.classList.remove( 'cro-exit-nudge--visible' );
			setTimeout( function () {
				if ( overlay.parentNode ) overlay.parentNode.removeChild( overlay );
			}, 300 );
		}

		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target === overlay || e.target === close ) {
				e.preventDefault();
				closeNudge();
			}
		} );
		close.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			closeNudge();
		} );
		cta.addEventListener( 'click', function ( e ) {
			if ( cta.getAttribute( 'href' ) === '#' ) {
				e.preventDefault();
				closeNudge();
			}
		} );

		document.body.appendChild( overlay );
		requestAnimationFrame( function () {
			overlay.classList.add( 'cro-exit-nudge--visible' );
		} );
	}

	function init( config ) {
		if ( ! config || ! config.enabled ) return;
		if ( wasAlreadyShown() ) return;

		const mobile = isMobile();

		if ( mobile ) {
			setTimeout( function () {
				if ( wasAlreadyShown() ) return;
				showNudge( config );
			}, MOBILE_DELAY_MS );
			return;
		}

		document.addEventListener( 'mouseleave', function onLeave( e ) {
			if ( e.clientY <= 0 ) {
				document.removeEventListener( 'mouseleave', onLeave );
				showNudge( config );
			}
		}, false );
	}

	if ( typeof window.croCartExitNudgeConfig !== 'undefined' ) {
		init( window.croCartExitNudgeConfig );
	}
})();
