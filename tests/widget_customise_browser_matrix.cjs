/* Local XAMPP only. Credentials stay in subprocess memory, never stdout. */
'use strict';
require( './test-env.cjs' ); // AISAAUTOPILOT_* defaults: the local test copy, else the owner's XAMPP
const { execFileSync, spawnSync } = require( 'node:child_process' );
const path = require( 'node:path' );
const php = process.env.AISAAUTOPILOT_PHP || 'C:\\xampp\\php\\php.exe';
const screenshotRoot = process.env.AISAAUTOPILOT_SCREENSHOT_DIR || path.resolve( __dirname, '../../../../test-artifacts/widget-customise' );
const fixture = 'local_widget_customise_browser_fixture.php';
function helper( name, mode, env = process.env ) {
	return execFileSync( php, [ path.join( __dirname, name ), mode ], { env, encoding: 'utf8', windowsHide: true } ).trim();
}
let failed = false;
try {
	for ( const locale of ( process.env.AISAAUTOPILOT_WIDGET_CUSTOMISE_LOCALES || 'en_US,fa_IR' ).split( ',' ) ) {
		const env = { ...process.env, AISAAUTOPILOT_BROWSER_LOCALE: locale };
		const account = JSON.parse( helper( 'local_browser_user.php', 'create', env ) );
		try {
			helper( fixture, 'prepare', env );
			console.log( 'MATRIX ' + locale );
			const result = spawnSync( process.execPath, [ path.join( __dirname, 'browser_release_check.cjs' ) ], {
				windowsHide: true, stdio: 'inherit', env: { ...env,
					AISAAUTOPILOT_BROWSER_USER: account.username,
					AISAAUTOPILOT_BROWSER_PASSWORD: account.password,
					AISAAUTOPILOT_BROWSER_SLUGS: 'aisaautopilot-widget',
					AISAAUTOPILOT_BROWSER_SKIP_SPECIALIST: '1',
					AISAAUTOPILOT_BROWSER_EXPECT_RTL: locale === 'fa_IR' ? '1' : '0',
					AISAAUTOPILOT_BROWSER_WIDGET_CUSTOMISE_FIXTURE: '1',
					AISAAUTOPILOT_SCREENSHOT_DIR: path.join( screenshotRoot, locale ),
				},
			} );
			if ( result.status !== 0 || result.error ) { failed = true; }
		} finally {
			console.log( helper( fixture, 'restore', env ) );
		}
		if ( failed ) { break; }
	}
} finally {
	try { console.log( helper( fixture, 'restore' ) ); }
	finally { console.log( helper( 'local_browser_user.php', 'delete' ) ); }
}
process.exitCode = failed ? 1 : 0;
