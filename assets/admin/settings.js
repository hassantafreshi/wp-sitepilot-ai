/* global AISAAutoPilotAI */
/**
 * Setup & Settings: provider picking, the model dropdown (curated list plus a
 * live refresh from the provider), the connection test, and the delivery test.
 */
( function () {
	'use strict';

	var cfg = window.AISAAutoPilotAI || {};
	var T = cfg.i18n || {};
	var catalog = readCatalog();
	var providers = catalog.providers || {};
	var strings = catalog.i18n || {};

	function t( key, fallback ) { return T[ key ] || fallback || key; }

	function $( id ) { return document.getElementById( id ); }

	function readCatalog() {
		var node = document.getElementById( 'sp-provider-catalog' );
		if ( ! node ) { return {}; }
		try {
			return JSON.parse( node.textContent || '{}' );
		} catch ( e ) {
			return {};
		}
	}

	function status( node, message, kind ) {
		if ( ! node ) { return; }
		node.className = 'sp-status-text' + ( kind ? ' is-' + kind : '' );
		node.textContent = message;
	}

	function currentProvider() {
		var select = $( 'sp-provider' );
		return select ? select.value : '';
	}

	function preset( key ) {
		return providers[ key ] || {};
	}

	/* ── Model dropdown ─────────────────────────────────────── */

	/**
	 * Rebuild the picker from a map of id → label, keeping whatever is currently
	 * in the model field selectable even when the provider has never heard of it
	 * (a saved custom model must not silently disappear from the list).
	 */
	function fillModels( models, extraLabel ) {
		var select = $( 'sp-model-select' );
		var input = $( 'sp-model' );
		if ( ! select || ! input ) { return; }

		var current = input.value.trim();
		var seen = {};

		select.textContent = '';

		Object.keys( models || {} ).forEach( function ( id ) {
			seen[ id ] = true;
			select.appendChild( option( id, models[ id ] || id ) );
		} );

		if ( current && ! seen[ current ] ) {
			select.insertBefore( option( current, current + ( extraLabel ? ' — ' + extraLabel : '' ) ), select.firstChild );
		}

		select.appendChild( option( '__custom', strings.other || 'Other — type a model name' ) );

		// `current` is always present as an option by now, either from the map or
		// from the insert above, so this never falls back silently.
		select.value = current !== '' ? current : '__custom';
	}

	function hasOption( select, value ) {
		return Array.prototype.some.call( select.options, function ( item ) { return item.value === value; } );
	}

	function option( value, label ) {
		var node = document.createElement( 'option' );
		node.value = value;
		node.textContent = label;
		return node;
	}

	function fillEmbeddings( key ) {
		var list = $( 'sp-embedding-models' );
		if ( ! list ) { return; }
		list.textContent = '';
		var models = preset( key ).embeddings || {};
		Object.keys( models ).forEach( function ( id ) {
			list.appendChild( option( id, models[ id ] || id ) );
		} );
	}

	/** Show where to get a key, as a real link rather than a bare URL. */
	function paintKeyLink( key ) {
		var holder = $( 'sp-key-link' );
		if ( ! holder ) { return; }
		holder.textContent = '';

		var url = preset( key ).keyUrl || '';
		if ( ! url ) { return; }

		var link = document.createElement( 'a' );
		link.href = url;
		if ( link.protocol !== 'https:' && link.protocol !== 'http:' ) { return; }
		link.textContent = strings.keyLink || 'Get an API key';
		link.target = '_blank';
		link.rel = 'noopener noreferrer';
		holder.appendChild( link );
	}

	/**
	 * Apply a provider choice. `fillEndpoint` is false on first paint so a saved
	 * custom base URL or model is never overwritten just by loading the page.
	 */
	function applyProvider( key, fillEndpoint ) {
		var base = $( 'sp-base-url' );
		var input = $( 'sp-model' );
		var entry = preset( key );

		if ( fillEndpoint ) {
			if ( base && entry.baseUrl ) { base.value = entry.baseUrl; }
			if ( input && entry.model ) { input.value = entry.model; }
			if ( fillEndpoint && key === 'custom' ) {
				if ( base ) { base.value = ''; }
				if ( input ) { input.value = ''; }
			}
		}

		var note = $( 'sp-provider-note' );
		if ( note ) { note.textContent = entry.note || ''; }

		var meta = $( 'sp-setup-testmeta' );
		if ( meta ) { meta.textContent = ( entry.label || key ) + ' · ' + ( entry.api === 'anthropic' ? 'messages' : 'chat/completions' ); }

		fillModels( entry.models || {}, strings.saved );
		fillEmbeddings( key );
		paintKeyLink( key );
		syncChips( key );
	}

	function syncChips( key ) {
		var wrap = document.querySelector( '[data-sp-provider-chips]' );
		if ( ! wrap ) { return; }
		Array.prototype.forEach.call( wrap.querySelectorAll( '[data-provider]' ), function ( chip ) {
			chip.classList.toggle( 'is-on', chip.getAttribute( 'data-provider' ) === key );
		} );
		paintBrand( wrap, key );
	}

	/**
	 * Show the chosen provider's mark next to the dropdown.
	 *
	 * The mark is cloned from the matching chip rather than rebuilt from a
	 * string: the SVG is already on the page, and cloning a node keeps markup out
	 * of innerHTML. A provider that has no chip simply leaves the box empty.
	 */
	function paintBrand( wrap, key ) {
		var box = $( 'sp-provider-brand' );
		if ( ! box ) { return; }

		box.textContent = '';
		var chip = wrap.querySelector( '[data-provider="' + key.replace( /[^a-z0-9_-]/gi, '' ) + '"]' );
		var mark = chip ? chip.querySelector( 'svg' ) : null;
		if ( ! mark ) { return; }

		var copy = mark.cloneNode( true );
		copy.setAttribute( 'width', '20' );
		copy.setAttribute( 'height', '20' );
		box.appendChild( copy );
	}

	/* ── REST ───────────────────────────────────────────────── */

	function post( path, body ) {
		return fetch( cfg.restUrl + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( body || {} ),
		} ).then( function ( r ) {
			return r.json().then( function ( data ) {
				if ( ! r.ok ) {
					var err = new Error( data && data.message ? data.message : 'HTTP ' + r.status );
					err.status = r.status;
					err.data = data;
					throw err;
				}
				return data;
			} ).catch( function ( e ) {
				if ( e.status ) { throw e; }
				throw new Error( 'Server returned HTTP ' + r.status );
			} );
		} );
	}

	/** What the user has typed, so a test runs against the pending values. */
	function draft() {
		var body = {
			provider: currentProvider(),
			base_url: $( 'sp-base-url' ) ? $( 'sp-base-url' ).value : '',
			model: $( 'sp-model' ) ? $( 'sp-model' ).value : '',
		};
		var keyField = $( 'sp-api-key' );
		if ( keyField && keyField.value ) { body.api_key = keyField.value; }
		return body;
	}

	function onRefreshModels() {
		var button = $( 'sp-refresh-models' );
		var result = $( 'sp-models-status' );
		status( result, strings.loading || 'Loading models…' );
		if ( button ) { button.disabled = true; }

		post( 'provider/models', draft() ).then( function ( data ) {
			if ( button ) { button.disabled = false; }
			if ( ! data || ! data.ok || ! data.models || ! data.models.length ) {
				status( result, ( data && data.message ) || t( 'testFail', 'Could not load models.' ), 'err' );
				return;
			}

			var map = {};
			data.models.forEach( function ( id ) { map[ id ] = id; } );
			fillModels( map, strings.saved );
			status( result, data.message || '', 'ok' );
		} ).catch( function ( e ) {
			if ( button ) { button.disabled = false; }
			var msg = ( e && e.data && e.data.message ) || ( e && e.message ) || t( 'networkError', 'Network error.' );
			status( result, msg, 'err' );
		} );
	}

	/* ── Connection test ────────────────────────────────────── */

	function paint( state, title, body ) {
		var box = $( 'sp-setup-testbox' );
		var mark = $( 'sp-setup-testmark' );
		if ( ! box ) { return; }

		box.className = 'sp-testbox' + ( state ? ' sp-testbox--' + state : '' );
		$( 'sp-setup-testtitle' ).textContent = title;
		$( 'sp-setup-testbody' ).textContent = body;

		if ( ! mark ) { return; }
		if ( state === 'busy' ) {
			mark.className = 'sp-spinner';
			mark.textContent = '';
			return;
		}
		mark.className = 'sp-mark';
		mark.textContent = state === 'ok' ? '✓' : ( state === 'err' ? '!' : '?' );
		mark.style.background = state === 'ok' ? '#059669' : ( state === 'err' ? '#dc2626' : '#1d2327' );
	}

	function onTest() {
		var button = $( 'sp-test' );
		var result = $( 'sp-test-result' );
		if ( button ) { button.disabled = true; }
		status( result, '' );
		paint( 'busy', t( 'testing', 'Testing…' ), t( 'testingBody', 'A short request was sent to the provider.' ) );

		post( 'provider/test', draft() ).then( function ( data ) {
			if ( button ) { button.disabled = false; }
			if ( data && data.ok ) {
				paint( 'ok', t( 'testOk', 'Connection works' ), data.message || t( 'testOkBody', 'Valid response received. You can continue.' ) );
			} else {
				paintProviderFailure( data || {} );
			}
		} ).catch( function ( e ) {
			if ( button ) { button.disabled = false; }
			paintProviderFailure( ( e && e.data ) || { message: ( e && e.message ) || t( 'networkError', 'Network error.' ) } );
		} );
	}

	/** Turn stable server diagnostic codes into an immediately useful next step. */
	function paintProviderFailure( data ) {
		var code = data.diagnostic || data.code || '';
		var map = {
			no_api_key: [ 'Add an API key', 'Enter a valid key and test the connection again.' ],
			provider_unauthorized: [ 'API key rejected', 'Check the key, its permissions, and the selected provider.' ],
			provider_payment_required: [ 'Billing action required', 'Add credit or activate billing with this AI provider, then test again.' ],
			provider_rate_limited: [ 'Provider is rate limiting', 'Wait a moment, then retry the connection test.' ],
			provider_unreachable: [ 'Can’t reach provider', 'Check the base URL, firewall settings, and network connection.' ],
			model_not_found: [ 'Endpoint or model not found', 'Check the base URL and model name, then test again.' ],
			invalid_config: [ 'Connection details are incomplete', 'Enter both the base URL and the model name.' ]
		};
		var entry = map[ code ];
		paint(
			'err',
			entry ? entry[ 0 ] : t( 'testFail', 'Connection test failed' ),
			( data && data.message ) || ( entry ? entry[ 1 ] : t( 'testFailBody', 'Check the connection details and try again.' ) )
		);
	}

	function onDeliveryTest() {
		var result = $( 'sp-form-delivery-test-result' );
		status( result, t( 'sending', 'Sending test…' ) );

		post( 'forms/delivery/test', {} ).then( function ( data ) {
			var ok = data && data.ok;
			status( result, ( data && data.message ) || ( ok ? t( 'deliveryOk', 'Test accepted.' ) : t( 'deliveryFail', 'Test failed.' ) ), ok ? 'ok' : 'err' );
		} ).catch( function ( e ) {
			var msg = ( e && e.data && e.data.message ) || ( e && e.message ) || t( 'networkError', 'Network error.' );
			status( result, msg, 'err' );
		} );
	}

	/** Keep the radio "card" highlight in sync with the checked input. */
	function initRadioCards() {
		var cards = document.querySelectorAll( '.sp-check--card' );
		Array.prototype.forEach.call( cards, function ( card ) {
			var input = card.querySelector( 'input[type="radio"]' );
			if ( ! input ) { return; }
			input.addEventListener( 'change', function () {
				Array.prototype.forEach.call(
					document.querySelectorAll( 'input[name="' + input.name + '"]' ),
					function ( sibling ) {
						var owner = sibling.closest( '.sp-check--card' );
						if ( owner ) { owner.classList.toggle( 'is-on', sibling.checked ); }
					}
				);
			} );
		} );
	}

	function init() {
		var select = $( 'sp-provider' );
		var modelSelect = $( 'sp-model-select' );
		var modelInput = $( 'sp-model' );

		if ( select ) {
			applyProvider( select.value, false );
			select.addEventListener( 'change', function () { applyProvider( select.value, true ); } );
		}

		var chips = document.querySelector( '[data-sp-provider-chips]' );
		if ( chips && select ) {
			Array.prototype.forEach.call( chips.querySelectorAll( '[data-provider]' ), function ( chip ) {
				chip.addEventListener( 'click', function () {
					select.value = chip.getAttribute( 'data-provider' ) || '';
					applyProvider( select.value, true );
				} );
			} );
		}

		// The text field is the value that gets saved; the picker only writes into
		// it, so an unlisted model typed by hand always wins.
		if ( modelSelect && modelInput ) {
			modelSelect.addEventListener( 'change', function () {
				if ( modelSelect.value === '__custom' ) {
					modelInput.focus();
					return;
				}
				modelInput.value = modelSelect.value;
			} );
			modelInput.addEventListener( 'input', function () {
				modelSelect.value = hasOption( modelSelect, modelInput.value ) ? modelInput.value : '__custom';
			} );
		}

		var refresh = $( 'sp-refresh-models' );
		if ( refresh ) { refresh.addEventListener( 'click', onRefreshModels ); }

		var test = $( 'sp-test' );
		if ( test ) { test.addEventListener( 'click', onTest ); }

		var delivery = $( 'sp-test-form-delivery' );
		if ( delivery ) { delivery.addEventListener( 'click', onDeliveryTest ); }

		initRadioCards();
		initAdvanced();
		initRetention();
	}

	/* ── Advanced settings: data retention ── */

	function initRetention() {
		var card = document.querySelector( '[data-sp-retention]' );
		if ( ! card ) { return; }
		var A = readJson( 'sp-adv-i18n' );
		var run = card.querySelector( '[data-sp-retention-run]' );
		var result = card.querySelector( '[data-sp-retention-result]' );
		var summary = card.querySelector( '[data-sp-retention-summary]' );

		if ( run ) {
			run.addEventListener( 'click', function () {
				run.disabled = true;
				status( result, A.retentionRunning || 'Deleting expired data…' );
				post( 'privacy/retention/run?_locale=user', {} ).then( function ( data ) {
					if ( summary && data && data.message ) { summary.textContent = data.message; }
					status( result, A.retentionDone || 'Done.', 'ok' );
				} ).catch( function ( e ) {
					status( result, ( e && e.data && e.data.message ) || ( e && e.message ) || t( 'networkError', 'Network error.' ), 'err' );
				} ).then( function () { run.disabled = false; } );
			} );
		}

		Array.prototype.forEach.call( card.querySelectorAll( '[data-sp-copy]' ), function ( button ) {
			button.addEventListener( 'click', function () {
				var source = button.parentNode.querySelector( '[data-sp-copy-source]' );
				if ( ! source || ! navigator.clipboard ) { return; }
				navigator.clipboard.writeText( source.textContent ).then( function () {
					var label = button.textContent;
					button.textContent = A.copied || 'Copied';
					window.setTimeout( function () { button.textContent = label; }, 1600 );
				} );
			} );
		} );
	}

	/* ── Advanced settings: unsaved changes and section navigation ── */

	function readJson( id ) {
		try {
			var node = document.getElementById( id );
			return node ? ( JSON.parse( node.textContent || '{}' ) || {} ) : {};
		} catch ( e ) { return {}; }
	}

	function initAdvanced() {
		var form = document.querySelector( '[data-sp-advanced-form]' );
		if ( ! form ) { return; }
		var A = readJson( 'sp-adv-i18n' );
		var bar = form.querySelector( '[data-sp-savebar]' );
		var dirty = false;

		function markDirty() {
			if ( dirty ) { return; }
			dirty = true;
			if ( bar ) { bar.hidden = false; }
		}

		// The delivery test is a button, so it never fires input or change and never counts as a change.
		form.addEventListener( 'input', markDirty );
		form.addEventListener( 'change', markDirty );
		form.addEventListener( 'submit', function () { dirty = false; } );
		window.addEventListener( 'beforeunload', function ( event ) {
			if ( ! dirty ) { return; }
			event.preventDefault();
			event.returnValue = '';
		} );

		var discard = form.querySelector( '[data-sp-discard]' );
		if ( discard ) {
			discard.addEventListener( 'click', function () {
				var shell = window.AISAAutoPilotShell;
				var ask = shell && 'function' === typeof shell.confirm
					? shell.confirm( {
						title: A.discardTitle || 'Discard your changes?',
						body: A.discardBody || 'Advanced settings go back to their last saved values.',
						confirmLabel: A.discardCta || 'Discard changes',
						danger: true,
					} )
					: Promise.resolve( window.confirm( A.discardTitle || 'Discard your changes?' ) );
				ask.then( function ( yes ) {
					if ( ! yes ) { return; }
					dirty = false;
					// A GET of the same address, so a page shown after saving is never posted again.
					window.location.href = window.location.href.split( '#' )[ 0 ];
				} );
			} );
		}

		var search = form.querySelector( '[data-sp-adv-toggle="search"]' );
		var searchFields = form.querySelector( '[data-sp-adv-fields="search"]' );
		if ( search && searchFields ) {
			search.addEventListener( 'change', function () { searchFields.classList.toggle( 'is-muted', ! search.checked ); } );
		}

		var links = document.querySelectorAll( '.sp-adv__nav a[href^="#"]' );
		if ( ! links.length || ! ( 'IntersectionObserver' in window ) ) { return; }
		var byId = {};
		Array.prototype.forEach.call( links, function ( link ) { byId[ link.getAttribute( 'href' ).slice( 1 ) ] = link; } );
		var observer = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( ! entry.isIntersecting ) { return; }
				Array.prototype.forEach.call( links, function ( link ) {
					var current = link === byId[ entry.target.id ];
					link.classList.toggle( 'is-current', current );
					if ( current ) {
						link.setAttribute( 'aria-current', 'true' );
					} else {
						link.removeAttribute( 'aria-current' );
					}
				} );
			} );
		}, { rootMargin: '-35% 0px -60% 0px' } );
		Object.keys( byId ).forEach( function ( id ) {
			var section = document.getElementById( id );
			if ( section ) { observer.observe( section ); }
		} );
	}

	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
}() );
