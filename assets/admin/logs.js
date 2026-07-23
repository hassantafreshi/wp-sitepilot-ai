/**
 * SitePilot AI — Activity Log
 * Client-side filtering (search + level) and copy-to-clipboard for trace IDs.
 * No network calls; operates purely on the rendered table.
 */
( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function () {
		var tbody = document.getElementById( 'sp-logs-tbody' );
		if ( ! tbody ) {
			return;
		}

		var search = document.getElementById( 'sp-logs-search' );
		var level = document.getElementById( 'sp-logs-level' );
		var rows = Array.prototype.slice.call( tbody.querySelectorAll( '.sp-logs-row' ) );
		var noMatch = tbody.querySelector( '.sp-logs-no-match' );

		function applyFilter() {
			var q = ( search && search.value || '' ).toLowerCase().trim();
			var lv = ( level && level.value ) || '';
			var visible = 0;

			rows.forEach( function ( row ) {
				var okLevel = ! lv || row.getAttribute( 'data-level' ) === lv;
				var okText = ! q || row.textContent.toLowerCase().indexOf( q ) !== -1;
				var show = okLevel && okText;
				row.hidden = ! show;
				if ( show ) {
					visible++;
				}
			} );

			if ( noMatch ) {
				noMatch.hidden = visible !== 0 || rows.length === 0;
			}
		}

		if ( search ) {
			search.addEventListener( 'input', applyFilter );
		}
		if ( level ) {
			level.addEventListener( 'change', applyFilter );
		}

		// Copy trace ID on click.
		tbody.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest ? e.target.closest( '.sp-logs-trace' ) : null;
			if ( ! btn ) {
				return;
			}
			var trace = btn.getAttribute( 'data-trace' ) || '';
			if ( ! trace ) {
				return;
			}
			var done = function () {
				btn.classList.add( 'sp-logs-trace--copied' );
				setTimeout( function () {
					btn.classList.remove( 'sp-logs-trace--copied' );
				}, 1200 );
			};
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( trace ).then( done ).catch( function () {} );
			}
		} );
	} );
} )();
