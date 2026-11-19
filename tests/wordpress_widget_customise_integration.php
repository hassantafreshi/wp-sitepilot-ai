<?php
/**
 * Local WordPress contracts for the advanced Widget settings (customise and privacy).
 *
 * Proves the page renders only customise and privacy controls, and that saving writes only
 * the submitted keys: visibility, voice, handoff channels and credentials stay untouched.
 * The settings option, the active user, the request globals and the locale are restored in
 * finally.
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Frontend\ChatWidgetSettings;

$missing       = new stdClass();
$original      = get_option( Settings::OPTION, $missing );
$original_user = get_current_user_id();
$original_post = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- snapshot for restore.
$checks        = array();

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

$render = static function ( array $post = array() ): string {
	$_POST = $post;
	ob_start();
	( new ChatWidgetSettings() )->render();
	$html  = (string) ob_get_clean();
	$_POST = array();
	return $html;
};

$widget = static function (): array {
	return array_merge( Settings::widget_defaults(), (array) Settings::get( 'widget', array() ) );
};

try {
	wp_set_current_user( 1 );
	switch_to_locale( 'en_US' );

	$telegram_secret_before = Settings::has_handoff_secret( 'handoff_telegram_token' );
	Settings::update(
		array(
			'widget' => array_merge(
				$widget(),
				array(
					'enabled'                      => false,
					'voice_enabled'                => true,
					'voice_auto'                   => false,
					'handoff_enabled'              => true,
					'handoff_email_enabled'        => true,
					'handoff_telegram_enabled'     => false,
					'conversation_logging_enabled' => false,
					'title'                        => 'Northwind help',
					'position'                     => 'bottom-right',
					'bubble_radius'                => 30,
				)
			),
		)
	);

	$html = $render();
	$check(
		false !== strpos( $html, 'data-sp-widget' ) && false !== strpos( $html, 'widget[title]' ) && false !== strpos( $html, 'sp-w-stage' ),
		'Widget customisation keeps the settings form and live preview contract'
	);
	$check(
		false === strpos( $html, 'widget[handoff_telegram_token]' )
		&& false === strpos( $html, 'widget[handoff_twilio_sid]' )
		&& false === strpos( $html, 'name="widget[voice_enabled]"' )
		&& false === strpos( $html, 'name="widget[enabled]"' )
		&& false === strpos( $html, 'name="widget[handoff_enabled]"' ),
		'Credentials, voice, visibility and the handoff switch are left to their own tabs'
	);
	$check(
		12 === substr_count( $html, 'data-sp-color="' )
		&& 4 === substr_count( $html, 'data-sp-slot="widget[icons]' )
		&& false !== strpos( $html, 'id="answer-desk-widget"' )
		&& false !== strpos( $html, 'id="answer-desk-privacy"' ),
		'The page offers twelve part colours, four icon slots and the customise and privacy anchors'
	);
	$check(
		1 === preg_match( '/<input type="hidden" name="widget\[conversation_logging_enabled\]" value="0" \/>\s*<input type="checkbox" class="sp-wz-switch"/', $html )
		&& false !== strpos( $html, 'data-sp-savebar hidden' )
		&& false !== strpos( $html, 'Unsaved widget changes — visitors still see the saved version' ),
		'The recording switch posts an explicit off value and the save bar starts hidden'
	);

	$saved_html = $render(
		array(
			'_wpnonce' => wp_create_nonce( 'sp_widget_save' ),
			'widget'   => array(
				'position'                     => 'top-left',
				'theme'                        => 'dark',
				'title'                        => '   ',
				'greeting'                     => 'Hello there',
				'handoff_text'                 => 'Talk to us',
				'conversation_logging_enabled' => '1',
				'conversation_notice'          => str_repeat( 'a', 300 ),
				'bubble_radius'                => '99',
			),
		)
	);
	$after = $widget();
	$check( false !== strpos( $saved_html, 'Settings saved.' ), 'A valid save confirms it on the page' );
	$check(
		'top-left' === $after['position'] && 'dark' === $after['theme'] && 'Hello there' === $after['greeting'] && 'Talk to us' === $after['handoff_text'],
		'Submitted customise fields are stored'
	);
	$check( 'Northwind help' === $after['title'], 'A required title submitted empty keeps its previous value' );
	$check(
		true === $after['conversation_logging_enabled'] && 240 === mb_strlen( (string) $after['conversation_notice'] ) && 30 === (int) $after['bubble_radius'],
		'Recording turns on, the notice is capped at 240 characters and the button corners are clamped'
	);
	$check(
		false === $after['enabled']
		&& true === $after['voice_enabled']
		&& false === $after['voice_auto']
		&& true === $after['handoff_enabled']
		&& true === $after['handoff_email_enabled']
		&& false === $after['handoff_telegram_enabled']
		&& Settings::has_handoff_secret( 'handoff_telegram_token' ) === $telegram_secret_before,
		'Keys the form does not show (visibility, voice, handoff channels, credentials) stay exactly as they were'
	);

	$render(
		array(
			'_wpnonce' => wp_create_nonce( 'sp_widget_save' ),
			'widget'   => array( 'conversation_logging_enabled' => '0' ),
		)
	);
	$check( false === $widget()['conversation_logging_enabled'] && 'top-left' === $widget()['position'], 'The explicit off value turns recording off without touching anything else' );

	$check(
		false !== strpos( $html, 'id="answer-desk-not-found"' )
		&& false !== strpos( $html, 'data-sp-radio-group="not-found"' )
		&& 1 === preg_match( '/name="widget\[not_found_action\]" value="message"\s+checked/', $html )
		&& false !== strpos( $html, 'name="widget[not_found_message]" maxlength="300"' ),
		'The "When there is no answer" card offers a message (chosen by default) or a handoff, and a message field'
	);
	$render(
		array(
			'_wpnonce' => wp_create_nonce( 'sp_widget_save' ),
			'widget'   => array(
				'not_found_action'  => 'handoff',
				'not_found_message' => '  ' . str_repeat( 'b', 400 ),
			),
		)
	);
	$check(
		'handoff' === $widget()['not_found_action'] && 300 === mb_strlen( (string) $widget()['not_found_message'] ) && 'top-left' === $widget()['position'],
		'The "not found" choice is stored and its message is trimmed and capped at 300 characters'
	);
	$render(
		array(
			'_wpnonce' => wp_create_nonce( 'sp_widget_save' ),
			'widget'   => array(
				'not_found_action'  => 'escalate-everything',
				'not_found_message' => '',
			),
		)
	);
	$check(
		'message' === $widget()['not_found_action'] && '' === $widget()['not_found_message'],
		'An unknown "not found" choice falls back to the message, and an empty message means the built-in text'
	);

	$render(
		array(
			'_wpnonce' => 'not-a-valid-nonce',
			'widget'   => array( 'position' => 'bottom-left' ),
		)
	);
	$check( 'top-left' === $widget()['position'], 'A request without a valid nonce changes nothing' );

	if ( switch_to_locale( 'fa_IR' ) ) {
		$fa = $render();
		restore_previous_locale();
		$check( 1 === preg_match( '/<h2 class="sp-wz-intro__title">[^<]*[\x{0600}-\x{06FF}]/u', $fa ), 'fa_IR renders the widget customisation in Persian' );
	} else {
		$check( false, 'fa_IR is available for the widget customisation copy check' );
	}
} catch ( Throwable $error ) {
	$check( false, 'Widget customisation contracts complete without a fatal error: ' . $error->getMessage() );
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
	wp_set_current_user( $original_user );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
