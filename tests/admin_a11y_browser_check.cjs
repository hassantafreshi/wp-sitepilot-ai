/*
 * Admin accessibility, RTL and responsive browser check (T17, Q4.3).
 *
 * Every admin route of the plugin, in the locale of the temporary QA account:
 *   - 1440, 1024 and 390 px: no horizontal overflow, no control pushed off screen, no clipped text;
 *   - accessible names on every visible control (icon-only buttons included) and a label on every field;
 *   - WCAG 2.2 contrast: 4.5:1 for text, 3:1 for large text (24px, or 18.66px bold);
 *   - keyboard: every Tab stop inside the plugin screen is visible and shows a focus indicator;
 *   - 390 px: every control that is not an inline text link is at least 24x24 (WCAG 2.2 AA 2.5.8);
 *   - prefers-reduced-motion: no animation or transition inside .sp-app runs longer than 1ms;
 *   - dialogs: Integrations configure dialog, the shared confirm dialog (Usage reset) and the
 *     handoff inbox drawer open from the keyboard, move focus inside, close on Escape and
 *     return focus to their opener, and the confirm sends no request when dismissed.
 * Read-only: it never saves, so it needs no fixture. Credentials come from the environment only.
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
const screenshotDir = process.env.AISAAUTOPILOT_SCREENSHOT_DIR || path.join( process.env.TEMP || '.', 'aisa-admin-a11y' );
const onlyRoutes = ( process.env.AISAAUTOPILOT_A11Y_ROUTES || '' ).split( ',' ).map( ( s ) => s.trim() ).filter( Boolean );
const failures = [];

// Every admin surface: the 18 render-smoke routes plus the KB console, advanced widget
// customisation, the EFB Copilot page and Form Studio.
const ROUTES = [
	[ 'home', 'page=aisaautopilot-home' ],
	[ 'usage', 'page=aisaautopilot-usage' ],
	[ 'health', 'page=aisaautopilot-connections' ],
	[ 'activity', 'page=aisaautopilot-logs' ],
	[ 'setup', 'page=aisaautopilot-setup' ],
	[ 'setup-advanced', 'page=aisaautopilot-settings' ],
	[ 'integrations', 'page=aisaautopilot-integrations' ],
	[ 'kb', 'page=aisaautopilot-kb' ],
	[ 'kb-queue', 'page=aisaautopilot-kb&tab=queue' ],
	[ 'kb-console', 'page=aisaautopilot-chat' ],
	[ 'answer-conversations', 'page=aisaautopilot-conversations' ],
	[ 'answer-widget', 'page=aisaautopilot-conversations&tab=widget' ],
	[ 'answer-handoff', 'page=aisaautopilot-conversations&tab=handoff' ],
	[ 'answer-voice', 'page=aisaautopilot-conversations&tab=voice' ],
	[ 'widget-customise', 'page=aisaautopilot-widget' ],
	[ 'handoff-inbox', 'page=aisaautopilot-handoffs' ],
	[ 'store-new', 'page=aisaautopilot-wc-dashboard&tab=newproduct' ],
	[ 'store-capabilities', 'page=aisaautopilot-wc-dashboard&tab=capabilities' ],
	[ 'store-products', 'page=aisaautopilot-wc-dashboard&tab=products' ],
	[ 'store-orders', 'page=aisaautopilot-wc-dashboard&tab=orders' ],
	// Legacy alias where T15 saw F15-1 (title not on the right edge in fa_IR).
	[ 'store-legacy-settings', 'page=aisaautopilot-woocommerce' ],
	[ 'studio-write', 'page=aisaautopilot-form-builder&mode=content' ],
	[ 'studio-templates', 'page=aisaautopilot-form-builder&mode=templates' ],
	[ 'studio-history', 'page=aisaautopilot-history' ],
	[ 'form-studio', 'page=aisaautopilot-form-studio' ],
	[ 'efb-copilot', 'page=aisaautopilot-efb' ],
].filter( ( [ name ] ) => ! onlyRoutes.length || onlyRoutes.includes( name ) );

const VIEWPORTS = [
	{ name: '1440', width: 1440, height: 960 },
	{ name: '1024', width: 1024, height: 768 },
	{ name: '390', width: 390, height: 844 },
];

function check( condition, message, details ) {
	if ( condition ) {
		console.log( 'PASS ' + message );
	} else {
		console.log( 'FAIL ' + message );
		( details || [] ).slice( 0, 12 ).forEach( ( line ) => console.log( '    - ' + line ) );
		failures.push( message );
	}
}

async function open( page, query ) {
	let response;
	let lastError;
	for ( let attempt = 1; attempt <= 2; attempt++ ) {
		try {
			response = await page.goto( baseUrl + '/wp-admin/admin.php?' + query, { waitUntil: 'domcontentloaded', timeout: 60000 } );
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
	await page.waitForLoadState( 'load', { timeout: 30000 } ).catch( () => {} );
	// Let the .sp-screen entry animation finish before measuring boxes and colours. Only finite
	// animations settle; spinners and pulses run forever, so they are left out and the wait is capped.
	await page.evaluate( () => Promise.race( [
		Promise.all( document.getAnimations()
			.filter( ( a ) => Number.isFinite( a.effect && a.effect.getComputedTiming().endTime ) )
			.map( ( a ) => a.finished.catch( () => null ) ) ),
		new Promise( ( resolve ) => setTimeout( resolve, 2000 ) ),
	] ) ).catch( () => {} );
	return response;
}

/* ── in-page measurements (run through page.evaluate) ─────────────────── */

function layoutAudit( viewportWidth ) {
	const app = document.querySelector( '.sp-app' );
	const out = { docOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth, offscreen: [], clipped: [], truncated: [], titleGap: null, headerEnd: null };
	if ( ! app ) {
		return out;
	}
	// F15-1: the page title's text starts on the inline-start edge of the header row.
	const title = app.querySelector( '.sp-pagebar__title' );
	const row = app.querySelector( '.sp-pagebar__row' );
	if ( title && row && title.firstChild ) {
		const range = document.createRange();
		range.selectNodeContents( title );
		const t = range.getBoundingClientRect();
		const rr = row.getBoundingClientRect();
		out.titleGap = Math.round( 'rtl' === getComputedStyle( app ).direction ? rr.right - t.right : t.left - rr.left );
		// T16: how far the header row's inline-end edge sits from the window's; equal on every page.
		out.headerEnd = Math.round( 'rtl' === getComputedStyle( app ).direction ? rr.left : document.documentElement.clientWidth - rr.right );
	}
	const describe = ( el ) => el.tagName.toLowerCase() + ( el.id ? '#' + el.id : '' ) + ( el.classList.length ? '.' + Array.from( el.classList ).slice( 0, 2 ).join( '.' ) : '' ) + ' "' + ( el.innerText || el.value || el.getAttribute( 'aria-label' ) || '' ).trim().slice( 0, 40 ) + '"';
	const insideScroller = ( el ) => {
		for ( let p = el.parentElement; p && p !== document.body; p = p.parentElement ) {
			const ox = getComputedStyle( p ).overflowX;
			if ( 'auto' === ox || 'scroll' === ox ) {
				return true;
			}
		}
		return false;
	};
	const visible = ( el ) => el.checkVisibility( { opacityProperty: true, visibilityProperty: true } ) && el.getClientRects().length > 0;
	app.querySelectorAll( 'a[href], button, input:not([type=hidden]), select, textarea, [role=button], [role=tab], h1, h2, h3, p, label, dd, dt, td, th, li, span, strong, code' ).forEach( ( el ) => {
		if ( ! visible( el ) || el.closest( '[aria-hidden="true"], .screen-reader-text, .sp-sr, dialog:not([open])' ) ) {
			return;
		}
		const r = el.getBoundingClientRect();
		if ( r.width < 2 || r.height < 2 ) {
			return;
		}
		if ( ( r.right > viewportWidth + 1 || r.left < -1 ) && ! insideScroller( el ) ) {
			out.offscreen.push( describe( el ) + ' [' + Math.round( r.left ) + '..' + Math.round( r.right ) + ']' );
		}
		// Clipped text: the element hides overflow, its text does not fit, and it does not say so with an ellipsis.
		const cs = getComputedStyle( el );
		const hides = [ 'hidden', 'clip' ].includes( cs.overflowX ) || [ 'hidden', 'clip' ].includes( cs.overflow );
		const hasOwnText = Array.from( el.childNodes ).some( ( n ) => 3 === n.nodeType && n.textContent.trim() );
		if ( hides && hasOwnText && el.scrollWidth > el.clientWidth + 1 && 'ellipsis' !== cs.textOverflow ) {
			out.clipped.push( describe( el ) + ' (' + el.scrollWidth + '>' + el.clientWidth + ')' );
		}
		// F15-3: text cut with an ellipsis hides part of a value; allowed only when a title carries the whole of it.
		if ( hides && hasOwnText && el.scrollWidth > el.clientWidth + 1 && 'ellipsis' === cs.textOverflow && ! el.getAttribute( 'title' ) && ! el.closest( '[title]' ) ) {
			out.truncated.push( describe( el ) + ' (' + el.scrollWidth + '>' + el.clientWidth + ')' );
		}
	} );
	return out;
}

function semanticsAudit() {
	const app = document.querySelector( '.sp-app' );
	const out = { unnamed: [], unlabelled: [] };
	if ( ! app ) {
		return out;
	}
	const visible = ( el ) => el.checkVisibility( { opacityProperty: false, visibilityProperty: true } ) && el.getClientRects().length > 0;
	const describe = ( el ) => el.tagName.toLowerCase() + ( el.id ? '#' + el.id : '' ) + ( el.classList.length ? '.' + Array.from( el.classList ).slice( 0, 3 ).join( '.' ) : '' );
	const byIds = ( ids ) => ids.split( /\s+/ ).map( ( id ) => document.getElementById( id ) ).filter( Boolean ).map( ( n ) => n.textContent.trim() ).join( ' ' ).trim();
	const nameOf = ( el ) => {
		if ( el.getAttribute( 'aria-labelledby' ) ) {
			const t = byIds( el.getAttribute( 'aria-labelledby' ) );
			if ( t ) {
				return t;
			}
		}
		if ( ( el.getAttribute( 'aria-label' ) || '' ).trim() ) {
			return el.getAttribute( 'aria-label' ).trim();
		}
		if ( el.labels && el.labels.length ) {
			const t = Array.from( el.labels ).map( ( l ) => l.textContent.trim() ).join( ' ' ).trim();
			if ( t ) {
				return t;
			}
		}
		if ( [ 'INPUT', 'SELECT', 'TEXTAREA' ].includes( el.tagName ) ) {
			if ( [ 'button', 'submit', 'reset' ].includes( el.type ) && ( el.value || '' ).trim() ) {
				return el.value.trim();
			}
			return ( el.getAttribute( 'title' ) || el.getAttribute( 'placeholder' ) || '' ).trim();
		}
		const text = ( el.innerText || '' ).trim();
		if ( text ) {
			return text;
		}
		const img = el.querySelector( 'img[alt]:not([alt=""])' );
		if ( img ) {
			return img.getAttribute( 'alt' );
		}
		return ( el.getAttribute( 'title' ) || '' ).trim();
	};
	app.querySelectorAll( 'a[href], button, [role=button], [role=tab], [role=switch], summary, input:not([type=hidden]), select, textarea' ).forEach( ( el ) => {
		if ( ! visible( el ) || el.closest( '[aria-hidden="true"], dialog:not([open]), [hidden]' ) ) {
			return;
		}
		// A radio or checkbox hidden behind a styled label still needs a name; one visually hidden for a switch is measured the same way.
		if ( ! nameOf( el ) ) {
			( [ 'INPUT', 'SELECT', 'TEXTAREA' ].includes( el.tagName ) && ! [ 'button', 'submit', 'reset' ].includes( el.type ) ? out.unlabelled : out.unnamed ).push( describe( el ) );
		}
	} );
	return out;
}

function contrastAudit() {
	const app = document.querySelector( '.sp-app' );
	const out = { checked: 0, skipped: 0, low: {} };
	if ( ! app ) {
		return out;
	}
	const parse = ( c ) => {
		const m = /rgba?\(\s*([\d.]+)[ ,]+([\d.]+)[ ,]+([\d.]+)(?:[ ,/]+([\d.]+%?))?\s*\)/.exec( c || '' );
		if ( ! m ) {
			return null;
		}
		let a = undefined === m[ 4 ] ? 1 : parseFloat( m[ 4 ] );
		if ( m[ 4 ] && m[ 4 ].endsWith( '%' ) ) {
			a /= 100;
		}
		return [ +m[ 1 ], +m[ 2 ], +m[ 3 ], a ];
	};
	const over = ( top, bottom ) => {
		const a = top[ 3 ];
		return [ top[ 0 ] * a + bottom[ 0 ] * ( 1 - a ), top[ 1 ] * a + bottom[ 1 ] * ( 1 - a ), top[ 2 ] * a + bottom[ 2 ] * ( 1 - a ), 1 ];
	};
	const lum = ( c ) => {
		const ch = c.slice( 0, 3 ).map( ( v ) => {
			v /= 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
		} );
		return 0.2126 * ch[ 0 ] + 0.7152 * ch[ 1 ] + 0.0722 * ch[ 2 ];
	};
	const hex = ( c ) => '#' + c.slice( 0, 3 ).map( ( v ) => Math.round( v ).toString( 16 ).padStart( 2, '0' ) ).join( '' );
	// Background behind an element: composite the layers from the element up to the root.
	const backgroundOf = ( el ) => {
		const layers = [];
		for ( let n = el; n; n = n.parentElement ) {
			const cs = getComputedStyle( n );
			if ( cs.backgroundImage && 'none' !== cs.backgroundImage && /gradient|url\(/.test( cs.backgroundImage ) ) {
				return null; // A gradient or image behind the text: not measurable from computed colours.
			}
			const bg = parse( cs.backgroundColor );
			if ( bg && bg[ 3 ] > 0 ) {
				layers.push( bg );
				if ( bg[ 3 ] >= 1 ) {
					break;
				}
			}
		}
		let base = [ 255, 255, 255, 1 ];
		for ( let i = layers.length - 1; i >= 0; i-- ) {
			base = over( layers[ i ], base );
		}
		return base;
	};
	const opacityOf = ( el ) => {
		let o = 1;
		for ( let n = el; n && n !== document.documentElement; n = n.parentElement ) {
			o *= parseFloat( getComputedStyle( n ).opacity || '1' );
		}
		return o;
	};
	const walker = document.createTreeWalker( app, NodeFilter.SHOW_TEXT );
	const seen = new Set();
	for ( let node = walker.nextNode(); node; node = walker.nextNode() ) {
		const el = node.parentElement;
		if ( ! el || seen.has( el ) || ! node.textContent.trim() ) {
			continue;
		}
		seen.add( el );
		if ( el.closest( '[aria-hidden="true"], .screen-reader-text, .sp-sr, [hidden], dialog:not([open]), script, style, noscript, svg, option' ) ) {
			continue;
		}
		// Disabled controls are exempt from 1.4.3.
		if ( el.closest( ':disabled, [aria-disabled="true"], .is-disabled' ) ) {
			continue;
		}
		if ( ! el.checkVisibility( { opacityProperty: true, visibilityProperty: true } ) ) {
			continue;
		}
		const r = el.getBoundingClientRect();
		if ( r.width <= 1 || r.height <= 1 ) {
			continue;
		}
		const cs = getComputedStyle( el );
		const fg = parse( cs.color );
		const bg = backgroundOf( el );
		if ( ! fg || ! bg ) {
			out.skipped++;
			continue;
		}
		const fgFlat = over( [ fg[ 0 ], fg[ 1 ], fg[ 2 ], fg[ 3 ] * opacityOf( el ) ], bg );
		const l1 = lum( fgFlat );
		const l2 = lum( bg );
		const ratio = ( Math.max( l1, l2 ) + 0.05 ) / ( Math.min( l1, l2 ) + 0.05 );
		const size = parseFloat( cs.fontSize );
		const bold = parseInt( cs.fontWeight, 10 ) >= 700;
		const min = size >= 24 || ( size >= 18.66 && bold ) ? 3 : 4.5;
		out.checked++;
		if ( ratio + 0.005 < min ) {
			const key = hex( fgFlat ) + ' on ' + hex( bg ) + ' = ' + ratio.toFixed( 2 ) + ':1 (min ' + min + ')';
			if ( ! out.low[ key ] ) {
				out.low[ key ] = [];
			}
			if ( out.low[ key ].length < 3 ) {
				out.low[ key ].push( el.tagName.toLowerCase() + ( el.classList.length ? '.' + Array.from( el.classList ).slice( 0, 2 ).join( '.' ) : '' ) + ' ' + size + 'px "' + node.textContent.trim().slice( 0, 30 ) + '"' );
			}
		}
	}
	return out;
}

/*
 * WCAG 2.2 AA 2.5.8 Target Size (Minimum): a target is at least 24x24 CSS px, or it is
 * undersized but a 24px circle centred on it intersects no other target and no other
 * undersized target's circle (the spacing exception). Inline links inside a sentence are exempt.
 */
function targetAudit() {
	const app = document.querySelector( '.sp-app' );
	const small = [];
	if ( ! app ) {
		return small;
	}
	const all = [];
	app.querySelectorAll( 'a[href], button, [role=button], [role=tab], input[type=checkbox], input[type=radio], select, summary' ).forEach( ( el ) => {
		if ( ! el.checkVisibility( { opacityProperty: true, visibilityProperty: true } ) || el.closest( '[aria-hidden="true"], dialog:not([open]), [hidden]' ) ) {
			return;
		}
		let target = el;
		// A visually hidden checkbox or radio is hit through its label.
		if ( 'INPUT' === el.tagName && el.labels && el.labels.length ) {
			target = el.labels[ 0 ];
		}
		const r = target.getBoundingClientRect();
		if ( r.width === 0 || r.height === 0 ) {
			return;
		}
		// Exception: a link inside a sentence (inline, with text around it) is limited by the line height.
		const cs = getComputedStyle( el );
		if ( 'A' === el.tagName && cs.display.startsWith( 'inline' ) && ! cs.display.includes( 'flex' ) && ! cs.display.includes( 'block' ) ) {
			const parentText = ( el.parentElement ? el.parentElement.textContent : '' ).replace( el.textContent, '' ).trim();
			if ( parentText.length > 0 ) {
				return;
			}
		}
		all.push( {
			el,
			target,
			r,
			undersized: r.width < 24 || r.height < 24,
			desc: el.tagName.toLowerCase() + ( el.id ? '#' + el.id : '' ) + ( el.classList.length ? '.' + Array.from( el.classList ).slice( 0, 2 ).join( '.' ) : '' ) + ' ' + Math.round( r.width ) + 'x' + Math.round( r.height ) + ' "' + ( el.innerText || el.getAttribute( 'aria-label' ) || '' ).trim().slice( 0, 24 ) + '"',
		} );
	} );
	const centre = ( r ) => [ r.left + r.width / 2, r.top + r.height / 2 ];
	const distToRect = ( p, r ) => {
		const dx = Math.max( r.left - p[ 0 ], 0, p[ 0 ] - r.right );
		const dy = Math.max( r.top - p[ 1 ], 0, p[ 1 ] - r.bottom );
		return Math.hypot( dx, dy );
	};
	all.filter( ( t ) => t.undersized ).forEach( ( t ) => {
		const c = centre( t.r );
		const crowded = all.some( ( o ) => {
			if ( o === t || o.target === t.target || o.el.contains( t.el ) || t.el.contains( o.el ) ) {
				return false;
			}
			if ( o.undersized ) {
				const oc = centre( o.r );
				return Math.hypot( c[ 0 ] - oc[ 0 ], c[ 1 ] - oc[ 1 ] ) < 24;
			}
			return distToRect( c, o.r ) < 12;
		} );
		if ( crowded ) {
			small.push( t.desc );
		}
	} );
	return small;
}

function motionAudit() {
	return document.getAnimations()
		.filter( ( a ) => a.effect && a.effect.target && a.effect.target.closest && a.effect.target.closest( '.sp-app' ) )
		.map( ( a ) => {
			const t = a.effect.getComputedTiming();
			const el = a.effect.target;
			return { name: a.animationName || a.transitionProperty || a.constructor.name, duration: t.duration, target: el.tagName.toLowerCase() + ( el.classList.length ? '.' + Array.from( el.classList ).slice( 0, 2 ).join( '.' ) : '' ) };
		} )
		.filter( ( a ) => 'number' === typeof a.duration && a.duration > 1 );
}

/* ── keyboard focus ───────────────────────────────────────────────────── */

async function focusAudit( page, tag ) {
	// Snapshot every focusable control while nothing is focused, then Tab through the plugin screen
	// and compare: a focus stop is visible when outline, box-shadow, border, background or text colour changed.
	const count = await page.evaluate( () => {
		const style = ( el ) => {
			const cs = getComputedStyle( el );
			return [ cs.outlineStyle, cs.outlineWidth, cs.outlineColor, cs.boxShadow, cs.borderColor, cs.backgroundColor, cs.color, cs.textDecorationLine ].join( '|' );
		};
		window.__aisaFocusBase = new Map();
		const nodes = document.querySelectorAll( '.sp-app a[href], .sp-app button, .sp-app input, .sp-app select, .sp-app textarea, .sp-app [tabindex], .sp-app summary' );
		nodes.forEach( ( el ) => window.__aisaFocusBase.set( el, style( el ) ) );
		window.__aisaFocusStyle = style;
		if ( document.activeElement && document.activeElement !== document.body ) {
			document.activeElement.blur();
		}
		return nodes.length;
	} );
	if ( ! count ) {
		return;
	}
	// Enter the screen from its first focusable control, as a keyboard user arriving from the admin menu would.
	await page.evaluate( () => {
		const first = Array.from( document.querySelectorAll( '.sp-app a[href], .sp-app button:not([disabled]), .sp-app input:not([disabled]):not([type=hidden]), .sp-app select:not([disabled]), .sp-app textarea:not([disabled]), .sp-app [tabindex]:not([tabindex="-1"])' ) )
			.find( ( el ) => el.checkVisibility() && el.getClientRects().length );
		if ( first ) {
			first.focus();
		}
	} );
	const invisible = [];
	const noIndicator = [];
	let stops = 0;
	for ( let i = 0; i < 70; i++ ) {
		const info = await page.evaluate( async () => {
			const el = document.activeElement;
			if ( ! el || el === document.body || ! el.closest( '.sp-app' ) ) {
				return null;
			}
			// Focus styles often fade in with a transition; measure the settled state, not its first frame.
			await Promise.race( [
				Promise.all( el.getAnimations( { subtree: false } ).map( ( a ) => a.finished.catch( () => null ) ) ),
				new Promise( ( resolve ) => setTimeout( resolve, 600 ) ),
			] );
			const r = el.getBoundingClientRect();
			const base = window.__aisaFocusBase.get( el );
			const now = window.__aisaFocusStyle( el );
			const cs = getComputedStyle( el );
			// A control whose own box is hidden (a styled checkbox) may show focus on its label or wrapper instead.
			let proxyChanged = false;
			const proxy = ( el.labels && el.labels[ 0 ] ) || el.parentElement;
			if ( proxy && proxy.matches( ':focus-within' ) ) {
				const pcs = getComputedStyle( proxy );
				proxyChanged = 'none' !== pcs.outlineStyle && parseFloat( pcs.outlineWidth ) > 0;
			}
			// A radio, checkbox or file input laid transparently over its label is a visually hidden
			// control like a 1px one: its focus must show on what the user sees, not on itself.
			const transparentControl = 'INPUT' === el.tagName && [ 'radio', 'checkbox', 'file' ].includes( el.type ) && parseFloat( cs.opacity ) === 0;
			return {
				desc: el.tagName.toLowerCase() + ( el.id ? '#' + el.id : '' ) + ( el.classList.length ? '.' + Array.from( el.classList ).slice( 0, 2 ).join( '.' ) : '' ) + ' "' + ( el.innerText || el.value || el.getAttribute( 'aria-label' ) || '' ).trim().slice( 0, 24 ) + '"',
				visible: r.width > 0 && r.height > 0 && ( transparentControl || el.checkVisibility( { opacityProperty: true, visibilityProperty: true } ) ),
				tiny: r.width <= 1 || r.height <= 1 || transparentControl,
				changed: ( undefined !== base && base !== now ) || ( 'none' !== cs.outlineStyle && parseFloat( cs.outlineWidth ) > 0 ) || proxyChanged,
				styles: 'unfocused [' + base + '] focused [' + now + ']',
			};
		} );
		if ( ! info ) {
			break;
		}
		stops++;
		if ( ! info.visible && ! info.tiny ) {
			invisible.push( info.desc );
		} else if ( ! info.changed && ! info.tiny ) {
			noIndicator.push( info.desc + ' ' + info.styles );
		} else if ( info.tiny ) {
			// A visually hidden control (radio, checkbox) must show focus on what the user sees.
			const shown = await page.evaluate( () => {
				const el = document.activeElement;
				const holder = ( el.labels && el.labels[ 0 ] ) || el.parentElement;
				const cs = holder ? getComputedStyle( holder ) : null;
				const inner = holder ? Array.from( holder.querySelectorAll( '*' ) ).some( ( n ) => {
					const s = getComputedStyle( n );
					return ( 'none' !== s.outlineStyle && parseFloat( s.outlineWidth ) > 0 ) || ( s.boxShadow && 'none' !== s.boxShadow );
				} ) : false;
				return !! cs && ( ( 'none' !== cs.outlineStyle && parseFloat( cs.outlineWidth ) > 0 ) || ( cs.boxShadow && 'none' !== cs.boxShadow ) || inner );
			} );
			if ( ! shown ) {
				noIndicator.push( info.desc + ' (visually hidden control, nothing around it shows focus)' );
			}
		}
		await page.keyboard.press( 'Tab' );
	}
	check( stops > 0, tag + ' keyboard reaches the plugin screen (' + stops + ' Tab stops walked)' );
	check( invisible.length === 0, tag + ' no Tab stop lands on an invisible element (' + invisible.length + ')', invisible );
	check( noIndicator.length === 0, tag + ' every Tab stop shows a focus indicator (' + noIndicator.length + ' without)', noIndicator );
}

/* ── dialogs ──────────────────────────────────────────────────────────── */

async function dialogChecks( page, apiRequests ) {
	console.log( 'CHECK dialogs: Escape and focus return' );
	await page.setViewportSize( { width: 1440, height: 960 } );

	await open( page, 'page=aisaautopilot-integrations' );
	const opener = page.locator( '[data-sp-integration-open]' ).first();
	if ( await opener.count() ) {
		const id = await opener.getAttribute( 'data-sp-integration-open' );
		await opener.focus();
		await page.keyboard.press( 'Enter' );
		const dialog = page.locator( '[data-sp-integration-dialog="' + id + '"]' );
		await dialog.waitFor( { state: 'visible', timeout: 5000 } ).catch( () => {} );
		check( await dialog.evaluate( ( d ) => d.open ), 'Integrations: Enter on the configure button opens its dialog (' + id + ')' );
		check( await dialog.evaluate( ( d ) => d.contains( document.activeElement ) ), 'Integrations: focus moves into the dialog' );
		await page.keyboard.press( 'Escape' );
		await page.waitForFunction( ( sel ) => ! document.querySelector( sel ).open, '[data-sp-integration-dialog="' + id + '"]', { timeout: 5000 } ).catch( () => {} );
		check( ! ( await dialog.evaluate( ( d ) => d.open ) ), 'Integrations: Escape closes the dialog' );
		check( await page.evaluate( ( sel ) => document.activeElement === document.querySelector( sel ), '[data-sp-integration-open="' + id + '"]' ), 'Integrations: focus returns to the configure button' );
	} else {
		check( false, 'Integrations: a configure button exists to open a dialog' );
	}

	await open( page, 'page=aisaautopilot-usage' );
	const reset = page.locator( '#sp-reset-usage' );
	if ( await reset.count() && await reset.isVisible() ) {
		const before = apiRequests.length;
		await reset.focus();
		await page.keyboard.press( 'Enter' );
		const confirm = page.locator( '[role="alertdialog"]' );
		await confirm.waitFor( { state: 'visible', timeout: 5000 } ).catch( () => {} );
		check( await confirm.count() === 1 && await confirm.isVisible(), 'Usage reset: Enter opens the shared confirm dialog (role=alertdialog)' );
		check( await confirm.evaluate( ( d ) => d.contains( document.activeElement ) ).catch( () => false ), 'Usage reset: focus moves into the confirm dialog' );
		await page.keyboard.press( 'Escape' );
		await confirm.waitFor( { state: 'hidden', timeout: 5000 } ).catch( () => {} );
		check( ! ( await confirm.isVisible().catch( () => false ) ), 'Usage reset: Escape dismisses the confirm dialog' );
		check( await page.evaluate( () => document.activeElement && 'sp-reset-usage' === document.activeElement.id ), 'Usage reset: focus returns to the reset button' );
		check( apiRequests.slice( before ).filter( ( u ) => u.includes( '/usage/reset' ) ).length === 0, 'Usage reset: dismissing sends no reset request' );
	} else {
		console.log( 'INFO Usage reset button not shown on this site; confirm dialog measured on no page' );
	}

	await open( page, 'page=aisaautopilot-handoffs' );
	const row = page.locator( '[data-sp-hoi-open], .sp-hoi-row button, .sp-hoi-row a' ).first();
	if ( await row.count() ) {
		await row.focus();
		await page.keyboard.press( 'Enter' );
		const drawer = page.locator( '.sp-hoi-drawer__panel[role="dialog"]' );
		await drawer.waitFor( { state: 'visible', timeout: 8000 } ).catch( () => {} );
		check( await drawer.isVisible().catch( () => false ), 'Handoff inbox: Enter on a request opens the drawer' );
		check( await drawer.evaluate( ( d ) => d.contains( document.activeElement ) ).catch( () => false ), 'Handoff inbox: focus moves into the drawer' );
		await page.keyboard.press( 'Escape' );
		await drawer.waitFor( { state: 'hidden', timeout: 5000 } ).catch( () => {} );
		check( ! ( await drawer.isVisible().catch( () => false ) ), 'Handoff inbox: Escape closes the drawer' );
		check( await row.evaluate( ( el ) => el === document.activeElement ).catch( () => false ), 'Handoff inbox: focus returns to the request that opened it' );
	} else {
		console.log( 'INFO Handoff inbox is empty on this site; the drawer (focus inside, Escape) is covered with fixture threads by tests/handoff_inbox_browser_matrix.cjs' );
	}
}

/* ── main ─────────────────────────────────────────────────────────────── */

( async () => {
	if ( ! username || ! password ) {
		throw new Error( 'AISAAUTOPILOT_BROWSER_USER and AISAAUTOPILOT_BROWSER_PASSWORD are required.' );
	}
	fs.mkdirSync( screenshotDir, { recursive: true } );
	const consoleErrors = [];
	const pageErrors = [];
	const failedResponses = [];
	const apiRequests = [];
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
		page.on( 'request', ( request ) => {
			if ( request.url().includes( '/wp-json/aisaautopilot/v1/' ) ) {
				apiRequests.push( request.method() + ' ' + request.url() );
			}
		} );
		page.on( 'response', ( response ) => {
			const url = response.url();
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
		await open( page, 'page=aisaautopilot-home' );
		const lang = await page.locator( 'html' ).getAttribute( 'lang' );
		check( expectRtl ? /^fa/.test( lang || '' ) : /^en/.test( lang || '' ), 'admin language is ' + ( expectRtl ? 'fa' : 'en' ) + ' (html[lang]=' + lang + ')' );

		for ( const viewport of VIEWPORTS ) {
			await page.setViewportSize( { width: viewport.width, height: viewport.height } );
			const headerEnds = {};
			for ( const [ name, query ] of ROUTES ) {
				const tag = name + ' @' + viewport.name;
				const response = await open( page, query );
				check( response.status() === 200 && await page.locator( '.sp-app' ).count() >= 1, tag + ' loads with HTTP 200 and the .sp-app shell' );
				const direction = await page.locator( '.sp-app' ).first().evaluate( ( el ) => getComputedStyle( el ).direction ).catch( () => '' );
				check( direction === ( expectRtl ? 'rtl' : 'ltr' ), tag + ' computed direction is ' + ( expectRtl ? 'rtl' : 'ltr' ) );
				const layout = await page.evaluate( layoutAudit, viewport.width );
				check( layout.docOverflow <= 1, tag + ' no horizontal page scroll (+' + layout.docOverflow + 'px)' );
				check( layout.offscreen.length === 0, tag + ' no control or text pushed outside the viewport (' + layout.offscreen.length + ')', layout.offscreen );
				check( layout.clipped.length === 0, tag + ' no text clipped without an ellipsis (' + layout.clipped.length + ')', layout.clipped );
				check( layout.truncated.length === 0, tag + ' no value cut with an ellipsis and no title to read it (' + layout.truncated.length + ')', layout.truncated );
				if ( null !== layout.titleGap ) {
					check( layout.titleGap >= -1 && layout.titleGap <= 2, tag + ' page title starts on the inline-start edge of its header row (gap ' + layout.titleGap + 'px)' );
				}
				if ( null !== layout.headerEnd ) {
					headerEnds[ name ] = layout.headerEnd;
				}
				if ( '390' === viewport.name ) {
					const small = await page.evaluate( targetAudit );
					check( small.length === 0, tag + ' every control is at least 24x24 (' + small.length + ' smaller)', small );
				}
				if ( '1440' === viewport.name ) {
					const sem = await page.evaluate( semanticsAudit );
					check( sem.unnamed.length === 0, tag + ' every visible button and link has an accessible name (' + sem.unnamed.length + ' without)', sem.unnamed );
					check( sem.unlabelled.length === 0, tag + ' every visible field has a label (' + sem.unlabelled.length + ' without)', sem.unlabelled );
					const contrast = await page.evaluate( contrastAudit );
					const low = Object.keys( contrast.low );
					check( low.length === 0, tag + ' text contrast meets WCAG AA (' + contrast.checked + ' text elements, ' + contrast.skipped + ' over a gradient or image skipped, ' + low.length + ' failing colour pairs)', low.map( ( k ) => k + ' — ' + contrast.low[ k ].join( '; ' ) ) );
					await focusAudit( page, tag );
				}
				await page.screenshot( { path: path.join( screenshotDir, name + '-' + viewport.name + '.png' ), fullPage: '390' !== viewport.name } );
			}
			const ends = Object.values( headerEnds );
			if ( ends.length > 1 ) {
				const spread = Math.max( ...ends ) - Math.min( ...ends );
				check( spread <= 2, '@' + viewport.name + ' every page header reaches the same inline-end edge (spread ' + spread + 'px)', Object.keys( headerEnds ).map( ( k ) => k + ' ' + headerEnds[ k ] + 'px' ) );
			}
		}

		console.log( 'CHECK prefers-reduced-motion' );
		const quiet = await browser.newContext( { viewport: { width: 1440, height: 960 }, reducedMotion: 'reduce', storageState: await context.storageState() } );
		const quietPage = await quiet.newPage();
		for ( const [ name, query ] of ROUTES ) {
			// Same one-retry budget as open(): the local server sometimes stalls on a single request.
			for ( let attempt = 1; attempt <= 2; attempt++ ) {
				try {
					await quietPage.goto( baseUrl + '/wp-admin/admin.php?' + query, { waitUntil: 'domcontentloaded', timeout: 60000 } );
					break;
				} catch ( error ) {
					if ( 2 === attempt ) {
						throw error;
					}
					console.log( 'RETRY ' + query + ' (reduced motion) after a local-server timeout' );
				}
			}
			await quietPage.locator( '.sp-app' ).first().waitFor( { state: 'attached', timeout: 15000 } ).catch( () => {} );
			const moving = await quietPage.evaluate( motionAudit );
			check( moving.length === 0, name + ' with prefers-reduced-motion runs no animation longer than 1ms (' + moving.length + ')', moving.map( ( m ) => m.target + ' ' + m.name + ' ' + m.duration + 'ms' ) );
		}
		await quiet.close();

		await dialogChecks( page, apiRequests );

		check( consoleErrors.length === 0, 'no console errors on any page (' + consoleErrors.length + ')', consoleErrors );
		check( pageErrors.length === 0, 'no uncaught page errors (' + pageErrors.length + ')', pageErrors );
		check( failedResponses.length === 0, 'no HTTP responses >= 400 (' + failedResponses.length + ')', failedResponses );
	} finally {
		if ( browser ) {
			await browser.close();
		}
	}
	console.log( 'COMPLETE admin a11y assertions (' + failures.length + ' failures)' );
	process.exitCode = failures.length ? 1 : 0;
} )().catch( ( error ) => {
	console.error( error.stack || error.message );
	process.exitCode = 1;
} );
