/* global AISAAutoPilotAI */
/**
 * Setup & Connection's four-step flow. It never stores a credential in the
 * browser: a key is sent only to the authenticated provider-test endpoint and
 * then to the normal nonce-protected settings form on explicit Save.
 */
( function () {
	'use strict';

	var root = document.querySelector( '[data-sp-setup-wizard]' );
	if ( ! root ) { return; }

	var cfg = window.AISAAutoPilotAI || {};
	var strings = cfg.i18n || {};
	var catalog = readCatalog();
	var providers = catalog.providers || {};
	var catalogStrings = catalog.i18n || {};
	var state = {
		step: root.getAttribute( 'data-saved' ) === '1' ? 5 : 1,
		tested: root.getAttribute( 'data-verified' ) === '1',
		filter: 'popular',
	};

	function t( key, fallback ) { return strings[ key ] || fallback || key; }
	function byId( id ) { return document.getElementById( id ); }
	function all( selector ) { return Array.prototype.slice.call( root.querySelectorAll( selector ) ); }

	function readCatalog() {
		var node = byId( 'sp-provider-catalog' );
		if ( ! node ) { return {}; }
		try {
			return JSON.parse( node.textContent || '{}' );
		} catch ( e ) {
			return {};
		}
	}

	function selectedKeyMode() {
		var radio = root.querySelector( 'input[name="key_management"]:checked' );
		return radio ? radio.value : '';
	}

	function currentProvider() {
		var field = byId( 'sp-provider' );
		return field ? field.value : '';
	}

	function preset( key ) { return providers[ key ] || {}; }
	function interpolate( template, values ) {
		return String( template ).replace( /\{([a-z_]+)\}/g, function ( match, key ) { return values[ key ] || match; } );
	}

	function draft() {
		var apiKey = byId( 'sp-api-key' );
		var payload = {
			provider: currentProvider(),
			base_url: value( 'sp-base-url' ),
			model: value( 'sp-model' ),
			key_management: selectedKeyMode(),
		};
		if ( apiKey && ! apiKey.disabled && apiKey.value ) { payload.api_key = apiKey.value; }
		var clearKey = root.querySelector( 'input[name="clear_api_key"]' );
		if ( clearKey && clearKey.checked ) { payload.clear_api_key = true; }
		return payload;
	}

	function value( id ) {
		var node = byId( id );
		return node ? node.value.trim() : '';
	}

	function post( path, body ) {
		if ( ! cfg.restUrl || ! cfg.nonce ) {
			return Promise.reject( new Error( t( 'networkError', 'Connection controls are unavailable.' ) ) );
		}
		return fetch( cfg.restUrl + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( body || {} ),
		} ).then( function ( response ) {
			return response.json().then( function ( data ) {
				if ( ! response.ok ) {
					var error = new Error( data && data.message ? data.message : 'HTTP ' + response.status );
					error.data = data;
					throw error;
				}
				return data;
			} );
		} );
	}

	function render() {
		var complete = root.querySelector( '[data-sp-setup-complete]' );
		var progress = root.querySelector( '[data-sp-setup-progress]' );
		var footer = root.querySelector( '[data-sp-setup-footer]' );

		all( '[data-sp-setup-step]' ).forEach( function ( panel ) {
			panel.hidden = state.step === 5 || Number( panel.getAttribute( 'data-sp-setup-step' ) ) !== state.step;
		} );
		if ( complete ) { complete.hidden = state.step !== 5; }
		if ( progress ) { progress.hidden = state.step === 5; }
		if ( footer ) { footer.hidden = state.step === 5; }

		all( '[data-sp-setup-progress-step]' ).forEach( function ( item ) {
			var number = Number( item.getAttribute( 'data-sp-setup-progress-step' ) );
			item.classList.toggle( 'is-current', number === state.step );
			item.classList.toggle( 'is-done', state.step !== 5 && number < state.step );
		} );

		var back = root.querySelector( '[data-sp-setup-back]' );
		var next = root.querySelector( '[data-sp-setup-next]' );
		var save = root.querySelector( '[data-sp-setup-save]' );
		if ( back ) { back.disabled = state.step <= 1; }
		if ( next ) { next.hidden = state.step === 4; }
		if ( save ) { save.hidden = state.step !== 4; }
		paintBlockNote();
	}

	function paintBlockNote() {
		var note = root.querySelector( '[data-sp-setup-block-note]' );
		if ( ! note ) { return; }
		if ( state.step === 1 && ! selectedKeyMode() ) {
			note.textContent = t( 'setupSelectStorage', 'Choose a key-storage option to continue.' );
		} else if ( state.step === 2 && ! currentProvider() ) {
			note.textContent = t( 'setupSelectProvider', 'Choose an AI service to continue.' );
		} else if ( state.step === 3 && ! state.tested ) {
			note.textContent = t( 'setupTestRequired', 'Test a working provider key and model before continuing.' );
		} else if ( state.step === 3 && ! value( 'sp-model' ) ) {
			note.textContent = t( 'setupModelRequired', 'Enter or choose a model before continuing.' );
		} else {
			note.textContent = '';
		}
	}

	function canContinue() {
		if ( state.step === 1 ) { return !! selectedKeyMode(); }
		if ( state.step === 2 ) { return !! currentProvider(); }
		if ( state.step === 3 ) { return state.tested && !! value( 'sp-model' ); }
		return true;
	}

	function move( nextStep ) {
		state.step = nextStep;
		render();
		var panel = root.querySelector( '[data-sp-setup-step="' + nextStep + '"]' );
		var heading = panel ? panel.querySelector( 'h2' ) : null;
		if ( heading ) { heading.setAttribute( 'tabindex', '-1' ); heading.focus(); }
	}

	function syncKeyMode() {
		var mode = selectedKeyMode();
		var keyField = byId( 'sp-api-key' );
		all( '[data-sp-key-mode]' ).forEach( function ( card ) {
			card.classList.toggle( 'is-selected', card.getAttribute( 'data-sp-key-mode' ) === mode );
		} );
		if ( keyField ) {
			keyField.disabled = mode === 'wp_connectors';
			if ( mode === 'wp_connectors' ) { keyField.value = ''; }
		}
		paintKeySource( preset( currentProvider() ) );
	}

	function paintKeySource( entry ) {
		var source = root.querySelector( '[data-sp-setup-key-source]' );
		if ( ! source ) { return; }
		source.textContent = '';
		if ( selectedKeyMode() === 'wp_connectors' ) {
			source.textContent = t( 'setupConnectorKey', 'This choice uses the key saved in WordPress Connectors.' );
			return;
		}
		source.appendChild( document.createTextNode( interpolate( t( 'setupGetKeyFrom', 'Get a key from {provider}.' ), { provider: entry.label || currentProvider() } ) + ' ' ) );
		if ( ! entry.keyUrl ) { return; }
		var link = document.createElement( 'a' );
		link.id = 'sp-key-link';
		link.href = entry.keyUrl;
		link.target = '_blank';
		link.rel = 'noopener noreferrer';
		link.textContent = catalogStrings.keyLink || 'Get an API key';
		source.appendChild( link );
	}

	function resetTest() {
		state.tested = false;
		paintTest( '', t( 'setupUntested', 'Not tested yet' ), t( 'setupUntestedBody', 'Run a short connection test before continuing.' ) );
		paintBlockNote();
	}

	function paintTest( stateName, title, body ) {
		var box = byId( 'sp-setup-testbox' );
		var mark = byId( 'sp-setup-testmark' );
		var titleNode = byId( 'sp-setup-testtitle' );
		var bodyNode = byId( 'sp-setup-testbody' );
		if ( ! box || ! titleNode || ! bodyNode ) { return; }

		box.className = 'sp-testbox' + ( stateName ? ' sp-testbox--' + stateName : '' );
		titleNode.textContent = title;
		bodyNode.textContent = body;
		if ( ! mark ) { return; }
		if ( stateName === 'busy' ) {
			mark.className = 'sp-spinner';
			mark.textContent = '';
			return;
		}
		mark.className = 'sp-mark';
		mark.textContent = stateName === 'ok' ? '✓' : ( stateName === 'err' ? '!' : '?' );
		mark.style.background = stateName === 'ok' ? '#059669' : ( stateName === 'err' ? '#dc2626' : '#1d2327' );
	}

	function paintFailure( data ) {
		var code = ( data && ( data.diagnostic || data.code ) ) || '';
		var titles = {
			no_api_key: t( 'setupNoKey', 'Add an API key' ),
			provider_unauthorized: t( 'setupUnauthorized', 'That key was rejected' ),
			provider_payment_required: t( 'setupPayment', 'Billing action required' ),
			provider_rate_limited: t( 'setupRateLimit', 'Provider is rate limiting' ),
			provider_unreachable: t( 'setupUnreachable', 'Your server could not reach the provider' ),
			model_not_found: t( 'setupNotFound', 'Wrong endpoint or model' ),
			invalid_config: t( 'setupIncomplete', 'Connection details are incomplete' ),
		};
		paintTest( 'err', titles[ code ] || t( 'testFail', 'Connection test failed' ), ( data && data.message ) || t( 'testFailBody', 'Check the connection details and try again.' ) );
	}

	function fillModels( models, extraLabel ) {
		var select = byId( 'sp-model-select' );
		var input = byId( 'sp-model' );
		if ( ! select || ! input ) { return; }
		var current = input.value.trim();
		var seen = {};
		select.textContent = '';
		Object.keys( models || {} ).forEach( function ( id ) {
			seen[ id ] = true;
			appendOption( select, id, models[ id ] || id );
		} );
		if ( current && ! seen[ current ] ) {
			var extra = document.createElement( 'option' );
			extra.value = current;
			extra.textContent = current + ( extraLabel ? ' — ' + extraLabel : '' );
			select.insertBefore( extra, select.firstChild );
		}
		appendOption( select, '__custom', catalogStrings.other || 'Other — type a model name' );
		select.value = current && optionExists( select, current ) ? current : '__custom';
	}

	function appendOption( select, id, label ) {
		var option = document.createElement( 'option' );
		option.value = id;
		option.textContent = label;
		select.appendChild( option );
	}

	function optionExists( select, current ) {
		return Array.prototype.some.call( select.options, function ( option ) { return option.value === current; } );
	}

	function selectProvider( key, overwrite ) {
		var field = byId( 'sp-provider' );
		var entry = preset( key );
		if ( ! field ) { return; }
		field.value = key;
		all( '[data-sp-provider-card]' ).forEach( function ( card ) {
			var selected = card.getAttribute( 'data-provider' ) === key;
			card.classList.toggle( 'is-selected', selected );
			card.setAttribute( 'aria-pressed', selected ? 'true' : 'false' );
		} );
		if ( overwrite ) {
			var base = byId( 'sp-base-url' );
			var model = byId( 'sp-model' );
			if ( base ) { base.value = key === 'custom' ? '' : ( entry.baseUrl || '' ); }
			if ( model ) { model.value = key === 'custom' ? '' : ( entry.model || '' ); }
		}
		fillModels( entry.models || {}, catalogStrings.saved || 'Saved model' );
		paintKeySource( entry );
		var meta = byId( 'sp-setup-testmeta' );
		if ( meta ) { meta.textContent = ( entry.label || key ) + ' · ' + ( entry.api === 'anthropic' ? 'messages' : 'chat/completions' ); }
		var note = root.querySelector( '[data-sp-setup-embeddings-note]' );
		if ( note ) {
			note.firstChild.textContent = Object.keys( entry.embeddings || {} ).length
				? t( 'setupEmbeddingsSupported', 'This provider supports embeddings. ' )
				: t( 'setupEmbeddingsUnsupported', 'This provider does not publish an embeddings endpoint. ' );
		}
		if ( overwrite ) { resetTest(); }
	}

	function filterProviders() {
		var input = byId( 'sp-setup-provider-search' );
		var query = input ? input.value.trim().toLowerCase() : '';
		var shown = 0;
		all( '[data-sp-provider-card]' ).forEach( function ( card ) {
			var matchesText = ! query || card.textContent.toLowerCase().indexOf( query ) !== -1;
			var isSelected = card.getAttribute( 'data-provider' ) === currentProvider();
			var matchesFilter = state.filter === 'all' || card.getAttribute( 'data-popular' ) === '1' || isSelected;
			var visible = matchesText && matchesFilter;
			card.hidden = ! visible;
			if ( visible ) { shown += 1; }
		} );
		var empty = root.querySelector( '[data-sp-provider-empty]' );
		if ( empty ) { empty.hidden = shown > 0; }
		all( '[data-sp-provider-filter]' ).forEach( function ( button ) {
			var active = button.getAttribute( 'data-sp-provider-filter' ) === state.filter;
			button.classList.toggle( 'is-active', active );
			button.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
		} );
	}

	function refreshModels( quiet ) {
		var button = byId( 'sp-refresh-models' );
		var status = byId( 'sp-models-status' );
		if ( button ) { button.disabled = true; }
		if ( status && ! quiet ) { status.textContent = catalogStrings.loading || 'Loading models…'; }
		return post( 'provider/models', draft() ).then( function ( data ) {
			if ( button ) { button.disabled = false; }
			if ( ! data || ! data.ok ) {
				if ( status ) { status.textContent = ( data && data.message ) || t( 'testFail', 'Could not load models.' ); }
				return;
			}
			if ( ! data.models || ! data.models.length ) {
				if ( status ) { status.textContent = t( 'setupModelsEmpty', 'The key works, but no models came back.' ); }
				return;
			}
			var models = {};
			data.models.forEach( function ( model ) { models[ model ] = model; } );
			fillModels( models, catalogStrings.saved || 'Saved model' );
			if ( status ) { status.textContent = data.message || ''; }
		} ).catch( function ( error ) {
			if ( button ) { button.disabled = false; }
			if ( status ) {
				var data = error && error.data ? error.data : {};
				status.textContent = data.code === 'models_unsupported'
					? t( 'setupModelsUnsupported', 'This provider does not publish a model list. Type its model name above.' )
					: ( data.message || ( error && error.message ) || t( 'networkError', 'Network error.' ) );
			}
		} );
	}

	function testProvider() {
		var button = byId( 'sp-test' );
		var result = byId( 'sp-test-result' );
		if ( button ) { button.disabled = true; }
		if ( result ) { result.textContent = ''; }
		paintTest( 'busy', t( 'testing', 'Testing…' ), t( 'testingBody', 'A short request was sent to the provider.' ) );
		post( 'provider/test', draft() ).then( function ( data ) {
			if ( button ) { button.disabled = false; }
			if ( data && data.ok ) {
				state.tested = true;
				paintTest( 'ok', t( 'testOk', 'Connection works' ), data.message || t( 'testOkBody', 'Valid response received. You can continue.' ) );
				refreshModels( true );
				render();
				return;
			}
			state.tested = false;
			paintFailure( data || {} );
			render();
		} ).catch( function ( error ) {
			if ( button ) { button.disabled = false; }
			state.tested = false;
			paintFailure( ( error && error.data ) || { message: ( error && error.message ) || t( 'networkError', 'Network error.' ) } );
			render();
		} );
	}

	function bind() {
		all( 'input[name="key_management"]' ).forEach( function ( radio ) {
			radio.addEventListener( 'change', function () { syncKeyMode(); resetTest(); } );
		} );
		all( '[data-sp-provider-card]' ).forEach( function ( card ) {
			card.addEventListener( 'click', function () { selectProvider( card.getAttribute( 'data-provider' ) || '', true ); } );
		} );
		all( '[data-sp-provider-filter]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () { state.filter = button.getAttribute( 'data-sp-provider-filter' ) || 'popular'; filterProviders(); } );
		} );
		var search = byId( 'sp-setup-provider-search' );
		if ( search ) { search.addEventListener( 'input', filterProviders ); }

		var modelPicker = byId( 'sp-model-select' );
		if ( modelPicker ) {
			modelPicker.addEventListener( 'change', function () {
				if ( modelPicker.value !== '__custom' ) {
					var model = byId( 'sp-model' );
					if ( model ) { model.value = modelPicker.value; }
				}
				resetTest();
			} );
		}
		[ 'sp-api-key', 'sp-base-url', 'sp-model', 'sp-temperature' ].forEach( function ( id ) {
			var field = byId( id );
			if ( field ) { field.addEventListener( 'input', resetTest ); }
		} );
		var clearKey = root.querySelector( 'input[name="clear_api_key"]' );
		if ( clearKey ) { clearKey.addEventListener( 'change', resetTest ); }
		var refresh = byId( 'sp-refresh-models' );
		if ( refresh ) { refresh.addEventListener( 'click', function () { refreshModels( false ); } ); }
		var test = byId( 'sp-test' );
		if ( test ) { test.addEventListener( 'click', testProvider ); }

		var back = root.querySelector( '[data-sp-setup-back]' );
		if ( back ) { back.addEventListener( 'click', function () { if ( state.step > 1 ) { move( state.step - 1 ); } } ); }
		var next = root.querySelector( '[data-sp-setup-next]' );
		if ( next ) {
			next.addEventListener( 'click', function () {
				if ( canContinue() ) { move( state.step + 1 ); } else { paintBlockNote(); }
			} );
		}
		var form = root.querySelector( '[data-sp-setup-form]' );
		if ( form ) {
			form.addEventListener( 'submit', function ( event ) {
				if ( state.step !== 4 || ! canContinue() ) {
					event.preventDefault();
					move( 3 );
				}
			} );
		}
	}

	function init() {
		selectProvider( currentProvider(), false );
		syncKeyMode();
		filterProviders();
		bind();
		render();
	}

	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
}() );
