/* global SitePilotEFB */
( function () {
	'use strict';

	var cfg = window.SitePilotEFB || {};
	var actionId = null;

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
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( body )
		} ).then( function ( r ) {
			return r.json().then( function ( d ) { return { status: r.status, data: d }; } );
		} );
	}

	var examples = [
		'Contact form with name, email, phone and message',
		'Job application: full name, email, phone, position (Sales, Support, Engineering), CV upload',
		'Event registration with name, email, number of guests and dietary needs (None, Vegetarian, Vegan)',
		'Feedback form: name, rating question as radio (Poor, OK, Great) and comments'
	];

	function body() { return document.getElementById( 'sitepilot-efb-body' ); }

	function renderComposer() {
		var b = body();
		b.textContent = '';

		if ( ! cfg.configured ) {
			var notice = el( 'div', { class: 'sitepilot-efb-notice' } );
			notice.appendChild( document.createTextNode( 'No AI provider is connected yet. ' ) );
			var a = el( 'a', { href: cfg.settingsUrl, text: 'Add your API key in SitePilot AI → Settings' } );
			notice.appendChild( a );
			b.appendChild( notice );
			return;
		}

		b.appendChild( el( 'label', { class: 'sitepilot-efb-label', for: 'sitepilot-efb-prompt', text: 'Describe the form you want to build:' } ) );
		var ta = el( 'textarea', { class: 'sitepilot-efb-textarea', id: 'sitepilot-efb-prompt', rows: '3', placeholder: 'e.g. Contact form with name, email, phone and message' } );
		b.appendChild( ta );

		var chips = el( 'div', { class: 'sitepilot-efb-chips' } );
		examples.forEach( function ( ex ) {
			var c = el( 'button', { type: 'button', class: 'sitepilot-efb-chip', text: ex } );
			c.addEventListener( 'click', function () { ta.value = ex; ta.focus(); } );
			chips.appendChild( c );
		} );
		b.appendChild( chips );

		var gen = el( 'button', { type: 'button', class: 'sitepilot-efb-btn sitepilot-efb-btn--primary', id: 'sitepilot-efb-generate', text: 'Generate preview' } );
		var status = el( 'span', { class: 'sitepilot-efb-status', id: 'sitepilot-efb-status' } );
		gen.addEventListener( 'click', onGenerate );
		b.appendChild( el( 'div', { class: 'sitepilot-efb-actions' }, [ gen, status ] ) );

		b.appendChild( el( 'div', { id: 'sitepilot-efb-preview' } ) );
	}

	function setStatus( id, msg, kind ) {
		var s = document.getElementById( id );
		if ( ! s ) { return; }
		s.className = 'sitepilot-efb-status' + ( kind && kind !== 'loading' ? ' sitepilot-efb-status--' + kind : '' );
		s.textContent = '';
		if ( kind === 'loading' ) { s.appendChild( el( 'span', { class: 'sitepilot-efb-spinner' } ) ); }
		s.appendChild( document.createTextNode( msg ) );
	}

	function onGenerate() {
		var prompt = ( document.getElementById( 'sitepilot-efb-prompt' ).value || '' ).trim();
		if ( ! prompt ) { setStatus( 'sitepilot-efb-status', 'Please describe the form first.', 'error' ); return; }
		var gen = document.getElementById( 'sitepilot-efb-generate' );
		gen.disabled = true;
		setStatus( 'sitepilot-efb-status', 'Thinking… drafting your form.', 'loading' );

		api( 'efb/plan', { prompt: prompt } ).then( function ( res ) {
			gen.disabled = false;
			if ( ! res.data.ok ) {
				var msg = res.data.message || 'Could not generate a form.';
				if ( res.data.errors && res.data.errors.length ) { msg += ' — ' + res.data.errors.join( ' ' ); }
				setStatus( 'sitepilot-efb-status', msg, 'error' );
				return;
			}
			actionId = res.data.action_id;
			setStatus( 'sitepilot-efb-status', 'Review, then approve to create it in Easy Form Builder.', 'ok' );
			renderPreview( res.data.preview );
		} ).catch( function () {
			gen.disabled = false;
			setStatus( 'sitepilot-efb-status', 'Network error. Please try again.', 'error' );
		} );
	}

	function renderPreview( p ) {
		var wrap = document.getElementById( 'sitepilot-efb-preview' );
		wrap.textContent = '';
		var card = el( 'div', { class: 'sitepilot-efb-card' } );
		card.appendChild( el( 'h3', { text: p.title } ) );

		var risk = { low: 'sitepilot-efb-pill', medium: 'sitepilot-efb-pill--warn', high: 'sitepilot-efb-pill--warn' };
		card.appendChild( el( 'div', { class: 'sitepilot-efb-meta' }, [
			el( 'span', { class: 'sitepilot-efb-pill', text: p.connector } ),
			el( 'span', { class: 'sitepilot-efb-pill--muted sitepilot-efb-pill', text: p.field_count + ' fields' } ),
			el( 'span', { class: 'sitepilot-efb-pill ' + ( risk[ p.risk_level ] || '' ), text: 'Risk: ' + p.risk_level } )
		] ) );

		var table = el( 'table', { class: 'sitepilot-efb-fields' } );
		table.appendChild( el( 'thead', {}, [ el( 'tr', {}, [
			el( 'th', { text: 'Label' } ), el( 'th', { text: 'Type' } ), el( 'th', { text: 'Required' } ), el( 'th', { text: 'Options' } )
		] ) ] ) );
		var tb = el( 'tbody' );
		p.fields.forEach( function ( f ) {
			tb.appendChild( el( 'tr', {}, [
				el( 'td', { text: f.label } ),
				el( 'td', {}, [ el( 'code', { text: f.type } ) ] ),
				el( 'td', { text: f.required ? 'Yes' : 'No' } ),
				el( 'td', { text: ( f.options || [] ).join( ', ' ) } )
			] ) );
		} );
		table.appendChild( tb );
		card.appendChild( table );

		var approve = el( 'button', { type: 'button', class: 'sitepilot-efb-btn sitepilot-efb-btn--primary', id: 'sitepilot-efb-approve', text: 'Approve & create in Easy Form Builder' } );
		var cancel = el( 'button', { type: 'button', class: 'sitepilot-efb-btn sitepilot-efb-btn--ghost', text: 'Discard' } );
		var st = el( 'span', { class: 'sitepilot-efb-status', id: 'sitepilot-efb-approve-status' } );
		approve.addEventListener( 'click', onApprove );
		cancel.addEventListener( 'click', function () { wrap.textContent = ''; actionId = null; } );
		card.appendChild( el( 'div', { class: 'sitepilot-efb-actions' }, [ approve, cancel, st ] ) );

		wrap.appendChild( card );
	}

	function onApprove() {
		if ( ! actionId ) { return; }
		var btn = document.getElementById( 'sitepilot-efb-approve' );
		btn.disabled = true;
		setStatus( 'sitepilot-efb-approve-status', 'Creating the form…', 'loading' );

		api( 'efb/create', { action_id: actionId } ).then( function ( res ) {
			if ( ! res.data.ok ) {
				setStatus( 'sitepilot-efb-approve-status', res.data.message || 'Creation failed.', 'error' );
				btn.disabled = false;
				return;
			}
			actionId = null;
			renderResult( res.data.result );
		} ).catch( function () {
			setStatus( 'sitepilot-efb-approve-status', 'Network error. Please try again.', 'error' );
			btn.disabled = false;
		} );
	}

	function renderResult( r ) {
		var b = body();
		b.textContent = '';
		var card = el( 'div', { class: 'sitepilot-efb-card' } );
		card.appendChild( el( 'h3', {}, [ el( 'span', { class: 'dashicons dashicons-yes-alt', style: 'color:#15803d' } ), document.createTextNode( ' Form created in Easy Form Builder' ) ] ) );

		if ( r.shortcode ) {
			var code = el( 'code', { text: r.shortcode } );
			var copy = el( 'button', { type: 'button', class: 'sitepilot-efb-btn sitepilot-efb-btn--ghost', text: 'Copy shortcode' } );
			copy.addEventListener( 'click', function () {
				if ( navigator.clipboard ) {
					navigator.clipboard.writeText( r.shortcode );
					copy.textContent = 'Copied!';
					setTimeout( function () { copy.textContent = 'Copy shortcode'; }, 1500 );
				}
			} );
			card.appendChild( el( 'p', { text: 'Paste this shortcode into any page or post:' } ) );
			card.appendChild( el( 'div', { class: 'sitepilot-efb-shortcode' }, [ code, copy ] ) );
		}

		var actions = el( 'div', { class: 'sitepilot-efb-actions' } );
		if ( r.edit_url ) {
			actions.appendChild( el( 'a', { class: 'sitepilot-efb-btn sitepilot-efb-btn--primary', href: r.edit_url, text: 'Open Easy Form Builder forms' } ) );
		}
		var again = el( 'button', { type: 'button', class: 'sitepilot-efb-btn sitepilot-efb-btn--ghost', text: 'Build another' } );
		again.addEventListener( 'click', renderComposer );
		actions.appendChild( again );
		card.appendChild( actions );

		b.appendChild( card );
	}

	function open() {
		var m = document.getElementById( 'sitepilot-efb-modal' );
		if ( ! m ) { return; }
		renderComposer();
		m.hidden = false;
		m.setAttribute( 'aria-hidden', 'false' );
	}
	function close() {
		var m = document.getElementById( 'sitepilot-efb-modal' );
		if ( ! m ) { return; }
		m.hidden = true;
		m.setAttribute( 'aria-hidden', 'true' );
		actionId = null;
	}

	function init() {
		var launch = document.getElementById( 'sitepilot-efb-launch' );
		if ( launch ) { launch.addEventListener( 'click', open ); }
		var modal = document.getElementById( 'sitepilot-efb-modal' );
		if ( modal ) {
			modal.addEventListener( 'click', function ( e ) {
				if ( e.target && e.target.getAttribute( 'data-close' ) === '1' ) { close(); }
			} );
		}
		document.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' ) { close(); } } );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
