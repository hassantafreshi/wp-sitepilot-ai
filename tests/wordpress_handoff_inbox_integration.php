<?php
/**
 * Local WordPress contracts for the handoff inbox.
 *
 * Records marker-named threads, renders the inbox, checks the thread data behind the drawer (contact
 * details included), proves the status route stores valid statuses and refuses unknown ones, and
 * archives, restores and bulk-archives threads. Every row created here is deleted in finally, resolved
 * threads this check did not create are moved back after the bulk archive, and no handoff is delivered.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\AnswerDeskHandoffPage;
use AISAAutoPilot\Admin\HandoffInboxPage;
use AISAAutoPilot\Api\HandoffInboxController;
use AISAAutoPilot\Support\HandoffInbox;

$original_user = get_current_user_id();
$checks        = array();
$created       = array();
$marker        = 'AutoPilot QA integration · ';

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

$json_script = static function ( string $html, string $id ): array {
	if ( 1 !== preg_match( '/<script type="application\/json" id="' . preg_quote( $id, '/' ) . '">(.*?)<\/script>/s', $html, $match ) ) {
		return array();
	}
	$data = json_decode( $match[1], true );
	return is_array( $data ) ? $data : array();
};

$status_request = static function ( int $id, string $status ): WP_REST_Request {
	$request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/handoffs/' . $id . '/status' );
	$request->set_param( 'id', $id );
	$request->set_param( 'status', $status );
	return $request;
};

try {
	wp_set_current_user( 1 );
	switch_to_locale( 'en_US' );
	HandoffInbox::ensure_schema();
	$inbox = new HandoffInbox();

	$long = array();
	for ( $i = 1; $i <= 15; $i++ ) {
		$long[] = array(
			'role'    => 0 === $i % 2 ? 'assistant' : 'user',
			'content' => 'Message ' . $i,
		);
	}
	$created['new']      = $inbox->record(
		array(
			array(
				'role'    => 'assistant',
				'content' => 'Hello, how can I help?',
			),
			array(
				'role'    => 'user',
				'content' => 'Can I change my delivery address?',
			),
		),
		$marker . 'Maya',
		'maya@example.com',
		'new'
	);
	$created['long']     = $inbox->record( $long, $marker . 'Long thread', 'not-an-email', 'failed' );
	$created['nameless'] = $inbox->record(
		array(
			array(
				'role'    => 'user',
				'content' => 'Anyone there?',
			),
		),
		'',
		'',
		'new'
	);
	$created['contact'] = $inbox->record(
		array(
			array(
				'role'    => 'user',
				'content' => 'Please call me about a wholesale order.',
			),
		),
		$marker . 'Sam',
		'',
		'in_progress',
		'+1 555 010 2030',
		'https://example.com/shop'
	);
	$created['script'] = $inbox->record(
		array(
			array(
				'role'    => 'user',
				'content' => 'Hello',
			),
		),
		$marker . 'Scheme',
		'',
		'resolved',
		'',
		'javascript:alert(1)'
	);

	ob_start();
	( new HandoffInboxPage() )->render();
	$html = (string) ob_get_clean();

	$data    = $json_script( $html, 'sp-handoff-inbox-data' );
	$threads = array();
	foreach ( (array) ( $data['threads'] ?? array() ) as $thread ) {
		$threads[ (int) $thread['id'] ] = $thread;
	}

	$check(
		false !== strpos( $html, 'data-sp-handoff-inbox-list' )
		&& 6 === substr_count( $html, 'data-sp-handoff-filter=' )
		&& false !== strpos( $html, 'data-sp-handoff-filter="archived"' )
		&& 3 === substr_count( $html, 'data-sp-handoff-channel-state=' ),
		'Inbox renders All, four status filters, Archived and the three real delivery channels'
	);
	$check(
		false !== strpos( $html, 'data-sp-handoff-archive>' )
		&& 1 === preg_match( '/data-sp-handoff-archive-resolved(?! hidden)[^>]*>/', $html ),
		'The drawer has an archive button and the inbox offers to archive resolved threads while one exists'
	);
	$contact_thread = $threads[ $created['contact'] ] ?? array();
	$check(
		'+1 555 010 2030' === ( $contact_thread['phone'] ?? '' )
		&& 'https://example.com/shop' === ( $contact_thread['website'] ?? '' )
		&& '+1 555 010 2030' === ( $contact_thread['contact'] ?? '' )
		&& '' === ( $threads[ $created['script'] ]['website'] ?? 'missing' )
		&& '' === (string) ( $inbox->get( $created['script'] )['visitor_website'] ?? 'missing' ),
		'Phone and website are kept for the team, the row falls back to the phone, and a non-web address is never stored or linked'
	);
	$check(
		1 === preg_match( '/data-sp-handoff-item="' . $created['new'] . '" data-status="new"/', $html )
		&& 1 === preg_match( '/data-sp-handoff-item="' . $created['long'] . '" data-status="failed"/', $html ),
		'Each thread row carries its stored status'
	);
	$check(
		false !== strpos( $html, '<div class="sp-hoi-drawer" data-sp-handoff-drawer hidden>' )
		&& false !== strpos( $html, 'role="dialog" aria-modal="true"' ),
		'The thread drawer is an initially hidden modal dialog'
	);
	$check( false !== strpos( $html, 'data-sp-handoff-new-badge>' ), 'The new-thread badge shows while threads wait' );
	$check(
		'Can I change my delivery address?' === ( $threads[ $created['new'] ]['question'] ?? '' )
		&& 'maya@example.com' === ( $threads[ $created['new'] ]['email'] ?? '' ),
		'A row shows the first visitor question rather than the assistant greeting'
	);
	$long_thread = $threads[ $created['long'] ] ?? array();
	$check(
		12 === count( (array) ( $long_thread['messages'] ?? array() ) )
		&& 'Message 4' === ( $long_thread['messages'][0]['content'] ?? '' )
		&& 'Message 15' === ( $long_thread['messages'][11]['content'] ?? '' )
		&& '' === ( $long_thread['email'] ?? 'missing' ),
		'A long thread keeps its last 12 messages and an invalid email is never offered for reply'
	);
	$check( 'Visitor' === ( $threads[ $created['nameless'] ]['name'] ?? '' ), 'A visitor who left no name is shown as Visitor' );

	$controller = new HandoffInboxController();
	$stored     = $controller->status( $status_request( $created['new'], 'resolved' ) );
	$check(
		200 === $stored->get_status() && 'resolved' === (string) ( $inbox->get( $created['new'] )['status'] ?? '' ),
		'The status route stores a valid status'
	);
	$refused = $controller->status( $status_request( $created['new'], 'archived' ) );
	$check(
		400 === $refused->get_status()
		&& 'invalid_status' === ( $refused->get_data()['code'] ?? '' )
		&& 'resolved' === (string) ( $inbox->get( $created['new'] )['status'] ?? '' ),
		'An unknown status is refused and does not reset the thread to new'
	);
	$check( 400 === $controller->status( $status_request( 999999999, 'resolved' ) )->get_status(), 'A status change for a missing thread fails instead of reporting success' );

	// Archiving: the thread keeps its status, leaves the inbox counts and can be moved back.
	// A row that is still in the inbox has a NULL archived_at, which ?? would hide.
	$is_active = static function ( int $id ) use ( $inbox ): bool {
		$row = $inbox->get( $id );
		return is_array( $row ) && array_key_exists( 'archived_at', $row ) && null === $row['archived_at'];
	};
	$archive_request = static function ( int $id, bool $archived ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/handoffs/' . $id . '/archive' );
		$request->set_param( 'id', $id );
		$request->set_param( 'archived', $archived );
		return $request;
	};
	$failed_before = $inbox->count_failed();
	$archived      = $controller->archive( $archive_request( $created['long'], true ) );
	$long_row      = $inbox->get( $created['long'] );
	$check(
		200 === $archived->get_status() && true === ( $archived->get_data()['archived'] ?? null ) && '' !== ( $archived->get_data()['archived_on'] ?? '' )
		&& null !== ( $long_row['archived_at'] ?? null ) && 'failed' === ( $long_row['status'] ?? '' )
		&& $failed_before - 1 === $inbox->count_failed(),
		'Archiving keeps the status and removes the thread from the waiting counts'
	);
	$active_ids   = array_map( 'intval', array_column( $inbox->list( 100 ), 'id' ) );
	$archived_ids = array_map( 'intval', array_column( $inbox->list( 100, 0, true ), 'id' ) );
	ob_start();
	( new HandoffInboxPage() )->render_inbox();
	$archived_html = (string) ob_get_clean();
	$check(
		! in_array( $created['long'], $active_ids, true ) && in_array( $created['long'], $archived_ids, true )
		&& 1 === preg_match( '/data-sp-handoff-item="' . $created['long'] . '" data-status="failed" data-archived="1" hidden>/', $archived_html ),
		'An archived thread is listed only under Archived and its row starts hidden'
	);
	$restored = $controller->archive( $archive_request( $created['long'], false ) );
	$check(
		200 === $restored->get_status() && false === ( $restored->get_data()['archived'] ?? null )
		&& $is_active( $created['long'] ) && $failed_before === $inbox->count_failed(),
		'Moving a thread back to the inbox restores it to the waiting counts'
	);
	$check( 400 === $controller->archive( $archive_request( 999999999, true ) )->get_status(), 'Archiving a missing thread fails instead of reporting success' );

	// Archive resolved touches every resolved thread on the site, so resolved threads this check did not create are moved back afterwards.
	$foreign_resolved = array_diff(
		array_map( 'intval', array_column( array_filter( $inbox->list( 100 ), static fn( array $row ): bool => 'resolved' === $row['status'] ), 'id' ) ),
		array_map( 'intval', array_values( $created ) )
	);
	$bulk = $controller->archive_resolved();
	foreach ( $foreign_resolved as $foreign_id ) {
		$inbox->set_archived( $foreign_id, false );
	}
	$check(
		200 === $bulk->get_status() && (int) ( $bulk->get_data()['count'] ?? 0 ) >= 2
		&& null !== ( $inbox->get( $created['new'] )['archived_at'] ?? null ) && null !== ( $inbox->get( $created['script'] )['archived_at'] ?? null )
		&& $is_active( $created['contact'] ),
		'Archive resolved moves only resolved threads to the archive'
	);

	$states = AnswerDeskHandoffPage::channel_states();
	$check(
		array( 'email', 'telegram', 'twilio' ) === array_column( $states, 'id' )
		&& array() === array_diff( array_column( $states, 'state' ), array( 'ready', 'needs-setup', 'paused', 'off' ) ),
		'Channel states cover the three real transports with known state names'
	);

	if ( switch_to_locale( 'fa_IR' ) ) {
		ob_start();
		( new HandoffInboxPage() )->render_inbox();
		$fa = (string) ob_get_clean();
		restore_previous_locale();
		$check( 1 === preg_match( '/<h2 class="sp-hoi__title" id="sp-hoi-title">[^<]*[\x{0600}-\x{06FF}]/u', $fa ), 'fa_IR renders the inbox in Persian' );
	} else {
		$check( false, 'fa_IR is available for the inbox copy check' );
	}
} catch ( Throwable $error ) {
	$check( false, 'Handoff inbox contracts complete without a fatal error: ' . $error->getMessage() );
} finally {
	global $wpdb;
	$table = ( new HandoffInbox() )->table();
	foreach ( $created as $id ) {
		if ( $id > 0 ) {
			$wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );
		}
	}
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
