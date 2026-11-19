<?php
/**
 * Local WordPress integration checks for the mockup-aligned Answer Desk
 * Human handoff tab. No provider request, connection test or delivery runs.
 * Two inbox rows are inserted with a unique marker and removed by id and marker in finally.
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\AdminMenu;
use AISAAutoPilot\Admin\AnswerConversationsPage;
use AISAAutoPilot\Admin\AnswerDeskNavigation;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Support\HandoffInbox;

$missing           = new stdClass();
$original_settings = get_option( Settings::OPTION, $missing );
$original_secrets  = get_option( Settings::HANDOFF_SECRET_OPTION, $missing );
$original_get      = $_GET;
$original_user_id  = get_current_user_id();
$checks            = array();
$inbox             = new HandoffInbox();
$marker            = 'Handoff tab strip QA ' . wp_generate_password( 8, false, false );
$inserted          = array();

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
		&& false !== strpos( $markup, 'data-sp-handoff-channel="whatsapp"' )
		&& false !== strpos( $markup, 'data-sp-handoff-channel="slack"' )
		&& false !== strpos( $markup, 'data-sp-handoff-channel="signal"' )
		&& false !== strpos( $markup, 'data-sp-handoff-add="slack"' )
		&& false !== strpos( $markup, 'data-sp-handoff-add="signal"' )
		&& false === strpos( $markup, 'data-sp-handoff-unsupported="whatsapp"' )
		&& false === strpos( $markup, 'data-sp-handoff-unsupported="slack"' )
		&& false !== strpos( $markup, 'data-sp-handoff-unsupported="discord"' )
		&& false !== strpos( $markup, 'data-sp-handoff-unsupported="webhook"' )
		&& false !== strpos( $markup, 'Add another channel' ),
		'Every delivered channel has a card (WhatsApp shown, Slack and Signal under "Add another channel"), while unsupported transports stay visibly locked'
	);
	$check(
		false !== strpos( $markup, 'When a visitor asks for a person, the plugin sends the handoff to every enabled and configured channel below.' )
		&& false !== strpos( $markup, 'data-sp-handoff-delivery-mode="fan-out"' )
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
		&& false === strpos( $markup, 'data-sp-handoff-inbox-list' )
		&& false === strpos( $markup, 'data-sp-handoff-item' )
		&& false === strpos( $markup, 'data-sp-handoff-drawer' ),
		'Canonical handoff tab stays within the mockup channel configuration scope and leaves unrelated legacy controls out'
	);
	// The inbox lives on its own screen; this tab only carries the entry strip the design adds above the channel cards.
	$inbox_url = esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_HANDOFFS ) );
	$check(
		1 === substr_count( $markup, 'data-sp-handoff-inbox-link' )
		&& false !== strpos( $markup, 'href="' . $inbox_url . '" data-sp-handoff-inbox-link' )
		&& false !== strpos( $markup, 'Open inbox' ),
		'Human handoff links to the separate handoff inbox instead of embedding it'
	);

	// The strip reads real counts from the inbox table: one new and one failed thread are added, then removed by id and marker.
	$new_before    = $inbox->count_new();
	$failed_before = $inbox->count_failed();
	if ( $inbox->is_ready() ) {
		$inserted[] = $inbox->record(
			array(
				array(
					'role'    => 'user',
					'content' => 'Strip QA new',
				),
			),
			$marker,
			'strip-new@example.test',
			'new'
		);
		$inserted[] = $inbox->record(
			array(
				array(
					'role'    => 'user',
					'content' => 'Strip QA failed',
				),
			),
			$marker,
			'strip-failed@example.test',
			'failed'
		);
		$with_rows = $render_handoff();
		$check(
			$new_before + 1 === $inbox->count_new()
			&& $failed_before + 1 === $inbox->count_failed()
			&& false !== strpos( $with_rows, 'data-sp-handoff-inbox-strip="waiting"' )
			&& false !== strpos( $with_rows, 'data-sp-handoff-strip-new="' . ( $new_before + 1 ) . '"' )
			&& false !== strpos( $with_rows, 'data-sp-handoff-strip-failed="' . ( $failed_before + 1 ) . '"' )
			&& false !== strpos( $with_rows, 'Waiting for a person' ),
			'The inbox strip counts new and delivery-failed threads from the inbox table'
		);
	} else {
		$check( 0 === $new_before && 0 === $failed_before, 'Without the inbox table both strip counts are 0 and nothing is queried' );
	}

	// With handoff switched off no request can be taken, so the strip must not promise that requests are kept or delivered.
	Settings::update(
		array(
			'widget' => array_merge( (array) Settings::get( 'widget', array() ), array( 'handoff_enabled' => false ) ),
		)
	);
	$unavailable = $render_handoff();
	$check(
		false !== strpos( $unavailable, 'data-sp-handoff-inbox-strip="unavailable"' )
		&& false !== strpos( $unavailable, 'Visitors are not offered a person until handoff is on and at least one channel below is ready.' )
		&& false === strpos( $unavailable, 'Requests are still kept in the inbox, but nobody is notified' )
		&& 1 === preg_match( '/data-count-state="(waiting|quiet)" data-body-ready="[^"]+" data-body-unavailable="Visitors are not offered a person/', $unavailable ),
		'Without a ready handoff the strip says visitors are not offered a person'
	);
} finally {
	if ( array() !== $inserted ) {
		global $wpdb;
		foreach ( $inserted as $row_id ) {
			if ( $row_id > 0 ) {
				$wpdb->delete( $inbox->table(), array( 'id' => $row_id, 'visitor_name' => $marker ), array( '%d', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- removes only the rows this check inserted.
			}
		}
	}
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
