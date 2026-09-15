/* Browser QA for the Copilot injected into the real Easy Form Builder editor. */
'use strict';

const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { chromium } = require( 'playwright-core' );

const baseUrl = ( process.env.AISAAUTOPILOT_BASE_URL || 'http://127.0.0.1/wp' ).replace( /\/$/, '' );
const editUrl = process.env.AISAAUTOPILOT_BROWSER_EFB_URL || '';
const username = process.env.AISAAUTOPILOT_BROWSER_USER || '';
const password = process.env.AISAAUTOPILOT_BROWSER_PASSWORD || '';
const screenshotDir = process.env.AISAAUTOPILOT_SCREENSHOT_DIR || process.env.TEMP;
const chromePath = process.env.AISAAUTOPILOT_CHROME || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const expectRtl = process.env.AISAAUTOPILOT_BROWSER_EXPECT_RTL === '1';
const failures = [];

function check( condition, message ) {
	console.log( ( condition ? 'PASS ' : 'FAIL ' ) + message );
	if ( ! condition ) { failures.push( message ); }
}

async function login( page ) {
	await page.goto( baseUrl + '/wp-login.php', { waitUntil: 'domcontentloaded', timeout: 30000 } );
	await page.locator( '#user_login' ).fill( username );
	await page.locator( '#user_pass' ).fill( password );
	// Only the auth cookie matters: wait for the redirect away from wp-login.php to commit,
	// not for the heavy dashboard DOM (it timed out at 30s on a busy local site). Wrong
	// credentials keep the page on wp-login.php, so this still fails loudly.
	await Promise.all( [ page.waitForURL( ( url ) => ! String( url ).includes( 'wp-login.php' ), { waitUntil: 'commit', timeout: 45000 } ), page.locator( '#wp-submit' ).click() ] );
}

( async () => {
	if ( ! editUrl || ! username || ! password ) { throw new Error( 'EFB URL and browser credentials are required.' ); }
	fs.mkdirSync( screenshotDir, { recursive: true } );
	const browser = await chromium.launch( { executablePath: chromePath, headless: true } );
	const context = await browser.newContext( { viewport: { width: 1440, height: 960 } } );
	const page = await context.newPage();
	const consoleErrors = [];
	const pageErrors = [];
	const failedResponses = [];
	page.on( 'console', ( message ) => { if ( message.type() === 'error' ) { consoleErrors.push( message.text() ); } } );
	page.on( 'pageerror', ( error ) => pageErrors.push( error.message ) );
	page.on( 'response', ( response ) => { if ( response.status() >= 400 ) { failedResponses.push( response.status() + ' ' + response.url() ); } } );

	// Deterministic microphone: a real headless getUserMedia settles at an unpredictable
	// moment, and its "denied" message could land after New chat. Reject at once, as a
	// user who refuses the permission would.
	await page.addInitScript( () => {
		if ( navigator.mediaDevices ) {
			navigator.mediaDevices.getUserMedia = () => Promise.reject( new DOMException( 'Permission denied', 'NotAllowedError' ) );
		}
	} );
	const voiceRequests = [];
	page.on( 'request', ( request ) => { if ( request.url().includes( '/aisaautopilot/v1/efb/voice' ) ) { voiceRequests.push( request.url() ); } } );

	const logStatuses = [];
	page.on( 'response', ( response ) => { if ( response.url().includes( '/aisaautopilot/v1/efb/log' ) ) { logStatuses.push( response.status() ); } } );

	// The local site can hold a real provider key (WordPress Connectors), so a live
	// /efb/chat call could reach a billable provider. Tests never call real providers:
	// answer with the controller's own provider_not_configured shape instead
	// (EfbCopilotController::chat(), 400). /efb/log stays real so its callback is proven.
	const chatRequests = [];
	await page.route( '**/wp-json/aisaautopilot/v1/efb/chat**', ( route ) => {
		chatRequests.push( route.request().url() );
		return route.fulfill( {
			status: 400,
			contentType: 'application/json',
			body: JSON.stringify( { ok: false, code: 'provider_not_configured', type: 'message', reply: 'Add your AI provider API key in AI Site Assistant AutoPilot → Settings first.' } ),
		} );
	} );

	try {
		await login( page );
		const response = await page.goto( editUrl, { waitUntil: 'domcontentloaded', timeout: 45000 } );
		check( response && response.status() === 200, 'real EFB editor loads with HTTP 200' );
		await page.locator( '#aisaautopilot-efb-launch' ).waitFor( { state: 'visible', timeout: 20000 } );
		await page.waitForFunction( () => typeof valj_efb !== 'undefined' && Array.isArray( valj_efb ) && valj_efb.length > 2, undefined, { timeout: 20000 } ); // eslint-disable-line no-undef
		check( await page.locator( '#aisaautopilot-efb-chat' ).isHidden(), 'Copilot starts as the mockup FAB state' );
		const liveFieldIds = await page.evaluate( () => valj_efb.filter( ( item ) => item && item.type && ! [ 'form', 'step', 'option', 'r_matrix' ].includes( item.type ) ).map( ( item ) => String( item.id_ ) ) ); // eslint-disable-line no-undef
		check( liveFieldIds.length === 3, 'real EFB state contains the three temporary form fields' );
		// valj_efb is populated before EFB draws the canvas: wait for the first wrapper before counting.
		if ( liveFieldIds.length ) {
			await page.locator( '.efbField[data-id="' + liveFieldIds[ 0 ] + '-id"]' ).first().waitFor( { state: 'visible', timeout: 20000 } );
		}
		const renderedFieldCount = await page.locator( '.efbField[data-id$="-id"]' ).evaluateAll( ( elements, ids ) => elements.filter( ( element ) => ids.includes( String( element.getAttribute( 'data-id' ) ).slice( 0, -3 ) ) ).length, liveFieldIds );
		check( renderedFieldCount >= 1, 'real EFB canvas renders a selectable temporary field' );
		const editorDirection = await page.locator( 'html' ).evaluate( ( element ) => getComputedStyle( element ).direction );
		check( editorDirection === ( expectRtl ? 'rtl' : 'ltr' ), 'EFB editor uses the expected locale direction' );
		await page.screenshot( { path: path.join( screenshotDir, 'efb-copilot-fab-desktop.png' ), fullPage: true } );

		await page.locator( '#aisaautopilot-efb-launch' ).click();
		const chat = page.locator( '#aisaautopilot-efb-chat' );
		await chat.waitFor( { state: 'visible' } );
		await page.waitForTimeout( 220 );
		const desktopBox = await chat.boundingBox();
		check( desktopBox && Math.abs( desktopBox.width - 388 ) <= 3 && Math.abs( desktopBox.height - 576 ) <= 3, 'desktop chat matches the 388×576 mockup panel' );
		if ( desktopBox ) { console.log( 'PANEL_BOX ' + JSON.stringify( desktopBox ) ); }
		check( await page.locator( '#aisaautopilot-efb-messages .aisaautopilot-efb-msg--ai' ).count() === 1, 'opening renders one truthful welcome message' );
		await page.screenshot( { path: path.join( screenshotDir, 'efb-copilot-chat-desktop.png' ), fullPage: true } );

		const field = page.locator( '.efbField[data-id="' + liveFieldIds[ 0 ] + '-id"]' ).first();
		const fieldDataId = await field.getAttribute( 'data-id' );
		const selectedId = String( fieldDataId ).slice( 0, -3 );
		await field.click( { position: { x: 8, y: 8 } } );
		check( await page.locator( '#aisaautopilot-efb-selection' ).isVisible(), 'clicking a real EFB field opens the selected-field banner' );
		check( await field.evaluate( ( element ) => element.classList.contains( 'aisaautopilot-efb-field-selected' ) ), 'selected real field receives the visual selection state' );
		check( ( await page.locator( '#aisaautopilot-efb-selection-label' ).innerText() ).trim().length > 0, 'selection banner uses the real field label' );

		await page.locator( '#aisaautopilot-efb-selection-clear' ).click();
		check( await page.locator( '#aisaautopilot-efb-selection' ).isHidden(), 'Clear removes selected-field context' );
		check( ! await field.evaluate( ( element ) => element.classList.contains( 'aisaautopilot-efb-field-selected' ) ), 'Clear removes the canvas highlight' );

		// Inner controls also carry .efbField with data-id="<id>-el"; closest() stops on them
		// (efb-copilot.js selectFieldFromTarget). A real pointer click must still select the field.
		const innerDataId = await page.locator( '.efbField[data-id$="-el"]' ).evaluateAll( ( elements, ids ) => {
			const hit = elements.find( ( element ) => ids.includes( String( element.getAttribute( 'data-id' ) ).slice( 0, -3 ) ) && element.getBoundingClientRect().width > 0 );
			return hit ? String( hit.getAttribute( 'data-id' ) ) : '';
		}, liveFieldIds );
		check( '' !== innerDataId, 'real EFB canvas renders a visible inner field control (-el)' );
		if ( innerDataId ) {
			const innerFieldId = innerDataId.slice( 0, -3 );
			const inner = page.locator( '.efbField[data-id="' + innerDataId + '"]' ).first();
			const innerState = await inner.evaluate( ( element ) => {
				const stop = element.closest( '.efbField[data-id]' );
				return { tag: element.tagName.toLowerCase(), disabled: !! element.disabled, readOnly: !! element.readOnly, closestDataId: stop ? stop.getAttribute( 'data-id' ) : '' };
			} );
			await inner.scrollIntoViewIfNeeded();
			const hitTest = await inner.evaluate( ( element ) => {
				const box = element.getBoundingClientRect();
				const x = box.left + ( box.width / 2 );
				const y = box.top + ( box.height / 2 );
				const top = document.elementFromPoint( x, y );
				const topField = top && top.closest ? top.closest( '.efbField[data-id]' ) : null;
				return {
					x,
					y,
					topTag: top ? top.tagName.toLowerCase() : '',
					topId: top && top.id ? top.id : '',
					topClass: top ? String( top.className || '' ).slice( 0, 120 ) : '',
					topIsControl: top === element,
					topClosestDataId: topField ? topField.getAttribute( 'data-id' ) : '',
					pointerEvents: getComputedStyle( element ).pointerEvents,
				};
			} );
			console.log( 'INNER_CONTROL ' + JSON.stringify( innerState ) );
			console.log( 'INNER_HIT_TEST ' + JSON.stringify( hitTest ) );
			// A real pointer click at the control's centre, exactly where a user would click.
			// Playwright's actionability click is not used: EFB may cover the control, and the
			// question is what a genuine click at that point selects.
			await page.mouse.click( hitTest.x, hitTest.y );
			const innerBannerVisible = await page.locator( '#aisaautopilot-efb-selection' ).isVisible();
			check( innerBannerVisible, 'a real click on the inner control (-el) opens the selected-field banner' );
			check( await page.locator( '.efbField[data-id="' + innerFieldId + '-id"]' ).first().evaluate( ( element ) => element.classList.contains( 'aisaautopilot-efb-field-selected' ) ), 'a real click on the inner control highlights its own field wrapper' );
			if ( innerBannerVisible ) {
				await page.locator( '#aisaautopilot-efb-selection-clear' ).click();
			}
		}
		await field.click( { position: { x: 8, y: 8 } } );

		const requestPromise = page.waitForRequest( ( request ) => request.url().includes( '/efb/chat' ) && request.method() === 'POST' );
		let logResponsesSeen = 0;
		const twoLogResponses = page.waitForResponse( ( item ) => item.url().includes( '/aisaautopilot/v1/efb/log' ) && ++logResponsesSeen >= 2, { timeout: 15000 } );
		await page.locator( '#aisaautopilot-efb-input' ).fill( 'Make this required' );
		await page.locator( '#aisaautopilot-efb-send' ).click();
		const request = await requestPromise;
		const payload = request.postDataJSON();
		check( payload.selected_field === selectedId, 'chat payload sends only the selected real field ID' );
		check( payload.fields.some( ( item ) => item.id === selectedId ), 'selected ID is also present in the current live field catalogue' );
		await page.waitForFunction( () => ! document.querySelector( '#aisaautopilot-efb-send' ).disabled, undefined, { timeout: 15000 } );
		await twoLogResponses.catch( () => {} );
		console.log( 'LOG_RESPONSES ' + JSON.stringify( logStatuses ) );
		check( logStatuses.length >= 2 && logStatuses.every( ( status ) => 200 === status ), 'client_log reports (chat_submitted, chat_response_error) reach /efb/log with HTTP 200' );
		check( 1 === chatRequests.length, 'chat is answered by the provider-free mock; no real provider is reached' );

		const beforeVoiceRequests = chatRequests.length;
		const aiBeforeVoice = await page.locator( '#aisaautopilot-efb-messages .aisaautopilot-efb-msg--ai' ).count();
		await page.locator( '#aisaautopilot-efb-live-voice' ).click();
		let voiceFallbackShown = true;
		try {
			await page.waitForFunction( ( count ) => document.querySelectorAll( '#aisaautopilot-efb-messages .aisaautopilot-efb-msg--ai' ).length > count, aiBeforeVoice, { timeout: 10000 } );
		} catch ( error ) {
			voiceFallbackShown = false;
		}
		check( voiceFallbackShown, 'denied microphone shows an honest type-instead message' );
		check( beforeVoiceRequests === chatRequests.length, 'voice shortcut never auto-sends a form command' );
		check( 0 === voiceRequests.length, 'denied microphone never uploads audio for transcription' );

		const beforeReset = await page.locator( '#aisaautopilot-efb-messages .aisaautopilot-efb-msg' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => ( node.classList.contains( 'aisaautopilot-efb-msg--ai' ) ? 'ai:' : 'user:' ) + String( node.textContent || '' ).trim().slice( 0, 60 ) ) );
		console.log( 'MESSAGES_BEFORE_RESET ' + JSON.stringify( beforeReset ) );
		await page.locator( '#aisaautopilot-efb-reset' ).click();
		const afterReset = await page.locator( '#aisaautopilot-efb-messages .aisaautopilot-efb-msg' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => ( node.classList.contains( 'aisaautopilot-efb-msg--ai' ) ? 'ai:' : 'user:' ) + String( node.textContent || '' ).trim().slice( 0, 60 ) ) );
		console.log( 'MESSAGES_AFTER_RESET ' + JSON.stringify( afterReset ) );
		check( await page.locator( '#aisaautopilot-efb-messages .aisaautopilot-efb-msg--ai' ).count() === 1, 'New chat clears history and renders one welcome message' );
		await page.keyboard.press( 'Escape' );
		check( await chat.isHidden(), 'Escape closes the Copilot panel' );
		check( await page.locator( '#aisaautopilot-efb-launch' ).evaluate( ( element ) => document.activeElement === element ), 'Escape returns focus to the FAB' );

		await page.setViewportSize( { width: 390, height: 844 } );
		await page.locator( '#aisaautopilot-efb-launch' ).click();
		await chat.waitFor( { state: 'visible' } );
		const mobile = await chat.evaluate( ( element ) => {
			const box = element.getBoundingClientRect();
			return { left: box.left, right: box.right, top: box.top, bottom: box.bottom, innerOverflow: element.scrollWidth > element.clientWidth + 1 };
		} );
		check( mobile.left >= 0 && mobile.right <= 390 && mobile.top >= 0 && mobile.bottom <= 844, '390px chat stays fully inside the viewport' );
		check( ! mobile.innerOverflow, '390px chat has no internal horizontal overflow' );
		check( ( await chat.evaluate( ( element ) => getComputedStyle( element ).direction ) ) === ( expectRtl ? 'rtl' : 'ltr' ), 'chat inherits the native locale direction' );
		await page.screenshot( { path: path.join( screenshotDir, 'efb-copilot-chat-mobile.png' ), fullPage: true } );

		const unexpectedFailedResponses = failedResponses.filter( ( item ) => ! ( item.includes( '/efb/chat' ) && item.startsWith( '400 ' ) ) && ! item.includes( '/wp-json/jetpack/' ) );
		const unexpectedConsoleErrors = consoleErrors.filter( ( item ) => ! item.includes( 'the server responded with a status of 400' ) );
		check( unexpectedConsoleErrors.length === 0, 'EFB Copilot flow has no unexpected console errors' );
		check( unexpectedFailedResponses.length === 0, 'EFB Copilot flow has no unexpected HTTP failures' );
		check( pageErrors.length === 0, 'EFB Copilot flow has no uncaught page errors' );
		if ( unexpectedConsoleErrors.length ) { console.log( 'CONSOLE_ERRORS ' + unexpectedConsoleErrors.join( ' | ' ).slice( 0, 1000 ) ); }
		if ( unexpectedFailedResponses.length ) { console.log( 'FAILED_RESPONSES ' + unexpectedFailedResponses.join( ' | ' ).slice( 0, 1500 ) ); }
		if ( pageErrors.length ) { console.log( 'PAGE_ERRORS ' + pageErrors.join( ' | ' ).slice( 0, 1000 ) ); }
	} finally {
		await browser.close();
	}
	process.exitCode = failures.length ? 1 : 0;
} )().catch( ( error ) => { console.error( error.stack || error.message ); process.exitCode = 1; } );
