/* global AISAAutoPilotAI */
/**
 * WP-02 / WP-03 / WP-04 — compose, review, result.
 * Markup mirrors "UI wireframe/AI Site Assistant AutoPilot.dc.html" one-for-one.
 */
( function () {
	'use strict';

	var cfg = window.AISAAutoPilotAI || {};
	var boot = readBoot();
	var T = boot.i18n || {};

	var actionId = null;
	var preview = null;
	var expiresAt = null;
	var expiryTimer = null;
	var genTimer = null;

	var recorder = null;
	var recorderStream = null;
	var chunks = [];
	var recordingTimer = null;

	// ── tiny DOM helpers ────────────────────────────────────────

	function readBoot() {
		var node = document.getElementById( 'sp-copilot-boot' );
		if ( ! node ) { return {}; }
		try { return JSON.parse( node.textContent ) || {}; } catch ( e ) { return {}; }
	}

	function t( key, fallback ) { return T[ key ] || fallback || key; }

	function sprintf( template, value ) { return String( template ).replace( /%[sd]/, value ); }

	function byId( id ) { return document.getElementById( id ); }

	function h( tag, attrs, children ) {
		var node = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( key ) {
			var value = attrs[ key ];
			if ( value === null || value === undefined || value === false ) { return; }
			if ( key === 'class' ) { node.className = value; }
			else if ( key === 'text' ) { node.textContent = value; }
			else if ( key === 'html' ) { node.innerHTML = value; }
			else if ( key === 'onClick' ) { node.addEventListener( 'click', value ); }
			else if ( key === 'style' ) { node.setAttribute( 'style', value ); }
			else { node.setAttribute( key, value === true ? '' : value ); }
		} );
		( children || [] ).forEach( function ( child ) {
			if ( child === null || child === undefined || child === false ) { return; }
			node.appendChild( typeof child === 'string' ? document.createTextNode( child ) : child );
		} );
		return node;
	}

	/** Inline SVG from a raw path string, matching the wireframe's icon set. */
	function svg( paths, size, stroke, width ) {
		var node = document.createElementNS( 'http://www.w3.org/2000/svg', 'svg' );
		node.setAttribute( 'width', size || 16 );
		node.setAttribute( 'height', size || 16 );
		node.setAttribute( 'viewBox', '0 0 24 24' );
		node.setAttribute( 'fill', 'none' );
		node.setAttribute( 'stroke', stroke || 'currentColor' );
		node.setAttribute( 'stroke-width', width || 1.7 );
		node.setAttribute( 'aria-hidden', 'true' );
		node.innerHTML = paths;
		return node;
	}

	var ICON = {
		shield: '<path d="M12 3l7.5 3v6c0 4.2-3.1 7.8-7.5 9-4.4-1.2-7.5-4.8-7.5-9V6z" stroke-linejoin="round"/>',
		clock: '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 1.8" stroke-linecap="round"/>',
		check: '<path d="M5 12.5l4.5 4.5L19 7" stroke-linecap="round" stroke-linejoin="round"/>',
		copy: '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a1 1 0 011-1h9"/>',
		warn: '<path d="M12 4.5l8.5 15h-17z" stroke-linejoin="round"/><path d="M12 10v4M12 17h.01" stroke-linecap="round"/>'
	};

	// ── shared chrome ───────────────────────────────────────────

	function showStage( name ) {
		[ 'compose', 'review', 'result' ].forEach( function ( stage ) {
			var screen = byId( 'aisaautopilot-' + stage + '-screen' );
			if ( ! screen ) { return; }
			screen.hidden = stage !== name;
		} );
		window.scrollTo( { top: 0, behavior: 'smooth' } );
	}

	function toast( message ) {
		var node = byId( 'sp-toast' );
		if ( ! node ) { return; }
		node.textContent = message;
		node.hidden = false;
		window.clearTimeout( node._t );
		node._t = window.setTimeout( function () { node.hidden = true; }, 2600 );
	}

	function busy( on, label ) {
		var node = byId( 'sp-busy' );
		if ( ! node ) { return; }
		if ( label ) { byId( 'sp-busy-label' ).textContent = label; }
		node.hidden = ! on;
	}

	function setStatus( message ) {
		var node = byId( 'aisaautopilot-status' );
		if ( node ) { node.textContent = message; }
	}

	function request( path, body ) {
		return fetch( cfg.restUrl + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( body )
		} ).then( responseData );
	}

	function responseData( response ) {
		return response.json().catch( function () { return {}; } ).then( function ( data ) {
			return { ok: response.ok, data: data };
		} );
	}

	function pill( label, tone, dot ) {
		return h( 'span', { class: 'sp-pill sp-pill--' + tone }, [ dot === false ? null : h( 'span', { class: 'sp-dot' } ), label ] );
	}

	function kvRow( key, value, valueNode ) {
		return h( 'div', { class: 'sp-kv__row' }, [
			h( 'span', { class: 'sp-kv__k', text: key } ),
			valueNode || h( 'span', { class: 'sp-kv__v', text: value } )
		] );
	}

	function card( extra, children ) {
		return h( 'div', { class: 'sp-card ' + ( extra || '' ) }, children );
	}

	function assistantHead( context ) {
		return h( 'header', { class: 'sp-fc-assistant__head' }, [
			h( 'span', {}, [ svg( ICON.shield, 19, '#fff', 1.9 ) ] ),
			h( 'span', {}, [
				h( 'strong', { text: t( 'formAssistant' ) } ),
				h( 'small', { text: context } )
			] )
		] );
	}

	// ── expiry countdown ────────────────────────────────────────

	function isExpired() { return ! expiresAt || Date.now() >= expiresAt; }

	function clockLabel() {
		var left = Math.max( 0, Math.floor( ( expiresAt - Date.now() ) / 1000 ) );
		var mm = Math.floor( left / 60 );
		var ss = left % 60;
		return mm + ':' + ( ss < 10 ? '0' : '' ) + ss;
	}

	function startExpiryTimer() {
		window.clearInterval( expiryTimer );
		expiryTimer = window.setInterval( function () {
			var box = byId( 'sp-expiry' );
			if ( ! box ) { window.clearInterval( expiryTimer ); return; }
			if ( isExpired() ) {
				window.clearInterval( expiryTimer );
				renderReview( preview );
				return;
			}
			box.querySelector( '[data-clock]' ).textContent = sprintf( t( 'validFor' ), clockLabel() );
			box.className = 'sp-expiry' + ( ( expiresAt - Date.now() ) < 120000 ? ' sp-expiry--warn' : '' );
		}, 1000 );
	}

	// ── WP-03 review ────────────────────────────────────────────

	function renderReview( plan ) {
		var root = byId( 'aisaautopilot-review-content' );
		if ( ! root || ! plan ) { return; }
		var expired = isExpired();
		var draft = plan.default_status !== 'publish';
		root.textContent = '';

		var meta = h( 'div', { class: 'sp-fc-review__meta' }, [
			h( 'div', { class: 'sp-fc-review__title' }, [
				h( 'h2', { text: plan.title || t( 'createWithAi' ) } ),
				h( 'div', { class: 'sp-meta-row' }, [
					expired ? pill( t( 'expired' ), 'off' ) : pill( t( 'waiting' ), 'info' ),
					h( 'span', { class: 'sp-mono', text: shortId() } )
				] )
			] ),
			h( 'div', {
				id: 'sp-expiry',
				class: 'sp-expiry' + ( expired ? ' sp-expiry--off' : '' )
			}, [
				svg( ICON.clock, 16, 'currentColor', 1.8 ),
				h( 'span', { 'data-clock': '', text: expired ? t( 'proposalExpired' ) : sprintf( t( 'validFor' ), clockLabel() ) } )
			] )
		] );

		var canvas = h( 'main', { class: 'sp-fc-canvas sp-fc-review-canvas', 'data-sp-form-review-canvas': true }, [
			h( 'div', { class: 'sp-fc-canvas__inner' }, [
				meta,
				h( 'div', { class: 'sp-strip' + ( expired ? ' sp-strip--off' : '' ) }, [
					svg( ICON.shield, 18, 'currentColor', 1.8 ),
					h( 'span', { text: expired ? t( 'expiredText' ) : t( 'safeText' ) } )
				] ),
				previewColumn( plan )
			] )
		] );

		var side = decisionColumn( plan, expired, draft );
		side.className = 'sp-fc-assistant sp-fc-review-assistant';
		side.setAttribute( 'data-sp-form-review-assistant', '' );
		side.insertBefore( h( 'div', { class: 'sp-fc-assistant__thread sp-fc-review__thread' }, [
			h( 'p', { text: t( 'reviewReady' ) } )
		] ), side.firstChild );
		side.insertBefore( assistantHead( sprintf( t( 'fieldCount' ), ( plan.fields || [] ).length ) ), side.firstChild );

		root.appendChild( h( 'div', { class: 'sp-fc-layout' }, [ canvas, side ] ) );

		showStage( 'review' );
		if ( ! expired ) { startExpiryTimer(); }
	}

	function previewColumn( plan ) {
		var col = h( 'div', { class: 'sp-col sp-col--540' } );

		var previewPane = h( 'div', { class: 'sp-preview' }, [ previewForm( plan ) ] );
		var structPane = h( 'div', { class: 'sp-card__body sp-stack', hidden: true }, structureRows( plan ) );

		var tabPreview = h( 'button', { type: 'button', class: 'sp-tabs__btn is-on', text: t( 'preview' ) } );
		var tabStruct = h( 'button', { type: 'button', class: 'sp-tabs__btn', text: t( 'structure' ) } );
		tabPreview.addEventListener( 'click', function () {
			tabPreview.classList.add( 'is-on' ); tabStruct.classList.remove( 'is-on' );
			previewPane.hidden = false; structPane.hidden = true;
		} );
		tabStruct.addEventListener( 'click', function () {
			tabStruct.classList.add( 'is-on' ); tabPreview.classList.remove( 'is-on' );
			previewPane.hidden = true; structPane.hidden = false;
		} );

		var formNode = previewPane.firstChild;
		var devDesktop = h( 'button', { type: 'button', class: 'sp-seg__btn is-on', text: t( 'desktop' ) } );
		var devMobile = h( 'button', { type: 'button', class: 'sp-seg__btn', text: t( 'mobile' ) } );
		devDesktop.addEventListener( 'click', function () {
			devDesktop.classList.add( 'is-on' ); devMobile.classList.remove( 'is-on' ); formNode.classList.remove( 'is-mobile' );
		} );
		devMobile.addEventListener( 'click', function () {
			devMobile.classList.add( 'is-on' ); devDesktop.classList.remove( 'is-on' ); formNode.classList.add( 'is-mobile' );
		} );

		col.appendChild( h( 'div', { class: 'sp-card sp-card--bare' }, [
			h( 'div', { class: 'sp-card__head sp-card__head--split' }, [
				h( 'div', { class: 'sp-tabs' }, [ tabPreview, tabStruct ] ),
				h( 'div', { class: 'sp-seg' }, [ devDesktop, devMobile ] )
			] ),
			previewPane,
			structPane
		] ) );

		col.appendChild( card( 'sp-card--16 sp-card--flat', [
			h( 'h3', { class: 'sp-h3', text: t( 'notCreated' ) } ),
			h( 'p', { class: 'sp-help sp-help--12', style: 'margin-bottom:11px', text: t( 'notCreatedNote' ) } ),
			h( 'div', { class: 'sp-stack sp-stack--sm' }, [
				excludedRow( t( 'noPayment' ) ),
				excludedRow( t( 'noPassword' ) ),
				excludedRow( t( 'noRegistration' ) )
			] )
		] ) );

		return col;
	}

	function excludedRow( label ) {
		return h( 'div', { style: 'display:flex;align-items:center;gap:9px;font-size:13px;color:#646970' }, [
			h( 'span', { style: 'width:16px;height:16px;border-radius:5px;background:#f0f0f1;color:#646970;display:flex;align-items:center;justify-content:center;font-size:12px;flex:0 0 auto', text: '×' } ),
			h( 'span', { text: label } )
		] );
	}

	function previewForm( plan ) {
		var form = h( 'form', { class: 'sp-preview__form', onsubmit: 'return false' } );
		form.addEventListener( 'submit', function ( e ) { e.preventDefault(); } );
		form.appendChild( h( 'div', {}, [
			h( 'h3', { class: 'sp-preview__title', text: plan.title || '' } ),
			plan.description ? h( 'p', { class: 'sp-preview__lede', text: plan.description } ) : null
		] ) );

		( plan.fields || [] ).forEach( function ( field, index ) { form.appendChild( previewField( field, index ) ); } );

		form.appendChild( h( 'button', {
			type: 'button',
			class: 'sp-preview__submit',
			text: plan.submit_button || 'Send'
		} ) );
		return form;
	}

	function previewField( field, index ) {
		var id = 'sp-pf-' + index;
		var control;
		var options = field.options || [];

		if ( field.type === 'textarea' ) {
			control = h( 'textarea', { id: id, disabled: true } );
		} else if ( field.type === 'select' ) {
			control = h( 'select', { id: id, disabled: true } );
			options.forEach( function ( option ) { control.appendChild( h( 'option', { text: option } ) ); } );
		} else if ( field.type === 'radio' || field.type === 'checkbox' ) {
			control = h( 'div', { class: 'sp-stack sp-stack--sm' } );
			options.forEach( function ( option, i ) {
				control.appendChild( h( 'label', { class: 'sp-check' }, [
					h( 'input', { type: field.type, name: id, id: id + '-' + i, disabled: true } ),
					h( 'span', { text: option } )
				] ) );
			} );
		} else {
			var type = [ 'email', 'tel', 'text', 'number', 'url', 'date' ].indexOf( field.type ) !== -1 ? field.type : 'text';
			control = h( 'input', { id: id, type: type, dir: ( type === 'email' || type === 'tel' || type === 'url' ) ? 'ltr' : null, disabled: true } );
		}

		return h( 'label', { class: 'sp-preview__field', for: id }, [
			h( 'span', { class: 'sp-preview__label' }, [
				h( 'span', { text: field.label || '' } ),
				field.required ? h( 'span', { class: 'sp-preview__req', text: ' *' } ) : null
			] ),
			control
		] );
	}

	function structureRows( plan ) {
		return ( plan.fields || [] ).map( function ( field ) {
			var meta = [ h( 'span', {}, [ t( 'typeLabel' ) + ': ', h( 'span', { class: 'sp-mono', text: field.type || 'text' } ) ] ) ];
			if ( ( field.options || [] ).length ) {
				meta.push( h( 'span', { text: sprintf( t( 'optionsCount' ), field.options.length ) } ) );
			}
			return h( 'div', { class: 'sp-fieldspec' }, [
				h( 'div', { class: 'sp-fieldspec__head' }, [
					h( 'span', { class: 'sp-fieldspec__name', text: field.label || '' } ),
					h( 'span', { class: field.required ? 'sp-tag sp-tag--err' : 'sp-tag sp-tag--off', text: field.required ? t( 'required' ) : t( 'optional' ) } )
				] ),
				h( 'div', { class: 'sp-fieldspec__meta' }, meta )
			] );
		} );
	}

	function decisionColumn( plan, expired, draft ) {
		var side = h( 'aside', { class: 'sp-side sp-side--320' } );
		var risk = ( plan.risk_level || 'low' ).toLowerCase();
		var riskTone = risk === 'high' ? 'err' : ( risk === 'medium' ? 'warn' : 'ok' );
		var riskLabel = risk === 'high' ? t( 'high' ) : ( risk === 'medium' ? t( 'medium' ) : t( 'low' ) );

		side.appendChild( card( 'sp-card--tight', [
			h( 'div', { class: 'sp-h3', style: 'margin-bottom:13px', text: t( 'whenApprove' ) } ),
			h( 'div', { class: 'sp-kv' }, [
				kvRow( t( 'connector' ), plan.connector || 'AISAAutoPilot Forms' ),
				kvRow( t( 'destination' ), plan.title || '' ),
				kvRow( t( 'state' ), null, h( 'span', { class: draft ? 'sp-pill sp-pill--off' : 'sp-pill sp-pill--warn' }, [ draft ? t( 'draftNotPublic' ) : t( 'publishedState' ) ] ) ),
				kvRow( t( 'riskLevel' ), null, pill( riskLabel, riskTone ) ),
				kvRow( t( 'notifications' ), plan.notification_email || t( 'emailToAdmin' ) )
			] )
		] ) );

		if ( ! draft ) {
			side.appendChild( h( 'div', { class: 'sp-card sp-card--flat', style: 'background:#fffbeb;border-color:#fde68a;padding:14px 16px' }, [
				h( 'div', { style: 'display:flex;gap:9px;align-items:flex-start' }, [
					svg( ICON.warn, 17, '#92400e', 1.8 ),
					h( 'div', {}, [
						h( 'div', { style: 'font-size:13.5px;font-weight:700;color:#92400e', text: t( 'approvePublish' ) } ),
						h( 'div', { class: 'sp-notice__text', style: 'margin-top:3px', text: t( 'publishedState' ) } )
					] )
				] )
			] ) );
		}

		var approve = h( 'button', {
			type: 'button',
			class: 'sp-btn sp-btn--lg sp-btn--block',
			id: 'aisaautopilot-approve',
			text: expired ? t( 'cannotApprove' ) : ( draft ? t( 'approveDraft' ) : t( 'approvePublish' ) ),
			disabled: expired || null,
			onClick: function () { draft ? approvePlan() : openConfirm(); }
		} );

		side.appendChild( h( 'div', { class: 'sp-card sp-card--16 sp-card--flat sp-decide' }, [
			approve,
			h( 'button', { type: 'button', class: 'sp-btn sp-btn--ghost sp-btn--block', text: t( 'backAndEdit' ), onClick: returnToCompose } ),
			h( 'button', { type: 'button', class: 'sp-btn sp-btn--reject sp-btn--block', text: t( 'rejectProposal' ), onClick: rejectPlan } ),
			h( 'p', { class: 'sp-decide__note', text: expired ? t( 'expiredNote' ) : t( 'approveNote' ) } )
		] ) );

		return side;
	}

	function shortId() {
		if ( ! actionId ) { return '—'; }
		return 'act_' + String( actionId ).replace( /-/g, '' ).slice( 0, 6 );
	}

	// ── WP-04 result ────────────────────────────────────────────

	function renderResult( result ) {
		var root = byId( 'aisaautopilot-result-content' );
		if ( ! root ) { return; }
		root.textContent = '';

		var ok = result && result.form_id;
		var draft = ! result || result.is_draft !== false;

		var head = h( 'div', { style: 'display:flex;gap:14px;align-items:flex-start' }, [
			h( 'span', {
				style: 'flex:0 0 42px;height:42px;border-radius:11px;display:flex;align-items:center;justify-content:center;' +
					( ok ? 'background:#ecfdf5;color:#065f46' : 'background:#fef2f2;color:#b91c1c' )
			}, [ ok ? svg( ICON.check, 22, 'currentColor', 2.4 ) : h( 'span', { style: 'font-size:20px;font-weight:700', text: '!' } ) ] ),
			h( 'div', { style: 'min-width:0;flex:1' }, [
				h( 'h2', { style: 'font-size:17px;font-weight:800;margin:0 0 6px;letter-spacing:-.01em', text: ok ? ( draft ? t( 'okTitle' ) : t( 'okTitlePub' ) ) : t( 'failTitle' ) } ),
				h( 'p', { style: 'margin:0;color:#646970;font-size:13.5px', text: ok ? sprintf( draft ? t( 'okBodyDraft' ) : t( 'okBodyPublished' ), ( preview && preview.title ) || '' ) : ( result && result.message ? result.message : t( 'genericError' ) ) } )
			] )
		] );

		var body = h( 'div', {
			class: 'sp-fc-result__card ' + ( ok ? 'sp-fc-result__card--ok' : 'sp-fc-result__card--bad' ),
			'data-sp-form-result': true
		}, [ head ] );

		if ( ok && result.shortcode ) {
			var input = h( 'input', { class: 'sp-input sp-input--r9 sp-mono', dir: 'ltr', readonly: true, value: result.shortcode } );
			var copy = h( 'button', { type: 'button', class: 'sp-btn sp-btn--ghost' }, [ svg( ICON.copy, 15 ), h( 'span', { text: t( 'copy' ) } ) ] );
			copy.addEventListener( 'click', function () {
				input.select();
				if ( navigator.clipboard && navigator.clipboard.writeText ) { navigator.clipboard.writeText( result.shortcode ); }
				else { try { document.execCommand( 'copy' ); } catch ( e ) {} }
				toast( t( 'copied' ) );
			} );

			var actions = [];
			actions.push( h( 'button', { type: 'button', class: 'sp-btn', 'data-sp-form-build-another': true, text: t( 'buildAnother' ), onClick: returnToCompose } ) );
			if ( result.edit_url ) { actions.push( h( 'a', { class: 'sp-btn sp-btn--ghost', href: result.edit_url, text: draft ? t( 'openDraft' ) : t( 'openForm' ) } ) ); }
			if ( boot.efbReady ) { actions.push( h( 'a', { class: 'sp-btn sp-btn--ghost', href: boot.efbUrl, text: t( 'editInEfb' ) } ) ); }

			body.appendChild( h( 'div', { class: 'sp-fc-result__shortcode' }, [
				h( 'label', { class: 'sp-label', text: t( 'shortcode' ) } ),
				h( 'div', { class: 'sp-row-wrap' }, [ input, copy ] )
			] ) );
			body.appendChild( h( 'div', { class: 'sp-fc-result__delivery' }, [
				svg( '<path d="M4 6h16v12H4ZM4 7l8 6 8-6"/>', 14, '#5b5e7d', 1.9 ),
				h( 'span', { text: t( 'entriesEmailTo' ) + ' ' + ( ( preview && preview.notification_email ) || t( 'emailToAdmin' ) ) } )
			] ) );
			body.appendChild( h( 'div', { class: 'sp-btn-group sp-btn-group--sm sp-fc-result__actions' }, actions ) );
		} else if ( ! ok ) {
			body.appendChild( h( 'div', { class: 'sp-btn-group sp-btn-group--sm sp-fc-result__actions' }, [
				h( 'button', { type: 'button', class: 'sp-btn', text: t( 'tryAgain' ), onClick: returnToCompose } )
			] ) );
		}

		var canvas = h( 'main', { class: 'sp-fc-canvas sp-fc-result-canvas', 'data-sp-form-result-canvas': true }, [
			h( 'div', { class: 'sp-fc-canvas__inner' }, [ body ] )
		] );
		var resultSummary = ok ? ( draft ? t( 'resultDraft' ) : t( 'resultPublished' ) ) : t( 'resultFailed' );
		var side = h( 'aside', { class: 'sp-fc-assistant sp-fc-result-assistant', 'data-sp-form-result-assistant': true }, [
			assistantHead( ok ? t( 'formCreated' ) : t( 'creationFailed' ) ),
			h( 'div', { class: 'sp-fc-assistant__thread' }, [
				h( 'p', { text: resultSummary } ),
				ok ? h( 'div', { class: 'sp-fc-result__ops' }, [
					h( 'strong', { text: t( 'createdInWp' ) } ),
					h( 'span', { text: draft ? t( 'draftNotPublic' ) : t( 'publishedState' ) } ),
					h( 'span', { text: t( 'notifications' ) + ': ' + ( ( preview && preview.notification_email ) || t( 'emailToAdmin' ) ) } ),
					h( 'span', { class: 'sp-mono', text: shortId() } )
				] ) : null
			] ),
			h( 'div', { class: 'sp-fc-assistant__footer' }, [
				h( 'a', { class: 'sp-btn sp-btn--ghost sp-btn--block', href: boot.historyUrl, text: t( 'viewInActivity' ) } )
			] )
		] );

		root.appendChild( h( 'div', { class: 'sp-fc-layout' }, [ canvas, side ] ) );

		showStage( 'result' );
	}

	// ── flow actions ────────────────────────────────────────────

	function returnToCompose() {
		actionId = null;
		preview = null;
		expiresAt = null;
		window.clearInterval( expiryTimer );
		byId( 'aisaautopilot-review-content' ).textContent = '';
		byId( 'aisaautopilot-result-content' ).textContent = '';
		setStatus( t( 'submitHint' ) );
		showStage( 'compose' );
		var prompt = byId( 'aisaautopilot-prompt' );
		if ( prompt ) { prompt.focus(); }
	}

	function rejectPlan() {
		toast( t( 'rejected' ) );
		returnToCompose();
	}

	function setGenStep( step ) {
		[ 1, 2, 3 ].forEach( function ( n ) {
			var node = document.querySelector( '[data-gen-step="' + n + '"]' );
			if ( ! node ) { return; }
			node.classList.toggle( 'is-done', step > n );
			node.classList.toggle( 'is-current', step === n );
			node.querySelector( '.sp-mark' ).textContent = step > n ? '✓' : '•';
		} );
	}

	function generatePlan() {
		var promptNode = byId( 'aisaautopilot-prompt' );
		var prompt = promptNode ? ( promptNode.value || '' ).trim() : '';
		if ( ! prompt ) { setStatus( t( 'promptFirst' ) ); return; }

		var generate = byId( 'aisaautopilot-generate' );
		var progress = byId( 'aisaautopilot-generation-progress' );
		generate.disabled = true;
		if ( progress ) { progress.hidden = false; }
		setGenStep( 1 );
		var tick = 1;
		window.clearInterval( genTimer );
		genTimer = window.setInterval( function () { tick = Math.min( 3, tick + 1 ); setGenStep( tick ); }, 900 );
		setStatus( t( 'building' ) );

		request( 'forms/plan', { prompt: prompt } ).then( function ( response ) {
			window.clearInterval( genTimer );
			generate.disabled = false;
			if ( progress ) { progress.hidden = true; }
			if ( ! response.ok || ! response.data.ok ) {
				setStatus( response.data.message || ( response.data.errors || [] ).join( ' ' ) || t( 'genericError' ) );
				return;
			}
			setStatus( t( 'submitHint' ) );
			actionId = response.data.action_id;
			preview = response.data.preview;
			expiresAt = response.data.expires_at ? Number( response.data.expires_at ) * 1000 : Date.now() + 900000;
			renderReview( preview );
		} ).catch( function () {
			window.clearInterval( genTimer );
			generate.disabled = false;
			if ( progress ) { progress.hidden = true; }
			setStatus( t( 'networkError' ) );
		} );
	}

	function openConfirm() {
		var modal = byId( 'sp-confirm' );
		if ( ! modal ) { approvePlan(); return; }
		byId( 'sp-confirm-ack' ).checked = false;
		byId( 'sp-confirm-go' ).disabled = true;
		modal.hidden = false;
	}

	function closeConfirm() {
		var modal = byId( 'sp-confirm' );
		if ( modal ) { modal.hidden = true; }
	}

	function approvePlan() {
		if ( ! actionId ) { return; }
		closeConfirm();
		busy( true, t( 'creating' ) );
		request( 'forms/execute', { action_id: actionId } ).then( function ( response ) {
			busy( false );
			if ( ! response.ok || ! response.data.ok ) {
				renderResult( { message: response.data.message || t( 'genericError' ) } );
				return;
			}
			renderResult( response.data.result || {} );
		} ).catch( function () {
			busy( false );
			renderResult( { message: t( 'networkError' ) } );
		} );
	}

	// ── voice ───────────────────────────────────────────────────

	function voiceButton( active ) {
		var voice = byId( 'aisaautopilot-voice-toggle' );
		if ( ! voice ) { return; }
		voice.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
		voice.classList.toggle( 'is-recording', active );
		var label = voice.querySelector( '.aisaautopilot-voice-button__label' );
		if ( label ) { label.textContent = active ? t( 'stop' ) : t( 'speak' ); }
	}

	function stopRecording() { if ( recorder && recorder.state === 'recording' ) { recorder.stop(); } }

	function transcribeRecording( blob, duration ) {
		var form = new FormData();
		form.append( 'audio', blob, 'aisaautopilot-form-command.webm' );
		form.append( 'duration_ms', String( duration ) );
		form.append( 'language', document.documentElement.lang || '' );
		setStatus( t( 'transcribing' ) );
		fetch( cfg.restUrl + 'forms/voice/transcribe', {
			method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce }, body: form
		} ).then( responseData ).then( function ( response ) {
			if ( ! response.ok || ! response.data.ok || ! response.data.transcript ) {
				setStatus( response.data.message || t( 'voiceFailed' ) );
				return;
			}
			var prompt = byId( 'aisaautopilot-prompt' );
			prompt.value = ( prompt.value ? prompt.value.trim() + ' ' : '' ) + response.data.transcript;
			prompt.focus();
			setStatus( t( 'voiceAdded' ) );
		} ).catch( function () { setStatus( t( 'voiceFailed' ) ); } );
	}

	function toggleVoice() {
		if ( recorder && recorder.state === 'recording' ) { stopRecording(); return; }
		if ( ! navigator.mediaDevices || ! window.MediaRecorder ) { setStatus( t( 'noMic' ) ); return; }
		navigator.mediaDevices.getUserMedia( { audio: true } ).then( function ( stream ) {
			recorderStream = stream;
			chunks = [];
			try { recorder = new MediaRecorder( stream, { mimeType: 'audio/webm' } ); } catch ( error ) { recorder = new MediaRecorder( stream ); }
			var started = Date.now();
			recorder.addEventListener( 'dataavailable', function ( event ) { if ( event.data && event.data.size ) { chunks.push( event.data ); } } );
			recorder.addEventListener( 'stop', function () {
				window.clearTimeout( recordingTimer );
				voiceButton( false );
				if ( recorderStream ) { recorderStream.getTracks().forEach( function ( track ) { track.stop(); } ); }
				var blob = new Blob( chunks, { type: recorder.mimeType || 'audio/webm' } );
				if ( blob.size ) { transcribeRecording( blob, Math.max( 1, Date.now() - started ) ); }
			} );
			recorder.start();
			voiceButton( true );
			setStatus( t( 'recording' ) );
			recordingTimer = window.setTimeout( stopRecording, 45000 );
		} ).catch( function () { setStatus( t( 'micDenied' ) ); } );
	}

	// ── boot ────────────────────────────────────────────────────

	function init() {
		var newForm = document.querySelector( '[data-sp-form-new]' );
		if ( newForm ) {
			newForm.addEventListener( 'click', function () {
				returnToCompose();
				var input = byId( 'aisaautopilot-prompt' );
				if ( input ) { input.value = ''; input.focus(); }
			} );
		}
		var generate = byId( 'aisaautopilot-generate' );
		if ( generate ) { generate.addEventListener( 'click', generatePlan ); }

		var voice = byId( 'aisaautopilot-voice-toggle' );
		if ( voice ) { voice.addEventListener( 'click', toggleVoice ); }

		var prompt = byId( 'aisaautopilot-prompt' );
		if ( prompt ) {
			prompt.addEventListener( 'keydown', function ( event ) {
				if ( ( event.ctrlKey || event.metaKey ) && event.key === 'Enter' ) { generatePlan(); }
			} );
		}

		Array.prototype.forEach.call( document.querySelectorAll( '#aisaautopilot-examples .sp-chip' ), function ( chip ) {
			chip.addEventListener( 'click', function () {
				var node = byId( 'aisaautopilot-prompt' );
				node.value = chip.getAttribute( 'data-example' ) || '';
				node.focus();
			} );
		} );

		var ack = byId( 'sp-confirm-ack' );
		if ( ack ) { ack.addEventListener( 'change', function () { byId( 'sp-confirm-go' ).disabled = ! ack.checked; } ); }
		var go = byId( 'sp-confirm-go' );
		if ( go ) { go.addEventListener( 'click', approvePlan ); }
		Array.prototype.forEach.call( document.querySelectorAll( '[data-sp-confirm-cancel]' ), function ( node ) {
			node.addEventListener( 'click', closeConfirm );
		} );

		window.addEventListener( 'beforeunload', function () { stopRecording(); } );
	}

	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
}() );
