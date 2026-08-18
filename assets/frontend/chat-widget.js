/* global AISAAutoPilotPublicChat */
( function () {
	'use strict';

	var cfg = window.AISAAutoPilotPublicChat || {};
	var root = document.getElementById( 'aisaautopilot-public-chat' );
	if ( ! root || ! cfg.restUrl ) {
		return;
	}

	var labels = cfg.labels || {};
	var history = [];
	var busy = false;
	var recorder = null;
	var stream = null;
	var chunks = [];
	var startedAt = 0;
	var timer = null;
	var recognition = null;
	var voiceInputPrefix = '';
	var voiceFinalText = '';
	var voiceSession = 0;
	var conversationSession = 0;

	function text( key, fallback ) {
		return labels[ key ] || fallback;
	}

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		attrs = attrs || {};
		Object.keys( attrs ).forEach( function ( key ) {
			if ( key === 'class' ) {
				node.className = attrs[ key ];
			} else if ( key === 'text' ) {
				node.textContent = attrs[ key ];
			} else {
				node.setAttribute( key, attrs[ key ] );
			}
		} );
		( children || [] ).forEach( function ( child ) {
			if ( child ) {
				node.appendChild( child );
			}
		} );
		return node;
	}

	function icon( name ) {
		return el( 'span', { class: 'dashicons dashicons-' + name } );
	}

	function responseLanguage( value ) {
		if ( /[\u0600-\u06ff]/.test( value ) ) {
			return /[\u067e\u0686\u0698\u06af]/.test( value ) ? 'fa' : 'ar';
		}
		if ( /[\u0400-\u04ff]/.test( value ) ) {
			return 'ru';
		}
		return cfg.language || 'en';
	}

	function speakReply( value ) {
		if ( ! cfg.voiceResponses || ! value || ! window.speechSynthesis || ! window.SpeechSynthesisUtterance ) {
			return;
		}
		window.speechSynthesis.cancel();
		var utterance = new SpeechSynthesisUtterance( value );
		utterance.lang = responseLanguage( value );
		utterance.rate = 1;
		window.speechSynthesis.speak( utterance );
	}

	var launch = el( 'button', {
		type: 'button',
		class: 'aisaautopilot-public-chat__launch',
		'aria-expanded': 'false',
		'aria-controls': 'aisaautopilot-public-chat-panel',
	}, [
		icon( 'superhero' ),
		el( 'span', { class: 'aisaautopilot-public-chat__launch-label', text: text( 'open', 'Ask AI Site Assistant AutoPilot' ) } ),
	] );

	var panel = el( 'section', {
		id: 'aisaautopilot-public-chat-panel',
		class: 'aisaautopilot-public-chat__panel',
		role: 'dialog',
		'aria-label': text( 'title', 'AI Site Assistant AutoPilot' ),
		'aria-hidden': 'true',
	} );
	panel.hidden = true;

	var title = el( 'div', { class: 'aisaautopilot-public-chat__title' }, [
		icon( 'superhero' ),
		el( 'span', { text: text( 'title', 'AI Site Assistant AutoPilot' ) } ),
	] );
	var reset = el( 'button', {
		type: 'button',
		class: 'aisaautopilot-public-chat__icon',
		title: text( 'newChat', 'New chat' ),
		'aria-label': text( 'newChat', 'New chat' ),
	}, [ icon( 'update' ) ] );
	var close = el( 'button', {
		type: 'button',
		class: 'aisaautopilot-public-chat__icon',
		title: text( 'close', 'Close' ),
		'aria-label': text( 'close', 'Close' ),
	}, [ icon( 'minus' ) ] );
	var head = el( 'div', { class: 'aisaautopilot-public-chat__head' }, [
		title,
		el( 'div', { class: 'aisaautopilot-public-chat__head-actions' }, [ reset, close ] ),
	] );

	var messages = el( 'div', {
		class: 'aisaautopilot-public-chat__messages',
		'aria-live': 'polite',
	} );
	var input = el( 'textarea', {
		class: 'aisaautopilot-public-chat__input',
		rows: '1',
		placeholder: text( 'placeholder', 'Write a message...' ),
	} );
	input.setAttribute( 'aria-label', input.getAttribute( 'placeholder' ) );

	var mic = el( 'button', {
		type: 'button',
		class: 'aisaautopilot-public-chat__voice',
		'aria-label': text( 'mic', 'Speak your question' ),
	}, [ icon( 'microphone' ) ] );
	var send = el( 'button', {
		type: 'submit',
		class: 'aisaautopilot-public-chat__send',
		'aria-label': text( 'send', 'Send' ),
	}, [ icon( 'arrow-up-alt' ) ] );
	var composer = el( 'form', { class: 'aisaautopilot-public-chat__composer' }, [ input, mic, send ] );
	var voiceStatus = el( 'div', {
		class: 'aisaautopilot-public-chat__voice-status',
		role: 'status',
		'aria-live': 'polite',
	} );
	voiceStatus.hidden = true;

	panel.appendChild( head );
	panel.appendChild( messages );
	panel.appendChild( composer );
	panel.appendChild( voiceStatus );
	root.appendChild( launch );
	root.appendChild( panel );

	function markdown( value ) {
		var wrapper = document.createElement( 'div' );
		wrapper.textContent = value;
		return wrapper.innerHTML
			.replace( /\[([^\]\n]+)\]\((https?:\/\/[^\s)]+)\)/g, function ( match, label, url ) {
				var decoder = document.createElement( 'textarea' );
				decoder.innerHTML = url;
				var link = document.createElement( 'a' );
				link.href = decoder.value;
				if ( link.protocol !== 'http:' && link.protocol !== 'https:' ) {
					return match;
				}
				decoder.innerHTML = label;
				link.textContent = decoder.value;
				link.target = '_blank';
				link.rel = 'noopener noreferrer';
				return link.outerHTML;
			} )
			.replace( /\*\*(.*?)\*\*/g, '<strong>$1</strong>' )
			.replace( /\*(.*?)\*/g, '<em>$1</em>' )
			.replace( /\x60(.*?)\x60/g, '<code>$1</code>' )
			.replace( /\n/g, '<br>' );
	}

	function scrollToLatest() {
		messages.scrollTop = messages.scrollHeight;
	}

	function add( role, value, chips ) {
		var row = el( 'div', { class: 'aisaautopilot-public-chat__message aisaautopilot-public-chat__message--' + role } );
		var bubble = el( 'div', { class: 'aisaautopilot-public-chat__bubble' } );
		bubble.innerHTML = markdown( value );

		if ( chips && chips.length ) {
			var chipList = el( 'div', { class: 'aisaautopilot-public-chat__chips' } );
			chips.forEach( function ( label ) {
				var chip = el( 'button', {
					type: 'button',
					class: 'aisaautopilot-public-chat__chip',
					text: label,
				} );
				chip.addEventListener( 'click', function () {
					input.value = label;
					autoGrow();
					ask();
				} );
				chipList.appendChild( chip );
			} );
			bubble.appendChild( chipList );
		}

		row.appendChild( bubble );
		messages.appendChild( row );
		scrollToLatest();
	}

	function addTyping() {
		var row = el( 'div', {
			id: 'aisaautopilot-public-chat-typing',
			class: 'aisaautopilot-public-chat__message aisaautopilot-public-chat__message--assistant',
		} );
		row.appendChild( el( 'div', { class: 'aisaautopilot-public-chat__bubble' }, [
			el( 'span', { class: 'aisaautopilot-public-chat__typing' }, [
				el( 'span' ),
				el( 'span' ),
				el( 'span' ),
			] ),
		] ) );
		messages.appendChild( row );
		scrollToLatest();
	}

	function removeTyping() {
		var typing = document.getElementById( 'aisaautopilot-public-chat-typing' );
		if ( typing ) {
			typing.remove();
		}
	}

	function showVoiceStatus( value ) {
		voiceStatus.textContent = value || '';
		voiceStatus.hidden = ! value;
	}

	function autoGrow() {
		input.style.height = 'auto';
		input.style.height = Math.min( input.scrollHeight, 140 ) + 'px';
	}

	function setBusy( value ) {
		busy = value;
		send.disabled = value;
		mic.disabled = value;
		input.disabled = value;
	}

	function request( path, data ) {
		return fetch( cfg.restUrl + path, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( data ),
		} ).then( function ( response ) {
			return response.json().catch( function () {
				return { ok: false, message: text( 'networkError', 'Network error. Please try again.' ) };
			} );
		} );
	}

	function ask() {
		var question = input.value.trim();
		if ( ! question || busy ) {
			return;
		}

		var requestSession = conversationSession;
		add( 'user', question );
		var prior = history.slice();
		history.push( { role: 'user', text: question } );
		input.value = '';
		autoGrow();
		setBusy( true );
		addTyping();

		request( 'public/chat', { question: question, history: prior } )
			.then( function ( data ) {
				if ( requestSession !== conversationSession ) {
					return;
				}
				var answer = data.answer || data.message || text( 'answerError', 'Sorry, I could not answer that.' );
				add( 'assistant', answer );
				if ( data.ok ) {
					history.push( { role: 'assistant', text: answer } );
					speakReply( answer );
				}
			} )
			.catch( function () {
				if ( requestSession === conversationSession ) {
					add( 'assistant', text( 'networkError', 'Network error. Please try again.' ) );
				}
			} )
			.finally( function () {
				if ( requestSession !== conversationSession ) {
					return;
				}
				removeTyping();
				setBusy( false );
				input.focus();
			} );
	}

	function greeting() {
		if ( messages.childElementCount ) {
			return;
		}
		var suggestions = Array.isArray( labels.suggestions ) && labels.suggestions.length
			? labels.suggestions
			: [ 'What is this website about?', 'How can I contact you?', 'What services do you offer?' ];
		add( 'assistant', text( 'greeting', 'Hi! I can answer questions about this website.' ), suggestions );
	}

	function stopRecognition() {
		if ( recognition ) {
			try {
				recognition.stop();
			} catch ( error ) {}
		}
	}

	function startRecognition( session ) {
		var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
		if ( ! SpeechRecognition ) {
			return;
		}

		voiceFinalText = '';
		recognition = new SpeechRecognition();
		recognition.lang = cfg.language || document.documentElement.lang || 'en';
		recognition.continuous = true;
		recognition.interimResults = true;
		recognition.onresult = function ( event ) {
			if ( session !== voiceSession ) {
				return;
			}
			var interim = '';
			for ( var index = event.resultIndex; index < event.results.length; index++ ) {
				var words = event.results[ index ][ 0 ].transcript;
				if ( event.results[ index ].isFinal ) {
					voiceFinalText += words + ' ';
				} else {
					interim += words;
				}
			}
			input.value = voiceInputPrefix + ( voiceFinalText + interim ).trim();
			autoGrow();
		};
		recognition.onerror = function () {};
		try {
			recognition.start();
		} catch ( error ) {
			recognition = null;
		}
	}

	function cleanVoiceUi() {
		stopRecognition();
		recognition = null;
		if ( timer ) {
			clearTimeout( timer );
			timer = null;
		}
		if ( stream ) {
			stream.getTracks().forEach( function ( track ) {
				track.stop();
			} );
			stream = null;
		}
		mic.classList.remove( 'is-recording' );
		mic.setAttribute( 'aria-label', text( 'mic', 'Speak your question' ) );
		showVoiceStatus( '' );
	}

	function stopRecording() {
		stopRecognition();
		if ( recorder && recorder.state !== 'inactive' ) {
			recorder.stop();
		}
	}

	function cancelVoice() {
		voiceSession++;
		stopRecognition();
		if ( recorder && recorder.state !== 'inactive' ) {
			recorder.stop();
		} else {
			cleanVoiceUi();
		}
	}

	function upload( blob, duration ) {
		var data = new FormData();
		data.append( 'audio', blob, 'aisaautopilot-voice.webm' );
		data.append( 'duration_ms', String( duration ) );
		data.append( 'language', cfg.language || 'en' );
		return fetch( cfg.restUrl + 'public/voice', { method: 'POST', body: data } ).then( function ( response ) {
			return response.json().catch( function () {
				return { ok: false, message: text( 'transcriptionFailed', 'Voice transcription failed. Please try again.' ) };
			} );
		} );
	}

	function voice() {
		if ( busy ) {
			return;
		}
		if ( recorder && recorder.state === 'recording' ) {
			stopRecording();
			return;
		}
		if ( ! navigator.mediaDevices || ! navigator.mediaDevices.getUserMedia || ! window.MediaRecorder ) {
			add( 'assistant', text( 'voiceUnsupported', 'Voice recording is not supported by this browser.' ) );
			return;
		}

		var session = ++voiceSession;
		navigator.mediaDevices.getUserMedia( { audio: true } ).then( function ( audioStream ) {
			if ( session !== voiceSession ) {
				audioStream.getTracks().forEach( function ( track ) {
					track.stop();
				} );
				return;
			}

			voiceInputPrefix = input.value.trim();
			if ( voiceInputPrefix ) {
				voiceInputPrefix += ' ';
			}
			stream = audioStream;
			chunks = [];
			startedAt = Date.now();
			recorder = new MediaRecorder( stream );
			mic.classList.add( 'is-recording' );
			mic.setAttribute( 'aria-label', text( 'stop', 'Stop recording' ) );
			showVoiceStatus( text( 'listening', 'Listening...' ) );

			recorder.ondataavailable = function ( event ) {
				if ( event.data.size ) {
					chunks.push( event.data );
				}
			};
			recorder.onstop = function () {
				var discarded = session !== voiceSession;
				var blob = new Blob( chunks, { type: recorder.mimeType || 'audio/webm' } );
				var duration = Date.now() - startedAt;
				cleanVoiceUi();
				recorder = null;
				if ( discarded ) {
					return;
				}
				if ( ! blob.size || blob.size > 5000000 ) {
					add( 'assistant', text( 'recordingInvalid', 'The recording is empty or too large. Please try a shorter message.' ) );
					return;
				}

				setBusy( true );
				upload( blob, duration )
					.then( function ( data ) {
						if ( session !== voiceSession ) {
							return;
						}
						if ( ! data.ok ) {
							add( 'assistant', data.message || text( 'transcriptionFailed', 'Voice transcription failed. Please try again.' ) );
							return;
						}
						input.value = voiceInputPrefix + ( data.transcript || voiceFinalText ).trim();
						autoGrow();
						input.focus();
						add( 'assistant', text( 'voiceReady', 'Your transcript is ready. Review it, then send.' ) );
					} )
					.catch( function () {
						if ( session === voiceSession ) {
							add( 'assistant', text( 'transcriptionFailed', 'Voice transcription failed. Please try again.' ) );
						}
					} )
					.finally( function () {
						if ( session === voiceSession ) {
							setBusy( false );
						}
					} );
			};

			recorder.start();
			startRecognition( session );
			timer = setTimeout( function () {
				if ( session === voiceSession ) {
					stopRecording();
				}
			}, 45000 );
		} ).catch( function () {
			if ( session === voiceSession ) {
				add( 'assistant', text( 'microphoneDenied', 'Microphone permission was denied. You can type your question instead.' ) );
			}
		} );
	}

	function resetChat() {
		cancelVoice();
		conversationSession++;
		history = [];
		messages.textContent = '';
		input.value = '';
		removeTyping();
		setBusy( false );
		autoGrow();
		greeting();
		input.focus();
	}

	function setOpen( open ) {
		panel.hidden = ! open;
		panel.setAttribute( 'aria-hidden', open ? 'false' : 'true' );
		launch.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		root.classList.toggle( 'is-open', open );
		if ( open ) {
			greeting();
			setTimeout( function () {
				input.focus();
				autoGrow();
			}, 50 );
		} else {
			cancelVoice();
		}
	}

	launch.addEventListener( 'click', function () {
		setOpen( panel.hidden );
	} );
	close.addEventListener( 'click', function () {
		setOpen( false );
	} );
	reset.addEventListener( 'click', resetChat );
	composer.addEventListener( 'submit', function ( event ) {
		event.preventDefault();
		ask();
	} );
	mic.addEventListener( 'click', voice );
	input.addEventListener( 'input', autoGrow );
	input.addEventListener( 'keydown', function ( event ) {
		if ( event.key === 'Enter' && ! event.shiftKey ) {
			event.preventDefault();
			ask();
		}
	} );
	document.addEventListener( 'keydown', function ( event ) {
		if ( event.key === 'Escape' && ! panel.hidden ) {
			setOpen( false );
		}
	} );
}() );
