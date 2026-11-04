<?php
/**
 * Local WordPress integration checks for data retention.
 *
 * Proves the approved defaults (30, 180 and 90 days) and that an administrator can change
 * them in Advanced settings; that the daily job deletes only what is older than its period
 * (visitor conversations by last message, requests to talk to a person by last change,
 * unanswered questions by last asked, cached answers by age), in batches, keeps everything
 * when a period is 0, and records what it did; that it is queued on WP-Cron and removed on
 * deactivation; that "Delete expired data now" is for administrators only; and that the
 * settings card explains how to add a server cron job by hand.
 *
 * Fixtures are dated about ten years back, past the 3,650-day maximum, so no row of the site
 * itself is ever old enough to be touched. Every fixture and option is restored in finally.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\AdminMenu;
use AISAAutoPilot\Admin\SettingsPage;
use AISAAutoPilot\Chat\VisitorConversationStore;
use AISAAutoPilot\Core\Deactivator;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Support\HandoffInbox;
use AISAAutoPilot\Support\PrivacyTools;
use AISAAutoPilot\Support\Retention;

global $wpdb;

$missing   = new stdClass();
$checks    = array();
$options   = array( Settings::OPTION, Retention::LAST_RUN_OPTION, 'aisaautopilot_kb_gaps', 'aisaautopilot_kb_answer_cache', 'aisaautopilot_logs' );
$originals = array();
foreach ( $options as $option ) {
	$originals[ $option ] = get_option( $option, $missing );
}
$original_user = get_current_user_id();
$original_get  = $_GET;
$original_post = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- snapshot for restore.
$chats         = $wpdb->prefix . VisitorConversationStore::TABLE;
$handoffs      = $wpdb->prefix . HandoffInbox::TABLE;
$tag           = 'retention-fixture-' . wp_generate_password( 8, false );
$subscriber    = 0;

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

$restore_option = static function ( string $option, $value ) use ( $missing ): void {
	if ( $value === $missing ) {
		delete_option( $option );
		return;
	}
	update_option( $option, $value, false );
};

/** A MySQL UTC time $days ago. */
$ago = static function ( int $days ): string {
	return gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
};

$count = static function ( string $table, string $like ) use ( $wpdb ): int {
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE messages LIKE %s', $table, '%' . $wpdb->esc_like( $like ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
};

$insert_chats = static function ( int $rows, int $days, string $label ) use ( $wpdb, $chats, $tag, $ago ): void {
	for ( $start = 0; $start < $rows; $start += 300 ) {
		$values = array();
		for ( $i = $start; $i < min( $rows, $start + 300 ); $i++ ) {
			$values[] = $wpdb->prepare( '(%s, %s, 2, %s, %s)', wp_generate_uuid4(), wp_json_encode( array( array( 'role' => 'user', 'content' => $tag . ' ' . $label ) ) ), $ago( $days ), $ago( $days ) );
		}
		$wpdb->query( "INSERT INTO {$chats} (session_token, messages, message_count, created_at, updated_at) VALUES " . implode( ',', $values ) ); // phpcs:ignore WordPress.DB
	}
};

$insert_handoff = static function ( int $days, string $label ) use ( $wpdb, $handoffs, $tag, $ago ): void {
	$wpdb->insert(
		$handoffs,
		array(
			'status'        => 'new',
			'visitor_email' => 'retention@example.test',
			'messages'      => wp_json_encode( array( array( 'role' => 'user', 'content' => $tag . ' ' . $label ) ) ),
			'created_at'    => $ago( $days ),
			'updated_at'    => $ago( $days ),
		)
	);
};

$render = static function ( array $post = array() ): string {
	$_GET  = array( 'page' => AdminMenu::SLUG_SETTINGS );
	$_POST = $post;
	ob_start();
	( new SettingsPage() )->render();
	$html  = (string) ob_get_clean();
	$_POST = array();
	return $html;
};

$cleanup = static function () use ( $wpdb, $chats, $handoffs, $tag ): void {
	foreach ( array( $chats, $handoffs ) as $table ) {
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE messages LIKE %s', $table, '%' . $wpdb->esc_like( $tag ) . '%' ) ); // phpcs:ignore WordPress.DB
	}
};

try {
	wp_set_current_user( 1 );
	switch_to_locale( 'en_US' );
	HandoffInbox::ensure_schema();
	VisitorConversationStore::ensure_schema();

	// Defaults, and what an administrator can type.
	$stored = (array) get_option( Settings::OPTION, array() );
	unset( $stored['retention'] );
	update_option( Settings::OPTION, $stored, false );
	$check(
		array(
			'conversations_days' => 30,
			'handoffs_days'      => 180,
			'questions_days'     => 90,
			'undelivered_days'   => 30,
			'carts_days'         => 30,
		) === Retention::periods(),
		'With nothing saved, conversations are kept 30 days, requests to talk to a person 180, unanswered questions 90, undelivered form submissions 30 and abandoned carts 30'
	);
	$check( 0 === Retention::days( -5 ) && 3650 === Retention::days( 99999 ) && 0 === Retention::days( 'abc' ) && 45 === Retention::days( '45' ), 'A period is a whole number of days from 0 to 3,650' );

	$html = $render();
	$check(
		false !== strpos( $html, 'id="sp-adv-retention"' )
		&& 1 === preg_match( '/name="retention_conversations_days"[^>]*value="30"/', $html )
		&& 1 === preg_match( '/name="retention_handoffs_days"[^>]*value="180"/', $html )
		&& 1 === preg_match( '/name="retention_questions_days"[^>]*value="90"/', $html )
		&& 1 === preg_match( '/name="retention_undelivered_days"[^>]*value="30"/', $html )
		&& 1 === preg_match( '/name="retention_carts_days"[^>]*value="30"/', $html )
		&& false !== strpos( $html, 'Delete expired data now' ),
		'Advanced settings show a Data retention card with the five periods and a button to delete now'
	);
	$check(
		false !== strpos( $html, esc_html( site_url( 'wp-cron.php?doing_wp_cron' ) ) )
		&& false !== strpos( $html, 'wp cron event run --due-now' )
		&& false !== strpos( $html, 'Cron Jobs' )
		&& false !== strpos( $html, 'DISABLE_WP_CRON' ),
		'The card shows how to add a server cron job by hand, with this site\'s own wp-cron.php address and a WP-CLI alternative'
	);

	$render(
		array(
			'aisaautopilot_settings_nonce'  => wp_create_nonce( 'aisaautopilot_settings_save' ),
			'advanced_settings'             => '1',
			'retention_conversations_days'  => '3650',
			'retention_handoffs_days'       => '3650',
			'retention_questions_days'      => '99999',
			'retention_undelivered_days'    => '14',
			'retention_carts_days'          => '7',
		)
	);
	$check(
		array(
			'conversations_days' => 3650,
			'handoffs_days'      => 3650,
			'questions_days'     => 3650,
			'undelivered_days'   => 14,
			'carts_days'         => 7,
		) === Retention::periods(),
		'Saving Advanced settings stores the periods, capped at 3,650 days'
	);

	// Fixtures: just past and just inside ten years.
	$insert_chats( 1100, 3700, 'old-chat' );
	$insert_chats( 1, 3600, 'new-chat' );
	$insert_handoff( 3700, 'old-handoff' );
	$insert_handoff( 3600, 'new-handoff' );
	update_option(
		'aisaautopilot_kb_gaps',
		array(
			md5( 'old' )    => array(
				'question'  => 'Old retention question',
				'last_seen' => gmdate( 'c', time() - 3700 * DAY_IN_SECONDS ),
				'status'    => 'open',
			),
			md5( 'nodate' ) => array(
				'question' => 'Undated retention question',
				'status'   => 'open',
			),
			md5( 'new' )    => array(
				'question'  => 'Recent retention question',
				'last_seen' => gmdate( 'c', time() - 10 * DAY_IN_SECONDS ),
				'status'    => 'open',
			),
		),
		false
	);
	update_option(
		'aisaautopilot_kb_answer_cache',
		array(
			md5( 'old' ) => array(
				'question' => 'Old cached question',
				'answer'   => 'x',
				'ts'       => time() - 3700 * DAY_IN_SECONDS,
			),
			md5( 'new' ) => array(
				'question' => 'New cached question',
				'answer'   => 'y',
				'ts'       => time(),
			),
		),
		false
	);

	$last  = ( new Retention() )->run();
	$gaps  = (array) get_option( 'aisaautopilot_kb_gaps', array() );
	$cache = (array) get_option( 'aisaautopilot_kb_answer_cache', array() );
	$check(
		0 === $count( $chats, $tag . ' old-chat' ) && 1 === $count( $chats, $tag . ' new-chat' )
		&& 1100 === $last['conversations'] && true === $last['complete'],
		'Conversations older than the period are deleted, 1,100 of them in batches, and a newer one stays'
	);
	$check(
		0 === $count( $handoffs, $tag . ' old-handoff' ) && 1 === $count( $handoffs, $tag . ' new-handoff' ) && 1 === $last['handoffs'],
		'A request to talk to a person older than its period is deleted, a newer one stays'
	);
	$check(
		array( md5( 'new' ) ) === array_keys( $gaps ) && array( md5( 'new' ) ) === array_keys( $cache ) && 3 === $last['questions'],
		'Unanswered questions not asked within the period, undated ones, and older cached answers are deleted; recent ones stay'
	);
	$recorded = Retention::last_run();
	$check(
		null !== $recorded && $recorded['conversations'] === 1100 && abs( $recorded['time'] - time() ) < 60
		&& false !== strpos( Retention::summary( $recorded ), '1,100 conversations' )
		&& false !== strpos( Retention::summary( $recorded ), '1 request to talk to a person' ),
		'The run is recorded and summed up for the administrator'
	);

	// 0 keeps everything.
	Settings::update(
		array(
			'retention' => array(
				'conversations_days' => 0,
				'handoffs_days'      => 0,
				'questions_days'     => 0,
			),
		)
	);
	$insert_chats( 1, 3700, 'kept-chat' );
	$insert_handoff( 3700, 'kept-handoff' );
	$kept = ( new Retention() )->run();
	$check(
		1 === $count( $chats, $tag . ' kept-chat' ) && 1 === $count( $handoffs, $tag . ' kept-handoff' ) && 0 === $kept['conversations'] + $kept['handoffs'] + $kept['questions'],
		'A period of 0 keeps that data however old it is'
	);

	// Scheduling.
	// Queued, not necessarily in the future: WP-Cron only runs on visits, and an overdue job is
	// still queued (the Advanced card then says the scheduler is not running).
	$check( Retention::next_run() > 0 && 'daily' === wp_get_schedule( Retention::HOOK ), 'The daily cleanup is queued on WP-Cron' );
	Deactivator::deactivate();
	$unscheduled = 0 === Retention::next_run();
	Retention::schedule();
	$check( $unscheduled && Retention::next_run() > 0, 'Deactivating the plugin removes the daily job; the next request queues it again' );

	// A job a day overdue means WP-Cron is not running; the card says so instead of a past "next" date.
	Retention::unschedule();
	wp_schedule_event( time() - DAY_IN_SECONDS, 'daily', Retention::HOOK );
	$overdue_html = $render();
	Retention::unschedule();
	Retention::schedule();
	$check(
		1 === preg_match( '/data-sp-retention-next>[^<]*has not run yet[^<]*server cron job/', $overdue_html ),
		'An overdue cleanup is reported as WordPress\'s scheduler not running, pointing to the server cron job'
	);

	// The button, for administrators only.
	$request  = new WP_REST_Request( 'POST', '/aisaautopilot/v1/privacy/retention/run' );
	$response = rest_do_request( $request );
	$data     = (array) $response->get_data();
	$subscriber = (int) wp_insert_user(
		array(
			'user_login' => 'retention_sub_' . wp_generate_password( 6, false ),
			'user_pass'  => wp_generate_password( 24 ),
			'user_email' => 'retention-sub-' . wp_generate_password( 6, false ) . '@example.test',
			'role'       => 'subscriber',
		)
	);
	wp_set_current_user( $subscriber );
	$denied = rest_do_request( new WP_REST_Request( 'POST', '/aisaautopilot/v1/privacy/retention/run' ) );
	wp_set_current_user( 1 );
	$check(
		200 === $response->get_status() && true === ( $data['ok'] ?? false ) && false !== strpos( (string) ( $data['message'] ?? '' ), 'Last cleanup' )
		&& in_array( $denied->get_status(), array( 401, 403 ), true ),
		'"Delete expired data now" runs the cleanup for an administrator and is refused to anyone else'
	);

	// The privacy policy text names the periods.
	Settings::update( array( 'retention' => Retention::DEFAULTS ) );
	$policy = PrivacyTools::policy_text();
	$check(
		false !== strpos( $policy, 'recorded conversations, 30 days' ) && false !== strpos( $policy, 'requests to talk to a person, 180 days' ) && false !== strpos( $policy, 'unanswered questions, 90 days' ),
		'The suggested privacy-policy text states how long each kind of data is kept'
	);
} catch ( Throwable $error ) {
	$check( false, 'Retention contract completes without a fatal error: ' . $error->getMessage() );
} finally {
	$cleanup();
	foreach ( $options as $option ) {
		$restore_option( $option, $originals[ $option ] );
	}
	if ( $subscriber > 0 ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $subscriber );
	}
	Retention::schedule();
	$_GET  = $original_get;
	$_POST = $original_post;
	wp_set_current_user( $original_user );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
