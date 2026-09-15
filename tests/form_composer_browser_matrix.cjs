/* Local XAMPP-only Form Composer browser matrix. Credentials never reach stdout. */
'use strict';
const { execFileSync, spawnSync } = require( 'node:child_process' );
const path = require( 'node:path' );
const php = process.env.AISAAUTOPILOT_PHP || 'C:\\xampp\\php\\php.exe';
const screenshotRoot = process.env.AISAAUTOPILOT_SCREENSHOT_DIR || path.resolve( __dirname, '../../../../test-artifacts/form-composer' );
const userHelper = path.join( __dirname, 'local_browser_user.php' );
function user( mode, env ) {
	return execFileSync( php, [ userHelper, mode ], { env, encoding: 'utf8', windowsHide: true } ).trim();
}
let failed = false;
try {
	for ( const locale of ( process.env.AISAAUTOPILOT_FORM_LOCALES || 'en_US,fa_IR' ).split( ',' ) ) {
		const env = { ...process.env, AISAAUTOPILOT_BROWSER_LOCALE: locale };
		const account = JSON.parse( user( 'create', env ) );
		console.log( 'FORM_COMPOSER_MATRIX ' + locale );
		const result = spawnSync( process.execPath, [ path.join( __dirname, 'browser_release_check.cjs' ) ], {
			windowsHide: true,
			stdio: 'inherit',
			env: { ...env,
				AISAAUTOPILOT_BROWSER_USER: account.username,
				AISAAUTOPILOT_BROWSER_PASSWORD: account.password,
				AISAAUTOPILOT_BROWSER_SLUGS: 'aisaautopilot-form-builder',
				AISAAUTOPILOT_BROWSER_FORM_COMPOSER_ONLY: '1',
				AISAAUTOPILOT_BROWSER_SKIP_SPECIALIST: '1',
				AISAAUTOPILOT_BROWSER_EXPECT_RTL: locale === 'fa_IR' ? '1' : '0',
				AISAAUTOPILOT_SCREENSHOT_DIR: path.join( screenshotRoot, locale ),
			},
		} );
		if ( result.status !== 0 || result.error ) {
			failed = true;
			break;
		}
	}
} finally {
	console.log( user( 'delete', process.env ) );
}
process.exitCode = failed ? 1 : 0;
