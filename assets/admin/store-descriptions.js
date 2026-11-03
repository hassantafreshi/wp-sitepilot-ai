/**
 * Store Copilot > Descriptions.
 *
 * Lists products with a missing or short description, writes AI drafts one request at a
 * time, and shows each draft beside the current text to edit, apply or skip. Applying
 * replaces the long description only; Undo puts the previous text back. Every change goes
 * through the protected /woocommerce/descriptions routes.
 */
( function () {
	'use strict';

	var cfg = window.AISAAutoPilotAI || {};
	var panel = document.querySelector( '[data-sp-store-descriptions-panel][data-sp-desc-ready="1"]' );
	if ( ! panel || ! cfg.restUrl ) {
		return;
	}
	var MAX = 10;
	var i18n = {};
	try { i18n = JSON.parse( ( document.getElementById( 'sp-store-i18n' ) || {} ).textContent || '{}' ) || {}; } catch ( e ) { i18n = {}; }
	function t( key, fallback ) { return typeof i18n[ key ] === 'string' && i18n[ key ] ? i18n[ key ] : fallback; }
	function fill( text, values ) {
		var i = 0;
		return String( text ).replace( /%(\d+)\$s|%s/g, function ( match, n ) { var v = n ? values[ Number( n ) - 1 ] : values[ i++ ]; return undefined === v ? match : String( v ); } );
	}
	function q( sel ) { return panel.querySelector( sel ); }
	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( key ) {
			if ( 'text' === key ) { node.textContent = attrs[ key ]; } else if ( 'class' === key ) { node.className = attrs[ key ]; } else { node.setAttribute( key, attrs[ key ] ); }
		} );
		( children || [] ).forEach( function ( child ) { if ( child ) { node.appendChild( child ); } } );
		return node;
	}

	// Messages in the admin's language; with plain permalinks the REST root already has "?rest_route=".
	function call( method, path, body ) {
		var url = cfg.restUrl + path;
		url += ( -1 === url.indexOf( '?' ) ? '?' : '&' ) + '_locale=user';
		var init = { method: method, credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce, Accept: 'application/json' } };
		if ( body ) { init.headers[ 'Content-Type' ] = 'application/json'; init.body = JSON.stringify( body ); }
		return fetch( url, init ).then( function ( response ) {
			return response.json().catch( function () { return {}; } ).then( function ( data ) { return { status: response.status, data: data || {} }; } );
		} );
	}

	/* A draft is shown as text and simple structure only: headings, paragraphs, lists, emphasis. */
	var KEEP = { H2: 1, H3: 1, H4: 1, P: 1, UL: 1, OL: 1, LI: 1, STRONG: 1, EM: 1, B: 1, I: 1, BR: 1 };
	function safePreview( html ) {
		var doc = new DOMParser().parseFromString( '<div>' + html + '</div>', 'text/html' );
		var out = document.createElement( 'div' );
		( function copy( from, to ) {
			Array.prototype.forEach.call( from.childNodes, function ( node ) {
				if ( 3 === node.nodeType ) { to.appendChild( document.createTextNode( node.textContent ) ); return; }
				if ( 1 !== node.nodeType ) { return; }
				if ( KEEP[ node.tagName ] ) { var clone = document.createElement( node.tagName.toLowerCase() ); to.appendChild( clone ); copy( node, clone ); } else { copy( node, to ); }
			} );
		}( doc.body.firstChild || doc.body, out ) );
		return out;
	}

	var status = q( '[data-sp-desc-status]' );
	var list = q( '[data-sp-desc-list]' );
	var reviews = q( '[data-sp-desc-reviews]' );
	var write = q( '[data-sp-desc-write]' );
	var onlyShort = q( '[data-sp-desc-only-short]' );
	function say( text, tone ) { if ( status ) { status.textContent = text || ''; status.className = 'sp-status-text' + ( tone ? ' is-' + tone : '' ); } }
	function checked() { return Array.prototype.slice.call( list.querySelectorAll( 'input[type="checkbox"]:checked' ) ); }
	function syncWrite() {
		var n = checked().length;
		write.disabled = 0 === n || n > MAX;
		say( n > MAX ? t( 'desc_limit', 'Pick up to 10 products at a time.' ) : '', n > MAX ? 'err' : '' );
	}

	function load() {
		list.textContent = '';
		say( t( 'desc_loading', 'Loading products…' ) );
		call( 'GET', 'woocommerce/descriptions' + ( onlyShort.checked ? '' : '?only_short=0' ) ).then( function ( res ) {
			var products = res.data.products || [];
			say( products.length ? '' : t( 'desc_none', 'Every product already has a description of at least 40 words.' ) );
			products.forEach( function ( p ) {
				var box = el( 'input', { type: 'checkbox', value: String( p.id ), 'data-sp-desc-product': String( p.id ) } );
				box.addEventListener( 'change', syncWrite );
				list.appendChild( el( 'li', { class: 'sp-store-desc__item' }, [
					el( 'label', {}, [ box, el( 'span', { class: 'sp-store-desc__name', dir: 'auto', text: p.name } ) ] ),
					el( 'span', { class: 'sp-store-desc__meta', text: p.words > 0 ? fill( t( 'desc_words', '%s words now' ), [ p.words ] ) : t( 'desc_empty', 'No description yet' ) } )
				] ) );
			} );
			syncWrite();
		} ).catch( function () { say( t( 'network_error', 'Network error. Please try again.' ), 'err' ); } );
	}

	function reviewCard( d ) {
		var area = el( 'textarea', { class: 'sp-textarea', rows: '8', dir: 'auto', 'aria-label': t( 'desc_edit_label', 'Description' ) } );
		area.value = d.draft;
		var preview = el( 'div', { class: 'sp-store-desc__preview', dir: 'auto' }, [ safePreview( d.draft ) ] );
		area.addEventListener( 'input', function () { preview.textContent = ''; preview.appendChild( safePreview( area.value ) ); } );
		var note = el( 'p', { class: 'sp-status-text', role: 'status', 'aria-live': 'polite' } );
		var apply = el( 'button', { type: 'button', class: 'sp-btn', 'data-sp-desc-apply': d.action_id, text: t( 'desc_apply', 'Apply' ) } );
		var skip = el( 'button', { type: 'button', class: 'sp-btn sp-btn--ghost', 'data-sp-desc-skip': '', text: t( 'desc_skip', 'Skip' ) } );
		var actions = el( 'div', { class: 'sp-store-desc__actions' }, [ apply, skip ] );
		var card = el( 'article', { class: 'sp-store-desc__review', 'data-sp-desc-review': String( d.product_id ) }, [
			el( 'h3', { dir: 'auto', text: d.name } ),
			el( 'div', { class: 'sp-store-desc__compare' }, [
				el( 'div', {}, [ el( 'h4', { text: t( 'desc_current', 'Now' ) } ), el( 'p', { class: 'sp-store-desc__now', dir: 'auto', text: d.current || t( 'desc_empty', 'No description yet' ) } ) ] ),
				el( 'div', {}, [ el( 'h4', { text: t( 'desc_draft', 'AI draft (you can edit it)' ) } ), preview, el( 'details', {}, [ el( 'summary', { text: t( 'desc_edit_label', 'Description' ) } ), area ] ) ] )
			] ),
			actions,
			note
		] );
		apply.addEventListener( 'click', function () {
			apply.disabled = true; skip.disabled = true;
			call( 'POST', 'woocommerce/descriptions/apply', { action_id: d.action_id, description: area.value !== d.draft ? area.value : '' } ).then( function ( res ) {
				note.textContent = res.data.message || '';
				note.className = 'sp-status-text ' + ( res.data.ok ? 'is-ok' : 'is-err' );
				if ( ! res.data.ok ) { apply.disabled = false; skip.disabled = false; return; }
				card.classList.add( 'is-applied' );
				actions.textContent = '';
				var undo = el( 'button', { type: 'button', class: 'sp-btn sp-btn--ghost', 'data-sp-desc-undo': String( d.product_id ), text: t( 'desc_undo', 'Undo' ) } );
				undo.addEventListener( 'click', function () {
					undo.disabled = true;
					call( 'POST', 'woocommerce/descriptions/undo', { product_id: d.product_id } ).then( function ( r ) {
						note.textContent = r.data.message || '';
						note.className = 'sp-status-text ' + ( r.data.ok ? 'is-ok' : 'is-err' );
						if ( r.data.ok ) { card.classList.remove( 'is-applied' ); card.classList.add( 'is-skipped' ); actions.textContent = ''; } else { undo.disabled = false; }
					} );
				} );
				actions.appendChild( el( 'span', { class: 'sp-pill sp-pill--ok', text: t( 'desc_applied', 'Applied' ) } ) );
				actions.appendChild( undo );
			} ).catch( function () { apply.disabled = false; skip.disabled = false; note.textContent = t( 'network_error', 'Network error. Please try again.' ); } );
		} );
		skip.addEventListener( 'click', function () {
			card.classList.add( 'is-skipped' );
			actions.textContent = '';
			note.textContent = t( 'desc_skipped', 'Skipped. Nothing changed.' );
		} );
		return card;
	}

	function writeDrafts() {
		var ids = checked().map( function ( box ) { return Number( box.value ); } ).slice( 0, MAX );
		var tone = ( q( '[data-sp-desc-tone]' ) || {} ).value || 'professional';
		reviews.textContent = '';
		write.disabled = true;
		var step = 0;
		ids.reduce( function ( chain, id ) {
			return chain.then( function () {
				step++;
				say( fill( t( 'desc_writing', 'Writing draft %1$s of %2$s…' ), [ step, ids.length ] ) );
				return call( 'POST', 'woocommerce/descriptions/draft', { product_id: id, tone: tone } ).then( function ( res ) {
					if ( res.data.ok ) { reviews.appendChild( reviewCard( res.data ) ); return; }
					reviews.appendChild( el( 'article', { class: 'sp-store-desc__review is-failed', 'data-sp-desc-review': String( id ) }, [
						el( 'p', { class: 'sp-status-text is-err', text: res.data.message || t( 'desc_failed', 'No draft for this product.' ) } )
					] ) );
				} );
			} );
		}, Promise.resolve() ).then( function () {
			say( t( 'desc_written', 'Drafts are ready. Read each one, edit it if you like, then apply or skip it.' ), 'ok' );
		} ).catch( function () {
			say( t( 'network_error', 'Network error. Please try again.' ), 'err' );
		} ).then( function () { syncWrite(); } );
	}

	write.addEventListener( 'click', writeDrafts );
	onlyShort.addEventListener( 'change', load );
	load();
}() );
