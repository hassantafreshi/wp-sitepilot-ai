/* global SitePilotAI, SitePilotChat */
( function () {
	'use strict';

	var cfg = window.SitePilotChat || window.SitePilotAI || {};
	var recorder = null;
	var stream = null;
	var chunks = [];
	var isRecording = false;
	var startedAt = 0;
	var stopTimer = null;
	var maxDurationMs = 45000;
	var synth = window.speechSynthesis;

	function setStatus( message, state ) {
		var status = document.getElementById( 'sp-chat-status' );
		if ( ! status ) { return; }
		status.textContent = message;
		status.className = 'sitepilot-status' + ( state ? ' sitepilot-status--' + state : '' );
	}

	function updateMicButton() {
		var button = document.getElementById( 'sp-mic-btn' );
		if ( ! button ) { return; }
		button.classList.toggle( 'sp-mic-btn--recording', isRecording );
		button.setAttribute( 'aria-label', isRecording ? 'Stop voice input' : 'Start voice input' );
		button.title = isRecording ? 'Stop voice input' : 'Start voice input';
	}

	function stopTracks() {
		if ( stream ) {
			stream.getTracks().forEach( function ( track ) { track.stop(); } );
			stream = null;
		}
	}

	function stopRecording() {
		if ( ! recorder || ! isRecording ) { return; }
		if ( stopTimer ) {
			window.clearTimeout( stopTimer );
			stopTimer = null;
		}
		isRecording = false;
		updateMicButton();
		recorder.stop();
	}

	function submitText( transcript ) {
		var input = document.getElementById( 'sp-chat-input' );
		var button = document.getElementById( 'sp-chat-send' );
		if ( ! input || ! button || button.disabled ) { return; }
		input.value = transcript;
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		button.click();
	}

	function sendAudio( blob, durationMs ) {
		if ( ! blob || ! blob.size ) {
			setStatus( 'No audio was captured. Please try again.', 'error' );
			return;
		}
		setStatus( 'Transcribing voice input…', 'loading' );
		var form = new FormData();
		form.append( 'audio', blob, 'sitepilot-chat-command.webm' );
		form.append( 'duration_ms', String( Math.min( maxDurationMs, Math.max( 0, durationMs ) ) ) );
		form.append( 'language', document.documentElement.lang || '' );

		fetch( cfg.restUrl + 'chat/voice/transcribe', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.nonce },
			body: form,
		} ).then( function ( response ) {
			return response.json().then( function ( data ) {
				if ( ! response.ok ) { throw new Error( data.message || 'Could not transcribe audio.' ); }
				return data;
			} );
		} ).then( function ( data ) {
			if ( data.ok && data.transcript ) {
				setStatus( 'Voice command recognized. Sending it to SitePilot…', 'ok' );
				submitText( data.transcript );
				return;
			}
			setStatus( data.message || 'Could not transcribe audio.', 'error' );
		} ).catch( function ( error ) {
			setStatus( error.message || 'Voice transcription failed. Please try again.', 'error' );
		} );
	}

	function startRecording() {
		if ( isRecording ) { return; }
		if ( ! navigator.mediaDevices || ! navigator.mediaDevices.getUserMedia || ! window.MediaRecorder ) {
			setStatus( 'Voice recording is not supported in this browser.', 'error' );
			return;
		}

		navigator.mediaDevices.getUserMedia( { audio: true } ).then( function ( mediaStream ) {
			stream = mediaStream;
			var preferred = window.MediaRecorder.isTypeSupported && window.MediaRecorder.isTypeSupported( 'audio/webm;codecs=opus' )
				? 'audio/webm;codecs=opus'
				: '';
			try {
				recorder = preferred ? new MediaRecorder( stream, { mimeType: preferred } ) : new MediaRecorder( stream );
			} catch ( error ) {
				stopTracks();
				setStatus( 'This browser cannot create a supported voice recording.', 'error' );
				return;
			}
			chunks = [];
			recorder.ondataavailable = function ( event ) {
				if ( event.data && event.data.size ) { chunks.push( event.data ); }
			};
			recorder.onstop = function () {
				var duration = Math.max( 0, Date.now() - startedAt );
				var blob = new Blob( chunks, { type: recorder.mimeType || 'audio/webm' } );
				stopTracks();
				sendAudio( blob, duration );
			};
			recorder.start();
			startedAt = Date.now();
			isRecording = true;
			stopTimer = window.setTimeout( function () {
				if ( isRecording ) {
					setStatus( 'Maximum recording time reached. Transcribing…', 'loading' );
					stopRecording();
				}
			}, maxDurationMs );
			updateMicButton();
			setStatus( 'Listening… Select the microphone again when you are finished.', 'recording' );
		} ).catch( function ( error ) {
			setStatus( 'Microphone access was denied: ' + error.message, 'error' );
		} );
	}

	function toggleRecording() {
		if ( isRecording ) { stopRecording(); } else { startRecording(); }
	}

	function speakText( text ) {
		if ( ! synth || ! text ) { return false; }
		synth.cancel();
		var utterance = new SpeechSynthesisUtterance( text );
		utterance.lang = document.documentElement.lang || 'en-US';
		var voices = synth.getVoices();
		var language = utterance.lang.toLowerCase().slice( 0, 2 );
		var preferred = voices.filter( function ( voice ) { return voice.lang.toLowerCase().slice( 0, 2 ) === language; } );
		if ( preferred.length ) { utterance.voice = preferred[ 0 ]; }
		synth.speak( utterance );
		return true;
	}

	function init() {
		var button = document.getElementById( 'sp-mic-btn' );
		if ( button ) { button.addEventListener( 'click', toggleRecording ); }
		if ( synth ) { synth.getVoices(); }
		window.SitePilotVoice = {
			startRecording: startRecording,
			stopRecording: stopRecording,
			toggleRecording: toggleRecording,
			speakText: speakText,
			toggleSpeech: function ( text ) {
				if ( synth && synth.speaking ) { synth.cancel(); return false; }
				return speakText( text );
			},
			stopSpeech: function () { if ( synth ) { synth.cancel(); } },
		};
	}

	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
} )();
