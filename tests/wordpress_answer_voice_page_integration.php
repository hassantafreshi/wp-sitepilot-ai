<?php
/**
 * Local WordPress integration checks for the mockup-aligned Answer Desk Voice
 * tab. No recording, provider request or browser speech request is made.
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\AnswerConversationsPage;
use AISAAutoPilot\Admin\AnswerDeskNavigation;
use AISAAutoPilot\Core\Settings;

$missing           = new stdClass();
$original_settings = get_option( Settings::OPTION, $missing );
$original_get      = $_GET;
$original_user_id  = get_current_user_id();
$checks            = array();

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

$render_voice = static function (): string {
	$_GET['tab'] = 'voice';
	ob_start();
	( new AnswerConversationsPage() )->render();
	return (string) ob_get_clean();
};

try {
	wp_set_current_user( 1 );
	Settings::update(
		array(
			'public_assistant_enabled' => true,
			'voice_model'              => 'whisper-1',
			'widget'                   => array_merge(
				Settings::widget_defaults(),
				array(
					'enabled'       => true,
					'voice_enabled' => true,
					'voice_auto'    => true,
					'voice_name'    => 'A private browser voice name',
				)
			),
		)
	);
	$tabs   = AnswerDeskNavigation::subtabs( AnswerDeskNavigation::TAB_VOICE );
	$markup = $render_voice();

	$check(
		array( 'conversations', 'widget', 'handoff', 'voice' ) === array_column( $tabs, 'id' )
		&& false !== strpos( (string) $tabs[3]['url'], 'page=aisaautopilot-conversations&tab=voice' )
		&& false !== strpos( $markup, 'data-sp-answer-desk' )
		&& false !== strpos( $markup, 'data-sp-answer-tab="voice"' )
		&& false !== strpos( $markup, 'data-sp-voice-settings' ),
		'Real-time voice is served from the canonical Answer Desk route with the four mockup tabs in order'
	);
	$check(
		false !== strpos( $markup, 'Real-time voice mode' )
		&& false !== strpos( $markup, 'data-sp-voice-realtime-locked' )
		&& false !== strpos( $markup, 'Not available in this release' )
		&& false !== strpos( $markup, 'data-sp-voice-engine-locked' ),
		'The mockup realtime stream is visibly locked instead of being presented as a working provider feature'
	);
	$check(
		false !== strpos( $markup, 'data-sp-voice-engine="transcribe"' )
		&& false !== strpos( $markup, 'data-sp-voice-auto-toggle' )
		&& false !== strpos( $markup, 'data-sp-voice-test' )
		&& false !== strpos( $markup, 'Plays locally in this administrator’' )
		&& false !== strpos( $markup, 'STT model: whisper-1' ),
		'The Voice tab connects only the implemented transcribe-and-browser-speak controls and identifies the STT model'
	);
	$check(
		false === strpos( $markup, 'A private browser voice name' )
		&& false === strpos( $markup, 'voice_responses_enabled' )
		&& false === strpos( $markup, 'name="voice_model"' )
		&& false === strpos( $markup, 'name="widget[voice_enabled]"' ),
		'Canonical Voice keeps device-specific legacy fields and unrelated settings out of the mockup surface'
	);
} finally {
	$_GET = $original_get;
	if ( $original_settings === $missing ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $original_settings, false );
	}
	wp_set_current_user( $original_user_id );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
