<?php
/**
 * Creates and removes a scoped local fixture for Answer Desk browser QA.
 *
 * It never calls a provider or handoff transport. The fixture exists only so
 * Playwright can inspect the truthful populated Conversations layout, then it
 * restores the exact previous settings and removes its own post/DB row.
 */
declare(strict_types=1);

$mode = $argv[1] ?? 'prepare';
if ( ! in_array( $mode, array( 'prepare', 'restore' ), true ) ) {
	fwrite( STDERR, "Usage: php local_answer_conversations_browser_fixture.php prepare|restore\n" );
	exit( 64 );
}

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Chat\VisitorConversationStore;
use AISAAutoPilot\Core\Settings;

const FIXTURE_OPTION = 'aisaautopilot_answer_conversations_browser_fixture';

/**
 * Restore a previous fixture, if any. This keeps repeated local QA invocations
 * from accumulating test posts or conversations.
 */
$restore = static function (): void {
	$record = get_option( FIXTURE_OPTION, null );
	// No snapshot means no fixture to restore. Never touch live settings here:
	// before T15 a missing snapshot fell through to delete_option( Settings::OPTION ).
	if ( null === $record ) {
		return;
	}
	if ( ! is_array( $record ) || ! array_key_exists( 'had_settings', $record ) ) {
		throw new RuntimeException( 'Answer Desk fixture snapshot is invalid; live settings were left untouched.' );
	}

	$token = sanitize_text_field( (string) ( $record['conversation_token'] ?? '' ) );
	if ( '' !== $token ) {
		$store = new VisitorConversationStore();
		global $wpdb;
		$wpdb->delete( $store->table(), array( 'session_token' => $token ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- only this fixture's UUID is removed.
	}

	$post_id = absint( $record['source_post_id'] ?? 0 );
	if ( $post_id > 0 ) {
		wp_delete_post( $post_id, true );
	}

	if ( ! empty( $record['had_settings'] ) ) {
		update_option( Settings::OPTION, $record['settings'], false );
	} else {
		delete_option( Settings::OPTION );
	}
	delete_option( FIXTURE_OPTION );
};

if ( 'restore' === $mode ) {
	$restore();
	echo "Answer Desk browser fixture removed.\n";
	exit( 0 );
}

$restore();

$missing          = new stdClass();
$original_settings = get_option( Settings::OPTION, $missing );
$source_post_id   = 0;
$conversation_token = '';

try {
	$source_post_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'AISAAutoPilot browser QA source',
			'post_content' => 'Temporary local source for the Answer Desk browser check.',
		),
		true
	);
	if ( is_wp_error( $source_post_id ) ) {
		throw new RuntimeException( $source_post_id->get_error_message() );
	}
	$source_post_id = (int) $source_post_id;
	$source_url     = (string) get_permalink( $source_post_id );
	if ( '' === $source_url ) {
		throw new RuntimeException( 'Could not resolve the browser fixture source URL.' );
	}

	$widget = array_merge(
		Settings::widget_defaults(),
		(array) ( Settings::all()['widget'] ?? array() ),
		array(
			'enabled'                      => true,
			'position'                     => 'bottom-right',
			'color'                        => '#202a8d',
			'custom_colors'                => false,
			'colors'                       => array(),
			'conversation_logging_enabled' => true,
			'title'                        => 'AutoPilot support',
			'subtitle'                     => 'Usually replies instantly',
			'greeting'                     => 'How can we help?',
			'suggestions'                  => array( 'What are your support hours?' ),
			'handoff_enabled'              => false,
			'handoff_email_enabled'        => false,
			'handoff_email_to'             => '',
			'handoff_telegram_enabled'     => false,
			'handoff_telegram_chat'        => '',
			'handoff_twilio_enabled'       => false,
			'handoff_twilio_from'          => '',
			'handoff_twilio_to'            => '',
			'voice_enabled'                => true,
			'voice_auto'                   => true,
			'voice_name'                   => '',
		)
	);
	Settings::update(
		array(
			'public_assistant_enabled' => true,
			'widget'                   => $widget,
		)
	);

	$conversation_token = wp_generate_uuid4();
	( new VisitorConversationStore() )->append_turn(
		$conversation_token,
		'What are your support hours?',
		'Our local browser QA source has the support-hours answer.',
		array(
			array(
				'title' => 'AISAAutoPilot browser QA source',
				'url'   => $source_url,
			),
		)
	);

	update_option(
		FIXTURE_OPTION,
		array(
			'had_settings'      => $original_settings !== $missing,
			'settings'          => $original_settings !== $missing ? $original_settings : array(),
			'source_post_id'    => $source_post_id,
			'conversation_token' => $conversation_token,
		),
		false
	);

	echo wp_json_encode(
		array(
			'source_post_id'    => $source_post_id,
			'conversation_token' => $conversation_token,
		)
	) . PHP_EOL;
} catch ( Throwable $error ) {
	if ( '' !== $conversation_token ) {
		$store = new VisitorConversationStore();
		global $wpdb;
		$wpdb->delete( $store->table(), array( 'session_token' => $conversation_token ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- only this fixture's UUID is removed.
	}
	if ( $source_post_id > 0 ) {
		wp_delete_post( $source_post_id, true );
	}
	if ( $original_settings === $missing ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $original_settings, false );
	}
	delete_option( FIXTURE_OPTION );
	fwrite( STDERR, $error->getMessage() . PHP_EOL );
	exit( 1 );
}
