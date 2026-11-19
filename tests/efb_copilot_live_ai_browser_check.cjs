/* Live browser check of the EFB Copilot with the real, configured AI provider: no mocked
 * replies. Flow 1 asks for two fields and a conditional rule in plain English on an open
 * form, saves with Easy Form Builder's own Save button and checks the published form as a
 * visitor. Flow 2 asks for a new contact form on the Create page, approves the preview and
 * checks the form Easy Form Builder actually created (consent checkbox and notifications).
 *
 * It spends real tokens (about three requests), so it runs only with AISAAUTOPILOT_LIVE_AI=1.
 * Needs EFB's Conditional Logic add-on active on Free Plus or Pro. Everything it creates
 * (QA user, forms, page) is removed in finally.
 * Run: AISAAUTOPILOT_LIVE_AI=1 node tests/efb_copilot_live_ai_browser_check.cjs */
'use strict';
require( './test-env.cjs' ); // AISAAUTOPILOT_* defaults: the local test copy, else the owner's XAMPP

const { execFileSync } = require( 'node:child_process' );
const path = require( 'node:path' );
const { chromium } = require( process.env.AISAAUTOPILOT_PLAYWRIGHT_CORE || 'playwright-core' );

if ( '1' !== process.env.AISAAUTOPILOT_LIVE_AI ) {
	console.log( 'SKIP set AISAAUTOPILOT_LIVE_AI=1 to call the real provider (it costs tokens).' );
	process.exit( 0 );
}

const php = process.env.AISAAUTOPILOT_PHP || 'C:\\xampp\\php\\php.exe';
const wpRoot = process.env.AISAAUTOPILOT_WP_ROOT || 'C:\\xampp\\htdocs\\wp';
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
/** Reads, or deletes, one EFB form row created by flow 2 (only rows this check recorded). */
function efbForm( id, remove ) {
	const code = 'require getenv("AISAAUTOPILOT_WP_ROOT") . "/wp-load.php"; global $wpdb; $t = $wpdb->prefix . "emsfb_form"; $id = (int) getenv("QA_FORM_ID");'
		+ ( remove ? ' $wpdb->delete( $t, array( "form_id" => $id ), array( "%d" ) ); echo "{}";'
			: ' $raw = (string) $wpdb->get_var( $wpdb->prepare( "SELECT form_structer FROM {$t} WHERE form_id = %d", $id ) ); $s = json_decode( $raw, true ); if ( ! is_array( $s ) ) { $s = json_decode( stripslashes( $raw ), true ); } echo wp_json_encode( is_array( $s ) ? $s : array() );' );
	return JSON.parse( execFileSync( php, [ '-r', code ], { encoding: 'utf8', windowsHide: true, env: { ...process.env, AISAAUTOPILOT_WP_ROOT: wpRoot, QA_FORM_ID: String( id ) } } ).trim() || '{}' );
}

( async () => {
	let created = 0;
	let skipped = false;
	const account = JSON.parse( helper( userHelper, 'create' ) );
	const browser = await chromium.launch( { executablePath: chromePath, headless: true } );
	try {
		const fixture = JSON.parse( helper( fixtureHelper, 'prepare' ) );
		const page = await browser.newPage( { viewport: { width: 1440, height: 960 } } );
		const pageErrors = [];
		page.on( 'pageerror', ( error ) => pageErrors.push( error.message ) );
		await page.goto( baseUrl + '/wp-login.php', { waitUntil: 'domcontentloaded' } );
		await page.locator( '#user_login' ).fill( account.username );
		await page.locator( '#user_pass' ).fill( account.password );
		await Promise.all( [ page.waitForURL( ( url ) => ! String( url ).includes( 'wp-login.php' ), { waitUntil: 'commit', timeout: 60000 } ), page.locator( '#wp-submit' ).click( { timeout: 60000 } ) ] );

		const ask = async ( text ) => {
			const messages = page.locator( '#aisaautopilot-efb-messages .aisaautopilot-efb-msg--ai' );
			const before = await messages.count();
			await page.locator( '#aisaautopilot-efb-input' ).fill( text );
			await page.locator( '#aisaautopilot-efb-input' ).press( 'Enter' );
			await page.waitForFunction( ( count ) => document.querySelectorAll( '#aisaautopilot-efb-messages .aisaautopilot-efb-msg--ai' ).length > count && ! document.getElementById( 'aisaautopilot-efb-typing' ), before, { timeout: 120000 } );
			const reply = messages.nth( before );
			console.log( 'REPLY ' + JSON.stringify( ( await reply.innerText() ).replace( /\s+/g, ' ' ).slice( 0, 220 ) ) );
			return reply;
		};

		// Flow 1: an open form, plain English, EFB's own Save, the visitor's view.
		await page.goto( fixture.edit_url, { waitUntil: 'domcontentloaded', timeout: 45000 } );
		await page.locator( '#aisaautopilot-efb-launch' ).waitFor( { state: 'visible', timeout: 20000 } );
		await page.waitForFunction( () => typeof valj_efb !== 'undefined' && Array.isArray( valj_efb ) && valj_efb.length > 2, undefined, { timeout: 20000 } ); // eslint-disable-line no-undef
		const logic = await page.evaluate( () => typeof efb_var !== 'undefined' && !! efb_var && !! efb_var.addons && Number( efb_var.addons.AdnSMF ) >= 1 ); // eslint-disable-line no-undef
		if ( ! logic ) {
			skipped = true;
			console.log( 'SKIP the Conditional Logic add-on is not active.' );
			return;
		}
		await page.locator( '#aisaautopilot-efb-launch' ).click();
		await ask( 'Add a dropdown called Customer type with the options Personal and Business, and a text field called Company.' );
		const fields = await page.evaluate( () => {
			const items = valj_efb.filter( ( it ) => it && it.type && ! [ 'form', 'step', 'option', 'r_matrix' ].includes( it.type ) ); // eslint-disable-line no-undef
			const byName = ( name ) => items.find( ( it ) => String( it.name || '' ).toLowerCase() === name ) || null;
			const choice = byName( 'customer type' );
			const company = byName( 'company' );
			return {
				choice: choice ? { id: String( choice.id_ ), type: choice.type, options: valj_efb.filter( ( o ) => o && 'option' === o.type && o.parent === choice.id_ ).map( ( o ) => ( { id: String( o.id_ ), value: String( o.value ) } ) ) } : null, // eslint-disable-line no-undef
				company: company ? { id: String( company.id_ ), type: company.type } : null,
			};
		} );
		console.log( 'FIELDS ' + JSON.stringify( fields ) );
		check( !! fields.choice && [ 'select', 'radio' ].includes( fields.choice.type ) && 2 === fields.choice.options.length && !! fields.company, 'the real model adds the dropdown with both options and the Company field' );
		if ( ! fields.choice || ! fields.company ) { return; }

		await ask( 'Show Company only when Customer type is Business.' );
		const rules = await page.evaluate( () => JSON.parse( JSON.stringify( valj_efb[ 0 ].logic_rules || [] ) ) ); // eslint-disable-line no-undef
		const business = ( fields.choice.options.find( ( o ) => 'business' === o.value.toLowerCase() ) || {} ).id;
		const rule = rules[ 0 ] || {};
		console.log( 'RULE ' + JSON.stringify( rule ) );
		check( 1 === rules.length && ( rule.conditions.items[ 0 ] || {} ).field_id === fields.choice.id && ( rule.conditions.items[ 0 ] || {} ).value === business && ( rule.actions[ 0 ] || {} ).target === fields.company.id, 'the real model\'s request becomes one rule on the right field, option id and target' );

		const saved = [];
		page.on( 'request', ( item ) => { if ( 'POST' === item.method() && ( item.postData() || '' ).includes( 'action=update_form_Emsfb' ) ) { saved.push( item.url() ); } } );
		await page.keyboard.press( 'Escape' );
		await page.locator( '[onclick*="saveFormEfb(1)"]' ).first().click();
		for ( let i = 0; i < 40 && ! saved.length; i++ ) { await page.waitForTimeout( 250 ); }
		await page.waitForTimeout( 3000 );
		const published = JSON.parse( helper( fixtureHelper, 'publish' ) );
		check( 1 === saved.length && JSON.stringify( published.logic_rules ) === JSON.stringify( rules ), 'Easy Form Builder\'s Save stores the rule unchanged' );

		const visitor = await ( await browser.newContext() ).newPage();
		await visitor.goto( published.page_url, { waitUntil: 'load', timeout: 45000 } );
		const company = visitor.locator( '[data-id="' + fields.company.id + '-id"]' ).first();
		const choice = visitor.locator( 'select[data-id="' + fields.choice.id + '-el"], [data-id="' + fields.choice.id + '-id"] select' ).first();
		await visitor.waitForTimeout( 1500 );
		const hiddenFirst = await company.isHidden();
		let shown = false;
		let hiddenAgain = false;
		if ( await choice.count() ) {
			await choice.selectOption( { label: 'Business' } );
			shown = await company.waitFor( { state: 'visible', timeout: 5000 } ).then( () => true ).catch( () => false );
			await choice.selectOption( { label: 'Personal' } );
			hiddenAgain = await company.waitFor( { state: 'hidden', timeout: 5000 } ).then( () => true ).catch( () => false );
		} else {
			const option = visitor.locator( '[data-id="' + fields.choice.id + '-id"] label', { hasText: 'Business' } ).first();
			await option.click();
			shown = await company.waitFor( { state: 'visible', timeout: 5000 } ).then( () => true ).catch( () => false );
			await visitor.locator( '[data-id="' + fields.choice.id + '-id"] label', { hasText: 'Personal' } ).first().click();
			hiddenAgain = await company.waitFor( { state: 'hidden', timeout: 5000 } ).then( () => true ).catch( () => false );
		}
		check( hiddenFirst && shown && hiddenAgain, 'on the published form Company appears only for Business' );

		// Flow 2: a new form from the Create page, approved, then read back from EFB.
		await page.goto( baseUrl + '/wp-admin/admin.php?page=Emsfb_create', { waitUntil: 'domcontentloaded', timeout: 45000 } );
		await page.locator( '#aisaautopilot-efb-launch' ).waitFor( { state: 'visible', timeout: 20000 } );
		await page.locator( '#aisaautopilot-efb-launch' ).click();
		const proposal = await ask( 'Build a contact form with name, email, mobile number and message. Email me each submission and send the sender a confirmation.' );
		const approve = proposal.locator( '.aisaautopilot-efb-btn--primary' );
		check( 1 === await approve.count(), 'the real model\'s new form arrives as a preview with Approve' );
		if ( await approve.count() ) {
			await Promise.all( [ page.waitForURL( /state=edit-form&id=\d+/, { timeout: 60000 } ), approve.click() ] );
			created = Number( ( /[?&]id=(\d+)/.exec( page.url() ) || [] )[ 1 ] || 0 );
			const structure = created ? efbForm( created, false ) : [];
			const types = ( Array.isArray( structure ) ? structure : [] ).map( ( item ) => item && item.type );
			const settings = ( Array.isArray( structure ) && structure[ 0 ] ) || {};
			const consent = ( Array.isArray( structure ) ? structure : [] ).find( ( item ) => item && 'trmCheckbox' === item.type ) || null;
			const emailField = ( Array.isArray( structure ) ? structure : [] ).find( ( item ) => item && 'email' === item.type ) || null;
			console.log( 'CREATED ' + JSON.stringify( { id: created, types, email: settings.email, sendEmail: settings.sendEmail, email_to: settings.email_to } ) );
			check( created > 0 && types.includes( 'email' ) && !! consent && 1 === Number( consent.required ), 'Approve creates a real EFB form with a required consent checkbox' );
			check( !! settings.email && [ true, 1, '1', 'true' ].includes( settings.sendEmail ) && !! emailField && settings.email_to === emailField.id_, 'the created form emails the site admin and confirms to the form\'s own email field' );
		}
		check( 0 === pageErrors.length, 'no uncaught page errors' + ( pageErrors.length ? ' (' + pageErrors.join( ' | ' ).slice( 0, 300 ) + ')' : '' ) );
	} finally {
		await browser.close();
		if ( created ) { try { efbForm( created, true ); } catch ( error ) { failures.push( 'cleanup' ); } }
		try { console.log( helper( fixtureHelper, 'restore' ) ); } catch ( error ) { failures.push( 'cleanup' ); }
		try { console.log( helper( userHelper, 'delete' ) ); } catch ( error ) { failures.push( 'cleanup' ); }
	}
	console.log( skipped ? 'EFB_LIVE_AI_BROWSER SKIP' : ( failures.length ? 'EFB_LIVE_AI_BROWSER FAIL ' + failures.length : 'EFB_LIVE_AI_BROWSER PASS' ) );
	process.exitCode = skipped ? 2 : ( failures.length ? 1 : 0 );
} )().catch( ( error ) => { console.error( error.stack || error.message ); process.exitCode = 1; } );
