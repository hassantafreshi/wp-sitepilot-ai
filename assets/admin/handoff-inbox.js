/* global AISAAutoPilotAI */
( function () {
	'use strict';

	var root = document.querySelector( '[data-sp-handoff-inbox-list]' );
	if ( ! root ) { return; }

	var cfg = window.AISAAutoPilotAI || {};
	var data = readJson( 'sp-handoff-inbox-data' );
	var T = data.i18n || {};
	var labels = data.labels || {};
	var threads = {};
	( data.threads || [] ).forEach( function ( thread ) { threads[ thread.id ] = thread; } );

	var drawer = root.querySelector( '[data-sp-handoff-drawer]' );
	var panel = drawer ? drawer.querySelector( '[role="dialog"]' ) : null;
	var activeFilter = 'all';
	var openId = 0;
	var opener = null;
	var saving = false;

	// .sp-screen animates with a transform, which would trap a fixed drawer inside the page body.
	// Keep the drawer beside the shared modals at the .sp-app level so it covers the whole viewport.
	var appRoot = root.closest( '.sp-app' );
	if ( drawer && appRoot && drawer.parentNode !== appRoot ) {
		appRoot.appendChild( drawer );
	}

	function readJson( id ) {
		try {
			var node = document.getElementById( id );
			return node ? ( JSON.parse( node.textContent || '{}' ) || {} ) : {};
		} catch ( e ) { return {}; }
	}

	function t( key, fallback ) { return T[ key ] || fallback; }

	function field( name ) { return drawer.querySelector( '[data-sp-handoff-d="' + name + '"]' ); }

	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) { node.className = className; }
		if ( undefined !== text ) { node.textContent = text; }
		return node;
	}

	function toast( message ) {
		var shell = window.AISAAutoPilotShell;
		if ( shell && 'function' === typeof shell.toast ) { shell.toast( message ); }
	}

	function confirmAction( opts ) {
		var shell = window.AISAAutoPilotShell;
		if ( shell && 'function' === typeof shell.confirm ) { return shell.confirm( opts ); }
		return Promise.resolve( window.confirm( opts.title ) );
	}

	function post( path, body ) {
		return fetch( ( cfg.restUrl || '' ) + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( body || {} ),
		} ).then( function ( response ) {
			return response.json().catch( function () { return {}; } ).then( function ( result ) {
				if ( ! response.ok || ! result.ok ) { throw new Error( result.message || '' ); }
				return result;
			} );
		} );
	}

	/* ── Filters and counts ── */
	// "Archived" shows only archived threads; every other filter shows threads still in the inbox.
	function matches( thread, key ) {
		if ( 'archived' === key ) { return !! thread.archived; }
		return ! thread.archived && ( 'all' === key || thread.status === key );
	}

	function applyFilter( key ) {
		var visible = 0;
		activeFilter = key;
		Array.prototype.forEach.call( root.querySelectorAll( '[data-sp-handoff-item]' ), function ( item ) {
			var thread = threads[ item.getAttribute( 'data-sp-handoff-item' ) ];
			var show = !! thread && matches( thread, key );
			item.hidden = ! show;
			if ( show ) { visible++; }
		} );
		Array.prototype.forEach.call( root.querySelectorAll( '[data-sp-handoff-filter]' ), function ( button ) {
			var on = button.getAttribute( 'data-sp-handoff-filter' ) === key;
			button.classList.toggle( 'is-on', on );
			button.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
		} );

		var empty = root.querySelector( '[data-sp-handoff-empty]' );
		if ( ! empty ) { return; }
		var ids = Object.keys( threads );
		var inInbox = ids.filter( function ( id ) { return ! threads[ id ].archived; } ).length;
		var title = t( 'emptyTitle', 'No one has asked for a person yet' );
		var text = t( 'emptyText', 'When a visitor asks for a person, the conversation lands here and in your ready channels.' );
		if ( 'archived' === key ) {
			title = t( 'archivedEmptyTitle', 'No archived threads' );
			text = t( 'archivedEmptyText', 'Archive a thread from its panel, or archive every resolved thread at once.' );
		} else if ( 'all' === key && ids.length && ! inInbox ) {
			title = t( 'allArchivedTitle', 'Nothing waiting in the inbox' );
			text = t( 'allArchivedText', 'Every thread is archived. Open Archived to see them or move one back.' );
		} else if ( ids.length ) {
			title = t( 'filteredTitle', 'No threads with this status' );
			text = t( 'filteredText', 'Pick another filter to see the rest.' );
		}
		empty.hidden = visible > 0;
		empty.querySelector( '[data-sp-handoff-empty-title]' ).textContent = title;
		empty.querySelector( '[data-sp-handoff-empty-text]' ).textContent = text;
	}

	function refreshCounts() {
		var counts = { all: 0, new: 0, in_progress: 0, resolved: 0, failed: 0, archived: 0 };
		Object.keys( threads ).forEach( function ( id ) {
			var thread = threads[ id ];
			if ( thread.archived ) {
				counts.archived++;
				return;
			}
			counts.all++;
			counts[ thread.status ] = ( counts[ thread.status ] || 0 ) + 1;
		} );
		Object.keys( counts ).forEach( function ( key ) {
			var node = root.querySelector( '[data-sp-handoff-count="' + key + '"]' );
			if ( node ) { node.textContent = String( counts[ key ] ); }
		} );
		var badge = root.querySelector( '[data-sp-handoff-new-badge]' );
		if ( badge ) {
			badge.hidden = counts.new < 1;
			badge.textContent = t( 'newBadge', '%s new' ).replace( '%s', String( counts.new ) );
		}
		var archiveAll = root.querySelector( '[data-sp-handoff-archive-resolved]' );
		if ( archiveAll ) {
			archiveAll.hidden = counts.resolved < 1;
			var count = archiveAll.querySelector( '[data-sp-handoff-archive-resolved-count]' );
			if ( count ) { count.textContent = String( counts.resolved ); }
		}
		updateMenuBubble( counts.new );
	}

	// Keeps the WordPress "Answer Desk" count bubble in step without a reload.
	function updateMenuBubble( waiting ) {
		Array.prototype.forEach.call( document.querySelectorAll( '#adminmenu .awaiting-mod' ), function ( bubble ) {
			var link = bubble.closest( 'a' );
			if ( ! link || -1 === String( link.getAttribute( 'href' ) || '' ).indexOf( 'page=aisaautopilot-conversations' ) ) { return; }
			var count = bubble.querySelector( '.pending-count' );
			var spoken = bubble.querySelector( '.screen-reader-text' );
			bubble.className = 'awaiting-mod count-' + waiting;
			bubble.hidden = waiting < 1;
			if ( count ) { count.textContent = String( waiting ); }
			if ( spoken ) {
				spoken.textContent = ( 1 === waiting
					? t( 'waitingOne', '%s visitor waiting for a person' )
					: t( 'waitingMany', '%s visitors waiting for a person' ) ).replace( '%s', String( waiting ) );
			}
		} );
	}

	function renderRow( id ) {
		var thread = threads[ id ];
		var item = root.querySelector( '[data-sp-handoff-item="' + id + '"]' );
		if ( ! thread || ! item ) { return; }
		item.setAttribute( 'data-status', thread.status );
		item.setAttribute( 'data-archived', thread.archived ? '1' : '0' );
		var label = item.querySelector( '[data-sp-handoff-status-label]' );
		if ( label ) { label.textContent = labels[ thread.status ] || thread.status; }
		applyFilter( activeFilter );
	}

	/* ── Thread drawer ── */
	function renderStatus() {
		var thread = threads[ openId ];
		Array.prototype.forEach.call( panel.querySelectorAll( '[data-sp-handoff-set]' ), function ( button ) {
			var on = !! thread && button.getAttribute( 'data-sp-handoff-set' ) === thread.status;
			button.classList.toggle( 'is-on', on );
			button.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			// aria-disabled rather than disabled, so keyboard focus stays on the pressed button while saving.
			button.setAttribute( 'aria-disabled', saving ? 'true' : 'false' );
		} );
		field( 'failed' ).hidden = ! thread || 'failed' !== thread.status;

		var note = field( 'archived' );
		note.hidden = ! thread || ! thread.archived;
		note.textContent = thread && thread.archived ? t( 'archivedNote', 'Archived %s.' ).replace( '%s', thread.archived_on || '' ) : '';

		var archive = panel.querySelector( '[data-sp-handoff-archive]' );
		if ( archive ) {
			archive.textContent = thread && thread.archived ? t( 'restore', 'Move back to inbox' ) : t( 'archive', 'Archive' );
			archive.setAttribute( 'aria-disabled', saving ? 'true' : 'false' );
		}
	}

	function renderContact( thread ) {
		var list = field( 'contact' );
		list.textContent = '';
		var items = [];
		if ( thread.email ) {
			items.push( { label: t( 'email', 'Email' ), text: thread.email, href: 'mailto:' + encodeURI( thread.email ) } );
		}
		if ( thread.phone ) {
			items.push( { label: t( 'phone', 'Mobile' ), text: thread.phone, href: 'tel:' + String( thread.phone ).replace( /[^0-9+]/g, '' ) } );
		}
		// The server only keeps http and https addresses; check again before making a link.
		if ( thread.website && /^https?:\/\//i.test( thread.website ) ) {
			items.push( { label: t( 'website', 'Website' ), text: thread.website.replace( /^https?:\/\//i, '' ), href: thread.website, external: true } );
		}
		if ( ! items.length ) {
			list.appendChild( el( 'li', 'sp-hoi-drawer__contact-none', t( 'noContact', 'No contact details left — reply in your usual channel' ) ) );
			return;
		}
		items.forEach( function ( entry ) {
			var row = el( 'li', 'sp-hoi-drawer__contact-item' );
			row.appendChild( el( 'span', 'sp-hoi-drawer__contact-label', entry.label ) );
			var link = el( 'a', 'sp-hoi-drawer__contact-value', entry.text );
			link.setAttribute( 'href', entry.href );
			link.setAttribute( 'dir', 'ltr' );
			if ( entry.external ) {
				link.setAttribute( 'target', '_blank' );
				link.setAttribute( 'rel', 'noopener noreferrer nofollow' );
			}
			row.appendChild( link );
			list.appendChild( row );
		} );
	}

	function renderMessages( thread ) {
		var wrap = field( 'messages' );
		wrap.textContent = '';
		if ( ! thread.messages || ! thread.messages.length ) {
			wrap.appendChild( el( 'p', 'sp-hoi-drawer__none', t( 'noMessages', 'The visitor did not share any messages.' ) ) );
			return;
		}
		thread.messages.forEach( function ( message ) {
			var assistant = 'assistant' === message.role;
			var row = el( 'div', 'sp-hoi-msg ' + ( assistant ? 'sp-hoi-msg--assistant' : 'sp-hoi-msg--visitor' ) );
			var text = el( 'div', 'sp-hoi-msg__text', message.content );
			text.setAttribute( 'dir', 'auto' );
			row.appendChild( el( 'div', 'sp-hoi-msg__who', assistant ? t( 'assistant', 'Assistant' ) : thread.name ) );
			row.appendChild( text );
			wrap.appendChild( row );
		} );
	}

	function focusables() {
		return Array.prototype.filter.call( panel.querySelectorAll( 'a[href], button' ), function ( node ) {
			return ! node.hidden && null !== node.offsetParent;
		} );
	}

	function onKeydown( event ) {
		if ( drawer.hidden ) { return; }
		if ( 'Escape' === event.key ) {
			event.preventDefault();
			closeThread();
			return;
		}
		if ( 'Tab' !== event.key ) { return; }
		var nodes = focusables();
		if ( ! nodes.length ) { return; }
		var first = nodes[ 0 ];
		var last = nodes[ nodes.length - 1 ];
		if ( ! panel.contains( document.activeElement ) ) {
			event.preventDefault();
			first.focus();
		} else if ( event.shiftKey && document.activeElement === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			event.preventDefault();
			first.focus();
		}
	}

	function openThread( id, trigger ) {
		var thread = threads[ id ];
		if ( ! thread || ! drawer ) { return; }
		openId = id;
		opener = trigger || null;

		field( 'name' ).textContent = thread.name;
		renderContact( thread );
		field( 'dates' ).textContent = t( 'dates', 'Asked %1$s · updated %2$s' ).replace( '%1$s', thread.asked ).replace( '%2$s', thread.updated );

		var reply = field( 'reply' );
		if ( thread.email ) {
			reply.hidden = false;
			reply.setAttribute( 'href', 'mailto:' + encodeURI( thread.email ) + '?subject=' + encodeURIComponent( t( 'replySubject', '' ) ) );
		} else {
			reply.hidden = true;
			reply.removeAttribute( 'href' );
		}

		field( 'saving' ).textContent = '';
		renderStatus();
		renderMessages( thread );
		drawer.hidden = false;
		// Locking the root scroller also removes the page scrollbar, so the drawer spans the full viewport.
		document.documentElement.classList.add( 'sp-hoi-open' );
		document.addEventListener( 'keydown', onKeydown, true );
		var close = panel.querySelector( '.sp-hoi-drawer__close' );
		if ( close ) { close.focus(); }
	}

	function closeThread() {
		if ( ! drawer || drawer.hidden ) { return; }
		drawer.hidden = true;
		document.documentElement.classList.remove( 'sp-hoi-open' );
		document.removeEventListener( 'keydown', onKeydown, true );
		openId = 0;
		// A status change or archiving can filter the opener's row away; fall back to the active filter.
		var target = opener && ! opener.closest( '[hidden]' ) ? opener : root.querySelector( '[data-sp-handoff-filter].is-on' );
		opener = null;
		if ( target ) { target.focus(); }
	}

	function finishSaving( id, saved, failedKey, failedFallback ) {
		saving = false;
		if ( id !== openId ) { return; }
		field( 'saving' ).textContent = saved ? '' : t( failedKey, failedFallback );
		renderStatus();
	}

	function setStatus( status ) {
		var thread = threads[ openId ];
		if ( ! thread || saving || thread.status === status ) { return; }
		var id = openId;
		var saved = false;
		saving = true;
		renderStatus();
		field( 'saving' ).textContent = t( 'saving', 'Saving…' );

		post( 'handoffs/' + id + '/status', { status: status } ).then( function () {
			saved = true;
			thread.status = status;
			renderRow( id );
			refreshCounts();
			toast( t( 'marked', 'Marked as %s. Visitors do not see this.' ).replace( '%s', labels[ status ] || status ) );
		} ).catch( function () {
			toast( t( 'saveFailed', 'Could not update the status. Please try again.' ) );
		} ).then( function () {
			finishSaving( id, saved, 'saveFailed', 'Could not update the status. Please try again.' );
		} );
	}

	function toggleArchived() {
		var thread = threads[ openId ];
		if ( ! thread || saving ) { return; }
		var id = openId;
		var archived = ! thread.archived;
		var saved = false;
		saving = true;
		renderStatus();
		field( 'saving' ).textContent = t( 'saving', 'Saving…' );

		post( 'handoffs/' + id + '/archive?_locale=user', { archived: archived } ).then( function ( result ) {
			saved = true;
			thread.archived = !! result.archived;
			thread.archived_on = result.archived_on || '';
			renderRow( id );
			refreshCounts();
			toast( thread.archived ? t( 'archivedToast', 'Archived. It no longer counts as waiting.' ) : t( 'restoredToast', 'Moved back to the inbox.' ) );
		} ).catch( function () {
			toast( t( 'archiveFailed', 'Could not archive this thread. Please try again.' ) );
		} ).then( function () {
			finishSaving( id, saved, 'archiveFailed', 'Could not archive this thread. Please try again.' );
		} );
	}

	function archiveResolved( button ) {
		if ( button.disabled ) { return; }
		confirmAction( {
			title: t( 'archiveResolvedTitle', 'Archive every resolved thread?' ),
			body: t( 'archiveResolvedBody', '' ),
			confirmLabel: t( 'archiveResolvedCta', 'Archive resolved' ),
		} ).then( function ( yes ) {
			if ( ! yes ) { return; }
			button.disabled = true;
			post( 'handoffs/archive-resolved?_locale=user', {} ).then( function ( result ) {
				Object.keys( threads ).forEach( function ( id ) {
					var thread = threads[ id ];
					if ( ! thread.archived && 'resolved' === thread.status ) {
						thread.archived = true;
						thread.archived_on = result.archived_on || '';
						renderRow( id );
					}
				} );
				refreshCounts();
				var count = Number( result.count || 0 );
				toast( ( 1 === count ? t( 'archivedOne', '%s resolved thread archived.' ) : t( 'archivedMany', '%s resolved threads archived.' ) ).replace( '%s', String( count ) ) );
			} ).catch( function () {
				toast( t( 'archiveFailed', 'Could not archive this thread. Please try again.' ) );
			} ).then( function () {
				button.disabled = false;
				// The button hides once nothing resolved is left; keep keyboard focus inside the inbox.
				if ( button.hidden ) {
					var filter = root.querySelector( '[data-sp-handoff-filter].is-on' );
					if ( filter ) { filter.focus(); }
				}
			} );
		} );
	}

	function onClick( event ) {
		var target = event.target;
		var filter = target.closest( '[data-sp-handoff-filter]' );
		if ( filter ) {
			applyFilter( filter.getAttribute( 'data-sp-handoff-filter' ) );
			return;
		}
		var open = target.closest( '[data-sp-handoff-open]' );
		if ( open ) {
			openThread( Number( open.getAttribute( 'data-sp-handoff-open' ) ), open );
			return;
		}
		var set = target.closest( '[data-sp-handoff-set]' );
		if ( set ) {
			setStatus( set.getAttribute( 'data-sp-handoff-set' ) );
			return;
		}
		if ( target.closest( '[data-sp-handoff-archive]' ) ) {
			toggleArchived();
			return;
		}
		var archiveAll = target.closest( '[data-sp-handoff-archive-resolved]' );
		if ( archiveAll ) {
			archiveResolved( archiveAll );
			return;
		}
		if ( target.closest( '[data-sp-handoff-close]' ) ) {
			closeThread();
		}
	}

	root.addEventListener( 'click', onClick );
	if ( drawer ) {
		drawer.addEventListener( 'click', onClick );
	}
} )();
