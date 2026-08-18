/* global AISAAutoPilotAI */
/**
 * Widget settings — every control drives the live preview immediately.
 */
( function () {
	'use strict';

	var stage = document.getElementById( 'sp-w-stage' );
	if ( ! stage ) { return; }

	var root = document.querySelector( '[data-sp-widget]' );

	/** Brand-derived palette, mirroring Settings::widget_derived_colors(). */
	function derived( brand, theme ) {
		var dark = theme === 'dark';
		return {
			head_from: brand,
			head_to: '#7c3aed',
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
		if ( ! CSS_VAR[ key ] ) { return; }
		stage.style.setProperty( CSS_VAR[ key ], value );
	}

	function currentTheme() {
		var checked = root.querySelector( 'input[name="widget[theme]"]:checked' );
		return checked ? checked.value : 'light';
	}

	function currentBrand() {
		var field = document.getElementById( 'sp-w-brand' );
		return field ? field.value : '#4f46e5';
	}

	function customOn() {
		var flag = document.getElementById( 'sp-w-custom-flag' );
		return flag ? flag.value === '1' : false;
	}

	/** Recompute every colour from brand + theme, unless the user customised them. */
	function repaint() {
		var palette = derived( currentBrand(), currentTheme() );
		stage.setAttribute( 'data-theme', currentTheme() );

		Object.keys( palette ).forEach( function ( key ) {
			var input = root.querySelector( '[data-sp-color="' + key + '"]' );
			var value = ( customOn() && input ) ? input.value : palette[ key ];
			setColor( key, value );
			if ( ! customOn() && input ) { input.value = palette[ key ]; }
		} );

		stage.style.setProperty( '--wc-mic', currentTheme() === 'dark' ? '#374151' : '#ffffff' );
		stage.style.setProperty( '--wc-muted', currentTheme() === 'dark' ? '#9ca3af' : '#6b7280' );
	}

	/* ── Text bindings ── */
	Array.prototype.forEach.call( root.querySelectorAll( '[data-sp-bind]' ), function ( input ) {
		var name = input.getAttribute( 'data-sp-bind' );

		if ( name === 'handoff' ) {
			input.addEventListener( 'change', function () {
				var region = stage.querySelector( '[data-sp-region="handoff"]' );
				if ( region ) { region.hidden = ! input.checked; }
			} );
			return;
		}

		if ( name === 'suggestions' ) {
			input.addEventListener( 'input', function () { outSuggestions( input.value ); } );
			return;
		}

		input.addEventListener( 'input', function () {
			out( name, input.value );
			// Keep the duplicate "Head" field inside the customiser in sync.
			Array.prototype.forEach.call( root.querySelectorAll( '[data-sp-mirror="' + name + '"]' ), function ( twin ) {
				if ( twin !== input ) { twin.value = input.value; }
			} );
		} );
	} );

	// The customiser's "Head" field writes back into the real Title input.
	Array.prototype.forEach.call( root.querySelectorAll( '[data-sp-mirror]' ), function ( mirror ) {
		var name = mirror.getAttribute( 'data-sp-mirror' );
		mirror.addEventListener( 'input', function () {
			var real = root.querySelector( '[data-sp-bind="' + name + '"]' );
			if ( real ) { real.value = mirror.value; }
			out( name, mirror.value );
		} );
	} );

	/* ── Position & theme pills ── */
	Array.prototype.forEach.call( root.querySelectorAll( '[data-sp-radio-group]' ), function ( group ) {
		group.addEventListener( 'change', function ( event ) {
			var input = event.target;
			if ( ! input.name ) { return; }
			Array.prototype.forEach.call( group.querySelectorAll( '.sp-choice' ), function ( label ) {
				var radio = label.querySelector( 'input' );
				label.classList.toggle( 'is-on', !! ( radio && radio.checked ) );
			} );
			if ( input.hasAttribute( 'data-sp-pos' ) ) {
				stage.setAttribute( 'data-pos', input.getAttribute( 'data-sp-pos' ) );
			}
			if ( input.hasAttribute( 'data-sp-theme' ) ) { repaint(); }
		} );
	} );

	/* ── Brand swatches ── */
	Array.prototype.forEach.call( root.querySelectorAll( '[data-sp-brand]' ), function ( swatch ) {
		swatch.addEventListener( 'click', function () {
			var value = swatch.getAttribute( 'data-sp-brand' );
			document.getElementById( 'sp-w-brand' ).value = value;
			Array.prototype.forEach.call( root.querySelectorAll( '[data-sp-brand]' ), function ( other ) {
				other.classList.toggle( 'is-on', other === swatch );
			} );
			// Picking a brand colour returns the palette to its derived values.
			document.getElementById( 'sp-w-custom-flag' ).value = '0';
			repaint();
			document.getElementById( 'sp-w-custom-flag' ).value = customPanelOpen() ? '1' : '0';
		} );
	} );

	function customPanelOpen() {
		var panel = document.getElementById( 'sp-w-custom-panel' );
		return !! panel && panel.style.display !== 'none';
	}

	/* ── Customize toggle ── */
	var toggle = document.getElementById( 'sp-w-customize' );
	if ( toggle ) {
		toggle.addEventListener( 'click', function () {
			var panel = document.getElementById( 'sp-w-custom-panel' );
			var flag = document.getElementById( 'sp-w-custom-flag' );
			var hint = document.getElementById( 'sp-w-custom-hint' );
			var open = ! customPanelOpen();

			panel.style.display = open ? 'flex' : 'none';
			toggle.classList.toggle( 'is-on', open );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			flag.value = open ? '1' : '0';
			if ( hint ) { hint.textContent = hint.getAttribute( 'data-alt' ) || hint.textContent; }
			repaint();
		} );
	}

	/* ── Per-part colour inputs ── */
	Array.prototype.forEach.call( root.querySelectorAll( '[data-sp-color]' ), function ( input ) {
		input.addEventListener( 'input', function () {
			document.getElementById( 'sp-w-custom-flag' ).value = '1';
			setColor( input.getAttribute( 'data-sp-color' ), input.value );
		} );
	} );

	var reset = document.getElementById( 'sp-w-reset-colors' );
	if ( reset ) {
		reset.addEventListener( 'click', function () {
			document.getElementById( 'sp-w-custom-flag' ).value = '0';
			repaint();
			document.getElementById( 'sp-w-custom-flag' ).value = '1';
		} );
	}

	/* ── Range sliders ── */
	var RANGE_VAR = {
		panel_radius: '--wp-radius',
		msg_radius: '--wp-msg-radius',
		bubble_radius: '--wp-bubble-radius',
	};
	Array.prototype.forEach.call( root.querySelectorAll( '[data-sp-range]' ), function ( input ) {
		input.addEventListener( 'input', function () {
			var key = input.getAttribute( 'data-sp-range' );
			stage.style.setProperty( RANGE_VAR[ key ], input.value + 'px' );
			var label = root.querySelector( '[data-sp-out="' + key + '"]' );
			if ( label ) { label.textContent = input.value + 'px'; }
		} );
	} );

	/* ── Channel cards ── */
	function refreshChannelSummary() {
		var on = root.querySelectorAll( '[data-sp-channel-toggle]:checked' ).length;
		var node = document.getElementById( 'sp-w-channel-summary' );
		if ( ! node ) { return; }
		node.textContent = on === 0
			? ( AISAAutoPilotAI.i18n && AISAAutoPilotAI.i18n.noChannels ) || node.textContent
			: node.textContent;
	}

	Array.prototype.forEach.call( root.querySelectorAll( '[data-sp-channel-toggle]' ), function ( input ) {
		input.addEventListener( 'change', function () {
			var card = input.closest( '[data-sp-channel]' );
			if ( card ) { card.classList.toggle( 'is-on', input.checked ); }
			refreshChannelSummary();
		} );
	} );

	/* ── Icon drop slots ── */
	Array.prototype.forEach.call( root.querySelectorAll( '[data-sp-slot]' ), function ( slot ) {
		var file = slot.querySelector( 'input[type="file"]' );
		var value = slot.querySelector( '[data-sp-slot-value]' );
		var img = slot.querySelector( '[data-sp-slot-img]' );
		var clear = slot.querySelector( '.sp-slot__clear' );
		var target = slot.getAttribute( 'data-sp-slot' ).replace( /^widget\[icons]\[|]$/g, '' );

		function apply( dataUrl ) {
			value.value = dataUrl || '';
			slot.classList.toggle( 'is-filled', !! dataUrl );
			if ( dataUrl ) { img.src = dataUrl; img.hidden = false; } else { img.removeAttribute( 'src' ); img.hidden = true; }

			var host = stage.querySelector( '[data-sp-icon="' + target + '"]' );
			if ( ! host ) { return; }
			if ( dataUrl ) {
				host.innerHTML = '';
				var preview = document.createElement( 'img' );
				preview.src = dataUrl;
				preview.alt = '';
				if ( target === 'handoff' ) { preview.style.cssText = 'width:14px;height:14px;object-fit:contain'; }
				host.appendChild( preview );
			} else {
				host.innerHTML = host.getAttribute( 'data-fallback' ) || host.innerHTML;
			}
		}

		function read( f ) {
			if ( ! f || ! /^image\//.test( f.type ) ) { return; }
			if ( f.size > 200 * 1024 ) {
				window.alert( ( AISAAutoPilotAI.i18n && AISAAutoPilotAI.i18n.iconTooLarge ) || 'Image is too large (max 200 KB).' );
				return;
			}
			var reader = new FileReader();
			reader.onload = function () { apply( String( reader.result ) ); };
			reader.readAsDataURL( f );
		}

		// Remember the built-in SVG so removing an upload restores it.
		var host = stage.querySelector( '[data-sp-icon="' + target + '"]' );
		if ( host && ! host.getAttribute( 'data-fallback' ) && ! host.querySelector( 'img' ) ) {
			host.setAttribute( 'data-fallback', host.innerHTML );
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

	repaint();
}() );
