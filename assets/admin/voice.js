/* global SitePilotAI, SitePilotChat */
/* Voice Input (STT) + Text-to-Speech + Karaoke Word Highlighting */
( function () {
	'use strict';

	var cfg = window.SitePilotAI || window.SitePilotChat || {};
	var isRecording = false;
	var mediaRecorder = null;
	var audioChunks = [];
	var synth = window.speechSynthesis;
	var currentUtterance = null;
	var wordSpans = [];

	/* ── MediaRecorder helpers ── */

	function startRecording() {
		if ( isRecording ) { return; }
		if ( ! navigator.mediaDevices || ! navigator.mediaDevices.getUserMedia ) {
			setStatus( 'Voice recording is not supported in this browser.', 'error' );
			return;
		}
		navigator.mediaDevices.getUserMedia( { audio: true } )
			.then( function ( stream ) {
				mediaRecorder = new MediaRecorder( stream, { mimeType: 'audio/webm;codecs=opus' } );
				audioChunks = [];
				mediaRecorder.ondataavailable = function ( e ) {
					if ( e.data.size > 0 ) { audioChunks.push( e.data ); }
				};
				mediaRecorder.onstop = function () {
					stream.getTracks().forEach( function ( t ) { t.stop(); } );
					var blob = new Blob( audioChunks, { type: 'audio/webm' } );
					sendAudio( blob );
				};
				mediaRecorder.start();
				isRecording = true;
				setStatus( '🎤 Recording… tap mic to stop', 'recording' );
				updateMicButton( true );
			} )
			.catch( function ( err ) {
				setStatus( 'Microphone access denied: ' + err.message, 'error' );
			} );
	}

	function stopRecording() {
		if ( ! isRecording || ! mediaRecorder ) { return; }
		mediaRecorder.stop();
		isRecording = false;
		updateMicButton( false );
	}

	function toggleRecording() {
		if ( isRecording ) { stopRecording(); }
		else { startRecording(); }
	}

	function sendAudio( blob ) {
		setStatus( '🧠 Transcribing speech…', 'loading' );
		var reader = new FileReader();
		reader.onloadend = function () {
			var base64 = reader.result.split( ',' )[ 1 ];
			fetch( cfg.restUrl + 'voice/transcribe', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
				body: JSON.stringify( { audio: base64, format: 'webm' } ),
			} )
				.then( function ( r ) { return r.json(); } )
				.then( function ( data ) {
					if ( data.ok && data.text ) {
						setStatus( '✅ Transcribed: "' + data.text + '"', 'ok' );
						submitText( data.text );
					} else {
						setStatus( data.message || 'Transcription failed.', 'error' );
					}
				} )
				.catch( function () {
					setStatus( 'Transcription network error.', 'error' );
				} );
		};
		reader.readAsDataURL( blob );
	}

	function submitText( text ) {
		var input = document.getElementById( 'sp-chat-input' );
		if ( input ) {
			input.value = text;
			var event = new Event( 'input', { bubbles: true } );
			input.dispatchEvent( event );
			var btn = document.querySelector( '.sp-chat-send' );
			if ( btn && ! btn.disabled ) { btn.click(); }
		}
	}

	/* ── TTS + Karaoke Word Highlighting ── */

	function speakText( text, containerEl ) {
		if ( ! synth ) {
			console.warn( 'SpeechSynthesis not available' );
			return;
		}
		synth.cancel();

		// Build per-word spans for karaoke highlighting.
		wordSpans = [];
		var words = text.split( /\b/ );
		containerEl.innerHTML = '';
		var charOffset = 0;
		words.forEach( function ( w ) {
			var span = document.createElement( 'span' );
			span.className = 'sp-word';
			span.textContent = w;
			span.dataset.start = charOffset;
			span.dataset.end = charOffset + w.length;
			containerEl.appendChild( span );
			wordSpans.push( span );
			charOffset += w.length;
		} );

		var utterance = new SpeechSynthesisUtterance( text );
		utterance.rate = 1.0;
		utterance.pitch = 1.0;

		// Pick a natural voice if available.
		var voices = synth.getVoices();
		var preferred = voices.filter( function ( v ) {
			return v.lang.startsWith( 'en' ) && v.name.includes( 'Google' );
		} );
		utterance.voice = preferred.length > 0 ? preferred[ 0 ] : ( voices[ 0 ] || null );

		utterance.onboundary = function ( e ) {
			if ( e.name === 'word' ) {
				var idx = e.charIndex;
				wordSpans.forEach( function ( span ) {
					var start = parseInt( span.dataset.start, 10 );
					var end = parseInt( span.dataset.end, 10 );
					if ( idx >= start && idx < end ) {
						wordSpans.forEach( function ( s ) { s.classList.remove( 'sp-word--active' ); } );
						span.classList.add( 'sp-word--active' );
						span.scrollIntoView( { block: 'nearest', behavior: 'smooth' } );
					}
				} );
			}
		};

		utterance.onend = function () {
			wordSpans.forEach( function ( s ) { s.classList.remove( 'sp-word--active' ); } );
			updateAudioBtn( 'play' );
		};

		currentUtterance = utterance;
		synth.speak( utterance );
		updateAudioBtn( 'pause' );
	}

	function stopSpeech() {
		if ( synth ) { synth.cancel(); }
		wordSpans.forEach( function ( s ) { s.classList.remove( 'sp-word--active' ); } );
		updateAudioBtn( 'play' );
	}

	function toggleSpeech( text, containerEl ) {
		if ( synth && synth.speaking ) {
			if ( synth.paused ) { synth.resume(); updateAudioBtn( 'pause' ); }
			else { synth.pause(); updateAudioBtn( 'play' ); }
		} else {
			speakText( text, containerEl );
		}
	}

	/* ── UI helpers ── */

	function updateMicButton( active ) {
		var btn = document.getElementById( 'sp-mic-btn' );
		if ( ! btn ) { return; }
		btn.classList.toggle( 'sp-mic--recording', active );
		btn.title = active ? 'Stop recording' : 'Start voice input';
	}

	function updateAudioBtn( state ) {
		var btns = document.querySelectorAll( '.sp-audio-btn' );
		btns.forEach( function ( b ) {
			b.textContent = 'pause' === state ? '⏸' : '🔊';
			b.classList.toggle( 'sp-audio--playing', 'pause' === state );
		} );
	}

	function setStatus( msg, kind ) {
		var el = document.getElementById( 'sp-mic-status' );
		if ( ! el ) { return; }
		el.textContent = msg;
		el.className = 'sp-mic-status' + ( kind ? ' sp-mic-status--' + kind : '' );
	}

	/* ── Init ── */

	function initVoice() {
		// Wire up the mic button.
		var micBtn = document.getElementById( 'sp-mic-btn' );
		if ( micBtn ) {
			micBtn.addEventListener( 'click', toggleRecording );
		}

		// Pre-load voices.
		if ( synth ) {
			synth.getVoices(); // Trigger async load.
			synth.onvoiceschanged = function () { synth.getVoices(); };
		}

		// Expose for chat.js integration.
		window.SitePilotVoice = {
			startRecording: startRecording,
			stopRecording: stopRecording,
			toggleRecording: toggleRecording,
			speakText: speakText,
			stopSpeech: stopSpeech,
			toggleSpeech: toggleSpeech,
			setStatus: setStatus,
			isSpeaking: function () { return synth && synth.speaking; },
		};
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initVoice );
	} else {
		initVoice();
	}
} )();
