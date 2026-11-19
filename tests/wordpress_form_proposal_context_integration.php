<?php
/** Local WordPress checks for immutable Form Studio approval context. */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Connectors\ConnectorRegistry;
use AISAAutoPilot\Connectors\NativeFormConnector;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Execution\ActionExecutor;
use AISAAutoPilot\Execution\ActionStore;

$missing           = new stdClass();
$original_settings = get_option( Settings::OPTION, $missing );
$original_logs     = get_option( 'aisaautopilot_logs', $missing );
$original_user_id  = get_current_user_id();
$checks            = array();
$post_ids          = array();
$action_ids        = array();

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array( 'ok' => $ok, 'name' => $name );
};
$restore_option = static function ( string $name, $value ) use ( $missing ): void {
	if ( $missing === $value ) {
		delete_option( $name );
		return;
	}
	update_option( $name, $value, false );
};
$plan = array(
	'title'                => 'AISAAutoPilot immutable proposal fixture',
	'description'          => 'A bounded local fixture.',
	'fields'               => array(
		array( 'key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true, 'placeholder' => '', 'options' => array() ),
	),
	'submit_button'        => 'Send',
	'confirmation_message' => 'Thanks.',
	'risk_level'           => 'low',
	'notes'                => '',
	'notification_email'   => (string) get_option( 'admin_email' ),
);

try {
	wp_set_current_user( 1 );
	$registry = new ConnectorRegistry();
	$registry->register( new NativeFormConnector() );
	$store    = new ActionStore();
	$executor = new ActionExecutor( $registry, $store );

	foreach ( array( 'publish' => 'draft', 'draft' => 'publish' ) as $reviewed => $later_setting ) {
		Settings::update( array( 'default_form_status' => $reviewed ) );
		$action_id    = $store->create( $plan, 'native_form', array( 'status' => $reviewed ) );
		$action_ids[] = $action_id;
		Settings::update( array( 'default_form_status' => $later_setting ) );
		$result  = $executor->approve_and_execute( $action_id, 'native_form' );
		$post_id = absint( $result['form_id'] ?? 0 );
		if ( $post_id > 0 ) {
			$post_ids[] = $post_id;
		}
		$check(
			'success' === ( $result['status'] ?? '' ) && $post_id > 0 && $reviewed === get_post_status( $post_id ),
			'Execution keeps the reviewed ' . $reviewed . ' status after Settings changes to ' . $later_setting
		);
	}

	$action_id    = $store->create( $plan, 'native_form', array( 'status' => 'administrator' ) );
	$action_ids[] = $action_id;
	$result       = $executor->approve_and_execute( $action_id, 'native_form' );
	$post_id      = absint( $result['form_id'] ?? 0 );
	if ( $post_id > 0 ) {
		$post_ids[] = $post_id;
	}
	$check( 'success' === ( $result['status'] ?? '' ) && 'draft' === get_post_status( $post_id ), 'Malformed proposal context fails closed to draft' );
} finally {
	foreach ( $post_ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( $action_ids as $action_id ) {
		delete_transient( 'aisaautopilot_action_' . $action_id );
		delete_option( 'aisaautopilot_action_claim_' . str_replace( '-', '', $action_id ) );
	}
	$restore_option( Settings::OPTION, $original_settings );
	$restore_option( 'aisaautopilot_logs', $original_logs );
	wp_set_current_user( $original_user_id );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
