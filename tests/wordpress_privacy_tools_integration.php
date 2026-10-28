<?php
/**
 * Local WordPress integration checks for the plugin's personal-data exporter and eraser
 * (Tools > Export / Erase Personal Data) and its suggested privacy-policy text.
 *
 * Fixtures: two handoff requests (one for the requested address, one for another), two
 * recorded visitor conversations (one mentions the address), and a console conversation of
 * a temporary user with that address. Proves the export finds exactly the matching rows
 * (case-insensitively), the eraser removes exactly those, and the policy text is offered.
 * Every fixture is removed in finally.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use AISAAutoPilot\Chat\ConversationManager;
use AISAAutoPilot\Chat\VisitorConversationStore;
use AISAAutoPilot\Support\HandoffInbox;
use AISAAutoPilot\Support\PrivacyTools;

global $wpdb;

$checks           = array();
$original_user_id = get_current_user_id();
$email            = 'privacy.subject@example.test';
$other            = 'someone.else@example.test';
$handoffs         = new HandoffInbox();
$visitors         = new VisitorConversationStore();
$console          = new ConversationManager();
$ids              = array(
	'handoff'       => 0,
	'handoff_other' => 0,
	'user'          => 0,
	'console'       => 0,
);
$tokens           = array( wp_generate_uuid4(), wp_generate_uuid4() );

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

$find = static function ( string $kind, string $key ) {
	foreach ( (array) apply_filters( 'wp_privacy_personal_data_' . $kind, array() ) as $id => $tool ) {
		if ( $key === $id ) {
			return $tool['callback'];
		}
	}
	return null;
};

$count = static function ( string $table, string $where, ...$args ) use ( $wpdb ): int {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}{$table} WHERE {$where}", ...$args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
};

try {
	HandoffInbox::ensure_schema();
	VisitorConversationStore::ensure_schema();
	$console->create_table();

	$ids['handoff']       = $handoffs->record(
		array(
			array(
				'role'    => 'user',
				'content' => 'Can someone call me about the wholesale order?',
			),
		),
		'Privacy Subject',
		'Privacy.Subject@Example.test',
		'new',
		'+15550194471',
		'https://subject.example.test'
	);
	$ids['handoff_other'] = $handoffs->record(
		array(
			array(
				'role'    => 'user',
				'content' => 'Unrelated request',
			),
		),
		'Someone Else',
		$other,
		'new'
	);
	$visitors->append_turn( $tokens[0], 'My email is ' . $email . ', please write to me.', 'We will.', array() );
	$visitors->append_turn( $tokens[1], 'What are your opening hours?', '9 to 5.', array() );

	$ids['user'] = (int) wp_insert_user(
		array(
			'user_login' => 'privacy_subject_' . wp_generate_password( 6, false ),
			'user_pass'  => wp_generate_password( 24 ),
			'user_email' => $email,
			'role'       => 'administrator',
		)
	);
	wp_set_current_user( $ids['user'] );
	$ids['console'] = $console->create( 'Privacy console fixture' );
	$console->add_message(
		$ids['console'],
		array(
			'role'    => 'user',
			'content' => 'What does our refund page say?',
		)
	);
	wp_set_current_user( 1 );

	$exporter = $find( 'exporters', 'aisaautopilot' );
	$eraser   = $find( 'erasers', 'aisaautopilot' );
	$check( is_callable( $exporter ) && is_callable( $eraser ), 'The plugin registers a personal-data exporter and eraser with WordPress' );

	$export = call_user_func( $exporter, strtoupper( $email ), 1 );
	$items  = (array) ( $export['data'] ?? array() );
	$groups = array_count_values( array_column( $items, 'group_id' ) );
	$flat   = (string) wp_json_encode( $items );
	$check(
		true === ( $export['done'] ?? false )
		&& 1 === ( $groups['aisaautopilot-handoffs'] ?? 0 )
		&& 1 === ( $groups['aisaautopilot-console'] ?? 0 )
		&& 1 === ( $groups['aisaautopilot-visitor-chats'] ?? 0 )
		&& false !== strpos( $flat, '+15550194471' )
		&& false !== strpos( $flat, 'wholesale order' )
		&& false !== strpos( $flat, 'refund page' )
		&& false === strpos( $flat, 'Unrelated request' )
		&& false === strpos( $flat, 'opening hours' ),
		'The export holds the handoff request, the console conversation and the visitor conversation that names the address, and nothing else'
	);

	$erased = call_user_func( $eraser, $email, 1 );
	$check(
		true === ( $erased['items_removed'] ?? false )
		&& true === ( $erased['done'] ?? false )
		&& 0 === $count( HandoffInbox::TABLE, 'id = %d', $ids['handoff'] )
		&& 1 === $count( HandoffInbox::TABLE, 'id = %d', $ids['handoff_other'] )
		&& 0 === $count( ConversationManager::TABLE, 'id = %d', $ids['console'] )
		&& 0 === $count( VisitorConversationStore::TABLE, 'session_token = %s', $tokens[0] )
		&& 1 === $count( VisitorConversationStore::TABLE, 'session_token = %s', $tokens[1] ),
		'Erasing removes exactly the matching request and conversations; other people\'s rows stay'
	);
	$again = call_user_func( $eraser, $email, 1 );
	$check(
		false === ( $again['items_removed'] ?? true ) && true === ( $again['done'] ?? false ) && array() === (array) ( call_user_func( $exporter, $email, 1 )['data'] ?? array( 1 ) ),
		'After erasing, a second erase removes nothing and an export is empty'
	);

	// WordPress takes the text only in wp-admin during admin_init, so check the hook and the text.
	$hooked = false;
	foreach ( (array) ( $GLOBALS['wp_filter']['admin_init']->callbacks ?? array() ) as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$fn     = $callback['function'] ?? null;
			$hooked = $hooked || ( is_array( $fn ) && $fn[0] instanceof PrivacyTools && 'add_policy_text' === $fn[1] );
		}
	}
	$text = PrivacyTools::policy_text();
	$check(
		$hooked && false !== strpos( $text, 'talk to a person' ) && false !== strpos( $text, 'recording on' ) && false !== strpos( $text, 'by the email address you gave' ),
		'The suggested privacy-policy text is offered on admin_init and explains what the chat sends and keeps'
	);
} catch ( Throwable $error ) {
	$check( false, 'Privacy tools contract completes without a fatal error: ' . $error->getMessage() );
} finally {
	foreach ( array( 'handoff', 'handoff_other' ) as $key ) {
		if ( $ids[ $key ] > 0 ) {
			$wpdb->delete( $wpdb->prefix . HandoffInbox::TABLE, array( 'id' => $ids[ $key ] ), array( '%d' ) );
		}
	}
	foreach ( $tokens as $token ) {
		$wpdb->delete( $wpdb->prefix . VisitorConversationStore::TABLE, array( 'session_token' => $token ), array( '%s' ) );
	}
	if ( $ids['console'] > 0 ) {
		$wpdb->delete( $wpdb->prefix . ConversationManager::TABLE, array( 'id' => $ids['console'] ), array( '%d' ) );
	}
	if ( $ids['user'] > 0 ) {
		wp_delete_user( $ids['user'] );
	}
	wp_set_current_user( $original_user_id );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
