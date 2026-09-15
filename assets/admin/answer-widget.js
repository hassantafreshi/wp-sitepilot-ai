/* global AISAAutoPilotAI */
/**
 * Immediate, non-destructive updates for the compact Answer Desk Widget tab.
 */
( function () {
	'use strict';

	var root = document.querySelector( '[data-sp-widget-appearance]' );
	if ( ! root ) { return; }

	var config = window.AISAAutoPilotAI || {};
	var status = root.querySelector( '[data-sp-widget-save-status]' );
	var enabledControl = root.querySelector( '[data-sp-widget-enabled]' );
	var preview = root.querySelector( '[data-sp-widget-preview]' );

	function strings() {
		return config.i18n || {};
	}

	function setStatus( message, state ) {
		if ( ! status ) { return; }
		status.textContent = message;
		status.setAttribute( 'data-state', state || '' );
	}

	function isEnabled() {
		return root.getAttribute( 'data-sp-widget-enabled' ) === '1';
	}

	function color() {
		return root.getAttribute( 'data-sp-widget-color' ) || '#202a8d';
	}

	function setBusy( busy ) {
		if ( enabledControl ) { enabledControl.disabled = busy; }
		Array.prototype.forEach.call( root.querySelectorAll( '[data-sp-widget-color]' ), function ( swatch ) {
			if ( root.getAttribute( 'data-sp-widget-custom-colors' ) !== '1' ) {
				swatch.disabled = busy;
			}
		} );
	}

	function render( nextEnabled, nextColor ) {
		root.setAttribute( 'data-sp-widget-enabled', nextEnabled ? '1' : '0' );
		root.setAttribute( 'data-sp-widget-color', nextColor );
		if ( enabledControl ) {
			enabledControl.classList.toggle( 'is-on', nextEnabled );
			enabledControl.setAttribute( 'aria-checked', nextEnabled ? 'true' : 'false' );
		}
		if ( preview ) {
			preview.style.setProperty( '--sp-answer-widget-accent', nextColor );
		}
		Array.prototype.forEach.call( root.querySelectorAll( '[data-sp-widget-color]' ), function ( swatch ) {
			var selected = swatch.getAttribute( 'data-sp-widget-color' ) === nextColor;
			swatch.classList.toggle( 'is-on', selected );
			swatch.setAttribute( 'aria-pressed', selected ? 'true' : 'false' );
		} );
	}

	function save( nextEnabled, nextColor ) {
		if ( ! config.restUrl || ! config.nonce ) {
			setStatus( strings().widgetSaveFailed || 'Could not save the Widget setting. Please try again.', 'error' );
			return;
		}

		setBusy( true );
		setStatus( strings().widgetSaving || 'Saving...', 'saving' );
		window.fetch( config.restUrl + 'widget/admin-settings', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce,
			},
			body: JSON.stringify( {
				enabled: nextEnabled,
				color: nextColor,
			} ),
		} )
			.then( function ( response ) {
				return response.json().catch( function () { return {}; } ).then( function ( body ) {
					if ( ! response.ok || ! body.ok || ! body.widget ) {
						throw new Error( body.message || 'widget_save_failed' );
					}
					return body.widget;
				} );
			} )
			.then( function ( widget ) {
				render( !! widget.enabled, String( widget.color || nextColor ) );
				setStatus( strings().widgetSaved || 'Saved.', 'saved' );
			} )
			.catch( function () {
				setStatus( strings().widgetSaveFailed || 'Could not save the Widget setting. Please try again.', 'error' );
			} )
			.finally( function () {
				setBusy( false );
			} );
	}

	if ( enabledControl ) {
		enabledControl.addEventListener( 'click', function () {
			save( ! isEnabled(), color() );
		} );
	}

	Array.prototype.forEach.call( root.querySelectorAll( '[data-sp-widget-color]' ), function ( swatch ) {
		swatch.addEventListener( 'click', function () {
			if ( swatch.disabled ) { return; }
			save( isEnabled(), swatch.getAttribute( 'data-sp-widget-color' ) || color() );
		} );
	} );
}() );
