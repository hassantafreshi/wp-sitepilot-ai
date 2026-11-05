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
	// D-23 / T54: Form Studio became its own top-level page with a new slug.
	'aisaautopilot-form-studio',
];
const requestedSlugs = ( process.env.AISAAUTOPILOT_BROWSER_SLUGS || '' ).split( ',' ).map( ( slug ) => slug.trim() ).filter( Boolean );
// Lets a wrapper run the default route list minus a slug it covers in a
// separate invocation with its own fixture (T15: Activity Log).
const excludedSlugs = ( process.env.AISAAUTOPILOT_BROWSER_EXCLUDE_SLUGS || '' ).split( ',' ).map( ( slug ) => slug.trim() ).filter( Boolean );
const checkedPages = ( requestedSlugs.length ? requestedSlugs : allCheckedPages ).filter( ( slug ) => !excludedSlugs.includes( slug ) );
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
const handoffInboxFixtureIds = process.env.AISAAUTOPILOT_BROWSER_HANDOFF_INBOX_IDS || '';
const kbAnswersFixture = process.env.AISAAUTOPILOT_BROWSER_KB_ANSWERS_FIXTURE || '';
const widgetCustomiseFixture = process.env.AISAAUTOPILOT_BROWSER_WIDGET_CUSTOMISE_FIXTURE === '1';
const advancedSettingsFixture = process.env.AISAAUTOPILOT_BROWSER_ADVANCED_SETTINGS_FIXTURE === '1';

function check( condition, message, detail ) {
	if ( condition ) {
		console.log( 'PASS ' + message );
	} else {
		console.log( 'FAIL ' + message );
		if ( undefined !== detail ) {
			console.log( '    - ' + JSON.stringify( detail ) );
		}
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
		// Route smoke (T15) also keeps a 390px frame of every route for review.
		// Overflow is printed as a diagnostic for the responsive pass (T17); the
		// surface checks below assert it where a surface owns that contract.
		const desktopViewport = page.viewportSize();
		await page.setViewportSize( { width: 390, height: 844 } );
		try {
			await page.goto( url, { waitUntil: 'domcontentloaded', timeout: 45000 } );
			await page.locator( '.sp-app' ).first().waitFor( { state: 'attached', timeout: 10000 } ).catch( () => {} );
			const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
			console.log( 'ROUTE_390 ' + slug + ' overflow=' + overflow );
			await page.screenshot( { path: path.join( screenshotDir, 'admin-' + slug + '-mobile.png' ), fullPage: true } );
		} finally {
			await page.setViewportSize( desktopViewport || { width: 1440, height: 960 } );
		}
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
	// The monthly spend cap is a visible card on step 4; its number shows only while the cap is on.
	const capSwitch = page.locator( '[data-sp-setup-cap-switch]' );
	const capField = page.locator( '[data-sp-setup-cap-field]' );
	const capVisible = await page.locator( '[data-sp-setup-cap]' ).isVisible();
	const capWasOn = await capSwitch.isChecked();
	await capSwitch.setChecked( ! capWasOn, { force: true } );
	const fieldAfterToggle = await capField.isVisible();
	await capSwitch.setChecked( capWasOn, { force: true } );
	check(
		capVisible && fieldAfterToggle === ! capWasOn && await capField.isVisible() === capWasOn
		&& Number( await capField.locator( 'input' ).inputValue() ) >= 10000,
		'Setup step 4 shows the monthly spend cap with its token number, which follows the switch'
	);
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
			// Assert the scope note by its hook and in the admin language: fa_IR translates the English copy.
			const scopeNote = page.locator( selector ).locator( '[data-sp-kb-queue-scope="browser-session"]' );
			const scopeText = await scopeNote.count() === 1 ? ( await scopeNote.textContent() || '' ).trim() : '';
			const persianAdmin = /^fa/i.test( await page.locator( 'html' ).getAttribute( 'lang' ) || '' );
			truthfulQueue = '' !== scopeText
				&& ( persianAdmin ? /[\u0600-\u06FF]/.test( scopeText ) : 'Browser session only' === scopeText )
				&& await page.locator( selector ).innerText().then( ( text ) => !text.includes( 'Retry the 3 failed' ) );
		}
	}
	check( truthfulQueue, 'Site Knowledge Queue does not imitate an unsupported persistent retry state' );

	// Indexed content: a refused list request shows the error with Try again instead of an endless loading state.
	// The refusal is answered with HTTP 200 and ok:false so the check exercises the UI path without an HTTP failure.
	let refuseList = true;
	const kbListRoute = ( route ) => route.fulfill( { status: 200, contentType: 'application/json', body: JSON.stringify( refuseList ? { ok: false, message: 'Refused' } : { ok: true, items: [], page: 1, pages: 1, total: 0 } ) } );
	await page.route( '**/aisaautopilot/v1/kb/list**', kbListRoute );
	try {
		await page.goto( knowledgeUrl + '&tab=entries', { waitUntil: 'domcontentloaded', timeout: 20000 } );
		const listError = page.locator( '[data-sp-kb-list-error]' );
		await listError.waitFor( { state: 'visible', timeout: 10000 } );
		check(
			1 === await listError.locator( '[data-sp-kb-list-retry]' ).count() && 'alert' === await listError.getAttribute( 'role' ) && 0 === await page.locator( '[data-sp-kb-list-loading]' ).count(),
			'Indexed content shows a failed list load with Try again instead of an endless loading state'
		);
		refuseList = false;
		const retried = page.waitForResponse( ( response ) => response.url().includes( '/kb/list' ) );
		await listError.locator( '[data-sp-kb-list-retry]' ).click();
		await retried;
		await page.waitForFunction( () => ! document.querySelector( '[data-sp-kb-list-error]' ) && ! document.querySelector( '[data-sp-kb-list-loading]' ) );
		check( 0 === await page.locator( '[data-sp-kb-list-error]' ).count(), 'Try again reloads the list and clears the error' );
	} finally {
		await page.unroute( '**/aisaautopilot/v1/kb/list**', kbListRoute );
	}
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
	const whatsapp = page.locator( '[data-sp-handoff-card][data-sp-handoff-channel="whatsapp"]' );
	const slack = page.locator( '[data-sp-handoff-card][data-sp-handoff-channel="slack"]' );
	const signal = page.locator( '[data-sp-handoff-card][data-sp-handoff-channel="signal"]' );
	check(
		await email.count() === 1
		&& await telegram.count() === 1
		&& await twilio.count() === 1
		&& await whatsapp.count() === 1
		&& await slack.count() === 1
		&& await signal.count() === 1
		&& await page.locator( '[data-sp-handoff-unsupported="whatsapp"]' ).count() === 0
		&& await page.locator( '[data-sp-handoff-unsupported="slack"]' ).count() === 0
		&& await page.locator( '[data-sp-handoff-unsupported="discord"]' ).count() === 1
		&& await page.locator( '[data-sp-handoff-unsupported="webhook"]' ).count() === 1,
		'Answer Desk Human handoff shows real channel cards and locks unsupported mockup transports'
	);
	const handoffText = await root.innerText();
	// Assert the delivery note by its hook and in the admin language: fa_IR translates the English copy.
	const deliveryNote = page.locator( '[data-sp-handoff-delivery-mode="fan-out"]' );
	const deliveryText = await deliveryNote.count() === 1 ? ( await deliveryNote.textContent() || '' ).trim() : '';
	const persianAdmin = /^fa/i.test( await page.locator( 'html' ).getAttribute( 'lang' ) || '' );
	check(
		'' !== deliveryText
		&& ( persianAdmin ? /[\u0600-\u06FF]/.test( deliveryText ) : deliveryText.includes( 'every enabled and configured channel' ) && deliveryText.includes( 'does not use an ordered fallback queue' ) )
		&& !handoffText.includes( 'Send a test message' )
		&& await telegram.locator( '[data-sp-handoff-test]' ).isDisabled(),
		'Answer Desk Human handoff describes actual fan-out delivery and keeps an unconfigured provider check disabled'
	);

	const inboxStrip = page.locator( '[data-sp-handoff-inbox-strip]' );
	check(
		1 === await inboxStrip.count()
		&& [ 'waiting', 'quiet', 'unavailable' ].includes( await inboxStrip.getAttribute( 'data-sp-handoff-inbox-strip' ) )
		&& ( await inboxStrip.locator( 'a[data-sp-handoff-inbox-link]' ).getAttribute( 'href' ) || '' ).includes( 'page=aisaautopilot-handoffs' ),
		'Human handoff shows the inbox strip with its real state and a link to the inbox'
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
		// The state label is set by JS from translated strings, so fa_IR shows it in Persian.
		&& ( persianAdmin ? /[\u0600-\u06FF]/.test( await email.locator( '[data-sp-handoff-state]' ).textContent() || '' ) : await email.locator( '[data-sp-handoff-state]' ).textContent() === 'Paused' ),
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
	check( 'unavailable' !== await page.locator( '[data-sp-handoff-inbox-strip]' ).getAttribute( 'data-sp-handoff-inbox-strip' ), 'The inbox strip stops saying visitors are not offered a person once a channel is ready' );

	const disableResponsePromise = page.waitForResponse( ( response ) => response.url().includes( '/handoff/admin-settings' ) && response.request().method() === 'POST' && response.ok() );
	await email.locator( '[data-sp-handoff-toggle]' ).click();
	await disableResponsePromise;
	await page.waitForFunction( () => document.querySelector( '[data-sp-handoff-channel="email"]' ).getAttribute( 'data-sp-handoff-enabled' ) === '0' );
	check( await root.getAttribute( 'data-sp-handoff-available' ) === '0', 'Answer Desk Human handoff can pause its only ready channel without a full-form reset' );
	check( 'unavailable' === await page.locator( '[data-sp-handoff-inbox-strip]' ).getAttribute( 'data-sp-handoff-inbox-strip' ), 'The inbox strip says visitors are not offered a person when the only ready channel is paused' );

	const restoreResponsePromise = page.waitForResponse( ( response ) => response.url().includes( '/handoff/admin-settings' ) && response.request().method() === 'POST' && response.ok() );
	await email.locator( '[data-sp-handoff-toggle]' ).click();
	await restoreResponsePromise;
	await page.waitForFunction( () => document.querySelector( '[data-sp-handoff-channel="email"]' ).getAttribute( 'data-sp-handoff-enabled' ) === '1' );
	check( await root.getAttribute( 'data-sp-handoff-available' ) === '1', 'Answer Desk Human handoff can restore its ready channel without altering the recipient' );

	// Details the visitor's handoff card asks for: Off, Optional or Required per detail.
	const details = page.locator( '[data-sp-handoff-details-form]' );
	check(
		4 === await details.locator( '[role="radiogroup"]' ).count()
		&& 12 === await details.locator( 'input[type="radio"]' ).count()
		&& await details.locator( 'input[name="name"][value="off"]' ).isChecked()
		&& await details.locator( 'input[name="email"][value="off"]' ).isChecked(),
		'Human handoff lists four visitor details with Off, Optional and Required, all off by default'
	);
	await details.locator( 'input[name="name"][value="off"]' ).focus();
	// ArrowDown moves to the next radio in both directions of text.
	await page.keyboard.press( 'ArrowDown' );
	await page.keyboard.press( 'ArrowDown' );
	const detailFocus = await page.evaluate( () => {
		const input = document.activeElement;
		const label = input && input.nextElementSibling ? getComputedStyle( input.nextElementSibling ) : null;
		return { value: input ? input.value : '', outline: label ? label.outlineStyle : 'none' };
	} );
	check(
		'required' === detailFocus.value && 'none' !== detailFocus.outline && await details.locator( 'input[name="name"][value="required"]' ).isChecked(),
		'Arrow keys choose a mode inside a detail and the focused choice shows a visible outline'
	);
	await details.locator( 'input[name="phone"][value="optional"]' ).check();
	const detailsResponsePromise = page.waitForResponse( ( response ) => response.url().includes( '/handoff/visitor-fields' ) && 'POST' === response.request().method() );
	await details.locator( '[data-sp-handoff-details-save]' ).click();
	const detailsResponse = await detailsResponsePromise;
	const detailsBody = JSON.parse( detailsResponse.request().postData() || '{}' );
	await details.locator( '[data-sp-handoff-details-status][data-state="saved"]' ).waitFor( { state: 'visible', timeout: 10000 } );
	check(
		detailsResponse.ok()
		&& !! detailsResponse.request().headers()[ 'x-wp-nonce' ]
		&& JSON.stringify( detailsBody.fields ) === JSON.stringify( { name: 'required', email: 'off', phone: 'optional', website: 'off' } ),
		'Save details sends all four choices through the nonce-protected route and confirms the save'
	);
	if ( ! skipScreenshots ) {
		await details.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-handoff-details.png' ) } );
	}
	await page.reload( { waitUntil: 'domcontentloaded', timeout: 20000 } );
	await root.waitFor( { state: 'attached', timeout: 10000 } );
	check(
		await details.locator( 'input[name="name"][value="required"]' ).isChecked() && await details.locator( 'input[name="phone"][value="optional"]' ).isChecked(),
		'The saved visitor details survive a reload'
	);

	// The visitor's card on the public site, with delivery mocked: nothing reaches a real channel.
	const visitor = await page.context().newPage();
	const handoffBodies = [];
	await visitor.route( ( url ) => url.href.includes( 'aisaautopilot/v1/widget/handoff' ), async ( route ) => {
		handoffBodies.push( JSON.parse( route.request().postData() || '{}' ) );
		await route.fulfill( { status: 200, contentType: 'application/json', body: JSON.stringify( { ok: true, code: 'sent', message: 'Your request has been sent to the support team.', reference: 4242 } ) } );
	} );
	try {
		await visitor.setViewportSize( { width: 390, height: 844 } );
		await visitor.goto( baseUrl + '/', { waitUntil: 'domcontentloaded', timeout: 20000 } );
		await visitor.locator( '#spw-toggle' ).click();
		await visitor.locator( '.spw-footnote [data-spw-handoff]' ).click();
		const card = visitor.locator( '#spw-msgs .spw-handoff' ).last();
		await card.waitFor( { state: 'visible', timeout: 10000 } );
		const nameInput = card.locator( '[data-spw-handoff-field="name"]' );
		const phoneInput = card.locator( '[data-spw-handoff-field="phone"]' );
		check(
			2 === await card.locator( '[data-spw-handoff-field]' ).count()
			&& null !== await nameInput.getAttribute( 'required' )
			&& null === await phoneInput.getAttribute( 'required' )
			&& 'tel' === await phoneInput.getAttribute( 'type' )
			&& 'tel' === await phoneInput.getAttribute( 'autocomplete' ),
			'The visitor handoff card asks only for the saved details, with a required name and an optional phone input'
		);
		await card.locator( '[data-spw-handoff-send]' ).click();
		check(
			0 === handoffBodies.length
			&& 'true' === await nameInput.getAttribute( 'aria-invalid' )
			&& await card.locator( '[data-spw-handoff-error]' ).first().isVisible()
			&& await nameInput.evaluate( ( node ) => node === document.activeElement && !! document.getElementById( node.getAttribute( 'aria-describedby' ) || '' ) ),
			'An empty required detail is flagged on its field, described for screen readers and focused, and nothing is sent'
		);
		await nameInput.fill( 'Browser QA visitor' );
		await phoneInput.fill( 'call me' );
		await card.locator( '[data-spw-handoff-send]' ).click();
		check(
			0 === handoffBodies.length && 'true' === await phoneInput.getAttribute( 'aria-invalid' ) && null === await nameInput.getAttribute( 'aria-invalid' ),
			'An invalid mobile number is flagged before anything is sent'
		);
		await phoneInput.fill( '۰۹۱۲ ۳۴۵ ۶۷۸۹' );
		await phoneInput.press( 'Enter' );
		await visitor.waitForFunction( () => !! document.querySelector( '#spw-msgs .spw-handoff.is-sent' ) );
		const sentBody = handoffBodies[ 0 ] || {};
		check(
			1 === handoffBodies.length
			&& 'Browser QA visitor' === sentBody.name
			&& '۰۹۱۲ ۳۴۵ ۶۷۸۹' === sentBody.phone
			&& ! ( 'email' in sentBody ) && ! ( 'website' in sentBody )
			&& Array.isArray( sentBody.messages ),
			'Enter sends the conversation once with only the asked-for details'
		);
		const cardLayout = await card.evaluate( ( node ) => {
			const rect = node.getBoundingClientRect();
			return { inside: rect.left >= 0 && rect.right <= window.innerWidth + 1, overflow: document.documentElement.scrollWidth > window.innerWidth + 1 };
		} );
		check( cardLayout.inside && ! cardLayout.overflow, 'The visitor handoff card with details fits a 390px screen without horizontal overflow' );
		if ( ! skipScreenshots ) {
			await card.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-handoff-visitor-card-mobile.png' ) } );
		}
	} finally {
		await visitor.close();
	}

	const addSms = page.locator( '[data-sp-handoff-add="twilio"]' );
	await addSms.click();
	check( await twilio.isVisible() && await twilio.locator( '[data-sp-handoff-form]' ).isVisible(), 'Answer Desk Human handoff reveals the real SMS transport only when an administrator adds it' );
	check( await whatsapp.isVisible(), 'Answer Desk Human handoff shows the WhatsApp Business card without adding it' );
	for ( const [ added, name ] of [ [ slack, 'Slack' ], [ signal, 'Signal' ] ] ) {
		const id = await added.getAttribute( 'data-sp-handoff-channel' );
		const wasHidden = await added.isHidden();
		await page.locator( '[data-sp-handoff-add="' + id + '"]' ).click();
		check(
			wasHidden && await added.isVisible() && await added.locator( '[data-sp-handoff-form]' ).isVisible()
			&& await page.locator( '[data-sp-handoff-add="' + id + '"]' ).isHidden(),
			'Answer Desk Human handoff reveals the ' + name + ' card, open for setup, only when an administrator adds it'
		);
		await added.locator( '[data-sp-handoff-cancel]' ).click();
	}
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
		JSON.stringify( tabIds ) === JSON.stringify( [ 'newproduct', 'capabilities', 'products', 'descriptions', 'orders' ] ),
		'Store Copilot renders its five tabs in order (the mockup four plus Descriptions)'
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
		&& await page.locator( '[data-sp-store-setting]' ).count() === 5,
		'Store Copilot What it can do keeps the compact capability, status, and five-live-setting panels (connector, search, descriptions, count carts, cart reminders)'
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
	check( await page.locator( '[data-sp-subtab]' ).count() === 5, 'Store Copilot keeps all five tabs at 390px width' );
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
	// Copy is locale-aware (T15): fa_IR translates these strings, so Persian
	// admin asserts Persian script per item and English admin the exact copy.
	const persianAdmin = /^fa/i.test( await page.locator( 'html' ).getAttribute( 'lang' ) || '' );
	const persianScript = /[\u0600-\u06FF]/;
	const chipTexts = voiceChips.map( ( chip ) => chip.trim() );
	const planLabel = ( await planButton.textContent() || '' ).trim();
	check(
		await root.locator( '[data-content-empty]' ).count() === 1
		&& await root.locator( '[data-content-recent]' ).count() === 1
		&& await prompt.getAttribute( 'maxlength' ) === '6000'
		&& await prompt.getAttribute( 'rows' ) === '4'
		&& ( persianAdmin
			? chipTexts.length === 3 && chipTexts.every( ( chip ) => persianScript.test( chip ) )
			: JSON.stringify( chipTexts ) === JSON.stringify( [ 'Learned from your site', 'Formal', 'Plain' ] ) )
		&& ( persianAdmin ? persianScript.test( planLabel ) : planLabel === 'Write a draft' ),
		'Content Studio Write matches the mockup composer, voice chips, and truthful draft-list shell'
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
	let workflowTone = '';
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
			if ( body.prompt === workflowBrief ) {
				workflowTone = body.tone || '';
			}
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
						tone: body.tone || 'site',
						tone_label: 'Browser QA voice ' + ( body.tone || 'site' ),
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
	// Voice (T32): a radio group; arrows (mirrored in RTL), Home and End move the choice.
	const voiceRadios = root.locator( '[data-content-voice] [role="radio"]' );
	await voiceRadios.evaluateAll( ( radios ) => radios.forEach( ( radio ) => { radio.disabled = false; } ) );
	const voiceState = () => voiceRadios.evaluateAll( ( radios ) => radios.map( ( radio ) => [ radio.dataset.contentVoiceOption, radio.getAttribute( 'aria-checked' ), radio.tabIndex, radio === document.activeElement ] ) );
	const rtlVoice = await root.locator( '[data-content-voice]' ).evaluate( ( group ) => 'rtl' === getComputedStyle( group ).direction );
	await root.locator( '[data-content-voice] [aria-checked="true"]' ).focus();
	await page.keyboard.press( 'Home' );
	const atHome = await voiceState();
	await page.keyboard.press( rtlVoice ? 'ArrowLeft' : 'ArrowRight' );
	const afterNext = await voiceState();
	await page.keyboard.press( 'End' );
	await page.keyboard.press( rtlVoice ? 'ArrowLeft' : 'ArrowRight' );
	const wrapped = await voiceState();
	await page.keyboard.press( rtlVoice ? 'ArrowLeft' : 'ArrowRight' );
	const onlyChecked = ( state, id ) => state.every( ( [ option, checked, tab, focused ] ) => ( option === id ) === ( 'true' === checked && 0 === tab && focused ) );
	check(
		'radiogroup' === await root.locator( '[data-content-voice]' ).getAttribute( 'role' ) && 3 === await voiceRadios.count()
		&& onlyChecked( atHome, 'site' ) && onlyChecked( afterNext, 'formal' ) && onlyChecked( wrapped, 'site' ),
		'Content Studio voice is a radio group: Home, the forward arrow (mirrored in RTL) and End move the choice and focus, wrapping at the end',
		{ atHome, afterNext, wrapped }
	);
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
	check(
		'formal' === workflowTone && 'Browser QA voice formal' === ( await preview.locator( '[data-content-voice-label]' ).textContent() || '' ).trim(),
		'Content Studio sends the chosen voice with the plan request and the preview names it',
		{ workflowTone }
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
		&& ( persianAdmin
			? templateLabels.length === 4 && templateLabels.every( ( label ) => persianScript.test( label.trim() ) )
			: JSON.stringify( templateLabels.map( ( label ) => label.trim() ) ) === JSON.stringify( [ 'Product description', 'Blog post outline', 'Meta description', 'Support answer' ] ) )
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
	// The Persian brief keeps the `{product}` placeholder untranslated.
	const templateBridgeSafe = ( persianAdmin
		? persianScript.test( templatePrefill ) && templatePrefill.includes( '{product}' )
		: templatePrefill === 'Write 60–90 words for {product}. Lead with the material, then the use. No superlatives.' )
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
	console.log( 'CHECK Form Studio workspace: gallery, plan, revision by message, approval, result, RTL and phone tabs' );
	// The menu opens the canonical page; the old Content Studio address is kept as an alias. Both
	// must run the workspace script (a release once loaded the wrong one on the canonical page).
	const formUrl = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-form-studio';
	const aliasUrl = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-form-builder&mode=forms';
	const routePattern = '**/wp-json/aisaautopilot/v1/forms/**';
	const planPrompts = [];
	const executedIds = [];
	let executeRequests = 0;
	let plannedStatus = 'draft';
	await page.route( routePattern, async ( route ) => {
		const request = route.request();
		const pathname = new URL( request.url() ).pathname;
		if ( pathname.endsWith( '/forms/plan' ) ) {
			const requestBody = request.postDataJSON();
			planPrompts.push( String( requestBody.prompt || '' ) );
			plannedStatus = /publish/i.test( requestBody.prompt || '' ) ? 'publish' : 'draft';
			const optionalName = /Make .?Name.? optional/i.test( requestBody.prompt || '' );
			await route.fulfill( { contentType: 'application/json', body: JSON.stringify( {
				ok: true,
				action_id: 'form-browser-qa-action-' + planPrompts.length,
				expires_at: Math.floor( Date.now() / 1000 ) + 900,
				preview: {
					title: 'Browser QA contact form', description: 'A review-only browser fixture.',
					fields: [
						{ label: 'Name', type: 'text', required: ! optionalName, options: [] },
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
			executedIds.push( String( ( request.postDataJSON() || {} ).action_id || '' ) );
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
		for ( const [ label, url ] of [ [ 'the canonical Form Studio page', formUrl ], [ 'the Content Studio forms alias', aliasUrl ] ] ) {
			await page.goto( url, { waitUntil: 'domcontentloaded', timeout: 20000 } );
			const booted = await page.waitForFunction( () => {
				const r = document.querySelector( '[data-aisaautopilot-copilot]' );
				return !! r && !! r.getAttribute( 'data-sp-fc-tab' ) && document.querySelectorAll( '#aisaautopilot-thread .aisa-ai-row' ).length > 0 && !! window.AISAAutoPilotDock;
			}, null, { timeout: 15000 } ).then( () => true, () => false );
			check( booted, 'Form Studio runs its workspace script on ' + label );
		}
		await page.goto( formUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
		const root = page.locator( '[data-aisaautopilot-copilot]' );
		await root.waitFor( { state: 'attached', timeout: 10000 } );
		const canvas = root.locator( '[data-sp-form-canvas]' );
		const assistant = root.locator( '[data-sp-form-assistant]' );
		const prompt = root.locator( '#aisaautopilot-prompt' );
		const examples = root.locator( '#aisaautopilot-examples [data-example]' );
		const thread = root.locator( '#aisaautopilot-thread' );
		await page.waitForTimeout( 150 );
		const rects = await Promise.all( [ canvas.boundingBox(), assistant.boundingBox() ] );
		const workBottom = await page.evaluate( () => document.querySelector( '.sp-fc-work' ).getBoundingClientRect().bottom );
		const desktopOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
		const nativeRtl = await page.locator( 'html' ).getAttribute( 'dir' ) === 'rtl';
		check(
			await root.locator( '.sp-fc-header' ).count() === 1
			&& await canvas.count() === 1 && await assistant.count() === 1
			&& await root.locator( '[data-sp-form-empty]' ).isVisible()
			&& await examples.count() === 6 && await root.locator( '[data-sp-form-delivery]' ).count() === 1
			&& !!rects[0] && !!rects[1]
			&& ( nativeRtl ? rects[1].x < rects[0].x : rects[1].x > rects[0].x )
			&& !desktopOverflow,
			'Form Studio is one workspace: a six-example gallery on the canvas and the chat beside it, without desktop overflow'
		);
		check( workBottom <= 960 + 1 && rects[1] && rects[1].height > 500, 'the workspace runs to the bottom of the screen, so the composer is always in view' );
		check( 1 === await thread.locator( '.aisa-ai-row' ).count(), 'the chat opens with one greeting and sends nothing' );
		await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-form-composer-empty-desktop.png' ), fullPage: true } );

		await examples.first().click();
		check( ( await prompt.inputValue() ).length > 10 && planPrompts.length === 0, 'An example only fills the composer with an editable request and makes no provider request' );
		await root.locator( '[data-sp-form-new]' ).click();
		check( await prompt.inputValue() === '' && await prompt.evaluate( ( node ) => document.activeElement === node ), 'New form clears the draft and returns keyboard focus to the composer' );
		await prompt.fill( 'Build a contact form for browser QA.' );
		await prompt.press( 'Control+Enter' );
		const review = root.locator( '#aisaautopilot-review-screen' );
		await review.waitFor( { state: 'visible', timeout: 10000 } );
		const approve = root.locator( '#aisaautopilot-approve' );
		const reviewRects = await Promise.all( [ review.boundingBox(), assistant.boundingBox() ] );
		const ctxText = ( await root.locator( '[data-sp-fc-ctx]' ).innerText() ).trim();
		check(
			planPrompts.length === 1 && executeRequests === 0
			&& await review.locator( '.sp-preview__field' ).count() === 3
			&& await review.locator( '.sp-fc-fieldlist li' ).count() === 3
			&& await approve.count() === 1
			&& await thread.locator( '.aisa-ai-user' ).count() === 1
			&& !!reviewRects[0] && !!reviewRects[1]
			&& ( nativeRtl ? reviewRects[1].x < reviewRects[0].x : reviewRects[1].x > reviewRects[0].x )
			&& /\d+:\d\d/.test( ctxText ),
			'The plan shows its fields beside a full-size preview, the approval sits in the chat, and the chat names the time left'
		);
		// The preview is the visitors' view: it follows the site language and direction, which the
		// boot data names, not the administrator's dashboard language.
		const previewDir = await review.locator( '.sp-preview__form' ).evaluate( ( node ) => ( {
			dir: node.getAttribute( 'dir' ),
			lang: node.getAttribute( 'lang' ),
			site: JSON.parse( document.getElementById( 'sp-copilot-boot' ).textContent ).site,
		} ) );
		check(
			!! previewDir.site && previewDir.dir === ( previewDir.site.rtl ? 'rtl' : 'ltr' ) && previewDir.lang === String( previewDir.site.locale ).replace( '_', '-' ),
			'The preview form carries the site language and direction'
		);
		await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-form-composer-review-desktop.png' ), fullPage: true } );

		// A plan is changed by asking: the next message carries the plan and the change.
		await prompt.fill( 'Make Name optional' );
		await prompt.press( 'Enter' );
		await page.waitForFunction( () => document.querySelectorAll( '[data-aisaautopilot-copilot] .aisa-ai-card.is-retired' ).length >= 1, null, { timeout: 10000 } );
		const revision = planPrompts[ 1 ] || '';
		check(
			planPrompts.length === 2 && /Make Name optional/.test( revision ) && /Browser QA contact form/.test( revision )
			&& await approve.count() === 1 && executeRequests === 0
			&& await review.locator( '.sp-preview__field' ).first().locator( '.sp-preview__req' ).count() === 0,
			'Asking for a change sends the current plan with the change and replaces the plan; the old one can no longer be approved'
		);

		// A reload keeps the plan under review (this tab only, until it is approved, rejected or expires).
		await page.reload( { waitUntil: 'domcontentloaded', timeout: 20000 } );
		await review.waitFor( { state: 'visible', timeout: 10000 } );
		check(
			planPrompts.length === 2 && executeRequests === 0
			&& 1 === await thread.locator( '[data-sp-fc-plan-card][data-sp-fc-restored="1"]' ).count()
			&& 3 === await review.locator( '.sp-preview__field' ).count()
			&& 0 === await review.locator( '.sp-preview__field' ).first().locator( '.sp-preview__req' ).count()
			&& /\d+:\d\d/.test( ( await root.locator( '[data-sp-fc-ctx]' ).innerText() ).trim() ),
			'After a reload the plan under review comes back with its time left, without asking the AI again'
		);

		await approve.click();
		const result = root.locator( '#aisaautopilot-result-screen' );
		await result.waitFor( { state: 'visible', timeout: 10000 } );
		check(
			executeRequests === 1
			&& await result.locator( 'input[value="[aisaautopilot_form id=\\"999998\\"]"]' ).count() === 1
			&& await result.locator( 'a[href*="post=999998"]' ).count() === 1
			&& await result.locator( '[data-sp-form-build-another]' ).count() === 1
			&& await thread.locator( '[data-sp-fc-created="ok"]' ).count() === 1
			&& !( await result.innerText() ).includes( '1,200' ),
			'Explicit approval shows the result on the canvas and says so in the chat'
		);
		check( 'form-browser-qa-action-2' === executedIds[ 0 ], 'The restored plan approves the same proposal the server holds', executedIds );
		await page.waitForTimeout( 350 );
		await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-form-composer-result-desktop.png' ), fullPage: true } );
		await result.locator( '[data-sp-form-build-another]' ).click();
		check( await root.locator( '[data-sp-form-empty]' ).isVisible() && 1 === await thread.locator( '.aisa-ai-row' ).count(), 'Build another returns to the gallery with a fresh chat' );
		await page.reload( { waitUntil: 'domcontentloaded', timeout: 20000 } );
		await root.locator( '#aisaautopilot-thread .aisa-ai-row' ).first().waitFor( { timeout: 10000 } );
		check(
			await root.locator( '[data-sp-form-empty]' ).isVisible() && 0 === await thread.locator( '[data-sp-fc-plan-card]' ).count(),
			'An approved plan is not brought back by a reload'
		);

		await prompt.fill( 'Build and publish a contact form for browser QA.' );
		await prompt.press( 'Control+Enter' );
		await review.waitFor( { state: 'visible', timeout: 10000 } );
		await approve.click();
		const confirm = root.locator( '#sp-confirm' );
		const confirmGo = confirm.locator( '#sp-confirm-go' );
		await confirm.waitFor( { state: 'visible', timeout: 10000 } );
		check(
			planPrompts.length === 3 && executeRequests === 1 && await confirmGo.isDisabled(),
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
			check( nativeRtl, 'Form Studio runs inside a genuinely RTL WordPress admin shell' );
			await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-form-composer-rtl.png' ), fullPage: true } );
		}

		// Phones: the chat and the canvas are two tabs, and the preview keeps the approval at hand.
		await page.setViewportSize( { width: 390, height: 844 } );
		await page.goto( formUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
		await root.waitFor( { state: 'attached', timeout: 10000 } );
		const tabChat = root.locator( 'button[data-sp-fc-tab="chat"]' );
		const tabPreview = root.locator( 'button[data-sp-fc-tab="preview"]' );
		const mobileOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
		check(
			await tabChat.isVisible() && await tabPreview.isVisible()
			&& 'true' === await tabChat.getAttribute( 'aria-selected' )
			&& await assistant.isVisible() && ! await canvas.isVisible()
			&& !mobileOverflow,
			'On a phone Form Studio opens on the Chat tab, inside the 390px viewport'
		);
		await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-form-composer-mobile.png' ), fullPage: true } );
		await prompt.fill( 'Build a contact form for mobile browser QA.' );
		await prompt.press( 'Control+Enter' );
		await approve.waitFor( { state: 'attached', timeout: 10000 } );
		check( await root.locator( '[data-sp-fc-tab-dot]' ).isVisible(), 'a new plan marks the Preview tab' );
		await tabPreview.click();
		const sticky = root.locator( '[data-sp-fc-approve-mobile]' );
		const mobileReviewOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
		check(
			await canvas.isVisible() && ! await assistant.isVisible()
			&& await review.locator( '.sp-preview__field' ).count() === 3
			&& await sticky.isVisible()
			&& !mobileReviewOverflow,
			'The Preview tab shows the plan with the approval fixed at the bottom, without 390px overflow'
		);
		await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-form-composer-review-mobile.png' ), fullPage: true } );
		await sticky.click();
		await result.waitFor( { state: 'visible', timeout: 10000 } );
		const mobileResultOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
		check( executeRequests === 3 && !mobileResultOverflow, 'Approving from the Preview tab creates the form once, without 390px overflow' );
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

	const resetResponsePromise = page.waitForResponse( ( response ) => response.url().includes( '/usage/reset' ) && response.request().method() === 'POST' && response.ok() );
	await page.locator( '[data-sp-usage-reset]' ).click();
	// The reset asks in the shared accessible dialog, not a native confirm().
	const resetDialog = page.locator( '[data-sp-confirm-dialog] [role="alertdialog"]' );
	await resetDialog.waitFor( { state: 'visible', timeout: 10000 } );
	check(
		await page.evaluate( () => !! document.activeElement && document.activeElement.classList.contains( 'sp-btn--ghost' ) ),
		'Usage reset dialog puts focus on the safe Cancel choice first'
	);
	await resetDialog.locator( '.sp-btn--danger-solid' ).click();
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
		// Copy is locale-aware (T15): Persian admin asserts Persian script on the
		// same elements; the legacy-control absence is checked in both languages
		// (the fa_IR PO still carries "All levels" and "Clear log" translations).
		const persianAdmin = /^fa/i.test( await page.locator( 'html' ).getAttribute( 'lang' ) || '' );
		const persianScript = /[\u0600-\u06FF]/;
		const subText = ( await root.locator( '.sp-pagebar__sub' ).first().textContent() || '' ).trim();
		const emptyTitle = activityLogExpectEmpty ? ( await root.locator( '.sp-activity-empty__title' ).first().textContent() || '' ).trim() : '';
		const legacyCopy = [ 'with a way back.', 'Search messages, events, traces', 'All levels', 'Clear log', 'همهٔ سطح‌ها', 'پاک‌سازی لاگ', 'جست‌وجوی پیام، رویداد' ];
		check(
			await timeline.count() === 1
			&& ( activityLogExpectEmpty
				? await rows.count() === 0 && await root.locator( '[data-sp-activity-group]' ).count() === 0
					&& ( persianAdmin ? persianScript.test( emptyTitle ) : emptyTitle === 'No changes yet' )
				: await root.locator( '[data-sp-activity-group]' ).count() >= 1 && await rows.count() >= 1 )
			&& rowContract
			&& ( persianAdmin ? persianScript.test( subText ) : subText === 'Recorded AutoPilot activity, with a real draft link when one is available.' )
			&& legacyCopy.every( ( legacy ) => !text.includes( legacy ) )
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

async function chatConsoleHealthy( page ) {
	console.log( 'CHECK Ask your knowledge base console: list, prefill, answers, voice review, delete dialog, RTL and 390px' );
	const chatUrl = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-chat';
	const chatRoutePattern = '**/aisaautopilot/v1/chat/**';
	const mysqlUtc = ( date ) => date.toISOString().slice( 0, 19 ).replace( 'T', ' ' );
	const now = new Date();
	const yesterday = new Date( now.getTime() - 86400000 );
	let conversations = [
		{ id: 9101, user_id: 1, title: 'Do you ship to Canada?', message_count: 2, created_at: mysqlUtc( now ), updated_at: mysqlUtc( now ) },
		{ id: 9102, user_id: 1, title: 'What is your return window?', message_count: 2, created_at: mysqlUtc( yesterday ), updated_at: mysqlUtc( yesterday ) },
	];
	const stored = {
		9101: [
			{ role: 'user', content: 'Do you ship to Canada?' },
			{ role: 'assistant', content: 'Yes, we ship to **Canada** within five working days.', sources: [ { title: 'Shipping', url: baseUrl + '/shipping/' } ], status: 'answered' },
		],
		9102: [
			{ role: 'user', content: 'What is your return window?' },
			{ role: 'assistant', content: 'I could not find a return policy in your content.', sources: [], status: 'not_found' },
		],
	};
	// Every chat endpoint is answered here from the real controller's response shape, so no provider is ever called.
	const calls = [];
	let askMode = 'not_found';
	const chatRoute = async ( route ) => {
		const request = route.request();
		const url = new URL( request.url() );
		const endpoint = url.pathname.replace( /^.*\/aisaautopilot\/v1\//, '' );
		const body = 'POST' === request.method() && 'chat/voice/transcribe' !== endpoint ? ( request.postDataJSON() || {} ) : null;
		calls.push( { method: request.method(), endpoint, body, nonce: !! request.headers()[ 'x-wp-nonce' ] } );
		const reply = ( status, payload ) => route.fulfill( { status, contentType: 'application/json', body: JSON.stringify( payload ) } );
		if ( 'chat/list' === endpoint ) {
			return reply( 200, { ok: true, conversations } );
		}
		if ( 'chat/get' === endpoint ) {
			const id = Number( url.searchParams.get( 'id' ) );
			return stored[ id ]
				? reply( 200, { ok: true, conversation: { id, title: '', messages: stored[ id ] } } )
				: reply( 404, { ok: false, message: 'Conversation not found.' } );
		}
		if ( 'chat/ask' === endpoint ) {
			if ( 'engine_error' === askMode ) {
				// ChatEngine catches provider failures; chat/ask refuses with 503 and saves nothing.
				return reply( 503, { ok: false, code: 'assistant_unavailable', message: 'An error occurred while processing your question. Please try again.', conversation_id: 9103 } );
			}
			if ( 'cap' === askMode ) {
				return reply( 429, { ok: false, code: 'usage_cap_reached', message: 'Monthly AI budget reached.', status: 'usage_cap_reached', conversation_id: 9103 } );
			}
			const question = String( body.question || '' );
			const answer = 'I could not find that in your content.';
			conversations = [ { id: 9103, user_id: 1, title: question, message_count: 2, created_at: mysqlUtc( new Date() ), updated_at: mysqlUtc( new Date() ) } ]
				.concat( conversations.filter( ( conversation ) => 9103 !== conversation.id ) );
			stored[ 9103 ] = [ { role: 'user', content: question }, { role: 'assistant', content: answer, sources: [], status: 'not_found' } ];
			return reply( 200, { ok: true, answer, sources: [], status: 'not_found', clarify_options: [], handoff_offer: true, usage: null, conversation_id: 9103 } );
		}
		if ( 'chat/delete' === endpoint ) {
			conversations = conversations.filter( ( conversation ) => conversation.id !== Number( body.id ) );
			return reply( 200, { ok: true, message: 'Conversation deleted.' } );
		}
		if ( 'chat/voice/transcribe' === endpoint ) {
			return reply( 200, { ok: true, transcript: 'Do you deliver on weekends?' } );
		}
		return route.abort();
	};
	const countCalls = ( endpoint ) => calls.filter( ( call ) => call.endpoint === endpoint ).length;
	const pluginRequests = [];
	const collectPluginRequest = ( request ) => {
		if ( request.url().includes( '/aisaautopilot/v1/' ) ) {
			pluginRequests.push( request.url().replace( /^.*\/aisaautopilot\/v1\//, '' ).split( '?' )[ 0 ] );
		}
	};

	await page.setViewportSize( { width: 1440, height: 960 } );
	await page.route( chatRoutePattern, chatRoute );
	page.on( 'request', collectPluginRequest );
	try {
		await page.goto( chatUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
		const root = page.locator( '[data-sp-chat]' );
		await root.waitFor( { state: 'attached', timeout: 10000 } );
		const threads = page.locator( '#sp-chat-list .sp-chat__thread' );
		await threads.first().waitFor( { state: 'visible', timeout: 10000 } );
		await page.waitForFunction( () => document.getElementById( 'sp-chat-list' ).getAttribute( 'aria-busy' ) === 'false' );
		check( await threads.count() === 2, 'Ask console lists the conversations returned by chat/list' );
		check(
			1 === calls.length && 'chat/list' === calls[ 0 ].endpoint && calls[ 0 ].nonce
			&& 0 === pluginRequests.filter( ( endpoint ) => 'chat/list' !== endpoint ).length,
			'Ask console opens with one nonce-protected conversation-list request and nothing else'
		);
		check( ( await page.locator( '[data-sp-chat-meta]' ).innerText() ).trim().length > 0, 'Ask console states what answers are drawn from' );
		check(
			2 === await page.locator( '#sp-chat-list .sp-chat__thread-delete[aria-label]' ).count(),
			'Every conversation has a labelled delete control'
		);

		const threadOpen = ( id ) => page.locator( '#sp-chat-list .sp-chat__thread[data-id="' + id + '"] .sp-chat__thread-open' );
		await threadOpen( 9102 ).click();
		await page.locator( '#sp-chat-messages [data-sp-chat-gap]' ).first().waitFor( { state: 'visible', timeout: 10000 } );
		check(
			2 === await page.locator( '#sp-chat-messages .sp-msg' ).count()
			&& 'true' === await threadOpen( 9102 ).getAttribute( 'aria-current' )
			&& await page.locator( '[data-sp-chat-welcome]' ).isHidden(),
			'Opening a conversation replays it, keeps its not-in-your-content marker and marks it current'
		);
		await threadOpen( 9101 ).click();
		await page.locator( '#sp-chat-messages .sp-msg__sources a' ).first().waitFor( { state: 'visible', timeout: 10000 } );
		const sourceLink = page.locator( '#sp-chat-messages .sp-msg__sources a' ).first();
		check(
			'Shipping' === ( await sourceLink.innerText() ).trim()
			&& '_blank' === await sourceLink.getAttribute( 'target' )
			&& ( await sourceLink.getAttribute( 'rel' ) || '' ).includes( 'noopener' )
			&& 1 === await page.locator( '#sp-chat-messages .sp-msg__text strong' ).count()
			&& 0 === await page.locator( '#sp-chat-messages [data-sp-chat-gap]' ).count(),
			'Answers list their source pages safely and render simple formatting'
		);

		const callsBeforeNew = calls.length;
		await page.locator( '#sp-chat-new' ).click();
		check(
			await page.locator( '[data-sp-chat-welcome]' ).isVisible()
			&& 0 === await page.locator( '#sp-chat-messages .sp-msg' ).count()
			&& 0 === await page.locator( '#sp-chat-list .sp-chat__thread.is-on' ).count()
			&& callsBeforeNew === calls.length,
			'New conversation returns to the welcome state without a request'
		);

		const ready = '1' === await root.getAttribute( 'data-sp-chat-ready' );
		const input = page.locator( '#sp-chat-input' );
		const send = page.locator( '#sp-chat-send' );
		const mic = page.locator( '#sp-mic-btn' );
		const chip = page.locator( '[data-sp-chat-welcome] [data-question]' ).first();
		if ( ! ready ) {
			check(
				await input.isDisabled() && await send.isDisabled() && await mic.isDisabled() && await chip.isDisabled()
				&& 1 === await page.locator( '[data-sp-chat-notice]' ).count(),
				'Ask console locks the composer, microphone and suggestions and says why when answers cannot run'
			);
		} else {
			check( await send.isDisabled(), 'Send stays disabled while the composer is empty' );
			const chipText = ( await chip.getAttribute( 'data-question' ) ) || '';
			await chip.click();
			check(
				chipText === await input.inputValue()
				&& await send.isEnabled()
				&& await input.evaluate( ( node ) => node === document.activeElement )
				&& 0 === countCalls( 'chat/ask' ),
				'A suggestion only fills and focuses the composer; nothing is sent'
			);

			await input.fill( '' );
			await input.focus();
			await page.keyboard.type( 'First line' );
			await page.keyboard.press( 'Shift+Enter' );
			await page.keyboard.type( 'Second line' );
			check( ( await input.inputValue() ).includes( '\n' ) && 0 === countCalls( 'chat/ask' ), 'Shift+Enter adds a new line without sending' );

			await input.fill( 'Do you deliver on weekends?' );
			const askResponse = page.waitForResponse( ( response ) => response.url().includes( '/chat/ask' ) );
			await input.press( 'Enter' );
			await askResponse;
			const gap = page.locator( '#sp-chat-messages [data-sp-chat-gap]' ).last();
			await gap.waitFor( { state: 'visible', timeout: 10000 } );
			const askCall = calls.find( ( call ) => 'chat/ask' === call.endpoint );
			check(
				!! askCall && askCall.nonce && 'Do you deliver on weekends?' === askCall.body.question && 0 === Number( askCall.body.conversation_id ),
				'Enter sends one nonce-protected question that starts a new conversation'
			);
			const gapHref = ( await gap.locator( 'a' ).getAttribute( 'href' ) ) || '';
			check(
				gapHref.includes( 'page=aisaautopilot-kb' ) && gapHref.endsWith( '#sp-kb-gaps' ) && 1 === await gap.locator( '.sp-msg__gap-note' ).count(),
				'An unanswered question is marked, links to the content gaps and notes the visitor handoff'
			);
			await page.waitForFunction( () => 3 === document.querySelectorAll( '#sp-chat-list .sp-chat__thread' ).length );
			check( '9103' === await page.locator( '#sp-chat-list .sp-chat__thread.is-on' ).getAttribute( 'data-id' ), 'The new conversation joins the list and is marked current' );

			askMode = 'cap';
			await input.fill( 'What are your opening hours?' );
			const capResponse = page.waitForResponse( ( response ) => response.url().includes( '/chat/ask' ) && 429 === response.status() );
			await send.click();
			await capResponse;
			await page.locator( '#sp-chat-messages .sp-msg--error' ).last().waitFor( { state: 'visible', timeout: 10000 } );
			await page.waitForFunction( () => ! document.getElementById( 'sp-chat-input' ).disabled );
			check(
				'What are your opening hours?' === await input.inputValue()
				&& ( await page.locator( '#sp-chat-messages .sp-msg--error' ).last().innerText() ).includes( 'Monthly AI budget reached.' ),
				'A refused question shows the server reason and gives the text back to the composer'
			);
			const capCard = page.locator( '#sp-chat-messages .sp-msg--error' ).last();
			check(
				1 === await capCard.locator( '.sp-msg__error-title' ).count()
				&& ( await capCard.locator( 'a.sp-msg__action' ).getAttribute( 'href' ) || '' ).includes( 'page=aisaautopilot-usage' )
				&& 0 === await capCard.locator( '[data-sp-chat-retry]' ).count(),
				'A cap refusal names the problem and links to Usage & Cost instead of offering a retry'
			);

			askMode = 'engine_error';
			const asksBeforeEngineError = countCalls( 'chat/ask' );
			await input.fill( 'Is the shop open on Sunday?' );
			const engineResponse = page.waitForResponse( ( response ) => response.url().includes( '/chat/ask' ) );
			await send.click();
			await engineResponse;
			const engineCard = page.locator( '#sp-chat-messages .sp-msg--error' ).last();
			await engineCard.locator( '[data-sp-chat-retry]' ).waitFor( { state: 'visible', timeout: 10000 } );
			await page.waitForFunction( () => ! document.getElementById( 'sp-chat-input' ).disabled );
			check(
				'Is the shop open on Sunday?' === await input.inputValue()
				&& 1 === await engineCard.locator( '.sp-msg__error-title' ).count()
				&& ( await engineCard.locator( 'a.sp-msg__action--ghost' ).getAttribute( 'href' ) || '' ).includes( 'page=aisaautopilot-connections' )
				&& asksBeforeEngineError + 1 === countCalls( 'chat/ask' ),
				'A provider failure shows the error card with Ask again and Open Health, and gives the question back'
			);
			const retryResponse = page.waitForResponse( ( response ) => response.url().includes( '/chat/ask' ) );
			await engineCard.locator( '[data-sp-chat-retry]' ).click();
			await retryResponse;
			await page.waitForFunction( () => ! document.getElementById( 'sp-chat-input' ).disabled );
			check( asksBeforeEngineError + 2 === countCalls( 'chat/ask' ), 'Ask again sends the same question once, and only when clicked' );
			askMode = 'not_found';

			// Deterministic recorder stubs; they live only until the next navigation.
			await input.fill( '' );
			await page.evaluate( () => {
				const track = { stop() {} };
				if ( ! navigator.mediaDevices ) {
					Object.defineProperty( navigator, 'mediaDevices', { value: {}, configurable: true } );
				}
				navigator.mediaDevices.getUserMedia = () => Promise.resolve( { getTracks: () => [ track ] } );
				window.MediaRecorder = class {
					constructor() { this.mimeType = 'audio/webm'; this.ondataavailable = null; this.onstop = null; }
					static isTypeSupported() { return true; }
					start() {}
					stop() {
						if ( this.ondataavailable ) { this.ondataavailable( { data: new Blob( [ 'voice' ], { type: 'audio/webm' } ) } ); }
						if ( this.onstop ) { this.onstop(); }
					}
				};
			} );
			const asksBeforeVoice = countCalls( 'chat/ask' );
			await mic.click();
			await page.waitForFunction( () => 'true' === document.getElementById( 'sp-mic-btn' ).getAttribute( 'aria-pressed' ) );
			const transcribeResponse = page.waitForResponse( ( response ) => response.url().includes( '/chat/voice/transcribe' ) );
			await mic.click();
			await transcribeResponse;
			await page.waitForFunction( () => 'ok' === document.getElementById( 'sp-chat-status' ).getAttribute( 'data-tone' ) );
			check(
				'Do you deliver on weekends?' === await input.inputValue()
				&& asksBeforeVoice === countCalls( 'chat/ask' )
				&& await send.isEnabled()
				&& 'false' === await mic.getAttribute( 'aria-pressed' ),
				'A voice transcript waits in the composer for review and is never sent on its own'
			);
			await input.fill( '' );
		}

		const deleteButton = page.locator( '#sp-chat-list .sp-chat__thread[data-id="9101"] .sp-chat__thread-delete' );
		const dialog = page.locator( '[data-sp-confirm-dialog] [role="alertdialog"]' );
		await deleteButton.click();
		await dialog.waitFor( { state: 'visible', timeout: 5000 } );
		const cancelFocused = await page.evaluate( () => {
			const active = document.activeElement;
			return !! active && !! active.closest( '[data-sp-confirm-dialog]' ) && active.classList.contains( 'sp-btn--ghost' );
		} );
		await page.keyboard.press( 'Escape' );
		await dialog.waitFor( { state: 'hidden', timeout: 5000 } );
		check(
			cancelFocused && await deleteButton.evaluate( ( node ) => node === document.activeElement ) && 0 === countCalls( 'chat/delete' ),
			'Delete asks first: Cancel is focused, Escape closes the dialog, focus returns and nothing is deleted'
		);
		await deleteButton.click();
		await dialog.waitFor( { state: 'visible', timeout: 5000 } );
		const deleteResponse = page.waitForResponse( ( response ) => response.url().includes( '/chat/delete' ) );
		await page.locator( '[data-sp-confirm-dialog] .sp-btn--danger-solid' ).click();
		await deleteResponse;
		await page.waitForFunction( () => ! document.querySelector( '#sp-chat-list .sp-chat__thread[data-id="9101"]' ) );
		const deleteCall = calls.find( ( call ) => 'chat/delete' === call.endpoint );
		check(
			!! deleteCall && deleteCall.nonce && 9101 === Number( deleteCall.body.id )
			&& await page.locator( '#sp-chat-new' ).evaluate( ( node ) => node === document.activeElement )
			&& await page.locator( '#sp-toast' ).isVisible(),
			'Confirmed delete removes only that conversation, confirms it and keeps focus on a stable control'
		);

		await page.locator( '#sp-chat-new' ).focus();
		await page.keyboard.press( 'Tab' );
		const focusState = await page.evaluate( () => {
			const active = document.activeElement;
			return {
				open: !! active && active.classList.contains( 'sp-chat__thread-open' ),
				outline: active ? getComputedStyle( active ).outlineStyle : 'none',
			};
		} );
		check( focusState.open && 'none' !== focusState.outline, 'Keyboard focus moves from New conversation into the list with a visible focus ring' );

		check( ! await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 ), 'Ask console has no desktop horizontal overflow' );
		if ( ! skipScreenshots ) {
			await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-chat-desktop.png' ), fullPage: true } );
		}

		let originalDir = null;
		if ( expectNativeRtl ) {
			check( 'rtl' === await page.locator( 'html' ).getAttribute( 'dir' ), 'Ask console runs inside a genuinely RTL WordPress admin shell' );
			const copy = await page.evaluate( () => ( {
				title: document.querySelector( '.sp-chat__welcome-title' ).textContent,
				placeholder: document.getElementById( 'sp-chat-input' ).getAttribute( 'placeholder' ),
				list: document.getElementById( 'sp-chat-list-title' ).textContent,
				foot: document.getElementById( 'sp-chat-foot' ).textContent,
			} ) );
			const persian = /[\u0600-\u06FF]/;
			check( persian.test( copy.title ) && persian.test( copy.placeholder ) && persian.test( copy.list ) && persian.test( copy.foot ), 'Ask console copy is really Persian in fa_IR' );
		} else {
			originalDir = await page.locator( 'html' ).getAttribute( 'dir' );
			await page.locator( 'html' ).evaluate( ( html ) => html.setAttribute( 'dir', 'rtl' ) );
			// Let the direction change settle before measuring.
			await page.waitForTimeout( 120 );
		}
		const rtlState = await page.evaluate( () => {
			const aside = document.querySelector( '.sp-chat__aside' ).getBoundingClientRect();
			const main = document.querySelector( '.sp-chat__main' ).getBoundingClientRect();
			return {
				direction: getComputedStyle( document.querySelector( '.sp-chat' ) ).direction,
				overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
				listOnRight: aside.left > main.left,
				mirrored: getComputedStyle( document.querySelector( '#sp-chat-send svg' ) ).transform.startsWith( 'matrix(-1' ),
			};
		} );
		check(
			'rtl' === rtlState.direction && ! rtlState.overflow && rtlState.listOnRight && rtlState.mirrored,
			'Ask console mirrors in RTL: list on the right, send icon flipped, no overflow'
		);
		if ( ! skipScreenshots ) {
			await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-chat-rtl.png' ), fullPage: true } );
		}
		if ( ! expectNativeRtl ) {
			await page.locator( 'html' ).evaluate( ( html, dir ) => {
				if ( dir ) {
					html.setAttribute( 'dir', dir );
				} else {
					html.removeAttribute( 'dir' );
				}
			}, originalDir );
		}

		await page.setViewportSize( { width: 390, height: 844 } );
		await page.goto( chatUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
		await page.locator( '#sp-chat-list .sp-chat__thread' ).first().waitFor( { state: 'visible', timeout: 10000 } );
		const mobile = await page.evaluate( () => {
			const chat = document.querySelector( '.sp-chat' );
			const box = chat.getBoundingClientRect();
			const aside = document.querySelector( '.sp-chat__aside' ).getBoundingClientRect();
			const main = document.querySelector( '.sp-chat__main' ).getBoundingClientRect();
			const field = document.querySelector( '.sp-chat__field' ).getBoundingClientRect();
			return {
				overflow: document.documentElement.scrollWidth > window.innerWidth + 1 || chat.scrollWidth > chat.clientWidth + 1,
				inside: box.left >= 0 && box.right <= window.innerWidth + 1,
				stacked: aside.bottom <= main.top + 1,
				fieldInside: field.left >= box.left - 1 && field.right <= box.right + 1,
			};
		} );
		check( ! mobile.overflow && mobile.inside && mobile.stacked && mobile.fieldInside, 'Ask console stacks the list above the conversation at 390px without horizontal overflow' );
		if ( ! skipScreenshots ) {
			await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-chat-mobile.png' ), fullPage: true } );
		}
	} finally {
		page.off( 'request', collectPluginRequest );
		await page.unroute( chatRoutePattern, chatRoute );
		await page.setViewportSize( { width: 1440, height: 960 } );
	}
}

async function handoffInboxHealthy( page ) {
	console.log( 'CHECK Handoff inbox: filters, thread drawer, stored status change, RTL and 390px' );
	const ids = JSON.parse( handoffInboxFixtureIds );
	const inboxUrl = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-handoffs';
	const pluginRequests = [];
	const collectPluginRequest = ( request ) => {
		if ( request.url().includes( '/aisaautopilot/v1/' ) ) {
			pluginRequests.push( request.method() + ' ' + request.url().replace( /^.*\/aisaautopilot\/v1\//, '' ) );
		}
	};
	await page.setViewportSize( { width: 1440, height: 960 } );
	page.on( 'request', collectPluginRequest );
	try {
		await page.goto( inboxUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
		const root = page.locator( '[data-sp-handoff-inbox-list]' );
		await root.waitFor( { state: 'attached', timeout: 10000 } );
		const item = ( id ) => root.locator( '[data-sp-handoff-item="' + id + '"]' );
		const openButton = ( id ) => item( id ).locator( '[data-sp-handoff-open]' );
		const dialog = page.locator( '[data-sp-handoff-drawer] [role="dialog"]' );
		const insideDialog = () => page.evaluate( () => !! document.activeElement && !! document.activeElement.closest( '[data-sp-handoff-drawer] [role="dialog"]' ) );
		// Waits for the drawer's entrance animation to finish instead of sleeping.
		const settleDrawer = () => dialog.evaluate( ( node ) => Promise.all( node.getAnimations().map( ( animation ) => animation.finished ) ) );
		const toNumber = ( text ) => Number( String( text || '' ).replace( /[۰-۹]/g, ( digit ) => String( '۰۱۲۳۴۵۶۷۸۹'.indexOf( digit ) ) ).replace( /[^\d]/g, '' ) );

		check(
			'new' === await item( ids.new ).getAttribute( 'data-status' )
			&& 'in_progress' === await item( ids.in_progress ).getAttribute( 'data-status' )
			&& 'failed' === await item( ids.failed ).getAttribute( 'data-status' ),
			'Handoff inbox lists the fixture threads with their stored statuses'
		);
		check(
			6 === await root.locator( '[data-sp-handoff-filter]' ).count()
			&& 6 === await root.locator( '[data-sp-handoff-channel-state]' ).count()
			&& await root.locator( '[data-sp-handoff-new-badge]' ).isVisible()
			&& await item( ids.archived ).isHidden()
			&& await root.locator( '[data-sp-handoff-archive-resolved]' ).isVisible(),
			'Handoff inbox shows All, four status filters and Archived, the new-thread badge, Archive resolved and the six real delivery channels, with archived threads hidden'
		);
		check( 0 === pluginRequests.length, 'Opening the inbox sends no plugin REST request' );

		await root.locator( '[data-sp-handoff-filter="failed"]' ).click();
		check(
			await item( ids.failed ).isVisible()
			&& await item( ids.new ).isHidden()
			&& await item( ids.in_progress ).isHidden()
			&& 'true' === await root.locator( '[data-sp-handoff-filter="failed"]' ).getAttribute( 'aria-pressed' ),
			'The Delivery failed filter shows only failed threads and marks itself pressed'
		);
		await root.locator( '[data-sp-handoff-filter="archived"]' ).click();
		check(
			await item( ids.archived ).isVisible()
			&& await item( ids.new ).isHidden()
			&& await item( ids.resolved ).isHidden(),
			'The Archived filter shows only archived threads'
		);
		await root.locator( '[data-sp-handoff-filter="all"]' ).click();

		const newBefore = toNumber( await root.locator( '[data-sp-handoff-count="new"]' ).innerText() );
		const progressBefore = toNumber( await root.locator( '[data-sp-handoff-count="in_progress"]' ).innerText() );
		await openButton( ids.new ).click();
		await dialog.waitFor( { state: 'visible', timeout: 5000 } );
		const drawerText = await dialog.innerText();
		check(
			drawerText.includes( 'Maya Chen' )
			&& drawerText.includes( 'maya.chen@example.com' )
			&& 3 === await dialog.locator( '.sp-hoi-msg' ).count()
			&& 'true' === await dialog.locator( '[data-sp-handoff-set="new"]' ).getAttribute( 'aria-pressed' )
			&& await insideDialog(),
			'Opening a thread moves focus into a drawer with the visitor, contact, transcript and current status'
		);
		const reply = dialog.locator( '[data-sp-handoff-d="reply"]' );
		check(
			await reply.isVisible() && ( ( await reply.getAttribute( 'href' ) ) || '' ).startsWith( 'mailto:maya.chen@example.com' ),
			'A thread with an email offers Reply by email as a mailto link'
		);

		const statusResponse = page.waitForResponse( ( response ) => response.url().includes( '/handoffs/' + ids.new + '/status' ) && 'POST' === response.request().method() );
		await dialog.locator( '[data-sp-handoff-set="in_progress"]' ).click();
		const saved = await statusResponse;
		await page.waitForFunction( ( id ) => 'in_progress' === document.querySelector( '[data-sp-handoff-item="' + id + '"]' ).getAttribute( 'data-status' ), ids.new );
		check(
			saved.ok()
			&& !! saved.request().headers()[ 'x-wp-nonce' ]
			&& 'true' === await dialog.locator( '[data-sp-handoff-set="in_progress"]' ).getAttribute( 'aria-pressed' )
			&& newBefore - 1 === toNumber( await root.locator( '[data-sp-handoff-count="new"]' ).innerText() )
			&& progressBefore + 1 === toNumber( await root.locator( '[data-sp-handoff-count="in_progress"]' ).innerText() )
			&& await page.locator( '#sp-toast' ).isVisible(),
			'Changing the status saves through the nonce-protected route and updates the row, counts and toast'
		);
		check( await insideDialog(), 'Focus stays in the drawer after a status change' );

		await page.keyboard.press( 'Escape' );
		await dialog.waitFor( { state: 'hidden', timeout: 5000 } );
		check( await openButton( ids.new ).evaluate( ( node ) => node === document.activeElement ), 'Escape closes the drawer and returns focus to the thread row' );

		await openButton( ids.in_progress ).click();
		await dialog.waitFor( { state: 'visible', timeout: 5000 } );
		check( await dialog.locator( '[data-sp-handoff-d="reply"]' ).isHidden(), 'A thread without an email offers no email reply' );
		for ( let step = 0; step < 9; step++ ) {
			await page.keyboard.press( 'Tab' );
		}
		check( await insideDialog(), 'Tab keeps focus inside the open drawer' );
		await dialog.locator( '.sp-hoi-drawer__close' ).click();
		await dialog.waitFor( { state: 'hidden', timeout: 5000 } );

		await openButton( ids.failed ).click();
		await dialog.waitFor( { state: 'visible', timeout: 5000 } );
		check( await dialog.locator( '[data-sp-handoff-d="failed"]' ).isVisible(), 'A delivery-failed thread explains that no channel accepted it' );
		await page.locator( '.sp-hoi-drawer__backdrop' ).dispatchEvent( 'click' );
		await dialog.waitFor( { state: 'hidden', timeout: 5000 } );

		// Contact details and archiving from the drawer.
		const archivedCountBefore = toNumber( await root.locator( '[data-sp-handoff-count="archived"]' ).innerText() );
		await openButton( ids.resolved ).click();
		await dialog.waitFor( { state: 'visible', timeout: 5000 } );
		const phoneLink = dialog.locator( '.sp-hoi-drawer__contact-value[href^="tel:"]' );
		const siteLink = dialog.locator( '.sp-hoi-drawer__contact-value[href^="https://"]' );
		check(
			await phoneLink.isVisible() && 'tel:+493012345678' === await phoneLink.getAttribute( 'href' )
			&& await siteLink.isVisible() && 'https://weber-atelier.example' === await siteLink.getAttribute( 'href' )
			&& '_blank' === await siteLink.getAttribute( 'target' ) && ( ( await siteLink.getAttribute( 'rel' ) ) || '' ).includes( 'noopener' )
			&& await dialog.locator( '[data-sp-handoff-d="reply"]' ).isHidden(),
			'A thread with a phone and website lists them as call and web links, with no email reply'
		);
		const archiveButton = dialog.locator( '[data-sp-handoff-archive]' );
		let archiveResponse = page.waitForResponse( ( response ) => response.url().includes( '/handoffs/' + ids.resolved + '/archive' ) && 'POST' === response.request().method() );
		await archiveButton.click();
		const archivedSaved = await archiveResponse;
		await page.waitForFunction( ( id ) => '1' === document.querySelector( '[data-sp-handoff-item="' + id + '"]' ).getAttribute( 'data-archived' ), ids.resolved );
		check(
			archivedSaved.ok()
			&& !! archivedSaved.request().headers()[ 'x-wp-nonce' ]
			&& await item( ids.resolved ).isHidden()
			&& archivedCountBefore + 1 === toNumber( await root.locator( '[data-sp-handoff-count="archived"]' ).innerText() )
			&& await dialog.locator( '[data-sp-handoff-d="archived"]' ).isVisible()
			&& 'true' === await dialog.locator( '[data-sp-handoff-set="resolved"]' ).getAttribute( 'aria-pressed' )
			&& await insideDialog(),
			'Archive in the drawer keeps the status, hides the row from the inbox, counts it under Archived and says so'
		);
		const toastClear = await page.evaluate( () => {
			const toast = document.querySelector( '#sp-toast' );
			const button = document.querySelector( '[data-sp-handoff-archive]' );
			if ( ! toast || toast.hidden || ! button ) { return true; }
			const t = toast.getBoundingClientRect();
			const b = button.getBoundingClientRect();
			return t.right <= b.left || t.left >= b.right || t.bottom <= b.top || t.top >= b.bottom;
		} );
		check( toastClear, 'The confirmation toast does not cover the Move back to inbox button in the drawer' );
		if ( ! skipScreenshots ) {
			await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-handoff-inbox-archived-drawer.png' ) } );
		}
		archiveResponse = page.waitForResponse( ( response ) => response.url().includes( '/handoffs/' + ids.resolved + '/archive' ) && 'POST' === response.request().method() );
		await archiveButton.click();
		await archiveResponse;
		await page.waitForFunction( ( id ) => '0' === document.querySelector( '[data-sp-handoff-item="' + id + '"]' ).getAttribute( 'data-archived' ), ids.resolved );
		check(
			await item( ids.resolved ).isVisible()
			&& archivedCountBefore === toNumber( await root.locator( '[data-sp-handoff-count="archived"]' ).innerText() )
			&& await dialog.locator( '[data-sp-handoff-d="archived"]' ).isHidden(),
			'Move back to inbox returns the thread to the list'
		);
		await page.keyboard.press( 'Escape' );
		await dialog.waitFor( { state: 'hidden', timeout: 5000 } );

		// Archive resolved touches every resolved thread on the site, so it runs only when the fixture holds all of them.
		const archiveAll = root.locator( '[data-sp-handoff-archive-resolved]' );
		let bulkArchived = false;
		if ( 1 === toNumber( await archiveAll.locator( '[data-sp-handoff-archive-resolved-count]' ).innerText() ) ) {
			await archiveAll.click();
			const confirmBox = page.locator( '[data-sp-confirm-dialog] [role="alertdialog"]' );
			await confirmBox.waitFor( { state: 'visible', timeout: 5000 } );
			const bulkResponse = page.waitForResponse( ( response ) => response.url().includes( '/handoffs/archive-resolved' ) && 'POST' === response.request().method() );
			await confirmBox.locator( '.sp-modal__foot .sp-btn:not(.sp-btn--ghost)' ).click();
			const bulk = await bulkResponse;
			await page.waitForFunction( ( id ) => '1' === document.querySelector( '[data-sp-handoff-item="' + id + '"]' ).getAttribute( 'data-archived' ), ids.resolved );
			bulkArchived = bulk.ok();
			check(
				bulk.ok() && await archiveAll.isHidden() && await item( ids.resolved ).isHidden() && await item( ids.failed ).isVisible()
				&& await page.evaluate( () => !! document.activeElement && !! document.activeElement.closest( '[data-sp-handoff-inbox-list]' ) ),
				'Archive resolved asks first, archives only resolved threads and keeps focus in the inbox'
			);
		} else {
			console.log( 'SKIP Archive resolved: the site has resolved threads outside the fixture' );
		}

		await page.reload( { waitUntil: 'domcontentloaded', timeout: 20000 } );
		await root.waitFor( { state: 'attached', timeout: 10000 } );
		check( 'in_progress' === await item( ids.new ).getAttribute( 'data-status' ), 'The changed status is stored and survives a reload' );
		if ( bulkArchived ) {
			check( '1' === await item( ids.resolved ).getAttribute( 'data-archived' ) && await item( ids.resolved ).isHidden(), 'An archived thread stays archived after a reload' );
		}
		check( ! await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 ), 'Handoff inbox has no desktop horizontal overflow' );
		if ( ! skipScreenshots ) {
			await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-handoff-inbox-desktop.png' ), fullPage: true } );
		}

		let originalDir = null;
		if ( expectNativeRtl ) {
			check( 'rtl' === await page.locator( 'html' ).getAttribute( 'dir' ), 'Handoff inbox runs inside a genuinely RTL WordPress admin shell' );
			const persian = /[\u0600-\u06FF]/;
			check(
				persian.test( await root.locator( '.sp-hoi__title' ).innerText() ) && persian.test( await root.locator( '[data-sp-handoff-filter="all"]' ).innerText() )
				&& persian.test( await root.locator( '[data-sp-handoff-filter="archived"]' ).innerText() ),
				'Handoff inbox copy is really Persian in fa_IR'
			);
		} else {
			originalDir = await page.locator( 'html' ).getAttribute( 'dir' );
			await page.locator( 'html' ).evaluate( ( html ) => html.setAttribute( 'dir', 'rtl' ) );
			// Let the direction change settle before measuring.
			await page.waitForTimeout( 120 );
		}
		await openButton( ids.failed ).click();
		await dialog.waitFor( { state: 'visible', timeout: 5000 } );
		await settleDrawer();
		const rtlState = await page.evaluate( () => {
			const panel = document.querySelector( '[data-sp-handoff-drawer] [role="dialog"]' ).getBoundingClientRect();
			return {
				direction: getComputedStyle( document.querySelector( '[data-sp-handoff-inbox-list]' ) ).direction,
				overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
				// A left-side scrollbar in RTL offsets the viewport, so allow for the scrollbar gutter.
				panelOnLeft: panel.left <= ( window.innerWidth - document.documentElement.clientWidth ) + 1,
				panelLeft: panel.left,
				overflowBy: document.documentElement.scrollWidth - window.innerWidth,
			};
		} );
		const rtlOk = 'rtl' === rtlState.direction && ! rtlState.overflow && rtlState.panelOnLeft;
		check( rtlOk, 'Handoff inbox mirrors in RTL: the drawer opens from the left without overflow' );
		if ( ! rtlOk ) {
			console.log( 'HANDOFF_INBOX_RTL_STATE ' + JSON.stringify( rtlState ) );
		}
		if ( ! skipScreenshots ) {
			await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-handoff-inbox-rtl.png' ) } );
		}
		await page.keyboard.press( 'Escape' );
		await dialog.waitFor( { state: 'hidden', timeout: 5000 } );
		if ( ! expectNativeRtl ) {
			await page.locator( 'html' ).evaluate( ( html, dir ) => {
				if ( dir ) {
					html.setAttribute( 'dir', dir );
				} else {
					html.removeAttribute( 'dir' );
				}
			}, originalDir );
		}

		await page.setViewportSize( { width: 390, height: 844 } );
		await page.goto( inboxUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
		await root.waitFor( { state: 'attached', timeout: 10000 } );
		const mobile = await page.evaluate( () => {
			const list = document.querySelector( '[data-sp-handoff-inbox-list]' ).getBoundingClientRect();
			return {
				overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
				inside: list.left >= 0 && list.right <= window.innerWidth + 1,
			};
		} );
		await openButton( ids.new ).click();
		await dialog.waitFor( { state: 'visible', timeout: 5000 } );
		await settleDrawer();
		const mobilePanel = await dialog.evaluate( ( node ) => {
			const rect = node.getBoundingClientRect();
			const viewport = document.documentElement.clientWidth;
			return { fits: rect.left >= -1 && rect.right <= window.innerWidth + 1 && rect.width >= viewport - 2, left: rect.left, right: rect.right, width: rect.width, viewport };
		} );
		const mobileOk = ! mobile.overflow && mobile.inside && mobilePanel.fits;
		check( mobileOk, 'Handoff inbox and its drawer fit the 390px viewport without horizontal overflow' );
		if ( ! mobileOk ) {
			console.log( 'HANDOFF_INBOX_MOBILE_STATE ' + JSON.stringify( { mobile, panel: mobilePanel } ) );
		}
		if ( ! skipScreenshots ) {
			await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-handoff-inbox-mobile.png' ) } );
		}
		await page.keyboard.press( 'Escape' );
	} finally {
		page.off( 'request', collectPluginRequest );
		await page.setViewportSize( { width: 1440, height: 960 } );
	}
}

async function kbAnswersHealthy( page ) {
	console.log( 'CHECK Site Knowledge unanswered questions: reasons, window, Answer it, saved answers, Write it, RTL and 390px' );
	const ids = JSON.parse( kbAnswersFixture );
	const kbUrl = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-kb';
	const writes = [];
	const collect = ( request ) => {
		if ( request.url().includes( '/aisaautopilot/v1/' ) && 'GET' !== request.method() ) {
			writes.push( request.method() + ' ' + request.url().replace( /^.*\/aisaautopilot\/v1\//, '' ) );
		}
	};
	const persianAdmin = () => page.locator( 'html' ).getAttribute( 'lang' ).then( ( lang ) => /^fa/i.test( lang || '' ) );
	const gapRow = ( hash ) => page.locator( '[data-sp-kb-gap="' + hash + '"]' );
	const answerRowFor = ( id ) => page.locator( '[data-sp-kb-answer="' + id + '"]' );
	await page.setViewportSize( { width: 1440, height: 960 } );
	page.on( 'request', collect );
	try {
		await page.goto( kbUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
		await gapRow( ids.ship ).waitFor( { state: 'visible', timeout: 15000 } );
		await answerRowFor( ids.answer ).waitFor( { state: 'visible', timeout: 15000 } );
		const fa = await persianAdmin();

		const ship = gapRow( ids.ship );
		const returns = gapRow( ids.returns );
		const shipWrite = ship.locator( '[data-sp-kb-gap-write]' );
		const shipHref = ( await shipWrite.getAttribute( 'href' ) ) || '';
		check(
			3 === await page.locator( '#sp-kb-gaps-list [data-sp-kb-gap]' ).count()
			&& '' !== ( await ship.locator( '.sp-kb-gap__reason' ).innerText() ).trim()
			&& /9|۹/.test( await ship.locator( '.sp-kb-gap__count' ).innerText() )
			&& shipHref.includes( 'tab=write' ) && shipHref.includes( 'source_gap=' + ids.ship ) && shipHref.includes( 'brief=' ),
			'Each unanswered question shows its reason, how often it was asked and a Write it link carrying the question'
		);
		const draftLink = returns.locator( '[data-sp-kb-gap-draft]' );
		check(
			1 === await draftLink.count() && ( ( await draftLink.getAttribute( 'href' ) ) || '' ).includes( 'post=' + ids.draft_id )
			&& 0 === await returns.locator( '[data-sp-kb-gap-write]' ).count()
			&& 1 === await returns.locator( '.sp-kb-gap__count.is-draft' ).count(),
			'A question already linked to a draft offers Open draft instead of Write it'
		);
		check( 0 === writes.length, 'Opening Site Knowledge changes nothing: no plugin write request is sent' );
		if ( ! skipScreenshots ) {
			await page.locator( '[data-sp-kb-gaps]' ).screenshot( { path: path.join( screenshotDir, 'aisaautopilot-kb-gaps.png' ) } );
		}

		// Last 7 days leaves out the question last asked ten days ago.
		const windowResponse = page.waitForResponse( ( response ) => response.url().includes( 'kb/gaps' ) && response.url().includes( 'window=7d' ) );
		await page.locator( '[data-sp-kb-gaps-window="7d"]' ).click();
		await windowResponse;
		await gapRow( ids.old ).waitFor( { state: 'detached', timeout: 10000 } );
		check(
			2 === await page.locator( '#sp-kb-gaps-list [data-sp-kb-gap]' ).count()
			&& 'true' === await page.locator( '[data-sp-kb-gaps-window="7d"]' ).getAttribute( 'aria-pressed' ),
			'Last 7 days shows only questions asked in the past week and marks itself pressed'
		);
		const allResponse = page.waitForResponse( ( response ) => response.url().includes( 'kb/gaps' ) && response.url().includes( 'window=all' ) );
		await page.locator( '[data-sp-kb-gaps-window="all"]' ).click();
		await allResponse;
		await gapRow( ids.old ).waitFor( { state: 'visible', timeout: 10000 } );

		// Answer it: Escape closes the editor, then a saved answer moves the question to Saved answers.
		const answerIt = ship.locator( '[data-sp-kb-gap-answer]' );
		await answerIt.click();
		const editor = ship.locator( '[data-sp-kb-answer-form]' );
		await editor.waitFor( { state: 'visible', timeout: 5000 } );
		check(
			'true' === await answerIt.getAttribute( 'aria-expanded' )
			&& await editor.locator( '[data-sp-kb-answer-text]' ).evaluate( ( node ) => node === document.activeElement ),
			'Answer it opens an inline editor with focus in the answer field'
		);
		await page.keyboard.press( 'Escape' );
		await editor.waitFor( { state: 'detached', timeout: 5000 } );
		check( await answerIt.evaluate( ( node ) => node === document.activeElement ) && 'false' === await answerIt.getAttribute( 'aria-expanded' ), 'Escape closes the editor and returns focus to Answer it' );
		const writesBefore = writes.length;
		await answerIt.click();
		await editor.locator( '[data-sp-kb-answer-save]' ).click();
		check( writesBefore === writes.length && '' !== ( await editor.locator( '[data-sp-kb-answer-status]' ).innerText() ).trim(), 'Saving an empty answer is stopped in the browser with a message' );
		await editor.locator( '[data-sp-kb-answer-text]' ).fill( 'Yes, we ship to Canada in 5 to 8 working days.' );
		const saveResponse = page.waitForResponse( ( response ) => response.url().includes( '/kb/answers' ) && 'POST' === response.request().method() );
		await editor.locator( '[data-sp-kb-answer-save]' ).click();
		const saved = await saveResponse;
		const savedBody = await saved.json();
		await ship.waitFor( { state: 'detached', timeout: 10000 } );
		const newRow = answerRowFor( savedBody.item && savedBody.item.id );
		await newRow.waitFor( { state: 'visible', timeout: 10000 } );
		await page.waitForFunction( ( id ) => {
			const edit = document.querySelector( '[data-sp-kb-answer="' + id + '"] [data-sp-kb-answer-edit]' );
			return !! edit && edit === document.activeElement;
		}, savedBody.item.id, { timeout: 5000 } );
		const savedRequest = JSON.parse( saved.request().postData() || '{}' );
		check(
			saved.ok() && !! saved.request().headers()[ 'x-wp-nonce' ]
			&& ids.ship === savedRequest.gap && ! ( 'question' in savedRequest )
			&& ( await newRow.innerText() ).includes( 'ship to Canada in 5 to 8 working days' ),
			'Saving the answer sends only the gap reference and the text, removes the question from the list and focuses the new saved answer'
		);

		// Saved answers: edit, add and delete.
		const hours = answerRowFor( ids.answer );
		check( /2|۲/.test( await hours.locator( '.sp-kb-answer__meta' ).innerText() ), 'A saved answer shows how many times it was used' );
		await hours.locator( '[data-sp-kb-answer-edit]' ).click();
		const hoursEditor = hours.locator( '[data-sp-kb-answer-form]' );
		await hoursEditor.waitFor( { state: 'visible', timeout: 5000 } );
		check( ( await hoursEditor.locator( '[data-sp-kb-answer-question]' ).inputValue() ).includes( 'opening hours' ), 'Editing a saved answer starts from its question and answer' );
		await hoursEditor.locator( '[data-sp-kb-answer-text]' ).fill( 'We are open Monday to Saturday, 9:00 to 18:00.' );
		const editResponse = page.waitForResponse( ( response ) => response.url().includes( '/kb/answers' ) && 'POST' === response.request().method() );
		await hoursEditor.locator( '[data-sp-kb-answer-save]' ).click();
		const edited = await editResponse;
		await page.waitForFunction( ( id ) => {
			const row = document.querySelector( '[data-sp-kb-answer="' + id + '"]' );
			return !! row && row.textContent.includes( 'Monday to Saturday' ) && ! row.querySelector( '[data-sp-kb-answer-form]' );
		}, ids.answer, { timeout: 10000 } );
		check( edited.ok() && ids.answer === JSON.parse( edited.request().postData() || '{}' ).id, 'Editing saves the new text for the same saved answer' );

		const add = page.locator( '[data-sp-kb-answer-add]' );
		await add.click();
		const newForm = page.locator( '[data-sp-kb-answer-new] [data-sp-kb-answer-form]' );
		await newForm.waitFor( { state: 'visible', timeout: 5000 } );
		await newForm.locator( '[data-sp-kb-answer-question]' ).fill( 'AutoPilot QA · Do you have a showroom?' );
		await newForm.locator( '[data-sp-kb-answer-text]' ).fill( 'No, we sell online only.' );
		const addResponse = page.waitForResponse( ( response ) => response.url().includes( '/kb/answers' ) && 'POST' === response.request().method() );
		await newForm.locator( '[data-sp-kb-answer-save]' ).click();
		const added = await addResponse;
		const addedId = ( ( await added.json() ).item || {} ).id;
		await answerRowFor( addedId ).waitFor( { state: 'visible', timeout: 10000 } );
		check( added.ok() && 3 === await page.locator( '[data-sp-kb-answers-list] [data-sp-kb-answer]' ).count() && await page.locator( '[data-sp-kb-answer-new]' ).isHidden(), 'Add an answer saves a new question and answer and closes the form' );

		await answerRowFor( addedId ).locator( '[data-sp-kb-answer-delete]' ).click();
		const confirmBox = page.locator( '[data-sp-confirm-dialog] [role="alertdialog"]' );
		await confirmBox.waitFor( { state: 'visible', timeout: 5000 } );
		const deleteResponse = page.waitForResponse( ( response ) => response.url().includes( '/kb/answers/delete' ) );
		await confirmBox.locator( '.sp-modal__foot .sp-btn:not(.sp-btn--ghost)' ).click();
		const deleted = await deleteResponse;
		await answerRowFor( addedId ).waitFor( { state: 'detached', timeout: 10000 } );
		check( deleted.ok() && 2 === await page.locator( '[data-sp-kb-answers-list] [data-sp-kb-answer]' ).count(), 'Deleting asks first and removes the saved answer' );
		if ( ! skipScreenshots ) {
			await page.locator( '[data-sp-kb-answers]' ).screenshot( { path: path.join( screenshotDir, 'aisaautopilot-kb-saved-answers.png' ) } );
		}

		if ( fa ) {
			const persian = /[؀-ۿ]/;
			check(
				persian.test( await returns.locator( '[data-sp-kb-gap-answer]' ).innerText() )
				// Reasons and counts are built by the server, so they are Persian only when the request asks for the user locale.
				&& persian.test( await returns.locator( '.sp-kb-gap__reason' ).innerText() )
				&& persian.test( await gapRow( ids.old ).locator( '.sp-kb-gap__count' ).innerText() )
				&& persian.test( await page.locator( '[data-sp-kb-answer-add]' ).innerText() )
				&& 'rtl' === await page.locator( '[data-sp-kb-answers]' ).evaluate( ( node ) => getComputedStyle( node ).direction ),
				'Unanswered questions and saved answers are Persian and right-to-left in fa_IR'
			);
		}

		// Write it opens Content Studio with the brief and the question, and nothing is generated on the way.
		const planRequests = writes.filter( ( entry ) => entry.includes( 'content/plan' ) ).length;
		await returns.scrollIntoViewIfNeeded();
		const oldWrite = gapRow( ids.old ).locator( '[data-sp-kb-gap-write]' );
		await oldWrite.click();
		await page.waitForURL( /tab=write/, { timeout: 20000 } );
		const note = page.locator( '[data-content-source-note]' );
		await note.waitFor( { state: 'visible', timeout: 10000 } );
		check(
			( await note.innerText() ).includes( 'old catalogue' )
			&& ( await page.locator( '#aisaautopilot-content-prompt' ).inputValue() ).includes( 'old catalogue' )
			&& ids.old === await page.locator( '[data-content-compose]' ).getAttribute( 'data-content-source-gap' )
			&& planRequests === writes.filter( ( entry ) => entry.includes( 'content/plan' ) ).length,
			'Write it opens Content Studio with the question in the brief, says which question it answers and generates nothing'
		);

		await page.setViewportSize( { width: 390, height: 844 } );
		await page.goto( kbUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
		await gapRow( ids.returns ).waitFor( { state: 'visible', timeout: 15000 } );
		const mobile = await page.evaluate( () => ( {
			overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
			rowInside: Array.prototype.every.call( document.querySelectorAll( '[data-sp-kb-gap], [data-sp-kb-answer]' ), ( node ) => {
				const rect = node.getBoundingClientRect();
				return rect.left >= -1 && rect.right <= window.innerWidth + 1;
			} ),
		} ) );
		check( ! mobile.overflow && mobile.rowInside, 'Unanswered questions and saved answers fit a 390px screen without horizontal overflow' );
		if ( ! skipScreenshots ) {
			await page.locator( '[data-sp-kb-gaps]' ).screenshot( { path: path.join( screenshotDir, 'aisaautopilot-kb-gaps-mobile.png' ) } );
		}
	} finally {
		page.off( 'request', collect );
		await page.setViewportSize( { width: 1440, height: 960 } );
	}
}

async function widgetCustomiseHealthy( page ) {
	console.log( 'CHECK Widget customisation: live preview, colours, privacy, save bar, discard, RTL and 390px' );
	const widgetUrl = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-widget';
	const pluginRequests = [];
	const collectPluginRequest = ( request ) => {
		if ( request.url().includes( '/aisaautopilot/v1/' ) ) {
			pluginRequests.push( request.url().replace( /^.*\/aisaautopilot\/v1\//, '' ) );
		}
	};
	// Native dialogs would hold navigation; record them (a leave-page prompt here is a defect) and let the page go.
	const dialogs = [];
	const recordDialog = async ( dialog ) => {
		dialogs.push( dialog.type() );
		await dialog.accept();
	};
	await page.setViewportSize( { width: 1440, height: 960 } );
	page.on( 'request', collectPluginRequest );
	page.on( 'dialog', recordDialog );
	try {
		for ( let attempt = 1; attempt <= 2; attempt++ ) {
			try {
				await page.goto( widgetUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
				break;
			} catch ( error ) {
				if ( 2 === attempt ) {
					throw error;
				}
				console.log( 'RETRY Widget customisation after a local-server timeout; dialogs so far: ' + JSON.stringify( dialogs ) );
			}
		}
		const form = page.locator( '[data-sp-widget-form]' );
		await form.waitFor( { state: 'attached', timeout: 10000 } );
		const stage = page.locator( '#sp-w-stage' );
		const savebar = form.locator( '[data-sp-savebar]' );
		const dialog = page.locator( '[data-sp-confirm-dialog] [role="alertdialog"]' );
		const stageVar = ( name ) => stage.evaluate( ( node, prop ) => node.style.getPropertyValue( prop ).trim().toLowerCase(), name );
		const choice = ( group, value ) => form.locator( '[data-sp-radio-group="' + group + '"] input[value="' + value + '"]' );
		const choiceLabel = ( group, value ) => form.locator( '[data-sp-radio-group="' + group + '"] label' ).filter( { has: page.locator( 'input[value="' + value + '"]' ) } );

		check( 0 === pluginRequests.length && await savebar.isHidden(), 'Widget customisation opens without REST requests and without an unsaved-changes bar' );
		check(
			0 === await form.locator( '[name^="widget[handoff_telegram"], [name^="widget[handoff_twilio"], [name="widget[voice_enabled]"], [name="widget[enabled]"]' ).count()
			&& 12 === await form.locator( '[data-sp-color]' ).count()
			&& 4 === await form.locator( '[data-sp-slot]' ).count(),
			'Widget customisation shows twelve part colours and four icon slots, leaving credentials, voice and visibility to their own tabs'
		);
		const autoOpen = form.locator( '[data-sp-auto-open]' );
		check(
			1 === await autoOpen.count()
			&& '0' === await autoOpen.inputValue()
			&& 6 === await autoOpen.locator( 'option' ).count()
			&& 'sp-wz-auto-open-help' === await autoOpen.getAttribute( 'aria-describedby' ),
			'Open the chat by itself offers six delays with Never selected by default (D-25)'
		);
		check(
			'none' === await stage.locator( '.sp-wpanel__sub' ).evaluate( ( node ) => getComputedStyle( node, '::before' ).content ),
			'The live preview, like the visitor widget, shows no "online" dot beside the subtitle'
		);

		await choiceLabel( 'position', 'top-left' ).click();
		await choiceLabel( 'theme', 'dark' ).click();
		check(
			'tl' === await stage.getAttribute( 'data-pos' ) && 'dark' === await stage.getAttribute( 'data-theme' ) && await savebar.isVisible(),
			'Position and theme update the preview at once and raise the unsaved-changes bar'
		);

		await form.locator( '[data-sp-brand="#198754"]' ).click();
		check(
			'true' === await form.locator( '[data-sp-brand="#198754"]' ).getAttribute( 'aria-pressed' )
			&& '#198754' === await stageVar( '--wc-head-from' )
			&& '#198754' === ( await form.locator( '[data-sp-color-hex="head_from"]' ).innerText() ).trim().toLowerCase()
			&& '0' === await page.locator( '#sp-w-custom-flag' ).inputValue(),
			'A brand colour repaints every derived part and its hex label'
		);
		await form.locator( '[data-sp-color="user"]' ).evaluate( ( input ) => {
			input.value = '#aa3355';
			input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		} );
		const hint = page.locator( '#sp-w-custom-hint' );
		check(
			'1' === await page.locator( '#sp-w-custom-flag' ).inputValue()
			&& '#aa3355' === await stageVar( '--wc-user' )
			&& ( await hint.innerText() ).trim() === ( ( await hint.getAttribute( 'data-custom-text' ) ) || '' ).trim(),
			'Adjusting one part switches to a custom palette and says so'
		);
		await page.locator( '#sp-w-reset-colors' ).click();
		check(
			'0' === await page.locator( '#sp-w-custom-flag' ).inputValue() && '#198754' === ( await form.locator( '[data-sp-color="user"]' ).inputValue() ).toLowerCase(),
			'Reset to brand colour returns every part to the brand palette'
		);

		const recording = form.locator( '[data-sp-recording]' );
		const notice = stage.locator( '[data-sp-region="notice"]' );
		check( ! await recording.isChecked() && await notice.isHidden(), 'With recording off the preview shows no visitor notice' );
		await recording.click();
		await form.locator( 'textarea[name="widget[conversation_notice]"]' ).fill( 'Chats are kept for 30 days.' );
		check(
			await recording.isChecked() && await notice.isVisible() && 'Chats are kept for 30 days.' === ( await notice.innerText() ).trim(),
			'Turning recording on shows the notice in the preview and it follows the text as typed'
		);

		await choiceLabel( 'shape', '6' ).click();
		check( '6px' === await stageVar( '--wp-bubble-radius' ), 'Button shape changes the floating button corners in the preview' );
		await page.locator( '[data-sp-device="mobile"]' ).click();
		check(
			'mobile' === await stage.getAttribute( 'data-device' ) && 'true' === await page.locator( '[data-sp-device="mobile"]' ).getAttribute( 'aria-pressed' ),
			'The preview switches to the mobile size without touching the saved settings'
		);

		await form.locator( '[data-sp-radio-group="theme"] input:checked' ).focus();
		await page.keyboard.press( 'Shift+Tab' );
		await page.keyboard.press( 'Tab' );
		const focusRing = await page.evaluate( () => {
			const active = document.activeElement;
			const label = active ? active.closest( '.sp-wz-choice' ) : null;
			return !! label && 'none' !== getComputedStyle( label ).outlineStyle;
		} );
		check( focusRing, 'Keyboard focus on a choice shows a visible ring' );

		await form.locator( '[data-sp-discard]' ).click();
		await dialog.waitFor( { state: 'visible', timeout: 5000 } );
		await page.keyboard.press( 'Escape' );
		await dialog.waitFor( { state: 'hidden', timeout: 5000 } );
		check( await savebar.isVisible() && 'tl' === await stage.getAttribute( 'data-pos' ), 'Cancelling Discard keeps every unsaved change' );

		await autoOpen.selectOption( '10' );
		const [ saved ] = await Promise.all( [
			page.waitForNavigation( { waitUntil: 'domcontentloaded', timeout: 20000 } ),
			savebar.locator( '.sp-wz-savebar__save' ).click(),
		] );
		await form.waitFor( { state: 'attached', timeout: 10000 } );
		check(
			!! saved && 200 === saved.status() && 'POST' === saved.request().method()
			&& 'tl' === await stage.getAttribute( 'data-pos' )
			&& 'dark' === await stage.getAttribute( 'data-theme' )
			&& await form.locator( '[data-sp-recording]' ).isChecked()
			&& '6' === await form.locator( '[data-sp-radio-group="shape"] input:checked' ).inputValue()
			&& '10' === await form.locator( '[data-sp-auto-open]' ).inputValue()
			&& await savebar.isHidden()
			&& 1 === await page.locator( '.sp-alert--ok' ).count(),
			'Save changes posts the form, confirms it and reopens with the saved settings, including the 10-second automatic opening'
		);

		await choiceLabel( 'position', 'bottom-left' ).click();
		await form.locator( '[data-sp-discard]' ).click();
		await dialog.waitFor( { state: 'visible', timeout: 5000 } );
		const [ discarded ] = await Promise.all( [
			page.waitForNavigation( { waitUntil: 'domcontentloaded', timeout: 20000 } ),
			page.locator( '[data-sp-confirm-dialog] .sp-btn--danger-solid' ).click(),
		] );
		await form.waitFor( { state: 'attached', timeout: 10000 } );
		check(
			!! discarded && 'GET' === discarded.request().method() && 'tl' === await stage.getAttribute( 'data-pos' ) && await savebar.isHidden(),
			'Confirmed Discard reloads the last saved settings with a plain GET'
		);

		check( ! await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 ), 'Widget customisation has no desktop horizontal overflow' );
		if ( ! skipScreenshots ) {
			await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-widget-customise-desktop.png' ), fullPage: true } );
		}

		let originalDir = null;
		if ( expectNativeRtl ) {
			check( 'rtl' === await page.locator( 'html' ).getAttribute( 'dir' ), 'Widget customisation runs inside a genuinely RTL WordPress admin shell' );
			const persian = /[\u0600-\u06FF]/;
			check(
				persian.test( await page.locator( '.sp-wz-intro__title' ).innerText() ) && persian.test( await page.locator( '#sp-wz-privacy-title' ).innerText() ),
				'Widget customisation copy is really Persian in fa_IR'
			);
			check(
				persian.test( await form.locator( '[data-sp-auto-open-field] .sp-wz-field__label' ).innerText() )
				&& persian.test( await form.locator( '[data-sp-auto-open] option[value="0"]' ).innerText() )
				&& persian.test( await form.locator( 'input[name="widget[greeting]"]' ).inputValue() ),
				'In fa_IR the automatic-opening control and the untouched default greeting are Persian'
			);
		} else {
			originalDir = await page.locator( 'html' ).getAttribute( 'dir' );
			await page.locator( 'html' ).evaluate( ( html ) => html.setAttribute( 'dir', 'rtl' ) );
			// Let the direction change settle before measuring.
			await page.waitForTimeout( 120 );
		}
		const rtlState = await page.evaluate( () => {
			const main = document.querySelector( '.sp-wz__main' ).getBoundingClientRect();
			const preview = document.querySelector( '.sp-wz__preview' ).getBoundingClientRect();
			return {
				direction: getComputedStyle( document.querySelector( '.sp-wz' ) ).direction,
				overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
				previewOnLeft: preview.right <= main.left + 1,
			};
		} );
		const rtlOk = 'rtl' === rtlState.direction && ! rtlState.overflow && rtlState.previewOnLeft;
		check( rtlOk, 'Widget customisation mirrors in RTL with the preview on the left and no overflow' );
		if ( ! rtlOk ) {
			console.log( 'WIDGET_CUSTOMISE_RTL_STATE ' + JSON.stringify( rtlState ) );
		}
		if ( ! skipScreenshots ) {
			await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-widget-customise-rtl.png' ), fullPage: true } );
		}
		if ( ! expectNativeRtl ) {
			await page.locator( 'html' ).evaluate( ( html, dir ) => {
				if ( dir ) {
					html.setAttribute( 'dir', dir );
				} else {
					html.removeAttribute( 'dir' );
				}
			}, originalDir );
		}

		await page.setViewportSize( { width: 390, height: 844 } );
		await page.goto( widgetUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
		await form.waitFor( { state: 'attached', timeout: 10000 } );
		await choiceLabel( 'position', 'bottom-right' ).click();
		const mobile = await page.evaluate( () => {
			const main = document.querySelector( '.sp-wz__main' ).getBoundingClientRect();
			const preview = document.querySelector( '.sp-wz__preview' ).getBoundingClientRect();
			const bar = document.querySelector( '.sp-wz-savebar__inner' ).getBoundingClientRect();
			return {
				overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
				stacked: preview.top >= main.bottom - 1,
				barInside: bar.left >= 0 && bar.right <= window.innerWidth + 1 && bar.bottom <= window.innerHeight + 1,
			};
		} );
		const mobileOk = ! mobile.overflow && mobile.stacked && mobile.barInside;
		check( 'rgb(255, 255, 255)' === await page.evaluate( () => getComputedStyle( document.querySelector( '.sp-wz-savebar__inner' ) ).backgroundColor ), 'Widget customisation uses the light unsaved-changes bar' );
		check( mobileOk, 'At 390px the controls stack above the preview and the save bar stays inside the viewport' );
		if ( ! mobileOk ) {
			console.log( 'WIDGET_CUSTOMISE_MOBILE_STATE ' + JSON.stringify( mobile ) );
		}
		if ( ! skipScreenshots ) {
			await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-widget-customise-mobile.png' ) } );
		}
		// Leave the page clean so the next navigation is not held by the unsaved-changes prompt.
		await form.locator( '[data-sp-discard]' ).click();
		await dialog.waitFor( { state: 'visible', timeout: 5000 } );
		await Promise.all( [
			page.waitForNavigation( { waitUntil: 'domcontentloaded', timeout: 20000 } ),
			page.locator( '[data-sp-confirm-dialog] .sp-btn--danger-solid' ).click(),
		] );
		check( 0 === dialogs.filter( ( type ) => 'beforeunload' === type ).length, 'Widget customisation never holds navigation with a leave-page prompt once changes are saved or discarded' );
	} finally {
		page.off( 'request', collectPluginRequest );
		page.off( 'dialog', recordDialog );
		await page.setViewportSize( { width: 1440, height: 960 } );
	}
}

async function widgetVisibilityHealthy( page ) {
	console.log( 'CHECK Pages where the chat appears (F-11): rules, title search, keyboard, save, Widget tab summary, visitor page, RTL and 390px' );
	const widgetUrl = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-widget';
	const tabUrl = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-conversations&tab=widget';
	// Title of the page created by tests/local_widget_customise_browser_fixture.php.
	const qaTitle = 'Visibility QA checkout aisaautopilot-visibility-qa';
	const pluginRequests = [];
	const searchRequests = [];
	const navigations = [];
	const collect = ( request ) => {
		const url = request.url();
		if ( url.includes( '/aisaautopilot/v1/' ) ) {
			pluginRequests.push( url.replace( /^.*\/aisaautopilot\/v1\//, '' ) );
		}
		if ( /wp\/v2\/search/.test( decodeURIComponent( url ) ) ) {
			searchRequests.push( url );
		}
	};
	const recordNavigation = ( frame ) => {
		if ( frame === page.mainFrame() ) {
			navigations.push( frame.url() );
		}
	};
	const dialogs = [];
	const recordDialog = async ( dialog ) => {
		dialogs.push( dialog.type() );
		await dialog.accept();
	};
	await page.setViewportSize( { width: 1440, height: 960 } );
	page.on( 'request', collect );
	page.on( 'dialog', recordDialog );
	try {
		await page.goto( widgetUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
		const form = page.locator( '[data-sp-widget-form]' );
		await form.waitFor( { state: 'attached', timeout: 10000 } );
		const card = form.locator( '#answer-desk-visibility' );
		const savebar = form.locator( '[data-sp-savebar]' );
		const rules = card.locator( '[data-sp-visibility-rules]' );
		const warn = card.locator( '[data-sp-visibility-none]' );
		const search = card.locator( '[data-sp-where-search]' );
		const results = card.locator( '[data-sp-where-results]' );
		const picked = card.locator( '[data-sp-where-picked] [data-sp-where-item]' );
		const status = card.locator( '[data-sp-where-status]' );
		const modeLabel = ( value ) => card.locator( '[data-sp-radio-group="visibility"] label' ).filter( { has: page.locator( 'input[value="' + value + '"]' ) } );

		check(
			await card.isVisible()
			&& await card.locator( 'input[name="widget[widget_visibility]"][value="all"]' ).isChecked()
			&& await rules.isHidden()
			&& await warn.isHidden()
			&& await savebar.isHidden(),
			'The card opens on "Every page" with the rules folded away and no unsaved-changes bar'
		);
		check(
			0 === await card.locator( 'input[name="widget[exclude_types][]"][value="attachment"]' ).count()
			&& 1 === await card.locator( 'input[name="widget[exclude_types][]"][value="page"]' ).count(),
			'Content types list pages and leave media attachments out'
		);

		// Typing a search term is not a settings change; Enter searches instead of submitting the form.
		// The rules are unfolded directly first, so no control has marked the form dirty yet.
		await rules.evaluate( ( node ) => { node.hidden = false; } );
		page.on( 'framenavigated', recordNavigation );
		const firstSearch = page.waitForResponse( ( response ) => /wp\/v2\/search/.test( decodeURIComponent( response.url() ) ), { timeout: 15000 } );
		await search.fill( 'Visibility QA checkout' );
		await search.press( 'Enter' );
		const searchResponse = await firstSearch;
		await results.locator( 'button' ).first().waitFor( { state: 'visible', timeout: 10000 } );
		page.off( 'framenavigated', recordNavigation );
		check(
			200 === searchResponse.status() && 0 === navigations.length && await savebar.isHidden() && 0 === pluginRequests.length,
			'Searching uses the site search route, never submits the form, never raises the save bar and calls no plugin route'
		);
		const hit = results.locator( 'button' ).filter( { hasText: qaTitle } ).first();
		check( 1 === await hit.count() && ! await hit.isDisabled(), 'The published QA page is found by its title' );

		await rules.evaluate( ( node ) => { node.hidden = true; } );
		await modeLabel( 'exclude' ).click();
		check(
			await rules.isVisible() && await warn.isVisible() && await savebar.isVisible(),
			'"Every page except" reveals the rules, warns that nothing is chosen yet and raises the save bar'
		);
		await hit.focus();
		await page.keyboard.press( 'Enter' );
		const item = picked.filter( { hasText: qaTitle } ).first();
		await item.waitFor( { state: 'visible', timeout: 5000 } );
		const qaId = await item.getAttribute( 'data-sp-where-item' );
		check(
			/^\d+$/.test( qaId || '' )
			&& qaId === await item.locator( 'input[type="hidden"][name="widget[exclude_ids][]"]' ).inputValue()
			&& await hit.isDisabled()
			&& await warn.isHidden()
			&& await savebar.isVisible()
			&& '' !== ( await status.innerText() ).trim(),
			'Enter on a result adds the page as a removable row with its ID, clears the warning, says so and raises the save bar'
		);

		await item.locator( '[data-sp-where-remove]' ).focus();
		await page.keyboard.press( 'Enter' );
		check(
			0 === await picked.count()
			&& ! await hit.isDisabled()
			&& await page.evaluate( () => document.activeElement && document.activeElement.hasAttribute( 'data-sp-where-search' ) )
			&& await warn.isVisible(),
			'Removing the only row with the keyboard re-enables its result, returns focus to the search box and brings the warning back'
		);
		await hit.click();
		await card.locator( 'input[name="widget[exclude_types][]"][value="post"]' ).check();

		const [ saved ] = await Promise.all( [
			page.waitForNavigation( { waitUntil: 'domcontentloaded', timeout: 20000 } ),
			savebar.locator( '.sp-wz-savebar__save' ).click(),
		] );
		await form.waitFor( { state: 'attached', timeout: 10000 } );
		check(
			!! saved && 'POST' === saved.request().method() && 200 === saved.status()
			&& await card.locator( 'input[name="widget[widget_visibility]"][value="exclude"]' ).isChecked()
			&& 1 === await card.locator( '[data-sp-where-item="' + qaId + '"]' ).count()
			&& await card.locator( 'input[name="widget[exclude_types][]"][value="post"]' ).isChecked()
			&& await warn.isHidden()
			&& await savebar.isHidden(),
			'Save changes stores the rules and the page reopens with "except", the chosen page by title and Posts ticked'
		);
		check( ! await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 ), 'The card adds no desktop horizontal overflow' );
		if ( ! skipScreenshots ) {
			await card.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-widget-visibility-desktop.png' ) } );
		}

		if ( expectNativeRtl ) {
			const persian = /[؀-ۿ]/;
			check(
				'rtl' === await card.evaluate( ( node ) => getComputedStyle( node ).direction )
				&& persian.test( await card.locator( '#sp-wz-where-title' ).innerText() )
				&& persian.test( ( await search.getAttribute( 'placeholder' ) ) || '' )
				&& persian.test( await modeLabel( 'exclude' ).innerText() ),
				'In fa_IR the card is right-to-left and its title, choices and search placeholder are Persian'
			);
		} else {
			check( 'ltr' === await card.evaluate( ( node ) => getComputedStyle( node ).direction ), 'In en_US the card is left-to-right' );
		}

		await page.goto( tabUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
		const row = page.locator( '[data-sp-widget-where]' );
		await row.waitFor( { state: 'visible', timeout: 10000 } );
		check(
			'limited' === await row.getAttribute( 'data-sp-widget-where' )
			&& ( await row.locator( 'a' ).getAttribute( 'href' ) || '' ).endsWith( '#answer-desk-visibility' ),
			'The Widget tab summarises the saved rules and links straight to the card'
		);
		if ( ! skipScreenshots ) {
			await row.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-widget-visibility-tab-row.png' ) } );
		}

		// The visitor side: the chosen page has no widget, the home page still has it. Public pages of the
		// local theme are slow on a first render, hence the same 90s the visitor widget check allows.
		await page.goto( baseUrl + '/?page_id=' + qaId, { waitUntil: 'domcontentloaded', timeout: 90000 } );
		const hiddenOnPage = 0 === await page.locator( '.spw-root' ).count();
		await page.goto( baseUrl + '/', { waitUntil: 'domcontentloaded', timeout: 90000 } );
		const shownOnHome = 0 < await page.locator( '.spw-root' ).count();
		check( hiddenOnPage && shownOnHome, 'On the public site the chosen page has no chat while the home page keeps it' );

		await page.setViewportSize( { width: 390, height: 844 } );
		await page.goto( widgetUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
		await form.waitFor( { state: 'attached', timeout: 10000 } );
		await search.fill( 'Visibility QA checkout' );
		await search.press( 'Enter' );
		await results.locator( 'button' ).first().waitFor( { state: 'visible', timeout: 10000 } );
		const narrow = await page.evaluate( () => {
			const node = document.querySelector( '#answer-desk-visibility' ).getBoundingClientRect();
			const hitRow = document.querySelector( '[data-sp-where-results] button' ).getBoundingClientRect();
			return {
				overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
				inside: node.left >= 0 && node.right <= window.innerWidth + 1,
				hitInside: hitRow.right <= window.innerWidth + 1,
			};
		} );
		const narrowOk = ! narrow.overflow && narrow.inside && narrow.hitInside;
		check( narrowOk, 'At 390px the card, its search results and the page stay inside the viewport' );
		if ( ! narrowOk ) {
			console.log( 'WIDGET_VISIBILITY_MOBILE_STATE ' + JSON.stringify( narrow ) );
		}
		if ( ! skipScreenshots ) {
			await card.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-widget-visibility-mobile.png' ) } );
		}
		check( 0 === pluginRequests.length, 'The card sent no request to a plugin route' );
		check( searchRequests.length >= 2 && 0 === dialogs.filter( ( type ) => 'beforeunload' === type ).length, 'Searches ran and no leave-page prompt held navigation' );
	} finally {
		page.off( 'request', collect );
		page.off( 'dialog', recordDialog );
		page.off( 'framenavigated', recordNavigation );
		await page.setViewportSize( { width: 1440, height: 960 } );
	}
}

async function advancedSettingsHealthy( page ) {
	console.log( 'CHECK Advanced settings: read-only connection, switches, delivery test, save bar, discard, RTL and 390px' );
	const advancedUrl = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-settings';
	const deliveryPattern = '**/aisaautopilot/v1/forms/delivery/test**';
	const pluginRequests = [];
	const collectPluginRequest = ( request ) => {
		if ( request.url().includes( '/aisaautopilot/v1/' ) ) {
			pluginRequests.push( request.url().replace( /^.*\/aisaautopilot\/v1\//, '' ) );
		}
	};
	// Native dialogs would hold navigation; record them (a leave-page prompt here is a defect) and let the page go.
	const dialogs = [];
	const recordDialog = async ( dialog ) => {
		dialogs.push( dialog.type() );
		await dialog.accept();
	};
	// The delivery test is answered here so no mail is ever sent from browser QA.
	let deliveryCalls = 0;
	const deliveryRoute = async ( route ) => {
		deliveryCalls++;
		await route.fulfill( { status: 200, contentType: 'application/json', body: JSON.stringify( { ok: true, message: 'Delivery test accepted (browser QA stub).' } ) } );
	};
	await page.setViewportSize( { width: 1440, height: 960 } );
	page.on( 'request', collectPluginRequest );
	page.on( 'dialog', recordDialog );
	await page.route( deliveryPattern, deliveryRoute );
	try {
		await page.goto( advancedUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
		const form = page.locator( '[data-sp-advanced-form]' );
		await form.waitFor( { state: 'attached', timeout: 10000 } );
		const savebar = form.locator( '[data-sp-savebar]' );
		const dialog = page.locator( '[data-sp-confirm-dialog] [role="alertdialog"]' );
		const discardAndReload = async () => {
			await form.locator( '[data-sp-discard]' ).click();
			await dialog.waitFor( { state: 'visible', timeout: 5000 } );
			const [ response ] = await Promise.all( [
				page.waitForNavigation( { waitUntil: 'domcontentloaded', timeout: 20000 } ),
				page.locator( '[data-sp-confirm-dialog] .sp-btn--danger-solid' ).click(),
			] );
			await form.waitFor( { state: 'attached', timeout: 10000 } );
			return response;
		};

		check( 0 === pluginRequests.length && await savebar.isHidden(), 'Advanced settings open without REST requests and without an unsaved-changes bar' );
		check(
			6 === await page.locator( '.sp-adv__nav a[href^="#sp-adv-"]' ).count()
			&& 0 === await form.locator( '[name="provider"], [name="api_key"], [name="base_url"], [name="model"], [name="key_management"], [name="temperature"]' ).count()
			&& 0 === await page.locator( '#sp-adv-connection input, #sp-adv-connection select' ).count()
			&& 1 === await page.locator( '#sp-adv-connection a[href*="page=aisaautopilot-setup"]' ).count(),
			'Advanced settings show the AI service read-only with a way back to Setup and never offer its key or model'
		);

		const deliveryResponse = page.waitForResponse( ( response ) => response.url().includes( '/forms/delivery/test' ) );
		await page.locator( '#sp-test-form-delivery' ).click();
		const delivered = await deliveryResponse;
		await page.waitForFunction( () => ( document.getElementById( 'sp-form-delivery-test-result' ).textContent || '' ).trim().length > 0 );
		check(
			1 === deliveryCalls
			&& !! delivered.request().headers()[ 'x-wp-nonce' ]
			&& ( await page.locator( '#sp-form-delivery-test-result' ).innerText() ).includes( 'browser QA stub' )
			&& await savebar.isHidden(),
			'The delivery test uses the nonce-protected route (stubbed, no mail) and is not an unsaved change'
		);

		// Data retention: the real route, which on this site only finds rows older than the saved periods.
		const retentionCard = page.locator( '#sp-adv-retention' );
		const retentionResponse = page.waitForResponse( ( response ) => response.url().includes( '/privacy/retention/run' ) );
		await retentionCard.locator( '[data-sp-retention-run]' ).click();
		const retained = await retentionResponse;
		await page.waitForFunction( () => /is-(ok|err)/.test( document.querySelector( '[data-sp-retention-result]' ).className ) );
		check(
			200 === retained.status()
			&& !! retained.request().headers()[ 'x-wp-nonce' ]
			&& 5 === await retentionCard.locator( 'input[type="number"][name^="retention_"]' ).count()
			&& /is-ok/.test( await retentionCard.locator( '[data-sp-retention-result]' ).getAttribute( 'class' ) )
			&& ( await retentionCard.locator( '[data-sp-retention-summary]' ).innerText() ).trim().length > 0
			&& 2 === await retentionCard.locator( '.sp-adv-cron [data-sp-copy-source]' ).count()
			&& ( await retentionCard.locator( '.sp-adv-cron [data-sp-copy-source]' ).first().textContent() ).includes( 'wp-cron.php?doing_wp_cron' )
			&& await savebar.isHidden(),
			'Data retention shows five periods, runs the cleanup on its nonce-protected route, explains a server cron job, and is not an unsaved change'
		);
		// Keeping undelivered form submissions: off by default, a switch in Forms and delivery.
		const keepSwitch = page.locator( '#sp-adv-keep-undelivered' );
		check(
			1 === await keepSwitch.count() && ! await keepSwitch.isChecked()
			&& 'switch' === await keepSwitch.getAttribute( 'role' )
			&& 1 === await page.locator( '#sp-adv-forms #sp-adv-keep-undelivered' ).count(),
			'Forms and delivery offers "Keep a submission when its email cannot be sent", off by default'
		);
		// Cloudflare Turnstile: two keys in Forms and delivery; the secret is a password field that never shows a value.
		const turnstile = page.locator( '#sp-adv-forms [data-sp-turnstile]' );
		check(
			1 === await turnstile.count()
			&& 1 === await turnstile.locator( 'input[name="turnstile_site_key"]' ).count()
			&& 'password' === await turnstile.locator( 'input[name="turnstile_secret"]' ).getAttribute( 'type' )
			&& '' === await turnstile.locator( 'input[name="turnstile_secret"]' ).inputValue()
			&& 1 === await turnstile.locator( '[data-sp-turnstile-state]' ).count(),
			'Forms and delivery offers optional Cloudflare Turnstile keys; the secret field never shows a saved value'
		);

		const search = form.locator( '#sp-adv-embeddings' );
		const searchFields = form.locator( '[data-sp-adv-fields="search"]' );
		const searchWasOn = await search.isChecked();
		await search.click();
		check(
			searchWasOn === await searchFields.evaluate( ( node ) => node.classList.contains( 'is-muted' ) ) && await savebar.isVisible(),
			'The semantic search switch dims or restores its fields and raises the unsaved-changes bar'
		);

		const preferWasOn = await form.locator( '#sp-adv-prefer-efb' ).isChecked();
		await form.locator( '#sp-adv-prefer-efb' ).click();
		await form.locator( 'select[name="default_form_status"]' ).selectOption( 'publish' );
		const [ saved ] = await Promise.all( [
			page.waitForNavigation( { waitUntil: 'domcontentloaded', timeout: 20000 } ),
			savebar.locator( '.sp-wz-savebar__save' ).click(),
		] );
		await form.waitFor( { state: 'attached', timeout: 10000 } );
		check(
			!! saved && 'POST' === saved.request().method() && 200 === saved.status()
			&& ! searchWasOn === await form.locator( '#sp-adv-embeddings' ).isChecked()
			&& ! preferWasOn === await form.locator( '#sp-adv-prefer-efb' ).isChecked()
			&& 'publish' === await form.locator( 'select[name="default_form_status"]' ).inputValue()
			&& 1 === await page.locator( '.sp-alert--ok' ).count()
			&& await savebar.isHidden(),
			'Save changes stores the switches and choices and reopens with them'
		);

		await form.locator( 'input[name="voice_model"]' ).fill( 'qa-voice-model' );
		const discarded = await discardAndReload();
		check(
			!! discarded && 'GET' === discarded.request().method()
			&& 'qa-voice-model' !== await form.locator( 'input[name="voice_model"]' ).inputValue()
			&& await savebar.isHidden(),
			'Discard reloads the saved advanced settings with a plain GET'
		);

		await page.locator( '.sp-adv__nav a[href="#sp-adv-public"]' ).click();
		const inView = await page.evaluate( () => {
			const rect = document.getElementById( 'sp-adv-public' ).getBoundingClientRect();
			return rect.top >= 0 && rect.top < window.innerHeight;
		} );
		check( inView, 'On this page links move to their section' );

		check( ! await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 ), 'Advanced settings have no desktop horizontal overflow' );
		if ( ! skipScreenshots ) {
			await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-advanced-settings-desktop.png' ), fullPage: true } );
		}

		let originalDir = null;
		if ( expectNativeRtl ) {
			check( 'rtl' === await page.locator( 'html' ).getAttribute( 'dir' ), 'Advanced settings run inside a genuinely RTL WordPress admin shell' );
			const persian = /[\u0600-\u06FF]/;
			check(
				persian.test( await page.locator( '.sp-pagebar__title' ).innerText() ) && persian.test( await page.locator( '#sp-adv-nav-title' ).innerText() ),
				'Advanced settings copy is really Persian in fa_IR'
			);
		} else {
			originalDir = await page.locator( 'html' ).getAttribute( 'dir' );
			await page.locator( 'html' ).evaluate( ( html ) => html.setAttribute( 'dir', 'rtl' ) );
			// Let the direction change settle before measuring.
			await page.waitForTimeout( 120 );
		}
		const rtlState = await page.evaluate( () => {
			const nav = document.querySelector( '.sp-adv__nav' ).getBoundingClientRect();
			const main = document.querySelector( '.sp-adv__main' ).getBoundingClientRect();
			return {
				direction: getComputedStyle( document.querySelector( '.sp-adv' ) ).direction,
				overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
				navOnRight: nav.left >= main.right - 1,
			};
		} );
		const rtlOk = 'rtl' === rtlState.direction && ! rtlState.overflow && rtlState.navOnRight;
		check( rtlOk, 'Advanced settings mirror in RTL with the page navigation on the right and no overflow' );
		if ( ! rtlOk ) {
			console.log( 'ADVANCED_SETTINGS_RTL_STATE ' + JSON.stringify( rtlState ) );
		}
		if ( ! skipScreenshots ) {
			await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-advanced-settings-rtl.png' ), fullPage: true } );
		}
		if ( ! expectNativeRtl ) {
			await page.locator( 'html' ).evaluate( ( html, dir ) => {
				if ( dir ) {
					html.setAttribute( 'dir', dir );
				} else {
					html.removeAttribute( 'dir' );
				}
			}, originalDir );
		}

		await page.setViewportSize( { width: 390, height: 844 } );
		await page.goto( advancedUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
		await form.waitFor( { state: 'attached', timeout: 10000 } );
		await form.locator( 'input[name="voice_model"]' ).fill( 'qa-mobile-voice' );
		const mobile = await page.evaluate( () => {
			const bar = document.querySelector( '.sp-wz-savebar__inner' ).getBoundingClientRect();
			return {
				overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
				navHidden: 'none' === getComputedStyle( document.querySelector( '.sp-adv__nav' ) ).display,
				barInside: bar.left >= 0 && bar.right <= window.innerWidth + 1 && bar.bottom <= window.innerHeight + 1,
			};
		} );
		const mobileOk = ! mobile.overflow && mobile.navHidden && mobile.barInside;
		check( 'rgb(255, 255, 255)' === await page.evaluate( () => getComputedStyle( document.querySelector( '.sp-wz-savebar__inner' ) ).backgroundColor ), 'Advanced settings use the light unsaved-changes bar' );
		check( mobileOk, 'At 390px the page navigation folds away, nothing overflows and the save bar stays inside the viewport' );
		if ( ! mobileOk ) {
			console.log( 'ADVANCED_SETTINGS_MOBILE_STATE ' + JSON.stringify( mobile ) );
		}
		if ( ! skipScreenshots ) {
			await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-advanced-settings-mobile.png' ) } );
		}
		// Leave the page clean so the next navigation is not held by the unsaved-changes prompt.
		await discardAndReload();
		check( 0 === dialogs.filter( ( type ) => 'beforeunload' === type ).length, 'Advanced settings never hold navigation with a leave-page prompt once changes are saved or discarded' );
	} finally {
		page.off( 'request', collectPluginRequest );
		page.off( 'dialog', recordDialog );
		await page.unroute( deliveryPattern, deliveryRoute );
		await page.setViewportSize( { width: 1440, height: 960 } );
	}
}

async function efbDetailsHealthy( page ) {
	console.log( 'CHECK Easy Form Builder copilot details: connection state, allowed and refused lists, links, RTL and 390px' );
	const efbUrl = baseUrl + '/wp-admin/admin.php?page=aisaautopilot-efb';
	const pluginRequests = [];
	const collectPluginRequest = ( request ) => {
		if ( request.url().includes( '/aisaautopilot/v1/' ) ) {
			pluginRequests.push( request.url().replace( /^.*\/aisaautopilot\/v1\//, '' ) );
		}
	};
	await page.setViewportSize( { width: 1440, height: 960 } );
	page.on( 'request', collectPluginRequest );
	try {
		await page.goto( efbUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
		const root = page.locator( '[data-sp-efb]' );
		await root.waitFor( { state: 'attached', timeout: 10000 } );
		const state = await root.getAttribute( 'data-sp-efb-state' );
		check( [ 'available', 'unavailable' ].includes( state ) && 1 === await page.locator( '[data-sp-efb-status]' ).count(), 'The details page states the Easy Form Builder connection' );
		check(
			await page.locator( '[data-sp-efb-op]' ).count() >= 9 && await page.locator( '[data-sp-efb-blocked]' ).count() >= 1,
			'The details page lists what the copilot may change and what it never touches'
		);
		check( 0 === pluginRequests.length, 'The details page is read-only and sends no REST request' );
		check(
			( ( await page.locator( '[data-sp-efb-last] a' ).getAttribute( 'href' ) ) || '' ).includes( 'page=aisaautopilot-logs' ),
			'The last-change card links to the activity log'
		);
		const statusHref = ( await page.locator( '[data-sp-efb-status] a' ).getAttribute( 'href' ) ) || '';
		check(
			'available' === state
				? statusHref.includes( 'page=Emsfb' )
				: ( statusHref.includes( 'update.php?action=install-plugin' ) || statusHref.includes( 'aisaautopilot_activate_dependency' ) || statusHref.includes( 'plugins.php' ) ),
			'The connection card offers the next real step: open the forms, or install, activate or update Easy Form Builder'
		);

		check( ! await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 ), 'The details page has no desktop horizontal overflow' );
		if ( ! skipScreenshots ) {
			await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-efb-details-desktop.png' ), fullPage: true } );
		}

		let originalDir = null;
		if ( expectNativeRtl ) {
			check( 'rtl' === await page.locator( 'html' ).getAttribute( 'dir' ), 'The details page runs inside a genuinely RTL WordPress admin shell' );
			check( /[\u0600-\u06FF]/.test( await page.locator( '#sp-efbx-ops-title' ).innerText() ), 'The details page copy is really Persian in fa_IR' );
		} else {
			originalDir = await page.locator( 'html' ).getAttribute( 'dir' );
			await page.locator( 'html' ).evaluate( ( html ) => html.setAttribute( 'dir', 'rtl' ) );
			// Let the direction change settle before measuring.
			await page.waitForTimeout( 120 );
		}
		const rtlState = await page.evaluate( () => {
			const ops = document.querySelector( '.sp-efbx-ops' ).getBoundingClientRect();
			const side = document.querySelector( '.sp-efbx-side' ).getBoundingClientRect();
			return {
				direction: getComputedStyle( document.querySelector( '.sp-efbx' ) ).direction,
				overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
				sideOnLeft: side.right <= ops.left + 1,
			};
		} );
		const rtlOk = 'rtl' === rtlState.direction && ! rtlState.overflow && rtlState.sideOnLeft;
		check( rtlOk, 'The details page mirrors in RTL with the side cards on the left and no overflow' );
		if ( ! rtlOk ) {
			console.log( 'EFB_DETAILS_RTL_STATE ' + JSON.stringify( rtlState ) );
		}
		if ( ! skipScreenshots ) {
			await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-efb-details-rtl.png' ), fullPage: true } );
		}
		if ( ! expectNativeRtl ) {
			await page.locator( 'html' ).evaluate( ( html, dir ) => {
				if ( dir ) {
					html.setAttribute( 'dir', dir );
				} else {
					html.removeAttribute( 'dir' );
				}
			}, originalDir );
		}

		await page.setViewportSize( { width: 390, height: 844 } );
		await page.goto( efbUrl, { waitUntil: 'domcontentloaded', timeout: 20000 } );
		await root.waitFor( { state: 'attached', timeout: 10000 } );
		const mobile = await page.evaluate( () => {
			const ops = document.querySelector( '.sp-efbx-ops' ).getBoundingClientRect();
			const side = document.querySelector( '.sp-efbx-side' ).getBoundingClientRect();
			return {
				overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
				stacked: side.top >= ops.bottom - 1,
			};
		} );
		check( ! mobile.overflow && mobile.stacked, 'At 390px the side cards stack below the list without horizontal overflow' );
		if ( ! skipScreenshots ) {
			await page.screenshot( { path: path.join( screenshotDir, 'aisaautopilot-efb-details-mobile.png' ), fullPage: true } );
		}
	} finally {
		page.off( 'request', collectPluginRequest );
		await page.setViewportSize( { width: 1440, height: 960 } );
	}
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
		// Diagnostic only: several surface checks answer on purpose with 4xx
		// through page.route (held budget 400, usage cap 429), so these are
		// printed for review rather than asserted here.
		const failedResponses = [];
		page.on( 'response', ( response ) => {
			if ( response.status() >= 400 ) {
				failedResponses.push( response.status() + ' ' + response.request().method() + ' ' + response.url() );
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
		// Wait for the login POST to finish navigating (to wp-admin, the admin-email
		// reminder, or the login page with an error). A fixed 700ms pause read a
		// still-loading login form as rejected credentials on a slow first admin page.
		await Promise.all( [
			page.waitForNavigation( { waitUntil: 'domcontentloaded', timeout: 60000 } ).catch( () => {} ),
			page.locator( '#wp-submit' ).click(),
		] );
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
		if ( checkedPages.includes( 'aisaautopilot-chat' ) ) {
			await chatConsoleHealthy( page );
		}
		if ( checkedPages.includes( 'aisaautopilot-handoffs' ) && handoffInboxFixtureIds ) {
			await handoffInboxHealthy( page );
		}
		if ( checkedPages.includes( 'aisaautopilot-widget' ) && widgetCustomiseFixture ) {
			await widgetCustomiseHealthy( page );
			await widgetVisibilityHealthy( page );
		}
		if ( checkedPages.includes( 'aisaautopilot-settings' ) && advancedSettingsFixture ) {
			await advancedSettingsHealthy( page );
		}
		if ( checkedPages.includes( 'aisaautopilot-efb' ) ) {
			await efbDetailsHealthy( page );
		}
		if ( checkedPages.includes( 'aisaautopilot-setup' ) ) {
			await setupHealthy( page );
		}
		if ( checkedPages.includes( 'aisaautopilot-integrations' ) ) {
			await integrationsHealthy( page );
		}
		if ( checkedPages.includes( 'aisaautopilot-kb' ) ) {
			if ( kbAnswersFixture ) {
				await kbAnswersHealthy( page );
			} else if ( quickKbChecks ) {
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
			await publicPage.locator( '#spw-toggle' ).click();
			// Stop only once recording has really started (getUserMedia is slow on a busy machine),
			// then wait for the upload itself rather than a fixed delay.
			const voicePost = publicPage.waitForRequest( ( request ) => request.url().includes( '/aisaautopilot/v1/public/voice' ) && request.method() === 'POST', { timeout: 15000 } ).then( () => true, () => false );
			await publicPage.locator( '#spw-mic' ).click();
			await publicPage.locator( '#spw-mic[aria-pressed="true"]' ).waitFor( { state: 'attached', timeout: 10000 } ).catch( () => {} );
			await publicPage.waitForTimeout( 700 );
			await publicPage.locator( '#spw-mic' ).click();
			check( await voicePost, 'widget microphone records and posts only to the safe public voice endpoint' );
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
		console.log( 'FAILED_RESPONSES ' + failedResponses.length + ( failedResponses.length ? ' :: ' + failedResponses.join( ' | ' ) : '' ) );
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
