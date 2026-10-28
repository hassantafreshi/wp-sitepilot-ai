/*
 * AI dock helpers shared by the Easy Form Builder copilot and Form Studio: the icon set
 * and the small builders for the thread (bubbles, cards, rows, working steps, starting
 * points). No network and no state here; each screen owns its own flow.
 */
( function () {
	'use strict';

	var NS = 'http://www.w3.org/2000/svg';
	// Constant markup only, never data: icons are drawn from this table alone.
	var P = {
		spark: '<path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
		plug: '<path d="M9 2v6M15 2v6M6 8h12v4a6 6 0 01-12 0zM12 18v4"/>',
		key: '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l3 3"/>',
		ext: '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V7a1 1 0 011-1h5"/>',
		plus: '<path d="M12 5v14M5 12h14"/>',
		minus: '<path d="M5 12h14"/>',
		check: '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
		undo: '<path d="M9 14L4 9l5-5"/><path d="M4 9h11a5 5 0 010 10h-3"/>',
		eye: '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
		mic: '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0014 0M12 18v3"/>',
		up: '<path d="M12 19V5M5.5 11.5L12 5l6.5 6.5"/>',
		x: '<path d="M6 6l12 12M18 6L6 18"/>',
		newchat: '<path d="M12 20H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v6"/><path d="M18 15v6M15 18h6"/>',
		alert: '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/>',
		logic: '<circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><circle cx="18" cy="6" r="2.5"/><path d="M6 8.5v3a3 3 0 003 3h6.5M15.5 6H8.5"/>',
		list: '<path d="M9 6h11M9 12h11M9 18h11"/><path d="M4 6h.01M4 12h.01M4 18h.01"/>',
		wand: '<path d="M15 4V2M15 10V8M11 6H9M21 6h-2M18.5 3.5L17 5M18.5 8.5L17 7"/><path d="M3 21l11-11"/>',
		help: '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 015 .5c0 1.7-2.5 2-2.5 3.5M12 17h.01"/>',
		text: '<path d="M4 7V5h16v2M9 19h6M12 5v14"/>',
		mail: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
		phone: '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z"/>',
		select: '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M14 11l2 2 2-2"/>',
		calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
		radio: '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/>',
		checkbox: '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 12l3 3 5-6"/>',
		para: '<path d="M4 6h16M4 10h16M4 14h10M4 18h7"/>',
		upload: '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v3a2 2 0 002 2h12a2 2 0 002-2v-3"/>',
		hash: '<path d="M5 9h14M5 15h14M10 4L8 20M16 4l-2 16"/>',
		link: '<path d="M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 00-5.7 0l-3 3a4 4 0 005.7 5.7l1-1"/>',
		star: '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>',
		save: '<path d="M5 3h11l3 3v13a2 2 0 01-2 2H7a2 2 0 01-2-2z"/><path d="M8 3v5h7M8 21v-7h8v7"/>',
		shield: '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M8.5 12l2.5 2.5 4.5-4.5"/>',
		edit: '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
		play: '<path d="M7 5l12 7-12 7z"/>',
		refresh: '<path d="M20 11a8 8 0 10-2.3 5.7M20 4v7h-7"/>',
		doc: '<path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4M9 12h7M9 16h5"/>',
		trash: '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
		pro: '<path d="M12 3l3 6 6 .9-4.5 4.4 1 6.2L12 17.5 6.5 20.5l1-6.2L3 9.9 9 9z"/>',
		monitor: '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
		mobile: '<rect x="7" y="3" width="10" height="18" rx="2"/><path d="M11 18h2"/>'
	};

	function icon( name, size, stroke ) {
		var svg = document.createElementNS( NS, 'svg' );
		svg.setAttribute( 'width', String( size || 16 ) );
		svg.setAttribute( 'height', String( size || 16 ) );
		svg.setAttribute( 'viewBox', '0 0 24 24' );
		svg.setAttribute( 'fill', 'none' );
		svg.setAttribute( 'stroke', 'currentColor' );
		svg.setAttribute( 'stroke-width', String( stroke || 2 ) );
		svg.setAttribute( 'stroke-linecap', 'round' );
		svg.setAttribute( 'stroke-linejoin', 'round' );
		svg.setAttribute( 'aria-hidden', 'true' );
		svg.setAttribute( 'focusable', 'false' );
		svg.innerHTML = P[ name ] || P.spark;
		return svg;
	}

	/** Creates an element; `text` is set as text, never parsed. Children may be nodes or strings. */
	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		attrs = attrs || {};
		Object.keys( attrs ).forEach( function ( key ) {
			var value = attrs[ key ];
			if ( null === value || undefined === value || false === value ) { return; }
			if ( 'class' === key ) { node.className = value; } else if ( 'text' === key ) { node.textContent = String( value ); } else { node.setAttribute( key, true === value ? '' : String( value ) ); }
		} );
		( children || [] ).forEach( function ( child ) {
			if ( null === child || undefined === child || false === child ) { return; }
			node.appendChild( 'string' === typeof child ? document.createTextNode( child ) : child );
		} );
		return node;
	}

	/** Fills every [data-aisa-icon] placeholder the server rendered. */
	function hydrate( root ) {
		Array.prototype.forEach.call( ( root || document ).querySelectorAll( '[data-aisa-icon]' ), function ( node ) {
			if ( node.firstChild ) { return; }
			node.appendChild( icon( node.getAttribute( 'data-aisa-icon' ), Number( node.getAttribute( 'data-aisa-size' ) ) || 16, Number( node.getAttribute( 'data-aisa-stroke' ) ) || 2 ) );
		} );
	}

	/** Card header: kind is ok, warn, plan or logic; small is the note on the far side. */
	function cardHead( kind, iconName, title, small ) {
		return el( 'div', { class: 'aisa-ai-card__head' + ( kind ? ' aisa-ai-card__head--' + kind : '' ) }, [
			iconName ? icon( iconName, 15, 2.4 ) : null,
			el( 'h3', { text: title, dir: 'auto' } ),
			small ? el( 'small', { text: small } ) : null
		] );
	}

	/** One line of a card: an icon tile, text with an optional second line, an optional tag. */
	function row( kind, iconName, main, meta, tag, attrs ) {
		var mainNode = el( 'span', { class: 'aisa-ai-main', dir: 'auto' }, [ 'string' === typeof main ? document.createTextNode( main ) : main, meta ? el( 'small', { text: meta, dir: 'auto' } ) : null ] );
		return el( 'li', attrs || {}, [ el( 'span', { class: 'aisa-ai-ic' + ( kind ? ' aisa-ai-ic--' + kind : '' ) }, [ icon( iconName, 15 ) ] ), mainNode, tag ? el( 'span', { class: 'aisa-ai-tag', text: tag } ) : null ] );
	}

	/** The working steps card: [ [ 'done'|'run'|'wait', text ], … ]. */
	function steps( items ) {
		var box = el( 'div', { class: 'aisa-ai-steps' } );
		items.forEach( function ( item ) {
			var marker = 'done' === item[ 0 ] ? el( 'span', { class: 'aisa-ai-tick' }, [ icon( 'check', 11, 3 ) ] ) : el( 'span', { class: 'run' === item[ 0 ] ? 'aisa-ai-spin' : 'aisa-ai-wait' } );
			box.appendChild( el( 'div', { class: 'aisa-ai-step aisa-ai-step--' + item[ 0 ] }, [ marker, el( 'span', { text: item[ 1 ] } ) ] ) );
		} );
		return el( 'div', { class: 'aisa-ai-card', role: 'status' }, [ box ] );
	}

	/** Grows a textarea with its text, up to max pixels. */
	function autoGrow( textarea, max ) {
		if ( ! textarea ) { return; }
		textarea.style.height = 'auto';
		textarea.style.height = Math.min( textarea.scrollHeight, max || 160 ) + 'px';
	}

	window.AISAAutoPilotDock = { icon: icon, el: el, hydrate: hydrate, cardHead: cardHead, row: row, steps: steps, autoGrow: autoGrow };
} )();
