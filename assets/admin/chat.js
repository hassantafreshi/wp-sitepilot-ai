/* global SitePilotChat */
( function () {
	'use strict';

	var cfg = window.SitePilotChat || {};
	var currentConvId = null;
	var waiting = false;

	function $( id ) { return document.getElementById( id ); }

	function autoGrow( node ) {
		if ( ! node ) { return; }
		node.style.height = 'auto';
		node.style.height = Math.min( node.scrollHeight, 140 ) + 'px';
	}

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		attrs = attrs || {};
		Object.keys( attrs ).forEach( function ( k ) {
			if ( k === 'class' ) { node.className = attrs[ k ]; }
			else if ( k === 'text' ) { node.textContent = attrs[ k ]; }
			else if ( k === 'html' ) { node.innerHTML = attrs[ k ]; }
			else { node.setAttribute( k, attrs[ k ] ); }
		} );
		( children || [] ).forEach( function ( c ) {
			if ( c ) { node.appendChild( typeof c === 'string' ? document.createTextNode( c ) : c ); }
		} );
		return node;
	}

	function api( path, body ) {
		return fetch( cfg.restUrl + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( body || {} ),
		} ).then( function ( r ) { return r.json().then( function ( d ) { return { status: r.status, data: d }; } ); } );
	}

	function apiGet( path ) {
		return fetch( cfg.restUrl + path, {
			method: 'GET',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.nonce },
		} ).then( function ( r ) { return r.json().then( function ( d ) { return { status: r.status, data: d }; } ); } );
	}

	function escHtml( s ) {
		var d = document.createElement( 'div' );
		d.textContent = s;
		return d.innerHTML;
	}

	function inlineMd( s ) {
		return escHtml( s )
			.replace( /\*\*(.+?)\*\*/g, '<strong>$1</strong>' )
			.replace( /\*(.+?)\*/g, '<em>$1</em>' )
			.replace( /`(.+?)`/g, '<code>$1</code>' )
			.replace( /\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>' );
	}

	// Small block-level Markdown renderer: paragraphs, headings and lists —
	// so answers read like ChatGPT/Claude instead of one flat wall of text.
	function markdown( text ) {
		var lines = String( text == null ? '' : text ).replace( /\r\n/g, '\n' ).split( '\n' );
		var html = '';
		var listType = null;
		var para = [];

		function flushPara() {
			if ( para.length ) { html += '<p>' + para.join( '<br>' ) + '</p>'; para = []; }
		}
		function closeList() {
			if ( listType ) { html += '</' + listType + '>'; listType = null; }
		}

		lines.forEach( function ( raw ) {
			var trimmed = raw.trim();

			if ( trimmed === '' ) { flushPara(); closeList(); return; }

			var h = trimmed.match( /^(#{1,3})\s+(.*)$/ );
			if ( h ) {
				flushPara(); closeList();
				var lvl = h[ 1 ].length + 2;
				html += '<h' + lvl + '>' + inlineMd( h[ 2 ] ) + '</h' + lvl + '>';
				return;
			}

			var ol = trimmed.match( /^(\d+)[.)]\s+(.*)$/ );
			if ( ol ) {
				flushPara();
				if ( listType !== 'ol' ) { closeList(); html += '<ol>'; listType = 'ol'; }
				html += '<li>' + inlineMd( ol[ 2 ] ) + '</li>';
				return;
			}

			var ul = trimmed.match( /^[-*•]\s+(.*)$/ );
			if ( ul ) {
				flushPara();
				if ( listType !== 'ul' ) { closeList(); html += '<ul>'; listType = 'ul'; }
				html += '<li>' + inlineMd( ul[ 1 ] ) + '</li>';
				return;
			}

			closeList();
			para.push( inlineMd( trimmed ) );
		} );

		flushPara();
		closeList();
		return html;
	}

	/* ── Messages ── */
	function addMessage( role, content, sources, extra ) {
		extra = extra || {};
		var msgs = $( 'sp-chat-messages' );
		// Hide welcome
		var welcome = msgs.querySelector( '.sitepilot-chat-welcome' );
		if ( welcome ) { welcome.hidden = true; }

		var msg = el( 'div', { class: 'sitepilot-msg sitepilot-msg--' + role } );
		var avatar = el( 'div', { class: 'sitepilot-msg__avatar', text: role === 'user' ? '👤' : '🤖' } );
		msg.appendChild( avatar );

		var body = el( 'div', { class: 'sitepilot-msg__body' } );
		body.innerHTML = '<div class="sitepilot-msg__content">' + markdown( content ) + '</div>';

		// Disambiguation chips ("which one did you mean?").
		if ( extra.clarifyOptions && extra.clarifyOptions.length ) {
			var clarify = el( 'div', { class: 'sitepilot-msg__clarify' } );
			extra.clarifyOptions.forEach( function ( opt ) {
				var chip = el( 'button', { class: 'sitepilot-clarify-chip', type: 'button', text: opt } );
				chip.addEventListener( 'click', function () {
					var input = $( 'sp-chat-input' );
					if ( input ) { input.value = opt; }
					ask();
				} );
				clarify.appendChild( chip );
			} );
			body.appendChild( clarify );
		}

		// Sources
		if ( sources && sources.length > 0 ) {
			var srcBox = el( 'div', { class: 'sitepilot-msg__sources' } );
			srcBox.appendChild( el( 'span', { class: 'sitepilot-msg__sources-label', text: '📚 ' + ( cfg.i18nSources || 'Sources' ) } ) );
			sources.forEach( function ( s ) {
				srcBox.appendChild( el( 'a', { class: 'sitepilot-source-pill', href: s.url, target: '_blank', rel: 'noopener', text: s.title } ) );
			} );
			body.appendChild( srcBox );
		}

		// Human handoff offer (only when the engine says handoff is available).
		if ( extra.handoffOffer ) {
			body.appendChild( buildHandoffOffer() );
		}

		if ( role === 'assistant' && 'speechSynthesis' in window ) {
			var audioButton = el( 'button', { class: 'sp-audio-btn', type: 'button', text: '🔊 Listen' } );
			audioButton.addEventListener( 'click', function () {
				if ( ! window.SitePilotVoice ) { return; }
				audioButton.textContent = window.SitePilotVoice.toggleSpeech( content ) ? '⏹ Stop audio' : '🔊 Listen';
			} );
			body.appendChild( audioButton );
		}

		msg.appendChild( body );
		msgs.appendChild( msg );
		msgs.scrollTop = msgs.scrollHeight;
	}

	function collectTranscript() {
		var out = [];
		Array.prototype.forEach.call( document.querySelectorAll( '#sp-chat-messages .sitepilot-msg' ), function ( m ) {
			var role = m.classList.contains( 'sitepilot-msg--user' ) ? 'user' : 'assistant';
			var c = m.querySelector( '.sitepilot-msg__content' );
			out.push( { role: role, content: c ? c.textContent : '' } );
		} );
		return out;
	}

	function buildHandoffOffer() {
		var wrap = el( 'div', { class: 'sitepilot-msg__handoff' } );
		var btn = el( 'button', { class: 'sitepilot-handoff-btn', type: 'button', text: '🙋 ' + ( cfg.i18nTalkHuman || 'Talk to a human' ) } );
		btn.addEventListener( 'click', function () {
			btn.disabled = true;
			btn.textContent = '…';
			api( 'widget/handoff', { messages: collectTranscript(), name: '', email: '' } )
				.then( function ( res ) {
					wrap.textContent = '';
					wrap.appendChild( el( 'span', {
						class: 'sitepilot-handoff-status',
						text: res.data && res.data.ok
							? ( cfg.i18nHandoffSent || 'Your request was sent — a team member will follow up.' )
							: ( ( res.data && res.data.message ) || 'Could not send the request.' ),
					} ) );
				} )
				.catch( function () {
					btn.disabled = false;
					btn.textContent = '🙋 ' + ( cfg.i18nTalkHuman || 'Talk to a human' );
				} );
		} );
		wrap.appendChild( btn );
		return wrap;
	}

	function addTypingIndicator() {
		var msgs = $( 'sp-chat-messages' );
		var ind = el( 'div', { id: 'sp-chat-typing', class: 'sitepilot-msg sitepilot-msg--assistant' } );
		ind.appendChild( el( 'div', { class: 'sitepilot-msg__avatar', text: '🤖' } ) );
		var body = el( 'div', { class: 'sitepilot-msg__body' } );
		body.innerHTML = '<div class="sitepilot-msg__typing"><span></span><span></span><span></span></div>';
		ind.appendChild( body );
		msgs.appendChild( ind );
		msgs.scrollTop = msgs.scrollHeight;
	}

	function removeTypingIndicator() {
		var ind = $( 'sp-chat-typing' );
		if ( ind ) { ind.remove(); }
	}

	/* ── Ask ── */
	function ask() {
		var input = $( 'sp-chat-input' );
		var question = input.value.trim();
		if ( ! question || waiting ) { return; }

		waiting = true;
		input.disabled = true;
		$( 'sp-chat-send' ).disabled = true;

		addMessage( 'user', question, null );
		input.value = '';
		autoGrow( input );
		addTypingIndicator();

		api( 'chat/ask', {
			question: question,
			conversation_id: currentConvId || 0,
		} ).then( function ( res ) {
			removeTypingIndicator();
			waiting = false;
			input.disabled = false;
			$( 'sp-chat-send' ).disabled = false;

			if ( res.data.ok ) {
				currentConvId = res.data.conversation_id;
				addMessage( 'assistant', res.data.answer, res.data.sources, {
					clarifyOptions: res.data.clarify_options || [],
					handoffOffer: !! res.data.handoff_offer,
				} );
				loadConversations();
			} else {
				addMessage( 'assistant', 'Error: ' + ( res.data.message || 'Unknown error' ), [] );
			}
			input.focus();
		} ).catch( function () {
			removeTypingIndicator();
			waiting = false;
			input.disabled = false;
			$( 'sp-chat-send' ).disabled = false;
			addMessage( 'assistant', 'Network error. Please try again.', [] );
		} );
	}

	/* ── Conversation sidebar ── */
	function loadConversations() {
		apiGet( 'chat/list' ).then( function ( res ) {
			var list = $( 'sp-chat-list' );
			list.textContent = '';
			if ( ! res.data.ok || ! res.data.conversations || res.data.conversations.length === 0 ) {
				list.appendChild( el( 'p', { class: 'description', text: 'No conversations yet.' } ) );
				return;
			}
			res.data.conversations.forEach( function ( c ) {
				var item = el( 'div', {
					class: 'sitepilot-chat-item' + ( c.id === currentConvId ? ' sitepilot-chat-item--active' : '' ),
					'data-id': c.id,
				} );
				item.appendChild( el( 'span', { class: 'sitepilot-chat-item__title', text: c.title.substring( 0, 40 ) } ) );
				item.appendChild( el( 'span', { class: 'sitepilot-chat-item__meta', text: c.message_count + ' msgs' } ) );
				item.addEventListener( 'click', function () { loadConversation( c.id ); } );
				list.appendChild( item );
			} );
		} );
	}

	function loadConversation( convId ) {
		currentConvId = convId;
		var msgs = $( 'sp-chat-messages' );
		msgs.textContent = '';

		apiGet( 'chat/get?id=' + convId ).then( function ( res ) {
			if ( ! res.data.ok || ! res.data.conversation ) { return; }
			var conv = res.data.conversation;
			( conv.messages || [] ).forEach( function ( msg ) {
				addMessage( msg.role, msg.content, msg.sources || [] );
			} );
			loadConversations();
		} );
	}

	function newConversation() {
		$( 'sp-chat-messages' ).textContent = '';
		var welcome = document.querySelector( '.sitepilot-chat-welcome' );
		if ( welcome ) { welcome.hidden = false; }
		currentConvId = null;
		$( 'sp-chat-input' ).value = '';
		$( 'sp-chat-input' ).focus();
		loadConversations();
	}

	/* ── Init ── */
	function init() {
		var sendBtn = $( 'sp-chat-send' );
		var input = $( 'sp-chat-input' );

		if ( sendBtn ) {
			sendBtn.addEventListener( 'click', ask );
		}
		if ( input ) {
			input.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' && ! e.shiftKey ) {
					e.preventDefault();
					ask();
				}
			} );
			input.addEventListener( 'input', function () { autoGrow( input ); } );
		}

		$( 'sp-chat-new' ).addEventListener( 'click', newConversation );

		// Suggestion chips
		Array.prototype.forEach.call( document.querySelectorAll( '.sitepilot-chip[data-question]' ), function ( chip ) {
			chip.addEventListener( 'click', function () {
				input.value = chip.getAttribute( 'data-question' ) || '';
				ask();
			} );
		} );

		loadConversations();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
