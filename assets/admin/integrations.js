( function () {
	'use strict';

	var root = document.querySelector( '[data-sp-integrations]' );
	if ( ! root ) {
		return;
	}

	var dialogs = Array.prototype.slice.call( root.querySelectorAll( '[data-sp-integration-dialog]' ) );
	var triggerByDialog = new Map();

	function dialogFor( id ) {
		return dialogs.find( function ( dialog ) {
			return dialog.getAttribute( 'data-sp-integration-dialog' ) === id;
		} );
	}

	function focusCloseButton( dialog ) {
		var closeButton = dialog.querySelector( '[data-sp-integration-close]' );
		if ( closeButton ) {
			closeButton.focus();
		}
	}

	function openDialog( dialog, trigger ) {
		if ( ! dialog ) {
			return;
		}

		triggerByDialog.set( dialog, trigger );
		if ( typeof dialog.showModal === 'function' ) {
			if ( ! dialog.open ) {
				dialog.showModal();
			}
		} else {
			dialog.setAttribute( 'open', 'open' );
		}
		focusCloseButton( dialog );
	}

	function closeDialog( dialog ) {
		if ( ! dialog ) {
			return;
		}

		if ( typeof dialog.close === 'function' && dialog.open ) {
			dialog.close();
		} else {
			dialog.removeAttribute( 'open' );
		}

		var trigger = triggerByDialog.get( dialog );
		if ( trigger ) {
			trigger.focus();
		}
	}

	root.addEventListener( 'click', function ( event ) {
		var trigger = event.target.closest( '[data-sp-integration-open]' );
		if ( trigger && root.contains( trigger ) ) {
			event.preventDefault();
			openDialog( dialogFor( trigger.getAttribute( 'data-sp-integration-open' ) ), trigger );
			return;
		}

		var close = event.target.closest( '[data-sp-integration-close]' );
		if ( close && root.contains( close ) ) {
			event.preventDefault();
			closeDialog( close.closest( '[data-sp-integration-dialog]' ) );
			return;
		}

		if ( event.target.matches( '[data-sp-integration-dialog]' ) ) {
			closeDialog( event.target );
		}
	} );

	dialogs.forEach( function ( dialog ) {
		dialog.addEventListener( 'cancel', function ( event ) {
			event.preventDefault();
			closeDialog( dialog );
		} );
		dialog.addEventListener( 'close', function () {
			var trigger = triggerByDialog.get( dialog );
			if ( trigger && document.activeElement === document.body ) {
				trigger.focus();
			}
		} );
	} );
}() );
