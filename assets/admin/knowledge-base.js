/* global AISAAutoPilotKB */
( function () {
	'use strict';

	var cfg = window.AISAAutoPilotKB || {};
	var indexing = false;
	var currentOffset = 0;
	var currentPage = 1;
	var activeFilters = { post_types: [], categories: [] };

	// Friendly labels for auto content-type slugs (embedded server-side).
	var ctLabels = readJson( 'sp-kb-ctype-labels' );
	var T = readJson( 'sp-kb-i18n' );

	function readJson( id ) {
		try {
			var node = document.getElementById( id );
			return node ? ( JSON.parse( node.textContent || '{}' ) || {} ) : {};
		} catch ( e ) { return {}; }
	}

	function t( key, fallback ) { return T[ key ] || fallback || key; }

	function $( id ) { return document.getElementById( id ); }

	function cols() {
		var wrap = $( 'sp-kb-rows' );
		return wrap ? ( wrap.getAttribute( 'data-cols' ) || '1fr' ) : '1fr';
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

	function api( path, body ) {
		return fetch( cfg.restUrl + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce,
			},
			body: JSON.stringify( body || {} ),
		} ).then( function ( r ) {
			return r.json().then( function ( data ) { return { status: r.status, data: data }; } );
		} );
	}

	function apiGet( path ) {
		return fetch( cfg.restUrl + path, {
			method: 'GET',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.nonce },
		} ).then( function ( r ) {
			return r.json().then( function ( data ) { return { status: r.status, data: data }; } );
		} );
	}

	/* ── AI-feeling messages ── */
	function randomAiMsg() {
		var pool = [ t( 'reading' ), t( 'analysing' ), t( 'summarising' ), t( 'keywords' ), t( 'organising' ) ];
		return pool[ Math.floor( Math.random() * pool.length ) ];
	}

	function randomDoneMsg() { return t( 'done' ); }

	/* ── Progress Log ── */
	function addLogEntry( item ) {
		var log = $( 'sp-kb-progress-log' );
		if ( ! log ) { return; }

		var icon = item.status === 'indexed' ? '✓' : ( item.status === 'skipped' ? '–' : '✕' );
		var entry = el( 'div', { style: 'display:flex;gap:8px;align-items:baseline' }, [
			el( 'span', { style: 'flex:0 0 auto;color:' + ( item.status === 'indexed' ? '#059669' : ( item.status === 'skipped' ? '#9ca3af' : '#dc2626' ) ), text: icon } ),
			el( 'span', { style: 'font-weight:600;color:#1d2327', text: item.title } ),
			el( 'span', { style: 'min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap', text: item.message } ),
		] );

		log.appendChild( entry );
		log.scrollTop = log.scrollHeight;

		// Keep max 50 entries visible.
		while ( log.children.length > 50 ) {
			log.removeChild( log.firstChild );
		}
	}

	function setProgress( percent, text ) {
		var bar = $( 'sp-kb-progress-bar' );
		var pct = $( 'sp-kb-progress-percent' );
		var txt = $( 'sp-kb-progress-text' );

		if ( bar ) { bar.style.width = percent + '%'; }
		if ( pct ) { pct.textContent = percent + '%'; }
		if ( txt ) { txt.textContent = text; }
	}

	/* ── Indexing Flow ── */
	function startIndexing( fullReindex, filters ) {
		if ( indexing ) { return; }
		indexing = true;
		currentOffset = 0;
		activeFilters = filters || { post_types: [], categories: [] };

		var progress = $( 'sp-kb-progress' );
		var startBtn = $( 'sp-kb-start-index' );
		var reindexBtn = $( 'sp-kb-reindex' );
		var log = $( 'sp-kb-progress-log' );

		if ( progress ) { progress.hidden = false; }
		if ( log ) { log.textContent = ''; }
		if ( startBtn ) { startBtn.disabled = true; }
		if ( reindexBtn ) { reindexBtn.disabled = true; }

		setProgress( 0, randomAiMsg() );

		var chain = Promise.resolve();

		if ( fullReindex ) {
			chain = chain.then( function () {
				return api( 'kb/truncate', {} );
			} );
		}

		chain
			.then( function () { return processBatch(); } )
			.catch( function ( err ) {
				setProgress( 0, t( 'failed' ) + ' ' + ( err.message || '' ) );
			} )
			.finally( function () {
				indexing = false;
				if ( startBtn ) { startBtn.disabled = false; }
				if ( reindexBtn ) { reindexBtn.disabled = false; }
				loadTable( 1 );
				loadStats();
			} );
	}

	function processBatch() {
		return api( 'kb/index/batch', {
			offset: currentOffset,
			post_types: activeFilters.post_types || [],
			categories: activeFilters.categories || [],
		} )
			.then( function ( res ) {
				if ( ! res.data.ok ) {
					setProgress( 0, res.data.message || t( 'failed' ) );
					return;
				}

				var data = res.data;
				currentOffset = data.offset;

				// Update progress bar.
				setProgress( data.percent, randomAiMsg() );

				// Log each item.
				( data.items || [] ).forEach( function ( item ) {
					addLogEntry( item );
				} );

				if ( data.done ) {
					setProgress( 100, randomDoneMsg() );
					return;
				}

				// Small delay for visual feedback.
				return new Promise( function ( resolve ) {
					setTimeout( resolve, 300 );
				} ).then( function () {
					return processBatch();
				} );
			} );
	}

	/* ── Load Stats ── */
	function loadStats() {
		apiGet( 'kb/stats' )
			.then( function ( res ) {
				if ( ! res.data.ok ) { return; }
				var numbers = document.querySelectorAll( '.sp-metric__num' );
				if ( numbers.length >= 1 ) { numbers[ 0 ].textContent = res.data.stats.total.toLocaleString(); }
			} )
			.catch( function () {} );
	}

	/* ── Load Table ── */
	function loadTable( page ) {
		currentPage = page || 1;
		var search = $( 'sp-kb-search' ) ? $( 'sp-kb-search' ).value : '';
		var typeFilter = $( 'sp-kb-type-filter' ) ? $( 'sp-kb-type-filter' ).value : '';
		var ctypeFilter = $( 'sp-kb-ctype-filter' ) ? $( 'sp-kb-ctype-filter' ).value : '';

		var params = 'page=' + currentPage;
		if ( search ) { params += '&search=' + encodeURIComponent( search ); }
		if ( typeFilter ) { params += '&post_type=' + encodeURIComponent( typeFilter ); }
		if ( ctypeFilter ) { params += '&content_type=' + encodeURIComponent( ctypeFilter ); }

		var wrap = $( 'sp-kb-rows' );
		if ( wrap ) {
			wrap.textContent = '';
			wrap.appendChild( el( 'div', { class: 'sp-empty', text: t( 'loading' ) } ) );
		}

		apiGet( 'kb/list?' + params )
			.then( function ( res ) {
				if ( ! res.data.ok ) { return; }
				renderTable( res.data.items, res.data.page, res.data.pages );
			} )
			.catch( function () {
				if ( wrap ) {
					wrap.textContent = '';
					wrap.appendChild( el( 'div', { class: 'sp-empty', text: t( 'loadFailed' ) } ) );
				}
			} );
	}

	function renderTable( items, page, pages ) {
		var wrap = $( 'sp-kb-rows' );
		if ( ! wrap ) { return; }

		wrap.textContent = '';

		if ( ! items || items.length === 0 ) {
			wrap.appendChild( el( 'div', { class: 'sp-empty' }, [
				el( 'span', { class: 'sp-empty__icon', text: '📭' } ),
				el( 'div', { text: t( 'empty' ) } ),
				el( 'div', { class: 'sp-help', text: t( 'emptyHint' ) } ),
			] ) );
			renderPagination( 1, 1 );
			return;
		}

		items.forEach( function ( item ) {
			var typeLabel = item.content_type ? ( ctLabels[ item.content_type ] || item.content_type ) : item.post_type;
			var remove = el( 'button', {
				type: 'button',
				class: 'sp-btn--icon-sm sp-kb-delete',
				'data-post-id': item.post_id,
				text: '✕',
				title: t( 'remove' ),
				'aria-label': t( 'remove' ),
			} );

			wrap.appendChild( el( 'div', { class: 'sp-tr', style: '--sp-cols:' + cols() }, [
				el( 'span', { class: 'sp-td sp-td--strong sp-td--clip', title: item.summary || '', text: item.title || '—' } ),
				el( 'span', { class: 'sp-td sp-td--muted sp-td--clip', text: typeLabel } ),
				el( 'span', { class: 'sp-td' }, [
					el( 'span', { class: 'sp-pill sp-pill--ok sp-pill--sm', text: t( 'indexed' ) } ),
				] ),
				el( 'span', { class: 'sp-td sp-td--muted sp-td--nowrap', text: item.updated_at ? item.updated_at.substring( 0, 10 ) : '—' } ),
				el( 'span', { class: 'sp-td' }, [ remove ] ),
			] ) );
		} );

		renderPagination( page, pages );
	}

	function renderPagination( page, pages ) {
		var wrap = $( 'sp-kb-pagination' );
		if ( ! wrap ) { return; }
		wrap.textContent = '';

		if ( pages <= 1 ) { return; }

		if ( page > 1 ) {
			var prev = el( 'button', { type: 'button', class: 'sp-btn sp-btn--ghost sp-btn--xs', text: '←' } );
			prev.addEventListener( 'click', function () { loadTable( page - 1 ); } );
			wrap.appendChild( prev );
		}

		wrap.appendChild( el( 'span', {
			class: 'sp-help',
			text: String( t( 'page' ) ).replace( '%1$d', page ).replace( '%2$d', pages ),
		} ) );

		if ( page < pages ) {
			var next = el( 'button', { type: 'button', class: 'sp-btn sp-btn--ghost sp-btn--xs', text: '→' } );
			next.addEventListener( 'click', function () { loadTable( page + 1 ); } );
			wrap.appendChild( next );
		}
	}

	/* ── Delete KB entry ── */
	function onDelete( e ) {
		var btn = e.target.closest( '.sp-kb-delete' );
		if ( ! btn ) { return; }

		var postId = parseInt( btn.getAttribute( 'data-post-id' ), 10 );
		if ( ! postId ) { return; }

		if ( ! window.confirm( t( 'confirmOne' ) ) ) { return; }

		btn.disabled = true;
		btn.textContent = '…';

		api( 'kb/delete', { post_id: postId } )
			.then( function ( res ) {
				if ( res.data.ok ) {
					loadTable( currentPage );
					loadStats();
				} else {
					btn.disabled = false;
					btn.textContent = '✕';
					window.alert( res.data.message || t( 'failed' ) );
				}
			} )
			.catch( function () {
				btn.disabled = false;
				btn.textContent = '✕';
			} );
	}

	/* ── Pre-index Picker Modal ── */
	function openModal() {
		var modal = $( 'sp-kb-modal' );
		if ( modal ) { modal.hidden = false; }
	}

	function closeModal() {
		var modal = $( 'sp-kb-modal' );
		if ( modal ) { modal.hidden = true; }
	}

	function collectFilters() {
		var types = Array.prototype.map.call(
			document.querySelectorAll( '.sp-kb-type-opt:checked' ),
			function ( n ) { return n.value; }
		);
		var catAll = $( 'sp-kb-cat-all' );
		var cats = [];
		if ( catAll && ! catAll.checked ) {
			cats = Array.prototype.map.call(
				document.querySelectorAll( '.sp-kb-cat-opt:checked' ),
				function ( n ) { return parseInt( n.value, 10 ); }
			);
		}
		return { post_types: types, categories: cats };
	}

	function initModal() {
		var modal = $( 'sp-kb-modal' );
		if ( ! modal ) { return; }

		// Close on backdrop / close buttons.
		Array.prototype.forEach.call( modal.querySelectorAll( '[data-close]' ), function ( n ) {
			n.addEventListener( 'click', closeModal );
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && ! modal.hidden ) { closeModal(); }
		} );

		// "All categories" toggles the category list.
		var catAll = $( 'sp-kb-cat-all' );
		var catList = $( 'sp-kb-cat-list' );
		if ( catAll && catList ) {
			catAll.addEventListener( 'change', function () {
				catList.hidden = catAll.checked;
			} );
		}

		// The dialog's own backdrop is the modal element itself.
		modal.addEventListener( 'click', function ( e ) { if ( e.target === modal ) { closeModal(); } } );

		var startBtn = $( 'sp-kb-modal-start' );
		if ( startBtn ) {
			startBtn.addEventListener( 'click', function () {
				var filters = collectFilters();
				if ( ! filters.post_types.length ) {
					window.alert( t( 'pickOneType' ) );
					return;
				}
				closeModal();
				startIndexing( false, filters );
			} );
		}
	}

	/* ── Content Gaps ── */
	function loadGaps() {
		apiGet( 'kb/gaps' ).then( function ( res ) {
			if ( ! res.data.ok ) { return; }
			renderGaps( res.data.items || [] );
		} ).catch( function () {} );
	}

	function renderGaps( items ) {
		var wrap = $( 'sp-kb-gaps' );
		var list = $( 'sp-kb-gaps-list' );
		var count = $( 'sp-kb-gaps-count' );
		if ( ! wrap || ! list ) { return; }

		if ( ! items.length ) { wrap.hidden = true; return; }

		wrap.hidden = false;
		if ( count ) { count.textContent = items.length; }
		list.textContent = '';

		items.forEach( function ( g ) {
			var row = el( 'div', { class: 'sp-notice sp-notice--warn' }, [
				el( 'span', { class: 'sp-notice__dot' } ),
				el( 'span', { class: 'sp-notice__body', text: g.question } ),
				el( 'span', { class: 'sp-tag sp-tag--warn', text: '×' + ( g.count || 1 ) } ),
			] );
			var dismiss = el( 'button', { type: 'button', class: 'sp-btn--icon-sm', title: t( 'dismiss' ), 'aria-label': t( 'dismiss' ), text: '✕' } );
			dismiss.addEventListener( 'click', function () {
				row.style.opacity = '0.4';
				api( 'kb/gaps/dismiss', { hash: g.hash } ).then( loadGaps );
			} );
			row.appendChild( dismiss );
			list.appendChild( row );
		} );
	}

	/* ── Init ── */
	function init() {
		var startBtn = $( 'sp-kb-start-index' );
		var reindexBtn = $( 'sp-kb-reindex' );
		var searchInput = $( 'sp-kb-search' );
		var typeFilter = $( 'sp-kb-type-filter' );
		var ctypeFilter = $( 'sp-kb-ctype-filter' );
		var rows = $( 'sp-kb-rows' );

		initModal();

		if ( startBtn ) {
			startBtn.addEventListener( 'click', openModal );
		}
		if ( reindexBtn ) {
			reindexBtn.addEventListener( 'click', function () {
				if ( window.confirm( t( 'confirmAll' ) ) ) {
					startIndexing( true, { post_types: [], categories: [] } );
				}
			} );
		}

		// Debounced search.
		var searchTimer = null;
		if ( searchInput ) {
			searchInput.addEventListener( 'input', function () {
				clearTimeout( searchTimer );
				searchTimer = setTimeout( function () { loadTable( 1 ); }, 400 );
			} );
		}

		if ( typeFilter ) {
			typeFilter.addEventListener( 'change', function () { loadTable( 1 ); } );
		}
		if ( ctypeFilter ) {
			ctypeFilter.addEventListener( 'change', function () { loadTable( 1 ); } );
		}

		if ( rows ) {
			rows.addEventListener( 'click', onDelete );
		}

		var gapsClear = $( 'sp-kb-gaps-clear' );
		if ( gapsClear ) {
			gapsClear.addEventListener( 'click', function () {
				if ( window.confirm( t( 'confirmGaps' ) ) ) {
					api( 'kb/gaps/dismiss', { hash: '' } ).then( loadGaps );
				}
			} );
		}

		// Load initial data.
		loadTable( 1 );
		loadGaps();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
