/**
 * Your forms (native AISAAutoPilot forms list).
 *
 * Copies a form's shortcode, sends the delivery test email, and resends or
 * deletes a kept submission whose email failed, all through protected REST routes.
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
			post( 'forms/delivery/test' ).then( function ( result ) {
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

	// Messages come back in the admin's language (_locale=user, honoured for JSON requests). With plain
	// permalinks the REST root already holds "?rest_route=/…", so the query joins with "&" there.
	function post( path ) {
		var url = ( cfg.restUrl || '' ) + path;
		return window.fetch( url + ( -1 === url.indexOf( '?' ) ? '?' : '&' ) + '_locale=user', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce || '' },
			body: '{}'
		} ).then( function ( response ) {
			return response.json().catch( function () { return {}; } ).then( function ( data ) {
				return { ok: response.ok && !! ( data && data.ok ), data: data || {} };
			} );
		} );
	}

	/* Kept submissions: Resend removes the row once sent; Delete asks first. */
	function initOutbox() {
		var box = document.querySelector( '[data-sp-form-outbox]' );
		if ( ! box || ! cfg.restUrl ) {
			return;
		}
		function done( row, result ) {
			var status = row.querySelector( '[data-sp-outbox-status]' );
			if ( result.ok ) {
				row.parentNode.removeChild( row );
				toast( result.data.message || '' );
				var left = box.querySelectorAll( '[data-sp-outbox-row]' ).length;
				var title = box.querySelector( '[data-sp-form-outbox-title]' );
				if ( title ) {
					title.textContent = 0 === left
						? t( 'outboxEmpty', 'Every kept submission has been sent or deleted.' )
						: ( 1 === left ? t( 'outboxLeftOne', 'One submission could not be emailed' ) : t( 'outboxLeftMany', '%s submissions are still waiting to be emailed' ).replace( '%s', String( left ) ) );
				}
				return;
			}
			if ( status ) {
				status.textContent = result.data.message || t( 'networkError', 'Network error. Please try again.' );
				status.setAttribute( 'data-tone', 'err' );
			}
		}
		box.addEventListener( 'click', function ( event ) {
			var resend = event.target.closest( '[data-sp-outbox-resend]' );
			var remove = event.target.closest( '[data-sp-outbox-delete]' );
			var button = resend || remove;
			if ( ! button ) {
				return;
			}
			var row = button.closest( '[data-sp-outbox-row]' );
			var id = button.getAttribute( resend ? 'data-sp-outbox-resend' : 'data-sp-outbox-delete' );
			if ( remove && ! window.confirm( t( 'outboxDeleteConfirm', 'Delete these answers for good? They were never emailed.' ) ) ) {
				return;
			}
			var status = row.querySelector( '[data-sp-outbox-status]' );
			if ( status ) { status.textContent = resend ? t( 'outboxSending', 'Sending…' ) : ''; status.setAttribute( 'data-tone', '' ); }
			Array.prototype.forEach.call( row.querySelectorAll( 'button' ), function ( b ) { b.disabled = true; } );
			post( 'forms/outbox/' + encodeURIComponent( id ) + '/' + ( resend ? 'resend' : 'delete' ) ).then( function ( result ) {
				done( row, result );
			} ).catch( function () {
				done( row, { ok: false, data: {} } );
			} ).then( function () {
				Array.prototype.forEach.call( row.querySelectorAll( 'button' ), function ( b ) { b.disabled = false; } );
			} );
		} );
	}

	function init() {
		initCopy();
		initDeliveryTest();
		initOutbox();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
