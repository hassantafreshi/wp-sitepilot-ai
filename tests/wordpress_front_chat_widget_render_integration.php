<?php
/**
 * Local WordPress contracts for the visitor chat widget's launcher, docked panel, full screen and
 * conversation (S3.3.1, T08; S3.3.2, T09; S3.3.3, T10).
 *
 * Proves the render gates, the panel direction, the accent and size custom properties, the
 * revision-2 defaults decided in D-25 (no automatic opening, no automatic read-aloud, 392×624),
 * the one-time move of legacy defaults nobody chose, the translated default copy in the markup,
 * the localized script data and GET /widget/settings, the new "Open the chat by itself" control,
 * and that no delivery credential or private recipient reaches a visitor. For full screen it
 * proves the side column carries only questions the site owner saved, the 641–768px breakpoint,
 * the chip rule that survives it, and the page scroll lock behind the modal view.
 *
 * Nothing is sent anywhere: rendering and the settings route make no outbound request, and
 * pre_http_request and pre_wp_mail are closed for the whole run. The settings option, the
 * active user, the request globals, the locale and the widget script registration are restored
 * in finally. No secret value is ever printed.
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Api\ChatWidgetController;
use AISAAutoPilot\Api\PublicChatController;
use AISAAutoPilot\Core\Plugin;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Frontend\ChatWidget;
use AISAAutoPilot\Frontend\ChatWidgetSettings;

$missing       = new stdClass();
$original      = get_option( Settings::OPTION, $missing );
$original_user = get_current_user_id();
$original_post = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- snapshot for restore.
$checks        = array();
$outbound      = 0;

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

$block_http = static function () use ( &$outbound ) {
	++$outbound;
	return new WP_Error( 'aisaautopilot_test_blocked', 'Outbound HTTP is blocked in this test.' );
};
$block_mail = static function () use ( &$outbound ) {
	++$outbound;
	return false;
};
add_filter( 'pre_http_request', $block_http, 1 );
add_filter( 'pre_wp_mail', $block_mail, 1 );

/** Store a raw option exactly as given, bypassing Settings::update(), so "never saved" can be simulated. */
$store_raw = static function ( array $widget, bool $public = true ): void {
	update_option(
		Settings::OPTION,
		array(
			'public_assistant_enabled' => $public,
			'widget'                   => $widget,
		),
		false
	);
};

$render = static function (): string {
	return ( new ChatWidget() )->render();
};

/** The object handed to widget.js, decoded from the localized script data. */
$localized = static function (): array {
	wp_dequeue_script( 'aisaautopilot-widget' );
	wp_deregister_script( 'aisaautopilot-widget' );
	wp_dequeue_style( 'aisaautopilot-widget' );
	wp_deregister_style( 'aisaautopilot-widget' );
	Plugin::instance()->enqueue_widget_assets();
	$data = (string) wp_scripts()->get_data( 'aisaautopilot-widget', 'data' );
	if ( 1 !== preg_match( '/var AISAAutoPilotWidget = (\{.*\});/s', $data, $match ) ) {
		return array();
	}
	$decoded = json_decode( $match[1], true );
	return is_array( $decoded ) ? $decoded : array();
};

$rest_settings = static function (): array {
	return (array) ( new ChatWidgetController() )->settings()->get_data();
};

$customiser = static function ( array $post = array() ): string {
	$_POST = $post;
	ob_start();
	( new ChatWidgetSettings() )->render();
	$html  = (string) ob_get_clean();
	$_POST = array();
	return $html;
};

$raw_widget = static function (): array {
	$stored = get_option( Settings::OPTION, array() );
	return is_array( $stored ) && isset( $stored['widget'] ) && is_array( $stored['widget'] ) ? $stored['widget'] : array();
};

$persian = static function ( string $text ): bool {
	return 1 === preg_match( '/[\x{0600}-\x{06FF}]/u', $text );
};

try {
	wp_set_current_user( 1 );
	switch_to_locale( 'en_US' );

	// ── Defaults (revision 2, D-25) ─────────────────────────────
	$defaults = Settings::widget_defaults();
	$check(
		0 === $defaults['auto_open'] && false === $defaults['voice_auto'] && 392 === $defaults['window_width'] && 624 === $defaults['window_height'] && 20 === $defaults['panel_radius'] && Settings::WIDGET_DEFAULTS_REVISION === $defaults['defaults_revision'],
		'Shipped defaults: no automatic opening, no automatic read-aloud, a 392×624 panel with 20px corners, revision marker 2'
	);

	// ── Render gates ────────────────────────────────────────────
	$store_raw( array( 'enabled' => true ), false );
	$check( '' === $render(), 'Answer Desk off in Integrations renders no widget' );
	$gate_off_script = $localized();
	$check( array() === $gate_off_script && ! wp_script_is( 'aisaautopilot-widget', 'enqueued' ), 'Answer Desk off in Integrations enqueues no widget script or data' );

	$store_raw( array( 'enabled' => false ), true );
	$check( '' === $render(), 'The widget visibility switch off renders no widget' );

	// ── A site that never saved widget settings reads the new defaults ──
	$store_raw( array( 'enabled' => true ), true );
	$html = $render();
	$check( false !== strpos( $html, 'class="spw-root spw--light spw--bottom-right"' ), 'Both gates on render the widget root' );
	$check( false !== strpos( $html, 'data-spw-auto-open="0"' ), 'By default the panel never opens by itself (data-spw-auto-open="0")' );
	$check(
		false !== strpos( $html, '--spw-radius:20px;' ) && false !== strpos( $html, '--spw-w:392px;' ) && false !== strpos( $html, '--spw-h:624px;' ),
		'Size and corner custom properties match the mockup panel: 392px, 624px, 20px'
	);
	$check( false !== strpos( $html, 'dir="ltr"' ) && false === strpos( $html, 'dir="rtl"' ), 'en_US renders the widget root left-to-right' );
	$check( false === strpos( $html, 'spw-avatar__dot' ), 'The header has no "online" dot, which no real status backs' );
	$check(
		false !== strpos( $html, 'id="spw-voice"' ) && false !== strpos( $html, 'id="spw-expand"' ) && false !== strpos( $html, 'id="spw-close"' ) && false !== strpos( $html, 'id="spw-mic"' ) && false !== strpos( $html, 'id="spw-send"' ) && false !== strpos( $html, 'id="spw-toggle"' ),
		'Launcher, header actions (voice, full screen, minimise) and composer controls (mic, send) are rendered'
	);
	$check(
		false !== strpos( $html, '>AI Assistant<' ) && false !== strpos( $html, 'Hi! How can I help you?' ),
		'en_US shows the English default subtitle and greeting'
	);

	$script = $localized();
	// wp_localize_script() turns top-level scalars into strings, so false arrives as "".
	$check(
		array() !== $script && '' === $script['voiceAuto'] && 'Hi! How can I help you?' === $script['greeting'] && 'Try again' === ( $script['i18n']['retry'] ?? '' ),
		'Localized script data: voiceAuto off by default, English greeting and runtime strings in en_US'
	);

	$rest = $rest_settings();
	$check(
		0 === $rest['auto_open'] && false === $rest['voice_auto'] && 'Hi! How can I help you?' === $rest['greeting'],
		'GET /widget/settings reports auto_open 0, voice_auto false and the default greeting'
	);

	// ── Accent and explicit auto-open ───────────────────────────
	$store_raw(
		array(
			'enabled'   => true,
			'color'     => '#1b4d89',
			'auto_open' => 12,
			'greeting'  => 'Welcome to Northwind',
		),
		true
	);
	$html = $render();
	$check(
		false !== strpos( $html, '--spw-primary:#1b4d89;' ) && false !== strpos( $html, '--spw-send:#1b4d89;' ) && false !== strpos( $html, '--spw-float:#1b4d89;' ),
		'The saved accent colour reaches the primary, send and launcher custom properties'
	);
	$check( false !== strpos( $html, 'data-spw-auto-open="12"' ) && 12 === $rest_settings()['auto_open'], 'An explicit delay is rendered and reported unchanged (12 seconds)' );

	// ── fa_IR: direction, translated defaults, typed copy untouched ──
	if ( switch_to_locale( 'fa_IR' ) ) {
		$fa_custom = $render();
		$check( false !== strpos( $fa_custom, 'dir="rtl"' ), 'fa_IR renders the widget root right-to-left' );
		$check( false !== strpos( $fa_custom, 'Welcome to Northwind' ), 'A greeting the site owner typed is shown exactly as saved, in any language' );

		$store_raw(
			array_merge(
				Settings::widget_defaults(),
				array(
					'enabled'                      => true,
					'conversation_logging_enabled' => true,
				)
			),
			true
		);
		$fa_html = $render();
		$fa_copy = Settings::widget_default_copy();
		$check(
			$persian( $fa_copy['greeting'] ) && $persian( $fa_copy['subtitle'] ) && $persian( $fa_copy['conversation_notice'] )
			&& false !== strpos( $fa_html, esc_html( $fa_copy['greeting'] ) )
			&& false !== strpos( $fa_html, esc_html( $fa_copy['subtitle'] ) )
			&& false !== strpos( $fa_html, esc_html( $fa_copy['conversation_notice'] ) ),
			'fa_IR: stored English defaults render as the Persian greeting, subtitle and recording notice'
		);
		$check(
			false === strpos( $fa_html, 'Hi! How can I help you?' ) && false === strpos( $fa_html, 'This conversation may be recorded to help our support team.' ),
			'fa_IR: no English default copy is left in the visitor markup'
		);
		$fa_script = $localized();
		$check(
			$persian( (string) ( $fa_script['greeting'] ?? '' ) ) && $persian( (string) ( $fa_script['i18n']['retry'] ?? '' ) ) && $persian( (string) ( $fa_script['i18n']['fullScreen'] ?? '' ) ),
			'fa_IR: localized script data carries the Persian greeting and runtime strings'
		);
		$check(
			$persian( (string) ( $fa_script['i18n']['listening'] ?? '' ) ) && $persian( (string) ( $fa_script['i18n']['stopRecording'] ?? '' ) ),
			'fa_IR: the one-line "Listening…" placeholder and the "Stop recording" label reach widget.js in Persian'
		);
		$fa_rest = $rest_settings();
		$check(
			$persian( (string) $fa_rest['greeting'] ) && $persian( (string) $fa_rest['conversation_notice'] ),
			'fa_IR: GET /widget/settings returns the Persian default greeting and notice'
		);

		$fa_form = $customiser();
		$check(
			false !== strpos( $fa_form, 'value="' . esc_attr( $fa_copy['greeting'] ) . '"' ),
			'fa_IR: the customiser shows the untouched default greeting in Persian'
		);
		$customiser(
			array(
				'_wpnonce' => wp_create_nonce( 'sp_widget_save' ),
				'widget'   => array(
					'greeting' => $fa_copy['greeting'],
					'subtitle' => 'پشتیبانی نورث‌ویند',
				),
			)
		);
		$saved = $raw_widget();
		$check(
			'Hi! How can I help you?' === $saved['greeting'] && 'پشتیبانی نورث‌ویند' === $saved['subtitle'],
			'fa_IR: saving the translated default keeps it stored as the default, while typed Persian copy is stored as typed'
		);
		restore_previous_locale();
	} else {
		$check( false, 'fa_IR is available for the direction and translated copy checks' );
	}

	// ── The customiser control for automatic opening ────────────
	$store_raw( array( 'enabled' => true ), true );
	$form = $customiser();
	$check(
		false !== strpos( $form, 'name="widget[auto_open]"' ) && 1 === preg_match( '/<option value="0"\s+selected=\'selected\'>/', $form ) && false !== strpos( $form, 'data-sp-auto-open-field' ),
		'The customiser offers "Open the chat by itself" with Never selected by default'
	);
	$customiser(
		array(
			'_wpnonce' => wp_create_nonce( 'sp_widget_save' ),
			'widget'   => array( 'auto_open' => '20' ),
		)
	);
	$check( 20 === $raw_widget()['auto_open'], 'Saving the control stores the chosen delay (20 seconds)' );
	$customiser(
		array(
			'_wpnonce' => wp_create_nonce( 'sp_widget_save' ),
			'widget'   => array( 'auto_open' => '999' ),
		)
	);
	$check( 120 === $raw_widget()['auto_open'], 'A delay above the limit is clamped to 120 seconds' );
	$form = $customiser();
	$check( 1 === preg_match( '/<option value="120"\s+selected=\'selected\'>/', $form ), 'A stored delay outside the offered list stays selectable instead of silently changing' );
	$customiser(
		array(
			'_wpnonce' => wp_create_nonce( 'sp_widget_save' ),
			'widget'   => array( 'position' => 'bottom-left' ),
		)
	);
	$check( 120 === $raw_widget()['auto_open'] && 'bottom-left' === $raw_widget()['position'], 'A save without the control leaves the stored delay untouched' );

	// ── One-time move of legacy defaults nobody chose ───────────
	$store_raw(
		array(
			'enabled'       => true,
			'auto_open'     => 5,
			'window_width'  => 380,
			'window_height' => 550,
			'voice_auto'    => true,
			'panel_radius'  => 16,
		),
		true
	);
	Settings::migrate_widget_defaults();
	$moved = $raw_widget();
	$check(
		0 === $moved['auto_open'] && 392 === $moved['window_width'] && 624 === $moved['window_height'] && Settings::WIDGET_DEFAULTS_REVISION === $moved['defaults_revision'],
		'Migration moves the unchosen legacy defaults (auto_open 5, 380×550) to revision 2 and marks the widget'
	);
	$check(
		true === $moved['voice_auto'] && 16 === $moved['panel_radius'],
		'Migration leaves values a site owner can set on a screen (voice_auto, panel corners) untouched'
	);
	$html = $render();
	$check( false !== strpos( $html, 'data-spw-auto-open="0"' ) && false !== strpos( $html, '--spw-w:392px;' ) && false !== strpos( $html, '--spw-radius:16px;' ), 'After migration the widget renders the moved values and keeps the saved corners' );

	$store_raw(
		array(
			'enabled'           => true,
			'auto_open'         => 5,
			'defaults_revision' => Settings::WIDGET_DEFAULTS_REVISION,
		),
		true
	);
	Settings::migrate_widget_defaults();
	$check( 5 === $raw_widget()['auto_open'], 'Migration never runs twice: a 5-second delay saved at revision 2 stays' );

	$store_raw(
		array(
			'enabled'   => true,
			'auto_open' => 15,
		),
		true
	);
	Settings::migrate_widget_defaults();
	$check( 15 === $raw_widget()['auto_open'] && Settings::WIDGET_DEFAULTS_REVISION === $raw_widget()['defaults_revision'], 'Migration leaves a non-default legacy value (15 seconds) and only adds the marker' );

	delete_option( Settings::OPTION );
	Settings::migrate_widget_defaults();
	$check( $missing === get_option( Settings::OPTION, $missing ), 'Migration creates no option on a site that never saved settings' );

	// ── No credential or private recipient reaches a visitor ────
	$marker_email = 'qa-private-recipient-t08@example.invalid';
	$marker_chat  = '-100987654321';
	$store_raw(
		array(
			'enabled'               => true,
			'handoff_enabled'       => true,
			'handoff_email_enabled' => true,
			'handoff_email_to'      => $marker_email,
			'handoff_telegram_chat' => $marker_chat,
			'exclude_ids'           => array( 987654 ),
		),
		true
	);
	$html      = $render();
	$script    = wp_json_encode( $localized() );
	$rest_json = (string) wp_json_encode( $rest_settings() );
	$surfaces  = $html . "\n" . $script . "\n" . $rest_json;
	$check( false !== strpos( $html, 'spw-handoff-template' ), 'With a ready channel the handoff card template is rendered, so the secret check covers it' );
	$check(
		false === strpos( $surfaces, $marker_email ) && false === strpos( $surfaces, $marker_chat ) && false === strpos( $rest_json, 'handoff_email_to' ) && false === strpos( $rest_json, 'exclude' ),
		'Private recipients and exclusion rules never reach the markup, the script data or GET /widget/settings'
	);
	$secrets = array_filter(
		array(
			Settings::get_api_key(),
			Settings::get_handoff_secret( 'handoff_telegram_token' ),
			Settings::get_handoff_secret( 'handoff_twilio_sid' ),
			Settings::get_handoff_secret( 'handoff_twilio_token' ),
		),
		static fn( string $value ): bool => strlen( $value ) >= 6
	);
	$leaked  = array_filter( $secrets, static fn( string $value ): bool => false !== strpos( $surfaces, $value ) );
	$check( array() === $leaked, 'No stored provider key or handoff credential appears in any visitor surface (' . count( $secrets ) . ' stored values compared, none printed)' );

	// ── Stylesheet contract ─────────────────────────────────────
	$store_raw(
		array(
			'enabled'     => true,
			'suggestions' => array( 'What are your opening hours?', 'How long does shipping take?' ),
		),
		true
	);
	$html = $render();
	$check(
		2 === substr_count( $html, 'class="spw-side__q"' )
		&& false !== strpos( $html, 'data-spw-ask="What are your opening hours?"' )
		&& false !== strpos( $html, 'data-spw-ask="How long does shipping take?"' ),
		'Full screen lists exactly the questions the site owner saved, with no sample question added'
	);
	$check( false === strpos( $html, 'spw-side__human' ), 'With no ready handoff channel the side column offers no "talk to a person" section' );

	$store_raw(
		array(
			'enabled'               => true,
			'suggestions'           => array(),
			'handoff_enabled'       => true,
			'handoff_email_enabled' => true,
			'handoff_email_to'      => 'qa-side-column-t09@example.invalid',
		),
		true
	);
	$html = $render();
	$check(
		false === strpos( $html, 'spw-side__list' ) && false === strpos( $html, 'spw-side__q' ) && false !== strpos( $html, 'spw-side__human' ),
		'With no saved questions the side column drops the question list and keeps only the human section'
	);

	$store_raw(
		array(
			'enabled'     => true,
			'suggestions' => array(),
		),
		true
	);
	$check( false === strpos( $render(), 'class="spw-side"' ), 'With neither questions nor a ready channel the side column is not rendered at all' );

	$css = (string) file_get_contents( AISAAUTOPILOT_DIR . 'assets/frontend/widget.css' );
	$js  = (string) file_get_contents( AISAAUTOPILOT_DIR . 'assets/frontend/widget.js' );
	$check( false === strpos( $css, 'spw-avatar__dot' ), 'widget.css has no rule for the removed online dot' );
	$check(
		1 === preg_match( '/\.spw-hbtn::before,\s*\.spw-root \.spw-jump::before \{ inset: -5px; \}/', $css ) && 1 === preg_match( '/\.spw-send::before,\s*\.spw-root \.spw-mic::before \{ inset: -4px; \}/', $css ),
		'widget.css grows the 34px and 36px icon buttons to 44px hit areas without changing their drawn size'
	);
	$check(
		1 === preg_match( '/@media \(prefers-reduced-motion: reduce\) \{[^@]*animation: none !important;[^@]*\.spw-toggle__ring \{ display: none; \}/s', $css ),
		'widget.css stops every animation and hides the pulsing launcher ring under prefers-reduced-motion'
	);
	$check(
		1 === preg_match( '/@media \(min-width: 641px\) and \(max-width: 768px\) \{(.*?)\n\}/s', $css, $band )
		&& false !== strpos( $band[1], 'height: 100dvh;' )
		&& 1 === preg_match( '/\.spw-panel--full \.spw-side \{ display: none; \}/', $band[1] )
		&& 1 === preg_match( '/\.spw-scrim \{ display: none; \}/', $band[1] ),
		'widget.css gives full screen the whole viewport between 641px and 768px, with no side column and no scrim (T07 breakpoint decision)'
	);
	$check(
		2 === preg_match_all( '/\.spw-panel--full:has\(\.spw-side__list\) \.spw-chips \{ display: flex; \}/', $css )
		&& 1 === preg_match_all( '/\.spw-panel--full:has\(\.spw-side__list\) \.spw-chips \{ display: none; \}/', $css ),
		'Both small-screen bands bring the suggestion chips back with the same :has() specificity that hides them beside the side column'
	);
	$check(
		false === strpos( $js, "matchMedia('(max-width: 900px)')" ) && false !== strpos( $js, "matchMedia('(max-width: 768px)')" ),
		'widget.js reads the same 768px threshold as the stylesheet, not the old 900px one'
	);
	$check(
		1 === preg_match( '/function lockPageScroll\(lock\) \{/', $js )
		&& 1 === preg_match( '/scrollLock = \{ html: html\.style\.overflow, body: body \? body\.style\.overflow : \'\' \};/', $js )
		&& 1 === preg_match( '/function syncModal\(\) \{\s+var panel = \$\(\'spw-panel\'\);\s+var modal = isModal\(\);\s+if \(panel\) panel\.setAttribute\(\'aria-modal\', modal \? \'true\' : \'false\'\);\s+lockPageScroll\(modal\);/', $js )
		// The definition and the one call in syncModal().
		&& 2 === substr_count( $js, 'lockPageScroll(' ),
		'widget.js locks the page behind a modal panel only through syncModal() and restores exactly the inline overflow it snapshotted (F-08)'
	);
	$check(
		false !== strpos( $js, "var sheetQuery = window.matchMedia ? window.matchMedia('(max-width: 640px)') : null;" )
		&& false !== strpos( $js, 'return isOpen && (isFull || !!(sheetQuery && sheetQuery.matches));' )
		&& false !== strpos( $js, "if (!isModal() || event.key !== 'Tab') return;" )
		&& 1 === preg_match( "/btn\\.setAttribute\\('aria-expanded', open \\? 'true' : 'false'\\);\\s+syncModal\\(\\);/", $js )
		&& 1 === preg_match( "/btn\\.title = label;\\s+syncModal\\(\\);/", $js )
		&& false !== strpos( $js, "sheetQuery.addEventListener('change', syncModal);" )
		&& 1 === preg_match( '/@media \(max-width: 640px\) \{/', $css ),
		'The phone sheet (640px, the stylesheet threshold) is modal like full screen: aria-modal, Tab kept inside and the page locked, re-checked on open, close, full screen and rotation (F-17, reversed by the owner)'
	);
	$check(
		1 === preg_match( "/var hide = atBottom\\(\\);\\s+if \\(hide && document\\.activeElement === jump\\) \\{\\s+var input = \\$\\('spw-input'\\);\\s+if \\(input\\) input\\.focus\\(\\{ preventScroll: true \\}\\);\\s+\\}\\s+jump\\.hidden = hide;/", $js ),
		'The "latest message" button hands focus to the composer before it hides, so keyboard focus never falls to the page or out of a modal panel (F-26, the T13 Tab flake)'
	);

	// ── Conversation contracts (T10) ──────────────────────
	$store_raw(
		array(
			'enabled'       => true,
			'voice_enabled' => true,
			'suggestions'   => array( 'What are your opening hours?' ),
			'greeting'      => 'Hello from Northwind',
			'subtitle'      => 'Northwind support',
		),
		true
	);
	$html = $render();
	$check(
		false !== strpos( $html, '<div class="spw-msg spw-msg--ai spw-msg--greeting" dir="auto">' )
		&& false !== strpos( $html, '<button type="button" class="spw-chip" dir="auto" data-spw-ask=' )
		&& false !== strpos( $html, '<button type="button" class="spw-side__q" dir="auto" data-spw-ask=' )
		&& false !== strpos( $html, '<div class="spw-header__title" id="spw-title" dir="auto">' )
		&& false !== strpos( $html, '<div class="spw-header__sub" dir="auto">' )
		&& false !== strpos( $html, '<span class="spw-nudge__text" dir="auto">' ),
		'Copy written by the site owner carries dir="auto": greeting, chips, popular questions, header and nudge (F-06)'
	);
	$composer_js = (string) file_get_contents( AISAAUTOPILOT_DIR . 'assets/frontend/widget.js' );
	$check(
		1 === preg_match( '/<textarea class="spw-input" id="spw-input" rows="1"\s+placeholder=/', $html )
		&& 1 === preg_match( '/function autoGrow\(node\) \{\s+if \(!node\) return;\s+if \(node\.value\) node\.setAttribute\(\'dir\', \'auto\'\);\s+else node\.removeAttribute\(\'dir\'\);/', $composer_js ),
		'The composer renders without a static dir and widget.js sets dir="auto" only while it holds text, so an empty box keeps the page direction for its placeholder (T13)'
	);
	$check(
		1 === preg_match( '/if \(!node\.value && node\.placeholder\) \{\s+node\.value = node\.placeholder;\s+height = node\.scrollHeight;\s+node\.value = \'\';/', $composer_js )
		&& 1 === preg_match( '/input\.setAttribute\(\'placeholder\', recording \?[^\n]+\n\s+autoGrow\(input\);/', $composer_js ),
		'An empty composer is sized for its placeholder and re-sized when recording swaps it, so a placeholder longer than the box is never cut (T13)'
	);
	$check(
		1 === preg_match( '/<span class="spw-rec" id="spw-rec" aria-hidden="true" hidden><span class="spw-rec__dot"><\/span><span class="spw-rec__time" id="spw-rec-time" dir="ltr">0:00<\/span><\/span>\s+<textarea class="spw-input"/', $html )
		&& 2 === substr_count( $html, '<span class="spw-mic__ico">' )
		&& 2 === substr_count( $html, '<span class="spw-mic__stop">' )
		&& 'Listening…' === ( $localized()['i18n']['listening'] ?? '' ),
		'Recording has a dot and timer before the message box, hidden until the mic is live and hidden from the status region audience; both mic buttons carry a stop square; the one-line "Listening…" reaches widget.js (F-25 follow-up)'
	);
	$check(
		false !== strpos( $composer_js, "input.setAttribute('placeholder', recording ? str('listening', 'Listening…') : defaultPlaceholder);" )
		&& false !== strpos( $composer_js, "box.classList.toggle('spw-composer__box--recording', recording);" )
		&& false !== strpos( $composer_js, 'mic.title = label;' )
		&& 1 === substr_count( $composer_js, "setVoiceStatus(str('recording', 'Listening… tap the mic again to stop'));" )
		&& false !== strpos( $css, '.spw-root .spw-mic--recording .spw-mic__stop { display: flex; }' )
		&& false !== strpos( $css, '.spw-root .spw-composer__box--recording:focus-within { border-color: #ff455f;' ),
		'While recording the placeholder is one line, the box turns coral, the mic buttons become stop buttons with a matching tooltip, and screen readers still hear the whole instruction'
	);
	$check(
		false !== strpos( $css, "\t--spw-faint: #686b8a;" ) && false !== strpos( $css, "\t--spw-faint: #9098a6;" )
		&& false === strpos( $css, '#a9abc4' ) && false === strpos( $css, '#7e81a3' )
		&& 1 === preg_match( '/\.spw-footnote \{[^}]*color: var\(--spw-faint\); \}/', $css )
		&& 1 === preg_match( '/\.spw-source \{[^}]*color: var\(--spw-faint\); \}/', $css ),
		'Small helper text uses one faint token that reaches 4.5:1 in the light and dark themes: day label, placeholder, source line, footnote and column label (T13 contrast)'
	);
	$check(
		false !== strpos( $css, '.spw-root[dir="rtl"] .spw-side__q { text-align: right; }' )
		&& false !== strpos( $css, '.spw-root[dir="ltr"] .spw-side__q { text-align: left; }' )
		&& false !== strpos( $css, '.spw-root[dir="rtl"] .spw-header__title,' )
		&& false === strpos( $css, 'text-align: match-parent' ),
		'Owner copy with dir="auto" lines up on the widget side, keyed on the root dir because Chrome ignores match-parent (T13)'
	);

	$script = $localized();
	$check(
		'1' === ( $script['voiceEnabled'] ?? null ) && '' === ( $script['voiceName'] ?? null ),
		'The localized script data carries the voice gate and voice name, so widget.js needs no settings request (F-15)'
	);
	$check(
		'Stop waiting for the reply' === ( $script['i18n']['stopWaiting'] ?? '' )
		&& false !== strpos( (string) ( $script['i18n']['stoppedWaiting'] ?? '' ), 'may still finish this answer' )
		&& 'Choose what you mean' === ( $script['i18n']['clarifyLabel'] ?? '' )
		&& ! isset( $script['i18n']['stop'] ),
		'Runtime strings say what stopping really does and name the clarify options, with no bare "Stop" left'
	);

	$store_raw(
		array(
			'enabled'       => true,
			'voice_enabled' => false,
		),
		true
	);
	$check(
		'' === ( $localized()['voiceEnabled'] ?? 'missing' ) && false === strpos( $render(), 'id="spw-mic"' ) && false === strpos( $render(), 'id="spw-rec"' ),
		'With voice switched off the script data reports the gate as off and no microphone is rendered'
	);

	$ask = static function ( string $question ): array {
		$request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/widget/ask' );
		$request->set_param( 'question', $question );
		$response = ( new ChatWidgetController() )->ask( $request );
		return array(
			'status' => $response->get_status(),
			'data'   => (array) $response->get_data(),
		);
	};
	// Both refusals return before the rate limiter and before the engine, so nothing is asked of a provider.
	$empty = $ask( '   ' );
	$check(
		400 === $empty['status'] && 'empty' === $empty['data']['code'] && ! isset( $empty['data']['answer'] )
		&& 'Please enter a question.' === $empty['data']['message'],
		'A refused question answers with "message" and never with "answer", which the widget reads as the assistant speaking (F-14)'
	);
	$long = $ask( str_repeat( 'a', 1501 ) );
	$check(
		400 === $long['status'] && 'question_too_long' === $long['data']['code'] && ! isset( $long['data']['answer'] ),
		'A message over the length limit is refused with a message and no answer'
	);

	$check(
		false === strpos( $js, "apiRoot + 'widget/settings'" ) && false === strpos( $js, 'getSettings' ),
		'widget.js sends no settings request on page load: a visitor who never opens the chat costs the site nothing (F-15)'
	);
	$check(
		1 === preg_match( '/var NO_RETRY = \[(.*?)\];/', $js, $no_retry )
		&& false !== strpos( $no_retry[1], "'rate_limited'" )
		&& false !== strpos( $no_retry[1], "'usage_cap_reached'" )
		&& false !== strpos( $no_retry[1], "'question_too_long'" )
		&& false !== strpos( $js, "retry: NO_RETRY.indexOf(String((data && data.code) || '')) === -1," ),
		'An hourly limit, a spent budget or an over-long message offers no immediate "try again" (F-07)'
	);
	$check(
		false !== strpos( $js, 'function stopGenerating(explain) {' )
		&& false !== strpos( $js, "addNote('stopped', str('stoppedWaiting'" )
		&& false !== strpos( $js, "str('stopWaiting', 'Stop waiting for the reply')" ),
		'Stopping says what really happened instead of implying the site cancelled the answer (F-07)'
	);
	$check(
		false !== strpos( $js, 'if (data && data.ok && data.answer) {' ),
		'Only a response the site marked successful becomes an assistant turn (F-14)'
	);
	$check(
		false !== strpos( $js, 'function renderAnswer(bubble, text) {' )
		&& 1 === preg_match( '/function addClarifyOptions\(stack, options\) \{(.*?)\n\t\}/s', $js, $clarify )
		&& false === strpos( $clarify[1], 'send(' )
		&& false !== strpos( $clarify[1], 'input.value = text;' ),
		'The answer Markdown is rendered, and a clarify option the model wrote only fills the composer (F-13, D-24)'
	);
	$check(
		1 === substr_count( $js, 'innerHTML' ) && 1 === substr_count( $js, 'html: BOOK_ICON' ),
		'widget.js writes no untrusted markup: the single innerHTML write is the el() helper and its only caller passes the static book icon'
	);

	$check(
		false !== strpos( $css, '.spw-root .spw-msg .spw-md-list {' )
		&& false !== strpos( $css, '.spw-root .spw-clarify {' )
		&& false !== strpos( $css, '.spw-root .spw-note {' ),
		'widget.css styles the rendered Markdown, the clarify options and the honest note'
	);

	// ── Handoff card (T11) ──────────────────────────────────────
	$handoff_route = static function ( array $body ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/' . \AISAAutoPilot\Api\RestRoutes::NAMESPACE . '/widget/handoff' );
		foreach ( $body as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return ( new ChatWidgetController() )->handoff( $request );
	};
	$handoff_status = static function (): array {
		return (array) ( new ChatWidgetController() )->handoff_status()->get_data();
	};
	$transcript     = array( array( 'role' => 'user', 'content' => 'Where is my order?' ) );

	// Not ready: no channel at all.
	$store_raw( array( 'enabled' => true, 'suggestions' => array( 'What are your opening hours?' ) ), true );
	$html   = $render();
	$before = $outbound;
	$denied = $handoff_route( array( 'messages' => $transcript ) );
	$check(
		false === strpos( $html, 'spw-handoff-template' )
		&& 0 === substr_count( $html, 'data-spw-handoff>' )
		&& false === strpos( $html, 'class="spw-handoff"' ),
		'With no ready channel the visitor gets no handoff card, no template and no button anywhere in the widget'
	);
	$check(
		403 === $denied->get_status()
		&& 'handoff_disabled' === ( (array) $denied->get_data() )['code']
		&& false === ( (array) $denied->get_data() )['ok']
		&& $before === $outbound,
		'A handoff request on a site with no ready channel is refused with handoff_disabled and nothing is sent'
	);
	$check( false === ( $handoff_status()['enabled'] ?? true ), 'GET /widget/handoff/status reports the handoff as unavailable in the not-ready state' );

	// A channel the site owner ticked but never finished must not create a button.
	$store_raw(
		array(
			'enabled'                  => true,
			'handoff_enabled'          => true,
			'handoff_email_enabled'    => false,
			'handoff_telegram_enabled' => true,
			'handoff_telegram_chat'    => '-100987654321',
		),
		true
	);
	$before    = $outbound;
	$half_done = $handoff_route( array( 'messages' => $transcript ) );
	$check(
		false === Settings::answer_desk_handoff_available()
		&& false === strpos( $render(), 'spw-handoff-template' )
		&& 403 === $half_done->get_status()
		&& $before === $outbound,
		'A ticked but unfinished channel (Telegram chat without a token) offers nothing and delivers nothing'
	);

	// Ready: one real email channel.
	$store_raw(
		array(
			'enabled'               => true,
			'suggestions'           => array( 'What are your opening hours?' ),
			'handoff_enabled'       => true,
			'handoff_email_enabled' => true,
			'handoff_email_to'      => 'qa-handoff-card-t11@example.invalid',
		),
		true
	);
	$html = $render();
	$check(
		1 === substr_count( $html, 'id="spw-handoff-template"' )
		&& 2 === substr_count( $html, 'data-spw-handoff>' )
		&& true === ( $handoff_status()['enabled'] ?? false ),
		'With one ready channel the card template is rendered once and exactly the two "talk to a person" buttons exist'
	);
	$card = 1 === preg_match( '#<template id="spw-handoff-template">(.*?)</template>#s', $html, $card_match ) ? $card_match[1] : '';
	$check(
		'' !== $card
		&& 1 === substr_count( $card, 'data-spw-handoff-send' )
		&& 1 === substr_count( $card, 'data-spw-handoff-dismiss' )
		&& false !== strpos( $card, 'Send this chat to our team' )
		&& false !== strpos( $card, 'Not now' )
		&& 1 === preg_match( '/<p class="spw-handoff__status" role="status" aria-live="polite">/', $card ),
		'The card sends only on its own button, offers "Not now", and announces its result in a live region'
	);
	$overclaims = array_values(
		array_filter(
			array( 'minute', 'seconds', 'right now', 'operator', 'is online', 'connected', 'live agent', 'typing' ),
			static fn( string $phrase ): bool => false !== stripos( wp_strip_all_tags( $card ), $phrase )
		)
	);
	$check( array() === $overclaims, 'The card promises no operator, no presence and no response time (' . count( $overclaims ) . ' over-claiming phrases found)' );
	$check(
		false !== strpos( $card, '>Talk to our team<' ) || false !== strpos( $html, '>Talk to a human</button>' ),
		'The default label a visitor presses is the translated "Talk to a human", not a hard-coded English string'
	);
	$check(
		false !== strpos( $customiser(), 'value="Talk to a human"' ),
		'The Widget tab prefills the same default label the renderer falls back to, so the two never disagree'
	);

	$script = $localized();
	$check(
		array() === array_filter( array_keys( $script ), static fn( string $key ): bool => 0 === strpos( $key, 'handoff' ) )
		&& '' !== ( $script['i18n']['sending'] ?? '' )
		&& '' !== ( $script['i18n']['sent'] ?? '' ),
		'The page carries no handoff copy of its own: the card is server-rendered and only its two status strings are localized'
	);
	$check(
		false === stripos( (string) ( $script['i18n']['sent'] ?? '' ), 'minute' )
		&& false === stripos( (string) ( $script['i18n']['sent'] ?? '' ), 'operator' )
		&& false === stripos( (string) ( $script['i18n']['sent'] ?? '' ), 'connected' ),
		'The confirmation the visitor reads after sending claims no operator and no response time'
	);

	$check(
		1 === preg_match( '/function syncChips\(\) \{(.*?)\n\t\}/s', $js, $sync )
		&& false !== strpos( $sync[1], "isProcessing || null !== qs('#spw-msgs .spw-handoff:not(.is-sent)')" )
		&& 1 === preg_match( '/function setBusy\(busy\) \{\s*isProcessing = busy;\s*syncChips\(\);/', $js )
		&& false === strpos( $js, 'chips.hidden = busy' ),
		'The suggestion chips step aside for both the writing assistant and a card still waiting for an answer (F-10)'
	);
	$check(
		1 === preg_match( '/function showHandoffCard\(focus\) \{(.*?)\n\t\}\n/s', $js, $offer )
		&& false === strpos( $offer[1], 'api(' )
		&& 2 === substr_count( $offer[1], 'syncChips();' )
		&& 1 === substr_count( $js, "api('widget/handoff', body)" ),
		'Offering the card sends nothing, and the chips come back the moment the visitor picks "Not now"'
	);
	$check(
		false !== strpos( $js, "content: typeof node.spwPlain === 'string' ? node.spwPlain : node.textContent," )
		&& false !== strpos( $js, 'bubble.spwPlain = plain;' )
		&& false === strpos( $js, 'content: node.textContent,' ),
		'The transcript sent to the team keeps the paragraphs and list items of a rendered answer instead of running them together'
	);

	// ── Honest voice contracts (T12) ──────────────────────
	$store_raw(
		array(
			'enabled'       => true,
			'voice_enabled' => true,
		),
		true
	);
	$html = $render();
	$check(
		1 === preg_match( '/<p class="spw-live" id="spw-voice-status" role="status" aria-live="polite" data-spw-voice-status><\/p>/', $html )
		&& 1 === substr_count( $html, 'data-spw-voice-status' ),
		'With voice on the composer carries exactly one polite status region for the recording phases (F-16)'
	);
	$check(
		false !== strpos( $css, '.spw-root .spw-live { position: absolute;' )
		&& false === strpos( $css, '.spw-root .spw-live { display: none' ),
		'The status region is visually hidden rather than display:none, so assistive tech still announces it'
	);

	$script = $localized();
	$check(
		'Transcribing your recording…' === ( $script['i18n']['transcribing'] ?? '' )
		&& false !== strpos( (string) ( $script['i18n']['transcriptReady'] ?? '' ), 'Nothing was sent yet.' ),
		'The two voice phase strings reach the widget, and the transcript one says plainly that nothing was sent'
	);

	$store_raw(
		array(
			'enabled'       => true,
			'voice_enabled' => false,
		),
		true
	);
	$check(
		false === strpos( $render(), 'data-spw-voice-status' ),
		'With voice switched off there is no voice status region to announce a phase that cannot happen'
	);

	// widget.js: every voice phase writes the region, and none of them sends anything.
	$check(
		1 === substr_count( $js, 'function setVoiceStatus(text)' )
		&& 1 === substr_count( $js, "region.textContent = text || '';" )
		&& 1 === substr_count( $js, "setVoiceStatus(str('recording'," )
		&& 1 === substr_count( $js, "setVoiceStatus(str('transcribing'," )
		&& 1 === substr_count( $js, "setVoiceStatus(str('transcriptReady'," ),
		'setVoiceStatus() is the only writer of the region and announces recording, transcribing and transcript ready (F-16)'
	);
	$check(
		7 === substr_count( $js, "setVoiceStatus('');" ),
		'All six voice failure paths and the deliberate send clear the region, so no stale phase is left announced'
	);
	$check(
		1 === preg_match( '/function sendAudio\(blob, durationMs\) \{(.*?)
	\}/s', $js, $audio )
		// Comment lines are dropped first: they may name send() while the code never calls it.
		&& false === strpos( (string) preg_replace( '/^\s*\/\/.*$/m', '', $audio[1] ), 'send(' )
		&& false !== strpos( $audio[1], 'input.value = data.transcript;' ),
		'A finished transcript only fills the composer: sendAudio() never calls send(), so the mic cannot ask a question on its own'
	);
	$check(
		false === stripos( $js, 'barge' )
		&& false === strpos( $js, 'spw-voice-overlay' )
		&& false === strpos( $js, 'SpeechRecognition' ),
		'No full-screen voice overlay, live caption or barge-in is built, in line with D-02 and what the backend can really do'
	);
	$check(
		1 === preg_match( '/utterance\.onboundary = function \(e\) \{(.*?)
		\};/s', $js, $boundary )
		&& false !== strpos( $boundary[1], 'idx >= ws.start && idx < ws.end' )
		&& false === strpos( $boundary[1], 'charIdx' ),
		'Read-aloud highlighting compares each word against its own offsets in the spoken text (F-19)'
	);

	// ── POST /public/voice follows the mic switch (F-09) ───
	$voice_scope   = 'aisaautopilot_rate_public_voice_' . hash_hmac( 'sha256', 'ip:' . substr( preg_replace( '/[^0-9a-fA-F:.]/', '', (string) ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) ) ?? '', 0, 64 ), wp_salt( 'auth' ) );
	$voice_counter = get_transient( $voice_scope );

	$transcribe = static function (): array {
		$request  = new WP_REST_Request( 'POST', '/aisaautopilot/v1/public/voice' );
		$response = ( new PublicChatController() )->transcribe( $request );
		return array(
			'status' => $response->get_status(),
			'data'   => (array) $response->get_data(),
		);
	};

	delete_transient( $voice_scope );
	$off = $transcribe();
	$check(
		403 === $off['status'] && 'voice_disabled' === $off['data']['code'] && empty( $off['data']['ok'] ),
		'With the mic switched off POST /public/voice refuses with 403 voice_disabled instead of transcribing for anyone who calls it (F-09)'
	);
	$check(
		false === get_transient( $voice_scope ),
		'The refusal happens before the rate limiter, so a disabled site spends neither the visitor quota nor a provider call'
	);

	$store_raw(
		array(
			'enabled'       => true,
			'voice_enabled' => true,
		),
		true
	);
	$on = $transcribe();
	$check(
		400 === $on['status'] && 'invalid_audio' === $on['data']['code'],
		'With the mic switched on the same call passes the gate and is refused only by audio validation, so the gate is the single change'
	);
	delete_transient( $voice_scope );
	if ( false !== $voice_counter ) {
		set_transient( $voice_scope, $voice_counter, HOUR_IN_SECONDS );
	}

	$check( 0 === $outbound, 'Rendering, the script data, the settings route and the customiser made no outbound HTTP request or mail' );
} catch ( Throwable $error ) {
	$check( false, 'Front chat widget render contracts complete without a fatal error: ' . $error->getMessage() );
} finally {
	if ( $original === $missing ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $original, false );
	}
	$_POST = $original_post;
	while ( is_locale_switched() ) {
		restore_previous_locale();
	}
	wp_dequeue_script( 'aisaautopilot-widget' );
	wp_deregister_script( 'aisaautopilot-widget' );
	wp_dequeue_style( 'aisaautopilot-widget' );
	wp_deregister_style( 'aisaautopilot-widget' );
	remove_filter( 'pre_http_request', $block_http, 1 );
	remove_filter( 'pre_wp_mail', $block_mail, 1 );
	wp_set_current_user( $original_user );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
