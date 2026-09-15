<?php
/**
 * Local WordPress contract checks for Content Studio's execution boundaries.
 *
 * No real provider request is permitted: the HTTP pre-flight filter turns one
 * into an observable failure, while the exhausted-cap assertion requires zero
 * attempted requests.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Api\ContentController;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Execution\ActionStore;
use AISAAutoPilot\Execution\ContentDraftExecutor;
use AISAAutoPilot\Usage\UsageTracker;

$missing            = new stdClass();
$original_settings  = get_option( Settings::OPTION, $missing );
$original_key       = get_option( Settings::KEY_OPTION, $missing );
$original_budget    = get_option( 'aisaautopilot_monthly_budget', $missing );
$original_totals    = get_option( 'aisaautopilot_usage_totals', $missing );
$original_period    = get_option( 'aisaautopilot_usage_period', $missing );
$original_logs      = get_option( 'aisaautopilot_logs', $missing );
$original_user_id   = get_current_user_id();
$checks             = array();
$action_id          = '';
$provider_attempts  = 0;
$provider_filter    = null;

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
			'content_studio_enabled' => true,
			'provider'               => 'deepseek',
			'base_url'               => 'https://content-cap-test.invalid/v1',
			'model'                  => 'content-cap-contract-test',
			'key_management'         => 'keyvault',
		)
	);
	// This inert key is intentionally never printed. It makes the provider
	// appear configured, so a missing cap gate would reach pre_http_request.
	Settings::set_api_key( 'content-cap-contract-test-key' );

	$store     = new ActionStore();
	$action_id = $store->create( array( 'title' => 'Must remain reviewable' ), 'native_form' );
	$result    = ( new ContentDraftExecutor( $store ) )->approve_and_execute( $action_id );
	$record    = $store->get( $action_id );
	$claim_key = 'aisaautopilot_action_claim_' . str_replace( '-', '', $action_id );
	$check(
		'failed' === ( $result['status'] ?? '' )
		&& 'invalid_action' === ( $result['code'] ?? '' )
		&& is_array( $record )
		&& 'native_form' === ( $record['connector_id'] ?? '' )
		&& false === get_option( $claim_key, false ),
		'Content Studio rejects another product\'s proposal without consuming or locking it'
	);

	$tracker = new UsageTracker();
	$tracker->set_budget( 1 );
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
	$provider_filter = static function ( $preempt, array $args, string $url ) use ( &$provider_attempts ) {
		++$provider_attempts;
		return new WP_Error( 'content_cap_provider_called', 'The provider must not be contacted when the cap is exhausted.' );
	};
	add_filter( 'pre_http_request', $provider_filter, 10, 3 );

	$request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/content/plan' );
	$request->set_param( 'prompt', 'Draft a short, safe test post.' );
	$response = ( new ContentController() )->plan( $request );
	$data     = (array) $response->get_data();
	$check(
		$tracker->is_exhausted()
		&& 429 === $response->get_status()
		&& false === ( $data['ok'] ?? true )
		&& 'usage_cap_reached' === ( $data['code'] ?? '' )
		&& 0 === $provider_attempts,
		'Content Studio rejects an exhausted cap before any provider request'
	);

	$_GET = array( 'mode' => 'content' ); // phpcs:ignore WordPress.Variables.GlobalVariables.OverrideProhibited -- isolated local renderer assertion.
	ob_start();
	( new \AISAAutoPilot\Admin\CopilotPage() )->render();
	$html = (string) ob_get_clean();
	$check(
		false !== strpos( $html, 'AI usage cap reached' )
		&& false !== strpos( $html, 'Open Usage' )
		&& 1 === preg_match( '/id="aisaautopilot-content-plan"[^>]*disabled/', $html ),
		'Content Studio UI disables drafting and links to Usage when the cap is exhausted'
	);
} catch ( Throwable $error ) {
	$check( false, 'Content Studio safety contract completes without a fatal error' );
} finally {
	if ( null !== $provider_filter ) {
		remove_filter( 'pre_http_request', $provider_filter, 10 );
	}
	if ( '' !== $action_id ) {
		delete_transient( 'aisaautopilot_action_' . $action_id );
		delete_option( 'aisaautopilot_action_claim_' . str_replace( '-', '', $action_id ) );
	}
	$restore_option( Settings::OPTION, $original_settings );
	$restore_option( Settings::KEY_OPTION, $original_key );
	$restore_option( 'aisaautopilot_monthly_budget', $original_budget );
	$restore_option( 'aisaautopilot_usage_totals', $original_totals );
	$restore_option( 'aisaautopilot_usage_period', $original_period );
	$restore_option( 'aisaautopilot_logs', $original_logs );
	wp_set_current_user( $original_user_id );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
