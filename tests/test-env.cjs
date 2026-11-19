/**
 * Not a test. Fills in the AISAAUTOPILOT_* settings every local browser script (and the PHP and Node processes it
 * starts) reads, so the same script runs on the owner's XAMPP machine and on any other box. Node twin of
 * tests/test-env.php. A variable that is already set is never changed.
 *
 *   AISAAUTOPILOT_WP_ROOT          the local test copy (LOCAL_SITE below) when it exists; else each script's own
 *                                  default, the owner's C:\xampp\htdocs\wp.
 *   AISAAUTOPILOT_BASE_URL         http://127.0.0.1:8090 with the local copy; else each script's own default.
 *   AISAAUTOPILOT_PHP              `php` outside Windows; on Windows each script's own C:\xampp\php\php.exe.
 *   AISAAUTOPILOT_PLAYWRIGHT_CORE  playwright-core as Node finds it, or the one inside the installed playwright.
 *   AISAAUTOPILOT_CHROME           outside Windows: Playwright's own Chromium when it is installed for that
 *                                  playwright-core, else /opt/pw-browsers/chromium; on Windows the owner's Chrome.
 */
'use strict';

const fs = require( 'node:fs' );
const path = require( 'node:path' );

const LOCAL_SITE = '/tmp/claude-0/-home-user/6470513e-3182-5834-893e-38f1388be77b/scratchpad/wpsite-sp/site';
const LOCAL_URL = 'http://127.0.0.1:8090';
const env = process.env;
const windows = 'win32' === process.platform;

if ( ! env.AISAAUTOPILOT_WP_ROOT && fs.existsSync( path.join( LOCAL_SITE, 'wp-load.php' ) ) ) {
	env.AISAAUTOPILOT_WP_ROOT = LOCAL_SITE;
	if ( ! env.AISAAUTOPILOT_BASE_URL ) {
		env.AISAAUTOPILOT_BASE_URL = LOCAL_URL;
	}
}
if ( ! env.AISAAUTOPILOT_PHP && ! windows ) {
	env.AISAAUTOPILOT_PHP = 'php';
}

function resolveCore() {
	try {
		return require.resolve( 'playwright-core' );
	} catch ( e ) {
		// playwright-core is a dependency of playwright: look for it next to (or inside) the installed playwright.
		try {
			const pw = path.dirname( require.resolve( 'playwright/package.json' ) );
			for ( const dir of [ path.join( pw, 'node_modules', 'playwright-core' ), path.join( pw, '..', 'playwright-core' ) ] ) {
				if ( fs.existsSync( path.join( dir, 'package.json' ) ) ) {
					return dir;
				}
			}
		} catch ( e2 ) {
			// Neither is installed: the scripts say so themselves.
		}
	}
	return '';
}
if ( ! env.AISAAUTOPILOT_PLAYWRIGHT_CORE ) {
	const core = resolveCore();
	if ( core ) {
		env.AISAAUTOPILOT_PLAYWRIGHT_CORE = core;
	}
}
if ( ! env.AISAAUTOPILOT_CHROME && ! windows ) {
	let exe = '';
	try {
		const own = require( env.AISAAUTOPILOT_PLAYWRIGHT_CORE || 'playwright-core' ).chromium.executablePath();
		exe = own && fs.existsSync( own ) ? own : '';
	} catch ( e ) {
		exe = '';
	}
	if ( ! exe && fs.existsSync( '/opt/pw-browsers/chromium' ) ) {
		exe = '/opt/pw-browsers/chromium';
	}
	if ( exe ) {
		env.AISAAUTOPILOT_CHROME = exe;
	}
}

module.exports = { LOCAL_SITE, LOCAL_URL };
