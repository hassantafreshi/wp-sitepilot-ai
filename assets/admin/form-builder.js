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
	var lastExtra = null;
	var working = false;
	var checking = false;
	// The field the administrator clicked: the chat then talks about it ("make this required").
	var selectedKey = '';
	// Earlier versions of the plan, newest last, so a change can be taken back while it is still valid.
	var undoStack = [];

	// The open plan survives a reload in this tab until it is approved, rejected or expires.
	var SAVED_PLAN = 'aisaautopilot.formStudio.plan';

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

	var TYPES = { text: [ 'typeText', 'Text', 'text' ], email: [ 'typeEmail', 'Email', 'mail' ], tel: [ 'typeTel', 'Phone', 'phone' ], url: [ 'typeUrl', 'Web address', 'link' ], number: [ 'typeNumber', 'Number', 'hash' ], date: [ 'typeDate', 'Date', 'calendar' ], textarea: [ 'typeTextarea', 'Long text', 'para' ], select: [ 'typeSelect', 'Dropdown', 'select' ], checkbox: [ 'typeCheckbox', 'Checkboxes', 'checkbox' ], radio: [ 'typeRadio', 'Choice', 'radio' ] };
	function typeLabel( type ) { var entry = TYPES[ type ]; return entry ? t( entry[ 0 ], entry[ 1 ] ) : String( type || '' ); }
	function typeIcon( type ) { var entry = TYPES[ type ]; return entry ? entry[ 2 ] : 'text'; }

	// ── the chat ────────────────────────────────────────────────

	function thread() { return byId( 'aisaautopilot-thread' ); }
	function scrollDown() { var node = thread(); if ( node ) { node.scrollTop = node.scrollHeight; } }
	function addUser( text, about ) {
		// A message about a picked field says so, so the thread reads back as it was meant.
		var node = el( 'div', { class: 'aisa-ai-user', dir: 'auto' }, [ about ? el( 'small', { class: 'aisa-ai-user__about', 'data-sp-about': '', text: t( 'aboutField', 'About: %s', about ) } ) : null, el( 'span', { text: text } ) ] );
		thread().appendChild( node );
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

	// Column widths in twelfths of a row, the same table the server and the visitor's form use.
	var WIDTHS = { full: 12, three_quarters: 9, two_thirds: 8, half: 6, third: 4, quarter: 3 };
	var WIDTH_CHOICES = [ [ 'full', '1', 'widthFull', 'Full width' ], [ 'three_quarters', '¾', 'widthThreeQuarters', 'Three quarters' ], [ 'two_thirds', '⅔', 'widthTwoThirds', 'Two thirds' ], [ 'half', '½', 'widthHalf', 'Half' ], [ 'third', '⅓', 'widthThird', 'One third' ], [ 'quarter', '¼', 'widthQuarter', 'One quarter' ] ];
	function widthOf( field ) { return WIDTHS[ field && field.width ] ? field.width : 'full'; }
	function widthBadge( field ) { var w = widthOf( field ); var found = WIDTH_CHOICES.filter( function ( c ) { return c[ 0 ] === w; } )[ 0 ]; return found ? found[ 1 ] : '1'; }
	function fieldByKey( key ) { var list = ( preview && preview.fields ) || []; for ( var i = 0; i < list.length; i++ ) { if ( list[ i ].key === key ) { return list[ i ]; } } return null; }

	function renderPlanCanvas( plan ) {
		var content = byId( 'aisaautopilot-review-content' );
		content.textContent = '';
		var rows = el( 'ul', { class: 'sp-fc-fieldlist' } );
		( plan.fields || [] ).forEach( function ( field ) {
			var meta = typeLabel( field.type ) + ( ( field.options || [] ).length ? ' · ' + field.options.join( ', ' ) : '' );
			var pick = el( 'button', { type: 'button', class: 'sp-fc-fieldlist__pick', 'data-sp-field-key': field.key || '', 'aria-pressed': 'false' }, [
				el( 'span', { class: 'aisa-ai-ic' }, [ icon( typeIcon( field.type ), 14 ) ] ),
				el( 'span', { class: 'sp-fc-fieldlist__main', dir: 'auto' }, [ field.label || '', field.required ? el( 'span', { class: 'sp-fc-req', text: ' *' } ) : null, el( 'small', { text: meta } ) ] ),
				'full' === widthOf( field ) ? null : el( 'span', { class: 'sp-fc-fieldlist__width', 'data-sp-field-width': widthOf( field ), title: t( 'widthTitle', 'Width: %s', widthName( widthOf( field ) ) ), text: widthBadge( field ) } )
			] );
			pick.addEventListener( 'click', function () { selectField( field.key === selectedKey ? '' : field.key ); } );
			rows.appendChild( el( 'li', {}, [ pick ] ) );
		} );
		var formNode = previewForm( plan );
		var desktop = el( 'button', { type: 'button', class: 'sp-seg__btn is-on', 'aria-pressed': 'true' }, [ icon( 'monitor', 14 ), el( 'span', { text: t( 'desktop', 'Desktop' ) } ) ] );
		var mobile = el( 'button', { type: 'button', class: 'sp-seg__btn', 'aria-pressed': 'false' }, [ icon( 'mobile', 14 ), el( 'span', { text: t( 'mobile', 'Mobile' ) } ) ] );
		desktop.addEventListener( 'click', function () { desktop.classList.add( 'is-on' ); mobile.classList.remove( 'is-on' ); desktop.setAttribute( 'aria-pressed', 'true' ); mobile.setAttribute( 'aria-pressed', 'false' ); formNode.classList.remove( 'is-mobile' ); } );
		mobile.addEventListener( 'click', function () { mobile.classList.add( 'is-on' ); desktop.classList.remove( 'is-on' ); mobile.setAttribute( 'aria-pressed', 'true' ); desktop.setAttribute( 'aria-pressed', 'false' ); formNode.classList.add( 'is-mobile' ); } );
		var undo = el( 'button', { type: 'button', class: 'sp-seg__btn sp-fc-undo', 'data-sp-fc-undo': '', disabled: undoStack.length ? null : true, title: t( 'undoTitle', 'Take back the last change' ) }, [ icon( 'undo', 14 ), el( 'span', { text: t( 'undo', 'Undo' ) } ) ] );
		undo.addEventListener( 'click', undoChange );
		content.appendChild( el( 'div', { class: 'sp-fc-plan__grid', 'data-sp-form-review-canvas': '' }, [
			el( 'section', { class: 'sp-fc-plan__fields', 'aria-label': t( 'fields', 'Fields' ) }, [
				el( 'h2', {}, [ t( 'fields', 'Fields' ), el( 'span', { class: 'aisa-ai-tag', text: String( ( plan.fields || [] ).length ) } ) ] ),
				el( 'p', { class: 'sp-fc-plan__hint' }, [ t( 'pickHint', 'Click a field, here or in the preview, to change it or to ask the assistant about it.' ) ] ),
				rows,
				el( 'div', { class: 'sp-fc-inspector', 'data-sp-field-inspector': '', hidden: true } ),
				el( 'p', { class: 'sp-fc-plan__never' }, [ icon( 'shield', 14 ), el( 'span', { text: t( 'neverIncluded', 'Native forms never include payments, passwords or sign-ups.' ) } ) ] )
			] ),
			el( 'section', { class: 'sp-fc-plan__preview', 'aria-label': t( 'preview', 'Preview' ) }, [
				el( 'div', { class: 'sp-fc-plan__bar' }, [ el( 'span', { text: t( 'preview', 'Preview' ) } ), el( 'div', { class: 'sp-fc-plan__tools' }, [ undo, el( 'div', { class: 'sp-seg' }, [ desktop, mobile ] ) ] ) ] ),
				el( 'div', { class: 'sp-preview' }, [ formNode ] )
			] )
		] ) );
		renderSelection();
	}

	function widthName( width ) { var found = WIDTH_CHOICES.filter( function ( c ) { return c[ 0 ] === width; } )[ 0 ]; return found ? t( found[ 2 ], found[ 3 ] ) : width; }

	function previewForm( plan ) {
		// Visitors read the form in the site language and direction, which may differ from the admin's.
		var form = el( 'form', { class: 'sp-preview__form', dir: boot.site && boot.site.rtl ? 'rtl' : 'ltr', lang: ( boot.site && boot.site.locale || '' ).replace( '_', '-' ) } );
		form.addEventListener( 'submit', function ( e ) { e.preventDefault(); } );
		form.appendChild( el( 'div', {}, [
			el( 'h3', { class: 'sp-preview__title', dir: 'auto', text: plan.title || '' } ),
			plan.description ? el( 'p', { class: 'sp-preview__lede', dir: 'auto', text: plan.description } ) : null
		] ) );
		var grid = el( 'div', { class: 'sp-preview__fields' } );
		( plan.fields || [] ).forEach( function ( field, index ) { grid.appendChild( previewField( field, index ) ); } );
		form.appendChild( grid );
		form.appendChild( el( 'button', { type: 'button', class: 'sp-preview__submit', text: plan.submit_button || 'Send' } ) );
		return form;
	}

	function previewField( field, index ) {
		var id = 'sp-pf-' + index;
		var control;
		var options = field.options || [];
		if ( 'textarea' === field.type ) {
			control = el( 'textarea', { id: id, disabled: true, placeholder: field.placeholder || null, tabindex: '-1' } );
		} else if ( 'select' === field.type ) {
			control = el( 'select', { id: id, disabled: true, tabindex: '-1' } );
			options.forEach( function ( option ) { control.appendChild( el( 'option', { text: option } ) ); } );
		} else if ( 'radio' === field.type || 'checkbox' === field.type ) {
			control = el( 'div', { class: 'sp-stack sp-stack--sm' } );
			if ( 'checkbox' === field.type && ! options.length ) { options = [ field.label || '' ]; }
			options.forEach( function ( option, i ) {
				control.appendChild( el( 'span', { class: 'sp-check' }, [ el( 'input', { type: field.type, name: id, id: id + '-' + i, disabled: true, tabindex: '-1' } ), el( 'span', { text: option } ) ] ) );
			} );
		} else {
			var type = [ 'email', 'tel', 'text', 'url', 'number', 'date' ].indexOf( field.type ) !== -1 ? field.type : 'text';
			control = el( 'input', { id: id, type: type, dir: 'text' === type ? null : 'ltr', disabled: true, placeholder: field.placeholder || null, tabindex: '-1' } );
		}
		var node = el( 'div', { class: 'sp-preview__field', 'data-sp-field-key': field.key || '', 'data-sp-field-width': widthOf( field ), role: 'button', tabindex: '0', 'aria-pressed': 'false', style: 'grid-column: span ' + WIDTHS[ widthOf( field ) ] }, [
			el( 'span', { class: 'sp-preview__label', dir: 'auto' }, [ el( 'span', { text: field.label || '' } ), field.required ? el( 'span', { class: 'sp-preview__req', text: ' *' } ) : null ] ),
			control,
			field.help ? el( 'small', { class: 'sp-preview__help', dir: 'auto', text: field.help } ) : null
		] );
		node.setAttribute( 'aria-label', t( 'selectField', 'Select the field “%s”', field.label || '' ) );
		node.addEventListener( 'click', function () { selectField( field.key === selectedKey ? '' : field.key ); } );
		node.addEventListener( 'keydown', function ( event ) { if ( 'Enter' === event.key || ' ' === event.key ) { event.preventDefault(); selectField( field.key === selectedKey ? '' : field.key ); } } );
		return node;
	}

	// ── a field picked on the canvas ────────────────────────────

	/* One place keeps the field list, the preview, the inspector, the chat chip and the composer in step. */
	function selectField( key ) {
		selectedKey = key && fieldByKey( key ) ? key : '';
		renderSelection();
		var field = selectedKey ? fieldByKey( selectedKey ) : null;
		if ( field ) {
			setSuggestions( selectedSuggestions( field ) );
			announce( t( 'fieldSelected', 'Selected “%s”. Your next message is about this field.', field.label || '' ) );
		} else if ( preview ) {
			setSuggestions( nextSuggestions( preview ) );
		}
	}

	function announce( message ) { var node = byId( 'aisaautopilot-status' ); if ( node ) { node.textContent = message || ''; } }

	function renderSelection() {
		var field = selectedKey ? fieldByKey( selectedKey ) : null;
		if ( selectedKey && ! field ) { selectedKey = ''; }
		Array.prototype.forEach.call( document.querySelectorAll( '[data-sp-field-key]' ), function ( node ) {
			var on = !! selectedKey && node.getAttribute( 'data-sp-field-key' ) === selectedKey;
			node.classList.toggle( 'is-selected', on );
			node.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
		} );
		renderInspector( field );
		renderChip( field );
		var input = byId( 'aisaautopilot-prompt' );
		if ( input && isReady() ) {
			input.placeholder = field ? t( 'placeholderSelected', 'Tell me what to change about “%s”…', field.label || '' ) : ( preview ? t( 'placeholderRevise', 'Ask for a change to the form…' ) : t( 'placeholderReady', 'Describe the form: who fills it in and what you need to know…' ) );
		}
		if ( actionId && preview && ! isExpired() ) { setCtx( field ? t( 'ctxSelected', 'Editing “%s”', field.label || '' ) : t( 'ctxPlan', 'Plan ready · valid for %s', clockLabel() ) ); }
	}

	function renderChip( field ) {
		var host = document.querySelector( '.aisa-ai-input' );
		if ( ! host ) { return; }
		var chipNode = host.querySelector( '[data-sp-fc-selected]' );
		if ( ! field ) { if ( chipNode ) { chipNode.remove(); } return; }
		var clear = el( 'button', { type: 'button', class: 'sp-fc-selected__x', 'aria-label': t( 'clearSelection', 'Stop editing this field' ) }, [ icon( 'x', 12, 2.4 ) ] );
		clear.addEventListener( 'click', function () { selectField( '' ); var input = byId( 'aisaautopilot-prompt' ); if ( input ) { input.focus(); } } );
		var fresh = el( 'div', { class: 'sp-fc-selected', 'data-sp-fc-selected': field.key, role: 'group', 'aria-label': t( 'editingField', 'Editing the field' ) }, [
			el( 'span', { class: 'aisa-ai-ic' }, [ icon( typeIcon( field.type ), 13 ) ] ),
			el( 'span', { class: 'sp-fc-selected__text', dir: 'auto' }, [ el( 'small', { text: t( 'editing', 'Editing' ) } ), el( 'strong', { text: field.label || '' } ), el( 'small', { text: typeLabel( field.type ) } ) ] ),
			clear
		] );
		if ( chipNode ) { chipNode.replaceWith( fresh ); } else { host.insertBefore( fresh, host.firstChild ); }
	}

	function selectedSuggestions( field ) {
		var list = [];
		var label = field.label || '';
		list.push( field.required ? t( 'selOptional', 'Make “%s” optional', label ) : t( 'selRequired', 'Make “%s” required', label ) );
		if ( 'full' === widthOf( field ) ) { list.push( t( 'selHalf', 'Put “%s” next to the field after it', label ) ); }
		if ( 'text' === field.type ) { list.push( t( 'selDropdown', 'Turn “%s” into a dropdown', label ) ); } else if ( 'select' === field.type || 'radio' === field.type ) { list.push( t( 'selOption', 'Add an option to “%s”', label ) ); } else { list.push( t( 'selRename', 'Rename “%s”', label ) ); }
		return list;
	}

	function renderInspector( field ) {
		var box = document.querySelector( '[data-sp-field-inspector]' );
		if ( ! box ) { return; }
		box.textContent = '';
		box.hidden = ! field;
		if ( ! field ) { return; }
		var key = field.key;
		var fields = ( preview && preview.fields ) || [];
		var at = fields.indexOf( field );
		var widths = el( 'div', { class: 'sp-seg sp-fc-inspector__widths', role: 'group', 'aria-label': t( 'width', 'Width' ) } );
		WIDTH_CHOICES.forEach( function ( choice ) {
			var on = widthOf( field ) === choice[ 0 ];
			var btn = el( 'button', { type: 'button', class: 'sp-seg__btn' + ( on ? ' is-on' : '' ), 'aria-pressed': on ? 'true' : 'false', 'data-sp-width': choice[ 0 ], 'aria-label': widthName( choice[ 0 ] ), title: widthName( choice[ 0 ] ), text: choice[ 1 ] } );
			btn.addEventListener( 'click', function () { if ( ! on ) { editPlan( [ { op: 'set_width', key: key, value: choice[ 0 ] } ] ); } } );
			widths.appendChild( btn );
		} );
		var required = el( 'input', { type: 'checkbox', 'data-sp-inspector-required': '', checked: field.required ? true : null } );
		required.addEventListener( 'change', function () { editPlan( [ { op: 'set_required', key: key, value: required.checked } ] ); } );
		function text( prop, label, value ) {
			var input = el( 'input', { type: 'text', class: 'sp-input', dir: 'auto', value: value || '', 'data-sp-inspector-text': prop, maxlength: 'label' === prop ? '120' : '200' } );
			var commit = function () { var next = input.value.trim(); if ( next !== ( value || '' ) && ( next || 'label' !== prop ) ) { editPlan( [ { op: 'set_text', key: key, prop: prop, value: next } ] ); } else { input.value = value || ''; } };
			input.addEventListener( 'change', commit );
			input.addEventListener( 'keydown', function ( event ) { if ( 'Enter' === event.key ) { event.preventDefault(); commit(); } } );
			return el( 'label', { class: 'sp-fc-inspector__field' }, [ el( 'span', { text: label } ), input ] );
		}
		var up = button( t( 'moveUp', 'Move up' ), { size: 'sm', icon: 'up', attrs: { 'data-sp-move': 'up', disabled: at <= 0 ? true : null }, onClick: function () { editPlan( [ { op: 'move', key: key, direction: 'up' } ] ); } } );
		var down = button( t( 'moveDown', 'Move down' ), { size: 'sm', attrs: { 'data-sp-move': 'down', disabled: at >= fields.length - 1 ? true : null }, onClick: function () { editPlan( [ { op: 'move', key: key, direction: 'down' } ] ); } } );
		var remove = button( t( 'removeField', 'Remove' ), { size: 'sm', icon: 'trash', attrs: { 'data-sp-remove': '', disabled: fields.length <= 1 ? true : null }, onClick: function () { selectedKey = ''; editPlan( [ { op: 'remove', key: key } ] ); } } );
		box.appendChild( el( 'div', { class: 'sp-fc-inspector__head' }, [ el( 'strong', { dir: 'auto', text: field.label || '' } ), el( 'small', { text: typeLabel( field.type ) } ) ] ) );
		box.appendChild( el( 'div', { class: 'sp-fc-inspector__row' }, [ el( 'span', { class: 'sp-fc-inspector__lbl', text: t( 'width', 'Width' ) } ), widths ] ) );
		box.appendChild( el( 'label', { class: 'sp-fc-inspector__row sp-fc-inspector__req' }, [ required, el( 'span', { text: t( 'requiredField', 'Required' ) } ) ] ) );
		box.appendChild( text( 'label', t( 'fieldLabel', 'Label' ), field.label ) );
		if ( [ 'text', 'email', 'tel', 'url', 'number', 'textarea' ].indexOf( field.type ) !== -1 ) { box.appendChild( text( 'placeholder', t( 'fieldPlaceholder', 'Placeholder' ), field.placeholder ) ); }
		box.appendChild( text( 'help', t( 'fieldHelp', 'Hint under the field' ), field.help ) );
		box.appendChild( el( 'div', { class: 'sp-fc-inspector__actions' }, [ up, down, remove ] ) );
	}

	/* A click-sized change: no AI, applied by the server so the plan stays valid, and it can be undone. */
	function editPlan( ops ) {
		if ( working || ! actionId || isExpired() ) { return; }
		working = true;
		var keep = selectedKey;
		request( 'forms/edit', { action_id: actionId, ops: ops } ).then( function ( response ) {
			working = false;
			if ( ! response.ok || ! response.data.ok ) { toast( ( response.data && response.data.message ) || t( 'genericError' ) ); return; }
			planned( response.data, { quiet: true, undoable: true } );
			if ( keep && fieldByKey( keep ) ) { selectField( keep ); }
		} ).catch( function () { working = false; toast( t( 'networkError' ) ); } );
	}

	function pushUndo() {
		if ( ! actionId || ! preview ) { return; }
		undoStack.push( { action_id: actionId, preview: preview, expires_at: expiresAt } );
		if ( undoStack.length > 20 ) { undoStack.shift(); }
	}

	function undoChange() {
		if ( working ) { return; }
		var previous = undoStack.pop();
		while ( previous && Number( previous.expires_at ) <= Date.now() + 2000 ) { previous = undoStack.pop(); }
		if ( ! previous ) { toast( t( 'nothingToUndo', 'Nothing to take back, or it has expired.' ) ); renderPlanCanvas( preview ); return; }
		var keep = selectedKey;
		planned( { action_id: previous.action_id, preview: previous.preview, expires_at: Number( previous.expires_at ) / 1000 }, { quiet: true } );
		if ( keep && fieldByKey( keep ) ) { selectField( keep ); }
		toast( t( 'undone', 'Change taken back' ) );
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

	function submit() {
		var input = byId( 'aisaautopilot-prompt' );
		var text = input ? ( input.value || '' ).trim() : '';
		if ( ! text ) { setStatus( t( 'promptFirst' ) ); return; }
		if ( working || ! isReady() ) { return; }
		var field = selectedKey ? fieldByKey( selectedKey ) : null;
		addUser( text, field ? field.label : '' );
		input.value = '';
		Dock.autoGrow( input, 180 );
		// With a plan on the canvas, the message asks for a change to it; the server holds the whole
		// plan, and the field the administrator picked is named so "this" and "it" mean that field.
		if ( actionId && preview && ! isExpired() ) {
			draft( text.slice( 0, 2000 ), { revise_of: actionId, selected: field ? field.key : '' } );
		} else {
			draft( text.slice( 0, 2000 ) );
		}
	}

	function draft( prompt, extra ) {
		working = true;
		lastPrompt = prompt;
		lastExtra = extra || null;
		setSuggestions( [] );
		setStatus( '' );
		var hadPlan = !! actionId;
		byId( 'aisaautopilot-generate' ).disabled = true;
		var steps = addAi( Dock.steps( [ [ 'done', t( 'stepUnderstood', 'Understood the request' ) ], [ 'run', t( 'stepChoosing', 'Choosing the fields…' ) ], [ 'wait', t( 'stepRules', 'Checking them against the native-form rules' ) ] ] ) );
		steps.parentNode.setAttribute( 'data-sp-fc-thinking', '' );
		setCanvas( 'drafting' );
		setCtx( t( 'ctxDrafting', 'Drafting a plan…' ) );
		request( 'forms/plan', Object.assign( { prompt: prompt }, extra || {} ) ).then( function ( response ) {
			finishDraft( steps );
			if ( ! response.ok || ! response.data.ok ) { failed( response.data || {}, hadPlan ); return; }
			planned( response.data, { undoable: !! ( extra && extra.revise_of ) } );
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
				button( t( 'tryAgain', 'Try again' ), { size: 'sm', icon: 'refresh', onClick: function () { if ( lastPrompt && ! working ) { draft( lastPrompt, lastExtra ); } } } )
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

	function planned( data, opts ) {
		opts = opts || {};
		var restored = !! opts.restored;
		var quiet = !! opts.quiet;
		// What is on the canvas now is what Undo brings back.
		if ( opts.undoable ) { pushUndo(); }
		if ( ! quiet ) { retirePlanCards(); }
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
		planCard.setAttribute( 'data-sp-fc-restored', restored ? '1' : '0' );
		var existing = quiet ? document.querySelector( '[data-sp-fc-plan-card]' ) : null;
		if ( existing ) {
			// A click-sized change updates the card that is already in the thread instead of adding another.
			existing.parentNode.replaceChild( planCard, existing );
		} else {
			addAi( [ restored ? t( 'planRestored' ) : t( 'planReady' ), preview.notes ? String( preview.notes ) : '', planCard ] );
		}
		if ( ! quiet && ! restored && Array.isArray( preview.needs_efb ) && preview.needs_efb.length ) { addAi( efbHandoffCard( preview.needs_efb ) ); }
		renderPlanCanvas( preview );
		setCanvas( 'plan' );
		stickyBar();
		setSuggestions( nextSuggestions( preview ) );
		startExpiryTimer();
		savePlan();
	}

	/* What a native form left out goes to Easy Form Builder: the request travels in this tab's session storage and waits
	   in its copilot's message box; nothing is sent there by itself. Without EFB, the way to install it is offered. */
	var FEATURES = { payment: [ 'featPayment', 'payment' ], file_upload: [ 'featFileUpload', 'file upload' ], conditional_logic: [ 'featLogic', 'conditional logic' ], multi_step: [ 'featMultiStep', 'multiple steps' ], signature: [ 'featSignature', 'signature' ], calculation: [ 'featCalculation', 'calculations' ], rating: [ 'featRating', 'rating' ] };
	function efbHandoffCard( needs ) {
		var names = needs.map( function ( key ) { var f = FEATURES[ key ]; return f ? t( f[ 0 ], f[ 1 ] ) : key; } ).join( ', ' );
		var foot = [];
		if ( boot.efbReady && boot.efbCreate ) {
			foot.push( button( t( 'efbContinue', 'Build it in Easy Form Builder' ), { kind: 'primary', size: 'sm', attrs: { 'data-sp-fc-efb-handoff': '' }, onClick: function () {
				try { window.sessionStorage.setItem( 'aisaautopilot.efb.resume', JSON.stringify( { text: String( lastPrompt || '' ), at: Date.now(), from: 'form_studio' } ) ); } catch ( e ) {}
				// The request moves to Easy Form Builder: the native plan is not brought back on the next visit.
				forgetPlan();
				window.location.href = boot.efbCreate;
			} } ) );
		} else if ( boot.efbAction && boot.efbAction.url ) {
			foot.push( linkButton( boot.efbAction.label, boot.efbAction.url, { kind: 'primary', size: 'sm' } ) );
		}
		return card( Dock.cardHead( 'warn', 'plug', t( 'efbNeededTitle', 'This part needs Easy Form Builder' ) ), [
			el( 'div', { class: 'aisa-ai-card__body' }, [ el( 'p', { dir: 'auto', text: t( 'efbNeededBody', 'Native forms leave out: %s.', names ) } ), boot.efbReady ? null : el( 'p', { text: t( 'efbMissingBody', 'Install and activate Easy Form Builder to build these.' ) } ) ] ),
			foot.length ? el( 'div', { class: 'aisa-ai-card__foot' }, foot ) : null
		], { 'data-sp-fc-efb': needs.join( ',' ) } );
	}

	function nextSuggestions( plan ) {
		var next = [];
		var fields = plan.fields || [];
		if ( fields.length >= 3 && ! fields.some( function ( field ) { return 'full' !== widthOf( field ); } ) ) { next.push( t( 'nextColumns', 'Put some fields side by side' ) ); }
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

	// ── a plan kept across a reload ─────────────────────────────

	/* Only this tab, only the administrator who drafted it, and only until the server's proposal expires. */
	function savePlan() {
		try {
			window.sessionStorage.setItem( SAVED_PLAN, JSON.stringify( { v: 1, user: boot.user || 0, action_id: actionId, preview: preview, expires_at: expiresAt, prompt: lastPrompt } ) );
		} catch ( e ) {}
	}
	function forgetPlan() {
		try { window.sessionStorage.removeItem( SAVED_PLAN ); } catch ( e ) {}
	}
	function savedPlan() {
		var saved = null;
		try { saved = JSON.parse( window.sessionStorage.getItem( SAVED_PLAN ) || 'null' ); } catch ( e ) { saved = null; }
		var usable = saved && 1 === saved.v && saved.action_id && saved.preview && Array.isArray( saved.preview.fields )
			&& ( saved.user || 0 ) === ( boot.user || 0 ) && Number( saved.expires_at ) > Date.now() + 5000;
		if ( ! usable ) { forgetPlan(); return null; }
		return saved;
	}
	function restorePlan() {
		var saved = savedPlan();
		if ( ! saved || ! isReady() ) { return false; }
		lastPrompt = String( saved.prompt || '' );
		planned( { action_id: saved.action_id, preview: saved.preview, expires_at: Number( saved.expires_at ) / 1000 }, { restored: true } );
		return true;
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
			var picked = selectedKey ? fieldByKey( selectedKey ) : null;
			setCtx( picked ? t( 'ctxSelected', 'Editing “%s”', picked.label || '' ) : t( 'ctxPlan', 'Plan ready · valid for %s', clockLabel() ) );
		};
		tick();
		expiryTimer = window.setInterval( tick, 1000 );
	}
	function expirePlan() {
		forgetPlan();
		setCtx( t( 'ctxExpired', 'Plan expired' ) );
		var approve = byId( 'aisaautopilot-approve' );
		if ( approve ) { approve.disabled = true; approve.querySelector( 'span' ).textContent = t( 'cannotApprove', 'Expired' ); }
		var bar = document.querySelector( '[data-sp-fc-stickybar]' );
		if ( bar ) { bar.hidden = true; }
		addAi( card( Dock.cardHead( 'warn', 'alert', t( 'ctxExpired', 'Plan expired' ) ), [
			el( 'div', { class: 'aisa-ai-card__body' }, [ el( 'p', { text: t( 'planExpired' ) } ) ] ),
			el( 'div', { class: 'aisa-ai-card__foot' }, [ button( t( 'askAgain', 'Draft it again' ), { size: 'sm', icon: 'refresh', onClick: function () { if ( lastPrompt && ! working ) { draft( lastPrompt, lastExtra ); } } } ) ] )
		], { 'data-sp-fc-expired': '' } ) );
	}

	// ── decisions ───────────────────────────────────────────────

	function resetEditing() { selectedKey = ''; undoStack = []; renderChip( null ); }

	function rejectPlan() {
		resetEditing();
		forgetPlan();
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
		resetEditing();
		forgetPlan();
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
		resetEditing();
		forgetPlan();
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
		restorePlan();
	}

	if ( 'loading' === document.readyState ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
}() );
