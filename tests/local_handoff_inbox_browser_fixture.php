<?php
/**
 * Creates and restores the isolated handoff inbox browser-QA fixture.
 *
 * Prepare records five marker-named threads: new with an email, in progress without one, delivery
 * failed, resolved with a phone and website but no email, and an archived resolved thread. Restore deletes only rows whose visitor name starts with the marker.
 * No handoff is delivered and no channel setting is changed.
 */

declare(strict_types=1);

$mode = $argv[1] ?? 'prepare';
if ( ! in_array( $mode, array( 'prepare', 'restore' ), true ) ) {
	fwrite( STDERR, "Usage: php local_handoff_inbox_browser_fixture.php prepare|restore\n" );
	exit( 64 );
}

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Support\HandoffInbox;

const FIXTURE_OPTION = 'aisaautopilot_handoff_inbox_browser_fixture';
const FIXTURE_MARKER = 'AutoPilot QA · ';

$restore = static function (): void {
	global $wpdb;
	// No snapshot means no fixture to restore. Never touch real visitor threads here.
	if ( null === get_option( FIXTURE_OPTION, null ) ) {
		return;
	}
	$table = ( new HandoffInbox() )->table();
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE visitor_name LIKE %s", $wpdb->esc_like( FIXTURE_MARKER ) . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table name.
	delete_option( FIXTURE_OPTION );
};

if ( 'restore' === $mode ) {
	$restore();
	echo "Handoff inbox browser fixture removed.\n";
	exit( 0 );
}

$restore();

try {
	HandoffInbox::ensure_schema();
	// Persist the snapshot before any row exists, so an interrupted prepare can still be restored.
	update_option( FIXTURE_OPTION, array( 'ids' => array() ), false );

	$inbox = new HandoffInbox();
	$new   = $inbox->record(
		array(
			array(
				'role'    => 'user',
				'content' => 'Can I change the delivery address on order 4821?',
			),
			array(
				'role'    => 'assistant',
				'content' => 'I can’t change an order from here, so I’ve asked a person to help.',
			),
			array(
				'role'    => 'user',
				'content' => 'Thanks, I’ll wait for an email.',
			),
		),
		FIXTURE_MARKER . 'Maya Chen',
		'maya.chen@example.com',
		'new'
	);
	$in_progress = $inbox->record(
		array(
			array(
				'role'    => 'user',
				'content' => 'Is the linen shirt available in XXL?',
			),
		),
		FIXTURE_MARKER . 'Lena Braun',
		'',
		'new'
	);
	$inbox->set_status( $in_progress, 'in_progress' );
	$failed = $inbox->record(
		array(
			array(
				'role'    => 'user',
				'content' => 'The code SUMMER is not working at checkout.',
			),
		),
		FIXTURE_MARKER . 'Omid Rahimi',
		'omid@example.com',
		'failed'
	);
	$resolved = $inbox->record(
		array(
			array(
				'role'    => 'user',
				'content' => 'Could someone call me about a bulk order for our studio?',
			),
		),
		FIXTURE_MARKER . 'Jonas Weber',
		'',
		'resolved',
		'+49 30 1234 5678',
		'https://weber-atelier.example'
	);
	$archived = $inbox->record(
		array(
			array(
				'role'    => 'user',
				'content' => 'Do you still sell the old catalogue?',
			),
		),
		FIXTURE_MARKER . 'Sara Ahmadi',
		'sara@example.com',
		'resolved'
	);
	$inbox->set_archived( $archived, true );
	if ( $new <= 0 || $in_progress <= 0 || $failed <= 0 || $resolved <= 0 || $archived <= 0 ) {
		throw new RuntimeException( 'The handoff inbox did not record the fixture threads.' );
	}
	update_option( FIXTURE_OPTION, array( 'ids' => array( $new, $in_progress, $failed, $resolved, $archived ) ), false );

	echo wp_json_encode(
		array(
			'new'         => $new,
			'in_progress' => $in_progress,
			'failed'      => $failed,
			'resolved'    => $resolved,
			'archived'    => $archived,
		)
	) . PHP_EOL;
} catch ( Throwable $error ) {
	$restore();
	fwrite( STDERR, $error->getMessage() . PHP_EOL );
	exit( 1 );
}
