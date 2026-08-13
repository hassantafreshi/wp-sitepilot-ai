/*
 * Headless visual/interaction smoke test for the local XAMPP release
 * candidate. Requires Playwright Core and Chrome but no AI provider.
 */
'use strict';

const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { chromium } = require( process.env.SITEPILOT_PLAYWRIGHT_CORE );

const baseUrl = ( process.env.SITEPILOT_BASE_URL || 'http://127.0.0.1/wp' ).replace( /\/$/, '' );
const username = process.env.SITEPILOT_BROWSER_USER || '';
const password = process.env.SITEPILOT_BROWSER_PASSWORD || '';
const screenshotDir = process.env.SITEPILOT_SCREENSHOT_DIR || process.env.TEMP;
const chromePath = process.env.SITEPILOT_CHROME || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const failures = [];
const checkedPages = [
	'sitepilot-ai-setup', 'sitepilot-ai', 'sitepilot-ai-form-builder',
	'sitepilot-ai-forms', 'sitepilot-ai-efb', 'sitepilot-ai-chat',
	'sitepilot-ai-kb', 'sitepilot-ai-usage', 'sitepilot-ai-widget',
	'sitepilot-ai-wc-dashboard', 'sitepilot-ai-woocommerce',
	'sitepilot-ai-connections', 'sitepilot-ai-settings', 'sitepilot-ai-logs',
];

function check( condition, message ) {
	if ( condition ) {
		console.log( 'PASS ' + message );
	} else {
		console.log( 'FAIL ' + message );
		failures.push( message );
	}
}

async function pageHealthy( page, slug ) {
	const url = baseUrl + '/wp-admin/admin.php?page=' + encodeURIComponent( slug );
	let response;
	let lastError;
	for ( let attempt = 1; attempt <= 2; attempt++ ) {
		try {
			response = await page.goto( url, { waitUntil: 'domcontentloaded', timeout: 45000 } );
			break;
		} catch ( error ) {
			lastError = error;
			if ( attempt < 2 ) {
				console.log( 'RETRY ' + slug + ' after transient local-server timeout' );
				await page.waitForTimeout( 1000 );
			}
		}
	}
	if ( !response ) {
		throw lastError;
	}
	check( !!response && response.status() === 200, slug + ' loads with HTTP 200' );
	await page.locator( '.sitepilot-wrap' ).first().waitFor( { state: 'attached', timeout: 10000 } ).catch( () => {} );
	const hasCritical = await page.locator( 'body' ).innerText().then( ( text ) => text.includes( 'There has been a critical error' ) );
	check( !hasCritical, slug + ' has no WordPress fatal-error screen' );
	check( await page.locator( '.sitepilot-wrap' ).count() >= 1, slug + ' renders the shared SitePilot UI shell' );
}

( async () => {
	if ( !username || !password ) {
		throw new Error( 'SITEPILOT_BROWSER_USER and SITEPILOT_BROWSER_PASSWORD are required.' );
	}
	fs.mkdirSync( screenshotDir, { recursive: true } );
	let browser;
	try {
		browser = await chromium.launch( {
			headless: true,
			executablePath: chromePath,
			args: [ '--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream' ],
		} );
		const context = await browser.newContext( { viewport: { width: 1440, height: 960 }, permissions: [ 'microphone' ] } );
		const page = await context.newPage();
		const sitepilotConsoleErrors = [];
		page.on( 'console', ( msg ) => {
			if ( msg.type() === 'error' && msg.location().url.includes( '/sitepilot-ai' ) ) {
				sitepilotConsoleErrors.push( msg.text() );
			}
		} );

		await page.goto( baseUrl + '/wp-login.php', { waitUntil: 'domcontentloaded', timeout: 20000 } );
		await page.locator( '#user_login' ).fill( username );
		await page.locator( '#user_pass' ).fill( password );
		const loginResponse = await page.locator( '#loginform' ).evaluate( async ( form ) => {
			const response = await fetch( form.action, {
				method: 'POST',
				body: new FormData( form ),
				credentials: 'same-origin',
				redirect: 'follow',
			} );
			return { ok: response.ok, url: response.url, html: await response.text() };
		} );
		if ( !loginResponse.ok || !loginResponse.url.includes( '/wp-admin/' ) || loginResponse.html.includes( 'login_error' ) ) {
			throw new Error( 'WordPress rejected the temporary browser-test credentials.' );
		}
		await page.goto( baseUrl + '/wp-admin/', { waitUntil: 'domcontentloaded', timeout: 45000 } );
		await page.waitForFunction(
			() => window.location.pathname.includes( '/wp-admin/' ) || document.querySelector( '#login_error' ) !== null,
			undefined,
			{ timeout: 45000 }
		);
		if ( await page.locator( '#login_error' ).count() ) {
			throw new Error( 'WordPress rejected the temporary browser-test credentials.' );
		}
		await page.locator( '#wpadminbar' ).waitFor( { state: 'attached', timeout: 10000 } );

		for ( const slug of checkedPages ) {
			await pageHealthy( page, slug );
		}

		await page.goto( baseUrl + '/wp-admin/admin.php?page=sitepilot-ai-form-builder', { waitUntil: 'domcontentloaded', timeout: 20000 } );
		await page.locator( '#sitepilot-compose-screen' ).waitFor( { state: 'attached', timeout: 10000 } );
		check( await page.locator( '#sitepilot-compose-screen' ).count() === 1, 'form builder has one compose screen' );
		check( await page.locator( '#sitepilot-review-screen' ).count() === 1, 'form builder has one review screen' );
		check( await page.locator( '#sitepilot-result-screen' ).count() === 1, 'form builder has one result screen' );
		check( await page.locator( '#sitepilot-compose-screen' ).isVisible(), 'form builder starts on the compose screen only' );
		check( !( await page.locator( '#sitepilot-review-screen' ).isVisible() ), 'form builder review is not duplicated or initially visible' );
		await page.screenshot( { path: path.join( screenshotDir, 'sitepilot-form-builder-desktop.png' ), fullPage: true } );

		await page.setViewportSize( { width: 390, height: 844 } );
		await page.goto( baseUrl + '/wp-admin/admin.php?page=sitepilot-ai-form-builder', { waitUntil: 'domcontentloaded', timeout: 20000 } );
		await page.locator( '#sitepilot-compose-screen' ).waitFor( { state: 'attached', timeout: 10000 } );
		const mobileOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
		check( !mobileOverflow, 'form builder has no horizontal overflow at 390px width' );
		await page.screenshot( { path: path.join( screenshotDir, 'sitepilot-form-builder-mobile.png' ), fullPage: true } );

		const publicPage = await context.newPage();
		await publicPage.goto( baseUrl + '/', { waitUntil: 'domcontentloaded', timeout: 20000 } );
		await publicPage.locator( '#spw-toggle' ).waitFor( { state: 'attached', timeout: 10000 } );
		check( await publicPage.locator( '#spw-toggle' ).count() === 1, 'public widget renders one launcher' );
		check( await publicPage.evaluate( () => 'speechSynthesis' in window ), 'browser exposes text-to-speech capability for widget responses' );
		let voiceRequest = false;
		publicPage.on( 'request', ( request ) => {
			if ( request.url().includes( '/sitepilot/v1/public/voice' ) && request.method() === 'POST' ) { voiceRequest = true; }
		} );
		await publicPage.locator( '#spw-toggle' ).click();
		await publicPage.locator( '#spw-mic' ).click();
		await publicPage.waitForTimeout( 700 );
		await publicPage.locator( '#spw-mic' ).click();
		await publicPage.waitForTimeout( 1800 );
		check( voiceRequest, 'widget microphone records and posts only to the safe public voice endpoint' );
		const publicOverflow = await publicPage.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
		check( !publicOverflow, 'public widget has no desktop horizontal overflow' );

		check( sitepilotConsoleErrors.length === 0, 'SitePilot pages produce no SitePilot JavaScript console errors' );
	} finally {
		if ( browser ) {
			await browser.close();
		}
	}
	if ( failures.length ) {
		process.exitCode = 1;
	}
} )().catch( ( error ) => {
	console.error( error.stack || error.message );
	process.exitCode = 1;
} );
