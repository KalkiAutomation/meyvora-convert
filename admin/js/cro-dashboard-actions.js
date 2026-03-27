( function ( $ ) {
	'use strict';

	if ( typeof croDashboardActions !== 'undefined' ) {
		var d = croDashboardActions;

		function goInsights() {
			window.location.href = d.insightsUrl;
		}

		$( '#cro-dash-run-ai-analysis' ).on( 'click', function () {
			var $b = $( this );
			$b.prop( 'disabled', true );
			$.post( d.ajaxUrl, {
				action: 'cro_ai_analyse',
				_wpnonce: d.nonceAiAnalyse,
				days: 30,
				refresh: 1,
			} )
				.done( function ( res ) {
					if ( res.success ) {
						goInsights();
					} else {
						window.alert( ( res.data && res.data.message ) || d.strings.error );
					}
				} )
				.fail( function () {
					window.alert( d.strings.error );
				} )
				.always( function () {
					$b.prop( 'disabled', false );
				} );
		} );

		$( '#cro-dash-suggest-offer' ).on( 'click', function () {
			var $b = $( this );
			$b.prop( 'disabled', true );
			$.post( d.ajaxUrl, {
				action: 'cro_ai_suggest_offer',
				_wpnonce: d.nonceSuggestOffer,
			} )
				.done( function ( res ) {
					if ( ! res.success || ! res.data || ! res.data.suggestion ) {
						window.alert( ( res.data && res.data.message ) || d.strings.error );
						return;
					}
					var s = res.data.suggestion;
					var msg = ( s.name || '' ) + '\n\n' + ( s.rationale || '' );
					window.alert( msg );
					if ( window.confirm( d.strings.openOffers ) ) {
						window.location.href = d.offersUrl;
					}
				} )
				.fail( function () {
					window.alert( d.strings.error );
				} )
				.always( function () {
					$b.prop( 'disabled', false );
				} );
		} );
	}

	if ( typeof croDashboardLive !== 'undefined' ) {
		var lv = croDashboardLive;
		var timer = null;
		var INTERVAL = 30000;
		function pad( n ) {
			return String( n ).padStart( 2, '0' );
		}
		function fetchLive() {
			$.post( lv.ajaxUrl, { action: 'cro_live_stats', nonce: lv.nonce }, function ( r ) {
				if ( ! r || ! r.success || ! r.data ) {
					return;
				}
				var dt = r.data;
				$( '#cro-live-impressions' ).text( dt.impressions || 0 );
				$( '#cro-live-conversions' ).text( dt.conversions || 0 );
				$( '#cro-live-emails' ).text( dt.emails || 0 );
				$( '#cro-live-carts' ).text( dt.carts_recovered || 0 );
				var n = new Date();
				$( '#cro-live-updated' ).text(
					'Updated ' + pad( n.getHours() ) + ':' + pad( n.getMinutes() ) + ':' + pad( n.getSeconds() )
				);
			} );
		}
		function startPolling() {
			if ( timer ) {
				return;
			}
			fetchLive();
			timer = setInterval( fetchLive, INTERVAL );
		}
		function stopPolling() {
			if ( ! timer ) {
				return;
			}
			clearInterval( timer );
			timer = null;
		}
		document.addEventListener( 'visibilitychange', function () {
			if ( document.hidden ) {
				stopPolling();
			} else {
				startPolling();
			}
		} );
		$( startPolling );
	}
} )( jQuery );
