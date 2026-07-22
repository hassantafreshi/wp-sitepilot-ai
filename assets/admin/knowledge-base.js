/* global SitePilotKB */
( function () {
	'use strict';

	var cfg = window.SitePilotKB || {};
	var indexing = false;
	var currentOffset = 0;
	var currentPage = 1;

	function $( id ) { return document.getElementById( id ); }

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
	var aiMessages = [
		'🤖 Reading your content…',
		'📖 Analyzing the text…',
		'🧠 Building understanding…',
		'✨ Summarizing key points…',
		'🔍 Extracting keywords…',
		'📝 Creating knowledge entry…',
		'🗂️ Organizing information…',
		'💡 Understanding context…',
	];

	function randomAiMsg() {
		return aiMessages[ Math.floor( Math.random() * aiMessages.length ) ];
	}

	/* ── Progress Log ── */
	function addLogEntry( item ) {
		var log = $( 'sp-kb-progress-log' );
		if ( ! log ) { return; }

		var icon = item.status === 'indexed' ? '✅' : ( item.status === 'skipped' ? '⏭️' : '❌' );
		var entry = el( 'div', { class: 'sp-kb-log-entry sp-kb-log-entry--' + item.status }, [
			el( 'span', { class: 'sp-kb-log-icon', text: icon } ),
			el( 'span', { class: 'sp-kb-log-title', text: item.title } ),
			el( 'span', { class: 'sp-kb-log-msg', text: item.message } ),
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
	function startIndexing( fullReindex ) {
		if ( indexing ) { return; }
		indexing = true;
		currentOffset = 0;

		var progress = $( 'sp-kb-progress' );
		var startBtn = $( 'sp-kb-start-index' );
		var reindexBtn = $( 'sp-kb-reindex' );
		var log = $( 'sp-kb-progress-log' );

		if ( progress ) { progress.hidden = false; }
		if ( log ) { log.textContent = ''; }
		if ( startBtn ) { startBtn.disabled = true; }
		if ( reindexBtn ) { reindexBtn.disabled = true; }

		setProgress( 0, randomAiMsg() );

		if ( fullReindex ) {
			api( 'kb/truncate', {} ).then( function () {
				runBatch();
			} );
		} else {
			runBatch();
		}
	}

	function runBatch() {
		setProgress(
			Math.min( 99, currentOffset > 0 ? Math.round( ( currentOffset / ( cfg.totalPosts || 1 ) ) * 100 ) : 0 ),
			randomAiMsg()
		);

		api( 'kb/index/batch', { offset: currentOffset } )
			.then( function ( res ) {
				if ( ! res.data.ok ) {
					setProgress( 0, res.data.message || 'Error during indexing.' );
					finishIndexing( false );
					return;
				}

				var result = res.data;

				// Log each item.
				( result.items || [] ).forEach( function ( item ) {
					addLogEntry( item );
				} );

				currentOffset = result.offset || 0;
				var percent = result.percent || 0;

				if ( result.done ) {
					setProgress( 100, '🎉 Indexing complete! ' + ( result.total || 0 ) + ' items processed.' );
					finishIndexing( true );
					loadTable( 1 );
					return;
				}

				// Continue with next batch after a small delay (feels more natural).
				setTimeout( function () {
					runBatch();
				}, 300 );
			} )
			.catch( function () {
				setProgress( 0, 'Network error. Please try again.' );
				finishIndexing( false );
			} );
	}

	function finishIndexing( success ) {
		indexing = false;
		var startBtn = $( 'sp-kb-start-index' );
		var reindexBtn = $( 'sp-kb-reindex' );
		if ( startBtn ) { startBtn.disabled = false; }
		if ( reindexBtn ) { reindexBtn.disabled = false; }

		if ( success ) {
			// Refresh stats.
			loadStats();
		}
	}

	/* ── Stats ── */
	function loadStats() {
		apiGet( 'kb/stats' ).then( function ( res ) {
			if ( ! res.data.ok ) { return; }
			var stats = res.data.stats;
			var nums = document.querySelectorAll( '.sitepilot-kb-stat__number' );
			if ( nums[ 0 ] ) { nums[ 0 ].textContent = ( stats.total || 0 ).toLocaleString(); }
			// Last updated.
			if ( nums[ 2 ] && stats.last_indexed ) {
				nums[ 2 ].textContent = stats.last_indexed;
			}
		} );
	}

	/* ── KB Table ── */
	function loadTable( page ) {
		currentPage = page || 1;
		var search = ( $( 'sp-kb-search' ) || {} ).value || '';
		var typeFilter = ( $( 'sp-kb-type-filter' ) || {} ).value || '';

		apiGet( 'kb/list?page=' + currentPage + '&search=' + encodeURIComponent( search ) + '&post_type=' + encodeURIComponent( typeFilter ) )
			.then( function ( res ) {
				if ( ! res.data.ok ) { return; }
				renderTable( res.data.items || [], res.data.total || 0, res.data.page || 1, res.data.pages || 1 );
			} );
	}

	function renderTable( items, total, page, pages ) {
		var tbody = $( 'sp-kb-table-body' );
		if ( ! tbody ) { return; }
		tbody.textContent = '';

		if ( items.length === 0 ) {
			tbody.appendChild( el( 'tr', {}, [
				el( 'td', { colspan: '6', class: 'text-center', text: 'No indexed content yet. Click "Start Indexing" above.' } ),
			] ) );
			return;
		}

		items.forEach( function ( item ) {
			var summary = ( item.summary || '' ).length > 120
				? item.summary.substring( 0, 120 ) + '…'
				: ( item.summary || '' );

			var row = el( 'tr', {}, [
				el( 'td', { class: 'sp-kb-cell-title' }, [
					el( 'strong', { text: item.title || '(no title)' } ),
					el( 'span', { class: 'sp-kb-post-id', text: ' #' + item.post_id } ),
				] ),
				el( 'td', {}, [
					el( 'span', { class: 'sitepilot-pill sitepilot-pill--info', text: item.post_type } ),
				] ),
				el( 'td', { class: 'sp-kb-cell-summary', text: summary } ),
				el( 'td', { class: 'sp-kb-cell-keywords', text: item.keywords || '—' } ),
				el( 'td', { text: item.updated_at ? item.updated_at.substring( 0, 10 ) : '—' } ),
				el( 'td', {}, [
					el( 'button', {
						class: 'button button-small sp-kb-delete',
						'data-post-id': item.post_id,
						text: 'Remove',
					} ),
				] ),
			] );
			tbody.appendChild( row );
		} );

		// Pagination.
		renderPagination( page, pages );
	}

	function renderPagination( page, pages ) {
		var wrap = $( 'sp-kb-pagination' );
		if ( ! wrap ) { return; }
		wrap.textContent = '';

		if ( pages <= 1 ) { return; }

		if ( page > 1 ) {
			var prev = el( 'button', { class: 'button button-small', text: '← Prev' } );
			prev.addEventListener( 'click', function () { loadTable( page - 1 ); } );
			wrap.appendChild( prev );
		}

		wrap.appendChild( el( 'span', { class: 'sp-kb-page-info', text: ' Page ' + page + ' of ' + pages + ' ' } ) );

		if ( page < pages ) {
			var next = el( 'button', { class: 'button button-small', text: 'Next →' } );
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

		if ( ! confirm( 'Remove this item from the knowledge base?' ) ) { return; }

		btn.disabled = true;
		btn.textContent = '…';

		api( 'kb/delete', { post_id: postId } )
			.then( function ( res ) {
				if ( res.data.ok ) {
					loadTable( currentPage );
					loadStats();
				} else {
					btn.disabled = false;
					btn.textContent = 'Remove';
					alert( res.data.message || 'Failed to remove.' );
				}
			} )
			.catch( function () {
				btn.disabled = false;
				btn.textContent = 'Remove';
			} );
	}

	/* ── Init ── */
	function init() {
		var startBtn = $( 'sp-kb-start-index' );
		var reindexBtn = $( 'sp-kb-reindex' );
		var searchInput = $( 'sp-kb-search' );
		var typeFilter = $( 'sp-kb-type-filter' );
		var tbody = $( 'sp-kb-table-body' );

		if ( startBtn ) {
			startBtn.addEventListener( 'click', function () { startIndexing( false ); } );
		}
		if ( reindexBtn ) {
			reindexBtn.addEventListener( 'click', function () {
				if ( confirm( 'This will clear all indexed data and re-index everything. Continue?' ) ) {
					startIndexing( true );
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

		if ( tbody ) {
			tbody.addEventListener( 'click', onDelete );
		}

		// Load initial table.
		loadTable( 1 );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
