/* global SitePilotAI */
( function () {
	'use strict';

	var cfg = window.SitePilotAI || {};
	var presets = cfg.presets || {};

	function $( id ) {
		return document.getElementById( id );
	}

	function applyPreset( key, fillFields ) {
		var preset = presets[ key ];
		if ( ! preset ) {
			return;
		}
		var base = $( 'sp-base-url' );
		var model = $( 'sp-model' );
		var help = $( 'sp-key-help' );

		// Only overwrite the fields on an explicit provider change, never on load
		// (so saved custom base/model values are preserved).
		if ( fillFields && key !== 'custom' ) {
			if ( base && preset.base_url ) {
				base.value = preset.base_url;
			}
			if ( model && preset.model ) {
				model.value = preset.model;
			}
		}
		if ( help ) {
			help.textContent = '';
			if ( preset.key_url ) {
				var a = document.createElement( 'a' );
				a.href = preset.key_url;
				a.target = '_blank';
				a.rel = 'noopener';
				a.textContent = 'Get your ' + preset.label + ' API key';
				help.appendChild( a );
			}
		}
	}

	function onTest() {
		var result = $( 'sp-test-result' );
		result.className = 'sitepilot-status';
		result.textContent = '';
		var spinner = document.createElement( 'span' );
		spinner.className = 'sitepilot-spinner';
		result.appendChild( spinner );
		result.appendChild( document.createTextNode( 'Testing…' ) );

		var body = {
			base_url: $( 'sp-base-url' ).value,
			model: $( 'sp-model' ).value,
		};
		var key = $( 'sp-api-key' ).value;
		if ( key ) {
			body.api_key = key;
		}

		fetch( cfg.restUrl + 'provider/test', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( body ),
		} )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( data ) {
				result.className = 'sitepilot-status ' + ( data.ok ? 'sitepilot-status--ok' : 'sitepilot-status--error' );
				result.textContent = data.message || ( data.ok ? 'OK' : 'Failed' );
			} )
			.catch( function () {
				result.className = 'sitepilot-status sitepilot-status--error';
				result.textContent = 'Network error.';
			} );
	}

	function onFormDeliveryTest() {
		var result = $( 'sp-form-delivery-test-result' );
		if ( ! result ) {
			return;
		}
		result.className = 'sitepilot-status';
		result.textContent = 'Sending test…';

		fetch( cfg.restUrl + 'forms/delivery/test', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( {} ),
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( data ) {
				result.className = 'sitepilot-status ' + ( data.ok ? 'sitepilot-status--ok' : 'sitepilot-status--error' );
				result.textContent = data.message || ( data.ok ? 'Test accepted.' : 'Test failed.' );
			} )
			.catch( function () {
				result.className = 'sitepilot-status sitepilot-status--error';
				result.textContent = 'Network error.';
			} );
	}

	function init() {
		var provider = $( 'sp-provider' );
		if ( provider ) {
			provider.addEventListener( 'change', function () {
				applyPreset( provider.value, true );
			} );
			applyPreset( provider.value, false );
		}
		var test = $( 'sp-test' );
		if ( test ) {
			test.addEventListener( 'click', onTest );
		}
		var deliveryTest = $( 'sp-test-form-delivery' );
		if ( deliveryTest ) {
			deliveryTest.addEventListener( 'click', onFormDeliveryTest );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
