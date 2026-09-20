/**
 * Advanced Widget settings: every control drives the live preview immediately, and a
 * save bar appears as soon as something differs from the saved settings.
 */
( function () {
	'use strict';

	var stage = document.getElementById( 'sp-w-stage' );
	var root = document.querySelector( '[data-sp-widget]' );
	var form = root ? root.querySelector( '[data-sp-widget-form]' ) : null;
	if ( ! stage || ! form ) { return; }

	var T = readJson( 'sp-wz-i18n' );
	var dirty = false;

	function readJson( id ) {
		try {
			var node = document.getElementById( id );
			return node ? ( JSON.parse( node.textContent || '{}' ) || {} ) : {};
		} catch ( e ) { return {}; }
	}

	function t( key, fallback ) { return T[ key ] || fallback; }

	function $( id ) { return document.getElementById( id ); }

	function each( selector, callback ) {
		Array.prototype.forEach.call( form.querySelectorAll( selector ), callback );
	}

	/** Brand-derived palette, mirroring Settings::widget_derived_colors(). */
	function derived( brand, theme ) {
		var dark = theme === 'dark';
		return {
			head_from: brand,
			head_to: '#181f66',
			user: brand,
			send: brand,
			bubble: brand,
			handoff: brand,
			panel: dark ? '#1f2937' : '#ffffff',
			msgs: dark ? '#111827' : '#f9fafb',
			ai_bg: dark ? '#1f2937' : '#ffffff',
			ai_text: dark ? '#f3f4f6' : '#1f2937',
			input: dark ? '#111827' : '#f9fafb',
			border: dark ? '#374151' : '#e5e7eb',
		};
	}

	var CSS_VAR = {
		head_from: '--wc-head-from',
		head_to: '--wc-head-to',
		user: '--wc-user',
		ai_bg: '--wc-ai-bg',
		ai_text: '--wc-ai-text',
		panel: '--wc-panel',
		msgs: '--wc-msgs',
		border: '--wc-border',
		input: '--wc-input',
		send: '--wc-send',
		handoff: '--wc-handoff',
		bubble: '--wc-bubble',
	};

	var RANGE_VAR = {
		panel_radius: '--wp-radius',
		msg_radius: '--wp-msg-radius',
	};

	/* ── Dirty state and the save bar ── */
	function markDirty() {
		if ( dirty ) { return; }
		dirty = true;
		var bar = form.querySelector( '[data-sp-savebar]' );
		if ( bar ) { bar.hidden = false; }
	}

	form.addEventListener( 'input', markDirty );
	form.addEventListener( 'change', markDirty );
	form.addEventListener( 'submit', function () { dirty = false; } );
	window.addEventListener( 'beforeunload', function ( event ) {
		if ( ! dirty ) { return; }
		event.preventDefault();
		event.returnValue = '';
	} );

	var discard = form.querySelector( '[data-sp-discard]' );
	if ( discard ) {
		discard.addEventListener( 'click', function () {
			var shell = window.AISAAutoPilotShell;
			var ask = shell && 'function' === typeof shell.confirm
				? shell.confirm( {
					title: t( 'discardTitle', 'Discard your changes?' ),
					body: t( 'discardBody', 'The widget goes back to its last saved settings. Nothing is sent to visitors.' ),
					confirmLabel: t( 'discardCta', 'Discard changes' ),
					danger: true,
				} )
				: Promise.resolve( window.confirm( t( 'discardTitle', 'Discard your changes?' ) ) );
			ask.then( function ( yes ) {
				if ( ! yes ) { return; }
				dirty = false;
				// A GET of the same address, so a page shown after saving is never posted again.
				window.location.href = window.location.href.split( '#' )[ 0 ];
			} );
		} );
	}

	/* ── Preview helpers ── */
	function out( name, value ) {
		Array.prototype.forEach.call( stage.querySelectorAll( '[data-sp-out="' + name + '"]' ), function ( node ) {
			node.textContent = value;
		} );
	}

	/** Suggested questions render as a list of chips, one per non-empty line. */
	function outSuggestions( value ) {
		var list = stage.querySelector( '[data-sp-out="suggestions"]' );
		if ( ! list ) { return; }
		list.textContent = '';
		String( value || '' ).split( /\r?\n/ ).forEach( function ( line ) {
			line = line.trim();
			if ( ! line || list.children.length >= 4 ) { return; }
			var chip = document.createElement( 'span' );
			chip.className = 'sp-wwelcome__chip';
			chip.textContent = line.slice( 0, 90 );
			list.appendChild( chip );
		} );
	}

	function setColor( key, value ) {
		if ( CSS_VAR[ key ] ) { stage.style.setProperty( CSS_VAR[ key ], value ); }
	}

	function showHex( key, value ) {
		var node = form.querySelector( '[data-sp-color-hex="' + key + '"]' );
		if ( node ) { node.textContent = value; }
	}

	function currentTheme() {
		var checked = form.querySelector( 'input[name="widget[theme]"]:checked' );
		return checked ? checked.value : 'light';
	}

	function currentBrand() {
		var field = $( 'sp-w-brand' );
		return field ? field.value : '#202a8d';
	}

	function customOn() {
		var flag = $( 'sp-w-custom-flag' );
		return !! flag && '1' === flag.value;
	}

	function setCustom( on ) {
		var flag = $( 'sp-w-custom-flag' );
		var hint = $( 'sp-w-custom-hint' );
		if ( flag ) { flag.value = on ? '1' : '0'; }
		if ( hint ) { hint.textContent = hint.getAttribute( on ? 'data-custom-text' : 'data-derived-text' ) || hint.textContent; }
	}

	/** Recompute every colour from brand and theme, unless parts have their own colours. */
	function repaint() {
		var palette = derived( currentBrand(), currentTheme() );
		var custom = customOn();
		stage.setAttribute( 'data-theme', currentTheme() );
		Object.keys( palette ).forEach( function ( key ) {
			var input = form.querySelector( '[data-sp-color="' + key + '"]' );
			var value = custom && input ? input.value : palette[ key ];
			if ( ! custom && input ) { input.value = palette[ key ]; }
			setColor( key, value );
			showHex( key, value );
		} );
		stage.style.setProperty( '--wc-mic', currentTheme() === 'dark' ? '#374151' : '#ffffff' );
		stage.style.setProperty( '--wc-muted', currentTheme() === 'dark' ? '#9ca3af' : '#6b7280' );
	}

	function selectBrand( value ) {
		var field = $( 'sp-w-brand' );
		var picker = form.querySelector( '[data-sp-brand-custom]' );
		if ( field ) { field.value = value; }
		if ( picker && picker.value.toLowerCase() !== value.toLowerCase() ) { picker.value = value; }
		each( '[data-sp-brand]', function ( swatch ) {
			var on = swatch.getAttribute( 'data-sp-brand' ).toLowerCase() === value.toLowerCase();
			swatch.classList.toggle( 'is-on', on );
			swatch.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
		} );
		// A brand colour drives every part again.
		setCustom( false );
		repaint();
		markDirty();
	}

	/* ── Text bindings ── */
	each( '[data-sp-bind]', function ( input ) {
		var name = input.getAttribute( 'data-sp-bind' );
		input.addEventListener( 'input', function () {
			if ( 'suggestions' === name ) {
				outSuggestions( input.value );
			} else {
				out( name, input.value );
			}
		} );
	} );

	/* ── Position, theme and button shape ── */
	each( '[data-sp-radio-group]', function ( group ) {
		group.addEventListener( 'change', function ( event ) {
			var input = event.target;
			if ( ! input || ! input.name ) { return; }
			Array.prototype.forEach.call( group.querySelectorAll( '.sp-wz-choice' ), function ( label ) {
				var radio = label.querySelector( 'input' );
				label.classList.toggle( 'is-on', !! ( radio && radio.checked ) );
			} );
			if ( input.hasAttribute( 'data-sp-pos' ) ) {
				stage.setAttribute( 'data-pos', input.getAttribute( 'data-sp-pos' ) );
			}
			if ( input.hasAttribute( 'data-sp-theme' ) ) {
				repaint();
			}
			if ( input.hasAttribute( 'data-sp-shape' ) ) {
				stage.style.setProperty( '--wp-bubble-radius', input.value + 'px' );
			}
		} );
	} );

	/* ── Colours ── */
	each( '[data-sp-brand]', function ( swatch ) {
		swatch.addEventListener( 'click', function () { selectBrand( swatch.getAttribute( 'data-sp-brand' ) ); } );
	} );

	var picker = form.querySelector( '[data-sp-brand-custom]' );
	if ( picker ) {
		picker.addEventListener( 'input', function () { selectBrand( picker.value ); } );
	}

	each( '[data-sp-color]', function ( input ) {
		input.addEventListener( 'input', function () {
			var key = input.getAttribute( 'data-sp-color' );
			setCustom( true );
			setColor( key, input.value );
			showHex( key, input.value );
		} );
	} );

	var reset = $( 'sp-w-reset-colors' );
	if ( reset ) {
		reset.addEventListener( 'click', function () {
			setCustom( false );
			repaint();
			markDirty();
		} );
	}

	/* ── Corners ── */
	each( '[data-sp-range]', function ( input ) {
		input.addEventListener( 'input', function () {
			var key = input.getAttribute( 'data-sp-range' );
			if ( RANGE_VAR[ key ] ) { stage.style.setProperty( RANGE_VAR[ key ], input.value + 'px' ); }
			var label = form.querySelector( '[data-sp-out="' + key + '"]' );
			if ( label ) { label.textContent = input.value + 'px'; }
		} );
	} );

	/* ── Recording and privacy ── */
	var recording = form.querySelector( '[data-sp-recording]' );
	if ( recording ) {
		recording.addEventListener( 'change', function () {
			var on = recording.checked;
			var notice = stage.querySelector( '[data-sp-region="notice"]' );
			var note = $( 'sp-wz-recording-note' );
			var field = form.querySelector( '[data-sp-notice-field]' );
			if ( notice ) { notice.hidden = ! on; }
			if ( note ) { note.textContent = note.getAttribute( on ? 'data-on-text' : 'data-off-text' ) || note.textContent; }
			if ( field ) { field.classList.toggle( 'is-muted', ! on ); }
		} );
	}

	/* ── Preview size (preview only, never saved) ── */
	Array.prototype.forEach.call( root.querySelectorAll( '[data-sp-device]' ), function ( button ) {
		button.addEventListener( 'click', function () {
			var device = button.getAttribute( 'data-sp-device' );
			stage.setAttribute( 'data-device', device );
			Array.prototype.forEach.call( root.querySelectorAll( '[data-sp-device]' ), function ( other ) {
				var on = other === button;
				other.classList.toggle( 'is-on', on );
				other.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			} );
		} );
	} );

	/* ── Icon drop slots ── */
	each( '[data-sp-slot]', function ( slot ) {
		var file = slot.querySelector( 'input[type="file"]' );
		var value = slot.querySelector( '[data-sp-slot-value]' );
		var img = slot.querySelector( '[data-sp-slot-img]' );
		var clear = slot.querySelector( '.sp-slot__clear' );
		var target = slot.getAttribute( 'data-sp-slot' ).replace( /^widget\[icons]\[|]$/g, '' );

		// Remember the built-in icon so removing an upload restores it.
		Array.prototype.forEach.call( stage.querySelectorAll( '[data-sp-icon="' + target + '"]' ), function ( host ) {
			if ( ! host.getAttribute( 'data-fallback' ) && ! host.querySelector( 'img' ) ) {
				host.setAttribute( 'data-fallback', host.innerHTML );
			}
		} );

		function apply( dataUrl ) {
			value.value = dataUrl || '';
			slot.classList.toggle( 'is-filled', !! dataUrl );
			if ( dataUrl ) { img.src = dataUrl; img.hidden = false; } else { img.removeAttribute( 'src' ); img.hidden = true; }

			Array.prototype.forEach.call( stage.querySelectorAll( '[data-sp-icon="' + target + '"]' ), function ( host ) {
				if ( dataUrl ) {
					host.textContent = '';
					var preview = document.createElement( 'img' );
					preview.src = dataUrl;
					preview.alt = '';
					if ( 'handoff' === target ) { preview.style.cssText = 'width:14px;height:14px;object-fit:contain'; }
					host.appendChild( preview );
				} else if ( host.getAttribute( 'data-fallback' ) ) {
					host.innerHTML = host.getAttribute( 'data-fallback' );
				}
			} );
			markDirty();
		}

		function read( picked ) {
			if ( ! picked || ! /^image\//.test( picked.type ) ) { return; }
			if ( picked.size > 200 * 1024 ) {
				window.alert( t( 'iconTooLarge', 'That image is too large. Please use a PNG under 200 KB.' ) );
				return;
			}
			var reader = new FileReader();
			reader.onload = function () { apply( String( reader.result ) ); };
			reader.readAsDataURL( picked );
		}

		if ( file ) { file.addEventListener( 'change', function () { read( file.files && file.files[ 0 ] ); } ); }

		[ 'dragenter', 'dragover' ].forEach( function ( type ) {
			slot.addEventListener( type, function ( e ) { e.preventDefault(); slot.classList.add( 'is-over' ); } );
		} );
		[ 'dragleave', 'drop' ].forEach( function ( type ) {
			slot.addEventListener( type, function ( e ) { e.preventDefault(); slot.classList.remove( 'is-over' ); } );
		} );
		slot.addEventListener( 'drop', function ( e ) {
			if ( e.dataTransfer && e.dataTransfer.files ) { read( e.dataTransfer.files[ 0 ] ); }
		} );

		if ( clear ) {
			clear.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				if ( file ) { file.value = ''; }
				apply( '' );
			} );
		}
	} );

	/* ── Pages where the chat appears ── */
	var where = form.querySelector( '[data-sp-visibility]' );
	if ( where ) { initWhere( where ); }

	function initWhere( card ) {
		var rules = card.querySelector( '[data-sp-visibility-rules]' );
		var none = card.querySelector( '[data-sp-visibility-none]' );
		var search = card.querySelector( '[data-sp-where-search]' );
		var results = card.querySelector( '[data-sp-where-results]' );
		var status = card.querySelector( '[data-sp-where-status]' );
		var picked = card.querySelector( '[data-sp-where-picked]' );
		var empty = card.querySelector( '[data-sp-where-empty]' );
		var url = card.getAttribute( 'data-sp-where-url' ) || '';
		var max = parseInt( card.getAttribute( 'data-sp-where-max' ), 10 ) || 200;
		var typeLabels = readJson( 'sp-wz-where-types' );
		var config = window.AISAAutoPilotAI || {};
		var timer = null;
		var latest = 0;

		function items() { return picked.querySelectorAll( '[data-sp-where-item]' ); }

		function pickedIds() {
			return Array.prototype.map.call( items(), function ( item ) { return item.getAttribute( 'data-sp-where-item' ); } );
		}

		function sync() {
			var except = !! card.querySelector( 'input[name="widget[widget_visibility]"][value="exclude"]:checked' );
			var chosen = items().length > 0 || !! card.querySelector( 'input[name="widget[exclude_types][]"]:checked, input[name="widget[exclude_cats][]"]:checked' );
			rules.hidden = ! except;
			none.hidden = ! except || chosen;
			empty.hidden = items().length > 0;
		}

		function say( message ) { status.textContent = message || ''; }

		/** Titles from the search route are HTML; read them as text without running anything. */
		function plain( html ) {
			var doc = new window.DOMParser().parseFromString( '<!doctype html><body>' + String( html || '' ), 'text/html' );
			return ( doc.body.textContent || '' ).trim();
		}

		function addItem( id, title, type ) {
			if ( pickedIds().indexOf( String( id ) ) !== -1 ) { return false; }
			if ( items().length >= max ) { say( t( 'whereMax', '' ) ); return false; }

			var item = document.createElement( 'li' );
			item.className = 'sp-wz-where__item';
			item.setAttribute( 'data-sp-where-item', String( id ) );

			var name = document.createElement( 'span' );
			name.className = 'sp-wz-where__name';
			name.textContent = title;

			var kind = document.createElement( 'span' );
			kind.className = 'sp-wz-where__type';
			kind.textContent = type;

			var input = document.createElement( 'input' );
			input.type = 'hidden';
			input.name = 'widget[exclude_ids][]';
			input.value = String( id );

			var remove = document.createElement( 'button' );
			remove.type = 'button';
			remove.className = 'sp-wz-where__remove';
			remove.setAttribute( 'data-sp-where-remove', '' );
			remove.setAttribute( 'aria-label', t( 'whereRemove', 'Show the chat again on %s' ).replace( '%s', title ) );
			var cross = document.createElement( 'span' );
			cross.setAttribute( 'aria-hidden', 'true' );
			cross.textContent = '×';
			remove.appendChild( cross );

			item.appendChild( name );
			item.appendChild( kind );
			item.appendChild( input );
			item.appendChild( remove );
			picked.appendChild( item );
			markDirty();
			sync();
			return true;
		}

		function showResults( list ) {
			results.textContent = '';
			var shown = 0;
			( Array.isArray( list ) ? list : [] ).forEach( function ( hit ) {
				var id = parseInt( hit && hit.id, 10 );
				if ( ! id ) { return; }
				var title = plain( hit.title ) || t( 'whereNoTitle', '(no title)' );
				var type = typeLabels[ hit.subtype ] || String( hit.subtype || '' );
				var done = pickedIds().indexOf( String( id ) ) !== -1;

				var row = document.createElement( 'li' );
				var button = document.createElement( 'button' );
				button.type = 'button';
				button.className = 'sp-wz-where__hit';
				button.setAttribute( 'data-sp-where-hit', String( id ) );
				button.disabled = done;

				var name = document.createElement( 'span' );
				name.className = 'sp-wz-where__name';
				name.textContent = title;
				var kind = document.createElement( 'span' );
				kind.className = 'sp-wz-where__type';
				kind.textContent = type;
				var action = document.createElement( 'span' );
				action.className = 'sp-wz-where__action';
				action.textContent = done ? t( 'whereAdded', 'Hidden' ) : t( 'whereAdd', 'Hide here' );

				button.appendChild( name );
				button.appendChild( kind );
				button.appendChild( action );
				button.addEventListener( 'click', function () {
					if ( ! addItem( id, title, type ) ) { return; }
					button.disabled = true;
					action.textContent = t( 'whereAdded', 'Hidden' );
					say( t( 'whereAddedOn', '%s added. Save changes to apply it.' ).replace( '%s', title ) );
					search.focus();
				} );
				row.appendChild( button );
				results.appendChild( row );
				shown++;
			} );
			results.hidden = 0 === shown;
			say( shown ? t( 'whereFound', 'Choose an entry to hide the chat on it.' ) : t( 'whereNone', 'No published page, post or product has that title.' ) );
		}

		function run() {
			var term = search.value.trim();
			var mine = ++latest;
			if ( term.length < 2 ) {
				results.hidden = true;
				results.textContent = '';
				say( '' );
				return;
			}
			if ( ! url || ! config.nonce ) {
				say( t( 'whereError', 'Search is not available right now. Try again.' ) );
				return;
			}
			say( t( 'whereLoading', 'Searching…' ) );
			var query = 'search=' + encodeURIComponent( term ) + '&type=post&subtype=any&per_page=10&_fields=id,title,subtype';
			window.fetch( url + ( url.indexOf( '?' ) === -1 ? '?' : '&' ) + query, {
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': config.nonce },
			} )
				.then( function ( response ) {
					if ( ! response.ok ) { throw new Error( 'search_failed' ); }
					return response.json();
				} )
				.then( function ( list ) {
					if ( mine === latest ) { showResults( list ); }
				} )
				.catch( function () {
					if ( mine !== latest ) { return; }
					results.hidden = true;
					say( t( 'whereError', 'Search is not available right now. Try again.' ) );
				} );
		}

		// Typing a search term changes no setting, so it must not raise the save bar or submit the form.
		search.addEventListener( 'input', function ( event ) {
			event.stopPropagation();
			window.clearTimeout( timer );
			timer = window.setTimeout( run, 300 );
		} );
		search.addEventListener( 'change', function ( event ) { event.stopPropagation(); } );
		search.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' !== event.key ) { return; }
			event.preventDefault();
			window.clearTimeout( timer );
			run();
		} );

		picked.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '[data-sp-where-remove]' );
			if ( ! button ) { return; }
			var item = button.closest( '[data-sp-where-item]' );
			var next = item.nextElementSibling || item.previousElementSibling;
			var hit = results.querySelector( '[data-sp-where-hit="' + item.getAttribute( 'data-sp-where-item' ) + '"]' );
			if ( hit ) {
				hit.disabled = false;
				hit.querySelector( '.sp-wz-where__action' ).textContent = t( 'whereAdd', 'Hide here' );
			}
			item.parentNode.removeChild( item );
			markDirty();
			sync();
			var target = next ? next.querySelector( '[data-sp-where-remove]' ) : search;
			if ( target ) { target.focus(); }
		} );

		card.addEventListener( 'change', sync );
		sync();
	}

	repaint();
}() );
