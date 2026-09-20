/* global AISAAutoPilotAI, AISAAutoPilotChat */
( function () {
	'use strict';

	var cfg = window.AISAAutoPilotChat || window.AISAAutoPilotAI || {};
	var T = readJson( 'sp-chat-i18n' );
	var recorder = null;
	var stream = null;
	var chunks = [];
	var isRecording = false;
	var isTranscribing = false;
	var startedAt = 0;
	var stopTimer = null;
	var maxDurationMs = 45000;
	var synth = window.speechSynthesis;

	function readJson( id ) {
		try {
			var node = document.getElementById( id );
			return node ? ( JSON.parse( node.textContent || '{}' ) || {} ) : {};
		} catch ( e ) { return {}; }
	}

	function t( key, fallback ) { return T[ key ] || fallback; }

	function setStatus( message, tone ) {
		var status = document.getElementById( 'sp-chat-status' );
		if ( ! status ) { return; }
		status.textContent = message || '';
		if ( tone ) {
			status.setAttribute( 'data-tone', tone );
		} else {
			status.removeAttribute( 'data-tone' );
		}
	}

	function updateMicButton() {
		var button = document.getElementById( 'sp-mic-btn' );
		if ( ! button ) { return; }
		var label = isRecording ? t( 'recordStop', 'Stop recording' ) : t( 'recordStart', 'Record a question' );
		button.classList.toggle( 'is-recording', isRecording );
		button.setAttribute( 'aria-pressed', isRecording ? 'true' : 'false' );
		button.setAttribute( 'aria-busy', isTranscribing ? 'true' : 'false' );
		button.setAttribute( 'aria-label', label );
		button.title = label;
		if ( isTranscribing ) {
			button.disabled = true;
		} else if ( ! isRecording ) {
			var input = document.getElementById( 'sp-chat-input' );
			button.disabled = !! input && input.disabled;
		}
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

	// Voice never sends by itself: the transcript waits in the composer for review.
	function placeTranscript( transcript ) {
		var input = document.getElementById( 'sp-chat-input' );
		if ( ! input ) { return; }
		var current = input.value.trim();
		input.value = current ? current + ' ' + transcript : transcript;
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		if ( ! input.disabled ) {
			input.focus();
			try { input.setSelectionRange( input.value.length, input.value.length ); } catch ( e ) {}
		}
	}

	function finishTranscribing() {
		isTranscribing = false;
		updateMicButton();
	}

	function sendAudio( blob, durationMs ) {
		if ( ! blob || ! blob.size ) {
			setStatus( t( 'noAudio', 'No audio was captured. Please try again.' ), 'error' );
			return;
		}
		isTranscribing = true;
		updateMicButton();
		setStatus( t( 'transcribing', 'Transcribing your question…' ), 'loading' );
		var form = new FormData();
		form.append( 'audio', blob, 'aisaautopilot-chat-command.webm' );
		form.append( 'duration_ms', String( Math.min( maxDurationMs, Math.max( 0, durationMs ) ) ) );
		form.append( 'language', document.documentElement.lang || '' );

		fetch( cfg.restUrl + 'chat/voice/transcribe', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.nonce },
			body: form,
		} ).then( function ( response ) {
			return response.json().catch( function () { return {}; } ).then( function ( data ) {
				if ( ! response.ok ) { throw new Error( data.message || '' ); }
				return data;
			} );
		} ).then( function ( data ) {
			finishTranscribing();
			if ( data.ok && data.transcript ) {
				placeTranscript( data.transcript );
				setStatus( t( 'transcriptReady', 'Transcript ready — check it, then send. Nothing is sent on its own.' ), 'ok' );
				return;
			}
			setStatus( data.message || t( 'transcribeFailed', 'Voice transcription failed. Please try again.' ), 'error' );
		} ).catch( function ( error ) {
			finishTranscribing();
			setStatus( ( error && error.message ) || t( 'transcribeFailed', 'Voice transcription failed. Please try again.' ), 'error' );
		} );
	}

	function startRecording() {
		if ( isRecording || isTranscribing ) { return; }
		if ( ! navigator.mediaDevices || ! navigator.mediaDevices.getUserMedia || ! window.MediaRecorder ) {
			setStatus( t( 'micUnsupported', 'Voice recording is not supported in this browser.' ), 'error' );
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
				setStatus( t( 'recorderFailed', 'This browser cannot create a supported voice recording.' ), 'error' );
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
					setStatus( t( 'maxDuration', 'Maximum recording time reached. Transcribing…' ), 'loading' );
					stopRecording();
				}
			}, maxDurationMs );
			updateMicButton();
			setStatus( t( 'listening', 'Listening… Select the microphone again when you are finished.' ), 'recording' );
		}, function () {
			setStatus( t( 'micDenied', 'Microphone access was denied. Allow it in your browser, then try again.' ), 'error' );
		} );
	}

	function toggleRecording() {
		if ( isRecording ) { stopRecording(); } else { startRecording(); }
	}

	function speakText( text, onEnd ) {
		if ( ! synth || ! text ) { return false; }
		synth.cancel();
		var utterance = new SpeechSynthesisUtterance( text );
		utterance.lang = document.documentElement.lang || 'en-US';
		var voices = synth.getVoices();
		var language = utterance.lang.toLowerCase().slice( 0, 2 );
		var preferred = voices.filter( function ( voice ) { return voice.lang.toLowerCase().slice( 0, 2 ) === language; } );
		if ( preferred.length ) { utterance.voice = preferred[ 0 ]; }
		if ( onEnd ) {
			utterance.onend = onEnd;
			utterance.onerror = onEnd;
		}
		synth.speak( utterance );
		return true;
	}

	function init() {
		var button = document.getElementById( 'sp-mic-btn' );
		if ( button ) { button.addEventListener( 'click', toggleRecording ); }
		if ( synth ) { synth.getVoices(); }
		window.AISAAutoPilotVoice = {
			startRecording: startRecording,
			stopRecording: stopRecording,
			toggleRecording: toggleRecording,
			speakText: speakText,
			toggleSpeech: function ( text, onEnd ) {
				if ( synth && synth.speaking ) { synth.cancel(); return false; }
				return speakText( text, onEnd );
			},
			stopSpeech: function () { if ( synth ) { synth.cancel(); } },
		};
	}

	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
} )();
