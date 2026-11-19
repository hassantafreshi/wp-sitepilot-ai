<?php
/**
 * Local WordPress integration checks for the saved-config Health diagnostics.
 *
 * It intercepts the provider request locally, restores every option it owns,
 * and proves that an unsaved Settings test cannot overwrite the Health
 * snapshot for the saved site configuration.
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\HealthDiagnostics;
use AISAAutoPilot\Admin\SettingsPage;
use AISAAutoPilot\Api\RestRoutes;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Core\WpConnectors;

$missing          = new stdClass();
$checks           = array();
$original_user_id = get_current_user_id();
$originals        = array(
	Settings::OPTION                 => get_option( Settings::OPTION, $missing ),
	Settings::KEY_OPTION             => get_option( Settings::KEY_OPTION, $missing ),
	WpConnectors::WP_OPTION_KEY      => get_option( WpConnectors::WP_OPTION_KEY, $missing ),
	HealthDiagnostics::OPTION        => get_option( HealthDiagnostics::OPTION, $missing ),
	SettingsPage::VERIFIED_OPTION    => get_option( SettingsPage::VERIFIED_OPTION, $missing ),
);

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

$restore_option = static function ( string $key, $value ) use ( $missing ): void {
	if ( $value === $missing ) {
		delete_option( $key );
		return;
	}

	update_option( $key, $value, false );
};

$state      = 'success';
$transports = 0;
$provider_filter = static function ( $pre, array $args, string $url ) use ( &$state, &$transports ) {
	if ( false === strpos( $url, '.example/' ) ) {
		return $pre;
	}

	++$transports;
	if ( 'unreachable' === $state ) {
		return new WP_Error( 'http_request_failed', 'Local Health fixture is unreachable.' );
	}

	if ( 'unauthorized' === $state ) {
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( array( 'error' => array( 'message' => 'Denied by local fixture.' ) ) ),
			'response' => array(
				'code'    => 401,
				'message' => 'Unauthorized',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	return array(
		'headers'  => array(),
		'body'     => wp_json_encode(
			array(
				'choices' => array(
					array(
						'message' => array( 'content' => 'pong' ),
					),
				),
				'usage'   => array(
					'prompt_tokens'     => 1,
					'completion_tokens' => 1,
					'total_tokens'      => 2,
				),
			)
		),
		'response' => array(
			'code'    => 200,
			'message' => 'OK',
		),
		'cookies'  => array(),
		'filename' => null,
	);
};

try {
	wp_set_current_user( 1 );
	add_filter( 'pre_http_request', $provider_filter, 10, 3 );
	do_action( 'rest_api_init' );

	$routes = rest_get_server()->get_routes();
	$check(
		isset( $routes[ '/' . RestRoutes::NAMESPACE . '/health/diagnostics' ] ),
		'Health diagnostics REST route is registered for the local administrator'
	);

	Settings::update(
		array(
			'provider'           => 'openai',
			'base_url'           => 'https://saved-health-provider.example/v1',
			'model'              => 'health-fixture-model',
			'key_management'     => 'keyvault',
			'embeddings_enabled' => false,
			'embedding_model'    => '',
			'kb_auto_index'      => true,
		)
	);
	Settings::set_api_key( 'health-saved-secret' );
	delete_option( HealthDiagnostics::OPTION );

	$dispatch = static function (): WP_REST_Response {
		return rest_do_request( new WP_REST_Request( 'POST', '/aisaautopilot/v1/health/diagnostics' ) );
	};
	$find = static function ( array $rows, string $id ): array {
		foreach ( $rows as $row ) {
			if ( $id === ( $row['id'] ?? '' ) ) {
				return $row;
			}
		}

		return array();
	};

	$success      = $dispatch();
	$success_data = (array) $success->get_data();
	$success_rows = (array) ( $success_data['report']['checks'] ?? array() );
	$ids          = array_map( static fn( array $row ): string => (string) ( $row['id'] ?? '' ), $success_rows );
	$check(
		200 === $success->get_status()
		&& true === ( $success_data['ok'] ?? false )
		&& array( 'provider', 'api_key', 'key_storage', 'embeddings', 'cron', 'index_freshness', 'rate_limit', 'runtime' ) === $ids
		&& 'Good' === ( $find( $success_rows, 'provider' )['state'] ?? '' )
		&& 'Good' === ( $find( $success_rows, 'api_key' )['state'] ?? '' )
		&& 'Good' === ( $find( $success_rows, 'key_storage' )['state'] ?? '' )
		&& 'Not measured' === ( $find( $success_rows, 'rate_limit' )['state'] ?? '' )
		&& 1 === $transports,
		'Health runs one saved-provider request and returns the exact ordered eight-check contract'
	);

	$snapshot_json = (string) wp_json_encode( get_option( HealthDiagnostics::OPTION, array() ) );
	$payload_json  = (string) wp_json_encode( $success_data );
	$check(
		false === strpos( $snapshot_json, 'health-saved-secret' )
		&& false === strpos( $snapshot_json, 'saved-health-provider.example' )
		&& false === strpos( $payload_json, 'health-saved-secret' )
		&& false === strpos( $payload_json, 'saved-health-provider.example' ),
		'Health snapshot and REST response contain no credential or raw provider endpoint'
	);

	update_option( Settings::KEY_OPTION, 'invalid-vault-ciphertext', false );
	update_option( WpConnectors::WP_OPTION_KEY, 'health-connectors-fallback-secret', false );
	$fallback_rows = ( new HealthDiagnostics() )->report()['checks'];
	$fallback_key  = $find( $fallback_rows, 'key_storage' );
	$fallback_source = WpConnectors::get_effective_api_key_source();
	$source_detail   = match ( $fallback_source ) {
		'environment'          => 'environment variable',
		'constant'             => 'PHP constant',
		'wordpress_connectors' => 'WordPress Connectors',
		default                => '',
	};
	$fallback_ok = 'Good' === ( $fallback_key['state'] ?? '' )
		&& false === strpos( (string) ( $fallback_key['detail'] ?? '' ), 'Encrypted in the AISAAutoPilot vault' )
		&& '' !== $source_detail
		&& false !== strpos( (string) ( $fallback_key['detail'] ?? '' ), $source_detail );
	$check(
		$fallback_ok,
		'Key storage reports the actual non-vault effective source instead of a corrupt vault ciphertext'
	);
	Settings::set_api_key( 'health-saved-secret' );
	delete_option( WpConnectors::WP_OPTION_KEY );

	$state          = 'unauthorized';
	$unauthorized   = $dispatch();
	$unauthorized_d = (array) $unauthorized->get_data();
	$unauthorized_r = (array) ( $unauthorized_d['report']['checks'] ?? array() );
	$check(
		200 === $unauthorized->get_status()
		&& 'Failing' === ( $find( $unauthorized_r, 'provider' )['state'] ?? '' )
		&& 'Failing' === ( $find( $unauthorized_r, 'api_key' )['state'] ?? '' )
		&& 2 === $transports,
		'Provider 401 is represented as a saved Health failure and an API-key failure'
	);

	$state         = 'unreachable';
	$unreachable   = $dispatch();
	$unreachable_d = (array) $unreachable->get_data();
	$unreachable_r = (array) ( $unreachable_d['report']['checks'] ?? array() );
	$check(
		200 === $unreachable->get_status()
		&& 'Failing' === ( $find( $unreachable_r, 'provider' )['state'] ?? '' )
		&& 'Unknown' === ( $find( $unreachable_r, 'api_key' )['state'] ?? '' )
		&& 3 === $transports,
		'Provider network failure does not falsely classify the configured key as rejected'
	);

	$before_draft_test = get_option( HealthDiagnostics::OPTION, array() );
	$state             = 'success';
	$draft_request     = new WP_REST_Request( 'POST', '/aisaautopilot/v1/provider/test' );
	$draft_request->set_param( 'provider', 'openai' );
	$draft_request->set_param( 'base_url', 'https://unsaved-draft-provider.example/v1' );
	$draft_request->set_param( 'model', 'draft-only-model' );
	$draft_request->set_param( 'api_key', 'unsaved-draft-secret' );
	$draft_response = rest_do_request( $draft_request );
	$check(
		200 === $draft_response->get_status()
		&& $before_draft_test === get_option( HealthDiagnostics::OPTION, array() )
		&& 4 === $transports,
		'An unsaved Settings provider test does not overwrite the saved-config Health snapshot'
	);
} catch ( Throwable $error ) {
	$check( false, 'Health diagnostics integration completes without a fatal error' );
} finally {
	remove_filter( 'pre_http_request', $provider_filter, 10 );
	delete_transient( 'aisaautopilot_provider_test_1' );
	foreach ( $originals as $key => $value ) {
		$restore_option( $key, $value );
	}
	wp_set_current_user( $original_user_id );
}

$failed = array_filter( $checks, static fn( array $item ): bool => ! $item['ok'] );
foreach ( $checks as $item ) {
	echo ( $item['ok'] ? 'PASS ' : 'FAIL ' ) . $item['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
