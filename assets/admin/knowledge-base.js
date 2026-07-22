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
		'🔗 Connecting related topics…',
		'📊 Structuring the data…',
	];

	var aiDoneMessages = [
		'✅ All content indexed! SitePilot now understands your site.',
		'🎉 Indexing complete! Your knowledge base is ready.',
		'✨ Done! SitePilot can now answer questions about your content.',
	];

	function randomAiMsg() {
		return aiMessages[ Math.floor( Math.random() * aiMessages.length ) ];
	}

	function randomDoneMsg() {
		return aiDoneMessages[ Math.floor( Math.random() * aiDoneMessages.length ) ];
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

		var chain = Promise.resolve();

		if ( fullReindex ) {
			chain = chain.then( function () {
				return api( 'kb/truncate', {} );
			} );
		}

		chain
			.then( function () { return processBatch(); } )
			.catch( function ( err ) {
				setProgress( 0, '❌ Error: ' + ( err.message || 'Unknown error' ) );
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
		return api( 'kb/index/batch', { offset: currentOffset } )
			.then( function ( res ) {
				if ( ! res.data.ok ) {
					setProgress( 0, '❌ ' + ( res.data.message || 'Indexing failed.' ) );
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
				var stats = res.data.stats;
				var numbers = document.querySelectorAll( '.sitepilot-kb-stat__number' );
				if ( numbers.length >= 1 ) { numbers[ 0 ].textContent = stats.total.toLocaleString(); }
			} )
			.catch( function () {} );
	}

	/* ── Load Table ── */
	function loadTable( page ) {
		currentPage = page || 1;
		var search = $( 'sp-kb-search' ) ? $( 'sp-kb-search' ).value : '';
		var typeFilter = $( 'sp-kb-type-filter' ) ? $( 'sp-kb-type-filter' ).value : '';

		var params = 'page=' + currentPage;
		if ( search ) { params += '&search=' + encodeURIComponent( search ); }
		if ( typeFilter ) { params += '&post_type=' + encodeURIComponent( typeFilter ); }

		var tbody = $( 'sp-kb-table-body' );
		if ( tbody ) {
			tbody.innerHTML = '<tr><td colspan="6"><div class="sp-kb-empty"><div class="sp-kb-empty__icon">⏳</div><div class="sp-kb-empty__text">Loading…</div></div></td></tr>';
		}

		apiGet( 'kb/list?' + params )
			.then( function ( res ) {
				if ( ! res.data.ok ) { return; }
				renderTable( res.data.items, res.data.page, res.data.pages );
			} )
			.catch( function () {
				if ( tbody ) {
					tbody.innerHTML = '<tr><td colspan="6"><div class="sp-kb-empty"><div class="sp-kb-empty__icon">❌</div><div class="sp-kb-empty__text">Failed to load data.</div></div></td></tr>';
				}
			} );
	}

	function renderTable( items, page, pages ) {
		var tbody = $( 'sp-kb-table-body' );
		if ( ! tbody ) { return; }

		tbody.textContent = '';

		if ( ! items || items.length === 0 ) {
			tbody.appendChild(
				el( 'tr', {}, [
					el( 'td', { colspan: '6' }, [
						el( 'div', { class: 'sp-kb-empty' }, [
							el( 'div', { class: 'sp-kb-empty__icon', text: '📭' } ),
							el( 'div', { class: 'sp-kb-empty__text', text: 'No indexed content yet.' } ),
							el( 'div', { class: 'sp-kb-empty__hint', text: 'Click "Start Indexing" above to build your knowledge base.' } ),
						] ),
					] ),
				] )
			);
			return;
		}

		items.forEach( function ( item ) {
			// Render keywords as tags.
			var keywordsHtml = '';
			if ( item.keywords ) {
				item.keywords.split( ',' ).slice( 0, 5 ).forEach( function ( kw ) {
					kw = kw.trim();
					if ( kw ) {
						keywordsHtml += '<span class="sp-kb-keyword-tag">' + escapeHtml( kw ) + '</span>';
					}
				} );
			}

			var typeClass = 'sp-kb-type-badge';
			if ( item.post_type === 'post' ) { typeClass += ' sp-kb-type-badge--post'; }
			else if ( item.post_type === 'page' ) { typeClass += ' sp-kb-type-badge--page'; }

			var row = el( 'tr', {}, [
				el( 'td', { class: 'sp-kb-cell-title' }, [
					el( 'span', { text: item.title || '—' } ),
					el( 'span', { class: 'sp-kb-post-id', text: '#' + item.post_id } ),
				] ),
				el( 'td', {}, [
					el( 'span', { class: typeClass, text: item.post_type } ),
				] ),
				el( 'td', { class: 'sp-kb-cell-summary', text: item.summary ? item.summary.substring( 0, 120 ) + ( item.summary.length > 120 ? '…' : '' ) : '—' } ),
				el( 'td', { class: 'sp-kb-cell-keywords', html: keywordsHtml || '—' } ),
				el( 'td', { text: item.updated_at ? item.updated_at.substring( 0, 10 ) : '—' } ),
				el( 'td', {}, [
					el( 'button', {
						class: 'button button-small sp-kb-delete',
						'data-post-id': item.post_id,
						text: '✕',
						title: 'Remove from knowledge base',
					} ),
				] ),
			] );
			tbody.appendChild( row );
		} );

		renderPagination( page, pages );
	}

	function escapeHtml( str ) {
		var div = document.createElement( 'div' );
		div.textContent = str;
		return div.innerHTML;
	}

	function renderPagination( page, pages ) {
		var wrap = $( 'sp-kb-pagination' );
		if ( ! wrap ) { return; }
		wrap.textContent = '';

		if ( pages <= 1 ) { return; }

		if ( page > 1 ) {
			var prev = el( 'button', { class: 'button button-small', text: '←' } );
			prev.addEventListener( 'click', function () { loadTable( page - 1 ); } );
			wrap.appendChild( prev );
		}

		wrap.appendChild( el( 'span', { class: 'sp-kb-page-info', text: 'Page ' + page + ' of ' + pages } ) );

		if ( page < pages ) {
			var next = el( 'button', { class: 'button button-small', text: '→' } );
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
					btn.textContent = '✕';
					alert( res.data.message || 'Failed to remove.' );
				}
			} )
			.catch( function () {
				btn.disabled = false;
				btn.textContent = '✕';
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
