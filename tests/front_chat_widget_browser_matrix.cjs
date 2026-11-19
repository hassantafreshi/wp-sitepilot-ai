/* Local XAMPP only. Visitor chat widget browser matrix.

   For each locale (AISAAUTOPILOT_FRONT_WIDGET_LOCALES, default en_US,fa_IR) and each scenario
   (AISAAUTOPILOT_FRONT_WIDGET_SCENARIOS, default main,auto,quiet) it prepares
   local_front_chat_widget_browser_fixture.php, runs front_chat_widget_browser_check.cjs and
   restores the fixture, and restores once more in finally. The widget is what a logged-out
   visitor sees, so no QA account is created. Exit 1 on any failure. */
'use strict';
require( './test-env.cjs' ); // AISAAUTOPILOT_* defaults: the local test copy, else the owner's XAMPP
const { execFileSync, spawnSync } = require( 'node:child_process' );
const path = require( 'node:path' );
const php = process.env.AISAAUTOPILOT_PHP || 'C:\\xampp\\php\\php.exe';
const screenshotRoot = process.env.AISAAUTOPILOT_SCREENSHOT_DIR || path.resolve( __dirname, '../../../../test-artifacts/front-chat-widget/matrix' );
const fixture = path.join( __dirname, 'local_front_chat_widget_browser_fixture.php' );
const locales = ( process.env.AISAAUTOPILOT_FRONT_WIDGET_LOCALES || 'en_US,fa_IR' ).split( ',' ).map( ( v ) => v.trim() ).filter( Boolean );
const scenarios = ( process.env.AISAAUTOPILOT_FRONT_WIDGET_SCENARIOS || 'main,auto,quiet' ).split( ',' ).map( ( v ) => v.trim() ).filter( Boolean );

function helper( mode, env = process.env ) {
	return execFileSync( php, [ fixture, mode ], { env, encoding: 'utf8', windowsHide: true } ).trim();
}

let failed = false;
try {
	for ( const locale of locales ) {
		for ( const scenario of scenarios ) {
			const env = { ...process.env, AISAAUTOPILOT_BROWSER_LOCALE: locale, AISAAUTOPILOT_FRONT_WIDGET_SCENARIO: scenario };
			try {
				const prepared = JSON.parse( helper( 'prepare', env ) );
				console.log( `MATRIX ${ locale } ${ scenario } handoff=${ prepared.handoff } voice=${ prepared.voice_enabled } auto=${ prepared.voice_auto } suggestions=${ prepared.suggestions }` );
				const result = spawnSync( process.execPath, [ path.join( __dirname, 'front_chat_widget_browser_check.cjs' ) ], {
					windowsHide: true,
					stdio: 'inherit',
					env: {
						...env,
						AISAAUTOPILOT_FRONT_WIDGET_SUGGESTIONS: String( prepared.suggestions ),
						AISAAUTOPILOT_SCREENSHOT_DIR: path.join( screenshotRoot, locale ),
					},
				} );
				if ( result.status !== 0 || result.error ) { failed = true; }
			} catch ( error ) {
				failed = true;
				console.log( `MATRIX ERROR ${ locale } ${ scenario } ${ error && error.message ? error.message.split( '\n' )[ 0 ] : error }` );
			} finally {
				console.log( helper( 'restore', env ) );
			}
		}
	}
} finally {
	console.log( helper( 'restore' ) );
}
console.log( 'MATRIX RESULT ' + ( failed ? 'FAIL' : 'PASS' ) );
process.exitCode = failed ? 1 : 0;
