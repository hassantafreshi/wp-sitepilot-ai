/* global AISAAutoPilotAI */
/**
 * WP-11 — run diagnostics. The only outbound call is one short request to the
 * administrator's own AI provider; everything else is already rendered server-side.
 */
( function () {
	'use strict';

	var button = document.getElementById( 'sp-run-diagnostics' );
	if ( ! button ) { return; }

	var cfg = window.AISAAutoPilotAI || {};
	var T = ( function () {
		try {
			var node = document.getElementById( 'sp-conn-i18n' );
			return node ? ( JSON.parse( node.textContent || '{}' ) || {} ) : {};
		} catch ( e ) { return {}; }
	}() );

	function t( key, fallback ) { return T[ key ] || fallback || key; }

	var label = button.querySelector( 'span' );
	var summary = document.getElementById( 'sp-diag-summary' );

	button.addEventListener( 'click', function () {
		button.disabled = true;
		if ( label ) { label.textContent = t( 'busy' ); }
		if ( summary ) {
			summary.className = 'sp-alert sp-alert--busy';
			var text = summary.querySelector( 'span' );
			if ( text ) { text.textContent = t( 'checking' ); }
		}

		fetch( cfg.restUrl + 'provider/test', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( {} ),
		} ).then( function ( r ) { return r.json(); } ).then( function ( data ) {
			if ( summary ) {
				summary.className = 'sp-alert sp-alert--' + ( data.ok ? 'ok' : 'err' );
				var text = summary.querySelector( 'span' );
				if ( text ) { text.textContent = data.ok ? t( 'ok' ) : ( data.message || t( 'failed' ) ); }
			}
			// Reload so every dependency row reflects the fresh check.
			window.setTimeout( function () { window.location.reload(); }, data.ok ? 700 : 1600 );
		} ).catch( function () {
			button.disabled = false;
			if ( label ) { label.textContent = t( 'button' ); }
			if ( summary ) {
				summary.className = 'sp-alert sp-alert--err';
				var text = summary.querySelector( 'span' );
				if ( text ) { text.textContent = t( 'failed' ); }
			}
		} );
	} );
}() );
