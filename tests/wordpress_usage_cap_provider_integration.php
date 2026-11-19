<?php
/**
 * Local WordPress contract checks for the central token-cap provider guard.
 *
 * No outbound traffic is permitted. pre_http_request turns a transport attempt
 * into an observable test failure and every option is restored in finally.
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Providers\ProviderException;
use AISAAutoPilot\Providers\ProviderManager;
use AISAAutoPilot\Providers\UsageCappedProvider;

$missing = new stdClass();
$checks  = array();
$options = array(
	Settings::OPTION,
	Settings::KEY_OPTION,
	'aisaautopilot_monthly_budget',
	'aisaautopilot_usage_totals',
	'aisaautopilot_usage_period',
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

$call_code = static function ( callable $call ): string {
	try {
		$call();
	} catch ( ProviderException $error ) {
		return $error->error_code();
	}

	return 'no_exception';
};

try {
	wp_set_current_user( 1 );
	Settings::update(
		array(
			'provider'       => 'deepseek',
			'base_url'       => 'https://usage-cap-provider-test.invalid/v1',
			'model'          => 'usage-cap-contract-model',
			'key_management' => 'keyvault',
		)
	);
	Settings::set_api_key( 'usage-cap-provider-test-key' );
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

	$provider_filter = static function ( $preempt, array $args, string $url ) use ( &$provider_attempts ) {
		++$provider_attempts;
		return new WP_Error( 'usage_cap_provider_called', 'Provider must not be contacted while the cap is exhausted.' );
	};
	add_filter( 'pre_http_request', $provider_filter, 10, 3 );

	$capped = ( new ProviderManager() )->make();
	$codes  = array(
		$call_code( static function () use ( $capped ): void {
			$capped->generate_structured_output( 'System', 'User' );
		} ),
		$call_code( static function () use ( $capped ): void {
			$capped->generate_text( 'System', 'User' );
		} ),
		$call_code( static function () use ( $capped ): void {
			$capped->generate_embedding( 'Meaningful text for an embedding.' );
		} ),
	);
	$check(
		$capped instanceof UsageCappedProvider
		&& array( 'usage_cap_reached', 'usage_cap_reached', 'usage_cap_reached' ) === $codes
		&& 0 === $provider_attempts,
		'Central cap guard blocks structured, text and embedding requests before transport'
	);

	update_option( 'aisaautopilot_monthly_budget', 0, false );
	$uncapped_code = $call_code(
		static function () use ( $capped ): void {
			$capped->generate_text( 'System', 'User' );
		}
	);
	$check(
		'provider_unreachable' === $uncapped_code
		&& 1 === $provider_attempts,
		'Unlimited usage passes the guard and reaches the transport pre-flight exactly once'
	);

	$raw = ProviderManager::build(
		array(
			'provider' => 'deepseek',
			'base_url' => 'https://usage-cap-provider-test.invalid/v1',
			'model'    => 'usage-cap-contract-model',
			'api_key'  => 'usage-cap-provider-test-key',
		)
	);
	$check(
		! $raw instanceof UsageCappedProvider,
		'Explicit provider builds for Settings diagnostics remain outside the generation cap wrapper'
	);
} catch ( Throwable $error ) {
	$check( false, 'Usage cap provider contract completes without a fatal error' );
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
