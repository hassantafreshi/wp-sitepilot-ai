/* global AISAAutoPilotAI */
( function () {
	'use strict';

	var root = document.querySelector( '[data-sp-usage]' );
	if ( ! root ) { return; }

	var T = ( function () {
		try {
			var node = document.getElementById( 'sp-usage-i18n' );
			return node ? ( JSON.parse( node.textContent || '{}' ) || {} ) : {};
		} catch ( e ) { return {}; }
	}() );

	function t( key, fallback ) { return T[ key ] || fallback || key; }
	function $( id ) { return document.getElementById( id ); }

	function api( path, body ) {
		if ( ! window.AISAAutoPilotAI || ! window.AISAAutoPilotAI.restUrl ) {
			return Promise.reject( new Error( 'REST configuration is unavailable.' ) );
		}

		return fetch( window.AISAAutoPilotAI.restUrl + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': window.AISAAutoPilotAI.nonce,
			},
			body: JSON.stringify( body || {} ),
		} ).then( function ( response ) {
			return response.json().catch( function () { return {}; } ).then( function ( data ) {
				return {
					ok: response.ok && !! data.ok,
					data: data || {},
				};
			} );
		} );
	}

	function setStatus( node, message, kind ) {
		if ( ! node ) { return; }
		node.textContent = message;
		node.className = 'sp-status-text' + ( kind ? ' is-' + kind : '' );
	}

	var editor = $( 'sp-usage-cap-editor' );
	var input = $( 'sp-monthly-budget' );
	var saveBtn = $( 'sp-save-budget' );
	var cancelBtn = $( 'sp-usage-cap-cancel' );
	var resetBtn = $( 'sp-reset-usage' );
	var status = $( 'sp-budget-status' );
	var openers = root.querySelectorAll( '[data-sp-usage-cap-open], #sp-usage-cap-open' );
	var lastOpener = null;
	var savedValue = input ? input.value : '0';
	var busy = false;

	function setExpanded( expanded ) {
		Array.prototype.forEach.call( openers, function ( opener ) {
			opener.setAttribute( 'aria-controls', 'sp-usage-cap-editor' );
			opener.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
		} );
	}

	function openEditor( opener ) {
		if ( busy || ! editor || ! input ) { return; }
		lastOpener = opener || document.activeElement;
		input.value = savedValue;
		setStatus( status, '' );
		editor.hidden = false;
		setExpanded( true );
		window.setTimeout( function () {
			input.focus( { preventScroll: true } );
		}, 0 );
	}

	function closeEditor( restoreValue ) {
		if ( ! editor ) { return; }
		if ( restoreValue && input ) { input.value = savedValue; }
		editor.hidden = true;
		setExpanded( false );
		if ( lastOpener && 'function' === typeof lastOpener.focus ) {
			lastOpener.focus();
		}
	}

	function setBusy( isBusy ) {
		busy = !! isBusy;
		if ( saveBtn ) { saveBtn.disabled = busy; }
		if ( cancelBtn ) { cancelBtn.disabled = busy; }
		if ( resetBtn ) { resetBtn.disabled = busy; }
		if ( input ) { input.disabled = busy; }
		Array.prototype.forEach.call( openers, function ( opener ) { opener.disabled = busy; } );
	}

	function budgetValue() {
		var raw = input ? input.value.trim() : '';
		if ( ! /^\d+$/.test( raw ) ) { return null; }
		var value = Number( raw );
		var maximum = Number( root.getAttribute( 'data-sp-usage-max-budget' ) || '1000000000' );
		return Number.isSafeInteger( value ) && value >= 0 && value <= maximum ? value : null;
	}

	Array.prototype.forEach.call( openers, function ( opener ) {
		opener.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			openEditor( opener );
		} );
	} );
	setExpanded( false );

	if ( cancelBtn ) {
		cancelBtn.addEventListener( 'click', function () {
			if ( busy ) { return; }
			closeEditor( true );
		} );
	}

	root.addEventListener( 'keydown', function ( event ) {
		if ( ! busy && 'Escape' === event.key && editor && ! editor.hidden ) {
			event.preventDefault();
			closeEditor( true );
		}
	} );

	if ( saveBtn ) {
		saveBtn.addEventListener( 'click', function () {
			var budget = budgetValue();
			if ( null === budget ) {
				setStatus( status, t( 'invalidBudget', 'Enter a whole number of zero or more tokens.' ), 'err' );
				if ( input ) { input.focus(); }
				return;
			}

			setStatus( status, t( 'saving', 'Saving...' ) );
			setBusy( true );
			api( 'usage/budget', { budget: budget } ).then( function ( result ) {
				if ( result.ok ) {
					savedValue = String( budget );
					setStatus( status, t( 'saved', 'Token cap saved.' ), 'ok' );
					window.setTimeout( function () { window.location.reload(); }, 700 );
					return;
				}

				setStatus( status, result.data.message || t( 'failed', 'Could not save the token cap.' ), 'err' );
				setBusy( false );
			} ).catch( function () {
				setStatus( status, t( 'failed', 'Could not save the token cap.' ), 'err' );
				setBusy( false );
			} );
		} );
	}

	if ( resetBtn ) {
		resetBtn.addEventListener( 'click', function () {
			if ( ! window.confirm( t( 'confirmWipe', 'Reset only this month\'s token usage? This cannot be undone.' ) ) ) { return; }

			setStatus( status, t( 'resetting', 'Resetting...' ) );
			setBusy( true );
			api( 'usage/reset', {} ).then( function ( result ) {
				if ( result.ok ) {
					setStatus( status, t( 'resetDone', 'This month\'s usage was reset.' ), 'ok' );
					window.setTimeout( function () { window.location.reload(); }, 700 );
					return;
				}

				setStatus( status, result.data.message || t( 'failed', 'Could not save the token cap.' ), 'err' );
				setBusy( false );
			} ).catch( function () {
				setStatus( status, t( 'failed', 'Could not save the token cap.' ), 'err' );
				setBusy( false );
			} );
		} );
	}
}() );
