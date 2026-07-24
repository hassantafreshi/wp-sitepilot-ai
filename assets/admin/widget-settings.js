/**
 * SitePilot AI — Widget Settings live preview.
 * Mirrors title, greeting, brand color, background, and position into the
 * mock widget on the right. Pure client-side; no network calls.
 */
( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function () {
		var form = document.querySelector( '.sitepilot-widget-form' );
		var stage = document.getElementById( 'sp-wp-stage' );
		if ( ! form || ! stage ) {
			return;
		}

		function field( name ) {
			return form.querySelector( '[name="widget[' + name + ']"]' );
		}

		var title = document.getElementById( 'sp-wp-title' );
		var greeting = document.getElementById( 'sp-wp-greeting' );
		var header = document.getElementById( 'sp-wp-header' );
		var body = document.getElementById( 'sp-wp-body' );
		var launcher = document.getElementById( 'sp-wp-launcher' );

		var titleIn = field( 'title' );
		var greetIn = field( 'greeting' );
		var colorIn = field( 'color' );
		var bgIn = field( 'bg' );
		var posIn = field( 'position' );

		function sync() {
			if ( titleIn && title ) {
				title.textContent = titleIn.value || 'SitePilot AI';
			}
			if ( greetIn && greeting ) {
				greeting.textContent = greetIn.value || 'Hi! How can I help?';
			}
			if ( colorIn ) {
				if ( header ) { header.style.background = colorIn.value; }
				if ( launcher ) { launcher.style.background = colorIn.value; }
			}
			if ( bgIn && body ) {
				body.style.background = bgIn.value;
			}
			if ( posIn ) {
				stage.setAttribute( 'data-position', posIn.value );
			}
		}

		[ titleIn, greetIn, colorIn, bgIn, posIn ].forEach( function ( el ) {
			if ( ! el ) {
				return;
			}
			el.addEventListener( 'input', sync );
			el.addEventListener( 'change', sync );
		} );

		sync();
	} );
} )();
