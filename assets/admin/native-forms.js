/**
 * Your forms (native AISAAutoPilot forms list).
 *
 * Copies a form's shortcode and sends the delivery test email through the
 * protected REST route. Nothing else on this screen changes data.
 */
( function () {
	'use strict';

	var cfg = window.AISAAutoPilotAI || {};
	var i18n = cfg.i18n || {};

	function t( key, fallback ) {
		return typeof i18n[ key ] === 'string' && i18n[ key ] ? i18n[ key ] : fallback;
	}

	function toast( message ) {
		var shell = window.AISAAutoPilotShell;
		if ( shell && typeof shell.toast === 'function' ) {
			shell.toast( message );
		}
	}

	function copyText( text ) {
		if ( navigator.clipboard && typeof navigator.clipboard.writeText === 'function' ) {
			return navigator.clipboard.writeText( text );
		}
		return new Promise( function ( resolve, reject ) {
			var field = document.createElement( 'textarea' );
			field.value = text;
			field.setAttribute( 'readonly', '' );
			field.style.position = 'fixed';
			field.style.opacity = '0';
			document.body.appendChild( field );
			field.select();
			var ok = false;
			try {
				ok = document.execCommand( 'copy' );
			} catch ( e ) {
				ok = false;
			}
			field.remove();
			if ( ok ) {
				resolve();
			} else {
				reject( new Error( 'copy failed' ) );
			}
		} );
	}

	function initCopy() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-sp-form-copy]' ), function ( button ) {
			button.addEventListener( 'click', function () {
				var code = button.getAttribute( 'data-sp-form-copy' ) || '';
				var label = button.querySelector( '[data-sp-form-copy-label]' );
				copyText( code ).then( function () {
					if ( label ) {
						label.textContent = t( 'copied', 'Copied' );
						window.setTimeout( function () { label.textContent = t( 'copy', 'Copy' ); }, 2000 );
					}
					toast( t( 'shortcodeCopied', 'Shortcode copied. Paste it into any page or post.' ) );
				} ).catch( function () {
					toast( t( 'copyFailed', 'Could not copy. Select the shortcode and copy it yourself.' ) );
				} );
			} );
		} );
	}

	function initDeliveryTest() {
		var button = document.querySelector( '[data-sp-form-delivery-test]' );
		var status = document.querySelector( '[data-sp-form-delivery-status]' );
		if ( ! button || ! cfg.restUrl ) {
			return;
		}
		button.addEventListener( 'click', function () {
			button.disabled = true;
			if ( status ) {
				status.textContent = t( 'sending', 'Sending test…' );
				status.setAttribute( 'data-tone', '' );
			}
			window.fetch( cfg.restUrl + 'forms/delivery/test', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce || '' },
				body: '{}'
			} ).then( function ( response ) {
				return response.json().catch( function () { return {}; } ).then( function ( data ) {
					return { ok: response.ok && !! ( data && data.ok ), data: data || {} };
				} );
			} ).then( function ( result ) {
				var message = result.data.message || ( result.ok ? t( 'deliveryOk', 'Test accepted.' ) : t( 'deliveryFail', 'Test failed.' ) );
				if ( status ) {
					status.textContent = message;
					status.setAttribute( 'data-tone', result.ok ? 'ok' : 'err' );
				}
			} ).catch( function () {
				if ( status ) {
					status.textContent = t( 'networkError', 'Network error. Please try again.' );
					status.setAttribute( 'data-tone', 'err' );
				}
			} ).then( function () {
				button.disabled = false;
			} );
		} );
	}

	function init() {
		initCopy();
		initDeliveryTest();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
