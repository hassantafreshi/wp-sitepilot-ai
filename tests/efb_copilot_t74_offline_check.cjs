/* Offline browser check for the T74 copilot flow: no WordPress, no Easy Form Builder install,
 * no network. The page carries the real copilot markup, configuration and efb-copilot.js
 * (rendered by efb_copilot_offline_server.php from EfbBuilderButton), an EFB form structure
 * built by EfbFormConverter, and a small stand-in for EFB's editFormEfb() that reloads
 * valj_efb from sessionStorage and redraws one wrapper per field, as EFB's own renderer
 * does. /efb/chat is answered by the real controller through the offline server.
 *
 * It complements, and does not replace, the real-EFB run in efb_copilot_browser_matrix.cjs.
 * Usage: node tests/efb_copilot_t74_offline_check.cjs   (AISAAUTOPILOT_PHP and
 * AISAAUTOPILOT_CHROME are optional; Playwright's bundled Chromium is used when present). */
'use strict';

const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { execFileSync } = require( 'node:child_process' );
const { fulfillScripted, runT74 } = require( './efb_copilot_t74_steps.cjs' );

let playwright;
try { playwright = require( 'playwright' ); } catch ( error ) { playwright = require( 'playwright-core' ); }

const ORIGIN = 'https://wp.offline.test';
const EDIT_URL = ORIGIN + '/wp-admin/admin.php?page=Emsfb&state=edit-form&id=7';
const PRIVACY_URL = ORIGIN + '/privacy-policy/';
const ROOT = path.resolve( __dirname, '..' );
const PLAN = {
	title: 'AISAAutoPilot EFB Copilot offline QA',
	fields: [
		{ type: 'text', label: 'Full name', required: true },
		{ type: 'email', label: 'Work email', required: true },
		{ type: 'textarea', label: 'Message', required: false },
	],
};
// EFB's plan codes: 1 Pro, 3 Free Plus, 2 Free.
const SCENARIOS = [
	{ label: 'pro + logic add-on', efbPlan: 1, logic: true },
	{ label: 'free plus, add-on inactive', efbPlan: 3, logic: false },
	{ label: 'free + logic add-on', efbPlan: 2, logic: true },
];

const failures = [];
function check( condition, message ) {
	console.log( ( condition ? 'PASS ' : 'FAIL ' ) + message );
	if ( ! condition ) { failures.push( message ); }
}

function offlinePage( efbPlan ) {
	const php = process.env.AISAAUTOPILOT_PHP || ( 'win32' === process.platform ? 'C:\\xampp\\php\\php.exe' : 'php' );
	return JSON.parse( execFileSync( php, [ path.join( __dirname, 'efb_copilot_offline_server.php' ) ], { input: JSON.stringify( { mode: 'page', efb_plan: efbPlan, plan: PLAN } ), encoding: 'utf8' } ) );
}

function html( page, logic ) {
	const config = Object.assign( {}, page.config, { restUrl: ORIGIN + '/wp-json/aisaautopilot/v1/' } );
	const json = ( value ) => JSON.stringify( value ).replace( /</g, '\\u003c' );
	return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>EFB editor (offline)</title><style>'
		+ fs.readFileSync( path.join( ROOT, 'assets/admin/efb-copilot.css' ), 'utf8' )
		+ '</style></head><body><div id="dropZoneEFB"></div>' + page.markup
		+ '<script>'
		+ 'let valj_efb = ' + json( page.structure ) + ';'
		+ 'var efb_var = { addons: { AdnSMF: ' + ( logic ? 1 : 0 ) + ', AdnHSH: 0 } };'
		+ 'function drawEfb() { var zone = document.getElementById( "dropZoneEFB" ); zone.textContent = "";'
		+ ' valj_efb.forEach( function ( it ) { if ( ! it || ! it.type || [ "form", "step", "option", "r_matrix" ].indexOf( it.type ) !== -1 ) { return; }'
		+ ' var node = document.createElement( "div" ); node.className = "efbField"; node.setAttribute( "data-id", it.id_ + "-id" ); node.textContent = it.name || it.id_; zone.appendChild( node ); } ); }'
		// EFB 4.3.1 admin-efb.js: editFormEfb re-reads sessionStorage, then draws after a delay.
		+ 'let editFormEfb = async () => { if ( sessionStorage.getItem( "valj_efb" ) ) { valj_efb = JSON.parse( sessionStorage.getItem( "valj_efb" ) ); } document.getElementById( "dropZoneEFB" ).textContent = ""; setTimeout( drawEfb, 120 ); };'
		+ 'window.AISAAutoPilotEFB = ' + json( config ) + ';'
		+ 'drawEfb();'
		+ '</script><script>' + fs.readFileSync( path.join( ROOT, 'assets/admin/efb-copilot.js' ), 'utf8' ) + '</script></body></html>';
}

( async () => {
	const launch = {};
	if ( process.env.AISAAUTOPILOT_CHROME ) { launch.executablePath = process.env.AISAAUTOPILOT_CHROME; }
	const browser = await playwright.chromium.launch( launch );
	try {
		for ( const scenario of SCENARIOS ) {
			console.log( 'EFB_T74_OFFLINE ' + scenario.label );
			const context = await browser.newContext( { viewport: { width: 1440, height: 960 } } );
			const page = await context.newPage();
			const pageErrors = [];
			const consoleErrors = [];
			page.on( 'pageerror', ( error ) => pageErrors.push( error.message ) );
			page.on( 'console', ( message ) => { if ( 'error' === message.type() ) { consoleErrors.push( message.text() ); } } );
			const providerCalls = [];
			const options = { efbPlan: scenario.efbPlan, privacyUrl: PRIVACY_URL, privacyTitle: 'Privacy Policy', onReply: ( body, reply ) => providerCalls.push( reply.provider_calls ) };
			const document = html( offlinePage( scenario.efbPlan ), scenario.logic );
			await page.route( ORIGIN + '/**', async ( route ) => {
				const url = route.request().url();
				if ( url.startsWith( EDIT_URL ) ) { return route.fulfill( { status: 200, contentType: 'text/html', body: document } ); }
				if ( url.includes( '/aisaautopilot/v1/efb/log' ) ) { return route.fulfill( { status: 200, contentType: 'application/json', body: '{"ok":true}' } ); }
				if ( url.includes( '/aisaautopilot/v1/efb/chat' ) && await fulfillScripted( route, options ) ) { return undefined; }
				return route.fulfill( { status: 404, contentType: 'text/plain', body: 'not part of the offline editor' } );
			} );
			await page.goto( EDIT_URL );
			await page.locator( '#aisaautopilot-efb-launch' ).waitFor( { state: 'visible' } );
			await runT74( page, { check, label: scenario.label, privacyUrl: PRIVACY_URL } );
			check( providerCalls.length > 0 && providerCalls.every( ( calls ) => 1 === calls ), scenario.label + ': every scripted reply came from one in-process provider call' );
			check( 0 === pageErrors.length, scenario.label + ': no uncaught page errors' + ( pageErrors.length ? ' (' + pageErrors.join( ' | ' ).slice( 0, 300 ) + ')' : '' ) );
			check( 0 === consoleErrors.length, scenario.label + ': no console errors' + ( consoleErrors.length ? ' (' + consoleErrors.join( ' | ' ).slice( 0, 300 ) + ')' : '' ) );
			await context.close();
		}
	} finally {
		await browser.close();
	}
	console.log( failures.length ? 'EFB_T74_OFFLINE FAIL ' + failures.length : 'EFB_T74_OFFLINE PASS' );
	process.exitCode = failures.length ? 1 : 0;
} )().catch( ( error ) => { console.error( error.stack || error.message ); process.exitCode = 1; } );
