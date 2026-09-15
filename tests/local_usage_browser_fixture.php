<?php
/**
 * Creates and removes the isolated Usage browser-QA fixture.
 *
 * It changes only usage options, never calls a provider, and restores every
 * previous option value (including absence) when run with `restore`.
 */

declare(strict_types=1);

$mode = $argv[1] ?? 'prepare';
if ( ! in_array( $mode, array( 'prepare', 'restore' ), true ) ) {
	fwrite( STDERR, "Usage: php local_usage_browser_fixture.php prepare|restore\n" );
	exit( 64 );
}

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

const FIXTURE_OPTION = 'aisaautopilot_usage_browser_fixture';

$options = array(
	'aisaautopilot_usage_log',
	'aisaautopilot_usage_totals',
	'aisaautopilot_monthly_budget',
	'aisaautopilot_usage_period',
	'aisaautopilot_usage_period_contexts',
	'aisaautopilot_usage_daily',
	'aisaautopilot_usage_historical_totals',
	'aisaautopilot_usage_historical_log',
	'aisaautopilot_usage_period_archive',
);

$restore = static function () use ( $options ): void {
	$record = get_option( FIXTURE_OPTION, array() );
	if ( ! is_array( $record ) || ! is_array( $record['options'] ?? null ) ) {
		delete_option( FIXTURE_OPTION );
		return;
	}

	foreach ( $options as $option ) {
		$snapshot = $record['options'][ $option ] ?? array();
		if ( ! empty( $snapshot['exists'] ) ) {
			update_option( $option, $snapshot['value'] ?? null, false );
		} else {
			delete_option( $option );
		}
	}
	delete_option( FIXTURE_OPTION );
};

if ( 'restore' === $mode ) {
	$restore();
	echo "Usage browser fixture removed.\n";
	exit( 0 );
}

$restore();
$missing   = new stdClass();
$snapshots = array();
foreach ( $options as $option ) {
	$value                = get_option( $option, $missing );
	$snapshots[ $option ] = array(
		'exists' => $value !== $missing,
		'value'  => $value !== $missing ? $value : null,
	);
}

try {
	update_option( FIXTURE_OPTION, array( 'options' => $snapshots ), false );

	$timezone = wp_timezone();
	$today    = new DateTimeImmutable( 'now', $timezone );
	$daily    = array();
	for ( $offset = 6; $offset >= 0; --$offset ) {
		$day = $today->setTime( 0, 0, 0 )->modify( '-' . $offset . ' days' );
		$daily[ $day->format( 'Y-m-d' ) ] = array(
			'tokens'   => ( 7 - $offset ) * 25,
			'requests' => 1,
		);
	}

	update_option( 'aisaautopilot_usage_log', array(), false );
	update_option(
		'aisaautopilot_usage_totals',
		array(
			'prompt_tokens'     => 500,
			'completion_tokens' => 400,
			'total_tokens'      => 900,
			'request_count'     => 9,
			'last_used'         => $today->format( 'c' ),
		),
		false
	);
	update_option( 'aisaautopilot_monthly_budget', 1000, false );
	update_option( 'aisaautopilot_usage_period', $today->format( 'Y-m' ), false );
	update_option(
		'aisaautopilot_usage_period_contexts',
		array(
			'text_generation'   => array( 'tokens' => 400, 'requests' => 4 ),
			'structured_output' => array( 'tokens' => 300, 'requests' => 3 ),
			'embedding'         => array( 'tokens' => 200, 'requests' => 2 ),
		),
		false
	);
	update_option( 'aisaautopilot_usage_daily', $daily, false );
	update_option( 'aisaautopilot_usage_historical_totals', array(), false );
	update_option( 'aisaautopilot_usage_historical_log', array(), false );
	update_option( 'aisaautopilot_usage_period_archive', array(), false );

	echo wp_json_encode(
		array(
			'period' => $today->format( 'Y-m' ),
			'budget' => 1000,
			'tokens' => 900,
		)
	) . PHP_EOL;
} catch ( Throwable $error ) {
	$restore();
	fwrite( STDERR, $error->getMessage() . PHP_EOL );
	exit( 1 );
}
