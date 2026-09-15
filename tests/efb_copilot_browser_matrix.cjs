/* Local XAMPP-only EFB Copilot browser matrix. Credentials never reach stdout. */
'use strict';
const { execFileSync, spawnSync } = require( 'node:child_process' );
const path = require( 'node:path' );
const php = process.env.AISAAUTOPILOT_PHP || 'C:\\xampp\\php\\php.exe';
const screenshotRoot = process.env.AISAAUTOPILOT_SCREENSHOT_DIR || path.resolve( __dirname, '../../../../test-artifacts/efb-copilot' );
const userHelper = path.join( __dirname, 'local_browser_user.php' );
const fixtureHelper = path.join( __dirname, 'local_efb_copilot_browser_fixture.php' );
function phpHelper( file, mode, env ) {
	return execFileSync( php, [ file, mode ], { env, encoding: 'utf8', windowsHide: true } ).trim();
}
let failed = false;
try {
	for ( const locale of ( process.env.AISAAUTOPILOT_EFB_LOCALES || 'en_US,fa_IR' ).split( ',' ) ) {
		const env = { ...process.env, AISAAUTOPILOT_BROWSER_LOCALE: locale };
		const account = JSON.parse( phpHelper( userHelper, 'create', env ) );
		const fixture = JSON.parse( phpHelper( fixtureHelper, 'prepare', env ) );
		console.log( 'EFB_COPILOT_MATRIX ' + locale );
		const result = spawnSync( process.execPath, [ path.join( __dirname, 'efb_copilot_browser_check.cjs' ) ], {
			windowsHide: true,
			stdio: 'inherit',
			env: {
				...env,
				AISAAUTOPILOT_BROWSER_USER: account.username,
				AISAAUTOPILOT_BROWSER_PASSWORD: account.password,
				AISAAUTOPILOT_BROWSER_EFB_URL: fixture.edit_url,
				AISAAUTOPILOT_BROWSER_EXPECT_RTL: locale === 'fa_IR' ? '1' : '0',
				AISAAUTOPILOT_SCREENSHOT_DIR: path.join( screenshotRoot, locale ),
			},
		} );
		phpHelper( fixtureHelper, 'restore', env );
		if ( result.status !== 0 || result.error ) { failed = true; break; }
	}
} finally {
	try { console.log( phpHelper( fixtureHelper, 'restore', process.env ) ); } catch ( error ) { console.error( 'Fixture cleanup failed.' ); failed = true; }
	try { console.log( phpHelper( userHelper, 'delete', process.env ) ); } catch ( error ) { console.error( 'User cleanup failed.' ); failed = true; }
}
process.exitCode = failed ? 1 : 0;
