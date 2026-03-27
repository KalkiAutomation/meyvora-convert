( function ( $ ) {
	'use strict';

	var root = document.getElementById( 'cro-onboarding-wizard' );
	if ( ! root || typeof croOnboardingWizard === 'undefined' ) {
		return;
	}

	var cfg = croOnboardingWizard;
	var step = 1;
	var goal = '';
	var profile = {};
	var checklistByGoal = cfg.checklists || {};

	function persistState() {
		$.post( cfg.ajaxUrl, {
			action: 'cro_onboarding_save_state',
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
		root.querySelectorAll( '.cro-ob-step' ).forEach( function ( el ) {
			var s = parseInt( el.getAttribute( 'data-step' ), 10 );
			var active = s === n;
			el.hidden = ! active;
			el.classList.toggle( 'is-active', active );
		} );
		var pct = ( n / 4 ) * 100;
		var fill = root.querySelector( '.cro-ob-progress-fill' );
		var sn = root.querySelector( '.cro-ob-step-num' );
		if ( fill ) {
			fill.style.width = Math.max( 25, pct ) + '%';
		}
		if ( sn ) {
			sn.textContent = String( n );
		}
		persistState();
	}

	function goalFromForm() {
		var r = root.querySelector( 'input[name="cro_ob_goal"]:checked' );
		return r ? r.value : '';
	}

	function profileFromForm() {
		return {
			store_type: ( root.querySelector( '#cro_ob_store_type' ) || {} ).value || '',
			aov_range: ( root.querySelector( '#cro_ob_aov' ) || {} ).value || '',
			monthly_visitors: ( root.querySelector( '#cro_ob_visitors' ) || {} ).value || '',
		};
	}

	function validateStep2() {
		var p = profileFromForm();
		return p.store_type && p.aov_range && p.monthly_visitors;
	}

	function bind() {
		root.addEventListener( 'change', function () {
			goal = goalFromForm();
			var n1 = root.querySelector( '.cro-ob-step--1 .cro-ob-next' );
			if ( n1 ) {
				n1.disabled = ! goal;
			}
		} );

		root.querySelectorAll( '.cro-ob-step--2 select' ).forEach( function ( sel ) {
			sel.addEventListener( 'change', function () {
				var n2 = root.querySelector( '.cro-ob-step--2 .cro-ob-next' );
				if ( n2 ) {
					n2.disabled = ! validateStep2();
				}
			} );
		} );

		root.querySelector( '.cro-ob-step--1 .cro-ob-next' ).addEventListener( 'click', function () {
			goal = goalFromForm();
			if ( ! goal ) {
				return;
			}
			profile = profileFromForm();
			setStep( 2 );
		} );

		root.querySelector( '.cro-ob-step--2 .cro-ob-back' ).addEventListener( 'click', function () {
			setStep( 1 );
		} );

		root.querySelector( '.cro-ob-step--2 .cro-ob-next' ).addEventListener( 'click', function () {
			if ( ! validateStep2() ) {
				return;
			}
			profile = profileFromForm();
			setStep( 3 );
			// Reset step 3 UI
			root.querySelector( '.cro-ob-configuring' ).classList.add( 'cro-is-hidden' );
			root.querySelector( '.cro-ob-summary' ).classList.add( 'cro-is-hidden' );
			root.querySelector( '.cro-ob-step3-start' ).classList.remove( 'cro-is-hidden' );
		} );

		root.querySelector( '.cro-ob-step--3 .cro-ob-back' ).addEventListener( 'click', function () {
			setStep( 2 );
		} );

		root.querySelector( '.cro-ob-run-config' ).addEventListener( 'click', function () {
			root.querySelector( '.cro-ob-step3-start' ).classList.add( 'cro-is-hidden' );
			root.querySelector( '.cro-ob-configuring' ).classList.remove( 'cro-is-hidden' );
			goal = goalFromForm();
			profile = profileFromForm();

			$.post( cfg.ajaxUrl, {
				action: 'cro_onboarding_configure',
				nonce: cfg.nonce,
				goal: goal,
				profile: JSON.stringify( profile ),
			} )
				.done( function ( res ) {
					root.querySelector( '.cro-ob-configuring' ).classList.add( 'cro-is-hidden' );
					root.querySelector( '.cro-ob-summary' ).classList.remove( 'cro-is-hidden' );
					var list = root.querySelector( '.cro-ob-summary-list' );
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
					root.querySelector( '.cro-ob-configuring' ).classList.add( 'cro-is-hidden' );
					root.querySelector( '.cro-ob-step3-start' ).classList.remove( 'cro-is-hidden' );
					window.alert( cfg.strings.ajaxFail );
				} );
		} );

		root.querySelector( '.cro-ob-summary .cro-ob-next' ).addEventListener( 'click', function () {
			buildChecklist();
			setStep( 4 );
		} );

		root.querySelector( '.cro-ob-view-dashboard' ).addEventListener( 'click', function () {
			$.post( cfg.ajaxUrl, {
				action: 'cro_onboarding_finish',
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
		var ul = document.getElementById( 'cro-ob-checklist' );
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
