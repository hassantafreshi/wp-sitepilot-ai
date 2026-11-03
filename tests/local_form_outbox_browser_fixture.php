<?php
/**
 * Fixture for tests/form_outbox_browser_matrix.cjs: turns on "Keep a submission when its
 * email cannot be sent", keeps two submissions of a temporary form, and restores all of it.
 * Usage: php local_form_outbox_browser_fixture.php prepare|restore
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Connectors\NativeFormConnector;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Forms\FormOutbox;

$state_option = 'aisaautopilot_local_form_outbox_fixture';
$mode         = (string) ( $argv[1] ?? '' );
$outbox       = new FormOutbox();

if ( 'prepare' === $mode ) {
	$state = get_option( $state_option );
	if ( ! is_array( $state ) ) {
		$state = array( 'keep' => (bool) Settings::get( 'forms_keep_undelivered', false ) );
	}
	$created          = ( new NativeFormConnector() )->execute_action(
		array(
			'title'              => 'Outbox browser QA form',
			'notification_email' => 'owner@example.test',
			'fields'             => array(
				array( 'key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true, 'placeholder' => '', 'options' => array() ),
				array( 'key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true, 'placeholder' => '', 'options' => array() ),
			),
		),
		array( 'status' => 'publish' )
	);
	$state['form_id'] = (int) ( $created['form_id'] ?? 0 );
	Settings::update( array( 'forms_keep_undelivered' => true ) );
	$state['rows'] = array(
		$outbox->keep( $state['form_id'], 'Outbox QA one', "Name: Ada Browser\nEmail: ada.browser@example.test", array() ),
		$outbox->keep( $state['form_id'], 'Outbox QA two', "Name: Bob Browser\nEmail: bob.browser@example.test", array() ),
	);
	update_option( $state_option, $state, false );
	echo wp_json_encode( $state ) . PHP_EOL;
	exit( 0 );
}

if ( 'restore' === $mode ) {
	$state = get_option( $state_option );
	if ( is_array( $state ) ) {
		foreach ( (array) ( $state['rows'] ?? array() ) as $id ) {
			$outbox->delete( (int) $id );
		}
		if ( ! empty( $state['form_id'] ) ) {
			wp_delete_post( (int) $state['form_id'], true );
		}
		Settings::update( array( 'forms_keep_undelivered' => ! empty( $state['keep'] ) ) );
		delete_option( $state_option );
	}
	echo 'Form outbox browser fixture removed.' . PHP_EOL;
	exit( 0 );
}

fwrite( STDERR, "Usage: prepare|restore\n" );
exit( 2 );
