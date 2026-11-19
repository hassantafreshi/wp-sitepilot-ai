<?php
/**
 * Local WordPress checks for Store Copilot > Orders > Checkout drop-off. Needs
 * WooCommerce active. No email leaves the site (pre_wp_mail); no AI is involved.
 *
 * Proves the figures come from WooCommerce's own orders: only orders customers placed
 * at checkout in the chosen period count (not orders an administrator added, not older
 * ones), each status lands in the right group, unpaid and failed checkouts are grouped
 * by payment method, and the card is shown only when the connector is on.
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\StoreCopilotPage;
use AISAAutoPilot\Connectors\WooCommerceAnalyticsSkill;
use AISAAutoPilot\Core\Settings;

$checks = array();
$check  = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array( 'ok' => $ok, 'name' => $name );
};
if ( ! function_exists( 'wc_create_order' ) ) {
	echo "SKIP WooCommerce is not active\n";
	exit( 0 );
}

$original_settings = get_option( Settings::OPTION );
$original_logs     = get_option( 'aisaautopilot_logs', null );
$no_mail           = static function () {
	return true;
};
add_filter( 'pre_wp_mail', $no_mail );
$created = array();
$suffix  = wp_generate_password( 4, false );
$card    = 'QA Card ' . $suffix;
$wallet  = 'QA Wallet ' . $suffix;
$make    = static function ( string $status, string $via, string $method = '', int $age_days = 1 ) use ( &$created ): int {
	$order = wc_create_order();
	$order->set_created_via( $via );
	$order->set_payment_method_title( $method );
	$order->set_total( 10 );
	$order->set_date_created( time() - $age_days * DAY_IN_SECONDS );
	$order->set_status( $status );
	$order->save();
	$created[] = $order->get_id();
	return $order->get_id();
};
$render = static function ( array $get ): string {
	$_GET = array_merge( array( 'page' => 'aisaautopilot-wc-dashboard' ), $get );
	ob_start();
	( new StoreCopilotPage() )->render();
	$html = (string) ob_get_clean();
	$_GET = array();
	return $html;
};

try {
	wp_set_current_user( 1 );
	Settings::update( array( 'woocommerce' => array( 'enabled' => true, 'auto_draft' => true, 'enable_search' => false, 'enable_edit' => false, 'enable_descriptions' => false ) ) );
	$skill  = new WooCommerceAnalyticsSkill();
	$before = $skill->checkout_dropoff( 30 );

	// Customer checkouts in the last 30 days (classic and block checkout).
	$make( 'processing', 'checkout' );
	$make( 'completed', 'store-api' );
	$make( 'on-hold', 'checkout' );
	$make( 'pending', 'checkout', $card );
	$make( 'failed', 'store-api', $card );
	$make( 'failed', 'checkout', $wallet, 3 );
	$make( 'cancelled', 'checkout' );
	// Not counted: added by an administrator, or older than the period.
	$make( 'failed', 'admin', $card );
	$make( 'pending', 'rest-api', $card );
	$make( 'failed', 'checkout', $card, 45 );

	$after = $skill->checkout_dropoff( 30 );
	$delta = static function ( string $key ) use ( $before, $after ): int {
		return (int) $after[ $key ] - (int) $before[ $key ];
	};

	// 1. Each status in its group; admin, API and older orders left out.
	$check(
		! empty( $after['ok'] ) && 3 === $delta( 'paid' ) && 1 === $delta( 'pending' ) && 2 === $delta( 'failed' ) && 1 === $delta( 'cancelled' ) && 7 === $delta( 'total' ),
		'Paid, stopped at payment, failed and cancelled checkouts are counted; orders added by an administrator or over the API, and older ones, are not'
	);

	// 2. The completion rate is paid / all checkouts.
	$check(
		null !== $after['completion'] && abs( (float) $after['completion'] - round( 100 * $after['paid'] / $after['total'], 1 ) ) < 0.01,
		'The paid share is paid checkouts out of all checkouts in the period'
	);

	// 3. Unpaid and failed checkouts by payment method: the card first (2), then the wallet (1).
	$methods = array_column( (array) $after['methods'], 'count', 'name' );
	$check(
		2 === (int) ( $methods[ $card ] ?? 0 ) && 1 === (int) ( $methods[ $wallet ] ?? 0 ),
		'Unpaid and failed checkouts are grouped by payment method, without the administrator\'s order'
	);

	// 4. The period: 90 days takes in the 45-day-old checkout; an unknown period falls back to 30.
	$ninety = $skill->checkout_dropoff( 90 );
	$odd    = $skill->checkout_dropoff( 365 );
	$check(
		(int) $ninety['failed'] >= (int) $after['failed'] + 1 && 30 === (int) $odd['days'],
		'90 days includes older checkouts; a period other than 7, 30 or 90 is read as 30'
	);

	// 5. The card on Store Copilot > Orders: figures, period links and WooCommerce order links.
	$html = $render( array( 'tab' => 'orders', 'days' => '7' ) );
	$check(
		false !== strpos( $html, 'data-sp-store-funnel data-sp-funnel-days="7"' ) && false !== strpos( $html, 'data-sp-funnel-row="failed"' )
		&& false !== strpos( $html, 'aria-current="page"' ) && 1 === preg_match( '/status=wc-failed|post_status=wc-failed/', $html )
		&& (int) strpos( $html, 'data-sp-store-funnel' ) < (int) strpos( $html, 'data-sp-store-orders' ),
		'Store Copilot > Orders shows the drop-off card for the chosen period, with links to the failed orders, above the order-access rules'
	);

	// 6. Connector off: no figures are read or shown.
	Settings::update( array( 'woocommerce' => array( 'enabled' => false, 'auto_draft' => true, 'enable_search' => false, 'enable_edit' => false, 'enable_descriptions' => false ) ) );
	$off = $render( array( 'tab' => 'orders' ) );
	$check( false === strpos( $off, 'data-sp-store-funnel' ) && false !== strpos( $off, 'data-sp-store-orders' ), 'With the WooCommerce connector off, the card is not shown' );
} finally {
	remove_filter( 'pre_wp_mail', $no_mail );
	foreach ( $created as $id ) {
		$o = wc_get_order( $id );
		if ( $o ) {
			$o->delete( true );
		}
	}
	update_option( Settings::OPTION, $original_settings );
	if ( null === $original_logs ) {
		delete_option( 'aisaautopilot_logs' );
	} else {
		update_option( 'aisaautopilot_logs', $original_logs, false );
	}
}

$failed = 0;
foreach ( $checks as $c ) {
	echo ( $c['ok'] ? 'PASS ' : 'FAIL ' ) . $c['name'] . "\n";
	$failed += $c['ok'] ? 0 : 1;
}
echo count( $checks ) . " checks, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
