/* global AISAAutoPilotEFB, AISAAutoPilotDock, valj_efb, editFormEfb, efb_var */
( function () {
	'use strict';

	var cfg = window.AISAAutoPilotEFB || {};
	var WIDTH = { full: '100', half: '50', third: '33', quarter: '25' };
	var FIELD_PROPERTIES = cfg.fieldProperties || {};
	var FORM_PROPERTIES = cfg.formProperties || {};
	var history = [];
	var busy = false;
	var greeted = false;
	var recorder = null;
	var recorderStream = null;
	var recorderChunks = [];
	var recorderStartedAt = 0;
	var recorderTimer = null;
	var recognition = null;
	var voiceInputPrefix = '';
	var voiceFinalText = '';
	var selectedField = null;
	// The id whose selection message is already the newest thing in the transcript, so a
	// Clear and reselect of the same field does not repeat an identical line. The banner
	// stays the standing indicator of the context.
	var lastIntroFieldId = '';
	var configured = !! cfg.configured;
	var unsaved = 0;
	var leaving = false; // The copilot's own navigation (a new form, a reload after a failed redraw) needs no warning.
	var flashTimer = null;
	var nudging = false;
	var checking = false;
	var composerMode = '';
	function t( key, fallback, replacement ) {
		var value = cfg.i18n && cfg.i18n[ key ] ? cfg.i18n[ key ] : fallback;
		if ( replacement === undefined ) { return value; }
		// Accepts one value or a list for numbered placeholders (%1$s, %2$s). A replacer
		// function keeps `$&`-style sequences in field labels literal.
		var args = Array.isArray( replacement ) ? replacement : [ replacement ];
		var next = 0;
		return value.replace( /%(?:(\d+)\$)?s/g, function ( match, position ) {
			var i = position ? Number( position ) - 1 : next++;
			return i < args.length ? String( args[ i ] ) : match;
		} );
	}
	function responseLanguage( text ) { if ( /[\u0600-\u06ff]/.test( text ) ) { return /[\u067e\u0686\u0698\u06af]/.test( text ) ? 'fa' : 'ar'; } if ( /[\u0400-\u04ff]/.test( text ) ) { return 'ru'; } return cfg.language || 'en'; }
	function speakReply( text ) { if ( ! cfg.voiceResponses || ! text || ! window.speechSynthesis || ! window.SpeechSynthesisUtterance ) { return; } window.speechSynthesis.cancel(); var utterance = new SpeechSynthesisUtterance( text ); utterance.lang = responseLanguage( text ); utterance.rate = 1; window.speechSynthesis.speak( utterance ); }

	var el = window.AISAAutoPilotDock.el;
	function $( id ) { return document.getElementById( id ); }
	function api( path, body ) {
		// WordPress honours _locale=user only for JSON requests, so server-built notes match the admin language.
		var url = cfg.restUrl + path;
		return fetch( url + ( url.indexOf( '?' ) < 0 ? '?' : '&' ) + '_locale=user', {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( body )
		} ).then( function ( r ) {
			return r.text().then( function ( text ) {
				var data = {};
				try { data = text ? JSON.parse( text ) : {}; } catch ( e ) { data = { ok: false, message: t( 'invalidResponse', 'Invalid server response.' ) }; }
				return { status: r.status, data: data };
			} );
		} );
	}
	function uploadAudio( blob, duration ) {
		var data = new FormData();
		data.append( 'audio', blob, 'aisaautopilot-voice.webm' );
		data.append( 'duration_ms', String( duration ) );
		data.append( 'language', cfg.language || 'en' );
		return fetch( cfg.restUrl + 'efb/voice/transcribe', { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce }, body: data } ).then( function ( r ) {
			return r.json().catch( function () { return { ok: false, message: t( 'invalidResponse', 'Invalid server response.' ) }; } );
		} );
	}
	function makeTraceId() {
		if ( window.crypto && window.crypto.getRandomValues ) { var values = new Uint32Array( 2 ); window.crypto.getRandomValues( values ); return 'efb-' + values[ 0 ].toString( 36 ) + values[ 1 ].toString( 36 ); }
		return 'efb-' + Date.now().toString( 36 ) + Math.random().toString( 36 ).slice( 2, 8 );
	}
	function clientLog( traceId, event, message, context ) {
		if ( ! cfg.restUrl || ! cfg.nonce ) { return; }
		api( 'efb/log', { trace_id: traceId, event: event, message: message, context: context || {} } ).catch( function () {} );
	}

	/* ---------- the thread ---------- */
	var Dock = window.AISAAutoPilotDock;
	var icon = Dock.icon;
	function messages() { return $( 'aisaautopilot-efb-messages' ); }
	function scrollDown() { var m = messages(); if ( m ) { m.scrollTop = m.scrollHeight; } }
	// Free text takes its direction from its own first strong character (T17): an English reply in a
	// Persian editor, or a Persian question in an English one, reads in its own direction.
	function addUser( text ) {
		messages().appendChild( el( 'div', { class: 'aisa-ai-user aisaautopilot-efb-msg aisaautopilot-efb-msg--user' }, [ el( 'div', { class: 'aisaautopilot-efb-bubble', dir: 'auto', text: text } ) ] ) );
		scrollDown();
	}
	/** One assistant turn; returns its body so cards can be added to it. */
	function addAi( content ) {
		var body = el( 'div', { class: 'aisa-ai-body aisaautopilot-efb-bubble' } );
		if ( typeof content === 'string' ) { if ( content ) { body.appendChild( say( content ) ); } } else if ( content ) { body.appendChild( content ); }
		var row = el( 'div', { class: 'aisa-ai-row aisaautopilot-efb-msg aisaautopilot-efb-msg--ai' }, [ el( 'span', { class: 'aisa-ai-av', 'aria-hidden': 'true' }, [ icon( 'spark', 15, 1.9 ) ] ), body ] );
		messages().appendChild( row );
		scrollDown();
		return body;
	}
	function say( text, attrs ) { return el( 'div', Object.assign( { class: 'aisa-ai-text', dir: 'auto', text: text }, attrs || {} ) ); }
	function card( headNode, children, attrs ) {
		return el( 'div', Object.assign( { class: 'aisa-ai-card aisaautopilot-efb-card' }, attrs || {} ), [ headNode ].concat( children || [] ) );
	}
	function hint( iconName, text, kind ) { return el( 'div', { class: 'aisa-ai-hint', 'data-sp-efb-note': kind || null }, [ icon( iconName, 14 ), el( 'span', { text: text } ) ] ); }
	function button( label, opts ) {
		opts = opts || {};
		var b = el( 'button', Object.assign( { type: 'button', class: 'aisa-ai-btn aisaautopilot-efb-btn' + ( opts.kind ? ' aisa-ai-btn--' + opts.kind : '' ) + ( opts.size ? ' aisa-ai-btn--' + opts.size : '' ) + ( opts.grow ? ' aisa-ai-btn--grow' : '' ) + ( opts.extra ? ' ' + opts.extra : '' ) }, opts.attrs || {} ), [ opts.icon ? icon( opts.icon, 14, opts.stroke || 2 ) : null, el( 'span', { text: label } ) ] );
		if ( opts.onClick ) { b.addEventListener( 'click', opts.onClick ); }
		return b;
	}
	function addThinking() {
		var fields = liveFields().length;
		var plan = cfg.license && cfg.license.tier === 'pro' ? t( 'planPro', 'Pro' ) : ( cfg.license && cfg.license.tier === 'free_plus' ? t( 'planFreePlus', 'Free Plus' ) : t( 'planFree', 'Free' ) );
		var body = addAi( Dock.steps( [
			[ 'done', hasLiveForm() ? t( 'stepReadForm', 'Read your form (%s fields)', fields ) : t( 'stepReadRequest', 'Read your request' ) ],
			[ 'run', t( 'stepWorking', 'Working out the answer…' ) ],
			[ 'wait', t( 'stepCheckPlan', 'Check it against your Easy Form Builder plan (%s)', plan ) ]
		] ) );
		body.parentNode.id = 'aisaautopilot-efb-typing';
	}
	function removeTyping() { var node = $( 'aisaautopilot-efb-typing' ); if ( node ) { node.remove(); } }
	// Starting points and suggestions never send. They fill and focus the composer so the
	// admin reads and edits the command before anything leaves the browser (same contract as
	// Form Studio and the chat console).
	function prefill( text ) {
		var input = $( 'aisaautopilot-efb-input' );
		if ( ! input || input.disabled ) { return; }
		input.value = String( text === undefined || text === null ? '' : text );
		autoGrow();
		input.focus();
		try { input.setSelectionRange( input.value.length, input.value.length ); } catch ( e ) {}
	}
	function chips( items, kind ) {
		var box = el( 'div', { class: 'aisa-ai-chips aisaautopilot-efb-chips', 'data-sp-efb-chips': kind } );
		items.forEach( function ( it ) {
			var label = typeof it === 'string' ? it : it.title;
			var c = el( 'button', { type: 'button', class: 'aisa-ai-chip aisaautopilot-efb-chip', 'data-sp-efb-chip': kind, dir: 'auto' }, [ icon( 'spark', 13 ), el( 'span', { text: label } ) ] );
			c.addEventListener( 'click', function () { prefill( label ); } );
			box.appendChild( c );
		} );
		return box;
	}
	/** Next steps above the composer, replaced after every reply. */
	function setSuggestions( list ) {
		var box = $( 'aisaautopilot-efb-suggest' );
		if ( ! box ) { return; }
		box.textContent = '';
		( list || [] ).slice( 0, 3 ).forEach( function ( text ) {
			var c = el( 'button', { type: 'button', class: 'aisa-ai-chip', 'data-sp-efb-chip': 'next', dir: 'auto' }, [ icon( 'spark', 13 ), el( 'span', { text: text } ) ] );
			c.addEventListener( 'click', function () { prefill( text ); } );
			box.appendChild( c );
		} );
	}
	function starter( iconName, kind, title, hintText, text ) {
		var b = el( 'button', { type: 'button', class: 'aisa-ai-starter aisaautopilot-efb-chip', 'data-sp-efb-chip': 'starter', 'data-sp-efb-prefill': text }, [
			el( 'span', { class: 'aisa-ai-ic aisa-ai-ic--' + kind }, [ icon( iconName, 16 ) ] ),
			el( 'span', {}, [ el( 'strong', { text: title } ), hintText ? el( 'small', { text: hintText } ) : null ] )
		] );
		b.addEventListener( 'click', function () { prefill( text ); } );
		return b;
	}
	var TYPE_LABELS = {
		text: [ 'typeText', 'Text', 'text' ], name: [ 'typeText', 'Text', 'text' ], firstName: [ 'typeText', 'Text', 'text' ], lastName: [ 'typeText', 'Text', 'text' ],
		email: [ 'typeEmail', 'Email', 'mail' ], tel: [ 'typePhone', 'Phone', 'phone' ], mobile: [ 'typePhone', 'Phone', 'phone' ], phone: [ 'typePhone', 'Phone', 'phone' ],
		textarea: [ 'typeLongText', 'Long text', 'para' ], number: [ 'typeNumber', 'Number', 'hash' ], range: [ 'typeNumber', 'Number', 'hash' ],
		select: [ 'typeDropdown', 'Dropdown', 'select' ], multiselect: [ 'typeDropdown', 'Dropdown', 'select' ],
		radio: [ 'typeChoice', 'Choice', 'radio' ], chlRadio: [ 'typeChoice', 'Choice', 'radio' ], imgRadio: [ 'typeChoice', 'Choice', 'radio' ],
		checkbox: [ 'typeCheckboxes', 'Checkboxes', 'checkbox' ], chlCheckBox: [ 'typeCheckboxes', 'Checkboxes', 'checkbox' ],
		date: [ 'typeDate', 'Date', 'calendar' ], file: [ 'typeFile', 'File upload', 'upload' ], dadfile: [ 'typeFile', 'File upload', 'upload' ],
		url: [ 'typeWebsite', 'Website', 'link' ], rating: [ 'typeRating', 'Rating', 'star' ], trmCheckbox: [ 'typeConsent', 'Consent', 'shield' ],
		yesNo: [ 'typeYesNo', 'Yes / No', 'radio' ], switch: [ 'typeYesNo', 'Yes / No', 'radio' ]
	};
	function typeLabel( type ) { var entry = TYPE_LABELS[ type ]; return entry ? t( entry[ 0 ], entry[ 1 ] ) : String( type || '' ); }
	function typeIcon( type ) { var entry = TYPE_LABELS[ type ]; return entry ? entry[ 2 ] : 'text'; }
	/* ---------- EFB live structure helpers ---------- */
	function hasLiveForm() { return typeof valj_efb !== 'undefined' && Array.isArray( valj_efb ) && valj_efb.length > 2; }
	function getFormId() { var m = /[?&]id=(\d+)/.exec( window.location.search ); return m ? Number( m[ 1 ] ) : 0; }
	function isFieldItem( it ) { return it && it.type && [ 'form', 'step', 'option', 'r_matrix' ].indexOf( it.type ) === -1; }
	function liveFields() {
		if ( ! hasLiveForm() ) { return []; }
		return valj_efb.filter( isFieldItem ).map( function ( it ) {
			var options = valj_efb.filter( function ( o ) { return o && o.type === 'option' && o.parent === it.id_; } ).map( function ( o ) { return { id: String( o.id_ ), value: String( o.value || '' ) }; } );
			return { id: it.id_, label: it.name || it.value || it.id_, type: it.type, required: it.required === true || it.required === 1 || it.required === '1', options: options };
		} );
	}
	// EFB only loads its Conditional Logic runtime when the AdnSMF add-on is active.
	function logicAddonActive() { return typeof efb_var !== 'undefined' && !! efb_var && !! efb_var.addons && Number( efb_var.addons.AdnSMF ) >= 1; }
	function liveLogicRules() { return hasLiveForm() && Array.isArray( valj_efb[ 0 ].logic_rules ) ? valj_efb[ 0 ].logic_rules : []; }
	// Human Shield (AdnHSH) is site-wide spam protection: active means every form is covered.
	function spamShieldActive() { return typeof efb_var !== 'undefined' && !! efb_var && !! efb_var.addons && Number( efb_var.addons.AdnHSH ) >= 1; }
	function liveCaptcha() { if ( ! hasLiveForm() ) { return null; } var c = valj_efb[ 0 ].captcha; return c === true || c === 1 || c === '1' || c === 'true'; }
	function liveSteps() {
		if ( typeof valj_efb === 'undefined' || ! Array.isArray( valj_efb ) ) { return []; }
		return valj_efb.filter( function ( it ) { return it && it.type === 'step'; } ).map( function ( it ) {
			return { id: String( it.id_ || it.step || '' ), label: String( it.name || '' ) };
		} ).filter( function ( it ) { return !! it.id; } );
	}
	function findField( target ) {
		var t = ( target || '' ).toString().toLowerCase();
		return valj_efb.find( function ( it ) { return isFieldItem( it ) && ( String( it.id_ ).toLowerCase() === t || String( it.name || '' ).toLowerCase() === t ); } ) || null;
	}
	function selectedLiveField() {
		return selectedField ? findField( selectedField.id ) : null;
	}
	function markSelectedField() {
		document.querySelectorAll( '.aisaautopilot-efb-field-selected' ).forEach( function ( node ) { node.classList.remove( 'aisaautopilot-efb-field-selected' ); } );
		var selected = selectedLiveField();
		if ( ! selected ) { return; }
		document.querySelectorAll( '.efbField[data-id]' ).forEach( function ( node ) {
			if ( node.getAttribute( 'data-id' ) === String( selected.id_ ) + '-id' ) { node.classList.add( 'aisaautopilot-efb-field-selected' ); }
		} );
	}
	function formName() { return hasLiveForm() && valj_efb[ 0 ] ? String( valj_efb[ 0 ].formName || '' ) : ''; }
	function updateContext() {
		var context = $( 'aisaautopilot-efb-context' );
		if ( ! context ) { return; }
		if ( ! configured ) { context.textContent = t( 'ctxOffline', 'Not connected yet' ); return; }
		if ( ! hasLiveForm() ) { context.textContent = t( 'ctxNewForm', 'New form' ); return; }
		var count = liveFields().length;
		var name = formName();
		context.textContent = ( name ? t( 'ctxEditing', 'Editing “%s”', name ) : t( 'editingForm', 'Editing the current form' ) ) + ' · ' + ( 1 === count ? t( 'oneField', '1 field' ) : t( 'fieldsCount', '%s fields', count ) );
	}
	function updateSelection() {
		var selected = selectedLiveField();
		if ( selectedField && ! selected ) { selectedField = null; }
		if ( selected ) { selectedField = { id: String( selected.id_ ), label: String( selected.name || selected.value || selected.id_ ), type: String( selected.type ) }; }
		var chip = $( 'aisaautopilot-efb-selection' );
		var label = $( 'aisaautopilot-efb-selection-label' );
		if ( chip ) { chip.hidden = ! selectedField; }
		if ( label ) { label.textContent = selectedField ? selectedField.label : ''; label.setAttribute( 'title', selectedField ? t( 'workingOn', 'Working on %s', selectedField.label ) : '' ); }
		updateContext();
		markSelectedField();
	}
	function fieldNode( id ) { return document.querySelector( '.efbField[data-id="' + String( id ).replace( /"/g, '' ) + '-id"]' ); }
	/* "Ask AI about …": offered beside a field picked while the dock is closed. */
	function fieldBar() {
		var bar = $( 'aisaautopilot-efb-fieldbar' );
		if ( bar ) { return bar; }
		bar = el( 'button', { type: 'button', id: 'aisaautopilot-efb-fieldbar', class: 'aisaautopilot-efb-fieldbar', hidden: true }, [ el( 'span', { class: 'aisaautopilot-efb-fieldbar__ic' }, [ icon( 'spark', 13 ) ] ), el( 'span', { 'data-aisa-fieldbar-label': '' } ) ] );
		bar.addEventListener( 'click', function () { hideFieldBar(); open( true ); introduceField(); } );
		document.body.appendChild( bar );
		return bar;
	}
	function showFieldBar() {
		var node = selectedField ? fieldNode( selectedField.id ) : null;
		if ( ! node ) { return; }
		var bar = fieldBar();
		bar.querySelector( '[data-aisa-fieldbar-label]' ).textContent = t( 'askAbout', 'Ask AI about “%s”', selectedField.label );
		bar.hidden = false;
		var box = node.getBoundingClientRect();
		var rtl = 'rtl' === getComputedStyle( document.documentElement ).direction;
		var top = box.top + window.scrollY - bar.offsetHeight - 10;
		var left = rtl ? box.left + window.scrollX : box.right + window.scrollX - bar.offsetWidth;
		bar.style.top = Math.max( window.scrollY + 40, top ) + 'px';
		bar.style.left = Math.max( 8, Math.min( left, window.scrollX + document.documentElement.clientWidth - bar.offsetWidth - 8 ) ) + 'px';
	}
	function hideFieldBar() { var bar = $( 'aisaautopilot-efb-fieldbar' ); if ( bar ) { bar.hidden = true; } }
	function introduceField() {
		if ( ! selectedField || ! greeted || lastIntroFieldId === selectedField.id ) { return; }
		addAi( el( 'div', { class: 'aisa-ai-text', dir: 'auto', 'data-sp-efb-intro': 'field', text: t( 'fieldIntro', 'You selected “%s”. Tell me what to change — make it required, rename it, or add help text.', selectedField.label ) } ) );
		lastIntroFieldId = selectedField.id;
	}
	function selectFieldFromTarget( target ) {
		if ( ! target || ! target.closest || target.closest( '#aisaautopilot-efb-chat, #aisaautopilot-efb-launch, #aisaautopilot-efb-pop, #aisaautopilot-efb-fieldbar' ) ) { return; }
		var node = target.closest( '.efbField[data-id]' );
		if ( ! node ) { hideFieldBar(); return; }
		var dataId = String( node.getAttribute( 'data-id' ) || '' );
		if ( dataId.slice( -3 ) !== '-id' ) { return; }
		var field = findField( dataId.slice( 0, -3 ) );
		if ( ! field ) { return; }
		selectedField = { id: String( field.id_ ), label: String( field.name || field.value || field.id_ ), type: String( field.type ) };
		updateSelection();
		if ( isOpen() ) { introduceField(); } else if ( configured ) { showFieldBar(); }
	}
	function clearSelection() { selectedField = null; hideFieldBar(); updateSelection(); var input = $( 'aisaautopilot-efb-input' ); if ( input ) { input.focus(); } }
	function maxAmount() { var m = 0; valj_efb.forEach( function ( it ) { var a = Number( it.amount ); if ( a > m ) { m = a; } } ); return m; }
	function propertyApplies( definition, field ) {
		var types = definition && definition.applies_to;
		return ! Array.isArray( types ) || ! types.length || types.indexOf( '*' ) !== -1 || types.indexOf( field.type ) !== -1;
	}
	function enumValue( definition, value ) {
		var values = definition ? definition.values : null;
		if ( ! values ) { return value; }
		if ( Array.isArray( values ) ) {
			// EFB compares style names case-sensitively, so store the catalog's spelling.
			for ( var j = 0; j < values.length; j++ ) { if ( String( values[ j ] ).toLowerCase() === String( value ).toLowerCase() ) { return values[ j ]; } }
			return value;
		}
		var wanted = String( value ).toLowerCase();
		var keys = Object.keys( values );
		for ( var i = 0; i < keys.length; i++ ) {
			if ( keys[ i ].toLowerCase() === wanted || String( values[ keys[ i ] ] ).toLowerCase() === wanted ) { return values[ keys[ i ] ]; }
		}
		return value;
	}
	function catalogValue( definition, value, isForm ) {
		if ( definition.value_type === 'boolean_flag' ) { return isForm ? !! value : ( value ? '1' : '0' ); }
		if ( definition.value_type === 'enum' ) { return enumValue( definition, value ); }
		if ( definition.value_type === 'integer' || definition.value_type === 'number_or_date' ) { return String( value ); }
		return value;
	}
	function setPath( target, path, value ) {
		var parts = String( path ).split( '.' );
		var object = target;
		for ( var i = 0; i < parts.length - 1; i++ ) {
			if ( ! object[ parts[ i ] ] || typeof object[ parts[ i ] ] !== 'object' ) { object[ parts[ i ] ] = {}; }
			object = object[ parts[ i ] ];
		}
		object[ parts[ parts.length - 1 ] ] = value;
	}
	function propertyLabel( key ) { return String( key || '' ).replace( /_/g, ' ' ); }
	function enumAllows( definition, value ) {
		var values = definition && definition.values ? definition.values : {};
		var wanted = String( value ).toLowerCase();
		if ( Array.isArray( values ) ) { return values.some( function ( item ) { return String( item ).toLowerCase() === wanted; } ); }
		return Object.keys( values ).some( function ( key ) { return key.toLowerCase() === wanted || String( values[ key ] ).toLowerCase() === wanted; } );
	}
	var STEP_STYLE_PROPERTIES = [ 'steps_style', 'progress_style', 'hide_steps_row', 'hide_progress_bar' ];
	// EFB already translates its style names for the settings picker; reuse them so
	// the chat names a style exactly as the picker beside it does.
	var STYLE_LABEL_KEYS = { classic: 'styleClassic', circles: 'stepStyleCircles', pills: 'stepStylePills', chevrons: 'stepStyleChevrons', bar: 'progStyleBar', segments: 'progStyleSegments', ring: 'progStyleRing' };
	function styleName( style ) {
		var key = STYLE_LABEL_KEYS[ style ];
		var text = typeof efb_var !== 'undefined' && efb_var && efb_var.text ? efb_var.text : {};
		return key && text[ key ] ? String( text[ key ] ) : String( style );
	}
	function formPropertyApplied( property, value ) {
		if ( property === 'steps_style' ) { return t( 'stepsStyleSet', 'Steps style set to %s', styleName( value ) ); }
		if ( property === 'progress_style' ) { return t( 'progressStyleSet', 'Progress bar style set to %s', styleName( value ) ); }
		if ( property === 'hide_steps_row' ) { return value ? t( 'stepsRowHidden', 'Steps row hidden' ) : t( 'stepsRowShown', 'Steps row shown' ); }
		if ( property === 'hide_progress_bar' ) { return value ? t( 'progressBarHidden', 'Progress bar hidden' ) : t( 'progressBarShown', 'Progress bar shown' ); }
		return t( 'formPropertyUpdated', 'Updated the form setting “%s”', propertyLabel( property ) );
	}
	// EFB draws its form settings panel once. When it is open, keep its hide toggles
	// and style pickers in step with what the chat just changed on valj_efb[0].
	function syncStepStyleControls() {
		var form = valj_efb[ 0 ];
		if ( ! form ) { return; }
		[ [ 'showSIconsEl', 'show_icon', 'steps_style' ], [ 'showSprosiEl', 'show_pro_bar', 'progress_style' ] ].forEach( function ( row ) {
			var hidden = form[ row[ 1 ] ] === true || Number( form[ row[ 1 ] ] ) === 1;
			var toggle = document.getElementById( row[ 0 ] );
			if ( toggle ) { toggle.classList.toggle( 'active', hidden ); }
			var picker = document.getElementById( 'efb-picker-' + row[ 2 ] );
			if ( ! picker ) { return; }
			picker.classList.toggle( 'd-none', hidden );
			var current = String( form[ row[ 2 ] ] || 'classic' );
			Array.prototype.forEach.call( picker.querySelectorAll( '.efb-sp-picker__opt' ), function ( option ) {
				var on = option.getAttribute( 'data-style' ) === current;
				option.classList.toggle( 'active', on );
				option.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			} );
		} );
	}
	// One applied change: the sentence for the history, and what the card shows about it.
	function done( report, text, kind, fieldId, extra ) {
		report.applied.push( text );
		report.items.push( Object.assign( { text: text, kind: kind, field: fieldId || '' }, extra || {} ) );
	}
	function ruleTarget( rule ) {
		var acts = rule && Array.isArray( rule.actions ) ? rule.actions : [];
		return acts.length ? String( acts[ 0 ].target || '' ) : '';
	}
	function failure( report, index, op, reason ) {
		report.failed.push( { index: index, operation: String( op.op || 'unknown' ), target: String( op.target || '' ), reason: reason } );
	}
	// Reason codes stay in the client log; people read a translated sentence.
	var REASONS = {
		invalid_operation: [ 'reasonInvalidOperation', 'the operation was malformed' ],
		field_not_found: [ 'reasonFieldNotFound', 'the field was not found in this form' ],
		unsupported_field_property: [ 'reasonUnsupportedFieldProperty', 'that field setting cannot be changed here' ],
		property_not_available: [ 'reasonPropertyNotAvailable', 'that setting is not available for this field type' ],
		invalid_colour_or_storage: [ 'reasonInvalidColour', 'the colour or setting value is not valid' ],
		form_property_not_available: [ 'reasonFormPropertyNotAvailable', 'that form setting is not available' ],
		invalid_form_property_value: [ 'reasonInvalidFormPropertyValue', 'Easy Form Builder does not accept that value' ],
		step_template_missing: [ 'reasonStepTemplateMissing', 'the step template was missing from the reply' ],
		field_template_missing: [ 'reasonFieldTemplateMissing', 'the field template was missing from the reply' ],
		invalid_or_duplicate_field_template: [ 'reasonInvalidFieldTemplate', 'the new field was invalid or its ID already exists' ],
		target_is_not_a_choice_field: [ 'reasonNotChoiceField', 'options can only be added to a choice field' ],
		option_not_found: [ 'reasonOptionNotFound', 'the option was not found' ],
		logic_addon_inactive: [ 'reasonLogicAddonInactive', 'the Conditional Logic add-on is not active on this form' ],
		unsupported_operation: [ 'reasonUnsupportedOperation', 'this change is not supported' ],
		no_operation: [ 'reasonNoOperation', 'the reply contained no change to apply' ],
		exception: [ 'reasonException', 'an unexpected error stopped this change' ],
		render: [ 'reasonRender', 'the Easy Form Builder canvas could not redraw' ]
	};
	function reasonCode( failed ) {
		var code = String( failed && failed.reason ? failed.reason : 'unknown' );
		if ( code.indexOf( 'exception:' ) === 0 ) { return 'exception'; }
		if ( failed && failed.operation === 'render' ) { return 'render'; }
		return code;
	}
	function reasonText( code ) {
		var entry = REASONS[ code ];
		return entry ? t( entry[ 0 ], entry[ 1 ] ) : t( 'reasonUnknown', 'this change could not be applied' );
	}
	function note( text, kind, reason ) {
		var attrs = { class: 'aisa-ai-note aisaautopilot-efb-inline-note', dir: 'auto', text: text, 'data-sp-efb-note': kind };
		if ( reason ) { attrs[ 'data-sp-efb-reason' ] = reason; }
		return el( 'div', attrs );
	}
	var WIDTH_LABELS = { full: [ 'widthFull', 'full width' ], half: [ 'widthHalf', 'half width' ], third: [ 'widthThird', 'one third' ], quarter: [ 'widthQuarter', 'one quarter' ] };
	function widthLabel( value ) { var entry = WIDTH_LABELS[ value ]; return entry ? t( entry[ 0 ], entry[ 1 ] ) : String( value ); }
	function colorToken( value ) {
		var hex = String( value || '' ).toLowerCase();
		var colors = { '#0d6efd': 'primary', '#198754': 'success', '#6c757d': 'secondary', '#ff455f': 'danger', '#e9c31a': 'warning', '#31d2f2': 'info', '#fbfbfb': 'light', '#202a8d': 'darkb', '#898aa9': 'labelEfb', '#83859f': 'd', '#ff4b93': 'pinkEfb', '#ffffff': 'white', '#212529': 'dark', '#777777': 'muted' };
		return colors[ hex ] || ( /^#[a-f0-9]{6}$/.test( hex ) ? 'colorDEfb-' + hex.slice( 1 ) : null );
	}
	function applyEfbColor( target, definition, value ) {
		var token = colorToken( value );
		var key = definition.storage_key || definition.storage_path;
		if ( ! token || ! key ) { return false; }
		if ( key === 'button_color' ) { setPath( target, key, 'btn-' + token ); }
		else if ( key === 'el_border_color' ) { setPath( target, key, 'border-' + token ); }
		else { setPath( target, key, 'text-' + token ); }
		if ( token.indexOf( 'colorDEfb-' ) === 0 && definition.style_path ) { setPath( target, definition.style_path, value ); }
		return true;
	}
	function isChoiceField( field ) { return field && [ 'select', 'radio', 'checkbox', 'multiselect', 'chlCheckBox', 'chlRadio' ].indexOf( field.type ) !== -1; }
	function findOption( field, text ) {
		var wanted = String( text ).toLowerCase();
		return valj_efb.find( function ( it ) { return it && it.type === 'option' && it.parent === field.id_ && String( it.value ).toLowerCase() === wanted; } ) || null;
	}
	function addLogicRules( op, report, index ) {
		if ( ! logicAddonActive() ) { failure( report, index, op, 'logic_addon_inactive' ); return; }
		var rules = op.rule && typeof op.rule === 'object' ? [ op.rule ] : [];
		if ( ! rules.length ) { failure( report, index, op, 'invalid_operation' ); return; }
		var ids = {};
		valj_efb.forEach( function ( it ) { if ( it && it.id_ !== undefined ) { ids[ String( it.id_ ) ] = true; } } );
		// The server resolved every reference against this canvas; a stale reply
		// (the field was removed since) must not leave a rule pointing at nothing.
		for ( var r = 0; r < rules.length; r++ ) {
			var rule = rules[ r ];
			var items = rule && rule.conditions && Array.isArray( rule.conditions.items ) ? rule.conditions.items : [];
			var acts = rule && Array.isArray( rule.actions ) ? rule.actions : [];
			if ( ! items.length || ! acts.length ) { failure( report, index, op, 'invalid_operation' ); return; }
			for ( var c = 0; c < items.length; c++ ) { if ( ! ids[ String( items[ c ].field_id ) ] ) { failure( report, index, op, 'field_not_found' ); return; } }
			for ( var a = 0; a < acts.length; a++ ) { if ( ! ids[ String( acts[ a ].target ) ] ) { failure( report, index, op, 'field_not_found' ); return; } }
		}
		if ( ! Array.isArray( valj_efb[ 0 ].logic_rules ) ) { valj_efb[ 0 ].logic_rules = []; }
		rules.forEach( function ( rule ) {
			var exists = valj_efb[ 0 ].logic_rules.some( function ( it ) { return it && it.id === rule.id; } );
			if ( ! exists ) { valj_efb[ 0 ].logic_rules.push( JSON.parse( JSON.stringify( rule ) ) ); }
		} );
		// The add-on's own saveRules() raises this flag whenever an enabled field rule exists.
		valj_efb[ 0 ].logic = true;
		done( report, t( 'logicRuleAdded', 'Added the rule: %s', op.summary || rules[ 0 ].name || '' ), 'logic', ruleTarget( rules[ 0 ] ), { display: op.display } );
		report.changed = true;
		report.logic = true;
	}
	function addFieldItems( op, report, index, requestedStep ) {
		var items = Array.isArray( op.items ) ? op.items : [];
		if ( ! items.length ) { failure( report, index, op, 'field_template_missing' ); return; }
		var used = {};
		valj_efb.forEach( function ( item ) { if ( item && item.id_ ) { used[ String( item.id_ ) ] = true; } } );
		for ( var i = 0; i < items.length; i++ ) {
			if ( ! items[ i ] || ! items[ i ].id_ || ! items[ i ].type || used[ String( items[ i ].id_ ) ] ) { failure( report, index, op, 'invalid_or_duplicate_field_template' ); return; }
			used[ String( items[ i ].id_ ) ] = true;
		}
		var fields = valj_efb.filter( isFieldItem );
		var step = requestedStep ? String( requestedStep ) : ( fields.length ? String( fields[ fields.length - 1 ].step || '1' ) : '1' );
		var amount = maxAmount();
		var fieldLabel = op.field && op.field.label ? op.field.label : t( 'newFieldLabel', 'New field' );
		items.forEach( function ( source ) {
			var item = Object.assign( {}, source );
			amount += 1;
			item.amount = String( amount );
			item.step = step;
			valj_efb.push( item );
		} );
		var main = items.filter( function ( it ) { return it.type !== 'option'; } )[ 0 ] || items[ 0 ];
		done( report, t( 'fieldAdded', 'Added “%s”', fieldLabel ), 'add', String( main.id_ ), { type: String( main.type ), required: main.required === true || main.required === '1' || main.required === 1, label: fieldLabel, options: items.filter( function ( it ) { return it.type === 'option'; } ).map( function ( it ) { return String( it.value || '' ); } ) } );
		report.changed = true;
	}
	function addStepItem( op, report, index ) {
		var source = op && op.item && typeof op.item === 'object' ? op.item : null;
		if ( ! source || source.type !== 'step' ) { failure( report, index, op || {}, 'step_template_missing' ); return ''; }
		var used = {};
		valj_efb.forEach( function ( item ) { if ( item && item.id_ !== undefined ) { used[ String( item.id_ ) ] = true; } } );
		var highest = 0;
		liveSteps().forEach( function ( step ) { var n = Number( step.id ); if ( n > highest ) { highest = n; } } );
		var next = Math.max( 1, highest + 1 );
		while ( used[ String( next ) ] ) { next++; }
		var item = Object.assign( {}, source );
		item.id_ = String( next );
		item.dataId = String( next );
		item.id = String( next );
		item.type = 'step';
		item.classes = item.classes || 'stepNavEfb';
		item.name = String( op.label || item.name || t( 'stepDefaultName', 'Step %s', next ) );
		item.step = String( next );
		item.amount = String( maxAmount() + 1 );
		valj_efb.push( item );
		if ( valj_efb[ 0 ] ) {
			valj_efb[ 0 ].steps = String( next );
			valj_efb[ 0 ].button_state = 'multi';
		}
		done( report, t( 'stepAdded', 'Added the step “%s”', item.name ), 'add', '' );
		report.changed = true;
		return String( next );
	}
	function applyOps( ops ) {
		var report = { applied: [], items: [], failed: [], changed: false };
		var stepRefs = {};
		if ( ! Array.isArray( ops ) ) { return report; }
		ops.forEach( function ( op, index ) {
			try {
				if ( ! op || ! op.op ) { failure( report, index, op || {}, 'invalid_operation' ); return; }
				if ( op.op === 'set_form_name' ) { valj_efb[ 0 ].formName = op.value; done( report, t( 'formRenamed', 'Renamed the form to “%s”', op.value ), 'edit', '' ); report.changed = true; }
				else if ( op.op === 'set_confirmation' ) { if ( ! valj_efb[ 0 ].thank_you_message || typeof valj_efb[ 0 ].thank_you_message !== 'object' ) { valj_efb[ 0 ].thank_you_message = {}; } valj_efb[ 0 ].thank_you_message.thankYou = op.value; done( report, t( 'confirmationSet', 'Updated the confirmation message' ), 'edit', '' ); report.changed = true; }
				else if ( op.op === 'set_field' ) {
					var f = findField( op.target ); if ( ! f ) { failure( report, index, op, 'field_not_found' ); return; }
					if ( op.prop === 'label' ) { f.name = op.value; done( report, t( 'fieldRenamed', 'Renamed “%1$s” to “%2$s”', [ op.target, op.value ] ), 'edit', String( f.id_ ) ); }
					else if ( op.prop === 'required' ) { f.required = op.value ? '1' : '0'; done( report, op.value ? t( 'fieldRequired', 'Made “%s” required', op.target ) : t( 'fieldOptional', 'Made “%s” optional', op.target ), 'edit', String( f.id_ ) ); }
					else if ( op.prop === 'placeholder' ) { f.placeholder = op.value; done( report, t( 'placeholderSet', 'Set the placeholder of “%s”', op.target ), 'edit', String( f.id_ ) ); }
					else if ( op.prop === 'width' ) { f.size = WIDTH[ op.value ] || '100'; done( report, t( 'widthSet', 'Set the width of “%1$s” to %2$s', [ op.target, widthLabel( op.value ) ] ), 'edit', String( f.id_ ) ); }
					else if ( op.prop === 'help' ) { f.message = op.value; done( report, t( 'helpTextSet', 'Set the help text of “%s”', op.target ), 'edit', String( f.id_ ) ); }
					else { failure( report, index, op, 'unsupported_field_property' ); return; }
					report.changed = true;
				}
				else if ( op.op === 'set_field_property' ) {
					var pf = findField( op.target ); var pd = FIELD_PROPERTIES[ op.property ];
					if ( ! pf ) { failure( report, index, op, 'field_not_found' ); return; }
					if ( ! pd || ! pd.storage_key || ! propertyApplies( pd, pf ) ) { failure( report, index, op, 'property_not_available' ); return; }
					if ( pd.value_type === 'efb_color' ? ! applyEfbColor( pf, pd, op.value ) : ! pd.storage_key ) { failure( report, index, op, 'invalid_colour_or_storage' ); return; }
					if ( pd.value_type !== 'efb_color' ) {
						var stored = catalogValue( pd, op.value, false );
						pf[ pd.storage_key ] = stored;
						// Some settings live under two keys that EFB keeps identical
						// (a file field's category is both `file` and `value`); its own
						// panel writes both, so leaving one behind desyncs the field.
						if ( pd.mirror_keys && pd.mirror_keys.length ) {
							for ( var m = 0; m < pd.mirror_keys.length; m++ ) { pf[ pd.mirror_keys[ m ] ] = stored; }
						}
					}
					done( report, t( 'fieldSettingSet', 'Updated %1$s for “%2$s”', [ propertyLabel( op.property ), op.target ] ), 'edit', String( pf.id_ ) ); report.changed = true;
				}
				else if ( op.op === 'set_form_property' ) {
					var fd = FORM_PROPERTIES[ op.property ];
					if ( ! fd || ! fd.storage_path || ! valj_efb[ 0 ] ) { failure( report, index, op, 'form_property_not_available' ); return; }
					// The server already validated the value. Check enums again so a stale or
					// tampered response never stores a style EFB would quietly draw as classic.
					if ( fd.value_type === 'enum' && ! enumAllows( fd, op.value ) ) { failure( report, index, op, 'invalid_form_property_value' ); return; }
					if ( fd.value_type === 'efb_color' ? ! applyEfbColor( valj_efb[ 0 ], fd, op.value ) : ! fd.storage_path ) { failure( report, index, op, 'invalid_colour_or_storage' ); return; }
					if ( fd.value_type !== 'efb_color' ) { setPath( valj_efb[ 0 ], fd.storage_path, catalogValue( fd, op.value, true ) ); }
					if ( STEP_STYLE_PROPERTIES.indexOf( op.property ) !== -1 ) { report.stepStyles = true; }
					done( report, formPropertyApplied( op.property, catalogValue( fd, op.value, true ) ), 'edit', '' ); report.changed = true;
				}
				else if ( op.op === 'add_step' ) { var newStep = addStepItem( op, report, index ); if ( newStep && op.step_ref ) { stepRefs[ op.step_ref ] = newStep; } }
				else if ( op.op === 'add_field' ) { addFieldItems( op, report, index, op.step_ref ? stepRefs[ op.step_ref ] : '' ); }
				else if ( op.op === 'remove_field' ) { var rf = findField( op.target ); if ( ! rf ) { failure( report, index, op, 'field_not_found' ); return; } valj_efb = valj_efb.filter( function ( it ) { return it !== rf && it.parent !== rf.id_; } ); window.valj_efb = valj_efb; done( report, t( 'fieldRemoved', 'Removed “%s”', op.target ), 'remove', '' ); report.changed = true; }
				else if ( op.op === 'add_option' ) {
					var af = findField( op.target ); if ( ! af ) { failure( report, index, op, 'field_not_found' ); return; }
					if ( ! isChoiceField( af ) ) { failure( report, index, op, 'target_is_not_a_choice_field' ); return; }
					var oid = ( af.id_ + '_o' + ( maxAmount() + 1 ) ).replace( /[^a-z0-9_]/gi, '' );
					var opt = { id_: oid, dataId: oid + '-id', parent: af.id_, type: 'option', value: op.value, id_op: oid, step: af.step || '1', amount: String( maxAmount() + 1 ) };
					var lastIdx = valj_efb.length - 1;
					for ( var i = valj_efb.length - 1; i >= 0; i-- ) { if ( valj_efb[ i ].parent === af.id_ || valj_efb[ i ] === af ) { lastIdx = i; break; } }
					valj_efb.splice( lastIdx + 1, 0, opt ); done( report, t( 'optionAdded', 'Added the option “%1$s” to “%2$s”', [ op.value, op.target ] ), 'add', String( af.id_ ) ); report.changed = true;
				}
				else if ( op.op === 'remove_option' ) { var of = findField( op.target ); if ( ! of ) { failure( report, index, op, 'field_not_found' ); return; } var hadOption = valj_efb.some( function ( it ) { return it.type === 'option' && it.parent === of.id_ && String( it.value ).toLowerCase() === String( op.value ).toLowerCase(); } ); if ( ! hadOption ) { failure( report, index, op, 'option_not_found' ); return; } valj_efb = valj_efb.filter( function ( it ) { return ! ( it.type === 'option' && it.parent === of.id_ && String( it.value ).toLowerCase() === String( op.value ).toLowerCase() ); } ); window.valj_efb = valj_efb; done( report, t( 'optionRemoved', 'Removed the option “%1$s” from “%2$s”', [ op.value, op.target ] ), 'remove', String( of.id_ ) ); report.changed = true; }
				else if ( op.op === 'rename_option' ) {
					var nf = findField( op.target ); if ( ! nf ) { failure( report, index, op, 'field_not_found' ); return; }
					var no = findOption( nf, op.from ); if ( ! no ) { failure( report, index, op, 'option_not_found' ); return; }
					no.value = op.value; done( report, t( 'optionRenamed', 'Renamed the option “%1$s” to “%2$s”', [ op.from, op.value ] ), 'edit', String( nf.id_ ) ); report.changed = true;
				}
				else if ( op.op === 'add_logic_rule' ) { addLogicRules( op, report, index ); }
				else { failure( report, index, op, 'unsupported_operation' ); }
			} catch ( e ) { failure( report, index, op || {}, 'exception:' + String( e && e.message ? e.message : 'unknown' ).slice( 0, 120 ) ); }
		} );
		if ( report.changed ) { window.valj_efb = valj_efb; }
		return report;
	}
	function refreshDropzone() {
		try {
			sessionStorage.setItem( 'valj_efb', JSON.stringify( valj_efb ) );
			if ( typeof editFormEfb === 'function' ) { editFormEfb(); return { ok: true }; }
			return { ok: false, reason: 'efb_renderer_not_available' };
		} catch ( e ) { return { ok: false, reason: String( e && e.message ? e.message : 'refresh_failed' ).slice( 0, 160 ) }; }
	}

	/* ---------- the result cards ---------- */
	function upgradeActions( data ) {
		var url = data.upgrade_url || ( cfg.license && cfg.license.upgrade_url ) || 'https://whitestudio.team/#price';
		var upgrade = button( t( 'upgrade', 'Upgrade to EFB Pro' ), { kind: 'primary', size: 'sm', icon: 'pro', extra: 'aisaautopilot-efb-btn--pro' } );
		upgrade.addEventListener( 'click', function () {
			// Reuse EFB's own dialog, translated message, and official purchase flow.
			if ( typeof window.pro_show_efb === 'function' ) { beforeEfbDialog(); window.pro_show_efb( data.upgrade_reason === 'step_limit' ? 2 : 1 ); return; }
			window.open( url, '_blank', 'noopener' );
		} );
		var pricing = el( 'a', { class: 'aisa-ai-btn aisa-ai-btn--sm aisaautopilot-efb-btn aisaautopilot-efb-btn--ghost', href: url, target: '_blank', rel: 'noopener noreferrer' }, [ el( 'span', { text: t( 'pricing', 'View pricing' ) } ), icon( 'ext', 13 ) ] );
		return el( 'div', { class: 'aisa-ai-card__foot aisaautopilot-efb-actions' }, [ upgrade, pricing ] );
	}
	function setupLink( label ) {
		return el( 'a', { class: 'aisa-ai-btn aisa-ai-btn--primary aisa-ai-btn--lg aisa-ai-btn--grow', href: cfg.settingsUrl || '#', target: '_blank', rel: 'noopener', 'data-sp-efb-setup': '1' }, [ icon( 'key', 15 ), el( 'span', { text: label } ), icon( 'ext', 13 ) ] );
	}
	function renderMessage( data ) {
		var body;
		if ( data.ok === false ) {
			var parts = [ el( 'div', { class: 'aisa-ai-card__body' }, [ el( 'p', { dir: 'auto', text: data.reply || '' } ) ] ) ];
			if ( 'provider_not_configured' === data.code ) { parts.push( el( 'div', { class: 'aisa-ai-card__foot' }, [ setupLink( t( 'setupCta', 'Set up AI' ) ) ] ) ); }
			body = addAi( card( Dock.cardHead( 'warn', 'alert', t( 'couldNotDo', 'I could not do that' ) ), parts, { 'data-sp-efb-state': 'error' } ) );
		} else {
			body = addAi( data.reply || '' );
		}
		if ( data.errors && data.errors.length ) { body.appendChild( note( data.errors.join( ' ' ), 'server' ) ); }
		if ( data.upgrade_required ) { body.appendChild( card( Dock.cardHead( 'plan', 'pro', t( 'upgrade', 'Upgrade to EFB Pro' ) ), [ upgradeActions( data ) ] ) ); }
		history.push( { role: 'assistant', text: data.reply || '' } );
	}
	function renderPreview( data ) {
		var p = data.preview;
		var mini = el( 'div', { class: 'aisa-ai-mini' } );
		var pages = 1;
		p.fields.forEach( function ( f ) {
			if ( f.type === 'steps' ) { pages++; mini.appendChild( el( 'div', { class: 'aisa-ai-mini__step', dir: 'auto', text: t( 'previewStep', 'New page: %s', f.label ) } ) ); return; }
			var meta = f.options && f.options.length ? f.options.join( ' · ' ) : ( f.help || '' );
			mini.appendChild( el( 'div', { class: 'aisa-ai-mini__f', 'data-sp-efb-field': f.type }, [
				el( 'span', { class: 'aisa-ai-ic' }, [ icon( typeIcon( f.type ), 14 ) ] ),
				el( 'span', { class: 'aisa-ai-mini__l', dir: 'auto' }, [ f.label, f.required ? el( 'span', { class: 'aisa-ai-req', 'aria-label': t( 'required', 'required' ), text: '*' } ) : null, meta ? el( 'small', { text: meta } ) : null ] ),
				el( 'span', { class: 'aisa-ai-tag', text: typeLabel( f.type ) } )
			] ) );
		} );
		var facts = el( 'div', { class: 'aisa-ai-facts' } );
		if ( p.notifications && p.notifications.admin ) { facts.appendChild( el( 'span', { class: 'aisa-ai-tag', dir: 'auto' }, [ icon( 'mail', 12 ), ' ' + t( 'previewNotifyAdmin', 'Each submission is emailed to %s', p.notifications.admin ) ] ) ); }
		if ( p.notifications && p.notifications.respondent ) { facts.appendChild( el( 'span', { class: 'aisa-ai-tag', text: t( 'previewNotifyRespondent', 'The person who fills it in gets a confirmation email' ) } ) ); }
		if ( p.submit_button ) { facts.appendChild( el( 'span', { class: 'aisa-ai-tag', dir: 'auto', text: t( 'previewSubmit', 'Button: %s', p.submit_button ) } ) ); }
		var advice = ( p.suggestions || [] ).map( function ( s ) {
			return el( 'div', { class: 'aisa-ai-advice aisaautopilot-efb-finding aisaautopilot-efb-finding--' + ( s.severity === 'warning' ? 'warning' : 'info' ) }, [ icon( s.severity === 'warning' ? 'shield' : 'help', 16 ), el( 'span', { dir: 'auto', text: s.message } ) ] );
		} );
		var status = el( 'span', { class: 'aisa-ai-status aisaautopilot-efb-inline-note', role: 'status' } );
		var approve = button( t( 'createThisForm', 'Create this form' ), { kind: 'primary', size: 'lg', grow: true, icon: 'check', stroke: 2.4, extra: 'aisaautopilot-efb-btn--primary', attrs: { 'data-sp-efb-create': '1' } } );
		var discard = button( t( 'discard', 'Discard' ), { kind: 'quiet', size: 'lg', extra: 'aisaautopilot-efb-btn--ghost' } );
		var countText = t( 'fieldsCount', '%s fields', p.field_count ) + ' · ' + ( 1 === pages ? t( 'onePage', '1 page' ) : t( 'pagesCount', '%s pages', pages ) );
		var box = card( Dock.cardHead( 'plan', 'doc', p.title, countText ), [ mini, facts ].concat( advice, [
			data.errors && data.errors.length ? note( data.errors.join( ' ' ), 'server' ) : null,
			el( 'div', { class: 'aisa-ai-card__foot aisaautopilot-efb-actions' }, [ approve, discard, status ] )
		] ), { 'data-sp-efb-state': 'preview' } );
		approve.addEventListener( 'click', function () {
			approve.disabled = true; status.textContent = t( 'creating', 'Creating…' );
			api( 'efb/create', { action_id: data.action_id } ).then( function ( res ) {
				if ( ! res.data.ok ) { status.textContent = res.data.message || t( 'createFailed', 'The form could not be created. Please try again.' ); approve.disabled = false; return; }
				status.textContent = t( 'createdOpening', 'Created! Opening the editor…' );
				if ( res.data.result && res.data.result.edit_url ) { setTimeout( function () { leaving = true; window.location.href = res.data.result.edit_url; }, 600 ); }
			} ).catch( function () { status.textContent = t( 'networkError', 'Network error. Please try again.' ); approve.disabled = false; } );
		} );
		discard.addEventListener( 'click', function () { box.remove(); setSuggestions( [] ); } );
		var body = addAi( data.reply || '' );
		body.appendChild( box );
		// Fields the licence cannot render were left out of the plan; name them and offer the
		// upgrade instead of quietly shipping a lesser form.
		if ( data.upgrade_required ) { box.appendChild( upgradeActions( data ) ); }
		var next = [];
		if ( p.fields.some( function ( f ) { return ( f.type === 'tel' || f.type === 'mobile' ) && f.required; } ) ) { next.push( t( 'nextPhoneOptional', 'Make the phone number optional' ) ); }
		if ( pages === 1 && p.field_count > 6 ) { next.push( t( 'nextSteps', 'Split it into 2 steps' ) ); }
		setSuggestions( next );
		setComposerMode( 'preview' );
		history.push( { role: 'assistant', text: 'Proposed a form: ' + p.title } );
	}
	function ruleCard( item ) {
		var display = item.display || {};
		var conditions = Array.isArray( display.if ) ? display.if : [];
		var actions = Array.isArray( display.then ) ? display.then : [];
		var lines = [];
		if ( conditions.length && actions.length ) {
			var ifLine = el( 'div', { class: 'aisa-ai-rule' }, [ el( 'span', { class: 'aisa-ai-rule__kw', text: t( 'ruleIf', 'IF' ) } ) ] );
			conditions.forEach( function ( text, i ) {
				if ( i ) { ifLine.appendChild( el( 'span', { text: 'any' === display.match ? t( 'ruleAny', 'or' ) : t( 'ruleAll', 'and' ) } ) ); }
				ifLine.appendChild( el( 'span', { class: 'aisa-ai-rule__tok', dir: 'auto', text: text } ) );
			} );
			var thenLine = el( 'div', { class: 'aisa-ai-rule' }, [ el( 'span', { class: 'aisa-ai-rule__kw', text: t( 'ruleThen', 'THEN' ) } ) ] );
			actions.forEach( function ( text ) { thenLine.appendChild( el( 'span', { class: 'aisa-ai-rule__tok', dir: 'auto', text: text } ) ); } );
			lines.push( ifLine, thenLine );
		} else {
			lines.push( el( 'div', { class: 'aisa-ai-card__body', dir: 'auto', text: item.text } ) );
		}
		return card( Dock.cardHead( 'logic', 'logic', t( 'newRule', 'New conditional rule' ), t( 'notSavedYet', 'Not saved yet' ) ), lines, { 'data-sp-efb-state': 'logic' } );
	}
	function nextSuggestions( report ) {
		var next = [];
		var added = report.items.filter( function ( it ) { return 'add' === it.kind && it.label; } )[ 0 ];
		if ( added && ! added.required ) { next.push( t( 'nextRequired', 'Make “%s” required', added.label ) ); }
		if ( added ) { next.push( t( 'nextHelp', 'Add help text to “%s”', added.label ) ); }
		next.push( t( 'nextReview', 'Review this form' ) );
		return next;
	}
	function renderOps( data, traceId ) {
		if ( ! hasLiveForm() ) {
			clientLog( traceId, 'apply_unavailable', 'Browser did not expose a live EFB form.', { operation_count: ( data.operations || [] ).length, failed: [ { reason: 'live_form_not_available' } ] } );
			addAi( t( 'openFormFirst', 'Open a form in the editor first (create one here, or open Easy Form Builder → Forms and edit a form), then ask me to change it.' ) );
			history.push( { role: 'assistant', text: 'Asked user to open a form first.' } );
			return;
		}
		// One snapshot per AI change, so Undo restores exactly the canvas before it.
		var snapshot = JSON.parse( JSON.stringify( valj_efb ) );
		var report = applyOps( data.operations || [] );
		if ( ! report.applied.length ) {
			var code = report.failed.length ? reasonCode( report.failed[ 0 ] ) : 'no_operation';
			var reason = report.failed.length ? report.failed[ 0 ].reason : 'no_operation';
			clientLog( traceId, 'apply_failed', 'No requested operation was applied.', { operation_count: ( data.operations || [] ).length, failed: report.failed } );
			// The model's reply may say the change was made; when nothing was applied it is not shown.
			var refused = card( Dock.cardHead( 'warn', 'alert', t( 'nothingChanged', 'Nothing changed' ) ), [
				el( 'div', { class: 'aisa-ai-card__body' }, [
					el( 'p', { text: t( 'formUnchanged', 'Your form is exactly as it was.' ) } ),
					el( 'p', { class: 'aisaautopilot-efb-inline-note', 'data-sp-efb-note': 'no-change', 'data-sp-efb-reason': code, text: t( 'noChangeApplied', 'No change was applied: %s.', reasonText( code ) ) } )
				] ),
				data.errors && data.errors.length ? note( data.errors.join( ' ' ), 'server' ) : null
			], { 'data-sp-efb-state': 'refused' } );
			addAi( refused );
			setSuggestions( [] );
			history.push( { role: 'assistant', text: 'No operation applied: ' + reason } );
			return;
		}
		var refreshed = refreshDropzone();
		updateSelection();
		if ( report.stepStyles ) { syncStepStyleControls(); }
		if ( ! refreshed.ok ) { report.failed.push( { operation: 'render', reason: refreshed.reason || 'renderer_refresh_failed' } ); }
		clientLog( traceId, refreshed.ok ? 'apply_completed' : 'apply_render_failed', refreshed.ok ? 'Operations applied in the live EFB canvas.' : 'Operations changed the structure but the EFB renderer failed.', { operation_count: ( data.operations || [] ).length, applied: report.applied, failed: report.failed, renderer: refreshed } );

		var body = addAi( data.reply || t( 'done', 'Done.' ) );
		var plain = report.items.filter( function ( it ) { return 'logic' !== it.kind; } );
		var rules = report.items.filter( function ( it ) { return 'logic' === it.kind; } );
		var cards = [];
		if ( plain.length || report.failed.length || ! rules.length ) {
			var list = el( 'ul', { class: 'aisa-ai-rows' } );
			plain.forEach( function ( it ) {
				var tag = it.type ? typeLabel( it.type ) + ( it.required ? ' · ' + t( 'required', 'required' ) : '' ) : '';
				list.appendChild( Dock.row( it.kind, 'add' === it.kind ? 'plus' : ( 'remove' === it.kind ? 'minus' : 'edit' ), it.text, it.options && it.options.length ? it.options.join( ' · ' ) : '', tag, { class: 'aisaautopilot-efb-inline-note', 'data-sp-efb-note': 'applied' } ) );
			} );
			report.failed.forEach( function ( f ) {
				var failedCode = reasonCode( f );
				list.appendChild( Dock.row( 'fail', 'alert', t( 'notApplied', 'Not applied: %s', reasonText( failedCode ) ), '', '', { class: 'aisaautopilot-efb-inline-note', 'data-sp-efb-note': 'failed', 'data-sp-efb-reason': failedCode } ) );
			} );
			var total = plain.length;
			cards.push( card( Dock.cardHead( 'ok', 'check', 1 === total ? t( 'changesOne', '1 change on the form' ) : t( 'changesMany', '%s changes on the form', total ), t( 'notSavedYet', 'Not saved yet' ) ), [ total || report.failed.length ? list : null ], { 'data-sp-efb-state': 'applied' } ) );
		}
		rules.forEach( function ( it ) { cards.push( ruleCard( it ) ); } );
		cards.forEach( function ( node ) { body.appendChild( node ); } );
		var last = cards[ cards.length - 1 ];
		// Server-side notes (a batch cut at the operation limit, a rule that fights another) must
		// show here too, otherwise a partly-applied change still reads as a clean success.
		if ( data.errors && data.errors.length ) { last.appendChild( note( data.errors.join( ' ' ), 'server' ) ); }
		var foot = el( 'div', { class: 'aisa-ai-card__foot aisaautopilot-efb-actions' } );
		var ids = report.items.map( function ( it ) { return it.field; } ).filter( function ( id ) { return !! id; } );
		if ( refreshed.ok && ids.length ) { foot.appendChild( button( t( 'showOnForm', 'Show on form' ), { size: 'sm', icon: 'eye', onClick: function () { flashFields( report.items, true ); } } ) ); }
		if ( rules.length && typeof window.previewFormEfb === 'function' ) { foot.appendChild( button( t( 'testPreview', 'Test in Preview' ), { size: 'sm', icon: 'play', onClick: function () { beforeEfbDialog(); window.previewFormEfb( 'pc' ); } } ) ); }
		if ( refreshed.ok ) { foot.appendChild( undoButton( snapshot, traceId, unsaved, last ) ); }
		if ( foot.childNodes.length ) { last.appendChild( foot ); }
		if ( ! refreshed.ok ) { last.appendChild( note( t( 'canvasRefreshFail', 'The Easy Form Builder canvas could not redraw, so the editor reloads now. Check the form before you save it; trace %s is in AI Site Assistant AutoPilot → Activity Log.', traceId ), 'render-failed' ) ); }
		else {
			if ( rules.length ) { last.appendChild( hint( 'play', t( 'logicReviewNote', 'Open Conditional Logic to review the rule and test it in Preview before you save.' ), 'logic' ) ); }
			last.appendChild( hint( 'save', t( 'appliedSaveNote', 'Click Save at the top of Easy Form Builder to keep the changes.' ), 'save-ownership' ) );
		}
		scrollDown();
		history.push( { role: 'assistant', text: 'Applied: ' + report.applied.join( '; ' ) } );
		if ( ! refreshed.ok ) { setTimeout( function () { leaving = true; window.location.reload(); }, 800 ); return; }
		setUnsaved( unsaved + report.items.length );
		flashFields( report.items, false );
		setSuggestions( nextSuggestions( report ) );
		setComposerMode( 'live' );
	}
	/* Fields the assistant just added or changed, marked on EFB's own canvas. */
	function flashFields( items, scroll ) {
		clearTimeout( flashTimer );
		document.querySelectorAll( '.aisaautopilot-efb-flash' ).forEach( function ( node ) { node.classList.remove( 'aisaautopilot-efb-flash' ); node.removeAttribute( 'data-aisa-flash' ); } );
		var paint = function () {
			var first = null;
			items.forEach( function ( it ) {
				var node = it.field ? fieldNode( it.field ) : null;
				if ( ! node ) { return; }
				node.classList.add( 'aisaautopilot-efb-flash' );
				node.setAttribute( 'data-aisa-flash', 'add' === it.kind ? t( 'flashNew', 'AI · new' ) : t( 'flashChanged', 'AI · changed' ) );
				first = first || node;
			} );
			if ( scroll && first ) { first.scrollIntoView( { block: 'center', behavior: reducedMotion() ? 'auto' : 'smooth' } ); }
		};
		// EFB redraws its canvas asynchronously after editFormEfb().
		setTimeout( paint, 80 );
		setTimeout( paint, 450 );
		flashTimer = setTimeout( function () { document.querySelectorAll( '.aisaautopilot-efb-flash' ).forEach( function ( node ) { node.classList.remove( 'aisaautopilot-efb-flash' ); } ); }, 12000 );
	}
	/* On a phone the sheet sits above everything of EFB's, so it steps aside for EFB's own dialogs. */
	function beforeEfbDialog() { if ( window.matchMedia && window.matchMedia( '(max-width: 640px)' ).matches ) { close(); } }
	function reducedMotion() { return !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ); }
	// Undo restores the canvas from before this change, dropping any later ones too, so the
	// unsaved count goes back to what it was then.
	function undoButton( snapshot, traceId, unsavedBefore, holder ) {
		var undo = button( t( 'undoChange', 'Undo' ), { size: 'sm', icon: 'undo', attrs: { 'data-sp-efb-undo': '1' } } );
		undo.addEventListener( 'click', function () {
			undo.disabled = true;
			valj_efb = JSON.parse( JSON.stringify( snapshot ) );
			window.valj_efb = valj_efb;
			var refreshed = refreshDropzone();
			updateSelection();
			clientLog( traceId, refreshed.ok ? 'undo_completed' : 'undo_render_failed', refreshed.ok ? 'The AI change was undone on the canvas.' : 'Undo restored the structure but the renderer failed.', { failed: refreshed.ok ? [] : [ { reason: refreshed.reason || 'renderer_refresh_failed' } ] } );
			holder.appendChild( note( t( 'undone', 'Undone. The canvas is back to how it was before this change.' ), 'undone' ) );
			setUnsaved( unsavedBefore );
			history.push( { role: 'assistant', text: 'Undid the last change.' } );
			if ( ! refreshed.ok ) { setTimeout( function () { leaving = true; window.location.reload(); }, 800 ); }
		} );
		return undo;
	}
	function renderList( data, traceId ) {
		var body = addAi( data.reply || '' );
		var items = data.items || [];
		if ( items.length ) {
			// A review names its findings by code; an explanation of the logic does not.
			var review = items.some( function ( item ) { return !! item.code; } );
			var list = el( 'ul', { class: 'aisa-ai-finds aisaautopilot-efb-findings' } );
			items.forEach( function ( item ) {
				var li = el( 'li', { class: 'aisa-ai-find aisaautopilot-efb-finding aisaautopilot-efb-finding--' + ( item.severity === 'warning' ? 'warning' : 'info' ) + ( item.severity === 'warning' ? ' aisa-ai-find--warning' : '' ) }, [ el( 'span', { class: 'aisa-ai-find__dot', 'aria-hidden': 'true' } ), el( 'span', { class: 'aisa-ai-find__txt', dir: 'auto', text: item.message || '' } ) ] );
				if ( Array.isArray( item.operations ) && item.operations.length && hasLiveForm() ) {
					var apply = button( t( 'applySuggestion', 'Apply' ), { kind: 'primary', size: 'sm', icon: 'plus', stroke: 2.4, attrs: { 'data-sp-efb-apply': '1' } } );
					apply.addEventListener( 'click', function () {
						apply.disabled = true;
						renderOps( { operations: item.operations, reply: t( 'suggestionApplied', 'Applied the suggestion.' ) }, traceId );
					} );
					li.appendChild( apply );
				} else if ( review ) {
					li.appendChild( button( t( 'howTo', 'How?' ), { size: 'sm', attrs: { 'data-sp-efb-how': '1' }, onClick: function () { prefill( t( 'howToPrefill', 'How do I fix this: %s', item.message || '' ) ); } } ) );
				}
				list.appendChild( li );
			} );
			var head = review
				? Dock.cardHead( 'warn', 'wand', 1 === items.length ? t( 'suggestionsOne', '1 suggestion' ) : t( 'suggestionsMany', '%s suggestions', items.length ), hasLiveForm() ? t( 'fieldsChecked', '%s fields checked', liveFields().length ) : '' )
				: Dock.cardHead( 'logic', 'logic', t( 'starterLogic', 'Show or hide fields' ) );
			body.appendChild( card( head, [ list ], { 'data-sp-efb-state': review ? 'review' : 'explain' } ) );
		}
		if ( data.errors && data.errors.length ) { body.appendChild( note( data.errors.join( ' ' ), 'server' ) ); }
		setSuggestions( [] );
		history.push( { role: 'assistant', text: ( data.reply || '' ) + ' ' + items.map( function ( i ) { return i.message; } ).join( ' | ' ) } );
	}
	function renderHelp( data ) {
		if ( data.suggestions && data.suggestions.length ) {
			var box = addAi( data.reply || '' );
			box.appendChild( chips( data.suggestions, 'suggestion' ) );
			history.push( { role: 'assistant', text: data.reply || 'Suggested topics.' } );
			return;
		}
		var paragraphs = ( data.answer || '' ).split( '\n\n' ).filter( function ( para ) { return !! para.trim(); } ).map( function ( para ) { return el( 'p', { dir: 'auto', text: para.trim() } ); } );
		var body = addAi( data.reply || '' );
		body.appendChild( card( Dock.cardHead( 'plan', 'help', data.title || t( 'starterHelp', 'How do I…?' ) ), [ el( 'div', { class: 'aisa-ai-card__body' }, paragraphs ) ], { 'data-sp-efb-state': 'help' } ) );
		history.push( { role: 'assistant', text: ( data.title || 'How-to' ) + ' answered.' } );
	}
	function renderResponse( data, traceId ) {
		if ( data.reply ) { speakReply( data.reply ); }
		if ( data.type === 'preview' ) { renderPreview( data ); }
		else if ( data.type === 'ops' ) { renderOps( data, traceId ); }
		else if ( data.type === 'help' ) { renderHelp( data ); }
		else if ( data.type === 'list' ) { renderList( data, traceId ); }
		else { renderMessage( data ); }
		// Cards are added after their turn, so the thread scrolls once they are in.
		scrollDown();
	}
	/* ---------- send ---------- */
	function setBusy( on ) {
		busy = on;
		var send = $( 'aisaautopilot-efb-send' );
		if ( send ) { send.disabled = on || ! configured; }
		var input = $( 'aisaautopilot-efb-input' );
		if ( input && configured ) { input.setAttribute( 'placeholder', on ? t( 'phWorking', 'Working on it…' ) : placeholderFor( composerMode ) ); }
	}
	function placeholderFor( mode ) {
		if ( ! configured ) { return t( 'phOffline', 'Connect an AI service to start…' ); }
		if ( 'preview' === mode ) { return t( 'phBeforeCreate', 'Ask for a change before creating it…' ); }
		if ( 'live' === mode ) { return t( 'phNext', 'Ask for another change…' ); }
		return hasLiveForm() ? t( 'phLive', 'Describe a change, or click a field on the form…' ) : t( 'phNew', 'Describe the form you need…' );
	}
	function setComposerMode( mode ) {
		composerMode = mode;
		var input = $( 'aisaautopilot-efb-input' );
		if ( input ) { input.setAttribute( 'placeholder', placeholderFor( mode ) ); }
		var foot = $( 'aisaautopilot-efb-foot' );
		if ( foot ) { foot.textContent = hasLiveForm() ? t( 'footLive', 'AI suggests, you decide. Nothing is saved until you click Save.' ) : t( 'footNew', 'AI suggests, you decide. Nothing is created until you click Create.' ); }
	}
	function submit( text ) {
		text = ( text || '' ).trim();
		if ( ! text || busy || ! configured ) { return; }
		addUser( text );
		lastIntroFieldId = '';
		setSuggestions( [] );
		var prior = history.slice();
		history.push( { role: 'user', text: text } );
		var input = $( 'aisaautopilot-efb-input' );
		input.value = ''; autoGrow();
		setBusy( true );
		addThinking();
		var traceId = makeTraceId();
		var fields = liveFields();
		var steps = liveSteps();
		var selected = selectedLiveField();
		var selectedId = selected ? String( selected.id_ ) : '';
		clientLog( traceId, 'chat_submitted', 'Chat request sent from the EFB editor.', { message: text.slice( 0, 200 ), has_form: hasLiveForm(), field_count: fields.length, step_count: steps.length, selected_field: selectedId, field_ids: fields.map( function ( field ) { return field.id; } ) } );
		api( 'efb/chat', { trace_id: traceId, message: text, history: prior, has_form: hasLiveForm(), fields: fields, steps: steps, selected_field: selectedId, logic_addon: logicAddonActive(), logic_rules: liveLogicRules(), captcha: liveCaptcha(), spam_shield: spamShieldActive() } )
			.then( function ( res ) {
				removeTyping(); setBusy( false );
				var data = res.data || { type: 'message', reply: t( 'genericError', 'Sorry, something went wrong.' ) };
				if ( data.ok === false && ! data.reply ) { data.type = 'message'; data.reply = data.message || t( 'requestFailed', 'The request could not be completed.' ); }
				var context = { status: res.status, response_type: data.type || 'message', code: data.code || '', operation_count: Array.isArray( data.operations ) ? data.operations.length : 0 };
				clientLog( traceId, data.ok === false ? 'chat_response_error' : 'chat_response', data.ok === false ? ( data.message || 'Chat endpoint returned an error.' ) : 'Chat response received.', context );
				renderResponse( data, traceId );
			} )
			.catch( function ( error ) {
				removeTyping(); setBusy( false );
				clientLog( traceId, 'chat_network_error', 'The browser could not reach the chat endpoint.', { failed: [ { reason: String( error && error.message ? error.message : 'network_error' ).slice( 0, 160 ) } ] } );
				renderMessage( { ok: false, code: 'network_error', reply: t( 'networkError', 'Network error. Please try again.' ) } );
			} );
	}
	/* Voice never sends a command by itself: the transcript is placed in the composer for review. */
	function voiceStatus( message ) { var status = $( 'aisaautopilot-efb-voice-status' ); if ( status ) { status.textContent = message; status.classList.toggle( 'is-visible', !! message ); } }
	function setVoiceButton( recording ) { var button = $( 'aisaautopilot-efb-voice' ); if ( button ) { button.classList.toggle( 'is-recording', recording ); button.setAttribute( 'aria-pressed', recording ? 'true' : 'false' ); } }
	function stopLiveRecognition() { if ( recognition ) { try { recognition.stop(); } catch ( e ) {} } }
	function startLiveRecognition() {
		var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
		if ( ! SpeechRecognition ) { return; }
		voiceFinalText = '';
		recognition = new SpeechRecognition(); recognition.lang = cfg.language || document.documentElement.lang || 'en'; recognition.continuous = true; recognition.interimResults = true;
		recognition.onresult = function ( event ) { var interim = ''; for ( var i = event.resultIndex; i < event.results.length; i++ ) { var words = event.results[ i ][ 0 ].transcript; if ( event.results[ i ].isFinal ) { voiceFinalText += words + ' '; } else { interim += words; } } var input = $( 'aisaautopilot-efb-input' ); input.value = voiceInputPrefix + ( voiceFinalText + interim ).trim(); autoGrow(); };
		recognition.onerror = function () {}; try { recognition.start(); } catch ( e ) { recognition = null; }
	}
	function stopVoice() { stopLiveRecognition(); if ( recorder && recorder.state !== 'inactive' ) { recorder.stop(); } }
	function startVoice() {
		if ( busy ) { return; }
		if ( recorder && recorder.state === 'recording' ) { stopVoice(); return; }
		if ( ! navigator.mediaDevices || ! navigator.mediaDevices.getUserMedia || ! window.MediaRecorder ) { addAi( t( 'voiceUnsupported', 'Voice recording is not supported by this browser. Please type your message.' ) ); return; }
		navigator.mediaDevices.getUserMedia( { audio: true } ).then( function ( stream ) {
			voiceInputPrefix = $( 'aisaautopilot-efb-input' ).value.trim(); if ( voiceInputPrefix ) { voiceInputPrefix += ' '; }
			recorderStream = stream; recorderChunks = []; recorderStartedAt = Date.now(); recorder = new MediaRecorder( stream );
			recorder.ondataavailable = function ( event ) { if ( event.data && event.data.size ) { recorderChunks.push( event.data ); } };
			recorder.onstop = function () {
				stopLiveRecognition(); recognition = null;
				if ( recorderTimer ) { clearTimeout( recorderTimer ); recorderTimer = null; }
				if ( recorderStream ) { recorderStream.getTracks().forEach( function ( track ) { track.stop(); } ); recorderStream = null; }
				setVoiceButton( false );
				var duration = Date.now() - recorderStartedAt;
				var blob = new Blob( recorderChunks, { type: recorder.mimeType || 'audio/webm' } );
				if ( ! blob.size ) { voiceStatus( t( 'noAudio', 'No audio was captured.' ) ); return; }
				if ( blob.size > 5000000 ) { addAi( t( 'audioLarge', 'That recording is too large. Please record a shorter message.' ) ); return; }
				voiceStatus( t( 'transcribing', 'Transcribing…' ) );
				uploadAudio( blob, duration ).then( function ( result ) {
					if ( ! result.ok ) { addAi( result.message || t( 'transcriptionFail', 'Voice transcription failed. Please try again.' ) ); return; }
					var input = $( 'aisaautopilot-efb-input' ); input.value = voiceInputPrefix + ( result.transcript || voiceFinalText ).trim(); autoGrow(); input.focus(); voiceStatus( t( 'transcriptReady', 'Transcript ready. Review it, then send.' ) );
				} ).catch( function () { addAi( t( 'transcriptionFail', 'Voice transcription failed. Please try again.' ) ); } );
			};
			recorder.start(); startLiveRecognition(); setVoiceButton( true ); voiceStatus( t( 'recording', 'Recording — words appear in the message box. Stops after 45 seconds.' ) ); recorderTimer = setTimeout( stopVoice, 45000 );
		} ).catch( function () { var message = t( 'micDenied', 'Microphone permission was denied. You can type your message instead.' ); voiceStatus( message ); addAi( message ); } );
	}

	/* ---------- greeting and the not-connected state ---------- */
	function setupCard() {
		return card( Dock.cardHead( 'warn', 'plug', t( 'setupTitle', 'Connect an AI service to start' ) ), [
			el( 'div', { class: 'aisa-ai-card__body' }, [ el( 'p', { text: t( 'setupWhy', 'The assistant needs an AI service such as OpenAI, Claude or Gemini. You use your own API key; it is stored encrypted on this site and used only for your requests.' ) } ) ] ),
			el( 'ol', { class: 'aisa-ai-setup' }, [
				el( 'li', {}, [ el( 'span', { class: 'aisa-ai-setup__n', text: '1' } ), el( 'span', { text: t( 'setupStep1', 'Pick a service' ) } ) ] ),
				el( 'li', {}, [ el( 'span', { class: 'aisa-ai-setup__n', text: '2' } ), el( 'span', { text: t( 'setupStep2', 'Paste its API key' ) } ) ] ),
				el( 'li', {}, [ el( 'span', { class: 'aisa-ai-setup__n', text: '3' } ), el( 'span', { text: t( 'setupStep3', 'Test the connection' ) } ), el( 'small', { text: t( 'setupTime', 'about 2 minutes' ) } ) ] )
			] ),
			el( 'div', { class: 'aisa-ai-card__foot' }, [ setupLink( t( 'setupCta', 'Set up AI' ) ) ] ),
			hint( 'shield', t( 'setupHint', 'Setup opens in a new tab, so nothing on this form is lost. Come back here and I will be ready.' ) )
		], { 'data-sp-efb-state': 'setup' } );
	}
	function greet() {
		if ( greeted ) { return; }
		greeted = true;
		if ( ! configured ) {
			var offline = addAi( el( 'div', { class: 'aisa-ai-text', 'data-sp-efb-intro': 'offline', text: t( 'setupIntro', 'I can change this form for you: add fields, show or hide them with logic, review it, or answer Easy Form Builder questions. First I need an AI service.' ) } ) );
			offline.appendChild( setupCard() );
			return;
		}
		var intro = selectedField
			? t( 'fieldIntro', 'You selected “%s”. Tell me what to change — make it required, rename it, or add help text.', selectedField.label )
			: ( hasLiveForm() ? t( 'formIntro', 'Hi! I can change this form, design a new one, or explain how to do something in Easy Form Builder.' ) : t( 'newFormIntro', 'Hi! Tell me what form you need and I will design a complete one, or ask me an Easy Form Builder question.' ) );
		var body = addAi( el( 'div', { class: 'aisa-ai-text', dir: 'auto', 'data-sp-efb-intro': selectedField ? 'field' : ( hasLiveForm() ? 'form' : 'new-form' ), text: intro } ) );
		lastIntroFieldId = selectedField ? selectedField.id : '';
		var starters = el( 'div', { class: 'aisa-ai-starters', 'data-sp-efb-chips': 'starter' } );
		if ( hasLiveForm() ) {
			starters.appendChild( starter( 'plus', 'add', t( 'starterFields', 'Add or change fields' ), t( 'quoted', '“%s”', t( 'starterFieldsExample', 'Add a phone field after Email' ) ), t( 'starterFieldsExample', 'Add a phone field after Email' ) ) );
			// Only offered where it can work: EFB writes rules only with its Conditional Logic add-on.
			if ( logicAddonActive() ) { starters.appendChild( starter( 'logic', 'logic', t( 'starterLogic', 'Show or hide fields' ), t( 'quoted', '“%s”', t( 'starterLogicExample', 'Show Company only for business customers' ) ), t( 'starterLogicExample', 'Show Company only for business customers' ) ) ); }
			starters.appendChild( starter( 'wand', 'edit', t( 'starterReview', 'Review this form' ), t( 'starterReviewHint', 'Missing consent, spam protection, too many questions' ), t( 'starterReview', 'Review this form' ) ) );
			starters.appendChild( starter( 'help', 'edit', t( 'starterHelp', 'How do I…?' ), t( 'starterHelpHint', 'Ask anything about Easy Form Builder' ), t( 'starterHelpPrefill', 'How do I ' ) ) );
		} else {
			[ [ 'mail', t( 'buildContact', 'Build a contact form' ) ], [ 'calendar', t( 'buildBooking', 'A booking form for a dental clinic' ) ], [ 'doc', t( 'buildJob', 'Job application form' ) ], [ 'help', t( 'notificationHelp', 'How do I turn on email notifications?' ) ] ].forEach( function ( item ) {
				starters.appendChild( starter( item[ 0 ], 'help' === item[ 0 ] ? 'edit' : 'add', item[ 1 ], '', item[ 1 ] ) );
			} );
		}
		body.appendChild( starters );
	}
	function setOffline( off ) {
		configured = ! off;
		var dock = $( 'aisaautopilot-efb-chat' );
		if ( dock ) { dock.classList.toggle( 'is-offline', off ); }
		var input = $( 'aisaautopilot-efb-input' );
		if ( input ) { input.disabled = off; }
		var box = $( 'aisaautopilot-efb-inputbox' );
		if ( box ) { box.classList.toggle( 'is-off', off ); }
		var voice = $( 'aisaautopilot-efb-voice' );
		if ( voice ) { voice.disabled = off; }
		var dot = document.querySelector( '#aisaautopilot-efb-launch [data-aisa-offline]' );
		if ( dot ) { dot.hidden = ! off; }
		setBusy( busy );
		setComposerMode( composerMode );
		updateContext();
		updatePop();
	}
	/* Back from setting up AI in another tab: check again, no reload needed. */
	function recheck() {
		if ( configured || checking || ! cfg.restUrl ) { return; }
		checking = true;
		fetch( cfg.restUrl + 'ai/status', { credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce } } ).then( function ( r ) { return r.json(); } ).then( function ( data ) {
			if ( ! data || ! data.configured ) { return; }
			setOffline( false );
			if ( greeted ) {
				var body = addAi( card( Dock.cardHead( 'ok', 'check', t( 'connectedTo', 'Connected to %1$s · %2$s', [ data.provider || '', data.model || '' ] ) ), [ el( 'div', { class: 'aisa-ai-card__body' }, [ el( 'p', { text: t( 'connectedText', 'You are all set. Tell me what to change on this form.' ) } ) ] ) ], { 'data-sp-efb-state': 'connected' } ) );
				body.setAttribute( 'data-sp-efb-intro', 'connected' );
				setSuggestions( hasLiveForm() ? [ t( 'starterFieldsExample', 'Add a phone field after Email' ), t( 'nextReview', 'Review this form' ) ] : [ t( 'buildContact', 'Build a contact form' ) ] );
			}
		} ).catch( function () {} ).then( function () { checking = false; } );
	}

	/* ---------- the launcher and its note ---------- */
	var NUDGE_KEY = 'aisaautopilot_efb_nudged';
	function storage( key, value ) {
		try {
			if ( undefined === value ) { return window.localStorage.getItem( key ); }
			window.localStorage.setItem( key, value );
		} catch ( e ) {}
		return null;
	}
	function popKind() {
		if ( ! configured ) { return 'offline'; }
		if ( unsaved > 0 ) { return 'unsaved'; }
		return 'tip';
	}
	function fillPop( kind ) {
		var pop = $( 'aisaautopilot-efb-pop' );
		if ( ! pop ) { return null; }
		var badge = pop.querySelector( '[data-aisa-pop-badge]' );
		var title = pop.querySelector( '[data-aisa-pop-title]' );
		var text = pop.querySelector( '[data-aisa-pop-text]' );
		var row = pop.querySelector( '[data-aisa-pop-row]' );
		row.textContent = '';
		badge.hidden = 'nudge' !== kind;
		badge.textContent = t( 'nudgeBadge', 'NEW' );
		pop.classList.toggle( 'is-sticky', 'nudge' === kind );
		if ( 'offline' === kind ) {
			title.textContent = t( 'offlineTitle', 'AI is not connected yet' );
			text.textContent = t( 'offlineText', 'Connect a service once (about 2 minutes) and the assistant can change this form for you.' );
		} else if ( 'unsaved' === kind ) {
			title.textContent = 1 === unsaved ? t( 'unsavedOne', '1 AI change is not saved yet' ) : t( 'unsavedMany', '%s AI changes are not saved yet', unsaved );
			text.textContent = t( 'unsavedText', 'Click Save at the top of Easy Form Builder to keep them, or open the assistant to undo.' );
		} else if ( 'nudge' === kind ) {
			title.textContent = t( 'nudgeTitle', 'Describe a change, I do the clicking' );
			text.textContent = t( 'nudgeText', '“Add a phone field after Email and make it required.”' );
			var tryIt = el( 'button', { type: 'button', class: 'is-primary', text: t( 'nudgeTry', 'Try it' ) } );
			var later = el( 'button', { type: 'button', text: t( 'nudgeLater', 'Not now' ) } );
			tryIt.addEventListener( 'click', function () { dismissNudge(); open( true ); prefill( t( 'starterFieldsExample', 'Add a phone field after Email' ) ); } );
			later.addEventListener( 'click', function () { dismissNudge(); $( 'aisaautopilot-efb-launch' ).focus(); } );
			row.appendChild( tryIt );
			row.appendChild( el( 'span', { class: 'aisa-sp' } ) );
			row.appendChild( later );
		} else {
			title.textContent = hasLiveForm() ? t( 'tipTitle', 'Ask AI to change this form' ) : t( 'tipNewTitle', 'Ask AI to build a form' );
			text.textContent = hasLiveForm() ? t( 'tipText', 'Add fields, show or hide them with logic, or get a review. Nothing is saved until you click Save.' ) : t( 'tipNewText', 'Describe the form you need. I draft it for you to check, and nothing is created until you click Create.' );
			row.appendChild( el( 'kbd', { text: shortcutKey() } ) );
			row.appendChild( el( 'kbd', { text: '/' } ) );
			row.appendChild( el( 'span', { text: t( 'tipShortcut', 'opens it from anywhere' ) } ) );
		}
		return pop;
	}
	function showPop( kind ) { var pop = fillPop( kind ); if ( pop && ! isOpen() ) { pop.classList.add( 'is-shown' ); } }
	function hidePop() { var pop = $( 'aisaautopilot-efb-pop' ); if ( pop && ! pop.classList.contains( 'is-sticky' ) ) { pop.classList.remove( 'is-shown' ); } }
	function updatePop() { if ( ! nudging ) { fillPop( popKind() ); } }
	function dismissNudge() {
		nudging = false;
		storage( NUDGE_KEY, '1' );
		var pop = $( 'aisaautopilot-efb-pop' );
		if ( pop ) { pop.classList.remove( 'is-sticky', 'is-shown' ); }
		updatePop();
	}
	function shortcutKey() { return /Mac|iPhone|iPad/.test( navigator.platform || navigator.userAgent || '' ) ? '⌘' : 'Ctrl'; }
	function setUnsaved( count ) {
		unsaved = count;
		var badge = document.querySelector( '#aisaautopilot-efb-launch [data-aisa-unsaved]' );
		if ( badge ) { badge.hidden = unsaved < 1; badge.textContent = String( unsaved ); }
		updatePop();
	}

	/* ---------- window controls ---------- */
	function isOpen() { var c = $( 'aisaautopilot-efb-chat' ); return !! c && ! c.classList.contains( 'is-closed' ); }
	function open( focusInput ) {
		var c = $( 'aisaautopilot-efb-chat' );
		if ( ! c ) { return; }
		if ( nudging ) { dismissNudge(); }
		hideFieldBar();
		c.classList.remove( 'is-closed' );
		c.setAttribute( 'aria-hidden', 'false' );
		$( 'aisaautopilot-efb-launch' ).setAttribute( 'aria-expanded', 'true' );
		document.body.classList.add( 'aisaautopilot-efb-docked' );
		var pop = $( 'aisaautopilot-efb-pop' ); if ( pop ) { pop.classList.remove( 'is-shown' ); }
		// Easy Form Builder fills valj_efb after this script starts, so the composer's words are set on open.
		setComposerMode( composerMode );
		updateSelection(); greet();
		if ( false !== focusInput ) { setTimeout( function () { var input = $( 'aisaautopilot-efb-input' ); if ( input && ! input.disabled ) { input.focus(); } else { $( 'aisaautopilot-efb-min' ).focus(); } }, 60 ); }
	}
	function close() {
		var c = $( 'aisaautopilot-efb-chat' );
		if ( ! c || ! isOpen() ) { return; }
		if ( recorder && recorder.state === 'recording' ) { stopVoice(); }
		c.classList.add( 'is-closed' );
		c.setAttribute( 'aria-hidden', 'true' );
		document.body.classList.remove( 'aisaautopilot-efb-docked' );
		var launch = $( 'aisaautopilot-efb-launch' );
		launch.setAttribute( 'aria-expanded', 'false' );
		launch.focus();
	}
	function toggle() { if ( isOpen() ) { close(); } else { open(); } }
	// New chat also drops the voice status line: a microphone error from the previous
	// conversation is stale once the transcript is gone.
	function reset() { history = []; greeted = false; messages().textContent = ''; setSuggestions( [] ); voiceStatus( '' ); setComposerMode( '' ); greet(); }

	function autoGrow() { Dock.autoGrow( $( 'aisaautopilot-efb-input' ), 160 ); }

	/* EFB saves with its own admin-ajax call; a successful save keeps every AI change. */
	function watchSaves() {
		// AI changes live only in the editor until Easy Form Builder's Save: ask before a tab close or
		// a link throws them away. The browser shows its own wording; the badge already names the count.
		window.addEventListener( 'beforeunload', function ( event ) {
			if ( unsaved > 0 && ! leaving ) { event.preventDefault(); event.returnValue = ''; }
		} );
		if ( ! window.jQuery ) { return; }
		window.jQuery( document ).on( 'ajaxSuccess', function ( event, xhr, settings ) {
			if ( ! /(^|&)action=(add|update)_form_Emsfb(&|$)/.test( String( settings && settings.data || '' ) ) ) { return; }
			var res = xhr && xhr.responseJSON ? xhr.responseJSON : null;
			if ( ! res ) { try { res = JSON.parse( xhr.responseText ); } catch ( e ) { res = null; } }
			if ( res && res.data && res.data.success ) { setUnsaved( 0 ); }
		} );
	}

	function init() {
		Dock.hydrate( document );
		var kbd = document.querySelector( '#aisaautopilot-efb-launch [data-aisa-kbd]' ); if ( kbd ) { kbd.textContent = shortcutKey() + ' /'; }
		var launch = $( 'aisaautopilot-efb-launch' );
		if ( launch ) {
			launch.addEventListener( 'click', toggle );
			[ 'mouseenter', 'focus' ].forEach( function ( type ) { launch.addEventListener( type, function () { if ( ! nudging ) { showPop( popKind() ); } } ); } );
			[ 'mouseleave', 'blur' ].forEach( function ( type ) { launch.addEventListener( type, hidePop ); } );
		}
		var min = $( 'aisaautopilot-efb-min' ); if ( min ) { min.addEventListener( 'click', close ); }
		var rst = $( 'aisaautopilot-efb-reset' ); if ( rst ) { rst.addEventListener( 'click', reset ); }
		var form = $( 'aisaautopilot-efb-form' ); if ( form ) { form.addEventListener( 'submit', function ( e ) { e.preventDefault(); submit( $( 'aisaautopilot-efb-input' ).value ); } ); }
		var voice = $( 'aisaautopilot-efb-voice' ); if ( voice ) { voice.addEventListener( 'click', startVoice ); }
		var clear = $( 'aisaautopilot-efb-selection-clear' ); if ( clear ) { clear.addEventListener( 'click', clearSelection ); }
		var input = $( 'aisaautopilot-efb-input' );
		if ( input ) {
			input.addEventListener( 'input', autoGrow );
			input.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Enter' && ! e.shiftKey && ! e.isComposing ) { e.preventDefault(); submit( input.value ); } } );
		}
		document.addEventListener( 'keydown', function ( e ) {
			if ( ( e.ctrlKey || e.metaKey ) && ! e.altKey && '/' === e.key ) { e.preventDefault(); toggle(); return; }
			if ( 'Escape' === e.key ) {
				if ( nudging ) { dismissNudge(); }
				hideFieldBar();
				close();
			}
		} );
		document.addEventListener( 'click', function ( e ) { selectFieldFromTarget( e.target ); } );
		window.addEventListener( 'focus', recheck );
		document.addEventListener( 'visibilitychange', function () { if ( 'visible' === document.visibilityState ) { recheck(); } } );
		watchSaves();
		setOffline( ! configured );
		updateSelection();
		// The short introduction appears once, on the first visit, and only when AI is connected.
		if ( configured && ! storage( NUDGE_KEY ) && launch ) { setTimeout( function () { offerNudge( 0 ); }, 1200 ); }
	}
	/* Never on top of one of Easy Form Builder's own dialogs: wait for it to close, or skip this visit. */
	function efbDialogOpen() {
		return Array.prototype.some.call( document.querySelectorAll( '[role="dialog"], .modal.show, .efb-dlg' ), function ( node ) {
			return ! node.closest( '#aisaautopilot-efb-chat' ) && node.getClientRects().length > 0 && 'hidden' !== getComputedStyle( node ).visibility;
		} );
	}
	function offerNudge( tries ) {
		if ( isOpen() || storage( NUDGE_KEY ) ) { return; }
		if ( efbDialogOpen() ) { if ( tries < 30 ) { setTimeout( function () { offerNudge( tries + 1 ); }, 2000 ); } return; }
		nudging = true;
		var pop = fillPop( 'nudge' );
		pop.classList.add( 'is-shown' );
		// Easy Form Builder may open a dialog of its own a moment later; step aside for it.
		var watch = setInterval( function () {
			if ( ! nudging ) { clearInterval( watch ); return; }
			if ( efbDialogOpen() ) {
				clearInterval( watch );
				nudging = false;
				pop.classList.remove( 'is-sticky', 'is-shown' );
				updatePop();
				setTimeout( function () { offerNudge( tries + 1 ); }, 2000 );
			}
		}, 700 );
	}
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
} )();
