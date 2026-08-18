/* global AISAAutoPilotAI */
( function () {
	'use strict';

	var t = function ( s ) {
		if ( window.wp && window.wp.i18n && window.wp.i18n.__ ) {
			return window.wp.i18n.__( s, 'ai-site-assistant-autopilot' );
		}
		return s;
	};

	var cfg = window.AISAAutoPilotAI || {};
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
		var s = $( 'aisaautopilot-status' );
		if ( ! s ) {
			return;
		}
		s.className = 'aisaautopilot-status' + ( kind ? ' aisaautopilot-status--' + kind : '' );
		s.textContent = '';
		if ( kind === 'loading' ) {
			s.appendChild( el( 'span', { class: 'aisaautopilot-spinner' } ) );
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

	/* ── Credit / Usage Warning Banner ── */
	function renderCreditWarning() {
		var usage = cfg.usage;
		if ( ! usage || ! usage.warning ) {
			return;
		}

		var w = usage.warning;
		var wrap = $( 'aisaautopilot-credit-warning' );
		if ( ! wrap ) {
			// Create the warning container after the subtitle.
			var subtitle = document.querySelector( '.aisaautopilot-subtitle' );
			if ( ! subtitle ) { return; }
			wrap = el( 'div', { id: 'aisaautopilot-credit-warning', class: 'aisaautopilot-banner aisaautopilot-banner--' + w.level } );
			subtitle.parentNode.insertBefore( wrap, subtitle.nextSibling );
		}

		wrap.textContent = '';
		wrap.appendChild( el( 'span', { class: 'dashicons dashicons-info' } ) );
		wrap.appendChild( el( 'span', { text: ' ' + w.message } ) );

		// Add a progress bar showing usage.
		var barWrap = el( 'div', { class: 'aisaautopilot-credit-bar-wrap' } );
		var bar = el( 'div', { class: 'aisaautopilot-credit-bar aisaautopilot-credit-bar--' + w.level } );
		bar.style.width = Math.min( 100, w.percent ) + '%';
		barWrap.appendChild( bar );
		wrap.appendChild( barWrap );
	}

	function riskPill( risk ) {
		var map = { low: 'ok', medium: 'warn', high: 'err' };
		return el( 'span', {
			class: 'aisaautopilot-pill aisaautopilot-pill--' + ( map[ risk ] || 'muted' ),
			text: t( 'Risk' ) + ': ' + risk,
		} );
	}

	function renderPreview( preview ) {
		var wrap = $( 'aisaautopilot-preview' );
		wrap.textContent = '';

		var head = el( 'div', { class: 'aisaautopilot-preview__head' }, [
			el( 'div', {}, [
				el( 'h2', { text: preview.title } ),
				preview.description ? el( 'p', { text: preview.description } ) : null,
			] ),
		] );
		wrap.appendChild( head );

		var meta = el( 'div', { class: 'aisaautopilot-meta' }, [
			el( 'span', { class: 'aisaautopilot-pill aisaautopilot-pill--info', text: preview.connector } ),
			el( 'span', {
				class: 'aisaautopilot-pill aisaautopilot-pill--muted',
				text: preview.field_count + ' ' + t( 'fields' ),
			} ),
			riskPill( preview.risk_level ),
			el( 'span', {
				class: 'aisaautopilot-pill aisaautopilot-pill--muted',
				text: preview.default_status === 'publish' ? t( 'Will be published' ) : t( 'Saved as draft' ),
			} ),
		] );
		wrap.appendChild( meta );

		var table = el( 'table', { class: 'aisaautopilot-fields' } );
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
		( preview.fields || [] ).forEach( function ( f ) {
			tbody.appendChild(
				el( 'tr', {}, [
					el( 'td', { text: f.label } ),
					el( 'td', {}, [ el( 'code', { text: f.type } ) ] ),
					el( 'td', { text: f.required ? '✓' : '—' } ),
					el( 'td', { text: ( f.options || [] ).join( ', ' ) || '—' } ),
				] )
			);
		} );
		table.appendChild( tbody );
		wrap.appendChild( table );

		if ( preview.notes && preview.notes.length ) {
			wrap.appendChild(
				el( 'div', { class: 'aisaautopilot-notes' }, [
					el( 'strong', { text: t( 'Notes' ) + ': ' } ),
					el( 'span', { text: preview.notes.join( ' ' ) } ),
				] )
			);
		}

		var actions = el( 'div', { class: 'aisaautopilot-preview__actions' }, [
			el( 'button', {
				id: 'aisaautopilot-approve',
				class: 'button button-primary button-hero',
				text: '✓ ' + t( 'Approve & Create' ),
			} ),
			el( 'button', {
				id: 'aisaautopilot-cancel',
				class: 'button button-secondary',
				text: t( 'Cancel' ),
			} ),
			el( 'span', { id: 'aisaautopilot-approve-status', class: 'aisaautopilot-status', role: 'status' } ),
		] );
		wrap.appendChild( actions );

		wrap.hidden = false;
		$( 'aisaautopilot-result' ).hidden = true;

		$( 'aisaautopilot-approve' ).addEventListener( 'click', onApprove );
		$( 'aisaautopilot-cancel' ).addEventListener( 'click', function () {
			wrap.hidden = true;
			currentActionId = null;
			setStatus( t( 'Cancelled. You can try a different description.' ), '' );
		} );
	}

	function renderResult( result ) {
		var wrap = $( 'aisaautopilot-result' );
		wrap.textContent = '';
		wrap.appendChild(
			el( 'div', { class: 'aisaautopilot-result__head' }, [
				el( 'span', { class: 'dashicons dashicons-yes-alt aisaautopilot-result__icon' } ),
				el( 'h2', { text: t( 'Form created successfully!' ) } ),
			] )
		);

		if ( result.shortcode ) {
			var code = el( 'code', { text: result.shortcode } );
			var copy = el( 'button', { class: 'button button-small', text: t( 'Copy shortcode' ) } );
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
					el( 'div', { class: 'aisaautopilot-shortcode' }, [ code, copy ] ),
				] )
			);
		}

		if ( result.edit_url ) {
			wrap.appendChild(
				el( 'p', {}, [ el( 'a', { class: 'button button-primary', href: result.edit_url, text: t( 'Open the form' ) } ) ] )
			);
		}

		wrap.hidden = false;
		$( 'aisaautopilot-preview' ).hidden = true;
	}

	function onGenerate() {
		var prompt = ( $( 'aisaautopilot-prompt' ).value || '' ).trim();
		if ( ! prompt ) {
			setStatus( t( 'Please describe the form first.' ), 'error' );
			return;
		}

		var btn = $( 'aisaautopilot-generate' );
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
		var approve = $( 'aisaautopilot-approve' );
		var st = $( 'aisaautopilot-approve-status' );
		approve.disabled = true;
		st.className = 'aisaautopilot-status';
		st.textContent = '';
		st.appendChild( el( 'span', { class: 'aisaautopilot-spinner' } ) );
		st.appendChild( document.createTextNode( t( 'Creating the form…' ) ) );

		api( 'forms/execute', { action_id: currentActionId } )
			.then( function ( res ) {
				if ( ! res.data.ok ) {
					st.className = 'aisaautopilot-status aisaautopilot-status--error';
					st.textContent = res.data.message || t( 'Creation failed.' );
					approve.disabled = false;
					return;
				}
				currentActionId = null;
				renderResult( res.data.result );
			} )
			.catch( function () {
				st.className = 'aisaautopilot-status aisaautopilot-status--error';
				st.textContent = t( 'Network error. Please try again.' );
				approve.disabled = false;
			} );
	}

	function init() {
		var gen = $( 'aisaautopilot-generate' );
		if ( gen ) {
			gen.addEventListener( 'click', onGenerate );
		}
		Array.prototype.forEach.call( document.querySelectorAll( '.aisaautopilot-chip' ), function ( chip ) {
			chip.addEventListener( 'click', function () {
				$( 'aisaautopilot-prompt' ).value = chip.getAttribute( 'data-example' ) || '';
				$( 'aisaautopilot-prompt' ).focus();
			} );
		} );

		// Render credit warning if applicable.
		renderCreditWarning();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
