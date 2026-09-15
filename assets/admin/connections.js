/* global AISAAutoPilotAI */
/**
 * Canonical Health interaction.
 *
 * The screen animates the mockup's queued/checking sequence while one
 * saved-config diagnostics request runs. Server data is applied with
 * textContent only, so provider details cannot become markup.
 */
( function () {
	'use strict';

	var root = document.querySelector( '[data-sp-connections]' );
	var button = document.getElementById( 'sp-run-diagnostics' );
	var list = document.getElementById( 'sp-health-list' );
	if ( ! root || ! button || ! list ) { return; }

	var rows = Array.prototype.slice.call( list.querySelectorAll( '[data-sp-health-row]' ) );
	if ( 8 !== rows.length ) { return; }

	var cfg = window.AISAAutoPilotAI || {};
	var T = ( function () {
		try {
			var node = document.getElementById( 'sp-conn-i18n' );
			return node ? ( JSON.parse( node.textContent || '{}' ) || {} ) : {};
		} catch ( e ) {
			return {};
		}
	}() );
	var label = button.querySelector( 'span' );
	var status = document.getElementById( 'sp-health-status' );
	var busy = false;
	var runTimer = 0;
	var initial = [];

	function t( key, fallback ) {
		return T[ key ] || fallback || key;
	}

	function announce( message ) {
		if ( status ) { status.textContent = message; }
	}

	function tone( value ) {
		return [ 'ok', 'warn', 'bad', 'idle' ].indexOf( value ) > -1 ? value : 'idle';
	}

	function rowParts( row ) {
		return {
			detail: row.querySelector( '[data-sp-health-detail]' ),
			state: row.querySelector( '[data-sp-health-state]' ),
		};
	}

	function paint( row, nextTone, nextState, nextDetail ) {
		var parts = rowParts( row );
		row.className = 'sp-health-check sp-health-check--' + tone( nextTone );
		if ( parts.state ) { parts.state.textContent = nextState; }
		if ( parts.detail ) { parts.detail.textContent = nextDetail; }
	}

	function capture() {
		initial = rows.map( function ( row ) {
			var parts = rowParts( row );
			return {
				className: row.className,
				state: parts.state ? parts.state.textContent : '',
				detail: parts.detail ? parts.detail.textContent : '',
			};
		} );
	}

	function restore() {
		rows.forEach( function ( row, index ) {
			var saved = initial[ index ];
			if ( ! saved ) { return; }
			row.className = saved.className;
			var parts = rowParts( row );
			if ( parts.state ) { parts.state.textContent = saved.state; }
			if ( parts.detail ) { parts.detail.textContent = saved.detail; }
		} );
	}

	function setBusy( isBusy ) {
		busy = !!isBusy;
		button.disabled = busy;
		button.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
		root.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
		if ( label ) { label.textContent = busy ? t( 'busy', 'Checking…' ) : t( 'button', 'Run all checks' ); }
	}

	function setProgress( activeIndex ) {
		rows.forEach( function ( row, index ) {
			if ( index === activeIndex ) {
				paint( row, 'idle', t( 'checking', 'Checking…' ), t( 'checking', 'Checking…' ) );
				return;
			}
			if ( index > activeIndex ) {
				paint( row, 'idle', t( 'queued', 'Queued' ), t( 'waiting', 'Waiting' ) );
				return;
			}

			var saved = initial[ index ];
			if ( saved ) {
				row.className = saved.className;
				var parts = rowParts( row );
				if ( parts.state ) { parts.state.textContent = saved.state; }
				if ( parts.detail ) { parts.detail.textContent = saved.detail; }
			}
		} );
	}

	function reportById( report ) {
		var data = report && report.checks;
		if ( ! Array.isArray( data ) || 8 !== data.length ) {
			throw new Error( t( 'failed', 'Health checks could not be completed. Please try again.' ) );
		}

		var byId = {};
		data.forEach( function ( item ) {
			if ( item && typeof item.id === 'string' ) {
				byId[ item.id ] = item;
			}
		} );
		rows.forEach( function ( row ) {
			var id = row.getAttribute( 'data-sp-health-id' ) || '';
			var item = byId[ id ];
			if ( ! item || typeof item.state !== 'string' || typeof item.detail !== 'string' ) {
				throw new Error( t( 'failed', 'Health checks could not be completed. Please try again.' ) );
			}
			paint( row, typeof item.tone === 'string' ? item.tone : 'idle', item.state, item.detail );
		} );
	}

	function fetchReport() {
		if ( ! cfg.restUrl || ! cfg.nonce ) {
			return Promise.reject( new Error( t( 'failed', 'Health checks could not be completed. Please try again.' ) ) );
		}

		return fetch( cfg.restUrl + 'health/diagnostics', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce,
			},
			body: JSON.stringify( {} ),
		} ).then( function ( response ) {
			return response.json().catch( function () {
				return {};
			} ).then( function ( data ) {
				if ( ! response.ok || ! data || ! data.ok || ! data.report ) {
					var error = new Error( ( data && data.message ) || t( 'failed', 'Health checks could not be completed. Please try again.' ) );
					error.data = data;
					throw error;
				}
				return data.report;
			} );
		} );
	}

	button.addEventListener( 'click', function () {
		if ( busy ) { return; }

		capture();
		setBusy( true );
		announce( t( 'checking', 'Checking…' ) );

		var animationDone = false;
		var result = null;
		var failure = null;

		function finishIfReady() {
			if ( ! animationDone || ! busy ) { return; }
			window.clearTimeout( runTimer );
			if ( failure ) {
				restore();
				setBusy( false );
				announce( failure.message || t( 'failed', 'Health checks could not be completed. Please try again.' ) );
				return;
			}
			if ( ! result ) { return; }

			try {
				reportById( result );
				setBusy( false );
				announce( t( 'completed', 'Eight checks finished.' ) );
			} catch ( error ) {
				restore();
				setBusy( false );
				announce( error.message || t( 'failed', 'Health checks could not be completed. Please try again.' ) );
			}
		}

		function advance( index ) {
			if ( ! busy ) { return; }
			setProgress( index );
			if ( index >= rows.length - 1 ) {
				animationDone = true;
				finishIfReady();
				return;
			}
			runTimer = window.setTimeout( function () {
				advance( index + 1 );
			}, 130 );
		}

		advance( 0 );
		fetchReport().then( function ( report ) {
			result = report;
			finishIfReady();
		} ).catch( function ( error ) {
			failure = error instanceof Error
				? error
				: new Error( t( 'failed', 'Health checks could not be completed. Please try again.' ) );
			finishIfReady();
		} );
	} );
}() );
