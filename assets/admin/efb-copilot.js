/* global SitePilotEFB, valj_efb, editFormEfb */
( function () {
	'use strict';

	var cfg = window.SitePilotEFB || {};
	var actionId = null;
	var tab = 'create';
	var WIDTH = { full: '100', half: '50', third: '33', quarter: '25' };

	function el( tag, attrs, children ) {
		var n = document.createElement( tag );
		attrs = attrs || {};
		Object.keys( attrs ).forEach( function ( k ) {
			if ( k === 'class' ) { n.className = attrs[ k ]; }
			else if ( k === 'text' ) { n.textContent = attrs[ k ]; }
			else { n.setAttribute( k, attrs[ k ] ); }
		} );
		( children || [] ).forEach( function ( c ) {
			if ( c ) { n.appendChild( typeof c === 'string' ? document.createTextNode( c ) : c ); }
		} );
		return n;
	}

	function api( path, body ) {
		return fetch( cfg.restUrl + path, {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( body )
		} ).then( function ( r ) { return r.json().then( function ( d ) { return { status: r.status, data: d }; } ); } );
	}

	function body() { return document.getElementById( 'sitepilot-efb-body' ); }

	function setStatus( id, msg, kind ) {
		var s = document.getElementById( id );
		if ( ! s ) { return; }
		s.className = 'sitepilot-efb-status' + ( kind && kind !== 'loading' ? ' sitepilot-efb-status--' + kind : '' );
		s.textContent = '';
		if ( kind === 'loading' ) { s.appendChild( el( 'span', { class: 'sitepilot-efb-spinner' } ) ); }
		s.appendChild( document.createTextNode( msg ) );
	}

	/* ---------- EFB live structure helpers ---------- */

	function hasLiveForm() {
		return typeof valj_efb !== 'undefined' && Array.isArray( valj_efb ) && valj_efb.length > 2;
	}
	function getFormId() {
		var m = /[?&]id=(\d+)/.exec( window.location.search );
		return m ? Number( m[ 1 ] ) : 0;
	}
	function isFieldItem( it ) {
		return it && it.type && [ 'form', 'step', 'option', 'r_matrix' ].indexOf( it.type ) === -1;
	}
	function liveFields() {
		if ( ! hasLiveForm() ) { return []; }
		return valj_efb.filter( isFieldItem ).map( function ( it ) {
			return { id: it.id_, label: it.name || it.value || it.id_, type: it.type };
		} );
	}
	function findField( target ) {
		var t = ( target || '' ).toString().toLowerCase();
		return valj_efb.find( function ( it ) {
			return isFieldItem( it ) && ( String( it.id_ ).toLowerCase() === t || String( it.name || '' ).toLowerCase() === t );
		} ) || null;
	}
	function maxAmount() {
		var m = 0;
		valj_efb.forEach( function ( it ) { var a = Number( it.amount ); if ( a > m ) { m = a; } } );
		return m;
	}
	function applyOps( ops ) {
		var applied = [];
		ops.forEach( function ( op ) {
			try {
				if ( op.op === 'set_form_name' ) {
					valj_efb[ 0 ].formName = op.value; applied.push( 'Renamed form to "' + op.value + '"' );
				} else if ( op.op === 'set_confirmation' ) {
					if ( ! valj_efb[ 0 ].thank_you_message || typeof valj_efb[ 0 ].thank_you_message !== 'object' ) { valj_efb[ 0 ].thank_you_message = {}; }
					valj_efb[ 0 ].thank_you_message.thankYou = op.value; applied.push( 'Updated confirmation message' );
				} else if ( op.op === 'set_field' ) {
					var f = findField( op.target );
					if ( ! f ) { return; }
					if ( op.prop === 'label' ) { f.name = op.value; applied.push( 'Renamed "' + op.target + '" to "' + op.value + '"' ); }
					else if ( op.prop === 'required' ) { f.required = op.value ? '1' : '0'; applied.push( ( op.value ? 'Made "' : 'Made "' ) + op.target + '" ' + ( op.value ? 'required' : 'optional' ) ); }
					else if ( op.prop === 'placeholder' ) { f.placeholder = op.value; applied.push( 'Set placeholder of "' + op.target + '"' ); }
					else if ( op.prop === 'width' ) { f.size = WIDTH[ op.value ] || '100'; applied.push( 'Set width of "' + op.target + '" to ' + op.value ); }
					else if ( op.prop === 'help' ) { f.message = op.value; applied.push( 'Set help text of "' + op.target + '"' ); }
				} else if ( op.op === 'remove_field' ) {
					var rf = findField( op.target );
					if ( ! rf ) { return; }
					valj_efb = valj_efb.filter( function ( it ) { return it !== rf && it.parent !== rf.id_; } );
					window.valj_efb = valj_efb;
					applied.push( 'Removed "' + op.target + '"' );
				} else if ( op.op === 'add_option' ) {
					var af = findField( op.target );
					if ( ! af ) { return; }
					var oid = ( af.id_ + '_o' + ( maxAmount() + 1 ) ).replace( /[^a-z0-9_]/gi, '' );
					var opt = { id_: oid, dataId: oid + '-id', parent: af.id_, type: 'option', value: op.value, id_op: oid, step: af.step || '1', amount: String( maxAmount() + 1 ) };
					var lastIdx = valj_efb.length - 1;
					for ( var i = valj_efb.length - 1; i >= 0; i-- ) { if ( valj_efb[ i ].parent === af.id_ ) { lastIdx = i; break; } if ( valj_efb[ i ] === af ) { lastIdx = i; break; } }
					valj_efb.splice( lastIdx + 1, 0, opt );
					applied.push( 'Added option "' + op.value + '" to "' + op.target + '"' );
				} else if ( op.op === 'remove_option' ) {
					var of = findField( op.target );
					if ( ! of ) { return; }
					valj_efb = valj_efb.filter( function ( it ) { return ! ( it.type === 'option' && it.parent === of.id_ && String( it.value ).toLowerCase() === String( op.value ).toLowerCase() ); } );
					window.valj_efb = valj_efb;
					applied.push( 'Removed option "' + op.value + '" from "' + op.target + '"' );
				}
			} catch ( e ) { /* skip bad op */ }
		} );
		return applied;
	}
	function refreshDropzone() {
		try {
			sessionStorage.setItem( 'valj_efb', JSON.stringify( valj_efb ) );
			if ( typeof editFormEfb === 'function' ) { editFormEfb(); return true; }
		} catch ( e ) {}
		return false;
	}

	/* ---------- Tabs ---------- */

	function render() {
		var b = body();
		b.textContent = '';
		var tabs = el( 'div', { class: 'sitepilot-efb-tabs' } );
		[ [ 'create', 'Create' ], [ 'customize', 'Customize' ], [ 'help', 'How do I…' ] ].forEach( function ( t ) {
			var btn = el( 'button', { type: 'button', class: 'sitepilot-efb-tab' + ( tab === t[ 0 ] ? ' is-active' : '' ), text: t[ 1 ] } );
			btn.addEventListener( 'click', function () { tab = t[ 0 ]; render(); } );
			tabs.appendChild( btn );
		} );
		b.appendChild( tabs );
		var pane = el( 'div', { class: 'sitepilot-efb-pane', id: 'sitepilot-efb-pane' } );
		b.appendChild( pane );
		if ( tab === 'create' ) { renderCreate( pane ); }
		else if ( tab === 'customize' ) { renderCustomize( pane ); }
		else { renderHelp( pane ); }
	}

	function needKeyNotice( pane ) {
		var notice = el( 'div', { class: 'sitepilot-efb-notice' } );
		notice.appendChild( document.createTextNode( 'No AI provider is connected yet. ' ) );
		notice.appendChild( el( 'a', { href: cfg.settingsUrl, text: 'Add your API key in SitePilot AI → Settings' } ) );
		pane.appendChild( notice );
	}

	/* ----- Create ----- */
	var createExamples = [
		'A professional contact form with name, email, phone and message',
		'Job application: full name, email, phone, position (Sales, Support, Engineering), CV upload, cover letter',
		'Event registration with name, email, number of guests and dietary needs (None, Vegetarian, Vegan)',
		'Customer feedback survey with rating (Poor, OK, Great) and comments'
	];
	function renderCreate( pane ) {
		if ( ! cfg.configured ) { return needKeyNotice( pane ); }
		pane.appendChild( el( 'label', { class: 'sitepilot-efb-label', for: 'sitepilot-efb-prompt', text: 'Describe the form you want (SitePilot designs a complete, user-friendly form):' } ) );
		var ta = el( 'textarea', { class: 'sitepilot-efb-textarea', id: 'sitepilot-efb-prompt', rows: '3', placeholder: 'e.g. A booking form for a dental clinic' } );
		pane.appendChild( ta );
		var chips = el( 'div', { class: 'sitepilot-efb-chips' } );
		createExamples.forEach( function ( ex ) {
			var c = el( 'button', { type: 'button', class: 'sitepilot-efb-chip', text: ex } );
			c.addEventListener( 'click', function () { ta.value = ex; ta.focus(); } );
			chips.appendChild( c );
		} );
		pane.appendChild( chips );
		var gen = el( 'button', { type: 'button', class: 'sitepilot-efb-btn sitepilot-efb-btn--primary', id: 'sitepilot-efb-generate', text: 'Generate preview' } );
		gen.addEventListener( 'click', onGenerate );
		pane.appendChild( el( 'div', { class: 'sitepilot-efb-actions' }, [ gen, el( 'span', { class: 'sitepilot-efb-status', id: 'sitepilot-efb-status' } ) ] ) );
		pane.appendChild( el( 'div', { id: 'sitepilot-efb-preview' } ) );
	}
	function onGenerate() {
		var prompt = ( document.getElementById( 'sitepilot-efb-prompt' ).value || '' ).trim();
		if ( ! prompt ) { setStatus( 'sitepilot-efb-status', 'Please describe the form first.', 'error' ); return; }
		var gen = document.getElementById( 'sitepilot-efb-generate' );
		gen.disabled = true;
		setStatus( 'sitepilot-efb-status', 'Designing your form…', 'loading' );
		api( 'efb/plan', { prompt: prompt } ).then( function ( res ) {
			gen.disabled = false;
			if ( ! res.data.ok ) {
				var msg = res.data.message || 'Could not generate a form.';
				if ( res.data.errors && res.data.errors.length ) { msg += ' — ' + res.data.errors.join( ' ' ); }
				setStatus( 'sitepilot-efb-status', msg, 'error' ); return;
			}
			actionId = res.data.action_id;
			setStatus( 'sitepilot-efb-status', 'Review, then approve to create it.', 'ok' );
			renderPreview( res.data.preview );
		} ).catch( function () { gen.disabled = false; setStatus( 'sitepilot-efb-status', 'Network error.', 'error' ); } );
	}
	function renderPreview( p ) {
		var wrap = document.getElementById( 'sitepilot-efb-preview' );
		wrap.textContent = '';
		var card = el( 'div', { class: 'sitepilot-efb-card' } );
		card.appendChild( el( 'h3', { text: p.title } ) );
		card.appendChild( el( 'div', { class: 'sitepilot-efb-meta' }, [
			el( 'span', { class: 'sitepilot-efb-pill', text: p.connector } ),
			el( 'span', { class: 'sitepilot-efb-pill sitepilot-efb-pill--muted', text: p.field_count + ' fields' } )
		] ) );
		var table = el( 'table', { class: 'sitepilot-efb-fields' } );
		table.appendChild( el( 'thead', {}, [ el( 'tr', {}, [ el( 'th', { text: 'Label' } ), el( 'th', { text: 'Type' } ), el( 'th', { text: 'Req.' } ), el( 'th', { text: 'Width' } ), el( 'th', { text: 'Options' } ) ] ) ] ) );
		var tb = el( 'tbody' );
		p.fields.forEach( function ( f ) {
			tb.appendChild( el( 'tr', {}, [
				el( 'td', { text: f.label } ), el( 'td', {}, [ el( 'code', { text: f.type } ) ] ),
				el( 'td', { text: f.required ? 'Yes' : 'No' } ), el( 'td', { text: f.width || 'full' } ),
				el( 'td', { text: ( f.options || [] ).join( ', ' ) } )
			] ) );
		} );
		table.appendChild( tb );
		card.appendChild( table );
		var approve = el( 'button', { type: 'button', class: 'sitepilot-efb-btn sitepilot-efb-btn--primary', id: 'sitepilot-efb-approve', text: 'Approve & create in Easy Form Builder' } );
		var cancel = el( 'button', { type: 'button', class: 'sitepilot-efb-btn sitepilot-efb-btn--ghost', text: 'Discard' } );
		approve.addEventListener( 'click', onApprove );
		cancel.addEventListener( 'click', function () { wrap.textContent = ''; actionId = null; } );
		card.appendChild( el( 'div', { class: 'sitepilot-efb-actions' }, [ approve, cancel, el( 'span', { class: 'sitepilot-efb-status', id: 'sitepilot-efb-approve-status' } ) ] ) );
		wrap.appendChild( card );
	}
	function onApprove() {
		if ( ! actionId ) { return; }
		var btn = document.getElementById( 'sitepilot-efb-approve' );
		btn.disabled = true;
		setStatus( 'sitepilot-efb-approve-status', 'Creating the form…', 'loading' );
		api( 'efb/create', { action_id: actionId } ).then( function ( res ) {
			if ( ! res.data.ok ) { setStatus( 'sitepilot-efb-approve-status', res.data.message || 'Creation failed.', 'error' ); btn.disabled = false; return; }
			var r = res.data.result;
			setStatus( 'sitepilot-efb-approve-status', 'Created! Opening the editor…', 'ok' );
			if ( r.edit_url ) { setTimeout( function () { window.location.href = r.edit_url; }, 600 ); }
		} ).catch( function () { setStatus( 'sitepilot-efb-approve-status', 'Network error.', 'error' ); btn.disabled = false; } );
	}

	/* ----- Customize ----- */
	var customizeExamples = [ 'Make Email required', 'Rename the form to Contact us', 'Reduce the width of First name to half', 'Add option Vegan to Diet', 'Change the confirmation message to Thanks, we will call you back' ];
	function renderCustomize( pane ) {
		if ( ! cfg.configured ) { return needKeyNotice( pane ); }
		if ( ! hasLiveForm() ) {
			pane.appendChild( el( 'div', { class: 'sitepilot-efb-notice' }, [ 'Open a form in the Easy Form Builder editor first (create one above, or go to Easy Form Builder → Forms and edit a form). Then come back here to customize it by chatting.' ] ) );
			return;
		}
		pane.appendChild( el( 'label', { class: 'sitepilot-efb-label', for: 'sitepilot-efb-cmd', text: 'Tell SitePilot what to change on this form:' } ) );
		var ta = el( 'textarea', { class: 'sitepilot-efb-textarea', id: 'sitepilot-efb-cmd', rows: '2', placeholder: 'e.g. Make Phone required and reduce its width' } );
		pane.appendChild( ta );
		var chips = el( 'div', { class: 'sitepilot-efb-chips' } );
		customizeExamples.forEach( function ( ex ) {
			var c = el( 'button', { type: 'button', class: 'sitepilot-efb-chip', text: ex } );
			c.addEventListener( 'click', function () { ta.value = ex; ta.focus(); } );
			chips.appendChild( c );
		} );
		pane.appendChild( chips );
		var run = el( 'button', { type: 'button', class: 'sitepilot-efb-btn sitepilot-efb-btn--primary', id: 'sitepilot-efb-run', text: 'Apply change' } );
		run.addEventListener( 'click', onCustomize );
		pane.appendChild( el( 'div', { class: 'sitepilot-efb-actions' }, [ run, el( 'span', { class: 'sitepilot-efb-status', id: 'sitepilot-efb-cmd-status' } ) ] ) );
		pane.appendChild( el( 'div', { class: 'sitepilot-efb-log', id: 'sitepilot-efb-log' } ) );
	}
	function logChange( lines, kind ) {
		var log = document.getElementById( 'sitepilot-efb-log' );
		if ( ! log ) { return; }
		var item = el( 'div', { class: 'sitepilot-efb-logline' + ( kind ? ' sitepilot-efb-logline--' + kind : '' ) } );
		( Array.isArray( lines ) ? lines : [ lines ] ).forEach( function ( l ) { item.appendChild( el( 'div', { text: '• ' + l } ) ); } );
		log.insertBefore( item, log.firstChild );
	}
	function onCustomize() {
		var cmd = ( document.getElementById( 'sitepilot-efb-cmd' ).value || '' ).trim();
		if ( ! cmd ) { setStatus( 'sitepilot-efb-cmd-status', 'Type a change first.', 'error' ); return; }
		if ( ! hasLiveForm() ) { setStatus( 'sitepilot-efb-cmd-status', 'No form is open in the editor.', 'error' ); return; }
		var run = document.getElementById( 'sitepilot-efb-run' );
		run.disabled = true;
		setStatus( 'sitepilot-efb-cmd-status', 'Understanding…', 'loading' );
		api( 'efb/customize', { command: cmd, fields: liveFields() } ).then( function ( res ) {
			run.disabled = false;
			if ( ! res.data.ok || ! res.data.operations || ! res.data.operations.length ) {
				setStatus( 'sitepilot-efb-cmd-status', res.data.message || 'No change understood.', 'error' ); return;
			}
			var applied = applyOps( res.data.operations );
			if ( ! applied.length ) { setStatus( 'sitepilot-efb-cmd-status', 'Could not match a field on this form.', 'error' ); return; }
			var refreshed = refreshDropzone();
			setStatus( 'sitepilot-efb-cmd-status', refreshed ? 'Applied. Preview updated.' : 'Applied (reloading to preview)…', 'ok' );
			logChange( applied, 'ok' );
			document.getElementById( 'sitepilot-efb-cmd' ).value = '';
			// Persist to the form row.
			var fid = getFormId();
			if ( fid ) { api( 'efb/save', { form_id: fid, structure: valj_efb } ).then( function ( s ) { if ( ! s.data.ok ) { logChange( 'Note: saved in editor only — click Easy Form Builder Save to persist.', 'warn' ); } } ); }
			if ( ! refreshed ) { setTimeout( function () { window.location.reload(); }, 700 ); }
		} ).catch( function () { run.disabled = false; setStatus( 'sitepilot-efb-cmd-status', 'Network error.', 'error' ); } );
	}

	/* ----- Help ----- */
	var helpExamples = [ 'How do I make a field required?', 'How do I change a field width?', 'How do I add options to a dropdown?', 'How do I turn on email notifications?', 'How do I get the shortcode?' ];
	function renderHelp( pane ) {
		pane.appendChild( el( 'label', { class: 'sitepilot-efb-label', for: 'sitepilot-efb-q', text: 'Ask how to do anything in Easy Form Builder:' } ) );
		var ta = el( 'input', { class: 'sitepilot-efb-textarea', id: 'sitepilot-efb-q', placeholder: 'e.g. How do I make the name field required?' } );
		pane.appendChild( ta );
		var chips = el( 'div', { class: 'sitepilot-efb-chips' } );
		helpExamples.forEach( function ( ex ) {
			var c = el( 'button', { type: 'button', class: 'sitepilot-efb-chip', text: ex } );
			c.addEventListener( 'click', function () { ta.value = ex; askHelp(); } );
			chips.appendChild( c );
		} );
		pane.appendChild( chips );
		var ask = el( 'button', { type: 'button', class: 'sitepilot-efb-btn sitepilot-efb-btn--primary', id: 'sitepilot-efb-ask', text: 'Ask' } );
		ask.addEventListener( 'click', askHelp );
		pane.appendChild( el( 'div', { class: 'sitepilot-efb-actions' }, [ ask, el( 'span', { class: 'sitepilot-efb-status', id: 'sitepilot-efb-q-status' } ) ] ) );
		pane.appendChild( el( 'div', { id: 'sitepilot-efb-answer' } ) );
	}
	function askHelp() {
		var q = ( document.getElementById( 'sitepilot-efb-q' ).value || '' ).trim();
		if ( ! q ) { setStatus( 'sitepilot-efb-q-status', 'Type a question.', 'error' ); return; }
		setStatus( 'sitepilot-efb-q-status', 'Looking it up…', 'loading' );
		api( 'efb/help', { question: q } ).then( function ( res ) {
			setStatus( 'sitepilot-efb-q-status', '', '' );
			var wrap = document.getElementById( 'sitepilot-efb-answer' );
			wrap.textContent = '';
			if ( res.data.code === 'no_match' ) {
				wrap.appendChild( el( 'p', { text: res.data.message } ) );
				var list = el( 'div', { class: 'sitepilot-efb-chips' } );
				( res.data.suggestions || [] ).forEach( function ( s ) {
					var c = el( 'button', { type: 'button', class: 'sitepilot-efb-chip', text: s.title } );
					c.addEventListener( 'click', function () { document.getElementById( 'sitepilot-efb-q' ).value = s.title; askHelp(); } );
					list.appendChild( c );
				} );
				wrap.appendChild( list );
				return;
			}
			var card = el( 'div', { class: 'sitepilot-efb-card' } );
			card.appendChild( el( 'h3', { text: res.data.title || 'How to' } ) );
			( res.data.answer || '' ).split( '\n\n' ).forEach( function ( para ) {
				if ( para.trim() ) { card.appendChild( el( 'p', { text: para.trim() } ) ); }
			} );
			wrap.appendChild( card );
		} ).catch( function () { setStatus( 'sitepilot-efb-q-status', 'Network error.', 'error' ); } );
	}

	/* ---------- Modal open/close ---------- */
	function open() {
		var m = document.getElementById( 'sitepilot-efb-modal' );
		if ( ! m ) { return; }
		tab = 'create';
		render();
		m.hidden = false; m.setAttribute( 'aria-hidden', 'false' );
	}
	function close() {
		var m = document.getElementById( 'sitepilot-efb-modal' );
		if ( ! m ) { return; }
		m.hidden = true; m.setAttribute( 'aria-hidden', 'true' ); actionId = null;
	}
	function init() {
		var launch = document.getElementById( 'sitepilot-efb-launch' );
		if ( launch ) { launch.addEventListener( 'click', open ); }
		var modal = document.getElementById( 'sitepilot-efb-modal' );
		if ( modal ) { modal.addEventListener( 'click', function ( e ) { if ( e.target && e.target.getAttribute( 'data-close' ) === '1' ) { close(); } } ); }
		document.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' ) { close(); } } );
	}
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
} )();
