/* global AISAAutoPilotAI */
/**
 * Setup & Connection — the mockup's four-step wizard.
 *
 * It never stores a credential in the browser: a key is sent only to the
 * authenticated provider-test and model endpoints, then to the normal
 * nonce-protected settings form on an explicit "Finish setup". The staged
 * checklist follows the two real requests (test, then models); nothing is
 * animated ahead of a response.
 */
( function () {
	'use strict';

	var root = document.querySelector( '[data-sp-setup-wizard]' );
	if ( ! root ) { return; }

	var cfg = window.AISAAutoPilotAI || {};
	var catalog = readCatalog();
	var providers = catalog.providers || {};
	var S = catalog.i18n || {};
	var total = catalog.total || Object.keys( providers ).length;

	var ICONS = {
		tick: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 13 4.5 4.5L19 7"/></svg>',
		cross: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 9l-6 6m0-6 6 6"/></svg>',
		dash: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14"/></svg>',
		spin: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" class="sp-wizard-spin" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.2-8.5"/></svg>',
		ok: '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 13 4.5 4.5L19 7"/></svg>',
		bad: '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 9l-6 6m0-6 6 6M12 21a9 9 0 1 1 0-18 9 9 0 0 1 0 18Z"/></svg>',
		warn: '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8v5M12 17h.01M10.3 3.9 2.5 17.4a2 2 0 0 0 1.7 3h15.6a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>',
		busy: '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" class="sp-wizard-spin" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.2-8.5"/></svg>',
	};

	var STAGES = [ 'reach', 'key', 'test', 'models' ];

	// Which stage a failed provider test stopped at, from the server diagnostic.
	var FAILED_STAGE = {
		no_api_key: 'key',
		provider_unauthorized: 'key',
		provider_payment_required: 'key',
		provider_rate_limited: 'key',
		provider_unreachable: 'reach',
		invalid_config: 'reach',
		model_not_found: 'test',
	};

	var state = {
		step: root.getAttribute( 'data-saved' ) === '1' ? 5 : 1,
		tested: root.getAttribute( 'data-verified' ) === '1',
		testedModel: null,
		showAll: false,
		showAllModels: false,
		checking: false,
		models: null,
	};

	function readCatalog() {
		var node = document.getElementById( 'sp-provider-catalog' );
		if ( ! node ) { return {}; }
		try {
			return JSON.parse( node.textContent || '{}' );
		} catch ( e ) {
			return {};
		}
	}

	function t( key, fallback, vars ) {
		var text = S[ key ] || fallback || key;
		return String( text ).replace( /\{([a-z]+)\}/g, function ( match, name ) {
			return vars && Object.prototype.hasOwnProperty.call( vars, name ) ? String( vars[ name ] ) : match;
		} );
	}

	function byId( id ) { return document.getElementById( id ); }
	function one( selector ) { return root.querySelector( selector ); }
	function all( selector ) { return Array.prototype.slice.call( root.querySelectorAll( selector ) ); }
	function value( id ) { var node = byId( id ); return node ? node.value.trim() : ''; }

	function keyMode() {
		var radio = one( 'input[name="key_management"]:checked' );
		return radio ? radio.value : 'keyvault';
	}

	function providerKey() { return value( 'sp-provider' ); }
	function preset( key ) { return providers[ key || providerKey() ] || {}; }

	function host() {
		var raw = value( 'sp-base-url' );
		try {
			return raw ? new URL( raw ).hostname : ( preset().host || t( 'yourEndpoint', 'your endpoint' ) );
		} catch ( e ) {
			return t( 'yourEndpoint', 'your endpoint' );
		}
	}

	function draft() {
		var payload = {
			provider: providerKey(),
			base_url: value( 'sp-base-url' ),
			model: value( 'sp-model' ),
			key_management: keyMode(),
		};
		var apiKey = byId( 'sp-api-key' );
		if ( apiKey && ! apiKey.disabled && apiKey.value ) { payload.api_key = apiKey.value; }
		var clearKey = one( 'input[name="clear_api_key"]' );
		if ( clearKey && clearKey.checked ) { payload.clear_api_key = true; }
		return payload;
	}

	function post( path, body ) {
		if ( ! cfg.restUrl || ! cfg.nonce ) {
			return Promise.reject( new Error( t( 'networkError', 'Network error. Please try again.' ) ) );
		}
		return fetch( cfg.restUrl + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( body || {} ),
		} ).then( function ( response ) {
			return response.json().catch( function () { return {}; } ).then( function ( data ) {
				if ( ! response.ok ) {
					var error = new Error( data && data.message ? data.message : 'HTTP ' + response.status );
					error.data = data || {};
					throw error;
				}
				return data;
			} );
		} );
	}

	// ── Frame: counter, stepper, panels and footer ───────────────────────

	function canContinue() {
		if ( state.step === 1 ) { return !! one( 'input[name="key_management"]:checked' ); }
		if ( state.step === 2 ) { return !! providerKey(); }
		if ( state.step === 3 ) { return state.tested && !! value( 'sp-model' ); }
		return true;
	}

	function blockedNote() {
		if ( state.step === 1 && ! canContinue() ) { return t( 'pickStorage', 'Pick one to continue' ); }
		if ( state.step === 2 && ! providerKey() ) { return t( 'pickService', 'Pick a service to continue' ); }
		if ( state.step === 3 && ! value( 'sp-model' ) ) { return t( 'needModel', 'Choose a model to continue' ); }
		if ( state.step === 3 && ! state.tested ) {
			return state.testedModel !== null ? t( 'needRecheck', 'Check the connection with this model to continue' ) : t( 'needKey', 'Connect a working key to continue' );
		}
		return '';
	}

	function render() {
		all( '[data-sp-setup-step]' ).forEach( function ( panel ) {
			panel.hidden = Number( panel.getAttribute( 'data-sp-setup-step' ) ) !== state.step;
		} );
		var complete = one( '[data-sp-setup-complete]' );
		if ( complete ) { complete.hidden = state.step !== 5; }
		var footer = one( '[data-sp-setup-footer]' );
		if ( footer ) { footer.hidden = state.step === 5; }

		all( '[data-sp-setup-progress-step]' ).forEach( function ( item ) {
			var number = Number( item.getAttribute( 'data-sp-setup-progress-step' ) );
			var done = number < state.step;
			item.classList.toggle( 'is-current', number === state.step );
			item.classList.toggle( 'is-done', done );
			if ( number === state.step ) { item.setAttribute( 'aria-current', 'step' ); } else { item.removeAttribute( 'aria-current' ); }
		} );

		var counter = one( '[data-sp-setup-counter]' );
		if ( counter ) { counter.textContent = state.step > 4 ? t( 'done', 'Done' ) : t( 'stepOf', 'Step {step} of 4', { step: state.step } ); }

		var back = one( '[data-sp-setup-back]' );
		var next = one( '[data-sp-setup-next]' );
		var save = one( '[data-sp-setup-save]' );
		var ready = canContinue();
		if ( back ) { back.hidden = state.step <= 1 || state.step === 5; }
		if ( next ) {
			next.hidden = state.step >= 4;
			next.classList.toggle( 'is-blocked', ! ready );
			next.setAttribute( 'aria-disabled', ready ? 'false' : 'true' );
		}
		if ( save ) {
			save.hidden = state.step !== 4;
		}
		var note = one( '[data-sp-setup-block-note]' );
		if ( note ) { note.textContent = blockedNote(); }
	}

	function move( nextStep ) {
		state.step = nextStep;
		render();
		var panel = one( nextStep === 5 ? '[data-sp-setup-complete]' : '[data-sp-setup-step="' + nextStep + '"]' );
		var heading = panel ? panel.querySelector( 'h2' ) : null;
		if ( heading ) {
			heading.setAttribute( 'tabindex', '-1' );
			heading.focus( { preventScroll: true } );
		}
		var frame = one( '.sp-wizard__head' );
		if ( frame && frame.getBoundingClientRect().top < 0 ) { frame.scrollIntoView( { block: 'start' } ); }
	}

	// ── Step 1: where the key lives ──────────────────────────────────────

	function syncKeyMode() {
		var mode = keyMode();
		all( '[data-sp-key-mode]' ).forEach( function ( card ) {
			card.classList.toggle( 'is-selected', card.getAttribute( 'data-sp-key-mode' ) === mode );
		} );
		var note = one( '[data-sp-connector-note]' );
		if ( note ) { note.hidden = mode !== 'wp_connectors'; }
		paintKeyPanel();
	}

	// ── Step 2: provider grid ────────────────────────────────────────────

	function filterProviders() {
		var input = byId( 'sp-setup-provider-search' );
		var query = input ? input.value.trim() : '';
		var needle = query.toLowerCase();
		var shown = 0;
		all( '[data-sp-provider-card]' ).forEach( function ( card ) {
			var key = card.getAttribute( 'data-provider' ) || '';
			var visible;
			if ( needle ) {
				visible = card.textContent.toLowerCase().indexOf( needle ) !== -1 || key.indexOf( needle ) !== -1;
			} else {
				visible = state.showAll || card.getAttribute( 'data-popular' ) === '1' || key === providerKey();
			}
			card.hidden = ! visible;
			if ( visible ) { shown += 1; }
		} );

		var label = one( '[data-sp-provider-results]' );
		if ( label ) {
			label.hidden = shown === 0;
			if ( needle ) {
				label.textContent = shown === 1 ? t( 'resultsMatch', '1 match' ) : t( 'resultsMatches', '{count} matches', { count: shown } );
			} else {
				label.textContent = state.showAll ? t( 'resultsAll', 'All {count} services', { count: total } ) : t( 'resultsPopular', 'Popular with WordPress sites' );
			}
		}

		var empty = one( '[data-sp-provider-empty]' );
		if ( empty ) {
			empty.hidden = shown > 0;
			var title = empty.querySelector( '[data-sp-provider-empty-title]' );
			if ( title ) { title.textContent = t( 'noMatch', 'No service called “{query}”', { query: query } ); }
		}

		var toggle = one( '.sp-wizard-expand' );
		if ( toggle ) {
			toggle.hidden = !! needle;
			toggle.setAttribute( 'aria-expanded', state.showAll ? 'true' : 'false' );
			toggle.setAttribute( 'data-sp-provider-filter', state.showAll ? 'popular' : 'all' );
			toggle.classList.toggle( 'is-open', state.showAll );
			var toggleLabel = toggle.querySelector( '[data-sp-provider-toggle-label]' );
			if ( toggleLabel ) { toggleLabel.textContent = state.showAll ? t( 'showFewer', 'Show fewer' ) : t( 'seeAll', 'See all {count} services', { count: total } ); }
		}
	}

	function selectProvider( key, overwrite ) {
		var field = byId( 'sp-provider' );
		if ( ! field || ! providers[ key ] ) { return; }
		field.value = key;
		all( '[data-sp-provider-card]' ).forEach( function ( card ) {
			var selected = card.getAttribute( 'data-provider' ) === key;
			card.classList.toggle( 'is-selected', selected );
			card.setAttribute( 'aria-pressed', selected ? 'true' : 'false' );
		} );
		if ( overwrite ) {
			var entry = preset( key );
			var base = byId( 'sp-base-url' );
			var model = byId( 'sp-model' );
			if ( base ) { base.value = key === 'custom' ? '' : ( entry.baseUrl || '' ); }
			if ( model ) { model.value = key === 'custom' ? '' : ( entry.model || '' ); }
			state.models = null;
			state.showAllModels = false;
			resetTest();
		}
		paintKeyPanel();
		renderModels();
		paintEmbeddings();
		filterProviders();
		render();
	}

	// ── Step 3: key, check, models, advanced ─────────────────────────────

	function endpointNeeded() {
		var key = providerKey();
		var base = value( 'sp-base-url' );
		return key === 'custom' || key === 'azure' || ! base || base.indexOf( 'YOUR-RESOURCE' ) !== -1;
	}

	function setEndpointOpen( open ) {
		var box = one( '[data-sp-setup-endpoint]' );
		var toggle = one( '[data-sp-setup-endpoint-toggle]' );
		var forced = endpointNeeded();
		if ( box ) { box.hidden = ! ( open || forced ); }
		if ( toggle ) {
			toggle.hidden = forced;
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			toggle.textContent = open ? t( 'endpointHide', 'Hide the endpoint' ) : t( 'endpointChange', 'Change the endpoint' );
		}
	}

	function paintKeyPanel() {
		var entry = preset();
		var label = entry.label || providerKey();
		var connector = keyMode() === 'wp_connectors';

		var heading = one( '[data-sp-setup-key-heading]' );
		if ( heading ) { heading.textContent = t( 'keyHeading', 'Connect your {provider} key', { provider: label } ); }
		var lead = one( '[data-sp-setup-key-lead]' );
		if ( lead ) {
			lead.textContent = connector
				? t( 'keyLeadConnector', 'The key stays in Settings → Connectors. AutoPilot sends it only to {host}.', { host: host() } )
				: t( 'keyLeadVault', 'We store it encrypted on this site and never send it anywhere except {host}.', { host: host() } );
		}

		var link = byId( 'sp-key-link' );
		if ( link ) {
			link.hidden = ! entry.keyUrl;
			if ( entry.keyUrl ) { link.href = entry.keyUrl; }
		}

		var input = byId( 'sp-api-key' );
		if ( input ) {
			input.disabled = connector;
			if ( connector ) { input.value = ''; }
			if ( root.getAttribute( 'data-has-vault-key' ) === '1' ) {
				input.placeholder = t( 'keySavedPlaceholder', '•••••••• saved — leave blank to keep it' );
			} else {
				input.placeholder = entry.keyPrefix ? entry.keyPrefix + '…' : t( 'keyPlaceholder', 'Paste your key' );
			}
		}

		var hint = one( '[data-sp-setup-key-source]' );
		if ( hint ) {
			hint.textContent = connector ? t( 'connectorKeyHint', 'This choice uses the key saved in Settings → Connectors.' ) : ( entry.note || t( 'keyHint', 'Nothing is saved until the check passes.' ) );
		}

		var toggle = one( '[data-sp-setup-endpoint-toggle]' );
		setEndpointOpen( toggle ? toggle.getAttribute( 'aria-expanded' ) === 'true' : false );
	}

	function stage( name, status ) {
		var row = one( '[data-sp-setup-checklist] [data-stage="' + name + '"]' );
		if ( ! row ) { return; }
		var icon = row.querySelector( '.sp-wizard-checklist__icon' );
		var label = row.querySelector( '[data-stage-label]' );
		row.className = 'is-' + status;
		if ( icon ) { icon.innerHTML = status === 'done' ? ICONS.tick : status === 'failed' ? ICONS.cross : status === 'running' ? ICONS.spin : ICONS.dash; }
		if ( label ) {
			var base = label.getAttribute( 'data-base' ) || label.textContent;
			label.setAttribute( 'data-base', base );
			label.textContent = status === 'failed' ? t( 'stageFailed', '{label} — failed', { label: base } ) : base;
		}
	}

	function resultBox( tone, title, body ) {
		var box = byId( 'sp-setup-testbox' );
		if ( ! box ) { return; }
		box.hidden = false;
		box.className = 'sp-wizard-result sp-testbox sp-testbox--' + tone;
		var mark = byId( 'sp-setup-testmark' );
		if ( mark ) { mark.innerHTML = ICONS[ tone === 'err' ? 'bad' : tone ] || ICONS.ok; }
		var titleNode = byId( 'sp-setup-testtitle' );
		var bodyNode = byId( 'sp-setup-testbody' );
		if ( titleNode ) { titleNode.textContent = title; }
		if ( bodyNode ) { bodyNode.textContent = body || ''; bodyNode.hidden = ! body; }
		var live = byId( 'sp-test-result' );
		if ( live ) { live.textContent = title + ( body ? '. ' + body : '' ); }
	}

	function setFix( text ) {
		var fix = one( '[data-sp-setup-fix]' );
		var fixText = one( '[data-sp-setup-fix-text]' );
		if ( fix ) { fix.hidden = ! text; }
		if ( fixText ) { fixText.textContent = text || ''; }
	}

	function setActions( actions ) {
		var holder = one( '[data-sp-setup-actions]' );
		if ( ! holder ) { return; }
		holder.textContent = '';
		( actions || [] ).forEach( function ( action, index ) {
			var node = document.createElement( action.href ? 'a' : 'button' );
			node.className = 'sp-wizard-btn sp-wizard-btn--sm ' + ( index === 0 ? 'sp-wizard-btn--primary' : 'sp-wizard-btn--outline' );
			node.textContent = action.label;
			if ( action.href ) {
				node.href = action.href;
				if ( action.external ) { node.target = '_blank'; node.rel = 'noopener noreferrer'; }
			} else {
				node.type = 'button';
				node.addEventListener( 'click', action.run );
			}
			holder.appendChild( node );
		} );
	}

	function showChecklist( visible ) {
		var list = one( '[data-sp-setup-checklist]' );
		if ( list ) { list.hidden = ! visible; }
	}

	function setChecking( busy ) {
		state.checking = busy;
		var button = byId( 'sp-test' );
		if ( button ) {
			button.classList.toggle( 'is-busy', busy );
			button.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
			var label = button.querySelector( '[data-sp-setup-test-label]' );
			if ( label ) { label.textContent = busy ? t( 'checking', 'Checking' ) : ( state.tested ? t( 'checkAgain', 'Check again' ) : t( 'check', 'Check connection' ) ); }
		}
		var input = byId( 'sp-api-key' );
		if ( input ) { input.classList.remove( 'is-ok', 'is-bad' ); }
	}

	function focusField( id ) {
		var node = byId( id );
		if ( node ) {
			node.focus();
			if ( node.select ) { node.select(); }
		}
	}

	function failure( data ) {
		var code = ( data && ( data.diagnostic || data.code ) ) || '';
		var entry = preset();
		var label = entry.label || providerKey();
		var failed = FAILED_STAGE[ code ] || 'test';
		var reached = true;
		STAGES.forEach( function ( name ) {
			if ( name === failed ) { stage( name, 'failed' ); reached = false; return; }
			stage( name, reached ? 'done' : 'pending' );
		} );

		var copy = {
			no_api_key: [ 'err', 'titleNoKey', 'fixNoKey', [ { label: t( 'actionPasteAgain', 'Paste it again' ), run: function () { focusField( 'sp-api-key' ); } } ] ],
			provider_unauthorized: [ 'err', 'titleUnauthorized', entry.keyPrefix ? 'fixUnauthorizedPrefix' : 'fixUnauthorized', [
				{ label: t( 'actionPasteAgain', 'Paste it again' ), run: function () { focusField( 'sp-api-key' ); } },
			].concat( entry.keyUrl ? [ { label: t( 'actionOpenKeys', 'Open {provider} keys', { provider: label } ), href: entry.keyUrl, external: true } ] : [] ) ],
			provider_payment_required: [ 'warn', 'titlePayment', 'fixPayment', [
				{ label: t( 'checkAgain', 'Check again' ), run: testProvider },
				{ label: t( 'actionPickAnother', 'Pick another service' ), run: function () { move( 2 ); } },
			] ],
			provider_rate_limited: [ 'warn', 'titleRateLimit', 'fixRateLimit', [ { label: t( 'actionRetry', 'Retry' ), run: testProvider } ] ],
			provider_unreachable: [ 'err', 'titleUnreachable', 'fixUnreachable', [
				{ label: t( 'actionRetry', 'Retry' ), run: testProvider },
				{ label: t( 'actionNetworkTest', 'Run the full network test' ), href: S.healthUrl || '' },
			] ],
			model_not_found: [ 'err', 'titleNotFound', 'fixNotFound', [
				{ label: t( 'actionEditEndpoint', 'Edit the endpoint' ), run: function () { setEndpointOpen( true ); focusField( 'sp-base-url' ); } },
				{ label: t( 'actionTypeModel', 'Type a model name' ), run: function () { focusField( 'sp-model' ); } },
			] ],
			invalid_config: [ 'err', 'titleIncomplete', 'fixIncomplete', [ { label: t( 'actionEditEndpoint', 'Edit the endpoint' ), run: function () { setEndpointOpen( true ); focusField( 'sp-base-url' ); } } ] ],
		}[ code ] || [ 'err', 'titleFailed', '', [ { label: t( 'checkAgain', 'Check again' ), run: testProvider } ] ];

		resultBox( copy[ 0 ], t( copy[ 1 ] ), ( data && data.message ) || '' );
		setFix( copy[ 2 ] ? t( copy[ 2 ], '', { provider: label, prefix: entry.keyPrefix || '', host: host() } ) : '' );
		setActions( copy[ 3 ] );
		var input = byId( 'sp-api-key' );
		if ( input && failed === 'key' ) { input.classList.add( 'is-bad' ); }
	}

	function testProvider() {
		if ( state.checking ) { return; }
		state.tested = false;
		state.testedModel = null;
		setChecking( true );
		showChecklist( true );
		setFix( '' );
		setActions( [] );
		stage( 'reach', 'running' );
		stage( 'key', 'pending' );
		stage( 'test', 'pending' );
		stage( 'models', 'pending' );
		resultBox( 'busy', t( 'checkingTitle', 'Checking your key…' ), t( 'checkingBody', 'A short test request is on its way to {host}.', { host: host() } ) );
		render();

		post( 'provider/test', draft() ).then( function ( data ) {
			if ( ! data || ! data.ok ) { throw Object.assign( new Error( 'failed' ), { data: data || {} } ); }
			state.tested = true;
			state.testedModel = value( 'sp-model' );
			stage( 'reach', 'done' );
			stage( 'key', 'done' );
			stage( 'test', 'done' );
			stage( 'models', 'running' );
			return refreshModels( true );
		} ).then( function () {
			setChecking( false );
			render();
		} ).catch( function ( error ) {
			setChecking( false );
			failure( ( error && error.data ) || { message: ( error && error.message ) || t( 'networkError', 'Network error. Please try again.' ) } );
			render();
		} );
	}

	function refreshModels( fromCheck ) {
		var button = byId( 'sp-refresh-models' );
		var status = byId( 'sp-models-status' );
		if ( button ) { button.disabled = true; }
		if ( status && ! fromCheck ) { status.textContent = t( 'loading', 'Loading models…' ); }

		return post( 'provider/models', draft() ).then( function ( data ) {
			var list = data && data.ok && Array.isArray( data.models ) ? data.models : [];
			state.models = list.length ? list : null;
			if ( fromCheck ) {
				stage( 'models', list.length ? 'done' : 'pending' );
				var input = byId( 'sp-api-key' );
				if ( input ) { input.classList.add( 'is-ok' ); }
				if ( list.length ) {
					resultBox( 'ok', t( 'okTitle', 'Connected' ), t( 'okBody', 'Your key works and {count} models are available on this account.', { count: list.length } ) );
				} else {
					resultBox( 'ok', t( 'okTitle', 'Connected' ), t( 'okBodyNoList', 'Your key works.' ) );
					setFix( t( 'fixNoModels', '' ) );
				}
			}
			renderModels();
		} ).catch( function ( error ) {
			var data = error && error.data ? error.data : {};
			if ( fromCheck ) {
				stage( 'models', 'pending' );
				resultBox( 'ok', t( 'okTitle', 'Connected' ), t( 'okBodyNoList', 'Your key works.' ) );
			} else if ( status ) {
				status.textContent = data.code === 'models_unsupported' ? t( 'modelsUnsupported', '' ) : ( data.message || ( error && error.message ) || t( 'networkError', '' ) );
			}
			renderModels( ! fromCheck );
		} ).then( function () {
			if ( button ) { button.disabled = false; }
		} );
	}

	function modelIds() {
		var entry = preset();
		var ids = state.models ? state.models.slice() : Object.keys( entry.models || {} );
		if ( entry.model && ids.indexOf( entry.model ) > 0 ) {
			ids.splice( ids.indexOf( entry.model ), 1 );
			ids.unshift( entry.model );
		}
		return ids;
	}

	function renderModels( keepStatus ) {
		var list = one( '[data-sp-model-list]' );
		if ( ! list ) { return; }
		var entry = preset();
		var ids = modelIds();
		var current = value( 'sp-model' );
		var shown = state.showAllModels ? ids : ids.slice( 0, 3 );
		if ( current && ids.indexOf( current ) !== -1 && shown.indexOf( current ) === -1 ) { shown.push( current ); }

		list.textContent = '';
		shown.forEach( function ( id ) {
			var on = id === current;
			var row = document.createElement( 'button' );
			row.type = 'button';
			row.className = 'sp-wizard-model' + ( on ? ' is-on' : '' );
			row.setAttribute( 'role', 'radio' );
			row.setAttribute( 'aria-checked', on ? 'true' : 'false' );
			row.tabIndex = on || ( ! current && id === shown[ 0 ] ) ? 0 : -1;
			row.setAttribute( 'data-model', id );

			var radio = document.createElement( 'span' );
			radio.className = 'sp-wizard-radio';
			radio.setAttribute( 'aria-hidden', 'true' );
			var copy = document.createElement( 'span' );
			copy.className = 'sp-wizard-model__copy';
			var name = document.createElement( 'span' );
			name.className = 'sp-wizard-model__name';
			name.textContent = ( entry.models && entry.models[ id ] ) || id;
			var titleRow = document.createElement( 'span' );
			titleRow.className = 'sp-wizard-choice__title-row';
			titleRow.appendChild( name );
			if ( id === entry.model ) {
				var tag = document.createElement( 'span' );
				tag.className = 'sp-wizard-tag sp-wizard-tag--ok';
				tag.textContent = t( 'modelRecommended', 'Recommended' );
				titleRow.appendChild( tag );
			}
			var mono = document.createElement( 'span' );
			mono.className = 'sp-wizard-model__id sp-mono';
			mono.textContent = id;
			copy.appendChild( titleRow );
			copy.appendChild( mono );
			row.appendChild( radio );
			row.appendChild( copy );
			row.addEventListener( 'click', function () { chooseModel( id ); } );
			row.addEventListener( 'keydown', modelKeys );
			list.appendChild( row );
		} );
		list.hidden = ids.length === 0;

		var more = one( '[data-sp-model-more]' );
		if ( more ) {
			more.hidden = ids.length <= 3;
			more.textContent = state.showAllModels ? t( 'showFewerModels', 'Show fewer models' ) : t( 'showAllModels', 'Show all {count} models', { count: ids.length } );
		}

		var status = byId( 'sp-models-status' );
		if ( status && ! keepStatus ) {
			status.textContent = state.models
				? t( 'modelsFound', '{count} models available — we picked the one that fits a WordPress site', { count: state.models.length } )
				: ( ids.length ? t( 'modelsPreset', '' ) : t( 'modelsNone', '' ) );
		}
		var chosen = one( '[data-sp-model-chosen]' );
		if ( chosen ) { chosen.hidden = ! entry.model || current !== entry.model; }
	}

	function modelKeys( event ) {
		var rows = all( '[data-sp-model-list] [role="radio"]' );
		var index = rows.indexOf( event.currentTarget );
		var step = event.key === 'ArrowDown' || event.key === 'ArrowRight' ? 1 : event.key === 'ArrowUp' || event.key === 'ArrowLeft' ? -1 : 0;
		if ( ! step || index === -1 ) { return; }
		event.preventDefault();
		var target = rows[ ( index + step + rows.length ) % rows.length ];
		chooseModel( target.getAttribute( 'data-model' ) );
		var again = one( '[data-sp-model-list] [data-model="' + target.getAttribute( 'data-model' ) + '"]' );
		if ( again ) { again.focus(); }
	}

	function chooseModel( id ) {
		var input = byId( 'sp-model' );
		if ( input ) { input.value = id; }
		renderModels( true );
		modelChanged();
	}

	function modelChanged() {
		if ( state.testedModel !== null && value( 'sp-model' ) !== state.testedModel ) {
			if ( state.tested ) {
				state.tested = false;
				showChecklist( false );
				setFix( '' );
				setActions( [ { label: t( 'checkAgain', 'Check again' ), run: testProvider } ] );
				resultBox( 'warn', t( 'needRecheck', 'Check the connection with this model to continue' ), t( 'modelChanged', '' ) );
			}
		} else if ( state.testedModel !== null && value( 'sp-model' ) === state.testedModel && ! state.tested ) {
			state.tested = true;
			resultBox( 'ok', t( 'okTitle', 'Connected' ), state.models ? t( 'okBody', '', { count: state.models.length } ) : t( 'okBodyNoList', '' ) );
			setActions( [] );
		}
		render();
	}

	function resetTest() {
		if ( state.checking ) { return; }
		state.tested = false;
		state.testedModel = null;
		var box = byId( 'sp-setup-testbox' );
		if ( box ) { box.hidden = true; box.className = 'sp-wizard-result sp-testbox'; }
		var input = byId( 'sp-api-key' );
		if ( input ) { input.classList.remove( 'is-ok', 'is-bad' ); }
		setChecking( false );
		render();
	}

	function paintTemperature() {
		var range = byId( 'sp-temperature' );
		if ( ! range ) { return; }
		var number = parseFloat( range.value ) || 0;
		var out = one( '[data-sp-temperature-value]' );
		if ( out ) { out.textContent = number.toFixed( 2 ); }
		var note = one( '[data-sp-temperature-note]' );
		if ( note ) { note.textContent = number <= 0.3 ? t( 'tempLow', '' ) : number <= 0.6 ? t( 'tempMid', '' ) : t( 'tempHigh', '' ); }
		range.style.setProperty( '--sp-range-fill', ( number * 100 ) + '%' );
	}

	function paintEmbeddings() {
		var toggle = one( '[data-sp-embeddings]' );
		var warning = one( '[data-sp-embeddings-warning]' );
		if ( ! toggle || ! warning ) { return; }
		var entry = preset();
		var missing = Object.keys( entry.embeddings || {} ).length === 0;
		warning.hidden = ! ( toggle.checked && missing );
		warning.textContent = t( 'embedWarning', '', { provider: entry.label || providerKey() } );
	}

	// ── Step 4 and shared controls ───────────────────────────────────────

	function paintIntegration( input ) {
		var card = input.closest( '[data-sp-setup-integration]' );
		if ( ! card ) { return; }
		card.classList.toggle( 'is-on', input.checked );
		var tag = card.querySelector( '[data-sp-integration-state]' );
		if ( tag ) {
			tag.textContent = input.checked ? t( 'stateOn', 'On' ) : t( 'stateOff', 'Off' );
			tag.className = 'sp-wizard-tag sp-wizard-tag--' + ( input.checked ? 'ok' : 'neutral' );
		}
	}

	function bind() {
		all( 'input[name="key_management"]' ).forEach( function ( radio ) {
			radio.addEventListener( 'change', function () { syncKeyMode(); resetTest(); } );
		} );

		all( '[data-sp-provider-card]' ).forEach( function ( card ) {
			card.addEventListener( 'click', function () { selectProvider( card.getAttribute( 'data-provider' ) || '', true ); } );
		} );
		var search = byId( 'sp-setup-provider-search' );
		if ( search ) { search.addEventListener( 'input', filterProviders ); }
		var expand = one( '.sp-wizard-expand' );
		if ( expand ) { expand.addEventListener( 'click', function () { state.showAll = ! state.showAll; filterProviders(); } ); }
		var custom = one( '[data-sp-provider-custom]' );
		if ( custom ) {
			custom.addEventListener( 'click', function () {
				if ( search ) { search.value = ''; }
				selectProvider( 'custom', true );
			} );
		}

		var apiKey = byId( 'sp-api-key' );
		if ( apiKey ) {
			apiKey.addEventListener( 'input', resetTest );
			apiKey.addEventListener( 'keydown', function ( event ) {
				if ( event.key === 'Enter' ) { event.preventDefault(); testProvider(); }
			} );
		}
		var base = byId( 'sp-base-url' );
		if ( base ) { base.addEventListener( 'input', function () { resetTest(); paintKeyPanel(); } ); }
		var model = byId( 'sp-model' );
		if ( model ) {
			model.addEventListener( 'input', function () { renderModels( true ); modelChanged(); } );
			model.addEventListener( 'keydown', function ( event ) { if ( event.key === 'Enter' ) { event.preventDefault(); } } );
		}
		var clearKey = one( 'input[name="clear_api_key"]' );
		if ( clearKey ) { clearKey.addEventListener( 'change', resetTest ); }
		var test = byId( 'sp-test' );
		if ( test ) { test.addEventListener( 'click', testProvider ); }
		var refresh = byId( 'sp-refresh-models' );
		if ( refresh ) { refresh.addEventListener( 'click', function () { refreshModels( false ); } ); }
		var more = one( '[data-sp-model-more]' );
		if ( more ) { more.addEventListener( 'click', function () { state.showAllModels = ! state.showAllModels; renderModels( true ); } ); }
		var endpointToggle = one( '[data-sp-setup-endpoint-toggle]' );
		if ( endpointToggle ) {
			endpointToggle.addEventListener( 'click', function () {
				var open = endpointToggle.getAttribute( 'aria-expanded' ) !== 'true';
				setEndpointOpen( open );
				if ( open ) { focusField( 'sp-base-url' ); }
			} );
		}

		var range = byId( 'sp-temperature' );
		if ( range ) { range.addEventListener( 'input', paintTemperature ); }
		var embeddings = one( '[data-sp-embeddings]' );
		if ( embeddings ) { embeddings.addEventListener( 'change', paintEmbeddings ); }

		all( '[data-sp-accordion]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var open = button.getAttribute( 'aria-expanded' ) !== 'true';
				button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
				var body = byId( button.getAttribute( 'aria-controls' ) || '' );
				if ( body ) { body.hidden = ! open; }
			} );
		} );

		all( '[data-sp-integration-switch]' ).forEach( function ( input ) {
			input.addEventListener( 'change', function () { paintIntegration( input ); } );
		} );

		var back = one( '[data-sp-setup-back]' );
		if ( back ) { back.addEventListener( 'click', function () { if ( state.step > 1 ) { move( state.step - 1 ); } } ); }
		var next = one( '[data-sp-setup-next]' );
		if ( next ) {
			next.addEventListener( 'click', function () {
				if ( canContinue() ) {
					move( state.step + 1 );
					return;
				}
				render();
				if ( state.step === 2 && search ) { search.focus(); }
				if ( state.step === 3 ) { focusField( state.tested ? 'sp-model' : 'sp-api-key' ); }
			} );
		}
		var form = one( '[data-sp-setup-form]' );
		if ( form ) {
			form.addEventListener( 'submit', function ( event ) {
				if ( state.step !== 4 || ! state.tested || ! value( 'sp-model' ) ) {
					event.preventDefault();
					move( 3 );
				}
			} );
			// Enter in a text field must never submit the wizard from an early step.
			form.addEventListener( 'keydown', function ( event ) {
				if ( event.key === 'Enter' && event.target && event.target.tagName === 'INPUT' && state.step !== 4 ) { event.preventDefault(); }
			} );
		}
	}

	function init() {
		if ( state.tested ) { state.testedModel = value( 'sp-model' ); }
		selectProvider( providerKey(), false );
		syncKeyMode();
		paintTemperature();
		paintEmbeddings();
		bind();
		render();
	}

	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
}() );
