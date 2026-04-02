( function ( $ ) {
	'use strict';

	if ( typeof meyvcDashboardActions !== 'undefined' ) {
		var d = meyvcDashboardActions;

		function goInsights() {
			window.location.href = d.insightsUrl;
		}

		$( '#meyvc-dash-run-ai-analysis' ).on( 'click', function () {
			var $b = $( this );
			$b.prop( 'disabled', true );
			$.post( d.ajaxUrl, {
				action: 'meyvc_ai_analyse',
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

		$( '#meyvc-dash-suggest-offer' ).on( 'click', function () {
			var $b = $( this );
			$b.prop( 'disabled', true );
			$.post( d.ajaxUrl, {
				action: 'meyvc_ai_suggest_offer',
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

	if ( typeof meyvcDashboardLive !== 'undefined' ) {
		var lv = meyvcDashboardLive;
		var timer = null;
		var INTERVAL = 30000;
		function pad( n ) {
			return String( n ).padStart( 2, '0' );
		}
		function fetchLive() {
			$.post( lv.ajaxUrl, { action: 'meyvc_live_stats', nonce: lv.nonce }, function ( r ) {
				if ( ! r || ! r.success || ! r.data ) {
					return;
				}
				var dt = r.data;
				$( '#meyvc-live-impressions' ).text( dt.impressions || 0 );
				$( '#meyvc-live-conversions' ).text( dt.conversions || 0 );
				$( '#meyvc-live-emails' ).text( dt.emails || 0 );
				$( '#meyvc-live-carts' ).text( dt.carts_recovered || 0 );
				var n = new Date();
				$( '#meyvc-live-updated' ).text(
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
