/*
 * Browser checks of the Easy Form Builder copilot screens on a real WordPress with Easy Form Builder (dev4):
 * the copilot inside EFB designs a form whose calculation needs an add-on the site lacks (the preview names the add-on
 * and its plan, shows the field used instead, offers the install), Create stores it through EFB, the editor then says
 * what EFB reported and offers Undo; and the plugin's "Easy Form Builder copilot" page lists what EFB reports about
 * this site. en_US at 1280 px, fa_IR and ar at 375 px, screenshots for each.
 *
 *   AISA_BASE=http://127.0.0.1:8090 AISA_USER=admin AISA_PASS=… AISA_WP="php wp-cli.phar --path=…" \
 *   AISA_SHOTS=/tmp/shots AISA_CHROME=/usr/bin/google-chrome node tests/efb_e2e/ui.cjs
 *
 * The AI provider is the inert mock (aisa-ai-mock-mu.php); replies are queued with ctl.php.
 */
'use strict';

const { execSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { chromium } = require( 'playwright-core' );

const BASE = ( process.env.AISA_BASE || 'http://127.0.0.1:8090' ).replace( /\/$/, '' );
const USER = process.env.AISA_USER || 'admin';
const PASS = process.env.AISA_PASS || '';
const WP = process.env.AISA_WP || 'wp';
const SHOTS = process.env.AISA_SHOTS || path.join( __dirname, 'shots' );
const CHROME = process.env.AISA_CHROME || undefined;
const CTL = path.join( __dirname, 'ctl.php' );
const failures = [];

function check( ok, name, detail ) {
	console.log( ( ok ? 'PASS ' : 'FAIL ' ) + name + ( ok || detail === undefined ? '' : ' :: ' + JSON.stringify( detail ).slice( 0, 400 ) ) );
	if ( ! ok ) { failures.push( name ); }
}
function ctl( ...args ) {
	const quoted = args.map( ( a ) => "'" + String( a ).replace( /'/g, "'\\''" ) + "'" ).join( ' ' );
	return execSync( `${ WP } eval-file ${ CTL } ${ quoted }`, { encoding: 'utf8' } ).trim().split( '\n' ).pop();
}

const CREATE_REPLY = {
	reply: 'Here is the order form.',
	action: 'create_form',
	form: {
		title: 'Catering order',
		fields: [
			{ type: 'text', label: 'Full name', required: true },
			{ type: 'email', label: 'Email', required: true },
			{ type: 'number', label: 'Guests', required: true },
			{ type: 'calc', label: 'Estimated total' },
		],
	},
};

async function login( page ) {
	await page.goto( BASE + '/wp-login.php', { waitUntil: 'domcontentloaded' } );
	await page.fill( '#user_login', USER );
	await page.fill( '#user_pass', PASS );
	await Promise.all( [ page.waitForURL( ( u ) => ! /wp-login\.php/.test( u.toString() ), { timeout: 60000 } ), page.click( '#wp-submit' ) ] );
}

async function noHorizontalScroll( page ) {
	return page.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1 );
}

async function run( locale, width, height ) {
	ctl( 'locale', locale );
	const browser = await chromium.launch( CHROME ? { executablePath: CHROME } : {} );
	const context = await browser.newContext( { viewport: { width, height }, deviceScaleFactor: 1 } );
	const page = await context.newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( String( e && e.message || e ) ) );
	const tag = `${ locale }-${ width }`;
	const rtl = locale !== 'en_US';
	try {
		await login( page );

		// 1. The plugin's EFB screen: what Easy Form Builder reports about this site.
		await page.goto( BASE + '/wp-admin/admin.php?page=aisaautopilot-efb', { waitUntil: 'networkidle', timeout: 90000 } );
		const site = page.locator( '[data-sp-efb-site="registry"]' );
		check( await site.count() === 1, `${ tag }: EFB copilot page shows "On this site" from EFB's registry` );
		const calcRow = page.locator( '[data-sp-efb-site-row="AdnCLC"] .sp-efbx-tag' );
		check( ( await calcRow.count() ) === 1, `${ tag }: Numbers & Calculations is listed with its state` );
		check( await page.locator( '[data-sp-efb-site-row="form_package"]' ).count() === 1, `${ tag }: the form package version is shown` );
		check( await noHorizontalScroll( page ), `${ tag }: EFB copilot page has no horizontal scroll` );
		if ( rtl ) { check( 'rtl' === await page.evaluate( () => document.documentElement.dir ), `${ tag }: page is right to left` ); }
		await page.screenshot( { path: path.join( SHOTS, `efb-page-${ tag }.png` ), fullPage: true } );

		// 2. The copilot inside EFB: a calculation the site cannot render becomes a Number field, named in the preview.
		ctl( 'queue', JSON.stringify( CREATE_REPLY ) );
		await page.goto( BASE + '/wp-admin/admin.php?page=Emsfb_create', { waitUntil: 'networkidle', timeout: 90000 } );
		await page.waitForSelector( '#aisaautopilot-efb-launch', { timeout: 60000 } );
		await page.evaluate( () => document.getElementById( 'aisaautopilot-efb-launch' ).click() );
		await page.waitForSelector( '#aisaautopilot-efb-chat:not(.is-closed)', { timeout: 15000 } );
		await page.fill( '#aisaautopilot-efb-input', 'A catering order form with an estimated total of guests × 20' );
		await page.click( '#aisaautopilot-efb-send' );
		await page.waitForSelector( '[data-sp-efb-state="preview"]', { timeout: 60000 } );
		const addonList = page.locator( '[data-sp-efb-addon-required]' );
		check( 1 === await addonList.count() && 'AdnCLC' === await addonList.getAttribute( 'data-sp-efb-addon-required' ), `${ tag }: the preview lists the calculation that needs Numbers & Calculations` );
		check( 0 === await page.locator( '[data-sp-efb-field="calc"]' ).count() && 2 === await page.locator( '[data-sp-efb-field="number"]' ).count(), `${ tag }: the preview shows a Number field, never a calc field` );
		check( 1 === await page.locator( '[data-sp-efb-install="AdnCLC"]' ).count(), `${ tag }: the install of Numbers & Calculations is offered` );
		check( await noHorizontalScroll( page ), `${ tag }: copilot preview has no horizontal scroll` );
		const chat = page.locator( '#aisaautopilot-efb-chat' );
		// The preview card from its title: the fields, then the add-on list.
		await page.locator( '[data-sp-efb-state="preview"]' ).evaluate( ( el ) => el.scrollIntoView( { block: 'start' } ) );
		await page.waitForTimeout( 300 );
		await page.screenshot( { path: path.join( SHOTS, `efb-copilot-preview-${ tag }.png` ), fullPage: false } );
		await chat.screenshot( { path: path.join( SHOTS, `efb-copilot-preview-panel-${ tag }.png` ) } );

		// 3. Create: EFB stores it (form package); the editor opens with what EFB reported and Undo.
		await page.click( '[data-sp-efb-create="1"]' );
		await page.waitForURL( /state=edit-form&id=\d+/, { timeout: 60000 } );
		const formId = Number( /id=(\d+)/.exec( page.url() )[ 1 ] );
		const stored = JSON.parse( ctl( 'form', formId ) );
		const types = ( stored.rows || [] ).map( ( r ) => r && r.type );
		const email = ( stored.rows || [] ).find( ( r ) => r && r.type === 'email' ) || {};
		check( types.includes( 'number' ) && ! types.includes( 'calc' ), `${ tag }: the stored EFB form has the Number field and no calc`, types );
		check( stored.rows && stored.rows[ 0 ].email_to === email.id_ && String( email.noti ) === '1', `${ tag }: the stored form confirms to the visitor at the first Email field`, stored.rows && stored.rows[ 0 ] );
		await page.waitForSelector( '[data-sp-efb-state="created"]', { timeout: 30000 } );
		check( 1 === await page.locator( `[data-sp-efb-undo="${ formId }"]` ).count(), `${ tag }: the editor offers Undo for the new form` );
		await page.waitForTimeout( 800 );
		await page.screenshot( { path: path.join( SHOTS, `efb-copilot-created-${ tag }.png` ), fullPage: false } );
		await page.click( `[data-sp-efb-undo="${ formId }"]` );
		await page.waitForURL( /page=Emsfb(&|$)/, { timeout: 30000 } );
		const gone = JSON.parse( ctl( 'form', formId ) );
		check( null === gone.rows, `${ tag }: Undo removed the form through EFB`, gone );
		check( 0 === errors.length, `${ tag }: no JavaScript errors`, errors );
	} catch ( e ) {
		check( false, `${ tag }: run`, String( e && e.stack || e ) );
		try { await page.screenshot( { path: path.join( SHOTS, `failure-${ tag }.png` ), fullPage: true } ); } catch ( x ) {}
	} finally {
		await browser.close();
	}
}

( async () => {
	fs.mkdirSync( SHOTS, { recursive: true } );
	ctl( 'setup' );
	ctl( 'plan', 3 );
	ctl( 'addon', 'AdnCLC', 0 );
	try {
		await run( 'en_US', 1280, 900 );
		await run( 'fa_IR', 375, 812 );
		await run( 'ar', 375, 812 );
	} finally {
		ctl( 'locale', 'en_US' );
		ctl( 'addon', 'AdnCLC', 1 );
	}
	console.log( failures.length ? `\n${ failures.length } failed` : '\nall passed' );
	process.exit( failures.length ? 1 : 0 );
} )();
