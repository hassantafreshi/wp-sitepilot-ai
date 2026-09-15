/**
 * AISAAutoPilot shared shell helpers.
 *
 * Toast, dismissible banners and the running-job bar. Loaded on every
 * AISAAutoPilot admin screen before the per-screen scripts.
 *
 * @namespace AISAAutoPilotShell
 */
( function () {
	'use strict';

	var toastTimer = null;
	var strings = window.AISAAutoPilotShellConfig || {};

	function byId( id ) {
		return document.getElementById( id );
	}

	function string( key, fallback ) {
		return typeof strings[ key ] === 'string' && strings[ key ] ? strings[ key ] : fallback;
	}

	function interpolate( template, values ) {
		return String( template ).replace( /\{([a-z]+)\}/g, function ( match, key ) {
			return Object.prototype.hasOwnProperty.call( values, key ) ? String( values[ key ] ) : match;
		} );
	}

	/**
	 * Bottom-centre dark toast with a Dismiss button (mockup `toast`).
	 * Auto-clears after 4 seconds, matching the mockup's `say()`.
	 */
	function toast( msg ) {
		var host = byId( 'sp-toast' );
		if ( ! host ) {
			return;
		}
		clearTimeout( toastTimer );
		host.textContent = '';
		var text = document.createElement( 'span' );
		text.textContent = String( msg || '' );
		var dismiss = document.createElement( 'button' );
		dismiss.type = 'button';
		dismiss.className = 'sp-toast__dismiss';
		dismiss.textContent = string( 'dismiss', 'Dismiss' );
		dismiss.addEventListener( 'click', function () {
			host.hidden = true;
		} );
		host.appendChild( text );
		host.appendChild( dismiss );
		host.hidden = false;
		toastTimer = setTimeout( function () {
			host.hidden = true;
		}, 4000 );
	}

	/**
	 * Banner dismissal: hide for this browser session only. The server keeps
	 * deriving the banner from real state, so a fixed condition brings it back
	 * next session — dismissal never silences a real problem permanently.
	 */
	function initBanners() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-sp-banner]' ), function ( banner ) {
			var id = banner.getAttribute( 'data-sp-banner' ) || '';
			var key = 'sp-banner-' + id;
			try {
				if ( id && window.sessionStorage.getItem( key ) === '1' ) {
					banner.hidden = true;
				}
			} catch ( e ) { /* sessionStorage unavailable — banner stays. */ }
			var btn = banner.querySelector( '.sp-banner__dismiss' );
			if ( btn ) {
				btn.addEventListener( 'click', function () {
					banner.hidden = true;
					try {
						if ( id ) {
							window.sessionStorage.setItem( key, '1' );
						}
					} catch ( e ) { /* ignore */ }
				} );
			}
		} );
	}

	/**
	 * Running-job bar. `start()` shows the bar, `update()` fills label/percent,
	 * pause/stop dispatch CustomEvents a batch loop can listen to, and the bar
	 * hides again on `finish()`.
	 */
	var job = {
		state: null,

		start: function ( label, total, unit ) {
			var host = byId( 'sp-jobbar' );
			if ( ! host ) {
				return;
			}
			this.state = { label: label || '', total: total || 0, unit: unit || 'items', pct: 0, paused: false, stopped: false };
			host.hidden = false;
			this.update( 0 );
		},

		update: function ( pct, doneCount ) {
			var host = byId( 'sp-jobbar' );
			if ( ! host || ! this.state ) {
				return;
			}
			var s = this.state;
			s.pct = Math.max( 0, Math.min( 100, Number( pct ) || 0 ) );
			var label = byId( 'sp-jobbar-label' );
			var eta = byId( 'sp-jobbar-eta' );
			var pctEl = byId( 'sp-jobbar-pct' );
			var fill = byId( 'sp-jobbar-fill' );
			var done = typeof doneCount === 'number' ? doneCount : Math.round( ( s.pct / 100 ) * s.total );
			if ( label ) {
				label.textContent = interpolate( string( 'progress', '{label} — {done} of {total} {unit}' ), {
					label: s.label,
					done: done,
					total: s.total,
					unit: s.unit
				} );
			}
			if ( eta ) {
				eta.textContent = s.paused
					? string( 'paused', 'Paused — nothing is running' )
					: interpolate( string( 'eta', 'About {seconds} seconds left · you can leave this page, it keeps running' ), {
						seconds: Math.max( 4, Math.round( ( 100 - s.pct ) * 0.7 ) )
					} );
			}
			if ( pctEl ) {
				pctEl.textContent = Math.round( s.pct ) + '%';
			}
			if ( fill ) {
				fill.style.width = Math.round( s.pct ) + '%';
			}
		},

		pause: function () {
			if ( ! this.state ) {
				return;
			}
			this.state.paused = ! this.state.paused;
			var btn = byId( 'sp-jobbar-pause' );
			if ( btn ) {
				btn.textContent = this.state.paused ? string( 'resume', 'Resume' ) : string( 'pause', 'Pause' );
			}
			document.dispatchEvent( new CustomEvent( 'sp-job-pause', { detail: { paused: this.state.paused } } ) );
			this.update( this.state.pct );
		},

		stop: function () {
			if ( ! this.state ) {
				return;
			}
			this.state.stopped = true;
			document.dispatchEvent( new CustomEvent( 'sp-job-stop', { detail: {} } ) );
			this.finish();
		},

		finish: function () {
			var host = byId( 'sp-jobbar' );
			if ( host ) {
				host.hidden = true;
			}
			this.state = null;
		}
	};

	function initJobbar() {
		var pause = byId( 'sp-jobbar-pause' );
		var stop = byId( 'sp-jobbar-stop' );
		if ( pause ) {
			pause.addEventListener( 'click', function () {
				job.pause();
			} );
		}
		if ( stop ) {
			stop.addEventListener( 'click', function () {
				job.stop();
			} );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initBanners();
			initJobbar();
		} );
	} else {
		initBanners();
		initJobbar();
	}

	window.AISAAutoPilotShell = {
		toast: toast,
		job: job
	};
}() );
