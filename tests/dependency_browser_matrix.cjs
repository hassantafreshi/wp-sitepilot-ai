/*
 * "Needs another plugin" screens in en_US and fa_IR, with WooCommerce and Easy Form Builder
 * installed but inactive (a fixture deactivates them and puts them back). Checks Store
 * Copilot shows only the activate step as a region named by its heading, the WordPress.org
 * link opens in a new tab, the Integrations cards say Activate (not Install), the Easy Form
 * Builder page keeps its allowed list, 390px layout and RTL, reaching the button with Tab,
 * and that pressing Enter on it activates WooCommerce and comes back to Store Copilot with
 * its tabs and a confirmation. No page errors. Credentials stay in subprocess memory.
 */
'use strict';
const { execFileSync } = require( 'node:child_process' );
const path = require( 'node:path' );
const { chromium } = require( process.env.AISAAUTOPILOT_PLAYWRIGHT_CORE || 'playwright' );

const php = process.env.AISAAUTOPILOT_PHP || 'C:\\xampp\\php\\php.exe';
const baseUrl = ( process.env.AISAAUTOPILOT_BASE_URL || 'http://localhost/wp' ).replace( /\/$/, '' );
const shots = process.env.AISAAUTOPILOT_SCREENSHOT_DIR || path.resolve( __dirname, '../../../../test-artifacts/plugin-dependency' );
const fixture = 'local_dependency_browser_fixture.php';
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
		for ( const locale of ( process.env.AISAAUTOPILOT_DEPENDENCY_LOCALES || 'en_US,fa_IR' ).split( ',' ) ) {
			const env = { ...process.env, AISAAUTOPILOT_BROWSER_LOCALE: locale };
			const account = JSON.parse( helper( 'local_browser_user.php', 'create', env ) );
			console.log( helper( fixture, 'prepare', env ) );
			const tag = locale + ': ';
			const fa = 'fa_IR' === locale;
			console.log( 'DEPENDENCY_MATRIX ' + locale );
			const browser = await chromium.launch( process.env.AISAAUTOPILOT_CHROME ? { executablePath: process.env.AISAAUTOPILOT_CHROME } : {} );
			try {
				const page = await browser.newPage( { viewport: { width: 1440, height: 960 } } );
				const errors = [];
				page.on( 'pageerror', ( e ) => errors.push( e.message ) );
				page.on( 'console', ( m ) => { if ( 'error' === m.type() ) { errors.push( m.text() ); } } );
				await page.goto( baseUrl + '/wp-login.php' );
				await page.fill( '#user_login', account.username );
				await page.fill( '#user_pass', account.password );
				await Promise.all( [ page.waitForURL( ( u ) => ! String( u ).includes( 'wp-login.php' ), { waitUntil: 'commit', timeout: 240000 } ), page.click( '#wp-submit' ) ] );
				const store = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-wc-dashboard';
				await page.goto( store + '&tab=products', { waitUntil: 'load', timeout: 120000 } );

				const dep = page.locator( '[data-sp-dependency="woocommerce"]' );
				const primary = dep.locator( '[data-sp-dependency-action="activate"]' );
				const title = ( await dep.locator( 'h2' ).textContent() || '' ).trim();
				const named = await dep.evaluate( ( s ) => {
					const h = document.getElementById( s.getAttribute( 'aria-labelledby' ) );
					return !! h && 'H2' === h.tagName && h.textContent.trim().length > 0;
				} );
				check(
					await dep.isVisible() && 'inactive' === await dep.getAttribute( 'data-sp-dependency-state' ) && named && 1 === await primary.count()
					&& 0 === await page.locator( '[data-sp-subtab]' ).count() && 0 === await page.locator( '[data-sp-store-products], #sp-woo-plan' ).count()
					&& 3 === await dep.locator( 'li' ).count(),
					tag + 'Store Copilot shows only the activate step, as a region named by its heading, with what it offers and no tabs', { title }
				);
				const link = dep.locator( '.sp-dep__link' );
				check(
					'https://wordpress.org/plugins/woocommerce/' === await link.getAttribute( 'href' ) && '_blank' === await link.getAttribute( 'target' )
					&& /noopener/.test( await link.getAttribute( 'rel' ) || '' ) && ( await link.locator( '.screen-reader-text' ).textContent() || '' ).trim().length > 0,
					tag + 'the WordPress.org link opens in a new tab safely and says so to screen readers'
				);
				if ( fa ) {
					check( 'rtl' === await page.locator( 'html' ).getAttribute( 'dir' ) && /[\u0600-\u06FF]/.test( title ) && ! /Activate|Store Copilot/.test( title ), tag + 'the page is right to left and the step is translated', { title } );
				} else {
					check( 'Activate WooCommerce to use Store Copilot' === title && 'Activate WooCommerce' === ( await primary.textContent() || '' ).trim(), tag + 'the title and button say Activate WooCommerce', { title } );
				}

				// Keyboard: Tab reaches the button, which shows a focus ring.
				await page.locator( '.sp-pagebar__btn' ).focus();
				let reached = false;
				for ( let i = 0; i < 12 && ! reached; i++ ) {
					await page.keyboard.press( 'Tab' );
					reached = await primary.evaluate( ( b ) => b === document.activeElement );
				}
				const ring = reached ? await primary.evaluate( ( b ) => getComputedStyle( b ).outlineStyle ) : 'none';
				check( reached && 'none' !== ring, tag + 'Tab reaches the activate button and it shows a focus ring', { reached, ring } );

				// 390px: no sideways scroll, a full-width 44px button on screen.
				await page.setViewportSize( { width: 390, height: 844 } );
				const layout = await primary.evaluate( ( b ) => {
					const r = b.getBoundingClientRect();
					return { overflow: document.documentElement.scrollWidth > window.innerWidth + 1, h: Math.round( r.height ), inside: r.left >= 0 && r.right <= window.innerWidth };
				} );
				check( ! layout.overflow && layout.h >= 44 && layout.inside, tag + 'at 390px nothing scrolls sideways and the button is at least 44px tall and on screen', layout );
				await page.screenshot( { path: path.join( shots, locale + '-store-390.png' ), fullPage: true } ).catch( () => {} );
				await page.setViewportSize( { width: 1440, height: 960 } );
				await page.screenshot( { path: path.join( shots, locale + '-store-1440.png' ), fullPage: false } ).catch( () => {} );

				// Integrations: both cards say Activate and come back to Integrations.
				await page.goto( baseUrl + '/wp-admin/admin.php?page=aisaautopilot-integrations', { waitUntil: 'load', timeout: 120000 } );
				const hrefs = await page.locator( '[data-sp-integration-card="store-copilot"] a, [data-sp-integration-card="efb-copilot"] a' ).evaluateAll( ( links ) => links.map( ( a ) => a.getAttribute( 'href' ) || '' ) );
				const activateLinks = hrefs.filter( ( h ) => h.includes( 'admin-post.php' ) && h.includes( 'aisaautopilot_activate_dependency' ) && h.includes( 'return=aisaautopilot-integrations' ) );
				check( 2 === activateLinks.length, tag + 'the Store Copilot and Easy Form Builder cards link to Activate and come back to Integrations', hrefs );
				await page.screenshot( { path: path.join( shots, locale + '-integrations-1440.png' ), fullPage: true } ).catch( () => {} );

				// Easy Form Builder page: the step plus the allowed list.
				await page.goto( baseUrl + '/wp-admin/admin.php?page=aisaautopilot-efb', { waitUntil: 'load', timeout: 120000 } );
				check(
					await page.locator( '[data-sp-efb-status][data-sp-dependency="easy-form-builder"] [data-sp-dependency-action="activate"]' ).isVisible()
					&& await page.locator( '[data-sp-efb-op]' ).count() >= 9,
					tag + 'the Easy Form Builder page offers Activate and still lists what the copilot may change'
				);

				// Enter on the button activates WooCommerce and returns to Store Copilot.
				await page.goto( store, { waitUntil: 'load', timeout: 120000 } );
				await primary.focus();
				await Promise.all( [ page.waitForURL( ( u ) => String( u ).includes( 'aisaautopilot_dependency_result=activated' ), { timeout: 240000 } ), page.keyboard.press( 'Enter' ) ] );
				await page.waitForLoadState( 'load' );
				const notice = page.locator( '[data-sp-dependency-result="activated"]' );
				check(
					await notice.isVisible() && 'status' === await notice.getAttribute( 'role' ) && 5 === await page.locator( '[data-sp-subtab]' ).count()
					&& 0 === await page.locator( '[data-sp-dependency]' ).count() && page.url().includes( 'page=aisaautopilot-wc-dashboard' ),
					tag + 'Enter activates WooCommerce and comes back to Store Copilot with its tabs and a spoken confirmation'
				);
				await page.screenshot( { path: path.join( shots, locale + '-store-activated.png' ), fullPage: false } ).catch( () => {} );

				check( 0 === errors.length, tag + 'no page errors', errors );
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
	console.log( failures ? 'DEPENDENCY_MATRIX FAIL' : 'DEPENDENCY_MATRIX PASS' );
	process.exit( failures ? 1 : 0 );
} )();
