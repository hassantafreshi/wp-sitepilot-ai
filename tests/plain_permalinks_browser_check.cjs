/*
 * Admin screens on a site with Plain permalinks, where the REST root is
 * "index.php?rest_route=/…". A path that carries its own query string must join it with
 * "&" there; a second "?" sends the request to a route that does not exist (404), which
 * once left the Site Knowledge list and the unanswered questions empty on such sites.
 *
 * Needs a WordPress site with Plain permalinks and the plugin active:
 *   AISAAUTOPILOT_PLAIN_BASE_URL   e.g. http://127.0.0.1:8081
 *   AISAAUTOPILOT_BROWSER_USER / AISAAUTOPILOT_BROWSER_PASSWORD   an administrator
 */
'use strict';
const { chromium } = require( 'playwright' );

const base = ( process.env.AISAAUTOPILOT_PLAIN_BASE_URL || '' ).replace( /\/$/, '' );
const user = process.env.AISAAUTOPILOT_BROWSER_USER || '';
const password = process.env.AISAAUTOPILOT_BROWSER_PASSWORD || '';
let failures = 0;
function check( ok, name, detail ) {
	console.log( ( ok ? 'PASS ' : 'FAIL ' ) + name );
	if ( ! ok ) {
		failures++;
		( detail || [] ).forEach( ( line ) => console.log( '    - ' + line ) );
	}
}

( async () => {
	if ( ! base || ! user || ! password ) {
		throw new Error( 'Set AISAAUTOPILOT_PLAIN_BASE_URL, AISAAUTOPILOT_BROWSER_USER and AISAAUTOPILOT_BROWSER_PASSWORD.' );
	}
	const browser = await chromium.launch();
	const page = await browser.newPage( { viewport: { width: 1440, height: 1000 } } );
	const calls = [];
	page.on( 'response', ( response ) => {
		const url = response.url();
		if ( url.includes( 'rest_route=' ) && url.includes( 'aisaautopilot' ) ) {
			calls.push( { status: response.status(), method: response.request().method(), route: decodeURIComponent( url.split( 'rest_route=' )[ 1 ] ) } );
		}
	} );
	page.on( 'dialog', ( dialog ) => dialog.accept() );

	await page.goto( base + '/wp-login.php' );
	await page.fill( '#user_login', user );
	await page.fill( '#user_pass', password );
	await Promise.all( [ page.waitForURL( ( url ) => ! String( url ).includes( 'wp-login.php' ), { timeout: 60000 } ), page.click( '#wp-submit' ) ] );

	const restRoot = await page.evaluate( async ( url ) => ( await fetch( url + '/wp-admin/admin.php?page=aisaautopilot-kb' ) ).text(), base )
		.then( ( html ) => /rest_route=/.test( html ) );
	check( restRoot, 'the site uses Plain permalinks (the REST root carries ?rest_route=)' );

	const screens = [
		[ 'aisaautopilot-kb', '/aisaautopilot/v1/kb/gaps' ],
		[ 'aisaautopilot-kb&tab=entries', '/aisaautopilot/v1/kb/list' ],
		[ 'aisaautopilot-handoffs', '' ],
		[ 'aisaautopilot-conversations', '' ],
		[ 'aisaautopilot-chat', '/aisaautopilot/v1/chat/list' ],
	];
	for ( const [ slug, expected ] of screens ) {
		const before = calls.length;
		await page.goto( base + '/wp-admin/admin.php?page=' + slug, { waitUntil: 'load', timeout: 60000 } );
		await page.waitForTimeout( 2500 );
		const mine = calls.slice( before );
		if ( expected ) {
			const hit = mine.find( ( c ) => c.route.startsWith( expected ) );
			check( !! hit && hit.status < 400, slug + ' reaches ' + expected, mine.map( ( c ) => c.status + ' ' + c.method + ' ' + c.route ) );
		}
	}

	await page.goto( base + '/wp-admin/admin.php?page=aisaautopilot-settings', { waitUntil: 'load', timeout: 60000 } );
	const run = page.locator( '[data-sp-retention-run]' );
	await run.scrollIntoViewIfNeeded();
	const answered = page.waitForResponse( ( response ) => response.url().includes( 'retention' ) && 'POST' === response.request().method(), { timeout: 20000 } ).catch( () => null );
	await run.click();
	const retention = await answered;
	check( !! retention && 200 === retention.status(), '"Delete expired data now" reaches its route', retention ? [ retention.status() + ' ' + decodeURIComponent( retention.url() ) ] : [ 'no request' ] );

	const failed = calls.filter( ( c ) => c.status >= 400 );
	check( 0 === failed.length, 'no plugin REST request failed (' + calls.length + ' made)', failed.map( ( c ) => c.status + ' ' + c.method + ' ' + c.route ) );
	await browser.close();
	console.log( failures ? 'PLAIN_PERMALINKS FAIL' : 'PLAIN_PERMALINKS PASS' );
	process.exitCode = failures ? 1 : 0;
} )().catch( ( error ) => {
	console.error( error );
	process.exitCode = 1;
} );
