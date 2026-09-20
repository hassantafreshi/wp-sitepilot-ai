/*
 * Local XAMPP-only admin IA browser matrix (T56, D-23): runs
 * admin_ia_browser_check.cjs once per locale with a temporary administrator.
 * Read-only check, so no fixture; the QA account is always deleted in finally.
 * Credentials never reach stdout.
 */
'use strict';
const { execFileSync, spawnSync } = require( 'node:child_process' );
const path = require( 'node:path' );
const php = process.env.AISAAUTOPILOT_PHP || 'C:\\xampp\\php\\php.exe';
const screenshotRoot = process.env.AISAAUTOPILOT_SCREENSHOT_DIR || path.resolve( __dirname, '../../../../test-artifacts/admin-ia' );
const userHelper = path.join( __dirname, 'local_browser_user.php' );
function user( mode, env ) {
	return execFileSync( php, [ userHelper, mode ], { env, encoding: 'utf8', windowsHide: true } ).trim();
}
// Every admin matrix shares one QA account record. If another job already holds it, running now
// would re-set its locale and delete it under that job (T56 collision, 2026-09-18), so refuse to start.
const wpRoot = ( process.env.AISAAUTOPILOT_WP_ROOT || 'C:\\xampp\\htdocs\\wp' ).replace( /\\/g, '/' );
const holder = execFileSync( php, [ '-r', "require '" + wpRoot + "/wp-load.php'; echo get_option( 'aisaautopilot_local_browser_user' ) ? 'busy' : 'free';" ], { encoding: 'utf8', windowsHide: true } ).trim();
if ( 'free' !== holder.split( /\r?\n/ ).pop() ) {
	console.log( 'ABORT another browser job holds the shared QA account (aisaautopilot_local_browser_user); run this matrix when it has finished.' );
	process.exit( 2 );
}
let failed = false;
try {
	for ( const locale of ( process.env.AISAAUTOPILOT_ADMIN_IA_LOCALES || 'en_US,fa_IR' ).split( ',' ) ) {
		const env = { ...process.env, AISAAUTOPILOT_BROWSER_LOCALE: locale };
		const account = JSON.parse( user( 'create', env ) );
		console.log( 'ADMIN_IA_MATRIX ' + locale );
		const result = spawnSync( process.execPath, [ path.join( __dirname, 'admin_ia_browser_check.cjs' ) ], {
			windowsHide: true,
			stdio: 'inherit',
			env: { ...env,
				AISAAUTOPILOT_BROWSER_USER: account.username,
				AISAAUTOPILOT_BROWSER_PASSWORD: account.password,
				AISAAUTOPILOT_BROWSER_EXPECT_RTL: locale === 'fa_IR' ? '1' : '0',
				AISAAUTOPILOT_SCREENSHOT_DIR: path.join( screenshotRoot, locale ),
			},
		} );
		if ( result.status !== 0 || result.error ) {
			failed = true;
		}
	}
} finally {
	console.log( user( 'delete', process.env ) );
}
console.log( 'MATRIX RESULT ' + ( failed ? 'FAIL' : 'PASS' ) );
process.exitCode = failed ? 1 : 0;
