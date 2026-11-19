<?php
/**
 * Local WordPress checks for keeping form submissions whose email could not be sent.
 *
 * Off (the default) nothing is stored and the visitor is asked to try again, as before.
 * On, the composed email is kept, the visitor sees the normal thank-you, a retry is
 * queued, and the administrator can resend or delete it from Your forms. Kept rows obey
 * the retention period, the privacy exporter and eraser, and the REST routes are for
 * administrators only. wp_mail is short-circuited with pre_wp_mail; no email is sent.
 *
 * A submission ends in exit() after its redirect, so each one runs in a child process
 * of this same file (`--submit <form_id> <fail|ok>`).
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\HomePage;
use AISAAutoPilot\Admin\NativeFormsPage;
use AISAAutoPilot\Connectors\NativeFormConnector;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Forms\FormOutbox;
use AISAAutoPilot\Frontend\FormShortcode;
use AISAAutoPilot\Support\PrivacyTools;
use AISAAutoPilot\Support\Retention;

// Child: submit the form once and report where the visitor was sent.
if ( isset( $argv[1] ) && '--submit' === $argv[1] ) {
	$form_id  = (int) ( $argv[2] ?? 0 );
	$mail_ok  = 'ok' === ( $argv[3] ?? '' );
	$location = '';
	add_filter( 'pre_wp_mail', static fn () => $mail_ok, 10, 2 );
	add_filter(
		'wp_redirect',
		static function ( $to ) use ( &$location ) {
			$location = (string) $to;
			return $to;
		}
	);
	register_shutdown_function(
		static function () use ( &$location ): void {
			echo wp_json_encode( array( 'location' => $location ) ) . PHP_EOL;
		}
	);
	$_SERVER['HTTP_REFERER'] = home_url( '/outbox-test/' );
	$_SERVER['REMOTE_ADDR']  = '198.51.100.' . wp_rand( 1, 250 );
	$_POST                   = array(
		'action'         => 'aisaautopilot_form_submit',
		'form_id'        => (string) $form_id,
		'sp_nonce'       => wp_create_nonce( 'aisaautopilot_form_submit_' . $form_id ),
		'sp_field_name'  => 'Ada Outbox',
		'sp_field_email' => 'ada.outbox@example.test',
	);
	( new FormShortcode() )->handle_submit();
	exit;
}

$checks = array();
$check  = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array( 'ok' => $ok, 'name' => $name );
};
$submit = static function ( int $form_id, string $mail ): string {
	$out  = (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --submit ' . $form_id . ' ' . $mail . ' 2>&1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- test harness.
	$json = json_decode( (string) strrchr( "\n" . trim( $out ), "\n" ), true );
	wp_cache_flush(); // The child wrote options (the retry event) this process has cached.
	return is_array( $json ) ? (string) $json['location'] : 'ERROR ' . $out;
};

$original_settings = get_option( Settings::OPTION );
$original_logs     = get_option( 'aisaautopilot_logs', null );
wp_set_current_user( 1 );
FormOutbox::ensure_schema();
$outbox = new FormOutbox();
foreach ( $outbox->list( 200 ) as $row ) {
	$outbox->delete( $row['id'] );
}
FormOutbox::unschedule();

$plan    = array(
	'title'                => 'Outbox QA form',
	'notification_email'   => 'owner@example.test',
	'submit_button'        => 'Send',
	'confirmation_message' => 'Thanks',
	'fields'               => array(
		array( 'key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true, 'placeholder' => '', 'options' => array() ),
		array( 'key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true, 'placeholder' => '', 'options' => array() ),
	),
);
$created = ( new NativeFormConnector() )->execute_action( $plan, array( 'status' => 'publish' ) );
$form_id = (int) ( $created['form_id'] ?? 0 );

try {
	// 1. Off by default: the visitor is asked to try again and nothing is kept.
	Settings::update( array( 'forms_keep_undelivered' => false ) );
	$location = $submit( $form_id, 'fail' );
	$check(
		false === (bool) ( Settings::defaults()['forms_keep_undelivered'] ?? true )
		&& false !== strpos( $location, 'sp_form_error=delivery' ) && 0 === $outbox->count(),
		'Off by default: a failed email asks the visitor to try again and stores nothing'
	);

	// 2. On: the answers are kept, the visitor sees the thank-you, a retry is queued.
	Settings::update( array( 'forms_keep_undelivered' => true ) );
	$location = $submit( $form_id, 'fail' );
	$rows     = $outbox->list();
	$check(
		false !== strpos( $location, 'sp_form_sent=' . $form_id ) && 1 === count( $rows )
		&& false !== strpos( $rows[0]['body'], 'Name: Ada Outbox' ) && 'Outbox QA form' === $rows[0]['form_title']
		&& false !== wp_next_scheduled( FormOutbox::RETRY_HOOK ),
		'On: a failed email keeps the answers, shows the visitor the thank-you and queues a retry'
	);
	$location = $submit( $form_id, 'ok' );
	$check( false !== strpos( $location, 'sp_form_sent=' . $form_id ) && 1 === $outbox->count(), 'A submission whose email works is sent and not kept' );

	// 3. The administrator sees it on Your forms and on Home.
	ob_start();
	( new NativeFormsPage() )->render();
	$forms_html = (string) ob_get_clean();
	ob_start();
	( new HomePage() )->render();
	$home_html = (string) ob_get_clean();
	$check(
		false !== strpos( $forms_html, 'data-sp-form-outbox' ) && false !== strpos( $forms_html, '1 submission could not be emailed' )
		&& false !== strpos( $forms_html, 'data-sp-outbox-resend="' . $rows[0]['id'] . '"' ) && false !== strpos( $forms_html, 'Name: Ada Outbox' ),
		'Your forms lists the kept submission with its answers, Resend and Delete'
	);
	$check( false !== strpos( $home_html, '1 form submission could not be emailed' ), 'Home names the undelivered submission under Needs you' );

	// 4. Retries: a failing resend counts the attempt; automatic tries stop at the limit.
	add_filter( 'pre_wp_mail', '__return_false', 99 );
	$failed = $outbox->resend( $rows[0]['id'] );
	$after  = $outbox->list()[0]['attempts'] ?? 0;
	for ( $i = 0; $i < FormOutbox::MAX_AUTO_ATTEMPTS + 2; $i++ ) {
		$outbox->retry_due();
	}
	$check(
		! $failed['ok'] && 'send_failed' === $failed['code'] && 2 === $after
		&& FormOutbox::MAX_AUTO_ATTEMPTS === ( $outbox->list()[0]['attempts'] ?? 0 ),
		'A failed resend counts the attempt, and automatic retries stop after ' . FormOutbox::MAX_AUTO_ATTEMPTS
	);
	remove_filter( 'pre_wp_mail', '__return_false', 99 );

	// 5. REST: administrators only; a working resend removes the row.
	wp_set_current_user( 0 );
	$denied = rest_do_request( new WP_REST_Request( 'POST', '/aisaautopilot/v1/forms/outbox/' . $rows[0]['id'] . '/resend' ) );
	wp_set_current_user( 1 );
	$sent_to = '';
	add_filter(
		'pre_wp_mail',
		static function ( $pre, $args ) use ( &$sent_to ) {
			$sent_to = (string) $args['to'];
			return true;
		},
		99,
		2
	);
	$resent = rest_do_request( new WP_REST_Request( 'POST', '/aisaautopilot/v1/forms/outbox/' . $rows[0]['id'] . '/resend' ) );
	$check(
		in_array( $denied->get_status(), array( 401, 403 ), true ) && 200 === $resent->get_status()
		&& 'owner@example.test' === $sent_to && 0 === $outbox->count() && 0 === (int) $resent->get_data()['left'],
		'Resend is for administrators only; it sends to the form\'s address and removes the row'
	);

	// 6. Delete route.
	$id      = $outbox->keep( $form_id, 'Subject', "Name: Delete Me\nEmail: del@example.test", array() );
	$deleted = rest_do_request( new WP_REST_Request( 'POST', '/aisaautopilot/v1/forms/outbox/' . $id . '/delete' ) );
	$again   = rest_do_request( new WP_REST_Request( 'POST', '/aisaautopilot/v1/forms/outbox/' . $id . '/delete' ) );
	$check( 200 === $deleted->get_status() && 404 === $again->get_status() && 0 === $outbox->count(), 'Delete removes the kept submission; a second delete finds nothing' );

	// 7. Retention: kept submissions older than their period go with the daily cleanup.
	global $wpdb;
	$old = $outbox->keep( $form_id, 'Old', "Name: Old\nEmail: old@example.test", array() );
	$new = $outbox->keep( $form_id, 'New', "Name: New\nEmail: new@example.test", array() );
	$wpdb->update( $outbox->table(), array( 'created_at' => gmdate( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS ) ), array( 'id' => $old ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$run = ( new Retention() )->run();
	$ids = array_column( $outbox->list(), 'id' );
	$check(
		30 === Retention::DEFAULTS['undelivered_days'] && 1 === $run['undelivered'] && array( $new ) === $ids
		&& false !== strpos( Retention::summary( Retention::last_run() ), 'It also deleted 1 undelivered form submission.' ),
		'The daily cleanup deletes kept submissions older than 30 days and says so'
	);

	// 8. Privacy: export finds the visitor's answers by email address, erase deletes them; the policy text says so.
	$privacy  = new PrivacyTools();
	$exported = $privacy->export( 'new@example.test' );
	$groups   = array_column( $exported['data'], 'group_id' );
	$erased   = $privacy->erase( 'new@example.test' );
	$check(
		in_array( 'aisaautopilot-form-outbox', $groups, true ) && $erased['items_removed'] && 0 === $outbox->count()
		&& false !== strpos( PrivacyTools::policy_text(), 'Undelivered form submissions: 30 days.' ),
		'Privacy tools export and erase kept submissions by email address, and the policy text names the period'
	);
	Settings::update( array( 'forms_keep_undelivered' => false ) );
	$check( false === strpos( PrivacyTools::policy_text(), 'Undelivered form submissions' ), 'With the option off the policy text does not mention kept submissions' );
} finally {
	foreach ( $outbox->list( 200 ) as $row ) {
		$outbox->delete( $row['id'] );
	}
	FormOutbox::unschedule();
	if ( $form_id > 0 ) {
		wp_delete_post( $form_id, true );
	}
	update_option( Settings::OPTION, $original_settings );
	if ( null === $original_logs ) {
		delete_option( 'aisaautopilot_logs' );
	} else {
		update_option( 'aisaautopilot_logs', $original_logs, false );
	}
}

$failed = 0;
foreach ( $checks as $c ) {
	echo ( $c['ok'] ? 'PASS ' : 'FAIL ' ) . $c['name'] . "\n";
	$failed += $c['ok'] ? 0 : 1;
}
echo count( $checks ) . " checks, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
