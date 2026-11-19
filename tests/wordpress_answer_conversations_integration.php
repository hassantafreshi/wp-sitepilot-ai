<?php
/**
 * Local WordPress integration checks for Answer Desk Conversations.
 *
 * The test uses a UUID-scoped VisitorConversationStore fixture. It never calls
 * an AI provider, a remote URL, or a delivery channel.
 *
 * Run: C:\xampp\php\php.exe tests\wordpress_answer_conversations_integration.php
 */
declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\AnswerConversationsPage;
use AISAAutoPilot\Chat\VisitorConversationStore;
use AISAAutoPilot\Core\Settings;

wp_set_current_user( 1 );

$checks            = array();
$missing           = new stdClass();
$original_settings = get_option( Settings::OPTION, $missing );
$conversation_token = '';
$source_post_id    = 0;
$store             = new VisitorConversationStore();

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'name' => $name,
		'ok'   => $ok,
	);
};

$set_logging = static function ( bool $enabled ): void {
	$settings                                  = Settings::defaults();
	$settings['public_assistant_enabled']      = true;
	$settings['widget']                        = Settings::widget_defaults();
	$settings['widget']['enabled']             = true;
	$settings['widget']['conversation_logging_enabled'] = $enabled;
	update_option( Settings::OPTION, $settings, false );
};

$render_page = static function ( ?Closure $conversation_lister = null ): string {
	ob_start();
	( new AnswerConversationsPage( $conversation_lister ) )->render();
	return (string) ob_get_clean();
};

try {
	VisitorConversationStore::ensure_schema();

	$set_logging( false );
	$logging_off_markup = $render_page();
	$check(
		false !== strpos( $logging_off_markup, 'data-sp-answer-desk' )
		&& false !== strpos( $logging_off_markup, 'data-sp-answer-tab="conversations"' )
		&& false !== strpos( $logging_off_markup, 'data-sp-answer-state="logging-off"' )
		&& false === strpos( $logging_off_markup, 'data-sp-answer-conversation-list' )
		&& false === strpos( $logging_off_markup, 'data-sp-answer-conversation' ),
		'Conversations exposes the canonical logging-off state without a retained-conversation list'
	);

	$set_logging( true );
	$empty_markup = $render_page( static fn(): array => array() );
	$check(
		false !== strpos( $empty_markup, 'data-sp-answer-desk' )
		&& false !== strpos( $empty_markup, 'data-sp-answer-tab="conversations"' )
		&& false !== strpos( $empty_markup, 'data-sp-answer-state="empty"' )
		&& false === strpos( $empty_markup, 'data-sp-answer-conversation-list' )
		&& false === strpos( $empty_markup, 'data-sp-answer-conversation' ),
		'Conversations exposes the canonical empty state when opt-in recording has no retained data'
	);

	$source_post_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'AISAAutoPilot Answer Desk source fixture',
			'post_content' => 'Temporary source for the Answer Desk Conversations integration check.',
		),
		true
	);
	if ( is_wp_error( $source_post_id ) ) {
		$source_post_id = 0;
	}

	$source_title = 'AISAAutoPilot Answer Desk source fixture';
	$source_url   = $source_post_id > 0 ? (string) get_permalink( $source_post_id ) : '';
	$question     = 'What are the Answer Desk fixture support hours?';
	$answer       = 'The Answer Desk fixture is answered from its retained source.';
	$conversation_token = wp_generate_uuid4();
	$stored = $store->append_turn(
		$conversation_token,
		$question,
		$answer,
		array(
			array(
				'title' => $source_title,
				'url'   => $source_url,
			),
		)
	);
	$stored_messages = (array) ( $stored['messages'] ?? array() );
	$stored_rows     = array_values(
		array_filter(
			$store->list(),
			static fn( array $item ): bool => $conversation_token === (string) ( $item['session_token'] ?? '' )
		)
	);

	$ready_markup = $render_page( static fn(): array => $stored_rows );
	$check(
		$source_post_id > 0
		&& '' !== $source_url
		&& 2 === count( $stored_messages )
		&& false !== strpos( $ready_markup, 'data-sp-answer-desk' )
		&& false !== strpos( $ready_markup, 'data-sp-answer-tab="conversations"' )
		&& false !== strpos( $ready_markup, 'data-sp-answer-state="ready"' )
		&& 1 === substr_count( $ready_markup, 'data-sp-answer-conversation-list' )
		&& false !== strpos( $ready_markup, 'data-sp-answer-conversation' )
		&& false !== strpos( $ready_markup, esc_html( $question ) )
		&& false !== strpos( $ready_markup, esc_html( $answer ) )
		&& false !== strpos( $ready_markup, 'data-sp-answer-time' ),
		'Conversations renders its real retained title, time, and message fixture in the ready state'
	);
	$check(
		false !== strpos( $ready_markup, 'data-sp-answer-source' )
		&& false !== strpos( $ready_markup, esc_html( $source_title ) )
		&& false !== strpos( $ready_markup, esc_url( $source_url ) ),
		'Conversations renders an actual stored assistant citation as a source link'
	);
	$check(
		false === stripos( $ready_markup, '94% answered without you' )
		&& false === stripos( $ready_markup, 'Visitor ·' )
		&& false === stripos( $ready_markup, 'Needs a person' )
		&& false === stripos( $ready_markup, 'AutoPilot is writing a reply' ),
		'Conversations does not invent confidence, geography, or live-status data'
	);
} finally {
	if ( '' !== $conversation_token ) {
		global $wpdb;
		$wpdb->delete( $store->table(), array( 'session_token' => $conversation_token ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- removes only this test's UUID-scoped fixture.
	}
	if ( $source_post_id > 0 ) {
		wp_delete_post( $source_post_id, true );
	}
	if ( $original_settings === $missing ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $original_settings, false );
	}
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
