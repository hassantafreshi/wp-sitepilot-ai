<?php
/**
 * Local WordPress render checks for the canonical eight-row Health page.
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\ConnectionsPage;

$checks           = array();
$original_user_id = get_current_user_id();

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

try {
	wp_set_current_user( 1 );
	ob_start();
	( new ConnectionsPage() )->render();
	$html = (string) ob_get_clean();

	preg_match_all( '/data-sp-health-id="([^"]+)"/', $html, $matches );
	$ids = $matches[1] ?? array();
	$check(
		8 === substr_count( $html, 'data-sp-health-row' )
		&& array( 'provider', 'api_key', 'key_storage', 'embeddings', 'cron', 'index_freshness', 'rate_limit', 'runtime' ) === $ids
		&& false !== strpos( $html, 'class="sp-health-card"' )
		&& false !== strpos( $html, 'id="sp-run-diagnostics"' ),
		'Health renders one canonical card with exactly the eight mockup rows in order'
	);

	$check(
		false === strpos( $html, 'sp-diag-summary' )
		&& false === strpos( $html, 'Visitor assistant limits' )
		&& false === strpos( $html, 'Handoff channels' )
		&& false === strpos( $html, 'Easy Form Builder' )
		&& false === strpos( $html, 'Open activity log' ),
		'Canonical Health removes legacy summary, connector, handoff, sidebar and activity-log surfaces absent from the mockup'
	);

	$check(
		1 === substr_count( $html, 'id="sp-health-list"' )
		&& 1 === substr_count( $html, 'id="sp-health-status"' )
		&& false !== strpos( $html, 'data-sp-health-check-count="8"' )
		&& false !== strpos( $html, 'Rate limit headroom' )
		&& false !== strpos( $html, 'Not measured' ),
		'Health exposes one accessible live report surface and truthful unmeasured rate-limit state'
	);
} catch ( Throwable $error ) {
	$check( false, 'Health page render completes without a fatal error' );
} finally {
	wp_set_current_user( $original_user_id );
}

$failed = array_filter( $checks, static fn( array $item ): bool => ! $item['ok'] );
foreach ( $checks as $item ) {
	echo ( $item['ok'] ? 'PASS ' : 'FAIL ' ) . $item['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
