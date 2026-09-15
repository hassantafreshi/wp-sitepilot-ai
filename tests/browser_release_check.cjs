/*
 * Headless visual/interaction smoke test for the local XAMPP release
 * candidate. Requires Playwright Core and Chrome but no AI provider.
 */
'use strict';

const fs = require( 'node:fs' );
const path = require( 'node:path' );

function loadPlaywright() {
	if ( process.env.AISAAUTOPILOT_PLAYWRIGHT_CORE ) {
		return require( process.env.AISAAUTOPILOT_PLAYWRIGHT_CORE );
	}
	try {
		return require( 'playwright-core' );
	} catch ( error ) {
		throw new Error( 'Playwright Core is required. Install it locally with `npm.cmd install --no-save --package-lock=false playwright-core`, or set AISAAUTOPILOT_PLAYWRIGHT_CORE.' );
	}
}

const { chromium } = loadPlaywright();

const baseUrl = ( process.env.AISAAUTOPILOT_BASE_URL || 'http://127.0.0.1/wp' ).replace( /\/$/, '' );
const username = process.env.AISAAUTOPILOT_BROWSER_USER || '';
const password = process.env.AISAAUTOPILOT_BROWSER_PASSWORD || '';
const screenshotDir = process.env.AISAAUTOPILOT_SCREENSHOT_DIR || process.env.TEMP;
const chromePath = process.env.AISAAUTOPILOT_CHROME || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const failures = [];
const allCheckedPages = [
	'aisaautopilot-setup', 'aisaautopilot-home', 'aisaautopilot-form-builder',
	'aisaautopilot-forms', 'aisaautopilot-efb', 'aisaautopilot-chat',
	'aisaautopilot-kb', 'aisaautopilot-usage', 'aisaautopilot-widget',
	'aisaautopilot-conversations', 'aisaautopilot-handoffs',
	'aisaautopilot-wc-dashboard', 'aisaautopilot-woocommerce',
	'aisaautopilot-connections', 'aisaautopilot-settings', 'aisaautopilot-logs',
	'aisaautopilot-integrations', 'aisaautopilot-history',
];
const requestedSlugs = ( process.env.AISAAUTOPILOT_BROWSER_SLUGS || '' ).split( ',' ).map( ( slug ) => slug.trim() ).filter( Boolean );
const checkedPages = requestedSlugs.length ? requestedSlugs : allCheckedPages;
const runSpecialistChecks = process.env.AISAAUTOPILOT_BROWSER_SKIP_SPECIALIST !== '1';
const skipScreenshots = process.env.AISAAUTOPILOT_BROWSER_SKIP_SCREENSHOTS === '1';
const quickKbChecks = process.env.AISAAUTOPILOT_BROWSER_QUICK_KB === '1';
const answerDeskChecks = ( process.env.AISAAUTOPILOT_BROWSER_ANSWER_DESK || 'all' ).split( ',' ).map( ( value ) => value.trim() ).filter( Boolean );
const expectNativeRtl = process.env.AISAAUTOPILOT_BROWSER_EXPECT_RTL === '1';
const contentHistoryFixtureTitle = process.env.AISAAUTOPILOT_BROWSER_CONTENT_HISTORY_TITLE || '';
const contentHistoryFixtureUnmarkedTitle = process.env.AISAAUTOPILOT_BROWSER_CONTENT_HISTORY_UNMARKED_TITLE || '';
const usageFixtureEnabled = process.env.AISAAUTOPILOT_BROWSER_USAGE_FIXTURE === '1';
const activityLogFixtureDraft = process.env.AISAAUTOPILOT_BROWSER_ACTIVITY_LOG_DRAFT || '';
const activityLogFixtureError = process.env.AISAAUTOPILOT_BROWSER_ACTIVITY_LOG_ERROR || '';
const activityLogExpectEmpty = process.env.AISAAUTOPILOT_BROWSER_ACTIVITY_LOG_EMPTY === '1';

function check( condition, message ) {
	if ( condition ) {
		console.log( 'PASS ' + message );
	} else {
		console.log( 'FAIL ' + message );
		failures.push( message );
	}
}

async function pageHealthy( page, slug ) {
	console.log( 'CHECK ' + slug );
	const url = baseUrl + '/wp-admin/admin.php?page=' + encodeURIComponent( slug );
	let response;
	let lastError;
	for ( let attempt = 1; attempt <= 2; attempt++ ) {
		try {
			response = await page.goto( url, { waitUntil: 'domcontentloaded', timeout: 45000 } );
			break;
		} catch ( error ) {
			lastError = error;
			if ( attempt < 2 ) {
				console.log( 'RETRY ' + slug + ' after transient local-server timeout' );
				await page.waitForTimeout( 1000 );
			}
		}
	}
	if ( !response ) {
		throw lastError;
	}
	const responseOk = !!response && response.status() === 200;
	check( responseOk, slug + ' loads with HTTP 200' );
	if ( !responseOk ) {
		console.log( 'HTTP_STATUS ' + slug + ' ' + ( response ? response.status() : 'no-response' ) );
	}
	await page.locator( '.sp-app' ).first().waitFor( { state: 'attached', timeout: 10000 } ).catch( () => {} );
	const hasCritical = await page.locator( 'body' ).innerText().then( ( text ) => text.includes( 'There has been a critical error' ) );
	check( !hasCritical, slug + ' has no WordPress fatal-error screen' );
	const hasShell = await page.locator( '.sp-app' ).count() >= 1;
	check( hasShell, slug + ' renders the shared AutoPilot UI shell' );
	if ( !hasShell ) {
		const bodyText = await page.locator( 'body' ).innerText();
		console.log( 'SHELL_MISSING ' + slug + ' title=' + await page.title() + ' body=' + bodyText.replace( /[\r\n]+/g, ' ' ).slice( 0, 500 ) );
	}
	if ( process.env.AISAAUTOPILOT_BROWSER_CAPTURE === '1' ) {
		await page.screenshot( { path: path.join( screenshotDir, 'admin-' + slug + '-desktop.png' ), fullPage: true } );
	}
}

async function overviewHealthy( page ) {
	console.log( 'CHECK Overview desktop, RTL and mobile layout' );
	const overviewUrl = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-home';
	await page.setViewportSize( { width: 1440, height: 960 } );
	await page.goto( overviewUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	await page.locator( '[data-sp-overview]' ).waitFor( { state: 'attached', timeout: 10000 } );
	check( await page.locator( '[data-sp-overview-stat]' ).count() === 4, 'Overview renders the four mockup metric cards' );
	check( await page.locator( '[data-sp-overview-running]' ).count() === 6, 'Overview renders all six running-now integrations' );
	const desktopOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( !desktopOverflow, 'Overview has no desktop horizontal overflow' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-home-desktop.png' ), fullPage: true } );

	const originalDir = await page.locator( 'html' ).getAttribute( 'dir' );
	await page.locator( 'html' ).evaluate( ( html ) => html.setAttribute( 'dir', 'rtl' ) );
	await page.waitForTimeout( 120 );
	const rtlDirection = await page.locator( '[data-sp-overview]' ).evaluate( ( element ) => getComputedStyle( element ).direction );
	const rtlOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( rtlDirection === 'rtl' && !rtlOverflow, 'Overview has no horizontal overflow when rendered RTL' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-home-rtl.png' ), fullPage: true } );
	await page.locator( 'html' ).evaluate( ( html, dir ) => {
		if ( dir ) {
			html.setAttribute( 'dir', dir );
		} else {
			html.removeAttribute( 'dir' );
		}
	}, originalDir );

	await page.setViewportSize( { width: 390, height: 844 } );
	await page.goto( overviewUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	await page.locator( '[data-sp-overview]' ).waitFor( { state: 'attached', timeout: 10000 } );
	const mobileOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( !mobileOverflow, 'Overview has no horizontal overflow at 390px width' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-home-mobile.png' ), fullPage: true } );
}

async function setupHealthy( page ) {
	console.log( 'CHECK Setup wizard functionality, RTL and mobile layout' );
	const setupUrl = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-setup';
	await page.setViewportSize( { width: 1440, height: 960 } );
	await page.goto( setupUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	await page.locator( '[data-sp-setup-wizard]' ).waitFor( { state: 'attached', timeout: 10000 } );
	check( await page.locator( '[data-sp-setup-progress-step]' ).count() === 4, 'Setup renders the four mockup progress steps' );
	check( await page.locator( '[data-sp-setup-step]' ).count() === 4, 'Setup renders all four wizard panels without duplicate screens' );
	check( await page.locator( '[data-sp-setup-step="1"]' ).isVisible(), 'Setup starts on the connection step' );

	await page.locator( '[data-sp-setup-next]' ).click();
	check( await page.locator( '[data-sp-setup-step="2"]' ).isVisible(), 'Setup advances from connection to AI-service selection' );
	const providerSearch = page.locator( '#sp-setup-provider-search' );
	await providerSearch.fill( 'not-a-real-provider' );
	check( await page.locator( '[data-sp-provider-empty]' ).isVisible(), 'Setup provider search shows its no-results state' );
	await providerSearch.fill( '' );
	await page.locator( '[data-sp-provider-filter="all"]' ).click();
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-setup-services-desktop.png' ), fullPage: true } );

	await page.locator( '[data-sp-provider-card][data-provider="custom"]' ).click();
	await page.locator( '[data-sp-setup-next]' ).click();
	check( await page.locator( '[data-sp-setup-step="3"]' ).isVisible(), 'Setup advances to key and model after choosing a service' );
	await page.locator( '#sp-test' ).click();
	await page.locator( '#sp-setup-testbox.sp-testbox--err' ).waitFor( { state: 'attached', timeout: 10000 } );
	check( await page.locator( '[data-sp-setup-step="3"]' ).isVisible(), 'invalid incomplete custom configuration stays on the required connection-test step' );
	check( !( await page.locator( '[data-sp-setup-save]' ).isVisible() ), 'Setup never exposes Save before a successful connection test' );
	const desktopOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( !desktopOverflow, 'Setup has no desktop horizontal overflow' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-setup-desktop.png' ), fullPage: true } );

	// A success path needs a real provider credential, which release QA must not
	// invent. Expose only the already-rendered Step 4 panel to inspect its visual
	// layout; its real save contract is covered by WordPress integration tests.
	await page.evaluate( () => {
		document.querySelectorAll( '[data-sp-setup-step]' ).forEach( ( panel ) => { panel.hidden = panel.getAttribute( 'data-sp-setup-step' ) !== '4'; } );
		const next = document.querySelector( '[data-sp-setup-next]' );
		const save = document.querySelector( '[data-sp-setup-save]' );
		if ( next ) { next.hidden = true; }
		if ( save ) { save.hidden = false; }
		document.querySelectorAll( '[data-sp-setup-progress-step]' ).forEach( ( item ) => {
			item.classList.toggle( 'is-current', item.getAttribute( 'data-sp-setup-progress-step' ) === '4' );
			item.classList.toggle( 'is-done', Number( item.getAttribute( 'data-sp-setup-progress-step' ) ) < 4 );
		} );
	} );
	const integrationOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( !integrationOverflow, 'Setup integrations panel has no desktop horizontal overflow' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-setup-integrations-desktop.png' ), fullPage: true } );

	const originalDir = await page.locator( 'html' ).getAttribute( 'dir' );
	await page.locator( 'html' ).evaluate( ( html ) => html.setAttribute( 'dir', 'rtl' ) );
	await page.waitForTimeout( 120 );
	const rtlDirection = await page.locator( '[data-sp-setup-wizard]' ).evaluate( ( element ) => getComputedStyle( element ).direction );
	const rtlOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( rtlDirection === 'rtl' && !rtlOverflow, 'Setup has no horizontal overflow when rendered RTL' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-setup-rtl.png' ), fullPage: true } );
	await page.locator( 'html' ).evaluate( ( html, dir ) => {
		if ( dir ) {
			html.setAttribute( 'dir', dir );
		} else {
			html.removeAttribute( 'dir' );
		}
	}, originalDir );

	await page.setViewportSize( { width: 390, height: 844 } );
	await page.goto( setupUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	await page.locator( '[data-sp-setup-wizard]' ).waitFor( { state: 'attached', timeout: 10000 } );
	const mobileOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( !mobileOverflow, 'Setup has no horizontal overflow at 390px width' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-setup-mobile.png' ), fullPage: true } );
	await page.locator( '[data-sp-setup-next]' ).click();
	await page.locator( '[data-sp-provider-filter="all"]' ).click();
	const servicesMobileOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( !servicesMobileOverflow, 'Setup service picker has no horizontal overflow at 390px width' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-setup-services-mobile.png' ), fullPage: true } );
	await page.evaluate( () => {
		document.querySelectorAll( '[data-sp-setup-step]' ).forEach( ( panel ) => { panel.hidden = panel.getAttribute( 'data-sp-setup-step' ) !== '4'; } );
		const next = document.querySelector( '[data-sp-setup-next]' );
		const save = document.querySelector( '[data-sp-setup-save]' );
		if ( next ) { next.hidden = true; }
		if ( save ) { save.hidden = false; }
	} );
	const integrationsMobileOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( !integrationsMobileOverflow, 'Setup integrations panel has no horizontal overflow at 390px width' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-setup-integrations-mobile.png' ), fullPage: true } );
}

async function integrationsHealthy( page ) {
	console.log( 'CHECK Integrations functionality, RTL and mobile layout' );
	const integrationsUrl = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-integrations';
	await page.setViewportSize( { width: 1440, height: 960 } );
	await page.goto( integrationsUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	await page.locator( '[data-sp-integrations]' ).waitFor( { state: 'attached', timeout: 10000 } );
	check( await page.locator( '[data-sp-integration-group]' ).count() === 4, 'Integrations renders the four mockup groups' );
	check( await page.locator( '[data-sp-integration-card]' ).count() === 6, 'Integrations renders all six mockup capability cards' );
	const cardIds = await page.locator( '[data-sp-integration-card]' ).evaluateAll( ( cards ) => cards.map( ( card ) => card.getAttribute( 'data-sp-integration-card' ) ) );
	check(
		JSON.stringify( cardIds ) === JSON.stringify( [ 'site-knowledge', 'answer-desk', 'form-composer', 'efb-copilot', 'content-studio', 'store-copilot' ] ),
		'Integrations keeps the canonical card order and stable selectors'
	);
	check( await page.locator( '[data-sp-integration-status="always-on"]' ).count() === 1, 'Site Knowledge declares its always-on core state' );
	const desktopOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( !desktopOverflow, 'Integrations has no desktop horizontal overflow' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-integrations-desktop.png' ), fullPage: true } );

	const siteKnowledgeTrigger = page.locator( '[data-sp-integration-open="site-knowledge"]' );
	await siteKnowledgeTrigger.click();
	const siteKnowledgeDialog = page.locator( '[data-sp-integration-dialog="site-knowledge"]' );
	await siteKnowledgeDialog.waitFor( { state: 'visible', timeout: 10000 } );
	check( await siteKnowledgeDialog.evaluate( ( dialog ) => dialog.open ), 'Site Knowledge Configure dialog opens from its card CTA' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-integrations-dialog-desktop.png' ), fullPage: true } );
	await page.keyboard.press( 'Escape' );
	await page.waitForFunction(
		() => {
			const dialog = document.querySelector( '[data-sp-integration-dialog="site-knowledge"]' );
			return !!dialog && !dialog.open;
		},
		undefined,
		{ timeout: 10000 }
	);
	check(
		await siteKnowledgeTrigger.evaluate( ( trigger ) => document.activeElement === trigger ),
		'Integrations dialog closes with Escape and returns focus to its CTA'
	);

	const originalDir = await page.locator( 'html' ).getAttribute( 'dir' );
	await page.locator( 'html' ).evaluate( ( html ) => html.setAttribute( 'dir', 'rtl' ) );
	await page.waitForTimeout( 120 );
	const rtlDirection = await page.locator( '[data-sp-integrations]' ).evaluate( ( element ) => getComputedStyle( element ).direction );
	const rtlOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( rtlDirection === 'rtl' && !rtlOverflow, 'Integrations has no horizontal overflow when rendered RTL' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-integrations-rtl.png' ), fullPage: true } );
	await page.locator( 'html' ).evaluate( ( html, dir ) => {
		if ( dir ) {
			html.setAttribute( 'dir', dir );
		} else {
			html.removeAttribute( 'dir' );
		}
	}, originalDir );

	await page.setViewportSize( { width: 390, height: 844 } );
	await page.goto( integrationsUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	await page.locator( '[data-sp-integrations]' ).waitFor( { state: 'attached', timeout: 10000 } );
	check( await page.locator( '[data-sp-integration-card]' ).count() === 6, 'Integrations keeps all six cards at 390px width' );
	const mobileOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( !mobileOverflow, 'Integrations has no horizontal overflow at 390px width' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-integrations-mobile.png' ), fullPage: true } );
}

async function knowledgeHealthy( page ) {
	console.log( 'CHECK Site Knowledge functionality, RTL and mobile layout' );
	const knowledgeUrl = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-kb';
	await page.setViewportSize( { width: 1440, height: 960 } );
	await page.goto( knowledgeUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	await page.locator( '[data-sp-kb]' ).waitFor( { state: 'attached', timeout: 10000 } );

	const tabIds = await page.locator( '[data-sp-subtab]' ).evaluateAll( ( tabs ) => tabs.map( ( tab ) => tab.getAttribute( 'data-sp-subtab' ) ) );
	check(
		JSON.stringify( tabIds ) === JSON.stringify( [ 'overview', 'collections', 'queue', 'sources' ] ),
		'Site Knowledge renders exactly the four canonical mockup tabs in order'
	);
	const hasEmpty = await page.locator( '[data-sp-kb-empty]' ).count() === 1;
	const hasPopulatedOverview = await page.locator( '[data-sp-kb-coverage]' ).count() === 1 && await page.locator( '[data-sp-kb-sources]' ).count() === 1;
	check( hasEmpty || hasPopulatedOverview, 'Site Knowledge Overview renders its truthful empty or populated mockup state' );
	check( await page.locator( '#sp-kb-rows' ).count() === 0, 'Site Knowledge canonical Overview hides the legacy indexed-entry table' );
	check( await page.locator( '#sp-kb-progress' ).count() === 1, 'Site Knowledge has one session-progress surface on Overview' );
	check(
		await page.locator( '[data-sp-kb-resume]' ).count() === 1
		&& await page.locator( '[data-sp-kb-resume]' ).isHidden(),
		'Site Knowledge exposes a hidden browser-session resume control until a token-cap pause occurs'
	);
	const desktopOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( !desktopOverflow, 'Site Knowledge has no desktop horizontal overflow' );
	if ( !skipScreenshots ) {
		await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-kb-desktop.png' ), fullPage: true } );
	}

	const enabledPicker = page.locator( '[data-sp-kb-open-picker]:not([disabled])' ).first();
	if ( await enabledPicker.count() ) {
		await enabledPicker.focus();
		await enabledPicker.click();
		const picker = page.locator( '#sp-kb-modal' );
		await picker.waitFor( { state: 'visible', timeout: 10000 } );
		check( await picker.isVisible(), 'Site Knowledge source Index action opens its real scope picker' );
		await page.keyboard.press( 'Escape' );
		await picker.waitFor( { state: 'hidden', timeout: 10000 } );
		check(
			await enabledPicker.evaluate( ( trigger ) => document.activeElement === trigger ),
			'Site Knowledge picker closes with Escape and returns focus to its source action'
		);
	} else {
		check(
			await page.locator( '[data-sp-kb-open-picker][disabled]' ).count() > 0 || hasEmpty,
			'Site Knowledge does not offer a misleading Index action before a provider is connected'
		);
	}

	const tabContracts = [
		[ 'collections', '[data-sp-kb-collections]', 'Collections' ],
		[ 'queue', '[data-sp-kb-queue]', 'Indexing Queue' ],
		[ 'sources', '[data-sp-kb-sync-sources]', 'Sync Sources' ],
	];
	let truthfulQueue = false;
	for ( const [ tab, selector, label ] of tabContracts ) {
		await page.goto( knowledgeUrl + '&tab=' + tab, { waitUntil: 'domcontentloaded', timeout: 20000 } );
		await page.locator( selector ).waitFor( { state: 'attached', timeout: 10000 } );
		check( await page.locator( selector ).count() === 1, 'Site Knowledge ' + label + ' renders one canonical panel' );
		check( await page.locator( '#sp-kb-rows' ).count() === 0, 'Site Knowledge ' + label + ' hides the legacy indexed-entry table' );
		check( await page.locator( '#sp-kb-progress' ).count() === 1, 'Site Knowledge ' + label + ' keeps one session-progress surface' );
		if ( tab === 'queue' ) {
			truthfulQueue = await page.locator( selector ).innerText().then( ( text ) => text.includes( 'Browser session only' ) && !text.includes( 'Retry the 3 failed' ) );
		}
	}
	check( truthfulQueue, 'Site Knowledge Queue does not imitate an unsupported persistent retry state' );
	if ( !skipScreenshots ) {
		await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-kb-sources-desktop.png' ), fullPage: true } );
	}

	await page.goto( knowledgeUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	const originalDir = await page.locator( 'html' ).getAttribute( 'dir' );
	await page.locator( 'html' ).evaluate( ( html ) => html.setAttribute( 'dir', 'rtl' ) );
	await page.waitForTimeout( 120 );
	const rtlDirection = await page.locator( '[data-sp-kb]' ).evaluate( ( element ) => getComputedStyle( element ).direction );
	const rtlOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( rtlDirection === 'rtl' && !rtlOverflow, 'Site Knowledge has no horizontal overflow when rendered RTL' );
	if ( !skipScreenshots ) {
		await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-kb-rtl.png' ), fullPage: true } );
	}
	await page.locator( 'html' ).evaluate( ( html, dir ) => {
		if ( dir ) {
			html.setAttribute( 'dir', dir );
		} else {
			html.removeAttribute( 'dir' );
		}
	}, originalDir );

	await page.setViewportSize( { width: 390, height: 844 } );
	await page.goto( knowledgeUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	await page.locator( '[data-sp-kb]' ).waitFor( { state: 'attached', timeout: 10000 } );
	check( await page.locator( '[data-sp-subtab]' ).count() === 4, 'Site Knowledge keeps all four tabs at 390px width' );
	const mobileOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( !mobileOverflow, 'Site Knowledge has no horizontal overflow at 390px width' );
	if ( !skipScreenshots ) {
		await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-kb-mobile.png' ), fullPage: true } );
	}
}

async function knowledgeResumeQuickHealthy( page ) {
	console.log( 'CHECK Site Knowledge cap-resume quick contract, native RTL and mobile layout' );
	const knowledgeUrl = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-kb';
	await page.setViewportSize( { width: 1440, height: 960 } );
	const root = page.locator( '[data-sp-kb]' );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	const tabIds = await page.locator( '[data-sp-subtab]' ).evaluateAll( ( tabs ) => tabs.map( ( tab ) => tab.getAttribute( 'data-sp-subtab' ) ) );
	check(
		JSON.stringify( tabIds ) === JSON.stringify( [ 'overview', 'collections', 'queue', 'sources' ] )
		&& await page.locator( '[data-sp-kb-resume]' ).count() === 1
		&& await page.locator( '[data-sp-kb-resume]' ).isHidden(),
		'Site Knowledge cap-resume control is present, hidden by default, and keeps the four mockup tabs'
	);

	let originalDir = null;
	if ( expectNativeRtl ) {
		check( await page.locator( 'html' ).getAttribute( 'dir' ) === 'rtl', 'Site Knowledge quick check runs in a native RTL WordPress shell' );
	} else {
		originalDir = await page.locator( 'html' ).getAttribute( 'dir' );
		await page.locator( 'html' ).evaluate( ( html ) => html.setAttribute( 'dir', 'rtl' ) );
	}
	const rtlFits = await root.evaluate( ( element ) => {
		const rect = element.getBoundingClientRect();
		const content = document.getElementById( 'wpcontent' );
		const contentRect = content ? content.getBoundingClientRect() : { left: 0, right: window.innerWidth };
		return getComputedStyle( element ).direction === 'rtl'
			&& document.documentElement.scrollWidth <= window.innerWidth + 1
			&& rect.left >= contentRect.left - 1
			&& rect.right <= contentRect.right + 1;
	} );
	check( rtlFits, 'Site Knowledge cap-resume surface fits its RTL WordPress content rectangle' );
	if ( !expectNativeRtl ) {
		await page.locator( 'html' ).evaluate( ( html, dir ) => {
			if ( dir ) {
				html.setAttribute( 'dir', dir );
			} else {
				html.removeAttribute( 'dir' );
			}
		}, originalDir );
	}

	await page.setViewportSize( { width: 390, height: 844 } );
	await page.goto( knowledgeUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	const mobileOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check(
		await page.locator( '[data-sp-kb-resume]' ).count() === 1
		&& await page.locator( '[data-sp-kb-resume]' ).isHidden()
		&& !mobileOverflow,
		'Site Knowledge cap-resume surface stays hidden by default and has no overflow at 390px'
	);
}

async function answerConversationsHealthy( page ) {
	console.log( 'CHECK Answer Desk Conversations desktop, RTL and mobile layout' );
	const answerUrl = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-conversations';
	await page.setViewportSize( { width: 1440, height: 960 } );
	await page.goto( answerUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	const root = page.locator( '[data-sp-answer-desk][data-sp-answer-tab="conversations"]' );
	await root.waitFor( { state: 'attached', timeout: 10000 } );

	const tabIds = await page.locator( '[data-sp-subtab]' ).evaluateAll( ( tabs ) => tabs.map( ( tab ) => tab.getAttribute( 'data-sp-subtab' ) ) );
	check(
		JSON.stringify( tabIds ) === JSON.stringify( [ 'conversations', 'widget', 'handoff', 'voice' ] ),
		'Answer Desk renders exactly the four canonical mockup tabs in order'
	);
	const state = await root.getAttribute( 'data-sp-answer-state' );
	const validState = [ 'widget-hidden', 'logging-off', 'empty', 'ready' ].includes( state );
	check( validState, 'Answer Desk Conversations exposes one truthful runtime state' );
	const hasList = await page.locator( '[data-sp-answer-conversation-list]' ).count() === 1;
	check( state === 'ready' ? hasList : !hasList, 'Answer Desk shows a conversation list only when recorded data exists' );
	check( await page.locator( '.sp-pagebar__action[href*="aisaautopilot-conversations"][href*="tab=widget"]' ).count() === 1, 'Answer Desk header keeps its canonical Widget destination' );
	const fabricatedText = await root.innerText();
	check(
		!fabricatedText.includes( '94% answered without you' )
		&& !fabricatedText.includes( 'AutoPilot is writing a reply' )
		&& !fabricatedText.includes( 'Needs a person' ),
		'Answer Desk Conversations does not imitate unsupported confidence or live states'
	);
	const desktopOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( !desktopOverflow, 'Answer Desk Conversations has no desktop horizontal overflow' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-conversations-desktop.png' ), fullPage: true } );

	const originalDir = await page.locator( 'html' ).getAttribute( 'dir' );
	await page.locator( 'html' ).evaluate( ( html ) => html.setAttribute( 'dir', 'rtl' ) );
	await page.waitForTimeout( 120 );
	const rtlDirection = await root.evaluate( ( element ) => getComputedStyle( element ).direction );
	const rtlOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( rtlDirection === 'rtl' && !rtlOverflow, 'Answer Desk Conversations has no horizontal overflow when rendered RTL' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-conversations-rtl.png' ), fullPage: true } );
	await page.locator( 'html' ).evaluate( ( html, dir ) => {
		if ( dir ) {
			html.setAttribute( 'dir', dir );
		} else {
			html.removeAttribute( 'dir' );
		}
	}, originalDir );

	await page.setViewportSize( { width: 390, height: 844 } );
	await page.goto( answerUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	check( await page.locator( '[data-sp-subtab]' ).count() === 4, 'Answer Desk keeps all four tabs at 390px width' );
	const mobileOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( !mobileOverflow, 'Answer Desk Conversations has no horizontal overflow at 390px width' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-conversations-mobile.png' ), fullPage: true } );
}

async function answerWidgetHealthy( page ) {
	console.log( 'CHECK Answer Desk Widget desktop, persistence, RTL and mobile layout' );
	const widgetUrl = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-conversations&tab=widget';
	await page.setViewportSize( { width: 1440, height: 960 } );
	await page.goto( widgetUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	const root = page.locator( '[data-sp-answer-desk][data-sp-answer-tab="widget"]' );
	await root.waitFor( { state: 'attached', timeout: 10000 } );

	const tabIds = await page.locator( '[data-sp-subtab]' ).evaluateAll( ( tabs ) => tabs.map( ( tab ) => tab.getAttribute( 'data-sp-subtab' ) ) );
	check(
		JSON.stringify( tabIds ) === JSON.stringify( [ 'conversations', 'widget', 'handoff', 'voice' ] ),
		'Answer Desk Widget keeps the four canonical mockup tabs in order'
	);
	check(
		await page.locator( '[data-sp-answer-widget-controls]' ).count() === 1
		&& await page.locator( '[data-sp-widget-preview]' ).count() === 1
		&& await page.locator( '[data-sp-widget-theme-locked] button' ).count() === 0
		&& await page.locator( '[data-sp-widget-sources-state] button' ).count() === 0,
		'Answer Desk Widget exposes only truthful mockup controls and one preview'
	);

	const coral = page.locator( '[data-sp-widget-color="#ff455f"]' );
	check( await coral.count() === 1 && await coral.isEnabled(), 'Answer Desk Widget offers a persistent coral accent swatch when no advanced palette overrides it' );
	const colorSave = page.waitForResponse( ( response ) => response.url().includes( '/widget/admin-settings' ) && response.request().method() === 'POST' && response.ok() );
	await coral.click();
	const colorResponse = await colorSave;
	check(
		!! colorResponse.request().headers()[ 'x-wp-nonce' ],
		'Answer Desk Widget autosave sends the WordPress REST nonce over its real HTTP request'
	);
	await page.locator( '[data-sp-widget-save-status][data-state="saved"]' ).waitFor( { state: 'visible', timeout: 10000 } );
	await page.waitForFunction( () => document.querySelector( '[data-sp-answer-tab="widget"]' ).getAttribute( 'data-sp-widget-color' ) === '#ff455f' );
	check(
		await root.getAttribute( 'data-sp-widget-color' ) === '#ff455f'
		&& await page.locator( '[data-sp-widget-preview]' ).evaluate( ( preview ) => getComputedStyle( preview ).getPropertyValue( '--sp-answer-widget-accent' ).trim() === '#ff455f' ),
		'Answer Desk Widget updates the live preview after the persisted accent change'
	);

	await page.reload( { waitUntil: 'domcontentloaded', timeout: 20000 } );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	check( await root.getAttribute( 'data-sp-widget-color' ) === '#ff455f', 'Answer Desk Widget retains the accent after reload' );

	const enabled = page.locator( 'button[data-sp-widget-enabled]' );
	const hideSave = page.waitForResponse( ( response ) => response.url().includes( '/widget/admin-settings' ) && response.request().method() === 'POST' && response.ok() );
	await enabled.click();
	await hideSave;
	await page.waitForFunction( () => document.querySelector( 'button[data-sp-widget-enabled]' ).getAttribute( 'aria-checked' ) === 'false' );
	check( await enabled.getAttribute( 'aria-checked' ) === 'false', 'Answer Desk Widget autosave can hide the public launcher' );
	const showSave = page.waitForResponse( ( response ) => response.url().includes( '/widget/admin-settings' ) && response.request().method() === 'POST' && response.ok() );
	await enabled.click();
	await showSave;
	await page.waitForFunction( () => document.querySelector( 'button[data-sp-widget-enabled]' ).getAttribute( 'aria-checked' ) === 'true' );
	check( await enabled.getAttribute( 'aria-checked' ) === 'true', 'Answer Desk Widget can restore the public launcher without a full-form reset' );

	const desktopOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( !desktopOverflow, 'Answer Desk Widget has no desktop horizontal overflow' );
	const desktopBounds = await page.locator( '.sp-answer-widget-grid' ).evaluate( ( grid ) => {
		const rect = grid.getBoundingClientRect();
		return { start: rect.left, end: rect.right, viewport: window.innerWidth };
	} );
	check( desktopBounds.start >= 0 && desktopBounds.end <= desktopBounds.viewport, 'Answer Desk Widget grid is fully visible on desktop' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-widget-desktop.png' ), fullPage: true } );

	const originalDir = await page.locator( 'html' ).getAttribute( 'dir' );
	await page.locator( 'html' ).evaluate( ( html ) => html.setAttribute( 'dir', 'rtl' ) );
	await page.waitForTimeout( 120 );
	const rtlDirection = await root.evaluate( ( element ) => getComputedStyle( element ).direction );
	const rtlOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( rtlDirection === 'rtl' && !rtlOverflow, 'Answer Desk Widget has no horizontal overflow when rendered RTL' );
	const rtlBounds = await page.locator( '.sp-answer-widget-grid' ).evaluate( ( grid ) => {
		const rect = grid.getBoundingClientRect();
		return { start: rect.left, end: rect.right, viewport: window.innerWidth };
	} );
	check( rtlBounds.start >= 0 && rtlBounds.end <= rtlBounds.viewport, 'Answer Desk Widget grid remains fully visible when rendered RTL' );
	const rtlTitleBounds = await page.locator( '.sp-pagebar__title' ).evaluate( ( title ) => {
		const range = document.createRange();
		range.selectNodeContents( title );
		const rect = range.getBoundingClientRect();
		return { start: rect.left, end: rect.right, viewport: window.innerWidth };
	} );
	check( rtlTitleBounds.start >= 0 && rtlTitleBounds.end <= rtlTitleBounds.viewport, 'Answer Desk Widget header title remains fully visible when rendered RTL' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-widget-rtl.png' ), fullPage: true } );
	await page.locator( 'html' ).evaluate( ( html, dir ) => {
		if ( dir ) {
			html.setAttribute( 'dir', dir );
		} else {
			html.removeAttribute( 'dir' );
		}
	}, originalDir );

	await page.setViewportSize( { width: 390, height: 844 } );
	await page.goto( widgetUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	check( await page.locator( '[data-sp-subtab]' ).count() === 4, 'Answer Desk Widget keeps all four tabs at 390px width' );
	const mobileOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( !mobileOverflow, 'Answer Desk Widget has no horizontal overflow at 390px width' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-widget-mobile.png' ), fullPage: true } );
}

async function answerHandoffHealthy( page ) {
	console.log( 'CHECK Answer Desk Human handoff desktop, persistence, RTL and mobile layout' );
	const handoffUrl = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-conversations&tab=handoff';
	await page.setViewportSize( { width: 1440, height: 960 } );
	await page.goto( handoffUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	const root = page.locator( '[data-sp-answer-desk][data-sp-answer-tab="handoff"]' );
	await root.waitFor( { state: 'attached', timeout: 10000 } );

	const tabIds = await page.locator( '[data-sp-subtab]' ).evaluateAll( ( tabs ) => tabs.map( ( tab ) => tab.getAttribute( 'data-sp-subtab' ) ) );
	check(
		JSON.stringify( tabIds ) === JSON.stringify( [ 'conversations', 'widget', 'handoff', 'voice' ] ),
		'Answer Desk Human handoff keeps the four canonical mockup tabs in order'
	);
	const email = page.locator( '[data-sp-handoff-card][data-sp-handoff-channel="email"]' );
	const telegram = page.locator( '[data-sp-handoff-card][data-sp-handoff-channel="telegram"]' );
	const twilio = page.locator( '[data-sp-handoff-card][data-sp-handoff-channel="twilio"]' );
	check(
		await email.count() === 1
		&& await telegram.count() === 1
		&& await twilio.count() === 1
		&& await page.locator( '[data-sp-handoff-unsupported="whatsapp"]' ).count() === 1
		&& await page.locator( '[data-sp-handoff-unsupported="slack"]' ).count() === 1
		&& await page.locator( '[data-sp-handoff-unsupported="discord"]' ).count() === 1
		&& await page.locator( '[data-sp-handoff-unsupported="webhook"]' ).count() === 1,
		'Answer Desk Human handoff shows real channel cards and locks unsupported mockup transports'
	);
	const handoffText = await root.innerText();
	check(
		handoffText.includes( 'every enabled and configured channel' )
		&& handoffText.includes( 'does not use an ordered fallback queue' )
		&& !handoffText.includes( 'Send a test message' )
		&& await telegram.locator( '[data-sp-handoff-test]' ).isDisabled(),
		'Answer Desk Human handoff describes actual fan-out delivery and keeps an unconfigured provider check disabled'
	);

	await email.locator( '[data-sp-handoff-edit]' ).click();
	await email.locator( '[data-sp-handoff-form]' ).waitFor( { state: 'visible', timeout: 10000 } );
	await email.locator( 'input[name="email_to"]' ).fill( 'browser-handoff@example.test' );
	const saveResponsePromise = page.waitForResponse( ( response ) => response.url().includes( '/handoff/admin-settings' ) && response.request().method() === 'POST' && response.ok() );
	await email.locator( '[data-sp-handoff-save]' ).click();
	const saveResponse = await saveResponsePromise;
	check( !! saveResponse.request().headers()[ 'x-wp-nonce' ], 'Answer Desk Human handoff autosave sends the WordPress REST nonce over its real HTTP request' );
	await email.locator( '[data-sp-handoff-status][data-state="saved"]' ).waitFor( { state: 'visible', timeout: 10000 } );
	check(
		await email.getAttribute( 'data-sp-handoff-enabled' ) === '0'
		&& await email.getAttribute( 'data-sp-handoff-configured' ) === '1'
		&& await root.getAttribute( 'data-sp-handoff-available' ) === '0'
		&& await email.locator( '[data-sp-handoff-state]' ).textContent() === 'Paused',
		'Answer Desk Human handoff saves a real Email recipient without silently resuming a paused channel'
	);

	await page.reload( { waitUntil: 'domcontentloaded', timeout: 20000 } );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	check(
		await email.getAttribute( 'data-sp-handoff-enabled' ) === '0'
		&& await email.locator( 'input[name="email_to"]' ).inputValue() === 'browser-handoff@example.test',
		'Answer Desk Human handoff retains the saved Email channel after reload'
	);

	const enableResponsePromise = page.waitForResponse( ( response ) => response.url().includes( '/handoff/admin-settings' ) && response.request().method() === 'POST' && response.ok() );
	await email.locator( '[data-sp-handoff-toggle]' ).click();
	await enableResponsePromise;
	await page.waitForFunction( () => document.querySelector( '[data-sp-handoff-channel="email"]' ).getAttribute( 'data-sp-handoff-enabled' ) === '1' );
	check( await root.getAttribute( 'data-sp-handoff-available' ) === '1', 'Answer Desk Human handoff can enable a configured channel without a full-form reset' );

	const disableResponsePromise = page.waitForResponse( ( response ) => response.url().includes( '/handoff/admin-settings' ) && response.request().method() === 'POST' && response.ok() );
	await email.locator( '[data-sp-handoff-toggle]' ).click();
	await disableResponsePromise;
	await page.waitForFunction( () => document.querySelector( '[data-sp-handoff-channel="email"]' ).getAttribute( 'data-sp-handoff-enabled' ) === '0' );
	check( await root.getAttribute( 'data-sp-handoff-available' ) === '0', 'Answer Desk Human handoff can pause its only ready channel without a full-form reset' );

	const restoreResponsePromise = page.waitForResponse( ( response ) => response.url().includes( '/handoff/admin-settings' ) && response.request().method() === 'POST' && response.ok() );
	await email.locator( '[data-sp-handoff-toggle]' ).click();
	await restoreResponsePromise;
	await page.waitForFunction( () => document.querySelector( '[data-sp-handoff-channel="email"]' ).getAttribute( 'data-sp-handoff-enabled' ) === '1' );
	check( await root.getAttribute( 'data-sp-handoff-available' ) === '1', 'Answer Desk Human handoff can restore its ready channel without altering the recipient' );

	const addSms = page.locator( '[data-sp-handoff-add="twilio"]' );
	await addSms.click();
	check( await twilio.isVisible() && await twilio.locator( '[data-sp-handoff-form]' ).isVisible(), 'Answer Desk Human handoff reveals the real SMS transport only when an administrator adds it' );
	const desktopOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( !desktopOverflow, 'Answer Desk Human handoff has no desktop horizontal overflow' );
	const desktopBounds = await page.locator( '.sp-answer-handoff' ).evaluate( ( element ) => {
		const rect = element.getBoundingClientRect();
		return { start: rect.left, end: rect.right, viewport: window.innerWidth };
	} );
	check( desktopBounds.start >= 0 && desktopBounds.end <= desktopBounds.viewport, 'Answer Desk Human handoff remains fully visible on desktop' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-handoff-desktop.png' ), fullPage: true } );

	const originalDir = await page.locator( 'html' ).getAttribute( 'dir' );
	await page.locator( 'html' ).evaluate( ( html ) => html.setAttribute( 'dir', 'rtl' ) );
	await page.waitForTimeout( 120 );
	const rtlDirection = await root.evaluate( ( element ) => getComputedStyle( element ).direction );
	const rtlOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( rtlDirection === 'rtl' && !rtlOverflow, 'Answer Desk Human handoff has no horizontal overflow when rendered RTL' );
	const rtlBounds = await page.locator( '.sp-answer-handoff' ).evaluate( ( element ) => {
		const rect = element.getBoundingClientRect();
		return { start: rect.left, end: rect.right, viewport: window.innerWidth };
	} );
	check( rtlBounds.start >= 0 && rtlBounds.end <= rtlBounds.viewport, 'Answer Desk Human handoff remains fully visible when rendered RTL' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-handoff-rtl.png' ), fullPage: true } );
	await page.locator( 'html' ).evaluate( ( html, dir ) => {
		if ( dir ) {
			html.setAttribute( 'dir', dir );
		} else {
			html.removeAttribute( 'dir' );
		}
	}, originalDir );

	await page.setViewportSize( { width: 390, height: 844 } );
	await page.goto( handoffUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	check( await page.locator( '[data-sp-subtab]' ).count() === 4, 'Answer Desk Human handoff keeps all four tabs at 390px width' );
	const mobileOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( !mobileOverflow, 'Answer Desk Human handoff has no horizontal overflow at 390px width' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-handoff-mobile.png' ), fullPage: true } );
}

async function answerVoiceHealthy( page ) {
	console.log( 'CHECK Answer Desk Real-time voice desktop, persistence, RTL and mobile layout' );
	const voiceUrl = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-conversations&tab=voice';
	await page.setViewportSize( { width: 1440, height: 960 } );
	await page.goto( voiceUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	const root = page.locator( '[data-sp-answer-desk][data-sp-answer-tab="voice"]' );
	await root.waitFor( { state: 'attached', timeout: 10000 } );

	const tabIds = await page.locator( '[data-sp-subtab]' ).evaluateAll( ( tabs ) => tabs.map( ( tab ) => tab.getAttribute( 'data-sp-subtab' ) ) );
	check(
		JSON.stringify( tabIds ) === JSON.stringify( [ 'conversations', 'widget', 'handoff', 'voice' ] ),
		'Answer Desk Real-time voice keeps the four canonical mockup tabs in order'
	);
	const realtimeSwitch = page.locator( '[data-sp-voice-realtime-locked] button[role="switch"]' );
	const realtimeEngine = page.locator( '[data-sp-voice-engine-locked]' );
	const engine = page.locator( '[data-sp-voice-engine="transcribe"]' );
	const auto = page.locator( '[data-sp-voice-auto-toggle]' );
	const testPhrase = page.locator( '[data-sp-voice-test]' );
	check(
		await realtimeSwitch.isDisabled()
		&& await realtimeEngine.getAttribute( 'aria-disabled' ) === 'true'
		&& await engine.count() === 1
		&& await auto.count() === 1
		&& await testPhrase.count() === 1,
		'Answer Desk Real-time voice locks unsupported streaming while keeping only real transcribe-and-browser-speak controls active'
	);
	check(
		await root.getAttribute( 'data-sp-voice-enabled' ) === '1'
		&& await engine.getAttribute( 'aria-checked' ) === 'true'
		&& await auto.isEnabled()
		&& await testPhrase.isEnabled(),
		'Answer Desk Real-time voice starts from the local fixture’s live browser-mediated voice state'
	);

	const pauseResponsePromise = page.waitForResponse( ( response ) => response.url().includes( '/voice/admin-settings' ) && response.request().method() === 'POST' && response.ok() );
	await engine.click();
	const pauseResponse = await pauseResponsePromise;
	check( !! pauseResponse.request().headers()[ 'x-wp-nonce' ], 'Answer Desk Real-time voice autosave sends the WordPress REST nonce over its real HTTP request' );
	await page.waitForFunction( () => document.querySelector( '[data-sp-answer-tab="voice"]' ).getAttribute( 'data-sp-voice-enabled' ) === '0' );
	check(
		await engine.getAttribute( 'aria-checked' ) === 'false'
		&& await auto.isDisabled()
		&& await testPhrase.isDisabled(),
		'Answer Desk Real-time voice can pause the actual visitor voice input without pretending realtime streaming exists'
	);

	await page.reload( { waitUntil: 'domcontentloaded', timeout: 20000 } );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	check( await root.getAttribute( 'data-sp-voice-enabled' ) === '0', 'Answer Desk Real-time voice retains its paused voice-input state after reload' );

	const resumeResponsePromise = page.waitForResponse( ( response ) => response.url().includes( '/voice/admin-settings' ) && response.request().method() === 'POST' && response.ok() );
	await engine.click();
	await resumeResponsePromise;
	await page.waitForFunction( () => document.querySelector( '[data-sp-answer-tab="voice"]' ).getAttribute( 'data-sp-voice-enabled' ) === '1' );
	check( await auto.isEnabled() && await testPhrase.isEnabled(), 'Answer Desk Real-time voice restores its live transcribe-and-speak controls without a form reset' );

	const autoResponsePromise = page.waitForResponse( ( response ) => response.url().includes( '/voice/admin-settings' ) && response.request().method() === 'POST' && response.ok() );
	await auto.click();
	await autoResponsePromise;
	await page.waitForFunction( () => document.querySelector( '[data-sp-answer-tab="voice"]' ).getAttribute( 'data-sp-voice-auto' ) === '0' );
	check( await auto.getAttribute( 'aria-checked' ) === 'false', 'Answer Desk Real-time voice can keep input enabled while pausing automatic browser reply playback' );

	const phraseRequests = [];
	const collectPhraseRequest = ( request ) => {
		if ( request.url().includes( '/voice/admin-settings' ) || request.url().includes( '/public/voice' ) ) { phraseRequests.push( request.url() ); }
	};
	page.on( 'request', collectPhraseRequest );
	await testPhrase.click();
	await page.waitForTimeout( 180 );
	page.off( 'request', collectPhraseRequest );
	check(
		phraseRequests.length === 0 && ( await page.locator( '[data-sp-voice-status]' ).textContent() ).trim().length > 0,
		'Answer Desk Real-time voice plays its test phrase locally without posting audio or changing saved settings'
	);

	const desktopOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( !desktopOverflow, 'Answer Desk Real-time voice has no desktop horizontal overflow' );
	const desktopBounds = await page.locator( '.sp-answer-voice' ).evaluate( ( element ) => {
		const rect = element.getBoundingClientRect();
		return { start: rect.left, end: rect.right, viewport: window.innerWidth };
	} );
	check( desktopBounds.start >= 0 && desktopBounds.end <= desktopBounds.viewport, 'Answer Desk Real-time voice remains fully visible on desktop' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-voice-desktop.png' ), fullPage: true } );

	const originalDir = await page.locator( 'html' ).getAttribute( 'dir' );
	await page.locator( 'html' ).evaluate( ( html ) => html.setAttribute( 'dir', 'rtl' ) );
	await page.waitForTimeout( 120 );
	const rtlDirection = await root.evaluate( ( element ) => getComputedStyle( element ).direction );
	const rtlOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( rtlDirection === 'rtl' && !rtlOverflow, 'Answer Desk Real-time voice has no horizontal overflow when rendered RTL' );
	const rtlBounds = await page.locator( '.sp-answer-voice' ).evaluate( ( element ) => {
		const rect = element.getBoundingClientRect();
		return { start: rect.left, end: rect.right, viewport: window.innerWidth };
	} );
	check( rtlBounds.start >= 0 && rtlBounds.end <= rtlBounds.viewport, 'Answer Desk Real-time voice remains fully visible when rendered RTL' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-voice-rtl.png' ), fullPage: true } );
	await page.locator( 'html' ).evaluate( ( html, dir ) => {
		if ( dir ) {
			html.setAttribute( 'dir', dir );
		} else {
			html.removeAttribute( 'dir' );
		}
	}, originalDir );

	await page.setViewportSize( { width: 390, height: 844 } );
	await page.goto( voiceUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	check( await page.locator( '[data-sp-subtab]' ).count() === 4, 'Answer Desk Real-time voice keeps all four tabs at 390px width' );
	const mobileOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( !mobileOverflow, 'Answer Desk Real-time voice has no horizontal overflow at 390px width' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-voice-mobile.png' ), fullPage: true } );
}

async function storeCopilotHealthy( page ) {
	console.log( 'CHECK Store Copilot desktop, persistence, RTL and mobile layout' );
	const storeUrl = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-wc-dashboard';
	await page.setViewportSize( { width: 1440, height: 960 } );
	await page.goto( storeUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	let root = page.locator( '[data-sp-store-copilot][data-sp-store-tab="newproduct"]' );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	if ( expectNativeRtl ) {
		check(
			await page.locator( 'html' ).getAttribute( 'dir' ) === 'rtl',
			'Store Copilot runs inside a genuinely RTL WordPress admin shell'
		);
	}
	const tabIds = await page.locator( '[data-sp-subtab]' ).evaluateAll( ( tabs ) => tabs.map( ( tab ) => tab.getAttribute( 'data-sp-subtab' ) ) );
	check(
		JSON.stringify( tabIds ) === JSON.stringify( [ 'newproduct', 'capabilities', 'products', 'orders' ] ),
		'Store Copilot renders exactly the four canonical mockup tabs in order'
	);
	check(
		await page.locator( '[data-sp-store-new-product]' ).count() === 1
		&& await page.locator( '[data-sp-store-example]' ).count() === 3
		&& await page.locator( '[data-sp-store-proposal]' ).count() === 1
		&& await page.locator( '#sp-woo-ttl' ).count() === 1,
		'Store Copilot New product renders proposal-before-draft controls and examples'
	);
	const planButton = page.locator( '#sp-woo-plan' );
	const proposalState = await Promise.all( [
		root.getAttribute( 'data-sp-store-active' ),
		root.getAttribute( 'data-sp-store-enabled' ),
		root.getAttribute( 'data-sp-store-provider' ),
		root.getAttribute( 'data-sp-store-can-manage' ),
	] );
	const proposalReady = proposalState.every( ( state ) => state === '1' );
	check(
		proposalReady ? await planButton.isEnabled() : await planButton.isDisabled(),
		'Store Copilot New product exposes a truthful proposal-ready or blocked state without calling a provider'
	);
	const desktopOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( !desktopOverflow, 'Store Copilot has no desktop horizontal overflow' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-store-new-product-desktop.png' ), fullPage: true } );

	const capabilitiesUrl = storeUrl + '&tab=capabilities';
	await page.goto( capabilitiesUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	root = page.locator( '[data-sp-store-copilot][data-sp-store-tab="capabilities"]' );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	check(
		await page.locator( '[data-sp-store-capability-state]' ).count() === 9
		&& await page.locator( '[data-sp-store-status-row]' ).count() === 6
		&& await page.locator( '[data-sp-store-setting]' ).count() === 2,
		'Store Copilot What it can do keeps the compact capability, status, and two-live-setting panels'
	);
	const connector = page.locator( '[data-sp-store-setting="enabled"]' );
	check( await connector.getAttribute( 'aria-checked' ) === 'true', 'Store Copilot browser fixture starts with the connector enabled' );
	const pauseSave = page.waitForResponse( ( response ) => response.url().includes( '/woocommerce/admin-settings' ) && response.request().method() === 'POST' && response.ok() );
	await connector.click();
	await pauseSave;
	await page.waitForFunction( () => document.querySelector( '[data-sp-store-setting="enabled"]' ).getAttribute( 'aria-checked' ) === 'false' );
	check( await root.getAttribute( 'data-sp-store-enabled' ) === '0', 'Store Copilot connector can be paused through its protected settings route' );
	const resumeSave = page.waitForResponse( ( response ) => response.url().includes( '/woocommerce/admin-settings' ) && response.request().method() === 'POST' && response.ok() );
	await connector.click();
	await resumeSave;
	await page.waitForFunction( () => document.querySelector( '[data-sp-store-setting="enabled"]' ).getAttribute( 'aria-checked' ) === 'true' );
	check( await root.getAttribute( 'data-sp-store-enabled' ) === '1', 'Store Copilot connector can be restored without a form reset' );

	const originalDir = await page.locator( 'html' ).getAttribute( 'dir' );
	await page.locator( 'html' ).evaluate( ( html ) => html.setAttribute( 'dir', 'rtl' ) );
	await page.waitForTimeout( 120 );
	const rtlDirection = await root.evaluate( ( element ) => getComputedStyle( element ).direction );
	const rtlOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( rtlDirection === 'rtl' && !rtlOverflow, 'Store Copilot capabilities has no horizontal overflow when rendered RTL' );
	const rtlHeaderBounds = await page.locator( '.sp-pagebar__title, .sp-pagebar__sub' ).evaluateAll( ( elements ) => elements.map( ( element ) => {
		const range = document.createRange();
		range.selectNodeContents( element );
		const rect = range.getBoundingClientRect();
		return { start: rect.left, end: rect.right, viewport: window.innerWidth };
	} ) );
	check(
		rtlHeaderBounds.every( ( bounds ) => bounds.start >= 0 && bounds.end <= bounds.viewport ),
		'Store Copilot title and description remain fully visible when rendered RTL'
	);
	const rtlGridBounds = await page.locator( '.sp-store-grid' ).evaluate( ( element ) => {
		const rect = element.getBoundingClientRect();
		return { start: rect.left, end: rect.right, viewport: window.innerWidth };
	} );
	check(
		rtlGridBounds.start >= 0 && rtlGridBounds.end <= rtlGridBounds.viewport,
		'Store Copilot capability panels remain fully visible when rendered RTL'
	);
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-store-capabilities-rtl.png' ), fullPage: true } );
	await page.locator( 'html' ).evaluate( ( html, dir ) => {
		if ( dir ) { html.setAttribute( 'dir', dir ); } else { html.removeAttribute( 'dir' ); }
	}, originalDir );

	const productsUrl = storeUrl + '&tab=products';
	await page.goto( productsUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	root = page.locator( '[data-sp-store-copilot][data-sp-store-tab="products"]' );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	check(
		await page.locator( '[data-sp-store-products]' ).count() === 1
		&& await page.locator( '#sp-stat-revenue, #sp-sales-chart, #sp-top-products' ).count() === 0,
		'Store Copilot Products is read-only and no legacy analytics dashboard controls remain'
	);
	if ( await root.getAttribute( 'data-sp-store-active' ) === '1' ) {
		check( await page.locator( '[data-sp-store-product-generated="1"]' ).count() >= 1, 'Store Copilot Products marks the owned AI-created browser fixture draft' );
	}

	const ordersUrl = storeUrl + '&tab=orders';
	await page.goto( ordersUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	root = page.locator( '[data-sp-store-copilot][data-sp-store-tab="orders"]' );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	check(
		await page.locator( '[data-sp-store-order-permission]' ).count() === 4
		&& await page.locator( '#sp-low-stock, #sp-recent-orders' ).count() === 0,
		'Store Copilot Orders documents the actual read-only contract and no legacy order dashboard controls remain'
	);

	await page.setViewportSize( { width: 390, height: 844 } );
	await page.goto( capabilitiesUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	root = page.locator( '[data-sp-store-copilot][data-sp-store-tab="capabilities"]' );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	check( await page.locator( '[data-sp-subtab]' ).count() === 4, 'Store Copilot keeps all four tabs at 390px width' );
	const mobileOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( !mobileOverflow, 'Store Copilot has no horizontal overflow at 390px width' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-store-capabilities-mobile.png' ), fullPage: true } );
}

async function contentStudioNavigationHealthy( page ) {
	console.log( 'CHECK Content Studio navigation, RTL and mobile shell' );
	const studioUrl = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-form-builder';
	await page.setViewportSize( { width: 1440, height: 960 } );
	await page.goto( studioUrl + '&tab=write', { waitUntil: 'domcontentloaded', timeout: 20000 } );
	let root = page.locator( '[data-sp-content-studio][data-sp-content-tab="write"]' );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	if ( expectNativeRtl ) {
		check(
			await page.locator( 'html' ).getAttribute( 'dir' ) === 'rtl',
			'Content Studio runs inside a genuinely RTL WordPress admin shell'
		);
	}
	const tabIds = await page.locator( '[data-sp-subtab]' ).evaluateAll( ( tabs ) => tabs.map( ( tab ) => tab.getAttribute( 'data-sp-subtab' ) ) );
	check(
		JSON.stringify( tabIds ) === JSON.stringify( [ 'write', 'templates', 'history' ] ),
		'Content Studio renders exactly the three canonical mockup tabs in order'
	);
	check( await page.locator( '[data-sp-subtab="write"][aria-current="page"]' ).count() === 1, 'Content Studio opens on canonical Write' );
	const desktopOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( !desktopOverflow, 'Content Studio has no desktop horizontal overflow' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-content-studio-write-desktop.png' ), fullPage: true } );
	const prompt = root.locator( '#aisaautopilot-content-prompt' );
	const planButton = root.locator( '#aisaautopilot-content-plan' );
	const preview = root.locator( '[data-content-preview]' );
	const result = root.locator( '[data-content-result]' );
	const recentRows = root.locator( '[data-content-recent-row]' );
	const voiceChips = await root.locator( '.sp-content-voice__chip' ).allTextContents();
	check(
		await root.locator( '[data-content-empty]' ).count() === 1
		&& await root.locator( '[data-content-recent]' ).count() === 1
		&& await prompt.getAttribute( 'maxlength' ) === '6000'
		&& await prompt.getAttribute( 'rows' ) === '4'
		&& JSON.stringify( voiceChips.map( ( chip ) => chip.trim() ) ) === JSON.stringify( [ 'Learned from your site', 'Formal', 'Plain' ] )
		&& await planButton.textContent().then( ( text ) => text.trim() === 'Write a draft' ),
		'Content Studio Write matches the mockup composer, static voice chips, and truthful draft-list shell'
	);
	check(
		await preview.isHidden() && await result.isHidden()
		&& await root.locator( '[data-content-approve]' ).count() === 1,
		'Content Studio starts with no generated preview and retains explicit draft approval'
	);

	const routePattern = '**/wp-json/aisaautopilot/v1/content/**';
	const workflowBrief = 'Browser QA content workflow';
	const capBrief = 'Browser QA usage cap';
	let planRequests = 0;
	let executeRequests = 0;
	let workflowPromptReceived = false;
	await page.route( routePattern, async ( route ) => {
		const request = route.request();
		const pathName = new URL( request.url() ).pathname;
		let body = {};
		try {
			body = JSON.parse( request.postData() || '{}' );
		} catch ( error ) {
			body = {};
		}
		if ( pathName.endsWith( '/content/plan' ) ) {
			planRequests++;
			workflowPromptReceived = workflowPromptReceived || body.prompt === workflowBrief;
			if ( body.prompt === capBrief ) {
				await route.fulfill( {
					status: 429,
					contentType: 'application/json',
					body: JSON.stringify( { ok: false, code: 'usage_cap_reached', message: 'Usage cap reached for browser QA.' } ),
				} );
				return;
			}
			await route.fulfill( {
				contentType: 'application/json',
				body: JSON.stringify( {
					ok: true,
					action_id: 'browser-qa-action',
					preview: {
						post_type: 'post',
						title: 'Browser QA draft title',
						slug: 'browser-qa-draft-title',
						meta_description: 'A safe browser QA preview.',
						body: 'This draft remains reviewable until explicit approval.',
					},
				} ),
			} );
			return;
		}
		if ( pathName.endsWith( '/content/execute' ) ) {
			executeRequests++;
			await route.fulfill( {
				contentType: 'application/json',
				body: JSON.stringify( {
					ok: true,
					result: {
						post_id: 999999,
						edit_url: baseUrl + '/wp-admin/post.php?post=999999&action=edit',
						is_draft: true,
					},
				} ),
			} );
			return;
		}
		await route.fallback();
	} );
	const rowsBeforePlan = await recentRows.count();
	await prompt.evaluate( ( element ) => { element.disabled = false; } );
	await planButton.evaluate( ( element ) => { element.disabled = false; } );
	await prompt.fill( workflowBrief );
	await planButton.click();
	await preview.waitFor( { state: 'visible', timeout: 10000 } );
	check(
		await preview.locator( '[data-content-title]' ).textContent().then( ( text ) => text.trim() === 'Browser QA draft title' )
		&& await root.locator( '[data-content-compose]' ).isHidden()
		&& rowsBeforePlan === await recentRows.count(),
		'Content Studio plan shows a reviewable preview only and adds no draft before approval'
	);
	await root.locator( '[data-content-approve]' ).click();
	await result.waitFor( { state: 'visible', timeout: 10000 } );
	check(
		planRequests === 1 && executeRequests === 1 && workflowPromptReceived
		&& rowsBeforePlan + 1 === await recentRows.count()
		&& await result.locator( 'a' ).getAttribute( 'href' ).then( ( href ) => href && href.includes( 'post=999999' ) ),
		'Content Studio creates the UI result and truthful recent row only after explicit approval'
	);

	await page.goto( studioUrl + '&tab=write', { waitUntil: 'domcontentloaded', timeout: 20000 } );
	root = page.locator( '[data-sp-content-studio][data-sp-content-tab="write"]' );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	const capPrompt = root.locator( '#aisaautopilot-content-prompt' );
	const capPlanButton = root.locator( '#aisaautopilot-content-plan' );
	await capPrompt.evaluate( ( element ) => { element.disabled = false; } );
	await capPlanButton.evaluate( ( element ) => { element.disabled = false; } );
	await capPrompt.fill( capBrief );
	await capPlanButton.click();
	await page.waitForFunction( () => {
		const input = document.querySelector( '#aisaautopilot-content-prompt' );
		const button = document.querySelector( '#aisaautopilot-content-plan' );
		return !!input && !!button && input.disabled && button.disabled;
	}, undefined, { timeout: 10000 } );
	check(
		planRequests === 2 && await capPrompt.isDisabled() && await capPlanButton.isDisabled()
		&& await root.locator( '[data-content-preview]' ).isHidden(),
		'Content Studio locks its composer after a usage-cap response and never exposes a stale preview'
	);
	await page.unroute( routePattern );

	await page.locator( '[data-sp-subtab="templates"]' ).click();
	root = page.locator( '[data-sp-content-studio][data-sp-content-tab="templates"]' );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	check( await page.locator( '[data-sp-subtab="templates"][aria-current="page"]' ).count() === 1, 'Content Studio Templates tab navigates to its canonical surface' );
	const templateCards = root.locator( '[data-content-template]' );
	const templateLabels = await root.locator( '.sp-content-template__title' ).allTextContents();
	const templateLink = templateCards.first().locator( 'a' );
	const templateHref = await templateLink.getAttribute( 'href' );
	const templatesOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check(
		await templateCards.count() === 4
		&& JSON.stringify( templateLabels.map( ( label ) => label.trim() ) ) === JSON.stringify( [ 'Product description', 'Blog post outline', 'Meta description', 'Support answer' ] )
		&& !!templateHref && templateHref.includes( 'tab=write' ) && templateHref.includes( 'brief=' )
		&& !templatesOverflow,
		'Content Studio Templates matches the four-card mockup and stays within the desktop viewport'
	);
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-content-studio-templates-desktop.png' ), fullPage: true } );
	let templateRestRequests = 0;
	await page.route( routePattern, async ( route ) => {
		templateRestRequests++;
		await route.abort();
	} );
	await templateLink.focus();
	await page.keyboard.press( 'Enter' );
	root = page.locator( '[data-sp-content-studio][data-sp-content-tab="write"]' );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	const templatePrefill = await root.locator( '#aisaautopilot-content-prompt' ).inputValue();
	const templatePreviewHidden = await root.locator( '[data-content-preview]' ).isHidden();
	const templateBridgeSafe = templatePrefill === 'Write 60–90 words for {product}. Lead with the material, then the use. No superlatives.'
		&& templatePreviewHidden
		&& templateRestRequests === 0;
	check(
		templateBridgeSafe,
		'Using a template by keyboard opens an editable Write prefill without any automatic REST action'
	);
	await page.unroute( routePattern );

	await page.goto( studioUrl + '&tab=templates', { waitUntil: 'domcontentloaded', timeout: 20000 } );
	root = page.locator( '[data-sp-content-studio][data-sp-content-tab="templates"]' );
	await root.waitFor( { state: 'attached', timeout: 10000 } );

	await page.locator( '[data-sp-subtab="history"]' ).click();
	root = page.locator( '[data-sp-content-studio][data-sp-content-tab="history"]' );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	const historyCard = root.locator( '[data-content-history]' );
	const historyRows = root.locator( '[data-content-history-row]' );
	const historyExport = historyCard.locator( '.sp-content-history__export' );
	const historyText = await historyCard.innerText();
	const historyDesktopOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check(
		await page.locator( '[data-sp-subtab="history"][aria-current="page"]' ).count() === 1
		&& await historyCard.count() === 1
		&& await historyExport.count() === 1
		&& !historyText.includes( 'Search actions' )
		&& !historyText.includes( 'All connectors' )
		&& !historyDesktopOverflow,
		'Content Studio History tab renders its mockup card without legacy Activity Log controls'
	);
	if ( contentHistoryFixtureTitle ) {
		check(
			await historyRows.count() >= 1
			&& historyText.includes( contentHistoryFixtureTitle )
			&& !historyText.includes( contentHistoryFixtureUnmarkedTitle )
			&& historyText.includes( 'Template not recorded' )
			&& historyText.includes( 'Usage not recorded' ),
			'Content Studio History displays only the marker-owned browser fixture with truthful unavailable attribution'
		);
		let historyExportRestRequests = 0;
		let historyCsv = '';
		await page.route( routePattern, async ( route ) => {
			historyExportRestRequests++;
			await route.abort();
		} );
		try {
			const downloadPromise = page.waitForEvent( 'download', { timeout: 20000 } );
			await historyExport.focus();
			await page.keyboard.press( 'Enter' );
			const download = await downloadPromise;
			const downloadPath = await download.path();
			if ( downloadPath ) {
				historyCsv = fs.readFileSync( downloadPath, 'utf8' );
			}
		} catch ( error ) {
			console.log( 'CONTENT_HISTORY_EXPORT_DIAGNOSTIC ' + error.message );
		} finally {
			await page.unroute( routePattern );
		}
		check(
			historyCsv.startsWith( 'created_at,title,destination,state,template,usage,edit_url' )
			&& historyCsv.includes( contentHistoryFixtureTitle )
			&& !historyCsv.includes( contentHistoryFixtureUnmarkedTitle )
			&& !historyCsv.includes( 'connector_id' )
			&& historyExportRestRequests === 0,
			'Content Studio History exports only its owned CSV rows by keyboard without REST activity'
		);
	}
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-content-studio-history-desktop.png' ), fullPage: true } );

	const originalDir = await page.locator( 'html' ).getAttribute( 'dir' );
	await page.locator( 'html' ).evaluate( ( html ) => html.setAttribute( 'dir', 'rtl' ) );
	await page.waitForTimeout( 120 );
	const rtlDirection = await root.evaluate( ( element ) => getComputedStyle( element ).direction );
	const rtlOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	const rtlBounds = await root.evaluate( ( element ) => {
		const rect = element.getBoundingClientRect();
		return { start: rect.left, end: rect.right, viewport: window.innerWidth };
	} );
	check(
		rtlDirection === 'rtl' && !rtlOverflow && rtlBounds.start >= 0 && rtlBounds.end <= rtlBounds.viewport,
		'Content Studio shell remains fully visible when rendered RTL'
	);
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-content-studio-history-rtl.png' ), fullPage: true } );
	await page.locator( 'html' ).evaluate( ( html, dir ) => {
		if ( dir ) { html.setAttribute( 'dir', dir ); } else { html.removeAttribute( 'dir' ); }
	}, originalDir );

	await page.setViewportSize( { width: 390, height: 844 } );
	await page.goto( studioUrl + '&tab=templates', { waitUntil: 'domcontentloaded', timeout: 20000 } );
	root = page.locator( '[data-sp-content-studio][data-sp-content-tab="templates"]' );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	const templatesMobileOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( await root.locator( '[data-content-template]' ).count() === 4 && !templatesMobileOverflow, 'Content Studio Templates keeps four cards without overflow at 390px width' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-content-studio-templates-mobile.png' ), fullPage: true } );

	await page.goto( studioUrl + '&tab=history', { waitUntil: 'domcontentloaded', timeout: 20000 } );
	root = page.locator( '[data-sp-content-studio][data-sp-content-tab="history"]' );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	const historyMobileOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check(
		await root.locator( '[data-content-history]' ).count() === 1
		&& ( !contentHistoryFixtureTitle || await root.locator( '[data-content-history-row]' ).count() >= 1 )
		&& !historyMobileOverflow,
		'Content Studio History keeps its rows within the 390px viewport'
	);
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-content-studio-history-mobile.png' ), fullPage: true } );

	await page.goto( studioUrl + '&tab=write', { waitUntil: 'domcontentloaded', timeout: 20000 } );
	root = page.locator( '[data-sp-content-studio][data-sp-content-tab="write"]' );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	check( await page.locator( '[data-sp-subtab]' ).count() === 3, 'Content Studio keeps all three tabs at 390px width' );
	const mobileOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check( !mobileOverflow, 'Content Studio has no horizontal overflow at 390px width' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-content-studio-write-mobile.png' ), fullPage: true } );
}

async function formComposerHealthy( page ) {
	console.log( 'CHECK Form Composer mockup shell, approval flow, RTL and mobile layout' );
	const formUrl = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-form-builder&mode=forms';
	const routePattern = '**/wp-json/aisaautopilot/v1/forms/**';
	let planRequests = 0;
	let executeRequests = 0;
	let plannedStatus = 'draft';
	await page.route( routePattern, async ( route ) => {
		const request = route.request();
		const pathname = new URL( request.url() ).pathname;
		if ( pathname.endsWith( '/forms/plan' ) ) {
			planRequests++;
			const requestBody = request.postDataJSON();
			plannedStatus = /publish/i.test( requestBody.prompt || '' ) ? 'publish' : 'draft';
			await route.fulfill( { contentType: 'application/json', body: JSON.stringify( {
				ok: true,
				action_id: 'form-browser-qa-action',
				expires_at: Math.floor( Date.now() / 1000 ) + 900,
				preview: {
					title: 'Browser QA contact form', description: 'A review-only browser fixture.',
					fields: [
						{ label: 'Name', type: 'text', required: true, options: [] },
						{ label: 'Email', type: 'email', required: true, options: [] },
						{ label: 'Message', type: 'textarea', required: false, options: [] },
					],
					submit_button: 'Send', confirmation_message: 'Thanks.', risk_level: 'low', notes: '',
					connector: 'AISAAutoPilot Built-in Forms', notification_email: 'a***@example.test',
					field_count: 3, default_status: plannedStatus,
				},
			} ) } );
			return;
		}
		if ( pathname.endsWith( '/forms/execute' ) ) {
			executeRequests++;
			await route.fulfill( { contentType: 'application/json', body: JSON.stringify( {
				ok: true,
				result: { connector: 'AISAAutoPilot Built-in Forms', form_id: 999998, edit_url: baseUrl + '/wp-admin/post.php?post=999998&action=edit', shortcode: '[aisaautopilot_form id="999998"]', is_draft: plannedStatus !== 'publish' },
			} ) } );
			return;
		}
		await route.fallback();
	} );

	try {
		await page.setViewportSize( { width: 1440, height: 960 } );
		await page.goto( formUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
		const root = page.locator( '[data-aisaautopilot-copilot]' );
		await root.waitFor( { state: 'attached', timeout: 10000 } );
		const canvas = root.locator( '[data-sp-form-canvas]' );
		const assistant = root.locator( '[data-sp-form-assistant]' );
		const prompt = root.locator( '#aisaautopilot-prompt' );
		const examples = root.locator( '#aisaautopilot-examples [data-example]' );
		const rects = await Promise.all( [ canvas.boundingBox(), assistant.boundingBox() ] );
		const desktopOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
		const nativeRtl = await page.locator( 'html' ).getAttribute( 'dir' ) === 'rtl';
		check(
			await root.locator( '.sp-fc-header' ).count() === 1
			&& await canvas.count() === 1 && await assistant.count() === 1
			&& await root.locator( '[data-sp-form-empty]' ).count() === 1
			&& await examples.count() === 3 && await root.locator( '[data-sp-form-delivery]' ).count() === 1
			&& !!rects[0] && !!rects[1]
			&& ( nativeRtl ? rects[1].x < rects[0].x : rects[1].x > rects[0].x )
			&& !desktopOverflow,
			'Form Composer renders the one-header canvas/assistant mockup structure without desktop overflow'
		);
		await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-form-composer-empty-desktop.png' ), fullPage: true } );

		await examples.first().click();
		check( ( await prompt.inputValue() ).length > 10 && planRequests === 0, 'Example chip only creates an editable prefill and makes no provider request' );
		await root.locator( '[data-sp-form-new]' ).click();
		check( await prompt.inputValue() === '' && await prompt.evaluate( ( node ) => document.activeElement === node ), 'New form clears the draft and returns keyboard focus to the composer' );
		await prompt.fill( 'Build a contact form for browser QA.' );
		await prompt.press( 'Control+Enter' );
		const review = root.locator( '#aisaautopilot-review-screen' );
		await review.waitFor( { state: 'visible', timeout: 10000 } );
		const reviewCanvas = review.locator( '[data-sp-form-review-canvas]' );
		const reviewAssistant = review.locator( '[data-sp-form-review-assistant]' );
		const reviewRects = await Promise.all( [ reviewCanvas.boundingBox(), reviewAssistant.boundingBox() ] );
		const reviewText = await review.innerText();
		check(
			planRequests === 1 && executeRequests === 0
			&& await review.locator( '.sp-preview__field' ).count() === 3
			&& await review.locator( '#aisaautopilot-approve' ).count() === 1
			&& !!reviewRects[0] && !!reviewRects[1]
			&& ( nativeRtl ? reviewRects[1].x < reviewRects[0].x : reviewRects[1].x > reviewRects[0].x )
			&& !reviewText.includes( '1,200' ),
			'Form Composer shows the validated field preview in the mockup canvas/assistant shell before execution'
		);
		await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-form-composer-review-desktop.png' ), fullPage: true } );
		await review.locator( '#aisaautopilot-approve' ).click();
		const result = root.locator( '#aisaautopilot-result-screen' );
		await result.waitFor( { state: 'visible', timeout: 10000 } );
		const resultCanvas = result.locator( '[data-sp-form-result-canvas]' );
		const resultAssistant = result.locator( '[data-sp-form-result-assistant]' );
		const resultRects = await Promise.all( [ resultCanvas.boundingBox(), resultAssistant.boundingBox() ] );
		check(
			executeRequests === 1
			&& await result.locator( 'input[value="[aisaautopilot_form id=\\"999998\\"]"]' ).count() === 1
			&& await result.locator( 'a[href*="post=999998"]' ).count() === 1
			&& await result.locator( '[data-sp-form-build-another]' ).count() === 1
			&& !!resultRects[0] && !!resultRects[1]
			&& ( nativeRtl ? resultRects[1].x < resultRects[0].x : resultRects[1].x > resultRects[0].x )
			&& !( await result.innerText() ).includes( '1,200' ),
			'Explicit approval produces the real result controls in the mockup canvas/assistant shell'
		);
		await page.waitForTimeout( 350 );
		await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-form-composer-result-desktop.png' ), fullPage: true } );
		await result.locator( '[data-sp-form-build-another]' ).click();
		check( await root.locator( '#aisaautopilot-compose-screen' ).isVisible(), 'Build another returns to the single compose surface' );

		await prompt.fill( 'Build and publish a contact form for browser QA.' );
		await prompt.press( 'Control+Enter' );
		await review.waitFor( { state: 'visible', timeout: 10000 } );
		await review.locator( '#aisaautopilot-approve' ).click();
		const confirm = root.locator( '#sp-confirm' );
		const confirmGo = confirm.locator( '#sp-confirm-go' );
		await confirm.waitFor( { state: 'visible', timeout: 10000 } );
		check(
			planRequests === 2 && executeRequests === 1 && await confirmGo.isDisabled(),
			'Publish approval opens the second confirmation and cannot execute before acknowledgement'
		);
		await confirm.locator( '#sp-confirm-ack' ).check();
		check( !( await confirmGo.isDisabled() ), 'Publish confirmation becomes actionable only after acknowledgement' );
		await confirmGo.click();
		await result.waitFor( { state: 'visible', timeout: 10000 } );
		check(
			executeRequests === 2 && await result.locator( '[data-sp-form-result]' ).count() === 1,
			'Confirmed publish executes once and renders the reviewed published result'
		);
		await result.locator( '[data-sp-form-build-another]' ).click();

		if ( expectNativeRtl ) {
			check( nativeRtl, 'Form Composer runs inside a genuinely RTL WordPress admin shell' );
			await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-form-composer-rtl.png' ), fullPage: true } );
		}
		await page.setViewportSize( { width: 390, height: 844 } );
		await page.goto( formUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
		await root.waitFor( { state: 'attached', timeout: 10000 } );
		const mobileOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
		check(
			await root.locator( '[data-sp-form-canvas]' ).count() === 1
			&& await root.locator( '[data-sp-form-assistant]' ).count() === 1
			&& !mobileOverflow,
			'Form Composer keeps canvas and assistant inside the 390px viewport'
		);
		await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-form-composer-mobile.png' ), fullPage: true } );

		await prompt.fill( 'Build a contact form for mobile browser QA.' );
		await prompt.press( 'Control+Enter' );
		await review.waitFor( { state: 'visible', timeout: 10000 } );
		const mobileReviewRects = await Promise.all( [ reviewCanvas.boundingBox(), reviewAssistant.boundingBox() ] );
		const mobileReviewOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
		check(
			planRequests === 3 && !!mobileReviewRects[0] && !!mobileReviewRects[1]
			&& mobileReviewRects[1].y >= mobileReviewRects[0].y + mobileReviewRects[0].height - 1
			&& !mobileReviewOverflow,
			'Form Composer stacks the review canvas before its assistant without 390px overflow'
		);
		await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-form-composer-review-mobile.png' ), fullPage: true } );
		await review.locator( '#aisaautopilot-approve' ).click();
		await result.waitFor( { state: 'visible', timeout: 10000 } );
		const mobileResultRects = await Promise.all( [ resultCanvas.boundingBox(), resultAssistant.boundingBox() ] );
		const mobileResultOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
		check(
			executeRequests === 3 && !!mobileResultRects[0] && !!mobileResultRects[1]
			&& mobileResultRects[1].y >= mobileResultRects[0].y + mobileResultRects[0].height - 1
			&& !mobileResultOverflow,
			'Form Composer stacks the approved result before its assistant without 390px overflow'
		);
		await page.waitForTimeout( 350 );
		await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-form-composer-result-mobile.png' ), fullPage: true } );
	} finally {
		await page.unroute( routePattern );
	}
}

async function usageHealthy( page ) {
	console.log( 'CHECK Usage desktop, cap control, RTL and mobile layout' );
	const usageUrl = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-usage';
	await page.setViewportSize( { width: 1440, height: 960 } );
	await page.goto( usageUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	const root = page.locator( '[data-sp-usage]' );
	await root.waitFor( { state: 'attached', timeout: 10000 } );

	const state = await root.getAttribute( 'data-sp-usage-state' );
	const rootText = await root.innerText();
	check(
		[ 'empty', 'unlimited', 'normal', 'near', 'exhausted' ].includes( state )
		&& await root.locator( '[data-sp-usage-card]' ).count() === 2
		&& await root.locator( '[data-sp-usage-day]' ).count() === 7
		&& await root.locator( '[data-sp-usage-operation]' ).count() === 4,
		'Usage renders its two mockup cards, seven daily bars and four truthful operation rows'
	);
	check(
		!rootText.includes( '$' )
		&& !rootText.includes( 'Free tier' )
		&& !rootText.includes( 'By Model' )
		&& !rootText.includes( 'Credit Health' ),
		'Usage does not invent price, free-tier or legacy model-health UI'
	);
	const desktopOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	const desktopBounds = await root.locator( '[data-sp-usage-grid]' ).evaluate( ( grid ) => {
		const rect = grid.getBoundingClientRect();
		return { start: rect.left, end: rect.right, viewport: window.innerWidth };
	} );
	check( !desktopOverflow && desktopBounds.start >= 0 && desktopBounds.end <= desktopBounds.viewport, 'Usage two-card grid fits the desktop viewport' );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-usage-desktop.png' ), fullPage: true } );

	const capTrigger = page.locator( '#sp-usage-cap-open' );
	const capEditor = page.locator( '[data-sp-usage-cap-editor]' );
	const capInput = page.locator( '#sp-monthly-budget' );
	await capTrigger.focus();
	await page.keyboard.press( 'Enter' );
	await capEditor.waitFor( { state: 'visible', timeout: 10000 } );
	await page.waitForFunction( () => document.activeElement === document.getElementById( 'sp-monthly-budget' ), undefined, { timeout: 10000 } );
	check(
		await capTrigger.getAttribute( 'aria-expanded' ) === 'true'
		&& await capInput.evaluate( ( input ) => document.activeElement === input ),
		'Usage cap editor opens from the header CTA and moves focus to its real token input'
	);
	await page.keyboard.press( 'Escape' );
	await capEditor.waitFor( { state: 'hidden', timeout: 10000 } );
	check(
		await capTrigger.getAttribute( 'aria-expanded' ) === 'false'
		&& await capTrigger.evaluate( ( trigger ) => document.activeElement === trigger ),
		'Usage cap editor closes with Escape and returns focus to its CTA'
	);

	let releaseBudgetResponse;
	const heldBudgetResponse = new Promise( ( resolve ) => { releaseBudgetResponse = resolve; } );
	let budgetRouteStarted = false;
	let finishBudgetRoute;
	const budgetRouteFinished = new Promise( ( resolve ) => { finishBudgetRoute = resolve; } );
	const heldBudgetRoute = async ( route ) => {
		budgetRouteStarted = true;
		try {
			await heldBudgetResponse;
			await route.fulfill( {
				status: 400,
				contentType: 'application/json',
				body: JSON.stringify( { ok: false, code: 'invalid_budget', message: 'Held local test response.' } ),
			} );
		} finally {
			finishBudgetRoute();
		}
	};
	await page.route( '**/usage/budget', heldBudgetRoute );
	try {
		await capTrigger.click();
		await capEditor.waitFor( { state: 'visible', timeout: 10000 } );
		await capInput.fill( '1200' );
		await page.locator( '[data-sp-usage-cap-save]' ).click();
		await page.waitForFunction( () => document.getElementById( 'sp-save-budget' ).disabled, undefined, { timeout: 10000 } );
		const lockedOpeners = await root.locator( '[data-sp-usage-cap-open], #sp-usage-cap-open' ).evaluateAll( ( openers ) => openers.every( ( opener ) => opener.disabled ) );
		check(
			await page.locator( '[data-sp-usage-cap-save]' ).isDisabled()
			&& await page.locator( '[data-sp-usage-cap-cancel]' ).isDisabled()
			&& await page.locator( '[data-sp-usage-reset]' ).isDisabled()
			&& await capInput.isDisabled()
			&& lockedOpeners,
			'Usage locks every cap control while its real save request is pending'
		);
		await page.keyboard.press( 'Escape' );
		check( await capEditor.isVisible(), 'Usage ignores Escape while a cap save is pending' );
	} finally {
		releaseBudgetResponse();
		if ( budgetRouteStarted ) {
			await budgetRouteFinished;
		}
		await page.unroute( '**/usage/budget', heldBudgetRoute );
	}
	await page.waitForFunction( () => !document.getElementById( 'sp-save-budget' ).disabled, undefined, { timeout: 10000 } );
	await page.locator( '[data-sp-usage-cap-cancel]' ).click();
	await capEditor.waitFor( { state: 'hidden', timeout: 10000 } );

	let originalDir = null;
	if ( expectNativeRtl ) {
		check(
			await page.locator( 'html' ).getAttribute( 'dir' ) === 'rtl',
			'Usage runs inside a genuinely RTL WordPress admin shell'
		);
	} else {
		originalDir = await page.locator( 'html' ).getAttribute( 'dir' );
		await page.locator( 'html' ).evaluate( ( html ) => html.setAttribute( 'dir', 'rtl' ) );
		await page.waitForTimeout( 120 );
	}
	const rtlDirection = await root.evaluate( ( element ) => getComputedStyle( element ).direction );
	const rtlOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	const rtlBounds = await root.locator( '[data-sp-usage-grid]' ).evaluate( ( grid ) => {
		const rect = grid.getBoundingClientRect();
		const content = document.getElementById( 'wpcontent' );
		const contentRect = content ? content.getBoundingClientRect() : { left: 0, right: window.innerWidth };
		return { start: rect.left, end: rect.right, contentStart: contentRect.left, contentEnd: contentRect.right };
	} );
	const rtlTitleBounds = await root.locator( '.sp-pagebar__title' ).evaluate( ( title ) => {
		const range = document.createRange();
		range.selectNodeContents( title );
		const rect = range.getBoundingClientRect();
		const content = document.getElementById( 'wpcontent' );
		const contentRect = content ? content.getBoundingClientRect() : { left: 0, right: window.innerWidth };
		return { start: rect.left, end: rect.right, contentStart: contentRect.left, contentEnd: contentRect.right };
	} );
	const rtlTotalBounds = await root.locator( '[data-sp-usage-total]' ).evaluate( ( total ) => {
		const range = document.createRange();
		range.selectNodeContents( total );
		const rect = range.getBoundingClientRect();
		const content = document.getElementById( 'wpcontent' );
		const contentRect = content ? content.getBoundingClientRect() : { left: 0, right: window.innerWidth };
		return { start: rect.left, end: rect.right, contentStart: contentRect.left, contentEnd: contentRect.right };
	} );
	const insideAdminContent = ( bounds ) => bounds.start >= bounds.contentStart - 1 && bounds.end <= bounds.contentEnd + 1;
	check(
		rtlDirection === 'rtl'
		&& !rtlOverflow
		&& insideAdminContent( rtlBounds )
		&& insideAdminContent( rtlTitleBounds )
		&& insideAdminContent( rtlTotalBounds ),
		expectNativeRtl
			? 'Usage stays inside its native RTL WordPress content rectangle without clipping'
			: 'Usage stays inside the WordPress content rectangle when rendered synthetic RTL'
	);
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-usage-rtl.png' ), fullPage: true } );
	if ( !expectNativeRtl ) {
		await page.locator( 'html' ).evaluate( ( html, dir ) => {
			if ( dir ) {
				html.setAttribute( 'dir', dir );
			} else {
				html.removeAttribute( 'dir' );
			}
		}, originalDir );
	}

	await page.setViewportSize( { width: 390, height: 844 } );
	await page.goto( usageUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	const mobileOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check(
		await root.locator( '[data-sp-usage-card]' ).count() === 2
		&& await root.locator( '[data-sp-usage-day]' ).count() === 7
		&& !mobileOverflow,
		'Usage keeps both cards and all seven bars without overflow at 390px width'
	);
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-usage-mobile.png' ), fullPage: true } );

	if ( !usageFixtureEnabled ) {
		console.log( 'SKIP Usage cap persistence/reset by AISAAUTOPILOT_BROWSER_USAGE_FIXTURE' );
		return;
	}

	await page.setViewportSize( { width: 1440, height: 960 } );
	await page.goto( usageUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	await capTrigger.click();
	await capEditor.waitFor( { state: 'visible', timeout: 10000 } );
	await capInput.fill( '2400' );
	const saveResponsePromise = page.waitForResponse( ( response ) => response.url().includes( '/usage/budget' ) && response.request().method() === 'POST' && response.ok() );
	await page.locator( '[data-sp-usage-cap-save]' ).click();
	const saveResponse = await saveResponsePromise;
	check( !!saveResponse.request().headers()[ 'x-wp-nonce' ], 'Usage cap save uses the real protected WordPress REST request' );
	await page.waitForTimeout( 900 );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	await capTrigger.click();
	await capEditor.waitFor( { state: 'visible', timeout: 10000 } );
	check( await capInput.inputValue() === '2400', 'Usage cap persists after reload' );

	page.once( 'dialog', ( dialog ) => dialog.accept() );
	const resetResponsePromise = page.waitForResponse( ( response ) => response.url().includes( '/usage/reset' ) && response.request().method() === 'POST' && response.ok() );
	await page.locator( '[data-sp-usage-reset]' ).click();
	const resetResponse = await resetResponsePromise;
	check( !!resetResponse.request().headers()[ 'x-wp-nonce' ], 'Usage reset uses the real protected WordPress REST request' );
	await page.waitForTimeout( 900 );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	check( await root.getAttribute( 'data-sp-usage-state' ) === 'empty', 'Usage reset clears only the fixture current-period state' );
}

async function activityLogHealthy( page ) {
	console.log( 'CHECK Activity Log timeline, export, RTL and mobile layout' );
	const activityUrl = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-logs';
	await page.setViewportSize( { width: 1440, height: 960 } );
	let activityApiRequests = 0;
	const activityRoute = async ( route ) => {
		activityApiRequests++;
		await route.abort();
	};
	await page.route( '**/aisaautopilot/v1/activity**', activityRoute );
	try {
		await page.goto( activityUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
		const root = page.locator( '[data-sp-logs]' );
		await root.waitFor( { state: 'attached', timeout: 10000 } );
		const timeline = root.locator( activityLogExpectEmpty ? '[data-sp-activity-empty]' : '[data-sp-activity-timeline]' );
		const rows = root.locator( '[data-sp-activity-row]' );
		const rowContract = await rows.evaluateAll( ( elements ) => elements.every( ( row ) => (
			!!row.querySelector( '.sp-activity-row__when' )
			&& !!row.querySelector( '.sp-activity-row__feature' )
			&& !!row.querySelector( '.sp-activity-row__summary' )
			&& !!row.querySelector( '.sp-activity-row__state' )
			&& !!row.querySelector( '.sp-activity-row__action' )
		) ) );
		const desktopOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
		const text = await root.innerText();
		check(
			await timeline.count() === 1
			&& ( activityLogExpectEmpty
				? await rows.count() === 0 && await root.locator( '[data-sp-activity-group]' ).count() === 0 && text.includes( 'No changes yet' )
				: await root.locator( '[data-sp-activity-group]' ).count() >= 1 && await rows.count() >= 1 )
			&& rowContract
			&& text.includes( 'Recorded AutoPilot activity, with a real draft link when one is available.' )
			&& !text.includes( 'with a way back.' )
			&& !text.includes( 'Search messages, events, traces' )
			&& !text.includes( 'All levels' )
			&& !text.includes( 'Clear log' )
			&& !desktopOverflow,
			'Activity Log renders the mockup card/timeline without legacy filters, clear control or desktop overflow'
		);
		check( activityApiRequests === 0, 'Activity Log uses its server-rendered redacted timeline and makes no client REST activity request' );

		if ( activityLogFixtureDraft && activityLogFixtureError ) {
			const draftLink = root.locator( 'a.sp-activity-row__action' );
			const draftLinkCount = await draftLink.count();
			const draftHref = draftLinkCount ? await draftLink.getAttribute( 'href' ) : '';
			if ( draftLinkCount ) {
				await draftLink.focus();
			}
			check(
				await rows.count() === 4
				&& text.includes( activityLogFixtureDraft )
				&& text.includes( activityLogFixtureError )
				&& await root.locator( '.sp-activity-row__state--bad' ).count() >= 1
				&& draftLinkCount === 1
				&& !!draftHref && draftHref.includes( 'post=' )
				&& draftLinkCount === 1 && await draftLink.evaluate( ( link ) => document.activeElement === link ),
				'Activity Log browser fixture shows real recorded/error rows and one keyboard-focusable generated-draft action only'
			);
		}

		const exportLink = root.locator( '.sp-pagebar__action' );
		const exportLinkCount = await exportLink.count();
		let csv = '';
		try {
			if ( !exportLinkCount ) {
				throw new Error( 'Activity Log export link is missing.' );
			}
			const downloadPromise = page.waitForEvent( 'download', { timeout: 20000 } );
			await exportLink.focus();
			await page.keyboard.press( 'Enter' );
			const download = await downloadPromise;
			const downloadPath = await download.path();
			if ( downloadPath ) {
				csv = fs.readFileSync( downloadPath, 'utf8' );
			}
		} catch ( error ) {
			console.log( 'ACTIVITY_LOG_EXPORT_DIAGNOSTIC ' + error.message );
		}
		const exportHref = exportLinkCount ? await exportLink.getAttribute( 'href' ) : '';
		check(
			exportLinkCount === 1
			&& !!exportHref && exportHref.includes( 'admin-post.php?action=aisaautopilot_export_logs' )
			&& csv.startsWith( 'time,level,event,trace_id,action_id,connector_id,message' )
			&& ( activityLogExpectEmpty ? csv.trim().split( /\r?\n/ ).length === 1 : !activityLogFixtureDraft || csv.includes( activityLogFixtureDraft ) ),
			'Activity Log exports its safe CSV schema by keyboard without a REST activity request'
		);
		check( await exportLink.evaluate( ( link ) => getComputedStyle( link ).color === 'rgb(255, 255, 255)' ), 'Focused Export CSV keeps readable white text' );
		await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-logs-desktop.png' ), fullPage: true } );

		let originalDir = null;
		if ( expectNativeRtl ) {
			check( await page.locator( 'html' ).getAttribute( 'dir' ) === 'rtl', 'Activity Log runs inside a genuinely RTL WordPress admin shell' );
			check( await root.locator( '.sp-pagebar__titles' ).evaluate( ( element ) => [ 'start', 'right' ].includes( getComputedStyle( element ).textAlign ) ), 'Activity Log title aligns to the RTL reading edge' );
		} else {
			originalDir = await page.locator( 'html' ).getAttribute( 'dir' );
			await page.locator( 'html' ).evaluate( ( html ) => html.setAttribute( 'dir', 'rtl' ) );
			await page.waitForTimeout( 120 );
		}
		const rtlDirection = await root.evaluate( ( element ) => getComputedStyle( element ).direction );
		const rtlOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
		const rtlBounds = await timeline.evaluate( ( element ) => {
			const rect = element.getBoundingClientRect();
			const content = document.getElementById( 'wpcontent' );
			const contentRect = content ? content.getBoundingClientRect() : { left: 0, right: window.innerWidth };
			return { start: rect.left, end: rect.right, contentStart: contentRect.left, contentEnd: contentRect.right };
		} );
		check(
			rtlDirection === 'rtl'
			&& !rtlOverflow
			&& rtlBounds.start >= rtlBounds.contentStart - 1
			&& rtlBounds.end <= rtlBounds.contentEnd + 1,
			expectNativeRtl
				? 'Activity Log stays inside the native RTL WordPress content rectangle without clipping'
				: 'Activity Log stays inside the WordPress content rectangle when rendered synthetic RTL'
		);
		await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-logs-rtl.png' ), fullPage: true } );
		if ( !expectNativeRtl ) {
			await page.locator( 'html' ).evaluate( ( html, dir ) => {
				if ( dir ) { html.setAttribute( 'dir', dir ); } else { html.removeAttribute( 'dir' ); }
			}, originalDir );
		}

		await page.setViewportSize( { width: 390, height: 844 } );
		await page.goto( activityUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
		await root.waitFor( { state: 'attached', timeout: 10000 } );
		const mobileOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
		check(
			( activityLogExpectEmpty ? await root.locator( '[data-sp-activity-empty]' ).count() === 1 : await root.locator( '[data-sp-activity-row]' ).count() >= 1 )
			&& !mobileOverflow,
			'Activity Log keeps its timeline rows inside the 390px viewport without overflow'
		);
		await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-logs-mobile.png' ), fullPage: true } );
	} finally {
		await page.unroute( '**/aisaautopilot/v1/activity**', activityRoute );
	}
}

async function healthHealthy( page ) {
	console.log( 'CHECK Health interaction, RTL and mobile layout' );
	const healthUrl = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-connections';
	const fixtureChecks = [
		{ id: 'provider', state: 'Good', detail: '<strong>Saved provider fixture</strong>', tone: 'ok' },
		{ id: 'api_key', state: 'Good', detail: 'Accepted by the fixture.', tone: 'ok' },
		{ id: 'key_storage', state: 'Good', detail: 'Encrypted fixture storage.', tone: 'ok' },
		{ id: 'embeddings', state: 'Limited', detail: 'Fixture vectors are catching up.', tone: 'warn' },
		{ id: 'cron', state: 'Good', detail: 'Fixture cron is enabled.', tone: 'ok' },
		{ id: 'index_freshness', state: 'Catching up', detail: 'Fixture index is catching up.', tone: 'warn' },
		{ id: 'rate_limit', state: 'Not measured', detail: 'Fixture quota is not measured.', tone: 'idle' },
		{ id: 'runtime', state: 'Good', detail: 'Fixture runtime is supported.', tone: 'ok' },
	];

	await page.setViewportSize( { width: 1440, height: 960 } );
	await page.goto( healthUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	const root = page.locator( '[data-sp-connections]' );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	const rows = root.locator( '[data-sp-health-row]' );
	const rowIds = await rows.evaluateAll( ( elements ) => elements.map( ( element ) => element.getAttribute( 'data-sp-health-id' ) ) );
	check(
		await root.locator( '[data-sp-health-list]' ).count() === 1
		&& await rows.count() === 8
		&& JSON.stringify( rowIds ) === JSON.stringify( [ 'provider', 'api_key', 'key_storage', 'embeddings', 'cron', 'index_freshness', 'rate_limit', 'runtime' ] ),
		'Health keeps the exact one-card, eight-row mockup order'
	);

	let releaseResponse;
	const heldResponse = new Promise( ( resolve ) => { releaseResponse = resolve; } );
	let routeStarted = false;
	let finishRoute;
	const routeFinished = new Promise( ( resolve ) => { finishRoute = resolve; } );
	let nonceSeen = false;
	const heldRoute = async ( route ) => {
		routeStarted = true;
		nonceSeen = !!route.request().headers()[ 'x-wp-nonce' ];
		try {
			await heldResponse;
			await route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( {
					ok: true,
					code: 'ok',
					report: { checked_at: 1, checks: fixtureChecks },
				} ),
			} );
		} finally {
			finishRoute();
		}
	};

	await page.route( '**/health/diagnostics', heldRoute );
	try {
		const button = page.locator( '#sp-run-diagnostics' );
		await button.click();
		await page.waitForFunction( () => document.getElementById( 'sp-run-diagnostics' ).disabled, undefined, { timeout: 10000 } );
		const sequenceActive = await root.evaluate( ( element ) => {
			const node = document.getElementById( 'sp-conn-i18n' );
			let strings = {};
			try {
				strings = node ? JSON.parse( node.textContent || '{}' ) : {};
			} catch ( error ) {}
			const states = Array.from( element.querySelectorAll( '[data-sp-health-state]' ) ).map( ( state ) => state.textContent || '' );
			return states.includes( strings.checking || 'Checking…' ) || states.includes( strings.queued || 'Queued' );
		} );
		check(
			await button.getAttribute( 'aria-busy' ) === 'true'
			&& await root.getAttribute( 'aria-busy' ) === 'true'
			&& sequenceActive,
			'Health locks its action and exposes the queued/checking mockup sequence while the protected request is pending'
		);
		await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-health-checking-desktop.png' ), fullPage: true } );
	} finally {
		releaseResponse();
		if ( routeStarted ) {
			await routeFinished;
		}
		await page.unroute( '**/health/diagnostics', heldRoute );
	}

	await page.waitForFunction(
		() => {
			const button = document.getElementById( 'sp-run-diagnostics' );
			const state = document.querySelector( '[data-sp-health-id="provider"] [data-sp-health-state]' );
			return !!button && !button.disabled && !!state && state.textContent === 'Good';
		},
		undefined,
		{ timeout: 10000 }
	);
	const providerDetail = root.locator( '[data-sp-health-id="provider"] [data-sp-health-detail]' );
	check(
		nonceSeen
		&& await root.getAttribute( 'aria-busy' ) === 'false'
		&& await providerDetail.textContent() === '<strong>Saved provider fixture</strong>'
		&& await providerDetail.locator( 'strong' ).count() === 0,
		'Health sends a nonce-protected request, unlocks after it finishes and applies returned details as text rather than HTML'
	);
	// The interception above proves safe rendering. Reload before visual checks so
	// release screenshots always show the site's truthful, non-fixture report.
	await page.goto( healthUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-health-desktop.png' ), fullPage: true } );

	let originalDir = null;
	if ( expectNativeRtl ) {
		check(
			await page.locator( 'html' ).getAttribute( 'dir' ) === 'rtl',
			'Health runs inside a genuinely RTL WordPress admin shell'
		);
	} else {
		originalDir = await page.locator( 'html' ).getAttribute( 'dir' );
		await page.locator( 'html' ).evaluate( ( html ) => html.setAttribute( 'dir', 'rtl' ) );
		await page.waitForTimeout( 120 );
	}
	const rtlDirection = await root.evaluate( ( element ) => getComputedStyle( element ).direction );
	const rtlOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	const rtlBounds = await root.locator( '[data-sp-health-list]' ).evaluate( ( list ) => {
		const rect = list.getBoundingClientRect();
		const content = document.getElementById( 'wpcontent' );
		const contentRect = content ? content.getBoundingClientRect() : { left: 0, right: window.innerWidth };
		return { start: rect.left, end: rect.right, contentStart: contentRect.left, contentEnd: contentRect.right };
	} );
	check(
		rtlDirection === 'rtl'
		&& !rtlOverflow
		&& rtlBounds.start >= rtlBounds.contentStart - 1
		&& rtlBounds.end <= rtlBounds.contentEnd + 1,
		expectNativeRtl
			? 'Health stays inside the native RTL WordPress content rectangle without clipping'
			: 'Health stays inside the WordPress content rectangle when rendered synthetic RTL'
	);
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-health-rtl.png' ), fullPage: true } );
	if ( !expectNativeRtl ) {
		await page.locator( 'html' ).evaluate( ( html, dir ) => {
			if ( dir ) {
				html.setAttribute( 'dir', dir );
			} else {
				html.removeAttribute( 'dir' );
			}
		}, originalDir );
	}

	await page.setViewportSize( { width: 390, height: 844 } );
	await page.goto( healthUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	const mobileOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
	check(
		await root.locator( '[data-sp-health-row]' ).count() === 8
		&& !mobileOverflow,
		'Health keeps all eight rows within the 390px viewport without overflow'
	);
	await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-health-mobile.png' ), fullPage: true } );
}

( async () => {
	if ( !username || !password ) {
		throw new Error( 'AISAAUTOPILOT_BROWSER_USER and AISAAUTOPILOT_BROWSER_PASSWORD are required.' );
	}
	fs.mkdirSync( screenshotDir, { recursive: true } );
	console.log( 'START browser QA for ' + checkedPages.length + ' admin route(s)' );
	let browser;
	try {
		browser = await chromium.launch( {
			headless: true,
			executablePath: chromePath,
			args: [ '--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream' ],
		} );
		const context = await browser.newContext( { viewport: { width: 1440, height: 960 }, permissions: [ 'microphone' ] } );
		const page = await context.newPage();
		const aisaautopilotConsoleErrors = [];
		const aisaautopilotPageErrors = [];
		page.on( 'console', ( msg ) => {
			if ( msg.type() === 'error' && msg.location().url.includes( '/ai-site-assistant-autopilot' ) ) {
				aisaautopilotConsoleErrors.push( msg.text() );
			}
		} );
		page.on( 'pageerror', ( error ) => {
			 aisaautopilotPageErrors.push( {
				message: error.message,
				stack: error.stack || '',
				url: page.url(),
			} );
		} );

		console.log( 'LOGIN' );
		await page.goto( baseUrl + '/wp-login.php', { waitUntil: 'domcontentloaded', timeout: 20000 } );
		await page.locator( '#user_login' ).fill( username );
		await page.locator( '#user_pass' ).fill( password );
		await page.locator( '#wp-submit' ).click();
		await page.waitForTimeout( 700 );
		const remindLater = page.locator( 'a[href*="action=confirm_admin_email"][href*="remind_me_later"]' );
		if ( await remindLater.count() ) {
			console.log( 'DISMISS WordPress admin-email reminder for temporary QA user' );
			await remindLater.click();
			await page.waitForTimeout( 700 );
		}
		if ( await page.locator( '#login_error' ).count() || page.url().includes( '/wp-login.php' ) ) {
			const loginText = await page.locator( 'body' ).innerText().catch( () => '' );
			throw new Error( 'WordPress rejected the temporary browser-test credentials: ' + loginText.replace( /[\r\n]+/g, ' ' ).slice( 0, 300 ) );
		}
		if ( !page.url().includes( '/wp-admin/' ) ) {
			throw new Error( 'WordPress did not redirect the temporary browser-test user into wp-admin.' );
		}
		await page.locator( '#wpadminbar' ).waitFor( { state: 'attached', timeout: 10000 } );

		for ( const slug of checkedPages ) {
			await pageHealthy( page, slug );
		}
		if ( checkedPages.includes( 'aisaautopilot-home' ) ) {
			await overviewHealthy( page );
		}
		if ( checkedPages.includes( 'aisaautopilot-usage' ) ) {
			await usageHealthy( page );
		}
		if ( checkedPages.includes( 'aisaautopilot-logs' ) ) {
			await activityLogHealthy( page );
		}
		if ( checkedPages.includes( 'aisaautopilot-connections' ) ) {
			await healthHealthy( page );
		}
		if ( checkedPages.includes( 'aisaautopilot-setup' ) ) {
			await setupHealthy( page );
		}
		if ( checkedPages.includes( 'aisaautopilot-integrations' ) ) {
			await integrationsHealthy( page );
		}
		if ( checkedPages.includes( 'aisaautopilot-kb' ) ) {
			if ( quickKbChecks ) {
				await knowledgeResumeQuickHealthy( page );
			} else {
				await knowledgeHealthy( page );
			}
		}
		if ( checkedPages.includes( 'aisaautopilot-wc-dashboard' ) ) {
			await storeCopilotHealthy( page );
		}
		if ( checkedPages.includes( 'aisaautopilot-form-builder' ) ) {
			if ( process.env.AISAAUTOPILOT_BROWSER_FORM_COMPOSER_ONLY !== '1' ) {
				await contentStudioNavigationHealthy( page );
			}
			await formComposerHealthy( page );
		}
		if ( checkedPages.includes( 'aisaautopilot-conversations' ) ) {
			if ( answerDeskChecks.includes( 'all' ) || answerDeskChecks.includes( 'conversations' ) ) {
				await answerConversationsHealthy( page );
			}
			if ( answerDeskChecks.includes( 'all' ) || answerDeskChecks.includes( 'widget' ) ) {
				await answerWidgetHealthy( page );
			}
			if ( answerDeskChecks.includes( 'all' ) || answerDeskChecks.includes( 'handoff' ) ) {
				await answerHandoffHealthy( page );
			}
			if ( answerDeskChecks.includes( 'all' ) || answerDeskChecks.includes( 'voice' ) ) {
				await answerVoiceHealthy( page );
			}
		}

		if ( runSpecialistChecks ) {
			console.log( 'CHECK specialist surfaces' );
			await page.setViewportSize( { width: 1440, height: 960 } );
			await page.goto( baseUrl + '/wp-admin/admin.php?page=aisaautopilot-form-builder&mode=forms', { waitUntil: 'domcontentloaded', timeout: 20000 } );
			await page.locator( '#aisaautopilot-compose-screen' ).waitFor( { state: 'attached', timeout: 10000 } );
			check( await page.locator( '#aisaautopilot-compose-screen' ).count() === 1, 'form builder has one compose screen' );
			check( await page.locator( '#aisaautopilot-review-screen' ).count() === 1, 'form builder has one review screen' );
			check( await page.locator( '#aisaautopilot-result-screen' ).count() === 1, 'form builder has one result screen' );
			check( await page.locator( '#aisaautopilot-compose-screen' ).isVisible(), 'form builder starts on the compose screen only' );
			check( !( await page.locator( '#aisaautopilot-review-screen' ).isVisible() ), 'form builder review is not duplicated or initially visible' );
			await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-form-builder-desktop.png' ), fullPage: true } );

			await page.setViewportSize( { width: 390, height: 844 } );
			await page.goto( baseUrl + '/wp-admin/admin.php?page=aisaautopilot-form-builder&mode=forms', { waitUntil: 'domcontentloaded', timeout: 20000 } );
			await page.locator( '#aisaautopilot-compose-screen' ).waitFor( { state: 'attached', timeout: 10000 } );
			const mobileOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
			check( !mobileOverflow, 'form builder has no horizontal overflow at 390px width' );
			await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-form-builder-mobile.png' ), fullPage: true } );

			const publicPage = await context.newPage();
			await publicPage.goto( baseUrl + '/', { waitUntil: 'domcontentloaded', timeout: 20000 } );
			await publicPage.locator( '#spw-toggle' ).waitFor( { state: 'attached', timeout: 10000 } );
			check( await publicPage.locator( '#spw-toggle' ).count() === 1, 'public widget renders one launcher' );
			check( await publicPage.evaluate( () => 'speechSynthesis' in window ), 'browser exposes text-to-speech capability for widget responses' );
			let voiceRequest = false;
			publicPage.on( 'request', ( request ) => {
				if ( request.url().includes( '/aisaautopilot/v1/public/voice' ) && request.method() === 'POST' ) { voiceRequest = true; }
			} );
			await publicPage.locator( '#spw-toggle' ).click();
			await publicPage.locator( '#spw-mic' ).click();
			await publicPage.waitForTimeout( 700 );
			await publicPage.locator( '#spw-mic' ).click();
			await publicPage.waitForTimeout( 1800 );
			check( voiceRequest, 'widget microphone records and posts only to the safe public voice endpoint' );
			const publicOverflow = await publicPage.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
			check( !publicOverflow, 'public widget has no desktop horizontal overflow' );
		} else {
			console.log( 'SKIP specialist surfaces by AISAAUTOPILOT_BROWSER_SKIP_SPECIALIST' );
		}

		check( aisaautopilotConsoleErrors.length === 0, 'AISAAutoPilot pages produce no AISAAutoPilot JavaScript console errors' );
		check( aisaautopilotPageErrors.length === 0, 'AISAAutoPilot admin pages produce no uncaught page errors' );
		if ( aisaautopilotPageErrors.length ) {
			console.log( 'PAGE_ERRORS ' + aisaautopilotPageErrors.map( ( item ) => ( item.url + ' :: ' + item.message + ' :: ' + item.stack ).replace( /[\r\n]+/g, ' ' ) ).join( ' | ' ) );
		}
		console.log( 'COMPLETE browser assertions (' + failures.length + ' failures)' );
	} finally {
		if ( browser ) {
			await browser.close();
		}
	}
	if ( failures.length ) {
		process.exitCode = 1;
	}
} )().catch( ( error ) => {
	console.error( error.stack || error.message );
	process.exitCode = 1;
} );
