/* global SitePilotAI */
( function () {
	'use strict';

	var cfg = window.SitePilotAI || {};
	var currentActionId = null;
	var currentPreview = null;
	var actionExpiresAt = null;
	var recorder = null;
	var recorderStream = null;
	var chunks = [];
	var recordingTimer = null;

	function byId( id ) { return document.getElementById( id ); }

	function text( value ) { return document.createTextNode( value === undefined || value === null ? '' : String( value ) ); }

	function element( tag, attrs, children ) {
		var node = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( key ) {
			if ( key === 'class' ) { node.className = attrs[ key ]; }
			else if ( key === 'text' ) { node.textContent = attrs[ key ]; }
			else if ( key === 'htmlFor' ) { node.htmlFor = attrs[ key ]; }
			else { node.setAttribute( key, attrs[ key ] ); }
		} );
		( children || [] ).forEach( function ( child ) {
			if ( child ) { node.appendChild( typeof child === 'string' ? text( child ) : child ); }
		} );
		return node;
	}

	function button( label, classes, handler, attrs ) {
		var props = attrs || {};
		props.type = 'button';
		props.class = classes;
		props.text = label;
		var node = element( 'button', props );
		node.addEventListener( 'click', handler );
		return node;
	}

	function setStatus( message, kind ) {
		var node = byId( 'sitepilot-status' );
		if ( ! node ) { return; }
		node.className = 'sitepilot-status' + ( kind ? ' sitepilot-status--' + kind : '' );
		node.textContent = message;
	}

	function showStage( name ) {
		[ 'compose', 'review', 'result' ].forEach( function ( stage ) {
			var screen = byId( 'sitepilot-' + stage + '-screen' );
			if ( ! screen ) { return; }
			var active = stage === name;
			screen.hidden = ! active;
			screen.setAttribute( 'aria-hidden', active ? 'false' : 'true' );
		} );
		var activeScreen = byId( 'sitepilot-' + name + '-screen' );
		if ( activeScreen ) { activeScreen.scrollIntoView( { behavior: 'smooth', block: 'start' } ); }
	}

	function request( path, body ) {
		return fetch( cfg.restUrl + path, {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( body )
		} ).then( responseData );
	}

	function responseData( response ) {
		return response.json().catch( function () { return {}; } ).then( function ( data ) {
			return { ok: response.ok, data: data };
		} );
	}

	function breadcrumb( items ) {
		var nav = element( 'nav', { class: 'sitepilot-breadcrumb', 'aria-label': 'Breadcrumb' } );
		items.forEach( function ( item, index ) {
			if ( index ) { nav.appendChild( element( 'span', { 'aria-hidden': 'true', text: '/' } ) ); }
			if ( item.href ) { nav.appendChild( element( 'a', { href: item.href, text: item.label } ) ); }
			else { nav.appendChild( element( 'span', { text: item.label } ) ); }
		} );
		return nav;
	}

	function flowHeading( id, title, subtitle, meta ) {
		var pieces = [ element( 'h1', { id: id, class: 'sitepilot-title', text: title } ) ];
		if ( meta ) { pieces.push( meta ); }
		pieces.push( element( 'p', { class: 'sitepilot-subtitle', text: subtitle } ) );
		return element( 'header', { class: 'sitepilot-flow-heading' }, [ element( 'div', {}, pieces ) ] );
	}

	function actionIdLabel() {
		if ( ! currentActionId ) { return '—'; }
		return currentActionId.length > 18 ? currentActionId.slice( 0, 18 ) + '…' : currentActionId;
	}

	function expiryLabel() {
		if ( ! actionExpiresAt ) { return 'Expires in 15 minutes'; }
		var minutes = Math.max( 0, Math.ceil( ( actionExpiresAt - Date.now() ) / 60000 ) );
		return minutes ? 'Expires in ' + minutes + ' minute' + ( minutes === 1 ? '' : 's' ) : 'This plan has expired';
	}

	function fieldControl( field, index ) {
		var id = 'sitepilot-preview-field-' + index;
		var required = field.required ? 'required' : null;
		var options = field.options || [];
		var control;
		var label = element( 'label', { htmlFor: id, text: field.label || 'Untitled field' } );
		var row = element( 'div', { class: 'sitepilot-form-preview__field' }, [ label ] );

		if ( field.type === 'textarea' ) {
			control = element( 'textarea', { id: id, rows: '3', placeholder: field.label || '', disabled: 'disabled' } );
		} else if ( field.type === 'select' ) {
			control = element( 'select', { id: id, disabled: 'disabled' } );
			control.appendChild( element( 'option', { text: 'Choose an option' } ) );
			options.forEach( function ( option ) { control.appendChild( element( 'option', { text: option } ) ); } );
		} else if ( field.type === 'radio' || field.type === 'checkbox' ) {
			var group = element( 'div', { class: 'sitepilot-form-preview__options', role: field.type === 'radio' ? 'radiogroup' : 'group', 'aria-label': field.label || 'Options' } );
			options.forEach( function ( option, optionIndex ) {
				var optionId = id + '-' + optionIndex;
				var input = element( 'input', { id: optionId, type: field.type, name: id, disabled: 'disabled' } );
				group.appendChild( element( 'label', { htmlFor: optionId }, [ input, text( option ) ] ) );
			} );
			control = group;
		} else {
			var inputType = [ 'email', 'tel', 'text' ].indexOf( field.type ) !== -1 ? field.type : 'text';
			control = element( 'input', { id: id, type: inputType, placeholder: field.label || '', disabled: 'disabled' } );
		}
		if ( required ) { row.appendChild( element( 'span', { class: 'sitepilot-form-preview__required', text: 'Required' } ) ); }
		row.appendChild( control );
		return row;
	}

	function previewForm( preview ) {
		var form = element( 'div', { class: 'sitepilot-form-preview' } );
		form.appendChild( element( 'h2', { text: preview.title || 'Untitled form' } ) );
		if ( preview.description ) { form.appendChild( element( 'p', { class: 'sitepilot-form-preview__description', text: preview.description } ) ); }
		( preview.fields || [] ).forEach( function ( field, index ) { form.appendChild( fieldControl( field, index ) ); } );
		form.appendChild( element( 'button', { type: 'button', class: 'button button-primary', text: 'Submit form', disabled: 'disabled' } ) );
		return form;
	}

	function structureDetails( preview ) {
		var table = element( 'table', { class: 'sitepilot-fields' } );
		table.appendChild( element( 'thead', {}, [ element( 'tr', {}, [
			element( 'th', { text: 'Field' } ), element( 'th', { text: 'Type' } ), element( 'th', { text: 'Required' } ), element( 'th', { text: 'Options' } )
		] ) ] ) );
		var body = element( 'tbody' );
		( preview.fields || [] ).forEach( function ( field ) {
			body.appendChild( element( 'tr', {}, [
				element( 'td', { text: field.label || '—' } ), element( 'td', { text: field.type || 'text' } ),
				element( 'td', { text: field.required ? 'Yes' : 'No' } ), element( 'td', { text: ( field.options || [] ).join( ', ' ) || '—' } )
			] ) );
		} );
		table.appendChild( body );
		return table;
	}

	function renderReview( preview ) {
		var root = byId( 'sitepilot-review-content' );
		if ( ! root ) { return; }
		root.textContent = '';
		root.appendChild( breadcrumb( [ { label: 'Home', href: 'admin.php?page=sitepilot-ai' }, { label: 'Create with AI' }, { label: 'Review and approve' } ] ) );
		var meta = element( 'div', { class: 'sitepilot-flow-meta' }, [
			element( 'span', { class: 'sitepilot-pill sitepilot-pill--warn', text: 'Approval required' } ),
			element( 'span', { class: 'sitepilot-flow-meta__item', text: 'Action ' + actionIdLabel() } ),
			element( 'span', { class: 'sitepilot-flow-meta__item', text: 'Created just now' } ),
			element( 'span', { id: 'sitepilot-expiry', class: 'sitepilot-expiry', text: expiryLabel() } )
		] );
		root.appendChild( flowHeading( 'sitepilot-review-title', preview.title || 'Review the proposed form', 'Confirm its fields and the delivery route before the form is created.', meta ) );

		var timeline = element( 'ol', { class: 'sitepilot-flow-timeline', 'aria-label': 'Action progress' } );
		[ 'Request', 'AI plan', 'Safety check', 'Approval', 'Execution' ].forEach( function ( item, index ) {
			timeline.appendChild( element( 'li', { class: index < 3 ? 'is-complete' : index === 3 ? 'is-current' : '', text: item } ) );
		} );
		root.appendChild( timeline );
		root.appendChild( element( 'div', { class: 'sitepilot-flow-alert sitepilot-flow-alert--info' }, [
			element( 'span', { class: 'dashicons dashicons-shield', 'aria-hidden': 'true' } ),
			element( 'div', {}, [ element( 'strong', { text: 'Safety check passed' } ), element( 'p', { text: 'This plan contains only single-step basic fields. It creates a draft and sends visitor submissions by email only.' } ) ] )
		] ) );

		var left = element( 'div', { class: 'sitepilot-flow-card sitepilot-review-preview' } );
		var previewArea = element( 'div', { class: 'sitepilot-review-preview__content' } );
		var structureArea = element( 'div', { class: 'sitepilot-review-preview__content', hidden: 'hidden' }, [ structureDetails( preview ) ] );
		var previewTab = button( 'Preview', 'sitepilot-view-tab is-active', function () {
			previewTab.classList.add( 'is-active' ); structureTab.classList.remove( 'is-active' ); previewArea.hidden = false; structureArea.hidden = true;
		} );
		var structureTab = button( 'Structure details', 'sitepilot-view-tab', function () {
			structureTab.classList.add( 'is-active' ); previewTab.classList.remove( 'is-active' ); previewArea.hidden = true; structureArea.hidden = false;
		} );
		var desktop = button( 'Desktop', 'sitepilot-device-button is-active', function () { desktop.classList.add( 'is-active' ); mobile.classList.remove( 'is-active' ); left.classList.remove( 'is-mobile' ); } );
		var mobile = button( 'Mobile', 'sitepilot-device-button', function () { mobile.classList.add( 'is-active' ); desktop.classList.remove( 'is-active' ); left.classList.add( 'is-mobile' ); } );
		left.appendChild( element( 'div', { class: 'sitepilot-review-preview__head' }, [
			element( 'div', { class: 'sitepilot-view-tabs', role: 'tablist' }, [ previewTab, structureTab ] ),
			element( 'div', { class: 'sitepilot-device-switch', 'aria-label': 'Preview device' }, [ desktop, mobile ] )
		] ) );
		previewArea.appendChild( previewForm( preview ) );
		left.appendChild( previewArea );
		left.appendChild( structureArea );
		if ( preview.notes ) { left.appendChild( element( 'p', { class: 'sitepilot-notes', text: preview.notes } ) ); }
		left.appendChild( element( 'div', { class: 'sitepilot-excluded-note' }, [
			element( 'span', { class: 'dashicons dashicons-info-outline', 'aria-hidden': 'true' } ),
			element( 'p', { text: 'Uploads, payments, conditional logic and multi-step flows are intentionally excluded. Use Easy Form Builder for those capabilities.' } )
		] ) );

		var approveStatus = element( 'span', { id: 'sitepilot-approve-status', class: 'sitepilot-status', role: 'status', 'aria-live': 'polite' } );
		var right = element( 'aside', { class: 'sitepilot-flow-card sitepilot-approval-card', 'aria-label': 'Approval details' } );
		right.appendChild( element( 'h2', { text: 'Approve this action' } ) );
		right.appendChild( element( 'p', { text: 'Review the destination before SitePilot creates anything on your site.' } ) );
		var details = element( 'dl', { class: 'sitepilot-context-list sitepilot-approval-list' }, [
			element( 'div', {}, [ element( 'dt', { text: 'Connector' } ), element( 'dd', { text: preview.connector || 'SitePilot Forms' } ) ] ),
			element( 'div', {}, [ element( 'dt', { text: 'Create as' } ), element( 'dd', { text: preview.default_status === 'publish' ? 'Published form' : 'Draft form' } ) ] ),
			element( 'div', {}, [ element( 'dt', { text: 'Submissions' } ), element( 'dd', { text: 'Email only — not stored' } ) ] ),
			element( 'div', {}, [ element( 'dt', { text: 'Recipient' } ), element( 'dd', { text: preview.notification_email || 'Configured notification email' } ) ] )
		] );
		right.appendChild( details );
		right.appendChild( element( 'div', { class: 'sitepilot-approval-card__warning' }, [ element( 'span', { class: 'dashicons dashicons-lock', 'aria-hidden': 'true' } ), element( 'span', { text: 'A fresh confirmation is required. This plan expires after 15 minutes.' } ) ] ) );
		var approve = button( 'Approve & create form', 'button button-primary button-hero sitepilot-approval-card__approve', approvePlan, { id: 'sitepilot-approve' } );
		var back = button( 'Edit request', 'button button-secondary sitepilot-approval-card__back', returnToCompose );
		right.appendChild( element( 'div', { class: 'sitepilot-approval-card__actions' }, [ approve, back, approveStatus ] ) );

		root.appendChild( element( 'div', { class: 'sitepilot-review-layout' }, [ left, right ] ) );
		showStage( 'review' );
	}

	function renderResult( result ) {
		var root = byId( 'sitepilot-result-content' );
		if ( ! root ) { return; }
		root.textContent = '';
		root.appendChild( breadcrumb( [ { label: 'Home', href: 'admin.php?page=sitepilot-ai' }, { label: 'Create with AI' }, { label: 'Action result' } ] ) );
		root.appendChild( flowHeading( 'sitepilot-result-title', 'Form created', result.is_draft ? 'The form is saved as a draft. Publish it after checking the final content.' : 'The form is published and ready to place on a page.' ) );

		var card = element( 'section', { class: 'sitepilot-result-card sitepilot-flow-card' }, [
			element( 'span', { class: 'dashicons dashicons-yes-alt sitepilot-result-card__icon', 'aria-hidden': 'true' } ),
			element( 'h2', { text: result.title || 'Your form is ready' } ),
			element( 'p', { text: 'Use the shortcode below to place this simple email form on any page.' } )
		] );
		if ( result.shortcode ) {
			var code = element( 'code', { text: result.shortcode } );
			var copy = button( 'Copy', 'button button-secondary button-small', function () {
				if ( navigator.clipboard && navigator.clipboard.writeText ) { navigator.clipboard.writeText( result.shortcode ); }
				copy.textContent = 'Copied';
			} );
			card.appendChild( element( 'div', { class: 'sitepilot-shortcode' }, [ code, copy ] ) );
		}
		var actions = [];
		if ( result.edit_url ) { actions.push( element( 'a', { class: 'button button-primary', href: result.edit_url, text: 'Open form' } ) ); }
		actions.push( button( 'Create another form', 'button button-secondary', returnToCompose ) );
		card.appendChild( element( 'div', { class: 'sitepilot-result-card__actions' }, actions ) );
		root.appendChild( card );

		var metrics = element( 'div', { class: 'sitepilot-result-metrics' }, [
			element( 'article', {}, [ element( 'span', { text: 'Action ID' } ), element( 'strong', { text: actionIdLabel() } ) ] ),
			element( 'article', {}, [ element( 'span', { text: 'Connector' } ), element( 'strong', { text: currentPreview && currentPreview.connector ? currentPreview.connector : 'SitePilot Forms' } ) ] ),
			element( 'article', {}, [ element( 'span', { text: 'Form state' } ), element( 'strong', { text: result.is_draft ? 'Draft' : 'Published' } ) ] )
		] );
		root.appendChild( metrics );
		showStage( 'result' );
	}

	function returnToCompose() {
		currentActionId = null;
		currentPreview = null;
		actionExpiresAt = null;
		var review = byId( 'sitepilot-review-content' );
		var result = byId( 'sitepilot-result-content' );
		if ( review ) { review.textContent = ''; }
		if ( result ) { result.textContent = ''; }
		setStatus( 'Adjust the request, then generate a new preview.', '' );
		showStage( 'compose' );
		var prompt = byId( 'sitepilot-prompt' );
		if ( prompt ) { prompt.focus(); }
	}

	function generatePlan() {
		var promptNode = byId( 'sitepilot-prompt' );
		var prompt = promptNode ? ( promptNode.value || '' ).trim() : '';
		if ( ! prompt ) { setStatus( 'Type or speak a description of the form first.', 'error' ); return; }
		var generate = byId( 'sitepilot-generate' );
		var progress = byId( 'sitepilot-generation-progress' );
		generate.disabled = true;
		if ( progress ) { progress.hidden = false; }
		setStatus( 'Preparing the reviewable plan…', 'loading' );
		request( 'forms/plan', { prompt: prompt } ).then( function ( response ) {
			generate.disabled = false;
			if ( progress ) { progress.hidden = true; }
			if ( ! response.ok || ! response.data.ok ) {
				setStatus( response.data.message || ( response.data.errors || [] ).join( ' ' ) || 'Could not generate a safe form.', 'error' );
				return;
			}
			currentActionId = response.data.action_id;
			currentPreview = response.data.preview;
			actionExpiresAt = response.data.expires_at ? Number( response.data.expires_at ) * 1000 : Date.now() + 900000;
			renderReview( currentPreview );
		} ).catch( function () {
			generate.disabled = false;
			if ( progress ) { progress.hidden = true; }
			setStatus( 'Network error. Please try again.', 'error' );
		} );
	}

	function approvePlan() {
		if ( ! currentActionId ) { return; }
		var approve = byId( 'sitepilot-approve' );
		var status = byId( 'sitepilot-approve-status' );
		if ( approve ) { approve.disabled = true; }
		if ( status ) { status.textContent = 'Creating the approved form…'; status.className = 'sitepilot-status sitepilot-status--loading'; }
		request( 'forms/execute', { action_id: currentActionId } ).then( function ( response ) {
			if ( ! response.ok || ! response.data.ok ) {
				if ( status ) { status.className = 'sitepilot-status sitepilot-status--error'; status.textContent = response.data.message || 'Creation failed.'; }
				if ( approve ) { approve.disabled = false; }
				return;
			}
			renderResult( response.data.result || {} );
		} ).catch( function () {
			if ( status ) { status.className = 'sitepilot-status sitepilot-status--error'; status.textContent = 'Network error. Please try again.'; }
			if ( approve ) { approve.disabled = false; }
		} );
	}

	function voiceButton( active ) {
		var voice = byId( 'sitepilot-voice-toggle' );
		if ( ! voice ) { return; }
		voice.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
		voice.setAttribute( 'aria-label', active ? 'Stop voice recording' : 'Start voice command' );
		voice.classList.toggle( 'is-recording', active );
		var label = voice.querySelector( '.sitepilot-voice-button__label' );
		if ( label ) { label.textContent = active ? 'Stop' : 'Speak'; }
	}

	function stopRecording() { if ( recorder && recorder.state === 'recording' ) { recorder.stop(); } }

	function transcribeRecording( blob, duration ) {
		var form = new FormData();
		form.append( 'audio', blob, 'sitepilot-form-command.webm' );
		form.append( 'duration_ms', String( duration ) );
		form.append( 'language', document.documentElement.lang || '' );
		setStatus( 'Transcribing your voice command…', 'loading' );
		fetch( cfg.restUrl + 'forms/voice/transcribe', { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce }, body: form } )
			.then( responseData ).then( function ( response ) {
				if ( ! response.ok || ! response.data.ok || ! response.data.transcript ) { setStatus( response.data.message || 'Voice transcription failed. You can type the command instead.', 'error' ); return; }
				var prompt = byId( 'sitepilot-prompt' );
				prompt.value = ( prompt.value ? prompt.value.trim() + ' ' : '' ) + response.data.transcript;
				prompt.focus();
				setStatus( 'Voice command added. Review the text, then generate the preview.', 'ok' );
			} ).catch( function () { setStatus( 'Voice transcription could not be reached.', 'error' ); } );
	}

	function toggleVoice() {
		if ( recorder && recorder.state === 'recording' ) { stopRecording(); return; }
		if ( ! navigator.mediaDevices || ! window.MediaRecorder ) { setStatus( 'This browser does not support voice recording. Please type the command.', 'error' ); return; }
		navigator.mediaDevices.getUserMedia( { audio: true } ).then( function ( stream ) {
			recorderStream = stream; chunks = [];
			try { recorder = new MediaRecorder( stream, { mimeType: 'audio/webm' } ); } catch ( error ) { recorder = new MediaRecorder( stream ); }
			var started = Date.now();
			recorder.addEventListener( 'dataavailable', function ( event ) { if ( event.data && event.data.size ) { chunks.push( event.data ); } } );
			recorder.addEventListener( 'stop', function () {
				window.clearTimeout( recordingTimer ); voiceButton( false );
				if ( recorderStream ) { recorderStream.getTracks().forEach( function ( track ) { track.stop(); } ); }
				var blob = new Blob( chunks, { type: recorder.mimeType || 'audio/webm' } );
				if ( blob.size ) { transcribeRecording( blob, Math.max( 1, Date.now() - started ) ); }
				else { setStatus( 'No audio was captured. Please try again.', 'error' ); }
			} );
			recorder.start(); voiceButton( true ); setStatus( 'Recording… click the microphone again when you finish.', 'loading' );
			recordingTimer = window.setTimeout( stopRecording, 45000 );
		} ).catch( function () { setStatus( 'Microphone access was not granted. Please type the command.', 'error' ); } );
	}

	function init() {
		var generate = byId( 'sitepilot-generate' );
		if ( generate ) { generate.addEventListener( 'click', generatePlan ); }
		var voice = byId( 'sitepilot-voice-toggle' );
		if ( voice ) { voice.addEventListener( 'click', toggleVoice ); }
		Array.prototype.forEach.call( document.querySelectorAll( '.sitepilot-chip' ), function ( chip ) {
			chip.addEventListener( 'click', function () { var prompt = byId( 'sitepilot-prompt' ); prompt.value = chip.getAttribute( 'data-example' ) || ''; prompt.focus(); } );
		} );
		window.addEventListener( 'beforeunload', function () { if ( recorder && recorder.state === 'recording' ) { stopRecording(); } } );
	}

	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
}() );
