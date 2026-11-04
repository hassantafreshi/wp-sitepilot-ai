( function () {
	'use strict';

	var root = document.querySelector( '[data-sp-integrations]' );
	if ( ! root ) {
		return;
	}

	var dialogs = Array.prototype.slice.call( root.querySelectorAll( '[data-sp-integration-dialog]' ) );
	var triggerByDialog = new Map();

	function dialogFor( id ) {
		return dialogs.find( function ( dialog ) {
			return dialog.getAttribute( 'data-sp-integration-dialog' ) === id;
		} );
	}

	function focusCloseButton( dialog ) {
		var closeButton = dialog.querySelector( '[data-sp-integration-close]' );
		if ( closeButton ) {
			closeButton.focus();
		}
	}

	function openDialog( dialog, trigger ) {
		if ( ! dialog ) {
			return;
		}

		triggerByDialog.set( dialog, trigger );
		if ( typeof dialog.showModal === 'function' ) {
			if ( ! dialog.open ) {
				dialog.showModal();
			}
		} else {
			dialog.setAttribute( 'open', 'open' );
		}
		focusCloseButton( dialog );
	}

	function closeDialog( dialog ) {
		if ( ! dialog ) {
			return;
		}

		if ( typeof dialog.close === 'function' && dialog.open ) {
			dialog.close();
		} else {
			dialog.removeAttribute( 'open' );
		}

		var trigger = triggerByDialog.get( dialog );
		if ( trigger ) {
			trigger.focus();
		}
	}

	root.addEventListener( 'click', function ( event ) {
		var trigger = event.target.closest( '[data-sp-integration-open]' );
		if ( trigger && root.contains( trigger ) ) {
			event.preventDefault();
			openDialog( dialogFor( trigger.getAttribute( 'data-sp-integration-open' ) ), trigger );
			return;
		}

		var close = event.target.closest( '[data-sp-integration-close]' );
		if ( close && root.contains( close ) ) {
			event.preventDefault();
			closeDialog( close.closest( '[data-sp-integration-dialog]' ) );
			return;
		}

		if ( event.target.matches( '[data-sp-integration-dialog]' ) ) {
			closeDialog( event.target );
		}
	} );

	// Another chat or AI plugin is active: hiding the warning is remembered for this
	// administrator on this site (POST /integrations/conflicts/dismiss).
	var cfg = window.AISAAutoPilotAI || {};
	var strings = cfg.i18n || {};

	function nextFocus( banner ) {
		var next = root.querySelector( '[data-sp-conflict]:not([hidden]) [data-sp-conflict-dismiss]' );
		if ( next ) {
			return next;
		}
		return root.querySelector( '.sp-integrations__groups button, .sp-integrations__groups a[href]' ) || banner.parentNode;
	}

	root.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-sp-conflict-dismiss]' );
		if ( ! button || ! cfg.restUrl ) {
			return;
		}
		var banner = button.closest( '[data-sp-conflict]' );
		var error = banner.querySelector( '[data-sp-conflict-error]' );
		var plugins = [];
		try { plugins = JSON.parse( banner.getAttribute( 'data-sp-conflict-plugins' ) || '[]' ); } catch ( e ) { plugins = []; }
		var url = cfg.restUrl + 'integrations/conflicts/dismiss';
		url += ( -1 === url.indexOf( '?' ) ? '?' : '&' ) + '_locale=user';
		button.disabled = true;
		fetch( url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.nonce, 'Content-Type': 'application/json', Accept: 'application/json' },
			body: JSON.stringify( { plugins: plugins } )
		} ).then( function ( response ) {
			return response.json().catch( function () { return {}; } ).then( function ( data ) {
				if ( ! response.ok || ! data.ok ) {
					throw new Error( data.message || '' );
				}
				banner.hidden = true;
				nextFocus( banner ).focus();
			} );
		} ).catch( function ( err ) {
			button.disabled = false;
			error.textContent = ( err && err.message ) || strings.conflictHideFailed || 'The warning could not be hidden. Please try again.';
			error.hidden = false;
		} );
	} );

	dialogs.forEach( function ( dialog ) {
		dialog.addEventListener( 'cancel', function ( event ) {
			event.preventDefault();
			closeDialog( dialog );
		} );
		dialog.addEventListener( 'close', function () {
			var trigger = triggerByDialog.get( dialog );
			if ( trigger && document.activeElement === document.body ) {
				trigger.focus();
			}
		} );
	} );
}() );
