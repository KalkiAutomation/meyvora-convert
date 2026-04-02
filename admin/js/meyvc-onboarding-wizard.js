( function ( $ ) {
	'use strict';

	var root = document.getElementById( 'meyvc-onboarding-wizard' );
	if ( ! root || typeof meyvcOnboardingWizard === 'undefined' ) {
		return;
	}

	var cfg = meyvcOnboardingWizard;
	var step = 1;
	var goal = '';
	var profile = {};
	var checklistByGoal = cfg.checklists || {};

	function persistState() {
		$.post( cfg.ajaxUrl, {
			action: 'meyvc_onboarding_save_state',
			nonce: cfg.nonce,
			state: JSON.stringify( {
				step: step,
				goal: goal,
				profile: profile,
			} ),
		} );
	}

	function setStep( n ) {
		step = n;
		root.querySelectorAll( '.meyvc-ob-step' ).forEach( function ( el ) {
			var s = parseInt( el.getAttribute( 'data-step' ), 10 );
			var active = s === n;
			el.hidden = ! active;
			el.classList.toggle( 'is-active', active );
		} );
		var pct = ( n / 4 ) * 100;
		var fill = root.querySelector( '.meyvc-ob-progress-fill' );
		var sn = root.querySelector( '.meyvc-ob-step-num' );
		if ( fill ) {
			fill.style.width = Math.max( 25, pct ) + '%';
		}
		if ( sn ) {
			sn.textContent = String( n );
		}
		persistState();
	}

	function goalFromForm() {
		var r = root.querySelector( 'input[name="meyvc_ob_goal"]:checked' );
		return r ? r.value : '';
	}

	function profileFromForm() {
		return {
			store_type: ( root.querySelector( '#meyvc_ob_store_type' ) || {} ).value || '',
			aov_range: ( root.querySelector( '#meyvc_ob_aov' ) || {} ).value || '',
			monthly_visitors: ( root.querySelector( '#meyvc_ob_visitors' ) || {} ).value || '',
		};
	}

	function validateStep2() {
		var p = profileFromForm();
		return p.store_type && p.aov_range && p.monthly_visitors;
	}

	function bind() {
		root.addEventListener( 'change', function () {
			goal = goalFromForm();
			var n1 = root.querySelector( '.meyvc-ob-step--1 .meyvc-ob-next' );
			if ( n1 ) {
				n1.disabled = ! goal;
			}
		} );

		root.querySelectorAll( '.meyvc-ob-step--2 select' ).forEach( function ( sel ) {
			sel.addEventListener( 'change', function () {
				var n2 = root.querySelector( '.meyvc-ob-step--2 .meyvc-ob-next' );
				if ( n2 ) {
					n2.disabled = ! validateStep2();
				}
			} );
		} );

		root.querySelector( '.meyvc-ob-step--1 .meyvc-ob-next' ).addEventListener( 'click', function () {
			goal = goalFromForm();
			if ( ! goal ) {
				return;
			}
			profile = profileFromForm();
			setStep( 2 );
		} );

		root.querySelector( '.meyvc-ob-step--2 .meyvc-ob-back' ).addEventListener( 'click', function () {
			setStep( 1 );
		} );

		root.querySelector( '.meyvc-ob-step--2 .meyvc-ob-next' ).addEventListener( 'click', function () {
			if ( ! validateStep2() ) {
				return;
			}
			profile = profileFromForm();
			setStep( 3 );
			// Reset step 3 UI
			root.querySelector( '.meyvc-ob-configuring' ).classList.add( 'meyvc-is-hidden' );
			root.querySelector( '.meyvc-ob-summary' ).classList.add( 'meyvc-is-hidden' );
			root.querySelector( '.meyvc-ob-step3-start' ).classList.remove( 'meyvc-is-hidden' );
		} );

		root.querySelector( '.meyvc-ob-step--3 .meyvc-ob-back' ).addEventListener( 'click', function () {
			setStep( 2 );
		} );

		root.querySelector( '.meyvc-ob-run-config' ).addEventListener( 'click', function () {
			root.querySelector( '.meyvc-ob-step3-start' ).classList.add( 'meyvc-is-hidden' );
			root.querySelector( '.meyvc-ob-configuring' ).classList.remove( 'meyvc-is-hidden' );
			goal = goalFromForm();
			profile = profileFromForm();

			$.post( cfg.ajaxUrl, {
				action: 'meyvc_onboarding_configure',
				nonce: cfg.nonce,
				goal: goal,
				profile: JSON.stringify( profile ),
			} )
				.done( function ( res ) {
					root.querySelector( '.meyvc-ob-configuring' ).classList.add( 'meyvc-is-hidden' );
					root.querySelector( '.meyvc-ob-summary' ).classList.remove( 'meyvc-is-hidden' );
					var list = root.querySelector( '.meyvc-ob-summary-list' );
					list.innerHTML = '';
					var lines = ( res.success && res.data && res.data.configured ) ? res.data.configured : [];
					if ( ! lines.length ) {
						lines = [ cfg.strings.error ];
					}
					lines.forEach( function ( line ) {
						var li = document.createElement( 'li' );
						li.textContent = line;
						list.appendChild( li );
					} );
				} )
				.fail( function () {
					root.querySelector( '.meyvc-ob-configuring' ).classList.add( 'meyvc-is-hidden' );
					root.querySelector( '.meyvc-ob-step3-start' ).classList.remove( 'meyvc-is-hidden' );
					window.alert( cfg.strings.ajaxFail );
				} );
		} );

		root.querySelector( '.meyvc-ob-summary .meyvc-ob-next' ).addEventListener( 'click', function () {
			buildChecklist();
			setStep( 4 );
		} );

		root.querySelector( '.meyvc-ob-view-dashboard' ).addEventListener( 'click', function () {
			$.post( cfg.ajaxUrl, {
				action: 'meyvc_onboarding_finish',
				nonce: cfg.nonce,
			} ).done( function ( res ) {
				if ( res.success && res.data && res.data.redirect ) {
					window.location.href = res.data.redirect;
				} else {
					window.location.href = cfg.dashboardUrl;
				}
			} );
		} );
	}

	function buildChecklist() {
		var ul = document.getElementById( 'meyvc-ob-checklist' );
		if ( ! ul ) {
			return;
		}
		ul.innerHTML = '';
		var items = checklistByGoal[ goal ] || checklistByGoal.default || [];
		items.forEach( function ( text ) {
			var li = document.createElement( 'li' );
			var icon = document.createElement( 'span' );
			icon.className = 'dashicons dashicons-yes-alt';
			icon.setAttribute( 'aria-hidden', 'true' );
			li.appendChild( icon );
			var span = document.createElement( 'span' );
			span.textContent = text;
			li.appendChild( span );
			ul.appendChild( li );
		} );
	}

	setStep( 1 );
	bind();
} )( jQuery );
