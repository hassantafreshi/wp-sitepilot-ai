/*
 * Admin information-architecture browser check (T56, D-23).
 *
 * Proves the eight-item sidebar, the four Overview tabs, the Form Studio page
 * and the legacy deep links in a real browser, at 1440x960 and 390x844, in the
 * locale of the temporary QA account. Read-only: it navigates and measures,
 * it never saves, so it needs no fixture. Credentials come from the
 * environment only and never reach stdout.
 */
'use strict';

const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { chromium } = require( process.env.AISAAUTOPILOT_PLAYWRIGHT_CORE || 'playwright-core' );

const baseUrl = ( process.env.AISAAUTOPILOT_BASE_URL || 'http://127.0.0.1/wp' ).replace( /\/$/, '' );
const username = process.env.AISAAUTOPILOT_BROWSER_USER || '';
const password = process.env.AISAAUTOPILOT_BROWSER_PASSWORD || '';
const chromePath = process.env.AISAAUTOPILOT_CHROME || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const expectRtl = process.env.AISAAUTOPILOT_BROWSER_EXPECT_RTL === '1';
const screenshotDir = process.env.AISAAUTOPILOT_SCREENSHOT_DIR || path.join( process.env.TEMP || '.', 'aisa-admin-ia' );
const failures = [];
const PERSIAN = /[\u0600-\u06FF]/;

// D-23 (2026-09-17): exactly these eight visible items, in this order.
const MAIN_PAGES = [
	{ slug: 'aisaautopilot-home', label: 'Overview' },
	{ slug: 'aisaautopilot-setup', label: 'Setup & Connection' },
	{ slug: 'aisaautopilot-integrations', label: 'Integrations' },
	{ slug: 'aisaautopilot-kb', label: 'Site Knowledge' },
	{ slug: 'aisaautopilot-conversations', label: 'Answer Desk' },
	{ slug: 'aisaautopilot-wc-dashboard', label: 'Store Copilot' },
	{ slug: 'aisaautopilot-form-builder', label: 'Content Studio' },
	{ slug: 'aisaautopilot-form-studio', label: 'Form Studio' },
];
// The four Overview tabs: each is its own real page; the last three are hidden aliases owned by Overview.
const OVERVIEW_TABS = [
	{ slug: 'aisaautopilot-home', label: 'Overview' },
	{ slug: 'aisaautopilot-usage', label: 'Usage & Cost' },
	{ slug: 'aisaautopilot-connections', label: 'Health' },
	{ slug: 'aisaautopilot-logs', label: 'Activity Log' },
];
const VIEWPORTS = [
	{ name: '1440', width: 1440, height: 960 },
	{ name: '390', width: 390, height: 844 },
];

function check( condition, message ) {
	if ( condition ) {
		console.log( 'PASS ' + message );
	} else {
		console.log( 'FAIL ' + message );
		failures.push( message );
	}
}

function pageUrl( query ) {
	return baseUrl + '/wp-admin/admin.php?' + query;
}

function slugOf( href ) {
	const match = /[?&]page=([^&#]+)/.exec( href || '' );
	return match ? decodeURIComponent( match[ 1 ] ) : '';
}

function modeOf( href ) {
	const match = /[?&]mode=([^&#]+)/.exec( href || '' );
	return match ? decodeURIComponent( match[ 1 ] ) : '';
}

async function open( page, query ) {
	let response;
	let lastError;
	for ( let attempt = 1; attempt <= 2; attempt++ ) {
		try {
			response = await page.goto( pageUrl( query ), { waitUntil: 'domcontentloaded', timeout: 60000 } );
			break;
		} catch ( error ) {
			lastError = error;
			console.log( 'RETRY ' + query + ' after a local-server timeout' );
		}
	}
	if ( ! response ) {
		throw lastError;
	}
	await page.locator( '.sp-app' ).first().waitFor( { state: 'attached', timeout: 15000 } ).catch( () => {} );
	return response;
}

/** Visible top-level sidebar items of the AutoPilot menu, in DOM order. */
async function sidebarItems( page ) {
	return page.$$eval( '#toplevel_page_aisaautopilot-home .wp-submenu > li:not(.wp-submenu-head) > a', ( links ) => links.map( ( a ) => ( {
		href: a.getAttribute( 'href' ) || '',
		// The Answer Desk label may carry a count bubble; compare only the label text.
		label: ( a.childNodes[ 0 ] && a.childNodes[ 0 ].nodeType === 3 ? a.childNodes[ 0 ].textContent : a.textContent ).trim(),
		current: a.parentElement.classList.contains( 'current' ),
	} ) ) );
}

async function pageBasics( page, query, viewport, expectedOwner ) {
	const tag = query + ' @' + viewport.name;
	const response = await open( page, query );
	check( response.status() === 200, tag + ' loads with HTTP 200 (got ' + response.status() + ')' );
	const bodyText = await page.locator( 'body' ).innerText();
	check( ! bodyText.includes( 'There has been a critical error' ), tag + ' has no WordPress fatal-error screen' );
	check( await page.locator( '.sp-app' ).count() >= 1, tag + ' renders the shared .sp-app shell' );
	const direction = await page.locator( '.sp-app' ).first().evaluate( ( el ) => getComputedStyle( el ).direction ).catch( () => '' );
	check( direction === ( expectRtl ? 'rtl' : 'ltr' ), tag + ' computed direction is ' + ( expectRtl ? 'rtl' : 'ltr' ) + ' (got ' + direction + ')' );
	const overflow = await page.evaluate( () => ( {
		doc: document.documentElement.scrollWidth - document.documentElement.clientWidth,
		body: document.body.scrollWidth - document.body.clientWidth,
	} ) );
	check( overflow.doc <= 1 && overflow.body <= 1, tag + ' has no horizontal overflow (doc +' + overflow.doc + 'px, body +' + overflow.body + 'px)' );
	if ( '390' === viewport.name ) {
		const appBox = await page.locator( '.sp-app' ).first().boundingBox();
		check( !! appBox && appBox.x >= -1 && appBox.x + appBox.width <= viewport.width + 1, tag + ' .sp-app stays inside the 390px viewport' );
	}
	const heading = ( await page.locator( '.sp-app h1' ).first().innerText().catch( () => '' ) ).trim();
	check( heading.length > 0, tag + ' shows a page heading' );
	if ( expectRtl ) {
		check( PERSIAN.test( heading ), tag + ' heading is Persian in fa_IR ("' + heading + '")' );
	}
	// The sidebar stays expanded on AutoPilot and highlights the owning main page.
	const items = await sidebarItems( page );
	const current = items.filter( ( item ) => item.current );
	check( current.length === 1 && slugOf( current[ 0 ].href ) === expectedOwner, tag + ' sidebar highlights exactly ' + expectedOwner + ' (got ' + current.map( ( item ) => slugOf( item.href ) ).join( ',' ) + ')' );
	check( await page.locator( '#toplevel_page_aisaautopilot-home.wp-menu-open, #toplevel_page_aisaautopilot-home.wp-has-current-submenu' ).count() === 1, tag + ' AutoPilot menu stays open' );
	return heading;
}

async function checkSidebar( page ) {
	console.log( 'CHECK sidebar (D-23)' );
	await open( page, 'page=aisaautopilot-home' );
	const items = await sidebarItems( page );
	check( items.length === 8, 'sidebar shows exactly 8 visible AutoPilot items (got ' + items.length + ')' );
	const slugs = items.map( ( item ) => slugOf( item.href ) );
	check( JSON.stringify( slugs ) === JSON.stringify( MAIN_PAGES.map( ( p ) => p.slug ) ), 'sidebar order matches D-23 (' + slugs.join( ' > ' ) + ')' );
	for ( const [ index, expected ] of MAIN_PAGES.entries() ) {
		const label = items[ index ] ? items[ index ].label : '';
		if ( expectRtl ) {
			check( PERSIAN.test( label ), 'sidebar item ' + expected.slug + ' is labelled in Persian ("' + label + '")' );
		} else {
			check( label === expected.label, 'sidebar item ' + expected.slug + ' is labelled "' + expected.label + '" (got "' + label + '")' );
		}
	}
	// Legacy slugs stay registered but hidden: none of them may appear in the sidebar.
	const hidden = [ 'aisaautopilot-usage', 'aisaautopilot-connections', 'aisaautopilot-logs', 'aisaautopilot-settings', 'aisaautopilot-forms', 'aisaautopilot-history', 'aisaautopilot-chat', 'aisaautopilot-efb', 'aisaautopilot-woocommerce', 'aisaautopilot-widget', 'aisaautopilot-handoffs' ];
	check( hidden.every( ( slug ) => ! slugs.includes( slug ) ), 'no legacy alias is shown in the sidebar' );
	return items;
}

async function checkOverviewTabs( page, viewport ) {
	for ( const tab of OVERVIEW_TABS ) {
		const tag = 'Overview tab ' + tab.slug + ' @' + viewport.name;
		await open( page, 'page=' + tab.slug );
		const nav = page.locator( 'nav.sp-overview-tabs' );
		check( await nav.count() === 1, tag + ' renders exactly one Overview tab bar' );
		const links = await nav.locator( 'a' ).evaluateAll( ( as ) => as.map( ( a ) => ( {
			href: a.getAttribute( 'href' ) || '',
			text: a.textContent.trim(),
			current: a.getAttribute( 'aria-current' ),
			on: a.classList.contains( 'is-on' ),
		} ) ) );
		check( links.length === 4, tag + ' tab bar has 4 tabs (got ' + links.length + ')' );
		check( JSON.stringify( links.map( ( l ) => slugOf( l.href ) ) ) === JSON.stringify( OVERVIEW_TABS.map( ( t ) => t.slug ) ), tag + ' tab order is Overview, Usage & Cost, Health, Activity Log' );
		const active = links.filter( ( l ) => 'page' === l.current );
		check( active.length === 1 && slugOf( active[ 0 ].href ) === tab.slug && active[ 0 ].on, tag + ' marks only its own tab with aria-current="page" and is-on' );
		for ( const [ index, expected ] of OVERVIEW_TABS.entries() ) {
			const text = links[ index ] ? links[ index ].text : '';
			if ( expectRtl ) {
				check( PERSIAN.test( text ), tag + ' tab ' + expected.slug + ' label is Persian ("' + text + '")' );
			} else {
				check( text === expected.label, tag + ' tab ' + expected.slug + ' label is "' + expected.label + '" (got "' + text + '")' );
			}
		}
		// T56 visual finding: the tab row touched the first card. The next visible block must sit clear of it.
		const gap = await nav.evaluate( ( el ) => {
			let next = el.nextElementSibling;
			while ( next && ( ! next.offsetParent || next.getBoundingClientRect().height === 0 ) ) {
				next = next.nextElementSibling;
			}
			// With the body's top padding dropped after a bar, its top edge is the first card's top edge.
			return next ? Math.round( next.getBoundingClientRect().top - el.getBoundingClientRect().bottom ) : null;
		} );
		check( null !== gap && gap >= 16, tag + ' leaves at least 16px between the tab bar and the content (got ' + gap + 'px)' );
		const navBox = await nav.boundingBox();
		check( !! navBox && navBox.x >= -1 && navBox.x + navBox.width <= viewport.width + 1, tag + ' tab bar fits inside the viewport' );
		const tabBoxes = await nav.locator( 'a' ).evaluateAll( ( as ) => as.map( ( a ) => {
			const r = a.getBoundingClientRect();
			return { left: r.left, right: r.right, top: r.top, height: r.height, width: r.width };
		} ) );
		check( tabBoxes.every( ( r ) => r.left >= -1 && r.right <= viewport.width + 1 ), tag + ' every tab is fully visible (no clipped tab)' );
		if ( '390' === viewport.name ) {
			const rows = new Set( tabBoxes.map( ( r ) => Math.round( r.top ) ) );
			const widths = tabBoxes.map( ( r ) => Math.round( r.width ) );
			// Sub-pixel positions put float noise on the rect (43.99998 for a 44px min-height); a real
			// shortfall is at least one layout unit (1/64px), which this still catches.
			check( rows.size === 2 && Math.max( ...widths ) - Math.min( ...widths ) <= 1 && tabBoxes.every( ( r ) => r.height > 44 - 1 / 64 ), tag + ' phone tab bar is a 2 x 2 grid of equal tabs with 44px targets (rows ' + rows.size + ', widths ' + widths.join( '/' ) + ', heights ' + tabBoxes.map( ( r ) => Math.round( r.height ) ).join( '/' ) + ')' );
		}
	}
}

async function checkKeyboard( page ) {
	console.log( 'CHECK keyboard on the Overview tab bar' );
	await page.setViewportSize( { width: 1440, height: 960 } );
	await open( page, 'page=aisaautopilot-home' );
	const tabs = page.locator( 'nav.sp-overview-tabs a' );
	await tabs.nth( 0 ).focus();
	await page.keyboard.press( 'Tab' );
	const focused = await page.evaluate( () => {
		const el = document.activeElement;
		const cs = getComputedStyle( el );
		return {
			href: el.getAttribute( 'href' ) || '',
			inTabs: !! el.closest( 'nav.sp-overview-tabs' ),
			focusVisible: el.matches( ':focus-visible' ),
			outline: cs.outlineStyle !== 'none' && parseFloat( cs.outlineWidth ) > 0,
			shadow: cs.boxShadow && cs.boxShadow !== 'none',
		};
	} );
	check( focused.inTabs && slugOf( focused.href ) === 'aisaautopilot-usage', 'Tab moves from the Overview tab to the Usage & Cost tab (got ' + slugOf( focused.href ) + ')' );
	check( focused.focusVisible && ( focused.outline || focused.shadow ), 'keyboard focus on a tab is visible (outline or box-shadow)' );
	await page.screenshot( { path: path.join( screenshotDir, 'overview-tabs-focus-1440.png' ), clip: await tabBarClip( page ) } );
	await Promise.all( [
		page.waitForURL( /page=aisaautopilot-usage/, { timeout: 60000, waitUntil: 'domcontentloaded' } ),
		page.keyboard.press( 'Enter' ),
	] );
	check( slugOf( page.url() ) === 'aisaautopilot-usage', 'Enter on the focused tab opens Usage & Cost' );
	await page.locator( 'nav.sp-overview-tabs' ).waitFor( { state: 'attached', timeout: 15000 } );
	const activeSlug = slugOf( await page.locator( 'nav.sp-overview-tabs a[aria-current="page"]' ).getAttribute( 'href' ) );
	check( activeSlug === 'aisaautopilot-usage', 'after Enter the Usage & Cost tab is the current one' );
}

async function tabBarClip( page ) {
	const box = await page.locator( 'nav.sp-overview-tabs' ).boundingBox();
	return { x: Math.max( 0, box.x - 12 ), y: Math.max( 0, box.y - 12 ), width: box.width + 24, height: box.height + 24 };
}

async function checkFormStudio( page, viewport, sidebarLabel ) {
	const tag = 'Form Studio @' + viewport.name;
	await open( page, 'page=aisaautopilot-form-studio' );
	check( await page.locator( '#aisaautopilot-compose-screen' ).count() === 1, tag + ' has one compose screen' );
	check( await page.locator( '#aisaautopilot-review-screen' ).count() === 1, tag + ' has one review screen' );
	check( await page.locator( '#aisaautopilot-compose-screen' ).isVisible(), tag + ' starts on the compose screen' );
	check( ! ( await page.locator( '#aisaautopilot-review-screen' ).isVisible() ), tag + ' review screen is not initially visible' );
	const heading = ( await page.locator( '.sp-fc-header h1' ).innerText() ).trim();
	check( heading === sidebarLabel, tag + ' heading equals its sidebar label ("' + heading + '" / "' + sidebarLabel + '")' );
	if ( ! expectRtl ) {
		check( heading === 'Form Studio' && ! /Form Composer/.test( await page.locator( '.sp-app' ).innerText() ), tag + ' is titled "Form Studio" and shows no "Form Composer" label' );
	}
	// Legacy deep link: Content Studio's mode=forms still renders the same composer, without a redirect.
	const legacy = await open( page, 'page=aisaautopilot-form-builder&mode=forms' );
	check( legacy.status() === 200 && slugOf( page.url() ) === 'aisaautopilot-form-builder' && modeOf( page.url() ) === 'forms', tag + ' legacy form-builder&mode=forms URL still resolves, without a redirect' );
	check( await page.locator( '#aisaautopilot-compose-screen' ).count() === 1, tag + ' legacy deep link renders the same compose screen' );
	const legacyHeading = ( await page.locator( '.sp-fc-header h1' ).innerText().catch( () => '' ) ).trim();
	check( legacyHeading === heading, tag + ' legacy deep link shows the same Form Studio heading' );
}

( async () => {
	if ( ! username || ! password ) {
		throw new Error( 'AISAAUTOPILOT_BROWSER_USER and AISAAUTOPILOT_BROWSER_PASSWORD are required.' );
	}
	fs.mkdirSync( screenshotDir, { recursive: true } );
	const consoleErrors = [];
	const pageErrors = [];
	const failedResponses = [];
	let browser;
	try {
		browser = await chromium.launch( { headless: true, executablePath: chromePath } );
		const context = await browser.newContext( { viewport: { width: 1440, height: 960 } } );
		const page = await context.newPage();
		page.on( 'console', ( msg ) => {
			if ( 'error' === msg.type() ) {
				consoleErrors.push( msg.text() + ' @ ' + ( msg.location().url || page.url() ) );
			}
		} );
		page.on( 'pageerror', ( error ) => pageErrors.push( error.message + ' @ ' + page.url() ) );
		page.on( 'response', ( response ) => {
			const url = response.url();
			// Jetpack's own REST probes may 4xx on a local site; they are not this plugin.
			if ( response.status() >= 400 && ! url.includes( '/wp-json/jetpack/' ) ) {
				failedResponses.push( response.status() + ' ' + url );
			}
		} );

		console.log( 'LOGIN' );
		await page.goto( baseUrl + '/wp-login.php', { waitUntil: 'domcontentloaded', timeout: 60000 } );
		await page.locator( '#user_login' ).fill( username );
		await page.locator( '#user_pass' ).fill( password );
		await Promise.all( [
			page.waitForURL( ( url ) => ! url.pathname.endsWith( '/wp-login.php' ), { timeout: 60000, waitUntil: 'commit' } ),
			page.locator( '#wp-submit' ).click( { timeout: 60000 } ),
		] );
		const lang = await open( page, 'page=aisaautopilot-home' ).then( () => page.locator( 'html' ).getAttribute( 'lang' ) );
		check( expectRtl ? /^fa/.test( lang || '' ) : /^en/.test( lang || '' ), 'admin language is ' + ( expectRtl ? 'fa' : 'en' ) + ' (html[lang]=' + lang + ')' );

		const items = await checkSidebar( page );
		const formStudioLabel = items[ 7 ] ? items[ 7 ].label : '';

		for ( const viewport of VIEWPORTS ) {
			await page.setViewportSize( { width: viewport.width, height: viewport.height } );
			console.log( 'CHECK main pages @' + viewport.name );
			for ( const main of MAIN_PAGES ) {
				await pageBasics( page, 'page=' + main.slug, viewport, main.slug );
				await page.screenshot( { path: path.join( screenshotDir, 'main-' + main.slug + '-' + viewport.name + '.png' ), fullPage: '1440' === viewport.name } );
			}
			console.log( 'CHECK Overview tabs @' + viewport.name );
			for ( const tab of OVERVIEW_TABS.slice( 1 ) ) {
				await pageBasics( page, 'page=' + tab.slug, viewport, 'aisaautopilot-home' );
				await page.screenshot( { path: path.join( screenshotDir, 'overview-tab-' + tab.slug + '-' + viewport.name + '.png' ), fullPage: '1440' === viewport.name } );
			}
			await checkOverviewTabs( page, viewport );
			console.log( 'CHECK Form Studio @' + viewport.name );
			await checkFormStudio( page, viewport, formStudioLabel );
			await page.screenshot( { path: path.join( screenshotDir, 'legacy-form-builder-mode-forms-' + viewport.name + '.png' ) } );
		}
		await checkKeyboard( page );

		check( consoleErrors.length === 0, 'no console errors on any page (' + consoleErrors.length + ')' );
		consoleErrors.forEach( ( line ) => console.log( 'CONSOLE_ERROR ' + line.replace( /[\r\n]+/g, ' ' ) ) );
		check( pageErrors.length === 0, 'no uncaught page errors (' + pageErrors.length + ')' );
		pageErrors.forEach( ( line ) => console.log( 'PAGE_ERROR ' + line.replace( /[\r\n]+/g, ' ' ) ) );
		check( failedResponses.length === 0, 'no HTTP responses >= 400 (' + failedResponses.length + ')' );
		failedResponses.forEach( ( line ) => console.log( 'FAILED_RESPONSES ' + line ) );
	} finally {
		if ( browser ) {
			await browser.close();
		}
	}
	console.log( 'COMPLETE admin IA assertions (' + failures.length + ' failures)' );
	process.exitCode = failures.length ? 1 : 0;
} )().catch( ( error ) => {
	console.error( error.stack || error.message );
	process.exitCode = 1;
} );
