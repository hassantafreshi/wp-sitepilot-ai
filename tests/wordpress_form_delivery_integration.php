<?php
/**
 * WordPress integration test for the public email-only submission path.
 *
 * wp_mail is short-circuited with pre_wp_mail, so no real email is sent.
 * The handler exits after its redirect; the shutdown callback reports results
 * and removes only the draft/published post created by this script.
 */
declare(strict_types=1);

require 'C:\\xampp\\htdocs\\wp\\wp-load.php';

use SitePilot\Connectors\NativeFormConnector;
use SitePilot\Frontend\FormShortcode;

wp_set_current_user( 1 );

$recipient     = (string) get_option( 'admin_email' );
$form_id       = 0;
$mail          = array();
$baseline_count = (int) ( wp_count_posts( NativeFormConnector::SUBMISSION_TYPE )->private ?? 0 );
$original_logs = get_option( 'sitepilot_ai_logs', null );

register_shutdown_function(
	static function () use ( &$mail, &$form_id, $recipient, $baseline_count, $original_logs ): void {
		$after_count = (int) ( wp_count_posts( NativeFormConnector::SUBMISSION_TYPE )->private ?? 0 );
		$headers = (array) ( $mail['headers'] ?? array() );
		$passed = ! empty( $mail )
			&& $recipient === (string) ( $mail['to'] ?? '' )
			&& false !== strpos( (string) ( $mail['message'] ?? '' ), 'Name: Ada' )
			&& in_array( 'Reply-To: ada@example.test', $headers, true )
			&& $baseline_count === $after_count;

		if ( $form_id > 0 ) {
			wp_delete_post( $form_id, true );
		}
		if ( null === $original_logs ) {
			delete_option( 'sitepilot_ai_logs' );
		} else {
			update_option( 'sitepilot_ai_logs', $original_logs, false );
		}

		echo ( $passed ? 'PASS' : 'FAIL' ) . ' email-only public submission with no stored visitor values' . PHP_EOL;
		exit( $passed ? 0 : 1 );
	}
);

add_filter(
	'pre_wp_mail',
	static function ( $pre, array $args ) use ( &$mail ) {
		$mail = $args;
		return true;
	},
	10,
	2
);

$plan = array(
	'title'                => 'P1 mail delivery test',
	'notification_email'   => $recipient,
	'submit_button'        => 'Send',
	'confirmation_message' => 'Thanks',
	'fields'               => array(
		array( 'key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true, 'placeholder' => '', 'options' => array() ),
		array( 'key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true, 'placeholder' => '', 'options' => array() ),
		array( 'key' => 'topic', 'type' => 'select', 'label' => 'Topic', 'required' => true, 'placeholder' => '', 'options' => array( 'Sales', 'Support' ) ),
	),
);
$result = ( new NativeFormConnector() )->execute_action( $plan, array( 'status' => 'publish' ) );
$form_id = (int) ( $result['form_id'] ?? 0 );
if ( $form_id <= 0 ) {
	echo 'FAIL could not create temporary form' . PHP_EOL;
	exit( 1 );
}

$_SERVER['HTTP_REFERER'] = home_url( '/phase1-mail-test/' );
$_POST = array(
	'action'          => 'sitepilot_form_submit',
	'form_id'         => (string) $form_id,
	'sp_nonce'        => wp_create_nonce( 'sitepilot_form_submit_' . $form_id ),
	'sp_field_name'   => 'Ada',
	'sp_field_email'  => 'ada@example.test',
	'sp_field_topic'  => 'Support',
);

( new FormShortcode() )->handle_submit();
