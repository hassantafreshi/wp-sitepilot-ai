/*
 * Integrations warning about another chat or AI plugin (T31) in en_US and fa_IR. Two
 * stand-in plugins (Social Chat Buttons, AI Engine) are reported as active by a fixture;
 * the Answer Desk chat button is on. Checks both warnings with real names, the Open Plugins
 * link, 390px layout, hiding each warning with the keyboard (Enter, then Space), focus after
 * hiding, that hiding survives a reload, and no page errors or failed plugin requests.
 * Credentials stay in subprocess memory, never stdout.
 */
'use strict';
require( './test-env.cjs' ); // AISAAUTOPILOT_* defaults: the local test copy, else the owner's XAMPP
const { execFileSync } = require( 'node:child_process' );
const path = require( 'node:path' );
const { chromium } = require( process.env.AISAAUTOPILOT_PLAYWRIGHT_CORE || 'playwright' );

const php = process.env.AISAAUTOPILOT_PHP || 'C:\\xampp\\php\\php.exe';
const baseUrl = ( process.env.AISAAUTOPILOT_BASE_URL || 'http://localhost/wp' ).replace( /\/$/, '' );
const shots = process.env.AISAAUTOPILOT_SCREENSHOT_DIR || path.resolve( __dirname, '../../../../test-artifacts/conflict-banner' );
const fixture = 'local_conflict_browser_fixture.php';
function helper( name, mode, env = process.env ) {
	return execFileSync( php, [ path.join( __dirname, name ), mode ], { env, encoding: 'utf8', windowsHide: true } ).trim();
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
		for ( const locale of ( process.env.AISAAUTOPILOT_CONFLICT_LOCALES || 'en_US,fa_IR' ).split( ',' ) ) {
			const env = { ...process.env, AISAAUTOPILOT_BROWSER_LOCALE: locale };
			const account = JSON.parse( helper( 'local_browser_user.php', 'create', env ) );
			helper( fixture, 'prepare', env );
			const tag = locale + ': ';
			console.log( 'CONFLICT_MATRIX ' + locale );
			const browser = await chromium.launch( process.env.AISAAUTOPILOT_CHROME ? { executablePath: process.env.AISAAUTOPILOT_CHROME } : {} );
			try {
				const page = await browser.newPage( { viewport: { width: 1440, height: 960 } } );
				const errors = [];
				const failed = [];
				page.on( 'pageerror', ( e ) => errors.push( e.message ) );
				page.on( 'console', ( m ) => { if ( 'error' === m.type() ) { errors.push( m.text() ); } } );
				page.on( 'response', ( r ) => { if ( r.url().includes( 'aisaautopilot' ) && r.url().includes( 'v1' ) && r.status() >= 400 ) { failed.push( r.status() + ' ' + r.url() ); } } );
				await page.goto( baseUrl + '/wp-login.php' );
				await page.fill( '#user_login', account.username );
				await page.fill( '#user_pass', account.password );
				await Promise.all( [ page.waitForURL( ( u ) => ! String( u ).includes( 'wp-login.php' ), { waitUntil: 'commit', timeout: 240000 } ), page.click( '#wp-submit' ) ] );
				const url = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-integrations';
				await page.goto( url, { waitUntil: 'load', timeout: 120000 } );

				const chat = page.locator( '[data-sp-conflict="chat_widget"]' );
				const ai = page.locator( '[data-sp-conflict="ai_assistant"]' );
				const chatText = ( await chat.textContent() || '' ).replace( /\s+/g, ' ' );
				const aiText = ( await ai.textContent() || '' ).replace( /\s+/g, ' ' );
				check( await chat.isVisible() && chatText.includes( 'Social Chat Buttons' ) && await ai.isVisible() && aiText.includes( 'AI Engine' ), tag + 'both warnings are shown with the real plugin names', { chatText, aiText } );
				if ( 'fa_IR' === locale ) {
					check( 'rtl' === await page.locator( 'html' ).getAttribute( 'dir' ) && ! /Another chat plugin|may overlap/.test( chatText ) && ! /also an AI assistant/.test( aiText ), tag + 'the page is right to left and the warnings are translated', { chatText, aiText } );
				}
				const cta = chat.locator( '.sp-banner__cta' );
				check( /\/wp-admin\/plugins\.php$/.test( await cta.getAttribute( 'href' ) || '' ), tag + 'Open Plugins goes to the Plugins screen' );
				const named = await page.evaluate( () => Array.prototype.every.call( document.querySelectorAll( '[data-sp-conflict]' ), ( s ) => {
					const h = document.getElementById( s.getAttribute( 'aria-labelledby' ) );
					const b = s.querySelector( '[data-sp-conflict-dismiss]' );
					return h && 'H2' === h.tagName && h.textContent.trim() && b && b.getAttribute( 'aria-label' );
				} ) );
				check( named, tag + 'each warning is a region named by its heading, and the hide button has a label' );

				// 390px: no sideways scroll, the hide button is a 32px target.
				await page.setViewportSize( { width: 390, height: 844 } );
				const layout = await page.evaluate( () => {
					const b = document.querySelector( '[data-sp-conflict] [data-sp-conflict-dismiss]' ).getBoundingClientRect();
					return { overflow: document.documentElement.scrollWidth > window.innerWidth + 1, w: Math.round( b.width ), h: Math.round( b.height ), inside: b.right <= window.innerWidth && b.left >= 0 };
				} );
				check( ! layout.overflow && layout.w >= 32 && layout.h >= 32 && layout.inside, tag + 'at 390px nothing scrolls sideways and the hide button is at least 32px and on screen', layout );
				await page.screenshot( { path: path.join( shots, locale + '-390.png' ), fullPage: false } ).catch( () => {} );
				await page.setViewportSize( { width: 1440, height: 960 } );
				await page.screenshot( { path: path.join( shots, locale + '-1440.png' ), fullPage: false } ).catch( () => {} );

				// Keyboard: Enter hides the chat warning, focus goes to the next warning's button.
				const saved = page.waitForResponse( ( r ) => r.url().includes( 'conflicts/dismiss' ) );
				await chat.locator( '[data-sp-conflict-dismiss]' ).focus();
				await page.keyboard.press( 'Enter' );
				await saved;
				await page.waitForTimeout( 150 );
				const focus1 = await page.evaluate( () => document.activeElement && document.activeElement.closest( '[data-sp-conflict="ai_assistant"]' ) && document.activeElement.hasAttribute( 'data-sp-conflict-dismiss' ) );
				check( ! await chat.isVisible() && await ai.isVisible() && !! focus1, tag + 'Enter hides the chat warning and moves focus to the next warning' );

				await page.reload( { waitUntil: 'load', timeout: 120000 } );
				check( 0 === await chat.count() && await ai.isVisible(), tag + 'after a reload the hidden warning stays hidden and the other one stays' );

				// Space hides the last warning; focus moves into the page.
				const saved2 = page.waitForResponse( ( r ) => r.url().includes( 'conflicts/dismiss' ) );
				await ai.locator( '[data-sp-conflict-dismiss]' ).focus();
				await page.keyboard.press( 'Space' );
				await saved2;
				await page.waitForTimeout( 150 );
				const focus2 = await page.evaluate( () => !! ( document.activeElement && document.activeElement.closest( '.sp-integrations__groups' ) ) );
				check( ! await ai.isVisible() && focus2, tag + 'Space hides the last warning and focus moves to the integrations' );
				await page.reload( { waitUntil: 'load', timeout: 120000 } );
				check( 0 === await page.locator( '[data-sp-conflict]' ).count(), tag + 'after a reload no warning is left' );

				check( 0 === errors.length && 0 === failed.length, tag + 'no page errors and no failed plugin requests', { errors, failed } );
			} finally {
				await browser.close();
				console.log( helper( fixture, 'restore', env ) );
				console.log( helper( 'local_browser_user.php', 'delete', env ) );
			}
		}
	} catch ( error ) {
		failures++;
		console.error( error );
	}
	console.log( failures ? 'CONFLICT_MATRIX FAIL' : 'CONFLICT_MATRIX PASS' );
	process.exit( failures ? 1 : 0 );
} )();
