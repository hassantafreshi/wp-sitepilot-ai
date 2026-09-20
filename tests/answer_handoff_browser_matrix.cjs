/* Local XAMPP only. Credentials stay in subprocess memory, never stdout. */
'use strict';
const { execFileSync, spawnSync } = require( 'node:child_process' );
const path = require( 'node:path' );
const php = process.env.AISAAUTOPILOT_PHP || 'C:\\xampp\\php\\php.exe';
const screenshotRoot = process.env.AISAAUTOPILOT_SCREENSHOT_DIR || path.resolve( __dirname, '../../../../test-artifacts/answer-handoff' );
// The Human handoff check saves a recipient and the visitor details through the UI, so the whole settings option is snapshotted and restored.
const fixture = 'local_answer_conversations_browser_fixture.php';
function helper( name, mode, env = process.env ) {
	return execFileSync( php, [ path.join( __dirname, name ), mode ], { env, encoding: 'utf8', windowsHide: true } ).trim();
}
let failed = false;
try {
	for ( const locale of ( process.env.AISAAUTOPILOT_ANSWER_HANDOFF_LOCALES || 'en_US,fa_IR' ).split( ',' ) ) {
		const env = { ...process.env, AISAAUTOPILOT_BROWSER_LOCALE: locale };
		const account = JSON.parse( helper( 'local_browser_user.php', 'create', env ) );
		try {
			helper( fixture, 'prepare', env );
			console.log( 'MATRIX ' + locale );
			const result = spawnSync( process.execPath, [ path.join( __dirname, 'browser_release_check.cjs' ) ], {
				windowsHide: true, stdio: 'inherit', env: { ...env,
					AISAAUTOPILOT_BROWSER_USER: account.username,
					AISAAUTOPILOT_BROWSER_PASSWORD: account.password,
					AISAAUTOPILOT_BROWSER_SLUGS: 'aisaautopilot-conversations',
					AISAAUTOPILOT_BROWSER_ANSWER_DESK: 'handoff',
					AISAAUTOPILOT_BROWSER_SKIP_SPECIALIST: '1',
					AISAAUTOPILOT_BROWSER_EXPECT_RTL: locale === 'fa_IR' ? '1' : '0',
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
