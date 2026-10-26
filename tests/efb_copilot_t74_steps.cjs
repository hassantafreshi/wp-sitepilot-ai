/* T74 browser steps for the EFB Copilot: add, rename, logic, explain, review/Apply and Undo.
 * Shared by the real-EFB check (efb_copilot_browser_check.cjs, XAMPP) and the offline stub
 * check (efb_copilot_t74_offline_check.cjs). Tests never call a real provider: each scripted
 * message carries only the model's JSON decision, and efb_copilot_offline_server.php runs the
 * real EfbCopilotController::chat() on it, so the browser receives exactly what the server
 * would send. */
'use strict';

const { execFileSync } = require( 'node:child_process' );
const path = require( 'node:path' );

const SERVER = path.join( __dirname, 'efb_copilot_offline_server.php' );

const MESSAGES = {
	add: 'T74: add a Customer type dropdown with Personal and Business, and a Company field',
	rename: 'T74: rename the Business option to Business account',
	show: 'T74: show Company only when Customer type is Business account',
	hide: 'T74: hide Company when Customer type is Business account',
	explain: 'T74: what does this form logic do?',
	review: 'T74: review this form',
};

// The model's half of each exchange. Field and option names are resolved by the server
// against the live form the browser sends, exactly as for a real reply.
const SCRIPTS = {
	[ MESSAGES.add ]: { action: 'customize', reply: 'Adding both fields.', operations: [
		{ op: 'add_field', field: { type: 'select', label: 'Customer type', options: [ 'Personal', 'Business' ], required: true } },
		{ op: 'add_field', field: { type: 'text', label: 'Company' } },
	] },
	[ MESSAGES.rename ]: { action: 'customize', reply: 'Renaming the option.', operations: [
		{ op: 'rename_option', target: 'Customer type', from: 'Business', value: 'Business account' },
	] },
	[ MESSAGES.show ]: { action: 'customize', reply: 'Adding the rule.', operations: [
		{ op: 'add_logic_rule', rule: { when: [ { field: 'Customer type', compare: 'is', value: 'Business account' } ], then: [ { action: 'show', target: 'Company' } ] } },
	] },
	[ MESSAGES.hide ]: { action: 'customize', reply: 'Adding the rule.', operations: [
		{ op: 'add_logic_rule', rule: { when: [ { field: 'Customer type', compare: 'is', value: 'Business account' } ], then: [ { action: 'hide', target: 'Company' } ] } },
	] },
	[ MESSAGES.explain ]: { action: 'explain_logic', reply: '' },
	[ MESSAGES.review ]: { action: 'review', reply: '' },
};

function phpBinary() {
	return process.env.AISAAUTOPILOT_PHP || ( 'win32' === process.platform ? 'C:\\xampp\\php\\php.exe' : 'php' );
}

/** The server's real reply to one /efb/chat body, with the model's decision scripted. */
function serverReply( request, ai, options ) {
	const input = JSON.stringify( {
		mode: 'chat',
		request,
		ai,
		efb_plan: options.efbPlan,
		privacy_url: options.privacyUrl || '',
		privacy_title: options.privacyTitle || '',
	} );
	return JSON.parse( execFileSync( phpBinary(), [ SERVER ], { input, encoding: 'utf8', windowsHide: true } ) );
}

/**
 * Answers a scripted T74 message through the offline server. Returns false for any other
 * message so the caller's own route handler keeps it.
 */
async function fulfillScripted( route, options ) {
	let body = {};
	try { body = route.request().postDataJSON() || {}; } catch ( error ) { body = {}; }
	const ai = SCRIPTS[ body.message ];
	if ( ! ai ) { return false; }
	const reply = serverReply( body, ai, options );
	if ( options.onReply ) { options.onReply( body, reply ); }
	await route.fulfill( { status: reply.status, contentType: 'application/json', body: JSON.stringify( reply.body ) } );
	return true;
}

async function runT74( page, ctx ) {
	const check = ctx.check;
	const chat = page.locator( '#aisaautopilot-efb-chat' );
	if ( await chat.isHidden() ) {
		await page.locator( '#aisaautopilot-efb-launch' ).click();
		await chat.waitFor( { state: 'visible' } );
	}
	const composer = page.locator( '#aisaautopilot-efb-input' );
	// Each reply is pinned by position: a later message must never stand in for it.
	const aiMessages = page.locator( '#aisaautopilot-efb-messages .aisaautopilot-efb-msg--ai' );
	const send = async ( text ) => {
		const before = await aiMessages.count();
		await composer.fill( text );
		await composer.press( 'Enter' );
		await page.waitForFunction( ( count ) => document.querySelectorAll( '#aisaautopilot-efb-messages .aisaautopilot-efb-msg--ai' ).length > count && ! document.getElementById( 'aisaautopilot-efb-typing' ), before, { timeout: 20000 } );
		return aiMessages.nth( before );
	};
	// EFB redraws asynchronously (editFormEfb waits a moment before it draws), so canvas
	// checks wait for the wrapper instead of reading the DOM once.
	const canvasHas = ( id, present ) => page.locator( '.efbField[data-id="' + id + '-id"]' ).first()
		.waitFor( { state: present ? 'visible' : 'detached', timeout: 20000 } ).then( () => true ).catch( () => false );
	const state = () => page.evaluate( () => {
		/* global valj_efb */
		const fields = valj_efb.filter( ( it ) => it && it.type && ! [ 'form', 'step', 'option', 'r_matrix' ].includes( it.type ) );
		const byName = ( name ) => fields.find( ( it ) => String( it.name || '' ) === name ) || null;
		const optionsOf = ( field ) => ( field ? valj_efb.filter( ( it ) => it && 'option' === it.type && it.parent === field.id_ ).map( ( it ) => ( { id: String( it.id_ ), value: String( it.value || '' ) } ) ) : [] );
		const customer = byName( 'Customer type' );
		const company = byName( 'Company' );
		const consent = fields.find( ( it ) => 'trmCheckbox' === it.type ) || null;
		return {
			ids: fields.map( ( it ) => String( it.id_ ) ),
			customer: customer ? { id: String( customer.id_ ), type: customer.type, options: optionsOf( customer ) } : null,
			company: company ? { id: String( company.id_ ), type: company.type } : null,
			consent: consent ? { id: String( consent.id_ ), required: String( consent.required ), options: optionsOf( consent ) } : null,
			logic: valj_efb[ 0 ].logic,
			rules: Array.isArray( valj_efb[ 0 ].logic_rules ) ? JSON.parse( JSON.stringify( valj_efb[ 0 ].logic_rules ) ) : [],
		};
	} );
	const env = await page.evaluate( () => ( {
		/* global efb_var */
		logicAddon: typeof efb_var !== 'undefined' && !! efb_var && !! efb_var.addons && Number( efb_var.addons.AdnSMF ) >= 1,
		tier: ( window.AISAAutoPilotEFB && window.AISAAutoPilotEFB.license && window.AISAAutoPilotEFB.license.tier ) || 'free',
	} ) );
	const label = ctx.label ? ctx.label + ': ' : '';
	console.log( 'T74_ENV ' + JSON.stringify( env ) );

	// Nothing in this flow may save the form; only EFB's own Save button does that.
	const savePosts = [];
	const trackPosts = ( item ) => {
		if ( 'POST' === item.method() && ! item.url().includes( '/aisaautopilot/v1/' ) && ! ( item.postData() || '' ).includes( 'action=heartbeat' ) ) { savePosts.push( item.url() ); }
	};
	page.on( 'request', trackPosts );

	const original = await state();

	// 1. Two fields in one change, built from the server's own EFB templates.
	const added = await send( MESSAGES.add );
	let now = await state();
	check( !! now.customer && 'select' === now.customer.type && 2 === now.customer.options.length && !! now.company && 'text' === now.company.type, label + 'add_field puts the dropdown, its two options and the text field into valj_efb' );
	check( 2 === await added.locator( '[data-sp-efb-note="applied"]' ).count(), label + 'both added fields are reported as applied' );
	check( 1 === await added.locator( '[data-sp-efb-undo]' ).count(), label + 'the change offers its own Undo button' );
	check( !! now.customer && await canvasHas( now.customer.id, true ), label + 'the EFB canvas redraws with the new dropdown' );
	const addUndo = added.locator( '[data-sp-efb-undo]' );
	const businessId = now.customer ? ( now.customer.options.find( ( o ) => 'Business' === o.value ) || {} ).id : '';

	// 2. Renaming keeps the option's id_, so answers and logic that point at it survive.
	await send( MESSAGES.rename );
	now = await state();
	const renamed = now.customer ? now.customer.options.find( ( o ) => 'Business account' === o.value ) : null;
	check( !! renamed && renamed.id === businessId && ! now.customer.options.some( ( o ) => 'Business' === o.value ), label + 'rename_option changes the text and keeps the option id_' );

	// 3. Conditional logic: applied only where EFB's add-on and plan allow it.
	const logicAllowed = env.logicAddon && 'free' !== env.tier;
	const ruled = await send( MESSAGES.show );
	now = await state();
	if ( logicAllowed ) {
		const rule = now.rules[ now.rules.length - 1 ] || {};
		const item = ( ( rule.conditions || {} ).items || [] )[ 0 ] || {};
		const action = ( rule.actions || [] )[ 0 ] || {};
		console.log( 'T74_RULE ' + JSON.stringify( rule ) );
		check( original.rules.length + 1 === now.rules.length && 'field' === rule.scope && true === rule.enabled, label + 'add_logic_rule stores one enabled field-scope rule in valj_efb[0].logic_rules' );
		check( !! now.customer && item.field_id === now.customer.id && 'is' === item.compare && item.value === businessId, label + 'the condition points at the dropdown and stores the option id_, as EFB\'s builder does' );
		check( !! now.company && 'show_field' === action.type && action.target === now.company.id, label + 'the action shows the Company field' );
		check( true === now.logic, label + 'valj_efb[0].logic is raised, as the add-on\'s saveRules() does' );
		check( 1 === await ruled.locator( '[data-sp-efb-note="logic"]' ).count(), label + 'the reply asks the admin to review the rule and test it in Preview' );

		// 4. A rule that fights the first is applied, but never silently; its Undo removes it.
		const fought = await send( MESSAGES.hide );
		const conflictNote = ( await fought.locator( '[data-sp-efb-note="server"]' ).allInnerTexts() ).join( ' ' );
		console.log( 'T74_CONFLICT ' + JSON.stringify( conflictNote.slice( 0, 200 ) ) );
		check( conflictNote.includes( 'Company' ) && original.rules.length + 2 === ( await state() ).rules.length, label + 'a contradicting rule is applied with a conflict warning that names the field' );
		await fought.locator( '[data-sp-efb-undo]' ).click();
		check( original.rules.length + 1 === ( await state() ).rules.length, label + 'Undo removes exactly the contradicting rule' );

		// 5. Explaining reads the rules and changes nothing.
		const before = JSON.stringify( await state() );
		const explained = await send( MESSAGES.explain );
		const lines = await explained.locator( '.aisaautopilot-efb-finding' ).allInnerTexts();
		console.log( 'T74_EXPLAIN ' + JSON.stringify( lines ) );
		check( lines.length >= 1 && lines.some( ( line ) => line.includes( 'Company' ) ), label + 'explain_logic lists the rule in plain words' );
		check( before === JSON.stringify( await state() ), label + 'explain_logic leaves the canvas untouched' );
	} else {
		check( original.rules.length === now.rules.length && 0 === await ruled.locator( '[data-sp-efb-note="applied"]' ).count(), label + 'without the add-on or on the Free plan no logic rule is written' );
		const refusal = ( await ruled.innerText() ).trim();
		console.log( 'T74_LOGIC_REFUSAL ' + JSON.stringify( refusal.slice( 0, 200 ) ) );
		check( refusal.length > 0 && 0 === await ruled.locator( '[data-sp-efb-undo]' ).count(), label + 'the refusal is explained and offers nothing to undo' );
		// The model writes its reply before the server checks anything; repeating it here
		// would announce a rule that was never added.
		check( ! refusal.includes( SCRIPTS[ MESSAGES.show ].reply ), label + 'a fully refused change does not repeat the model\'s claim that it was made' );
	}

	// 6. Review: findings first, nothing changes until Apply.
	const beforeReview = JSON.stringify( await state() );
	const reviewed = await send( MESSAGES.review );
	const findings = await reviewed.locator( '.aisaautopilot-efb-finding' ).allInnerTexts();
	console.log( 'T74_REVIEW ' + JSON.stringify( findings.map( ( text ) => text.slice( 0, 80 ) ) ) );
	check( findings.some( ( text ) => text.includes( 'consent' ) ), label + 'review flags personal data without consent' );
	check( beforeReview === JSON.stringify( await state() ), label + 'review changes nothing on its own' );
	const apply = reviewed.locator( '.aisaautopilot-efb-finding button' );
	if ( 'free' === env.tier ) {
		// EFB reserves the terms checkbox for Free Plus and Pro, so the server offers no fix.
		check( 0 === await apply.count(), label + 'on the Free plan the consent finding has no Apply button' );
	} else {
		check( 1 === await apply.count(), label + 'the consent finding offers one Apply button' );
		const appliedIndex = await aiMessages.count();
		await apply.first().click();
		await page.waitForFunction( () => valj_efb.some( ( it ) => it && 'trmCheckbox' === it.type ), undefined, { timeout: 10000 } ).catch( () => {} );
		now = await state();
		console.log( 'T74_CONSENT ' + JSON.stringify( now.consent ) );
		check( !! now.consent && '1' === now.consent.required && 1 === now.consent.options.length, label + 'Apply adds a required consent checkbox with one consent sentence' );
		check( !! now.consent && ( ! ctx.privacyUrl || now.consent.options[ 0 ].value.includes( ctx.privacyUrl ) ), label + 'the consent sentence links the site privacy page and nothing else' );
		const applied = aiMessages.nth( appliedIndex );
		check( 1 === await applied.locator( '[data-sp-efb-undo]' ).count(), label + 'the applied suggestion has its own Undo' );
		await applied.locator( '[data-sp-efb-undo]' ).click();
		check( null === ( await state() ).consent, label + 'Undo removes the consent checkbox again' );
	}

	// 7. Undo on the first change restores the canvas exactly as it was before it.
	await addUndo.click();
	now = await state();
	check( JSON.stringify( now.ids ) === JSON.stringify( original.ids ) && JSON.stringify( now.rules ) === JSON.stringify( original.rules ), label + 'Undo on the first change restores the original fields and rules' );
	check( ! original.ids.includes( 'customer_type' ) ? await canvasHas( 'customer_type', false ) : true, label + 'the EFB canvas redraws without the undone dropdown' );

	page.off( 'request', trackPosts );
	console.log( 'T74_SAVE_POSTS ' + JSON.stringify( savePosts ) );
	check( 0 === savePosts.length, label + 'nothing in the T74 flow saves the form' );
}

module.exports = { MESSAGES, SCRIPTS, serverReply, fulfillScripted, runT74 };
