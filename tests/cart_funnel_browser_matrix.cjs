/*
 * Store Copilot "Count carts" in en_US and fa_IR, end to end on the store's real cart and
 * checkout: the store manager turns it on in What it can do, two shoppers in their own
 * browser sessions (one reaches checkout, one leaves after adding to the cart) and the
 * manager's own visit to checkout, then the Orders card at desktop and 390px.
 * Credentials stay in subprocess memory, never stdout.
 */
'use strict';
const { execFileSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { chromium } = require( process.env.AISAAUTOPILOT_PLAYWRIGHT_CORE || 'playwright' );

const php = process.env.AISAAUTOPILOT_PHP || 'C:\\xampp\\php\\php.exe';
const baseUrl = ( process.env.AISAAUTOPILOT_BASE_URL || 'http://localhost/wp' ).replace( /\/$/, '' );
const shots = process.env.AISAAUTOPILOT_SCREENSHOT_DIR || path.resolve( __dirname, '../../../../test-artifacts/cart-funnel' );
const fixture = 'local_cart_funnel_browser_fixture.php';
function helper( name, mode, env = process.env ) {
	return execFileSync( php, [ path.join( __dirname, name ), mode ], { env, encoding: 'utf8', windowsHide: true } ).trim();
}
function counts( env ) {
	return JSON.parse( helper( fixture, 'status', env ).split( '\n' ).pop() );
}
let failures = 0;
function check( ok, name, detail ) {
	console.log( ( ok ? 'PASS ' : 'FAIL ' ) + name );
	if ( ! ok ) {
		failures++;
		if ( undefined !== detail ) { console.log( '    - ' + JSON.stringify( detail ) ); }
	}
}

( async () => {
	try {
		for ( const locale of ( process.env.AISAAUTOPILOT_FUNNEL_LOCALES || 'en_US,fa_IR' ).split( ',' ) ) {
			const env = { ...process.env, AISAAUTOPILOT_BROWSER_LOCALE: locale };
			const account = JSON.parse( helper( 'local_browser_user.php', 'create', env ) );
			const state = JSON.parse( helper( fixture, 'prepare', env ).split( '\n' ).pop() );
			const tag = locale + ': ';
			console.log( 'CART_FUNNEL_MATRIX ' + locale );
			const browser = await chromium.launch( process.env.AISAAUTOPILOT_CHROME ? { executablePath: process.env.AISAAUTOPILOT_CHROME } : {} );
			try {
				const admin = await browser.newPage( { viewport: { width: 1440, height: 960 } } );
				const errors = [];
				admin.on( 'pageerror', ( e ) => errors.push( e.message ) );
				await admin.goto( baseUrl + '/wp-login.php' );
				await admin.fill( '#user_login', account.username );
				await admin.fill( '#user_pass', account.password );
				await Promise.all( [ admin.waitForURL( ( u ) => ! String( u ).includes( 'wp-login.php' ), { waitUntil: 'commit', timeout: 240000 } ), admin.click( '#wp-submit' ) ] );
				const store = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-wc-dashboard';

				// Off: the Orders card says how to turn it on.
				await admin.goto( store + '&tab=orders', { waitUntil: 'load', timeout: 120000 } );
				check( 1 === await admin.locator( '[data-sp-cart-steps-off]' ).count(), tag + 'switched off, Orders says how to see carts left before checkout' );

				// On, through the switch.
				await admin.goto( store + '&tab=capabilities', { waitUntil: 'load', timeout: 120000 } );
				const toggle = admin.locator( '[data-sp-store-setting="cart_funnel"]' );
				check( 'false' === await toggle.getAttribute( 'aria-checked' ), tag + 'Count carts is off by default' );
				const saved = admin.waitForResponse( ( r ) => r.url().includes( 'admin-settings' ) );
				await toggle.click();
				await saved;
				check( 'true' === await toggle.getAttribute( 'aria-checked' ) && true === counts( env ).on, tag + 'the switch turns Count carts on' );

				// Shopper 1: adds with an add-to-cart link, opens checkout twice. Shopper 2 adds
				// through the Store API, as product blocks do, and leaves.
				for ( const [ who, toCheckout ] of [ [ 1, true ], [ 2, false ] ] ) {
					const ctx = await browser.newContext();
					const shop = await ctx.newPage();
					if ( toCheckout ) {
						await shop.goto( baseUrl + '/?add-to-cart=' + state.product, { waitUntil: 'load', timeout: 120000 } );
					} else {
						await shop.goto( baseUrl + '/', { waitUntil: 'load', timeout: 120000 } );
						const added = await shop.evaluate( async ( args ) => {
							const cart = await fetch( args.base + '/wp-json/wc/store/v1/cart', { credentials: 'same-origin' } );
							const nonce = cart.headers.get( 'Nonce' );
							const res = await fetch( args.base + '/wp-json/wc/store/v1/cart/add-item', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Nonce: nonce }, body: JSON.stringify( { id: args.id, quantity: 1 } ) } );
							return res.status;
						}, { base: baseUrl, id: state.product } );
						check( 201 === added || 200 === added, tag + 'shopper 2 adds to the cart through the Store API', added );
					}
					if ( toCheckout ) {
						await shop.goto( state.checkout, { waitUntil: 'load', timeout: 120000 } );
						await shop.goto( state.checkout, { waitUntil: 'load', timeout: 120000 } );
					}
					await ctx.close();
					console.log( '    shopper ' + who + ' done' );
				}
				// The store manager's own test visit does not count.
				await admin.goto( baseUrl + '/?add-to-cart=' + state.product, { waitUntil: 'load', timeout: 120000 } );
				await admin.goto( state.checkout, { waitUntil: 'load', timeout: 120000 } );
				const c = counts( env );
				check( 2 === c.cart && 1 === c.checkout, tag + 'two shoppers count two carts and one checkout; the manager\'s own visit is not counted', c );

				// The card.
				await admin.goto( store + '&tab=orders', { waitUntil: 'load', timeout: 120000 } );
				const steps = admin.locator( '[data-sp-cart-steps]' );
				const shown = await steps.locator( '[data-sp-cart-step-count]' ).allInnerTexts();
				check( 3 === shown.length && /2/.test( shown[ 0 ] ) && /1/.test( shown[ 1 ] ), tag + 'Orders shows carts started 2 and reached checkout 1', shown );
				const label = ( await steps.locator( '[data-sp-cart-step="checkout"] .sp-store-funnel__label' ).innerText() ).trim();
				check( ( 'fa_IR' === locale ) === /[\u0600-\u06FF]/.test( label ), tag + 'the steps are in the admin language', label );
				fs.mkdirSync( path.join( shots, locale ), { recursive: true } );
				await admin.locator( '[data-sp-store-funnel]' ).screenshot( { path: path.join( shots, locale, 'funnel-desktop.png' ) } );
				await admin.setViewportSize( { width: 390, height: 844 } );
				await admin.waitForTimeout( 300 );
				const layout = await admin.evaluate( () => {
					const card = document.querySelector( '[data-sp-store-funnel]' );
					const small = Array.from( card.querySelectorAll( 'a, button' ) ).map( ( e ) => e.getBoundingClientRect() ).filter( ( r ) => r.width > 0 && ( r.width < 24 || r.height < 24 ) ).length;
					return { small, overflow: document.documentElement.scrollWidth > window.innerWidth + 1 };
				} );
				check( 0 === layout.small && ! layout.overflow, tag + 'at 390px nothing scrolls sideways and links are at least 24px', layout );
				await admin.locator( '[data-sp-store-funnel]' ).screenshot( { path: path.join( shots, locale, 'funnel-mobile.png' ) } );
				check( 0 === errors.length, tag + 'no script errors', errors );
			} finally {
				await browser.close();
				console.log( helper( fixture, 'restore', env ) );
				console.log( helper( 'local_browser_user.php', 'delete', env ) );
			}
		}
	} catch ( error ) {
		console.error( error );
		failures++;
	}
	console.log( failures ? 'CART_FUNNEL_MATRIX FAIL' : 'CART_FUNNEL_MATRIX PASS' );
	process.exitCode = failures ? 1 : 0;
} )();
