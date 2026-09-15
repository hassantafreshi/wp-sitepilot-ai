/* global AISAAutoPilotAI */
/**
 * Channel-scoped controls for the mockup-aligned Answer Desk Human handoff tab.
 * Provider connection checks only run after an explicit administrator confirm.
 */
( function () {
	'use strict';

	var root = document.querySelector( '[data-sp-handoff-settings]' );
	if ( ! root ) { return; }

	var config = window.AISAAutoPilotAI || {};

	function strings() { return config.i18n || {}; }

	function state( enabled, configured ) {
		if ( enabled && configured ) { return { key: 'ready', label: 'Ready' }; }
		if ( enabled ) { return { key: 'needs-setup', label: 'Needs setup' }; }
		if ( configured ) { return { key: 'paused', label: 'Paused' }; }
		return { key: 'off', label: 'Not configured' };
	}

	function cardEnabled( card ) { return card.getAttribute( 'data-sp-handoff-enabled' ) === '1'; }
	function cardConfigured( card ) { return card.getAttribute( 'data-sp-handoff-configured' ) === '1'; }

	function statusElement( card ) { return card.querySelector( '[data-sp-handoff-status]' ); }
	function setStatus( card, message, kind ) {
		var node = statusElement( card );
		if ( ! node ) { return; }
		node.textContent = message || '';
		node.setAttribute( 'data-state', kind || '' );
	}

	function editButton( card ) { return card.querySelector( '[data-sp-handoff-edit]' ); }
	function defaultEditLabel( card ) { return cardEnabled( card ) || cardConfigured( card ) ? 'Edit' : 'Connect'; }

	function openCard( card, enableWhenSaved ) {
		var form = card.querySelector( '[data-sp-handoff-form]' );
		if ( ! form ) { return; }
		form.hidden = false;
		card.classList.add( 'is-open' );
		if ( enableWhenSaved ) { card.setAttribute( 'data-sp-handoff-pending-enable', '1' ); }
		var edit = editButton( card );
		if ( edit ) { edit.textContent = 'Close'; }
	}

	function closeCard( card, reset, keepStatus ) {
		var form = card.querySelector( '[data-sp-handoff-form]' );
		if ( ! form ) { return; }
		if ( reset ) { form.reset(); }
		form.hidden = true;
		card.classList.remove( 'is-open' );
		card.removeAttribute( 'data-sp-handoff-pending-enable' );
		var edit = editButton( card );
		if ( edit ) { edit.textContent = defaultEditLabel( card ); }
		if ( ! keepStatus ) { setStatus( card, '', '' ); }
	}

	function setBusy( card, busy ) {
		var elements = card.querySelectorAll( 'button, input' );
		Array.prototype.forEach.call( elements, function ( element ) {
			if ( element.hasAttribute( 'data-sp-handoff-test' ) && ! cardConfigured( card ) ) { return; }
			element.disabled = busy;
		} );
	}

	function payload( card, enabled ) {
		var form = card.querySelector( '[data-sp-handoff-form]' );
		var values = {
			channel: card.getAttribute( 'data-sp-handoff-channel' ),
			enabled: enabled
		};
		if ( ! form ) { return values; }

		new FormData( form ).forEach( function ( value, key ) {
			values[ key ] = value;
		} );
		return values;
	}

	function summary( card, channel ) {
		var form = card.querySelector( '[data-sp-handoff-form]' );
		if ( ! form || ! channel.configured ) { return 'Not set up yet'; }
		if ( channel.id === 'email' ) {
			var recipient = form.elements.email_to ? form.elements.email_to.value.trim() : '';
			return recipient || 'Uses the WordPress administration email';
		}
		if ( channel.id === 'telegram' ) {
			return 'Credential stored securely; chat ' + ( form.elements.telegram_chat ? form.elements.telegram_chat.value.trim() : '' );
		}
		return 'Credentials stored securely; sends to ' + ( form.elements.twilio_to ? form.elements.twilio_to.value.trim() : '' );
	}

	function apply( card, result ) {
		var channel = result.channel || {};
		var enabled = !!channel.enabled;
		var configured = !!channel.configured;
		var visual = state( enabled, configured );
		card.setAttribute( 'data-sp-handoff-enabled', enabled ? '1' : '0' );
		card.setAttribute( 'data-sp-handoff-configured', configured ? '1' : '0' );
		root.setAttribute( 'data-sp-handoff-available', result.handoff_available ? '1' : '0' );
		[ 'is-ready', 'is-needs-setup', 'is-paused', 'is-off' ].forEach( function ( className ) { card.classList.remove( className ); } );
		card.classList.add( 'is-' + visual.key );

		var switcher = card.querySelector( '[data-sp-handoff-toggle]' );
		if ( switcher ) {
			switcher.classList.toggle( 'is-on', enabled );
			switcher.setAttribute( 'aria-checked', enabled ? 'true' : 'false' );
		}
		var badge = card.querySelector( '[data-sp-handoff-state]' );
		if ( badge ) {
			badge.textContent = visual.label;
			badge.className = 'sp-answer-handoff-state is-' + visual.key;
		}
		var summaryNode = card.querySelector( '[data-sp-handoff-summary]' );
		if ( summaryNode ) { summaryNode.textContent = summary( card, channel ); }
		var test = card.querySelector( '[data-sp-handoff-test]' );
		if ( test ) { test.disabled = ! configured; }
		var form = card.querySelector( '[data-sp-handoff-form]' );
		if ( form ) {
			Array.prototype.forEach.call( form.querySelectorAll( 'input[type="password"]' ), function ( input ) {
				if ( input.value ) { input.value = ''; }
			} );
		}
	}

	function save( card, enabled ) {
		if ( ! config.restUrl || ! config.nonce ) {
			setStatus( card, strings().handoffSaveFailed || 'Could not save the handoff channel. Please try again.', 'error' );
			return Promise.reject( new Error( 'missing_rest_configuration' ) );
		}

		// FormData intentionally omits disabled controls, so read the scoped
		// payload before marking the card busy.
		var values = payload( card, enabled );
		setBusy( card, true );
		setStatus( card, strings().handoffSaving || 'Saving...', 'busy' );
		return window.fetch( config.restUrl + 'handoff/admin-settings', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce
			},
			body: JSON.stringify( values )
		} ).then( function ( response ) {
			return response.json().catch( function () { return {}; } ).then( function ( data ) {
				if ( ! response.ok || ! data.ok ) { throw new Error( data.message || 'handoff_save_failed' ); }
				return data;
			} );
		} ).then( function ( data ) {
			apply( card, data );
			card.removeAttribute( 'data-sp-handoff-pending-enable' );
			setStatus( card, strings().handoffSaved || 'Saved.', 'saved' );
			return data;
		} ).catch( function ( error ) {
			setStatus( card, error.message || strings().handoffSaveFailed || 'Could not save the handoff channel. Please try again.', 'error' );
			throw error;
		} ).finally( function () {
			setBusy( card, false );
		} );
	}

	function testConnection( button ) {
		var card = button.closest( '[data-sp-handoff-card]' );
		var url = button.getAttribute( 'data-sp-handoff-test-url' );
		if ( ! card || ! url || ! config.nonce || ! window.confirm( strings().handoffConnectionPrompt || 'This contacts the provider to validate the saved credential. Continue?' ) ) { return; }
		setBusy( card, true );
		setStatus( card, 'Checking connection...', 'busy' );
		window.fetch( url, {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': config.nonce }
		} ).then( function ( response ) {
			return response.json().catch( function () { return {}; } ).then( function ( data ) {
				if ( ! response.ok || ! data.ok ) { throw new Error( data.message || 'handoff_connection_failed' ); }
				return data;
			} );
		} ).then( function ( data ) {
			setStatus( card, data.message || 'Connection verified.', 'saved' );
		} ).catch( function ( error ) {
			setStatus( card, error.message || strings().handoffConnectionFailed || 'Could not check the connection. Please try again.', 'error' );
		} ).finally( function () {
			setBusy( card, false );
		} );
	}

	root.addEventListener( 'click', function ( event ) {
		var toggle = event.target.closest( '[data-sp-handoff-toggle]' );
		if ( toggle ) {
			var toggleCard = toggle.closest( '[data-sp-handoff-card]' );
			if ( ! toggleCard ) { return; }
			if ( ! cardConfigured( toggleCard ) && ! cardEnabled( toggleCard ) ) {
				openCard( toggleCard, true );
				return;
			}
			save( toggleCard, ! cardEnabled( toggleCard ) ).catch( function () {} );
			return;
		}

		var edit = event.target.closest( '[data-sp-handoff-edit]' );
		if ( edit ) {
			var editCard = edit.closest( '[data-sp-handoff-card]' );
			if ( ! editCard ) { return; }
			if ( editCard.classList.contains( 'is-open' ) ) {
				closeCard( editCard, true );
			} else {
				openCard( editCard, ! cardConfigured( editCard ) && ! cardEnabled( editCard ) );
			}
			return;
		}

		var cancel = event.target.closest( '[data-sp-handoff-cancel]' );
		if ( cancel ) {
			var cancelCard = cancel.closest( '[data-sp-handoff-card]' );
			if ( cancelCard ) { closeCard( cancelCard, true ); }
			return;
		}

		var add = event.target.closest( '[data-sp-handoff-add="twilio"]' );
		if ( add ) {
			var twilio = root.querySelector( '[data-sp-handoff-card][data-sp-handoff-channel="twilio"]' );
			if ( twilio ) {
				twilio.hidden = false;
				add.hidden = true;
				openCard( twilio, true );
			}
			return;
		}

		var test = event.target.closest( '[data-sp-handoff-test]' );
		if ( test && ! test.disabled ) { testConnection( test ); }
	} );

	root.addEventListener( 'submit', function ( event ) {
		var form = event.target.closest( '[data-sp-handoff-form]' );
		if ( ! form ) { return; }
		event.preventDefault();
		var card = form.closest( '[data-sp-handoff-card]' );
		if ( ! card ) { return; }
		var enabled = cardEnabled( card ) || card.getAttribute( 'data-sp-handoff-pending-enable' ) === '1';
		save( card, enabled ).catch( function () {} );
	} );
}() );
