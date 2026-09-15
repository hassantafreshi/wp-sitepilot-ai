<?php
/**
 * Local WordPress integration checks for the mockup-aligned Answer Desk
 * Human handoff tab. No provider request, connection test or delivery runs.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\AnswerConversationsPage;
use AISAAutoPilot\Admin\AnswerDeskNavigation;
use AISAAutoPilot\Core\Settings;

$missing           = new stdClass();
$original_settings = get_option( Settings::OPTION, $missing );
$original_secrets  = get_option( Settings::HANDOFF_SECRET_OPTION, $missing );
$original_get      = $_GET;
$original_user_id  = get_current_user_id();
$checks            = array();

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

$render_handoff = static function (): string {
	$_GET['tab'] = 'handoff';
	ob_start();
	( new AnswerConversationsPage() )->render();
	return (string) ob_get_clean();
};

try {
	wp_set_current_user( 1 );
	Settings::update(
		array(
			'public_assistant_enabled' => true,
			'widget'                   => array_merge(
				Settings::widget_defaults(),
				array(
					'enabled'                  => true,
					'handoff_enabled'          => true,
					'handoff_email_enabled'    => true,
					'handoff_email_to'         => 'handoff-page@example.test',
					'handoff_telegram_enabled' => true,
					'handoff_telegram_chat'    => '-1001234567890',
					'handoff_twilio_enabled'   => false,
					'handoff_twilio_from'      => '',
					'handoff_twilio_to'        => '',
					'voice_enabled'            => true,
					'handoff_text'             => 'Legacy handoff label',
				)
			),
		)
	);
	Settings::set_handoff_secret( 'handoff_telegram_token', '123456789:abcdefghijklmnop' );
	$tabs   = AnswerDeskNavigation::subtabs( AnswerDeskNavigation::TAB_HANDOFF );
	$markup = $render_handoff();

	$check(
		array( 'conversations', 'widget', 'handoff', 'voice' ) === array_column( $tabs, 'id' )
		&& false !== strpos( (string) $tabs[2]['url'], 'page=aisaautopilot-conversations&tab=handoff' )
		&& false !== strpos( $markup, 'data-sp-answer-desk' )
		&& false !== strpos( $markup, 'data-sp-answer-tab="handoff"' )
		&& false !== strpos( $markup, 'data-sp-handoff-settings' ),
		'Human handoff is served from the canonical Answer Desk route with the four mockup tabs in order'
	);
	$check(
		false !== strpos( $markup, 'data-sp-handoff-channel="email"' )
		&& false !== strpos( $markup, 'data-sp-handoff-channel="telegram"' )
		&& false !== strpos( $markup, 'data-sp-handoff-channel="twilio"' )
		&& false !== strpos( $markup, 'data-sp-handoff-unsupported="whatsapp"' )
		&& false !== strpos( $markup, 'data-sp-handoff-unsupported="slack"' )
		&& false !== strpos( $markup, 'data-sp-handoff-unsupported="discord"' )
		&& false !== strpos( $markup, 'data-sp-handoff-unsupported="webhook"' )
		&& false !== strpos( $markup, 'Add another channel' ),
		'Channel cards mirror the mockup while unsupported transports are visibly locked rather than presented as live'
	);
	$check(
		false !== strpos( $markup, 'When a visitor asks for a person, the plugin sends the handoff to every enabled and configured channel below.' )
		&& false !== strpos( $markup, 'Check connection' )
		&& false !== strpos( $markup, 'data-sp-handoff-test-url=' )
		&& false === strpos( $markup, 'Send a test message' )
		&& false === strpos( $markup, '123456789:abcdefghijklmnop' ),
		'Human handoff explains the actual fan-out delivery model and exposes only a consented connection check without leaking credentials'
	);
	$check(
		false === strpos( $markup, 'name="widget[voice_enabled]"' )
		&& false === strpos( $markup, 'Legacy handoff label' )
		&& false === strpos( $markup, 'Escalated conversations' )
		&& false === strpos( $markup, 'Handoff inbox' ),
		'Canonical handoff tab stays within the mockup channel configuration scope and leaves unrelated legacy controls out'
	);
} finally {
	$_GET = $original_get;
	if ( $original_settings === $missing ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $original_settings, false );
	}
	if ( $original_secrets === $missing ) {
		delete_option( Settings::HANDOFF_SECRET_OPTION );
	} else {
		update_option( Settings::HANDOFF_SECRET_OPTION, $original_secrets, false );
	}
	wp_set_current_user( $original_user_id );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
