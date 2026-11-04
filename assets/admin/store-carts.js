/**
 * Store Copilot > Orders > Abandoned carts: Send reminder and Delete. Each asks first,
 * then calls the protected /woocommerce/carts/{id}/remind|delete route. Nothing is sent
 * to a shopper without the store manager's click.
 */
( function () {
	'use strict';

	var cfg = window.AISAAutoPilotAI || {};
	var panel = document.querySelector( '[data-sp-store-carts]' );
	if ( ! panel || ! cfg.restUrl ) {
		return;
	}
	var i18n = {};
	try { i18n = JSON.parse( ( document.getElementById( 'sp-store-i18n' ) || {} ).textContent || '{}' ) || {}; } catch ( e ) { i18n = {}; }
	function t( key, fallback ) { return typeof i18n[ key ] === 'string' && i18n[ key ] ? i18n[ key ] : fallback; }

	// Messages in the admin's language; with plain permalinks the REST root already has "?rest_route=".
	function call( path ) {
		var url = cfg.restUrl + path;
		url += ( -1 === url.indexOf( '?' ) ? '?' : '&' ) + '_locale=user';
		return fetch( url, { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce, Accept: 'application/json' } } )
			.then( function ( response ) { return response.json().catch( function () { return {}; } ); } );
	}

	function act( button, action ) {
		var row = button.closest( '[data-sp-cart]' );
		var note = row.querySelector( '.sp-store-cart__note' );
		if ( ! window.confirm( 'remind' === action ? t( 'cart_confirm_remind', 'Send the one reminder email for this cart now?' ) : t( 'cart_confirm_delete', 'Delete this cart and its email? The shopper is not told.' ) ) ) {
			return;
		}
		var buttons = row.querySelectorAll( 'button' );
		Array.prototype.forEach.call( buttons, function ( b ) { b.disabled = true; } );
		call( 'woocommerce/carts/' + encodeURIComponent( row.getAttribute( 'data-sp-cart' ) ) + '/' + action ).then( function ( data ) {
			note.textContent = data.message || '';
			note.className = 'sp-status-text sp-store-cart__note ' + ( data.ok ? 'is-ok' : 'is-err' );
			if ( data.ok && 'delete' === action ) {
				row.classList.add( 'is-gone' );
				return;
			}
			if ( data.ok && 'remind' === action ) {
				var pill = document.createElement( 'span' );
				pill.className = 'sp-pill sp-pill--ok';
				pill.setAttribute( 'data-sp-cart-reminded', '' );
				pill.textContent = t( 'cart_reminded', 'Reminder sent' );
				button.replaceWith( pill );
			}
			Array.prototype.forEach.call( row.querySelectorAll( 'button' ), function ( b ) { b.disabled = false; } );
		} ).catch( function () {
			note.textContent = t( 'network_error', 'Network error. Please try again.' );
			note.className = 'sp-status-text sp-store-cart__note is-err';
			Array.prototype.forEach.call( buttons, function ( b ) { b.disabled = false; } );
		} );
	}

	panel.addEventListener( 'click', function ( event ) {
		var remind = event.target.closest( '[data-sp-cart-remind]' );
		var del = event.target.closest( '[data-sp-cart-delete]' );
		if ( remind ) { act( remind, 'remind' ); }
		if ( del ) { act( del, 'delete' ); }
	} );
}() );
