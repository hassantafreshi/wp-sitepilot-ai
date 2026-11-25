/*
 * Browser checks of the Easy Form Builder copilot screens on a real WordPress with Easy Form Builder (dev4):
 * the copilot inside EFB designs a form whose calculation needs an add-on the site lacks (the preview names the add-on
 * and its plan, shows the field used instead, offers the install), Create stores it through EFB, the editor then says
 * what EFB reported and offers Undo; and the plugin's "Easy Form Builder copilot" page lists what EFB reports about
 * this site. EFB 4.3.2: the new form is a draft the admin publishes from the card; with no form open, a SAVED form is
 * chosen, its change proposed and saved through EFB's form.update on approval; the owner's own Save warnings
 * (efb:form-saved) are explained. en_US at 1280 px, fa_IR and ar at 375 px, screenshots for each.
 *
 *   AISA_BASE=http://127.0.0.1:8090 AISA_USER=admin AISA_PASS=… AISA_WP="php wp-cli.phar --path=…" \
 *   AISA_SHOTS=/tmp/shots AISA_CHROME=/usr/bin/google-chrome node tests/efb_e2e/ui.cjs
 *
 * The AI provider is the inert mock (aisa-ai-mock-mu.php); replies are queued with ctl.php.
 */
'use strict';
require( '../test-env.cjs' ); // AISAAUTOPILOT_* defaults: the local test copy, else the owner's XAMPP

const { execSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { chromium } = require( process.env.AISAAUTOPILOT_PLAYWRIGHT_CORE || 'playwright-core' );

const BASE = ( process.env.AISA_BASE || 'http://127.0.0.1:8090' ).replace( /\/$/, '' );
const USER = process.env.AISA_USER || 'admin';
const PASS = process.env.AISA_PASS || '';
const WP = process.env.AISA_WP || 'wp';
const SHOTS = process.env.AISA_SHOTS || path.join( __dirname, 'shots' );
const CHROME = process.env.AISA_CHROME || undefined;
const CTL = path.join( __dirname, 'ctl.php' );
const failures = [];

function check( ok, name, detail ) {
	console.log( ( ok ? 'PASS ' : 'FAIL ' ) + name + ( ok || detail === undefined ? '' : ' :: ' + JSON.stringify( detail ).slice( 0, 400 ) ) );
	if ( ! ok ) { failures.push( name ); }
}
function ctl( ...args ) {
	const quoted = args.map( ( a ) => "'" + String( a ).replace( /'/g, "'\\''" ) + "'" ).join( ' ' );
	return execSync( `${ WP } eval-file ${ CTL } ${ quoted }`, { encoding: 'utf8' } ).trim().split( '\n' ).pop();
}

const CREATE_REPLY = {
	reply: 'Here is the order form.',
	action: 'create_form',
	form: {
		title: 'Catering order',
		fields: [
			{ type: 'text', label: 'Full name', required: true },
			{ type: 'email', label: 'Email', required: true },
			{ type: 'number', label: 'Guests', required: true },
			{ type: 'calc', label: 'Estimated total' },
		],
	},
};

const CLAIM_REPLY = {
	reply: 'Done: SMS code verification for the mobile number and Cloudflare Turnstile protection.',
	action: 'create_form',
	form: {
		title: 'Workshop sign-up',
		captcha: 'turnstile',
		fields: [
			{ type: 'text', label: 'Full name', required: true },
			{ type: 'mobile', label: 'Mobile', settings: { sms_verification: true } },
		],
	},
};

async function login( page ) {
	await page.goto( BASE + '/wp-login.php', { waitUntil: 'domcontentloaded' } );
	await page.fill( '#user_login', USER );
	await page.fill( '#user_pass', PASS );
	await Promise.all( [ page.waitForURL( ( u ) => ! /wp-login\.php/.test( u.toString() ), { timeout: 60000 } ), page.click( '#wp-submit' ) ] );
}

/*
 * Easy Form Builder's rating invitation (#efb-review-modal) opens on a timer on its own pages. The dock must stay usable
 * under it: with the dock open, the centre of the dock's Send button is the Send button even while the invitation is
 * shown (the real one, or a stand-in with EFB's own z-index when EFB does not ask on this visit).
 */
async function dockAboveRatingDialog( page ) {
	return page.evaluate( () => {
		let modal = document.getElementById( 'efb-review-modal' );
		let made = false;
		if ( ! modal ) {
			const css = document.createElement( 'style' );
			css.id = 'aisa-test-review-css';
			css.textContent = '#efb-review-modal{position:fixed;inset:0;z-index:160000;background:rgba(26,26,46,.55)}';
			document.head.appendChild( css );
			modal = document.createElement( 'div' );
			modal.id = 'efb-review-modal';
			modal.className = 'efb-dlg';
			document.body.appendChild( modal );
			made = true;
		}
		const wasHidden = modal.hidden;
		modal.hidden = false;
		const box = document.getElementById( 'aisaautopilot-efb-send' ).getBoundingClientRect();
		const hit = document.elementFromPoint( box.left + box.width / 2, box.top + box.height / 2 );
		const ok = !! hit && !! hit.closest( '#aisaautopilot-efb-send' );
		if ( made ) {
			modal.remove();
			document.getElementById( 'aisa-test-review-css' ).remove();
		} else {
			modal.hidden = wasHidden;
		}
		return ok;
	} );
}

/* The test answers EFB's rating invitation with "later" (its own close control) when EFB shows it. */
async function dismissRatingDialog( page ) {
	await page.evaluate( () => {
		const modal = document.getElementById( 'efb-review-modal' );
		const later = modal && ! modal.hidden ? modal.querySelector( '[data-efb-review-close]' ) : null;
		if ( later ) { later.click(); }
	} );
}

async function noHorizontalScroll( page ) {
	return page.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1 );
}

async function run( locale, width, height ) {
	ctl( 'locale', locale );
	const browser = await chromium.launch( CHROME ? { executablePath: CHROME } : {} );
	// WordPress 7.1's wp-admin uses cross-document view transitions; a page that navigates again while the next one is
	// being revealed (Undo's redirect, then the forms list) throws "Transition was skipped" in Chromium (WordPress core,
	// not this plugin). The run uses reduced motion, as many administrators do, so that core animation is off.
	const context = await browser.newContext( { viewport: { width, height }, deviceScaleFactor: 1, reducedMotion: 'reduce' } );
	const page = await context.newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( String( e && e.message || e ) ) );
	const tag = `${ locale }-${ width }`;
	const rtl = locale !== 'en_US';
	try {
		await login( page );

		// 1. The plugin's EFB screen: what Easy Form Builder reports about this site.
		await page.goto( BASE + '/wp-admin/admin.php?page=aisaautopilot-efb', { waitUntil: 'networkidle', timeout: 90000 } );
		const site = page.locator( '[data-sp-efb-site="registry"]' );
		check( await site.count() === 1, `${ tag }: EFB copilot page shows "On this site" from EFB's registry` );
		const calcRow = page.locator( '[data-sp-efb-site-row="AdnCLC"] .sp-efbx-tag' );
		check( ( await calcRow.count() ) === 1, `${ tag }: Numbers & Calculations is listed with its state` );
		check( await page.locator( '[data-sp-efb-site-row="form_package"]' ).count() === 1, `${ tag }: the form package version is shown` );
		// QA5-006 (owner decision): the customer portal is out of scope; the page has no portal row and never names it.
		check( 0 === await page.locator( '[data-sp-efb-site-row="portal"]' ).count() && ! /portal/i.test( await page.locator( '#wpbody-content' ).innerText() ), `${ tag }: the EFB copilot page never mentions the customer portal` );
		check( await noHorizontalScroll( page ), `${ tag }: EFB copilot page has no horizontal scroll` );
		if ( rtl ) { check( 'rtl' === await page.evaluate( () => document.documentElement.dir ), `${ tag }: page is right to left` ); }
		await page.screenshot( { path: path.join( SHOTS, `efb-page-${ tag }.png` ), fullPage: true } );

		// 2. The copilot inside EFB: a calculation the site cannot render becomes a Number field, named in the preview.
		ctl( 'queue', JSON.stringify( CREATE_REPLY ) );
		await page.goto( BASE + '/wp-admin/admin.php?page=Emsfb_create', { waitUntil: 'networkidle', timeout: 90000 } );
		await page.waitForSelector( '#aisaautopilot-efb-launch', { timeout: 60000 } );
		await dismissRatingDialog( page );
		await page.evaluate( () => document.getElementById( 'aisaautopilot-efb-launch' ).click() );
		await page.waitForSelector( '#aisaautopilot-efb-chat:not(.is-closed)', { timeout: 15000 } );
		await page.waitForTimeout( 400 );
		check( await dockAboveRatingDialog( page ), `${ tag }: the open dock stays above EFB's rating invitation` );
		await page.fill( '#aisaautopilot-efb-input', 'A catering order form with an estimated total of guests × 20' );
		await page.click( '#aisaautopilot-efb-send' );
		await page.waitForSelector( '[data-sp-efb-state="preview"]', { timeout: 60000 } );
		const addonList = page.locator( '[data-sp-efb-addon-required]' );
		check( 1 === await addonList.count() && 'AdnCLC' === await addonList.getAttribute( 'data-sp-efb-addon-required' ), `${ tag }: the preview lists the calculation that needs Numbers & Calculations` );
		check( 0 === await page.locator( '[data-sp-efb-field="calc"]' ).count() && 2 === await page.locator( '[data-sp-efb-field="number"]' ).count(), `${ tag }: the preview shows a Number field, never a calc field` );
		check( 1 === await page.locator( '[data-sp-efb-install="AdnCLC"]' ).count(), `${ tag }: the install of Numbers & Calculations is offered` );
		check( await noHorizontalScroll( page ), `${ tag }: copilot preview has no horizontal scroll` );
		const chat = page.locator( '#aisaautopilot-efb-chat' );
		// The preview card from its title: the fields, then the add-on list.
		await page.locator( '[data-sp-efb-state="preview"]' ).evaluate( ( el ) => el.scrollIntoView( { block: 'start' } ) );
		await page.waitForTimeout( 300 );
		await page.screenshot( { path: path.join( SHOTS, `efb-copilot-preview-${ tag }.png` ), fullPage: false } );
		await chat.screenshot( { path: path.join( SHOTS, `efb-copilot-preview-panel-${ tag }.png` ) } );

		// 3. Create: EFB stores it (form package); the editor opens with what EFB reported and Undo.
		await page.click( '[data-sp-efb-create="1"]' );
		await page.waitForURL( /state=edit-form&id=\d+/, { timeout: 60000 } );
		const formId = Number( /id=(\d+)/.exec( page.url() )[ 1 ] );
		const stored = JSON.parse( ctl( 'form', formId ) );
		const types = ( stored.rows || [] ).map( ( r ) => r && r.type );
		const email = ( stored.rows || [] ).find( ( r ) => r && r.type === 'email' ) || {};
		check( types.includes( 'number' ) && ! types.includes( 'calc' ), `${ tag }: the stored EFB form has the Number field and no calc`, types );
		check( stored.rows && stored.rows[ 0 ].email_to === email.id_ && String( email.noti ) === '1', `${ tag }: the stored form confirms to the visitor at the first Email field`, stored.rows && stored.rows[ 0 ] );
		await page.waitForSelector( '[data-sp-efb-state="created"]', { timeout: 30000 } );
		check( 1 === await page.locator( `[data-sp-efb-undo="${ formId }"]` ).count(), `${ tag }: the editor offers Undo for the new form` );
		// EFB 4.3.2: the new form is a draft; one card says so and offers Publish (no second draft card).
		check( 'draft' === JSON.parse( ctl( 'status', formId ) ).status && 1 === await page.locator( '[data-sp-efb-state="created"] [data-sp-efb-note="draft"]' ).count() && 1 === await page.locator( `[data-sp-efb-publish="${ formId }"]` ).count() && 0 === await page.locator( '[data-sp-efb-state="draft"]' ).count(), `${ tag }: the new form is a draft, the card offers Publish once` );
		await page.waitForTimeout( 800 );
		await page.screenshot( { path: path.join( SHOTS, `efb-copilot-created-${ tag }.png` ), fullPage: false } );
		// QA5-001: Undo deletes for good, so it asks first, in the card; "Keep it" leaves the form alone.
		await page.click( `[data-sp-efb-undo="${ formId }"]` );
		await page.waitForSelector( `[data-sp-efb-undo-ask="${ formId }"]`, { timeout: 10000 } );
		check( 1 === await page.locator( `[data-sp-efb-undo-confirm="${ formId }"]` ).count() && null !== JSON.parse( ctl( 'form', formId ) ).rows, `${ tag }: Undo asks before deleting (nothing deleted yet)` );
		await page.locator( `[data-sp-efb-undo-ask="${ formId }"]` ).evaluate( ( el ) => el.scrollIntoView( { block: 'center' } ) );
		await page.screenshot( { path: path.join( SHOTS, `efb-copilot-undo-confirm-${ tag }.png` ), fullPage: false } );
		await page.click( `[data-sp-efb-undo-keep="${ formId }"]` );
		check( 0 === await page.locator( `[data-sp-efb-undo-ask="${ formId }"]` ).count() && null !== JSON.parse( ctl( 'form', formId ) ).rows, `${ tag }: "Keep it" keeps the form` );
		// QA5-015: after Publish the card says only that it is published: no draft line, no Undo.
		await page.click( `[data-sp-efb-publish="${ formId }"]` );
		await page.waitForFunction( ( id ) => ! document.querySelector( `[data-sp-efb-publish="${ id }"]` ), formId, { timeout: 20000 } );
		check( 'published' === JSON.parse( ctl( 'status', formId ) ).status, `${ tag }: Publish published the form through EFB` );
		const createdCard = page.locator( '[data-sp-efb-state="created"]' );
		check( 0 === await page.locator( `[data-sp-efb-undo="${ formId }"]` ).count() && 0 === await createdCard.locator( '[data-sp-efb-note="draft"], [data-sp-efb-note="undo"], .aisa-ai-card__foot' ).count() && '1' === await createdCard.getAttribute( 'data-sp-efb-published' ), `${ tag }: after Publish the card drops the draft line, Undo and what Undo does` );
		await createdCard.evaluate( ( el ) => el.scrollIntoView( { block: 'center' } ) );
		await page.screenshot( { path: path.join( SHOTS, `efb-copilot-published-${ tag }.png` ), fullPage: false } );

		// The owner's own Save: EFB passes its warnings with efb:form-saved; the copilot explains them.
		await page.evaluate( () => document.dispatchEvent( new CustomEvent( 'efb:form-saved', { detail: { id: 1, created: false, warnings: [ { code: 'addon_keys_kept', addon: 'AdnTSG', m: 'Settings of Typed Signature are kept until the add-on is installed.' }, { code: 'pay_x', level: 'info', m: 'info' } ] } } ) ) );
		await page.waitForSelector( '[data-sp-efb-state="save-notes"]', { timeout: 20000 } );
		check( 1 === await page.locator( '[data-sp-efb-state="save-notes"] [data-sp-efb-note="save"]' ).count(), `${ tag }: the owner's Save warnings are explained (the info line left out)` );
		await page.locator( '[data-sp-efb-state="save-notes"]' ).evaluate( ( el ) => el.scrollIntoView( { block: 'center' } ) );
		await page.screenshot( { path: path.join( SHOTS, `efb-copilot-save-notes-${ tag }.png` ), fullPage: false } );
		ctl( 'delete', formId );

		// A second new form, still the untouched draft: Undo, confirmed, deletes it through EFB and opens the forms list.
		// QA5-003: its reply promised an SMS code check and Turnstile, which the copilot never sets on a new form: the
		// reply is replaced and both are named.
		ctl( 'queue', JSON.stringify( CLAIM_REPLY ) );
		await page.goto( BASE + '/wp-admin/admin.php?page=Emsfb_create', { waitUntil: 'networkidle', timeout: 90000 } );
		await page.waitForSelector( '#aisaautopilot-efb-launch', { timeout: 60000 } );
		await dismissRatingDialog( page );
		await page.evaluate( () => document.getElementById( 'aisaautopilot-efb-launch' ).click() );
		await page.waitForSelector( '#aisaautopilot-efb-chat:not(.is-closed)', { timeout: 15000 } );
		await page.fill( '#aisaautopilot-efb-input', 'A catering order form with an estimated total of guests × 20' );
		await page.click( '#aisaautopilot-efb-send' );
		await page.waitForSelector( '[data-sp-efb-create="1"]', { timeout: 60000 } );
		const claimText = await page.locator( '[data-sp-efb-state="preview"]' ).last().evaluate( ( el ) => ( el.closest( '.aisaautopilot-efb-msg--ai' ) || el.parentElement ).innerText );
		check( ! claimText.includes( CLAIM_REPLY.reply ) && /one-time code sent by SMS/.test( claimText ) && /CAPTCHA service/.test( claimText ), `${ tag }: a new form's reply never promises what was not built (SMS code, CAPTCHA named)`, claimText.slice( 0, 400 ) );
		await page.locator( '[data-sp-efb-state="preview"]' ).last().evaluate( ( el ) => el.scrollIntoView( { block: 'end' } ) );
		await page.waitForTimeout( 300 );
		await page.screenshot( { path: path.join( SHOTS, `efb-copilot-claims-${ tag }.png` ), fullPage: false } );
		await page.click( '[data-sp-efb-create="1"]' );
		await page.waitForURL( /state=edit-form&id=\d+/, { timeout: 60000 } );
		const undoId = Number( /id=(\d+)/.exec( page.url() )[ 1 ] );
		await page.waitForSelector( `[data-sp-efb-undo="${ undoId }"]`, { timeout: 30000 } );
		await page.click( `[data-sp-efb-undo="${ undoId }"]` );
		await page.click( `[data-sp-efb-undo-confirm="${ undoId }"]` );
		// Undo answers, then leaves the editor (page=Emsfb&state=edit-form&id=…) for the forms list.
		await page.waitForURL( ( u ) => /page=Emsfb(&|$)/.test( u.toString() ) && ! /state=edit-form/.test( u.toString() ), { timeout: 30000 } );
		const gone = JSON.parse( ctl( 'form', undoId ) );
		check( null === gone.rows, `${ tag }: Undo, confirmed, removed the untouched draft through EFB`, gone );

		// 4. EFB 4.3.2: no form open (the forms list): a saved form is chosen, its change proposed, then saved on approval.
		const saved = JSON.parse( ctl( 'saved-form', `Saved ${ tag }` ) ).form_id;
		try {
			// Undo's redirect may still be loading the forms list: let it settle, and retry a goto it aborts.
			await page.waitForLoadState( 'networkidle', { timeout: 60000 } ).catch( () => {} );
			for ( let i = 0; ; i++ ) {
				try {
					await page.goto( BASE + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'networkidle', timeout: 90000 } );
					break;
				} catch ( e ) {
					if ( i >= 2 || ! /ERR_ABORTED/.test( String( e ) ) ) throw e;
					await page.waitForTimeout( 1500 );
				}
			}
			await page.waitForSelector( '#aisaautopilot-efb-launch', { timeout: 60000 } );
			await page.evaluate( () => document.getElementById( 'aisaautopilot-efb-launch' ).click() );
			await page.waitForSelector( '[data-sp-efb-chip="saved-form"]', { timeout: 20000 } );
			await page.click( '[data-sp-efb-chip="saved-form"]' );
			await page.waitForSelector( `[data-sp-efb-form="${ saved }"]`, { timeout: 20000 } );
			await page.click( `[data-sp-efb-form="${ saved }"]` );
			ctl( 'queue', JSON.stringify( { reply: 'Done.', action: 'customize', operations: [ { op: 'set_field', target: 'Email', prop: 'required', value: false }, { op: 'add_field', field: { type: 'tel', label: 'Phone' }, after: 'Email' } ] } ) );
			await page.fill( '#aisaautopilot-efb-input', 'Make Email optional and add a phone field after it' );
			await page.click( '#aisaautopilot-efb-send' );
			await page.waitForSelector( '[data-sp-efb-state="update-preview"]', { timeout: 60000 } );
			check( 2 === await page.locator( '[data-sp-efb-state="update-preview"] [data-sp-efb-note="change"]' ).count(), `${ tag }: the change of the saved form is proposed in plain words` );
			check( ! JSON.parse( ctl( 'form', saved ) ).rows.some( ( r ) => r && r.type === 'tel' ), `${ tag }: nothing is saved before the approval` );
			check( await noHorizontalScroll( page ), `${ tag }: the proposal has no horizontal scroll` );
			await page.locator( '[data-sp-efb-state="update-preview"]' ).evaluate( ( el ) => el.scrollIntoView( { block: 'start' } ) );
			await page.waitForTimeout( 300 );
			await page.screenshot( { path: path.join( SHOTS, `efb-copilot-update-preview-${ tag }.png` ), fullPage: false } );
			await page.click( '[data-sp-efb-update="1"]' );
			await page.waitForSelector( '[data-sp-efb-state="update-saved"]', { timeout: 60000 } );
			const rows = JSON.parse( ctl( 'form', saved ) ).rows.filter( ( r ) => r && r.type !== 'option' ).map( ( r ) => r.type );
			check( rows.indexOf( 'tel' ) === rows.indexOf( 'email' ) + 1, `${ tag }: approved: EFB saved the Phone field right after Email`, rows );
			await page.locator( '[data-sp-efb-state="update-saved"]' ).evaluate( ( el ) => el.scrollIntoView( { block: 'center' } ) );
			await page.waitForTimeout( 300 );
			await page.screenshot( { path: path.join( SHOTS, `efb-copilot-update-saved-${ tag }.png` ), fullPage: false } );
		} finally {
			ctl( 'delete', saved );
		}
		check( 0 === errors.length, `${ tag }: no JavaScript errors`, errors );
	} catch ( e ) {
		check( false, `${ tag }: run`, String( e && e.stack || e ) );
		try { await page.screenshot( { path: path.join( SHOTS, `failure-${ tag }.png` ), fullPage: true } ); } catch ( x ) {}
	} finally {
		await browser.close();
		// A step that failed never asked for the replies queued after it: they are dropped here, so the next locale
		// starts with an empty queue. A run that passed used every reply it queued.
		const dropped = JSON.parse( ctl( 'clear-queue' ) ).dropped;
		if ( ! failures.some( ( name ) => name.startsWith( tag + ':' ) ) ) {
			check( 0 === dropped, `${ tag }: no queued AI reply is left behind`, dropped );
		}
	}
}

( async () => {
	fs.mkdirSync( SHOTS, { recursive: true } );
	ctl( 'setup' );
	ctl( 'plan', 3 );
	ctl( 'addon', 'AdnCLC', 0 );
	try {
		await run( 'en_US', 1280, 900 );
		await run( 'fa_IR', 375, 812 );
		await run( 'ar', 375, 812 );
	} finally {
		ctl( 'locale', 'en_US' );
		ctl( 'addon', 'AdnCLC', 1 );
	}
	console.log( failures.length ? `\n${ failures.length } failed` : '\nall passed' );
	process.exit( failures.length ? 1 : 0 );
} )();
