/* Browser QA for the visitor chat widget on the real front page of the active theme.

   Run through tests/front_chat_widget_browser_matrix.cjs, which prepares
   tests/local_front_chat_widget_browser_fixture.php for one locale and one scenario first.
   The widget is a visitor surface, so the check runs logged out: no QA account is needed.

   Nothing leaves the browser. /widget/ask, /widget/handoff and /public/voice are answered with
   page.route in the shape the real controllers return, and the microphone, the recorder and the
   speech engine are stubbed with addInitScript. The single real call is a POST to /public/voice
   in the quiet scenario, where voice is off and the gate refuses it with 403 before the rate
   limiter and before any provider lookup. */
'use strict';

const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { chromium } = require( 'playwright-core' );

const baseUrl = ( process.env.AISAAUTOPILOT_BASE_URL || 'http://127.0.0.1/wp' ).replace( /\/$/, '' ) + '/';
const chromePath = process.env.AISAAUTOPILOT_CHROME || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const locale = process.env.AISAAUTOPILOT_BROWSER_LOCALE || 'en_US';
const scenario = process.env.AISAAUTOPILOT_FRONT_WIDGET_SCENARIO || 'main';
const screenshotDir = process.env.AISAAUTOPILOT_SCREENSHOT_DIR || path.join( process.env.TEMP || '.', 'front-chat-widget', locale );
const suggestions = parseInt( process.env.AISAAUTOPILOT_FRONT_WIDGET_SUGGESTIONS || '3', 10 );
const persianRun = locale.startsWith( 'fa' );
const pageDirection = persianRun ? 'rtl' : 'ltr';
const views = [
	{ name: '1440', width: 1440, height: 960 },
	{ name: '390', width: 390, height: 844 },
];
const failures = [];

function check( condition, message ) {
	console.log( ( condition ? 'PASS ' : 'FAIL ' ) + locale + ' ' + scenario + ' ' + message );
	if ( ! condition ) { failures.push( message ); }
}
const persian = ( text ) => /[\u0600-\u06FF]/.test( String( text ) );
/* Visible copy is asserted through the site language: Persian script in fa_IR, the exact English string otherwise. */
const inLocale = ( text, english ) => ( persianRun ? persian( text ) : String( text ).trim() === english );
// Wait for the entry animations and transitions now running (panel 0.32s to 0.34s, nudge 0.3s) to end
// before measuring or capturing; the loops (launcher ring, recording pulse) never end and are skipped.
// A fixed 250ms was shorter than the panel's entry and measured it at 392×623 on a slow run.
// Capped at 3s so a paused animation cannot hold the run.
const settle = ( page ) => page.evaluate( () => Promise.race( [
	Promise.all( document.getAnimations()
		.filter( ( a ) => a.playState === 'running' && a.effect && a.effect.getComputedTiming().iterations !== Infinity )
		.map( ( a ) => a.finished.catch( () => null ) ) ),
	new Promise( ( resolve ) => setTimeout( resolve, 3000 ) ),
] ) );

/* ── the real controller shapes ─────────────────────────── */

const MARKDOWN_ANSWER = persianRun
	? '**ساعت کاری**\n\nما باز هستیم:\n- شنبه تا چهارشنبه، ۹ تا ۱۸\n- پنجشنبه، ۱۰ تا ۱۴\n\n[صفحهٔ تماس](https://example.com/contact) را ببینید یا `021-555` را بگیرید.'
	: '**Opening hours**\n\nWe are open:\n- Monday to Friday, 9 to 18\n- Saturday, 10 to 14\n\nSee [our contact page](https://example.com/contact) or dial `021-555`.';
const ANSWER_SPOKEN = persianRun ? 'ما هر روز کاری صبح به سراسر کشور ارسال داریم.' : 'We deliver across the country every weekday morning.';
const TRANSCRIPT = persianRun ? 'آیا شنبه‌ها به مشهد ارسال دارید؟' : 'Do you deliver to Manchester on Saturdays?';

/* ChatEngine::result() through ChatWidgetController::ask(), and its refusals. */
// A recorded conversation's id, as the server returns it (a UUID token).
const CONV_ID = '3f2b8c1e-5d4a-4b6f-9c2d-7e8f9a0b1c2d';
const ASK = {
	answer: { status: 200, body: { ok: true, answer: MARKDOWN_ANSWER, sources: [ { title: persianRun ? 'ساعت کاری' : 'Opening hours', url: 'https://example.com/hours' } ], status: 'answered', clarify_options: [], handoff_offer: false, conversation_id: CONV_ID } },
	plain: { status: 200, body: { ok: true, answer: ANSWER_SPOKEN, sources: [], status: 'answered', clarify_options: [], handoff_offer: false, conversation_id: CONV_ID } },
	clarify: { status: 200, body: { ok: true, answer: persianRun ? 'منظورتان کدام است؟' : 'Which one do you mean?', sources: [], status: 'clarify', clarify_options: persianRun ? [ 'ارسال داخل کشور', 'ارسال به خارج' ] : [ 'Shipping inside the country', 'Shipping abroad' ], handoff_offer: false, conversation_id: CONV_ID } },
	notFound: { status: 200, body: { ok: true, answer: 'I looked through the site\u2019s content but couldn\u2019t find a clear answer to that yet.\n\nWould you like me to connect you with a member of our team?', sources: [], status: 'not_found', clarify_options: [], handoff_offer: true, conversation_id: CONV_ID } },
	unavailable: { status: 503, body: { ok: false, code: 'assistant_unavailable', message: 'The assistant is temporarily unavailable. Please try again later.' } },
	rateLimited: { status: 429, body: { ok: false, code: 'rate_limited', message: 'Please wait before sending another message.' } },
};
/* ChatWidgetController::handoff(). */
const HANDOFF = {
	refused: { status: 400, body: { ok: false, code: 'invalid_email', message: 'Please provide a valid email address.', fields: [ 'email' ] } },
	sent: { status: 200, body: { ok: true, code: 'sent', message: 'Your request has been sent to the support team.', reference: 1234 } },
};

/* ── microphone, recorder and speech engine stubs ────────── */

const STUBS = () => {
	window.__qa = { micAsked: 0, started: 0, stopped: 0, tracksStopped: 0, spoken: [] };
	function FakeRecorder( stream, opts ) {
		const self = this;
		this.state = 'inactive';
		this.mimeType = ( opts && opts.mimeType ) || 'audio/webm';
		this.ondataavailable = null;
		this.onstop = null;
		this.start = function () { self.state = 'recording'; window.__qa.started += 1; };
		this.stop = function () {
			self.state = 'inactive';
			window.__qa.stopped += 1;
			if ( self.ondataavailable ) { self.ondataavailable( { data: new Blob( [ new Uint8Array( [ 1, 2, 3, 4, 5, 6, 7, 8 ] ) ], { type: 'audio/webm' } ) } ); }
			if ( self.onstop ) { self.onstop(); }
		};
	}
	FakeRecorder.isTypeSupported = function () { return true; };
	Object.defineProperty( window, 'MediaRecorder', { configurable: true, writable: true, value: FakeRecorder } );
	Object.defineProperty( navigator, 'mediaDevices', {
		configurable: true,
		value: {
			getUserMedia: function () {
				window.__qa.micAsked += 1;
				return Promise.resolve( { getTracks: function () { return [ { stop: function () { window.__qa.tracksStopped += 1; } } ]; } } );
			},
		},
	} );
	function FakeUtterance( text ) { this.text = text; this.onboundary = null; this.onend = null; }
	Object.defineProperty( window, 'SpeechSynthesisUtterance', { configurable: true, writable: true, value: FakeUtterance } );
	Object.defineProperty( window, 'speechSynthesis', {
		configurable: true,
		value: {
			speak: function ( u ) { window.__qa.spoken.push( u.text ); window.__qaUtterance = u; },
			cancel: function () {},
			getVoices: function () { return []; },
		},
	} );
};

/* ── measuring helpers ───────────────────────────────────── */

/**
 * WCAG 2.x contrast of each element's text against the first opaque background behind it.
 * Semi-transparent layers are composited over what lies beneath, so the header buttons'
 * white-on-navy tint is measured as drawn.
 */
async function contrast( page, items ) {
	return page.evaluate( ( list ) => {
		const parse = ( value ) => {
			const m = String( value ).match( /rgba?\(([^)]+)\)/ );
			if ( ! m ) { return null; }
			const p = m[ 1 ].split( /[\s,/]+/ ).filter( Boolean ).map( Number );
			return { r: p[ 0 ], g: p[ 1 ], b: p[ 2 ], a: p.length > 3 ? p[ 3 ] : 1 };
		};
		const over = ( top, bottom ) => ( {
			r: top.r * top.a + bottom.r * ( 1 - top.a ),
			g: top.g * top.a + bottom.g * ( 1 - top.a ),
			b: top.b * top.a + bottom.b * ( 1 - top.a ),
			a: 1,
		} );
		const background = ( node ) => {
			const layers = [];
			for ( let at = node; at && at.nodeType === 1; at = at.parentElement ) {
				const c = parse( getComputedStyle( at ).backgroundColor );
				if ( c && c.a > 0 ) { layers.push( c ); if ( c.a >= 1 ) { break; } }
			}
			let result = { r: 255, g: 255, b: 255, a: 1 };
			for ( let i = layers.length - 1; i >= 0; i-- ) { result = over( layers[ i ], result ); }
			return result;
		};
		const lum = ( c ) => {
			const f = ( v ) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 ); };
			return 0.2126 * f( c.r ) + 0.7152 * f( c.g ) + 0.0722 * f( c.b );
		};
		return list.map( ( item ) => {
			const node = document.querySelector( item.selector );
			if ( ! node || ! node.getClientRects().length ) { return { ...item, missing: true }; }
			const style = getComputedStyle( node, item.pseudo || null );
			const bg = background( node );
			const fg = over( parse( style.color ) || { r: 0, g: 0, b: 0, a: 1 }, bg );
			const a = lum( fg );
			const b = lum( bg );
			const ratio = ( Math.max( a, b ) + 0.05 ) / ( Math.min( a, b ) + 0.05 );
			return { ...item, ratio: Math.round( ratio * 100 ) / 100 };
		} );
	}, items );
}

/**
 * The clickable width and height of each control, probed with elementFromPoint along the lines
 * through its centre, so a pseudo-element that enlarges the target counts and a clipped one does not.
 */
async function hitTargets( page, selectors ) {
	return page.evaluate( ( list ) => list.map( ( selector ) => {
		const node = document.querySelector( selector );
		if ( ! node || getComputedStyle( node ).display === 'none' ) { return { selector, missing: true }; }
		const box = node.getBoundingClientRect();
		const cx = Math.round( box.left + box.width / 2 );
		const cy = Math.round( box.top + box.height / 2 );
		const owns = ( x, y ) => { const at = document.elementFromPoint( x, y ); return !! at && ( at === node || node.contains( at ) ); };
		let w = 0;
		let h = 0;
		for ( let d = -40; d <= 40; d++ ) {
			if ( owns( cx + d, cy ) ) { w++; }
			if ( owns( cx, cy + d ) ) { h++; }
		}
		return { selector, hit: w + 'x' + h, ok: w >= 44 && h >= 44 };
	} ), selectors );
}

/** Whether the focused control shows a ring: its own outline or box-shadow, or its field box's focus-within ring. */
async function focusState( page ) {
	return page.evaluate( () => {
		const node = document.activeElement;
		if ( ! node || node === document.body ) { return { id: 'body', inPanel: false, ring: false }; }
		const ringOf = ( el ) => {
			const s = getComputedStyle( el );
			return ( s.outlineStyle !== 'none' && parseFloat( s.outlineWidth ) >= 1 ) || ( s.boxShadow && s.boxShadow !== 'none' );
		};
		const box = node.closest( '.spw-composer__box' );
		return {
			id: node.id || node.getAttribute( 'data-spw-ask' ) || node.className || node.tagName,
			inPanel: !! node.closest( '#spw-panel' ),
			inWidget: !! node.closest( '.spw-root' ),
			ring: ringOf( node ) || ( !! box && ringOf( box ) ),
		};
	} );
}

async function docOverflow( page ) {
	return page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
}

/** Wire listeners and mocks shared by every context. */
async function instrument( page, ctx ) {
	await page.addInitScript( STUBS );
	page.on( 'console', ( m ) => {
		if ( m.type() !== 'error' ) { return; }
		// Chrome prints "Failed to load resource" for every 4xx/5xx, including the refusals this
		// check fulfils on purpose; only lines whose source is one of those mocked routes are skipped.
		const url = ( m.location() && m.location().url ) || '';
		if ( ctx.mockedRefusal( url ) ) { return; }
		ctx.consoleErrors.push( m.text().slice( 0, 200 ) + ' @ ' + url.slice( 0, 120 ) );
	} );
	page.on( 'pageerror', ( e ) => ctx.consoleErrors.push( 'pageerror: ' + String( e.message ).slice( 0, 200 ) ) );
	page.on( 'request', ( r ) => { if ( r.url().includes( '/aisaautopilot/v1/' ) ) { ctx.requests.push( r.method() + ' ' + r.url().replace( /^.*aisaautopilot\/v1\//, '' ) ); } } );
	page.on( 'response', ( r ) => {
		if ( r.status() < 400 || ctx.mockedRefusal( r.url() ) ) { return; }
		// Another plugin's Jetpack probe may 4xx on a local site; never a failure of this plugin.
		if ( r.url().includes( '/wp-json/jetpack/' ) ) { return; }
		ctx.httpFailures.push( r.status() + ' ' + r.url() );
	} );
}

function newCtx() {
	const ctx = { requests: [], consoleErrors: [], httpFailures: [], ask: 'answer', askDelay: 0, askBodies: [], handoff: 'sent', handoffBodies: [], uploads: 0, refusalsFromMocks: false };
	ctx.mockedRefusal = ( url ) => ctx.refusalsFromMocks && ( url.includes( '/aisaautopilot/v1/widget/ask' ) || url.includes( '/aisaautopilot/v1/widget/handoff' ) );
	return ctx;
}

async function mockRoutes( page, ctx ) {
	await page.route( '**/aisaautopilot/v1/widget/ask**', async ( route ) => {
		const reply = ASK[ ctx.ask ];
		try { ctx.askBodies.push( JSON.parse( route.request().postData() || '{}' ) ); } catch ( e ) { ctx.askBodies.push( { parseError: true } ); }
		if ( ctx.askDelay ) { await new Promise( ( resolve ) => setTimeout( resolve, ctx.askDelay ) ); }
		try {
			await route.fulfill( { status: reply.status, contentType: 'application/json', body: JSON.stringify( reply.body ) } );
		} catch ( e ) { /* the visitor stopped waiting and the request is gone */ }
	} );
	await page.route( '**/aisaautopilot/v1/widget/handoff**', async ( route ) => {
		try { ctx.handoffBodies.push( JSON.parse( route.request().postData() || '{}' ) ); } catch ( e ) { ctx.handoffBodies.push( { parseError: true } ); }
		const reply = HANDOFF[ ctx.handoff ];
		await route.fulfill( { status: reply.status, contentType: 'application/json', body: JSON.stringify( reply.body ) } );
	} );
	await page.route( '**/aisaautopilot/v1/public/voice**', async ( route ) => {
		ctx.uploads += 1;
		// Held open so the "transcribing" phase can be observed while the upload is in flight.
		await new Promise( ( resolve ) => setTimeout( resolve, 1200 ) );
		await route.fulfill( { status: 200, contentType: 'application/json', body: JSON.stringify( { ok: true, transcript: TRANSCRIPT, language: persianRun ? 'fa' : 'en' } ) } );
	} );
	// Any other public route would reach a provider; none is expected.
	await page.route( '**/aisaautopilot/v1/public/chat**', ( route ) => route.abort() );
}

async function lastAi( page ) {
	return page.evaluate( () => {
		const rows = Array.from( document.querySelectorAll( '#spw-msgs .spw-row--ai' ) );
		const row = rows[ rows.length - 1 ] || null;
		const bubble = row ? row.querySelector( '.spw-msg' ) : null;
		return {
			rows: rows.length,
			text: bubble ? bubble.textContent : '',
			error: !! ( bubble && bubble.classList.contains( 'spw-msg--error' ) ),
			strong: row ? row.querySelectorAll( '.spw-msg strong' ).length : 0,
			items: row ? row.querySelectorAll( '.spw-msg .spw-md-list li' ).length : 0,
			code: row ? row.querySelectorAll( '.spw-msg .spw-code' ).length : 0,
			links: row ? Array.from( row.querySelectorAll( '.spw-msg a' ) ).map( ( a ) => a.href + '|' + a.rel ) : [],
			sources: row ? Array.from( row.querySelectorAll( '.spw-source a' ) ).map( ( a ) => a.href ) : [],
			retry: row ? row.querySelectorAll( '.spw-retry' ).length : 0,
			typing: !! document.querySelector( '#spw-typing' ),
		};
	} );
}

async function ask( page, text ) {
	await page.locator( '#spw-input' ).fill( text );
	await page.locator( '#spw-input' ).press( 'Enter' );
}

function shot( page, name ) {
	return page.screenshot( { path: path.join( screenshotDir, name ) } );
}

/* ══ main: launcher, panel, keyboard, contrast, conversation, handoff, voice, full screen ══ */

async function runMain( browser, view, reducedMotionDone ) {
	const tag = view.name;
	const context = await browser.newContext( { viewport: { width: view.width, height: view.height } } );
	const page = await context.newPage();
	const ctx = newCtx();
	await instrument( page, ctx );
	await mockRoutes( page, ctx );

	await page.goto( baseUrl, { waitUntil: 'domcontentloaded', timeout: 90000 } );
	await page.locator( '#spw-toggle' ).waitFor( { state: 'visible', timeout: 30000 } );

	// ── launcher: the nudge arrives by itself, the panel does not, and nothing is fetched ──
	const nudge = await page.locator( '#spw-nudge:not([hidden])' ).waitFor( { state: 'visible', timeout: 15000 } ).then( () => true, () => false );
	check( nudge, `${ tag } the "Need a hand?" nudge appears on its own` );
	const root = await page.evaluate( () => ( {
		lang: document.documentElement.lang,
		autoOpen: document.querySelector( '.spw-root' ).getAttribute( 'data-spw-auto-open' ),
		direction: getComputedStyle( document.querySelector( '#spw-panel' ) ).direction,
		open: document.querySelector( '#spw-panel' ).classList.contains( 'spw-panel--open' ),
		nudgeTitle: ( document.querySelector( '.spw-nudge__title' ) || {} ).textContent || '',
		dot: document.querySelectorAll( '.spw-avatar__dot' ).length,
	} ) );
	check( root.lang.toLowerCase().startsWith( persianRun ? 'fa' : 'en' ), `${ tag } the page is served in the site language (html lang ${ root.lang })` );
	check( root.direction === pageDirection, `${ tag } the widget reads ${ pageDirection } (computed ${ root.direction })` );
	check( root.autoOpen === '0' && ! root.open, `${ tag } the panel stays closed: data-spw-auto-open ${ root.autoOpen }` );
	check( inLocale( root.nudgeTitle, 'Need a hand?' ), `${ tag } the nudge speaks the site language ("${ root.nudgeTitle.trim() }")` );
	check( root.dot === 0, `${ tag } no "online" dot claims presence nobody measures` );
	check( ctx.requests.length === 0, `${ tag } a visitor who has not opened the chat costs the site no request: ${ JSON.stringify( ctx.requests ) }` );
	if ( nudge ) {
		// Capture the nudge once its 0.3s rise has finished, not half faded in.
		await page.locator( '#spw-nudge' ).evaluate( ( node ) => Promise.all( node.getAnimations().map( ( a ) => a.finished ) ) );
	}
	await shot( page, `widget-launcher-${ tag }.png` );

	// ── keyboard reaches the launcher, shows a ring and opens it with Enter ──
	const pageLock = await page.evaluate( () => [ document.documentElement.style.overflow, document.body.style.overflow ] );
	let reached = false;
	for ( let i = 0; i < 60 && ! reached; i++ ) {
		await page.keyboard.press( 'Shift+Tab' );
		reached = await page.evaluate( () => document.activeElement && document.activeElement.id === 'spw-toggle' );
	}
	check( reached, `${ tag } Shift+Tab from the page reaches the launcher` );
	const launcherFocus = await focusState( page );
	check( launcherFocus.ring, `${ tag } the focused launcher shows a visible ring` );
	await page.keyboard.press( 'Enter' );
	await page.locator( '#spw-panel.spw-panel--open' ).waitFor();
	await settle( page );
	const opened = await page.evaluate( () => ( {
		active: document.activeElement ? document.activeElement.id : '',
		expanded: document.getElementById( 'spw-toggle' ).getAttribute( 'aria-expanded' ),
		box: ( ( b ) => [ Math.round( b.width ), Math.round( b.height ) ] )( document.getElementById( 'spw-panel' ).getBoundingClientRect() ),
		radius: getComputedStyle( document.getElementById( 'spw-panel' ) ).borderTopLeftRadius,
		sub: document.querySelector( '.spw-header__sub' ).textContent.trim(),
		greeting: document.querySelector( '.spw-msg--greeting' ).textContent.trim(),
		placeholder: document.getElementById( 'spw-input' ).getAttribute( 'placeholder' ),
		chips: document.querySelectorAll( '#spw-chips [data-spw-ask]' ).length,
		inputDir: getComputedStyle( document.getElementById( 'spw-input' ) ).direction,
		inputDirAttr: document.getElementById( 'spw-input' ).getAttribute( 'dir' ),
		overscroll: getComputedStyle( document.getElementById( 'spw-msgs' ) ).overscrollBehaviorY,
		modal: document.getElementById( 'spw-panel' ).getAttribute( 'aria-modal' ),
		lock: [ document.documentElement.style.overflow, document.body.style.overflow ],
	} ) );
	check( opened.active === 'spw-input' && opened.expanded === 'true', `${ tag } Enter opens the chat and puts the caret in the message box (${ opened.active }, aria-expanded ${ opened.expanded })` );
	if ( view.name === '1440' ) {
		check( opened.box[ 0 ] === 392 && opened.box[ 1 ] === 624 && opened.radius === '20px', `${ tag } the docked panel is 392×624 with 20px corners (${ opened.box.join( '×' ) }, ${ opened.radius })` );
		check( opened.modal === 'false' && opened.lock[ 0 ] === pageLock[ 0 ] && opened.lock[ 1 ] === pageLock[ 1 ], `${ tag } the docked panel floats beside the page: not modal and the page still scrolls (aria-modal ${ opened.modal }, overflow ${ JSON.stringify( opened.lock ) })` );
	} else {
		check( opened.box[ 0 ] === 390 && opened.box[ 1 ] === 844, `${ tag } at 390px the panel is a full-screen sheet (${ opened.box.join( '×' ) })` );
		// F-17, reversed by the owner after T13: the sheet covers the whole page, so it is modal
		// and the page behind it is locked, as in full screen. The thread still contains its scroll.
		check( opened.modal === 'true' && opened.lock[ 0 ] === 'hidden' && opened.lock[ 1 ] === 'hidden', `${ tag } the sheet covers the page, so it is modal and locks the page behind it (aria-modal ${ opened.modal }, overflow ${ JSON.stringify( opened.lock ) })` );
		check( opened.overscroll === 'contain', `${ tag } scrolling the thread never scrolls the page behind the sheet (overscroll-behavior ${ opened.overscroll })` );
	}
	check(
		inLocale( opened.sub, 'AI Assistant' ) && inLocale( opened.greeting, 'Hi! How can I help you?' ) && inLocale( opened.placeholder, 'Write a message…' ),
		`${ tag } untouched default copy is shown in the site language (${ JSON.stringify( [ opened.sub, opened.greeting, opened.placeholder ] ) })`
	);
	check( opened.chips === suggestions, `${ tag } the ${ suggestions } saved questions are offered as chips (${ opened.chips })` );
	// The default title is Latin even on a Persian site; with dir="auto" it reads ltr, but it must
	// still line up with the subtitle on the page's side, next to the avatar.
	const header = await page.evaluate( ( rtl ) => {
		const edge = ( node ) => {
			const range = document.createRange();
			range.selectNodeContents( node );
			const b = range.getBoundingClientRect();
			return Math.round( rtl ? b.right : b.left );
		};
		return { title: edge( document.getElementById( 'spw-title' ) ), sub: edge( document.querySelector( '.spw-header__sub' ) ) };
	}, persianRun );
	check( Math.abs( header.title - header.sub ) <= 2, `${ tag } the header title and subtitle start on the same ${ persianRun ? 'right' : 'left' } edge (${ header.title } / ${ header.sub })` );

	// ── the composer: empty keeps the page direction, text reads in its own ──
	check( opened.inputDir === pageDirection && opened.inputDirAttr === null, `${ tag } the empty message box follows the page (${ opened.inputDir }), so the placeholder sits on the reading side` );
	await page.keyboard.type( 'Hello there' );
	const latinDir = await page.evaluate( () => getComputedStyle( document.getElementById( 'spw-input' ) ).direction );
	check( latinDir === 'ltr', `${ tag } Latin text in the message box reads left-to-right (${ latinDir })` );
	await page.keyboard.press( 'Shift+Enter' );
	await page.keyboard.type( 'سلام' );
	const multi = await page.evaluate( () => document.getElementById( 'spw-input' ).value );
	check( multi === 'Hello there\nسلام' && ctx.requests.length === 0, `${ tag } Shift+Enter adds a new line and sends nothing (${ ctx.requests.length } requests)` );
	await page.locator( '#spw-input' ).fill( 'سلام، وقت بخیر' );
	const persianDir = await page.evaluate( () => getComputedStyle( document.getElementById( 'spw-input' ) ).direction );
	check( persianDir === 'rtl', `${ tag } Persian text in the message box reads right-to-left (${ persianDir })` );
	await page.locator( '#spw-input' ).fill( '' );
	const clearedDir = await page.evaluate( () => [ getComputedStyle( document.getElementById( 'spw-input' ) ).direction, document.getElementById( 'spw-input' ).getAttribute( 'dir' ) ] );
	check( clearedDir[ 0 ] === pageDirection && clearedDir[ 1 ] === null, `${ tag } emptied again, the box returns to the page direction (${ clearedDir[ 0 ] })` );

	// ── Tab walks the panel controls and every one shows a ring ──
	const walked = [];
	let leftPanel = '';
	for ( let i = 0; i < 14; i++ ) {
		await page.keyboard.press( 'Tab' );
		const f = await focusState( page );
		if ( ! f.inPanel ) { leftPanel = f.id; break; }
		walked.push( f );
	}
	if ( view.name === '390' ) {
		check( ! leftPanel, `${ tag } 14 presses of Tab stay inside the modal sheet${ leftPanel ? ' (escaped to ' + leftPanel + ')' : '' }` );
	}
	// And backwards from the message box through the chips and the header buttons.
	await page.locator( '#spw-input' ).focus();
	for ( let i = 0; i < 14; i++ ) {
		await page.keyboard.press( 'Shift+Tab' );
		const f = await focusState( page );
		if ( ! f.inPanel || f.id === 'spw-panel' ) { break; }
		walked.push( f );
	}
	check( walked.length >= 6, `${ tag } Tab moves through the panel controls (${ walked.map( ( f ) => f.id ).join( ' → ' ) })` );
	check( walked.every( ( f ) => f.ring ), `${ tag } every focused control in the panel shows a visible ring: ${ JSON.stringify( walked.filter( ( f ) => ! f.ring ).map( ( f ) => f.id ) ) }` );

	// ── hit targets and contrast of the docked panel ──
	const targets = await hitTargets( page, view.name === '1440' ? [ '#spw-voice', '#spw-expand', '#spw-close', '#spw-mic', '#spw-send' ] : [ '#spw-voice', '#spw-close', '#spw-mic', '#spw-send' ] );
	check( targets.every( ( t ) => ! t.missing && t.ok ), `${ tag } icon buttons are at least 44×44 to a finger: ${ targets.map( ( t ) => t.selector + ' ' + t.hit ).join( ', ' ) }` );
	if ( view.name === '390' ) {
		// T17: on the touch sheet the text controls are finger targets too: 44px tall (their width is their label).
		const textTargets = await hitTargets( page, [ '#spw-chips .spw-chip', '.spw-footnote [data-spw-handoff]' ] );
		const present = textTargets.filter( ( t ) => ! t.missing );
		check( present.length > 0 && present.every( ( t ) => parseInt( t.hit.split( 'x' )[ 1 ], 10 ) >= 44 ), `${ tag } a suggested question and the footnote's handoff link are 44px tall to a finger: ${ textTargets.map( ( t ) => t.selector + ' ' + ( t.missing ? 'missing' : t.hit ) ).join( ', ' ) }` );
	}
	const ratios = await contrast( page, [
		{ name: 'header title', selector: '#spw-title', min: 4.5 },
		{ name: 'header subtitle', selector: '.spw-header__sub', min: 4.5 },
		{ name: 'greeting', selector: '.spw-msg--greeting', min: 4.5 },
		{ name: 'chip', selector: '#spw-chips .spw-chip', min: 4.5 },
		{ name: 'day label', selector: '.spw-day', min: 4.5 },
		{ name: 'placeholder', selector: '#spw-input', pseudo: '::placeholder', min: 4.5 },
		{ name: 'footnote', selector: '.spw-footnote span', min: 4.5 },
		{ name: 'footnote handoff link', selector: '.spw-footnote .spw-link', min: 4.5 },
		{ name: 'send icon', selector: '#spw-send', min: 3 },
		{ name: 'mic icon', selector: '#spw-mic', min: 3 },
		{ name: 'header button icon', selector: '#spw-close', min: 3 },
	] );
	for ( const r of ratios ) {
		check( ! r.missing && r.ratio >= r.min, `${ tag } contrast of the ${ r.name } is ${ r.ratio }:1 (at least ${ r.min }:1)` );
	}
	check( await docOverflow( page ) <= 1, `${ tag } no horizontal document overflow with the panel open` );
	await shot( page, `widget-panel-${ tag }.png` );

	// ── a chip sends the question the owner wrote (D-24) and the answer renders safely ──
	ctx.ask = 'answer';
	await page.locator( '#spw-chips .spw-chip' ).first().click();
	await page.locator( '#spw-msgs .spw-row--ai .spw-md-list' ).first().waitFor( { timeout: 15000 } );
	let turn = await lastAi( page );
	check( ctx.requests.filter( ( r ) => r === 'POST widget/ask' ).length === 1, `${ tag } one press of a saved question sends exactly one request` );
	check( turn.strong === 1 && turn.items === 2 && turn.code === 1 && ! turn.text.includes( '**' ) && ! turn.text.includes( '](' ), `${ tag } the Markdown answer becomes real elements with no raw markers` );
	check( turn.links.length === 1 && turn.links[ 0 ].startsWith( 'https://example.com/contact|' ) && turn.links[ 0 ].includes( 'noopener' ), `${ tag } an inline link is a safe anchor (${ turn.links.join( ', ' ) })` );
	check( turn.sources.length === 1 && turn.sources[ 0 ] === 'https://example.com/hours', `${ tag } the source line comes from the citation the site returned` );
	check( ! turn.typing, `${ tag } the typing dots leave when the reply arrives` );
	const answerRatios = await contrast( page, [
		{ name: 'visitor bubble', selector: '#spw-msgs .spw-row--user .spw-msg', min: 4.5 },
		{ name: 'answer text', selector: '#spw-msgs .spw-row--ai .spw-md-p', min: 4.5 },
		{ name: 'source line', selector: '#spw-msgs .spw-source a', min: 4.5 },
	] );
	for ( const r of answerRatios ) {
		check( ! r.missing && r.ratio >= r.min, `${ tag } contrast of the ${ r.name } is ${ r.ratio }:1 (at least ${ r.min }:1)` );
	}
	await settle( page );
	await shot( page, `widget-answer-${ tag }.png` );

	// ── no citation, no source line ──
	ctx.ask = 'plain';
	await ask( page, persianRun ? 'ارسال چقدر طول می‌کشد؟' : 'How fast do you ship?' );
	await page.waitForFunction( ( n ) => document.querySelectorAll( '#spw-msgs .spw-row--ai' ).length > n && ! document.querySelector( '#spw-typing' ), turn.rows, { timeout: 15000 } );
	turn = await lastAi( page );
	check( turn.sources.length === 0 && turn.text.includes( ANSWER_SPOKEN.slice( 0, 12 ) ), `${ tag } an answer without a citation shows no source line` );

	// ── a clarify option only fills the composer ──
	ctx.ask = 'clarify';
	await ask( page, persianRun ? 'ارسال' : 'shipping' );
	await page.locator( '[data-spw-clarify]' ).last().waitFor( { timeout: 15000 } );
	const beforeClarify = ctx.requests.length;
	const option = page.locator( '[data-spw-clarify-option]' ).last();
	const optionText = await option.textContent();
	await option.click();
	const clarified = await page.evaluate( () => ( { value: document.getElementById( 'spw-input' ).value, focused: document.activeElement === document.getElementById( 'spw-input' ) } ) );
	check( ctx.requests.length === beforeClarify && clarified.value === optionText && clarified.focused, `${ tag } an option the assistant wrote only fills the composer ("${ clarified.value }")` );
	await page.locator( '#spw-input' ).fill( '' );

	// ── refusals: 503 may be retried, 429 not ──
	ctx.refusalsFromMocks = true;
	ctx.ask = 'unavailable';
	await ask( page, persianRun ? 'کسی هست؟' : 'Is anyone there?' );
	await page.locator( '#spw-msgs .spw-msg--error' ).last().waitFor( { timeout: 15000 } );
	turn = await lastAi( page );
	check( turn.error && turn.retry === 1, `${ tag } a 503 is shown as an error with "try again" (retry ${ turn.retry })` );
	const errorRatio = await contrast( page, [ { name: 'error bubble', selector: '#spw-msgs .spw-msg--error', min: 4.5 } ] );
	check( errorRatio[ 0 ].ratio >= 4.5, `${ tag } contrast of the error bubble is ${ errorRatio[ 0 ].ratio }:1 (at least 4.5:1)` );
	ctx.ask = 'rateLimited';
	const errorsBefore = await page.locator( '#spw-msgs .spw-msg--error' ).count();
	await ask( page, persianRun ? 'یک پرسش دیگر' : 'Another question' );
	await page.waitForFunction( ( n ) => document.querySelectorAll( '#spw-msgs .spw-msg--error' ).length > n, errorsBefore, { timeout: 15000 } );
	turn = await lastAi( page );
	check( turn.error && turn.retry === 0, `${ tag } 429 rate_limited offers no immediate retry (retry ${ turn.retry })` );

	// ── stopping is honest about what it stops ──
	ctx.ask = 'answer';
	ctx.askDelay = 3000;
	await ask( page, persianRun ? 'یک پرسش کند' : 'A slow question' );
	await page.locator( '#spw-typing' ).waitFor( { timeout: 10000 } );
	const waitingLabel = await page.locator( '#spw-send' ).getAttribute( 'aria-label' );
	check( inLocale( waitingLabel, 'Stop waiting for the reply' ), `${ tag } while waiting the send button says "${ waitingLabel }"` );
	const rowsBefore = ( await lastAi( page ) ).rows;
	await page.locator( '#spw-send' ).click();
	await page.locator( '#spw-msgs [data-spw-note="stopped"]' ).waitFor( { timeout: 10000 } );
	ctx.askDelay = 0;
	// The mocked reply lands 3s after the request; wait past it to prove it never shows.
	await page.waitForTimeout( 3200 );
	const afterStop = await lastAi( page );
	check( afterStop.rows === rowsBefore && ! afterStop.typing, `${ tag } stopping adds a note and no answer, and the abandoned reply never lands later (rows ${ rowsBefore } → ${ afterStop.rows })` );
	ctx.refusalsFromMocks = false;

	// ── the handoff card: offered in the thread, declined, requested, validated, sent (mocked) ──
	ctx.ask = 'notFound';
	await ask( page, persianRun ? 'سفارشم کجاست؟' : 'Where is my order?' );
	await page.locator( '#spw-msgs .spw-handoff' ).waitFor( { timeout: 15000 } );
	let card = await page.evaluate( () => ( {
		cards: document.querySelectorAll( '#spw-msgs .spw-handoff' ).length,
		chipsHidden: ! document.getElementById( 'spw-chips' ).offsetParent,
		fields: Array.from( document.querySelectorAll( '#spw-msgs .spw-handoff [data-spw-handoff-field]' ) ).map( ( i ) => i.getAttribute( 'data-spw-handoff-field' ) + ( i.required ? '*' : '' ) ),
		title: ( document.querySelector( '#spw-msgs .spw-handoff__title' ) || {} ).textContent || '',
		direction: getComputedStyle( document.querySelector( '#spw-msgs .spw-handoff' ) ).direction,
		emailDir: getComputedStyle( document.querySelector( '#spw-msgs .spw-handoff [data-spw-handoff-field="email"]' ) ).direction,
		right: Math.round( document.querySelector( '#spw-msgs .spw-handoff' ).getBoundingClientRect().right ),
	} ) );
	check( card.cards === 1 && ctx.handoffBodies.length === 0, `${ tag } the card is offered in the thread and nothing is sent yet` );
	check( card.chipsHidden, `${ tag } the chips step aside while the card waits (F-10)` );
	check( card.fields.join( ',' ) === 'name,email*', `${ tag } the card asks only for what the owner switched on (${ card.fields.join( ',' ) })` );
	check( persianRun ? persian( card.title ) : card.title.includes( 'bring in a person' ), `${ tag } the card title is in the site language ("${ card.title.trim().slice( 0, 40 ) }")` );
	check( card.direction === pageDirection && card.emailDir === 'ltr', `${ tag } the card follows the page (${ card.direction }) and the e-mail field stays ltr (${ card.emailDir })` );
	check( card.right <= view.width && await docOverflow( page ) <= 1, `${ tag } the card fits the viewport (right edge ${ card.right })` );
	if ( view.name === '390' ) {
		// On the phone sheet the card can sit below the visible thread; bring its buttons into view first.
		await page.evaluate( () => {
			const msgs = document.getElementById( 'spw-msgs' );
			const send = document.querySelector( '#spw-msgs [data-spw-handoff-send]' );
			msgs.style.scrollBehavior = 'auto';
			send.scrollIntoView( { block: 'center' } );
			msgs.style.scrollBehavior = '';
		} );
		const cardTargets = await hitTargets( page, [ '#spw-msgs [data-spw-handoff-send]', '#spw-msgs [data-spw-handoff-dismiss]' ] );
		check( cardTargets.every( ( t ) => ! t.missing && parseInt( t.hit.split( 'x' )[ 1 ], 10 ) >= 44 ), `${ tag } the card's two buttons are 44px tall to a finger: ${ cardTargets.map( ( t ) => t.selector + ' ' + ( t.missing ? 'missing' : t.hit ) ).join( ', ' ) }` );
	}
	// T17 (WCAG 2.4.11): a focused card field is scrolled clear of the floating "latest message" button.
	// Filler rows below the card keep the thread off its end, so the button shows; they are removed again.
	const obscured = await page.evaluate( async () => {
		const frame = () => new Promise( ( resolve ) => requestAnimationFrame( () => requestAnimationFrame( resolve ) ) );
		const msgs = document.getElementById( 'spw-msgs' );
		const jump = document.getElementById( 'spw-jump' );
		const field = document.querySelector( '#spw-msgs .spw-handoff [data-spw-handoff-field="email"]' );
		const filler = [];
		for ( let i = 0; i < 30; i++ ) {
			const row = document.createElement( 'div' );
			row.className = 'spw-row spw-row--ai';
			row.textContent = 'filler ' + i;
			msgs.insertBefore( row, document.getElementById( 'spw-chips' ) );
			filler.push( row );
		}
		const behavior = msgs.style.scrollBehavior;
		msgs.style.scrollBehavior = 'auto';
		msgs.scrollTop = 0;
		msgs.dispatchEvent( new Event( 'scroll' ) );
		await frame();
		field.focus();
		msgs.dispatchEvent( new Event( 'scroll' ) );
		await frame();
		const a = field.getBoundingClientRect();
		const b = jump.getBoundingClientRect();
		const shown = ! jump.hidden && b.width > 0;
		const overlap = shown && a.left < b.right && b.left < a.right && a.top < b.bottom && b.top < a.bottom;
		filler.forEach( ( row ) => row.remove() );
		msgs.style.scrollBehavior = behavior;
		msgs.dispatchEvent( new Event( 'scroll' ) );
		field.blur();
		await frame();
		return { shown, overlap, gap: Math.round( b.top - a.bottom ) };
	} );
	check( obscured.shown && ! obscured.overlap, `${ tag } a focused card field is never under the "latest message" button (button shown ${ obscured.shown }, overlap ${ obscured.overlap }, gap ${ obscured.gap }px)` );
	const cardRatios = await contrast( page, [
		{ name: 'card title', selector: '#spw-msgs .spw-handoff__title', min: 4.5 },
		{ name: 'card text', selector: '#spw-msgs .spw-handoff__text', min: 4.5 },
		{ name: 'card label', selector: '#spw-msgs .spw-handoff__label', min: 4.5 },
		{ name: 'card send button', selector: '#spw-msgs [data-spw-handoff-send]', min: 4.5 },
		{ name: 'card "Not now"', selector: '#spw-msgs [data-spw-handoff-dismiss]', min: 4.5 },
	] );
	for ( const r of cardRatios ) {
		check( ! r.missing && r.ratio >= r.min, `${ tag } contrast of the ${ r.name } is ${ r.ratio }:1 (at least ${ r.min }:1)` );
	}
	await settle( page );
	await shot( page, `widget-handoff-${ tag }.png` );

	await page.locator( '#spw-msgs [data-spw-handoff-dismiss]' ).click();
	card = await page.evaluate( () => ( { cards: document.querySelectorAll( '#spw-msgs .spw-handoff' ).length, chips: !! document.getElementById( 'spw-chips' ).offsetParent, inputFocused: document.activeElement === document.getElementById( 'spw-input' ) } ) );
	check( card.cards === 0 && card.chips && card.inputFocused && ctx.handoffBodies.length === 0, `${ tag } "Not now" removes the card, brings the chips back, returns focus to the composer and sends nothing` );

	await page.locator( '.spw-footnote [data-spw-handoff]' ).click();
	await page.locator( '#spw-msgs .spw-handoff' ).waitFor( { timeout: 10000 } );
	await page.locator( '#spw-msgs [data-spw-handoff-send]' ).click();
	const invalid = await page.evaluate( () => {
		const email = document.querySelector( '#spw-msgs [data-spw-handoff-field="email"]' );
		return { invalid: email.getAttribute( 'aria-invalid' ) === 'true', focused: document.activeElement === email, describedBy: email.getAttribute( 'aria-describedby' ) || '' };
	} );
	check( invalid.invalid && invalid.focused && invalid.describedBy !== '' && ctx.handoffBodies.length === 0, `${ tag } a missing required e-mail stops the send, marks the field and moves focus to it` );
	ctx.refusalsFromMocks = true;
	ctx.handoff = 'refused';
	await page.locator( '#spw-msgs [data-spw-handoff-field="email"]' ).fill( 'visitor@example.invalid' );
	await page.locator( '#spw-msgs [data-spw-handoff-send]' ).click();
	// The name field has an error box of its own, so wait on the field the site named.
	await page.locator( '#spw-msgs .spw-handoff [data-spw-handoff-field="email"][aria-invalid="true"]' ).waitFor( { timeout: 10000 } );
	check( ctx.handoffBodies.length === 1 && await page.locator( '#spw-msgs .spw-handoff.is-sent' ).count() === 0, `${ tag } a refusal from the site lands on the field it names and the card lives on` );
	ctx.handoff = 'sent';
	await page.locator( '#spw-msgs [data-spw-handoff-field="email"]' ).press( 'Enter' );
	await page.locator( '#spw-msgs .spw-handoff.is-sent' ).waitFor( { timeout: 10000 } );
	ctx.refusalsFromMocks = false;
	const sent = await page.evaluate( () => ( {
		status: ( document.querySelector( '#spw-msgs .spw-handoff.is-sent .spw-handoff__status' ) || {} ).textContent || '',
		role: ( document.querySelector( '#spw-msgs .spw-handoff.is-sent .spw-handoff__status' ) || { getAttribute: () => '' } ).getAttribute( 'role' ),
		chips: !! document.getElementById( 'spw-chips' ).offsetParent,
	} ) );
	const body = ctx.handoffBodies[ 1 ] || {};
	check( ctx.handoffBodies.length === 2 && body.email === 'visitor@example.invalid' && ! ( 'phone' in body ) && ! ( 'website' in body ), `${ tag } Enter in the field sends once, with only the details the owner asked for: ${ JSON.stringify( Object.keys( body ).filter( ( k ) => k !== 'messages' ) ) }` );
	check( inLocale( sent.status, 'Sent — we will be in touch.' ) && sent.role === 'status' && sent.chips, `${ tag } the confirmation promises no operator or reply time ("${ sent.status }") and the chips come back` );
	await settle( page );
	await shot( page, `widget-handoff-sent-${ tag }.png` );

	// ── voice: transcript-first, nothing sends on its own ──
	const asksBeforeVoice = ctx.requests.filter( ( r ) => r === 'POST widget/ask' ).length;
	const userTurnsBefore = await page.locator( '#spw-msgs .spw-row--user' ).count();
	const idleBox = await page.evaluate( () => Math.round( document.querySelector( '.spw-composer__box' ).getBoundingClientRect().height ) );
	await page.locator( '#spw-mic' ).click();
	await page.waitForFunction( () => document.querySelector( '[data-spw-voice-status]' ).textContent !== '' );
	// Wait for the colour transitions of both mic buttons and the box to end; the pulse and
	// the dot loop forever, so only CSS transitions are awaited.
	await page.evaluate( () => Promise.all( [ document.getElementById( 'spw-mic' ), document.getElementById( 'spw-voice' ), document.querySelector( '.spw-composer__box' ) ]
		.flatMap( ( node ) => node.getAnimations().filter( ( a ) => a instanceof CSSTransition ).map( ( a ) => a.finished ) ) ) );
	const recording = await page.evaluate( () => {
		const shown = ( node ) => !! node && getComputedStyle( node ).display !== 'none' && node.getClientRects().length > 0;
		const mics = [ 'spw-mic', 'spw-voice' ].map( ( id ) => {
			const mic = document.getElementById( id );
			return { id, bg: getComputedStyle( mic ).backgroundColor, stop: shown( mic.querySelector( '.spw-mic__stop svg' ) ), ico: shown( mic.querySelector( '.spw-mic__ico' ) ), label: mic.getAttribute( 'aria-label' ), title: mic.title };
		} );
		const box = document.querySelector( '.spw-composer__box' );
		return {
			status: document.querySelector( '[data-spw-voice-status]' ).textContent,
			role: document.querySelector( '[data-spw-voice-status]' ).getAttribute( 'role' ),
			pressed: document.getElementById( 'spw-mic' ).getAttribute( 'aria-pressed' ),
			placeholder: document.getElementById( 'spw-input' ).getAttribute( 'placeholder' ),
			// Whether the whole placeholder is visible: the height its text needs against the box's height.
			fits: ( ( i ) => { const has = i.clientHeight; i.value = i.placeholder; const needs = i.scrollHeight; i.value = ''; return needs <= has + 1; } )( document.getElementById( 'spw-input' ) ),
			boxHeight: Math.round( box.getBoundingClientRect().height ),
			border: getComputedStyle( box ).borderTopColor,
			rec: shown( document.getElementById( 'spw-rec' ) ),
			recHidden: document.getElementById( 'spw-rec' ).getAttribute( 'aria-hidden' ),
			time: document.getElementById( 'spw-rec-time' ).textContent,
			mics,
			qa: window.__qa,
		};
	} );
	check( recording.fits && recording.boxHeight === idleBox, `${ tag } "listening" fits on one line: the message box keeps its ${ idleBox }px height while recording (${ recording.boxHeight }px)` );
	check( inLocale( recording.placeholder, 'Listening…' ), `${ tag } the placeholder while recording is the short "${ recording.placeholder }"` );
	check( recording.qa.micAsked === 1 && recording.pressed === 'true' && recording.role === 'status', `${ tag } the mic asks for the microphone once, reports itself pressed and announces through a status region` );
	check( inLocale( recording.status, 'Listening… tap the mic again to stop' ) && recording.status !== recording.placeholder, `${ tag } a screen reader still hears the whole instruction ("${ recording.status }")` );
	check( recording.rec && recording.recHidden === 'true' && recording.border === 'rgb(255, 69, 95)', `${ tag } the box shows it is recording: coral frame (${ recording.border }) and a live dot with the time, hidden from the status region's audience` );
	check( ( persianRun ? /^[۰-۹]:[۰-۹]{2}$/ : /^\d:\d{2}$/ ).test( recording.time ), `${ tag } the elapsed time reads m:ss in the page's digits ("${ recording.time }")` );
	check(
		recording.mics.every( ( m ) => m.bg === 'rgb(255, 69, 95)' && m.stop && ! m.ico && inLocale( m.label, 'Stop recording' ) && m.label === m.title ),
		`${ tag } both mic buttons turn into coral stop buttons with a "Stop recording" label and tooltip: ${ JSON.stringify( recording.mics.map( ( m ) => [ m.id, m.bg, m.stop, m.label ] ) ) }`
	);
	// Polled on a timer, not on animation frames, and the state is printed so a miss can be read.
	const ticked = await page.waitForFunction( ( first ) => document.getElementById( 'spw-rec-time' ).textContent !== first, recording.time, { timeout: 3000, polling: 100 } ).then( () => true, () => false );
	const timeAfter = await page.evaluate( () => ( {
		text: document.getElementById( 'spw-rec-time' ).textContent,
		recording: document.getElementById( 'spw-mic' ).getAttribute( 'aria-pressed' ),
		shown: ! document.getElementById( 'spw-rec' ).hidden,
		state: window.__qa && window.__qa.started,
	} ) );
	check( ticked, `${ tag } the recording time runs while the microphone is on ("${ recording.time }" → ${ JSON.stringify( timeAfter ) })` );
	const recRatios = await contrast( page, [ { name: 'recording time', selector: '#spw-rec-time', min: 4.5 } ] );
	for ( const r of recRatios ) {
		check( ! r.missing && r.ratio >= r.min, `${ tag } contrast of the ${ r.name } is ${ r.ratio }:1 (at least ${ r.min }:1)` );
	}
	check( ctx.uploads === 0, `${ tag } recording uploads nothing yet` );
	await shot( page, `widget-voice-recording-${ tag }.png` );
	await page.locator( '#spw-mic' ).click();
	const stopped = await page.evaluate( () => ( {
		rec: getComputedStyle( document.getElementById( 'spw-rec' ) ).display,
		recording: document.querySelector( '.spw-composer__box' ).classList.contains( 'spw-composer__box--recording' ),
		ico: getComputedStyle( document.querySelector( '#spw-mic .spw-mic__ico' ) ).display,
		label: document.getElementById( 'spw-mic' ).getAttribute( 'aria-label' ),
		placeholder: document.getElementById( 'spw-input' ).getAttribute( 'placeholder' ),
	} ) );
	check( stopped.rec === 'none' && ! stopped.recording && stopped.ico !== 'none' && inLocale( stopped.label, 'Voice input' ) && inLocale( stopped.placeholder, 'Write a message…' ), `${ tag } stopping clears the dot, the frame and the stop icon and brings the usual placeholder back` );
	await page.waitForFunction( () => document.getElementById( 'spw-input' ).value !== '', null, { timeout: 15000 } );
	const transcribed = await page.evaluate( () => ( {
		value: document.getElementById( 'spw-input' ).value,
		focused: document.activeElement === document.getElementById( 'spw-input' ),
		status: document.querySelector( '[data-spw-voice-status]' ).textContent,
		tracks: window.__qa.tracksStopped,
		direction: getComputedStyle( document.getElementById( 'spw-input' ) ).direction,
	} ) );
	const userTurnsAfter = await page.locator( '#spw-msgs .spw-row--user' ).count();
	check( ctx.uploads === 1 && transcribed.value === TRANSCRIPT && transcribed.focused && transcribed.tracks === 1, `${ tag } one clip is transcribed, the microphone is released and the words wait in the focused message box` );
	check( userTurnsAfter === userTurnsBefore && ctx.requests.filter( ( r ) => r === 'POST widget/ask' ).length === asksBeforeVoice, `${ tag } the transcript is not asked: no new turn and no /widget/ask request` );
	check( inLocale( transcribed.status, 'Your words are in the message box. Check them, then press Send. Nothing was sent yet.' ), `${ tag } the visitor is told nothing was sent yet ("${ transcribed.status.slice( 0, 60 ) }")` );
	check( transcribed.direction === ( persianRun ? 'rtl' : 'ltr' ), `${ tag } the transcript reads in its own direction (${ transcribed.direction })` );
	await shot( page, `widget-voice-transcript-${ tag }.png` );
	ctx.ask = 'plain';
	await page.locator( '#spw-send' ).click();
	await page.waitForFunction( ( n ) => document.querySelectorAll( '#spw-msgs .spw-row--user' ).length > n, userTurnsAfter, { timeout: 15000 } );
	await page.waitForFunction( () => ! document.querySelector( '#spw-typing' ), null, { timeout: 15000 } );
	const afterSend = await page.evaluate( () => ( { status: document.querySelector( '[data-spw-voice-status]' ).textContent, spoken: window.__qa.spoken.length } ) );
	check( ctx.requests.filter( ( r ) => r === 'POST widget/ask' ).length === asksBeforeVoice + 1 && afterSend.status === '', `${ tag } only the deliberate Send asks the question, and the "nothing sent yet" line clears` );
	check( afterSend.spoken === 0, `${ tag } with automatic read-aloud off no answer is spoken (${ afterSend.spoken })` );

	// ── full screen (desktop): modal, scrim, column, lock, trap, Escape ──
	if ( view.name === '1440' ) {
		const lockBefore = await page.evaluate( () => [ document.documentElement.style.overflow, document.body.style.overflow ] );
		await page.locator( '#spw-expand' ).click();
		await page.locator( '#spw-panel.spw-panel--full' ).waitFor();
		await settle( page );
		const full = await page.evaluate( () => {
			const panel = document.getElementById( 'spw-panel' );
			const b = panel.getBoundingClientRect();
			return {
				modal: panel.getAttribute( 'aria-modal' ),
				size: Math.round( b.width ) + '×' + Math.round( b.height ),
				scrim: getComputedStyle( document.getElementById( 'spw-scrim' ) ).display,
				side: document.querySelectorAll( '.spw-side__q' ).length,
				sideDisplay: getComputedStyle( document.querySelector( '.spw-side' ) ).display,
				lock: [ document.documentElement.style.overflow, document.body.style.overflow ],
				label: document.getElementById( 'spw-expand' ).getAttribute( 'aria-label' ),
				focusInside: panel.contains( document.activeElement ),
			};
		} );
		check( full.modal === 'true' && full.size === '940×760' && full.scrim === 'block', `${ tag } full screen is a modal 940×760 frame over a scrim (${ full.modal }, ${ full.size }, ${ full.scrim })` );
		check( full.sideDisplay === 'flex' && full.side === suggestions, `${ tag } the popular-questions column lists the ${ full.side } saved questions` );
		check( full.lock[ 0 ] === 'hidden' && full.lock[ 1 ] === 'hidden', `${ tag } the page behind full screen is locked (F-08)` );
		check( inLocale( full.label, 'Exit full screen' ) && full.focusInside, `${ tag } the button now says "${ full.label }" and focus is inside` );
		let escaped = '';
		const trail = [];
		for ( let i = 0; i < 24 && ! escaped; i++ ) {
			await page.keyboard.press( 'Tab' );
			const at = await page.evaluate( () => {
				const a = document.activeElement;
				const name = a ? ( a.id || a.getAttribute( 'data-spw-handoff-field' ) || a.getAttribute( 'data-spw-ask' ) || a.className || a.tagName ) : 'none';
				return { name: String( name ).slice( 0, 40 ), inside: !! a && document.getElementById( 'spw-panel' ).contains( a ) };
			} );
			trail.push( at.name );
			if ( ! at.inside ) { escaped = at.name; }
		}
		check( ! escaped, `${ tag } 24 presses of Tab never leave the full-screen panel${ escaped ? ' (escaped to ' + escaped + ' after ' + trail.join( ' → ' ) + ')' : '' }` );
		const sideRatios = await contrast( page, [
			{ name: 'column label', selector: '.spw-side__label', min: 4.5 },
			{ name: 'popular question', selector: '.spw-side__q', min: 4.5 },
			{ name: 'column handoff button', selector: '.spw-side__btn', min: 4.5 },
		] );
		for ( const r of sideRatios ) {
			check( ! r.missing && r.ratio >= r.min, `${ tag } contrast of the ${ r.name } is ${ r.ratio }:1 (at least ${ r.min }:1)` );
		}
		await shot( page, `widget-fullscreen-${ tag }.png` );
		await page.locator( '#spw-input' ).focus();
		await page.keyboard.press( 'Escape' );
		await settle( page );
		const left = await page.evaluate( () => ( {
			full: document.getElementById( 'spw-panel' ).classList.contains( 'spw-panel--full' ),
			open: document.getElementById( 'spw-panel' ).classList.contains( 'spw-panel--open' ),
			active: document.activeElement ? document.activeElement.id : '',
			lock: [ document.documentElement.style.overflow, document.body.style.overflow ],
		} ) );
		check( ! left.full && left.open && left.active === 'spw-input', `${ tag } Escape leaves full screen, keeps the chat open and keeps the composer's focus` );
		check( left.lock[ 0 ] === lockBefore[ 0 ] && left.lock[ 1 ] === lockBefore[ 1 ], `${ tag } the page scroll is restored exactly` );
	} else {
		const expand = await page.evaluate( () => getComputedStyle( document.getElementById( 'spw-expand' ) ).display );
		check( expand === 'none', `${ tag } the full-screen button is hidden: the sheet already fills the screen (${ expand })` );
	}

	// ── the "latest message" button never takes focus with it when it hides ──
	// The T13 flake "Tab left full screen" (focus trail ending at the handoff e-mail field, then <body>):
	// Tab reached the jump button while the thread was still scrolling to its end, the button hid itself
	// and focus fell to <body>. Reproduced deterministically here with filler rows that are removed again.
	const jumpFocus = await page.evaluate( async () => {
		const frame = () => new Promise( ( resolve ) => requestAnimationFrame( () => requestAnimationFrame( resolve ) ) );
		const msgs = document.getElementById( 'spw-msgs' );
		const jump = document.getElementById( 'spw-jump' );
		const filler = [];
		for ( let i = 0; i < 40; i++ ) {
			const row = document.createElement( 'div' );
			row.className = 'spw-row spw-row--ai';
			row.textContent = 'filler ' + i;
			msgs.insertBefore( row, document.getElementById( 'spw-chips' ) );
			filler.push( row );
		}
		const behavior = msgs.style.scrollBehavior;
		msgs.style.scrollBehavior = 'auto';
		msgs.scrollTop = 0;
		msgs.dispatchEvent( new Event( 'scroll' ) );
		await frame();
		const shown = ! jump.hidden;
		jump.focus();
		msgs.scrollTop = msgs.scrollHeight;
		msgs.dispatchEvent( new Event( 'scroll' ) );
		await frame();
		const active = document.activeElement;
		const result = { shown, hidden: jump.hidden, active: active === document.body ? 'body' : ( active.id || active.className ) };
		filler.forEach( ( row ) => row.remove() );
		msgs.style.scrollBehavior = behavior;
		msgs.dispatchEvent( new Event( 'scroll' ) );
		await frame();
		return result;
	} );
	check( jumpFocus.shown && jumpFocus.hidden && jumpFocus.active === 'spw-input', `${ tag } when the focused "latest message" button hides, focus moves to the message box instead of falling out of the panel (shown ${ jumpFocus.shown }, hidden ${ jumpFocus.hidden }, focus ${ jumpFocus.active })` );

	// ── Escape closes the chat and hands focus back to the launcher ──
	await page.locator( '#spw-input' ).focus();
	await page.keyboard.press( 'Escape' );
	await page.locator( '#spw-panel.spw-panel--open' ).waitFor( { state: 'hidden' } );
	const back = await page.waitForFunction( () => document.activeElement && document.activeElement.id === 'spw-toggle', null, { timeout: 3000 } ).then( () => true, () => false );
	check( back, `${ tag } Escape closes the chat and focus returns to the launcher` );

	// ── 390: the sheet holds the page still and gives it back where it was (F-17) ──
	if ( view.name === '390' ) {
		const lockBefore = await page.evaluate( () => [ document.documentElement.style.overflow, document.body.style.overflow ] );
		const startY = await page.evaluate( () => {
			// A short front page cannot scroll at all; a spacer makes the lock measurable.
			if ( document.documentElement.scrollHeight < window.innerHeight + 1400 ) {
				const spacer = document.createElement( 'div' );
				spacer.id = 'aisa-qa-spacer';
				spacer.style.height = '2000px';
				document.body.appendChild( spacer );
			}
			// The theme scrolls smoothly; jump at once so the start position is settled before opening.
			window.scrollTo( { top: 600, behavior: 'instant' } );
			return Math.round( window.scrollY );
		} );
		await page.locator( '#spw-toggle' ).click();
		await page.locator( '#spw-panel.spw-panel--open' ).waitFor();
		await settle( page );
		// Wheel over the header, which has nothing of its own to scroll: an unlocked page would move.
		const head = await page.locator( '.spw-header' ).boundingBox();
		await page.mouse.move( head.x + head.width / 2, head.y + head.height / 2 );
		await page.mouse.wheel( 0, 700 );
		const moved = await page.waitForFunction( ( y ) => Math.round( window.scrollY ) !== y, startY, { timeout: 800 } ).then( () => true, () => false );
		const held = await page.evaluate( () => ( {
			y: Math.round( window.scrollY ),
			modal: document.getElementById( 'spw-panel' ).getAttribute( 'aria-modal' ),
			lock: [ document.documentElement.style.overflow, document.body.style.overflow ],
		} ) );
		check( startY === 600 && ! moved && held.modal === 'true' && held.lock[ 0 ] === 'hidden' && held.lock[ 1 ] === 'hidden', `${ tag } with the sheet open the page behind does not scroll (scrollY ${ startY } → ${ held.y }, aria-modal ${ held.modal })` );
		await page.locator( '#spw-close' ).click();
		await page.locator( '#spw-panel.spw-panel--open' ).waitFor( { state: 'hidden' } );
		const released = await page.evaluate( () => ( {
			y: Math.round( window.scrollY ),
			modal: document.getElementById( 'spw-panel' ).getAttribute( 'aria-modal' ),
			lock: [ document.documentElement.style.overflow, document.body.style.overflow ],
		} ) );
		check( released.y === startY && released.modal === 'false' && released.lock[ 0 ] === lockBefore[ 0 ] && released.lock[ 1 ] === lockBefore[ 1 ], `${ tag } closing the sheet gives the page back where it was (scrollY ${ released.y }, overflow ${ JSON.stringify( released.lock ) })` );
		// Control: the same wheel does move the page once the sheet is closed, so the probe can see a scroll.
		await page.mouse.wheel( 0, 300 );
		const free = await page.waitForFunction( ( y ) => Math.round( window.scrollY ) !== y, startY, { timeout: 3000 } ).then( () => true, () => false );
		check( free, `${ tag } with the sheet closed the page scrolls again` );
		await page.evaluate( () => { const spacer = document.getElementById( 'aisa-qa-spacer' ); if ( spacer ) { spacer.remove(); } window.scrollTo( { top: 0, behavior: 'instant' } ); } );
	}
	check( await docOverflow( page ) <= 1, `${ tag } no horizontal document overflow after the whole run` );

	const routes = Array.from( new Set( ctx.requests ) ).sort();
	check( routes.every( ( r ) => [ 'POST public/voice', 'POST widget/ask', 'POST widget/handoff' ].includes( r ) ), `${ tag } the widget spoke only to its three routes: ${ JSON.stringify( routes ) }` );
	check( ctx.consoleErrors.length === 0, `${ tag } console/pageerror ${ JSON.stringify( ctx.consoleErrors ) }` );
	check( ctx.httpFailures.length === 0, `${ tag } unexpected HTTP >= 400 ${ JSON.stringify( ctx.httpFailures ) }` );
	await context.close();

	if ( view.name === '1440' && ! reducedMotionDone ) {
		const quiet = await browser.newContext( { viewport: { width: 1440, height: 960 }, reducedMotion: 'reduce' } );
		const still = await quiet.newPage();
		await still.goto( baseUrl, { waitUntil: 'domcontentloaded', timeout: 90000 } );
		await still.locator( '#spw-toggle' ).waitFor( { state: 'visible', timeout: 30000 } );
		const ring = await still.evaluate( () => getComputedStyle( document.querySelector( '.spw-toggle__ring' ) ).display );
		await still.locator( '#spw-toggle' ).click();
		await still.locator( '#spw-panel.spw-panel--open' ).waitFor();
		const animation = await still.evaluate( () => getComputedStyle( document.getElementById( 'spw-panel' ) ).animationName );
		check( ring === 'none' && animation === 'none', `1440 prefers-reduced-motion stops the launcher ring (${ ring }) and the panel entry (${ animation })` );
		await quiet.close();
	}
}

/* ══ auto: read-aloud highlights the word being spoken (F-19) ══ */

async function runAuto( browser ) {
	const tag = '1440';
	const context = await browser.newContext( { viewport: { width: 1440, height: 960 } } );
	const page = await context.newPage();
	const ctx = newCtx();
	ctx.ask = 'plain';
	await instrument( page, ctx );
	await mockRoutes( page, ctx );
	await page.goto( baseUrl, { waitUntil: 'domcontentloaded', timeout: 90000 } );
	await page.locator( '#spw-toggle' ).click();
	await page.locator( '#spw-panel.spw-panel--open' ).waitFor();
	await ask( page, persianRun ? 'ارسال چطور است؟' : 'How does delivery work?' );
	await page.waitForFunction( () => window.__qa.spoken.length > 0, null, { timeout: 15000 } );
	const spoken = await page.evaluate( () => window.__qa.spoken[ 0 ] );
	check( spoken === ANSWER_SPOKEN, `${ tag } the text handed to the speech engine is the answer as it reads` );
	const lit = await page.evaluate( () => {
		const u = window.__qaUtterance;
		const text = window.__qa.spoken[ 0 ];
		const words = [];
		const re = /\S+/g;
		let m;
		while ( ( m = re.exec( text ) ) !== null ) { words.push( { word: m[ 0 ], at: m.index } ); }
		const seen = [ words[ 0 ], words[ 3 ], words[ words.length - 1 ] ].map( ( w ) => {
			u.onboundary( { name: 'word', charIndex: w.at } );
			const active = Array.from( document.querySelectorAll( '.spw-word--active' ) );
			return active.length === 1 && active[ 0 ].textContent === w.word;
		} );
		u.onboundary( { name: 'sentence', charIndex: 0 } );
		const afterSentence = document.querySelectorAll( '.spw-word--active' ).length;
		u.onend();
		return { seen, afterSentence, afterEnd: document.querySelectorAll( '.spw-word--active' ).length };
	} );
	check( lit.seen.every( Boolean ) && lit.afterSentence === 1 && lit.afterEnd === 0, `${ tag } each word boundary lights exactly the word being read, and the end clears it (F-19): ${ JSON.stringify( lit ) }` );

	// A follow-up carries the previous turns; another page in the same tab continues the recorded conversation.
	const asked = ( text ) => Promise.all( [ page.waitForResponse( ( r ) => r.url().includes( '/aisaautopilot/v1/widget/ask' ) ), ask( page, text ) ] );
	await asked( persianRun ? 'هزینه‌اش چقدر است؟' : 'And how much is it?' );
	await page.reload( { waitUntil: 'domcontentloaded' } );
	await page.locator( '#spw-toggle' ).click();
	await page.locator( '#spw-panel.spw-panel--open' ).waitFor();
	await asked( persianRun ? 'ساعت کاری؟' : 'What are your hours?' );
	const bodies = ctx.askBodies;
	check(
		3 === bodies.length
		&& null === bodies[ 0 ].conversation_id && Array.isArray( bodies[ 0 ].history ) && 0 === bodies[ 0 ].history.length
		&& CONV_ID === bodies[ 1 ].conversation_id && 2 === ( bodies[ 1 ].history || [] ).length
		&& 'user' === bodies[ 1 ].history[ 0 ].role && 'assistant' === bodies[ 1 ].history[ 1 ].role
		&& CONV_ID === bodies[ 2 ].conversation_id && 0 === ( bodies[ 2 ].history || [ 1 ] ).length,
		`${ tag } a follow-up sends the previous turns, and after moving to another page the same recorded conversation continues with a fresh history: ${ JSON.stringify( bodies.map( ( b ) => [ b.conversation_id, ( b.history || [] ).length ] ) ) }`
	);
	check( ctx.consoleErrors.length === 0, `${ tag } console/pageerror ${ JSON.stringify( ctx.consoleErrors ) }` );
	check( ctx.httpFailures.length === 0, `${ tag } unexpected HTTP >= 400 ${ JSON.stringify( ctx.httpFailures ) }` );
	await context.close();
}

/* ══ quiet: no channel and no voice means no offer and no mic ══ */

async function runQuiet( browser, view ) {
	const tag = view.name;
	const context = await browser.newContext( { viewport: { width: view.width, height: view.height } } );
	const page = await context.newPage();
	const ctx = newCtx();
	ctx.ask = 'notFound';
	await instrument( page, ctx );
	await mockRoutes( page, ctx );
	await page.goto( baseUrl, { waitUntil: 'domcontentloaded', timeout: 90000 } );
	await page.locator( '#spw-toggle' ).click();
	await page.locator( '#spw-panel.spw-panel--open' ).waitFor();
	const before = await page.evaluate( () => ( {
		mic: !! document.getElementById( 'spw-mic' ) || !! document.getElementById( 'spw-voice' ),
		status: document.querySelectorAll( '[data-spw-voice-status]' ).length,
		triggers: document.querySelectorAll( '[data-spw-handoff]' ).length,
		template: !! document.getElementById( 'spw-handoff-template' ),
	} ) );
	check( ! before.mic && before.status === 0, `${ tag } with voice off there is no microphone and no voice status region` );
	check( before.triggers === 0 && ! before.template, `${ tag } with no ready channel there is no "talk to a person" button and no card template` );
	await ask( page, persianRun ? 'سفارشم کجاست؟' : 'Where is my order?' );
	await page.locator( '#spw-msgs .spw-row--ai .spw-md-p' ).last().waitFor( { timeout: 15000 } );
	await page.waitForFunction( () => ! document.querySelector( '#spw-typing' ), null, { timeout: 15000 } );
	const cards = await page.locator( '#spw-msgs .spw-handoff' ).count();
	check( cards === 0, `${ tag } even a reply that offers a person produces no card (${ cards })` );
	check( await docOverflow( page ) <= 1, `${ tag } no horizontal document overflow` );
	await settle( page );
	await shot( page, `widget-quiet-${ tag }.png` );

	if ( view.name === '1440' ) {
		/* The one real call of the matrix: it carries no audio and is refused by the voice gate before
		   the rate limiter and any provider lookup. It goes through the context's request API, so the
		   deliberate 403 is not a page failure. */
		const refused = await context.request.post( baseUrl + 'wp-json/aisaautopilot/v1/public/voice', { failOnStatusCode: false } );
		const body = await refused.json();
		check( refused.status() === 403 && body.code === 'voice_disabled', `${ tag } POST /public/voice is refused with 403 voice_disabled while the mic is off (F-09): ${ refused.status() } ${ body.code }` );
		check( inLocale( body.message, 'Voice input is turned off for this site.' ), `${ tag } the refusal explains itself in the site language ("${ body.message }")` );
	}
	check( ctx.requests.every( ( r ) => r === 'POST widget/ask' ), `${ tag } only the question was sent: ${ JSON.stringify( Array.from( new Set( ctx.requests ) ) ) }` );
	check( ctx.consoleErrors.length === 0, `${ tag } console/pageerror ${ JSON.stringify( ctx.consoleErrors ) }` );
	check( ctx.httpFailures.length === 0, `${ tag } unexpected HTTP >= 400 ${ JSON.stringify( ctx.httpFailures ) }` );
	await context.close();
}

( async () => {
	fs.mkdirSync( screenshotDir, { recursive: true } );
	const browser = await chromium.launch( { executablePath: chromePath, headless: true } );
	try {
		if ( scenario === 'main' ) {
			let reduced = locale !== 'en_US';
			for ( const view of views ) {
				await runMain( browser, view, reduced );
				reduced = true;
			}
		} else if ( scenario === 'auto' ) {
			await runAuto( browser );
		} else {
			for ( const view of views ) { await runQuiet( browser, view ); }
		}
	} catch ( error ) {
		check( false, 'the run completes without an exception: ' + ( error && error.stack ? error.stack.split( '\n' ).slice( 0, 3 ).join( ' | ' ) : String( error ) ) );
	} finally {
		await browser.close();
		console.log( `SUMMARY ${ locale } ${ scenario } FAIL ${ failures.length }` );
		process.exitCode = failures.length ? 1 : 0;
	}
} )();
