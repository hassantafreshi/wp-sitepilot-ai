/*
 * Your forms: submissions kept because their email failed. In en_US and fa_IR, at desktop
 * and phone width: the list shows both kept submissions with their answers, Resend reports
 * a real failure (this test site has no mail server) and keeps the row, Delete asks first
 * and removes the row, the controls are at least 24x24 and the page does not scroll
 * sideways. Credentials stay in subprocess memory, never stdout.
 */
'use strict';
require( './test-env.cjs' ); // AISAAUTOPILOT_* defaults: the local test copy, else the owner's XAMPP
const { execFileSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { chromium } = require( process.env.AISAAUTOPILOT_PLAYWRIGHT_CORE || 'playwright' );

const php = process.env.AISAAUTOPILOT_PHP || 'C:\\xampp\\php\\php.exe';
const baseUrl = ( process.env.AISAAUTOPILOT_BASE_URL || 'http://localhost/wp' ).replace( /\/$/, '' );
const shots = process.env.AISAAUTOPILOT_SCREENSHOT_DIR || path.resolve( __dirname, '../../../../test-artifacts/form-outbox' );
function helper( name, mode, env = process.env ) {
	return execFileSync( php, [ path.join( __dirname, name ), mode ], { env, encoding: 'utf8', windowsHide: true } ).trim();
}
let failures = 0;
function check( ok, name, detail ) {
	console.log( ( ok ? 'PASS ' : 'FAIL ' ) + name );
	if ( ! ok ) {
		failures++;
		if ( detail ) { console.log( '    - ' + JSON.stringify( detail ) ); }
	}
}

( async () => {
	try {
		for ( const locale of ( process.env.AISAAUTOPILOT_OUTBOX_LOCALES || 'en_US,fa_IR' ).split( ',' ) ) {
			const env = { ...process.env, AISAAUTOPILOT_BROWSER_LOCALE: locale };
			const account = JSON.parse( helper( 'local_browser_user.php', 'create', env ) );
			const state = JSON.parse( helper( 'local_form_outbox_browser_fixture.php', 'prepare', env ).split( '\n' ).pop() );
			const tag = locale + ': ';
			console.log( 'FORM_OUTBOX_MATRIX ' + locale );
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
				await page.goto( baseUrl + '/wp-admin/admin.php?page=aisaautopilot-forms', { waitUntil: 'load' } );

				const box = page.locator( '[data-sp-form-outbox]' );
				const rows = box.locator( '[data-sp-outbox-row]' );
				check( 1 === await box.count() && 2 === await rows.count(), tag + 'Your forms lists both kept submissions' );
				const dir = await page.evaluate( () => getComputedStyle( document.querySelector( '[data-sp-form-outbox]' ) ).direction );
				check( dir === ( 'fa_IR' === locale ? 'rtl' : 'ltr' ), tag + 'the list follows the admin language direction' );
				const first = rows.first();
				await first.locator( 'summary' ).click();
				check( ( await first.locator( 'pre' ).innerText() ).includes( 'Name: Ada Browser' ), tag + 'Show the answers reveals the kept answers' );

				// Resend on a site that cannot send mail: the failure is reported and the row stays.
				const resendDone = page.waitForResponse( ( r ) => r.url().includes( '/resend' ), { timeout: 60000 } );
				await first.locator( '[data-sp-outbox-resend]' ).click();
				const resend = await resendDone;
				await page.waitForTimeout( 300 );
				const status = ( await first.locator( '[data-sp-outbox-status]' ).innerText() ).trim();
				check( 502 === resend.status() && status.length > 10 && 2 === await rows.count(), tag + 'a failed Resend explains why and keeps the submission', { http: resend.status(), status } );
				check( ( 'fa_IR' === locale ) === /[\u0600-\u06FF]/.test( status ), tag + 'the explanation is in the admin language', status );
				const titleBefore = ( await box.locator( '[data-sp-form-outbox-title]' ).innerText() ).trim();

				// Delete asks first; dismissing keeps it, accepting removes it.
				page.once( 'dialog', ( d ) => d.dismiss() );
				await rows.nth( 1 ).locator( '[data-sp-outbox-delete]' ).click();
				await page.waitForTimeout( 300 );
				check( 2 === await rows.count(), tag + 'Delete asks first; Cancel keeps the submission' );
				page.once( 'dialog', ( d ) => d.accept() );
				const deleteDone = page.waitForResponse( ( r ) => r.url().includes( '/delete' ), { timeout: 60000 } );
				await rows.nth( 1 ).locator( '[data-sp-outbox-delete]' ).click();
				await deleteDone;
				await page.waitForFunction( () => 1 === document.querySelectorAll( '[data-sp-outbox-row]' ).length, null, { timeout: 10000 } ).catch( () => {} );
				check( 1 === await rows.count(), tag + 'confirming Delete removes the submission from the list' );
				const titleAfter = ( await box.locator( '[data-sp-form-outbox-title]' ).innerText() ).trim();
				check( titleAfter !== titleBefore && ! titleAfter.includes( '2' ) && ! titleAfter.includes( '۲' ), tag + 'the heading count follows the list', { titleBefore, titleAfter } );

				fs.mkdirSync( path.join( shots, locale ), { recursive: true } );
				await page.screenshot( { path: path.join( shots, locale, 'form-outbox-desktop.png' ), fullPage: true } );
				await page.setViewportSize( { width: 390, height: 844 } );
				await page.waitForTimeout( 400 );
				const layout = await page.evaluate( () => {
					const small = Array.from( document.querySelectorAll( '[data-sp-form-outbox] button, [data-sp-form-outbox] summary' ) )
						.map( ( el ) => el.getBoundingClientRect() ).filter( ( r ) => r.width > 0 && ( r.width < 24 || r.height < 24 ) ).length;
					return { small, overflow: document.documentElement.scrollWidth > window.innerWidth + 1 };
				} );
				check( 0 === layout.small && ! layout.overflow, tag + 'at 390px every control is at least 24x24 and nothing scrolls sideways', layout );
				await page.screenshot( { path: path.join( shots, locale, 'form-outbox-mobile.png' ), fullPage: true } );
				check( 0 === errors.length, tag + 'no script errors', errors );
			} finally {
				await browser.close();
				console.log( helper( 'local_form_outbox_browser_fixture.php', 'restore', env ) );
				console.log( helper( 'local_browser_user.php', 'delete', env ) );
			}
		}
	} catch ( error ) {
		console.error( error );
		failures++;
	}
	console.log( failures ? 'FORM_OUTBOX_MATRIX FAIL' : 'FORM_OUTBOX_MATRIX PASS' );
	process.exitCode = failures ? 1 : 0;
} )();
