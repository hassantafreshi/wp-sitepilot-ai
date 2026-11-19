<?php
/**
 * Local WordPress render contracts for the mockup-aligned Usage page.
 *
 * All usage options and the active user are restored in finally.
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\UsagePage;

$missing = new stdClass();
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
$original_user = get_current_user_id();
$checks        = array();

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

$render = static function (): string {
	ob_start();
	( new UsagePage() )->render();
	return (string) ob_get_clean();
};

try {
	wp_set_current_user( 1 );
	$timezone = wp_timezone();
	$today    = new DateTimeImmutable( 'now', $timezone );
	$period   = $today->format( 'Y-m' );
	$daily    = array();
	for ( $offset = 6; $offset >= 0; --$offset ) {
		$day = $today->setTime( 0, 0, 0 )->modify( '-' . $offset . ' days' );
		$daily[ $day->format( 'Y-m-d' ) ] = array(
			'tokens'   => ( 7 - $offset ) * 10,
			'requests' => 1,
		);
	}

	update_option( 'aisaautopilot_usage_period', $period, false );
	update_option( 'aisaautopilot_usage_totals', array(), false );
	update_option( 'aisaautopilot_monthly_budget', 0, false );
	update_option( 'aisaautopilot_usage_period_contexts', array(), false );
	update_option( 'aisaautopilot_usage_daily', array(), false );
	update_option( 'aisaautopilot_usage_log', array(), false );
	$empty_html = $render();
	$check(
		false !== strpos( $empty_html, 'data-sp-usage-state="empty"' )
		&& 2 === substr_count( $empty_html, 'data-sp-usage-card=' )
		&& 7 === substr_count( $empty_html, 'data-sp-usage-day aria-label=' )
		&& 4 === substr_count( $empty_html, 'data-sp-usage-operation-id=' )
		&& false !== strpos( $empty_html, 'No token cap is set.' )
		&& false !== strpos( $empty_html, 'No token usage has been recorded in this month.' )
		&& false === strpos( $empty_html, '$' )
		&& false === strpos( $empty_html, 'Free tier' )
		&& false === strpos( $empty_html, 'By Model' )
		&& false === strpos( $empty_html, 'Credit Health' ),
		'Empty unlimited state keeps the exact two-card shell without invented prices or legacy panels'
	);

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
	$near_html = $render();
	$near_text = html_entity_decode( $near_html, ENT_QUOTES, 'UTF-8' );
	$check(
		false !== strpos( $near_html, 'data-sp-usage-state="near"' )
		&& false !== strpos( $near_html, '900 tokens used' )
		&& false !== strpos( $near_html, 'Text generation' )
		&& false !== strpos( $near_html, 'Structured output' )
		&& false !== strpos( $near_html, 'Embeddings' )
		&& false !== strpos( $near_text, 'You are close to this month\'s token cap.' )
		&& false !== strpos( $near_html, 'data-sp-usage-cap-editor' )
		&& false !== strpos( $near_html, 'data-sp-usage-danger' )
		&& false !== strpos( $near_text, 'Reset this month\'s counter' )
		&& 1 === substr_count( $near_html, 'data-sp-usage-reset' ),
		'Near-cap state uses real token operations, seven-day data, an inline cap editor and a separate reset danger zone'
	);

	update_option(
		'aisaautopilot_usage_totals',
		array(
			'prompt_tokens'     => 600,
			'completion_tokens' => 400,
			'total_tokens'      => 1000,
			'request_count'     => 10,
			'last_used'         => $today->format( 'c' ),
		),
		false
	);
	update_option(
		'aisaautopilot_usage_period_contexts',
		array(
			'text_generation'        => array( 'tokens' => 400, 'requests' => 4 ),
			'structured_output'      => array( 'tokens' => 300, 'requests' => 3 ),
			'embedding'              => array( 'tokens' => 200, 'requests' => 2 ),
			'legacy_without_a_label' => array( 'tokens' => 50, 'requests' => 1 ),
		),
		false
	);
	$exhausted_html = $render();
	$exhausted_text = html_entity_decode( $exhausted_html, ENT_QUOTES, 'UTF-8' );
	$check(
		false !== strpos( $exhausted_html, 'data-sp-usage-state="exhausted"' )
		&& false !== strpos( $exhausted_text, 'This month\'s token cap has been reached.' )
		&& false !== strpos( $exhausted_html, 'Requests covered by this token cap are paused until the next monthly period.' )
		&& false !== strpos( $exhausted_html, 'Other or unclassified records' )
		&& false !== strpos( $exhausted_html, '100 tokens' )
		&& false === strpos( $exhausted_html, 'every AI feature' )
		&& false === strpos( $exhausted_html, 'emails you' ),
		'Exhausted state reconciles unlabeled records without claiming global AI or email behaviour'
	);
} catch ( Throwable $error ) {
	$check( false, 'Usage page render contract completes without a fatal error' );
} finally {
	foreach ( $options as $option ) {
		$restore_option( $option, $originals[ $option ] );
	}
	wp_set_current_user( $original_user );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
