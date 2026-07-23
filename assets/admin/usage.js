/* global ajaxurl, SitePilotAI */
( function () {
	'use strict';

	var $ = function ( id ) { return document.getElementById( id ); };

	function api( path, body ) {
		return fetch( window.SitePilotAI.restUrl + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': window.SitePilotAI.nonce,
			},
			body: JSON.stringify( body || {} ),
		} ).then( function ( r ) { return r.json().then( function ( d ) { return d; } ); } );
	}

	function setStatus( el, msg, kind ) {
		el.textContent = msg;
		el.className = 'sp-status-text';
		if ( kind ) { el.classList.add( 'sp-status-text--' + kind ); }
		if ( kind === 'loading' ) { el.textContent = '⏳ ' + msg; }
		if ( kind === 'error' ) { el.style.color = '#dc2626'; }
	}

	/* ── Save Budget ── */
	var saveBtn = $( 'sp-save-budget' );
	if ( saveBtn ) {
		saveBtn.addEventListener( 'click', function () {
			var budget = parseInt( $( 'sp-monthly-budget' ).value, 10 ) || 0;
			var status = $( 'sp-budget-status' );
			setStatus( status, 'Saving…', 'loading' );
			saveBtn.disabled = true;

			api( 'usage/budget', { budget: budget } )
				.then( function ( r ) {
					if ( r.ok ) {
						setStatus( status, '✅ Budget saved!', '' );
						setTimeout( function () { location.reload(); }, 1000 );
					} else {
						setStatus( status, '❌ ' + ( r.message || 'Error' ), 'error' );
						saveBtn.disabled = false;
					}
				} )
				.catch( function () {
					setStatus( status, '❌ Network error', 'error' );
					saveBtn.disabled = false;
				} );
		} );
	}

	/* ── Reset Usage ── */
	var resetBtn = $( 'sp-reset-usage' );
	if ( resetBtn ) {
		resetBtn.addEventListener( 'click', function () {
			if ( ! confirm( 'Are you sure? This will permanently delete all usage data.' ) ) {
				return;
			}
			var status = $( 'sp-budget-status' );
			setStatus( status, 'Resetting…', 'loading' );
			resetBtn.disabled = true;

			api( 'usage/reset', {} )
				.then( function ( r ) {
					if ( r.ok ) {
						setStatus( status, '✅ Usage data reset!', '' );
						setTimeout( function () { location.reload(); }, 1000 );
					} else {
						setStatus( status, '❌ ' + ( r.message || 'Error' ), 'error' );
						resetBtn.disabled = false;
					}
				} )
				.catch( function () {
					setStatus( status, '❌ Network error', 'error' );
					resetBtn.disabled = false;
				} );
		} );
	}

	/* ── Refresh ── */
	var refreshBtn = $( 'sp-usage-refresh' );
	if ( refreshBtn ) {
		refreshBtn.addEventListener( 'click', function () {
			refreshBtn.disabled = true;
			var icon = refreshBtn.querySelector( '.dashicons' );
			if ( icon ) { icon.classList.add( 'sp-spin' ); }
			setTimeout( function () { location.reload(); }, 200 );
		} );
	}
} )();
