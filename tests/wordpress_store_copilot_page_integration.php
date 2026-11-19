<?php
/**
 * Local WordPress integration checks for Store Copilot's four mockup tabs.
 * No provider request, product write, order read, or settings mutation occurs.
 */

declare(strict_types=1);

require_once __DIR__ . '/test-env.php';
$wp_root = aisa_test_wp_root();
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\StoreCopilotPage;
use AISAAutoPilot\Admin\WooCommerceSettings;

$original_get     = $_GET;
$original_user_id = get_current_user_id();
$checks           = array();

$check = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array(
		'ok'   => $ok,
		'name' => $name,
	);
};

$render = static function ( string $tab ): string {
	$_GET['tab'] = $tab;
	ob_start();
	( new StoreCopilotPage() )->render();
	return (string) ob_get_clean();
};

try {
	wp_set_current_user( 1 );
	$new_product  = $render( 'newproduct' );
	$capabilities = $render( 'capabilities' );
	$products     = $render( 'products' );
	$orders       = $render( 'orders' );

	$new_at  = strpos( $new_product, 'data-sp-subtab="newproduct"' );
	$caps_at = strpos( $new_product, 'data-sp-subtab="capabilities"' );
	$prod_at = strpos( $new_product, 'data-sp-subtab="products"' );
	$ord_at  = strpos( $new_product, 'data-sp-subtab="orders"' );
	$check(
		false !== $new_at && false !== $caps_at && false !== $prod_at && false !== $ord_at
		&& $new_at < $caps_at && $caps_at < $prod_at && $prod_at < $ord_at,
		'Store Copilot keeps the four mockup tabs in canonical New product, capabilities, products, orders order'
	);
	$check(
		false !== strpos( $new_product, 'data-sp-store-copilot' )
		&& false !== strpos( $new_product, 'data-sp-store-tab="newproduct"' )
		&& false !== strpos( $new_product, 'data-sp-store-new-product' )
		&& false !== strpos( $new_product, 'data-sp-store-example' )
		&& false !== strpos( $new_product, 'data-sp-store-proposal' )
		&& false !== strpos( $new_product, 'id="sp-woo-ttl"' ),
		'New product renders the proposal-before-draft controls, examples, and expiry target'
	);
	$check(
		false !== strpos( $capabilities, 'data-sp-store-capabilities' )
		&& 9 === substr_count( $capabilities, 'data-sp-store-capability-state=' )
		&& 6 === substr_count( $capabilities, 'data-sp-store-status-row' )
		&& false !== strpos( $capabilities, 'data-sp-store-setting="enabled"' )
		&& false !== strpos( $capabilities, 'data-sp-store-setting="enable_search"' )
		&& false !== strpos( $capabilities, 'Product editing by AI' )
		&& false !== strpos( $capabilities, 'Always create as draft' ),
		'What it can do renders the Phase 1 capability, status, live-setting, and locked-setting panels'
	);
	$check(
		false !== strpos( $products, 'data-sp-store-products' )
		&& false !== strpos( $products, 'Read-only here' ) && false !== strpos( $products, 'Descriptions tab' )
		&& false === strpos( $products, 'sp-stat-revenue' )
		&& false === strpos( $products, 'sp-sales-chart' )
		&& false === strpos( $products, 'sp-top-products' ),
		'Products is the compact read-only Store list and no longer renders analytics dashboard targets'
	);
	$check(
		false !== strpos( $orders, 'data-sp-store-orders' )
		&& 4 === substr_count( $orders, 'data-sp-store-order-permission=' )
		&& false !== strpos( $orders, 'visitor-facing order lookup is not registered' )
		&& false !== strpos( $orders, 'not registered as Store Copilot routes in Phase 1' )
		&& false === strpos( $orders, 'sp-low-stock' )
		&& false === strpos( $orders, 'sp-recent-orders' ),
		'Orders exposes the actual manager-only read contract and no former stock or recent-order dashboard UI'
	);

	$_GET['tab'] = 'newproduct';
	ob_start();
	( new WooCommerceSettings() )->render();
	$legacy = (string) ob_get_clean();
	$check(
		false !== strpos( $legacy, 'data-sp-store-tab="capabilities"' )
		&& false !== strpos( $legacy, 'data-sp-store-capabilities' )
		&& false === strpos( $legacy, 'AI-powered product management: create, edit, search, and bulk-update products' )
		&& false === strpos( $legacy, 'sp_woo_nonce' ),
		'Legacy WooCommerce alias opens canonical capabilities and no longer promises unshipped edit or bulk actions'
	);
} finally {
	$_GET = $original_get;
	wp_set_current_user( $original_user_id );
}

$failed = array_filter( $checks, static fn( array $check ): bool => ! $check['ok'] );
foreach ( $checks as $check ) {
	echo ( $check['ok'] ? 'PASS ' : 'FAIL ' ) . $check['name'] . PHP_EOL;
}
echo count( $checks ) . ' checks, ' . count( $failed ) . ' failed' . PHP_EOL;
exit( array() === $failed ? 0 : 1 );
