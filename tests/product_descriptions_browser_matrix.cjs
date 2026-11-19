/*
 * Store Copilot > Descriptions in en_US and fa_IR: the switch under What it can do, the
 * product list, a draft written through the real routes (the AI host is answered by the
 * fixture's temporary must-use plugin), the safe preview, an edit, Apply, Undo, and the
 * layout at 390px. Credentials stay in subprocess memory, never stdout.
 */
'use strict';
require( './test-env.cjs' ); // AISAAUTOPILOT_* defaults: the local test copy, else the owner's XAMPP
const { execFileSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { chromium } = require( process.env.AISAAUTOPILOT_PLAYWRIGHT_CORE || 'playwright' );

const php = process.env.AISAAUTOPILOT_PHP || 'C:\\xampp\\php\\php.exe';
const baseUrl = ( process.env.AISAAUTOPILOT_BASE_URL || 'http://localhost/wp' ).replace( /\/$/, '' );
const shots = process.env.AISAAUTOPILOT_SCREENSHOT_DIR || path.resolve( __dirname, '../../../../test-artifacts/product-descriptions' );
const fixture = 'local_product_descriptions_browser_fixture.php';
function helper( name, mode, env = process.env ) {
	return execFileSync( php, [ path.join( __dirname, name ), mode ], { env, encoding: 'utf8', windowsHide: true } ).trim();
}
function description( env ) {
	return JSON.parse( helper( fixture, 'status', env ).split( '\n' ).pop() ).description;
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
		for ( const locale of ( process.env.AISAAUTOPILOT_DESC_LOCALES || 'en_US,fa_IR' ).split( ',' ) ) {
			const env = { ...process.env, AISAAUTOPILOT_BROWSER_LOCALE: locale };
			const account = JSON.parse( helper( 'local_browser_user.php', 'create', env ) );
			const state = JSON.parse( helper( fixture, 'prepare', env ).split( '\n' ).pop() );
			const tag = locale + ': ';
			console.log( 'PRODUCT_DESCRIPTIONS_MATRIX ' + locale );
			const browser = await chromium.launch( process.env.AISAAUTOPILOT_CHROME ? { executablePath: process.env.AISAAUTOPILOT_CHROME } : {} );
			try {
				const page = await browser.newPage( { viewport: { width: 1440, height: 960 } } );
				const errors = [];
				page.on( 'pageerror', ( e ) => errors.push( e.message ) );
				page.on( 'console', ( m ) => { if ( 'error' === m.type() && m.location().url.includes( 'ai-site-assistant-autopilot' ) ) { errors.push( m.text() ); } } );
				await page.goto( baseUrl + '/wp-login.php' );
				await page.fill( '#user_login', account.username );
				await page.fill( '#user_pass', account.password );
				await Promise.all( [ page.waitForURL( ( u ) => ! String( u ).includes( 'wp-login.php' ), { waitUntil: 'commit', timeout: 90000 } ), page.click( '#wp-submit' ) ] );
				const store = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-wc-dashboard';

				// The switch: on (fixture), saved through the Store settings route when clicked.
				await page.goto( store + '&tab=capabilities', { waitUntil: 'load' } );
				const toggle = page.locator( '[data-sp-store-setting="enable_descriptions"]' );
				check( 1 === await toggle.count() && 'true' === await toggle.getAttribute( 'aria-checked' ), tag + 'What it can do shows Product descriptions switched on' );
				const savedOff = page.waitForResponse( ( r ) => r.url().includes( 'admin-settings' ) );
				await toggle.click();
				await savedOff;
				await page.goto( store + '&tab=descriptions', { waitUntil: 'load' } );
				check( 1 === await page.locator( '[data-sp-desc-off]' ).count() && 0 === await page.locator( '[data-sp-desc-write]' ).count(), tag + 'switched off, Descriptions says how to turn it on and offers nothing to run' );
				await page.goto( store + '&tab=capabilities', { waitUntil: 'load' } );
				const savedOn = page.waitForResponse( ( r ) => r.url().includes( 'admin-settings' ) );
				await page.locator( '[data-sp-store-setting="enable_descriptions"]' ).click();
				await savedOn;

				// The list and a draft.
				await page.goto( store + '&tab=descriptions', { waitUntil: 'load' } );
				const box = page.locator( '[data-sp-desc-product="' + state.product + '"]' );
				await box.waitFor( { state: 'attached', timeout: 20000 } );
				const write = page.locator( '[data-sp-desc-write]' );
				check( await write.isDisabled(), tag + 'Write drafts waits until a product is picked' );
				await box.check();
				const drafted = page.waitForResponse( ( r ) => r.url().includes( '/descriptions/draft' ), { timeout: 60000 } );
				await write.click();
				await drafted;
				const card = page.locator( '[data-sp-desc-review="' + state.product + '"]' );
				await card.waitFor( { timeout: 20000 } );
				const preview = card.locator( '.sp-store-desc__preview' );
				check(
					( await preview.innerText() ).includes( 'Built to last' ) && 0 === await preview.locator( 'script' ).count()
					&& ( await card.locator( '.sp-store-desc__now' ).innerText() ).includes( 'A wallet.' ) && 'A wallet.' === description( env ),
					tag + 'the draft shows beside the current text, without scripts, and nothing is changed yet'
				);

				// Edit, then Apply: the edited text is what the product gets.
				await card.locator( 'summary' ).click();
				await card.locator( 'textarea' ).fill( '<p>Our own wording for the wallet.</p>' );
				check( ( await preview.innerText() ).includes( 'Our own wording' ), tag + 'editing the draft updates its preview' );
				const applied = page.waitForResponse( ( r ) => r.url().includes( '/descriptions/apply' ) );
				await card.locator( '[data-sp-desc-apply]' ).click();
				const applyRes = await applied;
				await card.locator( '[data-sp-desc-undo]' ).waitFor( { timeout: 10000 } );
				check( 200 === applyRes.status() && '<p>Our own wording for the wallet.</p>' === description( env ), tag + 'Apply writes the edited description', description( env ) );
				const note = ( await card.locator( '.sp-status-text' ).innerText() ).trim();
				check( ( 'fa_IR' === locale ) === /[\u0600-\u06FF]/.test( note ), tag + 'the confirmation is in the admin language', note );

				const undone = page.waitForResponse( ( r ) => r.url().includes( '/descriptions/undo' ) );
				await card.locator( '[data-sp-desc-undo]' ).click();
				await undone;
				await page.waitForTimeout( 300 );
				check( 'A wallet.' === description( env ) && 0 === await card.locator( '.sp-pill' ).count() && 0 === await card.locator( '[data-sp-desc-undo]' ).count(), tag + 'Undo puts the previous description back and the card no longer says Applied' );

				fs.mkdirSync( path.join( shots, locale ), { recursive: true } );
				await page.screenshot( { path: path.join( shots, locale, 'descriptions-desktop.png' ), fullPage: true } );
				await page.setViewportSize( { width: 390, height: 844 } );
				await page.waitForTimeout( 400 );
				const layout = await page.evaluate( () => {
					const small = Array.from( document.querySelectorAll( '[data-sp-store-descriptions-panel] button, [data-sp-store-descriptions-panel] summary, [data-sp-store-descriptions-panel] select' ) )
						.map( ( el ) => el.getBoundingClientRect() ).filter( ( r ) => r.width > 0 && ( r.width < 24 || r.height < 24 ) ).length;
					return { small, overflow: document.documentElement.scrollWidth > window.innerWidth + 1 };
				} );
				check( 0 === layout.small && ! layout.overflow, tag + 'at 390px every control is at least 24x24 and nothing scrolls sideways', layout );
				await page.screenshot( { path: path.join( shots, locale, 'descriptions-mobile.png' ), fullPage: true } );
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
	console.log( failures ? 'PRODUCT_DESCRIPTIONS_MATRIX FAIL' : 'PRODUCT_DESCRIPTIONS_MATRIX PASS' );
	process.exitCode = failures ? 1 : 0;
} )();
