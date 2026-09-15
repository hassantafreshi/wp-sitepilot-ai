<?php
/**
 * Local WordPress integration check for Overview's first-day and banner states.
 *
 * Every option touched by this script is restored in finally, including the
 * encrypted provider-key option. The temporary key is never printed.
 */
declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\xampp\htdocs\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\AdminMenu;
use AISAAutoPilot\Admin\HomePage;
use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Core\WpConnectors;
use AISAAutoPilot\Providers\ProviderManager;
use AISAAutoPilot\Usage\UsageTracker;

wp_set_current_user( 1 );

$checks  = array();
$missing = new stdClass();

$original_settings       = get_option( Settings::OPTION, $missing );
$original_key            = get_option( Settings::KEY_OPTION, $missing );
$original_connector_key  = get_option( WpConnectors::WP_OPTION_KEY, $missing );
$original_usage_budget   = get_option( 'aisaautopilot_monthly_budget', $missing );
$original_usage_totals   = get_option( 'aisaautopilot_usage_totals', $missing );
$original_usage_period   = get_option( 'aisaautopilot_usage_period', $missing );

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'name' => $name,
		'ok'   => $ok,
	);
};

$render = static function (): string {
	ob_start();
	( new HomePage() )->render();
	return (string) ob_get_clean();
};

$restore_option = static function ( string $option, $value ) use ( $missing ): void {
	if ( $value === $missing ) {
		delete_option( $option );
		return;
	}
	update_option( $option, $value, false );
};

try {
	$overview     = new HomePage();
	$stats_method  = new ReflectionMethod( HomePage::class, 'stats' );
	$needs_method  = new ReflectionMethod( HomePage::class, 'needs_you' );
	$stats_method->setAccessible( true );
	$needs_method->setAccessible( true );

	$first_day_stats = (array) $stats_method->invoke( $overview, true, 0, 0, 0, 0, 0, 0 );
	$first_day_needs = (array) $needs_method->invoke( $overview, true, true, 0, 0, 0, 0, 0.0, false, true );
	$first_day_urls  = array_column( $first_day_needs, 'url' );
	$check(
		4 === count( $first_day_stats )
		&& 'drafts-created' === ( $first_day_stats[2]['id'] ?? '' )
		&& 'tokens' === ( $first_day_stats[3]['id'] ?? '' ),
		'Overview first-day state keeps the mockup metric identities'
	);
	$check(
		2 === count( $first_day_needs )
		&& in_array( admin_url( 'admin.php?page=' . AdminMenu::SLUG_KB ), $first_day_urls, true )
		&& in_array( admin_url( 'admin.php?page=' . AdminMenu::SLUG_CONVERSATIONS ), $first_day_urls, true ),
		'Overview first-day state routes both mockup guidance cards to real screens'
	);

	$no_service_settings                   = Settings::defaults();
	$no_service_settings['key_management'] = 'keyvault';
	update_option( Settings::OPTION, $no_service_settings, false );
	delete_option( Settings::KEY_OPTION );
	delete_option( WpConnectors::WP_OPTION_KEY );
	$no_service_markup = $render();
	$check(
		! ( new ProviderManager() )->is_configured()
		&& false !== strpos( $no_service_markup, 'data-sp-banner="home:noservice"' ),
		'Overview renders the connected-service recovery banner when no provider key exists'
	);

	$paused_settings                   = Settings::defaults();
	$paused_settings['key_management'] = 'keyvault';
	update_option( Settings::OPTION, $paused_settings, false );
	Settings::set_api_key( 'overview-state-test-key' );
	( new UsageTracker() )->set_budget( 100 );
	update_option( 'aisaautopilot_usage_period', wp_date( 'Y-m' ), false );
	update_option(
		'aisaautopilot_usage_totals',
		array(
			'prompt_tokens'     => 60,
			'completion_tokens' => 40,
			'total_tokens'      => 100,
			'request_count'     => 1,
			'last_used'         => gmdate( 'c' ),
		),
		false
	);
	$paused_markup = $render();
	$check(
		( new ProviderManager() )->is_configured()
		&& false !== strpos( $paused_markup, 'data-sp-banner="home:paused"' ),
		'Overview renders the paused recovery banner when the token cap is reached'
	);
} finally {
	$restore_option( Settings::OPTION, $original_settings );
	$restore_option( Settings::KEY_OPTION, $original_key );
	$restore_option( WpConnectors::WP_OPTION_KEY, $original_connector_key );
	$restore_option( 'aisaautopilot_monthly_budget', $original_usage_budget );
	$restore_option( 'aisaautopilot_usage_totals', $original_usage_totals );
	$restore_option( 'aisaautopilot_usage_period', $original_usage_period );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
