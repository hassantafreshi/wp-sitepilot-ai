<?php
/**
 * Local WordPress integration checks for UsageTracker's monthly report.
 *
 * The script owns every option it changes and restores each one in finally.
 * Its clock is fixed per assertion so rollover and seven-day buckets do not
 * depend on WP-Cron, the server timezone or the real calendar date.
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Usage\UsageTracker;

$missing = new stdClass();
$checks  = array();
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
$originals = array();
foreach ( $options as $option ) {
	$originals[ $option ] = get_option( $option, $missing );
}

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

$clear_options = static function ( array $options ): void {
	foreach ( $options as $option ) {
		delete_option( $option );
	}
};

try {
	$clear_options( $options );
	$timezone   = wp_timezone();
	$legacy_now = new DateTimeImmutable( '2032-03-11 10:00:00', $timezone );

	update_option(
		'aisaautopilot_usage_totals',
		array(
			'prompt_tokens'     => 50,
			'completion_tokens' => 20,
			'total_tokens'      => 70,
			'request_count'     => 3,
			'last_used'         => '2031-12-31T20:00:00+00:00',
		),
		false
	);
	update_option(
		'aisaautopilot_usage_log',
		array(
			array(
				'time'       => '2031-12-31T20:00:00+00:00',
				'model'      => 'legacy-model',
				'prompt'     => 50,
				'completion' => 20,
				'total'      => 70,
				'context'    => 'text_generation',
				'user_id'    => 1,
			),
		),
		false
	);

	$legacy_tracker = new UsageTracker( $legacy_now );
	$legacy_tracker->set_budget( 100 );
	$legacy_report = $legacy_tracker->get_report();
	$check(
		0 === (int) $legacy_report['totals']['total_tokens']
		&& 70 === (int) $legacy_report['historical_totals']['total_tokens']
		&& 100 === (int) $legacy_report['budget']
		&& '2032-03' === (string) $legacy_report['period']
		&& 0 === count( (array) get_option( 'aisaautopilot_usage_log', array() ) ),
		'Legacy cumulative usage is preserved separately and is not fabricated as this month'
	);
	$check(
		1 === count( (array) get_option( 'aisaautopilot_usage_historical_log', array() ) )
		&& ! $legacy_tracker->is_exhausted(),
		'Legacy events and the configured cap survive migration without pausing a new month'
	);

	$clear_options( $options );
	$first_day = new DateTimeImmutable( '2032-06-26 09:30:00', $timezone );
	$tracker   = new UsageTracker( $first_day );
	$tracker->set_budget( 100 );

	for ( $offset = 0; $offset < 5; ++$offset ) {
		$day = $first_day->modify( '+' . $offset . ' days' );
		( new UsageTracker( $day ) )->record( 'chart-model', $offset + 1, 0, 'text_generation' );
	}
	$july_first  = $first_day->modify( '+5 days' );
	$july_second = $first_day->modify( '+6 days' );
	( new UsageTracker( $july_first ) )->record( 'chart-model', 6, 0, 'structured_output' );
	( new UsageTracker( $july_second ) )->record( 'chart-model', 7, 0, 'structured_output' );

	$report      = ( new UsageTracker( $july_second ) )->get_report();
	$day_totals  = array_map( static fn( array $day ): int => (int) $day['tokens'], (array) $report['last_7_days'] );
	$archive     = (array) get_option( 'aisaautopilot_usage_period_archive', array() );
	$last_archive = $archive[ count( $archive ) - 1 ] ?? array();
	$check(
		array( 1, 2, 3, 4, 5, 6, 7 ) === $day_totals
		&& 13 === (int) $report['totals']['total_tokens']
		&& 2 === (int) $report['totals']['request_count']
		&& 15 === (int) ( $last_archive['totals']['total_tokens'] ?? 0 )
		&& '2032-06' === (string) ( $last_archive['period'] ?? '' ),
		'Lazy rollover keeps seven real day buckets across the month boundary and archives the closed total'
	);
	$check(
		15 === (int) ( $report['by_context']['text_generation']['tokens'] ?? 0 )
		&& 13 === (int) ( $report['period_by_context']['structured_output']['tokens'] ?? 0 )
		&& 28 === array_sum( $day_totals ),
		'Additive current-period breakdown stays distinct from the retained-log API breakdown'
	);

	$retained_log = get_option( 'aisaautopilot_usage_log', array() );
	update_option( 'aisaautopilot_usage_log', array(), false );
	$trimmed_report = ( new UsageTracker( $july_second ) )->get_report();
	$check(
		13 === (int) ( $trimmed_report['period_by_context']['structured_output']['tokens'] ?? 0 )
		&& 2 === (int) ( $trimmed_report['period_by_context']['structured_output']['requests'] ?? 0 ),
		'Current-period operation totals survive retained-event ring-buffer trimming'
	);
	update_option( 'aisaautopilot_usage_log', $retained_log, false );

	( new UsageTracker( $july_second ) )->reset();
	$after_reset = ( new UsageTracker( $july_second ) )->get_report();
	$check(
		0 === (int) $after_reset['totals']['total_tokens']
		&& 100 === (int) $after_reset['budget']
		&& array() === (array) $after_reset['period_by_context']
		&& array( 1, 2, 3, 4, 5, 0, 0 ) === array_map( static fn( array $day ): int => (int) $day['tokens'], (array) $after_reset['last_7_days'] ),
		'Resetting current-period usage keeps the cap and prior seven-day evidence intact'
	);

	update_option( 'aisaautopilot_usage_totals', array( 'total_tokens' => -9, 'request_count' => -2 ), false );
	update_option( 'aisaautopilot_usage_log', array( 'not-an-entry' ), false );
	$malformed = ( new UsageTracker( $july_second ) )->get_report();
	$check(
		0 === (int) $malformed['totals']['total_tokens']
		&& 0 === (int) $malformed['totals']['request_count']
		&& array() === (array) $malformed['by_context']
		&& 7 === count( (array) $malformed['last_7_days'] ),
		'Malformed or negative stored data is normalised without breaking the report'
	);
	update_option( 'aisaautopilot_monthly_budget', -5000, false );
	$negative_budget_tracker = new UsageTracker( $july_second );
	$check(
		0 === $negative_budget_tracker->get_budget()
		&& ! $negative_budget_tracker->is_exhausted(),
		'A corrupt negative stored cap is normalised to unlimited rather than becoming positive'
	);

	$clear_options( $options );
	$future_now = new DateTimeImmutable( '2032-09-14 10:00:00', $timezone );
	update_option( 'aisaautopilot_usage_period', '2099-01', false );
	update_option(
		'aisaautopilot_usage_totals',
		array(
			'prompt_tokens'     => 77,
			'completion_tokens' => 0,
			'total_tokens'      => 77,
			'request_count'     => 1,
			'last_used'         => '2099-01-04T09:00:00+00:00',
		),
		false
	);
	update_option(
		'aisaautopilot_usage_log',
		array(
			array(
				'time'       => '2099-01-04T09:00:00+00:00',
				'period'     => '2099-01',
				'model'      => 'future-corrupt-model',
				'prompt'     => 77,
				'completion' => 0,
				'total'      => 77,
				'context'    => 'text_generation',
				'user_id'    => 1,
			),
		),
		false
	);
	$future_report  = ( new UsageTracker( $future_now ) )->get_report();
	$future_archive = (array) get_option( 'aisaautopilot_usage_period_archive', array() );
	$has_future_archive = false;
	foreach ( $future_archive as $archive_entry ) {
		if ( is_array( $archive_entry ) && '2099-01' === (string) ( $archive_entry['period'] ?? '' ) ) {
			$has_future_archive = true;
		}
	}
	$check(
		'2032-09' === (string) $future_report['period']
		&& 0 === (int) $future_report['totals']['total_tokens']
		&& 77 === (int) $future_report['historical_totals']['total_tokens']
		&& ! $has_future_archive
		&& array() === (array) get_option( 'aisaautopilot_usage_log', array() ),
		'A corrupt future period is preserved as history and never fabricated as a closed archive'
	);

	$clear_options( $options );
	$migration_now = new DateTimeImmutable( '2032-11-12 10:00:00', $timezone );
	update_option( 'aisaautopilot_usage_period', '2032-11', false );
	update_option(
		'aisaautopilot_usage_totals',
		array(
			'prompt_tokens'     => 10,
			'completion_tokens' => 0,
			'total_tokens'      => 10,
			'request_count'     => 2,
			'last_used'         => '2032-11-12T09:00:00+00:00',
		),
		false
	);
	update_option(
		'aisaautopilot_usage_log',
		array(
			array(
				'time'       => '2032-11-11T09:00:00+00:00',
				'period'     => '2032-11',
				'model'      => 'migration-model',
				'prompt'     => 7,
				'completion' => 0,
				'total'      => 7,
				'context'    => 'embedding',
				'user_id'    => 1,
			),
			array(
				'time'       => '2032-11-12T09:00:00+00:00',
				'period'     => '2032-11',
				'model'      => 'migration-model',
				'prompt'     => 3,
				'completion' => 0,
				'total'      => 3,
				'context'    => 'text_generation',
				'user_id'    => 1,
			),
		),
		false
	);
	$derived_report = ( new UsageTracker( $migration_now ) )->get_report();
	( new UsageTracker( $migration_now ) )->record( 'migration-model', 2, 0, 'embedding' );
	$migrated_report = ( new UsageTracker( $migration_now ) )->get_report();
	$check(
		7 === (int) ( $derived_report['period_by_context']['embedding']['tokens'] ?? 0 )
		&& 3 === (int) ( $derived_report['period_by_context']['text_generation']['tokens'] ?? 0 )
		&& 9 === (int) ( $migrated_report['period_by_context']['embedding']['tokens'] ?? 0 )
		&& 2 === (int) ( $migrated_report['period_by_context']['embedding']['requests'] ?? 0 )
		&& 3 === (int) ( $migrated_report['period_by_context']['text_generation']['tokens'] ?? 0 )
		&& is_array( get_option( 'aisaautopilot_usage_daily', null ) ),
		'Missing period-context and daily options derive once, then record without double-counting'
	);

	$clear_options( $options );
	$busy_first  = new DateTimeImmutable( '2032-08-20 09:30:00', $timezone );
	$busy_last   = new DateTimeImmutable( '2032-08-26 09:30:00', $timezone );
	$busy_tracker = new UsageTracker( $busy_last );
	( new UsageTracker( $busy_first ) )->record( 'chart-model', 23, 0, 'embedding' );
	for ( $request = 0; $request < 500; ++$request ) {
		$busy_tracker->record( 'chart-model', 1, 0, 'structured_output' );
	}
	$busy_report = $busy_tracker->get_report();
	$busy_days   = array_map( static fn( array $day ): int => (int) $day['tokens'], (array) $busy_report['last_7_days'] );
	$busy_log    = (array) get_option( 'aisaautopilot_usage_log', array() );
	$check(
		500 === count( $busy_log )
		&& array( 23, 0, 0, 0, 0, 0, 500 ) === $busy_days
		&& 23 === (int) ( $busy_report['period_by_context']['embedding']['tokens'] ?? 0 )
		&& 500 === (int) ( $busy_report['period_by_context']['structured_output']['tokens'] ?? 0 ),
		'Daily aggregates keep the seven-day chart and operation split complete after 500 retained events'
	);
} catch ( Throwable $error ) {
	$check( false, 'UsageTracker monthly report completes without a fatal error' );
} finally {
	foreach ( $options as $option ) {
		$restore_option( $option, $originals[ $option ] );
	}
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
