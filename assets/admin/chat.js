/* global SitePilotChat */
( function () {
	'use strict';

	var cfg = window.SitePilotChat || {};
	var currentConvId = null;
	var waiting = false;

	function $( id ) { return document.getElementById( id ); }

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

	function markdown( text ) {
		return escHtml( text )
			.replace( /\*\*(.*?)\*\*/g, '<strong>$1</strong>' )
			.replace( /\*(.*?)\*/g, '<em>$1</em>' )
			.replace( /`(.*?)`/g, '<code>$1</code>' )
			.replace( /\n/g, '<br>' );
	}

	/* ── Messages ── */
	function addMessage( role, content, sources ) {
		var msgs = $( 'sp-chat-messages' );
		// Hide welcome
		var welcome = msgs.querySelector( '.sitepilot-chat-welcome' );
		if ( welcome ) { welcome.hidden = true; }

		var msg = el( 'div', { class: 'sitepilot-msg sitepilot-msg--' + role } );
		var avatar = el( 'div', { class: 'sitepilot-msg__avatar', text: role === 'user' ? '👤' : '🤖' } );
		msg.appendChild( avatar );

		var body = el( 'div', { class: 'sitepilot-msg__body' } );
		body.innerHTML = '<div class="sitepilot-msg__content">' + markdown( content ) + '</div>';

		// Sources
		if ( sources && sources.length > 0 ) {
			var srcBox = el( 'div', { class: 'sitepilot-msg__sources' } );
			srcBox.appendChild( el( 'strong', { text: '📚 Sources: ' } ) );
			sources.forEach( function ( s, i ) {
				var link = el( 'a', { href: s.url, target: '_blank', text: s.title } );
				srcBox.appendChild( link );
				if ( i < sources.length - 1 ) { srcBox.appendChild( document.createTextNode( ', ' ) ); }
			} );
			body.appendChild( srcBox );
		}

		msg.appendChild( body );
		msgs.appendChild( msg );
		msgs.scrollTop = msgs.scrollHeight;
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
				addMessage( 'assistant', res.data.answer, res.data.sources );
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
