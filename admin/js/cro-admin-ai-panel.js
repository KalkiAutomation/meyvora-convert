( function ( $ ) {
	'use strict';

	if ( typeof croAiPanel === 'undefined' ) {
		return;
	}

	var c = croAiPanel;
	var storageKey = 'cro_ai_panel_open';
	var root = document.getElementById( 'cro-ai-panel' );
	if ( ! root ) {
		return;
	}

	var drawer = document.getElementById( 'cro-ai-panel-drawer' );
	var toggle = document.getElementById( 'cro-ai-panel-toggle' );
	var dot = document.getElementById( 'cro-ai-panel-status-dot' );
	var topCard = document.getElementById( 'cro-ai-panel-top-card' );
	var cfgMsg = document.getElementById( 'cro-ai-panel-config-msg' );
	var usageEl = document.getElementById( 'cro-ai-panel-usage' );

	function setOpen( open ) {
		if ( ! drawer || ! toggle ) {
			return;
		}
		drawer.hidden = ! open;
		toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		try {
			localStorage.setItem( storageKey, open ? '1' : '0' );
		} catch ( e ) {}
	}

	function restoreOpen() {
		try {
			if ( localStorage.getItem( storageKey ) === '1' ) {
				setOpen( true );
			}
		} catch ( e ) {}
	}

	function modal( title, html, extraBtn ) {
		var wrap = document.getElementById( 'cro-ai-panel-modals' );
		if ( ! wrap ) {
			return;
		}
		var overlay = document.createElement( 'div' );
		overlay.className = 'cro-ai-modal-overlay';
		overlay.innerHTML =
			'<div class="cro-ai-modal" role="dialog" aria-modal="true"><h4></h4><div class="cro-ai-modal-body"></div><div class="cro-ai-modal__actions"><button type="button" class="button cro-ai-modal-close">' +
			escapeHtml( c.strings.close ) +
			'</button></div></div>';
		overlay.querySelector( 'h4' ).textContent = title;
		overlay.querySelector( '.cro-ai-modal-body' ).innerHTML = html;
		var act = overlay.querySelector( '.cro-ai-modal__actions' );
		if ( extraBtn ) {
			act.insertBefore( extraBtn, act.firstChild );
		}
		overlay.addEventListener( 'click', function ( ev ) {
			if ( ev.target === overlay || ev.target.classList.contains( 'cro-ai-modal-close' ) ) {
				wrap.innerHTML = '';
			}
		} );
		wrap.appendChild( overlay );
	}

	function escapeHtml( s ) {
		var d = document.createElement( 'div' );
		d.textContent = s;
		return d.innerHTML;
	}

	function loadData() {
		$.post( c.ajaxUrl, { action: 'cro_load_ai_panel_data', nonce: c.nonce } ).done( function ( res ) {
			if ( ! res.success || ! res.data ) {
				return;
			}
			var d = res.data;
			if ( d.ai_ready ) {
				if ( dot ) {
					dot.classList.add( 'is-live' );
				}
				if ( cfgMsg ) {
					cfgMsg.textContent = c.strings.aiReady;
				}
			} else {
				if ( cfgMsg ) {
					cfgMsg.innerHTML = c.settingsAiLink;
				}
			}
			if ( usageEl && d.usage_note ) {
				usageEl.textContent = d.usage_note;
			}
			if ( topCard && d.top_insight && d.top_insight.title ) {
				var ins = d.top_insight;
				var url = ins.fix_url || '#';
				topCard.innerHTML =
					'<h4>' +
					escapeHtml( ins.title ) +
					'</h4><p>' +
					escapeHtml( ins.description ) +
					'</p><a class="button button-small" href="' +
					escapeHtml( url ) +
					'">' +
					escapeHtml( ins.fix_label || c.strings.apply ) +
					'</a>';
			} else if ( topCard ) {
				topCard.innerHTML = '<p class="cro-ai-panel__muted">' + escapeHtml( c.strings.noInsight ) + '</p>';
			}
		} );
	}

	if ( toggle && drawer ) {
		toggle.addEventListener( 'click', function () {
			setOpen( drawer.hidden );
		} );
	}
	restoreOpen();
	loadData();

	$( '#cro-ai-panel-run-analysis' ).on( 'click', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true );
		$.post( c.ajaxUrl, {
			action: 'cro_ai_analyse',
			_wpnonce: c.nonceAiAnalyse,
			days: 30,
			refresh: 1,
		} )
			.done( function ( res ) {
				if ( res.success ) {
					window.location.href = c.insightsUrl;
				} else {
					window.alert( ( res.data && res.data.message ) || c.strings.error );
				}
			} )
			.fail( function () {
				window.alert( c.strings.error );
			} )
			.always( function () {
				$btn.prop( 'disabled', false );
			} );
	} );

	$( '#cro-ai-panel-suggest-offer' ).on( 'click', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true );
		$.post( c.ajaxUrl, {
			action: 'cro_ai_suggest_offer',
			_wpnonce: c.nonceSuggestOffer,
		} )
			.done( function ( res ) {
				if ( ! res.success || ! res.data || ! res.data.suggestion ) {
					window.alert( ( res.data && res.data.message ) || c.strings.error );
					return;
				}
				var s = res.data.suggestion;
				var body =
					'<p><strong>' +
					escapeHtml( s.name || s.title || '' ) +
					'</strong></p><p>' +
					escapeHtml( s.rationale || s.summary || s.description || '' ) +
					'</p>';
				var a = document.createElement( 'a' );
				a.className = 'button button-primary';
				a.href = c.offersUrl;
				a.textContent = c.strings.createOffer;
				modal( c.strings.suggestionTitle, body, a );
			} )
			.fail( function () {
				window.alert( c.strings.error );
			} )
			.always( function () {
				$btn.prop( 'disabled', false );
			} );
	} );

	$( '#cro-ai-panel-generate-copy' ).on( 'click', function () {
		var goal = ( $( '#cro-ai-panel-copy-goal' ).val() || '' ).trim();
		if ( ! goal ) {
			window.alert( c.strings.needGoal );
			return;
		}
		var $btn = $( this );
		var $out = $( '#cro-ai-panel-copy-result' );
		$btn.prop( 'disabled', true );
		$.post( c.ajaxUrl, {
			action: 'cro_ai_generate_copy',
			_wpnonce: c.nonceGenerateCopy,
			goal: goal,
		} )
			.done( function ( res ) {
				if ( ! res.success || ! res.data ) {
					window.alert( ( res.data && res.data.message ) || c.strings.error );
					return;
				}
				var copy = res.data;
				var headline = copy.headline || copy.headline_text || '';
				var body = copy.body || copy.body_text || '';
				$out.html(
					'<p><strong>' +
						escapeHtml( headline ) +
						'</strong></p><p>' +
						escapeHtml( body ) +
						'</p><button type="button" class="button button-small" id="cro-ai-copy-use">' +
						escapeHtml( c.strings.useThis ) +
						'</button>'
				).prop( 'hidden', false );
			} )
			.fail( function () {
				window.alert( c.strings.error );
			} )
			.always( function () {
				$btn.prop( 'disabled', false );
			} );
	} );

	$( document ).on( 'click', '#cro-ai-copy-use', function () {
		window.location.href = c.campaignNewUrl;
	} );
} )( jQuery );
