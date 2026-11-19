/*
 * Local XAMPP only. Release browser smoke (Q4.1 / T15): every admin route and
 * alias of `browser_release_check.cjs` in each locale, with its default route
 * list and default surface checks.
 *
 * Two passes per locale, one browser job at a time:
 *
 * 1. Every route except Activity Log. Several default surface checks save
 *    through the real UI (Answer Desk Widget and Human handoff, the Store
 *    Copilot connector toggle), so this pass is wrapped in the fixtures that
 *    snapshot what they change. Both snapshot the whole settings option, so
 *    they are restored in reverse order (LIFO): restoring the last one first
 *    hands the first one back its own untouched snapshot.
 * 2. Activity Log alone, inside its own fixture. Its check expects exactly the
 *    fixture's four rows, while the Answer Desk fixture publishes a post whose
 *    deferred Site Knowledge index writes a real log row once WP-Cron runs
 *    during pass 1 (seen in the first T15 run). Separating the passes keeps
 *    that assertion exact instead of loosening it.
 *
 * Credentials stay in subprocess memory and never reach stdout.
 */
'use strict';
require( './test-env.cjs' ); // AISAAUTOPILOT_* defaults: the local test copy, else the owner's XAMPP
const { execFileSync, spawnSync } = require( 'node:child_process' );
const path = require( 'node:path' );
const php = process.env.AISAAUTOPILOT_PHP || 'C:\\xampp\\php\\php.exe';
const screenshotRoot = process.env.AISAAUTOPILOT_SCREENSHOT_DIR || path.resolve( __dirname, '../../../../test-artifacts/release-smoke' );
const passOneFixtures = [
	'local_store_copilot_browser_fixture.php',
	'local_answer_conversations_browser_fixture.php',
];
const activityFixture = 'local_activity_log_browser_fixture.php';
function helper( name, mode, env = process.env ) {
	return execFileSync( php, [ path.join( __dirname, name ), mode ], { env, encoding: 'utf8', windowsHide: true } ).trim();
}
function restore( names, env ) {
	let firstError = null;
	for ( const name of [ ...names ].reverse() ) {
		try {
			console.log( helper( name, 'restore', env ) );
		} catch ( error ) {
			console.log( 'RESTORE_FAILED ' + name + ' ' + error.message );
			firstError = firstError || error;
		}
	}
	if ( firstError ) {
		throw firstError;
	}
}
function runCheck( env, account, locale, extra ) {
	const result = spawnSync( process.execPath, [ path.join( __dirname, 'browser_release_check.cjs' ) ], {
		windowsHide: true,
		stdio: 'inherit',
		env: { ...env,
			AISAAUTOPILOT_BROWSER_USER: account.username,
			AISAAUTOPILOT_BROWSER_PASSWORD: account.password,
			AISAAUTOPILOT_BROWSER_CAPTURE: '1',
			AISAAUTOPILOT_BROWSER_EXPECT_RTL: locale === 'fa_IR' ? '1' : '0',
			AISAAUTOPILOT_SCREENSHOT_DIR: path.join( screenshotRoot, locale ),
			...extra,
		},
	} );
	return result.status === 0 && !result.error;
}
let failed = false;
try {
	for ( const locale of ( process.env.AISAAUTOPILOT_RELEASE_SMOKE_LOCALES || 'en_US,fa_IR' ).split( ',' ) ) {
		const env = { ...process.env, AISAAUTOPILOT_BROWSER_LOCALE: locale };
		const account = JSON.parse( helper( 'local_browser_user.php', 'create', env ) );
		try {
			for ( const name of passOneFixtures ) {
				helper( name, 'prepare', env );
			}
			console.log( 'RELEASE_SMOKE_MATRIX ' + locale + ' routes' );
			if ( !runCheck( env, account, locale, { AISAAUTOPILOT_BROWSER_EXCLUDE_SLUGS: 'aisaautopilot-logs' } ) ) {
				failed = true;
			}
		} finally {
			restore( passOneFixtures, env );
		}
		try {
			const activity = JSON.parse( helper( activityFixture, 'prepare', env ) );
			console.log( 'RELEASE_SMOKE_MATRIX ' + locale + ' activity-log' );
			if ( !runCheck( env, account, locale, {
				AISAAUTOPILOT_BROWSER_SLUGS: 'aisaautopilot-logs',
				AISAAUTOPILOT_BROWSER_SKIP_SPECIALIST: '1',
				AISAAUTOPILOT_BROWSER_ACTIVITY_LOG_DRAFT: activity.draft_message || '',
				AISAAUTOPILOT_BROWSER_ACTIVITY_LOG_ERROR: activity.error_message || '',
			} ) ) {
				failed = true;
			}
		} finally {
			restore( [ activityFixture ], env );
		}
		if ( failed ) {
			break;
		}
	}
} finally {
	try {
		restore( [ ...passOneFixtures, activityFixture ], process.env );
	} finally {
		console.log( helper( 'local_browser_user.php', 'delete' ) );
	}
}
process.exitCode = failed ? 1 : 0;
