/* global AISAAutoPilotAI */
( function () {
	'use strict';

	var cfg = window.AISAAutoPilotAI || {};
	var root = document.querySelector( '[data-sp-store-copilot]' );
	if ( ! root ) { return; }

	var actionId = '';
	var expiresAt = 0;
	var expiryTimer = 0;
	var settingsBusy = false;
	var settings = {
		enabled: root.getAttribute( 'data-sp-store-enabled' ) === '1',
		enable_search: root.getAttribute( 'data-sp-store-search' ) === '1',
		enable_descriptions: root.getAttribute( 'data-sp-store-descriptions' ) === '1',
		cart_funnel: root.getAttribute( 'data-sp-store-cart-funnel' ) === '1'
	};
	var i18n = readJson( 'sp-store-i18n' );

	function readJson( id ) {
		var node = document.getElementById( id );
		if ( ! node ) { return {}; }
		try { return JSON.parse( node.textContent || '{}' ); } catch ( error ) { return {}; }
	}

	function t( key, fallback ) {
		return typeof i18n[ key ] === 'string' && i18n[ key ] ? i18n[ key ] : fallback;
	}

	function byId( id ) { return document.getElementById( id ); }

	function setStatus( message, tone ) {
		var node = byId( 'sp-woo-plan-status' );
		if ( ! node ) { return; }
		node.className = 'sp-status-text' + ( tone === 'ok' ? ' is-ok' : ( tone === 'error' ? ' is-err' : '' ) );
		node.textContent = message || '';
	}

	function request( endpoint, payload ) {
		return fetch( ( cfg.restUrl || '' ) + endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce || ''
			},
			body: JSON.stringify( payload || {} )
		} ).then( function ( response ) {
			return response.json().catch( function () { return {}; } ).then( function ( data ) {
				data.httpStatus = response.status;
				return data;
			} );
		} );
	}

	function clear( node ) {
		if ( node ) { node.replaceChildren(); }
	}

	function appendText( parent, tag, className, value ) {
		var node = document.createElement( tag );
		if ( className ) { node.className = className; }
		node.textContent = value || '';
		parent.appendChild( node );
		return node;
	}

	function field( target, label, value, locked ) {
		if ( value === undefined || value === null || value === '' ) { return; }
		var row = document.createElement( 'div' );
		row.className = 'sp-kv__row sp-kv__row--ruled';
		appendText( row, 'span', 'sp-kv__k sp-mono', label );
		appendText( row, 'span', 'sp-kv__v sp-mono', Array.isArray( value ) ? value.join( ', ' ) : String( value ) );
		if ( locked ) { appendText( row, 'span', 'sp-store-field-lock', locked ); }
		target.appendChild( row );
	}

	function errorMessage( data ) {
		if ( data && Array.isArray( data.errors ) && data.errors.length ) { return data.errors.join( ' ' ); }
		return ( data && ( data.message || data.error ) ) || t( 'network_error', 'The request could not be completed.' );
	}

	function formatRemaining( milliseconds ) {
		var seconds = Math.max( 0, Math.ceil( milliseconds / 1000 ) );
		var minutes = Math.floor( seconds / 60 );
		return minutes + ':' + String( seconds % 60 ).padStart( 2, '0' );
	}

	function stopExpiryTimer() {
		if ( expiryTimer ) { window.clearInterval( expiryTimer ); }
		expiryTimer = 0;
		expiresAt = 0;
	}

	function updateExpiry() {
		var ttl = byId( 'sp-woo-ttl' );
		if ( ! ttl || ! expiresAt ) { return; }
		var remaining = expiresAt - Date.now();
		if ( remaining <= 0 ) {
			stopExpiryTimer();
			actionId = '';
			var review = byId( 'sp-woo-review' );
			if ( review ) { review.hidden = true; }
			// T16: the result box is the live region, so the status line stays empty instead of repeating it.
			showResult( 'warn', t( 'proposal_expired', 'This proposal expired before it was approved. Nothing was created.' ), '' );
			setStatus( '', '' );
			return;
		}
		// T16: every visible string comes from sp-store-i18n, so it follows the admin language.
		ttl.textContent = t( 'expires_in', 'Expires in %s' ).replace( '%s', formatRemaining( remaining ) );
	}

	function showProposal( plan, expiry ) {
		var review = byId( 'sp-woo-review' );
		var summary = byId( 'sp-woo-review-summary' );
		var fields = byId( 'sp-woo-review-fields' );
		if ( ! review || ! summary || ! fields ) { return; }

		clear( summary );
		appendText( summary, 'h3', 'sp-store-proposal__name', plan.name || '' );
		if ( plan.short_description ) { appendText( summary, 'p', 'sp-store-proposal__short', plan.short_description ); }
		if ( plan.description ) { appendText( summary, 'p', 'sp-store-proposal__body', plan.description ); }

		clear( fields );
		field( fields, 'type', plan.type || 'simple', t( 'lock_simple', 'Phase 1 creates simple products only' ) );
		field( fields, 'status', 'draft', t( 'lock_draft', 'Phase 1 always creates a draft' ) );
		field( fields, 'regular_price', plan.regular_price || plan.price );
		field( fields, 'sale_price', plan.sale_price || '-' );
		field( fields, 'sku', plan.sku );
		field( fields, 'stock_status', plan.stock_status );
		field( fields, 'stock_quantity', plan.manage_stock ? plan.stock_quantity : t( 'not_managed', 'Not managed' ) );
		field( fields, 'categories', plan.categories );
		field( fields, 'weight', plan.weight );

		review.hidden = false;
		stopExpiryTimer();
		expiresAt = Number( expiry || 0 ) * 1000;
		updateExpiry();
		if ( expiresAt ) { expiryTimer = window.setInterval( updateExpiry, 1000 ); }
	}

	function showResult( tone, title, body, link ) {
		var result = byId( 'sp-woo-result' );
		if ( ! result ) { return; }
		clear( result );
		result.className = 'sp-store-result sp-alert sp-alert--' + ( tone || 'ok' );
		var content = document.createElement( 'span' );
		appendText( content, 'strong', '', title );
		if ( body ) { appendText( content, 'span', 'sp-store-result__body', body ); }
		result.appendChild( content );
		if ( link && link.href && link.label ) {
			var anchor = document.createElement( 'a' );
			anchor.className = 'sp-btn sp-btn--xs';
			anchor.href = link.href;
			anchor.textContent = link.label;
			result.appendChild( anchor );
		}
		result.hidden = false;
	}

	function hideResult() {
		var result = byId( 'sp-woo-result' );
		if ( ! result ) { return; }
		result.hidden = true;
		clear( result );
	}

	function resetProposal() {
		actionId = '';
		stopExpiryTimer();
		var review = byId( 'sp-woo-review' );
		if ( review ) { review.hidden = true; }
		hideResult();
		setStatus( '', '' );
	}

	function plan() {
		var prompt = byId( 'sp-woo-prompt' );
		var button = byId( 'sp-woo-plan' );
		if ( ! prompt || ! button || button.disabled ) { return; }
		if ( ! prompt.value.trim() ) {
			setStatus( t( 'prompt_required', 'Describe the product you want to draft.' ), 'error' );
			return;
		}

		button.disabled = true;
		setStatus( t( 'proposing', 'Writing the proposal...' ), '' );
		hideResult();
		request( 'woocommerce/plan', { prompt: prompt.value.trim() } ).then( function ( data ) {
			if ( ! data.ok || ! data.action_id || ! data.plan ) {
				setStatus( '', '' );
				showResult( 'err', errorMessage( data ), '' );
				return;
			}
			actionId = data.action_id;
			showProposal( data.plan, data.expires_at );
			setStatus( t( 'proposal_ready', 'Read the proposal before creating the draft.' ), 'ok' );
		} ).catch( function () {
			setStatus( t( 'network_error', 'Network error. Please try again.' ), 'error' );
		} ).finally( function () { button.disabled = false; } );
	}

	function approve() {
		var button = byId( 'sp-woo-approve' );
		if ( ! actionId || ! button || button.disabled ) { return; }
		button.disabled = true;
		setStatus( t( 'creating', 'Creating the WooCommerce draft...' ), '' );
		request( 'woocommerce/execute', { action_id: actionId } ).then( function ( data ) {
			if ( ! data.ok ) {
				setStatus( '', '' );
				showResult( 'err', errorMessage( data ), '' );
				return;
			}
			actionId = '';
			stopExpiryTimer();
			var review = byId( 'sp-woo-review' );
			if ( review ) { review.hidden = true; }
			showResult(
				'ok',
				data.message || t( 'draft_created', 'Draft created. Publishing remains a manual WooCommerce step.' ),
				'',
				data.edit_url ? { href: data.edit_url, label: t( 'review_draft', 'Review product draft' ) } : null
			);
			setStatus( '', '' );
		} ).catch( function () {
			setStatus( t( 'network_error', 'Network error. Please try again.' ), 'error' );
		} ).finally( function () { button.disabled = false; } );
	}

	function applySettings( next ) {
		settings.enabled = !! next.enabled;
		settings.enable_search = !! next.enable_search;
		settings.enable_descriptions = !! next.enable_descriptions;
		settings.cart_funnel = !! next.cart_funnel;
		root.setAttribute( 'data-sp-store-cart-funnel', settings.cart_funnel ? '1' : '0' );
		root.setAttribute( 'data-sp-store-enabled', settings.enabled ? '1' : '0' );
		root.setAttribute( 'data-sp-store-search', settings.enable_search ? '1' : '0' );
		root.setAttribute( 'data-sp-store-descriptions', settings.enable_descriptions ? '1' : '0' );
		Object.keys( settings ).forEach( function ( key ) {
			var button = root.querySelector( '[data-sp-store-setting="' + key + '"]' );
			if ( ! button ) { return; }
			button.classList.toggle( 'is-on', settings[ key ] );
			button.setAttribute( 'aria-checked', settings[ key ] ? 'true' : 'false' );
		} );
		updateProposalAvailability();
	}

	function updateProposalAvailability() {
		var canPlan = settings.enabled
			&& root.getAttribute( 'data-sp-store-active' ) === '1'
			&& root.getAttribute( 'data-sp-store-provider' ) === '1'
			&& root.getAttribute( 'data-sp-store-can-manage' ) === '1';
		[ byId( 'sp-woo-prompt' ), byId( 'sp-woo-plan' ) ].forEach( function ( node ) {
			if ( node ) { node.disabled = ! canPlan; }
		} );
	}

	function setSettingsBusy( busy ) {
		settingsBusy = busy;
		root.querySelectorAll( '[data-sp-store-setting]' ).forEach( function ( button ) { button.disabled = busy; } );
	}

	function saveSetting( key ) {
		if ( settingsBusy || ! Object.prototype.hasOwnProperty.call( settings, key ) ) { return; }
		var previous = { enabled: settings.enabled, enable_search: settings.enable_search, enable_descriptions: settings.enable_descriptions, cart_funnel: settings.cart_funnel };
		settings[ key ] = ! settings[ key ];
		applySettings( settings );
		setSettingsBusy( true );
		var status = root.querySelector( '[data-sp-store-settings-status]' );
		if ( status ) { status.textContent = ''; status.className = 'sp-status-text'; }
		request( 'woocommerce/admin-settings', settings ).then( function ( data ) {
			if ( ! data.ok || ! data.settings ) { throw new Error( errorMessage( data ) ); }
			applySettings( data.settings );
			if ( status ) { status.textContent = t( 'settings_saved', 'Store Copilot settings saved.' ); status.className = 'sp-status-text is-ok'; }
		} ).catch( function () {
			applySettings( previous );
			if ( status ) { status.textContent = t( 'settings_failed', 'The Store Copilot settings could not be saved.' ); status.className = 'sp-status-text is-err'; }
		} ).finally( function () { setSettingsBusy( false ); } );
	}

	function init() {
		var planButton = byId( 'sp-woo-plan' );
		var approveButton = byId( 'sp-woo-approve' );
		var regenerateButton = byId( 'sp-woo-regenerate' );
		var discardButton = byId( 'sp-woo-cancel' );
		if ( planButton ) { planButton.addEventListener( 'click', plan ); }
		if ( approveButton ) { approveButton.addEventListener( 'click', approve ); }
		if ( regenerateButton ) { regenerateButton.addEventListener( 'click', plan ); }
		if ( discardButton ) { discardButton.addEventListener( 'click', resetProposal ); }
		root.querySelectorAll( '[data-sp-store-example]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var prompt = byId( 'sp-woo-prompt' );
				if ( ! prompt || prompt.disabled ) { return; }
				prompt.value = button.getAttribute( 'data-sp-store-example' ) || '';
				prompt.focus();
			} );
		} );
		root.querySelectorAll( '[data-sp-store-setting]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () { saveSetting( button.getAttribute( 'data-sp-store-setting' ) || '' ); } );
		} );
	}

	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
}() );
