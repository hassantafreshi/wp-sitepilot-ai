/* global SitePilotAI */
( function () {
	'use strict';

	var cfg = window.SitePilotAI || {};
	var actionId = '';

	function byId( id ) { return document.getElementById( id ); }
	function status( message, type ) {
		var node = byId( 'sp-woo-plan-status' );
		if ( ! node ) { return; }
		node.className = 'sitepilot-status' + ( type ? ' sitepilot-status--' + type : '' );
		node.textContent = message;
	}
	function request( endpoint, payload ) {
		return fetch( cfg.restUrl + endpoint, {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( payload || {} )
		} ).then( function ( response ) {
			return response.json().catch( function () { return {}; } );
		} );
	}
	function text( value ) { return String( value || '' ); }
	function field( target, label, value ) {
		if ( ! value && value !== 0 ) { return; }
		var dt = document.createElement( 'dt' );
		var dd = document.createElement( 'dd' );
		dt.textContent = label;
		dd.textContent = Array.isArray( value ) ? value.join( ', ' ) : text( value );
		target.appendChild( dt );
		target.appendChild( dd );
	}
	function showReview( plan ) {
		var fields = byId( 'sp-woo-review-fields' );
		var review = byId( 'sp-woo-review' );
		var compose = byId( 'sp-woo-compose' );
		if ( ! fields || ! review || ! compose ) { return; }
		fields.replaceChildren();
		field( fields, 'Name', plan.name );
		field( fields, 'Description', plan.description );
		field( fields, 'Short description', plan.short_description );
		field( fields, 'Regular price', plan.regular_price || plan.price );
		field( fields, 'Sale price', plan.sale_price );
		field( fields, 'Categories', plan.categories );
		field( fields, 'SKU', plan.sku );
		field( fields, 'Stock status', plan.stock_status );
		field( fields, 'Stock quantity', plan.manage_stock ? plan.stock_quantity : 'Not managed' );
		field( fields, 'Status', 'Draft' );
		compose.hidden = true;
		review.hidden = false;
	}
	function reset() {
		actionId = '';
		var review = byId( 'sp-woo-review' );
		var compose = byId( 'sp-woo-compose' );
		var result = byId( 'sp-woo-result' );
		if ( review ) { review.hidden = true; }
		if ( compose ) { compose.hidden = false; }
		if ( result ) { result.hidden = true; result.replaceChildren(); }
		status( '', '' );
	}
	function showResult( data ) {
		var result = byId( 'sp-woo-result' );
		if ( ! result ) { return; }
		result.replaceChildren();
		var message = document.createElement( 'p' );
		message.textContent = data.message || 'Product draft created.';
		result.appendChild( message );
		if ( data.edit_url ) {
			var link = document.createElement( 'a' );
			link.className = 'button button-primary';
			link.href = data.edit_url;
			link.textContent = 'Review product draft';
			result.appendChild( link );
		}
		result.hidden = false;
		var review = byId( 'sp-woo-review' );
		if ( review ) { review.hidden = true; }
		status( 'Draft created.', 'ok' );
	}
	function errorMessage( data ) {
		if ( Array.isArray( data.errors ) && data.errors.length ) { return data.errors.join( ' ' ); }
		return data.message || 'The request could not be completed.';
	}
	function plan() {
		var prompt = byId( 'sp-woo-prompt' );
		var button = byId( 'sp-woo-plan' );
		if ( ! prompt || ! button ) { return; }
		if ( ! prompt.value.trim() ) { status( 'Describe the product you want to draft.', 'error' ); return; }
		button.disabled = true;
		status( 'Generating a draft proposal…', '' );
		request( 'woocommerce/plan', { prompt: prompt.value.trim() } ).then( function ( data ) {
			if ( ! data.ok || ! data.action_id || ! data.plan ) { status( errorMessage( data ), 'error' ); return; }
			actionId = data.action_id;
			showReview( data.plan );
			status( 'Review the proposal before creating the draft.', 'ok' );
		} ).catch( function () { status( 'Network error. Please try again.', 'error' ); } ).finally( function () { button.disabled = false; } );
	}
	function approve() {
		var button = byId( 'sp-woo-approve' );
		if ( ! actionId || ! button ) { status( 'Generate a new proposal before approving.', 'error' ); return; }
		button.disabled = true;
		status( 'Creating the WooCommerce draft…', '' );
		request( 'woocommerce/execute', { action_id: actionId } ).then( function ( data ) {
			if ( ! data.ok ) { status( errorMessage( data ), 'error' ); return; }
			actionId = '';
			showResult( data );
		} ).catch( function () { status( 'Network error. Please try again.', 'error' ); } ).finally( function () { button.disabled = false; } );
	}
	function init() {
		var planButton = byId( 'sp-woo-plan' );
		var approveButton = byId( 'sp-woo-approve' );
		var cancelButton = byId( 'sp-woo-cancel' );
		if ( planButton ) { planButton.addEventListener( 'click', plan ); }
		if ( approveButton ) { approveButton.addEventListener( 'click', approve ); }
		if ( cancelButton ) { cancelButton.addEventListener( 'click', reset ); }
	}
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
}() );
