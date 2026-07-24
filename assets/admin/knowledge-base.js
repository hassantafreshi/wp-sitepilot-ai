/* global SitePilotKB */
( function () {
	'use strict';

	var cfg = window.SitePilotKB || {};
	var indexing = false;
	var currentOffset = 0;
	var currentPage = 1;
	var activeFilters = { post_types: [], categories: [] };

	// Friendly labels for auto content-type slugs (embedded server-side).
	var ctLabels = {};
	try {
		var labelNode = document.getElementById( 'sp-kb-ctype-labels' );
		if ( labelNode ) { ctLabels = JSON.parse( labelNode.textContent || '{}' ); }
	} catch ( e ) { ctLabels = {}; }

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
		return api( 'kb/index/batch', {
			offset: currentOffset,
			post_types: activeFilters.post_types || [],
			categories: activeFilters.categories || [],
		} )
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
		var ctypeFilter = $( 'sp-kb-ctype-filter' ) ? $( 'sp-kb-ctype-filter' ).value : '';

		var params = 'page=' + currentPage;
		if ( search ) { params += '&search=' + encodeURIComponent( search ); }
		if ( typeFilter ) { params += '&post_type=' + encodeURIComponent( typeFilter ); }
		if ( ctypeFilter ) { params += '&content_type=' + encodeURIComponent( ctypeFilter ); }

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

			var typeCell = [ el( 'span', { class: typeClass, text: item.post_type } ) ];
			if ( item.content_type ) {
				var ctLabel = ctLabels[ item.content_type ] || item.content_type;
				typeCell.push( el( 'span', {
					class: 'sp-kb-ctype-badge sp-kb-ctype-badge--' + item.content_type,
					text: ctLabel,
				} ) );
			}

			var row = el( 'tr', {}, [
				el( 'td', { class: 'sp-kb-cell-title' }, [
					el( 'span', { text: item.title || '—' } ),
					el( 'span', { class: 'sp-kb-post-id', text: '#' + item.post_id } ),
				] ),
				el( 'td', {}, typeCell ),
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

	/* ── Pre-index Picker Modal ── */
	function openModal() {
		var modal = $( 'sp-kb-modal' );
		if ( modal ) { modal.hidden = false; document.body.classList.add( 'sp-kb-modal-open' ); }
	}

	function closeModal() {
		var modal = $( 'sp-kb-modal' );
		if ( modal ) { modal.hidden = true; document.body.classList.remove( 'sp-kb-modal-open' ); }
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

		var startBtn = $( 'sp-kb-modal-start' );
		if ( startBtn ) {
			startBtn.addEventListener( 'click', function () {
				var filters = collectFilters();
				if ( ! filters.post_types.length ) {
					alert( 'Please select at least one content type to index.' );
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
			var row = el( 'div', { class: 'sp-kb-gap' }, [
				el( 'span', { class: 'sp-kb-gap__q', text: g.question } ),
				el( 'span', { class: 'sp-kb-gap__count', text: '×' + ( g.count || 1 ) } ),
			] );
			var dismiss = el( 'button', { class: 'sp-kb-gap__dismiss', title: 'Dismiss', text: '✕' } );
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
		var tbody = $( 'sp-kb-table-body' );

		initModal();

		if ( startBtn ) {
			startBtn.addEventListener( 'click', openModal );
		}
		if ( reindexBtn ) {
			reindexBtn.addEventListener( 'click', function () {
				if ( confirm( 'This will clear all indexed data and re-index everything. Continue?' ) ) {
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

		if ( tbody ) {
			tbody.addEventListener( 'click', onDelete );
		}

		var gapsClear = $( 'sp-kb-gaps-clear' );
		if ( gapsClear ) {
			gapsClear.addEventListener( 'click', function () {
				if ( confirm( 'Clear all logged questions?' ) ) {
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
