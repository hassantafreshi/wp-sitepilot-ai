/* global SitePilotWidget */
( function () {
	'use strict';

	if ( document.getElementById( 'sp-widget' ) === null ) { return; }

	var cfg = window.SitePilotWidget || {};
	var api = cfg.restUrl || '/wp-json/sitepilot/v1/';
	var nonce = cfg.nonce || '';
	var w = document.getElementById( 'sp-widget' );
	var bubble = w.querySelector( '.sp-widget-bubble' );
	var panel = w.querySelector( '.sp-widget-panel' );
	var msgs = w.querySelector( '.sp-widget-msgs' );
	var input = w.querySelector( '.sp-widget-input textarea' );
	var sendBtn = w.querySelector( '.sp-widget-send' );
	var micBtn = w.querySelector( '.sp-widget-mic' );
	var closeBtn = w.querySelector( '.sp-widget-close' );
	var color = w.getAttribute( 'data-color' ) || '#4f46e5';
	var voiceEnabled = w.getAttribute( 'data-voice' ) === '1';
	var handoffEnabled = w.getAttribute( 'data-handoff' ) === '1';
	var autoOpen = parseInt( w.getAttribute( 'data-auto-open' ) || '0' );
	var isDark = w.classList.contains( 'sp-widget--dark' );
	var isOpen = false;
	var isRecording = false;
	var mediaRecorder = null;
	var audioChunks = [];
	var synth = window.speechSynthesis;
	var utterance = null;

	w.style.setProperty( '--sp-color', color );

	// ── State ──
	var state = loadState();

	function loadState() {
		try { return JSON.parse( localStorage.getItem( 'sp_widget_chat' ) ) || { messages: [], convId: null }; }
		catch ( e ) { return { messages: [], convId: null }; }
	}
	function saveState() {
		try { localStorage.setItem( 'sp_widget_chat', JSON.stringify( state ) ); } catch ( e ) {}
	}

	// ── API ──
	function apiCall( path, body ) {
		return fetch( api + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
			body: JSON.stringify( body || {} ),
		} ).then( function ( r ) { return r.json(); } );
	}

	// ── UI ──
	function toggle() {
		isOpen = ! isOpen;
		w.classList.toggle( 'open', isOpen );
		if ( isOpen && state.messages.length === 0 ) {
			addMsg( 'ai', cfg.greeting || 'Hi! How can I help you?' );
		}
		if ( isOpen ) { setTimeout( function () { input.focus(); }, 300 ); }
	}

	bubble.addEventListener( 'click', toggle );
	closeBtn.addEventListener( 'click', toggle );

	if ( autoOpen > 0 ) {
		setTimeout( function () {
			if ( ! isOpen ) { toggle(); }
		}, autoOpen * 1000 );
	}

	// ── Messages ──
	function addMsg( role, content, sources ) {
		var div = document.createElement( 'div' );
		div.className = 'sp-wm sp-wm--' + role;

		var avatar = document.createElement( 'div' );
		avatar.className = 'sp-wm-avatar sp-wm-avatar--' + role;
		avatar.textContent = role === 'user' ? '👤' : '🤖';

		var bubble = document.createElement( 'div' );
		bubble.className = 'sp-wm-bubble';
		bubble.textContent = content;

		// Karaoke: wrap words for AI responses
		if ( role === 'ai' ) {
			bubble.innerHTML = '';
			var words = ( content || '' ).split( /\s+/ );
			words.forEach( function ( w ) {
				var span = document.createElement( 'span' );
				span.className = 'sp-wm-kw';
				span.textContent = w + '\u00A0';
				bubble.appendChild( span );
			} );
		}

		div.appendChild( avatar );
		div.appendChild( bubble );

		// Voice toggle for AI
		if ( role === 'ai' && voiceEnabled ) {
			var voiceBtn = document.createElement( 'button' );
			voiceBtn.className = 'sp-wm-voice';
			voiceBtn.textContent = '🔊';
			voiceBtn.addEventListener( 'click', function () { speakText( content, this ); } );
			div.appendChild( voiceBtn );
		}

		msgs.appendChild( div );
		msgs.scrollTop = msgs.scrollHeight;
		return bubble;
	}

	function addThinking() {
		var div = document.createElement( 'div' );
		div.className = 'sp-wm sp-wm--ai sp-wd-thinking';
		div.id = 'sp-wd-thinking';

		var avatar = document.createElement( 'div' );
		avatar.className = 'sp-wm-avatar sp-wm-avatar--ai';
		avatar.textContent = '🤖';

		var dots = document.createElement( 'div' );
		dots.className = 'sp-wd-think';
		dots.innerHTML = '<span></span><span></span><span></span>';

		div.appendChild( avatar );
		div.appendChild( dots );
		msgs.appendChild( div );
		msgs.scrollTop = msgs.scrollHeight;
	}

	function removeThinking() {
		var d = document.getElementById( 'sp-wd-thinking' );
		if ( d ) { d.remove(); }
	}

	// ── Voice ──
	function speakText( text, btn ) {
		if ( synth.speaking ) { synth.cancel(); return; }
		utterance = new SpeechSynthesisUtterance( text );
		var voices = synth.getVoices();
		var preferred = cfg.voiceName || 'Google UK English Female';
		var voice = voices.find( function ( v ) { return v.name === preferred; } ) || voices[0];
		if ( voice ) { utterance.voice = voice; }
		utterance.rate = cfg.voiceRate || 1.0;
		utterance.pitch = cfg.voicePitch || 1.0;

		// Karaoke highlight
		var spans = btn ? btn.parentElement.querySelectorAll( '.sp-wm-kw' ) : [];
		var charIdx = 0;
		var wordIdx = 0;
		utterance.onboundary = function ( e ) {
			if ( e.name === 'word' ) {
				var idx = e.charIndex;
				spans.forEach( function ( s, i ) { s.classList.toggle( 'sp-wm-kw--active', false ); } );
				var acc = 0;
				for ( var i = 0; i < spans.length; i++ ) {
					acc += spans[i].textContent.length;
					if ( idx <= acc ) { spans[i].classList.add( 'sp-wm-kw--active' ); break; }
				}
			}
		};
		utterance.onend = function () {
			spans.forEach( function ( s ) { s.classList.remove( 'sp-wm-kw--active' ); } );
			if ( btn ) { btn.textContent = '🔊'; }
		};
		if ( btn ) { btn.textContent = '⏹'; }
		synth.speak( utterance );
	}

	// ── Mic (STT) ──
	if ( micBtn ) {
		if ( ! navigator.mediaDevices || ! navigator.mediaDevices.getUserMedia ) {
			micBtn.style.display = 'none';
		} else {
			micBtn.addEventListener( 'click', function () {
				if ( isRecording ) { stopRecording(); }
				else { startRecording(); }
			} );
		}
	}

	function startRecording() {
		isRecording = true;
		micBtn.classList.add( 'sp-widget-mic--recording' );
		micBtn.innerHTML = '<span class="sp-mic-wave"><span></span><span></span><span></span></span>';
		navigator.mediaDevices.getUserMedia( { audio: true } ).then( function ( stream ) {
			mediaRecorder = new MediaRecorder( stream, { mimeType: 'audio/webm' } );
			audioChunks = [];
			mediaRecorder.ondataavailable = function ( e ) { audioChunks.push( e.data ); };
			mediaRecorder.onstop = function () {
				stream.getTracks().forEach( function ( t ) { t.stop(); } );
				var blob = new Blob( audioChunks, { type: 'audio/webm' } );
				sendAudio( blob );
			};
			mediaRecorder.start();
		} ).catch( function () { micBtn.classList.remove( 'sp-widget-mic--recording' ); micBtn.textContent = '🎤'; } );
	}

	function stopRecording() {
		if ( mediaRecorder && mediaRecorder.state !== 'inactive' ) {
			mediaRecorder.stop();
		}
		isRecording = false;
	}

	function sendAudio( blob ) {
		var reader = new FileReader();
		reader.onload = function () {
			var base64audio = reader.result.split( ',' )[1];
			apiCall( 'voice/transcribe', { audio: base64audio, format: 'webm' } ).then( function ( res ) {
				micBtn.classList.remove( 'sp-widget-mic--recording' );
				micBtn.textContent = '🎤';
				if ( res.ok && res.text ) {
					input.value = res.text;
					sendMsg();
				} else {
					input.value = '(transcription failed)';
				}
			} );
		};
		reader.readAsDataURL( blob );
	}

	// ── Send ──
	function sendMsg() {
		var text = input.value.trim();
		if ( ! text ) { return; }
		input.value = '';
		input.style.height = 'auto';

		addMsg( 'user', text );
		state.messages.push( { role: 'user', content: text } );
		saveState();
		addThinking();

		// Determine which endpoint to use
		var ep = state.convId ? 'widget/ask' : 'widget/ask';
		apiCall( ep, { question: text, conversation_id: state.convId } ).then( function ( res ) {
			removeThinking();
			var answer = res.answer || 'Sorry, I could not process that.';
			addMsg( 'ai', answer, res.sources || [] );
			state.messages.push( { role: 'ai', content: answer, sources: res.sources } );
			state.convId = res.conversation_id || state.convId;
			saveState();

			// Auto-play TTS
			if ( voiceEnabled && cfg.voiceAuto ) {
				var lastMsg = msgs.lastElementChild;
				var vb = lastMsg ? lastMsg.querySelector( '.sp-wm-voice' ) : null;
				if ( vb ) { setTimeout( function () { speakText( answer, vb ); }, 300 ); }
			}
		} ).catch( function () {
			removeThinking();
			addMsg( 'ai', 'Network error. Please try again.' );
		} );
	}

	sendBtn.addEventListener( 'click', sendMsg );
	input.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Enter' && ! e.shiftKey ) { e.preventDefault(); sendMsg(); }
	} );
	input.addEventListener( 'input', function () {
		this.style.height = 'auto';
		this.style.height = Math.min( this.scrollHeight, 80 ) + 'px';
	} );

	// ── Human Handoff ──
	if ( handoffEnabled ) {
		var handoffContainer = document.createElement( 'div' );
		handoffContainer.className = 'sp-widget-handoff';
		var handoffBtn = document.createElement( 'button' );
		handoffBtn.textContent = cfg.handoffText || '👤 Talk to human';
		handoffBtn.addEventListener( 'click', function () {
			handoffBtn.disabled = true;
			handoffContainer.classList.add( 'sp-widget-handoff--sending' );
			handoffBtn.textContent = '⏳ ' + ( cfg.handoffSending || 'Connecting...' );
			apiCall( 'widget/handoff', { messages: state.messages, name: state.name || '', email: state.email || '' } ).then( function ( r ) {
				handoffBtn.textContent = r.ok ? '✅ ' + ( cfg.handoffSent || 'Support notified!' ) : '❌ Failed';
				addMsg( 'ai', cfg.handoffHook || 'You are now connected to human support. They will reply shortly.' );
			} ).catch( function () {
				handoffBtn.textContent = '❌ Error';
			} );
		} );
		handoffContainer.appendChild( handoffBtn );
		msgs.parentElement.appendChild( handoffContainer );
	}

	// ── Restore conversation ──
	state.messages.forEach( function ( m ) {
		addMsg( m.role, m.content, m.sources || [] );
	} );
} )();
