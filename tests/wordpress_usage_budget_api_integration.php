<?php
/**
 * Local WordPress integration checks for the Usage budget endpoint contract.
 *
 * The script changes only the budget option and restores it in finally. It
 * dispatches through the real REST route as an administrator so malformed
 * client values cannot be hidden by a direct-controller-only test.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Api\RestRoutes;
use AISAAutoPilot\Usage\UsageTracker;

$missing          = new stdClass();
$original         = get_option( 'aisaautopilot_monthly_budget', $missing );
$original_user_id = get_current_user_id();
$checks           = array();

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

try {
	wp_set_current_user( 1 );
	do_action( 'rest_api_init' );
	$routes = rest_get_server()->get_routes();
	$check(
		isset( $routes[ '/' . RestRoutes::NAMESPACE . '/usage/budget' ] ),
		'Usage budget REST route is registered for the local administrator'
	);

	$dispatch = static function ( $budget, bool $provided = true ): \WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/usage/budget' );
		if ( $provided ) {
			$request->set_param( 'budget', $budget );
		}

		return rest_do_request( $request );
	};

	$positive      = $dispatch( '7500' );
	$positive_data = (array) $positive->get_data();
	$check(
		200 === $positive->get_status()
		&& true === ( $positive_data['ok'] ?? false )
		&& 7500 === (int) ( $positive_data['budget'] ?? 0 )
		&& 7500 === (int) get_option( 'aisaautopilot_monthly_budget', 0 ),
		'Positive REST budget retains its requested token cap'
	);

	update_option( 'aisaautopilot_monthly_budget', 4321, false );
	$invalid_cases = array(
		'missing value'       => array( null, false ),
		'empty string'        => array( '', true ),
		'alphabetic string'   => array( 'abc', true ),
		'fractional string'   => array( '3.5', true ),
		'exponent string'     => array( '1e3', true ),
		'plus-prefixed string' => array( '+7', true ),
		'whitespace string'   => array( ' 7', true ),
		'compound array'      => array( array( 1 ), true ),
		'boolean value'       => array( true, true ),
		'floating value'      => array( 1.0, true ),
		'over maximum'        => array( (string) ( UsageTracker::MAX_BUDGET + 1 ), true ),
	);
	foreach ( $invalid_cases as $label => $case ) {
		$response = $dispatch( $case[0], $case[1] );
		$data     = (array) $response->get_data();
		$check(
			400 === $response->get_status()
			&& false === ( $data['ok'] ?? true )
			&& 'invalid_budget' === ( $data['code'] ?? '' )
			&& 4321 === (int) get_option( 'aisaautopilot_monthly_budget', 0 ),
			'Invalid budget (' . $label . ') returns 400 without changing the stored cap'
		);
	}

	$maximum      = $dispatch( (string) UsageTracker::MAX_BUDGET );
	$maximum_data = (array) $maximum->get_data();
	$check(
		200 === $maximum->get_status()
		&& UsageTracker::MAX_BUDGET === (int) ( $maximum_data['budget'] ?? 0 )
		&& UsageTracker::MAX_BUDGET === (int) get_option( 'aisaautopilot_monthly_budget', 0 ),
		'The exact documented maximum token cap is accepted'
	);

	$negative      = $dispatch( -5000 );
	$negative_data = (array) $negative->get_data();
	$check(
		200 === $negative->get_status()
		&& 0 === (int) ( $negative_data['budget'] ?? -1 )
		&& 0 === (int) get_option( 'aisaautopilot_monthly_budget', -1 ),
		'Negative REST budget remains the documented unlimited compatibility value'
	);
} catch ( Throwable $error ) {
	$check( false, 'Usage budget endpoint completes without a fatal error' );
} finally {
	if ( $original === $missing ) {
		delete_option( 'aisaautopilot_monthly_budget' );
	} else {
		update_option( 'aisaautopilot_monthly_budget', $original, false );
	}
	wp_set_current_user( $original_user_id );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
