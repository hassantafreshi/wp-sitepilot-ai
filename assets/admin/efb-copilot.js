/* global AISAAutoPilotEFB, valj_efb, editFormEfb */
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
	function t( key, fallback, replacement ) {
		var value = cfg.i18n && cfg.i18n[ key ] ? cfg.i18n[ key ] : fallback;
		return replacement === undefined ? value : value.replace( '%s', String( replacement ) );
	}
	function responseLanguage( text ) { if ( /[\u0600-\u06ff]/.test( text ) ) { return /[\u067e\u0686\u0698\u06af]/.test( text ) ? 'fa' : 'ar'; } if ( /[\u0400-\u04ff]/.test( text ) ) { return 'ru'; } return cfg.language || 'en'; }
	function speakReply( text ) { if ( ! cfg.voiceResponses || ! text || ! window.speechSynthesis || ! window.SpeechSynthesisUtterance ) { return; } window.speechSynthesis.cancel(); var utterance = new SpeechSynthesisUtterance( text ); utterance.lang = responseLanguage( text ); utterance.rate = 1; window.speechSynthesis.speak( utterance ); }

	function el( tag, attrs, children ) {
		var n = document.createElement( tag );
		attrs = attrs || {};
		Object.keys( attrs ).forEach( function ( k ) {
			if ( k === 'class' ) { n.className = attrs[ k ]; }
			else if ( k === 'text' ) { n.textContent = attrs[ k ]; }
			else { n.setAttribute( k, attrs[ k ] ); }
		} );
		( children || [] ).forEach( function ( c ) { if ( c ) { n.appendChild( typeof c === 'string' ? document.createTextNode( c ) : c ); } } );
		return n;
	}
	function $( id ) { return document.getElementById( id ); }
	function api( path, body ) {
		return fetch( cfg.restUrl + path, {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( body )
		} ).then( function ( r ) {
			return r.text().then( function ( text ) {
				var data = {};
				try { data = text ? JSON.parse( text ) : {}; } catch ( e ) { data = { ok: false, message: 'Invalid server response.' }; }
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
			return r.json().catch( function () { return { ok: false, message: 'Invalid server response.' }; } );
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

	/* ---------- message rendering ---------- */
	function messages() { return $( 'aisaautopilot-efb-messages' ); }
	function scrollDown() { var m = messages(); if ( m ) { m.scrollTop = m.scrollHeight; } }
	function addUser( text ) {
		messages().appendChild( el( 'div', { class: 'aisaautopilot-efb-msg aisaautopilot-efb-msg--user' }, [ el( 'div', { class: 'aisaautopilot-efb-bubble', text: text } ) ] ) );
		scrollDown();
	}
	function addAi( content, wide ) {
		var bubble = el( 'div', { class: 'aisaautopilot-efb-bubble' } );
		if ( typeof content === 'string' ) { bubble.textContent = content; } else { bubble.appendChild( content ); }
		var row = el( 'div', { class: 'aisaautopilot-efb-msg aisaautopilot-efb-msg--ai' + ( wide ? ' aisaautopilot-efb-msg--wide' : '' ) }, [ bubble ] );
		messages().appendChild( row );
		scrollDown();
		return bubble;
	}
	function addTyping() {
		var t = el( 'div', { class: 'aisaautopilot-efb-msg aisaautopilot-efb-msg--ai', id: 'aisaautopilot-efb-typing' }, [
			el( 'div', { class: 'aisaautopilot-efb-bubble' }, [ el( 'span', { class: 'aisaautopilot-efb-typing' }, [ el( 'span' ), el( 'span' ), el( 'span' ) ] ) ] )
		] );
		messages().appendChild( t );
		scrollDown();
	}
	function removeTyping() { var t = $( 'aisaautopilot-efb-typing' ); if ( t ) { t.remove(); } }
	function chips( items, onPick ) {
		var box = el( 'div', { class: 'aisaautopilot-efb-chips' } );
		items.forEach( function ( it ) {
			var label = typeof it === 'string' ? it : it.title;
			var c = el( 'button', { type: 'button', class: 'aisaautopilot-efb-chip', text: label } );
			c.addEventListener( 'click', function () { onPick( it ); } );
			box.appendChild( c );
		} );
		return box;
	}

	/* ---------- EFB live structure helpers ---------- */
	function hasLiveForm() { return typeof valj_efb !== 'undefined' && Array.isArray( valj_efb ) && valj_efb.length > 2; }
	function getFormId() { var m = /[?&]id=(\d+)/.exec( window.location.search ); return m ? Number( m[ 1 ] ) : 0; }
	function isFieldItem( it ) { return it && it.type && [ 'form', 'step', 'option', 'r_matrix' ].indexOf( it.type ) === -1; }
	function liveFields() {
		if ( ! hasLiveForm() ) { return []; }
		return valj_efb.filter( isFieldItem ).map( function ( it ) { return { id: it.id_, label: it.name || it.value || it.id_, type: it.type }; } );
	}
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
	function updateSelection() {
		var selected = selectedLiveField();
		if ( selectedField && ! selected ) { selectedField = null; }
		if ( selected ) { selectedField = { id: String( selected.id_ ), label: String( selected.name || selected.value || selected.id_ ), type: String( selected.type ) }; }
		var banner = $( 'aisaautopilot-efb-selection' );
		var label = $( 'aisaautopilot-efb-selection-label' );
		var context = $( 'aisaautopilot-efb-context' );
		if ( banner ) { banner.hidden = ! selectedField; }
		if ( label ) { label.textContent = selectedField ? t( 'workingOn', 'Working on %s', selectedField.label ) : ''; }
		if ( context ) { context.textContent = hasLiveForm() ? t( 'editingForm', 'Editing the current form' ) : t( 'designingForm', 'Designing a new form' ); }
		markSelectedField();
	}
	function selectFieldFromTarget( target ) {
		if ( ! target || ! target.closest || target.closest( '#aisaautopilot-efb-chat, #aisaautopilot-efb-launch' ) ) { return; }
		var fieldNode = target.closest( '.efbField[data-id]' );
		if ( ! fieldNode ) { return; }
		var dataId = String( fieldNode.getAttribute( 'data-id' ) || '' );
		if ( dataId.slice( -3 ) !== '-id' ) { return; }
		var field = findField( dataId.slice( 0, -3 ) );
		if ( ! field ) { return; }
		var changed = ! selectedField || selectedField.id !== String( field.id_ );
		selectedField = { id: String( field.id_ ), label: String( field.name || field.value || field.id_ ), type: String( field.type ) };
		updateSelection();
		open( false );
		if ( changed && greeted ) { addAi( t( 'fieldIntro', 'You selected “%s”. Tell me what to change — make it required, rename it, or add help text.', selectedField.label ) ); }
	}
	function clearSelection() { selectedField = null; updateSelection(); }
	function maxAmount() { var m = 0; valj_efb.forEach( function ( it ) { var a = Number( it.amount ); if ( a > m ) { m = a; } } ); return m; }
	function propertyApplies( definition, field ) {
		var types = definition && definition.applies_to;
		return ! Array.isArray( types ) || ! types.length || types.indexOf( '*' ) !== -1 || types.indexOf( field.type ) !== -1;
	}
	function enumValue( definition, value ) {
		var values = definition ? definition.values : null;
		if ( ! values ) { return value; }
		if ( Array.isArray( values ) ) { return value; }
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
	function failure( report, index, op, reason ) {
		report.failed.push( { index: index, operation: String( op.op || 'unknown' ), target: String( op.target || '' ), reason: reason } );
	}
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
	function isChoiceField( field ) { return field && [ 'select', 'radio', 'checkbox', 'multiselect' ].indexOf( field.type ) !== -1; }
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
		var fieldLabel = op.field && op.field.label ? op.field.label : 'field';
		items.forEach( function ( source ) {
			var item = Object.assign( {}, source );
			amount += 1;
			item.amount = String( amount );
			item.step = step;
			valj_efb.push( item );
		} );
		report.applied.push( 'Added "' + fieldLabel + '"' );
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
		item.name = String( op.label || item.name || 'Step ' + next );
		item.step = String( next );
		item.amount = String( maxAmount() + 1 );
		valj_efb.push( item );
		if ( valj_efb[ 0 ] ) {
			valj_efb[ 0 ].steps = String( next );
			valj_efb[ 0 ].button_state = 'multi';
		}
		report.applied.push( 'Added Step "' + item.name + '"' );
		report.changed = true;
		return String( next );
	}
	function applyOps( ops ) {
		var report = { applied: [], failed: [], changed: false };
		var stepRefs = {};
		if ( ! Array.isArray( ops ) ) { return report; }
		ops.forEach( function ( op, index ) {
			try {
				if ( ! op || ! op.op ) { failure( report, index, op || {}, 'invalid_operation' ); return; }
				if ( op.op === 'set_form_name' ) { valj_efb[ 0 ].formName = op.value; report.applied.push( 'Renamed form to "' + op.value + '"' ); report.changed = true; }
				else if ( op.op === 'set_confirmation' ) { if ( ! valj_efb[ 0 ].thank_you_message || typeof valj_efb[ 0 ].thank_you_message !== 'object' ) { valj_efb[ 0 ].thank_you_message = {}; } valj_efb[ 0 ].thank_you_message.thankYou = op.value; report.applied.push( 'Updated confirmation message' ); report.changed = true; }
				else if ( op.op === 'set_field' ) {
					var f = findField( op.target ); if ( ! f ) { failure( report, index, op, 'field_not_found' ); return; }
					if ( op.prop === 'label' ) { f.name = op.value; report.applied.push( 'Renamed "' + op.target + '" to "' + op.value + '"' ); }
					else if ( op.prop === 'required' ) { f.required = op.value ? '1' : '0'; report.applied.push( 'Made "' + op.target + '" ' + ( op.value ? 'required' : 'optional' ) ); }
					else if ( op.prop === 'placeholder' ) { f.placeholder = op.value; report.applied.push( 'Set placeholder of "' + op.target + '"' ); }
					else if ( op.prop === 'width' ) { f.size = WIDTH[ op.value ] || '100'; report.applied.push( 'Set width of "' + op.target + '" to ' + op.value ); }
					else if ( op.prop === 'help' ) { f.message = op.value; report.applied.push( 'Set help text of "' + op.target + '"' ); }
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
					report.applied.push( 'Updated ' + propertyLabel( op.property ) + ' for "' + op.target + '"' ); report.changed = true;
				}
				else if ( op.op === 'set_form_property' ) {
					var fd = FORM_PROPERTIES[ op.property ];
					if ( ! fd || ! fd.storage_path || ! valj_efb[ 0 ] ) { failure( report, index, op, 'form_property_not_available' ); return; }
					if ( fd.value_type === 'efb_color' ? ! applyEfbColor( valj_efb[ 0 ], fd, op.value ) : ! fd.storage_path ) { failure( report, index, op, 'invalid_colour_or_storage' ); return; }
					if ( fd.value_type !== 'efb_color' ) { setPath( valj_efb[ 0 ], fd.storage_path, catalogValue( fd, op.value, true ) ); }
					report.applied.push( 'Updated form ' + propertyLabel( op.property ) ); report.changed = true;
				}
				else if ( op.op === 'add_step' ) { var newStep = addStepItem( op, report, index ); if ( newStep && op.step_ref ) { stepRefs[ op.step_ref ] = newStep; } }
				else if ( op.op === 'add_field' ) { addFieldItems( op, report, index, op.step_ref ? stepRefs[ op.step_ref ] : '' ); }
				else if ( op.op === 'remove_field' ) { var rf = findField( op.target ); if ( ! rf ) { failure( report, index, op, 'field_not_found' ); return; } valj_efb = valj_efb.filter( function ( it ) { return it !== rf && it.parent !== rf.id_; } ); window.valj_efb = valj_efb; report.applied.push( 'Removed "' + op.target + '"' ); report.changed = true; }
				else if ( op.op === 'add_option' ) {
					var af = findField( op.target ); if ( ! af ) { failure( report, index, op, 'field_not_found' ); return; }
					if ( ! isChoiceField( af ) ) { failure( report, index, op, 'target_is_not_a_choice_field' ); return; }
					var oid = ( af.id_ + '_o' + ( maxAmount() + 1 ) ).replace( /[^a-z0-9_]/gi, '' );
					var opt = { id_: oid, dataId: oid + '-id', parent: af.id_, type: 'option', value: op.value, id_op: oid, step: af.step || '1', amount: String( maxAmount() + 1 ) };
					var lastIdx = valj_efb.length - 1;
					for ( var i = valj_efb.length - 1; i >= 0; i-- ) { if ( valj_efb[ i ].parent === af.id_ || valj_efb[ i ] === af ) { lastIdx = i; break; } }
					valj_efb.splice( lastIdx + 1, 0, opt ); report.applied.push( 'Added option "' + op.value + '" to "' + op.target + '"' ); report.changed = true;
				}
				else if ( op.op === 'remove_option' ) { var of = findField( op.target ); if ( ! of ) { failure( report, index, op, 'field_not_found' ); return; } var hadOption = valj_efb.some( function ( it ) { return it.type === 'option' && it.parent === of.id_ && String( it.value ).toLowerCase() === String( op.value ).toLowerCase(); } ); if ( ! hadOption ) { failure( report, index, op, 'option_not_found' ); return; } valj_efb = valj_efb.filter( function ( it ) { return ! ( it.type === 'option' && it.parent === of.id_ && String( it.value ).toLowerCase() === String( op.value ).toLowerCase() ); } ); window.valj_efb = valj_efb; report.applied.push( 'Removed option "' + op.value + '" from "' + op.target + '"' ); report.changed = true; }
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

	/* ---------- response renderers ---------- */
	function renderMessage( data ) {
		var bubble = el( 'div', {} );
		bubble.appendChild( el( 'div', { text: data.reply || '' } ) );
		if ( data.errors && data.errors.length ) { bubble.appendChild( el( 'div', { class: 'aisaautopilot-efb-inline-note', text: data.errors.join( ' ' ) } ) ); }
		if ( data.upgrade_required ) {
			var upgrade = el( 'button', { type: 'button', class: 'aisaautopilot-efb-btn aisaautopilot-efb-btn--pro', text: t( 'upgrade', 'Upgrade to EFB Pro' ) } );
			upgrade.addEventListener( 'click', function () {
				// Reuse EFB's own dialog, translated message, and official purchase flow.
				if ( typeof window.pro_show_efb === 'function' ) { window.pro_show_efb( data.upgrade_reason === 'step_limit' ? 2 : 1 ); return; }
				window.open( data.upgrade_url || ( cfg.license && cfg.license.upgrade_url ) || 'https://whitestudio.team/#price', '_blank', 'noopener' );
			} );
			var pricing = el( 'a', { class: 'aisaautopilot-efb-btn aisaautopilot-efb-btn--ghost', href: data.upgrade_url || ( cfg.license && cfg.license.upgrade_url ) || 'https://whitestudio.team/#price', target: '_blank', rel: 'noopener noreferrer', text: t( 'pricing', 'View pricing' ) } );
			bubble.appendChild( el( 'div', { class: 'aisaautopilot-efb-actions' }, [ upgrade, pricing ] ) );
		}
		addAi( bubble );
		history.push( { role: 'assistant', text: data.reply || '' } );
	}
	function renderPreview( data ) {
		var box = el( 'div', {} );
		if ( data.reply ) { box.appendChild( el( 'div', { text: data.reply } ) ); }
		var p = data.preview;
		var card = el( 'div', { class: 'aisaautopilot-efb-card' } );
		card.appendChild( el( 'h3', { text: p.title } ) );
		card.appendChild( el( 'div', { class: 'aisaautopilot-efb-meta' }, [
			el( 'span', { class: 'aisaautopilot-efb-pill', text: p.connector } ),
			el( 'span', { class: 'aisaautopilot-efb-pill aisaautopilot-efb-pill--muted', text: t( 'fieldsCount', '%s fields', p.field_count ) } )
		] ) );
		var table = el( 'table', { class: 'aisaautopilot-efb-fields' } );
		table.appendChild( el( 'thead', {}, [ el( 'tr', {}, [ el( 'th', { text: t( 'label', 'Label' ) } ), el( 'th', { text: t( 'type', 'Type' ) } ), el( 'th', { text: t( 'requiredShort', 'Req.' ) } ), el( 'th', { text: t( 'widthShort', 'W' ) } ) ] ) ] ) );
		var tb = el( 'tbody' );
		p.fields.forEach( function ( f ) {
			tb.appendChild( el( 'tr', {}, [ el( 'td', { text: f.label } ), el( 'td', {}, [ el( 'code', { text: f.type } ) ] ), el( 'td', { text: f.required ? '✓' : '' } ), el( 'td', { text: ( f.width || 'full' ).charAt( 0 ) } ) ] ) );
		} );
		table.appendChild( tb );
		card.appendChild( table );
		var approve = el( 'button', { type: 'button', class: 'aisaautopilot-efb-btn aisaautopilot-efb-btn--primary', text: t( 'approveCreate', 'Approve & create' ) } );
		var discard = el( 'button', { type: 'button', class: 'aisaautopilot-efb-btn aisaautopilot-efb-btn--ghost', text: t( 'discard', 'Discard' ) } );
		var note = el( 'span', { class: 'aisaautopilot-efb-inline-note' } );
		approve.addEventListener( 'click', function () {
			approve.disabled = true; note.textContent = t( 'creating', 'Creating…' );
			api( 'efb/create', { action_id: data.action_id } ).then( function ( res ) {
				if ( ! res.data.ok ) { note.textContent = res.data.message || 'Failed.'; approve.disabled = false; return; }
				note.textContent = t( 'createdOpening', 'Created! Opening the editor…' );
				if ( res.data.result && res.data.result.edit_url ) { setTimeout( function () { window.location.href = res.data.result.edit_url; }, 600 ); }
			} ).catch( function () { note.textContent = t( 'networkError', 'Network error. Please try again.' ); approve.disabled = false; } );
		} );
		discard.addEventListener( 'click', function () { card.remove(); } );
		card.appendChild( el( 'div', { class: 'aisaautopilot-efb-actions' }, [ approve, discard, note ] ) );
		// Fields the licence cannot render were left out of the plan; name them
		// and offer the upgrade instead of quietly shipping a lesser form.
		if ( data.errors && data.errors.length ) {
			card.appendChild( el( 'div', { class: 'aisaautopilot-efb-inline-note', text: data.errors.join( ' ' ) } ) );
		}
		if ( data.upgrade_required ) {
			var proBtn = el( 'button', { type: 'button', class: 'aisaautopilot-efb-btn aisaautopilot-efb-btn--pro', text: t( 'upgrade', 'Upgrade to EFB Pro' ) } );
			proBtn.addEventListener( 'click', function () {
				if ( typeof window.pro_show_efb === 'function' ) { window.pro_show_efb( 1 ); return; }
				window.open( data.upgrade_url || ( cfg.license && cfg.license.upgrade_url ) || 'https://whitestudio.team/#price', '_blank', 'noopener' );
			} );
			card.appendChild( el( 'div', { class: 'aisaautopilot-efb-actions' }, [ proBtn ] ) );
		}
		box.appendChild( card );
		addAi( box, true );
		history.push( { role: 'assistant', text: 'Proposed a form: ' + p.title } );
	}
	function renderOps( data, traceId ) {
		if ( ! hasLiveForm() ) {
			clientLog( traceId, 'apply_unavailable', 'Browser did not expose a live EFB form.', { operation_count: ( data.operations || [] ).length, failed: [ { reason: 'live_form_not_available' } ] } );
			addAi( 'Open a form in the editor first (create one here, or open Easy Form Builder → Forms and edit a form), then ask me to change it.' );
			history.push( { role: 'assistant', text: 'Asked user to open a form first.' } );
			return;
		}
		var report = applyOps( data.operations || [] );
		var box = el( 'div', {} );
		box.appendChild( el( 'div', { text: data.reply || 'Done.' } ) );
		// Server-side notes (e.g. a batch truncated at the op limit) must show here
		// too, otherwise a partly-applied change still reads as a clean success.
		if ( data.errors && data.errors.length ) { box.appendChild( el( 'div', { class: 'aisaautopilot-efb-inline-note', text: data.errors.join( ' ' ) } ) ); }
		if ( ! report.applied.length ) {
			var reason = report.failed.length ? report.failed[ 0 ].reason.replace( /_/g, ' ' ) : 'no operation was applied';
			box.appendChild( el( 'div', { class: 'aisaautopilot-efb-inline-note', text: 'No change was applied: ' + reason + '.' } ) );
			clientLog( traceId, 'apply_failed', 'No requested operation was applied.', { operation_count: ( data.operations || [] ).length, failed: report.failed } );
			addAi( box ); history.push( { role: 'assistant', text: 'No operation applied: ' + reason } ); return;
		}
		var refreshed = refreshDropzone();
		updateSelection();
		if ( ! refreshed.ok ) { report.failed.push( { operation: 'render', reason: refreshed.reason || 'renderer_refresh_failed' } ); }
		clientLog( traceId, refreshed.ok ? 'apply_completed' : 'apply_render_failed', refreshed.ok ? 'Operations applied in the live EFB canvas.' : 'Operations changed the structure but the EFB renderer failed.', { operation_count: ( data.operations || [] ).length, applied: report.applied, failed: report.failed, renderer: refreshed } );
		var ul = el( 'div', {} );
		report.applied.forEach( function ( a ) { ul.appendChild( el( 'div', { class: 'aisaautopilot-efb-inline-note', text: '✓ ' + a } ) ); } );
		report.failed.forEach( function ( f ) { ul.appendChild( el( 'div', { class: 'aisaautopilot-efb-inline-note', text: 'Not applied: ' + f.reason.replace( /_/g, ' ' ) } ) ); } );
		box.appendChild( ul );
		if ( ! refreshed.ok ) { box.appendChild( el( 'div', { class: 'aisaautopilot-efb-inline-note', text: 'The EFB canvas could not refresh. Reload the editor and check AI Site Assistant AutoPilot → Logs with trace ' + traceId + '.' } ) ); }
		else { box.appendChild( el( 'div', { class: 'aisaautopilot-efb-inline-note', text: 'Applied to the canvas. Review it, then click Easy Form Builder’s green Save button to persist the changes.' } ) ); }
		addAi( box );
		history.push( { role: 'assistant', text: 'Applied: ' + report.applied.join( '; ' ) } );
		if ( ! refreshed.ok ) { setTimeout( function () { window.location.reload(); }, 800 ); }
	}
	function renderHelp( data ) {
		var box = el( 'div', {} );
		if ( data.reply ) { box.appendChild( el( 'div', { text: data.reply } ) ); }
		if ( data.suggestions && data.suggestions.length ) {
			box.appendChild( chips( data.suggestions, function ( s ) { submit( s.title ); } ) );
			addAi( box ); history.push( { role: 'assistant', text: data.reply || 'Suggested topics.' } ); return;
		}
		var card = el( 'div', { class: 'aisaautopilot-efb-card' } );
		if ( data.title ) { card.appendChild( el( 'h3', { text: data.title } ) ); }
		( data.answer || '' ).split( '\n\n' ).forEach( function ( para ) { if ( para.trim() ) { card.appendChild( el( 'p', { text: para.trim() } ) ); } } );
		box.appendChild( card );
		addAi( box, true );
		history.push( { role: 'assistant', text: ( data.title || 'How-to' ) + ' answered.' } );
	}
	function renderResponse( data, traceId ) {
		if ( data.reply ) { speakReply( data.reply ); }
		if ( data.type === 'preview' ) { return renderPreview( data ); }
		if ( data.type === 'ops' ) { return renderOps( data, traceId ); }
		if ( data.type === 'help' ) { return renderHelp( data ); }
		return renderMessage( data );
	}

	/* ---------- send ---------- */
	function submit( text ) {
		text = ( text || '' ).trim();
		if ( ! text || busy ) { return; }
		addUser( text );
		var prior = history.slice();
		history.push( { role: 'user', text: text } );
		var input = $( 'aisaautopilot-efb-input' );
		input.value = ''; autoGrow();
		busy = true; $( 'aisaautopilot-efb-send' ).disabled = true;
		addTyping();
		var traceId = makeTraceId();
		var fields = liveFields();
		var steps = liveSteps();
		var selected = selectedLiveField();
		var selectedId = selected ? String( selected.id_ ) : '';
		clientLog( traceId, 'chat_submitted', 'Chat request sent from the EFB editor.', { message: text.slice( 0, 200 ), has_form: hasLiveForm(), field_count: fields.length, step_count: steps.length, selected_field: selectedId, field_ids: fields.map( function ( field ) { return field.id; } ) } );
		api( 'efb/chat', { trace_id: traceId, message: text, history: prior, has_form: hasLiveForm(), fields: fields, steps: steps, selected_field: selectedId } )
			.then( function ( res ) {
				removeTyping(); busy = false; $( 'aisaautopilot-efb-send' ).disabled = false;
				var data = res.data || { type: 'message', reply: 'Sorry, something went wrong.' };
				if ( data.ok === false && ! data.reply ) { data.type = 'message'; data.reply = data.message || 'The request could not be completed.'; }
				var context = { status: res.status, response_type: data.type || 'message', code: data.code || '', operation_count: Array.isArray( data.operations ) ? data.operations.length : 0 };
				clientLog( traceId, data.ok === false ? 'chat_response_error' : 'chat_response', data.ok === false ? ( data.message || 'Chat endpoint returned an error.' ) : 'Chat response received.', context );
				renderResponse( data, traceId );
			} )
			.catch( function ( error ) { removeTyping(); busy = false; $( 'aisaautopilot-efb-send' ).disabled = false; clientLog( traceId, 'chat_network_error', 'The browser could not reach the chat endpoint.', { failed: [ { reason: String( error && error.message ? error.message : 'network_error' ).slice( 0, 160 ) } ] } ); addAi( t( 'networkError', 'Network error. Please try again.' ) ); } );
	}

	/* Voice never sends a command by itself: the transcript is placed in the composer for review. */
	function voiceStatus( message ) { var status = $( 'aisaautopilot-efb-voice-status' ); if ( status ) { status.textContent = message; status.classList.toggle( 'is-visible', !! message ); } }
	function setVoiceButton( recording ) { [ $( 'aisaautopilot-efb-voice' ), $( 'aisaautopilot-efb-live-voice' ) ].forEach( function ( button ) { if ( button ) { button.classList.toggle( 'is-recording', recording ); button.setAttribute( 'aria-pressed', recording ? 'true' : 'false' ); } } ); }
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
					if ( ! result.ok ) { addAi( result.message || 'Voice transcription failed.' ); return; }
					var input = $( 'aisaautopilot-efb-input' ); input.value = voiceInputPrefix + ( result.transcript || voiceFinalText ).trim(); autoGrow(); input.focus(); voiceStatus( t( 'transcriptReady', 'Transcript ready. Review it, then send.' ) );
				} ).catch( function () { addAi( t( 'transcriptionFail', 'Voice transcription failed. Please try again.' ) ); } );
			};
			recorder.start(); startLiveRecognition(); setVoiceButton( true ); voiceStatus( t( 'recording', 'Recording — words appear in the message box. Stops after 45 seconds.' ) ); recorderTimer = setTimeout( stopVoice, 45000 );
		} ).catch( function () { var message = t( 'micDenied', 'Microphone permission was denied. You can type your message instead.' ); voiceStatus( message ); addAi( message ); } );
	}

	function greet() {
		if ( greeted ) { return; }
		greeted = true;
		var intro = selectedField
			? t( 'fieldIntro', 'You selected “%s”. Tell me what to change — make it required, rename it, or add help text.', selectedField.label )
			: ( hasLiveForm() ? t( 'formIntro', 'Hi! I can change this form, design a new one, or explain how to do something in Easy Form Builder.' ) : t( 'newFormIntro', 'Hi! Tell me what form you need and I will design a complete one, or ask me an Easy Form Builder question.' ) );
		var box = el( 'div', {} );
		box.appendChild( el( 'div', { text: intro } ) );
		var starters = hasLiveForm()
			? [ t( 'makeRequired', 'Make Email required' ), t( 'renameForm', 'Rename the form to Contact us' ), t( 'addOptionsHelp', 'How do I add options to a dropdown?' ) ]
			: [ t( 'buildContact', 'Build a contact form' ), t( 'buildBooking', 'A booking form for a dental clinic' ), t( 'buildJob', 'Job application form' ), t( 'notificationHelp', 'How do I turn on email notifications?' ) ];
		box.appendChild( chips( starters, function ( s ) { submit( s ); } ) );
		addAi( box );
	}

	/* ---------- window controls ---------- */
	function open( focusInput ) { var c = $( 'aisaautopilot-efb-chat' ); if ( ! c ) { return; } c.hidden = false; c.setAttribute( 'aria-hidden', 'false' ); $( 'aisaautopilot-efb-launch' ).setAttribute( 'aria-expanded', 'true' ); updateSelection(); greet(); if ( focusInput !== false ) { setTimeout( function () { $( 'aisaautopilot-efb-input' ).focus(); }, 50 ); } }
	function close() { var c = $( 'aisaautopilot-efb-chat' ); if ( ! c || c.hidden ) { return; } if ( recorder && recorder.state === 'recording' ) { stopVoice(); } c.hidden = true; c.setAttribute( 'aria-hidden', 'true' ); var launch = $( 'aisaautopilot-efb-launch' ); launch.setAttribute( 'aria-expanded', 'false' ); launch.focus(); }
	function toggle() { var c = $( 'aisaautopilot-efb-chat' ); if ( c && c.hidden ) { open(); } else { close(); } }
	function reset() { history = []; greeted = false; messages().textContent = ''; greet(); }

	function autoGrow() { var t = $( 'aisaautopilot-efb-input' ); if ( ! t ) { return; } t.style.height = 'auto'; t.style.height = Math.min( t.scrollHeight, 140 ) + 'px'; }

	function init() {
		var launch = $( 'aisaautopilot-efb-launch' ); if ( launch ) { launch.addEventListener( 'click', toggle ); }
		var min = $( 'aisaautopilot-efb-min' ); if ( min ) { min.addEventListener( 'click', close ); }
		var rst = $( 'aisaautopilot-efb-reset' ); if ( rst ) { rst.addEventListener( 'click', reset ); }
		var form = $( 'aisaautopilot-efb-form' ); if ( form ) { form.addEventListener( 'submit', function ( e ) { e.preventDefault(); submit( $( 'aisaautopilot-efb-input' ).value ); } ); }
		var voice = $( 'aisaautopilot-efb-voice' ); if ( voice ) { voice.addEventListener( 'click', startVoice ); }
		var liveVoice = $( 'aisaautopilot-efb-live-voice' ); if ( liveVoice ) { liveVoice.addEventListener( 'click', startVoice ); }
		var clear = $( 'aisaautopilot-efb-selection-clear' ); if ( clear ) { clear.addEventListener( 'click', clearSelection ); }
		var input = $( 'aisaautopilot-efb-input' );
		if ( input ) {
			input.addEventListener( 'input', autoGrow );
			input.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Enter' && ! e.shiftKey ) { e.preventDefault(); submit( input.value ); } } );
		}
		document.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' ) { close(); } } );
		document.addEventListener( 'click', function ( e ) { selectFieldFromTarget( e.target ); } );
		updateSelection();
	}
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
} )();
