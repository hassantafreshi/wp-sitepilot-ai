/* global SitePilotAI */
( function () {
	'use strict';

	var t = function ( s ) {
		if ( window.wp && window.wp.i18n && window.wp.i18n.__ ) {
			return window.wp.i18n.__( s, 'sitepilot-ai' );
		}
		return s;
	};

	var cfg = window.SitePilotAI || {};
	var currentActionId = null;

	function $( id ) {
		return document.getElementById( id );
	}

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		attrs = attrs || {};
		Object.keys( attrs ).forEach( function ( k ) {
			if ( k === 'class' ) {
				node.className = attrs[ k ];
			} else if ( k === 'text' ) {
				node.textContent = attrs[ k ];
			} else if ( k === 'html' ) {
				node.innerHTML = attrs[ k ];
			} else {
				node.setAttribute( k, attrs[ k ] );
			}
		} );
		( children || [] ).forEach( function ( c ) {
			if ( c ) {
				node.appendChild( typeof c === 'string' ? document.createTextNode( c ) : c );
			}
		} );
		return node;
	}

	function setStatus( msg, kind ) {
		var s = $( 'sitepilot-status' );
		if ( ! s ) {
			return;
		}
		s.className = 'sitepilot-status' + ( kind ? ' sitepilot-status--' + kind : '' );
		s.textContent = '';
		if ( kind === 'loading' ) {
			s.appendChild( el( 'span', { class: 'sitepilot-spinner' } ) );
		}
		s.appendChild( document.createTextNode( msg ) );
	}

	function api( path, body ) {
		return fetch( cfg.restUrl + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce,
			},
			body: JSON.stringify( body ),
		} ).then( function ( r ) {
			return r.json().then( function ( data ) {
				return { status: r.status, data: data };
			} );
		} );
	}

	function riskPill( risk ) {
		var map = { low: 'ok', medium: 'warn', high: 'err' };
		return el( 'span', {
			class: 'sitepilot-pill sitepilot-pill--' + ( map[ risk ] || 'muted' ),
			text: t( 'Risk' ) + ': ' + risk,
		} );
	}

	function renderPreview( preview ) {
		var wrap = $( 'sitepilot-preview' );
		wrap.textContent = '';

		var head = el( 'div', { class: 'sitepilot-preview__head' }, [
			el( 'div', {}, [
				el( 'h2', { text: preview.title } ),
				preview.description ? el( 'p', { text: preview.description } ) : null,
			] ),
		] );
		wrap.appendChild( head );

		var meta = el( 'div', { class: 'sitepilot-meta' }, [
			el( 'span', { class: 'sitepilot-pill sitepilot-pill--info', text: preview.connector } ),
			el( 'span', {
				class: 'sitepilot-pill sitepilot-pill--muted',
				text: preview.field_count + ' ' + t( 'fields' ),
			} ),
			riskPill( preview.risk_level ),
			el( 'span', {
				class: 'sitepilot-pill sitepilot-pill--muted',
				text: preview.default_status === 'publish' ? t( 'Will be published' ) : t( 'Saved as draft' ),
			} ),
		] );
		wrap.appendChild( meta );

		var table = el( 'table', { class: 'sitepilot-fields' } );
		table.appendChild(
			el( 'thead', {}, [
				el( 'tr', {}, [
					el( 'th', { text: t( 'Label' ) } ),
					el( 'th', { text: t( 'Type' ) } ),
					el( 'th', { text: t( 'Required' ) } ),
					el( 'th', { text: t( 'Options' ) } ),
				] ),
			] )
		);
		var tbody = el( 'tbody' );
		preview.fields.forEach( function ( f ) {
			tbody.appendChild(
				el( 'tr', {}, [
					el( 'td', { text: f.label } ),
					el( 'td', {}, [ el( 'code', { text: f.type } ) ] ),
					el( 'td', { text: f.required ? t( 'Yes' ) : t( 'No' ) } ),
					el( 'td', { text: ( f.options || [] ).join( ', ' ) } ),
				] )
			);
		} );
		table.appendChild( tbody );
		wrap.appendChild( table );

		if ( preview.notes ) {
			wrap.appendChild( el( 'p', { class: 'description', text: preview.notes } ) );
		}

		var approve = el( 'button', {
			class: 'button button-primary',
			text: t( 'Approve & create form' ),
			id: 'sitepilot-approve',
		} );
		var cancel = el( 'button', { class: 'button', text: t( 'Cancel' ), id: 'sitepilot-cancel' } );
		var approveStatus = el( 'span', { class: 'sitepilot-status', id: 'sitepilot-approve-status' } );

		approve.addEventListener( 'click', onApprove );
		cancel.addEventListener( 'click', function () {
			wrap.hidden = true;
			currentActionId = null;
		} );

		wrap.appendChild( el( 'div', { class: 'sitepilot-preview__actions' }, [ approve, cancel, approveStatus ] ) );
		wrap.hidden = false;
		$( 'sitepilot-result' ).hidden = true;
	}

	function renderResult( result ) {
		var wrap = $( 'sitepilot-result' );
		wrap.textContent = '';

		wrap.appendChild(
			el( 'div', { class: 'sitepilot-result__head' }, [
				el( 'span', { class: 'dashicons dashicons-yes-alt', style: 'color:#15803d' } ),
				el( 'h2', { text: result.is_draft ? t( 'Form created as a draft' ) : t( 'Form created' ) } ),
			] )
		);

		if ( result.shortcode ) {
			var code = el( 'code', { text: result.shortcode } );
			var copy = el( 'button', { class: 'button', text: t( 'Copy shortcode' ) } );
			copy.addEventListener( 'click', function () {
				if ( navigator.clipboard ) {
					navigator.clipboard.writeText( result.shortcode );
					copy.textContent = t( 'Copied!' );
					setTimeout( function () {
						copy.textContent = t( 'Copy shortcode' );
					}, 1500 );
				}
			} );
			wrap.appendChild(
				el( 'div', {}, [
					el( 'p', { text: t( 'Paste this shortcode into any page or post to show the form:' ) } ),
					el( 'div', { class: 'sitepilot-shortcode' }, [ code, copy ] ),
				] )
			);
		}

		if ( result.edit_url ) {
			wrap.appendChild(
				el( 'p', {}, [ el( 'a', { class: 'button button-primary', href: result.edit_url, text: t( 'Open the form' ) } ) ] )
			);
		}

		wrap.hidden = false;
		$( 'sitepilot-preview' ).hidden = true;
	}

	function onGenerate() {
		var prompt = ( $( 'sitepilot-prompt' ).value || '' ).trim();
		if ( ! prompt ) {
			setStatus( t( 'Please describe the form first.' ), 'error' );
			return;
		}

		var btn = $( 'sitepilot-generate' );
		btn.disabled = true;
		setStatus( t( 'Thinking… drafting your form.' ), 'loading' );

		api( 'forms/plan', { prompt: prompt } )
			.then( function ( res ) {
				btn.disabled = false;
				if ( ! res.data.ok ) {
					var msg = res.data.message || t( 'Could not generate a form.' );
					if ( res.data.errors && res.data.errors.length ) {
						msg += ' — ' + res.data.errors.join( ' ' );
					}
					setStatus( msg, 'error' );
					return;
				}
				currentActionId = res.data.action_id;
				setStatus( t( 'Review the preview below, then approve.' ), 'ok' );
				renderPreview( res.data.preview );
			} )
			.catch( function () {
				btn.disabled = false;
				setStatus( t( 'Network error. Please try again.' ), 'error' );
			} );
	}

	function onApprove() {
		if ( ! currentActionId ) {
			return;
		}
		var approve = $( 'sitepilot-approve' );
		var st = $( 'sitepilot-approve-status' );
		approve.disabled = true;
		st.className = 'sitepilot-status';
		st.textContent = '';
		st.appendChild( el( 'span', { class: 'sitepilot-spinner' } ) );
		st.appendChild( document.createTextNode( t( 'Creating the form…' ) ) );

		api( 'forms/execute', { action_id: currentActionId } )
			.then( function ( res ) {
				if ( ! res.data.ok ) {
					st.className = 'sitepilot-status sitepilot-status--error';
					st.textContent = res.data.message || t( 'Creation failed.' );
					approve.disabled = false;
					return;
				}
				currentActionId = null;
				renderResult( res.data.result );
			} )
			.catch( function () {
				st.className = 'sitepilot-status sitepilot-status--error';
				st.textContent = t( 'Network error. Please try again.' );
				approve.disabled = false;
			} );
	}

	function init() {
		var gen = $( 'sitepilot-generate' );
		if ( gen ) {
			gen.addEventListener( 'click', onGenerate );
		}
		Array.prototype.forEach.call( document.querySelectorAll( '.sitepilot-chip' ), function ( chip ) {
			chip.addEventListener( 'click', function () {
				$( 'sitepilot-prompt' ).value = chip.getAttribute( 'data-example' ) || '';
				$( 'sitepilot-prompt' ).focus();
			} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
