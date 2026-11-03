/* global AISAAutoPilotKB */
( function () {
	'use strict';

	var cfg = window.AISAAutoPilotKB || {};
	var indexing = false;
	var currentOffset = 0;
	var currentPage = 1;
	var activeFilters = { post_types: [], categories: [] };
	var modalTrigger = null;
	var pausedRun = null;
	var latestPercent = 0;

	// Friendly labels for auto content-type slugs (embedded server-side).
	var ctLabels = readJson( 'sp-kb-ctype-labels' );
	var typeLabels = readJson( 'sp-kb-type-labels' );
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

	// With plain permalinks the REST root is "index.php?rest_route=/…", so a path's own query joins with "&".
	function restUrl( path ) {
		var base = cfg.restUrl || '';
		return -1 !== base.indexOf( '?' ) ? base + path.replace( '?', '&' ) : base + path;
	}

	function api( path, body ) {
		return fetch( restUrl( path ), {
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
		return fetch( restUrl( path ), {
			method: 'GET',
			credentials: 'same-origin',
			// WordPress honours _locale=user only for JSON requests, so server-built labels match the admin language.
			headers: { 'X-WP-Nonce': cfg.nonce, Accept: 'application/json' },
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
		var safePercent = Math.max( 0, Math.min( 100, Number( percent ) || 0 ) );

		latestPercent = safePercent;
		if ( bar ) { bar.style.width = safePercent + '%'; }
		if ( pct ) { pct.textContent = safePercent + '%'; }
		if ( txt ) { txt.textContent = text; }
	}

	function capError( data, fallback ) {
		var err = new Error( data.message || fallback || t( 'failed' ) );
		err.code = data.code || '';
		err.offset = Number.isInteger( data.offset ) ? data.offset : currentOffset;
		err.percent = Number.isFinite( Number( data.percent ) ) ? Number( data.percent ) : latestPercent;
		return err;
	}

	function applyBatchResult( data, onItem ) {
		if ( Number.isInteger( data.offset ) && data.offset >= 0 ) {
			currentOffset = data.offset;
		}

		setProgress( data.percent, randomAiMsg() );
		( data.items || [] ).forEach( function ( item ) {
			if ( onItem ) { onItem( item ); }
			addLogEntry( item );
		} );
	}

	/* ── Indexing Flow ── */
	function startIndexing( fullReindex, filters, resume ) {
		if ( indexing ) { return; }
		var resuming = !! resume && !! pausedRun;
		if ( resume && ! resuming ) { return; }

		indexing = true;
		var completed = false;
		var hasItemErrors = false;

		var progress = $( 'sp-kb-progress' );
		var startBtn = $( 'sp-kb-start-index' );
		var reindexBtn = $( 'sp-kb-reindex' );
		var headerReindex = $( 'sp-kb-header-reindex' );
		var log = $( 'sp-kb-progress-log' );
		var resumeBtn = $( 'sp-kb-resume' );

		if ( resuming ) {
			currentOffset = pausedRun.offset;
			activeFilters = pausedRun.filters;
			fullReindex = pausedRun.fullReindex;
			pausedRun = null;
		} else {
			currentOffset = 0;
			activeFilters = filters || { post_types: [], categories: [] };
			latestPercent = 0;
			pausedRun = null;
			if ( log ) { log.textContent = ''; }
		}

		if ( progress ) { progress.hidden = false; }
		if ( startBtn ) { startBtn.disabled = true; }
		if ( reindexBtn ) { reindexBtn.disabled = true; }
		if ( headerReindex ) { headerReindex.disabled = true; }
		if ( resumeBtn ) {
			resumeBtn.hidden = true;
			resumeBtn.disabled = true;
		}

		setProgress( resuming ? latestPercent : 0, resuming ? t( 'resuming' ) : randomAiMsg() );

		var chain = Promise.resolve();

		if ( fullReindex ) {
			chain = chain.then( function () {
				return api( 'kb/truncate', {} ).then( function ( res ) {
					if ( ! res.data.ok ) {
						var err = capError( res.data, t( 'failed' ) );
						err.fullReindex = 'usage_cap_reached' === err.code;
						throw err;
					}
				} );
			} );
		}

		chain
			.then( function () {
				return processBatch( function ( item ) {
					if ( item.status === 'error' ) {
						hasItemErrors = true;
					}
				} );
			} )
			.then( function () {
				completed = true;
				if ( hasItemErrors ) {
					setProgress( 100, t( 'doneWithErrors' ) );
				}
			} )
			.catch( function ( err ) {
				if ( err && 'usage_cap_reached' === err.code ) {
					currentOffset = Number.isInteger( err.offset ) && err.offset >= 0 ? err.offset : currentOffset;
					latestPercent = Number.isFinite( err.percent ) ? err.percent : latestPercent;
					pausedRun = {
						offset: currentOffset,
						filters: activeFilters,
						fullReindex: !! err.fullReindex,
					};
					setProgress( latestPercent, t( 'paused' ) + ' ' + ( err.message || '' ) );
					if ( resumeBtn ) {
						resumeBtn.hidden = false;
						resumeBtn.disabled = false;
					}
					return;
				}

				setProgress( latestPercent, t( 'failed' ) + ' ' + ( err.message || '' ) );
			} )
			.finally( function () {
				indexing = false;
				if ( startBtn ) { startBtn.disabled = false; }
				if ( reindexBtn ) { reindexBtn.disabled = false; }
				if ( headerReindex ) { headerReindex.disabled = false; }
				if ( $( 'sp-kb-rows' ) ) { loadTable( 1 ); }
				if ( completed && ! hasItemErrors && document.querySelector( '[data-sp-kb-overview], [data-sp-kb-collections], [data-sp-kb-queue], [data-sp-kb-sync-sources]' ) ) {
					window.setTimeout( function () { window.location.reload(); }, 650 );
				} else {
					loadStats();
				}
			} );
	}

	function processBatch( onItem ) {
		return api( 'kb/index/batch', {
			offset: currentOffset,
			post_types: activeFilters.post_types || [],
			categories: activeFilters.categories || [],
		} )
			.then( function ( res ) {
				if ( ! res.data.ok ) {
					if ( 429 === res.status && 'usage_cap_reached' === res.data.code ) {
						applyBatchResult( res.data, onItem );
						throw capError( res.data, t( 'failed' ) );
					}
					throw new Error( res.data.message || t( 'failed' ) );
				}

				var data = res.data;
				applyBatchResult( data, onItem );

				if ( data.done ) {
					setProgress( 100, randomDoneMsg() );
					return;
				}

				// Small delay for visual feedback.
				return new Promise( function ( resolve ) {
					setTimeout( resolve, 300 );
				} ).then( function () {
					return processBatch( onItem );
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
		if ( wrap ) { showListLoading( wrap ); }

		apiGet( 'kb/list?' + params )
			.then( function ( res ) {
				// A refused request (an expired nonce, for example) is a failed load too, never an endless loading state.
				if ( ! res.data || ! res.data.ok ) { throw new Error( 'kb/list' ); }
				renderTable( res.data.items, res.data.page, res.data.pages );
			} )
			.catch( function () {
				if ( wrap ) { showListError( wrap ); }
			} );
	}

	// Skeleton rows while the list loads; only the note is announced to screen readers.
	function showListLoading( wrap ) {
		wrap.textContent = '';
		var box = el( 'div', { class: 'sp-kb-loading', 'aria-busy': 'true', 'data-sp-kb-list-loading': '' } );
		for ( var i = 0; i < 5; i++ ) {
			box.appendChild( el( 'div', { class: 'sp-kb-skeleton', 'aria-hidden': 'true' }, [ el( 'span' ), el( 'span' ), el( 'span' ), el( 'span' ) ] ) );
		}
		box.appendChild( el( 'p', { class: 'sp-kb-loading__note', role: 'status', text: t( 'readingIndex', 'Reading the index…' ) } ) );
		wrap.appendChild( box );
	}

	// A failed list request removes nothing, so say that and retry the same page.
	function showListError( wrap ) {
		wrap.textContent = '';
		var box = el( 'div', { class: 'sp-kb-load-error', role: 'alert', 'data-sp-kb-list-error': '' } );
		box.appendChild( el( 'span', { class: 'sp-kb-load-error__icon', 'aria-hidden': 'true', html: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M12 8v5M12 17h.01M10.3 3.9 2.5 17.4a2 2 0 0 0 1.7 3h15.6a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>' } ) );
		box.appendChild( el( 'p', { class: 'sp-kb-load-error__title', text: t( 'listFailed', 'The list could not be loaded' ) } ) );
		box.appendChild( el( 'p', { class: 'sp-kb-load-error__text', text: t( 'listFailedBody', 'The request to your site failed. Nothing was removed from the index.' ) } ) );
		var retry = el( 'button', { type: 'button', class: 'sp-btn sp-btn--r8 sp-kb-load-error__retry', 'data-sp-kb-list-retry': '', text: t( 'tryAgain', 'Try again' ) } );
		retry.addEventListener( 'click', function () { loadTable( currentPage ); } );
		box.appendChild( retry );
		wrap.appendChild( box );
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
			var title = item.title || '—';
			var type = item.post_type ? ( typeLabels[ item.post_type ] || item.post_type ) : '—';
			var category = item.content_type ? ( ctLabels[ item.content_type ] || item.content_type ) : '—';
			var indexed = ! item.status || 'indexed' === item.status;
			var readAt = item.indexed_at && '1970' !== String( item.indexed_at ).substring( 0, 4 ) ? item.indexed_at : item.updated_at;
			var remove = el( 'button', {
				type: 'button',
				class: 'sp-btn sp-btn--ghost sp-btn--xs sp-kb-delete',
				'data-post-id': item.post_id,
				text: t( 'removeShort', 'Remove' ),
				'aria-label': String( t( 'removeNamed', 'Remove “%s” from the index' ) ).replace( '%s', title ),
			} );

			wrap.appendChild( el( 'div', { class: 'sp-tr sp-kb-entry', style: '--sp-cols:' + cols() }, [
				el( 'span', { class: 'sp-td sp-td--strong sp-td--clip', title: item.summary || '', text: title } ),
				el( 'span', { class: 'sp-td sp-td--muted sp-td--clip', text: type } ),
				el( 'span', { class: 'sp-td sp-td--muted sp-td--clip', text: category } ),
				el( 'span', { class: 'sp-td' }, [
					el( 'span', { class: 'sp-pill sp-pill--sm ' + ( indexed ? 'sp-pill--ok' : 'sp-pill--warn' ), text: indexed ? t( 'indexed' ) : t( 'pending' ) } ),
				] ),
				el( 'span', { class: 'sp-td sp-td--muted sp-td--nowrap', text: readAt ? String( readAt ).substring( 0, 16 ) : '—' } ),
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
			var prev = el( 'button', { type: 'button', class: 'sp-btn sp-btn--ghost sp-btn--xs', text: '←', 'aria-label': t( 'previousPage', 'Previous page' ) } );
			prev.addEventListener( 'click', function () { loadTable( page - 1 ); } );
			wrap.appendChild( prev );
		}

		wrap.appendChild( el( 'span', {
			class: 'sp-help',
			text: String( t( 'page' ) ).replace( '%1$d', page ).replace( '%2$d', pages ),
		} ) );

		if ( page < pages ) {
			var next = el( 'button', { type: 'button', class: 'sp-btn sp-btn--ghost sp-btn--xs', text: '→', 'aria-label': t( 'nextPage', 'Next page' ) } );
			next.addEventListener( 'click', function () { loadTable( page + 1 ); } );
			wrap.appendChild( next );
		}
	}

	/* ── Confirm: the shared accessible dialog, native confirm only as a fallback ── */
	function confirmAction( opts ) {
		var shell = window.AISAAutoPilotShell;
		if ( shell && typeof shell.confirm === 'function' ) { return shell.confirm( opts ); }
		return Promise.resolve( window.confirm( opts.title ) );
	}

	/* ── Delete KB entry ── */
	function onDelete( e ) {
		var btn = e.target.closest( '.sp-kb-delete' );
		if ( ! btn ) { return; }

		var postId = parseInt( btn.getAttribute( 'data-post-id' ), 10 );
		if ( ! postId ) { return; }

		confirmAction( { title: t( 'confirmOne' ), body: t( 'removeBody' ), confirmLabel: t( 'removeCta' ), danger: true } ).then( function ( yes ) {
			if ( yes ) { deleteEntry( btn, postId ); }
		} );
	}

	function deleteEntry( btn, postId ) {
		btn.disabled = true;
		btn.textContent = '…';

		api( 'kb/delete', { post_id: postId } )
			.then( function ( res ) {
				if ( res.data.ok ) {
					loadTable( currentPage );
					loadStats();
				} else {
					btn.disabled = false;
					btn.textContent = t( 'removeShort', 'Remove' );
					window.alert( res.data.message || t( 'failed' ) );
				}
			} )
			.catch( function () {
				btn.disabled = false;
				btn.textContent = t( 'removeShort', 'Remove' );
			} );
	}

	/* ── Pre-index Picker Modal ── */
	function openModal( selectedType, trigger ) {
		var modal = $( 'sp-kb-modal' );
		if ( ! modal ) { return; }

		selectedType = typeof selectedType === 'string' ? selectedType : '';
		modalTrigger = trigger || document.activeElement;
		Array.prototype.forEach.call( modal.querySelectorAll( '.sp-kb-type-opt' ), function ( input ) {
			input.checked = ! selectedType || input.value === selectedType;
		} );

		modal.hidden = false;
		window.setTimeout( function () {
			var first = modal.querySelector( '.sp-kb-type-opt' );
			if ( first ) { first.focus(); }
		}, 0 );
	}

	function closeModal() {
		var modal = $( 'sp-kb-modal' );
		if ( modal ) { modal.hidden = true; }
		var trigger = modalTrigger;
		modalTrigger = null;
		if ( trigger && typeof trigger.focus === 'function' ) {
			trigger.focus();
		}
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

	/* ── Content Gaps and saved answers ── */
	var gapsWindow = 'all';
	var formCount = 0;

	function toast( message ) {
		var shell = window.AISAAutoPilotShell;
		if ( shell && typeof shell.toast === 'function' ) { shell.toast( message ); }
	}

	function loadGaps() {
		return apiGet( 'kb/gaps?_locale=user&window=' + encodeURIComponent( gapsWindow ) ).then( function ( res ) {
			if ( ! res.data.ok ) { return; }
			renderGaps( res.data.items || [], res.data.total );
		} ).catch( function () {} );
	}

	/**
	 * An inline answer editor. A gap passes a fixed question; a saved answer or a new one edits both fields.
	 * Nothing is sent until the administrator presses Save answer.
	 */
	function answerForm( opts ) {
		formCount++;
		var form = el( 'form', { class: 'sp-kb-answer-form', 'data-sp-kb-answer-form': '', novalidate: 'novalidate' } );
		var questionInput = null;
		if ( opts.editQuestion ) {
			var questionId = 'sp-kb-answer-q-' + formCount;
			form.appendChild( el( 'label', { class: 'sp-kb-answer-form__label', for: questionId, text: t( 'answerQuestion', 'Question visitors ask' ) } ) );
			questionInput = el( 'input', { id: questionId, class: 'sp-input sp-input--sm', type: 'text', maxlength: '300', dir: 'auto', 'data-sp-kb-answer-question': '' } );
			questionInput.value = opts.question || '';
			form.appendChild( questionInput );
		}
		var answerId = 'sp-kb-answer-a-' + formCount;
		form.appendChild( el( 'label', { class: 'sp-kb-answer-form__label', for: answerId, text: t( 'answerLabel', 'Answer visitors get' ) } ) );
		var answer = el( 'textarea', { id: answerId, class: 'sp-textarea sp-kb-answer-form__text', rows: '4', maxlength: '2000', dir: 'auto', 'aria-describedby': answerId + '-hint', 'data-sp-kb-answer-text': '' } );
		answer.value = opts.answer || '';
		form.appendChild( answer );
		form.appendChild( el( 'p', { id: answerId + '-hint', class: 'sp-kb-answer-form__hint', text: t( 'answerHint', '' ) } ) );

		var save = el( 'button', { type: 'submit', class: 'sp-btn sp-btn--xs', 'data-sp-kb-answer-save': '', text: t( 'answerSave', 'Save answer' ) } );
		var cancel = el( 'button', { type: 'button', class: 'sp-btn sp-btn--ghost sp-btn--xs', text: t( 'cancel', 'Cancel' ) } );
		var status = el( 'p', { class: 'sp-kb-answer-form__status', role: 'status', 'aria-live': 'polite', 'data-sp-kb-answer-status': '' } );
		form.appendChild( el( 'div', { class: 'sp-kb-answer-form__actions' }, [ save, cancel, status ] ) );

		cancel.addEventListener( 'click', opts.onCancel );
		form.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				event.preventDefault();
				opts.onCancel();
			}
		} );
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			var values = { answer: answer.value.trim() };
			if ( questionInput ) {
				values.question = questionInput.value.trim();
				if ( ! values.question ) {
					status.textContent = t( 'answerNeedAsk', 'Enter the question first.' );
					questionInput.focus();
					return;
				}
			}
			if ( ! values.answer ) {
				status.textContent = t( 'answerNeedText', 'Write the answer first.' );
				answer.focus();
				return;
			}
			save.disabled = true;
			status.textContent = t( 'saving', 'Saving…' );
			opts.onSave( values ).then( function ( res ) {
				if ( res && res.data && res.data.ok ) { return; }
				save.disabled = false;
				status.textContent = ( res && res.data && res.data.message ) || t( 'answerFailed', 'Could not save the answer. Please try again.' );
			} ).catch( function () {
				save.disabled = false;
				status.textContent = t( 'answerFailed', 'Could not save the answer. Please try again.' );
			} );
		} );
		form.focusFirst = function () {
			( questionInput && ! questionInput.value ? questionInput : answer ).focus();
		};
		return form;
	}

	function renderGaps( items, total ) {
		var wrap = $( 'sp-kb-gaps' );
		var list = $( 'sp-kb-gaps-list' );
		var count = $( 'sp-kb-gaps-count' );
		var empty = $( 'sp-kb-gaps-empty' );
		var clear = $( 'sp-kb-gaps-clear' );
		if ( ! wrap || ! list ) { return; }

		list.textContent = '';
		if ( empty && ! empty.hasAttribute( 'data-all-text' ) ) {
			empty.setAttribute( 'data-all-text', empty.textContent );
		}
		if ( ! items.length ) {
			// The overview keeps the card visible with an honest empty line (mockup);
			// surfaces without that line still hide the card.
			wrap.hidden = ! empty;
			if ( empty ) {
				empty.hidden = false;
				empty.textContent = '7d' === gapsWindow ? t( 'gapsEmpty7d', 'No unanswered questions were asked in the last 7 days.' ) : empty.getAttribute( 'data-all-text' );
			}
			if ( count ) { count.hidden = true; }
			if ( clear ) { clear.hidden = true; }
			revealGapsFromHash( wrap );
			return;
		}

		wrap.hidden = false;
		if ( empty ) { empty.hidden = true; }
		if ( clear ) { clear.hidden = false; }
		if ( count ) { count.hidden = false; count.textContent = String( undefined !== total ? total : items.length ); }

		items.forEach( function ( g ) {
			var questionId = 'sp-kb-gap-q-' + g.hash;
			var badge = g.draft
				? el( 'span', { class: 'sp-kb-gap__count is-draft', text: t( 'draftReady', 'Draft ready' ) } )
				: el( 'span', { class: 'sp-kb-gap__count', text: g.asked_label || ( '×' + ( g.count || 1 ) ) } );
			var main = el( 'div', { class: 'sp-kb-gap__main' }, [
				el( 'div', { class: 'sp-kb-gap__top' }, [
					el( 'span', { class: 'sp-kb-gap__body', id: questionId, dir: 'auto', text: g.question } ),
					badge,
				] ),
				g.reason_label ? el( 'p', { class: 'sp-kb-gap__reason', text: g.reason_label } ) : null,
			] );
			var actions = el( 'div', { class: 'sp-kb-gap__actions' } );
			var row = el( 'div', { class: 'sp-kb-gap', 'data-sp-kb-gap': g.hash }, [ main, actions ] );

			// Write it only opens Content Studio with an editable brief; an existing draft opens in the editor.
			var write = g.draft
				? el( 'a', { class: 'sp-btn sp-btn--xs', href: g.draft.url, 'data-sp-kb-gap-draft': '', 'aria-describedby': questionId, text: t( 'openDraft', 'Open draft' ) } )
				: el( 'a', { class: 'sp-btn sp-btn--xs', href: g.write_url || '#', 'data-sp-kb-gap-write': '', 'aria-describedby': questionId, text: t( 'writeIt', 'Write it' ) } );
			var answerBtn = el( 'button', { type: 'button', class: 'sp-kb-gap__action', 'aria-expanded': 'false', 'aria-describedby': questionId, 'data-sp-kb-gap-answer': '', text: t( 'answerIt', 'Answer it' ) } );
			var dismiss = el( 'button', { type: 'button', class: 'sp-kb-gap__dismiss', 'aria-describedby': questionId, 'data-sp-kb-gap-dismiss': '', text: t( 'dismiss' ) } );
			actions.appendChild( write );
			actions.appendChild( answerBtn );
			actions.appendChild( dismiss );

			var editor = null;
			function closeEditor() {
				if ( editor ) { editor.remove(); editor = null; }
				answerBtn.setAttribute( 'aria-expanded', 'false' );
				answerBtn.focus();
			}
			answerBtn.addEventListener( 'click', function () {
				if ( editor ) { closeEditor(); return; }
				editor = answerForm( {
					editQuestion: false,
					onCancel: closeEditor,
					onSave: function ( values ) {
						return api( 'kb/answers?_locale=user', { gap: g.hash, answer: values.answer } ).then( function ( res ) {
							if ( res.data && res.data.ok ) {
								toast( t( 'answerSavedGap', 'Answer saved. The question moved to Saved answers.' ) );
								loadGaps();
								loadAnswers().then( function () { focusAnswer( res.data.item && res.data.item.id ); } );
							}
							return res;
						} );
					},
				} );
				row.appendChild( editor );
				answerBtn.setAttribute( 'aria-expanded', 'true' );
				editor.focusFirst();
			} );
			dismiss.addEventListener( 'click', function () {
				row.style.opacity = '0.4';
				api( 'kb/gaps/dismiss', { hash: g.hash } ).then( loadGaps );
			} );
			list.appendChild( row );
		} );
		revealGapsFromHash( wrap );
	}

	function answersSection() { return document.querySelector( '[data-sp-kb-answers]' ); }

	function loadAnswers() {
		var section = answersSection();
		if ( ! section ) { return Promise.resolve(); }
		return apiGet( 'kb/answers' ).then( function ( res ) {
			if ( ! res.data || ! res.data.ok ) { throw new Error( 'answers_failed' ); }
			renderAnswers( res.data.items || [] );
		} ).catch( function () {
			var error = section.querySelector( '[data-sp-kb-answers-error]' );
			if ( error ) { error.hidden = false; }
		} );
	}

	function focusAnswer( id ) {
		var section = answersSection();
		if ( ! section ) { return; }
		var edit = id ? section.querySelector( '[data-sp-kb-answer="' + id + '"] [data-sp-kb-answer-edit]' ) : null;
		if ( edit ) {
			edit.focus();
			return;
		}
		var title = section.querySelector( '#sp-kb-answers-title' );
		if ( title ) { title.focus(); }
	}

	function usedLabel( hits ) {
		if ( ! hits ) { return t( 'usedNever', 'Not used yet' ); }
		return ( 1 === hits ? t( 'usedOne', 'Used %s time' ) : t( 'usedMany', 'Used %s times' ) ).replace( '%s', String( hits ) );
	}

	function renderAnswers( items ) {
		var section = answersSection();
		var list = section.querySelector( '[data-sp-kb-answers-list]' );
		var count = section.querySelector( '[data-sp-kb-answers-count]' );
		var empty = section.querySelector( '[data-sp-kb-answers-empty]' );
		var error = section.querySelector( '[data-sp-kb-answers-error]' );
		list.textContent = '';
		if ( error ) { error.hidden = true; }
		if ( empty ) { empty.hidden = items.length > 0; }
		if ( count ) {
			count.hidden = ! items.length;
			count.textContent = String( items.length );
		}
		items.forEach( function ( item ) { list.appendChild( answerRow( item ) ); } );
	}

	function answerRow( item ) {
		var questionId = 'sp-kb-answer-row-q-' + item.id;
		var edit = el( 'button', { type: 'button', class: 'sp-kb-gap__action', 'aria-expanded': 'false', 'aria-describedby': questionId, 'data-sp-kb-answer-edit': '', text: t( 'edit', 'Edit' ) } );
		var del = el( 'button', { type: 'button', class: 'sp-kb-gap__dismiss', 'aria-describedby': questionId, 'data-sp-kb-answer-delete': '', text: t( 'delete', 'Delete' ) } );
		var row = el( 'div', { class: 'sp-kb-gap sp-kb-answer', 'data-sp-kb-answer': item.id }, [
			el( 'div', { class: 'sp-kb-gap__main' }, [
				el( 'span', { class: 'sp-kb-gap__body', id: questionId, dir: 'auto', text: item.question } ),
				el( 'p', { class: 'sp-kb-answer__text', dir: 'auto', text: item.answer } ),
				el( 'span', { class: 'sp-kb-answer__meta', text: usedLabel( item.hits ) } ),
			] ),
			el( 'div', { class: 'sp-kb-gap__actions' }, [ edit, del ] ),
		] );

		var editor = null;
		function closeEditor() {
			if ( editor ) { editor.remove(); editor = null; }
			edit.setAttribute( 'aria-expanded', 'false' );
			edit.focus();
		}
		edit.addEventListener( 'click', function () {
			if ( editor ) { closeEditor(); return; }
			editor = answerForm( {
				editQuestion: true,
				question: item.question,
				answer: item.answer,
				onCancel: closeEditor,
				onSave: function ( values ) {
					return api( 'kb/answers?_locale=user', { id: item.id, question: values.question, answer: values.answer } ).then( function ( res ) {
						if ( res.data && res.data.ok ) {
							toast( t( 'answerSaved', 'Answer saved.' ) );
							loadAnswers().then( function () { focusAnswer( res.data.item && res.data.item.id ); } );
						}
						return res;
					} );
				},
			} );
			row.appendChild( editor );
			edit.setAttribute( 'aria-expanded', 'true' );
			editor.focusFirst();
		} );
		del.addEventListener( 'click', function () {
			confirmAction( { title: t( 'deleteTitle', 'Delete this saved answer?' ), body: t( 'deleteBody', '' ), confirmLabel: t( 'deleteCta', 'Delete answer' ), danger: true } ).then( function ( yes ) {
				if ( ! yes ) { return; }
				del.disabled = true;
				api( 'kb/answers/delete', { id: item.id } ).then( function ( res ) {
					if ( ! res.data || ! res.data.ok ) { throw new Error( 'delete_failed' ); }
					toast( t( 'deleted', 'Saved answer deleted.' ) );
					return loadAnswers().then( function () { focusAnswer( '' ); } );
				} ).catch( function () {
					del.disabled = false;
					toast( t( 'deleteFailed', 'Could not delete the saved answer. Please try again.' ) );
				} );
			} );
		} );
		return row;
	}

	function bindAnswers() {
		var section = answersSection();
		if ( ! section ) { return; }
		var add = section.querySelector( '[data-sp-kb-answer-add]' );
		var slot = section.querySelector( '[data-sp-kb-answer-new]' );
		if ( ! add || ! slot ) { return; }
		function closeNew() {
			slot.textContent = '';
			slot.hidden = true;
			add.setAttribute( 'aria-expanded', 'false' );
			add.focus();
		}
		add.addEventListener( 'click', function () {
			if ( ! slot.hidden ) { closeNew(); return; }
			var form = answerForm( {
				editQuestion: true,
				onCancel: closeNew,
				onSave: function ( values ) {
					return api( 'kb/answers?_locale=user', { question: values.question, answer: values.answer } ).then( function ( res ) {
						if ( res.data && res.data.ok ) {
							slot.textContent = '';
							slot.hidden = true;
							add.setAttribute( 'aria-expanded', 'false' );
							toast( t( 'answerSaved', 'Answer saved.' ) );
							loadAnswers().then( function () { focusAnswer( res.data.item && res.data.item.id ); } );
						}
						return res;
					} );
				},
			} );
			slot.appendChild( form );
			slot.hidden = false;
			add.setAttribute( 'aria-expanded', 'true' );
			form.focusFirst();
		} );
	}

	function bindGapsWindow() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-sp-kb-gaps-window]' ), function ( button ) {
			button.addEventListener( 'click', function () {
				gapsWindow = '7d' === button.getAttribute( 'data-sp-kb-gaps-window' ) ? '7d' : 'all';
				Array.prototype.forEach.call( document.querySelectorAll( '[data-sp-kb-gaps-window]' ), function ( other ) {
					var on = other === button;
					other.classList.toggle( 'is-on', on );
					other.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
				} );
				loadGaps();
			} );
		} );
	}

	// The Ask console links to #sp-kb-gaps. The card stays hidden until gaps load, so scroll to it once it shows.
	var gapsRevealed = false;
	function revealGapsFromHash( wrap ) {
		if ( gapsRevealed || wrap.hidden || '#sp-kb-gaps' !== window.location.hash ) { return; }
		gapsRevealed = true;
		wrap.setAttribute( 'tabindex', '-1' );
		wrap.scrollIntoView( { block: 'start' } );
		wrap.focus( { preventScroll: true } );
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
			startBtn.addEventListener( 'click', function () {
				openModal( '', startBtn );
			} );
		}
		Array.prototype.forEach.call( document.querySelectorAll( '[data-sp-kb-open-picker]' ), function ( trigger ) {
			trigger.addEventListener( 'click', function () {
				openModal( trigger.getAttribute( 'data-sp-kb-open-picker' ) || '', trigger );
			} );
		} );
		if ( reindexBtn ) {
			reindexBtn.addEventListener( 'click', function () {
				confirmAction( { title: t( 'confirmAll' ), body: t( 'reindexBody' ), confirmLabel: t( 'reindexCta' ), danger: true } ).then( function ( yes ) {
					if ( yes ) { startIndexing( true, { post_types: [], categories: [] } ); }
				} );
			} );
		}

		// Header-bar action "Re-index everything" (G-22) runs the same full re-index.
		var headerReindex = $( 'sp-kb-header-reindex' );
		if ( headerReindex ) {
			headerReindex.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				confirmAction( { title: t( 'confirmAll' ), body: t( 'reindexBody' ), confirmLabel: t( 'reindexCta' ), danger: true } ).then( function ( yes ) {
					if ( yes ) { startIndexing( true, { post_types: [], categories: [] } ); }
				} );
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
				confirmAction( { title: t( 'confirmGaps' ), body: t( 'gapsBody' ), confirmLabel: t( 'gapsCta' ), danger: true } ).then( function ( yes ) {
					if ( yes ) { api( 'kb/gaps/dismiss', { hash: '' } ).then( loadGaps ); }
				} );
			} );
		}

		// The indexed-entry manager is a deep route; avoid a needless API call
		// from the four canonical Site Knowledge tabs.
		if ( rows ) {
			loadTable( 1 );
		}

		var resumeBtn = $( 'sp-kb-resume' );
		if ( resumeBtn ) {
			resumeBtn.addEventListener( 'click', function () {
				startIndexing( false, null, true );
			} );
		}
		bindGapsWindow();
		bindAnswers();
		loadGaps();
		loadAnswers();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
