/**
 * AI Site Assistant AutoPilot — WooCommerce dashboard.
 * Fills the wireframe's stat tiles, bar chart and lists from the analytics REST routes.
 */
( function () {
	'use strict';

	var cfg = window.AISAAutoPilotDashboard || {};
	var T = ( function () {
		try {
			var node = document.getElementById( 'sp-wc-i18n' );
			return node ? ( JSON.parse( node.textContent || '{}' ) || {} ) : {};
		} catch ( e ) { return {}; }
	}() );

	function t( key, fallback ) { return T[ key ] || fallback || key; }

	function fill( template, value ) { return String( template ).replace( '%s', value ); }

	function $( id ) { return document.getElementById( id ); }

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( key ) {
			if ( key === 'class' ) { node.className = attrs[ key ]; }
			else if ( key === 'text' ) { node.textContent = attrs[ key ]; }
			else if ( key === 'style' ) { node.setAttribute( 'style', attrs[ key ] ); }
			else { node.setAttribute( key, attrs[ key ] ); }
		} );
		( children || [] ).forEach( function ( child ) {
			if ( child ) { node.appendChild( typeof child === 'string' ? document.createTextNode( child ) : child ); }
		} );
		return node;
	}

	function setText( id, value ) {
		var node = $( id );
		if ( node ) { node.textContent = value; }
	}

	function replace( id, children ) {
		var node = $( id );
		if ( ! node ) { return; }
		node.textContent = '';
		if ( ! children.length ) {
			node.appendChild( el( 'p', { class: 'sp-empty', text: t( 'empty' ) } ) );
			return;
		}
		children.forEach( function ( child ) { node.appendChild( child ); } );
	}

	function get( endpoint ) {
		return fetch( cfg.restUrl + endpoint, { credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce } } )
			.then( function ( r ) { return r.json(); } );
	}

	/* ── Renderers ── */

	function renderStats( dashboard ) {
		if ( ! dashboard.sales ) { return; }
		setText( 'sp-stat-revenue', dashboard.sales.total_revenue + ' ' + ( dashboard.currency || '' ) );
		setText( 'sp-stat-orders', dashboard.sales.total_orders );
		setText( 'sp-stat-avg', dashboard.sales.avg_order + ' ' + ( dashboard.currency || '' ) );
	}

	function renderSalesChart( daily ) {
		var chart = $( 'sp-sales-chart' );
		var labels = $( 'sp-sales-labels' );
		if ( ! chart || ! labels ) { return; }

		chart.textContent = '';
		labels.textContent = '';

		var days = Object.keys( daily || {} ).sort();
		if ( ! days.length ) {
			chart.appendChild( el( 'p', { class: 'sp-empty', text: t( 'empty' ) } ) );
			return;
		}

		var max = Math.max.apply( null, days.map( function ( d ) { return Number( daily[ d ].revenue ) || 0; } ) );
		var peak = days.reduce( function ( best, d ) {
			return ( Number( daily[ d ].revenue ) || 0 ) > ( Number( daily[ best ].revenue ) || 0 ) ? d : best;
		}, days[ 0 ] );

		days.forEach( function ( day ) {
			var revenue = Number( daily[ day ].revenue ) || 0;
			var height = max > 0 ? Math.max( 5, ( revenue / max ) * 100 ) : 5;
			chart.appendChild( el( 'span', {
				class: 'sp-chart__bar' + ( day === peak ? ' sp-chart__bar--peak' : '' ),
				style: 'height:' + height + '%',
				title: String( revenue ),
			} ) );

			var date = new Date( day );
			var label = isNaN( date.getTime() )
				? day
				: ( ( T.days && T.days[ ( date.getDay() + 6 ) % 7 ] ) || date.toLocaleDateString( undefined, { weekday: 'short' } ) );
			labels.appendChild( el( 'span', { text: label } ) );
		} );
	}

	function renderTopProducts( products ) {
		replace( 'sp-top-products', ( products || [] ).slice( 0, 5 ).map( function ( p, i ) {
			return el( 'div', { class: 'sp-kv__row' + ( i < 2 ? ' sp-kv__row--ruled' : '' ) }, [
				el( 'span', { text: p.name || '' } ),
				el( 'span', { style: 'font-weight:600', text: p.revenue !== undefined ? String( p.revenue ) : fill( t( 'sold' ), p.sold || 0 ) } ),
			] );
		} ) );
	}

	function renderLowStock( products ) {
		var count = $( 'sp-low-stock-count' );
		if ( count ) { count.textContent = ( products || [] ).length; }

		replace( 'sp-low-stock', ( products || [] ).slice( 0, 5 ).map( function ( p ) {
			var critical = Number( p.stock ) <= 2;
			return el( 'div', {
				class: 'sp-notice ' + ( critical ? 'sp-notice--err' : 'sp-notice--warn' ),
				style: 'align-items:center',
			}, [
				el( 'span', { class: 'sp-notice__body', style: 'font-size:13px;font-weight:600', text: p.name || '' } ),
				el( 'span', {
					style: 'font-size:12px;font-weight:700;color:' + ( critical ? '#b91c1c' : '#92400e' ),
					text: fill( t( 'left' ), p.stock ),
				} ),
			] );
		} ) );
	}

	function statusTone( status ) {
		if ( status === 'completed' ) { return 'ok'; }
		if ( status === 'processing' ) { return 'blue'; }
		if ( status === 'cancelled' || status === 'failed' || status === 'refunded' ) { return 'err'; }
		return 'warn';
	}

	function renderRecentOrders( orders ) {
		replace( 'sp-recent-orders', ( orders || [] ).map( function ( o, i ) {
			return el( 'div', {
				class: 'sp-kv__row' + ( i < ( orders.length - 1 ) ? ' sp-kv__row--ruled' : '' ),
				style: 'font-size:13px',
			}, [
				el( 'span', { class: 'sp-mono sp-td--muted', text: '#' + ( o.number || o.id || '' ) } ),
				el( 'span', { class: 'sp-pill sp-pill--' + statusTone( o.status ) + ' sp-pill--xs', text: o.status || '' } ),
				el( 'span', { style: 'font-weight:600', text: ( o.total || '' ) + ' ' + ( o.currency || '' ) } ),
			] );
		} ) );
	}

	function showInactive() {
		[ 'sp-stat-revenue', 'sp-stat-orders', 'sp-stat-avg', 'sp-stat-products' ].forEach( function ( id ) {
			setText( id, '—' );
		} );
		[ 'sp-top-products', 'sp-low-stock', 'sp-recent-orders' ].forEach( function ( id ) { replace( id, [] ); } );
		var chart = $( 'sp-sales-chart' );
		if ( chart ) { chart.textContent = ''; }
	}

	function init() {
		if ( ! cfg.wcActive ) { showInactive(); return; }

		get( 'woocommerce/analytics/dashboard' ).then( function ( data ) {
			if ( ! data.ok || ! data.dashboard ) { return; }
			renderStats( data.dashboard );
			renderSalesChart( data.dashboard.sales ? data.dashboard.sales.daily_sales : {} );
			renderTopProducts( data.dashboard.top_products || [] );
			renderLowStock( data.dashboard.low_stock || [] );
		} ).catch( showInactive );

		get( 'woocommerce/orders/recent?limit=5' ).then( function ( data ) {
			if ( data.ok ) { renderRecentOrders( data.orders || [] ); }
		} ).catch( function () {} );

		get( 'woocommerce/check' ).then( function ( data ) {
			if ( data.ok && data.health ) { setText( 'sp-stat-products', data.health.product_count || '—' ); }
		} ).catch( function () {} );
	}

	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
}() );
