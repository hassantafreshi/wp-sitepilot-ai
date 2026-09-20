/* global AISAAutoPilotChat */
( function () {
	'use strict';

	var cfg = window.AISAAutoPilotChat || {};
	var T = readJson( 'sp-chat-i18n' );
	var conf = readJson( 'sp-chat-config' );
	var currentConvId = null;
	var waiting = false;
	var loadToken = 0;

	var TRASH_ICON = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg>';
	var SPEAKER_ICON = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M11 5 6 9H3v6h3l5 4V5ZM15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13"/></svg>';

	function readJson( id ) {
		try {
			var node = document.getElementById( id );
			return node ? ( JSON.parse( node.textContent || '{}' ) || {} ) : {};
		} catch ( e ) { return {}; }
	}

	function t( key, fallback ) { return T[ key ] || fallback || key; }

	function $( id ) { return document.getElementById( id ); }

	function ready() { return true === conf.ready; }

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

	function request( method, path, body, query ) {
		var url = ( cfg.restUrl || '' ) + path;
		if ( query ) {
			url += ( url.indexOf( '?' ) === -1 ? '?' : '&' ) + query;
		}
		var init = { method: method, credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce } };
		if ( 'POST' === method ) {
			init.headers[ 'Content-Type' ] = 'application/json';
			init.body = JSON.stringify( body || {} );
		}
		return fetch( url, init ).then( function ( r ) {
			return r.json().catch( function () { return {}; } ).then( function ( d ) {
				return { status: r.status, data: d || {} };
			} );
		} );
	}

	// textContent → innerHTML escapes <, > and & but not quotes. inlineMd() puts
	// the link URL inside href="…", so a quote in an answer would otherwise end
	// the attribute and add its own (an event handler) to the link.
	function escHtml( s ) {
		var d = document.createElement( 'div' );
		d.textContent = s;
		return d.innerHTML.replace( /"/g, '&quot;' ).replace( /'/g, '&#39;' );
	}

	function inlineMd( s ) {
		return escHtml( s )
			.replace( /\*\*(.+?)\*\*/g, '<strong>$1</strong>' )
			.replace( /\*(.+?)\*/g, '<em>$1</em>' )
			.replace( /`(.+?)`/g, '<code>$1</code>' )
			.replace( /\[([^\]]+)\]\((https?:\/\/[^\s)&]+(?:&amp;[^\s)&]+)*)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>' );
	}

	// Small block-level Markdown renderer: paragraphs, headings and lists,
	// so answers read as structured text instead of one flat block.
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

	/* ── Shared shell helpers ── */
	function toast( message ) {
		var shell = window.AISAAutoPilotShell;
		if ( shell && 'function' === typeof shell.toast ) {
			shell.toast( message );
			return;
		}
		setStatus( message );
	}

	function confirmAction( opts ) {
		var shell = window.AISAAutoPilotShell;
		if ( shell && 'function' === typeof shell.confirm ) {
			return shell.confirm( opts );
		}
		return Promise.resolve( window.confirm( opts.title ) );
	}

	function stopSpeech() {
		if ( window.AISAAutoPilotVoice && window.AISAAutoPilotVoice.stopSpeech ) {
			window.AISAAutoPilotVoice.stopSpeech();
		}
	}

	/* ── Composer state ── */
	function setStatus( message, tone ) {
		var status = $( 'sp-chat-status' );
		if ( ! status ) { return; }
		status.textContent = message || '';
		if ( tone ) {
			status.setAttribute( 'data-tone', tone );
		} else {
			status.removeAttribute( 'data-tone' );
		}
	}

	function updateSend() {
		var input = $( 'sp-chat-input' );
		var send = $( 'sp-chat-send' );
		if ( ! input || ! send ) { return; }
		send.disabled = ! ready() || waiting || '' === input.value.trim();
	}

	function setBusy( busy ) {
		var input = $( 'sp-chat-input' );
		var mic = $( 'sp-mic-btn' );
		waiting = busy;
		if ( input ) { input.disabled = busy || ! ready(); }
		// A recording or transcription in progress keeps control of the microphone button.
		if ( mic && ! mic.classList.contains( 'is-recording' ) && 'true' !== mic.getAttribute( 'aria-busy' ) ) {
			mic.disabled = busy || ! ready();
		}
		updateSend();
	}

	// Suggestions and clarifying choices only fill the composer; nothing is sent until the administrator sends it.
	function prefill( text ) {
		var input = $( 'sp-chat-input' );
		if ( ! input || input.disabled ) { return; }
		input.value = text;
		autoGrow( input );
		updateSend();
		input.focus();
		try { input.setSelectionRange( input.value.length, input.value.length ); } catch ( e ) {}
	}

	/* ── Messages ── */
	function showWelcome( show ) {
		var welcome = document.querySelector( '[data-sp-chat-welcome]' );
		if ( welcome ) { welcome.hidden = ! show; }
	}

	function clearMessages() {
		var msgs = $( 'sp-chat-messages' );
		if ( ! msgs ) { return; }
		Array.prototype.forEach.call( msgs.querySelectorAll( '.sp-msg' ), function ( node ) { node.remove(); } );
	}

	function listenButton( textNode ) {
		var button = el( 'button', { type: 'button', class: 'sp-msg__listen', html: SPEAKER_ICON } );
		var label = el( 'span', { text: t( 'listen', 'Listen' ) } );
		button.appendChild( label );

		function setPlaying( playing ) {
			button.classList.toggle( 'is-playing', playing );
			label.textContent = playing ? t( 'stopAudio', 'Stop audio' ) : t( 'listen', 'Listen' );
		}

		button.addEventListener( 'click', function () {
			if ( ! window.AISAAutoPilotVoice ) { return; }
			setPlaying( window.AISAAutoPilotVoice.toggleSpeech( textNode.textContent || '', function () { setPlaying( false ); } ) );
		} );
		return button;
	}

	function addMessage( role, content, extra ) {
		var msgs = $( 'sp-chat-messages' );
		if ( ! msgs ) { return; }
		extra = extra || {};
		showWelcome( false );

		var mine = 'user' === role;
		var msg = el( 'div', { class: 'sp-msg ' + ( mine ? 'sp-msg--me' : 'sp-msg--ai' ) + ( extra.tone ? ' sp-msg--' + extra.tone : '' ) } );
		var body = el( 'div', { class: 'sp-msg__body' } );
		var bubble = el( 'div', { class: 'sp-msg__bubble' } );
		var text = el( 'div', { class: 'sp-msg__text', dir: 'auto' } );
		if ( mine || extra.tone ) {
			text.textContent = content;
		} else {
			text.innerHTML = markdown( content );
		}
		if ( extra.errorTitle ) {
			bubble.appendChild( el( 'p', { class: 'sp-msg__error-title', text: t( 'errorTitle', 'No answer came back' ) } ) );
		}
		bubble.appendChild( text );
		var actions = ( extra.actions || [] ).filter( Boolean );
		if ( actions.length ) {
			var actionRow = el( 'div', { class: 'sp-msg__actions' } );
			actions.forEach( function ( action ) { actionRow.appendChild( action ); } );
			bubble.appendChild( actionRow );
		}

		// "Which one did you mean?" choices.
		if ( extra.clarify && extra.clarify.length ) {
			var choices = el( 'div', { class: 'sp-msg__choices' } );
			extra.clarify.forEach( function ( option ) {
				var choice = el( 'button', { type: 'button', class: 'sp-chat__chip', text: String( option ) } );
				choice.addEventListener( 'click', function () { prefill( String( option ) ); } );
				choices.appendChild( choice );
			} );
			bubble.appendChild( choices );
		}

		var sources = ( extra.sources || [] ).filter( function ( s ) { return s && s.title; } );
		if ( sources.length ) {
			var from = el( 'div', { class: 'sp-msg__sources' } );
			from.appendChild( el( 'span', { class: 'sp-msg__sources-label', text: t( 'sources', 'From:' ) } ) );
			sources.forEach( function ( s ) {
				from.appendChild( /^https?:\/\//i.test( String( s.url || '' ) )
					? el( 'a', { href: s.url, target: '_blank', rel: 'noopener noreferrer', text: s.title } )
					: el( 'span', { class: 'sp-msg__source', text: s.title } ) );
			} );
			bubble.appendChild( from );
		}

		if ( extra.notFound ) {
			var gap = el( 'div', { class: 'sp-msg__gap', 'data-sp-chat-gap': '' } );
			gap.appendChild( el( 'span', { class: 'sp-msg__gap-label', text: t( 'notFound', 'Not in your content' ) } ) );
			if ( conf.gapsUrl ) {
				gap.appendChild( el( 'a', { class: 'sp-msg__gap-link', href: conf.gapsUrl, text: t( 'seeGaps', 'See questions your site could not answer' ) } ) );
			}
			if ( extra.handoffOffer ) {
				gap.appendChild( el( 'span', { class: 'sp-msg__gap-note', text: t( 'handoffNote', 'Visitors are offered a person at this point.' ) } ) );
			}
			bubble.appendChild( gap );
		}

		body.appendChild( bubble );
		if ( ! mine && ! extra.tone && content && 'speechSynthesis' in window ) {
			body.appendChild( listenButton( text ) );
		}
		msg.appendChild( body );
		msgs.appendChild( msg );
		msgs.scrollTop = msgs.scrollHeight;
	}

	function addTypingIndicator() {
		var msgs = $( 'sp-chat-messages' );
		if ( ! msgs ) { return; }
		var indicator = el( 'div', { id: 'sp-chat-typing', class: 'sp-msg sp-msg--ai' } );
		var body = el( 'div', { class: 'sp-msg__body' } );
		body.appendChild( el( 'span', { class: 'sp-typing', 'aria-hidden': 'true', html: '<span></span><span></span><span></span>' } ) );
		body.appendChild( el( 'span', { class: 'sp-sr', text: t( 'thinking', 'Finding an answer…' ) } ) );
		indicator.appendChild( body );
		msgs.appendChild( indicator );
		msgs.scrollTop = msgs.scrollHeight;
	}

	function removeTypingIndicator() {
		var indicator = $( 'sp-chat-typing' );
		if ( indicator ) { indicator.remove(); }
	}

	// "Ask again" sends the same question again, and only when the administrator clicks it.
	function retryButton( question ) {
		var button = el( 'button', { type: 'button', class: 'sp-msg__action', 'data-sp-chat-retry': '', text: t( 'askAgain', 'Ask again' ) } );
		button.addEventListener( 'click', function () {
			var input = $( 'sp-chat-input' );
			if ( ! input || waiting || ! ready() ) { return; }
			input.value = question;
			autoGrow( input );
			ask();
		} );
		return button;
	}

	function linkAction( url, label ) {
		return url ? el( 'a', { class: 'sp-msg__action sp-msg__action--ghost', href: url, text: label } ) : null;
	}

	/* ── Ask ── */
	function ask() {
		var input = $( 'sp-chat-input' );
		var question = input ? input.value.trim() : '';
		if ( ! question || waiting || ! ready() ) { return; }

		var failed = false;
		setBusy( true );
		setStatus( '' );
		addMessage( 'user', question );
		input.value = '';
		autoGrow( input );
		addTypingIndicator();

		request( 'POST', 'chat/ask', { question: question, conversation_id: currentConvId || 0 } ).then( function ( res ) {
			var data = res.data;
			removeTypingIndicator();
			if ( 404 === res.status ) {
				// The conversation was deleted elsewhere; the next question starts a new one.
				currentConvId = null;
			} else if ( data.conversation_id ) {
				currentConvId = Number( data.conversation_id );
			}
			if ( data.ok && 'error' !== data.status ) {
				addMessage( 'assistant', String( data.answer || '' ), {
					sources: data.sources || [],
					clarify: data.clarify_options || [],
					notFound: 'not_found' === data.status,
					handoffOffer: !! data.handoff_offer,
				} );
			} else if ( data.ok ) {
				// The engine caught a provider failure, so no answer exists: say so and offer a retry and Health.
				failed = true;
				addMessage( 'assistant', t( 'errorProvider', 'The AI service did not return an answer. Ask again, or check the connection in Health.' ), {
					tone: 'error',
					errorTitle: true,
					actions: [ retryButton( question ), linkAction( conf.healthUrl, t( 'openHealth', 'Open Health' ) ) ],
				} );
			} else {
				failed = true;
				// The monthly cap would refuse a retry too, so it points to Usage & Cost instead.
				addMessage( 'assistant', data.message || t( 'answerFailed', 'Could not get an answer. Please try again.' ), {
					tone: 'error',
					errorTitle: true,
					actions: 'usage_cap_reached' === data.code
						? [ linkAction( conf.usageUrl, t( 'openUsage', 'Open Usage & Cost' ) ) ]
						: [ retryButton( question ) ],
				} );
			}
			loadConversations();
		} ).catch( function () {
			failed = true;
			removeTypingIndicator();
			addMessage( 'assistant', t( 'networkError', 'Network error. Please try again.' ), { tone: 'error', errorTitle: true, actions: [ retryButton( question ) ] } );
		} ).then( function () {
			setBusy( false );
			// Give an unanswered question back so it can be sent again without retyping.
			if ( failed && '' === input.value.trim() ) {
				input.value = question;
				autoGrow( input );
				updateSend();
			}
			input.focus();
		} );
	}

	/* ── Conversation list ── */
	function formatWhen( value ) {
		if ( ! value ) { return ''; }
		// Stored as UTC MySQL datetimes.
		var date = new Date( String( value ).replace( ' ', 'T' ) + 'Z' );
		if ( isNaN( date.getTime() ) ) { return ''; }
		var now = new Date();
		var startToday = new Date( now.getFullYear(), now.getMonth(), now.getDate() ).getTime();
		var startThen = new Date( date.getFullYear(), date.getMonth(), date.getDate() ).getTime();
		var days = Math.round( ( startToday - startThen ) / 86400000 );
		var lang = document.documentElement.lang || undefined;
		try {
			if ( days <= 0 ) {
				return t( 'today', 'Today' ) + ' · ' + new Intl.DateTimeFormat( lang, { hour: '2-digit', minute: '2-digit' } ).format( date );
			}
			if ( 1 === days ) {
				return t( 'yesterday', 'Yesterday' );
			}
			var options = { month: 'short', day: 'numeric' };
			if ( date.getFullYear() !== now.getFullYear() ) { options.year = 'numeric'; }
			return new Intl.DateTimeFormat( lang, options ).format( date );
		} catch ( e ) {
			return date.toLocaleDateString();
		}
	}

	function note( text ) {
		return el( 'p', { class: 'sp-chat__list-note', text: text } );
	}

	function markActive() {
		Array.prototype.forEach.call( document.querySelectorAll( '#sp-chat-list .sp-chat__thread' ), function ( item ) {
			var on = Number( item.getAttribute( 'data-id' ) ) === currentConvId;
			var open = item.querySelector( '.sp-chat__thread-open' );
			item.classList.toggle( 'is-on', on );
			if ( ! open ) { return; }
			if ( on ) {
				open.setAttribute( 'aria-current', 'true' );
			} else {
				open.removeAttribute( 'aria-current' );
			}
		} );
	}

	function threadItem( conversation ) {
		var id = Number( conversation.id );
		var title = String( conversation.title || '' ).trim() || t( 'untitled', 'New conversation' );
		var item = el( 'li', { class: 'sp-chat__thread', 'data-id': String( id ) } );
		var open = el( 'button', { type: 'button', class: 'sp-chat__thread-open' }, [
			el( 'span', { class: 'sp-chat__thread-title', dir: 'auto', text: title } ),
			el( 'span', { class: 'sp-chat__thread-time', text: formatWhen( conversation.updated_at || conversation.created_at ) } ),
		] );
		var remove = el( 'button', {
			type: 'button',
			class: 'sp-chat__thread-delete',
			html: TRASH_ICON,
			'aria-label': t( 'deleteLabel', 'Delete “%s”' ).replace( '%s', title ),
			title: t( 'deleteLabel', 'Delete “%s”' ).replace( '%s', title ),
		} );
		open.addEventListener( 'click', function () { loadConversation( id ); } );
		remove.addEventListener( 'click', function () { deleteConversation( id ); } );
		item.appendChild( open );
		item.appendChild( remove );
		return item;
	}

	function renderConversations( conversations ) {
		var list = $( 'sp-chat-list' );
		list.textContent = '';
		if ( ! conversations.length ) {
			list.appendChild( note( t( 'noConversations', 'No conversations yet. Your questions appear here.' ) ) );
			return;
		}
		var threads = el( 'ul', { class: 'sp-chat__threads' } );
		conversations.forEach( function ( conversation ) { threads.appendChild( threadItem( conversation ) ); } );
		list.appendChild( threads );
		markActive();
	}

	function loadConversations() {
		var list = $( 'sp-chat-list' );
		if ( ! list ) { return; }
		request( 'GET', 'chat/list' ).then( function ( res ) {
			if ( ! res.data.ok ) { throw new Error( 'list' ); }
			renderConversations( Array.isArray( res.data.conversations ) ? res.data.conversations : [] );
		} ).catch( function () {
			list.textContent = '';
			list.appendChild( note( t( 'listFailed', 'Could not load conversations.' ) ) );
		} ).then( function () {
			list.setAttribute( 'aria-busy', 'false' );
		} );
	}

	function loadConversation( id ) {
		if ( waiting ) { return; }
		var token = ++loadToken;
		currentConvId = id;
		stopSpeech();
		clearMessages();
		showWelcome( false );
		setStatus( '' );
		markActive();

		request( 'GET', 'chat/get', null, 'id=' + encodeURIComponent( id ) ).then( function ( res ) {
			if ( token !== loadToken ) { return; }
			var conversation = res.data.ok ? res.data.conversation : null;
			if ( ! conversation ) { throw new Error( 'get' ); }
			var messages = Array.isArray( conversation.messages ) ? conversation.messages : [];
			if ( ! messages.length ) { showWelcome( true ); }
			messages.forEach( function ( m ) {
				addMessage( 'user' === m.role ? 'user' : 'assistant', String( m.content || '' ), {
					sources: m.sources || [],
					notFound: 'not_found' === m.status,
					// A stored provider failure replays as the error it was, not as an answer.
					tone: 'error' === m.status ? 'error' : '',
					errorTitle: 'error' === m.status,
				} );
			} );
		} ).catch( function () {
			if ( token !== loadToken ) { return; }
			addMessage( 'assistant', t( 'openFailed', 'Could not open this conversation. Please try again.' ), { tone: 'error' } );
		} );
	}

	function resetConversation( focusInput ) {
		var input = $( 'sp-chat-input' );
		loadToken++;
		currentConvId = null;
		stopSpeech();
		clearMessages();
		showWelcome( true );
		setStatus( '' );
		markActive();
		if ( ! input ) { return; }
		input.value = '';
		autoGrow( input );
		updateSend();
		if ( focusInput && ! input.disabled ) { input.focus(); }
	}

	function deleteConversation( id ) {
		if ( waiting ) { return; }
		confirmAction( {
			title: t( 'deleteTitle', 'Delete this conversation?' ),
			body: t( 'deleteBody', 'It is removed from this console for good. Nothing on your site changes.' ),
			confirmLabel: t( 'deleteCta', 'Delete conversation' ),
			danger: true,
		} ).then( function ( yes ) {
			if ( ! yes ) { return; }
			request( 'POST', 'chat/delete', { id: id } ).then( function ( res ) {
				if ( ! res.data.ok ) { throw new Error( 'delete' ); }
				if ( id === currentConvId ) { resetConversation( false ); }
				toast( t( 'deleted', 'Conversation deleted.' ) );
				loadConversations();
				// The deleted row is gone, so keep keyboard focus on a stable control.
				var newButton = $( 'sp-chat-new' );
				if ( newButton ) { newButton.focus(); }
			} ).catch( function () {
				toast( t( 'deleteFailed', 'Could not delete the conversation. Please try again.' ) );
			} );
		} );
	}

	/* ── Init ── */
	function init() {
		var input = $( 'sp-chat-input' );
		var send = $( 'sp-chat-send' );
		var newButton = $( 'sp-chat-new' );
		if ( ! input || ! send ) { return; }

		send.addEventListener( 'click', ask );
		input.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key && ! event.shiftKey && ! event.isComposing ) {
				event.preventDefault();
				ask();
			}
		} );
		input.addEventListener( 'input', function () {
			autoGrow( input );
			updateSend();
		} );

		if ( newButton ) {
			newButton.addEventListener( 'click', function () {
				if ( ! waiting ) { resetConversation( true ); }
			} );
		}

		Array.prototype.forEach.call( document.querySelectorAll( '[data-sp-chat-welcome] [data-question]' ), function ( chip ) {
			chip.addEventListener( 'click', function () { prefill( chip.getAttribute( 'data-question' ) || '' ); } );
		} );

		updateSend();
		loadConversations();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
