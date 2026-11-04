/**
 * Abandoned-cart reminders: the notice under the checkout email field.
 *
 * Shown on the checkout page only while the store manager has turned reminders on. The
 * notice says what is kept and for how long, with a button not to keep it. Only after a
 * valid email is in the field (typed, or already there for a signed-in customer) is the
 * cart sent to the site, once per distinct address. Works with the classic checkout
 * (#billing_email) and the checkout block (#email), which renders later.
 */
( function () {
	'use strict';

	var cfg = window.AISAAutoPilotCart;
	if ( ! cfg || ! window.fetch ) {
		return;
	}
	var declined = !! cfg.declined;
	var lastSent = '';
	var timer = 0;

	function post( action, data ) {
		var body = new URLSearchParams( Object.assign( { action: action, nonce: cfg.nonce }, data || {} ) );
		return fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			.catch( function () { return {}; } );
	}
	function looksValid( value ) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test( value ); }
	function emailField() { return document.querySelector( '#billing_email, .wc-block-checkout #email, form.wc-block-checkout__form input#email' ); }

	function buildNotice() {
		var note = document.createElement( 'p' );
		note.className = 'aisaautopilot-cart-notice';
		note.setAttribute( 'data-aisaautopilot-cart-notice', declined ? 'declined' : 'shown' );
		var text = document.createElement( 'span' );
		text.textContent = declined ? cfg.declinedText : cfg.notice;
		note.appendChild( text );
		if ( ! declined ) {
			var button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'aisaautopilot-cart-notice__decline';
			button.textContent = cfg.decline;
			button.addEventListener( 'click', function () {
				declined = true;
				window.clearTimeout( timer );
				button.disabled = true;
				post( 'aisaautopilot_cart_forget' ).then( function () {
					text.textContent = cfg.declinedText;
					note.setAttribute( 'data-aisaautopilot-cart-notice', 'declined' );
					button.remove();
				} );
			} );
			note.appendChild( document.createTextNode( ' ' ) );
			note.appendChild( button );
		}
		return note;
	}

	function placeNotice( input ) {
		if ( document.querySelector( '[data-aisaautopilot-cart-notice]' ) ) {
			return;
		}
		var host = input.closest( '.wc-block-components-text-input, .form-row' ) || input.parentNode;
		host.insertAdjacentElement( 'afterend', buildNotice() );
	}

	function capture( input ) {
		var value = ( input.value || '' ).trim();
		if ( declined || ! looksValid( value ) || value === lastSent ) {
			return;
		}
		lastSent = value;
		post( 'aisaautopilot_cart_capture', { email: value } );
	}

	function bind( input ) {
		placeNotice( input );
		if ( input.getAttribute( 'data-aisaautopilot-cart' ) ) {
			return;
		}
		input.setAttribute( 'data-aisaautopilot-cart', '1' );
		input.addEventListener( 'change', function () { capture( input ); } );
		input.addEventListener( 'blur', function () { capture( input ); } );
		input.addEventListener( 'input', function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( function () { capture( input ); }, 1500 );
		} );
		// A signed-in customer's address is already there; the notice is shown first.
		window.setTimeout( function () { capture( input ); }, 400 );
	}

	function scan() {
		var input = emailField();
		if ( input ) { bind( input ); }
	}

	scan();
	// The checkout block renders after load and may re-render the contact step.
	new MutationObserver( scan ).observe( document.body, { childList: true, subtree: true } );
}() );
