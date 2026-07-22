/* global SitePilotEFB, valj_efb, editFormEfb */
( function () {
	'use strict';

	var cfg = window.SitePilotEFB || {};
	var WIDTH = { full: '100', half: '50', third: '33', quarter: '25' };
	var history = [];
	var busy = false;
	var greeted = false;

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
		} ).then( function ( r ) { return r.json().then( function ( d ) { return { status: r.status, data: d }; } ); } );
	}

	/* ---------- message rendering ---------- */
	function messages() { return $( 'sitepilot-efb-messages' ); }
	function scrollDown() { var m = messages(); if ( m ) { m.scrollTop = m.scrollHeight; } }
	function addUser( text ) {
		messages().appendChild( el( 'div', { class: 'sitepilot-efb-msg sitepilot-efb-msg--user' }, [ el( 'div', { class: 'sitepilot-efb-bubble', text: text } ) ] ) );
		scrollDown();
	}
	function addAi( content, wide ) {
		var bubble = el( 'div', { class: 'sitepilot-efb-bubble' } );
		if ( typeof content === 'string' ) { bubble.textContent = content; } else { bubble.appendChild( content ); }
		var row = el( 'div', { class: 'sitepilot-efb-msg sitepilot-efb-msg--ai' + ( wide ? ' sitepilot-efb-msg--wide' : '' ) }, [ bubble ] );
		messages().appendChild( row );
		scrollDown();
		return bubble;
	}
	function addTyping() {
		var t = el( 'div', { class: 'sitepilot-efb-msg sitepilot-efb-msg--ai', id: 'sitepilot-efb-typing' }, [
			el( 'div', { class: 'sitepilot-efb-bubble' }, [ el( 'span', { class: 'sitepilot-efb-typing' }, [ el( 'span' ), el( 'span' ), el( 'span' ) ] ) ] )
		] );
		messages().appendChild( t );
		scrollDown();
	}
	function removeTyping() { var t = $( 'sitepilot-efb-typing' ); if ( t ) { t.remove(); } }
	function chips( items, onPick ) {
		var box = el( 'div', { class: 'sitepilot-efb-chips' } );
		items.forEach( function ( it ) {
			var label = typeof it === 'string' ? it : it.title;
			var c = el( 'button', { type: 'button', class: 'sitepilot-efb-chip', text: label } );
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
	function findField( target ) {
		var t = ( target || '' ).toString().toLowerCase();
		return valj_efb.find( function ( it ) { return isFieldItem( it ) && ( String( it.id_ ).toLowerCase() === t || String( it.name || '' ).toLowerCase() === t ); } ) || null;
	}
	function maxAmount() { var m = 0; valj_efb.forEach( function ( it ) { var a = Number( it.amount ); if ( a > m ) { m = a; } } ); return m; }
	function applyOps( ops ) {
		var applied = [];
		ops.forEach( function ( op ) {
			try {
				if ( op.op === 'set_form_name' ) { valj_efb[ 0 ].formName = op.value; applied.push( 'Renamed form to "' + op.value + '"' ); }
				else if ( op.op === 'set_confirmation' ) { if ( ! valj_efb[ 0 ].thank_you_message || typeof valj_efb[ 0 ].thank_you_message !== 'object' ) { valj_efb[ 0 ].thank_you_message = {}; } valj_efb[ 0 ].thank_you_message.thankYou = op.value; applied.push( 'Updated confirmation message' ); }
				else if ( op.op === 'set_field' ) {
					var f = findField( op.target ); if ( ! f ) { return; }
					if ( op.prop === 'label' ) { f.name = op.value; applied.push( 'Renamed "' + op.target + '" to "' + op.value + '"' ); }
					else if ( op.prop === 'required' ) { f.required = op.value ? '1' : '0'; applied.push( 'Made "' + op.target + '" ' + ( op.value ? 'required' : 'optional' ) ); }
					else if ( op.prop === 'placeholder' ) { f.placeholder = op.value; applied.push( 'Set placeholder of "' + op.target + '"' ); }
					else if ( op.prop === 'width' ) { f.size = WIDTH[ op.value ] || '100'; applied.push( 'Set width of "' + op.target + '" to ' + op.value ); }
					else if ( op.prop === 'help' ) { f.message = op.value; applied.push( 'Set help text of "' + op.target + '"' ); }
				}
				else if ( op.op === 'remove_field' ) { var rf = findField( op.target ); if ( ! rf ) { return; } valj_efb = valj_efb.filter( function ( it ) { return it !== rf && it.parent !== rf.id_; } ); window.valj_efb = valj_efb; applied.push( 'Removed "' + op.target + '"' ); }
				else if ( op.op === 'add_option' ) {
					var af = findField( op.target ); if ( ! af ) { return; }
					var oid = ( af.id_ + '_o' + ( maxAmount() + 1 ) ).replace( /[^a-z0-9_]/gi, '' );
					var opt = { id_: oid, dataId: oid + '-id', parent: af.id_, type: 'option', value: op.value, id_op: oid, step: af.step || '1', amount: String( maxAmount() + 1 ) };
					var lastIdx = valj_efb.length - 1;
					for ( var i = valj_efb.length - 1; i >= 0; i-- ) { if ( valj_efb[ i ].parent === af.id_ || valj_efb[ i ] === af ) { lastIdx = i; break; } }
					valj_efb.splice( lastIdx + 1, 0, opt ); applied.push( 'Added option "' + op.value + '" to "' + op.target + '"' );
				}
				else if ( op.op === 'remove_option' ) { var of = findField( op.target ); if ( ! of ) { return; } valj_efb = valj_efb.filter( function ( it ) { return ! ( it.type === 'option' && it.parent === of.id_ && String( it.value ).toLowerCase() === String( op.value ).toLowerCase() ); } ); window.valj_efb = valj_efb; applied.push( 'Removed option "' + op.value + '" from "' + op.target + '"' ); }
			} catch ( e ) {}
		} );
		return applied;
	}
	function refreshDropzone() {
		try { sessionStorage.setItem( 'valj_efb', JSON.stringify( valj_efb ) ); if ( typeof editFormEfb === 'function' ) { editFormEfb(); return true; } } catch ( e ) {}
		return false;
	}

	/* ---------- response renderers ---------- */
	function renderMessage( data ) {
		var bubble = el( 'div', {} );
		bubble.appendChild( el( 'div', { text: data.reply || '' } ) );
		if ( data.errors && data.errors.length ) { bubble.appendChild( el( 'div', { class: 'sitepilot-efb-inline-note', text: data.errors.join( ' ' ) } ) ); }
		addAi( bubble );
		history.push( { role: 'assistant', text: data.reply || '' } );
	}
	function renderPreview( data ) {
		var box = el( 'div', {} );
		if ( data.reply ) { box.appendChild( el( 'div', { text: data.reply } ) ); }
		var p = data.preview;
		var card = el( 'div', { class: 'sitepilot-efb-card' } );
		card.appendChild( el( 'h3', { text: p.title } ) );
		card.appendChild( el( 'div', { class: 'sitepilot-efb-meta' }, [
			el( 'span', { class: 'sitepilot-efb-pill', text: p.connector } ),
			el( 'span', { class: 'sitepilot-efb-pill sitepilot-efb-pill--muted', text: p.field_count + ' fields' } )
		] ) );
		var table = el( 'table', { class: 'sitepilot-efb-fields' } );
		table.appendChild( el( 'thead', {}, [ el( 'tr', {}, [ el( 'th', { text: 'Label' } ), el( 'th', { text: 'Type' } ), el( 'th', { text: 'Req.' } ), el( 'th', { text: 'W' } ) ] ) ] ) );
		var tb = el( 'tbody' );
		p.fields.forEach( function ( f ) {
			tb.appendChild( el( 'tr', {}, [ el( 'td', { text: f.label } ), el( 'td', {}, [ el( 'code', { text: f.type } ) ] ), el( 'td', { text: f.required ? '✓' : '' } ), el( 'td', { text: ( f.width || 'full' ).charAt( 0 ) } ) ] ) );
		} );
		table.appendChild( tb );
		card.appendChild( table );
		var approve = el( 'button', { type: 'button', class: 'sitepilot-efb-btn sitepilot-efb-btn--primary', text: 'Approve & create' } );
		var discard = el( 'button', { type: 'button', class: 'sitepilot-efb-btn sitepilot-efb-btn--ghost', text: 'Discard' } );
		var note = el( 'span', { class: 'sitepilot-efb-inline-note' } );
		approve.addEventListener( 'click', function () {
			approve.disabled = true; note.textContent = 'Creating…';
			api( 'efb/create', { action_id: data.action_id } ).then( function ( res ) {
				if ( ! res.data.ok ) { note.textContent = res.data.message || 'Failed.'; approve.disabled = false; return; }
				note.textContent = 'Created! Opening the editor…';
				if ( res.data.result && res.data.result.edit_url ) { setTimeout( function () { window.location.href = res.data.result.edit_url; }, 600 ); }
			} ).catch( function () { note.textContent = 'Network error.'; approve.disabled = false; } );
		} );
		discard.addEventListener( 'click', function () { card.remove(); } );
		card.appendChild( el( 'div', { class: 'sitepilot-efb-actions' }, [ approve, discard, note ] ) );
		box.appendChild( card );
		addAi( box, true );
		history.push( { role: 'assistant', text: 'Proposed a form: ' + p.title } );
	}
	function renderOps( data ) {
		if ( ! hasLiveForm() ) {
			addAi( 'Open a form in the editor first (create one here, or open Easy Form Builder → Forms and edit a form), then ask me to change it.' );
			history.push( { role: 'assistant', text: 'Asked user to open a form first.' } );
			return;
		}
		var applied = applyOps( data.operations || [] );
		var box = el( 'div', {} );
		box.appendChild( el( 'div', { text: data.reply || 'Done.' } ) );
		if ( ! applied.length ) {
			box.appendChild( el( 'div', { class: 'sitepilot-efb-inline-note', text: 'I could not match a field on this form.' } ) );
			addAi( box ); history.push( { role: 'assistant', text: 'No matching field to change.' } ); return;
		}
		var refreshed = refreshDropzone();
		var ul = el( 'div', {} );
		applied.forEach( function ( a ) { ul.appendChild( el( 'div', { class: 'sitepilot-efb-inline-note', text: '✓ ' + a } ) ); } );
		box.appendChild( ul );
		if ( ! refreshed ) { box.appendChild( el( 'div', { class: 'sitepilot-efb-inline-note', text: 'Reloading to update the preview…' } ) ); }
		addAi( box );
		history.push( { role: 'assistant', text: 'Applied: ' + applied.join( '; ' ) } );
		var fid = getFormId();
		if ( fid ) { api( 'efb/save', { form_id: fid, structure: valj_efb } ); }
		if ( ! refreshed ) { setTimeout( function () { window.location.reload(); }, 800 ); }
	}
	function renderHelp( data ) {
		var box = el( 'div', {} );
		if ( data.reply ) { box.appendChild( el( 'div', { text: data.reply } ) ); }
		if ( data.suggestions && data.suggestions.length ) {
			box.appendChild( chips( data.suggestions, function ( s ) { submit( s.title ); } ) );
			addAi( box ); history.push( { role: 'assistant', text: data.reply || 'Suggested topics.' } ); return;
		}
		var card = el( 'div', { class: 'sitepilot-efb-card' } );
		if ( data.title ) { card.appendChild( el( 'h3', { text: data.title } ) ); }
		( data.answer || '' ).split( '\n\n' ).forEach( function ( para ) { if ( para.trim() ) { card.appendChild( el( 'p', { text: para.trim() } ) ); } } );
		box.appendChild( card );
		addAi( box, true );
		history.push( { role: 'assistant', text: ( data.title || 'How-to' ) + ' answered.' } );
	}
	function renderResponse( data ) {
		if ( data.type === 'preview' ) { return renderPreview( data ); }
		if ( data.type === 'ops' ) { return renderOps( data ); }
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
		var input = $( 'sitepilot-efb-input' );
		input.value = ''; autoGrow();
		busy = true; $( 'sitepilot-efb-send' ).disabled = true;
		addTyping();
		api( 'efb/chat', { message: text, history: prior, has_form: hasLiveForm(), fields: liveFields() } )
			.then( function ( res ) { removeTyping(); busy = false; $( 'sitepilot-efb-send' ).disabled = false; renderResponse( res.data || { type: 'message', reply: 'Sorry, something went wrong.' } ); } )
			.catch( function () { removeTyping(); busy = false; $( 'sitepilot-efb-send' ).disabled = false; addAi( 'Network error. Please try again.' ); } );
	}

	function greet() {
		if ( greeted ) { return; }
		greeted = true;
		var intro = hasLiveForm()
			? 'Hi! I can change this form for you (e.g. “make Email required”, “rename the form”), design a new one, or explain how to do anything in Easy Form Builder.'
			: 'Hi! Tell me what form you need and I’ll design a complete one — or ask me how to do anything in Easy Form Builder. I’ll ask a question if I need details.';
		var box = el( 'div', {} );
		box.appendChild( el( 'div', { text: intro } ) );
		var starters = hasLiveForm()
			? [ 'Make Email required', 'Rename the form to Contact us', 'How do I add options to a dropdown?' ]
			: [ 'Build a contact form', 'A booking form for a dental clinic', 'Job application form', 'How do I turn on email notifications?' ];
		box.appendChild( chips( starters, function ( s ) { submit( s ); } ) );
		addAi( box );
	}

	/* ---------- window controls ---------- */
	function open() { var c = $( 'sitepilot-efb-chat' ); if ( ! c ) { return; } c.hidden = false; c.setAttribute( 'aria-hidden', 'false' ); greet(); setTimeout( function () { $( 'sitepilot-efb-input' ).focus(); }, 50 ); }
	function close() { var c = $( 'sitepilot-efb-chat' ); if ( ! c ) { return; } c.hidden = true; c.setAttribute( 'aria-hidden', 'true' ); }
	function toggle() { var c = $( 'sitepilot-efb-chat' ); if ( c && c.hidden ) { open(); } else { close(); } }
	function reset() { history = []; greeted = false; messages().textContent = ''; greet(); }

	function autoGrow() { var t = $( 'sitepilot-efb-input' ); if ( ! t ) { return; } t.style.height = 'auto'; t.style.height = Math.min( t.scrollHeight, 140 ) + 'px'; }

	function init() {
		var launch = $( 'sitepilot-efb-launch' ); if ( launch ) { launch.addEventListener( 'click', toggle ); }
		var min = $( 'sitepilot-efb-min' ); if ( min ) { min.addEventListener( 'click', close ); }
		var rst = $( 'sitepilot-efb-reset' ); if ( rst ) { rst.addEventListener( 'click', reset ); }
		var form = $( 'sitepilot-efb-form' ); if ( form ) { form.addEventListener( 'submit', function ( e ) { e.preventDefault(); submit( $( 'sitepilot-efb-input' ).value ); } ); }
		var input = $( 'sitepilot-efb-input' );
		if ( input ) {
			input.addEventListener( 'input', autoGrow );
			input.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Enter' && ! e.shiftKey ) { e.preventDefault(); submit( input.value ); } } );
		}
		document.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' ) { close(); } } );
	}
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
} )();
