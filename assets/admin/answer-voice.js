/* global AISAAutoPilotAI */
/**
 * Controls for the truthful, browser-mediated Answer Desk Voice tab.
 * It never starts an audio recording, calls a provider, or stores audio.
 */
( function () {
	'use strict';

	var root = document.querySelector( '[data-sp-voice-settings]' );
	if ( ! root ) { return; }

	var config = window.AISAAutoPilotAI || {};

	function strings() { return config.i18n || {}; }
	function voiceEnabled() { return root.getAttribute( 'data-sp-voice-enabled' ) === '1'; }
	function voiceAuto() { return root.getAttribute( 'data-sp-voice-auto' ) === '1'; }
	function statusNode() { return root.querySelector( '[data-sp-voice-status]' ); }

	function setStatus( message, state ) {
		var node = statusNode();
		if ( ! node ) { return; }
		node.textContent = message || '';
		node.setAttribute( 'data-state', state || '' );
	}

	function setBusy( busy ) {
		Array.prototype.forEach.call( root.querySelectorAll( '[data-sp-voice-engine="transcribe"], [data-sp-voice-auto-toggle]' ), function ( control ) {
			control.disabled = busy || ( control.hasAttribute( 'data-sp-voice-auto-toggle' ) && ! voiceEnabled() );
		} );
	}

	function apply( data ) {
		var voice = data.voice || {};
		var enabled = !! voice.enabled;
		var auto = !! voice.auto;
		var engine = root.querySelector( '[data-sp-voice-engine="transcribe"]' );
		var engineState = root.querySelector( '[data-sp-voice-engine-state]' );
		var autoControl = root.querySelector( '[data-sp-voice-auto-toggle]' );
		var autoNote = root.querySelector( '[data-sp-voice-auto-note]' );
		var test = root.querySelector( '[data-sp-voice-test]' );

		root.setAttribute( 'data-sp-voice-enabled', enabled ? '1' : '0' );
		root.setAttribute( 'data-sp-voice-auto', auto ? '1' : '0' );
		if ( engine ) {
			engine.classList.toggle( 'is-on', enabled );
			engine.setAttribute( 'aria-checked', enabled ? 'true' : 'false' );
		}
		if ( engineState ) { engineState.textContent = enabled ? 'On' : 'Paused'; }
		if ( autoControl ) {
			autoControl.classList.toggle( 'is-on', auto );
			autoControl.setAttribute( 'aria-checked', auto ? 'true' : 'false' );
			autoControl.disabled = ! enabled;
		}
		if ( autoNote ) {
			autoNote.textContent = enabled
				? 'Uses the visitor’s browser speech engine. Off keeps voice input but shows the answer as text only.'
				: 'Turn on transcribe + speak first; reply playback is unavailable while voice input is paused.';
		}
		if ( test ) { test.disabled = ! enabled; }
		if ( ! enabled && window.speechSynthesis && window.speechSynthesis.cancel ) { window.speechSynthesis.cancel(); }
	}

	function save( enabled, auto ) {
		if ( ! config.restUrl || ! config.nonce ) {
			setStatus( strings().voiceSaveFailed || 'Could not save the voice settings. Please try again.', 'error' );
			return Promise.reject( new Error( 'missing_rest_configuration' ) );
		}

		var values = { voice_enabled: enabled, voice_auto: auto };
		setBusy( true );
		setStatus( strings().voiceSaving || 'Saving...', 'busy' );
		return window.fetch( config.restUrl + 'voice/admin-settings', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce
			},
			body: JSON.stringify( values )
		} ).then( function ( response ) {
			return response.json().catch( function () { return {}; } ).then( function ( data ) {
				if ( ! response.ok || ! data.ok ) { throw new Error( data.message || 'voice_save_failed' ); }
				return data;
			} );
		} ).then( function ( data ) {
			apply( data );
			setStatus( strings().voiceSaved || 'Saved.', 'saved' );
			return data;
		} ).catch( function ( error ) {
			setStatus( error.message || strings().voiceSaveFailed || 'Could not save the voice settings. Please try again.', 'error' );
			throw error;
		} ).finally( function () {
			setBusy( false );
		} );
	}

	function playTestPhrase() {
		if ( ! voiceEnabled() ) {
			setStatus( strings().voiceEnableFirst || 'Turn on transcribe + speak before playing a test phrase.', 'error' );
			return;
		}
		if ( ! window.speechSynthesis || ! window.SpeechSynthesisUtterance ) {
			setStatus( strings().voiceTestUnavailable || 'Speech output is not available in this browser.', 'error' );
			return;
		}

		try {
			var utterance = new window.SpeechSynthesisUtterance( 'This is a local AutoPilot voice test.' );
			utterance.lang = document.documentElement.lang || '';
			utterance.onend = function () {
				setStatus( strings().voiceTestDone || 'Test phrase finished in this browser.', 'saved' );
			};
			utterance.onerror = function () {
				setStatus( strings().voiceTestFailed || 'This browser could not play the test phrase.', 'error' );
			};
			window.speechSynthesis.cancel();
			window.speechSynthesis.speak( utterance );
			setStatus( strings().voiceTestPlaying || 'Playing a test phrase locally...', 'busy' );
		} catch ( error ) {
			setStatus( strings().voiceTestFailed || 'This browser could not play the test phrase.', 'error' );
		}
	}

	root.addEventListener( 'click', function ( event ) {
		var engine = event.target.closest( '[data-sp-voice-engine="transcribe"]' );
		if ( engine && ! engine.disabled ) {
			save( ! voiceEnabled(), voiceAuto() ).catch( function () {} );
			return;
		}

		var auto = event.target.closest( '[data-sp-voice-auto-toggle]' );
		if ( auto && ! auto.disabled ) {
			save( voiceEnabled(), ! voiceAuto() ).catch( function () {} );
			return;
		}

		var test = event.target.closest( '[data-sp-voice-test]' );
		if ( test && ! test.disabled ) { playTestPhrase(); }
	} );
}() );
