<?php
/**
 * Local WordPress integration check for Store Copilot's exhausted-cap path.
 *
 * This intentionally requires a real, active WooCommerce installation. It
 * never creates a product or proposal: the pre-HTTP filter makes any provider
 * transport observable, while the exhausted shared budget must stop the plan
 * before an action ID can be created. Every option changed by the test and the
 * current user are restored in finally.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Api\WooCommerceController;
use AISAAutoPilot\Connectors\WooCommerceConnector;
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

	if ( ! WooCommerceConnector::is_active() ) {
		$check( false, 'Prerequisite: WooCommerce must be active in local WordPress for the Store cap contract' );
	} elseif ( ! WooCommerceConnector::current_user_can_manage() ) {
		$check( false, 'Prerequisite: local administrator must be allowed to manage WooCommerce for the Store cap contract' );
	} else {
		Settings::update(
			array(
				'provider'       => 'deepseek',
				'base_url'       => 'https://usage-cap-store-test.invalid/v1',
				'model'          => 'usage-cap-store-contract-model',
				'key_management' => 'keyvault',
				'woocommerce'    => array(
					'enabled'       => true,
					'auto_draft'    => true,
					'enable_search' => false,
					'enable_edit'   => false,
				),
			)
		);
		Settings::set_api_key( 'usage-cap-store-test-key' );
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
			return new WP_Error( 'usage_cap_store_provider_called', 'Provider transport must not run while the Store cap is exhausted.' );
		};
		add_filter( 'pre_http_request', $provider_filter, 10, 3 );

		$request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/woocommerce/plan' );
		$request->set_param( 'prompt', 'Create one temporary product proposal.' );
		$response = ( new WooCommerceController() )->plan( $request );
		$data     = (array) $response->get_data();

		$check(
			429 === $response->get_status()
			&& false === ( $data['ok'] ?? true )
			&& 'usage_cap_reached' === ( $data['code'] ?? '' )
			&& ! array_key_exists( 'action_id', $data ),
			'Store proposal returns usage_cap_reached as 429 without creating an action ID'
		);
		$check( 0 === $provider_attempts, 'Store proposal reaches no provider transport while the shared cap is exhausted' );
	}
} catch ( Throwable $error ) {
	$check( false, 'Store cap contract completes without a fatal error' );
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
