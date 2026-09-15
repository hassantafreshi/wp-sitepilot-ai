<?php
/**
 * Local WordPress integration check for the EFB Copilot usage-cap route.
 *
 * The EFB conversational route does not require an installed EFB form to
 * reject a capped AI request. This exercises the real REST registration and
 * permission callback, prevents all provider transport with pre_http_request,
 * and restores every changed option in finally.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Api\RestRoutes;
use AISAAutoPilot\Core\Settings;

$missing = new stdClass();
$checks  = array();
$options = array(
	Settings::OPTION,
	Settings::KEY_OPTION,
	'aisaautopilot_monthly_budget',
	'aisaautopilot_usage_totals',
	'aisaautopilot_usage_period',
	'aisaautopilot_usage_log',
	'aisaautopilot_usage_period_contexts',
	'aisaautopilot_usage_daily',
	'aisaautopilot_usage_historical_totals',
	'aisaautopilot_usage_historical_log',
	'aisaautopilot_usage_period_archive',
	'aisaautopilot_logs',
);
$originals = array();
foreach ( $options as $option ) {
	$originals[ $option ] = get_option( $option, $missing );
}

$original_user_id  = get_current_user_id();
$provider_attempts = 0;
$provider_filter   = null;

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

try {
	wp_set_current_user( 1 );
	Settings::update(
		array(
			'provider'            => 'deepseek',
			'base_url'            => 'https://usage-cap-efb-test.invalid/v1',
			'model'               => 'usage-cap-efb-contract-model',
			'key_management'      => 'keyvault',
			'efb_copilot_enabled' => true,
		)
	);
	Settings::set_api_key( 'usage-cap-efb-contract-key' );
	update_option( 'aisaautopilot_monthly_budget', 1, false );
	update_option( 'aisaautopilot_usage_period', wp_date( 'Y-m' ), false );
	update_option(
		'aisaautopilot_usage_totals',
		array(
			'prompt_tokens'     => 1,
			'completion_tokens' => 0,
			'total_tokens'      => 1,
			'request_count'     => 1,
			'last_used'         => gmdate( 'c' ),
		),
		false
	);

	do_action( 'rest_api_init' );
	$routes = rest_get_server()->get_routes();
	$check(
		isset( $routes[ '/' . RestRoutes::NAMESPACE . '/efb/chat' ] ),
		'EFB Copilot chat route is registered for the local administrator'
	);

	$provider_filter = static function ( $preempt, array $args, string $url ) use ( &$provider_attempts ) {
		++$provider_attempts;
		return new WP_Error( 'usage_cap_efb_provider_called', 'Provider transport must not run while the EFB cap is exhausted.' );
	};
	add_filter( 'pre_http_request', $provider_filter, 10, 3 );

	$request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/efb/chat' );
	$request->set_param( 'message', 'Help me build a short contact form.' );
	$request->set_param( 'has_form', false );
	$request->set_param( 'fields', array() );
	$request->set_param( 'steps', array() );
	$request->set_param( 'history', array() );
	$response = rest_do_request( $request );
	$data     = (array) $response->get_data();
	$check(
		429 === $response->get_status()
		&& false === ( $data['ok'] ?? true )
		&& 'usage_cap_reached' === ( $data['code'] ?? '' )
		&& 'message' === ( $data['type'] ?? '' )
		&& '' !== trim( (string) ( $data['reply'] ?? '' ) )
		&& 0 === $provider_attempts,
		'EFB Copilot returns an actionable 429 before provider transport when the shared cap is exhausted'
	);
} catch ( Throwable $error ) {
	$check( false, 'EFB Copilot usage-cap route completes without a fatal error' );
} finally {
	if ( null !== $provider_filter ) {
		remove_filter( 'pre_http_request', $provider_filter, 10 );
	}
	foreach ( $options as $option ) {
		$restore_option( $option, $originals[ $option ] );
	}
	wp_set_current_user( $original_user_id );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
