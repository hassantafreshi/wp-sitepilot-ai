<?php
/**
 * Local WordPress checks for Store Copilot's anonymous cart funnel (Connectors\CartFunnel).
 * Needs WooCommerce active. Nothing leaves the site.
 *
 * Proves it is off until the store manager turns "Count carts" on; each shopper's session
 * counts once per step (cart started, reached checkout); a cart kept from an earlier
 * visit counts as started when it reaches checkout; store staff are not counted; the
 * table holds only a day, a step and a number; old days are pruned; and the Orders card
 * shows the three steps (or how to turn counting on).
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Admin\StoreCopilotPage;
use AISAAutoPilot\Connectors\CartFunnel;
use AISAAutoPilot\Core\Settings;

$checks = array();
$check  = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array( 'ok' => $ok, 'name' => $name );
};
if ( ! function_exists( 'wc_load_cart' ) ) {
	echo "SKIP WooCommerce is not active\n";
	exit( 0 );
}

global $wpdb;
$original_settings = get_option( Settings::OPTION );
$funnel            = new CartFunnel();
CartFunnel::ensure_schema();
$table    = $funnel->table();
$original = $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test fixture.
$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test fixture.
$created = array();

// A shopper: a fresh WooCommerce session and cart, as on a first visit.
$new_shopper = static function (): void {
	wp_set_current_user( 0 );
	WC()->session = new WC_Session_Handler();
	WC()->session->init();
	WC()->cart = new WC_Cart();
	WC()->cart->get_cart_from_session();
};
// A checkout page view. WooCommerce caches its page detection once per request, so the
// test uses WooCommerce's own filter; the browser QA opens the real checkout page.
$view_checkout = static function () use ( $funnel ): void {
	add_filter( 'woocommerce_is_checkout', '__return_true' );
	$funnel->on_checkout_view();
	remove_filter( 'woocommerce_is_checkout', '__return_true' );
};
$counts = static function () use ( $funnel ): array {
	$t = $funnel->totals( 7 );
	return array( $t['cart'], $t['checkout'] );
};
$set = static function ( bool $on ): void {
	$request = new WP_REST_Request( 'POST', '/aisaautopilot/v1/woocommerce/admin-settings' );
	$request->set_param( 'enabled', true );
	$request->set_param( 'enable_search', false );
	$request->set_param( 'cart_funnel', $on );
	wp_set_current_user( 1 );
	rest_do_request( $request );
};
$render = static function (): string {
	wp_set_current_user( 1 );
	$_GET = array( 'page' => 'aisaautopilot-wc-dashboard', 'tab' => 'orders', 'days' => '7' );
	ob_start();
	( new StoreCopilotPage() )->render();
	$html = (string) ob_get_clean();
	$_GET = array();
	return $html;
};

try {
	wc_load_cart();
	foreach ( array( 'QA Funnel Mug', 'QA Funnel Cap' ) as $name ) {
		$product = new WC_Product_Simple();
		$product->set_name( $name );
		$product->set_regular_price( '12' );
		$product->set_status( 'publish' );
		$created[] = (int) $product->save();
	}
	list( $mug, $cap ) = $created;

	// 1. Off by default: adding to a cart and opening checkout count nothing.
	$set( false );
	$new_shopper();
	WC()->cart->add_to_cart( $mug );
	$view_checkout();
	$check( ! CartFunnel::enabled() && array( 0, 0 ) === $counts(), 'Off by default: nothing is counted' );
	$check( false !== strpos( $render(), 'data-sp-cart-steps-off' ), 'Switched off, the Orders card says how to turn counting on' );

	// 2. On through the Store settings route; a save without the switch keeps it.
	$set( true );
	$keep = new WP_REST_Request( 'POST', '/aisaautopilot/v1/woocommerce/admin-settings' );
	$keep->set_param( 'enabled', true );
	$keep->set_param( 'enable_search', false );
	rest_do_request( $keep );
	$check( CartFunnel::enabled(), 'The Store settings route turns Count carts on, and a save without it keeps it' );

	// 3. One shopper: two products and two checkout visits count one cart and one checkout.
	$new_shopper();
	WC()->cart->add_to_cart( $mug );
	WC()->cart->add_to_cart( $cap );
	$after_cart = $counts();
	$view_checkout();
	$view_checkout();
	$check( array( 1, 0 ) === $after_cart && array( 1, 1 ) === $counts(), 'A shopper counts once per step, however many products or checkout visits' );

	// 4. A second shopper who only adds to the cart: two carts, one checkout.
	$new_shopper();
	WC()->cart->add_to_cart( $cap );
	$check( array( 2, 1 ) === $counts(), 'A second shopper who leaves before checkout adds a cart only' );

	// 5. A cart kept from an earlier visit counts as started when it reaches checkout.
	$set( false );
	$new_shopper();
	WC()->cart->add_to_cart( $mug ); // Added before counting was turned on.
	$set( true );
	wp_set_current_user( 0 );
	$view_checkout();
	$check( array( 3, 2 ) === $counts(), 'Reaching checkout with a cart from an earlier visit counts it as started too' );

	// 6. Store staff are not counted; an empty cart at checkout is not counted.
	wp_set_current_user( 1 );
	WC()->session = new WC_Session_Handler();
	WC()->session->init();
	WC()->cart = new WC_Cart();
	WC()->cart->add_to_cart( $mug );
	$view_checkout();
	$new_shopper();
	WC()->cart->empty_cart();
	$view_checkout();
	$check( array( 3, 2 ) === $counts(), 'Store staff, and a checkout visit with an empty cart, are not counted' );

	// 7. Only a day, a step and a number are stored.
	$columns = $wpdb->get_col( "DESCRIBE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test fixture.
	$check( array( 'day', 'stage', 'hits' ) === $columns, 'The table holds only the day, the step and the count: nothing about the shopper' );

	// 8. The Orders card shows the steps; days past KEEP_DAYS are pruned.
	$html = $render();
	$check(
		1 === preg_match( '/data-sp-cart-step="cart">.*?data-sp-cart-step-count>3</s', $html )
		&& 1 === preg_match( '/data-sp-cart-step="checkout">.*?data-sp-cart-step-count>2</s', $html )
		&& false !== strpos( $html, 'data-sp-cart-step="paid"' ) && false === strpos( $html, 'data-sp-cart-steps-off' )
		&& false === strpos( $html, 'carts left before checkout are not counted' ),
		'Store Copilot > Orders shows carts started, reached checkout and paid, and no longer says carts are not counted'
	);
	$wpdb->insert( $table, array( 'day' => wp_date( 'Y-m-d', time() - 200 * DAY_IN_SECONDS ), 'stage' => 'cart', 'hits' => 9 ) );
	$pruned = $funnel->prune();
	$check( 1 === $pruned && array( 3, 2 ) === $counts(), 'The daily retention job deletes days older than ' . CartFunnel::KEEP_DAYS . ' and keeps recent ones' );
} finally {
	wp_set_current_user( 1 );
	foreach ( $created as $id ) {
		wp_delete_post( $id, true );
	}
	$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test fixture.
	foreach ( (array) $original as $row ) {
		$wpdb->insert( $table, $row );
	}
	update_option( Settings::OPTION, $original_settings );
}

$failed = 0;
foreach ( $checks as $c ) {
	echo ( $c['ok'] ? 'PASS ' : 'FAIL ' ) . $c['name'] . "\n";
	$failed += $c['ok'] ? 0 : 1;
}
echo count( $checks ) . " checks, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
