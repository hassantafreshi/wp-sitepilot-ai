/* Browser QA for the Copilot injected into the real Easy Form Builder editor. */
'use strict';

const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { chromium } = require( 'playwright-core' );
const { fulfillScripted, runT74 } = require( './efb_copilot_t74_steps.cjs' );

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
	// The login POST itself can take longer than Playwright's 30s action default on this
	// local site (handoff: dashboard loads have exceeded 30s), so the click and the URL
	// wait share one 60s budget. The assertion is unchanged: wrong credentials keep the
	// page on wp-login.php and this still fails loudly.
	await Promise.all( [ page.waitForURL( ( url ) => ! String( url ).includes( 'wp-login.php' ), { waitUntil: 'commit', timeout: 60000 } ), page.locator( '#wp-submit' ).click( { timeout: 60000 } ) ] );
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
	// T04: nothing may leave the browser while the admin only opens the panel or picks a
	// prefill chip, so count every plugin REST request, not just chat.
	const pluginRequests = [];
	page.on( 'request', ( request ) => { if ( request.url().includes( '/aisaautopilot/v1/' ) ) { pluginRequests.push( request.method() + ' ' + request.url() ); } } );

	// The local site can hold a real provider key (WordPress Connectors), so a live
	// /efb/chat call could reach a billable provider. Tests never call real providers:
	// answer with the controller's own provider_not_configured shape instead
	// (EfbCopilotController::chat(), 400). /efb/log stays real so its callback is proven.
	const chatRequests = [];
	// T27 (D-08): one scripted command gets the controller's real ops response shape
	// (EfbCopilotController::respond()), so the steps and progress styles run through the
	// real browser executor without a provider. The last operation carries a value the
	// server would never send, to prove the client refuses it too.
	const STYLE_COMMAND = 'Use pills steps with a ring progress bar';
	// T04: three more scripted commands drive the how-to card, the help suggestion chips
	// and the Pro upgrade card through the controller's real payload shapes
	// (EfbCopilotController::answer_help() and its pro_omitted branch), still provider-free.
	const HELP_COMMAND = 'How do I add options to a dropdown?';
	const HELP_SUGGEST_COMMAND = 'What can you help me with?';
	const PRO_COMMAND = 'Add a file upload field';
	const HELP_TITLE = 'Add options to a dropdown';
	const HELP_PARAS = [ 'Open the field settings and use the Options list.', 'Each line you add becomes one option in the dropdown.' ];
	const HELP_SUGGESTIONS = [ { title: 'Turn on email notifications' }, { title: 'Make a form multi-step' } ];
	// EfbLicense::upgrade_url() is locale-aware, so the expected destination is too.
	const UPGRADE_URL = expectRtl ? 'https://easyformbuilder.ir/#price' : 'https://whitestudio.team/#price';
	const PRO_ERROR = 'I left out Upload your CV (File upload) — that field needs Easy Form Builder Free Plus or Pro.';
	const styleOps = [
		{ op: 'set_form_property', property: 'hide_steps_row', value: false },
		{ op: 'set_form_property', property: 'hide_progress_bar', value: false },
		{ op: 'set_form_property', property: 'steps_style', value: 'pills' },
		{ op: 'set_form_property', property: 'progress_style', value: 'ring' },
		{ op: 'set_form_property', property: 'steps_style', value: 'zigzag' },
	];
	// T74: scripted messages get the real controller's reply from efb_copilot_offline_server.php
	// (no WordPress call, no provider). The site's EFB plan is read once the editor loads.
	const t74Options = { efbPlan: 2, privacyUrl: baseUrl + '/privacy-policy/', privacyTitle: 'Privacy Policy' };
	await page.route( '**/wp-json/aisaautopilot/v1/efb/chat**', async ( route ) => {
		chatRequests.push( route.request().url() );
		if ( await fulfillScripted( route, t74Options ) ) { return undefined; }
		let body = {};
		try { body = route.request().postDataJSON() || {}; } catch ( error ) { body = {}; }
		if ( STYLE_COMMAND === body.message ) {
			return route.fulfill( { status: 200, contentType: 'application/json', body: JSON.stringify( { ok: true, code: 'ok', type: 'ops', reply: 'Applying the steps and progress styles.', operations: styleOps } ) } );
		}
		if ( HELP_COMMAND === body.message ) {
			return route.fulfill( { status: 200, contentType: 'application/json', body: JSON.stringify( { ok: true, code: 'ok', type: 'help', reply: '', title: HELP_TITLE, answer: HELP_PARAS.join( String.fromCharCode( 10, 10 ) ) } ) } );
		}
		if ( HELP_SUGGEST_COMMAND === body.message ) {
			return route.fulfill( { status: 200, contentType: 'application/json', body: JSON.stringify( { ok: true, code: 'ok', type: 'help', reply: 'Here are topics I can walk you through:', suggestions: HELP_SUGGESTIONS } ) } );
		}
		if ( PRO_COMMAND === body.message ) {
			return route.fulfill( { status: 200, contentType: 'application/json', body: JSON.stringify( { ok: true, code: 'ok', type: 'message', reply: 'I can build the rest of that form now.', errors: [ PRO_ERROR ], upgrade_required: true, upgrade_reason: 'pro_field', upgrade_url: UPGRADE_URL } ) } );
		}
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
		t74Options.efbPlan = await page.evaluate( () => ( window.AISAAutoPilotEFB && window.AISAAutoPilotEFB.license && Number( window.AISAAutoPilotEFB.license.code ) ) || 2 );
		// valj_efb is populated before EFB draws the canvas: wait for the first wrapper before counting.
		if ( liveFieldIds.length ) {
			await page.locator( '.efbField[data-id="' + liveFieldIds[ 0 ] + '-id"]' ).first().waitFor( { state: 'visible', timeout: 20000 } );
		}
		const renderedFieldCount = await page.locator( '.efbField[data-id$="-id"]' ).evaluateAll( ( elements, ids ) => elements.filter( ( element ) => ids.includes( String( element.getAttribute( 'data-id' ) ).slice( 0, -3 ) ) ).length, liveFieldIds );
		check( renderedFieldCount >= 1, 'real EFB canvas renders a selectable temporary field' );
		const editorDirection = await page.locator( 'html' ).evaluate( ( element ) => getComputedStyle( element ).direction );
		check( editorDirection === ( expectRtl ? 'rtl' : 'ltr' ), 'EFB editor uses the expected locale direction' );
		// The EFB editor page is extremely tall; review images are scoped to the surface.
		await page.locator( '#aisaautopilot-efb-launch' ).screenshot( { path: path.join( screenshotDir, 'efb-copilot-fab-desktop.png' ) } );

		const pluginRequestsBeforeOpen = pluginRequests.length;
		await page.locator( '#aisaautopilot-efb-launch' ).click();
		const chat = page.locator( '#aisaautopilot-efb-chat' );
		await chat.waitFor( { state: 'visible' } );
		await page.waitForTimeout( 220 );
		const desktopBox = await chat.boundingBox();
		check( desktopBox && Math.abs( desktopBox.width - 388 ) <= 3 && Math.abs( desktopBox.height - 576 ) <= 3, 'desktop chat matches the 388×576 mockup panel' );
		if ( desktopBox ) { console.log( 'PANEL_BOX ' + JSON.stringify( desktopBox ) ); }
		check( await page.locator( '#aisaautopilot-efb-messages .aisaautopilot-efb-msg--ai' ).count() === 1, 'opening renders one truthful welcome message' );
		await chat.screenshot( { path: path.join( screenshotDir, 'efb-copilot-chat-desktop.png' ) } );
		console.log( 'PLUGIN_REQUESTS_ON_OPEN ' + JSON.stringify( pluginRequests.slice( pluginRequestsBeforeOpen ) ) );
		check( pluginRequests.length === pluginRequestsBeforeOpen, 'opening the Copilot panel sends no request' );

		// T04: chips prefill the composer and never send. The mockup runs its chips at once
		// (EFB Builder Chat.dc.html runChip); this plugin keeps the reviewed-before-send
		// contract of ARCHITECTURE_FA.md, so the difference is deliberate.
		const composer = page.locator( '#aisaautopilot-efb-input' );
		const starterChips = page.locator( '#aisaautopilot-efb-messages [data-sp-efb-chip="starter"]' );
		const starterTexts = ( await starterChips.allInnerTexts() ).map( ( item ) => item.trim() );
		console.log( 'STARTER_CHIPS ' + JSON.stringify( starterTexts ) );
		check( 3 === starterTexts.length, 'the welcome message offers the three live-form starter chips' );
		const requestsBeforeChip = pluginRequests.length;
		await starterChips.first().click();
		check( ( await composer.inputValue() ).trim() === starterTexts[ 0 ] && starterTexts[ 0 ].length > 0, 'a starter chip only prefills the composer with its own text' );
		check( await composer.evaluate( ( element ) => document.activeElement === element ), 'a starter chip moves focus into the composer' );
		check( pluginRequests.length === requestsBeforeChip, 'a starter chip sends no request' );
		check( 0 === await page.locator( '#aisaautopilot-efb-messages .aisaautopilot-efb-msg--user' ).count(), 'a starter chip posts nothing into the transcript' );
		await composer.fill( '' );

		// T04: fa_IR must translate the surface, not only flip its direction.
		const localeCopy = await page.evaluate( () => {
			const text = ( selector ) => { const node = document.querySelector( selector ); return node ? String( node.textContent || '' ).trim() : ''; };
			const label = ( id ) => { const node = document.getElementById( id ); return node ? String( node.getAttribute( 'aria-label' ) || '' ) : ''; };
			const input = document.getElementById( 'aisaautopilot-efb-input' );
			return {
				lang: String( document.documentElement.getAttribute( 'lang' ) || '' ),
				title: text( '.aisaautopilot-efb-chat__title-copy strong' ),
				context: text( '#aisaautopilot-efb-context' ),
				fab: text( '.aisaautopilot-efb-fab__label' ),
				welcome: text( '#aisaautopilot-efb-messages [data-sp-efb-intro]' ),
				placeholder: input ? String( input.getAttribute( 'placeholder' ) || '' ) : '',
				composer: input ? String( input.getAttribute( 'aria-label' ) || '' ) : '',
				newChat: label( 'aisaautopilot-efb-reset' ),
				voice: label( 'aisaautopilot-efb-voice' ),
				send: label( 'aisaautopilot-efb-send' ),
				close: label( 'aisaautopilot-efb-min' ),
			};
		} );
		console.log( 'LOCALE_COPY ' + JSON.stringify( localeCopy ) );
		const persian = ( value ) => /[\u0600-\u06ff]/.test( value );
		const copyValues = Object.keys( localeCopy ).filter( ( key ) => 'lang' !== key ).map( ( key ) => localeCopy[ key ] );
		check( expectRtl ? localeCopy.lang.startsWith( 'fa' ) : ! localeCopy.lang.startsWith( 'fa' ), 'the admin language matches the locale under test' );
		check( copyValues.every( ( value ) => value.length > 0 ), 'panel title, context, FAB label, welcome, placeholder and action labels all render copy' );
		check( expectRtl ? copyValues.every( persian ) : copyValues.every( ( value ) => ! persian( value ) ), 'shell, welcome, placeholder and action labels are written in the admin language' );
		check( expectRtl ? starterTexts.every( persian ) : starterTexts.every( ( value ) => ! persian( value ) ), 'starter chips are written in the admin language' );

		// T04: keyboard. Focus is driven from the keyboard so :focus-visible really applies.
		const focusState = () => page.evaluate( () => {
			const node = document.activeElement;
			const style = getComputedStyle( node );
			return { id: node.id || '', outline: parseFloat( style.outlineWidth || '0' ) || 0, shadow: String( style.boxShadow || 'none' ) };
		} );
		await composer.focus();
		const composerFocus = await focusState();
		check( 'aisaautopilot-efb-input' === composerFocus.id && ( composerFocus.outline >= 1 || 'none' !== composerFocus.shadow ), 'the focused composer shows a visible focus ring' );
		await page.keyboard.press( 'Tab' );
		const nextFocus = await focusState();
		console.log( 'FOCUS_STATE ' + JSON.stringify( { composerFocus, nextFocus } ) );
		check( [ 'aisaautopilot-efb-voice', 'aisaautopilot-efb-send' ].includes( nextFocus.id ), 'Tab from the composer reaches the composer buttons' );
		check( nextFocus.outline >= 1 || 'none' !== nextFocus.shadow, 'the keyboard-focused composer button shows a visible focus ring' );
		const requestsBeforeNewline = pluginRequests.length;
		await composer.focus();
		await composer.fill( 'Line one' );
		await page.keyboard.press( 'Shift+Enter' );
		await page.keyboard.type( 'Line two' );
		const newlineValue = await composer.inputValue();
		check( newlineValue === 'Line one' + String.fromCharCode( 10 ) + 'Line two', 'Shift+Enter adds a new line in the composer' );
		check( pluginRequests.length === requestsBeforeNewline, 'Shift+Enter sends nothing' );
		await composer.fill( '' );

		const field = page.locator( '.efbField[data-id="' + liveFieldIds[ 0 ] + '-id"]' ).first();
		const fieldDataId = await field.getAttribute( 'data-id' );
		const selectedId = String( fieldDataId ).slice( 0, -3 );
		await field.click( { position: { x: 8, y: 8 } } );
		check( await page.locator( '#aisaautopilot-efb-selection' ).isVisible(), 'clicking a real EFB field opens the selected-field banner' );
		check( await field.evaluate( ( element ) => element.classList.contains( 'aisaautopilot-efb-field-selected' ) ), 'selected real field receives the visual selection state' );
		const bannerLabel = ( await page.locator( '#aisaautopilot-efb-selection-label' ).innerText() ).trim();
		check( bannerLabel.length > 0, 'selection banner uses the real field label' );
		console.log( 'SELECTION_BANNER ' + JSON.stringify( bannerLabel ) );
		check( expectRtl ? persian( bannerLabel ) : bannerLabel.startsWith( 'Working on' ), 'the selection banner is written in the admin language' );
		await page.locator( '#aisaautopilot-efb-selection' ).screenshot( { path: path.join( screenshotDir, 'efb-copilot-selection-banner.png' ) } );

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
		// T04: the transcript must not repeat an identical selection line when the admin
		// clears and picks the same field again; the banner is the standing indicator.
		const fieldIntros = page.locator( '#aisaautopilot-efb-messages [data-sp-efb-intro="field"]' );
		check( 1 === await fieldIntros.count(), 'clearing and reselecting the same field does not repeat the selection message' );
		if ( liveFieldIds.length > 1 ) {
			const otherId = liveFieldIds[ 1 ];
			const otherLabel = await page.evaluate( ( id ) => { const item = valj_efb.find( ( entry ) => String( entry.id_ ) === id ); return item ? String( item.name || item.value || item.id_ ) : ''; }, otherId ); // eslint-disable-line no-undef
			await page.locator( '.efbField[data-id="' + otherId + '-id"]' ).first().click( { position: { x: 8, y: 8 } } );
			const introTexts = await fieldIntros.allInnerTexts();
			console.log( 'FIELD_INTROS ' + JSON.stringify( introTexts.map( ( item ) => item.trim().slice( 0, 60 ) ) ) );
			check( 2 === introTexts.length && introTexts[ 1 ].includes( otherLabel ), 'selecting a different field announces the new context by name' );
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
		const providerBubble = ( await page.locator( '#aisaautopilot-efb-messages .aisaautopilot-efb-msg--ai' ).last().innerText() ).trim();
		console.log( 'PROVIDER_STATE ' + JSON.stringify( providerBubble.slice( 0, 160 ) ) );
		// The mocked body is this file's own English payload, so asserting it is locale-safe.
		check( providerBubble.includes( 'Add your AI provider API key' ), 'the provider-not-configured reply is shown verbatim, with no invented answer' );
		check( ! providerBubble.includes( String.fromCharCode( 10003 ) ) && 0 === await page.locator( '#aisaautopilot-efb-messages [data-sp-efb-note="applied"]' ).count(), 'a failed request claims no applied change' );
		check( ! await page.locator( '#aisaautopilot-efb-send' ).isDisabled(), 'the composer is usable again after the failed request' );

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

		// T27: styles are applied to the live canvas only. Any POST outside this plugin's
		// REST routes (heartbeat aside) would mean something tried to save the form.
		const nonCopilotPosts = [];
		const trackPosts = ( item ) => {
			if ( 'POST' === item.method() && ! item.url().includes( '/aisaautopilot/v1/' ) && ! ( item.postData() || '' ).includes( 'action=heartbeat' ) ) { nonCopilotPosts.push( item.url() ); }
		};
		page.on( 'request', trackPosts );
		// Open EFB's own form settings panel first, so the check proves its hide toggles
		// and style pickers follow the chat change without a reload.
		await page.evaluate( () => { if ( ! document.getElementById( 'showSIconsEl' ) ) { show_setting_window_efb( 'formSet' ); } } ); // eslint-disable-line no-undef
		await page.locator( '#showSIconsEl' ).waitFor( { state: 'attached', timeout: 20000 } ).catch( () => {} );
		await page.locator( '#aisaautopilot-efb-input' ).fill( STYLE_COMMAND );
		// EFB's settings side panel can cover the send button; submit the composer form itself.
		await page.evaluate( () => document.getElementById( 'aisaautopilot-efb-form' ).requestSubmit() );
		let styleReplyShown = true;
		try {
			// T03: reason notes are translated, so wait on the stable reason hook, not English copy.
			await page.locator( '#aisaautopilot-efb-messages [data-sp-efb-note="failed"][data-sp-efb-reason="invalid_form_property_value"]' ).first().waitFor( { timeout: 15000 } );
		} catch ( error ) {
			styleReplyShown = false;
		}
		check( styleReplyShown, 'the mocked style command renders an operation report' );
		const styleState = await page.evaluate( () => ( { steps: valj_efb[ 0 ].steps_style, progress: valj_efb[ 0 ].progress_style, showIcon: valj_efb[ 0 ].show_icon, showProBar: valj_efb[ 0 ].show_pro_bar } ) ); // eslint-disable-line no-undef
		console.log( 'STYLE_STATE ' + JSON.stringify( styleState ) );
		check( 'pills' === styleState.steps && 'ring' === styleState.progress, 'style operations set steps_style and progress_style on the live valj_efb[0]' );
		check( false === styleState.showIcon && false === styleState.showProBar, 'style operations show the steps row and progress bar (EFB hide flags stored as false)' );
		const styleNotes = await page.locator( '#aisaautopilot-efb-messages .aisaautopilot-efb-msg--ai' ).filter( { has: page.locator( '.aisaautopilot-efb-inline-note' ) } ).last().locator( '.aisaautopilot-efb-inline-note' ).allInnerTexts();
		console.log( 'STYLE_NOTES ' + JSON.stringify( styleNotes ) );
		const appliedNotes = styleNotes.filter( ( note ) => note.startsWith( '✓ ' ) );
		check( 4 === appliedNotes.length, 'the four valid style operations report as applied' );
		// T03 changed this contract on purpose: the note carries the reason code as a hook and a
		// translated sentence, so assert the hook plus the admin language instead of English text.
		const refusedNotes = await page.locator( '#aisaautopilot-efb-messages [data-sp-efb-note="failed"][data-sp-efb-reason="invalid_form_property_value"]' ).allInnerTexts();
		console.log( 'REFUSED_NOTES ' + JSON.stringify( refusedNotes ) );
		check( refusedNotes.length > 0 && refusedNotes.every( ( text ) => ( expectRtl ? /[؀-ۿ]/.test( text ) : text.includes( 'Not applied: Easy Form Builder does not accept that value' ) ) && ! text.includes( 'invalid_form_property_value' ) && ! text.includes( 'invalid form property value' ) ), 'the out-of-enum steps style is refused by the client and reported in the admin language, without the raw reason code' );
		const saveNotes = await page.locator( '#aisaautopilot-efb-messages [data-sp-efb-note="save-ownership"]' ).allInnerTexts();
		console.log( 'SAVE_NOTES ' + JSON.stringify( saveNotes.slice( -1 ) ) );
		check( saveNotes.length > 0 && ( expectRtl ? /[؀-ۿ]/.test( saveNotes[ saveNotes.length - 1 ] ) : saveNotes[ saveNotes.length - 1 ].includes( 'green Save button' ) ), 'the note that Easy Form Builder Save keeps the change is shown in the admin language' );
		check( expectRtl ? appliedNotes.length > 0 && appliedNotes.every( ( note ) => /[\u0600-\u06ff]/.test( note ) ) : appliedNotes.some( ( note ) => note.includes( 'Steps style set to' ) ), 'style results use the localized result messages' );

		const readPanel = () => page.evaluate( () => {
			const row = ( toggleId, target ) => {
				const picker = document.getElementById( 'efb-picker-' + target );
				const active = picker ? picker.querySelector( '.efb-sp-picker__opt.active' ) : null;
				const toggle = document.getElementById( toggleId );
				return { toggle: !! toggle, hidesRow: !! toggle && toggle.classList.contains( 'active' ), pickerHidden: ! picker || picker.classList.contains( 'd-none' ), active: active ? active.getAttribute( 'data-style' ) : '', pressed: active ? active.getAttribute( 'aria-pressed' ) : '' };
			};
			return { steps: row( 'showSIconsEl', 'steps_style' ), progress: row( 'showSprosiEl', 'progress_style' ) };
		} );
		let panel = await readPanel();
		let panelSource = 'open panel synced by the Copilot';
		if ( ! panel.steps.toggle ) {
			panelSource = 'panel reopened after the canvas render closed it';
			await page.evaluate( () => show_setting_window_efb( 'formSet' ) ); // eslint-disable-line no-undef
			await page.locator( '#showSIconsEl' ).waitFor( { state: 'attached', timeout: 20000 } ).catch( () => {} );
			panel = await readPanel();
		}
		console.log( 'SETTINGS_PANEL ' + panelSource + ' ' + JSON.stringify( panel ) );
		check( panel.steps.toggle && ! panel.steps.hidesRow && ! panel.steps.pickerHidden && 'pills' === panel.steps.active && 'true' === panel.steps.pressed, 'EFB settings panel shows the steps row toggle off and Pills selected' );
		check( panel.progress.toggle && ! panel.progress.hidesRow && ! panel.progress.pickerHidden && 'ring' === panel.progress.active && 'true' === panel.progress.pressed, 'EFB settings panel shows the progress bar toggle off and Ring selected' );
		await page.evaluate( () => { if ( typeof sideMenuEfb === 'function' ) { sideMenuEfb( 0 ); } window.efbOpenSettingIdEfb = null; } ); // eslint-disable-line no-undef

		// EFB's own desktop preview reads valj_efb[0] and must draw the chosen styles.
		await page.evaluate( () => previewFormEfb( 'pc' ) ); // eslint-disable-line no-undef
		const previewShell = page.locator( '.efb-sp[data-steps-style="pills"][data-progress-style="ring"]' ).first();
		let previewShown = true;
		try {
			await previewShell.waitFor( { state: 'visible', timeout: 20000 } );
		} catch ( error ) {
			previewShown = false;
		}
		check( previewShown, 'EFB preview draws the pills steps row with the ring progress bar' );
		if ( previewShown ) {
			await previewShell.screenshot( { path: path.join( screenshotDir, 'efb-copilot-steps-style-preview.png' ) } ).catch( ( error ) => console.log( 'PREVIEW_SCREENSHOT_FAILED ' + error.message ) );
		}
		await page.evaluate( () => { if ( typeof state_modal_show_efb === 'function' ) { state_modal_show_efb( 0 ); } } ); // eslint-disable-line no-undef
		page.off( 'request', trackPosts );
		console.log( 'NON_COPILOT_POSTS ' + JSON.stringify( nonCopilotPosts ) );
		check( 0 === nonCopilotPosts.length, 'applying styles and previewing never saves the form' );

		// T04: how-to card, help suggestion chips and the Pro upgrade card, all sent with
		// Enter from the composer so the keyboard send path is proven too.
		const sendByKeyboard = async ( text ) => {
			const before = chatRequests.length;
			const aiBefore = await page.locator( '#aisaautopilot-efb-messages .aisaautopilot-efb-msg--ai' ).count();
			await composer.fill( text );
			await composer.press( 'Enter' );
			await page.waitForFunction( ( count ) => document.querySelectorAll( '#aisaautopilot-efb-messages .aisaautopilot-efb-msg--ai' ).length > count && ! document.getElementById( 'aisaautopilot-efb-typing' ), aiBefore, { timeout: 15000 } );
			return chatRequests.length - before;
		};
		check( 1 === await sendByKeyboard( HELP_COMMAND ), 'Enter in the composer sends the message once' );
		const helpCard = page.locator( '#aisaautopilot-efb-messages .aisaautopilot-efb-card' ).last();
		await helpCard.waitFor( { state: 'visible', timeout: 10000 } );
		const helpTitle = ( await helpCard.locator( 'h3' ).innerText() ).trim();
		const helpParagraphs = ( await helpCard.locator( 'p' ).allInnerTexts() ).map( ( item ) => item.trim() );
		console.log( 'HELP_CARD ' + JSON.stringify( { helpTitle, helpParagraphs } ) );
		check( HELP_TITLE === helpTitle && 2 === helpParagraphs.length && helpParagraphs[ 1 ] === HELP_PARAS[ 1 ], 'the how-to answer renders as a card with its title and both paragraphs' );
		await helpCard.screenshot( { path: path.join( screenshotDir, 'efb-copilot-help-card.png' ) } );

		check( 1 === await sendByKeyboard( HELP_SUGGEST_COMMAND ), 'the suggestion request reaches the mocked endpoint once' );
		const suggestionChips = page.locator( '#aisaautopilot-efb-messages [data-sp-efb-chip="suggestion"]' );
		await suggestionChips.first().waitFor( { state: 'visible', timeout: 10000 } );
		const suggestionTexts = ( await suggestionChips.allInnerTexts() ).map( ( item ) => item.trim() );
		console.log( 'SUGGESTION_CHIPS ' + JSON.stringify( suggestionTexts ) );
		check( suggestionTexts.length === HELP_SUGGESTIONS.length, 'the topic list renders one chip per suggestion' );
		const beforeSuggestionClick = chatRequests.length;
		await suggestionChips.first().click();
		check( ( await composer.inputValue() ).trim() === suggestionTexts[ 0 ] && chatRequests.length === beforeSuggestionClick, 'a help suggestion chip only prefills the composer' );
		await composer.fill( '' );

		check( 1 === await sendByKeyboard( PRO_COMMAND ), 'the Pro-field request reaches the mocked endpoint once' );
		const proBubble = page.locator( '#aisaautopilot-efb-messages .aisaautopilot-efb-msg--ai' ).last();
		const proButton = proBubble.locator( '.aisaautopilot-efb-btn--pro' );
		const pricingLink = proBubble.locator( 'a.aisaautopilot-efb-btn--ghost' );
		await proButton.waitFor( { state: 'visible', timeout: 10000 } );
		// T17 (open since T04): free text follows its own language, not the panel's direction.
		const textDirs = await page.evaluate( () => {
			const reply = Array.from( document.querySelectorAll( '#aisaautopilot-efb-messages .aisaautopilot-efb-msg--ai [dir="auto"]' ) ).pop();
			const user = Array.from( document.querySelectorAll( '#aisaautopilot-efb-messages .aisaautopilot-efb-msg--user .aisaautopilot-efb-bubble' ) ).pop();
			const own = ( node ) => ( /^[^A-Za-z؀-ۿ]*[؀-ۿ]/.test( node.textContent ) ? 'rtl' : 'ltr' );
			return {
				reply: reply ? { dir: reply.getAttribute( 'dir' ), computed: getComputedStyle( reply ).direction, expected: own( reply ) } : null,
				user: user ? { dir: user.getAttribute( 'dir' ), computed: getComputedStyle( user ).direction, expected: own( user ) } : null,
			};
		} );
		check( !! textDirs.reply && 'auto' === textDirs.reply.dir && textDirs.reply.computed === textDirs.reply.expected, 'the reply text takes its own direction with dir="auto" (' + JSON.stringify( textDirs.reply ) + ')' );
		check( !! textDirs.user && 'auto' === textDirs.user.dir && textDirs.user.computed === textDirs.user.expected, 'the user\'s own text takes its own direction with dir="auto" (' + JSON.stringify( textDirs.user ) + ')' );
		const proText = ( await proBubble.innerText() ).trim();
		const pricing = await pricingLink.evaluate( ( element ) => ( { href: element.getAttribute( 'href' ) || '', target: element.getAttribute( 'target' ) || '', rel: element.getAttribute( 'rel' ) || '' } ) );
		console.log( 'PRO_CARD ' + JSON.stringify( { proText: proText.slice( 0, 160 ), pricing } ) );
		check( proText.includes( 'Upload your CV' ), 'the Pro answer names the field it left out instead of shipping a lesser form silently' );
		check( UPGRADE_URL === pricing.href && '_blank' === pricing.target && pricing.rel.includes( 'noopener' ), 'the pricing link points at the official upgrade URL and opens safely' );
		await proBubble.screenshot( { path: path.join( screenshotDir, 'efb-copilot-pro-card.png' ) } );
		// The CTA must not take the admin out of the editor. External hosts are aborted so a
		// wrong implementation cannot reach the network, and popups are recorded.
		const popups = [];
		context.on( 'page', ( item ) => popups.push( item.url() ) );
		await page.route( 'https://whitestudio.team/**', ( route ) => route.abort() );
		await page.route( 'https://easyformbuilder.ir/**', ( route ) => route.abort() );
		const hasEfbDialog = await page.evaluate( () => 'function' === typeof window.pro_show_efb );
		const urlBeforeCta = page.url();
		await proButton.click();
		let efbDialogShown = false;
		if ( hasEfbDialog ) {
			efbDialogShown = await page.locator( '.efb-dlg__centered' ).first().waitFor( { state: 'visible', timeout: 10000 } ).then( () => true ).catch( () => false );
		}
		console.log( 'PRO_CTA ' + JSON.stringify( { hasEfbDialog, efbDialogShown, popups } ) );
		check( page.url() === urlBeforeCta, 'the Pro CTA never navigates the editor away' );
		check( hasEfbDialog ? ( efbDialogShown && 0 === popups.length ) : 1 === popups.length, hasEfbDialog ? 'the Pro CTA opens Easy Form Builder own upgrade dialog, with no external tab' : 'without the EFB dialog the Pro CTA falls back to the official pricing tab' );
		await page.evaluate( () => { if ( 'function' === typeof state_modal_show_efb ) { state_modal_show_efb( 0 ); } } ); // eslint-disable-line no-undef
		check( await chat.isVisible(), 'the Copilot panel is still open after the Pro dialog closes' );

		// T74 on the real EFB canvas: add, rename, logic (when this site's add-on and plan allow
		// it), explain, review with Apply, and Undo back to the fixture form.
		await runT74( page, { check, label: 'T74', privacyUrl: t74Options.privacyUrl } );
		const beforeReset = await page.locator( '#aisaautopilot-efb-messages .aisaautopilot-efb-msg' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => ( node.classList.contains( 'aisaautopilot-efb-msg--ai' ) ? 'ai:' : 'user:' ) + String( node.textContent || '' ).trim().slice( 0, 60 ) ) );
		console.log( 'MESSAGES_BEFORE_RESET ' + JSON.stringify( beforeReset ) );
		await page.locator( '#aisaautopilot-efb-reset' ).click();
		const afterReset = await page.locator( '#aisaautopilot-efb-messages .aisaautopilot-efb-msg' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => ( node.classList.contains( 'aisaautopilot-efb-msg--ai' ) ? 'ai:' : 'user:' ) + String( node.textContent || '' ).trim().slice( 0, 60 ) ) );
		console.log( 'MESSAGES_AFTER_RESET ' + JSON.stringify( afterReset ) );
		check( await page.locator( '#aisaautopilot-efb-messages .aisaautopilot-efb-msg--ai' ).count() === 1, 'New chat clears history and renders one welcome message' );
		// T04: the one remaining message must match the live context, not a generic greeting.
		const resetWelcome = await page.evaluate( () => {
			const node = document.querySelector( '#aisaautopilot-efb-messages [data-sp-efb-intro]' );
			const banner = document.getElementById( 'aisaautopilot-efb-selection' );
			return { kind: node ? String( node.getAttribute( 'data-sp-efb-intro' ) ) : '', text: node ? String( node.textContent || '' ).trim() : '', selected: !! banner && ! banner.hidden };
		} );
		const selectedLabel = await page.evaluate( ( id ) => { const item = valj_efb.find( ( entry ) => String( entry.id_ ) === id ); return item ? String( item.name || item.value || item.id_ ) : ''; }, selectedId ); // eslint-disable-line no-undef
		console.log( 'RESET_WELCOME ' + JSON.stringify( { kind: resetWelcome.kind, selected: resetWelcome.selected, selectedLabel } ) );
		check( resetWelcome.selected && 'field' === resetWelcome.kind && resetWelcome.text.includes( selectedLabel ), 'New chat keeps the live selection and greets with it instead of a generic welcome' );
		const staleVoiceStatus = ( await page.locator( '#aisaautopilot-efb-voice-status' ).innerText() ).trim();
		console.log( 'VOICE_STATUS_AFTER_RESET ' + JSON.stringify( staleVoiceStatus ) );
		check( 0 === staleVoiceStatus.length, 'New chat clears the microphone status left by the previous conversation' );
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
		await chat.screenshot( { path: path.join( screenshotDir, 'efb-copilot-chat-mobile.png' ) } );

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
