/* global AISAAutoPilotAI, AISAAutoPilotDock */
/**
 * Form Studio: one workspace. The canvas shows the gallery, the form taking shape, the plan
 * beside a full-size preview, or the result; the chat beside it carries the conversation, so
 * a plan is changed by asking. Nothing is created until the administrator approves a plan.
 */
( function () {
	'use strict';

	var cfg = window.AISAAutoPilotAI || {};
	var Dock = window.AISAAutoPilotDock;
	var el = Dock.el;
	var icon = Dock.icon;
	var boot = readBoot();
	var T = boot.i18n || {};
	var ready = boot.ready || { studio: true, ai: true, recipient: '' };

	var actionId = null;
	var preview = null;
	var expiresAt = null;
	var expiryTimer = null;
	var lastPrompt = '';
	var working = false;
	var checking = false;

	var recorder = null;
	var recorderStream = null;
	var chunks = [];
	var recordingTimer = null;

	// ── helpers ─────────────────────────────────────────────────

	function readBoot() {
		var node = document.getElementById( 'sp-copilot-boot' );
		if ( ! node ) { return {}; }
		try { return JSON.parse( node.textContent ) || {}; } catch ( e ) { return {}; }
	}

	/** A translated string; %s, %d and %1$s take the values in order. */
	function t( key, fallback, values ) {
		var text = T[ key ] || fallback || key;
		if ( undefined === values ) { return text; }
		var list = Array.isArray( values ) ? values : [ values ];
		var next = 0;
		return String( text ).replace( /%(?:(\d+)\$)?[sd]/g, function ( match, position ) {
			var i = position ? Number( position ) - 1 : next++;
			return i < list.length ? String( list[ i ] ) : match;
		} );
	}

	function byId( id ) { return document.getElementById( id ); }
	function root() { return document.querySelector( '[data-aisaautopilot-copilot]' ); }
	function isReady() { return !! ( ready.studio && ready.ai && ready.recipient ); }

	function request( path, body ) {
		// WordPress honours _locale=user only for JSON requests, so server-built notes match the admin language.
		var url = cfg.restUrl + path;
		return fetch( url + ( url.indexOf( '?' ) < 0 ? '?' : '&' ) + '_locale=user', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( body )
		} ).then( responseData );
	}

	function responseData( response ) {
		return response.json().catch( function () { return {}; } ).then( function ( data ) {
			return { ok: response.ok, data: data };
		} );
	}

	function toast( message ) {
		var node = byId( 'sp-toast' );
		if ( ! node ) { return; }
		node.textContent = message;
		node.hidden = false;
		window.clearTimeout( node._t );
		node._t = window.setTimeout( function () { node.hidden = true; }, 2600 );
	}

	function busy( on, label ) {
		var node = byId( 'sp-busy' );
		if ( ! node ) { return; }
		if ( label ) { byId( 'sp-busy-label' ).textContent = label; }
		node.hidden = ! on;
	}

	function setStatus( message ) {
		var node = byId( 'aisaautopilot-status' );
		if ( ! node ) { return; }
		node.textContent = message || '';
		node.classList.toggle( 'is-visible', !! message );
	}

	/* The workspace fills the screen below whatever WordPress shows above it (notices included). */
	function fitWorkspace() {
		var work = document.querySelector( '.sp-fc-work' );
		if ( ! work ) { return; }
		var top = work.getBoundingClientRect().top + window.scrollY;
		work.style.blockSize = Math.max( 520, window.innerHeight - top - 20 ) + 'px';
	}

	function setCtx( text ) { var node = document.querySelector( '[data-sp-fc-ctx]' ); if ( node ) { node.textContent = text; } }

	var TYPES = { text: [ 'typeText', 'Text', 'text' ], email: [ 'typeEmail', 'Email', 'mail' ], tel: [ 'typeTel', 'Phone', 'phone' ], textarea: [ 'typeTextarea', 'Long text', 'para' ], select: [ 'typeSelect', 'Dropdown', 'select' ], checkbox: [ 'typeCheckbox', 'Checkboxes', 'checkbox' ], radio: [ 'typeRadio', 'Choice', 'radio' ] };
	function typeLabel( type ) { var entry = TYPES[ type ]; return entry ? t( entry[ 0 ], entry[ 1 ] ) : String( type || '' ); }
	function typeIcon( type ) { var entry = TYPES[ type ]; return entry ? entry[ 2 ] : 'text'; }

	// ── the chat ────────────────────────────────────────────────

	function thread() { return byId( 'aisaautopilot-thread' ); }
	function scrollDown() { var node = thread(); if ( node ) { node.scrollTop = node.scrollHeight; } }
	function addUser( text ) {
		thread().appendChild( el( 'div', { class: 'aisa-ai-user', dir: 'auto', text: text } ) );
		scrollDown();
	}
	function addAi( content ) {
		var body = el( 'div', { class: 'aisa-ai-body' } );
		( Array.isArray( content ) ? content : [ content ] ).forEach( function ( part ) {
			if ( 'string' === typeof part ) { if ( part ) { body.appendChild( el( 'div', { class: 'aisa-ai-text', dir: 'auto', text: part } ) ); } } else if ( part ) { body.appendChild( part ); }
		} );
		thread().appendChild( el( 'div', { class: 'aisa-ai-row' }, [ el( 'span', { class: 'aisa-ai-av', 'aria-hidden': 'true' }, [ icon( 'spark', 15, 1.9 ) ] ), body ] ) );
		scrollDown();
		return body;
	}
	function card( head, children, attrs ) { return el( 'div', Object.assign( { class: 'aisa-ai-card' }, attrs || {} ), [ head ].concat( children || [] ) ); }
	function button( label, opts ) {
		opts = opts || {};
		var node = el( 'button', Object.assign( { type: 'button', class: 'aisa-ai-btn' + ( opts.kind ? ' aisa-ai-btn--' + opts.kind : '' ) + ( opts.size ? ' aisa-ai-btn--' + opts.size : '' ) + ( opts.grow ? ' aisa-ai-btn--grow' : '' ) }, opts.attrs || {} ), [ opts.icon ? icon( opts.icon, 14, opts.stroke || 2 ) : null, el( 'span', { text: label } ) ] );
		if ( opts.onClick ) { node.addEventListener( 'click', opts.onClick ); }
		return node;
	}
	function linkButton( label, href, opts ) {
		opts = opts || {};
		return el( 'a', { class: 'aisa-ai-btn' + ( opts.kind ? ' aisa-ai-btn--' + opts.kind : '' ) + ( opts.size ? ' aisa-ai-btn--' + opts.size : '' ), href: href, target: opts.newTab ? '_blank' : null, rel: opts.newTab ? 'noopener' : null }, [ el( 'span', { text: label } ), opts.newTab ? icon( 'ext', 13 ) : null ] );
	}
	function prefill( text ) {
		var input = byId( 'aisaautopilot-prompt' );
		if ( ! input || input.disabled ) { return; }
		input.value = String( text || '' );
		Dock.autoGrow( input, 180 );
		showTab( 'chat' );
		input.focus();
		try { input.setSelectionRange( input.value.length, input.value.length ); } catch ( e ) {}
	}
	function chip( text, label ) {
		var node = el( 'button', { type: 'button', class: 'aisa-ai-chip', dir: 'auto', 'data-sp-fc-chip': '1' }, [ icon( 'spark', 13 ), el( 'span', { text: label || text } ) ] );
		node.addEventListener( 'click', function () { prefill( text ); } );
		return node;
	}
	function setSuggestions( list ) {
		var box = byId( 'aisaautopilot-suggest' );
		if ( ! box ) { return; }
		box.textContent = '';
		( list || [] ).slice( 0, 3 ).forEach( function ( text ) { box.appendChild( chip( text ) ); } );
	}

	// ── the canvas ──────────────────────────────────────────────

	function setCanvas( state ) {
		var node = root();
		if ( node ) { node.setAttribute( 'data-sp-fc-state', state ); }
		var empty = document.querySelector( '[data-sp-form-empty]' );
		var ghost = document.querySelector( '[data-sp-form-ghost]' );
		if ( empty ) { empty.hidden = 'start' !== state; }
		if ( ghost ) { ghost.hidden = 'drafting' !== state; }
		byId( 'aisaautopilot-review-screen' ).hidden = 'plan' !== state;
		byId( 'aisaautopilot-result-screen' ).hidden = 'result' !== state;
		var bar = document.querySelector( '[data-sp-fc-stickybar]' );
		if ( bar ) { bar.hidden = 'plan' !== state || ! actionId || isExpired(); }
		var canvas = byId( 'sp-fc-canvas' );
		if ( canvas ) { canvas.scrollTop = 0; }
		// On a phone the chat and the canvas are two tabs; a new plan or result marks the other one.
		if ( ( 'plan' === state || 'result' === state ) && node && 'preview' !== node.getAttribute( 'data-sp-fc-tab' ) ) {
			var dot = document.querySelector( '[data-sp-fc-tab-dot]' );
			if ( dot ) { dot.hidden = false; }
		}
	}

	function showTab( tab ) {
		var node = root();
		if ( ! node ) { return; }
		node.setAttribute( 'data-sp-fc-tab', tab );
		Array.prototype.forEach.call( document.querySelectorAll( '[data-sp-fc-tab]' ), function ( btn ) {
			if ( 'BUTTON' === btn.tagName ) { btn.setAttribute( 'aria-selected', btn.getAttribute( 'data-sp-fc-tab' ) === tab ? 'true' : 'false' ); }
		} );
		if ( 'preview' === tab ) { var dot = document.querySelector( '[data-sp-fc-tab-dot]' ); if ( dot ) { dot.hidden = true; } }
	}

	function renderPlanCanvas( plan ) {
		var content = byId( 'aisaautopilot-review-content' );
		content.textContent = '';
		var rows = el( 'ul', { class: 'sp-fc-fieldlist' } );
		( plan.fields || [] ).forEach( function ( field ) {
			var meta = typeLabel( field.type ) + ( ( field.options || [] ).length ? ' · ' + field.options.join( ', ' ) : '' );
			rows.appendChild( el( 'li', {}, [
				el( 'span', { class: 'aisa-ai-ic' }, [ icon( typeIcon( field.type ), 14 ) ] ),
				el( 'span', { class: 'sp-fc-fieldlist__main', dir: 'auto' }, [ field.label || '', field.required ? el( 'span', { class: 'sp-fc-req', text: ' *' } ) : null, el( 'small', { text: meta } ) ] )
			] ) );
		} );
		var formNode = previewForm( plan );
		var desktop = el( 'button', { type: 'button', class: 'sp-seg__btn is-on', 'aria-pressed': 'true' }, [ icon( 'monitor', 14 ), el( 'span', { text: t( 'desktop', 'Desktop' ) } ) ] );
		var mobile = el( 'button', { type: 'button', class: 'sp-seg__btn', 'aria-pressed': 'false' }, [ icon( 'mobile', 14 ), el( 'span', { text: t( 'mobile', 'Mobile' ) } ) ] );
		desktop.addEventListener( 'click', function () { desktop.classList.add( 'is-on' ); mobile.classList.remove( 'is-on' ); desktop.setAttribute( 'aria-pressed', 'true' ); mobile.setAttribute( 'aria-pressed', 'false' ); formNode.classList.remove( 'is-mobile' ); } );
		mobile.addEventListener( 'click', function () { mobile.classList.add( 'is-on' ); desktop.classList.remove( 'is-on' ); mobile.setAttribute( 'aria-pressed', 'true' ); desktop.setAttribute( 'aria-pressed', 'false' ); formNode.classList.add( 'is-mobile' ); } );
		content.appendChild( el( 'div', { class: 'sp-fc-plan__grid', 'data-sp-form-review-canvas': '' }, [
			el( 'section', { class: 'sp-fc-plan__fields', 'aria-label': t( 'fields', 'Fields' ) }, [
				el( 'h2', {}, [ t( 'fields', 'Fields' ), el( 'span', { class: 'aisa-ai-tag', text: String( ( plan.fields || [] ).length ) } ) ] ),
				rows,
				el( 'p', { class: 'sp-fc-plan__never' }, [ icon( 'shield', 14 ), el( 'span', { text: t( 'neverIncluded', 'Native forms never include payments, passwords or sign-ups.' ) } ) ] )
			] ),
			el( 'section', { class: 'sp-fc-plan__preview', 'aria-label': t( 'preview', 'Preview' ) }, [
				el( 'div', { class: 'sp-fc-plan__bar' }, [ el( 'span', { text: t( 'preview', 'Preview' ) } ), el( 'div', { class: 'sp-seg' }, [ desktop, mobile ] ) ] ),
				el( 'div', { class: 'sp-preview' }, [ formNode ] )
			] )
		] ) );
	}

	function previewForm( plan ) {
		var form = el( 'form', { class: 'sp-preview__form' } );
		form.addEventListener( 'submit', function ( e ) { e.preventDefault(); } );
		form.appendChild( el( 'div', {}, [
			el( 'h3', { class: 'sp-preview__title', dir: 'auto', text: plan.title || '' } ),
			plan.description ? el( 'p', { class: 'sp-preview__lede', dir: 'auto', text: plan.description } ) : null
		] ) );
		( plan.fields || [] ).forEach( function ( field, index ) { form.appendChild( previewField( field, index ) ); } );
		form.appendChild( el( 'button', { type: 'button', class: 'sp-preview__submit', text: plan.submit_button || 'Send' } ) );
		return form;
	}

	function previewField( field, index ) {
		var id = 'sp-pf-' + index;
		var control;
		var options = field.options || [];
		if ( 'textarea' === field.type ) {
			control = el( 'textarea', { id: id, disabled: true } );
		} else if ( 'select' === field.type ) {
			control = el( 'select', { id: id, disabled: true } );
			options.forEach( function ( option ) { control.appendChild( el( 'option', { text: option } ) ); } );
		} else if ( 'radio' === field.type || 'checkbox' === field.type ) {
			control = el( 'div', { class: 'sp-stack sp-stack--sm' } );
			options.forEach( function ( option, i ) {
				control.appendChild( el( 'label', { class: 'sp-check' }, [ el( 'input', { type: field.type, name: id, id: id + '-' + i, disabled: true } ), el( 'span', { text: option } ) ] ) );
			} );
		} else {
			var type = [ 'email', 'tel', 'text' ].indexOf( field.type ) !== -1 ? field.type : 'text';
			control = el( 'input', { id: id, type: type, dir: 'text' === type ? null : 'ltr', disabled: true } );
		}
		return el( 'label', { class: 'sp-preview__field', for: id }, [
			el( 'span', { class: 'sp-preview__label', dir: 'auto' }, [ el( 'span', { text: field.label || '' } ), field.required ? el( 'span', { class: 'sp-preview__req', text: ' *' } ) : null ] ),
			control
		] );
	}

	function renderResultCanvas( result ) {
		var content = byId( 'aisaautopilot-result-content' );
		content.textContent = '';
		var ok = result && result.form_id;
		var draft = ! result || false !== result.is_draft;
		var body = el( 'div', { class: 'sp-fc-result__card ' + ( ok ? 'sp-fc-result__card--ok' : 'sp-fc-result__card--bad' ), 'data-sp-form-result': '', 'data-sp-form-result-canvas': '' }, [
			el( 'div', { class: 'sp-fc-result__head' }, [
				el( 'span', { class: 'sp-fc-result__icon' }, [ ok ? icon( 'check', 22, 2.4 ) : icon( 'alert', 20 ) ] ),
				el( 'div', {}, [
					el( 'h2', { text: ok ? ( draft ? t( 'okTitle' ) : t( 'okTitlePub' ) ) : t( 'failTitle' ) } ),
					el( 'p', { dir: 'auto', text: ok ? t( draft ? 'okBodyDraft' : 'okBodyPublished', '', ( preview && preview.title ) || '' ) : ( result && result.message ? result.message : t( 'genericError' ) ) } )
				] )
			] )
		] );
		if ( ok && result.shortcode ) {
			var input = el( 'input', { class: 'sp-input sp-input--r9 sp-mono', dir: 'ltr', readonly: true, value: result.shortcode } );
			var copy = button( t( 'copy' ), { icon: 'doc' } );
			copy.addEventListener( 'click', function () {
				input.select();
				if ( navigator.clipboard && navigator.clipboard.writeText ) { navigator.clipboard.writeText( result.shortcode ); } else { try { document.execCommand( 'copy' ); } catch ( e ) {} }
				toast( t( 'copied' ) );
			} );
			body.appendChild( el( 'div', { class: 'sp-fc-result__shortcode' }, [ el( 'label', { class: 'sp-label', text: t( 'shortcode' ) } ), el( 'div', { class: 'sp-row-wrap' }, [ input, copy ] ) ] ) );
			body.appendChild( el( 'div', { class: 'sp-fc-result__delivery' }, [ icon( 'mail', 14 ), el( 'span', { text: t( 'entriesEmailTo' ) + ' ' + ( ( preview && preview.notification_email ) || t( 'emailToAdmin' ) ) } ) ] ) );
		}
		var actions = [ button( ok ? t( 'buildAnother' ) : t( 'tryAgain' ), { kind: 'primary', attrs: { 'data-sp-form-build-another': '' }, onClick: newForm } ) ];
		if ( ok && result.edit_url ) { actions.push( linkButton( draft ? t( 'openDraft' ) : t( 'openForm' ), result.edit_url ) ); }
		if ( ok && boot.efbReady ) { actions.push( linkButton( t( 'editInEfb' ), boot.efbUrl ) ); }
		if ( boot.historyUrl ) { actions.push( linkButton( t( 'viewInActivity' ), boot.historyUrl, { kind: 'quiet' } ) ); }
		body.appendChild( el( 'div', { class: 'sp-fc-result__actions' }, actions ) );
		content.appendChild( body );
		return ok;
	}

	// ── readiness ───────────────────────────────────────────────

	function checklist() {
		var rows = [
			[ ready.studio, t( 'studioOn', 'Form Studio is on' ), t( 'studioOff', 'Turn on Form Studio' ), t( 'studioOffNote' ), t( 'openIntegrations', 'Open Integrations' ), boot.urls && boot.urls.integrations ],
			[ ready.ai, t( 'aiOn', 'An AI service is connected' ), t( 'aiOff', 'Connect an AI service' ), t( 'aiOffNote' ), t( 'setUp', 'Set up' ), boot.urls && boot.urls.setup ],
			[ !! ready.recipient, t( 'recipientOn', 'Answers go to %s', ready.recipient ), t( 'recipientOff', 'Choose where answers are emailed' ), t( 'recipientOffNote' ), t( 'setEmail', 'Set email' ), boot.urls && boot.urls.settings ]
		];
		return card( Dock.cardHead( 'warn', 'plug', t( 'ctxSetup', 'Setup needed' ) ), rows.map( function ( row ) {
			return el( 'div', { class: 'aisa-ai-check', 'data-sp-fc-check': row[ 0 ] ? 'ok' : 'todo' }, [
				el( 'span', { class: 'aisa-ai-st' + ( row[ 0 ] ? '' : ' aisa-ai-st--todo' ) }, [ icon( row[ 0 ] ? 'check' : 'plug', 12, 3 ) ] ),
				el( 'span', { class: 'aisa-ai-main', dir: 'auto' }, row[ 0 ] ? [ row[ 1 ] ] : [ el( 'strong', { text: row[ 2 ] } ), el( 'small', { text: row[ 3 ] } ) ] ),
				row[ 0 ] || ! row[ 5 ] ? null : linkButton( row[ 4 ], row[ 5 ], { kind: 'primary', size: 'sm', newTab: true } )
			] );
		} ), { 'data-sp-fc-checklist': '' } );
	}

	function applyReadiness() {
		var on = isReady();
		var chat = byId( 'sp-fc-chat' );
		if ( chat ) { chat.classList.toggle( 'is-offline', ! on ); }
		var input = byId( 'aisaautopilot-prompt' );
		if ( input ) {
			input.disabled = ! on;
			input.placeholder = on ? t( 'placeholderReady', 'Describe the form: who fills it in and what you need to know…' ) : t( 'placeholderOff', 'Finish the setup to start…' );
			input.parentNode.classList.toggle( 'is-off', ! on );
		}
		[ 'aisaautopilot-generate', 'aisaautopilot-voice-toggle' ].forEach( function ( id ) { var node = byId( id ); if ( node ) { node.disabled = ! on; } } );
		Array.prototype.forEach.call( document.querySelectorAll( '#aisaautopilot-examples [data-example]' ), function ( node ) { node.disabled = ! on; } );
	}

	/* Back from setup in another tab: check again, no reload needed. */
	function recheck() {
		if ( isReady() || checking || ! cfg.restUrl ) { return; }
		checking = true;
		fetch( cfg.restUrl + 'ai/status', { credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce } } ).then( function ( r ) { return r.json(); } ).then( function ( data ) {
			if ( ! data || ! data.ok ) { return; }
			ready = { studio: !! data.form_studio, ai: !! data.configured, recipient: String( data.recipient || '' ) };
			var old = document.querySelector( '[data-sp-fc-checklist]' );
			if ( isReady() ) {
				if ( old ) { old.remove(); }
				applyReadiness();
				setCtx( t( 'ctxNew', 'New native form' ) );
				addAi( card( Dock.cardHead( 'ok', 'check', t( 'readyNow', 'All set. Describe the form you need, or pick an example.' ) ), [], { 'data-sp-fc-ready': '' } ) );
			} else if ( old ) {
				old.parentNode.replaceChild( checklist(), old );
			}
		} ).catch( function () {} ).then( function () { checking = false; } );
	}

	// ── the conversation ────────────────────────────────────────

	function greet() {
		if ( isReady() ) {
			addAi( t( 'greet' ) );
			setCtx( t( 'ctxNew', 'New native form' ) );
			return;
		}
		addAi( [ t( 'readyIntro' ), checklist() ] );
		setCtx( t( 'ctxSetup', 'Setup needed' ) );
	}

	function planSummary( plan ) {
		var fields = ( plan.fields || [] ).map( function ( field ) {
			return field.label + ' (' + field.type + ( field.required ? ', required' : '' ) + ( ( field.options || [] ).length ? ', options: ' + field.options.join( '/' ) : '' ) + ')';
		} );
		return '“' + ( plan.title || '' ) + '” with ' + fields.join( '; ' ) + ( plan.submit_button ? '; button “' + plan.submit_button + '”' : '' );
	}

	function submit() {
		var input = byId( 'aisaautopilot-prompt' );
		var text = input ? ( input.value || '' ).trim() : '';
		if ( ! text ) { setStatus( t( 'promptFirst' ) ); return; }
		if ( working || ! isReady() ) { return; }
		addUser( text );
		input.value = '';
		Dock.autoGrow( input, 180 );
		// With a plan on the canvas, the message asks for a change to it.
		var prompt = actionId && preview && ! isExpired() ? t( 'revisePrompt', 'Change this form plan. Current plan: %1$s. Change: %2$s', [ planSummary( preview ), text ] ) : text;
		draft( prompt.slice( 0, 2000 ) );
	}

	function draft( prompt ) {
		working = true;
		lastPrompt = prompt;
		setSuggestions( [] );
		setStatus( '' );
		var hadPlan = !! actionId;
		byId( 'aisaautopilot-generate' ).disabled = true;
		var steps = addAi( Dock.steps( [ [ 'done', t( 'stepUnderstood', 'Understood the request' ) ], [ 'run', t( 'stepChoosing', 'Choosing the fields…' ) ], [ 'wait', t( 'stepRules', 'Checking them against the native-form rules' ) ] ] ) );
		steps.parentNode.setAttribute( 'data-sp-fc-thinking', '' );
		setCanvas( 'drafting' );
		setCtx( t( 'ctxDrafting', 'Drafting a plan…' ) );
		request( 'forms/plan', { prompt: prompt } ).then( function ( response ) {
			finishDraft( steps );
			if ( ! response.ok || ! response.data.ok ) { failed( response.data || {}, hadPlan ); return; }
			planned( response.data );
		} ).catch( function () {
			finishDraft( steps );
			failed( { message: t( 'networkError' ) }, hadPlan );
		} );
	}

	function finishDraft( steps ) {
		working = false;
		var send = byId( 'aisaautopilot-generate' );
		if ( send ) { send.disabled = ! isReady(); }
		if ( steps && steps.parentNode ) { steps.parentNode.remove(); }
	}

	function failed( data, hadPlan ) {
		var message = data.message || t( 'genericError' );
		var errors = Array.isArray( data.errors ) ? data.errors : [];
		var examples = ( boot.examples || [] ).slice( 0, 2 );
		addAi( card( Dock.cardHead( 'warn', 'alert', t( 'notBuilt', 'I could not build that yet' ) ), [
			el( 'div', { class: 'aisa-ai-card__body' }, [ el( 'p', { dir: 'auto', text: message } ), errors.length ? el( 'p', { dir: 'auto', text: errors.join( ' ' ) } ) : null, el( 'p', { text: t( 'tryExamples' ) } ) ] ),
			el( 'div', { class: 'aisa-ai-card__foot' }, examples.map( function ( example ) { return chip( example.prompt, example.title ); } ).concat( [
				button( t( 'tryAgain', 'Try again' ), { size: 'sm', icon: 'refresh', onClick: function () { if ( lastPrompt && ! working ) { draft( lastPrompt ); } } } )
			] ) )
		], { 'data-sp-fc-error': '' } ) );
		if ( hadPlan && preview && ! isExpired() ) { setCanvas( 'plan' ); startExpiryTimer(); } else { setCanvas( 'start' ); setCtx( t( 'ctxNew', 'New native form' ) ); }
	}

	function retirePlanCards() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-sp-fc-plan-card]' ), function ( node ) {
			node.removeAttribute( 'data-sp-fc-plan-card' );
			Array.prototype.forEach.call( node.querySelectorAll( 'button' ), function ( btn ) { btn.disabled = true; btn.removeAttribute( 'id' ); } );
			node.classList.add( 'is-retired' );
		} );
	}

	function planned( data ) {
		retirePlanCards();
		actionId = data.action_id;
		preview = data.preview;
		expiresAt = data.expires_at ? Number( data.expires_at ) * 1000 : Date.now() + 900000;
		var draftOnly = 'publish' !== preview.default_status;
		var risk = String( preview.risk_level || 'low' ).toLowerCase();
		var riskLabel = 'high' === risk ? t( 'high' ) : ( 'medium' === risk ? t( 'medium' ) : t( 'low' ) );
		var list = el( 'ul', { class: 'aisa-ai-rows' }, [
			draftOnly ? Dock.row( '', 'save', t( 'savedAsDraft', 'Saved as a draft' ), t( 'notVisible', 'Not visible on your site' ) ) : Dock.row( 'fail', 'alert', t( 'publishedState' ), t( 'publishedNote' ) ),
			Dock.row( '', 'mail', t( 'answersTo', 'Answers emailed to %s', preview.notification_email || t( 'emailToAdmin' ) ) ),
			Dock.row( 'high' === risk ? 'fail' : 'add', 'shield', t( 'riskIs', 'Risk: %s', riskLabel ), t( 'neverIncluded' ) )
		] );
		var approve = button( draftOnly ? t( 'approveDraft' ) : t( 'approvePublish' ), { kind: 'primary', size: 'lg', grow: true, icon: 'check', stroke: 2.4, attrs: { id: 'aisaautopilot-approve' }, onClick: function () { if ( draftOnly ) { approvePlan(); } else { openConfirm(); } } } );
		var reject = button( t( 'reject', 'Reject' ), { kind: 'quiet', size: 'lg', onClick: rejectPlan } );
		var planCard = card( Dock.cardHead( 'plan', 'doc', preview.title || t( 'createWithAi' ), t( 'fieldCount', '%d fields ready to review', ( preview.fields || [] ).length ) ), [ list, el( 'div', { class: 'aisa-ai-card__foot' }, [ approve, reject ] ) ], { 'data-sp-fc-plan-card': '' } );
		addAi( [ t( 'planReady' ), preview.notes ? String( preview.notes ) : '', planCard ] );
		renderPlanCanvas( preview );
		setCanvas( 'plan' );
		stickyBar();
		setSuggestions( nextSuggestions( preview ) );
		startExpiryTimer();
	}

	function nextSuggestions( plan ) {
		var next = [];
		var fields = plan.fields || [];
		var optional = fields.filter( function ( field ) { return field.required && 'email' !== field.type; } )[ 1 ];
		if ( optional ) { next.push( t( 'nextOptional', 'Make “%s” optional', optional.label ) ); }
		if ( ! fields.some( function ( field ) { return 'tel' === field.type; } ) ) { next.push( t( 'nextPhone', 'Add a phone number field' ) ); }
		next.push( t( 'nextButtonText', 'Change the text of the send button' ) );
		return next;
	}

	/* On a phone, the preview tab keeps the approval at hand. */
	function stickyBar() {
		var bar = document.querySelector( '[data-sp-fc-stickybar]' );
		if ( ! bar ) { return; }
		bar.textContent = '';
		var draftOnly = preview && 'publish' !== preview.default_status;
		bar.appendChild( button( draftOnly ? t( 'approveDraft' ) : t( 'approvePublish' ), { kind: 'primary', size: 'lg', grow: true, icon: 'check', stroke: 2.4, attrs: { 'data-sp-fc-approve-mobile': '' }, onClick: function () { var main = byId( 'aisaautopilot-approve' ); if ( main && ! main.disabled ) { main.click(); } } } ) );
		bar.hidden = false;
	}

	// ── expiry ──────────────────────────────────────────────────

	function isExpired() { return ! expiresAt || Date.now() >= expiresAt; }
	function clockLabel() {
		var left = Math.max( 0, Math.floor( ( expiresAt - Date.now() ) / 1000 ) );
		var mm = Math.floor( left / 60 );
		var ss = left % 60;
		return mm + ':' + ( ss < 10 ? '0' : '' ) + ss;
	}
	function startExpiryTimer() {
		window.clearInterval( expiryTimer );
		var tick = function () {
			if ( ! actionId ) { window.clearInterval( expiryTimer ); return; }
			if ( isExpired() ) {
				window.clearInterval( expiryTimer );
				expirePlan();
				return;
			}
			setCtx( t( 'ctxPlan', 'Plan ready · valid for %s', clockLabel() ) );
		};
		tick();
		expiryTimer = window.setInterval( tick, 1000 );
	}
	function expirePlan() {
		setCtx( t( 'ctxExpired', 'Plan expired' ) );
		var approve = byId( 'aisaautopilot-approve' );
		if ( approve ) { approve.disabled = true; approve.querySelector( 'span' ).textContent = t( 'cannotApprove', 'Expired' ); }
		var bar = document.querySelector( '[data-sp-fc-stickybar]' );
		if ( bar ) { bar.hidden = true; }
		addAi( card( Dock.cardHead( 'warn', 'alert', t( 'ctxExpired', 'Plan expired' ) ), [
			el( 'div', { class: 'aisa-ai-card__body' }, [ el( 'p', { text: t( 'planExpired' ) } ) ] ),
			el( 'div', { class: 'aisa-ai-card__foot' }, [ button( t( 'askAgain', 'Draft it again' ), { size: 'sm', icon: 'refresh', onClick: function () { if ( lastPrompt && ! working ) { draft( lastPrompt ); } } } ) ] )
		], { 'data-sp-fc-expired': '' } ) );
	}

	// ── decisions ───────────────────────────────────────────────

	function rejectPlan() {
		retirePlanCards();
		actionId = null;
		preview = null;
		expiresAt = null;
		window.clearInterval( expiryTimer );
		addAi( t( 'rejected' ) );
		toast( t( 'rejected' ) );
		setSuggestions( [] );
		setCanvas( 'start' );
		setCtx( t( 'ctxNew', 'New native form' ) );
	}

	function openConfirm() {
		var modal = byId( 'sp-confirm' );
		if ( ! modal ) { approvePlan(); return; }
		byId( 'sp-confirm-ack' ).checked = false;
		byId( 'sp-confirm-go' ).disabled = true;
		modal.hidden = false;
	}
	function closeConfirm() { var modal = byId( 'sp-confirm' ); if ( modal ) { modal.hidden = true; } }

	function approvePlan() {
		if ( ! actionId || isExpired() ) { return; }
		closeConfirm();
		busy( true, t( 'creating' ) );
		var id = actionId;
		request( 'forms/execute', { action_id: id } ).then( function ( response ) {
			busy( false );
			created( response.ok && response.data.ok ? ( response.data.result || {} ) : { message: response.data.message || t( 'genericError' ) } );
		} ).catch( function () {
			busy( false );
			created( { message: t( 'networkError' ) } );
		} );
	}

	function created( result ) {
		retirePlanCards();
		window.clearInterval( expiryTimer );
		var ok = renderResultCanvas( result );
		var draftOnly = ! result || false !== result.is_draft;
		actionId = null;
		expiresAt = null;
		setCanvas( 'result' );
		setSuggestions( [] );
		setCtx( ok ? t( 'ctxCreated', 'Form created' ) : t( 'creationFailed', 'Creation failed' ) );
		addAi( card( Dock.cardHead( ok ? 'ok' : 'warn', ok ? 'check' : 'alert', ok ? ( draftOnly ? t( 'okTitle' ) : t( 'okTitlePub' ) ) : t( 'failTitle' ) ), [
			el( 'div', { class: 'aisa-ai-card__body' }, [ el( 'p', { dir: 'auto', text: ok ? ( draftOnly ? t( 'resultDraft' ) : t( 'resultPublished' ) ) : ( result.message || t( 'resultFailed' ) ) } ) ] ),
			ok && result.edit_url ? el( 'div', { class: 'aisa-ai-card__foot' }, [ linkButton( draftOnly ? t( 'openDraft' ) : t( 'openForm' ), result.edit_url, { size: 'sm' } ) ] ) : null
		], { 'data-sp-fc-created': ok ? 'ok' : 'failed' } ) );
	}

	function newForm() {
		retirePlanCards();
		actionId = null;
		preview = null;
		expiresAt = null;
		lastPrompt = '';
		window.clearInterval( expiryTimer );
		thread().textContent = '';
		setSuggestions( [] );
		setStatus( '' );
		setCanvas( 'start' );
		showTab( 'chat' );
		greet();
		var input = byId( 'aisaautopilot-prompt' );
		if ( input ) { input.value = ''; Dock.autoGrow( input, 180 ); if ( ! input.disabled ) { input.focus(); } }
	}

	// ── voice ───────────────────────────────────────────────────

	function voiceButton( active ) {
		var voice = byId( 'aisaautopilot-voice-toggle' );
		if ( ! voice ) { return; }
		voice.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
		voice.classList.toggle( 'is-recording', active );
		var label = voice.querySelector( '.aisaautopilot-voice-button__label' );
		if ( label ) { label.textContent = active ? t( 'stop' ) : t( 'speak' ); }
	}

	function stopRecording() { if ( recorder && 'recording' === recorder.state ) { recorder.stop(); } }

	function transcribeRecording( blob, duration ) {
		var form = new FormData();
		form.append( 'audio', blob, 'aisaautopilot-form-command.webm' );
		form.append( 'duration_ms', String( duration ) );
		form.append( 'language', document.documentElement.lang || '' );
		setStatus( t( 'transcribing' ) );
		fetch( cfg.restUrl + 'forms/voice/transcribe', {
			method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce }, body: form
		} ).then( responseData ).then( function ( response ) {
			if ( ! response.ok || ! response.data.ok || ! response.data.transcript ) {
				setStatus( response.data.message || t( 'voiceFailed' ) );
				return;
			}
			var prompt = byId( 'aisaautopilot-prompt' );
			prompt.value = ( prompt.value ? prompt.value.trim() + ' ' : '' ) + response.data.transcript;
			Dock.autoGrow( prompt, 180 );
			prompt.focus();
			setStatus( t( 'voiceAdded' ) );
		} ).catch( function () { setStatus( t( 'voiceFailed' ) ); } );
	}

	function toggleVoice() {
		if ( recorder && 'recording' === recorder.state ) { stopRecording(); return; }
		if ( ! navigator.mediaDevices || ! window.MediaRecorder ) { setStatus( t( 'noMic' ) ); return; }
		navigator.mediaDevices.getUserMedia( { audio: true } ).then( function ( stream ) {
			recorderStream = stream;
			chunks = [];
			try { recorder = new MediaRecorder( stream, { mimeType: 'audio/webm' } ); } catch ( error ) { recorder = new MediaRecorder( stream ); }
			var started = Date.now();
			recorder.addEventListener( 'dataavailable', function ( event ) { if ( event.data && event.data.size ) { chunks.push( event.data ); } } );
			recorder.addEventListener( 'stop', function () {
				window.clearTimeout( recordingTimer );
				voiceButton( false );
				if ( recorderStream ) { recorderStream.getTracks().forEach( function ( track ) { track.stop(); } ); }
				var blob = new Blob( chunks, { type: recorder.mimeType || 'audio/webm' } );
				if ( blob.size ) { transcribeRecording( blob, Math.max( 1, Date.now() - started ) ); }
			} );
			recorder.start();
			voiceButton( true );
			setStatus( t( 'recording' ) );
			recordingTimer = window.setTimeout( stopRecording, 45000 );
		} ).catch( function () { setStatus( t( 'micDenied' ) ); } );
	}

	// ── boot ────────────────────────────────────────────────────

	function init() {
		if ( ! root() || ! Dock ) { return; }
		Dock.hydrate( root() );
		var input = byId( 'aisaautopilot-prompt' );
		if ( input ) {
			Dock.autoGrow( input, 180 );
			input.addEventListener( 'input', function () { Dock.autoGrow( input, 180 ); } );
			// Enter sends and Shift+Enter starts a new line; Ctrl+Enter keeps working as before.
			input.addEventListener( 'keydown', function ( event ) {
				if ( 'Enter' === event.key && ! event.shiftKey && ! event.isComposing ) { event.preventDefault(); submit(); }
			} );
		}
		var compose = byId( 'aisaautopilot-compose' );
		if ( compose ) { compose.addEventListener( 'submit', function ( event ) { event.preventDefault(); submit(); } ); }
		var voice = byId( 'aisaautopilot-voice-toggle' );
		if ( voice ) { voice.addEventListener( 'click', toggleVoice ); }
		var newBtn = document.querySelector( '[data-sp-form-new]' );
		if ( newBtn ) { newBtn.addEventListener( 'click', newForm ); }

		// The gallery only fills the composer; nothing is sent until the administrator submits.
		Array.prototype.forEach.call( document.querySelectorAll( '#aisaautopilot-examples [data-example]' ), function ( node ) {
			node.addEventListener( 'click', function () { prefill( node.getAttribute( 'data-example' ) || '' ); } );
		} );
		Array.prototype.forEach.call( document.querySelectorAll( 'button[data-sp-fc-tab]' ), function ( node ) {
			node.addEventListener( 'click', function () { showTab( node.getAttribute( 'data-sp-fc-tab' ) ); } );
		} );
		showTab( 'chat' );

		var ack = byId( 'sp-confirm-ack' );
		if ( ack ) { ack.addEventListener( 'change', function () { byId( 'sp-confirm-go' ).disabled = ! ack.checked; } ); }
		var go = byId( 'sp-confirm-go' );
		if ( go ) { go.addEventListener( 'click', approvePlan ); }
		Array.prototype.forEach.call( document.querySelectorAll( '[data-sp-confirm-cancel]' ), function ( node ) { node.addEventListener( 'click', closeConfirm ); } );

		window.addEventListener( 'focus', recheck );
		document.addEventListener( 'visibilitychange', function () { if ( 'visible' === document.visibilityState ) { recheck(); } } );
		window.addEventListener( 'beforeunload', function () { stopRecording(); } );

		fitWorkspace();
		window.addEventListener( 'resize', fitWorkspace );
		setCanvas( 'start' );
		greet();
	}

	if ( 'loading' === document.readyState ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
}() );
