/*
 * Abandoned-cart reminders in en_US and fa_IR, end to end on the store's real checkout:
 * the store manager turns them on; a shopper sees the notice under the email field, and
 * the cart is kept only after an email is typed; a second shopper declines and nothing is
 * kept; after an hour the manager sees the cart and sends the one reminder (recorded by
 * the fixture, not sent); the link in that email refills the cart in a fresh browser and
 * opens checkout with the email filled in. Credentials stay in subprocess memory.
 */
'use strict';
require( './test-env.cjs' ); // AISAAUTOPILOT_* defaults: the local test copy, else the owner's XAMPP
const { execFileSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { chromium } = require( process.env.AISAAUTOPILOT_PLAYWRIGHT_CORE || 'playwright' );

const php = process.env.AISAAUTOPILOT_PHP || 'C:\\xampp\\php\\php.exe';
const baseUrl = ( process.env.AISAAUTOPILOT_BASE_URL || 'http://localhost/wp' ).replace( /\/$/, '' );
const shots = process.env.AISAAUTOPILOT_SCREENSHOT_DIR || path.resolve( __dirname, '../../../../test-artifacts/cart-recovery' );
const fixture = 'local_cart_recovery_browser_fixture.php';
function helper( name, mode, env = process.env ) {
	return execFileSync( php, [ path.join( __dirname, name ), mode ], { env, encoding: 'utf8', windowsHide: true } ).trim();
}
function status( env ) {
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
async function waitFor( fn, ms ) {
	const end = Date.now() + ms;
	while ( Date.now() < end ) {
		if ( fn() ) { return true; }
		await new Promise( ( r ) => setTimeout( r, 300 ) );
	}
	return fn();
}

( async () => {
	try {
		for ( const locale of ( process.env.AISAAUTOPILOT_CART_LOCALES || 'en_US,fa_IR' ).split( ',' ) ) {
			const env = { ...process.env, AISAAUTOPILOT_BROWSER_LOCALE: locale };
			const account = JSON.parse( helper( 'local_browser_user.php', 'create', env ) );
			const state = JSON.parse( helper( fixture, 'prepare', env ).split( '\n' ).pop() );
			const tag = locale + ': ';
			console.log( 'CART_RECOVERY_MATRIX ' + locale );
			fs.mkdirSync( path.join( shots, locale ), { recursive: true } );
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

				// On, through the switch.
				await admin.goto( store + '&tab=capabilities', { waitUntil: 'load', timeout: 120000 } );
				const toggle = admin.locator( '[data-sp-store-setting="cart_recovery"]' );
				check( 'false' === await toggle.getAttribute( 'aria-checked' ), tag + 'Abandoned cart reminders is off by default' );
				const saved = admin.waitForResponse( ( r ) => r.url().includes( 'admin-settings' ) );
				await toggle.click();
				await saved;
				check( true === status( env ).on, tag + 'the switch turns reminders on' );

				// Shopper 1: the notice is shown; nothing is kept until an email is typed.
				const ctx1 = await browser.newContext( { viewport: { width: 390, height: 844 } } );
				const shop = await ctx1.newPage();
				const shopErrors = [];
				shop.on( 'pageerror', ( e ) => shopErrors.push( e.message ) );
				await shop.goto( baseUrl + '/?add-to-cart=' + state.product, { waitUntil: 'load', timeout: 120000 } );
				await shop.goto( state.checkout, { waitUntil: 'load', timeout: 120000 } );
				const notice = shop.locator( '[data-aisaautopilot-cart-notice]' );
				await notice.waitFor( { timeout: 30000 } );
				const email = shop.locator( '#email, #billing_email' ).first();
				const noticeBelowEmail = await shop.evaluate( () => {
					const n = document.querySelector( '[data-aisaautopilot-cart-notice]' ).getBoundingClientRect();
					const e = document.querySelector( '#email, #billing_email' ).getBoundingClientRect();
					return n.top >= e.bottom - 1 && n.top - e.bottom < 120;
				} );
				check( noticeBelowEmail && /30/.test( await notice.innerText() ) && 0 === status( env ).carts.length, tag + 'checkout shows the notice right under the email field, and nothing is kept before an email is typed' );
				await email.fill( 'qa.shopper@example.test' );
				await email.blur();
				const kept = await waitFor( () => 1 === status( env ).carts.length, 10000 );
				const row = status( env ).carts[ 0 ] || {};
				check( kept && 'qa.shopper@example.test' === row.email && /QA Recovery Teapot/.test( row.contents || '' ), tag + 'typing an email keeps the cart with that email', row );
				const mobile = await shop.evaluate( () => ( {
					overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
					button: ( () => { const b = document.querySelector( '.aisaautopilot-cart-notice__decline' ); const r = b.getBoundingClientRect(); return r.height >= 24 && r.width >= 24; } )(),
				} ) );
				check( ! mobile.overflow && mobile.button, tag + 'on a phone the notice fits and its button is at least 24px', mobile );
				await shop.screenshot( { path: path.join( shots, locale, 'checkout-notice-mobile.png' ), fullPage: false } );
				await ctx1.close();

				// Shopper 2 declines: nothing is kept, even after typing an email.
				const ctx2 = await browser.newContext();
				const shop2 = await ctx2.newPage();
				await shop2.goto( baseUrl + '/?add-to-cart=' + state.product, { waitUntil: 'load', timeout: 120000 } );
				await shop2.goto( state.checkout, { waitUntil: 'load', timeout: 120000 } );
				await shop2.locator( '.aisaautopilot-cart-notice__decline' ).click();
				await shop2.locator( '[data-aisaautopilot-cart-notice="declined"]' ).waitFor( { timeout: 10000 } );
				const email2 = shop2.locator( '#email, #billing_email' ).first();
				await email2.fill( 'qa.declined@example.test' );
				await email2.blur();
				await shop2.waitForTimeout( 2500 );
				check( ! status( env ).carts.some( ( c ) => 'qa.declined@example.test' === c.email ), tag + 'a shopper who declines is not kept, even after typing an email' );
				await ctx2.close();

				// After an hour the manager sees the cart and sends the one reminder.
				helper( fixture, 'age', env );
				await admin.goto( store + '&tab=orders', { waitUntil: 'load', timeout: 120000 } );
				const cart = admin.locator( '[data-sp-cart]' ).first();
				check( 1 === await admin.locator( '[data-sp-cart]' ).count() && /qa\.shopper@example\.test/.test( await cart.innerText() ), tag + 'Orders lists the abandoned cart with its email and products' );
				const heading = ( await admin.locator( '[data-sp-store-carts] h2' ).innerText() ).trim();
				check( ( 'fa_IR' === locale ) === /[\u0600-\u06FF]/.test( heading ), tag + 'the card is in the admin language', heading );
				check( 0 === status( env ).mails.length, tag + 'no email is sent before the manager asks' );
				admin.once( 'dialog', ( d ) => d.accept() );
				const reminded = admin.waitForResponse( ( r ) => r.url().includes( '/remind' ) );
				await cart.locator( '[data-sp-cart-remind]' ).click();
				await reminded;
				await cart.locator( '[data-sp-cart-reminded]' ).waitFor( { timeout: 10000 } );
				const mails = status( env ).mails;
				const link = ( String( ( mails[ 0 ] || {} ).message || '' ).match( /https?:\/\/\S*aisaautopilot-cart=[a-f0-9]{32}/ ) || [] )[ 0 ] || '';
				check( 1 === mails.length && 'qa.shopper@example.test' === mails[ 0 ].to && '' !== link, tag + 'Send reminder emails the shopper once, with a link back to the cart', mails.map( ( m ) => m.subject ) );
				await admin.locator( '[data-sp-store-carts]' ).screenshot( { path: path.join( shots, locale, 'abandoned-carts-desktop.png' ) } );
				await admin.setViewportSize( { width: 390, height: 844 } );
				await admin.waitForTimeout( 300 );
				const adminMobile = await admin.evaluate( () => {
					const card = document.querySelector( '[data-sp-store-carts]' );
					const small = Array.from( card.querySelectorAll( 'button, a' ) ).map( ( e ) => e.getBoundingClientRect() ).filter( ( r ) => r.width > 0 && ( r.width < 24 || r.height < 24 ) ).length;
					return { small, overflow: document.documentElement.scrollWidth > window.innerWidth + 1 };
				} );
				check( 0 === adminMobile.small && ! adminMobile.overflow, tag + 'at 390px the cart list fits and its buttons are at least 24px', adminMobile );
				await admin.locator( '[data-sp-store-carts]' ).screenshot( { path: path.join( shots, locale, 'abandoned-carts-mobile.png' ) } );

				// The link refills the cart in a fresh browser and opens checkout with the email.
				const ctx3 = await browser.newContext();
				const back = await ctx3.newPage();
				await back.goto( link, { waitUntil: 'load', timeout: 120000 } );
				await back.locator( 'text=QA Recovery Teapot' ).first().waitFor( { timeout: 30000 } );
				const backEmail = await back.locator( '#email, #billing_email' ).first().inputValue().catch( () => '' );
				check( back.url().startsWith( state.checkout.replace( /\/$/, '' ) ) && 'qa.shopper@example.test' === backEmail, tag + 'the reminder link opens checkout with the cart and the email filled in', { url: back.url(), backEmail } );
				const forged = await back.goto( baseUrl + '/?aisaautopilot-cart=00000000000000000000000000000000', { waitUntil: 'load', timeout: 120000 } );
				check( ! back.url().includes( 'aisaautopilot-cart=' ) && forged.ok(), tag + 'an unknown link token restores nothing and drops the token from the address' );
				await ctx3.close();
				check( 0 === errors.length && 0 === shopErrors.length, tag + 'no script errors', errors.concat( shopErrors ) );
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
	console.log( failures ? 'CART_RECOVERY_MATRIX FAIL' : 'CART_RECOVERY_MATRIX PASS' );
	process.exitCode = failures ? 1 : 0;
} )();
