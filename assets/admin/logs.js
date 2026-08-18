/**
 * Client-side filtering for the Activity Log and Action History tables.
 * Both screens render `[data-row]` elements carrying `data-search` plus one
 * classifying attribute, so a single filter routine serves them.
 */
( function () {
	'use strict';

	function wire( options ) {
		var rows = document.getElementById( options.rows );
		if ( ! rows ) { return; }

		var search = document.getElementById( options.search );
		var selects = ( options.selects || [] )
			.map( function ( pair ) {
				var node = document.getElementById( pair.id );
				return node ? { node: node, attr: pair.attr } : null;
			} )
			.filter( Boolean );

		var empty = document.getElementById( options.empty );
		var all = rows.querySelectorAll( '[data-row]' );

		function apply() {
			var term = search ? search.value.trim().toLowerCase() : '';
			var visible = 0;

			Array.prototype.forEach.call( all, function ( row ) {
				var show = ! term || ( row.getAttribute( 'data-search' ) || '' ).indexOf( term ) !== -1;

				selects.forEach( function ( filter ) {
					if ( ! show || ! filter.node.value ) { return; }
					show = row.getAttribute( filter.attr ) === filter.node.value;
				} );

				row.hidden = ! show;
				if ( show ) { visible++; }
			} );

			if ( empty ) { empty.hidden = visible !== 0 || all.length === 0; }
		}

		var timer = null;
		if ( search ) {
			search.addEventListener( 'input', function () {
				window.clearTimeout( timer );
				timer = window.setTimeout( apply, 180 );
			} );
		}
		selects.forEach( function ( filter ) { filter.node.addEventListener( 'change', apply ); } );
	}

	function init() {
		wire( {
			rows: 'sp-logs-rows',
			search: 'sp-logs-search',
			empty: 'sp-logs-nomatch',
			selects: [ { id: 'sp-logs-level', attr: 'data-level' } ],
		} );

		wire( {
			rows: 'sp-history-rows',
			search: 'sp-history-search',
			empty: 'sp-history-nomatch',
			selects: [
				{ id: 'sp-history-status', attr: 'data-status' },
				{ id: 'sp-history-connector', attr: 'data-connector' },
			],
		} );
	}

	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
}() );
