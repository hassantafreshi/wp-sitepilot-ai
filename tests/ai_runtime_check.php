<?php
/**
 * Safe local AI behaviour check for the candidate plugin.
 *
 * It generates a proposal only: no form is approved or created. Any temporary
 * action and test log entry are removed before the script exits.
 */
declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Api\CopilotController;
use AISAAutoPilot\Providers\ProviderManager;

wp_set_current_user( 1 );

$original_logs = get_option( 'aisaautopilot_logs', null );
$action_id     = '';

try {
	if ( ! ( new ProviderManager() )->is_configured() ) {
		echo "SKIP AI provider is not configured in this local WordPress environment.\n";
		exit( 0 );
	}

	$request = new \WP_REST_Request( 'POST' );
	$request->set_param( 'prompt', 'Create a simple contact form with name, email, a topic select, and a message field.' );
	$response = ( new CopilotController() )->plan( $request );
	$data     = $response->get_data();
	$action_id = sanitize_text_field( (string) ( $data['action_id'] ?? '' ) );

	$ok = 200 === $response->get_status()
		&& ! empty( $data['ok'] )
		&& '' !== $action_id
		&& ! empty( $data['preview']['fields'] )
		&& ! empty( $data['expires_at'] );

	echo ( $ok ? 'PASS' : 'FAIL' ) . " AI proposal generated without executing a form action.\n";
	if ( ! $ok ) {
		echo 'Response code: ' . (string) ( $data['code'] ?? 'unknown' ) . PHP_EOL;
		exit( 1 );
	}
} finally {
	if ( '' !== $action_id ) {
		delete_transient( 'aisaautopilot_action_' . $action_id );
	}
	if ( null === $original_logs ) {
		delete_option( 'aisaautopilot_logs' );
	} else {
		update_option( 'aisaautopilot_logs', $original_logs, false );
	}
}
