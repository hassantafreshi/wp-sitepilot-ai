/* End-to-end check of a Copilot conditional-logic rule on a real Easy Form Builder site:
 * the Copilot adds a dropdown, a text field and a "show Company for Business account" rule
 * (scripted model decision, real server reply through efb_copilot_offline_server.php), the
 * admin saves with EFB's own Save button, and a visitor on the published page sees Company
 * appear and disappear with the answer. Automates MANUAL-TESTING.md 9.9 and 9.10.
 *
 * Needs EFB's Conditional Logic add-on active on Free Plus or Pro; otherwise it reports SKIP
 * and exits 2. Everything it creates (QA user, form, page) is removed in finally.
 * Run: node tests/efb_copilot_logic_e2e_check.cjs   (same AISAAUTOPILOT_* variables as the matrices) */
'use strict';

const { execFileSync } = require( 'node:child_process' );
const path = require( 'node:path' );
const { chromium } = require( 'playwright-core' );
const { MESSAGES, fulfillScripted } = require( './efb_copilot_t74_steps.cjs' );

const php = process.env.AISAAUTOPILOT_PHP || 'C:\\xampp\\php\\php.exe';
const baseUrl = ( process.env.AISAAUTOPILOT_BASE_URL || 'http://127.0.0.1/wp' ).replace( /\/$/, '' );
const chromePath = process.env.AISAAUTOPILOT_CHROME || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const userHelper = path.join( __dirname, 'local_browser_user.php' );
const fixtureHelper = path.join( __dirname, 'local_efb_copilot_browser_fixture.php' );
const failures = [];

function check( condition, message ) {
	console.log( ( condition ? 'PASS ' : 'FAIL ' ) + message );
	if ( ! condition ) { failures.push( message ); }
}
function helper( file, mode ) {
	return execFileSync( php, [ file, mode ], { encoding: 'utf8', windowsHide: true } ).trim();
}

( async () => {
	let skipped = false;
	const account = JSON.parse( helper( userHelper, 'create' ) );
	const browser = await chromium.launch( { executablePath: chromePath, headless: true } );
	try {
		const fixture = JSON.parse( helper( fixtureHelper, 'prepare' ) );
		const admin = await browser.newPage( { viewport: { width: 1440, height: 960 } } );
		const options = { efbPlan: 2, privacyUrl: '', privacyTitle: '' };
		await admin.route( '**/wp-json/aisaautopilot/v1/efb/chat**', async ( route ) => {
			if ( ! await fulfillScripted( route, options ) ) { await route.fulfill( { status: 400, contentType: 'application/json', body: '{"ok":false,"code":"provider_not_configured"}' } ); }
		} );
		const saves = [];
		admin.on( 'request', ( item ) => { if ( 'POST' === item.method() && ( item.postData() || '' ).includes( 'action=update_form_Emsfb' ) ) { saves.push( item.url() ); } } );

		await admin.goto( baseUrl + '/wp-login.php', { waitUntil: 'domcontentloaded' } );
		await admin.locator( '#user_login' ).fill( account.username );
		await admin.locator( '#user_pass' ).fill( account.password );
		await Promise.all( [ admin.waitForURL( ( url ) => ! String( url ).includes( 'wp-login.php' ), { waitUntil: 'commit', timeout: 60000 } ), admin.locator( '#wp-submit' ).click( { timeout: 60000 } ) ] );
		await admin.goto( fixture.edit_url, { waitUntil: 'domcontentloaded', timeout: 45000 } );
		await admin.locator( '#aisaautopilot-efb-launch' ).waitFor( { state: 'visible', timeout: 20000 } );
		await admin.waitForFunction( () => typeof valj_efb !== 'undefined' && Array.isArray( valj_efb ) && valj_efb.length > 2, undefined, { timeout: 20000 } ); // eslint-disable-line no-undef

		const env = await admin.evaluate( () => ( {
			logic: typeof efb_var !== 'undefined' && !! efb_var && !! efb_var.addons && Number( efb_var.addons.AdnSMF ) >= 1, // eslint-disable-line no-undef
			code: Number( ( window.AISAAutoPilotEFB && window.AISAAutoPilotEFB.license && window.AISAAutoPilotEFB.license.code ) || 2 ),
		} ) );
		options.efbPlan = env.code;
		console.log( 'ENV ' + JSON.stringify( env ) );
		if ( ! env.logic || 2 === env.code ) {
			skipped = true;
			console.log( 'SKIP Conditional Logic add-on inactive or EFB Free plan: activate the add-on on Free Plus or Pro to run this check.' );
			return;
		}

		await admin.locator( '#aisaautopilot-efb-launch' ).click();
		const composer = admin.locator( '#aisaautopilot-efb-input' );
		for ( const message of [ MESSAGES.add, MESSAGES.rename, MESSAGES.show ] ) {
			const before = await admin.locator( '#aisaautopilot-efb-messages .aisaautopilot-efb-msg--ai' ).count();
			await composer.fill( message );
			await composer.press( 'Enter' );
			await admin.waitForFunction( ( count ) => document.querySelectorAll( '#aisaautopilot-efb-messages .aisaautopilot-efb-msg--ai' ).length > count && ! document.getElementById( 'aisaautopilot-efb-typing' ), before, { timeout: 20000 } );
		}
		const editorRules = await admin.evaluate( () => JSON.parse( JSON.stringify( valj_efb[ 0 ].logic_rules || [] ) ) ); // eslint-disable-line no-undef
		check( 1 === editorRules.length, 'the Copilot added one rule to the open form' );

		// EFB's own toolbar Save, exactly what the admin clicks.
		await admin.keyboard.press( 'Escape' );
		await admin.locator( '[onclick*="saveFormEfb(1)"]' ).first().click();
		for ( let i = 0; i < 40 && ! saves.length; i++ ) { await admin.waitForTimeout( 250 ); }
		await admin.waitForTimeout( 3000 );
		check( 1 === saves.length, 'Easy Form Builder\'s Save button sends the form once' );

		const published = JSON.parse( helper( fixtureHelper, 'publish' ) );
		console.log( 'STORED ' + JSON.stringify( { logic: published.logic, rules: published.logic_rules } ) );
		check( [ true, 1, '1' ].includes( published.logic ), 'EFB stored the form with conditional logic switched on' );
		check( JSON.stringify( published.logic_rules ) === JSON.stringify( editorRules ), 'EFB\'s server-side sanitiser kept the Copilot rule unchanged' );

		// A visitor in a fresh, logged-out context.
		const visitor = await ( await browser.newContext( { viewport: { width: 1280, height: 900 } } ) ).newPage();
		const pageErrors = [];
		visitor.on( 'pageerror', ( error ) => pageErrors.push( error.message ) );
		await visitor.goto( published.page_url, { waitUntil: 'load', timeout: 45000 } );
		const company = visitor.locator( '[data-id="company-id"]' ).first();
		const choice = visitor.locator( 'select[data-id="customer_type-el"], select[id^="customer_type"]' ).first();
		await choice.waitFor( { state: 'attached', timeout: 20000 } );
		await visitor.waitForTimeout( 1500 );
		check( await company.isHidden(), 'the published form hides Company until a customer type is chosen' );
		await choice.selectOption( { label: 'Business account' } );
		const shown = await company.waitFor( { state: 'visible', timeout: 5000 } ).then( () => true ).catch( () => false );
		check( shown, 'choosing "Business account" shows Company' );
		await choice.selectOption( { label: 'Personal' } );
		const hidden = await company.waitFor( { state: 'hidden', timeout: 5000 } ).then( () => true ).catch( () => false );
		check( hidden, 'choosing "Personal" hides Company again' );
		check( 0 === pageErrors.length, 'the public form runs without page errors' + ( pageErrors.length ? ' (' + pageErrors.join( ' | ' ).slice( 0, 300 ) + ')' : '' ) );
	} finally {
		await browser.close();
		try { console.log( helper( fixtureHelper, 'restore' ) ); } catch ( error ) { console.error( 'Fixture cleanup failed.' ); failures.push( 'cleanup' ); }
		try { console.log( helper( userHelper, 'delete' ) ); } catch ( error ) { console.error( 'User cleanup failed.' ); failures.push( 'cleanup' ); }
	}
	console.log( skipped ? 'EFB_LOGIC_E2E SKIP' : ( failures.length ? 'EFB_LOGIC_E2E FAIL ' + failures.length : 'EFB_LOGIC_E2E PASS' ) );
	process.exitCode = skipped ? 2 : ( failures.length ? 1 : 0 );
} )().catch( ( error ) => { console.error( error.stack || error.message ); process.exitCode = 1; } );
