/* global AISAAutoPilotAI */
( function () {
	'use strict';

	var T = ( function () {
		try {
			var node = document.getElementById( 'sp-usage-i18n' );
			return node ? ( JSON.parse( node.textContent || '{}' ) || {} ) : {};
		} catch ( e ) { return {}; }
	}() );

	function t( key, fallback ) { return T[ key ] || fallback || key; }

	function $( id ) { return document.getElementById( id ); }

	function api( path, body ) {
		return fetch( window.AISAAutoPilotAI.restUrl + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': window.AISAAutoPilotAI.nonce,
			},
			body: JSON.stringify( body || {} ),
		} ).then( function ( r ) { return r.json(); } );
	}

	function setStatus( node, message, kind ) {
		if ( ! node ) { return; }
		node.textContent = message;
		node.className = 'sp-status-text' + ( kind ? ' is-' + kind : '' );
	}

	/* ── Save budget ── */
	var saveBtn = $( 'sp-save-budget' );
	if ( saveBtn ) {
		saveBtn.addEventListener( 'click', function () {
			var budget = parseInt( $( 'sp-monthly-budget' ).value, 10 ) || 0;
			var status = $( 'sp-budget-status' );
			setStatus( status, t( 'saving' ) );
			saveBtn.disabled = true;

			api( 'usage/budget', { budget: budget } ).then( function ( r ) {
				if ( r.ok ) {
					setStatus( status, t( 'saved' ), 'ok' );
					window.setTimeout( function () { window.location.reload(); }, 900 );
				} else {
					setStatus( status, r.message || t( 'failed' ), 'err' );
					saveBtn.disabled = false;
				}
			} ).catch( function () {
				setStatus( status, t( 'failed' ), 'err' );
				saveBtn.disabled = false;
			} );
		} );
	}

	/* ── Reset usage ── */
	var resetBtn = $( 'sp-reset-usage' );
	if ( resetBtn ) {
		resetBtn.addEventListener( 'click', function () {
			if ( ! window.confirm( t( 'confirmWipe' ) ) ) { return; }
			var status = $( 'sp-budget-status' );
			setStatus( status, t( 'resetting' ) );
			resetBtn.disabled = true;

			api( 'usage/reset', {} ).then( function ( r ) {
				if ( r.ok ) {
					setStatus( status, t( 'resetDone' ), 'ok' );
					window.setTimeout( function () { window.location.reload(); }, 900 );
				} else {
					setStatus( status, r.message || t( 'failed' ), 'err' );
					resetBtn.disabled = false;
				}
			} ).catch( function () {
				setStatus( status, t( 'failed' ), 'err' );
				resetBtn.disabled = false;
			} );
		} );
	}

	/* ── Refresh ── */
	var refreshBtn = $( 'sp-usage-refresh' );
	if ( refreshBtn ) {
		refreshBtn.addEventListener( 'click', function () {
			refreshBtn.disabled = true;
			window.location.reload();
		} );
	}
}() );
